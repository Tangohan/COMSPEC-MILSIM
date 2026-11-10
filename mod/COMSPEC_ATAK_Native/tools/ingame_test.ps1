<#
.SYNOPSIS
    Test automatique en jeu du mod COMSPEC ATAK et du SSE : lance Arma 3 sur la mission de test, puis résume le RPT.
    Documentation : tools/INGAME_TEST.md. Codes de sortie : 0 tout est bon, 1 KO ou erreur de script COMSPEC,
    2 mission non terminée (dépassement, plantage, lancement raté), 3 Arma déjà lancé (rien n'est fait), 4 environnement.
.EXAMPLE
    powershell -NoProfile -ExecutionPolicy Bypass -File tools\ingame_test.ps1
.EXAMPLE
    powershell -NoProfile -ExecutionPolicy Bypass -File tools\ingame_test.ps1 -Groupes fonctions
#>
[CmdletBinding()]
param(
    [string]$ArmaExe = 'F:\SteamLibrary\steamapps\common\Arma 3\arma3_x64.exe',
    [string]$WorkshopDir = 'F:\SteamLibrary\steamapps\workshop\content\107410',
    # Build local à tester (vides : ceux du dépôt qui contient ce script).
    [string]$AtakDir = '',
    [string]$SseDir = '',
    # ACRE (vide : Workshop 751965892). Le pack 3026491672 contient aussi des PBO COMSPEC : il masquerait le build, il est refusé.
    [string]$AcreDir = '',
    # Liste complète des mods : remplace CBA, ACE, ACRE, ATAK, SSE.
    [string[]]$Mods = @(),
    [switch]$SansAcre,
    [switch]$SansSse,
    # MPU-5 d'ACRE (ACRE_MPU5.pbo seul, tiré du pack 3026491672) pour l'étape acre_radio_detectee : retiré avec ce paramètre.
    [switch]$SansAcreMpu5,
    # Groupes de la mission (vide : tous) : fonctions telephone carte antennes gps explo sse equipement zeus acre reseau mpudiag mpuhud chiffrement relais
    [string[]]$Groupes = @(),
    [int]$DureeMaxMinutes = 45,
    # Attente de la première ligne AUTOTEST avant de passer au plan suivant.
    [int]$DemarrageMaxSecondes = 300,
    # Auto : -init (copie temporaire dans Arma 3\Missions), puis -autotest si rien ne démarre.
    [ValidateSet('Auto', 'Init', 'Autotest')][string]$Lancement = 'Auto',
    [switch]$PleinEcran,
    [switch]$AvecSon,
    # Vérification de la licence (« ne transmet rien » si le joueur répond Non) : sans le proxy mort, toute connexion vers Athena
    # donne un échec. Sans ce paramètre, le proxy bloque les DLL du mod et le test ne prouve rien sur ce point.
    [switch]$SansProxy,
    [int]$LargeurCapture = 1280,
    [string]$Sortie = ''
)
$ErrorActionPreference = 'Stop'
Set-StrictMode -Version 2
# -File ne sait pas passer un tableau : -Groupes antennes,sse arrive en une seule chaîne.
$Groupes = @($Groupes | ForEach-Object { $_ -split ',' } | Where-Object { $_ })

$Profil = 'COMSPECTest'
$NomMission = 'COMSPEC_Autotest.Stratis'
$RptDir = Join-Path $env:LOCALAPPDATA 'Arma 3'
$Docs = [Environment]::GetFolderPath('MyDocuments')
$ModRoot = Split-Path -Parent $PSScriptRoot                 # mod\COMSPEC_ATAK_Native
$MissionSrc = Join-Path $ModRoot "missions\$NomMission"
$ModsRoot = Split-Path -Parent $ModRoot                     # mod

function Sortir([int]$Code, [string]$Msg) { Write-Host $Msg; exit $Code }

function Get-ArmaProcesses {
    Get-Process -Name 'arma3', 'arma3_x64', 'arma3battleye', 'arma3server', 'arma3server_x64' -ErrorAction SilentlyContinue
}

# ---------- Contrôles avant lancement ----------
$dejaLa = @(Get-ArmaProcesses)
if ($dejaLa.Count -gt 0) {
    Sortir 3 ("Arma est déjà lancé (PID {0}) : le test refuse de démarrer et n'y touche pas." -f (($dejaLa | ForEach-Object { $_.Id }) -join ', '))
}
if (-not (Test-Path -LiteralPath $ArmaExe)) { Sortir 4 "Arma introuvable : $ArmaExe" }
if (-not (Test-Path -LiteralPath $MissionSrc)) { Sortir 4 "Mission introuvable : $MissionSrc" }
if (-not (Get-Process -Name 'steam' -ErrorAction SilentlyContinue)) { Write-Host '[ATTENTION] Steam ne tourne pas : Arma risque de ne pas démarrer ou de se relancer sans ces paramètres.' }

if (-not $AtakDir) { $AtakDir = Join-Path $ModRoot '@COMSPEC_ATAK_Native' }
if (-not $SseDir) {
    $SseDir = Join-Path $ModsRoot '@COMSPEC_SSE'
    if (-not (Test-Path -LiteralPath (Join-Path $SseDir 'addons\comspec_sse_main.pbo'))) { $SseDir = Join-Path $ModsRoot 'publisher\@COMSPEC_SSE' }
}
if (-not $AcreDir) { $AcreDir = Join-Path $WorkshopDir '751965892' }

function Test-Mod([string]$Dir, [string]$Pbo, [string]$Nom) {
    if (-not (Test-Path -LiteralPath (Join-Path $Dir "addons\$Pbo"))) { Sortir 4 "$Nom introuvable : $(Join-Path $Dir "addons\$Pbo")" }
}
$avert = New-Object System.Collections.ArrayList
if ($Mods.Count -gt 0) {
    $listeMods = $Mods
} else {
    $listeMods = @((Join-Path $WorkshopDir '450814997'), (Join-Path $WorkshopDir '463939057'))
    Test-Mod $listeMods[0] 'cba_main.pbo' 'CBA'
    Test-Mod $listeMods[1] 'ace_main.pbo' 'ACE'
    if (-not $SansAcre) {
        if (Test-Path -LiteralPath (Join-Path $AcreDir 'addons\acre_main.pbo')) {
            if (@(Get-ChildItem -LiteralPath (Join-Path $AcreDir 'addons') -Filter 'comspec_*.pbo').Count -gt 0) {
                Sortir 4 "$AcreDir contient aussi des PBO COMSPEC : ils masqueraient le build à tester. Donnez un ACRE seul (-AcreDir) ou -SansAcre."
            }
            $listeMods += $AcreDir
        } else {
            [void]$avert.Add("ACRE introuvable dans $AcreDir : test sans ACRE.")
        }
    }
    Test-Mod $AtakDir 'main.pbo' 'Build ATAK'
    $listeMods += $AtakDir
    if (-not $SansSse) {
        Test-Mod $SseDir 'comspec_sse_main.pbo' 'Build SSE'
        $listeMods += $SseDir
    }
}
foreach ($m in $listeMods) { if (-not (Test-Path -LiteralPath $m)) { Sortir 4 "Mod introuvable : $m" } }

# Build périmé : le test porterait sur d'anciens PBO. Un PBO refait en local (modifié pour Git) est à jour.
function Test-Perime([string]$Pbo, [string]$Sources, [string]$Nom) {
    if (-not (Test-Path -LiteralPath $Pbo) -or -not (Test-Path -LiteralPath $Sources)) { return }
    $ErrorActionPreference = 'Continue'
    $dirPbo = Split-Path -Parent $Pbo
    $leaf = Split-Path -Leaf $Pbo
    if (git -C $dirPbo status --porcelain -- $leaf 2>$null) { return }
    $tSrc = git -C $Sources log -1 --format=%ct -- . 2>$null
    $tPbo = git -C $dirPbo log -1 --format=%ct -- $leaf 2>$null
    if ($tSrc -and $tPbo -and ([int64]$tSrc -gt [int64]$tPbo)) {
        [void]$avert.Add("$Nom : sources commitées après le PBO. Refaire le build (build_mod.bat), sinon le test porte sur l'ancien code.")
    }
}
if ($Mods.Count -eq 0) {
    Test-Perime (Join-Path $AtakDir 'addons\main.pbo') (Join-Path $ModRoot 'Sources\addons\main') 'ATAK'
    if (-not $SansSse) { Test-Perime (Join-Path $SseDir 'addons\comspec_sse_identity.pbo') (Join-Path $SseDir 'addons\identity') 'SSE' }
}

# ---------- Dossier du passage : mission copiée, sauvegardes, RPT, captures, rapport ----------
$stamp = Get-Date -Format 'yyyyMMdd_HHmmss'
if (-not $Sortie) { $Sortie = Join-Path $env:TEMP "comspec_autotest\$stamp" }
[void](New-Item -ItemType Directory -Force -Path $Sortie)
$MissionRun = Join-Path $Sortie "mission\$NomMission"
[void](New-Item -ItemType Directory -Force -Path (Split-Path -Parent $MissionRun))
Copy-Item -LiteralPath $MissionSrc -Destination $MissionRun -Recurse
# MPU-5 d'ACRE (mod « MPU-5 Persistent Systems », classe ACRE_MPU5) : seul son PBO est copié dans le dossier du passage, le reste du
# pack 3026491672 masquerait le build testé. Sans lui, l'étape acre_radio_detectee teste la 152 et la 343 et note la MPU-5 en INFO.
if ($Mods.Count -eq 0 -and -not $SansAcre -and -not $SansAcreMpu5 -and ($listeMods -contains $AcreDir) -and ($Groupes.Count -eq 0 -or $Groupes -contains 'acre')) {
    $pboMpu = Join-Path $WorkshopDir '3026491672\addons\ACRE_MPU5.pbo'
    if (Test-Path -LiteralPath $pboMpu) {
        $dirMpu = Join-Path $Sortie 'mods\@ACRE_MPU5\addons'
        [void](New-Item -ItemType Directory -Force -Path $dirMpu)
        Copy-Item -LiteralPath $pboMpu -Destination $dirMpu
        $listeMods += (Split-Path -Parent $dirMpu)
    } else {
        [void]$avert.Add("ACRE_MPU5.pbo introuvable ($pboMpu) : l'étape acre_radio_detectee ne teste pas la MPU-5 d'ACRE.")
    }
}
$sqfGroupes = ($Groupes | ForEach-Object { '"' + $_ + '"' }) -join ', '
[IO.File]::WriteAllText((Join-Path $MissionRun 'autotest_params.sqf'), "AT_Groupes = [$sqfGroupes];`r`nAT_Quitter = true;`r`n", (New-Object Text.UTF8Encoding($false)))

# Fichiers partagés entre profils : sauvegardés, rendus s'ils ont changé. Les fichiers du profil de l'utilisateur ne sont pas ouverts :
# -name sépare le profil de test (Documents\Arma 3 - Other Profiles\COMSPECTest).
$partages = @((Join-Path $Docs 'Arma 3\Arma3.cfg'), (Join-Path $Docs 'Arma 3\COMSPEC_Local\config.json'))
$sauvegardes = @{}
$saveDir = Join-Path $Sortie 'sauvegarde'
[void](New-Item -ItemType Directory -Force -Path $saveDir)
$i = 0
foreach ($f in $partages) {
    if (Test-Path -LiteralPath $f) {
        $i++
        $copie = Join-Path $saveDir ("{0}_{1}" -f $i, (Split-Path -Leaf $f))
        Copy-Item -LiteralPath $f -Destination $copie
        $sauvegardes[$f] = $copie
    }
}

# ---------- Captures ----------
# La commande screenshot d'Arma n'enregistre que la scène 3D, sans aucune interface (téléphone compris), en PNG de 17 Mo avec un
# plafond de 250 Mo par dossier : la mission écrit AUTOTEST|SHOT|nom au RPT, ce script capture la fenêtre réelle en JPEG.
$dossierCaptures = Join-Path $Sortie 'captures'
[void](New-Item -ItemType Directory -Force -Path $dossierCaptures)
Add-Type -ReferencedAssemblies System.Drawing -TypeDefinition @'
using System;
using System.Drawing;
using System.Drawing.Imaging;
using System.Linq;
using System.Runtime.InteropServices;
public static class AtCap {
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
    // Vrai clic de souris (autotest du toucher) : curseur posé au point de la fenêtre (fractions de la zone cliente),
    // bouton gauche enfoncé, capture au milieu de l'appui, puis relâché. La mission pose aussi son curseur (setMousePosition).
    public static string Clic(IntPtr h, double fx, double fy, int holdMs, string path, int maxW) {
        if (!Front(h)) return "ECHEC|fenêtre pas au premier plan";
        RECT r; GetClientRect(h, out r);
        POINT o = new POINT(); ClientToScreen(h, ref o);
        SetCursorPos(o.X + (int)((r.R - r.L) * fx), o.Y + (int)((r.B - r.T) * fy));
        System.Threading.Thread.Sleep(60);
        mouse_event(0x0002, 0, 0, 0, UIntPtr.Zero);
        System.Threading.Thread.Sleep(Math.Max(0, holdMs / 2 - 100));
        string cap = path == "" ? "" : Capture(h, path, maxW);
        System.Threading.Thread.Sleep(Math.Max(0, holdMs / 2));
        mouse_event(0x0004, 0, 0, 0, UIntPtr.Zero);
        return "clic|" + cap;
    }
    public static bool Front(IntPtr h) {
        if (GetForegroundWindow() == h) return true;
        if (IsIconic(h)) ShowWindow(h, 9);
        uint me = GetCurrentThreadId();
        uint fg = GetWindowThreadProcessId(GetForegroundWindow(), IntPtr.Zero);
        uint tg = GetWindowThreadProcessId(h, IntPtr.Zero);
        AttachThreadInput(me, fg, true); AttachThreadInput(me, tg, true);
        BringWindowToTop(h); SetForegroundWindow(h);
        AttachThreadInput(me, fg, false); AttachThreadInput(me, tg, false);
        System.Threading.Thread.Sleep(80);
        return GetForegroundWindow() == h;
    }
    // Rend "premierplan|printwindow, largeur x hauteur, UNIE si l'image est d'une seule teinte (rendu noir)".
    public static string Capture(IntPtr h, string path, int maxW) {
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
            int tw = Math.Min(maxW, w), th = (int)(ht * ((double)tw / w));
            using (Bitmap s = new Bitmap(tw, th, PixelFormat.Format24bppRgb)) {
                using (Graphics g2 = Graphics.FromImage(s)) { g2.InterpolationMode = System.Drawing.Drawing2D.InterpolationMode.HighQualityBicubic; g2.DrawImage(b, 0, 0, tw, th); }
                double sum = 0, sq = 0; int n = 0;
                for (int y = 0; y < th; y += Math.Max(1, th / 32)) for (int x = 0; x < tw; x += Math.Max(1, tw / 32)) { Color c = s.GetPixel(x, y); double v = c.R + c.G + c.B; sum += v; sq += v * v; n++; }
                double sd = Math.Sqrt(Math.Max(0, sq / n - (sum / n) * (sum / n)));
                ImageCodecInfo jpg = ImageCodecInfo.GetImageEncoders().First(e => e.FormatID == ImageFormat.Jpeg.Guid);
                EncoderParameters ep = new EncoderParameters(1); ep.Param[0] = new EncoderParameter(Encoder.Quality, 82L);
                s.Save(path, jpg, ep);
                return (front ? "premierplan" : "printwindow") + "|" + w + "x" + ht + (sd < 6 ? "|UNIE" : "");
            }
        }
    }
    // Planches contact : vignettes étiquetées, pour tout regarder d'un coup.
    public static void Planche(string[] files, string path, int cols, int thumbW) {
        int thumbH = (int)(thumbW * 0.75), lab = 22, rows = (int)Math.Ceiling(files.Length / (double)cols);
        using (Bitmap b = new Bitmap(cols * thumbW, rows * (thumbH + lab), PixelFormat.Format24bppRgb))
        using (Graphics g = Graphics.FromImage(b))
        using (Font f = new Font("Arial", 11f, FontStyle.Bold)) {
            g.Clear(Color.Black);
            for (int i = 0; i < files.Length; i++) {
                int x = (i % cols) * thumbW, y = (i / cols) * (thumbH + lab);
                using (Image im = Image.FromFile(files[i])) {
                    double k = Math.Min((double)thumbW / im.Width, (double)thumbH / im.Height);
                    g.InterpolationMode = System.Drawing.Drawing2D.InterpolationMode.HighQualityBicubic;
                    g.DrawImage(im, x, y + lab, (int)(im.Width * k), (int)(im.Height * k));
                }
                g.DrawString(System.IO.Path.GetFileNameWithoutExtension(files[i]), f, Brushes.Yellow, x + 2, y + 2);
            }
            ImageCodecInfo jpg = ImageCodecInfo.GetImageEncoders().First(e => e.FormatID == ImageFormat.Jpeg.Guid);
            EncoderParameters ep = new EncoderParameters(1); ep.Param[0] = new EncoderParameter(Encoder.Quality, 85L);
            b.Save(path, jpg, ep);
        }
    }
}
'@
[AtCap]::Init()
$journalCaptures = New-Object System.Collections.ArrayList
function Invoke-Capture($P, [string]$Nom) {
    $propre = $Nom -replace '[^\w\-]', '_'
    $chemin = Join-Path $dossierCaptures "$propre.jpg"
    $h = $P.MainWindowHandle
    if ($h -eq [IntPtr]::Zero) { $P.Refresh(); $h = $P.MainWindowHandle }
    $info = 'ECHEC|pas de fenêtre'
    if ($h -ne [IntPtr]::Zero) {
        try { $info = [AtCap]::Capture($h, $chemin, $LargeurCapture) } catch { $info = "ECHEC|$($_.Exception.Message)" }
    }
    [void]$journalCaptures.Add("$Nom|$info")
}

