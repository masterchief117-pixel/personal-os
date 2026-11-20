# Fresh Installation Guide

## Prerequisites

- Windows with XAMPP
- Apache and MariaDB/MySQL running
- PHP 8.1 or newer
- PowerShell

## Install

1. Copy this project folder to `C:/xampp/htdocs/personal-os`.
2. Start Apache and MariaDB.
3. Run the installer:

```powershell
powershell -ExecutionPolicy Bypass -File C:\xampp\htdocs\personal-os\install.ps1
```

The installer skips the destructive schema import if `personal_os` already exists.

To explicitly rebuild an empty or disposable database:

```powershell
powershell -ExecutionPolicy Bypass -File C:\xampp\htdocs\personal-os\install.ps1 -ForceFreshImport
```

4. Copy `.env.example` to `C:/xampp/.personal-os.env`.
5. Set `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASSWORD`, `VAULT_KEY`, and `APP_URL`.
6. Open `http://localhost/personal-os/setup.php`.
7. Provision the administrator account.
8. Open `http://localhost/personal-os/login.php`.

## Scheduled maintenance

Register the backup task:

```powershell
powershell -ExecutionPolicy Bypass -File C:\xampp\htdocs\personal-os\install-register-backup.ps1
```

Registering the task requires permission to create local scheduled tasks.

## Validation

```powershell
php C:\xampp\htdocs\personal-os\tests\run.php
php C:\xampp\htdocs\personal-os\tests\qa.php
php C:\xampp\htdocs\personal-os\tests\regression.php
```
