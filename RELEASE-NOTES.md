# Personal OS Release Notes

## Summary

Personal OS 1.0 is a complete local productivity dashboard for XAMPP. It provides task and note management, calendar planning, recurring work, habits, reminders, team workspaces, templates, analytics, a private vault, and operational backup tools.

## Requirements

- XAMPP with Apache and MariaDB
- PHP 8.1 or newer
- `C:/xampp/.personal-os.env` database configuration
- HTTPS for production deployments

## Main entry points

- Dashboard: `http://localhost/personal-os/`
- Analytics: `http://localhost/personal-os/analytics.php`
- Calendar: `http://localhost/personal-os/calendar.php`
- Habits: `http://localhost/personal-os/habits.php`
- Workspaces: `http://localhost/personal-os/workspaces.php`
- Templates: `http://localhost/personal-os/templates.php`
- Vault: `http://localhost/personal-os/vault.php`
- Maintenance: `http://localhost/personal-os/maintenance.php`

## Release verification

- Database tests: 4/4
- HTTP QA: 9/9
- PHP syntax: passed
- JavaScript syntax: passed
- Protected SQL/config routes: denied
- Anonymous API calls: denied

## Operational notes

Run recurring tasks and automation scripts on a schedule:

```powershell
php C:\xampp\htdocs\personal-os\cron\generate_recurring_tasks.php
```

Create backups through the administrator backup page and protect the vault key in `C:/xampp/.personal-os.env`.
