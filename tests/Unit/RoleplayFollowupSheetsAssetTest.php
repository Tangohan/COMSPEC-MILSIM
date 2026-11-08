<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Personnel\PhaseRules\PhaseRuleEngine;
use App\Support\RoleplayFollowupBoard;
use PHPUnit\Framework\TestCase;

/**
 * Bureau de suivi et parcours RH refaits : rien n'a disparu (actions, champs envoyés), et le calcul « à traiter » est juste.
 */
final class RoleplayFollowupSheetsAssetTest extends TestCase
{
    private static function root(): string
    {
        return dirname(__DIR__, 2);
    }

    public function testBoardKeepsEveryActionOfTheOldTable(): void
    {
        $src = (string) file_get_contents(self::root() . '/views/admin/organization/roleplay_followup.php');
        foreach (['/stage', '/tutor', '/validate', '/bilan'] as $action) {
            self::assertStringContainsString("' . \$uid . '" . $action . "'", $src, $action);
        }
        foreach (['name="rp_followup_stage"', 'name="rp_tutor_user_id"', '_csrf_token', "url('personnel/'", 'back-office/roleplay-followup/echeances', 'back-office/users'] as $needle) {
            self::assertStringContainsString($needle, $src, $needle);
        }
        foreach (['function', 'track', 'origin', 'notes', 'interview', 'medical', 'rotation', 'bilanNext', 'bilanLast', 'eligible', 'status', 'progress', 'event'] as $fact) {
            self::assertStringContainsString('data-sheet="' . $fact . '"', $src, $fact);
        }
    }

    public function testBoardShowsNoEmailAndOffersOneDialogForAllMembers(): void
    {
        $view = (string) file_get_contents(self::root() . '/views/admin/organization/roleplay_followup.php');
        $controller = (string) file_get_contents(self::root() . '/app/Controllers/Admin/Organization/RoleplayFollowupAdminController.php');
        self::assertSame(1, substr_count($view, '<dialog'));
        self::assertStringNotContainsString("['email']", $view);
        self::assertStringNotContainsString("\$u['email']", $controller);
        self::assertFileExists(self::root() . '/public/assets/js/roleplay-followup-board.js');
        self::assertFileExists(self::root() . '/public/assets/css/back-office-roleplay-suivi.css');
    }

    public function testNextActionIsTheNearestDateAndOverdueIsDetected(): void
    {
        $row = ['next_interview_date' => '2026-12-01', 'medical_due_date' => '2026-11-01', 'service_rotation_date' => '', 'rp_bilan_next_due_at' => '2027-01-01'];
        self::assertSame(['label' => 'Visite médicale', 'date' => '2026-11-01', 'overdue' => true], RoleplayFollowupBoard::nextAction($row, '2026-11-08'));
        self::assertNull(RoleplayFollowupBoard::nextAction(['next_interview_date' => '0000-00-00'], '2026-11-08'));
    }

    public function testFiltersSortMembersByWhatTheyNeed(): void
    {
        $today = '2026-11-08';
        $late = ['tracked' => true, 'tutor_id' => 4, 'medical_due_date' => '2026-01-01'];
        $noTutor = ['tracked' => true, 'tutor_id' => 0];
        $untracked = ['tracked' => false, 'tutor_id' => 0, 'eligible' => true];
        self::assertEqualsCanonicalizing(['tous', 'retard', 'a-traiter'], RoleplayFollowupBoard::filtersOf($late, $today));
        self::assertEqualsCanonicalizing(['tous', 'sans-tuteur', 'a-traiter'], RoleplayFollowupBoard::filtersOf($noTutor, $today));
        self::assertEqualsCanonicalizing(['tous', 'prets', 'non-suivis'], RoleplayFollowupBoard::filtersOf($untracked, $today));
        $counts = RoleplayFollowupBoard::counts([$late, $noTutor, $untracked], $today);
        self::assertSame(3, $counts['tous']);
        self::assertSame(2, $counts['a-traiter']);
        self::assertSame(1, $counts['non-suivis']);
    }

    public function testStagePositionFollowsTheCommunityList(): void
    {
        self::assertSame(2, RoleplayFollowupBoard::stagePosition(' Tutorat ', ['Accueil', 'Tutorat']));
        self::assertSame(0, RoleplayFollowupBoard::stagePosition('Ancienne', ['Accueil', 'Tutorat']));
    }

    public function testPhasePageKeepsEveryPostedFieldAndCoversEveryConditionType(): void
    {
        $view = (string) file_get_contents(self::root() . '/views/admin/organization/roleplay_phase_rules.php');
        foreach (['phase_action', 'add_phase', 'save_rules', 'reorder', 'phase_order[]', 'phase_id', 'phase_label', 'phase_status', 'phase_active', 'rule_logic', 'rule_effect'] as $needle) {
            self::assertStringContainsString($needle, $view, $needle);
        }
        foreach (['condition_type', 'threshold_value', 'window_days', 'qualification_id', 'require_validity', 'training_module_id', 'hour_category', 'session_kind'] as $field) {
            self::assertStringContainsString('][' . $field . ']', $view, $field);
        }
        $js = (string) file_get_contents(self::root() . '/public/assets/js/roleplay-phase-rules.js');
        $fields = PhaseRuleEngine::conditionFields();
        foreach (PhaseRuleEngine::catalog() as $row) {
            self::assertArrayHasKey($row['type'], $fields, $row['type']);
            self::assertStringContainsString("case '" . $row['type'] . "'", $js, $row['type']);
        }
    }

    public function testQualificationFallbackQueryIsValidUnderOnlyFullGroupBy(): void
    {
        $src = (string) file_get_contents(self::root() . '/app/Controllers/Admin/Organization/PersonnelPhaseAdminController.php');
        self::assertStringContainsString('SELECT MIN(id) AS id, qualification_name AS label', $src);
    }
}
