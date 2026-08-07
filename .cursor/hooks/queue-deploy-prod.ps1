# Mode Git : plus de file SCP. Hook no-op (le flush rappelle commit/push).
$ErrorActionPreference = 'Continue'
try {
    $raw = [Console]::In.ReadToEnd()
    # Consumed for Cursor hook protocol; nothing to queue in git mode.
    $null = $raw
} catch {}
Write-Output '{}'
exit 0
