# Signe le PBO de COMSPEC Flashbang (DSSignFile) et pose la clé publique dans @COMSPEC_Flashbang\keys.
# Même mécanisme que mod\COMSPEC_ATAK_Native\tools\sign_pbos.ps1, avec une clé propre au mod.
# Clé STABLE : la même clé privée doit servir à toutes les versions, sinon les serveurs doivent changer de .bikey.
# Elle vit HORS du dépôt (un worktree supprimé ne l'emporte pas) :
#   -SigningDir, sinon $env:COMSPEC_FB_SIGNING_DIR, sinon %APPDATA%\COMSPEC\signing\flashbang.
# Ancien emplacement (mod\COMSPEC_Flashbang\signing\, ignoré par Git) : utilisé s'il contient déjà la clé.
# Première exécution sans clé : la paire est créée une seule fois. Le script ne lit ni n'affiche jamais la clé privée.
param(
    [string]$ModRoot = (Split-Path $PSScriptRoot -Parent),
    [string]$SigningDir = ""
)

$ErrorActionPreference = "Stop"
$tools = $env:ARMA3TOOLS
if (-not $tools) {
    foreach ($d in @("C","D","E","F","G","H")) {
        $cand = "${d}:\SteamLibrary\steamapps\common\Arma 3 Tools"
        if (Test-Path "$cand\DSSignFile\DSSignFile.exe") { $tools = $cand; break }
    }
}
if (-not $tools) { throw "Arma 3 Tools introuvable (DSSignFile)." }

$create = Join-Path $tools "DSSignFile\DSCreateKey.exe"
$sign = Join-Path $tools "DSSignFile\DSSignFile.exe"
$out = Join-Path $ModRoot "@COMSPEC_Flashbang"
$addons = Join-Path $out "addons"
$keysDir = Join-Path $out "keys"
$keyName = "comspec_flashbang"
$legacyDir = Join-Path $ModRoot "signing"
if ($SigningDir) { $signingDir = $SigningDir }
elseif ($env:COMSPEC_FB_SIGNING_DIR) { $signingDir = $env:COMSPEC_FB_SIGNING_DIR }
elseif (Test-Path (Join-Path $legacyDir "$keyName.biprivatekey")) { $signingDir = $legacyDir }
else { $signingDir = Join-Path $env:APPDATA "COMSPEC\signing\flashbang" }
Write-Host "[INFO] Dossier de la clé : $signingDir"
$priv = Join-Path $signingDir "$keyName.biprivatekey"
$pub = Join-Path $signingDir "$keyName.bikey"

New-Item -ItemType Directory -Force -Path $signingDir | Out-Null
New-Item -ItemType Directory -Force -Path $keysDir | Out-Null

if (-not (Test-Path $priv)) {
    Push-Location $signingDir
    try {
        & $create $keyName
        if ($LASTEXITCODE -ne 0) { throw "DSCreateKey a échoué ($LASTEXITCODE)." }
    } finally {
        Pop-Location
    }
    if (-not (Test-Path $priv)) { throw "Clé privée non créée dans signing\" }
    Write-Host "[OK] Paire de clés créée : $keyName"
} else {
    Write-Host "[INFO] Réutilisation de la clé existante : $keyName"
}

Copy-Item -Force $pub (Join-Path $keysDir "$keyName.bikey")

$pbos = Get-ChildItem -LiteralPath $addons -Filter "*.pbo" -File
if ($pbos.Count -lt 1) { throw "Aucun PBO dans $addons" }
foreach ($pbo in $pbos) {
    Get-ChildItem -LiteralPath $addons -Filter "$($pbo.Name).*.bisign" -ErrorAction SilentlyContinue | Remove-Item -Force
    & $sign $priv $pbo.FullName
    if ($LASTEXITCODE -ne 0) { throw "DSSignFile a échoué pour $($pbo.Name) ($LASTEXITCODE)." }
    $bisign = Get-ChildItem -LiteralPath $addons -Filter "$($pbo.Name).*.bisign" | Select-Object -First 1
    if (-not $bisign) { throw "Signature manquante pour $($pbo.Name)" }
    Write-Host "[OK] Signé $($pbo.Name) -> $($bisign.Name)"
}
Write-Host "[OK] Clé publique : keys\$keyName.bikey"