# ---------- Lancement ----------
# Vrai clic (AUTOTEST|CLICK|nom|x|y|durée en ms, x et y en fractions de la fenêtre) : capture pendant l'appui.
function Invoke-Clic($P, [string]$Nom, [double]$Fx, [double]$Fy, [int]$Ms) {
    $propre = $Nom -replace '[^\w\-]', '_'
    $chemin = Join-Path $dossierCaptures "$propre.jpg"
    $h = $P.MainWindowHandle
    if ($h -eq [IntPtr]::Zero) { $P.Refresh(); $h = $P.MainWindowHandle }
    $info = 'ECHEC|pas de fenêtre'
    if ($h -ne [IntPtr]::Zero) {
        try { $info = [AtCap]::Clic($h, $Fx, $Fy, $Ms, $chemin, $LargeurCapture) } catch { $info = "ECHEC|$($_.Exception.Message)" }
    }
    [void]$journalCaptures.Add("$Nom|$info")
}

function Get-ArgsArma([string]$Plan, [string]$AutotestCfg) {
    $a = New-Object System.Collections.ArrayList
    [void]$a.Add('-skipIntro'); [void]$a.Add('-noSplash'); [void]$a.Add('-world=empty'); [void]$a.Add('-showScriptErrors')
    [void]$a.Add('-noPause'); [void]$a.Add('-noPauseAudio'); [void]$a.Add("-name=$Profil")
    if (-not $PleinEcran) { [void]$a.Add('-window') }
    if (-not $AvecSon) { [void]$a.Add('-noSound') }
    [void]$a.Add('"-mod=' + ($listeMods -join ';') + '"')
    # Guillemets simples du SQF : aucun guillemet double à échapper dans la ligne de commande.
    if ($Plan -eq 'Init') { [void]$a.Add("-init=playMission['','$NomMission']") }
    else { [void]$a.Add('"-autotest=' + $AutotestCfg + '"') }
    return ($a -join ' ')
}

