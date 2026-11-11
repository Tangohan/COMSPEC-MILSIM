<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Repositories\OperatorHubRepository;
use App\Repositories\SseCoverExportRepository;
use App\Services\Operator\OperatorIdentityService;
use App\Services\Sse\MemberIdentityPapersService;
use App\Services\Sse\SseCoverExportService;
use App\Services\Sse\SseCoverSyncService;
use PDO;
use PHPUnit\Framework\TestCase;
use Tests\Support\SseIdentitySqlite;

/**
 * Mon identité (outils opérateur) : complétude d'une identité, propriété (ni autre membre, ni autre communauté),
 * transitions d'état des papiers, couverture active, retouche et aller-retour du fichier, révocation, journal.
 */
final class OperatorIdentityServiceTest extends TestCase
{
    private PDO $pdo;
    private \DateTimeImmutable $now;
    /** @var list<array<string, mixed>> */
    private array $audit = [];

    protected function setUp(): void
    {
        if (!extension_loaded('pdo_sqlite')) {
            self::markTestSkipped('pdo_sqlite requis');
        }
        $this->pdo = SseIdentitySqlite::open();
        $root = dirname(__DIR__, 2);
        (require $root . '/bootstrap/sse_cover_exports_migration.php')($this->pdo);
        (require $root . '/bootstrap/sse_files_records_migration.php')($this->pdo);
        (require $root . '/bootstrap/operator_hub_migration.php')($this->pdo);
        $this->pdo->exec("INSERT INTO users (id, tenant_id, steam_id, display_name) VALUES (10, 1, '76561198000000010', 'Jake'), (11, 1, NULL, 'Autre membre'), (20, 2, NULL, 'Autre communauté')");
        $this->now = new \DateTimeImmutable('2026-11-11 12:00:00', new \DateTimeZone('UTC'));
        $this->audit = [];
    }

    protected function tearDown(): void
    {
        if (extension_loaded('pdo_sqlite')) {
            SseIdentitySqlite::close();
        }
    }

    private function audit(): \Closure
    {
        return function (array $e): void {
            $this->audit[] = $e;
        };
    }

    private function service(): OperatorIdentityService
    {
        $exports = new SseCoverExportRepository();

        return new OperatorIdentityService(null, null, null, null, null, null, $exports, new SseCoverExportService($exports, null, null, $this->audit(), 'test-key'), $this->audit());
    }

    private function papers(): MemberIdentityPapersService
    {
        return new MemberIdentityPapersService(null, null, null, null, null, $this->audit());
    }

    /** @return array<string, mixed> */
    private function jake(array $over = []): array
    {
        return $over + ['last_name' => 'Gyllen', 'first_name' => 'Jake', 'sex' => 'M', 'birth_date' => '1990-04-02', 'birth_place' => 'Kavala', 'nationality' => 'ALT',
            'height_cm' => '182', 'weight_kg' => '80', 'eyes' => 'blue', 'hair' => 'brown', 'marks' => 'Cicatrice au sourcil gauche'];
    }

    // ------------------------------------------------------------------ Identités

    public function testCompletenessListsEachMissingField(): void
    {
        $empty = OperatorIdentityService::completeness(['last_name' => 'Gyllen', 'first_name' => 'Jake', 'sex' => 'X'], []);
        self::assertSame(2, $empty['done']);
        self::assertSame(10, $empty['total']);
        self::assertSame(20, $empty['percent']);
        self::assertSame(['sex', 'birth_date', 'birth_place', 'nationality', 'photo', 'height_cm', 'eyes', 'hair'], array_column($empty['missing'], 'key'));

        $full = OperatorIdentityService::completeness(
            ['last_name' => 'Gyllen', 'first_name' => 'Jake', 'sex' => 'M', 'birth_date' => '1990-04-02', 'birth_place' => 'Kavala', 'nationality' => 'ALT', 'photo_path' => 'uploads/x.jpg'],
            ['height_cm' => 182, 'eyes' => 'blue', 'hair' => 'brown']
        );
        self::assertSame([], $full['missing']);
        self::assertSame(100, $full['percent']);
    }

