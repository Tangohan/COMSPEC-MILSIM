<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Clôture automatique du bilan 30 jours (« néant ») : règle pure, sans accès base.
 *
 * Le bilan d'une candidature acceptée est dû 30 jours après l'acceptation (EnlistmentStatusCatalog::RETRO_DELAY_DAYS).
 * Quand il est attendu depuis AUTO_CLOSE_LATE_DAYS jours (le « Attendu depuis N j » du bureau recrutement) sans
 * qu'aucun bilan d'équipe n'existe, la tâche planifiée « recruitment_retro_auto_close » en enregistre un « néant » :
 * sans note, validé par le système Athena. Un instructeur peut ensuite rédiger un vrai bilan, qui le remplace.
 */
final class EnlistmentRetroAutoClose
{
    /** Seuil unique : jours de retard (après l'échéance J+30) à partir desquels le néant est enregistré. */
    public const AUTO_CLOSE_LATE_DAYS = 45;

    /** Nombre maximal de dossiers clos par communauté et par exécution (le reste passe à l'exécution suivante). */
    public const BATCH_PER_TENANT = 25;

    /** Nombre maximal de dossiers clos par exécution, toutes communautés confondues. */
    public const BATCH_TOTAL = 100;

    public const COMMENT = 'Bilan non renseigné dans les délais — clos automatiquement (néant)';

    public const BADGE_LABEL = 'Clos par Athena';

    public const NOTIFICATION_TYPE = 'recruitment_retro_auto_closed';

    /** Ancienneté minimale de l'acceptation, en jours calendaires, pour que le seuil soit atteint. */
    public static function acceptanceAgeDays(): int
    {
        return EnlistmentStatusCatalog::RETRO_DELAY_DAYS + self::AUTO_CLOSE_LATE_DAYS;
    }

    /**
     * Jours de retard sur l'échéance J+30, comme affichés (« Attendu depuis N j »). Null si le dossier n'est pas
     * une candidature acceptée ou n'a pas de date de départ. Jamais négatif.
     *
     * @param array<string, mixed> $row
     */
    public static function lateDays(array $row, ?int $now = null): ?int
    {
        $days = EnlistmentStatusCatalog::daysSince(EnlistmentStatusCatalog::retroAnchor($row), $now);
        if ($days === null) {
            return null;
        }

        return max(0, $days - EnlistmentStatusCatalog::RETRO_DELAY_DAYS);
    }

    /**
     * Le néant doit-il être enregistré maintenant ? Seulement une candidature acceptée, sans bilan d'équipe
     * (humain ou néant), en retard d'au moins AUTO_CLOSE_LATE_DAYS jours.
     *
     * @param array<string, mixed> $row ligne de la table enlistments
     */
    public static function isDue(array $row, bool $hasStaffRetro, ?int $now = null): bool
    {
        if ($hasStaffRetro || (string) ($row['status'] ?? '') !== 'reviewed') {
            return false;
        }
        $late = self::lateDays($row, $now);

        return $late !== null && $late >= self::AUTO_CLOSE_LATE_DAYS;
    }

    /**
     * Ce bilan d'équipe est-il un néant posé par Athena ?
     *
     * @param array<string, mixed>|null $retroRow ligne enlistment_retro_feedbacks
     */
    public static function isAutoClosed(?array $retroRow): bool
    {
        return is_array($retroRow) && trim((string) ($retroRow['auto_closed_at'] ?? '')) !== '';
    }

    /**
     * Un bilan compte pour les statistiques de qualité seulement s'il a été rédigé par un membre de l'équipe.
     *
     * @param array<string, mixed>|null $retroRow
     */
    public static function countsAsWrittenBilan(?array $retroRow): bool
    {
        return $retroRow !== null && !self::isAutoClosed($retroRow);
    }
}
