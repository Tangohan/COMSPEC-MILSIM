<?php

declare(strict_types=1);

namespace App\Services\Portal;

/**
 * Guides consultables depuis la recherche du back-office (Ctrl/⌘+K).
 *
 * Chaque entrée pointe vers une section existante : guide du portail (/documentation#…)
 * ou guide de création de communauté (/communities/create/guide#…).
 */
final class BackOfficeHelpCatalog
{
    /**
     * @return list<array{title: string, subtitle: string, href: string, keywords: string}>
     */
    public static function entries(): array
    {
        $doc = static fn (string $anchor): string => url('documentation') . '#' . $anchor;
        $create = static fn (string $anchor): string => url('communities/create/guide') . '#' . $anchor;

        return [
            ['title' => 'Mettre en service une communauté', 'subtitle' => 'Guide · les étapes après la création', 'href' => $doc('etapes-de-mise-en-service'), 'keywords' => 'démarrage première soirée mise en service checklist configuration initiale tutoriel'],
            ['title' => 'Navigation et recherche', 'subtitle' => 'Guide · raccourcis et recherche', 'href' => $doc('navigation-et-recherche'), 'keywords' => 'navigation recherche raccourci ctrl k menu'],
            ['title' => 'Droits, permissions et rôles', 'subtitle' => 'Guide · qui peut faire quoi', 'href' => $doc('droits-permissions-et-roles'), 'keywords' => 'droits permissions rôles accès matrice niveaux'],
            ['title' => 'Créer une communauté : le guide complet', 'subtitle' => 'Guide · étapes et explication de chaque champ', 'href' => url('communities/create/guide'), 'keywords' => 'création assistant wizard tenant nouvelle communauté tutoriel champs'],
            ['title' => 'Champs de l’identité (nom, adresse, unité représentée)', 'subtitle' => 'Guide de création · étape 1', 'href' => $create('etape-identite'), 'keywords' => 'nom adresse slug url langue fuseau unité réelle fictive vitrine logo bannière'],
            ['title' => 'Choisir le type de communauté', 'subtitle' => 'Guide de création · étape 2', 'href' => $create('etape-type'), 'keywords' => 'type complet bureau des effectifs carte atak modules'],
            ['title' => 'Rôles, modèles et organigramme à la création', 'subtitle' => 'Guide de création · étape 3', 'href' => $create('etape-organisation'), 'keywords' => 'rôles modèle catalogue orbat organigramme structure unités'],
            ['title' => 'Structures types d’organigramme', 'subtitle' => 'Guide · escouade, section, peloton, compagnie, SOF', 'href' => $create('structures'), 'keywords' => 'orbat structure type preset escouade section peloton compagnie détachement interarmes'],
            ['title' => 'Modèles officiels du catalogue', 'subtitle' => 'Guide · contenu de chaque modèle', 'href' => $create('modeles'), 'keywords' => 'catalogue modèles kits armée française sof infanterie blindé aéromobile hélicoptère smp pmc gaming fonctions emplois'],
            ['title' => 'Inscription, visibilité et formule', 'subtitle' => 'Guide de création · étape 5', 'href' => $create('etape-acces'), 'keywords' => 'inscription recrutement milsim discord formulaire court visibilité formule abonnement ancienneté'],
            ['title' => 'Questions fréquentes sur la création', 'subtitle' => 'Guide de création · FAQ', 'href' => $create('faq'), 'keywords' => 'faq questions modifier adresse paiement modèle'],
            ['title' => 'Rôles de la communauté et fonctions métier', 'subtitle' => 'Guide · différence entre rôle et fonction', 'href' => $doc('roles-communaute-et-metiers'), 'keywords' => 'rôle fonction emploi métier différence'],
            ['title' => 'Personnel, ORBAT et profils métier', 'subtitle' => 'Guide · organigramme et affectations', 'href' => $doc('personnel-et-orbat'), 'keywords' => 'personnel orbat organigramme affectation effectifs unités'],
            ['title' => 'Recrutement et enrôlement', 'subtitle' => 'Guide · candidatures et intégration', 'href' => $doc('recrutement-et-enrolement'), 'keywords' => 'recrutement candidature enrôlement dossier milsim discord intégration'],
            ['title' => 'Pilotage de l’organisation', 'subtitle' => 'Guide · réglages de la communauté', 'href' => $doc('pilotage-organisation'), 'keywords' => 'pilotage organisation paramètres réglages administration'],
            ['title' => 'Abonnements, quotas et limites', 'subtitle' => 'Guide · formules et capacités', 'href' => $doc('abonnements-et-limites-plan'), 'keywords' => 'abonnement formule quota limite places paiement'],
            ['title' => 'Alertes et annonces', 'subtitle' => 'Guide · messages aux membres', 'href' => $doc('alertes-et-annonces'), 'keywords' => 'alertes annonces bandeau messages'],
            ['title' => 'Événements, messages et pointage', 'subtitle' => 'Guide · sessions et présences', 'href' => $doc('evenements-messages-pointage'), 'keywords' => 'événements opérations calendrier pointage présence rsvp messagerie'],
            ['title' => 'Formations (LMS et Studio)', 'subtitle' => 'Guide · parcours et qualifications', 'href' => $doc('formations'), 'keywords' => 'formations lms studio cours qualification instruction'],
            ['title' => 'Documents', 'subtitle' => 'Guide · bibliothèque et droits', 'href' => $doc('documents'), 'keywords' => 'documents doctrine bibliothèque sop'],
            ['title' => 'Courrier officiel', 'subtitle' => 'Guide · en-têtes et diffusion', 'href' => $doc('courrier-officiel'), 'keywords' => 'courrier lettre en-tête officiel'],
            ['title' => 'Forum et briefings', 'subtitle' => 'Guide · échanges et ordres', 'href' => $doc('forum-et-briefings'), 'keywords' => 'forum briefing sujets'],
            ['title' => 'Modération et signalements', 'subtitle' => 'Guide · forum et contenus', 'href' => $doc('moderation-forum'), 'keywords' => 'modération signalement forum sanctions'],
            ['title' => 'Coopérations inter-unités', 'subtitle' => 'Guide · missions communes', 'href' => $doc('cooperations-inter-unites'), 'keywords' => 'coopération inter-unités alliance mission commune'],
            ['title' => 'Équipement et modpacks', 'subtitle' => 'Guide · dotation et mods', 'href' => $doc('equipement-et-modpacks'), 'keywords' => 'équipement dotation modpack mods arsenal'],
            ['title' => 'Outils, cartes et tactique', 'subtitle' => 'Guide · carte, ATAK et terrain', 'href' => $doc('outils-cartes-et-tactique'), 'keywords' => 'carte tactique atak overwatch terrain outils'],
            ['title' => 'Analytics et intégrations', 'subtitle' => 'Guide · indicateurs et services liés', 'href' => $doc('analytics-et-integrations'), 'keywords' => 'analytics indicateurs statistiques intégrations discord steam'],
            ['title' => 'Bonnes pratiques', 'subtitle' => 'Guide · conseils d’organisation', 'href' => $doc('bonnes-pratiques'), 'keywords' => 'bonnes pratiques conseils astuces'],
            ['title' => 'Compte et sécurité', 'subtitle' => 'Guide · connexion et protection', 'href' => $doc('compte-et-securite'), 'keywords' => 'compte sécurité mot de passe connexion'],
        ];
    }
}
