<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Repositories\OperatorHubRepository;
use App\Services\Operator\OperatorIdentityService;
use App\Services\Sse\MemberIdentityPapersService;
use PDO;
use PHPUnit\Framework\TestCase;
use Tests\Support\SseIdentitySqlite;

/**
 * Mes identités (outils opérateur) : complétude d'une identité, propriété (ni autre membre, ni autre communauté),
 * transitions d'état des papiers, type et histoire d'une identité, synthèse de l'accueil, journal.
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
        return new OperatorIdentityService(null, null, null, null, null, null, $this->audit());
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

    // ------------------------------------------------------------------ Type, histoire, synthèse

    public function testTypeIsOnlyCharacterOrBorrowedAndStoryNeedsItsStep(): void
    {
        $s = $this->service();
        $primary = (int) $s->saveIdentity(1, 10, null, $this->jake() + ['story' => 'Né à Kavala.'], $this->now)['id'];
        $alias = (int) $s->saveIdentity(1, 10, null, $this->jake(['last_name' => 'Kostas', 'first_name' => 'Nikos']), $this->now)['id'];
        // Identité montée par un responsable (falsifiée) : le membre la voit comme une identité d'emprunt, rien de plus.
        $forged = $this->papers()->createAlias(1, 10, ['kind' => 'cover', 'last_name' => 'Petrov', 'first_name' => 'Ivan'], true, 99);
        $this->pdo->exec("UPDATE sse_identities SET kind = 'forged' WHERE id = " . (int) $forged['id']);
        $list = $s->identities(1, 10);
        self::assertSame(['personnage', 'emprunt', 'emprunt'], array_column($list, 'type'));
        self::assertSame(['Personnage', 'D’emprunt', 'D’emprunt'], array_column($list, 'type_label'));
        foreach ($list as $i) {
            self::assertArrayNotHasKey('kind', $i, 'jamais la nature exacte');
            self::assertArrayNotHasKey('kind_label', $i);
        }
        // Étape 4450 pas passée : pas de champ histoire, rien n'est écrit, aucune erreur.
        self::assertFalse($s->storyAvailable());
        self::assertSame('', $list[0]['story']);
        self::assertNull($list[0]['game_synced_at']);

        (require dirname(__DIR__, 2) . '/bootstrap/sse_identities_unified_migration.php')($this->pdo);
        $s = $this->service();
        self::assertTrue($s->storyAvailable());
        self::assertTrue($s->saveIdentity(1, 10, $primary, $this->jake() + ['story' => "Né à Kavala.\nMécanicien."], $this->now)['ok']);
        self::assertSame("Né à Kavala.\nMécanicien.", $s->identities(1, 10)[0]['story']);
        // Clé absente : histoire inchangée ; histoire trop longue : refusée.
        self::assertTrue($s->saveIdentity(1, 10, $primary, $this->jake(), $this->now)['ok']);
        self::assertSame("Né à Kavala.\nMécanicien.", $s->identities(1, 10)[0]['story']);
        self::assertFalse($s->saveIdentity(1, 10, $primary, $this->jake() + ['story' => str_repeat('a', OperatorIdentityService::STORY_MAX + 1)], $this->now)['ok']);
        // L'histoire d'une identité ne s'écrit pas par un autre membre ni depuis une autre communauté.
        self::assertFalse($s->saveIdentity(1, 11, $alias, $this->jake() + ['story' => 'Volée'], $this->now)['ok']);
        self::assertFalse($s->saveIdentity(2, 10, $alias, $this->jake() + ['story' => 'Volée'], $this->now)['ok']);
        self::assertSame(1, (int) $this->pdo->query('SELECT COUNT(*) FROM sse_identity_stories')->fetchColumn());
    }

    public function testSummaryFeedsTheHubAndEveryWriteIsLogged(): void
    {
        $s = $this->service();
        $primary = (int) $s->saveIdentity(1, 10, null, $this->jake(), $this->now)['id'];
        $s->saveIdentity(1, 10, null, $this->jake(['last_name' => 'Kostas', 'first_name' => 'Nikos']), $this->now);
        $s->requestDocument(1, 10, $primary, 'id_card', 'ALT');
        $sum = $s->summary(1, 10);
        self::assertTrue($sum['has_primary']);
        self::assertSame(1, $sum['identity_missing']);
        self::assertSame(2, $sum['identities']);
        self::assertSame('Jake GYLLEN', $sum['active_name']);
        self::assertSame(1, $sum['papers_pending']);
        self::assertSame(0, $sum['papers_valid']);
        self::assertSame(['identity_missing' => 0, 'has_primary' => false, 'identities' => 0, 'active_name' => null, 'papers_valid' => 0, 'papers_attention' => 0, 'papers_pending' => 0], $s->summary(1, 11));

        // Papiers habituels que l'identité n'a pas encore : celui déjà demandé n'est plus proposé.
        $countries = [];
        foreach ($s->countries(1) as $c) {
            $countries[(string) $c['code']] = $c;
        }
        $missing = OperatorIdentityService::missingPapers($s->identities(1, 10)[0], $s->documents(1, 10), $countries);
        self::assertNotContains('id_card', array_column($missing, 'type'));

        // Chaque écriture est journalisée au nom du membre.
        foreach ($this->audit as $e) {
            self::assertSame(1, $e['tenant_id']);
        }
        self::assertContains('op_identity.identity_create', array_column($this->audit, 'action'));
        self::assertContains('op_identity.document_request', array_column($this->audit, 'action'));
    }
}
