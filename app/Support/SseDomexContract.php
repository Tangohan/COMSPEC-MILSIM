<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Contrat DOMEX lot 1 : nœud d’objet (Eden/Zeus) + paquet de contenu.
 * Aucune technique réelle — uniquement du renseignement scénarisé.
 */
final class SseDomexContract
{
    public const SCHEMA = 'comspec_sse_athena_digital_v0.5';
    public const SCHEMA_LEGACY = 'comspec_sse_athena_digital_v0.4';

    public const DEVICE_TYPES = [
        'ordinateur' => 'Ordinateur',
        'telephone' => 'Téléphone',
        'tablette' => 'Tablette',
        'radio_numerique' => 'Radio',
        'disque_dur' => 'Disque dur',
        'cle_usb' => 'Clé USB',
        'carte_memoire' => 'Carte mémoire',
        'gps' => 'GPS',
        'appareil_photo' => 'Appareil photo',
        'support_amovible' => 'Autre support',
    ];

    public const CONTENT_PROFILES = [
        'logistique' => 'Logistique',
        'commandement' => 'Commandement',
        'personnel' => 'Personnel',
        'radio' => 'Radio / liaisons',
        'generique' => 'Générique',
    ];

    public const SECURITY_TIERS = [
        'faible' => 'Faible',
        'moyenne' => 'Moyenne',
        'elevee' => 'Élevée',
    ];

    public const TERRAIN_STAGES = [
        'non_identifie' => 'Non identifié',
        'decouvert' => 'Découvert',
        'acces_en_cours' => 'Accès en cours',
        'acces_etabli' => 'Accès établi',
        'exploite' => 'Exploité',
    ];

    public const DURATIONS = [
        '30' => '30 secondes',
        '60' => '1 minute',
        '120' => '2 minutes',
        '180' => '3 minutes',
    ];

    public const PACKET_TYPES = [
        'message' => 'Message',
        'document' => 'Document',
        'photo' => 'Photographie',
        'contact' => 'Contact',
        'coordinate' => 'Coordonnée / point',
        'frequency' => 'Fréquence',
        'schedule' => 'Horaire',
        'manifest' => 'Manifeste',
        'location_history' => 'Historique de position',
        'objective' => 'Objectif',
        'marker' => 'Marqueur',
    ];

    public const PACKET_QUALITIES = [
        'complet' => 'Complet',
        'fragment' => 'Fragment',
        'leurre_possible' => 'Peut être un leurre',
    ];

    public const CHANNELS = [
        'physique' => 'Accès physique uniquement',
        'distant' => 'Visible aussi à distance (plus pauvre)',
        'les_deux' => 'Physique et distant',
        'zeus_live' => 'Injecté en cours de mission',
    ];

    public const REVEAL_MODES = [
        'immediat' => 'Immédiat',
        'delai' => 'Après le délai d’exploitation',
        'acces_etabli' => 'Au palier « accès établi »',
    ];

    public const ORIGINS = [
        'scenario' => 'Préparé en mission',
        'terrain' => 'Collecté sur le terrain',
        'zeus_live' => 'Ajouté en cours de mission',
    ];

    public const CONFIDENCES = [
        'non_evalue' => 'Non évalué',
        'a_corroborer' => 'À corroborer',
        'probable' => 'Probable',
        'confirme' => 'Confirmé',
    ];

    public const PACKET_STATUSES = [
        'en_attente' => 'En attente d’un palier',
        'a_exploiter' => 'À exploiter',
        'rattache' => 'Rattaché au dossier',
        'ecarte' => 'Écarté',
    ];

    public const ENTITY_KINDS = [
        'lieu' => 'Lieu',
        'personne' => 'Personne',
        'organisation' => 'Organisation',
        'evenement' => 'Événement',
        'objectif' => 'Objectif',
        'frequence' => 'Fréquence',
        'vehicule' => 'Véhicule',
        'support' => 'Support',
    ];

