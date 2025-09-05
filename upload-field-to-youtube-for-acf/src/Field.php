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

namespace WpSpaghetti\UFTYFACF;

use DI\Container;
use WpSpaghetti\UFTYFACF\Service\CacheHandler;
use WpSpaghetti\UFTYFACF\Service\GoogleClientManager;
use WpSpaghetti\UFTYFACF\Service\YoutubeApiService;
use WpSpaghetti\UFTYFACF\Trait\HookTrait;
use WpSpaghetti\WpEnv\Environment;
use WpSpaghetti\WpLogger\Logger;
use WpSpaghetti\WpVite\Vite;

if (!\defined('ABSPATH')) {
    exit;
}

/**
 * Class Field - ACF field type class.
 */
class Field extends \acf_field
{
    /**
     * Use trait aliases to resolve method name conflicts with the parent acf_field class.
     *
     * The acf_field class already has public add_action() and add_filter() methods,
     * so we alias our trait methods to avoid conflicts while keeping both functionalities available:
     * - $this->add_action() calls the ACF parent method (for WordPress hooks)
     * - $this->hook_add_action() calls our trait method (for plugin-prefixed hooks)
     */
    use HookTrait {
        add_action as hook_add_action;
        add_filter as hook_add_filter;
    }

    /**
     * Field type title.
     */
    public string $title;

    /**
     * Environment values relating to the theme or plugin.
     *
     * @var array plugin or theme context such as 'url' and 'version'
     */
    private array $env;

    /**
     * Constructor with dependency injection.
     */
    public function __construct(
        private Container $container,
        private GoogleClientManager $google_client_manager,
        private YoutubeApiService $youtube_api_service,
        private CacheHandler $cache_handler,
        private Logger $logger
    ) {
        $this->init_hook($container);

        /*
         * Field type reference used in PHP and JS code.
         *
         * No spaces. Underscores allowed.
         */
        $this->name = $this->container->get('plugin_undername'); // Single words, no spaces, underscores allowed

        /*
         * Field type title.
         *
         * For admin-facing UI. May contain spaces.
         */
        $this->title = __('Upload Field to YouTube for ACF', 'upload-field-to-youtube-for-acf');

        /*
         * Field type label.
         *
         * For public-facing UI. May contain spaces.
         */
        $this->label = __('YouTube Uploader', 'upload-field-to-youtube-for-acf');

        /*
         * The category the field appears within in the field type picker.
         * Basic: basic | content | choice | relational | jquery | layout | CUSTOM GROUP NAME
         */
        $this->category = 'content';

        /*
         * Field type Description.
         *
         * For field descriptions. May contain spaces.
         */
        $this->description = __('Upload Field to YouTube for ACF is a WordPress plugin that allows you to upload videos directly to YouTube via API from the WordPress admin area and/or select existing videos on your YouTube channel based on playlists.', 'upload-field-to-youtube-for-acf');

        /*
         * Field type Doc URL.
         *
         * For linking to a documentation page. Displayed in the field picker modal.
         */
        $this->doc_url = 'https://github.com/wp-spaghetti/upload-field-to-youtube-for-acf';

        /*
         * Field type Tutorial URL.
         *
         * For linking to a tutorial resource. Displayed in the field picker modal.
         */
        $this->tutorial_url = 'https://github.com/wp-spaghetti/upload-field-to-youtube-for-acf';

        // Defaults for your custom user-facing settings for this field type.
        $this->defaults = $this->container->get('field_defaults');

        /*
         * Strings used in JavaScript code.
         *
         * Allows JS strings to be translated in PHP and loaded in JS via:
         *
         * ```js
         * const errorMessage = acf._e(this.field.data('type'), "error");
         * ```
         */
        $this->l10n = [
            'attention' => '⚠️ '.__('Attention', 'upload-field-to-youtube-for-acf'),
            'before_uploading' => __('Before uploading your video, make sure you:', 'upload-field-to-youtube-for-acf'),
            'confirm_video_id' => __('Confirm Video ID', 'upload-field-to-youtube-for-acf'),
            'do_not_save_before_entering_id' => __('Do NOT save the post until you have entered the Video ID, otherwise the video will not be associated with this post', 'upload-field-to-youtube-for-acf'),
            // translators: %s: Description
            'enter_description' => \sprintf(__('Enter a "%s"', 'upload-field-to-youtube-for-acf'), __('Description', 'upload-field-to-youtube-for-acf')),
            // translators: %s: Title
            'enter_title' => \sprintf(__('Enter a "%s"', 'upload-field-to-youtube-for-acf'), __('Title', 'upload-field-to-youtube-for-acf')),
            'enter_video_id_manually' => __('Enter Video ID manually', 'upload-field-to-youtube-for-acf'),
            'error_while_uploading' => __('Error while uploading', 'upload-field-to-youtube-for-acf'),
            'following_error' => __('The following error occurred:', 'upload-field-to-youtube-for-acf'),
            'loading' => __('Loading', 'upload-field-to-youtube-for-acf'),
            'manual_video_id_instructions' => __('You can find the Video ID in your YouTube Studio. Go to your channel, find the uploaded video, and copy the 11-character ID from the URL or video details', 'upload-field-to-youtube-for-acf'),
            'network_error' => __('Network error', 'upload-field-to-youtube-for-acf'),
            'network_error_while_uploading' => __('Network error while uploading', 'upload-field-to-youtube-for-acf'),
            'now_safe_to_save_post' => __('You can now safely save the post. The video is properly associated', 'upload-field-to-youtube-for-acf'),
            'parse_error' => __('Parse error', 'upload-field-to-youtube-for-acf'),
            'please_enter_valid_video_id' => __('Please enter a valid Video ID', 'upload-field-to-youtube-for-acf'),
            'preparing_upload' => __('Preparing to upload your file', 'upload-field-to-youtube-for-acf'),
            'recommended_save_post' => __('It is recommended to save the post by clicking the "Publish" button', 'upload-field-to-youtube-for-acf'),
            'select' => __('select', 'upload-field-to-youtube-for-acf'),
            // translators: %s: Video file
            'select_video_file' => \sprintf(__('Select a "%s"', 'upload-field-to-youtube-for-acf'), __('Video file', 'upload-field-to-youtube-for-acf')),
            'status_error' => __('Status error', 'upload-field-to-youtube-for-acf'),
            'technical_problem' => __('There was a technical problem, please try again later', 'upload-field-to-youtube-for-acf'),
            'upload_aborted' => __('Upload aborted', 'upload-field-to-youtube-for-acf'),
            'upload_completed_successfully' => __('Upload completed successfully', 'upload-field-to-youtube-for-acf'),
            'upload_failed' => __('Upload failed', 'upload-field-to-youtube-for-acf'),
            'upload_successful_id_needed' => __('Video uploaded - Manual ID entry required', 'upload-field-to-youtube-for-acf'),
            'verifying' => __('Verifying', 'upload-field-to-youtube-for-acf'),
            'video_associated_successfully' => __('Video successfully associated with this post', 'upload-field-to-youtube-for-acf'),
            'video_id' => __('Video ID', 'upload-field-to-youtube-for-acf'),
            // translators: %s: example video ID in bold tags
            'video_id_help_text_part1' => \sprintf(__('The Video ID is an 11-character string like: %s', 'upload-field-to-youtube-for-acf'), '<strong>dQw4w9WgXcQ</strong>'),
            'video_id_help_text_part2' => __('You can find it in YouTube Studio or in the video URL after "v=" or "youtu.be/"', 'upload-field-to-youtube-for-acf'),
            // translators: %s: example video ID
            'video_id_placeholder' => \sprintf(__('e.g., %s', 'upload-field-to-youtube-for-acf'), 'dQw4w9WgXcQ'),
            'video_id_set_successfully' => __('Video ID set successfully', 'upload-field-to-youtube-for-acf'),
            'video_uploaded_id_retrieval_failed' => __('Your video was uploaded successfully to YouTube! However, we could not automatically retrieve the Video ID due to a technical limitation', 'upload-field-to-youtube-for-acf'),
            'video_uploaded_successfully' => __('Video uploaded successfully', 'upload-field-to-youtube-for-acf'),
            'wait_please' => __('Wait please', 'upload-field-to-youtube-for-acf'),
        ];

        /*
         * Environment values relating to the theme or plugin.
         *
         * @var array plugin or theme context such as 'url' and 'version'
         */
        $this->env = $this->container->get('env_settings');

        /*
         * Field type preview image.
         *
         * A preview image for the field type in the picker modal.
         */
        // $this->preview_image = $this->env['url'] . '/asset/img/preview-custom.png';

        parent::__construct();

        add_action('admin_menu', [$this, 'admin_menu']);
        add_action('admin_init', [$this, 'admin_init']);
        add_action('admin_notices', [$this, 'admin_notices']);
        add_action('before_delete_post', [$this, 'before_delete_post']);
        add_action('wp_ajax_get_youtube_upload_url', [$this, 'wp_ajax_get_youtube_upload_url']);
        add_action('wp_ajax_upload_video_to_youtube', [$this, 'wp_ajax_upload_video_to_youtube']);
        add_action('wp_ajax_get_video_id_from_upload', [$this, 'wp_ajax_get_video_id_from_upload']);
        add_action('wp_ajax_save_youtube_video_id', [$this, 'wp_ajax_save_youtube_video_id']);
        add_action('wp_ajax_get_videos_by_playlist', [$this, 'wp_ajax_get_videos_by_playlist']);

        add_action($this->container->get('plugin_undername').'__check_oauth_token', [$this, 'check_oauth_token']);

        // Migrate old options format if needed
        $this->migrate_options();
    }

