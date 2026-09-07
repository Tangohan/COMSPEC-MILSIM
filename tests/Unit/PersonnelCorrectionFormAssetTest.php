<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Personnel\PersonnelCorrectionRequestService;
use PHPUnit\Framework\TestCase;

final class PersonnelCorrectionFormAssetTest extends TestCase
{
    public function testFormUsesDossierChromeAndReadableTitle(): void
    {
        $root = dirname(__DIR__, 2);
        $view = (string) file_get_contents($root . '/views/personnel/correction_form.php');
        $controller = (string) file_get_contents($root . '/app/Controllers/Web/PersonnelCorrectionController.php');
        $css = (string) file_get_contents($root . '/public/assets/css/personnel-dossier.css');
        $service = (string) file_get_contents($root . '/app/Services/Personnel/PersonnelCorrectionRequestService.php');

        self::assertStringContainsString('pd-page', $view);
        self::assertStringContainsString('pd-header__title', $view);
        self::assertStringContainsString('Corrections RH', $view);
        self::assertStringContainsString('pd-form-grid', $view);
        self::assertStringContainsString('pd-form-grid__full', $view);
        self::assertStringContainsString('pd-tabs', $view);
        self::assertStringContainsString('name="note"', $view);
        self::assertStringContainsString('Envoyer pour confirmation', $view);
        self::assertStringContainsString('Enregistrer tout de suite', $view);
        self::assertStringContainsString('apply_now', $view);
        self::assertStringContainsString('unit_assignments[', $view);
        self::assertStringContainsString('from_corrections', $view);
        self::assertStringNotContainsString('text-white', $view);
        self::assertStringNotContainsString('bg-slate-900', $view);
        self::assertStringNotContainsString('bg-slate-950', $view);

        self::assertStringContainsString("personnel-dossier.css", $controller);
        self::assertStringContainsString('fieldCatalog', $controller);
        self::assertStringContainsString('fieldLabels($forStaff)', $controller);
        self::assertStringContainsString('listRoleOptionsForSelect', $controller);
        self::assertStringContainsString('function applyDirect', $controller);
        self::assertStringContainsString('function applyDirect', $service);

        self::assertStringContainsString('.pd-header__title', $css);
        self::assertStringContainsString('color: #0f172a', $css);
        self::assertStringContainsString('.pd-form-grid', $css);
        self::assertStringContainsString('.pd-form-grid__full', $css);
        self::assertStringContainsString('.pd-container--narrow', $css);

        self::assertStringContainsString("'identity' => 'Personnage'", $service);
        self::assertStringContainsString('STAFF_ONLY_FIELDS', $service);
        self::assertStringContainsString('applyApprovedPayload', $service);
        self::assertStringContainsString('USER_PROFILE_KEYS', $service);
        self::assertStringContainsString('extra_callsigns_json', $service);
        self::assertStringContainsString('nicknames_json', $service);
        self::assertStringContainsString('userProfiles->upsert', $service);
    }

    public function testCorrectableFieldsCoverDossierIdentityAndPersistOnServer(): void
    {
        $labels = PersonnelCorrectionRequestService::fieldLabels();
        $catalog = PersonnelCorrectionRequestService::fieldCatalog();
        $choices = PersonnelCorrectionRequestService::choiceCatalog();

        foreach ([
            'first_name' => 'Prénom',
            'last_name' => 'Nom',
            'bio' => 'Présentation du personnage',
            'extra_callsigns' => 'Indicatifs secondaires',
            'nicknames' => 'Autres surnoms',
            'rp_operational_function' => 'Fonction sur le dossier',
            'rp_medical_due_date' => 'Échéance visite médicale',
            'callsign' => 'Indicatif radio',
            'enlistment_date' => 'Date d’engagement',
        ] as $key => $label) {
            self::assertArrayHasKey($key, $labels);
            self::assertSame($label, $labels[$key]);
            self::assertArrayHasKey($key, $catalog);
        }

        self::assertSame('select', $catalog['blood_type']['type'] ?? '');
        self::assertSame('select', $catalog['sex']['type'] ?? '');
        self::assertSame('select', $catalog['family_situation']['type'] ?? '');
        self::assertSame('select', $catalog['operator_status']['type'] ?? '');
        self::assertSame('textarea', $catalog['bio']['type'] ?? '');
        self::assertSame(2, (int) ($catalog['bio']['span'] ?? 0));
        self::assertSame('date', $catalog['enlistment_date']['type'] ?? '');
        self::assertSame('date', $catalog['rp_medical_due_date']['type'] ?? '');

        self::assertArrayHasKey('blood_type', $choices);
        self::assertArrayHasKey('sex', $choices);
        self::assertArrayHasKey('family_situation', $choices);
        self::assertArrayHasKey('operator_status', $choices);

        self::assertArrayHasKey('grade_id', $labels);
        self::assertArrayHasKey('unit_assignments', $labels);
        self::assertArrayHasKey('job_roles', $labels);
        self::assertArrayHasKey('rank_display_override', $labels);
        self::assertSame('assignment', $catalog['grade_id']['group'] ?? '');
        self::assertSame('unit_assignments', $catalog['unit_assignments']['type'] ?? '');
        self::assertContains('grade_id', PersonnelCorrectionRequestService::ORBAT_KEYS);
        self::assertContains('unit_assignments', PersonnelCorrectionRequestService::ORBAT_KEYS);

        self::assertArrayNotHasKey('email', $labels);
        self::assertArrayNotHasKey('clearance_level', $labels);
        self::assertArrayNotHasKey('command_notes', $labels);
        self::assertArrayNotHasKey('matricule_internal', $labels);

        $staffLabels = PersonnelCorrectionRequestService::fieldLabels(true);
        $staffCatalog = PersonnelCorrectionRequestService::fieldCatalog(true);
        foreach (['command_notes', 'matricule_internal', 'clearance_level', 'deployable', 'medal_rack', 'rp_tutor_user_id'] as $staffKey) {
            self::assertArrayHasKey($staffKey, $staffLabels);
            self::assertArrayHasKey($staffKey, $staffCatalog);
        }
        self::assertSame('checkbox', $staffCatalog['deployable']['type'] ?? '');
        self::assertSame('command', $staffCatalog['command_notes']['group'] ?? '');
        self::assertSame(4, PersonnelCorrectionRequestService::ASSIGNMENT_SLOT_COUNT);

        $view = (string) file_get_contents(dirname(__DIR__, 2) . '/views/personnel/correction_form.php');
        self::assertStringContainsString("name=\"<?= \$h(\$key) ?>\"", $view);
        self::assertStringContainsString('choiceCatalog', $view);
        self::assertStringContainsString('En attente', $view);
        self::assertStringNotContainsString('snake_case', $view);

        $dispatch = (string) file_get_contents(dirname(__DIR__, 2) . '/app/Support/DevDispatchCatalog.php');
        self::assertStringContainsString('Corriger sa fiche se lit enfin', $dispatch);
    }
}
