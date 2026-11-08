<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Repositories\SseIdCountryRepository;
use App\Repositories\SseIdDocumentRepository;
use App\Repositories\SseIdentityRepository;
use App\Repositories\SseIdKitRepository;
use App\Repositories\SsePersonRepository;
use App\Repositories\SseWatchlistRepository;
use App\Services\Sse\MemberIdentityPapersService;
use App\Services\Sse\SseIdentityPackService;
use PDO;
use PHPUnit\Framework\TestCase;
use Tests\Support\SseIdentitySqlite;

/**
 * Fiche membre › Identité & papiers : droits, vérité de couverture masquée, lecture par le joueur, actions,
 * kit de mission dans le pack du serveur de jeu (clé Steam ID), cloison des communautés, journal d'audit.
 */
final class MemberIdentityPapersServiceTest extends TestCase
{
    private PDO $pdo;

    /** @var list<array<string, mixed>> */
    private array $audit = [];

    protected function setUp(): void
    {
        if (!extension_loaded('pdo_sqlite')) {
            self::markTestSkipped('pdo_sqlite requis');
        }
        $this->pdo = SseIdentitySqlite::open();
        $this->pdo->exec("INSERT INTO users (id, tenant_id, steam_id, display_name) VALUES (10, 1, '76561198000000010', 'Joueur'), (11, 1, NULL, 'Sans Steam'), (20, 2, '76561198000000020', 'Autre')");
        $this->audit = [];
    }

    protected function tearDown(): void
    {
        SseIdentitySqlite::close();
    }

    private function service(): MemberIdentityPapersService
    {
        return new MemberIdentityPapersService(null, null, null, null, null, function (array $e): void {
            $this->audit[] = $e;
        });
    }

    private function pack(int $tenant, ?string $op = null): array
    {
        $watch = $this->createMock(SseWatchlistRepository::class);
        $watch->method('listActive')->willReturn([]);
        $persons = $this->createMock(SsePersonRepository::class);
        $persons->method('listForContext')->willReturn([]);

        return (new SseIdentityPackService(new SseIdDocumentRepository(), new SseIdentityRepository(), new SseIdCountryRepository(), $watch, $persons, new SseIdKitRepository()))
            ->build($tenant, 1, $op);
    }

    /** @return array{0: int, 1: int, 2: int, 3: int} identité principale, couverture, papier principal émis, papier de couverture émis */
    private function player(MemberIdentityPapersService $s, int $tenant = 1, int $user = 10): array
    {
        $primary = $s->savePrimary($tenant, $user, ['last_name' => 'Dupont', 'first_name' => 'Jean', 'sex' => 'M', 'birth_date' => '1990-04-02', 'nationality' => 'ALT'], 1)['id'];
        $cover = $s->createAlias($tenant, $user, ['kind' => 'cover', 'last_name' => 'Markos', 'first_name' => 'Petros', 'nationality' => 'ALT'], true, 1)['id'];
        $doc = $s->saveDocument($tenant, $user, $s->ownedIdentity($tenant, $user, $primary, true), null, ['doc_type' => 'id_card', 'country_code' => 'ALT'], true, 1)['id'];
        $coverDoc = $s->saveDocument($tenant, $user, $s->ownedIdentity($tenant, $user, $cover, true), null, ['doc_type' => 'passport', 'country_code' => 'ALT'], true, 1)['id'];
        foreach ([$doc, $coverDoc] as $d) {
            self::assertTrue($s->action($tenant, $user, $s->ownedDocument($tenant, $user, $d, true), 'issue', '', 1)['ok']);
        }

        return [$primary, $cover, $doc, $coverDoc];
    }

    public function testRightsSeparateViewingManagingAndCoverTruth(): void
    {
        self::assertSame(['view' => false, 'manage' => false, 'truth' => false, 'cover' => false], MemberIdentityPapersService::rights(false, false, false, false));
        // Responsable des effectifs sans habilitation : lecture seule.
        self::assertSame(['view' => true, 'manage' => false, 'truth' => false, 'cover' => false], MemberIdentityPapersService::rights(false, true, false, false));
        // Effectifs + habilitation Encadrement : gestion, sans la vérité de couverture.
        self::assertSame(['view' => true, 'manage' => true, 'truth' => false, 'cover' => false], MemberIdentityPapersService::rights(false, true, true, false));
        // Responsable RENS habilité Confidentiel : tout.
        self::assertSame(['view' => true, 'manage' => true, 'truth' => true, 'cover' => true], MemberIdentityPapersService::rights(true, false, true, true));
    }

