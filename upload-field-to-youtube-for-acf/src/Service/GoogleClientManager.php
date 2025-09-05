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
use Google\Client;
use Google\Service\Oauth2;
use Google\Service\YouTube;
use WpSpaghetti\UFTYFACF\Trait\HookTrait;
use WpSpaghetti\WpEnv\Environment;
use WpSpaghetti\WpLogger\Logger;

if (!\defined('ABSPATH')) {
    exit;
}

/**
 * Google Client Manager for handling OAuth and client configuration.
 */
class GoogleClientManager
{
    use HookTrait;

    private ?Client $client = null;
    private array|bool|null $access_token = null;

    public function __construct(
        private Container $container,
        private CacheHandler $cache_handler,
        private Logger $logger
    ) {
        $this->init_hook($container);
    }

    /**
     * Initialize and configure the Google Client for YouTube API access.
     *
     * This method sets up the Google Client with OAuth credentials,
     * scopes, and other necessary configuration for YouTube API access.
     */
    public function set_google_client(): void
    {
        if ($this->get_google_client() instanceof Client) {
            return;
        }

        $data = $this->apply_filters(__FUNCTION__.'_data', [
            'client_id' => Environment::getRequired('WPSPAGHETTI_UFTYFACF_GOOGLE_OAUTH_CLIENT_ID'),
            'client_secret' => Environment::getRequired('WPSPAGHETTI_UFTYFACF_GOOGLE_OAUTH_CLIENT_SECRET'),
            'redirect_uri' => admin_url(),
            'access_type' => 'offline',
            'prompt' => 'select_account consent',
        ]);

        $this->client = new Client();
        $this->client->setClientId($data['client_id']);
        $this->client->setClientSecret($data['client_secret']);
        $this->client->setRedirectUri($data['redirect_uri']);
        $this->client->setAccessType($data['access_type']);
        $this->client->setPrompt($data['prompt']);

        $scopes = $this->apply_filters(__FUNCTION__.'_scopes', [
            Oauth2::USERINFO_EMAIL,
            YouTube::YOUTUBE_FORCE_SSL,
            YouTube::YOUTUBE_UPLOAD,
        ]);

        foreach ($scopes as $scope) {
            $this->client->addScope($scope);
        }

        // Set Wonolog logger for Google Client if available
        $wonolog_logger = $this->logger->getWonologLogger();
        if (null !== $wonolog_logger) {
            // https://github.com/inpsyde/Wonolog/pull/55
            $this->client->setLogger($wonolog_logger);
        }

        $this->do_action(__FUNCTION__.'_after', $this->client);

        $this->client = $this->apply_filters(__FUNCTION__.'_client', $this->client);
    }

    public function get_google_client(): ?Client
    {
        return $this->client;
    }

    public function set_access_token(array|bool|null $token): void
    {
        $this->access_token = $token;
    }

    public function get_access_token(): array|bool|null
    {
        return $this->access_token;
    }

