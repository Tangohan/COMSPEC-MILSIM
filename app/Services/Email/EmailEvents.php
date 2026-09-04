<?php

declare(strict_types=1);

namespace App\Services\Email;

final class EmailEvents
{
    public const USER_REGISTER_CONFIRMATION = 'USER_REGISTER_CONFIRMATION';
    /** Compte public créé — notification à l'exploitation de la plateforme. */
    public const PLATFORM_ACCOUNT_CREATED = 'PLATFORM_ACCOUNT_CREATED';
    /** Adresse du nouveau compte vérifiée — notification à l'exploitation de la plateforme. */
    public const PLATFORM_ACCOUNT_EMAIL_VERIFIED = 'PLATFORM_ACCOUNT_EMAIL_VERIFIED';
    public const REGISTER_SECURITY_COMPANION = 'REGISTER_SECURITY_COMPANION';
    public const COMMUNITY_CREATION_CHECKLIST = 'COMMUNITY_CREATION_CHECKLIST';
    public const NEW_COMMUNITY_MEMBER = 'NEW_COMMUNITY_MEMBER';
    /** Membre a quitté la communauté — notification aux responsables. */
    public const MEMBER_LEFT_COMMUNITY_STAFF = 'MEMBER_LEFT_COMMUNITY_STAFF';
    /** Confirmation au membre qui a quitté la communauté. */
    public const MEMBER_LEFT_COMMUNITY_CONFIRMATION = 'MEMBER_LEFT_COMMUNITY_CONFIRMATION';
    public const SECURITY_ALERT = 'SECURITY_ALERT';
    public const NEW_DEVICE_LOGIN = 'NEW_DEVICE_LOGIN';
    /** Identifiant Steam associé au compte depuis Overwatch (e-mail au membre). */
    public const GAME_STEAM_LINKED_MEMBER = 'GAME_STEAM_LINKED_MEMBER';
    /** Identifiant Steam associé ou en conflit — e-mail à l’encadrement. */
    public const GAME_STEAM_LINKED_STAFF = 'GAME_STEAM_LINKED_STAFF';
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
    /** Offre de poste publiée — notification équipe recrutement / RH. */
    public const RECRUITMENT_OPENING_PUBLISHED_STAFF = 'RECRUITMENT_OPENING_PUBLISHED_STAFF';
    /** Message envoyé par le candidat depuis le portail de suivi — alerte recrutement / RH. */
    public const ENLISTMENT_PORTAL_CANDIDATE_REPLY_STAFF = 'ENLISTMENT_PORTAL_CANDIDATE_REPLY_STAFF';
    /** Réponse ou activité recrutement sur le portail de suivi — information au candidat (adresse du dossier). */
    public const ENLISTMENT_PORTAL_UPDATE_CANDIDATE = 'ENLISTMENT_PORTAL_UPDATE_CANDIDATE';
    /** Modération automatique sur le portail candidat / recrutement — alerte aux parties concernées. */
    public const ENLISTMENT_PORTAL_AUTOMOD_ALERT = 'ENLISTMENT_PORTAL_AUTOMOD_ALERT';

    /** Rappel bilan recrutement (équipe) après 30 jours. */
    public const ENLISTMENT_RETRO_STAFF_REMINDER = 'ENLISTMENT_RETRO_STAFF_REMINDER';

    /** Rappel bilan recrutement (candidat) après 30 jours. */
    public const ENLISTMENT_RETRO_CANDIDATE_REMINDER = 'ENLISTMENT_RETRO_CANDIDATE_REMINDER';

