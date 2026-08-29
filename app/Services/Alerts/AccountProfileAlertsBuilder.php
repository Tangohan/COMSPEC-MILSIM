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
        $missingMedia = (new \App\Services\Media\MissingUserMediaScanner())->missingKindsForUser($user, $personnelProfile);

        $out = [];

        if ($missingMedia !== []) {
            $labels = [
                'avatar' => 'photo de compte',
                'portrait' => 'portrait personnage',
                'banner' => 'bannière',
            ];
            $parts = [];
            foreach ($missingMedia as $k) {
                $parts[] = $labels[$k] ?? $k;
            }
            $ctaUrl = in_array('portrait', $missingMedia, true)
                ? url('account/portrait')
                : url('account/image');
            $out[] = [
                'scope' => 'Compte',
                'id' => -1003,
                'kind' => 'novelty',
                'title' => 'Photo à re-téléverser',
                'body' => 'Après la migration, un fichier est introuvable sur le serveur ('
                    . implode(', ', $parts)
                    . '). Rechargez votre image pour la réafficher partout.',
                'cta_label' => 'Recharger ma photo',
                'cta_url' => $ctaUrl,
                'coupon_code' => null,
            ];
        }

        if ($missingRp) {
            $out[] = [
                'scope' => 'Compte',
                'id' => -1002,
                'kind' => 'novelty',
                'title' => 'Identité personnage à compléter',
                'body' => 'Renseignez au minimum le prénom et le nom du personnage (ou la nationalité) sur votre fiche pour l’ORBAT et le forum.',
                'cta_label' => 'Éditer la fiche personnel',
                'cta_url' => url('personnel/me/edit'),
                'coupon_code' => null,
            ];
        }

        return $out;
    }
}
