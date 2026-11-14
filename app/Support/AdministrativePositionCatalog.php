<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Positions administratives d’un membre dans sa communauté, et notions « en mission » / « en service ».
 *
 * Source unique des libellés et des explications montrés aux membres (fiche, annuaire, guide).
 * Chaque position est stockée comme un rôle d’affichage (semantic_tier « status ») portant le slug ci-dessous :
 * un membre n’a qu’une position à la fois. Les textes sont en français ; l’anglais vient du dictionnaire du site.
 */
final class AdministrativePositionCatalog
{
    public const TRAINING = 'status_in_training';
    public const ACTIVE = 'status_active_duty';
    public const INACTIVE = 'status_inactive_duty';
    public const REINFORCEMENT = 'status_reinforcement';

    /**
     * Ordre d’affichage et de priorité (le premier trouvé l’emporte si un membre en porte deux par erreur).
     *
     * @return list<string>
     */
    public static function slugs(): array
    {
        return [self::ACTIVE, self::REINFORCEMENT, self::INACTIVE, self::TRAINING];
    }

    /**
     * Ordre du parcours, pour les listes de choix et le guide.
     *
     * @return list<array{slug: string, label: string, summary: string, detail: string, mission: string, service: string, strength: string, tone: string, priority: int}>
     */
    public static function all(): array
    {
        return [
            [
                'slug' => self::TRAINING,
                'label' => 'En formation',
                'summary' => 'Nouvel arrivant en cours d’accueil et d’instruction initiale.',
                'detail' => 'Position donnée automatiquement à l’arrivée dans la communauté. Le membre suit son parcours d’intégration avec un référent. Il passe en service actif à la fin de l’accueil, une fois la durée minimale de formation écoulée.',
                'mission' => 'Oui, encadré',
                'service' => 'Oui : instruction et stages',
                'strength' => 'Non',
                'tone' => 'info',
                'priority' => 20,
            ],
            [
                'slug' => self::ACTIVE,
                'label' => 'En service actif',
                'summary' => 'Membre opérationnel et disponible, engagé en mission comme en service.',
                'detail' => 'Position normale d’un membre formé. Il peut être engagé sur toutes les missions de l’unité et tient son service courant : entraînements, permanences, réunions.',
                'mission' => 'Oui',
                'service' => 'Oui',
                'strength' => 'Oui',
                'tone' => 'ok',
                'priority' => 70,
            ],
            [
                'slug' => self::INACTIVE,
                'label' => 'En service non actif',
                'summary' => 'Toujours membre, mais temporairement indisponible : pause, congé ou absence longue.',
                'detail' => 'Le membre garde son grade, ses qualifications, son ancienneté et son affectation. Il n’est ni engagé en mission ni attendu au service courant. Remettez-le en service actif à son retour.',
                'mission' => 'Non',
                'service' => 'Non',
                'strength' => 'Non',
                'tone' => 'muted',
                'priority' => 60,
            ],
            [
                'slug' => self::REINFORCEMENT,
                'label' => 'En service statutaire de renforcement',
                'summary' => 'Renfort ponctuel : réserviste ou membre venu d’une autre unité, engagé à la demande.',
                'detail' => 'Le membre n’a pas d’obligation de service régulier. Il est appelé pour une opération ou une période définie, et compte dans l’effectif opérationnel pendant ce renfort.',
                'mission' => 'Oui, à la demande',
                'service' => 'Sur convocation',
                'strength' => 'Pendant le renfort',
                'tone' => 'warn',
                'priority' => 65,
            ],
        ];
    }

    /**
     * @return array{slug: string, label: string, summary: string, detail: string, mission: string, service: string, strength: string, tone: string, priority: int}|null
     */
    public static function find(?string $slug): ?array
    {
        foreach (self::all() as $row) {
            if ($row['slug'] === $slug) {
                return $row;
            }
        }

        return null;
    }

    /**
     * Retrouve une position à partir du libellé affiché (nom du rôle en base).
     *
     * @return array{slug: string, label: string, summary: string, detail: string, mission: string, service: string, strength: string, tone: string, priority: int}|null
     */
    public static function findByLabel(string $label): ?array
    {
        $needle = mb_strtolower(trim($label));
        if ($needle === '') {
            return null;
        }
        foreach (self::all() as $row) {
            if (mb_strtolower($row['label']) === $needle) {
                return $row;
            }
        }

        return null;
    }

    public static function label(?string $slug): string
    {
        return self::find($slug)['label'] ?? '';
    }

    public static function summary(?string $slug): string
    {
        return self::find($slug)['summary'] ?? '';
    }

    public static function isKnown(string $slug): bool
    {
        return self::find($slug) !== null;
    }

    /**
     * « Dans l’exercice de ses fonctions » regroupe deux situations.
     *
     * @return list<array{key: string, label: string, summary: string, examples: string}>
     */
    public static function dutySituations(): array
    {
        return [
            [
                'key' => 'mission',
                'label' => 'En mission',
                'summary' => 'Le membre est engagé sur une mission opérationnelle.',
                'examples' => 'Opération de jeu, intervention, patrouille, reconnaissance, recherche du renseignement, garde et surveillance, escorte, QRF, maintien de l’ordre, appui et soutien opérationnel, OPEX ou OPINT jouée.',
            ],
            [
                'key' => 'service',
                'label' => 'En service, hors mission',
                'summary' => 'Le membre est en activité pour l’unité, en dehors d’une mission opérationnelle.',
                'examples' => 'Entraînement, instruction, stage, cours de l’Academy, briefing et débriefing, permanence ou astreinte, liaison, réunion d’état-major, travail à distance sur le site.',
            ],
        ];
    }
}
