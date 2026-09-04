<?php

namespace htrxuan\hdotp;

if (!defined('ABSPATH')) {
    exit;
}

final class HDOTP_Admin
{

    const OPTION_GROUP = 'hdotp_option_group';

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
        require_once HDOTP_PLUGIN_DIR . 'includes/class-hdotp-hub.php';
        add_filter('hdwebmobile_hub_tabs', array($this, 'register_hub_tabs'));
        add_action('admin_init', array($this, 'register_settings'));
    }

    public function register_hub_tabs($tabs)
    {
        $tabs['otp-login'] = array(
            'label'  => __('OTP Login', 'hdwebmobile-otp-login'),
            'order'  => 15,
            'render' => array($this, 'render_settings_page'),
        );
        return $tabs;
    }

    public function register_settings()
    {
        register_setting(self::OPTION_GROUP, 'hdotp_options', array($this, 'sanitize'));
    }

    public function sanitize($input)
    {
        return array('enabled' => !empty($input['enabled']) ? 1 : 0);
    }

    public function render_settings_page()
    {
        $options = get_option('hdotp_options', array('enabled' => 1));
        ?>
        <p><?php esc_html_e('Lets customers log in (or create an account) with a one-time code emailed to them, as an alternative to a password. A session is only ever created after the code is verified with a secure, constant-time, single-use check.', 'hdwebmobile-otp-login'); ?></p>
        <form method="post" action="options.php">
            <?php settings_fields(self::OPTION_GROUP); ?>
            <table class="form-table">
                <tr>
                    <th scope="row"><?php esc_html_e('Enable OTP login', 'hdwebmobile-otp-login'); ?></th>
                    <td><label><input type="checkbox" name="hdotp_options[enabled]" value="1" <?php checked(!empty($options['enabled'])); ?> /> <?php esc_html_e('Show "Log in with a one-time code" on the login form', 'hdwebmobile-otp-login'); ?></label></td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e('Code expiry', 'hdwebmobile-otp-login'); ?></th>
                    <td><?php echo esc_html(HDOTP_Repository::EXPIRY_MINUTES); ?> <?php esc_html_e('minutes (fixed)', 'hdwebmobile-otp-login'); ?></td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e('Max attempts per code', 'hdwebmobile-otp-login'); ?></th>
                    <td><?php echo esc_html(HDOTP_Repository::MAX_ATTEMPTS); ?> <?php esc_html_e('(fixed)', 'hdwebmobile-otp-login'); ?></td>
                </tr>
            </table>
            <?php submit_button(); ?>
        </form>
        <?php
    }
}
