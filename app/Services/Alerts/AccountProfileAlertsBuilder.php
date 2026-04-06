<?php

declare(strict_types=1);

namespace App\Services\Alerts;

use App\Repositories\EnlistmentRepository;
use App\Repositories\PersonnelProfileRepository;
use App\Repositories\UserProfileRepository;
use App\Repositories\UserRepository;

/**
 * Alertes synthétiques (cloche header) lorsque le dossier compte / RP est incomplet.
 */
final class AccountProfileAlertsBuilder
{
    public function __construct(
        private UserRepository $users,
        private UserProfileRepository $userProfiles,
        private PersonnelProfileRepository $personnelProfiles,
        private EnlistmentRepository $enlistments
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

        $profile = $this->userProfiles->getByUserId($userId);
        $personnelProfile = $this->personnelProfiles->getByUserId($userId);
        $latestEnlistment = $this->enlistments->findLatestBySubmitter($tenantId, $userId);

        $civil = $this->resolveCivilIdentity($profile, $user, $latestEnlistment);
        $missingCivil = $civil['first_name'] === '' || $civil['last_name'] === '';

        $rpName = trim((string) ($personnelProfile['character_name'] ?? ''));
        $missingRp = $rpName === '';

        $out = [];

        if ($missingCivil) {
            $out[] = [
                'scope' => 'Compte',
                'id' => -1001,
                'kind' => 'info',
                'title' => 'Identité civile incomplète',
                'body' => 'Votre prénom et/ou votre nom ne sont pas renseignés dans le dossier compte. Complétez-les pour les fiches, l’administration et la cohérence avec vos candidatures.',
                'cta_label' => 'Ouvrir les préférences',
                'cta_url' => url('account/preferences'),
                'coupon_code' => null,
            ];
        }

        if ($missingRp) {
            $out[] = [
                'scope' => 'Compte',
                'id' => -1002,
                'kind' => 'novelty',
                'title' => 'Identité de personnage (RP) à définir',
                'body' => 'Aucun nom d’opérateur / RP n’est enregistré sur votre fiche personnelle. Renseignez-le pour l’ORBAT, le forum et les documents.',
                'cta_label' => 'Éditer la fiche personnel',
                'cta_url' => url('personnel/me/edit'),
                'coupon_code' => null,
            ];
        }

        return $out;
    }

    /**
     * @return array{first_name: string, last_name: string, source: ?string}
     */
    private function resolveCivilIdentity(?array $userProfile, array $targetUser, ?array $enlistment): array
    {
        $up = $userProfile ?? [];
        $fn = trim((string) ($up['first_name'] ?? ''));
        $ln = trim((string) ($up['last_name'] ?? ''));
        $source = ($fn !== '' || $ln !== '') ? 'profile' : null;

        if ($fn === '' && $ln === '' && $enlistment !== null) {
            $fn = trim((string) ($enlistment['first_name'] ?? ''));
            $ln = trim((string) ($enlistment['last_name'] ?? ''));
            if ($fn !== '' || $ln !== '') {
                $source = 'enlistment';
            }
        }

        if ($fn === '' && $ln === '') {
            $dn = trim((string) ($targetUser['display_name'] ?? ''));
            if ($dn !== '') {
                $parts = preg_split('/\s+/u', $dn, 2, PREG_SPLIT_NO_EMPTY);
                if ($parts !== false && $parts !== []) {
                    $fn = $parts[0];
                    $ln = isset($parts[1]) ? trim($parts[1]) : '';
                    $source = 'display_name';
                }
            }
        }

        return ['first_name' => $fn, 'last_name' => $ln, 'source' => $source];
    }
}
