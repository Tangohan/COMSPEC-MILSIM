<?php

declare(strict_types=1);

namespace App\Services\Sse;

use App\Core\Response;
use App\Core\Session;
use App\Repositories\SseIntelEventRepository;
use App\Support\Pdf\AthenaPdfTheme as Theme;
use App\Support\SseTransmissionView;
use App\Support\TrainingCertificatePdfEngine;

/**
 * Exports PDF des transmissions terrain SSE : fiche unitaire et journal, au gabarit Athena
 * (A4 portrait, en-tête communauté + référence, bandeau de diffusion, pied « Généré par Athena »).
 *
 * Mise en page « fiche de renseignement » : bloc titre, cotation OTAN lisible, données regroupées
 * par thème (identité, contexte, localisation, relevés), coordonnées dédoublonnées, identité liée
 * avec photo, traçabilité technique en fin.
 */
final class SseTransmissionPdfService
{
    private const MARKING = 'Diffusion restreinte · usage interne SSE';

    /** Regroupement des champs du payload par thème (ordre d'affichage). */
    private const GROUPS = [
        'Identité' => [
            'display_name', 'last_name', 'first_name', 'alias', 'status', 'age_estimated',
            'nationality', 'language_spoken', 'affiliation', 'distinguishing_marks',
        ],
        'Contexte de l’acquisition' => [
            'circumstances', 'statements', 'confidence_level', 'medical_context', 'identity_query',
        ],
        'Relevés' => [
            'biometrics_simulated', 'consent_recorded', 'biometric_samples', 'weapons', 'equipment', 'signature',
        ],
        'Site' => ['name', 'title', 'site_type', 'reference_code', 'notes', 'description'],
        'Opérateur terrain' => ['submitter_callsign', 'submitter_steam_id', 'target_unit_netid', 'case_code'],
    ];

    private const POSITION_KEYS = [
        'grid_reference', 'location_summary',
        'pos_x', 'pos_y', 'pos_z', 'capture_pos_x', 'capture_pos_y', 'capture_pos_z',
    ];

    private const IGNORED_KEYS = ['client', 'fields', 'person_id', 'site_id'];

    private const CLIENT_KEYS = ['mod_name', 'mod_version', 'mod_cfg', 'sse_addon_version', 'sse_addon_cfg', 'arma_version'];

    /** Numérotation des sections de la fiche (« 01 », « 02 »…). */
    private int $sectionNo = 0;

    // ------------------------------------------------------------------ exports

    /**
     * @param list<array<string, mixed>> $events
     */
    public function exportJournal(array $events, string $filterLabel = ''): Response
    {
        $generatedAt = (new \DateTimeImmutable('now'))->format('d/m/Y à H:i');
        $html = $this->journalHtml($events, $generatedAt, $filterLabel);

        return $this->renderPdf(
            $html,
            'Journal des transmissions terrain',
            'journal-transmissions-' . date('Ymd-Hi') . '.pdf',
            'P',
            'Journal des transmissions',
            [],
            ['kind' => 'Journal des transmissions', 'reference' => 'SSE-TX-' . date('Ymd'), 'tenant_id' => $this->tenantOf($events[0] ?? [])]
        );
    }

    /**
     * @param array<string, mixed> $event
     * @param array<string, mixed>|null $payload  payload brut (sinon $event['payload'])
     * @param array<string, mixed>|null $linkedPerson identité liée (nom, alias, ref, photo, prints, iris, dna, quality)
     * @param list<array{src:string,label:string}> $images images transmises
     */
    public function exportEvent(array $event, ?array $payload = null, ?array $linkedPerson = null, array $images = []): Response
    {
        $id = (int) ($event['id'] ?? 0);
        $payload ??= is_array($event['payload'] ?? null) ? $event['payload'] : [];
        $generatedAt = (new \DateTimeImmutable('now'))->format('d/m/Y à H:i');
        $html = $this->eventHtml($event, $payload, $linkedPerson, $generatedAt);

        return $this->renderPdf(
            $html,
            (string) ($event['event_type_label'] ?? 'Transmission'),
            sprintf('transmission-TX-%d.pdf', max(0, $id)),
            'P',
            'TX-' . $id,
            $this->resolveImages($images, $linkedPerson),
            ['kind' => 'Fiche de transmission terrain', 'tenant_id' => $this->tenantOf($event)]
        );
    }

