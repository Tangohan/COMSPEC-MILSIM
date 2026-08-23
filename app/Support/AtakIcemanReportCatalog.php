<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Référentiel des rapports ATAK Enhanced (Iceman) et Overwatch.
 *
 * Les cinq formulaires de l’app Reports (TIC, Eagle Down, BDA, FRAGO, SALUTE)
 * partagent les mêmes codes que POST /api/atak/reports. Les libellés sont
 * destinés à l’affichage métier ; les clés de champs restent stables pour
 * le JSON structuré.
 */
final class AtakIcemanReportCatalog
{
    /**
     * @var array<string, array{
     *   label: string,
     *   hint: string,
     *   alert_kind: ?string,
     *   persist: bool,
     *   fields: array<string, list<string>>
     * }>
     */
    public const TYPES = [
        'TIC' => [
            'label' => 'Contact',
            'hint' => 'Prise de contact : unité, grille, description.',
            'alert_kind' => 'tic',
            'persist' => true,
            'fields' => [
                'unit' => ['Unit', 'Unité', 'Reported Unit'],
                'grid' => ['Reported Grid', 'Grid', 'Grille'],
                'desc' => ['Desc', 'Description', 'Détail'],
                'send_to' => ['Send To', 'Destinataires'],
            ],
        ],
        'EAGLE_DOWN' => [
            'label' => 'Opérateur à terre',
            'hint' => 'Blessé ou opérateur hors combat, avec état de la LZ.',
            'alert_kind' => 'eagle_down',
            'persist' => true,
            'fields' => [
                'category' => ['Category', 'Catégorie'],
                'dtg' => ['DTG'],
                'callsign' => ['Callsign', 'Indicatif'],
                'grid' => ['Grid', 'Grille'],
                'casualty' => ['Casualty', 'Blessé', 'Victime'],
                'status' => ['Status', 'État', 'Etat'],
                'mechanism' => ['Mechanism', 'Mécanisme', 'Mecanisme'],
                'situation' => ['Situation'],
                'medevac' => ['Medevac', 'Évacuation', 'Evacuation'],
                'lz' => ['LZ', 'Zone d’atterrissage', "Zone d'atterrissage"],
                'treatment' => ['Current Treatment', 'Treatment', 'Traitement'],
                'remarks' => ['Remarks', 'Remarques'],
            ],
        ],
        'BDA' => [
            'label' => 'Bilan des dégâts',
            'hint' => 'Effets observés après un tir ou une frappe.',
            'alert_kind' => 'bda',
            'persist' => true,
            'fields' => [
                'dtg' => ['DTG'],
                'unit' => ['Unit', 'Unité'],
                'trn' => ['TRN'],
                'grid' => ['Grid', 'Grille'],
                'type' => ['Type'],
                'desc' => ['Desc', 'Description'],
                'ordnance' => ['Ordnance', 'Munition'],
                'munitions' => ['Munition(s) Count', 'Munitions Count', 'Munitions', 'Munitions/Method', 'Munitions / méthode'],
                'platform' => ['Platform', 'Plateforme'],
                'ekia' => ['EKIA'],
                'equip' => ['Equip', 'Équipement', 'Equipement'],
                'rating' => ['Rating', 'Notation'],
                'reattack' => ['Reattack', 'Nouvelle attaque'],
                'send_to' => ['Send To', 'Destinataires'],
                'reports' => ['Reports', 'Comptes rendus'],
                'observer' => ['Observer', 'Observateur', 'Émetteur', 'Emetteur'],
                'time' => ['Time', 'Heure'],
                'target' => ['Target/Objective', 'Target', 'Cible', 'Cible / objectif'],
                'damage' => ['Damage Observed', 'Dégâts observés', 'Dégâts'],
                'enemy' => ['Enemy BDA', 'Effets ennemis'],
                'friendly' => ['Friendly/Civilian Effects', 'Effets amis / civils'],
                'remarks' => ['Remarks', 'Remarques'],
            ],
        ],
        'FRAGO' => [
            'label' => 'Ordre fragmentaire',
            'hint' => 'Ordre court : situation, mission, exécution, soutien, commandement.',
            'alert_kind' => 'frago',
            'persist' => true,
            'fields' => [
                'reference' => ['References', 'Reference', 'Référence', 'Reference'],
                'situation' => ['Situation', 'SITUATION'],
                'mission' => ['Mission', 'MISSION'],
                'execution' => ['Execution', 'Exécution', 'EXECUTION'],
                'support' => ['Service Support', 'Soutien', 'Support', 'SERVICE SUPPORT'],
                'command' => ['Command/Signal', 'Command and Signal', 'Commandement', 'COMMAND AND SIGNAL'],
                'acknowledge' => ['Acknowledge', 'Accusé', 'ACKNOWLEDGE'],
            ],
        ],
        'SALUTE' => [
            'label' => 'Compte rendu SALUTE',
            'hint' => 'Taille, activité, lieu, uniforme, heure, équipement.',
            'alert_kind' => 'salute',
            'persist' => true,
            'fields' => [
                'size' => ['Size', 'Taille', 'S'],
                'activity' => ['Activity', 'Activité', 'Activite', 'A'],
                'location' => ['Location', 'Localisation', 'L'],
                'unit' => ['Unit/Uniform', 'Unit', 'Unité', 'Unite', 'U'],
                'time' => ['Time Observed', 'Time', 'Heure', 'T'],
                'equipment' => ['Equipment', 'Équipement', 'Equipement', 'E'],
            ],
        ],
        'SPOTREP' => [
            'label' => 'Observation',
            'hint' => 'Observation ponctuelle (véhicule, mouvement, installation).',
            'alert_kind' => null,
            'persist' => true,
            'fields' => [],
        ],
        'SITREP' => [
            'label' => 'Situation',
            'hint' => 'Situation globale de l’élément.',
            'alert_kind' => null,
            'persist' => true,
            'fields' => [],
        ],
        'CONTACT' => [
            'label' => 'Prise de contact',
            'hint' => 'Contact ennemi depuis le terminal Overwatch.',
            'alert_kind' => null,
            'persist' => true,
            'fields' => [],
        ],
        'OTHER' => [
            'label' => 'Rapport',
            'hint' => 'Rapport libre, hors formulaire.',
            'alert_kind' => null,
            'persist' => true,
            'fields' => [],
        ],
    ];