    public function testFirstIdentityIsPrimaryThenAliasesWithALimit(): void
    {
        $s = $this->service();
        $res = $s->saveIdentity(1, 10, null, $this->jake(), $this->now);
        self::assertTrue($res['ok'], (string) ($res['error'] ?? ''));
        $list = $s->identities(1, 10);
        self::assertCount(1, $list);
        self::assertTrue($list[0]['is_primary']);
        self::assertTrue($list[0]['active'], 'sans kit, l’identité principale est celle envoyée en jeu');
        self::assertSame(182, $list[0]['profile']['height_cm']);
        self::assertSame(['photo'], array_column($list[0]['completeness']['missing'], 'key'));
        // Le membre ne lit jamais la nature de l'identité (vérité de jeu).
        self::assertArrayNotHasKey('kind', $list[0]);
        self::assertArrayNotHasKey('kind_label', $list[0]);

        for ($n = 1; $n <= OperatorIdentityService::MAX_ALIASES; $n++) {
            self::assertTrue($s->saveIdentity(1, 10, null, $this->jake(['last_name' => 'Markos' . str_repeat('a', $n), 'first_name' => 'Petros']), $this->now)['ok']);
        }
        $over = $s->saveIdentity(1, 10, null, $this->jake(['last_name' => 'Trop', 'first_name' => 'Plein']), $this->now);
        self::assertFalse($over['ok']);
        self::assertStringContainsString('identités d’emprunt', (string) $over['error']);
        self::assertContains('op_identity.identity_create', array_column($this->audit, 'action'));
    }

    public function testValidationRefusesBadValues(): void
    {
        $s = $this->service();
        foreach ([
            ['last_name' => ''], ['first_name' => 'J4ke<script>'], ['birth_date' => '2020-01-01'], ['birth_date' => '1990-13-40'], ['nationality' => 'ZZZ'],
            ['height_cm' => '300'], ['weight_kg' => 'abc'], ['eyes' => 'violet'], ['marks' => str_repeat('x', 301)], ['sex' => 'Q'],
        ] as $bad) {
            self::assertFalse($s->saveIdentity(1, 10, null, $this->jake($bad), $this->now)['ok'], (string) json_encode($bad));
        }
        self::assertSame([], $s->identities(1, 10));
    }

    public function testAMemberCannotTouchAnotherMembersOrCommunitysIdentity(): void
    {
        $s = $this->service();
        $mine = (int) $s->saveIdentity(1, 10, null, $this->jake(), $this->now)['id'];
        $alias = (int) $s->saveIdentity(1, 10, null, $this->jake(['last_name' => 'Markos', 'first_name' => 'Petros']), $this->now)['id'];

        foreach ([[1, 11], [2, 10], [2, 20]] as [$tenant, $user]) {
            self::assertFalse($s->saveIdentity($tenant, $user, $mine, $this->jake(['last_name' => 'Vole']), $this->now)['ok']);
            self::assertNull($s->ownedIdentity($tenant, $user, $alias));
            self::assertFalse($s->archiveIdentity($tenant, $user, $alias, true)['ok']);
            self::assertFalse($s->activateIdentity($tenant, $user, $alias)['ok']);
            self::assertFalse($s->setPhoto($tenant, $user, $mine, 'uploads/x.jpg'));
            self::assertFalse($s->requestDocument($tenant, $user, $mine, 'id_card', 'ALT')['ok']);
            self::assertSame([], $s->identities($tenant, $user));
        }
        self::assertSame('Gyllen', $s->identities(1, 10)[0]['last_name']);
    }

