<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Règles automatiques « Cerbère-Tri » fournies : activables d'un clic par les gestionnaires, jamais actives d'office
 * (sauf la résolution automatique du moteur, qui est le comportement normal du passage nocturne et que la communauté peut couper).
 *
 * Une règle de tri se lit : SI (file, type, mot du titre, ancienneté, gravité, portée) ALORS (action). Les actions ne détruisent rien :
 * acquitter, mettre en veille, majorer la priorité, assigner ; chacune est journalisée et annulable.
 */
final class SseTriagePresets
{
    public const ACTIONS = [
        'acknowledge' => 'Acquitter',
        'snooze' => 'Mettre en veille',
        'priority' => 'Majorer la priorité',
        'assign' => 'Assigner',
    ];

    public const SEVERITIES = ['info' => 'Information', 'medium' => 'Moyenne', 'high' => 'Haute', 'critical' => 'Critique'];

    public const SCOPES = ['all' => 'Tous les sujets', 'cover' => 'Identités de couverture'];

    /** Clé du modèle gérée par le moteur analytique (SseAnalyticalEngineService), pas par le tri. */
    public const ENGINE_AUTO_RESOLVE = 'auto_resolve';

    /**
     * @return array<string, array{name: string, hint: string, engine: bool, rule: array<string, mixed>}>
     */
    public static function all(): array
    {
        return [
            'old_completeness' => [
                'name' => 'Acquitter automatiquement les complétudes faibles de plus de 30 jours',
                'hint' => 'Une complétude faible qui dure depuis un mois est connue : elle quitte la liste, le dossier reste ouvert.',
                'engine' => false,
                'rule' => ['source_key' => 'anomalies', 'type_key' => 'signal:completeness', 'keyword' => '', 'min_age_days' => 30, 'min_severity' => null, 'max_severity' => null, 'scope' => 'all', 'action' => 'acknowledge', 'action_value' => 0],
            ],
            'cover_photo' => [
                'name' => 'Mettre en veille les lacunes de photographie pour les identités de couverture',
                'hint' => 'Une identité de couverture n’a pas à être photographiée sur le terrain : 30 jours de veille.',
                'engine' => false,
                'rule' => ['source_key' => 'anomalies', 'type_key' => 'signal:gap_auto', 'keyword' => 'Photographie', 'min_age_days' => 0, 'min_severity' => null, 'max_severity' => null, 'scope' => 'cover', 'action' => 'snooze', 'action_value' => 30],
            ],
            'min_severity' => [
                'name' => 'Ne remonter que les anomalies de gravité moyenne ou plus',
                'hint' => 'Les anomalies d’information sont acquittées d’office ; elles restent consultables dans « Acquittés ».',
                'engine' => false,
                'rule' => ['source_key' => 'anomalies', 'type_key' => '', 'keyword' => '', 'min_age_days' => 0, 'min_severity' => null, 'max_severity' => 'info', 'scope' => 'all', 'action' => 'acknowledge', 'action_value' => 0],
            ],
            self::ENGINE_AUTO_RESOLVE => [
                'name' => 'Résoudre automatiquement quand la donnée existe',
                'hint' => 'Au passage de la nuit, un signal dont la condition a disparu (photo ajoutée, biométrie relevée, site rattaché, complétude remontée) est clos par Cerbère-Détection. Active par défaut.',
                'engine' => true,
                'rule' => ['source_key' => '', 'type_key' => '', 'keyword' => '', 'min_age_days' => 0, 'min_severity' => null, 'max_severity' => null, 'scope' => 'all', 'action' => 'acknowledge', 'action_value' => 0],
            ],
        ];
    }

    /** Rang d'une gravité (info 0, moyenne 1, haute 2, critique 3). */
    public static function severityRank(string $severity): int
    {
        return match ($severity) {
            'critical' => 3,
            'high' => 2,
            'medium' => 1,
            default => 0,
        };
    }
}
