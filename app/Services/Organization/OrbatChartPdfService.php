<?php

declare(strict_types=1);

namespace App\Services\Organization;

use App\Core\Response;
use App\Support\OrbatSheetLabels;
use App\Support\Pdf\AthenaPdfTheme as Theme;
use App\Support\TrainingCertificatePdfEngine;

/**
 * Export ORBAT style FM (mockup ATHENA) — HTML imprimable + PDF au gabarit Athena.
 * La vue imprimable HTML garde un format papier adaptatif (A3→A0 / personnalisé, meta « paper ») ;
 * le PDF est toujours en A4 portrait : ordre de bataille en tableau arborescent, sur plusieurs pages
 * si besoin. Toutes les sous-unités sont incluses sans troncature.
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
            $unitLabel = $rootLabel !== '' && $rootLabel !== 'Command'
                ? $rootLabel
                : OrbatSheetLabels::get('organisation_default', (string) ($meta['lang'] ?? ''));
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
                'lang' => OrbatSheetLabels::language($meta['lang'] ?? null),
                'country' => trim((string) ($meta['country'] ?? '')),
                'country_label' => trim((string) ($meta['country_label'] ?? '')),
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
     * Téléchargement PDF : A4 portrait au gabarit Athena (TCPDF, sinon Dompdf).
     *
     * @param array<string, mixed> $document
     */
    public function pdfResponse(array $document): Response
    {
        $meta = is_array($document['meta'] ?? null) ? $document['meta'] : [];
        $filename = 'orbat-' . preg_replace('/[^a-zA-Z0-9_-]+/', '-', strtolower((string) ($meta['reference'] ?? 'export'))) . '.pdf';

        return TrainingCertificatePdfEngine::suppressTcpdfPhpDeprecationsWhile(function () use ($document, $filename): Response {
            $binary = $this->buildPdf($document);
            if ($binary === null) {
                return (new Response())
                    ->setStatusCode(503)
                    ->header('Content-Type', 'text/plain; charset=UTF-8')
                    ->setBody('Export PDF indisponible (moteur PDF manquant).');
            }

            return (new Response())
                ->header('Content-Type', 'application/pdf')
                ->header('Content-Disposition', 'attachment; filename="' . $filename . '"')
                ->header('Cache-Control', 'private, no-store')
                ->setBody($binary);
        });
    }

    /**
     * Binaire PDF A4 portrait (TCPDF via le gabarit Athena, repli Dompdf) ; null si aucun moteur.
     *
     * @param array<string, mixed> $document
     */
    public function buildPdf(array $document): ?string
    {
        $docMeta = is_array($document['meta'] ?? null) ? $document['meta'] : [];
        $lang = OrbatSheetLabels::language($docMeta['lang'] ?? null);
        $unitLabel = trim((string) ($docMeta['unit_label'] ?? ''));
        $tenantId = (int) ($docMeta['tenant_id'] ?? 0);
        if ($tenantId < 1 && isset($_SESSION['tenant_id'])) {
            $tenantId = (int) $_SESSION['tenant_id'];
        }
        $pdfMeta = [
            'kind' => self::label('kind', $lang),
            'reference' => trim((string) ($docMeta['reference'] ?? '')),
            'tenant_id' => $tenantId,
            'community' => $tenantId > 0 ? '' : $unitLabel,
            'classification' => trim((string) ($docMeta['classification'] ?? '')),
            'audience' => trim((string) ($docMeta['doc_code'] ?? '')),
            'title' => 'ORBAT — ' . ($unitLabel !== '' ? $unitLabel : 'Organisation'),
        ];
        $body = $this->tcpdfFriendlyHtml($document);

        $pdf = Theme::tcpdf($pdfMeta);
        if ($pdf !== null) {
            $pdf->AddPage();
            $pdf->writeAthenaHtml($body);
            $out = $pdf->Output('', 'S');

            return is_string($out) && $out !== '' ? $out : null;
        }

        return Theme::dompdfRender(Theme::dompdfDocument($body, $pdfMeta), $pdfMeta);
    }

    /**
     * Corps du PDF (fragments du gabarit Athena, compatibles TCPDF/Dompdf) : indicateurs,
     * composition générale, ordre de bataille arborescent (toutes les unités), mission, notes, légende.
     *
     * @param array<string, mixed> $document
     */
    public function tcpdfFriendlyHtml(array $document): string
    {
        $meta = is_array($document['meta'] ?? null) ? $document['meta'] : [];
        $root = is_array($document['root'] ?? null) ? $document['root'] : [];
        $groups = is_array($document['groups'] ?? null) ? $document['groups'] : [];
        $totals = is_array($document['totals'] ?? null) ? $document['totals'] : [];
        $e = static fn (mixed $v): string => Theme::e($v);
        $lang = OrbatSheetLabels::language($meta['lang'] ?? null);
        $t = static fn (string $key): string => OrbatSheetLabels::get($key, $lang);
        $countryLabel = \App\Support\CommunityLanguages::countryLabelIn((string) ($meta['country'] ?? ''), $lang)
            ?: trim((string) ($meta['country_label'] ?? ''));

        $rows = [];
        foreach ($groups as $g) {
            if (is_array($g)) {
                $this->collectTreeRows($g, 0, $rows);
            }
        }
        $theo = (int) ($totals['theoretical'] ?? 0);
        $pres = (int) ($totals['present'] ?? 0);
        $rate = (int) ($totals['rate'] ?? 0);
        $unitCount = (int) ($totals['unit_count'] ?? count($rows));
        $active = count(array_filter($rows, static fn (array $r): bool => $r['present'] > 0));

        $html = Theme::titleBlock(
            $t('chart'),
            (string) ($root['name'] ?? ($meta['unit_label'] ?? '')),
            [[$t('version') . ' ' . (string) ($meta['version'] ?? '1.0'), 'muted'], [(string) ($meta['effect_date'] ?? ''), 'muted']],
            $t('toe')
        );

        $html .= Theme::stats([
            ['label' => $t('units'), 'value' => (string) $unitCount],
            ['label' => self::label('filled', $lang), 'value' => $pres . ' / ' . $theo, 'sub' => $rate . ' %', 'tone' => 'ok'],
            ['label' => $t('availability_rate'), 'value' => $rate . ' %'],
            ['label' => self::label('active', $lang), 'value' => (string) $active, 'sub' => sprintf(self::label('of', $lang), $unitCount)],
        ]);

        $n = 0;
        $num = static function () use (&$n): string {
            return sprintf('%02d', ++$n);
        };

        $identity = Theme::keyValues([
            [$t('unit'), (string) ($meta['unit_label'] ?? '')],
            [$t('country'), $countryLabel],
            [$t('theater'), (string) ($meta['theater'] ?? '—')],
            [$t('effect_date'), (string) ($meta['effect_date'] ?? '')],
            [$t('reference'), (string) ($meta['reference'] ?? '')],
            [$t('version'), (string) ($meta['version'] ?? '1.0')],
            [self::label('document', $lang), (string) ($meta['doc_code'] ?? '')],
        ]);
        $figures = Theme::keyValues([
            [self::label('commander', $lang), (string) ($root['leader'] ?? '—')],
            [self::label('code', $lang), (string) ($root['code'] ?? '')],
            [$t('strength_total'), (string) $theo],
            [$t('strength_present'), (string) $pres],
            [$t('availability_rate'), $rate . ' %'],
            [$t('units_shown'), (string) $unitCount],
        ], '62%');
        $html .= Theme::section($num(), $t('composition')) . Theme::twoColumns($identity, $figures);

        $html .= Theme::section($num(), $t('strength_summary'));
        $columns = [
            ['label' => $t('element'), 'width' => 37],
            ['label' => self::label('code', $lang), 'width' => 10, 'mono' => true],
            ['label' => self::label('commander', $lang), 'width' => 20],
            ['label' => self::label('pax', $lang), 'width' => 10, 'align' => 'R'],
            ['label' => $t('availability_short'), 'width' => 9, 'align' => 'R'],
            ['label' => self::label('status', $lang), 'width' => 14, 'align' => 'R'],
        ];
        $tableRows = [];
        foreach ($rows as $r) {
            $pct = $r['theoretical'] > 0 ? (int) round(($r['present'] / $r['theoretical']) * 100) : 0;
            [$statusKey, $tone] = match (true) {
                $r['theoretical'] < 1 && $r['present'] < 1 => ['status_none', 'muted'],
                $r['present'] < 1 => ['status_empty', 'bad'],
                $r['present'] >= $r['theoretical'] => ['status_full', 'ok'],
                default => ['status_partial', 'warn'],
            };
            $dash = '<span style="color:' . Theme::MUTED . ';">—</span>';
            $name = $e($r['title']);
            $tableRows[] = [
                str_repeat('&nbsp;', $r['depth'] * 4)
                    . '<span style="color:' . self::typeDotColor($r['type']) . ';font-size:7pt;">■</span>&nbsp;'
                    . ($r['depth'] === 0 ? '<b>' . $name . '</b>' : $name),
                $r['code'] !== '' ? $e($r['code']) : $dash,
                $r['leader'] !== '' && $r['leader'] !== '—' ? $e($r['leader']) : $dash,
                $r['present'] . ' / ' . $r['theoretical'],
                $pct . ' %',
                Theme::pill(self::label($statusKey, $lang), $tone),
            ];
        }
        $tableRows[] = [
            '<b>' . $e($t('total')) . '</b>', '', '', '<b>' . $pres . ' / ' . $theo . '</b>', '<b>' . $rate . ' %</b>', '',
        ];
        $html .= Theme::table($columns, $tableRows, false);

        if (!empty($meta['include_mission'])) {
            $mission = trim((string) ($root['mission'] ?? ''));
            $html .= Theme::section($num(), $t('mission'))
                . Theme::callout(Theme::paragraph($mission !== '' ? $mission : $t('mission_default')), '', 'ok');
        }
        if (!empty($meta['include_notes'])) {
            $html .= Theme::section($num(), $t('notes'))
                . Theme::bullets([$t('note_1'), $t('note_2'), $t('note_3')], 'muted');
        }
        if (!empty($meta['include_legend'])) {
            $types = array_values(array_unique(array_merge(['hq', 'combat', 'support', 'air'], array_column($rows, 'type'))));
            $cells = [];
            foreach ($types as $type) {
                $legendKey = 'legend_' . $type;
                $legend = OrbatSheetLabels::get($legendKey, $lang);
                $cells[] = '<span style="color:' . self::typeDotColor($type) . ';">■</span>&nbsp;<b>' . $e(OrbatSheetLabels::typeLabel($type, $lang)) . '</b>'
                    . ($legend !== $legendKey ? '&nbsp;<span style="color:' . Theme::MUTED . ';">' . $e($legend) . '</span>' : '');
            }
            $html .= Theme::section($num(), $t('legend'))
                . '<p style="font-size:8pt;">' . implode('&nbsp;&nbsp;&nbsp;&nbsp;', $cells) . '</p>';
        }

        return $html;
    }

    /**
     * Lignes de l'ordre de bataille (parcours en profondeur, aucune troncature).
     *
     * @param array<string, mixed> $node
     * @param list<array{title: string, code: string, leader: string, theoretical: int, present: int, type: string, depth: int}> $out
     */
    private function collectTreeRows(array $node, int $depth, array &$out): void
    {
        $out[] = [
            'title' => (string) ($node['title'] ?? $node['name'] ?? 'Unité'),
            'code' => trim((string) ($node['code'] ?? '')),
            'leader' => trim((string) ($node['leader'] ?? $node['rank'] ?? '')),
            'theoretical' => (int) ($node['theoretical'] ?? 0),
            'present' => (int) ($node['present'] ?? 0),
            'type' => (string) ($node['type'] ?? 'combat'),
            'depth' => $depth,
        ];
        foreach (is_array($node['children'] ?? null) ? $node['children'] : [] as $child) {
            if (is_array($child)) {
                $this->collectTreeRows($child, $depth + 1, $out);
            }
        }
    }

    /** Intitulés propres au PDF A4 (complètent OrbatSheetLabels). */
    private static function label(string $key, string $lang): string
    {
        $labels = [
            'kind' => ['fr' => 'Ordre de bataille', 'en' => 'Order of battle', 'ru' => 'Боевой состав', 'es' => 'Orden de batalla'],
            'filled' => ['fr' => 'Effectif pourvu', 'en' => 'Filled strength', 'ru' => 'Укомплектовано', 'es' => 'Efectivo cubierto'],
            'active' => ['fr' => 'Actives', 'en' => 'Active', 'ru' => 'Активные', 'es' => 'Activas'],
            'of' => ['fr' => 'sur %d', 'en' => 'of %d', 'ru' => 'из %d', 'es' => 'de %d'],
            'document' => ['fr' => 'Document', 'en' => 'Document', 'ru' => 'Документ', 'es' => 'Documento'],
            'code' => ['fr' => 'Code', 'en' => 'Code', 'ru' => 'Код', 'es' => 'Código'],
            'commander' => ['fr' => 'Commandant', 'en' => 'Commander', 'ru' => 'Командир', 'es' => 'Comandante'],
            'pax' => ['fr' => 'Pax', 'en' => 'Pax', 'ru' => 'Чел.', 'es' => 'Pax'],
            'status' => ['fr' => 'Statut', 'en' => 'Status', 'ru' => 'Статус', 'es' => 'Estado'],
            'status_full' => ['fr' => 'Complète', 'en' => 'Full', 'ru' => 'Полный', 'es' => 'Completa'],
            'status_partial' => ['fr' => 'Partielle', 'en' => 'Partial', 'ru' => 'Частично', 'es' => 'Parcial'],
            'status_empty' => ['fr' => 'Non pourvue', 'en' => 'Unfilled', 'ru' => 'Пусто', 'es' => 'Sin cubrir'],
            'status_none' => ['fr' => 'À constituer', 'en' => 'To be formed', 'ru' => 'Формируется', 'es' => 'Por constituir'],
        ];
        $row = $labels[$key] ?? null;

        return $row === null ? $key : ($row[$lang] ?? $row['fr']);
    }

    /** Puce de type d'unité (teintes sourdes, lisibles sur fond blanc). */
    private static function typeDotColor(string $type): string
    {
        return match ($type) {
            'hq' => Theme::INK,
            'support' => '#5f8f2e',
            'air' => '#4a90c2',
            'fire' => '#c9a227',
            'engineer' => '#c26a1b',
            'log' => '#8a9199',
            'isr' => '#6b5fb5',
            default => '#3b6ea5',
        };
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