    public function testArchiveRulesAndCivilFieldsLockedOncePapersAreIssued(): void
    {
        $s = $this->service();
        $primary = (int) $s->saveIdentity(1, 10, null, $this->jake(), $this->now)['id'];
        $alias = (int) $s->saveIdentity(1, 10, null, $this->jake(['last_name' => 'Markos', 'first_name' => 'Petros']), $this->now)['id'];
        self::assertFalse($s->archiveIdentity(1, 10, $primary, true)['ok'], 'l’identité principale ne s’archive pas');
        self::assertTrue($s->archiveIdentity(1, 10, $alias, true)['ok']);
        self::assertNull($s->ownedIdentity(1, 10, $alias), 'archivée : plus de papier ni de modification');
        self::assertFalse($s->saveIdentity(1, 10, $alias, $this->jake(['last_name' => 'Markos', 'first_name' => 'Petros']), $this->now)['ok']);
        self::assertTrue($s->archiveIdentity(1, 10, $alias, false)['ok']);
        self::assertNotNull($s->ownedIdentity(1, 10, $alias));

        // Papier émis par un responsable : l'état civil ne bouge plus, le signalement oui.
        $doc = (int) $s->requestDocument(1, 10, $primary, 'id_card', 'ALT')['id'];
        $papers = $this->papers();
        self::assertTrue($papers->action(1, 10, $papers->ownedDocument(1, 10, $doc, true), 'issue', '', 99)['ok']);
        self::assertTrue($s->saveIdentity(1, 10, $primary, $this->jake(['last_name' => 'Autre', 'height_cm' => '190']), $this->now)['ok']);
        $after = $s->identities(1, 10)[0];
        self::assertSame('Gyllen', $after['last_name']);
        self::assertTrue($after['civil_locked']);
        self::assertSame(190, $after['profile']['height_cm']);
    }

    // ------------------------------------------------------------------ Papiers

    public function testDocumentStatusAndAllowedTransitions(): void
    {
        $doc = ['workflow' => 'issued', 'st_validity' => 'valid', 'st_report' => 'none', 'expires_on' => '2030-01-01', 'notes' => null];
        self::assertSame('valid', OperatorIdentityService::docStatus($doc, '2026-11-11'));
        self::assertSame('expired', OperatorIdentityService::docStatus(['expires_on' => '2026-11-10'] + $doc, '2026-11-11'));
        self::assertSame('lost', OperatorIdentityService::docStatus(['st_report' => 'lost'] + $doc, '2026-11-11'));
        self::assertSame('stolen', OperatorIdentityService::docStatus(['st_report' => 'stolen', 'expires_on' => '2020-01-01'] + $doc, '2026-11-11'));
        self::assertSame('revoked', OperatorIdentityService::docStatus(['workflow' => 'revoked'] + $doc, '2026-11-11'));
        self::assertSame('suspended', OperatorIdentityService::docStatus(['st_validity' => 'suspended'] + $doc, '2026-11-11'));
        self::assertSame('pending', OperatorIdentityService::docStatus(['workflow' => 'validated'] + $doc, '2026-11-11'));
        self::assertSame('requested', OperatorIdentityService::docStatus(['workflow' => 'draft', 'notes' => OperatorIdentityService::REQUEST_NOTE] + $doc));
        self::assertNull(OperatorIdentityService::docStatus(['workflow' => 'draft'] + $doc), 'brouillon d’un responsable : invisible du membre');

        self::assertSame('lost', OperatorIdentityService::docTransition('valid', 'lost'));
        self::assertSame('stolen', OperatorIdentityService::docTransition('expired', 'stolen'));
        self::assertNull(OperatorIdentityService::docTransition('requested', 'cancel'));
        foreach ([['requested', 'lost'], ['pending', 'cancel'], ['lost', 'stolen'], ['stolen', 'lost'], ['valid', 'cancel'], ['suspended', 'renew'], ['valid', 'issue'], ['valid', 'clear_report']] as [$status, $action]) {
            self::assertFalse(OperatorIdentityService::docTransition($status, $action), $status . ' → ' . $action);
        }
    }

