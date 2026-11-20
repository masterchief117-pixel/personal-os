# Production Deployment

## Apache

1. Map `personal-os.local` in the hosts file or DNS.
2. Install a trusted TLS certificate.
3. Load `apache-personal-os.conf` in Apache.
4. Confirm `AllowOverride All`.
5. Enable `mod_headers`, `mod_rewrite`, and `mod_ssl`.
6. Configure PHP `session.cookie_secure=1` behind HTTPS.

## Database

1. Create `personal_os`.
2. Create a dedicated least-privilege application account.
3. Import `database/schema.sql`.
4. Store credentials outside the web root.
5. Set `VAULT_KEY` to a random secret of at least 32 characters outside the web root.

## Application

1. Copy the application outside public development directories when deploying.
2. Run `composer install --no-dev`.
3. Copy `.env.example` content to `C:/xampp/.personal-os.env`.
4. Replace placeholder values, including production `APP_URL`.
5. Configure authenticated SMTP values outside the web root if invitation emails are required.
6. Run `php tests/run.php`, `php tests/regression.php`, and `php tests/qa.php`.
7. Provision the administrator through `/personal-os/setup.php`.

## HTTPS

For production, add the HSTS header only after HTTPS is stable. Test certificate renewal and redirects before enabling HSTS across subdomains.

The generated localhost certificate and its private CA are for local development only. Replace them with a trusted certificate and keep production mail credentials outside the web root.
