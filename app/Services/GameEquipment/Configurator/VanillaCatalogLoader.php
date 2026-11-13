<?php

declare(strict_types=1);

namespace App\Services\GameEquipment\Configurator;

use App\Repositories\GameItemSpecRepository;

/**
 * Chargement du catalogue de base d'Arma 3 (VanillaCatalog) dans game_item_specs d'une communauté, par lots, au premier
 * accès à la page Équipement : le navigateur appelle step() jusqu'à « finished » et montre l'avancement. Rejoué quand le
 * fichier livré change de version, ou à la demande (« Recharger le catalogue de base », restart).
 *
 * Idempotent : un lot rejoué réécrit les mêmes fiches de base ; une fiche importée par un responsable n'est jamais
 * touchée (GameItemSpecRepository::importVanilla). Aucune donnée vue en jeu (game_item_catalog) n'est modifiée.
 */
final class VanillaCatalogLoader
{
    public const BATCH = 300;

    public function __construct(
        private ?GameItemSpecRepository $specs = null,
        private ?string $path = null,
    ) {
        $this->specs ??= new GameItemSpecRepository();
    }

    /**
     * available : fichier lisible et base à jour ; needed : chargement à faire (jamais fait, interrompu, nouvelle
     * version du fichier).
     *
     * @return array{available: bool, needed: bool, version: string, total: int, done: int, finished: bool, families: array<string, int>}
     */
    public function status(int $tenantId): array
    {
        $file = VanillaCatalog::load($this->path);
        $total = count($file['rows']);
        $out = ['available' => false, 'needed' => false, 'version' => $file['version'], 'total' => $total, 'done' => 0, 'finished' => false, 'families' => []];
        if (!$file['ok'] || $total === 0 || $tenantId < 1) {
            return $out;
        }
        try {
            if (!$this->specs->vanillaReady()) {
                return $out;
            }
            $state = $this->specs->vanillaState($tenantId);
        } catch (\Throwable) {
            return $out;
        }
        $out['available'] = true;
        $current = $state !== null && $state['version'] === $file['version'];
        $out['done'] = $current ? min($total, $state['done']) : 0;
        $out['finished'] = $current && $state['finished'];
        $out['needed'] = !$out['finished'];
        $out['families'] = VanillaCatalog::familyCounts($file['rows']);

        return $out;
    }

    /**
     * Un lot. restart = tout recharger depuis le début (bouton « Recharger le catalogue de base »).
     *
     * @return array{ok: bool, error?: string, done: int, total: int, finished: bool, written: int, version: string}
     */
    public function step(int $tenantId, bool $restart = false): array
    {
        $status = $this->status($tenantId);
        $base = ['done' => $status['done'], 'total' => $status['total'], 'finished' => $status['finished'], 'written' => 0, 'version' => $status['version']];
        if (!$status['available']) {
            return ['ok' => false, 'error' => 'Catalogue de base indisponible : appliquez la mise à jour de la base (étape « Catalogue de base d’Arma 3 »).'] + $base;
        }
        if ($status['finished'] && !$restart) {
            return ['ok' => true] + $base;
        }
        $offset = $restart ? 0 : $status['done'];
        $rows = VanillaCatalog::load($this->path)['rows'];
        $batch = array_slice($rows, $offset, self::BATCH);
        $written = $this->specs->importVanilla($tenantId, $batch);
        $done = min($status['total'], $offset + count($batch));
        $finished = $done >= $status['total'];
        if ($finished) {
            // Fiches de base d'une ancienne version du fichier, absentes de la nouvelle : retirées.
            $this->specs->pruneVanilla($tenantId, array_map(static fn (array $r): string => (string) $r['class_name'], $rows));
        }
        $this->specs->saveVanillaState($tenantId, $status['version'], $status['total'], $done, $finished);

        return ['ok' => true, 'done' => $done, 'total' => $status['total'], 'finished' => $finished, 'written' => $written, 'version' => $status['version']];
    }

    /**
     * Tout le catalogue d'un coup (tests, ligne de commande).
     *
     * @return array{ok: bool, done: int, total: int, finished: bool, written: int, version: string}
     */
    public function loadAll(int $tenantId, bool $restart = false): array
    {
        $written = 0;
        $r = $this->step($tenantId, $restart);
        $written += $r['written'];
        for ($i = 0; $r['ok'] && !$r['finished'] && $i < 200; $i++) {
            $r = $this->step($tenantId);
            $written += $r['written'];
        }
        $r['written'] = $written;

        return $r;
    }
}
