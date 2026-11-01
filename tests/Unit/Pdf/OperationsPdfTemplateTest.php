<?php

declare(strict_types=1);

namespace Tests\Unit\Pdf;

use App\Services\Aar\AarReportPdfService;
use App\Services\MissionPlanning\MissionPlanningPdfService;
use App\Services\MissionPlanning\MissionPlanningService;
use App\Services\Replay\ReplayAarPdfService;
use App\Support\TrainingCertificatePdfEngine;
use PHPUnit\Framework\TestCase;

/**
 * Comptes rendus d'opération et bilans de rejeu : A4 portrait, gabarit Athena sur chaque page.
 */
final class OperationsPdfTemplateTest extends TestCase
{
    use AthenaPdfAssertions;

    protected function setUp(): void
    {
        if (!TrainingCertificatePdfEngine::ensureTcpdfLoaded()) {
            self::markTestSkipped('TCPDF indisponible');
        }
    }

    /** @return array<string, mixed> */
    private function report(): array
    {
        return [
            'id' => 40,
            'tenant_id' => 0,
            'reference_code' => 'AAR-2026-040',
            'status' => 'validated',
            'title' => 'Raid nocturne',
            'operation_label' => 'Op. Lame Grise',
            'author_name' => 'CPT Damian Stones',
            'validator_name' => 'LTC Liam Kaiser',
            'reported_at' => '2026-10-15 10:12:00',
            'validated_at' => '2026-10-16 09:00:00',
            'mission_started_at' => '2026-10-14 20:42:00',
            'mission_ended_at' => '2026-10-14 23:30:00',
            'summary_heading' => 'Objectifs 1 à 3 atteints',
            'summary_text' => "Insertion réussie, zone Écho.\nOBJ 4 abandonné, extraction ordonnée.",
            'strengths' => ['Discipline radio exemplaire en phase d’approche.', 'Évacuation sanitaire en moins de 6 minutes.'],
            'weaknesses' => ['Reconnaissance du carrefour insuffisante.', 'Fréquence Bravo saturée en phase 3.'],
            'lessons_learned' => 'Prévoir une reconnaissance des carrefours avant l’assaut.',
            'lessons_context' => 'les raids nocturnes',
            'conclusion_text' => 'Mission remplie à 75 %. Bravo à tous.',
            'open_actions' => [['label' => 'Former deux opérateurs radio supplémentaires', 'owner' => 'SSG Mercer', 'due_at' => '2026-11-20']],
            'closed_actions' => [['label' => 'Mettre à jour la carte du secteur', 'owner' => 'SGT Laurent', 'due_at' => '2026-10-20']],
        ];
    }

    public function testAarReportHtmlKeepsContent(): void
    {
        $html = (new AarReportPdfService())->buildHtml($this->report());

        self::assertStringContainsString('OP. LAME GRISE', $html);
        self::assertStringContainsString('Évacuation sanitaire en moins de 6 minutes.', $html);
        self::assertStringContainsString('Former deux opérateurs radio supplémentaires', $html);
        self::assertStringContainsString('Validé par', $html);
    }

    public function testAarReportPdfIsPortraitWithTemplate(): void
    {
        $response = (new AarReportPdfService())->export($this->report());

        self::assertAthenaTcpdf($response->body(), 'compte-rendu-operation');
    }

    public function testEmptyAarReportStillRenders(): void
    {
        $pdf = (new AarReportPdfService())->buildPdfBinary(['reference_code' => 'AAR-0001', 'status' => 'pending', 'title' => 'Vide']);

        self::assertAthenaTcpdf($pdf, 'compte-rendu-operation-vide');
    }

