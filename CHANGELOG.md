# Personal OS Changelog

## Update 1.1

- Added event-triggered automations and owner-safe workspace collaboration controls.
- Hardened login throttling, invitations, reminders, backups, imports, goals, and focus sessions.
- Added modal focus restoration and optimistic task rollback in the dashboard.
- Added stronger JSON, text-length, and CSV formula validation.
- Registered recurring-task and backup maintenance schedules.
- Removed inline scripts and handlers for strict CSP compatibility.
- Added login submit feedback and confirm-dialog handling through external JavaScript.
- Refined the dashboard life-widget and responsive grid placement.
- Added a refresh action for system statistics.
- Added authenticated Open-Meteo weather and local server-vitals APIs with dashboard refresh intervals.
- Added configurable weather coordinates through `C:/xampp/.personal-os.env`, defaulting to London.
- Added localhost HTTPS with a trusted local certificate and scoped HTTP redirect.
- Added provider-neutral, authenticated SMTP delivery for invitations; incomplete settings retain the one-time link and report the delivery state.
- Reworked offline support to cache only static assets and show a generic offline page without private dashboard data.
- Upgraded dashboard search into an accessible command palette with keyboard navigation, quick-create actions, and task/note jumps.
- Extended the command palette with debounced, authenticated, user-scoped search across tasks, notes, goals, habits, and journal entries; private journal text is never included in search results.
- Updated the service worker cache generation.
- Added stricter goal and journal date handling.
- Added journal deletion and focus-session reload persistence.
- Added data import, focus sessions, backup schedules, and scheduled maintenance scripts.

## Release 1.0

### Goals and journal update

- Added long-term goals with target values, units, progress bars, and target dates.
- Added daily journal entries with mood, energy, gratitude, wins, and reflection.
- Added current journal streak calculation.

### Core platform

- Added the XAMPP PHP dashboard foundation.
- Added MariaDB schema, seeded data, PDO configuration, and local environment settings.
- Added administrator provisioning and secure session login.
- Added task, note, reminder, habit, and system activity CRUD.

### Planning and productivity

- Added live clock and dynamic greetings.
- Added task search and status filters.
- Added drag-and-drop calendar rescheduling.
- Added recurring tasks and recurring habit tracking.
- Added browser notifications.
- Added task templates and automation rules.
- Added local daily planning recommendations.

### Collaboration

- Added workspaces and workspace memberships.
- Added team role management.
- Added invitations with hashed tokens and acceptance validation.
- Added workspace activity and audit event records.

### Analytics and security

- Added productivity analytics and category reporting.
- Added AES-256-GCM private vault entries.
- Added database and PWA maintenance pages.
- Added CSRF validation and session expiration enforcement.
- Added protected schema/configuration routes.
- Added CSV formula neutralization.
- Added exported backup restore validation.

### Operations

- Added PWA manifest and service worker.
- Added JSON/CSV export and backup restore.
- Added database QA and HTTP endpoint QA.
- Added installation, deployment, runbook, status, and release documentation.
