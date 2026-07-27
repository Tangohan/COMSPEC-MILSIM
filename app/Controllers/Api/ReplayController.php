<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Request;
use App\Core\Response;
use App\Services\Replay\ReplayService;
use App\Support\TrainingCertificatePdfEngine;

class ReplayController
{
    public function __construct(
        private ReplayService $replayService
    ) {
    }

    public function mission(Request $request, array $params = []): Response
    {
        $missionId = $params['missionId'] ?? $request->query('missionId') ?? '';
        if ($missionId === '') {
            return Response::json(['error' => 'missionId required'], 400);
        }
        $from = $request->query('from');
        $to = $request->query('to');
        $data = $this->replayService->getTimeline($missionId, $from ?: null, $to ?: null);
        return Response::json($data);
    }

    public function events(Request $request, array $params = []): Response
    {
        $missionId = $params['missionId'] ?? $request->query('missionId') ?? '';
        if ($missionId === '') {
            return Response::json(['error' => 'missionId required'], 400);
        }
        $from = $request->query('from');
        $to = $request->query('to');
        return Response::json($this->replayService->getEvents($missionId, $from ?: null, $to ?: null));
    }

    public function aar(Request $request, array $params = []): Response
    {
        $missionId = $params['missionId'] ?? $request->query('missionId') ?? '';
        if ($missionId === '') {
            return Response::json(['error' => 'missionId required'], 400);
        }
        $from = $request->query('from');
        $to = $request->query('to');
        return Response::json($this->replayService->buildAfterActionReview($missionId, $from ?: null, $to ?: null));
    }

    public function aarExportPdf(Request $request, array $params = []): Response
    {
        $missionId = $params['missionId'] ?? $request->query('missionId') ?? '';
        if ($missionId === '') {
            return Response::json(['error' => 'missionId required'], 400);
        }
        $from = $request->query('from');
        $to = $request->query('to');
        $aar = $this->replayService->buildAfterActionReview($missionId, $from ?: null, $to ?: null);

        return TrainingCertificatePdfEngine::suppressTcpdfPhpDeprecationsWhile(function () use ($missionId, $aar): Response {
            if (!TrainingCertificatePdfEngine::ensureTcpdfLoaded()) {
                return Response::json(['error' => 'pdf_engine_unavailable'], 503);
            }

            $pdf = new \TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
            $pdf->SetCreator('COMSPEC');
            $pdf->SetAuthor('COMSPEC Overwatch');
            $pdf->SetTitle('AAR ' . $missionId);
            $pdf->SetMargins(12, 12, 12);
            $pdf->AddPage();

            $summary = $aar['summary'] ?? [];
            $errors = $aar['errors'] ?? [];
            $intelTimeline = $aar['intelTimeline'] ?? [];
            $opsEvents = $aar['operationalEvents'] ?? [];

            $html = '<h1>Bilan après-action</h1>';
            $html .= '<p><strong>Mission :</strong> ' . htmlspecialchars($missionId, ENT_QUOTES, 'UTF-8') . '</p>';
            $html .= '<p><strong>Fenêtre :</strong> ' . htmlspecialchars((string) ($aar['window']['from'] ?? 'Début'), ENT_QUOTES, 'UTF-8') . ' → ' . htmlspecialchars((string) ($aar['window']['to'] ?? 'Fin'), ENT_QUOTES, 'UTF-8') . '</p>';
            $html .= '<h3>Synthèse</h3><ul>';
            $html .= '<li>Début : ' . htmlspecialchars((string) ($summary['missionStart'] ?? '—'), ENT_QUOTES, 'UTF-8') . '</li>';
            $html .= '<li>Fin : ' . htmlspecialchars((string) ($summary['missionEnd'] ?? '—'), ENT_QUOTES, 'UTF-8') . '</li>';
            $html .= '<li>Unités : ' . (int) ($summary['unitCount'] ?? 0) . '</li>';
            $html .= '<li>Instantanés de position : ' . (int) ($summary['positionSamples'] ?? 0) . '</li>';
            $html .= '<li>Contacts / signalements : ' . (int) ($summary['contactEvents'] ?? $summary['intelEvents'] ?? 0) . '</li>';
            $html .= '<li>MEDEVAC : ' . (int) ($summary['medevacEvents'] ?? 0) . '</li>';
            $html .= '<li>Ordres : ' . (int) ($summary['orderEvents'] ?? 0) . '</li>';
            $html .= '<li>Repères carte : ' . (int) ($summary['markerEvents'] ?? 0) . '</li>';
            $html .= '<li>Délai médian de réaction (s) : ' . (($summary['medianReactionDelaySeconds'] ?? null) !== null ? (int) $summary['medianReactionDelaySeconds'] : '—') . '</li>';
            $html .= '</ul>';

            $html .= '<h3>Points d’attention</h3>';
            if (is_array($errors) && $errors !== []) {
                $html .= '<ul>';
                foreach ($errors as $error) {
                    $label = htmlspecialchars((string) ($error['label'] ?? 'Alerte'), ENT_QUOTES, 'UTF-8');
                    $count = (int) ($error['count'] ?? 0);
                    $html .= '<li>' . $label . ' (' . $count . ')</li>';
                }
                $html .= '</ul>';
            } else {
                $html .= '<p>Aucun état critique automatique détecté.</p>';
            }

            $html .= '<h3>Chronologie opérationnelle (extrait)</h3>';
            if (is_array($opsEvents) && $opsEvents !== []) {
                $html .= '<ol>';
                $max = min(40, count($opsEvents));
                for ($i = 0; $i < $max; $i++) {
                    $evt = $opsEvents[$i];
                    $ts = htmlspecialchars((string) ($evt['timestamp'] ?? ''), ENT_QUOTES, 'UTF-8');
                    $lab = htmlspecialchars((string) ($evt['label'] ?? $evt['type'] ?? 'Événement'), ENT_QUOTES, 'UTF-8');
                    $html .= '<li>' . $ts . ' — ' . $lab . '</li>';
                }
                $html .= '</ol>';
            } elseif (is_array($intelTimeline) && $intelTimeline !== []) {
                $html .= '<ol>';
                $max = min(25, count($intelTimeline));
                for ($i = 0; $i < $max; $i++) {
                    $evt = $intelTimeline[$i];
                    $ts = htmlspecialchars((string) ($evt['timestamp'] ?? ''), ENT_QUOTES, 'UTF-8');
                    $target = htmlspecialchars((string) ($evt['targetType'] ?? ''), ENT_QUOTES, 'UTF-8');
                    $source = htmlspecialchars((string) ($evt['source'] ?? ''), ENT_QUOTES, 'UTF-8');
                    $html .= '<li>' . $ts . ' — ' . $target . ' (source : ' . $source . ')</li>';
                }
                $html .= '</ol>';
            } else {
                $html .= '<p>Aucun événement opérationnel enregistré.</p>';
            }

            $pdf->SetTitle('Bilan après-action ' . $missionId);
            $pdf->writeHTML($html, true, false, true, false, '');
            $binary = (string) $pdf->Output('', 'S');

            return (new Response())
                ->header('Content-Type', 'application/pdf')
                ->header('Content-Disposition', 'attachment; filename="Bilan-' . preg_replace('/[^a-zA-Z0-9_-]+/', '-', $missionId) . '.pdf"')
                ->setBody($binary);
        });
    }
}