    // ------------------------------------------------------------------ fiche

    /**
     * @param array<string, mixed> $event
     * @param array<string, mixed> $payload
     * @param array<string, mixed>|null $linkedPerson
     */
    public function eventHtml(array $event, array $payload, ?array $linkedPerson, string $generatedAt): string
    {
        $this->sectionNo = 0;
        $id = (int) ($event['id'] ?? 0);
        $nature = (string) ($event['event_type_label'] ?? 'Transmission');
        $summary = trim((string) ($event['summary'] ?? ''));
        $time = $this->formatDateTime((string) ($event['event_time'] ?? ''));
        $code = strtoupper(trim((string) ($event['confidence_code'] ?? '')));

        $html = $this->style();

        // Bloc titre
        $html .= $this->titleHtml(
            'Fiche de transmission terrain · TX-' . $id,
            $nature,
            ($summary !== '' ? $summary : 'Sans résumé') . ' · ' . $time
        );

        // Bande d'informations clés
        $html .= '<table cellpadding="5" cellspacing="0" style="border:0.3px solid ' . Theme::LINE . ';"><tr>'
            . $this->metaCell('Origine', (string) ($event['source_system_label'] ?? '—'), '25%', false)
            . $this->metaCell('Opérateur', $this->orDash($event['author_label'] ?? ''), '25%')
            . $this->metaCell('Unité', $this->orDash($event['unit_label'] ?? '', 'Non renseignée'), '25%')
            . '<td width="25%" style="border-left:0.3px solid ' . Theme::LINE . ';">'
            . '<span class="lbl">COTATION</span><br/>' . $this->cotationBadge($code)
            . '<br/><span style="font-size:7pt;color:' . Theme::MUTED . ';">' . $this->esc($this->cotationMeaning($code)) . '</span>'
            . '</td></tr></table>' . Theme::spacer(1);

        // Identité liée
        if (is_array($linkedPerson) && trim((string) ($linkedPerson['name'] ?? '')) !== '') {
            $html .= $this->linkedPersonHtml($linkedPerson);
        }

        // Données groupées
        $fields = is_array($payload['fields'] ?? null) ? $payload['fields'] : [];
        $flat = $fields + array_diff_key($payload, array_flip(array_merge(self::IGNORED_KEYS, self::CLIENT_KEYS)));
        $used = [];

        foreach (self::GROUPS as $title => $keys) {
            $pairs = [];
            foreach ($keys as $k) {
                if (!array_key_exists($k, $flat) || $this->isEmpty($flat[$k]) || $this->isImage($flat[$k])) {
                    continue;
                }
                $pairs[] = [SseIntelEventRepository::payloadFieldLabel($k), $flat[$k]];
                $used[$k] = true;
            }
            // L'identité affichée duplique prénom/alias quand ils sont présents.
            if ($title === 'Identité' && count($pairs) > 1) {
                $pairs = array_values(array_filter(
                    $pairs,
                    static fn (array $p): bool => $p[0] !== SseIntelEventRepository::payloadFieldLabel('display_name')
                ));
                $used['display_name'] = true;
            }
            if ($pairs !== []) {
                $html .= $this->sectionTitle($title) . $this->kvGrid($pairs);
            }
        }

        // Localisation (dédoublonnée)
        $loc = $this->locationPairs($flat);
        foreach (self::POSITION_KEYS as $k) {
            $used[$k] = true;
        }
        if ($loc !== []) {
            $html .= $this->sectionTitle('Localisation') . $this->kvGrid($loc);
        }

        // Compléments
        $rest = [];
        foreach ($flat as $k => $v) {
            $k = (string) $k;
            if (isset($used[$k]) || $k === 'source_system' || $this->isEmpty($v) || $this->isImage($v)) {
                continue;
            }
            $rest[] = [SseIntelEventRepository::payloadFieldLabel($k), $v];
        }
        if ($rest !== []) {
            $html .= $this->sectionTitle('Compléments') . $this->kvGrid($rest);
        }

        // Traçabilité technique
        $client = is_array($payload['client'] ?? null) ? $payload['client'] : array_intersect_key($payload, array_flip(self::CLIENT_KEYS));
        $tech = [];
        $tech[] = ['Canal', (string) ($payload['source_system'] ?? $event['source_system'] ?? 'ARMA_SSE')];
        $software = trim((string) ($event['client_label'] ?? ''));
        if ($software !== '') {
            $tech[] = ['Logiciel', $software];
        }
        foreach ($client as $k => $v) {
            if ($this->isEmpty($v) || (string) $k === 'mod_name') {
                continue;
            }
            $tech[] = [SseIntelEventRepository::payloadFieldLabel((string) $k), $v];
        }
        $tech[] = ['Horodatage serveur', $this->formatDateTime((string) ($event['created_at'] ?? $event['event_time'] ?? ''))];
        $html .= $this->sectionTitle('Traçabilité technique', true) . $this->kvGrid($tech, true);

        $html .= Theme::spacer(3) . '<p style="font-size:7pt;color:' . Theme::MUTED . ';">Document généré par Athena SSE le '
            . $this->esc($generatedAt) . '. Toute reproduction ou diffusion hors du bureau SSE est interdite.</p>';

        return $html;
    }

