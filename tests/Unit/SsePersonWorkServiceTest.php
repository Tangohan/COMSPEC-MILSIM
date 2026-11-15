<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Repositories\SseIdentityRepository;
use App\Repositories\SsePersonRepository;
use App\Repositories\SsePersonWorkRepository;
use App\Repositories\SsePortalSettingsRepository;
use App\Services\Sse\SseMemberImageGate;
use App\Services\Sse\SsePersonViewer;
use App\Services\Sse\SsePersonWorkService;
use App\Services\Sse\SseWantedAccess;
use App\Support\SsePersonWork;
use PDO;
use PHPUnit\Framework\TestCase;
use Tests\Support\SsePersonWorkSqlite;

/**
 * Page Personnes : onglets, compteurs, filtres, tri, dernière ouverture ou lecture, classification (plafond d'habilitation,
 * motif, journal), verrouillage (masquage pour qui n'est pas habilité, règle automatique, déverrouillage), clôture (motifs, avis
 * actif, réouverture), actions groupées. Base sqlite réelle, aucune doublure : les règles tournent sur les vraies requêtes.
 */
final class SsePersonWorkServiceTest extends TestCase
{
    private const NOW = 1_795_000_000;

    private PDO $pdo;
    private SsePersonWorkService $service;
    private int $clock = self::NOW;

    protected function setUp(): void
    {
        if (!extension_loaded('pdo_sqlite')) {
            self::markTestSkipped('pdo_sqlite requis');
        }
        $this->pdo = SsePersonWorkSqlite::open();
        $this->clock = self::NOW;
        $this->service = $this->makeService();
    }

    protected function tearDown(): void
    {
        SsePersonWorkSqlite::close();
        SseMemberImageGate::useForSession(null);
    }

    /** @param array<int, list<int>> $chains membre => supérieurs */
    private function makeService(array $chains = []): SsePersonWorkService
    {
        $gate = new SseMemberImageGate(
            null,
            null,
            static fn (int $t, int $owner): array => $chains[$owner] ?? [],
            static fn (): bool => false,
            static function (array $row): void {
            },
            null,
            fn (): string => gmdate('Y-m-d H:i:s', $this->clock),
            null,
            null
        );

        return new SsePersonWorkService(null, null, null, null, $gate, fn (): int => $this->clock);
    }

    private function viewer(string $level = 'confidentiel', int $user = 7, bool $write = true, bool $manage = true, bool $wanted = true): SsePersonViewer
    {
        return new SsePersonViewer(1, $user, false, true, $write, $manage, true, $level, true, $wanted, 'ANALYSTE-' . $user);
    }

    /** @param array<string, mixed> $extra */
    private function person(string $last, string $first = 'Test', array $extra = []): int
    {
        $row = $extra + [
            'tenant_id' => 1, 'context_id' => 1, 'status' => 'civil', 'last_name' => $last, 'first_name' => $first,
            'created_at' => gmdate('Y-m-d H:i:s', self::NOW - 30 * 86400), 'updated_at' => gmdate('Y-m-d H:i:s', self::NOW - 30 * 86400),
        ];
        $cols = array_keys($row);
        $this->pdo->prepare('INSERT INTO sse_persons (' . implode(',', $cols) . ') VALUES (' . implode(',', array_fill(0, count($cols), '?')) . ')')->execute(array_values($row));

        return (int) $this->pdo->lastInsertId();
    }

    private function signed(int $id): void
    {
        $this->pdo->prepare("UPDATE sse_persons SET signed_by_callsign = 'TA1', signed_terminal_uid = 'T-1', signed_at = '2026-11-01 10:00:00' WHERE id = ?")->execute([$id]);
    }

    private function identity(int $personId, ?int $userId = null, string $kind = 'civil'): int
    {
        return (new SseIdentityRepository())->create(1, ['last_name' => 'X', 'first_name' => 'Y', 'kind' => $kind, 'person_id' => $personId, 'user_id' => $userId]);
    }

