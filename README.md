# Personal OS

Personal OS is a local-first PHP dashboard for tasks, notes, habits, reminders, calendar planning, and team administration.

## Requirements

- XAMPP with Apache and MariaDB/MySQL
- PHP 8.1 or newer
- Apache `mod_headers`, `mod_rewrite`, and `mod_ssl`

## Initial setup

1. Start Apache and MySQL.
2. Import `database/schema.sql` into MariaDB/MySQL.
3. Run `composer install --no-dev` in the project directory.
4. Copy `.env.example` to `C:/xampp/.personal-os.env`.
5. Create a least-privilege database user or use the generated `personal_os_app` account.
6. Open `https://127.0.0.1/personal-os/setup.php`.
7. Provision the administrator name, email, and password.
8. Open `https://127.0.0.1/personal-os/login.php`.

The setup seed intentionally contains no usable password. Do not reuse the placeholder account values in production.

## Database configuration

The application reads `C:/xampp/.personal-os.env` and requires:

- `DB_HOST`
- `DB_PORT`
- `DB_NAME`
- `DB_USER`
- `DB_PASSWORD`
- `VAULT_KEY`

An application database account should only have `SELECT`, `INSERT`, `UPDATE`, and `DELETE` on `personal_os`.

The optional `WEATHER_LATITUDE` and `WEATHER_LONGITUDE` values set the dashboard weather location in decimal degrees. They default to London when omitted.

Invitation email uses authenticated SMTP. Configure `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_ENCRYPTION` (`tls` or `ssl`), and `MAIL_FROM_ADDRESS` in the external environment file. Do not put SMTP credentials in the project or share them in chat. Invitations continue to show a one-time link if email is not configured or delivery fails.

## Core routes

- `/personal-os/` - dashboard
- `/personal-os/calendar.php` - monthly planning
- `/personal-os/habits.php` - recurring habits
- `/personal-os/team.php` - team administration
- `/personal-os/invitations.php` - invitation management
- `/personal-os/settings.php` - profile settings and exports
- `/personal-os/admin/backup.php` - backup download and restore
- `/personal-os/setup.php` - initial administrator provisioning

## Command palette

Open the dashboard palette with `Ctrl+K` (`Cmd+K` on macOS) or the search button. Search tasks, notes, goals, habits, and journal entries across your account, jump to a section or feature, or create a task or note without leaving the keyboard. Search requests use an authenticated, CSRF-protected endpoint; journal results reveal only the date and mood, never journal text. Use the arrow keys to select, `Enter` to open, and `Esc` to close.

## Security

- State-changing APIs require a session CSRF token.
- Sessions use HttpOnly and SameSite=Lax cookies.
- Secure cookies are enabled when HTTPS is active.
- Protected paths and SQL files are denied by Apache.
- CSV exports neutralize spreadsheet formula characters.
- PWA caching excludes authenticated pages and APIs.
- This local app redirects HTTP to HTTPS and uses a certificate trusted for the current Windows user.
- The local certificate is not suitable for production; deploy with a trusted certificate and production `APP_URL`.

## QA commands

Run database tests:

```powers
php C:\xampp\htdocs\personal-os\tests\run.php
```

Run endpoint QA:

```powers
php C:\xampp\htdocs\personal-os\tests\qa.php
```

Lint PHP:

```powers
Get-ChildItem C:\xampp\htdocs\personal-os -Recurse -Filter *.php | ForEach-Object { php -l $_.FullName }
```

## Backups

Use the backup page while signed in as an administrator. JSON backups contain tasks and notes. Restoring a backup replaces the current user's tasks and notes inside a database transaction.
