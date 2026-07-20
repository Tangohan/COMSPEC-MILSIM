<?php

declare(strict_types=1);

namespace App\Support\Training;

use App\Support\TrainingFormationCustomPageRenderer;

/**
 * Diff texte simple (HTML → texte, ligne par ligne) entre deux instantanés de révision
 * (content_snapshot_json) d'une Documentation HTML. Algorithme LCS classique, plafonné en
 * taille pour rester rapide sur de gros manuels.
 */
final class TrainingRevisionDiffService
{
    private const MAX_LINES = 800;

    /**
     * @param array<string, mixed> $before
     * @param array<string, mixed> $after
     * @return list<array{type: string, text: string}> type = same|added|removed
     */
    public function diffSnapshots(array $before, array $after): array
    {
        $beforeLines = self::snapshotToLines($before);
        $afterLines = self::snapshotToLines($after);

        return self::diffLines($beforeLines, $afterLines);
    }

    /** @return list<string> */
    private static function snapshotToLines(array $snapshot): array
    {
        $lines = [];
        $title = trim((string) ($snapshot['title'] ?? ''));
        if ($title !== '') {
            $lines[] = '# ' . $title;
        }
        $subtitle = trim((string) ($snapshot['subtitle'] ?? ''));
        if ($subtitle !== '') {
            $lines[] = $subtitle;
        }
        $summary = trim((string) ($snapshot['summary'] ?? ''));
        if ($summary !== '') {
            $lines[] = $summary;
        }
        $intro = trim((string) (($snapshot['intro_html'] ?? '') ?: ($snapshot['html_body'] ?? '')));
        if ($intro !== '') {
            array_push($lines, ...self::htmlToLines($intro));
        }
        $sections = TrainingFormationCustomPageRenderer::decodeSections(
            isset($snapshot['sections_json']) ? (string) $snapshot['sections_json'] : null
        );
        foreach ($sections as $s) {
            $lines[] = '## ' . $s['title'];
            array_push($lines, ...self::htmlToLines($s['html']));
        }

        return array_slice($lines, 0, self::MAX_LINES);
    }

    /** @return list<string> */
    private static function htmlToLines(string $html): array
    {
        $normalized = preg_replace('/<\/(p|div|li|h[1-6]|br|tr)>/i', "\n", $html) ?? $html;
        $text = trim(html_entity_decode(strip_tags($normalized), ENT_QUOTES, 'UTF-8'));
        $lines = preg_split('/\r\n|\r|\n/', $text) ?: [];
        $lines = array_map('trim', $lines);

        return array_values(array_filter($lines, static fn (string $l): bool => $l !== ''));
    }

    /**
     * @param list<string> $a
     * @param list<string> $b
     * @return list<array{type: string, text: string}>
     */
    private static function diffLines(array $a, array $b): array
    {
        $n = count($a);
        $m = count($b);
        // LCS par programmation dynamique (n*m plafonné par MAX_LINES des deux côtés).
        $lcs = array_fill(0, $n + 1, array_fill(0, $m + 1, 0));
        for ($i = $n - 1; $i >= 0; $i--) {
            for ($j = $m - 1; $j >= 0; $j--) {
                $lcs[$i][$j] = $a[$i] === $b[$j]
                    ? $lcs[$i + 1][$j + 1] + 1
                    : max($lcs[$i + 1][$j], $lcs[$i][$j + 1]);
            }
        }

        $out = [];
        $i = 0;
        $j = 0;
        while ($i < $n && $j < $m) {
            if ($a[$i] === $b[$j]) {
                $out[] = ['type' => 'same', 'text' => $a[$i]];
                $i++;
                $j++;
            } elseif ($lcs[$i + 1][$j] >= $lcs[$i][$j + 1]) {
                $out[] = ['type' => 'removed', 'text' => $a[$i]];
                $i++;
            } else {
                $out[] = ['type' => 'added', 'text' => $b[$j]];
                $j++;
            }
        }
        while ($i < $n) {
            $out[] = ['type' => 'removed', 'text' => $a[$i]];
            $i++;
        }
        while ($j < $m) {
            $out[] = ['type' => 'added', 'text' => $b[$j]];
            $j++;
        }

        return $out;
    }
}
