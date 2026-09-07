<?php

declare(strict_types=1);

namespace App\Services\Alerts;

use App\Core\Gate;
use App\Repositories\ProbationOversightRepository;
use App\Repositories\RoleRepository;
use App\Services\Personnel\RoleplayFollowupSettings;

/**
 * Alerte cloche automatique : période d’essai dépassant la durée de la communauté
 * sans titularisation ni bilan de fin de période.
 */
final class ProbationOverdueAlertsBuilder
{
    public function __construct(
        private ProbationOversightRepository $probation,
        private RoleRepository $roles,
    ) {}

    /**
     * @return list<array{scope: string, id: int, kind: string, title: string, body: string, cta_label: ?string, cta_url: ?string, cta_secondary_label: ?string, cta_secondary_url: ?string, coupon_code: ?string}>
     */
    public function build(int $userId, int $tenantId): array
    {
        if ($userId <= 0 || $tenantId <= 0 || !$this->viewerCanOverseeProbation()) {
            return [];
        }

        $cfg = RoleplayFollowupSettings::forTenant($tenantId);
        $probation = is_array($cfg['probation'] ?? null) ? $cfg['probation'] : [];
        if (array_key_exists('alert_enabled', $probation) && empty($probation['alert_enabled'])) {
            return [];
        }
        $threshold = max(14, min(365, (int) ($probation['alert_after_days'] ?? $probation['duration_days'] ?? 60)));
        $bilanLabel = trim((string) ($probation['bilan_label'] ?? 'Fin de période d’essai')) ?: 'Fin de période d’essai';

        $due = $this->probation->listOverdue($tenantId, $threshold, 5);
        if ($due === []) {
            return [];
        }
        $total = $this->probation->countOverdue($tenantId, $threshold);

        $first = $due[0];
        $name = $this->displayName($first);
        $age = (int) ($first['age_days'] ?? $threshold);

        if ($total === 1) {
            $userIdTarget = (int) $first['user_id'];
            $body = 'Le dossier de ' . $name . ' est en période d’essai depuis ' . $age . ' jours (au-delà des ' . $threshold . ' jours de référence). Faites le point pour titulariser ou prolonger l’intégration.';
            $ctaLabel = 'Faire le bilan de fin d’essai';
            $ctaUrl = url('personnel/' . $userIdTarget . '?tab=bilans&bilan_stage=' . rawurlencode($bilanLabel) . '#bilan-create');
            $ctaSecondaryLabel = 'Modifier le rôle';
            $ctaSecondaryUrl = url('personnel/' . $userIdTarget . '/edit');
        } else {
            $body = $total . ' membres sont en période d’essai depuis plus de ' . $threshold . ' jours, dont ' . $name . ' (' . $age . ' jours). Faites le point sur ces dossiers pour titulariser ou prolonger l’intégration.';
            $ctaLabel = 'Voir les membres concernés';
            $ctaUrl = $this->overdueListUrl($tenantId);
            $ctaSecondaryLabel = null;
            $ctaSecondaryUrl = null;
        }

        return [[
            'scope' => 'Personnel',
            'id' => -2400 - (int) $first['user_id'],
            'kind' => $total >= 3 ? 'urgent' : 'rappel',
            'title' => 'Période d’essai à examiner',
            'body' => $body,
            'cta_label' => $ctaLabel,
            'cta_url' => $ctaUrl,
            'cta_secondary_label' => $ctaSecondaryLabel,
            'cta_secondary_url' => $ctaSecondaryUrl,
            'coupon_code' => null,
        ]];
    }

    /**
     * @param array{first_name: string, last_name: string, display_name: string} $row
     */
    private function displayName(array $row): string
    {
        $name = trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? ''));
        if ($name !== '') {
            return $name;
        }
        $dn = trim((string) ($row['display_name'] ?? ''));

        return $dn !== '' ? $dn : 'un membre';
    }

    private function overdueListUrl(int $tenantId): string
    {
        $roleId = $this->roles->getIdBySlug($tenantId, 'probation');

        return $roleId !== null
            ? url('back-office/users?role_id=' . $roleId)
            : url('back-office/users');
    }

    private function viewerCanOverseeProbation(): bool
    {
        try {
            $gate = Gate::getInstance();
            if ($gate->allows('personnel.profile.update')
                || $gate->allows('admin.organization')
                || $gate->allows('admin.access')) {
                return true;
            }
        } catch (\Throwable) {
            return false;
        }

        return false;
    }
}
