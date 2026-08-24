<?php

declare(strict_types=1);

namespace App\Services\Portal;

/**
 * Parcours courts partagés entre la vitrine et l'accueil authentifié.
 */
final class OnboardingPersonaCatalog
{
    /** @return array<string, array{label: string, eyebrow: string, description: string, steps: list<array{label: string, description: string, href: string}>}> */
    public static function all(): array
    {
        return [
            'member' => [
                'label' => 'Membre d’unité',
                'eyebrow' => 'Rejoindre et participer',
                'description' => 'Retrouvez votre fiche, les rendez-vous et les ressources utiles sans parcourir tout le portail.',
                'steps' => [
                    ['label' => 'Vérifier ma fiche', 'description' => 'Complétez votre identité opérationnelle et vos informations visibles.', 'href' => url('personnel/me')],
                    ['label' => 'Voir ce qui m’attend', 'description' => 'Regroupez messages, dossiers et notifications à traiter aujourd’hui.', 'href' => url('aujourdhui')],
                    ['label' => 'Consulter les prochains rendez-vous', 'description' => 'Retrouvez les événements et confirmez votre participation.', 'href' => url('evenements')],
                ],
            ],
            'command' => [
                'label' => 'Commandement',
                'eyebrow' => 'Piloter l’unité',
                'description' => 'Structurez les effectifs, les accès et les priorités quotidiennes de votre organisation.',
                'steps' => [
                    ['label' => 'Ouvrir le briefing du jour', 'description' => 'Identifiez les dossiers et messages qui attendent une décision.', 'href' => url('aujourdhui')],
                    ['label' => 'Contrôler les effectifs', 'description' => 'Consultez l’organisation, les affectations et les disponibilités.', 'href' => url('back-office/ressources/effectifs')],
                    ['label' => 'Configurer les accès', 'description' => 'Vérifiez les rôles et permissions avant d’inviter votre équipe.', 'href' => url('back-office/roles')],
                ],
            ],
            'operations' => [
                'label' => 'Opérations & ATAK',
                'eyebrow' => 'Préparer et débriefer',
                'description' => 'Passez du briefing à la carte tactique, puis relisez la mission sur une chronologie commune.',
                'steps' => [
                    ['label' => 'Préparer une opération', 'description' => 'Centralisez objectifs, ordres et ressources de mission.', 'href' => url('tableau-operationnel')],
                    ['label' => 'Ouvrir la carte ATAK', 'description' => 'Suivez les unités, rapports et alertes autorisés.', 'href' => url('atak')],
                    ['label' => 'Relire la mission', 'description' => 'Utilisez la frise, les filtres événementiels et le bilan après-action.', 'href' => url('atak') . '#replay'],
                ],
            ],
            'training' => [
                'label' => 'Formation',
                'eyebrow' => 'Transmettre et qualifier',
                'description' => 'Organisez les parcours, suivez la progression et retrouvez les qualifications à renouveler.',
                'steps' => [
                    ['label' => 'Parcourir les formations', 'description' => 'Découvrez les cursus accessibles dans votre communauté.', 'href' => url('formations')],
                    ['label' => 'Reprendre mon parcours', 'description' => 'Continuez les modules déjà commencés.', 'href' => url('formations/mes-formations')],
                    ['label' => 'Consulter les ressources', 'description' => 'Retrouvez doctrine, supports et documents publiés.', 'href' => url('documents')],
                ],
            ],
        ];
    }

    public static function normalize(?string $persona): ?string
    {
        $persona = strtolower(trim((string) $persona));

        return array_key_exists($persona, self::all()) ? $persona : null;
    }
}
