<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Alertes tactiques structurées (inspiré Iceman ATAK_Alerts : TIC / CLEAR / FRAGO / SALUTE / Eagle Down).
 * Préfixe messagerie : « ALERTE TACTIQUE|KIND|callsign|grid|x|y|summary »
 */
final class TacticalAlertParser
{
    private const ALERT_PREFIX = 'ALERTE TACTIQUE';

    /** Fenêtre d’affichage des alertes actives (secondes). */
    public const ACTIVE_WINDOW_SECONDS = 4 * 60 * 60;

    /** @var list<string> */
    public const KINDS = [
        'tic',
        'tic_clear',
        'frago',
        'salute',
        'eagle_down',
        'bda',
    ];

    /**
     * @return array{
     *   is_tactical: bool,
     *   kind: string,
     *   kind_label: string,
     *   call_sign: string,
     *   grid: string,
     *   pos_x: ?float,
     *   pos_y: ?float,
     *   summary: string,
     *   severity: string
     * }|null
     */
    public static function parse(?string $body): ?array
    {
        try {
            $body = trim((string) $body);
            if ($body === '') {
                return null;
            }

            $body = self::stripCommsPrefix($body);
            $upper = mb_strtoupper($body);

            if (!str_starts_with($upper, 'ALERTE TACTIQUE') && !str_starts_with($upper, 'ALERTE TACTIQUE|')) {
                // Réglages d’affichage camps (pas une alerte, mais même canal)
                return null;
            }

            $parts = array_map('trim', explode('|', $body));
            // [0]=préfixe [1]=kind [2]=callsign [3]=grid [4]=x [5]=y [6]=summary…
            $kindRaw = strtoupper((string) ($parts[1] ?? 'TIC'));
            $kind = self::normalizeKind($kindRaw);
            $callSign = (string) ($parts[2] ?? '');
            $grid = (string) ($parts[3] ?? '');
            $posX = self::parseCoord($parts[4] ?? null);
            $posY = self::parseCoord($parts[5] ?? null);
            $tailParts = array_values(array_filter(
                array_slice($parts, 6),
                static fn ($p) => trim((string) $p) !== ''
            ));

            $orderId = '';
            if ($tailParts !== [] && preg_match('/^(?:ORDER_ID|ATHENA_ORDER_ID)=(.+)$/i', (string) $tailParts[0], $om) === 1) {
                $rawOid = trim((string) ($om[1] ?? ''));
                array_shift($tailParts);
                // Ancien sendTacticalAlert collait le reste après « · » dans le même champ.
                if (preg_match('/^([A-Za-z0-9_.:\-]+)\s*[·|]\s*(.+)$/u', $rawOid, $split) === 1) {
                    $orderId = trim((string) $split[1]);
                    array_unshift($tailParts, trim((string) $split[2]));
                } elseif (preg_match('/^([A-Za-z0-9_.:\-]+)$/', $rawOid) === 1) {
                    $orderId = $rawOid;
                }
            }

            $summary = trim(implode(' — ', $tailParts));
            if ($orderId === '' && preg_match('/(?:ORDER_ID|ATHENA_ORDER_ID)=([A-Za-z0-9_.:\-]+)/i', $summary, $om2) === 1) {
                $orderId = trim((string) ($om2[1] ?? ''));
                $summary = trim((string) (preg_replace(
                    '/\s*(?:ORDER_ID|ATHENA_ORDER_ID)=[A-Za-z0-9_.:\-]+\s*/i',
                    ' ',
                    $summary
                ) ?? $summary));
            }
            $rawForBda = $summary;
            $summary = self::cleanSummary($summary, $kind, $callSign, $grid);
            if ($summary === '') {
                $summary = self::kindLabelFr($kind);
            }

            $salute = null;
            if ($kind === 'salute') {
                $salute = self::parseSaluteFields($tailParts);
                if ($salute === null || self::saluteIsEmpty($salute)) {
                    $fromCatalog = AtakIcemanReportCatalog::parseFields('SALUTE', $rawForBda);
                    if ($fromCatalog !== []) {
                        $salute = array_merge([
                            'size' => '', 'activity' => '', 'location' => '',
                            'unit' => '', 'time' => '', 'equipment' => '',
                        ], $fromCatalog);
                    }
                }
                if ($salute !== null) {
                    $built = self::formatSaluteSummary($salute);
                    if ($built !== '') {
                        $summary = $built;
                    }
                }
            }

            $frago = null;
            if ($kind === 'frago') {
                $frago = self::parseFragoSections($summary);
                if ($frago === []) {
                    $frago = self::parseFragoSections($rawForBda);
                }
                if ($frago === []) {
                    $frago = AtakIcemanReportCatalog::parseFields('FRAGO', $rawForBda);
                }
            }

            $bda = null;
            if ($kind === 'bda') {
                $bda = self::parseBdaFields($rawForBda !== '' ? $rawForBda : $summary);
                $fromCatalog = AtakIcemanReportCatalog::parseFields('BDA', $rawForBda !== '' ? $rawForBda : $summary);
                if ($fromCatalog !== []) {
                    $bda = array_merge($bda ?? [], $fromCatalog);
                }
                if ($bda !== null) {
                    $builtBda = self::formatBdaSummary($bda);
                    if ($builtBda === '') {
                        $builtBda = AtakIcemanReportCatalog::summaryFromFields('BDA', $bda);
                    }
                    if ($builtBda !== '') {
                        $summary = $builtBda;
                    }
                }
            }

            $eagle = null;
            if ($kind === 'eagle_down') {
                $eagle = AtakIcemanReportCatalog::parseFields('EAGLE_DOWN', $rawForBda !== '' ? $rawForBda : $summary);
                if ($eagle !== []) {
                    $builtEagle = AtakIcemanReportCatalog::summaryFromFields('EAGLE_DOWN', $eagle);
                    if ($builtEagle !== '') {
                        $summary = $builtEagle;
                    }
                } else {
                    $eagle = null;
                }
            }

            $tic = null;
            if ($kind === 'tic') {
                $tic = AtakIcemanReportCatalog::parseFields('TIC', $rawForBda !== '' ? $rawForBda : $summary);
                if ($tic !== []) {
                    $builtTic = AtakIcemanReportCatalog::summaryFromFields('TIC', $tic, $summary);
                    if ($builtTic !== '') {
                        $summary = $builtTic;
                    }
                } else {
                    $tic = null;
                }
            }

            $out = [
                'is_tactical' => true,
                'kind' => $kind,
                'kind_label' => self::kindLabelFr($kind),
                'call_sign' => $callSign,
                'grid' => $grid,
                'pos_x' => $posX,
                'pos_y' => $posY,
                'summary' => $summary,
                'severity' => self::severityForKind($kind),
            ];
            if ($orderId !== '') {
                $out['order_id'] = $orderId;
            }
            if ($salute !== null) {
                $out['salute'] = $salute;
            }
            if ($frago !== null && $frago !== []) {
                $out['frago'] = $frago;
            }
            if ($bda !== null && $bda !== []) {
                $out['bda'] = $bda;
            }
            if ($eagle !== null && $eagle !== []) {
                $out['eagle_down'] = $eagle;
            }
            if ($tic !== null && $tic !== []) {
                $out['tic'] = $tic;
            }

            return $out;
        } catch (\Throwable) {
            // Ligne chat malformée / regex invalide : ne pas faire échouer l’endpoint.
            return null;
        }
    }

