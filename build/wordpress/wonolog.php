<?php

declare(strict_types=1);

/**
 * Plugin Name: Wonolog Init
 * Description: Initialize Wonolog logging
 * Version: 1.0.0
 * Author: Frugan
 */

use Inpsyde\Wonolog\Configurator;
use Inpsyde\Wonolog\LogLevel;
use Inpsyde\Wonolog\HookListener\HttpApiListener;
use Monolog\Formatter\HtmlFormatter;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\ErrorLogHandler;
use Monolog\Handler\NativeMailerHandler;
use Monolog\Processor\PsrLogMessageProcessor;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * This MU plugin is required to load the Composer autoloader early enough
 * for Wonolog to auto-bootstrap itself.
 * 
 * Wonolog tries to register on 'muplugins_loaded' hook, but when loaded from
 * a regular plugin, that hook has already fired. By requiring the autoloader
 * in this MU plugin, Wonolog becomes available before 'muplugins_loaded'
 * and can properly register its setup callback.
 * 
 * Without this, Wonolog would never initialize and the plugin would fall back
 * to its built-in error_log() based logging system.
 */
if (file_exists(WP_PLUGIN_DIR.'/upload-field-to-youtube-for-acf/vendor/autoload.php')) {
    require WP_PLUGIN_DIR.'/upload-field-to-youtube-for-acf/vendor/autoload.php';
}

if (!class_exists(Configurator::class)) {
    return;
}

add_action(Configurator::ACTION_SETUP, function (Configurator $configurator) {
    $defaultHandler = new ErrorLogHandler(ErrorLogHandler::SAPI, LogLevel::defaultMinLevel());

    // The last "true" here tells monolog to remove empty []'s
    $defaultHandler->setFormatter(new LineFormatter(null, null, false, true));

    // Get the site domain and get rid of www.
    $sitename = wp_parse_url(network_home_url(), PHP_URL_HOST);
    $from_email = 'wordpress@';

    if (null !== $sitename) {
        if (str_starts_with($sitename, 'www.')) {
            $sitename = substr($sitename, 4);
        }

        $from_email .= $sitename;
    }

    $emailHandler = new NativeMailerHandler(
        get_option('admin_email'),
        \sprintf('Error reporting from %1$s', $sitename),
        $from_email,
        LogLevel::ERROR
    );
    $emailHandler->setContentType('text/html');
    $emailHandler->setFormatter(new HtmlFormatter());

    $configurator->logSilencedPhpErrors();

    if (\is_string(WP_DEBUG_LOG) || WP_DEBUG_LOG) {
        $errorTypes = E_ALL & ~E_WARNING & ~E_NOTICE & ~E_USER_WARNING & ~E_USER_NOTICE & ~E_STRICT & ~E_DEPRECATED & ~E_USER_DEPRECATED;
        $configurator->logPhpErrorsTypes($errorTypes);
    }

    $configurator->disableFallbackHandler()
        // Disable default HttpApiListener (logs at ERROR level)
        // See: https://github.com/inpsyde/Wonolog/issues/83
        ->disableDefaultHookListeners(HttpApiListener::class)
        // Add custom HttpApiListener with WARNING level instead of ERROR
        // Since HttpApiListener is final, we need to create a new instance
        // with the desired log level in the constructor
        ->addActionListener(new HttpApiListener(LogLevel::WARNING))
        ->pushHandler($defaultHandler)
        ->pushHandler($emailHandler)
        // for placeholder substitution
        ->pushProcessor('psr-log-message-processor', new PsrLogMessageProcessor())
        ->pushProcessor('extra-processor', function (array $record) {
            $record['extra']['_REQUEST'] = $_REQUEST;
            $record['extra']['_POST'] = $_POST;
            $record['extra']['_FILES'] = $_FILES;
            $record['extra']['_SERVER'] = str_contains(\ini_get('variables_order'), 'E') ? $_SERVER : array_diff_key($_SERVER, $_ENV);
            $record['extra']['_SESSION'] = $_SESSION ?? null;

            return $record;
        })
    ;
});