    /** @return list<string> */
    public static function knownTypeCodes(): array
    {
        return array_keys(self::TYPES);
    }

    public static function isKnown(string $code): bool
    {
        return isset(self::TYPES[strtoupper(trim($code))]);
    }

    public static function normalizeType(string $raw): string
    {
        $code = strtoupper(trim($raw));
        $aliases = [
            'TIC_CLEAR' => 'TIC',
            'CLEAR' => 'TIC',
            'TICCLEAR' => 'TIC',
            'PANIC' => 'EAGLE_DOWN',
            'EAGLEDOWN' => 'EAGLE_DOWN',
            'EAGLE DOWN' => 'EAGLE_DOWN',
            'BDA_REPORT' => 'BDA',
            'BDAREPORT' => 'BDA',
        ];
        if (isset($aliases[$code])) {
            $code = $aliases[$code];
        }
        if (isset(self::TYPES[$code])) {
            return $code;
        }

        return 'OTHER';
    }

    public static function labelFr(string $code): string
    {
        $norm = self::normalizeType($code);
        if ($norm === 'OTHER' && strtoupper(trim($code)) !== 'OTHER' && !isset(self::TYPES[strtoupper(trim($code))])) {
            return self::TYPES['OTHER']['label'];
        }

        return self::TYPES[$norm]['label'] ?? 'Rapport';
    }

    public static function shouldPersist(string $code): bool
    {
        $norm = strtoupper(trim($code));
        if (in_array($norm, ['TIC_CLEAR', 'CLEAR', 'TICCLEAR'], true)) {
            return false;
        }
        $type = self::normalizeType($code);

        return (bool) (self::TYPES[$type]['persist'] ?? false);
    }

