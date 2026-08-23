<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Libellés et pictogrammes du bureau SSE — jamais de codes techniques à l’écran.
 */
final class SseWorkspaceUi
{
    /**
     * @return array<string, string>
     */
    public static function eventTypeLabels(): array
    {
        return [
            'OBSERVED' => 'Observation',
            'IDENTIFIED' => 'Identification',
            'PHOTOGRAPHED' => 'Photographie',
            'BIOMETRIC_CAPTURE' => 'Acquisition biométrique',
            'BIOMETRIC_SCAN' => 'Acquisition biométrique',
            'DEVICE_SEIZED' => 'Appareil saisi',
            'DEVICE_EXPLOITED' => 'Appareil exploité',
            'SITE_SEARCHED' => 'Site fouillé',
            'VEHICLE_OBSERVED' => 'Véhicule observé',
            'RELATION_CREATED' => 'Relation créée',
            'REPORT_RECEIVED' => 'Rapport reçu',
            'INTEL_VALIDATED' => 'Renseignement validé',
            'INTEL_DISSEMINATED' => 'Renseignement diffusé',
            'EXPLOSIVE_COMPONENT_FOUND' => 'Composant explosif trouvé',
            'CUSTODY_UPDATE' => 'Garde mise à jour',
            'DIGITAL_FINDING' => 'Découverte numérique',
            'REQUIREMENT_CREATED' => 'Exigence créée',
            'REQUIREMENT_UPDATED' => 'Exigence mise à jour',
            'REQUIREMENT_COVERAGE' => 'Couverture d’exigence',
            'TASKING_CREATED' => 'Ordre de collecte',
            'TASKING_UPDATED' => 'Ordre de collecte mis à jour',
            'PRODUCT_CREATED' => 'Compte rendu composé',
            'PRODUCT_VALIDATED' => 'Compte rendu validé',
            'PRODUCT_SANITISED' => 'Compte rendu expurgé',
            'PRODUCT_DIFFUSED' => 'Compte rendu diffusé',
            'SEIZURE' => 'Saisie',
            'ACQUISITION' => 'Acquisition',
            'MESSAGE' => 'Message',
            'CALL' => 'Appel',
            'PHOTO' => 'Photographie',
            'WIFI' => 'Réseau sans fil',
        ];
    }

    public static function eventTypeLabel(string $type): string
    {
        $key = strtoupper(trim($type));
        $map = self::eventTypeLabels();
        if ($key !== '' && isset($map[$key])) {
            return $map[$key];
        }

        return self::humanizeCode($type, 'Événement');
    }

    public static function sourceSystemLabel(string $src): string
    {
        return match (strtoupper(trim($src))) {
            'ACE' => 'ACE',
            'ACE_DOGTAG' => 'Plaque d’identité ACE',
            'BII_IDENTIFI' => 'Identifi BII',
            'ARMA_SSE' => 'Terminal terrain',
            'ZEUS' => 'Zeus',
            'EDEN' => 'Éditeur de mission',
            'MANUAL' => 'Saisie analyste',
            'CTAB' => 'Tablette cTAB',
            'TFAR' => 'Radio TFAR',
            'ACRE' => 'Radio ACRE',
            'UAV' => 'Drone',
            'INTEL_CYCLE' => 'Cycle de renseignement',
            'PORTAL', 'ATHENA' => 'Portail',
            default => self::humanizeCode($src, 'Source inconnue'),
        };
    }

    public static function entityTypeLabel(string $type): string
    {
        $key = strtoupper(trim($type));
        $key = str_replace(['SSE_', 'SSE-'], '', $key);

        return match ($key) {
            'PERSON', 'PERSONS', 'PEOPLE', 'IDENTITY', 'IDENTITE' => 'Personne',
            'CASE', 'CASES', 'DOSSIER' => 'Dossier',
            'SITE', 'SITES', 'LOCATION' => 'Site',
            'VEHICLE', 'VEHICULE' => 'Véhicule',
            'ORG', 'ORGANISATION', 'ORGANIZATION' => 'Organisation',
            'DOCUMENT', 'DOC', 'NOTE', 'FIELD_NOTE' => 'Document',
            'MATERIAL', 'MATERIEL', 'GEAR', 'ITEM' => 'Matériel',
            'EVENT' => 'Événement',
            'INTEREST', 'INTEREST_CASE' => 'Dossier d’intérêt',
            'ACQUISITION' => 'Acquisition',
            'RELATION' => 'Relation',
            default => self::humanizeCode($type, 'Élément'),
        };
    }