    /** Formation assignée par le staff (hors auto-inscription). */
    public const TRAINING_ENROLLMENT_ASSIGNED = 'TRAINING_ENROLLMENT_ASSIGNED';
    /** Parcours formation entièrement validé par l’apprenant. */
    public const TRAINING_COURSE_COMPLETED = 'TRAINING_COURSE_COMPLETED';
    /** Attestation / certificat PDF prêt à télécharger pour l’apprenant. */
    public const TRAINING_CERTIFICATE_AVAILABLE = 'TRAINING_CERTIFICATE_AVAILABLE';
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
    /** Demande d’élévation pour publier une fiche Studio — notification aux personnes habilitées. */
    public const TRAINING_PUBLISH_ELEVATION_REQUEST = 'TRAINING_PUBLISH_ELEVATION_REQUEST';
    /** Demande d’élévation RH (grade / rôle / droits) depuis le bureau effectifs. */
    public const EFFECTIFS_ELEVATION_REQUEST = 'EFFECTIFS_ELEVATION_REQUEST';
    /** Demande de correction RH fiche opérateur — notification organisateurs. */
    public const PERSONNEL_CORRECTION_REQUEST_STAFF = 'PERSONNEL_CORRECTION_REQUEST_STAFF';
    /** Accusé de réception — demande de correction RH (membre). */
    public const PERSONNEL_CORRECTION_REQUEST_MEMBER = 'PERSONNEL_CORRECTION_REQUEST_MEMBER';
    /** Décision sur une demande de correction RH (confirmée / refusée). */
    public const PERSONNEL_CORRECTION_DECISION = 'PERSONNEL_CORRECTION_DECISION';
    /** Résumé hebdomadaire au staff RH : dossiers incomplets, sans unité/rôle, élévations en attente. */
    public const EFFECTIFS_HR_WEEKLY_DIGEST = 'EFFECTIFS_HR_WEEKLY_DIGEST';
    /** Résumé hebdomadaire au staff LMS : brouillons oubliés, documents publiés jamais consultés. */
    public const TRAINING_FORGOTTEN_DOCS_DIGEST = 'TRAINING_FORGOTTEN_DOCS_DIGEST';

    /** Accusé de réception — signalement transmis (signaleur). */
    public const COMMUNITY_REPORT_RECEIPT = 'COMMUNITY_REPORT_RECEIPT';
    /** Signalement marqué traité — suivi pour le signaleur. */
    public const COMMUNITY_REPORT_HANDLED = 'COMMUNITY_REPORT_HANDLED';
    /** Nouveau signalement — alerte équipe de modération. */
    public const COMMUNITY_REPORT_NEW_STAFF = 'COMMUNITY_REPORT_NEW_STAFF';
    /** Dossier rouvert — alerte équipe de modération. */
    public const COMMUNITY_REPORT_REOPENED_STAFF = 'COMMUNITY_REPORT_REOPENED_STAFF';
    /** Dossier rouvert — information au signaleur. */
    public const COMMUNITY_REPORT_REOPENED_REPORTER = 'COMMUNITY_REPORT_REOPENED_REPORTER';
    /** Changement de participation (RSVP) — activité créée par le destinataire. */
    public const ATTENDANCE_RSVP_ORGANIZER = 'ATTENDANCE_RSVP_ORGANIZER';

    /** Code de confirmation — partage de données en coopération inter-unités. */
    public const INTERTEAM_COOPERATION_OTP = 'INTERTEAM_COOPERATION_OTP';
    /** Code OTP — seconde étape de connexion pour comptes sécurité. */
    public const LOGIN_SECURITY_OTP = 'LOGIN_SECURITY_OTP';
    /** Code de test — préférences compte (réception e-mail). */
    public const LOGIN_OTP_MAILBOX_SELF_TEST = 'LOGIN_OTP_MAILBOX_SELF_TEST';

    /** Demande d’accès transmise aux gestionnaires de la communauté (rôles / habilitations). */
    public const TENANT_ACCESS_REQUEST = 'TENANT_ACCESS_REQUEST';

    /** Nouveau message sur un fil de messagerie interne (participant autre que l’expéditeur). */
    public const TENANT_INTERNAL_MESSAGE_THREAD = 'TENANT_INTERNAL_MESSAGE_THREAD';

    /** Annonce liée à une coopération inter-unités (texte issu d’un gabarit configurable). */
    public const COOPERATION_ANNOUNCEMENT = 'COOPERATION_ANNOUNCEMENT';