    public function testRequestIssueLoseAndRenewADocument(): void
    {
        $s = $this->service();
        $primary = (int) $s->saveIdentity(1, 10, null, $this->jake(), $this->now)['id'];
        $req = $s->requestDocument(1, 10, $primary, 'id_card', 'ALT');
        self::assertTrue($req['ok'], (string) ($req['error'] ?? ''));
        $docId = (int) $req['id'];
        self::assertFalse($s->requestDocument(1, 10, $primary, 'id_card', 'ALT')['ok'], 'même papier déjà demandé');
        self::assertFalse($s->requestDocument(1, 10, $primary, 'nope', 'ALT')['ok']);
        self::assertFalse($s->requestDocument(1, 10, $primary, 'id_card', 'ZZZ')['ok']);

        $view = $s->documents(1, 10);
        self::assertCount(1, $view);
        self::assertSame('requested', $view[0]['status']);
        self::assertSame(['cancel'], $view[0]['actions']);
        self::assertNotSame('', $view[0]['doc_number']);
        // Jamais l'origine ni la falsification dans ce que lit le membre.
        foreach (['st_origin', 'forgery_kind', 'forgery_quality', 'identity'] as $secret) {
            self::assertArrayNotHasKey($secret, $view[0]);
        }
        // Une demande n'est pas un papier émis : rien à déclarer perdu.
        self::assertFalse($s->documentAction(1, 10, $docId, 'lost')['ok']);

        // Un responsable émet ; le membre déclare la perte (signalée aux fichiers par st_report), puis renouvelle.
        $papers = $this->papers();
        self::assertTrue($papers->action(1, 10, $papers->ownedDocument(1, 10, $docId, true), 'issue', '', 99)['ok']);
        self::assertSame('valid', $s->documents(1, 10)[0]['status']);
        self::assertFalse($s->documentAction(1, 11, $docId, 'lost')['ok'], 'autre membre');
        self::assertFalse($s->documentAction(2, 10, $docId, 'lost')['ok'], 'autre communauté');
        self::assertTrue($s->documentAction(1, 10, $docId, 'lost')['ok']);
        self::assertSame('lost', (string) $this->pdo->query('SELECT st_report FROM sse_id_documents WHERE id = ' . $docId)->fetchColumn());
        self::assertFalse($s->documentAction(1, 10, $docId, 'stolen')['ok'], 'déjà signalé : seul un responsable lève le signalement');

        self::assertTrue($s->documentAction(1, 10, $docId, 'renew')['ok']);
        self::assertFalse($s->documentAction(1, 10, $docId, 'renew')['ok'], 'un seul renouvellement ouvert');
        $statuses = array_column($s->documents(1, 10), 'status', 'id');
        self::assertSame('lost', $statuses[$docId]);
        self::assertContains('requested', $statuses);
        $renewal = (int) array_search('requested', $statuses, true);
        self::assertTrue($s->documentAction(1, 10, $renewal, 'cancel')['ok']);
        self::assertCount(1, $s->documents(1, 10));
        self::assertNull($s->ownedDocumentView(1, 11, $docId));
        self::assertNotNull($s->ownedDocumentView(1, 10, $docId));
    }

    public function testPendingRequestsAreLimited(): void
    {
        $s = $this->service();
        $primary = (int) $s->saveIdentity(1, 10, null, $this->jake(), $this->now)['id'];
        $ok = 0;
        foreach (['id_card', 'passport', 'driving_license', 'military_id'] as $type) {
            $ok += $s->requestDocument(1, 10, $primary, $type, 'ALT')['ok'] ? 1 : 0;
        }
        self::assertLessThanOrEqual(OperatorIdentityService::MAX_PENDING_REQUESTS, $ok);
        self::assertGreaterThanOrEqual(1, $ok);
    }

    // ------------------------------------------------------------------ Couvertures

