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
        if (strtotime((string) $validUntil) < time()) {
            $this->assetRepository->setResponse($missionId, $assetId, $responseCode, 'EXPIRED');
            return ['status' => 'EXPIRED', 'message' => 'Challenge expired'];
        }
        $status = strtoupper(trim($responseCode)) === strtoupper(trim((string) $validCode)) ? 'FRIENDLY' : 'SUSPECT';
        $this->assetRepository->setResponse($missionId, $assetId, $responseCode, $status);
        return ['status' => $status];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listAssets(string $missionId): array
    {
        $this->assetRepository->promoteUnknownAfterGrace($missionId);
        $challenge = $this->challengeRepository->getCurrentForMission($missionId);
        $challengeExpired = false;
        $validUntilTs = null;
        if ($challenge) {
            $validUntilTs = strtotime((string) ($challenge['valid_until'] ?? ''));
            if ($validUntilTs !== false && $validUntilTs < time()) {
                $challengeExpired = true;
            }
        } else {
            $challengeExpired = true;
        }

        $rows = $this->assetRepository->listByMission($missionId);
        $out = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $status = strtoupper(trim((string) ($row['response_status'] ?? 'PENDING')));
            $graceUntil = $row['grace_until'] ?? null;
            $graceTs = $graceUntil ? strtotime((string) $graceUntil) : false;
            $now = time();

            if ($status === 'PENDING' && $challengeExpired) {
                $status = 'EXPIRED';
            } elseif ($status === 'PENDING' && $graceTs !== false && $graceTs < $now) {
                $status = 'UNKNOWN';
            }

            $graceRemainingSec = null;
            if ($status === 'PENDING' && $graceTs !== false && $graceTs >= $now) {
                $graceRemainingSec = $graceTs - $now;
            }
            $challengeRemainingSec = null;
            if ($validUntilTs !== false && $validUntilTs !== null && $validUntilTs >= $now) {
                $challengeRemainingSec = $validUntilTs - $now;
            }

            $platform = strtolower(trim((string) ($row['platform_type'] ?? '')));
            $isVehicle = $platform !== '' && !in_array($platform, ['infantry', 'man', 'soldier', 'foot'], true);

            $row['response_status'] = $status;
            $row['display_status'] = $status;
            $row['is_alert'] = in_array($status, ['UNKNOWN', 'SUSPECT', 'EXPIRED'], true);
            $row['is_vehicle'] = $isVehicle;
            $row['grace_until'] = $graceUntil;
            $row['grace_remaining_sec'] = $graceRemainingSec;
            $row['challenge_remaining_sec'] = $challengeRemainingSec;
            $row['challenge_valid_until'] = $challenge['valid_until'] ?? null;
            $out[] = $row;
        }

        return $out;
    }
}
