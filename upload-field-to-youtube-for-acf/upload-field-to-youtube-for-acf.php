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

use WpSpaghetti\UFTYFACF\Bootstrap;

/*
 * Plugin Name: Upload Field to YouTube for ACF
 * Plugin URI: https://github.com/wp-spaghetti/upload-field-to-youtube-for-acf
 * Description: Upload Field to YouTube for ACF is a WordPress plugin that allows you to upload videos directly to YouTube via API from the WordPress admin area and/or select existing videos on your YouTube channel based on playlists.
 * Version: 0.4.0
 * Requires Plugins: advanced-custom-fields
 * Requires PHP: 8.0
 * Author: Frugan
 * Author URI: https://github.com/wp-spaghetti
 * License: GPLv3 or later
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 * Donate link: https://buymeacoff.ee/frugan
 */

if (!defined('ABSPATH')) {
    exit;
}

if (file_exists(__DIR__.'/vendor/autoload.php')) {
    require __DIR__.'/vendor/autoload.php';
}

define('WPSPAGHETTI_UFTYFACF_VERSION', '0.4.0');
define('WPSPAGHETTI_UFTYFACF_BASENAME', plugin_basename(__FILE__));
define('WPSPAGHETTI_UFTYFACF_NAME', dirname(WPSPAGHETTI_UFTYFACF_BASENAME));
define('WPSPAGHETTI_UFTYFACF_UNDERNAME', str_replace('-', '_', WPSPAGHETTI_UFTYFACF_NAME));
define('WPSPAGHETTI_UFTYFACF_URL', plugin_dir_url(__FILE__));
define('WPSPAGHETTI_UFTYFACF_PATH', plugin_dir_path(__FILE__));

require_once __DIR__.'/include/function.php';

// Initialize the plugin with dependency injection
$container = wpspaghetti_uftyfacf_get_container();
$bootstrap = $container->get(Bootstrap::class);

// Store container reference for global access if needed
$GLOBALS['wpspaghetti_uftyfacf_container'] = $container;
