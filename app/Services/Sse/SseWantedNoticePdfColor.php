<?php

declare(strict_types=1);

namespace App\Services\Sse;

use App\Support\Pdf\AthenaPdfTheme;
use App\Support\SseAlertLevel;
use App\Support\SseAlertReportTo;

/**
 * Mise en page d'une NOTICE DE COULEUR (gabarit de SseWantedNoticePdf) : un document de recherche reconnaissable d'un coup d'œil.
 * Cadre plein de la couleur de la notice sur tout le contour, recto et verso ; grand bandeau de titre (« NOTICE ROUGE », sous-titre du
 * type) ; bandeaux de classification, référence et pagination à l'intérieur du cadre ; portrait à gauche ; rubriques numérotées :
 * 1. Identité, 2. Signalement, 3. Faits et motifs, 4. Qualification et mesures demandées, 5. Conduite à tenir en cas de découverte,
 * 6. Mise en garde, 7. Dernier lieu connu, véhicules, personnes associées, 8. Validité et service à informer (QR code).
 *
 * Le type de notice est écrit en toutes lettres : la couleur ne porte jamais seule l'information. Aucun nom, sceau, emblème ni
 * contact réel : le service est celui du catalogue fictif, son emblème un cartouche générique dessiné ici.
 */
trait SseWantedNoticePdfColor
{
    /** Épaisseur (mm) du cadre de couleur, sur les quatre côtés de chaque page. */
    public const FRAME_MM = 7.0;

    /** Nombre de pages encadrées du dernier rendu (lu par les tests). */
    public static int $framedPages = 0;

    /** Clé de couleur du dernier rendu encadré, ou vide (lu par les tests). */
    public static string $framedColor = '';

    private const CL = 15.0;

    private const CW = 180.0;

    /** Libellés des rubriques : clé => [français, anglais]. */
    private const N = [
        'identity' => ['Identité', 'Identity'], 'description' => ['Signalement', 'Description'], 'facts' => ['Faits et motifs', 'Facts and grounds'],
        'measures' => ['Qualification et mesures demandées', 'Category and action requested'], 'conduct' => ['Conduite à tenir en cas de découverte', 'Action to take if located'],
        'caution' => ['Mise en garde', 'Caution'], 'known' => ['Dernier lieu connu, véhicules, personnes associées', 'Last known location, vehicles, associates'],
        'validity' => ['Validité et service à informer', 'Validity and service to report to'], 'control' => ['N° de contrôle', 'Control no.'],
        'vehicles' => ['Véhicules', 'Vehicles'], 'assoc' => ['Personnes associées', 'Associates'], 'last' => ['Dernier lieu connu', 'Last known location'],
        'report' => ['Service à informer', 'Report to'], 'channel' => ['Canal', 'Channel'], 'delay' => ['Délai', 'Deadline'], 'quote' => ['Référence à citer', 'Quote reference'],
        'online' => ['Signalement en ligne', 'Online report'], 'none' => ['Aucun connu', 'None known'], 'no_expiry' => ['Sans échéance', 'No expiry'],
        'flight' => ['RISQUE DE FUITE', 'FLIGHT RISK'], 'violent' => ['VIOLENT', 'VIOLENT'], 'caution_std' => ['PRUDENCE À L’APPROCHE', 'APPROACH WITH CARE'],
        'cont' => ['suite', 'continued'], 'name' => ['Nom, prénoms', 'Name, forenames'], 'perishable' => ['PÉRISSABLE', 'PERISHABLE'],
    ];

    private int $nSection = 0;

    /** @return array{0: string, 1: string} */
    private function n(string $key): array
    {
        return self::N[$key] ?? [$key, $key];
    }

    /**
     * @param array<string, mixed> $d
     * @return array{rgb: array{0: int, 1: int, 2: int}, ink: array{0: int, 1: int, 2: int}, title: array{0: string, 1: string}, subtitle: array{0: string, 1: string}, key: string}
     */
    private function color(array $d): array
    {
        $c = $d['notice_color'];

        return ['rgb' => $c['rgb'], 'ink' => $c['ink'], 'title' => $c['title'], 'subtitle' => $c['subtitle'], 'key' => (string) $c['key']];
    }

