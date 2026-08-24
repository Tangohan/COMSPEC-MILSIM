<?php

declare(strict_types=1);

namespace App\Services\Portal;

use App\Core\Gate;

/**
 * Propositions de suite de parcours (libellés métier, liens profonds).
 *
 * @phpstan-type Step array{label: string, description: string, href: string, accent?: string}
 */
final class PortalNextStepsService
{
    /**
     * @return list<Step>
     */
    public static function forHub(Gate $gate): array
    {
        $steps = [];
        $steps[] = [
            'label' => 'Voir ce qui demande votre attention',
            'description' => 'Synthèse des notifications et dossiers en attente, avec liens vers les écrans concernés.',
            'href' => url('aujourdhui'),
            'accent' => 'emerald',
        ];
        if ($gate->allows('forum.view')) {
            $steps[] = [
                'label' => 'Consulter votre activité récente',
                'description' => 'Forum, courrier interne et fils de discussion récents.',
                'href' => url('activite'),
                'accent' => 'sky',
            ];
        }
        if (! $gate->deny('documents.view')) {
            $steps[] = [
                'label' => 'Parcourir les documents publiés',
                'description' => 'Doctrine, consignes et supports accessibles selon vos droits.',
                'href' => url('documents'),
                'accent' => 'teal',
            ];
        }
        if ($gate->allows('courrier.view')) {
            $steps[] = [
                'label' => 'Ouvrir le bureau courrier',
                'description' => 'Circuits de validation, signatures et documents officiels.',
                'href' => url('courrier'),
                'accent' => 'amber',
            ];
        }

        return array_slice($steps, 0, 4);
    }

    /**
     * @return list<Step>
     */
    public static function forDashboard(
        Gate $gate,
        bool $hasMyPendingEnlistment,
        bool $hasStaffRecruitmentQueue,
        bool $trainingFeatureEnabled,
    ): array {
        $steps = [];
        if ($hasMyPendingEnlistment) {
            $steps[] = [
                'label' => 'Finaliser votre dossier de recrutement',
                'description' => 'Complétez les informations demandées pour que l’encadrement puisse traiter votre demande.',
                'href' => url('account'),
                'accent' => 'rose',
            ];
        }
        if ($hasStaffRecruitmentQueue) {
            $steps[] = [
                'label' => 'Traiter les dossiers en attente',
                'description' => 'Accédez à la file des candidatures soumises pour votre communauté.',
                'href' => url('back-office/recruitments'),
                'accent' => 'violet',
            ];
        }
        if ($trainingFeatureEnabled) {
            $steps[] = [
                'label' => 'Poursuivre vos formations',
                'description' => 'Reprenez un parcours ou découvrez le catalogue des modules disponibles.',
                'href' => url('formations/mes-formations'),
                'accent' => 'emerald',
            ];
        }
        $steps[] = [
            'label' => 'Explorer le centre opérationnel',
            'description' => 'Raccourcis par thème : opérations, personnel, ressources et administration.',
            'href' => url('hub'),
            'accent' => 'slate',
        ];
        if (! $gate->deny('documents.view')) {
            $steps[] = [
                'label' => 'Rechercher une ressource',
                'description' => 'Trouvez rapidement un document, un sujet de forum ou une fiche personnelle autorisée.',
                'href' => url('search'),
                'accent' => 'sky',
            ];
        }

        return array_slice($steps, 0, 5);
    }
}
