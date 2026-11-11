<#
.SYNOPSIS
    Test automatique en jeu du mod COMSPEC Flashbang : lance Arma 3 (profil COMSPECTest, fenêtré, sans son) sur la mission
    missions\COMSPEC_FB_Autotest.Stratis avec CBA, ACE3 et le build @COMSPEC_Flashbang, attend AUTOTEST|FIN dans le RPT,
    ferme le jeu, rend Arma3.cfg, retire la copie de mission et résume les lignes AUTOTEST (OK / KO / SKIP).
    Codes de sortie : 0 tout est bon, 1 KO ou erreur de script COMSPEC FB, 2 mission non terminée, 3 Arma déjà lancé
    (rien n'est fait, le jeu en cours n'est jamais touché), 4 environnement.
.EXAMPLE
    powershell -NoProfile -ExecutionPolicy Bypass -File mod\COMSPEC_Flashbang\tools\ingame_test.ps1
.EXAMPLE
    powershell -NoProfile -ExecutionPolicy Bypass -File mod\COMSPEC_Flashbang\tools\ingame_test.ps1 -SansAce
#>
[CmdletBinding()]
param(
    [string]$ArmaExe = 'F:\SteamLibrary\steamapps\common\Arma 3\arma3_x64.exe',
    [string]$WorkshopDir = 'F:\SteamLibrary\steamapps\workshop\content\107410',
    [string]$BuildDir = '',
    [switch]$SansAce,
    [int]$DureeMaxMinutes = 8
)
$ErrorActionPreference = 'Stop'
$Profil = 'COMSPECTest'
$NomMission = 'COMSPEC_FB_Autotest.Stratis'
$RptDir = Join-Path $env:LOCALAPPDATA 'Arma 3'
$Docs = [Environment]::GetFolderPath('MyDocuments')
$ModRoot = Split-Path -Parent $PSScriptRoot
$MissionSrc = Join-Path $ModRoot "missions\$NomMission"
if (-not $BuildDir) { $BuildDir = Join-Path $ModRoot '@COMSPEC_Flashbang' }

function Sortir([int]$Code, [string]$Msg) { Write-Host $Msg; exit $Code }
$dejaLa = @(Get-Process -Name 'arma3', 'arma3_x64', 'arma3battleye' -ErrorAction SilentlyContinue)
if ($dejaLa.Count -gt 0) { Sortir 3 ("Arma est déjà lancé (PID {0}) : rien n'est fait." -f (($dejaLa | ForEach-Object { $_.Id }) -join ', ')) }
$mods = @((Join-Path $WorkshopDir '450814997'))
if (-not $SansAce) { $mods += (Join-Path $WorkshopDir '463939057') }
$mods += $BuildDir
foreach ($m in $mods) { if (-not (Test-Path -LiteralPath (Join-Path $m 'addons'))) { Sortir 4 "Mod introuvable : $m" } }
if (-not (Test-Path -LiteralPath (Join-Path $BuildDir 'addons\comspec_flashbang_main.pbo'))) { Sortir 4 'Build absent : lancer build_mod.bat' }

$stamp = Get-Date -Format 'yyyyMMdd_HHmmss'
$Sortie = Join-Path $env:TEMP "comspec_autotest\flashbang_$stamp"
[void](New-Item -ItemType Directory -Force -Path $Sortie)
# Arma3.cfg est partagé entre profils : sauvegardé, rendu s'il a changé.
$cfgJeu = Join-Path $Docs 'Arma 3\Arma3.cfg'
$cfgSave = Join-Path $Sortie 'Arma3.cfg'
if (Test-Path -LiteralPath $cfgJeu) { Copy-Item -LiteralPath $cfgJeu -Destination $cfgSave }
# playMission lit le dossier Missions du jeu : copie temporaire, retirée à la fin.
$missionJeu = Join-Path (Split-Path -Parent $ArmaExe) "Missions\$NomMission"
if (Test-Path -LiteralPath $missionJeu) { Sortir 4 "$missionJeu existe déjà : le retirer d'abord." }
Copy-Item -LiteralPath $MissionSrc -Destination $missionJeu -Recurse

