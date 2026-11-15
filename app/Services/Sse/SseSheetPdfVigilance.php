<?php

declare(strict_types=1);

namespace App\Services\Sse;

use App\Support\Pdf\AthenaPdfTheme;
use App\Support\SseAlertLevel;
use App\Support\SseAlertReportTo;
use App\Support\SseSheetText;
use App\Support\SseUsClassification;

/**
 * Mise en page de la FICHE DE VIGILANCE (gabarit de SseSheetPdf) : un avertissement lisible en trois secondes sur le terrain.
 * Même grammaire que les autres fiches (bandeaux de classification, en-tête du service, numéro de contrôle, titres numérotés avec
 * marquage de portion, pied à code-barres), mais la page s'ouvre sur un BLOC D'ALERTE dominant : bandes hachurées, pictogramme,
 * niveau en très grand, rang « n/4 » en barres, consigne dominante. Le niveau ne dépend jamais de la seule couleur.
 *
 * Page 1 : alerte, sujet, conduite à tenir, à ne pas faire, service à informer (canal, délai, QR code et adresse de signalement),
 * motif, dernier lieu connu, véhicules, personnes associées, validité. Page 2 (annexe, seulement s'il y a de quoi) : comportement,
 * commentaires, adresses, téléphones.
 */
trait SseSheetPdfVigilance
{
    /** Libellés bilingues du bloc d'alerte : clé => [français, anglais]. */
    private const V = [
        'level' => ['Niveau', 'Level'], 'subject' => ['Sujet', 'Subject'], 'conduct' => ['Conduite à tenir', 'Action to take'],
        'dont' => ['À ne pas faire', 'Do not'], 'reason' => ['Motif de la vigilance', 'Reason for vigilance'],
        'report' => ['Service à informer', 'Report to'], 'channel' => ['Canal', 'Channel'], 'delay' => ['Délai', 'Deadline'],
        'quote' => ['Référence à citer', 'Quote reference'], 'online' => ['Signalement en ligne', 'Online report'],
        'photo_na' => ['PHOTO INDISPONIBLE', 'PHOTO UNAVAILABLE'], 'perishable' => ['PÉRISSABLE', 'PERISHABLE'],
        'issued_by' => ['Émetteur', 'Issued by'], 'assoc' => ['Personnes associées', 'Associates'], 'last' => ['Dernier lieu connu', 'Last known location'],
        'lifted' => ['LEVÉE', 'LIFTED'], 'annex' => ['Annexe', 'Annex'], 'issued' => ['Émise le', 'Issued'], 'valid' => ['Valable jusqu’au', 'Valid until'],
        'born' => ['Né(e) le', 'Born'], 'marks' => ['Signes particuliers', 'Distinguishing marks'], 'alias' => ['Alias', 'Aliases'],
        'none_known' => ['Aucun connu', 'None known'], 'behaviour' => ['Comportement observé', 'Observed behaviour'], 'protected' => ['Identité protégée à ce niveau de diffusion', 'Identity protected at this distribution level'],
    ];

    /** Le motif complet et le bloc « dernier lieu connu, véhicules, personnes associées » passent en annexe quand la page 1 est pleine. */
    private bool $vReasonInAnnex = false;

    private bool $vKnownInAnnex = false;

    /** Le dernier lieu connu a trouvé sa place sous la conduite à tenir ; les adresses ont pris sa colonne. */
    private bool $vLastDone = false;

    private bool $vAddressesDone = false;

    private bool $vPhonesDone = false;

    /** @return array{0: string, 1: string} */
    private function v(string $key): array
    {
        return self::V[$key] ?? [$key, $key];
    }

    /** @return array<string, mixed> bloc d'alerte de la fiche (niveau déduit des faits pour un document émis avant ce gabarit) */
    private function alert(): array
    {
        $f = $this->facts();
        $level = SseAlertLevel::of((string) ($f['threat'] ?? ''), (string) ($f['armed'] ?? ''));
        $a = is_array($this->d['alert'] ?? null) ? $this->d['alert'] : [];
        $measures = array_values(array_filter(is_array($a['measures'] ?? null) ? $a['measures'] : [], 'is_array'));

        return [
            'level' => $level, 'order' => SseAlertLevel::order(array_column($measures, 'code')), 'measures' => $measures,
            'reasons' => array_values(array_filter(is_array($a['reasons'] ?? null) ? $a['reasons'] : [], 'is_array')),
            'donts' => array_values(array_map('strval', (array) ($a['donts'][$this->lang] ?? []))),
            'report' => SseAlertReportTo::read($a['report'] ?? []) + ['emblem_bytes' => $a['report']['emblem_bytes'] ?? null],
            'has_report' => is_array($a['report'] ?? null) && $a['report'] !== [],
            'url' => (string) ($a['url'] ?? ''), 'perishable' => !empty($a['perishable']), 'lifted_at' => $a['lifted_at'] ?? null,
        ];
    }

    // ------------------------------------------------------------ Page de tête