    public function testCoverActiveSwitchEditAndExportRoundTrip(): void
    {
        $s = $this->service();
        $a = $s->generateCover(1, 10, 'random', 'ALT', 'F', 'fr', null, $this->now);
        self::assertTrue($a['ok'], (string) ($a['error'] ?? ''));
        $b = $s->generateCover(1, 10, 'random', 'ALT', 'M', 'fr', null, $this->now);
        self::assertTrue($b['ok']);

        $covers = array_column($s->covers(1, 10, $this->now), null, 'id');
        self::assertTrue($covers[$a['id']]['active'], 'la première couverture devient active');
        self::assertFalse($covers[$b['id']]['active']);
        self::assertSame('never', $covers[$a['id']]['sync']);

        // Bascule : une seule active.
        self::assertTrue($s->activateCover(1, 10, (int) $b['id'], $this->now)['ok']);
        $covers = array_column($s->covers(1, 10, $this->now), null, 'id');
        self::assertFalse($covers[$a['id']]['active']);
        self::assertTrue($covers[$b['id']]['active']);
        self::assertSame((int) $b['id'], $s->covers(1, 10, $this->now)[0]['id'], 'l’active en tête');

        // Téléchargement, puis retouche : le fichier reste valable, sous la même référence, et une mise à jour est signalée.
        $dl = $s->downloadCover(1, 10, (int) $b['id'], $this->now);
        self::assertNotNull($dl);
        self::assertSame('synced', array_column($s->covers(1, 10, $this->now), 'sync', 'id')[$b['id']]);
        $edit = $s->editCover(1, 10, (int) $b['id'], ['profession' => 'Photographe de presse', 'history' => "A grandi à Kavala.\nCouvre la région depuis deux ans."], $this->now->modify('+1 hour'));
        self::assertTrue($edit['ok'], (string) ($edit['error'] ?? ''));
        $after = array_column($s->covers(1, 10, $this->now->modify('+1 hour')), null, 'id')[$b['id']];
        self::assertSame('Photographe de presse', $after['profession']);
        self::assertSame(['A grandi à Kavala.', 'Couvre la région depuis deux ans.'], $after['history']);
        self::assertSame('update', $after['sync']);
        self::assertSame($covers[$b['id']]['name'], $after['name'], 'le nom ne change pas');
        self::assertSame($covers[$b['id']]['expires_at'], $after['expires_at'], 'la date d’expiration ne bouge pas');

        $file = $s->downloadCover(1, 10, (int) $b['id'], $this->now->modify('+2 hours'));
        self::assertNotNull($file);
        $parsed = SseCoverExportService::parse($file['json'], $this->now);
        self::assertTrue($parsed['ok'], 'aller-retour : le fichier retouché est relu par le contrat commun au site et au mod');
        self::assertSame('Photographe de presse', $parsed['cover']['profession']);
        self::assertSame($b['ref'], $parsed['file']['ref']);
        $export = new SseCoverExportService(new SseCoverExportRepository(), null, null, $this->audit(), 'test-key');
        self::assertTrue($export->verifySignature($parsed['file']), 'signature du serveur valable après retouche');
        // Le téléphone reçoit le même contenu.
        $sync = (new SseCoverSyncService(new SseCoverExportRepository(), $export))->filesFor(1, 10, [$b['ref']], $this->now);
        self::assertSame('Photographe de presse', $sync['files'][0]['cover']['profession']);

        // Refus : texte trop long, caractères de contrôle, trop de lignes.
        self::assertFalse($s->editCover(1, 10, (int) $b['id'], ['profession' => str_repeat('x', 121)])['ok']);
        self::assertFalse($s->editCover(1, 10, (int) $b['id'], ['city' => "Kav\x07ala"])['ok']);
        self::assertFalse($s->editCover(1, 10, (int) $b['id'], ['history' => implode("\n", array_fill(0, 9, 'ligne'))])['ok']);
    }

    public function testCoversAreNeverReachableByAnotherMemberOrCommunity(): void
    {
        $s = $this->service();
        $mine = $s->generateCover(1, 10, 'random', 'ALT', 'F', 'fr', null, $this->now);
        self::assertTrue($mine['ok']);
        $id = (int) $mine['id'];
        foreach ([[1, 11], [2, 10], [2, 20]] as [$tenant, $user]) {
            self::assertSame([], $s->covers($tenant, $user, $this->now));
            self::assertNull($s->downloadCover($tenant, $user, $id, $this->now));
            self::assertFalse($s->editCover($tenant, $user, $id, ['profession' => 'Espion'])['ok']);
            self::assertFalse($s->activateCover($tenant, $user, $id)['ok']);
            self::assertFalse($s->duplicateCover($tenant, $user, $id, $this->now)['ok']);
            self::assertFalse($s->revokeCover($tenant, $user, $id)['ok']);
        }
        self::assertSame('valid', $s->covers(1, 10, $this->now)[0]['status']);
    }

