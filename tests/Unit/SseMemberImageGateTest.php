<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Controllers\Web\SseFileRecordController;
use App\Core\Request;
use App\Core\Session;
use App\Repositories\SseFileRecordRepository;
use App\Repositories\SseIdentityRepository;
use App\Repositories\SseMemberImageGrantRepository;
use App\Services\Sse\SseFileSearchService;
use App\Services\Sse\SseMemberImageGate;
use App\Services\Sse\SseSeekPhotoService;
use App\Support\SseAccessCatalog;
use PDO;
use PHPUnit\Framework\TestCase;
use Tests\Support\SseFilesSqlite;
use Tests\Support\SseIdentitySqlite;

/**
 * Images des membres classées TRÈS SECRET (TS) : classification (calculée sur les liens existants, fail-closed), accès (titulaire,
 * hiérarchie directe et indirecte, habilitation avec droit explicite, dérogation datée), et refus SANS AUCUN OCTET sur chaque chemin de
 * lecture (liste, fiche, image, adresse d'identité, recherche de la photothèque), journalisation, demande d'accès, isolation des communautés.
 * Données fictives : des octets quelconques, jamais de vrai visage.
 */
final class SseMemberImageGateTest extends TestCase
{
    private ?PDO $pdo = null;

    /** @var list<string> fichiers de test à supprimer */
    private array $files = [];

    /** @var list<string> dossiers créés par le test, à retirer s'ils sont vides */
    private array $dirs = [];

    /** @var list<array<string, mixed>> */
    private array $audit = [];

    /** @var list<array{0: int, 1: int, 2: array<string, mixed>}> */
    private array $sent = [];

    private const OWNER = 10;       // membre titulaire
    private const CMD1 = 20;        // chef direct
    private const CMD2 = 30;        // chef du chef (supérieur indirect)
    private const PEER = 11;        // collègue de même rang
    private const MANAGER = 40;     // gestionnaire SSE sans lien hiérarchique
    private const HIGH = 50;        // habilitation haute + droit explicite

    protected function setUp(): void
    {
        if (!extension_loaded('pdo_sqlite')) {
            self::markTestSkipped('pdo_sqlite requis');
        }
        $this->pdo = SseFilesSqlite::open();
        (require dirname(__DIR__, 2) . '/bootstrap/sse_member_image_grants_migration.php')($this->pdo);
        SseMemberImageGate::useForSession(null);
        $this->audit = [];
        $this->sent = [];
        Session::forgetMany(['user_id', 'tenant_id', 'sse_tenant_id', 'sse_guest']);
    }

    protected function tearDown(): void
    {
        SseMemberImageGate::useForSession(null);
        Session::forgetMany(['user_id', 'tenant_id', 'sse_tenant_id', 'sse_guest']);
        foreach ($this->files as $f) {
            if (is_file($f)) {
                unlink($f);
            }
        }
        foreach (array_reverse($this->dirs) as $d) {
            if (is_dir($d) && (glob($d . '/*') ?: []) === []) {
                @rmdir($d);
            }
        }
        if ($this->pdo !== null) {
            SseFilesSqlite::close();
            $this->pdo = null;
        }
    }

    /** Porte de la requête : lecteur, chaînes hiérarchiques, habilitation, journal et notifications retenus par le test. */
    private function gate(int $viewer, bool $clearance = false, ?\Closure $chain = null, string $now = '2026-11-15 12:00:00'): SseMemberImageGate
    {
        $chains = [self::OWNER => [self::CMD1, self::CMD2], self::CMD1 => [self::CMD2]];

        return new SseMemberImageGate(
            null,
            null,
            $chain ?? static fn (int $t, int $o): array => $chains[$o] ?? [],
            static fn (int $t, int $v): bool => $clearance,
            function (array $row): void {
                $this->audit[] = $row;
            },
            function (int $t, int $u, array $p): void {
                $this->sent[] = [$t, $u, $p];
            },
            static fn (): string => $now,
            null,
            static fn (): ?int => $viewer > 0 ? $viewer : null
        );
    }

    private function identity(int $tenant, string $last, ?int $userId, string $kind = 'civil', ?string $photo = null): int
    {
        $repo = new SseIdentityRepository();
        $id = $repo->create($tenant, ['last_name' => $last, 'first_name' => 'Test', 'birth_date' => '1990-01-02', 'sex' => 'M', 'nationality' => 'USA', 'kind' => $kind, 'user_id' => $userId]);
        if ($photo !== null) {
            $repo->setImages($id, $tenant, ['photo_path' => $photo]);
        }

        return $id;
    }

    private function photo(int $tenant, ?int $identity, string $path, string $source = 'upload', ?string $ref = null): int
    {
        return (new SseFileRecordRepository())->addPhoto($tenant, ['identity_id' => $identity, 'path' => $path, 'mime' => 'image/jpeg', 'kind' => 'front', 'source' => $source, 'source_ref' => $ref], null);
    }

