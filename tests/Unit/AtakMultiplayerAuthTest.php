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
        // capLocal, CBA appelle le gestionnaire d'une IA locale au serveur dans cette même pile, et fn_captureServer y
        // refuse l'action « local » : l'IA gardait le téléphone alors que l'app annonçait « Téléphone repris ».
        $recover = $this->between($this->read(self::MAIN . '/functions/admin/fn_adminServer.sqf'), 'case "recover": {', '"Téléphone repris à l\'IA');
        self::assertNotEmpty($recover);
        self::assertMatchesRegularExpression('/if \(local _ai\) then \{\s*if \(_x in \(assignedItems _ai\)\) then \{ _ai unlinkItem _x; \} else \{ _ai removeItem _x; \};\s*\} else \{\s*\["comspec_atak_native_capLocal", \["strip", _ai, _x\], _ai\] call CBA_fnc_targetEvent;/', $recover);
        // La correction ne passe pas par le filtre : « local » reste refusée à tout appel distant (sinon un client
        // ferait retirer, donner ou déplacer quoi que ce soit à une IA du serveur).
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
        // La machine de l'IA pose le téléphone et n'envoie rien.
        $give = $this->between($code, 'case "give": {', 'case "move":');
        self::assertStringContainsString('createVehicle ["GroundWeaponHolder"', $give);
        foreach (['Event', 'remoteExec', 'publicVariable', 'setVariable', 'giveFail', 'giveCheck'] as $send) {
            self::assertStringNotContainsString($send, $give, $send);
        }
        // Le serveur lance la vérification juste après son ordre « give » (tick), avec l'état relevé avant l'ordre.
        self::assertMatchesRegularExpression('/private _chk = \[_ai, _cls, \{ _x == _cls \} count \(\(assignedItems _ai\) \+ \(items _ai\)\), nearestObjects \[_p0, \["GroundWeaponHolder"\], 12\], _p0, _now\];\s*'
            . '\["comspec_atak_native_capLocal", \["give", _ai, _cls\], _ai\] call CBA_fnc_targetEvent;\s*'
            . '\[\{ \["giveCheck", _this\] call comspec_atak_native_fnc_captureServer; \}, _chk, 2\] call CBA_fnc_waitAndExecute;/', $code);
        $check = $this->between($code, 'case "giveCheck": {', 'case "giveFail": {');
        self::assertStringContainsString('if (({ _x == _cls } count ((assignedItems _ai) + (items _ai))) > _had) exitWith {};', $check);
        self::assertStringContainsString('(nearestObjects [_p0, ["GroundWeaponHolder"], 10]) select {', $check);
        self::assertStringContainsString('if (_gh isNotEqualTo []) exitWith { ["giveFail", [_ai, _gh select 0, _p0]] call comspec_atak_native_fnc_captureServer; };', $check);
        $fail = $this->between($code, 'case "giveFail": {', 'case "local": {');
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
        self::assertStringContainsString('class comspec_atak_native_fnc_ftServer { allowedTargets = 2; };', $this->read(self::MAIN . '/config.cpp'));
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
            . 'if \(_remote && \{!\(_act in \["scan", "capture", "hunt", "apps"\]\)\}\) exitWith \{.*?'
            . 'if \(_remote && \{isNull _caller\}\) exitWith \{.*?'
            . 'if \(_remote && \{!\(\[_caller\] call comspec_atak_native_fnc_adminAllowed\)\}\) exitWith \{.*?switch \(_act\) do \{/s', $code);
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
            . 'if \(_remote && \{!\(_act in \["force", "remind", "lift", "pull", "state", "hello"\]\)\}\) exitWith \{.*?'
            . 'if \(isNull _caller && \{_act isNotEqualTo "init"\}\) exitWith \{.*?'
            . 'if \(_act in \["force", "remind", "lift", "pull"\]\) then \{.*?'
            . '_ok = \(_caller getVariable \["COMSPEC_ATAK_DebriefRight", false\]\) isEqualTo true\s*\|\| \{\[_caller\] call comspec_atak_native_fnc_adminAllowed && .*?'
            . 'if \(!_ok\) exitWith \{.*?\["comspec_atak_native_debriefData", \["denied", \[\]\], _caller\] call CBA_fnc_targetEvent;/s', $head);
        self::assertStringNotContainsString('CBA_fnc_addEventHandler', $code);
        // Les deux émetteurs du client passent par remoteExecCall, sans unité dans la demande.
        self::assertStringContainsString('private _server = { params ["_a", ["_x", []]]; [_a, _x] remoteExecCall ["comspec_atak_native_fnc_debriefServer", 2]; };', $this->read(self::MAIN . '/functions/debrief/fn_debrief.sqf'));
        self::assertStringContainsString('["hello", []] remoteExecCall ["comspec_atak_native_fnc_debriefServer", 2];', $this->read(self::MAIN . '/XEH_postInitClient.sqf'));
        self::assertStringContainsString('class comspec_atak_native_fnc_debriefServer { allowedTargets = 2; };', $this->cppCode($this->read(self::MAIN . '/config.cpp')));
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
        self::assertSame(['force', 'remind', 'lift', 'pull', 'state', 'hello'], $lists['comspec_atak_native_fnc_debriefserver'] ?? null);
        [$sent] = $this->actionsSent($files, $lists);
        $got = array_keys($sent['comspec_atak_native_fnc_debriefserver'] ?? []);
        sort($got);
        self::assertSame(['force', 'hello', 'lift', 'pull', 'remind', 'state'], $got);
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
        self::assertStringContainsString('class comspec_atak_native_fnc_compromise { allowedTargets = 0; jip = 0; };', $this->read(self::MAIN . '/config.cpp'));
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
        self::assertMatchesRegularExpression('/private _viewer = \{\s*params \["_v", "_u"\];\s*if !\(_v isEqualType \[\]\) exitWith \{ false \};\s*private _o = _v param \[0, objNull\];\s*'
            . '_o isEqualType objNull && \{!isNull _o\} && \{_o in \(missionNamespace getVariable \["COMSPEC_ATAK_TocScreens", \[\]\]\)\}\s*'
            . '&& \{\[_o, _u\] call _sideOk\} && \{\(_u distance _o\) <= 25\}\s*\};/', $head);
        // « spawn » ne se ré-appelle plus pour la liste des écrans (« classes » est refusée à distance) ; plus d'événement.
        self::assertStringNotContainsString('["classes"] call _fn', $code);
        self::assertStringNotContainsString('CBA_fnc_addEventHandler', $code);
        self::assertStringNotContainsString('CBA_fnc_serverEvent', $code);
        foreach (['add' => 'true', 'remove' => 'true', 'spawn' => 'objNull', 'view' => 'true'] as $mode => $ret) {
            self::assertStringContainsString('if (!isServer) exitWith { ["' . $mode . '", _a] remoteExecCall ["comspec_atak_native_fnc_tocScreen", 2]; ' . $ret . ' };', $code, $mode);
        }
        self::assertStringContainsString('class comspec_atak_native_fnc_tocScreen { allowedTargets = 2; };', $this->cppCode($this->read(self::MAIN . '/config.cpp')));
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
        // commentaires ni blocs « #if 0 » : une classe en commentaire ou sous #if 0 ne compte pas (v4, N14, K13, K14).
        $mainCfg = $this->cppCode($this->read(self::MAIN . '/config.cpp'));
        self::assertStringContainsString('class comspec_atak_native_fnc_adminClient { allowedTargets = 1; jip = 0; };', $mainCfg);
        self::assertStringContainsString('class comspec_atak_native_fnc_compromise { allowedTargets = 0; jip = 0; };', $mainCfg);
        self::assertStringContainsString('class comspec_overwatch_connect_fnc_applyZeusAtakEffect { allowedTargets = 0; jip = 0; };', $this->cppCode($this->read(self::CONNECT . '/config.cpp')));
        // Aucun ordre légitime vers ces fonctions n'emprunte la file : chaque appel distant n'a que deux arguments.
        // Lecture tolérante (contre-expertise v3, N10) : commentaires retirés, tableau lu jusqu'à son crochet fermant
        // (sur plusieurs lignes, sans « ; » final, nom entre guillemets simples), code porté par des chaînes compris.
        // Plus d'angle mort de nom (v4, N14, K15, K16) : dans main/ et connect/, tout remoteExec / remoteExecCall est
        // suivi d'un tableau écrit en toutes lettres, dont le premier élément est un nom de fonction littéral.
        $targets = ['comspec_atak_native_fnc_compromise', 'comspec_atak_native_fnc_adminclient', 'comspec_overwatch_connect_fnc_applyzeusatakeffect'];
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
        $removed = ['captureSrv', 'compromise', 'admin', 'adminReport', 'zeus', 'ft', 'planSrv', 'dataSrv'];
        $quoted = '/(["\'])comspec_atak_native_(?:' . implode('|', $removed) . ')\1/i';
        // Fonctions qui jugent le demandeur par remoteExecutedOwner (fn_reqUnit) : appelées depuis un gestionnaire CBA,
        // elles ne voient pas d'appel distant et croient l'unité fournie par l'émetteur. Aucun gestionnaire ne les
        // appelle (v4, N14, K17), sauf deux corps exacts, à action fixe : capLocal vers fn_captureServer « local »
        // (actions sur la machine où l'objet est local, que le serveur refuse dans une pile distante : résidu connu du
        // lot) et comspec_atak_native_camShare vers fn_camShare « recv » (lot mpauth3 : notification du destinataire,
        // sans effet hors de son téléphone ; « srvAcc » reste hors d'atteinte du gestionnaire).
        $allowed = [
            'comspec_atak_native_caplocal' => '{ ["local", _this] call comspec_atak_native_fnc_captureServer; }',
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
        [$sent, $unread] = $this->actionsSent($files, $lists);
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
            'comspec_atak_native_fnc_adminserver' => ['apps', 'capture', 'hunt', 'scan'],
            'comspec_atak_native_fnc_captureserver' => ['athena', 'dismiss', 'dropped', 'validate'],
            'comspec_atak_native_fnc_zeusserver' => ['jamArea', 'jamClear', 'once', 'reset', 'set'],
        ] as $fn => $expected) {
            $got = array_keys($sent[$fn] ?? []);
            sort($got);
            self::assertSame($expected, $got, $fn);
        }
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
        // Ramassage : pour le demandeur lui-même, vivant, à 10 m au plus. Registre : ajout seulement, caméra proche.
        $take = $this->between($code, 'case "srvTake": {', 'case "pickupGive": {');
        self::assertMatchesRegularExpression('/^case "srvTake": \{\s*if \(!isServer\) exitWith \{ false \};\s*'
            . 'if \(!\(_a isEqualType objNull\) \|\| \{isNull _a\} \|\| \{!\(_b isEqualType objNull\)\} \|\| \{isNull _b\} [^\n]*\) exitWith \{ false \};\s*'
            . 'if \(_remote && \{_b isNotEqualTo _req \|\| \{!alive _req\} \|\| \{\(_req distance _a\) > 10\}\}\) exitWith \{[^{}]*false\s*\};\s*'
            . '_a setVariable \["COMSPEC_ATAK_CamTaken", true\];/', $take);
        $reg = $this->between($code, 'case "srvReg": {', 'case "rotate": {');
        self::assertMatchesRegularExpression('/^case "srvReg": \{\s*if \(!isServer \|\| \{!\(_a isEqualType objNull\)\}\) exitWith \{ false \};\s*'
            . 'if \(_remote && \{_b isNotEqualTo true \|\| \{isNull _a\} \|\| \{!alive _req\} \|\| \{\(_req distance _a\) > 10\}\}\) exitWith \{[^{}]*false\s*\};\s*'
            . '\[_a, _b isEqualTo true\] call _register;\s*true\s*\};\s*$/', $reg);
        // Ni l'une ni l'autre ne se ré-appelle : dans la pile d'une demande distante, l'appel imbriqué garde le contexte
        // distant et serait refusé (« dead » rendait faux, le retrait du registre ne se faisait plus).
        foreach (['srvTake' => $take, 'srvReg' => $reg] as $mode => $body) {
            self::assertDoesNotMatchRegularExpression('/\bcall\s+(?:_fn|comspec_atak_native_fnc_camAction)\b/i', $body, $mode);
        }
        self::assertStringContainsString('class comspec_atak_native_fnc_camAction { allowedTargets = 0; jip = 0; };', $this->cppCode($this->read(self::MAIN . '/config.cpp')));
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
        // (« owner », « can »), écrites une seule fois en fonctions locales.
        $code = $this->stripSqfComments($this->read(self::MAIN . '/functions/camera/fn_camShare.sqf'));
        $head = $this->between($code, 'params [["_mode", ""], ["_a", objNull], ["_b", objNull]];', 'switch (_mode) do {');
        self::assertMatchesRegularExpression('/^params \[\["_mode", ""\], \["_a", objNull\], \["_b", objNull\]\];\s*'
            . 'if \(isRemoteExecutedJIP\) exitWith \{[^{}]*false\s*\};\s*'
            . 'private _remote = isMultiplayer && \{isRemoteExecuted\} && \{remoteExecutedOwner isNotEqualTo 2\};\s*'
            . 'private _req = objNull;\s*'
            . 'if \(_remote\) then \{ _req = \[remoteExecutedOwner\] call comspec_atak_native_fnc_reqUnit; \};\s*'
            . 'if \(_remote && \{!\(_mode in \["srvAcc"\]\)\}\) exitWith \{[^{}]*false\s*\};\s*'
            . 'if \(_remote && \{isNull _req\}\) exitWith \{[^{}]*false\s*\};/', $head);
        // Caméra de téléphone : son porteur seul ; caméra fixe : retrait par le poseur, partage par qui y a accès.
        self::assertMatchesRegularExpression('/private _mayEdit = \{\s*params \["_s", "_u", "_share"\];\s*'
            . 'if !\(\[_s\] call _isCam\) exitWith \{ _s isEqualTo _u \};\s*'
            . 'if \(_share\) then \{ \[_s, _u\] call _canSee \} else \{ \[_s, _u\] call _isOwner \}\s*\};/', $head);
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
        self::assertStringContainsString('class comspec_atak_native_fnc_camShare { allowedTargets = 2; };', $this->cppCode($this->read(self::MAIN . '/config.cpp')));
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
        foreach (['add', 'remove'] as $mode) {
            self::assertStringContainsString('if (!isServer) exitWith { ["' . $mode . '", _a] remoteExecCall ["comspec_atak_native_fnc_screenCast", 2]; true };', $code, $mode);
        }
        self::assertStringNotContainsString('CBA_fnc_addEventHandler', $code);
        self::assertStringNotContainsString('CBA_fnc_serverEvent', $code);
        self::assertStringContainsString('class comspec_atak_native_fnc_screenCast { allowedTargets = 2; };', $this->cppCode($this->read(self::MAIN . '/config.cpp')));
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
