# Personal OS Deployment Handoff

## Completed

- Dashboard and all major modules are installed locally under XAMPP.
- Administrator account is provisioned.
- Database schema and sample data are imported.
- Dedicated database configuration is externalized.
- CSRF, session timeout, protected files, and audit controls are enabled.
- PWA assets and recurring-task automation are present.
- Database and endpoint test suites pass.

## Deployment sequence

1. Install XAMPP Apache and MariaDB.
2. Import `database/schema.sql` or the post-release SQL archive.
3. Copy `.env.example` values to `C:/xampp/.personal-os.env`.
4. Create a strong `VAULT_KEY`.
5. Create and grant a dedicated MariaDB account.
6. Configure TLS certificates.
7. Copy Apache configuration and enable overrides.
8. Run `php tests/run.php`.
9. Run `php tests/qa.php`.
10. Provision or verify the administrator account.

## Monitoring

- Run the recurring-task script hourly or daily.
- Run QA scripts after every deployment.
- Export database backups regularly.
- Archive application and environment configuration separately.
- Review `audit_events` and Apache/PHP logs.

## Known operating requirements

- The vault key must remain stable or existing vault entries cannot be decrypted.
- HTTPS and trusted certificates are required for production.
- Invitation delivery requires a real mail transport for external use.
- Automation rules are represented in the database and require the scheduled executor.
