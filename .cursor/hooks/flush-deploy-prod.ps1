# Fin de tour agent — mode Git : rappel si commits / fichiers à pousser.
$ErrorActionPreference = 'Continue'
$root = (Resolve-Path (Join-Path $PSScriptRoot '..\..')).Path
$deployEnv = Join-Path $root '.deploy.env'
$queuePath = Join-Path $root 'storage\cache\deploy-queue.txt'

$followup = $null
try {
    Set-Location $root

    # Vide l’ancienne file SCP (obsolète en mode git)
    if (Test-Path $queuePath) {
        Clear-Content -Path $queuePath -ErrorAction SilentlyContinue
    }

    if (!(Test-Path $deployEnv)) {
        Write-Output '{}'
        exit 0
    }

    $map = @{}
    Get-Content $deployEnv | ForEach-Object {
        $line = $_.Trim()
        if ($line -eq '' -or $line.StartsWith('#')) { return }
        $i = $line.IndexOf('=')
        if ($i -lt 1) { return }
        $map[$line.Substring(0, $i).Trim()] = $line.Substring($i + 1).Trim()
    }

    if (($map['DEPLOY_ENABLED'] ?? '0') -ne '1') {
        Write-Output '{}'
        exit 0
    }

    $mode = ($map['DEPLOY_MODE'] ?? 'git').ToLowerInvariant()
    if ($mode -ne 'git') {
        $script = Join-Path $root 'scripts\deploy-web-prod.ps1'
        if ((Test-Path $queuePath) -and ((Get-Item $queuePath).Length -gt 0)) {
            & powershell -NoProfile -ExecutionPolicy Bypass -File $script -Queue
            if ($LASTEXITCODE -eq 0) {
                $followup = 'Fichiers web poussés sur athena (SCP).'
            } else {
                $followup = 'Échec upload SCP — préférez DEPLOY_MODE=git (push GitHub).'
            }
        }
    } else {
        $dirty = git status --porcelain 2>$null
        $remote = if ($map['DEPLOY_REMOTE']) { $map['DEPLOY_REMOTE'] } else { 'origin' }
        $branch = if ($map['DEPLOY_BRANCH']) { $map['DEPLOY_BRANCH'] } else { 'main' }
        $ahead = 0
        try {
            git fetch $remote $branch --quiet 2>$null | Out-Null
            $ahead = [int](git rev-list --count "$remote/$branch..HEAD" 2>$null)
        } catch {}

        if ($dirty) {
            $followup = 'Prod via Git : des fichiers locaux ne sont pas commités. Quand vous voulez déployer : commit + `git push origin main` (Action FTP Hostinger).'
        } elseif ($ahead -gt 0) {
            $auto = ($map['DEPLOY_AUTO_PUSH'] ?? '0') -eq '1'
            if ($auto) {
                & git push $remote "HEAD:$branch"
                if ($LASTEXITCODE -eq 0) {
                    $followup = "Push Git OK ($ahead commit) — déploiement Hostinger lancé via GitHub Actions."
                } else {
                    $followup = 'Échec `git push` — authentifiez GitHub puis relancez.'
                }
            } else {
                $followup = "Prod via Git : $ahead commit(s) prêts. Lancez `git push origin main` pour déployer sur Hostinger."
            }
        }
    }
} catch {
    $followup = "Hook deploy : $($_.Exception.Message)"
}

if ($followup) {
    $payload = @{ followup_message = $followup } | ConvertTo-Json -Compress
    Write-Output $payload
} else {
    Write-Output '{}'
}
exit 0
