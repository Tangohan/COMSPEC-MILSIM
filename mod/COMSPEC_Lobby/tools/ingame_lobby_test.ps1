<#
.SYNOPSIS
    Essai en jeu de COMSPEC Lobby : un serveur dédié local (mission de test à postes étiquetés, codes dans userconfig) et un client
    qui s'y connecte et tombe sur l'écran des rôles. Par étapes, parce que l'écran se pilote à la souris :
      Start   lance le serveur puis le client, attend l'écran des rôles, prend une capture
      Shot    <nom>                       capture de la fenêtre du client (captures\<nom>.jpg)
      Click   <nom> <x> <y> [<ms>]        vrai clic gauche (x et y : fractions de la fenêtre, 0 à 1), puis capture
      Report                              lignes [COMSPEC LOBBY] et erreurs de script du client et du serveur
      Stop                                ferme le client et le serveur, rend les fichiers partagés, retire la mission et les codes de test
    Rien n'est lancé si Arma tourne déjà (code 3). Profil de test : -name=COMSPECTest (les fichiers du profil de l'utilisateur ne
    sont pas ouverts ; Arma3.cfg et COMSPEC_Local\config.json sont sauvegardés puis rendus).
.EXAMPLE
    powershell -NoProfile -ExecutionPolicy Bypass -File tools\ingame_lobby_test.ps1 Start
#>
[CmdletBinding()]
param(
    [Parameter(Position = 0)][ValidateSet('Start', 'Shot', 'Click', 'Report', 'Stop')][string]$Action = 'Start',
    [Parameter(Position = 1)][string]$Nom = 'capture',
    [Parameter(Position = 2)][double]$X = 0.5,
    [Parameter(Position = 3)][double]$Y = 0.5,
    [Parameter(Position = 4)][int]$Ms = 250,
    [string]$ArmaDir = 'F:\SteamLibrary\steamapps\common\Arma 3',
    [string]$WorkshopDir = 'F:\SteamLibrary\steamapps\workshop\content\107410',
    [string]$LobbyDir = '',
    [int]$Port = 2302,
    [int]$AttenteMaxSecondes = 420,
    [int]$Largeur = 1920,
    [int]$Hauteur = 1080
)
$ErrorActionPreference = 'Stop'
Set-StrictMode -Version 2

$ModRoot = Split-Path -Parent $PSScriptRoot                  # mod\COMSPEC_Lobby
if (-not $LobbyDir) { $LobbyDir = Join-Path $ModRoot '@COMSPEC_Lobby' }
$Work = Join-Path $env:TEMP 'comspec_lobby_test'
$StateFile = Join-Path $Work 'etat.json'
$Profil = 'COMSPECTest'
$NomMission = 'COMSPEC_Lobby_Test.Stratis'
$Docs = [Environment]::GetFolderPath('MyDocuments')
$RptDir = Join-Path $env:LOCALAPPDATA 'Arma 3'
function Sortir([int]$Code, [string]$Msg) { Write-Host $Msg; exit $Code }

