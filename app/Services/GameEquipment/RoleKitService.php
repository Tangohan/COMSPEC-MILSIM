<?php

declare(strict_types=1);

namespace App\Services\GameEquipment;

use App\Repositories\ArsenalWardrobeRepository;
use App\Repositories\GameEquipmentRepository;
use App\Repositories\GameRoleKitRepository;
use App\Repositories\PersonnelJobRoleRepository;

/**
 * Kits par rôle : enregistrement, génération du loadout, publication comme tenue d’arsenal
 * (collection « Kits par rôle », visible de toute la communauté et proposée dans l’arsenal en jeu).
 */
final class RoleKitService
{
    public const COLLECTION_SLUG = 'kits-par-role';

    public const COLLECTION_NAME = 'Kits par rôle';

    public function __construct(
        private GameRoleKitRepository $kits,
        private GameEquipmentRepository $equipment,
        private ArsenalWardrobeRepository $wardrobes,
        private PersonnelJobRoleRepository $roles,
    ) {}

    public function ready(): bool
    {
        return $this->kits->tableReady();
    }

    /**
     * Fonctions de la communauté proposées comme rôle d’un kit : [id => nom].
     *
     * @return array<int, string>
     */
    public function roleOptions(int $tenantId): array
    {
        $out = [];
        try {
            foreach ($this->roles->listRolesWithCategory($tenantId) as $r) {
                $id = (int) ($r['id'] ?? 0);
                $name = trim((string) ($r['name'] ?? ''));
                if ($id > 0 && $name !== '') {
                    $out[$id] = $name;
                }
            }
        } catch (\Throwable) {
            return [];
        }

        return $out;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listForTenant(int $tenantId): array
    {
        $roles = $this->roleOptions($tenantId);
        $rows = $this->kits->listForTenant($tenantId);
        foreach ($rows as &$row) {
            $row['role_name'] = $row['job_role_id'] !== null ? ($roles[$row['job_role_id']] ?? 'Rôle supprimé') : null;
            $row['item_count'] = count(RoleKitLoadout::classes(RoleKitLoadout::normalize($row['slots'])));
        }
        unset($row);

        return $rows;
    }

    /**
     * Kits proposés à un membre selon ses fonctions (plus les kits « tous les rôles »).
     *
     * @return list<array<string, mixed>>
     */
    public function listForMember(int $tenantId, int $userId): array
    {
        if (!$this->kits->tableReady()) {
            return [];
        }
        $roleIds = [];
        try {
            $assign = $this->roles->listPivotAssignmentsForUsers($tenantId, [$userId]);
            foreach ($assign[$userId] ?? [] as $a) {
                $roleIds[] = (int) ($a['personnel_job_role_id'] ?? 0);
            }
        } catch (\Throwable) {
            $roleIds = [];
        }
        $roles = $this->roleOptions($tenantId);
        $rows = $this->kits->listForRoles($tenantId, $roleIds);
        foreach ($rows as &$row) {
            $row['role_name'] = $row['job_role_id'] !== null ? ($roles[$row['job_role_id']] ?? null) : null;
        }
        unset($row);

        return $rows;
    }

    /**
     * @param array<string, mixed> $input name, job_role_id, description, slot, cargo
     * @return array{ok: bool, error?: string, id?: int, warnings?: list<string>}
     */
    public function save(int $tenantId, int $userId, ?int $kitId, array $input): array
    {
        $name = trim(preg_replace('/\s+/u', ' ', (string) ($input['name'] ?? '')) ?? '');
        if ($name === '') {
            return ['ok' => false, 'error' => 'Donnez un nom au kit.'];
        }
        if (mb_strlen($name) > 90) {
            return ['ok' => false, 'error' => 'Nom du kit trop long (90 caractères au plus).'];
        }
        $description = trim((string) ($input['description'] ?? ''));
        if (mb_strlen($description) > 1000) {
            return ['ok' => false, 'error' => 'Description trop longue (1 000 caractères au plus).'];
        }
        $roles = $this->roleOptions($tenantId);
        $roleId = (int) ($input['job_role_id'] ?? 0);
        if ($roleId > 0 && !isset($roles[$roleId])) {
            return ['ok' => false, 'error' => 'Ce rôle n’existe pas dans la communauté.'];
        }
        $slotIn = is_array($input['slot'] ?? null) ? $input['slot'] : [];
        $cargoIn = is_array($input['cargo'] ?? null) ? $input['cargo'] : [];
        $kit = RoleKitLoadout::normalize($slotIn, $cargoIn);
        if (RoleKitLoadout::isEmpty($kit)) {
            return ['ok' => false, 'error' => 'Choisissez au moins un objet pour le kit.'];
        }
        $classes = RoleKitLoadout::classes($kit);
        $catalog = $this->equipment->catalogueFor($tenantId, $classes);
        $payload = RoleKitLoadout::build($kit, $catalog);
        $mods = RoleKitLoadout::requiredMods($classes, $catalog);
        $data = [
            'job_role_id' => $roleId > 0 ? $roleId : null,
            'name' => $name,
            'description' => $description !== '' ? $description : null,
            'slots' => $kit,
            'payload_text' => $payload,
            'required_mods' => $mods,
        ];
        if ($kitId !== null && $kitId > 0) {
            if ($this->kits->find($tenantId, $kitId) === null) {
                return ['ok' => false, 'error' => 'Ce kit n’existe plus.'];
            }
            $this->kits->update($tenantId, $kitId, $userId, $data);
            $id = $kitId;
        } else {
            $id = $this->kits->create($tenantId, $userId, $data);
        }
        $warnings = [];
        $unknown = RoleKitLoadout::unknownClasses($classes, $catalog);
        if ($unknown !== []) {
            $warnings[] = 'Objets jamais vus en jeu : ' . implode(', ', array_slice($unknown, 0, 6)) . (count($unknown) > 6 ? '…' : '');
        }
        if (!$this->publish($tenantId, $userId, $id)) {
            $warnings[] = 'Kit enregistré, mais la tenue d’arsenal n’a pas pu être publiée.';
        }

        return ['ok' => true, 'id' => $id, 'warnings' => $warnings];
    }

    /** Publie (ou met à jour) la tenue d’arsenal du kit. */
    public function publish(int $tenantId, int $userId, int $kitId): bool
    {
        try {
            $kit = $this->kits->find($tenantId, $kitId);
            if ($kit === null || !$this->wardrobes->tablesReady()) {
                return false;
            }
            $roles = $this->roleOptions($tenantId);
            $roleName = $kit['job_role_id'] !== null ? ($roles[$kit['job_role_id']] ?? null) : null;
            $collectionId = $this->wardrobes->ensureTenantCollection(
                $tenantId,
                self::COLLECTION_SLUG,
                self::COLLECTION_NAME,
                'Kits proposés par rôle, composés au back-office à partir de l’équipement porté en jeu.',
                $this->collectionTags($tenantId, $roles),
            );
            $owner = $userId;
            if ($kit['wardrobe_id'] !== null) {
                $current = $this->wardrobes->findWardrobe($tenantId, (int) $kit['wardrobe_id']);
                if ($current !== null) {
                    $owner = (int) ($current['user_id'] ?? $userId);
                }
            }
            $wardrobe = $this->wardrobes->upsertWardrobe($tenantId, $owner, [
                'name' => RoleKitLoadout::wardrobeName((string) $kit['name'], $roleName),
                'slug' => 'kit-role-' . $kitId,
                'payload_text' => (string) $kit['payload_text'],
                'payload_format' => 'arma_loadout_str',
                'source' => 'role_kit',
                'collection_id' => $collectionId > 0 ? $collectionId : null,
                'notes' => 'Kit par rôle · ' . ($roleName ?? 'tous les rôles'),
            ]);
            $wardrobeId = (int) ($wardrobe['id'] ?? 0);
            if ($wardrobeId < 1) {
                return false;
            }
            if ($collectionId > 0) {
                $this->wardrobes->assignWardrobeCollection($tenantId, $owner, $wardrobeId, $collectionId);
            }
            try {
                $this->wardrobes->updateWardrobeDescription($tenantId, $owner, $wardrobeId, $this->wardrobeDescription($kit, $roleName));
            } catch (\Throwable) {
                // Colonne description absente sur une base ancienne : la tenue reste publiée.
            }
            if ($kit['wardrobe_id'] !== $wardrobeId) {
                $this->kits->setWardrobe($tenantId, $kitId, $wardrobeId);
            }

            return true;
        } catch (\Throwable $e) {
            error_log('[role-kits] publish failed: ' . $e->getMessage());

            return false;
        }
    }

    public function delete(int $tenantId, int $kitId): bool
    {
        $kit = $this->kits->find($tenantId, $kitId);
        if ($kit === null) {
            return false;
        }
        if ($kit['wardrobe_id'] !== null) {
            try {
                $w = $this->wardrobes->findWardrobe($tenantId, (int) $kit['wardrobe_id']);
                if ($w !== null) {
                    $this->wardrobes->deleteWardrobe($tenantId, (int) ($w['user_id'] ?? 0), (int) $kit['wardrobe_id']);
                }
            } catch (\Throwable $e) {
                error_log('[role-kits] wardrobe delete failed: ' . $e->getMessage());
            }
        }

        return $this->kits->delete($tenantId, $kitId);
    }

    /**
     * @param array<string, mixed> $kit
     */
    private function wardrobeDescription(array $kit, ?string $roleName): string
    {
        $parts = ['Kit proposé pour : ' . ($roleName ?? 'tous les rôles') . '.'];
        $desc = trim((string) ($kit['description'] ?? ''));
        if ($desc !== '') {
            $parts[] = $desc;
        }
        $mods = is_array($kit['required_mods'] ?? null) ? $kit['required_mods'] : [];
        $parts[] = $mods !== [] ? 'Mods requis : ' . implode(', ', $mods) . '.' : 'Aucun mod requis en dehors d’Arma 3.';

        return implode("\n\n", $parts);
    }

    /**
     * @param array<int, string> $roles
     * @return list<string>
     */
    private function collectionTags(int $tenantId, array $roles): array
    {
        $tags = ['kits-par-role'];
        foreach ($this->kits->listForTenant($tenantId) as $k) {
            $tags[] = $k['job_role_id'] !== null ? 'role:' . ($roles[$k['job_role_id']] ?? (string) $k['job_role_id']) : 'role:tous';
        }

        return array_values(array_unique($tags));
    }
}