    /** Diffusion membre — famille structure / ORBAT. */
    public const TENANT_EMAIL_ORBAT = 'TENANT_EMAIL_ORBAT';
    /** Diffusion membre — pilotage opérationnel. */
    public const TENANT_EMAIL_MISSION = 'TENANT_EMAIL_MISSION';
    /** Diffusion membre — activités. */
    public const TENANT_EMAIL_ACTIVITY = 'TENANT_EMAIL_ACTIVITY';
    /** Diffusion membre — message libre. */
    public const TENANT_EMAIL_CUSTOM = 'TENANT_EMAIL_CUSTOM';

    /** Annonce maintenance plateforme — diffusion aux comptes actifs (message libre depuis la règle). */
    public const MAINTENANCE_MEMBER_BROADCAST = 'MAINTENANCE_MEMBER_BROADCAST';

    /** Annonce plateforme diffusée par e-mail aux membres. */
    public const PLATFORM_ALERT_BROADCAST = 'PLATFORM_ALERT_BROADCAST';

    /** Nouvelle communauté créée — alerte à la boîte technique des incidents. */
    public const PLATFORM_NEW_COMMUNITY = 'PLATFORM_NEW_COMMUNITY';


    /** Notification envoyée quand un membre est marqué comme déployé. */
    public const PERSONNEL_DEPLOYMENT_ASSIGNED = 'PERSONNEL_DEPLOYMENT_ASSIGNED';
    /** Notification envoyée à la validation complète du check-up de déploiement. */
    public const PERSONNEL_DEPLOYMENT_CHECKUP_VALIDATED = 'PERSONNEL_DEPLOYMENT_CHECKUP_VALIDATED';

    /** Confirmation au membre — changement de grade, d’affectation ou de fonction. */
    public const PERSONNEL_STRUCTURE_CHANGED = 'PERSONNEL_STRUCTURE_CHANGED';
    /** Alerte RH / effectifs — grade, affectation ou fonction d’un membre modifié. */
    public const PERSONNEL_STRUCTURE_CHANGED_STAFF = 'PERSONNEL_STRUCTURE_CHANGED_STAFF';

    /** Mise à jour du suivi roleplay / tutorat (membre ou tuteur). */
    public const ROLEPLAY_FOLLOWUP_UPDATED = 'ROLEPLAY_FOLLOWUP_UPDATED';
    /** Rappel — bilan roleplay dû ou en retard (tuteur / staff RH). */
    public const ROLEPLAY_BILAN_DUE = 'ROLEPLAY_BILAN_DUE';

    /** Retour questionnaire après (ou pendant) une démonstration NDA. */
    public const DEMO_NDA_FEEDBACK = 'DEMO_NDA_FEEDBACK';

    /** Digest quotidien SSE — rapprochements / signaux / fiches terrain à traiter (analystes). */
    public const SSE_ANALYST_DIGEST = 'SSE_ANALYST_DIGEST';

    public const MEMBER_INTEGRATION_STARTED = 'MEMBER_INTEGRATION_STARTED';
    public const MEMBER_INTEGRATION_TASK = 'MEMBER_INTEGRATION_TASK';
    public const MEMBER_INTEGRATION_INVITE = 'MEMBER_INTEGRATION_INVITE';
    public const MEMBER_INTEGRATION_APPOINTMENT_CHANGED = 'MEMBER_INTEGRATION_APPOINTMENT_CHANGED';
    public const MEMBER_INTEGRATION_APPOINTMENT_CANCELLED = 'MEMBER_INTEGRATION_APPOINTMENT_CANCELLED';
    public const MEMBER_INTEGRATION_REMINDER = 'MEMBER_INTEGRATION_REMINDER';
    public const MEMBER_INTEGRATION_REFERENT_MESSAGE = 'MEMBER_INTEGRATION_REFERENT_MESSAGE';
    public const MEMBER_INTEGRATION_COMPLETED = 'MEMBER_INTEGRATION_COMPLETED';

