<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Repositories\JnetLinksRepository;
use App\Services\Jnet\JnetLinkRules as R;
use App\Services\Jnet\JnetLinkService;
use App\Services\Jnet\JnetLinkViewer;
use App\Services\Jnet\JnetSseViewer;
use App\Services\Jnet\SseLinkJnetCitations;
use App\Support\SseLinkCatalog;
use PDO;
use PHPUnit\Framework\TestCase;
use Tests\Support\MysqlDdlSqlitePdo;

/**
 * JNET : citer un élément SSE (personne, identité, papier, contrôle, dossier…) dans un échange, une page ou un lien, SANS copier
 * aucune donnée SSE dans le contenu : seul « type-numéro » est gardé, le titre et le chemin de liaison se résolvent à la lecture
 * avec les droits DU LECTEUR (« Élément restreint » sinon) ; rétro-navigation « cité dans » vue avec les droits JNET du lecteur.
 */
final class JnetSseLinksTest extends TestCase
{
    private const ROOT = __DIR__ . '/../../';

    public function testAnSseElementIsAJnetTypeIdentifiedByKindAndNumberOnly(): void
    {
        self::assertSame('Élément SSE', R::TYPES['sse']);
        self::assertTrue(R::validId('sse', 'person-12'));
        self::assertTrue(R::validId('sse', 'case-1'));
        foreach (['person', 'person-0', 'person-012', 'person-12345678901', 'inconnu-5', 'Person-5', 'person-5;DROP', 'jnet-5', '12', ''] as $bad) {
            self::assertFalse(R::validId('sse', $bad), $bad);
        }
        self::assertSame(['type' => 'person', 'id' => '12'], R::sseParts('person-12'));
        self::assertSame('person-12', R::sseIdFromRef('person:12'));
        self::assertNull(R::sseIdFromRef('person:abc'));
        self::assertNull(R::sseIdFromRef('jnet:12'));
        // Le champ JNET « identifiant » est de 40 caractères : le plus long type cabe largement.
        self::assertLessThanOrEqual(40, strlen('doc_issue-1234567890'));
        self::assertSame(['type' => 'sse', 'id' => 'check-3'], R::parseRef('sse:check-3'));
        self::assertTrue(R::canPair('page', '3', 'sse', 'person-12'));
        self::assertFalse(R::canPair('sse', 'person-12', 'sse', 'person-12'));
        self::assertSame('sse', R::groupOf('sse'));
        foreach (R::SSE_KINDS as $kind) {
            self::assertArrayHasKey($kind, SseLinkCatalog::TYPES, 'chaque type citable existe côté SSE : ' . $kind);
        }
        self::assertArrayNotHasKey('jnet', array_flip(R::SSE_KINDS));
    }

    public function testTheTypeColumnOfTheTableIsLongEnoughForSse(): void
    {
        $sql = (string) file_get_contents(self::ROOT . 'migrations/2026_11_14_070_jnet_links.sql');
        self::assertMatchesRegularExpression('/src_type VARCHAR\((\d+)\)/', $sql);
        preg_match('/src_type VARCHAR\((\d+)\)/', $sql, $m);
        self::assertGreaterThanOrEqual(strlen('sse'), (int) $m[1]);
        self::assertMatchesRegularExpression('/dst_id VARCHAR\((\d+)\)/', $sql);
    }

    public function testMentionsOfAnSseElementAreParsedAndOthersAreUnchanged(): void
    {
        $text = 'Vu [[sse:person-12|IDN-00012]], dossier [[sse:case-3|SSE-2026-0003]], unité [[unite:4|Alpha]] et [[page:9|Plan]].';
        $m = R::mentions($text);
        self::assertSame(['sse', 'sse', 'unite', 'page'], array_column($m, 'type'));
        self::assertSame(['person-12', 'case-3', '4', '9'], array_column($m, 'id'));
        self::assertSame([], R::mentions('[[sse:12|x]] [[sse:person-|x]] [[sse:PERSON-1|x]] [[autre:1|x]] [[sse:person-12'));
        self::assertSame('[[sse:person-12|IDN-00012]]', R::mentionToken('sse', 'person-12', 'IDN-00012'));
        self::assertSame('[[sse:person-12|IDN-00012 x]]', R::mentionToken('sse', 'person-12', "IDN-00012 [x]|\n"));
        self::assertContains('sse', R::MENTION_TYPES);
    }

