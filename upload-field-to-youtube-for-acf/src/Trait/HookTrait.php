<?php

declare(strict_types=1);

/*
 * This file is part of the WordPress plugin "Upload Field to YouTube for ACF".
 *
 * (É") Frugan <dev@frugan.it>
 *
 * This source file is subject to the GNU GPLv3 or later license that is bundled
 * with this source code in the file LICENSE.
 */

namespace WpSpaghetti\UFTYFACF\Trait;

use DI\Container;

if (!\defined('ABSPATH')) {
    exit;
}

/**
 * Trait HookTrait - Provides consistent hook naming for WordPress actions and filters.
 */
trait HookTrait
{
    /**
     * Cached hook prefix for this class instance.
     */
    private string $hook_prefix;

    /**
     * Initialize the hook functionality.
     * Should be called in the constructor of classes using this trait.
     *
     * @param Container $container the dependency injection container
     */
    protected function init_hook(Container $container): void
    {
        $this->hook_prefix = $container->get('plugin_prefix').'_'.$this->get_short_name().'_';
    }

    /**
     * Get the hook prefix for this class.
     *
     * @return string the hook prefix (e.g., 'wpspaghetti_uftyfacf_bootstrap')
     */
    protected function get_hook_prefix(): string
    {
        return $this->hook_prefix;
    }

    /**
     * Execute a WordPress action with the class-specific prefix.
     *
     * @param string $action the action name (will be prefixed)
     * @param mixed  ...$args action arguments
     */
    protected function do_action(string $action, ...$args): void
    {
        do_action($this->get_hook_prefix().$action, ...$args);
    }

    /**
     * Apply a WordPress filter with the class-specific prefix.
     *
     * @param string $filter the filter name (will be prefixed)
     * @param mixed  $value  the value to filter
     * @param mixed  ...$args additional filter arguments
     *
     * @return mixed the filtered value
     */
    protected function apply_filters(string $filter, $value, ...$args)
    {
        return apply_filters($this->get_hook_prefix().$filter, $value, ...$args);
    }

    /**
     * Add a WordPress action with the class-specific prefix.
     * 
     * Note: This method is public (not protected) and uses the exact same signature 
     * to match the visibility and compatibility requirements of ACF's acf_field parent class.
     *
     * @param string   $tag             the action name (will be prefixed)
     * @param callable $function_to_add the callback function
     * @param int      $priority        the priority (default: 10)
     * @param int      $accepted_args   the number of arguments (default: 1)
     */
    public function add_action($tag = '', $function_to_add = '', $priority = 10, $accepted_args = 1)
    {
        add_action($this->get_hook_prefix().$tag, $function_to_add, $priority, $accepted_args);
    }

    /**
     * Add a WordPress filter with the class-specific prefix.
     * 
     * Note: This method is public (not protected) and uses the exact same signature 
     * to match the visibility and compatibility requirements of ACF's acf_field parent class.
     *
     * @param string   $tag             the filter name (will be prefixed)
     * @param callable $function_to_add the callback function
     * @param int      $priority        the priority (default: 10)
     * @param int      $accepted_args   the number of arguments (default: 1)
     */
    public function add_filter($tag = '', $function_to_add = '', $priority = 10, $accepted_args = 1)
    {
        add_filter($this->get_hook_prefix().$tag, $function_to_add, $priority, $accepted_args);
    }

    /**
     * Remove a WordPress action with the class-specific prefix.
     *
     * @param string   $action   the action name (will be prefixed)
     * @param callable $callback the callback function
     * @param int      $priority the priority (default: 10)
     *
     * @return bool true on success, false on failure
     */
    protected function remove_action(string $action, callable $callback, int $priority = 10): bool
    {
        return remove_action($this->get_hook_prefix().$action, $callback, $priority);
    }

    /**
     * Remove a WordPress filter with the class-specific prefix.
     *
     * @param string   $filter   the filter name (will be prefixed)
     * @param callable $callback the callback function
     * @param int      $priority the priority (default: 10)
     *
     * @return bool true on success, false on failure
     */
    protected function remove_filter(string $filter, callable $callback, int $priority = 10): bool
    {
        return remove_filter($this->get_hook_prefix().$filter, $callback, $priority);
    }

    /**
     * Get the short class name in lowercase.
     *
     * @return string the lowercase class name (e.g., 'bootstrap')
     */
    private function get_short_name(): string
    {
        return strtolower((new \ReflectionClass(static::class))->getShortName());
    }
}