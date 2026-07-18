<?php

declare(strict_types=1);

namespace App\Services\Alerts;

use App\Core\Gate;
use App\Repositories\ProbationOversightRepository;
use App\Repositories\RoleRepository;

/**
 * Alerte cloche automatique : période d’essai (rôle « Période d’essai ») dépassant 60 jours
 * sans titularisation ni bilan de fin de période.
 */
final class ProbationOverdueAlertsBuilder
{
    private const DAYS_THRESHOLD = 60;

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

        $due = $this->probation->listOverdue($tenantId, self::DAYS_THRESHOLD, 5);
        if ($due === []) {
            return [];
        }
        $total = $this->probation->countOverdue($tenantId, self::DAYS_THRESHOLD);

        $first = $due[0];
        $name = $this->displayName($first);
        $age = (int) ($first['age_days'] ?? self::DAYS_THRESHOLD);

        if ($total === 1) {
            $userIdTarget = (int) $first['user_id'];
            $body = 'Le dossier de ' . $name . ' est en période d’essai depuis ' . $age . ' jours (au-delà des ' . self::DAYS_THRESHOLD . ' jours de référence). Faites le point pour titulariser ou prolonger l’intégration.';
            $ctaLabel = 'Faire le bilan de fin d’essai';
            $ctaUrl = url('personnel/' . $userIdTarget . '?tab=bilans&bilan_stage=' . rawurlencode('Fin de période d’essai') . '#bilan-create');
            $ctaSecondaryLabel = 'Modifier le rôle';
            $ctaSecondaryUrl = url('personnel/' . $userIdTarget . '/edit');
        } else {
            $body = $total . ' membres sont en période d’essai depuis plus de ' . self::DAYS_THRESHOLD . ' jours, dont ' . $name . ' (' . $age . ' jours). Faites le point sur ces dossiers pour titulariser ou prolonger l’intégration.';
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
