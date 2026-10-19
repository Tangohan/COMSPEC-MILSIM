<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Personnel\Pass\PassConditionEngine as E;
use App\Support\PassConditionSummary;
use PHPUnit\Framework\TestCase;

/** Formulaire PASS : champs par type, phrases lisibles, essai sur un membre. */
final class PassFormRefonteTest extends TestCase
{
    public function testEveryCatalogTypeShowsOnlyItsFields(): void
    {
        $known = ['training', 'qualification', 'validity', 'grade', 'hour_category', 'avis', 'bilan', 'window', 'threshold'];
        foreach (E::catalog() as $row) {
            $fields = E::fieldsFor($row['type']);
            self::assertNotEmpty($fields, $row['type']);
            foreach ($fields as $f) {
                self::assertContains($f, $known);
            }
        }
        self::assertSame(['training'], E::fieldsFor(E::TYPE_LMS));
        self::assertSame(['bilan', 'threshold', 'window'], E::fieldsFor(E::TYPE_BILAN_RATING));
        self::assertSame([], E::fieldsFor('inconnu'));

        $inFamilies = array_merge(...array_values(array_map(static fn (array $f): array => $f['types'], E::families())));
        sort($inFamilies);
        $all = array_column(E::catalog(), 'type');
        sort($all);
        self::assertSame($all, $inFamilies, 'chaque type apparaît dans une famille de la palette');
    }

    public function testSentencesNameTheChosenItemsAndFlagWhatIsMissing(): void
    {
        $lookups = ['courses' => [3 => 'JTAC'], 'qualifications' => [7 => 'Contrôleur aérien'], 'grades' => [2 => 'Sergent-chef [SCH]']];
        $s = PassConditionSummary::describe(['condition_type' => E::TYPE_LMS, 'training_module_id' => 3], $lookups);
        self::assertSame('A validé la formation « JTAC »', $s['text']);
        self::assertSame('', $s['missing']);

        $s = PassConditionSummary::describe(['condition_type' => E::TYPE_QUALIFICATION, 'qualification_id' => 7, 'require_validity' => 1], $lookups);
        self::assertSame('Détient la qualification « Contrôleur aérien », encore valable', $s['text']);

        $s = PassConditionSummary::describe(['condition_type' => E::TYPE_VALIDATED_HOURS, 'threshold_value' => '40.00'], $lookups);
        self::assertSame('Au moins 40 h de jeu validées', $s['text']);

        $s = PassConditionSummary::describe(['condition_type' => E::TYPE_CATEGORY_HOURS, 'threshold_value' => 10], $lookups);
        self::assertSame('Choisissez le type d’heure.', $s['missing']);

        $s = PassConditionSummary::describe(['condition_type' => E::TYPE_BILAN_RATING, 'bilan_kind' => 'commandement', 'threshold_value' => 3.5, 'window_days' => 180], $lookups);
        self::assertSame('Moyenne des bilans « Commandement » d’au moins 3,5 sur les 180 derniers jours', $s['text']);

        self::assertSame('Choisissez la formation.', PassConditionSummary::describe(['condition_type' => E::TYPE_LMS], $lookups)['missing']);
        self::assertSame('Choisissez le grade minimum.', PassConditionSummary::describe(['condition_type' => E::TYPE_MIN_GRADE], $lookups)['missing']);
        self::assertSame('Choisissez le type de condition.', PassConditionSummary::describe(['condition_type' => ''], $lookups)['missing']);
    }

    public function testFormKeepsPostedArraysAlignedAndWiresTheTest(): void
    {
        $root = dirname(__DIR__, 2);
        $view = (string) file_get_contents($root . '/views/admin/organization/passes_form.php');
        // Chaque carte contient tous les tableaux postés : les index restent alignés côté serveur.
        foreach (['condition_type[]', 'training_module_id[]', 'qualification_id[]', 'require_validity[]', 'grade_id[]', 'hour_category[]', 'avis_kind[]', 'bilan_kind[]', 'threshold_value[]', 'window_days[]', 'session_kind[]'] as $name) {
            self::assertStringContainsString('name="' . $name . '"', $view, $name);
        }
        self::assertStringContainsString('Csrf::field()', $view);
        self::assertStringContainsString('pass-form.js', $view);
        self::assertStringContainsString('data-pass-test', $view);

        $routes = (string) file_get_contents($root . '/routes/web.php');
        self::assertStringContainsString("'/back-office/organisation/passes/{id}/test', [PersonnelPassAdminController::class, 'test'], [AuthMiddleware::class, OrganizationAdminMiddleware::class]", $routes);
        $ctrl = (string) file_get_contents($root . '/app/Controllers/Admin/Organization/PersonnelPassAdminController.php');
        self::assertStringContainsString('findByIdsForTenant($tenantId, [$userId])', $ctrl);
        self::assertStringContainsString("'backOfficePageCss' => ['back-office-pass-form.css']", $ctrl);
        self::assertFileExists($root . '/public/assets/css/back-office-pass-form.css');
    }
}
