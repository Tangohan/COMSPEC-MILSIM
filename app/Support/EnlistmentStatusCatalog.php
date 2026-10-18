<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Source unique des libellés du recrutement : statut, étape du parcours, délai et bilan 30 jours.
 * Utilisé par le bureau recrutement, le tableau de bord (staff et « Mes candidatures ») et la fiche dossier,
 * pour qu'un même dossier affiche partout la même chose.
 *
 * Parcours d'un dossier (4 étapes) :
 *   1. Réception      — déposé, personne ne l'a encore pris ;
 *   2. Instruction    — un recruteur l'a pris (entretien planifié, mise en attente…) ;
 *   3. Intégration    — accepté, compte à créer ou à rattacher, rôles/unité à finaliser ;
 *   4. Intégré        — compte membre rattaché, parcours terminé.
 * Refusé / non admis / annulé : dossier clos, sans étape suivante.
 *
 * Bilan 30 jours : ne concerne que les candidatures acceptées. Il est dû 30 jours après l'acceptation
 * (reviewed_at), pas après le dépôt : un dossier encore en instruction n'a pas de bilan à faire.
 *
 * Tons : amber | emerald | rose | slate | sky | orange | muted (chaque vue les traduit en classes CSS).
 */
final class EnlistmentStatusCatalog
{
    public const RETRO_DELAY_DAYS = 30;

    public const STEPS_TOTAL = 4;

    /** @var list<string> */
    public const CLOSED_STATUSES = ['rejected', 'blocked', 'cancelled'];

    /**
     * @return array{label: string, tone: string}
     */
    public static function status(string $status, bool $autoRejected = false): array
    {
        return match ($status) {
            'submitted' => ['label' => 'À traiter', 'tone' => 'amber'],
            'reviewed' => ['label' => 'Acceptée', 'tone' => 'emerald'],
            'rejected' => ['label' => $autoRejected ? 'Refusée (auto)' : 'Refusée', 'tone' => 'rose'],
            'blocked' => ['label' => 'Non admis', 'tone' => 'slate'],
            'cancelled' => ['label' => 'Annulée', 'tone' => 'muted'],
            default => ['label' => 'À vérifier', 'tone' => 'muted'],
        };
    }

    public static function isClosed(string $status): bool
    {
        return in_array($status, self::CLOSED_STATUSES, true);
    }

    /**
     * Étape du parcours, en tenant compte de l'étape de pipeline (entretien, attente, intégration)
     * et du rattachement du compte membre.
     *
     * @param array<string, mixed> $row
     * @return array{key: string, label: string, hint: string, tone: string, index: int, total: int}
     */
    public static function stage(array $row): array
    {
        $status = (string) ($row['status'] ?? 'submitted');
        $pipeline = trim((string) ($row['pipeline_stage'] ?? ''));
        $hasReviewer = (int) ($row['reviewed_by'] ?? 0) > 0;
        $linked = (int) ($row['submitter_user_id'] ?? 0) > 0;
        $total = self::STEPS_TOTAL;

        if ($status === 'submitted') {
            if ($pipeline === 'interview_scheduled') {
                $at = self::formatDate((string) ($row['discord_interview_at'] ?? ''), 'd/m à H:i');

                return ['key' => 'interview', 'label' => 'Entretien planifié', 'hint' => $at !== '' ? 'Le ' . $at : 'Date à confirmer', 'tone' => 'sky', 'index' => 2, 'total' => $total];
            }
            if ($pipeline === 'on_hold') {
                return ['key' => 'on_hold', 'label' => 'Mis en attente', 'hint' => 'Décision reportée', 'tone' => 'orange', 'index' => 2, 'total' => $total];
            }
            if ($hasReviewer) {
                return ['key' => 'instruction', 'label' => 'Instruction en cours', 'hint' => 'Dossier pris en charge', 'tone' => 'amber', 'index' => 2, 'total' => $total];
            }

            return ['key' => 'reception', 'label' => 'Réception', 'hint' => 'Aucun recruteur désigné', 'tone' => 'amber', 'index' => 1, 'total' => $total];
        }

        if ($status === 'reviewed') {
            if ($pipeline === 'acceptance_onboarding') {
                return ['key' => 'onboarding', 'label' => 'Intégration à finaliser', 'hint' => 'Rôles, unité et compte', 'tone' => 'amber', 'index' => 3, 'total' => $total];
            }
            if (!$linked) {
                return ['key' => 'link_account', 'label' => 'Compte à rattacher', 'hint' => 'Aucun compte membre lié', 'tone' => 'rose', 'index' => 3, 'total' => $total];
            }

            return ['key' => 'integrated', 'label' => 'Intégré', 'hint' => 'Parcours terminé', 'tone' => 'emerald', 'index' => 4, 'total' => $total];
        }

        return match ($status) {
            'rejected' => ['key' => 'closed', 'label' => 'Dossier clos', 'hint' => 'Candidature refusée', 'tone' => 'muted', 'index' => 0, 'total' => $total],
            'blocked' => ['key' => 'closed', 'label' => 'Dossier clos', 'hint' => 'Candidat non admis', 'tone' => 'muted', 'index' => 0, 'total' => $total],
            'cancelled' => ['key' => 'closed', 'label' => 'Dossier clos', 'hint' => 'Candidature annulée', 'tone' => 'muted', 'index' => 0, 'total' => $total],
            default => ['key' => 'unknown', 'label' => '—', 'hint' => '', 'tone' => 'muted', 'index' => 0, 'total' => $total],
        };
    }

