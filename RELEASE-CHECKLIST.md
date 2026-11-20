# Personal OS Release Checklist

## Pre-release

- [x] Database imported successfully
- [x] Dedicated database account configured
- [x] `C:/xampp/.personal-os.env` populated outside the web root
- [x] Administrator provisioned through `/setup.php`
- [x] Demo and placeholder credentials removed
- [x] Local HTTPS certificate and HTTP redirect configured for localhost
- [ ] Trusted production HTTPS certificate and redirect verified
- [x] Apache overrides and security headers verified
- [x] Service worker cache behavior verified
- [x] Service worker cache contains only static assets and the generic offline shell
- [x] Session timeout verified
- [x] Automated recurring task task scheduling registered
- [x] Automated backup task scheduling registered
- [x] Login CSP and external-script loading verified
- [x] Mobile login viewport verified without horizontal overflow

## Functional

- [x] Login and logout
- [x] Task create, complete, filter, search, and delete
- [x] Recurring task generation
- [x] Note create and pin
- [x] Calendar navigation
- [x] Habit creation and progress
- [x] Browser notifications
- [x] Live weather and server-vitals dashboard widgets
- [x] Command palette keyboard navigation, quick-create, cross-record search, and mobile access
- [x] Team role restrictions
- [x] Invitation creation
- [x] SMTP handshake and message acceptance tested against a loopback TLS mock with dummy credentials
- [ ] SMTP credentials configured and test invitation email delivered
- [x] JSON and CSV export
- [x] Backup download and restore

## Validation

- [x] `php tests/run.php`
- [x] `php tests/qa.php`
- [x] `node --test tests/service-worker.test.js`
- [x] JavaScript syntax check
- [x] PHP lint across application
- [x] Desktop browser pass
- [x] Mobile browser pass
- [x] Protected resources return 403
- [x] Anonymous APIs return 401
- [x] Authenticated weather and system-vitals APIs return live JSON
- [x] Authenticated workspace search returns tasks, notes, goals, habits, and journal entries without journal text
- [x] Search-result links resolve to the matching owner-scoped records and journal dates
- [x] Invitation email remains disabled until SMTP is configured

## Sign-off

- Owner:
- Date:
- Environment:
