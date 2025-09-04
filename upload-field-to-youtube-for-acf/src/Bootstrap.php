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
use WpSpaghetti\UFTYFACF\Trait\HookTrait;

if (!\defined('ABSPATH')) {
    exit;
}

/**
 * Class Bootstrap - Plugin bootstrap class.
 */
class Bootstrap
{
    use HookTrait;

    /**
     * Field instance.
     */
    private Field $field_instance;

    /**
     * Constructor.
     *
     * @param Container $container the dependency injection container
     */
    public function __construct(
        private Container $container
    ) {
        $this->init_hook($container);

        $this->do_action(__FUNCTION__.'_before');

        $this->field_instance = $this->apply_filters(__FUNCTION__.'_field_instance', $this->container->get(Field::class));

        add_action('muplugins_loaded', [$this, 'muplugins_loaded']);
        add_action('plugins_loaded', [$this, 'plugins_loaded']);
        add_action('init', [$this, 'init']);
        add_action('admin_init', [$this, 'admin_init'], 999);
        add_action('deactivated_plugin', [$this, 'deactivated_plugin']);

        if (!$this->is_mu_plugin()) {
            delete_option($this->container->get('plugin_undername').'__activated');

            register_activation_hook(WPSPAGHETTI_UFTYFACF_BASENAME, [$this->field_instance, 'activate']);
            register_deactivation_hook(WPSPAGHETTI_UFTYFACF_BASENAME, [$this->field_instance, 'deactivate']);
        }

        $this->do_action(__FUNCTION__.'_after');
    }

    /**
     * Load text domain for mu-plugins.
     *
     * Note: Since WordPress 4.6, text domains are loaded automatically.
     * This method is kept for future extensibility but doesn't load text domains.
     *
     * https://make.wordpress.org/core/2016/07/06/i18n-improvements-in-4-6/
     */
    public function muplugins_loaded(): void
    {
        // WordPress 4.6+ handles text domain loading automatically
        // This hook is available for future mu-plugin specific functionality
        if ($this->is_mu_plugin()) {
            // Allow extensions to hook into mu-plugin loading
            $this->do_action(__FUNCTION__.'_muplugin_loaded');
        }
    }

    /**
     * Load text domain for regular plugins.
     *
     * Note: Since WordPress 4.6, text domains are loaded automatically.
     * This method is kept for future extensibility but doesn't load text domains.
     *
     * https://make.wordpress.org/core/2016/07/06/i18n-improvements-in-4-6/
     */
    public function plugins_loaded(): void
    {
        // WordPress 4.6+ handles text domain loading automatically
        // This hook is available for future plugin specific functionality
        if (!$this->is_mu_plugin()) {
            // Allow extensions to hook into plugin loading
            $this->do_action(__FUNCTION__.'_plugin_loaded');
        }
    }

    /**
     * Initialize the plugin.
     */
    public function init(): void
    {
        $this->do_action(__FUNCTION__.'_before');

        if (!class_exists('acf') || !\function_exists('acf_register_field_type')) {
            $this->do_action(__FUNCTION__.'_acf_not_available');

            return;
        }

        if ($this->is_mu_plugin() && !get_option($this->container->get('plugin_undername').'__activated')) {
            $this->field_instance::activate();
            update_option($this->container->get('plugin_undername').'__activated', true);

            $this->do_action(__FUNCTION__.'_mu_plugin_activated');
        }

        acf_register_field_type($this->field_instance);

        $this->do_action(__FUNCTION__.'_after');
    }

    /**
     * Admin init actions.
     */
    public function admin_init(): void
    {
        $this->do_action(__FUNCTION__.'_before');

        if (!class_exists('acf')) {
            $this->deactivate();

            $this->do_action(__FUNCTION__.'_deactivated_missing_acf');

            add_action('admin_notices', static function (): void {
                echo '<div class="notice notice-error">';
                echo '<h3>'.esc_html__('YouTube Uploader', 'upload-field-to-youtube-for-acf').'</h3>';
                // translators: %s: plugin name
                echo '<p><strong>'.esc_html(\sprintf(__('%1$s plugin is required.', 'upload-field-to-youtube-for-acf'), __('Advanced Custom Fields', 'upload-field-to-youtube-for-acf'))).'</strong></p>';
                echo '</div>';
            });
        }

        $this->do_action(__FUNCTION__.'_after');
    }

    /**
     * Deactivate this plugin if ACF is deactivated.
     *
     * @param string $plugin the plugin being deactivated
     */
    public function deactivated_plugin(string $plugin): void
    {
        if (\in_array($plugin, ['advanced-custom-fields/acf.php', 'advanced-custom-fields-pro/acf.php'], true)) {
            $this->deactivate();
        }
    }

    /**
     * Deactivate this plugin.
     */
    private function deactivate(): void
    {
        deactivate_plugins(WPSPAGHETTI_UFTYFACF_BASENAME);
    }

    /**
     * Check if the plugin is running as a mu-plugin.
     *
     * @return bool true if the plugin is a mu-plugin, false otherwise
     */
    private function is_mu_plugin(): bool
    {
        return \defined('WPMU_PLUGIN_DIR') && str_contains(WPSPAGHETTI_UFTYFACF_PATH, WPMU_PLUGIN_DIR);
    }
}