    /**
     * Délai de traitement.
     *  - Dossier à traiter : temps écoulé depuis la dernière action, comparé au seuil d'alerte de la communauté.
     *  - Dossier tranché : durée totale de traitement (dépôt → décision).
     *
     * @param array<string, mixed> $row
     * @return array{key: string, label: string, tone: string, meta: string}
     */
    public static function delay(array $row, int $slaHours, ?int $now = null): array
    {
        $now ??= time();
        $slaHours = max(1, $slaHours);
        $status = (string) ($row['status'] ?? '');

        if ($status === 'submitted') {
            $ageHours = isset($row['submitted_age_hours']) && $row['submitted_age_hours'] !== null
                ? (int) $row['submitted_age_hours']
                : self::hoursSince((string) (($row['updated_at'] ?? '') !== '' ? $row['updated_at'] : ($row['created_at'] ?? '')), $now);
            if ($ageHours === null) {
                return ['key' => 'unknown', 'label' => '—', 'tone' => 'muted', 'meta' => ''];
            }
            if ($ageHours > $slaHours) {
                return ['key' => 'late', 'label' => 'En retard', 'tone' => 'rose', 'meta' => 'Sans action depuis ' . self::formatHours($ageHours) . ' (seuil ' . $slaHours . ' h)'];
            }
            $watchFrom = max(0, $slaHours - max(1, (int) ceil($slaHours / 4)));
            $left = self::formatHours(max(0, $slaHours - $ageHours));
            if ($ageHours >= $watchFrom) {
                return ['key' => 'watch', 'label' => 'À surveiller', 'tone' => 'amber', 'meta' => 'Reste ' . $left . ' avant alerte'];
            }

            return ['key' => 'ok', 'label' => 'Dans les délais', 'tone' => 'emerald', 'meta' => 'Reste ' . $left . ' avant alerte'];
        }

        $created = strtotime((string) ($row['created_at'] ?? ''));
        $decided = strtotime((string) ($row['reviewed_at'] ?? ''));
        if ($created === false || $decided === false || $decided < $created) {
            return ['key' => 'done', 'label' => 'Tranché', 'tone' => 'muted', 'meta' => ''];
        }
        $hours = (int) floor(($decided - $created) / 3600);

        return [
            'key' => 'done',
            'label' => $hours < 1 ? 'Traité en moins d’1 h' : 'Traité en ' . self::formatHours($hours),
            'tone' => $hours <= $slaHours ? 'emerald' : 'muted',
            'meta' => 'Décision le ' . date('d/m/Y', $decided),
        ];
    }

    /**
     * Date de départ du bilan 30 jours : la date d'acceptation. Null si le dossier n'est pas accepté.
     *
     * @param array<string, mixed> $row
     */
    public static function retroAnchor(array $row): ?string
    {
        if ((string) ($row['status'] ?? '') !== 'reviewed') {
            return null;
        }
        $at = trim((string) ($row['reviewed_at'] ?? ''));
        if ($at === '') {
            // Acceptation antérieure au suivi de la date de décision : on retombe sur le dépôt.
            $at = trim((string) ($row['created_at'] ?? ''));
        }

        return $at !== '' ? $at : null;
    }

