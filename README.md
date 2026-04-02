# Exam Application

Two-step application workflow built with PHP, MySQL, JavaScript, and AJAX.

## Included features
- Step 1 registration with OTP verification for mobile and email.
- Deterministic application ID generation format: `2510` + `LPAD(id, 7, '0')`.
- Post-registration redirect to login with highlighted newly generated application number.
- Login using application number and password from Step 1.
- Session-based redirect to Step 2 after successful login.
- Step 2 multi-tab form (Basic Info, Corresponding Address, Selection of Courses, Image Upload).
- Step 2 Basic Info save/load with conditional validation and persistence.

## Run locally
1. Import `sql/schema.sql` into MySQL.
2. Update database credentials in `includes/config.php`.
3. Optional: adjust local math CAPTCHA range in `includes/config.php`:
   - `CAPTCHA_MIN_VALUE`
   - `CAPTCHA_MAX_VALUE`
4. Start PHP server:
   ```bash
   php -S 127.0.0.1:8000 -t public
   ```
5. Open `http://127.0.0.1:8000` for Step 1 registration.

## Demo OTP behavior
This scaffold writes OTP values to `logs/otp.log` for development/testing. Replace that section with actual SMS and email gateway integrations in production.

## CAPTCHA behavior
This scaffold uses a built-in math CAPTCHA (no third-party dependency). The challenge is session-based and validated during registration submit.
