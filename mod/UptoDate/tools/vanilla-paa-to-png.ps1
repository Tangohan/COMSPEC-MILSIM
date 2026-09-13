<#
.SYNOPSIS
  Convert vanilla Arma 3 map marker / map icon .paa assets to .png via Pal2PacE.
.EXAMPLE
  .\vanilla-paa-to-png.ps1
  .\vanilla-paa-to-png.ps1 -SourceA3Root "F:\SteamLibrary\steamapps\common\Arma 3\Addons\a3"
  .\vanilla-paa-to-png.ps1 -SourceA3Root "F:\...\Orange\Addons\a3" -IncludeGlobs @("ui_f_orange\data\cfgmarkers")
#>
param(
  [string]$SourceA3Root = "F:\SteamLibrary\steamapps\common\Arma 3\Addons\a3",
  [string]$DestRoot = "E:\Developpement\compsec.ttrd.fr\COMSPEC-MILSIM\public\assets\markers\arma\a3",
  [string]$Pal2PacE = (Join-Path $PSScriptRoot "Pal2PacE.exe"),
  # Relative to SourceA3Root - marker / map-icon trees only (not full UI)
  [string[]]$IncludeGlobs = @(
    "ui_f\data\map\markers",
    "ui_f\data\map\markerbrushes",
    "ui_f\data\map\groupicons",
    "ui_f\data\map\vehicleicons",
    "ui_f\data\map\locationtypes",
    "ui_f\data\map\respawn",
    "ui_f\data\map\mapcontrol"
  )
)

$ErrorActionPreference = "Continue"

if (-not (Test-Path -LiteralPath $Pal2PacE)) {
  Write-Error "Pal2PacE not found: $Pal2PacE"
  exit 1
}
if (-not (Test-Path -LiteralPath $SourceA3Root)) {
  Write-Error "Source a3 root not found: $SourceA3Root"
  exit 1
}

New-Item -ItemType Directory -Force -Path $DestRoot | Out-Null

$found = 0
$created = 0
$skippedMissing = 0
$failed = New-Object System.Collections.Generic.List[string]
$log = Join-Path $DestRoot "_conversion_log.txt"

function Convert-One {
  param([string]$PaaPath, [string]$OutPng)
  $dir = Split-Path -Parent $OutPng
  if (-not (Test-Path -LiteralPath $dir)) {
    New-Item -ItemType Directory -Force -Path $dir | Out-Null
  }
  $tmpDir = Join-Path $env:TEMP ("paa2png_" + [guid]::NewGuid().ToString("N"))
  New-Item -ItemType Directory -Force -Path $tmpDir | Out-Null
  try {
    $base = [IO.Path]::GetFileNameWithoutExtension($PaaPath).ToLowerInvariant()
    $tmpPaa = Join-Path $tmpDir ($base + ".paa")
    $tmpPng = Join-Path $tmpDir ($base + ".png")
    Copy-Item -LiteralPath $PaaPath -Destination $tmpPaa -Force
    $out = & $script:Pal2PacE $tmpPaa $tmpPng 2>&1
    if ((Test-Path -LiteralPath $tmpPng) -and ((Get-Item -LiteralPath $tmpPng).Length -gt 0)) {
      Copy-Item -LiteralPath $tmpPng -Destination $OutPng -Force
      return $true
    }
    $script:failed.Add("$PaaPath => $OutPng | $out") | Out-Null
    return $false
  } finally {
    Remove-Item -LiteralPath $tmpDir -Recurse -Force -ErrorAction SilentlyContinue
  }
}

Write-Host "Pal2PacE : $Pal2PacE"
Write-Host "Source   : $SourceA3Root"
Write-Host "Dest     : $DestRoot"
Write-Host ""

$sourceNorm = (Resolve-Path -LiteralPath $SourceA3Root).Path.TrimEnd('\')

foreach ($rel in $IncludeGlobs) {
  $srcSub = Join-Path $SourceA3Root $rel
  if (-not (Test-Path -LiteralPath $srcSub)) {
    Write-Warning "Missing include path: $srcSub"
    $skippedMissing++
    continue
  }
  Get-ChildItem -LiteralPath $srcSub -Recurse -Filter "*.paa" -File | ForEach-Object {
    $script:found++
    $full = $_.FullName
    $relFromA3 = $full.Substring($sourceNorm.Length).TrimStart('\')
    $outRel = ($relFromA3 -replace '\\', '/').ToLowerInvariant() -replace '\.paa$', '.png'
    $outPng = Join-Path $DestRoot ($outRel -replace '/', '\')
    if (Convert-One -PaaPath $full -OutPng $outPng) {
      $script:created++
      Write-Host "OK $outRel"
    } else {
      Write-Warning "FAIL $outRel"
    }
  }
}

$report = @"
PAA found        : $found
PNG created      : $created
Failures         : $($failed.Count)
Missing includes : $skippedMissing
Dest root        : $DestRoot
Converter        : Pal2PacE ($Pal2PacE)

Failures:
$($failed -join "`n")
"@
Set-Content -LiteralPath $log -Value $report -Encoding UTF8
Write-Host ""
Write-Host $report
if ($failed.Count -gt 0) { exit 1 }
exit 0
