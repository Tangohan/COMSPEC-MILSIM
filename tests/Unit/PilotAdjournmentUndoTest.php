<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Personnel\PilotQualificationEngine as Eng;
use App\Services\Personnel\QualificationStatusTransitionService;
use App\Support\QualificationAdminStatus as Status;
use PHPUnit\Framework\TestCase;

/**
 * Annuler un ajournement fait par erreur : l'échec peut redevenir une proposition, jamais une qualification obtenue.
 */
final class PilotAdjournmentUndoTest extends TestCase
{
    public function testFailedAwardCanGoBackToWaitingStatesButNotStraightToObtained(): void
    {
        foreach ([Status::CANDIDATE, Status::IN_TRAINING, Status::IN_EVALUATION] as $to) {
            self::assertTrue(Status::canTransition(Status::FAILED, $to), $to);
        }
        self::assertFalse(Status::canTransition(Status::FAILED, Status::OBTAINED));
        self::assertFalse(Status::canTransition(Status::REVOKED, Status::IN_EVALUATION));
        self::assertFalse(Status::canTransition(Status::OBTAINED, Status::IN_EVALUATION));
    }

    public function testPreviousStatusIsReadFromTheHistoryNewestFirst(): void
    {
        $history = [
            ['details' => 'De En formation vers Échouée — Ajourné par un instructeur'],
            ['details' => 'De En évaluation vers En formation'],
            ['details' => 'Attribution : En évaluation'],
        ];
        self::assertSame(Status::IN_TRAINING, QualificationStatusTransitionService::statusBeforeFailure($history));
        self::assertSame(Status::IN_EVALUATION, QualificationStatusTransitionService::statusBeforeFailure([['details' => 'De En évaluation vers Échouée — Refusée par un responsable']]));
        self::assertSame(Status::IN_EVALUATION, QualificationStatusTransitionService::statusBeforeFailure([]), 'historique absent : retour sûr en évaluation');
    }

    public function testBoardItemsExposeTheStatusSoOnlyAdjournedOnesOfferTheUndo(): void
    {
        $rule = ['definition_id' => 10, 'scope' => 'kind:heli', 'track' => 'heli', 'min_scope_hours' => 2, 'min_track_hours' => 0, 'prereq_definition_id' => null];
        $failed = Eng::evaluate($rule, [], [10 => ['id' => 7, 'admin_status' => 'failed']]);
        $revoked = Eng::evaluate($rule, [], [10 => ['id' => 8, 'admin_status' => 'revoked']]);
        self::assertSame(Eng::BLOCKED, $failed['state']);
        self::assertSame(Status::FAILED, $failed['admin_status']);
        self::assertSame(Status::REVOKED, $revoked['admin_status']);
    }

    public function testRoutesFormsAndControllersAreProtected(): void
    {
        $root = dirname(__DIR__, 2);
        $routes = (string) file_get_contents($root . '/routes/web.php');
        self::assertMatchesRegularExpression('#post\(\'/back-office/referentiels/qualifications/cursus-pilote/propositions/\{awardId\}/annuler\'.*OrganizationAdminMiddleware#', $routes);
        self::assertMatchesRegularExpression('#post\(\'/back-office/referentiels/qualifications/propositions/\{awardId\}/annuler\'.*OrganizationAdminMiddleware#', $routes);

        $ctl = (string) file_get_contents($root . '/app/Controllers/Admin/Organization/PilotSyllabusController.php');
        self::assertMatchesRegularExpression('#function reopen\(.*?\$this->guarded\(.*?service->reopen\(#s', $ctl, 'CSRF et formule payante via guarded()');
        $rules = (string) file_get_contents($root . '/app/Controllers/Admin/Organization/QualificationRulesController.php');
        self::assertMatchesRegularExpression('#function reopen\(.*?guardBackOfficeRight.*?\$this->post\(#s', $rules);

        foreach (['views/admin/airspace/index.php', 'views/admin/organization/qualifications/pilot_syllabus.php', 'views/admin/organization/qualifications/show.php'] as $view) {
            $src = (string) file_get_contents($root . '/' . $view);
            self::assertStringContainsString('/annuler', $src, $view);
            self::assertStringContainsString('Annuler l’ajournement ?', $src, $view . ' : confirmation');
        }
    }

    public function testServiceRefusesWhatCannotBeUndone(): void
    {
        $src = (string) file_get_contents(dirname(__DIR__, 2) . '/app/Services/Personnel/QualificationStatusTransitionService.php');
        self::assertStringContainsString('Cet ajournement ne peut plus être annulé : la qualification est maintenant', $src);
        self::assertStringContainsString('une autre attribution de cette qualification a été délivrée ou ouverte depuis', $src);
        self::assertStringContainsString('Ajournement annulé', $src, 'trace dans l’historique de l’attribution');
    }
}