Add-Type -ReferencedAssemblies System.Drawing -TypeDefinition @'
using System;
using System.Drawing;
using System.Drawing.Imaging;
using System.Linq;
using System.Runtime.InteropServices;
public static class LbCap {
    [StructLayout(LayoutKind.Sequential)] struct RECT { public int L, T, R, B; }
    [StructLayout(LayoutKind.Sequential)] struct POINT { public int X, Y; }
    [DllImport("user32.dll")] static extern IntPtr GetForegroundWindow();
    [DllImport("user32.dll")] static extern bool SetForegroundWindow(IntPtr h);
    [DllImport("user32.dll")] static extern bool BringWindowToTop(IntPtr h);
    [DllImport("user32.dll")] static extern bool ShowWindow(IntPtr h, int c);
    [DllImport("user32.dll")] static extern bool IsIconic(IntPtr h);
    [DllImport("user32.dll")] static extern bool GetClientRect(IntPtr h, out RECT r);
    [DllImport("user32.dll")] static extern bool ClientToScreen(IntPtr h, ref POINT p);
    [DllImport("user32.dll")] static extern bool PrintWindow(IntPtr h, IntPtr dc, uint f);
    [DllImport("user32.dll")] static extern uint GetWindowThreadProcessId(IntPtr h, IntPtr p);
    [DllImport("user32.dll")] static extern bool AttachThreadInput(uint a, uint b, bool f);
    [DllImport("user32.dll")] static extern bool SetProcessDPIAware();
    [DllImport("kernel32.dll")] static extern uint GetCurrentThreadId();
    [DllImport("user32.dll")] static extern bool SetCursorPos(int x, int y);
    [DllImport("user32.dll")] static extern void mouse_event(uint f, uint dx, uint dy, uint d, UIntPtr e);
    public static void Init() { SetProcessDPIAware(); }
    public static bool Front(IntPtr h) {
        if (GetForegroundWindow() == h) return true;
        if (IsIconic(h)) ShowWindow(h, 9);
        uint me = GetCurrentThreadId();
        uint fg = GetWindowThreadProcessId(GetForegroundWindow(), IntPtr.Zero);
        uint tg = GetWindowThreadProcessId(h, IntPtr.Zero);
        AttachThreadInput(me, fg, true); AttachThreadInput(me, tg, true);
        BringWindowToTop(h); SetForegroundWindow(h);
        AttachThreadInput(me, fg, false); AttachThreadInput(me, tg, false);
        System.Threading.Thread.Sleep(120);
        return GetForegroundWindow() == h;
    }
    public static string Capture(IntPtr h, string path) {
        bool front = Front(h);
        RECT r; GetClientRect(h, out r);
        int w = r.R - r.L, ht = r.B - r.T;
        if (w <= 0 || ht <= 0) return "ECHEC|fenêtre vide";
        POINT o = new POINT(); ClientToScreen(h, ref o);
        using (Bitmap b = new Bitmap(w, ht, PixelFormat.Format24bppRgb)) {
            using (Graphics g = Graphics.FromImage(b)) {
                if (front) { g.CopyFromScreen(o.X, o.Y, 0, 0, new Size(w, ht)); }
                else { IntPtr dc = g.GetHdc(); PrintWindow(h, dc, 3); g.ReleaseHdc(dc); }
            }
            ImageCodecInfo jpg = ImageCodecInfo.GetImageEncoders().First(e => e.FormatID == ImageFormat.Jpeg.Guid);
            EncoderParameters ep = new EncoderParameters(1); ep.Param[0] = new EncoderParameter(Encoder.Quality, 88L);
            b.Save(path, jpg, ep);
            return (front ? "premierplan" : "printwindow") + "|" + w + "x" + ht;
        }
    }
    public static string Clic(IntPtr h, double fx, double fy, int holdMs) {
        if (!Front(h)) return "ECHEC|fenêtre pas au premier plan";
        RECT r; GetClientRect(h, out r);
        POINT o = new POINT(); ClientToScreen(h, ref o);
        SetCursorPos(o.X + (int)((r.R - r.L) * fx), o.Y + (int)((r.B - r.T) * fy));
        System.Threading.Thread.Sleep(80);
        mouse_event(0x0002, 0, 0, 0, UIntPtr.Zero);
        System.Threading.Thread.Sleep(Math.Max(30, holdMs));
        mouse_event(0x0004, 0, 0, 0, UIntPtr.Zero);
        System.Threading.Thread.Sleep(250);
        return "clic";
    }
}
'@
[LbCap]::Init()

function Get-State { if (Test-Path $StateFile) { Get-Content $StateFile -Raw | ConvertFrom-Json } else { $null } }
function Get-ClientProcess($st) {
    if ($null -eq $st) { return $null }
    Get-Process -Id ([int]$st.clientPid) -ErrorAction SilentlyContinue
}
function Get-Handle($p) {
    $h = $p.MainWindowHandle
    if ($h -eq [IntPtr]::Zero) { $p.Refresh(); $h = $p.MainWindowHandle }
    return $h
}
function Get-ClientRpt($since) {
    Get-ChildItem -LiteralPath $RptDir -Filter 'arma3_x64_*.rpt' -ErrorAction SilentlyContinue |
        Where-Object { $_.LastWriteTime -ge $since } | Sort-Object LastWriteTime -Descending | Select-Object -First 1
}
function Read-Shared([string]$path) {
    # Lecture sans verrou (Arma écrit encore dans le RPT).
    $fs = [IO.File]::Open($path, 'Open', 'Read', 'ReadWrite')
    try { $sr = New-Object IO.StreamReader($fs, [Text.Encoding]::UTF8); return $sr.ReadToEnd() } finally { $fs.Dispose() }
}

