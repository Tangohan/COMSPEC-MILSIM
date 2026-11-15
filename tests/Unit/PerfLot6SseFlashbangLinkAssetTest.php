<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Perf (lot 6) : pack SSE, Flashbang et COMSPEC Link (appels bloquants).
 * Lecture des sources : délimiteurs SQF équilibrés, aucun affichage à l'écran, garde-fous câblés (bornes par image,
 * empreinte avant décodage, attentes bornées), et pour le C# les invariants de sécurité et d'asynchronisme
 * (rien d'écrit sur disque sous le verrou de la position, Disconnect plafonné, résultats gardés bornés).
 * Aucun test ne dépend des fins de ligne du checkout ni des numéros de version. Les durées ne se mesurent qu'en jeu.
 */
final class PerfLot6SseFlashbangLinkAssetTest extends TestCase
{
    private const SSE = 'mod/@COMSPEC_SSE/addons/identity/';
    private const FB = 'mod/COMSPEC_Flashbang/Sources/addons/main/functions/';
    private const LINK = 'mod/UptoDate/COMSPECExtension/Extension.cs';

    /** Fichiers SQF modifiés par le lot. */
    private const SQF_FILES = [
        self::SSE . 'functions/fn_idAthenaPack.sqf',
        self::SSE . 'functions/fn_idInitQueue.sqf',
        self::SSE . 'functions/fn_idCoverAthena.sqf',
        self::SSE . 'functions/fn_idCoverImport.sqf',
        self::SSE . 'functions/fn_idServer.sqf',
        self::FB . 'fn_aiBatch.sqf',
        self::FB . 'fn_burst.sqf',
        self::FB . 'fn_fx.sqf',
    ];

    private static function read(string $p): string
    {
        $src = file_get_contents(dirname(__DIR__, 2) . '/' . $p);
        self::assertNotFalse($src, "{$p} : fichier lisible");

        return str_replace("\r\n", "\n", (string) $src);
    }

    /** Code SQF sans commentaires ni contenu des chaînes. */
    private static function code(string $src): string
    {
        return (string) preg_replace_callback(
            '~"(?:[^"]|"")*"|\'(?:[^\']|\'\')*\'|//[^\n]*|/\*.*?\*/~s',
            static function (array $m): string {
                $t = $m[0];
                if ($t[0] === '/') {
                    return str_repeat("\n", substr_count($t, "\n"));
                }

                return '""';
            },
            $src
        );
    }

    /** Bloc d'un « case "nom": { … }; » de fn_idAthenaPack (jusqu'au case suivant). */
    private static function packCase(string $name): string
    {
        $src = self::read(self::SSE . 'functions/fn_idAthenaPack.sqf');
        $start = strpos($src, "    case \"{$name}\": {");
        self::assertNotFalse($start, "case {$name} présent");
        $next = strpos($src, "\n    case \"", (int) $start + 10);

        return substr($src, (int) $start, ($next === false ? strlen($src) : $next) - (int) $start);
    }

    /** Corps d'une méthode C# (de sa signature à l'accolade fermante au niveau des membres). */
    private static function csMethod(string $signature): string
    {
        $src = self::read(self::LINK);
        $start = strpos($src, $signature);
        self::assertNotFalse($start, "méthode présente : {$signature}");
        $end = strpos($src, "\n    }\n", (int) $start);
        self::assertNotFalse($end, "fin de méthode : {$signature}");

        return substr($src, (int) $start, (int) $end - (int) $start);
    }

    public function testDelimiteursSqfEquilibres(): void
    {
        foreach (self::SQF_FILES as $file) {
            $code = self::code(self::read($file));
            foreach ([['{', '}'], ['[', ']'], ['(', ')']] as [$open, $close]) {
                self::assertSame(substr_count($code, $open), substr_count($code, $close), "{$file} : « {$open} » et « {$close} » équilibrés");
            }
        }
    }

    public function testAucunAffichageAEcranDansLesFichiersDuLot(): void
    {
        foreach (self::SQF_FILES as $file) {
            if (str_ends_with($file, 'fn_idServer.sqf')) {
                continue; // fichier historique : seul son bloc « pack » est vérifié ci-dessous
            }
            $code = self::code(self::read($file));
            self::assertSame(0, preg_match('/\b(hint|hintSilent|hintC|systemChat|cutText|titleText|globalChat|sideChat)\b/i', $code), "{$file} : aucun affichage à l'écran (mesures au RPT seulement)");
        }
        $server = self::read(self::SSE . 'functions/fn_idServer.sqf');
        $start = strpos($server, 'case "pack": {');
        self::assertNotFalse($start);
        $block = self::code(substr($server, (int) $start, (int) strpos($server, 'case "packFail": {') - (int) $start));
        self::assertSame(0, preg_match('/\b(hint|hintSilent|hintC|systemChat|cutText|titleText)\b/i', $block));
    }

    public function testMorceauxDuPaquetRecollesEnUneFois(): void
    {
        foreach (['fn_idAthenaPack.sqf', 'fn_idCoverAthena.sqf', 'fn_idCoverImport.sqf'] as $f) {
            $src = self::read(self::SSE . 'functions/' . $f);
            self::assertStringNotContainsString('_buf = _buf +', $src, "{$f} : plus de recopie du tampon à chaque morceau");
            self::assertStringContainsString('_parts pushBack (_c select [3]);', $src, $f);
            self::assertStringContainsString('_parts joinString ""', $src, $f);
        }
        // Garde-fou inchangé : 400 morceaux au plus pour le paquet complet.
        self::assertStringContainsString('for "_i" from 0 to ((_n min 400) - 1) do {', self::packCase('fetch'));
    }

    public function testEmpreinteDuPaquetAvantToutDecodage(): void
    {
        $apply = self::packCase('applyNow');
        $fp = strpos($apply, 'hashValue _fpText');
        $parse = strpos($apply, 'parseSimpleArray _raw');
        self::assertNotFalse($fp);
        self::assertNotFalse($parse);
        self::assertLessThan((int) $parse, (int) $fp, "l'empreinte du texte est calculée avant parseSimpleArray");
        // Un paquet inchangé sort avant le décodage ; l'empreinte de l'arbre reste le repli (ancien format).
        $exit = strpos($apply, 'if (_fpSure && {_fp isEqualTo (_reg getOrDefault ["packFp", ""])}) exitWith {');
        self::assertNotFalse($exit);
        self::assertLessThan((int) $parse, (int) $exit);
        self::assertStringContainsString('if (!_fpSure) then {', $apply);
        self::assertStringContainsString('_fp = hashValue str (_fpKeys apply { [_x, _top get _x] });', $apply);
        self::assertSame(1, substr_count($apply, 'parseSimpleArray _raw'), 'un seul décodage du paquet');
        // Date de génération et « since » retirées seulement si elles sont à coup sûr celles de la racine.
        self::assertStringContainsString('_raw find "[""settings"","', $apply);
        self::assertStringContainsString('_raw find "[""countries"","', $apply);
        self::assertStringContainsString('[["[""generated_at"",""", true], ["[""since"",""", false]]', $apply);
        self::assertStringContainsString('if (_i > _limit || {_j < 0}) then { _fpSure = false; }', $apply);
        // Le rechargement forcé par le Zeus (empreinte vidée) reste possible.
        self::assertStringContainsString('_reg set ["packFp", ""];', self::read(self::SSE . 'functions/fn_idServer.sqf'));
    }

    public function testApplicationDuPaquetEtaleeEtUneSeuleALaFois(): void
    {
        $apply = self::packCase('apply');
        self::assertStringContainsString('if (!canSuspend) exitWith { ["applyNow", _a] call comspec_sse_fnc_idAthenaPack; };', $apply);
        self::assertStringContainsString('waitUntil { !(_reg getOrDefault ["packApplying", false]) || {(diag_tickTime - _t) > 60} };', $apply, 'attente bornée à 60 s');
        self::assertLessThan((int) strpos($apply, '_reg set ["packApplying", false];'), (int) strpos($apply, '_reg set ["packApplying", true];'));
        self::assertStringContainsString('if (!isServer) exitWith {};', $apply);
        self::assertStringContainsString('if (!isServer) exitWith {};', self::packCase('applyNow'));
        // Paquet relayé par un Zeus : appliqué en tâche planifiée, toujours réservé à l'autorité.
        $server = self::read(self::SSE . 'functions/fn_idServer.sqf');
        $start = (int) strpos($server, 'case "pack": {');
        $block = substr($server, $start, (int) strpos($server, 'case "packFail": {') - $start);
        self::assertStringContainsString('if (_mode isEqualTo "full" && {[_me] call comspec_sse_fnc_isAuthority}) exitWith {', $block);
        self::assertStringContainsString('[_raw, _reg] spawn {', $block);
        self::assertStringContainsString('["applySelf", [_me, _raw]] call comspec_sse_fnc_idAthenaPack;', $block);
    }

    public function testRecherchesDuPaquetParTable(): void
    {
        $apply = self::packCase('applyNow');
        self::assertStringNotContainsString('(_reg get "watch") findIf', $apply, 'plus de parcours de tous les avis par avis');
        self::assertStringContainsString('private _watchRefs = createHashMapFromArray ((_reg get "watch") apply { [_x param [4, ""], true] });', $apply);
        self::assertStringContainsString('_watchRefs set [_ref, true];', $apply);
        self::assertStringContainsString('if (_ref in _watchRefs || {_gr isNotEqualTo "" && {_gr in _localRefs}}) then { continue; };', $apply);
        self::assertStringContainsString('private _revoked = createHashMapFromArray ((_top getOrDefault ["revoked", []]) apply { [str _x, true] });', $apply);
        // Mesure au RPT du serveur.
        self::assertStringContainsString("[COMSPEC SSE][PERF] SSE pack d'identités (application) : %1 ms sur %2 image(s), %3 octets.", $apply);
        self::assertStringContainsString('diag_log text format', $apply);
    }

    public function testInitialisationsParPersonneEnFile(): void
    {
        $cfg = self::read(self::SSE . 'config.cpp');
        self::assertStringContainsString('class idInitQueue {};', $cfg);
        self::assertStringContainsString("serverInit = \"[_this select 0, 'kit'] call comspec_sse_fnc_idInitQueue;\";", $cfg);
        self::assertStringContainsString("serverInit = \"[_this select 0, 'lore'] call comspec_sse_fnc_idInitQueue;\";", $cfg);
        $start = (int) strpos($cfg, 'class Extended_InitPost_EventHandlers {');
        $block = substr($cfg, $start, (int) strpos($cfg, 'class CfgWeapons {', $start) - $start);
        self::assertStringNotContainsString('CBA_fnc_waitAndExecute', $block, 'plus une tâche différée par personne');

        $queue = self::read(self::SSE . 'functions/fn_idInitQueue.sqf');
        self::assertStringContainsString('if (!isServer || {isNull _u}) exitWith {};', $queue);
        self::assertStringContainsString('private _left = 8;', $queue, '8 personnes au plus par image');
        self::assertStringContainsString('CBA_missionTime + ([1, 1.5] select (_kind isEqualTo "lore"))', $queue, 'mêmes délais : 1 s (kit), 1,5 s (lore)');
        self::assertStringContainsString('["kit", [_u]] call comspec_sse_fnc_idRegistry;', $queue);
        self::assertStringContainsString('["attach", [_u]] call comspec_sse_fnc_loreServer;', $queue);
        self::assertStringContainsString('[_pfh] call CBA_fnc_removePerFrameHandler;', $queue, 'la boucle s\'arrête file vide');
        self::assertSame(0, preg_match('/\b(waitUntil|sleep|uiSleep|spawn|execVM)\b/i', self::code($queue)), 'aucune attente ni fil non borné');
    }

    public function testFlashbangBudgetIaCommunParImage(): void
    {
        $batch = self::read(self::FB . 'fn_aiBatch.sqf');
        self::assertStringContainsString('private _n = 6 min count _queue;', $batch, 'borne de la 1.0.1 : 6 IA par image');
        self::assertStringContainsString('if ((comspec_fb_aiFrame select 0) != diag_frameNo) then { comspec_fb_aiFrame = [diag_frameNo, 0] };', $batch);
        self::assertStringContainsString('_n = _n min ((6 - (comspec_fb_aiFrame select 1)) max 0);', $batch);
        self::assertStringContainsString('comspec_fb_aiFrame set [1, (comspec_fb_aiFrame select 1) + _n];', $batch);
        self::assertStringContainsString('CBA_fnc_execNextFrame', $batch, 'le surplus passe à l\'image suivante, rien n\'est perdu');
        self::assertSame(0, preg_match('/\b(waitUntil|sleep|uiSleep|spawn|execVM)\b/i', self::code($batch)));
        // Borne de la 1.0.1 : 20 IA par détonation par défaut.
        self::assertStringContainsString('missionNamespace getVariable ["comspec_fb_aiMax", 20]', self::read(self::FB . 'fn_aiParams.sqf'));
        self::assertStringContainsString('_keyed select [0, _cfg select 7]', self::read(self::FB . 'fn_aiBurst.sqf'));
    }

    public function testFlashbangRafaleSansRefaireRayonsNiPoussiere(): void
    {
        $burst = self::read(self::FB . 'fn_burst.sqf');
        self::assertStringContainsString('private _occ = _ctx getOrDefault ["occl", []];', $burst);
        self::assertStringContainsString('{(_tick0 - (_occ select 0)) < 0.12}', $burst, 'reprise seulement sous 0,12 s');
        self::assertStringContainsString('{((_occ select 1) vectorDistance _cam) < 0.5}', $burst);
        self::assertStringContainsString('{((_occ select 2) vectorDistance _src) < 2}', $burst);
        self::assertStringContainsString('{(_occ select 4) == count _rays}', $burst);
        self::assertStringContainsString('_ctx set ["occl", [_tick0, _cam, _src, _nOpen, count _rays]];', $burst);
        self::assertSame(1, substr_count(self::code($burst), 'lineIntersectsSurfaces'), 'un seul jeu de rayons pour le son');
        // Rayon de recherche et réglages inchangés (aucun changement de comportement).
        self::assertStringContainsString('((missionNamespace getVariable ["comspec_fb_radiusMax", 25]) * (_env getOrDefault ["mult", 1])) max 60', $burst);

        $fx = self::read(self::FB . 'fn_fx.sqf');
        self::assertSame(2, substr_count($fx, 'if (_q >= 1 && {!_fused}) then {'), 'onde de choc et poussière non recréées sous 0,12 s');
        self::assertStringContainsString('private _fused = (_t - (_ctx getOrDefault ["lastLight", -1])) < 0.12;', $fx);
        self::assertStringContainsString('count comspec_fb_lights < 8', $fx, 'budget de 8 lumières inchangé');
    }

    public function testLinkDisconnectPlafonneSurLeFilAppelant(): void
    {
        $src = self::read(self::LINK);
        self::assertStringContainsString('private const int DisconnectBudgetMs = 1500;', $src);
        self::assertStringContainsString('private const int DisconnectLogFlushMs = 1000;', $src);
        self::assertStringContainsString('private const int DisconnectPostFloorMs = 500;', $src);
        $m = self::csMethod('private static string VerifyDisconnectSync(string callSign)');
        // Contrôles d'accès inchangés, avant tout envoi.
        self::assertLessThan((int) strpos($m, 'FlushDeviceLogsSync('), (int) strpos($m, 'return "ERR|not_connected";'));
        self::assertLessThan((int) strpos($m, 'FlushDeviceLogsSync('), (int) strpos($m, 'return "ERR|unauthorized";'));
        self::assertStringContainsString('TryBuildRequestUri(baseUrl, "/api/atak/disconnect"', $m);
        self::assertStringContainsString('FlushDeviceLogsSync(DisconnectLogFlushMs);', $m);
        self::assertStringContainsString('var post = Task.Run(() =>', $m);
        self::assertStringContainsString('Math.Max(DisconnectPostFloorMs, DisconnectBudgetMs - (int)started.ElapsedMilliseconds)', $m);
        self::assertStringContainsString('if (post.Wait(waitMs)) return post.Result;', $m);
        self::assertStringNotContainsString('SyncTimeoutSeconds', $m, 'plus de délai de 8 s sur ce chemin');
        // Mêmes réponses qu'avant.
        foreach (['"OK|disconnected"', '"ERR|unauthorized"', '"ERR|not_found"', '"ERR|http_" + code', '"ERR|timeout"'] as $answer) {
            self::assertStringContainsString($answer, $m);
        }
        // Le dernier envoi des journaux a un délai borné, jamais au-delà du délai général.
        self::assertStringContainsString('TimeSpan.FromMilliseconds(Math.Clamp(timeoutMs, 250, SyncTimeoutSeconds * 1000))', $src);
    }

    public function testLinkAucuneEcritureDisqueSousLeVerrouDeLaPosition(): void
    {
        $src = self::read(self::LINK);
        self::assertSame(0, preg_match('/lock \(CoalescedPositionLock\)\s*\{[^{}]*File\./', $src), 'aucun accès disque sous CoalescedPositionLock');
        $m = self::csMethod('private static void PersistQueueToDisk()');
        self::assertStringContainsString('positionSnapshot = _coalescedPosition;', $m);
        self::assertLessThan((int) strpos($m, 'File.WriteAllText(pos,'), (int) strpos($m, 'positionSnapshot = _coalescedPosition;'));
        self::assertStringContainsString('StripQueueSecrets(p.Body)', $m, 'les secrets restent retirés avant écriture');
        self::assertStringContainsString('lock (DiskQueueLock)', $m);
    }

    public function testLinkLectureDeLaFileEtMenageHorsDuFilDuJeu(): void
    {
        $timer = self::csMethod('private static void EnsureDrainTimer(bool rejitter = false)');
        $head = substr($timer, 0, (int) strpos($timer, 'lock (DrainTimerLock)'));
        self::assertSame(0, preg_match('/^\s*LoadQueueFromDisk\(\);/m', $head), 'plus de lecture directe du disque à chaque mise en file');
        self::assertStringContainsString('System.Threading.Volatile.Read(ref _diskQueueLoaded) == 0', $head);
        self::assertStringContainsString('Task.Run(static () => { try { LoadQueueFromDisk(); }', $head);
        // Les garde-fous de la relecture restent : une seule fois, taille bornée, adresse de confiance.
        $load = self::csMethod('private static void LoadQueueFromDisk()');
        self::assertStringContainsString('Interlocked.Exchange(ref _diskQueueLoaded, 1) != 0', $load);
        self::assertStringContainsString('QuarantineOversizedCache(dir, fifo, MaxDiskQueueBytes);', $load);
        self::assertStringContainsString('IsSecretSafeUri(u)', $load);

        $mirror = self::csMethod('private static string? MirrorCapture(string resolved)');
        $copy = strpos($mirror, 'File.Copy(resolved, dest, false);');
        $prune = strpos($mirror, '_ = Task.Run(() => PruneCaptureDir(dir));');
        self::assertNotFalse($copy);
        self::assertNotFalse($prune);
        self::assertLessThan((int) $prune, (int) $copy, 'la copie est faite avant de répondre, le ménage part ensuite');
        $pruneBody = self::csMethod('private static void PruneCaptureDir(string dir)');
        self::assertStringContainsString('if (i < 200 && kept <= CapturesMaxBytes) continue;', $pruneBody, 'mêmes limites : 200 fichiers, 1 Go');
    }

    public function testLinkResultatGardeBorneEtMemesControles(): void
    {
        $src = self::read(self::LINK);
        self::assertStringContainsString('private const int AsyncKeptMax = 32;', $src);
        self::assertStringContainsString('private const long AsyncKeptTtlMs = 120_000;', $src);
        self::assertStringContainsString('private const int AsyncTicketMaxLength = 64;', $src);
        self::assertStringContainsString('if (function is "Async" or "AsyncKeep")', $src);
        self::assertStringContainsString('if (command is "Async" or "AsyncKeep" or "AsyncPoll") return "ERR|async_nested";', $src);
        self::assertStringContainsString('if (!keep && _callback == null) return "ERR|no_callback";', $src);
        self::assertStringContainsString('if (AsyncKept.Count >= AsyncKeptMax) return "ERR|async_busy";', $src);
        self::assertStringContainsString('if (ticket.Length > AsyncTicketMaxLength) return "ERR|async_args";', $src);
        self::assertStringContainsString('if (kept.Result is null) return "PENDING|" + pollTicket;', $src);
        self::assertStringContainsString('AsyncKept.TryRemove(pollTicket, out _);', $src, 'un résultat ne se lit qu\'une fois');
        // Un seul chemin d'exécution : même porte (6 appels au plus), même marqueur de fil de fond.
        self::assertSame(1, substr_count($src, 'InAsyncWorker.Value = true;'));
        self::assertSame(1, substr_count($src, 'await AsyncGate.WaitAsync().ConfigureAwait(false);'));
        self::assertSame(2, substr_count($src, 'var direct = TryGetSyncResponse(command, rest);'));
        // Les refus hors « Async » des commandes réseau lourdes restent en place.
        self::assertGreaterThanOrEqual(6, substr_count($src, 'if (!InAsyncWorker.Value) return "ERR|use_async";'));
        self::assertStringContainsString('"Async", "AsyncKeep", "AsyncPoll", "Warmup"', $src);
    }
}
