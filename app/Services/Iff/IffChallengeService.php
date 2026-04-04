<?php

declare(strict_types=1);

namespace App\Services\Iff;

use App\Repositories\IffChallengeRepository;

class IffChallengeService
{
    public function __construct(
        private IffChallengeRepository $repository
    ) {
    }

    public function getCurrent(string $missionId): ?array
    {
        return $this->repository->getCurrentForMission($missionId);
    }

    public function create(string $missionId, string $code, int $validMinutes = 30): int
    {
        $from = date('Y-m-d H:i:s');
        $until = date('Y-m-d H:i:s', time() + $validMinutes * 60);
        return $this->repository->create($missionId, $code, $from, $until);
    }
}
