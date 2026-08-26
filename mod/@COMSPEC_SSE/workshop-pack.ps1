<#
.SYNOPSIS
  Assemble un dossier Workshop propre pour @COMSPEC_SSE.

.DESCRIPTION
  Copie uniquement les artefacts destines a la diffusion Steam :
    - mod.cpp (+ meta.cpp s'il existe)
    - CREDITS.md (credits / licences)
    - CHANGELOG.md (historique versions, a la racine du pack)
    - addons/comspec_sse_*.pbo (+ event. .bisign)
    - logos (logo.paa, logoSmall.paa, overview.paa)

  Exclut explicitement : sources SQF/HPP, dossiers d'addons decompresses,
  docs internes, missions de demo, tools/, STEAM_DESCRIPTION.md,
  PACKAGING.md, build_log, obj, .cs.

  Ne reconstruit PAS les PBO - lancez build_mod.bat / build_pbo.bat avant si besoin.
  Ne copie PAS le dossier docs/ (le script Overwatch le fait, puis se bloque :
  ne pas reproduire).

.PARAMETER SourceMod
  Dossier mod de travail (defaut : ce dossier).

.PARAMETER OutDir
  Destination du pack (defaut : ../publisher/@COMSPEC_SSE).

.PARAMETER Zip
  Si defini, cree aussi une archive .zip a cote de OutDir.

.EXAMPLE
  .\workshop-pack.ps1
  .\workshop-pack.ps1 -Zip
#>
[CmdletBinding()]
param(
    [string] $SourceMod = "",
    [string] $OutDir = "",
    [switch] $Zip
)

$ErrorActionPreference = "Stop"
$ModRoot = $PSScriptRoot
if (-not $SourceMod) { $SourceMod = $ModRoot }
if (-not $OutDir) { $OutDir = Join-Path (Split-Path $ModRoot -Parent) 'publisher\@COMSPEC_SSE' }

function Write-Step([string] $msg) { Write-Host "[PACK] $msg" -ForegroundColor Cyan }
function Write-Fail([string] $msg) { Write-Host "[FAIL] $msg" -ForegroundColor Red; exit 1 }
function Write-Warn([string] $msg) { Write-Host "[WARN] $msg" -ForegroundColor Yellow }

if (-not (Test-Path -LiteralPath $SourceMod)) {
    Write-Fail "Source introuvable : $SourceMod"
}

$requiredPbos = @(
    'comspec_sse_main.pbo',
    'comspec_sse_core.pbo',
    'comspec_sse_generator.pbo',
    'comspec_sse_evidence.pbo',
    'comspec_sse_intel.pbo',
    'comspec_sse_interaction.pbo',
    'comspec_sse_zeus.pbo',
    'comspec_sse_eden.pbo',
    'comspec_sse_ui.pbo',
    'comspec_sse_network.pbo',
    'comspec_sse_digital.pbo',
    'comspec_sse_biometrics.pbo',
    'comspec_sse_compat_bii.pbo',
    'comspec_sse_compat_ace.pbo'
)

$addonsSrc = Join-Path $SourceMod 'addons'
foreach ($name in $requiredPbos) {
    $p = Join-Path $addonsSrc $name
    if (-not (Test-Path -LiteralPath $p)) {
        Write-Fail "Manquant : $p - lancez build_mod.bat / build_pbo.bat."
    }
}

$modCpp = Join-Path $SourceMod 'mod.cpp'
if (-not (Test-Path -LiteralPath $modCpp)) {
    Write-Fail "mod.cpp introuvable."
}

Write-Step "Nettoyage staging : $OutDir"
if (Test-Path -LiteralPath $OutDir) {
    Remove-Item -LiteralPath $OutDir -Recurse -Force
}
New-Item -ItemType Directory -Path (Join-Path $OutDir 'addons') -Force | Out-Null

Write-Step "Copie meta / credits"
Copy-Item -LiteralPath $modCpp -Destination (Join-Path $OutDir 'mod.cpp') -Force
$metaCpp = Join-Path $SourceMod 'meta.cpp'
if (Test-Path -LiteralPath $metaCpp) {
    Copy-Item -LiteralPath $metaCpp -Destination (Join-Path $OutDir 'meta.cpp') -Force
}
$credits = Join-Path $SourceMod 'CREDITS.md'
if (Test-Path -LiteralPath $credits) {
    Copy-Item -LiteralPath $credits -Destination (Join-Path $OutDir 'CREDITS.md') -Force
}

$changelogRoot = Join-Path $SourceMod 'CHANGELOG.md'
$changelogDocs = Join-Path $SourceMod 'docs\CHANGELOG.md'
if (Test-Path -LiteralPath $changelogRoot) {
    Copy-Item -LiteralPath $changelogRoot -Destination (Join-Path $OutDir 'CHANGELOG.md') -Force
} elseif (Test-Path -LiteralPath $changelogDocs) {
    Copy-Item -LiteralPath $changelogDocs -Destination (Join-Path $OutDir 'CHANGELOG.md') -Force
}

Write-Step "Copie PBO"
Get-ChildItem -LiteralPath $addonsSrc -Filter 'comspec_sse_*.pbo' -File -ErrorAction SilentlyContinue |
    ForEach-Object {
        Copy-Item -LiteralPath $_.FullName -Destination (Join-Path $OutDir "addons\$($_.Name)") -Force
        Write-Host "  + $($_.Name)"
    }

Get-ChildItem -LiteralPath $addonsSrc -Filter '*.bisign' -ErrorAction SilentlyContinue |
    ForEach-Object { Copy-Item -LiteralPath $_.FullName -Destination (Join-Path $OutDir "addons\$($_.Name)") -Force }
Get-ChildItem -LiteralPath (Join-Path $SourceMod 'keys') -Filter '*.bikey' -ErrorAction SilentlyContinue |
    ForEach-Object {
        $keysOut = Join-Path $OutDir 'keys'
        if (-not (Test-Path -LiteralPath $keysOut)) { New-Item -ItemType Directory -Path $keysOut | Out-Null }
        Copy-Item -LiteralPath $_.FullName -Destination (Join-Path $keysOut $_.Name) -Force
    }

foreach ($picName in @('logo.paa', 'logoSmall.paa', 'overview.paa', 'logo.png')) {
    $pic = Join-Path $SourceMod $picName
    if (Test-Path -LiteralPath $pic) {
        Copy-Item -LiteralPath $pic -Destination (Join-Path $OutDir $picName) -Force
    }
}

$forbidden = @(
    '*.pdb', '*.cs', '*.exp', '*.lib', '*.deps.json',
    '*.sqf', '*.hpp', 'XEH_*.sqf', 'config.cpp', '$PBOPREFIX$'
)
$bad = @()
Get-ChildItem -LiteralPath $OutDir -Recurse -File | ForEach-Object {
    $rel = $_.FullName.Substring($OutDir.Length).TrimStart('\', '/')
    $name = $_.Name
    if ($name -match '\.(pdb|cs|exp|lib|sqf|hpp)$') { $bad += $rel }
    if ($name -eq 'config.cpp' -or $name -eq '$PBOPREFIX$') { $bad += $rel }
    if ($rel -match '(?i)(^|[/\\])(net8\.0|Sources|docs|obj|bin|tools|missions|_build_tmp)([/\\]|$)') { $bad += $rel }
    if ($name -eq 'STEAM_DESCRIPTION.md' -or $name -eq 'PACKAGING.md') { $bad += $rel }
    if ($name -eq 'build_mod.bat' -or $name -eq 'build_pbo.bat' -or $name -eq 'workshop-pack.ps1') { $bad += $rel }
    if ($name -eq '.env' -or $name -like '*.biprivatekey') { $bad += $rel }
}
if ($bad.Count -gt 0) {
    Write-Fail ("Fichiers interdits detectes dans le pack :`n  - " + ($bad -join "`n  - "))
}

Write-Host ""
Write-Host "========== PACK WORKSHOP OK ==========" -ForegroundColor Green
Write-Host "  Sortie : $OutDir"
Write-Host "  Contenu autorise :"
Get-ChildItem -LiteralPath $OutDir -Recurse -File | ForEach-Object {
    $rel = $_.FullName.Substring($OutDir.Length).TrimStart('\', '/')
    Write-Host ("    {0,-50} {1,10:N0} o" -f $rel, $_.Length)
}
Write-Host ""
Write-Host "  A NE PAS publier depuis le dossier de travail :"
Write-Host "    - addons/main|core|... (sources) - seulement comspec_sse_*.pbo"
Write-Host "    - docs/, missions/, tools/, STEAM_DESCRIPTION.md, PACKAGING.md"
Write-Host "======================================"

if ($Zip) {
    $zipPath = Join-Path (Split-Path $OutDir -Parent) 'COMSPEC_SSE-workshop.zip'
    if (Test-Path -LiteralPath $zipPath) { Remove-Item -LiteralPath $zipPath -Force }
    Write-Step "Archive : $zipPath"
    Compress-Archive -Path $OutDir -DestinationPath $zipPath -CompressionLevel Optimal
    Write-Host "  ZIP : $zipPath" -ForegroundColor Green
}

Write-Host ""
Write-Host "Prochaine etape : publier le contenu de $OutDir via Publisher Arma 3 / Steam Workshop."
Write-Host "Voir PACKAGING.md (atelier) et docs/PUBLICATION.md (chef de mission)."
