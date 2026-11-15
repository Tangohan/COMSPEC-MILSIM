<?php

declare(strict_types=1);

namespace App\Services\Cron\Jobs;

use App\Services\Atak\MarkerCycle\AtakMarkerArchiveService;
use App\Services\Atak\MarkerCycle\PdoMarkerCycleStore;
use App\Services\Cron\CronJobInterface;

/**
 * Cycle de 24 h des marqueurs des postes web : archive en « mission » les marqueurs et tracés du cycle écoulé (bascule à
 * 12:00 par défaut, réglable par communauté), puis les retire de la carte. L'instantané est toujours écrit avant tout
 * retrait ; un passage est idempotent, verrouillé contre l'exécution simultanée et borné (AtakMarkerArchiveService::RUN_BUDGET
 * éléments par passage), si bien que le rattrapage de l'existant se fait en plusieurs passages sans bloquer le site.
 * Retire aussi les missions archivées plus vieilles que la rétention de la communauté et vide la corbeille du poste TOC.
 */
final class AtakMarkerCycleCronJob implements CronJobInterface
{
    public function __construct(private ?AtakMarkerArchiveService $service = null)
    {
    }

    public function key(): string
    {
        return 'atak_marker_cycle';
    }

    public function label(): string
    {
        return 'Marqueurs des postes web : archivage de la mission à la bascule du cycle';
    }

    public function description(): string
    {
        return 'À la bascule du cycle de 24 h (12:00 par défaut), archive les marqueurs et tracés du cycle écoulé dans une mission consultable en lecture seule, puis repart d’une carte propre ; les éléments épinglés restent. Par lots, rejouable sans doublon.';
    }

    public function run(): array
    {
        $r = ($this->service ??= new AtakMarkerArchiveService(new PdoMarkerCycleStore()))->runScheduled();
        // Corbeille du poste TOC : ce qui a dépassé 15 minutes est supprimé pour de bon (marqueurs du jeu : après 48 h).
        $r['trash_expired'] = 0;
        try {
            $r['trash_expired'] = (new \App\Services\AtakToc\Purge\TocElementStore(\App\Core\Database::getPdo()))->expire(null, time());
        } catch (\Throwable) {
            // Base indisponible ou migration absente : rien à nettoyer.
        }

        return [
            'ok' => $r['ok'],
            'summary' => $r['skipped']
                ? 'Cycle des marqueurs : passage ignoré (tables absentes ou autre passage en cours).'
                : sprintf(
                    'Cycle des marqueurs : %d élément(s) archivé(s) dans %d mission(s), %d retiré(s) de la carte, %d mission(s) purgée(s), %d carte(s) examinée(s), %d erreur(s).',
                    $r['archived'],
                    $r['missions'],
                    $r['removed'],
                    $r['purged'],
                    $r['scopes'],
                    $r['errors']
                ),
            'details' => $r,
        ];
    }
}