    /** Cadre, bandeaux de classification, référence et pagination : tout ce qui se répète sur chaque page. @param array<string, mixed> $d */
    private function colorChrome(array $d, int $page, int $pages): void
    {
        $pdf = $this->pdf;
        $c = $this->color($d);
        $t = self::FRAME_MM;
        $this->artFrame($c['rgb'], $t);
        ++self::$framedPages;
        self::$framedColor = $c['key'];
        // Type de notice en toutes lettres dans le cadre, haut et bas : lisible sans couleur.
        $pdf->SetTextColor(...$c['ink']);
        $pdf->SetFont($this->sans, 'B', 8.4);
        $pdf->setFontSpacing(1.1);
        $tag = $c['title'][$this->lang] . '  ·  ' . $c['subtitle'][$this->lang];
        foreach ([1.5, 297.0 - $t + 1.5] as $y) {
            $pdf->SetXY($t, $y);
            $pdf->Cell(210.0 - 2 * $t, 4.0, $tag, 0, 0, 'C');
        }
        $pdf->setFontSpacing(0);
        // Bandeaux de classification à l'intérieur du cadre.
        $cl = $d['classification'] ?? null;
        if (is_array($cl) && ($cl['banner'] ?? '') !== '') {
            $rgb = $cl['rgb'];
            $light = ($rgb[0] * 299 + $rgb[1] * 587 + $rgb[2] * 114) / 1000 > 140;
            $pdf->SetFillColor(...$rgb);
            $pdf->SetTextColor(...($light ? [0, 0, 0] : [255, 255, 255]));
            $pdf->SetFont($this->sans, 'B', 8.0);
            $pdf->setFontSpacing(0.4);
            foreach ([$t + 2.6, 297.0 - $t - 2.6 - 4.8] as $y) {
                $pdf->Rect($t + 2.6, $y, 210.0 - 2 * $t - 5.2, 4.8, 'F');
                $pdf->SetXY($t + 2.6, $y + 0.7);
                $pdf->Cell(210.0 - 2 * $t - 5.2, 3.4, (string) $cl['banner'], 0, 0, 'C');
            }
            $pdf->setFontSpacing(0);
        }
        // Pied dans le cadre : code-barres de la référence, référence, pagination, horodatage.
        $l = self::CL;
        $pdf->SetDrawColor(...self::INK);
        $pdf->SetLineWidth(0.3);
        $pdf->Line($l, 269.0, $l + self::CW, 269.0);
        $style = ['position' => '', 'border' => false, 'padding' => 0, 'fgcolor' => [0, 0, 0], 'bgcolor' => false, 'text' => false];
        $pdf->write1DBarcode((string) $d['ref'], 'C128', $l, 270.6, 44.0, 6.4, 0.4, $style, 'N');
        $pdf->SetFont($this->sans, '', 6.4);
        $pdf->SetTextColor(...self::MUTED);
        $pdf->SetXY($l, 277.3);
        $pdf->Cell(44.0, 2.8, (string) $d['ref'], 0, 0, 'L');
        $pdf->SetFont($this->sans, 'B', 7.4);
        $pdf->SetTextColor(...self::INK);
        $pdf->SetXY($l + 50.0, 272.0);
        $pdf->Cell(self::CW - 100.0, 3.6, $c['title'][$this->lang] . ' ' . $d['ref'], 0, 0, 'C');
        $pdf->SetXY($l + self::CW - 50.0, 270.8);
        $pdf->Cell(50.0, 3.6, $this->t('page')[$this->lang] . ' ' . $page . ' ' . ($this->lang === 1 ? 'of' : 'sur') . ' ' . $pages, 0, 0, 'R');
        $pdf->SetFont($this->sans, '', 6.4);
        $pdf->SetTextColor(...self::MUTED);
        $pdf->SetXY($l + self::CW - 50.0, 275.0);
        $pdf->Cell(50.0, 3.0, (string) $d['generated_at'], 0, 0, 'R');
        $pdf->SetLineWidth(0.25);
    }

    /** Titre de rubrique numéroté : « 3. FAITS ET MOTIFS » et son libellé dans l'autre langue. */
    private function section(string $key, float $x, float $y, float $w, array $d): void
    {
        $pdf = $this->pdf;
        $c = $this->color($d);
        ++$this->nSection;
        $title = $this->n($key);
        $pdf->SetFillColor(...$c['rgb']);
        $pdf->Rect($x, $y, 6.4, 5.2, 'F');
        $pdf->SetFont($this->sans, 'B', 9.4);
        $pdf->SetTextColor(...$c['ink']);
        $pdf->SetXY($x, $y + 0.5);
        $pdf->Cell(6.4, 4.2, (string) $this->nSection, 0, 0, 'C');
        $pdf->SetTextColor(...self::INK);
        [$primary, $ps] = $this->artFit(mb_strtoupper($title[$this->lang], 'UTF-8'), $w - 8.0, 9.4, 7.0);
        $pdf->SetFont($this->sans, 'B', $ps);
        $pw = $pdf->GetStringWidth($primary);
        $pdf->SetXY($x + 8.0, $y + 0.3);
        $pdf->Cell($pw + 1.0, 4.6, $primary, 0, 0, 'L');
        $room = $w - 8.0 - $pw - 4.0;
        $pdf->SetFont($this->sans, '', 7.2);
        if ($room > 12.0 && $pdf->GetStringWidth($title[1 - $this->lang]) <= $room) {
            $pdf->SetTextColor(...self::MUTED);
            $pdf->SetXY($x + 8.0 + $pw + 3.0, $y + 0.8);
            $pdf->Cell($room, 4.0, $title[1 - $this->lang], 0, 0, 'L');
        }
        $pdf->SetDrawColor(...self::INK);
        $pdf->SetLineWidth(0.3);
        $pdf->Line($x, $y + 5.2, $x + $w, $y + 5.2);
        $pdf->SetLineWidth(0.25);
    }

    /** Champ « libellé / valeur » compact (libellé au-dessus), retourne le bas du champ. */
    private function field(array $label, string $value, float $x, float $y, float $w, float $size = 9.4, float $maxH = 4.6): float
    {
        $pdf = $this->pdf;
        // Libellé dans les deux langues s'il tient dans la colonne, sinon dans la langue du document seule.
        $pdf->SetFont($this->sans, '', 6.0);
        $both = mb_strtoupper($label[$this->lang], 'UTF-8') . ' / ' . $label[1 - $this->lang];
        [$caption, $cs] = $pdf->GetStringWidth($both) <= $w - 2.0 ? [$both, 6.0] : $this->artFit(mb_strtoupper($label[$this->lang], 'UTF-8'), $w - 2.0, 6.0, 4.8, '');
        $pdf->SetFont($this->sans, '', $cs);
        $pdf->SetTextColor(...self::MUTED);
        $pdf->SetXY($x, $y);
        $pdf->Cell($w, 2.6, $caption, 0, 0, 'L');
        $value = trim($value) !== '' ? $value : '—';
        [$text, $s] = $this->fitOrCut($value, $w - 1.0, $maxH, $size, 6.8, 'B');
        $pdf->SetFont($this->sans, 'B', $s);
        $pdf->SetTextColor(...self::INK);
        $pdf->SetXY($x, $y + 2.8);
        $pdf->MultiCell($w - 1.0, $s * 0.43, $text, 0, 'L', false, 1, null, null, true, 0, false, true, $maxH + 0.4, 'T', true);

        return $y + 2.8 + min($maxH + 0.4, max(4.2, $pdf->getStringHeight($w - 1.0, $text))) + 1.2;
    }

