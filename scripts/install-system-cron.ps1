# Installe le passage automatique des tâches Athena (toutes les 5 minutes) sous Windows.
# Exécuter en PowerShell : powershell -ExecutionPolicy Bypass -File scripts/install-system-cron.ps1
$ErrorActionPreference = 'Stop'
$root = Split-Path -Parent $PSScriptRoot
$php = $env:PHP_CLI
if (-not $php) {
    $cmd = Get-Command php -ErrorAction SilentlyContinue
    if ($cmd) { $php = $cmd.Source }
}
if (-not $php) {
    Write-Error 'php introuvable. Installez PHP en ligne de commande, puis relancez.'
}
$script = Join-Path $root 'scripts\cron-run.php'
$taskName = 'Athena-TachesAutomatiques'
$action = New-ScheduledTaskAction -Execute $php -Argument "`"$script`"" -WorkingDirectory $root
$trigger = New-ScheduledTaskTrigger -Once -At (Get-Date) -RepetitionInterval (New-TimeSpan -Minutes 5) -RepetitionDuration ([TimeSpan]::MaxValue)
Unregister-ScheduledTask -TaskName $taskName -Confirm:$false -ErrorAction SilentlyContinue
Register-ScheduledTask -TaskName $taskName -Action $action -Trigger $trigger -Description 'Taches automatiques Athena (escalade des rapports, rappels, nettoyage).' | Out-Null
Write-Host "Passage automatique installe : $taskName (toutes les 5 minutes)"
