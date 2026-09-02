<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Repositories\ForumReportRepository;
use App\Repositories\TrainingCourseRepository;
use App\Repositories\UserRepository;
use App\Services\Community\CommunityReportService;
use PHPUnit\Framework\TestCase;

final class CommunityReportServiceTest extends TestCase
{
    public function testPortalHelpAcceptsSelectedTargetAndMember(): void
    {
        $reports = $this->createMock(ForumReportRepository::class);
        $courses = $this->createMock(TrainingCourseRepository::class);
        $users = $this->createMock(UserRepository::class);

        $users->expects(self::once())
            ->method('findById')
            ->with(77, 10)
            ->willReturn(['id' => 77, 'display_name' => 'Orion']);

        $reports->expects(self::once())
            ->method('create')
            ->with(
                10,
                22,
                null,
                null,
                self::stringContains('Membre ciblé : Orion (n° 77)'),
                self::anything(),
                self::anything(),
                'https://milsim.test/personnel/orion',
                'portal_help'
            )
            ->willReturn(501);

        $service = new CommunityReportService($reports, $courses, $users);
        $result = $service->submit(10, 22, 'portal_help', [
            'help_subject' => 'profile',
            'reference_note' => 'caserne Alpha',
            'selected_member_id' => 77,
            'selected_target_url' => 'https://milsim.test/personnel/orion',
            'selected_target_kind' => 'member_profile',
            'reason' => 'other',
            'details' => 'Le profil cible contient une erreur importante.',
            'page_url' => 'https://milsim.test/hub',
        ], 'milsim.test');

        self::assertTrue($result['ok']);
        self::assertSame(501, $result['report_id']);
    }

    public function testPortalHelpRejectsExternalSelectedUrl(): void
    {
        $reports = $this->createMock(ForumReportRepository::class);
        $courses = $this->createMock(TrainingCourseRepository::class);
        $users = $this->createMock(UserRepository::class);

        $reports->expects(self::never())->method('create');

        $service = new CommunityReportService($reports, $courses, $users);
        $result = $service->submit(10, 22, 'portal_help', [
            'help_subject' => 'page_content',
            'selected_target_url' => 'https://external.example/phishing',
            'reason' => 'suspicious_link',
            'details' => 'Lien suspect publié sur le portail.',
            'page_url' => 'https://milsim.test/forum',
        ], 'milsim.test');

        self::assertFalse($result['ok']);
        self::assertSame('Le lien ciblé doit appartenir à ce site.', $result['error']);
    }

    public function testOrgAnomalyIsForwardedToOrganizationManagement(): void
    {
        $reports = $this->createMock(ForumReportRepository::class);
        $courses = $this->createMock(TrainingCourseRepository::class);
        $users = $this->createMock(UserRepository::class);

        $reports->expects(self::once())
            ->method('create')
            ->with(
                10,
                22,
                null,
                null,
                self::stringContains('Anomalie transmise à la gestion — thème : Fiche, grade ou unité'),
                'other',
                self::anything(),
                'https://milsim.test/dashboard',
                'org_anomaly'
            )
            ->willReturn(612);

        $service = new CommunityReportService($reports, $courses, $users);
        $result = $service->submit(10, 22, 'org_anomaly', [
            'help_subject' => 'fiche',
            'reference_note' => 'grade incorrect sur la fiche',
            'reason' => 'other',
            'details' => 'Le grade affiché ne correspond plus à la situation actuelle du membre.',
            'page_url' => 'https://milsim.test/dashboard',
        ], 'milsim.test');

        self::assertTrue($result['ok']);
        self::assertSame(612, $result['report_id']);
    }

    public function testOrgAnomalyRejectsUnknownSubject(): void
    {
        $reports = $this->createMock(ForumReportRepository::class);
        $courses = $this->createMock(TrainingCourseRepository::class);
        $users = $this->createMock(UserRepository::class);

        $reports->expects(self::never())->method('create');

        $service = new CommunityReportService($reports, $courses, $users);
        $result = $service->submit(10, 22, 'org_anomaly', [
            'help_subject' => 'sql_dump',
            'details' => 'Description suffisamment longue pour passer.',
            'page_url' => 'https://milsim.test/dashboard',
        ], 'milsim.test');

        self::assertFalse($result['ok']);
        self::assertSame('Indiquez de quel type d’anomalie il s’agit.', $result['error']);
    }

    public function testSiteSupportRequestIsRoutedToPlatformAdministration(): void
    {
        $reports = $this->createMock(ForumReportRepository::class);
        $courses = $this->createMock(TrainingCourseRepository::class);
        $users = $this->createMock(UserRepository::class);

        $reports->expects(self::once())
            ->method('create')
            ->with(
                10,
                22,
                null,
                null,
                self::stringContains('Demande organisateur → administration site — thème : Compte supprimé encore visible'),
                'other',
                self::anything(),
                'https://milsim.test/dashboard',
                'site_support_request'
            )
            ->willReturn(701);

        $service = new CommunityReportService($reports, $courses, $users);
        $result = $service->submit(10, 22, 'site_support_request', [
            'help_subject' => 'compte_fantome',
            'reference_note' => 'compte #441',
            'reason' => 'other',
            'details' => 'Le compte supprimé apparaît encore dans l’annuaire effectifs.',
            'page_url' => 'https://milsim.test/dashboard',
        ], 'milsim.test');

        self::assertTrue($result['ok']);
        self::assertSame(701, $result['report_id']);
    }

    public function testSiteSupportRequestRejectsUnknownSubject(): void
    {
        $reports = $this->createMock(ForumReportRepository::class);
        $courses = $this->createMock(TrainingCourseRepository::class);
        $users = $this->createMock(UserRepository::class);

        $reports->expects(self::never())->method('create');

        $service = new CommunityReportService($reports, $courses, $users);
        $result = $service->submit(10, 22, 'site_support_request', [
            'help_subject' => 'sql_dump',
            'details' => 'Description suffisamment longue pour passer.',
            'page_url' => 'https://milsim.test/dashboard',
        ], 'milsim.test');

        self::assertFalse($result['ok']);
        self::assertSame('Indiquez le type de demande à transmettre.', $result['error']);
    }
}