    public static function identityTierLabel(mixed $tier): string
    {
        return match (strtoupper(trim((string) ($tier ?? '')))) {
            'DECLARED' => 'Identité déclarée',
            'DOCUMENTARY' => 'Identité documentaire',
            'CONFIRMED' => 'Identité confirmée',
            'UNKNOWN' => 'Identité inconnue',
            '' => '',
            default => self::humanizeCode((string) $tier, ''),
        };
    }

    public static function relationLabel(string $relation): string
    {
        $key = strtolower(trim($relation));

        return match ($key) {
            'same_person', 'same-person', 'sameas', 'same_as' => 'même personne',
            'associe', 'associated', 'linked' => 'associé à',
            'connexe' => 'connexe à',
            'seen_with', 'vu_avec' => 'vu avec',
            'owns', 'possede' => 'possède',
            'located_at', 'au_site' => 'se trouve à',
            'member_of', 'membre' => 'membre de',
            'uses', 'utilise' => 'utilise',
            'parent' => 'parent de',
            'enfant' => 'enfant de',
            default => self::humanizeCode($relation, 'lié à'),
        };
    }

    public static function iconForEventType(string $type): string
    {
        $key = strtoupper(trim($type));

        return match (true) {
            str_contains($key, 'PERSON') || in_array($key, ['IDENTIFIED', 'CUSTODY_UPDATE'], true) => 'person',
            str_contains($key, 'BIOMETRIC') => 'fingerprint',
            str_contains($key, 'PHOTO') => 'photo',
            str_contains($key, 'REPORT') || str_contains($key, 'REQUIREMENT') || str_contains($key, 'PRODUCT')
                || in_array($key, ['INTEL_VALIDATED', 'INTEL_DISSEMINATED'], true) => 'document',
            str_contains($key, 'SITE') => 'site',
            str_contains($key, 'VEHICLE') => 'vehicle',
            str_contains($key, 'DEVICE') || str_contains($key, 'DIGITAL')
                || in_array($key, ['WIFI', 'CALL', 'MESSAGE', 'SEIZURE', 'ACQUISITION'], true) => 'device',
            str_contains($key, 'RELATION') => 'graph',
            str_contains($key, 'TASKING') => 'collect',
            str_contains($key, 'EXPLOSIVE') => 'alert',
            default => 'event',
        };
    }

    public static function iconForInboxKind(string $kind, string $title = '', string $eventType = ''): string
    {
        if ($eventType !== '') {
            return self::iconForEventType($eventType);
        }
        $kind = strtolower(trim($kind));
        $t = mb_strtolower($title);

        return match (true) {
            $kind === 'interest_case' => 'folder',
            $kind === 'suggestion' => 'link',
            $kind === 'relation' => 'graph',
            $kind === 'acquisition' => 'device',
            $kind === 'contradiction' => 'alert',
            str_contains($t, 'personne') || str_contains($t, 'identité') => 'person',
            str_contains($t, 'fiche de renseignement') || str_contains($t, 'rapport') || str_contains($t, 'document') => 'document',
            str_contains($t, 'site') => 'site',
            default => 'event',
        };
    }

    public static function iconForEntityType(string $type): string
    {
        $key = strtoupper(trim($type));

        return match (true) {
            str_contains($key, 'PERSON') || str_contains($key, 'IDENT') => 'person',
            str_contains($key, 'CASE') || str_contains($key, 'DOSSIER') => 'folder',
            str_contains($key, 'SITE') => 'site',
            str_contains($key, 'VEHIC') => 'vehicle',
            str_contains($key, 'DOC') || str_contains($key, 'NOTE') => 'document',
            str_contains($key, 'ORG') => 'org',
            default => 'event',
        };
    }