    private function vigilanceFront(): void
    {
        $d = $this->d;
        $pdf = $this->pdf;
        $l = self::MARGIN;
        $a = $this->alert();
        $this->band(true);
        // Service émetteur : emblème de la communauté, emblème du service à informer, sinon pictogramme neutre.
        if ($a['has_report'] && ($d['emblem_path'] ?? null) === null) {
            $this->artServiceEmblem($a['report'], $l, 9.6, 13.0);
        } else {
            $this->emblem($l, 9.6, 13.0);
        }
        [$name, $size] = $this->artFit(mb_strtoupper((string) $d['authority'][$this->lang], 'UTF-8'), 116.0, 11.0, 6.8);
        $pdf->SetFont($this->sans, 'B', $size);
        $pdf->SetTextColor(...self::INK);
        $pdf->SetXY($l + 16.0, 11.6);
        $pdf->Cell(116.0, 5.0, $name, 0, 0, 'L');
        [$sub, $subSize] = $this->artFit($d['service'] !== '' ? $this->t('issuer')[$this->lang] . ' : ' . $d['service'] : (string) $d['authority'][1 - $this->lang], 116.0, 7.8, 6.2, '');
        $pdf->SetFont($this->sans, '', $subSize);
        $pdf->SetTextColor(...self::MUTED);
        $pdf->SetXY($l + 16.0, 17.2);
        $pdf->Cell(116.0, 4.0, $sub, 0, 0, 'L');
        // Numéro de contrôle et groupe date-heure (même cartouche que les autres fiches).
        $x = 148.0;
        $pdf->SetDrawColor(...self::INK);
        $pdf->SetLineWidth(0.3);
        $pdf->Rect($x, 9.6, 50.0, 14.0, 'D');
        $pdf->SetFont($this->sans, '', 6.2);
        $pdf->SetTextColor(...self::MUTED);
        $pdf->SetXY($x + 2.0, 10.4);
        $pdf->Cell(46.0, 2.8, mb_strtoupper($this->t('control_no')[$this->lang] . ' / ' . $this->t('control_no')[1 - $this->lang], 'UTF-8'), 0, 0, 'L');
        $pdf->SetFont($this->sans, 'B', 10.5);
        $pdf->SetTextColor(...self::INK);
        $pdf->SetXY($x + 2.0, 13.4);
        $pdf->Cell(46.0, 5.0, (string) $d['ref'], 0, 0, 'L');
        $pdf->SetFont($this->sans, '', 7.2);
        $pdf->SetXY($x + 2.0, 19.0);
        $pdf->Cell(46.0, 3.6, 'DTG ' . $this->dtg(), 0, 0, 'L');
        // Titre du document et diffusion, sur deux lignes serrées : la place est pour l'alerte.
        $pdf->SetLineWidth(0.6);
        $pdf->Line($l, 26.0, $l + self::CONTENT_W, 26.0);
        $pdf->SetFont($this->sans, 'B', 12.5);
        $pdf->SetTextColor(...self::INK);
        $title = mb_strtoupper((string) $d['title'][$this->lang], 'UTF-8');
        $titleW = $pdf->GetStringWidth($title);
        $pdf->SetXY($l, 27.4);
        $pdf->Cell($titleW + 1.0, 5.4, $title, 0, 0, 'L');
        $pdf->SetFont($this->sans, '', 7.6);
        $pdf->SetTextColor(...self::MUTED);
        $pdf->SetXY($l + $titleW + 3.0, 28.6);
        $pdf->Cell(60.0, 4.0, mb_strtoupper((string) $d['title'][1 - $this->lang], 'UTF-8'), 0, 0, 'L');
        $dist = mb_strtoupper($this->t('dist')[$this->lang], 'UTF-8') . ' : ' . implode(' / ', SseUsClassification::dissemination($this->level())) . ' · ' . $d['classification'][$this->lang]
            . ' · ' . ($this->lang === 1 ? 'Cleared personnel, SSE field distribution' : 'Personnel habilité, diffusion SSE de terrain');
        [$dist, $distSize] = $this->artFit($dist, self::CONTENT_W, 7.4, 6.0, 'B', AthenaPdfTheme::mono());
        $pdf->SetFont(AthenaPdfTheme::mono(), 'B', $distSize);
        $pdf->SetTextColor(...self::INK);
        $pdf->SetXY($l, 33.4);
        $pdf->Cell(self::CONTENT_W, 3.6, $dist, 0, 0, 'L');
        $pdf->SetLineWidth(0.25);
        $this->y = 39.0;
    }

    // ------------------------------------------------------------------ Corps

    private function vigilance(): void
    {
        $a = $this->alert();
        $this->vReasonInAnnex = false;
        $this->vKnownInAnnex = false;
        $this->vLastDone = false;
        $this->vAddressesDone = false;
        $this->vPhonesDone = false;
        $this->alertBlock($a);
        $this->subjectBlock();
        $this->conductAndReport($a);
        $this->reasonBlock($a);
        $this->knownRow(254.0);
        $this->annex();
        $this->validityStrip($a);
    }

    /** @param array<string, mixed> $a */
    private function alertBlock(array $a): void
    {
        $pdf = $this->pdf;
        $l = self::MARGIN;
        $w = self::CONTENT_W;
        $y0 = $this->y;
        $level = $a['level'];
        $rgb = $level['rgb'];
        $ink = $level['ink'];
        $hatch = 3.4;
        $solidH = 33.0;
        $black = [16, 18, 22];
        // Bandes hachurées haut et bas (noir et couleur du niveau), aplat de la couleur entre les deux.
        $this->artHatch($l, $y0, $w, $hatch, $black, $rgb, 3.4);
        $pdf->SetFillColor(...$rgb);
        $pdf->Rect($l, $y0 + $hatch, $w, $solidH, 'F');
        $this->artHatch($l, $y0 + $hatch + $solidH, $w, $hatch, $black, $rgb, 3.4);
        $pdf->SetDrawColor(...$black);
        $pdf->SetLineWidth(0.7);
        $pdf->Rect($l, $y0, $w, $hatch * 2 + $solidH, 'D');
        $s = $y0 + $hatch;
        // Pictogramme : forme du niveau, dessinée dans l'encre du bloc.
        $this->artPictogram($l + 6.0, $s + 4.5, 24.0, (string) $level['shape'], $ink, $rgb);
        // Rang : « NIVEAU 3/4 » et barres (lisibles sans couleur).
        $rx = $l + $w - 33.0;
        $pdf->SetDrawColor(...$ink);
        $pdf->SetLineWidth(0.35);
        $pdf->Line($rx - 3.0, $s + 4.0, $rx - 3.0, $s + $solidH - 4.0);
        $pdf->SetTextColor(...$ink);
        $pdf->SetFont($this->sans, 'B', 7.0);
        $pdf->SetXY($rx, $s + 3.6);
        $pdf->Cell(30.0, 3.2, mb_strtoupper($this->v('level')[$this->lang] . ' / ' . $this->v('level')[1 - $this->lang], 'UTF-8'), 0, 0, 'L');
        $pdf->SetFont($this->sans, 'B', 25.0);
        $pdf->SetXY($rx - 0.6, $s + 6.6);
        $pdf->Cell(30.0, 10.4, $level['rank'] . '/4', 0, 0, 'L');
        $this->artBars($rx, $s + 18.6, (int) $level['rank'], $ink, 5.6, 1.8, 10.4);
        // Titre du niveau, en très grand. « DANGER — ARMÉ ET DANGEREUX » : le mot d'alerte seul sur la première ligne.
        $tx = $l + 36.0;
        $tw = $rx - 6.0 - $tx;
        $title = (string) $level['title'][$this->lang];
        $other = (string) $level['title'][1 - $this->lang];
        $order = $a['order'] !== '' ? SseAlertLevel::ORDERS[$a['order']][$this->lang] : '';
        $parts = explode(' — ', $title, 2);
        $pdf->SetTextColor(...$ink);
        if (count($parts) === 2) {
            [$t1, $s1] = $this->artFit($parts[0], $tw, 37.0, 18.0);
            $pdf->SetFont($this->sans, 'B', $s1);
            $pdf->SetXY($tx, $s + 1.6);
            $pdf->Cell($tw, 13.6, $t1, 0, 0, 'L');
            [$t2, $s2] = $this->artFit($parts[1], $tw, 21.0, 11.0);
            $pdf->SetFont($this->sans, 'B', $s2);
            $pdf->SetXY($tx, $s + 14.6);
            $pdf->Cell($tw, 8.0, $t2, 0, 0, 'L');
            $after = $s + 23.2;
        } else {
            [$t1, $s1] = $this->artFit($title, $tw, 31.0, 13.0);
            $pdf->SetFont($this->sans, 'B', $s1);
            $pdf->SetXY($tx, $s + ($order !== '' ? 3.4 : 7.0));
            $pdf->Cell($tw, 12.0, $t1, 0, 0, 'L');
            $pdf->SetFont($this->sans, 'B', 9.0);
            $pdf->SetXY($tx + 0.4, $s + ($order !== '' ? 15.4 : 19.4));
            $pdf->Cell($tw, 4.4, $other, 0, 0, 'L');
            $after = $s + ($order !== '' ? 21.6 : 99.0);
        }
        // Consigne dominante : cartouche inversé (encre du bloc en fond, couleur du niveau en texte).
        if ($order !== '') {
            [$o, $os] = $this->artFit($order, $tw - 6.0, 12.5, 8.0);
            $pdf->SetFont($this->sans, 'B', $os);
            $ow = $pdf->GetStringWidth($o) + 6.0;
            $pdf->SetFillColor(...$ink);
            $pdf->Rect($tx, $after, $ow, 7.0, 'F');
            $pdf->SetTextColor(...$rgb);
            $pdf->SetXY($tx, $after + 0.9);
            $pdf->Cell($ow, 5.2, $o, 0, 0, 'C');
        }
        // Fiche levée depuis son émission (re-téléchargement) : tampon en travers du bloc.
        if (is_string($a['lifted_at']) && $a['lifted_at'] !== '') {
            $stamp = $this->v('lifted')[$this->lang] . ' · ' . $this->date($a['lifted_at']);
            $pdf->StartTransform();
            $pdf->Rotate(7.0, $l + $w / 2, $s + $solidH / 2);
            $pdf->SetFont($this->sans, 'B', 24.0);
            $sw = $pdf->GetStringWidth(mb_strtoupper($stamp, 'UTF-8')) + 12.0;
            $pdf->SetFillColor(255, 255, 255);
            $pdf->SetDrawColor(...$black);
            $pdf->SetLineWidth(1.2);
            $pdf->Rect($l + ($w - $sw) / 2, $s + $solidH / 2 - 8.0, $sw, 16.0, 'DF');
            $pdf->SetTextColor(...$black);
            $pdf->SetXY($l + ($w - $sw) / 2, $s + $solidH / 2 - 5.4);
            $pdf->Cell($sw, 10.8, mb_strtoupper($stamp, 'UTF-8'), 0, 0, 'C');
            $pdf->StopTransform();
        }
        $pdf->SetLineWidth(0.25);
        $this->y = $y0 + $hatch * 2 + $solidH + 4.0;
    }