    // ------------------------------------------------------------------ journal

    /**
     * @param list<array<string, mixed>> $events
     */
    public function journalHtml(array $events, string $generatedAt, string $filterLabel = ''): string
    {
        $count = count($events);
        $byNature = [];
        $operators = [];
        foreach ($events as $e) {
            $n = (string) ($e['event_type_label'] ?? 'Autre');
            $byNature[$n] = ($byNature[$n] ?? 0) + 1;
            $op = trim((string) ($e['author_label'] ?? ''));
            if ($op !== '') {
                $operators[$op] = true;
            }
        }
        arsort($byNature);
        $first = $events !== [] ? $this->formatDateTime((string) end($events)['event_time']) : '—';
        $last = $events !== [] ? $this->formatDateTime((string) $events[0]['event_time']) : '—';

        $html = $this->style();
        $html .= $this->titleHtml(
            'Bureau SSE · Extrait',
            'Journal des transmissions terrain',
            ($filterLabel !== '' ? $filterLabel : 'Toutes transmissions') . ' · généré le ' . $generatedAt
        );

        // Synthèse
        $stats = [[
            'label' => 'Transmissions',
            'value' => (string) $count,
            'sub' => count($operators) . ' opérateur' . (count($operators) > 1 ? 's' : ''),
        ]];
        foreach (array_slice($byNature, 0, 4, true) as $n => $c) {
            $stats[] = ['label' => (string) $n, 'value' => (string) $c];
        }
        $html .= Theme::stats($stats);
        $html .= Theme::keyValues([['Période', $first . ' → ' . $last]], '30%') . Theme::spacer(2);

        $rows = [];
        foreach ($events as $e) {
            $origin = trim((string) ($e['source_system_label'] ?? ''));
            $rows[] = [
                '<b>TX-' . (int) ($e['id'] ?? 0) . '</b>',
                $this->esc($this->formatDateTime((string) ($e['event_time'] ?? ''), 'd/m/Y H:i')),
                '<b>' . $this->esc((string) ($e['event_type_label'] ?? '')) . '</b>'
                    . ($origin !== '' ? '<br/><span style="color:' . Theme::MUTED . ';font-size:7.5pt;">' . $this->esc($origin) . '</span>' : ''),
                $this->esc((string) ($e['summary'] ?? '')),
                $this->esc($this->orDash($e['author_label'] ?? '')),
                $this->cotationBadge(strtoupper((string) ($e['confidence_code'] ?? '')), true),
            ];
        }
        $html .= Theme::table([
            ['label' => 'Réf.', 'width' => 10, 'mono' => true],
            ['label' => 'Horodatage', 'width' => 13],
            ['label' => 'Nature · origine', 'width' => 19],
            ['label' => 'Résumé', 'width' => 36],
            ['label' => 'Opérateur', 'width' => 14],
            ['label' => 'Cot.', 'width' => 8, 'align' => 'C'],
        ], $rows, false, 'Aucune transmission pour ces filtres.');

        return $html;
    }

    // ------------------------------------------------------------------ blocs

    private function style(): string
    {
        return '<style>'
            . '.lbl{font-size:6.5pt;font-weight:bold;color:' . Theme::MUTED . ';letter-spacing:0.6px;}'
            . '.val{font-size:9pt;color:' . Theme::INK . ';}'
            . '</style>';
    }

