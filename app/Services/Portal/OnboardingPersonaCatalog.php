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
                'label' => __('home.persona_member_label'),
                'eyebrow' => __('home.persona_member_eyebrow'),
                'description' => __('home.persona_member_description'),
                'steps' => [
                    ['label' => 'Vérifier ma fiche', 'description' => 'Complétez votre identité opérationnelle et vos informations visibles.', 'href' => url('personnel/me')],
                    ['label' => 'Voir ce qui m’attend', 'description' => 'Regroupez messages, dossiers et notifications à traiter aujourd’hui.', 'href' => url('aujourdhui')],
                    ['label' => 'Consulter les prochains rendez-vous', 'description' => 'Retrouvez les événements et confirmez votre participation.', 'href' => url('evenements')],
                ],
            ],
            'command' => [
                'label' => __('home.persona_command_label'),
                'eyebrow' => __('home.persona_command_eyebrow'),
                'description' => __('home.persona_command_description'),
                'steps' => [
                    ['label' => 'Ouvrir le briefing du jour', 'description' => 'Identifiez les dossiers et messages qui attendent une décision.', 'href' => url('aujourdhui')],
                    ['label' => 'Contrôler les effectifs', 'description' => 'Consultez l’organisation, les affectations et les disponibilités.', 'href' => url('back-office/ressources/effectifs')],
                    ['label' => 'Configurer les accès', 'description' => 'Vérifiez les rôles et permissions avant d’inviter votre équipe.', 'href' => url('back-office/roles')],
                ],
            ],
            'operations' => [
                'label' => __('home.persona_operations_label'),
                'eyebrow' => __('home.persona_operations_eyebrow'),
                'description' => __('home.persona_operations_description'),
                'steps' => [
                    ['label' => 'Préparer une opération', 'description' => 'Centralisez objectifs, ordres et ressources de mission.', 'href' => url('tableau-operationnel')],
                    ['label' => 'Ouvrir la carte ATAK', 'description' => 'Suivez les unités, rapports et alertes autorisés.', 'href' => url('atak')],
                    ['label' => 'Relire la mission', 'description' => 'Utilisez la frise, les filtres événementiels et le bilan après-action.', 'href' => url('atak') . '#replay'],
                ],
            ],
            'training' => [
                'label' => __('home.persona_training_label'),
                'eyebrow' => __('home.persona_training_eyebrow'),
                'description' => __('home.persona_training_description'),
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

    /** @return list<int> */
    public static function normalizeCompletedSteps(mixed $stored, int $stepCount = 3): array
    {
        if (!is_array($stored) || $stepCount < 1) {
            return [];
        }
        $scalarSteps = array_filter(
            $stored,
            static fn (mixed $value): bool => is_int($value) || (is_string($value) && preg_match('/^-?\d+$/', $value) === 1)
        );
        $steps = array_values(array_unique(array_map('intval', $scalarSteps)));
        sort($steps);

        return array_values(array_filter(
            $steps,
            static fn (int $index): bool => $index >= 0 && $index < $stepCount
        ));
    }
}