    /**
     * Étiquettes de codes (« M-TRF-01 Trafic d'armes ») en ligne, avec retour ; retourne le bas.
     *
     * @param list<array{code: string, label: string}> $codes
     */
    private function chips(array $codes, float $x, float $y, float $w, int $maxRows = 3): float
    {
        $pdf = $this->pdf;
        if ($codes === []) {
            return $y;
        }
        $cx = $x;
        $rows = 1;
        foreach ($codes as $c) {
            $code = (string) $c['code'];
            $label = (string) $c['label'];
            $pdf->SetFont(AthenaPdfTheme::mono(), 'B', 7.2);
            $cw = $pdf->GetStringWidth($code) + 2.8;
            $pdf->SetFont($this->sans, 'B', 8.2);
            $lw = min($w - $cw, $pdf->GetStringWidth($label) + 3.4);
            if ($cx + $cw + $lw > $x + $w + 0.1) {
                if (++$rows > $maxRows) {
                    break;
                }
                $cx = $x;
                $y += 5.6;
            }
            $pdf->SetFillColor(...self::INK);
            $pdf->Rect($cx, $y, $cw, 4.7, 'F');
            $pdf->SetDrawColor(...self::INK);
            $pdf->SetLineWidth(0.3);
            $pdf->Rect($cx, $y, $cw + $lw, 4.7, 'D');
            $pdf->SetFont(AthenaPdfTheme::mono(), 'B', 7.2);
            $pdf->SetTextColor(255, 255, 255);
            $pdf->SetXY($cx, $y + 0.7);
            $pdf->Cell($cw, 3.3, $code, 0, 0, 'C');
            [$label, $ls] = $this->artFit($label, $lw - 2.6, 8.2, 6.4);
            $pdf->SetFont($this->sans, 'B', $ls);
            $pdf->SetTextColor(...self::INK);
            $pdf->SetXY($cx + $cw + 1.4, $y + 0.5);
            $pdf->Cell($lw - 2.0, 3.7, $label, 0, 0, 'L');
            $cx += $cw + $lw + 2.2;
        }
        $pdf->SetLineWidth(0.25);

        return $y + 6.0;
    }

    /** Texte libre dans une hauteur bornée (réduit puis coupé avec « … ») ; retourne [bas, coupé ?]. @return array{0: float, 1: bool} */
    private function para(string $text, float $x, float $y, float $w, float $maxH, float $size = 9.0): array
    {
        $pdf = $this->pdf;
        if (trim($text) === '' || $maxH < 3.4) {
            return [$y, trim($text) !== ''];
        }
        [$t, $s, $cut] = $this->fitOrCut($text, $w, $maxH, $size, 7.2, '');
        $pdf->SetFont($this->sans, '', $s);
        $pdf->SetTextColor(...self::INK);
        $pdf->SetXY($x, $y);
        $pdf->MultiCell($w, $s * 0.45, $t, 0, 'L', false, 1, null, null, true, 0, false, true, $maxH + 0.6, 'T', true);

        return [$y + min($maxH, $pdf->getStringHeight($w, $t)) + 1.4, $cut];
    }

    // ------------------------------------------------------------------ Recto

