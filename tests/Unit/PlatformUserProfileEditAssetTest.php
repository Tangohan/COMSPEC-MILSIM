<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Admin\PlatformUserProfileService;
use PHPUnit\Framework\TestCase;

final class PlatformUserProfileEditAssetTest extends TestCase
{
    public function testRoutesAndControllerExposeFullEdit(): void
    {
        $root = dirname(__DIR__, 2);
        $routes = (string) file_get_contents($root . '/routes/web.php');
        $ctrl = (string) file_get_contents($root . '/app/Controllers/Admin/System/SystemUsersController.php');

        self::assertStringContainsString("/admin/users/{id}/edit", $routes);
        self::assertStringContainsString("/admin/users/{id}/update", $routes);
        self::assertStringContainsString('SystemUsersController::class, \'edit\'', $routes);
        self::assertStringContainsString('SystemUsersController::class, \'update\'', $routes);
        self::assertStringContainsString('function edit', $ctrl);
        self::assertStringContainsString('function update', $ctrl);
        self::assertStringContainsString('PlatformUserProfileService', $ctrl);
        self::assertStringContainsString('admin.system.user_edit', $ctrl);
        self::assertStringContainsString('USER_PROFILE_UPDATED', $ctrl);

        $container = (string) file_get_contents($root . '/app/Core/Container.php');
        self::assertStringContainsString('PlatformUserProfileService::class', $container);
    }

    public function testFormCoversAccountIdentityContactDossierGradeAndSteam(): void
    {
        $root = dirname(__DIR__, 2);
        $view = (string) file_get_contents($root . '/views/admin/system/user_edit.php');

        $requiredNames = [
            'email',
            'password',
            'status',
            'first_name',
            'last_name',
            'callsign',
            'bio',
            'civil_first_name',
            'civil_last_name',
            'phone',
            'birth_date',
            'civil_nationality',
            'discord_handle',
            'timezone',
            'language',
            'public_flag_country_code',
            'blood_type',
            'sex',
            'family_situation',
            'enlistment_date',
            'matricule_internal',
            'nationality_code',
            'professional_category_code',
            'grade_id',
            'preferred_grade_format',
            'primary_unit_id',
            'clearance_level',
            'steam_id',
            'org_role_ids[]',
            'command_notes',
            'deployable',
        ];
        foreach ($requiredNames as $name) {
            self::assertStringContainsString('name="' . $name . '"', $view, 'Champ manquant : ' . $name);
        }

        self::assertStringContainsString('Adresse e-mail', $view);
        self::assertStringContainsString('accountStatusOptions', $view);
        self::assertStringContainsString('status_options', $view);
        self::assertStringContainsString('Identité du personnage', $view);
        self::assertStringContainsString('Identité civile et contact', $view);
        self::assertStringContainsString('Dossier personnel', $view);
        self::assertStringContainsString('Liaison Steam', $view);
        self::assertStringContainsString('Rôles dans la communauté', $view);
        self::assertStringContainsString('Notes de commandement', $view);
        self::assertStringContainsString('Identifiant plateforme', $view);
        self::assertStringNotContainsString('snake_case', $view);
        self::assertStringNotContainsString('JSON', $view);
        self::assertStringNotContainsString('endpoint', $view);
    }

    public function testDirectoryAndDossierLinkToTheEditor(): void
    {
        $root = dirname(__DIR__, 2);
        $list = (string) file_get_contents($root . '/views/admin/system/users.php');
        $person = (string) file_get_contents($root . '/views/admin/system/user_person.php');

        self::assertStringContainsString("admin/users/' . \$siteUid . '/edit", $list);
        self::assertStringContainsString("admin/users/' . \$uid . '/edit", $list);
        self::assertStringContainsString('Modifier', $list);
        self::assertStringContainsString("admin/users/' . \$uid . '/edit", $person);
        self::assertStringContainsString('Modifier la fiche', $person);
        self::assertStringContainsString('Compte actif', $list);
        self::assertStringContainsString('En attente de vérification de l’e-mail', $list);
        self::assertStringContainsString('Compte actif', $person);
    }

    public function testClosedChoiceCatalogsAreHumanFrench(): void
    {
        $statuses = PlatformUserProfileService::accountStatusOptions();
        self::assertSame('Compte actif', $statuses['active']);
        self::assertSame('Compte inactif', $statuses['inactive']);
        self::assertSame('En attente de vérification de l’e-mail', $statuses['pending_verification']);

        $family = PlatformUserProfileService::familySituationOptions();
        self::assertArrayHasKey('Célibataire', $family);
        self::assertArrayHasKey('Marié(e)', $family);

        $languages = PlatformUserProfileService::interfaceLanguageOptions();
        self::assertSame('Français', $languages['fr']);
        self::assertSame('Anglais', $languages['en']);

        $doctrine = PlatformUserProfileService::doctrineOptions();
        self::assertSame('Française', $doctrine['FR']);
        self::assertSame('Américaine', $doctrine['US']);

        $blood = PlatformUserProfileService::bloodTypeOptions();
        self::assertSame('Non renseigné', $blood['']);
        self::assertArrayHasKey('O+', $blood);
    }
}