    public function testRevokedCoverIsNoLongerServedAndManagerCoversAreReadOnly(): void
    {
        $s = $this->service();
        $exports = new SseCoverExportRepository();
        $export = new SseCoverExportService($exports, null, null, $this->audit(), 'test-key');
        $sync = new SseCoverSyncService($exports, $export);
        $mine = $s->generateCover(1, 10, 'random', 'ALT', 'F', 'fr', null, $this->now);
        $byManager = $export->generate(1, 10, 99, 'ALT', 'M', 'fr', null, true, $this->now);
        self::assertTrue($byManager['ok']);

        $view = array_column($s->covers(1, 10, $this->now), null, 'id');
        self::assertTrue($view[$byManager['id']]['by_manager']);
        self::assertFalse($view[$byManager['id']]['editable']);
        self::assertFalse($s->editCover(1, 10, (int) $byManager['id'], ['profession' => 'Autre'])['ok'], 'préparée par un responsable : lecture seule');

        self::assertCount(2, $sync->listFor(1, 10, $this->now));
        self::assertTrue($s->revokeCover(1, 10, (int) $mine['id'], $this->now)['ok']);
        $view = array_column($s->covers(1, 10, $this->now), null, 'id');
        self::assertSame('revoked', $view[$mine['id']]['status']);
        self::assertFalse($view[$mine['id']]['active']);
        self::assertNull($s->downloadCover(1, 10, (int) $mine['id'], $this->now), 'site : plus téléchargeable');
        self::assertSame([$byManager['ref']], array_column($sync->listFor(1, 10, $this->now), 'ref'), 'téléphone : absente de la liste');
        self::assertSame('not_found', $sync->filesFor(1, 10, [$mine['ref']], $this->now)['errors'][0]['error']);
        self::assertFalse($s->activateCover(1, 10, (int) $mine['id'], $this->now)['ok']);
        // La révocation d'un membre ne touche pas les couvertures d'un autre.
        self::assertSame([], OperatorHubRepository::revokedCoverIds(1, 11));
    }

    public function testDuplicateCountsInTheDailyLimitAndSummaryFeedsTheHub(): void
    {
        $s = $this->service();
        $primary = (int) $s->saveIdentity(1, 10, null, $this->jake(), $this->now)['id'];
        $first = $s->generateCover(1, 10, 'random', 'ALT', 'F', 'fr', null, $this->now);
        $copy = $s->duplicateCover(1, 10, (int) $first['id'], $this->now);
        self::assertTrue($copy['ok'], (string) ($copy['error'] ?? ''));
        self::assertNotSame($first['id'], $copy['id']);
        self::assertSame(1, $s->coverQuota(1, 10, $this->now));
        self::assertTrue($s->duplicateCover(1, 10, (int) $first['id'], $this->now)['ok']);
        self::assertFalse($s->duplicateCover(1, 10, (int) $first['id'], $this->now)['ok'], 'limite sur 24 heures');
        self::assertFalse($s->generateCover(1, 10, 'random', 'ALT', 'F', 'fr', null, $this->now)['ok']);

        $s->requestDocument(1, 10, $primary, 'id_card', 'ALT');
        $sum = $s->summary(1, 10);
        self::assertTrue($sum['has_primary']);
        self::assertSame(1, $sum['identity_missing']);
        self::assertSame(1, $sum['papers_pending']);
        self::assertSame(0, $sum['papers_valid']);
        self::assertSame(3, $sum['covers']);
        self::assertNotNull($sum['cover_active']);
        self::assertSame(['identity_missing' => 0, 'has_primary' => false, 'papers_valid' => 0, 'papers_attention' => 0, 'papers_pending' => 0, 'cover_active' => null, 'covers' => 0], $s->summary(1, 11));

        // Chaque écriture est journalisée au nom du membre.
        foreach ($this->audit as $e) {
            self::assertSame(1, $e['tenant_id']);
        }
        self::assertContains('op_identity.cover_duplicate', array_column($this->audit, 'action'));
        self::assertContains('op_identity.document_request', array_column($this->audit, 'action'));
    }
}
