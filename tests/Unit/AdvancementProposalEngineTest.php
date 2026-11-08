<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Advancement\AdvancementEligibilityService;
use App\Services\Advancement\AdvancementProposalEngine as Engine;
use App\Support\AdvancementCampaignProgress;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

/**
 * Moteur des propositions automatiques : score détaillé, critères manquants en clair, sanctions, seuils, données absentes.
 */
final class AdvancementProposalEngineTest extends TestCase
{
    private const TODAY = '2026-11-02';

    /** @return array<string, mixed> */
    private function eligible(int $months = 14, int $required = 8): array
    {
        return (new AdvancementEligibilityService())->evaluate([
            'current' => ['rank_order' => 1, 'filiere_id' => null, 'obtained_at' => (new DateTimeImmutable(self::TODAY))->modify('-' . $months . ' months')->format('Y-m-d'), 'label' => 'Caporal'],
            'target' => ['rank_order' => 2, 'filiere_id' => null, 'label' => 'Sergent', 'min_time_in_previous_grade_months' => $required, 'advancement_choice_enabled' => true],
        ], new DateTimeImmutable(self::TODAY));
    }

    /** @return array<string, mixed> */
    private function facts(array $over = []): array
    {
        return array_merge([
            'eligibility' => $this->eligible(),
            'seniority_months' => 24,
            'presences' => 6,
            'hours' => 12.0,
            'last_activity' => '2026-10-30',
            'last_activity_source' => 'session Arma',
            'activity_measured' => true,
            'sanctions' => [],
            'absent_until' => null,
        ], $over);
    }

    private function evaluate(array $facts, array $settings = []): array
    {
        return (new Engine())->evaluate(Engine::sanitize($settings), $facts, new DateTimeImmutable(self::TODAY));
    }

    public function testMembreCompletEstProposeAvecDetailDesPointsEtJustification(): void
    {
        $out = $this->evaluate($this->facts());

        self::assertSame(Engine::STATUS_PROPOSE, $out['status']);
        self::assertSame(100, $out['max']);
        $byKey = array_column($out['criteria'], null, 'key');
        self::assertSame(['grade_time', 'seniority', 'presences', 'hours', 'activity', 'conduct'], array_keys($byKey));
        // 14 mois sur 8 + 12 = 20 → 25 × 0,7 = 17,5 → 18 points.
        self::assertSame(18, $byKey['grade_time']['points']);
        self::assertSame(10, $byKey['seniority']['points']);
        self::assertSame(25, $byKey['presences']['points']);
        self::assertSame(20, $byKey['hours']['points']);
        self::assertSame(10, $byKey['conduct']['points']);
        self::assertSame($out['points'], array_sum(array_column($out['criteria'], 'points')));
        self::assertSame((int) round(100 * $out['points'] / $out['max']), $out['score']);
        self::assertStringContainsString('14 mois dans le grade (8 requis)', $out['justification']);
        self::assertStringContainsString('aucune sanction récente', $out['justification']);
        self::assertSame([], $out['missing']);
    }

    public function testTempsDeGradeManquantEstExpliqueAvecLaDateDeLEcheance(): void
    {
        $out = $this->evaluate($this->facts(['eligibility' => $this->eligible(6, 8)]));

        self::assertSame(Engine::STATUS_INELIGIBLE, $out['status']);
        self::assertTrue($out['near'], '2 mois manquants, marge par défaut de 2 mois : bientôt éligible');
        self::assertSame('Manque 2 mois dans le grade (6 sur 8, possible à partir du 2 janvier 2027)', $out['missing'][0]);

        $far = $this->evaluate($this->facts(['eligibility' => $this->eligible(2, 8)]));
        self::assertFalse($far['near']);
    }