    /**
     * Check and refresh OAuth token if necessary.
     *
     * This method validates the current access token and refreshes it
     * if expired, ensuring continuous API access to YouTube.
     */
    public function check_oauth_token(): void
    {
        $this->set_access_token($this->cache_handler->get_access_token());

        $access_token = $this->get_access_token();
        if (!empty($access_token)) {
            $this->set_google_client();

            try {
                // Validate token format before using it
                if (!$this->cache_handler->is_valid_token_format($access_token)) {
                    throw new \InvalidArgumentException(__('Token format validation failed', 'upload-field-to-youtube-for-acf'));
                }

                $this->client->setAccessToken($access_token);
            } catch (\Exception $exception) {
                // FIXED - https://github.com/inpsyde/Wonolog/blob/2.x/src/HookLogFactory.php#L135
                // use `$exception->getMessage()` instead of `$exception`, because Wonolog
                // assigns the ERROR level to messages that are instances of Throwable
                $this->logger->warning($exception->getMessage(), [
                    'exception'=> $exception,
                    'access_token' => $this->cache_handler->sanitize_token_for_logging($access_token),
                    'token_type' => \gettype($access_token),
                    'token_keys' => \is_array($access_token) ? array_keys($access_token) : 'not_array',
                    'cache_info' => $this->cache_handler->get_cache_info(),
                ]);

                // Clean up corrupted token
                $this->cache_handler->delete_access_token();
                $this->set_access_token(null);

                return;
            }

            try {
                if ($this->client->isAccessTokenExpired()) {
                    $refresh_token = $this->client->getRefreshToken();
                    if (!empty($refresh_token)) {
                        $new_token = $this->client->fetchAccessTokenWithRefreshToken($refresh_token);

                        // Validate the new token before setting it
                        if (!$this->cache_handler->is_valid_token_format($new_token)) {
                            throw new \UnexpectedValueException(__('New token format validation failed', 'upload-field-to-youtube-for-acf'));
                        }

                        $this->set_access_token($new_token);
                        $this->client->setAccessToken($new_token);

                        // Save with cache handling
                        $this->cache_handler->save_access_token($new_token);
                    } else {
                        // translators: %s: field name (refresh_token)
                        throw new \UnexpectedValueException(\sprintf(__('Unable to retrieve "%1$s"', 'upload-field-to-youtube-for-acf'), 'refresh_token'));
                    }
                }
            } catch (\Exception $exception) {
                $this->logger->error($exception, [
                    'access_token' => $this->cache_handler->sanitize_token_for_logging($access_token),
                    'refresh_token' => isset($refresh_token) ? $this->cache_handler->sanitize_token_for_logging($refresh_token) : null,
                    'cache_info' => $this->cache_handler->get_cache_info(),
                ]);
                $this->cache_handler->delete_access_token();
                $this->set_access_token(null);
            }
        }
    }

    /**
     * Handle OAuth authorization flow for YouTube API access.
     *
     * This method manages the complete OAuth flow including authorization
     * URL generation, token exchange, and user authentication status.
     *
     * @return array Status information about the OAuth process
     */
    public function handle_oauth(): array
    {
        try {
            Environment::validateRequired([
                'WPSPAGHETTI_UFTYFACF_GOOGLE_OAUTH_CLIENT_ID',
                'WPSPAGHETTI_UFTYFACF_GOOGLE_OAUTH_CLIENT_SECRET',
            ]);
        } catch (\Exception $exception) {
            $data = [
                'status' => 'error',
                'message' => $exception->getMessage(),
            ];

            $this->logger->error($exception);

            return $data;
        }

        $this->check_oauth_token();

        if (empty($access_token = $this->get_access_token()) || !isset($access_token['access_token'])) {
            if (!current_user_can('manage_options') && !current_user_can('manage_'.$this->container->get('plugin_undername'))) {
                $data = [
                    'status' => 'error',
                    'message' => __('App not authorized, contact your system administrator.', 'upload-field-to-youtube-for-acf'),
                ];

                $this->logger->error($data);

                return $data;
            }

            $this->set_google_client();

            // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Google OAuth callback parameter
            if (isset($_GET['code'])) {
                // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Google OAuth callback parameter
                $this->client->authenticate(sanitize_text_field(wp_unslash($_GET['code'])));
                $this->set_access_token($this->client->getAccessToken());
                $this->cache_handler->save_access_token($this->get_access_token());

                $data = [
                    'status' => 'success',
                    'message' => __('App authorized! You can now upload videos to YouTube.', 'upload-field-to-youtube-for-acf'),
                ];

                $this->logger->info($data);

                return $data;
            }

            $auth_url = $this->client->createAuthUrl();

            return [
                'status' => 'authorize',
                'message' => __('Authorize the app to upload videos to YouTube:', 'upload-field-to-youtube-for-acf'),
                'auth_url' => $auth_url,
            ];
        }

        $googleServiceOauth2 = new Oauth2($this->get_google_client());
        $user_info = $googleServiceOauth2->userinfo->get();

        return [
            'status' => 'authorized',
            // translators: %s: user email address
            'message' => \sprintf(__('App authorized! You are logged in as: %1$s', 'upload-field-to-youtube-for-acf'), $user_info->email),
        ];
    }

    /**
     * Delete access token.
     */
    public function delete_access_token(): bool
    {
        $this->set_access_token(null);

        return $this->cache_handler->delete_access_token();
    }

    /**
     * Check if user is authorized.
     */
    public function is_authorized(): bool
    {
        $access_token = $this->cache_handler->get_access_token();

        return !empty($access_token) && isset($access_token['access_token']);
    }
}
