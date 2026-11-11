<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * « Suivez-moi » : une IA du SSE suit le joueur jusqu'à ce qu'on la relâche (addon ia_rp du pack @COMSPEC_SSE).
 * La décision (avancer ou non, allure) est une table (data/iarp_follow.sqf) lue par fn_iarpFollow « step » : le test
 * relit cette table et rejoue la même règle (première ligne vraie), puis vérifie le câblage (ordre, fin du suivi,
 * état « Suit le joueur », aucune fenêtre d'alerte du jeu).
 */
final class SseIaSuivreAssetTest extends TestCase
{
    private function addon(): string
    {
        return dirname(__DIR__, 2) . '/mod/@COMSPEC_SSE/addons/ia_rp';
    }

    private function read(string $rel): string
    {
        $path = $this->addon() . '/' . $rel;
        self::assertFileExists($path);

        return (string) file_get_contents($path);
    }

    /** @return array<string, mixed> */
    private function table(): array
    {
        $src = (string) preg_replace('#/\*.*?\*/#s', '', $this->read('data/iarp_follow.sqf'));
        $rows = json_decode(trim($src), true);
        self::assertIsArray($rows, 'data/iarp_follow.sqf : tableau SQF illisible');
        $out = [];
        foreach ($rows as [$k, $v]) {
            $out[(string) $k] = $v;
        }

        return $out;
    }

    /**
     * Même règle que fn_iarpFollow « step ».
     *
     * @param array<string, mixed> $c
     *
     * @return array{0: bool, 1: string}
     */
    private function step(array $c, float $dist, float $leaderKmh, bool $moving): array
    {
        $move = $dist > ($moving ? $c['stopAt'] : $c['startAt']);
        $pace = 'walk';
        foreach ($c['pace'] as [$dm, $sm, $p]) {
            if ($dist >= $dm || $leaderKmh >= $sm) {
                $pace = $p;
                break;
            }
        }

        return [$move, $pace];
    }

    public function testTheFollowerStaysBetweenTwoAndFourMetresWithoutJitter(): void
    {
        $c = $this->table();
        self::assertGreaterThanOrEqual(2, $c['stopAt']);
        self::assertLessThanOrEqual(4, $c['startAt']);
        self::assertGreaterThan($c['stopAt'] + 1, $c['startAt'], 'écart trop faible : départs et arrêts en rafale');
        self::assertLessThanOrEqual($c['stopAt'], $c['keep'], 'le point visé doit être dans la zone d\'arrêt');

        // À l'arrêt, elle ne bouge pas tant que le joueur reste à 4 m ; en mouvement, elle continue jusqu'à 2,5 m.
        self::assertFalse($this->step($c, 3.0, 0, false)[0]);
        self::assertTrue($this->step($c, 3.0, 0, true)[0]);
        self::assertFalse($this->step($c, 2.4, 0, true)[0]);
        self::assertTrue($this->step($c, 4.5, 0, false)[0]);
    }

    public function testPaceMatchesThePlayerAndTheGap(): void
    {
        $c = $this->table();
        self::assertSame('walk', $this->step($c, 5, 5, true)[1], 'joueur au pas, tout près : marche');
        self::assertSame('jog', $this->step($c, 5, 10, true)[1], 'joueur en petite foulée : petite foulée');
        self::assertSame('run', $this->step($c, 5, 18, true)[1], 'joueur qui court : course');
        self::assertSame('run', $this->step($c, 30, 0, false)[1], 'joueur loin devant : course pour le rattraper');
        self::assertSame('walk', end($c['pace'])[2], 'dernière ligne : marche par défaut');
        $prevD = INF;
        foreach ($c['pace'] as [$dm, $sm]) {
            self::assertLessThan($prevD, $dm, 'allures : de la plus rapide à la plus lente');
            $prevD = $dm;
        }
    }

    public function testFollowingLastsUntilReleasedAndIsNotSpammedWithMoveOrders(): void
    {
        $c = $this->table();
        self::assertGreaterThanOrEqual(100, $c['lostAt'], 'un joueur à 80 m n\'est pas « perdu »');
        self::assertGreaterThanOrEqual(1, $c['minOrder']);
        self::assertGreaterThan(0, $c['replan']);

        $fn = $this->read('functions/fn_iarpFollow.sqf');
        // Plus de durée limite (4 min) ni d'abandon à 80 m : fin sur ordre, mort, départ ou perte de vue seulement.
        self::assertStringNotContainsString('time + 240', $fn . $this->read('XEH_postInit.sqf'));
        self::assertStringContainsString('"lostAfter"', $fn);
        // Un ordre de déplacement seulement si le point visé a bougé (ou si elle est bloquée) : pas de doMove à chaque passage.
        self::assertSame(1, substr_count($fn, '_u doMove '), 'un seul doMove, gardé par la règle « replan »');
        self::assertMatchesRegularExpression('/\(_tp distance _last\) > \(_c getOrDefault \["replan"/', $fn);
        // Chemin coupé par l'interpellation de l'automate (doStop, disableAI "PATH") : rétabli à chaque passage.
        self::assertStringContainsString('if !(_u checkAIFeature "PATH") then { _u enableAI "PATH"; };', $fn);
        // Exécution là où l'IA est locale ; changement de machine : la boucle reprend chez le nouveau propriétaire.
        self::assertStringContainsString('if (!local _u) exitWith {', $fn);
        self::assertStringContainsString('["comspec_sse_iarpAiDo", [_u, "follow", [_l]], _u] call CBA_fnc_targetEvent', $fn);
        // Menottée, mains en l'air, inconsciente : en pause, pas abandonné.
        self::assertStringContainsString('ace_captives_isHandcuffed', $fn);
    }

    public function testOnlyMovementOrdersEndTheFollow(): void
    {
        $xeh = $this->read('XEH_postInit.sqf');
        self::assertStringContainsString('if !(_cmd in ["follow", "look", "turn", "drop"]) then { ["stop", [_u, _cmd]] call comspec_sse_fnc_iarpFollow; };', $xeh);
        self::assertStringContainsString('case "follow": { ["start", [_u, _a param [0, objNull]]] call comspec_sse_fnc_iarpFollow; };', $xeh);
        // « Reculez » / « Approchez » bridaient l'IA au pas pour toujours : son allure lui est rendue.
        self::assertMatchesRegularExpression('/case "free": \{[^}]*forceSpeed -1/', $xeh);
        self::assertStringContainsString('class iarpFollow {};', $this->read('config.cpp'));
    }

    public function testServerAnswersRealisticallyAndShowsTheStatus(): void
    {
        $srv = $this->read('functions/fn_iarpServer.sqf');
        self::assertStringContainsString('_gi = "followCuffed"', $srv);
        self::assertStringContainsString('_outcome = "trop effrayé pour suivre"', $srv);
        self::assertStringContainsString('["order", _bonus] call _obeys', $srv);
        self::assertStringContainsString('exitWith { "follow" };', $srv);
        self::assertStringContainsString('case "follow"', $this->read('functions/fn_iarpUi.sqf'));

        $bank = $this->read('data/iarp_offtopic.sqf');
        foreach (['"comply.follow"', '"noComply.follow"', '"followCuffed"'] as $key) {
            self::assertStringContainsString('[' . $key . ', [', $bank, "répliques $key absentes");
        }

        $xml = simplexml_load_string($this->read('stringtable.xml'));
        self::assertNotFalse($xml);
        $key = $xml->xpath('//Key[@ID="STR_COMSPEC_IARP_Mood_follow"]')[0] ?? null;
        self::assertNotNull($key, 'état « Suit le joueur » absent');
        self::assertSame('Suit le joueur', trim((string) $key->French));
        self::assertNotSame('', trim((string) $key->English));
    }

    public function testNoGameAlertPopup(): void
    {
        $fn = $this->read('functions/fn_iarpFollow.sqf');
        foreach (['hint ', 'hintSilent', 'systemChat', 'titleText', 'cutText', 'BIS_fnc_showNotification', 'zen_common_fnc_showMessage'] as $cmd) {
            self::assertStringNotContainsString($cmd, $fn, "fn_iarpFollow : $cmd interdit");
        }
    }
}
