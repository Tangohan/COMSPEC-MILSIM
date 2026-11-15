<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Repositories\SseSuggestionQueueRepository;
use App\Support\SseSignalIdentity;
use App\Support\SseSignalSchema;
use PDO;
use PHPUnit\Framework\TestCase;
use Tests\Support\SseTriageSqlite;

/**
 * Source du bruit de « À trier » : un signal du moteur est une OBSERVATION. Un seul signal ouvert par (dossier, type, règle,
 * variante) ; le passage nocturne le met à jour au lieu d'en créer un autre ; une condition disparue le clôt ; une complétude
 * faible inchangée passe en veille. Vraie base sqlite, vrai dépôt, vraies colonnes de l'étape 4740.
 */
final class SseSignalDedupTest extends TestCase
{
    private const PHOTO = 'Lacune / besoin proposé : Photographie manquante';
    private const BIO = 'Lacune / besoin proposé : Biométrie manquante';

    private PDO $pdo;
    private string $now = '2026-11-01 02:00:00';

    protected function setUp(): void
    {
        $this->pdo = SseTriageSqlite::open(true);
    }

    protected function tearDown(): void
    {
        SseTriageSqlite::close();
    }

    private function repo(): SseSuggestionQueueRepository
    {
        return new SseSuggestionQueueRepository(null, fn (): string => $this->now);
    }

    /** @param array<string, mixed> $over */
    private function gap(SseSuggestionQueueRepository $q, string $title, string $run, int $case = 5, int $tenant = 1, array $over = []): ?int
    {
        return $q->addSignal($tenant, $over + ['case_id' => $case, 'signal_type' => 'gap_auto', 'severity' => 'medium', 'title' => $title, 'detail' => 'BESOIN — Disposer d’une photographie exploitable.', 'rule_key' => 'auto_gap', 'run_id' => $run]);
    }

    private function countRows(string $where = '1=1'): int
    {
        return (int) $this->pdo->query('SELECT COUNT(*) FROM sse_engine_signals WHERE ' . $where)->fetchColumn();
    }

    public function testTheSameObservationEveryNightKeepsASingleOpenSignal(): void
    {
        $q = $this->repo();
        $ids = [];
        for ($night = 1; $night <= 30; $night++) {
            $this->now = gmdate('Y-m-d H:i:s', strtotime('2026-10-01 02:00:00 UTC') + $night * 86400);
            $ids[] = $this->gap($q, self::PHOTO, 'run' . $night);
        }

        self::assertSame(1, $this->countRows(), 'avant : 30 lignes, un signal par nuit');
        self::assertSame(1, count(array_unique($ids)), 'toujours le même identifiant');
        $row = $this->pdo->query('SELECT * FROM sse_engine_signals')->fetch(PDO::FETCH_ASSOC);
        self::assertSame('open', $row['status']);
        self::assertSame('run30', $row['run_id'], 'mis à jour au lieu de recréé');
        self::assertSame(30, (int) $row['seen_count']);
        self::assertSame('2026-10-31 02:00:00', $row['last_seen_at'], 'dernière observation');
        self::assertSame('2026-10-02 02:00:00', $row['created_at'], 'la source garde sa date de création');
    }

    public function testDifferentGapsCasesAndCommunitiesStayDistinct(): void
    {
        $q = $this->repo();
        $this->gap($q, self::PHOTO, 'r1');
        $this->gap($q, self::BIO, 'r1');
        $this->gap($q, self::PHOTO, 'r1', 6);
        $this->gap($q, self::PHOTO, 'r1', 5, 2);

        self::assertSame(4, $this->countRows(), 'type de lacune, dossier et communauté font l’identité');
    }

    public function testTheScoreOfALowCompletenessSignalIsNotPartOfItsIdentity(): void
    {
        $q = $this->repo();
        $base = ['case_id' => 5, 'signal_type' => 'completeness', 'severity' => 'medium', 'detail' => 'Le dossier présente des trous majeurs.', 'rule_key' => 'low_completeness'];
        $a = $q->addSignal(1, $base + ['title' => 'Complétude faible (22/100)', 'run_id' => 'r1']);
        $b = $q->addSignal(1, $base + ['title' => 'Complétude faible (18/100)', 'run_id' => 'r2']);

        self::assertSame($a, $b);
        self::assertSame(1, $this->countRows());
        self::assertSame('Complétude faible (18/100)', (string) $this->pdo->query('SELECT title FROM sse_engine_signals')->fetchColumn(), 'le score est mis à jour');
        self::assertSame('Complétude faible', SseSignalIdentity::shortTitle('completeness', 'Complétude faible (18/100)'));
    }