    public function testPrimaryIdentityIsPrefilledFromTheCharacterSheet(): void
    {
        $countries = (new SseIdCountryRepository())->listForTenant(1);
        $p = MemberIdentityPapersService::primaryProposal(
            ['first_name' => 'Charles', 'last_name' => 'de Gaulle', 'birth_date' => '1990-11-22 00:00:00', 'nationality' => 'Livonie'],
            ['sex' => 'Homme', 'birth_place' => 'Lille'],
            $countries
        );
        self::assertSame(['de Gaulle', 'Charles', '1990-11-22', 'Lille', 'M', 'LVN'], [$p['last_name'], $p['first_name'], $p['birth_date'], $p['birth_place'], $p['sex'], $p['nationality']]);
        self::assertSame('Charles de GAULLE', MemberIdentityPapersService::displayName($p), 'même format que MemberName');

        $s = $this->service();
        $id = $s->savePrimary(1, 10, $p, 1)['id'];
        self::assertSame($id, $s->savePrimary(1, 10, ['first_name' => 'Charles', 'last_name' => 'de Gaulle'] + $p, 1)['id'], 'une seule identité principale');
        self::assertFalse($s->savePrimary(1, 10, ['last_name' => '', 'first_name' => 'X'], 1)['ok']);
    }

    public function testCoverTruthIsHiddenWithoutConfidentialAndFromThePlayer(): void
    {
        $s = $this->service();
        [$primary, $cover, $doc, $coverDoc] = $this->player($s);
        self::assertFalse($s->createAlias(1, 10, ['kind' => 'cover', 'last_name' => 'A', 'first_name' => 'B'], false, 1)['ok'], 'couverture : Confidentiel requis');

        $staff = $s->section(1, 10, '76561198000000010', false);
        self::assertSame([$doc], array_column($staff['documents'], 'id'));
        self::assertSame([], $staff['aliases']);
        self::assertNull($s->ownedDocument(1, 10, $coverDoc, false));
        self::assertNull($s->ownedIdentity(1, 10, $cover, false));

        $cleared = $s->section(1, 10, '76561198000000010', true);
        self::assertEqualsCanonicalizing([$doc, $coverDoc], array_column($cleared['documents'], 'id'));
        self::assertSame($primary, $cleared['primary']['id']);
        self::assertSame('cover', $cleared['aliases'][0]['kind']);

        // Le joueur voit ses deux papiers, sans la nature de l'identité ni l'origine du papier.
        $mine = $s->memberView(1, 10, '76561198000000010');
        self::assertCount(2, $mine['documents']);
        $flat = json_encode($mine);
        foreach (['"kind"', 'st_origin', 'forgery', '"cover"', 'couverture'] as $needle) {
            self::assertStringNotContainsString($needle, (string) $flat);
        }
        self::assertEqualsCanonicalizing(['Jean DUPONT', 'Petros MARKOS'], array_column($mine['documents'], 'holder_name'));
        self::assertNotEmpty($mine['documents'][0]['mrz']);
    }

    public function testConsultationWithoutClearanceDoesNotUnmaskAnInfiltrator(): void
    {
        $s = $this->service();
        [$primary, , $doc, $coverDoc] = $this->player($s);
        $alias = $s->createAlias(1, 10, ['kind' => 'alias', 'last_name' => 'Renard', 'first_name' => 'Luc', 'nationality' => 'ALT'], true, 1)['id'];
        $aliasDoc = $s->saveDocument(1, 10, $s->ownedIdentity(1, 10, $alias, true), null, ['doc_type' => 'id_card', 'country_code' => 'ALT'], true, 1)['id'];
        self::assertFalse($s->createAlias(1, 10, ['kind' => 'alias', 'last_name' => 'A', 'first_name' => 'B'], false, 1)['ok'], 'alias : Confidentiel requis');

        // Kit fait seulement de papiers masqués : il n'existe pas pour ce responsable.
        $s->saveKit(1, 10, null, 'OP-MASK', [$coverDoc, $aliasDoc], true, 1);
        $s->saveKit(1, 10, null, 'OP-MIX', [$doc, $aliasDoc], true, 1);

        $staff = $s->section(1, 10, '76561198000000010', false);
        self::assertSame([], $staff['aliases']);
        self::assertSame([$primary], array_column($staff['identities'], 'id'));
        self::assertSame([$doc], array_column($staff['documents'], 'id'));
        self::assertNull($s->ownedIdentity(1, 10, $alias, false));
        self::assertNull($s->ownedDocument(1, 10, $aliasDoc, false));
        self::assertSame(['OP-MIX'], array_column($staff['kits'], 'op_key'));
        self::assertSame([$doc], $staff['kits'][0]['document_ids']);
        $flat = (string) json_encode($staff);
        foreach (['Renard', 'Markos'] as $needle) {
            self::assertStringNotContainsString($needle, $flat);
        }

        $cleared = $s->section(1, 10, '76561198000000010', true);
        self::assertContains('Renard', array_column($cleared['aliases'], 'last_name'));
        self::assertCount(2, $cleared['kits']);
    }

