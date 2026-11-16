<?php

declare(strict_types=1);

namespace App\Services\Sse;

use App\Support\SseRedactionExplain as X;
use App\Support\SseUsClassification;

/**
 * Explication du caviardage dans le PDF d'une fiche (trait de SseSheetPdf) : un bloc « Certains passages ont été masqués / Portions
 * withheld » sous le titre (nombre de passages, catégorie de chacun avec le niveau qui la rend lisible, niveau de diffusion du
 * document, marche à suivre), une lettre de repère à côté de chaque barre noire et la légende en bas de chaque page.
 *
 * Le bloc ne contient que des catégories, des niveaux et des nombres : jamais la valeur masquée ni sa longueur (la largeur des
 * barres reste fixe). Sans passage masqué, rien n'est dessiné. Le décompte vient du dessin lui-même : une première passe compte les
 * barres réellement dessinées, la seconde (la seule gardée) réserve le bloc et pose les lettres.
 *
 * @property \TCPDF $pdf
 */
trait SseSheetPdfRedaction
{
    /** Décompte des barres dessinées par catégorie ('' : catégorie inconnue, texte d'un ancien document). @var array<string, int> */
    private array $rxCounts = [];

    /** Lettres de repère connues (seconde passe) : catégorie => lettre. @var array<string, string>|null */
    private ?array $rxLetters = null;

    /** Décompte de la première passe : sert au bloc et à la légende de la seconde. @var array{total: int, by: array<string, int>} */
    private array $rxKnown = ['total' => 0, 'by' => []];

    /** Mesure en cours (hauteur d'un texte) : rien n'est compté. */
    private int $rxMeasuring = 0;

    /** Textes du bloc : clé => [français, anglais]. */
    private const RX = [
        'title' => ['CERTAINS PASSAGES ONT ÉTÉ MASQUÉS', 'PORTIONS WITHHELD'],
        'one' => ['1 passage masqué', '1 portion withheld'],
        'many' => ['%d passages masqués', '%d portions withheld'],
        'level' => ['Diffusion : %s.', 'Distribution: %s.'],
        'needs' => ['requiert %s', 'requires %s'],
        'why' => ['Le masquage dépend du niveau de diffusion du document et de la catégorie de chaque donnée, pas de la classification de la personne.', 'Withholding depends on the distribution level of the document and on the category of each item, not on the classification of the person.'],
        'how' => ['Pour obtenir ces passages : demander la communication à un niveau supérieur auprès du service émetteur, réf. %s.', 'To obtain these portions: request release at a higher level from the issuing service, ref. %s.'],
        'legend' => ['Passages masqués', 'Portions withheld'],
        'other' => ['catégorie non conservée', 'category not kept'],
    ];

    /** @return array{total: int, by: array<string, int>} */
    private function rxResult(): array
    {
        $by = array_filter($this->rxCounts, static fn (int $n): bool => $n > 0);

        return ['total' => array_sum($by), 'by' => $by];
    }

    /** @param array<string, string>|null $letters */
    private function rxStart(?array $letters): void
    {
        $this->rxKnown = $this->rxResult();
        $this->rxCounts = [];
        $this->rxMeasuring = 0;
        $this->rxLetters = $letters;
    }

    /** Compte une barre dessinée et rend sa lettre de repère (vide pendant la première passe). */
    private function rxMark(string $run): string
    {
        $cat = X::categoryOfRun($run);
        if ($this->rxMeasuring === 0) {
            $this->rxCounts[$cat] = ($this->rxCounts[$cat] ?? 0) + 1;
        }

        return $this->rxLetters === null ? '' : ($this->rxLetters[$cat] ?? '?');
    }

    private function rxText(string $key): string
    {
        return self::RX[$key][$this->lang];
    }

    /** « 3 passages masqués ». */
    private function rxCount(int $n): string
    {
        return $n === 1 ? $this->rxText('one') : sprintf($this->rxText('many'), $n);
    }