    public function testReplayAarPdfIsPortraitWithTemplate(): void
    {
        $aar = [
            'summary' => [
                'missionStart' => '2026-10-14 20:42:00', 'missionEnd' => '2026-10-14 23:30:00',
                'operators' => 30, 'allies' => 4, 'phones' => 12, 'beacons' => 2,
                'contactEvents' => 7, 'medevacEvents' => 2, 'orderEvents' => 5, 'markerEvents' => 9,
            ],
            'errors' => [['label' => 'Positions GPS perdues', 'count' => 3]],
            'unitTracks' => [
                ['callsign' => 'Alpha 1-1', 'kindLabel' => 'Opérateur', 'firstSeen' => '2026-10-14 20:42:00', 'lastSeen' => '2026-10-14 23:20:00'],
                ['callsign' => 'Bravo 1-2', 'kindLabel' => 'Véhicule', 'firstSeen' => '2026-10-14 20:50:00', 'lastSeen' => '2026-10-14 23:00:00'],
            ],
            'operationalEvents' => [
                ['timestamp' => '2026-10-14 21:31:00', 'label' => 'Contact au carrefour, 2 blessés pris en charge'],
                ['timestamp' => '2026-10-14 22:10:00', 'label' => 'OBJ 2 et 3 sécurisés'],
            ],
        ];
        $response = ReplayAarPdfService::response($aar, 'Op. Lame Grise');

        self::assertStringContainsString('Contact au carrefour', ReplayAarPdfService::buildHtml($aar, 'Op. Lame Grise'));
        self::assertAthenaTcpdf($response->body(), 'bilan-apres-action-rejeu');
    }
    public function testMissionPlanningPackageIsPortraitWithTemplate(): void
    {
        $slot = static fn (string $cs, string $who): array => ['callsign' => $cs, 'assigned_label' => $who];
        $board = [
            'plan' => [
                'mission_code' => 'OPORD-2026-041', 'operation_name' => 'Op. Aube Rouge', 'title' => 'Aube Rouge',
                'opord_version' => '2', 'task_force_name' => 'JSOC — Task Force Pyrgos', 'dtg' => '032030ANOV26',
                'classification' => 'Confidentiel opération', 'status' => 'draft',
            ],
            'counts' => ['assigned' => 7, 'auth' => 9],
            'tree' => [[
                'element' => ['label' => 'JSOC — PC'],
                'slots' => [$slot('YA1', 'LTC Kaiser')],
                'children' => [
                    ['element' => ['label' => 'Alpha Assault'], 'slots' => [$slot('AA1', 'SSG Mercer'), $slot('AA2', 'Vacant')], 'children' => []],
                    ['element' => ['label' => 'Charlie Appui'], 'slots' => [$slot('CS1', 'SSG Rossi')], 'children' => []],
                ],
            ]],
            'matrix' => [['label' => 'Total', 'auth' => 9, 'assigned' => 7, 'present' => 6, 'absent' => 1, 'attached' => 0]],
            'roster' => [
                ['element_label' => 'Alpha Assault', 'callsign' => 'AA1', 'function_label' => 'Chef d’équipe', 'assigned_label' => 'SSG Mercer', 'presence_label' => 'Présent'],
                ['element_label' => 'Alpha Assault', 'callsign' => 'AA2', 'function_label' => 'Fusilier', 'assigned_label' => 'Vacant', 'presence_label' => ''],
                ['element_label' => 'Charlie Appui', 'callsign' => 'CS1', 'function_label' => 'Chef d’équipe', 'assigned_label' => 'SSG Rossi', 'presence_label' => 'Absent'],
            ],
            'document' => ['situation_enemy' => 'Une cellule ennemie d’environ 40 combattants tient le port de Pyrgos.', 'execution_intent' => 'Fixer au nord, frapper au port de nuit.'],
            'mission_sentence' => 'Le JSOC s’empare du port de Pyrgos le 03/11 avant 23:00.',
        ];
        $service = new MissionPlanningPdfService($this->createStub(MissionPlanningService::class));

        self::assertAthenaTcpdf($service->buildPdfBinary(0, $board), 'ordre-operation-paquet-mission');
    }
}