    /**
     * @param array<string, mixed> $d
     * @return array{reason: bool, instructions: bool, marks: bool}
     */
    private function colorFront(array $d): array
    {
        $pdf = $this->pdf;
        $c = $this->color($d);
        $l = self::CL;
        $w = self::CW;
        $this->nSection = 0;
        $report = $this->reportOf($d);

        // En-tête : emblème du service (cartouche générique), autorité, service ; numéro de contrôle et date d'émission.
        if ($d['emblem_path'] !== null && is_file((string) $d['emblem_path'])) {
            $this->emblem($d, $l, 17.4, 13.0, false);
        } else {
            $this->artServiceEmblem($report, $l, 17.4, 13.0);
        }
        [$auth, $as] = $this->artFit(mb_strtoupper((string) $d['authority'][$this->lang], 'UTF-8'), 104.0, 10.6, 6.8);
        $pdf->SetFont($this->sans, 'B', $as);
        $pdf->SetTextColor(...self::INK);
        $pdf->SetXY($l + 16.0, 18.4);
        $pdf->Cell(104.0, 5.0, $auth, 0, 0, 'L');
        $svc = SseAlertReportTo::label($report, $this->lang === 1 ? 'en' : 'fr');
        [$svc, $ss] = $this->artFit($svc !== '' ? $svc : (string) $d['authority'][1 - $this->lang], 104.0, 8.0, 6.0, '');
        $pdf->SetFont($this->sans, '', $ss);
        $pdf->SetTextColor(...self::MUTED);
        $pdf->SetXY($l + 16.0, 24.2);
        $pdf->Cell(104.0, 4.0, $svc, 0, 0, 'L');
        $bx = $l + $w - 54.0;
        $pdf->SetDrawColor(...self::INK);
        $pdf->SetLineWidth(0.3);
        $pdf->Rect($bx, 17.0, 54.0, 14.0, 'D');
        $pdf->SetFont($this->sans, '', 6.0);
        $pdf->SetTextColor(...self::MUTED);
        $pdf->SetXY($bx + 2.0, 17.7);
        $pdf->Cell(50.0, 2.6, mb_strtoupper($this->n('control')[$this->lang] . ' / ' . $this->n('control')[1 - $this->lang], 'UTF-8'), 0, 0, 'L');
        [$ref, $rs] = $this->artFit((string) $d['ref'], 50.0, 13.0, 8.0);
        $pdf->SetFont($this->sans, 'B', $rs);
        $pdf->SetTextColor(...self::INK);
        $pdf->SetXY($bx + 2.0, 20.4);
        $pdf->Cell(50.0, 5.6, $ref, 0, 0, 'L');
        $pdf->SetFont($this->sans, '', 7.0);
        $pdf->SetXY($bx + 2.0, 26.6);
        $pdf->Cell(50.0, 3.4, $this->t('issued')[$this->lang] . ' ' . $this->date((string) $d['issued_at']), 0, 0, 'L');

        // Bandeau de titre dans la couleur de la notice.
        $by = 34.0;
        $bh = 22.0;
        $t = self::FRAME_MM;
        $pdf->SetFillColor(...$c['rgb']);
        $pdf->Rect($t, $by, 210.0 - 2 * $t, $bh, 'F');
        $pdf->SetTextColor(...$c['ink']);
        $pdf->setFontSpacing(1.8);
        $pdf->SetFont($this->sans, 'B', 30.0);
        $pdf->SetXY($l, $by + 1.6);
        $pdf->Cell($w, 12.4, $c['title'][$this->lang], 0, 0, 'L');
        $titleW = $pdf->GetStringWidth($c['title'][$this->lang]);
        $pdf->setFontSpacing(0.5);
        [$sub, $subS] = $this->artFit($c['subtitle'][$this->lang], $w - 2.0, 12.6, 8.0);
        $pdf->SetFont($this->sans, 'B', $subS);
        $pdf->SetXY($l + 0.6, $by + 14.2);
        $pdf->Cell($w, 5.6, $sub, 0, 0, 'L');
        $pdf->setFontSpacing(0);
        $pdf->SetFont($this->sans, '', 7.6);
        $pdf->SetXY($l + 60.0, $by + 15.4);
        $pdf->Cell($w - 60.0, 4.0, $c['title'][1 - $this->lang] . ' — ' . $c['subtitle'][1 - $this->lang], 0, 0, 'R');
        // Cartouche d'alerte dans le bandeau : armé et dangereux.
        if ($d['armed'] === 'yes') {
            $warn = self::ARMED['yes'][$this->lang];
            $pdf->SetFont($this->sans, 'B', 12.0);
            $ww = $pdf->GetStringWidth($warn) + 17.0;
            $wx = max($l + $titleW + 8.0, $l + $w - $ww);
            if ($wx + $ww <= $l + $w + 0.1) {
                $pdf->SetFillColor(...$c['ink']);
                $pdf->Rect($wx, $by + 3.2, $ww, 9.4, 'F');
                $this->artPictogram($wx + 2.2, $by + 4.4, 7.0, 'triangle', $c['rgb'], $c['ink']);
                $pdf->SetTextColor(...$c['rgb']);
                $pdf->SetXY($wx + 11.0, $by + 5.2);
                $pdf->Cell($ww - 13.0, 5.4, $warn, 0, 0, 'L');
            }
        }

        // Portrait à gauche, 1. Identité et 2. Signalement à droite.
        $top = $by + $bh + 5.0;
        $pw = 44.0;
        $ph = 56.0;
        $this->portrait($d, $l, $top, $pw, $ph);
        $pdf->SetFont(AthenaPdfTheme::mono(), 'B', 7.0);
        $pdf->SetTextColor(...self::MUTED);
        $pdf->SetXY($l, $top + $ph + 0.8);
        $pdf->Cell($pw, 3.0, (string) $d['ref'], 0, 0, 'C');
        $x = $l + $pw + 5.0;
        $cw = $w - $pw - 5.0;
        $this->section('identity', $x, $top, $cw, $d);
        $y = $top + 6.8;
        [$last, $ls] = $this->artFit(mb_strtoupper((string) $d['last_name'], 'UTF-8') !== '' ? mb_strtoupper((string) $d['last_name'], 'UTF-8') : '—', $cw, 20.0, 10.0);
        $pdf->SetFont($this->sans, 'B', $ls);
        $pdf->SetTextColor(...self::INK);
        $pdf->SetXY($x, $y);
        $pdf->Cell($cw, 8.2, $last, 0, 0, 'L');
        [$first, $fs] = $this->artFit((string) $d['first_name'] !== '' ? (string) $d['first_name'] : '—', $cw, 13.0, 8.4);
        $pdf->SetFont($this->sans, 'B', $fs);
        $pdf->SetXY($x, $y + 8.0);
        $pdf->Cell($cw, 5.6, $first, 0, 0, 'L');
        $y += 14.6;
        $unknown = $this->t('unknown')[$this->lang];
        $col = $cw / 4;
        $sex = self::SEX[$d['sex']][$this->lang] ?? self::SEX['X'][$this->lang];
        $this->field($this->t('sex'), $sex, $x, $y, $col);
        $this->field($this->t('dob'), $d['birth_date'] !== null ? $this->date((string) $d['birth_date']) : $unknown, $x + $col, $y, $col);
        $this->field($this->t('pob'), (string) ($d['birth_place'] ?? '') !== '' ? (string) $d['birth_place'] : $unknown, $x + 2 * $col, $y, $col);
        $y = $this->field($this->t('nat'), $d['nationality'] !== '' ? (string) $d['nationality'] : $unknown, $x + 3 * $col, $y, $col);
        $aliases = (array) $d['aliases'];
        $y = $this->field($this->t('alias'), $aliases !== [] ? implode(' ; ', array_slice($aliases, 0, 4)) : '—', $x, $y, $cw, 9.4, 4.6);

        $this->section('description', $x, $y + 0.6, $cw, $d);
        $y += 7.4;
        $this->field($this->t('height'), $d['height_cm'] !== null ? $d['height_cm'] . ' cm' : $unknown, $x, $y, $col);
        $this->field($this->t('weight'), $d['weight_kg'] !== null ? $d['weight_kg'] . ' kg' : $unknown, $x + $col, $y, $col);
        $this->field($this->t('hair'), $d['hair'] !== '' ? (string) $d['hair'] : $unknown, $x + 2 * $col, $y, $col);
        $y = $this->field($this->t('eyes'), $d['eyes'] !== '' ? (string) $d['eyes'] : $unknown, $x + 3 * $col, $y, $col);
        $marksText = trim((string) $d['marks'] . ((string) ($d['physical_line'] ?? '') !== '' ? ((string) $d['marks'] !== '' ? ' — ' : '') . $d['physical_line'] : ''));
        $pdf->SetFont($this->sans, 'B', 8.6);
        $marksCut = $marksText !== '' && $pdf->getStringHeight($cw - 1.0, $marksText) > 10.4;
        $y = $this->field($this->t('marks'), $marksText, $x, $y, $cw, 8.6, 8.4);
        $y = max($y, $top + $ph + 5.0) + 1.0;

        // Rubriques 6 à 8 ancrées par le bas (elles ne débordent jamais) ; les deux textes libres se partagent ce qui reste.
        $bottom = 266.0;
        $reportH = 42.0;
        $y8 = $bottom - $reportH;
        $y7Max = $y8 - 19.4;
        $y6Max = $y7Max - 18.8;
        $measureRows = count((array) ($d['measures'] ?? [])) > 3 ? 2 : (($d['measures'] ?? []) !== [] ? 1 : 0);

        // 3. Faits et motifs.
        $this->section('facts', $l, $y, $w, $d);
        $y += 6.8;
        $y = $this->chips((array) ($d['reasons'] ?? []), $l, $y, $w, 2);
        $free = $y6Max - $y - (1.4 + 6.8 + 5.4 + $measureRows * 5.8 + 0.6) - (0.6 + 6.8) - 4.4;
        [$y, $reasonCut] = $this->para((string) $d['reason'], $l, $y, $w, max(4.0, $free * 0.62), 9.2);

        // 4. Qualification et mesures demandées.
        $y += 1.4;
        $this->section('measures', $l, $y, $w, $d);
        $y += 6.8;
        $pdf->SetFont($this->sans, 'B', 9.2);
        $pdf->SetTextColor(...self::INK);
        [$qual, $qs] = $this->artFit(mb_strtoupper($this->t('qualification')[$this->lang], 'UTF-8') . ' : ' . $d['type_label'], $w, 9.2, 7.0);
        $pdf->SetFont($this->sans, 'B', $qs);
        $pdf->SetXY($l, $y);
        $pdf->Cell($w, 4.4, $qual, 0, 0, 'L');
        $y += 5.4;
        $y = $this->chips((array) ($d['measures'] ?? []), $l, $y, $w, 2);

        // 5. Conduite à tenir en cas de découverte.
        $y += 0.6;
        $this->section('conduct', $l, $y, $w, $d);
        $y += 6.8;
        $instrRoom = $y6Max - 1.0 - $y - 1.4;
        if ($instrRoom < 3.6 && trim((string) $d['instructions']) !== '') {
            // Page pleine : la conduite à tenir est donnée en entier au verso.
            $instrCut = true;
            $pdf->SetFont($this->sans, 'I', 7.4);
            $pdf->SetTextColor(...self::MUTED);
            $pdf->SetXY($l + 100.0, $y - 6.0);
            $pdf->Cell($w - 100.0, 3.6, $this->lang === 1 ? 'see overleaf' : 'voir au verso', 0, 0, 'R');
            $y -= 1.0;
        } else {
            [$y, $instrCut] = $this->para((string) $d['instructions'], $l, $y, $w, $instrRoom, 9.2);
        }

        // 6. Mise en garde : cartouche hachuré au niveau d'alerte (rang, forme et libellé, jamais la seule couleur).
        $y = max(min($y + 1.0, $y6Max), $y - 0.4);
        $this->section('caution', $l, $y, $w, $d);
        $this->cautionBox($d, $l, $y + 6.6, $w, 9.8);
        $y += 18.8;

        // 7. Dernier lieu connu, véhicules, personnes associées.
        $this->section('known', $l, $y, $w, $d);
        $y += 6.8;
        $gap = 5.0;
        $colW = ($w - 2 * $gap) / 3;
        $seen = array_values(array_filter([(string) $d['last_seen_place'], $d['last_seen_at'] !== null ? $this->date((string) $d['last_seen_at']) : '', $d['zone'] !== '' ? $this->t('zone')[$this->lang] . ' : ' . $d['zone'] : ''], static fn (string $v): bool => trim($v) !== ''));
        $room = $y8 - 1.4 - $y;
        $ky = $y;
        foreach ([['last', $seen], ['vehicles', (array) ($d['vehicles'] ?? [])], ['assoc', (array) ($d['associates'] ?? [])]] as $k => [$key, $items]) {
            $ix = $l + $k * ($colW + $gap);
            $pdf->SetFont($this->sans, 'B', 6.4);
            $pdf->SetTextColor(...self::MUTED);
            $pdf->SetXY($ix, $y);
            $pdf->Cell($colW, 2.8, mb_strtoupper($this->n($key)[$this->lang], 'UTF-8') . ' / ' . $this->n($key)[1 - $this->lang], 0, 0, 'L');
            $iy = $y + 3.2;
            foreach ($items !== [] ? array_slice($items, 0, 3) : [$this->n('none')[$this->lang]] as $i => $item) {
                $left = $room - ($iy - $y);
                if ($left < 3.6) {
                    break;
                }
                [$item, $is] = $this->fitOrCut((string) $item, $colW, min(7.4, $left), 8.6, 7.0, $k === 0 && $i === 0 ? 'B' : '');
                $pdf->SetFont($this->sans, $k === 0 && $i === 0 ? 'B' : '', $is);
                $pdf->SetTextColor(...self::INK);
                $pdf->SetXY($ix, $iy);
                $pdf->MultiCell($colW, $is * 0.43, $item, 0, 'L', false, 1, null, null, true, 0, false, true, min(7.8, $left + 0.4), 'T', true);
                $iy += min(7.8, max(3.6, $pdf->getStringHeight($colW, $item))) + 0.5;
            }
            $ky = max($ky, $iy);
        }

        // 8. Validité et service à informer, ancré en bas de page.
        $this->section('validity', $l, $y8, $w, $d);
        $this->reportBlock($d, $report, $l, $y8 + 6.8, $w, $reportH - 7.6);

        return ['reason' => $reasonCut, 'instructions' => $instrCut, 'marks' => $marksCut];
    }