    /** @return array{member: int, memberCover: int, civil: int, memberPhoto: int, civilPhoto: int} */
    private function world(): array
    {
        $member = $this->identity(1, 'Membre', self::OWNER, 'civil', 'uploads/sse-id-photos/1_aaaaaaaaaaaaaaaa.jpg');
        $cover = $this->identity(1, 'Couverture', self::OWNER, 'cover', 'uploads/sse-id-photos/1_bbbbbbbbbbbbbbbb.jpg');
        $civil = $this->identity(1, 'Civil', null, 'civil', 'uploads/sse-id-photos/1_cccccccccccccccc.jpg');

        return [
            'member' => $member,
            'memberCover' => $cover,
            'civil' => $civil,
            'memberPhoto' => $this->photo(1, $member, 'sse-seek/1/dddddddddddddddd.jpg', 'seek', 'REC-261115-0001'),
            'civilPhoto' => $this->photo(1, $civil, 'sse-seek/1/eeeeeeeeeeeeeeee.jpg', 'seek', 'REC-261115-0002'),
        ];
    }

    // ------------------------------------------------------------------ classification

    public function testMemberImagesAreClassifiedAndNonMemberImagesAreUnchanged(): void
    {
        $w = $this->world();
        $gate = $this->gate(self::PEER);
        $m = $gate->identityState(1, $w['member']);
        self::assertSame(SseMemberImageGate::CLASS_MEMBER, $m['class']);
        self::assertSame(self::OWNER, $m['owner']);
        self::assertSame(SseMemberImageGate::CLASS_MEMBER, $gate->identityState(1, $w['memberCover'])['class'], 'couverture préparée pour un membre');
        self::assertSame(SseMemberImageGate::CLASS_PUBLIC, $gate->identityState(1, $w['civil'])['class'], 'sujet non membre : inchangé');
        self::assertSame(SseMemberImageGate::CLASS_PUBLIC, $gate->identityState(1, null)['class']);
        $photo = (new SseFileRecordRepository())->photo(1, $w['memberPhoto']);
        self::assertSame(self::OWNER, $gate->photoState(1, $photo ?? [])['owner'], 'cliché SEEK d\'un membre');
        self::assertTrue($gate->isConfidential($gate->photoState(1, $photo ?? [])));
        $civilPhoto = (new SseFileRecordRepository())->photo(1, $w['civilPhoto']);
        self::assertFalse($gate->isConfidential($gate->photoState(1, $civilPhoto ?? [])));
        self::assertSame('TRÈS SECRET (TS)', SseMemberImageGate::LABEL);
    }

    public function testUnattachedSeekPhotoFollowsItsSeekRecordAndAmbiguousIsFailClosed(): void
    {
        $w = $this->world();
        $gate = new SseMemberImageGate(null, null, static fn (): array => [], static fn (): bool => false, null, null, null, static fn (int $t, string $rec): ?int => $rec === 'REC-X' ? $w['member'] : null, static fn (): ?int => self::PEER);
        $linked = ['identity_id' => null, 'source' => 'seek', 'source_ref' => 'REC-X'];
        self::assertSame(self::OWNER, $gate->photoState(1, $linked)['owner'], 'fiche SEEK II rattachée à un membre');
        self::assertSame(SseMemberImageGate::CLASS_PUBLIC, $gate->photoState(1, ['identity_id' => null, 'source' => 'seek', 'source_ref' => 'REC-AUCUN'])['class'], 'aucun lien membre');
        self::assertSame(SseMemberImageGate::CLASS_PUBLIC, $gate->photoState(1, ['identity_id' => null, 'source' => 'upload'])['class']);
        // Ambigu : identité introuvable ou d'une autre communauté => TRÈS SECRET (TS) sans propriétaire.
        $ghost = $gate->photoState(1, ['identity_id' => 99999, 'source' => 'upload']);
        self::assertSame(SseMemberImageGate::CLASS_UNREADABLE, $ghost['class']);
        self::assertNull($ghost['owner']);
        self::assertTrue($gate->isConfidential($ghost));
        $other = $this->identity(2, 'Autre', null);
        self::assertSame(SseMemberImageGate::CLASS_UNREADABLE, $gate->identityState(1, $other)['class'], 'identité d\'une autre communauté : jamais lue');
        self::assertFalse($gate->decide(1, $ghost, self::PEER)['allowed']);
    }

    // ------------------------------------------------------------------ règles d'accès

    public function testAccessMatrix(): void
    {
        $w = $this->world();
        $state = (new SseMemberImageGate())->identityState(1, $w['member']);
        $reason = fn (int $viewer, bool $clearance = false): array => $this->gate($viewer, $clearance)->decide(1, $state);

        self::assertSame(['allowed' => true, 'reason' => 'holder', 'confidential' => true], $reason(self::OWNER), 'le titulaire voit ses propres images');
        self::assertSame('hierarchy', $reason(self::CMD1)['reason'], 'supérieur direct');
        self::assertSame('hierarchy', $reason(self::CMD2)['reason'], 'supérieur indirect');
        self::assertFalse($reason(self::PEER)['allowed'], 'collègue de même rang');
        self::assertFalse($reason(self::MANAGER)['allowed'], 'gestionnaire SSE sans lien hiérarchique');
        self::assertFalse($reason(0)['allowed'], 'invité ou sans compte');
        self::assertSame('clearance', $reason(self::HIGH, true)['reason'], 'habilitation haute avec le droit explicite');
        // Chaîne illisible : le doute masque.
        $broken = $this->gate(self::CMD1, false, static function (): array {
            throw new \RuntimeException('organigramme illisible');
        });
        self::assertFalse($broken->decide(1, $state)['allowed']);
        // Autre communauté : l'identité n'est jamais lue.
        self::assertFalse($this->gate(self::OWNER)->decide(1, $this->gate(self::OWNER)->identityState(2, $w['member']))['allowed']);
    }

