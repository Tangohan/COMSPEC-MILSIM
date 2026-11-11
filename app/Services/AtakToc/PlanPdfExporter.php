<?php

declare(strict_types=1);

namespace App\Services\AtakToc;

use App\Support\Pdf\AthenaPdfTheme as Theme;

/**
 * Fiche de briefing PDF d'un plan (gabarit ATHENA) : carte du plan (image rendue par l'éditeur, ou tracé vectoriel
 * sans fond), phases, légende, ordre d'opération (SMEAC) et liste des éléments avec leur grille.
 */
final class PlanPdfExporter
{
    public const MAX_IMAGE_BYTES = 8 * 1024 * 1024;

    /** Grille Arma à 6 ou 8 chiffres (mètres depuis le coin sud-ouest). */
    public static function grid(float $x, float $y, int $digits = 8): string
    {
        $div = $digits === 6 ? 100 : 10;
        $w = $digits === 6 ? 3 : 4;

        return sprintf('%0' . $w . 'd %0' . $w . 'd', max(0, (int) floor($x / $div)), max(0, (int) floor($y / $div)));
    }

    /**
     * Image envoyée par l'éditeur (data URL PNG ou JPEG) => octets, ou null si invalide.
     */
    public static function decodeImage(string $dataUrl): ?string
    {
        if (preg_match('#^data:image/(png|jpeg);base64,([A-Za-z0-9+/=]+)$#', $dataUrl, $m) !== 1) {
            return null;
        }
        if (strlen($m[2]) > (int) (self::MAX_IMAGE_BYTES * 1.37)) {
            return null;
        }
        $bin = base64_decode($m[2], true);
        if ($bin === false || strlen($bin) > self::MAX_IMAGE_BYTES) {
            return null;
        }
        $png = str_starts_with($bin, "\x89PNG\r\n\x1a\n");
        $jpg = str_starts_with($bin, "\xFF\xD8\xFF");
        if (($m[1] === 'png' && !$png) || ($m[1] === 'jpeg' && !$jpg)) {
            return null;
        }
        $size = @getimagesizefromstring($bin);
        if (!is_array($size) || $size[0] < 16 || $size[1] < 16 || $size[0] > 8000 || $size[1] > 8000) {
            return null;
        }

        return $bin;
    }

    /**
     * Lignes de la fiche (testables sans TCPDF).
     *
     * @param array<string, mixed> $doc document du plan (PlanService::document)
     * @return array{phases: list<array<string, string>>, elements: list<array<string, string>>, notes: list<array{0: string, 1: string}>}
     */
    public static function rows(array $doc): array
    {
        $kinds = PlanCatalog::kinds();
        $phaseNames = [];
        $phases = [];
        foreach ($doc['phases'] as $i => $ph) {
            $phaseNames[$ph['uid']] = ($i + 1) . '. ' . $ph['name'];
            $count = 0;
            foreach ($doc['elements'] as $el) {
                if ($el['phases'] === [] || in_array($ph['uid'], $el['phases'], true)) {
                    $count++;
                }
            }
            $phases[] = ['n' => (string) ($i + 1), 'name' => (string) $ph['name'], 'time' => (string) $ph['time_label'], 'note' => (string) $ph['note'], 'count' => (string) $count];
        }
        $elements = [];
        foreach ($doc['elements'] as $el) {
            $p = $el['props'];
            [$x, $y] = $el['pts'][0];
            $label = (string) ($el['kind'] === 'unit' ? (($p['callsign'] ?? '') ?: ($p['label'] ?? '')) : ($p['label'] ?? ''));
            $elements[] = [
                'kind' => (string) ($kinds[$el['kind']]['label'] ?? $el['kind']),
                'label' => $label,
                'grid' => self::grid((float) $x, (float) $y, 8),
                'phases' => $el['phases'] === [] ? 'Toutes' : implode(', ', array_map(static fn (string $u): string => explode('.', $phaseNames[$u] ?? '?')[0], $el['phases'])),
            ];
        }
        $titles = ['situation' => '1. Situation', 'mission' => '2. Mission', 'execution' => '3. Exécution', 'support' => '4. Soutien', 'command' => '5. Commandement et transmissions', 'free' => 'Notes'];
        $notes = [];
        foreach ($titles as $k => $t) {
            $v = trim((string) ($doc['meta']['notes'][$k] ?? ''));
            if ($v !== '') {
                $notes[] = [$t, $v];
            }
        }

        return ['phases' => $phases, 'elements' => $elements, 'notes' => $notes];
    }

