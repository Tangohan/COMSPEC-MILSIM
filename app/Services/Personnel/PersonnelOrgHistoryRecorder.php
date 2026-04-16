<?php

declare(strict_types=1);

namespace App\Services\Personnel;

use App\Repositories\GradeRepository;
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
        private GradeRepository $gradeRepository,
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

        $gidB = isset($beforeRow['grade_id']) ? (int) $beforeRow['grade_id'] : 0;
        $gidA = isset($afterRow['grade_id']) ? (int) $afterRow['grade_id'] : 0;
        if ($gidB !== $gidA) {
            $lines[] = 'Grade : '
                . $this->gradeLabel($tenantId, $gidB)
                . ' → '
                . $this->gradeLabel($tenantId, $gidA);
        }

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

    private function gradeLabel(int $tenantId, int $gradeId): string
    {
        if ($gradeId < 1) {
            return '—';
        }
        $row = $this->gradeRepository->findById($gradeId, $tenantId);
        if (!$row) {
            return 'Référence ' . $gradeId;
        }
        $short = trim((string) ($row['short_name'] ?? ''));
        $long = trim((string) ($row['name'] ?? ''));

        return $short !== '' ? $short : ($long !== '' ? $long : 'Grade');
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
