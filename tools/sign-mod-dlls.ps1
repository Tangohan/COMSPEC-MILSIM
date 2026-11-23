<#
.SYNOPSIS
    Signe (Authenticode) les deux DLL d'extension du mod ATAK natif avec un certificat de signature de code.

.DESCRIPTION
    Une DLL non signée qui charge du code et parle à un service en ligne est souvent signalée à tort par les antivirus
    (« item being flagged by AV » sur le Workshop). La signer avec un certificat reconnu est la mesure qui réduit le
    plus ces faux positifs. À lancer APRÈS la compilation des DLL et AVANT la construction de l'archive : une signature
    change l'empreinte SHA-256 du fichier.

    Le certificat et son mot de passe ne sont JAMAIS dans le dépôt :
      - fichier PFX : -Certificate <chemin> et mot de passe dans la variable d'environnement COMSPEC_SIGN_PFX_PASSWORD
        (jamais en argument de ligne de commande : il resterait dans l'historique) ;
      - ou certificat du magasin Windows / jeton matériel (EV) : -Thumbprint <empreinte du certificat>.

.EXAMPLE
    $env:COMSPEC_SIGN_PFX_PASSWORD = Read-Host -AsSecureString | ConvertFrom-SecureString -AsPlainText
    .\tools\sign-mod-dlls.ps1 -Certificate D:\secrets\comspec-codesign.pfx

.EXAMPLE
    .\tools\sign-mod-dlls.ps1 -Thumbprint 0123456789ABCDEF0123456789ABCDEF01234567
#>
[CmdletBinding()]
param(
    [string]$Certificate,
    [string]$Thumbprint,
    [string]$TimestampUrl = 'http://timestamp.digicert.com',
    [string]$ModDir = (Join-Path $PSScriptRoot '..\mod\COMSPEC_ATAK_Native\@COMSPEC_ATAK_Native'),
    [string]$SignTool
)

$ErrorActionPreference = 'Stop'

if (-not $Certificate -and -not $Thumbprint) {
    throw 'Indiquez -Certificate <fichier .pfx> (mot de passe dans COMSPEC_SIGN_PFX_PASSWORD) ou -Thumbprint <empreinte>.'
}
if ($Certificate -and -not (Test-Path -LiteralPath $Certificate)) {
    throw "Certificat introuvable : $Certificate"
}

# signtool.exe : argument, sinon PATH, sinon le Kit Windows le plus récent.
if (-not $SignTool) {
    $cmd = Get-Command signtool.exe -ErrorAction SilentlyContinue
    if ($cmd) { $SignTool = $cmd.Source }
}
if (-not $SignTool) {
    $kits = Join-Path ${env:ProgramFiles(x86)} 'Windows Kits\10\bin'
    $found = Get-ChildItem -Path $kits -Recurse -Filter signtool.exe -ErrorAction SilentlyContinue |
        Where-Object { $_.FullName -match '\\x64\\' } | Sort-Object FullName -Descending | Select-Object -First 1
    if ($found) { $SignTool = $found.FullName }
}
if (-not $SignTool -or -not (Test-Path -LiteralPath $SignTool)) {
    throw 'signtool.exe introuvable : installez le Kit de développement Windows (composant « Outils de signature ») ou passez -SignTool.'
}

$dlls = @('COMSPECATAKNativeExtension_x64.dll', 'COMSPECExtension_x64.dll') | ForEach-Object { Join-Path $ModDir $_ }
foreach ($d in $dlls) {
    if (-not (Test-Path -LiteralPath $d)) { throw "DLL introuvable : $d" }
}

foreach ($d in $dlls) {
    Write-Host "Signature de $(Split-Path $d -Leaf)…"
    $sigArgs = @('sign', '/fd', 'SHA256', '/tr', $TimestampUrl, '/td', 'SHA256')
    if ($Thumbprint) {
        $sigArgs += @('/sha1', $Thumbprint)
    } else {
        $sigArgs += @('/f', (Resolve-Path -LiteralPath $Certificate).Path)
        if ($env:COMSPEC_SIGN_PFX_PASSWORD) { $sigArgs += @('/p', $env:COMSPEC_SIGN_PFX_PASSWORD) }
    }
    $sigArgs += $d
    & $SignTool @sigArgs
    if ($LASTEXITCODE -ne 0) { throw "Échec de la signature de $d (code $LASTEXITCODE)." }
}

Write-Host ''
Write-Host 'Vérification :'
foreach ($d in $dlls) {
    $sig = Get-AuthenticodeSignature -LiteralPath $d
    if ($sig.Status -ne 'Valid') { throw "Signature invalide pour $d : $($sig.Status)" }
    $hash = (Get-FileHash -LiteralPath $d -Algorithm SHA256).Hash.ToLower()
    '{0}  signé par « {1} »  SHA-256 {2}' -f (Split-Path $d -Leaf), $sig.SignerCertificate.Subject, $hash
}
Write-Host ''
Write-Host 'À faire ensuite : mettre à jour docs/securite/empreintes-atak-natif.md, construire l''archive, la publier sur /modpacks.'
