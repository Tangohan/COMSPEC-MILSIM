<?php

declare(strict_types=1);

namespace App\Services\DangerZone;

use App\Repositories\DangerZoneRepository;

class DangerZoneService
{
    public function __construct(
        private DangerZoneRepository $repository,
        private GeometryService $geometry
    ) {
    }

    public function listForMission(string $missionId, bool $activeOnly = true): array
    {
        return $this->repository->listByMission($missionId, $activeOnly);
    }

    public function create(string $missionId, array $data): array
    {
        return $this->repository->create($missionId, $data);
    }

    public function update(int $id, string $missionId, array $data): bool
    {
        return $this->repository->update($id, $missionId, $data);
    }

    public function delete(int $id, string $missionId): bool
    {
        return $this->repository->delete($id, $missionId);
    }

    public function get(int $id, string $missionId): ?array
    {
        return $this->repository->getByIdAndMission($id, $missionId);
    }

    public function isPointInZone(float $x, float $y, array $zone): bool
    {
        $geom = is_array($zone['geometry_json'] ?? null) ? $zone['geometry_json'] : json_decode($zone['geometry_json'] ?? '{}', true);
        $type = $zone['geometry_type'] ?? 'CIRCLE';
        return $this->geometry->pointInGeometry($x, $y, $type, $geom ?: []);
    }
}
