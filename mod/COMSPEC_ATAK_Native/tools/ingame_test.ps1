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
    # Groupes de la mission (vide : tous) : fonctions telephone carte antennes gps explo sse zeus acre reseau
    [string[]]$Groupes = @(),
    [int]$DureeMaxMinutes = 45,
    # Attente de la première ligne AUTOTEST avant de passer au plan suivant.
    [int]$DemarrageMaxSecondes = 300,
    # Auto : -init (copie temporaire dans Arma 3\Missions), puis -autotest si rien ne démarre.
    [ValidateSet('Auto', 'Init', 'Autotest')][string]$Lancement = 'Auto',
    [switch]$PleinEcran,
    [switch]$AvecSon,
    [string]$Sortie = ''
)
$ErrorActionPreference = 'Stop'
Set-StrictMode -Version 2

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
$dirsCaptures = @((Join-Path $Docs "Arma 3 - Other Profiles\$Profil\Screenshots"), (Join-Path $Docs 'Arma 3\Screenshots'))

# ---------- Lancement ----------
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
    foreach ($v in 'HTTP_PROXY', 'HTTPS_PROXY', 'ALL_PROXY') { $psi.EnvironmentVariables[$v] = 'http://127.0.0.1:9' }
    $psi.EnvironmentVariables['NO_PROXY'] = '127.0.0.1,localhost'
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

$connexions = @{}
function Note-Connexions([int]$ProcId) {
    Get-NetTCPConnection -OwningProcess $ProcId -State Established -ErrorAction SilentlyContinue |
        Where-Object { $_.RemoteAddress -notmatch '^(127\.|::1|0\.0\.0\.0)' } |
        ForEach-Object { $connexions["$($_.RemoteAddress):$($_.RemotePort)"] = $true }
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
    $relance = $false
    $etat = 'EN_COURS'
    $rpt = $null
    $limite = $debut.AddMinutes($DureeMaxMinutes)
    $limiteDebut = $debut.AddSeconds($DemarrageMaxSecondes)
    $finVueA = $null
    $vuDebut = $false
    try {
        while ($true) {
            Start-Sleep -Seconds 5
            $p = Get-NotreArma $parent
            if ($p -and $parent.HasExited) { $relance = $true }
            if ($p) { Note-Connexions $p.Id }
            if (-not $rpt) {
                $rpt = Get-ChildItem -LiteralPath $RptDir -Filter '*.rpt' -ErrorAction SilentlyContinue | Where-Object { $_.LastWriteTime -ge $debut } | Sort-Object LastWriteTime -Descending | Select-Object -First 1
            }
            $texteRpt = ''
            if ($rpt) { try { $texteRpt = Read-Partage $rpt.FullName } catch { $texteRpt = '' } }
            if (-not $vuDebut -and $texteRpt -match 'AUTOTEST\|DEBUT') { $vuDebut = $true; Write-Host 'Mission démarrée.' }
            if ($texteRpt -match 'AUTOTEST\|FIN') {
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
$dossierCaptures = Join-Path $Sortie 'captures'
[void](New-Item -ItemType Directory -Force -Path $dossierCaptures)
$copiees = 0
foreach ($d in $dirsCaptures) {
    if (Test-Path -LiteralPath $d) {
        Get-ChildItem -LiteralPath $d -Filter 'CAT_*.png' -ErrorAction SilentlyContinue | Where-Object { $res.Debut -and $_.LastWriteTime -ge $res.Debut } | ForEach-Object {
            Copy-Item -LiteralPath $_.FullName -Destination $dossierCaptures -Force
            $copiees++
        }
    }
}
L "Captures : $copiees fichiers sur $($shots.Count) demandés, dans $dossierCaptures"
L ''
$ts = Get-Process -Name 'ts3client_win64', 'ts3client_win32', 'TeamSpeak' -ErrorAction SilentlyContinue
L ("TeamSpeak pendant le test : {0}" -f $(if ($ts) { 'lancé (le test ne l''utilise pas)' } else { 'absent : ACRE tourne sans plugin, aucun son ni parole testés' }))
if ($res.Relance) { L '[ATTENTION] Arma s''est relancé (Steam ?) : proxy et paramètres d''environnement peut-être perdus, voir les connexions ci-dessous.' }
L ("Connexions Internet du processus Arma observées (toutes les 5 s) : {0}" -f $(if ($connexions.Count) { ($connexions.Keys -join ', ') } else { 'aucune' }))
if ($rendus.Count) { L ("Fichiers partagés restaurés depuis la sauvegarde : {0}" -f ($rendus -join ', ')) }
L "Profil de test : $(Join-Path $Docs "Arma 3 - Other Profiles\$Profil") (profil $Profil, à supprimer à volonté)"

$code = 0
if ($res.Etat -ne 'FINI') { $code = 2 }
elseif ($ko.Count -gt 0 -or $erreurs.Count -gt 0) { $code = 1 }
L ''
L "Code de sortie : $code (0 bon, 1 KO ou erreur COMSPEC, 2 mission non terminée)"
$fichierRapport = Join-Path $Sortie 'rapport.txt'
$rap | Set-Content -LiteralPath $fichierRapport -Encoding UTF8
$rap | ForEach-Object { Write-Host $_ }
Write-Host "Rapport : $fichierRapport"
exit $code
