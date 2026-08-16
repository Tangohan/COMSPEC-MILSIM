<?php

declare(strict_types=1);

namespace App\Services\Jnet;

use App\Repositories\TenantEmailRecipientGroupRepository;
use App\Repositories\TenantMessageRepository;
use App\Repositories\UnitRepository;
use App\Repositories\UserRepository;

/**
 * Messagerie d’unité JNET : annuaire des destinataires, groupes de diffusion
 * (unités de l’ORBAT, encadrement, listes enregistrées) et résolution des envois.
 */
final class JnetMessagingService
{
    /** Au-delà, la diffusion est refusée : un message d’unité n’a pas vocation à partir en masse aveugle. */
    private const MAX_RECIPIENTS = 250;

    /** Au-delà, on ne double pas la diffusion par e-mail (le message reste dans la messagerie). */
    private const EMAIL_NOTIFICATION_LIMIT = 25;

    /** @var array<string, list<int>> */
    private array $groupMembersCache = [];

    /** @var array<int, list<array<string, mixed>>> */
    private array $directoryCache = [];

    public function __construct(
        private ?UserRepository $users = null,
        private ?UnitRepository $units = null,
        private ?TenantMessageRepository $messages = null,
        private ?TenantEmailRecipientGroupRepository $savedGroups = null,
        private ?JnetDashboardService $jnet = null,
    ) {
        $this->users ??= \App\Core\Container::get(UserRepository::class);
        $this->units ??= new UnitRepository();
        $this->messages ??= new TenantMessageRepository();
        $this->savedGroups ??= new TenantEmailRecipientGroupRepository();
        $this->jnet ??= new JnetDashboardService();
    }

    /**
     * Niveaux d’urgence proposés à la rédaction.
     *
     * @return array<string, array{label: string, hint: string}>
     */
    public function precedences(): array
    {
        return [
            'routine' => ['label' => 'Routine', 'hint' => 'À lire dans la journée'],
            'prioritaire' => ['label' => 'Prioritaire', 'hint' => 'À traiter dès la prise de service'],
            'immediat' => ['label' => 'Immédiat', 'hint' => 'Le destinataire doit être prévenu sans délai'],
            'flash' => ['label' => 'Flash', 'hint' => 'Urgence opérationnelle, interrompt le service courant'],
        ];
    }

    public function precedenceLabel(string $key): string
    {
        return $this->precedences()[$key]['label'] ?? 'Routine';
    }

    public function emailNotificationLimit(): int
    {
        return self::EMAIL_NOTIFICATION_LIMIT;
    }

    public function maxRecipients(): int
    {
        return self::MAX_RECIPIENTS;
    }

    /**
     * Annuaire des destinataires nominatifs : uniquement des comptes réels et actifs.
     *
     * @return list<array<string, mixed>>
     */
    public function directory(int $tenantId, int $excludeUserId = 0): array
    {
        if (!array_key_exists($tenantId, $this->directoryCache)) {
            $cards = [];
            foreach ($this->jnet->loadPersonnelCards($tenantId) as $card) {
                if (!empty($card['demo'])) {
                    continue;
                }
                $id = (int) ($card['id'] ?? 0);
                if ($id < 1) {
                    continue;
                }
                $cards[] = [
                    'id' => $id,
                    'name' => (string) ($card['name'] ?? 'Opérateur'),
                    'callsign' => (string) ($card['callsign'] ?? '—'),
                    'grade' => (string) ($card['grade'] ?? '—'),
                    'unit' => (string) ($card['unit'] ?? '—'),
                    'function' => (string) ($card['function'] ?? '—'),
                    'photo' => $card['photo'] ?? null,
                    'initials' => (string) ($card['initials'] ?? '??'),
                    'duty_label' => (string) ($card['duty_label'] ?? 'ACTIF'),
                ];
            }
            $this->directoryCache[$tenantId] = $cards;
        }

        $out = $this->directoryCache[$tenantId];
        if ($excludeUserId > 0) {
            $out = array_values(array_filter($out, static fn (array $m): bool => (int) $m['id'] !== $excludeUserId));
        }

        return $out;
    }