    private function notice(int $identityId, string $threat = 'high', string $ref = 'A-1'): int
    {
        $this->pdo->prepare("INSERT INTO sse_wanted_notices (tenant_id, identity_id, ref, ref_year, ref_seq, notice_type, threat_level, armed, status, active_key, issued_at, created_at)
            VALUES (1, ?, ?, 0, 1, 'poi', ?, 'unknown', 'active', 1, '2026-11-10 10:00:00', '2026-11-10 10:00:00')")->execute([$identityId, $ref, $threat]);

        return (int) $this->pdo->lastInsertId();
    }

    /** @return list<int> */
    private function ids(array $listing): array
    {
        return array_map(static fn (array $r): int => $r['id'], $listing['rows']);
    }

    private function audit(string $action): array
    {
        $st = $this->pdo->prepare('SELECT * FROM sse_audit_log WHERE action = ? ORDER BY id DESC');
        $st->execute([$action]);

        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    // ------------------------------------------------------------------ Onglets, compteurs, filtres, tri

    public function testTabsCountersAndClosedAreHiddenByDefault(): void
    {
        $a = $this->person('Alpha');
        $this->signed($a);
        $b = $this->person('Bravo', 'T', ['classification' => 'encadrement']);
        $c = $this->person('Charlie');
        $this->identity($c);
        $this->notice($this->identity($this->person('Delta')), 'critical');
        $e = $this->person('Echo', 'T', ['closed_at' => '2026-11-01 00:00:00', 'closed_by' => 7, 'close_reason' => 'resolu']);
        $f = $this->person('Foxtrot', 'T', ['classification' => 'confidentiel', 'locked_at' => '2026-11-02 00:00:00', 'locked_by' => 7, 'lock_reason' => 'Test']);
        $g = $this->person('Golf', 'T', ['updated_at' => gmdate('Y-m-d H:i:s', self::NOW - 3600)]);

        $all = $this->service->listing($this->viewer(), []);
        self::assertSame(['toutes' => 6, 'recherchees' => 1, 'non_signees' => 5, 'classifiees' => 2, 'verrouillees' => 1, 'closes' => 1, 'recentes' => 1], $all['counts']);
        self::assertNotContains($e, $this->ids($all), 'Un dossier clos est masqué de la vue principale');
        self::assertSame(6, $all['total']);

        self::assertSame([$e], $this->ids($this->service->listing($this->viewer(), ['tab' => 'closes'])));
        self::assertSame([$f], $this->ids($this->service->listing($this->viewer(), ['tab' => 'verrouillees'])));
        self::assertSame([$g], $this->ids($this->service->listing($this->viewer(), ['tab' => 'recentes'])));
        $classified = $this->ids($this->service->listing($this->viewer(), ['tab' => 'classifiees']));
        sort($classified);
        self::assertSame([$b, $f], $classified);
        self::assertNotContains($a, $this->ids($this->service->listing($this->viewer(), ['tab' => 'non_signees'])));
    }

    public function testSearchFindsNameAliasIdnNationalityAndGrid(): void
    {
        $a = $this->person('Karimzadeh-Haddad', 'Élodie', ['alias' => 'Abou Hamza', 'nationality' => 'Takistan', 'grid_reference' => '046087']);
        $this->person('Autre');
        $v = $this->viewer();
        self::assertSame([$a], $this->ids($this->service->listing($v, ['q' => 'karimzadeh'])));
        self::assertSame([$a], $this->ids($this->service->listing($v, ['q' => 'hamza'])));
        self::assertSame([$a], $this->ids($this->service->listing($v, ['q' => 'IDN-0000' . $a])));
        self::assertSame([$a], $this->ids($this->service->listing($v, ['q' => (string) $a])));
        self::assertSame([$a], $this->ids($this->service->listing($v, ['q' => 'takistan'])));
        self::assertSame([$a], $this->ids($this->service->listing($v, ['q' => '046087'])));
        self::assertSame([], $this->ids($this->service->listing($v, ['q' => 'inconnu'])));
    }

    public function testFiltersStatusClassificationWantedSignedBiometricsNationalityMapAndPeriod(): void
    {
        $a = $this->person('Alpha', 'T', ['status' => 'detenu', 'nationality' => 'TAK', 'classification' => 'interne', 'context_id' => 2]);
        $this->signed($a);
        $b = $this->person('Bravo', 'T', ['status' => 'civil', 'nationality' => 'USA', 'updated_at' => gmdate('Y-m-d H:i:s', self::NOW - 600)]);
        $this->notice($this->identity($b), 'low');
        foreach (['empreintes', 'iris', 'adn'] as $kind) {
            $this->pdo->prepare('INSERT INTO sse_biometric_samples (person_id, tenant_id, kind, quality) VALUES (?, 1, ?, 90)')->execute([$a, $kind]);
        }
        $this->pdo->prepare("INSERT INTO sse_biometric_samples (person_id, tenant_id, kind, quality) VALUES (?, 1, 'iris', 40)")->execute([$b]);
        $v = $this->viewer();
        $one = fn (array $q): array => $this->ids($this->service->listing($v, $q));

        self::assertSame([$a], $one(['status' => 'detenu']));
        self::assertSame([$a], $one(['cls' => 'interne']));
        self::assertSame([$b], $one(['cls' => 'none']));
        self::assertSame([$b], $one(['wanted' => '1']));
        self::assertSame([$a], $one(['wanted' => '0']));
        self::assertSame([$a], $one(['signed' => '1']));
        self::assertSame([$b], $one(['signed' => '0']));
        self::assertSame([$a], $one(['bio' => 'full']));
        self::assertSame([$b], $one(['bio' => 'partial']));
        self::assertSame([$a], $one(['nat' => 'tak']));
        self::assertSame([$a], $one(['ctx' => '2']));
        self::assertSame([$b], $one(['period' => '24h']));
        self::assertSame([$a], $one(['status' => 'detenu', 'wanted' => '0', 'signed' => '1']));
        self::assertSame([], $one(['status' => 'detenu', 'wanted' => '1']));
        // Valeurs inconnues : ignorées, jamais une erreur.
        self::assertCount(2, $one(['status' => 'x', 'cls' => 'x', 'tab' => 'x', 'sort' => 'x', 'p' => '-4']));
    }

    public function testBiometricPillsAreThreeStatesNeverBars(): void
    {
        $a = $this->person('Alpha');
        $this->pdo->prepare("INSERT INTO sse_biometric_samples (person_id, tenant_id, kind, quality) VALUES (?, 1, 'empreintes', 88), (?, 1, 'iris', 30)")->execute([$a, $a]);
        $row = $this->service->listing($this->viewer(), [])['rows'][0];
        self::assertSame(['empreintes' => 'present', 'iris' => 'partial', 'adn' => 'absent'], $row['bio']);
        self::assertFalse($row['bio_complete']);
    }

    public function testSortOrdersAndPagination(): void
    {
        $z = $this->person('Zorro', 'T', ['updated_at' => gmdate('Y-m-d H:i:s', self::NOW - 100)]);
        $m = $this->person('Martin', 'T', ['updated_at' => gmdate('Y-m-d H:i:s', self::NOW - 5000)]);
        $a = $this->person('Alvarez', 'T', ['updated_at' => gmdate('Y-m-d H:i:s', self::NOW - 9000), 'status' => 'prioritaire']);
        $v = $this->viewer();
        self::assertSame([$a, $m, $z], $this->ids($this->service->listing($v, ['sort' => 'nom'])));
        self::assertSame([$z, $m, $a], $this->ids($this->service->listing($v, ['sort' => 'modification'])));
        self::assertSame($a, $this->ids($this->service->listing($v, ['sort' => 'priorite']))[0], 'La personne prioritaire passe devant');
        // Dernière ouverture : celle que CE compte a ouverte en dernier d'abord.
        $this->service->recordOpen($v, $m);
        self::assertSame($m, $this->ids($this->service->listing($v, ['sort' => 'ouverture']))[0]);

        for ($i = 0; $i < 60; $i++) {
            $this->person('Dupont' . str_pad((string) $i, 2, '0', STR_PAD_LEFT));
        }
        $p1 = $this->service->listing($v, ['sort' => 'nom']);
        $p2 = $this->service->listing($v, ['sort' => 'nom', 'p' => '2']);
        self::assertSame(63, $p1['total']);
        self::assertSame(2, $p1['pages']);
        self::assertCount(50, $p1['rows']);
        self::assertCount(13, $p2['rows']);
        self::assertSame(2, $this->service->listing($v, ['p' => '99'])['page'], 'Une page trop loin retombe sur la dernière');
    }

    // ------------------------------------------------------------------ Signature, avis, ouvertures

    public function testSignatureAndNoticeAreCarriedOnEveryRow(): void
    {
        $a = $this->person('Alpha');
        $this->signed($a);
        $b = $this->person('Bravo');
        $identity = $this->identity($b);
        $this->notice($identity, 'critical', 'A-0042');
        $rows = [];
        foreach ($this->service->listing($this->viewer(), [])['rows'] as $r) {
            $rows[$r['id']] = $r;
        }
        self::assertTrue($rows[$a]['signed']);
        self::assertSame('TA1', $rows[$a]['sig']['callsign']);
        self::assertSame('T-1', $rows[$a]['sig']['terminal']);
        self::assertFalse($rows[$b]['signed']);
        self::assertSame('A-0042', $rows[$b]['wanted']['ref']);
        self::assertSame('critical', $rows[$b]['wanted']['threat']);
        self::assertSame($identity, $rows[$b]['wanted']['identity_id']);
        self::assertNull($rows[$a]['wanted']);
    }

    public function testSecretIdentitiesNoticeIsOnlyShownWithTheTruthClearance(): void
    {
        $a = $this->person('Alpha');
        $this->notice($this->identity($a, null, 'cover'));
        $with = $this->service->listing($this->viewer(), [])['rows'][0];
        self::assertNotNull($with['wanted']);
        $without = $this->service->listing(new SsePersonViewer(1, 7, false, true, true, true, true, 'encadrement', false, false, 'X'), [])['rows'][0];
        self::assertNull($without['wanted'], 'Un avis posé sur une couverture ne se révèle pas sans l’habilitation Secret');
    }

    public function testOpeningRecordsOnceAndLastReadNamesTheReaderOnlyForManagers(): void
    {
        $p = $this->person('Alpha');
        $this->pdo->exec("INSERT INTO users (id, tenant_id, display_name, callsign) VALUES (7, 1, 'Moi', 'MOI'), (8, 1, 'Jeanne Martin', 'JEANNE')");
        $me = $this->viewer('confidentiel', 7);
        $other = $this->viewer('confidentiel', 8);

        self::assertTrue($this->service->recordOpen($other, $p));
        self::assertTrue($this->service->recordOpen($me, $p));
        $this->clock += 20;
        self::assertFalse($this->service->recordOpen($me, $p), 'Un rafraîchissement ne compte pas');
        $views = $this->pdo->query('SELECT user_id, open_count FROM sse_person_views ORDER BY user_id')->fetchAll(PDO::FETCH_ASSOC);
        self::assertSame([['user_id' => 7, 'open_count' => 1], ['user_id' => 8, 'open_count' => 1]], $views);
        $this->clock += 120;
        self::assertTrue($this->service->recordOpen($me, $p));
        self::assertSame('2', (string) $this->pdo->query('SELECT open_count FROM sse_person_views WHERE user_id = 7')->fetchColumn());

        $row = $this->service->listing($me, [])['rows'][0];
        self::assertNotNull($row['my_opened_at']);
        self::assertSame('JEANNE (Jeanne Martin)', $row['last_read']['who']);
        $plain = $this->service->listing($this->viewer('confidentiel', 7, true, false), [])['rows'][0];
        self::assertNull($plain['last_read']['who'], 'Le nom du lecteur n’est donné qu’à qui gère la section');
        self::assertNotNull($plain['last_read']['at']);
        self::assertFalse($this->service->recordOpen(new SsePersonViewer(1, null, true, true, false, false, false, 'interne'), $p), 'Un invité n’est pas suivi');
    }

    // ------------------------------------------------------------------ Classification

    public function testClassifyNeverAboveTheEditorsClearanceAndLogsEveryChange(): void
    {
        $p = $this->person('Alpha');
        $editor = $this->viewer('encadrement');
        self::assertSame('ceiling', $this->service->classify($editor, $p, 'confidentiel', '')['code']);
        self::assertTrue($this->service->classify($editor, $p, 'encadrement', '')['ok'], 'Une première classification ne demande pas de motif');
        self::assertSame('same', $this->service->classify($editor, $p, 'encadrement', 'Idem')['code']);
        self::assertSame('reason', $this->service->classify($editor, $p, 'interne', '')['code'], 'Abaisser demande un motif');
        self::assertTrue($this->service->classify($editor, $p, 'interne', 'Information publiée')['ok']);
        self::assertSame('interne', (new SsePersonRepository())->findById($p, 1)['classification']);
        self::assertTrue($this->service->classify($editor, $p, null, 'Classification retirée')['ok']);
        self::assertNull((new SsePersonRepository())->findById($p, 1)['classification']);

        $log = $this->audit('person.classify');
        self::assertCount(3, $log);
        self::assertSame('Classification retirée', $log[0]['reason']);
        self::assertSame(7, (int) $log[0]['actor_user_id']);
        self::assertSame('person', $log[0]['object_type']);
        self::assertSame($p, (int) $log[0]['object_id']);
        self::assertStringContainsString('"classification":"interne"', (string) $log[0]['before_json']);
    }

    public function testCannotTouchAFileClassifiedAboveYourClearance(): void
    {
        $p = $this->person('Secret', 'T', ['classification' => 'tres_restreint']);
        $low = $this->viewer('confidentiel');
        foreach ([
            $this->service->classify($low, $p, 'interne', 'Je baisse'),
            $this->service->lock($low, $p, 'Verrou', null),
            $this->service->close($low, $p, 'resolu', ''),
        ] as $result) {
            self::assertFalse($result['ok']);
            self::assertSame('ceiling', $result['code']);
        }
        self::assertSame([], $this->audit('person.classify'));
    }

    // ------------------------------------------------------------------ Verrouillage

    public function testLockedFileIsRestrictedForUnclearedViewersNoNameNoSearchHit(): void
    {
        $locked = $this->person('Verrouillé-Secret', 'Jean', ['classification' => 'confidentiel', 'locked_at' => '2026-11-01 00:00:00', 'locked_by' => 7, 'lock_reason' => 'Opération en cours']);
        $open = $this->person('Visible');
        $low = $this->viewer('encadrement');
        $listing = $this->service->listing($low, []);
        $row = array_values(array_filter($listing['rows'], static fn (array $r): bool => $r['id'] === $locked))[0];
        self::assertTrue($row['restricted']);
        self::assertSame('', $row['name']);
        self::assertSame('', $row['idn']);
        self::assertArrayNotHasKey('alias', $row);
        self::assertArrayNotHasKey('photo', $row);
        self::assertStringNotContainsString('Verrouillé', json_encode($listing['rows'], JSON_UNESCAPED_UNICODE));
        self::assertSame($open, $listing['rows'][0]['id'], 'Les lignes restreintes ferment la liste');
        self::assertSame([], $this->ids($this->service->listing($low, ['q' => 'verrouillé'])), 'Une recherche ne lit pas à travers le verrou');
        self::assertSame([$open], $this->ids($this->service->listing($low, ['status' => 'civil'])));
        self::assertNull($this->service->detail($low, $locked), 'Même réponse que pour une fiche inexistante');
        self::assertSame('notfound', $this->service->classify($low, $locked, 'interne', 'x')['code']);

        $cleared = $this->viewer('confidentiel');
        $row = array_values(array_filter($this->service->listing($cleared, [])['rows'], static fn (array $r): bool => $r['id'] === $locked))[0];
        self::assertFalse($row['restricted']);
        self::assertSame('Jean Verrouillé-Secret', $row['name']);
        self::assertNotNull($this->service->detail($cleared, $locked));
    }

    public function testManualLockNeedsAReasonAndAClassificationAndCanExpire(): void
    {
        $p = $this->person('Alpha');
        $v = $this->viewer('confidentiel');
        self::assertSame('reason', $this->service->lock($v, $p, '', null, 'encadrement')['code']);
        self::assertSame('classification', $this->service->lock($v, $p, 'Opération en cours', null)['code']);
        self::assertSame('ceiling', $this->service->lock($v, $p, 'Opération en cours', null, 'tres_restreint')['code']);
        self::assertSame('until', $this->service->lock($v, $p, 'Opération en cours', '2020-01-01 00:00')['code']);

        $until = gmdate('Y-m-d H:i', self::NOW + 3600);
        self::assertTrue($this->service->lock($v, $p, 'Opération en cours', $until, 'confidentiel')['ok']);
        $stored = (new SsePersonRepository())->findById($p, 1);
        self::assertSame('confidentiel', $stored['classification']);
        self::assertSame(7, $stored['locked_by']);
        self::assertSame('same', $this->service->lock($v, $p, 'Encore', null)['code']);

        $low = $this->viewer('encadrement');
        self::assertTrue($this->service->listing($low, [])['rows'][0]['restricted']);
        // La date de fin passée, le verrou n'agit plus.
        $this->clock = self::NOW + 7200;
        self::assertFalse($this->service->listing($low, [])['rows'][0]['restricted']);
        self::assertCount(1, $this->audit('person.lock'));
    }

    public function testUnlockIsLoggedAndAutomaticLockCannotBeUnlockedByHand(): void
    {
        $p = $this->person('Alpha', 'T', ['classification' => 'encadrement']);
        $v = $this->viewer('confidentiel');
        self::assertTrue($this->service->lock($v, $p, 'Opération en cours', null)['ok']);
        self::assertSame('reason', $this->service->unlock($v, $p, '')['code']);
        self::assertTrue($this->service->unlock($v, $p, 'Opération terminée')['ok']);
        self::assertNull((new SsePersonRepository())->findById($p, 1)['locked_at']);
        self::assertCount(1, $this->audit('person.unlock'));
        self::assertSame('same', $this->service->unlock($v, $p, 'Encore')['code']);

        $q = $this->person('Beta', 'T', ['classification' => 'confidentiel']);
        self::assertTrue($this->service->setAutoLockLevel($v, 'encadrement')['ok']);
        self::assertSame('encadrement', $this->service->autoLockLevel(1));
        $low = $this->viewer('encadrement');
        $rows = [];
        foreach ($this->service->listing($low, [])['rows'] as $r) {
            $rows[$r['id']] = $r;
        }
        self::assertTrue($rows[$q]['restricted'], 'Au-delà du niveau de la règle : verrouillée d’office');
        self::assertFalse($rows[$p]['restricted'], 'Au niveau de la règle : pas verrouillée');
        $unlock = $this->service->unlock($v, $q, 'Je déverrouille');
        self::assertSame('same', $unlock['code']);
        self::assertStringContainsString('règle de la communauté', $unlock['error']);
        self::assertCount(1, $this->audit('person.autolock_rule'));
        self::assertTrue($this->service->setAutoLockLevel($v, null)['ok']);
        self::assertNull($this->service->autoLockLevel(1));
    }

    public function testAutoLockRuleNeedsManageRightAndCannotExceedOwnLevel(): void
    {
        self::assertFalse($this->service->setAutoLockLevel($this->viewer('confidentiel', 7, true, false), 'interne')['ok']);
        self::assertFalse($this->service->setAutoLockLevel($this->viewer('encadrement'), 'confidentiel')['ok']);
    }

    public function testLockedFileOfAMemberFollowsTheMemberImageGate(): void
    {
        $p = $this->person('Membre');
        $this->identity($p, 50);
        $hidden = $this->makeService([50 => [20]]);
        $peer = $this->viewer('tres_restreint', 11);
        $row = $hidden->listing($peer, [])['rows'][0];
        self::assertTrue($row['restricted'], 'La fiche liée à l’identité d’un membre reste masquée à un collègue, même très habilité');
        self::assertNull($hidden->detail($peer, $p));
        self::assertSame('notfound', $hidden->close($peer, $p, 'resolu', '')['code']);

        $boss = $this->viewer('interne', 20);
        self::assertFalse($hidden->listing($boss, [])['rows'][0]['restricted'], 'Un supérieur du membre la voit');
        $owner = $this->viewer('interne', 50);
        self::assertFalse($hidden->listing($owner, [])['rows'][0]['restricted'], 'Le titulaire la voit');
    }

    // ------------------------------------------------------------------ Clôture

    public function testCloseNeedsAKnownReasonIsLoggedAndCanBeReopened(): void
    {
        $p = $this->person('Alpha');
        $v = $this->viewer();
        self::assertSame('reason', $this->service->close($v, $p, '', '')['code']);
        self::assertSame('reason', $this->service->close($v, $p, 'inconnu', '')['code']);
        foreach (array_keys(SsePersonWork::CLOSE_REASONS) as $code) {
            $id = $this->person('Dossier ' . $code);
            self::assertTrue($this->service->close($v, $id, $code, 'Note')['ok'], $code);
            self::assertSame($code, (new SsePersonRepository())->findById($id, 1)['close_reason']);
        }
        self::assertTrue($this->service->close($v, $p, 'doublon_fusionne', 'Fusionné avec #12')['ok']);
        self::assertSame('same', $this->service->close($v, $p, 'resolu', '')['code']);
        $stored = (new SsePersonRepository())->findById($p, 1);
        self::assertNotNull($stored['closed_at']);
        self::assertSame(7, $stored['closed_by']);
        self::assertSame($p, (int) $this->pdo->query('SELECT id FROM sse_persons WHERE id = ' . $p)->fetchColumn(), 'Jamais de suppression');
        self::assertStringContainsString('Doublon fusionné — Fusionné avec #12', (string) $this->audit('person.close')[0]['reason']);

        self::assertSame('reason', $this->service->reopen($v, $p, '')['code']);
        self::assertTrue($this->service->reopen($v, $p, 'Clôture par erreur')['ok']);
        self::assertNull((new SsePersonRepository())->findById($p, 1)['closed_at']);
        self::assertSame('same', $this->service->reopen($v, $p, 'Encore')['code']);
        self::assertCount(1, $this->audit('person.reopen'));
    }

    public function testCannotCloseAPersonUnderActiveNoticeUnlessTheNoticeIsLifted(): void
    {
        $p = $this->person('Recherché');
        $identity = $this->identity($p);
        $noticeId = $this->notice($identity);
        $v = $this->viewer();
        $denied = $this->service->close($v, $p, 'resolu', '');
        self::assertSame('wanted', $denied['code']);
        self::assertNull((new SsePersonRepository())->findById($p, 1)['closed_at']);

        $noRight = $this->viewer('confidentiel', 7, true, true, false);
        self::assertSame('wanted_right', $this->service->close($noRight, $p, 'resolu', '', true, new SseWantedAccess(false, true, false, true, 1, 7))['code']);
        self::assertSame('wanted_right', $this->service->close($v, $p, 'resolu', '', true, null)['code']);

        $ok = $this->service->close($v, $p, 'neutralise', 'Cible neutralisée', true, new SseWantedAccess(false, true, false, true, 1, 7, 'ANALYSTE'));
        self::assertTrue($ok['ok'], (string) ($ok['error'] ?? ''));
        self::assertNotNull((new SsePersonRepository())->findById($p, 1)['closed_at']);
        $notice = $this->pdo->query('SELECT status, closed_reason FROM sse_wanted_notices WHERE id = ' . $noticeId)->fetch(PDO::FETCH_ASSOC);
        self::assertSame('lifted', $notice['status']);
        self::assertStringContainsString('Neutralisé', (string) $notice['closed_reason']);
    }

    public function testBatchAppliesTheSameRulesToEachFileAndReportsFailures(): void
    {
        $a = $this->person('Alpha');
        $b = $this->person('Bravo', 'T', ['classification' => 'tres_restreint']);
        $c = $this->person('Charlie');
        $this->notice($this->identity($c));
        $v = $this->viewer('confidentiel');
        $res = $this->service->batch($v, 'close', [$a, $b, $c, $a, 0, -3], ['reason_code' => 'resolu', 'note' => 'Lot']);
        self::assertSame([$a], $res['done']);
        self::assertSame(2, $res['failed']);
        self::assertSame(['ceiling', 'wanted'], array_values(array_map(static fn (array $r): string => $r['code'] ?? '', array_slice($res['results'], 1))));
        $undo = $this->service->batch($v, 'reopen', $res['done'], ['reason' => 'Annulation de la clôture']);
        self::assertSame([$a], $undo['done']);
        self::assertNull((new SsePersonRepository())->findById($a, 1)['closed_at']);

        $ids = [];
        for ($i = 0; $i < SsePersonWork::BATCH_MAX + 20; $i++) {
            $ids[] = $this->person('Lot' . $i);
        }
        self::assertCount(SsePersonWork::BATCH_MAX, $this->service->batch($v, 'classify', $ids, ['level' => 'interne'])['done']);
        self::assertFalse($this->service->batch($v, 'rien', [$a], [])['results'][0]['ok']);
    }

    // ------------------------------------------------------------------ Droits, communauté, base sans l'étape 4780

    public function testWritingNeedsTheRightAnAccountAndTheSameCommunity(): void
    {
        $p = $this->person('Alpha');
        $foreign = $this->pdo->prepare("INSERT INTO sse_persons (tenant_id, last_name, first_name, created_at, updated_at) VALUES (2, 'Etrangere', 'T', '2026-11-01', '2026-11-01')");
        $foreign->execute();
        $foreignId = (int) $this->pdo->lastInsertId();
        self::assertSame('right', $this->service->classify($this->viewer('confidentiel', 7, false), $p, 'interne', '')['code']);
        self::assertSame('right', $this->service->close(new SsePersonViewer(1, null, true, true, true, true, true, 'tres_restreint'), $p, 'resolu', '')['code'], 'Une session invitée n’écrit jamais');
        self::assertSame('notfound', $this->service->close($this->viewer(), $foreignId, 'resolu', '')['code'], 'Une fiche d’une autre communauté est introuvable');
        self::assertNull($this->service->detail($this->viewer(), $foreignId));
        self::assertCount(1, $this->service->listing($this->viewer(), [])['rows']);
        self::assertSame([], $this->service->listing(new SsePersonViewer(1, 7, false, false, false, false, false, 'tres_restreint'), [])['rows'], 'Sans le droit de lire : rien');
        self::assertSame([], $this->audit('person.close'));
    }

    public function testWithoutTheWorkColumnsThePageIsReadOnlyWithAClearMessage(): void
    {
        SsePersonWorkSqlite::close();
        $this->pdo = SsePersonWorkSqlite::open(false, false);
        $service = $this->makeService();
        $p = $this->person('Alpha');
        $listing = $service->listing($this->viewer(), []);
        self::assertFalse($listing['ready']);
        self::assertFalse($listing['canClassify']);
        self::assertCount(1, $listing['rows']);
        $res = $service->close($this->viewer(), $p, 'resolu', '');
        self::assertSame('unavailable', $res['code']);
        self::assertStringContainsString('étape', $res['error']);
        self::assertFalse($service->recordOpen($this->viewer(), $p), 'Sans la table des ouvertures, rien n’est écrit');
    }

    public function testGameNeverReceivesClosedOrLockedFiles(): void
    {
        $open = $this->person('Visible');
        $closed = $this->person('Close', 'T', ['closed_at' => '2026-11-01 00:00:00']);
        $locked = $this->person('Verrou', 'T', ['classification' => 'confidentiel', 'locked_at' => '2026-11-01 00:00:00']);
        $auto = $this->person('Auto', 'T', ['classification' => 'tres_restreint']);
        $expired = $this->person('Expire', 'T', ['classification' => 'encadrement', 'locked_at' => '2020-01-01 00:00:00', 'lock_until' => '2020-02-01 00:00:00']);
        (new SsePortalSettingsRepository())->set(1, SsePersonWork::SETTING_AUTO_LOCK, 'confidentiel');
        $repo = new SsePersonRepository();
        $kept = array_column($repo->withoutGameHidden(1, $repo->listAllForTenant(1)), 'id');
        sort($kept);
        self::assertSame([$open, $expired], $kept, 'Un verrou échu n’est plus un verrou');
        self::assertNotContains($closed, $kept);
        self::assertNotContains($locked, $kept);
        self::assertNotContains($auto, $kept, 'Au-delà du niveau de la règle : verrouillée d’office, donc retirée du jeu');
    }

    public function testWorkRepositoryReadinessFollowsTheColumns(): void
    {
        self::assertTrue(SsePersonWorkRepository::ready());
        self::assertTrue(SsePersonWorkRepository::viewsReady());
        SsePersonWorkSqlite::close();
        SsePersonWorkSqlite::open(false, false);
        self::assertFalse(SsePersonWorkRepository::ready());
        self::assertFalse(SsePersonWorkRepository::viewsReady());
    }
}
