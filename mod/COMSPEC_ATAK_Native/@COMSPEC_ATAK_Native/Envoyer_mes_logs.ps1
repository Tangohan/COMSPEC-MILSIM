# COMSPEC : prépare un .zip de vos logs sur le Bureau, prêt à glisser dans Discord.
# COMSPEC: builds a .zip of your logs on the Desktop, ready to drop into Discord.
# Les mots de passe, jetons et liens de webhook sont masqués dans la copie.

$ErrorActionPreference = 'Stop'
$armaDir  = Join-Path $env:LOCALAPPDATA 'Arma 3'
$linkDir  = Join-Path $armaDir 'COMSPEC\logs'
$desktop  = [Environment]::GetFolderPath('Desktop')
$stamp    = Get-Date -Format 'yyyy-MM-dd_HHmm'
$zipPath  = Join-Path $desktop "COMSPEC_logs_$stamp.zip"
$work     = Join-Path $env:TEMP "COMSPEC_logs_$stamp"

function Read-Shared([string]$path) {
    # Arma peut encore écrire dans le fichier : lecture partagée.
    $fs = [IO.File]::Open($path, 'Open', 'Read', 'ReadWrite')
    try {
        $sr = New-Object IO.StreamReader($fs, [Text.Encoding]::UTF8, $true)
        return $sr.ReadToEnd()
    } finally { $fs.Dispose() }
}

function Hide-Secrets([string]$text) {
    $text = [regex]::Replace($text, 'https?://(?:canary\.|ptb\.)?discord(?:app)?\.com/api/webhooks/\S+', '[webhook masqué]')
    $text = [regex]::Replace($text, '(?i)\b(bearer)\s+[A-Za-z0-9._~+/=-]{8,}', '$1 [masqué]')
    $text = [regex]::Replace($text, '(?i)\b(token|jeton|password|passwd|mot_de_passe|secret|api[_-]?key|apikey|authorization|session_key|sessionkey)("?\s*[:=]\s*"?)[^\s",;}]+', '$1$2[masqué]')
    return $text
}

Write-Host ''
Write-Host '  COMSPEC - Envoyer mes logs / Send my logs' -ForegroundColor Green
Write-Host '  -----------------------------------------'
Write-Host ''

$files = @()
if (Test-Path $armaDir) {
    $files += Get-ChildItem -Path $armaDir -Filter '*.rpt' -File | Sort-Object LastWriteTime -Descending | Select-Object -First 2
}
if (Test-Path $linkDir) {
    $files += Get-ChildItem -Path $linkDir -Filter 'COMSPEC_*.log' -File | Sort-Object LastWriteTime -Descending | Select-Object -First 2
}

if ($files.Count -eq 0) {
    Write-Host "  Aucun log trouvé dans $armaDir" -ForegroundColor Yellow
    Write-Host '  No log found. Launch Arma 3 once, then try again.' -ForegroundColor Yellow
    Write-Host ''
    Read-Host '  Entrée pour fermer / Press Enter to close' | Out-Null
    exit 1
}

New-Item -ItemType Directory -Path $work -Force | Out-Null
try {
    foreach ($f in $files) {
        $kind = if ($f.Extension -eq '.rpt') { 'Arma' } else { 'COMSPEC Link' }
        Write-Host ("  + {0,-13} {1}  ({2:dd/MM HH:mm}, {3:N0} Ko)" -f $kind, $f.Name, $f.LastWriteTime, ($f.Length / 1KB))
        $clean = Hide-Secrets (Read-Shared $f.FullName)
        [IO.File]::WriteAllText((Join-Path $work $f.Name), $clean, (New-Object Text.UTF8Encoding($false)))
    }
    if (Test-Path $zipPath) { Remove-Item $zipPath -Force }
    Compress-Archive -Path (Join-Path $work '*') -DestinationPath $zipPath -CompressionLevel Optimal
} finally {
    Remove-Item $work -Recurse -Force -ErrorAction SilentlyContinue
}

$sizeMb = (Get-Item $zipPath).Length / 1MB
Write-Host ''
Write-Host ("  Prêt / Ready : {0}  ({1:N1} Mo)" -f $zipPath, $sizeMb) -ForegroundColor Green
Write-Host ''
Write-Host '  Glissez ce fichier dans votre sujet bugs ou help sur Discord.'
Write-Host '  Drag this file into your bugs or help post on Discord.'
if ($sizeMb -gt 10) {
    Write-Host '  Fichier lourd : sans Nitro, Discord limite à 10 Mo. Envoyez-le au Support.' -ForegroundColor Yellow
    Write-Host '  Large file: Discord caps uploads at 10 MB without Nitro. Send it to Support.' -ForegroundColor Yellow
}
Start-Process explorer.exe -ArgumentList "/select,`"$zipPath`""
Write-Host ''
Read-Host '  Entrée pour fermer / Press Enter to close' | Out-Null