    /** @var list<string> */
    public const EMAIL_EVENTS = [
        self::USER_REGISTER_CONFIRMATION,
        self::PLATFORM_ACCOUNT_CREATED,
        self::PLATFORM_ACCOUNT_EMAIL_VERIFIED,
        self::REGISTER_SECURITY_COMPANION,
        self::COMMUNITY_CREATION_CHECKLIST,
        self::NEW_COMMUNITY_MEMBER,
        self::MEMBER_LEFT_COMMUNITY_STAFF,
        self::MEMBER_LEFT_COMMUNITY_CONFIRMATION,
        self::SECURITY_ALERT,
        self::NEW_DEVICE_LOGIN,
        self::GAME_STEAM_LINKED_MEMBER,
        self::GAME_STEAM_LINKED_STAFF,
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
        self::RECRUITMENT_OPENING_PUBLISHED_STAFF,
        self::ENLISTMENT_PORTAL_CANDIDATE_REPLY_STAFF,
        self::ENLISTMENT_PORTAL_UPDATE_CANDIDATE,
        self::ENLISTMENT_PORTAL_AUTOMOD_ALERT,
        self::ENLISTMENT_RETRO_STAFF_REMINDER,
        self::ENLISTMENT_RETRO_CANDIDATE_REMINDER,
        self::TRAINING_ENROLLMENT_ASSIGNED,
        self::TRAINING_COURSE_COMPLETED,
        self::TRAINING_CERTIFICATE_AVAILABLE,
        self::TRAINING_ENROLLMENT_PENDING_APPROVAL,
        self::TRAINING_SELF_ENROLL_APPROVED,
        self::TRAINING_SELF_ENROLL_DECLINED,
        self::TRAINING_MODULE_BLOCKED_STAFF,
        self::TRAINING_COURSE_SESSION_SCHEDULED_LEARNER,
        self::TRAINING_PUBLISH_ELEVATION_REQUEST,
        self::EFFECTIFS_ELEVATION_REQUEST,
        self::PERSONNEL_CORRECTION_REQUEST_STAFF,
        self::PERSONNEL_CORRECTION_REQUEST_MEMBER,
        self::PERSONNEL_CORRECTION_DECISION,
        self::EFFECTIFS_HR_WEEKLY_DIGEST,
        self::TRAINING_FORGOTTEN_DOCS_DIGEST,
        self::COMMUNITY_REPORT_RECEIPT,
        self::COMMUNITY_REPORT_HANDLED,
        self::COMMUNITY_REPORT_NEW_STAFF,
        self::COMMUNITY_REPORT_REOPENED_STAFF,
        self::COMMUNITY_REPORT_REOPENED_REPORTER,
        self::ATTENDANCE_RSVP_ORGANIZER,
        self::INTERTEAM_COOPERATION_OTP,
        self::LOGIN_SECURITY_OTP,
        self::LOGIN_OTP_MAILBOX_SELF_TEST,
        self::TENANT_ACCESS_REQUEST,
        self::TENANT_INTERNAL_MESSAGE_THREAD,
        self::COOPERATION_ANNOUNCEMENT,
        self::TENANT_EMAIL_ORBAT,
        self::TENANT_EMAIL_MISSION,
        self::TENANT_EMAIL_ACTIVITY,
        self::TENANT_EMAIL_CUSTOM,
        self::MAINTENANCE_MEMBER_BROADCAST,
        self::PLATFORM_ALERT_BROADCAST,
        self::PLATFORM_NEW_COMMUNITY,
        self::PERSONNEL_DEPLOYMENT_ASSIGNED,
        self::PERSONNEL_DEPLOYMENT_CHECKUP_VALIDATED,
        self::PERSONNEL_STRUCTURE_CHANGED,
        self::PERSONNEL_STRUCTURE_CHANGED_STAFF,
        self::ROLEPLAY_FOLLOWUP_UPDATED,
        self::ROLEPLAY_BILAN_DUE,
        self::DEMO_NDA_FEEDBACK,
        self::SSE_ANALYST_DIGEST,
        self::MEMBER_INTEGRATION_STARTED,
        self::MEMBER_INTEGRATION_TASK,
        self::MEMBER_INTEGRATION_INVITE,
        self::MEMBER_INTEGRATION_APPOINTMENT_CHANGED,
        self::MEMBER_INTEGRATION_APPOINTMENT_CANCELLED,
        self::MEMBER_INTEGRATION_REMINDER,
        self::MEMBER_INTEGRATION_REFERENT_MESSAGE,
        self::MEMBER_INTEGRATION_COMPLETED,
    ];
}
