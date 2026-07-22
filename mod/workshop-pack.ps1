<#
.SYNOPSIS
  Assemble un dossier Workshop / zip " propre " pour @COMSPECOverwatch.

.DESCRIPTION
  Copie uniquement les artefacts destinés à la diffusion :
    - mod.cpp (+ meta.cpp s'il existe)
    - CREDITS.md (obligations licence / crédits)
    - addons/*.pbo (+ évent. .bisign)
    - COMSPECExtension_x64.dll Native AOT (~5 Mo) à la racine

  Exclut explicitement : Sources SQF/HPP, net8.0/, *.pdb, *.cs, *.exp, *.lib,
  docs internes, STEAM_DESCRIPTION.md, README de build, COMSPECExtension sources.

  Ne reconstruit PAS les PBO ni la DLL - lancez build_mod.bat avant si besoin.
  Ne touche pas aux sources de développement dans @COMSPECOverwatch/addons/connect|main.

.PARAMETER SourceMod
  Dossier mod de travail (défaut : @COMSPECOverwatch à côté de ce script).

.PARAMETER OutDir
  Destination du pack (défaut : publisher/@COMSPECOverwatch).

.PARAMETER Zip
  Si défini, crée aussi une archive .zip à côté de OutDir.

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
if (-not $SourceMod) { $SourceMod = Join-Path $ModRoot '@COMSPECOverwatch' }
if (-not $OutDir) { $OutDir = Join-Path $ModRoot 'publisher\@COMSPECOverwatch' }

function Write-Step([string] $msg) { Write-Host "[PACK] $msg" -ForegroundColor Cyan }
function Write-Fail([string] $msg) { Write-Host "[FAIL] $msg" -ForegroundColor Red; exit 1 }
function Write-Warn([string] $msg) { Write-Host "[WARN] $msg" -ForegroundColor Yellow }

if (-not (Test-Path -LiteralPath $SourceMod)) {
    Write-Fail "Source introuvable : $SourceMod"
}

$dllCandidates = @(
    (Join-Path $SourceMod "COMSPECExtension_x64.dll"),
    (Join-Path $ModRoot "COMSPECExtension\bin\publish\COMSPECExtension_x64.dll"),
    (Join-Path $ModRoot "COMSPECExtension\bin\Release\net8.0\win-x64\publish\COMSPECExtension_x64.dll"),
    (Join-Path $SourceMod "net8.0\win-x64\native\COMSPECExtension_x64.dll")
)

$dll = $null
foreach ($c in $dllCandidates) {
    if (Test-Path -LiteralPath $c) {
        $len = (Get-Item -LiteralPath $c).Length
        # Stub managé Native AOT manqué ≈ 30-80 Ko ; vraie DLL ≈ 5 Mo.
        if ($len -ge 500000) {
            $dll = $c
            break
        }
        Write-Warn "DLL trop petite ignorée ($len o) : $c"
    }
}
if (-not $dll) {
    Write-Fail "Aucune COMSPECExtension_x64.dll Native AOT (>= 500 Ko) trouvée. Lancez build_mod.bat / dotnet publish."
}

$pboMain = Join-Path $SourceMod "addons\main.pbo"
$pboConnect = Join-Path $SourceMod "addons\connect.pbo"
if (-not (Test-Path -LiteralPath $pboMain)) { Write-Fail "Manquant : $pboMain - rebuild AddonBuilder." }
if (-not (Test-Path -LiteralPath $pboConnect)) { Write-Fail "Manquant : $pboConnect - rebuild AddonBuilder." }

$modCpp = Join-Path $SourceMod "mod.cpp"
if (-not (Test-Path -LiteralPath $modCpp)) {
    $alt = Join-Path $ModRoot "mod.cpp"
    if (Test-Path -LiteralPath $alt) { $modCpp = $alt } else { Write-Fail "mod.cpp introuvable." }
}

Write-Step "Nettoyage staging : $OutDir"
if (Test-Path -LiteralPath $OutDir) {
    Remove-Item -LiteralPath $OutDir -Recurse -Force
}
New-Item -ItemType Directory -Path (Join-Path $OutDir "addons") -Force | Out-Null

Write-Step "Copie meta / crédits"
Copy-Item -LiteralPath $modCpp -Destination (Join-Path $OutDir "mod.cpp") -Force
$metaCpp = Join-Path $SourceMod "meta.cpp"
if (Test-Path -LiteralPath $metaCpp) {
    Copy-Item -LiteralPath $metaCpp -Destination (Join-Path $OutDir "meta.cpp") -Force
}
$credits = Join-Path $SourceMod "CREDITS.md"
if (Test-Path -LiteralPath $credits) {
    Copy-Item -LiteralPath $credits -Destination (Join-Path $OutDir "CREDITS.md") -Force
}

Write-Step "Copie PBO"
Copy-Item -LiteralPath $pboMain -Destination (Join-Path $OutDir "addons\main.pbo") -Force
Copy-Item -LiteralPath $pboConnect -Destination (Join-Path $OutDir "addons\connect.pbo") -Force

# Signatures BI optionnelles (si présentes à côté des PBO)
Get-ChildItem -LiteralPath (Join-Path $SourceMod "addons") -Filter "*.bisign" -ErrorAction SilentlyContinue |
    ForEach-Object { Copy-Item -LiteralPath $_.FullName -Destination (Join-Path $OutDir "addons\$($_.Name)") -Force }
Get-ChildItem -LiteralPath (Join-Path $SourceMod "keys") -Filter "*.bikey" -ErrorAction SilentlyContinue |
    ForEach-Object {
        $keysOut = Join-Path $OutDir "keys"
        if (-not (Test-Path -LiteralPath $keysOut)) { New-Item -ItemType Directory -Path $keysOut | Out-Null }
        Copy-Item -LiteralPath $_.FullName -Destination (Join-Path $keysOut $_.Name) -Force
    }

# Logo / picture référencé par mod.cpp (optionnel)
foreach ($picName in @("gotak.png", "logo.paa", "overview.paa")) {
    $pic = Join-Path $SourceMod $picName
    $picAddons = Join-Path $SourceMod "addons\$picName"
    if (Test-Path -LiteralPath $pic) {
        Copy-Item -LiteralPath $pic -Destination (Join-Path $OutDir $picName) -Force
    } elseif (Test-Path -LiteralPath $picAddons) {
        Copy-Item -LiteralPath $picAddons -Destination (Join-Path $OutDir "addons\$picName") -Force
    }
}

Write-Step "Copie DLL Native AOT depuis $dll"
Copy-Item -LiteralPath $dll -Destination (Join-Path $OutDir "COMSPECExtension_x64.dll") -Force
$dllSize = (Get-Item -LiteralPath (Join-Path $OutDir "COMSPECExtension_x64.dll")).Length
Write-Step ("DLL : {0:N0} octets" -f $dllSize)

# Garde-fous : rien de sensible / source dans le staging
$forbidden = @(
    "*.pdb", "*.cs", "*.exp", "*.lib", "*.deps.json",
    "*.sqf", "*.hpp", "XEH_*.sqf", "config.cpp", "`$PBOPREFIX`$"
)
$bad = @()
Get-ChildItem -LiteralPath $OutDir -Recurse -File | ForEach-Object {
    $rel = $_.FullName.Substring($OutDir.Length).TrimStart('\', '/')
    $name = $_.Name
    if ($name -match '\.(pdb|cs|exp|lib|sqf|hpp)$') { $bad += $rel }
    if ($name -eq "config.cpp" -or $name -eq "`$PBOPREFIX`$") { $bad += $rel }
    if ($rel -match '(?i)(^|[/\\])(net8\.0|Sources|docs|obj|bin)([/\\]|$)') { $bad += $rel }
    if ($name -eq "STEAM_DESCRIPTION.md" -or $name -eq "Extension.cs") { $bad += $rel }
    if ($name -eq ".env" -or $name -like "*.biprivatekey") { $bad += $rel }
}
if ($bad.Count -gt 0) {
    Write-Fail ("Fichiers interdits détectés dans le pack :`n  - " + ($bad -join "`n  - "))
}

# Rapport
Write-Host ""
Write-Host "========== PACK WORKSHOP OK ==========" -ForegroundColor Green
Write-Host "  Sortie : $OutDir"
Write-Host "  Contenu autorisé :"
Get-ChildItem -LiteralPath $OutDir -Recurse -File | ForEach-Object {
    $rel = $_.FullName.Substring($OutDir.Length).TrimStart('\', '/')
    Write-Host ("    {0,-50} {1,10:N0} o" -f $rel, $_.Length)
}
Write-Host ""
Write-Host "  À NE PAS publier depuis le dossier de travail :"
Write-Host "    - addons/connect/*.sqf|*.hpp (sources) - seulement connect.pbo"
Write-Host "    - net8.0/, *.pdb, COMSPECExtension/, mod/Sources/"
Write-Host "    - STEAM_DESCRIPTION.md, docs/, SECURITY.md, PACKAGING.md (dev)"
Write-Host "======================================"

if ($Zip) {
    $zipPath = Join-Path (Split-Path $OutDir -Parent) "COMSPECOverwatch-workshop.zip"
    if (Test-Path -LiteralPath $zipPath) { Remove-Item -LiteralPath $zipPath -Force }
    Write-Step "Archive : $zipPath"
    # Compresser le dossier @COMSPECOverwatch pour que le zip s'extraisse correctement
    Compress-Archive -Path $OutDir -DestinationPath $zipPath -CompressionLevel Optimal
    Write-Host "  ZIP : $zipPath" -ForegroundColor Green
}

Write-Host ""
Write-Host "Prochaine etape : publier le contenu de $OutDir via Publisher Arma / Steam Workshop."
Write-Host 'Voir PACKAGING.md et SECURITY.md dans le dossier mod.'