    /**
     * Construit le corps messagerie pour un SALUTE structuré.
     *
     * @param array{size?:string,activity?:string,location?:string,unit?:string,time?:string,equipment?:string} $fields
     */
    public static function buildSaluteBody(array $fields): string
    {
        $map = [
            'S' => trim((string) ($fields['size'] ?? $fields['S'] ?? '')),
            'A' => trim((string) ($fields['activity'] ?? $fields['A'] ?? '')),
            'L' => trim((string) ($fields['location'] ?? $fields['L'] ?? '')),
            'U' => trim((string) ($fields['unit'] ?? $fields['U'] ?? '')),
            'T' => trim((string) ($fields['time'] ?? $fields['T'] ?? '')),
            'E' => trim((string) ($fields['equipment'] ?? $fields['E'] ?? '')),
        ];
        $chunks = [];
        foreach ($map as $k => $v) {
            $chunks[] = $k . '=' . str_replace(['|', "\n", "\r"], ['/', ' ', ''], $v);
        }

        return implode('|', $chunks);
    }

    /**
     * @param list<string> $parts
     * @return array{size:string,activity:string,location:string,unit:string,time:string,equipment:string}|null
     */
    public static function parseSaluteFields(array $parts): ?array
    {
        $out = [
            'size' => '',
            'activity' => '',
            'location' => '',
            'unit' => '',
            'time' => '',
            'equipment' => '',
        ];
        $keyMap = [
            'S' => 'size', 'SIZE' => 'size', 'TAILLE' => 'size',
            'A' => 'activity', 'ACTIVITY' => 'activity', 'ACTIVITE' => 'activity', 'ACTIVITÉ' => 'activity',
            'L' => 'location', 'LOCATION' => 'location', 'LOCALISATION' => 'location',
            'U' => 'unit', 'UNIT' => 'unit', 'UNITE' => 'unit', 'UNITÉ' => 'unit',
            'T' => 'time', 'TIME' => 'time', 'HEURE' => 'time',
            'E' => 'equipment', 'EQUIPMENT' => 'equipment', 'EQUIPEMENT' => 'equipment', 'ÉQUIPEMENT' => 'equipment',
        ];
        $found = false;
        foreach ($parts as $part) {
            $part = trim((string) $part);
            if ($part === '') {
                continue;
            }
            // Délimiteur ~ pour éviter toute fermeture précoce sur « / ».
            // Iceman numérote : « 1. Size: 4 pax ».
            if (preg_match('~^(?:\d+\.\s*)?([\p{L}]+)\s*[:=]\s*(.*)$~u', $part, $m) === 1) {
                $k = mb_strtoupper(trim($m[1]));
                $v = trim($m[2]);
                if (isset($keyMap[$k])) {
                    $out[$keyMap[$k]] = $v;
                    $found = true;
                }
            }
        }
        // Ancien gabarit brut « Taille — Activité — … » sans valeurs → pas de structure.
        if (!$found) {
            $joined = implode(' — ', $parts);
            if (preg_match('~^Taille\s*[-\x{2014}]+\s*Activit~iu', $joined) === 1) {
                return [
                    'size' => '',
                    'activity' => '',
                    'location' => '',
                    'unit' => '',
                    'time' => '',
                    'equipment' => '',
                ];
            }

            return null;
        }

        return $out;
    }