    public function testClearanceAloneNeverOpensWithoutTheExplicitRightAndSsePolicyKeepsItSealed(): void
    {
        // La section « Identités de service » existe, elle est fermée par défaut : ni gestionnaire ni administrateur ne l'ouvrent.
        self::assertArrayHasKey('identites_service', SseAccessCatalog::SECTIONS);
        self::assertSame('sealed', SseAccessCatalog::legacyProfile('identites_service', 'read'));
        self::assertNotContains('identites_service', SseAccessCatalog::sectionKeys(), 'jamais comprise dans « toutes les sections »');
        $perm = static fn (int $u, int $t, string $slug): bool => true; // tous les droits du monde
        $policy = new \App\Services\Sse\SseAccessPolicy(null, null, $perm, static fn (): bool => true, static fn (): string => '2026-11-15 12:00:00', static fn (): bool => true);
        self::assertFalse($policy->allows(self::MANAGER, 'identites_service', 'read', ['tenant_id' => 1, 'guest' => false]), 'tous les droits d\'origine ne suffisent pas');
        self::assertFalse($policy->allows(self::MANAGER, 'identites_service', 'write', ['tenant_id' => 1, 'guest' => false]));
        // Une règle d'accord explicite l'ouvre à son bénéficiaire seulement (porte du portail ouverte) ; le gestionnaire reste dehors.
        $rule = ['id' => 1, 'section' => 'identites_service', 'level' => 'read', 'effect' => 'allow', 'subject_type' => 'user', 'subject_id' => self::HIGH, 'starts_at' => null, 'ends_at' => null];
        $ctx = ['tenant_id' => 1, 'guest' => false, 'extra_rules' => [$rule]];
        $denyAll = static fn (int $u, int $t, string $slug): bool => false;
        $open = new \App\Services\Sse\SseAccessPolicy(null, null, $denyAll, static fn (): bool => true, static fn (): string => '2026-11-15 12:00:00', static fn (): bool => false);
        self::assertTrue($open->decide(self::HIGH, 'identites_service', 'read', $ctx)->allowed, 'règle explicite');
        self::assertFalse($open->decide(self::MANAGER, 'identites_service', 'read', $ctx)->allowed, 'gestionnaire sans règle');
        // Sans habilitation haute, le droit seul ne suffit pas non plus : le contrôle d'habilitation est fourni à la porte.
        $w = $this->world();
        $state = (new SseMemberImageGate())->identityState(1, $w['member']);
        self::assertFalse($this->gate(self::HIGH, false)->decide(1, $state)['allowed']);
    }

    public function testDatedDerogationGrantedByASuperiorOnly(): void
    {
        $w = $this->world();
        $state = (new SseMemberImageGate())->identityState(1, $w['member']);
        $grants = new SseMemberImageGrantRepository();
        // Demande puis dérogation accordée par le supérieur direct (fin dans 3 jours).
        $gate = $this->gate(self::PEER);
        $r = $gate->requestAccess(1, self::PEER, self::OWNER, 'Enquête interne sur le contrôle du 14');
        self::assertTrue($r['ok']);
        self::assertFalse($gate->decide(1, $state)['allowed'], 'une demande en attente n\'ouvre rien');
        $pending = $grants->pending(1, self::OWNER, self::PEER);
        self::assertNotNull($pending);
        $badDecider = $this->gate(self::MANAGER)->decideRequest(1, self::MANAGER, (int) $pending['id'], true, 'Je suis gestionnaire', '2026-11-18 23:59:59');
        self::assertFalse($badDecider['ok'], 'un gestionnaire sans lien hiérarchique ne peut pas déroger');
        $ok = $this->gate(self::CMD1)->decideRequest(1, self::CMD1, (int) $pending['id'], true, 'Enquête interne validée', '2026-11-18 23:59:59');
        self::assertTrue($ok['ok']);
        self::assertSame('derogation', $this->gate(self::PEER)->decide(1, $state)['reason']);
        self::assertFalse($this->gate(self::PEER, false, null, '2026-11-19 00:00:01')->decide(1, $state)['allowed'], 'dérogation terminée');
        self::assertFalse($this->gate(self::MANAGER)->decide(1, $state)['allowed'], 'la dérogation est nominative');
        // Une dérogation sans fin n'existe pas ; au-delà de 30 jours non plus.
        $second = $grants->addRequest(1, self::OWNER, self::MANAGER, 'motif de test', '2026-11-15 12:00:00');
        self::assertFalse($this->gate(self::CMD1)->decideRequest(1, self::CMD1, (int) $second, true, 'trop long', '2027-06-01 00:00:00')['ok']);
        self::assertFalse($this->gate(self::CMD1)->decideRequest(1, self::CMD1, (int) $second, true, 'sans fin', null)['ok']);
        // Journal : demande et décision tracées ; notification à la chaîne.
        $actions = array_map(static fn (array $a): string => (string) $a['action'], $this->audit);
        self::assertContains('member_image.request', $actions);
        self::assertContains('member_image.grant', $actions);
        $notified = array_map(static fn (array $s): int => $s[1], $this->sent);
        self::assertContains(self::CMD1, $notified);
        self::assertContains(self::CMD2, $notified, 'toute la chaîne est prévenue');
    }

