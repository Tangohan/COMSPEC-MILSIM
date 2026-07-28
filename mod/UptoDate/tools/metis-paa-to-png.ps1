<#
.SYNOPSIS
  Recursively convert Metis Marker .paa assets to .png via Pal2PacE.
.EXAMPLE
  .\metis-paa-to-png.ps1
  .\metis-paa-to-png.ps1 -SourceRoot "C:\Users\tetar\Documents\Paascripts\MetisMarker" -DestRoot "E:\...\data"
#>
param(
  [string]$SourceRoot = "C:\Users\tetar\Documents\Paascripts\MetisMarker",
  [string]$DestRoot = "E:\Developpement\compsec.ttrd.fr\COMSPEC-MILSIM\public\assets\markers\arma\z\mts\addons\markers\data",
  [string]$Pal2PacE = "C:\Users\tetar\Documents\Paascripts\Pal2PacE.exe",
  [string[]]$Subdirs = @("alphanum","blu","com","dtg","neu","red","special","ui","unk")
)

$ErrorActionPreference = "Continue"

if (-not (Test-Path -LiteralPath $Pal2PacE)) {
  Write-Error "Pal2PacE not found: $Pal2PacE"
  exit 1
}
if (-not (Test-Path -LiteralPath $SourceRoot)) {
  Write-Error "Source root not found: $SourceRoot"
  exit 1
}

New-Item -ItemType Directory -Force -Path $DestRoot | Out-Null

$found = 0
$created = 0
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
Write-Host "Source   : $SourceRoot"
Write-Host "Dest     : $DestRoot"
Write-Host ""

# Root special textmarker
$rootPaa = Join-Path $SourceRoot "mts_markers_special_textmarker.paa"
if (Test-Path -LiteralPath $rootPaa) {
  $found++
  $out = Join-Path $DestRoot "mts_markers_special_textmarker.png"
  Write-Host "[root] mts_markers_special_textmarker.paa"
  if (Convert-One -PaaPath $rootPaa -OutPng $out) { $created++ }
}

foreach ($sub in $Subdirs) {
  $srcSub = Join-Path $SourceRoot $sub
  if (-not (Test-Path -LiteralPath $srcSub)) {
    Write-Warning "Missing subdir: $srcSub"
    continue
  }
  $paas = Get-ChildItem -LiteralPath $srcSub -Recurse -Filter *.paa -File
  Write-Host "[$sub] $($paas.Count) paa"
  foreach ($paa in $paas) {
    $found++
    $rel = $paa.FullName.Substring($srcSub.Length).TrimStart('\','/')
    $relDir = Split-Path -Parent $rel
    $name = ([IO.Path]::GetFileNameWithoutExtension($paa.Name)).ToLowerInvariant() + ".png"
    if ($relDir) {
      $parts = $relDir.Split([char[]]@('\','/'), [StringSplitOptions]::RemoveEmptyEntries) | ForEach-Object { $_.ToLowerInvariant() }
      $outDir = Join-Path (Join-Path $DestRoot $sub) ($parts -join '\')
    } else {
      $outDir = Join-Path $DestRoot $sub
    }
    $out = Join-Path $outDir $name
    if (-not (Convert-One -PaaPath $paa.FullName -OutPng $out)) {
      # already logged
    } else {
      $created++
    }
  }
}

$summary = @"
PAA found   : $found
PNG created : $created
Failures    : $($failed.Count)
Dest root   : $DestRoot
Converter   : Pal2PacE ($Pal2PacE)
"@
Write-Host ""
Write-Host $summary
if ($failed.Count -gt 0) {
  Write-Host "FAILURES:"
  $failed | ForEach-Object { Write-Host $_ }
}
$summary + "`n`nFailures:`n" + ($failed -join "`n") | Set-Content -LiteralPath $log -Encoding UTF8
Write-Host "Log: $log"
exit $(if ($failed.Count -gt 0) { 1 } else { 0 })
