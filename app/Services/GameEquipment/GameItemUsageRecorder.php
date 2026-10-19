<?php

declare(strict_types=1);

namespace App\Services\GameEquipment;

use App\Repositories\GameEquipmentRepository;

/**
 * Enregistre l’équipement porté et les mods chargés à chaque register/sync du profil opérateur.
 * Ne lève jamais : une panne ici ne doit pas faire échouer la synchronisation du joueur.
 */
final class GameItemUsageRecorder
{
    public function __construct(
        private ?GameEquipmentRepository $repo = null,
        private ?GameItemUsageExtractor $extractor = null,
    ) {
        $this->repo ??= new GameEquipmentRepository();
        $this->extractor ??= new GameItemUsageExtractor();
    }

    /**
     * @param array<string, mixed> $payload profil normalisé
     */
    public function record(int $tenantId, string $steamId, array $payload): void
    {
        try {
            $data = $this->extractor->extract($payload);
            $now = date('Y-m-d H:i:s');
            $this->repo->recordItems($tenantId, $steamId, $data['items'], $now);
            if ($data['mods'] !== null) {
                $this->repo->recordMods($tenantId, $steamId, $data['mods'], $now);
            }
        } catch (\Throwable $e) {
            error_log('[game-equipment] usage not recorded: ' . $e->getMessage());
        }
    }
}
