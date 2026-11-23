<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Repositories\CasNineLineRepository;
use App\Services\Tactical\AirBriefService;
use PDO;
use PHPUnit\Framework\TestCase;

/**
 * Nom libre et référence automatique sur un vrai dépôt sqlite (étape 4980 passée) : références consécutives par jour et par
 * communauté, jamais réutilisées après suppression, nom nettoyé, renommage tant que le brief n'est pas clos, recherche par
 * nom ou référence (dite ou tapée) pour la boîte du pilote et l'accusé, communautés étanches, et repli sans l'étape.
 */
final class AtakBriefNameReferenceDbTest extends TestCase
{
    private const T = 7;

    private const OTHER = 3;

    private PDO $pdo;

    private CasNineLineRepository $repo;

    private AirBriefService $briefs;

    protected function setUp(): void
    {
        parent::setUp();
        if (!extension_loaded('pdo_sqlite')) {
            self::markTestSkipped('pdo_sqlite requis');
        }
        $this->pdo = self::schema();
        $this->migrate($this->pdo);
        $this->repo = new CasNineLineRepository($this->pdo);
        $this->briefs = new AirBriefService($this->pdo, static function (string $m): void {
        });
    }

    private function migrate(PDO $pdo): string
    {
        $root = dirname(__DIR__, 2);
        ob_start();
        require $root . '/database/steps/4980_atak_brief_name_reference.php';

        return (string) ob_get_clean();
    }

    private static function today(): string
    {
        return date('d');
    }

    /** @return array<string, mixed> */
    private function cas(int $tenant = self::T, string $name = '', string $author = 'HAMMER 11'): array
    {
        return $this->repo->createCas($tenant, 1, $author, ['line1' => 'IP DELTA', 'line6' => '0234 5678', 'name' => $name]);
    }

    public function testReferencesFollowEachOtherAndAreNeverReusedAfterADeletion(): void
    {
        $a = $this->cas();
        $b = $this->cas();
        self::assertSame('CAS-' . self::today() . '-01', $a['briefRef']);
        self::assertSame('CAS-' . self::today() . '-02', $b['briefRef']);
        self::assertTrue($this->repo->deleteCas(self::T, (int) $b['id']));
        $c = $this->cas();
        self::assertSame('CAS-' . self::today() . '-03', $c['briefRef'], 'la référence 02 ne sert plus');
    }

    public function testEachCommunityHasItsOwnCounterAndMedevacHasItsPrefix(): void
    {
        $this->cas();
        $other = $this->cas(self::OTHER);
        self::assertSame('CAS-' . self::today() . '-01', $other['briefRef']);
        $med = $this->repo->createMedevac(self::T, 1, 'DUSTOFF 1', ['line1' => '0156 4420']);
        self::assertSame('MED-' . self::today() . '-02', $med['briefRef']);
        $five = $this->repo->createFive(self::T, 1, 'HAMMER 11', ['line1' => 'TA1']);
        self::assertSame('CAS-' . self::today() . '-03', $five['briefRef']);
    }

    public function testAReferenceHeldInTheRegistryIsSkipped(): void
    {
        // Création simultanée : un autre processus a déjà inscrit le numéro suivant au registre.
        $this->pdo->exec("INSERT INTO atak_brief_refs (tenant_id, ref_day, seq, ref, created_at) VALUES (" . self::T . ", '" . date('Y-m-d') . "', 1, 'CAS-" . self::today() . "-01', '2026-01-01 00:00:00')");
        $row = $this->cas();
        self::assertSame('CAS-' . self::today() . '-02', $row['briefRef']);
        self::assertSame(2, (int) $this->pdo->query('SELECT COUNT(*) FROM atak_brief_refs')->fetchColumn());
    }