    public function testQualificationRequiseManquante(): void
    {
        $eligibility = (new AdvancementEligibilityService())->evaluate([
            'current' => ['rank_order' => 1, 'filiere_id' => null, 'obtained_at' => '2024-01-01'],
            'target' => ['rank_order' => 2, 'filiere_id' => null, 'label' => 'Sergent', 'min_time_in_previous_grade_months' => 8,
                'required_qualification_id' => 4, 'required_qualification_label' => 'Chef d’équipe', 'advancement_choice_enabled' => true],
            'qualification_award' => null,
        ], new DateTimeImmutable(self::TODAY));

        $out = $this->evaluate($this->facts(['eligibility' => $eligibility]));

        // Une qualification manquante est un suivi indicatif : elle n'empêche plus l'avancement (42538b34c).
        self::assertTrue($eligibility['is_eligible']);
        $qualification = array_values(array_filter($eligibility['conditions'], static fn (array $c): bool => $c['key'] === 'qualification'))[0];
        self::assertFalse($qualification['met']);
        self::assertStringContainsString('Chef d’équipe manquante', $qualification['detail']);
        self::assertSame(Engine::STATUS_PROPOSE, $out['status']);
        self::assertFalse($out['near']);
    }

    public function testSanctionsLourdeRecenteOuEnVigueurEcartentEtAvertissementRetireLesPoints(): void
    {
        $suspended = $this->evaluate($this->facts(['sanctions' => [['type' => 'suspend', 'created_at' => '2026-09-01 10:00:00', 'expires_at' => '2026-09-15 10:00:00', 'revoked_at' => null]]]));
        self::assertSame(Engine::STATUS_BLOCKED, $suspended['status']);
        self::assertSame('Suspension le 1 septembre 2026 (moins de 180 jours)', $suspended['missing'][0]);

        $active = $this->evaluate($this->facts(['sanctions' => [['type' => 'mute', 'created_at' => '2025-01-01', 'expires_at' => '2027-01-01', 'revoked_at' => null]]]));
        self::assertSame(Engine::STATUS_BLOCKED, $active['status']);
        self::assertSame('Restriction d’activité en vigueur depuis le 1 janvier 2025', $active['missing'][0]);
        self::assertSame('Restriction d’activité', array_column($active['criteria'], null, 'key')['conduct']['hint']);

        $warned = $this->evaluate($this->facts(['sanctions' => [['type' => 'warn', 'created_at' => '2026-10-01', 'expires_at' => null, 'revoked_at' => null]]]));
        $conduct = array_column($warned['criteria'], null, 'key')['conduct'];
        self::assertSame(0, $conduct['points']);
        self::assertFalse($conduct['blocking']);
        self::assertNotSame(Engine::STATUS_BLOCKED, $warned['status']);

        // Mesure levée ou trop ancienne : ignorée.
        $lifted = $this->evaluate($this->facts(['sanctions' => [
            ['type' => 'ban', 'created_at' => '2026-10-01', 'expires_at' => null, 'revoked_at' => '2026-10-02'],
            ['type' => 'suspend', 'created_at' => '2025-01-01', 'expires_at' => '2025-02-01', 'revoked_at' => null],
        ]]));
        self::assertSame(Engine::STATUS_PROPOSE, $lifted['status']);
        self::assertSame(10, array_column($lifted['criteria'], null, 'key')['conduct']['points']);
    }

    public function testMinimumsDePresenceEtDHeuresNonAtteintsDonnentAExaminer(): void
    {
        $out = $this->evaluate($this->facts(['presences' => 1, 'hours' => 2.5]));

        self::assertSame(Engine::STATUS_REVIEW, $out['status']);
        self::assertContains('Manque 2 présences sur 90 jours (1 sur 3)', $out['missing']);
        self::assertContains('Manque 3,5 h de jeu validées sur 90 jours (2,5 h sur 6 h)', $out['missing']);
    }

    public function testInactiviteEcarteSaufAbsenceDeclaree(): void
    {
        $idle = $this->evaluate($this->facts(['last_activity' => '2026-08-01']));
        self::assertSame(Engine::STATUS_BLOCKED, $idle['status']);
        self::assertContains('Inactif depuis 93 jours (au-delà de 30 jours)', $idle['missing']);

        $away = $this->evaluate($this->facts(['last_activity' => '2026-08-01', 'absent_until' => '2026-11-20']));
        self::assertNotSame(Engine::STATUS_BLOCKED, $away['status']);
        self::assertStringContainsString('Absence déclarée jusqu’au 20 novembre 2026', array_column($away['criteria'], null, 'key')['activity']['detail']);
    }