    /**
     * Bloc titre du gabarit (surtitre, titre en capitales grasses italiques, sous-titre). Les capitales
     * viennent du CSS : le texte source reste lisible tel quel dans le HTML.
     */
    private function titleHtml(string $kicker, string $title, string $subtitle): string
    {
        return '<p style="font-size:7pt;font-weight:bold;color:' . Theme::MUTED . ';letter-spacing:0.6px;text-transform:uppercase;">' . $this->esc($kicker) . '</p>'
            . '<p style="font-size:21pt;font-weight:bold;font-style:italic;color:' . Theme::INK . ';line-height:1.05;text-transform:uppercase;">' . $this->esc($title) . '</p>'
            . ($subtitle !== '' ? '<p style="font-size:9pt;color:' . Theme::MUTED . ';">' . $this->esc($subtitle) . '</p>' : '')
            . Theme::spacer(3);
    }

    private function metaCell(string $label, string $value, string $width, bool $separator = true): string
    {
        return '<td width="' . $width . '"' . ($separator ? ' style="border-left:0.3px solid ' . Theme::LINE . ';"' : '') . '>'
            . '<span class="lbl">' . $this->esc(mb_strtoupper($label, 'UTF-8')) . '</span><br/>'
            . '<span style="font-size:9.5pt;font-weight:bold;color:' . Theme::INK . ';">' . $this->esc($value) . '</span></td>';
    }

    private function sectionTitle(string $title, bool $muted = false): string
    {
        return Theme::section($muted ? '' : sprintf('%02d', ++$this->sectionNo), $title);
    }

    /**
     * Grille deux colonnes « libellé au-dessus de la valeur ». Les valeurs longues prennent toute la largeur.
     *
     * @param list<array{0:string,1:mixed}> $pairs
     */
    private function kvGrid(array $pairs, bool $compact = false): string
    {
        $size = $compact ? '8pt' : '9pt';
        $cells = [];
        foreach ($pairs as [$label, $value]) {
            $text = $this->stringify($value);
            $wide = mb_strlen($text, 'UTF-8') > 60 || str_contains($text, "\n");
            $cells[] = ['wide' => $wide, 'html' => '<span class="lbl">' . $this->esc(mb_strtoupper($label, 'UTF-8')) . '</span><br/>'
                . (is_bool($value) || in_array($text, ['Oui', 'Non'], true)
                    ? $this->pill($text === 'Oui')
                    : '<span style="font-size:' . $size . ';color:' . Theme::INK . ';">' . nl2br($this->esc($text)) . '</span>')];
        }

        $line = 'border-bottom:0.2px solid ' . Theme::LINE . ';';
        $html = '<table cellpadding="3" cellspacing="0" width="100%">';
        $row = [];
        $flush = function () use (&$row, &$html, $line): void {
            if ($row === []) {
                return;
            }
            $html .= '<tr nobr="true">';
            foreach ($row as $c) {
                $html .= '<td width="50%" style="' . $line . '">' . $c . '</td>';
            }
            if (count($row) === 1) {
                $html .= '<td width="50%" style="' . $line . '"></td>';
            }
            $html .= '</tr>';
            $row = [];
        };
        foreach ($cells as $c) {
            if ($c['wide']) {
                $flush();
                $html .= '<tr nobr="true"><td width="100%" colspan="2" style="' . $line . '">' . $c['html'] . '</td></tr>';
                continue;
            }
            $row[] = $c['html'];
            if (count($row) === 2) {
                $flush();
            }
        }
        $flush();

        return $html . '</table>';
    }