    /**
     * @param array{size?:string,activity?:string,location?:string,unit?:string,time?:string,equipment?:string} $salute
     */
    public static function formatSaluteSummary(array $salute): string
    {
        $labels = [
            'size' => 'Taille',
            'activity' => 'Activité',
            'location' => 'Localisation',
            'unit' => 'Unité',
            'time' => 'Heure',
            'equipment' => 'Équipement',
        ];
        $bits = [];
        foreach ($labels as $k => $lab) {
            $v = trim((string) ($salute[$k] ?? ''));
            if ($v !== '') {
                $bits[] = $lab . ' : ' . $v;
            }
        }

        return implode(' · ', $bits);
    }

    /**
     * @param array<string, string> $salute
     */
    private static function saluteIsEmpty(array $salute): bool
    {
        foreach ($salute as $v) {
            if (trim((string) $v) !== '') {
                return false;
            }
        }

        return true;
    }

    private static function parseCoord(mixed $raw): ?float
    {
        if ($raw === null || $raw === '') {
            return null;
        }
        if (is_int($raw) || is_float($raw)) {
            $f = (float) $raw;

            return is_finite($f) ? $f : null;
        }
        $s = trim(str_replace(',', '.', (string) $raw));
        if ($s === '' || !is_numeric($s)) {
            return null;
        }
        $f = (float) $s;

        return is_finite($f) ? $f : null;
    }