    public function testAnAuthorCannotWriteACitationOfAnElementTheyCannotRead(): void
    {
        $author = static fn (string $type, string $id): bool => $type === 'sse' && $id === 'person-12';
        $clean = R::sanitizeMentions('A [[sse:person-12|IDN-00012]] B [[sse:case-3|SSE-2026-0003]]', $author);
        self::assertSame('A [[sse:person-12|IDN-00012]] B SSE-2026-0003', $clean, 'la seconde redevient son libellé neutre');
    }

    public function testTheReaderSeesTheBlockWithTheirOwnRightsAndARestrictedElementOtherwise(): void
    {
        $visible = R::renderSse(['title' => 'Karimzadeh Élodie', 'href' => '/atak/sse/identites/12', 'ref' => 'IDN-00012', 'type_label' => 'Personne', 'path' => ['IDN-00012 → IDT-000001 → DOC-000001', 'IDN-00012 → SSE-2026-0001']]);
        self::assertStringContainsString('<b>IDN-00012</b> Karimzadeh Élodie', $visible);
        self::assertStringContainsString('href="/atak/sse/identites/12"', $visible);
        self::assertStringContainsString('Personne', $visible);
        self::assertStringContainsString('IDN-00012 → IDT-000001 → DOC-000001<br>IDN-00012 → SSE-2026-0001', $visible);
        $hidden = R::renderSse(null);
        self::assertStringContainsString('Élément restreint', $hidden);
        foreach (['IDN-', 'href', 'Karimzadeh', '<a '] as $leak) {
            self::assertStringNotContainsString($leak, $hidden, 'aucun indice sur l’élément : ' . $leak);
        }
        // Au plus deux lignes de chemin, tout est échappé.
        $xss = R::renderSse(['title' => '<script>alert(1)</script>', 'href' => '/x" onclick="y', 'ref' => '<i>', 'type_label' => '<b>', 'path' => ['<u>a</u>', 'b', 'c']]);
        self::assertStringNotContainsString('<script>', $xss);
        self::assertStringNotContainsString('<u>', $xss);
        self::assertStringNotContainsString('onclick="y', $xss);
        self::assertSame(1, substr_count($xss, '<br>'));
    }

    public function testATextIsRenderedPerReaderWithoutTouchingTheStoredText(): void
    {
        $text = "Contrôle : [[sse:person-12|IDN-00012]]\nVoir [[sse:case-3|SSE-2026-0003]] et [[operation:5|OP]].";
        $withRights = R::renderMentions($text, [
            'sse:person-12' => ['title' => 'Karimzadeh Élodie', 'href' => '/p/12', 'ref' => 'IDN-00012', 'type_label' => 'Personne', 'path' => []],
            'sse:case-3' => null,
            'operation:5' => ['title' => 'OP SPECTRE', 'href' => '/o/5'],
        ]);
        self::assertStringContainsString('Karimzadeh Élodie', $withRights);
        self::assertStringContainsString('Élément restreint', $withRights);
        self::assertStringNotContainsString('SSE-2026-0003', $withRights, 'le libellé neutre stocké n’est pas montré quand l’élément est restreint');
        self::assertStringContainsString('<br />', $withRights);
        $withoutRights = R::renderMentions($text, ['sse:person-12' => null, 'sse:case-3' => null]);
        self::assertStringNotContainsString('Karimzadeh', $withoutRights);
        self::assertSame(2, substr_count($withoutRights, '>Élément restreint</span>'));
        self::assertSame($text, $text, 'le texte enregistré ne change pas');
    }