    /** Bloc d'explication sous le titre : rien si aucun passage n'est masqué. */
    private function rxBlock(): void
    {
        if ($this->rxLetters === null) {
            return;
        }
        $res = $this->rxKnown;
        if ($res['total'] < 1) {
            return;
        }
        $pdf = $this->pdf;
        $d = $this->d;
        $x = self::MARGIN;
        $w = self::CONTENT_W;
        $pad = 3.0;
        $iw = $w - 2 * $pad;
        $lang = $this->lang === 1 ? 'en' : 'fr';
        $level = SseUsClassification::label($this->level(), $lang);
        $colon = $this->lang === 1 ? ': ' : ' : ';
        $items = [];
        foreach ($this->rxLetters as $cat => $letter) {
            $n = (int) ($res['by'][$cat] ?? 0);
            if ($n < 1) {
                continue;
            }
            $need = $cat === '' ? $this->rxText('other') : sprintf($this->rxText('needs'), X::need($cat, is_array($d['visibility'] ?? null) ? $d['visibility'] : null));
            $items[] = '(' . $letter . ') ' . X::label($cat, $lang) . $colon . $need . ' (' . $n . ')';
        }
        $how = sprintf($this->rxText('how'), (string) $d['ref']);
        $lines = [
            [sprintf($this->rxText('level'), $level) . ' ' . implode(' · ', $items), 'B', 7.8],
            [$this->rxText('why') . ' ' . $how, '', 7.3],
        ];
        $pdf->SetFont($this->sans, '', 8.0);
        $h = 6.9;
        foreach ($lines as [$text, $style, $size]) {
            $pdf->SetFont($this->sans, $style, $size);
            $h += $pdf->getStringHeight($iw, $text) + 0.7;
        }
        $h += 0.6;
        if (!$this->ensure($h + 2.0)) {
            return;
        }
        $y = $this->y;
        $pdf->SetDrawColor(0, 0, 0);
        $pdf->SetLineWidth(0.45);
        $pdf->SetFillColor(...self::PAPER);
        $pdf->Rect($x, $y, $w, $h, 'DF');
        $pdf->SetLineWidth(0.25);
        // Titre : une barre noire en pictogramme, puis le titre dans les deux langues.
        $pdf->SetFillColor(0, 0, 0);
        $pdf->Rect($x + $pad, $y + 2.1, 7.0, 3.2, 'F');
        $pdf->SetFont($this->sans, 'B', 8.6);
        $pdf->SetTextColor(...self::INK);
        $pdf->SetXY($x + $pad + 9.0, $y + 1.6);
        $pdf->Cell($iw - 9.0, 4.4, self::RX['title'][$this->lang] . ' / ' . self::RX['title'][1 - $this->lang] . '  ·  ' . $this->rxCount($res['total']), 0, 0, 'L');
        $cy = $y + 6.3;
        foreach ($lines as [$text, $style, $size]) {
            $pdf->SetFont($this->face($text), $style, $size);
            $pdf->SetTextColor(...self::INK);
            $pdf->SetXY($x + $pad, $cy);
            $pdf->MultiCell($iw, 3.6, $text, 0, 'L', false, 1, null, null, true, 0, false, true, 0, 'T', false);
            $cy += $pdf->getStringHeight($iw, $text) + 0.7;
        }
        $this->y = $y + $h + 3.0;
    }

    /** Légende en bas de page : « Passages masqués : (a) Identité · (b) Biométrie ». */
    private function rxLegend(): void
    {
        if ($this->rxLetters === null) {
            return;
        }
        $res = $this->rxKnown;
        $parts = [];
        foreach ($this->rxLetters as $cat => $letter) {
            if (($res['by'][$cat] ?? 0) > 0) {
                $parts[] = '(' . $letter . ') ' . X::label($cat, $this->lang === 1 ? 'en' : 'fr');
            }
        }
        if ($parts === []) {
            return;
        }
        // Colonne centrale du pied de page, sous la mention de diffusion : une ligne, réduite pour tenir.
        $pdf = $this->pdf;
        $text = mb_strtoupper($this->rxText('legend'), 'UTF-8') . ' : ' . implode('  ·  ', $parts);
        $size = 6.6;
        $pdf->SetFont($this->sans, 'B', $size);
        while ($size > 5.0 && $pdf->GetStringWidth($text) > 88.0) {
            $size -= 0.2;
            $pdf->SetFont($this->sans, 'B', $size);
        }
        $pdf->SetTextColor(...self::INK);
        $pdf->SetXY(58.0, 282.6);
        $pdf->Cell(92.0, 3.0, $text, 0, 0, 'C');
    }

    /** Lettre de repère dessinée à droite d'une barre noire (aucune pendant la première passe). */
    private function rxLetter(string $letter, float $x, float $y, float $size): float
    {
        if ($letter === '') {
            return 0.0;
        }
        $pdf = $this->pdf;
        $pdf->SetFont($this->sans, 'B', max(6.4, $size * 0.74));
        $pdf->SetTextColor(...self::INK);
        $pdf->SetXY($x, $y - 0.4);
        $w = $pdf->GetStringWidth($letter) + 1.0;
        $pdf->Cell($w, 3.0, $letter, 0, 0, 'L');

        return $w;
    }
}
