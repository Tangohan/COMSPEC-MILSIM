<?php

declare(strict_types=1);

/**
 * Métadonnées ATHENA pour les pages back-office (fil d'Ariane, en-tête, CSS).
 * Les entrées les plus spécifiques doivent apparaître en premier (match par préfixe).
 */
return [
    'pages' => [
        ['path' => 'jnet/personnel', 'group' => 'Unité', 'kicker' => 'UNITÉ · EXTRANET', 'title' => 'Personnel', 'subtitle' => 'Annuaire et fiches de l’unité.', 'css' => ['jnet_portal.css', 'jnet_bo_embed.css']],
        ['path' => 'jnet/operations', 'group' => 'Unité', 'kicker' => 'UNITÉ · EXTRANET', 'title' => 'Opérations', 'subtitle' => 'Engagements et missions de l’unité.', 'css' => ['jnet_portal.css', 'jnet_bo_embed.css']],
        ['path' => 'jnet/renseignement', 'group' => 'Unité', 'kicker' => 'UNITÉ · EXTRANET', 'title' => 'Renseignement', 'css' => ['jnet_portal.css', 'jnet_bo_embed.css']],
        ['path' => 'jnet/cibles', 'group' => 'Unité', 'kicker' => 'UNITÉ · EXTRANET', 'title' => 'Cibles prioritaires', 'css' => ['jnet_portal.css', 'jnet_bo_embed.css']],
        ['path' => 'jnet/exploitation', 'group' => 'Unité', 'kicker' => 'UNITÉ · EXTRANET', 'title' => 'Exploitation', 'css' => ['jnet_portal.css', 'jnet_bo_embed.css']],
        ['path' => 'jnet/bibliotheque', 'group' => 'Unité', 'kicker' => 'UNITÉ · EXTRANET', 'title' => 'Bibliothèque', 'css' => ['jnet_portal.css', 'jnet_bo_embed.css']],
        ['path' => 'jnet/courrier', 'group' => 'Unité', 'kicker' => 'UNITÉ · EXTRANET', 'title' => 'Messagerie d’unité', 'css' => ['jnet_portal.css', 'jnet_bo_embed.css']],
        ['path' => 'jnet/systeme', 'group' => 'Unité', 'kicker' => 'UNITÉ · EXTRANET', 'title' => 'Système', 'css' => ['jnet_portal.css', 'jnet_bo_embed.css']],
        ['path' => 'jnet/unite', 'group' => 'Unité', 'kicker' => 'UNITÉ · EXTRANET', 'title' => 'Fiche d’unité', 'css' => ['jnet_portal.css', 'jnet_bo_embed.css']],
        ['path' => 'jnet', 'group' => 'Unité', 'kicker' => 'UNITÉ · EXTRANET', 'title' => 'Tableau d’unité', 'subtitle' => 'Situation, personnel, opérations et renseignement de l’unité.', 'css' => ['jnet_portal.css', 'jnet_bo_embed.css']],
        ['path' => 'back-office', 'group' => 'Pilotage', 'kicker' => 'PILOTAGE', 'title' => 'Tableau de bord', 'subtitle' => 'Synthèse de la communauté, indicateurs et accès rapides.'],
        ['path' => 'back-office/centre-operations', 'group' => 'Opérations', 'kicker' => 'OPÉRATIONS', 'title' => 'Centre d’opérations'],
        ['path' => 'back-office/operations-admin', 'group' => 'Opérations', 'kicker' => 'OPÉRATIONS', 'title' => 'Centre d’opérations'],
        ['path' => 'back-office/tableau-operationnel', 'group' => 'Opérations', 'kicker' => 'OPÉRATIONS', 'title' => 'Mur opérationnel', 'subtitle' => 'Administration du mur de permanence et des consignes.'],
        ['path' => 'back-office/analytics/conversion', 'group' => 'Communauté', 'kicker' => 'COMMUNAUTÉ', 'title' => 'Conversion communautés'],
        ['path' => 'back-office/analytics', 'group' => 'Communauté', 'kicker' => 'COMMUNAUTÉ', 'title' => 'Indicateurs d’usage'],
        ['path' => 'back-office/configuration-initiale', 'group' => 'Communauté', 'kicker' => 'PREMIERS PAS', 'title' => 'Configuration initiale', 'subtitle' => 'Votre communauté est en place. Complétez les derniers réglages essentiels : identité, contact, mode d’inscription, modules visibles et rôle d’accueil.', 'css' => ['back-office-initial-setup.css'], 'quick' => [
            ['label' => 'Enregistrer', 'href' => 'back-office/configuration-initiale#initial-setup-actions'],
            ['label' => 'Aide', 'href' => 'back-office/onboarding-recovery'],
            ['label' => 'Paramètres', 'href' => 'back-office/organisation/parametres'],
        ]],
        ['path' => 'back-office/community/presentation', 'group' => 'Communauté', 'kicker' => 'COMMUNAUTÉ', 'title' => 'Page d’accueil publique'],
        ['path' => 'back-office/community/inscription', 'group' => 'Communauté', 'kicker' => 'COMMUNAUTÉ · INSCRIPTION', 'title' => 'Paramètres d’inscription', 'subtitle' => 'Mode de candidature, rôle d’accueil, contact des candidats, créneaux de disponibilité et section Motivation.', 'quick' => [
            ['label' => 'Parcours', 'href' => 'back-office/community/inscription#parcours'],
            ['label' => 'Contact', 'href' => 'back-office/community/inscription#coordonnees'],
            ['label' => 'Dossier', 'href' => 'back-office/community/inscription#dossier'],
            ['label' => 'Identité', 'href' => 'back-office/community'],
        ]],
        ['path' => 'back-office/community', 'group' => 'Communauté', 'kicker' => 'COMMUNAUTÉ · IDENTITÉ', 'title' => 'Paramètres de la communauté', 'subtitle' => 'Identité, représentation d’unité, textes publics et navigation du portail.', 'quick' => [
            ['label' => 'Identité', 'href' => 'back-office/community#identite'],
            ['label' => 'Textes publics', 'href' => 'back-office/community#textes-publics'],
            ['label' => 'Visibilité', 'href' => 'back-office/community#visibilite'],
            ['label' => 'Inscription', 'href' => 'back-office/community/inscription'],
        ]],
        ['path' => 'back-office/organisation/parametres', 'group' => 'Communauté', 'kicker' => 'COMMUNAUTÉ · IDENTITÉ', 'title' => 'Paramètres de la communauté', 'subtitle' => 'Identité, représentation d’unité, textes publics et navigation du portail.', 'quick' => [
            ['label' => 'Identité', 'href' => 'back-office/organisation/parametres#identite'],
            ['label' => 'Textes publics', 'href' => 'back-office/organisation/parametres#textes-publics'],
            ['label' => 'Visibilité', 'href' => 'back-office/organisation/parametres#visibilite'],
            ['label' => 'Inscription', 'href' => 'back-office/community/inscription'],
        ]],
        ['path' => 'back-office/media', 'group' => 'Communauté', 'kicker' => 'COMMUNAUTÉ · MÉDIAS', 'title' => 'Médias de la communauté', 'subtitle' => 'Images et vidéos pour la vitrine publique.', 'css' => ['back-office-media.css'], 'quick' => [
            ['label' => 'Vitrine publique', 'href' => 'back-office/community/presentation'],
        ]],
        ['path' => 'back-office/alerts/create', 'group' => 'Communauté', 'kicker' => 'COMMUNAUTÉ · ANNONCES', 'title' => 'Nouvelle annonce', 'subtitle' => 'Type, couleur, icône et période de diffusion pour le bandeau.', 'css' => ['back-office-alerts.css'], 'quick' => [
            ['label' => 'Liste des annonces', 'href' => 'back-office/alerts'],
        ]],
        ['path' => 'back-office/alerts', 'group' => 'Communauté', 'kicker' => 'COMMUNAUTÉ', 'title' => 'Annonces & alertes', 'subtitle' => 'Bandeaux visibles par les membres connectés de votre communauté.', 'css' => ['back-office-alerts.css'], 'quick' => [
            ['label' => 'Nouvelle annonce', 'href' => 'back-office/alerts/create'],
        ]],
        ['path' => 'back-office/configuration', 'group' => 'Communauté', 'kicker' => 'COMMUNAUTÉ', 'title' => 'Paramètres avancés'],
        ['path' => 'back-office/integrations', 'group' => 'Communauté', 'kicker' => 'COMMUNAUTÉ', 'title' => 'Intégrations externes'],
        ['path' => 'back-office/dashboard-pins', 'group' => 'Communauté', 'kicker' => 'COMMUNAUTÉ', 'title' => 'Raccourcis du portail'],
        ['path' => 'back-office/onboarding-members', 'group' => 'Communauté', 'kicker' => 'COMMUNAUTÉ', 'title' => 'Onboarding membres'],
        ['path' => 'back-office/onboarding-recovery', 'group' => 'Communauté', 'kicker' => 'COMMUNAUTÉ · PREMIERS PAS', 'title' => 'Aide après inscription', 'css' => ['back-office-onboarding-recovery.css']],
        ['path' => 'back-office/users/create', 'group' => 'Personnel', 'kicker' => 'PERSONNEL · MEMBRES', 'title' => 'Nouvel utilisateur', 'css' => ['back-office-users.css']],
        ['path' => 'back-office/users', 'group' => 'Personnel', 'kicker' => 'PERSONNEL · ANNUAIRE', 'title' => 'Membres', 'subtitle' => 'Annuaire complet : identité, affectation, statut et présence.', 'css' => ['back-office-users.css'], 'quick' => [
            ['label' => 'Inviter', 'href' => 'back-office/invitations'],
            ['label' => 'Nouvel utilisateur', 'href' => 'back-office/users/create'],
        ]],
        ['path' => 'back-office/invitations/envoyees', 'group' => 'Personnel', 'kicker' => 'PERSONNEL · INVITATIONS', 'title' => 'Invitations envoyées', 'subtitle' => 'Suivi des liens d’accès envoyés par e-mail.', 'css' => ['invitations-sheet.css'], 'quick' => [
            ['label' => 'Nouvelle invitation', 'href' => 'back-office/invitations'],
            ['label' => 'Membres', 'href' => 'back-office/users'],
        ]],
        ['path' => 'back-office/invitations', 'group' => 'Personnel', 'kicker' => 'PERSONNEL · INVITATIONS', 'title' => 'Nouvelle invitation', 'subtitle' => 'Envoyez un lien d’accès par e-mail et préparez l’arrivée dans l’organigramme.', 'css' => ['invitations-sheet.css'], 'quick' => [
            ['label' => 'Envoyées', 'href' => 'back-office/invitations/envoyees'],
            ['label' => 'Membres', 'href' => 'back-office/users'],
        ]],
        ['path' => 'back-office/organisation-effectifs', 'group' => 'Personnel', 'kicker' => 'PERSONNEL · STRUCTURE', 'title' => 'Structure & effectifs', 'subtitle' => 'Vue d’ensemble non nominative : organigramme, rôles, référentiels et indicateurs utiles aux fiches personnel.', 'css' => ['back-office-effectifs-hub.css'], 'quick' => [
            ['label' => 'Tableur des membres', 'href' => 'back-office/ressources/effectifs'],
            ['label' => 'Centre de pilotage', 'href' => 'back-office'],
        ]],
        ['path' => 'back-office/ressources/effectifs', 'group' => 'Personnel', 'kicker' => 'PERSONNEL · EFFECTIFS', 'title' => 'Bureau effectifs', 'css' => ['back-office-effectifs-hub.css']],
        ['path' => 'back-office/organisation/structure', 'group' => 'Personnel', 'kicker' => 'PERSONNEL', 'title' => 'Structure & recrutement'],
        ['path' => 'back-office/organisation/anciennete', 'group' => 'Personnel', 'kicker' => 'PERSONNEL · EFFECTIFS', 'title' => 'Ancienneté', 'css' => ['back-office-seniority.css']],
        ['path' => 'back-office/groups', 'group' => 'Personnel', 'kicker' => 'PERSONNEL', 'title' => 'Groupes'],
        ['path' => 'back-office/teams', 'group' => 'Personnel', 'kicker' => 'PERSONNEL', 'title' => 'Équipes'],
        ['path' => 'back-office/categories', 'group' => 'Personnel', 'kicker' => 'PERSONNEL', 'title' => 'Catégories forum'],
        ['path' => 'back-office/referentiels/grades', 'group' => 'Personnel', 'kicker' => 'PERSONNEL', 'title' => 'Référentiel des grades'],
        ['path' => 'back-office/positions', 'group' => 'Personnel', 'kicker' => 'PERSONNEL', 'title' => 'Postes & fonctions'],
        ['path' => 'back-office/roles-permissions', 'group' => 'Système', 'kicker' => 'SYSTÈME · ACCÈS', 'title' => 'Rôles & permissions', 'subtitle' => 'Matrice des rôles, périmètres et titulaires.'],
        ['path' => 'back-office/access-management', 'group' => 'Système', 'kicker' => 'SYSTÈME · ACCÈS', 'title' => 'Gestion des accès', 'subtitle' => 'Rôles, droits et règles particulières pour votre communauté.', 'css' => ['back-office-access.css'], 'quick' => [
            ['label' => 'Rôles', 'href' => 'back-office/roles'],
            ['label' => 'Matrice', 'href' => 'back-office/roles-permissions'],
        ]],
        ['path' => 'back-office/roles/presets', 'group' => 'Système', 'kicker' => 'SYSTÈME', 'title' => 'Profils & kits de rôles'],
        ['path' => 'back-office/roles-functions/referentiel', 'group' => 'Personnel', 'kicker' => 'PERSONNEL · CELLULE S1', 'title' => 'Référentiel des fonctions', 'subtitle' => 'Liens doctrinaux entre fonctions de référence — modèle pour les relations entre rôles de votre communauté.', 'css' => ['back-office-doctrine.css'], 'quick' => [
            ['label' => 'Doctrine', 'href' => 'back-office/roles-functions'],
            ['label' => 'Catalogue', 'href' => 'back-office/roles-functions/catalogue'],
        ]],
        ['path' => 'back-office/roles-functions/catalogue', 'group' => 'Personnel', 'kicker' => 'PERSONNEL · CELLULE S1', 'title' => 'Catalogue des fonctions', 'subtitle' => 'Tableur complet des fonctions de référence : noms, familles et descriptions.', 'css' => ['back-office-doctrine.css'], 'quick' => [
            ['label' => 'Doctrine', 'href' => 'back-office/roles-functions'],
            ['label' => 'Référentiel', 'href' => 'back-office/roles-functions/referentiel'],
        ]],
        ['path' => 'back-office/roles-functions', 'group' => 'Personnel', 'kicker' => 'PERSONNEL · CELLULE S1', 'title' => 'Doctrine des fonctions', 'subtitle' => 'Référentiel des fonctions, relations de commandement entre les rôles de la communauté et suivi des postes qui doivent être pourvus.', 'css' => ['back-office-doctrine.css'], 'quick' => [
            ['label' => 'Référentiel', 'href' => 'back-office/roles-functions/referentiel'],
            ['label' => 'Catalogue', 'href' => 'back-office/roles-functions/catalogue'],
            ['label' => 'Obligatoires', 'href' => 'back-office/roles-functions#rf-obligatoires'],
            ['label' => 'Graphe', 'href' => 'back-office/roles-functions#rf-graphe'],
        ]],
        ['path' => 'back-office/roles', 'group' => 'Système', 'kicker' => 'RÔLES · TABLE', 'title' => 'Table des rôles', 'subtitle' => 'Liste structurée par famille opérationnelle.', 'css' => ['back-office-roles.css']],
        ['path' => 'back-office/personnel-job-roles/assignments', 'group' => 'Personnel', 'kicker' => 'PERSONNEL · EMPLOIS', 'title' => 'Attributions métier', 'subtitle' => 'Attribuez les emplois du référentiel à chaque membre de l’effectif.', 'quick' => [
            ['label' => 'Référentiel', 'href' => 'back-office/personnel-job-roles'],
            ['label' => 'Nouvel emploi', 'href' => 'back-office/personnel-job-roles/roles/create'],
        ]],
        ['path' => 'back-office/personnel-job-roles', 'group' => 'Personnel', 'kicker' => 'PERSONNEL · EMPLOIS', 'title' => 'Emplois & missions', 'subtitle' => 'Référentiel des emplois métier, catégories et droits associés.', 'quick' => [
            ['label' => 'Affectations', 'href' => 'back-office/personnel-job-roles/assignments'],
            ['label' => 'Nouvel emploi', 'href' => 'back-office/personnel-job-roles/roles/create'],
        ]],
        ['path' => 'back-office/roleplay/immersion', 'group' => 'Roleplay', 'kicker' => 'ROLEPLAY · IMMERSION', 'title' => 'Réglages d’immersion', 'subtitle' => 'Activation du suivi, étapes d’avancement, filières et indicateur « dossier prêt ».', 'quick' => [
            ['label' => 'Bureau de suivi', 'href' => 'back-office/roleplay-followup'],
            ['label' => 'Échéances', 'href' => 'back-office/roleplay-followup/echeances'],
            ['label' => 'Activation', 'href' => 'back-office/roleplay/immersion#activation-options'],
            ['label' => 'Listes', 'href' => 'back-office/roleplay/immersion#listes'],
        ]],
        ['path' => 'back-office/roleplay-followup/echeances', 'group' => 'Roleplay', 'kicker' => 'ROLEPLAY · ÉCHÉANCES', 'title' => 'Échéances', 'subtitle' => 'Entretiens, visites médicales et rotations de service pour tous les membres.', 'quick' => [
            ['label' => 'Bureau de suivi', 'href' => 'back-office/roleplay-followup'],
            ['label' => 'Réglages d’immersion', 'href' => 'back-office/roleplay/immersion'],
        ]],
        ['path' => 'back-office/roleplay-followup', 'group' => 'Roleplay', 'kicker' => 'ROLEPLAY · SUIVI', 'title' => 'Bureau de suivi', 'subtitle' => 'Tutorat, étapes d’immersion, bilans et échéances des dossiers.', 'quick' => [
            ['label' => 'Échéances', 'href' => 'back-office/roleplay-followup/echeances'],
            ['label' => 'Réglages d’immersion', 'href' => 'back-office/roleplay/immersion'],
        ]],
        ['path' => 'back-office/communications/history', 'group' => 'Communauté', 'kicker' => 'COMMUNAUTÉ · MESSAGES', 'title' => 'Historique des envois'],
        ['path' => 'back-office/communications/templates', 'group' => 'Communauté', 'kicker' => 'COMMUNAUTÉ · MESSAGES', 'title' => 'Modèles d’e-mail'],
        ['path' => 'back-office/communications/groups', 'group' => 'Communauté', 'kicker' => 'COMMUNAUTÉ · MESSAGES', 'title' => 'Groupes de diffusion'],
        ['path' => 'back-office/communications', 'group' => 'Communauté', 'kicker' => 'COMMUNAUTÉ · MESSAGES', 'title' => 'Nouveau message'],
        ['path' => 'back-office/planification', 'group' => 'Opérations', 'kicker' => 'OPÉRATIONS · PLANIFICATION', 'title' => 'Planification de mission', 'subtitle' => 'Organisation de combat, affectations et documents d’ordre avant et pendant la session.', 'css' => ['back-office-mission-planning.css']],
        ['path' => 'back-office/events/insights', 'group' => 'Opérations', 'kicker' => 'OPÉRATIONS · PRÉSENCES', 'title' => 'Insights présence', 'css' => ['back-office-events.css']],
        ['path' => 'back-office/events/reponses-nominatives', 'group' => 'Opérations', 'kicker' => 'OPÉRATIONS · CRÉNEAU', 'title' => 'Réponses nominatives', 'subtitle' => 'Suivi nominatif des réponses pour ce créneau.', 'css' => ['back-office-events.css']],
        ['path' => 'back-office/events', 'group' => 'Opérations', 'kicker' => 'OPÉRATIONS · REGISTRE', 'title' => 'Opérations', 'subtitle' => 'Registre des opérations passées et à venir.', 'css' => ['back-office-events.css']],
        ['path' => 'back-office/atak/comptes-rendus', 'group' => 'Opérations', 'kicker' => 'OPÉRATIONS · RETOURS', 'title' => 'Comptes rendus (AAR)', 'subtitle' => 'Rapports post-opération, points d’amélioration relevés et suivi de leur traitement.', 'css' => ['back-office-aar.css'], 'quick' => [
            ['label' => 'En attente', 'href' => 'back-office/atak/comptes-rendus?status=pending'],
            ['label' => 'Validés', 'href' => 'back-office/atak/comptes-rendus?status=validated'],
            ['label' => 'Actions ouvertes', 'href' => 'back-office/atak/comptes-rendus?open_actions=1'],
        ]],
        ['path' => 'back-office/atak/cycle-mission', 'group' => 'Opérations', 'kicker' => 'OPÉRATIONS · POSTE DE COMMANDEMENT', 'title' => 'Cycle de mission', 'css' => ['back-office-mission-cycle.css']],
        ['path' => 'back-office/atak/briefing-slides', 'group' => 'Ressources', 'kicker' => 'RESSOURCES · TACTIQUE', 'title' => 'Diapositives de briefing', 'subtitle' => 'Images du briefing, ordre de passage et visibilité en jeu pour Arma et les téléphones ATAK.', 'quick' => [
            ['label' => 'Configuration ATAK', 'href' => 'admin/atak-config'],
        ]],
        ['path' => 'back-office/atak/fire-teams', 'group' => 'Personnel', 'kicker' => 'PERSONNEL · TACTIQUE', 'title' => 'Équipes de feu', 'css' => ['back-office-fire-teams.css']],
        ['path' => 'back-office/atak/operateurs', 'group' => 'ATAK', 'kicker' => 'ATAK · SESSIONS', 'title' => 'Sessions & connexions', 'subtitle' => 'Opérateurs actuellement en liaison et historique de présence sur la carte.'],
        ['path' => 'back-office/atak/fiche-operateur', 'group' => 'ATAK', 'kicker' => 'ATAK · FICHE OPÉRATEUR', 'title' => 'Fiche opérateur', 'subtitle' => 'Vue consolidée identité, terminal, certificat et liaison.'],
        ['path' => 'back-office/atak/realisme', 'group' => 'ATAK', 'kicker' => 'ATAK · PARC', 'title' => 'Parc de terminaux', 'subtitle' => 'Inventaire des terminaux appairés et rattachements opérateur.'],
        ['path' => 'back-office/atak/certificats', 'group' => 'ATAK', 'kicker' => 'ATAK · SÉCURITÉ', 'title' => 'Certificats & data packages', 'subtitle' => 'Cycle de vie des certificats client et échéances.'],
        ['path' => 'admin/atak-mod-reports', 'group' => 'Ressources', 'kicker' => 'RESSOURCES · MOD', 'title' => 'Signalements mod', 'css' => ['back-office-atak-beta.css']],
        ['path' => 'admin/atak-mod', 'group' => 'Ressources', 'kicker' => 'RESSOURCES · MOD', 'title' => 'Mod Arma', 'css' => ['back-office-atak-mod.css']],
        ['path' => 'admin/atak-beta', 'group' => 'Ressources', 'kicker' => 'RESSOURCES · MOD', 'title' => 'Inscriptions bêta mod', 'css' => ['back-office-atak-beta.css']],
        ['path' => 'back-office/audit', 'group' => 'Système', 'kicker' => 'SYSTÈME · TRAÇABILITÉ', 'title' => 'Journal d\'audit', 'subtitle' => 'Actions administratives horodatées et conservées 24 mois.', 'flags' => ['hideAuditPageHeader' => true]],
        ['path' => 'back-office/moderation', 'group' => 'Système', 'kicker' => 'SYSTÈME · MODÉRATION', 'title' => 'Restrictions membres'],
        ['path' => 'back-office/security-indicators', 'group' => 'Système', 'kicker' => 'SYSTÈME · SÉCURITÉ', 'title' => 'Blocages & sécurité'],
        ['path' => 'back-office/forum-moderation', 'group' => 'Système', 'kicker' => 'SYSTÈME · FORUM', 'title' => 'Modération forum'],
        ['path' => 'back-office/courrier/traceabilite', 'group' => 'Opérations', 'kicker' => 'OPÉRATIONS', 'title' => 'Traçabilité courrier'],
        ['path' => 'back-office/doctrine/referentiel', 'group' => 'Opérations', 'kicker' => 'OPÉRATIONS · DOCTRINE', 'title' => 'Référentiel doctrinal', 'quick' => [
            ['label' => 'Doctrine & SOP', 'href' => 'back-office/doctrine'],
        ]],
        ['path' => 'back-office/doctrine', 'group' => 'Opérations', 'kicker' => 'OPÉRATIONS', 'title' => 'Doctrine & SOP'],
        ['path' => 'back-office/conformite', 'group' => 'Opérations', 'kicker' => 'OPÉRATIONS', 'title' => 'Export conformité'],
        ['path' => 'back-office/recruitments', 'group' => 'Personnel', 'kicker' => 'PERSONNEL · RECRUTEMENT', 'title' => 'Recrutement'],
        ['path' => 'back-office/ressources/recrutement', 'group' => 'Personnel', 'kicker' => 'PERSONNEL · RECRUTEMENT', 'title' => 'Bureau recrutement'],
        ['path' => 'back-office/recruitments/codes-invitation', 'group' => 'Personnel', 'kicker' => 'PERSONNEL · RECRUTEMENT', 'title' => 'Codes d’invitation prioritaires', 'subtitle' => 'Accélèrent une candidature sur le formulaire d’enrôlement — distincts des invitations par e-mail et du code communauté.'],
        ['path' => 'back-office/recruitment', 'group' => 'Personnel', 'kicker' => 'PERSONNEL · RECRUTEMENT', 'title' => 'Recrutement'],
        ['path' => 'back-office/cooperation', 'group' => 'Ressources', 'kicker' => 'RESSOURCES', 'title' => 'Coopérations inter-unités'],
        ['path' => 'back-office/forum/priorite-mission', 'group' => 'Ressources', 'kicker' => 'RESSOURCES · FORUM', 'title' => 'Publication priorité mission'],
        ['path' => 'back-office/ressources', 'group' => 'Ressources', 'kicker' => 'RESSOURCES', 'title' => 'Ressources'],
        ['path' => 'formation', 'group' => 'Ressources', 'kicker' => 'RESSOURCES · FORMATIONS', 'title' => 'Pilotage des formations'],
        ['path' => 'documents/gestion', 'group' => 'Ressources', 'kicker' => 'RESSOURCES · DOCUMENTS', 'title' => 'Bibliothèque documentaire'],
        ['path' => 'admin/atak-config', 'group' => 'Ressources', 'kicker' => 'RESSOURCES · TACTIQUE', 'title' => 'Cartographie & ATAK'],
        ['path' => 'admin/modpacks', 'group' => 'Ressources', 'kicker' => 'RESSOURCES', 'title' => 'Modpacks'],
        ['path' => 'admin/forum-config', 'group' => 'Ressources', 'kicker' => 'RESSOURCES · FORUM', 'title' => 'Briefing & forum'],
        ['path' => 'admin/content-moderation', 'group' => 'Système', 'kicker' => 'SYSTÈME · MODÉRATION', 'title' => 'Fichiers et pièces jointes'],
        ['path' => 'tableau-operationnel', 'group' => 'Opérations', 'kicker' => 'OPÉRATIONS', 'title' => 'Mur opérationnel'],
    ],

    'dashboard_css' => ['back-office-dashboard.css', 'announce-tiles.css'],

    'skip_page_head_paths' => [
        'back-office/atak/comptes-rendus/',
    ],
];
