<#
.SYNOPSIS
  Déploiement web Athena — mode Git (push) ou SCP (legacy).

.DESCRIPTION
  Lit .deploy.env à la racine.
  Mode git (défaut) :
    .\scripts\deploy-web-prod.ps1
    → git push vers origin/main (si commits locaux en avance)
  Mode scp :
    .\scripts\deploy-web-prod.ps1 -Files app\Foo.php
    .\scripts\deploy-web-prod.ps1 -Queue
#>
[CmdletBinding()]
param(
    [string[]]$Files = @(),
    [switch]$Queue,
    [switch]$DryRun
)

$ErrorActionPreference = 'Stop'
$root = (Resolve-Path (Join-Path $PSScriptRoot '..')).Path
Set-Location $root

function Read-DeployEnv {
    $path = Join-Path $root '.deploy.env'
    if (!(Test-Path $path)) {
        throw "Fichier .deploy.env manquant. Copiez .deploy.env.example vers .deploy.env."
    }
    $map = @{}
    Get-Content -Path $path | ForEach-Object {
        $line = $_.Trim()
        if ($line -eq '' -or $line.StartsWith('#')) { return }
        $i = $line.IndexOf('=')
        if ($i -lt 1) { return }
        $k = $line.Substring(0, $i).Trim()
        $v = $line.Substring($i + 1).Trim().Trim('"').Trim("'")
        $map[$k] = $v
    }
    return $map
}

function Deploy-ViaGit([hashtable]$envMap) {
    $remote = if ($envMap['DEPLOY_REMOTE']) { $envMap['DEPLOY_REMOTE'] } else { 'origin' }
    $branch = if ($envMap['DEPLOY_BRANCH']) { $envMap['DEPLOY_BRANCH'] } else { 'main' }
    $autoPush = ($envMap['DEPLOY_AUTO_PUSH'] ?? '0') -eq '1'

    $status = git status --porcelain
    if ($status) {
        Write-Host '[deploy/git] Des fichiers ne sont pas commités. Committez puis poussez pour déployer.'
        Write-Host ($status | Select-Object -First 12)
        if ($status.Count -gt 12) { Write-Host '…' }
        exit 2
    }

    git fetch $remote $branch 2>$null | Out-Null
    $ahead = [int](git rev-list --count "$remote/$branch..HEAD" 2>$null)
    if ($ahead -lt 1) {
        Write-Host "[deploy/git] Rien à pousser — HEAD est déjà aligné sur $remote/$branch."
        exit 0
    }

    Write-Host "[deploy/git] $ahead commit(s) en avance sur $remote/$branch."
    if ($DryRun -or -not $autoPush) {
        Write-Host "[deploy/git] Lancez : git push $remote HEAD:$branch"
        Write-Host '[deploy/git] (GitHub Actions FTP déploiera ensuite sur Hostinger si les secrets sont configurés.)'
        exit 0
    }

    & git push $remote "HEAD:$branch"
    if ($LASTEXITCODE -ne 0) { exit $LASTEXITCODE }
    Write-Host '[deploy/git] Push OK — déploiement Hostinger via Action GitHub.'
    exit 0
}

function Test-AllowedPath([string]$rel) {
    $rel = $rel -replace '\\', '/'
    $rel = $rel.TrimStart('/')
    if ($rel -match '(^|/)\.env($|\.)' -or $rel -match '(^|/)vendor/' -or $rel -match '(^|/)storage/' -or $rel -match '(^|/)node_modules/' -or $rel -match '(^|/)\.git/') {
        return $false
    }
    $prefixes = @(
        'app/', 'views/', 'public/', 'bootstrap/', 'routes/', 'config/',
        'scripts/', 'migrations/', 'docs/', 'composer.json', 'run-migrations.php',
        'index.php', '.htaccess'
    )
    foreach ($p in $prefixes) {
        if ($rel -eq $p.TrimEnd('/') -or $rel.StartsWith($p)) { return $true }
    }
    return $false
}