function Start-Arma([string]$Arguments) {
    $psi = New-Object Diagnostics.ProcessStartInfo
    $psi.FileName = $ArmaExe
    $psi.WorkingDirectory = Split-Path -Parent $ArmaExe
    $psi.Arguments = $Arguments
    $psi.UseShellExecute = $false
    # Proxy mort pour ce seul processus : les DLL du mod (.NET, HttpClient.DefaultProxy) ne joignent plus Internet.
    # Le mod lui-même ouvre une connexion à Athena au chargement (Warmup), même en mode hors ligne.
    if (-not $SansProxy) {
        foreach ($v in 'HTTP_PROXY', 'HTTPS_PROXY', 'ALL_PROXY') { $psi.EnvironmentVariables[$v] = 'http://127.0.0.1:9' }
        $psi.EnvironmentVariables['NO_PROXY'] = '127.0.0.1,localhost'
    }
    return [Diagnostics.Process]::Start($psi)
}

# Le processus de ce test : celui lancé, ou celui que Steam a relancé (ligne de commande avec -name=COMSPECTest).
function Get-NotreArma($Parent) {
    if ($Parent -and -not $Parent.HasExited) { return $Parent }
    Get-CimInstance Win32_Process -Filter "Name='arma3_x64.exe'" -ErrorAction SilentlyContinue |
        Where-Object { $_.CommandLine -like "*-name=$Profil*" } | ForEach-Object { Get-Process -Id $_.ProcessId -ErrorAction SilentlyContinue } | Select-Object -First 1
}

