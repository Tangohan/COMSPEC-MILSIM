<?php

declare(strict_types=1);

namespace App\Support\Pdf;

/**
 * Certificat pleine page A4 portrait (brevets de qualification, attestations de formation),
 * au dessin de la maquette « Modèles PDF · Athena » :
 * double cadre (filet noir + filet d'accent), en-tête propre (« ATHENA. » + communauté à gauche,
 * référence en mono à droite), bloc central (surtitre, titre, titulaire, intitulé, description,
 * trois petites colonnes), deux signatures de part et d'autre d'un sceau circulaire,
 * ligne de vérification en bas.
 *
 * Le gabarit en-tête / bandeau / pied standard d'{@see AthenaPdfTheme} n'est volontairement pas utilisé.
 * Deux moteurs : {@see self::dompdfHtml()} + {@see self::dompdfRender()} et {@see self::tcpdf()} (repli).
 *
 * Données ($d), toutes facultatives sauf holder / title :
 *  - variant      : 'classique' (maquette) ou 'moderne' (bandeau d'accent, colonnes teintées, sceau plein)
 *  - accent, ink  : couleurs #rrggbb (défaut : vert Athena, encre)
 *  - community    : nom de la communauté ; logo : chemin local PNG/JPEG/SVG affiché avant « ATHENA. »
 *  - reference    : référence en haut à droite (mono gras) et dans la ligne de vérification
 *  - kicker, title, subtitle, awarded_intro, holder, holder_line, reason, subject, subject_sub, description
 *  - fields       : list<array{0: label, 1: valeur, 2?: bool mono}> (3 ou 4 colonnes)
 *  - signatures   : list<array{name: string, role: string, art_svg?: string}> (2 : gauche / droite)
 *  - seal_image   : chemin local ou data URI d'un insigne (remplace la lettre) ; seal_letter ; seal_label
 *  - background   : chemin local d'une image de fond (filigrane)
 *  - verify       : ligne de vérification ; legal : mention légale ; status : array{0: texte, 1: fond, 2: texte}
 */
final class AthenaCertificateLayout
{
    /** Géométrie commune aux deux moteurs (mm). */
    private const OUTER = 9.0;
    private const INNER = 13.0;
    private const SIDE = 24.0;
    private const SEAL_D = 34.0;
    private const SEAL_Y = 222.0;
    private const SIGN_Y = 246.0;

    /**
     * @param array<string, mixed> $d
     * @return array<string, mixed>
     */
    public static function normalize(array $d): array
    {
        $s = static fn (string $k): string => trim((string) ($d[$k] ?? ''));
        $community = $s('community');
        $seal = $s('seal_label') !== '' ? $s('seal_label') : self::shortName($community !== '' ? $community : 'Athena');
        $letter = $s('seal_letter');
        if (!preg_match('/\p{L}/u', $letter)) {
            // Première lettre (pas un chiffre) de la communauté : « 75th Ranger » → « R ».
            $letter = preg_match('/\p{L}/u', $community, $m) ? $m[0] : 'A';
        }
        $letter = mb_substr($letter, 0, 1, 'UTF-8');
        $fields = [];
        foreach ((array) ($d['fields'] ?? []) as $f) {
            if (is_array($f) && trim((string) ($f[0] ?? '')) !== '') {
                $fields[] = [(string) $f[0], trim((string) ($f[1] ?? '')) !== '' ? (string) $f[1] : '—', !empty($f[2])];
            }
        }
        $sigs = [];
        foreach ((array) ($d['signatures'] ?? []) as $g) {
            if (is_array($g)) {
                $sigs[] = ['name' => trim((string) ($g['name'] ?? '')), 'role' => trim((string) ($g['role'] ?? '')), 'art_svg' => (string) ($g['art_svg'] ?? '')];
            }
        }
        $status = isset($d['status']) && is_array($d['status']) && trim((string) ($d['status'][0] ?? '')) !== ''
            ? [(string) $d['status'][0], (string) ($d['status'][1] ?? AthenaPdfTheme::ACCENT_SOFT), (string) ($d['status'][2] ?? AthenaPdfTheme::ACCENT_DARK)]
            : null;

        return [
            'variant' => $s('variant') === 'moderne' ? 'moderne' : 'classique',
            'accent' => self::hex($s('accent'), AthenaPdfTheme::ACCENT),
            'ink' => self::hex($s('ink'), AthenaPdfTheme::INK),
            'community' => $community,
            'logo' => $s('logo'),
            'reference' => $s('reference'),
            'kicker' => self::upper($s('kicker')),
            'title' => self::upper($s('title') !== '' ? $s('title') : 'Certificat'),
            'subtitle' => $s('subtitle'),
            'awarded_intro' => $s('awarded_intro') !== '' ? $s('awarded_intro') : 'est décerné à',
            'holder' => $s('holder'),
            'holder_line' => $s('holder_line'),
            'reason' => $s('reason'),
            'subject' => self::upper($s('subject')),
            'subject_sub' => $s('subject_sub'),
            'description' => $s('description'),
            'fields' => $fields,
            'signatures' => $sigs,
            'seal_image' => $s('seal_image'),
            'seal_letter' => self::upper($letter),
            'seal_label' => self::upper($seal),
            'background' => $s('background'),
            'verify' => $s('verify'),
            'legal' => $s('legal'),
            'status' => $status,
        ];
    }

