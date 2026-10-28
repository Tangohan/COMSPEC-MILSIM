# Signe les PBO du pack ATAK (DSSignFile) et pose la clé publique dans keys/.
# La clé privée reste dans signing/ (hors Git). Première exécution : crée la paire.
param(
    [string]$ModRoot = (Split-Path $PSScriptRoot -Parent)
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
$out = Join-Path $ModRoot "@COMSPEC_ATAK_Native"
$addons = Join-Path $out "addons"
$keysDir = Join-Path $out "keys"
$signingDir = Join-Path $ModRoot "signing"
$keyName = "comspec_atak_native"
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
    if (-not (Test-Path $priv)) { throw "Clé privée non créée : $priv" }
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
Write-Host "[OK] $($pbos.Count) PBO signés."
