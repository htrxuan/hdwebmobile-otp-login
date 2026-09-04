<?php

namespace htrxuan\hdotp;

if (!defined('ABSPATH')) {
    exit;
}

final class HDOTP_Core
{

    private static $instance = null;

    public static function get_instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct()
    {
        $this->includes();
        $this->init_hooks();
    }

    private function __clone()
    {
    }

    private function includes()
    {
        require_once HDOTP_PLUGIN_DIR . 'includes/class-hdotp-repository.php';
        require_once HDOTP_PLUGIN_DIR . 'includes/class-hdotp-frontend.php';
        require_once HDOTP_PLUGIN_DIR . 'includes/class-hdotp-admin.php';
    }

    private function init_hooks()
    {
        add_action('admin_notices', array($this, 'render_missing_woocommerce_notice'));

        if (!class_exists('WooCommerce')) {
            return;
        }

        HDOTP_Frontend::get_instance();

        if (is_admin()) {
            HDOTP_Admin::get_instance();
        }
    }

    public function render_missing_woocommerce_notice()
    {
        $screen = get_current_screen();
        if (!$screen || 'plugins' !== $screen->id) {
            return;
        }

        if (!get_transient('hdotp_wc_missing_notice')) {
            return;
        }
        delete_transient('hdotp_wc_missing_notice');
        ?>
        <div class="notice notice-error is-dismissible">
            <p>
                <?php esc_html_e('HDWebmobile OTP Login requires WooCommerce to be installed and active. The plugin has been deactivated.', 'hdwebmobile-otp-login'); ?>
            </p>
        </div>
        <?php
    }
}