    /** @param array<string, mixed> $d @return array<string, mixed> service à informer (instantané), avec l'emblème téléversé s'il existe */
    private function reportOf(array $d): array
    {
        $r = is_array($d['report'] ?? null) ? $d['report'] : [];
        $read = SseAlertReportTo::read($r + [
            'fr' => (string) ($d['service']['name'] ?? '') !== '' ? (string) $d['service']['name'] : (string) $d['contact_label'],
            'ac' => (string) ($d['service']['ac'] ?? ''), 'channel' => (string) $d['contact_detail'],
            'auth_fr' => (string) $d['authority'][0], 'auth_en' => (string) $d['authority'][1],
        ]);

        return $read + ['emblem_bytes' => $r['emblem_bytes'] ?? null];
    }

    /**
     * Cartouche de mise en garde : bande hachurée, pictogramme, « ARMÉ ET DANGEREUX », « VIOLENT », « RISQUE DE FUITE », menace n/4.
     *
     * @param array<string, mixed> $d
     */
    private function cautionBox(array $d, float $x, float $y, float $w, float $h): void
    {
        $pdf = $this->pdf;
        $level = SseAlertLevel::of((string) $d['threat_level'], (string) $d['armed']);
        $rgb = $level['rgb'];
        $ink = $level['ink'];
        $black = [16, 18, 22];
        $this->artHatch($x, $y, 9.0, $h, $black, $rgb, 2.6);
        $this->artHatch($x + $w - 9.0, $y, 9.0, $h, $black, $rgb, 2.6);
        $pdf->SetFillColor(...$rgb);
        $pdf->Rect($x + 9.0, $y, $w - 18.0, $h, 'F');
        $pdf->SetDrawColor(...$black);
        $pdf->SetLineWidth(0.6);
        $pdf->Rect($x, $y, $w, $h, 'D');
        $this->artPictogram($x + 11.4, $y + 1.6, $h - 3.2, (string) $level['shape'], $ink, $rgb);
        $tags = [];
        if ($d['armed'] === 'yes') {
            $tags[] = self::ARMED['yes'][$this->lang];
        }
        if (in_array($d['threat_level'], ['high', 'critical'], true)) {
            $tags[] = $this->n('violent')[$this->lang];
        }
        if (!empty($d['flight_risk'])) {
            $tags[] = $this->n('flight')[$this->lang];
        }
        if ($tags === []) {
            $tags[] = $d['armed'] === 'unknown' ? mb_strtoupper(self::ARMED['unknown'][$this->lang], 'UTF-8') : $this->n('caution_std')[$this->lang];
        }
        $rx = $x + $w - 9.0 - 46.0;
        [$text, $ts] = $this->artFit(implode('  ·  ', $tags), $rx - ($x + 22.0) - 3.0, 14.0, 8.0);
        $pdf->SetFont($this->sans, 'B', $ts);
        $pdf->SetTextColor(...$ink);
        $pdf->SetXY($x + 22.0, $y + ($h - 6.4) / 2);
        $pdf->Cell($rx - ($x + 22.0) - 3.0, 6.4, $text, 0, 0, 'L');
        // Niveau de menace : libellé, rang et barres.
        $pdf->SetDrawColor(...$ink);
        $pdf->SetLineWidth(0.3);
        $pdf->Line($rx - 2.0, $y + 1.6, $rx - 2.0, $y + $h - 1.6);
        $pdf->SetFont($this->sans, 'B', 6.2);
        $pdf->SetXY($rx, $y + 1.2);
        $pdf->Cell(26.0, 2.8, mb_strtoupper($this->t('threat')[$this->lang], 'UTF-8'), 0, 0, 'L');
        $pdf->SetFont($this->sans, 'B', 10.4);
        $pdf->SetXY($rx, $y + 4.2);
        $pdf->Cell(27.0, 4.8, mb_strtoupper((string) $d['threat_label'], 'UTF-8'), 0, 0, 'L');
        $this->artBars($rx + 28.0, $y + 1.8, self::THREAT_BARS[$d['threat_level']] ?? 2, $ink, 3.2, 1.1, $h - 3.6);
        $pdf->SetLineWidth(0.25);
    }