    /**
     * @param array<string, mixed> $p
     */
    private function linkedPersonHtml(array $p): string
    {
        $bits = [];
        foreach (['prints' => 'Empreintes', 'iris' => 'Iris', 'dna' => 'ADN'] as $k => $l) {
            $v = trim((string) ($p[$k] ?? ''));
            if ($v !== '') {
                $bits[] = '<b>' . $l . '</b> ' . $this->esc($v);
            }
        }
        if (($p['quality'] ?? null) !== null) {
            $bits[] = '<b>Qualité</b> ' . (int) $p['quality'] . ' %';
        }
        $alias = trim((string) ($p['alias'] ?? ''));
        $photo = $this->localImageTag((string) ($p['photo'] ?? ''), 22);

        $body = '<table cellpadding="0" cellspacing="0" width="100%"><tr>'
            . ($photo !== '' ? '<td width="16%">' . $photo . '</td>' : '')
            . '<td width="' . ($photo !== '' ? '84%' : '100%') . '">'
            . '<span class="lbl">' . $this->esc((string) ($p['ref'] ?? '')) . '</span><br/>'
            . '<span style="font-size:12pt;font-weight:bold;color:' . Theme::INK . ';">' . $this->esc((string) $p['name']) . '</span>'
            . ($alias !== '' ? ' <span style="font-size:9.5pt;color:' . Theme::MUTED . ';">« ' . $this->esc($alias) . ' »</span>' : '')
            . ($bits !== [] ? '<br/><span style="font-size:8pt;color:' . Theme::INK . ';">' . implode(' &nbsp;·&nbsp; ', $bits) . '</span>' : '')
            . '</td></tr></table>';

        return $this->sectionTitle('Identité liée au dossier') . Theme::callout($body, '', 'ok');
    }

    /**
     * Grille, résumé de position et coordonnées terrain : une seule fois même si
     * la position de capture duplique la position de l'opérateur.
     *
     * @param array<string, mixed> $f
     * @return list<array{0:string,1:string}>
     */
    private function locationPairs(array $f): array
    {
        $out = [];
        $grid = trim((string) ($f['grid_reference'] ?? ''));
        if ($grid !== '') {
            $out[] = ['Référence grille', $grid];
        }
        $coord = static function (array $f, string $prefix): ?array {
            $x = $f[$prefix . 'x'] ?? null;
            $y = $f[$prefix . 'y'] ?? null;
            if (!is_numeric($x) || !is_numeric($y)) {
                return null;
            }

            return [(float) $x, (float) $y, is_numeric($f[$prefix . 'z'] ?? null) ? (float) $f[$prefix . 'z'] : null];
        };
        $fmt = static fn (array $c): string => sprintf('X %s · Y %s', number_format($c[0], 1, ',', ' '), number_format($c[1], 1, ',', ' '))
            . ($c[2] !== null ? ' · Z ' . number_format($c[2], 1, ',', ' ') : '');
        $op = $coord($f, 'pos_');
        $cap = $coord($f, 'capture_pos_');
        $same = $op !== null && $cap !== null && abs($op[0] - $cap[0]) < 0.5 && abs($op[1] - $cap[1]) < 0.5;
        if ($cap !== null) {
            $out[] = ['Coordonnées de la capture', $fmt($cap)];
        }
        if ($op !== null && !$same) {
            $out[] = [$cap !== null ? 'Coordonnées de l’opérateur' : 'Coordonnées terrain', $fmt($op)];
        }
        $summary = trim((string) ($f['location_summary'] ?? ''));
        // Le résumé « Grille … · Terrain x / y » ne fait que répéter ce qui précède.
        if ($summary !== '' && ($out === [] || !preg_match('/^grille\b.*terrain/i', $summary))) {
            $out[] = ['Position relevée', $summary];
        }

        return $out;
    }

    private function cotationBadge(string $code, bool $small = false): string
    {
        if ($code === '' || !preg_match('/^([A-F])([1-6])$/', $code, $m)) {
            return '<span style="color:' . Theme::MUTED . ';">—</span>';
        }
        $score = (ord('F') - ord($m[1])) + (6 - (int) $m[2]); // 0 (F6) → 10 (A1)
        $bg = $score >= 7 ? Theme::ACCENT_DARK : ($score >= 4 ? Theme::WARN : Theme::DANGER);
        $size = $small ? '7.5pt' : '11pt';

        return '<span style="background-color:' . $bg . ';color:#ffffff;font-family:' . Theme::mono() . ';font-size:' . $size . ';font-weight:bold;">&nbsp;' . $code . '&nbsp;</span>';
    }

    private function cotationMeaning(string $code): string
    {
        if (!preg_match('/^([A-F])([1-6])$/', $code, $m)) {
            return 'Non cotée';
        }
        $rel = [
            'A' => 'Source sûre', 'B' => 'Source habituellement sûre', 'C' => 'Source assez sûre',
            'D' => 'Source peu sûre', 'E' => 'Source non sûre', 'F' => 'Fiabilité inconnue',
        ][$m[1]];
        $cred = [
            '1' => 'info confirmée', '2' => 'info probable', '3' => 'info possible',
            '4' => 'info douteuse', '5' => 'info improbable', '6' => 'véracité inconnue',
        ][$m[2]];

        return $rel . ' · ' . $cred;
    }