    /**
     * Messages techniques (sync affichage carte…) — hors journal radio / tchat opérateur.
     */
    public static function isHiddenSystemChatBody(?string $body): bool
    {
        return self::parseFactionSettings($body) !== null;
    }

    /**
     * Parse « REGLAGES AFFICHAGE|adversaire=1|independants=1|civils=1 »
     *
     * @return array{show_east: bool, show_guer: bool, show_civ: bool}|null
     */
    public static function parseFactionSettings(?string $body): ?array
    {
        $body = trim((string) $body);
        if ($body === '') {
            return null;
        }
        $body = self::stripCommsPrefix($body);
        $upper = mb_strtoupper($body);
        // Variantes / tronquages éventuels côté affichage journal
        if (
            !str_starts_with($upper, 'REGLAGES AFFICHAGE')
            && !str_starts_with($upper, 'AFFICHAGE|ADVERSAIRE=')
            && !str_contains($upper, 'REGLAGES AFFICHAGE|')
        ) {
            // Cas journal : auteur « REGLAGES » + corps « AFFICHAGE|adversaire=… »
            if (!(str_starts_with($upper, 'AFFICHAGE|') && str_contains($upper, 'ADVERSAIRE='))) {
                return null;
            }
        }

        $showEast = true;
        $showGuer = true;
        $showCiv = true;
        foreach (explode('|', $body) as $chunk) {
            $chunk = trim($chunk);
            if (!str_contains($chunk, '=')) {
                continue;
            }
            [$k, $v] = array_map('trim', explode('=', $chunk, 2));
            $k = mb_strtolower($k);
            $on = in_array($v, ['1', 'true', 'oui', 'yes'], true);
            if (in_array($k, ['adversaire', 'east', 'opfor', 'showeast'], true)) {
                $showEast = $on;
            }
            if (in_array($k, ['independants', 'guer', 'independent', 'showguer'], true)) {
                $showGuer = $on;
            }
            if (in_array($k, ['civils', 'civ', 'civilian', 'showciv'], true)) {
                $showCiv = $on;
            }
        }

        return [
            'show_east' => $showEast,
            'show_guer' => $showGuer,
            'show_civ' => $showCiv,
        ];
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>|null
     */
    public static function enrichChatRow(array $row): ?array
    {
        $parsed = self::parse(isset($row['body']) ? (string) $row['body'] : null);
        if ($parsed === null) {
            return null;
        }

        return array_merge($parsed, [
            'id' => (int) ($row['id'] ?? 0),
            'author' => (string) ($row['author'] ?? ''),
            'created_at' => (string) ($row['created_at'] ?? ''),
            'map_id' => (int) ($row['map_id'] ?? 0),
        ]);
    }

    public static function kindLabelFr(string $kind): string
    {
        return match (self::normalizeKind($kind)) {
            'tic' => 'Contact',
            'tic_clear' => 'Fin de contact',
            'frago' => 'Ordre fragmentaire',
            'salute' => 'Compte rendu SALUTE',
            'eagle_down' => 'Opérateur à terre',
            'bda' => 'Bilan des dégâts',
            default => 'Alerte',
        };
    }

    public static function severityForKind(string $kind): string
    {
        return match (self::normalizeKind($kind)) {
            'eagle_down', 'tic' => 'critical',
            'frago', 'bda' => 'high',
            'salute' => 'medium',
            'tic_clear' => 'info',
            default => 'medium',
        };
    }

    public static function isWithinActiveWindow(?string $createdAt): bool
    {
        if ($createdAt === null || $createdAt === '') {
            return true;
        }
        $ts = strtotime($createdAt);
        if ($ts === false) {
            return true;
        }

        return (time() - $ts) <= self::ACTIVE_WINDOW_SECONDS;
    }

    public static function normalizeKind(string $raw): string
    {
        $k = strtoupper(trim(str_replace([' ', '-'], '_', $raw)));
        return match ($k) {
            'TIC' => 'tic',
            'CLEAR', 'TIC_CLEAR', 'TICCLEAR' => 'tic_clear',
            'FRAGO' => 'frago',
            'SALUTE' => 'salute',
            'EAGLE_DOWN', 'EAGLEDOWN', 'PANIC' => 'eagle_down',
            'BDA', 'BDA_REPORT', 'BDAREPORT' => 'bda',
            default => 'tic',
        };
    }

    /**
     * Retire les préfixes redondants (type / indicatif / grille) déjà présents dans les champs dédiés.
     */
    public static function cleanSummary(string $summary, string $kind, string $callSign = '', string $grid = ''): string
    {
        $summary = trim($summary);
        if ($summary === '') {
            return '';
        }

        // ORDER_ID=… résiduel
        if (preg_match('/^ORDER_ID=[^\s|—\-]+[\s|—\-]*/iu', $summary) === 1) {
            $summary = trim((string) preg_replace('/^ORDER_ID=[^\s|—\-]+[\s|—\-]*/iu', '', $summary));
        }

        $label = self::kindLabelFr($kind);
        $patterns = [
            '/^' . preg_quote($label, '/') . '\s*[—\-–·|]+\s*/iu',
            '/^FRAGO\s*[—\-–·|]+\s*/iu',
            '/^Bilan des dégâts\s*[—\-–·|]+\s*/iu',
            '/^Opérateur à terre\s*[—\-–·|]+\s*/iu',
            '/^Contact\s*[—\-–·|]+\s*/iu',
            '/^Fin de contact\s*[—\-–·|]+\s*/iu',
        ];
        if ($callSign !== '') {
            $patterns[] = '/^' . preg_quote($callSign, '/') . '\s*[—\-–·|]+\s*/iu';
            // Avec ou sans séparateur final (souvent fin de chaîne)
            $patterns[] = '/^' . preg_quote($callSign, '/') . '\s*[·•]\s*grille\s+\S+(?:\s*[—\-–·|]+\s*)?/iu';
            $patterns[] = '/^' . preg_quote($callSign, '/') . '\s*[—\-–]\s*Grille\s+\S+(?:\s*[—\-–·|]+\s*)?/iu';
        }
        if ($grid !== '') {
            $patterns[] = '/^Grille\s+' . preg_quote($grid, '/') . '(?:\s*[—\-–·|]+\s*)?/iu';
            $patterns[] = '/^[·•]\s*grille\s+' . preg_quote($grid, '/') . '(?:\s*[—\-–·|]+\s*)?/iu';
        }
        $patterns[] = '/^grille\s+\S+(?:\s*[—\-–·|]+\s*)?/iu';
        $patterns[] = '/^' . preg_quote($label, '/') . '$/iu';

        $prev = null;
        while ($prev !== $summary) {
            $prev = $summary;
            foreach ($patterns as $re) {
                $summary = trim((string) preg_replace($re, '', $summary));
            }
        }

        // « FRAGO — CS · grille X — » collé en tête des anciens messages
        $summary = trim((string) preg_replace(
            '/^FRAGO\s*[—\-–]\s*[^\—\-–]+[·•]\s*grille\s+\S+\s*[—\-–]\s*/iu',
            '',
            $summary
        ));

        return trim($summary, " \t—\-–·|");
    }

    /**
     * Libellé court pour le journal d’activité (sans duplication type/indicatif/grille).
     *
     * @param array<string, mixed> $tactical
     */
    public static function activityLabel(array $tactical): string
    {
        $kind = (string) ($tactical['kind'] ?? 'tic');
        $label = self::kindLabelFr($kind);
        $callSign = trim((string) ($tactical['call_sign'] ?? ''));
        $grid = trim((string) ($tactical['grid'] ?? ''));
        $summary = self::cleanSummary((string) ($tactical['summary'] ?? ''), $kind, $callSign, $grid);

        if ($kind === 'frago' && isset($tactical['frago']) && is_array($tactical['frago'])) {
            $fragoLabels = [
                'situation' => 'Situation',
                'mission' => 'Mission',
                'execution' => 'Exécution',
                'support' => 'Soutien',
                'command' => 'Commandement',
            ];
            $bits = [];
            foreach ($fragoLabels as $id => $fr) {
                $v = trim((string) ($tactical['frago'][$id] ?? ''));
                if ($v === '') {
                    continue;
                }
                $bits[] = $fr . ' : ' . $v;
                if (count($bits) >= 2) {
                    break;
                }
            }
            if ($bits !== []) {
                $out = $label . ' — ' . implode(' · ', $bits);
                if ($grid !== '' && mb_stripos($out, $grid) === false) {
                    $out .= ' — Grille ' . $grid;
                }

                return mb_strlen($out) > 160 ? (mb_substr($out, 0, 157) . '…') : $out;
            }
        }

        if ($summary !== '' && mb_strtolower($summary) !== mb_strtolower($label)) {
            $out = $label . ' — ' . $summary;
        } else {
            $out = $label;
        }
        if ($grid !== '' && mb_stripos($out, $grid) === false) {
            $out .= ' — Grille ' . $grid;
        }

        return mb_strlen($out) > 160 ? (mb_substr($out, 0, 157) . '…') : $out;
    }

    /**
     * Champs structurés d’un bilan des dégâts (Iceman ATAK_BDA / Athena).
     *
     * @return array<string, string>|null
     */
    public static function parseBdaFields(string $summary): ?array
    {
        $s = trim(html_entity_decode(strip_tags($summary), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        if ($s === '') {
            return null;
        }
        $s = str_replace(["\r\n", "\r"], "\n", $s);
        $s = preg_replace('/\s*[|·•]+\s*/u', "\n", $s) ?? $s;
        $s = preg_replace('/\s+[—–]\s+(?=\d\.\s)/u', "\n", $s) ?? $s;

        /** @var list<array{0:string,1:string}> */
        $rules = [
            ['observer', '/^(?:Observer|Observateur|Émetteur)\s*:\s*(.+)$/iu'],
            ['grid', '/^(?:Grid|Grille)\s*:\s*(.+)$/iu'],
            ['time', '/^(?:Time|Heure)\s*:\s*(.+)$/iu'],
            ['target', '/^(?:1\.\s*)?(?:Target\/?Objective|Cible(?:\s*\/\s*Objectif)?)\s*:\s*(.+)$/iu'],
            ['damage', '/^(?:2\.\s*)?(?:Damage\s*Observed|Dégâts(?:\s*observés)?)\s*:\s*(.+)$/iu'],
            ['enemy', '/^(?:3\.\s*)?(?:Enemy\s*BDA|Effets\s*ennemis)\s*:\s*(.+)$/iu'],
            ['friendly', '/^(?:4\.\s*)?(?:Friendly\/?Civilian\s*Effects|Effets\s*amis(?:\s*\/\s*civils)?)\s*:\s*(.+)$/iu'],
            ['munitions', '/^(?:5\.\s*)?(?:Munitions\/?Method|Munitions(?:\s*\/\s*méthode)?)\s*:\s*(.+)$/iu'],
            ['remarks', '/^(?:6\.\s*)?(?:Remarks|Remarques)\s*:\s*(.+)$/iu'],
        ];

        $out = [];
        foreach (preg_split('/\n+/u', $s) ?: [] as $line) {
            $line = trim((string) $line);
            if ($line === '' || preg_match('/^BDA(?:\s*REPORT)?$/iu', $line) === 1) {
                continue;
            }
            foreach ($rules as [$id, $re]) {
                if (isset($out[$id])) {
                    continue;
                }
                if (preg_match($re, $line, $m) === 1) {
                    $val = trim((string) ($m[1] ?? ''), " \t—\-–·|");
                    if ($val !== '' && preg_match('/^(n\/?a|—|-)$/i', $val) !== 1) {
                        $out[$id] = $val;
                    }
                    break;
                }
            }
        }

        return $out === [] ? null : $out;
    }

    /**
     * @param array<string, string> $bda
     */
    public static function formatBdaSummary(array $bda): string
    {
        $labels = [
            'type' => 'Nature de la cible',
            'desc' => 'Description',
            'rating' => 'Notation',
            'target' => 'Cible',
            'damage' => 'Dégâts observés',
            'enemy' => 'Effets ennemis',
            'friendly' => 'Effets amis / civils',
            'munitions' => 'Munitions',
            'ekia' => 'Pertes ennemies estimées',
            'reattack' => 'Nouvelle attaque',
            'remarks' => 'Remarques',
        ];
        $bits = [];
        foreach ($labels as $id => $fr) {
            $v = trim((string) ($bda[$id] ?? ''));
            if ($v !== '') {
                $bits[] = $fr . ' : ' . $v;
            }
        }

        return implode(' — ', $bits);
    }

    /**
     * @return array<string, string>
     */
    public static function parseFragoSections(string $summary): array
    {
        $summary = trim($summary);
        if ($summary === '') {
            return [];
        }
        // Corps IceMan HTML → texte plat.
        $summary = html_entity_decode(strip_tags(str_ireplace(['<br/>', '<br>', '<br />'], "\n", $summary)), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $summary = str_replace(["\r\n", "\r"], "\n", $summary);
        $summary = preg_replace('/\s*[|·•]+\s*/u', "\n", $summary) ?? $summary;
        $summary = preg_replace('/\s+[—–]\s+(?=(?:\d\.\s*)?(?:Situation|Mission|Exécution|Execution|Soutien|Support|Commandement|Command)\b)/iu', "\n", $summary) ?? $summary;

        $keys = [
            'reference' => ['References', 'Reference', 'Référence'],
            'situation' => ['Situation', 'SITUATION'],
            'mission' => ['Mission', 'MISSION'],
            'execution' => ['Exécution', 'Execution', 'EXECUTION'],
            'support' => ['Soutien', 'Support', 'SERVICE SUPPORT', 'Service Support'],
            'command' => ['Commandement', 'Command', 'COMMAND AND SIGNAL', 'Command and Signal', 'Command/Signal'],
            'acknowledge' => ['Acknowledge', 'ACKNOWLEDGE', 'Accusé'],
        ];
        $out = [];
        foreach ($keys as $id => $labels) {
            foreach ($labels as $label) {
                $quoted = preg_quote($label, '/');
                if (preg_match(
                    '/(?:^|\n)\s*(?:\d+\.\s*)?' . $quoted . '\s*:\s*(.+?)(?=\n\s*(?:\d+\.\s*)?(?:Situation|SITUATION|Mission|MISSION|Exécution|Execution|EXECUTION|Soutien|Support|SERVICE SUPPORT|Commandement|Command|COMMAND AND SIGNAL)\s*:|\z)/ius',
                    $summary,
                    $m
                ) === 1) {
                    $val = trim((string) ($m[1] ?? ''));
                    $val = trim(preg_replace('/\s+/u', ' ', $val) ?? $val);
                    if ($val !== '' && !preg_match('/^N\/?A$/i', $val)) {
                        $out[$id] = $val;
                        break;
                    }
                }
            }
        }

        return $out;
    }

    /**
     * Payload ordre C2 (SMEAC) à partir d’une alerte FRAGO parsée.
     *
     * @param array<string, mixed> $tactical
     */
    public static function formatFragoOrderPayload(array $tactical): string
    {
        $labels = [
            'situation' => 'Situation',
            'mission' => 'Mission',
            'execution' => 'Exécution',
            'support' => 'Soutien',
            'command' => 'Commandement',
        ];
        $parts = [];
        $frago = isset($tactical['frago']) && is_array($tactical['frago']) ? $tactical['frago'] : [];
        foreach ($labels as $id => $label) {
            $v = trim((string) ($frago[$id] ?? ''));
            if ($v !== '') {
                $parts[] = $label . ': ' . $v;
            }
        }
        if ($parts !== []) {
            return implode(' — ', $parts);
        }

        return trim((string) ($tactical['summary'] ?? ''));
    }

    private static function stripCommsPrefix(string $body): string
    {
        if (preg_match(
            '~^\[\d{1,2}:\d{2}:\d{2}\]\[[A-Za-z0-9_]+\]\[[A-Za-z0-9_]+\]\[[A-Za-z0-9_]+\]\s*([\s\S]+)$~u',
            $body,
            $m
        ) === 1) {
            return trim((string) ($m[1] ?? $body));
        }

        return $body;
    }
}
