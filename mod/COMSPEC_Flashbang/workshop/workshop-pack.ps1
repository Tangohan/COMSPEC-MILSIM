<#
.SYNOPSIS
  Assemble le dossier Workshop propre de COMSPEC Flashbang et la note de version.

.DESCRIPTION
  Source : mod\COMSPEC_Flashbang\@COMSPEC_Flashbang (sortie de build_mod.bat).
  Sortie : mod\COMSPEC_Flashbang\publisher\@COMSPEC_Flashbang (ignore par Git), a donner a PublisherCmd :
    addons\comspec_flashbang_main.pbo + .bisign, keys\comspec_flashbang.bikey, mod.cpp, logo.paa.
  Jamais : Sources, tools, missions, workshop, README, build_mod.bat, *.biprivatekey, sqf/hpp/config.cpp.

  Verifications (echec = rien n'est publiable) :
    - PBO present et plus recent que toutes les sources (Sources\addons) ;
    - signature presente, plus recente que le PBO, validee par DSCheckSignatures -deep avec la cle du pack ;
    - aucune cle privee ni source dans le pack ; taille et empreinte SHA-256 de chaque fichier affichees.
  Ecrit aussi publisher\change_note.txt : premier bloc [h1] de workshop\STEAM_CHANGELOG.txt (limite 8000 octets).

  Ne reconstruit pas le PBO : lancer d'abord  cmd /c "<depot>\mod\COMSPEC_Flashbang\build_mod.bat".
  Ne publie rien.

.EXAMPLE
  powershell -NoProfile -ExecutionPolicy Bypass -File mod\COMSPEC_Flashbang\workshop\workshop-pack.ps1
#>
[CmdletBinding()]
param(
    [string] $OutDir = ""
)

$ErrorActionPreference = "Stop"
$ModRoot = Split-Path $PSScriptRoot -Parent
$Src = Join-Path $ModRoot "@COMSPEC_Flashbang"
$Publisher = Join-Path $ModRoot "publisher"
if (-not $OutDir) { $OutDir = Join-Path $Publisher "@COMSPEC_Flashbang" }
$Pbo = "comspec_flashbang_main.pbo"
$KeyName = "comspec_flashbang"

function Step([string] $m) { Write-Host "[PACK] $m" -ForegroundColor Cyan }
function Fail([string] $m) { Write-Host "[FAIL] $m" -ForegroundColor Red; exit 1 }

# 1. Sortie de build complete et a jour.
$pboSrc = Join-Path $Src "addons\$Pbo"
$sig = Get-ChildItem -LiteralPath (Join-Path $Src "addons") -Filter "$Pbo.*.bisign" -File -ErrorAction SilentlyContinue | Select-Object -First 1
$key = Join-Path $Src "keys\$KeyName.bikey"
foreach ($p in @($pboSrc, $key, (Join-Path $Src "mod.cpp"), (Join-Path $Src "logo.paa"))) {
    if (-not (Test-Path -LiteralPath $p)) { Fail "Manquant : $p (lancer build_mod.bat)." }
}
if (-not $sig) { Fail "Signature $Pbo.*.bisign absente (lancer build_mod.bat)." }
$pboItem = Get-Item -LiteralPath $pboSrc
$newest = Get-ChildItem -LiteralPath (Join-Path $ModRoot "Sources\addons") -Recurse -File |
    Sort-Object LastWriteTime -Descending | Select-Object -First 1
if ($newest.LastWriteTime -gt $pboItem.LastWriteTime) {
    Fail "PBO plus ancien que $($newest.FullName) : relancer build_mod.bat."
}
if ($sig.LastWriteTime -lt $pboItem.LastWriteTime) { Fail "Signature plus ancienne que le PBO : relancer build_mod.bat." }

# 2. Copie du strict necessaire.
Step "Pack : $OutDir"
if (Test-Path -LiteralPath $OutDir) { Remove-Item -LiteralPath $OutDir -Recurse -Force }
New-Item -ItemType Directory -Force -Path (Join-Path $OutDir "addons"), (Join-Path $OutDir "keys") | Out-Null
Copy-Item -LiteralPath $pboSrc -Destination (Join-Path $OutDir "addons\$Pbo")
Copy-Item -LiteralPath $sig.FullName -Destination (Join-Path $OutDir "addons\$($sig.Name)")
Copy-Item -LiteralPath $key -Destination (Join-Path $OutDir "keys\$KeyName.bikey")
Copy-Item -LiteralPath (Join-Path $Src "mod.cpp") -Destination (Join-Path $OutDir "mod.cpp")
Copy-Item -LiteralPath (Join-Path $Src "logo.paa") -Destination (Join-Path $OutDir "logo.paa")

# 3. Signature verifiee avec la cle du pack.
$tools = $env:ARMA3TOOLS
if (-not $tools) {
    foreach ($d in @("C", "D", "E", "F", "G", "H")) {
        $c = "${d}:\SteamLibrary\steamapps\common\Arma 3 Tools"
        if (Test-Path "$c\DSSignFile\DSCheckSignatures.exe") { $tools = $c; break }
    }
}
if (-not $tools) { Fail "Arma 3 Tools introuvable (DSCheckSignatures)." }
& (Join-Path $tools "DSSignFile\DSCheckSignatures.exe") -deep (Join-Path $OutDir "addons") (Join-Path $OutDir "keys") | Out-Host
if ($LASTEXITCODE -ne 0) { Fail "DSCheckSignatures : la signature ne correspond pas a keys\$KeyName.bikey." }
Step "Signature valide (DSCheckSignatures -deep)."

# 4. Rien d'interdit.
$bad = Get-ChildItem -LiteralPath $OutDir -Recurse -File | Where-Object {
    $_.Extension -match '^\.(sqf|hpp|cpp|biprivatekey|md|bat|ps1|py|png|p3d)$' -and $_.Name -ne "mod.cpp"
}
if ($bad) { Fail ("Fichiers interdits dans le pack :`n  " + (($bad | ForEach-Object FullName) -join "`n  ")) }

# 5. Note de version : premier bloc [h1] du changelog.
$log = Get-Content -LiteralPath (Join-Path $PSScriptRoot "STEAM_CHANGELOG.txt") -Raw -Encoding UTF8
$log = $log -replace "`r`n", "`n"
$blocks = @([regex]::Split($log, "(?m)^(?=\[h1\])") | Where-Object { $_.Trim() })
$note = $blocks[0].Trim() + "`n"
$utf8 = New-Object System.Text.UTF8Encoding($false)
if ($utf8.GetByteCount($note) -gt 8000) { Fail "Note de version trop longue ($($utf8.GetByteCount($note)) octets > 8000)." }
$notePath = Join-Path $Publisher "change_note.txt"
[System.IO.File]::WriteAllText($notePath, $note, $utf8)
Step "Note de version : $notePath ($($utf8.GetByteCount($note)) octets)"

# 6. Bilan.
Write-Host ""
Write-Host "========== PACK WORKSHOP OK ==========" -ForegroundColor Green
$total = 0
Get-ChildItem -LiteralPath $OutDir -Recurse -File | ForEach-Object {
    $rel = $_.FullName.Substring($OutDir.Length).TrimStart('\')
    $h = (Get-FileHash -LiteralPath $_.FullName -Algorithm SHA256).Hash.Substring(0, 16)
    $total += $_.Length
    Write-Host ("  {0,-58} {1,10:N0} o  sha256 {2}" -f $rel, $_.Length, $h)
}
Write-Host ("  Total : {0:N0} o" -f $total)
$id = (Get-Content -LiteralPath (Join-Path $PSScriptRoot "STEAM_ITEM_ID") -Raw).Trim()
Write-Host ""
Write-Host "Commande de mise a jour (NE PAS lancer sans accord de TTRD) :"
Write-Host "  & `"F:\SteamLibrary\steamapps\common\Arma 3 Tools\Publisher\PublisherCmd.exe`" update /id:$id `"/changeNoteFile:$notePath`" `"/path:$OutDir`" /nologo"