function Stop-NotreArma($P) {
    if (-not $P -or $P.HasExited) { return }
    [void]$P.CloseMainWindow()
    if (-not $P.WaitForExit(30000)) { Stop-Process -Id $P.Id -Force -ErrorAction SilentlyContinue; [void]$P.WaitForExit(15000) }
}

function Read-Partage([string]$Chemin) {
    $fs = [IO.File]::Open($Chemin, 'Open', 'Read', 'ReadWrite,Delete')
    try { $r = New-Object IO.StreamReader($fs, [Text.Encoding]::UTF8); return $r.ReadToEnd() } finally { $fs.Dispose() }
}

# Connexions du processus : première vue et nom d'hôte d'après le cache DNS de Windows (le relevé d'adresse seul ne dit pas qui c'est).
$connexions = @{}
$debutRelevé = Get-Date
function Get-NomsDns {
    @(Get-DnsClientCache -ErrorAction SilentlyContinue | ForEach-Object { "$($_.Entry)=$($_.Data)" })
}
$dnsAvant = @{}
foreach ($n in (Get-NomsDns)) { $dnsAvant[$n] = $true }
function Note-Connexions([int]$ProcId) {
    Get-NetTCPConnection -OwningProcess $ProcId -State Established -ErrorAction SilentlyContinue |
        Where-Object { $_.RemoteAddress -notmatch '^(127\.|::1|0\.0\.0\.0)' } |
        ForEach-Object {
            $cle = "$($_.RemoteAddress):$($_.RemotePort)"
            if (-not $connexions.ContainsKey($cle)) {
                $ip = $_.RemoteAddress
                $nom = @(Get-DnsClientCache -ErrorAction SilentlyContinue | Where-Object { $_.Data -eq $ip } | ForEach-Object { $_.Entry } | Select-Object -Unique) -join ','
                $connexions[$cle] = @{ Ip = $ip; Vu = [int]((Get-Date) - $debutRelevé).TotalSeconds; Nom = $nom; Fin = 0 }
            }
            $connexions[$cle].Fin = [int]((Get-Date) - $debutRelevé).TotalSeconds
        }
}

