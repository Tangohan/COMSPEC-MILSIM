<?php

declare(strict_types=1);

namespace App\Services\Personnel;

use App\Repositories\PersonnelOrgHistoryRepository;
use App\Repositories\RoleRepository;

/**
 * Construit des libellés lisibles pour l’historique affiché sur la fiche personnel.
 */
final class PersonnelOrgHistoryRecorder
{
    public function __construct(
        private PersonnelOrgHistoryRepository $historyRepository,
        private RoleRepository $roleRepository,
    ) {}

    /**
     * @param array<string, mixed> $beforeRow users.* avant mise à jour
     * @param array<string, mixed> $afterRow users.* après mise à jour
     */
    public function recordUserTableDiff(
        int $tenantId,
        array $beforeRow,
        array $afterRow,
        int $actorUserId,
        string $actorDisplayName,
    ): void {
        if (!$this->historyRepository->schemaReady()) {
            return;
        }
        $uid = (int) ($afterRow['id'] ?? 0);
        if ($uid < 1) {
            return;
        }
        $actorLabel = trim($actorDisplayName) !== '' ? trim($actorDisplayName) : 'Encadrement';

        $lines = [];

        $emB = strtolower(trim((string) ($beforeRow['email'] ?? '')));
        $emA = strtolower(trim((string) ($afterRow['email'] ?? '')));
        if ($emB !== $emA) {
            $lines[] = 'Adresse e-mail du compte mise à jour';
        }

        // Grade / affectation / fonction : consignés via PersonnelStructureChangeNotificationService
        // (même chemin que l’e-mail « Dossier personnel »), pour éviter les doublons.

        $stB = (string) ($beforeRow['status'] ?? '');
        $stA = (string) ($afterRow['status'] ?? '');
        if ($stB !== $stA) {
            $lines[] = 'Statut du compte : ' . $this->statusLabel($stB) . ' → ' . $this->statusLabel($stA);
        }

        $dnB = trim((string) ($beforeRow['display_name'] ?? ''));
        $dnA = trim((string) ($afterRow['display_name'] ?? ''));
        if ($dnB !== $dnA) {
            $lines[] = 'Nom d’affichage : « ' . ($dnB !== '' ? $dnB : '—') . ' » → « ' . ($dnA !== '' ? $dnA : '—') . ' »';
        }

        $csB = trim((string) ($beforeRow['callsign'] ?? ''));
        $csA = trim((string) ($afterRow['callsign'] ?? ''));
        if ($csB !== $csA) {
            $lines[] = 'Indicatif : « ' . ($csB !== '' ? $csB : '—') . ' » → « ' . ($csA !== '' ? $csA : '—') . ' »';
        }

        $pcB = trim((string) ($beforeRow['professional_category_code'] ?? ''));
        $pcA = trim((string) ($afterRow['professional_category_code'] ?? ''));
        if ($pcB !== $pcA) {
            $lines[] = 'Catégorie professionnelle : « ' . ($pcB !== '' ? $pcB : '—') . ' » → « ' . ($pcA !== '' ? $pcA : '—') . ' »';
        }

        if ($lines === []) {
            return;
        }
        $summary = implode(' · ', $lines) . ' — par ' . $actorLabel;
        $this->historyRepository->append($tenantId, $uid, $actorUserId > 0 ? $actorUserId : null, $summary);
    }

    /**
     * Consigne grade / affectation / fonction tels qu’annoncés dans l’e-mail de structure.
     *
     * @param list<array{type?: string, label: string, from: string, to: string}> $changes
     */
    public function recordStructureChanges(
        int $tenantId,
        int $userId,
        ?int $actorUserId,
        array $changes,
    ): void {
        if (!$this->historyRepository->schemaReady() || $tenantId < 1 || $userId < 1 || $changes === []) {
            return;
        }
        $lines = [];
        foreach ($changes as $c) {
            if (!is_array($c)) {
                continue;
            }
            $label = trim((string) ($c['label'] ?? ''));
            if ($label === '') {
                continue;
            }
            $from = trim((string) ($c['from'] ?? ''));
            $to = trim((string) ($c['to'] ?? ''));
            $lines[] = $label . ' : '
                . ($from !== '' ? $from : 'Non renseigné')
                . ' → '
                . ($to !== '' ? $to : 'Non renseigné');
        }
        if ($lines === []) {
            return;
        }
        $this->historyRepository->append(
            $tenantId,
            $userId,
            $actorUserId !== null && $actorUserId > 0 ? $actorUserId : null,
            implode(' · ', $lines)
        );
    }

    /**
     * @param list<int> $oldRoleIds
     * @param list<int> $newRoleIds
     */
    public function recordOrganizationRolesChange(
        int $tenantId,
        int $userId,
        int $actorUserId,
        string $actorDisplayName,
        array $oldRoleIds,
        array $newRoleIds,
    ): void {
        if (!$this->historyRepository->schemaReady() || $tenantId < 1 || $userId < 1) {
            return;
        }
        $a = array_values(array_filter(array_map('intval', $oldRoleIds), static fn (int $x) => $x > 0));
        $b = array_values(array_filter(array_map('intval', $newRoleIds), static fn (int $x) => $x > 0));
        sort($a);
        sort($b);
        if ($a === $b) {
            return;
        }
        $actorLabel = trim($actorDisplayName) !== '' ? trim($actorDisplayName) : 'Encadrement';
        $summary = 'Rôles organisation : « '
            . $this->rolesListLabel($tenantId, $a)
            . ' » → « '
            . $this->rolesListLabel($tenantId, $b)
            . ' » — par '
            . $actorLabel;
        $this->historyRepository->append($tenantId, $userId, $actorUserId > 0 ? $actorUserId : null, $summary);
    }

    /** @param list<int> $ids */
    private function rolesListLabel(int $tenantId, array $ids): string
    {
        if ($ids === []) {
            return '—';
        }
        $labels = [];
        foreach ($ids as $rid) {
            $labels[] = $this->roleLabel($tenantId, $rid);
        }

        return implode(', ', $labels);
    }

    private function roleLabel(int $tenantId, int $roleId): string
    {
        if ($roleId < 1) {
            return '—';
        }
        $row = $this->roleRepository->findById($roleId, $tenantId)
            ?? $this->roleRepository->findById($roleId, null);
        if (!$row) {
            return 'Référence ' . $roleId;
        }
        $n = trim((string) ($row['name'] ?? ''));

        return $n !== '' ? $n : 'Rôle';
    }

    private function statusLabel(string $status): string
    {
        return match ($status) {
            'active' => 'Compte actif',
            'inactive' => 'Compte inactif',
            'pending_verification' => 'En attente de vérification de l’e-mail',
            'pending' => 'Compte en attente',
            default => $status !== '' ? 'Statut à confirmer' : '—',
        };
    }
}