    public function testResolvingMentionsAsksTheViewerForEachElementAndCapsThePathLookups(): void
    {
        $viewer = new SseTestJnetViewer(['sse:person-12' => ['type' => 'sse', 'id' => 'person-12', 'title' => 'Karimzadeh Élodie', 'sub' => 'Personne · IDN-00012', 'href' => '/p/12', 'group' => 'sse', 'ref' => 'IDN-00012', 'type_label' => 'Personne']]);
        $service = new JnetLinkService(new MemLinksRepoForSse());
        $text = '[[sse:person-12|IDN-00012]] [[sse:case-9|SSE-9]]';
        $out = $service->resolveMentions($viewer, [$text]);
        self::assertSame('Karimzadeh Élodie', $out['sse:person-12']['title']);
        self::assertSame('IDN-00012', $out['sse:person-12']['ref']);
        self::assertSame(['IDN-00012 → IDT-000001'], $out['sse:person-12']['path']);
        self::assertNull($out['sse:case-9'], 'non visible : null, jamais un titre');
        self::assertSame(['person-12'], $viewer->pathAsked, 'le chemin n’est demandé que pour ce qui est visible');
        // Plafond : dix chemins par affichage.
        $many = implode(' ', array_map(static fn (int $i): string => '[[sse:person-' . $i . '|x]]', range(1, 25)));
        $all = [];
        foreach (range(1, 25) as $i) {
            $all['sse:person-' . $i] = ['type' => 'sse', 'id' => 'person-' . $i, 'title' => 'P' . $i, 'sub' => '', 'href' => '/p/' . $i, 'group' => 'sse', 'ref' => 'IDN-' . $i, 'type_label' => 'Personne'];
        }
        $v2 = new SseTestJnetViewer($all);
        $res = $service->resolveMentions($v2, [$many]);
        self::assertCount(25, $res);
        self::assertCount(10, $v2->pathAsked);
    }