    /** Sujet : portrait (ou cadre « photo indisponible »), nom en grand, alias, signalement sur une ligne, signes particuliers. */
    private function subjectBlock(): void
    {
        $pdf = $this->pdf;
        $f = $this->facts();
        $l = self::MARGIN;
        $top = $this->y;
        $pw = 29.0;
        $ph = 36.0;
        if ($this->hasPhoto()) {
            $this->portrait($l, $top, $pw, $ph);
        } else {
            $pdf->SetFillColor(...self::PAPER);
            $pdf->SetDrawColor(...self::INK);
            $pdf->SetLineWidth(0.3);
            $pdf->Rect($l, $top, $pw, $ph, 'DF');
            $pdf->SetFillColor(206, 211, 217);
            $pdf->Circle($l + $pw / 2, $top + $ph * 0.36, $pw * 0.2, 0, 360, 'F');
            $pdf->StartTransform();
            $pdf->Rect($l + 0.3, $top + 0.3, $pw - 0.6, $ph - 7.6, 'CNZ');
            $pdf->Ellipse($l + $pw / 2, $top + $ph * 0.86, $pw * 0.36, $ph * 0.26, 0, 0, 360, 'F');
            $pdf->StopTransform();
            $pdf->SetFillColor(...self::INK);
            $pdf->Rect($l, $top + $ph - 7.0, $pw, 7.0, 'F');
            $pdf->SetFont($this->sans, 'B', 5.8);
            $pdf->SetTextColor(255, 255, 255);
            $pdf->SetXY($l, $top + $ph - 6.4);
            $pdf->Cell($pw, 2.9, $this->v('photo_na')[$this->lang], 0, 0, 'C');
            $pdf->SetFont($this->sans, '', 5.2);
            $pdf->SetXY($l, $top + $ph - 3.5);
            $pdf->Cell($pw, 2.6, $this->v('photo_na')[1 - $this->lang], 0, 0, 'C');
        }
        $x = $l + $pw + 5.0;
        $w = self::CONTENT_W - $pw - 5.0;
        $pdf->SetFont($this->sans, '', 6.2);
        $pdf->SetTextColor(...self::MUTED);
        $pdf->SetXY($x, $top);
        $pdf->Cell($w, 2.8, mb_strtoupper($this->v('subject')[$this->lang], 'UTF-8') . ' / ' . $this->v('subject')[1 - $this->lang], 0, 0, 'L');
        // Nom : NOM Prénom, réduit pour tenir sur la ligne (jamais coupé au milieu sans « … »).
        $last = (string) $f['last_name'];
        $first = (string) $f['first_name'];
        $y = $top + 3.2;
        $twoLineName = false;
        if (str_contains($last . $first, self::BLOCK)) {
            $pdf->SetFillColor(0, 0, 0);
            $pdf->Rect($x, $y + 1.4, 78.0, 7.4, 'F');
            // Lettre de repère de la barre (légende « Passages masqués » en pied de page).
            $run = self::BLOCK; // une suite d'un bloc = identité
            $lw = $this->rxLetter($this->rxMark($run), $x + 78.6, $y + 3.6, 12.0);
            $pdf->SetFont($this->sans, 'I', 7.4);
            $pdf->SetTextColor(...self::MUTED);
            $pdf->SetXY($x + 81.0 + $lw, $y + 3.4);
            $pdf->Cell($w - 81.0 - $lw, 3.6, $this->v('protected')[$this->lang], 0, 0, 'L');
        } else {
            $full = trim(mb_strtoupper($last, 'UTF-8') . ' ' . $first);
            $full = $full !== '' ? $full : '—';
            $pdf->SetFont($this->face($full), 'B', 15.0);
            $pdf->SetTextColor(...self::INK);
            if ($pdf->GetStringWidth($full) <= $w || $first === '' || $last === '') {
                [$name, $size] = $this->artFit($full, $w, 23.0, 10.5, 'B', $this->face($full));
                $pdf->SetFont($this->face($full), 'B', $size);
                $pdf->SetXY($x, $y);
                $pdf->Cell($w, 10.0, $name, 0, 0, 'L');
            } else {
                // Nom très long : le nom de famille sur une ligne, les prénoms dessous (la ligne de naissance cède sa place).
                $twoLineName = true;
                [$n1, $s1] = $this->artFit(mb_strtoupper($last, 'UTF-8'), $w, 17.0, 9.0, 'B', $this->face($last));
                $pdf->SetFont($this->face($last), 'B', $s1);
                $pdf->SetXY($x, $y - 0.4);
                $pdf->Cell($w, 7.4, $n1, 0, 0, 'L');
                [$n2, $s2] = $this->artFit($first, $w, 13.0, 8.5, 'B', $this->face($first));
                $pdf->SetFont($this->face($first), 'B', $s2);
                $pdf->SetXY($x, $y + 6.8);
                $pdf->Cell($w, 6.0, $n2, 0, 0, 'L');
                $y += 3.6;
            }
        }
        $y += 11.0;
        $aliases = implode(' ; ', array_map('strval', (array) $f['aliases']));
        $this->labelled($this->v('alias'), $aliases !== '' ? $aliases : '—', $x, $y, $w, 10.4, 'B');
        $y += 6.2;
        $sex = self::SEX[$f['sex']] ?? self::SEX['X'];
        $sig = array_values(array_filter([
            $sex[$this->lang], $f['age_estimated'] !== null ? $f['age_estimated'] . ($this->lang === 1 ? ' yrs' : ' ans') : '',
            $f['height_cm'] !== null ? $f['height_cm'] . ' cm' : '', $f['weight_kg'] !== null ? $f['weight_kg'] . ' kg' : '', (string) $f['build'],
            $f['hair'] !== '' ? ($this->lang === 1 ? 'hair ' : 'cheveux ') . mb_strtolower((string) $f['hair'], 'UTF-8') : '',
            $f['eyes'] !== '' ? ($this->lang === 1 ? 'eyes ' : 'yeux ') . mb_strtolower((string) $f['eyes'], 'UTF-8') : '',
        ], static fn (string $v): bool => trim($v) !== ''));
        $this->labelled($this->t('physical_line'), implode(' · ', $sig), $x, $y, $w, 9.8, 'B');
        $y += 6.2;
        $born = array_values(array_filter([$this->dateOrRaw((string) $f['birth_date']), (string) $f['birth_place'], (string) $f['nationality']], static fn (string $v): bool => trim($v) !== ''));
        if (!$twoLineName) {
            $this->labelled($this->v('born'), $born !== [] ? implode(' · ', $born) : '—', $x, $y, $w, 9.0, '');
            $y += 6.0;
        } else {
            $y += 0.6;
        }
        // Signes particuliers : deux lignes au plus.
        $marks = trim((string) $f['marks']);
        $pdf->SetFont($this->sans, 'B', 7.0);
        $pdf->SetTextColor(...self::MUTED);
        $label = mb_strtoupper($this->v('marks')[$this->lang], 'UTF-8') . ' :';
        $lw = $pdf->GetStringWidth($label) + 1.6;
        $pdf->SetXY($x, $y + 0.5);
        $pdf->Cell($lw, 3.6, $label, 0, 0, 'L');
        $text = $this->plain($marks !== '' ? $marks : '—');
        [$text, $ms] = $this->shrink($text, $w - $lw, 9.0, 7.2);
        $pdf->SetFont($this->face($text), '', $ms);
        $pdf->SetTextColor(...self::INK);
        $pdf->SetXY($x + $lw, $y);
        $pdf->MultiCell($w - $lw, 3.9, $text, 0, 'L', false, 1, null, null, true, 0, false, true, 8.2, 'T', true);
        $this->y = $top + $ph + 4.0;
    }