    public static function formatEventTime(?string $raw): string
    {
        $raw = trim((string) $raw);
        if ($raw === '') {
            return '';
        }
        $ts = strtotime($raw);
        if ($ts === false) {
            return $raw;
        }
        $months = [1 => 'janv.', 2 => 'févr.', 3 => 'mars', 4 => 'avr.', 5 => 'mai', 6 => 'juin',
            7 => 'juil.', 8 => 'août', 9 => 'sept.', 10 => 'oct.', 11 => 'nov.', 12 => 'déc.'];

        return (int) date('j', $ts) . ' ' . ($months[(int) date('n', $ts)] ?? '') . ' · ' . date('H:i', $ts);
    }

    /**
     * @param list<array<string, mixed>> $events
     * @return list<array<string, mixed>>
     */
    public static function collapseTimeline(array $events): array
    {
        $best = [];
        $order = [];
        foreach ($events as $event) {
            if (!is_array($event)) {
                continue;
            }
            $key = mb_strtolower(trim(
                (string) ($event['event_type'] ?? '') . "\n"
                . (string) ($event['summary'] ?? '') . "\n"
                . (string) ($event['entity_uuid'] ?? '')
            ));
            if (trim(str_replace("\n", '', $key)) === '') {
                $key = 'id:' . (int) ($event['id'] ?? 0);
            }
            if (!isset($best[$key])) {
                $order[] = $key;
                $event['repeat_count'] = 1;
                $best[$key] = $event;
                continue;
            }
            $best[$key]['repeat_count'] = (int) ($best[$key]['repeat_count'] ?? 1) + 1;
            if ((string) ($event['event_time'] ?? '') > (string) ($best[$key]['event_time'] ?? '')) {
                $count = (int) $best[$key]['repeat_count'];
                $event['repeat_count'] = $count;
                $best[$key] = $event;
            }
        }
        $out = [];
        foreach ($order as $key) {
            if (isset($best[$key])) {
                $out[] = $best[$key];
            }
        }

        return $out;
    }

    /**
     * @param list<array<string, mixed>> $items
     * @return list<array<string, mixed>>
     */
    public static function collapseInbox(array $items): array
    {
        $seen = [];
        $out = [];
        foreach ($items as $item) {
            if (!is_array($item) || !empty($item['placeholder'])) {
                $out[] = $item;
                continue;
            }
            $kind = (string) ($item['kind'] ?? '');
            $title = mb_strtolower(trim((string) ($item['title'] ?? '')));
            if ($kind === 'event' && $title !== '') {
                if (isset($seen[$title])) {
                    continue;
                }
                $seen[$title] = true;
            }
            $out[] = $item;
        }

        return $out;
    }

    public static function humanizeCode(string $raw, string $fallback = ''): string
    {
        $s = trim($raw);
        if ($s === '') {
            return $fallback;
        }
        $s = str_replace(['-', '.'], '_', $s);
        if (!str_contains($s, '_') && !preg_match('/^[A-Z0-9]+$/', $s)) {
            return $s;
        }
        $parts = preg_split('/_+/', $s) ?: [];
        $parts = array_values(array_filter($parts, static fn (string $p): bool => $p !== ''));
        if ($parts === []) {
            return $fallback !== '' ? $fallback : $s;
        }
        $fr = [
            'requirement' => 'exigence',
            'created' => 'créée',
            'updated' => 'mise à jour',
            'coverage' => 'couverture',
            'tasking' => 'ordre de collecte',
            'product' => 'compte rendu',
            'validated' => 'validé',
            'sanitised' => 'expurgé',
            'sanitized' => 'expurgé',
            'diffused' => 'diffusé',
            'identified' => 'identification',
            'biometric' => 'biométrie',
            'capture' => 'acquisition',
            'documentary' => 'documentaire',
            'declared' => 'déclarée',
            'confirmed' => 'confirmée',
            'person' => 'personne',
            'case' => 'dossier',
            'site' => 'site',
            'intel' => 'renseignement',
            'cycle' => 'cycle',
        ];
        $words = [];
        foreach ($parts as $part) {
            $low = mb_strtolower($part);
            $words[] = $fr[$low] ?? $low;
        }
        $out = implode(' ', $words);

        return mb_strtoupper(mb_substr($out, 0, 1)) . mb_substr($out, 1);
    }

