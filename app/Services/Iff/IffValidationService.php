<?php

declare(strict_types=1);

namespace App\Services\Iff;

use App\Repositories\IffAssetStatusRepository;
use App\Repositories\IffChallengeRepository;

class IffValidationService
{
    public function __construct(
        private IffAssetStatusRepository $assetRepository,
        private IffChallengeRepository $challengeRepository
    ) {
    }

    public function respond(string $missionId, string $assetId, string $responseCode): array
    {
        $challenge = $this->challengeRepository->getCurrentForMission($missionId);
        if (!$challenge) {
            return ['status' => 'EXPIRED', 'message' => 'No active challenge'];
        }
        $validCode = $challenge['code'] ?? '';
        $validUntil = $challenge['valid_until'] ?? '';
        if (strtotime($validUntil) < time()) {
            $this->assetRepository->setResponse($missionId, $assetId, $responseCode, 'EXPIRED');
            return ['status' => 'EXPIRED', 'message' => 'Challenge expired'];
        }
        $status = strtoupper(trim($responseCode)) === strtoupper(trim($validCode)) ? 'FRIENDLY' : 'SUSPECT';
        $this->assetRepository->setResponse($missionId, $assetId, $responseCode, $status);
        return ['status' => $status];
    }

    public function listAssets(string $missionId): array
    {
        return $this->assetRepository->listByMission($missionId);
    }
}
