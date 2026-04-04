<?php

declare(strict_types=1);

namespace App\Services\Courrier;

use App\Repositories\Courrier\DocumentPresetRepository;

/**
 * Logique métier des presets (CRUD, défaut).
 */
class DocumentPresetService
{
    public function __construct(
        private DocumentPresetRepository $presetRepository
    ) {
    }

    public function listForTenant(?int $tenantId): array
    {
        return $this->presetRepository->listForTenant($tenantId);
    }

    public function getDefault(?int $tenantId): ?array
    {
        return $this->presetRepository->getDefault($tenantId);
    }

    public function findById(int $id, ?int $tenantId = null): ?array
    {
        return $this->presetRepository->findById($id, $tenantId);
    }

    public function create(array $data): int
    {
        return $this->presetRepository->create($data);
    }

    public function update(int $id, array $data): bool
    {
        return $this->presetRepository->update($id, $data);
    }

    public function setAsDefault(int $id, ?int $tenantId): void
    {
        $this->presetRepository->setDefault($id, $tenantId);
    }
}