    /**
     * Add settings page to the admin menu.
     *
     * YouTube's ToS only allow custom icons; dashicons like `dashicons-video-alt3` are not allowed.
     * https://developers.google.com/youtube/terms/branding-guidelines
     * https://www.youtube.com/yt/about/brand-resources/#logos-icons-colors
     *
     * Callback for admin_menu.
     */
    public function admin_menu(): void
    {
        if (current_user_can('manage_options') || current_user_can('manage_'.$this->container->get('plugin_undername'))) {
            $capability = current_user_can('manage_options') ? 'manage_options' : 'manage_'.$this->container->get('plugin_undername');

            add_options_page(
                $this->label,               // Page title
                $this->label,               // Menu title
                $capability,                // Capability
                $this->container->get('plugin_undername'),                // Menu slug
                [$this, 'settings_page'],   // Callback function
            );
        }
    }

    /**
     * Admin init actions.
     * Handles logout and settings save.
     * Callback for admin_init.
     */
    public function admin_init(): void
    {
        $role = get_role('administrator');
        if ($role) {
            $role->add_cap('manage_'.$this->container->get('plugin_undername'));
        }

        if (isset($_POST['action']) && 'logout' === sanitize_text_field(wp_unslash($_POST['action']))) {
            $this->cache_handler->delete_access_token();

            add_action('admin_notices', static function (): void {
                echo '<div class="notice notice-success is-dismissible">';
                echo '<p><strong>'.esc_html__('Successfully logged out from YouTube.', 'upload-field-to-youtube-for-acf').'</strong></p>';
                echo '</div>';
            });
        }

        // Handle settings save
        if (isset($_POST['action']) && sanitize_text_field(wp_unslash($_POST['action'])) === $this->container->get('plugin_undername').'_save_settings') {
            if (!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_wpnonce'] ?? '')))) {
                wp_die(esc_html__('Security check failed', 'upload-field-to-youtube-for-acf'));
            }

            if (!current_user_can('manage_options') && !current_user_can('manage_'.$this->container->get('plugin_undername'))) {
                wp_die(esc_html__('Insufficient permissions', 'upload-field-to-youtube-for-acf'));
            }

            // Allow extensions to save their settings
            $this->do_action(__FUNCTION__.'_save_settings', $_POST);

            $this->logger->info(__('Plugin settings saved successfully', 'upload-field-to-youtube-for-acf'));

            add_action('admin_notices', static function (): void {
                echo '<div class="notice notice-success is-dismissible">';
                echo '<p><strong>'.esc_html__('Settings saved successfully.', 'upload-field-to-youtube-for-acf').'</strong></p>';
                echo '</div>';
            });
        }
    }

    /**
     * Admin notices to display in the admin area.
     * Callback for admin_notices.
     */
    public function admin_notices(): void
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Wordpress parameter
        if (isset($_GET['page']) && $this->container->get('plugin_undername') === sanitize_text_field(wp_unslash($_GET['page']))) {
            return;
        }

        $oauth = $this->google_client_manager->handle_oauth();
        $status = $oauth['status'] ?? 'error';