    /**
     * @param array<string, mixed> $raw
     * @return array<string, mixed>
     */
    public static function normalizeNode(array $raw): array
    {
        $nodeId = self::cleanId((string) ($raw['node_id'] ?? $raw['nodeId'] ?? $raw['id'] ?? ''));
        $deviceType = self::pickKey($raw['device_type'] ?? $raw['deviceType'] ?? $raw['type'] ?? '', self::DEVICE_TYPES, 'ordinateur');
        $duration = (string) ((int) ($raw['duration_s'] ?? $raw['duration'] ?? 180));
        if (!isset(self::DURATIONS[$duration])) {
            $duration = '180';
        }

        return [
            'node_id' => $nodeId,
            'device_type' => $deviceType,
            'owner_label' => self::clip((string) ($raw['owner_label'] ?? $raw['owner'] ?? ''), 160),
            'organization_label' => self::clip((string) ($raw['organization_label'] ?? $raw['organization'] ?? $raw['org'] ?? ''), 160),
            'fictional_network' => self::clip((string) ($raw['fictional_network'] ?? $raw['network'] ?? ''), 80),
            'exploitable' => self::truthy($raw['exploitable'] ?? true),
            'access_physical' => self::truthy($raw['access_physical'] ?? $raw['accessPhysical'] ?? true),
            'access_remote' => self::truthy($raw['access_remote'] ?? $raw['accessRemote'] ?? false),
            'security_tier' => self::pickKey($raw['security_tier'] ?? $raw['security'] ?? '', self::SECURITY_TIERS, 'moyenne'),
            'content_profile' => self::pickKey($raw['content_profile'] ?? $raw['profile'] ?? '', self::CONTENT_PROFILES, 'generique'),
            'duration_s' => (int) $duration,
            'terrain_stage' => self::pickKey($raw['terrain_stage'] ?? $raw['stage'] ?? '', self::TERRAIN_STAGES, 'non_identifie'),
        ];
    }

    /**
     * @param array<string, mixed> $raw
     * @return array<string, mixed>|null
     */
    public static function normalizePacket(array $raw, string $nodeId = '', int $index = 1): ?array
    {
        $text = trim((string) ($raw['body_text'] ?? $raw['text'] ?? $raw['body'] ?? ''));
        $type = self::pickKey($raw['packet_type'] ?? $raw['type'] ?? '', self::PACKET_TYPES, '');
        if ($text === '' && $type === '') {
            return null;
        }
        if ($type === '') {
            $type = 'document';
        }

        $quality = self::pickKey($raw['quality'] ?? '', self::PACKET_QUALITIES, 'complet');
        if ($quality === 'complet') {
            if (self::truthy($raw['is_decoy'] ?? $raw['decoy'] ?? false)) {
                $quality = 'leurre_possible';
            } elseif (self::truthy($raw['is_fragment'] ?? $raw['fragment'] ?? false) || !self::truthy($raw['is_complete'] ?? $raw['complete'] ?? true)) {
                $quality = 'fragment';
            }
        }

        $isDecoy = $quality === 'leurre_possible';
        $isFragment = $quality === 'fragment';
        $confidence = self::pickKey($raw['confidence'] ?? '', self::CONFIDENCES, 'non_evalue');
        if ($confidence === 'confirme') {
            $confidence = 'non_evalue';
        }
        if ($isDecoy || $isFragment) {
            $confidence = 'a_corroborer';
        }

        $reveal = self::pickKey($raw['reveal_after'] ?? $raw['reveal'] ?? '', self::REVEAL_MODES, 'immediat');
        $status = self::pickKey($raw['status'] ?? '', self::PACKET_STATUSES, '');
        if ($status === '') {
            $status = $reveal === 'immediat' ? 'a_exploiter' : 'en_attente';
        }

        $uid = self::cleanId((string) ($raw['packet_uid'] ?? $raw['uid'] ?? ''));
        if ($uid === '') {
            $basis = strtoupper($nodeId !== '' ? $nodeId : 'NODE');
            $uid = $basis . '-P' . max(1, $index);
        }

        $entities = self::normalizeEntities($raw['linked_entities'] ?? $raw['entities'] ?? $raw['linked_entities_text'] ?? []);

        $pos = $raw['position'] ?? $raw['pos'] ?? null;
        $posX = self::coerceCoord($raw['pos_x'] ?? $raw['x'] ?? null);
        $posY = self::coerceCoord($raw['pos_y'] ?? $raw['y'] ?? null);
        $posZ = self::coerceCoord($raw['pos_z'] ?? $raw['z'] ?? null);
        if (is_array($pos)) {
            $posX ??= self::coerceCoord($pos[0] ?? $pos['x'] ?? null);
            $posY ??= self::coerceCoord($pos[1] ?? $pos['y'] ?? null);
            $posZ ??= self::coerceCoord($pos[2] ?? $pos['z'] ?? null);
        }

        $grid = self::clip((string) ($raw['grid_reference'] ?? $raw['grid'] ?? ''), 40);
        $origin = self::pickKey($raw['origin'] ?? '', self::ORIGINS, 'scenario');
        $showOnMap = array_key_exists('show_on_map', $raw) || array_key_exists('showOnMap', $raw)
            ? self::truthy($raw['show_on_map'] ?? $raw['showOnMap'] ?? false)
            : ($origin === 'zeus_live' && $posX !== null && $posY !== null);

        return [
            'packet_uid' => $uid,
            'packet_type' => $type,
            'title' => self::clip((string) ($raw['title'] ?? self::PACKET_TYPES[$type]), 160),
            'body_text' => self::clip($text, 4000),
            'occurred_at_label' => self::clip((string) ($raw['occurred_at'] ?? $raw['occurred_at_label'] ?? ''), 40),
            'quality' => $quality,
            'is_decoy' => $isDecoy,
            'is_fragment' => $isFragment,
            'is_complete' => $quality === 'complet',
            'channel' => self::pickKey($raw['channel'] ?? '', self::CHANNELS, 'physique'),
            'reveal_after' => $reveal,
            'delay_seconds' => max(0, (int) ($raw['delay_seconds'] ?? $raw['delay'] ?? 0)),
            'origin' => $origin,
            'confidence' => $confidence,
            'status' => $status,
            'linked_entities' => $entities,
            'pos_x' => $posX,
            'pos_y' => $posY,
            'pos_z' => $posZ,
            'grid_reference' => $grid,
            'show_on_map' => $showOnMap,
        ];
    }

