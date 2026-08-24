<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Libellés métier — jamais d’enums bruts dans l’UI.
 */
final class MissionPlanningLabels
{
    public static function status(string $status): string
    {
        return match ($status) {
            'draft' => 'Brouillon',
            'published' => 'Publié',
            'live' => 'En session',
            'closed' => 'Clôturé',
            default => 'Brouillon',
        };
    }

    public static function presence(string $status): string
    {
        return match ($status) {
            'vacant' => 'Vacant',
            'open' => 'Ouvert',
            'confirmed' => 'Confirmé',
            'present' => 'Présent',
            'absent' => 'Absent',
            'mismatch' => 'Remplaçant détecté',
            'temporary' => 'Affectation temporaire',
            'unreconciled' => 'Non rapproché',
            default => 'Vacant',
        };
    }

    public static function mode(string $mode): string
    {
        return match ($mode) {
            'preassigned' => 'Affecté à l’avance',
            'detected' => 'Reconnu à la connexion',
            'live' => 'Modifié en session',
            default => 'Affecté à l’avance',
        };
    }

    public static function toVersion(string $version): string
    {
        return match ($version) {
            'planned' => 'Organisation prévue',
            'current' => 'Organisation en cours',
            'final' => 'Organisation finale',
            default => 'Organisation en cours',
        };
    }

    public static function elementKind(string $kind): string
    {
        return match ($kind) {
            'hq' => 'État-major',
            'maneuver' => 'Manœuvre',
            'air' => 'Air',
            'support' => 'Soutien',
            'attachment' => 'Renfort',
            default => 'Autre',
        };
    }

    public static function graphicKind(string $kind): string
    {
        return match ($kind) {
            'ld' => 'Ligne de départ',
            'pl' => 'Ligne de phase',
            'orp' => 'Point de rassemblement',
            'obj' => 'Objectif',
            'lz' => 'Zone de poser',
            'hlz' => 'Zone d’atterrissage',
            'axis' => 'Axe',
            'cp' => 'Point de contrôle',
            'rp' => 'Point de relais',
            default => 'Repère',
        };
    }

    public static function drawState(string $state): string
    {
        return match ($state) {
            'planned' => 'Prévu',
            'current' => 'En cours',
            'completed' => 'Terminé',
            'modified' => 'Modifié en session',
            default => 'Prévu',
        };
    }

    public static function timelineSource(string $source): string
    {
        return match ($source) {
            'planned' => 'Prévu',
            'arma' => 'Terrain',
            'c2' => 'Poste de commandement',
            default => 'Prévu',
        };
    }

    public static function armaLink(string $status): string
    {
        return match ($status) {
            'linked' => 'En liaison',
            'delayed' => 'Liaison dégradée',
            'offline' => 'Hors liaison',
            default => 'Hors liaison',
        };
    }

    public static function taskStatus(string $status): string
    {
        return match ($status) {
            'en_route' => 'En route',
            'holding' => 'En attente',
            'on_station' => 'En station',
            'on_objective' => 'Sur objectif',
            'ready' => 'Prêt',
            'standby' => 'En veille',
            default => 'En veille',
        };
    }

    public static function phase(string $phase): string
    {
        $p = strtoupper(trim($phase));

        return match ($p) {
            'PREPARATION', 'PREP' => 'Préparation',
            'MOVEMENT', 'MOVE' => 'Mouvement',
            'ASSAULT' => 'Assaut',
            'CONSOLIDATION', 'CONSOL' => 'Consolidation',
            'EXFIL' => 'Exfiltration',
            '' => 'Non renseignée',
            default => $phase,
        };
    }

    public static function personLabel(?array $user): string
    {
        if ($user === null) {
            return 'Vacant';
        }
        $callsign = trim((string) ($user['callsign'] ?? ''));
        $name = trim((string) ($user['display_name'] ?? $user['name'] ?? ''));
        if ($callsign !== '' && $name !== '') {
            return $callsign . ' · ' . $name;
        }

        return $callsign !== '' ? $callsign : ($name !== '' ? $name : 'Membre');
    }
}