    public function testAControlPresentedUnderAnotherNameIsHiddenWithoutClearance(): void
    {
        $identities = [['last_name' => 'Dupont']];
        $checks = [['presented_last' => 'DUPONT'], ['presented_last' => 'Markos'], ['presented_last' => null]];
        self::assertSame([$checks[0], $checks[2]], MemberIdentityPapersService::withoutForeignNames($checks, $identities));
    }

    public function testMilitaryCardIsADocumentTypeWithItsOwnMrzCode(): void
    {
        self::assertArrayHasKey('military_id', \App\Support\SseIdentityCatalog::DOC_TYPES);
        $s = $this->service();
        [$primary] = $this->player($s);
        $id = $s->saveDocument(1, 10, $s->ownedIdentity(1, 10, $primary, true), null, ['doc_type' => 'military_id', 'country_code' => 'ALT'], true, 1)['id'];
        $doc = $s->ownedDocument(1, 10, $id, true);
        self::assertSame('military_id', $doc['doc_type']);
        self::assertSame('Carte militaire', $doc['doc_type_label']);
        $mrz = \App\Services\Sse\SseMrzService::forDocument($doc, $s->ownedIdentity(1, 10, $primary, true), 'ALT');
        self::assertStringStartsWith('A', $mrz[0]);
    }

    public function testDocumentActionsFollowTheLifecycleAndAreAudited(): void
    {
        $s = $this->service();
        $primary = $s->savePrimary(1, 10, ['last_name' => 'Dupont', 'first_name' => 'Jean', 'nationality' => 'ALT'], 1)['id'];
        $identity = $s->ownedIdentity(1, 10, $primary, false);
        self::assertFalse($s->saveDocument(1, 10, $identity, null, ['doc_type' => 'id_card', 'country_code' => 'ALT', 'doc_number' => '12'], false, 1)['ok'], 'format du pays');
        $id = $s->saveDocument(1, 10, $identity, null, ['doc_type' => 'id_card', 'country_code' => 'ALT'], false, 1)['id'];
        $doc = fn (): array => (new SseIdDocumentRepository())->find($id, 1);

        self::assertFalse($s->action(1, 10, $doc(), 'stolen', '', 1)['ok'], 'un brouillon ne se déclare pas volé');
        $dup = $s->action(1, 10, $doc(), 'duplicate', '', 1);
        self::assertTrue($dup['ok']);
        self::assertNotSame($doc()['doc_number'], (new SseIdDocumentRepository())->find($dup['id'], 1)['doc_number']);
        self::assertTrue($s->action(1, 10, (new SseIdDocumentRepository())->find($dup['id'], 1), 'delete', '', 1)['ok']);
        self::assertNull((new SseIdDocumentRepository())->find($dup['id'], 1));

        self::assertTrue($s->action(1, 10, $doc(), 'issue', '', 1)['ok']);
        self::assertSame('issued', $doc()['workflow']);
        self::assertFalse($s->action(1, 10, $doc(), 'delete', '', 1)['ok'], 'un papier émis ne se supprime pas');
        self::assertTrue($s->action(1, 10, $doc(), 'lost', '', 1)['ok']);
        self::assertSame('lost', $doc()['st_report']);
        $edit = $s->saveDocument(1, 10, $identity, $doc(), ['doc_number' => $doc()['doc_number'], 'issued_on' => '2025-01-01', 'expires_on' => '2035-01-01', 'authority' => 'Mairie'], false, 1);
        self::assertTrue($edit['ok']);
        self::assertSame('Mairie', $doc()['authority']);
        self::assertFalse($s->action(1, 10, $doc(), 'revoke', '', 1)['ok'], 'motif obligatoire');
        self::assertTrue($s->action(1, 10, $doc(), 'revoke', 'Identité grillée', 1)['ok']);
        self::assertSame('revoked', $doc()['workflow']);

        $actions = array_column($this->audit, 'action');
        foreach (['id_member.primary_create', 'id_member.document_create', 'id_member.document_duplicate', 'id_member.document_issue', 'id_member.document_revoke'] as $a) {
            self::assertContains($a, $actions);
        }
        self::assertSame(10, $this->audit[0]['after']['member_user_id']);
    }

