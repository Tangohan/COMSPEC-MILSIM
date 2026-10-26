<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Controllers\Admin\EffectifsWorkspaceController;
use App\Services\Export\TablePdfExportService;
use App\Support\TrainingCertificatePdfEngine;
use PHPUnit\Framework\TestCase;

final class TablePdfExportServiceTest extends TestCase
{
    /** @return array<string, mixed> */
    private function doc(): array
    {
        return [
            'title' => 'Effectifs · Médical',
            'community' => '1er RIMa <test>',
            'filters' => [['Fonction', 'Medic'], ['Recherche', ''], ['Tri', 'Ordre de grade']],
            'columns' => [['label' => 'Membre', 'weight' => 2], ['label' => 'Poids', 'align' => 'R']],
            'rows' => [['Jean « Doc » Martin', '82 kg'], ['Alpha', '']],
            'note' => 'Confidentiel : données médicales',
            'filename' => 'effectifs-médical',
        ];
    }

    public function testHtmlCarriesHeaderFiltersAndEscapedCells(): void
    {
        $html = (new TablePdfExportService())->buildHtml($this->doc());

        self::assertStringContainsString('1ER RIMA &lt;TEST&gt;', $html);
        self::assertStringContainsString('Effectifs · Médical', $html);
        self::assertStringContainsString('<b>Fonction</b> : Medic', $html);
        self::assertStringNotContainsString('Recherche', $html, 'un filtre vide ne s\'affiche pas');
        self::assertStringContainsString('<thead>', $html, 'en-tête répété à chaque page');
        self::assertStringContainsString('Jean « Doc » Martin', $html);
        self::assertStringContainsString('2 lignes', $html);
        self::assertStringContainsString('width="66.67%"', $html);
    }

    public function testEmptyRowsAndNoFilters(): void
    {
        $doc = $this->doc();
        $doc['rows'] = [];
        $doc['filters'] = [];
        $html = (new TablePdfExportService())->buildHtml($doc);

        self::assertStringContainsString('Aucun filtre', $html);
        self::assertStringContainsString('Aucune ligne ne correspond', $html);
    }

    public function testSafeFilename(): void
    {
        self::assertSame('effectifs-medical-2026-10-26', TablePdfExportService::safeFilename('effectifs-médical 2026-10-26'));
        self::assertSame('export', TablePdfExportService::safeFilename('«»'));
    }

    public function testRealPdfIsProduced(): void
    {
        if (!TrainingCertificatePdfEngine::ensureTcpdfLoaded()) {
            self::markTestSkipped('TCPDF indisponible');
        }
        $response = (new TablePdfExportService())->export($this->doc());
        $body = $response->body();

        self::assertStringStartsWith('%PDF-', $body);
    }

    public function testRosterMedicalTableColumnsAndValues(): void
    {
        [$columns, $cells, $label] = EffectifsWorkspaceController::rosterPdfTable('medical', [[
            'display_name' => 'Jean Martin',
            'callsign' => 'Doc',
            'grade_short' => 'SGT',
            'job_role_display' => 'Medic',
            'blood_type' => 'O+',
            'weight_kg' => 80,
            'height_cm' => 180,
            'drug_intolerances' => '',
            'allergies' => 'Pénicilline',
        ]]);

        self::assertSame('Médical', $label);
        self::assertSame('Groupe sanguin', $columns[3]['label']);
        self::assertSame(["Jean Martin\nDoc", 'SGT', 'Medic', 'O+', '80 kg', '180 cm', '24,7', 'Aucune connue', 'Pénicilline'], $cells[0]);
    }

    public function testRosterAviationTableAddsAircraftColumns(): void
    {
        [$columns, $cells] = EffectifsWorkspaceController::rosterPdfTable('aviation', [[
            'display_name' => 'Maverick',
            'pilot_seconds' => 9000,
            'pilot_families' => [['key' => 'heli', 'label' => 'Hélicoptère', 'seconds' => 9000]],
            'pilot_last_flown_at' => '2026-10-20 21:00:00',
        ]]);

        self::assertSame(['Membre', 'Fonction', 'Affectation', 'Heures de vol', 'Hélicoptère', 'Dernier vol'], array_column($columns, 'label'));
        self::assertSame('2 h 30', $cells[0][3]);
        self::assertSame('20/10/2026', $cells[0][5]);
    }
}
