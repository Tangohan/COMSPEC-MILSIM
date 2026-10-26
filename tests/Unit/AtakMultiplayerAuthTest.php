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
            // La garde est la première instruction après params, hors commentaires : ni commentée, ni placée après
            // un effet (contre-expertise v3, N10).
            $code = $this->stripSqfComments($src);
            $open = strpos($code, 'params [');
            self::assertNotFalse($open, $file);
            $body = $this->bracketBody($code, $open + strlen('params '));
            self::assertNotNull($body, $file);
            $after = substr($code, $open + strlen('params ') + strlen((string) $body) + 2);
            self::assertMatchesRegularExpression('/^\s*;\s*' . preg_quote($guard, '/') . '/', $after, $file);
        }
        // Liste blanche (mode 1) : jip = 0 fait refuser par le moteur tout dépôt de ces appels dans la file JIP.
        $mainCfg = $this->read(self::MAIN . '/config.cpp');
        self::assertStringContainsString('class comspec_atak_native_fnc_adminClient { allowedTargets = 1; jip = 0; };', $mainCfg);
        self::assertStringContainsString('class comspec_atak_native_fnc_compromise { allowedTargets = 0; jip = 0; };', $mainCfg);
        self::assertStringContainsString('class comspec_overwatch_connect_fnc_applyZeusAtakEffect { allowedTargets = 0; jip = 0; };', $this->read(self::CONNECT . '/config.cpp'));
        // Aucun ordre légitime vers ces fonctions n'emprunte la file : chaque appel distant n'a que deux arguments.
        // Lecture tolérante (contre-expertise v3, N10) : commentaires retirés, tableau lu jusqu'à son crochet fermant
        // (sur plusieurs lignes, sans « ; » final, nom entre guillemets simples). Un nom de fonction calculé n'est pas
        // lisible ainsi : il n'y en a aucun aujourd'hui vers ces trois fonctions (rapport du lot).
        $targets = ['comspec_atak_native_fnc_compromise', 'comspec_atak_native_fnc_adminclient', 'comspec_overwatch_connect_fnc_applyzeusatakeffect'];
        $calls = [];
        foreach ([self::MAIN, self::CONNECT] as $dir) {
            $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(dirname(__DIR__, 2) . '/' . $dir, \FilesystemIterator::SKIP_DOTS));
            foreach ($it as $f) {
                if (strtolower($f->getExtension()) !== 'sqf') {
                    continue;
                }
                $code = $this->stripSqfComments((string) file_get_contents($f->getPathname()));
                preg_match_all('/remoteExec(?:Call)?\s*\[/i', $code, $m, PREG_OFFSET_CAPTURE);
                foreach ($m[0] as [$txt, $off]) {
                    $body = $this->bracketBody($code, $off + strlen($txt) - 1);
                    if ($body === null) {
                        continue;
                    }
                    $args = $this->topLevelSplit($body);
                    if (preg_match('/^(["\'])(.*)\1$/s', trim($args[0]), $q) === 1 && in_array(strtolower($q[2]), $targets, true)) {
                        $calls[] = [$f->getFilename() . ' : ' . preg_replace('/\s+/', ' ', trim($body)), count($args)];
                    }
                }
            }
        }
        self::assertGreaterThanOrEqual(6, count($calls), implode("\n", array_column($calls, 0)));
        foreach ($calls as [$call, $n]) {
            self::assertSame(2, $n, $call);
        }
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
}
