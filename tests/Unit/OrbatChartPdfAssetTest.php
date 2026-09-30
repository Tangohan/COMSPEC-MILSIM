<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Organization\OrbatChartPdfService;
use PHPUnit\Framework\TestCase;

final class OrbatChartPdfAssetTest extends TestCase
{
    private function root(): string
    {
        return dirname(__DIR__, 2);
    }

    public function testOrbatPdfExportIsWired(): void
    {
        $root = $this->root();
        $routes = (string) file_get_contents($root . '/routes/web.php');
        $controller = (string) file_get_contents($root . '/app/Controllers/Web/PersonnelController.php');
        $canvas = (string) file_get_contents($root . '/views/partials/orbat/orbat_canvas.php');
        $view = (string) file_get_contents($root . '/views/personnel/orbat_chart_export.php');
        $service = (string) file_get_contents($root . '/app/Services/Organization/OrbatChartPdfService.php');

        self::assertStringContainsString("'/orbat/export'", $routes);
        self::assertStringContainsString("'/orbat/pdf'", $routes);
        self::assertStringContainsString('orbatExport', $controller);
        self::assertStringContainsString('orbatPdf', $controller);
        self::assertStringContainsString('OrbatChartPdfService', $controller);
        self::assertStringContainsString('orbat/export', $canvas);
        self::assertStringContainsString('Exporter PDF', $canvas);
        self::assertStringContainsString('ORGANIZATION CHART (ORBAT)', $view);
        self::assertStringContainsString('COMPOSITION GÉNÉRALE', $view);
        self::assertStringContainsString('SYNTHÈSE EFFECTIFS', $view);
        self::assertStringContainsString('tcpdfFriendlyHtml', $service);
        self::assertStringContainsString('buildDocument', $service);
        self::assertFileExists($root . '/docs/design/ATHENA_ORBAT_Mockup_Full.html');
    }

