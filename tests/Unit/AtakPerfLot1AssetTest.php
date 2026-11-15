<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Perf, lot 1 (gels du 15/11) : écriture du profil à un moment calme par une seule fonction, mesure étendue à tout le
 * code de l'ATAK natif (boucles, rappels différés, appels et rappels de DLL), horloge des tâches différées.
 * Lecture du code seulement : rien de tout cela n'a tourné en jeu.
 */
final class AtakPerfLot1AssetTest extends TestCase
{
    private const MAIN = __DIR__ . '/../../mod/COMSPEC_ATAK_Native/Sources/addons/main';

    /** Fichiers touchés par le lot. */
    private const FILES = [
        'functions/core/fn_perfAcc.sqf', 'functions/core/fn_profileSave.sqf', 'functions/core/fn_saveProfile.sqf',
        'functions/core/fn_frameQueue.sqf', 'functions/core/fn_waitAndExec.sqf', 'functions/core/fn_profiler.sqf',
        'functions/core/fn_perfLog.sqf', 'functions/network/fn_extensionCall.sqf', 'functions/network/fn_extAwait.sqf',
        'functions/network/fn_meshGraph.sqf', 'functions/network/fn_mpuLink.sqf', 'functions/network/fn_mpu5.sqf',
        'functions/network/fn_jammer.sqf', 'functions/shell/fn_captureServer.sqf', 'functions/datalink/fn_dataInit.sqf',
        'functions/bluetooth/fn_btInit.sqf', 'functions/map/fn_markerWebSweep.sqf',
        'XEH_preInit.sqf', 'XEH_postInitClient.sqf', 'XEH_postInitServer.sqf',
    ];

    private function read(string $rel): string
    {
        $text = file_get_contents(self::MAIN . '/' . $rel);
        self::assertIsString($text, $rel);

        return str_replace("\r\n", "\n", $text);
    }

    /** Code sans commentaires ni contenu des chaînes. */
    private function code(string $rel): string
    {
        $code = preg_replace('~/\*.*?\*/~s', '', $this->read($rel)) ?? '';
        $code = preg_replace('~"(?:[^"]|"")*"~s', '""', $code) ?? '';
        $code = preg_replace('~//[^\n]*~', '', $code) ?? '';

        return preg_replace("~'(?:[^']|'')*'~s", "''", $code) ?? '';
    }

    /** @return list<string> fichiers SQF de l'addon, chemins relatifs avec des barres obliques */
    private function sqfFiles(): array
    {
        $out = [];
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(self::MAIN, \FilesystemIterator::SKIP_DOTS));
        foreach ($it as $file) {
            if ($file->isFile() && strtolower($file->getExtension()) === 'sqf') {
                $out[] = str_replace('\\', '/', substr($file->getPathname(), strlen(self::MAIN) + 1));
            }
        }
        sort($out);