    /**
     * « LIBELLÉ : valeur » sur une ligne ; une valeur caviardée devient un rectangle noir.
     *
     * @param array{0: string, 1: string} $label
     */
    private function labelled(array $label, string $value, float $x, float $y, float $w, float $size, string $style): void
    {
        $pdf = $this->pdf;
        $pdf->SetFont($this->sans, 'B', 7.0);
        $pdf->SetTextColor(...self::MUTED);
        $text = mb_strtoupper($label[$this->lang], 'UTF-8') . ' :';
        $lw = $pdf->GetStringWidth($text) + 1.6;
        $pdf->SetXY($x, $y + 0.8);
        $pdf->Cell($lw, 3.6, $text, 0, 0, 'L');
        if (str_contains($value, self::BLOCK)) {
            $this->barLine($value, $x + $lw, $y, $w - $lw, $size);

            return;
        }
        [$v, $s] = $this->artFit($value, $w - $lw, $size, 7.0, $style, $this->face($value));
        $pdf->SetFont($this->face($value), $style, $s);
        $pdf->SetTextColor(...self::INK);
        $pdf->SetXY($x + $lw, $y);
        $pdf->Cell($w - $lw, 5.0, $v, 0, 0, 'L');
    }

    /**
     * Deux colonnes : conduite à tenir numérotée et « à ne pas faire » à gauche, service à informer (canal, délai, référence, QR) à droite.
     *
     * @param array<string, mixed> $a
     */
    private function conductAndReport(array $a): void
    {
        $pdf = $this->pdf;
        $l = self::MARGIN;
        $top = $this->y;
        $leftW = 112.0;
        $gap = 5.0;
        $rightX = $l + $leftW + $gap;
        $rightW = self::CONTENT_W - $leftW - $gap;

        // ---- Conduite à tenir
        $this->heading($this->v('conduct'), $l, $leftW);
        $y = $top + 7.4;
        $lines = [];
        foreach ($a['measures'] as $m) {
            $lines[] = [(string) ($m[$this->lang === 1 ? 'en' : 'fr'] ?? ''), (string) ($m['code'] ?? '')];
        }
        foreach (preg_split('/\R/u', SseSheetText::plain((string) ($this->d['texts']['instructions'] ?? ''))) ?: [] as $free) {
            $free = trim((string) preg_replace('/^[\-•–]\s*/u', '', trim($free)));
            if ($free !== '') {
                $lines[] = [$free, ''];
            }
        }
        $lines = array_slice($lines, 0, 7);
        if ($lines === []) {
            $lines[] = [$this->lang === 1 ? 'Observe and report to the service shown.' : 'Observer et rendre compte au service indiqué.', ''];
        }
        foreach ($lines as $i => [$text, $code]) {
            $text = $this->plain($text);
            $textW = $leftW - 9.0 - ($code !== '' ? 14.0 : 0.0);
            // Une ligne tant que le texte tient à 9 points ; au-delà, deux lignes.
            $pdf->SetFont($this->face($text), 'B', 9.0);
            $twoLines = $pdf->GetStringWidth($text) > $textW;
            $oneSize = 10.6;
            for (; $oneSize > 9.0; $oneSize -= 0.2) {
                $pdf->SetFont($this->face($text), 'B', $oneSize);
                if ($pdf->GetStringWidth($text) <= $textW) {
                    break;
                }
            }
            $rowH = $twoLines ? 9.6 : 6.6;
            // Pastille du numéro.
            $pdf->SetFillColor(...self::INK);
            $pdf->Circle($l + 3.0, $y + 3.1, 2.9, 0, 360, 'F');
            $pdf->SetFont($this->sans, 'B', 9.4);
            $pdf->SetTextColor(255, 255, 255);
            $pdf->SetXY($l, $y + 1.05);
            $pdf->Cell(6.0, 4.1, (string) ($i + 1), 0, 0, 'C');
            $pdf->SetTextColor(...self::INK);
            if ($twoLines) {
                [$text, $fs] = $this->shrink($text, $textW, 10.0, 8.0);
                $pdf->SetFont($this->face($text), 'B', $fs);
                $pdf->SetXY($l + 8.0, $y + 0.2);
                $pdf->MultiCell($textW, 4.3, $text, 0, 'L', false, 1, null, null, true, 0, false, true, 9.0, 'T', true);
            } else {
                $pdf->SetFont($this->face($text), 'B', $oneSize);
                $pdf->SetXY($l + 8.0, $y + 0.6);
                $pdf->Cell($textW, 5.0, $text, 0, 0, 'L');
            }
            if ($code !== '') {
                $pdf->SetFont(AthenaPdfTheme::mono(), 'B', 7.0);
                $pdf->SetTextColor(...self::MUTED);
                $pdf->SetXY($l + $leftW - 14.0, $y + 1.4);
                $pdf->Cell(14.0, 3.6, $code, 0, 0, 'R');
            }
            $pdf->SetDrawColor(...self::LINE);
            $pdf->SetLineWidth(0.15);
            $pdf->Line($l + 8.0, $y + $rowH, $l + $leftW, $y + $rowH);
            $y += $rowH + 0.4;
        }
        // ---- À ne pas faire : cartouche à bord épais, croix dessinées.
        if ($a['donts'] !== []) {
            $y += 2.0;
            $boxH = 6.4 + count($a['donts']) * 5.2 + 1.4;
            $pdf->SetDrawColor(...self::INK);
            $pdf->SetLineWidth(0.6);
            $pdf->Rect($l, $y, $leftW, $boxH, 'D');
            $pdf->SetFillColor(...self::INK);
            $pdf->Rect($l, $y, $leftW, 5.6, 'F');
            $pdf->SetFont($this->sans, 'B', 8.6);
            $pdf->SetTextColor(255, 255, 255);
            $pdf->SetXY($l + 2.4, $y + 0.9);
            $pdf->Cell($leftW - 4.0, 3.8, mb_strtoupper($this->v('dont')[$this->lang], 'UTF-8') . '  /  ' . mb_strtoupper($this->v('dont')[1 - $this->lang], 'UTF-8'), 0, 0, 'L');
            $iy = $y + 6.6;
            foreach ($a['donts'] as $dont) {
                $pdf->SetDrawColor(...self::RED);
                $pdf->SetLineWidth(0.9);
                $pdf->Line($l + 2.6, $iy + 0.9, $l + 5.4, $iy + 3.7);
                $pdf->Line($l + 5.4, $iy + 0.9, $l + 2.6, $iy + 3.7);
                [$dt, $ds] = $this->artFit($dont, $leftW - 10.0, 10.0, 7.4);
                $pdf->SetFont($this->sans, 'B', $ds);
                $pdf->SetTextColor(...self::INK);
                $pdf->SetXY($l + 8.0, $iy);
                $pdf->Cell($leftW - 10.0, 4.6, $dt, 0, 0, 'L');
                $iy += 5.2;
            }
            $y += $boxH;
        }
        $leftBottom = $y;

        // ---- Service à informer
        $saved = $this->y;
        $this->y = $top;
        $this->heading($this->v('report'), $rightX, $rightW);
        $this->y = $saved;
        $r = $a['report'];
        $by = $top + 7.4;
        $boxTop = $by;
        $this->artServiceEmblem($r, $rightX + 2.4, $by + 2.6, 12.0);
        $name = SseAlertReportTo::label($r, $this->lang === 1 ? 'en' : 'fr');
        $name = $name !== '' ? $name : (string) $this->d['service'];
        [$name, $ns] = $this->shrink($this->plain($name), $rightW - 19.0, 9.6, 7.2);
        $pdf->SetFont($this->face($name), 'B', $ns);
        $pdf->SetTextColor(...self::INK);
        $pdf->SetXY($rightX + 16.8, $by + 2.4);
        $pdf->MultiCell($rightW - 19.0, 4.0, $name, 0, 'L', false, 1, null, null, true, 0, false, true, 8.4, 'T', true);
        $authority = (string) $r[$this->lang === 1 ? 'auth_en' : 'auth_fr'];
        if ($authority !== '') {
            [$authority, $as] = $this->artFit($authority, $rightW - 19.0, 6.8, 5.6, '');
            $pdf->SetFont($this->sans, '', $as);
            $pdf->SetTextColor(...self::MUTED);
            $pdf->SetXY($rightX + 16.8, $by + 11.2);
            $pdf->Cell($rightW - 19.0, 3.0, $authority, 0, 0, 'L');
        }
        $by += 17.0;
        $row = function (array $label, string $value, float $size, string $style, float $maxH) use ($pdf, $rightX, $rightW, &$by): void {
            $pdf->SetFont($this->sans, 'B', 6.2);
            $pdf->SetTextColor(...self::MUTED);
            $pdf->SetXY($rightX + 2.4, $by);
            $pdf->Cell($rightW - 4.8, 2.8, mb_strtoupper($label[$this->lang], 'UTF-8') . ' / ' . $label[1 - $this->lang], 0, 0, 'L');
            [$value, $size] = $maxH > 5.0 ? $this->shrink($value, $rightW - 4.8, $size, 6.8) : $this->artFit($value, $rightW - 4.8, $size, 6.8, $style, $this->face($value));
            $pdf->SetFont($this->face($value), $style, $size);
            $pdf->SetTextColor(...self::INK);
            $pdf->SetXY($rightX + 2.4, $by + 3.0);
            $pdf->MultiCell($rightW - 4.8, 3.8, $value, 0, 'L', false, 1, null, null, true, 0, false, true, $maxH, 'T', true);
            $by += 3.4 + min($maxH, max(4.2, $pdf->getStringHeight($rightW - 4.8, $value))) + 0.8;
        };
        $row($this->v('channel'), $this->plain((string) $r['channel']), 8.8, 'B', 8.0);
        $row($this->v('delay'), mb_strtoupper(SseAlertReportTo::delayLabel((string) $r['delay'], $this->lang === 1 ? 'en' : 'fr'), 'UTF-8'), 10.4, 'B', 4.6);
        $row($this->v('quote'), (string) $this->d['ref'], 10.4, 'B', 4.6);
        if (trim((string) $r['address']) !== '') {
            $pdf->SetFont($this->sans, '', 7.0);
            $pdf->SetTextColor(...self::MUTED);
            $pdf->SetXY($rightX + 2.4, $by);
            $pdf->MultiCell($rightW - 4.8, 3.1, $this->plain((string) $r['address']), 0, 'L', false, 1, null, null, true, 0, false, true, 9.4, 'T', true);
            $by += min(9.4, $pdf->getStringHeight($rightW - 4.8, $this->plain((string) $r['address']))) + 1.0;
        }
        // QR code et adresse de signalement en clair.
        if ($a['url'] !== '') {
            $pdf->SetDrawColor(...self::LINE);
            $pdf->SetLineWidth(0.2);
            $pdf->Line($rightX + 2.4, $by + 0.4, $rightX + $rightW - 2.4, $by + 0.4);
            $by += 1.8;
            $qr = 21.0;
            $this->artQr((string) $a['url'], $rightX + $rightW - $qr - 2.4, $by, $qr);
            $pdf->SetFont($this->sans, 'B', 6.2);
            $pdf->SetTextColor(...self::MUTED);
            $pdf->SetXY($rightX + 2.4, $by);
            $pdf->Cell($rightW - $qr - 7.0, 2.8, mb_strtoupper($this->v('online')[$this->lang], 'UTF-8'), 0, 0, 'L');
            // Adresse en clair, coupée aux « / » : domaine, chemin, jeton (jamais au milieu d'un mot).
            $ly = $by + 3.8;
            foreach (self::urlLines((string) $a['url']) as $k => $part) {
                [$part, $us] = $this->artFit($part, $rightW - $qr - 7.0, 6.6, 4.6, $k === 2 ? 'B' : '', AthenaPdfTheme::mono());
                $pdf->SetFont(AthenaPdfTheme::mono(), $k === 2 ? 'B' : '', $us);
                $pdf->SetTextColor(...self::INK);
                $pdf->SetXY($rightX + 2.4, $ly);
                $pdf->Cell($rightW - $qr - 7.0, 3.2, $part, 0, 0, 'L');
                $ly += 3.5;
            }
            $by += $qr + 1.6;
        }
        $pdf->SetDrawColor(...self::INK);
        $pdf->SetLineWidth(0.5);
        $pdf->Rect($rightX, $boxTop, $rightW, $by - $boxTop, 'D');
        $pdf->SetLineWidth(0.25);
        // Dernier lieu connu : sous la conduite à tenir quand la colonne a de la place à côté du service à informer.
        if ($by - $leftBottom >= 21.0) {
            $this->y = $leftBottom + 4.0;
            $this->heading($this->v('last'), $l, $leftW);
            $leftBottom = $this->shortList($this->lastKnown(), $l, $this->y + 6.8, $leftW, $by - $leftBottom - 10.0, true);
            $this->vLastDone = true;
        }
        $this->y = max($leftBottom, $by) + 4.0;
    }