    /**
     * @param array<string, mixed> $doc
     */
    public function render(array $doc, int $tenantId, string $author, ?string $image): string
    {
        $rows = self::rows($doc);
        $pdf = Theme::tcpdf([
            'kind' => 'Plan d’opération',
            'reference' => 'PLAN-' . (int) ($doc['plan_id'] ?? 0) . ' r' . (int) ($doc['rev'] ?? 0),
            'tenant_id' => $tenantId,
            'classification' => 'EXERCISE / MILSIM',
            'audience' => 'Participants',
            'generated_by' => $author,
            'title' => (string) $doc['title'],
        ]);
        if ($pdf === null) {
            throw new \RuntimeException('TCPDF indisponible');
        }
        $pdf->AddPage();
        $pills = [[strtoupper((string) ($doc['world'] ?: 'terrain')), 'info'], [(string) ($doc['meta']['side'] ?? 'WEST'), 'ok']];
        if (trim((string) ($doc['meta']['dtg'] ?? '')) !== '') {
            $pills[] = [(string) $doc['meta']['dtg'], 'muted'];
        }
        // Pas de petit libellé au-dessus du titre (règle de TTRD) : le genre du document est dans l'en-tête de page.
        $pdf->writeAthenaHtml(Theme::titleBlock('', (string) $doc['title'], $pills, count($doc['elements']) . ' élément(s), ' . count($doc['phases']) . ' phase(s)'));
        $y = $pdf->GetY() + 2;
        $w = 180.0;
        $h = 120.0;
        if ($image !== null) {
            $size = getimagesizefromstring($image);
            if (is_array($size)) {
                $h = min(150.0, $w * $size[1] / max(1, $size[0]));
            }
            $pdf->Image('@' . $image, 15, $y, $w, $h, '', '', '', true, 200);
        } else {
            $this->drawVector($pdf, $doc, 15, $y, $w, $h);
        }
        $pdf->SetY($y + $h + 4);
        $legend = is_array($doc['meta']['legend'] ?? null) ? $doc['meta']['legend'] : [];
        if ($legend !== []) {
            $items = array_map(static fn (array $l): string => '<span style="color:' . Theme::e($l['color']) . '">■</span> ' . Theme::e($l['label']), $legend);
            $pdf->writeAthenaHtml(Theme::paragraph(implode('&nbsp;&nbsp;&nbsp;', $items)));
        }
        if ($rows['phases'] !== []) {
            $pdf->AddPage();
            $pdf->writeAthenaHtml(Theme::section('1', 'Phases'));
            $pdf->writeAthenaHtml(Theme::table(
                [['label' => 'N°', 'width' => 7, 'mono' => true], ['label' => 'Phase', 'width' => 28, 'bold' => true], ['label' => 'Heure', 'width' => 13, 'mono' => true], ['label' => 'Éléments', 'width' => 12, 'align' => 'R'], ['label' => 'Note', 'width' => 40]],
                array_map(static fn (array $r): array => [$r['n'], $r['name'], $r['time'], $r['count'], $r['note']], $rows['phases'])
            ));
        }
        if ($rows['notes'] !== []) {
            $pdf->writeAthenaHtml(Theme::section('2', 'Ordre d’opération'));
            foreach ($rows['notes'] as [$title, $text]) {
                $pdf->writeAthenaHtml('<h4>' . Theme::e($title) . '</h4>' . Theme::paragraph($text));
            }
        }
        if ($rows['elements'] !== []) {
            $pdf->writeAthenaHtml(Theme::section('3', 'Éléments du plan'));
            $pdf->writeAthenaHtml(Theme::table(
                [['label' => 'Type', 'width' => 32], ['label' => 'Libellé', 'width' => 30, 'bold' => true], ['label' => 'Grille', 'width' => 20, 'mono' => true], ['label' => 'Phases', 'width' => 18]],
                array_map(static fn (array $r): array => [$r['kind'], $r['label'], $r['grid'], $r['phases']], array_slice($rows['elements'], 0, 400))
            ));
        }

        return (string) $pdf->Output('', 'S');
    }

