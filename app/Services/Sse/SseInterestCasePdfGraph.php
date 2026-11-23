<?php

declare(strict_types=1);

namespace App\Services\Sse;

/** Graphe vectoriel paginé : huit éléments par planche, répertoire complet dans le PDF. */
final class SseInterestCasePdfGraph
{
    public function draw(object $pdf, array $graph): void
    {
        $nodes = array_filter((array) ($graph['nodes'] ?? []), static fn (array $n): bool => empty($n['restricted']));
        $rootKey = (string) ($graph['root'] ?? '');
        $root = $nodes[$rootKey] ?? null;
        if ($root === null || count($nodes) < 2) { return; }
        unset($nodes[$rootKey]);
        $pages = array_chunk($nodes, 8, true);
        foreach ($pages as $index => $page) {
            $pdf->AddPage();
            $pdf->SetFont('dejavusans', 'B', 14);
            $pdf->SetXY(14, 42);
            $pdf->MultiCell(180, 14, 'Réseau du dossier · ' . ($index + 1) . '/' . count($pages), 0, 'L');
            $positions = [$rootKey => [75.0, 136.0, 60.0, 28.0]];
            $i = 0;
            foreach ($page as $key => $node) {
                $angle = -M_PI / 2 + $i++ * M_PI / 4;
                $positions[$key] = [105 + 70 * cos($angle) - 23, 150 + 72 * sin($angle) - 11, 46.0, 22.0];
            }
            $pdf->SetDrawColor(133, 148, 166);
            $pdf->SetLineWidth(0.35);
            foreach ((array) ($graph['edges'] ?? []) as $edge) {
                if (!isset($positions[$edge['from']], $positions[$edge['to']])) { continue; }
                [$x, $y, $w, $h] = $positions[$edge['from']];
                [$tx, $ty, $tw, $th] = $positions[$edge['to']];
                $pdf->Line($x + $w / 2, $y + $h / 2, $tx + $tw / 2, $ty + $th / 2);
            }
            foreach ([$rootKey => $root] + $page as $key => $node) {
                [$x, $y, $w, $h] = $positions[$key];
                $isRoot = $key === $rootKey;
                $pdf->SetFillColor(...($isRoot ? [21, 47, 65] : [241, 245, 249]));
                $pdf->RoundedRect($x, $y, $w, $h, 2, '1111', 'DF');
                $pdf->SetTextColor(...($isRoot ? [255, 255, 255] : [21, 47, 65]));
                $pdf->SetFont('dejavusans', 'B', 8);
                $pdf->SetXY($x + 2, $y + 2);
                $pdf->MultiCell($w - 4, 4, (string) ($node['type_label'] ?? '') . ' · ' . (string) ($node['ref'] ?? ''), 0, 'C');
                $pdf->SetFont('dejavusans', '', 8);
                $pdf->SetXY($x + 2, $y + 10);
                $pdf->MultiCell($w - 4, 4, mb_strimwidth((string) ($node['title'] ?? ''), 0, 60, '…', 'UTF-8'), 0, 'C');
            }
            $pdf->SetTextColor(71, 85, 105);
            $pdf->SetFont('dejavusans', '', 9);
            $pdf->SetXY(16, 241);
            $pdf->MultiCell(178, 12, 'Les libellés complets, motifs et relations entre planches figurent au répertoire du réseau. Les éléments inaccessibles sont exclus.', 0, 'L');
        }
    }
}