        return $out;
    }

    public function testSqfDelimitersAreBalanced(): void
    {
        foreach (self::FILES as $rel) {
            $code = $this->code($rel);
            foreach (['{' => '}', '[' => ']', '(' => ')'] as $open => $close) {
                self::assertSame(substr_count($code, $open), substr_count($code, $close), "équilibre $open$close dans $rel");
            }
        }
    }

    public function testNothingIsShownOnScreen(): void
    {
        // Mesure et écriture du profil : tout va au RPT (diag_log), jamais à l'écran.
        $silent = [
            'functions/core/fn_perfAcc.sqf', 'functions/core/fn_profileSave.sqf', 'functions/core/fn_saveProfile.sqf',
            'functions/core/fn_frameQueue.sqf', 'functions/core/fn_waitAndExec.sqf', 'functions/core/fn_profiler.sqf',
            'functions/core/fn_perfLog.sqf',
        ];
        $screen = ['hint', 'hintSilent', 'hintC', 'systemChat', 'sideChat', 'globalChat', 'groupChat', 'cutText', 'titleText', 'cutRsc',
            'titleRsc', 'BIS_fnc_showNotification', 'BIS_fnc_dynamicText', 'comspec_atak_native_fnc_notify', 'comspec_atak_native_fnc_toast',
            'CBA_fnc_notify', 'ctrlCreate', 'createDialog', 'createDisplay'];
        foreach ($silent as $rel) {
            $code = $this->code($rel);
            foreach ($screen as $cmd) {
                self::assertSame(0, preg_match('/\b' . preg_quote($cmd, '/') . '\b/', $code), "$cmd dans $rel");
            }
        }
        // Les lignes ajoutées ailleurs par le lot n'affichent rien non plus : mêmes commandes absentes des blocs de mesure.
        $pre = $this->read('XEH_preInit.sqf');
        $a = strpos($pre, 'COMSPEC_ATAK_PfhProbe = {');
        $b = strpos($pre, '// Langue du mod (par joueur)');
        self::assertTrue($a !== false && $b !== false && $b > $a);
        $block = substr($pre, (int) $a, (int) $b - (int) $a);
        foreach ($screen as $cmd) {
            self::assertSame(0, preg_match('/\b' . preg_quote($cmd, '/') . '\b/', $block), "$cmd dans la sonde des boucles");
        }
    }

    public function testProfileIsWrittenByOneDeferredFunction(): void
    {
        // Une seule fonction écrit le profil dans l'ATAK natif. Les écrans du TOC (lot 4) gardent encore leurs appels directs.
        $writers = [];
        foreach ($this->sqfFiles() as $rel) {
            if (preg_match('/\bsaveProfileNamespace\b/', $this->code($rel)) === 1) {
                $writers[] = $rel;
            }
        }
        $allowed = ['functions/core/fn_profileSave.sqf', 'functions/toc/fn_tocLayout.sqf', 'functions/toc/fn_tocManager.sqf'];
        self::assertSame([], array_values(array_diff($writers, $allowed)), 'écriture directe du profil hors de fn_profileSave');
        self::assertContains('functions/core/fn_profileSave.sqf', $writers);
        $save = $this->code('functions/core/fn_profileSave.sqf');
        self::assertSame(1, preg_match_all('/\bsaveProfileNamespace\b/', $save));

        // L'écriture regroupée ne fait que marquer le profil ; hors boucle du téléphone, elle garde son échéance de 60 s.
        $grouped = $this->read('functions/core/fn_saveProfile.sqf');
        self::assertStringContainsString('missionNamespace setVariable ["COMSPEC_ProfileSavePending", true];', $grouped);
        self::assertStringContainsString('if (missionNamespace getVariable ["COMSPEC_ATAK_ProfileTickOn", false]) exitWith {};', $grouped);
        self::assertStringContainsString('}, [], 60] call comspec_atak_native_fnc_waitAndExec;', $grouped);

        // Moment calme : ni tir récent, ni véhicule en mouvement, ni téléphone affiché ; menu Échap, arrière-plan ou
        // joueur mort : tout de suite.
        $fn = $this->read('functions/core/fn_profileSave.sqf');
        self::assertStringContainsString('if (_mode isEqualTo "calm") exitWith {', $fn);
        self::assertStringContainsString('if (!isNull (findDisplay 49) || {!isGameFocused}) exitWith { true };', $fn);
        self::assertStringContainsString('if (_quiet < 10 || {(getSuppression player) > 0.05}) exitWith { false };', $fn);
        self::assertStringContainsString('if (_veh isNotEqualTo player && {(vectorMagnitude (velocity _veh)) > 1.4}) exitWith { false };', $fn);
        self::assertStringContainsString('if (!isNull ([] call comspec_atak_native_fnc_display) || {diag_tickTime - (missionNamespace getVariable ["COMSPEC_ATAK_PhoneShownAt", -100]) < 5}) exitWith { false };', $fn);
        // Attente de plus de 10 min : seul l'échange de tirs retient encore l'écriture.
        self::assertStringContainsString('if (_why isEqualTo true) exitWith { _quiet >= 3 };', $fn);
        self::assertStringContainsString('private _late = _age >= 600;', $fn);
        self::assertStringContainsString('if !(["calm", _late] call comspec_atak_native_fnc_profileSave) exitWith {};', $fn);
        // Regroupement : 30 s après la première modification, 120 s entre deux écritures.
        self::assertStringContainsString('if (_age < 30) exitWith {};', $fn);
        self::assertStringContainsString('missionNamespace setVariable ["COMSPEC_ATAK_ProfileSaveAt", diag_tickTime + 120];', $fn);
        // Tirs suivis sur l'unité du joueur, reposés à chaque changement d'unité.
        self::assertStringContainsString('forEach ["FiredMan", "FiredNear", "Hit"];', $fn);
        self::assertStringContainsString('}, true] call CBA_fnc_addPlayerEventHandler;', $fn);
        // Chaque écriture laisse sa ligne dans le RPT.
        self::assertStringContainsString('[PERF] Écriture du profil (%1, en attente depuis %2 s) : %3 ms', $fn);
    }

    public function testProfileIsFlushedAtMissionEndAndOnLeaving(): void
    {
        $client = $this->read('XEH_postInitClient.sqf');
        // L'échéance tourne dans la boucle de 2 s, le suivi des tirs est posé au postInit.
        self::assertStringContainsString('["init"] call comspec_atak_native_fnc_profileSave;', $client);
        self::assertStringContainsString('["tick"] call comspec_atak_native_fnc_profileSave;', $client);
        // Fin de mission (Ended, MPEnded) et fermeture de l'écran de jeu : tout ce qui attend est écrit, sauvegardes
        // périodiques comprises.
        self::assertSame(1, preg_match('/addMissionEventHandler \[_x, \{ \["end"\] call comspec_atak_native_fnc_deviceGuard; \["flush"\] call comspec_atak_native_fnc_profileSave; \}\];\s*\} forEach \["Ended", "MPEnded"\];/', $client));
        self::assertSame(1, preg_match('/displayAddEventHandler \["Unload", \{\s*\["selfFlush"\] call comspec_atak_native_fnc_distanceServer;[^}]*\["flush"\] call comspec_atak_native_fnc_profileSave;\s*\}\];/', $client));
        $server = $this->read('XEH_postInitServer.sqf');
        self::assertStringContainsString('addMissionEventHandler [_x, { ["flush"] call comspec_atak_native_fnc_profileSave; }];', $server);
        $flush = $this->read('functions/core/fn_profileSave.sqf');
        self::assertSame(1, preg_match('/case "flush": \{\s*if \(\(missionNamespace getVariable \["COMSPEC_ATAK_ProfileDirty", false\]\) \|\| \{missionNamespace getVariable \["COMSPEC_ProfileSavePending", false\]\}\) then \{/', $flush));
    }

    public function testMeasurementCoversLoopsCallbacksAndDllCalls(): void
    {
        // Sonde permanente des boucles : toutes celles de COMSPEC_ATAK_PfhSpread passent par elle, seuil de 8 ms.
        $pre = $this->read('XEH_preInit.sqf');
        self::assertStringContainsString('[_comspecPfhA select 2, _comspecPfhT, 8, -1, _comspecPfhA select 3] call comspec_atak_native_fnc_perfAcc;', $pre);
        self::assertStringContainsString('if (_delay <= 0) exitWith { [_code, _delay, _args, _name] call COMSPEC_ATAK_PfhAdd; nil };', $pre);
        self::assertStringContainsString('[{ _this call COMSPEC_ATAK_PfhAdd; }, [_code, _delay, _args, _name], random (_delay min 5)] call comspec_atak_native_fnc_waitAndExec;', $pre);
        self::assertStringContainsString('[COMSPEC_ATAK_PfhProbe, _delay, [_code, _args] + ([_code, _name] call COMSPEC_ATAK_PfhName)] call CBA_fnc_addPerFrameHandler', $pre);
        // Le profilage n'enveloppe pas une seconde fois une boucle déjà sondée.
        self::assertStringContainsString('if (_f isEqualTo (missionNamespace getVariable ["COMSPEC_ATAK_PfhProbe", {}])) then { continue; };', $this->read('functions/core/fn_profiler.sqf'));

        // Boucles du périmètre enregistrées en direct : départ décalé et sonde.
        foreach (['functions/datalink/fn_dataInit.sqf', 'functions/bluetooth/fn_btInit.sqf', 'functions/network/fn_mpu5.sqf',
            'functions/network/fn_jammer.sqf', 'functions/shell/fn_captureServer.sqf'] as $rel) {
            $code = $this->code($rel);
            self::assertStringNotContainsString('CBA_fnc_addPerFrameHandler', $code, $rel);
            self::assertStringContainsString('call COMSPEC_ATAK_PfhSpread', $code, $rel);
        }

        // Appels de DLL : toujours chronométrés, ligne au-delà de 20 ms.
        self::assertStringContainsString('_pt, 20, -1, format ["DLL native %1 (direct)", _command]] call comspec_atak_native_fnc_perfAcc;', $this->read('functions/network/fn_extensionCall.sqf'));
        $await = $this->read('functions/network/fn_extAwait.sqf');
        self::assertStringContainsString('_pt, 20, -1, format ["DLL %1 %2 (direct)", _ext, _cmd]] call comspec_atak_native_fnc_perfAcc;', $await);
        self::assertStringContainsString('_pt, 20, -1, format ["DLL %1 Async %2 (dépôt)", _ext, _cmd]] call comspec_atak_native_fnc_perfAcc;', $await);
        // Rappels de la DLL et rappels différés du mod.
        $client = $this->read('XEH_postInitClient.sqf');
        self::assertStringContainsString('[["Rappels DLL natif", _fn] select (missionNamespace getVariable ["COMSPEC_ATAK_Prof", false]), _pt, 8, -1, _fn] call comspec_atak_native_fnc_perfAcc;', $client);
        $queue = $this->read('functions/core/fn_frameQueue.sqf');
        self::assertStringContainsString('if (_fqRan) then { ["file d\'image (rappels)", _fqT0] call comspec_atak_native_fnc_perfAcc; };', $queue);
        self::assertSame(2, substr_count($queue, 'if (diag_tickTime - _fqT > 0.008) then { [_item, _fqT] call _fqSlow; };'));
        // Budget par image : 5 ms de rappels pour les attentes échues, une au moins par image, les autres à l'image suivante.
        self::assertStringContainsString('while { _n > 0 && { (_w select 0 select 2) <= _now } && { !_fqOne || { diag_tickTime - _fqT0 < 0.005 } } } do {', $queue);

        // Code ordonnancé : l'image de départ est passée, la mesure étalée sur plusieurs images est rangée à part.
        $mesh = $this->read('functions/network/fn_meshGraph.sqf');
        self::assertSame(2, preg_match_all('/\["maillage constructeur \(tranche\)", _ct? select 6, -1, _ct? select 8\] call comspec_atak_native_fnc_perfAcc;/', $mesh));
        self::assertStringContainsString('["radio de données", _t0, -1, _f0] call comspec_atak_native_fnc_perfAcc;', $this->read('functions/network/fn_mpuLink.sqf'));
        $acc = $this->read('functions/core/fn_perfAcc.sqf');
        self::assertStringContainsString('private _spread = _frameNo >= 0 && {_frameNo isNotEqualTo diag_frameNo};', $acc);
        self::assertStringContainsString('if (_spread) then { _key = _key + " (étalé)"; };', $acc);
    }

    public function testFrameShareHasNoDoubleCountAndReportIsReal(): void
    {
        $acc = $this->read('functions/core/fn_perfAcc.sqf');
        // Mesures imbriquées comptées une fois : celles commencées après le t0 de la mesure qui se termine sont marquées
        // incluses (citées dans la ligne, hors de la somme).
        self::assertStringContainsString('while { _i >= 0 && {((_list select _i) select 0) >= _t0} } do { (_list select _i) set [3, false]; _i = _i - 1; };', $acc);
        self::assertStringContainsString('if ((count _list) < 64) then { _list pushBack [_t0, _dt, _key, true]; };', $acc);
        self::assertStringContainsString('{ if (_x select 3) then { _mine = _mine + (_x select 1); }; } forEach _list;', $acc);
        // Bilan : moyenne réelle d'images par seconde, 99e centile, focus et pause ; début de ligne inchangé (analyse.py).
        self::assertStringContainsString('private _fps = if (_frames > 0) then { round (_frames / _span) } else { round diag_fps };', $acc);
        self::assertStringContainsString('[PERF] Boucles 30 s : %1 img/s, images lentes (plus de 50 ms) %2 (pire %3 ms), drone %4', $acc);
        self::assertStringContainsString('image au 99e centile %8 ms, pire image %9 ms, focus %10 (%11 images hors focus), pause %12%13 ; %7', $acc);
        // Images hors focus : ni lentes ni saccades.
        self::assertStringContainsString('private _focus = !hasInterface || {isGameFocused};', $acc);
        self::assertStringContainsString('private _hitch = _focus && {_dt > 0.05} && {_dt > 3 * _avg};', $acc);
        // Image figée dès 150 ms, avec véhicule, Zeus, taille de la partie ; saccades : 6 par minute au plus.
        self::assertStringContainsString('if (_dt < 0.15 && {!_hitch}) exitWith {};', $acc);
        self::assertStringContainsString('[PERF] Image figée : %1 ms, dont environ %10 ms dans les boucles du mod (arsenal %2,', $acc);
        self::assertStringContainsString('focus %11, pause %12, véhicule %13, Zeus %14, unités %15, joueurs %16, marqueurs %17, %18, mesuré %19)', $acc);
        self::assertStringContainsString('if ((count _at) >= 6) exitWith { _w set [7, _at]; };', $acc);
        // Seuil : une ligne par libellé toutes les 10 s au plus.
        self::assertStringContainsString('if (diag_tickTime - _at < 10) exitWith { _warn set [_what, [_at, _muted + 1]]; };', $acc);
        // Serveur : taille de la partie et diffusions du graphe du maillage.
        self::assertStringContainsString('serveur : img/s mini %1, joueurs %2, unités %3, nœuds du maillage %4, diffusions du graphe %5', $acc);

        // La boucle d'image est câblée côté client et sur un serveur dédié.
        self::assertStringContainsString('[{ ["frame"] call comspec_atak_native_fnc_perfAcc; }, 0] call CBA_fnc_addPerFrameHandler;', $this->read('XEH_postInitClient.sqf'));
        $server = $this->read('XEH_postInitServer.sqf');
        self::assertStringContainsString('[{ ["frame"] call comspec_atak_native_fnc_perfAcc; }, 0] call CBA_fnc_addPerFrameHandler;', $server);
        self::assertStringContainsString('[{ ["report"] call comspec_atak_native_fnc_perfAcc; }, 30] call CBA_fnc_addPerFrameHandler;', $server);
    }

    public function testProfilingStaysOffByDefault(): void
    {
        // Les deux réglages de profil restent décochés par défaut (bloc des réglages inchangé par ce lot).
        $pre = $this->read('XEH_preInit.sqf');
        self::assertSame(1, preg_match('/\["comspec_atak_native_profiling", "CHECKBOX",\s*\[[^\n]*\],\s*\["COMSPEC", "ATAK · Debug"\], false, 0,/', $pre));
        self::assertSame(1, preg_match('/\["comspec_atak_native_perf_log", "CHECKBOX",\s*\[[^\n]*\],\s*\["COMSPEC", "ATAK · Debug"\], false, 0,/', $pre));
        // Profil d'une minute : à la demande seulement (événement CBA local), il s'arrête seul et rend la main aux réglages.
        $client = $this->read('XEH_postInitClient.sqf');
        self::assertSame(1, substr_count($client, '["minute"] call comspec_atak_native_fnc_profiler'));
        self::assertStringContainsString('["comspec_atak_native_profileMinute", { ["minute"] call comspec_atak_native_fnc_profiler; }] call CBA_fnc_addEventHandler;', $client);
        $prof = $this->read('functions/core/fn_profiler.sqf');
        self::assertStringContainsString('if !(missionNamespace getVariable ["comspec_atak_native_profiling", false]) then { ["stop"] call comspec_atak_native_fnc_profiler; };', $prof);
        self::assertStringContainsString('if !(missionNamespace getVariable ["comspec_atak_native_perf_log", false]) then { ["stop"] call comspec_atak_native_fnc_perfLog; };', $prof);
        // Aucun démarrage du profilage ailleurs que par son réglage, la mesure des performances et le profil d'une minute.
        foreach ($this->sqfFiles() as $rel) {
            if (preg_match('/"start"\]( select _this\])? call comspec_atak_native_fnc_profiler/', $this->read($rel)) === 1) {
                self::assertContains($rel, ['XEH_preInit.sqf', 'functions/core/fn_profiler.sqf', 'functions/core/fn_perfLog.sqf'], $rel);
            }
        }
    }

    public function testDeferredTasksKeepTheirDelayWhenMissionTimeJumps(): void
    {
        // Horloge de la file : le temps de mission sans ses sauts (arrivée en cours de partie).
        $wait = $this->read('functions/core/fn_waitAndExec.sqf');
        self::assertStringContainsString('private _due = COMSPEC_ATAK_WaitClock + (_delay max 0);', $wait);
        self::assertStringNotContainsString('CBA_missionTime', $this->code('functions/core/fn_waitAndExec.sqf'));
        $queue = $this->read('functions/core/fn_frameQueue.sqf');
        self::assertStringContainsString('if (_step > 5 || {_step < 0}) then {', $queue);
        self::assertStringContainsString('COMSPEC_ATAK_WaitClock = COMSPEC_ATAK_WaitClock + _step;', $queue);
        self::assertStringContainsString('private _now = COMSPEC_ATAK_WaitClock;', $queue);
        self::assertStringContainsString('[PERF] Horloge de mission : saut de %1 s en une image', $queue);
        // L'horloge repart de zéro avec les files, à chaque mission.
        self::assertSame(1, preg_match('/COMSPEC_ATAK_WaitQ = \[\];\s*COMSPEC_ATAK_FrameQueueBusy = \[\];\s*COMSPEC_ATAK_WaitClock = 0;/', $queue));
    }

    public function testMarkerWorkIsBounded(): void
    {
        // Relais des marqueurs vers Athena : budget épuisé, le reste va au carnet au lieu de relire toute la carte.
        $sweep = $this->read('functions/map/fn_markerWebSweep.sqf');
        self::assertStringNotContainsString('[-1, _rev] select (_budget > 0)', $sweep);
        self::assertStringContainsString('if ((_prev getOrDefault [_n, ""]) isEqualTo _sig) then { _next set [_n, _sig]; } else { _left set [_n, true]; };', $sweep);
        self::assertSame(2, substr_count($sweep, 'missionNamespace setVariable ["COMSPEC_ATAK_MkSweepRev", [_rev + 0.5, _rev] select ((count _left) isEqualTo 0)];'));
        // Cartouches ENI : purge au plus toutes les 5 s, heure calculée une fois par seconde.
        $client = $this->read('XEH_postInitClient.sqf');
        self::assertStringContainsString('if ((count _meta) > 600 && {diag_tickTime >= (missionNamespace getVariable ["COMSPEC_ATAK_MarkerMetaPurgeAt", 0])}) then {', $client);
        self::assertStringContainsString('if (_secAt isNotEqualTo _sec) then {', $client);
        self::assertSame(1, substr_count($client, 'BIS_fnc_timeToString) splitString ":") joinString ""'));
    }
}
