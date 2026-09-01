<?php

declare(strict_types=1);

namespace App\Services\Tactical;

use App\Repositories\ElevationRequestRepository;
use App\Repositories\UserRepository;

/**
 * Écarts d’accès sur la carte du poste : vues ou actions fermées par le grade,
 * le rôle ou la fonction — pas les outils d’administration.
 */
final class AtakMapAccessGapService
{
    /**
     * Vues / actions visibles sur la carte, ouvertes seulement à certains profils.
     * `grant_slugs` : le droit métier (sans les raccourcis d’administration).
     * `any_of` : ce qui ouvre réellement la vue pour la session courante.
     *
     * @var list<array{id: string, label: string, hint: string, grant_slugs: list<string>, any_of: list<string>}>
     */
    public const FEATURES = [
        [
            'id' => 'personnel_files',
            'label' => 'Fiches des opérateurs',
            'hint' => 'Ouvrir la fiche d’un contact depuis la carte',
            'grant_slugs' => ['personnel.profile.view'],
            'any_of' => ['personnel.profile.view', 'admin.access', 'admin.organization'],
        ],
        [
            'id' => 'sse_intel',
            'label' => 'Renseignement sur la carte',
            'hint' => 'Dossiers, photos terrain et points de renseignement',
            'grant_slugs' => ['atak.sse.access', 'atak.sse.case.manage', 'atak.sse.grant'],
            'any_of' => ['atak.sse.access', 'atak.sse.case.manage', 'atak.sse.grant', 'admin.access'],
        ],
        [
            'id' => 'mission_docs',
            'label' => 'Documents de mission',
            'hint' => 'Supports classifiés liés à l’opération',
            'grant_slugs' => ['documents.view'],
            'any_of' => ['documents.view', 'admin.access'],
        ],
    ];

    /** Personnes qui accordent déjà les accès : pas de fenêtre de demande. */
    private const GRANT_SLUGS = [
        'admin.access',
        'admin.organization',
        'admin.roles.manage',
        'personnel.grades.manage',
        'personnel.assignments.manage',
        'personnel.status.manage',
    ];

    /** @var callable(string): bool */
    private $allows;

    public function __construct(
        private UserRepository $userRepository,
        private ?ElevationRequestRepository $elevationRequestRepository = null,
        ?callable $allows = null,
    ) {
        $this->allows = $allows ?? static fn (string $slug): bool => function_exists('can') && can($slug);
    }

    /**
     * @param callable(string): bool $allows
     * @return list<array{id: string, label: string, hint: string}>
     */
    public static function gapsForAllows(callable $allows): array
    {
        $gaps = [];
        foreach (self::FEATURES as $feature) {
            if (!self::allowsAny($allows, $feature['any_of'])) {
                $gaps[] = [
                    'id' => $feature['id'],
                    'label' => $feature['label'],
                    'hint' => $feature['hint'],
                ];
            }
        }

        return $gaps;
    }

    /**
     * Payload carte web. `offer` n’est vrai que s’il existe des vues fermées
     * (l’affichage réel attend aussi la liaison en jeu, côté navigateur).
     *
     * @param array<string, mixed>|null $user
     * @return array{
     *   offer: bool,
     *   pending: bool,
     *   requestUrl: string,
     *   gaps: list<array{id: string, label: string, hint: string}>
     * }
     */
    public function webPayload(int $tenantId, ?array $user): array
    {
        $empty = [
            'offer' => false,
            'pending' => false,
            'requestUrl' => url('atak/demande-acces'),
            'gaps' => [],
        ];
        $userId = (int) ($user['id'] ?? 0);
        if ($tenantId < 1 || $userId < 1) {
            return $empty;
        }
        if (!empty($user['phoneSession'])) {
            return $empty;
        }
        $allows = $this->allows;
        if (self::allowsAny($allows, self::GRANT_SLUGS)) {
            return $empty;
        }

        $gaps = self::gapsForAllows($allows);
        $gaps = $this->keepGapsGrantedInCommunity($tenantId, $userId, $gaps);
        if ($gaps === []) {
            return $empty;
        }

        $pending = false;
        try {
            $repo = $this->elevationRequestRepository ?? new ElevationRequestRepository();
            $open = $repo->findOpenForRequesterTarget($tenantId, $userId, $userId);
            $pending = is_array($open);
        } catch (\Throwable) {
            $pending = false;
        }

        return [
            'offer' => true,
            'pending' => $pending,
            'requestUrl' => url('atak/demande-acces'),
            'gaps' => $gaps,
        ];
    }

    /**
     * @param list<array{id: string, label: string, hint: string}> $gaps
     */
    public static function formatRequestNote(array $gaps): string
    {
        $labels = [];
        foreach ($gaps as $gap) {
            $label = trim((string) ($gap['label'] ?? ''));
            if ($label !== '' && !in_array($label, $labels, true)) {
                $labels[] = $label;
            }
        }
        $list = $labels !== [] ? implode(' ; ', $labels) : 'certaines vues de la carte';

        return 'Demande depuis la carte du poste. L’opérateur est en liaison en jeu, mais son grade, son rôle ou sa fonction n’ouvrent pas : '
            . $list
            . '. Traitez la demande depuis les élévations du bureau effectifs (grade, rôle, fonction ou habilitation).';
    }

    /**
     * Si la communauté n’accorde la vue à personne, ce n’est pas un écart de profil.
     *
     * @param list<array{id: string, label: string, hint: string}> $gaps
     * @return list<array{id: string, label: string, hint: string}>
     */
    private function keepGapsGrantedInCommunity(int $tenantId, int $excludeUserId, array $gaps): array
    {
        if ($gaps === []) {
            return [];
        }
        $kept = [];
        foreach ($gaps as $gap) {
            $feature = $this->featureById((string) ($gap['id'] ?? ''));
            if ($feature === null) {
                continue;
            }
            if ($this->communityGrantsAny($tenantId, $excludeUserId, $feature['grant_slugs'])) {
                $kept[] = $gap;
            }
        }

        return $kept;
    }

    /**
     * @param list<string> $slugs
     */
    private function communityGrantsAny(int $tenantId, int $excludeUserId, array $slugs): bool
    {
        try {
            $ids = $this->userRepository->listActiveUserIdsWithAnyPermissionSlug($tenantId, $slugs);
        } catch (\Throwable) {
            // En cas d’échec (table absente, etc.), on conserve l’écart : mieux vaut proposer que masquer.
            return true;
        }
        foreach ($ids as $id) {
            if ((int) $id !== $excludeUserId) {
                return true;
            }
        }

        // Personne d’autre n’a le droit : soit la communauté ne l’utilise pas, soit seul le demandeur
        // le porterait — dans le second cas il n’y a pas d’écart. Si la liste est vide, on ne propose pas.
        return false;
    }

    /**
     * @return array{id: string, label: string, hint: string, grant_slugs: list<string>, any_of: list<string>}|null
     */
    private function featureById(string $id): ?array
    {
        foreach (self::FEATURES as $feature) {
            if ($feature['id'] === $id) {
                return $feature;
            }
        }

        return null;
    }

    /**
     * @param callable(string): bool $allows
     * @param list<string> $slugs
     */
    public static function allowsAny(callable $allows, array $slugs): bool
    {
        foreach ($slugs as $slug) {
            if ($allows($slug)) {
                return true;
            }
        }

        return false;
    }
}
