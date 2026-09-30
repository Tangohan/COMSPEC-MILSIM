<?php

declare(strict_types=1);

namespace App\Services\Organization;

use App\Core\Response;
use App\Support\TrainingCertificatePdfEngine;

/**
 * Export ORBAT style FM (mockup ATHENA) — HTML imprimable + PDF TCPDF/Dompdf.
 */
final class OrbatChartPdfService
{
    /**
     * @param array<string, mixed> $roster Racine OrbatRosterPayload
     * @param array{
     *   unit_label?: string,
     *   theater?: string,
     *   reference?: string,
     *   version?: string,
     *   classification?: string,
     *   include_mission?: bool,
     *   include_notes?: bool,
     *   include_legend?: bool
     * } $meta
     * @return array{
     *   root: array<string, mixed>,
     *   groups: list<array<string, mixed>>,
     *   totals: array{theoretical: int, present: int, rate: int},
     *   meta: array<string, mixed>
     * }
     */
    public function buildDocument(array $roster, array $meta = []): array
    {
        $rootLabel = trim((string) ($roster['label'] ?? 'Command'));
        $rootStrength = $this->subtreeStrength($roster);
        $rootPresent = $this->subtreePresent($roster);
        $children = is_array($roster['children'] ?? null) ? $roster['children'] : [];

        // Si la racine est un placeholder « Command », les groupes sont ses enfants ;
        // sinon la racine réelle est affichée et ses enfants deviennent les groupes.
        $groupsSource = $children;
        if ($groupsSource === [] && (int) ($roster['unitId'] ?? 0) > 0) {
            $groupsSource = [$roster];
        }

        $groups = [];
        foreach ($groupsSource as $child) {
            if (!is_array($child)) {
                continue;
            }
            $groups[] = $this->normalizeGroup($child);
        }

        $theo = 0;
        $pres = 0;
        foreach ($groups as $g) {
            $theo += (int) ($g['theoretical'] ?? 0);
            $pres += (int) ($g['present'] ?? 0);
        }
        if ($theo < 1) {
            $theo = $rootStrength;
            $pres = $rootPresent;
        }
        $rate = $theo > 0 ? (int) round(($pres / $theo) * 100) : 0;

        $unitLabel = trim((string) ($meta['unit_label'] ?? ''));
        if ($unitLabel === '') {
            $unitLabel = $rootLabel !== '' && $rootLabel !== 'Command' ? $rootLabel : 'Organisation';
        }

        return [
            'root' => [
                'name' => mb_strtoupper($unitLabel),
                'code' => trim((string) ($roster['role'] ?? 'TF')),
                'strength' => $theo,
                'present' => $pres,
                'leader' => trim((string) ($roster['leader'] ?? '—')),
                'mission' => trim((string) ($roster['mission'] ?? '')),
                'type' => $this->symbolType((string) ($roster['type'] ?? 'command'), (string) ($roster['structType'] ?? '')),
            ],
            'groups' => $groups,
            'totals' => [
                'theoretical' => $theo,
                'present' => $pres,
                'rate' => $rate,
            ],
            'meta' => [
                'unit_label' => $unitLabel,
                'theater' => trim((string) ($meta['theater'] ?? '—')),
                'reference' => trim((string) ($meta['reference'] ?? ('ORBAT-' . date('Ymd')))),
                'version' => trim((string) ($meta['version'] ?? '1.0')),
                'effect_date' => trim((string) ($meta['effect_date'] ?? date('d M Y'))),
                'classification' => trim((string) ($meta['classification'] ?? 'UNCLASSIFIED // FOR OFFICIAL USE ONLY')),
                'doc_code' => trim((string) ($meta['doc_code'] ?? 'FM ATHENA RH-01')),
                'include_mission' => !empty($meta['include_mission']),
                'include_notes' => !empty($meta['include_notes']),
                'include_legend' => array_key_exists('include_legend', $meta) ? !empty($meta['include_legend']) : true,
            ],
        ];
    }

    /**
     * @param array<string, mixed> $document
     */
    public function renderHtml(array $document, bool $printToolbar = true): string
    {
        $view = base_path('views/personnel/orbat_chart_export.php');
        ob_start();
        require $view;

        return (string) ob_get_clean();
    }