    private function pill(bool $yes): string
    {
        return Theme::pill($yes ? 'OUI' : 'NON', $yes ? 'ok' : 'warn');
    }

    // ------------------------------------------------------------------ images

    /**
     * @param list<array{src:string,label:string}> $images
     * @param array<string, mixed>|null $person
     * @return list<array{path:string,label:string}>
     */
    private function resolveImages(array $images, ?array $person): array
    {
        $out = [];
        $seen = [];
        $all = $images;
        foreach ((array) ($person['photos'] ?? []) as $ph) {
            if (is_array($ph) && isset($ph['src'])) {
                $all[] = ['src' => (string) $ph['src'], 'label' => (string) ($ph['label'] ?? 'Photographie')];
            }
        }
        foreach ($all as $img) {
            $path = $this->localImagePath((string) ($img['src'] ?? ''));
            if ($path === null || isset($seen[$path])) {
                continue;
            }
            $seen[$path] = true;
            $out[] = ['path' => $path, 'label' => (string) ($img['label'] ?? 'Image')];
        }

        return array_slice($out, 0, 12);
    }

    private function localImageTag(string $src, int $mm): string
    {
        $path = $this->localImagePath($src);
        if ($path === null) {
            return '';
        }

        return '<img src="' . $this->esc($path) . '" width="' . ($mm * 3) . '" />';
    }

    private function localImagePath(string $src): ?string
    {
        $src = trim($src);
        if ($src === '' || !SseTransmissionView::isImageValue($src) || str_starts_with($src, 'data:')) {
            return null;
        }
        $path = (string) (parse_url($src, PHP_URL_PATH) ?? '');
        $base = (string) (parse_url(function_exists('url') ? url('/') : '/', PHP_URL_PATH) ?? '');
        if ($base !== '' && $base !== '/' && str_starts_with($path, rtrim($base, '/') . '/')) {
            $path = substr($path, strlen(rtrim($base, '/')));
        }
        $rel = ltrim(str_replace(['\\', '..'], ['/', ''], $path), '/');
        if (!function_exists('base_path')) {
            return null;
        }
        foreach ([base_path('public/' . $rel), base_path($rel)] as $cand) {
            if (is_file($cand) && is_readable($cand)) {
                return $cand;
            }
        }

        return null;
    }

    // ------------------------------------------------------------------ utilitaires

    private function isEmpty(mixed $v): bool
    {
        return $v === null || $v === '' || $v === [];
    }

    private function isImage(mixed $v): bool
    {
        return is_string($v) && SseTransmissionView::isImageValue($v);
    }

    private function stringify(mixed $v): string
    {
        if (is_bool($v)) {
            return $v ? 'Oui' : 'Non';
        }
        if (is_scalar($v)) {
            $s = trim((string) $v);
            if (in_array(strtolower($s), ['true', 'yes'], true)) {
                return 'Oui';
            }
            if (in_array(strtolower($s), ['false', 'no'], true)) {
                return 'Non';
            }

            return function_exists('sse_normalize_ref_display') ? sse_normalize_ref_display($s) : $s;
        }
        if (is_array($v)) {
            if (array_is_list($v) && array_filter($v, 'is_scalar') === $v) {
                return implode(', ', array_map('strval', $v));
            }
            $lines = [];
            foreach ($v as $k => $item) {
                $label = is_int($k) ? '' : SseIntelEventRepository::payloadFieldLabel((string) $k) . ' : ';
                $lines[] = $label . (is_array($item) ? $this->stringify($item) : $this->stringify($item));
            }

            return implode("\n", $lines);
        }

        return '';
    }

    private function orDash(mixed $v, string $fallback = '—'): string
    {
        $s = trim((string) $v);

        return $s !== '' ? $s : $fallback;
    }

    private function formatDateTime(string $raw, string $format = 'd/m/Y · H:i:s'): string
    {
        $raw = trim($raw);
        if ($raw === '') {
            return '—';
        }
        $ts = strtotime($raw);

        return $ts === false ? $raw : date($format, $ts);
    }