    public function testBuildDocumentMapsRosterToChartGroups(): void
    {
        if (!class_exists(OrbatChartPdfService::class)) {
            require_once $this->root() . '/app/Services/Organization/OrbatChartPdfService.php';
        }

        $service = new OrbatChartPdfService();
        $doc = $service->buildDocument([
            'label' => 'Command',
            'role' => 'Structure',
            'type' => 'command',
            'unitId' => 0,
            'leader' => '—',
            'mission' => '',
            'strength' => 0,
            'visibleStrength' => 0,
            'members' => [],
            'children' => [
                [
                    'label' => 'CIE ALPHA',
                    'role' => 'A',
                    'type' => 'alpha',
                    'structType' => 'company',
                    'unitId' => 12,
                    'leader' => 'CPT MARTIN',
                    'mission' => 'Manoeuvre',
                    'strength' => 24,
                    'visibleStrength' => 22,
                    'members' => array_fill(0, 8, ['user_id' => 1, 'label' => 'X']),
                    'children' => [
                        [
                            'label' => 'ALPHA 1',
                            'role' => 'A1',
                            'type' => 'alpha',
                            'unitId' => 13,
                            'leader' => 'SGT',
                            'strength' => 8,
                            'visibleStrength' => 7,
                            'members' => array_fill(0, 7, ['user_id' => 2, 'label' => 'Y']),
                            'children' => [
                                [
                                    'label' => 'ALPHA 1-1',
                                    'role' => 'A11',
                                    'type' => 'alpha',
                                    'unitId' => 14,
                                    'leader' => 'CPL',
                                    'strength' => 4,
                                    'visibleStrength' => 4,
                                    'members' => array_fill(0, 4, ['user_id' => 4, 'label' => 'W']),
                                    'children' => [],
                                ],
                                [
                                    'label' => 'ALPHA 1-2',
                                    'role' => 'A12',
                                    'type' => 'alpha',
                                    'unitId' => 15,
                                    'leader' => 'CPL',
                                    'strength' => 3,
                                    'visibleStrength' => 3,
                                    'members' => array_fill(0, 3, ['user_id' => 5, 'label' => 'V']),
                                    'children' => [],
                                ],
                            ],
                        ],
                        [
                            'label' => 'ALPHA 2',
                            'role' => 'A2',
                            'type' => 'alpha',
                            'unitId' => 16,
                            'leader' => 'SGT',
                            'strength' => 6,
                            'visibleStrength' => 6,
                            'members' => array_fill(0, 6, ['user_id' => 6, 'label' => 'U']),
                            'children' => [],
                        ],
                        [
                            'label' => 'ALPHA 3',
                            'role' => 'A3',
                            'type' => 'alpha',
                            'unitId' => 17,
                            'leader' => 'SGT',
                            'strength' => 5,
                            'visibleStrength' => 5,
                            'members' => array_fill(0, 5, ['user_id' => 7, 'label' => 'T']),
                            'children' => [],
                        ],
                        [
                            'label' => 'ALPHA 4',
                            'role' => 'A4',
                            'type' => 'alpha',
                            'unitId' => 18,
                            'leader' => 'SGT',
                            'strength' => 4,
                            'visibleStrength' => 4,
                            'members' => array_fill(0, 4, ['user_id' => 8, 'label' => 'S']),
                            'children' => [],
                        ],
                        [
                            'label' => 'ALPHA 5',
                            'role' => 'A5',
                            'type' => 'alpha',
                            'unitId' => 19,
                            'leader' => 'SGT',
                            'strength' => 4,
                            'visibleStrength' => 4,
                            'members' => array_fill(0, 4, ['user_id' => 9, 'label' => 'R']),
                            'children' => [],
                        ],
                        [
                            'label' => 'ALPHA 6',
                            'role' => 'A6',
                            'type' => 'alpha',
                            'unitId' => 21,
                            'leader' => 'SGT',
                            'strength' => 3,
                            'visibleStrength' => 3,
                            'members' => array_fill(0, 3, ['user_id' => 10, 'label' => 'Q']),
                            'children' => [],
                        ],
                        [
                            'label' => 'ALPHA 7',
                            'role' => 'A7',
                            'type' => 'alpha',
                            'unitId' => 22,
                            'leader' => 'SGT',
                            'strength' => 2,
                            'visibleStrength' => 2,
                            'members' => array_fill(0, 2, ['user_id' => 11, 'label' => 'P']),
                            'children' => [],
                        ],
                    ],
                ],
                [
                    'label' => 'APPUIS',
                    'role' => 'SUP',
                    'type' => 'support',
                    'unitId' => 20,
                    'leader' => 'MAJ',
                    'mission' => 'Soutien',
                    'strength' => 10,
                    'visibleStrength' => 10,
                    'members' => array_fill(0, 4, ['user_id' => 3, 'label' => 'Z']),
                    'children' => [],
                ],
            ],
        ], [
            'unit_label' => 'Task Force PHOENIX',
            'theater' => 'Altis',
            'include_mission' => true,
            'include_legend' => true,
        ]);

        self::assertSame('TASK FORCE PHOENIX', $doc['root']['name']);
        self::assertCount(2, $doc['groups']);
        self::assertSame('CIE ALPHA', $doc['groups'][0]['title']);
        self::assertSame('support', $doc['groups'][1]['type']);
        // Toutes les sous-unités (pas de troncature à 6)
        self::assertCount(7, $doc['groups'][0]['children']);
        self::assertSame('ALPHA 1-1', $doc['groups'][0]['children'][0]['children'][0]['title']);
        self::assertGreaterThanOrEqual(9, $doc['totals']['unit_count']);
        self::assertGreaterThan(0, $doc['totals']['theoretical']);
        self::assertSame('Altis', $doc['meta']['theater']);
        // Jamais A4 — format adaptatif large
        self::assertNotSame('A4', $doc['meta']['paper']);
        self::assertGreaterThanOrEqual(420, (int) $doc['meta']['paper_width_mm']);
        self::assertContains($doc['meta']['paper'], ['A3', 'A2', 'A1', 'A0', 'CUSTOM']);

        $html = $service->tcpdfFriendlyHtml($doc);
        self::assertStringContainsString('ATHENA MILSIM', $html);
        self::assertStringContainsString('CIE ALPHA', $html);
        self::assertStringContainsString('ALPHA 1-1', $html);
        self::assertStringContainsString('ALPHA 7', $html);
        self::assertStringContainsString('COMPOSITION GÉNÉRALE', $html);
        self::assertStringNotContainsString('A4', $html);
    }
}
