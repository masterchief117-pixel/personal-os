# Personal OS Operations Runbook

## Start

1. Start MySQL/MariaDB.
2. Start Apache.
3. Verify `C:/xampp/.personal-os.env`.
4. Open `http://localhost/personal-os/setup.php` on first run or `http://localhost/personal-os/login.php` afterward.

## Stop

Stop Apache and MySQL through the XAMPP Control Panel. Export a backup before destructive database work.

## Database reset

1. Export a backup.
2. Back up `C:/xampp/mysql/data`.
3. Import `database/schema.sql`.
4. Run `php tests/run.php`.

Importing the schema recreates seeded application tables.

## Restore

1. Sign in as an administrator.
2. Open `/personal-os/admin/backup.php`.
3. Upload a valid Personal OS JSON export.
4. Confirm the restore prompt.

The restore operation replaces the active user's tasks and notes.

## Troubleshooting

### Database connection failed

- Confirm MySQL is running.
- Verify `.personal-os.env` values.
- Confirm the application database account has table grants.

### Login fails

- Confirm first-run setup is complete.
- Reset the password through `/personal-os/setup.php` only on local development or by a controlled administrator procedure.
- Check PHP and Apache error logs.

### Protected files return unexpected access

- Confirm `AllowOverride All` is configured.
- Confirm `mod_headers` and `mod_rewrite` are loaded.
- Restart Apache after Apache configuration changes.

### PWA data appears stale

- Unregister the service worker in browser developer tools.
- Clear site data.
- Reload the application.
- If Node.js is installed, run `node --test tests/service-worker.test.js` to verify private routes are excluded from offline caching.

## Monitoring

Use `tests/run.php` for database health and `tests/qa.php` for route and authentication smoke checks. Review Apache and PHP logs for recurring errors.
