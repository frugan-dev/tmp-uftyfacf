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

use DI\ContainerBuilder;
use Psr\Container\ContainerInterface;
use WpSpaghetti\WpEnv\Environment;

if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('wpspaghetti_uftyfacf_init_container')) {
    /**
     * Initialize the dependency injection container.
     */
    function wpspaghetti_uftyfacf_init_container(): ContainerInterface
    {
        $builder = new ContainerBuilder();

        // https://php-di.org/doc/autowiring.html
        // Autowiring is enabled by default
        $builder->useAutowiring(true);

        // https://php-di.org/doc/attributes.html
        // Attributes are disabled by default
        $builder->useAttributes(false);

        // Load container definitions
        $definitions = require dirname(__DIR__).'/include/container.php';

        // Allow third-party modifications
        $definitions = apply_filters(__FUNCTION__.'_definitions', $definitions);

        // Set cache directory in WordPress cache folder
        $cache_dir = WP_CONTENT_DIR.'/cache/'.$definitions['plugin_name'];
        $proxies_dir = $cache_dir.'/proxies';

        // Check if we should use ContainerBuilder (with cache)
        $builder_cache = Environment::getBool('WPSPAGHETTI_UFTYFACF_BUILDER_CACHE_ENABLED', true);

        if ($builder_cache) {
            // Ensure cache directory exists
            if (!file_exists($cache_dir)) {
                wp_mkdir_p($cache_dir);
            }

            // Uncaught LogicException: You cannot set a definition at runtime on a compiled container.
            // You can either put your definitions in a file, disable compilation
            // or ->set() a raw value directly (PHP object, string, int, ...) instead of a PHP-DI definition.
            $builder->enableCompilation($cache_dir);

            $builder->writeProxiesToFile(true, $proxies_dir);
        } else {
            // https://wordpress.stackexchange.com/a/370377/99214
            if (!function_exists('WP_Filesystem_Direct')) {
                require_once ABSPATH.'wp-admin/includes/class-wp-filesystem-base.php';

                require_once ABSPATH.'wp-admin/includes/class-wp-filesystem-direct.php';
            }

            $wp_filesystem = new WP_Filesystem_Direct(null);

            if ($wp_filesystem->is_dir($cache_dir)) {
                $wp_filesystem->delete($cache_dir, true);
            }
        }

        // https://github.com/PHP-DI/PHP-DI/issues/674
        $builder->addDefinitions($definitions);

        return $builder->build();
    }
}

if (!function_exists('wpspaghetti_uftyfacf_get_container')) {
    /**
     * Get the global container instance.
     */
    function wpspaghetti_uftyfacf_get_container(): ContainerInterface
    {
        static $container = null;

        if (null === $container) {
            $container = wpspaghetti_uftyfacf_init_container();
        }

        return $container;
    }
}