        switch ($status) {
            case 'authorize':
                echo '<div class="notice notice-warning is-dismissible">';
                echo '<h3>'.esc_html($this->label).'</h3>';
                echo '<p><strong>'.esc_html($oauth['message']).'</strong></p>';
                echo '<p><a href="'.esc_url($oauth['auth_url']).'" class="button button-primary">'.esc_html__('Authorize App', 'upload-field-to-youtube-for-acf').'</a></p>';
                echo '</div>';

                break;

            case 'success':
                echo '<div class="notice notice-success is-dismissible">';
                echo '<h3>'.esc_html($this->label).'</h3>';
                echo '<p><strong>'.esc_html($oauth['message']).'</strong></p>';
                echo '</div>';

                break;

            case 'error':
                echo '<div class="notice notice-error">';
                echo '<h3>'.esc_html($this->label).'</h3>';
                echo '<p><strong>'.esc_html($oauth['message']).'</strong></p>';
                echo '</div>';

                break;
        }
    }

    /**
     * Settings to display when users configure a field of this type.
     *
     * These settings appear on the ACF "Edit Field Group" admin page when
     * setting up the field.
     *
     * @param array $field
     *                     Callback for render_field_settings
     */
    public function render_field_settings($field): void
    {
        // Repeat for each setting you wish to display for this field type.
        acf_render_field_setting(
            $field,
            [
                'label' => __('Category ID', 'upload-field-to-youtube-for-acf'),
                'instructions' => implode(
                    '<br>'.PHP_EOL,
                    [
                        // translators: %s: category_id
                        \sprintf(__('Default: %1$s', 'upload-field-to-youtube-for-acf'), '<code>'.$this->defaults['category_id'].'</code>'),
                    ]
                ),
                'type' => 'number',
                'name' => 'category_id',
                'min' => 0,
                'step' => 1,
            ]
        );

        acf_render_field_setting(
            $field,
            [
                'label' => __('Tags', 'upload-field-to-youtube-for-acf'),
                'instructions' => implode(
                    '<br>'.PHP_EOL,
                    [
                        // translators: %s: tags
                        \sprintf(__('Default: %1$s', 'upload-field-to-youtube-for-acf'), '<code>'.$this->defaults['tags'].'</code>'),
                    ]
                ),
                'type' => 'text',
                'name' => 'tags',
            ]
        );

        acf_render_field_setting(
            $field,
            [
                'label' => __('Privacy status', 'upload-field-to-youtube-for-acf'),
                'instructions' => implode(
                    '<br>'.PHP_EOL,
                    [
                        // translators: %s: privacy_status
                        \sprintf(__('Default: %1$s', 'upload-field-to-youtube-for-acf'), '<code>'.$this->defaults['privacy_status'].'</code>'),
                    ]
                ),
                'type' => 'select',
                'name' => 'privacy_status',
                'choices' => [
                    'unlisted' => __('Unlisted', 'upload-field-to-youtube-for-acf'),
                    'private' => __('Private', 'upload-field-to-youtube-for-acf'),
                    'public' => __('Public', 'upload-field-to-youtube-for-acf'),
                ],
            ]
        );

        acf_render_field_setting(
            $field,
            [
                'label' => __('Made for kids', 'upload-field-to-youtube-for-acf'),
                'instructions' => implode(
                    '<br>'.PHP_EOL,
                    [
                        // translators: %s: made_for_kids
                        \sprintf(__('Default: %1$s', 'upload-field-to-youtube-for-acf'), '<code>'.($this->defaults['made_for_kids'] ? __('Yes', 'upload-field-to-youtube-for-acf') : __('No', 'upload-field-to-youtube-for-acf')).'</code>'),
                    ]
                ),
                'type' => 'true_false',
                'name' => 'made_for_kids',
            ]
        );

        acf_render_field_setting(
            $field,
            [
                'label' => __('Allow upload', 'upload-field-to-youtube-for-acf'),
                'instructions' => implode(
                    '<br>'.PHP_EOL,
                    [
                        // translators: %s: allow_upload
                        \sprintf(__('Default: %1$s', 'upload-field-to-youtube-for-acf'), '<code>'.($this->defaults['allow_upload'] ? __('Yes', 'upload-field-to-youtube-for-acf') : __('No', 'upload-field-to-youtube-for-acf')).'</code>'),
                    ]
                ),
                'type' => 'true_false',
                'name' => 'allow_upload',
            ]
        );

        acf_render_field_setting(
            $field,
            [
                'label' => __('Allow select', 'upload-field-to-youtube-for-acf'),
                'instructions' => implode(
                    '<br>'.PHP_EOL,
                    [
                        // translators: %s: allow_select
                        \sprintf(__('Default: %1$s', 'upload-field-to-youtube-for-acf'), '<code>'.($this->defaults['allow_select'] ? __('Yes', 'upload-field-to-youtube-for-acf') : __('No', 'upload-field-to-youtube-for-acf')).'</code>'),
                    ]
                ),
                'type' => 'true_false',
                'name' => 'allow_select',
            ]
        );

        acf_render_field_setting(
            $field,
            [
                'label' => __('Update YouTube video on post update', 'upload-field-to-youtube-for-acf'),
                'instructions' => implode(
                    '<br>'.PHP_EOL,
                    [
                        // translators: %s: api_update_on_post_update
                        \sprintf(__('Default: %1$s', 'upload-field-to-youtube-for-acf'), '<code>'.($this->defaults['api_update_on_post_update'] ? __('Yes', 'upload-field-to-youtube-for-acf') : __('No', 'upload-field-to-youtube-for-acf')).'</code>'),
                    ]
                ),
                'type' => 'true_false',
                'name' => 'api_update_on_post_update',
            ]
        );

        acf_render_field_setting(
            $field,
            [
                'label' => __('Delete YouTube video on post delete', 'upload-field-to-youtube-for-acf'),
                'instructions' => implode(
                    '<br>'.PHP_EOL,
                    [
                        // translators: %s: api_delete_on_post_delete
                        \sprintf(__('Default: %1$s', 'upload-field-to-youtube-for-acf'), '<code>'.($this->defaults['api_delete_on_post_delete'] ? __('Yes', 'upload-field-to-youtube-for-acf') : __('No', 'upload-field-to-youtube-for-acf')).'</code>'),
                    ]
                ),
                'type' => 'true_false',
                'name' => 'api_delete_on_post_delete',
            ]
        );

        // To render field settings on other tabs in ACF 6.0+:
        // https://www.advancedcustomfields.com/resources/adding-custom-settings-fields/#moving-field-setting
    }

    /**
     * HTML content to show when a publisher edits the field on the edit screen.
     *
     * @param array $field the field settings and values
     *                     Callback for render_field
     */
    public function render_field($field): void
    {
        $this->do_action(__FUNCTION__.'_before', $field);

        $field = $this->apply_filters(__FUNCTION__.'_field', $field);
        ?>
		<div class="<?php echo esc_attr($field['key']); ?>__wrapper <?php echo esc_attr($field['type']); ?>__wrapper">
            <input type="hidden" name="<?php echo esc_attr($field['name']); ?>" value="<?php echo esc_attr($field['value']); ?>" class="<?php echo esc_attr($field['key']); ?>__hidden_value_input">

            <?php if (!empty($field['value'])) { ?>
        	    <p>
                    <a href="https://www.youtube.com/embed/<?php echo esc_attr($field['value']); ?>?rel=0&TB_iframe=true" class="thickbox">
                        <img src="https://img.youtube.com/vi/<?php echo esc_attr($field['value']); ?>/hqdefault.jpg" alt="">
                    </a>
                </p>
			<?php } elseif ($this->cache_handler->get_access_token()) { ?>
                <input type="hidden" name="mode" class="<?php echo esc_attr($field['key']); ?>__hidden_mode_input">

                <div class="<?php echo esc_attr($field['key']); ?>__tabs">
                    <ul>
                        <?php if (!empty($field['allow_upload'])) { ?>
                            <li>
                                <a href="#<?php echo esc_attr($field['key']); ?>__tab_1">
                                    <?php esc_html_e('Upload via API', 'upload-field-to-youtube-for-acf'); ?>
                                </a>
                            </li>
                        <?php }
                        ?>
                        <?php if (!empty($field['allow_select'])) { ?>
                            <li>
                                <a href="#<?php echo esc_attr($field['key']); ?>__tab_2">
                                    <?php esc_html_e('Select from channel', 'upload-field-to-youtube-for-acf'); ?>
                                </a>
                            </li>
                         <?php }
                        ?>
                    </ul>

                    <?php if (!empty($field['allow_upload'])) { ?>
                        <div id="<?php echo esc_attr($field['key']); ?>__tab_1">
                            <input type="file" class="<?php echo esc_attr($field['key']); ?>__file_input" name="<?php echo esc_attr($field['key']); ?>__file_input" lang="<?php echo esc_attr(get_locale()); ?>" accept="video/*">
                            <button type="button" class="<?php echo esc_attr($field['key']); ?>__button button button-secondary">
			                	<?php esc_html_e('Upload', 'upload-field-to-youtube-for-acf'); ?>
			                </button>
                        </div>
                    <?php }
                    ?>

                    <?php if (!empty($field['allow_select'])) { ?>
                        <div id="<?php echo esc_attr($field['key']); ?>__tab_2">
                            <?php
                                $result = $this->youtube_api_service->get_playlists_by_privacy_status($field['privacy_status']);
                        if (!empty($result['items'])) { ?>
                                <p>
                                    <label for="<?php echo esc_attr($field['key']); ?>__playlist_select">
                                        <?php esc_html_e('Playlist', 'upload-field-to-youtube-for-acf'); ?>
                                    </label>
                                    <select class="<?php echo esc_attr($field['key']); ?>__playlist_select" name="<?php echo esc_attr($field['key']); ?>__playlist_select">
                                        <option value="">- <?php esc_html_e('select', 'upload-field-to-youtube-for-acf'); ?> -</option>
                                        <?php foreach ($result['items'] as $item) { ?>
                                            <option value="<?php echo esc_attr($item['id']); ?>">
                                                <?php echo esc_html($item['title']); ?> (<?php echo esc_html($item['id']); ?>)
                                            </option>
                                        <?php }
                                        ?>
                                    </select>
                                </p>

                                <p>
                                    <label for="<?php echo esc_attr($field['key']); ?>__video_select">
                                        <?php esc_html_e('Video', 'upload-field-to-youtube-for-acf'); ?>
                                    </label>
                                    <select class="<?php echo esc_attr($field['key']); ?>__video_select" name="<?php echo esc_attr($field['key']); ?>__video_select"></select>
                                </p>
                            <?php } else { ?>
                                <p><?php esc_html_e('No playlists available', 'upload-field-to-youtube-for-acf'); ?></p>
                            <?php }
                            ?>
                        </div>
<?php }
                    ?>
                </div>

                <p class="<?php echo esc_attr($field['key']); ?>__response <?php echo esc_attr($field['type']); ?>__response"></p>
			    <p class="<?php echo esc_attr($field['key']); ?>__spinner <?php echo esc_attr($field['type']); ?>__spinner">
			        <span class="spinner is-active"></span>
			    </p>
<?php } else { ?>
                <p><?php esc_html_e('You are not logged in', 'upload-field-to-youtube-for-acf'); ?></p>
            <?php }
?>
		</div>
<?php
        $this->do_action(__FUNCTION__.'_after', $field);
    }

    /**
     * Enqueues CSS and JavaScript needed by HTML in the render_field() method.
     *
     * Callback for admin_enqueue_script.
     */
    public function input_admin_enqueue_scripts(): void
    {
        global $post;

        Vite::init(
            $this->env['path'],
            $this->env['url'],
            $this->env['version']
        );

        // Add Vite dev scripts for HMR in development
        if ($this->env['debug']) {
            Vite::devScripts();
        }

        // https://wordpress.stackexchange.com/a/273996/99214
        // https://stackoverflow.com/a/59665364/3929620
        // No need to enqueue -core, because dependancies are set.
        wp_enqueue_script('jquery-ui-tabs');

        // WordPress does not register jQuery UI styles by default!
        // If you're going to submit your plugin to the wordpress.org repo, then you need to load the CSS locally
        // (see: https://developer.wordpress.org/plugins/wordpress-org/detailed-plugin-guidelines/#8-plugins-may-not-send-executable-code-via-third-party-systems).
        // https://code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css
        Vite::enqueueStyle(
            'jquery-ui-css',
            'css/jquery-ui',
            ['acf-input']
        );

        add_thickbox();

        Vite::enqueueScript(
            $this->container->get('plugin_name'),
            'js/main',
            ['acf-input'],
            true
        );

        // $object_name is the name of the variable which will contain the data.
        // Note that this should be unique to both the script and to the plugin or theme.
        // Thus, the value here should be properly prefixed with the slug or another unique value,
        // to prevent conflicts. However, as this is a JavaScript object name, it cannot contain dashes.
        // Use underscores or camelCasing.
        wp_localize_script($this->container->get('plugin_name'), $this->container->get('plugin_undername').'_obj', [
            '_wpnonce' => wp_create_nonce(),
            'postStatus' => $post ? $post->post_status : null,
            'serverUpload' => $this->env['server_upload'],
            'debug' => $this->env['debug'],
        ]);

        Vite::enqueueStyle(
            $this->container->get('plugin_name'),
            'css/main',
            ['acf-input']
        );
    }

    /**
     * Validate the field value before saving.
     *
     * This method validates the YouTube video ID and checks if it exists
     * in the user's authorized YouTube account.
     *
     * @param mixed $valid
     * @param mixed $field
     * @param mixed $input
     *
     * @return mixed (bool|string) an error message or true if the value is valid
     *
     * @throws \Exception if an error occurs during validation
     */
    public function validate_value($valid, mixed $value, $field, $input)
    {
        try {
            if (empty($value) && !empty($field['required'])) {
                // translators: %s: field label
                throw new \InvalidArgumentException(\sprintf(__('Empty field "%1$s"', 'upload-field-to-youtube-for-acf'), $field['label']));
            }

            if (!empty($value)) {
                $this->do_action(__FUNCTION__.'_before', $valid, $value, $field, $input);

                // Safely get post ID with sanitization
                $post_id = 0;
                // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- ACF handles security for field validation
                if (isset($_POST['post_ID'])) {
                    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- ACF handles security for field validation
                    $post_id = (int) sanitize_text_field(wp_unslash($_POST['post_ID']));
                // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- ACF handles security for field validation
                } elseif (isset($_POST['post_id'])) {
                    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- ACF handles security for field validation
                    $post_id = (int) sanitize_text_field(wp_unslash($_POST['post_id']));
                }

                // Check if this should update based on field setting (for normal post updates)
                $field_setting_update = !empty($field['api_update_on_post_update']);

                // Check if mode is explicitly set to upload in POST data (for form submissions)
                // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- ACF handles security for field validation
                $is_form_upload = isset($_POST['mode']) && 'upload' === sanitize_text_field(wp_unslash($_POST['mode']));

                // Determine if we should update: always for form uploads,
                // or when field setting allows it for normal post updates
                $should_update = $is_form_upload || $field_setting_update;

                $api_update_on_post_update = $this->apply_filters(
                    __FUNCTION__.'_should_update',
                    $should_update,
                    $value,
                    $field,
                    $input,
                    $post_id
                );

                if ($api_update_on_post_update) {
                    $is_gutenberg = \function_exists('use_block_editor_for_post') && use_block_editor_for_post($post_id);
                    if ($is_gutenberg) {
                        $post = get_post($post_id);
                        $title = sanitize_text_field($post->post_title ?? '');
                        $excerpt = sanitize_text_field($post->post_excerpt ?? '');
                    } else {
                        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- ACF handles security for field validation
                        $title = isset($_POST['post_title']) ? sanitize_text_field(wp_unslash($_POST['post_title'])) : '';
                        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- ACF handles security for field validation
                        $excerpt = isset($_POST['excerpt']) ? sanitize_text_field(wp_unslash($_POST['excerpt'])) : '';
                    }

                    if (empty($title)) {
                        // translators: %s: field name (Title)
                        throw new \LengthException(\sprintf(__('Empty field "%1$s"', 'upload-field-to-youtube-for-acf'), __('Title', 'upload-field-to-youtube-for-acf')));
                    }

                    /*if (empty($excerpt)) {
                        // translators: %s: field name (Excerpt)
                        throw new \LengthException(\sprintf(__('Empty field "%1$s"', 'upload-field-to-youtube-for-acf'), __('Excerpt', 'upload-field-to-youtube-for-acf')));
                    }*/
                }

                if (!$this->youtube_api_service->validate_video_exists($value)) {
                    throw new \Exception(__('This video is not associated with your authorized YouTube account', 'upload-field-to-youtube-for-acf'));
                }

                $this->do_action(__FUNCTION__.'_after', $valid, $value, $field, $input);
            }
        } catch (\InvalidArgumentException|\LengthException $exception) {
            // FIXED - https://github.com/inpsyde/Wonolog/blob/2.x/src/HookLogFactory.php#L135
            // use `$exception->getMessage()` instead of `$exception`, because Wonolog
            // assigns the ERROR level to messages that are instances of Throwable
            $this->logger->warning($exception->getMessage(), [
                'video_id' => $value,
                'field' => $field,
                'input' => $input,
            ]);
            $valid = $exception->getMessage();
            $this->do_action(__FUNCTION__.'_error', $exception, $valid, $value, $field, $input);
        } catch (\Google\Service\Exception $exception) {
            $this->logger->error($exception, [
                'video_id' => $value,
                'field' => $field,
                'input' => $input,
            ]);
            $error_data = json_decode($exception->getMessage(), true);
            $valid = $error_data['error']['message'] ?? $exception->getMessage();
            $this->do_action(__FUNCTION__.'_error', $exception, $valid, $value, $field, $input);
        } catch (\Exception $exception) {
            $this->logger->error($exception, [
                'video_id' => $value,
                'field' => $field,
                'input' => $input,
            ]);
            $valid = $exception->getMessage();
            $this->do_action(__FUNCTION__.'_error', $exception, $valid, $value, $field, $input);
        }

        return $valid;
    }

    /**
     * Update the field value and optionally update YouTube video metadata.
     *
     * This method is called when the field value is saved and can optionally
     * update the YouTube video's title and description based on post content.
     *
     * @param mixed $field
     *
     * @return mixed the original value, unchanged
     *
     * @throws \Exception if an error occurs during video metadata update
     */
    public function update_value(mixed $value, mixed $post_id, $field)
    {
        try {
            // Check specifically if this is one of our plugin's AJAX upload actions
            $is_our_ajax_upload = false;
            // phpcs:ignore WordPress.Security.NonceVerification.Missing -- ACF handles security for field updates
            if (isset($_POST['action'])) {
                // phpcs:ignore WordPress.Security.NonceVerification.Missing -- ACF handles security for field updates
                $action = sanitize_text_field(wp_unslash($_POST['action']));
                $is_our_ajax_upload = \in_array($action, [
                    'save_youtube_video_id',
                    'upload_video_to_youtube',
                ], true);
            }

            // Check if this should update based on field setting (for normal post updates)
            $field_setting_update = !empty($field['api_update_on_post_update']);

            // Check if mode is explicitly set to upload in POST data (for form submissions)
            // phpcs:ignore WordPress.Security.NonceVerification.Missing -- ACF handles security for field updates
            $is_form_upload = isset($_POST['mode']) && 'upload' === sanitize_text_field(wp_unslash($_POST['mode']));

            // Determine if we should update: always for our AJAX upload actions or form uploads,
            // or when field setting allows it for normal post updates
            $should_update = $is_our_ajax_upload || $is_form_upload || $field_setting_update;

            $api_update_on_post_update = $this->apply_filters(
                __FUNCTION__.'_should_update',
                $should_update,
                $value,
                $post_id,
                $field
            );

            if (!$api_update_on_post_update) {
                return $value;
            }

            if (!empty($value)) {
                $this->do_action(__FUNCTION__.'_before', $value, $post_id, $field);

                $data = $this->apply_filters(__FUNCTION__.'_data', [
                    'category_id' => $field['category_id'],
                    'title' => get_the_title($post_id),
                    'description' => get_post_field('post_excerpt', $post_id),
                ], $value, $post_id, $field);

                $response = $this->youtube_api_service->update_video_metadata($value, $data);

                $this->do_action(__FUNCTION__.'_after', $value, $post_id, $field, $response);
            }
        } catch (\Exception $exception) {
            $this->logger->error($exception, [
                'video_id' => $value,
                'post_id' => $post_id,
                'field' => $field,
                'response' => $response ?? null,
            ]);
            $this->do_action(__FUNCTION__.'_error', $exception, $value, $post_id ?? null, $field ?? null, $response ?? null);
        }

        return $value;
    }

    /**
     * Handle post deletion and optionally delete associated YouTube videos.
     *
     * This method is triggered before a post is deleted and can optionally
     * delete the associated YouTube videos if configured to do so.
     *
     * @param int $post_id the ID of the post being deleted
     *
     * @throws \Exception if an error occurs during video deletion
     */
    public function before_delete_post(int $post_id): void
    {
        $fields = get_field_objects($post_id);

        if ($fields) {
            foreach ($fields as $field) {
                if (!empty($field['type']) && !empty($field['value']) && $field['type'] === $this->container->get('plugin_undername')) {
                    try {
                        $this->do_action(__FUNCTION__.'_before', $post_id, $field);

                        $should_delete = $this->apply_filters(__FUNCTION__.'_should_delete', $field['api_delete_on_post_delete'] ?? false, $post_id, $field);

                        if (!$should_delete) {
                            continue;
                        }

                        $this->youtube_api_service->delete_video($field['value']);

                        $this->do_action(__FUNCTION__.'_after', $post_id, $field);
                    } catch (\Exception $exception) {
                        $this->logger->error($exception, [
                            'post_id' => $post_id,
                            'video_id' => $field['value'],
                        ]);
                        $this->do_action(__FUNCTION__.'_error', $exception, $post_id, $field);
                    }
                }
            }
        }
    }

    /**
     * Display the plugin settings page in WordPress admin.
     *
     * This method renders the settings page where users can authorize
     * the application with YouTube and configure plugin settings.
     *
     * @throws \Exception if an error occurs during OAuth handling
     */
    public function settings_page(): void
    {
        $oauth = $this->google_client_manager->handle_oauth();
        $status = $oauth['status'] ?? 'error';

        echo '<div class="wrap">';
        echo '<h1>'.esc_html($this->label).'</h1>';

        $this->do_action(__FUNCTION__.'_before', $oauth, $this);

        switch ($status) {
            case 'authorize':
                $this->do_action(__FUNCTION__.'_'.$status.'_before', $oauth, $this);

                echo '<div class="notice notice-warning is-dismissible">';
                echo '<h3>'.esc_html($this->label).'</h3>';
                echo '<p><strong>'.esc_html($oauth['message']).'</strong></p>';
                echo '<p><a href="'.esc_url($oauth['auth_url']).'" class="button button-primary">'.esc_html__('Authorize App', 'upload-field-to-youtube-for-acf').'</a></p>';
                echo '</div>';

                $this->do_action(__FUNCTION__.'_'.$status.'_after', $oauth, $this);

                break;

            case 'authorized':
                $this->do_action(__FUNCTION__.'_'.$status.'_before', $oauth, $this);

                echo '<div class="notice notice-success">';
                echo '<p><strong>'.esc_html($oauth['message']).'</strong></p>';
                echo '</div>';

                echo '<form method="post" action="">';
                echo '<input type="hidden" name="action" value="logout">';
                submit_button(__('Logout from YouTube', 'upload-field-to-youtube-for-acf'));
                echo '</form>';

                // Check if there are any extensions that want to add settings
                ob_start();
                $this->do_action(__FUNCTION__.'_'.$status.'_after', $oauth, $this);
                $output = ob_get_clean();

                if (!empty(trim($output))) {
                    echo '<hr>';
                    echo '<form method="post" action="">';
                    wp_nonce_field();
                    echo '<input type="hidden" name="action" value="'.esc_attr($this->container->get('plugin_undername').'_save_settings').'">';

                    echo wp_kses_post($output);

                    submit_button(__('Save Settings', 'upload-field-to-youtube-for-acf'));
                    echo '</form>';
                }

                break;

            case 'error':
                $this->do_action(__FUNCTION__.'_'.$status.'_before', $oauth, $this);

                echo '<div class="notice notice-error">';
                echo '<p><strong>'.esc_html($oauth['message']).'</strong></p>';
                echo '</div>';

                $this->do_action(__FUNCTION__.'_'.$status.'_after', $oauth, $this);

                break;
        }

        $this->do_action(__FUNCTION__.'_after', $oauth, $this);

        echo '</div>';
    }

    /**
     * Activate the plugin and schedule necessary cron jobs.
     *
     * This static method is called when the plugin is activated and sets up
     * scheduled tasks for token maintenance and other periodic operations.
     *
     * @throws \Exception if an error occurs during activation
     */
    public static function activate(): void
    {
        $container = wpspaghetti_uftyfacf_get_container();
        $instance = $container->get(self::class);
        $hook = $instance->get_cron_hook();
        $schedule = $instance->get_cron_schedule();

        // Action before activation
        $instance->do_action(__FUNCTION__.'_before', $instance, $hook, $schedule);

        if (!wp_next_scheduled($hook)) {
            $result = wp_schedule_event(time(), $schedule, $hook);

            if (false === $result) {
                $container->get(Logger::class)->error('Failed to schedule cron event', [
                    'hook' => $hook,
                    'schedule' => $schedule,
                    'default_schedule' => $instance->env['cron_schedule'],
                ]);
            } else {
                $container->get(Logger::class)->info('Cron event scheduled successfully', [
                    'hook' => $hook,
                    'schedule' => $schedule,
                    'next_run' => wp_next_scheduled($hook),
                    'default_schedule' => $instance->env['cron_schedule'],
                ]);
            }
        }

        // Action after activation
        $instance->do_action(__FUNCTION__.'_after', $instance, $hook, $schedule, $result ?? null);
    }

    /**
     * Deactivate the plugin and clean up resources.
     *
     * This static method is called when the plugin is deactivated and handles
     * cleanup of tokens, scheduled events, and temporary data.
     *
     * @param bool $network_deactivating whether this is a network deactivation
     *                                   for multisite installations
     *
     * @throws \Exception if an error occurs during deactivation
     */
    public static function deactivate($network_deactivating = false): void
    {
        $container = wpspaghetti_uftyfacf_get_container();
        $instance = $container->get(self::class);
        $hook = $instance->get_cron_hook();

        // Action before deactivation
        $instance->do_action(__FUNCTION__.'_before', $network_deactivating, $instance, $hook);

        // Delete access token
        $container->get(CacheHandler::class)->delete_access_token();

        // Unschedule cron
        $timestamp = wp_next_scheduled($hook);
        if ($timestamp) {
            $result = wp_unschedule_event($timestamp, $hook);

            if (false === $result) {
                $container->get(Logger::class)->warning('Failed to unschedule cron event', [
                    'hook' => $hook,
                    'timestamp' => $timestamp,
                ]);
            } else {
                $container->get(Logger::class)->info('Cron event unscheduled successfully', [
                    'hook' => $hook,
                ]);
            }
        }

        // Action after deactivation
        $instance->do_action(__FUNCTION__.'_after', $network_deactivating, $instance, $hook, $timestamp);
    }

    /**
     * Get YouTube upload URL for resumable upload.
     * This method is called via AJAX to obtain a unique upload URL
     * for uploading a video file directly to YouTube.
     *
     * @throws \Exception if an error occurs during the process
     */
    public function wp_ajax_get_youtube_upload_url(): void
    {
        try {
            // Verify nonce for security
            if (!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_wpnonce'] ?? '')))) {
                throw new \InvalidArgumentException(__('Security check failed', 'upload-field-to-youtube-for-acf'));
            }

            $post_id = isset($_POST['post_id']) ? (int) $_POST['post_id'] : 0;
            if (empty($post_id)) {
                // translators: %s: field name (post_id)
                throw new \InvalidArgumentException(\sprintf(__('Empty field "%1$s"', 'upload-field-to-youtube-for-acf'), 'post_id'));
            }

            $field_key = isset($_POST['field_key']) ? sanitize_text_field(wp_unslash($_POST['field_key'])) : '';
            if (empty($field_key)) {
                // translators: %s: field name (field_key)
                throw new \InvalidArgumentException(\sprintf(__('Empty field "%1$s"', 'upload-field-to-youtube-for-acf'), 'field_key'));
            }

            // https://support.advancedcustomfields.com/forums/topic/get-choices-from-field-without-post_id/
            $field = $this->apply_filters(__FUNCTION__.'_field', get_field_object($field_key));
            if (!$field) {
                // translators: %s: field key value
                throw new \InvalidArgumentException(\sprintf(__('Unable to retrieve field "%1$s"', 'upload-field-to-youtube-for-acf'), $field_key));
            }

            $this->do_action(__FUNCTION__.'_before', $post_id, $field_key, $field);

            $upload_url = $this->youtube_api_service->get_youtube_upload_url($post_id, $field);

            $this->do_action(__FUNCTION__.'_after', $upload_url, $post_id, $field);
            wp_send_json_success(['upload_url' => $upload_url]);
        } catch (\Exception $exception) {
            $this->logger->error($exception, [
                'post_id' => $post_id ?? null,
                'field_key' => $field_key ?? null,
            ]);
            $this->do_action(__FUNCTION__.'_error', $exception, $post_id ?? null, $field ?? null);
            wp_send_json_error(['message' => $exception->getMessage()]);
        }
    }

    /**
     * Upload video file directly to YouTube via server-side processing.
     *
     * This method handles the complete upload process on the server side,
     * including file validation, metadata preparation, and chunked upload to YouTube.
     *
     * @throws \Exception if an error occurs during the upload process
     */
    public function wp_ajax_upload_video_to_youtube(): void
    {
        try {
            // Verify nonce for security
            if (!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_wpnonce'] ?? '')))) {
                throw new \InvalidArgumentException(__('Security check failed', 'upload-field-to-youtube-for-acf'));
            }

            $post_id = isset($_POST['post_id']) ? (int) $_POST['post_id'] : 0;
            if (empty($post_id)) {
                // translators: %s: field name (post_id)
                throw new \InvalidArgumentException(\sprintf(__('Empty field "%1$s"', 'upload-field-to-youtube-for-acf'), 'post_id'));
            }

            $field_key = isset($_POST['field_key']) ? sanitize_text_field(wp_unslash($_POST['field_key'])) : '';
            if (empty($field_key)) {
                // translators: %s: field name (field_key)
                throw new \InvalidArgumentException(\sprintf(__('Empty field "%1$s"', 'upload-field-to-youtube-for-acf'), 'field_key'));
            }

            // Validate uploaded file using WordPress functions
            if (!isset($_FILES['video_file'])) {
                throw new \InvalidArgumentException(__('No video file uploaded', 'upload-field-to-youtube-for-acf'));
            }

            /*
             * Use wp_handle_upload() instead of direct $_FILES access for WordPress compliance.
             *
             * This approach introduces a small overhead as the file is moved from PHP's temporary
             * directory (/tmp) to WordPress uploads directory (/wp-content/uploads/) and then read
             * from there, instead of reading directly from /tmp. However, this provides significant
             * security benefits:
             *
             * - Automatic file type validation (MIME type vs extension matching)
             * - File size and upload error checking
             * - Protection against malicious uploads
             * - WordPress Coding Standards compliance (required for wordpress.org)
             *
             * The performance impact is minimal (one additional file operation) compared to the
             * security and compliance benefits gained.
             */
            if (!\function_exists('wp_handle_upload')) {
                require_once ABSPATH.'wp-admin/includes/file.php';
            }

            // Custom upload handling for temporary video processing
            $upload_overrides = [
                'test_form' => false, // Skip form validation since this is AJAX
                'test_type' => true,  // Validate file type
                'mimes' => $this->container->get('allowed_video_mime_types'),
            ];

            // Handle upload with WordPress security validation
            $uploaded_file = wp_handle_upload($_FILES['video_file'], $upload_overrides);

            if (isset($uploaded_file['error'])) {
                throw new \InvalidArgumentException($uploaded_file['error']);
            }

            if (!isset($uploaded_file['file']) || !file_exists($uploaded_file['file'])) {
                throw new \InvalidArgumentException(__('Upload error occurred', 'upload-field-to-youtube-for-acf'));
            }

            $field = get_field_object($field_key);
            if (!$field) {
                // Clean up uploaded file on error
                wp_delete_file($uploaded_file['file']);

                // translators: %s: field key value
                throw new \InvalidArgumentException(\sprintf(__('Unable to retrieve field "%1$s"', 'upload-field-to-youtube-for-acf'), $field_key));
            }

            $this->do_action(__FUNCTION__.'_before', $post_id, $field, $uploaded_file);

            // Prepare file data for YouTube upload
            $video_file_data = [
                'tmp_name' => $uploaded_file['file'],
                'size' => filesize($uploaded_file['file']),
                'type' => $uploaded_file['type'],
                'name' => basename($uploaded_file['file']),
            ];

            // Upload to YouTube
            $video_id = $this->youtube_api_service->upload_video_to_youtube($post_id, $field, $video_file_data);

            // Update the field value
            $field_saved = $this->save_video_id_to_field($video_id, $post_id, $field_key, false);

            // Clean up temporary file after successful upload
            wp_delete_file($uploaded_file['file']);

            $this->do_action(__FUNCTION__.'_after', $video_id, $post_id, $field);

            wp_send_json_success([
                'video_id' => $video_id,
                'message' => 'Video uploaded successfully',
                'field_saved' => $field_saved, // Add flag to indicate field was already saved
            ]);
        } catch (\Google\Service\Exception $exception) {
            // Clean up uploaded file on error
            wp_delete_file($uploaded_file['file']);

            $this->logger->error($exception, [
                'post_id' => $post_id,
            ]);
            $error_data = json_decode($exception->getMessage(), true);
            $error_message = $error_data['error']['message'] ?? $exception->getMessage();
            $this->do_action(__FUNCTION__.'_error', $exception, $post_id, $field_key);
            wp_send_json_error(['message' => $error_message]);
        } catch (\Exception $exception) {
            // Clean up uploaded file on error
            if (isset($uploaded_file['file']) && file_exists($uploaded_file['file'])) {
                wp_delete_file($uploaded_file['file']);
            }

            $this->logger->error($exception, [
                'post_id' => $post_id ?? null,
            ]);
            $this->do_action(__FUNCTION__.'_error', $exception, $post_id ?? null, $field_key ?? null);
            wp_send_json_error(['message' => $exception->getMessage()]);
        }
    }

    /**
     * Retrieve video ID from a recent upload by checking recently uploaded videos.
     *
     * This method searches for recently uploaded videos that might match
     * the current upload session when the upload response is not readable.
     *
     * @throws \Exception if an error occurs during the retrieval process
     */
    public function wp_ajax_get_video_id_from_upload(): void
    {
        try {
            // Verify nonce for security
            if (!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_wpnonce'] ?? '')))) {
                throw new \InvalidArgumentException(__('Security check failed', 'upload-field-to-youtube-for-acf'));
            }

            $upload_id = isset($_POST['upload_id']) ? sanitize_text_field(wp_unslash($_POST['upload_id'])) : '';
            $post_id = isset($_POST['post_id']) ? (int) $_POST['post_id'] : 0;
            $field_key = isset($_POST['field_key']) ? sanitize_text_field(wp_unslash($_POST['field_key'])) : '';

            if (empty($upload_id) || empty($post_id) || empty($field_key)) {
                throw new \InvalidArgumentException(__('Missing required parameters', 'upload-field-to-youtube-for-acf'));
            }

            $this->do_action(__FUNCTION__.'_before', $upload_id, $post_id, $field_key);

            $video_id = $this->youtube_api_service->get_video_id_from_upload($upload_id);

            if (!$video_id) {
                throw new \UnexpectedValueException(__('Could not find recently uploaded video', 'upload-field-to-youtube-for-acf'));
            }

            update_field($field_key, $video_id, $post_id);

            $this->logger->debug('Saved recent video ID', [
                'video_id' => $video_id,
                'upload_id' => $upload_id,
                'post_id' => $post_id,
                'field_key' => $field_key,
            ]);

            $this->do_action(__FUNCTION__.'_after', $video_id, $upload_id, $post_id, $field_key);

            wp_send_json_success([
                'video_id' => $video_id,
                'message' => __('Video ID found and saved', 'upload-field-to-youtube-for-acf'),
            ]);
        } catch (\Exception $exception) {
            $this->logger->error($exception, [
                'video_id' => $video_id ?? null,
                'upload_id' => $upload_id ?? null,
                'post_id' => $post_id ?? null,
                'field_key' => $field_key ?? null,
            ]);
            $this->do_action(__FUNCTION__.'_error', $exception, $video_id ?? null, $upload_id ?? null, $post_id ?? null, $field_key ?? null);
            wp_send_json_error(['message' => $exception->getMessage()]);
        }
    }

    /**
     * Save YouTube video ID to the specified ACF field.
     *
     * This method updates the ACF field with the provided video ID
     * after validating user permissions and field existence.
     *
     * @throws \Exception if an error occurs during the save process
     */
    public function wp_ajax_save_youtube_video_id(): void
    {
        try {
            // Verify nonce for security
            if (!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_wpnonce'] ?? '')))) {
                throw new \InvalidArgumentException(__('Security check failed', 'upload-field-to-youtube-for-acf'));
            }

            $post_id = isset($_POST['post_id']) ? (int) $_POST['post_id'] : 0;
            $video_id = isset($_POST['video_id']) ? sanitize_text_field(wp_unslash($_POST['video_id'])) : '';
            $field_key = isset($_POST['field_key']) ? sanitize_text_field(wp_unslash($_POST['field_key'])) : '';

            $this->do_action(__FUNCTION__.'_before', $post_id, $video_id, $field_key);

            $this->save_video_id_to_field($video_id, $post_id, $field_key);

            $this->do_action(__FUNCTION__.'_after', $post_id, $video_id, $field_key);
            wp_send_json_success();
        } catch (\Exception $exception) {
            $this->logger->error($exception, [
                'post_id' => $post_id ?? null,
                'video_id' => $video_id ?? null,
                'field_key' => $field_key ?? null,
            ]);
            $this->do_action(__FUNCTION__.'_error', $exception, $post_id ?? null, $video_id ?? null, $field_key ?? null);
            wp_send_json_error(['message' => $exception->getMessage()]);
        }
    }

    /**
     * Retrieve videos from a specific YouTube playlist.
     *
     * This method fetches videos from the specified playlist that match
     * the field's privacy status setting.
     *
     * @throws \Exception if an error occurs during the retrieval process
     */
    public function wp_ajax_get_videos_by_playlist(): void
    {
        try {
            // Verify nonce for security
            if (!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_wpnonce'] ?? '')))) {
                throw new \InvalidArgumentException(__('Security check failed', 'upload-field-to-youtube-for-acf'));
            }

            $field_key = isset($_POST['field_key']) ? sanitize_text_field(wp_unslash($_POST['field_key'])) : '';
            if (empty($field_key)) {
                // translators: %s: field name (field_key)
                throw new \InvalidArgumentException(\sprintf(__('Empty field "%1$s"', 'upload-field-to-youtube-for-acf'), 'field_key'));
            }

            // https://support.advancedcustomfields.com/forums/topic/get-choices-from-field-without-post_id/
            $field = get_field_object($field_key);
            if (!$field) {
                // translators: %s: field key value
                throw new \InvalidArgumentException(\sprintf(__('Unable to retrieve field "%1$s"', 'upload-field-to-youtube-for-acf'), $field_key));
            }

            $playlist_id = isset($_POST['playlist_id']) ? sanitize_text_field(wp_unslash($_POST['playlist_id'])) : '';
            if (empty($playlist_id)) {
                // translators: %s: field name (playlist_id)
                throw new \InvalidArgumentException(\sprintf(__('Empty field "%1$s"', 'upload-field-to-youtube-for-acf'), 'playlist_id'));
            }

            $this->do_action(__FUNCTION__.'_before', $field_key, $field, $playlist_id);

            $result = $this->youtube_api_service->get_videos_by_playlist($playlist_id, $field['privacy_status']);

            if (!$result) {
                // translators: %s: playlist ID value
                throw new \UnexpectedValueException(\sprintf(__('Unable to retrieve videos by playlist ID "%1$s"', 'upload-field-to-youtube-for-acf'), $playlist_id));
            }

            $this->do_action(__FUNCTION__.'_after', $field_key, $field, $playlist_id, $result);
            wp_send_json_success($result);
        } catch (\UnexpectedValueException $exception) {
            // FIXED - https://github.com/inpsyde/Wonolog/blob/2.x/src/HookLogFactory.php#L135
            // use `$exception->getMessage()` instead of `$exception`, because Wonolog
            // assigns the ERROR level to messages that are instances of Throwable
            $this->logger->warning($exception->getMessage(), [
                'field_key' => $field_key,
                'field' => $field,
                'playlist_id' => $playlist_id,
            ]);
            $this->do_action(__FUNCTION__.'_error', $exception, $field_key, $field, $playlist_id);
            wp_send_json_error(['message' => $exception->getMessage()]);
        } catch (\Exception $exception) {
            $this->logger->error($exception, [
                'field_key' => $field_key ?? null,
                'field' => $field ?? null,
                'playlist_id' => $playlist_id ?? null,
                'result' => $result ?? null,
            ]);
            $this->do_action(__FUNCTION__.'_error', $exception, $field_key ?? null, $field ?? null, $playlist_id ?? null, $result ?? null);
            wp_send_json_error(['message' => $exception->getMessage()]);
        }
    }

    /**
     * Get cron status information for debugging.
     *
     * @return array associative array with cron status details
     */
    public function get_cron_status(): array
    {
        $hook = $this->get_cron_hook();
        $schedule = $this->get_cron_schedule();
        $next_run = wp_next_scheduled($hook);

        $status = [
            'hook' => $hook,
            'schedule' => $schedule,
            'default_schedule' => $this->env['cron_schedule'],
            'is_scheduled' => (bool) $next_run,
            'next_run' => $next_run,
            'next_run_formatted' => $next_run ? wp_date('Y-m-d H:i:s', $next_run) : null,
            'available_schedules' => array_keys(wp_get_schedules()),
            'env_debug' => $this->env['debug'],
        ];

        // Allow third parties to add info
        return $this->apply_filters(__FUNCTION__.'_status', $status);
    }

    /**
     * Save video ID to ACF field with validation.
     *
     * @param string $video_id          The YouTube video ID
     * @param int    $post_id           The WordPress post ID
     * @param string $field_key         The ACF field key
     * @param bool   $check_permissions Whether to check edit permissions
     *
     * @return bool True on success
     *
     * @throws \InvalidArgumentException For invalid arguments
     * @throws \RuntimeException         For permission or save errors
     */
    private function save_video_id_to_field(string $video_id, int $post_id, string $field_key, bool $check_permissions = true): bool
    {
        if (empty($video_id)) {
            // translators: %s: field name (video_id)
            throw new \InvalidArgumentException(\sprintf(esc_html__('Empty field "%1$s"', 'upload-field-to-youtube-for-acf'), 'video_id'));
        }

        if (empty($post_id)) {
            // translators: %s: field name (post_id)
            throw new \InvalidArgumentException(\sprintf(esc_html__('Empty field "%1$s"', 'upload-field-to-youtube-for-acf'), 'post_id'));
        }

        if (empty($field_key)) {
            // translators: %s: field name (field_key)
            throw new \InvalidArgumentException(\sprintf(esc_html__('Empty field "%1$s"', 'upload-field-to-youtube-for-acf'), 'field_key'));
        }

        if ($check_permissions && !current_user_can('edit_post', $post_id)) {
            // translators: %s: video ID value
            throw new \RuntimeException(\sprintf(esc_html__('Insufficient permissions to save video "%1$s"', 'upload-field-to-youtube-for-acf'), esc_html($video_id)));
        }

        $this->do_action(__FUNCTION__.'_before', $post_id, $video_id, $field_key);

        $this->logger->debug('Saving video ID to field', [
            'video_id' => $video_id,
            'post_id' => $post_id,
            'field_key' => $field_key,
        ]);

        $result = update_field($field_key, $video_id, $post_id);

        if (!$result) {
            throw new \RuntimeException(esc_html__('Unable to save video', 'upload-field-to-youtube-for-acf'));
        }

        $this->do_action(__FUNCTION__.'_after', $post_id, $video_id, $field_key);

        return true;
    }

    /**
     * Get the cron schedule for OAuth token checks.
     *
     * @return string the cron schedule identifier
     *
     * @throws \Exception if an error occurs while retrieving the schedule
     */
    private function get_cron_schedule(): string
    {
        // Allow customization via filter
        $schedule = $this->apply_filters(__FUNCTION__.'_cron_schedule', $this->env['cron_schedule']);

        // Validate schedule exists in WordPress
        $schedules = wp_get_schedules();
        if (!isset($schedules[$schedule])) {
            $this->logger->warning('Invalid cron schedule specified, falling back to daily', [
                'requested_schedule' => $schedule,
                'available_schedules' => array_keys($schedules),
                'default_schedule' => $this->env['cron_schedule'],
            ]);
            $schedule = 'daily';
        }

        return $schedule;
    }

    /**
     * Get the cron hook name.
     *
     * @return string the cron hook name
     */
    private function get_cron_hook(): string
    {
        return $this->container->get('plugin_undername').'__check_oauth_token';
    }

    /**
     * Migrate options from old hyphen format to underscore format if needed.
     */
    private function migrate_options(): void
    {
        // Define migrations: old_key => new_key
        $option_migrations = [
            $this->container->get('plugin_name').'__access_token' => $this->container->get('plugin_undername').'__access_token',
            $this->container->get('plugin_name').'__activated' => $this->container->get('plugin_undername').'__activated',
        ];

        // Migrate options
        foreach ($option_migrations as $old_key => $new_key) {
            $this->migrate_option($old_key, $new_key);
        }

        // Migrate scheduled events: old_hook => new_hook
        $hook_migrations = [
            $this->container->get('plugin_name').'__check_oauth_token' => $this->get_cron_hook(),
        ];

        // Migrate scheduled events
        foreach ($hook_migrations as $old_hook => $new_hook) {
            $this->migrate_scheduled_event($old_hook, $new_hook);
        }
    }

    /**
     * Migrate a single option from old key to new key.
     */
    private function migrate_option(string $old_key, string $new_key): void
    {
        if ($old_key === $new_key) {
            return;
        }

        $old_value = get_option($old_key);
        if ($old_value && !get_option($new_key)) {
            update_option($new_key, $old_value);
            delete_option($old_key);
        }
    }

    /**
     * Migrate a scheduled event from old hook to new hook.
     */
    private function migrate_scheduled_event(string $old_hook, string $new_hook): void
    {
        if ($old_hook === $new_hook) {
            return;
        }

        $timestamp = wp_next_scheduled($old_hook);
        if ($timestamp && !wp_next_scheduled($new_hook)) {
            wp_unschedule_event($timestamp, $old_hook);
            wp_schedule_event(time(), 'hourly', $new_hook);
        }
    }
}
