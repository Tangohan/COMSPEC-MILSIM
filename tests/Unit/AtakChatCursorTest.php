<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Controllers\Api\AtakApiController;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * Relève de tchat avec curseur (TEST-006).
 *
 * Le jeu avance son curseur « after » au plus grand id REÇU. Athena lisait 2 × limit messages après le curseur
 * puis gardait les limit PLUS RÉCENTS : après une rafale (ordres du TOC, reprise après coupure), les plus anciens
 * n'étaient plus jamais demandés et disparaissaient sans erreur. On renvoie désormais les plus anciens.
 */
final class AtakChatCursorTest extends TestCase
{
    /** @return list<array<string, mixed>> */
    private static function scan(callable $fetch, int $after, int $limit, int $window, string $callsign = ''): array
    {
        $m = new ReflectionMethod(AtakApiController::class, 'scanChatAfter');
        $m->setAccessible(true);

        /** @var list<array<string, mixed>> $rows */
        $rows = $m->invoke(null, $fetch, $after, $limit, $window, $callsign);

        return $rows;
    }

    /**
     * @param list<array<string, mixed>> $all
     * @return callable(int, int): list<array<string, mixed>>
     */
    private static function table(array $all, array &$calls): callable
    {
        return static function (int $cursor, int $window) use ($all, &$calls): array {
            $calls[] = [$cursor, $window];
            $out = [];
            foreach ($all as $row) {
                if ((int) $row['id'] > $cursor) {
                    $out[] = $row;
                    if (count($out) >= $window) {
                        break;
                    }
                }
            }

            return $out;
        };
    }

    /** @return list<array<string, mixed>> */
    private static function messages(int $from, int $to, string $author = 'TOC', string $body = 'ordre'): array
    {
        $rows = [];
        for ($id = $from; $id <= $to; $id++) {
            $rows[] = ['id' => $id, 'author' => $author, 'body' => $body . ' ' . $id, 'source' => 'game'];
        }

        return $rows;
    }

    /** @return list<int> */
    private static function ids(array $rows): array
    {
        return array_map(static fn (array $r): int => (int) $r['id'], $rows);
    }

    public function testBurstReturnsTheOldestMessagesAfterTheCursorNotTheNewest(): void
    {
        // Cas C07 du banc : after=1005, 60 nouveaux messages, limit 25, fenêtre 50.
        $calls = [];
        $rows = self::scan(self::table(self::messages(1006, 1065), $calls), 1005, 25, 50);

        self::assertSame(range(1006, 1030), self::ids($rows), 'les 25 plus anciens après le curseur');
        self::assertCount(1, $calls, 'une seule lecture suffit quand la page est pleine');
    }

    public function testClientDrainsTheBacklogOverSuccessivePolls(): void
    {
        // Le client repart du plus grand id reçu : trois relèves doivent tout rendre, sans trou ni doublon.
        $all = self::messages(1006, 1065);
        $seen = [];
        $after = 1005;
        for ($poll = 0; $poll < 3; $poll++) {
            $calls = [];
            $rows = self::scan(self::table($all, $calls), $after, 25, 50);
            if ($rows === []) {
                break;
            }
            foreach (self::ids($rows) as $id) {
                self::assertNotContains($id, $seen, 'aucun message renvoyé deux fois');
                $seen[] = $id;
            }
            $after = max(self::ids($rows));
        }

        self::assertSame(range(1006, 1065), $seen, 'les 60 messages finissent tous par arriver');
    }

    public function testHiddenSettingsBlockDoesNotFreezeTheCursor(): void
    {
        // 50 messages techniques (auteur REGLAGES / corps AFFICHAGE|…) puis 5 vrais : la fenêtre de 50 ne contient
        // que du bruit. Sans enchaînement de fenêtres, Athena renvoyait [] et le curseur ne bougeait plus jamais.
        $all = array_merge(
            self::messages(1006, 1055, 'REGLAGES', 'AFFICHAGE|carte'),
            self::messages(1056, 1060)
        );
        $calls = [];
        $rows = self::scan(self::table($all, $calls), 1005, 25, 50);

        self::assertSame(range(1056, 1060), self::ids($rows));
        self::assertGreaterThan(1, count($calls), 'il a fallu traverser le bloc caché');
        self::assertLessThanOrEqual(4, count($calls), 'le nombre de fenêtres par relève reste borné');
    }

    public function testScanStopsWhenTheWindowIsNotFull(): void
    {
        $calls = [];
        $rows = self::scan(self::table(self::messages(1006, 1010), $calls), 1005, 25, 50);

        self::assertSame(range(1006, 1010), self::ids($rows));
        self::assertCount(1, $calls, 'rattrapé : pas de seconde lecture');
    }

    public function testEmptyTableReturnsNothingAndReadsOnce(): void
    {
        $calls = [];
        self::assertSame([], self::scan(self::table([], $calls), 1005, 25, 50));
        self::assertCount(1, $calls);
    }

    public function testPrivateMessagesForSomeoneElseAreSkippedWithoutFreezingTheCursor(): void
    {
        // 50 MP entre deux autres joueurs, puis 5 messages ouverts : le joueur qui filtre par indicatif doit
        // recevoir les 5, pas une réponse vide.
        $all = array_merge(
            self::messages(1006, 1055, 'BRAVO', 'MP|BRAVO|CHARLIE|salut'),
            self::messages(1056, 1060)
        );
        $calls = [];
        $rows = self::scan(self::table($all, $calls), 1005, 25, 50, 'ALPHA');

        self::assertSame(range(1056, 1060), self::ids($rows));
    }

    public function testPrivateMessagesForTheCallerAreKept(): void
    {
        $all = self::messages(1006, 1008, 'BRAVO', 'MP|BRAVO|ALPHA|salut');
        $calls = [];
        $rows = self::scan(self::table($all, $calls), 1005, 25, 50, 'ALPHA');

        self::assertSame(range(1006, 1008), self::ids($rows));
    }

    public function testNonArrayRowsAreIgnored(): void
    {
        $fetch = static fn (int $cursor, int $window): array => $cursor === 1005
            ? [['id' => 1006, 'author' => 'TOC', 'body' => 'ordre'], 'bruit', null]
            : [];
        $rows = self::scan($fetch, 1005, 25, 50);

        self::assertSame([1006], self::ids($rows));
    }

    public function testTheControllerStillReturnsTheNewestMessagesForAFirstSnapshot(): void
    {
        // Pas de curseur = première ouverture du tchat : le joueur doit voir les messages les plus récents.
        // Le dépôt rend déjà les plus récents en ordre croissant, le contrôleur garde la fin de la liste.
        $src = (string) file_get_contents(dirname(__DIR__, 2) . '/app/Controllers/Api/AtakApiController.php');
        self::assertStringContainsString('$rows = array_slice($rows, -$limit);', $src);
        self::assertStringContainsString('private static function scanChatAfter(', $src);
    }
}