    /**
     * @param array<string, mixed> $document
     */
    public function pdfResponse(array $document): Response
    {
        $meta = is_array($document['meta'] ?? null) ? $document['meta'] : [];
        $title = 'ORBAT — ' . (string) ($meta['unit_label'] ?? 'Organisation');
        $filename = 'orbat-' . preg_replace('/[^a-zA-Z0-9_-]+/', '-', strtolower((string) ($meta['reference'] ?? 'export'))) . '.pdf';

        return TrainingCertificatePdfEngine::suppressTcpdfPhpDeprecationsWhile(function () use (
            $document,
            $title,
            $filename
        ): Response {
            if (!TrainingCertificatePdfEngine::ensureTcpdfLoaded() && !class_exists(\Dompdf\Dompdf::class)) {
                return (new Response())
                    ->setStatusCode(503)
                    ->header('Content-Type', 'text/plain; charset=UTF-8')
                    ->setBody('Export PDF indisponible (moteur PDF manquant).');
            }

            $html = $this->tcpdfFriendlyHtml($document);

            if (class_exists(\Dompdf\Dompdf::class) && !TrainingCertificatePdfEngine::prefersTcpdf()) {
                $dompdf = new \Dompdf\Dompdf(['isRemoteEnabled' => false]);
                $dompdf->loadHtml($html);
                $dompdf->setPaper('a3', 'landscape');
                $dompdf->render();
                $binary = (string) $dompdf->output();
            } else {
                $pdf = new \TCPDF('L', 'mm', 'A3', true, 'UTF-8', false);
                $pdf->SetCreator('Athena');
                $pdf->SetAuthor('Athena MILSIM');
                $pdf->SetTitle($title);
                $pdf->setPrintHeader(false);
                $pdf->setPrintFooter(false);
                $pdf->SetMargins(10, 10, 10);
                $pdf->SetAutoPageBreak(true, 12);
                $font = TrainingCertificatePdfEngine::resolveCertificateFontFamily() ?? 'helvetica';
                $pdf->SetFont($font, '', 10);
                $pdf->AddPage();
                $pdf->writeHTML($html, true, false, true, false, '');
                $binary = (string) $pdf->Output('', 'S');
            }

            return (new Response())
                ->header('Content-Type', 'application/pdf')
                ->header('Content-Disposition', 'attachment; filename="' . $filename . '"')
                ->header('Cache-Control', 'private, no-store')
                ->setBody($binary);
        });
    }