    /**
     * Adresse de signalement en trois lignes lisibles : domaine, chemin, jeton.
     *
     * @return list<string>
     */
    private static function urlLines(string $url): array
    {
        if (preg_match('~^(https?://[^/]+)(/.*/)([^/]+)$~', $url, $m) === 1) {
            return [$m[1], $m[2], $m[3]];
        }

        return array_values(array_filter(str_split($url, 30), static fn (string $p): bool => $p !== ''));
    }

    /**
     * Motif : motifs codifiés en étiquettes (code et libellé), puis le texte.
     *
     * @param array<string, mixed> $a
     */
    private function reasonBlock(array $a): void
    {
        $pdf = $this->pdf;
        $l = self::MARGIN;
        $text = trim((string) ($this->d['texts']['reason'] ?? ''));
        if ($a['reasons'] === [] && $text === '') {
            return;
        }
        $this->heading($this->v('reason'));
        $y = $this->y + 7.2;
        $x = $l;
        foreach ($a['reasons'] as $r) {
            $code = (string) ($r['code'] ?? '');
            $label = $this->plain((string) ($r[$this->lang === 1 ? 'en' : 'fr'] ?? ''));
            $pdf->SetFont(AthenaPdfTheme::mono(), 'B', 7.4);
            $cw = $pdf->GetStringWidth($code) + 3.0;
            $pdf->SetFont($this->sans, 'B', 8.6);
            $lw = min(90.0, $pdf->GetStringWidth($label) + 3.6);
            if ($x + $cw + $lw > $l + self::CONTENT_W) {
                $x = $l;
                $y += 6.0;
            }
            $pdf->SetFillColor(...self::INK);
            $pdf->Rect($x, $y, $cw, 4.9, 'F');
            $pdf->SetDrawColor(...self::INK);
            $pdf->SetLineWidth(0.3);
            $pdf->Rect($x, $y, $cw + $lw, 4.9, 'D');
            $pdf->SetFont(AthenaPdfTheme::mono(), 'B', 7.4);
            $pdf->SetTextColor(255, 255, 255);
            $pdf->SetXY($x, $y + 0.7);
            $pdf->Cell($cw, 3.5, $code, 0, 0, 'C');
            [$label, $ls] = $this->artFit($label, $lw - 3.0, 8.6, 6.6);
            $pdf->SetFont($this->face($label), 'B', $ls);
            $pdf->SetTextColor(...self::INK);
            $pdf->SetXY($x + $cw + 1.6, $y + 0.5);
            $pdf->Cell($lw - 2.0, 3.9, $label, 0, 0, 'L');
            $x += $cw + $lw + 2.4;
        }
        if ($a['reasons'] !== []) {
            $y += 6.4;
        }
        if ($text !== '') {
            $html = SseSheetText::toHtml($text);
            $w = self::CONTENT_W;
            $size = 9.4;
            // La place de la première page va au motif ; le bloc des véhicules et des personnes garde la sienne (24 mm) quand il
            // reste au moins deux lignes pour le motif, sinon il passe en annexe.
            $h = $this->htmlHeight($html, $w, $size);
            $avail = 254.0 - $y;
            $room = $avail - 24.0 >= min($h, 9.0) ? $avail - 24.0 : $avail - 1.0;
            $room = max(5.0, min(40.0, $room));
            for ($s = $size; $h > $room && $s >= 7.4; $s -= 0.4) {
                $size = $s;
                $h = $this->htmlHeight($html, $w, $s);
            }
            $pdf->SetFont($this->sans, '', $size);
            $pdf->SetTextColor(...self::INK);
            if ($h > $room) {
                // Trop long pour la première page : coupé à une ligne entière, le texte complet passe en annexe.
                $this->vReasonInAnnex = true;
                $lineH = $size * 0.3528 * 1.28;
                $clip = max($lineH, floor(($room - 4.0) / $lineH) * $lineH) + 0.5;
                $pdf->StartTransform();
                $pdf->Rect($l, $y, $w, $clip, 'CNZ');
                $pdf->writeHTMLCell($w, 0, $l, $y, $this->wrapHtml($html, $size), 0, 1, false, true, 'L', true);
                $pdf->StopTransform();
                $pdf->SetFont($this->sans, 'BI', 7.2);
                $pdf->SetTextColor(...self::MUTED);
                $pdf->SetXY($l, $y + $clip + 0.2);
                $pdf->Cell($w, 3.2, $this->lang === 1 ? '… continued in the annex, page 2' : '… suite en annexe, page 2', 0, 0, 'R');
                $h = $clip + 3.4;
            } else {
                $pdf->writeHTMLCell($w, 0, $l, $y, $this->wrapHtml($html, $size), 0, 1, false, true, 'L', true);
            }
            $y += $h + 1.0;
        }
        $this->y = $y + 2.6;
    }