    public function testNameIsCleanedAndRefusedWhenItLooksLikeAReference(): void
    {
        $row = $this->cas(self::T, "  <b>Pont</b>\n  nord  ");
        self::assertStringNotContainsString('<', $row['briefName']);
        self::assertStringContainsString('Pont', $row['briefName']);
        self::assertSame('', $this->cas(self::T, 'CAS-23-04')['briefName']);
        self::assertSame('', $this->cas(self::T, '42')['briefName']);
        self::assertSame(40, mb_strlen($this->cas(self::T, str_repeat('Z', 90))['briefName']));
    }

    public function testRenameWhileOpenThenFrozenOnceClosed(): void
    {
        $row = $this->cas(self::T, 'Pont nord');
        $id = (int) $row['id'];
        $res = $this->repo->renameCas(self::T, $id, 'Pont sud');
        self::assertSame('ok', $res['result']);
        self::assertSame('Pont sud', $res['row']['briefName']);
        self::assertSame($row['briefRef'], $res['row']['briefRef'], 'la référence ne change jamais');
        self::assertSame('', $this->repo->renameCas(self::T, $id, '')['row']['briefName'], 'nom vide : retiré');
        self::assertSame('invalid', $this->repo->renameCas(self::T, $id, 'CAS-01-01')['result']);
        self::assertSame('not_found', $this->repo->renameCas(self::OTHER, $id, 'X')['result'], 'communauté étanche');
        $this->repo->updateCasStatus(self::T, $id, 'COMPLETE');
        self::assertSame('closed', $this->repo->renameCas(self::T, $id, 'Trop tard')['result']);
    }

    public function testRenameIsRefusedWhenAPilotClosedTheMission(): void
    {
        $row = $this->cas();
        $this->briefs->addRecipients(self::T, (int) $row['id'], ['TIGER 6']);
        $this->briefs->acknowledge(self::T, (string) $row['id'], 'done', 'TIGER 6');
        self::assertSame('closed', $this->repo->renameCas(self::T, (int) $row['id'], 'Trop tard')['result']);
    }

    public function testPilotAndJtacFindTheBriefByNameOrByReference(): void
    {
        $row = $this->cas(self::T, 'Pont nord');
        $ref = (string) $row['briefRef'];
        $spoken = strtolower(str_replace('-', ' ', $ref));
        foreach ([$ref, $spoken, 'Pont nord', 'pont-nord', 'PONT NORD', (string) $row['id']] as $said) {
            $found = $this->briefs->resolveLine(self::T, $said);
            self::assertNotNull($found, $said);
            self::assertSame((int) $row['id'], (int) $found['id'], $said);
        }
        self::assertNull($this->briefs->resolveLine(self::T, 'Pont ouest'));
        self::assertNull($this->briefs->resolveLine(self::OTHER, $ref), 'communauté étanche');
    }

    public function testSeveralBriefsWithTheSameNameKeepTheLatestOpenOne(): void
    {
        $old = $this->cas(self::T, 'Pont nord');
        $new = $this->cas(self::T, 'Pont nord');
        self::assertSame((int) $new['id'], (int) $this->briefs->resolveLine(self::T, 'Pont nord')['id']);
        $this->repo->updateCasStatus(self::T, (int) $new['id'], 'COMPLETE');
        self::assertSame((int) $old['id'], (int) $this->briefs->resolveLine(self::T, 'Pont nord')['id']);
    }

    public function testPilotAcknowledgesByNameOrReferenceAndWilcoFormatIsUnchanged(): void
    {
        $row = $this->cas(self::T, 'Pont nord');
        $this->briefs->addRecipients(self::T, (int) $row['id'], ['HOG 21']);
        self::assertSame(['wilco', 'HOG 21'], AirBriefService::parseAck('WILCO:HOG 21'));
        $byName = $this->briefs->acknowledge(self::T, 'Pont nord', 'wilco', 'HOG 21');
        self::assertSame('ok', $byName['result']);
        self::assertSame('wilco', $byName['recipient']['status']);
        $byRef = $this->briefs->acknowledge(self::T, (string) $row['briefRef'], 'read', 'HOG 21');
        self::assertSame('ok', $byRef['result'], 'un accusé « lu » ne fait pas reculer le WILCO');
        $delivery = $this->briefs->lineDelivery(self::T, (string) $row['briefRef']);
        self::assertSame([$row['briefRef'], 'Pont nord'], [$delivery['brief_ref'], $delivery['brief_name']]);
    }