switch ($Action) {
'Start' {
    $dejaLa = @(Get-Process -Name 'arma3', 'arma3_x64', 'arma3battleye', 'arma3server', 'arma3server_x64' -ErrorAction SilentlyContinue)
    if ($dejaLa.Count -gt 0) { Sortir 3 ("Arma est déjà lancé (PID {0}) : rien n'est fait." -f (($dejaLa | ForEach-Object { $_.Id }) -join ', ')) }
    $serverExe = Join-Path $ArmaDir 'arma3server_x64.exe'
    $clientExe = Join-Path $ArmaDir 'arma3_x64.exe'
    foreach ($f in $serverExe, $clientExe) { if (-not (Test-Path -LiteralPath $f)) { Sortir 4 "Introuvable : $f" } }
    if (-not (Test-Path -LiteralPath (Join-Path $LobbyDir 'addons\comspec_lobby_main.pbo'))) { Sortir 4 "Build du mod introuvable : $LobbyDir (build_mod.bat)" }
    $cba = Join-Path $WorkshopDir '450814997'
    if (-not (Test-Path -LiteralPath (Join-Path $cba 'addons\cba_main.pbo'))) { Sortir 4 "CBA introuvable : $cba" }
    if (Get-Process -Name steam -ErrorAction SilentlyContinue) { } else { Write-Host '[ATTENTION] Steam ne tourne pas.' }

    if (Test-Path $Work) { Remove-Item -Recurse -Force $Work }
    [void](New-Item -ItemType Directory -Force -Path $Work, (Join-Path $Work 'captures'), (Join-Path $Work 'srvprof'), (Join-Path $Work 'mods'))
    # Le mod est copié : le dossier du dépôt reste libre (changement de branche, nouveau build).
    Copy-Item -LiteralPath $LobbyDir -Destination (Join-Path $Work 'mods\@COMSPEC_Lobby') -Recurse
    $modsArg = '"-mod=' + $cba + ';' + (Join-Path $Work 'mods\@COMSPEC_Lobby') + '"'

    # Mission de test : copiée dans MPMissions, retirée par Stop.
    $src = Join-Path $ModRoot "missions\$NomMission"
    $dst = Join-Path $ArmaDir "MPMissions\$NomMission"
    if (Test-Path $dst) { Remove-Item -Recurse -Force $dst }
    Copy-Item -LiteralPath $src -Destination $dst -Recurse
    # Codes de test (poste HQ-1 = 1234, poste A-1 = 4821), dans userconfig du serveur : retirés par Stop.
    $uc = Join-Path $ArmaDir 'userconfig\comspec_lobby'
    $ucAvant = Test-Path -LiteralPath (Join-Path $uc 'pins.txt')
    if ($ucAvant) { Sortir 4 "$uc\pins.txt existe déjà : je ne l'écrase pas." }
    [void](New-Item -ItemType Directory -Force -Path $uc)
    [IO.File]::WriteAllText((Join-Path $uc 'pins.txt'), "# codes de l'essai COMSPEC Lobby`r`nHQ-1=1234`r`nA-1=4821`r`n", (New-Object Text.UTF8Encoding($false)))
    # Essai : la copie du mod porte aussi son userconfig (le moteur ne voit userconfig que par là).
    $mu = Join-Path $Work 'mods\@COMSPEC_Lobby\userconfig\comspec_lobby'; [void](New-Item -ItemType Directory -Force -Path $mu); Copy-Item -LiteralPath (Join-Path $uc 'pins.txt') -Destination $mu

    # État écrit tout de suite : Stop sait retirer la mission et les codes de test même si le lancement échoue plus loin.
    @{ clientPid = 0; serverPid = 0; start = (Get-Date).ToString('o'); mission = $dst; pins = (Join-Path $uc 'pins.txt'); ucDir = $uc; sauv = @{} } | ConvertTo-Json -Depth 4 | Set-Content $StateFile -Encoding UTF8

    $cfg = @"
hostName = "COMSPEC Lobby Test";
password = "";
passwordAdmin = "lobbytest$(Get-Random)";
maxPlayers = 8;
verifySignatures = 0;
BattlEye = 0;
disableVoN = 1;
persistent = 0;
kickDuplicate = 0;
requiredSecureId = 0;
class Missions {
    class Test {
        template = "$NomMission";
        difficulty = "Regular";
    };
};
"@
    [IO.File]::WriteAllText((Join-Path $Work 'server.cfg'), $cfg, (New-Object Text.UTF8Encoding($false)))
    [IO.File]::WriteAllText((Join-Path $Work 'basic.cfg'), "MaxMsgSend = 128;`r`nMaxSizeGuaranteed = 512;`r`nMaxSizeNonguaranteed = 256;`r`nMinBandwidth = 131072;`r`nMaxBandwidth = 10000000000;`r`n", (New-Object Text.UTF8Encoding($false)))

    # Fichiers partagés entre profils : sauvegardés pour être rendus.
    $partages = @((Join-Path $Docs 'Arma 3\Arma3.cfg'), (Join-Path $Docs 'Arma 3\COMSPEC_Local\config.json'))
    $sauv = @{}
    $i = 0
    foreach ($f in $partages) { if (Test-Path -LiteralPath $f) { $i++; $c = Join-Path $Work ("sauv_{0}_{1}" -f $i, (Split-Path -Leaf $f)); Copy-Item -LiteralPath $f -Destination $c; $sauv[$f] = $c } }

    $t0 = Get-Date
    $srvArgs = "-port=$Port `"-config=$(Join-Path $Work 'server.cfg')`" `"-cfg=$(Join-Path $Work 'basic.cfg')`" `"-profiles=$(Join-Path $Work 'srvprof')`" -name=lobbysrv -filePatching -autoInit -noSound $modsArg"
    $srv = Start-Process -FilePath $serverExe -ArgumentList $srvArgs -WorkingDirectory $ArmaDir -PassThru
    Write-Host "Serveur lancé (PID $($srv.Id)), attente de la mission..."
    $pret = $false
    for ($k = 0; $k -lt 40; $k++) {
        Start-Sleep -Seconds 2
        if ($srv.HasExited) { break }
        $rptSrv = Get-ChildItem -LiteralPath (Join-Path $Work 'srvprof') -Filter '*.rpt' -ErrorAction SilentlyContinue | Select-Object -First 1
        if ($rptSrv) {
            $txt = Read-Shared $rptSrv.FullName
            if ($txt -match 'Mission .* read from|Roles assigned|Game started|Waiting for host|Role assignment' -or $txt -match 'COMSPEC LOBBY\] codes de poste') { $pret = $true; break }
        }
    }
    if ($srv.HasExited) { Sortir 2 'Le serveur s''est arrêté au démarrage : voir srvprof\*.rpt' }
    Write-Host "Serveur : $(if ($pret) { 'mission prête' } else { 'pas de signe de la mission (on continue)' })"

    $cliArgs = "-connect=127.0.0.1 -port=$Port -skipIntro -noSplash -world=empty -showScriptErrors -noPause -noPauseAudio -noSound -window -x=$Largeur -y=$Hauteur -posX=0 -posY=0 -name=$Profil $modsArg"
    $cli = Start-Process -FilePath $clientExe -ArgumentList $cliArgs -WorkingDirectory $ArmaDir -PassThru
    Write-Host "Client lancé (PID $($cli.Id))."
    @{ clientPid = $cli.Id; serverPid = $srv.Id; start = $t0.ToString('o'); mission = $dst; pins = (Join-Path $uc 'pins.txt'); ucDir = $uc; sauv = $sauv } | ConvertTo-Json -Depth 4 | Set-Content $StateFile -Encoding UTF8

    $vu = $false
    $limite = (Get-Date).AddSeconds($AttenteMaxSecondes)
    while ((Get-Date) -lt $limite) {
        Start-Sleep -Seconds 3
        if ($cli.HasExited) { Sortir 2 'Le client s''est arrêté avant l''écran des rôles.' }
        $rpt = Get-ClientRpt $t0
        if ($rpt) {
            $txt = Read-Shared $rpt.FullName
            if ($txt -match 'COMSPEC LOBBY\] (écran ouvert|contrôles manquants)') { $vu = $true; break }
        }
    }
    if (-not $vu) { Write-Host "L'écran des rôles n'a pas écrit sa ligne dans le délai. On capture quand même." }
    Start-Sleep -Seconds 4
    $h = Get-Handle $cli
    if ($h -ne [IntPtr]::Zero) { Write-Host ('Capture : ' + [LbCap]::Capture($h, (Join-Path $Work 'captures\01_lobby.jpg'))) } else { Write-Host 'Pas de fenêtre à capturer.' }
    Write-Host "Dossier de l'essai : $Work"
}
'Shot' {
    $st = Get-State; $p = Get-ClientProcess $st
    if ($null -eq $p) { Sortir 2 'Client absent (Start d''abord).' }
    $f = Join-Path $Work ("captures\{0}.jpg" -f ($Nom -replace '[^\w\-]', '_'))
    Write-Host ('Capture : ' + [LbCap]::Capture((Get-Handle $p), $f) + ' -> ' + $f)
}
'Click' {
    $st = Get-State; $p = Get-ClientProcess $st
    if ($null -eq $p) { Sortir 2 'Client absent (Start d''abord).' }
    $h = Get-Handle $p
    Write-Host ([LbCap]::Clic($h, $X, $Y, $Ms))
    Start-Sleep -Milliseconds 900
    $f = Join-Path $Work ("captures\{0}.jpg" -f ($Nom -replace '[^\w\-]', '_'))
    Write-Host ('Capture : ' + [LbCap]::Capture($h, $f) + ' -> ' + $f)
}
'Report' {
    $st = Get-State
    if ($null -eq $st) { Sortir 2 'Pas d''essai en cours.' }
    $t0 = [datetime]::Parse($st.start)
    $rpt = Get-ClientRpt $t0
    if ($rpt) {
        Write-Host "=== Client : $($rpt.Name)"
        $txt = Read-Shared $rpt.FullName
        ($txt -split "`n") | Where-Object { $_ -match 'COMSPEC LOBBY|LOBBYTEST|Error in expression|Error position|Error Undefined|Error Missing|Error Zero|Error Type|Warning Message|Cannot open|not found' } | Select-Object -Last 80
    }
    $rs = Get-ChildItem -LiteralPath (Join-Path $Work 'srvprof') -Filter '*.rpt' -ErrorAction SilentlyContinue | Select-Object -First 1
    if ($rs) {
        Write-Host "=== Serveur : $($rs.Name)"
        $txt = Read-Shared $rs.FullName
        ($txt -split "`n") | Where-Object { $_ -match 'COMSPEC LOBBY|Error in expression|Error position|Error Undefined|Error Missing|Error Type|Mission|Connected|Player .* (connected|disconnected)' } | Select-Object -Last 60
    }
}
'Stop' {
    $st = Get-State
    foreach ($n in 'arma3_x64', 'arma3server_x64', 'arma3battleye') {
        Get-Process -Name $n -ErrorAction SilentlyContinue | ForEach-Object { try { $_.Kill() } catch { } }
    }
    Start-Sleep -Seconds 2
    if ($null -ne $st) {
        foreach ($prop in $st.sauv.PSObject.Properties) {
            if ((Test-Path -LiteralPath $prop.Name) -and ((Get-FileHash $prop.Name).Hash -ne (Get-FileHash $prop.Value).Hash)) { Copy-Item -LiteralPath $prop.Value -Destination $prop.Name -Force; Write-Host "Rendu : $($prop.Name)" }
        }
        if (Test-Path -LiteralPath $st.mission) { Remove-Item -Recurse -Force -LiteralPath $st.mission }
        if (Test-Path -LiteralPath $st.pins) { Remove-Item -Force -LiteralPath $st.pins }
        if ((Test-Path -LiteralPath $st.ucDir) -and -not (Get-ChildItem -LiteralPath $st.ucDir)) { Remove-Item -Force -LiteralPath $st.ucDir }
    }
    Write-Host 'Arma fermé, mission et codes de test retirés.'
}
}