    public function testKitReachesTheGameServerKeyedBySteamIdWithTheMostPreciseScope(): void
    {
        $s = $this->service();
        [, , $doc, $coverDoc] = $this->player($s);
        self::assertSame(1, $s->saveKit(1, 10, null, null, [$doc], true, 1));
        self::assertSame(2, $s->saveKit(1, 10, null, 'OP-NOX', [$doc, $coverDoc], true, 1));

        $default = $this->pack(1);
        self::assertSame([['uid' => '76561198000000010', 'user_id' => 10, 'scope' => 'default', 'documents' => [$doc]]], $default['kits']);
        $op = $this->pack(1, 'OP-NOX');
        self::assertSame('operation', $op['kits'][0]['scope']);
        self::assertEqualsCanonicalizing([$doc, $coverDoc], $op['kits'][0]['documents']);
        // Tout papier du kit fait partie des papiers du pack.
        self::assertSame([], array_diff($op['kits'][0]['documents'], array_column($op['documents'], 'id')));

        // Sans habilitation, enregistrer le kit ne retire pas la couverture déjà prévue (invisible pour ce responsable).
        $s->saveKit(1, 10, null, 'OP-NOX', [$doc], false, 1);
        self::assertEqualsCanonicalizing([$doc, $coverDoc], $this->pack(1, 'OP-NOX')['kits'][0]['documents']);

        // Papier révoqué : retiré du kit envoyé ; joueur sans Steam : pas de kit.
        $s->action(1, 10, $s->ownedDocument(1, 10, $doc, true), 'revoke', 'fin', 1);
        self::assertSame([], $this->pack(1)['kits']);
        $p2 = $s->savePrimary(1, 11, ['last_name' => 'Sans', 'first_name' => 'Steam', 'nationality' => 'ALT'], 1)['id'];
        $d2 = $s->saveDocument(1, 11, $s->ownedIdentity(1, 11, $p2, false), null, ['doc_type' => 'id_card', 'country_code' => 'ALT'], false, 1)['id'];
        $s->action(1, 11, $s->ownedDocument(1, 11, $d2, false), 'issue', '', 1);
        $s->saveKit(1, 11, null, null, [$d2], false, 1);
        self::assertSame([], $this->pack(1)['kits']);
        self::assertFalse($s->section(1, 11, '', false)['steam_linked']);
    }

    public function testCommunitiesAndMembersAreSealed(): void
    {
        $s = $this->service();
        [, , $doc] = $this->player($s);
        // Autre communauté, autre membre : rien.
        self::assertNull($s->ownedDocument(2, 10, $doc, true));
        self::assertNull($s->ownedDocument(1, 11, $doc, true));
        self::assertSame([], $s->section(2, 10, '76561198000000010', true)['documents']);
        // Un papier d'un autre membre ou d'une autre communauté n'entre pas dans un kit.
        self::assertSame(0, (new SseIdKitRepository())->replace(1, 11, null, null, [$doc]));
        self::assertSame(0, (new SseIdKitRepository())->replace(2, 10, null, null, [$doc]));
        $s->saveKit(1, 10, null, null, [$doc], true, 1);
        self::assertSame([], $this->pack(2)['kits']);
        self::assertCount(1, $this->pack(1)['kits']);
    }

    public function testChecksSufferedByThePlayerAreListedButNotThroughHisCover(): void
    {
        $s = $this->service();
        [, $cover, $doc, $coverDoc] = $this->player($s);
        $ins = $this->pdo->prepare('INSERT INTO sse_id_checks (tenant_id, idempotency_key, document_id, identity_id, subject_steam_id, verdict, created_at) VALUES (?, ?, ?, ?, ?, ?, ?)');
        $ins->execute([1, 'k1', $doc, null, null, 'ras', '2026-11-01 10:00:00']);
        $ins->execute([1, 'k2', $coverDoc, $cover, null, 'ras', '2026-11-01 11:00:00']);
        $ins->execute([1, 'k3', null, null, '76561198000000010', 'inconnu', '2026-11-01 12:00:00']);
        $ins->execute([2, 'k4', $doc, null, null, 'ras', '2026-11-01 12:00:00']);

        self::assertSame(['k3', 'k2', 'k1'], array_column($s->section(1, 10, '76561198000000010', true)['checks'], 'idempotency_key'));
        self::assertSame(['k1'], array_column($s->section(1, 10, '76561198000000010', false)['checks'], 'idempotency_key'));
        self::assertCount(3, $s->memberView(1, 10, '76561198000000010')['checks']);
    }
}
