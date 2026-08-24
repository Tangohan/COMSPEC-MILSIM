<?php

declare(strict_types=1);

namespace App\Services\Replay;

use App\Core\Response;
use App\Support\TrainingCertificatePdfEngine;

/**
 * Bilan après-action — document lisible (pas un dump technique).
 */
final class ReplayAarPdfService
{
    /**
     * @param array<string, mixed> $aar
     */
    public static function response(array $aar, string $title = ''): Response
    {
        return TrainingCertificatePdfEngine::suppressTcpdfPhpDeprecationsWhile(function () use ($aar, $title): Response {
            if (!TrainingCertificatePdfEngine::ensureTcpdfLoaded()) {
                return Response::json(['error' => 'pdf_engine_unavailable'], 503);
            }

            $title = self::cleanTitle($title);
            $summary = is_array($aar['summary'] ?? null) ? $aar['summary'] : [];
            $errors = is_array($aar['errors'] ?? null) ? $aar['errors'] : [];
            $tracks = is_array($aar['unitTracks'] ?? null) ? $aar['unitTracks'] : [];
            $ops = is_array($aar['operationalEvents'] ?? null) ? $aar['operationalEvents'] : [];

            $pdf = new class ('P', 'mm', 'A4', true, 'UTF-8', false) extends \TCPDF {
                public function Header(): void
                {
                }

                public function Footer(): void
                {
                    $this->SetY(-12);
                    $this->SetFont('helvetica', '', 8);
                    $this->SetTextColor(90, 100, 108);
                    $this->Cell(90, 6, 'Athena — Bilan après-action', 0, 0, 'L');
                    $this->Cell(0, 6, 'Page ' . $this->getAliasNumPage() . ' / ' . $this->getAliasNbPages(), 0, 0, 'R');
                }
            };

            $pdf->SetCreator('Athena');
            $pdf->SetAuthor('Athena');
            $pdf->SetTitle($title);
            $pdf->setPrintHeader(false);
            $pdf->setPrintFooter(true);
            $pdf->SetMargins(14, 16, 14);
            $pdf->SetAutoPageBreak(true, 18);
            $pdf->AddPage();

            $pdf->SetFillColor(8, 16, 22);
            $pdf->Rect(0, 0, 210, 32, 'F');
            $pdf->SetTextColor(45, 212, 153);
            $pdf->SetFont('helvetica', 'B', 9);
            $pdf->SetXY(14, 8);
            $pdf->Cell(0, 5, 'BILAN APRÈS-ACTION', 0, 1, 'L');
            $pdf->SetTextColor(248, 250, 252);
            $pdf->SetFont('helvetica', 'B', 16);
            $pdf->SetXY(14, 15);
            $pdf->Cell(0, 10, $title, 0, 1, 'L');

            $pdf->SetY(40);
            $pdf->SetTextColor(30, 41, 51);
            $pdf->SetFont('helvetica', '', 10);
            $start = self::when($summary['missionStart'] ?? null);
            $end = self::when($summary['missionEnd'] ?? null);
            $pdf->MultiCell(0, 6, 'Période : ' . $start . '  →  ' . $end, 0, 'L', false, 1);

            $pdf->Ln(2);
            self::section($pdf, 'Moyens sur le terrain');
            $cards = [
                ['Opérateurs', (int) ($summary['operators'] ?? $summary['unitCount'] ?? 0)],
                ['Unités alliées', (int) ($summary['allies'] ?? 0)],
                ['Téléphones', (int) ($summary['phones'] ?? 0)],
                ['Balises GPS', (int) ($summary['beacons'] ?? 0)],
                ['Contacts', (int) ($summary['contactEvents'] ?? $summary['intelEvents'] ?? 0)],
                ['Évacuations', (int) ($summary['medevacEvents'] ?? 0)],
                ['Ordres', (int) ($summary['orderEvents'] ?? 0)],
                ['Repères', (int) ($summary['markerEvents'] ?? 0)],
            ];
            $x0 = 14;
            $y0 = $pdf->GetY();
            $w = 44;
            $h = 16;
            foreach ($cards as $i => $card) {
                $col = $i % 4;
                $row = intdiv($i, 4);
                $x = $x0 + ($col * ($w + 4));
                $y = $y0 + ($row * ($h + 4));
                $pdf->SetXY($x, $y);
                $pdf->SetFillColor(241, 245, 249);
                $pdf->SetDrawColor(203, 213, 225);
                $pdf->Cell($w, $h, '', 1, 0, 'L', true);
                $pdf->SetXY($x + 3, $y + 2);
                $pdf->SetFont('helvetica', '', 7);
                $pdf->SetTextColor(100, 116, 139);
                $pdf->Cell($w - 6, 4, $card[0], 0, 2, 'L');
                $pdf->SetFont('helvetica', 'B', 13);
                $pdf->SetTextColor(15, 23, 42);
                $pdf->Cell($w - 6, 8, (string) $card[1], 0, 0, 'L');
            }
            $pdf->SetY($y0 + 2 * ($h + 4) + 2);

            if ($errors !== []) {
                self::section($pdf, 'Points d’attention');
                $pdf->SetFont('helvetica', '', 10);
                $pdf->SetTextColor(30, 41, 51);
                foreach ($errors as $error) {
                    if (!is_array($error)) {
                        continue;
                    }
                    $label = trim((string) ($error['label'] ?? 'Alerte'));
                    $count = (int) ($error['count'] ?? 0);
                    $line = $label;
                    if ($count > 0) {
                        $line .= ' — ' . $count;
                    }
                    $pdf->MultiCell(0, 5.5, '• ' . $line, 0, 'L', false, 1);
                }
                $pdf->Ln(2);
            }

            self::section($pdf, 'Personnes et moyens');
            if ($tracks === []) {
                $pdf->SetFont('helvetica', 'I', 10);
                $pdf->SetTextColor(100, 116, 139);
                $pdf->Cell(0, 6, 'Aucune trace enregistrée.', 0, 1, 'L');
            } else {
                $pdf->SetFillColor(15, 23, 42);
                $pdf->SetTextColor(248, 250, 252);
                $pdf->SetFont('helvetica', 'B', 8);
                $pdf->Cell(78, 7, 'Indicatif', 0, 0, 'L', true);
                $pdf->Cell(42, 7, 'Type', 0, 0, 'L', true);
                $pdf->Cell(36, 7, 'Première vue', 0, 0, 'L', true);
                $pdf->Cell(26, 7, 'Dernière vue', 0, 1, 'L', true);
                $pdf->SetFont('helvetica', '', 8);
                $fill = false;
                $n = 0;
                foreach ($tracks as $track) {
                    if (!is_array($track) || $n >= 80) {
                        continue;
                    }
                    $n++;
                    $fill = !$fill;
                    $pdf->SetFillColor($fill ? 248 : 255, $fill ? 250 : 255, $fill ? 252 : 255);
                    $pdf->SetTextColor(30, 41, 51);
                    $cs = self::clip((string) ($track['callsign'] ?? $track['unitId'] ?? '—'), 42);
                    $kind = (string) ($track['kindLabel'] ?? ReplayTimelineBuilder::kindLabel((string) ($track['kind'] ?? 'player')));
                    $pdf->Cell(78, 6, $cs, 0, 0, 'L', true);
                    $pdf->Cell(42, 6, $kind, 0, 0, 'L', true);
                    $pdf->Cell(36, 6, self::whenShort($track['firstSeen'] ?? null), 0, 0, 'L', true);
                    $pdf->Cell(26, 6, self::whenShort($track['lastSeen'] ?? null), 0, 1, 'L', true);
                }
            }

            $pdf->Ln(4);
            self::section($pdf, 'Chronologie');
            $pdf->SetFont('helvetica', '', 9);
            $pdf->SetTextColor(30, 41, 51);
            if ($ops === []) {
                $pdf->SetFont('helvetica', 'I', 10);
                $pdf->SetTextColor(100, 116, 139);
                $pdf->Cell(0, 6, 'Aucun événement opérationnel enregistré.', 0, 1, 'L');
            } else {
                $max = min(45, count($ops));
                for ($i = 0; $i < $max; $i++) {
                    $evt = $ops[$i];
                    if (!is_array($evt)) {
                        continue;
                    }
                    $lab = trim((string) ($evt['label'] ?? 'Événement'));
                    $line = self::when($evt['timestamp'] ?? null) . '  —  ' . self::clip($lab, 90);
                    $pdf->MultiCell(0, 5, $line, 0, 'L', false, 1);
                }
            }

            $safe = preg_replace('/[^A-Za-z0-9_-]+/', '-', $title) ?: 'bilan';
            $binary = (string) $pdf->Output('', 'S');

            return (new Response())
                ->header('Content-Type', 'application/pdf')
                ->header('Content-Disposition', 'attachment; filename="Bilan-' . $safe . '.pdf"')
                ->setBody($binary);
        });
    }

