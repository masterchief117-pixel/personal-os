$ErrorActionPreference = "Stop"

$php = "C:\xampp\php\php.exe"
$script = "C:\xampp\htdocs\personal-os\cron\generate_backup.php"
$taskName = "Personal OS Scheduled Backups"

if (-not (Test-Path $php)) { throw "PHP executable was not found at $php" }
if (-not (Test-Path $script)) { throw "Backup script was not found at $script" }

$action = New-ScheduledTaskAction -Execute $php -Argument "`"$script`""
$trigger = New-ScheduledTaskTrigger -Daily -At "02:30"
$settings = New-ScheduledTaskSettingsSet -StartWhenAvailable

Register-ScheduledTask -TaskName $taskName -Action $action -Trigger $trigger -Settings $settings -Force | Out-Null
Write-Host "Registered scheduled task: $taskName"