    /**
     * Validité (émis le, valable jusqu'au) et service à informer : emblème, nom, canal, délai, référence à citer, QR code et adresse.
     *
     * @param array<string, mixed> $d
     * @param array<string, mixed> $r
     */
    private function reportBlock(array $d, array $r, float $x, float $y, float $w, float $h): void
    {
        $pdf = $this->pdf;
        $lang = $this->lang === 1 ? 'en' : 'fr';
        $url = (string) ($d['report_url'] ?? '');
        $qr = $url !== '' ? min(24.0, $h - 2.0) : 0.0;
        $pdf->SetDrawColor(...self::INK);
        $pdf->SetLineWidth(0.5);
        $pdf->Rect($x, $y, $w, $h, 'D');
        $pdf->SetLineWidth(0.25);
        // Colonne validité.
        $vw = 40.0;
        $fy = $this->field($this->t('issued'), $this->date((string) $d['issued_at']), $x + 2.4, $y + 1.8, $vw - 3.0, 9.0);
        $fy = $this->field($this->t('expires'), $d['expires_at'] !== null ? $this->date((string) $d['expires_at']) : $this->n('no_expiry')[$this->lang], $x + 2.4, $fy, $vw - 3.0, 9.0);
        if (SseAlertLevel::perishable($d['expires_at'] !== null ? (string) $d['expires_at'] : null)) {
            $this->artHatch($x + 2.4, $fy + 0.4, $vw - 5.0, 6.0, [16, 18, 22], [242, 194, 0], 2.4);
            $pdf->SetFillColor(255, 255, 255);
            $pdf->Rect($x + 3.8, $fy + 1.6, $vw - 7.8, 3.6, 'F');
            $pdf->SetFont($this->sans, 'B', 7.4);
            $pdf->SetTextColor(16, 18, 22);
            $pdf->SetXY($x + 3.8, $fy + 1.7);
            $pdf->Cell($vw - 7.8, 3.4, $this->n('perishable')[$this->lang], 0, 0, 'C');
        }
        $pdf->SetDrawColor(...self::INK);
        $pdf->Line($x + $vw, $y, $x + $vw, $y + $h);
        // Service à informer ; à droite, une colonne pour le QR code et l'adresse de signalement en clair.
        $qw = $qr > 0 ? 52.0 : 0.0;
        $sx = $x + $vw + 2.6;
        $sw = $w - $vw - 5.2 - ($qw > 0 ? $qw + 2.0 : 0.0);
        $pdf->SetFont($this->sans, 'B', 6.2);
        $pdf->SetTextColor(...self::MUTED);
        $pdf->SetXY($sx, $y + 1.6);
        $pdf->Cell($sw, 2.8, mb_strtoupper($this->n('report')[$this->lang], 'UTF-8') . ' / ' . $this->n('report')[1 - $this->lang], 0, 0, 'L');
        $this->artServiceEmblem($r, $sx, $y + 5.0, 9.4);
        $name = SseAlertReportTo::label($r, $lang);
        [$name, $ns, ] = $this->fitOrCut($name !== '' ? $name : '—', $sw - 12.4, 7.6, 9.6, 6.8, 'B');
        $pdf->SetFont($this->sans, 'B', $ns);
        $pdf->SetTextColor(...self::INK);
        $pdf->SetXY($sx + 11.8, $y + 4.6);
        $pdf->MultiCell($sw - 12.4, $ns * 0.42, $name, 0, 'L', false, 1, null, null, true, 0, false, true, 8.0, 'T', true);
        [$auth, $aus] = $this->artFit((string) $r[$lang === 'en' ? 'auth_en' : 'auth_fr'], $sw - 12.4, 6.6, 5.4, '');
        $pdf->SetFont($this->sans, '', $aus);
        $pdf->SetTextColor(...self::MUTED);
        $pdf->SetXY($sx + 11.8, $y + 12.2);
        $pdf->Cell($sw - 12.4, 2.8, $auth, 0, 0, 'L');
        $half = ($sw - 3.0) / 2;
        // Canal puis adresse choisie par la communauté (cases « afficher sur les PDF »), sur la même rubrique.
        $contact = trim((string) $r['channel']);
        $addr = trim(preg_replace('/\s*[\r\n]+\s*/', ' · ', (string) ($r['address'] ?? '')) ?? '');
        if ($addr !== '') {
            $contact = $contact !== '' ? $contact . ' · ' . $addr : $addr;
        }
        $this->field($this->n('channel'), $contact, $sx, $y + 16.0, $sw, 8.4, 7.4);
        $rowY = $y + $h - 8.6;
        $this->field($this->n('delay'), mb_strtoupper(SseAlertReportTo::delayLabel((string) $r['delay'], $lang), 'UTF-8'), $sx, $rowY, $half, 9.0);
        $this->field($this->n('quote'), (string) $d['ref'], $sx + $half + 3.0, $rowY, $half, 9.0);
        if ($qr > 0) {
            $cx = $x + $w - $qw - 1.4;
            $pdf->SetDrawColor(...self::LINE);
            $pdf->Line($cx - 1.0, $y + 2.0, $cx - 1.0, $y + $h - 2.0);
            [$online, $os] = $this->artFit(mb_strtoupper($this->n('online')[$this->lang], 'UTF-8'), $qw - $qr - 3.4, 6.2, 4.4);
            $pdf->SetFont($this->sans, 'B', $os);
            $pdf->SetTextColor(...self::MUTED);
            $pdf->SetXY($cx + 1.0, $y + 1.6);
            $pdf->Cell($qw - $qr - 3.4, 2.8, $online, 0, 0, 'L');
            $this->artQr($url, $x + $w - $qr - 2.2, $y + 1.8, $qr);
            $lines = preg_match('~^(https?://[^/]+)(/.*/)([^/]+)$~', $url, $m) === 1 ? [$m[1], $m[2], $m[3]] : [$url];
            // Adresse en clair à gauche du QR code : domaine, chemin, jeton (une ligne chacun).
            $ly = $y + 6.6;
            $lw = $qw - $qr - 3.4;
            foreach ($lines as $k => $line) {
                [$line, $us] = $this->artFit($line, $lw, 6.0, 3.6, $k === 2 ? 'B' : '', AthenaPdfTheme::mono());
                $pdf->SetFont(AthenaPdfTheme::mono(), $k === 2 ? 'B' : '', $us);
                $pdf->SetTextColor(...self::INK);
                $pdf->SetXY($cx + 1.0, $ly);
                $pdf->Cell($lw, 2.8, $line, 0, 0, 'L');
                $ly += 3.6;
            }
        }
    }

