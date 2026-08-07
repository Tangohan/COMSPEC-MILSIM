#Requires -Version 5.1
<#
.SYNOPSIS
  Réencode les vidéos hero Athena en H.264 (navigateur) à partir des MP4 existants.

.DESCRIPTION
  Les fichiers public/assets/video/hero-athena*.mp4 sont actuellement des QuickTime HEVC
  (hvc1), illisibles dans Chrome / Edge / Firefox. Ce script produit des MP4 H.264 +
  yuv420p + faststart, sans piste audio (le hero est muet à l'affichage).

  Prérequis : ffmpeg et ffprobe dans le PATH.
  Voir docs/VIDEO-HERO-ENCODAGE.md et docs/bugs/2026-08-07-hero-videos-hevc-rejetes.md.
#>
[CmdletBinding()]
param(
    [string]$VideoDir = "",
    [switch]$KeepWebm,
    [switch]$WhatIf
)

$ErrorActionPreference = 'Stop'

function Assert-Command([string]$Name) {
    if (-not (Get-Command $Name -ErrorAction SilentlyContinue)) {
        throw "Commande introuvable : $Name. Installez ffmpeg (ex. winget install Gyan.FFmpeg) puis rouvrez le terminal."
    }
}

Assert-Command 'ffmpeg'
Assert-Command 'ffprobe'

if ([string]::IsNullOrWhiteSpace($VideoDir)) {
    $root = Split-Path -Parent $PSScriptRoot
    $VideoDir = Join-Path $root 'public\assets\video'
}

if (-not (Test-Path -LiteralPath $VideoDir)) {
    throw "Dossier introuvable : $VideoDir"
}

$stems = @('hero-athena', 'hero-athena-2', 'hero-athena-3')

foreach ($stem in $stems) {
    $src = Join-Path $VideoDir ($stem + '.mp4')
    if (-not (Test-Path -LiteralPath $src)) {
        Write-Warning "Absent, ignoré : $src"
        continue
    }

    $tmp = Join-Path $VideoDir ($stem + '.h264.mp4')
    $webm = Join-Path $VideoDir ($stem + '.webm')

    Write-Host "==> $stem"
    if ($WhatIf) {
        Write-Host "  [WhatIf] ffmpeg -i $src -> $tmp"
        continue
    }

    if (Test-Path -LiteralPath $tmp) {
        Remove-Item -LiteralPath $tmp -Force
    }

    & ffmpeg -y -hide_banner -loglevel error -i $src `
        -c:v libx264 -profile:v high -level 4.1 -pix_fmt yuv420p `
        -crf 23 -preset slow `
        -movflags +faststart `
        -an `
        $tmp
    if ($LASTEXITCODE -ne 0) {
        throw "ffmpeg a échoué pour $src (code $LASTEXITCODE)"
    }

    $probe = & ffprobe -v error -select_streams v:0 `
        -show_entries stream=codec_name,profile,pix_fmt `
        -of default=nw=1 $tmp
    if ($LASTEXITCODE -ne 0) {
        throw "ffprobe a échoué pour $tmp"
    }

    $joined = ($probe | Out-String)
    if ($joined -notmatch 'codec_name=h264' -or $joined -notmatch 'pix_fmt=yuv420p') {
        throw "Sortie inattendue pour ${stem}:`n$joined"
    }

    Move-Item -LiteralPath $tmp -Destination $src -Force
    Write-Host "  OK H.264 -> $src"
    Write-Host "  $(($probe -join ', '))"

    if ($KeepWebm) {
        & ffmpeg -y -hide_banner -loglevel error -i $src `
            -c:v libvpx-vp9 -crf 33 -b:v 0 -row-mt 1 `
            -an `
            $webm
        if ($LASTEXITCODE -ne 0) {
            throw "ffmpeg WebM a échoué pour $src"
        }
        Write-Host "  OK WebM -> $webm"
    }
}

Write-Host ''
Write-Host 'Terminé. Vérifier l''accueil : data-hero-videos-ready="1", puis déployer les MP4 en binaire.'
