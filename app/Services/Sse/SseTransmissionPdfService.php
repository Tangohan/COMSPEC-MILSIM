<?php

declare(strict_types=1);

namespace App\Services\Sse;

use App\Core\Response;
use App\Repositories\SseIntelEventRepository;
use App\Support\SseTransmissionView;
use App\Support\TrainingCertificatePdfEngine;

/**
 * Exports PDF des transmissions terrain SSE : fiche unitaire et journal.
 *
 * Mise en page « fiche de renseignement » : bandeau de diffusion, cartouche titre,
 * cotation OTAN lisible, données regroupées par thème (identité, contexte, localisation,
 * relevés), coordonnées dédoublonnées, identité liée avec photo, traçabilité technique en fin.
 */
final class SseTransmissionPdfService
{
    private const INK = '#0b1220';
    private const MUTED = '#64748b';
    private const LINE = '#e2e8f0';
    private const SOFT = '#f8fafc';
    private const ACCENT = '#0f5132';
    private const ACCENT_SOFT = '#e8f3ee';
    private const MARKING = 'DIFFUSION RESTREINTE · USAGE INTERNE SSE';

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
            'L',
            'Journal des transmissions',
            []
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
            $this->resolveImages($images, $linkedPerson)
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
        $id = (int) ($event['id'] ?? 0);
        $nature = (string) ($event['event_type_label'] ?? 'Transmission');
        $summary = trim((string) ($event['summary'] ?? ''));
        $time = $this->formatDateTime((string) ($event['event_time'] ?? ''));
        $code = strtoupper(trim((string) ($event['confidence_code'] ?? '')));

        $html = $this->style();

        // Cartouche titre
        $html .= '<table cellpadding="0" cellspacing="0" width="100%"><tr>'
            . '<td width="72%" style="background-color:' . self::INK . ';color:#ffffff;">'
            . '<table cellpadding="10" cellspacing="0" width="100%"><tr><td>'
            . '<span style="font-size:7.5px;color:#9fb3c8;letter-spacing:1.5px;">FICHE DE TRANSMISSION TERRAIN</span><br/>'
            . '<span style="font-size:18px;font-weight:bold;">' . $this->esc($nature) . '</span><br/>'
            . '<span style="font-size:9px;color:#cbd5e1;">' . $this->esc($summary !== '' ? $summary : 'Sans résumé') . '</span>'
            . '</td></tr></table></td>'
            . '<td width="28%" style="background-color:' . self::ACCENT . ';color:#ffffff;" align="right">'
            . '<table cellpadding="10" cellspacing="0" width="100%"><tr><td align="right">'
            . '<span style="font-size:7.5px;color:#bfe3cf;letter-spacing:1.5px;">RÉFÉRENCE</span><br/>'
            . '<span style="font-size:20px;font-weight:bold;">TX-' . $id . '</span><br/>'
            . '<span style="font-size:8.5px;color:#d7efe2;">' . $this->esc($time) . '</span>'
            . '</td></tr></table></td>'
            . '</tr></table>';

        // Bande d'informations clés
        $html .= '<br/><table cellpadding="6" cellspacing="0" width="100%" style="border:0.4px solid ' . self::LINE . ';"><tr>'
            . $this->metaCell('Origine', (string) ($event['source_system_label'] ?? '—'), '25%')
            . $this->metaCell('Opérateur', $this->orDash($event['author_label'] ?? ''), '25%')
            . $this->metaCell('Unité', $this->orDash($event['unit_label'] ?? '', 'Non renseignée'), '25%')
            . '<td width="25%" style="background-color:' . self::SOFT . ';">'
            . '<span class="lbl">COTATION</span><br/>' . $this->cotationBadge($code)
            . '<br/><span style="font-size:7px;color:' . self::MUTED . ';">' . $this->esc($this->cotationMeaning($code)) . '</span>'
            . '</td></tr></table>';

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

        $html .= '<br/><span style="font-size:7px;color:' . self::MUTED . ';">Document généré par Athena SSE le '
            . $this->esc($generatedAt) . '. Toute reproduction ou diffusion hors du bureau SSE est interdite.</span>';

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
        $html .= '<table cellpadding="10" cellspacing="0" width="100%"><tr>'
            . '<td width="70%" style="background-color:' . self::INK . ';color:#ffffff;">'
            . '<span style="font-size:7.5px;color:#9fb3c8;letter-spacing:1.5px;">BUREAU SSE · EXTRAIT</span><br/>'
            . '<span style="font-size:17px;font-weight:bold;">Journal des transmissions terrain</span><br/>'
            . '<span style="font-size:8.5px;color:#cbd5e1;">' . $this->esc($filterLabel !== '' ? $filterLabel : 'Toutes transmissions')
            . ' · généré le ' . $this->esc($generatedAt) . '</span></td>'
            . '<td width="30%" style="background-color:' . self::ACCENT . ';color:#ffffff;" align="right">'
            . '<span style="font-size:7.5px;color:#bfe3cf;letter-spacing:1.5px;">TRANSMISSIONS</span><br/>'
            . '<span style="font-size:20px;font-weight:bold;">' . $count . '</span><br/>'
            . '<span style="font-size:8px;color:#d7efe2;">' . count($operators) . ' opérateur' . (count($operators) > 1 ? 's' : '') . '</span>'
            . '</td></tr></table><br/>';

