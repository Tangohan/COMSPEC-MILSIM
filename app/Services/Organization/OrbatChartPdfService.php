<?php

declare(strict_types=1);

namespace App\Services\Organization;

use App\Core\Response;
use App\Support\TrainingCertificatePdfEngine;

/**
 * Export ORBAT style FM (mockup ATHENA) — HTML imprimable + PDF TCPDF/Dompdf.
 * Format papier adaptatif (A3→A0 / personnalisé) selon la largeur de l’arbre ;
 * toutes les sous-unités sont incluses sans troncature.
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
     *   include_legend?: bool,
     *   paper?: string
     * } $meta
     * @return array{
     *   root: array<string, mixed>,
     *   groups: list<array<string, mixed>>,
     *   flat_units: list<array<string, mixed>>,
     *   totals: array{theoretical: int, present: int, rate: int, unit_count: int},
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
            $groups[] = $this->normalizeUnit($child);
        }

        $flatUnits = [];
        foreach ($groups as $g) {
            $this->collectFlatUnits($g, $flatUnits, 0);
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

        $breadth = $this->maxBreadth($groups);
        $depth = $this->maxDepth($groups);
        $unitCount = count($flatUnits);
        $paper = $this->resolvePaper(
            trim((string) ($meta['paper'] ?? '')),
            $breadth,
            $depth,
            $unitCount
        );

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
            'flat_units' => $flatUnits,
            'totals' => [
                'theoretical' => $theo,
                'present' => $pres,
                'rate' => $rate,
                'unit_count' => $unitCount,
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
                'paper' => $paper['name'],
                'paper_css' => $paper['css'],
                'paper_width_mm' => $paper['width_mm'],
                'paper_height_mm' => $paper['height_mm'],
                'tree_breadth' => $breadth,
                'tree_depth' => $depth,
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
        $w = max(420, (int) ($meta['paper_width_mm'] ?? 841));
        $h = max(297, (int) ($meta['paper_height_mm'] ?? 594));
        $paperName = strtoupper((string) ($meta['paper'] ?? 'A1'));

        return TrainingCertificatePdfEngine::suppressTcpdfPhpDeprecationsWhile(function () use (
            $document,
            $title,
            $filename,
            $w,
            $h,
            $paperName
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
                // Dompdf: dimensions déjà en paysage (mm → pt) — ne pas re-swapper via « landscape »
                $dompdf->setPaper([0.0, 0.0, $w * 2.83465, $h * 2.83465]);
                $dompdf->render();
                $binary = (string) $dompdf->output();
            } else {
                // Format personnalisé paysage (largeur × hauteur mm) — jamais forcé en A4
                $pageFormat = in_array($paperName, ['A3', 'A2', 'A1', 'A0'], true)
                    ? $paperName
                    : [$w, $h];
                $pdf = new \TCPDF('L', 'mm', $pageFormat, true, 'UTF-8', false);
                $pdf->SetCreator('Athena');
                $pdf->SetAuthor('Athena MILSIM');
                $pdf->SetTitle($title);
                $pdf->setPrintHeader(false);
                $pdf->setPrintFooter(false);
                $pdf->SetMargins(8, 8, 8);
                $pdf->SetAutoPageBreak(true, 10);
                $font = TrainingCertificatePdfEngine::resolveCertificateFontFamily() ?? 'helvetica';
                $pdf->SetFont($font, '', 9);
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
        $flat = is_array($document['flat_units'] ?? null) ? $document['flat_units'] : [];
        $totals = is_array($document['totals'] ?? null) ? $document['totals'] : [];
        $e = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $paper = $e($meta['paper'] ?? 'A1');

        $groupCells = '';
        $nGroups = max(1, count($groups));
        // Répartir en lignes de 4 colonnes max pour les ORBAT très larges
        $chunks = array_chunk($groups, min(4, $nGroups));
        foreach ($chunks as $chunk) {
            $row = '';
            $colW = max(12, (int) floor(100 / max(1, count($chunk))));
            foreach ($chunk as $g) {
                if (!is_array($g)) {
                    continue;
                }
                $row .= '<td width="' . $colW . '%" valign="top" style="border:1px solid #21282e;padding:5px;">'
                    . $this->renderUnitTcpdf($g, $e, 0)
                    . '</td>';
            }
            $groupCells .= '<tr>' . $row . '</tr>';
        }

        $strengthRows = '';
        foreach ($flat as $u) {
            if (!is_array($u)) {
                continue;
            }
            $t = (int) ($u['theoretical'] ?? 0);
            $p = (int) ($u['present'] ?? 0);
            $pct = $t > 0 ? (int) round(($p / $t) * 100) : 0;
            $indent = str_repeat('· ', max(0, (int) ($u['depth'] ?? 0)));
            $strengthRows .= '<tr><td>' . $indent . $e($u['title'] ?? '') . '</td><td align="center">' . $t
                . '</td><td align="center">' . $p . '</td><td align="center">' . $pct . ' %</td></tr>';
        }

        $classif = $e($meta['classification'] ?? 'UNCLASSIFIED // FOR OFFICIAL USE ONLY');
        $unitCount = (int) ($totals['unit_count'] ?? count($flat));

        return '<div style="font-family:dejavusans,freesans,helvetica,sans-serif;color:#101820;font-size:9px;">'
            . '<table width="100%" cellpadding="3" cellspacing="0"><tr>'
            . '<td width="34%"><div style="font-size:13px;font-weight:bold;">ATHENA MILSIM</div>'
            . '<div style="font-size:11px;font-weight:bold;">ORGANIZATION CHART (ORBAT)</div>'
            . '<div style="font-size:8px;font-weight:bold;">TABLEAU D’ORGANISATION ET D’ÉQUIPEMENT</div></td>'
            . '<td width="32%" align="center" style="background-color:#141c24;color:#ffffff;font-size:10px;font-weight:bold;">'
            . $classif . '</td>'
            . '<td width="34%" align="right" style="font-size:9px;font-weight:bold;">'
            . $e($meta['doc_code'] ?? 'FM ATHENA RH-01') . '<br/>ANNEXE B<br/>FIGURE B-1 · ' . $paper . ' paysage</td>'
            . '</tr></table>'
            . '<br/>'
            . '<table width="100%" cellpadding="3" cellspacing="0"><tr>'
            . '<td width="32%" valign="top">'
            . '<b>UNITÉ :</b> ' . $e($meta['unit_label'] ?? '') . '<br/>'
            . '<b>THÉÂTRE :</b> ' . $e($meta['theater'] ?? '—') . '<br/>'
            . '<b>DATE D’EFFET :</b> ' . $e($meta['effect_date'] ?? '') . '<br/>'
            . '<b>RÉFÉRENCE :</b> ' . $e($meta['reference'] ?? '') . '<br/>'
            . '<b>VERSION :</b> ' . $e($meta['version'] ?? '1.0') . '<br/>'
            . '<b>UNITÉS :</b> ' . $unitCount
            . '</td>'
            . '<td width="36%" align="center" valign="top">'
            . '<div style="font-size:11px;font-weight:bold;">' . $e($root['code'] ?? 'TF') . '</div>'
            . '<div style="display:inline-block;background:' . $e($this->symbolColor((string) ($root['type'] ?? 'combat')))
            . ';border:2px solid #111;padding:5px 14px;font-weight:bold;font-size:10px;margin:3px 0;">'
            . $e(strtoupper((string) ($root['type'] ?? 'TF'))) . '</div>'
            . '<div style="font-size:12px;font-weight:bold;">' . $e($root['name'] ?? '') . '</div>'
            . '<div style="font-size:9px;">(± ' . (int) ($totals['theoretical'] ?? 0) . ' pers.)</div>'
            . '<div style="font-size:8px;">' . $e($root['leader'] ?? '—') . '</div>'
            . '</td>'
            . '<td width="32%" valign="top">'
            . '<table cellpadding="3" cellspacing="0" border="1" width="100%">'
            . '<tr style="background-color:#1e2932;color:#fff;"><th colspan="2" align="left">COMPOSITION GÉNÉRALE</th></tr>'
            . '<tr><td>Effectif total (théorique)</td><td align="right"><b>' . (int) ($totals['theoretical'] ?? 0) . '</b></td></tr>'
            . '<tr><td>Effectif présent (T/O)</td><td align="right"><b>' . (int) ($totals['present'] ?? 0) . '</b></td></tr>'
            . '<tr><td>Taux de disponibilité</td><td align="right"><b>' . (int) ($totals['rate'] ?? 0) . ' %</b></td></tr>'
            . '<tr><td>Unités affichées</td><td align="right"><b>' . $unitCount . '</b></td></tr>'
            . '</table></td></tr></table>'
            . '<br/>'
            . '<table width="100%" cellpadding="0" cellspacing="4">' . $groupCells . '</table>'
            . '<br/>'
            . '<table width="100%" cellpadding="0" cellspacing="6"><tr>'
            . '<td width="55%" valign="top">'
            . '<table cellpadding="2" cellspacing="0" border="1" width="100%">'
            . '<tr style="background-color:#1e2932;color:#fff;">'
            . '<th align="left">ÉLÉMENT (toutes unités)</th><th>THÉORIQUE</th><th>PRÉSENT</th><th>DISP.</th></tr>'
            . $strengthRows
            . '<tr style="background-color:#f3f4f5;font-weight:bold;"><td>TOTAL</td>'
            . '<td align="center">' . (int) ($totals['theoretical'] ?? 0) . '</td>'
            . '<td align="center">' . (int) ($totals['present'] ?? 0) . '</td>'
            . '<td align="center">' . (int) ($totals['rate'] ?? 0) . ' %</td></tr>'
            . '</table></td>'
            . '<td width="45%" valign="top">'
            . (!empty($meta['include_mission'])
                ? '<table cellpadding="3" cellspacing="0" border="1" width="100%">'
                . '<tr style="background-color:#1e2932;color:#fff;"><th align="left">MISSION</th></tr>'
                . '<tr><td style="font-size:8px;">' . $e(($root['mission'] ?? '') !== '' ? (string) $root['mission'] : 'Conduire les opérations conformément aux directives du commandement.') . '</td></tr></table>'
                : '')
            . '</td></tr></table>'
            . '<br/><table width="100%" cellpadding="3"><tr>'
            . '<td width="33%">' . $e($meta['doc_code'] ?? 'FM ATHENA RH-01') . '</td>'
            . '<td width="34%" align="center" style="font-size:8px;font-weight:bold;">' . $classif . '</td>'
            . '<td width="33%" align="right">ANNEXE B-1 · ' . $paper . ' · saut de page auto</td>'
            . '</tr></table>'
            . '</div>';
    }

    /**
     * @param array<string, mixed> $node
     * @param callable(mixed): string $e
     */
    private function renderUnitTcpdf(array $node, callable $e, int $depth): string
    {
        $type = (string) ($node['type'] ?? 'combat');
        $fs = max(7, 10 - $depth);
        $html = '<div style="text-align:center;font-size:' . ($fs + 1) . 'px;font-weight:bold;letter-spacing:1px;">'
            . $e($node['echelon'] ?? '') . '</div>'
            . '<div style="text-align:center;background:' . $e($this->symbolColor($type))
            . ';border:1.5px solid #111;padding:3px;margin:3px auto;font-size:' . $fs
            . 'px;font-weight:bold;max-width:120px;">'
            . $e(strtoupper((string) ($node['type_label'] ?? $type))) . '</div>'
            . '<div style="text-align:center;font-size:' . ($fs + 1) . 'px;font-weight:bold;">'
            . $e($node['title'] ?? '') . '</div>'
            . '<div style="text-align:center;font-size:' . $fs . 'px;">'
            . (int) ($node['present'] ?? 0) . ' / ' . (int) ($node['theoretical'] ?? 0) . ' pers.</div>'
            . '<div style="text-align:center;font-size:' . max(7, $fs - 1) . 'px;">'
            . $e($node['rank'] ?? '') . '</div>';

        $kids = is_array($node['children'] ?? null) ? $node['children'] : [];
        if ($kids === []) {
            return $html;
        }

        $validKids = array_values(array_filter($kids, static fn ($c): bool => is_array($c)));
        $html .= '<table width="100%" cellpadding="2" cellspacing="2" style="margin-top:4px;">';
        foreach (array_chunk($validKids, 4) as $chunk) {
            $html .= '<tr>';
            $colW = max(10, (int) floor(100 / max(1, count($chunk))));
            foreach ($chunk as $child) {
                /** @var array<string, mixed> $child */
                $html .= '<td width="' . $colW . '%" valign="top" style="border:0.5px solid #94a3b8;padding:3px;">'
                    . $this->renderUnitTcpdf($child, $e, $depth + 1)
                    . '</td>';
            }
            $html .= '</tr>';
        }
        $html .= '</table>';

        return $html;
    }

    /**
     * @param array<string, mixed> $node
     * @return array<string, mixed>
     */
    private function normalizeUnit(array $node): array
    {
        $theo = $this->subtreeStrength($node);
        $pres = $this->subtreePresent($node);
        $type = $this->symbolType((string) ($node['type'] ?? ''), (string) ($node['structType'] ?? ''));
        $children = [];
        foreach ($node['children'] ?? [] as $child) {
            if (!is_array($child)) {
                continue;
            }
            $children[] = $this->normalizeUnit($child);
        }

        $title = trim((string) ($node['label'] ?? 'Unité'));
        $code = trim((string) ($node['role'] ?? ''));

        return [
            'echelon' => $this->echelonMark($theo, count($children)),
            'type' => $type,
            'type_label' => $this->typeLabel($type),
            'title' => $title,
            'code' => $code,
            'name' => $title,
            'theoretical' => $theo,
            'present' => $pres,
            'rank' => trim((string) ($node['leader'] ?? '—')),
            'leader' => trim((string) ($node['leader'] ?? '—')),
            'desc' => trim((string) ($node['mission'] ?? '')),
            'unit_id' => (int) ($node['unitId'] ?? 0),
            'children' => $children,
        ];
    }

    /**
     * @param array<string, mixed> $node
     * @param list<array<string, mixed>> $out
     */
    private function collectFlatUnits(array $node, array &$out, int $depth): void
    {
        $out[] = [
            'title' => (string) ($node['title'] ?? $node['name'] ?? 'Unité'),
            'code' => (string) ($node['code'] ?? ''),
            'theoretical' => (int) ($node['theoretical'] ?? 0),
            'present' => (int) ($node['present'] ?? 0),
            'type' => (string) ($node['type'] ?? 'combat'),
            'depth' => $depth,
            'unit_id' => (int) ($node['unit_id'] ?? 0),
        ];
        foreach ($node['children'] ?? [] as $child) {
            if (is_array($child)) {
                $this->collectFlatUnits($child, $out, $depth + 1);
            }
        }
    }

    /**
     * @param list<array<string, mixed>> $nodes
     */
    private function maxBreadth(array $nodes): int
    {
        if ($nodes === []) {
            return 0;
        }
        $max = count($nodes);
        foreach ($nodes as $n) {
            if (!is_array($n)) {
                continue;
            }
            $kids = is_array($n['children'] ?? null) ? $n['children'] : [];
            $max = max($max, $this->maxBreadth($kids));
        }

        return $max;
    }

    /**
     * @param list<array<string, mixed>> $nodes
     */
    private function maxDepth(array $nodes): int
    {
        if ($nodes === []) {
            return 0;
        }
        $d = 1;
        foreach ($nodes as $n) {
            if (!is_array($n)) {
                continue;
            }
            $kids = is_array($n['children'] ?? null) ? $n['children'] : [];
            $d = max($d, 1 + $this->maxDepth($kids));
        }

        return $d;
    }

    /**
     * @return array{name:string,css:string,width_mm:int,height_mm:int}
     */
    private function resolvePaper(string $forced, int $breadth, int $depth, int $unitCount): array
    {
        $catalog = [
            'A3' => ['name' => 'A3', 'css' => 'A3 landscape', 'width_mm' => 420, 'height_mm' => 297],
            'A2' => ['name' => 'A2', 'css' => 'A2 landscape', 'width_mm' => 594, 'height_mm' => 420],
            'A1' => ['name' => 'A1', 'css' => 'A1 landscape', 'width_mm' => 841, 'height_mm' => 594],
            'A0' => ['name' => 'A0', 'css' => 'A0 landscape', 'width_mm' => 1189, 'height_mm' => 841],
        ];

        $key = strtoupper($forced);
        if (isset($catalog[$key])) {
            return $catalog[$key];
        }

        // Heuristique : largeur réelle de l’ORBAT, jamais A4
        if ($unitCount <= 10 && $breadth <= 4 && $depth <= 3) {
            return $catalog['A3'];
        }
        if ($unitCount <= 28 && $breadth <= 8 && $depth <= 5) {
            return $catalog['A2'];
        }
        if ($unitCount <= 70 && $breadth <= 14 && $depth <= 7) {
            return $catalog['A1'];
        }
        if ($unitCount <= 140 && $breadth <= 22) {
            return $catalog['A0'];
        }

        // Format personnalisé plus large que A0 si nécessaire
        $w = min(2000, max(1189, 120 + $breadth * 55));
        $h = min(1400, max(841, 200 + $depth * 90));

        return [
            'name' => 'CUSTOM',
            'css' => $w . 'mm ' . $h . 'mm',
            'width_mm' => $w,
            'height_mm' => $h,
        ];
    }

    /** @param array<string, mixed> $node */
    private function subtreeStrength(array $node): int
    {
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

        return max(
            (int) ($node['strength'] ?? 0),
            (int) ($node['visibleStrength'] ?? 0),
            0
        );
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