    /**
     * HTML simplifié compatible TCPDF/Dompdf (sans flex/grid complexes).
     *
     * @param array<string, mixed> $document
     */
    public function tcpdfFriendlyHtml(array $document): string
    {
        $meta = is_array($document['meta'] ?? null) ? $document['meta'] : [];
        $root = is_array($document['root'] ?? null) ? $document['root'] : [];
        $groups = is_array($document['groups'] ?? null) ? $document['groups'] : [];
        $totals = is_array($document['totals'] ?? null) ? $document['totals'] : [];
        $e = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        $groupCells = '';
        foreach ($groups as $g) {
            if (!is_array($g)) {
                continue;
            }
            $kids = '';
            foreach (($g['children'] ?? []) as $c) {
                if (!is_array($c)) {
                    continue;
                }
                $kids .= '<tr><td style="font-size:8px;">' . $e($c['code'] ?? '') . '</td>'
                    . '<td style="font-size:8px;font-weight:bold;">' . $e($c['name'] ?? '') . '</td>'
                    . '<td style="font-size:8px;" align="center">' . (int) ($c['present'] ?? 0) . '/' . (int) ($c['theoretical'] ?? 0) . '</td>'
                    . '<td style="font-size:8px;">' . $e($c['leader'] ?? '') . '</td></tr>';
            }
            $groupCells .= '<td width="' . max(12, (int) floor(100 / max(1, count($groups)))) . '%" valign="top" style="border:1px solid #21282e;padding:6px;">'
                . '<div style="text-align:center;font-size:9px;font-weight:bold;letter-spacing:2px;">' . $e($g['echelon'] ?? '') . '</div>'
                . '<div style="text-align:center;background:' . $e($this->symbolColor((string) ($g['type'] ?? 'combat'))) . ';border:1.5px solid #111;padding:4px;margin:4px 0;font-size:10px;font-weight:bold;">'
                . $e(strtoupper((string) ($g['type_label'] ?? $g['type'] ?? ''))) . '</div>'
                . '<div style="text-align:center;font-size:11px;font-weight:bold;">' . $e($g['title'] ?? '') . '</div>'
                . '<div style="text-align:center;font-size:9px;">' . (int) ($g['present'] ?? 0) . ' / ' . (int) ($g['theoretical'] ?? 0) . ' pers.</div>'
                . '<div style="text-align:center;font-size:9px;">' . $e($g['rank'] ?? '') . '</div>'
                . '<div style="text-align:center;font-size:8px;color:#5b6268;margin:4px 0;">' . $e(strip_tags((string) ($g['desc'] ?? ''))) . '</div>'
                . ($kids !== '' ? '<table cellpadding="2" cellspacing="0" border="0.5" width="100%">' . $kids . '</table>' : '')
                . '</td>';
        }

        $strengthRows = '';
        foreach ($groups as $g) {
            if (!is_array($g)) {
                continue;
            }
            $t = (int) ($g['theoretical'] ?? 0);
            $p = (int) ($g['present'] ?? 0);
            $pct = $t > 0 ? (int) round(($p / $t) * 100) : 0;
            $strengthRows .= '<tr><td>' . $e($g['title'] ?? '') . '</td><td align="center">' . $t
                . '</td><td align="center">' . $p . '</td><td align="center">' . $pct . ' %</td></tr>';
        }

        $classif = $e($meta['classification'] ?? 'UNCLASSIFIED // FOR OFFICIAL USE ONLY');

        return '<div style="font-family:dejavusans,freesans,helvetica,sans-serif;color:#101820;font-size:10px;">'
            . '<table width="100%" cellpadding="4" cellspacing="0"><tr>'
            . '<td width="34%"><div style="font-size:14px;font-weight:bold;">ATHENA MILSIM</div>'
            . '<div style="font-size:12px;font-weight:bold;">ORGANIZATION CHART (ORBAT)</div>'
            . '<div style="font-size:9px;font-weight:bold;">TABLEAU D’ORGANISATION ET D’ÉQUIPEMENT</div></td>'
            . '<td width="32%" align="center" style="background-color:#141c24;color:#ffffff;font-size:11px;font-weight:bold;">'
            . $classif . '</td>'
            . '<td width="34%" align="right" style="font-size:10px;font-weight:bold;">'
            . $e($meta['doc_code'] ?? 'FM ATHENA RH-01') . '<br/>ANNEXE B<br/>FIGURE B-1</td>'
            . '</tr></table>'
            . '<br/>'
            . '<table width="100%" cellpadding="3" cellspacing="0"><tr>'
            . '<td width="32%" valign="top">'
            . '<b>UNITÉ :</b> ' . $e($meta['unit_label'] ?? '') . '<br/>'
            . '<b>THÉÂTRE :</b> ' . $e($meta['theater'] ?? '—') . '<br/>'
            . '<b>DATE D’EFFET :</b> ' . $e($meta['effect_date'] ?? '') . '<br/>'
            . '<b>RÉFÉRENCE :</b> ' . $e($meta['reference'] ?? '') . '<br/>'
            . '<b>VERSION :</b> ' . $e($meta['version'] ?? '1.0')
            . '</td>'
            . '<td width="36%" align="center" valign="top">'
            . '<div style="font-size:12px;font-weight:bold;">' . $e($root['code'] ?? 'TF') . '</div>'
            . '<div style="display:inline-block;background:' . $e($this->symbolColor((string) ($root['type'] ?? 'combat')))
            . ';border:2px solid #111;padding:6px 18px;font-weight:bold;font-size:11px;margin:4px 0;">'
            . $e(strtoupper((string) ($root['type'] ?? 'TF'))) . '</div>'
            . '<div style="font-size:13px;font-weight:bold;">' . $e($root['name'] ?? '') . '</div>'
            . '<div style="font-size:10px;">(± ' . (int) ($totals['theoretical'] ?? 0) . ' pers.)</div>'
            . '<div style="font-size:9px;">' . $e($root['leader'] ?? '—') . '</div>'
            . '</td>'
            . '<td width="32%" valign="top">'
            . '<table cellpadding="3" cellspacing="0" border="1" width="100%">'
            . '<tr style="background-color:#1e2932;color:#fff;"><th colspan="2" align="left">COMPOSITION GÉNÉRALE</th></tr>'
            . '<tr><td>Effectif total (théorique)</td><td align="right"><b>' . (int) ($totals['theoretical'] ?? 0) . '</b></td></tr>'
            . '<tr><td>Effectif présent (T/O)</td><td align="right"><b>' . (int) ($totals['present'] ?? 0) . '</b></td></tr>'
            . '<tr><td>Taux de disponibilité</td><td align="right"><b>' . (int) ($totals['rate'] ?? 0) . ' %</b></td></tr>'
            . '</table></td></tr></table>'
            . '<br/>'
            . '<table width="100%" cellpadding="0" cellspacing="4"><tr>' . $groupCells . '</tr></table>'
            . '<br/>'
            . '<table width="100%" cellpadding="0" cellspacing="6"><tr>'
            . '<td width="50%" valign="top">'
            . '<table cellpadding="3" cellspacing="0" border="1" width="100%">'
            . '<tr style="background-color:#1e2932;color:#fff;">'
            . '<th align="left">ÉLÉMENT</th><th>THÉORIQUE</th><th>PRÉSENT</th><th>DISPONIBILITÉ</th></tr>'
            . $strengthRows
            . '<tr style="background-color:#f3f4f5;font-weight:bold;"><td>TOTAL</td>'
            . '<td align="center">' . (int) ($totals['theoretical'] ?? 0) . '</td>'
            . '<td align="center">' . (int) ($totals['present'] ?? 0) . '</td>'
            . '<td align="center">' . (int) ($totals['rate'] ?? 0) . ' %</td></tr>'
            . '</table></td>'
            . '<td width="50%" valign="top">'
            . (!empty($meta['include_mission'])
                ? '<table cellpadding="3" cellspacing="0" border="1" width="100%">'
                . '<tr style="background-color:#1e2932;color:#fff;"><th align="left">MISSION</th></tr>'
                . '<tr><td style="font-size:9px;">' . $e(($root['mission'] ?? '') !== '' ? (string) $root['mission'] : 'Conduire les opérations conformément aux directives du commandement.') . '</td></tr></table>'
                : '')
            . '</td></tr></table>'
            . '<br/><table width="100%" cellpadding="3"><tr>'
            . '<td width="33%">' . $e($meta['doc_code'] ?? 'FM ATHENA RH-01') . '</td>'
            . '<td width="34%" align="center" style="font-size:9px;font-weight:bold;">' . $classif . '</td>'
            . '<td width="33%" align="right">ANNEXE B-1 — PAGE 1/1</td>'
            . '</tr></table>'
            . '</div>';
    }