function Normalize-Rel([string]$path) {
    if ([string]::IsNullOrWhiteSpace($path)) { return $null }
    $full = $path
    if (!(Test-Path -LiteralPath $full)) {
        $candidate = Join-Path $root $path
        if (Test-Path -LiteralPath $candidate) { $full = $candidate } else { return $null }
    }
    $full = (Resolve-Path -LiteralPath $full).Path
    if (!$full.StartsWith($root, [System.StringComparison]::OrdinalIgnoreCase)) { return $null }
    $rel = $full.Substring($root.Length).TrimStart('\', '/')
    return ($rel -replace '\\', '/')
}

$envMap = Read-DeployEnv
if (($envMap['DEPLOY_ENABLED'] ?? '0') -ne '1') {
    Write-Host '[deploy] Désactivé (DEPLOY_ENABLED!=1).'
    exit 0
}

$mode = ($envMap['DEPLOY_MODE'] ?? 'git').ToLowerInvariant()
if ($mode -eq 'git') {
    Deploy-ViaGit $envMap
}

# --- Mode SCP legacy ---
$hostName = $envMap['DEPLOY_HOST']
$user = $envMap['DEPLOY_USER']
$port = if ($envMap['DEPLOY_PORT']) { $envMap['DEPLOY_PORT'] } else { '22' }
$remoteRoot = ($envMap['DEPLOY_PATH'] ?? '').TrimEnd('/', '\')
$key = $envMap['DEPLOY_KEY']

if ([string]::IsNullOrWhiteSpace($hostName) -or [string]::IsNullOrWhiteSpace($user) -or [string]::IsNullOrWhiteSpace($remoteRoot)) {
    throw 'Mode scp : DEPLOY_HOST, DEPLOY_USER et DEPLOY_PATH obligatoires (ou passez en DEPLOY_MODE=git).'
}

$queuePath = Join-Path $root 'storage/cache/deploy-queue.txt'
$toUpload = New-Object System.Collections.Generic.HashSet[string]

if ($Queue -and (Test-Path $queuePath)) {
    Get-Content $queuePath | ForEach-Object {
        $n = Normalize-Rel $_.Trim()
        if ($n -and (Test-AllowedPath $n)) { [void]$toUpload.Add($n) }
    }
    Clear-Content -Path $queuePath -ErrorAction SilentlyContinue
}

foreach ($f in $Files) {
    $n = Normalize-Rel $f
    if ($n -and (Test-AllowedPath $n)) { [void]$toUpload.Add($n) }
}

if ($toUpload.Count -eq 0) {
    Write-Host '[deploy/scp] Aucun fichier ciblé.'
    exit 0
}

$scpArgs = @('-P', $port, '-o', 'BatchMode=yes', '-o', 'StrictHostKeyChecking=accept-new')
if ($key -and (Test-Path -LiteralPath $key)) {
    $scpArgs += @('-i', $key)
}

$ok = 0
$fail = 0
foreach ($rel in ($toUpload | Sort-Object)) {
    $local = Join-Path $root ($rel -replace '/', '\')
    if (!(Test-Path -LiteralPath $local)) {
        Write-Warning "Absent localement : $rel"
        continue
    }
    $remoteDir = Split-Path ($remoteRoot + '/' + $rel) -Parent
    $remoteDirUnix = ($remoteDir -replace '\\', '/')
    $remoteFile = ($remoteRoot + '/' + $rel) -replace '\\', '/'
    $target = "${user}@${hostName}:$remoteFile"

    Write-Host "[deploy/scp] $rel -> $target"
    if ($DryRun) { $ok++; continue }

    $sshMkdir = @('-p', $port, '-o', 'BatchMode=yes', '-o', 'StrictHostKeyChecking=accept-new')
    if ($key -and (Test-Path -LiteralPath $key)) { $sshMkdir += @('-i', $key) }
    $sshMkdir += @("${user}@${hostName}", "mkdir -p `"$remoteDirUnix`"")
    & ssh @sshMkdir 2>$null | Out-Null

    & scp @scpArgs -- $local $target
    if ($LASTEXITCODE -eq 0) { $ok++ } else { $fail++; Write-Warning "Échec scp : $rel" }
}

Write-Host "[deploy/scp] Terminé — ok=$ok fail=$fail"
if ($fail -gt 0) { exit 1 }
exit 0
