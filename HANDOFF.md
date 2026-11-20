# Personal OS Handoff

Personal OS is complete for local XAMPP use and includes the following operational areas:

- Authentication and administrator provisioning
- Tasks, notes, and system activity
- Search and task filtering
- Calendar planning
- Recurring habits
- Browser reminders and PWA support
- Team roles and invitations
- JSON/CSV export
- Administrator backup and restore
- Security headers, CSRF protection, protected database files, and least-privilege database configuration

## Access

- Dashboard: `http://localhost/personal-os/`
- Setup: `http://localhost/personal-os/setup.php`
- Login: `http://localhost/personal-os/login.php`
- Habits: `http://localhost/personal-os/habits.php`
- Calendar: `http://localhost/personal-os/calendar.php`
- Team: `http://localhost/personal-os/team.php`
- Settings: `http://localhost/personal-os/settings.php`
- Backup: `http://localhost/personal-os/admin/backup.php`

## Administrator

The administrator account was provisioned during the final setup pass. Passwords are never stored in plaintext or represented by a public demo credential.

## Verification

- Database tests: 4/4 passed
- HTTP and endpoint QA: 8/8 passed
- PHP syntax: all application and test PHP files passed
- JavaScript syntax: passed
- Setup, dashboard, calendar, habits, team, and backup pages were visually inspected
- Protected schema and configuration paths returned HTTP 403

## Operations

See `README.md`, `RUNBOOK.md`, and `DEPLOYMENT.md`.
