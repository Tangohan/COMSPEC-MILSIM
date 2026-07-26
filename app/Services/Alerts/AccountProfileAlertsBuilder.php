<?php

declare(strict_types=1);

namespace App\Services\Alerts;

use App\Services\Profile\RecruitmentPresetPayloadService;
use App\Repositories\PersonnelProfileRepository;
use App\Repositories\UserRepository;

/**
 * Alertes synthétiques (cloche header) lorsque le dossier RP est incomplet.
 */
final class AccountProfileAlertsBuilder
{
    public function __construct(
        private UserRepository $users,
        private PersonnelProfileRepository $personnelProfiles,
    ) {}

    /**
     * @return list<array{scope: string, id: int, kind: string, title: string, body: string, cta_label: ?string, cta_url: ?string, coupon_code: ?string}>
     */
    public function build(int $userId, int $tenantId): array
    {
        if ($userId <= 0 || $tenantId <= 0) {
            return [];
        }
        $user = $this->users->findById($userId, $tenantId);
        if ($user === null) {
            return [];
        }

        $personnelProfile = $this->personnelProfiles->getByUserId($userId) ?? [];
        $missingRp = RecruitmentPresetPayloadService::personnelRpDossierNeedsAttention($personnelProfile);

        $out = [];

        if ($missingRp) {
            $out[] = [
                'scope' => 'Compte',
                'id' => -1002,
                'kind' => 'novelty',
                'title' => 'Identité personnage à compléter',
                'body' => 'Renseignez au minimum le nom affiché dossier ou la nationalité personnage sur votre fiche (ou via un profil de candidature) pour l’ORBAT et le forum.',
                'cta_label' => 'Éditer la fiche personnel',
                'cta_url' => url('personnel/me/edit'),
                'coupon_code' => null,
            ];
        }

        return $out;
    }
}