        // Synthèse
        $chips = '';
        foreach (array_slice($byNature, 0, 6, true) as $n => $c) {
            $chips .= '<td style="background-color:' . self::SOFT . ';border:0.4px solid ' . self::LINE . ';">'
                . '<span class="lbl">' . $this->esc(mb_strtoupper($n, 'UTF-8')) . '</span><br/>'
                . '<span style="font-size:12px;font-weight:bold;">' . $c . '</span></td>';
        }
        $html .= '<table cellpadding="6" cellspacing="3" width="100%"><tr>'
            . '<td style="background-color:' . self::SOFT . ';border:0.4px solid ' . self::LINE . ';"><span class="lbl">PÉRIODE</span><br/>'
            . '<span style="font-size:8.5px;font-weight:bold;">' . $this->esc($first) . '<br/>→ ' . $this->esc($last) . '</span></td>'
            . $chips . '</tr></table><br/>';

        $html .= '<table cellpadding="5" cellspacing="0" width="100%">'
            . '<thead><tr style="background-color:' . self::INK . ';color:#ffffff;font-size:7.5px;">'
            . '<th width="8%">RÉF.</th><th width="13%">HORODATAGE</th><th width="15%">NATURE</th>'
            . '<th width="11%">ORIGINE</th><th width="35%">RÉSUMÉ</th><th width="11%">OPÉRATEUR</th><th width="7%" align="center">COT.</th>'
            . '</tr></thead><tbody>';
        if ($events === []) {
            $html .= '<tr><td colspan="7" align="center" style="color:' . self::MUTED . ';">Aucune transmission pour ces filtres.</td></tr>';
        }
        foreach ($events as $i => $e) {
            $bg = $i % 2 === 0 ? '#ffffff' : self::SOFT;
            $html .= '<tr style="background-color:' . $bg . ';font-size:8px;" nobr="true">'
                . '<td width="8%" style="border-bottom:0.3px solid ' . self::LINE . ';"><b>TX-' . (int) ($e['id'] ?? 0) . '</b></td>'
                . '<td width="13%" style="border-bottom:0.3px solid ' . self::LINE . ';">' . $this->esc($this->formatDateTime((string) ($e['event_time'] ?? ''), 'd/m/Y H:i')) . '</td>'
                . '<td width="15%" style="border-bottom:0.3px solid ' . self::LINE . ';"><b>' . $this->esc((string) ($e['event_type_label'] ?? '')) . '</b></td>'
                . '<td width="11%" style="border-bottom:0.3px solid ' . self::LINE . ';color:' . self::MUTED . ';">' . $this->esc((string) ($e['source_system_label'] ?? '')) . '</td>'
                . '<td width="35%" style="border-bottom:0.3px solid ' . self::LINE . ';">' . $this->esc((string) ($e['summary'] ?? '')) . '</td>'
                . '<td width="11%" style="border-bottom:0.3px solid ' . self::LINE . ';">' . $this->esc($this->orDash($e['author_label'] ?? '')) . '</td>'
                . '<td width="7%" align="center" style="border-bottom:0.3px solid ' . self::LINE . ';">' . $this->cotationBadge(strtoupper((string) ($e['confidence_code'] ?? '')), true) . '</td>'
                . '</tr>';
        }
        $html .= '</tbody></table>';

