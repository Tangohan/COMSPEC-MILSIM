#Requires -Version 5.1
<#
.SYNOPSIS
  Réencode les vidéos hero Athena en H.264 + AAC (navigateur) à partir des MP4 existants.

.DESCRIPTION
  Les exports Apple (QuickTime HEVC / hvc1) sont illisibles dans Chrome / Edge / Firefox.
  Ce script produit des MP4 H.264 + yuv420p + faststart, avec piste audio AAC (le hero
  peut être démuté après geste utilisateur).

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
        Write-Host "  [WhatIf] ffmpeg -i $src -> $tmp (H.264 + AAC)"
        continue
    }

    if (Test-Path -LiteralPath $tmp) {
        Remove-Item -LiteralPath $tmp -Force
    }

    # fps=30 : certains exports Dreamina / QT ont un timebase 120 tbr qui
    # produit des centaines de frames dupliquées et dépasse le level 4.1.
    & ffmpeg -y -hide_banner -loglevel error -i $src `
        -vf 'fps=30' `
        -c:v libx264 -profile:v high -level 4.1 -pix_fmt yuv420p `
        -crf 23 -preset medium `
        -movflags +faststart `
        -c:a aac -b:a 128k -ac 2 `
        $tmp
    if ($LASTEXITCODE -ne 0) {
        throw "ffmpeg a échoué pour $src (code $LASTEXITCODE)"
    }

    $probe = & ffprobe -v error -select_streams v:0 `
        -show_entries stream=codec_name,profile,pix_fmt,r_frame_rate `
        -of default=nw=1 $tmp
    if ($LASTEXITCODE -ne 0) {
        throw "ffprobe a échoué pour $tmp"
    }

    $audioProbe = & ffprobe -v error -select_streams a:0 `
        -show_entries stream=codec_name,channels `
        -of default=nw=1 $tmp
    if ($LASTEXITCODE -ne 0) {
        throw "Aucune piste audio dans $tmp — le bouton son du hero serait mort."
    }

    $joined = (($probe + $audioProbe) | Out-String)
    if ($joined -notmatch 'codec_name=h264' -or $joined -notmatch 'pix_fmt=yuv420p') {
        throw "Sortie vidéo inattendue pour ${stem}:`n$joined"
    }
    if ($joined -notmatch 'codec_name=aac') {
        throw "Sortie audio inattendue pour ${stem}:`n$joined"
    }

    Move-Item -LiteralPath $tmp -Destination $src -Force
    Write-Host "  OK H.264 + AAC -> $src"
    Write-Host "  $(($probe + $audioProbe) -join ', ')"

    if ($KeepWebm) {
        & ffmpeg -y -hide_banner -loglevel error -i $src `
            -vf 'fps=30' `
            -c:v libvpx-vp9 -crf 33 -b:v 0 -row-mt 1 `
            -c:a libopus -b:a 96k `
            $webm
        if ($LASTEXITCODE -ne 0) {
            throw "ffmpeg WebM a échoué pour $src"
        }
        Write-Host "  OK WebM -> $webm"
    }
}

Write-Host ''
Write-Host 'Terminé. Vérifier l''accueil : data-hero-videos-ready="1", son démutable, puis déployer les MP4 en binaire.'
