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

// Define WordPress constants for testing
if (!defined('ABSPATH')) {
    define('ABSPATH', '/tmp/');
}

if (!defined('WP_DEBUG')) {
    define('WP_DEBUG', false);
}

if (!defined('WP_ENV')) {
    define('WP_ENV', 'testing');
}

if (!defined('WP_ENVIRONMENT_TYPE')) {
    define('WP_ENVIRONMENT_TYPE', 'testing');
}

// Autoload Composer dependencies
require_once __DIR__.'/../vendor/autoload.php';

// Global test variables for mocking
global $applied_filters, $triggered_actions, $mock_constants, $mock_env_vars, $mock_files, $mock_environment_vars;

$applied_filters = [];
$triggered_actions = [];
$mock_constants = [];
$mock_env_vars = [];
$mock_files = [];
$mock_environment_vars = [];

// Mock WordPress functions for testing

if (!function_exists('apply_filters')) {
    /**
     * @param mixed $value
     * @param mixed ...$args
     *
     * @return mixed
     */
    function apply_filters(string $hook_name, $value, ...$args)
    {
        global $applied_filters;

        $applied_filters[] = [
            'hook' => $hook_name,
            'value' => $value,
            'args' => $args,
        ];

        // Return test overrides for specific scenarios
        return match ($hook_name) {
            'wp_env_get_value' => $applied_filters[count($applied_filters) - 1]['test_override'] ?? $value,
            'wp_env_get_environment' => $applied_filters[count($applied_filters) - 1]['test_override'] ?? $value,
            'wp_env_is_docker' => $applied_filters[count($applied_filters) - 1]['test_override'] ?? $value,
            'wp_env_is_container' => $applied_filters[count($applied_filters) - 1]['test_override'] ?? $value,
            'wp_env_is_sensitive_key' => $applied_filters[count($applied_filters) - 1]['test_override'] ?? $value,
            default => $value,
        };
    }
}

if (!function_exists('do_action')) {
    /**
     * @param mixed ...$args
     */
    function do_action(string $hook_name, ...$args): void
    {
        global $triggered_actions;

        $triggered_actions[] = [
            'hook' => $hook_name,
            'args' => $args,
        ];
    }
}

if (!function_exists('get_bloginfo')) {
    function get_bloginfo(string $show = ''): string
    {
        return match ($show) {
            'version' => '6.4',
            'url' => 'https://example.com',
            default => 'WordPress Test'
        };
    }
}

if (!function_exists('current_user_can')) {
    function current_user_can(string $capability): bool
    {
        return 'administrator' === $capability;
    }
}

// Override built-in functions for testing
if (!function_exists('mock_file_exists')) {
    function mock_file_exists(string $filename): bool
    {
        global $mock_files;

        if (isset($mock_files[$filename])) {
            return (bool) $mock_files[$filename];
        }

        // Fall back to real file_exists for normal files
        return file_exists($filename);
    }
}

if (!function_exists('mock_file_get_contents')) {
    function mock_file_get_contents(string $filename): false|string
    {
        global $mock_files;

        if (isset($mock_files[$filename]) && true === $mock_files[$filename]) {
            // Mock file content based on filename
            return match ($filename) {
                '/.dockerenv' => '',
                '/proc/1/cgroup' => 'docker',
                '/proc/self/mountinfo' => 'docker overlay',
                default => 'mock content',
            };
        }

        if (isset($mock_files[$filename]) && false === $mock_files[$filename]) {
            return false;
        }

        // Fall back to real function for unmocked files
        return file_exists($filename) ? file_get_contents($filename) : false;
    }
}

if (!function_exists('mock_getenv')) {
    function mock_getenv(string $varname): false|string
    {
        global $mock_env_vars;

        return $mock_env_vars[$varname] ?? false;
    }
}

// Test utilities for managing global state
function reset_wp_env_test_globals(): void
{
    global $applied_filters, $triggered_actions, $mock_constants, $mock_env_vars, $mock_files, $mock_environment_vars;

    $applied_filters = [];
    $triggered_actions = [];
    $mock_constants = [];
    $mock_env_vars = [];
    $mock_files = [];
    $mock_environment_vars = [];
}

/**
 * @param mixed $value
 */
function set_mock_constant(string $name, $value): void
{
    global $mock_constants;
    $mock_constants[$name] = $value;

    if (!defined($name)) {
        define($name, $value);
    }
}

/**
 * Set mock environment variable (legacy system).
 */
function set_mock_env_var(string $name, string $value): void
{
    global $mock_env_vars;
    $mock_env_vars[$name] = $value;
}

/**
 * Set mock environment variable (new system with highest priority).
 *
 * @param mixed $value
 */
function set_mock_environment_var(string $name, $value): void
{
    global $mock_environment_vars;
    $mock_environment_vars[$name] = $value;
}

function set_mock_file(string $filename, bool $exists = true): void
{
    global $mock_files;
    $mock_files[$filename] = $exists;
}

/**
 * @return array<int, array<string, mixed>>
 */
function get_applied_filters(): array
{
    global $applied_filters;

    return $applied_filters;
}

/**
 * @return array<int, array<string, mixed>>
 */
function get_triggered_actions(): array
{
    global $triggered_actions;

    return $triggered_actions;
}

/**
 * @param mixed $override_value
 */
function set_filter_override(string $hook, $override_value): void
{
    global $applied_filters;

    // Find the last entry for this hook and set override
    for ($i = count($applied_filters) - 1; $i >= 0; --$i) {
        if ($applied_filters[$i]['hook'] === $hook) {
            $applied_filters[$i]['test_override'] = $override_value;

            break;
        }
    }
}
