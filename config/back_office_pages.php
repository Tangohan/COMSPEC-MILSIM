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
        ['path' => 'jnet', 'group' => 'Unité', 'kicker' => 'UNITÉ · EXTRANET', 'title' => 'Tableau d’unité', 'subtitle' => 'Situation réelle de l’unité : personnel, opérations, renseignement et documents.', 'css' => ['jnet_portal.css', 'jnet_bo_embed.css']],
        ['path' => 'back-office', 'group' => 'Pilotage', 'kicker' => 'PILOTAGE', 'title' => 'Tableau de bord', 'subtitle' => 'Synthèse de la communauté, indicateurs et accès rapides.'],
        ['path' => 'back-office/centre-operations', 'group' => 'Opérations', 'kicker' => 'OPÉRATIONS', 'title' => 'Centre d’opérations', 'subtitle' => 'Les demandes qui attendent une décision, les incidents en cours et l’historique des actions.'],
        ['path' => 'back-office/operations-admin', 'group' => 'Opérations', 'kicker' => 'OPÉRATIONS', 'title' => 'Centre d’opérations', 'subtitle' => 'Les demandes qui attendent une décision, les incidents en cours et l’historique des actions.'],
        ['path' => 'back-office/tableau-operationnel', 'group' => 'Opérations', 'kicker' => 'OPÉRATIONS', 'title' => 'Tableau opérationnel', 'subtitle' => 'Événements, articles, poste ATAK et annonces : tout ce que voient les membres, sur un seul écran.', 'css' => ['operational-board.css'], 'quick' => [
            ['label' => 'Événements', 'href' => 'back-office/events'],
            ['label' => 'Articles', 'href' => 'back-office/articles'],
            ['label' => 'Poste ATAK', 'href' => 'back-office/atak'],
            ['label' => 'Vue membres', 'href' => 'tableau-operationnel'],
        ]],
        ['path' => 'back-office/analytics/conversion', 'group' => 'Communauté', 'kicker' => 'COMMUNAUTÉ', 'title' => 'Conversion des communautés', 'subtitle' => 'Combien de visiteurs deviennent candidats, puis membres.'],
        ['path' => 'back-office/analytics', 'group' => 'Communauté', 'kicker' => 'COMMUNAUTÉ', 'title' => 'Indicateurs d’usage', 'subtitle' => 'Qui se connecte, quand, et quelles pages sont réellement utilisées.'],
        ['path' => 'back-office/configuration-initiale', 'group' => 'Communauté', 'kicker' => 'PREMIERS PAS', 'title' => 'Configuration initiale', 'subtitle' => 'Votre communauté est en ligne. Il reste cinq réglages essentiels : identité, contact, mode d’inscription, modules visibles et rôle attribué aux nouveaux membres.', 'css' => ['back-office-initial-setup.css'], 'quick' => [
            ['label' => 'Enregistrer', 'href' => 'back-office/configuration-initiale#initial-setup-actions'],
            ['label' => 'Besoin d’aide ?', 'href' => 'back-office/onboarding-recovery'],
            ['label' => 'Paramètres', 'href' => 'back-office/organisation/parametres'],
        ]],
        ['path' => 'back-office/community/presentation', 'group' => 'Communauté', 'kicker' => 'COMMUNAUTÉ', 'title' => 'Page d’accueil publique', 'subtitle' => 'Ce que voient les visiteurs avant de s’inscrire : présentation, photos et appel à candidater.'],
        ['path' => 'back-office/community/inscription', 'group' => 'Communauté', 'kicker' => 'COMMUNAUTÉ', 'title' => 'Paramètres de la communauté', 'subtitle' => 'Nom, logo, page publique, inscription et écran d’accueil des membres.', 'quick' => [
            ['label' => 'Identité', 'href' => 'back-office/community?onglet=identite'],
            ['label' => 'Vitrine', 'href' => 'back-office/community?onglet=vitrine'],
            ['label' => 'Inscription', 'href' => 'back-office/community?onglet=inscription'],
            ['label' => 'Accueil', 'href' => 'back-office/community?onglet=accueil'],
        ]],
        ['path' => 'back-office/community', 'group' => 'Communauté', 'kicker' => 'COMMUNAUTÉ', 'title' => 'Paramètres de la communauté', 'subtitle' => 'Nom, logo, page publique, inscription et écran d’accueil des membres.', 'quick' => [
            ['label' => 'Identité', 'href' => 'back-office/community?onglet=identite'],
            ['label' => 'Vitrine', 'href' => 'back-office/community?onglet=vitrine'],
            ['label' => 'Inscription', 'href' => 'back-office/community?onglet=inscription'],
            ['label' => 'Accueil', 'href' => 'back-office/community?onglet=accueil'],
        ]],
        ['path' => 'back-office/organisation/parametres', 'group' => 'Communauté', 'kicker' => 'COMMUNAUTÉ', 'title' => 'Paramètres de la communauté', 'subtitle' => 'Nom, logo, page publique, inscription et écran d’accueil des membres.', 'quick' => [
            ['label' => 'Identité', 'href' => 'back-office/organisation/parametres?onglet=identite'],
            ['label' => 'Vitrine', 'href' => 'back-office/organisation/parametres?onglet=vitrine'],
            ['label' => 'Inscription', 'href' => 'back-office/organisation/parametres?onglet=inscription'],
            ['label' => 'Accueil', 'href' => 'back-office/organisation/parametres?onglet=accueil'],
        ]],
        ['path' => 'back-office/media', 'group' => 'Communauté', 'kicker' => 'COMMUNAUTÉ · MÉDIAS', 'title' => 'Médias de la communauté', 'subtitle' => 'Les photos et vidéos affichées sur votre page publique.', 'css' => ['back-office-media.css'], 'quick' => [
            ['label' => 'Vitrine publique', 'href' => 'back-office/community/presentation'],
        ]],
        ['path' => 'back-office/alerts/create', 'group' => 'Communauté', 'kicker' => 'COMMUNAUTÉ · ANNONCES', 'title' => 'Nouvelle annonce', 'subtitle' => 'Rédigez le message, choisissez son importance et ses dates d’affichage.', 'css' => ['back-office-alerts.css'], 'quick' => [
            ['label' => 'Liste des annonces', 'href' => 'back-office/alerts'],
        ]],
        ['path' => 'back-office/alerts', 'group' => 'Communauté', 'kicker' => 'COMMUNAUTÉ', 'title' => 'Annonces', 'subtitle' => 'Les bandeaux affichés en haut du portail pour tous les membres connectés.', 'css' => ['back-office-alerts.css'], 'quick' => [
            ['label' => 'Nouvelle annonce', 'href' => 'back-office/alerts/create'],
        ]],
        ['path' => 'back-office/articles/create', 'group' => 'Communauté', 'kicker' => 'COMMUNAUTÉ · ARTICLES', 'title' => 'Nouveau mini-article', 'subtitle' => 'Un titre, quelques mots-clés, une image et votre texte. Il reste en ligne jusqu’à ce que vous le retiriez.', 'quick' => [
            ['label' => 'Liste', 'href' => 'back-office/articles'],
            ['label' => 'Voir côté membres', 'href' => 'articles'],
        ]],
        ['path' => 'back-office/articles', 'group' => 'Communauté', 'kicker' => 'COMMUNAUTÉ', 'title' => 'Mini-articles', 'subtitle' => 'De courts textes permanents pour informer les membres : règles, guides, présentations.', 'quick' => [
            ['label' => 'Nouvel article', 'href' => 'back-office/articles/create'],
            ['label' => 'Voir côté membres', 'href' => 'articles'],
        ]],
        ['path' => 'back-office/configuration', 'group' => 'Communauté', 'kicker' => 'COMMUNAUTÉ', 'title' => 'Paramètres avancés', 'subtitle' => 'Réglages techniques de la plateforme. Modifiez-les seulement si vous savez ce qu’ils font.'],
        ['path' => 'back-office/integrations', 'group' => 'Communauté', 'kicker' => 'COMMUNAUTÉ', 'title' => 'Intégrations externes', 'subtitle' => 'Reliez Discord et les autres services : choix des salons, envoi des photos et des transmissions, clés d’accès.'],
        ['path' => 'back-office/dashboard-pins', 'group' => 'Communauté', 'kicker' => 'COMMUNAUTÉ', 'title' => 'Raccourcis du portail', 'subtitle' => 'Les liens épinglés sur le tableau de bord des membres.'],
        ['path' => 'back-office/dashboard-tenues/create', 'group' => 'Communauté', 'kicker' => 'COMMUNAUTÉ · TABLEAU DE BORD', 'title' => 'Mettre une tenue en avant', 'css' => ['dashboard-impact.css'], 'quick' => [
            ['label' => 'Vitrine', 'href' => 'back-office/dashboard-tenues'],
            ['label' => 'Tableau de bord', 'href' => 'dashboard'],
        ]],
        ['path' => 'back-office/dashboard-tenues', 'group' => 'Communauté', 'kicker' => 'COMMUNAUTÉ · TABLEAU DE BORD', 'title' => 'Tenues du tableau de bord', 'subtitle' => 'Les tenues mises en avant sur le tableau de bord des membres, avec leur visuel et leur fond.', 'css' => ['dashboard-impact.css'], 'quick' => [
            ['label' => 'Ajouter', 'href' => 'back-office/dashboard-tenues/create'],
            ['label' => 'Tableau de bord', 'href' => 'dashboard'],
        ]],
        ['path' => 'back-office/integration-membres/modeles/nouveau', 'group' => 'Communauté', 'kicker' => 'COMMUNAUTÉ · INTÉGRATION', 'title' => 'Nouveau modèle de parcours', 'subtitle' => 'Nom, durée, règle de référent et étapes proposées aux nouvelles arrivées.', 'css' => ['member-integration.css'], 'quick' => [
            ['label' => 'Modèles', 'href' => 'back-office/integration-membres/modeles'],
            ['label' => 'Parcours', 'href' => 'back-office/integration-membres'],
        ]],
        ['path' => 'back-office/integration-membres/modeles', 'group' => 'Communauté', 'kicker' => 'COMMUNAUTÉ · INTÉGRATION', 'title' => 'Modèles de parcours', 'subtitle' => 'Un modèle publié s’applique aux nouvelles arrivées. Les suivis déjà commencés conservent leur version.', 'css' => ['member-integration.css'], 'quick' => [
            ['label' => 'Parcours', 'href' => 'back-office/integration-membres'],
            ['label' => 'Reprise', 'href' => 'back-office/integration-membres/reprise'],
        ]],
        ['path' => 'back-office/integration-membres/{id}', 'group' => 'Communauté', 'kicker' => 'COMMUNAUTÉ · INTÉGRATION', 'title' => 'Parcours d’arrivée', 'subtitle' => 'Étapes, dossier personnel, référents et rendez-vous pour ce membre.', 'css' => ['member-integration.css'], 'quick' => [
            ['label' => 'Tous les parcours', 'href' => 'back-office/integration-membres'],
            ['label' => 'Modèles', 'href' => 'back-office/integration-membres/modeles'],
        ]],
        ['path' => 'back-office/integration-membres/reprise', 'group' => 'Communauté', 'kicker' => 'COMMUNAUTÉ · INTÉGRATION', 'title' => 'Reprise des arrivées récentes', 'subtitle' => 'Ouvrez un parcours pour les membres déjà arrivés qui n’en ont pas encore.', 'css' => ['member-integration.css'], 'quick' => [
            ['label' => 'Parcours', 'href' => 'back-office/integration-membres'],
            ['label' => 'Modèles', 'href' => 'back-office/integration-membres/modeles'],
        ]],
        ['path' => 'back-office/integration-membres', 'group' => 'Communauté', 'kicker' => 'COMMUNAUTÉ', 'title' => 'Intégration des nouveaux membres', 'subtitle' => 'Parcours d’arrivée, référents, rendez-vous et dossier personnel.', 'css' => ['member-integration.css'], 'quick' => [
            ['label' => 'Modèles', 'href' => 'back-office/integration-membres/modeles'],
            ['label' => 'Reprise', 'href' => 'back-office/integration-membres/reprise'],
        ]],
        ['path' => 'back-office/onboarding-members', 'group' => 'Communauté', 'kicker' => 'COMMUNAUTÉ', 'title' => 'Intégration des nouveaux membres'],
        ['path' => 'back-office/onboarding-recovery', 'group' => 'Communauté', 'kicker' => 'COMMUNAUTÉ · PREMIERS PAS', 'title' => 'Besoin d’aide pour démarrer ?', 'subtitle' => 'Les étapes pour finir d’installer votre communauté, et qui contacter si vous êtes bloqué.', 'css' => ['back-office-onboarding-recovery.css']],
        ['path' => 'back-office/users/create', 'group' => 'Personnel', 'kicker' => 'PERSONNEL · MEMBRES', 'title' => 'Ajouter un membre', 'subtitle' => 'Créez son compte. Il recevra un e-mail pour choisir son mot de passe.', 'css' => ['back-office-users.css']],
        ['path' => 'back-office/users', 'group' => 'Personnel', 'kicker' => 'PERSONNEL · ANNUAIRE', 'title' => 'Membres', 'subtitle' => 'Tous les membres : identité, affectation, statut et présence.', 'css' => ['back-office-users.css'], 'quick' => [
            ['label' => 'Inviter', 'href' => 'back-office/invitations'],
            ['label' => 'Ajouter un membre', 'href' => 'back-office/users/create'],
        ]],
        ['path' => 'back-office/invitations/envoyees', 'group' => 'Personnel', 'kicker' => 'PERSONNEL · INVITATIONS', 'title' => 'Invitations envoyées', 'subtitle' => 'Les invitations envoyées, et qui les a acceptées.', 'css' => ['invitations-sheet.css'], 'quick' => [
            ['label' => 'Nouvelle invitation', 'href' => 'back-office/invitations'],
            ['label' => 'Membres', 'href' => 'back-office/users'],
        ]],
        ['path' => 'back-office/invitations', 'group' => 'Personnel', 'kicker' => 'PERSONNEL · INVITATIONS', 'title' => 'Nouvelle invitation', 'subtitle' => 'Envoyez un lien par e-mail. Vous pouvez choisir dès maintenant son unité d’arrivée.', 'css' => ['invitations-sheet.css'], 'quick' => [
            ['label' => 'Envoyées', 'href' => 'back-office/invitations/envoyees'],
            ['label' => 'Membres', 'href' => 'back-office/users'],
        ]],
        ['path' => 'back-office/organisation-effectifs', 'group' => 'Personnel', 'kicker' => 'PERSONNEL · STRUCTURE', 'title' => 'Organisation', 'subtitle' => 'Vue d’ensemble sans noms : organigramme, rôles, référentiels et indicateurs utiles aux fiches personnel.', 'css' => ['back-office-effectifs-hub.css'], 'quick' => [
            ['label' => 'Tableur des membres', 'href' => 'back-office/ressources/effectifs'],
            ['label' => 'Tableau de bord', 'href' => 'back-office'],
        ]],
        ['path' => 'back-office/personnel/corrections', 'group' => 'Personnel', 'kicker' => 'PERSONNEL · RH', 'title' => 'Corrections de fiches', 'subtitle' => 'Validez les corrections demandées par les membres, ou corrigez une fiche vous-même.', 'css' => ['back-office-corrections.css', 'personnel-dossier.css'], 'quick' => [
            ['label' => 'Effectifs', 'href' => 'back-office/ressources/effectifs'],
            ['label' => 'Annuaire', 'href' => 'personnel'],
        ]],
        ['path' => 'back-office/ressources/effectifs', 'group' => 'Personnel', 'kicker' => 'PERSONNEL · EFFECTIFS', 'title' => 'Effectifs', 'subtitle' => 'Les fiches de vos membres, leurs affectations, leurs droits et leur parcours, au même endroit.', 'css' => ['effectifs_lms.css', 'back-office-effectifs-workspace.css']],
        ['path' => 'back-office/organisation/catalogue', 'group' => 'Personnel', 'kicker' => 'PERSONNEL · STRUCTURE', 'title' => 'Catalogue de l’organisation', 'subtitle' => 'Administrez l’organigramme, les grades, les fonctions et les rôles, ou copiez un modèle.', 'css' => ['back-office-catalog.css'], 'quick' => [
            ['label' => 'Structure', 'href' => 'back-office/organisation/structure'],
            ['label' => 'Journal', 'href' => 'back-office/organisation/catalogue/historique'],
            ['label' => 'Accès', 'href' => 'back-office/ressources/effectifs/roles'],
            ['label' => 'Emplois', 'href' => 'back-office/ressources/effectifs/fonctions'],
        ]],
        ['path' => 'back-office/organisation/catalogue/historique', 'group' => 'Personnel', 'kicker' => 'PERSONNEL · STRUCTURE', 'title' => 'Journal du catalogue', 'subtitle' => 'Toutes les applications de modèles dans cette communauté.', 'css' => ['back-office-catalog.css'], 'quick' => [
            ['label' => 'Catalogue', 'href' => 'back-office/organisation/catalogue'],
        ]],
        ['path' => 'back-office/organisation/catalogue/modele', 'group' => 'Personnel', 'kicker' => 'PERSONNEL · STRUCTURE', 'title' => 'Modèle d’organisation', 'css' => ['back-office-catalog.css']],
        ['path' => 'back-office/organisation/catalogue/apercu', 'group' => 'Personnel', 'kicker' => 'PERSONNEL · STRUCTURE', 'title' => 'Aperçu du modèle', 'css' => ['back-office-catalog.css']],
        ['path' => 'back-office/organisation/structure', 'group' => 'Personnel', 'kicker' => 'PERSONNEL', 'title' => 'Organigramme', 'subtitle' => 'Unités, sections et postes de votre communauté. Glissez un poste pour le déplacer.'],
        ['path' => 'back-office/organisation/anciennete', 'group' => 'Personnel', 'kicker' => 'PERSONNEL · EFFECTIFS', 'title' => 'Ancienneté', 'subtitle' => 'Comment l’ancienneté est calculée et affichée sur les fiches des membres.', 'css' => ['back-office-seniority.css']],
        ['path' => 'back-office/organisation/progression', 'group' => 'Personnel', 'kicker' => 'PERSONNEL · CARRIÈRE', 'title' => 'Progression & carrière', 'subtitle' => 'Comment un membre progresse : étapes, validations et qualifications requises.'],
        ['path' => 'back-office/organisation/passes', 'group' => 'Personnel', 'kicker' => 'PERSONNEL · PASS', 'title' => 'PASS RH', 'subtitle' => 'Des lots de conditions réutilisables pour ouvrir un poste, accorder une promotion ou noter un membre.', 'quick' => [
            ['label' => 'Nouveau PASS', 'href' => 'back-office/organisation/passes/create'],
            ['label' => 'Avancement', 'href' => 'back-office/rh/avancement'],
            ['label' => 'Offres', 'href' => 'back-office/recruitment/offers'],
        ]],
        ['path' => 'back-office/organisation/indicatifs', 'group' => 'Personnel', 'kicker' => 'PERSONNEL · CARRIÈRE', 'title' => 'Indicatifs radio', 'subtitle' => 'Comment les indicatifs sont attribués : séries, numéros réservés et historique.'],
        ['path' => 'back-office/groups', 'group' => 'Personnel', 'kicker' => 'PERSONNEL', 'title' => 'Groupes', 'subtitle' => 'Regroupez des membres en sous-unités ou en cellules.'],
        ['path' => 'back-office/teams', 'group' => 'Personnel', 'kicker' => 'PERSONNEL', 'title' => 'Équipes', 'subtitle' => 'Des équipes formées pour une mission ou un créneau.'],
        ['path' => 'back-office/categories', 'group' => 'Personnel', 'kicker' => 'PERSONNEL', 'title' => 'Catégories du forum', 'subtitle' => 'Les rubriques du forum et l’ordre dans lequel elles apparaissent.'],
        ['path' => 'back-office/referentiels/grades', 'group' => 'Personnel', 'kicker' => 'PERSONNEL', 'title' => 'Grades', 'subtitle' => 'La liste des grades de votre communauté, dans l’ordre hiérarchique.'],
        ['path' => 'back-office/positions', 'group' => 'Personnel', 'kicker' => 'PERSONNEL', 'title' => 'Postes', 'subtitle' => 'Les postes à pourvoir dans l’organigramme et leurs titulaires.'],
        ['path' => 'back-office/roles-permissions', 'group' => 'Accès', 'kicker' => 'ACCÈS', 'title' => 'Rôles et droits', 'subtitle' => 'Ce que chaque rôle permet de faire, et qui le détient.'],
        ['path' => 'back-office/access-management', 'group' => 'Accès', 'kicker' => 'ACCÈS', 'title' => 'Gestion des accès', 'subtitle' => 'Qui peut faire quoi : rôles, droits et exceptions accordées à certains membres.', 'css' => ['back-office-access.css'], 'quick' => [
            ['label' => 'Rôles', 'href' => 'back-office/roles'],
            ['label' => 'Matrice', 'href' => 'back-office/roles-permissions'],
        ]],
        ['path' => 'back-office/roles/presets', 'group' => 'Accès', 'kicker' => 'ACCÈS', 'title' => 'Profils de rôles', 'subtitle' => 'Des ensembles de droits prêts à l’emploi, à attribuer en un clic.'],
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
        ['path' => 'back-office/roles', 'group' => 'Accès', 'kicker' => 'ACCÈS · RÔLES', 'title' => 'Liste des rôles', 'subtitle' => 'Tous les rôles, classés par famille.', 'css' => ['back-office-roles.css']],
        ['path' => 'back-office/personnel-job-roles/kits', 'group' => 'Personnel', 'kicker' => 'PERSONNEL · ACCÈS', 'title' => 'Kits d’accès', 'subtitle' => 'Des lots de droits simples (lecture, modification, recrutement, paramètres) à cumuler et attribuables.', 'css' => ['back-office-catalog.css'], 'quick' => [
            ['label' => 'Emplois', 'href' => 'back-office/ressources/effectifs/fonctions'],
        ]],
        ['path' => 'back-office/personnel-job-roles/assignments', 'group' => 'Personnel', 'kicker' => 'PERSONNEL · EMPLOIS', 'title' => 'Emplois', 'subtitle' => 'Attribuez un emploi à chaque membre depuis la page Effectifs.', 'quick' => [
            ['label' => 'Emplois', 'href' => 'back-office/ressources/effectifs/fonctions'],
        ]],
        ['path' => 'back-office/personnel-job-roles', 'group' => 'Personnel', 'kicker' => 'PERSONNEL · EMPLOIS', 'title' => 'Emplois', 'subtitle' => 'Le métier d’un membre (tireur, infirmier…). Un emploi ne donne aucun droit dans la plateforme.', 'quick' => [
            ['label' => 'Emplois', 'href' => 'back-office/ressources/effectifs/fonctions'],
        ]],
        ['path' => 'back-office/roleplay/immersion', 'group' => 'Roleplay', 'kicker' => 'ROLEPLAY · ARRIVÉE', 'title' => 'Parcours d’immersion', 'subtitle' => 'Décidez comment votre communauté suit l’arrivée d’un membre : étapes, filière, tuteur et dossier prêt.', 'css' => ['back-office-roleplay-immersion.css'], 'quick' => [
            ['label' => 'Bureau de suivi', 'href' => 'back-office/roleplay-followup'],
            ['label' => 'Échéances', 'href' => 'back-office/roleplay-followup/echeances'],
            ['label' => 'Parcours RH', 'href' => 'back-office/roleplay/regles-phases'],
            ['label' => 'Sessions Arma', 'href' => 'back-office/roleplay/sessions'],
            ['label' => 'Affichage', 'href' => 'back-office/roleplay/immersion#activation-options'],
            ['label' => 'Étapes et filières', 'href' => 'back-office/roleplay/immersion#listes'],
        ]],
        ['path' => 'back-office/roleplay/regles-phases', 'group' => 'Roleplay', 'kicker' => 'ROLEPLAY · PARCOURS', 'title' => 'Parcours RH', 'subtitle' => 'Ordonnez les étapes du parcours et les conditions de passage à l’étape suivante. Un jeu vide n’avance personne.', 'quick' => [
            ['label' => 'Parcours d’immersion', 'href' => 'back-office/roleplay/immersion'],
            ['label' => 'Sessions Arma', 'href' => 'back-office/roleplay/sessions'],
            ['label' => 'Bureau de suivi', 'href' => 'back-office/roleplay-followup'],
        ]],
        ['path' => 'back-office/roleplay/sessions', 'group' => 'Roleplay', 'kicker' => 'ROLEPLAY · SESSIONS', 'title' => 'Sessions Arma', 'subtitle' => 'Les types de sessions de jeu, la façon de compter les heures et le temps pris en compte dans le suivi.', 'quick' => [
            ['label' => 'Parcours RH', 'href' => 'back-office/roleplay/regles-phases'],
            ['label' => 'Parcours d’immersion', 'href' => 'back-office/roleplay/immersion'],
            ['label' => 'Bureau de suivi', 'href' => 'back-office/roleplay-followup'],
        ]],
        ['path' => 'back-office/roleplay-followup/echeances', 'group' => 'Roleplay', 'kicker' => 'ROLEPLAY · ÉCHÉANCES', 'title' => 'Échéances', 'subtitle' => 'Les prochains entretiens, visites médicales et rotations de service, tous membres confondus.', 'quick' => [
            ['label' => 'Bureau de suivi', 'href' => 'back-office/roleplay-followup'],
            ['label' => 'Parcours d’immersion', 'href' => 'back-office/roleplay/immersion'],
        ]],
        ['path' => 'back-office/roleplay-followup', 'group' => 'Roleplay', 'kicker' => 'ROLEPLAY · SUIVI', 'title' => 'Bureau de suivi', 'subtitle' => 'Où en est chaque membre : tuteur, étape d’immersion, bilans et prochaines échéances.', 'quick' => [
            ['label' => 'Échéances', 'href' => 'back-office/roleplay-followup/echeances'],
            ['label' => 'Parcours d’immersion', 'href' => 'back-office/roleplay/immersion'],
        ]],
        ['path' => 'back-office/communications/history', 'group' => 'Communauté', 'kicker' => 'COMMUNAUTÉ · MESSAGES', 'title' => 'Historique des e-mails', 'subtitle' => 'Les e-mails envoyés aux membres, avec leur date et leurs destinataires.'],
        ['path' => 'back-office/communications/templates', 'group' => 'Communauté', 'kicker' => 'COMMUNAUTÉ · MESSAGES', 'title' => 'Modèles d’e-mail', 'subtitle' => 'Des messages prêts à réutiliser pour les envois fréquents.'],
        ['path' => 'back-office/communications/groups', 'group' => 'Communauté', 'kicker' => 'COMMUNAUTÉ · MESSAGES', 'title' => 'Listes de diffusion', 'subtitle' => 'Des listes de membres à qui écrire en une fois.'],
        ['path' => 'back-office/communications', 'group' => 'Communauté', 'kicker' => 'COMMUNAUTÉ · MESSAGES', 'title' => 'E-mail aux membres', 'subtitle' => 'Écrivez à toute la communauté, à une unité ou à une liste de diffusion.'],
        ['path' => 'back-office/missions', 'group' => 'Opérations', 'kicker' => 'OPÉRATIONS · PORTAIL', 'title' => 'Portail missions', 'subtitle' => 'Toutes les missions avec leurs participants, leurs communications ATAK et leurs liaisons.', 'css' => ['back-office-missions-portal.css'], 'quick' => [
            ['label' => 'Planification', 'href' => 'back-office/planification'],
            ['label' => 'Cycle de mission', 'href' => 'back-office/atak/cycle-mission'],
            ['label' => 'Poste ATAK', 'href' => 'back-office/atak'],
            ['label' => 'Sessions', 'href' => 'back-office/atak/operateurs'],
        ]],
        ['path' => 'back-office/planification', 'group' => 'Opérations', 'kicker' => 'OPÉRATIONS · PLANIFICATION', 'title' => 'Planification de mission', 'subtitle' => 'Qui fait quoi pendant la mission : organisation, affectations et ordres, avant et pendant la session.', 'css' => ['back-office-mission-planning.css']],
        ['path' => 'back-office/events/insights', 'group' => 'Opérations', 'kicker' => 'OPÉRATIONS · PRÉSENCES', 'title' => 'Bilan de participation', 'subtitle' => 'Qui vient, qui annule, qui ne répond pas : la participation sur les événements passés.', 'css' => ['back-office-events.css']],
        ['path' => 'back-office/events/reponses-nominatives', 'group' => 'Opérations', 'kicker' => 'OPÉRATIONS · CRÉNEAU', 'title' => 'Réponses nominatives', 'subtitle' => 'Suivi nominatif des réponses pour ce créneau.', 'css' => ['back-office-events.css']],
        ['path' => 'back-office/events', 'group' => 'Opérations', 'kicker' => 'OPÉRATIONS · REGISTRE', 'title' => 'Événements', 'subtitle' => 'Les opérations et entraînements à venir et passés, avec les inscriptions.', 'css' => ['back-office-events.css']],
        ['path' => 'back-office/atak/comptes-rendus', 'group' => 'Opérations', 'kicker' => 'OPÉRATIONS · RETOURS', 'title' => 'Comptes rendus', 'subtitle' => 'Les retours rédigés après chaque opération, à relire et valider.', 'css' => ['back-office-aar.css'], 'quick' => [
            ['label' => 'En attente', 'href' => 'back-office/atak/comptes-rendus?status=pending'],
            ['label' => 'Validés', 'href' => 'back-office/atak/comptes-rendus?status=validated'],
            ['label' => 'Actions ouvertes', 'href' => 'back-office/atak/comptes-rendus?open_actions=1'],
            ['label' => 'Modèles', 'href' => 'back-office/atak/comptes-rendus/modeles'],
        ]],
        ['path' => 'back-office/atak/comptes-rendus/modeles', 'group' => 'Opérations', 'kicker' => 'OPÉRATIONS · RETOURS', 'title' => 'Modèles de debriefing', 'subtitle' => 'Questionnaires de compte rendu : questions courtes, listes, cases à cocher et texte libre.', 'css' => ['back-office-aar.css'], 'quick' => [
            ['label' => 'Comptes rendus', 'href' => 'back-office/atak/comptes-rendus'],
            ['label' => 'Nouveau modèle', 'href' => 'back-office/atak/comptes-rendus/modeles/nouveau'],
        ]],
        ['path' => 'back-office/atak/comptes-rendus/modeles/nouveau', 'group' => 'Opérations', 'kicker' => 'OPÉRATIONS · RETOURS', 'title' => 'Nouveau modèle de debriefing', 'css' => ['back-office-aar.css']],
        ['path' => 'back-office/atak/cycle-mission', 'group' => 'Opérations', 'kicker' => 'OPÉRATIONS · POSTE DE COMMANDEMENT', 'title' => 'Cycle de mission', 'subtitle' => 'Les étapes d’une mission, de la préparation au compte rendu.', 'css' => ['back-office-mission-cycle.css']],
        ['path' => 'back-office/atak/briefing-slides', 'group' => 'Ressources', 'kicker' => 'RESSOURCES · TACTIQUE', 'title' => 'Diapositives de briefing', 'subtitle' => 'Images du briefing, ordre de passage et visibilité en jeu pour Arma et les téléphones ATAK.', 'quick' => [
            ['label' => 'Configuration ATAK', 'href' => 'admin/atak-config'],
        ]],
        ['path' => 'back-office/atak/fire-teams', 'group' => 'Personnel', 'kicker' => 'PERSONNEL · TACTIQUE', 'title' => 'Équipes de feu', 'subtitle' => 'La composition de chaque équipe de feu pour la session.', 'css' => ['back-office-fire-teams.css']],
        ['path' => 'back-office/atak', 'group' => 'ATAK', 'kicker' => 'ATAK · POSTE', 'title' => 'Poste de situation', 'subtitle' => 'La situation en direct : qui est connecté, où, et les dossiers de renseignement en cours.'],
        ['path' => 'back-office/atak/controle-serveur', 'group' => 'ATAK', 'kicker' => 'ATAK · MISSION', 'title' => 'Contrôle de mission', 'subtitle' => 'Les règles de la mission en cours, les fonctions activées et les relais de liaison.'],
        ['path' => 'back-office/atak/relays-network', 'group' => 'ATAK', 'kicker' => 'ATAK · RELAIS', 'title' => 'Réseau de relais', 'subtitle' => 'Les relais radio sur le terrain : état, portée, places disponibles et fiabilité.', 'quick' => [
            ['label' => 'Contrôle de mission', 'href' => 'back-office/atak/controle-serveur'],
            ['label' => 'Mode roleplay', 'href' => 'back-office/atak/roleplay'],
        ]],
        ['path' => 'back-office/atak/operateurs', 'group' => 'ATAK', 'kicker' => 'ATAK · SESSIONS', 'title' => 'Sessions & connexions', 'subtitle' => 'Qui est connecté en ce moment, et l’historique des connexions.'],
        ['path' => 'back-office/atak/fiche-operateur', 'group' => 'ATAK', 'kicker' => 'ATAK · FICHE OPÉRATEUR', 'title' => 'Fiche opérateur', 'subtitle' => 'Tout sur un opérateur : identité, appareil, certificat et liaison.'],
        ['path' => 'back-office/atak/realisme', 'group' => 'ATAK', 'kicker' => 'ATAK · PARC', 'title' => 'Parc de terminaux', 'subtitle' => 'Les appareils reliés à la plateforme et le membre qui utilise chacun.'],
        ['path' => 'back-office/atak/realisme/terminaux', 'group' => 'ATAK', 'kicker' => 'ATAK · PARC', 'title' => 'Journal de l’appareil', 'subtitle' => 'L’activité de cet appareil : erreurs, déconnexions et qualité de liaison.'],
        ['path' => 'back-office/atak/certificats', 'group' => 'ATAK', 'kicker' => 'ATAK · SÉCURITÉ', 'title' => 'Certificats', 'subtitle' => 'Les certificats qui autorisent chaque appareil à se connecter, et leur date d’expiration.'],
        ['path' => 'admin/atak-mod-reports', 'group' => 'Ressources', 'kicker' => 'RESSOURCES · MOD', 'title' => 'Signalements mod', 'css' => ['back-office-atak-beta.css']],
        ['path' => 'admin/atak-mod', 'group' => 'Ressources', 'kicker' => 'RESSOURCES · MOD', 'title' => 'Mod Arma', 'css' => ['back-office-atak-mod.css']],
        ['path' => 'admin/atak-beta', 'group' => 'Ressources', 'kicker' => 'RESSOURCES · MOD', 'title' => 'Inscriptions bêta mod', 'css' => ['back-office-atak-beta.css']],
        ['path' => 'back-office/audit', 'group' => 'Système', 'kicker' => 'SYSTÈME · TRAÇABILITÉ', 'title' => 'Journal d\'audit', 'subtitle' => 'Toutes les actions des administrateurs, datées et conservées 24 mois.', 'flags' => ['hideAuditPageHeader' => true]],
        ['path' => 'back-office/moderation', 'group' => 'Personnel', 'kicker' => 'PERSONNEL · DISCIPLINE', 'title' => 'Sanctions et absences', 'subtitle' => 'Les sanctions, limitations et absences en cours, membre par membre.'],
        ['path' => 'back-office/security-indicators', 'group' => 'Accès', 'kicker' => 'ACCÈS · SÉCURITÉ', 'title' => 'Sécurité et blocages', 'subtitle' => 'Les comptes bloqués et les tentatives de connexion suspectes.'],
        ['path' => 'back-office/forum-moderation', 'group' => 'Système', 'kicker' => 'SYSTÈME · FORUM', 'title' => 'Modération du forum', 'subtitle' => 'Les messages signalés par les membres, à garder ou à retirer.'],
        ['path' => 'back-office/courrier/traceabilite', 'group' => 'Opérations', 'kicker' => 'OPÉRATIONS', 'title' => 'Traçabilité du courrier', 'subtitle' => 'Qui a reçu et lu chaque message officiel.'],
        ['path' => 'back-office/doctrine/referentiel', 'group' => 'Opérations', 'kicker' => 'OPÉRATIONS · DOCTRINE', 'title' => 'Référentiel doctrinal', 'subtitle' => 'Les textes de référence sur lesquels s’appuient vos procédures.', 'quick' => [
            ['label' => 'Doctrine et SOP', 'href' => 'back-office/doctrine'],
        ]],
        ['path' => 'back-office/doctrine', 'group' => 'Opérations', 'kicker' => 'OPÉRATIONS', 'title' => 'Doctrine et SOP', 'subtitle' => 'Les procédures de votre unité, rangées et à jour.'],
        ['path' => 'back-office/conformite', 'group' => 'Opérations', 'kicker' => 'OPÉRATIONS', 'title' => 'Export pour contrôle', 'subtitle' => 'Rassemblez en un fichier les pièces demandées lors d’un contrôle.'],
        ['path' => 'back-office/recruitments', 'group' => 'Personnel', 'kicker' => 'PERSONNEL · RECRUTEMENT', 'title' => 'Candidatures', 'subtitle' => 'Les candidatures reçues. Ouvrez-en une pour l’accepter, la refuser ou demander un complément.'],
        ['path' => 'back-office/ressources/recrutement', 'group' => 'Personnel', 'kicker' => 'PERSONNEL · RECRUTEMENT', 'title' => 'Recrutement', 'subtitle' => 'Vue d’ensemble : candidatures en cours, délais de réponse et offres publiées.'],
        ['path' => 'back-office/recruitments/codes-invitation', 'group' => 'Personnel', 'kicker' => 'PERSONNEL · RECRUTEMENT', 'title' => 'Codes d’invitation prioritaires', 'subtitle' => 'Un code qui fait passer une candidature en priorité sur le formulaire d’enrôlement — distincts des invitations par e-mail et du code communauté.'],
        ['path' => 'back-office/recruitment', 'group' => 'Personnel', 'kicker' => 'PERSONNEL · RECRUTEMENT', 'title' => 'Recrutement'],
        ['path' => 'back-office/cooperation', 'group' => 'Ressources', 'kicker' => 'RESSOURCES', 'title' => 'Coopérations inter-unités', 'subtitle' => 'Les opérations menées avec d’autres communautés.', 'css' => ['cooperation.css']],
        ['path' => 'back-office/forum/priorite-mission', 'group' => 'Ressources', 'kicker' => 'RESSOURCES · FORUM', 'title' => 'Publier en priorité mission', 'subtitle' => 'Mettez un sujet du forum en tête pour la prochaine mission.'],
        ['path' => 'back-office/ressources', 'group' => 'Ressources', 'kicker' => 'RESSOURCES', 'title' => 'Ressources', 'subtitle' => 'Formations, documents, cartographie et outils de terrain.'],
        ['path' => 'formation', 'group' => 'Ressources', 'kicker' => 'RESSOURCES · FORMATIONS', 'title' => 'Formations', 'subtitle' => 'Les cours, les inscriptions et les qualifications délivrées.'],
        ['path' => 'documents/gestion/ajout', 'group' => 'Ressources', 'kicker' => 'RESSOURCES · DOCUMENTS', 'title' => 'Nouveau document', 'subtitle' => 'Ajoutez un fichier, ou rédigez directement un manuel avec sa page de garde.', 'css' => ['document-fm.css']],
        ['path' => 'documents/gestion', 'group' => 'Ressources', 'kicker' => 'RESSOURCES · DOCUMENTS', 'title' => 'Documents', 'subtitle' => 'Les documents de la communauté, classés par type.', 'css' => ['document-fm.css']],
        ['path' => 'admin/atak-config', 'group' => 'Ressources', 'kicker' => 'RESSOURCES · TACTIQUE', 'title' => 'Configuration ATAK', 'subtitle' => 'Cartes, calques et réglages de l’application ATAK pour vos membres.'],
        ['path' => 'admin/modpacks', 'group' => 'Ressources', 'kicker' => 'RESSOURCES', 'title' => 'Modpacks', 'subtitle' => 'Les listes de mods à installer pour rejoindre vos sessions.'],
        ['path' => 'admin/forum-config', 'group' => 'Ressources', 'kicker' => 'RESSOURCES · FORUM', 'title' => 'Réglages du forum', 'subtitle' => 'Rubriques, briefings et règles de publication du forum.'],
        ['path' => 'admin/content-moderation', 'group' => 'Système', 'kicker' => 'SYSTÈME · MODÉRATION', 'title' => 'Fichiers et pièces jointes', 'subtitle' => 'Les fichiers déposés par les membres, à vérifier ou supprimer.'],
        ['path' => 'tableau-operationnel', 'group' => 'Opérations', 'kicker' => 'OPÉRATIONS', 'title' => 'Mur opérationnel', 'subtitle' => 'Ce que voient les membres : prochaines opérations, articles et annonces.'],
    ],

    'dashboard_css' => ['back-office-dashboard.css', 'announce-tiles.css'],

    'skip_page_head_paths' => [
        'back-office/atak/comptes-rendus/',
    ],
];