    /**
     * Jours calendaires écoulés depuis une date SQL (aligné sur TIMESTAMPDIFF(DAY, …) côté requêtes).
     */
    public static function daysSince(?string $sqlDate, ?int $now = null): ?int
    {
        $ts = $sqlDate !== null ? strtotime(trim($sqlDate)) : false;
        if ($ts === false || $ts <= 0) {
            return null;
        }
        $now ??= time();
        try {
            $tz = new \DateTimeZone(date_default_timezone_get());
            $start = (new \DateTimeImmutable('@' . $ts))->setTimezone($tz)->setTime(0, 0, 0);
            $end = (new \DateTimeImmutable('@' . $now))->setTimezone($tz)->setTime(0, 0, 0);

            return $end < $start ? 0 : (int) $start->diff($end)->days;
        } catch (\Throwable) {
            return max(0, (int) floor(($now - $ts) / 86400));
        }
    }

    /**
     * État du bilan d'équipe 30 jours.
     *
     * @param array<string, mixed> $row
     * @return array{key: string, label: string, tone: string, meta: string, days_since_acceptance: ?int, actionable: bool}
     */
    public static function retro(array $row, ?string $doneAt, bool $tableReady, ?int $now = null): array
    {
        $status = (string) ($row['status'] ?? '');
        $doneAt = $doneAt !== null ? trim($doneAt) : null;
        $anchor = self::retroAnchor($row);
        $days = self::daysSince($anchor, $now);

        if ($doneAt !== null) {
            $fmt = self::formatDate($doneAt, 'd/m/Y');

            return ['key' => 'done', 'label' => 'Fait', 'tone' => 'emerald', 'meta' => $fmt !== '' ? 'Le ' . $fmt : '', 'days_since_acceptance' => $days, 'actionable' => false];
        }
        if (self::isClosed($status)) {
            return ['key' => 'not_applicable', 'label' => 'Non concerné', 'tone' => 'muted', 'meta' => 'Dossier clos', 'days_since_acceptance' => null, 'actionable' => false];
        }
        if ($status === 'submitted') {
            return ['key' => 'pending_decision', 'label' => 'Après acceptation', 'tone' => 'muted', 'meta' => 'Décision en attente', 'days_since_acceptance' => null, 'actionable' => false];
        }
        if (!$tableReady) {
            return ['key' => 'unavailable', 'label' => 'Indisponible', 'tone' => 'muted', 'meta' => 'Migration à passer', 'days_since_acceptance' => $days, 'actionable' => false];
        }
        if ($days === null) {
            return ['key' => 'unknown', 'label' => '—', 'tone' => 'muted', 'meta' => '', 'days_since_acceptance' => null, 'actionable' => false];
        }
        if ($days >= self::RETRO_DELAY_DAYS) {
            $late = $days - self::RETRO_DELAY_DAYS;

            return [
                'key' => 'due',
                'label' => 'À faire',
                'tone' => $late > 14 ? 'rose' : 'amber',
                'meta' => $late > 0 ? 'Attendu depuis ' . $late . ' j' : 'Attendu aujourd’hui',
                'days_since_acceptance' => $days,
                'actionable' => true,
            ];
        }
        $left = self::RETRO_DELAY_DAYS - $days;

        return ['key' => 'waiting', 'label' => 'Prévu', 'tone' => 'muted', 'meta' => 'Dans ' . $left . ' j', 'days_since_acceptance' => $days, 'actionable' => false];
    }

    /**
     * Libellé du canal de dépôt.
     */
    public static function channel(string $raw): string
    {
        return match (strtolower(trim($raw))) {
            'guest' => 'Sans compte',
            'account' => 'Compte connecté',
            'preset' => 'Profil enregistré',
            '' => '—',
            default => 'Autre canal',
        };
    }

    public static function formatHours(int $hours): string
    {
        $hours = max(0, $hours);
        if ($hours < 24) {
            return $hours . ' h';
        }
        $days = intdiv($hours, 24);
        $rem = $hours % 24;

        return $rem > 0 && $days < 7 ? $days . ' j ' . $rem . ' h' : $days . ' j';
    }

    private static function hoursSince(string $sqlDate, int $now): ?int
    {
        $ts = strtotime(trim($sqlDate));
        if ($ts === false || $ts <= 0) {
            return null;
        }

        return max(0, (int) floor(($now - $ts) / 3600));
    }

    private static function formatDate(string $sqlDate, string $format): string
    {
        $sqlDate = trim($sqlDate);
        if ($sqlDate === '') {
            return '';
        }
        $ts = strtotime($sqlDate);

        return $ts !== false && $ts > 0 ? date($format, $ts) : '';
    }
}
