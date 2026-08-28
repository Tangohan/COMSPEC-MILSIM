<?php

declare(strict_types=1);

namespace App\Support;

use App\Services\Email\EmailEvents;

/**
 * Relais Discord sortants de la communauté (page Intégrations).
 * Les clés e-mail reprennent EmailEvents pour brancher le relais au même passage.
 */
final class DiscordWebhookCatalog
{
    public const KEY_ANNOUNCEMENTS = 'announcements';
    public const KEY_OVERWATCH_PACK = 'overwatch_pack';

    public const MODE_OFF = 'off';
    public const MODE_DEFAULT = 'default';
    public const MODE_CUSTOM = 'custom';

    /**
     * @return list<array{key:string, group:string, label:string, hint:string, default_mode:string}>
     */
    public static function events(): array
    {
        return [
            [
                'key' => self::KEY_ANNOUNCEMENTS,
                'group' => 'Portail',
                'label' => 'Annonces du portail',
                'hint' => 'Bandeaux et messages publiés pour les membres connectés.',
                'default_mode' => self::MODE_DEFAULT,
            ],
            [
                'key' => self::KEY_OVERWATCH_PACK,
                'group' => 'Portail',
                'label' => 'Mises à jour du pack Overwatch',
                'hint' => 'Nouvelle version du pack jeu, avec extrait du journal des changements.',
                'default_mode' => self::MODE_DEFAULT,
            ],
            [
                'key' => EmailEvents::NEW_COMMUNITY_MEMBER,
                'group' => 'Effectifs',
                'label' => 'Nouveau membre',
                'hint' => 'Quand une invitation est acceptée ou qu’un compte rejoint la communauté.',
                'default_mode' => self::MODE_OFF,
            ],
            [
                'key' => EmailEvents::MEMBER_LEFT_COMMUNITY_STAFF,
                'group' => 'Effectifs',
                'label' => 'Départ d’un membre',
                'hint' => 'Quand un membre quitte la communauté.',
                'default_mode' => self::MODE_OFF,
            ],
            [
                'key' => EmailEvents::PERSONNEL_STRUCTURE_CHANGED_STAFF,
                'group' => 'Effectifs',
                'label' => 'Grade, affectation ou fonction',
                'hint' => 'Changement de structure sur un dossier.',
                'default_mode' => self::MODE_OFF,
            ],
            [
                'key' => EmailEvents::EFFECTIFS_ELEVATION_REQUEST,
                'group' => 'Effectifs',
                'label' => 'Demande d’élévation',
                'hint' => 'Demande de grade, de rôle ou de droits depuis le bureau effectifs.',
                'default_mode' => self::MODE_OFF,
            ],
            [
                'key' => EmailEvents::EFFECTIFS_HR_WEEKLY_DIGEST,
                'group' => 'Effectifs',
                'label' => 'Résumé hebdomadaire RH',
                'hint' => 'Point du lundi : dossiers incomplets, sans unité, élévations en attente.',
                'default_mode' => self::MODE_OFF,
            ],
            [
                'key' => EmailEvents::ROLEPLAY_BILAN_DUE,
                'group' => 'Effectifs',
                'label' => 'Bilans roleplay dus',
                'hint' => 'Rappel tuteur / RH lorsqu’un bilan arrive à échéance.',
                'default_mode' => self::MODE_OFF,
            ],
            [
                'key' => EmailEvents::ENLISTMENT_SUBMITTED_STAFF,
                'group' => 'Recrutement',
                'label' => 'Nouvelle candidature',
                'hint' => 'Dossier déposé, à traiter par l’équipe recrutement.',
                'default_mode' => self::MODE_OFF,
            ],
            [
                'key' => EmailEvents::ENLISTMENT_ACCEPTED_STAFF,
                'group' => 'Recrutement',
                'label' => 'Candidature acceptée',
                'hint' => 'Un candidat est intégré.',
                'default_mode' => self::MODE_OFF,
            ],
            [
                'key' => EmailEvents::RECRUITMENT_OPENING_PUBLISHED_STAFF,
                'group' => 'Recrutement',
                'label' => 'Offre de poste publiée',
                'hint' => 'Nouvelle offre visible pour les candidats.',
                'default_mode' => self::MODE_OFF,
            ],
            [
                'key' => EmailEvents::ENLISTMENT_PORTAL_CANDIDATE_REPLY_STAFF,
                'group' => 'Recrutement',
                'label' => 'Message d’un candidat',
                'hint' => 'Réponse déposée sur le portail de suivi.',
                'default_mode' => self::MODE_OFF,
            ],
            [
                'key' => EmailEvents::ENLISTMENT_PORTAL_AUTOMOD_ALERT,
                'group' => 'Recrutement',
                'label' => 'Alerte de modération recrutement',
                'hint' => 'Filtre automatique sur un échange candidat.',
                'default_mode' => self::MODE_OFF,
            ],
            [
                'key' => EmailEvents::ENLISTMENT_RETRO_STAFF_REMINDER,
                'group' => 'Recrutement',
                'label' => 'Bilan recrutement (30 jours)',
                'hint' => 'Rappel d’équipe après un mois.',
                'default_mode' => self::MODE_OFF,
            ],
            [
                'key' => EmailEvents::ATTENDANCE_RSVP_ORGANIZER,
                'group' => 'Opérations',
                'label' => 'Changement de participation',
                'hint' => 'Quelqu’un confirme, décline ou passe en « peut-être ».',
                'default_mode' => self::MODE_OFF,
            ],
            [
                'key' => EmailEvents::ATTENDANCE_EVENT_CANCELLED,
                'group' => 'Opérations',
                'label' => 'Annulation d’événement',
                'hint' => 'Une activité prévue est annulée.',
                'default_mode' => self::MODE_OFF,
            ],
            [
                'key' => EmailEvents::TRAINING_COURSE_COMPLETED,
                'group' => 'Formation',
                'label' => 'Parcours terminé',
                'hint' => 'Un apprenant valide entièrement une formation.',
                'default_mode' => self::MODE_OFF,
            ],
            [
                'key' => EmailEvents::TRAINING_ENROLLMENT_PENDING_APPROVAL,
                'group' => 'Formation',
                'label' => 'Inscription à valider',
                'hint' => 'Demande d’auto-inscription en attente.',
                'default_mode' => self::MODE_OFF,
            ],
            [
                'key' => EmailEvents::TRAINING_MODULE_BLOCKED_STAFF,
                'group' => 'Formation',
                'label' => 'Apprenant bloqué',
                'hint' => 'Un module bloque la progression.',
                'default_mode' => self::MODE_OFF,
            ],
            [
                'key' => EmailEvents::TRAINING_PUBLISH_ELEVATION_REQUEST,
                'group' => 'Formation',
                'label' => 'Publication Studio',
                'hint' => 'Demande d’élévation pour publier une fiche.',
                'default_mode' => self::MODE_OFF,
            ],
            [
                'key' => EmailEvents::TRAINING_FORGOTTEN_DOCS_DIGEST,
                'group' => 'Formation',
                'label' => 'Documents oubliés (LMS)',
                'hint' => 'Résumé hebdomadaire des brouillons et documents jamais ouverts.',
                'default_mode' => self::MODE_OFF,
            ],
            [
                'key' => EmailEvents::COMMUNITY_REPORT_NEW_STAFF,
                'group' => 'Modération',
                'label' => 'Nouveau signalement',
                'hint' => 'Un membre dépose un signalement.',
                'default_mode' => self::MODE_OFF,
            ],
            [
                'key' => EmailEvents::COMMUNITY_REPORT_REOPENED_STAFF,
                'group' => 'Modération',
                'label' => 'Signalement rouvert',
                'hint' => 'Un dossier traité est rouvert.',
                'default_mode' => self::MODE_OFF,
            ],
            [
                'key' => EmailEvents::SSE_ANALYST_DIGEST,
                'group' => 'Renseignement',
                'label' => 'Point quotidien SSE',
                'hint' => 'Rapprochements, signaux et fiches terrain à traiter.',
                'default_mode' => self::MODE_OFF,
            ],
            [
                'key' => EmailEvents::TENANT_ACCESS_REQUEST,
                'group' => 'Accès',
                'label' => 'Demande d’accès',
                'hint' => 'Quelqu’un demande un rôle ou une habilitation.',
                'default_mode' => self::MODE_OFF,
            ],
            [
                'key' => EmailEvents::COOPERATION_ANNOUNCEMENT,
                'group' => 'Accès',
                'label' => 'Annonce de coopération',
                'hint' => 'Message lié à une coopération inter-unités.',
                'default_mode' => self::MODE_OFF,
            ],
        ];
    }

    /**
     * @return array<string, array{key:string, group:string, label:string, hint:string, default_mode:string}>
     */
    public static function byKey(): array
    {
        $out = [];
        foreach (self::events() as $row) {
            $out[$row['key']] = $row;
        }

        return $out;
    }

    public static function defaultMode(string $key): string
    {
        $row = self::byKey()[$key] ?? null;

        return is_array($row) ? (string) $row['default_mode'] : self::MODE_OFF;
    }

    public static function isKnown(string $key): bool
    {
        return isset(self::byKey()[$key]);
    }

    /**
     * @return list<string>
     */
    public static function keys(): array
    {
        return array_column(self::events(), 'key');
    }
}