    /** Dernier lieu connu et zone, véhicules, personnes associées : trois colonnes de libellés courts. */
    private function knownRow(float $limit): void
    {
        $pdf = $this->pdf;
        $f = $this->facts();
        $l = self::MARGIN;
        $gap = 5.0;
        $colW = (self::CONTENT_W - 2 * $gap) / 3;
        $top = $this->y;
        $room = $limit - $top - 7.2;
        if ($room < 13.0) {
            // Plus de place sur la première page : le bloc passe en annexe (rien n'est perdu).
            $this->vKnownInAnnex = true;

            return;
        }
        // Le dernier lieu connu est déjà sous la conduite à tenir : sa colonne revient aux adresses.
        $cols = $this->vLastDone
            ? [[$this->t('vehicles'), array_values(array_map('strval', (array) $f['links']['vehicle'])), false], [$this->v('assoc'), array_values(array_map('strval', (array) $f['links']['associate'])), false], [$this->t('addresses'), array_values(array_map('strval', (array) $f['links']['address'])), false]]
            : [[$this->v('last'), $this->lastKnown(), true], [$this->t('vehicles'), array_values(array_map('strval', (array) $f['links']['vehicle'])), false], [$this->v('assoc'), array_values(array_map('strval', (array) $f['links']['associate'])), false]];
        $this->vAddressesDone = $this->vLastDone;
        $bottom = $top;
        foreach ($cols as $k => [$title, $items, $lead]) {
            $x = $l + $k * ($colW + $gap);
            $this->y = $top;
            $this->heading($title, $x, $colW);
            $bottom = max($bottom, $this->shortList($items, $x, $top + 6.8, $colW, $room, $lead));
        }
        $y = $bottom + 1.2;
        // Adresses (quand elles n'ont pas eu de colonne) puis téléphones et courriels : une ligne chacun, si elle tient en entier.
        $phones = array_values(array_map('strval', array_merge((array) $f['links']['phone'], (array) $f['links']['email'])));
        foreach ([['addresses', array_values(array_map('strval', (array) $f['links']['address'])), $this->vAddressesDone], ['phones', $phones, false]] as [$key, $items, $done]) {
            if ($done || $items === [] || $y + 5.6 > $limit) {
                continue;
            }
            $line = implode(' ; ', $items);
            $pdf->SetFont($this->face($line), '', 7.6);
            if (str_contains($line, self::BLOCK) || $pdf->GetStringWidth($line) > self::CONTENT_W - 44.0) {
                continue;
            }
            $this->labelled($this->t($key), $this->plain($line), $l, $y, self::CONTENT_W, 9.0, '');
            $y += 5.4;
            if ($key === 'addresses') {
                $this->vAddressesDone = true;
            } else {
                $this->vPhonesDone = true;
            }
        }
        $this->y = $y + 1.0;
    }

