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

namespace WpSpaghetti\UFTYFACF\Trait;

if (!\defined('ABSPATH')) {
    exit;
}

/**
 * Trait HookTrait - Complete hook functionality including conflicting methods.
 * 
 * This trait provides the full hook functionality for classes that do not extend acf_field.
 * It includes add_action() and add_filter() methods that would conflict with acf_field,
 * so it should NOT be used in classes that extend acf_field (use BaseHookTrait instead).
 */
trait HookTrait
{
    use BaseHookTrait;

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
    public function add_action($tag = '', $function_to_add = '', $priority = 10, $accepted_args = 1): void
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
    public function add_filter($tag = '', $function_to_add = '', $priority = 10, $accepted_args = 1): void
    {
        add_filter($this->get_hook_prefix().$tag, $function_to_add, $priority, $accepted_args);
    }
}
