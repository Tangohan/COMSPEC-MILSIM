<?php

declare(strict_types=1);

namespace App\Services\Iff;

use App\Repositories\IffChallengeRepository;
use App\Support\UtcDateTime;

class IffChallengeService
{
    public function __construct(
        private IffChallengeRepository $repository
    ) {
    }

    /**
     * Défi courant pour l'API. valid_from / valid_until sont stockés en UTC (comparés à NOW() en
     * session UTC) ; ils sont renvoyés en heure locale du serveur, format attendu par les écrans
     * ATAK / Overwatch qui les affichent tels quels et les lisent avec Date.parse() local.
     */
    public function getCurrent(string $missionId): ?array
    {
        $row = $this->repository->getCurrentForMission($missionId);
        if ($row === null) {
            return null;
        }
        foreach (['valid_from', 'valid_until'] as $key) {
            $ts = UtcDateTime::timestamp($row[$key] ?? null);
            if ($ts !== null) {
                $row[$key] = date('Y-m-d H:i:s', $ts);
            }
        }
        return $row;
    }

    public function create(string $missionId, string $code, int $validMinutes = 30): int
    {
        // En UTC : getCurrentForMission filtre sur valid_until > NOW() (session UTC).
        $from = gmdate('Y-m-d H:i:s');
        $until = gmdate('Y-m-d H:i:s', time() + $validMinutes * 60);
        return $this->repository->create($missionId, $code, $from, $until);
    }
}
