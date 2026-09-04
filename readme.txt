=== HDWebmobile OTP Login ===
Contributors: htrxuan
Donate link: https://paypal.me/htrxuan/20
Tags: woocommerce, otp, passwordless login, one time password, 2fa
Requires at least: 6.9
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.0.0
Requires Plugins: woocommerce
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Passwordless email one-time-code login for WooCommerce. A session is only ever created after the code is actually verified.

== Description ==

HDWebmobile OTP Login adds a "Log in with a one-time code" option next to the normal password login on your My Account page. A customer enters their email, receives a 6-digit code, and enters it to log in -- creating an account automatically if they don't have one yet. It's an addition to the existing password login, not a replacement.

= Why this plugin exists =
A competing "OTP Login for WooCommerce" plugin had a critical authentication bypass (CVE-2026-12492): its login handler never actually confirmed the OTP code had been verified before creating a session -- it trusted a client-submitted email or phone number alone, meaning anyone could log in as any user, including administrators, just by submitting their target's email address. This plugin closes that exact failure mode by construction:

* A session is created from exactly one place in this plugin's code, reached only after `HDOTP_Repository::verify_and_consume()` has already returned true -- there is no second path, flag, or shortcut that can produce a login.
* The verification check compares a securely-hashed digest of the submitted code against the stored digest using `hash_equals()` (constant-time, so response timing can't leak how many digits were correct) -- never a plaintext comparison, and never trusting the submitted email by itself.
* The raw code is never stored anywhere; only an HMAC-SHA256 digest (keyed with WordPress's own `wp_salt('auth')`) is kept, so reading the database can never recover a working code.
* Every code is single-use (consumed immediately on a correct match), expires after 10 minutes, and locks out after 5 incorrect attempts -- closing off brute-forcing a 6-digit code long before it becomes practical.
* Requesting a new code invalidates any previous outstanding code for that email, so there is never more than one valid code at a time.

= Key Features =
* "Log in with a one-time code" option alongside the normal password login on My Account
* Automatically creates an account for a new email after the code is verified
* Single-use, time-limited, attempt-limited codes -- no external SMS/OTP service required, works entirely over email
* Simple enable/disable toggle under WooCommerce > HDWebmobile > OTP Login

= Limitations (please read before installing) =
* Email-based codes only in this version -- no SMS/phone verification
* Does not replace or remove the existing password login; it's offered as an alternative

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/hdwebmobile-otp-login` directory, or install the plugin through the WordPress plugins screen directly.
2. Activate the plugin through the 'Plugins' screen in WordPress. WooCommerce must already be installed and active.
3. That's it -- the "Log in with a one-time code" option appears automatically on your My Account login page.

== How to Use ==

= 1. Customer requests a code =
On the My Account login page, a customer clicks "Log in with a one-time code", enters their email, and clicks "Send code".

= 2. Customer receives and enters the code =
A 6-digit code arrives by email. The customer enters it and clicks "Verify and log in" -- they're logged in immediately (or a new account is created for them if they didn't have one).

= 3. Turn it off if you don't need it =
Under WooCommerce > HDWebmobile > OTP Login, uncheck "Enable OTP login" to hide the option and disable the feature entirely.

== Screenshots ==

1. The "Log in with a one-time code" link on the My Account login page.
2. The email-entry and code-entry steps.
3. The OTP Login settings under WooCommerce > HDWebmobile.

== Changelog ==

= 1.0.0 =
* Initial release: passwordless email OTP login and signup, hashed single-use time-limited attempt-limited codes, enable/disable setting.