    /** @return list<string> dernier lieu connu, date, zone */
    private function lastKnown(): array
    {
        $f = $this->facts();
        $when = (string) $f['last_seen_at'];
        $when = str_starts_with($when, self::BLOCK) ? $when : ($when !== '' ? $this->dateOrRaw(substr($when, 0, 10)) : '');

        return array_values(array_filter([(string) $f['last_seen_place'], $when, (string) $f['zone'] !== '' ? $this->t('zone')[$this->lang] . ' : ' . $f['zone'] : ''], static fn (string $v): bool => trim($v) !== ''));
    }

    /**
     * Liste courte (3 libellés au plus, 2 lignes chacun) ; retourne le bas de la liste. Un libellé caviardé devient un rectangle noir.
     *
     * @param list<string> $items
     */
    private function shortList(array $items, float $x, float $y, float $w, float $room, bool $lead): float
    {
        $pdf = $this->pdf;
        $start = $y;
        $items = $items !== [] ? array_slice($items, 0, 3) : [$this->v('none_known')[$this->lang]];
        foreach ($items as $i => $item) {
            if ($y - $start + 4.4 > $room) {
                break;
            }
            if (str_contains($item, self::BLOCK)) {
                $this->barLine($item, $x, $y, $w, 8.8);
                $y += 4.8;

                continue;
            }
            $item = $this->plain($item);
            [$item, $fs] = $this->shrink($item, $w, 8.8, 7.0);
            $pdf->SetFont($this->face($item), $lead && $i === 0 ? 'B' : '', $fs);
            $pdf->SetTextColor(...self::INK);
            $pdf->SetXY($x, $y);
            $pdf->MultiCell($w, 3.8, $item, 0, 'L', false, 1, null, null, true, 0, false, true, 7.8, 'T', true);
            $y += min(7.8, max(4.0, $pdf->getStringHeight($w, $item))) + 0.8;
        }

        return $y;
    }

