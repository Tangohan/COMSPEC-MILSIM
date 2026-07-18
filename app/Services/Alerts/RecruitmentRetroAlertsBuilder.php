<?php

declare(strict_types=1);

namespace App\Services\Alerts;

use App\Core\Gate;
use App\Repositories\EnlistmentRecruitmentEngagementRepository;
use App\Repositories\EnlistmentRepository;

/**
 * Alertes cloche automatiques : bilan de recrutement dû après 30 jours.
 */
final class RecruitmentRetroAlertsBuilder
{
    public function __construct(
        private EnlistmentRecruitmentEngagementRepository $engagement,
        private EnlistmentRepository $enlistments,
    ) {}

    /**
     * @return list<array{scope: string, id: int, kind: string, title: string, body: string, cta_label: ?string, cta_url: ?string, coupon_code: ?string}>
     */
    public function build(int $userId, int $tenantId): array
    {
        if ($userId <= 0 || $tenantId <= 0 || !$this->engagement->retroTableExists()) {
            return [];
        }

        $out = [];

        if ($this->viewerCanManageRecruitment()) {
            $due = $this->engagement->listStaffRetrosDue($tenantId, 5);
            $total = $this->engagement->countStaffRetrosDue($tenantId);
            if ($due !== []) {
                $first = $due[0];
                $name = trim(($first['first_name'] ?? '') . ' ' . ($first['last_name'] ?? ''));
                if ($name === '') {
                    $name = 'un dossier';
                }
                $extra = $total > 1 ? (' · ' . $total . ' dossiers concernés') : '';
                $out[] = [
                    'scope' => 'Recrutement',
                    'id' => -2100 - (int) $first['id'],
                    'kind' => $total >= 3 ? 'urgent' : 'rappel',
                    'title' => 'Bilan à renseigner',
                    'body' => 'Le dossier de ' . $name . ' a plus de 30 jours' . $extra . '. Laissez une courte note pour améliorer le processus.',
                    'cta_label' => $total === 1 ? 'Ouvrir le dossier' : 'Voir les dossiers',
                    'cta_url' => $total === 1
                        ? url('back-office/recruitments/' . (int) $first['id'] . '?dossier=1#bilan-recrutement')
                        : url('back-office/recruitments'),
                    'coupon_code' => null,
                ];
            }
        }

        $candidateDue = $this->engagement->listCandidateRetrosDueForSubmitter($tenantId, $userId, 3);
        foreach ($candidateDue as $i => $row) {
            $eid = (int) ($row['id'] ?? 0);
            if ($eid < 1) {
                continue;
            }
            $token = $this->enlistments->findValidCandidatePortalTokenForEnlistment($tenantId, $eid);
            if ($token === null) {
                $token = $this->enlistments->ensureCandidatePortalToken($tenantId, $eid, 24 * 14);
            }
            if ($token === null) {
                continue;
            }
            $age = (int) ($row['age_days'] ?? 30);
            $out[] = [
                'scope' => 'Candidature',
                'id' => -2200 - $eid,
                'kind' => 'rappel',
                'title' => 'Votre avis sur le recrutement',
                'body' => 'Votre dossier a été reçu il y a ' . $age . ' jours. Un court retour aide l’équipe à améliorer l’accueil des candidats.',
                'cta_label' => 'Laisser mon avis',
                'cta_url' => url('enlistment/suivi/' . rawurlencode($token) . '#bilan-processus'),
                'coupon_code' => null,
            ];
            if ($i >= 1) {
                break;
            }
        }

        return $out;
    }

    private function viewerCanManageRecruitment(): bool
    {
        try {
            $gate = Gate::getInstance();
            if ($gate->allows('organization.recruitment.manage')
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
