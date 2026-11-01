<?php

declare(strict_types=1);

namespace App\Services\Export;

use App\Core\Response;
use App\Support\HtmlTranslator;
use App\Support\Pdf\AthenaPdfTheme as Theme;
use App\Support\TrainingCertificatePdfEngine;

/**
 * Export PDF générique d'un tableau du back-office : en-tête communauté, titre, date d'édition, filtres
 * appliqués, puis le tableau (en-tête répété à chaque page). Réutilisable par tout écran qui sait
 * produire ses colonnes et ses lignes en texte.
 *
 * Document attendu :
 *  - title       : titre du tableau (« Effectifs · Médical »)
 *  - community   : nom de la communauté
 *  - filters     : liste de [libellé, valeur] (vide = « aucun filtre »)
 *  - columns     : liste de ['label' => …, 'weight' => largeur relative, 'align' => 'L'|'R'|'C',
 *                  'bold' => true (cellules en gras), 'alert' => true (cellules en rouge),
 *                  'muted' => valeurs affichées en gris (« Aucune connue »), 'translate' => true (valeurs repères)]
 *  - rows        : liste de lignes, chaque ligne = liste de cellules texte (même ordre que columns)
 *  - note        : mention de bas de page facultative (ex. confidentialité)
 *  - filename    : nom du fichier téléchargé, sans extension
 *  - reference   : référence du document (en-tête et pied de page), facultative
 *  - generated_by: auteur de l'édition (pied de page), facultatif
 *
 * Toujours en A4 portrait (gabarit Athena) : les colonnes se partagent la largeur, le texte passe à la ligne.
 *
 * Visiteur en anglais : titres, libellés, filtres et valeurs repères (« Aucune connue », colonnes 'translate')
 * passent par le dictionnaire du site ; les données saisies (noms, allergies…) restent telles quelles.
 */
final class TablePdfExportService
{
    public const MAX_ROWS = 2000;


    /**
     * @param array<string, mixed> $doc
     */
    public function export(array $doc): Response
    {
        $filename = self::safeFilename((string) ($doc['filename'] ?? 'export'));

        return TrainingCertificatePdfEngine::suppressTcpdfPhpDeprecationsWhile(function () use ($doc, $filename): Response {
            if (!TrainingCertificatePdfEngine::ensureTcpdfLoaded()) {
                return (new Response())->setStatusCode(503)->setBody('<p>Export PDF indisponible pour le moment.</p>');
            }
            $binary = $this->buildPdf($doc);

            return (new Response())
                ->header('Content-Type', 'application/pdf')
                ->header('Content-Disposition', 'attachment; filename="' . $filename . '.pdf"')
                ->header('Cache-Control', 'private, no-store')
                ->setBody($binary);
        });
    }

    /**
     * Corps HTML (TCPDF) : surtitre des filtres, titre, compteur, tableau (A4 portrait : les colonnes
     * se partagent la largeur et le texte passe à la ligne).
     *
     * @param array<string, mixed> $doc
     */
    public function buildHtml(array $doc): string
    {
        $e = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $t = self::translator();
        $columns = self::columns($doc);
        $rows = is_array($doc['rows'] ?? null) ? array_slice(array_values($doc['rows']), 0, self::MAX_ROWS) : [];
        $filters = is_array($doc['filters'] ?? null) ? $doc['filters'] : [];
        $total = (int) ($doc['total'] ?? count($rows));

        $filterBits = [];
        foreach ($filters as $f) {
            if (!is_array($f) || count($f) < 2 || trim((string) $f[1]) === '') {
                continue;
            }
            $filterBits[] = '<b>' . $e($t((string) $f[0])) . '</b> : ' . $e($t((string) $f[1]));
        }
        $count = $t($total . ($total > 1 ? ' lignes' : ' ligne'));
        if (count($rows) < $total) {
            $count .= ' (' . $t(count($rows) . ' imprimées') . ')';
        }

        $title = $t((string) ($doc['title'] ?? 'Tableau'));
        $html = Theme::titleBlock('', $title, [[$count, 'ok']]);
        $html .= '<p style="font-size:7.5pt;color:' . Theme::MUTED . ';">'
            . ($filterBits !== [] ? $e($t('Filtres')) . ' : ' . implode(' · ', $filterBits) : $e($t('Aucun filtre : tout le tableau.')))
            . '</p>' . Theme::spacer(2.5);

        if ($columns === []) {
            return $html;
        }
        if ($rows === []) {
            return $html . '<p style="color:' . Theme::MUTED . ';">' . $e($t('Aucune ligne ne correspond aux filtres.')) . '</p>';
        }

        $weights = array_sum(array_map(static fn (array $c): float => $c['weight'], $columns));
        $widths = array_map(static fn (array $c): string => number_format($c['weight'] * 100 / $weights, 2, '.', '') . '%', $columns);
        $size = count($columns) > 8 ? '6.6pt' : (count($columns) > 5 ? '7.4pt' : '8.2pt');
        $th = 'font-size:6.2pt;font-weight:bold;color:' . Theme::MUTED . ';border-bottom:0.6px solid ' . Theme::INK . ';';
        $html .= '<table cellpadding="2.6" cellspacing="0"><thead><tr>';
        foreach ($columns as $i => $c) {
            $html .= '<th width="' . $widths[$i] . '" align="' . self::align($c['align']) . '" style="' . $th . '">' . $e(mb_strtoupper($t($c['label']), 'UTF-8')) . '</th>';
        }
        $html .= '</tr></thead><tbody>';
        foreach ($rows as $row) {
            $cells = is_array($row) ? array_values($row) : [];
            $html .= '<tr nobr="true">';
            foreach ($columns as $i => $c) {
                $raw = trim((string) ($cells[$i] ?? ''));
                if ($raw === '' || in_array($raw, $c['muted'], true)) {
                    $cell = '<span style="color:' . Theme::MUTED . ';">' . ($raw !== '' ? $e($t($raw)) : '—') . '</span>';
                } else {
                    $cell = nl2br($e($c['translate'] ? $t($raw) : $raw));
                    if ($c['bold']) {
                        $cell = '<b>' . $cell . '</b>';
                    }
                    if ($c['alert']) {
                        $cell = '<span style="color:' . Theme::DANGER . ';">' . $cell . '</span>';
                    }
                }
                $html .= '<td width="' . $widths[$i] . '" align="' . self::align($c['align']) . '" style="font-size:' . $size . ';border-bottom:0.2px solid ' . Theme::LINE . ';">' . $cell . '</td>';
            }
            $html .= '</tr>';
        }

        return $html . '</tbody></table>';
    }

