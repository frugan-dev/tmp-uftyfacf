![](.wordpress-org/banner-1544x500.jpg)

![PHP Version](https://img.shields.io/packagist/php-v/wp-spaghetti/upload-field-to-youtube-for-acf)
![GitHub Downloads (all assets, all releases)](https://img.shields.io/github/downloads/wp-spaghetti/upload-field-to-youtube-for-acf/total)
![GitHub Actions Workflow Status](https://github.com/wp-spaghetti/upload-field-to-youtube-for-acf/actions/workflows/main.yml/badge.svg)
![Coverage Status](https://img.shields.io/codecov/c/github/wp-spaghetti/upload-field-to-youtube-for-acf)
![GitHub Issues](https://img.shields.io/github/issues/wp-spaghetti/upload-field-to-youtube-for-acf)
![PRs Welcome](https://img.shields.io/badge/PRs-welcome-brightgreen)
![GitHub Release](https://img.shields.io/github/v/release/wp-spaghetti/upload-field-to-youtube-for-acf)
![License](https://img.shields.io/github/license/wp-spaghetti/upload-field-to-youtube-for-acf)
<!--
![Code Climate](https://img.shields.io/codeclimate/maintainability/wp-spaghetti/upload-field-to-youtube-for-acf)
-->

# Upload Field to YouTube for ACF (WordPress Plugin)

__Upload Field to YouTube for ACF__ is a WordPress plugin that allows you to upload videos directly to YouTube via API from the WordPress admin area and/or select existing videos on your YouTube channel based on playlists. It is particularly useful for managing videos that may be associated with Custom Post Types (CPT).

To use this plugin, you need to configure Google oAuth credentials so the plugin can authenticate with the user's YouTube channel.

## Requirements

- PHP ^8.0
- WordPress ^5.7 || ^6.0
- [Advanced Custom Fields](https://www.advancedcustomfields.com) ^5.9 || ^6.0

## Features

- **Direct video upload to YouTube**: upload videos from CPTs within the WordPress interface via Google API
- **Dual upload modes**: client-side upload (browser → YouTube, default) or server-side upload (browser → WordPress → YouTube)  
- **Video selection from playlists**: select existing videos from your YouTube channel organized by playlists
- **Privacy control support**: manage "unlisted" and private videos, ideal for exclusive content tied to specific posts
- **Dependency injection architecture**: clean, extensible codebase with PHP-DI container for better maintainability
- **Official ACF integration**: built using the official [ACF Example Field Type](https://github.com/AdvancedCustomFields/acf-example-field-type) as foundation
- **Comprehensive logging system**: supports [Wonolog](https://github.com/inpsyde/Wonolog) ^2.x when available, with fallback to native file-based logging with multi-server protection
- **Modern JavaScript implementation**: built with [Vanilla JS](http://vanilla-js.com) (no jQuery dependency)
- **Professional development setup**: autoload classes with Composer and PSR-4, dependency management
- **Advanced field support**: full compatibility with ACF nested repeaters and complex field structures  
- **Internationalization ready**: translations managed via [Crowdin](https://crowdin.com/project/upload-field-to-youtube-for-acf)

## Installation

You can install the plugin in three ways: manually, via Composer (wpackagist) _(coming soon)_ or via Composer (package).

<details>
<summary>Manual Installation</summary>

1. Go to the [Releases](../../releases) section of this repository.
2. Download the latest release zip file.
3. Log in to your WordPress admin dashboard.
4. Navigate to `Plugins` > `Add New`.
5. Click `Upload Plugin`.
6. Choose the downloaded zip file and click `Install Now`.

</details>

<details>
<summary>Installation via Composer "wpackagist" (coming soon)</summary>

If you use Composer to manage WordPress plugins, you can install it from [WordPress Packagist](https://wpackagist.org):

1. Open your terminal.
2. Navigate to the root directory of your WordPress installation.
3. Ensure your `composer.json` file has the following configuration: *

```json
{
    "require": {
        "composer/installers": "^1.0 || ^2.0",
        "wpackagist-plugin/upload-field-to-youtube-for-acf": "^0.4"
    },
    "extra": {
        "installer-paths": {
            "wp-content/plugins/{$name}/": [
               "type:wordpress-plugin"
            ]
        }
    }
}
```
4. Run the following command:

```sh
composer update
```

<sub><i>
_Note:_  
_* `composer/installers` might already be required by another dependency._
</i></sub>
</details>

<details>
<summary>Installation via Composer "package"</summary>

If you use Composer to manage WordPress plugins, you can install it from this repository directly:

1. Open your terminal.
2. Navigate to the root directory of your WordPress installation.
3. Ensure your `composer.json` file has the following configuration: *

```json
{
    "require": {
        "composer/installers": "^1.0 || ^2.0",
        "wp-spaghetti/upload-field-to-youtube-for-acf": "^0.4"
    },
    "repositories": [
        {
            "type": "package",
            "package": {
                "name": "wp-spaghetti/upload-field-to-youtube-for-acf",
                "version": "0.4.0",
                "type": "wordpress-plugin",
                "dist": {
                    "url": "https://github.com/wp-spaghetti/upload-field-to-youtube-for-acf/releases/download/v0.4.0/upload-field-to-youtube-for-acf.zip",
                    "type": "zip"
                }
            }
        }
    ],
    "extra": {
        "installer-paths": {
            "wp-content/plugins/{$name}/": [
               "type:wordpress-plugin"
            ]
        }
    }
}
```
4. Run the following command:

```sh
composer update
```

<sub><i>
_Note:_  
_* `composer/installers` might already be required by another dependency._
</i></sub>
</details>

## Configuration

Once installed:

1. In your WordPress admin dashboard, navigate to the `Plugins` section and click `Activate Plugin`.
2. Create a new field via ACF and select the `YouTube Uploader` type.
3. Read the description above for advanced usage instructions.

### Creating the oAuth Credentials

1. Go to the [Google API Console](https://console.developers.google.com/).
2. Create a project or select an existing one.
3. Navigate to the **OAuth consent screen** section and configure the required details to enable authentication.
4. Then, go to **Credentials** and create an **OAuth 2.0 Client ID**.
5. In the **Authorized redirect URIs** field, enter your WordPress site's callback URL (e.g., `https://domain.tld/wp-admin/`).
6. Obtain the `Client ID` and `Client Secret`.

#### Limitations

If you use the API upload mode, please note this important notice [here](https://developers.google.com/youtube/v3/docs/videos/insert):

> All videos uploaded via the `videos.insert` endpoint from unverified API projects created after July 28, 2020, will be restricted to private viewing mode. To lift this restriction, each API project must undergo an audit to verify compliance with the YouTube Terms of Service.

### Required Configurations

Add the following lines to `wp-config.php` to define the oAuth credentials:

```php
define('WPSPAGHETTI_UFTYFACF_GOOGLE_OAUTH_CLIENT_ID', 'Client ID');
define('WPSPAGHETTI_UFTYFACF_GOOGLE_OAUTH_CLIENT_SECRET', 'Client Secret');
```

### Optional Configurations

The plugin supports several optional configuration constants in `wp-config.php`:

#### Performance & Caching

```php
// Enable filename-based cache busting for CSS/JS assets
define('WPSPAGHETTI_UFTYFACF_CACHE_BUSTING', true);

// Enable dependency injection container compilation cache (default: true)
define('WPSPAGHETTI_UFTYFACF_BUILDER_CACHE', true);
```

For more information on cache busting, see filename-based cache busting on [Nginx](https://github.com/h5bp/server-configs-nginx/blob/main/h5bp/location/web_performance_filename-based_cache_busting.conf) and [Apache](https://github.com/h5bp/server-configs-apache/blob/main/h5bp/web_performance/filename-based_cache_busting.conf).

#### Upload Behavior

```php
// Enable server-side upload mode (browser → WordPress → YouTube, default: false)
define('WPSPAGHETTI_UFTYFACF_SERVER_UPLOAD', true);

// Maximum chunks for resumable uploads (default: 10000)
define('WPSPAGHETTI_UFTYFACF_RESUMABLE_UPLOAD_MAX_CHUNKS', 10000);

// Time window for recent upload detection in seconds (default: 300)
define('WPSPAGHETTI_UFTYFACF_RECENT_UPLOAD_TIME_WINDOW', 300);
```

#### Video ID Retrieval Settings

```php
// Maximum attempts to retrieve video ID after upload (default: 5)
define('WPSPAGHETTI_UFTYFACF_VIDEO_ID_RETRIEVAL_MAX_ATTEMPTS', 5);

// Sleep interval between retrieval attempts in seconds (default: 3)
define('WPSPAGHETTI_UFTYFACF_VIDEO_ID_RETRIEVAL_SLEEP_INTERVAL', 3);

// Initial sleep before first retrieval attempt in seconds (default: 2)
define('WPSPAGHETTI_UFTYFACF_VIDEO_ID_RETRIEVAL_INITIAL_SLEEP', 2);
```

#### Maintenance & Logging

```php
// Cron schedule for token maintenance (default: 'daily')
define('WPSPAGHETTI_UFTYFACF_CRON_SCHEDULE', 'daily');

// Log retention period in days (default: 30)
define('WPSPAGHETTI_UFTYFACF_LOG_RETENTION_DAYS', 30);

// Disable all plugin logging (default: false)
define('WPSPAGHETTI_UFTYFACF_DISABLE_LOGGING', true);
```

## Actions

<details>
<summary>Bootstrap & Initialization</summary>

```php
do_action('upload_field_to_youtube_for_acf_bootstrap_before', $container);
do_action('upload_field_to_youtube_for_acf_bootstrap_after', $container);
do_action('upload_field_to_youtube_for_acf_muplugin_loaded');
do_action('upload_field_to_youtube_for_acf_plugin_loaded');
do_action('upload_field_to_youtube_for_acf_init_before', $container);
do_action('upload_field_to_youtube_for_acf_init_after', $container);
do_action('upload_field_to_youtube_for_acf_init_acf_not_available');
do_action('upload_field_to_youtube_for_acf_init_mu_plugin_activated');
do_action('upload_field_to_youtube_for_acf_admin_init_before', $container);
do_action('upload_field_to_youtube_for_acf_admin_init_after', $container);
do_action('upload_field_to_youtube_for_acf_admin_init_deactivated_missing_acf');
do_action('upload_field_to_youtube_for_acf_admin_init_save_settings', $_POST);
```

</details>

<details>
<summary>Field Lifecycle</summary>

```php
do_action('upload_field_to_youtube_for_acf_activate_before', $instance, $hook, $schedule);
do_action('upload_field_to_youtube_for_acf_activate_after', $instance, $hook, $schedule, $result);
do_action('upload_field_to_youtube_for_acf_deactivate_before', $network_deactivating, $instance, $hook);
do_action('upload_field_to_youtube_for_acf_deactivate_after', $network_deactivating, $instance, $hook, $timestamp);
do_action('upload_field_to_youtube_for_acf_render_field_before', $field);
do_action('upload_field_to_youtube_for_acf_render_field_after', $field);
```

</details>

<details>
<summary>Field Validation & Updates</summary>

```php
do_action('upload_field_to_youtube_for_acf_validate_value_before', $valid, $value, $field, $input);
do_action('upload_field_to_youtube_for_acf_validate_value_after', $valid, $value, $field, $input);
do_action('upload_field_to_youtube_for_acf_validate_value_error', $exception, $valid, $value, $field, $input);
do_action('upload_field_to_youtube_for_acf_update_value_before', $value, $post_id, $field);
do_action('upload_field_to_youtube_for_acf_update_value_after', $value, $post_id, $field, $response);
do_action('upload_field_to_youtube_for_acf_update_value_error', $exception, $value, $post_id, $field, $response);
```

</details>

<details>
<summary>Post Deletion</summary>

```php
do_action('upload_field_to_youtube_for_acf_before_delete_post_before', $post_id, $field);
do_action('upload_field_to_youtube_for_acf_before_delete_post_after', $post_id, $field);
do_action('upload_field_to_youtube_for_acf_before_delete_post_error', $exception, $post_id, $field);
```

</details>

<details>
<summary>Settings Page</summary>

```php
do_action('upload_field_to_youtube_for_acf_settings_page_before', $oauth, $this);
do_action('upload_field_to_youtube_for_acf_settings_page_after', $oauth, $this);
do_action('upload_field_to_youtube_for_acf_settings_page_authorize_before', $oauth, $this);
do_action('upload_field_to_youtube_for_acf_settings_page_authorize_after', $oauth, $this);
do_action('upload_field_to_youtube_for_acf_settings_page_authorized_before', $oauth, $this);
do_action('upload_field_to_youtube_for_acf_settings_page_authorized_after', $oauth, $this);
do_action('upload_field_to_youtube_for_acf_settings_page_error_before', $oauth, $this);
do_action('upload_field_to_youtube_for_acf_settings_page_error_after', $oauth, $this);
```

</details>

<details>
<summary>AJAX Actions</summary>

```php
do_action('upload_field_to_youtube_for_acf_wp_ajax_get_youtube_upload_url_before', $post_id, $field_key, $field);
do_action('upload_field_to_youtube_for_acf_wp_ajax_get_youtube_upload_url_after', $upload_url, $post_id, $field);
do_action('upload_field_to_youtube_for_acf_wp_ajax_get_youtube_upload_url_error', $exception, $post_id, $field);
do_action('upload_field_to_youtube_for_acf_wp_ajax_upload_video_to_youtube_before', $post_id, $field, $uploaded_file);
do_action('upload_field_to_youtube_for_acf_wp_ajax_upload_video_to_youtube_after', $video_id, $post_id, $field);
do_action('upload_field_to_youtube_for_acf_wp_ajax_upload_video_to_youtube_error', $exception, $post_id, $field_key);
do_action('upload_field_to_youtube_for_acf_wp_ajax_get_video_id_from_upload_before', $upload_id, $post_id, $field_key);
do_action('upload_field_to_youtube_for_acf_wp_ajax_get_video_id_from_upload_after', $video_id, $upload_id, $post_id, $field_key);
do_action('upload_field_to_youtube_for_acf_wp_ajax_get_video_id_from_upload_error', $exception, $video_id, $upload_id, $post_id, $field_key);
do_action('upload_field_to_youtube_for_acf_wp_ajax_save_youtube_video_id_before', $post_id, $video_id, $field_key);
do_action('upload_field_to_youtube_for_acf_wp_ajax_save_youtube_video_id_after', $post_id, $video_id, $field_key);
do_action('upload_field_to_youtube_for_acf_wp_ajax_save_youtube_video_id_error', $exception, $post_id, $video_id, $field_key);
do_action('upload_field_to_youtube_for_acf_wp_ajax_get_videos_by_playlist_before', $field_key, $field, $playlist_id);
do_action('upload_field_to_youtube_for_acf_wp_ajax_get_videos_by_playlist_after', $field_key, $field, $playlist_id, $result);
do_action('upload_field_to_youtube_for_acf_wp_ajax_get_videos_by_playlist_error', $exception, $field_key, $field, $playlist_id);
```

</details>

<details>
<summary>YouTube API Operations</summary>

```php
do_action('upload_field_to_youtube_for_acf_get_playlists_by_privacy_status_before', $privacy_status);
do_action('upload_field_to_youtube_for_acf_get_playlists_by_privacy_status_after', $privacy_status, $result);
do_action('upload_field_to_youtube_for_acf_get_playlists_by_privacy_status_error', $exception, $privacy_status);
do_action('upload_field_to_youtube_for_acf_get_youtube_upload_url_before', $post_id, $field);
do_action('upload_field_to_youtube_for_acf_get_youtube_upload_url_after', $upload_url, $post_id, $field);
do_action('upload_field_to_youtube_for_acf_upload_video_to_youtube_before', $post_id, $field, $file_data);
do_action('upload_field_to_youtube_for_acf_upload_video_to_youtube_after', $video_id, $post_id, $field);
do_action('upload_field_to_youtube_for_acf_get_video_id_from_upload_before', $upload_id);
do_action('upload_field_to_youtube_for_acf_get_video_id_from_upload_after', $video_id, $upload_id);
do_action('upload_field_to_youtube_for_acf_get_videos_by_playlist_before', $playlist_id, $privacy_status);
do_action('upload_field_to_youtube_for_acf_get_videos_by_playlist_after', $playlist_id, $privacy_status, $response, $result);
do_action('upload_field_to_youtube_for_acf_create_resumable_upload_request_before', $post_id, $field);
do_action('upload_field_to_youtube_for_acf_create_resumable_upload_request_after', $request_data, $post_id, $field);
```

</details>

<details>
<summary>Google Client & Authentication</summary>

```php
do_action('upload_field_to_youtube_for_acf_set_google_client_after', $this->client);
```

</details>

<details>
<summary>Cache Management</summary>

```php
do_action('upload_field_to_youtube_for_acf_get_access_token_before', $cache_info);
do_action('upload_field_to_youtube_for_acf_get_access_token_after', $token, $cache_info);
do_action('upload_field_to_youtube_for_acf_save_access_token_before', $token);
do_action('upload_field_to_youtube_for_acf_save_access_token_after', $token, $cache_info);
do_action('upload_field_to_youtube_for_acf_save_access_token_error', $token, $cache_info);
do_action('upload_field_to_youtube_for_acf_save_access_token_invalid_token_format', $token);
do_action('upload_field_to_youtube_for_acf_delete_access_token_before', $cache_info);
do_action('upload_field_to_youtube_for_acf_delete_access_token_after', $cache_info);
do_action('upload_field_to_youtube_for_acf_cache_info_after', $cache_info);
do_action('upload_field_to_youtube_for_acf_clear_all_caches_before');
do_action('upload_field_to_youtube_for_acf_clear_all_caches_after', $cache_clear_methods);
do_action('upload_field_to_youtube_for_acf_aggressive_cache_clear_before');
do_action('upload_field_to_youtube_for_acf_aggressive_cache_clear_after');
```

</details>

<details>
<summary>Logging</summary>

```php
do_action('upload_field_to_youtube_for_acf_log', $level, $message, $context);
do_action('upload_field_to_youtube_for_acf_log_emergency', $message, $context);
do_action('upload_field_to_youtube_for_acf_log_alert', $message, $context);
do_action('upload_field_to_youtube_for_acf_log_critical', $message, $context);
do_action('upload_field_to_youtube_for_acf_log_error', $message, $context);
do_action('upload_field_to_youtube_for_acf_log_warning', $message, $context);
do_action('upload_field_to_youtube_for_acf_log_notice', $message, $context);
do_action('upload_field_to_youtube_for_acf_log_info', $message, $context);
do_action('upload_field_to_youtube_for_acf_log_debug', $message, $context);
```

</details>

<details>
<summary>Utility Actions</summary>

```php
do_action('upload_field_to_youtube_for_acf_save_video_id_to_field_before', $post_id, $video_id, $field_key);
do_action('upload_field_to_youtube_for_acf_save_video_id_to_field_after', $post_id, $video_id, $field_key);
```

</details>

<details>
<summary>Cron Hook</summary>

```php
do_action('upload_field_to_youtube_for_acf__check_oauth_token');
```

</details>

## Filters

<details>
<summary>Configuration Filters</summary>

```php
apply_filters('upload_field_to_youtube_for_acf_field_defaults', $defaults);
apply_filters('upload_field_to_youtube_for_acf_env_settings', $settings);
apply_filters('upload_field_to_youtube_for_acf_allowed_video_mime_types', $mime_types);
apply_filters('upload_field_to_youtube_for_acf_container_definitions', $definitions);
```

</details>

<details>
<summary>Field Instance & Rendering</summary>

```php
apply_filters('upload_field_to_youtube_for_acf_field_instance', $this->container->get(Field::class));
apply_filters('upload_field_to_youtube_for_acf_render_field_field', $field);
```

</details>

<details>
<summary>Google Client Configuration</summary>

```php
apply_filters('upload_field_to_youtube_for_acf_set_google_client_data', $data);
apply_filters('upload_field_to_youtube_for_acf_set_google_client_scopes', $scopes);
apply_filters('upload_field_to_youtube_for_acf_set_google_client_client', $this->client);
```

</details>

<details>
<summary>Field Validation & Updates</summary>

```php
apply_filters('upload_field_to_youtube_for_acf_validate_value_should_update', $should_update, $value, $field, $input, $post_id);
apply_filters('upload_field_to_youtube_for_acf_update_value_should_update', $should_update, $value, $post_id, $field);
apply_filters('upload_field_to_youtube_for_acf_update_value_data', $data, $value, $post_id, $field);
apply_filters('upload_field_to_youtube_for_acf_before_delete_post_should_delete', $field['api_delete_on_post_delete'], $post_id, $field);
```

</details>

<details>
<summary>AJAX Field Retrieval</summary>

```php
apply_filters('upload_field_to_youtube_for_acf_wp_ajax_get_youtube_upload_url_field', get_field_object($field_key));
```

</details>

<details>
<summary>Cron Configuration</summary>

```php
apply_filters('upload_field_to_youtube_for_acf_get_cron_schedule_cron_schedule', $this->env['cron_schedule']);
apply_filters('upload_field_to_youtube_for_acf_get_cron_status_status', $status);
```

</details>

<details>
<summary>Cache Management</summary>

```php
apply_filters('upload_field_to_youtube_for_acf_cache_info', $cache_info);
apply_filters('upload_field_to_youtube_for_acf_cache_clear_methods', $cache_clear_methods);
apply_filters('upload_field_to_youtube_for_acf_get_access_token_token', $token, $cache_info);
apply_filters('upload_field_to_youtube_for_acf_save_access_token_token', $token);
```

</details>

<details>
<summary>Logging Configuration</summary>

```php
apply_filters('upload_field_to_youtube_for_acf_wonolog_namespace', 'Inpsyde\Wonolog');
apply_filters('upload_field_to_youtube_for_acf_wonolog_prefix', $wonolog_prefix, $level, $message, $context);
```

</details>

## More info

See [LINKS](docs/LINKS.md) file.

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for a detailed list of changes for each release.

We follow [Semantic Versioning](https://semver.org/) and use [Conventional Commits](https://www.conventionalcommits.org/) to automatically generate our changelog.

### Release Process

- **Major versions** (1.0.0 → 2.0.0): Breaking changes
- **Minor versions** (1.0.0 → 1.1.0): New features, backward compatible
- **Patch versions** (1.0.0 → 1.0.1): Bug fixes, backward compatible

All releases are automatically created when changes are pushed to the `main` branch, based on commit message conventions.

## Contributing

For your contributions please use:

- [Conventional Commits](https://www.conventionalcommits.org)
- [git-flow workflow](https://danielkummer.github.io/git-flow-cheatsheet/)
- [Pull request workflow](https://docs.github.com/en/get-started/exploring-projects-on-github/contributing-to-a-project)

See [CONTRIBUTING](.github/CONTRIBUTING.md) for detailed guidelines.

## Sponsor

[<img src="https://cdn.buymeacoffee.com/buttons/v2/default-yellow.png" width="200" alt="Buy Me A Coffee">](https://buymeacoff.ee/frugan)

## License

(ɔ) Copyleft 2025 [Frugan](https://frugan.it).  
[GNU GPLv3](https://choosealicense.com/licenses/gpl-3.0/), see [LICENSE](LICENSE) file.
