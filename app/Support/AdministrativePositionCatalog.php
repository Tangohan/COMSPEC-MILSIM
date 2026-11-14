<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Positions administratives d’un membre dans sa communauté, et notions « en mission » / « en service ».
 *
 * Source unique des libellés et des explications montrés aux membres (fiche, annuaire, guide).
 * Chaque position est stockée comme un rôle d’affichage (semantic_tier « status ») portant le slug ci-dessous :
 * un membre n’a qu’une position à la fois. Les textes sont en français ; l’anglais vient du dictionnaire du site.
 *
 * Aux quatre positions du site s’ajoutent celles que la communauté crée (table tenant_administrative_positions, slug
 * « status_custom_… »). Les méthodes prennent la communauté en paramètre ; sans paramètre, celle de la session
 * (requêtes web), et seulement les quatre positions du site hors session (ligne de commande, tests).
 */
final class AdministrativePositionCatalog
{
    public const CUSTOM_PREFIX = 'status_custom_';

    public const TONES = ['info', 'ok', 'warn', 'muted', 'bad'];

    /** @var array<int, list<array<string, mixed>>> positions de communauté par communauté (une lecture par requête) */
    private static array $custom = [];

    public const TRAINING = 'status_in_training';
    public const ACTIVE = 'status_active_duty';
    public const INACTIVE = 'status_inactive_duty';
    public const REINFORCEMENT = 'status_reinforcement';

    /**
     * Ordre d’affichage et de priorité (le premier trouvé l’emporte si un membre en porte deux par erreur).
     *
     * @return list<string>
     */
    public static function slugs(?int $tenantId = null): array
    {
        return array_merge(
            [self::ACTIVE, self::REINFORCEMENT, self::INACTIVE, self::TRAINING],
            array_column(self::custom(self::tenant($tenantId)), 'slug')
        );
    }

    /**
     * Ordre du parcours, pour les listes de choix et le guide.
     *
     * @return list<array{slug: string, label: string, summary: string, detail: string, mission: string, service: string, strength: string, tone: string, priority: int}>
     */
    public static function all(?int $tenantId = null): array
    {
        return array_merge(self::builtIn(), self::custom(self::tenant($tenantId)));
    }

    /** Positions de la communauté seulement (sans les quatre du site), archivées exclues. */
    public static function customFor(int $tenantId): array
    {
        return self::custom($tenantId);
    }

    public static function isCustomSlug(?string $slug): bool
    {
        return is_string($slug) && str_starts_with($slug, self::CUSTOM_PREFIX);
    }

    /** Tests et enregistrement : oublie les positions de communauté lues. */
    public static function forget(?int $tenantId = null): void
    {
        if ($tenantId === null) {
            self::$custom = [];
        } else {
            unset(self::$custom[$tenantId]);
        }
    }

    /**
     * Tests : positions de communauté imposées, sans base.
     *
     * @param list<array<string, mixed>> $rows lignes de tenant_administrative_positions
     */
    public static function setCustomForTests(int $tenantId, array $rows): void
    {
        self::$custom[$tenantId] = array_map([self::class, 'fromRow'], $rows);
    }

    /**
     * Les quatre positions du site.
     *
     * @return list<array<string, mixed>>
     */
    public static function builtIn(): array
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
    public static function find(?string $slug, ?int $tenantId = null): ?array
    {
        foreach (self::all($tenantId) as $row) {
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
    public static function findByLabel(string $label, ?int $tenantId = null): ?array
    {
        $needle = mb_strtolower(trim($label));
        if ($needle === '') {
            return null;
        }
        foreach (self::all($tenantId) as $row) {
            if (mb_strtolower($row['label']) === $needle) {
                return $row;
            }
        }

        return null;
    }

    public static function label(?string $slug, ?int $tenantId = null): string
    {
        return self::find($slug, $tenantId)['label'] ?? '';
    }

    public static function summary(?string $slug, ?int $tenantId = null): string
    {
        return self::find($slug, $tenantId)['summary'] ?? '';
    }

    public static function isKnown(string $slug, ?int $tenantId = null): bool
    {
        return self::find($slug, $tenantId) !== null;
    }

    private static function tenant(?int $tenantId): int
    {
        if ($tenantId !== null) {
            return max(0, $tenantId);
        }
        if (PHP_SAPI === 'cli' || !class_exists(\App\Core\Session::class)) {
            return 0;
        }
        try {
            return max(0, (int) \App\Core\Session::get('tenant_id'));
        } catch (\Throwable) {
            return 0;
        }
    }

    /** @return list<array<string, mixed>> */
    private static function custom(int $tenantId): array
    {
        if ($tenantId < 1) {
            return [];
        }
        if (!array_key_exists($tenantId, self::$custom)) {
            $rows = [];
            try {
                $st = \App\Core\Database::getPdo()->prepare(
                    'SELECT * FROM tenant_administrative_positions WHERE tenant_id = ? AND archived_at IS NULL ORDER BY display_order, id'
                );
                $st->execute([$tenantId]);
                $rows = $st->fetchAll(\PDO::FETCH_ASSOC) ?: [];
            } catch (\Throwable) {
                // Table absente (étape tenant_administrative_positions pas encore lancée) : les quatre positions du site.
                $rows = [];
            }
            self::$custom[$tenantId] = array_map([self::class, 'fromRow'], $rows);
        }

        return self::$custom[$tenantId];
    }

    /** @param array<string, mixed> $row */
    private static function fromRow(array $row): array
    {
        $tone = (string) ($row['tone'] ?? 'muted');

        return [
            'slug' => (string) $row['slug'],
            'label' => (string) $row['label'],
            'summary' => (string) ($row['summary'] ?? ''),
            'detail' => (string) ($row['detail'] ?? ''),
            'mission' => (string) ($row['mission'] ?? ''),
            'service' => (string) ($row['service'] ?? ''),
            'strength' => (string) ($row['strength'] ?? ''),
            'tone' => in_array($tone, self::TONES, true) ? $tone : 'muted',
            'priority' => 50,
            'custom' => true,
            'id' => (int) ($row['id'] ?? 0),
        ];
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