    public static function reportTypeForAlertKind(string $kind): ?string
    {
        $kind = strtolower(trim($kind));
        if ($kind === 'tic_clear') {
            return null;
        }
        foreach (self::TYPES as $code => $def) {
            if (($def['alert_kind'] ?? null) === $kind) {
                return $code;
            }
        }

        return null;
    }

    /**
     * @return array<string, string> code => libellé
     */
    public static function routingOptions(): array
    {
        $out = [];
        foreach (self::TYPES as $code => $def) {
            $out[$code] = $def['label'];
        }

        return $out;
    }

    /**
     * @return array<string, mixed>
     */
    public static function forFrontend(): array
    {
        $types = [];
        foreach (self::TYPES as $code => $def) {
            $fields = [];
            foreach ($def['fields'] as $id => $labels) {
                $fields[$id] = [
                    'id' => $id,
                    'label' => self::fieldLabelFr($code, $id),
                ];
            }
            $types[] = [
                'code' => $code,
                'label' => $def['label'],
                'hint' => $def['hint'],
                'alert_kind' => $def['alert_kind'],
                'fields' => $fields,
            ];
        }

        return ['types' => $types];
    }

    public static function fieldLabelFr(string $type, string $fieldId): string
    {
        $labels = [
            'unit' => 'Unité',
            'grid' => 'Grille',
            'desc' => 'Description',
            'send_to' => 'Destinataires',
            'category' => 'Catégorie',
            'dtg' => 'Groupe date-heure',
            'callsign' => 'Indicatif',
            'casualty' => 'Blessé',
            'status' => 'État',
            'mechanism' => 'Mécanisme',
            'situation' => 'Situation',
            'medevac' => 'Évacuation sanitaire',
            'lz' => 'Zone d’atterrissage',
            'treatment' => 'Traitement en cours',
            'remarks' => 'Remarques',
            'trn' => 'Numéro de transmission',
            'type' => 'Nature de la cible',
            'ordnance' => 'Munition employée',
            'munitions' => 'Nombre de munitions',
            'platform' => 'Plateforme',
            'ekia' => 'Pertes ennemies estimées',
            'equip' => 'Matériel observé',
            'rating' => 'Notation',
            'reattack' => 'Nouvelle attaque',
            'reports' => 'Comptes rendus liés',
            'observer' => 'Observateur',
            'time' => 'Heure',
            'target' => 'Cible',
            'damage' => 'Dégâts observés',
            'enemy' => 'Effets ennemis',
            'friendly' => 'Effets amis / civils',
            'reference' => 'Référence',
            'mission' => 'Mission',
            'execution' => 'Exécution',
            'support' => 'Soutien',
            'command' => 'Commandement',
            'acknowledge' => 'Accusé de réception',
            'size' => 'Taille',
            'activity' => 'Activité',
            'location' => 'Localisation',
            'equipment' => 'Équipement',
        ];

        return $labels[$fieldId] ?? $fieldId;
    }

    /**
     * @return array<string, string>
     */
    public static function parseFields(string $type, string $raw): array
    {
        $type = self::normalizeType($type);
        $map = self::TYPES[$type]['fields'] ?? [];
        if ($map === []) {
            return [];
        }

        return self::parseLabeledLines($raw, $map);
    }