    /**
     * @param array<string, mixed> $doc
     * @return list<array{label: string, weight: float, align: string, bold: bool, alert: bool, muted: list<string>, translate: bool}>
     */
    private static function columns(array $doc): array
    {
        $out = [];
        foreach (is_array($doc['columns'] ?? null) ? $doc['columns'] : [] as $c) {
            if (is_string($c)) {
                $c = ['label' => $c];
            }
            if (!is_array($c)) {
                continue;
            }
            $out[] = [
                'label' => trim((string) ($c['label'] ?? '')),
                'weight' => max(0.5, (float) ($c['weight'] ?? 1)),
                'align' => (string) ($c['align'] ?? 'L'),
                'bold' => !empty($c['bold']),
                'alert' => !empty($c['alert']),
                'translate' => !empty($c['translate']),
                'muted' => array_values(array_map('strval', is_array($c['muted'] ?? null) ? $c['muted'] : [])),
            ];
        }

        return $out;
    }

    /**
     * Traduction d'un libellé pour un visiteur anglophone (« Effectifs · Médical » : chaque morceau séparément).
     *
     * @return \Closure(string): string
     */
    private static function translator(): \Closure
    {
        if (!function_exists('site_lang') || site_lang() === 'fr') {
            return static fn (string $s): string => $s;
        }

        return static fn (string $s): string => implode(' · ', array_map(
            static fn (string $part): string => HtmlTranslator::translateText($part),
            explode(' · ', $s)
        ));
    }

    private static function align(string $a): string
    {
        return match (strtoupper($a)) {
            'R' => 'right',
            'C' => 'center',
            default => 'left',
        };
    }

    public static function safeFilename(string $name): string
    {
        $ascii = function_exists('iconv') ? (string) @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $name) : $name;
        $slug = strtolower(trim((string) preg_replace('/[^A-Za-z0-9]+/', '-', $ascii), '-'));

        return $slug !== '' ? mb_substr($slug, 0, 80) : 'export';
    }

    /**
     * @param array<string, mixed> $doc
     */
    private function buildPdf(array $doc): string
    {
        $t = self::translator();
        $title = $t((string) ($doc['title'] ?? 'Tableau'));
        $community = trim((string) ($doc['community'] ?? 'Communauté'));
        $note = trim((string) ($doc['note'] ?? ''));
        $pdf = Theme::tcpdf([
            'kind' => $t('Liste d’effectifs'),
            'reference' => trim((string) ($doc['reference'] ?? '')) !== '' ? (string) $doc['reference'] : strtoupper(self::safeFilename((string) ($doc['filename'] ?? 'export'))),
            'community' => $community,
            'classification' => $t($note !== '' ? 'Diffusion restreinte · données sensibles' : 'Diffusion restreinte · usage interne'),
            'audience' => $t('Staff'),
            'generated_by' => (string) ($doc['generated_by'] ?? ''),
            'note' => $note !== '' ? $t($note) : '',
            'title' => $community . ' · ' . $title,
        ]);
        if ($pdf === null) {
            throw new \RuntimeException('TCPDF indisponible');
        }
        $pdf->AddPage();
        $pdf->writeAthenaHtml($this->buildHtml($doc));

        return $pdf->Output('', 'S');
    }
}
