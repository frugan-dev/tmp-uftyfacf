<?php

declare(strict_types=1);

/*
 * This file is part of the WordPress plugin "Upload Field to YouTube for ACF".
 *
 * (ɔ) Frugan <dev@frugan.it>
 *
 * This source file is subject to the GNU GPLv3 or later license that is bundled
 * with this source code in the file LICENSE.
 */

namespace WpSpaghetti\UFTYFACF\Service;

use DI\Container;
use Google\Service\YouTube;
use Google\Service\YouTube\Video;
use Google\Service\YouTube\VideoSnippet;
use GuzzleHttp\Psr7\Request;
use WpSpaghetti\UFTYFACF\Trait\HookTrait;
use WpSpaghetti\WpLogger\Logger;

if (!\defined('ABSPATH')) {
    exit;
}

/**
 * YouTube API Service for handling YouTube-specific operations.
 */
class YoutubeApiService
{
    use HookTrait;

    /**
     * Default field settings.
     */
    private array $field_defaults;

    /**
     * Environment settings.
     */
    private array $env_settings;

    public function __construct(
        private Container $container,
        private GoogleClientManager $google_client_manager,
        private Logger $logger
    ) {
        $this->init_hook($container);

        $this->field_defaults = $this->container->get('field_defaults');
        $this->env_settings = $this->container->get('env_settings');
    }

    /**
     * Retrieve playlists filtered by privacy status.
     *
     * This method fetches playlists from the user's YouTube channel
     * that match the specified privacy status.
     *
     * @param mixed $privacy_status
     */
    public function get_playlists_by_privacy_status($privacy_status): array
    {
        $result = [];

        try {
            $this->do_action(__FUNCTION__.'_before', $privacy_status);

            $this->logger->debug('Retrieving playlists by privacy status', [
                'privacy_status' => $privacy_status,
            ]);

            $this->google_client_manager->check_oauth_token();
            $googleServiceYouTube = new YouTube($this->google_client_manager->get_google_client());
            $params = [
                'part' => 'snippet,status',
                'mine' => true,
                'maxResults' => 50,
            ];

            // Quota impact: A call to this method has a quota cost of 1 unit.
            // https://developers.google.com/youtube/v3/getting-started#quota
            // https://developers.google.com/youtube/v3/determine_quota_cost
            $response = $googleServiceYouTube->playlists->listPlaylists('snippet,status', $params);

            $this->logger->notice('YouTube API quota usage', [
                'channel' => 'youtube_api_quota',
                'resource' => 'playlists',
                'method' => 'listPlaylists',
                'quota' => 1,
            ]);

            $this->logger->debug('Playlists retrieved successfully', [
                'privacy_status' => $privacy_status,
                'total_playlists' => \count($response->getItems()),
            ]);

            foreach ($response->getItems() as $item) {
                $playlistId = $item->getId();
                if (!isset($result[$playlistId])) {
                    $status = $item->getStatus();
                    if ($status && $status->getPrivacyStatus() === $privacy_status) {
                        $result[$playlistId] = [
                            'id' => $playlistId,
                            'title' => $item->getSnippet()->getTitle(),
                        ];
                    }
                }
            }

            if ($result) {
                $result = [
                    'items' => array_values($result),
                ];

                if (!empty($nextPageToken = $response->getNextPageToken())) {
                    $result['nextPageToken'] = $nextPageToken;
                }

                $this->logger->debug('Successfully processed playlists', [
                    'privacy_status' => $privacy_status,
                    'matching_playlists' => \count($result['items']),
                ]);
            }

            $this->do_action(__FUNCTION__.'_after', $privacy_status, $result);
        } catch (\Exception $exception) {
            $this->logger->error($exception, [
                'privacy_status' => $privacy_status,
                'response' => $response ?? null,
            ]);
            $this->do_action(__FUNCTION__.'_error', $exception, $privacy_status);
        }

        return $result;
    }