    /**
     * Validité, en pied de la première page : émise le, valable jusqu'au, « PÉRISSABLE » si l'échéance est proche, émetteur.
     *
     * @param array<string, mixed> $a
     */
    private function validityStrip(array $a): void
    {
        $pdf = $this->pdf;
        $pdf->setPage(1);
        $d = $this->d;
        $l = self::MARGIN;
        $y = 258.4;
        $h = 9.6;
        $pdf->SetDrawColor(...self::INK);
        $pdf->SetLineWidth(0.5);
        $pdf->Rect($l, $y, self::CONTENT_W, $h, 'D');
        $cell = function (float $x, float $w, array $label, string $value) use ($pdf, $y): void {
            $pdf->SetFont($this->sans, '', 6.2);
            $pdf->SetTextColor(...self::MUTED);
            $pdf->SetXY($x + 2.4, $y + 1.0);
            $pdf->Cell($w - 3.0, 2.8, mb_strtoupper($label[$this->lang], 'UTF-8') . ' / ' . $label[1 - $this->lang], 0, 0, 'L');
            [$value, $s] = $this->artFit($value, $w - 4.0, 10.0, 7.0, 'B', $this->face($value));
            $pdf->SetFont($this->face($value), 'B', $s);
            $pdf->SetTextColor(...self::INK);
            $pdf->SetXY($x + 2.4, $y + 4.0);
            $pdf->Cell($w - 3.0, 4.6, $value, 0, 0, 'L');
        };
        $until = (string) ($d['valid_until'] ?? '');
        $tagW = !empty($a['perishable']) ? 34.0 : 0.0;
        $w1 = 44.0;
        $w2 = 50.0;
        $cell($l, $w1, $this->v('issued'), $this->date((string) $d['issued_at']));
        $pdf->SetDrawColor(...self::INK);
        $pdf->SetLineWidth(0.25);
        $pdf->Line($l + $w1, $y, $l + $w1, $y + $h);
        $exact = (string) ($d['expires_at'] ?? '');
        $validText = !empty($d['until_lifted']) ? ($this->lang === 0 ? 'Jusqu’à levée' : 'Until lifted')
            : ($exact !== '' ? $this->date(substr($exact, 0, 10)) . ' ' . substr($exact, 11, 5) . ' UTC' : ($until !== '' ? $this->date($until) : '—'));
        $cell($l + $w1, $w2, $this->v('valid'), $validText);
        $x = $l + $w1 + $w2;
        if ($tagW > 0) {
            // Échéance proche : cartouche hachuré « PÉRISSABLE ».
            $this->artHatch($x, $y, $tagW, $h, [16, 18, 22], [242, 194, 0], 3.0);
            $pdf->SetFillColor(255, 255, 255);
            $pdf->Rect($x + 2.0, $y + 2.0, $tagW - 4.0, $h - 4.0, 'F');
            $pdf->SetFont($this->sans, 'B', 9.4);
            $pdf->SetTextColor(16, 18, 22);
            $pdf->SetXY($x + 2.0, $y + 2.6);
            $pdf->Cell($tagW - 4.0, 4.4, $this->v('perishable')[$this->lang], 0, 0, 'C');
            $x += $tagW;
        } else {
            $pdf->Line($x, $y, $x, $y + $h);
        }
        $issuer = trim((string) ($d['issuer'] ?? ''));
        $cell($x, $l + self::CONTENT_W - $x, $this->v('issued_by'), $issuer !== '' ? $issuer : ((string) $d['service'] !== '' ? (string) $d['service'] : (string) $d['authority'][$this->lang]));
        $pdf->SetLineWidth(0.25);
        $pdf->setPage($this->pages);
        if ($this->pages === 1) {
            $this->y = self::BOTTOM;
        }
    }

    /** Annexe (page 2, seulement s'il y a de quoi) : comportement observé, commentaires, adresses, téléphones et courriels. */
    private function annex(): void
    {
        $f = $this->facts();
        $texts = (array) ($this->d['texts'] ?? []);
        $addresses = (array) $f['links']['address'];
        $phones = array_merge((array) $f['links']['phone'], (array) $f['links']['email']);
        $behaviour = trim((string) ($texts['behaviour'] ?? ''));
        $mention = trim((string) ($texts['mention'] ?? ''));
        if ($this->vAddressesDone && count($addresses) <= 3) {
            $addresses = [];
        }
        if ($this->vPhonesDone) {
            $phones = [];
        }
        $lists = array_values(array_filter([['addresses', $addresses], ['phones', $phones]], static fn (array $l): bool => $l[1] !== []));
        $deferred = $this->vReasonInAnnex || $this->vKnownInAnnex;
        if (!$deferred && $behaviour === '' && $mention === '' && $lists === []) {
            return;
        }
        // Tout tient sous la première page (au-dessus du cartouche de validité) : pas de seconde page pour si peu.
        $need = 0.0;
        foreach ([$behaviour, $mention] as $t) {
            $need += $t !== '' ? $this->htmlHeight(SseSheetText::toHtml($t), self::CONTENT_W - 8.0, 9.2) + 15.0 : 0.0;
        }
        $need += $lists !== [] ? 9.0 + max(array_map(fn (array $l): float => $this->listHeight($l[1], (self::CONTENT_W - 6.0) / 2 - 5.0), $lists)) : 0.0;
        $onFirst = !$deferred && $this->y + $need <= 255.0 && $this->d['faces'] !== '2';
        if (!$onFirst) {
            if ($this->d['faces'] === '1') {
                $this->overflow[] = 'annex';

                return;
            }
            if (!$this->newPage()) {
                return;
            }
        }
        if ($this->vReasonInAnnex) {
            $this->textSection('reason', false);
        }
        if ($this->vKnownInAnnex) {
            $this->knownRow(self::BOTTOM);
            // Les lignes d'adresses et de téléphones viennent d'être imprimées avec le bloc : pas de doublon en liste.
            $lists = array_values(array_filter($lists, fn (array $l): bool => !(($l[0] === 'addresses' && $this->vAddressesDone && count($l[1]) <= 3) || ($l[0] === 'phones' && $this->vPhonesDone))));
        }
        $this->textSection('behaviour', false);
        $this->twoLists($lists);
        $this->none = [];
        $this->textSection('mention', false);
    }
}
