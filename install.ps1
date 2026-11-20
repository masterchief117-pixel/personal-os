$ErrorActionPreference = "Stop"
param(
    [switch]$ForceFreshImport
)
$root = Split-Path -Parent $MyScriptName
$root = Resolve-Path "$PSScriptRoot"
$mysql = "C:\xampp\mysql\bin\mysql.exe"

if (-not (Test-Path $mysql)) { throw "XAMPP MariaDB executable was not found." }
if (-not (Test-Path "$root\database\schema.sql")) { throw "Database schema was not found." }

$databaseExists = & $mysql -u root -N -e "SELECT COUNT(*) FROM information_schema.schemata WHERE schema_name='personal_os'"
if ($ForceFreshImport -or $databaseExists -eq "0") {
    Write-Host "Importing Personal OS database schema..."
    Get-Content "$root\database\schema.sql" -Raw | & $mysql -u root
} else {
    Write-Host "Existing personal_os database detected; skipping destructive schema import."
}

$envFile = "C:\xampp\.personal-os.env"
if (-not (Test-Path $envFile)) {
    Write-Host "Creating local database environment configuration..."
    Copy-Item "$root\.env.example" $envFile
}

Write-Host "Personal OS installation completed."
Write-Host "Open http://localhost/personal-os/setup.php"