    /**
     * Get YouTube upload URL for resumable upload.
     *
     * This method prepares the initial request to YouTube's resumable upload API
     * and returns the upload URL that will be used for the actual file upload.
     *
     * Note: Using wp_remote_* functions instead of Google API client methods
     * because the Google API client's videos->insert() with resumable option
     * does not work correctly for resumable uploads.
     */
    public function get_youtube_upload_url(int $post_id, array $field): string
    {
        $this->do_action(__FUNCTION__.'_before', $post_id, $field);

        // Create the request using the common method
        $request_data = $this->create_resumable_upload_request($post_id, $field);

        // Use WordPress HTTP API for the initial request
        // Quota impact: A call to this method has a quota cost of 1600 units.
        // https://developers.google.com/youtube/v3/getting-started#quota
        // https://developers.google.com/youtube/v3/determine_quota_cost
        $response = wp_remote_post($request_data['url'], [
            'headers' => $request_data['headers'],
            'body' => $request_data['body'],
            'timeout' => 30,
            'sslverify' => true,
        ]);

        $this->logger->notice('YouTube API quota usage', [
            'channel' => 'youtube_api_quota',
            'resource' => 'videos',
            'method' => 'insert_resumable',
            'quota' => 1600,
        ]);

        if (is_wp_error($response)) {
            $this->logger->error($response);

            throw new \Exception(esc_html($response->get_error_message()));
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        $response_headers = wp_remote_retrieve_headers($response);

        $this->logger->debug('Response received', [
            'response_code' => $response_code,
            'response_body' => $response_body,
            'response_headers' => $response_headers,
        ]);

        if (!\in_array($response_code, [200, 201, 202], true)) {
            $this->logger->error('YouTube API error', [
                'response_code' => $response_code,
                'response_body' => $response_body,
                'response_headers' => $response_headers,
            ]);

            // Try to parse error message
            $error_data = json_decode($response_body, true);
            // translators: %d: HTTP response code
            $error_message = $error_data['error']['message'] ?? \sprintf(__('YouTube API returned error code: %1$d', 'upload-field-to-youtube-for-acf'), $response_code);

            throw new \RuntimeException(esc_html($error_message));
        }

        // Get upload URL from Location header
        $upload_url = $response_headers['location'] ?? $response_headers['Location'] ?? null;

        if (!$upload_url) {
            // translators: %s: expected response field name
            $error_message = \sprintf(__('Unable to retrieve "%1$s" from Google API response', 'upload-field-to-youtube-for-acf'), 'upload URL');

            $this->logger->error($error_message, [
                'response_headers' => $response_headers,
                'post_id' => $post_id,
            ]);

            throw new \Exception(esc_html($error_message));
        }

        $this->logger->debug('Video upload URL retrieved successfully', [
            'upload_url' => substr($upload_url, 0, 50).'...', // Log only part for security
            'post_id' => $post_id,
        ]);

        $this->do_action(__FUNCTION__.'_after', $upload_url, $post_id, $field);

        return $upload_url;
    }

    /**
     * Upload video file directly to YouTube via server-side processing.
     *
     * This method handles the complete upload process on the server side,
     * including file validation, metadata preparation, and chunked upload to YouTube.
     *
     * Note: Using wp_remote_* functions instead of Google API client's MediaFileUpload
     * because the Google API client with resumable option does not work correctly
     * for chunked uploads in our WordPress environment.
     *
     * @param array $file_data File data from wp_handle_upload()
     *
     * @return string Video ID
     *
     * @throws \Exception
     */
    public function upload_video_to_youtube(int $post_id, array $field, array $file_data): string
    {
        try {
            $this->do_action(__FUNCTION__.'_before', $post_id, $field, $file_data);

            $this->logger->debug('Starting server-side video upload to YouTube', [
                'post_id' => $post_id,
                'field' => $field,
                'file_data' => $file_data,
                'chunk_max' => $this->env_settings['resumable_upload_max_chunks'],
            ]);

            // Validate file data coming from wp_handle_upload
            if (empty($file_data['tmp_name']) || !file_exists($file_data['tmp_name'])) {
                throw new \InvalidArgumentException(__('Invalid file data provided', 'upload-field-to-youtube-for-acf'));
            }

            if (empty($file_data['size']) || $file_data['size'] <= 0) {
                throw new \InvalidArgumentException(__('Invalid file size', 'upload-field-to-youtube-for-acf'));
            }

            $upload_uri = $this->get_youtube_upload_url($post_id, $field);

            // Upload the video file directly
            $chunkSizeBytes = 1 * 1024 * 1024; // 1MB chunks
            $fileSize = $file_data['size'];

            // Read file content using WP_Filesystem
            $file_content = $this->container->get('wp_filesystem')->get_contents($file_data['tmp_name']);
            if (false === $file_content) {
                throw new \RuntimeException(__('Could not read uploaded file', 'upload-field-to-youtube-for-acf'));
            }

            $uploaded = 0;
            $chunk_count = 0;

            while ($uploaded < $fileSize) {
                $chunk = substr($file_content, $uploaded, $chunkSizeBytes);
                // Fix PHPStan issue: substr can return false, so check properly
                // @phpstan-ignore-next-line
                if (false === $chunk || '' === $chunk) {
                    throw new \RuntimeException(__('Error reading file chunk', 'upload-field-to-youtube-for-acf'));
                }

                ++$chunk_count;
                $chunkSize = \strlen($chunk);
                $start = $uploaded;
                $end = $uploaded + $chunkSize - 1;

                $this->logger->debug('Uploading chunk', [
                    'chunk_number' => $chunk_count,
                    'chunk_size' => $chunkSize,
                    'range' => "bytes {$start}-{$end}/{$fileSize}",
                ]);

                // Upload this chunk (0 quota cost - part of resumable upload)
                $response = wp_remote_request($upload_uri, [
                    'method' => 'PUT',
                    'headers' => [
                        'Content-Range' => "bytes {$start}-{$end}/{$fileSize}",
                        'Content-Length' => $chunkSize,
                        'Content-Type' => $file_data['type'],
                    ],
                    'body' => $chunk,
                    'timeout' => 60,
                    'sslverify' => true,
                ]);

                if (is_wp_error($response)) {
                    $this->logger->error($response);

                    throw new \Exception($response->get_error_message());
                }

                $response_code = wp_remote_retrieve_response_code($response);
                $response_body = wp_remote_retrieve_body($response);

                $this->logger->debug('Chunk upload response', [
                    'chunk_number' => $chunk_count,
                    'response_code' => $response_code,
                    'response_body_length' => \strlen($response_body),
                ]);

                // @phpmnd-ignore-next-line
                if ((int) 308 === $response_code) {
                    // Continue uploading (Resume Incomplete status)
                    $uploaded += $chunkSize;
                // @phpmnd-ignore-next-line
                } elseif ($response_code >= (int) 200 && $response_code < (int) 300) {
                    // Upload complete
                    $uploaded = $fileSize;

                    // Parse response to get video ID
                    $result = json_decode($response_body, true);
                    if (!$result || !isset($result['id'])) {
                        throw new \UnexpectedValueException(__('Upload completed but no video ID returned', 'upload-field-to-youtube-for-acf'));
                    }

                    $video_id = $result['id'];

                    $this->logger->info('Video uploaded successfully', [
                        'video_id' => $video_id,
                        'post_id' => $post_id,
                        'total_chunks' => $chunk_count,
                        'total_bytes' => $fileSize,
                    ]);

                    $this->do_action(__FUNCTION__.'_after', $video_id, $post_id, $field);

                    return $video_id;
                } else {
                    throw new \RuntimeException("Upload failed with HTTP {$response_code}: {$response_body}");
                }

                // Safety check to prevent infinite loops
                if ($chunk_count > $this->env_settings['resumable_upload_max_chunks']) {
                    throw new \RuntimeException(__('Upload exceeded maximum chunk limit', 'upload-field-to-youtube-for-acf'));
                }
            }

            throw new \UnexpectedValueException(__('Upload loop completed but video ID not found', 'upload-field-to-youtube-for-acf'));
        } catch (\Exception $exception) {
            throw $exception;
        }
    }

    /**
     * Retrieve video ID from a recent upload by checking recently uploaded videos.
     *
     * @return null|string Video ID if found
     */
    public function get_video_id_from_upload(string $upload_id): ?string
    {
        $this->do_action(__FUNCTION__.'_before', $upload_id);

        $this->logger->debug('Attempting to retrieve video ID from upload', [
            'upload_id' => $upload_id,
        ]);

        $this->google_client_manager->check_oauth_token();
        $googleServiceYouTube = new YouTube($this->google_client_manager->get_google_client());

        // Get channel uploads playlist
        // Quota impact: A call to this method has a quota cost of 1 unit.
        // https://developers.google.com/youtube/v3/getting-started#quota
        // https://developers.google.com/youtube/v3/determine_quota_cost
        $channelsResponse = $googleServiceYouTube->channels->listChannels('contentDetails', [
            'mine' => true,
        ]);

        $this->logger->notice('YouTube API quota usage', [
            'channel' => 'youtube_api_quota',
            'resource' => 'channels',
            'method' => 'listChannels',
            'quota' => 1,
        ]);

        if (empty($channelsResponse->getItems())) {
            $this->logger->warning('No channel found for authenticated user');

            return null;
        }

        $channel = $channelsResponse->getItems()[0];
        $uploadsPlaylistId = $channel->getContentDetails()->getRelatedPlaylists()->getUploads();

        // Configuration from container
        $maxAttempts = $this->env_settings['video_id_retrieval_max_attempts'];
        $sleepInterval = $this->env_settings['video_id_retrieval_sleep_interval'];
        $initialSleep = $this->env_settings['video_id_retrieval_initial_sleep'];

        $this->logger->debug('Starting video ID retrieval loop', [
            'upload_id' => $upload_id,
            'playlist_id' => $uploadsPlaylistId,
            'max_attempts' => $maxAttempts,
            'sleep_interval' => $sleepInterval,
            'initial_sleep' => $initialSleep,
        ]);

        // Initial sleep before first attempt
        sleep($initialSleep);

        $attempt = 1;
        while ($attempt <= $maxAttempts) {
            $this->logger->debug('Video ID retrieval attempt', [
                'attempt' => $attempt,
                'max_attempts' => $maxAttempts,
                'upload_id' => $upload_id,
            ]);

            try {
                // Quota impact: A call to this method has a quota cost of 1 unit.
                // https://developers.google.com/youtube/v3/getting-started#quota
                // https://developers.google.com/youtube/v3/determine_quota_cost
                $response = $googleServiceYouTube->playlistItems->listPlaylistItems('snippet', [
                    'playlistId' => $uploadsPlaylistId,
                    'maxResults' => 10,
                ]);

                $this->logger->notice('YouTube API quota usage', [
                    'channel' => 'youtube_api_quota',
                    'resource' => 'playlistItems',
                    'method' => 'listPlaylistItems',
                    'quota' => 1,
                ]);

                $this->logger->debug('Checking playlist items', [
                    'attempt' => $attempt,
                    'found_items' => \count($response->getItems()),
                    'time_window_seconds' => $this->env_settings['recent_upload_time_window'],
                ]);

                foreach ($response->getItems() as $item) {
                    $uploadTime = strtotime($item->getSnippet()->getPublishedAt());
                    $timeDiff = time() - $uploadTime;

                    $this->logger->debug('Checking video in uploads playlist', [
                        'attempt' => $attempt,
                        'video_id' => $item->getSnippet()->getResourceId()->getVideoId(),
                        'upload_time' => $item->getSnippet()->getPublishedAt(),
                        'time_diff_seconds' => $timeDiff,
                        'title' => $item->getSnippet()->getTitle(),
                    ]);

                    // Check if this video was uploaded recently (within last X minutes)
                    if ($timeDiff < $this->env_settings['recent_upload_time_window']) {
                        $video_id = $item->getSnippet()->getResourceId()->getVideoId();

                        $this->logger->debug('Found recent video ID', [
                            'video_id' => $video_id,
                            'upload_id' => $upload_id,
                            'attempt' => $attempt,
                            'time_diff_seconds' => $timeDiff,
                        ]);

                        $this->do_action(__FUNCTION__.'_after', $video_id, $upload_id);

                        return $video_id;
                    }
                }
            } catch (\Exception $exception) {
                $this->logger->error('Error during video ID retrieval attempt', [
                    'exception' => $exception,
                    'attempt' => $attempt,
                    'upload_id' => $upload_id,
                ]);
            }

            // If this wasn't the last attempt, sleep before next one
            if ($attempt < $maxAttempts) {
                $this->logger->debug('Sleeping before next attempt', [
                    'attempt' => $attempt,
                    'sleep_seconds' => $sleepInterval,
                ]);
                sleep($sleepInterval);
            }

            ++$attempt;
        }

        $this->logger->warning('No recent video found after all attempts', [
            'upload_id' => $upload_id,
            'attempts_made' => $maxAttempts,
            'time_window_seconds' => $this->env_settings['recent_upload_time_window'],
            'total_time_elapsed' => $initialSleep + (($maxAttempts - 1) * $sleepInterval),
        ]);

        return null;
    }

    /**
     * Retrieve videos from a specific YouTube playlist.
     */
    public function get_videos_by_playlist(string $playlist_id, string $privacy_status): array
    {
        $this->do_action(__FUNCTION__.'_before', $playlist_id, $privacy_status);

        $this->logger->debug('Retrieving videos from playlist', [
            'playlist_id' => $playlist_id,
            'privacy_status' => $privacy_status,
        ]);

        $this->google_client_manager->check_oauth_token();
        $googleServiceYouTube = new YouTube($this->google_client_manager->get_google_client());
        $params = [
            'playlistId' => $playlist_id,
            'maxResults' => 50,
        ];

        // Quota impact: A call to this method has a quota cost of 1 unit.
        // https://developers.google.com/youtube/v3/getting-started#quota
        // https://developers.google.com/youtube/v3/determine_quota_cost
        $response = $googleServiceYouTube->playlistItems->listPlaylistItems('snippet,status', $params);

        $this->logger->notice('YouTube API quota usage', [
            'channel' => 'youtube_api_quota',
            'resource' => 'playlistItems',
            'method' => 'listPlaylistItems',
            'quota' => 1,
        ]);

        $this->logger->debug('Videos retrieved successfully from playlist', [
            'playlist_id' => $playlist_id,
            'privacy_status' => $privacy_status,
            'response' => $response,
        ]);

        $result = [];
        foreach ($response->getItems() as $item) {
            $videoId = $item->getSnippet()->getResourceId()->getVideoId();
            if (!isset($result[$videoId])) {
                $status = $item->getStatus();
                if ($status && $status->getPrivacyStatus() === $privacy_status) {
                    $result[$videoId] = [
                        'id' => $videoId,
                        'title' => $item->getSnippet()->getTitle(),
                    ];
                }
            }
        }

        if ($result) {
            $result = [
                'items' => array_values($result),
            ];

            if (!empty($nextPageToken = $response->getNextPageToken())) {
                $result['nextPageToken'] = $nextPageToken;
            }

            $this->logger->debug('Successfully processed playlist videos', [
                'playlist_id' => $playlist_id,
                'matching_videos' => \count($result['items']),
            ]);
        }

        $this->do_action(__FUNCTION__.'_after', $playlist_id, $privacy_status, $response, $result);

        return $result;
    }

    /**
     * Validate video exists in user's YouTube account.
     *
     * @throws \Exception
     */
    public function validate_video_exists(string $video_id): bool
    {
        $this->google_client_manager->check_oauth_token();
        $googleServiceYouTube = new YouTube($this->google_client_manager->get_google_client());

        // Quota impact: A call to this method has a quota cost of 1 unit.
        // https://developers.google.com/youtube/v3/getting-started#quota
        // https://developers.google.com/youtube/v3/determine_quota_cost
        $response = $googleServiceYouTube->videos->listVideos('snippet', [
            'id' => $video_id,
        ]);

        $this->logger->notice('YouTube API quota usage', [
            'channel' => 'youtube_api_quota',
            'resource' => 'videos',
            'method' => 'listVideos',
            'quota' => 1,
        ]);

        $this->logger->debug('Video validation result', [
            'video_id' => $video_id,
            'exists' => !empty($response->getItems()),
        ]);

        return !empty($response->getItems());
    }

    /**
     * Update YouTube video metadata.
     *
     * @return Video Response from YouTube API
     *
     * @throws \Exception
     */
    public function update_video_metadata(string $video_id, array $data): Video
    {
        $this->google_client_manager->check_oauth_token();
        $googleServiceYouTube = new YouTube($this->google_client_manager->get_google_client());

        // Quota impact: A call to this method has a quota cost of 1 unit.
        // https://developers.google.com/youtube/v3/getting-started#quota
        // https://developers.google.com/youtube/v3/determine_quota_cost
        $response = $googleServiceYouTube->videos->listVideos('snippet', ['id' => $video_id]);

        $this->logger->notice('YouTube API quota usage', [
            'channel' => 'youtube_api_quota',
            'resource' => 'videos',
            'method' => 'listVideos',
            'quota' => 1,
        ]);

        $videoSnippet = $response->getItems()[0]->getSnippet();

        $googleServiceYouTubeVideoSnippet = new VideoSnippet();
        $googleServiceYouTubeVideoSnippet->setCategoryId($data['category_id'] ?? $videoSnippet->getCategoryId());
        $googleServiceYouTubeVideoSnippet->setTitle($data['title']);

        if (!empty($data['description'])) {
            $googleServiceYouTubeVideoSnippet->setDescription($data['description']);
        }

        $googleServiceYouTubeVideo = new Video();
        $googleServiceYouTubeVideo->setId($video_id);
        $googleServiceYouTubeVideo->setSnippet($googleServiceYouTubeVideoSnippet);

        // Quota impact: A call to this method has a quota cost of 50 units.
        // https://developers.google.com/youtube/v3/getting-started#quota
        // https://developers.google.com/youtube/v3/determine_quota_cost
        $response = $googleServiceYouTube->videos->update('snippet', $googleServiceYouTubeVideo);

        $this->logger->notice('YouTube API quota usage', [
            'channel' => 'youtube_api_quota',
            'resource' => 'videos',
            'method' => 'update',
            'quota' => 50,
        ]);

        $this->logger->info(__('Video updated successfully', 'upload-field-to-youtube-for-acf'), [
            'video_id' => $video_id,
            'data' => $data,
        ]);

        return $response;
    }

    /**
     * Delete YouTube video.
     *
     * @throws \Exception
     */
    public function delete_video(string $video_id): bool
    {
        $this->google_client_manager->check_oauth_token();
        $googleServiceYouTube = new YouTube($this->google_client_manager->get_google_client());

        // Quota impact: A call to this method has a quota cost of 50 units.
        // https://developers.google.com/youtube/v3/getting-started#quota
        // https://developers.google.com/youtube/v3/determine_quota_cost
        $response = $googleServiceYouTube->videos->delete($video_id);

        $this->logger->notice('YouTube API quota usage', [
            'channel' => 'youtube_api_quota',
            'resource' => 'videos',
            'method' => 'delete',
            'quota' => 50,
        ]);

        $this->logger->info(__('Video deleted successfully', 'upload-field-to-youtube-for-acf'), [
            'video_id' => $video_id,
        ]);

        return true;
    }

    /**
     * Create the initial resumable upload request for YouTube.
     *
     * Note: Using wp_remote_* functions instead of Google API client's videos->insert()
     * because the Google API client with uploadType=resumable parameter does not work
     * correctly for resumable uploads in our WordPress environment.
     *
     * @param int   $post_id The post ID
     * @param array $field   The field configuration
     *
     * @return array Request data with URL, headers, and body
     *
     * @throws \Exception
     */
    private function create_resumable_upload_request(int $post_id, array $field): array
    {
        $this->do_action(__FUNCTION__.'_before', $post_id, $field);

        $this->logger->debug('Creating resumable upload request', [
            'post_id' => $post_id,
            'field' => $field,
        ]);

        // Get the post title and validate it
        $post_title = get_the_title($post_id);
        if (empty($post_title)) {
            throw new \InvalidArgumentException(esc_html__('Post title is required', 'upload-field-to-youtube-for-acf'));
        }

        // Prepare tags array and validate
        $tags = array_filter(array_map('trim', explode(',', $field['tags'] ?? '')));

        // Prepare video metadata with strict validation
        $metadata = [
            'snippet' => [
                'title' => $post_title,
                'categoryId' => (string) ($field['category_id'] ?? $this->field_defaults['category_id']),
            ],
            'status' => [
                'privacyStatus' => $field['privacy_status'] ?? $this->field_defaults['privacy_status'],
                'selfDeclaredMadeForKids' => (bool) ($field['made_for_kids'] ?? $this->field_defaults['made_for_kids']),
            ],
        ];

        // Add tags only if they exist
        if (!empty($tags)) {
            $metadata['snippet']['tags'] = $tags;
        }

        // Add description only if it exists
        $excerpt = get_post_field('post_excerpt', $post_id);
        if (!empty($excerpt)) {
            $metadata['snippet']['description'] = $excerpt;
        }

        $this->logger->debug('Video metadata prepared', [
            'metadata' => $metadata,
        ]);

        // Validate privacy status
        // All videos uploaded via the videos.insert endpoint from unverified API projects
        // created after 28 July 2020 will be restrictedto private viewing mode.
        // To lift this restriction, each API project mustundergo an audit to verify compliance
        // with the Terms of Service. Please see the API Revision History for more details.
        if (!\in_array($metadata['status']['privacyStatus'], ['private', 'public', 'unlisted'], true)) {
            // translators: %s: privacy status value
            throw new \InvalidArgumentException(\sprintf(esc_html__('Invalid privacy status "%1$s"', 'upload-field-to-youtube-for-acf'), esc_html($metadata['status']['privacyStatus'])));
        }

        // Prepare request headers
        $this->google_client_manager->check_oauth_token();
        $access_token = $this->google_client_manager->get_access_token();
        $headers = [
            'Authorization' => 'Bearer '.$access_token['access_token'],
            'Content-Type' => 'application/json; charset=UTF-8',
            'X-Upload-Content-Type' => 'video/*',
        ];

        // https://stackoverflow.com/a/74402514/3929620
        // https://developers.google.com/youtube/v3/guides/using_resumable_upload_protocol
        // https://github.com/youtube/api-samples/blob/master/php/resumable_upload.php
        // https://github.com/googleapis/google-api-php-client
        $request_data = [
            'url' => 'https://www.googleapis.com/upload/youtube/v3/videos?uploadType=resumable&part=snippet,status',
            'headers' => $headers,
            'body' => wp_json_encode($metadata),
            'metadata' => $metadata, // Include for logging/debugging
        ];

        // Log request data with redacted Authorization header for security
        $safe_request_data = $request_data;
        $safe_request_data['headers'] = array_merge($headers, ['Authorization' => 'Bearer [REDACTED]']);

        $this->logger->debug('Resumable upload request prepared', [
            'request_data' => $safe_request_data,
        ]);

        $this->do_action(__FUNCTION__.'_after', $request_data, $post_id, $field);

        return $request_data;
    }
}