    /**
     * Point carte bureau : injection chef de mission, ou paquet rattaché au dossier.
     *
     * @param array<string, mixed> $packet
     */
    public static function shouldShowOnMap(array $packet): bool
    {
        $status = (string) ($packet['status'] ?? '');
        if ($status === 'ecarte') {
            return false;
        }
        $x = $packet['pos_x'] ?? null;
        $y = $packet['pos_y'] ?? null;
        if (!is_numeric($x) || !is_numeric($y)) {
            return false;
        }
        if (($packet['origin'] ?? '') === 'zeus_live') {
            return !array_key_exists('show_on_map', $packet) || self::truthy($packet['show_on_map']);
        }
        if ($status === 'rattache') {
            return true;
        }

        return self::truthy($packet['show_on_map'] ?? false);
    }

    public static function coerceCoord(mixed $v): ?float
    {
        if ($v === null || $v === '') {
            return null;
        }
        if (is_numeric($v)) {
            return (float) $v;
        }

        return null;
    }

    /**
     * @param mixed $raw
     * @return list<array{label:string,kind:string}>
     */
    public static function normalizeEntities(mixed $raw): array
    {
        $lines = [];
        if (is_string($raw)) {
            $lines = preg_split('/\r\n|\r|\n/', $raw) ?: [];
        } elseif (is_array($raw)) {
            foreach ($raw as $item) {
                if (is_string($item)) {
                    $lines[] = $item;
                    continue;
                }
                if (!is_array($item)) {
                    continue;
                }
                $label = trim((string) ($item['label'] ?? $item['name'] ?? ''));
                $kind = self::pickKey($item['kind'] ?? $item['type'] ?? '', self::ENTITY_KINDS, 'lieu');
                if ($label !== '') {
                    $lines[] = $label . ' | ' . $kind;
                }
            }
        }

        $out = [];
        $seen = [];
        foreach ($lines as $line) {
            $line = trim((string) $line);
            if ($line === '') {
                continue;
            }
            $parts = array_map('trim', explode('|', $line, 2));
            $label = self::clip($parts[0], 80);
            if ($label === '') {
                continue;
            }
            $kind = self::pickKey($parts[1] ?? 'lieu', self::ENTITY_KINDS, 'lieu');
            $key = mb_strtolower($label) . '|' . $kind;
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $out[] = ['label' => $label, 'kind' => $kind];
            if (count($out) >= 8) {
                break;
            }
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $node
     * @param list<array<string, mixed>> $packets
     * @return array<string, mixed>
     */
    public static function summarizeQueueItem(array $node, array $packets): array
    {
        $decoy = 0;
        $frag = 0;
        foreach ($packets as $p) {
            if (!empty($p['is_decoy'])) {
                $decoy++;
            }
            if (!empty($p['is_fragment'])) {
                $frag++;
            }
        }

        return [
            'node_id' => (string) ($node['node_id'] ?? $node['node_key'] ?? ''),
            'device_type_label' => self::DEVICE_TYPES[(string) ($node['device_type'] ?? '')] ?? 'Support',
            'origin_label' => self::ORIGINS[(string) ($packets[0]['origin'] ?? 'terrain')] ?? 'Collecté sur le terrain',
            'packet_count' => count($packets),
            'quality_note' => self::qualityNote($decoy, $frag, count($packets)),
        ];
    }

    public static function qualityNote(int $decoy, int $frag, int $total): string
    {
        $bits = [];
        if ($total > 0) {
            $bits[] = $total === 1 ? '1 paquet' : $total . ' paquets';
        }
        if ($frag > 0) {
            $bits[] = $frag === 1 ? 'dont 1 fragment' : 'dont ' . $frag . ' fragments';
        }
        if ($decoy > 0) {
            $bits[] = $decoy === 1 ? '1 élément à corroborer' : $decoy . ' éléments à corroborer';
        }

        return $bits === [] ? 'Rien à exploiter' : implode(', ', $bits);
    }

    public static function cleanId(string $raw): string
    {
        $s = strtoupper(trim($raw));
        $s = preg_replace('/[^A-Z0-9._-]/', '-', $s) ?? '';
        $s = trim($s, '-');

        return self::clip($s, 40);
    }

    /**
     * @param array<string, string> $allowed
     */
    public static function pickKey(mixed $raw, array $allowed, string $default): string
    {
        $aliases = [
            'laptop' => 'ordinateur',
            'computer' => 'ordinateur',
            'pc' => 'ordinateur',
            'phone' => 'telephone',
            'smartphone' => 'telephone',
            'radio' => 'radio_numerique',
            'usb' => 'cle_usb',
            'low' => 'faible',
            'medium' => 'moyenne',
            'high' => 'elevee',
            'logistics' => 'logistique',
            'command' => 'commandement',
            'personal' => 'personnel',
            'generic' => 'generique',
            'complete' => 'complet',
            'fragmentary' => 'fragment',
            'decoy' => 'leurre_possible',
            'physical' => 'physique',
            'remote' => 'distant',
            'both' => 'les_deux',
            'immediate' => 'immediat',
            'delay' => 'delai',
            'access_established' => 'acces_etabli',
            'authored' => 'scenario',
            'unassessed' => 'non_evalue',
            'to_corroborate' => 'a_corroborer',
            'confirmed' => 'confirme',
            'queued' => 'en_attente',
            'to_exploit' => 'a_exploiter',
            'linked' => 'rattache',
            'dismissed' => 'ecarte',
            'place' => 'lieu',
            'person' => 'personne',
            'org' => 'organisation',
            'event' => 'evenement',
            'unidentified' => 'non_identifie',
            'discovered' => 'decouvert',
            'accessing' => 'acces_en_cours',
            'established' => 'acces_etabli',
            'exploited' => 'exploite',
        ];
        $s = strtolower(trim((string) $raw));
        $s = str_replace(['-', ' '], '_', $s);
        if ($s !== '' && isset($aliases[$s])) {
            $s = $aliases[$s];
        }
        if ($s !== '' && isset($allowed[$s])) {
            return $s;
        }

        return $default;
    }

    public static function truthy(mixed $v): bool
    {
        if (is_bool($v)) {
            return $v;
        }
        if (is_int($v) || is_float($v)) {
            return (int) $v !== 0;
        }
        $s = strtolower(trim((string) $v));

        return in_array($s, ['1', 'true', 'yes', 'oui', 'on'], true);
    }

    public static function clip(string $s, int $max): string
    {
        $s = trim($s);
        if (mb_strlen($s) <= $max) {
            return $s;
        }

        return mb_substr($s, 0, $max);
    }
}