    // ------------------------------------------------------------------ Verso

    /**
     * @param array<string, mixed> $d
     * @param array{reason: bool, instructions: bool, marks: bool} $truncated
     */
    private function colorBack(array $d, array $truncated, int $pages): void
    {
        $pdf = $this->pdf;
        $c = $this->color($d);
        $l = self::CL;
        $w = self::CW;
        $bottom = 265.0;
        $this->colorChrome($d, 2, $pages);
        $t = self::FRAME_MM;
        $pdf->SetFillColor(...$c['rgb']);
        $pdf->Rect($t, 16.6, 210.0 - 2 * $t, 11.0, 'F');
        $pdf->SetTextColor(...$c['ink']);
        $pdf->setFontSpacing(1.0);
        $pdf->SetFont($this->sans, 'B', 13.0);
        $pdf->SetXY($l, 19.0);
        $pdf->Cell(110.0, 6.4, $c['title'][$this->lang] . ' · ' . $this->n('cont')[$this->lang], 0, 0, 'L');
        $pdf->setFontSpacing(0);
        $pdf->SetFont($this->sans, 'B', 11.0);
        $pdf->SetXY($l + 100.0, 19.4);
        $pdf->Cell($w - 100.0, 6.0, (string) $d['ref'], 0, 0, 'R');
        [$title, $ts] = $this->artFit(mb_strtoupper((string) $d['last_name'], 'UTF-8') . ' ' . $d['first_name'], $w, 11.0, 7.4);
        $pdf->SetFont($this->sans, 'B', $ts);
        $pdf->SetTextColor(...self::INK);
        $pdf->SetXY($l, 30.4);
        $pdf->Cell($w, 5.0, $title, 0, 0, 'L');
        $y = 38.0;
        $block = function (string $key, string $text, float $maxH) use (&$y, $l, $w, $d): void {
            if (trim($text) === '') {
                return;
            }
            $this->plainHeading($this->n($key), $l, $y, $w, $d);
            [$y] = $this->para($text, $l, $y + 6.6, $w, $maxH, 9.2);
            $y += 3.0;
        };
        $block('facts', self::factsText($d), 60.0);
        $block('conduct', self::actionText($d), 34.0);
        if (($d['physical_rows'] ?? []) !== []) {
            $block('description', implode("\n", array_map(static fn (array $r): string => $r['label'] . ' : ' . $r['value'], $d['physical_rows'])), 34.0);
        }
        $others = [];
        if (count($d['aliases']) > 4) {
            $others[] = $this->t('alias')[$this->lang] . ' : ' . implode(' ; ', $d['aliases']);
        }
        if ($truncated['marks'] || mb_strlen((string) $d['marks'], 'UTF-8') > 140) {
            $others[] = $this->t('marks')[$this->lang] . ' : ' . $d['marks'];
        }
        if ($others !== []) {
            $block('identity', implode("\n", $others), 26.0);
        }
        $thumbs = $d['attached']['thumbs'] ?? [];
        if (($d['extra_photos'] !== [] || $thumbs !== []) && $y + 50.0 <= $bottom) {
            $this->plainHeading($this->t('photos'), $l, $y, $w, $d);
            $y += 7.2;
            $x = $l;
            foreach (array_slice($d['extra_photos'], 0, 3) as $photo) {
                $this->drawPhoto($photo, $x, $y, 28.0, 36.0);
                $x += 31.0;
            }
            foreach (array_slice($thumbs, 0, 3) as $thumb) {
                $this->drawPhoto($thumb['bytes'], $x, $y, 28.0, 31.0);
                $pdf->SetFont($this->sans, '', 6.2);
                $pdf->SetTextColor(...self::MUTED);
                $pdf->SetXY($x, $y + 32.0);
                $pdf->MultiCell(28.0, 2.8, (string) $thumb['caption'], 0, 'C', false, 1, null, null, true, 0, false, true, 5.6, 'T', true);
                $x += 31.0;
            }
            $y += 41.0;
        }
        if ($d['signature'] !== null && $y + 30.0 <= $bottom) {
            $this->plainHeading($this->t('signature'), $l, $y, 90.0, $d);
            $y += 7.2;
            $pdf->SetDrawColor(...self::LINE);
            $pdf->SetFillColor(...self::PAPER);
            $pdf->Rect($l, $y, 90.0, 22.0, 'DF');
            $this->drawPhoto((string) $d['signature'], $l + 4.0, $y + 2.0, 82.0, 18.0);
            $y += 26.0;
        }
        if ($y + 26.0 <= $bottom) {
            $this->plainHeading($this->t('general'), $l, $y, $w, $d);
            $y += 7.0;
            foreach ([self::GENERAL[$this->lang], self::GENERAL[1 - $this->lang]] as $k => $para) {
                $pdf->SetFont($this->sans, $k === 0 ? '' : 'I', 8.2);
                $pdf->SetTextColor(...($k === 0 ? self::INK : self::MUTED));
                $pdf->SetXY($l, $y);
                $pdf->MultiCell($w, 3.8, $para, 0, 'L', false, 1, null, null, true, 0, false, true, 0, 'T', false);
                $y = $pdf->GetY() + 2.0;
            }
        }
    }

