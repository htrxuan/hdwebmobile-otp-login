<?php

/**
 * Plugin Name: HDWebmobile OTP Login
 * Plugin URI: https://hdwebmobile.com/plugins/hdwebmobile-otp-login/
 * Description: Passwordless email one-time-code login and signup for WooCommerce, offered alongside the normal password login. A session is only ever created after a securely-hashed, single-use, rate-limited code is verified with a constant-time comparison -- never by trusting a submitted email or phone number alone.
 * Version: 1.0.0
 * Author: htrxuan - Han Tran
 * Author URI: https://hdwebmobile.com/
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: hdwebmobile-otp-login
 * Domain Path: /languages
 * Requires Plugins: woocommerce
 * Requires PHP: 7.4
 * Requires at least: 6.9
 */

namespace htrxuan\hdotp;

if (!defined('ABSPATH')) {
    exit;
}

define('HDOTP_VERSION', '1.0.0');
define('HDOTP_DB_VERSION', '1.0.0');
define('HDOTP_PLUGIN_FILE', __FILE__);
define('HDOTP_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('HDOTP_PLUGIN_URL', plugin_dir_url(__FILE__));

require_once HDOTP_PLUGIN_DIR . 'includes/class-hdotp-activator.php';

register_activation_hook(__FILE__, array(HDOTP_Activator::class, 'activate'));

add_action('before_woocommerce_init', function () {
    if (class_exists('\Automattic\WooCommerce\Utilities\FeaturesUtil')) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', HDOTP_PLUGIN_FILE, true);
    }
});

add_action('plugins_loaded', function () {
    require_once HDOTP_PLUGIN_DIR . 'includes/class-hdotp-core.php';
    HDOTP_Core::get_instance();
});

add_filter('plugin_action_links_' . plugin_basename(__FILE__), function ($links) {
    $donate_link = '<a href="https://paypal.me/htrxuan/20" target="_blank" rel="noopener noreferrer">' . esc_html__('Donate', 'hdwebmobile-otp-login') . '</a>';
    array_unshift($links, $donate_link);
    return $links;
});