    /** @return list<int> */
    private function directoryIds(int $tenantId): array
    {
        return array_map(static fn (array $m): int => (int) $m['id'], $this->directory($tenantId));
    }

    /**
     * Groupes de diffusion disponibles : unité entière, encadrement, sections de l’ORBAT,
     * puis listes de diffusion enregistrées par la communauté.
     *
     * @return list<array{key: string, label: string, description: string, count: int, kind: string}>
     */
    public function groups(int $tenantId): array
    {
        $out = [];
        $directory = $this->directoryIds($tenantId);
        if ($directory === []) {
            return [];
        }

        $out[] = [
            'key' => 'all',
            'label' => 'Toute l’unité',
            'description' => 'Tous les membres actifs rattachés à l’unité',
            'count' => count($this->membersOfGroup($tenantId, 'all')),
            'kind' => 'unite',
        ];

        $command = $this->membersOfGroup($tenantId, 'command');
        if ($command !== []) {
            $out[] = [
                'key' => 'command',
                'label' => 'Chaîne de commandement',
                'description' => 'Encadrement et responsables désignés de la communauté',
                'count' => count($command),
                'kind' => 'unite',
            ];
        }

        try {
            $units = $this->units->allForTenant($tenantId);
        } catch (\Throwable) {
            $units = [];
        }
        foreach ($units as $unit) {
            $unitId = (int) ($unit['id'] ?? 0);
            if ($unitId < 1) {
                continue;
            }
            $members = $this->membersOfGroup($tenantId, 'unit:' . $unitId);
            if ($members === []) {
                continue;
            }
            $name = trim((string) ($unit['name'] ?? ''));
            if ($name === '') {
                continue;
            }
            $out[] = [
                'key' => 'unit:' . $unitId,
                'label' => $name,
                'description' => 'Personnel affecté à cette formation et à ses sous-ensembles',
                'count' => count($members),
                'kind' => 'orbat',
            ];
        }

        foreach ($this->savedGroups->listForTenant($tenantId) as $group) {
            $groupId = (int) ($group['id'] ?? 0);
            if ($groupId < 1) {
                continue;
            }
            $members = $this->membersOfGroup($tenantId, 'list:' . $groupId);
            if ($members === []) {
                continue;
            }
            $description = trim((string) ($group['description'] ?? ''));
            $out[] = [
                'key' => 'list:' . $groupId,
                'label' => trim((string) ($group['name'] ?? 'Liste de diffusion')),
                'description' => $description !== '' ? $description : 'Liste de diffusion enregistrée par la communauté',
                'count' => count($members),
                'kind' => 'liste',
            ];
        }

        return $out;
    }

    /**
     * @return array<string, string> clé de groupe => libellé lisible
     */
    public function groupLabels(int $tenantId): array
    {
        $labels = [];
        foreach ($this->groups($tenantId) as $group) {
            $labels[$group['key']] = $group['label'];
        }

        return $labels;
    }

    /** @return list<int> */
    private function membersOfGroup(int $tenantId, string $key): array
    {
        $cacheKey = $tenantId . '|' . $key;
        if (array_key_exists($cacheKey, $this->groupMembersCache)) {
            return $this->groupMembersCache[$cacheKey];
        }

        $ids = [];
        try {
            if ($key === 'all') {
                $ids = $this->directoryIds($tenantId);
            } elseif ($key === 'command') {
                $ids = $this->messages->findStaffUserIdsForTenant($tenantId);
            } elseif (str_starts_with($key, 'unit:')) {
                $unitId = (int) substr($key, 5);
                if ($unitId > 0) {
                    $scope = $this->units->expandUnitIdsWithDescendants($tenantId, [$unitId]);
                    $ids = $this->units->listActiveUserIdsForUnits($tenantId, $scope !== [] ? $scope : [$unitId]);
                }
            } elseif (str_starts_with($key, 'list:')) {
                $groupId = (int) substr($key, 5);
                $group = $groupId > 0 ? $this->savedGroups->findById($groupId, $tenantId) : null;
                if (is_array($group)) {
                    $definition = json_decode((string) ($group['definition_json'] ?? '{}'), true);
                    $ids = $this->resolveDefinition($tenantId, is_array($definition) ? $definition : []);
                }
            }
        } catch (\Throwable) {
            $ids = [];
        }

        $allowed = array_flip($this->directoryIds($tenantId));
        $ids = array_values(array_unique(array_filter(
            array_map('intval', $ids),
            static fn (int $id): bool => $id > 0 && isset($allowed[$id])
        )));

        return $this->groupMembersCache[$cacheKey] = $ids;
    }