function Invoke-Plan([string]$Plan) {
    $cfg = ''
    $missionJeu = $null
    if ($Plan -eq 'Init') {
        # playMission lit le dossier Missions du jeu ; copie temporaire, retirée à la fin.
        $missionJeu = Join-Path (Split-Path -Parent $ArmaExe) "Missions\$NomMission"
        if (Test-Path -LiteralPath $missionJeu) { Write-Host "[ATTENTION] $missionJeu existe déjà : plan -init sauté."; return @{ Etat = 'NON_DEMARRE'; Rpt = $null; Relance = $false; Plan = $Plan; Debut = (Get-Date) } }
        try { Copy-Item -LiteralPath $MissionRun -Destination $missionJeu -Recurse }
        catch { Write-Host "[ATTENTION] Copie dans Missions impossible : $($_.Exception.Message)"; return @{ Etat = 'NON_DEMARRE'; Rpt = $null; Relance = $false; Plan = $Plan; Debut = (Get-Date) } }
    } else {
        $cfg = Join-Path $Sortie 'autotest.cfg'
        $texte = "class TestMissions`r`n{`r`n`tclass COMSPEC_Autotest`r`n`t{`r`n`t`tcampaign = `"`";`r`n`t`tmission = `"$MissionRun`";`r`n`t};`r`n};`r`n"
        [IO.File]::WriteAllText($cfg, $texte, (New-Object Text.UTF8Encoding($false)))
    }
    $debut = Get-Date
    $arguments = Get-ArgsArma $Plan $cfg
    Write-Host "Lancement ($Plan) : $ArmaExe $arguments"
    Add-Content -LiteralPath (Join-Path $Sortie 'lancement.txt') -Value ("[{0}] {1} {2}" -f $debut, $ArmaExe, $arguments) -Encoding UTF8
    $parent = Start-Arma $arguments
    $debutRelevé = $debut
    $relance = $false
    $etat = 'EN_COURS'
    $rpt = $null
    $limite = $debut.AddMinutes($DureeMaxMinutes)
    $limiteDebut = $debut.AddSeconds($DemarrageMaxSecondes)
    $finVueA = $null
    $vuDebut = $false
    $vuFin = $false
    $pos = [int64]0
    $reste = ''
    $prochainReleve = Get-Date
    try {
        while ($true) {
            Start-Sleep -Milliseconds 150
            $p = Get-NotreArma $parent
            if ($p -and $parent.HasExited) { $relance = $true }
            if ($p -and (Get-Date) -ge $prochainReleve) { Note-Connexions $p.Id; $prochainReleve = (Get-Date).AddSeconds(3) }
            if (-not $rpt) {
                $rpt = Get-ChildItem -LiteralPath $RptDir -Filter '*.rpt' -ErrorAction SilentlyContinue | Where-Object { $_.LastWriteTime -ge $debut } | Sort-Object LastWriteTime -Descending | Select-Object -First 1
            }
            # Lignes nouvelles du RPT seulement : la mission attend la capture quelques instants.
            $neuf = ''
            if ($rpt) {
                try {
                    $fs = [IO.File]::Open($rpt.FullName, 'Open', 'Read', 'ReadWrite,Delete')
                    try {
                        if ($fs.Length -gt $pos) {
                            [void]$fs.Seek($pos, 'Begin')
                            $buf = New-Object byte[] ($fs.Length - $pos)
                            $n = $fs.Read($buf, 0, $buf.Length)
                            $pos += $n
                            $neuf = [Text.Encoding]::UTF8.GetString($buf, 0, $n)
                        }
                    } finally { $fs.Dispose() }
                } catch { $neuf = '' }
            }
            if ($neuf) {
                $tout = $reste + $neuf
                $i = $tout.LastIndexOf("`n")
                if ($i -ge 0) {
                    $reste = $tout.Substring($i + 1)
                    foreach ($ligne in $tout.Substring(0, $i).Split("`n")) {
                        if ($ligne -match 'AUTOTEST\|CLICK\|([^|"\r]+)\|([0-9.]+)\|([0-9.]+)\|([0-9]+)') { if ($p) { Invoke-Clic $p $Matches[1] ([double]::Parse($Matches[2], [Globalization.CultureInfo]::InvariantCulture)) ([double]::Parse($Matches[3], [Globalization.CultureInfo]::InvariantCulture)) ([int]$Matches[4]) } }
                        elseif ($ligne -match 'AUTOTEST\|SHOT\|([^"\r]+)') { if ($p) { Invoke-Capture $p $Matches[1] } }
                        elseif ($ligne -match 'AUTOTEST\|DEBUT') { if (-not $vuDebut) { $vuDebut = $true; Write-Host 'Mission démarrée.'; if ($p) { [void][AtCap]::Front($p.MainWindowHandle) } } }
                        elseif ($ligne -match 'AUTOTEST\|FIN') { $vuFin = $true }
                    }
                } else { $reste = $tout }
            }
            if ($vuFin) {
                if (-not $finVueA) { $finVueA = Get-Date; Write-Host 'Mission terminée : fermeture du jeu.' }
                if (-not $p -or ((Get-Date) - $finVueA).TotalSeconds -gt 20) { $etat = 'FINI'; break }
            }
            if (-not $p) {
                # Ni processus d'origine, ni relancé : le jeu est fermé.
                if (((Get-Date) - $debut).TotalSeconds -gt 30) { $etat = $(if ($vuDebut) { 'INTERROMPU' } else { 'NON_DEMARRE' }); break }
            }
            if (-not $vuDebut -and (Get-Date) -gt $limiteDebut) { $etat = 'NON_DEMARRE'; break }
            if ((Get-Date) -gt $limite) { $etat = 'DEPASSEMENT'; break }
        }
    } finally {
        Stop-NotreArma (Get-NotreArma $parent)
        if ($missionJeu -and (Test-Path -LiteralPath $missionJeu)) { Remove-Item -LiteralPath $missionJeu -Recurse -Force -ErrorAction SilentlyContinue }
    }
    if ($rpt) { $rpt = Get-Item -LiteralPath $rpt.FullName }
    return @{ Etat = $etat; Rpt = $rpt; Relance = $relance; Plan = $Plan; Debut = $debut }
}

