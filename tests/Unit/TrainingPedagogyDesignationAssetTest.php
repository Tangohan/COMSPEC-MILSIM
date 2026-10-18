<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Repositories\PedagogyRepository;
use App\Services\Email\EmailEvents;
use App\Services\Training\TenantPedagogyChainGuard;
use App\Services\Training\TrainingPedagogyDesignationService;
use App\Services\Training\TrainingSessionInstructorGuard;
use PHPUnit\Framework\TestCase;

/** Nomination par personne aux étapes de la chaîne pédagogique (commandement / espace formateur). */
final class TrainingPedagogyDesignationAssetTest extends TestCase
{
    private function src(string $rel): string
    {
        return (string) file_get_contents(dirname(__DIR__, 2) . '/' . $rel);
    }

    public function testStepsCoverTheFourKindsWithExplanations(): void
    {
        $steps = TrainingPedagogyDesignationService::steps();
        self::assertSame(PedagogyRepository::DESIGNATION_KINDS, array_keys($steps));
        foreach ($steps as $step) {
            self::assertNotSame('', $step['lead']);
            self::assertNotSame([], $step['can']);
            self::assertNotSame('', $step['mail']);
        }
    }

    public function testDesignatedPeopleCountForTheChain(): void
    {
        $repo = $this->createMock(PedagogyRepository::class);
        $repo->method('countDesignatedUsers')->willReturnCallback(static fn (int $t, string $kind): int => 1);
        $guard = new TenantPedagogyChainGuard($repo);
        self::assertTrue($guard->assessTenantChain(1)['ok']);
    }

    public function testDesignatedAnimatorCanBeAssignedToASession(): void
    {
        $repo = $this->createMock(PedagogyRepository::class);
        $repo->method('userHasDesignation')->willReturn(true);
        self::assertTrue((new TrainingSessionInstructorGuard($repo))->canAssignInstructor(1, 5, 9));
    }

    public function testPagesOfferNamingByIdentityAndEmail(): void
    {
        $trainer = $this->src('views/admin/training/competency_trainer.php');
        self::assertStringContainsString('designate_person', $trainer);
        self::assertStringContainsString('remove_designation', $trainer);
        self::assertStringContainsString('notify_email', $trainer);
        self::assertStringContainsString('Par rôle (avancé)', $trainer);
        self::assertStringContainsString('pedagogy_member_picker.php', $trainer);

        $command = $this->src('views/admin/training/competency_command.php');
        self::assertStringContainsString('designate_referent', $command);
        self::assertStringContainsString('competences/formateur', $command);

        $controller = $this->src('app/Controllers/Web/TrainingCompetencyController.php');
        self::assertStringNotContainsString('La désignation automatique n’est pas disponible', $controller);
        self::assertStringContainsString('designationService->designate', $controller);
    }

    public function testDesignationEmailGoesThroughTheTemplatedMailSystem(): void
    {
        self::assertContains(EmailEvents::TRAINING_PEDAGOGY_DESIGNATION, EmailEvents::EMAIL_EVENTS);
        $email = $this->src('app/Services/EmailService.php');
        self::assertStringContainsString("'training_pedagogy_designation'", $email);
        self::assertFileExists(dirname(__DIR__, 2) . '/views/emails/training_pedagogy_designation.php');
        $svc = $this->src('app/Services/Training/TrainingPedagogyDesignationService.php');
        self::assertStringContainsString('isEmailEventEnabled', $svc);
        self::assertStringContainsString('TrainingUrlDeliverySchema::ensure', $svc);
    }
}