    public function testPilotInboxCarriesNameAndReferenceInTheSqfArray(): void
    {
        $row = $this->cas(self::T, 'Pont nord');
        $this->briefs->addRecipients(self::T, (int) $row['id'], ['TIGER 6']);
        $box = $this->briefs->inbox(self::T, ['TIGER6']);
        self::assertCount(1, $box);
        self::assertSame(['Pont nord', $row['briefRef']], [$box[0]['brief_name'], $box[0]['brief_ref']]);
        $sqf = AirBriefService::inboxSqf($box);
        self::assertStringContainsString(',"","Pont nord","' . $row['briefRef'] . '"]]', $sqf);
        // Les neuf premières colonnes lues par la DLL gardent leur place.
        self::assertStringContainsString('"9line"', $sqf);
        self::assertStringContainsString('"TIGER 6"', $sqf);
    }

    public function testWithoutTheStepBriefsStillWorkWithoutNameOrReference(): void
    {
        $pdo = self::schema();
        $repo = new CasNineLineRepository($pdo);
        $row = $repo->createCas(self::T, 1, 'HAMMER 11', ['line1' => 'IP', 'name' => 'Pont nord']);
        self::assertGreaterThan(0, (int) $row['id']);
        self::assertSame('', $row['briefRef']);
        self::assertSame('unavailable', $repo->renameCas(self::T, (int) $row['id'], 'X')['result']);
        self::assertNull((new AirBriefService($pdo, static function (string $m): void {
        }))->resolveLine(self::T, 'Pont nord'));
    }

    public function testStepAddsColumnsIndexAndRegistryOnceAndCanBeRunAgain(): void
    {
        $pdo = self::schema();
        $first = $this->migrate($pdo);
        $second = $this->migrate($pdo);
        self::assertStringContainsString('brief_name', $first);
        self::assertStringContainsString('atak_brief_refs', $first);
        self::assertStringContainsString('déjà en place', $second);
        $cols = array_column($pdo->query('PRAGMA table_info(atak_nine_line)')->fetchAll(PDO::FETCH_ASSOC), 'name');
        self::assertContains('brief_ref', $cols);
        self::assertContains('brief_name', $cols);
    }

    private static function schema(): PDO
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->sqliteCreateFunction('NOW', static fn (): string => date('Y-m-d H:i:s'), 0);
        $pdo->exec("CREATE TABLE atak_nine_line (id INTEGER PRIMARY KEY AUTOINCREMENT, tenant_id INTEGER NOT NULL, map_id INTEGER NOT NULL DEFAULT 1,
            mission_kind TEXT NOT NULL DEFAULT 'cas', mission_id TEXT, author TEXT NOT NULL, author_user_id INTEGER, assigned_aircraft TEXT,
            line1 TEXT, line2 TEXT, line3 TEXT, line4 TEXT, line5 TEXT, line6 TEXT, line7 TEXT, line8 TEXT, line9 TEXT, remarks TEXT,
            lines_checked TEXT, geo_json TEXT, status TEXT, created_at TEXT DEFAULT CURRENT_TIMESTAMP, updated_at TEXT)");
        $pdo->exec("CREATE TABLE atak_nine_line_recipients (id INTEGER PRIMARY KEY AUTOINCREMENT, tenant_id INTEGER NOT NULL, nine_line_id INTEGER NOT NULL,
            callsign TEXT NOT NULL, label TEXT, crew_names TEXT, status TEXT NOT NULL DEFAULT 'sent', received_at TEXT, read_at TEXT, ack_at TEXT,
            ack_by TEXT, polled_as TEXT, last_polled_at TEXT, created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at TEXT, UNIQUE (nine_line_id, callsign))");

        return $pdo;
    }
}
