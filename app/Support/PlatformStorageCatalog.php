<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Lots vidables depuis l’administration du site (pas le back-office communauté).
 * Les comptes, communautés et réglages ne figurent jamais ici.
 */
final class PlatformStorageCatalog
{
    public const CONFIRM_WORD = 'VIDER';

    /**
     * Tables jamais proposées au vidage, même si elles sont volumineuses.
     *
     * @return list<string>
     */
    public static function protectedTables(): array
    {
        return [
            'users',
            'tenants',
            'roles',
            'permissions',
            'role_permissions',
            'site_role_assignments',
            'user_roles',
            'settings',
            'subscription_plans',
            'tenant_matricule_config',
            'organization_callsign_sequences',
            'personnel_progression_tracks',
            'audit_logs',
        ];
    }

    /**
     * @return list<array{
     *   key: string,
     *   title: string,
     *   blurb: string,
     *   severity: 'high'|'critical',
     *   tables: list<string>,
     *   directories: list<string>
     * }>
     */
    public static function purgeGroups(): array
    {
        return [
            [
                'key' => 'atak_motion',
                'title' => 'Positions et déplacements',
                'blurb' => 'Historique des mouvements sur la carte. Les pastilles encore en liaison ne sont pas effacées.',
                'severity' => 'high',
                'tables' => ['atak_unit_motion_samples', 'atak_unit_motion'],
                'directories' => [],
            ],
            [
                'key' => 'atak_terrain',
                'title' => 'Relief et bâtiments relevés',
                'blurb' => 'Sol, volumes et fichiers de carte déjà relevés. Toutes les communautés sont concernées. Il faudra relever à nouveau.',
                'severity' => 'critical',
                'tables' => ['atak_terrain_chunks', 'atak_terrain_grids'],
                'directories' => ['storage/atak_terrain'],
            ],
            [
                'key' => 'atak_live',
                'title' => 'Situation en cours sur la carte',
                'blurb' => 'Pastilles, croix, formes et dernière activité. La carte du poste se vide jusqu’au prochain passage en liaison.',
                'severity' => 'critical',
                'tables' => ['atak_pings', 'atak_map_shapes', 'atak_last_activity', 'atak_units', 'atak_markers'],
                'directories' => ['storage/cache/atak_sessions', 'storage/cache/atak-activity'],
            ],
            [
                'key' => 'atak_chat',
                'title' => 'Messagerie du poste',
                'blurb' => 'Messages échangés sur le poste de situation.',
                'severity' => 'high',
                'tables' => ['atak_chat_messages'],
                'directories' => [],
            ],
            [
                'key' => 'atak_photos',
                'title' => 'Photos terrain',
                'blurb' => 'Clichés casque, reconnaissance et pièces associées. Les fichiers sur le disque sont aussi retirés.',
                'severity' => 'critical',
                'tables' => ['atak_poi_photos', 'atak_intel_photos', 'recon_images'],
                'directories' => ['public/uploads/recon', 'public/uploads/intel'],
            ],
            [
                'key' => 'atak_analysis',
                'title' => 'Journal d’analyse',
                'blurb' => 'Lignes d’analyse (tirs, contacts, événements) enregistrées pendant les sessions.',
                'severity' => 'high',
                'tables' => ['atak_unit_intel_events'],
                'directories' => [],
            ],
            [
                'key' => 'sse_tx',
                'title' => 'Transmissions de renseignement',
                'blurb' => 'Journal des transmissions reçues du terrain. Les dossiers d’identité ne sont pas touchés.',
                'severity' => 'high',
                'tables' => ['sse_intel_events'],
                'directories' => [],
            ],
            [
                'key' => 'cache_logs',
                'title' => 'Journaux serveur et fichiers temporaires',
                'blurb' => 'Traces techniques et caches. N’efface ni les comptes ni les documents des communautés.',
                'severity' => 'high',
                'tables' => ['cron_job_runs'],
                'directories' => ['storage/logs', 'storage/cache', 'storage/mail-outbox'],
            ],
        ];
    }

    public static function groupByKey(string $key): ?array
    {
        foreach (self::purgeGroups() as $group) {
            if ($group['key'] === $key) {
                return $group;
            }
        }

        return null;
    }

    /**
     * Dossiers mesurés sur le disque (y compris ceux qu’on ne vide pas ici).
     *
     * @return list<array{path: string, label: string, purgeable: bool}>
     */
    public static function watchedDirectories(): array
    {
        return [
            ['path' => 'storage/atak_terrain', 'label' => 'Relief relevé', 'purgeable' => true],
            ['path' => 'public/uploads/recon', 'label' => 'Photos de reconnaissance', 'purgeable' => true],
            ['path' => 'public/uploads/intel', 'label' => 'Photos du poste', 'purgeable' => true],
            ['path' => 'public/uploads/sse', 'label' => 'Pièces SSE', 'purgeable' => false],
            ['path' => 'storage/documents', 'label' => 'Documents des communautés', 'purgeable' => false],
            ['path' => 'storage/logs', 'label' => 'Journaux serveur', 'purgeable' => true],
            ['path' => 'storage/cache', 'label' => 'Cache', 'purgeable' => true],
            ['path' => 'storage/updates', 'label' => 'Paquets de mise à jour', 'purgeable' => false],
            ['path' => 'storage/atak-mod', 'label' => 'Packs jeu des communautés', 'purgeable' => false],
        ];
    }
}
