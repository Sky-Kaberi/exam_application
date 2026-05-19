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

## Admin module
- Admin login: `/admin/login.php`
- Dashboard with filter + pagination: `/admin/dashboard.php`
- Candidate details: `/admin/candidate_details.php?id={applicant_id}`
- Logout: `/admin/logout.php`

### Admin user setup
Run `sql/migrations/20260408_add_admin_users.sql` to create `admin_users` and seed a default admin.

> Important: replace the seeded password hash with your own `password_hash(...)` generated value before production use.

## Database backup via cron
Use `scripts/db_backup.php` to create compressed MySQL backups (`.sql.gz`) for scheduled jobs.

Example cron entry (daily at 2:30 AM):
```cron
30 2 * * * /usr/bin/php /path/to/exam_application/scripts/db_backup.php --backup-dir=/path/to/exam_application/backups --retention-days=14 >> /path/to/exam_application/logs/backup.log 2>&1
```

Options:
- `--backup-dir` (optional): destination directory for backup files. Defaults to `backups/` in the project root.
- `--retention-days` (optional): delete backup files older than this many days. Default is `7`. Use `0` to skip cleanup.

## Browser-triggered backup (Plesk friendly)
You can run the same script from a browser/URL if CLI access is limited.

1. Set a secure token in `includes/config.php`:
   - `BACKUP_WEB_TOKEN = 'replace_with_long_random_secret';`
2. Open URL:
   - `/scripts/db_backup.php?token=YOUR_TOKEN`
3. Optional URL params:
   - `backup_dir=/full/path/to/backups`
   - `retention_days=14`

Example:
```text
https://your-domain.com/scripts/db_backup.php?token=YOUR_TOKEN&retention_days=14
```

> Important: keep the token secret and prefer HTTPS only.
