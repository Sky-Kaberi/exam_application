# Exam Application

Step 1 registration scaffold built with PHP, MySQL, JavaScript, and AJAX.

## Included in this step
- MySQL schema for applicants and OTP verification.
- Registration UI for basic applicant data.
- OTP send/verify endpoints for email and mobile.
- Application ID generation after both OTP channels are verified.

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
5. Open `http://127.0.0.1:8000`.

## Demo OTP behavior
This scaffold writes OTP values to `logs/otp.log` for development/testing. Replace that section with actual SMS and email gateway integrations in production.

## CAPTCHA behavior
This scaffold uses a built-in math CAPTCHA (no third-party dependency). The challenge is session-based and validated during registration submit.
