<?php

declare(strict_types=1);

namespace App\Support\Pdf;

use App\Repositories\TenantRepository;
use App\Support\TrainingCertificatePdfEngine;

/**
 * Gabarit commun des PDF du site (maquette « Modèles PDF · Athena ») : A4 portrait uniquement,
 * en-tête « ATHENA. » + communauté (logo si présent) + type et référence du document,
 * bandeau de diffusion noir, pied de page « Généré par Athena le … » + référence et pagination.
 *
 * Deux moteurs :
 *  - TCPDF : {@see self::tcpdf()} renvoie un {@see AthenaTcpdf} prêt (marges, polices, en-tête/pied) ;
 *  - Dompdf : {@see self::dompdfDocument()} enveloppe un corps HTML et {@see self::dompdfRender()} le rend.
 *
 * Les fragments HTML (titres, sections, tableaux, encadrés, signatures…) sont écrits pour le
 * sous-ensemble HTML/CSS de TCPDF et restent valides sous Dompdf.
 *
 * Métadonnées d'un document ($meta) :
 *  - kind           : type de document (« Compte rendu d'opération »)
 *  - reference      : référence affichée en haut à droite et en pied de page
 *  - community      : nom de la communauté (sinon résolu par tenant_id)
 *  - tenant_id      : communauté (nom + logo) ; facultatif
 *  - logo           : chemin local d'un logo PNG/JPEG (prioritaire sur celui du tenant)
 *  - classification : bandeau de diffusion (« Diffusion restreinte · usage interne ») ; vide = pas de bandeau
 *  - audience       : à droite du bandeau (« Staff & intéressé »)
 *  - generated_by   : auteur de l'édition (« par … » dans le pied de page)
 *  - note           : mention de confidentialité en pied de page
 *  - title          : titre des propriétés PDF
 */
final class AthenaPdfTheme
{
    public const INK = '#0b0f0e';
    public const MUTED = '#5b6470';
    public const LINE = '#d9dee3';
    public const SOFT = '#f1f5f9';
    public const ACCENT = '#059669';
    public const ACCENT_DARK = '#047857';
    public const ACCENT_SOFT = '#ecfdf5';
    public const BAND_ACCENT = '#34d399';
    public const WARN = '#b45309';
    public const WARN_SOFT = '#fffbeb';
    public const DANGER = '#b91c1c';
    public const DANGER_SOFT = '#fef2f2';

    /** Marges A4 (mm). */
    public const MARGIN = 15.0;

    /** Haut de contenu sous l'en-tête, avec et sans bandeau de diffusion (mm). */
    public const TOP_WITH_BAND = 38.0;
    public const TOP_WITHOUT_BAND = 31.0;

    /** Bas réservé au pied de page (mm). */
    public const BOTTOM = 20.0;

    private static ?string $sansFamily = null;

    private static ?string $monoFamily = null;

    /**
     * Trace du dernier rendu Dompdf (gabarit appliqué, nombre de pages), lue par les tests.
     *
     * @var array{header: bool, footer: bool, pages: int}
     */
    public static array $lastDompdfStamp = ['header' => false, 'footer' => false, 'pages' => 0];

    /** Famille Unicode embarquée (accents FR) : freesans, sinon dejavusans. */
    public static function sans(): string
    {
        if (self::$sansFamily === null) {
            self::$sansFamily = TrainingCertificatePdfEngine::resolveCertificateFontFamily() ?? 'dejavusans';
        }

        return self::$sansFamily;
    }

    /** Police à chasse fixe pour références et codes (même famille que sans()). */
    public static function mono(): string
    {
        if (self::$monoFamily === null) {
            self::$monoFamily = self::sans();
            $dir = TrainingCertificatePdfEngine::resolveTcpdfFontsDir();
            foreach (['dejavusansmono', 'freemono'] as $family) {
                if ($dir !== null && self::fontFilesReady($dir, $family)) {
                    self::$monoFamily = $family;
                    break;
                }
            }
        }

        return self::$monoFamily;
    }