    /**
     * Résolution d’une liste enregistrée (unités, rôles, membres explicites).
     *
     * @param array<string, mixed> $definition
     * @return list<int>
     */
    private function resolveDefinition(int $tenantId, array $definition): array
    {
        $ids = [];
        if (!empty($definition['all_members'])) {
            $ids = array_merge($ids, $this->directoryIds($tenantId));
        }
        $unitIds = array_values(array_filter(array_map('intval', (array) ($definition['unit_ids'] ?? []))));
        if ($unitIds !== []) {
            $scope = !empty($definition['include_descendants'])
                ? $this->units->expandUnitIdsWithDescendants($tenantId, $unitIds)
                : $unitIds;
            $ids = array_merge($ids, $this->units->listActiveUserIdsForUnits($tenantId, $scope !== [] ? $scope : $unitIds));
        }
        $roleSlugs = array_values(array_filter(array_map('strval', (array) ($definition['role_slugs'] ?? []))));
        if ($roleSlugs !== []) {
            $ids = array_merge($ids, $this->users->listActiveUserIdsWithOrganizationRoleSlugs($tenantId, $roleSlugs));
        }
        $ids = array_merge($ids, array_map('intval', (array) ($definition['extra_user_ids'] ?? [])));

        return array_values(array_unique(array_filter($ids, static fn (int $id): bool => $id > 0)));
    }

    /**
     * Transforme une sélection (groupes + membres nommés) en destinataires réels.
     *
     * @param list<string> $groupKeys
     * @param list<int> $memberIds
     * @return array{recipients: array<int, string|null>, labels: list<string>, summary: string, error: string|null}
     */
    public function resolveSelection(int $tenantId, int $senderId, array $groupKeys, array $memberIds): array
    {
        $available = $this->groupLabels($tenantId);
        $directory = array_flip($this->directoryIds($tenantId));

        /** @var array<int, string|null> $recipients */
        $recipients = [];
        $labels = [];

        foreach ($groupKeys as $key) {
            $key = trim((string) $key);
            if ($key === '' || !isset($available[$key])) {
                continue;
            }
            $members = $this->membersOfGroup($tenantId, $key);
            if ($members === []) {
                continue;
            }
            $added = 0;
            foreach ($members as $uid) {
                if ($uid === $senderId) {
                    continue;
                }
                if (!array_key_exists($uid, $recipients)) {
                    $recipients[$uid] = $available[$key];
                }
                $added++;
            }
            if ($added > 0) {
                $labels[] = $available[$key] . ' (' . $added . ')';
            }
        }

        $named = 0;
        foreach ($memberIds as $id) {
            $id = (int) $id;
            if ($id < 1 || $id === $senderId || !isset($directory[$id])) {
                continue;
            }
            if (!array_key_exists($id, $recipients)) {
                $recipients[$id] = null;
                $named++;
            }
        }
        if ($named > 0) {
            $labels[] = $named > 1
                ? $named . ' destinataires nominatifs'
                : '1 destinataire nominatif';
        }

        $error = null;
        if ($recipients === []) {
            $error = 'Choisissez au moins un destinataire ou un groupe de diffusion.';
        } elseif (count($recipients) > self::MAX_RECIPIENTS) {
            $error = 'Cette diffusion dépasse ' . self::MAX_RECIPIENTS . ' destinataires. Réduisez la sélection ou passez par une annonce.';
        }

        return [
            'recipients' => $recipients,
            'labels' => $labels,
            'summary' => implode(' · ', $labels),
            'error' => $error,
        ];
    }
}