$plans = switch ($Lancement) { 'Auto' { @('Init', 'Autotest') } default { @($Lancement) } }
$res = $null
foreach ($plan in $plans) {
    $res = Invoke-Plan $plan
    if ($res.Etat -ne 'NON_DEMARRE') { break }
    Write-Host "Plan $plan : la mission n'a pas démarré."
}

# ---------- Fichiers partagés rendus ----------
$rendus = New-Object System.Collections.ArrayList
foreach ($f in $sauvegardes.Keys) {
    if (-not (Test-Path -LiteralPath $f) -or (Get-FileHash -LiteralPath $f).Hash -ne (Get-FileHash -LiteralPath $sauvegardes[$f]).Hash) {
        Copy-Item -LiteralPath $sauvegardes[$f] -Destination $f -Force
        [void]$rendus.Add($f)
    }
}

# ---------- Rapport ----------
$rap = New-Object System.Collections.ArrayList
function L([string]$T = '') { [void]$rap.Add($T) }
L "COMSPEC autotest $stamp : plan $($res.Plan), état $($res.Etat)"
L "Mods : $($listeMods -join ' ; ')"
foreach ($a in $avert) { L "[ATTENTION] $a" }

$ko = @(); $skip = @(); $oks = @(); $visuel = @(); $infos = @(); $fct = @(); $perfAt = @(); $shots = @(); $frame = @(); $warn = @(); $perf = @(); $manquants = @()
$erreurs = @{}; $autres = 0; $etape = '(hors étape)'
if ($res.Rpt) {
    $copieRpt = Join-Path $Sortie $res.Rpt.Name
    Copy-Item -LiteralPath $res.Rpt.FullName -Destination $copieRpt -Force
    L "RPT : $copieRpt"
    $bloc = $null
    foreach ($ligne in (Read-Partage $res.Rpt.FullName) -split "`r?`n") {
        $l = ($ligne -replace '^\s*\d+:\d+:\d+\s+', '').TrimEnd()
        if ($l -match 'AUTOTEST\|([A-Z]+)\|?(.*)$') {
            $k = $Matches[1]; $v = ($Matches[2] -replace '"+$', '')
            switch ($k) {
                'BEGIN' { $etape = $v }
                'END' { $etape = '(hors étape)' }
                'OK' { $oks += $v }
                'KO' { $ko += $v }
                'SKIP' { $skip += $v }
                'VISUEL' { $visuel += $v }
                'INFO' { $infos += $v }
                'FCT' { $fct += $v }
                'PERF' { $perfAt += $v }
                'SHOT' { $shots += $v }
            }
            continue
        }
        if ($l -match '^Error in expression') { $bloc = @{ Texte = $l; Msg = ''; Etape = $etape; N = 0 }; continue }
        if ($bloc) {
            if ($l -match '^Error (?!position)(.+)$') { $bloc.Msg = $Matches[1]; continue }
            if ($l -match '^File (.+?)(?:\.\.\.)?, line (\d+)') {
                $fichier = $Matches[1]; $num = $Matches[2]
                $estNous = ($fichier -match '(?i)comspec') -or ($bloc.Texte -match '(?i)comspec_')
                if ($estNous) {
                    $cle = "${fichier}:$num $($bloc.Msg)"
                    if (-not $erreurs.ContainsKey($cle)) { $erreurs[$cle] = @{ N = 0; Etape = $bloc.Etape } }
                    $erreurs[$cle].N++
                } else { $autres++ }
                $bloc = $null
                continue
            }
            # Expression sur plusieurs lignes : le bloc reste ouvert jusqu'à sa ligne File, dans une limite raisonnable.
            $bloc.Texte += $l
            $bloc.N++
            if ($bloc.N -gt 80) { $bloc = $null }
            continue
        }
        if ($l -match '\[FRAME\]') { $frame += $l }
        elseif ($l -match '\[PERF\]') { $perf += $l }
        elseif ($l -match '\[WARN(ING)?\]') { $warn += $l }
        elseif ($l -match '(?i)(cannot (open|load)|missing|not found).*comspec|comspec.*(cannot (open|load)|not found)') { $manquants += $l }
    }
} else {
    L "RPT : aucun nouveau fichier trouvé dans $RptDir"
}

