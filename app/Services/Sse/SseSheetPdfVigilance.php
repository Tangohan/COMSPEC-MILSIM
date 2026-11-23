<?php

declare(strict_types=1);

namespace App\Services\Sse;

use App\Support\Pdf\AthenaPdfTheme;
use App\Support\SseAlertLevel;
use App\Support\SseAlertReportTo;
use App\Support\SseRedactionExplain as X;
use App\Support\SseSheetText;
use App\Support\SseUsClassification;

/**
 * Mise en page de la FICHE DE VIGILANCE (gabarit de SseSheetPdf) : un avertissement lisible en trois secondes sur le terrain.
 * Même grammaire que les autres fiches (bandeaux de classification, en-tête du service, numéro de contrôle, rubriques numérotées avec
 * marquage de portion, pied à code-barres), mais la page s'ouvre sur un BLOC D'ALERTE dominant : bandes hachurées, pictogramme,
 * niveau en très grand, rang « n/4 » en barres, consigne dominante. Le niveau ne dépend jamais de la seule couleur.
 *
 * Grille : A4 portrait, marges de 12 mm, une colonne de 186 mm. Typographie : titre 13,4 gras, rubriques 8,8 gras capitales, corps 9,2,
 * libellés 6,2 capitales espacées, filets de 0,2 mm. La mention de caviardage tient en trois lignes sous le bandeau d'alerte ; la page
 * est remplie par ÉCHELLE : la conduite à tenir, les « à ne pas faire », le service à informer (QR code, adresse) et les écarts entre
 * blocs grandissent avec la place libre (essais successifs sous transaction TCPDF), sans jamais laisser de vide en pied de page.
 *
 * Page 1 : alerte, sujet, conduite à tenir, à ne pas faire, service à informer, motif, dernier lieu connu, véhicules, personnes
 * associées, validité. Page 2 (annexe, seulement s'il y a de quoi) : ce qui n'a pas tenu, comportement, commentaires, adresses.
 * Une rubrique sans donnée disparaît (ou affiche « Non communiqué » dans sa colonne) : aucun texte de remplissage.
 */
trait SseSheetPdfVigilance
{
    /** Bas du contenu de la première page (le cartouche de validité commence en dessous). */
    private const V_LIMIT = 256.4;

    /** Haut et hauteur du cartouche de validité. */
    private const V_STRIP_Y = 259.6;

