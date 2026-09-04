# HDWebmobile OTP Login

Passwordless email one-time-code login for WooCommerce. A session is only ever created after the code is actually verified.

- **WordPress.org:** https://wordpress.org/plugins/hdwebmobile-otp-login/
- **Requires:** WordPress 6.9+, WooCommerce, PHP 7.4+
- **License:** GPLv2 or later

## Description

HDWebmobile OTP Login adds a "Log in with a one-time code" option next to the normal password login on your My Account page. A customer enters their email, receives a 6-digit code, and enters it to log in -- creating an account automatically if they don't have one yet. It's an addition to the existing password login, not a replacement.

## Why this plugin exists

A competing "OTP Login for WooCommerce" plugin had a critical authentication bypass (CVE-2026-12492): its login handler never actually confirmed the OTP code had been verified before creating a session -- it trusted a client-submitted email or phone number alone, meaning anyone could log in as any user, including administrators, just by submitting their target's email address. This plugin closes that exact failure mode by construction:

* A session is created from exactly one place in this plugin's code, reached only after `HDOTP_Repository::verify_and_consume()` has already returned true -- there is no second path, flag, or shortcut that can produce a login.
* The verification check compares a securely-hashed digest of the submitted code against the stored digest using `hash_equals()` (constant-time, so response timing can't leak how many digits were correct) -- never a plaintext comparison, and never trusting the submitted email by itself.
* The raw code is never stored anywhere; only an HMAC-SHA256 digest (keyed with WordPress's own `wp_salt('auth')`) is kept, so reading the database can never recover a working code.
* Every code is single-use (consumed immediately on a correct match), expires after 10 minutes, and locks out after 5 incorrect attempts -- closing off brute-forcing a 6-digit code long before it becomes practical.
* Requesting a new code invalidates any previous outstanding code for that email, so there is never more than one valid code at a time.

## Features

* "Log in with a one-time code" option alongside the normal password login on My Account
* Automatically creates an account for a new email after the code is verified
* Single-use, time-limited, attempt-limited codes -- no external SMS/OTP service required, works entirely over email
* Simple enable/disable toggle under WooCommerce > HDWebmobile > OTP Login

## Development

Standard WordPress plugin structure:

```
hdwebmobile-otp-login.php    Bootstrap
includes/class-hdotp-activator.php
includes/class-hdotp-admin.php
includes/class-hdotp-core.php
includes/class-hdotp-frontend.php
includes/class-hdotp-hub.php
includes/class-hdotp-repository.php
```

Part of the [HDWebmobile](https://hdwebmobile.com/plugins/) suite of focused, single-purpose WooCommerce plugins.

## License

GPLv2 or later. See [LICENSE](LICENSE).