    public static function icon(string $name, string $class = 'iw-item-ico'): string
    {
        $common = ' class="' . htmlspecialchars($class, ENT_QUOTES, 'UTF-8')
            . '" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" '
            . 'stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"';

        return match ($name) {
            'person' => '<svg' . $common . '><circle cx="12" cy="8" r="3.2"/><path d="M5.4 19c1.5-3.3 3.9-5 6.6-5s5.1 1.7 6.6 5"/></svg>',
            'document', 'doc' => '<svg' . $common . '><path d="M7 3.5h7l4 4V20a1 1 0 0 1-1 1H7a1 1 0 0 1-1-1V4.5a1 1 0 0 1 1-1z"/><path d="M14 3.5V8h4M9 12h6M9 15.5h6"/></svg>',
            'folder' => '<svg' . $common . '><path d="M3 7.5A1.5 1.5 0 0 1 4.5 6H9l1.5 2H19.5A1.5 1.5 0 0 1 21 9.5v8A1.5 1.5 0 0 1 19.5 19h-15A1.5 1.5 0 0 1 3 17.5z"/></svg>',
            'photo' => '<svg' . $common . '><rect x="3.5" y="6.5" width="17" height="13" rx="1.5"/><circle cx="12" cy="13" r="3.2"/><path d="M8 6.5 9.2 4.5h5.6L16 6.5"/></svg>',
            'fingerprint' => '<svg' . $common . '><path d="M12 4.5a7.5 7.5 0 0 1 5.2 12.9"/><path d="M12 7.2a4.8 4.8 0 0 1 3.4 8.2"/><path d="M12 10a2.1 2.1 0 0 1 1.4 3.6"/><path d="M8.2 6.2A7.5 7.5 0 0 0 7 19"/><path d="M9.4 9A4.8 4.8 0 0 0 8.6 18"/></svg>',
            'site' => '<svg' . $common . '><path d="M12 21s-7-5.2-7-11a7 7 0 1 1 14 0c0 5.8-7 11-7 11z"/><circle cx="12" cy="10" r="2.3"/></svg>',
            'vehicle' => '<svg' . $common . '><path d="M4 15h16l-1.2-5.2A2 2 0 0 0 16.9 8H7.1a2 2 0 0 0-1.9 1.8L4 15z"/><circle cx="7.5" cy="17.5" r="1.2"/><circle cx="16.5" cy="17.5" r="1.2"/></svg>',
            'device' => '<svg' . $common . '><rect x="7" y="3" width="10" height="18" rx="1.5"/><path d="M11 18.5h2"/></svg>',
            'graph' => '<svg' . $common . '><circle cx="5" cy="12" r="2"/><circle cx="12" cy="5" r="2"/><circle cx="19" cy="12" r="2"/><circle cx="12" cy="19" r="2"/><path d="M6.7 10.7 10.3 6.7M13.7 6.7 17.3 10.7M17.3 13.3 13.7 17.3M10.3 17.3 6.7 13.3"/></svg>',
            'link' => '<svg' . $common . '><circle cx="8" cy="8" r="3"/><circle cx="16" cy="16" r="3"/><path d="M10.2 10.2 13.8 13.8"/></svg>',
            'alert' => '<svg' . $common . '><path d="M12 4 21 19H3z"/><path d="M12 10v4M12 16.5h.01"/></svg>',
            'collect' => '<svg' . $common . '><path d="M4 7h16v11a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2z"/><path d="M8 7V5.5A1.5 1.5 0 0 1 9.5 4h5A1.5 1.5 0 0 1 16 5.5V7"/></svg>',
            'org' => '<svg' . $common . '><path d="M4 20V9l8-5 8 5v11"/><path d="M9 20v-6h6v6"/></svg>',
            default => '<svg' . $common . '><circle cx="12" cy="12" r="7.2"/><path d="M12 8.5v4M12 16.2h.01"/></svg>',
        };
    }
}
