<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Vocabulaire et constantes du parcours d’intégration (hors jargon LMS).
 */
final class MemberIntegrationCatalog
{
    public const STATUS_TO_START = 'to_start';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_WAITING_MEMBER = 'waiting_member';
    public const STATUS_WAITING_STAFF = 'waiting_staff';
    public const STATUS_BLOCKED = 'blocked';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_CANCELLED = 'cancelled';

    /** @var list<string> */
    public const STATUSES = [
        self::STATUS_TO_START,
        self::STATUS_IN_PROGRESS,
        self::STATUS_WAITING_MEMBER,
        self::STATUS_WAITING_STAFF,
        self::STATUS_BLOCKED,
        self::STATUS_COMPLETED,
        self::STATUS_CANCELLED,
    ];

    public const STEP_PENDING = 'pending';
    public const STEP_IN_PROGRESS = 'in_progress';
    public const STEP_WAITING_MEMBER = 'waiting_member';
    public const STEP_WAITING_STAFF = 'waiting_staff';
    public const STEP_BLOCKED = 'blocked';
    public const STEP_COMPLETED = 'completed';
    public const STEP_SKIPPED = 'skipped';
    public const STEP_CANCELLED = 'cancelled';

    public const TYPE_TASK = 'task';
    public const TYPE_PERSONNEL_DOSSIER = 'personnel_dossier';
    public const TYPE_APPOINTMENT = 'appointment';
    public const TYPE_EVENT_INVITE = 'event_invite';
    public const TYPE_MATRIX_ASSIGN = 'matrix_assign';
    public const TYPE_STAGE_BILAN = 'stage_bilan';
    public const TYPE_DOCUMENT_READ = 'document_read';
    public const TYPE_LMS_OPTIONAL = 'lms_optional';
    public const TYPE_MANUAL_VALIDATION = 'manual_validation';
    public const TYPE_CUSTOM = 'custom';

    /** @var list<string> */
    public const STEP_TYPES = [
        self::TYPE_TASK,
        self::TYPE_PERSONNEL_DOSSIER,
        self::TYPE_APPOINTMENT,
        self::TYPE_EVENT_INVITE,
        self::TYPE_MATRIX_ASSIGN,
        self::TYPE_STAGE_BILAN,
        self::TYPE_DOCUMENT_READ,
        self::TYPE_LMS_OPTIONAL,
        self::TYPE_MANUAL_VALIDATION,
        self::TYPE_CUSTOM,
    ];

    public const RESP_MEMBER = 'member';
    public const RESP_REFERENT = 'referent';
    public const RESP_HR = 'rh';
    public const RESP_RECRUITMENT = 'recruitment';
    public const RESP_INSTRUCTOR = 'instructor';
    public const RESP_OTHER_ROLE = 'other_role';

    public const VISIBILITY_STAFF = 'staff';
    public const VISIBILITY_MEMBER = 'member';

    public const SOURCE_RECRUITMENT = 'recruitment';
    public const SOURCE_MANUAL = 'manual';
    public const SOURCE_INVITATION = 'invitation';
    public const SOURCE_ROLE_CHANGE = 'role_change';
    public const SOURCE_BACKFILL = 'backfill';

    public const RSVP_PENDING = 'pending';
    public const RSVP_ACCEPTED = 'accepted';
    public const RSVP_TENTATIVE = 'tentative';
    public const RSVP_DECLINED = 'declined';
    public const RSVP_CANCELLED = 'cancelled';

    public const APPT_SCHEDULED = 'scheduled';
    public const APPT_CANCELLED = 'cancelled';
    public const APPT_DONE = 'done';

    /**
     * @return array<string, string>
     */
    public static function statusLabels(): array
    {
        return [
            self::STATUS_TO_START => 'À démarrer',
            self::STATUS_IN_PROGRESS => 'En cours',
            self::STATUS_WAITING_MEMBER => 'En attente du membre',
            self::STATUS_WAITING_STAFF => 'En attente de l’encadrement',
            self::STATUS_BLOCKED => 'Bloqué',
            self::STATUS_COMPLETED => 'Terminé',
            self::STATUS_CANCELLED => 'Annulé',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function stepTypeLabels(): array
    {
        return [
            self::TYPE_TASK => 'Tâche',
            self::TYPE_PERSONNEL_DOSSIER => 'Vérification du dossier personnel',
            self::TYPE_APPOINTMENT => 'Entretien / rendez-vous',
            self::TYPE_EVENT_INVITE => 'Invitation à un événement',
            self::TYPE_MATRIX_ASSIGN => 'Groupe de suivi',
            self::TYPE_STAGE_BILAN => 'Bilan d’étape',
            self::TYPE_DOCUMENT_READ => 'Document à lire',
            self::TYPE_LMS_OPTIONAL => 'Formation facultative',
            self::TYPE_MANUAL_VALIDATION => 'Validation manuelle',
            self::TYPE_CUSTOM => 'Étape personnalisée',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function responsibleLabels(): array
    {
        return [
            self::RESP_MEMBER => 'Membre',
            self::RESP_REFERENT => 'Référent',
            self::RESP_HR => 'RH',
            self::RESP_RECRUITMENT => 'Recrutement',
            self::RESP_INSTRUCTOR => 'Instructeur',
            self::RESP_OTHER_ROLE => 'Autre rôle',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function rsvpLabels(): array
    {
        return [
            self::RSVP_PENDING => 'En attente',
            self::RSVP_ACCEPTED => 'Oui',
            self::RSVP_TENTATIVE => 'Peut-être',
            self::RSVP_DECLINED => 'Non',
            self::RSVP_CANCELLED => 'Annulé',
        ];
    }

    public static function isTerminalStatus(string $status): bool
    {
        return in_array($status, [self::STATUS_COMPLETED, self::STATUS_CANCELLED], true);
    }

    public static function isStepDone(string $status): bool
    {
        return in_array($status, [self::STEP_COMPLETED, self::STEP_SKIPPED], true);
    }
}