    /** Régulier + gras complets (.php, .z, .ctg.z) : un fichier manquant fait échouer TCPDF. */
    private static function fontFilesReady(string $dir, string $family): bool
    {
        foreach ([$family, $family . 'b'] as $f) {
            foreach (['.php', '.z', '.ctg.z'] as $ext) {
                $file = $dir . DIRECTORY_SEPARATOR . $f . $ext;
                if (!is_file($file) || filesize($file) < 64) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Document TCPDF A4 portrait habillé, ou null si TCPDF est indisponible.
     *
     * @param array<string, mixed> $meta
     */
    public static function tcpdf(array $meta): ?AthenaTcpdf
    {
        if (!TrainingCertificatePdfEngine::ensureTcpdfLoaded()) {
            return null;
        }

        return AthenaTcpdf::make(self::meta($meta));
    }

    /**
     * Métadonnées normalisées (communauté et logo résolus).
     *
     * @param array<string, mixed> $meta
     * @return array{kind: string, reference: string, community: string, logo: ?string, classification: string, audience: string, generated_by: string, note: string, title: string, generated_at: string}
     */
    public static function meta(array $meta): array
    {
        $tenantId = (int) ($meta['tenant_id'] ?? 0);
        $tenant = $tenantId > 0 ? self::tenantRow($tenantId) : null;
        $community = trim((string) ($meta['community'] ?? ''));
        if ($community === '' && $tenant !== null) {
            $community = function_exists('community_display_name') ? community_display_name($tenant) : (string) ($tenant['name'] ?? '');
        }
        $logo = isset($meta['logo']) && is_string($meta['logo']) ? self::usableImage($meta['logo']) : null;
        if ($logo === null && $tenant !== null) {
            $logo = self::tenantLogo((string) ($tenant['logo_url'] ?? ''));
        }
        $kind = trim((string) ($meta['kind'] ?? ''));

        return [
            'kind' => $kind,
            'reference' => trim((string) ($meta['reference'] ?? '')),
            'community' => $community,
            'logo' => $logo,
            'classification' => trim((string) ($meta['classification'] ?? '')),
            'audience' => trim((string) ($meta['audience'] ?? '')),
            'generated_by' => trim((string) ($meta['generated_by'] ?? '')),
            'note' => trim((string) ($meta['note'] ?? '')),
            'title' => trim((string) ($meta['title'] ?? '')) !== '' ? trim((string) $meta['title']) : $kind,
            'generated_at' => (string) ($meta['generated_at'] ?? date('Y-m-d H:i')),
        ];
    }

    /** Libellé en anglais pour un visiteur anglophone (textes du gabarit uniquement). */
    public static function t(string $fr): string
    {
        if (!self::isEnglish()) {
            return $fr;
        }
        static $en = [
            'Généré par Athena le %s à %s' => 'Generated by Athena on %s at %s',
            'par' => 'by',
            'Nom, date, signature' => 'Name, date, signature',
            'Aucune donnée.' => 'No data.',
            'Page' => 'Page',
        ];

        return $en[$fr] ?? (class_exists(\App\Support\HtmlTranslator::class) ? \App\Support\HtmlTranslator::translateText($fr) : $fr);
    }

    public static function isEnglish(): bool
    {
        return function_exists('site_lang') && site_lang() !== 'fr';
    }

    /** Ligne de gauche du pied de page. */
    public static function footerLine(array $meta): string
    {
        $ts = strtotime((string) ($meta['generated_at'] ?? '')) ?: time();
        $line = sprintf(self::t('Généré par Athena le %s à %s'), date(self::isEnglish() ? 'm/d/Y' : 'd/m/Y', $ts), date('H:i', $ts));
        $by = trim((string) ($meta['generated_by'] ?? ''));

        return $by !== '' ? $line . ' · ' . self::t('par') . ' ' . $by : $line;
    }

    /** @return array{0: int, 1: int, 2: int} */
    public static function rgb(string $hex): array
    {
        $hex = ltrim($hex, '#');
        if (strlen($hex) === 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }
        if (!preg_match('/^[0-9a-fA-F]{6}$/', $hex)) {
            return [0, 0, 0];
        }

        return [(int) hexdec(substr($hex, 0, 2)), (int) hexdec(substr($hex, 2, 2)), (int) hexdec(substr($hex, 4, 2))];
    }

    public static function e(mixed $v): string
    {
        return htmlspecialchars((string) $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    private static function upper(string $s): string
    {
        return mb_strtoupper($s, 'UTF-8');
    }

    public static function toneColor(string $tone): string
    {
        return match ($tone) {
            'ok', 'success' => self::ACCENT_DARK,
            'warn', 'warning' => self::WARN,
            'bad', 'danger' => self::DANGER,
            'muted' => self::MUTED,
            default => self::INK,
        };
    }

    private static function toneSoft(string $tone): string
    {
        return match ($tone) {
            'ok', 'success' => self::ACCENT_SOFT,
            'warn', 'warning' => self::WARN_SOFT,
            'bad', 'danger' => self::DANGER_SOFT,
            default => self::SOFT,
        };
    }

    // ── Fragments HTML ──────────────────────────────────────────────────────

    /** Feuille de style de base (TCPDF writeHTML et Dompdf). */
    public static function css(): string
    {
        return '<style>
            p, td, th, li, div { font-family: ' . self::sans() . '; color: ' . self::INK . '; }
            p { font-size: 9pt; margin: 0; }
            td, th { font-size: 8.5pt; }
            .athena-muted { color: ' . self::MUTED . '; }
            .athena-mono { font-family: ' . self::mono() . '; }
        </style>';
    }

    /**
     * Bloc titre : surtitre discret, titre en capitales grasses italiques, pastilles facultatives.
     *
     * @param list<array{0: string, 1?: string}> $pills [texte, ton]
     */
    public static function titleBlock(string $kicker, string $title, array $pills = [], string $subtitle = ''): string
    {
        $html = '';
        if (trim($kicker) !== '') {
            $html .= '<p style="font-size:7pt;font-weight:bold;color:' . self::MUTED . ';letter-spacing:0.6px;">' . self::e(self::upper($kicker)) . '</p>';
        }
        $html .= '<p style="font-size:21pt;font-weight:bold;font-style:italic;color:' . self::INK . ';line-height:1.05;">' . self::e(self::upper($title)) . '</p>';
        if ($subtitle !== '') {
            $html .= '<p style="font-size:9pt;color:' . self::MUTED . ';">' . self::e($subtitle) . '</p>';
        }
        if ($pills !== []) {
            $html .= self::spacer(1.2) . '<p style="font-size:7.5pt;">' . implode('&nbsp;&nbsp;', array_map(static fn (array $p): string => self::pill($p[0], $p[1] ?? 'muted'), $pills)) . '</p>';
        }

        return $html . self::spacer(3);
    }

    /** Pastille de statut (« Actif », « Succès »…). */
    public static function pill(string $text, string $tone = 'muted'): string
    {
        return '<span style="background-color:' . self::toneSoft($tone) . ';color:' . self::toneColor($tone) . ';font-weight:bold;font-size:7.5pt;">&nbsp;' . self::e($text) . '&nbsp;</span>';
    }

    /** Titre de section numéroté (« 01  ÉTAT CIVIL ») ; numéro vide = sans numéro. */
    public static function section(string $num, string $title): string
    {
        $n = trim($num) !== '' ? '<span style="font-family:' . self::mono() . ';color:' . self::ACCENT . ';font-weight:normal;">' . self::e($num) . '</span>&nbsp;&nbsp;' : '';

        return self::spacer(3) . '<p style="font-size:8.5pt;font-weight:bold;letter-spacing:0.8px;color:' . self::INK . ';">' . $n . self::e(self::upper($title)) . '</p>' . self::spacer(1.2);
    }

    public static function spacer(float $mm): string
    {
        // Hauteur d'une ligne ≈ taille de police × 1,3 (ratio des cellules) : 1 mm ≈ 2,18 pt de police.
        return '<div style="font-size:' . number_format(max(0.3, $mm * 2.18), 2, '.', '') . 'pt;">&nbsp;</div>';
    }

    public static function paragraph(string $text, string $tone = ''): string
    {
        $color = $tone !== '' ? self::toneColor($tone) : self::INK;

        return '<p style="font-size:9pt;line-height:1.45;color:' . $color . ';">' . nl2br(self::e(trim($text))) . '</p>';
    }

    /**
     * Rangée d'indicateurs encadrés (Opérations / Présence / …).
     *
     * @param list<array{label: string, value: string, sub?: string, tone?: string}> $stats
     */
    public static function stats(array $stats): string
    {
        $stats = array_values(array_filter($stats, static fn ($s): bool => is_array($s) && trim((string) ($s['value'] ?? '')) !== ''));
        if ($stats === []) {
            return '';
        }
        $w = number_format(100 / count($stats), 2, '.', '');
        $cells = '';
        foreach ($stats as $i => $s) {
            $sep = $i > 0 ? 'border-left:0.3px solid ' . self::LINE . ';' : '';
            $cells .= '<td width="' . $w . '%" style="' . $sep . '">'
                . '<span style="font-size:6.5pt;font-weight:bold;color:' . self::MUTED . ';letter-spacing:0.6px;">' . self::e(self::upper((string) $s['label'])) . '</span><br>'
                . '<span style="font-size:' . (mb_strlen((string) $s['value']) > 12 ? '10.5' : '15') . 'pt;font-weight:bold;color:' . self::toneColor((string) ($s['tone'] ?? '')) . ';">' . self::e((string) $s['value']) . '</span>'
                . (trim((string) ($s['sub'] ?? '')) !== '' ? '<br><span style="font-size:7pt;color:' . self::MUTED . ';">' . self::e((string) $s['sub']) . '</span>' : '')
                . '</td>';
        }

        return '<table cellpadding="5" cellspacing="0" style="border:0.3px solid ' . self::LINE . ';"><tr>' . $cells . '</tr></table>' . self::spacer(2);
    }

    /**
     * Liste libellé / valeur (libellé gris à gauche, valeur grasse à droite).
     *
     * @param array<string, string>|list<array{0: string, 1: string}> $pairs
     */
    public static function keyValues(array $pairs, string $labelWidth = '45%'): string
    {
        $rows = '';
        foreach ($pairs as $k => $v) {
            [$label, $value] = is_array($v) ? [(string) ($v[0] ?? ''), (string) ($v[1] ?? '')] : [(string) $k, (string) $v];
            if (trim($value) === '') {
                continue;
            }
            $valueWidth = (100 - (float) $labelWidth) . '%';
            $rows .= '<tr nobr="true"><td width="' . $labelWidth . '" style="color:' . self::MUTED . ';border-bottom:0.2px solid ' . self::SOFT . ';">' . self::e($label) . '</td>'
                . '<td width="' . $valueWidth . '" align="right" style="font-weight:bold;border-bottom:0.2px solid ' . self::SOFT . ';">' . nl2br(self::e($value)) . '</td></tr>';
        }

        return $rows === '' ? '' : '<table cellpadding="2.5" cellspacing="0">' . $rows . '</table>';
    }

    /**
     * Deux blocs côte à côte (ex. deux sections de la fiche membre).
     */
    public static function twoColumns(string $left, string $right): string
    {
        return '<table cellpadding="0" cellspacing="0"><tr>'
            . '<td width="48%">' . $left . '</td><td width="4%"></td><td width="48%">' . $right . '</td>'
            . '</tr></table>';
    }

    /**
     * Tableau de données : en-tête discret souligné de noir (répété à chaque page), lignes filetées.
     * Les lignes de groupe ['group' => libellé, 'sub' => détail] s'affichent en bandeau gris.
     *
     * @param list<array{label: string, width: float|int, align?: string, mono?: bool, bold?: bool}> $columns largeurs en %
     * @param list<list<string>|array{group: string, sub?: string}|array{cells: list<string>, tones?: array<int, string>}> $rows
     * @param bool $escape false = cellules déjà en HTML
     */
    public static function table(array $columns, array $rows, bool $escape = true, string $empty = ''): string
    {
        if ($rows === []) {
            return $empty !== '' ? '<p style="font-size:8.5pt;color:' . self::MUTED . ';">' . self::e($empty) . '</p>' : '';
        }
        $head = '';
        foreach ($columns as $c) {
            $head .= '<th width="' . self::pct($c['width']) . '" align="' . self::align($c['align'] ?? 'L') . '" style="font-size:6.5pt;font-weight:bold;color:' . self::MUTED . ';letter-spacing:0.6px;border-bottom:0.6px solid ' . self::INK . ';">'
                . self::e(self::upper((string) $c['label'])) . '</th>';
        }
        $body = '';
        $n = count($columns);
        foreach ($rows as $row) {
            if (isset($row['group'])) {
                $sub = trim((string) ($row['sub'] ?? ''));
                $body .= '<tr nobr="true"><td colspan="' . $n . '" width="100%" style="background-color:' . self::SOFT . ';font-weight:bold;font-size:8pt;">'
                    . self::e(self::upper((string) $row['group']))
                    . ($sub !== '' ? '&nbsp;&nbsp;<span style="font-weight:normal;color:' . self::MUTED . ';">' . self::e($sub) . '</span>' : '')
                    . '</td></tr>';
                continue;
            }
            $cells = isset($row['cells']) && is_array($row['cells']) ? array_values($row['cells']) : array_values((array) $row);
            $tones = isset($row['tones']) && is_array($row['tones']) ? $row['tones'] : [];
            $body .= '<tr nobr="true">';
            foreach ($columns as $i => $c) {
                $raw = (string) ($cells[$i] ?? '');
                $content = $escape ? ($raw === '' ? '<span style="color:' . self::MUTED . ';">—</span>' : nl2br(self::e($raw))) : $raw;
                $style = 'border-bottom:0.2px solid ' . self::LINE . ';';
                if (!empty($c['mono'])) {
                    $style .= 'font-family:' . self::mono() . ';';
                }
                if (!empty($c['bold'])) {
                    $style .= 'font-weight:bold;';
                }
                if (isset($tones[$i]) && $tones[$i] !== '') {
                    $style .= 'color:' . self::toneColor((string) $tones[$i]) . ';';
                }
                $body .= '<td width="' . self::pct($c['width']) . '" align="' . self::align($c['align'] ?? 'L') . '" style="' . $style . '">' . $content . '</td>';
            }
            $body .= '</tr>';
        }

        return '<table cellpadding="3" cellspacing="0"><thead><tr>' . $head . '</tr></thead><tbody>' . $body . '</tbody></table>' . self::spacer(1.5);
    }

    /** Encadré fileté (note d'encadrement, synthèse…) ; ton = couleur du filet gauche. */
    public static function callout(string $bodyHtml, string $meta = '', string $tone = ''): string
    {
        $left = $tone !== '' ? 'border-left:1.6px solid ' . self::toneColor($tone) . ';' : '';

        return '<table cellpadding="6" cellspacing="0" style="border:0.3px solid ' . self::LINE . ';"><tr nobr="true"><td style="' . $left . '">'
            . $bodyHtml
            . ($meta !== '' ? '<br><span style="font-size:7.5pt;color:' . self::MUTED . ';">' . self::e($meta) . '</span>' : '')
            . '</td></tr></table>' . self::spacer(2);
    }

    /**
     * Liste à puces colorées.
     *
     * @param list<string> $items
     */
    public static function bullets(array $items, string $tone = 'ok'): string
    {
        $rows = '';
        foreach ($items as $it) {
            if (trim((string) $it) === '') {
                continue;
            }
            $rows .= '<tr nobr="true"><td width="4%" style="color:' . self::toneColor($tone) . ';font-size:8pt;">■</td><td width="96%" style="font-size:9pt;">' . self::e(trim((string) $it)) . '</td></tr>';
        }

        return $rows === '' ? '' : '<table cellpadding="2" cellspacing="0">' . $rows . '</table>';
    }

    /**
     * Cases de visa / signature (filet noir, intitulé gras, mention grise).
     *
     * @param list<array{0: string, 1?: string}> $boxes [intitulé, mention]
     */
    public static function signatures(array $boxes): string
    {
        if ($boxes === []) {
            return '';
        }
        $n = count($boxes);
        $gap = 4;
        $w = (100 - $gap * ($n - 1)) / $n;
        $cells = '';
        foreach ($boxes as $i => $b) {
            if ($i > 0) {
                $cells .= '<td width="' . $gap . '%"></td>';
            }
            $cells .= '<td width="' . self::pct($w) . '" style="border-top:0.6px solid ' . self::INK . ';">'
                . '<span style="font-size:8.5pt;font-weight:bold;">' . self::e($b[0]) . '</span><br>'
                . '<span style="font-size:7.5pt;color:' . self::MUTED . ';">' . self::e($b[1] ?? self::t('Nom, date, signature')) . '</span></td>';
        }

        return self::spacer(14) . '<table cellpadding="2" cellspacing="0"><tr nobr="true">' . $cells . '</tr></table>';
    }

    private static function pct(float|int|string $w): string
    {
        return number_format((float) $w, 2, '.', '') . '%';
    }

    private static function align(string $a): string
    {
        return match (strtoupper($a)) {
            'R', 'RIGHT' => 'right',
            'C', 'CENTER' => 'center',
            default => 'left',
        };
    }

    // ── Dompdf ──────────────────────────────────────────────────────────────

    /**
     * Document HTML complet pour Dompdf : A4 portrait, en-tête et pied de page fixes répétés.
     * Le corps utilise les fragments ci-dessus ; $extraCss complète la feuille de style.
     *
     * @param array<string, mixed> $meta
     */
    public static function dompdfDocument(string $bodyHtml, array $meta, string $extraCss = ''): string
    {
        $m = self::meta($meta);
        $band = $m['classification'] !== '';
        $top = $band ? self::TOP_WITH_BAND : self::TOP_WITHOUT_BAND;
        $logo = '';
        if ($m['logo'] !== null) {
            $data = @file_get_contents($m['logo']);
            $mime = str_ends_with(strtolower($m['logo']), '.png') ? 'image/png' : 'image/jpeg';
            if (is_string($data) && $data !== '') {
                $logo = '<img src="data:' . $mime . ';base64,' . base64_encode($data) . '" style="height:11mm;max-width:22mm;margin-right:3mm;vertical-align:middle;">';
            }
        }
        $header = '<div class="ath-head"><table class="ath-head-t"><tr>'
            . '<td>' . $logo . '<span style="display:inline-block;vertical-align:middle;"><span class="ath-word">ATHENA<span style="color:' . self::ACCENT . ';">.</span></span>'
            . ($m['community'] !== '' ? '<br><span class="ath-com">' . self::e($m['community']) . '</span>' : '') . '</span></td>'
            . '<td style="text-align:right;"><span class="ath-kind">' . self::e(self::upper($m['kind'])) . '</span>'
            . ($m['reference'] !== '' ? '<br><span class="ath-ref">' . self::e($m['reference']) . '</span>' : '') . '</td>'
            . '</tr></table><div class="ath-rule"></div>'
            . ($band ? '<table class="ath-band"><tr><td>' . self::e(self::upper($m['classification'])) . '</td><td style="text-align:right;color:' . self::BAND_ACCENT . ';">' . self::e(self::upper($m['audience'])) . '</td></tr></table>' : '')
            . '</div>';
        $footer = '<div class="ath-foot">'
            . ($m['note'] !== '' ? '<div class="ath-note">' . self::e($m['note']) . '</div>' : '')
            . '<div class="ath-foot-line">' . self::e(self::footerLine($m)) . '</div></div>';

        $sans = 'DejaVu Sans';
        $css = '@page { size: A4 portrait; margin: ' . $top . 'mm ' . self::MARGIN . 'mm ' . self::BOTTOM . 'mm ' . self::MARGIN . 'mm; }
            body { font-family: "' . $sans . '", sans-serif; font-size: 9pt; color: ' . self::INK . '; margin: 0; }
            p { margin: 0; }
            table { border-collapse: collapse; width: 100%; }
            td, th { vertical-align: top; }
            .ath-head { position: fixed; top: -' . ($top - 11) . 'mm; left: 0; right: 0; }
            .ath-head-t td { padding: 0; vertical-align: bottom; }
            .ath-word { font-size: 15pt; font-weight: bold; font-style: italic; letter-spacing: -0.3pt; }
            .ath-com { font-size: 7pt; color: ' . self::MUTED . '; }
            .ath-kind { font-size: 6.5pt; font-weight: bold; color: ' . self::MUTED . '; letter-spacing: 1pt; }
            .ath-ref { font-family: "DejaVu Sans Mono", monospace; font-size: 8.5pt; font-weight: bold; }
            .ath-rule { border-top: 1.6pt solid ' . self::INK . '; margin-top: 2.2mm; }
            .ath-band { background: ' . self::INK . '; margin-top: 1.2mm; }
            .ath-band td { color: #ffffff; font-size: 6.8pt; font-weight: bold; letter-spacing: 1pt; padding: 1.4mm 3mm; }
            .ath-foot { position: fixed; bottom: -' . (self::BOTTOM - 7) . 'mm; left: 0; right: 0; }
            .ath-note { font-size: 6.5pt; color: ' . self::DANGER . '; text-align: center; margin-bottom: 1mm; }
            .ath-foot-line { border-top: 0.6pt solid ' . self::LINE . '; padding-top: 1.6mm; font-size: 6.5pt; color: ' . self::MUTED . '; }
            ' . $extraCss;

        return '<!DOCTYPE html><html lang="' . (self::isEnglish() ? 'en' : 'fr') . '"><head><meta charset="UTF-8"><title>' . self::e($m['title']) . '</title><style>' . $css . '</style></head><body>'
            . $header . $footer . '<main>' . $bodyHtml . '</main></body></html>';
    }

    /**
     * Rendu Dompdf A4 portrait avec « RÉF · n / N » en bas à droite. Null si Dompdf est absent ou échoue.
     *
     * @param array<string, mixed> $meta
     */
    public static function dompdfRender(string $html, array $meta): ?string
    {
        if (!class_exists(\Dompdf\Dompdf::class)) {
            return null;
        }
        $m = self::meta($meta);
        $dompdf = new \Dompdf\Dompdf([
            'isRemoteEnabled' => false,
            'isHtml5ParserEnabled' => true,
            'defaultFont' => 'DejaVu Sans',
            'chroot' => function_exists('base_path') ? base_path() : getcwd(),
        ]);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();
        $canvas = $dompdf->getCanvas();
        $metrics = $dompdf->getFontMetrics();
        $font = $metrics->getFont('DejaVu Sans Mono', 'normal') ?? $metrics->getFont('DejaVu Sans');
        $label = ($m['reference'] !== '' ? $m['reference'] . ' · ' : '') . '{PAGE_NUM} / {PAGE_COUNT}';
        $size = 6.5;
        $mm = 72 / 25.4;
        $width = $metrics->getTextWidth(str_replace(['{PAGE_NUM}', '{PAGE_COUNT}'], ['00', '00'], $label), $font, $size);
        $x = $canvas->get_width() - self::MARGIN * $mm - $width;
        $y = $canvas->get_height() - (self::BOTTOM - 8.9) * $mm;
        $canvas->page_text($x, $y, $label, $font, $size, [0.357, 0.392, 0.439]);
        self::$lastDompdfStamp = [
            'header' => str_contains($html, 'class="ath-head"'),
            'footer' => str_contains($html, 'class="ath-foot"'),
            'pages' => (int) $canvas->get_page_count(),
        ];
        $out = $dompdf->output();

        return is_string($out) && $out !== '' ? $out : null;
    }

    // ── Communauté ──────────────────────────────────────────────────────────

    /** @return array<string, mixed>|null */
    private static function tenantRow(int $tenantId): ?array
    {
        try {
            return (new TenantRepository())->findById($tenantId);
        } catch (\Throwable) {
            return null;
        }
    }

    /** Fichier local du logo (PNG/JPEG < 4 Mo) depuis logo_url du tenant. */
    private static function tenantLogo(string $url): ?string
    {
        $url = trim($url);
        if ($url === '' || !function_exists('base_path')) {
            return null;
        }
        $rel = (string) (parse_url($url, PHP_URL_PATH) ?? '');
        $rel = preg_replace('#^/public/#', '/', $rel) ?? $rel;
        if ($rel === '' || str_contains($rel, '..')) {
            return null;
        }

        return self::usableImage(base_path('public' . $rel));
    }

    private static function usableImage(string $abs): ?string
    {
        if ($abs === '' || !is_file($abs) || !is_readable($abs) || filesize($abs) > 4 * 1024 * 1024) {
            return null;
        }
        $head = (string) @file_get_contents($abs, false, null, 0, 8);
        $isPng = str_starts_with($head, "\x89PNG");
        $isJpeg = str_starts_with($head, "\xFF\xD8");

        return $isPng || $isJpeg ? $abs : null;
    }
}
