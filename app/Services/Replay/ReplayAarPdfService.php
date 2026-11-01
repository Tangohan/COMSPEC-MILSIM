<?php

declare(strict_types=1);

namespace App\Services\Replay;

use App\Core\Response;
use App\Support\Pdf\AthenaPdfTheme as Theme;
use App\Support\TrainingCertificatePdfEngine;

/**
 * Bilan après-action — document lisible (pas un dump technique), A4 portrait au gabarit Athena.
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
            $safe = preg_replace('/[^A-Za-z0-9_-]+/', '-', $title) ?: 'bilan';

            return (new Response())
                ->header('Content-Type', 'application/pdf')
                ->header('Content-Disposition', 'attachment; filename="Bilan-' . $safe . '.pdf"')
                ->setBody(self::buildPdfBinary($aar, $title));
        });
    }

    /**
     * @param array<string, mixed> $aar
     */
    public static function buildPdfBinary(array $aar, string $title): string
    {
        $summary = is_array($aar['summary'] ?? null) ? $aar['summary'] : [];
        $startTs = strtotime(trim((string) ($summary['missionStart'] ?? '')));
        $pdf = Theme::tcpdf([
            'kind' => 'Bilan après-action',
            'reference' => 'RAAR-' . ($startTs !== false && $startTs > 0 ? gmdate('Ymd-Hi', $startTs) : gmdate('Ymd')),
            'tenant_id' => (int) ($aar['tenantId'] ?? $aar['tenant_id'] ?? 0),
            'classification' => 'Diffusion interne',
            'audience' => 'Rejeu tactique',
            'title' => $title,
        ]);
        if ($pdf === null) {
            throw new \RuntimeException('TCPDF indisponible');
        }
        $pdf->AddPage();
        $pdf->writeAthenaHtml(self::buildHtml($aar, $title));

        return (string) $pdf->Output('', 'S');
    }

    /**
     * @param array<string, mixed> $aar
     */
    public static function buildHtml(array $aar, string $title): string
    {
        $summary = is_array($aar['summary'] ?? null) ? $aar['summary'] : [];
        $errors = is_array($aar['errors'] ?? null) ? $aar['errors'] : [];
        $tracks = is_array($aar['unitTracks'] ?? null) ? $aar['unitTracks'] : [];
        $ops = is_array($aar['operationalEvents'] ?? null) ? $aar['operationalEvents'] : [];

        $html = Theme::titleBlock('Période : ' . self::when($summary['missionStart'] ?? null) . '  →  ' . self::when($summary['missionEnd'] ?? null), $title);

        $html .= Theme::section('01', 'Moyens sur le terrain');
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
        foreach (array_chunk($cards, 4) as $chunk) {
            $html .= Theme::stats(array_map(static fn (array $c): array => ['label' => $c[0], 'value' => (string) $c[1]], $chunk));
        }

        $n = 1;
        if ($errors !== []) {
            $items = [];
            foreach ($errors as $error) {
                if (!is_array($error)) {
                    continue;
                }
                $count = (int) ($error['count'] ?? 0);
                $items[] = trim((string) ($error['label'] ?? 'Alerte')) . ($count > 0 ? ' — ' . $count : '');
            }
            $html .= Theme::section(sprintf('%02d', ++$n), 'Points d’attention') . Theme::bullets($items, 'warn');
        }

        $html .= Theme::section(sprintf('%02d', ++$n), 'Personnes et moyens');
        $rows = [];
        foreach ($tracks as $track) {
            if (!is_array($track) || count($rows) >= 80) {
                continue;
            }
            $rows[] = [
                self::clip((string) ($track['callsign'] ?? $track['unitId'] ?? '—'), 42),
                (string) ($track['kindLabel'] ?? ReplayTimelineBuilder::kindLabel((string) ($track['kind'] ?? 'player'))),
                self::whenShort($track['firstSeen'] ?? null),
                self::whenShort($track['lastSeen'] ?? null),
            ];
        }
        $html .= Theme::table([
            ['label' => 'Indicatif', 'width' => 43, 'bold' => true],
            ['label' => 'Type', 'width' => 23],
            ['label' => 'Première vue', 'width' => 17, 'mono' => true],
            ['label' => 'Dernière vue', 'width' => 17, 'mono' => true],
        ], $rows, true, 'Aucune trace enregistrée.');

        $html .= Theme::section(sprintf('%02d', ++$n), 'Chronologie');
        $rows = [];
        foreach (array_slice($ops, 0, 45) as $evt) {
            if (!is_array($evt)) {
                continue;
            }
            $rows[] = [self::when($evt['timestamp'] ?? null), self::clip(trim((string) ($evt['label'] ?? 'Événement')), 120)];
        }

        return $html . Theme::table([
            ['label' => 'Heure', 'width' => 24, 'mono' => true],
            ['label' => 'Événement', 'width' => 76],
        ], $rows, true, 'Aucun événement opérationnel enregistré.');
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
}