    public function testAResolvedSignalDoesNotBlockANewOneWhenTheConditionComesBack(): void
    {
        $q = $this->repo();
        $first = $this->gap($q, self::PHOTO, 'r1');
        self::assertSame(1, $q->resolveUnobserved(1, 5, 'r2'), 'photo ajoutée : plus observée');
        $again = $this->gap($q, self::PHOTO, 'r3');

        self::assertNotSame($first, $again, 'la condition réapparaît : nouveau signal, donc nouvel élément à trier');
        self::assertSame(1, $this->countRows("status = 'open'"));
        self::assertSame(1, $this->countRows("status = 'resolved'"));
    }

    public function testResolutionRecordsWhenAndByWhomAndOnlyTouchesUnobservedSignals(): void
    {
        $q = $this->repo();
        $this->gap($q, self::PHOTO, 'r1');
        $this->gap($q, self::BIO, 'r1');
        $this->gap($q, self::PHOTO, 'r1', 9);
        $this->now = '2026-11-02 02:00:00';
        $this->gap($q, self::BIO, 'r2');
        $closed = $q->resolveUnobserved(1, 5, 'r2');

        self::assertSame(1, $closed, 'seule la photo du dossier 5 a disparu');
        $photo = $this->pdo->query("SELECT * FROM sse_engine_signals WHERE title LIKE '%Photographie%' AND case_id = 5")->fetch(PDO::FETCH_ASSOC);
        self::assertSame('resolved', $photo['status']);
        self::assertSame('cerbere-detection', $photo['resolved_by']);
        self::assertSame('2026-11-02 02:00:00', $photo['resolved_at']);
        self::assertSame(1, $this->countRows("status = 'open' AND case_id = 9"), 'un autre dossier n’est pas touché');
        self::assertSame(1, $this->countRows("status = 'open' AND case_id = 5"));
    }

    public function testALowCompletenessUnchangedForThirtyDaysGoesDormantAndAChangeReopensIt(): void
    {
        $q = $this->repo();
        $base = ['case_id' => 5, 'signal_type' => 'completeness', 'severity' => 'medium', 'detail' => 'Trous majeurs.', 'rule_key' => 'low_completeness'];
        $this->now = '2026-10-01 02:00:00';
        $id = (int) $q->addSignal(1, $base + ['title' => 'Complétude faible (22/100)', 'run_id' => 'r1']);
        $this->gap($q, self::PHOTO, 'r1');
        $this->now = '2026-10-20 02:00:00';
        $q->addSignal(1, $base + ['title' => 'Complétude faible (22/100)', 'run_id' => 'r2']);
        self::assertSame(0, $q->expireStale(1, 30), '19 jours : trop tôt');

        $this->now = '2026-11-05 02:00:00';
        $q->addSignal(1, $base + ['title' => 'Complétude faible (22/100)', 'run_id' => 'r3']);
        self::assertSame(1, $q->expireStale(1, 30), 'inchangée depuis 35 jours : en veille');
        self::assertSame('dormant', (string) $this->pdo->query('SELECT status FROM sse_engine_signals WHERE id = ' . $id)->fetchColumn());
        self::assertSame(1, $this->countRows("signal_type = 'gap_auto' AND status = 'open'"), 'seule la complétude expire');
        self::assertSame([], array_filter($q->listOpenSignalsDeduped(1), static fn (array $s): bool => (int) $s['id'] === $id), 'plus dans la liste à trier');

        $this->now = '2026-11-06 02:00:00';
        $q->addSignal(1, $base + ['title' => 'Complétude faible (31/100)', 'run_id' => 'r4']);
        self::assertSame('open', (string) $this->pdo->query('SELECT status FROM sse_engine_signals WHERE id = ' . $id)->fetchColumn(), 'le score a changé : le signal se rouvre');
    }

    public function testADormantSignalStaysDormantWhileNothingChanges(): void
    {
        $q = $this->repo();
        $base = ['case_id' => 5, 'signal_type' => 'completeness', 'severity' => 'medium', 'detail' => 'Trous majeurs.', 'rule_key' => 'low_completeness', 'title' => 'Complétude faible (22/100)'];
        $this->now = '2026-09-01 02:00:00';
        $id = (int) $q->addSignal(1, $base + ['run_id' => 'r1']);
        $this->now = '2026-11-01 02:00:00';
        $q->expireStale(1, 30);
        $q->addSignal(1, $base + ['run_id' => 'r2']);

        self::assertSame('dormant', (string) $this->pdo->query('SELECT status FROM sse_engine_signals WHERE id = ' . $id)->fetchColumn());
        self::assertSame(1, $this->countRows());
    }

