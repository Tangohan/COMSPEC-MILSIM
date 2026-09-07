<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class EffectifsRolesUiAssetTest extends TestCase
{
    public function testRolesPagePresentsTheThreeAccessLevels(): void
    {
        $root = dirname(__DIR__, 2);
        $view = (string) file_get_contents($root . '/views/admin/effectifs_workspace/roles.php');
        $member = (string) file_get_contents($root . '/views/admin/effectifs_workspace/member.php');

        self::assertStringContainsString('Niveaux d’accès', $view);
        self::assertStringContainsString('Comme un rôle Discord', $view);
        self::assertStringContainsString('permission_ids[]', $view);
        self::assertStringContainsString('Nouveau niveau', $view);
        self::assertStringNotContainsString('endpoint', $view);
        self::assertStringNotContainsString('JSON', $view);
        self::assertStringNotContainsString('slug', strtolower($view));

        self::assertStringContainsString('name="access_role_id"', $member);
        self::assertStringContainsString('type="radio"', $member);
        self::assertStringContainsString('Corriger ce qu’un niveau a le droit de faire', $member);
        self::assertStringNotContainsString('name="role_ids[]"', $member);

        $rail = (string) file_get_contents($root . '/views/admin/effectifs_workspace/partials/effectifs_lms_rail.php');
        $shell = (string) file_get_contents($root . '/views/admin/effectifs_workspace/shell.php');
        self::assertStringContainsString('Accès<em>Niveaux et droits de la communauté</em>', $rail);
        self::assertStringContainsString('Emplois<em>Libellés du dossier, pas des droits</em>', $rail);
        self::assertStringNotContainsString('Droits d’accès<em>', $rail);
        self::assertStringContainsString("['roles', 'Accès', 'roles', 0]", $shell);
        self::assertStringContainsString("['fonctions', 'Emplois', 'fonctions', 0]", $shell);
        self::assertStringNotContainsString("'droits'", $shell);
    }

    public function testFonctionsPageExplainsJobsComeFromOrbat(): void
    {
        $root = dirname(__DIR__, 2);
        $view = (string) file_get_contents($root . '/views/admin/effectifs_workspace/fonctions.php');
        $ctrl = (string) file_get_contents($root . '/app/Controllers/Admin/EffectifsWorkspaceController.php');
        $pjr = (string) file_get_contents($root . '/app/Controllers/Admin/Organization/PersonnelJobRoleAdminController.php');

        self::assertStringContainsString('organigramme', $view);
        self::assertStringContainsString('ORBAT', $view);
        self::assertStringContainsString('Ce ne sont pas des droits d’accès', $view);
        self::assertStringContainsString('Nouvel emploi', $view);
        self::assertStringContainsString('Qui tient quel emploi', $view);
        self::assertStringContainsString('effectifs_workspace_url(\'fonctions/nouveau\')', $view);
        self::assertStringNotContainsString('Référentiel des emplois', $view);
        self::assertStringNotContainsString('slug', strtolower($view));
        self::assertStringNotContainsString('JSON', $view);
        self::assertStringNotContainsString('back-office/personnel-job-roles', $view);

        self::assertStringContainsString("function createJobRole", $ctrl);
        self::assertStringContainsString("function saveJobRole", $ctrl);
        self::assertStringContainsString("'jobsVue' => 'attributions'", $ctrl);

        self::assertStringContainsString("return Response::redirect(effectifs_workspace_url('fonctions'));", $pjr);
        self::assertStringContainsString("return Response::redirect(effectifs_workspace_url('fonctions') . '?vue=attributions');", $pjr);
    }
}