    private static function cleanTitle(string $title): string
    {
        $title = trim($title);
        if ($title === '' || preg_match('/^mission_\d+_map_\d+$/', $title) === 1) {
            return 'Session terrain';
        }

        return self::clip($title, 80);
    }

    private static function when(mixed $ts): string
    {
        $raw = trim((string) $ts);
        if ($raw === '') {
            return '—';
        }
        $t = strtotime($raw);

        return $t === false ? '—' : gmdate('d/m/Y H:i', $t) . ' Z';
    }

    private static function whenShort(mixed $ts): string
    {
        $raw = trim((string) $ts);
        if ($raw === '') {
            return '—';
        }
        $t = strtotime($raw);

        return $t === false ? '—' : gmdate('H:i', $t);
    }

    private static function clip(string $s, int $max): string
    {
        if (function_exists('mb_strlen') && mb_strlen($s) > $max) {
            return mb_substr($s, 0, $max - 1) . '...';
        }
        if (strlen($s) > $max) {
            return substr($s, 0, $max - 1) . '...';
        }

        return $s;
    }

    private static function section(\TCPDF $pdf, string $label): void
    {
        $pdf->Ln(1);
        $pdf->SetFont('helvetica', 'B', 11);
        $pdf->SetTextColor(15, 23, 42);
        $pdf->Cell(0, 8, $label, 0, 1, 'L');
        $pdf->SetDrawColor(45, 212, 153);
        $pdf->SetLineWidth(0.5);
        $y = $pdf->GetY();
        $pdf->Line(14, $y, 42, $y);
        $pdf->Ln(3);
    }
}