    /**
     * @param array<string, list<string>> $fieldMap
     * @return array<string, string>
     */
    public static function parseLabeledLines(string $raw, array $fieldMap): array
    {
        $s = trim(html_entity_decode(strip_tags(str_ireplace(
            ['<br/>', '<br />', '<br>'],
            "\n",
            $raw
        )), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        if ($s === '') {
            return [];
        }
        $s = str_replace(["\r\n", "\r"], "\n", $s);
        $s = preg_replace('/\s*[|·•]+\s*/u', "\n", $s) ?? $s;

        $out = [];
        foreach (preg_split('/\n+/u', $s) ?: [] as $line) {
            $line = trim((string) $line);
            if ($line === '') {
                continue;
            }
            if (preg_match('/^(?:CASUALTY INFORMATION|LZ STATUS|REMARKS|SPOT REPORT\s*\/\s*SALUTE|BDA(?:\s*REPORT)?|FRAGMENTARY ORDER)$/iu', $line) === 1) {
                continue;
            }
            if (preg_match('/^(?:\d+\.\s*)?(.+?)\s*:\s*(.+)$/u', $line, $m) !== 1) {
                continue;
            }
            $label = trim((string) ($m[1] ?? ''));
            $value = trim((string) ($m[2] ?? ''), " \t—\-–·|");
            if ($label === '' || $value === '' || preg_match('/^(n\/?a|—|-)$/i', $value) === 1) {
                continue;
            }
            $id = self::matchFieldId($label, $fieldMap);
            if ($id !== null && !isset($out[$id])) {
                $out[$id] = $value;
            }
        }

        return $out;
    }

    /**
     * @param array<string, list<string>> $fieldMap
     */
    private static function matchFieldId(string $label, array $fieldMap): ?string
    {
        $norm = self::normalizeLabel($label);
        foreach ($fieldMap as $id => $aliases) {
            foreach ($aliases as $alias) {
                if (self::normalizeLabel($alias) === $norm) {
                    return $id;
                }
            }
        }

        return null;
    }

    private static function normalizeLabel(string $label): string
    {
        $label = trim($label);
        $label = preg_replace('/^\d+\.\s*/u', '', $label) ?? $label;
        $label = mb_strtolower($label);
        $label = strtr($label, ['é' => 'e', 'è' => 'e', 'ê' => 'e', 'à' => 'a', 'ù' => 'u', 'ç' => 'c', 'ô' => 'o']);
        $label = preg_replace('/[^a-z0-9]+/u', '', $label) ?? $label;

        return $label;
    }

    /**
     * @param array<string, string> $fields
     */
    public static function priorityFor(string $type, array $fields): string
    {
        $type = self::normalizeType($type);
        if ($type === 'TIC') {
            return 'IMMEDIATE';
        }
        if ($type === 'EAGLE_DOWN') {
            $status = mb_strtolower((string) ($fields['status'] ?? ''));
            $medevac = mb_strtolower((string) ($fields['medevac'] ?? ''));
            if (str_contains($status, 'kia') || str_contains($status, 'critical') || str_contains($medevac, 'urgent')) {
                return 'FLASH';
            }
            if (str_contains($status, 'urgent') || str_contains($medevac, 'priority') || str_contains($medevac, 'priorit')) {
                return 'IMMEDIATE';
            }

            return 'PRIORITY';
        }
        if ($type === 'BDA') {
            $reattack = mb_strtolower((string) ($fields['reattack'] ?? ''));
            if (str_contains($reattack, 'required') || str_contains($reattack, 'requise')) {
                return 'PRIORITY';
            }
        }
        if ($type === 'FRAGO') {
            return 'PRIORITY';
        }

        return 'ROUTINE';
    }

    /**
     * @param array<string, string> $fields
     */
    public static function summaryFromFields(string $type, array $fields, string $fallback = ''): string
    {
        $type = self::normalizeType($type);
        $preferred = match ($type) {
            'TIC' => ['desc', 'unit', 'grid'],
            'EAGLE_DOWN' => ['casualty', 'status', 'mechanism', 'medevac', 'lz'],
            'BDA' => ['type', 'desc', 'rating', 'target', 'damage'],
            'FRAGO' => ['mission', 'situation'],
            'SALUTE' => ['size', 'activity', 'location', 'equipment'],
            default => array_keys($fields),
        };
        $bits = [];
        foreach ($preferred as $id) {
            $v = trim((string) ($fields[$id] ?? ''));
            if ($v === '') {
                continue;
            }
            $bits[] = self::fieldLabelFr($type, $id) . ' : ' . $v;
            if (count($bits) >= 3) {
                break;
            }
        }
        if ($bits !== []) {
            return implode(' — ', $bits);
        }

        return trim($fallback);
    }
}