        return $html;
    }

    // ------------------------------------------------------------------ blocs

    private function style(): string
    {
        return '<style>'
            . '.lbl{font-size:6.5px;color:' . self::MUTED . ';letter-spacing:1px;}'
            . '.val{font-size:9.5px;color:' . self::INK . ';}'
            . '</style>';
    }

    private function metaCell(string $label, string $value, string $width): string
    {
        return '<td width="' . $width . '" style="background-color:' . self::SOFT . ';">'
            . '<span class="lbl">' . $this->esc(mb_strtoupper($label, 'UTF-8')) . '</span><br/>'
            . '<span style="font-size:10px;font-weight:bold;color:' . self::INK . ';">' . $this->esc($value) . '</span></td>';
    }

    private function sectionTitle(string $title, bool $muted = false): string
    {
        $color = $muted ? self::MUTED : self::ACCENT;

        return '<div style="font-size:5px;line-height:1;">&nbsp;</div><table cellpadding="0" cellspacing="0" width="100%"><tr>'
            . '<td width="1.6%" style="background-color:' . $color . ';"> </td>'
            . '<td width="98.4%" style="font-size:10px;font-weight:bold;color:' . ($muted ? self::MUTED : self::INK) . ';">&nbsp;&nbsp;'
            . $this->esc(mb_strtoupper($title, 'UTF-8')) . '</td></tr></table><br/>';
    }

    /**
     * Grille deux colonnes « libellé au-dessus de la valeur ». Les valeurs longues prennent toute la largeur.
     *
     * @param list<array{0:string,1:mixed}> $pairs
     */
    private function kvGrid(array $pairs, bool $compact = false): string
    {
        $size = $compact ? '8px' : '9.5px';
        $cells = [];
        foreach ($pairs as [$label, $value]) {
            $text = $this->stringify($value);
            $wide = mb_strlen($text, 'UTF-8') > 60 || str_contains($text, "\n");
            $cells[] = ['wide' => $wide, 'html' => '<span class="lbl">' . $this->esc(mb_strtoupper($label, 'UTF-8')) . '</span><br/>'
                . (is_bool($value) || in_array($text, ['Oui', 'Non'], true)
                    ? $this->pill($text === 'Oui')
                    : '<span style="font-size:' . $size . ';color:' . self::INK . ';">' . nl2br($this->esc($text)) . '</span>')];
        }

        $html = '<table cellpadding="5" cellspacing="0" width="100%">';
        $row = [];
        $flush = function () use (&$row, &$html): void {
            if ($row === []) {
                return;
            }
            $html .= '<tr nobr="true">';
            foreach ($row as $c) {
                $html .= '<td width="50%" style="border-bottom:0.3px solid ' . self::LINE . ';">' . $c . '</td>';
            }
            if (count($row) === 1) {
                $html .= '<td width="50%" style="border-bottom:0.3px solid ' . self::LINE . ';"></td>';
            }
            $html .= '</tr>';
            $row = [];
        };
        foreach ($cells as $c) {
            if ($c['wide']) {
                $flush();
                $html .= '<tr nobr="true"><td width="100%" colspan="2" style="border-bottom:0.3px solid ' . self::LINE . ';">' . $c['html'] . '</td></tr>';
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

        return $this->sectionTitle('Identité liée au dossier')
            . '<table cellpadding="8" cellspacing="0" width="100%" style="background-color:' . self::ACCENT_SOFT . ';"><tr>'
            . ($photo !== '' ? '<td width="16%">' . $photo . '</td>' : '')
            . '<td width="' . ($photo !== '' ? '84%' : '100%') . '">'
            . '<span class="lbl">' . $this->esc((string) ($p['ref'] ?? '')) . '</span><br/>'
            . '<span style="font-size:13px;font-weight:bold;color:' . self::INK . ';">' . $this->esc((string) $p['name']) . '</span>'
            . ($alias !== '' ? ' <span style="font-size:10px;color:' . self::MUTED . ';">« ' . $this->esc($alias) . ' »</span>' : '')
            . ($bits !== [] ? '<br/><span style="font-size:8px;color:' . self::INK . ';">' . implode(' &nbsp;·&nbsp; ', $bits) . '</span>' : '')
            . '</td></tr></table>';
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
            return '<span style="color:' . self::MUTED . ';">—</span>';
        }
        $score = (ord('F') - ord($m[1])) + (6 - (int) $m[2]); // 0 (F6) → 10 (A1)
        $bg = $score >= 7 ? '#15803d' : ($score >= 4 ? '#b45309' : '#b91c1c');
        $size = $small ? '8px' : '12px';

        return '<span style="background-color:' . $bg . ';color:#ffffff;font-size:' . $size . ';font-weight:bold;">&nbsp;' . $code . '&nbsp;</span>';
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
        return '<span style="background-color:' . ($yes ? '#dcfce7' : '#fef3c7') . ';color:' . ($yes ? '#166534' : '#92400e')
            . ';font-size:8.5px;font-weight:bold;">&nbsp;' . ($yes ? 'OUI' : 'NON') . '&nbsp;</span>';
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

    // ------------------------------------------------------------------ moteur

    /**
     * @param list<array{path:string,label:string}> $images
     */
    private function renderPdf(string $html, string $title, string $filename, string $orientation, string $footerRef, array $images): Response
    {
        return TrainingCertificatePdfEngine::suppressTcpdfPhpDeprecationsWhile(function () use (
            $html,
            $title,
            $filename,
            $orientation,
            $footerRef,
            $images
        ): Response {
            if (!TrainingCertificatePdfEngine::ensureTcpdfLoaded()) {
                return (new Response())->setStatusCode(503)->setBody('<p>Export PDF indisponible pour le moment.</p>');
            }

            $binary = $this->buildPdf($html, $title, $orientation, $footerRef, $images);

            return (new Response())
                ->header('Content-Type', 'application/pdf')
                ->header('Content-Disposition', 'attachment; filename="' . $filename . '"')
                ->header('Cache-Control', 'private, no-store')
                ->setBody($binary);
        });
    }

    /**
     * Construit le binaire PDF (TCPDF déjà chargé).
     *
     * @param list<array{path:string,label:string}> $images
     */
    public function buildPdf(string $html, string $title, string $orientation, string $footerRef, array $images = []): string
    {
        $pdf = new class ($orientation, 'mm', 'A4', true, 'UTF-8', false) extends \TCPDF {
            public string $sseRef = '';
            public string $sseMarking = '';

            public function Header(): void
            {
                $l = $this->lMargin;
                $r = $this->w - $this->rMargin;
                $this->SetFillColor(15, 81, 50);
                $this->Rect(0, 0, $this->w, 2.2, 'F');
                $this->SetY(5);
                $this->SetX($l);
                $this->SetFont('helvetica', 'B', 7);
                $this->SetTextColor(15, 81, 50);
                $this->Cell(($r - $l) / 2, 4, 'ATHENA · SECTION SÉCURITÉ ET ENQUÊTES', 0, 0, 'L');
                $this->SetTextColor(185, 28, 28);
                $this->Cell(($r - $l) / 2, 4, $this->sseMarking, 0, 0, 'R');
            }

            public function Footer(): void
            {
                $l = $this->lMargin;
                $r = $this->w - $this->rMargin;
                $col = ($r - $l) / 3;
                $this->SetY(-12);
                $this->SetDrawColor(226, 232, 240);
                $this->SetLineWidth(0.3);
                $this->Line($l, $this->GetY(), $r, $this->GetY());
                $this->SetY(-10);
                $this->SetX($l);
                $this->SetFont('helvetica', '', 7);
                $this->SetTextColor(100, 116, 139);
                $this->Cell($col, 5, $this->sseRef, 0, 0, 'L');
                $this->SetFont('helvetica', 'B', 7);
                $this->SetTextColor(185, 28, 28);
                $this->Cell($col, 5, $this->sseMarking, 0, 0, 'C');
                $this->SetFont('helvetica', '', 7);
                $this->SetTextColor(100, 116, 139);
                $this->Cell($col, 5, 'Page ' . $this->getAliasNumPage() . ' / ' . $this->getAliasNbPages(), 0, 0, 'R');
            }
        };
        $pdf->sseRef = 'Athena SSE · ' . $footerRef;
        $pdf->sseMarking = self::MARKING;
        $pdf->SetCreator('Athena COMSPEC');
        $pdf->SetAuthor('Portail SSE Athena');
        $pdf->SetTitle($title);
        $pdf->SetMargins(14, 14, 14);
        $pdf->SetHeaderMargin(0);
        $pdf->SetAutoPageBreak(true, 18);
        $pdf->setFooterMargin(12);
        $pdf->setCellHeightRatio(1.3);
        $pdf->setPrintHeader(true);
        $pdf->setPrintFooter(true);
        $pdf->SetFont('dejavusans', '', 9);
        $pdf->AddPage();
        $pdf->writeHTML($html, true, false, true, false, '');

        if ($images !== []) {
            $pdf->AddPage();
            $pdf->SetFont('dejavusans', 'B', 11);
            $pdf->SetTextColor(11, 18, 32);
            $pdf->Cell(0, 8, 'PIÈCES IMAGES', 0, 1);
            $w = ($pdf->getPageWidth() - 28 - 6) / 2;
            $x0 = 14;
            $col = 0;
            $y = $pdf->GetY() + 2;
            foreach ($images as $img) {
                $h = $w * 0.75;
                if ($y + $h + 10 > $pdf->getPageHeight() - 18) {
                    $pdf->AddPage();
                    $y = 22;
                    $col = 0;
                }
                $x = $x0 + $col * ($w + 6);
                try {
                    $pdf->Image($img['path'], $x, $y, $w, $h, '', '', '', true, 150, '', false, false, 0, 'CM');
                } catch (\Throwable) {
                }
                $pdf->SetXY($x, $y + $h + 1);
                $pdf->SetFont('dejavusans', '', 7.5);
                $pdf->SetTextColor(100, 116, 139);
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
