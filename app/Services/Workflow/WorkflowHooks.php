<?php

declare(strict_types=1);

namespace App\Services\Workflow;

use App\Core\Container;

/**
 * Point d'entrée des tables métier vers le moteur de circuits.
 *
 * Les dépôts des demandes (élévations, mobilités) l'appellent après chaque création et chaque changement de statut :
 * tous les chemins qui créent ou traitent une demande passent ainsi par le même circuit, sans modifier chaque écran.
 * Un échec du moteur ne bloque jamais la demande elle-même : il est seulement écrit dans le journal d'erreurs.
 */
final class WorkflowHooks
{
    private static ?WorkflowEngine $engine = null;

    private static bool $resolved = false;

    /** Tests : moteur à utiliser (null = aucun, les appels ne font rien). */
    public static function useEngine(?WorkflowEngine $engine): void
    {
        self::$engine = $engine;
        self::$resolved = true;
    }

    public static function reset(): void
    {
        self::$engine = null;
        self::$resolved = false;
    }

    public static function opened(
        int $tenantId,
        string $subjectType,
        int $subjectId,
        int $memberUserId,
        int $requestedBy,
        string $summary = ''
    ): void {
        $engine = self::engine();
        if ($engine === null) {
            return;
        }
        try {
            $engine->open($tenantId, $subjectType, $subjectId, $memberUserId, $requestedBy, $summary);
        } catch (\Throwable $e) {
            error_log('Moteur de circuits : ouverture impossible (' . $subjectType . ' #' . $subjectId . ') : ' . $e->getMessage());
        }
    }

    public static function statusChanged(
        int $tenantId,
        string $subjectType,
        int $subjectId,
        string $status,
        int $actorUserId,
        string $comment = ''
    ): void {
        $engine = self::engine();
        if ($engine === null) {
            return;
        }
        try {
            $engine->recordStatus($tenantId, $subjectType, $subjectId, $status, $actorUserId, $comment);
        } catch (\Throwable $e) {
            error_log('Moteur de circuits : statut non journalisé (' . $subjectType . ' #' . $subjectId . ') : ' . $e->getMessage());
        }
    }

    /**
     * Avis du chef d'unité pour une liste de demandes (écrans du bureau des effectifs). Vide si le moteur manque.
     *
     * @param list<array<string, mixed>> $rows lignes de la table métier (colonne id)
     * @return array<int, array{state: string, assignee_label: string, opinion: string, opinion_comment: string, opinion_at: string}>
     */
    public static function summaries(int $tenantId, string $subjectType, array $rows): array
    {
        $engine = self::engine();
        if ($engine === null || $rows === []) {
            return [];
        }
        try {
            return $engine->summaries($tenantId, $subjectType, array_map(static fn (array $r): int => (int) ($r['id'] ?? 0), $rows));
        } catch (\Throwable $e) {
            error_log('Moteur de circuits : lecture des avis impossible : ' . $e->getMessage());

            return [];
        }
    }

    private static function engine(): ?WorkflowEngine
    {
        if (!self::$resolved) {
            self::$resolved = true;
            try {
                $engine = Container::get(WorkflowEngine::class);
                self::$engine = $engine instanceof WorkflowEngine ? $engine : null;
            } catch (\Throwable $e) {
                error_log('Moteur de circuits indisponible : ' . $e->getMessage());
                self::$engine = null;
            }
        }

        return self::$engine;
    }
}
