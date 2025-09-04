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

if (!defined('ABSPATH')) {
    exit;
}

use WpSpaghetti\UFTYFACF\Service\CacheHandler;
use WpSpaghetti\UFTYFACF\Service\GoogleClientManager;
use WpSpaghetti\UFTYFACF\Service\YoutubeApiService;
use WpSpaghetti\WpEnv\Environment;
use WpSpaghetti\WpLogger\Logger;
use Psr\Container\ContainerInterface;

return [
    'plugin_prefix' => 'wpspaghetti_uftyfacf',
    'plugin_name' => WPSPAGHETTI_UFTYFACF_NAME, // Single words, no spaces, hyphens allowed
    'plugin_undername' => WPSPAGHETTI_UFTYFACF_UNDERNAME, // Single words, no spaces, underscores allowed

    'field_defaults' => \DI\factory(static fn (ContainerInterface $container) => apply_filters($container->get('plugin_prefix').'_field_defaults', [
        'category_id' => 22, // People & Blogs
        'tags' => !empty($_SERVER['HTTP_HOST']) ? str_replace('www.', '', sanitize_text_field(wp_unslash($_SERVER['HTTP_HOST']))) : '',
        'privacy_status' => 'unlisted',
        'made_for_kids' => false,
        'allow_upload' => true,
        'allow_select' => true,
        'api_update_on_post_update' => true,
        'api_delete_on_post_delete' => false,
    ])),

    'env_settings' => \DI\factory(static fn (ContainerInterface $container) => apply_filters($container->get('plugin_prefix').'_env_settings', [
        'version' => WPSPAGHETTI_UFTYFACF_VERSION,
        'url' => WPSPAGHETTI_UFTYFACF_URL,
        'path' => WPSPAGHETTI_UFTYFACF_PATH,
        'debug' => Environment::getBool('WP_DEBUG', false),
        'locale' => get_locale(),
        'server_upload' => Environment::getBool('WPSPAGHETTI_UFTYFACF_SERVER_UPLOAD', false),
        'cache_busting' => Environment::getBool('WPSPAGHETTI_UFTYFACF_CACHE_BUSTING', false),
        'cron_schedule' => Environment::get('WPSPAGHETTI_UFTYFACF_CRON_SCHEDULE', 'daily'),
        'recent_upload_time_window' => Environment::getInt('WPSPAGHETTI_UFTYFACF_RECENT_UPLOAD_TIME_WINDOW', 300), // 5 minutes
        'resumable_upload_max_chunks' => Environment::getInt('WPSPAGHETTI_UFTYFACF_RESUMABLE_UPLOAD_MAX_CHUNKS', 10000), // Increased limit for very large files
        'video_id_retrieval_max_attempts' => Environment::getInt('WPSPAGHETTI_UFTYFACF_VIDEO_ID_RETRIEVAL_MAX_ATTEMPTS', 5), // Maximum retry attempts
        'video_id_retrieval_sleep_interval' => Environment::getInt('WPSPAGHETTI_UFTYFACF_VIDEO_ID_RETRIEVAL_SLEEP_INTERVAL', 3), // Sleep between attempts in seconds
        'video_id_retrieval_initial_sleep' => Environment::getInt('WPSPAGHETTI_UFTYFACF_VIDEO_ID_RETRIEVAL_INITIAL_SLEEP', 2), // Initial sleep before first attempt in seconds
        'log_retention_days' => Environment::getInt('WPSPAGHETTI_UFTYFACF_LOG_RETENTION_DAYS', 30), // Days to keep log files
        'disable_logging' => Environment::getBool('WPSPAGHETTI_UFTYFACF_DISABLE_LOGGING', false),
    ])),

    'allowed_video_mime_types' => \DI\factory(static fn (ContainerInterface $container) => apply_filters($container->get('plugin_prefix').'_allowed_video_mime_types', [
        // Common video formats supported by YouTube
        'mp4' => 'video/mp4',
        'avi' => 'video/avi',
        'mov' => 'video/quicktime',
        'wmv' => 'video/x-ms-wmv',
        'flv' => 'video/x-flv',
        'webm' => 'video/webm',
        'mkv' => 'video/x-matroska',
        '3gp' => 'video/3gpp',
        'ogv' => 'video/ogg',
        'm4v' => 'video/mp4',
        'mpg' => 'video/mpeg',
        'mpeg' => 'video/mpeg',
        'mts' => 'video/mp2t',
        'ts' => 'video/mp2t',
    ])),

    'wp_filesystem' => static function (): WP_Filesystem_Direct {
        // https://wordpress.stackexchange.com/a/370377/99214
        if (!function_exists('WP_Filesystem_Direct')) {
            require_once ABSPATH.'wp-admin/includes/class-wp-filesystem-base.php';

            require_once ABSPATH.'wp-admin/includes/class-wp-filesystem-direct.php';
        }

        return new WP_Filesystem_Direct(null);
    },

    CacheHandler::class => \DI\autowire(),

    GoogleClientManager::class => \DI\autowire(),

    YoutubeApiService::class => \DI\autowire(),

    Logger::class => function (ContainerInterface $container) {
        // Use Environment class to detect testing environment
        $minLogLevel = Environment::isTesting() ? 'emergency' : (Environment::isDebug() ? 'debug' : 'info');

        return new Logger([
            'plugin_name' => $container->get('plugin_name'),
            'min_log_level' => $minLogLevel,
        ]);
    },

    // Allow third-party extensions to add their own definitions
    // This can be extended via WordPress filters
];