L ''
L "Étapes : $($oks.Count) OK, $($ko.Count) KO, $($skip.Count) SKIP"
foreach ($x in $ko) { L "  KO   $x" }
foreach ($x in $skip) { L "  SKIP $x" }
L ''
L "Erreurs de script dont un fichier COMSPEC est cause : $($erreurs.Count) distinctes (hors COMSPEC : $autres)"
foreach ($c in ($erreurs.Keys | Sort-Object)) { L ("  {0}x [{1}] {2}" -f $erreurs[$c].N, $erreurs[$c].Etape, $c) }
L ''
L "Contrôle des fonctions (CfgFunctions) :"
foreach ($x in $fct) { L "  $x" }
if ($fct.Count -gt 0) {
    @($fct | Where-Object { $_ -notmatch '^TOTAL' }) | Set-Content -LiteralPath (Join-Path $Sortie 'fonctions_manquantes.txt') -Encoding UTF8
}
L ''
L "[FRAME] : $($frame.Count) ; [WARN] : $($warn.Count) ; [PERF] : $($perf.Count) ; fichiers COMSPEC manquants : $($manquants.Count)"
foreach ($x in ($frame | Select-Object -First 10)) { L "  $x" }
foreach ($x in ($warn | Select-Object -Unique | Select-Object -First 25)) { L "  $x" }
foreach ($x in ($manquants | Select-Object -Unique | Select-Object -First 15)) { L "  $x" }
L ''
L 'Banc réseau :'
foreach ($x in $perfAt) { L "  $x" }
foreach ($x in ($perf | Where-Object { $_ -match 'Boucles 30 s|Image figée' } | Select-Object -Last 12)) { L "  $x" }
L ''
L 'À contrôler à l''œil sur les captures :'
foreach ($x in $visuel) { L "  $x" }
L ''
$journalCaptures | Set-Content -LiteralPath (Join-Path $Sortie 'captures.txt') -Encoding UTF8
$imgs = @(Get-ChildItem -LiteralPath $dossierCaptures -Filter '*.jpg' | Sort-Object Name)
$ratees = @($journalCaptures | Where-Object { $_ -match '\|ECHEC|\|UNIE|\|printwindow' })
L "Captures : $($imgs.Count) fichiers sur $($shots.Count) demandés, dans $dossierCaptures"
foreach ($x in ($ratees | Select-Object -First 20)) { L "  $x" }
# Planches contact : 12 vignettes par planche.
$dossierPlanches = Join-Path $Sortie 'planches'
[void](New-Item -ItemType Directory -Force -Path $dossierPlanches)
$np = 0
for ($k = 0; $k -lt $imgs.Count; $k += 12) {
    $np++
    $lot = @($imgs | Select-Object -Skip $k -First 12 | ForEach-Object { $_.FullName })
    try { [AtCap]::Planche([string[]]$lot, (Join-Path $dossierPlanches ('planche_{0:00}.jpg' -f $np)), 3, 640) } catch { L "  Planche $np impossible : $($_.Exception.Message)" }
}
L "Planches contact : $np, dans $dossierPlanches"
L ''
$figees = @($perf | ForEach-Object { if ($_ -match 'Image figée : (\d+) ms, dont environ (\d+) ms.*page (\w*),') { [pscustomobject]@{ Ms = [int]$Matches[1]; Mod = [int]$Matches[2]; Page = $Matches[3] } } })
if ($figees.Count) {
    L ("Images figées : {0}, pire {1} ms, part mesurée dans les boucles du mod au plus {2} ms" -f $figees.Count, ($figees | Measure-Object Ms -Maximum).Maximum, ($figees | Measure-Object Mod -Maximum).Maximum)
    foreach ($g in ($figees | Group-Object Page | Sort-Object Count -Descending | Select-Object -First 8)) { L ("  {0} : {1} fois, pire {2} ms" -f $g.Name, $g.Count, ($g.Group | Measure-Object Ms -Maximum).Maximum) }
}
$bilans = @($perf | ForEach-Object { if ($_ -match 'Boucles 30 s : (\d+) img/s') { [int]$Matches[1] } })
if ($bilans.Count) { L ("Bilans de 30 s : {0}, de {1} à {2} img/s" -f $bilans.Count, ($bilans | Measure-Object -Minimum).Minimum, ($bilans | Measure-Object -Maximum).Maximum) }
L ''
$ts = Get-Process -Name 'ts3client_win64', 'ts3client_win32', 'TeamSpeak' -ErrorAction SilentlyContinue
L ("TeamSpeak pendant le test : {0}" -f $(if ($ts) { 'lancé (le test ne l''utilise pas)' } else { 'absent : ACRE tourne sans plugin, aucun son ni parole testés' }))
if ($res.Relance) { L '[ATTENTION] Arma s''est relancé (Steam ?) : proxy et paramètres d''environnement peut-être perdus, voir les connexions ci-dessous.' }
$cAthena = @()
L ("Proxy mort sur les DLL du mod : {0}" -f $(if ($SansProxy) { 'NON (-SansProxy)' } else { 'oui' }))
if ($connexions.Count) {
    L 'Connexions Internet du processus Arma (relevé toutes les 3 s, depuis le lancement) :'
    foreach ($c in ($connexions.Keys | Sort-Object)) {
        $e = $connexions[$c]
        L ("  {0}  de {1} s à {2} s  nom DNS : {3}" -f $c, $e.Vu, $e.Fin, $(if ($e.Nom) { $e.Nom } else { '(inconnu)' }))
    }
} else { L 'Connexions Internet du processus Arma : aucune vue' }
$dnsApres = @(Get-NomsDns | Where-Object { -not $dnsAvant.ContainsKey($_) })
L "Noms résolus par Windows pendant le test (tout le PC, $($dnsApres.Count)) :"
foreach ($x in ($dnsApres | Select-Object -First 40)) { L "  $x" }
$ipAthena = @()
foreach ($hote in 'athena.ttrd.fr', 'comspec.ttrd.fr', 'ttrd.fr') { try { $ipAthena += [Net.Dns]::GetHostAddresses($hote) | ForEach-Object { $_.ToString() } } catch { } }
$cAthena = @($connexions.Keys | Where-Object { ($ipAthena -contains $connexions[$_].Ip) -or ($connexions[$_].Nom -match 'ttrd\.fr') })
$dnsAthena = @($dnsApres | Where-Object { $_ -match 'ttrd\.fr' })
if ($SansProxy) {
    if ($cAthena.Count -or $dnsAthena.Count) { L ("LICENCE KO : le jeu a joint Athena, réponse Non : {0} {1}" -f ($cAthena -join ', '), ($dnsAthena -join ', ')) }
    else { L 'LICENCE OK : aucune connexion ni résolution DNS vers Athena, réponse Non, proxy retiré' }
} else {
    L 'Licence « ne transmet rien » : NON vérifiée par ce passage (proxy mort). Relancer avec -SansProxy une fois #1461 fusionnée.'
}
if ($rendus.Count) { L ("Fichiers partagés restaurés depuis la sauvegarde : {0}" -f ($rendus -join ', ')) }
L "Profil de test : $(Join-Path $Docs "Arma 3 - Other Profiles\$Profil") (profil $Profil, à supprimer à volonté)"

$code = 0
if ($res.Etat -ne 'FINI') { $code = 2 }
elseif ($ko.Count -gt 0 -or $erreurs.Count -gt 0 -or ($SansProxy -and ($cAthena.Count -gt 0 -or $dnsAthena.Count -gt 0))) { $code = 1 }
L ''
L "Code de sortie : $code (0 bon, 1 KO ou erreur COMSPEC, 2 mission non terminée)"
$fichierRapport = Join-Path $Sortie 'rapport.txt'
$rap | Set-Content -LiteralPath $fichierRapport -Encoding UTF8
$rap | ForEach-Object { Write-Host $_ }
Write-Host "Rapport : $fichierRapport"
exit $code