    public function testSeuilsConfigurablesEtBornes(): void
    {
        $settings = Engine::sanitize(['min_presences' => '10', 'min_score' => 250, 'window_days' => 5, 'inactivity_days' => 'abc']);
        self::assertSame(10, $settings['min_presences']);
        self::assertSame(100, $settings['min_score']);
        self::assertSame(30, $settings['window_days']);
        self::assertSame(Engine::DEFAULTS['inactivity_days'], $settings['inactivity_days']);
        self::assertSame(Engine::DEFAULTS, Engine::settings([]));
        self::assertSame(12, Engine::settings([Engine::SETTINGS_KEY => ['min_hours' => 12]])['min_hours']);

        $strict = $this->evaluate($this->facts(), ['min_presences' => 10]);
        self::assertSame(Engine::STATUS_REVIEW, $strict['status']);

        $high = $this->evaluate($this->facts(['seniority_months' => 2, 'presences' => 3, 'hours' => 6.0]), ['min_score' => 95]);
        self::assertSame(Engine::STATUS_REVIEW, $high['status']);
        self::assertStringStartsWith('Score ', end($high['missing']));

        // 0 = critère non utilisé : il disparaît du score.
        $off = $this->evaluate($this->facts(), ['min_presences' => 0, 'min_hours' => 0, 'inactivity_days' => 0]);
        self::assertSame(['grade_time', 'seniority', 'conduct'], array_column($off['criteria'], 'key'));
        self::assertSame(45, $off['max']);
    }

    public function testCriteresSansDonneesNeSontPasComptes(): void
    {
        $out = $this->evaluate($this->facts(['presences' => null, 'hours' => null, 'activity_measured' => false, 'sanctions' => null, 'seniority_months' => null]));

        self::assertSame(['grade_time'], array_column($out['criteria'], 'key'));
        self::assertSame(25, $out['max']);
        self::assertSame(72, $out['score'], '18 sur 25 ramenés sur 100');
    }

    public function testClassementProposesPuisScorePuisTempsDeGrade(): void
    {
        $engine = new Engine();
        $rows = $engine->rank([
            ['name' => 'C', 'months_in_grade' => 30, 'proposal' => ['status' => Engine::STATUS_INELIGIBLE, 'score' => 99, 'near' => false]],
            ['name' => 'B', 'months_in_grade' => 10, 'proposal' => ['status' => Engine::STATUS_PROPOSE, 'score' => 70, 'near' => false]],
            ['name' => 'A', 'months_in_grade' => 20, 'proposal' => ['status' => Engine::STATUS_PROPOSE, 'score' => 70, 'near' => false]],
            ['name' => 'D', 'months_in_grade' => 5, 'proposal' => ['status' => Engine::STATUS_PROPOSE, 'score' => 90, 'near' => false]],
            ['name' => 'E', 'months_in_grade' => 5, 'proposal' => ['status' => Engine::STATUS_REVIEW, 'score' => 95, 'near' => false]],
        ]);
        self::assertSame(['D', 'A', 'B', 'E', 'C'], array_column($rows, 'name'));
    }

    public function testAvancementDeLaCampagneEnCinqEtapes(): void
    {
        $today = new DateTimeImmutable(self::TODAY);
        $campaign = ['status' => 'ouverte', 'opens_at' => '2026-10-01', 'closes_at' => '2026-11-30', 'quota_slots' => 2];
        $empty = AdvancementCampaignProgress::summarize($campaign, [], $today);
        self::assertSame(2, $empty['step']);
        self::assertSame(28, $empty['days_left']);

        $rows = [
            ['live_eligible' => true, 'decision' => 'inscrit', 'commission_opinion' => 'propose', 'preference_rank' => 1],
            ['live_eligible' => false, 'decision' => '', 'commission_opinion' => '', 'preference_rank' => null],
        ];
        $open = AdvancementCampaignProgress::summarize($campaign, $rows, $today);
        self::assertSame(3, $open['step']);
        self::assertSame(1, $open['eligible']);
        self::assertSame(2, $open['quota']);

        $commission = AdvancementCampaignProgress::summarize(['status' => 'en_commission'] + $campaign, $rows, $today);
        self::assertSame(4, $commission['step']);
        self::assertStringContainsString('1 avis sur 2', $commission['next']);

        $published = AdvancementCampaignProgress::summarize(['status' => 'publiee'] + $campaign, $rows, $today);
        self::assertSame(5, $published['step']);
        self::assertTrue($published['done']);
    }
}
