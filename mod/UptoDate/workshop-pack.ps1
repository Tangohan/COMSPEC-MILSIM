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

  Ce pack hérité n'a plus d'objet Workshop (ARCH-021). L'objet 3684656708 est l'ATAK natif,
  publié seulement depuis mod/COMSPEC_ATAK_Native. Le script s'arrête avec le code 1 :
    - si le meta.cpp du pack vise l'objet 3684656708 ;
    - si la DLL retenue annonce une ProductVersion sous 2.0.96 (plancher SEC-001,
      docs/PLANCHER-VERSION-DLL.md) ou une version illisible.

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

# Objet Workshop de l'ATAK natif (App\Support\SteamWorkshop::ATAK_NATIVE_ID) : ce pack ne doit jamais le viser.
$AtakNativeWorkshopId = '3684656708'
# Plancher de version de COMSPEC Link (SEC-001) : les DLL plus anciennes joignent les secrets Athena à tout hôte
# qu'un script leur demande, et Athena les refuse.
$MinLinkVersion = [version]'2.0.96'

function Write-Step([string] $msg) { Write-Host "[PACK] $msg" -ForegroundColor Cyan }
function Write-Fail([string] $msg) {
    Write-Host "[FAIL] $msg" -ForegroundColor Red
    if (Test-Path -LiteralPath $OutDir) {
        Write-Host "[FAIL] Ne publiez pas le contenu de $OutDir : ce n'est pas un pack validé par cette exécution." -ForegroundColor Red
    }
    exit 1
}
function Write-Warn([string] $msg) { Write-Host "[WARN] $msg" -ForegroundColor Yellow }

if (-not (Test-Path -LiteralPath $SourceMod)) {
    Write-Fail "Source introuvable : $SourceMod"
}

# meta.cpp : le Publisher d'Arma 3 garde dans ce fichier le numéro de l'objet qu'il met à jour (mod/docs/PUBLICATION.md).
$metaCpp = Join-Path $SourceMod "meta.cpp"
if (Test-Path -LiteralPath $metaCpp) {
    $metaText = [string](Get-Content -LiteralPath $metaCpp -Raw)
    if ($metaText -match "\b$AtakNativeWorkshopId\b") {
        Write-Fail ("$metaCpp vise l'objet Workshop $AtakNativeWorkshopId, qui est l'ATAK natif. Ce pack hérité " +
            "n'a plus d'objet Workshop : retirez publishedid de ce meta.cpp. L'ATAK natif se publie seulement " +
            "depuis mod\COMSPEC_ATAK_Native.")
    }
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

# Version de la DLL retenue (SEC-001). Celle de @COMSPECOverwatch vient du dernier build_mod.bat ; git ne la suit
# plus, mais une copie plus ancienne peut y rester (une 2.0.57 dans un clone d'avant ARCH-021).
$dllProductVersion = [string]([System.Diagnostics.FileVersionInfo]::GetVersionInfo((Get-Item -LiteralPath $dll).FullName).ProductVersion)
$dllVersion = $null
if ($dllProductVersion -match '^\s*(\d+\.\d+(\.\d+){0,2})') { $dllVersion = [version]($Matches[1]) }
if ($null -eq $dllVersion) {
    Write-Fail "Version illisible (ProductVersion « $dllProductVersion ») pour $dll : DLL refusée, plancher $MinLinkVersion (SEC-001)."
}
if ($dllVersion -lt $MinLinkVersion) {
    Write-Fail ("DLL COMSPEC Link $dllProductVersion refusée : version sous le plancher $MinLinkVersion (SEC-001), " +
        "fichier $dll. Lancez build_mod.bat pour la remplacer par une DLL fraîche.")
}
Write-Step "DLL retenue : $dll (version $dllProductVersion)"

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

# docs/ d'atelier : ne pas les embarquer (le garde-fou refuse ce dossier).
$docsSrc = Join-Path $ModRoot "docs"
if (Test-Path -LiteralPath $docsSrc) {
    Write-Warn "docs/ d'atelier ignore (non publie sur Steam)."
}
$docsIndex = Join-Path $SourceMod "DOCS.md"
if (Test-Path -LiteralPath $docsIndex) {
    Copy-Item -LiteralPath $docsIndex -Destination (Join-Path $OutDir "DOCS.md") -Force
}
$changelog = Join-Path $SourceMod "CHANGELOG.md"
if (Test-Path -LiteralPath $changelog) {
    Copy-Item -LiteralPath $changelog -Destination (Join-Path $OutDir "CHANGELOG.md") -Force
}

Write-Step "Copie PBO"
Copy-Item -LiteralPath $pboMain -Destination (Join-Path $OutDir "addons\main.pbo") -Force
Copy-Item -LiteralPath $pboConnect -Destination (Join-Path $OutDir "addons\connect.pbo") -Force
$pboAthena = Join-Path $SourceMod "addons\atak_athena.pbo"
if (Test-Path -LiteralPath $pboAthena) {
    Copy-Item -LiteralPath $pboAthena -Destination (Join-Path $OutDir "addons\atak_athena.pbo") -Force
    Write-Host "  + atak_athena.pbo (pont cTab / ATAK Enhanced)"
} else {
    Write-Host "  (atak_athena.pbo absent — pont cTab non packagé)"
}
$pboMavik = Join-Path $SourceMod "addons\mavik_compat.pbo"
if (Test-Path -LiteralPath $pboMavik) {
    Copy-Item -LiteralPath $pboMavik -Destination (Join-Path $OutDir "addons\mavik_compat.pbo") -Force
    Write-Host "  + mavik_compat.pbo (filet Mavic / settings CBA)"
} else {
    Write-Host "  (mavik_compat.pbo absent — compat Mavic non packagée)"
}
$pboSseAce = Join-Path $SourceMod "addons\sse_ace.pbo"
if (Test-Path -LiteralPath $pboSseAce) {
    Copy-Item -LiteralPath $pboSseAce -Destination (Join-Path $OutDir "addons\sse_ace.pbo") -Force
    Write-Host "  + sse_ace.pbo (interaction ACE SSE)"
}

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
foreach ($picName in @("gotak.png", "logo.paa", "logoSmall.paa", "overview.paa")) {
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
Write-Host "Ce pack hérité n'a plus d'objet Workshop : ne publiez pas $OutDir sur l'objet $AtakNativeWorkshopId (ATAK natif)."
Write-Host "L'ATAK natif se publie seulement depuis mod\COMSPEC_ATAK_Native (dossier @COMSPEC_ATAK_Native)."
Write-Host 'Voir PACKAGING.md et SECURITY.md dans le dossier mod.'
