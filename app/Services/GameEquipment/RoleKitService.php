<?php

declare(strict_types=1);

namespace App\Services\GameEquipment;

use App\Repositories\ArsenalWardrobeRepository;
use App\Repositories\GameEquipmentRepository;
use App\Repositories\GameRoleKitRepository;
use App\Repositories\PersonnelJobRoleRepository;
use App\Repositories\UnitRepository;
use App\Support\ArsenalLoadoutItems;

/**
 * Kits de départ de la communauté (anciennement « kits par rôle ») : enregistrement, génération du loadout, proposition
 * à une unité, à un rôle ou à tous, copie dans les tenues d’un membre et publication comme tenue d’arsenal
 * (collection « Kits par rôle », visible de toute la communauté et proposée dans l’arsenal en jeu) pour les kits qui
 * ne sont pas réservés à une unité.
 *
 * Tout est filtré par communauté. L’accès aux kits suit la clé de formule `equipment_kits` (KitsAccess), vérifiée par
 * les contrôleurs.
 */
final class RoleKitService
{
    public const COLLECTION_SLUG = 'kits-par-role';

    public const COLLECTION_NAME = 'Kits par rôle';

    /** Tenue copiée par un membre : une seule par kit et par membre (la copie suivante la met à jour). */
    public const COPY_SLUG_PREFIX = 'kit-de-depart-';

    public function __construct(
        private GameRoleKitRepository $kits,
        private GameEquipmentRepository $equipment,
        private ArsenalWardrobeRepository $wardrobes,
        private PersonnelJobRoleRepository $roles,
        private ?UnitRepository $units = null,
    ) {
        $this->units ??= new UnitRepository();
    }

    public function ready(): bool
    {
        return $this->kits->tableReady();
    }