    public function testRequestAccessRefusesSelfShortReasonAndMemberWithoutSuperior(): void
    {
        $this->world();
        $gate = $this->gate(self::PEER);
        self::assertFalse($gate->requestAccess(1, self::PEER, self::PEER, 'un motif suffisant')['ok']);
        self::assertFalse($gate->requestAccess(1, self::PEER, self::OWNER, 'non')['ok']);
        $noChain = $this->gate(self::PEER, false, static fn (): array => []);
        self::assertFalse($noChain->requestAccess(1, self::PEER, self::OWNER, 'un motif suffisant')['ok'], 'aucun supérieur désigné');
        self::assertSame([], $this->sent);
    }

    // ------------------------------------------------------------------ chemins de lecture

    public function testPhotoRepositoryNeverReturnsAnImageTheViewerMayNotSee(): void
    {
        $w = $this->world();
        $repo = new SseFileRecordRepository();
        SseMemberImageGate::useForSession($this->gate(self::PEER));
        $list = $repo->photosFor(1, [$w['member'], $w['civil']]);
        self::assertSame([], $list[$w['member']] ?? [], 'collègue : aucune image du membre');
        self::assertCount(1, $list[$w['civil']], 'sujet non membre inchangé');
        self::assertArrayNotHasKey('confidential', $list[$w['civil']][0]);
        // Gestionnaire SSE sans lien : même résultat.
        SseMemberImageGate::useForSession($this->gate(self::MANAGER));
        self::assertSame([], $repo->photosFor(1, [$w['member']])[$w['member']] ?? []);
        // Supérieur et titulaire : l'image est rendue, marquée confidentielle (adresse protégée).
        foreach ([self::CMD2, self::OWNER] as $viewer) {
            SseMemberImageGate::useForSession($this->gate($viewer));
            $own = $repo->photosFor(1, [$w['member']])[$w['member']] ?? [];
            self::assertCount(1, $own);
            self::assertTrue($own[0]['confidential']);
        }
        // Photo non rattachée d'un membre (fiche SEEK II du membre) : pas dans la liste « à rattacher ».
        $un = $this->photo(1, null, 'sse-seek/1/ffffffffffffffff.jpg', 'seek', 'REC-261115-0003');
        $this->pdo?->exec("INSERT INTO sse_seek_records (tenant_id, rec, idempotency_key, bio_key, last_name, first_name, subject_unknown, verdict, enrolled, mod_fingers, mod_iris, mod_face, identity_id, created_at) VALUES (1, 'REC-261115-0003', 'K-3', 'BIO-3', 'Membre', 'Test', 0, 'concordant', 0, 1, 0, 1, {$w['member']}, '2026-11-15 10:00:00')");
        SseMemberImageGate::useForSession($this->gate(self::PEER));
        $ids = array_map(static fn (array $p): int => $p['id'], $repo->unattachedPhotos(1));
        self::assertNotContains($un, $ids);
        SseMemberImageGate::useForSession($this->gate(self::CMD1));
        $ids = array_map(static fn (array $p): int => $p['id'], $repo->unattachedPhotos(1));
        self::assertContains($un, $ids);
    }

    public function testPhotobookSearchHidesMemberImagesAndNeverExposesTheirPaths(): void
    {
        $w = $this->world();
        $search = static fn (): array => (new SseFileSearchService(null, null, null, '2026-11-11'))->search(1, SseFileSearchService::cleanParams(['photo' => '1']));
        SseMemberImageGate::useForSession($this->gate(self::PEER));
        $result = $search();
        $byId = [];
        foreach ($result['hits'] as $hit) {
            $byId[(int) $hit['identity']['id']] = $hit;
        }
        self::assertArrayHasKey($w['member'], $byId);
        $locked = $byId[$w['member']];
        self::assertNull($locked['photo'], 'aucun cliché');
        self::assertNull($locked['photo_src'], 'aucune adresse');
        self::assertNull($locked['identity']['photo_path'], 'aucun chemin d\'image');
        self::assertSame('TRÈS SECRET (TS)', $locked['image_lock']['label']);
        self::assertTrue($locked['image_lock']['can_request']);
        $raw = json_encode($locked, JSON_UNESCAPED_UNICODE);
        self::assertStringNotContainsString('sse-id-photos', (string) $raw);
        self::assertStringNotContainsString('sse-seek/', (string) $raw);
        self::assertStringNotContainsString('aaaaaaaaaaaaaaaa', (string) $raw);
        self::assertSame($w['civil'] > 0, isset($byId[$w['civil']]));
        self::assertNull($byId[$w['civil']]['image_lock'] ?? null, 'non membre inchangé');
        self::assertNotNull($byId[$w['civil']]['photo']);

        // Supérieur : l'image passe par la route protégée, jamais par le dossier public.
        SseMemberImageGate::useForSession($this->gate(self::CMD1));
        foreach ($search()['hits'] as $hit) {
            if ((int) $hit['identity']['id'] === $w['member']) {
                self::assertNull($hit['image_lock'] ?? null);
                self::assertSame('hierarchy', $hit['image_banner']);
                self::assertStringContainsString('atak/sse/fichiers/photos/' . $w['memberPhoto'] . '/image', (string) $hit['photo_src']);
                self::assertStringNotContainsString('uploads/', (string) $hit['photo_src']);
            }
        }
    }