    /**
     * Tracé vectoriel du plan sans fond de carte (quadrillage, zones, traits, points).
     *
     * @param array<string, mixed> $doc
     */
    private function drawVector(\TCPDF $pdf, array $doc, float $x0, float $y0, float $w, float $h): void
    {
        $pts = [];
        foreach ($doc['elements'] as $el) {
            foreach ($el['pts'] as $p) {
                $pts[] = $p;
            }
        }
        if ($pts === []) {
            return;
        }
        $xs = array_column($pts, 0);
        $ys = array_column($pts, 1);
        $minX = min($xs) - 200;
        $maxX = max($xs) + 200;
        $minY = min($ys) - 200;
        $maxY = max($ys) + 200;
        $scale = min($w / max(1, $maxX - $minX), $h / max(1, $maxY - $minY));
        $tx = static fn (float $x): float => $x0 + ($x - $minX) * $scale;
        $ty = static fn (float $y): float => $y0 + $h - ($y - $minY) * $scale;
        $pdf->SetFillColor(240, 242, 244);
        $pdf->Rect($x0, $y0, $w, $h, 'F');
        $pdf->SetDrawColor(200, 205, 210);
        $pdf->SetLineWidth(0.1);
        $step = 1000;
        for ($gx = ceil($minX / $step) * $step; $gx < $maxX; $gx += $step) {
            if ($tx($gx) <= $x0 + $w) {
                $pdf->Line($tx($gx), $y0, $tx($gx), $y0 + $h);
            }
        }
        for ($gy = ceil($minY / $step) * $step; $gy < $maxY; $gy += $step) {
            if ($ty($gy) >= $y0) {
                $pdf->Line($x0, $ty($gy), $x0 + $w, $ty($gy));
            }
        }
        $pdf->SetFont(Theme::sans(), '', 7);
        foreach ($doc['elements'] as $el) {
            $c = Theme::rgb((string) ($el['props']['color'] ?? '#333333'));
            // Blanc illisible sur fond clair : gris foncé.
            if ($c[0] > 220 && $c[1] > 220 && $c[2] > 220) {
                $c = [60, 64, 70];
            }
            $pdf->SetDrawColor($c[0], $c[1], $c[2]);
            $pdf->SetTextColor($c[0], $c[1], $c[2]);
            $pdf->SetLineWidth(0.25 + 0.1 * (int) ($el['props']['width'] ?? 2));
            $poly = PlanGameMapper::outline($el, 36);
            $flat = [];
            foreach ($poly as $p) {
                $flat[] = $tx((float) $p[0]);
                $flat[] = $ty((float) $p[1]);
            }
            if (in_array($el['geom'], ['area', 'circle', 'ellipse', 'rect'], true) && count($poly) >= 3) {
                $pdf->Polygon($flat, 'D');
            } elseif ($el['geom'] === 'line') {
                $pdf->PolyLine($flat, 'D');
            } else {
                $pdf->Circle($flat[0], $flat[1], 1.2, 0, 360, 'D');
            }
            $label = (string) (($el['props']['callsign'] ?? '') ?: ($el['props']['label'] ?? ''));
            if ($label !== '') {
                $pdf->Text($flat[0] + 1.5, $flat[1] - 1.5, $label);
            }
        }
        $pdf->SetTextColor(0, 0, 0);
    }
}