    private const V_STRIP_H = 10.2;

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
        'not_given' => ['Non communiqué', 'Not available'], 'behaviour' => ['Comportement observé', 'Observed behaviour'],
        'protected' => ['Identité protégée à ce niveau de diffusion', 'Identity protected at this distribution level'],
        'physical' => ['Signalement', 'Description'], 'hair_eyes' => ['Cheveux / yeux', 'Hair / eyes'], 'hair' => ['Cheveux', 'Hair'], 'eyes' => ['Yeux', 'Eyes'],
        'grid' => ['Grille', 'Grid'], 'cont' => ['suite en annexe, page 2', 'continued in the annex, page 2'],
    ];

    /** Mention de caviardage : clé => [français, anglais] (le reste des textes est dans SseSheetPdfRedaction). */
    private const VRX = ['unspecified' => ['catégorie non indiquée', 'category not indicated']];

    /** Le motif complet passe en annexe quand la page 1 est pleine. */
    private bool $vReasonInAnnex = false;

    /** Les adresses et les téléphones sont déjà dans le tableau du sujet. */
    private bool $vAddressesDone = false;

    private bool $vPhonesDone = false;

    /** Le comportement observé a trouvé sa place sur la première page. */
    private bool $vBehaviourDone = false;

    /** Tout le motif est passé en annexe (page 1 pleine). */
    private bool $vReasonMoved = false;

    /** Essai de mise en page en cours (transaction TCPDF) : aucun texte HTML n'est écrit, la hauteur planifiée est rendue. */
    private bool $vTrialing = false;

    /** @var array<string, array{html: string, size: float, h: float, clipped: bool}> plans de texte déjà mesurés */
    private array $vPlans = [];

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
            $this->artServiceEmblem($a['report'], $l, 9.4, 12.0);
        } else {
            $this->emblem($l, 9.4, 12.0);
        }
        [$name, $size] = $this->artFit(mb_strtoupper((string) $d['authority'][$this->lang], 'UTF-8'), 112.0, 10.6, 6.8);
        $pdf->SetFont($this->sans, 'B', $size);
        $pdf->SetTextColor(...self::INK);
        $pdf->SetXY($l + 15.5, 10.4);
        $pdf->Cell(112.0, 4.8, $name, 0, 0, 'L');
        [$sub, $subSize] = $this->artFit($d['service'] !== '' ? $this->t('issuer')[$this->lang] . ' : ' . $d['service'] : (string) $d['authority'][1 - $this->lang], 112.0, 7.6, 6.2, '');
        $pdf->SetFont($this->sans, '', $subSize);
        $pdf->SetTextColor(...self::MUTED);
        $pdf->SetXY($l + 15.5, 16.2);
        $pdf->Cell(112.0, 3.8, $sub, 0, 0, 'L');
        // Numéro de contrôle et groupe date-heure : un bloc de texte aligné, sans cadre, séparé par un filet vertical.
        $x = 150.0;
        $pdf->SetDrawColor(...self::LINE);
        $pdf->SetLineWidth(0.25);
        $pdf->Line($x - 3.6, 9.8, $x - 3.6, 21.2);
        $this->artCaption($this->t('control_no')[$this->lang] . ' / ' . $this->t('control_no')[1 - $this->lang], $x, 9.6, 48.0, 'L', 5.8);
        [$ref, $rs] = $this->artFit((string) $d['ref'], 48.0, 12.6, 8.0);
        $pdf->SetFont($this->sans, 'B', $rs);
        $pdf->SetTextColor(...self::INK);
        $pdf->SetXY($x, 12.8);
        $pdf->Cell(48.0, 5.2, $ref, 0, 0, 'L');
        $pdf->SetFont($this->sans, '', 7.0);
        $pdf->SetTextColor(...self::MUTED);
        $pdf->SetXY($x, 18.4);
        $pdf->Cell(48.0, 3.4, 'DTG ' . $this->dtg(), 0, 0, 'L');
        // Titre du document et diffusion.
        $pdf->SetDrawColor(...self::INK);
        $pdf->SetLineWidth(0.6);
        $pdf->Line($l, 24.6, $l + self::CONTENT_W, 24.6);
        $pdf->SetFont($this->sans, 'B', 13.4);
        $pdf->SetTextColor(...self::INK);
        $title = mb_strtoupper((string) $d['title'][$this->lang], 'UTF-8');
        $pdf->setFontSpacing(0.45);
        $titleW = $pdf->GetStringWidth($title);
        $pdf->SetXY($l, 26.6);
        $pdf->Cell($titleW + 1.0, 5.6, $title, 0, 0, 'L');
        $pdf->setFontSpacing(0);
        $pdf->SetFont($this->sans, '', 7.6);
        $pdf->SetTextColor(...self::MUTED);
        $pdf->SetXY($l + $titleW + 4.0, 28.4);
        $pdf->Cell(70.0, 3.8, mb_strtoupper((string) $d['title'][1 - $this->lang], 'UTF-8'), 0, 0, 'L');
        // Diffusion : les mentions en gras, le reste en texte courant.
        $caveats = implode(', ', SseUsClassification::dissemination($this->level()));
        $rest = ' · ' . $d['classification'][$this->lang] . ' · ' . ($this->lang === 1 ? 'Cleared personnel, SSE field distribution' : 'Personnel habilité, diffusion SSE de terrain');
        $pdf->SetFont($this->sans, 'B', 6.8);
        $head = mb_strtoupper($this->lang === 1 ? 'Distribution' : 'Diffusion', 'UTF-8') . ' : ' . $caveats;
        $hw = $pdf->GetStringWidth($head) + 0.6;
        $pdf->SetTextColor(...self::INK);
        $pdf->SetXY($l, 33.4);
        $pdf->Cell($hw, 3.4, $head, 0, 0, 'L');
        [$rest, $rSize] = $this->artFit($rest, self::CONTENT_W - $hw, 6.8, 5.6, '');
        $pdf->SetFont($this->sans, '', $rSize);
        $pdf->SetTextColor(...self::MUTED);
        $pdf->SetXY($l + $hw, 33.4);
        $pdf->Cell(self::CONTENT_W - $hw, 3.4, $rest, 0, 0, 'L');
        $pdf->SetLineWidth(0.25);
        $this->y = 38.8;
    }

    // ------------------------------------------------------------------ Corps

    private function vigilance(): void
    {
        $a = $this->alert();
        // Aucun texte de remplissage : les phrases « à compléter » et le rappel du dernier lieu (déjà dans le tableau) sont écartés.
        foreach (['reason', 'behaviour', 'mention'] as $key) {
            if (isset($this->d['texts'][$key]) && is_string($this->d['texts'][$key])) {
                $this->d['texts'][$key] = self::vCleanText($this->d['texts'][$key]);
            }
        }
        $this->vReasonInAnnex = false;
        $this->vReasonMoved = false;
        $this->vAddressesDone = false;
        $this->vPhonesDone = false;
        $this->vBehaviourDone = false;
        $this->alertBlock($a);
        $this->vRedactionStrip();
        $this->subjectBlock();
        $this->vFlow($a);
        $this->annex();
        $this->validityStrip($a);
    }

    /** Retire d'un texte rédigé les phrases de remplissage (« à compléter », « to be completed ») et le rappel « Dernier lieu connu : … ». */
    private static function vCleanText(string $text): string
    {
        $lines = [];
        foreach (preg_split('/\R/u', $text) ?: [] as $line) {
            $kept = [];
            foreach (preg_split('/(?<=[.!?])\s+/u', trim($line)) ?: [] as $sentence) {
                $sentence = trim($sentence);
                if ($sentence === '') {
                    continue;
                }
                if (preg_match('/(à compléter|à renseigner|à préciser|to be completed|to be filled|to be confirmed|TBD)\W*$/iu', $sentence) === 1
                    || preg_match('/^(dernier lieu connu|last seen|last known location)\s*:/iu', $sentence) === 1) {
                    continue;
                }
                $kept[] = $sentence;
            }
            $lines[] = implode(' ', $kept);
        }

        return trim((string) preg_replace('/\n{3,}/', "\n\n", implode("\n", $lines)));
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
        $hatch = 3.0;
        $solidH = 26.4;
        $black = [16, 18, 22];
        // Bandes hachurées haut et bas (noir et couleur du niveau), aplat de la couleur entre les deux.
        $this->artHatch($l, $y0, $w, $hatch, $black, $rgb, 3.0);
        $pdf->SetFillColor(...$rgb);
        $pdf->Rect($l, $y0 + $hatch, $w, $solidH, 'F');
        $this->artHatch($l, $y0 + $hatch + $solidH, $w, $hatch, $black, $rgb, 3.0);
        $pdf->SetDrawColor(...$black);
        $pdf->SetLineWidth(0.7);
        $pdf->Rect($l, $y0, $w, $hatch * 2 + $solidH, 'D');
        $s = $y0 + $hatch;
        // Pictogramme : forme du niveau, dessinée dans l'encre du bloc.
        $this->artPictogram($l + 6.0, $s + 2.2, 22.0, (string) $level['shape'], $ink, $rgb);
        // Rang : « NIVEAU 3/4 » et barres (lisibles sans couleur).
        $rx = $l + $w - 33.0;
        $pdf->SetDrawColor(...$ink);
        $pdf->SetLineWidth(0.35);
        $pdf->Line($rx - 3.0, $s + 3.0, $rx - 3.0, $s + $solidH - 3.0);
        $pdf->SetTextColor(...$ink);
        $pdf->SetFont($this->sans, 'B', 7.0);
        $pdf->setFontSpacing(0.3);
        $pdf->SetXY($rx, $s + 2.6);
        $pdf->Cell(30.0, 3.2, mb_strtoupper($this->v('level')[$this->lang] . ' / ' . $this->v('level')[1 - $this->lang], 'UTF-8'), 0, 0, 'L');
        $pdf->setFontSpacing(0);
        $pdf->SetFont($this->sans, 'B', 25.0);
        $pdf->SetXY($rx - 0.6, $s + 5.4);
        $pdf->Cell(30.0, 10.4, $level['rank'] . '/4', 0, 0, 'L');
        $this->artBars($rx, $s + 16.2, (int) $level['rank'], $ink, 5.6, 1.8, 8.4);
        // Titre du niveau, en très grand. « DANGER — ARMÉ ET DANGEREUX » : le mot d'alerte seul sur la première ligne.
        $tx = $l + 36.0;
        $tw = $rx - 6.0 - $tx;
        $title = (string) $level['title'][$this->lang];
        $other = (string) $level['title'][1 - $this->lang];
        $order = $a['order'] !== '' ? SseAlertLevel::ORDERS[$a['order']][$this->lang] : '';
        $parts = explode(' — ', $title, 2);
        $pdf->SetTextColor(...$ink);
        if (count($parts) === 2) {
            [$t1, $s1] = $this->artFit($parts[0], $tw, 33.0, 16.0);
            $pdf->SetFont($this->sans, 'B', $s1);
            $pdf->SetXY($tx, $s + 0.2);
            $pdf->Cell($tw, 12.4, $t1, 0, 0, 'L');
            [$t2, $s2] = $this->artFit($parts[1], $tw, 19.0, 10.0);
            $pdf->SetFont($this->sans, 'B', $s2);
            $pdf->SetXY($tx, $s + 11.6);
            $pdf->Cell($tw, 7.2, $t2, 0, 0, 'L');
            $after = $s + 19.4;
        } else {
            [$t1, $s1] = $this->artFit($title, $tw, 28.0, 13.0);
            $pdf->SetFont($this->sans, 'B', $s1);
            $pdf->SetXY($tx, $s + ($order !== '' ? 1.4 : 4.6));
            $pdf->Cell($tw, 11.0, $t1, 0, 0, 'L');
            $pdf->SetFont($this->sans, 'B', 8.6);
            $pdf->SetXY($tx + 0.4, $s + ($order !== '' ? 12.6 : 16.2));
            $pdf->Cell($tw, 4.2, $other, 0, 0, 'L');
            $after = $s + ($order !== '' ? 18.2 : 99.0);
        }
        // Consigne dominante : cartouche inversé (encre du bloc en fond, couleur du niveau en texte).
        if ($order !== '') {
            [$o, $os] = $this->artFit($order, $tw - 6.0, 11.5, 7.6);
            $pdf->SetFont($this->sans, 'B', $os);
            $ow = $pdf->GetStringWidth($o) + 6.0;
            $pdf->SetFillColor(...$ink);
            $pdf->Rect($tx, $after, $ow, 6.2, 'F');
            $pdf->SetTextColor(...$rgb);
            $pdf->SetXY($tx, $after + 0.6);
            $pdf->Cell($ow, 5.0, $o, 0, 0, 'C');
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
        $this->y = $y0 + $hatch * 2 + $solidH + 3.4;
    }

    // ------------------------------------------------------------ Mention de caviardage

    /**
     * Mention compacte sous le bandeau d'alerte : trois lignes (titre et nombre de passages, niveau de diffusion et catégories avec
     * le niveau qui les rend lisibles, marche à suivre). Rien n'est dessiné sans passage masqué. Ne porte jamais une valeur masquée.
     */
    private function vRedactionStrip(): void
    {
        if ($this->rxLetters === null || $this->rxKnown['total'] < 1) {
            return;
        }
        $pdf = $this->pdf;
        $res = $this->rxKnown;
        $x = self::MARGIN;
        $w = self::CONTENT_W;
        $d = $this->d;
        $lang = $this->lang === 1 ? 'en' : 'fr';
        $colon = $this->lang === 1 ? ': ' : ' : ';
        $items = [];
        foreach ($this->rxLetters as $cat => $letter) {
            $n = (int) ($res['by'][$cat] ?? 0);
            if ($n < 1) {
                continue;
            }
            $need = $cat === '' ? self::VRX['unspecified'][$this->lang] : X::label($cat, $lang) . $colon . sprintf($this->rxText('needs'), X::need($cat, is_array($d['visibility'] ?? null) ? $d['visibility'] : null));
            $items[] = '(' . $letter . ') ' . $need . ' (' . $n . ')';
        }
        $level = SseUsClassification::label($this->level(), $lang);
        $title = self::RX['title'][$this->lang] . ' / ' . self::RX['title'][1 - $this->lang] . '  ·  ' . $this->rxCount($res['total']);
        $second = sprintf($this->rxText('level'), $level) . ' ' . implode(' · ', $items);
        $third = sprintf($this->rxText('how'), (string) $d['ref']);
        $h = 12.0;
        $y = $this->y;
        $pdf->SetFillColor(...self::PAPER);
        $pdf->Rect($x, $y, $w, $h, 'F');
        $pdf->SetFillColor(0, 0, 0);
        $pdf->Rect($x, $y, 1.2, $h, 'F');
        // Repère : une barre noire miniature, comme celles du document.
        $pdf->Rect($x + 4.0, $y + 2.2, 6.0, 2.4, 'F');
        $iw = $w - 16.0;
        foreach ([[$title, 'B', 7.4, 1.1, self::INK], [$second, '', 6.9, 4.5, self::INK], [$third, '', 6.4, 7.8, self::MUTED]] as [$text, $style, $size, $dy, $color]) {
            [$text, $fs] = $this->artFit($text, $iw, $size, 5.2, $style, $this->face($text));
            $pdf->SetFont($this->face($text), $style, $fs);
            $pdf->SetTextColor(...$color);
            $pdf->SetXY($x + 12.5, $y + $dy);
            $pdf->Cell($iw, 3.4, $text, 0, 0, 'L');
        }
        $this->y = $y + $h + 3.8;
    }

    // ------------------------------------------------------------ Sujet

    /**
     * Rubrique « Sujet » : portrait (ou silhouette), nom, puis tableau aligné des repères : alias, signalement, naissance, signes,
     * dernier lieu connu, véhicules, personnes associées, adresses, téléphones. Une ligne sans donnée n'est pas écrite.
     */
    private function subjectBlock(): void
    {
        $pdf = $this->pdf;
        $f = $this->facts();
        $l = self::MARGIN;
        $top = $this->y;
        $this->vHead($this->v('subject'), $l, self::CONTENT_W, $top);
        $y0 = $top + 9.0;
        $pw = 32.0;
        $x = $l + $pw + 7.0;
        $w = self::CONTENT_W - $pw - 7.0;
        $lw = 36.0;
        $last = (string) $f['last_name'];
        $first = (string) $f['first_name'];
        $masked = str_contains($last . $first, self::BLOCK);
        // Tableau des repères.
        $sex = self::SEX[$f['sex']] ?? self::SEX['X'];
        $physical = array_values(array_filter([
            $f['sex'] !== 'X' && $f['sex'] !== '' ? $sex[$this->lang] : '', $f['age_estimated'] !== null ? $f['age_estimated'] . ($this->lang === 1 ? ' yrs' : ' ans') : '',
            $f['height_cm'] !== null ? $f['height_cm'] . ' cm' : '', $f['weight_kg'] !== null ? $f['weight_kg'] . ' kg' : '', (string) $f['build'],
        ], static fn (mixed $v): bool => trim((string) $v) !== ''));
        $hair = trim((string) $f['hair']);
        $eyes = trim((string) $f['eyes']);
        $traits = array_values(array_filter([
            $hair !== '' ? ($this->lang === 1 ? 'hair ' : 'cheveux ') . mb_strtolower($hair, 'UTF-8') : '',
            $eyes !== '' ? ($this->lang === 1 ? 'eyes ' : 'yeux ') . mb_strtolower($eyes, 'UTF-8') : '',
        ], static fn (string $v): bool => $v !== ''));
        $aliases = implode(' ; ', array_map('strval', (array) $f['aliases']));
        $born = array_values(array_filter([$this->dateOrRaw((string) $f['birth_date']), (string) $f['birth_place'], (string) $f['nationality']], static fn (string $v): bool => trim($v) !== ''));
        $vehicles = array_slice(array_values(array_map('strval', (array) $f['links']['vehicle'])), 0, 3);
        $assoc = array_slice(array_values(array_map('strval', (array) $f['links']['associate'])), 0, 3);
        $rows = [
            [$this->v('alias'), $aliases, 1, true],
            [$this->v('physical'), implode(' · ', array_merge(array_map('strval', $physical), $traits)), 1, false],
            [$this->v('born'), implode(' · ', $born), 1, false],
            [$this->v('marks'), $this->plain(trim((string) $f['marks'])), 2, false],
            [$this->v('last'), implode(' · ', $this->lastKnownLines()), 2, true],
            [$this->t('vehicles'), implode(' ; ', $vehicles), 2, false],
            [$this->v('assoc'), implode(' ; ', $assoc), 2, false],
        ];
        // Adresses, téléphones et courriels : une ligne chacun si elle tient en entier et n'est pas masquée, sinon en annexe.
        $addresses = array_values(array_map('strval', (array) $f['links']['address']));
        $phones = array_values(array_map('strval', array_merge((array) $f['links']['phone'], (array) $f['links']['email'])));
        foreach ([['addresses', $addresses], ['phones', $phones]] as [$key, $items]) {
            $line = implode(' ; ', array_slice($items, 0, 3));
            $visible = (string) preg_replace('/' . self::BLOCK . '+/u', '', $line);
            $bars = preg_match_all('/' . self::BLOCK . '+/u', $line);
            $pdf->SetFont($this->face($visible), '', 9.2);
            if ($line !== '' && $pdf->GetStringWidth($visible) + 17.0 * (int) $bars <= $w - $lw - 2.0 && ($key === 'phones' || count($items) <= 3)) {
                $rows[] = [$this->t($key), $line, 1, false];
                if ($key === 'addresses') {
                    $this->vAddressesDone = true;
                } else {
                    $this->vPhonesDone = true;
                }
            }
        }
        $rows = array_values(array_filter($rows, static fn (array $r): bool => trim((string) $r[1]) !== ''));
        $nameH = 13.4;
        $tableH = 0.0;
        foreach ($rows as [, $value, $maxLines]) {
            $tableH += $this->vRowHeight((string) $value, $w - $lw, $maxLines);
        }
        $ph = max(40.0, min(52.0, $nameH + max($tableH, 1.0)));
        if ($this->hasPhoto()) {
            $this->portrait($l, $y0, $pw, $ph);
        } else {
            $this->vSilhouette($l, $y0, $pw, $ph);
        }
        // Nom : NOM en gras, prénom dessous ; barre noire de largeur fixe quand l'identité est masquée.
        if ($masked) {
            $this->vBar($x, $y0 + 0.6, 62.0, 7.0, self::BLOCK);
            $pdf->SetFont($this->sans, '', 7.4);
            $pdf->SetTextColor(...self::MUTED);
            $pdf->SetXY($x + 62.0 + 5.0, $y0 + 2.4);
            $pdf->Cell($w - 67.0, 3.6, $this->v('protected')[$this->lang], 0, 0, 'L');
        } else {
            $upper = mb_strtoupper($last, 'UTF-8');
            $surname = $upper !== '' ? $upper : '—';
            [$n1, $s1] = $this->artFit($surname, $w, 21.0, 10.5, 'B', $this->face($surname));
            $pdf->SetFont($this->face($surname), 'B', $s1);
            $pdf->SetTextColor(...self::INK);
            $pdf->SetXY($x, $y0 - 0.4);
            $pdf->Cell($w, 8.8, $n1, 0, 0, 'L');
            if ($first !== '') {
                [$n2, $s2] = $this->artFit($first, $w, 13.5, 8.5, '', $this->face($first));
                $pdf->SetFont($this->face($first), '', $s2);
                $pdf->SetXY($x, $y0 + 7.8);
                $pdf->Cell($w, 5.6, $n2, 0, 0, 'L');
            }
        }
        $y = $y0 + $nameH;
        foreach ($rows as [$label, $value, $maxLines, $strong]) {
            $y += $this->vRow($label, (string) $value, $x, $y, $w, $lw, $maxLines, $strong);
        }
        if ($rows === []) {
            $y += $this->vRow($this->v('physical'), $this->v('not_given')[$this->lang], $x, $y, $w, $lw, 1, false, true);
        }
        $pdf->SetDrawColor(...self::LINE);
        $pdf->SetLineWidth(0.15);
        $pdf->Line($x, $y, $x + $w, $y);
        $pdf->SetLineWidth(0.25);
        $this->y = max($y, $y0 + $ph) + 4.6;
    }

    /** Hauteur (mm) d'une ligne du tableau des repères : une ligne, ou deux quand la valeur est longue. */
    private function vRowHeight(string $value, float $vw, int $maxLines): float
    {
        if ($maxLines < 2 || str_contains($value, self::BLOCK)) {
            return 5.7;
        }
        $pdf = $this->pdf;
        $value = $this->plain($value);
        $pdf->SetFont($this->face($value), '', 9.2);

        return $pdf->GetStringWidth($value) > $vw ? 9.6 : 5.7;
    }

    /** Silhouette neutre, sobre, à la place d'un portrait indisponible. */
    private function vSilhouette(float $x, float $y, float $w, float $h): void
    {
        $pdf = $this->pdf;
        $pdf->SetFillColor(239, 241, 244);
        $pdf->SetDrawColor(...self::LINE);
        $pdf->SetLineWidth(0.3);
        $pdf->Rect($x, $y, $w, $h, 'DF');
        $tone = [196, 202, 210];
        $pdf->SetFillColor(...$tone);
        $pdf->Circle($x + $w / 2, $y + $h * 0.33, $w * 0.17, 0, 360, 'F');
        $pdf->StartTransform();
        $pdf->Rect($x + 0.3, $y + 0.3, $w - 0.6, $h - 7.2, 'CNZ');
        $pdf->Ellipse($x + $w / 2, $y + $h * 0.80, $w * 0.33, $h * 0.25, 0, 0, 360, 'F');
        $pdf->StopTransform();
        $pdf->SetFillColor(228, 231, 235);
        $pdf->Rect($x + 0.3, $y + $h - 6.9, $w - 0.6, 6.6, 'F');
        $pdf->SetDrawColor(...self::LINE);
        $pdf->Line($x, $y + $h - 6.9, $x + $w, $y + $h - 6.9);
        $pdf->SetTextColor(...self::MUTED);
        $pdf->SetFont($this->sans, 'B', 5.4);
        $pdf->setFontSpacing(0.25);
        $pdf->SetXY($x, $y + $h - 6.3);
        $pdf->Cell($w, 2.6, $this->v('photo_na')[$this->lang], 0, 0, 'C');
        $pdf->SetFont($this->sans, '', 4.8);
        $pdf->SetXY($x, $y + $h - 3.4);
        $pdf->Cell($w, 2.4, $this->v('photo_na')[1 - $this->lang], 0, 0, 'C');
        $pdf->setFontSpacing(0);
        $pdf->SetLineWidth(0.25);
    }

    /**
     * Ligne du tableau des repères ; retourne sa hauteur. Valeur masquée : barre noire de largeur fixe et lettre de repère.
     *
     * @param array{0: string, 1: string} $label
     */
    private function vRow(array $label, string $value, float $x, float $y, float $w, float $lw, int $maxLines, bool $strong, bool $muted = false): float
    {
        $pdf = $this->pdf;
        $pdf->SetDrawColor(...self::LINE);
        $pdf->SetLineWidth(0.15);
        $pdf->Line($x, $y, $x + $w, $y);
        $capSize = 6.2;
        $pdf->SetFont($this->sans, '', $capSize);
        $pdf->setFontSpacing(0.25);
        while ($capSize > 4.6 && $pdf->GetStringWidth(mb_strtoupper($label[$this->lang], 'UTF-8')) > $lw - 2.0) {
            $capSize -= 0.3;
            $pdf->SetFont($this->sans, '', $capSize);
        }
        $pdf->setFontSpacing(0);
        $this->artCaption($label[$this->lang], $x, $y + 1.7, $lw - 1.0, 'L', $capSize);
        $vw = $w - $lw;
        if (str_contains($value, self::BLOCK)) {
            $this->vBarLine($value, $x + $lw, $y + 0.8, $vw, 9.2);
            $pdf->SetTextColor(...self::INK);

            return 5.7;
        }
        $style = $strong ? 'B' : '';
        $text = $maxLines > 1 ? $value : $value;
        $pdf->SetFont($this->face($text), $style, 9.2);
        if ($maxLines === 1) {
            [$t, $s] = $this->artFit($text, $vw, 9.2, 7.0, $style, $this->face($text));
            $pdf->SetFont($this->face($t), $style, $s);
            $pdf->SetTextColor(...($muted ? self::MUTED : self::INK));
            $pdf->SetXY($x + $lw, $y + 0.7);
            $pdf->Cell($vw, 4.6, $t, 0, 0, 'L');

            return 5.7;
        }
        // Deux lignes au plus : réduit puis coupé.
        $size = 9.2;
        for (; $size > 7.0; $size -= 0.4) {
            $pdf->SetFont($this->face($text), $style, $size);
            if ($pdf->getStringHeight($vw, $text) <= $size * 0.3528 * 1.25 * 2.2) {
                break;
            }
        }
        $pdf->SetFont($this->face($text), $style, $size);
        while (mb_strlen($text, 'UTF-8') > 8 && $pdf->getStringHeight($vw, $text) > $size * 0.3528 * 1.25 * 2.2) {
            $text = rtrim(mb_substr($text, 0, mb_strlen($text, 'UTF-8') - 6, 'UTF-8')) . '…';
        }
        $pdf->SetTextColor(...self::INK);
        $pdf->SetXY($x + $lw, $y + 0.9);
        $pdf->MultiCell($vw, $size * 0.3528 * 1.25, $text, 0, 'L', false, 1, null, null, true, 0, false, true, $size * 0.3528 * 1.25 * 2.2, 'T', true);

        return $pdf->getStringHeight($vw, $text) > $size * 0.3528 * 1.5 ? 9.6 : 5.7;
    }

    /**
     * Barre de caviardage : rectangle noir de largeur fixe, lettre de repère de sa catégorie en blanc à l'intérieur. La largeur ne
     * dépend jamais de la valeur masquée.
     */
    private function vBar(float $x, float $y, float $w, float $h, string $run): void
    {
        $pdf = $this->pdf;
        $pdf->SetFillColor(0, 0, 0);
        $pdf->Rect($x, $y, $w, $h, 'F');
        $letter = $this->rxMark($run);
        if ($letter !== '') {
            $fs = min(8.4, max(5.0, $h / 0.3528 * 0.56));
            $lh = $fs * 0.3528 * 1.2;
            $pdf->SetFont($this->sans, 'B', $fs);
            $pdf->SetTextColor(255, 255, 255);
            $pdf->SetXY($x + $w - 4.6, $y + ($h - $lh) / 2);
            $pdf->Cell(3.6, $lh, $letter, 0, 0, 'R');
        }
    }

    /** Valeur masquée sur une ligne : le texte clair reste du texte, chaque suite de caviardage devient une barre de largeur fixe. */
    private function vBarLine(string $text, float $x, float $y, float $w, float $size): void
    {
        $pdf = $this->pdf;
        $parts = preg_split('/(' . self::BLOCK . '+)/u', $text, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY) ?: [];
        $cx = $x;
        $bh = max(3.2, $size * 0.3528 * 1.0);
        foreach ($parts as $part) {
            if (str_starts_with($part, self::BLOCK)) {
                $bw = min(self::BAR_W, max(4.0, $x + $w - $cx));
                $this->vBar($cx, $y + 0.6, $bw, $bh, $part);
                $cx += $bw + 1.4;

                continue;
            }
            $pdf->SetFont($this->face($part), '', $size);
            $pdf->SetTextColor(...self::INK);
            $tw = $pdf->GetStringWidth($part);
            $pdf->SetXY($cx, $y);
            $pdf->Cell($tw + 0.5, 4.4, $part, 0, 0, 'L');
            $cx += $tw;
        }
    }

    /** Titre de rubrique numérotée dans le style de la fiche. @param array{0: string, 1: string} $title */
    private function vHead(array $title, float $x, float $w, float $y): void
    {
        ++$this->n;
        $this->artSectionHead($this->n, $title, $x, $y, $w, '(' . SseUsClassification::portion($this->level()) . ')');
    }

    // ------------------------------------------------------------ Remplissage de la page

    /**
     * Conduite à tenir, service à informer, motif et comportement : la place libre de la première page est donnée par échelle.
     * Chaque configuration (de la plus complète à la plus économe) est essayée sous transaction TCPDF à des échelles décroissantes ;
     * la première qui tient dans la page est dessinée pour de bon, puis le reste de la place (jamais de vide en pied) grossit la
     * conduite à tenir, le service à informer et les écarts.
     *
     * @param array<string, mixed> $a
     */
    private function vFlow(array $a): void
    {
        $start = $this->y;
        $configs = [
            ['reason' => 34.0, 'behaviour' => true],
            ['reason' => 34.0, 'behaviour' => false],
            ['reason' => 22.0, 'behaviour' => false],
            ['reason' => 11.0, 'behaviour' => false],
            ['reason' => -1.0, 'behaviour' => false],
        ];
        // Les textes sont mesurés AVANT les essais (une transaction TCPDF ne s'imbrique pas).
        $this->vPlans = [];
        foreach ($configs as $cfg) {
            foreach ([['reason', (float) $cfg['reason']], ['behaviour', 16.0]] as [$key, $maxH]) {
                $text = trim((string) ($this->d['texts'][$key] ?? ''));
                if ($text !== '') {
                    $this->vTextPlan($text, $maxH);
                }
            }
        }
        $chosen = $configs[count($configs) - 1];
        $scale = 0.0;
        $bottom = $start;
        $fits = false;
        foreach ($configs as $cfg) {
            for ($f = 1.0; $f >= -0.001; $f -= 0.125) {
                $b = $this->vTrial($a, $cfg, max(0.0, $f), 0.0, 0.0);
                if ($b <= self::V_LIMIT) {
                    $chosen = $cfg;
                    $scale = max(0.0, $f);
                    $bottom = $b;
                    $fits = true;
                    break 2;
                }
            }
        }
        $grow = 0.0;
        $gapExtra = 0.0;
        if ($fits && $scale >= 0.999 && $bottom < self::V_LIMIT) {
            // Place restante : une part pour la conduite à tenir et le service à informer, le reste pour l'écart avant le motif.
            $free = self::V_LIMIT - $bottom;
            $after = $this->vHasReason($a) ? 1 : 0;
            $grow = min(46.0, $after > 0 ? $free * 0.7 : $free);
            $gapExtra = $after > 0 ? min(12.0, ($free - $grow) / $after) : 0.0;
            $try = $this->vTrial($a, $chosen, 1.0, $grow, $gapExtra);
            for ($i = 0; $i < 4 && $try > self::V_LIMIT + 0.2; ++$i) {
                $grow *= 0.7;
                $gapExtra *= 0.7;
                $try = $this->vTrial($a, $chosen, 1.0, $grow, $gapExtra);
            }
        }
        $this->y = $start;
        $this->vBody($a, $chosen, $scale, $grow, $gapExtra);
        $this->pdf->SetLineWidth(0.25);
    }

    /** Essai de mise en page sous transaction : rend le bas du contenu, sans rien laisser dans le PDF. @param array<string, mixed> $a @param array<string, mixed> $cfg */
    private function vTrial(array $a, array $cfg, float $f, float $grow, float $gapExtra): float
    {
        $pdf = $this->pdf;
        $save = [$this->y, $this->n, $this->vBehaviourDone, $this->vReasonInAnnex, $this->vReasonMoved];
        $pdf->startTransaction();
        ++$this->rxMeasuring;
        $this->vTrialing = true;
        $bottom = $this->vBody($a, $cfg, $f, $grow, $gapExtra);
        $this->vTrialing = false;
        --$this->rxMeasuring;
        $pdf->rollbackTransaction(true);
        [$this->y, $this->n, $this->vBehaviourDone, $this->vReasonInAnnex, $this->vReasonMoved] = $save;

        return $bottom;
    }

    /**
     * Dessine les blocs élastiques à l'échelle $f (0 : serré, 1 : aéré) ; retourne le bas du contenu.
     *
     * @param array<string, mixed> $a
     * @param array<string, mixed> $cfg
     */
    private function vBody(array $a, array $cfg, float $f, float $grow, float $gapExtra): float
    {
        $gap = self::artLerp(3.2, 6.4, $f) + $gapExtra;
        $this->vBehaviourDone = false;
        $this->vReasonInAnnex = false;
        $this->vReasonMoved = false;
        $this->vMiddle($a, $f, $grow);
        if ($this->vHasReason($a)) {
            if ((float) $cfg['reason'] < 0.0) {
                // Page pleine : tout le motif (étiquettes et texte) passe en annexe.
                $this->vReasonMoved = true;
                $this->vReasonInAnnex = true;
            } else {
                $this->y += $gap;
                $this->vReasonBlock($a, (float) $cfg['reason'], $cfg['behaviour'] === true);
            }
        }

        return $this->y;
    }

    /** @param array<string, mixed> $a */
    private function vHasReason(array $a): bool
    {
        return $a['reasons'] !== [] || trim((string) ($this->d['texts']['reason'] ?? '')) !== '';
    }

    // ------------------------------------------------------------ Conduite à tenir et service à informer

    /**
     * Deux colonnes alignées sur la même hauteur : conduite à tenir numérotée et « à ne pas faire » à gauche ; service à informer
     * (canal, délai, référence, QR code, adresse) à droite.
     *
     * @param array<string, mixed> $a
     */
    private function vMiddle(array $a, float $f, float $grow): void
    {
        $l = self::MARGIN;
        $top = $this->y;
        $leftW = 108.0;
        $gutter = 6.0;
        $rightX = $l + $leftW + $gutter;
        $rightW = self::CONTENT_W - $leftW - $gutter;
        $this->vHead($this->v('conduct'), $l, $leftW, $top);
        $this->vHead($this->v('report'), $rightX, $rightW, $top);
        $y = $top + 9.0;
        // Hauteur naturelle de la carte du service (sans extension) et de la colonne de gauche.
        $cardNat = $this->vCardHeight($a, $f);
        $leftBottom = $this->vConduct($a, $l, $y, $leftW, $f, 0.0, true);
        $donts = $a['donts'] !== [] ? $this->vDontsHeight($a, $f) : 0.0;
        $leftNat = $leftBottom - $y + ($donts > 0 ? 3.0 + $donts : 0.0);
        $target = max($cardNat + $grow, $leftNat);
        // Colonne de gauche : les lignes de la conduite et l'encadré grandissent avec la place restante.
        $extra = max(0.0, $target - $leftNat);
        $dontShare = $donts > 0 ? 0.4 : 0.0;
        $leftBottom = $this->vConduct($a, $l, $y, $leftW, $f, $extra * (1.0 - $dontShare), false);
        if ($donts > 0) {
            $leftBottom = $this->vDonts($a, $l, $leftBottom + 3.0, $leftW, $f, $donts + $extra * $dontShare);
        }
        $bottom = max($leftBottom, $y + $cardNat + $grow);
        $this->vCard($a, $rightX, $y, $rightW, $f, $bottom - $y);
        $this->y = $bottom;
    }

    /** @return list<array{0: string, 1: string}> lignes de la conduite à tenir : texte, code */
    private function conductLines(array $a): array
    {
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

        return $lines;
    }

    /**
     * Conduite à tenir : lignes numérotées séparées d'un filet. Retourne le bas ; $measure vrai : calcul seul (rien n'est dessiné).
     *
     * @param array<string, mixed> $a
     */
    private function vConduct(array $a, float $x, float $y, float $w, float $f, float $extra, bool $measure): float
    {
        $pdf = $this->pdf;
        $lines = $this->conductLines($a);
        $size = self::artLerp(9.2, 11.8, $f);
        $pad = self::artLerp(1.3, 2.6, $f) + min(2.6, $extra / max(1, count($lines)) / 2);
        $startY = $y;
        foreach ($lines as $i => [$text, $code]) {
            $text = $this->plain($text);
            $textW = $w - 9.6 - ($code !== '' ? 15.0 : 0.0);
            $pdf->SetFont($this->face($text), 'B', $size);
            $fs = $size;
            if ($pdf->GetStringWidth($text) > $textW) {
                [$text, $fs] = $this->shrink($text, $textW, $size, 8.2);
                $fs = min($fs, $size);
            }
            $pdf->SetFont($this->face($text), 'B', $fs);
            $lh = $fs * 0.3528 * 1.2;
            $textH = max($lh, min($lh * 2.1, $pdf->getStringHeight($textW, $text)));
            $rowH = max(6.4, $textH + 2 * $pad);
            if (!$measure) {
                $cy = $y + $pad + $lh / 2;
                $pdf->SetFillColor(...self::INK);
                $pdf->Circle($x + 2.9, $cy, 2.9, 0, 360, 'F');
                $pdf->SetFont($this->sans, 'B', 8.6);
                $pdf->SetTextColor(255, 255, 255);
                $pdf->SetXY($x, $cy - 1.9);
                $pdf->Cell(5.8, 3.8, (string) ($i + 1), 0, 0, 'C');
                $pdf->SetTextColor(...self::INK);
                $pdf->SetFont($this->face($text), 'B', $fs);
                $pdf->SetXY($x + 9.6, $y + $pad);
                $pdf->MultiCell($textW, $lh, $text, 0, 'L', false, 1, null, null, true, 0, false, true, $lh * 2.1, 'T', true);
                if ($code !== '') {
                    $pdf->SetFont(AthenaPdfTheme::mono(), 'B', 7.0);
                    $pdf->SetTextColor(...self::MUTED);
                    $pdf->SetXY($x + $w - 15.0, $y + $pad + ($lh - 2.6) / 2);
                    $pdf->Cell(15.0, 3.0, $code, 0, 0, 'R');
                }
                $pdf->SetDrawColor(...self::LINE);
                $pdf->SetLineWidth(0.15);
                $pdf->Line($x + 9.6, $y + $rowH, $x + $w, $y + $rowH);
            }
            $y += $rowH;
        }

        return $measure ? $startY + ($y - $startY) : $y;
    }

    /** Hauteur de l'encadré « à ne pas faire ». @param array<string, mixed> $a */
    private function vDontsHeight(array $a, float $f): float
    {
        return 2.0 + 4.4 + 1.2 + count($a['donts']) * self::artLerp(5.0, 6.2, $f) + 1.6;
    }

    /** Encadré « à ne pas faire » : titre, croix et consignes ; retourne le bas. @param array<string, mixed> $a */
    private function vDonts(array $a, float $x, float $y, float $w, float $f, float $h): float
    {
        $pdf = $this->pdf;
        $n = count($a['donts']);
        $pitch = self::artLerp(5.0, 6.2, $f);
        $h = max($h, $this->vDontsHeight($a, $f));
        $pitch += min(1.6, ($h - $this->vDontsHeight($a, $f)) / max(1, $n) / 2.0);
        $pdf->SetFillColor(...self::PAPER);
        $pdf->Rect($x, $y, $w, $h, 'F');
        $pdf->SetFillColor(...self::RED);
        $pdf->Rect($x, $y, 1.6, $h, 'F');
        $pdf->SetFont($this->sans, 'B', 7.2);
        $pdf->setFontSpacing(0.35);
        $pdf->SetTextColor(...self::INK);
        $off = max(0.0, ($h - $this->vDontsHeight($a, $f) - ($pitch - self::artLerp(5.0, 6.2, $f)) * $n) / 2.0);
        $pdf->SetXY($x + 6.0, $y + 2.0 + $off);
        $pdf->Cell($w - 8.0, 3.6, mb_strtoupper($this->v('dont')[$this->lang], 'UTF-8') . '  /  ' . mb_strtoupper($this->v('dont')[1 - $this->lang], 'UTF-8'), 0, 0, 'L');
        $pdf->setFontSpacing(0);
        $iy = $y + 2.0 + 4.4 + 1.2 + $off;
        $size = self::artLerp(9.0, 10.4, $f);
        foreach ($a['donts'] as $dont) {
            $cy = $iy + $pitch / 2 - 0.2;
            $pdf->SetDrawColor(...self::RED);
            $pdf->SetLineWidth(0.8);
            $pdf->Line($x + 6.0, $cy - 1.4, $x + 8.8, $cy + 1.4);
            $pdf->Line($x + 8.8, $cy - 1.4, $x + 6.0, $cy + 1.4);
            [$dt, $ds] = $this->artFit($dont, $w - 16.0, $size, 7.4);
            $pdf->SetFont($this->sans, 'B', $ds);
            $pdf->SetTextColor(...self::INK);
            $pdf->SetXY($x + 11.6, $cy - 2.4);
            $pdf->Cell($w - 14.0, 4.8, $dt, 0, 0, 'L');
            $iy += $pitch;
        }
        $pdf->SetLineWidth(0.25);

        return $y + $h;
    }

    /** Taille du code QR à l'échelle $f (mm). */
    private function vQrSize(float $f): float
    {
        return self::artLerp(21.0, 29.0, $f);
    }

    /** Hauteur naturelle de la carte du service à informer. @param array<string, mixed> $a */
    private function vCardHeight(array $a, float $f): float
    {
        return $this->vCard($a, 0.0, 0.0, 72.0, $f, 0.0, true);
    }

    /**
     * Carte du service à informer : emblème, nom, canal, délai et référence à citer, puis QR code et adresse en clair. Hauteur
     * cible $h (l'excédent se répartit entre les lignes) ; $measure vrai : retourne la hauteur naturelle sans rien dessiner.
     *
     * @param array<string, mixed> $a
     */
    private function vCard(array $a, float $x, float $y, float $w, float $f, float $h, bool $measure = false): float
    {
        $pdf = $this->pdf;
        $r = $a['report'];
        $pad = self::artLerp(2.6, 3.2, $f);
        $iw = $w - 2 * $pad;
        $lang = $this->lang === 1 ? 'en' : 'fr';
        $name = SseAlertReportTo::label($r, $lang);
        $name = $this->plain($name !== '' ? $name : (string) $this->d['service']);
        $nameW = $iw - 13.4;
        [$name, $ns] = $this->shrink($name, $nameW, 9.8, 7.4);
        $pdf->SetFont($this->face($name), 'B', $ns);
        $nameH = min(9.0, max(4.2, $pdf->getStringHeight($nameW, $name)));
        $authority = (string) $r[$lang === 'en' ? 'auth_en' : 'auth_fr'];
        if (trim($authority) === trim((string) $this->d['authority'][$this->lang])) {
            $authority = '';
        }
        $headH = max(10.6, $nameH + ($authority !== '' ? 3.6 : 0.0));
        $channel = $this->plain((string) $r['channel']);
        [$channel, $cs] = $this->shrink($channel, $iw, 8.8, 6.8);
        $pdf->SetFont($this->face($channel), 'B', $cs);
        $chanH = min(8.4, max(4.2, $pdf->getStringHeight($iw, $channel)));
        $address = trim((string) $r['address']) !== '' ? $this->plain((string) preg_replace('/\s*[\r\n]+\s*/', ' · ', (string) $r['address'])) : '';
        $pdf->SetFont($this->face($address), '', 6.8);
        $addrH = $address !== '' ? min(7.4, max(3.4, $pdf->getStringHeight($iw, $address))) : 0.0;
        $url = (string) $a['url'];
        $qr = $url !== '' ? $this->vQrSize($f) : 0.0;
        $natural = $pad + $headH + 2.6 + 3.1 + $chanH + ($addrH > 0 ? 1.0 + $addrH : 0.0) + 2.2 + 3.1 + 5.6 + ($qr > 0 ? 3.0 + $qr : 0.0) + $pad;
        if ($measure) {
            return $natural;
        }
        $spare = max(0.0, $h - $natural);
        if ($qr > 0 && $spare > 8.0) {
            // Place en trop : le QR code grossit d'abord (jusqu'à 34 mm), le reste se répartit entre les lignes.
            $bump = max(0.0, min(30.0 - $qr, $spare * 0.4));
            $qr += $bump;
            $natural += $bump;
            $spare -= $bump;
        }
        $sp = $spare;
        // Cadre de la carte.
        $pdf->SetDrawColor(...self::INK);
        $pdf->SetLineWidth(0.45);
        $pdf->Rect($x, $y, $w, max($h, $natural), 'D');
        $pdf->SetLineWidth(0.15);
        $cy = $y + $pad;
        $this->artServiceEmblem($r, $x + $pad, $cy, 10.6);
        $pdf->SetFont($this->face($name), 'B', $ns);
        $pdf->SetTextColor(...self::INK);
        $pdf->SetXY($x + $pad + 13.4, $cy);
        $pdf->MultiCell($nameW, $ns * 0.3528 * 1.15, $name, 0, 'L', false, 1, null, null, true, 0, false, true, 9.0, 'T', true);
        if ($authority !== '') {
            [$authority, $as] = $this->artFit($authority, $nameW, 6.6, 5.4, '');
            $pdf->SetFont($this->sans, '', $as);
            $pdf->SetTextColor(...self::MUTED);
            $pdf->SetXY($x + $pad + 13.4, $cy + $nameH + 0.6);
            $pdf->Cell($nameW, 3.0, $authority, 0, 0, 'L');
        }
        $cy += $headH + 2.6 + $sp * 0.3;
        $pdf->SetDrawColor(...self::LINE);
        $pdf->Line($x + $pad, $cy - 1.3, $x + $w - $pad, $cy - 1.3);
        $this->artCaption($this->v('channel')[$this->lang] . ' / ' . $this->v('channel')[1 - $this->lang], $x + $pad, $cy, $iw);
        $pdf->SetFont($this->face($channel), 'B', $cs);
        $pdf->SetTextColor(...self::INK);
        $pdf->SetXY($x + $pad, $cy + 3.1);
        $pdf->MultiCell($iw, $cs * 0.3528 * 1.15, $channel, 0, 'L', false, 1, null, null, true, 0, false, true, 8.4, 'T', true);
        $cy += 3.1 + $chanH;
        if ($address !== '') {
            $pdf->SetFont($this->face($address), '', 6.8);
            $pdf->SetTextColor(...self::MUTED);
            $pdf->SetXY($x + $pad, $cy + 1.0);
            $pdf->MultiCell($iw, 3.0, $address, 0, 'L', false, 1, null, null, true, 0, false, true, 7.4, 'T', true);
            $cy += 1.0 + $addrH;
        }
        $cy += 2.2 + $sp * 0.25;
        // Délai et référence à citer, côte à côte.
        $half = ($iw - 3.0) / 2;
        $delay = mb_strtoupper(SseAlertReportTo::delayLabel((string) $r['delay'], $lang), 'UTF-8');
        foreach ([[$this->v('delay'), $delay, $x + $pad], [$this->v('quote'), (string) $this->d['ref'], $x + $pad + $half + 3.0]] as [$label, $value, $cx]) {
            $both = mb_strtoupper($label[$this->lang] . ' / ' . $label[1 - $this->lang], 'UTF-8');
            $pdf->SetFont($this->sans, '', 5.6);
            $pdf->setFontSpacing(0.25);
            $fits = $pdf->GetStringWidth($both) <= $half;
            $pdf->setFontSpacing(0);
            $this->artCaption($fits ? $label[$this->lang] . ' / ' . $label[1 - $this->lang] : $label[$this->lang], $cx, $cy, $half, 'L', 5.6);
            [$value, $vs] = $this->artFit($value, $half, 10.2, 6.8, 'B', $this->face($value));
            $pdf->SetFont($this->face($value), 'B', $vs);
            $pdf->SetTextColor(...self::INK);
            $pdf->SetXY($cx, $cy + 3.1);
            $pdf->Cell($half, 5.0, $value, 0, 0, 'L');
        }
        $cy += 3.1 + 5.6 + $sp * 0.25;
        if ($qr > 0) {
            $pdf->SetDrawColor(...self::LINE);
            $pdf->Line($x + $pad, $cy, $x + $w - $pad, $cy);
            $cy += 3.0;
            $this->artQr($url, $x + $pad, $cy, $qr);
            $tx = $x + $pad + $qr + 3.2;
            $tw = $iw - $qr - 3.2;
            $this->artCaption($this->v('online')[$this->lang], $tx, $cy, $tw, 'L', 5.8);
            // Adresse en clair, coupée aux « / » : domaine, chemin, jeton (jamais au milieu d'un mot), au plus gros qui tient.
            $parts = self::urlLines($url);
            $us = 6.8;
            foreach ($parts as $k => $part) {
                [, $one] = $this->artFit($part, $tw, 6.8, 4.4, $k === 2 ? 'B' : '', AthenaPdfTheme::mono());
                $us = min($us, $one);
            }
            $ly = $cy + 4.4;
            foreach ($parts as $k => $part) {
                [$part, $us2] = $this->artFit($part, $tw, $us, 4.4, $k === 2 ? 'B' : '', AthenaPdfTheme::mono());
                $pdf->SetFont(AthenaPdfTheme::mono(), $k === 2 ? 'B' : '', $us2);
                $pdf->SetTextColor(...self::INK);
                $pdf->SetXY($tx, $ly);
                $pdf->Cell($tw, 3.2, $part, 0, 0, 'L');
                $ly += 3.7;
            }
        }
        $pdf->SetLineWidth(0.25);

        return $y + max($h, $natural);
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

    // ------------------------------------------------------------ Motif

    /**
     * Motif : motifs codifiés en étiquettes (code et libellé), puis le texte, et le comportement observé s'il tient à côté.
     *
     * @param array<string, mixed> $a
     */
    private function vReasonBlock(array $a, float $maxText, bool $withBehaviour): void
    {
        $pdf = $this->pdf;
        $l = self::MARGIN;
        $text = trim((string) ($this->d['texts']['reason'] ?? ''));
        $top = $this->y;
        $this->vHead($this->v('reason'), $l, self::CONTENT_W, $top);
        $y = $top + 9.0;
        $x = $l;
        foreach ($a['reasons'] as $r) {
            $code = (string) ($r['code'] ?? '');
            $label = $this->plain((string) ($r[$this->lang === 1 ? 'en' : 'fr'] ?? ''));
            $pdf->SetFont(AthenaPdfTheme::mono(), 'B', 7.2);
            $cw = $pdf->GetStringWidth($code) + 3.2;
            $pdf->SetFont($this->sans, 'B', 8.4);
            $lw = min(100.0, $pdf->GetStringWidth($label) + 4.0);
            if ($x + $cw + $lw > $l + self::CONTENT_W && $x > $l) {
                $x = $l;
                $y += 6.4;
            }
            $pdf->SetFillColor(...self::INK);
            $pdf->Rect($x, $y, $cw, 5.0, 'F');
            $pdf->SetDrawColor(...self::INK);
            $pdf->SetLineWidth(0.3);
            $pdf->Rect($x, $y, $cw + $lw, 5.0, 'D');
            $pdf->SetFont(AthenaPdfTheme::mono(), 'B', 7.2);
            $pdf->SetTextColor(255, 255, 255);
            $pdf->SetXY($x, $y + 0.8);
            $pdf->Cell($cw, 3.5, $code, 0, 0, 'C');
            [$label, $ls] = $this->artFit($label, $lw - 3.0, 8.4, 6.6);
            $pdf->SetFont($this->face($label), 'B', $ls);
            $pdf->SetTextColor(...self::INK);
            $pdf->SetXY($x + $cw + 1.8, $y + 0.6);
            $pdf->Cell($lw - 2.0, 3.9, $label, 0, 0, 'L');
            $x += $cw + $lw + 2.6;
        }
        if ($a['reasons'] !== []) {
            $y += 7.0;
        }
        if ($text !== '') {
            $y += $this->vText($text, $y, $maxText, 'reason');
        }
        $behaviour = trim((string) ($this->d['texts']['behaviour'] ?? ''));
        if ($withBehaviour && $behaviour !== '') {
            $plan = $this->vTextPlan($behaviour, 16.0);
            if (!$plan['clipped']) {
                // Comportement observé : à la suite du motif, sous un simple libellé de champ (pas de rubrique de plus).
                $this->vBehaviourDone = true;
                $y += 1.6;
                $this->artCaption($this->v('behaviour')[$this->lang] . ' / ' . $this->v('behaviour')[1 - $this->lang], $l, $y, self::CONTENT_W);
                $y += 3.8;
                $y += $this->vText($behaviour, $y, 16.0, 'behaviour');
            }
        }
        $this->y = max($top + 9.0, $y - 1.6);
    }

    /** @return array{html: string, size: float, h: float, clipped: bool} réduit le corps jusqu'à tenir dans $maxH, sinon coupe */
    private function vTextPlan(string $text, float $maxH): array
    {
        $key = md5($text) . '|' . $maxH;
        if (isset($this->vPlans[$key])) {
            return $this->vPlans[$key];
        }
        $html = SseSheetText::toHtml($text);
        $w = self::CONTENT_W;
        $size = 9.4;
        $h = $this->htmlHeight($html, $w, $size);
        for ($s = $size; $h > $maxH && $s > 7.8; $s -= 0.4) {
            $size = $s - 0.4;
            $h = $this->htmlHeight($html, $w, $size);
        }

        return $this->vPlans[$key] = ['html' => $html, 'size' => $size, 'h' => $h, 'clipped' => $h > $maxH];
    }

    /** Texte rédigé dans une hauteur bornée ; retourne la hauteur utilisée. Le texte coupé est donné en entier en annexe. */
    private function vText(string $text, float $y, float $maxH, string $key): float
    {
        $pdf = $this->pdf;
        $l = self::MARGIN;
        $w = self::CONTENT_W;
        $plan = $this->vTextPlan($text, $maxH);
        $pdf->SetFont($this->sans, '', $plan['size']);
        $pdf->SetTextColor(...self::INK);
        if ($plan['clipped']) {
            $this->vReasonInAnnex = $this->vReasonInAnnex || $key === 'reason';
            $lineH = $plan['size'] * 0.3528 * 1.28;
            $clip = max($lineH, floor(($maxH - 4.0) / $lineH) * $lineH) + 0.5;
            if ($this->vTrialing) {
                return $clip + 3.4;
            }
            $pdf->StartTransform();
            $pdf->Rect($l, $y, $w, $clip, 'CNZ');
            $pdf->writeHTMLCell($w, 0, $l, $y, $this->wrapHtml($plan['html'], $plan['size']), 0, 1, false, true, 'L', true);
            $pdf->StopTransform();
            $pdf->SetFont($this->sans, 'I', 7.0);
            $pdf->SetTextColor(...self::MUTED);
            $pdf->SetXY($l, $y + $clip + 0.2);
            $pdf->Cell($w, 3.2, '… ' . $this->v('cont')[$this->lang], 0, 0, 'R');

            return $clip + 3.4;
        }
        if (!$this->vTrialing) {
            $pdf->writeHTMLCell($w, 0, $l, $y, $this->wrapHtml($plan['html'], $plan['size']), 0, 1, false, true, 'L', true);
        }

        return $plan['h'] + 1.0;
    }

    // ------------------------------------------------------------ Dernier lieu connu

    /** @return list<string> dernier lieu connu, date, zone (le code de grille est présenté « Grille 151 173 ») */
    private function lastKnownLines(): array
    {
        $f = $this->facts();
        $when = (string) $f['last_seen_at'];
        $when = str_starts_with($when, self::BLOCK) ? $when : ($when !== '' ? $this->dateOrRaw(substr($when, 0, 10)) : '');
        $places = array_map(fn (string $p): string => $this->gridLabel(trim($p)), explode(' · ', (string) $f['last_seen_place']));

        return array_values(array_filter(array_merge($places, [$when, (string) $f['zone'] !== '' ? $this->t('zone')[$this->lang] . ' : ' . $f['zone'] : '']), static fn (string $v): bool => trim($v) !== ''));
    }

    /** Un code de grille nu (« 151173 ») devient « Grille 151 173 » ; tout autre lieu reste tel quel. */
    private function gridLabel(string $place): string
    {
        if (preg_match('/^\d{4,10}$/', $place) !== 1) {
            return $place;
        }
        $half = (int) ceil(strlen($place) / 2);

        return $this->v('grid')[$this->lang] . ' ' . substr($place, 0, $half) . ' ' . substr($place, $half);
    }

    // ------------------------------------------------------------ Validité

    /**
     * Validité, en pied de la première page : émise le, valable jusqu'au, « PÉRISSABLE » si l'échéance est proche, émetteur.
     *
     * @param array<string, mixed> $a
     */
    private function validityStrip(array $a): void
    {
        $pdf = $this->pdf;
        $pdf->setPage(1 + $this->off);
        $d = $this->d;
        $l = self::MARGIN;
        $y = self::V_STRIP_Y;
        $h = self::V_STRIP_H;
        $pdf->SetFillColor(...self::PAPER);
        $pdf->Rect($l, $y, self::CONTENT_W, $h, 'F');
        $pdf->SetDrawColor(...self::INK);
        $pdf->SetLineWidth(0.5);
        $pdf->Line($l, $y, $l + self::CONTENT_W, $y);
        $pdf->SetLineWidth(0.15);
        $pdf->SetDrawColor(...self::LINE);
        $pdf->Line($l, $y + $h, $l + self::CONTENT_W, $y + $h);
        $cell = function (float $x, float $w, array $label, string $value) use ($pdf, $y): void {
            $this->artCaption($label[$this->lang] . ' / ' . $label[1 - $this->lang], $x + 3.0, $y + 1.5, $w - 4.0, 'L', 5.8);
            [$value, $s] = $this->artFit($value, $w - 5.0, 10.2, 7.0, 'B', $this->face($value));
            $pdf->SetFont($this->face($value), 'B', $s);
            $pdf->SetTextColor(...self::INK);
            $pdf->SetXY($x + 3.0, $y + 4.6);
            $pdf->Cell($w - 4.0, 4.6, $value, 0, 0, 'L');
        };
        $until = (string) ($d['valid_until'] ?? '');
        $tagW = !empty($a['perishable']) ? 34.0 : 0.0;
        $w1 = 46.0;
        $w2 = 52.0;
        $cell($l, $w1, $this->v('issued'), $this->date((string) $d['issued_at']));
        $pdf->SetDrawColor(...self::LINE);
        $pdf->SetLineWidth(0.2);
        $pdf->Line($l + $w1, $y + 1.8, $l + $w1, $y + $h - 1.8);
        $exact = (string) ($d['expires_at'] ?? '');
        $validText = !empty($d['until_lifted']) ? ($this->lang === 0 ? 'Jusqu’à levée' : 'Until lifted')
            : ($exact !== '' ? $this->date(substr($exact, 0, 10)) . ' ' . substr($exact, 11, 5) . ' UTC' : ($until !== '' ? $this->date($until) : '—'));
        $cell($l + $w1, $w2, $this->v('valid'), $validText);
        $x = $l + $w1 + $w2;
        if ($tagW > 0) {
            // Échéance proche : cartouche hachuré « PÉRISSABLE ».
            $this->artHatch($x + 1.0, $y + 1.4, $tagW - 2.0, $h - 2.8, [16, 18, 22], [242, 194, 0], 3.0);
            $pdf->SetFillColor(255, 255, 255);
            $pdf->Rect($x + 3.0, $y + 3.0, $tagW - 6.0, $h - 6.0, 'F');
            $pdf->SetFont($this->sans, 'B', 9.0);
            $pdf->SetTextColor(16, 18, 22);
            $pdf->SetXY($x + 3.0, $y + 3.4);
            $pdf->Cell($tagW - 6.0, 4.0, $this->v('perishable')[$this->lang], 0, 0, 'C');
            $x += $tagW;
        }
        $pdf->SetDrawColor(...self::LINE);
        $pdf->SetLineWidth(0.2);
        $pdf->Line($x, $y + 1.8, $x, $y + $h - 1.8);
        $issuer = trim((string) ($d['issuer'] ?? ''));
        $cell($x, $l + self::CONTENT_W - $x, $this->v('issued_by'), $issuer !== '' ? $issuer : ((string) $d['service'] !== '' ? (string) $d['service'] : (string) $d['authority'][$this->lang]));
        $pdf->SetLineWidth(0.25);
        $pdf->setPage($this->pages + $this->off);
        if ($this->pages === 1) {
            $this->y = self::BOTTOM;
        }
    }

    /** Annexe (page 2, seulement s'il y a de quoi) : ce qui n'a pas tenu, comportement observé, commentaires, adresses, téléphones et courriels. */
    private function annex(): void
    {
        $f = $this->facts();
        $texts = (array) ($this->d['texts'] ?? []);
        $addresses = (array) $f['links']['address'];
        $phones = array_merge((array) $f['links']['phone'], (array) $f['links']['email']);
        $behaviour = $this->vBehaviourDone ? '' : trim((string) ($texts['behaviour'] ?? ''));
        $mention = trim((string) ($texts['mention'] ?? ''));
        if ($this->vAddressesDone && count($addresses) <= 3) {
            $addresses = [];
        }
        if ($this->vPhonesDone) {
            $phones = [];
        }
        $lists = array_values(array_filter([['addresses', $addresses], ['phones', $phones]], static fn (array $l): bool => $l[1] !== []));
        $deferred = $this->vReasonInAnnex;
        if (!$deferred && $behaviour === '' && $mention === '' && $lists === []) {
            return;
        }
        // Tout tient sous la première page (au-dessus du cartouche de validité) : pas de seconde page pour si peu.
        $need = 0.0;
        foreach ([$behaviour, $mention] as $t) {
            $need += $t !== '' ? $this->htmlHeight(SseSheetText::toHtml($t), self::CONTENT_W - 8.0, 9.2) + 15.0 : 0.0;
        }
        $need += $lists !== [] ? 9.0 + max(array_map(fn (array $l): float => $this->listHeight($l[1], (self::CONTENT_W - 6.0) / 2 - 5.0), $lists)) : 0.0;
        $onFirst = !$deferred && $this->y + $need <= self::V_LIMIT && $this->d['faces'] !== '2';
        if (!$onFirst) {
            if ($this->d['faces'] === '1') {
                $this->overflow[] = 'annex';

                return;
            }
            if (!$this->newPage()) {
                return;
            }
        }
        if ($this->vReasonMoved) {
            $this->vReasonBlock($this->alert(), 120.0, false);
            $this->y += 4.0;
        } elseif ($this->vReasonInAnnex) {
            $this->textSection('reason', false);
        }
        $this->textSection('behaviour', false);
        $this->twoLists($lists);
        $this->none = [];
        $this->textSection('mention', false);
    }
}
