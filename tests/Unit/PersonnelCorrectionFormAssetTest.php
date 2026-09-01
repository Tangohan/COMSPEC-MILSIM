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
        self::assertStringContainsString('Correction RH', $view);
        self::assertStringContainsString('pd-form-grid', $view);
        self::assertStringContainsString('pd-form-grid__full', $view);
        self::assertStringContainsString('name="note"', $view);
        self::assertStringContainsString('Envoyer pour confirmation', $view);
        self::assertStringNotContainsString('text-white', $view);
        self::assertStringNotContainsString('bg-slate-900', $view);
        self::assertStringNotContainsString('bg-slate-950', $view);

        self::assertStringContainsString("personnel-dossier.css", $controller);
        self::assertStringContainsString('fieldCatalog', $controller);
        self::assertStringContainsString("array_keys(PersonnelCorrectionRequestService::fieldLabels())", $controller);

        self::assertStringContainsString('.pd-header__title', $css);
        self::assertStringContainsString('color: #0f172a', $css);
        self::assertStringContainsString('.pd-form-grid', $css);
        self::assertStringContainsString('.pd-form-grid__full', $css);
        self::assertStringContainsString('.pd-container--narrow', $css);

        self::assertStringContainsString('Identité du personnage', $service);
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

        self::assertArrayNotHasKey('email', $labels);
        self::assertArrayNotHasKey('clearance_level', $labels);
        self::assertArrayNotHasKey('command_notes', $labels);
        self::assertArrayNotHasKey('matricule_internal', $labels);

        $view = (string) file_get_contents(dirname(__DIR__, 2) . '/views/personnel/correction_form.php');
        self::assertStringContainsString("name=\"<?= \$h(\$key) ?>\"", $view);
        self::assertStringContainsString('choiceCatalog', $view);
        self::assertStringContainsString('En attente', $view);
        self::assertStringNotContainsString('snake_case', $view);

        $dispatch = (string) file_get_contents(dirname(__DIR__, 2) . '/app/Support/DevDispatchCatalog.php');
        self::assertStringContainsString('Corriger sa fiche se lit enfin', $dispatch);
    }
}
