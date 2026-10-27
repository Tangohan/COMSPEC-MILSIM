<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * ATAK natif, autorisations multijoueur : ce que le serveur et les clients ne croient plus d'un autre poste, et les
 * chemins légitimes du serveur qu'un contrôle d'émetteur trop large casserait.
 */
final class AtakMultiplayerAuthTest extends TestCase
{
    private const MAIN = 'mod/COMSPEC_ATAK_Native/Sources/addons/main';
    private const CONNECT = 'mod/UptoDate/Sources/comspec-overwatch-addons/connect';

    private function read(string $rel): string
    {
        return (string) file_get_contents(dirname(__DIR__, 2) . '/' . $rel);
    }

    /** Texte de $src qui va du repère $from (inclus) au repère $to suivant (exclu) ; vide si l'un manque. */
    private function between(string $src, string $from, string $to): string
    {
        $i = strpos($src, $from);
        $j = $i === false ? false : strpos($src, $to, $i + strlen($from));
        return $j === false ? '' : substr($src, (int) $i, $j - (int) $i);
    }

    /** Fichiers .sqf d'un dossier du dépôt, récursivement : [chemin relatif => source], dans un ordre stable. */
    private function sqfFiles(string $dir): array
    {
        $root = dirname(__DIR__, 2) . '/';
        $out = [];
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root . $dir, \FilesystemIterator::SKIP_DOTS));
        foreach ($it as $f) {
            if (strtolower($f->getExtension()) === 'sqf') {
                $out[substr($f->getPathname(), strlen($root))] = (string) file_get_contents($f->getPathname());
            }
        }
        ksort($out);
        return $out;
    }

    /** Actions de fn_compromise que le garde de tête refuse à un appel distant qui ne vient pas du serveur. */
    private function compromiseGuarded(): array
    {
        $src = $this->read(self::MAIN . '/functions/shell/fn_compromise.sqf');
        $head = $this->between($src, 'params [["_act", ""], ["_arg", []]];', 'switch (_act) do {');
        $cond = '/^if \((?:_act in \[([^\]]*)\]|!\(_act in \[([^\]]*)\]\)) && \{isRemoteExecutedJIP \|\| \{isMultiplayer && \{isRemoteExecuted\} && \{remoteExecutedOwner isNotEqualTo 2\}\}\}\) exitWith \{$/m';
        if (preg_match($cond, $head, $m) !== 1) {
            return [];
        }
        // « _act in [...] » : seules les actions listées ; « !(_act in [...]) » : toutes sauf celles-là.
        $except = str_starts_with($m[0], 'if (!(');
        preg_match_all('/"([^"]+)"/', $except ? $m[2] : $m[1], $listed);
        preg_match_all('/^    case "([^"]+)"/m', $src, $cases);
        return array_values(array_filter($cases[1], static fn (string $a): bool => in_array($a, $listed[1], true) !== $except));
    }

    public function testAdminRecoverStripsAServerLocalAiItself(): void
    {
        // « Reprendre le téléphone » (app Admin) tourne dans la pile du remoteExec de l'admin. Par l'événement
        // capLocal, CBA appelait le gestionnaire d'une IA locale au serveur dans cette même pile, et fn_captureServer y
        // refusait l'action « local » : l'IA gardait le téléphone alors que l'app annonçait « Téléphone repris ». IA
        // locale au serveur : retrait fait sur place. Ailleurs : ordre du serveur à fn_captureLocal (lot mpauth3,
        // SEC-023, l'événement capLocal est retiré).
        $recover = $this->between($this->read(self::MAIN . '/functions/admin/fn_adminServer.sqf'), 'case "recover": {', '"Téléphone repris à l\'IA');
        self::assertNotEmpty($recover);
        self::assertMatchesRegularExpression('/if \(local _ai\) then \{\s*if \(_x in \(assignedItems _ai\)\) then \{ _ai unlinkItem _x; \} else \{ _ai removeItem _x; \};\s*\} else \{\s*\["strip", _ai, _x\] remoteExecCall \["comspec_atak_native_fnc_captureLocal", _ai\];/', $recover);
        // La correction ne passe pas par le filtre : aucun ordre local n'est ouvert à un appel distant de fn_captureServer
        // (sinon un client ferait retirer, donner ou déplacer quoi que ce soit à une IA du serveur).
        self::assertStringContainsString('if (_remote && {!(_act in ["dropped", "validate", "dismiss", "athena"])}) exitWith {', $this->read(self::MAIN . '/functions/shell/fn_captureServer.sqf'));
    }

    public function testAthenaResultIsTakenOnlyFromTheSolicitedPlayerWhilePending(): void
    {
        // Résultat de la révocation chez Athena : n'importe quel client posait « ok » (même avant toute validation) ou
        // faisait passer la révocation au candidat suivant. Le serveur retient l'UID du joueur à qui il demande la
        // révocation, à la validation comme à chaque relance, et n'accepte la réponse que de lui, en attente.
        $srv = $this->read(self::MAIN . '/functions/shell/fn_captureServer.sqf');
        self::assertStringContainsString('_c set ["asked", getPlayerUID _by];', $this->between($srv, '_c set ["state", "validated"];', '"revoke"'));
        $athena = $this->between($srv, 'case "athena": {', "\n};\ntrue");
        self::assertNotEmpty($athena);
        self::assertMatchesRegularExpression('/^case "athena": \{\s*_arg params \[\["_id", ""\], \["_ok", false\], \["_from", objNull\]\];.*?'
            . 'if \(\(_c getOrDefault \["athena", ""\]\) isNotEqualTo "pending"\) exitWith \{\};.*?'
            . 'private _asked = _c getOrDefault \["asked", ""\];\s*if \(_asked isEqualTo "" \|\| \{\(getPlayerUID _from\) isNotEqualTo _asked\}\) exitWith \{.*?'
            . 'if \(_ok\) exitWith \{ _c set \["athena", "ok"\]; call _publish; \};/s', $athena);
        self::assertSame(1, substr_count($athena, 'if (_ok) exitWith'));
        self::assertMatchesRegularExpression('/_c set \["asked", getPlayerUID _next\];\s*\S[^\n]*"revoke"[^\n]*_next\]/', $athena);
    }

    public function testGiveFailIsNoticedByTheServerWithoutAnyClientMessage(): void
    {
        // « giveFail » (regroupement 2.5.0) : une IA à l'inventaire plein pose le téléphone au sol. main le faisait
        // savoir au serveur par l'événement CBA comspec_atak_native_captureSrv, que ce lot retire parce que tout client
        // pouvait le lever (contre-expertise v4, B5). Le serveur le constate maintenant lui-même : après l'ordre
        // « give », « giveCheck » regarde si l'IA a reçu le téléphone, sinon cherche le porte-objet apparu à moins de
        // 10 m et y reporte la capture (« giveFail »).
        $code = $this->stripSqfComments($this->read(self::MAIN . '/functions/shell/fn_captureServer.sqf'));
        // La machine de l'IA pose le téléphone et n'envoie rien (ordre du serveur à fn_captureLocal, lot mpauth3).
        $give = $this->between($this->stripSqfComments($this->read(self::MAIN . '/functions/shell/fn_captureLocal.sqf')), 'case "give": {', 'case "move":');
        self::assertStringContainsString('createVehicle ["GroundWeaponHolder"', $give);
        foreach (['Event', 'remoteExec', 'publicVariable', 'setVariable', 'giveFail', 'giveCheck'] as $send) {
            self::assertStringNotContainsString($send, $give, $send);
        }
        // Le serveur lance la vérification juste après son ordre « give » (tick), avec l'état relevé avant l'ordre.
        self::assertMatchesRegularExpression('/private _chk = \[_ai, _cls, \{ _x == _cls \} count \(\(assignedItems _ai\) \+ \(items _ai\)\), nearestObjects \[_p0, \["GroundWeaponHolder"\], 12\], _p0, _now\];\s*'
            . '\["give", _ai, _cls\] remoteExecCall \["comspec_atak_native_fnc_captureLocal", _ai\];\s*'
            . '\[\{ \["giveCheck", _this\] call comspec_atak_native_fnc_captureServer; \}, _chk, 2\] call CBA_fnc_waitAndExecute;/', $code);
        $check = $this->between($code, 'case "giveCheck": {', 'case "giveFail": {');
        self::assertStringContainsString('if (({ _x == _cls } count ((assignedItems _ai) + (items _ai))) > _had) exitWith {};', $check);
        self::assertStringContainsString('(nearestObjects [_p0, ["GroundWeaponHolder"], 10]) select {', $check);
        self::assertStringContainsString('if (_gh isNotEqualTo []) exitWith { ["giveFail", [_ai, _gh select 0, _p0]] call comspec_atak_native_fnc_captureServer; };', $check);
        $fail = $this->between($code, 'case "giveFail": {', 'case "tick": {');
        self::assertStringContainsString('if (((_gh distance2D _p0) > 10) || {([_gh, "holder"] call _items) isEqualTo []}) exitWith {};', $fail);
        // Ni « giveCheck » ni « giveFail » n'est ouverte aux clients, et aucun autre fichier ne les appelle.
        self::assertStringContainsString('if (_remote && {!(_act in ["dropped", "validate", "dismiss", "athena"])}) exitWith {', $code);
        $elsewhere = [];
        foreach ([self::MAIN, self::CONNECT] as $dir) {
            foreach ($this->sqfFiles($dir) as $rel => $src) {
                if (!str_ends_with($rel, '/fn_captureServer.sqf') && preg_match('/give(?:Fail|Check)/', $this->stripSqfComments($src)) === 1) {
                    $elsewhere[] = $rel;
                }
            }
        }
        self::assertSame([], $elsewhere);
    }

    public function testFireTeamsHaveNoCbaEventLeft(): void
    {
        // Équipes de feu : les demandes arrivent par remoteExecCall vers 2. Le gestionnaire CBA comspec_atak_native_ft
        // n'avait plus d'émetteur, et en partie hébergée un client qui levait l'événement agissait au nom de l'hôte.
        self::assertStringNotContainsString('"comspec_atak_native_ft"', $this->read(self::MAIN . '/XEH_postInitServer.sqf'));
        foreach (['functions/teams/fn_ftAction.sqf', 'functions/teams/fn_ftRoleSync.sqf', 'functions/pages/fn_groupAction.sqf'] as $f) {
            $src = $this->read(self::MAIN . '/' . $f);
            self::assertStringNotContainsString('"comspec_atak_native_ft"', $src, $f);
            self::assertStringContainsString('remoteExecCall ["comspec_atak_native_fnc_ftServer", 2]', $src, $f);
        }
        self::assertStringContainsString('class comspec_atak_native_fnc_ftServer { allowedTargets = 2; };', $this->remoteExecFunctions(self::MAIN . '/config.cpp'));
    }

    public function testAdminAppsRuleIsOpenToARemoteAdminAndStillJudgedOnTheRequester(): void
    {
        // « apps » (App Store, regroupement 2.5.0) : les boutons de l'app Admin l'envoient par la branche « server » de
        // fn_adminClient, donc par remoteExecCall vers 2. Absente de la liste des actions distantes de fn_adminServer,
        // elle était refusée à tout admin qui n'est pas l'hôte (contre-expertise v4, B5). Le droit reste jugé juste
        // après, sur le demandeur retrouvé par remoteExecutedOwner.
        $page = $this->read(self::MAIN . '/functions/admin/fn_pageAdmin.sqf');
        self::assertStringContainsString("['server', ['apps', ['', 'reset']]] call comspec_atak_native_fnc_adminClient;", $page);
        self::assertStringContainsString("['server', ['apps', [%1, %2]]] call comspec_atak_native_fnc_adminClient;", $page);
        self::assertStringContainsString('[_a, _xa] remoteExecCall ["comspec_atak_native_fnc_adminServer", 2];', $this->read(self::MAIN . '/functions/admin/fn_adminClient.sqf'));
        $code = $this->stripSqfComments($this->read(self::MAIN . '/functions/admin/fn_adminServer.sqf'));
        self::assertMatchesRegularExpression('/if \(_remote\) then \{ _caller = \[remoteExecutedOwner\] call comspec_atak_native_fnc_reqUnit; \};.*?'
            . 'if \(_remote && \{!\(_act in \["scan", "capture", "hunt", "apps", "row"\]\)\}\) exitWith \{.*?'
            . 'if \(_remote && \{isNull _caller\}\) exitWith \{.*?'
            . 'if \(_remote && \{!\(\[_caller\] call comspec_atak_native_fnc_adminAllowed\)\}\) exitWith \{.*?switch \(_act\) do \{/s', $code);
        $this->assertRequesterRulesArePinned();
    }

    public function testDebriefRequestsAreJudgedOnTheRequesterFoundByTheServer(): void
    {
        // CR fin d'op (contre-expertise v4, N15) : l'événement CBA comspec_atak_native_debrief portait l'unité du
        // demandeur, choisie par l'émetteur. Tout client imposait, relançait ou levait un CR au nom d'un Zeus ou d'un
        // admin, ou changeait l'état de la fiche d'un autre joueur. Les demandes arrivent par remoteExecCall vers 2 ;
        // le serveur retrouve le demandeur par remoteExecutedOwner et juge ses droits avec la même règle qu'avant.
        $code = $this->stripSqfComments($this->read(self::MAIN . '/functions/debrief/fn_debriefServer.sqf'));
        $head = $this->between($code, 'params [["_act", ""], ["_args", []], ["_caller", objNull]];', 'switch (_act) do {');
        self::assertMatchesRegularExpression('/^params \[\["_act", ""\], \["_args", \[\]\], \["_caller", objNull\]\];\s*'
            . 'private _remote = isMultiplayer && \{isRemoteExecuted\} && \{remoteExecutedOwner isNotEqualTo 2\};\s*'
            . 'if \(_remote\) then \{ _caller = \[remoteExecutedOwner\] call comspec_atak_native_fnc_reqUnit; \};\s*'
            . 'if \(!_remote && \{isNull _caller\} && \{hasInterface\}\) then \{ _caller = player; \};\s*'
            . 'if \(_remote && \{!\(_act in \["force", "remind", "lift", "pull", "state", "hello", "right"\]\)\}\) exitWith \{.*?'
            . 'if \(isNull _caller && \{_act isNotEqualTo "init"\}\) exitWith \{.*?'
            . 'if \(_act in \["force", "remind", "lift", "pull"\]\) then \{.*?'
            . '_ok = \[_caller\] call comspec_atak_native_fnc_adminAllowed && .*?'
            . 'if \(!_ok\) exitWith \{.*?\[_caller, "note", "denied"\] call _send;/s', $head);
        $this->assertRequesterRulesArePinned();
        self::assertStringNotContainsString('CBA_fnc_addEventHandler', $code);
        // Les deux émetteurs du client passent par remoteExecCall, sans unité dans la demande.
        self::assertStringContainsString('private _server = { params ["_a", ["_x", []]]; [_a, _x] remoteExecCall ["comspec_atak_native_fnc_debriefServer", 2]; };', $this->read(self::MAIN . '/functions/debrief/fn_debrief.sqf'));
        self::assertStringContainsString('["hello", []] remoteExecCall ["comspec_atak_native_fnc_debriefServer", 2];', $this->read(self::MAIN . '/XEH_postInitClient.sqf'));
        self::assertStringContainsString('class comspec_atak_native_fnc_debriefServer { allowedTargets = 2; };', $this->remoteExecFunctions(self::MAIN . '/config.cpp'));
        // Plus de gestionnaire ni d'émetteur de l'événement dans main/ et connect/ (code porté par des chaînes compris).
        $files = $this->sources();
        $left = [];
        foreach ($files as $rel => $units) {
            foreach ($units as $u) {
                if (preg_match('/(["\'])comspec_atak_native_debrief\1/i', $u) === 1) {
                    $left[$rel] = true;
                }
            }
        }
        self::assertSame([], array_keys($left));
        // Toutes les actions que le client envoie sont ouvertes à distance (relais _server compris).
        $lists = $this->remoteLists($files);
        self::assertSame(['force', 'remind', 'lift', 'pull', 'state', 'hello', 'right'], $lists['comspec_atak_native_fnc_debriefserver'] ?? null);
        [$sent] = $this->actionsSent($files, $lists);
        $got = array_keys($sent['comspec_atak_native_fnc_debriefserver'] ?? []);
        sort($got);
        self::assertSame(['force', 'hello', 'lift', 'pull', 'remind', 'right', 'state'], $got);
    }

    public function testCompromiseOrdersAreAcceptedOnlyFromTheServer(): void
    {
        // Alertes, révocation chez Athena et nouvel IMEI : l'événement CBA comspec_atak_native_compromise ne disait pas
        // qui l'avait levé. Un client faisait révoquer un terminal de son choix avec la session d'un autre joueur,
        // imposait un nouvel IMEI ou passait l'ATAK d'un joueur en mode compromis. Le serveur envoie ces ordres par
        // remoteExecCall et fn_compromise n'accepte, à distance, que le poste 2.
        self::assertStringNotContainsString('["comspec_atak_native_compromise", {', $this->read(self::MAIN . '/XEH_preInit.sqf'));
        $srv = $this->read(self::MAIN . '/functions/shell/fn_captureServer.sqf');
        self::assertStringNotContainsString('["comspec_atak_native_compromise", [', $srv);
        foreach ([
            '["event", [_kind, _row]] remoteExecCall ["comspec_atak_native_fnc_compromise", _to];',
            '["revoke", [_id, _term, (_c get "tag") param [2, ""]]] remoteExecCall ["comspec_atak_native_fnc_compromise", _by];',
            '["ownerReset", [_id]] remoteExecCall ["comspec_atak_native_fnc_compromise", allPlayers select _i];',
            '["revoke", [_id, (_c get "tag") param [5, ""], (_c get "tag") param [2, ""]]] remoteExecCall ["comspec_atak_native_fnc_compromise", _next];',
        ] as $call) {
            self::assertStringContainsString($call, $srv);
        }
        // Le garde est en tête de fn_compromise, avant toute action.
        $guarded = $this->compromiseGuarded();
        foreach (['event', 'revoke', 'ownerReset'] as $act) {
            self::assertContains($act, $guarded, $act);
        }
        self::assertStringContainsString('class comspec_atak_native_fnc_compromise { allowedTargets = 0; jip = 0; };', $this->remoteExecFunctions(self::MAIN . '/config.cpp'));
    }

    public function testCompromiseRefusesEveryActingCallFromAnotherClient(): void
    {
        // La déclaration CfgRemoteExec (allowedTargets = 0) ouvre fn_compromise de poste à poste dans une mission en
        // liste blanche, et le mode 2 par défaut l'ouvrait déjà. Un autre poste faisait exécuter « validate » ou
        // « dismiss » chez un chef de groupe : le serveur recevait la demande du poste du chef et la jugeait en son nom
        // (validation, donc révocation chez Athena avec la session du chef, ou rejet). Seules les lectures « can » et
        // « mine » restent ouvertes.
        $guarded = $this->compromiseGuarded();
        foreach (['publish', 'validate', 'dismiss', 'map'] as $act) {
            self::assertContains($act, $guarded, $act);
        }
    }

    public function testTocScreenRequestsAreJudgedOnTheRequesterFoundByTheServer(): void
    {
        // Écrans TOC (contre-expertise v4, N15) : le gestionnaire CBA comspec_atak_native_tocScreen ne contrôlait rien.
        // Tout client faisait poser au serveur des écrans sans limite, désigner comme écran n'importe quel objet (recréé
        // par fn_tocPersist aux parties suivantes, quelle que soit sa classe), supprimer un objet marqué
        // COMSPEC_ATAK_TocOwned ou changer l'affichage de tout écran. Les demandes arrivent par remoteExecCall vers 2 et
        // sont jugées sur le demandeur retrouvé par remoteExecutedOwner : Zeus, hôte, admin ou UID autorisé ;
        // l'affichage, aussi le joueur du camp de l'écran, à moins de 25 m.
        $code = $this->stripSqfComments($this->read(self::MAIN . '/functions/toc/fn_tocScreen.sqf'));
        $head = $this->between($code, 'params [["_mode", "tick"], ["_a", objNull]];', 'switch (_mode) do {');
        self::assertMatchesRegularExpression('/private _remote = isServer && \{isMultiplayer\} && \{isRemoteExecuted\} && \{remoteExecutedOwner isNotEqualTo 2\};\s*'
            . 'private _req = objNull;\s*'
            . 'if \(_remote\) then \{ _req = \[remoteExecutedOwner\] call comspec_atak_native_fnc_reqUnit; \};\s*'
            . 'if \(_remote && \{!\(_mode in \["add", "remove", "spawn", "view"\]\)\}\) exitWith \{.*?'
            . 'if \(_remote && \{isNull _req\}\) exitWith \{.*?'
            . 'if \(_remote && \{!\(\[_req\] call comspec_atak_native_fnc_adminAllowed\)\} && \{!\(_mode isEqualTo "view" && \{\[_a, _req\] call _viewer\}\)\}\) exitWith \{.*?false\s*\};\s*$/s', $head);
        $this->assertRequesterRulesArePinned();
        // Lot mpauth3 (SEC-033) : l'écran et son camp sont lus dans la copie du serveur, plus dans le registre public.
        self::assertMatchesRegularExpression('/private _viewer = \{\s*params \["_v", "_u"\];\s*if !\(_v isEqualType \[\]\) exitWith \{ false \};\s*private _o = _v param \[0, objNull\];\s*'
            . 'if \(!\(_o isEqualType objNull\) \|\| \{isNull _o\}\) exitWith \{ false \};\s*'
            . 'private _s = \(\[_o\] call _srvOf\) param \[1, -1\];\s*'
            . '_s isEqualType "" && \{_s isEqualTo "" \|\| \{_s isEqualTo str side group _u\}\} && \{\(_u distance _o\) <= 25\}\s*\};/', $head);
        // « spawn » ne se ré-appelle plus pour la liste des écrans (« classes » est refusée à distance) ; plus d'événement.
        self::assertStringNotContainsString('["classes"] call _fn', $code);
        self::assertStringNotContainsString('CBA_fnc_addEventHandler', $code);
        self::assertStringNotContainsString('CBA_fnc_serverEvent', $code);
        foreach (['add' => 'true', 'remove' => 'true', 'spawn' => 'objNull', 'view' => 'true'] as $mode => $ret) {
            self::assertStringContainsString('if (!isServer) exitWith { ["' . $mode . '", _a] remoteExecCall ["comspec_atak_native_fnc_tocScreen", 2]; ' . $ret . ' };', $code, $mode);
        }
        self::assertStringContainsString('class comspec_atak_native_fnc_tocScreen { allowedTargets = 2; };', $this->remoteExecFunctions(self::MAIN . '/config.cpp'));
        // Plus de gestionnaire ni d'émetteur de l'événement dans main/ et connect/ ; actions envoyées = liste distante.
        $files = $this->sources();
        $left = [];
        foreach ($files as $rel => $units) {
            foreach ($units as $u) {
                if (preg_match('/(["\'])comspec_atak_native_tocScreen\1/i', $u) === 1) {
                    $left[$rel] = true;
                }
            }
        }
        self::assertSame([], array_keys($left));
        $lists = $this->remoteLists($files);
        self::assertSame(['add', 'remove', 'spawn', 'view'], $lists['comspec_atak_native_fnc_tocscreen'] ?? null);
        [$sent] = $this->actionsSent($files, $lists);
        $got = array_keys($sent['comspec_atak_native_fnc_tocscreen'] ?? []);
        sort($got);
        self::assertSame(['add', 'remove', 'spawn', 'view'], $got);
    }

    public function testCallsReplayedFromTheJipQueueAreRefusedByTheServerOnlyGuards(): void
    {
        // Un client peut déposer un appel dans la file JIP (troisième argument de remoteExec / remoteExecCall) : le
        // serveur le rejoue à chaque joueur qui arrive ensuite, et la valeur de remoteExecutedOwner pendant ce rejeu
        // n'est pas établie (client d'origine, 0 ou 2). Les trois gardes « seul le serveur » refusent donc tout appel
        // rejoué (contre-expertise v2, B4), et isRemoteExecutedJIP y est lu seul, en premier : le refus ne dépend pas
        // de ce que isRemoteExecuted vaut pendant un rejeu (contre-expertise v3, N11).
        $remote = '{isRemoteExecuted && {remoteExecutedOwner isNotEqualTo 2}}) exitWith {';
        foreach ([
            self::MAIN . '/functions/shell/fn_compromise.sqf' => 'if (!(_act in ["can", "mine"]) && {isRemoteExecutedJIP || {isMultiplayer && {isRemoteExecuted} && {remoteExecutedOwner isNotEqualTo 2}}}) exitWith {',
            self::MAIN . '/functions/admin/fn_adminClient.sqf' => 'if (isRemoteExecutedJIP || ' . $remote,
            self::CONNECT . '/functions/fn_applyZeusAtakEffect.sqf' => 'if (isRemoteExecutedJIP || ' . $remote,
            self::MAIN . '/functions/shell/fn_captureLocal.sqf' => 'if (isRemoteExecutedJIP || ' . $remote,
            self::MAIN . '/functions/drone/fn_droneCmd.sqf' => 'if (isRemoteExecutedJIP || ' . $remote,
        ] as $file => $guard) {
            $src = $this->read($file);
            self::assertSame(1, substr_count($src, $guard), $file);
            self::assertStringNotContainsString('isRemoteExecuted && {isRemoteExecutedJIP', $src, $file);
            self::assertStringNotContainsString('{isRemoteExecuted} && {isRemoteExecutedJIP', $src, $file);
            // La garde est la première instruction de la fonction, juste après params, hors commentaires : ni
            // commentée, ni placée après un effet (contre-expertise v3, N10), et rien avant params (v4, N14, K18).
            $code = $this->stripSqfComments($src);
            $open = strpos($code, 'params [');
            self::assertNotFalse($open, $file);
            self::assertSame('', trim(substr($code, 0, (int) $open)), $file);
            $body = $this->bracketBody($code, $open + strlen('params '));
            self::assertNotNull($body, $file);
            $after = substr($code, $open + strlen('params ') + strlen((string) $body) + 2);
            self::assertMatchesRegularExpression('/^\s*;\s*' . preg_quote($guard, '/') . '/', $after, $file);
        }
        // Liste blanche (mode 1) : jip = 0 fait refuser par le moteur tout dépôt de ces appels dans la file JIP. Lu sans
        // commentaires ni blocs « #if 0 » : une classe en commentaire ou sous #if 0 ne compte pas (v4, N14, K13, K14). Lu
        // dans le bloc CfgRemoteExec > Functions lui-même : hors de ce bloc, ou sous un bloc renommé, une classe ne compte
        // pas non plus (contre-expertise mpauth3 v1, O31).
        $mainCfg = $this->remoteExecFunctions(self::MAIN . '/config.cpp');
        self::assertStringContainsString('class comspec_atak_native_fnc_adminClient { allowedTargets = 1; jip = 0; };', $mainCfg);
        self::assertStringContainsString('class comspec_atak_native_fnc_compromise { allowedTargets = 0; jip = 0; };', $mainCfg);
        self::assertStringContainsString('class comspec_atak_native_fnc_captureLocal { allowedTargets = 0; jip = 0; };', $mainCfg);
        self::assertStringContainsString('class comspec_atak_native_fnc_droneCmd { allowedTargets = 0; jip = 0; };', $mainCfg);
        self::assertStringContainsString('class comspec_overwatch_connect_fnc_applyZeusAtakEffect { allowedTargets = 0; jip = 0; };', $this->remoteExecFunctions(self::CONNECT . '/config.cpp'));
        // Aucun ordre légitime vers ces fonctions n'emprunte la file : chaque appel distant n'a que deux arguments.
        // Lecture tolérante (contre-expertise v3, N10) : commentaires retirés, tableau lu jusqu'à son crochet fermant
        // (sur plusieurs lignes, sans « ; » final, nom entre guillemets simples), code porté par des chaînes compris.
        // Plus d'angle mort de nom (v4, N14, K15, K16) : dans main/ et connect/, tout remoteExec / remoteExecCall est
        // suivi d'un tableau écrit en toutes lettres, dont le premier élément est un nom de fonction littéral.
        $targets = ['comspec_atak_native_fnc_compromise', 'comspec_atak_native_fnc_adminclient', 'comspec_overwatch_connect_fnc_applyzeusatakeffect', 'comspec_atak_native_fnc_capturelocal', 'comspec_atak_native_fnc_dronecmd'];
        $calls = [];
        $opaque = [];
        $seen = 0;
        foreach ([self::MAIN, self::CONNECT] as $dir) {
            foreach ($this->sqfFiles($dir) as $rel => $src) {
                foreach ($this->codeUnits($src) as $code) {
                    foreach ($this->remoteCalls($code) as [, $right, $at]) {
                        $seen++;
                        $fn = $right === null ? null : $this->literal($right[0]);
                        if ($fn === null) {
                            $opaque[] = $rel . ' : ' . preg_replace('/\s+/', ' ', substr($code, $at, 90));
                        } elseif (in_array(strtolower($fn), $targets, true)) {
                            $calls[] = [$rel . ' : ' . preg_replace('/\s+/', ' ', implode(',', (array) $right)), count((array) $right)];
                        }
                    }
                }
            }
        }
        self::assertGreaterThan(80, $seen);
        self::assertSame([], $opaque);
        self::assertGreaterThanOrEqual(6, count($calls), implode("\n", array_column($calls, 0)));
        foreach ($calls as [$call, $n]) {
            self::assertSame(2, $n, $call);
        }
    }

    public function testEventsReplacedByTheLotHaveNeitherHandlerNorEmitterLeft(): void
    {
        // Contre-expertise v4, B5, point 4 (premier invariant) : les huit événements CBA que le lot remplace par
        // remoteExecCall n'ont plus ni gestionnaire ni émetteur dans main/ et connect/ (commentaires retirés, code porté
        // par des chaînes compris, casse ignorée comme le fait CBA). Un gestionnaire rétabli rouvre la voie que le lot
        // ferme (R2 : captureSrv gardé pour « giveFail » rouvrait B2 et D2) ; un émetteur sans gestionnaire perd la
        // demande sans bruit (R1).
        // Lot mpauth3 : capLocal (SEC-023), droneCmd (SEC-022), debriefRecv et debriefData (SEC-030), adminData (SEC-015),
        // dockPut, dockTake, dockDeploy, dockPack et dockResult (SEC-025), deleteLater (SEC-024) s'y ajoutent.
        $removed = ['captureSrv', 'compromise', 'admin', 'adminReport', 'zeus', 'ft', 'planSrv', 'dataSrv', 'capLocal', 'droneCmd', 'debriefRecv', 'debriefData', 'adminData',
            'dockPut', 'dockTake', 'dockDeploy', 'dockPack', 'dockResult', 'deleteLater'];
        $quoted = '/(["\'])comspec_atak_native_(?:' . implode('|', $removed) . ')\1/i';
        // Fonctions qui jugent le demandeur par remoteExecutedOwner (fn_reqUnit) : appelées depuis un gestionnaire CBA,
        // elles ne voient pas d'appel distant et croient l'unité fournie par l'émetteur. Aucun gestionnaire ne les
        // appelle (v4, N14, K17), sauf un corps exact, à action fixe : comspec_atak_native_camShare vers fn_camShare
        // « recv » (lot mpauth3 : notification du destinataire, sans effet hors de son téléphone ; « srvAcc » reste hors
        // d'atteinte du gestionnaire). L'exception de capLocal vers fn_captureServer « local » est retirée avec
        // l'événement (lot mpauth3, SEC-023).
        $allowed = [
            'comspec_atak_native_camshare' => '{ ["recv", _this] call comspec_atak_native_fnc_camShare; }',
        ];
        $files = $this->sources();
        $judges = [];
        foreach ($files as $rel => $units) {
            if (preg_match('/\[\s*remoteExecutedOwner\s*\]\s*call\s+comspec_atak_native_fnc_reqUnit\b/i', $units[0]) === 1) {
                $judges[] = $this->functionOf($rel);
            }
        }
        foreach (['adminserver', 'zeusserver', 'captureserver', 'dataserver', 'planserver', 'ftserver'] as $f) {
            self::assertContains('comspec_atak_native_fnc_' . $f, $judges);
        }
        $left = [];
        $handlers = 0;
        foreach ($files as $rel => $units) {
            if (preg_match_all($quoted, $units[0], $m) > 0) {
                $left[] = $rel . ' : ' . implode(', ', array_unique($m[0]));
            }
            foreach ($units as $code) {
                foreach ($this->cbaHandlers($code) as [$name, $body]) {
                    $handlers++;
                    foreach ($judges as $fn) {
                        if (preg_match('/\b' . preg_quote($fn, '/') . '\b/i', $body) !== 1) {
                            continue;
                        }
                        if (($allowed[strtolower($name)] ?? null) === preg_replace('/\s+/', ' ', $body)) {
                            continue;
                        }
                        $left[] = "$rel : le gestionnaire $name appelle $fn";
                    }
                }
            }
        }
        self::assertGreaterThan(50, $handlers);
        self::assertSame([], $left);
    }

    public function testEveryActionAClientSendsToAListGuardedFunctionIsInItsRemoteList(): void
    {
        // Contre-expertise v4, B5, point 4 (second invariant) : une action qu'un client envoie, mais absente de la liste
        // des actions distantes de la fonction, est refusée sans bruit (« apps »). Fonctions à liste : toute fonction de
        // main/ ou connect/ dont la garde porte « if (_remote && {!(_act in [...])}) exitWith { » (quel que soit le nom
        // du sélecteur). Actions envoyées : premier élément du tableau passé par remoteExec / remoteExecCall à ces
        // fonctions, dans le code et dans le code porté par des chaînes. Une action non littérale n'est lue que si un
        // relais connu la remplit (relayActions), dont les appelants sont lus à leur tour ; sinon le test échoue. Un
        // envoi placé après le « if (!isServer) exitWith { … }; » qui ouvre son bloc est un ordre du serveur (lot
        // mpauth3 : « pickupGive » de fn_camAction) : la garde le laisse passer (poste 2), il n'a pas à être listé.
        $files = $this->sources();
        $lists = $this->remoteLists($files);
        foreach (['adminserver', 'zeusserver', 'captureserver'] as $f) {
            self::assertArrayHasKey('comspec_atak_native_fnc_' . $f, $lists);
        }
        [$sent, $unread, $orders] = $this->actionsSent($files, $lists);
        self::assertSame([], $unread);
        $refused = [];
        foreach ($sent as $fn => $acts) {
            foreach ($acts as $act => $where) {
                if (!in_array($act, $lists[$fn], true)) {
                    $refused[] = "$fn refuse « $act », envoyée par " . implode(', ', array_keys($where));
                }
            }
        }
        self::assertSame([], $refused);
        // Repères : la lecture voit les envois connus, relais compris (une lecture cassée ne passerait pas en silence).
        foreach ([
            'comspec_atak_native_fnc_adminserver' => ['apps', 'capture', 'hunt', 'row', 'scan'],
            'comspec_atak_native_fnc_captureserver' => ['athena', 'dismiss', 'dropped', 'validate'],
            'comspec_atak_native_fnc_zeusserver' => ['jamArea', 'jamClear', 'once', 'reset', 'set'],
        ] as $fn => $expected) {
            $got = array_keys($sent[$fn] ?? []);
            sort($got);
            self::assertSame($expected, $got, $fn);
        }
        // Ordres du serveur, que la garde laisse passer sans liste (poste 2) : « pickupGive » de fn_camAction seul. Un
        // envoi de client placé par erreur derrière « if (!isServer) exitWith » y passerait, et ne partirait plus d'un
        // client (contre-expertise mpauth3 v1, O13 : reprise sur un socle cassée sur serveur dédié).
        self::assertSame(['comspec_atak_native_fnc_camaction' => ['pickupGive']], array_map('array_keys', $orders));
    }

    public function testCameraPickupAndRegistryAreJudgedOnTheRequester(): void
    {
        // Lot mpauth3 (SEC-017, SEC-005, SEC-006) : « srvTake » (fn_camAction) supprimait n'importe quelle caméra de la
        // carte et en livrait l'item à l'unité nommée par l'appelant ; « srvReg » laissait tout client vider le registre
        // des caméras. La fonction était absente de la liste blanche (pose et ramassage cassés en mode 1). Elle y entre
        // avec une garde en tête : rien de la file JIP ; d'un autre poste que le serveur, « srvReg » et « srvTake »
        // seules, jugées sur le demandeur retrouvé par remoteExecutedOwner, jamais sur une unité des arguments.
        $code = $this->stripSqfComments($this->read(self::MAIN . '/functions/camera/fn_camAction.sqf'));
        $head = $this->between($code, 'params [["_mode", ""], ["_a", objNull], ["_b", 0]];', 'switch (_mode) do {');
        self::assertMatchesRegularExpression('/^params \[\["_mode", ""\], \["_a", objNull\], \["_b", 0\]\];\s*'
            . 'if \(isRemoteExecutedJIP\) exitWith \{[^{}]*false\s*\};\s*'
            . 'private _remote = isMultiplayer && \{isRemoteExecuted\} && \{remoteExecutedOwner isNotEqualTo 2\};\s*'
            . 'private _req = objNull;\s*'
            . 'if \(_remote\) then \{ _req = \[remoteExecutedOwner\] call comspec_atak_native_fnc_reqUnit; \};\s*'
            . 'if \(_remote && \{!\(_mode in \["srvReg", "srvTake"\]\)\}\) exitWith \{[^{}]*false\s*\};\s*'
            . 'if \(_remote && \{isNull _req\}\) exitWith \{[^{}]*false\s*\};/', $head);
        $this->assertRequesterRulesArePinned();
        // Ramassage : pour le demandeur lui-même, vivant, à 10 m au plus. Registre : ajout seulement, caméra proche.
        $take = $this->between($code, 'case "srvTake": {', 'case "pickupGive": {');
        self::assertMatchesRegularExpression('/^case "srvTake": \{\s*if \(!isServer\) exitWith \{ false \};\s*'
            . 'if \(!\(_a isEqualType objNull\) \|\| \{isNull _a\} \|\| \{!\(_b isEqualType objNull\)\} \|\| \{isNull _b\} [^\n]*\) exitWith \{ false \};\s*'
            . 'if \(_remote && \{_b isNotEqualTo _req \|\| \{!alive _req\} \|\| \{\(_req distance _a\) > 10\}\}\) exitWith \{[^{}]*false\s*\};\s*'
            . '_a setVariable \["COMSPEC_ATAK_CamTaken", true\];/', $take);
        // Bloc entier (contre-expertise mpauth3 v1, O16) : l'item part au demandeur seul, par l'ordre du serveur.
        $this->assertBlocks($code, [
            'case "srvTake": {' => [
                'case "srvTake": {',
                'if (!isServer) exitWith { false };',
                'if (!(_a isEqualType objNull) || {isNull _a} || {!(_b isEqualType objNull)} || {isNull _b} || {(typeOf _a) isNotEqualTo CAM_CLASS} || {_a getVariable ["COMSPEC_ATAK_CamTaken", false]}) exitWith { false };',
                'if (_remote && {_b isNotEqualTo _req || {!alive _req} || {(_req distance _a) > 10}}) exitWith {',
                'diag_log format ["[COMSPEC ATAK NATIVE][WARN][CAM] Ramassage refusé à %1 (pour une autre unité, ou demandeur mort ou à plus de 10 m de la caméra)", name _req];',
                'false',
                '};',
                '_a setVariable ["COMSPEC_ATAK_CamTaken", true];',
                'private _dead = [_a] call _isDead;',
                'private _name = _a getVariable ["COMSPEC_ATAK_CamName", "Caméra"];',
                '[_a, false] call _register;',
                'deleteVehicle _a;',
                '["pickupGive", _name, _dead] remoteExecCall ["comspec_atak_native_fnc_camAction", _b];',
                'true',
                '}',
            ],
        ], 'fn_camAction');
        $reg = $this->between($code, 'case "srvReg": {', 'case "rotate": {');
        self::assertMatchesRegularExpression('/^case "srvReg": \{\s*if \(!isServer \|\| \{!\(_a isEqualType objNull\)\}\) exitWith \{ false \};\s*'
            . 'if \(_remote && \{_b isNotEqualTo true \|\| \{isNull _a\} \|\| \{!alive _req\} \|\| \{\(_req distance _a\) > 10\}\}\) exitWith \{[^{}]*false\s*\};\s*'
            . '\[_a, _b isEqualTo true, \[player, _req\] select _remote\] call _register;\s*true\s*\};\s*$/', $reg);
        // Poseur retenu par le serveur (contre-expertise mpauth3 v1, R5) : le demandeur (l'hôte pour sa propre pose), à la
        // première pose seulement, dans localNamespace, que le réseau n'écrit pas ; l'entrée part avec la caméra.
        $this->assertBlocks($code, [
            'private _register = {' => [
                'private _register = {',
                'params ["_o", "_add", ["_by", objNull]];',
                'private _list = (missionNamespace getVariable ["COMSPEC_ATAK_Cams", []]) select { !isNull _x && {_x isNotEqualTo _o} };',
                'private _own = (localNamespace getVariable ["COMSPEC_ATAK_CamOwners", []]) select { !isNull (_x select 0) && {_add || {(_x select 0) isNotEqualTo _o}} };',
                'if (_add && {!isNull _o} && {(typeOf _o) isEqualTo CAM_CLASS}) then {',
                '_list pushBack _o;',
                'if (!isNull _by && {(_own findIf { (_x select 0) isEqualTo _o }) < 0}) then {',
                '_own pushBack [_o, [getPlayerUID _by, netId _by] select (getPlayerUID _by isEqualTo "")];',
                '};',
                'if (isNil { _o getVariable "COMSPEC_ATAK_CamSrvEh" }) then {',
                '_o setVariable ["COMSPEC_ATAK_CamSrvEh", true];',
                '_o addEventHandler ["Explosion", { params ["_c", "_dmg"]; if (_dmg > 0.05) then { _c setVariable ["COMSPEC_ATAK_CamDead", true, true]; }; }];',
                '{ _x addCuratorEditableObjects [[_o], true]; } forEach allCurators;',
                '};',
                '};',
                'localNamespace setVariable ["COMSPEC_ATAK_CamOwners", _own];',
                'missionNamespace setVariable ["COMSPEC_ATAK_Cams", _list, true];',
                '}',
            ],
        ], 'fn_camAction');
        // Ni l'une ni l'autre ne se ré-appelle : dans la pile d'une demande distante, l'appel imbriqué garde le contexte
        // distant et serait refusé (« dead » rendait faux, le retrait du registre ne se faisait plus).
        foreach (['srvTake' => $take, 'srvReg' => $reg] as $mode => $body) {
            self::assertDoesNotMatchRegularExpression('/\bcall\s+(?:_fn|comspec_atak_native_fnc_camAction)\b/i', $body, $mode);
        }
        self::assertStringContainsString('class comspec_atak_native_fnc_camAction { allowedTargets = 0; jip = 0; };', $this->remoteExecFunctions(self::MAIN . '/config.cpp'));
        // Les clients envoient « srvReg » et « srvTake » ; « pickupGive » n'est envoyée que par le serveur.
        $files = $this->sources();
        $lists = $this->remoteLists($files);
        self::assertSame(['srvReg', 'srvTake'], $lists['comspec_atak_native_fnc_camaction'] ?? null);
        [$sent, , $orders] = $this->actionsSent($files, $lists);
        $got = array_keys($sent['comspec_atak_native_fnc_camaction'] ?? []);
        sort($got);
        self::assertSame(['srvReg', 'srvTake'], $got);
        self::assertSame(['comspec_atak_native_fnc_camaction' => ['pickupGive']], array_map('array_keys', $orders));
    }

    public function testCameraAccessListIsChangedOnlyByTheOwnerOrAViewer(): void
    {
        // Lot mpauth3 (SEC-005, SEC-006) : « srvAcc » (fn_camShare) réécrivait la liste d'accès COMSPEC_ATAK_ShareAcc de
        // n'importe quel objet sans regarder qui demandait : un client s'accordait la caméra d'autrui ou vidait une
        // liste. La fonction était absente de la liste blanche (partage cassé en mode 1). Elle y entre (allowedTargets
        // = 2) avec une garde en tête, et « srvAcc » juge le demandeur avec les règles que le téléphone applique déjà
        // (« owner », « can »), écrites une seule fois en fonctions locales. Contre-expertise mpauth3 v1 (R5) : sur le
        // serveur, ces règles lisent le poseur qu'il a retenu à la pose et sa copie des listes d'accès, jamais les
        // variables publiques de la caméra, qu'un poste client peut réécrire.
        $code = $this->stripSqfComments($this->read(self::MAIN . '/functions/camera/fn_camShare.sqf'));
        $head = $this->between($code, 'params [["_mode", ""], ["_a", objNull], ["_b", objNull]];', 'switch (_mode) do {');
        self::assertMatchesRegularExpression('/^params \[\["_mode", ""\], \["_a", objNull\], \["_b", objNull\]\];\s*'
            . 'if \(isRemoteExecutedJIP\) exitWith \{[^{}]*false\s*\};\s*'
            . 'private _remote = isMultiplayer && \{isRemoteExecuted\} && \{remoteExecutedOwner isNotEqualTo 2\};\s*'
            . 'private _req = objNull;\s*'
            . 'if \(_remote\) then \{ _req = \[remoteExecutedOwner\] call comspec_atak_native_fnc_reqUnit; \};\s*'
            . 'if \(_remote && \{!\(_mode in \["srvAcc"\]\)\}\) exitWith \{[^{}]*false\s*\};\s*'
            . 'if \(_remote && \{isNull _req\}\) exitWith \{[^{}]*false\s*\};/', $head);
        $this->assertRequesterRulesArePinned();
        // Caméra de téléphone : son porteur seul ; caméra fixe : retrait par le poseur, partage par qui y a accès.
        self::assertMatchesRegularExpression('/private _mayEdit = \{\s*params \["_s", "_u", "_share"\];\s*'
            . 'if !\(\[_s\] call _isCam\) exitWith \{ _s isEqualTo _u \};\s*'
            . 'if \(_share\) then \{ \[_s, _u\] call _canSee \} else \{ \[_s, _u\] call _isOwner \}\s*\};/', $head);
        // Règles entières (contre-expertise mpauth3 v1, O1, O2) : identité, caméra fixe, poseur, accès, droit de changer.
        $this->assertBlocks($code, [
            'private _uidOf = {' => ['private _uidOf = { params ["_u"]; private _id = getPlayerUID _u; if (_id isEqualTo "") then { netId _u } else { _id } }'],
            'private _isCam = {' => ['private _isCam = { params ["_o"]; !isNull _o && {_o isKindOf "COMSPEC_ATAK_CameraObj"} }'],
            'private _num = {' => ['private _num = { params ["_v", "_d"]; if (_v isEqualType 0) then { _v } else { _d } }'],
            'private _required = {' => ['private _required = { ([missionNamespace getVariable ["comspec_atak_native_camera_share", 0], 0] call _num) isEqualTo 0 }'],
            'private _accessOf = {' => [
                'private _accessOf = {',
                'params ["_s"];',
                'if (isNull _s) exitWith { [] };',
                'private _acc = if (isServer) then {',
                '(((localNamespace getVariable ["COMSPEC_ATAK_CamAcc", []]) select { (_x select 0) isEqualTo _s }) param [0, [objNull, []]]) select 1',
                '} else {',
                '_s getVariable ["COMSPEC_ATAK_ShareAcc", []]',
                '};',
                'if (_acc isEqualType []) then { _acc } else { [] }',
                '}',
            ],
            'private _isOwner = {' => [
                'private _isOwner = {',
                'params ["_s", "_u"];',
                'if (isNull _s) exitWith { false };',
                'if !([_s] call _isCam) exitWith { _s isEqualTo _u };',
                'if (isServer) exitWith {',
                'private _e = (localNamespace getVariable ["COMSPEC_ATAK_CamOwners", []]) select { (_x select 0) isEqualTo _s };',
                '_e isNotEqualTo [] && {((_e select 0) select 1) isEqualTo ([_u] call _uidOf)}',
                '};',
                'private _ou = _s getVariable ["COMSPEC_ATAK_CamOwnerUid", ""];',
                'if (_ou isEqualTo "") then { (_s getVariable ["COMSPEC_ATAK_CamOwner", ""]) isEqualTo name _u } else { _ou isEqualTo ([_u] call _uidOf) }',
                '}',
            ],
            'private _canSee = {' => [
                'private _canSee = {',
                'params ["_s", "_v"];',
                'if (isNull _s) exitWith { false };',
                'if !(call _required) exitWith { true };',
                'if (_s isEqualTo _v) exitWith { true };',
                'if ([_s] call _isCam && {[_s, _v] call _isOwner}) exitWith { true };',
                'private _id = [_v] call _uidOf;',
                '(([_s] call _accessOf) findIf { (_x param [0, ""]) in ["ALL", _id] }) >= 0',
                '}',
            ],
            'private _mayEdit = {' => [
                'private _mayEdit = {',
                'params ["_s", "_u", "_share"];',
                'if !([_s] call _isCam) exitWith { _s isEqualTo _u };',
                'if (_share) then { [_s, _u] call _canSee } else { [_s, _u] call _isOwner }',
                '}',
            ],
        ], 'fn_camShare');
        // Les règles ne ré-appellent pas la fonction (dans une pile distante, l'appel imbriqué serait refusé).
        $rules = $this->between($head, 'private _required = {', 'private _mayEdit = {');
        self::assertNotEmpty($rules);
        self::assertDoesNotMatchRegularExpression('/\bcall\s+(?:_fn|comspec_atak_native_fnc_cam(?:Share|Action))\b/i', $rules);
        foreach (['case "owner": { [_a, player] call _isOwner };', 'case "can": { [_a, [_b, player] select (isNull _b)] call _canSee };'] as $case) {
            self::assertStringContainsString($case, $code);
        }
        $srv = $this->between($code, 'case "srvAcc": {', 'case "recv": {');
        self::assertMatchesRegularExpression('/^case "srvAcc": \{\s*if \(!isServer\) exitWith \{ false \};\s*'
            . '_a params \[\["_src", objNull, \[objNull\]\], \["_add", \[\], \[\[\]\]\]\];\s*if \(isNull _src\) exitWith \{ false \};\s*'
            . 'if \(_remote && \{!\(\[_src, _req, _add isNotEqualTo \[\]\] call _mayEdit\)\}\) exitWith \{[^{}]*false\s*\};/', $srv);
        // Liste publiée bornée (chaînes courtes, 64 entrées par demande, 128 en tout), sans ré-appel de la fonction.
        self::assertStringContainsString('(_x findIf { !(_x isEqualType "") || {(count _x) > 64} }) < 0', $srv);
        self::assertStringContainsString('if ((count _new) > 64) then { _new resize 64; };', $srv);
        self::assertStringContainsString('if ((count _all) > 128) then { _all = _all select [(count _all) - 128, 128]; };', $srv);
        self::assertDoesNotMatchRegularExpression('/\bcall\s+(?:_fn|comspec_atak_native_fnc_cam(?:Share|Action))\b/i', $srv);
        // Bloc entier (R5) : la fusion part de la copie du serveur, gardée avant la publication.
        $this->assertBlocks($code, [
            'case "srvAcc": {' => [
                'case "srvAcc": {',
                'if (!isServer) exitWith { false };',
                '_a params [["_src", objNull, [objNull]], ["_add", [], [[]]]];',
                'if (isNull _src) exitWith { false };',
                'if (_remote && {!([_src, _req, _add isNotEqualTo []] call _mayEdit)}) exitWith {',
                'diag_log format ["[COMSPEC ATAK NATIVE][WARN][CAM] Partage de %1 refusé à %2 (ni poseur ou porteur, ni accès à la caméra)", _src, name _req];',
                'false',
                '};',
                'private _acc = if (_add isEqualTo []) then { [] } else {',
                'private _new = _add select { _x isEqualType [] && {(count _x) isEqualTo 4} && {(_x findIf { !(_x isEqualType "") || {(count _x) > 64} }) < 0} };',
                'if ((count _new) > 64) then { _new resize 64; };',
                'private _ids = _new apply { _x select 0 };',
                'private _all = (([_src] call _accessOf) select { !((_x param [0, ""]) in _ids) }) + _new;',
                'if ((count _all) > 128) then { _all = _all select [(count _all) - 128, 128]; };',
                '_all',
                '};',
                'private _tab = (localNamespace getVariable ["COMSPEC_ATAK_CamAcc", []]) select { !isNull (_x select 0) && {(_x select 0) isNotEqualTo _src} };',
                '_tab pushBack [_src, _acc];',
                'localNamespace setVariable ["COMSPEC_ATAK_CamAcc", _tab];',
                '_src setVariable ["COMSPEC_ATAK_ShareAcc", _acc, true];',
                'true',
                '}',
            ],
        ], 'fn_camShare');
        // Tables du serveur (R5) : en localNamespace seulement ; le poseur écrit au seul registre de fn_camAction, la
        // liste d'accès au seul « srvAcc » ; aucune autre mention de ces noms dans le mod.
        $uses = [];
        $named = 0;
        foreach ($this->sources() as $rel => $units) {
            $named += (int) preg_match_all('/\bCOMSPEC_ATAK_Cam(?:Owners|Acc)\b/', $units[0]);
            preg_match_all('/\blocalNamespace ([gs]etVariable) \["(COMSPEC_ATAK_Cam(?:Owners|Acc))"/', $units[0], $m, PREG_SET_ORDER);
            foreach ($m as $x) {
                $uses[] = basename($rel) . ' ' . $x[1] . ' ' . $x[2];
            }
        }
        sort($uses);
        self::assertSame(count($uses), $named);
        self::assertSame([
            'fn_camAction.sqf getVariable COMSPEC_ATAK_CamOwners',
            'fn_camAction.sqf setVariable COMSPEC_ATAK_CamOwners',
            'fn_camShare.sqf getVariable COMSPEC_ATAK_CamAcc',
            'fn_camShare.sqf getVariable COMSPEC_ATAK_CamAcc',
            'fn_camShare.sqf getVariable COMSPEC_ATAK_CamOwners',
            'fn_camShare.sqf setVariable COMSPEC_ATAK_CamAcc',
        ], $uses);
        self::assertStringContainsString('class comspec_atak_native_fnc_camShare { allowedTargets = 2; };', $this->remoteExecFunctions(self::MAIN . '/config.cpp'));
        $files = $this->sources();
        $lists = $this->remoteLists($files);
        self::assertSame(['srvAcc'], $lists['comspec_atak_native_fnc_camshare'] ?? null);
        [$sent] = $this->actionsSent($files, $lists);
        self::assertSame(['srvAcc'], array_keys($sent['comspec_atak_native_fnc_camshare'] ?? []));
    }

    public function testScreenRequestsAreJudgedOnTheRequesterFoundByTheServer(): void
    {
        // Lot mpauth3 (SEC-005) : le gestionnaire CBA comspec_atak_native_screen (fn_screenCast) ne contrôlait rien. Tout
        // client désignait comme écran n'importe quel objet, avec nom, portée, camp et texture de son choix (l'image se
        // pose sur l'objet chez chaque joueur), ou retirait un écran. Les demandes arrivent par remoteExecCall vers 2 et
        // sont jugées sur le demandeur retrouvé par remoteExecutedOwner, avec la règle du module Zeus qui les envoie :
        // Zeus, hôte, admin connecté ou UID autorisé (fn_adminAllowed). Le module Eden tourne sur le serveur.
        $code = $this->stripSqfComments($this->read(self::MAIN . '/functions/pages/fn_screenCast.sqf'));
        $head = $this->between($code, 'params [["_mode", ""], ["_a", objNull]];', 'switch (_mode) do {');
        self::assertMatchesRegularExpression('/^params \[\["_mode", ""\], \["_a", objNull\]\];\s*'
            . 'if \(isRemoteExecutedJIP\) exitWith \{[^{}]*false\s*\};\s*'
            . 'private _remote = isMultiplayer && \{isRemoteExecuted\} && \{remoteExecutedOwner isNotEqualTo 2\};\s*'
            . 'private _req = objNull;\s*'
            . 'if \(_remote\) then \{ _req = \[remoteExecutedOwner\] call comspec_atak_native_fnc_reqUnit; \};\s*'
            . 'if \(_remote && \{!\(_mode in \["add", "remove"\]\)\}\) exitWith \{[^{}]*false\s*\};\s*'
            . 'if \(_remote && \{isNull _req\}\) exitWith \{[^{}]*false\s*\};\s*'
            . 'if \(_remote && \{!\(\[_req\] call comspec_atak_native_fnc_adminAllowed\)\}\) exitWith \{[^{}]*false\s*\};/', $head);
        $this->assertRequesterRulesArePinned();
        foreach (['add', 'remove'] as $mode) {
            self::assertStringContainsString('if (!isServer) exitWith { ["' . $mode . '", _a] remoteExecCall ["comspec_atak_native_fnc_screenCast", 2]; true };', $code, $mode);
        }
        // Branches entières (contre-expertise mpauth3 v1, O14, O15) : arguments typés, nom coupé à 64 caractères, camp
        // connu, portée de 3 à 500 m.
        $this->assertBlocks($code, [
            'case "add": {' => [
                'case "add": {',
                'if !(_a isEqualType []) exitWith { false };',
                '_a params [["_o", objNull, [objNull]], ["_name", "", [""]], ["_range", 25, [0]], ["_side", "", [""]], ["_sel", 0, [0]], ["_ace", true, [true]]];',
                'if (isNull _o) exitWith { false };',
                'if (!isServer) exitWith { ["add", _a] remoteExecCall ["comspec_atak_native_fnc_screenCast", 2]; true };',
                'if ((count _name) > 64) then { _name = _name select [0, 64]; };',
                'if !(_side in ["", "WEST", "EAST", "GUER", "CIV"]) then { _side = ""; };',
                'if (_name isEqualTo "") then { _name = getText (configOf _o >> "displayName"); };',
                'if (_name isEqualTo "") then { _name = "Écran"; };',
                'private _list = (missionNamespace getVariable ["COMSPEC_ATAK_Screens", []]) select { !isNull _x };',
                'if !(_o in _list) then { _o setVariable ["COMSPEC_ATAK_ScreenCast", [], true]; };',
                '_o setVariable ["COMSPEC_ATAK_Screen", [_name, (_range max 3) min 500, _side, _sel max 0, _ace], true];',
                '_list pushBackUnique _o;',
                'missionNamespace setVariable ["COMSPEC_ATAK_Screens", _list, true];',
                'private _off = missionNamespace getVariable ["COMSPEC_ATAK_ScreensOff", []];',
                'if (_o in _off) then { missionNamespace setVariable ["COMSPEC_ATAK_ScreensOff", _off select { !isNull _x && {_x isNotEqualTo _o} }, true]; };',
                'true',
                '}',
            ],
            'case "remove": {' => [
                'case "remove": {',
                'if (!(_a isEqualType objNull) || {isNull _a}) exitWith { false };',
                'if (!isServer) exitWith { ["remove", _a] remoteExecCall ["comspec_atak_native_fnc_screenCast", 2]; true };',
                '_a setVariable ["COMSPEC_ATAK_ScreenCast", [], true];',
                '_a setVariable ["COMSPEC_ATAK_Screen", nil, true];',
                'missionNamespace setVariable ["COMSPEC_ATAK_Screens", (missionNamespace getVariable ["COMSPEC_ATAK_Screens", []]) select { !isNull _x && {_x isNotEqualTo _a} }, true];',
                'private _off = (missionNamespace getVariable ["COMSPEC_ATAK_ScreensOff", []]) select { !isNull _x };',
                '_off pushBackUnique _a;',
                'missionNamespace setVariable ["COMSPEC_ATAK_ScreensOff", _off, true];',
                'true',
                '}',
            ],
        ], 'fn_screenCast');
        self::assertStringNotContainsString('CBA_fnc_addEventHandler', $code);
        self::assertStringNotContainsString('CBA_fnc_serverEvent', $code);
        self::assertStringContainsString('class comspec_atak_native_fnc_screenCast { allowedTargets = 2; };', $this->remoteExecFunctions(self::MAIN . '/config.cpp'));
        // Plus de gestionnaire ni d'émetteur de l'événement dans main/ et connect/ ; actions envoyées = liste distante.
        $files = $this->sources();
        $left = [];
        foreach ($files as $rel => $units) {
            foreach ($units as $u) {
                if (preg_match('/(["\'])comspec_atak_native_screen\1/i', $u) === 1) {
                    $left[$rel] = true;
                }
            }
        }
        self::assertSame([], array_keys($left));
        $lists = $this->remoteLists($files);
        self::assertSame(['add', 'remove'], $lists['comspec_atak_native_fnc_screencast'] ?? null);
        [$sent] = $this->actionsSent($files, $lists);
        $got = array_keys($sent['comspec_atak_native_fnc_screencast'] ?? []);
        sort($got);
        self::assertSame(['add', 'remove'], $got);
        // L'API de mission documentée dit la règle (MODULES.md, en français et en anglais).
        $doc = $this->read('mod/COMSPEC_ATAK_Native/MODULES.md');
        self::assertStringContainsString("(remoteExecCall), qui ne l'accepte que d'un Zeus, de l'hôte, d'un admin connecté ou d'un UID autorisé.", $doc);
        self::assertStringContainsString('which accepts them only from a Zeus, the host, a logged-in admin or an authorized UID.', $doc);
    }

    public function testServerRequestsMissingFromTheWhitelistAreDeclaredWithTheirGuard(): void
    {
        // Lot mpauth3 (SEC-006) : quatre fonctions appelées par un client avec remoteExecCall manquaient à CfgRemoteExec
        // (refusées sans bruit en mode 1) : conditions réseau, zones interdites au drone, passerelle de véhicule, heure H
        // de la montre. Chacune y entre avec une garde en tête, juste après params : rien de la file JIP, et d'un autre
        // poste que le serveur, seulement sa demande. Sur l'hôte d'une partie locale, un client faisait exécuter
        // « apply », « add » ou « relay » : l'hôte se renvoyait la demande (poste 2, donc admise) et agissait en son nom.
        $cfg = $this->remoteExecFunctions(self::MAIN . '/config.cpp');
        $files = $this->sources();
        $lists = $this->remoteLists($files);
        [$sent] = $this->actionsSent($files, $lists);
        foreach ([
            'netConfig' => ['network/fn_netConfig.sqf', 'params [["_mode", "open"], ["_a", displayNull], ["_b", ""]];', '_mode', ['server'], 'allowedTargets = 2;'],
            'noFly' => ['drone/fn_noFly.sqf', 'params [["_act", "list"], ["_arg", []]];', '_act', ['srvAdd', 'srvRemove', 'srvClear'], 'allowedTargets = 2;'],
            'vehicleGateway' => ['vehicle/fn_vehicleGateway.sqf', 'params [["_act", "list"], ["_v", objNull]];', '_act', ['srv'], 'allowedTargets = 2;'],
            'watch' => ['garmin/fn_watch.sqf', 'params [["_act", "tick"], ["_arg", []]];', '_act', ['hhRecv'], 'allowedTargets = 0; jip = 0;'],
        ] as $fn => [$file, $params, $sel, $acts, $targets]) {
            $code = $this->stripSqfComments($this->read(self::MAIN . '/functions/' . $file));
            self::assertSame('', trim(substr($code, 0, (int) strpos($code, $params))), $fn);
            $quoted = implode(', ', array_map(static fn (string $a): string => '"' . $a . '"', $acts));
            self::assertMatchesRegularExpression('/^' . preg_quote($params, '/') . '\s*'
                . 'if \(isRemoteExecutedJIP\) exitWith \{[^{}]*\};\s*'
                . 'private _remote = isMultiplayer && \{isRemoteExecuted\} && \{remoteExecutedOwner isNotEqualTo 2\};\s*'
                . 'if \(_remote && \{!\(' . $sel . ' in \[' . preg_quote($quoted, '/') . '\]\)\}\) exitWith \{[^{}]*\};/', trim($code), $fn);
            self::assertStringContainsString('class comspec_atak_native_fnc_' . $fn . ' { ' . $targets . ' };', $cfg, $fn);
            $key = 'comspec_atak_native_fnc_' . strtolower($fn);
            self::assertSame($acts, $lists[$key] ?? null, $fn);
            $got = array_keys($sent[$key] ?? []);
            sort($got);
            $want = $acts;
            sort($want);
            self::assertSame($want, $got, $fn);
        }
        // Les demandes restent jugées sur remoteExecutedOwner, comme avant (la garde ne fait que fermer le reste).
        self::assertStringContainsString('private _ok = !isMultiplayer || {_owner in [0, 2, clientOwner]} || {(admin _owner) > 0};', $this->read(self::MAIN . '/functions/network/fn_netConfig.sqf'));
        self::assertStringContainsString('private _i = allPlayers findIf { (owner _x) isEqualTo _owner };', $this->read(self::MAIN . '/functions/drone/fn_noFly.sqf'));
        self::assertStringContainsString('if ((objectParent _u) isNotEqualTo _veh && {(_u distance _veh) > 10}) exitWith {};', $this->read(self::MAIN . '/functions/vehicle/fn_vehicleGateway.sqf'));
        // Heure H reçue d'un autre poste : la montre ne se ré-appelle pas (« tick » serait refusée dans ce contexte).
        $hh = $this->between($this->stripSqfComments($this->read(self::MAIN . '/functions/garmin/fn_watch.sqf')), 'case "hhRecv": {', 'case "hhWarn": {');
        self::assertNotEmpty($hh);
        self::assertStringNotContainsString('call _redraw', $hh);
        self::assertSame(1, preg_match_all('/\bcall\s+comspec_atak_native_fnc_watch\b/', $hh));
        self::assertStringContainsString('}, [_at, _what, _id]] call CBA_fnc_waitUntilAndExecute;', $hh);
    }

    public function testCaptureOrdersReachTheObjectMachineOnlyFromTheServer(): void
    {
        // Lot mpauth3 (SEC-023) : l'événement CBA comspec_atak_native_capLocal faisait exécuter la branche « local » de
        // fn_captureServer sur la machine visée, sans dire qui l'avait levé. Tout client y faisait retirer ou donner un
        // objet à un joueur (le sien est local à sa machine) ou à une IA, déplacer une IA ou lui faire traquer qui il
        // voulait. Les ordres partent maintenant du serveur par remoteExecCall vers l'objet, à fn_captureLocal, dont la
        // garde de tête refuse tout appel distant qui ne vient pas du serveur et tout rejeu JIP.
        $local = $this->stripSqfComments($this->read(self::MAIN . '/functions/shell/fn_captureLocal.sqf'));
        self::assertMatchesRegularExpression('/^\s*params \[\["_mode", ""\], \["_o", objNull\], \["_v", ""\]\];\s*'
            . 'if \(isRemoteExecutedJIP \|\| \{isRemoteExecuted && \{remoteExecutedOwner isNotEqualTo 2\}\}\) exitWith \{[^{}]*\};\s*'
            . 'if \(!\(_o isEqualType objNull\) \|\| \{isNull _o\} \|\| \{!local _o\}\) exitWith \{\};\s*switch \(_mode\) do \{/', $local);
        preg_match_all('/^    case "([^"]+)"/m', $local, $cases);
        self::assertSame(['strip', 'give', 'move', 'hunt'], $cases[1]);
        $srv = $this->stripSqfComments($this->read(self::MAIN . '/functions/shell/fn_captureServer.sqf'));
        self::assertStringNotContainsString('case "local":', $srv);
        // Émetteurs : le serveur seul (tick, exploit, « recover » de l'app Admin), vers l'objet.
        foreach ([
            '["move", _ai, _pos] remoteExecCall ["comspec_atak_native_fnc_captureLocal", _ai];',
            '["strip", _o, _cls] remoteExecCall ["comspec_atak_native_fnc_captureLocal", _o];',
            '["give", _ai, _cls] remoteExecCall ["comspec_atak_native_fnc_captureLocal", _ai];',
            '{ ["hunt", leader _x, [_targets, _hunt]] remoteExecCall ["comspec_atak_native_fnc_captureLocal", leader _x]; } forEach _grps;',
        ] as $call) {
            self::assertStringContainsString($call, $srv);
        }
        $from = [];
        foreach ($this->sources() as $rel => $units) {
            foreach ($units as $code) {
                foreach ($this->remoteCalls($code) as [, $right]) {
                    if ($right !== null && strcasecmp((string) $this->literal($right[0]), 'comspec_atak_native_fnc_captureLocal') === 0) {
                        $from[basename($rel)] = true;
                    }
                }
            }
        }
        ksort($from);
        self::assertSame(['fn_adminServer.sqf', 'fn_captureServer.sqf'], array_keys($from));
        $cfg = $this->cppCode($this->read(self::MAIN . '/config.cpp'));
        self::assertStringContainsString('class captureServer {}; class captureLocal {};', $cfg);
        self::assertStringContainsString('class comspec_atak_native_fnc_captureLocal { allowedTargets = 0; jip = 0; };', $this->remoteExecFunctions(self::MAIN . '/config.cpp'));
        self::assertStringNotContainsString('comspec_atak_native_capLocal', $this->stripSqfComments($this->read(self::MAIN . '/XEH_preInit.sqf')));
    }

    public function testDroneOrdersReachTheDroneOnlyFromItsPilotThroughTheServer(): void
    {
        // Lot mpauth3 (SEC-022) : l'événement CBA comspec_atak_native_droneCmd faisait exécuter n'importe quel ordre à
        // n'importe quel drone, à la demande de n'importe quel client (frappe, atterrissage, désappairage, transfert vers
        // soi, « relocal » sur le serveur). Les demandes vont au serveur (fn_droneServer), qui les juge sur le pilote qu'il
        // tient lui-même (localNamespace, jamais la variable publique COMSPEC_DroneOwner) et ne relaie que celles du
        // pilote à fn_droneCmd, dont la garde de tête refuse tout appel distant qui ne vient pas du serveur.
        $srv = $this->stripSqfComments($this->read(self::MAIN . '/functions/drone/fn_droneServer.sqf'));
        self::assertMatchesRegularExpression('/^\s*params \[\["_d", objNull\], \["_cmd", ""\], \["_args", \[\]\]\];\s*'
            . 'if \(!isServer\) exitWith \{\};\s*'
            . 'if \(isRemoteExecutedJIP\) exitWith \{[^{}]*\};\s*'
            . 'private _remote = isMultiplayer && \{isRemoteExecuted\} && \{remoteExecutedOwner isNotEqualTo 2\};\s*'
            . 'private _req = \[objNull, player\] select hasInterface;\s*'
            . 'if \(_remote\) then \{ _req = \[remoteExecutedOwner\] call comspec_atak_native_fnc_reqUnit; \};\s*'
            . 'if \(_remote && \{isNull _req\}\) exitWith \{[^{}]*\};/', $srv);
        $this->assertRequesterRulesArePinned();
        // Le pilote est celui que tient le serveur ; la variable publique n'est lue qu'une fois, pour la lui remettre.
        self::assertStringContainsString('private _db = (localNamespace getVariable ["COMSPEC_ATAK_DroneOwners", []]) select {', $srv);
        self::assertStringContainsString('localNamespace setVariable ["COMSPEC_ATAK_DroneOwners", _db];', $srv);
        self::assertStringContainsString('private _pilot = if (_i >= 0) then { (_db select _i) select 1 } else { "" };', $srv);
        self::assertStringContainsString('private _isPilot = isNull _req || {_pilot isNotEqualTo "" && {_pilot isEqualTo _uid}};', $srv);
        self::assertSame(1, substr_count($srv, 'getVariable ["COMSPEC_DroneOwner"'));
        self::assertStringContainsString('if ((_d getVariable ["COMSPEC_DroneOwner", ""]) isNotEqualTo _pilot) then { _d setVariable ["COMSPEC_DroneOwner", _pilot, true]; };', $srv);
        // Chaque demande est jugée avant son premier effet (registre, relais au drone, setGroupOwner).
        $default = (string) substr($srv, (int) strpos($srv, '    default {'));
        foreach ([
            [$this->between($srv, 'case "pair": {', 'case "release": {'), ['(_req distance _d) > 25', 'if (_pilot isNotEqualTo "" && {_pilot isNotEqualTo _uid} && {[_pilot] call _online}) exitWith {'], '[_uid] call _set;'],
            [$this->between($srv, 'case "release": {', 'case "transferIn": {'), ['if (!_isPilot && {[_pilot] call _online}) exitWith {'], '[""] call _set;'],
            [$this->between($srv, 'case "transferIn": {', 'case "relocal": {'), ['if (!_isPilot) exitWith {', '(_t distance _req) > 60', '(_x select 1) isEqualTo _to'], '[_to] call _set;'],
            [$this->between($srv, 'case "transferIn": {', 'case "relocal": {'), ['if (!_isPilot) exitWith {'], 'remoteExecCall ["comspec_atak_native_fnc_droneCmd", _t];'],
            [$this->between($srv, 'case "relocal": {', '    default {'), ['if (!_isPilot) exitWith {'], 'setGroupOwner'],
            [$default, ['if (!_isPilot) exitWith {', '!(_cmd in (_orders + ["force"]))'], '[_d, _cmd, _args] remoteExecCall ["comspec_atak_native_fnc_droneCmd", _d];'],
        ] as $n => [$body, $checks, $effect]) {
            $at = strpos($body, $effect);
            self::assertNotFalse($at, "branche $n : $effect");
            foreach ($checks as $check) {
                $c = strpos($body, $check);
                self::assertNotFalse($c, "branche $n : $check");
                self::assertLessThan($at, $c, "branche $n : $check");
            }
        }
        // Règles entières (contre-expertise mpauth3 v1, O6, O7) : qui devient pilote, et quand le serveur l'écrit.
        $this->assertBlocks($srv, [
            'private _online = {' => ['private _online = { params ["_u"]; (allPlayers findIf { (getPlayerUID _x) isEqualTo _u }) >= 0 }'],
            'private _set = {' => [
                'private _set = {',
                'params ["_u"];',
                'if (_i >= 0) then { _db deleteAt _i; };',
                'if (_u isNotEqualTo "") then { _db pushBack [_d, _u]; };',
                'localNamespace setVariable ["COMSPEC_ATAK_DroneOwners", _db];',
                '_d setVariable ["COMSPEC_DroneOwner", _u, true];',
                '}',
            ],
            'case "pair": {' => [
                'case "pair": {',
                'if (isNull _req) exitWith { ["appairage sans joueur demandeur"] call _deny; };',
                'if (!unitIsUAV _d || {!alive _d}) exitWith { ["objet qui n\'est pas un drone en état"] call _deny; };',
                'if (!alive _req || {(_req distance _d) > 25}) exitWith { ["demandeur à plus de 25 m du drone", "Posez le drone à vos pieds pour l\'appairer"] call _deny; };',
                'if (_pilot isNotEqualTo "" && {_pilot isNotEqualTo _uid} && {[_pilot] call _online}) exitWith { [format ["drone de %1, toujours en partie", _pilot], "Ce drone est déjà appairé à un autre téléphone"] call _deny; };',
                '[_uid] call _set;',
                '}',
            ],
            'case "release": {' => [
                'case "release": {',
                'if (_pilot isEqualTo "") exitWith { ["drone appairé à personne"] call _deny; };',
                'if (!_isPilot && {[_pilot] call _online}) exitWith { [format ["drone de %1, toujours en partie", _pilot], "Ce drone est déjà appairé à un autre téléphone"] call _deny; };',
                '[""] call _set;',
                '_d setVariable ["COMSPEC_DroneLock", nil, true];',
                'if (alive _d) then { [_d, "release", []] remoteExecCall ["comspec_atak_native_fnc_droneCmd", _d]; };',
                '}',
            ],
            'case "transferIn": {' => [
                'case "transferIn": {',
                '_args params [["_from", ""], ["_to", ""], ["_home", []], ["_stage", "DONE"]];',
                'if (isNull _req) exitWith { ["transfert sans joueur demandeur"] call _deny; };',
                'if (!_isPilot) exitWith { ["transfert demandé par un autre que le pilote", ["Ce drone est déjà appairé à un autre téléphone", "Ce drone n\'est plus appairé au téléphone du pilote"] select (_pilot isEqualTo "")] call _deny; };',
                'if (!alive _d || {!(_to isEqualType "")} || {!(_stage in ["HELLO", "DONE"])}) exitWith { ["transfert mal formé"] call _deny; };',
                'private _k = allPlayers findIf { (getPlayerUID _x) isEqualTo _to };',
                'private _t = if (_k >= 0) then { allPlayers select _k } else { objNull };',
                'if (isNull _t || {!alive _t} || {(_t distance _req) > 60}) exitWith { ["destinataire absent ou à plus de 60 m du pilote", "Transfert annulé : destinataire hors de portée"] call _deny; };',
                'if ((_db findIf { (_x select 0) isNotEqualTo _d && {(_x select 1) isEqualTo _to} }) >= 0) exitWith { ["destinataire déjà pilote d\'un autre drone", format ["%1 pilote déjà un drone", name _t]] call _deny; };',
                'if !(_home isEqualTypeArray [0, 0, 0]) then { _home = []; };',
                'if (_stage isEqualTo "DONE") then {',
                '[_to] call _set;',
                '_d setVariable ["COMSPEC_DroneLock", "", true];',
                '};',
                '[_d, "transferIn", [name _req, _to, _home, _stage]] remoteExecCall ["comspec_atak_native_fnc_droneCmd", _t];',
                '}',
            ],
        ], 'fn_droneServer');
        // Ordres relayés : exactement ceux que fn_droneCmd exécute là où le drone est local (« release » a sa branche).
        self::assertSame(1, preg_match('/private _orders = \[([^\]]*)\];/', $srv, $m));
        preg_match_all('/"([^"]+)"/', $m[1], $o);
        $orders = $o[1];
        $cmd = $this->stripSqfComments($this->read(self::MAIN . '/functions/drone/fn_droneCmd.sqf'));
        preg_match_all('/^    case "([^"]+)"/m', $cmd, $cases);
        $want = array_values(array_diff($cases[1], ['release']));
        sort($want);
        $got = $orders;
        sort($got);
        self::assertSame($want, $got);
        $act = $this->stripSqfComments($this->read(self::MAIN . '/functions/drone/fn_droneAction.sqf'));
        self::assertSame(1, preg_match('/private _flight = \[([^\]]*)\];/', $act, $f));
        preg_match_all('/"([^"]+)"/', $f[1], $fl);
        self::assertSame([], array_values(array_diff($fl[1], $orders)));
        // fn_droneCmd : garde de tête (testCallsReplayedFromTheJipQueueAreRefusedByTheServerOnlyGuards), plus de
        // « relocal » ni d'émetteur ; un refus du serveur n'y fait qu'afficher un message.
        self::assertStringNotContainsString('setGroupOwner', $cmd);
        self::assertStringNotContainsString('CBA_fnc_targetEvent', $this->between($cmd, 'params [', 'if (isNull _d || {!alive _d} || {!local _d}) exitWith {};'));
        $denied = $this->between($cmd, 'if (_cmd isEqualTo "denied") exitWith {', 'if (isNull _d || {!alive _d} || {!local _d}) exitWith {};');
        self::assertStringContainsString('["WARNING", _msg, 4, 40] call comspec_atak_native_fnc_notify;', $denied);
        self::assertStringNotContainsString('remoteExec', $denied);
        self::assertStringNotContainsString('fnc_droneAction', $denied);
        // Émetteurs : seul fn_droneServer parle à fn_droneCmd ; les téléphones parlent au serveur (vers 2), avec des
        // demandes qu'il connaît.
        $toCmd = [];
        $literal = [];
        foreach ($this->sources() as $rel => $units) {
            foreach ($units as $code) {
                foreach ($this->remoteCalls($code) as [$left, $right]) {
                    $fn = strtolower((string) ($right === null ? '' : $this->literal($right[0])));
                    if ($fn === 'comspec_atak_native_fnc_dronecmd') {
                        $toCmd[basename($rel)] = true;
                    } elseif ($fn === 'comspec_atak_native_fnc_droneserver') {
                        self::assertSame('2', trim($right[1] ?? ''), $rel);
                        $c = $this->literal($left[1] ?? '');
                        if ($c !== null) {
                            $literal[$c] = true;
                        }
                    }
                }
            }
        }
        self::assertSame(['fn_droneServer.sqf'], array_keys($toCmd));
        $sent = array_keys($literal);
        sort($sent);
        self::assertSame(['force', 'goto', 'manual', 'pair', 'release', 'relocal', 'rth', 'transferIn'], $sent);
        self::assertSame([], array_values(array_diff($sent, array_merge($orders, ['force', 'pair', 'release', 'transferIn', 'relocal']))));
        // L'appairage au pied est déclaré au serveur, sinon il refuserait les ordres du nouveau pilote.
        self::assertMatchesRegularExpression('/\[_new\] call _adopt;\s*\[_new, "pair", \[\]\] remoteExecCall \["comspec_atak_native_fnc_droneServer", 2\];/', $act);
        $cfg = $this->cppCode($this->read(self::MAIN . '/config.cpp'));
        self::assertStringContainsString('class droneCmd {}; class droneServer {};', $cfg);
        $rex = $this->remoteExecFunctions(self::MAIN . '/config.cpp');
        self::assertStringContainsString('class comspec_atak_native_fnc_droneServer { allowedTargets = 2; };', $rex);
        self::assertStringContainsString('class comspec_atak_native_fnc_droneCmd { allowedTargets = 0; jip = 0; };', $rex);
        self::assertStringNotContainsString('comspec_atak_native_droneCmd', $this->stripSqfComments($this->read(self::MAIN . '/XEH_preInit.sqf')));
    }

    public function testTocScreenSaveReadsOnlyTheServerCopyAndRecreatesOnlyScreenClasses(): void
    {
        // Lot mpauth3 (SEC-033, contre-expertise v5, N17) : fn_tocPersist « write » lisait le registre public
        // COMSPEC_ATAK_TocScreens et l'état public COMSPEC_ATAK_TocScr, que tout client peut écrire, puis « restore »
        // recréait toute classe de CfgVehicles aux parties suivantes. Le serveur tient maintenant sa propre copie, jamais
        // diffusée (localNamespace COMSPEC_ATAK_TocSrv, COMSPEC_ATAK_TocMade) : seule lue pour juger, sauver, recréer et
        // supprimer ; « restore » ne recrée qu'une classe d'écran admise, par « spawn », et retrouve un objet de mission.
        $persist = $this->stripSqfComments($this->read(self::MAIN . '/functions/toc/fn_tocPersist.sqf'));
        $write = $this->between($persist, 'case "write": {', 'case "clear": {');
        self::assertStringContainsString('private _scr = ((localNamespace getVariable ["COMSPEC_ATAK_TocSrv", []]) select {', $write);
        self::assertStringNotContainsString('COMSPEC_ATAK_TocScreens', $persist);
        self::assertStringNotContainsString('"COMSPEC_ATAK_TocScr"', $persist);
        self::assertStringNotContainsString('COMSPEC_ATAK_TocOwned', $persist);
        $restore = (string) substr($persist, (int) strpos($persist, 'case "restore": {'));
        self::assertStringNotContainsString('createVehicle', $restore);
        self::assertStringContainsString('private _admitted = (["classes"] call comspec_atak_native_fnc_tocScreen) apply { _x select 1 };', $restore);
        self::assertStringContainsString('{!(_cfg isEqualTypeParams ["", "", 0, "", 0, [], true, true])}) then { continue };', $restore);
        // Recréation : seulement pour un objet introuvable, posé en jeu, d'une classe admise, et par « spawn ».
        $order = ['if (isNull _o) then {', 'if !(_cfg select 7) then { continue };', 'if !(_cls in _admitted) then {', 'continue', '_o = ["spawn", [ASLToAGL _pos, 0, _cls, _cfg select 0, _cfg select 1, _cfg select 3, _cfg select 6]] call comspec_atak_native_fnc_tocScreen;'];
        $at = 0;
        foreach ($order as $step) {
            $i = strpos($restore, $step, $at);
            self::assertNotFalse($i, $step);
            $at = (int) $i + strlen($step);
        }
        // Cadrage remis par « view » (validé comme une demande), plus écrit dans la variable publique.
        self::assertStringContainsString('["view", [_o, "zoom", _cfg select 4]] call comspec_atak_native_fnc_tocScreen;', $restore);
        self::assertStringContainsString('["view", [_o, "center", _cfg select 5]] call comspec_atak_native_fnc_tocScreen;', $restore);
        // fn_tocScreen : chaque écriture publique de l'état et du registre passe par la copie du serveur (_srvSet).
        $toc = $this->stripSqfComments($this->read(self::MAIN . '/functions/toc/fn_tocScreen.sqf'));
        $srvSet = $this->between($toc, 'private _srvSet = {', 'private _classes = {');
        self::assertStringContainsString('localNamespace setVariable ["COMSPEC_ATAK_TocSrv", _l];', $srvSet);
        self::assertSame(2, substr_count($toc, 'setVariable ["COMSPEC_ATAK_TocScr"'));
        self::assertSame(2, substr_count($srvSet, 'setVariable ["COMSPEC_ATAK_TocScr"'));
        self::assertSame(1, substr_count($toc, 'setVariable ["COMSPEC_ATAK_TocScreens"'));
        self::assertStringContainsString('missionNamespace setVariable ["COMSPEC_ATAK_TocScreens", _l apply { _x select 0 }, true];', $srvSet);
        self::assertStringContainsString('private _srvList = { (localNamespace getVariable ["COMSPEC_ATAK_TocSrv", []]) select { !isNull (_x select 0) } };', $toc);
        // Ce que le serveur lit pour décider : sa copie, jamais l'état ni le registre publics.
        foreach ([
            'add' => [$this->between($toc, 'case "add": {', 'case "remove": {'), 'private _old = [_o] call _srvOf;'],
            'remove' => [$this->between($toc, 'case "remove": {', 'case "spawn": {'), 'private _owned = _a in _made;'],
            'view' => [$this->between($toc, 'case "view": {', 'case "load": {'), 'private _c = [_o] call _srvOf;'],
            'viewer' => [$this->between($toc, 'private _viewer = {', 'private _remote = '), 'private _s = ([_o] call _srvOf) param [1, -1];'],
        ] as $what => [$body, $read]) {
            self::assertStringContainsString($read, $body, $what);
            self::assertStringNotContainsString('_cfgOf', $body, $what);
            self::assertStringNotContainsString('getVariable ["COMSPEC_ATAK_TocScreens"', $body, $what);
            self::assertStringNotContainsString('getVariable ["COMSPEC_ATAK_TocOwned"', $body, $what);
        }
        self::assertStringNotContainsString('getVariable ["COMSPEC_ATAK_TocOwned"', $toc);
        self::assertStringContainsString('localNamespace setVariable ["COMSPEC_ATAK_TocMade", ((localNamespace getVariable ["COMSPEC_ATAK_TocMade", []]) select { !isNull _x }) + [_o]];', $this->between($toc, 'case "spawn": {', 'case "view": {'));
        // Aucun autre fichier n'écrit l'état ou le registre publics des écrans.
        $writers = [];
        foreach ($this->sources() as $rel => $units) {
            if (preg_match('/setVariable\s*\[\s*"COMSPEC_ATAK_TocScr(?:eens)?"/', $units[0]) === 1) {
                $writers[] = basename($rel);
            }
        }
        self::assertSame(['fn_tocScreen.sqf'], $writers);
    }

    public function testDebriefRightFromAthenaIsKeptByTheServerUnderTheRequesterUid(): void
    {
        // Lot mpauth3 (SEC-034, contre-expertise v5, N17) : le droit d'imposer le CR donné par Athena était la variable
        // publique COMSPEC_ATAK_DebriefRight du joueur, posée par son propre client (fn_debrief « cfg ») et crue par le
        // serveur (fn_debriefServer). Tout client se la donnait, ou la donnait à un autre, puis imposait, relançait ou
        // levait un CR. Le serveur ne peut pas connaître ce droit : le site ne le calcule que pour le téléphone du joueur
        // (ligne « debrief_cfg » de AdminControl.Poll). Le poste du joueur le déclare donc, pour lui seul, et le serveur
        // le garde sous l'UID retrouvé par remoteExecutedOwner, jamais diffusé. Le réglage du serveur
        // comspec_atak_native_debrief_site (coché par défaut : la fonction reste) permet de ne plus l'admettre.
        $files = $this->sources();
        $left = [];
        foreach ($files as $rel => $units) {
            foreach ($units as $u) {
                if (str_contains($u, 'COMSPEC_ATAK_DebriefRight')) {
                    $left[$rel] = true;
                }
            }
        }
        self::assertSame([], array_keys($left));
        $code = $this->stripSqfComments($this->read(self::MAIN . '/functions/debrief/fn_debriefServer.sqf'));
        // Droit jugé sur le demandeur retrouvé : hôte, admin, UID et Zeus d'abord, puis la liste du serveur, selon le réglage.
        $rights = $this->between($code, 'if (_act in ["force", "remind", "lift", "pull"]) then {', 'if (!_ok) exitWith {');
        self::assertMatchesRegularExpression('/_ok = \[_caller\] call comspec_atak_native_fnc_adminAllowed && \{!_zeusOnly \|\| \{missionNamespace getVariable \["comspec_atak_native_debrief_zeus", true\]\}\};\s*'
            . 'if \(!_ok && \{\(missionNamespace getVariable \["comspec_atak_native_debrief_site", true\]\) isEqualTo true\}\s*'
            . '&& \{\(getPlayerUID _caller\) in \(localNamespace getVariable \["COMSPEC_ATAK_DbfSiteRight", \[\]\]\)\}\) then \{\s*'
            . '_ok = true;\s*diag_log format \["\[COMSPEC ATAK NATIVE\]\[INFO\]\[DEBRIEF\] [^"]*", _act, name _caller, getPlayerUID _caller\];\s*\};\s*\};\s*$/', $rights);
        $this->assertRequesterRulesArePinned();
        // « right » : seule la valeur vient de la demande ; l'UID est celui du demandeur retrouvé, jamais un argument.
        $right = $this->between($code, 'case "right": {', 'case "hello": {');
        self::assertMatchesRegularExpression('/^case "right": \{\s*_args params \[\["_on", false\]\];\s*'
            . 'if !\(_on isEqualType true\) exitWith \{\};\s*'
            . 'private _uid = getPlayerUID _caller;\s*'
            . 'if \(_uid isEqualTo ""\) exitWith \{\};\s*'
            . 'private _l = \(localNamespace getVariable \["COMSPEC_ATAK_DbfSiteRight", \[\]\]\) - \[_uid\];\s*'
            . 'if \(_on\) then \{ _l pushBack _uid; \};\s*'
            . 'localNamespace setVariable \["COMSPEC_ATAK_DbfSiteRight", _l\];\s*\};\s*$/', $right);
        self::assertSame(1, substr_count($code, 'setVariable ["COMSPEC_ATAK_DbfSiteRight"'));
        // Le poste déclare par la voie gardée ; l'app ne propose le panneau que si le serveur admet ce droit.
        $client = $this->stripSqfComments($this->read(self::MAIN . '/functions/debrief/fn_debrief.sqf'));
        self::assertStringContainsString('["right", [_c get "rights"]] call _server;', $this->between($client, 'case "cfg": {', 'case "recv": {'));
        self::assertStringContainsString('if ((_cfg getOrDefault ["rights", false]) && {missionNamespace getVariable ["comspec_atak_native_debrief_site", true]}) exitWith { true };', $client);
        // Réglage du serveur, global, coché par défaut ; titre et aide traduits.
        $setting = '/\["comspec_atak_native_debrief_site", "CHECKBOX",\s*\["([^"]+)", "([^"]+)"\],\s*\["COMSPEC", "ATAK · Admin"\], true, 1\] call comspec_atak_native_fnc_addSetting;/u';
        self::assertSame(1, preg_match($setting, $this->read(self::MAIN . '/XEH_preInit.sqf'), $m));
        $en = $this->read(self::MAIN . '/i18n/en.txt');
        foreach ([$m[1], $m[2]] as $fr) {
            self::assertStringContainsString('["' . $fr . '", "', $en, $fr);
        }
    }

    public function testClientRelaysOfTocScreenAndDebriefRefuseACallFromAnotherClient(): void
    {
        // Lot mpauth3 (SEC-035, contre-expertise v5, N18) : les branches client de fn_tocScreen (« add », « remove »,
        // « spawn », « view ») et les relais de fn_debrief (« zeus », « sForce », « sLift », « sRemind », « sPull », « cfg »…)
        // transmettent une demande au serveur sous le nom du joueur du poste. Sans garde d'appel distant, en mode 2 (sans
        // liste blanche), un autre client les faisait jouer au poste d'un Zeus. Garde d'une ligne, première instruction
        // après params, sur le modèle de fn_adminClient : seul le serveur, jamais un rejeu de la file JIP.
        $remote = '{isRemoteExecutedJIP || {isRemoteExecuted && {remoteExecutedOwner isNotEqualTo 2}}}) exitWith {';
        $guards = [
            self::MAIN . '/functions/toc/fn_tocScreen.sqf' => 'if (!isServer && ' . $remote,
            self::MAIN . '/functions/debrief/fn_debrief.sqf' => 'if (isRemoteExecutedJIP || {isRemoteExecuted && {remoteExecutedOwner isNotEqualTo 2}}) exitWith {',
        ];
        foreach ($guards as $file => $guard) {
            $code = $this->stripSqfComments($this->read($file));
            self::assertSame(1, substr_count($code, $guard), $file);
            $open = strpos($code, 'params [');
            self::assertNotFalse($open, $file);
            self::assertSame('', trim(substr($code, 0, (int) $open)), $file);
            $body = $this->bracketBody($code, $open + strlen('params '));
            self::assertNotNull($body, $file);
            $after = substr($code, $open + strlen('params ') + strlen((string) $body) + 2);
            self::assertMatchesRegularExpression('/^\s*;\s*' . preg_quote($guard, '/') . '\s*diag_log format \["\[COMSPEC ATAK NATIVE\]\[WARN\]\[(?:TOC|DEBRIEF)\] [^"]*", _(?:mode|act), remoteExecutedOwner, isRemoteExecutedJIP\];\s*false\s*\};/', $after, $file);
        }
        // fn_tocScreen : les quatre relais du poste client sont les branches « if (!isServer) exitWith { … } ».
        $toc = $this->stripSqfComments($this->read(self::MAIN . '/functions/toc/fn_tocScreen.sqf'));
        preg_match_all('/if \(!isServer\) exitWith \{ \["(\w+)", _a\] remoteExecCall \["comspec_atak_native_fnc_tocScreen", 2\];/', $toc, $m);
        self::assertSame(['add', 'remove', 'spawn', 'view'], $m[1]);
        self::assertSame(4, substr_count($toc, 'remoteExecCall'));
        // fn_debrief : actions qui relaient au serveur, directement (_server) ou par une aide qui le fait (_add, _close).
        $dbf = $this->stripSqfComments($this->read(self::MAIN . '/functions/debrief/fn_debrief.sqf'));
        $braces = $this->pairsOf($this->blankStrings($dbf), '{', '}');
        $bodyAt = static function (array $match, int $i) use ($dbf, $braces): string {
            $o = $match[0][$i][1] + strlen($match[0][$i][0]) - 1;
            return substr($dbf, $o, ($braces[$o] ?? $o) - $o);
        };
        $helpers = ['_server'];
        preg_match_all('/^private (_\w+) = \{/m', $dbf, $hs, PREG_OFFSET_CAPTURE);
        foreach ($hs[1] as $i => [$name]) {
            if ($name !== '_server' && preg_match('/\bcall _server\b/', $bodyAt($hs, $i)) === 1) {
                $helpers[] = $name;
            }
        }
        $relays = [];
        preg_match_all('/^    case "(\w+)": \{/m', $dbf, $cases, PREG_OFFSET_CAPTURE);
        foreach ($cases[1] as $i => [$act]) {
            if (preg_match('/\bcall (?:' . implode('|', $helpers) . ')\b/', $bodyAt($cases, $i)) === 1) {
                $relays[] = $act;
            }
        }
        foreach (['zeus', 'sForce', 'sLift', 'sRemind', 'sPull', 'cfg'] as $act) {
            self::assertContains($act, $relays, $act);
        }
        // Ensemble exact : un relais ajouté à une autre action passe par ce test (lot mpauth3, revue de ses mutations).
        sort($relays);
        self::assertSame(['cfg', 'opened', 'read', 'recv', 'sForce', 'sLift', 'sPull', 'sRemind', 'send', 'site', 'zeus'], $relays);
        // Aucune action hors de la garde (lot mpauth3, SEC-030 : les messages du serveur arrivent par remoteExecCall).
        self::assertSame(0, preg_match('/^if \(!\(_act in /m', $dbf));
    }

    public function testDebriefServerMessagesReachThePhoneOnlyFromTheServer(): void
    {
        // Lot mpauth3 (SEC-030, contre-expertise v5, § 6.3) : les gestionnaires CBA comspec_atak_native_debriefRecv et
        // comspec_atak_native_debriefData tournaient chez chaque joueur sans savoir qui avait levé l'événement. Tout client
        // ouvrait chez un autre une fausse fiche, lui faisait déclarer à Athena un faux « forced » sous son nom, levait ses
        // CR ou affichait un faux suivi. Le serveur envoie maintenant ses messages par remoteExecCall à fn_debrief, qui
        // refuse tout autre émetteur et tout rejeu JIP ; le joueur local au serveur (hôte) est servi à l'image suivante,
        // hors de la pile de la demande d'un autre client.
        $srv = $this->stripSqfComments($this->read(self::MAIN . '/functions/debrief/fn_debriefServer.sqf'));
        self::assertStringNotContainsString('CBA_fnc_targetEvent', $srv);
        self::assertStringNotContainsString('CBA_fnc_globalEvent', $srv);
        self::assertMatchesRegularExpression('/private _send = \{\s*params \["_u", "_a", \["_d", \[\]\]\];\s*'
            . 'if \(isNull _u\) exitWith \{\};\s*'
            . 'if \(local _u\) exitWith \{ \[\{ _this call comspec_atak_native_fnc_debrief; \}, \[_a, _d\]\] call CBA_fnc_execNextFrame; \};\s*'
            . '\[_a, _d\] remoteExecCall \["comspec_atak_native_fnc_debrief", _u\];\s*\};/', $srv);
        // Chaque message nomme en toutes lettres une action de fn_debrief, et la seule qui répond au panneau est « note ».
        $client = $this->stripSqfComments($this->read(self::MAIN . '/functions/debrief/fn_debrief.sqf'));
        preg_match_all('/^    case "(\w+)": \{/m', $client, $cases);
        $acts = [];
        foreach ($this->callsOf($srv, '_send') as [$args]) {
            $act = $args === null ? null : $this->literal((string) ($args[1] ?? ''));
            self::assertNotNull($act, 'message du serveur sans action littérale');
            self::assertContains($act, $cases[1], $act);
            $acts[$act] = true;
        }
        $acts = array_keys($acts);
        sort($acts);
        self::assertSame(['forced', 'lift', 'note', 'recv', 'track'], $acts);
        self::assertMatchesRegularExpression('/case "note": \{\s*switch \(_arg\) do \{\s*'
            . 'case "none": \{ \["Aucun joueur visé\.", 3\] call comspec_atak_native_fnc_toast; \};\s*'
            . 'case "denied": \{ \["Vous ne pouvez pas imposer le CR\.", 3\] call comspec_atak_native_fnc_toast; \};\s*\};\s*\};/u', $client);
        $en = $this->read(self::MAIN . '/i18n/en.txt');
        foreach (['Aucun joueur visé.', 'Vous ne pouvez pas imposer le CR.'] as $fr) {
            self::assertStringContainsString('["' . $fr . '", "', $en, $fr);
        }
        // Seul fn_debriefServer appelle fn_debrief à distance, avec deux arguments (rien dans la file JIP) ; liste blanche :
        // clients seuls, sans file JIP.
        $calls = [];
        foreach ($this->sources() as $rel => $units) {
            foreach ($units as $code) {
                foreach ($this->remoteCalls($code) as [, $right]) {
                    if ($right !== null && strtolower((string) $this->literal($right[0])) === 'comspec_atak_native_fnc_debrief') {
                        $calls[] = [$rel, count($right)];
                    }
                }
            }
        }
        self::assertSame([[self::MAIN . '/functions/debrief/fn_debriefServer.sqf', 2]], $calls);
        self::assertStringContainsString('class comspec_atak_native_fnc_debrief { allowedTargets = 1; jip = 0; };', $this->remoteExecFunctions(self::MAIN . '/config.cpp'));
    }

    public function testAdminAppDataReachesThePhoneOnlyFromTheServer(): void
    {
        // Lot mpauth3 (SEC-015, N12) : le gestionnaire CBA comspec_atak_native_adminData tournait chez chaque joueur sans
        // savoir qui avait levé l'événement ; tout client envoyait à un admin (ou à tous, rejoué à chaque arrivant par
        // CBA_fnc_globalEventJIP) une fausse notification « ADMIN », de fausses fiches ou un faux débogage. Réponses du
        // serveur et fiches des joueurs arrivent maintenant par remoteExecCall du serveur à fn_adminClient « data », qui
        // refuse tout autre émetteur ; le serveur met dans chaque fiche l'UID et le nom du poste émetteur et ne la relaie
        // qu'à un admin dont le relevé est en cours. L'admin local au serveur (hôte) est servi à l'image suivante.
        $srv = $this->stripSqfComments($this->read(self::MAIN . '/functions/admin/fn_adminServer.sqf'));
        self::assertStringNotContainsString('CBA_fnc_targetEvent', $srv);
        self::assertMatchesRegularExpression('/private _replyTo = \{\s*params \["_u", "_kind", "_data"\];\s*'
            . 'if \(isNull _u\) exitWith \{\};\s*'
            . 'if \(local _u\) exitWith \{ \[\{ _this call comspec_atak_native_fnc_adminClient; \}, \["data", \[_kind, _data\]\]\] call CBA_fnc_execNextFrame; \};\s*'
            . '\["data", \[_kind, _data\]\] remoteExecCall \["comspec_atak_native_fnc_adminClient", _u\];\s*\};/', $srv);
        self::assertStringContainsString('private _reply = { params ["_kind", "_data"]; [_caller, _kind, _data] call _replyTo; };', $srv);
        self::assertStringContainsString('[_caller, "denied", []] call _replyTo;', $srv);
        // « row » : jugée avant le droit d'admin (tout joueur répond au relevé), pour la fiche de son poste seulement.
        $row = $this->between($srv, 'if (_act isEqualTo "row") exitWith {', 'if (_remote && {!([_caller] call comspec_atak_native_fnc_adminAllowed)}) exitWith {');
        $order = [
            '_args params [["_to", objNull], ["_row", []]];',
            'if (isNull _caller || {!(_to isEqualType objNull)} || {isNull _to} || {!(_row isEqualType [])} || {(count _row) > 30} || {(count str _row) > 8000}) exitWith { false };',
            'if (((localNamespace getVariable ["COMSPEC_ATAK_AdminScans", []]) findIf { (_x select 0) isEqualTo _to && {(_t - (_x select 1)) < 15} }) < 0) exitWith { false };',
            'if ((_t - (_rowAt getOrDefault [_k, -100])) < 2) exitWith { false };',
            '_row set [0, getPlayerUID _caller];',
            '_row set [1, name _caller];',
            '[_to, "player", _row] call _replyTo;',
        ];
        $at = 0;
        foreach ($order as $step) {
            $i = strpos($row, $step, $at);
            self::assertNotFalse($i, $step);
            $at = (int) $i + strlen($step);
        }
        // Le relevé en cours est noté par « scan » seul, après le droit et la cadence ; l'hôte est servi sans appel distant.
        $scan = $this->between($srv, 'case "scan": {', 'case "capture": {');
        self::assertSame(1, substr_count($srv, 'setVariable ["COMSPEC_ATAK_AdminScans"'));
        self::assertStringContainsString('localNamespace setVariable ["COMSPEC_ATAK_AdminScans", ', $scan);
        self::assertStringContainsString('private _far = _to select { !local _x };', $scan);
        self::assertStringContainsString('if (_far isNotEqualTo []) then { ["report", [_caller]] remoteExecCall ["comspec_atak_native_fnc_adminClient", _far]; };', $scan);
        self::assertStringContainsString('if ((_to findIf { local _x }) >= 0) then { [{ _this call comspec_atak_native_fnc_adminClient; }, ["report", [_caller]]] call CBA_fnc_execNextFrame; };', $scan);
        // Le joueur envoie sa fiche au serveur, plus à l'admin.
        $client = $this->stripSqfComments($this->read(self::MAIN . '/functions/admin/fn_adminClient.sqf'));
        self::assertStringNotContainsString('CBA_fnc_targetEvent', $client);
        self::assertStringContainsString('["row", [_to, _row]] remoteExecCall ["comspec_atak_native_fnc_adminServer", 2];', $this->between($client, 'case "report": {', 'case "data": {'));
        // fn_adminClient n'est appelée à distance que par fn_adminServer, avec deux arguments (rien dans la file JIP).
        $calls = [];
        foreach ($this->sources() as $rel => $units) {
            foreach ($units as $code) {
                foreach ($this->remoteCalls($code) as [, $right]) {
                    if ($right !== null && strtolower((string) $this->literal($right[0])) === 'comspec_atak_native_fnc_adminclient') {
                        $calls[$rel][] = count($right);
                    }
                }
            }
        }
        self::assertSame([self::MAIN . '/functions/admin/fn_adminServer.sqf' => [2, 2]], $calls);
    }

    public function testDockRequestsAreJudgedOnTheRequesterAndTheServerProfileIsBounded(): void
    {
        // Lot mpauth3 (SEC-025, MP2-05) : les événements CBA comspec_atak_native_dockPut, _dockTake, _dockDeploy et
        // _dockPack prenaient l'unité dans les arguments. Tout client posait ou reprenait un téléphone au nom d'un autre et
        // posait sans fin des socles près de n'importe qui, gardés dans le profil du serveur (COMSPEC_ATAK_DockDb_v1) et
        // recréés à chaque partie ; comspec_atak_native_dockResult faisait rendre un objet, une charge ou un état
        // d'appareil au téléphone d'un autre. Les quatre demandes passent par remoteExecCall vers fn_dockServer, jugées sur
        // le demandeur que retrouve le serveur ; la réponse revient par remoteExecCall vers fn_dockClient, qui n'accepte
        // que le serveur. Socles du kit plafonnés par joueur, profil borné et écrit au plus toutes les 5 s.
        $srv = $this->stripSqfComments($this->read(self::MAIN . '/functions/dock/fn_dockServer.sqf'));
        self::assertMatchesRegularExpression('/^params \[\["_act", "init"\], \["_a", \[\]\]\];\s*'
            . 'if \(!isServer\) exitWith \{\};\s*'
            . 'if \(isRemoteExecutedJIP\) exitWith \{[^{}]*\};\s*'
            . 'private _remote = isMultiplayer && \{isRemoteExecuted\} && \{remoteExecutedOwner isNotEqualTo 2\};\s*'
            . 'if \(_remote && \{!\(_act in \["put", "take", "deploy", "pack"\]\)\}\) exitWith \{[^{}]*\};\s*'
            . 'private _req = \[objNull, player\] select hasInterface;\s*'
            . 'if \(_remote\) then \{ _req = \[remoteExecutedOwner\] call comspec_atak_native_fnc_reqUnit; \};\s*'
            . 'if \(_remote && \{isNull _req\}\) exitWith \{[^{}]*\};\s*'
            . 'if !\(_a isEqualType \[\]\) exitWith \{\};/', trim($srv));
        $this->assertRequesterRulesArePinned();
        $files = $this->sources();
        $lists = $this->remoteLists($files);
        self::assertSame(['put', 'take', 'deploy', 'pack'], $lists['comspec_atak_native_fnc_dockserver'] ?? null);
        // Les quatre demandes partent d'un client, jamais d'une branche du serveur seul (contre-expertise mpauth3 v1, O13 :
        // « take » placée derrière « if (!isServer) exitWith » ne partirait plus d'un client).
        [$sent, , $orders] = $this->actionsSent($files, $lists);
        $got = array_keys($sent['comspec_atak_native_fnc_dockserver'] ?? []);
        sort($got);
        self::assertSame(['deploy', 'pack', 'put', 'take'], $got);
        self::assertArrayNotHasKey('comspec_atak_native_fnc_dockserver', $orders);
        // Aucune unité reçue : le demandeur est _req partout. Plus d'événement CBA, ni d'appel imbriqué de la fonction
        // hors des gestionnaires du serveur posés par « init » (dans une demande, l'appel imbriqué garderait le contexte de
        // l'appel distant et la garde le refuserait).
        self::assertStringNotContainsString('_unit', $srv);
        self::assertStringNotContainsString('CBA_fnc_targetEvent', $srv);
        self::assertStringNotContainsString('CBA_fnc_addEventHandler', $srv);
        self::assertSame(5, substr_count($srv, 'call comspec_atak_native_fnc_dockServer'));
        self::assertSame(5, substr_count($this->between($srv, 'case "init": {', 'case "id": {'), 'call comspec_atak_native_fnc_dockServer'));
        $steps = [
            'put' => [$this->between($srv, 'case "put": {', 'case "take": {'), [
                '_a params [["_dock", objNull], ["_slot", -1], ["_item", ""], ["_level", 100], ["_device", []], ["_tint", "coyote"]];',
                'if (isNull _req || {!(_item isEqualType "")} || {_item isEqualTo ""}) exitWith {};',
                'if (!(_dock isEqualType objNull) || {isNull _dock} || {!(_dock isKindOf _class)}) exitWith { [_req, "put", false, "Socle replié", [_item]] call _reply; };',
                'if !((toLower _item) in ([] call comspec_atak_native_fnc_deviceCatalog)) exitWith { [_req, "put", false, "Pas de téléphone sur vous", [_item]] call _reply; };',
                'if (!(_slot isEqualType 0) || {!(_level isEqualType 0)}) exitWith { [_req, "put", false, "Emplacement occupé ou socle plein", [_item]] call _reply; };',
                'if (!(_device isEqualType []) || {(count _device) > 64} || {(count str _device) > 8000}) then { _device = []; };',
                'if !(_tint in ["coyote", "od", "black"]) then { _tint = "coyote"; };',
                'private _id = [_dock] call _idOf;',
                'if ((_req distance _dock) > 6) exitWith { [_req, "put", false, "Trop loin du socle", [_item]] call _reply; };',
                '_slots set [_slot, [getPlayerUID _req, name _req, _item, (_level max 0) min 100, _device, _tint, serverTime]];',
                '[false] call _save;',
            ]],
            'take' => [$this->between($srv, 'case "take": {', 'case "zeusKit": {'), [
                '_a params [["_dock", objNull], ["_slot", -1]];',
                'if (isNull _req || {!(_dock isEqualType objNull)} || {isNull _dock} || {!(_dock isKindOf _class)} || {!(_slot isEqualType 0)}) exitWith {};',
                'private _id = [_dock] call _idOf;',
                'private _mine = (_s select 0) isEqualTo getPlayerUID _req;',
                'private _staff = (admin (owner _req)) > 0 || {!isNull (getAssignedCuratorLogic _req)};',
                'if ((_req distance _dock) > 6) exitWith { [_req, "take", false, "Trop loin du socle"] call _reply; };',
                '[false] call _save;',
            ]],
            'deploy' => [$this->between($srv, 'case "deploy": {', 'case "pack": {'), [
                '_a params [["_pos", []], ["_dir", 0]];',
                'if (isNull _req || {!(_pos isEqualType [])} || {!(_pos isEqualTypeArray [0, 0, 0])} || {!(_dir isEqualType 0)} || {((getPosASL _req) distance _pos) > 4}) exitWith { [_req, "deploy", false, "Place invalide"] call _reply; };',
                'private _uid = getPlayerUID _req;',
                'if (({ private _pl = _x select 1; (count _pl) > 4 && {(_pl select 4) isEqualTo _uid} } count (values _db)) >= _kitMax) exitWith {',
                '[_req, "deploy", false, format ["Déjà %1 socles posés à votre nom sur ce serveur. Repliez-en un d\'abord.", _kitMax]] call _reply;',
                'private _o = createVehicle [_class, ASLToATL _pos, [], 0, "CAN_COLLIDE"];',
                'private _id = format ["kit|%1|%2|%3", _uid, (call _utc), floor random 1e5];',
                '[_o, _id, true] call _register;',
                '_db set [_id, [call _empty, [worldName, getPosASL _o, vectorDir _o, vectorUp _o, _uid], call _utc]];',
                '[false] call _save;',
            ]],
            'pack' => [$this->between($srv, 'case "pack": {', 'default {};'), [
                '_a params [["_dock", objNull]];',
                'if (isNull _req || {!(_dock isEqualType objNull)} || {isNull _dock} || {!(_dock isKindOf _class)}) exitWith {};',
                'private _id = [_dock] call _idOf;',
                'if !([_dock] call _kitOf) exitWith { [_req, "pack", false, "Ce socle fait partie de la mission"] call _reply; };',
                'if ((_req distance _dock) > 6) exitWith { [_req, "pack", false, "Trop loin du socle"] call _reply; };',
                '_db deleteAt _id;',
                '[false] call _save;',
            ]],
        ];
        foreach ($steps as $act => [$case, $order]) {
            $at = 0;
            foreach ($order as $step) {
                $i = strpos($case, $step, $at);
                self::assertNotFalse($i, "$act : $step");
                $at = (int) $i + strlen($step);
            }
        }
        // « put », « take » et « pack » entières (contre-expertise mpauth3 v1, O3, O4, O5) : emplacement libre, téléphone
        // du demandeur (sauf admin, Zeus ou réglage), socle vide avant d'être replié.
        $this->assertBlocks($srv, [
            'case "put": {' => [
                'case "put": {',
                '_a params [["_dock", objNull], ["_slot", -1], ["_item", ""], ["_level", 100], ["_device", []], ["_tint", "coyote"]];',
                'if (isNull _req || {!(_item isEqualType "")} || {_item isEqualTo ""}) exitWith {};',
                'if (!(_dock isEqualType objNull) || {isNull _dock} || {!(_dock isKindOf _class)}) exitWith { [_req, "put", false, "Socle replié", [_item]] call _reply; };',
                'if !((toLower _item) in ([] call comspec_atak_native_fnc_deviceCatalog)) exitWith { [_req, "put", false, "Pas de téléphone sur vous", [_item]] call _reply; };',
                'if (!(_slot isEqualType 0) || {!(_level isEqualType 0)}) exitWith { [_req, "put", false, "Emplacement occupé ou socle plein", [_item]] call _reply; };',
                'if (!(_device isEqualType []) || {(count _device) > 64} || {(count str _device) > 8000}) then { _device = []; };',
                'if !(_tint in ["coyote", "od", "black"]) then { _tint = "coyote"; };',
                'private _id = [_dock] call _idOf;',
                'private _slots = (_db get _id) select 0;',
                '_slot = floor _slot;',
                'if (_slot < 0) then { _slot = _slots findIf { (count _x) == 0 }; };',
                'if (_slot < 0 || {_slot > 9} || {(count (_slots select _slot)) > 0}) exitWith {',
                '[_req, "put", false, "Emplacement occupé ou socle plein", [_item]] call _reply;',
                '};',
                'if ((_req distance _dock) > 6) exitWith { [_req, "put", false, "Trop loin du socle", [_item]] call _reply; };',
                '_slots set [_slot, [getPlayerUID _req, name _req, _item, (_level max 0) min 100, _device, _tint, serverTime]];',
                '[_dock, _slots] call _publish;',
                '[false] call _save;',
                '[_req, "put", true, format ["Téléphone posé sur l\'emplacement %1 (%2 %%)", _slot + 1, floor _level], [_slot]] call _reply;',
                '}',
            ],
            'case "take": {' => [
                'case "take": {',
                '_a params [["_dock", objNull], ["_slot", -1]];',
                'if (isNull _req || {!(_dock isEqualType objNull)} || {isNull _dock} || {!(_dock isKindOf _class)} || {!(_slot isEqualType 0)}) exitWith {};',
                'private _id = [_dock] call _idOf;',
                'private _slots = (_db get _id) select 0;',
                '_slot = floor _slot;',
                'if (_slot < 0 || {_slot > 9} || {(count (_slots select _slot)) == 0}) exitWith { [_req, "take", false, "Emplacement vide"] call _reply; };',
                'private _s = _slots select _slot;',
                'private _mine = (_s select 0) isEqualTo getPlayerUID _req;',
                'private _staff = (admin (owner _req)) > 0 || {!isNull (getAssignedCuratorLogic _req)};',
                'if (!_mine && {!_staff} && {!(missionNamespace getVariable ["comspec_atak_native_dock_anyone", false])}) exitWith {',
                '[_req, "take", false, format ["Ce téléphone appartient à %1", _s select 1]] call _reply;',
                '};',
                'if ((_req distance _dock) > 6) exitWith { [_req, "take", false, "Trop loin du socle"] call _reply; };',
                '_slots set [_slot, []];',
                '[_dock, _slots] call _publish;',
                '[false] call _save;',
                '[_req, "take", true, format ["Téléphone repris (emplacement %1, %2 %%)%3", _slot + 1, floor (_s select 3), ["", format [" · appartient à %1", _s select 1]] select !_mine],',
                '[_s select 2, _s select 3, _s select 4, _mine]] call _reply;',
                '}',
            ],
            'case "pack": {' => [
                'case "pack": {',
                '_a params [["_dock", objNull]];',
                'if (isNull _req || {!(_dock isEqualType objNull)} || {isNull _dock} || {!(_dock isKindOf _class)}) exitWith {};',
                'private _id = [_dock] call _idOf;',
                'if !([_dock] call _kitOf) exitWith { [_req, "pack", false, "Ce socle fait partie de la mission"] call _reply; };',
                'if ((((_db get _id) select 0) findIf { (count _x) > 0 }) >= 0) exitWith { [_req, "pack", false, "Retirez d\'abord les téléphones"] call _reply; };',
                'if ((_req distance _dock) > 6) exitWith { [_req, "pack", false, "Trop loin du socle"] call _reply; };',
                '_db deleteAt _id;',
                'deleteVehicle _dock;',
                '[false] call _save;',
                '[_req, "pack", true, "Socle replié"] call _reply;',
                '}',
            ],
        ], 'fn_dockServer');
        // Identifiant et genre (kit ou mission) d'un socle : registre du serveur, rempli à la création ou à la première vue
        // (dès le lancement pour les socles de la mission) ; les variables publiques ne sont plus qu'écrites, pour les menus.
        self::assertStringContainsString('{ [_x] call _idOf; } forEach (allMissionObjects _class);', $this->between($srv, 'case "init": {', 'case "id": {'));
        self::assertSame(1, substr_count($srv, 'getVariable ["COMSPEC_ATAK_DockId"'));
        self::assertStringContainsString('if (!(_id isEqualType "") || {_id isEqualTo ""} || {(count _id) > 100}) then {', $srv);
        self::assertStringNotContainsString('getVariable ["COMSPEC_ATAK_DockKit"', $srv);
        self::assertSame(1, substr_count($srv, 'setVariable ["COMSPEC_ATAK_DockKit"'));
        self::assertSame(1, substr_count($srv, 'setVariable ["COMSPEC_ATAK_DockId"'));
        self::assertStringContainsString('if ([_dock] call _kitOf) then {', $this->between($srv, 'case "tick": {', 'case "publish": {'));
        // État du serveur hors de portée des clients (localNamespace) ; profil borné, relu avec prudence, écrit au plus
        // toutes les 5 s (une demande de joueur écrit le profil du serveur : saveProfileNamespace réécrit tout le profil).
        self::assertSame(0, preg_match('/missionNamespace (?:get|set)Variable \["COMSPEC_ATAK_Dock/', $srv));
        self::assertStringContainsString('private _kitMax = 10;', $srv);
        self::assertStringContainsString('private _dbMax = 300;', $srv);
        self::assertStringContainsString('if ((count _out) > _dbMax) then { _out = ([_out, [], { _x select 3 }, "DESCEND"] call BIS_fnc_sortBy) select [0, _dbMax]; };', $srv);
        self::assertStringContainsString('_rows = ([_rows select { _x isEqualType [] && {(_x param [3, ""]) isEqualType 0} }, [], { _x select 3 }, "DESCEND"] call BIS_fnc_sortBy) select [0, _dbMax];', $srv);
        self::assertStringContainsString('_slots = _slots apply { [[], _x] select (_x isEqualType [] && {_x isEqualTypeArray ["", "", "", 0, [], "", 0]}) };', $srv);
        self::assertStringContainsString('if (!_force && {(serverTime - (localNamespace getVariable ["COMSPEC_ATAK_DockSaved", -1e9])) < 5}) exitWith { localNamespace setVariable ["COMSPEC_ATAK_DockLater", true]; };', $srv);
        self::assertStringContainsString('case "save": { [true] call _save; };', $srv);
        self::assertSame(substr_count($srv, 'call _save'), substr_count($srv, '[false] call _save') + substr_count($srv, '[true] call _save'));
        // Réponse au demandeur seul, par la voie gardée ; l'hôte d'une partie locale est servi à l'image suivante.
        self::assertMatchesRegularExpression('/private _reply = \{\s*params \["_u", "_what", "_ok", "_msg", \["_data", \[\]\]\];\s*'
            . 'if \(isNull _u\) exitWith \{\};\s*'
            . 'if \(local _u\) exitWith \{ \[\{ _this call comspec_atak_native_fnc_dockClient; \}, \["result", \[_what, _ok, _msg, _data\]\]\] call CBA_fnc_execNextFrame; \};\s*'
            . '\["result", \[_what, _ok, _msg, _data\]\] remoteExecCall \["comspec_atak_native_fnc_dockClient", _u\];\s*\};/', $srv);
        // fn_dockClient : seul le serveur l'appelle à distance (garde en tête, juste après params) ; plus d'événement CBA.
        $client = $this->stripSqfComments($this->read(self::MAIN . '/functions/dock/fn_dockClient.sqf'));
        self::assertMatchesRegularExpression('/^params \[\["_act", "phone"\], \["_a", \[\]\]\];\s*'
            . preg_quote('if (isRemoteExecutedJIP || {isRemoteExecuted && {remoteExecutedOwner isNotEqualTo 2}}) exitWith {', '/')
            . '\s*diag_log format \["\[COMSPEC ATAK NATIVE\]\[WARN\]\[DOCK\] [^"]*", _act, remoteExecutedOwner, isRemoteExecutedJIP\];\s*false\s*\};/', trim($client));
        self::assertStringNotContainsString('CBA_fnc_serverEvent', $client);
        self::assertStringNotContainsString('CBA_fnc_addEventHandler', $this->stripSqfComments($this->read(self::MAIN . '/functions/dock/fn_dockMenu.sqf')));
        // Appels distants : demandes de fn_dockClient au serveur, réponse de fn_dockServer au demandeur ; deux arguments
        // chacun (rien dans la file JIP), sans unité dans la demande.
        $calls = [];
        foreach ($this->sources() as $rel => $units) {
            foreach ($units as $code) {
                foreach ($this->remoteCalls($code) as [$left, $right]) {
                    $fn = strtolower((string) ($right === null ? '' : $this->literal($right[0])));
                    if (in_array($fn, ['comspec_atak_native_fnc_dockserver', 'comspec_atak_native_fnc_dockclient'], true)) {
                        $calls[] = [basename($rel), $this->literal(trim($left[0] ?? '')), trim($right[1] ?? ''), count($right)];
                    }
                }
            }
        }
        self::assertSame([
            ['fn_dockClient.sqf', 'put', '2', 2],
            ['fn_dockClient.sqf', 'take', '2', 2],
            ['fn_dockClient.sqf', 'deploy', '2', 2],
            ['fn_dockClient.sqf', 'pack', '2', 2],
            ['fn_dockServer.sqf', 'result', '_u', 2],
        ], $calls);
        self::assertStringContainsString('["put", [_dock, _slot, _item, _lv, ', $client);
        self::assertStringNotContainsString('player', $this->between($client, 'case "take": {', 'case "status": {'));
        self::assertStringContainsString('["deploy", [_pos, (_dir + 180) % 360]] remoteExecCall ["comspec_atak_native_fnc_dockServer", 2];', $client);
        self::assertStringContainsString('["pack", [_dock]] remoteExecCall ["comspec_atak_native_fnc_dockServer", 2];', $client);
        $cfg = $this->remoteExecFunctions(self::MAIN . '/config.cpp');
        self::assertStringContainsString('class comspec_atak_native_fnc_dockServer { allowedTargets = 2; };', $cfg);
        self::assertStringContainsString('class comspec_atak_native_fnc_dockClient { allowedTargets = 1; jip = 0; };', $cfg);
        self::assertStringContainsString('["Déjà %1 socles posés à votre nom sur ce serveur. Repliez-en un d\'abord.", "', $this->read(self::MAIN . '/i18n/en.txt'));
    }

    public function testDelayedCleanupDeletesOnlyTheRequesterObjectsWithinBounds(): void
    {
        // Lot mpauth3 (SEC-024, MP2-04) : le gestionnaire CBA comspec_atak_native_deleteLater (XEH_postInitServer)
        // supprimait plus tard n'importe quel objet de la partie (véhicule, bâtiment, unité d'un autre joueur), au délai
        // choisi par l'émetteur, même après son départ. La demande passe par remoteExecCall vers fn_deleteLater, sur le
        // serveur : objets du poste qui demande seulement (créés par lui, encore locaux chez lui), jamais une unité, 4 par
        // demande, 20 en attente par poste, délai de 10 min à 2 h, et rien qui a un équipage au moment de supprimer.
        $f = $this->stripSqfComments($this->read(self::MAIN . '/functions/core/fn_deleteLater.sqf'));
        self::assertMatchesRegularExpression('/^params \[\["_objs", \[\]\], \["_delay", 1800\]\];\s*'
            . 'if \(!isServer\) exitWith \{\};\s*'
            . 'if \(isRemoteExecutedJIP\) exitWith \{[^{}]*\};\s*'
            . 'private _remote = isMultiplayer && \{isRemoteExecuted\} && \{remoteExecutedOwner isNotEqualTo 2\};\s*'
            . 'if \(!\(_objs isEqualType \[\]\) \|\| \{!\(_delay isEqualType 0\)\}\) exitWith \{\};\s*'
            . 'private _who = \[2, remoteExecutedOwner\] select _remote;/', trim($f));
        $order = [
            '_objs = (_objs select { _x isEqualType objNull && {!isNull _x} && {!(_x isKindOf "CAManBase")} && {!_remote || {(owner _x) isEqualTo _who}} }) select [0, 4];',
            'private _q = localNamespace getVariable ["COMSPEC_ATAK_DeleteLater", createHashMap];',
            'private _due = (_q getOrDefault [_who, []]) select { _x > _now };',
            '_objs = _objs select [0, (20 - (count _due)) max 0];',
            'if (_objs isEqualTo []) exitWith {',
            '_delay = (_delay max 600) min 7200;',
            '{ _due pushBack (_now + _delay); } forEach _objs;',
            '_q set [_who, _due];',
            '[{ { if (!isNull _x && {(crew _x) isEqualTo []}) then { deleteVehicle _x; }; } forEach _this; }, _objs, _delay] call CBA_fnc_waitAndExecute;',
        ];
        $at = 0;
        foreach ($order as $step) {
            $i = strpos($f, $step, $at);
            self::assertNotFalse($i, $step);
            $at = (int) $i + strlen($step);
        }
        self::assertSame(1, substr_count($f, 'deleteVehicle'));
        // Deux émetteurs connus (caisse UberEats, caisse et jerrican de la logistique), vers le serveur, deux arguments
        // (rien dans la file JIP) ; plus de gestionnaire CBA (invariant des événements retirés).
        $calls = [];
        foreach ($this->sources() as $rel => $units) {
            foreach ($units as $code) {
                foreach ($this->remoteCalls($code) as [$left, $right]) {
                    if ($right !== null && strtolower((string) $this->literal($right[0])) === 'comspec_atak_native_fnc_deletelater') {
                        $calls[] = [basename($rel), trim($right[1] ?? ''), count($right), count((array) $left)];
                    }
                }
            }
        }
        self::assertSame([['fn_foodAction.sqf', '2', 2, 2], ['fn_logisticsAction.sqf', '2', 2, 2]], $calls);
        $cfg = $this->cppCode($this->read(self::MAIN . '/config.cpp'));
        self::assertStringContainsString('class saveProfile {}; class localCfg {}; class deleteLater {};', $cfg);
        self::assertStringContainsString('class comspec_atak_native_fnc_deleteLater { allowedTargets = 2; };', $this->remoteExecFunctions(self::MAIN . '/config.cpp'));
    }

    public function testZeusRelayModulesAreDeclaredAndAcceptOnlyTheServerOrTheModuleOwner(): void
    {
        // Lot mpauth3 (DEF-001, contre-expertise v5, § 4.3) : les modules Zeus « Scanner les relais » et « Resync relais »
        // (connect, isGlobal = 0) tournent sur le serveur, qui, dédié, relaie l'appel au poste du Zeus qui a posé le
        // module (remoteExec vers le propriétaire de la logique). Sans classe CfgRemoteExec, ce relais était refusé en
        // silence en mode 1. Les deux fonctions y entrent (cible : un client, jamais par la file JIP), avec une garde en
        // tête, avant le relais : d'un appel distant, seulement le serveur ou, sur le serveur, le poste de la logique.
        $cfg = $this->remoteExecFunctions(self::CONNECT . '/config.cpp');
        foreach (['zeusModuleScanRelays', 'zeusResyncRelay'] as $fn) {
            $code = $this->stripSqfComments($this->read(self::CONNECT . '/functions/fn_' . $fn . '.sqf'));
            self::assertMatchesRegularExpression('/^private _lgReq = _this param \[0, objNull\];\s*'
                . 'if !\(_lgReq isEqualType objNull\) then \{ _lgReq = objNull; \};\s*'
                . 'if \(isRemoteExecutedJIP \|\| \{isRemoteExecuted && \{!\(remoteExecutedOwner in \[2, owner _lgReq\]\)\}\}\) exitWith \{\s*'
                . 'diag_log format \["\[COMSPEC ATAK\]\[WARN\]\[ZEUS\] [^"]*", remoteExecutedOwner, isRemoteExecutedJIP\];\s*\};\s*'
                . 'if \(!hasInterface\) exitWith \{\s*private _lg = _this param \[0, objNull\];/', trim($code), $fn);
            self::assertStringContainsString('class comspec_overwatch_connect_fnc_' . $fn . ' { allowedTargets = 1; jip = 0; };', $cfg, $fn);
        }
        // Seuls appels distants de ces fonctions : le relais du serveur vers le poste du Zeus, deux éléments à droite
        // (rien dans la file JIP).
        $calls = [];
        foreach ($this->sources() as $rel => $units) {
            foreach ($units as $code) {
                foreach ($this->remoteCalls($code) as [, $right]) {
                    $fn = $right === null ? null : $this->literal($right[0]);
                    if ($fn !== null && in_array(strtolower($fn), ['comspec_overwatch_connect_fnc_zeusmodulescanrelays', 'comspec_overwatch_connect_fnc_zeusresyncrelay'], true)) {
                        $calls[] = [basename($rel), $fn, trim($right[1] ?? ''), count($right)];
                    }
                }
            }
        }
        sort($calls);
        self::assertSame([
            ['fn_zeusModuleScanRelays.sqf', 'comspec_overwatch_connect_fnc_zeusModuleScanRelays', 'owner _lg', 2],
            ['fn_zeusResyncRelay.sqf', 'comspec_overwatch_connect_fnc_zeusResyncRelay', 'owner _lg', 2],
        ], $calls);
    }

    public function testTocScreenDocumentationSaysWhoMayCallItFromAClient(): void
    {
        // Lot mpauth3 (B6, contre-expertise v5) : MODULES.md § 7 présentait « add », « remove » et « spawn » comme
        // appelables de partout (« Ailleurs, la demande part au serveur »). Depuis le lot mpauth, le serveur refuse la
        // demande d'un client qui n'est ni Zeus, ni hôte, ni admin, ni UID autorisé, avec une seule ligne WARN dans son
        // journal ; « view » est admis en plus d'un joueur du camp de l'écran, à moins de 25 m. Le texte dit ce contrat,
        // en français et en anglais.
        $doc = $this->read('mod/COMSPEC_ATAK_Native/MODULES.md');
        $toc = $this->between($doc, "\n## 7. ", "\n## 8. ");
        self::assertNotEmpty($toc);
        self::assertStringNotContainsString('Ailleurs, la demande part au serveur.', $toc);
        foreach ([
            '« add », « remove », « spawn » et « view » partent au serveur',
            'qui juge le joueur de ce poste : poser, désigner ou retirer un écran, seulement pour un Zeus, l\'hôte,',
            'en plus, pour un joueur du camp de l\'écran à moins de 25 m. Une demande refusée ne fait rien',
            'et laisse une seule ligne WARN dans le journal du serveur',
            'appelle donc l\'écran sur le serveur seulement (`if (isServer)`)',
            '`add`, `remove`, `spawn` and `view` are sent to the server, which judges the player of that machine',
            'accepted only from a Zeus, the host, a logged-in admin or a UID of the "In-game',
            'changing the display (`view`) also from a player of the screen\'s side within 25 m.',
            'A refused request does nothing and leaves a single WARN line in the server log',
        ] as $line) {
            self::assertStringContainsString($line, preg_replace('/\s*\n\s*/', ' ', $toc) ?? '', $line);
        }
        // Le réglage cité existe sous ce nom (texte français et clé anglaise).
        self::assertStringContainsString('["Gestionnaires en jeu (UID Steam)", "UID Steam (64 bits)', $this->read(self::MAIN . '/XEH_preInit.sqf'));
        self::assertStringContainsString('["Gestionnaires en jeu (UID Steam)", "In-game managers (Steam UID)"],', $this->read(self::MAIN . '/i18n/en.txt'));
    }

    /**
     * fn_reqUnit (qui a envoyé l'appel distant) et fn_adminAllowed (qui est gestionnaire), lus en entier, code sans
     * commentaires : les gardes du lot jugent le joueur que rend la première, avec la règle de la seconde. Une règle
     * changée en silence change le jugement de toutes ces gardes (contre-expertise mpauth3 v1, R3 : O11, O12) ; un
     * changement voulu passe par ce test.
     */
    private function assertRequesterRulesArePinned(): void
    {
        self::assertSame(implode(' ', [
            'params [["_owner", 0, [0]]];',
            'if (!isServer) exitWith { objNull };',
            'if (!isMultiplayer) exitWith { player };',
            'if (_owner <= 0 || {_owner isEqualTo 2}) exitWith { objNull };',
            'if (hasInterface && {_owner isEqualTo clientOwner}) exitWith { player };',
            '(allPlayers select { (owner _x) isEqualTo _owner }) param [0, objNull]',
        ]), $this->squash($this->stripSqfComments($this->read(self::MAIN . '/functions/network/fn_reqUnit.sqf'))), 'fn_reqUnit');
        self::assertSame(implode(' ', [
            'params [["_u", objNull]];',
            'if (isNull _u || {!isPlayer _u && {_u isNotEqualTo player}}) exitWith { false };',
            'if (!isNull (getAssignedCuratorLogic _u)) exitWith { true };',
            'if (isServer && {hasInterface} && {_u isEqualTo player}) exitWith { true };',
            'if (isServer && {(admin (owner _u)) > 0}) exitWith { true };',
            'if (!isServer && {_u isEqualTo player} && {serverCommandAvailable "#kick"}) exitWith { true };',
            'private _uids = missionNamespace getVariable ["comspec_atak_native_admin_uids", ""];',
            'if !(_uids isEqualType "") exitWith { false };',
            '(getPlayerUID _u) in ((_uids splitString ", ;") apply { trim _x })',
        ]), $this->squash($this->stripSqfComments($this->read(self::MAIN . '/functions/admin/fn_adminAllowed.sqf'))), 'fn_adminAllowed');
    }

    /** Chaque bloc nommé par son en-tête (block) vaut exactement ses lignes, jointes par une espace. */
    private function assertBlocks(string $code, array $blocks, string $what): void
    {
        foreach ($blocks as $head => $lines) {
            self::assertSame(implode(' ', $lines), $this->block($code, $head), "$what : $head");
        }
    }

    /** $s aux blancs (retours à la ligne compris) ramenés à une espace, sans blanc aux deux bouts. */
    private function squash(string $s): string
    {
        return trim((string) preg_replace('/\s+/', ' ', $s));
    }

    /**
     * Bloc d'un code SQF sans commentaires, de son en-tête $head (qui finit par « { » et n'y paraît qu'une fois) à
     * l'accolade qui le ferme, blancs réduits (squash) ; vide si l'en-tête manque, se répète ou ne se ferme pas.
     */
    private function block(string $code, string $head): string
    {
        $at = strpos($code, $head);
        if ($at === false || !str_ends_with($head, '{') || substr_count($code, $head) !== 1) {
            return '';
        }
        $close = $this->pairsOf($this->blankStrings($code), '{', '}')[$at + strlen($head) - 1] ?? null;
        return $close === null ? '' : $this->squash(substr($code, $at, $close - $at + 1));
    }

    /**
     * Contenu de « class CfgRemoteExec { class Functions { … }; }; » d'un config.cpp, sans commentaires ni blocs
     * « #if 0 » (cppCode) ; vide si ce n'est pas le bloc que lit le moteur (contre-expertise mpauth3 v1, R3 : O31). Il
     * faut une seule classe CfgRemoteExec, au premier niveau, avec une seule classe Functions juste dedans ; aucun
     * allowedTargets hors de ce bloc ; ni mode ni jip au niveau de l'addon (ils s'imposeraient à toutes les missions).
     */
    private function remoteExecFunctions(string $rel): string
    {
        $code = $this->cppCode($this->read($rel));
        $blank = $this->blankStrings($code);
        $pairs = $this->pairsOf($blank, '{', '}');
        $depth = static fn (int $at): int => substr_count($blank, '{', 0, $at) - substr_count($blank, '}', 0, $at);
        if (preg_match_all('/\bclass\s+CfgRemoteExec\s*\{/', $blank, $outer, PREG_OFFSET_CAPTURE) !== 1) {
            return '';
        }
        $o = $outer[0][0][1] + strlen($outer[0][0][0]) - 1;
        if ($depth($outer[0][0][1]) !== 0 || !isset($pairs[$o])) {
            return '';
        }
        if (preg_match_all('/\bclass\s+Functions\s*\{/', substr($blank, $o, $pairs[$o] - $o), $inner, PREG_OFFSET_CAPTURE) !== 1) {
            return '';
        }
        $f = $o + $inner[0][0][1] + strlen($inner[0][0][0]) - 1;
        if ($depth($o + $inner[0][0][1]) !== 1 || !isset($pairs[$f])) {
            return '';
        }
        $body = substr($blank, $f + 1, $pairs[$f] - $f - 1);
        $top = $body;
        do {
            $prev = $top;
            $top = (string) preg_replace('/\{[^{}]*\}/', '', $prev);
        } while ($top !== $prev);
        if (substr_count($body, 'allowedTargets') !== substr_count($blank, 'allowedTargets') || preg_match('/\b(?:mode|jip)\s*=/i', $top) === 1) {
            return '';
        }
        return substr($code, $f + 1, $pairs[$f] - $f - 1);
    }

    /** Source SQF sans ses commentaires de ligne et de bloc, chaînes "…" et '…' gardées telles quelles. */
    private function stripSqfComments(string $src): string
    {
        $out = '';
        $n = strlen($src);
        for ($i = 0; $i < $n; $i++) {
            $c = $src[$i];
            if ($c === '"' || $c === "'") {
                $j = $this->stringEnd($src, $i);
                $out .= substr($src, $i, $j - $i + 1);
                $i = $j;
            } elseif ($c === '/' && ($src[$i + 1] ?? '') === '/') {
                $j = strpos($src, "\n", $i);
                $i = $j === false ? $n : $j - 1;
            } elseif ($c === '/' && ($src[$i + 1] ?? '') === '*') {
                $j = strpos($src, '*/', $i + 2);
                $i = $j === false ? $n : $j + 1;
                $out .= ' ';
            } else {
                $out .= $c;
            }
        }
        return $out;
    }

    /** Position du guillemet qui ferme la chaîne ouverte en $i (un guillemet doublé est un guillemet échappé). */
    private function stringEnd(string $s, int $i): int
    {
        $q = $s[$i];
        $n = strlen($s);
        for ($j = $i + 1; $j < $n; $j++) {
            if ($s[$j] === $q) {
                if (($s[$j + 1] ?? '') !== $q) {
                    return $j;
                }
                $j++;
            }
        }
        return $n - 1;
    }

    /** Contenu du tableau dont le crochet ouvrant est en $open, jusqu'à son crochet fermant ; null s'il ne ferme pas. */
    private function bracketBody(string $code, int $open): ?string
    {
        $depth = 0;
        $n = strlen($code);
        for ($i = $open; $i < $n; $i++) {
            $c = $code[$i];
            if ($c === '"' || $c === "'") {
                $i = $this->stringEnd($code, $i);
            } elseif ($c === '[') {
                $depth++;
            } elseif ($c === ']' && --$depth === 0) {
                return substr($code, $open + 1, $i - $open - 1);
            }
        }
        return null;
    }

    /** Éléments de premier niveau d'un contenu de tableau (virgules hors crochets, parenthèses, accolades et chaînes). */
    private function topLevelSplit(string $body): array
    {
        $parts = [];
        $depth = 0;
        $start = 0;
        $n = strlen($body);
        for ($i = 0; $i < $n; $i++) {
            $c = $body[$i];
            if ($c === '"' || $c === "'") {
                $i = $this->stringEnd($body, $i);
            } elseif ($c === '[' || $c === '(' || $c === '{') {
                $depth++;
            } elseif ($c === ']' || $c === ')' || $c === '}') {
                $depth--;
            } elseif ($c === ',' && $depth === 0) {
                $parts[] = substr($body, $start, $i - $start);
                $start = $i + 1;
            }
        }
        $parts[] = substr($body, $start);
        return $parts;
    }

    /** Code de chaque fichier .sqf de main/ et de connect/ (codeUnits) : [chemin relatif => [code, code des chaînes…]]. */
    private function sources(): array
    {
        $files = [];
        foreach ([self::MAIN, self::CONNECT] as $dir) {
            foreach ($this->sqfFiles($dir) as $rel => $src) {
                $files[$rel] = $this->codeUnits($src);
            }
        }
        return $files;
    }

    /** Listes d'actions distantes : [fonction (minuscules) => actions de sa garde « if (_remote && {!(_x in [...])}) exitWith { »]. */
    private function remoteLists(array $files): array
    {
        $lists = [];
        foreach ($files as $rel => $units) {
            if (preg_match('/^if \(_remote && \{!\(_\w+ in \[([^\]]*)\]\)\}\) exitWith \{$/m', $units[0], $m) === 1) {
                preg_match_all('/"([^"]+)"/', $m[1], $a);
                $lists[$this->functionOf($rel)] = $a[1];
            }
        }
        return $lists;
    }

    /**
     * Actions envoyées par remoteExec / remoteExecCall aux fonctions à liste : [[fonction => [action => [fichier => true]]],
     * [envois dont l'action n'est ni littérale ni remplie par un relais connu], [ordres du serveur (serverBranch), même
     * forme que le premier tableau]].
     */
    private function actionsSent(array $files, array $lists): array
    {
        $sent = [];
        $unread = [];
        $orders = [];
        foreach ($files as $rel => $units) {
            foreach ($units as $code) {
                foreach ($this->remoteCalls($code) as [$left, $right, $at]) {
                    $fn = strtolower((string) ($right === null ? '' : $this->literal($right[0])));
                    if (!isset($lists[$fn])) {
                        continue;
                    }
                    $first = trim($left[0] ?? '');
                    $lit = $this->literal($first);
                    $acts = $left === null ? [] : ($lit !== null ? [$lit] : $this->relayActions($rel, $code, $first, $at, $files));
                    if ($acts === []) {
                        $unread[] = "$rel : action « $first » vers $fn, ni littérale ni remplie par un relais connu";
                    }
                    $server = $this->serverBranch($code, $at);
                    foreach ($acts as $act) {
                        if ($server) {
                            $orders[$fn][$act][$rel] = true;
                        } else {
                            $sent[$fn][$act][$rel] = true;
                        }
                    }
                }
            }
        }
        return [$sent, $unread, $orders];
    }

    /**
     * Vrai si la position $at d'un code tombe dans une branche du serveur seul : dans un bloc « { … } » qui s'ouvre sur
     * « if (!isServer) exitWith { … }; », après ce bloc de sortie (le code de sortie lui-même tourne hors du serveur).
     */
    private function serverBranch(string $code, int $at): bool
    {
        $blank = $this->blankStrings($code);
        $braces = $this->pairsOf($blank, '{', '}');
        foreach ($braces as $o => $c) {
            if ($o >= $at || $at >= $c || preg_match('/^\{\s*if\s*\(\s*!\s*isServer\s*\)\s*exitWith\s*\{/i', substr($blank, $o, 120), $m) !== 1) {
                continue;
            }
            $exit = $o + strlen($m[0]) - 1;
            if (isset($braces[$exit]) && $braces[$exit] < $at) {
                return true;
            }
        }
        return false;
    }

    /** Nom CfgFunctions (en minuscules) de la fonction d'un fichier fn_*.sqf de main/ ou de connect/. */
    private function functionOf(string $rel): string
    {
        $prefix = str_starts_with($rel, self::CONNECT . '/') ? 'comspec_overwatch_connect_fnc_' : 'comspec_atak_native_fnc_';
        return preg_match('#/fn_(\w+)\.sqf$#', $rel, $m) === 1 ? strtolower($prefix . $m[1]) : '';
    }

    /** $code où le contenu de chaque chaîne est remplacé par des espaces (guillemets et retours à la ligne gardés). */
    private function blankStrings(string $code): string
    {
        $n = strlen($code);
        for ($i = 0; $i < $n; $i++) {
            $c = $code[$i];
            if ($c === '"' || $c === "'") {
                $j = $this->stringEnd($code, $i);
                for ($k = $i + 1; $k < $j; $k++) {
                    if ($code[$k] !== "\n") {
                        $code[$k] = ' ';
                    }
                }
                $i = $j;
            }
        }
        return $code;
    }

    /**
     * Code d'un source SQF, commentaires retirés (en premier), puis le code porté par ses chaînes (actions d'interface
     * compilées, formats), chaînes dans les chaînes comprises.
     */
    private function codeUnits(string $src): array
    {
        $units = [];
        $todo = [$this->stripSqfComments($src)];
        while ($todo !== []) {
            $code = array_shift($todo);
            $units[] = $code;
            $n = strlen($code);
            for ($i = 0; $i < $n; $i++) {
                $c = $code[$i];
                if ($c === '"' || $c === "'") {
                    $j = $this->stringEnd($code, $i);
                    $inner = str_replace($c . $c, $c, substr($code, $i + 1, max(0, $j - $i - 1)));
                    if (preg_match('/\bcall\b|\bremoteExec|CBA_fnc_/i', $inner) === 1) {
                        $todo[] = $inner;
                    }
                    $i = $j;
                }
            }
        }
        return $units;
    }

    /** Valeur d'un littéral de chaîne SQF ("…" ou '…', guillemet doublé = guillemet) ; null si $expr n'en est pas un. */
    private function literal(string $expr): ?string
    {
        $e = trim($expr);
        if (strlen($e) < 2 || ($e[0] !== '"' && $e[0] !== "'") || $this->stringEnd($e, 0) !== strlen($e) - 1) {
            return null;
        }
        return str_replace($e[0] . $e[0], $e[0], substr($e, 1, -1));
    }

    /** Paires d'un code aux chaînes vidées (blankStrings) : position de l'ouvrant => position du fermant. */
    private function pairsOf(string $blank, string $open, string $close): array
    {
        $pairs = [];
        $stack = [];
        preg_match_all('/[' . preg_quote($open . $close, '/') . ']/', $blank, $m, PREG_OFFSET_CAPTURE);
        foreach ($m[0] as [$c, $i]) {
            if ($c === $open) {
                $stack[] = $i;
            } elseif ($stack !== []) {
                $pairs[array_pop($stack)] = $i;
            }
        }
        return $pairs;
    }

    /** Éléments du tableau écrit juste avant $at (« [ … ] » puis des blancs) ; null s'il n'y en a pas. */
    private function arrayBefore(string $code, string $blank, array $closeToOpen, int $at): ?array
    {
        $k = $at - 1;
        while ($k >= 0 && ctype_space($blank[$k])) {
            $k--;
        }
        if ($k < 0 || $blank[$k] !== ']' || !isset($closeToOpen[$k])) {
            return null;
        }
        $o = $closeToOpen[$k];
        return $this->topLevelSplit(substr($code, $o + 1, $k - $o - 1));
    }

    /**
     * Appels remoteExec / remoteExecCall d'un code, hors chaînes (leur code est lu par codeUnits) : [éléments du
     * tableau de gauche ou null, éléments du tableau de droite ou null s'il n'est pas écrit en toutes lettres, position].
     */
    private function remoteCalls(string $code): array
    {
        $blank = $this->blankStrings($code);
        $pairs = $this->pairsOf($blank, '[', ']');
        $back = array_flip($pairs);
        $out = [];
        preg_match_all('/\bremoteExec(?:Call)?\b/i', $blank, $m, PREG_OFFSET_CAPTURE);
        foreach ($m[0] as [$txt, $at]) {
            $k = $at + strlen($txt);
            while ($k < strlen($blank) && ctype_space($blank[$k])) {
                $k++;
            }
            $right = isset($pairs[$k]) ? $this->topLevelSplit(substr($code, $k + 1, $pairs[$k] - $k - 1)) : null;
            $out[] = [$this->arrayBefore($code, $blank, $back, $at), $right, $at];
        }
        return $out;
    }

    /** Tableaux passés à « call <nom> » dans un code, hors chaînes (nom exact, casse ignorée) : [éléments ou null, position]. */
    private function callsOf(string $code, string $name): array
    {
        $blank = $this->blankStrings($code);
        $back = array_flip($this->pairsOf($blank, '[', ']'));
        $out = [];
        preg_match_all('/\bcall\s+' . preg_quote($name, '/') . '\b/i', $blank, $m, PREG_OFFSET_CAPTURE);
        foreach ($m[0] as [, $at]) {
            $out[] = [$this->arrayBefore($code, $blank, $back, $at), $at];
        }
        return $out;
    }

    /** Gestionnaires d'événement CBA écrits en toutes lettres dans un code : [nom, corps « { … } »]. */
    private function cbaHandlers(string $code): array
    {
        $out = [];
        foreach (['CBA_fnc_addEventHandler', 'CBA_fnc_addEventHandlerArgs'] as $add) {
            foreach ($this->callsOf($code, $add) as [$args]) {
                $name = $args === null ? null : $this->literal($args[0]);
                $body = trim($args[1] ?? '');
                if ($name !== null && str_starts_with($body, '{')) {
                    $out[] = [$name, $body];
                }
            }
        }
        return $out;
    }

    /** Rang de $var dans le premier tableau « params [ … ] » de $code ; null s'il n'y est pas. */
    private function paramRank(string $code, string $var): ?int
    {
        $i = strpos($code, '[');
        $body = $i === false ? null : $this->bracketBody($code, $i);
        if ($body === null) {
            return null;
        }
        foreach ($this->topLevelSplit($body) as $k => $el) {
            $el = trim($el);
            if (str_starts_with($el, '[')) {
                $inner = $this->bracketBody($el, 0);
                $el = $inner === null ? '' : trim($this->topLevelSplit($inner)[0]);
            }
            $name = $this->literal($el);
            if ($name !== null && strcasecmp($name, $var) === 0) {
                return $k;
            }
        }
        return null;
    }

    /**
     * Actions qu'une variable porte jusqu'à un appel distant, quand un relais connu la remplit ([] sinon) :
     * - paramètre d'une fonction locale (« private _send = { params ["_a", …]; … } ») : élément de même rang des
     *   appelants « [...] call _send » du même code ;
     * - sélecteur du switch englobant (« switch (_act) do { case "validate"; case "dismiss": { … } } ») : ces étiquettes ;
     * - paramètre lu dans une branche de la fonction (« case "server": { _arg params [["_a", ""], …] … } ») : élément
     *   de même rang du deuxième argument des appelants « ["server", [...]] call <fonction du fichier> », dans tous
     *   les fichiers.
     * Une valeur non littérale chez un appelant, qu'aucun relais ne remplit, est rendue entre chevrons : absente de
     * toute liste, elle fait échouer le test.
     */
    private function relayActions(string $rel, string $code, string $var, int $at, array $files, int $depth = 0): array
    {
        if ($depth > 3 || preg_match('/^_\w+$/', $var) !== 1) {
            return [];
        }
        $blank = $this->blankStrings($code);
        $around = array_filter($this->pairsOf($blank, '{', '}'), static fn (int $c, int $o): bool => $o < $at && $at < $c, ARRAY_FILTER_USE_BOTH);
        krsort($around);
        foreach ($around as $o => $c) {
            $inside = substr($code, $o + 1, $c - $o - 1);
            if (preg_match('/^\s*params\s*\[/', $inside) === 1 && ($rank = $this->paramRank($inside, $var)) !== null) {
                if (preg_match('/(_\w+)\s*=\s*$/', substr($blank, max(0, $o - 120), min(120, $o)), $nm) !== 1) {
                    return [];
                }
                $acts = [];
                foreach ($this->callsOf($code, $nm[1]) as [$args, $p]) {
                    $acts = array_merge($acts, $this->valueAt($rel, $code, $args, $rank, $p, $files, $depth));
                }
                return $acts;
            }
            if (preg_match('/((?:\bcase\s+"[^"]*"\s*;\s*)*)\bcase\s+"([^"]*)"\s*:\s*$/i', substr($code, max(0, $o - 400), min(400, $o)), $cm) !== 1) {
                continue;
            }
            preg_match_all('/"([^"]*)"/', $cm[0], $lm);
            $outer = array_filter($around, static fn (int $c2, int $o2): bool => $o2 < $o && $c < $c2, ARRAY_FILTER_USE_BOTH);
            $sw = $outer === [] ? null : max(array_keys($outer));
            if ($sw !== null && preg_match('/\bswitch\s*\(\s*(_\w+)\s*\)\s*do\s*$/i', substr($code, max(0, $sw - 200), min(200, $sw)), $sm) === 1
                && strcasecmp($sm[1], $var) === 0) {
                return $lm[1];
            }
            $pre = substr($code, $o + 1, $at - $o - 1);
            if (count($lm[1]) === 1 && preg_match('/\b_arg\s+params\s*\[/', $pre, $am, PREG_OFFSET_CAPTURE) === 1
                && ($rank = $this->paramRank(substr($pre, $am[0][1]), $var)) !== null) {
                $acts = [];
                foreach ($files as $rel2 => $units) {
                    foreach ($units as $code2) {
                        foreach ($this->callsOf($code2, $this->functionOf($rel)) as [$args, $p]) {
                            if ($args === null || $this->literal($args[0]) !== $lm[1][0]) {
                                continue;
                            }
                            $sub = trim($args[1] ?? '');
                            $inner = str_starts_with($sub, '[') && str_ends_with($sub, ']') ? $this->topLevelSplit(substr($sub, 1, -1)) : null;
                            $acts = array_merge($acts, $this->valueAt($rel2, $code2, $inner, $rank, $p, $files, $depth));
                        }
                    }
                }
                return $acts;
            }
        }
        return [];
    }

    /** Valeur d'un élément d'appel : littéral, ou actions d'un relais qui le remplit, ou l'expression entre chevrons. */
    private function valueAt(string $rel, string $code, ?array $args, int $rank, int $at, array $files, int $depth): array
    {
        $v = trim($args[$rank] ?? '?');
        $lit = $this->literal($v);
        if ($lit !== null) {
            return [$lit];
        }
        $deeper = $this->relayActions($rel, $code, $v, $at, $files, $depth + 1);
        return $deeper !== [] ? $deeper : ['<' . $v . '>'];
    }

    /** Texte d'un config.cpp sans commentaires (hors chaînes "…") ni blocs « #if 0 » (jusqu'au #else ou #endif de même niveau). */
    private function cppCode(string $src): string
    {
        $out = '';
        $n = strlen($src);
        for ($i = 0; $i < $n; $i++) {
            $c = $src[$i];
            if ($c === '"') {
                $j = $this->stringEnd($src, $i);
                $out .= substr($src, $i, $j - $i + 1);
                $i = $j;
            } elseif ($c === '/' && ($src[$i + 1] ?? '') === '/') {
                $j = strpos($src, "\n", $i);
                $i = $j === false ? $n : $j - 1;
            } elseif ($c === '/' && ($src[$i + 1] ?? '') === '*') {
                $j = strpos($src, '*/', $i + 2);
                $i = $j === false ? $n : $j + 1;
                $out .= ' ';
            } else {
                $out .= $c;
            }
        }
        $keep = [];
        $skip = 0;
        foreach (explode("\n", $out) as $line) {
            if ($skip > 0) {
                if (preg_match('/^\s*#\s*if/', $line) === 1) {
                    $skip++;
                } elseif (preg_match('/^\s*#\s*endif\b/', $line) === 1) {
                    $skip--;
                } elseif ($skip === 1 && preg_match('/^\s*#\s*(?:else|elif)\b/', $line) === 1) {
                    $skip = 0;
                }
                continue;
            }
            if (preg_match('/^\s*#\s*if\s+0\b/', $line) === 1) {
                $skip = 1;
                continue;
            }
            $keep[] = $line;
        }
        return implode("\n", $keep);
    }
}