    /** Les kits peuvent être proposés à une unité (colonnes de l'étape equipment_starter_kits présentes). */
    public function unitsReady(): bool
    {
        return $this->kits->audienceReady();
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
     * Unités de la communauté proposées comme destinataire d’un kit : [id => nom].
     *
     * @return array<int, string>
     */
    public function unitOptions(int $tenantId): array
    {
        try {
            $names = $this->units->namesByIdForTenant($tenantId);
        } catch (\Throwable) {
            return [];
        }
        $out = [];
        foreach ($names as $id => $name) {
            if ($id > 0 && trim($name) !== '') {
                $out[$id] = trim($name);
            }
        }
        asort($out, SORT_NATURAL | SORT_FLAG_CASE);

        return $out;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listForTenant(int $tenantId): array
    {
        $roles = $this->roleOptions($tenantId);
        $units = $this->unitOptions($tenantId);
        $rows = $this->kits->listForTenant($tenantId);
        foreach ($rows as &$row) {
            $row['role_name'] = $row['job_role_id'] !== null ? ($roles[$row['job_role_id']] ?? 'Rôle supprimé') : null;
            $row['unit_name'] = $row['unit_id'] !== null ? ($units[$row['unit_id']] ?? 'Unité supprimée') : null;
            $row['item_count'] = count(RoleKitLoadout::classes(RoleKitLoadout::normalize($row['slots'])));
        }
        unset($row);

        return $rows;
    }

    /**
     * Kits proposés à un membre : ceux de ses fonctions, ceux de ses unités (une unité voit aussi les kits de ses
     * unités mères), plus les kits « tous les rôles ».
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
        $units = $this->unitOptions($tenantId);
        $rows = $this->kits->listForRoles($tenantId, $roleIds, $this->memberUnitIds($tenantId, $userId));
        foreach ($rows as &$row) {
            $row['role_name'] = $row['job_role_id'] !== null ? ($roles[$row['job_role_id']] ?? null) : null;
            $row['unit_name'] = $row['unit_id'] !== null ? ($units[$row['unit_id']] ?? null) : null;
        }
        unset($row);

        return $rows;
    }

    /**
     * Unités d'un membre et leurs unités mères : un kit proposé à la section est vu par les membres de ses équipes.
     *
     * @return list<int>
     */
    public function memberUnitIds(int $tenantId, int $userId): array
    {
        if (!$this->kits->audienceReady()) {
            return [];
        }
        try {
            $own = $this->units->unitIdsForUser($tenantId, $userId);
            if ($own === []) {
                return [];
            }
            $parent = [];
            foreach ($this->units->allForTenant($tenantId) as $u) {
                $parent[(int) ($u['id'] ?? 0)] = (int) ($u['parent_id'] ?? 0);
            }
        } catch (\Throwable) {
            return [];
        }
        $out = [];
        foreach ($own as $id) {
            for ($guard = 0; $id > 0 && !isset($out[$id]) && $guard < 30; $guard++) {
                $out[$id] = true;
                $id = $parent[$id] ?? 0;
            }
        }

        return array_map('intval', array_keys($out));
    }

    /**
     * Destinataire d'un kit saisi comme « all », « unit:12 » ou « role:7 » (liste unique du formulaire).
     *
     * @return array{ok: bool, error?: string, unit_id: int|null, job_role_id: int|null}
     */
    public function parseAudience(int $tenantId, mixed $value): array
    {
        $value = is_string($value) ? trim($value) : '';
        $unitId = null;
        $roleId = null;
        if (preg_match('/^unit:(\d{1,10})$/', $value, $m) === 1) {
            $unitId = (int) $m[1];
        } elseif (preg_match('/^role:(\d{1,10})$/', $value, $m) === 1) {
            $roleId = (int) $m[1];
        } elseif ($value !== '' && $value !== 'all') {
            return ['ok' => false, 'error' => 'Choisissez à qui proposer ce kit.', 'unit_id' => null, 'job_role_id' => null];
        }

        return $this->checkAudience($tenantId, $unitId, $roleId);
    }

    /**
     * @return array{ok: bool, error?: string, unit_id: int|null, job_role_id: int|null}
     */
    private function checkAudience(int $tenantId, ?int $unitId, ?int $roleId): array
    {
        if ($unitId !== null && $unitId > 0) {
            if (!$this->kits->audienceReady()) {
                return ['ok' => false, 'error' => 'Proposer un kit à une unité arrive avec la prochaine mise à jour de la base : appliquez les migrations.', 'unit_id' => null, 'job_role_id' => null];
            }
            if (!isset($this->unitOptions($tenantId)[$unitId])) {
                return ['ok' => false, 'error' => 'Cette unité n’existe pas dans la communauté.', 'unit_id' => null, 'job_role_id' => null];
            }

            return ['ok' => true, 'unit_id' => $unitId, 'job_role_id' => null];
        }
        if ($roleId !== null && $roleId > 0) {
            if (!isset($this->roleOptions($tenantId)[$roleId])) {
                return ['ok' => false, 'error' => 'Ce rôle n’existe pas dans la communauté.', 'unit_id' => null, 'job_role_id' => null];
            }

            return ['ok' => true, 'unit_id' => null, 'job_role_id' => $roleId];
        }

        return ['ok' => true, 'unit_id' => null, 'job_role_id' => null];
    }

    /**
     * @param array<string, mixed> $input name, description, audience ("all", "unit:ID", "role:ID") ou job_role_id /
     *        unit_id, slot, cargo ; ou kit (emplacements déjà prêts), payload (contenu à garder tel quel), source
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
        $existing = null;
        if ($kitId !== null && $kitId > 0) {
            $existing = $this->kits->find($tenantId, $kitId);
            if ($existing === null) {
                return ['ok' => false, 'error' => 'Ce kit n’existe plus.'];
            }
        }
        if (array_key_exists('audience', $input)) {
            $audience = $this->parseAudience($tenantId, $input['audience']);
        } else {
            $roleId = (int) ($input['job_role_id'] ?? 0);
            // Ancien formulaire (Kits par rôle) : sans unité envoyée, le kit garde la sienne tant qu'aucun rôle n'est choisi.
            $unitId = array_key_exists('unit_id', $input) ? (int) $input['unit_id'] : (int) ($existing['unit_id'] ?? 0);
            $audience = $this->checkAudience($tenantId, $roleId > 0 ? null : ($unitId > 0 ? $unitId : null), $roleId > 0 ? $roleId : null);
        }
        if (!$audience['ok']) {
            return ['ok' => false, 'error' => (string) ($audience['error'] ?? 'Destinataire invalide.')];
        }

        $slotIn = is_array($input['slot'] ?? null) ? $input['slot'] : [];
        $cargoIn = is_array($input['cargo'] ?? null) ? $input['cargo'] : [];
        $kit = is_array($input['kit'] ?? null) ? RoleKitLoadout::normalize($input['kit']) : RoleKitLoadout::normalize($slotIn, $cargoIn);
        if (RoleKitLoadout::isEmpty($kit)) {
            return ['ok' => false, 'error' => 'Choisissez au moins un objet pour le kit.'];
        }
        $classes = RoleKitLoadout::classes($kit);
        $keptPayload = trim((string) ($input['payload'] ?? ''));
        if ($keptPayload !== '') {
            $classes = array_values(array_unique(array_merge($classes, ArsenalLoadoutItems::classes($keptPayload))));
        }
        $catalog = $this->equipment->catalogueFor($tenantId, $classes);
        $payload = $keptPayload !== '' ? $keptPayload : RoleKitLoadout::build($kit, $catalog);
        if (ArsenalLoadoutItems::aceExport($payload) === null) {
            return ['ok' => false, 'error' => 'Ce contenu n’est pas une tenue lisible par l’arsenal ACE : le kit n’a pas été enregistré.'];
        }
        $mods = RoleKitLoadout::requiredMods($classes, $catalog);
        $data = [
            'job_role_id' => $audience['job_role_id'],
            'unit_id' => $audience['unit_id'],
            'source' => (string) ($input['source'] ?? ($existing['source'] ?? 'scratch')),
            'name' => $name,
            'description' => $description !== '' ? $description : null,
            'slots' => $kit,
            'payload_text' => $payload,
            'required_mods' => $mods,
        ];
        if ($existing !== null) {
            $this->kits->update($tenantId, (int) $kitId, $userId, $data);
            $id = (int) $kitId;
        } else {
            $id = $this->kits->create($tenantId, $userId, $data);
        }
        $warnings = [];
        $unknown = RoleKitLoadout::unknownClasses($classes, $catalog);
        if ($unknown !== []) {
            $warnings[] = 'Objets jamais vus en jeu : ' . implode(', ', array_slice($unknown, 0, 6)) . (count($unknown) > 6 ? '…' : '');
        }
        if (!$this->syncPublication($tenantId, $userId, $id)) {
            $warnings[] = 'Kit enregistré, mais la tenue d’arsenal n’a pas pu être publiée.';
        }

        return ['ok' => true, 'id' => $id, 'warnings' => $warnings];
    }

    /**
     * Transforme une tenue remontée du jeu (la sienne ou celle d'un autre membre, si elle est visible) en kit.
     * Le contenu de la tenue est gardé tel quel ; les emplacements servent à l'afficher et à le modifier.
     *
     * @return array{ok: bool, error?: string, id?: int, warnings?: list<string>}
     */
    public function saveFromWardrobe(int $tenantId, int $userId, int $wardrobeId, string $name, string $description, mixed $audience): array
    {
        $row = $wardrobeId > 0 ? $this->wardrobes->findWardrobe($tenantId, $wardrobeId) : null;
        if ($row === null || !ArsenalWardrobeRepository::canView($row, $userId)) {
            return ['ok' => false, 'error' => 'Cette tenue n’existe pas ou n’est pas visible.'];
        }
        $payload = (string) ($row['payload_text'] ?? '');
        $loadout = ArsenalLoadoutItems::aceLoadout($payload);
        $kit = RoleKitLoadout::fromPayload($payload);
        if ($loadout === null || $kit === null || RoleKitLoadout::isEmpty($kit)) {
            return ['ok' => false, 'error' => 'Cette tenue ne peut pas être lue : elle ne devient pas un kit.'];
        }
        $name = trim($name) !== '' ? trim($name) : ArsenalLoadoutItems::formatWardrobeTitle((string) ($row['name'] ?? ''));

        return $this->save($tenantId, $userId, null, [
            'name' => mb_substr($name, 0, 90),
            'description' => $description,
            'audience' => $audience,
            'kit' => $kit,
            'payload' => (string) json_encode($loadout[0], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'source' => 'game_loadout',
        ]);
    }

    /**
     * Change le destinataire d'un kit (une unité, un rôle ou tous) et ajuste sa publication en arsenal.
     *
     * @return array{ok: bool, error?: string}
     */
    public function updateAudience(int $tenantId, int $userId, int $kitId, mixed $audience): array
    {
        $kit = $this->kits->find($tenantId, $kitId);
        if ($kit === null) {
            return ['ok' => false, 'error' => 'Ce kit n’existe plus.'];
        }
        $parsed = $this->parseAudience($tenantId, $audience);
        if (!$parsed['ok']) {
            return ['ok' => false, 'error' => (string) ($parsed['error'] ?? 'Destinataire invalide.')];
        }
        $this->kits->setAudience($tenantId, $kitId, $parsed['unit_id'], $parsed['job_role_id'], $userId);
        $this->syncPublication($tenantId, $userId, $kitId);

        return ['ok' => true];
    }

    /**
     * Copie un kit dans les tenues du membre (une copie par kit : la suivante la met à jour). Le kit doit lui être
     * proposé, sauf pour un responsable des kits.
     *
     * @return array{ok: bool, error?: string, id?: int, created?: bool}
     */
    public function copyToMember(int $tenantId, int $userId, int $kitId, bool $manager = false): array
    {
        $kit = $this->kits->find($tenantId, $kitId);
        if ($kit === null) {
            return ['ok' => false, 'error' => 'Ce kit n’existe plus.'];
        }
        if (!$manager) {
            $offered = array_column($this->listForMember($tenantId, $userId), 'id');
            if (!in_array($kitId, array_map('intval', $offered), true)) {
                return ['ok' => false, 'error' => 'Ce kit ne vous est pas proposé.'];
            }
        }
        if (!$this->wardrobes->tablesReady()) {
            return ['ok' => false, 'error' => 'Les tenues ne sont pas encore disponibles sur cette instance.'];
        }
        $slug = self::COPY_SLUG_PREFIX . $kitId;
        $created = true;
        try {
            foreach ($this->wardrobes->listWardrobesForUser($tenantId, $userId) as $w) {
                if ((string) ($w['slug'] ?? '') === $slug) {
                    $created = false;
                    break;
                }
            }
            $wardrobe = $this->wardrobes->upsertWardrobe($tenantId, $userId, [
                'name' => mb_substr((string) $kit['name'], 0, 120),
                'slug' => $slug,
                'payload_text' => (string) $kit['payload_text'],
                'payload_format' => 'arma_loadout_str',
                'source' => 'starter_kit',
                'notes' => mb_substr('Copié du kit de départ « ' . (string) $kit['name'] . ' »', 0, 255),
            ]);
            $id = (int) ($wardrobe['id'] ?? 0);
            if ($id < 1) {
                return ['ok' => false, 'error' => 'Copie impossible.'];
            }
            if ($created) {
                // Une copie est d'abord personnelle : le membre choisit ensuite de la partager.
                $this->wardrobes->updateWardrobeVisibility($tenantId, $userId, $id, 'personal');
            }

            return ['ok' => true, 'id' => $id, 'created' => $created];
        } catch (\Throwable $e) {
            error_log('[role-kits] copy failed: ' . $e->getMessage());

            return ['ok' => false, 'error' => 'Copie impossible.'];
        }
    }

    /** Publie la tenue d’arsenal du kit, sauf pour un kit réservé à une unité (alors la tenue publiée est retirée). */
    public function syncPublication(int $tenantId, int $userId, int $kitId): bool
    {
        $kit = $this->kits->find($tenantId, $kitId);
        if ($kit === null) {
            return false;
        }
        if ($kit['unit_id'] !== null) {
            $this->unpublish($tenantId, $kit);

            return true;
        }

        return $this->publish($tenantId, $userId, $kitId);
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

    /**
     * Retire la tenue d'arsenal publiée d'un kit devenu réservé à une unité (le kit et ses copies restent).
     *
     * @param array<string, mixed> $kit
     */
    private function unpublish(int $tenantId, array $kit): void
    {
        if ($kit['wardrobe_id'] === null) {
            return;
        }
        try {
            $w = $this->wardrobes->findWardrobe($tenantId, (int) $kit['wardrobe_id']);
            if ($w !== null) {
                $this->wardrobes->deleteWardrobe($tenantId, (int) ($w['user_id'] ?? 0), (int) $kit['wardrobe_id']);
            }
            $this->kits->setWardrobe($tenantId, (int) $kit['id'], null);
        } catch (\Throwable $e) {
            error_log('[role-kits] unpublish failed: ' . $e->getMessage());
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
            if ($k['unit_id'] !== null) {
                continue;
            }
            $tags[] = $k['job_role_id'] !== null ? 'role:' . ($roles[$k['job_role_id']] ?? (string) $k['job_role_id']) : 'role:tous';
        }

        return array_values(array_unique($tags));
    }
}
