<?php

declare(strict_types=1);

namespace App\Services\Effectifs\Dossier;

/**
 * Vocabulaire du suivi de dossier : parcours, statuts affichés, responsables, actions et calcul du statut.
 *
 * Forme d'une étape (tableau) : key, source_type, source_id, track, title, description, state (état brut),
 * status (affiché), late, optional, owner_kind, owner_user_id, owner_label, opened_on, due_on, done_at, updated_at,
 * source_label, source_url, note, overridden {due, owner}, original_due_on, editable, actions, locked_reason, pos.
 */
final class DossierCatalog
{
    public const TRACK_RECRUITMENT = 'recruitment';
    public const TRACK_INTEGRATION = 'integration';
    public const TRACK_FORMATION = 'formation';
    public const TRACK_DOCUMENTS = 'documents';
    public const TRACK_CAREER = 'career';
    public const TRACK_RELIABILITY = 'reliability';
    public const TRACK_DEPARTURE = 'departure';

    /** États bruts rendus par les adaptateurs. */
    public const STATE_TODO = 'todo';
    public const STATE_IN_PROGRESS = 'in_progress';
    public const STATE_BLOCKED = 'blocked';
    public const STATE_DONE = 'done';
    public const STATE_SKIPPED = 'skipped';

    /** Statuts affichés (état brut, corrigé par l'échéance). */
    public const STATUS_UPCOMING = 'upcoming';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_BLOCKED = 'blocked';
    public const STATUS_DONE = 'done';
    public const STATUS_SKIPPED = 'skipped';
    public const STATUS_OVERDUE = 'overdue';

    public const OWNER_MEMBER = 'member';
    public const OWNER_INSTRUCTOR = 'instructor';
    public const OWNER_RH = 'rh';
    public const OWNER_REFERENT = 'referent';
    public const OWNER_RECRUITMENT = 'recruitment';
    public const OWNER_OTHER = 'other';
    public const OWNER_USER = 'user';

    public const ACTION_COMPLETE = 'complete';
    public const ACTION_REOPEN = 'reopen';
    public const ACTION_POSTPONE = 'postpone';
    public const ACTION_REASSIGN = 'reassign';
    public const ACTION_NOTE = 'note';
    public const ACTION_SKIP = 'skip';
    public const ACTION_UNSKIP = 'unskip';
    public const ACTION_REMIND = 'remind';
    public const ACTION_REMOVE = 'remove';
    public const ACTION_ADD = 'add';
    /** Vérification de disponibilité enregistrée (fiche ou tableur des effectifs) : historique seulement, pas un geste d'étape. */
    public const ACTION_CHECK = 'check';

    /** @return array<string, string> parcours dans l'ordre d'affichage */
    public static function trackLabels(): array
    {
        return [
            self::TRACK_RECRUITMENT => 'Recrutement',
            self::TRACK_INTEGRATION => 'Intégration',
            self::TRACK_FORMATION => 'Formation et qualifications',
            self::TRACK_DOCUMENTS => 'Documents',
            self::TRACK_CAREER => 'Carrière',
            self::TRACK_RELIABILITY => 'Fiabilisation',
            self::TRACK_DEPARTURE => 'Départ',
        ];
    }

    /** @return array<string, string> */
    public static function trackHints(): array
    {
        return [
            self::TRACK_RECRUITMENT => 'Candidature, décision et bilans de la première période.',
            self::TRACK_INTEGRATION => 'Étapes d’accueil et de prise de poste du parcours d’intégration.',
            self::TRACK_FORMATION => 'Qualifications à obtenir et formations suivies.',
            self::TRACK_DOCUMENTS => 'Documents à lire ou à signer.',
            self::TRACK_CAREER => 'Demandes d’élévation et de mobilité en cours.',
            self::TRACK_RELIABILITY => 'Relecture annuelle de la fiche par le membre.',
            self::TRACK_DEPARTURE => 'Départ enregistré, retrait des accès et archivage.',
        ];
    }

    /** @return array<string, string> */
    public static function statusLabels(): array
    {
        return [
            self::STATUS_UPCOMING => 'À venir',
            self::STATUS_IN_PROGRESS => 'En cours',
            self::STATUS_BLOCKED => 'Bloquée',
            self::STATUS_DONE => 'Terminée',
            self::STATUS_SKIPPED => 'Ignorée',
            self::STATUS_OVERDUE => 'En retard',
        ];
    }

    /** @return array<string, string> */
    public static function ownerLabels(): array
    {
        return [
            self::OWNER_MEMBER => 'Le membre',
            self::OWNER_INSTRUCTOR => 'Un instructeur',
            self::OWNER_RH => 'Un responsable RH',
            self::OWNER_REFERENT => 'Son référent',
            self::OWNER_RECRUITMENT => 'Le recrutement',
            self::OWNER_OTHER => 'Un responsable',
            self::OWNER_USER => 'Un responsable',
        ];
    }

    /** @return list<string> */
    public static function tracks(): array
    {
        return array_keys(self::trackLabels());
    }

    public static function isOpenState(string $state): bool
    {
        return in_array($state, [self::STATE_TODO, self::STATE_IN_PROGRESS, self::STATE_BLOCKED], true);
    }

    /**
     * Statut affiché : un état ouvert dont l'échéance est dépassée est « en retard », sauf s'il est bloqué
     * (le blocage reste l'information utile ; `late` signale le retard à part).
     */
    public static function status(string $state, ?string $dueOn, string $today): string
    {
        if ($state === self::STATE_DONE) {
            return self::STATUS_DONE;
        }
        if ($state === self::STATE_SKIPPED) {
            return self::STATUS_SKIPPED;
        }
        if ($state === self::STATE_BLOCKED) {
            return self::STATUS_BLOCKED;
        }
        if (self::isLate($state, $dueOn, $today)) {
            return self::STATUS_OVERDUE;
        }

        return $state === self::STATE_IN_PROGRESS ? self::STATUS_IN_PROGRESS : self::STATUS_UPCOMING;
    }

    public static function isLate(string $state, ?string $dueOn, string $today): bool
    {
        return self::isOpenState($state) && $dueOn !== null && $dueOn !== '' && substr($dueOn, 0, 10) < $today;
    }

    /** Date au format Y-m-d, ou null si la valeur n'en est pas une. */
    public static function day(mixed $value): ?string
    {
        $text = trim((string) $value);
        if ($text === '' || str_starts_with($text, '0000')) {
            return null;
        }
        $day = substr($text, 0, 10);

        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $day) === 1 && checkdate((int) substr($day, 5, 2), (int) substr($day, 8, 2), (int) substr($day, 0, 4)) ? $day : null;
    }

    /** Date et heure au format Y-m-d H:i:s, ou null. */
    public static function stamp(mixed $value): ?string
    {
        $text = trim((string) $value);
        if ($text === '' || str_starts_with($text, '0000')) {
            return null;
        }
        $ts = strtotime($text);

        return $ts === false ? null : date('Y-m-d H:i:s', $ts);
    }

    public static function owner(string $kind): string
    {
        return array_key_exists($kind, self::ownerLabels()) ? $kind : self::OWNER_OTHER;
    }
}