$arguments = @('-skipIntro', '-noSplash', '-world=empty', '-showScriptErrors', '-noPause', '-noPauseAudio', "-name=$Profil", '-window', '-noSound',
    ('"-mod=' + ($mods -join ';') + '"'), "-init=playMission['','$NomMission']") -join ' '
Write-Host "Lancement : $ArmaExe $arguments"
$debut = Get-Date
$psi = New-Object Diagnostics.ProcessStartInfo
$psi.FileName = $ArmaExe
$psi.WorkingDirectory = Split-Path -Parent $ArmaExe
$psi.Arguments = $arguments
$psi.UseShellExecute = $false
$parent = [Diagnostics.Process]::Start($psi)
$script:notrePid = $parent.Id
function Get-NotreArma {
    # Le processus lancé, ou celui que Steam a relancé : un Arma démarré après ce lancement (aucun ne tournait au
    # départ, sinon le test aurait refusé de démarrer). Son identifiant est retenu dès qu'il est trouvé.
    $p = Get-Process -Id $script:notrePid -ErrorAction SilentlyContinue
    if ($p -and -not $p.HasExited) { return $p }
    $c = @(Get-Process -Name 'arma3_x64' -ErrorAction SilentlyContinue | Where-Object {
        $t = $null; try { $t = $_.StartTime } catch { }
        (-not $t) -or ($t -ge $debut.AddSeconds(-5))
    })
    if ($c.Count -gt 0) { $script:notrePid = $c[0].Id; return $c[0] }
    return $null
}
# Captures de la fenêtre du jeu (calque blanc et tache rémanente compris, que la commande screenshot n'enregistre pas) :
# la mission écrit AUTOTEST|SHOT|nom, le script capture la zone de la fenêtre en JPEG dans le dossier du passage.
Add-Type -ReferencedAssemblies System.Drawing -TypeDefinition @'
using System; using System.Drawing; using System.Drawing.Imaging; using System.Runtime.InteropServices;
public static class FbCap {
    [StructLayout(LayoutKind.Sequential)] struct RECT { public int L, T, R, B; }
    [DllImport("user32.dll")] static extern bool GetWindowRect(IntPtr h, out RECT r);
    [DllImport("user32.dll")] static extern bool SetForegroundWindow(IntPtr h);
    [DllImport("user32.dll")] static extern bool SetProcessDPIAware();
    public static string Shot(IntPtr h, string path) {
        SetProcessDPIAware(); SetForegroundWindow(h);
        RECT r; if (!GetWindowRect(h, out r)) return "fenêtre introuvable";
        int w = r.R - r.L, hh = r.B - r.T; if (w < 50 || hh < 50) return "fenêtre trop petite";
        using (var bmp = new Bitmap(w, hh)) using (var g = Graphics.FromImage(bmp)) {
            g.CopyFromScreen(r.L, r.T, 0, 0, new Size(w, hh)); bmp.Save(path, ImageFormat.Jpeg);
        }
        return "ok";
    }
}
'@
$dossierCaptures = Join-Path $Sortie 'captures'
[void](New-Item -ItemType Directory -Force -Path $dossierCaptures)
$rpt = $null; $etat = 'EN_COURS'; $finA = $null; $vuA = $null; $pos = [int64]0; $reste = ''
try {
    while ($true) {
        Start-Sleep -Milliseconds 150
        if (-not $rpt) { $rpt = Get-ChildItem -LiteralPath $RptDir -Filter '*.rpt' | Where-Object { $_.LastWriteTime -ge $debut } | Sort-Object LastWriteTime -Descending | Select-Object -First 1 }
        $p = Get-NotreArma
        if ($rpt) {
            # Seulement les octets nouveaux du RPT.
            $fs = [IO.File]::Open($rpt.FullName, 'Open', 'Read', 'ReadWrite,Delete')
            try {
                $neuf = ''
                if ($fs.Length -gt $pos) {
                    [void]$fs.Seek($pos, 'Begin'); $buf = New-Object byte[] ($fs.Length - $pos)
                    $n = $fs.Read($buf, 0, $buf.Length); $pos += $n; $neuf = [Text.Encoding]::UTF8.GetString($buf, 0, $n)
                }
            } finally { $fs.Dispose() }
            $tout = $reste + $neuf; $i = $tout.LastIndexOf("`n")
            if ($i -ge 0) {
                $reste = $tout.Substring($i + 1)
                foreach ($l in $tout.Substring(0, $i).Split("`n")) {
                    if ($l -match 'AUTOTEST\|SHOT\|([A-Za-z0-9_]+)' -and $p) {
                        $f = Join-Path $dossierCaptures ($Matches[1] + '.jpg')
                        Write-Host ("Capture {0} : {1}" -f $Matches[1], [FbCap]::Shot($p.MainWindowHandle, $f))
                    }
                    if ($l -match 'AUTOTEST\|FIN' -and -not $finA) { $finA = Get-Date }
                }
            } else { $reste = $tout }
        }
        if ($finA -and ((Get-Date) - $finA).TotalSeconds -gt 3) { $etat = 'FINI'; break }
        if ($p) { $vuA = Get-Date }
        # Jeu introuvable plus de 20 s de suite (et lancé depuis plus de 40 s) : fermé ou planté.
        if (-not $p -and ((Get-Date) - $debut).TotalSeconds -gt 40 -and (-not $vuA -or ((Get-Date) - $vuA).TotalSeconds -gt 20)) { $etat = 'INTERROMPU'; break }
        if ((Get-Date) -gt $debut.AddMinutes($DureeMaxMinutes)) { $etat = 'DEPASSEMENT'; break }
    }
} finally {
    $p = Get-NotreArma
    if ($p -and -not $p.HasExited) { [void]$p.CloseMainWindow(); if (-not $p.WaitForExit(20000)) { Stop-Process -Id $p.Id -Force; [void]$p.WaitForExit(10000) } }
    if (Test-Path -LiteralPath $missionJeu) { Remove-Item -LiteralPath $missionJeu -Recurse -Force }
    if ((Test-Path -LiteralPath $cfgSave) -and ((Get-FileHash -LiteralPath $cfgJeu).Hash -ne (Get-FileHash -LiteralPath $cfgSave).Hash)) {
        Copy-Item -LiteralPath $cfgSave -Destination $cfgJeu -Force
        Write-Host 'Arma3.cfg rendu.'
    }
}
Write-Host "Etat : $etat"
if (-not $rpt) { Sortir 2 'Aucun RPT.' }
Copy-Item -LiteralPath $rpt.FullName -Destination $Sortie
$lignes = (Get-Content -LiteralPath $rpt.FullName -Encoding UTF8)
$at = @($lignes | Where-Object { $_ -match 'AUTOTEST\||\[COMSPEC FB\] (chargé|RÉSULTAT)' })
$at | ForEach-Object { Write-Host $_ }
# Erreurs de script dont le fichier est du mod.
$err = @(); for ($i = 0; $i -lt $lignes.Count; $i++) { if ($lignes[$i] -match '^\s*\S+\s+File \\?z\\comspec_flashbang') { $err += ($lignes[[Math]::Max(0, $i - 3)..$i] -join ' / ') } }
Write-Host "Erreurs de script du mod : $($err.Count)"; $err | Select-Object -First 10 | ForEach-Object { Write-Host "  $_" }
Write-Host "RPT copié dans $Sortie"
if ($etat -ne 'FINI') { exit 2 }
if (@($at | Where-Object { $_ -match 'AUTOTEST\|KO' }).Count -gt 0 -or $err.Count -gt 0) { exit 1 }
exit 0
