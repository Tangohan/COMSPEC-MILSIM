<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class PlatformUsersMultiTenantAssetTest extends TestCase
{
    public function testPersonDossierAndOrphanMarkerAreWiredWithoutHiding(): void
    {
        $root = dirname(__DIR__, 2);

        $repo = (string) file_get_contents($root . '/app/Repositories/UserRepository.php');
        self::assertStringContainsString('listAllMembershipsByEmail', $repo);
        self::assertStringContainsString('emailHasActiveNonDefaultMembership', $repo);
        self::assertStringContainsString("SqlText::notEqualsLiteral(\$pdo, 't.slug', 'default')", $repo);
        self::assertStringContainsString("SqlText::equalsLiteral(\$pdo, 't.slug', 'default')", $repo);
        /* Les orphelins doivent rester visibles dans liste + recherche. */
        self::assertStringNotContainsString('shouldHideOrphanedPlatformAccounts', $repo);
        self::assertStringNotContainsString('hasActiveNonDefaultMembershipPredicate', $repo);

        $controller = (string) file_get_contents($root . '/app/Controllers/Admin/System/SystemUsersController.php');
        self::assertStringContainsString('function showPerson', $controller);
        self::assertStringContainsString('listAllMembershipsByEmail', $controller);
        self::assertStringContainsString('personFileDossiers', $controller);
        self::assertStringContainsString('admin.system.user_person', $controller);

        $routes = (string) file_get_contents($root . '/routes/web.php');
        self::assertStringContainsString("/admin/users/person", $routes);
        self::assertStringContainsString('showPerson', $routes);

        self::assertFileExists($root . '/views/admin/system/user_person.php');
        $view = (string) file_get_contents($root . '/views/admin/system/user_person.php');
        self::assertStringContainsString('Identifiant plateforme', $view);
        self::assertStringContainsString('Appartenance active', $view);
        self::assertStringContainsString('Orphelin', $view);
        self::assertStringContainsString('reste visible dans l’annuaire', $view);
        self::assertStringContainsString('le dossier métier reste propre à chacune', $view);
        self::assertStringNotContainsString('masquée de l’annuaire', $view);
        self::assertStringNotContainsString('Steam (fiche)', $view);

        $list = (string) file_get_contents($root . '/views/admin/system/users.php');
        self::assertStringContainsString('admin/users/person', $list);
        self::assertStringContainsString('Dossier complet', $list);
        self::assertStringContainsString('restent visibles', $list);
        self::assertStringNotContainsString('sont masqués par défaut', $list);

        $orgShow = (string) file_get_contents($root . '/views/admin/organization/users/show.php');
        self::assertStringContainsString('Identifiant plateforme', $orgShow);
        self::assertStringContainsString('Appartenances multi-communautés', $orgShow);
        self::assertStringContainsString('Dossier plateforme complet', $orgShow);
    }

    public function testPlatformDirectoryDoesNotFilterOrphansByDefault(): void
    {
        $repo = (string) file_get_contents(dirname(__DIR__, 2) . '/app/Repositories/UserRepository.php');
        self::assertStringNotContainsString('function shouldHideOrphanedPlatformAccounts', $repo);
        self::assertStringNotContainsString('hasActiveNonDefaultMembershipPredicate', $repo);
    }
}