    public function testPhotobookViewRendersTheLockedFrameWithoutAnyImageTag(): void
    {
        if (!function_exists('url') || !function_exists('asset_url')) {
            self::markTestSkipped('helpers du site requis');
        }
        $w = $this->world();
        SseMemberImageGate::useForSession($this->gate(self::PEER));
        $result = (new SseFileSearchService(null, null, null, '2026-11-11'))->search(1, SseFileSearchService::cleanParams(['photo' => '1']));
        $keep = [];
        foreach ($result['hits'] as $hit) {
            if ((int) $hit['identity']['id'] === $w['member']) {
                $keep[] = $hit;
            }
        }
        $html = $this->renderFile('/views/atak/sse/files/_hit.php', ['hit' => $keep[0], 'purpose' => '']);
        self::assertStringContainsString('TRÈS SECRET (TS)', $html);
        self::assertStringContainsString('class="fx-lk"', $html);
        self::assertStringNotContainsString('<img', $html);
        self::assertStringNotContainsString('uploads/', $html);
        self::assertStringNotContainsString('blur', $html, 'jamais d\'image floutée côté client');
    }

    public function testSeekImageServiceFiltersByIdentityAndByRec(): void
    {
        $w = $this->world();
        $svc = new SseSeekPhotoService(new SseFileRecordRepository());
        SseMemberImageGate::useForSession($this->gate(self::PEER));
        self::assertSame([], $svc->forIdentity(1, $w['member']));
        self::assertSame([], $svc->forRecs(1, ['REC-261115-0001']));
        self::assertCount(1, $svc->forRecs(1, ['REC-261115-0002']), 'non membre inchangé');
        SseMemberImageGate::useForSession($this->gate(self::CMD2));
        self::assertCount(1, $svc->forIdentity(1, $w['member']));
        $url = (string) ($svc->forRecs(1, ['REC-261115-0001'])['REC-261115-0001'][0]['url'] ?? '');
        self::assertStringContainsString('/image', $url);
    }

    public function testPublicPathOfAConfidentialPhotoIsNeverTheAddress(): void
    {
        if (!function_exists('url') || !function_exists('user_media_public_url')) {
            self::markTestSkipped('helpers du site requis');
        }
        $url = (string) SseSeekPhotoService::photoUrl(['id' => 7, 'path' => 'uploads/sse-id-photos/1_aaaaaaaaaaaaaaaa.jpg', 'confidential' => true]);
        self::assertStringContainsString('atak/sse/fichiers/photos/7/image', $url);
        self::assertStringNotContainsString('uploads/', $url);
        // Même une image héritée non confidentielle d'un dossier sensible passe par la route (jamais d'adresse publique).
        self::assertStringContainsString('atak/sse/fichiers/photos/7/image', (string) SseSeekPhotoService::photoUrl(['id' => 7, 'path' => 'uploads/sse-id-photos/1_aaaaaaaaaaaaaaaa.jpg']));
        self::assertStringContainsString('atak/sse/fichiers/photos/7/image', (string) SseSeekPhotoService::photoUrl(['id' => 7, 'path' => 'sse-private/sse-id-photos/1_aaaaaaaaaaaaaaaa.jpg']));
        self::assertStringContainsString('uploads/', (string) SseSeekPhotoService::photoUrl(['id' => 7, 'path' => 'uploads/sse/evidence/ev_1_abc.png']), 'dossier non couvert : adresse publique inchangée');
    }

    public function testMaskIdentityForScreens(): void
    {
        $w = $this->world();
        $identity = (new SseIdentityRepository())->find($w['member'], 1) ?? [];
        $locked = $this->gate(self::PEER)->maskIdentity(1, $identity);
        self::assertNull($locked['photo_path']);
        self::assertNull($locked['signature_path']);
        self::assertSame('TRÈS SECRET (TS)', $locked['image_lock']['label']);
        $boss = $this->gate(self::CMD1)->maskIdentity(1, $identity);
        self::assertSame('atak/sse/fichiers/identites/' . $w['member'] . '/photo', $boss['photo_path']);
        self::assertSame('hierarchy', $boss['image_banner']);
        $own = $this->gate(self::OWNER)->maskIdentity(1, $identity);
        self::assertSame($identity['photo_path'], $own['photo_path'], 'le titulaire garde sa propre fiche telle quelle');
        $civil = (new SseIdentityRepository())->find($w['civil'], 1) ?? [];
        self::assertSame($civil, $this->gate(self::PEER)->maskIdentity(1, $civil), 'non membre : inchangé');
    }

    // ------------------------------------------------------------------ contrôleur : aucun octet sans autorisation

    public function testControllerServesNoByteToAnUnauthorizedReaderAndLogsAuthorizedOnes(): void
    {
        $w = $this->world();
        $dir = base_path('storage/private/sse-seek/1');
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
            $this->dirs[] = $dir;
            $this->dirs[] = dirname($dir);
        }
        $file = $dir . '/dddddddddddddddd.jpg';
        file_put_contents($file, "\xFF\xD8\xFFTEST-MEMBER-BYTES\xFF\xD9");
        $this->files[] = $file;

