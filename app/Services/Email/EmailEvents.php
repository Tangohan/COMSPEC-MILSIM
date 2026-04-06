<?php

declare(strict_types=1);

namespace App\Services\Email;

final class EmailEvents
{
    public const USER_REGISTER_CONFIRMATION = 'USER_REGISTER_CONFIRMATION';
    public const NEW_COMMUNITY_MEMBER = 'NEW_COMMUNITY_MEMBER';
    public const SECURITY_ALERT = 'SECURITY_ALERT';
    public const NEW_DEVICE_LOGIN = 'NEW_DEVICE_LOGIN';
    public const MULTIPLE_LOGIN_ATTEMPTS = 'MULTIPLE_LOGIN_ATTEMPTS';
    public const EMAIL_VERIFICATION = 'EMAIL_VERIFICATION';
    public const COMMUNITY_INVITATION = 'COMMUNITY_INVITATION';
    public const PASSWORD_RESET = 'PASSWORD_RESET';
    /** Compte créé par l’admin communauté : lien pour définir le mot de passe. */
    public const TENANT_USER_SETUP = 'TENANT_USER_SETUP';
    public const COMMUNITY_CONTACT = 'COMMUNITY_CONTACT';
    public const PROFILE_INCOMPLETE_REMINDER = 'PROFILE_INCOMPLETE_REMINDER';
    public const ATTENDANCE_RSVP_CONFIRM = 'ATTENDANCE_RSVP_CONFIRM';
    public const ATTENDANCE_EVENT_CANCELLED = 'ATTENDANCE_EVENT_CANCELLED';
    public const ATTENDANCE_REMINDER = 'ATTENDANCE_REMINDER';
    public const ATTENDANCE_CHECKIN_CONFIRM = 'ATTENDANCE_CHECKIN_CONFIRM';
    /** Candidature (enlistment) soumise — notification staff communauté. */
    public const ENLISTMENT_SUBMITTED_STAFF = 'ENLISTMENT_SUBMITTED_STAFF';
    /** Candidature acceptée — message au candidat (récap + lien espace). */
    public const ENLISTMENT_ACCEPTED_CANDIDATE = 'ENLISTMENT_ACCEPTED_CANDIDATE';
    /** Candidature acceptée — notification RH / recrutement. */
    public const ENLISTMENT_ACCEPTED_STAFF = 'ENLISTMENT_ACCEPTED_STAFF';

    /** Formation assignée par le staff (hors auto-inscription). */
    public const TRAINING_ENROLLMENT_ASSIGNED = 'TRAINING_ENROLLMENT_ASSIGNED';
    /** Parcours formation entièrement validé par l’apprenant. */
    public const TRAINING_COURSE_COMPLETED = 'TRAINING_COURSE_COMPLETED';
    /** Demande d’auto-inscription à valider — notification formateurs. */
    public const TRAINING_ENROLLMENT_PENDING_APPROVAL = 'TRAINING_ENROLLMENT_PENDING_APPROVAL';
    /** Auto-inscription validée — message à l’apprenant. */
    public const TRAINING_SELF_ENROLL_APPROVED = 'TRAINING_SELF_ENROLL_APPROVED';
    /** Auto-inscription refusée — message à l’apprenant. */
    public const TRAINING_SELF_ENROLL_DECLINED = 'TRAINING_SELF_ENROLL_DECLINED';
    /** Apprenant bloqué sur un module — notification aux référents formation. */
    public const TRAINING_MODULE_BLOCKED_STAFF = 'TRAINING_MODULE_BLOCKED_STAFF';
    /** Nouveau créneau (session) sur une formation — apprenants inscrits dont le parcours n’est pas terminé. */
    public const TRAINING_COURSE_SESSION_SCHEDULED_LEARNER = 'TRAINING_COURSE_SESSION_SCHEDULED_LEARNER';

    /** Accusé de réception — signalement transmis (signaleur). */
    public const COMMUNITY_REPORT_RECEIPT = 'COMMUNITY_REPORT_RECEIPT';
    /** Signalement marqué traité — suivi pour le signaleur. */
    public const COMMUNITY_REPORT_HANDLED = 'COMMUNITY_REPORT_HANDLED';
    /** Nouveau signalement — alerte équipe de modération. */
    public const COMMUNITY_REPORT_NEW_STAFF = 'COMMUNITY_REPORT_NEW_STAFF';
    /** Changement de participation (RSVP) — activité créée par le destinataire. */
    public const ATTENDANCE_RSVP_ORGANIZER = 'ATTENDANCE_RSVP_ORGANIZER';

    /** @var list<string> */
    public const EMAIL_EVENTS = [
        self::USER_REGISTER_CONFIRMATION,
        self::NEW_COMMUNITY_MEMBER,
        self::SECURITY_ALERT,
        self::NEW_DEVICE_LOGIN,
        self::MULTIPLE_LOGIN_ATTEMPTS,
        self::EMAIL_VERIFICATION,
        self::COMMUNITY_INVITATION,
        self::PASSWORD_RESET,
        self::TENANT_USER_SETUP,
        self::COMMUNITY_CONTACT,
        self::PROFILE_INCOMPLETE_REMINDER,
        self::ATTENDANCE_RSVP_CONFIRM,
        self::ATTENDANCE_EVENT_CANCELLED,
        self::ATTENDANCE_REMINDER,
        self::ATTENDANCE_CHECKIN_CONFIRM,
        self::ENLISTMENT_SUBMITTED_STAFF,
        self::ENLISTMENT_ACCEPTED_CANDIDATE,
        self::ENLISTMENT_ACCEPTED_STAFF,
        self::TRAINING_ENROLLMENT_ASSIGNED,
        self::TRAINING_COURSE_COMPLETED,
        self::TRAINING_ENROLLMENT_PENDING_APPROVAL,
        self::TRAINING_SELF_ENROLL_APPROVED,
        self::TRAINING_SELF_ENROLL_DECLINED,
        self::TRAINING_MODULE_BLOCKED_STAFF,
        self::TRAINING_COURSE_SESSION_SCHEDULED_LEARNER,
        self::COMMUNITY_REPORT_RECEIPT,
        self::COMMUNITY_REPORT_HANDLED,
        self::COMMUNITY_REPORT_NEW_STAFF,
        self::ATTENDANCE_RSVP_ORGANIZER,
    ];
}