    /** « Vérifiable sur hôte · code XXX » (hôte du site si connu). */
    public static function verificationLine(string $code, string $community = ''): string
    {
        $host = '';
        if (function_exists('url')) {
            try {
                $host = (string) (parse_url((string) url('/'), PHP_URL_HOST) ?? '');
            } catch (\Throwable) {
                $host = '';
            }
        }
        $where = $host !== '' ? 'Vérifiable sur ' . $host : 'Vérifiable auprès de ' . ($community !== '' ? $community : 'la communauté');
        if ($host !== '' && $community !== '') {
            $where .= ' auprès de ' . $community;
        }

        return trim($code) !== '' ? $where . ' · code ' . trim($code) : $where;
    }

    /** Nom court pour le sceau (sigle des initiales si le nom est long). */
    public static function shortName(string $name): string
    {
        $name = trim($name);
        if (mb_strlen($name, 'UTF-8') <= 14) {
            return $name;
        }
        $words = preg_split('/[\s\-_]+/u', $name, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $initials = '';
        foreach ($words as $w) {
            if (mb_strlen($w, 'UTF-8') > 2) {
                $initials .= mb_substr($w, 0, 1, 'UTF-8');
            }
        }

        return mb_strlen($initials, 'UTF-8') >= 2 ? $initials : mb_substr($name, 0, 14, 'UTF-8');
    }

    // ── Dompdf ──────────────────────────────────────────────────────────────

    /** @param array<string, mixed> $data */
    public static function dompdfHtml(array $data): string
    {
        $d = self::normalize($data);
        $e = [AthenaPdfTheme::class, 'e'];
        $a = $d['accent'];
        $ink = $d['ink'];
        $muted = AthenaPdfTheme::MUTED;
        $modern = $d['variant'] === 'moderne';
        $o = self::OUTER;
        $i = self::INNER;
        $side = self::SIDE;

        $bg = '';
        $bgUri = $d['background'] !== '' ? self::dataUri($d['background']) : null;
        if ($bgUri !== null) {
            $bg = '<div class="bg" style="background-image:url(' . $bgUri . ');"></div>';
        }
        $logoUri = $d['logo'] !== '' ? self::dataUri($d['logo']) : null;
        $logo = $logoUri !== null ? '<img class="logo" src="' . $logoUri . '" alt="">' : '';

        $head = '<table class="head"><tr><td class="head-l">' . $logo
            . '<span class="word-box"><span class="word">ATHENA<span style="color:' . $a . ';">.</span></span>'
            . ($d['community'] !== '' ? '<br><span class="com">' . $e($d['community']) . '</span>' : '') . '</span></td>'
            . '<td class="head-r">' . ($d['reference'] !== '' ? '<span class="ref">' . $e($d['reference']) . '</span>' : '') . '</td></tr></table>';

        $main = ($d['kicker'] !== '' ? '<div class="kicker">' . $e($d['kicker']) . '</div>' : '')
            . '<div class="title">' . $e($d['title']) . '</div>'
            . ($d['subtitle'] !== '' ? '<div class="subtitle">' . $e($d['subtitle']) . '</div>' : '')
            . '<div class="rule"><span></span></div>'
            . '<div class="intro">' . $e($d['awarded_intro']) . '</div>'
            . '<div class="holder">' . $e($d['holder']) . '</div>'
            . ($d['holder_line'] !== '' ? '<div class="hline">' . $e($d['holder_line']) . '</div>' : '')
            . ($d['reason'] !== '' ? '<div class="reason">' . $e($d['reason']) . '</div>' : '')
            . ($d['subject'] !== '' ? '<div class="subject">' . $e($d['subject']) . '</div>' : '')
            . ($d['subject_sub'] !== '' ? '<div class="ssub">' . $e($d['subject_sub']) . '</div>' : '')
            . ($d['description'] !== '' ? '<div class="desc">' . nl2br($e($d['description'])) . '</div>' : '');

        if ($d['fields'] !== []) {
            $cells = '';
            $w = number_format(100 / count($d['fields']), 2, '.', '');
            foreach ($d['fields'] as $f) {
                $cells .= '<td style="width:' . $w . '%;"><div class="lbl">' . $e(self::upper($f[0])) . '</div>'
                    . '<div class="val' . ($f[2] ? ' mono' : '') . '">' . $e($f[1]) . '</div></td>';
            }
            $main .= '<table class="fields"><tr>' . $cells . '</tr></table>';
        }
        if ($d['status'] !== null) {
            $main .= '<div class="status-row"><span class="status" style="background:' . $e($d['status'][1]) . ';color:' . $e($d['status'][2]) . ';">' . $e(self::upper($d['status'][0])) . '</span></div>';
        }

        $sealInner = self::sealImageUri($d['seal_image']);
        $sealContent = $sealInner !== null
            ? '<img class="seal-img" src="' . $sealInner . '" alt="">'
            : '<div class="seal-letter">' . $e($d['seal_letter']) . '</div><div class="seal-label">' . $e($d['seal_label']) . '</div>';
        $sealTop = self::SEAL_Y;
        $seal = '<div class="seal"><div class="seal-in">' . $sealContent . '</div></div>';

        $signs = '';
        foreach (array_slice($d['signatures'], 0, 2) as $k => $g) {
            $art = $g['art_svg'] !== '' ? '<img class="sig-art" src="data:image/svg+xml;base64,' . base64_encode($g['art_svg']) . '" alt="">' : '';
            $signs .= '<div class="sign ' . ($k === 0 ? 'sign-l' : 'sign-r') . '"><div class="sig-art-box">' . $art . '</div><div class="sig-line"></div>'
                . '<div class="sig-name">' . $e($g['name']) . '</div>'
                . ($g['role'] !== '' ? '<div class="sig-role">' . $e($g['role']) . '</div>' : '') . '</div>';
        }

        $foot = ($d['legal'] !== '' ? '<div class="legal">' . nl2br($e($d['legal'])) . '</div>' : '')
            . ($d['verify'] !== '' ? '<div class="verify">' . $e($d['verify']) . '</div>' : '');

        $frameInner = $modern
            ? '<div class="band"></div><div class="frame-in" style="border-color:' . AthenaPdfTheme::LINE . ';"></div>'
            : '<div class="frame-in"></div>';
        $signW = 54;
        $sealD = self::SEAL_D;
        $fieldsBg = $modern ? 'background:' . self::tint($a, 0.08) . ';border:0;' : 'border-top:0.5pt solid ' . AthenaPdfTheme::LINE . ';';

        $css = '@page { size: A4 portrait; margin: 0; }
            * { margin: 0; padding: 0; }
            html, body { margin: 0; padding: 0; }
            body { font-family: "DejaVu Sans", sans-serif; color: ' . $ink . '; font-size: 9pt; }
            .page { position: absolute; left: 0; top: 0; width: 210mm; height: 290mm; overflow: hidden; }
            .bg { position: absolute; left: ' . $i . 'mm; top: ' . $i . 'mm; right: ' . $i . 'mm; bottom: ' . ($i - 1) . 'mm; background-size: cover; background-position: center; background-repeat: no-repeat; opacity: 0.28; }
            .frame-out { position: absolute; left: ' . $o . 'mm; top: ' . $o . 'mm; width: ' . (210 - 2 * $o) . 'mm; height: ' . (296 - 2 * $o) . 'mm; border: 1.2pt solid ' . ($modern ? $a : $ink) . '; }
            .frame-in { position: absolute; left: ' . $i . 'mm; top: ' . $i . 'mm; width: ' . (210 - 2 * $i) . 'mm; height: ' . (296 - 2 * $i) . 'mm; border: 0.5pt solid ' . $a . '; }
            .band { position: absolute; left: ' . $o . 'mm; top: ' . $o . 'mm; width: ' . (210 - 2 * $o) . 'mm; height: 2.4mm; background: ' . $a . '; }
            .head { position: absolute; left: ' . $side . 'mm; top: 22mm; width: ' . (210 - 2 * $side) . 'mm; border-collapse: collapse; }
            .head td { vertical-align: top; padding: 0; }
            .head-r { text-align: right; padding-top: 1.2mm; }
            .logo { height: 12mm; max-width: 26mm; margin-right: 3mm; vertical-align: top; }
            .word-box { display: inline-block; vertical-align: top; }
            .word { font-size: 17pt; font-weight: bold; font-style: italic; letter-spacing: -0.3pt; color: ' . $ink . '; }
            .com { font-size: 7.5pt; color: ' . $muted . '; }
            .ref { font-family: "DejaVu Sans Mono", monospace; font-size: 9.5pt; font-weight: bold; color: ' . $ink . '; }
            .main { position: absolute; left: ' . $side . 'mm; top: 60mm; width: ' . (210 - 2 * $side) . 'mm; text-align: center; }
            .kicker { font-size: 8pt; font-weight: bold; letter-spacing: 2.6pt; color: ' . $a . '; margin-bottom: 4mm; }
            .title { font-size: 31pt; font-weight: bold; font-style: italic; line-height: 1.04; letter-spacing: -0.4pt; color: ' . $ink . '; padding: 0 6mm; }
            .subtitle { font-size: 9.5pt; font-style: italic; color: ' . $muted . '; margin-top: 2.5mm; }
            .rule { margin: 6mm 0 6mm; }
            .rule span { display: inline-block; width: 16mm; height: 1.4pt; background: ' . $a . '; }
            .intro { font-size: 10pt; color: ' . $muted . '; }
            .holder { font-size: 30pt; font-weight: bold; line-height: 1.12; margin-top: 2.5mm; color: ' . $ink . '; }
            .hline { font-size: 8.5pt; font-weight: bold; letter-spacing: 0.6pt; margin-top: 2mm; color: ' . $ink . '; }
            .reason { font-size: 10pt; color: ' . $muted . '; margin-top: 7mm; }
            .subject { font-size: 15pt; font-weight: bold; line-height: 1.2; letter-spacing: 0.3pt; margin-top: 2.5mm; color: ' . $ink . '; }
            .ssub { font-size: 9pt; font-weight: bold; color: ' . $a . '; margin-top: 1.5mm; }
            .desc { font-size: 8.5pt; line-height: 1.5; color: ' . $muted . '; margin: 3mm 14mm 0; }
            .fields { width: ' . ($modern ? 146 : 132) . 'mm; margin: 8mm auto 0; border-collapse: collapse; ' . $fieldsBg . ' }
            .fields td { text-align: center; vertical-align: top; padding: 3.2mm 2mm 2.6mm; }
            .lbl { font-size: 6pt; font-weight: bold; letter-spacing: 1.2pt; color: ' . $muted . '; }
            .val { font-size: 9.5pt; font-weight: bold; margin-top: 1.4mm; color: ' . $ink . '; }
            .mono { font-family: "DejaVu Sans Mono", monospace; font-size: 8.5pt; }
            .status-row { margin-top: 3.5mm; }
            .status { font-size: 6.5pt; font-weight: bold; letter-spacing: 1pt; padding: 1mm 3mm; }
            .seal { position: absolute; left: ' . ((210 - $sealD) / 2) . 'mm; top: ' . $sealTop . 'mm; width: ' . $sealD . 'mm; height: ' . $sealD . 'mm; border: 1.1pt solid ' . $a . '; border-radius: ' . ($sealD / 2) . 'mm; ' . ($modern ? 'background: ' . $a . ';' : '') . ' }
            .seal-in { position: absolute; left: 2.2mm; top: 2.2mm; width: ' . ($sealD - 4.4 - 0.6) . 'mm; height: ' . ($sealD - 4.4 - 0.6) . 'mm; border: 0.9pt dotted ' . ($modern ? '#ffffff' : $a) . '; border-radius: ' . (($sealD - 5) / 2) . 'mm; text-align: center; }
            .seal-letter { font-size: 25pt; font-weight: bold; font-style: italic; line-height: 1; margin-top: 6.2mm; color: ' . ($modern ? '#ffffff' : $a) . '; }
            .seal-label { font-size: 5.2pt; font-weight: bold; letter-spacing: 1pt; margin-top: 1.2mm; color: ' . ($modern ? '#ffffff' : $a) . '; }
            .seal-img { width: 19mm; height: 19mm; margin-top: 5.5mm; }
            .sign { position: absolute; top: ' . (self::SIGN_Y - 12) . 'mm; width: ' . $signW . 'mm; text-align: center; }
            .sign-l { left: ' . $side . 'mm; }
            .sign-r { left: ' . (210 - $side - $signW) . 'mm; }
            .sig-art-box { height: 11mm; }
            .sig-art { width: 40mm; height: 10mm; }
            .sig-line { border-top: 0.7pt solid ' . $ink . '; margin: 1mm 0 1.6mm; }
            .sig-name { font-size: 8.5pt; font-weight: bold; color: ' . $ink . '; }
            .sig-role { font-size: 7pt; color: ' . $muted . '; margin-top: 0.6mm; }
            .legal { position: absolute; left: ' . $side . 'mm; top: 266mm; width: ' . (210 - 2 * $side) . 'mm; text-align: center; font-size: 6.3pt; line-height: 1.4; color: ' . $muted . '; }
            .verify { position: absolute; left: ' . $side . 'mm; top: 275mm; width: ' . (210 - 2 * $side) . 'mm; text-align: center; font-size: 6.8pt; color: ' . $muted . '; }';

        return '<!DOCTYPE html><html lang="fr"><head><meta charset="UTF-8"><title>' . $e($d['title']) . '</title><style>' . $css . '</style></head><body><div class="page">'
            . $bg . '<div class="frame-out"></div>' . $frameInner . $head
            . '<div class="main">' . $main . '</div>'
            . $signs . $seal . $foot
            . '</div></body></html>';
    }

    /** Rendu Dompdf A4 portrait d'un certificat ; null si Dompdf est absent. */
    public static function dompdfRender(string $html): ?string
    {
        if (!class_exists(\Dompdf\Dompdf::class)) {
            return null;
        }
        $root = function_exists('base_path') ? (realpath(base_path()) ?: base_path()) : (string) getcwd();
        $dompdf = new \Dompdf\Dompdf([
            'isRemoteEnabled' => false,
            'isHtml5ParserEnabled' => true,
            'defaultFont' => 'DejaVu Sans',
            'chroot' => $root,
        ]);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();
        $out = $dompdf->output();

        return is_string($out) && $out !== '' ? $out : null;
    }

    // ── TCPDF (repli) ───────────────────────────────────────────────────────

    /**
     * Même dessin avec TCPDF (le chargement de TCPDF est à la charge de l'appelant).
     * Les images (logo, sceau, fond) doivent être des chemins JPEG/PNG sûrs pour TCPDF.
     *
     * @param array<string, mixed> $data
     */
    public static function tcpdf(array $data): ?string
    {
        if (!class_exists(\TCPDF::class, false) && !class_exists(\TCPDF::class)) {
            return null;
        }
        $d = self::normalize($data);
        $sans = AthenaPdfTheme::sans();
        $mono = AthenaPdfTheme::mono();
        $a = AthenaPdfTheme::rgb($d['accent']);
        $ink = AthenaPdfTheme::rgb($d['ink']);
        $muted = AthenaPdfTheme::rgb(AthenaPdfTheme::MUTED);
        $modern = $d['variant'] === 'moderne';

        $pdf = new \TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetCreator('Athena');
        $pdf->SetTitle($d['title']);
        $pdf->SetMargins(0, 0, 0);
        $pdf->SetAutoPageBreak(false);
        $pdf->setCellHeightRatio(1.15);
        $pdf->setFontSubsetting(true);
        $pdf->AddPage('P', 'A4');
        $W = $pdf->getPageWidth();
        $H = $pdf->getPageHeight();
        $o = self::OUTER;
        $i = self::INNER;
        $side = self::SIDE;
        $cw = $W - 2 * $side;

        if ($d['background'] !== '' && is_file($d['background'])) {
            $pdf->Image($d['background'], $i, $i, $W - 2 * $i, $H - 2 * $i, '', '', '', false, 300, '', false, false, 0, false, false, false);
        }
        $pdf->SetLineWidth(0.42);
        $pdf->SetDrawColorArray($modern ? $a : $ink);
        $pdf->Rect($o, $o, $W - 2 * $o, $H - 2 * $o, 'D');
        $pdf->SetLineWidth(0.18);
        $pdf->SetDrawColorArray($modern ? AthenaPdfTheme::rgb(AthenaPdfTheme::LINE) : $a);
        $pdf->Rect($i, $i, $W - 2 * $i, $H - 2 * $i, 'D');
        if ($modern) {
            $pdf->SetFillColorArray($a);
            $pdf->Rect($o, $o, $W - 2 * $o, 2.4, 'F');
        }

        // En-tête : logo + ATHENA. + communauté | référence.
        $x = $side;
        if ($d['logo'] !== '' && is_file($d['logo'])) {
            $pdf->Image($d['logo'], $side, 22, 0, 12, '', '', '', true, 300);
            $x = $pdf->getImageRBX() + 3;
        }
        $pdf->SetTextColorArray($ink);
        $pdf->SetFont($sans, 'BI', 17);
        $pdf->SetXY($x, 21.5);
        $pdf->Cell($pdf->GetStringWidth('ATHENA'), 7, 'ATHENA', 0, 0, 'L');
        $pdf->SetTextColorArray($a);
        $pdf->Cell(4, 7, '.', 0, 0, 'L');
        if ($d['community'] !== '') {
            $pdf->SetFont($sans, '', 7.5);
            $pdf->SetTextColorArray($muted);
            $pdf->SetXY($x, 28.6);
            $pdf->Cell(100, 4, $d['community'], 0, 0, 'L', false, '', 1);
        }
        if ($d['reference'] !== '') {
            $pdf->SetFont($mono, 'B', 9.5);
            $pdf->SetTextColorArray($ink);
            $pdf->SetXY($W - $side - 90, 23);
            $pdf->Cell(90, 5, $d['reference'], 0, 0, 'R', false, '', 1);
        }

        $y = 60.0;
        $line = static function (string $text, string $style, float $size, array $rgb, float $h, float $before = 0.0, float $inset = 0.0) use ($pdf, $sans, $side, $cw, &$y): void {
            if ($text === '') {
                return;
            }
            $y += $before;
            $pdf->SetFont($sans, $style, $size);
            $pdf->SetTextColorArray($rgb);
            $pdf->SetXY($side + $inset, $y);
            $pdf->MultiCell($cw - 2 * $inset, $h, $text, 0, 'C', false, 1);
            $y = $pdf->GetY();
        };
        if ($d['kicker'] !== '') {
            $pdf->setFontSpacing(0.9);
            $line($d['kicker'], 'B', 8, $a, 4);
            $pdf->setFontSpacing(0);
            $y += 4;
        }
        $line($d['title'], 'BI', 31, $ink, 11.5, 0, 6);
        $line($d['subtitle'], 'I', 9.5, $muted, 4.5, 2.5);
        $y += 6;
        $pdf->SetFillColorArray($a);
        $pdf->Rect(($W - 16) / 2, $y, 16, 0.5, 'F');
        $y += 6;
        $line($d['awarded_intro'], '', 10, $muted, 4.5);
        $line($d['holder'], 'B', 30, $ink, 11.5, 2.5);
        $line($d['holder_line'], 'B', 8.5, $ink, 4, 2);
        $line($d['reason'], '', 10, $muted, 4.5, 7);
        $line($d['subject'], 'B', 15, $ink, 6.5, 2.5);
        $line($d['subject_sub'], 'B', 9, $a, 4.2, 1.5);
        $line($d['description'], '', 8.5, $muted, 4.3, 3, 14);

        if ($d['fields'] !== []) {
            $y += 8;
            $fw = $modern ? 146.0 : 132.0;
            $fx = ($W - $fw) / 2;
            $colW = $fw / count($d['fields']);
            if ($modern) {
                $pdf->SetFillColorArray(AthenaPdfTheme::rgb(self::tint($d['accent'], 0.08)));
                $pdf->Rect($fx, $y, $fw, 14, 'F');
            } else {
                $pdf->SetDrawColorArray(AthenaPdfTheme::rgb(AthenaPdfTheme::LINE));
                $pdf->SetLineWidth(0.18);
                $pdf->Line($fx, $y, $fx + $fw, $y);
            }
            foreach ($d['fields'] as $k => $f) {
                $pdf->setFontSpacing(0.35);
                $pdf->SetFont($sans, 'B', 6);
                $pdf->SetTextColorArray($muted);
                $pdf->SetXY($fx + $k * $colW, $y + 3);
                $pdf->Cell($colW, 3, self::upper($f[0]), 0, 0, 'C', false, '', 1);
                $pdf->setFontSpacing(0);
                $pdf->SetFont($f[2] ? $mono : $sans, 'B', $f[2] ? 8.5 : 9.5);
                $pdf->SetTextColorArray($ink);
                $pdf->SetXY($fx + $k * $colW, $y + 7);
                $pdf->Cell($colW, 4.5, $f[1], 0, 0, 'C', false, '', 1);
            }
            $y += 14;
        }
        if ($d['status'] !== null) {
            $pdf->SetFont($sans, 'B', 6.5);
            $label = self::upper($d['status'][0]);
            $sw = $pdf->GetStringWidth($label) + 6;
            $pdf->SetFillColorArray(AthenaPdfTheme::rgb($d['status'][1]));
            $pdf->SetTextColorArray(AthenaPdfTheme::rgb($d['status'][2]));
            $pdf->SetXY(($W - $sw) / 2, $y + 3.5);
            $pdf->Cell($sw, 4.5, $label, 0, 0, 'C', true);
        }

        // Sceau.
        $r = self::SEAL_D / 2;
        $cx = $W / 2;
        $cy = self::SEAL_Y + $r;
        $pdf->SetLineStyle(['width' => 0.4, 'dash' => 0, 'color' => $a]);
        $pdf->Circle($cx, $cy, $r, 0, 360, $modern ? 'DF' : 'D', [], $modern ? $a : []);
        $pdf->SetLineStyle(['width' => 0.25, 'dash' => '0.6,0.8', 'color' => $modern ? [255, 255, 255] : $a]);
        $pdf->Circle($cx, $cy, $r - 2.5, 0, 360, 'D');
        $pdf->SetLineStyle(['width' => 0.2, 'dash' => 0, 'color' => $ink]);
        $sealImg = $d['seal_image'] !== '' && !str_starts_with($d['seal_image'], 'data:') && is_file($d['seal_image']) ? $d['seal_image'] : null;
        $sealColor = $modern ? [255, 255, 255] : $a;
        if ($sealImg !== null) {
            $pdf->Image($sealImg, $cx - 9.5, $cy - 9.5, 19, 19, '', '', '', true, 300, '', false, false, 0, 'CM');
        } else {
            $pdf->SetFont($sans, 'BI', 25);
            $pdf->SetTextColorArray($sealColor);
            $pdf->SetXY($cx - $r, $cy - 8.5);
            $pdf->Cell(2 * $r, 10, $d['seal_letter'], 0, 0, 'C');
            $pdf->SetFont($sans, 'B', 5.2);
            $pdf->setFontSpacing(0.35);
            $pdf->SetXY($cx - $r + 3, $cy + 3);
            $pdf->Cell(2 * $r - 6, 3, $d['seal_label'], 0, 0, 'C', false, '', 1);
            $pdf->setFontSpacing(0);
        }

        // Signatures.
        $signW = 54.0;
        foreach (array_slice($d['signatures'], 0, 2) as $k => $g) {
            $sx = $k === 0 ? $side : $W - $side - $signW;
            if ($g['art_svg'] !== '') {
                try {
                    $pdf->ImageSVG('@' . $g['art_svg'], $sx + 7, self::SIGN_Y - 11, 40, 10);
                } catch (\Throwable) {
                    // Signature décorative facultative.
                }
            }
            $pdf->SetLineStyle(['width' => 0.25, 'dash' => 0, 'color' => $ink]);
            $pdf->Line($sx, self::SIGN_Y, $sx + $signW, self::SIGN_Y);
            $pdf->SetFont($sans, 'B', 8.5);
            $pdf->SetTextColorArray($ink);
            $pdf->SetXY($sx, self::SIGN_Y + 1.5);
            $pdf->Cell($signW, 4, $g['name'], 0, 0, 'C', false, '', 1);
            if ($g['role'] !== '') {
                $pdf->SetFont($sans, '', 7);
                $pdf->SetTextColorArray($muted);
                $pdf->SetXY($sx, self::SIGN_Y + 5.6);
                $pdf->Cell($signW, 3.5, $g['role'], 0, 0, 'C', false, '', 1);
            }
        }

        if ($d['legal'] !== '') {
            $pdf->SetFont($sans, '', 6.3);
            $pdf->SetTextColorArray($muted);
            $pdf->SetXY($side, 266);
            $pdf->MultiCell($cw, 3, $d['legal'], 0, 'C', false, 1);
        }
        if ($d['verify'] !== '') {
            $pdf->SetFont($sans, '', 6.8);
            $pdf->SetTextColorArray($muted);
            $pdf->SetXY($side, 275);
            $pdf->Cell($cw, 3.5, $d['verify'], 0, 0, 'C', false, '', 1);
        }

        $out = $pdf->Output('', 'S');

        return is_string($out) && $out !== '' ? $out : null;
    }

    // ── Utilitaires ─────────────────────────────────────────────────────────

    private static function upper(string $s): string
    {
        return mb_strtoupper($s, 'UTF-8');
    }

    private static function hex(string $v, string $fallback): string
    {
        return preg_match('/^#[0-9A-Fa-f]{6}$/', $v) ? $v : $fallback;
    }

    /** Teinte claire d'une couleur (mélange avec le blanc). */
    private static function tint(string $hex, float $ratio): string
    {
        [$r, $g, $b] = AthenaPdfTheme::rgb($hex);
        $mix = static fn (int $c): int => (int) round(255 - (255 - $c) * $ratio);

        return sprintf('#%02x%02x%02x', $mix($r), $mix($g), $mix($b));
    }

    private static function sealImageUri(string $src): ?string
    {
        if ($src === '') {
            return null;
        }

        return str_starts_with($src, 'data:') ? $src : self::dataUri($src);
    }

    /** Data URI d'une image locale (PNG, JPEG, GIF, SVG ≤ 6 Mo), sinon null. */
    private static function dataUri(string $abs): ?string
    {
        if (!is_file($abs) || !is_readable($abs) || filesize($abs) > 6 * 1024 * 1024) {
            return null;
        }
        $data = @file_get_contents($abs);
        if (!is_string($data) || $data === '') {
            return null;
        }
        if (str_ends_with(strtolower($abs), '.svg')) {
            $mime = 'image/svg+xml';
        } else {
            $info = @getimagesizefromstring($data);
            $mime = is_array($info) ? (string) ($info['mime'] ?? '') : '';
            if (!in_array($mime, ['image/png', 'image/jpeg', 'image/gif'], true)) {
                return null;
            }
        }

        return 'data:' . $mime . ';base64,' . base64_encode($data);
    }
}
