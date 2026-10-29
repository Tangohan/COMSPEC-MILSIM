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
        self::assertStringContainsString('toujours requis', $view);
        self::assertStringContainsString('data-perm-locked', $view);
        self::assertStringContainsString('clés de la communauté', $view);
        self::assertStringNotContainsString('endpoint', $view);
        self::assertStringNotContainsString('JSON', $view);
        self::assertStringNotContainsString('slug', strtolower($view));

        $js = (string) file_get_contents($root . '/public/assets/js/perm-check-all.js');
        self::assertStringContainsString('data-perm-locked', $js);
        self::assertStringContainsString('toggleable', $js);

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

    public function testFonctionsPageExplainsIndependentJobsAndOrbat(): void
    {
        $root = dirname(__DIR__, 2);
        $view = \App\Core\Response::view('admin.effectifs_workspace.fonctions', [
            'canManageAssignments' => true, 'creatingJobRole' => true, 'csrfToken' => str_repeat('a', 64),
            'jobStructureOptions' => ['ready' => true, 'roles' => [], 'units' => [], 'grades' => [['id' => 10, 'code' => 'FR_CNE', 'label_long' => 'Capitaine local']], 'specialties' => [], 'kinds' => ['employment' => 'Emploi', 'function' => 'Fonction']],
        ])->body();
        $ctrl = (string) file_get_contents($root . '/app/Controllers/Admin/EffectifsWorkspaceController.php');
        $pjr = (string) file_get_contents($root . '/app/Controllers/Admin/Organization/PersonnelJobRoleAdminController.php');

        self::assertStringContainsString('ORBAT', $view);
        self::assertStringContainsString('indépendante de ses emplois de dossier', $view);
        self::assertStringContainsString('Créer un emploi ou une fonction', $view);
        self::assertStringContainsString('Qui tient quel emploi', $view);
        self::assertStringContainsString('name="parent_job_role_id"', $view);
        self::assertStringContainsString('name="linked_unit_id"', $view);
        self::assertStringContainsString('name="grade_id"', $view);
        self::assertStringContainsString('FR_CNE · Capitaine local', $view);
        self::assertStringContainsString('name="specialty_id"', $view);
        self::assertStringNotContainsString('Référentiel des emplois', $view);
        self::assertStringNotContainsString('slug', strtolower($view));
        self::assertStringNotContainsString('JSON', $view);
        self::assertStringNotContainsString('back-office/personnel-job-roles', $view);

        self::assertStringContainsString("function createJobRole", $ctrl);
        self::assertStringContainsString("function saveJobRole", $ctrl);

        self::assertStringContainsString("return Response::redirect(effectifs_workspace_url('fonctions'));", $pjr);
        self::assertStringContainsString("return Response::redirect(effectifs_workspace_url('fonctions') . '?vue=attributions');", $pjr);
    }
}
