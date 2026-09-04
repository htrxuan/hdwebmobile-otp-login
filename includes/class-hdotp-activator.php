<?php

namespace htrxuan\hdotp;

if (!defined('ABSPATH')) {
    exit;
}

class HDOTP_Activator
{

    public static function activate()
    {
        if (!self::is_woocommerce_active()) {
            deactivate_plugins(plugin_basename(HDOTP_PLUGIN_FILE));
            set_transient('hdotp_wc_missing_notice', true, 30);
            return;
        }

        self::maybe_upgrade_db();

        if (false === get_option('hdotp_options')) {
            add_option('hdotp_options', array(
                'enabled' => 1,
            ));
        }
    }

    public static function is_woocommerce_active()
    {
        if (!function_exists('is_plugin_active')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        return is_plugin_active('woocommerce/woocommerce.php') || class_exists('WooCommerce');
    }

    public static function maybe_upgrade_db()
    {
        if (get_option('hdotp_db_version') === HDOTP_DB_VERSION) {
            return;
        }

        require_once HDOTP_PLUGIN_DIR . 'includes/class-hdotp-repository.php';
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        dbDelta(HDOTP_Repository::get_schema_sql());

        update_option('hdotp_db_version', HDOTP_DB_VERSION);
    }
}
