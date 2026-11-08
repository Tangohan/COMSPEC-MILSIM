<?php

declare(strict_types=1);

namespace App\Services\Cron\Jobs;

use App\Services\Cron\CronJobInterface;
use App\Services\Watch\WatchFlusher;

/**
 * Surveillance de la plateforme : regroupe les mesures de charge et les signaux du mod en base, met à jour l'état de
 * charge, écrit les alertes d'activité anormale (à décider par un humain) et purge les anciennes données par lots.
 *
 * Le trafic web lance déjà ce regroupement toutes les 30 secondes ; cette tâche (toutes les 5 minutes) couvre les
 * périodes sans trafic web et la purge.
 */
final class PlatformWatchCronJob implements CronJobInterface
{
    public function key(): string
    {
        return 'platform_watch';
    }

    public function label(): string
    {
        return 'Surveillance : charge et activité anormale du mod';
    }

    public function description(): string
    {
        return 'Regroupe les mesures de charge par route et par communauté, détecte les envois anormaux du mod (alertes à décider dans '
            . 'l’administration), met à jour l’état de charge et purge les données de plus de quelques jours.';
    }

    public function run(): array
    {
        $r = WatchFlusher::run(time(), null, false, null, true);
        if (($r['ran'] ?? false) !== true) {
            return ['ok' => true, 'summary' => 'Un autre passage était en cours.', 'details' => $r];
        }
        $parts = ['Palier de charge ' . (int) ($r['level'] ?? 0)];
        if (isset($r['load_files'])) {
            $parts[] = (int) $r['load_files'] . ' minute(s) enregistrée(s)';
        }
        if (isset($r['alerts_created'])) {
            $parts[] = (int) $r['alerts_created'] . ' alerte(s) créée(s)';
        }
        if (!empty($r['db_error'])) {
            $parts[] = 'base indisponible : état de charge publié, regroupement reporté';
        }

        return ['ok' => true, 'summary' => implode(' · ', $parts), 'details' => $r];
    }
}
