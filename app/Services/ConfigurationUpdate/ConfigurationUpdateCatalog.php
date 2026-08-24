<?php

declare(strict_types=1);

namespace App\Services\ConfigurationUpdate;

/**
 * Catalogue central : une nouvelle évolution = une entrée ici.
 * La migration seed la table system_configuration_updates ; le catalogue porte l’éligibilité.
 */
final class ConfigurationUpdateCatalog
{
    public function __construct(private ConfigurationUpdateProbes $probes) {}

    /**
     * @return list<ConfigurationUpdateDefinition>
     */
    public function definitions(): array
    {
        $p = $this->probes;

        return [
            new ConfigurationUpdateDefinition(
                code: 'MILITARY_AFFILIATION_V1',
                title: 'Rattachement militaire',
                description: 'Indiquez si votre communauté représente une unité réelle du référentiel (pays, service, commandement, régiment, commando…) ou un cadre fictif. La recherche couvre aussi les alias (ex. Hubert, 1RPIMA).',
                level: ConfigurationUpdateDefinition::LEVEL_RECOMMENDED,
                configurePath: 'back-office/organisation/parametres#affiliation',
                estimateMinutes: 3,
                dismissible: true,
                blocking: false,
                dependsOn: [],
                sortOrder: 10,
                isApplicable: static fn (int $tenantId): bool => true,
                isSatisfied: fn (int $tenantId): bool => $p->hasMilitaryAffiliation($tenantId),
            ),
            new ConfigurationUpdateDefinition(
                code: 'TIMEZONE_V1',
                title: 'Fuseau horaire',
                description: 'Indiquez le fuseau utilisé pour les événements, présences et communications de votre organisation.',
                level: ConfigurationUpdateDefinition::LEVEL_RECOMMENDED,
                configurePath: 'back-office/organisation/parametres#timezone',
                estimateMinutes: 1,
                dismissible: true,
                blocking: false,
                dependsOn: [],
                sortOrder: 20,
                isApplicable: static fn (int $tenantId): bool => true,
                isSatisfied: fn (int $tenantId): bool => $p->hasTimezone($tenantId),
            ),
            new ConfigurationUpdateDefinition(
                code: 'GRADE_SYSTEM_V1',
                title: 'Référentiel de grades',
                description: 'Choisissez le système de grades utilisé par vos effectifs (français, américain, ou personnalisé).',
                level: ConfigurationUpdateDefinition::LEVEL_RECOMMENDED,
                configurePath: 'back-office/referentiels/grades',
                estimateMinutes: 2,
                dismissible: true,
                blocking: false,
                dependsOn: [],
                sortOrder: 30,
                isApplicable: static fn (int $tenantId): bool => true,
                isSatisfied: fn (int $tenantId): bool => $p->hasGradeSystem($tenantId),
            ),
            new ConfigurationUpdateDefinition(
                code: 'ORGANIZATION_STRUCTURE_V1',
                title: 'Structure organisationnelle',
                description: 'Organisez vos effectifs en unités (compagnies, sections, groupes, équipes).',
                level: ConfigurationUpdateDefinition::LEVEL_RECOMMENDED,
                configurePath: 'back-office/organisation/structure',
                estimateMinutes: 5,
                dismissible: true,
                blocking: false,
                dependsOn: [],
                sortOrder: 40,
                isApplicable: static fn (int $tenantId): bool => true,
                isSatisfied: fn (int $tenantId): bool => $p->hasRootUnit($tenantId),
            ),
            new ConfigurationUpdateDefinition(
                code: 'MISSION_PLANNING_V1',
                title: 'Planification de mission',
                description: 'Préparez l’organisation de combat avant la session (postes, affectations, documents d’ordre), puis suivez l’exécution une fois les joueurs connectés.',
                level: ConfigurationUpdateDefinition::LEVEL_INFORMATIVE,
                configurePath: 'back-office/planification',
                estimateMinutes: 8,
                dismissible: true,
                blocking: false,
                dependsOn: [],
                sortOrder: 45,
                isApplicable: fn (int $tenantId): bool => $p->operationsPlanningApplicable($tenantId),
                isSatisfied: fn (int $tenantId): bool => $p->hasMissionPlan($tenantId),
            ),
            new ConfigurationUpdateDefinition(
                code: 'PUBLIC_PROFILE_V1',
                title: 'Vitrine publique',
                description: 'Complétez la présentation publique de votre organisation (doctrine, spécialités, accroche).',
                level: ConfigurationUpdateDefinition::LEVEL_INFORMATIVE,
                configurePath: 'back-office/community/presentation',
                estimateMinutes: 5,
                dismissible: true,
                blocking: false,
                dependsOn: [],
                sortOrder: 50,
                isApplicable: static fn (int $tenantId): bool => true,
                isSatisfied: fn (int $tenantId): bool => $p->hasPublicProfileBasics($tenantId),
            ),
            new ConfigurationUpdateDefinition(
                code: 'ENLISTMENT_CUSTOM_QUESTIONS_V1',
                title: 'Questions et refus automatiques du dossier candidature',
                description: 'Ajoutez des listes déroulantes au formulaire d’enrôlement et, si besoin, des conditions de refus automatique selon les réponses.',
                level: ConfigurationUpdateDefinition::LEVEL_INFORMATIVE,
                configurePath: 'back-office/community/presentation#pack-milsim-editor',
                estimateMinutes: 8,
                dismissible: true,
                blocking: false,
                dependsOn: [],
                sortOrder: 55,
                isApplicable: fn (int $tenantId): bool => $p->isMilsimRegistrationMode($tenantId),
                isSatisfied: fn (int $tenantId): bool => $p->hasReviewedEnlistmentCustomQuestions($tenantId),
            ),
            new ConfigurationUpdateDefinition(
                code: 'ATAK_CONFIGURATION_V1',
                title: 'Configuration ATAK / Overwatch',
                description: 'Paramétrez la liaison opérationnelle ATAK (modules, accès, options) pour votre communauté.',
                level: ConfigurationUpdateDefinition::LEVEL_RECOMMENDED,
                configurePath: 'admin/atak-config',
                estimateMinutes: 4,
                dismissible: true,
                blocking: false,
                dependsOn: [],
                sortOrder: 60,
                isApplicable: fn (int $tenantId): bool => $p->atakApplicable($tenantId),
                isSatisfied: fn (int $tenantId): bool => $p->hasAtakConfig($tenantId),
            ),
            new ConfigurationUpdateDefinition(
                code: 'SSE_PERSONS_V1',
                title: 'Renseignement interpersonnel (SSE)',
                description: 'Activez le module de renseignement interpersonnel pour enregistrer des personnes sur le terrain (identité, photo du visage) et les consulter au poste de commandement.',
                level: ConfigurationUpdateDefinition::LEVEL_INFORMATIVE,
                configurePath: 'admin/atak-config#bridge-modules',
                estimateMinutes: 3,
                dismissible: true,
                blocking: false,
                dependsOn: [],
                sortOrder: 65,
                isApplicable: fn (int $tenantId): bool => $p->ssePersonsApplicable($tenantId),
                isSatisfied: fn (int $tenantId): bool => $p->hasSsePersonsConfig($tenantId),
            ),
            new ConfigurationUpdateDefinition(
                code: 'SSE_PORTAL_V1',
                title: 'Portail de renseignement classifié',
                description: 'Le commandement peut délivrer des codes d’accès temporaires au portail de renseignement interpersonnel (dossiers d’affaire, croisements, export). Vérifiez les rôles et créez un premier code si besoin.',
                level: ConfigurationUpdateDefinition::LEVEL_RECOMMENDED,
                configurePath: 'back-office/renseignement/codes',
                estimateMinutes: 5,
                dismissible: true,
                blocking: false,
                dependsOn: [],
                sortOrder: 66,
                isApplicable: fn (int $tenantId): bool => $p->ssePortalApplicable($tenantId),
                isSatisfied: fn (int $tenantId): bool => $p->hasSsePortalConfig($tenantId),
            ),
            new ConfigurationUpdateDefinition(
                code: 'SSE_DIGITAL_LAB_V1',
                title: 'Laboratoire numérique (exploitation des supports)',
                description: 'Le portail SSE inclut désormais l’exploitation numérique : enregistrement des supports saisis, acquisitions simulées, artefacts et propositions de rapprochement. Ouvrez le laboratoire pour prendre connaissance du module.',
                level: ConfigurationUpdateDefinition::LEVEL_INFORMATIVE,
                configurePath: 'atak/sse/exploitation-numerique',
                estimateMinutes: 5,
                dismissible: true,
                blocking: false,
                dependsOn: [],
                sortOrder: 67,
                isApplicable: fn (int $tenantId): bool => $p->sseDigitalLabApplicable($tenantId),
                isSatisfied: fn (int $tenantId): bool => $p->hasSseDigitalLabConfig($tenantId),
            ),
            new ConfigurationUpdateDefinition(
                code: 'SSE_DOMEX_QUEUE_V1',
                title: 'File de renseignement numérique à exploiter',
                description: 'Les supports de mission (ordinateur, téléphone, radio…) peuvent désormais déposer des paquets de renseignement dans une file dédiée du laboratoire. Ouvrez « À exploiter » pour rattacher ou écarter ces contenus — un paquet n’est jamais une preuve.',
                level: ConfigurationUpdateDefinition::LEVEL_INFORMATIVE,
                configurePath: 'atak/sse/exploitation-numerique/a-exploiter',
                estimateMinutes: 4,
                dismissible: true,
                blocking: false,
                dependsOn: [],
                sortOrder: 68,
                isApplicable: fn (int $tenantId): bool => $p->sseDigitalLabApplicable($tenantId),
                isSatisfied: fn (int $tenantId): bool => $p->hasSseDomexQueueIntro($tenantId),
            ),
            new ConfigurationUpdateDefinition(
                code: 'SSE_DOMEX_ZEUS_LIVE_V1',
                title: 'Renseignement ajouté en cours de mission',
                description: 'Le chef de mission peut désormais ajouter un renseignement, changer le palier d’accès d’un support, ou poser un point sur la carte pendant la partie. Ces ajouts arrivent dans la file « À exploiter » — un point posé en mission apparaît sur la carte du bureau, pas sur celle des joueurs.',
                level: ConfigurationUpdateDefinition::LEVEL_INFORMATIVE,
                configurePath: 'atak/sse/exploitation-numerique/a-exploiter',
                estimateMinutes: 3,
                dismissible: true,
                blocking: false,
                dependsOn: [],
                sortOrder: 69,
                isApplicable: fn (int $tenantId): bool => $p->sseDigitalLabApplicable($tenantId),
                isSatisfied: fn (int $tenantId): bool => $p->hasSseDomexZeusLiveIntro($tenantId),
            ),
            new ConfigurationUpdateDefinition(
                code: 'ATAK_INTEL_SCRAMBLE_V1',
                title: 'Données chiffrées ATAK',
                description: 'Décidez si le journal radio, les ordres et la carte masquent les informations lorsque le certificat d’un appareil est invalide, ou si un terminal est capturé. À activer dans le mode roleplay ATAK.',
                level: ConfigurationUpdateDefinition::LEVEL_INFORMATIVE,
                configurePath: 'admin/atak/roleplay#intel-scramble',
                estimateMinutes: 3,
                dismissible: true,
                blocking: false,
                dependsOn: [],
                sortOrder: 70,
                isApplicable: fn (int $tenantId): bool => $p->atakApplicable($tenantId),
                isSatisfied: fn (int $tenantId): bool => $p->hasAtakIntelScrambleDecision($tenantId),
            ),
            new ConfigurationUpdateDefinition(
                code: 'ATAK_PHOTO_HUD_V1',
                title: 'Bandeau d’identification des photos terrain',
                description: 'Les photos reçues du terrain peuvent porter un bandeau du type caméra-piéton (unité, indicatif, grille, horodatage). Vérifiez le libellé de votre unité et les informations à afficher.',
                level: ConfigurationUpdateDefinition::LEVEL_RECOMMENDED,
                configurePath: 'admin/atak-config#photo-hud',
                estimateMinutes: 2,
                dismissible: true,
                blocking: false,
                dependsOn: [],
                sortOrder: 72,
                isApplicable: fn (int $tenantId): bool => $p->atakApplicable($tenantId),
                isSatisfied: fn (int $tenantId): bool => $p->hasAtakPhotoHudReviewed($tenantId),
            ),
        ];
    }

    public function find(string $code): ?ConfigurationUpdateDefinition
    {
        foreach ($this->definitions() as $def) {
            if ($def->code === $code) {
                return $def;
            }
        }

        return null;
    }
}