    /** Titre de rubrique du verso (sans numéro : le recto porte la numérotation). @param array{0: string, 1: string} $title @param array<string, mixed> $d */
    private function plainHeading(array $title, float $x, float $y, float $w, array $d): void
    {
        $pdf = $this->pdf;
        $c = $this->color($d);
        $pdf->SetFillColor(...$c['rgb']);
        $pdf->Rect($x, $y + 0.2, 1.8, 4.6, 'F');
        $pdf->SetFont($this->sans, 'B', 9.4);
        $pdf->SetTextColor(...self::INK);
        $primary = mb_strtoupper($title[$this->lang], 'UTF-8');
        $pw = $pdf->GetStringWidth($primary);
        $pdf->SetXY($x + 3.6, $y);
        $pdf->Cell($pw + 1.0, 5.0, $primary, 0, 0, 'L');
        $pdf->SetFont($this->sans, '', 7.2);
        $pdf->SetTextColor(...self::MUTED);
        $pdf->SetXY($x + 3.6 + $pw + 3.0, $y + 0.5);
        $pdf->Cell(max(10.0, $w - $pw - 8.0), 4.2, $title[1 - $this->lang], 0, 0, 'L');
        $pdf->SetDrawColor(...self::INK);
        $pdf->SetLineWidth(0.3);
        $pdf->Line($x, $y + 5.2, $x + $w, $y + 5.2);
        $pdf->SetLineWidth(0.25);
    }
}