    private function esc(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /**
     * Communauté du document : celle de la transmission, sinon celle de la session.
     *
     * @param array<string, mixed> $event
     */
    private function tenantOf(array $event): int
    {
        $tid = (int) ($event['tenant_id'] ?? 0);
        if ($tid <= 0) {
            try {
                $tid = (int) (Session::get('tenant_id') ?? 0);
            } catch (\Throwable) {
                $tid = 0;
            }
        }

        return $tid;
    }

    // ------------------------------------------------------------------ moteur

    /**
     * @param list<array{path:string,label:string}> $images
     * @param array<string, mixed> $meta
     */
    private function renderPdf(string $html, string $title, string $filename, string $orientation, string $footerRef, array $images, array $meta = []): Response
    {
        return TrainingCertificatePdfEngine::suppressTcpdfPhpDeprecationsWhile(function () use (
            $html,
            $title,
            $filename,
            $orientation,
            $footerRef,
            $images,
            $meta
        ): Response {
            if (!TrainingCertificatePdfEngine::ensureTcpdfLoaded()) {
                return (new Response())->setStatusCode(503)->setBody('<p>Export PDF indisponible pour le moment.</p>');
            }

            $binary = $this->buildPdf($html, $title, $orientation, $footerRef, $images, $meta);

            return (new Response())
                ->header('Content-Type', 'application/pdf')
                ->header('Content-Disposition', 'attachment; filename="' . $filename . '"')
                ->header('Cache-Control', 'private, no-store')
                ->setBody($binary);
        });
    }

    /**
     * Construit le binaire PDF (TCPDF déjà chargé). Toujours en A4 portrait (gabarit Athena) :
     * `$orientation` est conservé pour compatibilité et ignoré.
     *
     * `$meta` complète les métadonnées du gabarit (kind, reference, tenant_id, community…).
     *
     * @param list<array{path:string,label:string}> $images
     * @param array<string, mixed> $meta
     */
    public function buildPdf(string $html, string $title, string $orientation, string $footerRef, array $images = [], array $meta = []): string
    {
        unset($orientation);
        $community = '';
        if ((int) ($meta['tenant_id'] ?? 0) <= 0) {
            try {
                $community = trim((string) (Session::get('tenant_name') ?? ''));
            } catch (\Throwable) {
                $community = '';
            }
        }
        $pdf = Theme::tcpdf($meta + [
            'kind' => 'Transmission terrain',
            'reference' => $footerRef,
            'community' => $community,
            'classification' => self::MARKING,
            'audience' => 'Bureau SSE',
            'title' => $title,
        ]);
        if ($pdf === null) {
            throw new \RuntimeException('TCPDF indisponible');
        }
        $pdf->AddPage();
        $pdf->writeAthenaHtml($html);

        if ($images !== []) {
            $pdf->AddPage();
            $pdf->writeAthenaHtml(Theme::section(sprintf('%02d', $this->sectionNo + 1), 'Pièces images'));
            $l = Theme::MARGIN;
            $gap = 6;
            $w = ($pdf->getPageWidth() - 2 * $l - $gap) / 2;
            $bottom = $pdf->getPageHeight() - Theme::BOTTOM;
            $col = 0;
            $y = $pdf->GetY() + 1;
            foreach ($images as $img) {
                $h = $w * 0.75;
                if ($y + $h + 10 > $bottom) {
                    $pdf->AddPage();
                    $y = $pdf->contentTop();
                    $col = 0;
                }
                $x = $l + $col * ($w + $gap);
                $pdf->SetDrawColorArray(Theme::rgb(Theme::LINE));
                $pdf->SetLineWidth(0.2);
                $pdf->Rect($x, $y, $w, $h, 'D');
                try {
                    $pdf->Image($img['path'], $x, $y, $w, $h, '', '', '', true, 150, '', false, false, 0, 'CM');
                } catch (\Throwable) {
                }
                $pdf->SetXY($x, $y + $h + 1);
                $pdf->SetFont(Theme::sans(), '', 7.5);
                $pdf->SetTextColorArray(Theme::rgb(Theme::MUTED));
                $pdf->Cell($w, 4, $img['label'], 0, 0, 'C');
                $col++;
                if ($col === 2) {
                    $col = 0;
                    $y += $h + 9;
                }
            }
        }

        return (string) $pdf->Output('', 'S');
    }
}