    /**
     * @param array<string, mixed> $node
     * @return array<string, mixed>
     */
    private function normalizeGroup(array $node): array
    {
        $theo = $this->subtreeStrength($node);
        $pres = $this->subtreePresent($node);
        $type = $this->symbolType((string) ($node['type'] ?? ''), (string) ($node['structType'] ?? ''));
        $children = [];
        foreach ($node['children'] ?? [] as $child) {
            if (!is_array($child)) {
                continue;
            }
            $cTheo = $this->subtreeStrength($child);
            $cPres = $this->subtreePresent($child);
            $children[] = [
                'code' => trim((string) ($child['role'] ?? '')),
                'name' => trim((string) ($child['label'] ?? 'Unité')),
                'theoretical' => $cTheo,
                'present' => $cPres,
                'leader' => trim((string) ($child['leader'] ?? '—')),
                'rank' => '',
                'type' => $this->symbolType((string) ($child['type'] ?? ''), (string) ($child['structType'] ?? '')),
            ];
        }

        return [
            'echelon' => $this->echelonMark($theo, count($children)),
            'type' => $type,
            'type_label' => $this->typeLabel($type),
            'title' => trim((string) ($node['label'] ?? 'Unité')),
            'theoretical' => $theo,
            'present' => $pres,
            'rank' => trim((string) ($node['leader'] ?? '—')),
            'desc' => trim((string) ($node['mission'] ?? '')),
            'children' => array_slice($children, 0, 6),
        ];
    }