        $serve = function (int $viewer) use ($w): array {
            Session::set('user_id', $viewer);
            Session::set('tenant_id', 1);
            Session::set('sse_tenant_id', 1);
            SseMemberImageGate::useForSession($this->gate($viewer));
            $resp = (new SseFileRecordController())->photoImage(new Request(), ['id' => (string) $w['memberPhoto']]);

            return [$resp->statusCode(), self::bytesOf($resp)];
        };
        foreach ([self::PEER, self::MANAGER] as $viewer) {
            [$status, $body] = $serve($viewer);
            self::assertSame(404, $status);
            self::assertStringNotContainsString('TEST-MEMBER-BYTES', $body);
        }
        self::assertSame('member_image.denied', $this->audit[count($this->audit) - 1]['action'], 'tentative refusée journalisée');
        $before = count($this->audit);
        foreach ([self::OWNER => 'holder', self::CMD1 => 'hierarchy', self::CMD2 => 'hierarchy'] as $viewer => $why) {
            [$status, $body] = $serve($viewer);
            self::assertSame(200, $status, 'viewer ' . $viewer);
            self::assertStringContainsString('TEST-MEMBER-BYTES', $body);
        }
        $views = array_values(array_filter(array_slice($this->audit, $before), static fn (array $a): bool => $a['action'] === 'member_image.view'));
        self::assertCount(3, $views, 'chaque consultation est journalisée');
        self::assertSame(self::CMD1, $views[1]['actor_user_id']);
        self::assertSame('hierarchy', $views[1]['reason'], 'motif d\'accès');
        self::assertSame($w['memberPhoto'], $views[1]['object_id']);
        // Image d'un autre membre non classé : servie sans journal, comme avant.
        $civil = $dir . '/eeeeeeeeeeeeeeee.jpg';
        file_put_contents($civil, "\xFF\xD8\xFFTEST-CIVIL-BYTES\xFF\xD9");
        $this->files[] = $civil;
        $n = count($this->audit);
        Session::set('user_id', self::PEER);
        SseMemberImageGate::useForSession($this->gate(self::PEER));
        $resp = (new SseFileRecordController())->photoImage(new Request(), ['id' => (string) $w['civilPhoto']]);
        self::assertSame(200, $resp->statusCode());
        self::assertStringContainsString('TEST-CIVIL-BYTES', self::bytesOf($resp));
        self::assertCount($n, $this->audit, 'un sujet non membre n\'est pas journalisé');
    }

    public function testAuditFailureRefusesTheImage(): void
    {
        $w = $this->world();
        $dir = base_path('storage/private/sse-seek/1');
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
            $this->dirs[] = $dir;
            $this->dirs[] = dirname($dir);
        }
        $file = $dir . '/dddddddddddddddd.jpg';
        file_put_contents($file, "\xFF\xD8\xFFTEST-MEMBER-BYTES\xFF\xD9");
        $this->files[] = $file;
        Session::set('user_id', self::CMD1);
        Session::set('tenant_id', 1);
        Session::set('sse_tenant_id', 1);
        $broken = new SseMemberImageGate(null, null, static fn (): array => [self::CMD1], static fn (): bool => false, static function (): void {
            throw new \RuntimeException('journal indisponible');
        }, null, null, null, static fn (): ?int => self::CMD1);
        SseMemberImageGate::useForSession($broken);
        $resp = (new SseFileRecordController())->photoImage(new Request(), ['id' => (string) $w['memberPhoto']]);
        $out = self::bytesOf($resp);
        self::assertSame(404, $resp->statusCode(), 'pas d\'accès sans trace');
        self::assertStringNotContainsString('TEST-MEMBER-BYTES', $out);
    }

    public function testIdentityImageRouteServesMemberPhotosOnlyThroughTheGate(): void
    {
        $w = $this->world();
        $pubDir = base_path('public/uploads/sse-id-photos');
        if (!is_dir($pubDir)) {
            mkdir($pubDir, 0775, true);
            $this->dirs[] = $pubDir;
            $this->dirs[] = dirname($pubDir);
        }
        $file = $pubDir . '/1_aaaaaaaaaaaaaaaa.jpg';
        file_put_contents($file, "\xFF\xD8\xFFTEST-IDENTITY-BYTES\xFF\xD9");
        $this->files[] = $file;
        $civilFile = $pubDir . '/1_cccccccccccccccc.jpg';
        file_put_contents($civilFile, "\xFF\xD8\xFFTEST-CIVIL-ID-BYTES\xFF\xD9");
        $this->files[] = $civilFile;
        $serve = function (int $viewer, int $identityId, string $kind = 'photo'): array {
            Session::set('user_id', $viewer);
            Session::set('tenant_id', 1);
            Session::set('sse_tenant_id', 1);
            SseMemberImageGate::useForSession($this->gate($viewer));
            $resp = (new SseFileRecordController())->identityImage(new Request(), ['id' => (string) $identityId, 'kind' => $kind]);

            return [$resp->statusCode(), self::bytesOf($resp)];
        };
        [$status, $body] = $serve(self::PEER, $w['member']);
        self::assertSame(404, $status);
        self::assertStringNotContainsString('TEST-IDENTITY-BYTES', $body);
        [$status, $body] = $serve(self::MANAGER, $w['member']);
        self::assertSame(404, $status);
        [$status, $body] = $serve(self::CMD2, $w['member']);
        self::assertSame(200, $status);
        self::assertStringContainsString('TEST-IDENTITY-BYTES', $body);
        self::assertSame('member_image.view', $this->audit[count($this->audit) - 1]['action']);
        self::assertSame(404, $serve(self::CMD2, $w['member'], 'inconnu')[0]);
        self::assertSame(404, $serve(self::CMD2, 987654)[0], 'identité inconnue');
        [$status, $body] = $serve(self::PEER, $w['civil']);
        self::assertSame(200, $status, 'non membre : inchangé');
        self::assertStringContainsString('TEST-CIVIL-ID-BYTES', $body);
    }

    // ------------------------------------------------------------------ espace privé (storage/private/sse-images)

    /** Dépose un fichier factice dans l'espace privé SSE (nettoyé à la fin du test). */
    private function privateFile(string $folder, string $name, string $bytes): void
    {
        $dir = base_path('storage/private/sse-images/' . $folder);
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
            $this->dirs[] = $dir;
            $this->dirs[] = dirname($dir);
        }
        file_put_contents($dir . '/' . $name, $bytes);
        $this->files[] = $dir . '/' . $name;
    }

    /** @return array{0: int, 1: string, 2: \App\Core\Response} */
    private function serveIdentity(int $viewer, int $identityId, string $kind = 'photo', int $tenant = 1): array
    {
        Session::set('user_id', $viewer);
        Session::set('tenant_id', $tenant);
        Session::set('sse_tenant_id', $tenant);
        SseMemberImageGate::useForSession($this->gate($viewer));
        $resp = (new SseFileRecordController())->identityImage(new Request(), ['id' => (string) $identityId, 'kind' => $kind]);

        return [$resp->statusCode(), self::bytesOf($resp), $resp];
    }

    public function testPrivateIdentityImageIsServedOnlyThroughTheGateWithSafeHeaders(): void
    {
        $repo = new SseIdentityRepository();
        $member = $this->identity(1, 'Prive', self::OWNER, 'civil', 'sse-private/sse-id-photos/1_aaaaaaaaaaaaaaaa.jpg');
        $repo->setImages($member, 1, ['signature_path' => 'sse-private/sse-id-signatures/1_bbbbbbbbbbbbbbbb.png']);
        $png = "\x89PNG\r\n\x1a\nTEST-PRIVATE-BYTES";
        $this->privateFile('sse-id-photos', '1_aaaaaaaaaaaaaaaa.jpg', $png); // extension .jpg, contenu PNG : le contenu décide
        $this->privateFile('sse-id-signatures', '1_bbbbbbbbbbbbbbbb.png', $png);

        foreach ([self::PEER, self::MANAGER] as $viewer) {
            [$status, $body] = $this->serveIdentity($viewer, $member);
            self::assertSame(404, $status, 'viewer ' . $viewer);
            self::assertStringNotContainsString('TEST-PRIVATE-BYTES', $body, 'aucun octet sans autorisation');
        }
        [$status, $body, $resp] = $this->serveIdentity(self::CMD1, $member);
        self::assertSame(200, $status);
        self::assertStringContainsString('TEST-PRIVATE-BYTES', $body);
        self::assertSame('image/png', $resp->headerValue('Content-Type'), 'type réel, pas l’extension');
        self::assertSame('nosniff', $resp->headerValue('X-Content-Type-Options'));
        self::assertSame('private, no-store', $resp->headerValue('Cache-Control'));
        self::assertSame('no-referrer', $resp->headerValue('Referrer-Policy'));
        self::assertStringStartsWith('inline;', (string) $resp->headerValue('Content-Disposition'));
        [$status, $body] = $this->serveIdentity(self::OWNER, $member, 'signature');
        self::assertSame(200, $status);
        self::assertStringContainsString('TEST-PRIVATE-BYTES', $body);
        self::assertSame('member_image.view', $this->audit[count($this->audit) - 1]['action'], 'consultation journalisée');
        [$status, $body] = $this->serveIdentity(self::CMD1, $member, 'photo', 2);
        self::assertSame(404, $status, 'autre communauté');
        self::assertStringNotContainsString('TEST-PRIVATE-BYTES', $body);
    }

    public function testPrivateRouteRefusesNonImagesTraversalAndForeignTenantNames(): void
    {
        $repo = new SseIdentityRepository();
        $fake = $this->identity(1, 'Faux', null, 'civil', 'sse-private/sse-id-photos/1_cccccccccccccccc.jpg');
        $this->privateFile('sse-id-photos', '1_cccccccccccccccc.jpg', "<?php echo 'TEST-NOT-AN-IMAGE';");
        [$status, $body] = $this->serveIdentity(self::PEER, $fake);
        self::assertSame(404, $status, 'contenu non image : jamais servi');
        self::assertStringNotContainsString('TEST-NOT-AN-IMAGE', $body);

        $this->privateFile('sse-id-photos', '2_dddddddddddddddd.jpg', "\xFF\xD8\xFFTEST-OTHER-TENANT\xFF\xD9");
        foreach ([
            'sse-private/sse-id-photos/../../../../../etc/passwd',
            'sse-private/sse-id-photos/1_cccccccccccccccc.jpg/../2_dddddddddddddddd.jpg',
            'sse-private/sse-id-photos/2_dddddddddddddddd.jpg',
            'sse-private/other/1_cccccccccccccccc.jpg',
        ] as $bad) {
            $repo->setImages($fake, 1, ['photo_path' => $bad]);
            [$status, $body] = $this->serveIdentity(self::OWNER, $fake);
            self::assertSame(404, $status, $bad);
            self::assertStringNotContainsString('TEST-OTHER-TENANT', $body, $bad);
        }
    }

    public function testBackgroundRouteFollowsTheIdentityRulesAndNeverTheirPublicAddress(): void
    {
        SseIdentitySqlite::migrate($this->pdo);
        $member = $this->identity(1, 'Fond', self::OWNER, 'civil');
        $docs = new \App\Repositories\SseIdDocumentRepository();
        $docId = $docs->create(1, ['identity_id' => $member, 'doc_type' => 'id_card', 'country_code' => 'ALT', 'doc_number' => 'TEST-1', 'workflow' => 'draft'], null);
        $docs->setBackground($docId, 1, 'sse-private/sse-id-backgrounds/1_eeeeeeeeeeeeeeee.jpg');
        $this->privateFile('sse-id-backgrounds', '1_eeeeeeeeeeeeeeee.jpg', "\xFF\xD8\xFFTEST-BACKGROUND\xFF\xD9");
        $serve = function (int $viewer, int $doc, int $tenant = 1): array {
            Session::set('user_id', $viewer);
            Session::set('tenant_id', $tenant);
            Session::set('sse_tenant_id', $tenant);
            SseMemberImageGate::useForSession($this->gate($viewer));
            $resp = (new SseFileRecordController())->documentBackground(new Request(), ['id' => (string) $doc]);

            return [$resp->statusCode(), self::bytesOf($resp), $resp];
        };
        [$status, $body] = $serve(self::PEER, $docId);
        self::assertSame(404, $status);
        self::assertStringNotContainsString('TEST-BACKGROUND', $body);
        [$status, $body, $resp] = $serve(self::CMD1, $docId);
        self::assertSame(200, $status);
        self::assertStringContainsString('TEST-BACKGROUND', $body);
        self::assertSame('image/jpeg', $resp->headerValue('Content-Type'));
        self::assertSame('private, no-store', $resp->headerValue('Cache-Control'));
        self::assertSame('nosniff', $resp->headerValue('X-Content-Type-Options'));
        self::assertSame(404, $serve(self::CMD1, $docId, 2)[0], 'autre communauté');
        self::assertSame(404, $serve(self::CMD1, 987654)[0], 'papier inconnu');
        self::assertStringContainsString('/atak/sse/fichiers/documents/' . $docId . '/fond', (string) \App\Support\SseImageStorage::backgroundUrl(['id' => $docId, 'background_path' => 'sse-private/sse-id-backgrounds/1_eeeeeeeeeeeeeeee.jpg']));
    }

    public function testPrivatePhotothequeImageGoesThroughThePhotoRoute(): void
    {
        $civil = $this->identity(1, 'Phototheque', null, 'civil');
        $id = $this->photo(1, $civil, 'sse-private/sse-id-photos/1_ffffffffffffffff.jpg', 'upload');
        $this->privateFile('sse-id-photos', '1_ffffffffffffffff.jpg', "\xFF\xD8\xFFTEST-LIBRARY\xFF\xD9");
        Session::set('user_id', self::PEER);
        Session::set('tenant_id', 1);
        Session::set('sse_tenant_id', 1);
        SseMemberImageGate::useForSession($this->gate(self::PEER));
        $resp = (new SseFileRecordController())->photoImage(new Request(), ['id' => (string) $id]);
        self::assertSame(200, $resp->statusCode());
        self::assertStringContainsString('TEST-LIBRARY', self::bytesOf($resp));
        self::assertSame('nosniff', $resp->headerValue('X-Content-Type-Options'));
        self::assertStringContainsString('/atak/sse/fichiers/photos/' . $id . '/image', (string) SseSeekPhotoService::photoUrl(['id' => $id, 'path' => 'sse-private/sse-id-photos/1_ffffffffffffffff.jpg']));
    }

    public function testRoutesAndSealedSectionAreWired(): void
    {
        $root = dirname(__DIR__, 2);
        $routes = (string) file_get_contents($root . '/routes/web.php');
        foreach (['identites/{id}/{kind}', 'acces-images/demande', 'acces-images/{id}'] as $needle) {
            self::assertStringContainsString("/atak/sse/fichiers/{$needle}'", $routes);
        }
        $registry = (string) file_get_contents($root . '/database/steps/registry.php');
        self::assertStringContainsString("'file' => '4660_sse_member_image_grants.php'", $registry);
        self::assertFileExists($root . '/database/steps/4660_sse_member_image_grants.php');
        self::assertFileExists($root . '/migrations/rollback/sse_member_image_grants_down.sql');
        // Aucun accès direct au dossier public pour une image de membre dans les écrans des fichiers.
        foreach (['_hit.php', 'photobook.php'] as $view) {
            $src = (string) file_get_contents($root . '/views/atak/sse/files/' . $view);
            self::assertStringNotContainsString('user_media_public_url((string) $i[\'photo_path\'])', $src, $view);
        }
    }

    /** Octets que la réponse enverrait (corps ou flux), sans émettre d'en-têtes. */
    private static function bytesOf(\App\Core\Response $resp): string
    {
        $prop = new \ReflectionProperty($resp, 'bodyStream');
        $stream = $prop->getValue($resp);
        if (!is_callable($stream)) {
            return $resp->body();
        }
        ob_start();
        $stream();

        return (string) ob_get_clean();
    }

    /** @param array<string, mixed> $vars */
    private function renderFile(string $relative, array $vars): string
    {
        extract($vars + ['csrfToken' => 't'], EXTR_SKIP);
        ob_start();
        require dirname(__DIR__, 2) . $relative;

        return (string) ob_get_clean();
    }
}
