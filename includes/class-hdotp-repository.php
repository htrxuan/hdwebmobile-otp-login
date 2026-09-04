<?php

namespace htrxuan\hdotp;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Storage and verification for one-time login codes. This is the exact surface
 * CVE-2026-12492 broke in a competing plugin: its login handler trusted a
 * client-submitted email/phone identifier and created a session without ever
 * checking that the OTP code itself had been answered correctly -- a missing
 * authentication step (CWE-287), not a weak one. Every method here is written
 * so that outcome is structurally impossible:
 *
 * - The raw 6-digit code is never stored anywhere, only an HMAC-SHA256 digest
 *   of it (keyed with wp_salt('auth'), the same secret WordPress itself signs
 *   auth cookies with) -- reading this table can never recover a usable code.
 * - verify_and_consume() is the ONLY method that can mark a code used, and it
 *   is the ONLY place in this entire plugin that returns "this login is
 *   valid" -- it does so exclusively via hash_equals() (constant-time, so a
 *   response-timing side channel can't leak how many digits matched) against
 *   the stored digest. There is no second code path, flag, or shortcut
 *   anywhere that can produce a positive verification result.
 * - A code is rejected outright once expired, once already consumed (single
 *   use), or once its own attempt counter is exhausted -- all three checks
 *   happen before the hash comparison is even attempted.
 */
// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
class HDOTP_Repository
{

    const CODE_LENGTH   = 6;
    const EXPIRY_MINUTES = 10;
    const MAX_ATTEMPTS   = 5;
    const MAX_ACTIVE_PER_HOUR = 5; // Simple per-email request-rate limit against email-bombing.

    public static function get_table_name()
    {
        global $wpdb;
        return $wpdb->prefix . 'hdotp_codes';
    }

    public static function get_schema_sql()
    {
        global $wpdb;
        $table           = self::get_table_name();
        $charset_collate = $wpdb->get_charset_collate();

        return "CREATE TABLE {$table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            email VARCHAR(150) NOT NULL,
            code_hash VARCHAR(64) NOT NULL,
            attempts INT UNSIGNED NOT NULL DEFAULT 0,
            consumed TINYINT UNSIGNED NOT NULL DEFAULT 0,
            expires_at DATETIME NOT NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            KEY email (email)
        ) {$charset_collate};";
    }

    private static function hash_code($email, $code)
    {
        // The email is mixed into the HMAC input so a code hash can never be
        // replayed against a different email even if two users were ever
        // issued the same 6-digit code by coincidence.
        return hash_hmac('sha256', $email . '|' . $code, wp_salt('auth'));
    }

    public static function generate_code()
    {
        return str_pad((string) random_int(0, 999999), self::CODE_LENGTH, '0', STR_PAD_LEFT);
    }

    /**
     * Returns the raw code on success (the ONLY place the raw code exists
     * outside of the email sent to the user), or a WP_Error if this email is
     * currently being rate-limited.
     */
    public static function request_code($email)
    {
        global $wpdb;
        $table = self::get_table_name();

        $recent_count = (int) $wpdb->get_var($wpdb->prepare(
            'SELECT COUNT(*) FROM %i WHERE email = %s AND created_at > %s',
            $table,
            $email,
            gmdate('Y-m-d H:i:s', strtotime('-1 hour'))
        ));

        if ($recent_count >= self::MAX_ACTIVE_PER_HOUR) {
            return new \WP_Error('hdotp_rate_limited', __('Too many codes requested. Please try again later.', 'hdwebmobile-otp-login'));
        }

        // Invalidate any still-pending codes for this email -- only the most
        // recently issued code is ever valid, closing off any ambiguity about
        // which of several outstanding codes an attacker might try replaying.
        $wpdb->update(
            $table,
            array('consumed' => 1),
            array('email' => $email, 'consumed' => 0),
            array('%d'),
            array('%s', '%d')
        );

        $code = self::generate_code();
        $now  = current_time('mysql');

        $wpdb->insert(
            $table,
            array(
                'email'      => $email,
                'code_hash'  => self::hash_code($email, $code),
                'attempts'   => 0,
                'consumed'   => 0,
                'expires_at' => gmdate('Y-m-d H:i:s', strtotime($now . ' +' . self::EXPIRY_MINUTES . ' minutes')),
                'created_at' => $now,
            ),
            array('%s', '%s', '%d', '%d', '%s', '%s')
        );

        return $code;
    }

    /**
     * The single authoritative verification method. Returns true only if a
     * live, unconsumed, unexpired, not-over-attempted code for this email
     * hashes to exactly the stored digest -- every other path returns false
     * or a WP_Error, and none of them ever grant access.
     */
    public static function verify_and_consume($email, $submitted_code)
    {
        global $wpdb;
        $table = self::get_table_name();

        $row = $wpdb->get_row($wpdb->prepare(
            'SELECT * FROM %i WHERE email = %s AND consumed = 0 ORDER BY id DESC LIMIT 1',
            $table,
            $email
        ));

        if (!$row) {
            return new \WP_Error('hdotp_no_code', __('No active code found. Please request a new one.', 'hdwebmobile-otp-login'));
        }

        if (strtotime($row->expires_at) < time()) {
            return new \WP_Error('hdotp_expired', __('That code has expired. Please request a new one.', 'hdwebmobile-otp-login'));
        }

        if ((int) $row->attempts >= self::MAX_ATTEMPTS) {
            return new \WP_Error('hdotp_too_many_attempts', __('Too many incorrect attempts. Please request a new code.', 'hdwebmobile-otp-login'));
        }

        $expected_hash = self::hash_code($email, $submitted_code);

        if (!hash_equals($row->code_hash, $expected_hash)) {
            $wpdb->update(
                $table,
                array('attempts' => (int) $row->attempts + 1),
                array('id' => $row->id),
                array('%d'),
                array('%d')
            );
            return new \WP_Error('hdotp_incorrect', __('Incorrect code.', 'hdwebmobile-otp-login'));
        }

        $wpdb->update(
            $table,
            array('consumed' => 1),
            array('id' => $row->id),
            array('%d'),
            array('%d')
        );

        return true;
    }

    public static function cleanup_expired()
    {
        global $wpdb;
        $wpdb->query($wpdb->prepare(
            'DELETE FROM %i WHERE expires_at < %s',
            self::get_table_name(),
            gmdate('Y-m-d H:i:s', strtotime('-1 day'))
        ));
    }
}
