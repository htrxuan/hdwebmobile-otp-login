<?php

namespace htrxuan\hdotp;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * The actual session-creation call (login()) is reached from exactly one
 * place: after HDOTP_Repository::verify_and_consume() has already returned
 * true. There is no branch anywhere in this class that logs a visitor in
 * based on the email field alone.
 */
final class HDOTP_Frontend
{

    const NONCE_ACTION = 'hdotp_login';

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
        // Deliberately woocommerce_after_customer_login_form, not woocommerce_login_form_end
        // -- the latter fires INSIDE the classic login <form>, and browsers silently drop a
        // nested <form> tag (HTML doesn't allow one), which splices this form's fields
        // straight into the surrounding login form instead of keeping them in their own.
        add_action('woocommerce_after_customer_login_form', array($this, 'render_toggle_and_form'));
        add_action('template_redirect', array($this, 'handle_submission'));
        add_action('wp_enqueue_scripts', array($this, 'maybe_enqueue_assets'));
    }

    public function maybe_enqueue_assets()
    {
        if (is_account_page()) {
            wp_enqueue_style('hdotp-frontend', HDOTP_PLUGIN_URL . 'assets/css/hdotp-frontend.css', array(), HDOTP_VERSION);
        }
    }

    private function is_enabled()
    {
        $options = get_option('hdotp_options', array('enabled' => 1));
        return !empty($options['enabled']);
    }

    public function render_toggle_and_form()
    {
        if (!$this->is_enabled() || is_user_logged_in()) {
            return;
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only step selector for which half of the form to display; every state-changing action below is its own nonce-verified POST handler.
        $step  = isset($_GET['hdotp_step']) ? sanitize_key(wp_unslash($_GET['hdotp_step'])) : '';
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- same read-only display purpose as $step above, just echoed back into the next step's form field, never used to grant access.
        $email = isset($_GET['hdotp_email']) ? sanitize_email(wp_unslash($_GET['hdotp_email'])) : '';

        echo '<div class="hdotp-login-block">';

        if ('enter-code' === $step && is_email($email)) {
            $this->render_code_form($email);
        } else {
            echo '<p class="hdotp-toggle"><a href="' . esc_url(add_query_arg('hdotp_step', 'request', wc_get_page_permalink('myaccount'))) . '#hdotp">' . esc_html__('Or log in with a one-time code', 'hdwebmobile-otp-login') . '</a></p>';
            if ('request' === $step) {
                $this->render_request_form();
            }
        }

        echo '</div>';
    }

    private function render_request_form()
    {
        echo '<form method="post" class="hdotp-request-form" id="hdotp">';
        wp_nonce_field(self::NONCE_ACTION, 'hdotp_nonce');
        echo '<input type="hidden" name="hdotp_action" value="request" />';
        printf(
            '<p><label for="hdotp_email">%s</label><input type="email" id="hdotp_email" name="hdotp_email" required /></p>',
            esc_html__('Your email', 'hdwebmobile-otp-login')
        );
        echo '<button type="submit" class="woocommerce-button button wp-element-button">' . esc_html__('Send code', 'hdwebmobile-otp-login') . '</button>';
        echo '</form>';
    }

    private function render_code_form($email)
    {
        echo '<p class="hdotp-sent-notice">' . esc_html__('We emailed a login code to', 'hdwebmobile-otp-login') . ' ' . esc_html($email) . '</p>';
        echo '<form method="post" class="hdotp-verify-form" id="hdotp">';
        wp_nonce_field(self::NONCE_ACTION, 'hdotp_nonce');
        echo '<input type="hidden" name="hdotp_action" value="verify" />';
        printf('<input type="hidden" name="hdotp_email" value="%s" />', esc_attr($email));
        printf(
            '<p><label for="hdotp_code">%s</label><input type="text" id="hdotp_code" name="hdotp_code" inputmode="numeric" pattern="[0-9]*" maxlength="6" autocomplete="one-time-code" required /></p>',
            esc_html__('6-digit code', 'hdwebmobile-otp-login')
        );
        echo '<button type="submit" class="woocommerce-button button wp-element-button">' . esc_html__('Verify and log in', 'hdwebmobile-otp-login') . '</button>';
        echo '</form>';
    }

    public function handle_submission()
    {
        if (!is_account_page() || !isset($_POST['hdotp_action']) || is_user_logged_in()) {
            return;
        }

        if (!isset($_POST['hdotp_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['hdotp_nonce'])), self::NONCE_ACTION)) {
            return;
        }

        $action = sanitize_text_field(wp_unslash($_POST['hdotp_action']));
        $email  = isset($_POST['hdotp_email']) ? sanitize_email(wp_unslash($_POST['hdotp_email'])) : '';

        if (!is_email($email)) {
            return;
        }

        if ('request' === $action) {
            $this->do_request($email);
        } elseif ('verify' === $action) {
            $code = isset($_POST['hdotp_code']) ? preg_replace('/[^0-9]/', '', wp_unslash($_POST['hdotp_code'])) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- the preg_replace() strips everything except digits, which is itself the sanitization; the result is also never trusted as a correct code, only compared via HDOTP_Repository's hash_equals() check.
            $this->do_verify($email, $code);
        }
    }

    private function do_request($email)
    {
        $result = HDOTP_Repository::request_code($email);

        $account_url = wc_get_page_permalink('myaccount');

        if (is_wp_error($result)) {
            wc_add_notice($result->get_error_message(), 'error');
            wp_safe_redirect(add_query_arg('hdotp_step', 'request', $account_url) . '#hdotp');
            exit;
        }

        $this->email_code($email, $result);

        wp_safe_redirect(add_query_arg(array('hdotp_step' => 'enter-code', 'hdotp_email' => rawurlencode($email)), $account_url) . '#hdotp');
        exit;
    }

    private function do_verify($email, $code)
    {
        $account_url = wc_get_page_permalink('myaccount');
        $result = HDOTP_Repository::verify_and_consume($email, $code);

        if (is_wp_error($result)) {
            wc_add_notice($result->get_error_message(), 'error');
            wp_safe_redirect(add_query_arg(array('hdotp_step' => 'enter-code', 'hdotp_email' => rawurlencode($email)), $account_url) . '#hdotp');
            exit;
        }

        // Only reachable once verify_and_consume() has returned true above.
        $user = get_user_by('email', $email);
        if (!$user) {
            $user_id = wc_create_new_customer($email, '', wp_generate_password(20, true, true));
            if (is_wp_error($user_id)) {
                wc_add_notice(__('Could not create an account for that email.', 'hdwebmobile-otp-login'), 'error');
                wp_safe_redirect(add_query_arg('hdotp_step', 'request', $account_url) . '#hdotp');
                exit;
            }
        } else {
            $user_id = $user->ID;
        }

        wc_set_customer_auth_cookie($user_id);
        wp_safe_redirect($account_url);
        exit;
    }

    private function email_code($email, $code)
    {
        $subject = __('Your login code', 'hdwebmobile-otp-login');
        $body    = sprintf(
            /* translators: 1: the 6-digit one-time code, 2: number of minutes until it expires */
            __("Your one-time login code is: %1\$s\n\nThis code expires in %2\$d minutes and can only be used once.", 'hdwebmobile-otp-login'),
            $code,
            HDOTP_Repository::EXPIRY_MINUTES
        );
        wp_mail($email, $subject, $body);
    }
}