    public function testExistingDuplicatesAreMergedIntoTheOldestWhenTheEngineObservesThem(): void
    {
        foreach ([1, 2, 3] as $night) {
            $this->pdo->prepare("INSERT INTO sse_engine_signals (tenant_id, case_id, signal_type, severity, title, detail, status, rule_key, run_id, created_at, updated_at) VALUES (1, 5, 'gap_auto', 'medium', ?, 'BESOIN — photo', 'open', 'auto_gap', ?, ?, ?)")
                ->execute([self::PHOTO, 'old' . $night, '2026-10-0' . $night . ' 02:00:00', '2026-10-0' . $night . ' 02:00:00']);
        }
        $q = $this->repo();
        $survivor = $this->gap($q, self::PHOTO, 'new');

        self::assertSame(1, $survivor, 'le plus ancien est la source');
        self::assertSame(1, $this->countRows("status = 'open'"));
        self::assertSame(2, $this->countRows("status = 'merged' AND CAST(merged_into AS INTEGER) = 1"));
    }

    public function testListOpenSignalsDedupedShowsOneLinePerIdentityEvenBeforeTheCatchUp(): void
    {
        foreach ([1, 2, 3, 4] as $night) {
            $this->pdo->prepare("INSERT INTO sse_engine_signals (tenant_id, case_id, signal_type, severity, title, detail, status, rule_key, run_id, created_at, updated_at) VALUES (1, 5, 'gap_auto', 'medium', ?, 'BESOIN — photo', 'open', 'auto_gap', 'r', ?, ?)")
                ->execute([self::PHOTO, '2026-10-0' . $night . ' 02:00:00', '2026-10-0' . $night . ' 02:00:00']);
        }
        $this->pdo->prepare("INSERT INTO sse_engine_signals (tenant_id, case_id, signal_type, severity, title, status, rule_key, created_at, updated_at) VALUES (1, 6, 'anomaly', 'high', 'Contradiction non résolue', 'open', 'single_divergence', '2026-10-05 02:00:00', '2026-10-05 02:00:00')")->execute();
        $list = $this->repo()->listOpenSignalsDeduped(1);

        self::assertCount(2, $list);
        self::assertSame('anomaly', $list[0]['signal_type'], 'le plus grave d’abord');
        self::assertSame(3, $list[1]['duplicates']);
        self::assertSame(1, (int) $list[1]['id'], 'identifiant de la source');
        self::assertSame('2026-10-01 02:00:00', $list[1]['created_at'], 'âge de la source');
    }

    public function testWithoutTheStepTheEngineStillDeduplicatesAndCloses(): void
    {
        SseTriageSqlite::close();
        $this->pdo = SseTriageSqlite::open(false);
        self::assertFalse(SseSignalSchema::has('dedup_key'));
        $q = $this->repo();
        $this->gap($q, self::PHOTO, 'r1');
        $this->gap($q, self::PHOTO, 'r2');
        self::assertSame(1, $this->countRows(), 'dédoublonné sans colonne de suivi');
        self::assertSame(1, $q->resolveUnobserved(1, 5, 'r3'));
        self::assertSame(1, $this->countRows("status = 'resolved'"), 'statut seul');
    }

    public function testAMergedOrResolvedSignalIsNeverListedNorCounted(): void
    {
        $q = $this->repo();
        $this->gap($q, self::PHOTO, 'r1');
        $this->gap($q, self::BIO, 'r1');
        $q->resolveUnobserved(1, 5, 'r2');

        self::assertSame(0, $q->countOpenSignals(1));
        self::assertSame([], $q->listSignals(1, ['limit' => 50]));
    }

    public function testEveryEngineSignalTypeHasAnIdentityWithoutDetailNoise(): void
    {
        $q = $this->repo();
        $aging = ['case_id' => 5, 'signal_type' => 'aging', 'severity' => 'info', 'title' => 'Renseignement vieilli — valeur historique conservée', 'rule_key' => 'intel_aging'];
        $q->addSignal(1, $aging + ['detail' => 'Appréciation #4 âgée de 31 jours.', 'run_id' => 'r1']);
        $q->addSignal(1, $aging + ['detail' => 'Appréciation #4 âgée de 32 jours.', 'run_id' => 'r2']);
        $q->addSignal(1, $aging + ['detail' => 'Appréciation #9 âgée de 45 jours.', 'run_id' => 'r2']);

        self::assertSame(2, $this->countRows(), 'une appréciation vieillie = un signal, le nombre de jours le met à jour');
    }
}