    /** @param array<string, mixed> $node */
    private function subtreeStrength(array $node): int
    {
        $n = max(
            (int) ($node['strength'] ?? 0),
            (int) ($node['visibleStrength'] ?? 0),
            is_array($node['members'] ?? null) ? count($node['members']) : 0
        );
        foreach ($node['children'] ?? [] as $child) {
            if (is_array($child)) {
                $n = max($n, $this->subtreeStrength($child));
                // Sum unique-ish: add child strengths for group totals
            }
        }
        // Prefer sum of direct members + recursive children present for group box
        $sum = is_array($node['members'] ?? null) ? count($node['members']) : 0;
        $hasChildSum = false;
        foreach ($node['children'] ?? [] as $child) {
            if (is_array($child)) {
                $hasChildSum = true;
                $sum += $this->subtreeStrength($child);
            }
        }
        if ($hasChildSum || $sum > 0) {
            return max($sum, (int) ($node['strength'] ?? 0));
        }

        return max($n, 0);
    }

    /** @param array<string, mixed> $node */
    private function subtreePresent(array $node): int
    {
        $sum = is_array($node['members'] ?? null) ? count($node['members']) : (int) ($node['visibleStrength'] ?? 0);
        $hasChild = false;
        foreach ($node['children'] ?? [] as $child) {
            if (is_array($child)) {
                $hasChild = true;
                $sum += $this->subtreePresent($child);
            }
        }
        if ($hasChild) {
            return $sum;
        }

        return max($sum, (int) ($node['visibleStrength'] ?? 0), is_array($node['members'] ?? null) ? count($node['members']) : 0);
    }

    private function symbolType(string $type, string $struct): string
    {
        $blob = mb_strtolower($type . ' ' . $struct);
        if (str_contains($blob, 'air') || str_contains($blob, 'aviation') || str_contains($blob, 'helico')) {
            return 'air';
        }
        if (str_contains($blob, 'hq') || str_contains($blob, 'command') || str_contains($blob, 'etat') || str_contains($blob, 'état')) {
            return 'hq';
        }
        if (str_contains($blob, 'fire') || str_contains($blob, 'artiller') || str_contains($blob, 'mortier')) {
            return 'fire';
        }
        if (str_contains($blob, 'engineer') || str_contains($blob, 'genie') || str_contains($blob, 'génie')) {
            return 'engineer';
        }
        if (str_contains($blob, 'log') || str_contains($blob, 'supply')) {
            return 'log';
        }
        if (str_contains($blob, 'support') || str_contains($blob, 'soutien')) {
            return 'support';
        }
        if (str_contains($blob, 'isr') || str_contains($blob, 'recon')) {
            return 'isr';
        }

        return 'combat';
    }

    private function typeLabel(string $type): string
    {
        return match ($type) {
            'hq' => 'HQ',
            'support' => 'SOUTIEN',
            'air' => 'AIR',
            'fire' => 'FEU',
            'engineer' => 'GÉNIE',
            'log' => 'LOG',
            'isr' => 'ISR',
            default => 'COMBAT',
        };
    }

    private function symbolColor(string $type): string
    {
        return match ($type) {
            'hq' => '#dcecf8',
            'support' => '#cbe8a3',
            'air' => '#bcd8f0',
            'fire' => '#f8e979',
            'engineer' => '#f4ba79',
            'log' => '#d7dadd',
            'isr' => '#bcd8f0',
            default => '#bcd8f0',
        };
    }

    private function echelonMark(int $strength, int $childCount): string
    {
        if ($strength >= 100 || $childCount >= 4) {
            return 'III';
        }
        if ($strength >= 40 || $childCount >= 2) {
            return 'II';
        }
        if ($strength >= 12) {
            return '●';
        }

        return '••';
    }
}