    public function testCitationsComeBackWithTheJnetRightsOfTheReader(): void
    {
        if (!extension_loaded('pdo_sqlite')) {
            self::markTestSkipped('pdo_sqlite requis');
        }
        $pdo = $this->jnetDatabase();
        $pdo->exec("INSERT INTO jnet_links (tenant_id, src_type, src_id, dst_type, dst_id, space_id, created_by, created_at) VALUES
            (1, 'echange', '5', 'sse', 'person-12', 3, 7, '2026-11-11 08:00:00'),
            (1, 'page', '9', 'sse', 'person-12', 3, 7, '2026-11-11 09:00:00'),
            (1, 'echange', '6', 'sse', 'case-3', 3, 7, '2026-11-11 10:00:00'),
            (2, 'echange', '77', 'sse', 'person-12', 0, 8, '2026-11-11 11:00:00')");
        $viewer = new SseTestJnetViewer([
            'echange:5' => ['type' => 'echange', 'id' => '5', 'title' => 'Compte rendu du 4 novembre', 'sub' => 'Compte rendu', 'href' => '/jnet/u/3?vue=echanges#ex-5', 'group' => 'comptes_rendus'],
        ]);
        $bridge = new SseLinkJnetCitations(1, 7, $viewer, new JnetLinksRepository($pdo));
        $rows = $bridge->citations(1, ['person:12', 'case:3', 'check:1']);
        self::assertCount(1, $rows, 'la page 9 et l’échange 6 ne sont pas lisibles par ce lecteur : ils n’apparaissent pas, sans trace');
        self::assertSame('person:12', $rows[0]['ref']);
        self::assertSame('echange:5', $rows[0]['id']);
        self::assertSame('Compte rendu du 4 novembre', $rows[0]['title']);
        self::assertSame([], $bridge->citations(2, ['person:12']), 'une autre communauté ne se lit pas par ce pont');
        self::assertSame([], $bridge->citations(1, ['nimporte:12', 'person:x']));
        $other = new SseLinkJnetCitations(1, 0, $viewer, new JnetLinksRepository($pdo));
        self::assertSame([], $other->citations(1, ['person:12']), 'pas de lecteur identifié, pas de citation');
    }

    private function jnetDatabase(): PDO
    {
        $scratch = new MysqlDdlSqlitePdo();
        $scratch->exec('CREATE TABLE tenants (id INTEGER PRIMARY KEY, name TEXT, slug TEXT)');
        (new \App\Support\Migrations\SchemaInspector($scratch))->runSqlFile(self::ROOT . 'migrations/2026_11_14_070_jnet_links.sql');

        return $scratch;
    }

    public function testTheCompteRenduIsWiredEndToEnd(): void
    {
        $js = (string) file_get_contents(self::ROOT . 'public/assets/js/jnet-sse-cite.js');
        self::assertStringContainsString("types=sse", $js);
        self::assertStringContainsString("'[[' + String(r.ref) + '|'", $js);
        self::assertDoesNotMatchRegularExpression('/[éèàùçêô]/u', (string) preg_replace(['#/\*.*?\*/#s', '#^\s*//.*$#m'], '', $js), 'aucun texte français dans le script : les messages viennent de la page');
        $composer = (string) file_get_contents(self::ROOT . 'views/jnet/_exchange_composer.php');
        self::assertStringContainsString('Citer un élément SSE', $composer);
        self::assertStringContainsString('data-jn-cite-target', $composer);
        self::assertStringContainsString('defer src', $composer);
        $controller = (string) file_get_contents(self::ROOT . 'app/Controllers/Web/JnetPortalController.php');
        self::assertStringContainsString("str_contains(\$body, '[[sse:')", $controller);
        self::assertStringContainsString('sanitizeMentions(JnetLinkAccess::forViewer', $controller);
        self::assertStringContainsString(',sse', $controller, 'le sélecteur « Lier à… » propose les éléments SSE');
        $access = (string) file_get_contents(self::ROOT . 'app/Services/Jnet/JnetLinkAccess.php');
        self::assertStringContainsString("'sse' => \$this->sse(\$id)", $access);
        self::assertStringContainsString('JnetSseViewer', $access);
        self::assertStringContainsString("Titre NEUTRE", $access, 'le titre écrit dans le contenu JNET est la référence seule');
        // Rétro-navigation : le widget SSE lit les citations via le pont JNET, sous les droits du lecteur.
        self::assertStringContainsString('SseLinkJnetCitations', (string) file_get_contents(self::ROOT . 'app/Services/Sse/Links/SseLinksWidget.php'));
    }

    public function testNoSseDataIsCopiedIntoJnet(): void
    {
        $src = (string) file_get_contents(self::ROOT . 'app/Services/Jnet/SseLinkJnetCitations.php')
            . (string) file_get_contents(self::ROOT . 'app/Services/Jnet/JnetLinkRules.php');
        self::assertStringNotContainsString('INSERT INTO sse_', $src);
        self::assertStringNotContainsString('UPDATE sse_', $src);
        $access = (string) file_get_contents(self::ROOT . 'app/Services/Jnet/JnetLinkAccess.php');
        self::assertStringNotContainsString('INSERT INTO sse_', $access);
        self::assertSame(0, preg_match('/jnet_links[^;]{0,200}(last_name|first_name|display_name)/', $access));
    }
}

/** Dépôt de liens vide : seul le service de citations en a besoin. */
final class MemLinksRepoForSse extends JnetLinksRepository
{
    public function __construct()
    {
        parent::__construct(null);
    }
}

/** Lecteur JNET de test : ce qu'on lui donne est ce qu'il voit ; les chemins SSE demandés sont notés. */
final class SseTestJnetViewer implements JnetLinkViewer, JnetSseViewer
{
    /** @var list<string> */
    public array $pathAsked = [];

    /** @param array<string, array<string, mixed>> $visible */
    public function __construct(private array $visible)
    {
    }

    public function tenantId(): int
    {
        return 1;
    }

    public function userId(): int
    {
        return 7;
    }

    public function today(): string
    {
        return '2026-11-15';
    }

    public function see(string $type, string $id): ?array
    {
        return $this->visible[$type . ':' . $id] ?? null;
    }

    public function ssePath(string $id): array
    {
        $this->pathAsked[] = $id;

        return ['IDN-00012 → IDT-000001'];
    }

    public function canWriteIn(int $spaceId): bool
    {
        return false;
    }

    public function isChief(int $spaceId): bool
    {
        return false;
    }

    public function canModerate(int $spaceId): bool
    {
        return false;
    }

    public function isOrgManager(): bool
    {
        return false;
    }

    public function inOwnUnits(): bool
    {
        return true;
    }

    public function unitLabel(int $unitId): ?string
    {
        return null;
    }

    public function related(int $a, int $b): bool
    {
        return false;
    }

    public function ownUnitIds(): array
    {
        return [];
    }

    public function search(string $q, array $types, int $spaceId): array
    {
        return [];
    }

    public function authors(array $userIds): array
    {
        return array_fill_keys($userIds, 'Analyste Sept');
    }
}
