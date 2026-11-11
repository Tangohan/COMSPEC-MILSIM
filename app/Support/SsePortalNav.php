<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Navigation du bureau SSE, définie une seule fois et rangée par usage :
 * pilotage → renseignement et enquêtes → référentiel → analyse → production
 * → identités et papiers → fichiers et contrôles → IA et roleplay → outils et réglages.
 *
 * Sert la barre latérale, l'accueil du bureau, le fil d'Ariane, les onglets de section (téléphone)
 * et la palette de commandes (Ctrl+K). Un lien n'apparaît que si la session peut ouvrir la page ;
 * l'accueil dit ce qui manque et pourquoi (restricted()). L'élément actif se déduit de l'URL
 * demandée (correspondance la plus longue), ce qui couvre les pages de détail
 * (/dossiers/12 → « Dossiers validés ») et les registres d'objets (/objets/vehicules).
 */
final class SsePortalNav
{
    public const HOME = 'atak/sse/accueil';

    /**
     * @return list<array{id: string, label: string, hint: string, items: list<array{id: string, label: string, hint: string, path: string, icon: string, external?: bool, aliases?: list<string>, keywords?: string}>}>
     */
    public static function sections(bool $canManage, bool $canGrant, bool $isGuest = false, bool $canMemberPapers = false, bool $canEditorLibrary = false): array
    {
        $only = static fn (bool $allowed, array $item): ?array => $allowed ? $item : null;

        return [
            ['id' => 'pilotage', 'label' => 'Pilotage', 'hint' => 'La situation du jour et ce qui attend une décision.', 'items' => [
                ['id' => 'accueil', 'label' => 'Accueil du bureau', 'hint' => 'Tous les espaces du bureau sur une page, avec leur accès.', 'path' => self::HOME, 'icon' => 'home', 'keywords' => 'accueil plan sommaire espaces'],
                ['id' => 'operations', 'label' => 'Vue opérationnelle', 'hint' => 'Situation du jour : priorités, file opérateur, alertes.', 'path' => 'atak/sse/operations', 'icon' => 'ops', 'keywords' => 'tableau de bord situation control tower'],
                ['id' => 'workspace', 'label' => 'Espace d’exploitation', 'hint' => 'Boîte de réception, cycle du renseignement et constats d’analyse.', 'path' => 'atak/sse/workspace', 'icon' => 'inbox', 'keywords' => 'intelligence workspace inbox cycle'],
                ['id' => 'validation', 'label' => 'Files de validation', 'hint' => 'Décisions en attente d’arbitrage.', 'path' => 'atak/sse/validation', 'icon' => 'queue', 'keywords' => 'valider arbitrage attente'],
            ]],
            ['id' => 'renseignement', 'label' => 'Renseignement et enquêtes', 'hint' => 'Ce qui remonte du terrain et les dossiers qui l’instruisent.', 'items' => [
                ['id' => 'transmissions', 'label' => 'Transmissions terrain', 'hint' => 'Envois reçus d’Arma 3 : fiches, biométrie, sites, relevés.', 'path' => 'atak/sse/transmissions', 'icon' => 'collect', 'keywords' => 'arma envois seek collecte'],
                ['id' => 'fiches', 'label' => 'Fiches de renseignement', 'hint' => 'Notes libres remontées du terrain, avec pièces jointes.', 'path' => 'atak/sse/fiches', 'icon' => 'note', 'keywords' => 'notes terrain'],
                ['id' => 'collecte', 'label' => 'Demandes de collecte', 'hint' => 'Ce qu’il reste à collecter et où le déposer.', 'path' => 'atak/sse/collecte', 'icon' => 'target', 'keywords' => 'collecte besoins'],
                ['id' => 'labnum', 'label' => 'Exploitation numérique', 'hint' => 'Téléphones, ordinateurs et supports saisis.', 'path' => 'atak/sse/exploitation-numerique', 'icon' => 'lab', 'keywords' => 'domex telephone ordinateur supports'],
                ['id' => 'interet', 'label' => 'Dossiers d’intérêt', 'hint' => 'Signalements à qualifier : hypothèses, pas encore d’identité certaine.', 'path' => 'atak/sse/interet', 'icon' => 'interest', 'keywords' => 'pressee signalements'],
                ['id' => 'dossiers', 'label' => 'Dossiers validés', 'hint' => 'Affaires structurées : preuves, notes, personnel, comptes rendus.', 'path' => 'atak/sse/dossiers', 'icon' => 'folder', 'keywords' => 'affaires cases preuves enquetes'],
                ['id' => 'toiles', 'label' => 'Investigations', 'hint' => 'Tableaux des liens : relier personnes, lieux et objets.', 'path' => 'atak/sse/toiles', 'icon' => 'mesh', 'keywords' => 'tableau des liens graphe relationnel toiles reseau'],
            ]],
            ['id' => 'referentiel', 'label' => 'Référentiel', 'hint' => 'Personnes, organisations, lieux et objets connus du bureau.', 'items' => [
                ['id' => 'identites', 'label' => 'Personnes', 'hint' => 'Fiches personnes et indices biométriques.', 'path' => 'atak/sse/identites', 'icon' => 'id', 'aliases' => ['atak/sse/personnes'], 'keywords' => 'identites personnes biometrie'],
                ['id' => 'organisations', 'label' => 'Organisations', 'hint' => 'Groupes, cellules et structures affiliées.', 'path' => 'atak/sse/objets/organisations', 'icon' => 'org'],
                ['id' => 'sites', 'label' => 'Sites', 'hint' => 'Lieux d’intérêt et d’exploitation.', 'path' => 'atak/sse/sites', 'icon' => 'site', 'keywords' => 'lieux'],
                ['id' => 'vehicules', 'label' => 'Véhicules', 'hint' => 'Moyens mobiles rattachés aux dossiers.', 'path' => 'atak/sse/objets/vehicules', 'icon' => 'vehicle'],
                ['id' => 'materiels', 'label' => 'Matériels', 'hint' => 'Équipements et objets saisis.', 'path' => 'atak/sse/objets/materiels', 'icon' => 'gear', 'keywords' => 'armes saisies'],
                ['id' => 'pieces', 'label' => 'Pièces documentaires', 'hint' => 'Documents physiques ou numérisés versés au dossier.', 'path' => 'atak/sse/objets/documents', 'icon' => 'doc', 'keywords' => 'preuves'],
            ]],
            ['id' => 'analyse', 'label' => 'Analyse', 'hint' => 'Relier les faits : chronologie, rapprochements, anomalies, carte.', 'items' => [
                ['id' => 'chronologie', 'label' => 'Chronologie', 'hint' => 'Faits et événements dans l’ordre.', 'path' => 'atak/sse/chronologie', 'icon' => 'time', 'keywords' => 'timeline'],
                ['id' => 'croisements', 'label' => 'Croisements', 'hint' => 'Correspondances entre fiches terrain et listes de surveillance, à valider.', 'path' => 'atak/sse/croisements', 'icon' => 'cross', 'keywords' => 'surveillance watchlist'],
                ['id' => 'rapprochements', 'label' => 'Suggestions du moteur', 'hint' => 'Liens trouvés automatiquement, à accepter ou rejeter.', 'path' => 'atak/sse/rapprochements', 'icon' => 'engine', 'keywords' => 'rapprochements moteur'],
                ['id' => 'anomalies', 'label' => 'Anomalies', 'hint' => 'Écarts et incohérences à examiner.', 'path' => 'atak/sse/anomalies', 'icon' => 'alert'],
                ['id' => 'carte', 'label' => 'Carte ATAK', 'hint' => 'Projection géographique (ouvre l’ATAK).', 'path' => 'atak', 'icon' => 'map', 'external' => true],
            ]],
            ['id' => 'production', 'label' => 'Production', 'hint' => 'Rédiger, relire et diffuser les documents du bureau.', 'items' => [
                ['id' => 'documents', 'label' => 'Rédaction', 'hint' => 'Notes, flash et documents officiels.', 'path' => 'atak/sse/documents', 'icon' => 'write', 'keywords' => 'documents note flash'],
                ['id' => 'rapports', 'label' => 'Rapports', 'hint' => 'Productions finales et bilans du bureau.', 'path' => 'atak/sse/rapports', 'icon' => 'report'],
                ['id' => 'bibliotheque', 'label' => 'Mentions officielles', 'hint' => 'Formulations validées à réutiliser dans les documents.', 'path' => 'atak/sse/bibliotheque', 'icon' => 'library', 'keywords' => 'bibliotheque textes'],
                ['id' => 'presentation', 'label' => 'Présentation des documents', 'hint' => 'Bandeau, pied de page et papier des documents.', 'path' => 'atak/sse/presentation', 'icon' => 'paper'],
            ]],
            ['id' => 'identite', 'label' => 'Identités et papiers', 'hint' => 'Les papiers fictifs du jeu, de leur création à leur émission, et ceux des membres.', 'items' => array_values(array_filter([
                ['id' => 'papiers-tableau', 'label' => 'Tableau de bord', 'hint' => 'Contrôles, faux détectés et recherchés interpellés par opération.', 'path' => 'atak/sse/papiers/tableau', 'icon' => 'ops', 'keywords' => 'identite controle tableau bord operation faux recherches'],
                ['id' => 'papiers', 'label' => 'Papiers d’identité', 'hint' => 'Registre des papiers d’identité fictifs émis pour le jeu.', 'path' => 'atak/sse/papiers', 'icon' => 'doc', 'aliases' => ['atak/sse/papiers/documents'], 'keywords' => 'papiers passeport carte permis registre mrz documents identite'],
                ['id' => 'papiers-identites', 'label' => 'Identités fictives', 'hint' => 'Identités (réelles, de couverture, falsifiées) portées par les papiers.', 'path' => 'atak/sse/papiers/identites', 'icon' => 'id', 'keywords' => 'identites fictives couverture alias faux'],
                $only($canMemberPapers, ['id' => 'papiers-membres', 'label' => 'Papiers des membres', 'hint' => 'Identité principale, alias et kit de papiers des membres.', 'path' => 'atak/sse/papiers/membres', 'icon' => 'org', 'keywords' => 'membres papiers kit alias identite principale']),
                ['id' => 'papiers-pays', 'label' => 'Pays émetteurs', 'hint' => 'Pays imaginaires (ou réels stylisés) qui émettent les papiers.', 'path' => 'atak/sse/papiers/pays', 'icon' => 'flag', 'keywords' => 'pays emetteurs drapeaux nationalite'],
                $only($canMemberPapers, ['id' => 'papiers-atelier', 'label' => 'Atelier de documents', 'hint' => 'Composer un papier avec aperçu recto, verso et pages de passeport.', 'path' => 'atak/sse/papiers/atelier', 'icon' => 'write', 'keywords' => 'atelier editeur document apercu passeport carte permis export png']),
                $only($canMemberPapers, ['id' => 'papiers-kits', 'label' => 'Kits types', 'hint' => 'Modèles de kits (civil, militaire, journaliste, agent infiltré…) à affecter aux membres.', 'path' => 'atak/sse/papiers/kits-types', 'icon' => 'library', 'keywords' => 'kits types modeles civil militaire diplomatique journaliste contractor infiltre']),
                $only($canMemberPapers, ['id' => 'papiers-briefs', 'label' => 'Briefs de couverture', 'hint' => 'Biographie, légende et consignes remises aux joueurs couverts.', 'path' => 'atak/sse/papiers/briefs', 'icon' => 'note', 'keywords' => 'briefs couverture legende biographie consignes']),
                $only($canMemberPapers, ['id' => 'papiers-echanges', 'label' => 'Import et export', 'hint' => 'Importer ou exporter identités et papiers (CSV, JSON).', 'path' => 'atak/sse/papiers/echanges', 'icon' => 'transfer', 'keywords' => 'import export csv json identites papiers']),
            ]))],
            ['id' => 'fichiers', 'label' => 'Fichiers et contrôles', 'hint' => 'Interroger les fichiers et suivre les contrôles d’identité faits en jeu.', 'items' => array_values(array_filter([
                ['id' => 'fichiers', 'label' => 'Passage aux fichiers', 'hint' => 'Interroger les fichiers comme depuis un terminal de patrouille.', 'path' => 'atak/sse/fichiers', 'icon' => 'search', 'aliases' => ['atak/sse/fichiers/sujet', 'atak/sse/fichiers/source', 'atak/sse/fichiers/comparer'], 'keywords' => 'fichiers requete hit terminal patrouille alias recherche sujet fiche'],
                ['id' => 'fichiers-photos', 'label' => 'Photothèque', 'hint' => 'Photos signalétiques filtrées par signalement, comparaison de deux fiches.', 'path' => 'atak/sse/fichiers/photos', 'icon' => 'id', 'keywords' => 'photos photographie signalement visage comparer trombinoscope'],
                ['id' => 'fichiers-telephones', 'label' => 'Téléphones du jeu', 'hint' => 'Extractions des téléphones ATAK synchronisés depuis le jeu.', 'path' => 'atak/sse/fichiers/telephones', 'icon' => 'collect', 'keywords' => 'telephone extraction atak contacts messages positions'],
                $only(self::canLore($isGuest), ['id' => 'fichiers-lore', 'label' => 'Lore des sessions', 'hint' => 'Préparer les personnes d’une mission et ce que les fichiers en savent.', 'path' => 'atak/sse/fichiers/lore', 'icon' => 'dice', 'keywords' => 'lore session mission mj zeus eden ia synchronisation']),
                ['id' => 'fichiers-journal', 'label' => 'Journal des consultations', 'hint' => 'Toutes les consultations des fichiers, sur le site ou en jeu.', 'path' => 'atak/sse/fichiers/journal', 'icon' => 'time', 'keywords' => 'journal consultations historique'],
                ['id' => 'fichiers-mentions', 'label' => 'Mentions aux fichiers', 'hint' => 'Mandats, avis de recherche, signalements et consignes de prudence.', 'path' => 'atak/sse/fichiers/mentions', 'icon' => 'alert', 'keywords' => 'mentions mandat recherche signalement disparu arme dangereux'],
                ['id' => 'fichiers-seek', 'label' => 'Fiches SEEK II', 'hint' => 'Enregistrements biométriques faits en jeu avec le SEEK II.', 'path' => 'atak/sse/fichiers/seek', 'icon' => 'id', 'keywords' => 'seek biometrie empreintes iris visage'],
                ['id' => 'papiers-controles', 'label' => 'Contrôles reçus', 'hint' => 'Contrôles d’identité transmis depuis le terrain.', 'path' => 'atak/sse/papiers/controles', 'icon' => 'queue', 'keywords' => 'controles identite verdict terrain checkpoint'],
                ['id' => 'papiers-frm', 'label' => 'FRM à valider', 'hint' => 'Brouillons de fiches FRM nés des contrôles, à valider.', 'path' => 'atak/sse/papiers/tableau#frm', 'icon' => 'note', 'keywords' => 'frm brouillons valider fiches controles'],
            ]))],
            ['id' => 'roleplay', 'label' => 'IA et roleplay', 'hint' => 'Préparer ce que les joueurs rencontreront en jeu : population, IA, campagnes, scénarios.', 'items' => array_values(array_filter([
                $only($canMemberPapers, ['id' => 'papiers-profils-ia', 'label' => 'Profils IA', 'hint' => 'Identité, camp, papiers et comportement à relier aux IA en jeu (Zeus, Eden).', 'path' => 'atak/sse/papiers/profils-ia', 'icon' => 'person', 'keywords' => 'ia pnj zeus eden profil population']),
                $only($canMemberPapers, ['id' => 'papiers-preparation', 'label' => 'Préparation de mission', 'hint' => 'Générer la population, les recherchés et les HVT d’une opération.', 'path' => 'atak/sse/papiers/preparation', 'icon' => 'prepare', 'keywords' => 'preparation mission population civils militaires suspects hvt recherches generation']),
                ['id' => 'campagnes', 'label' => 'Campagnes', 'hint' => 'Personnages, organisations et scellés du mod, partagés entre communautés.', 'path' => 'atak/sse/campagnes', 'icon' => 'flag', 'keywords' => 'campagnes personnages scelles'],
                ['id' => 'dev', 'label' => 'Modèles de mission', 'hint' => 'Créer et emporter des modèles de mission pour Arma.', 'path' => 'atak/sse/dev', 'icon' => 'prepare', 'keywords' => 'modeles scenario import'],
                $only($canEditorLibrary, ['id' => 'editeur', 'label' => 'Bibliothèque de l’éditeur', 'hint' => 'Contenus de l’éditeur SSE du jeu, en lecture seule.', 'path' => 'atak/sse/editeur', 'icon' => 'library', 'keywords' => 'editeur mod renseignements identites papiers kits dossiers contenus']),
                $only($canManage, ['id' => 'maitre-jeu', 'label' => 'Maître du jeu', 'hint' => 'Identités, listes et histoires lues par le terminal SEEK.', 'path' => 'atak/sse/maitre-jeu', 'icon' => 'dice', 'keywords' => 'zeus seek']),
            ]))],
            ['id' => 'outils', 'label' => 'Outils et réglages', 'hint' => 'Accès au bureau et mode d’emploi.', 'items' => array_values(array_filter([
                $only($canGrant && !$isGuest, ['id' => 'acces', 'label' => 'Habilitations et codes', 'hint' => 'Codes d’accès temporaires et niveaux de diffusion.', 'path' => 'atak/sse/acces', 'icon' => 'admin', 'keywords' => 'codes acces administration']),
                ['id' => 'guide', 'label' => 'Documentation', 'hint' => 'Mode d’emploi du bureau SSE.', 'path' => 'atak/sse/guide', 'icon' => 'help', 'aliases' => ['atak/sse/documentation'], 'keywords' => 'aide guide manuel'],
            ]))],
        ];
    }

    /**
     * Navigation de la session courante, avec les mêmes droits que les pages : papiers des membres
     * et atelier (gestion SSE ou habilitation Encadrement, hors invité), bibliothèque de l'éditeur
     * (compte identifié et habilitation Confidentiel).
     *
     * @return array{sections: list<array<string, mixed>>, restricted: list<array{label: string, reason: string}>}
     */
    /** Lore des sessions : MJ (atak.sse.lore.manage) ou administrateur, jamais en session invitée. */
    public static function canLore(bool $isGuest): bool
    {
        return !$isGuest && function_exists('can') && (can('atak.sse.lore.manage') || can('admin.access'));
    }

    public static function forSession(bool $canManage, bool $canGrant, bool $isGuest, ?bool $canMemberPapers = null): array
    {
        if ($canMemberPapers === null) {
            try {
                $canMemberPapers = !$isGuest && (int) \App\Core\Session::get('user_id') > 0
                    && \App\Controllers\Admin\MemberIdentityPapersController::currentRights()['sse'];
            } catch (\Throwable) {
                $canMemberPapers = false;
            }
        }
        try {
            $canEditor = \App\Controllers\Web\SseEditorLibraryController::allowed();
        } catch (\Throwable) {
            $canEditor = false;
        }

        return [
            'sections' => self::sections($canManage, $canGrant, $isGuest, $canMemberPapers, $canEditor),
            'restricted' => self::restricted($canManage, $canGrant, $isGuest, $canMemberPapers, $canEditor),
        ];
    }

    /**
     * Espaces absents de la navigation de la session, et pourquoi (affichés sur l'accueil du bureau).
     *
     * @return list<array{label: string, reason: string}>
     */
    public static function restricted(bool $canManage, bool $canGrant, bool $isGuest, bool $canMemberPapers, bool $canEditorLibrary): array
    {
        $out = [];
        if (!$canMemberPapers) {
            $out[] = [
                'label' => 'Papiers des membres, atelier de documents, kits types, briefs, import et export, profils IA, préparation de mission',
                'reason' => $isGuest ? 'Fermés aux sessions invitées : entrez avec votre compte.' : 'Réservés à la gestion SSE ou à l’habilitation Encadrement.',
            ];
        }
        if (!$canEditorLibrary) {
            $out[] = ['label' => 'Bibliothèque de l’éditeur', 'reason' => 'Demande un compte identifié et l’habilitation Confidentiel.'];
        }
        if (!$canManage) {
            $out[] = ['label' => 'Maître du jeu', 'reason' => 'Réservé à la gestion SSE.'];
        }
        if (!$canGrant || $isGuest) {
            $out[] = ['label' => 'Habilitations et codes', 'reason' => 'Réservé aux personnes qui délivrent les codes d’accès, hors session invitée.'];
        }

        return $out;
    }

    /**
     * Identifiant de l'élément actif pour un chemin demandé (« /public/atak/sse/dossiers/12?x=1 »).
     * Correspondance la plus longue sur le chemin de l'élément ou ses alias ; à défaut, $activeNav.
     *
     * Une ancre (« …/tableau#frm ») ne compte pas : le fragment n'arrive jamais au serveur.
     *
     * @param list<array<string, mixed>> $sections
     */
    public static function activeId(array $sections, string $requestPath, string $activeNav = ''): string
    {
        $path = '/' . trim((string) (parse_url($requestPath, PHP_URL_PATH) ?? ''), '/') . '/';
        $best = '';
        $bestLen = 0;
        foreach ($sections as $section) {
            foreach ($section['items'] as $item) {
                if (!empty($item['external']) || str_contains((string) $item['path'], '#')) {
                    continue;
                }
                foreach (array_merge([(string) $item['path']], $item['aliases'] ?? []) as $p) {
                    $needle = '/' . trim($p, '/') . '/';
                    if (str_contains($path, $needle) && strlen($needle) > $bestLen) {
                        $best = (string) $item['id'];
                        $bestLen = strlen($needle);
                    }
                }
            }
        }
        if ($best !== '') {
            return $best;
        }
        // Anciennes valeurs de $activeNav encore passées par certaines vues.
        $legacy = ['graphe' => 'toiles', 'pressee' => 'interet', 'validated' => 'dossiers', 'personnes' => 'identites', 'objets' => 'organisations'];

        return $legacy[$activeNav] ?? $activeNav;
    }

    /** Section qui contient l'élément actif. */
    public static function sectionOf(array $sections, string $itemId): string
    {
        $found = self::locate($sections, $itemId);

        return $found === null ? '' : (string) $found[0]['id'];
    }

    /**
     * Section et élément d'un identifiant, ou null.
     *
     * @param list<array<string, mixed>> $sections
     * @return array{0: array<string, mixed>, 1: array<string, mixed>}|null
     */
    public static function locate(array $sections, string $itemId): ?array
    {
        foreach ($sections as $section) {
            foreach ($section['items'] as $item) {
                if ($item['id'] === $itemId) {
                    return [$section, $item];
                }
            }
        }

        return null;
    }

    /**
     * Fil d'Ariane : Bureau SSE › section › page › page de détail (titre de la page, sans lien).
     *
     * @param list<array<string, mixed>> $sections
     * @return list<array{label: string, href: ?string}>
     */
    public static function breadcrumb(array $sections, string $activeId, string $requestPath, string $pageTitle, callable $url): array
    {
        $home = ['label' => 'Bureau SSE', 'href' => (string) $url(self::HOME)];
        $title = self::shortTitle($pageTitle);
        $found = self::locate($sections, $activeId);
        if ($found === null) {
            return $title !== '' ? [$home, ['label' => $title, 'href' => null]] : [['label' => 'Bureau SSE', 'href' => null]];
        }
        [$section, $item] = $found;
        if ($item['id'] === 'accueil') {
            return [['label' => 'Bureau SSE', 'href' => null]];
        }
        $crumbs = [$home, ['label' => (string) $section['label'], 'href' => (string) $url(self::HOME) . '#' . $section['id']]];
        $path = '/' . trim((string) (parse_url($requestPath, PHP_URL_PATH) ?? ''), '/') . '/';
        $onItemPage = false;
        foreach (array_merge([(string) $item['path']], $item['aliases'] ?? []) as $p) {
            $onItemPage = $onItemPage || str_ends_with($path, '/' . trim($p, '/') . '/');
        }
        if ($onItemPage || $title === '' || mb_strtolower($title) === mb_strtolower((string) $item['label'])) {
            $crumbs[] = ['label' => (string) $item['label'], 'href' => null];
        } else {
            $crumbs[] = ['label' => (string) $item['label'], 'href' => (string) $url((string) $item['path'])];
            $crumbs[] = ['label' => $title, 'href' => null];
        }

        return $crumbs;
    }

    /** Titre sans suffixe décoratif (« Vue opérationnelle — Control Tower » → « Vue opérationnelle »). */
    private static function shortTitle(string $title): string
    {
        $title = trim($title);
        $cut = mb_strpos($title, ' — ');

        return $cut === false ? $title : trim(mb_substr($title, 0, $cut));
    }

    /** Pictogramme SVG d'un élément (décoratif, masqué aux lecteurs d'écran). */
    public static function icon(string $name): string
    {
        $common = ' class="iw-nav-ico" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"';
        $paths = match ($name) {
            'home' => '<path d="M4 11 12 4l8 7"/><path d="M6 9.5V20h12V9.5"/><path d="M10 20v-5h4v5"/>',
            'ops' => '<rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/>',
            'inbox' => '<path d="M3.5 13.5 6 5h12l2.5 8.5V19a1 1 0 0 1-1 1h-15a1 1 0 0 1-1-1z"/><path d="M3.5 13.5H8l1.5 2.5h5l1.5-2.5h4.5"/>',
            'mesh' => '<circle cx="6" cy="7" r="2.2"/><circle cx="18" cy="7" r="2.2"/><circle cx="12" cy="17" r="2.2"/><path d="M8 8.2 10.5 15M16 8.2 13.5 15M8.2 7h7.6"/>',
            'interest' => '<path d="M12 3.5 14.2 9l5.8.5-4.4 3.8 1.4 5.7L12 16.2 6.9 19l1.4-5.7L4 9.5 9.8 9z"/>',
            'folder' => '<path d="M3 7.5A1.5 1.5 0 0 1 4.5 6H9l1.5 2H19.5A1.5 1.5 0 0 1 21 9.5v8A1.5 1.5 0 0 1 19.5 19h-15A1.5 1.5 0 0 1 3 17.5z"/>',
            'id', 'person' => '<circle cx="12" cy="8" r="3.2"/><path d="M5.5 19c1.6-3.2 4-4.8 6.5-4.8S16.9 15.8 18.5 19"/>',
            'org' => '<path d="M4 20V9l8-5 8 5v11"/><path d="M9 20v-6h6v6"/><path d="M9 10h.01M15 10h.01"/>',
            'site' => '<path d="M12 21s-7-5.2-7-11a7 7 0 1 1 14 0c0 5.8-7 11-7 11z"/><circle cx="12" cy="10" r="2.4"/>',
            'vehicle' => '<path d="M4 15h16l-1.2-5.2A2 2 0 0 0 16.9 8H7.1a2 2 0 0 0-1.9 1.8L4 15z"/><path d="M6.5 15v2.5M17.5 15v2.5M7.5 11h9"/><circle cx="7.5" cy="17.5" r="1.2"/><circle cx="16.5" cy="17.5" r="1.2"/>',
            'gear' => '<path d="M12 8.2a3.8 3.8 0 1 0 0 7.6 3.8 3.8 0 0 0 0-7.6z"/><path d="M12 3.2v2M12 18.8v2M4.8 6.4l1.5 1.5M17.7 16.1l1.5 1.5M3.2 12h2M18.8 12h2M4.8 17.6l1.5-1.5M17.7 7.9l1.5-1.5"/>',
            'doc' => '<path d="M7 3.5h7l4 4V20a1 1 0 0 1-1 1H7a1 1 0 0 1-1-1V4.5a1 1 0 0 1 1-1z"/><path d="M14 3.5V8h4M9 12h6M9 15.5h6"/>',
            'time' => '<circle cx="12" cy="12" r="8.2"/><path d="M12 7.5V12l3.2 2"/>',
            'map' => '<path d="M3.5 6.5 9 4.5l6 2 5.5-2v13l-5.5 2-6-2-5.5 2z"/><path d="M9 4.5v13M15 6.5v13"/>',
            'cross' => '<circle cx="8" cy="8" r="3"/><circle cx="16" cy="16" r="3"/><path d="M10.2 10.2 13.8 13.8"/>',
            'engine' => '<path d="M4 13h3l1.5-3h3L13 13h3l2-3h2"/><path d="M5 16h14"/><circle cx="8" cy="19" r="1.2"/><circle cx="16" cy="19" r="1.2"/>',
            'alert' => '<path d="M12 4 21 19H3z"/><path d="M12 10v4M12 16.5h.01"/>',
            'lab' => '<path d="M9 3h6M10 3v6l-5 9a2 2 0 0 0 1.7 3h10.6a2 2 0 0 0 1.7-3l-5-9V3"/><path d="M8.2 14h7.6"/>',
            'prepare' => '<path d="M4 19h16M7 16 12 5l5 11"/><path d="M9.2 12h5.6"/>',
            'collect' => '<path d="M4 7h16v11a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2z"/><path d="M8 7V5.5A1.5 1.5 0 0 1 9.5 4h5A1.5 1.5 0 0 1 16 5.5V7"/><path d="M12 11v4M10 13h4"/>',
            'target' => '<circle cx="12" cy="12" r="8"/><circle cx="12" cy="12" r="4"/><path d="M12 2v3M12 19v3M2 12h3M19 12h3"/>',
            'queue' => '<path d="M5 7h14M5 12h14M5 17h9"/><path d="M17 15.5 19.5 18 22 14"/>',
            'report' => '<path d="M5 4h10l4 4v12H5z"/><path d="M15 4v4h4M8 13h8M8 16.5h5"/>',
            'write' => '<path d="M4 19.5 5.5 14 16 3.5 20 7.5 9.5 18z"/><path d="M13.5 6 17.5 10"/>',
            'paper' => '<rect x="5" y="3.5" width="14" height="17" rx="1"/><path d="M5 7.5h14M5 16.5h14"/>',
            'note' => '<path d="M6 3.5h9l4 4V19a1.5 1.5 0 0 1-1.5 1.5h-11A1.5 1.5 0 0 1 5 19V5a1.5 1.5 0 0 1 1-1.5z"/><path d="M15 3.5V8h4"/><path d="M8.5 12h7M8.5 15.5h4.5"/>',
            'library' => '<path d="M5 4h4v16H5zM10.5 4H15v16h-4.5zM16.5 6.5 21 5v14.5L16.5 21z"/>',
            'flag' => '<path d="M5 21V4M5 4h11l-2 4 2 4H5"/>',
            'dice' => '<rect x="4" y="4" width="16" height="16" rx="3"/><path d="M8.5 8.5h.01M15.5 15.5h.01M12 12h.01M15.5 8.5h.01M8.5 15.5h.01"/>',
            'admin' => '<path d="M12 3.5 19 7v5.2c0 4.2-2.8 7.4-7 8.8-4.2-1.4-7-4.6-7-8.8V7z"/><path d="M9.5 12.2 11.2 14l3.5-3.8"/>',
            'help' => '<circle cx="12" cy="12" r="8.2"/><path d="M9.6 9.4a2.5 2.5 0 1 1 3.6 2.2c-.8.4-1.2.9-1.2 1.7V14"/><path d="M12 17h.01"/>',
            'search' => '<circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/>',
            'transfer' => '<path d="M4 8h13l-3-3M20 16H7l3 3"/>',
            default => '<circle cx="12" cy="12" r="7"/>',
        };

        return '<svg' . $common . '>' . $paths . '</svg>';
    }

    /**
     * Entrées de la palette de commandes : pages + actions rapides, aplaties.
     *
     * @param list<array<string, mixed>> $sections
     * @return list<array{label: string, hint: string, href: string, group: string, keywords: string}>
     */
    public static function paletteEntries(array $sections, callable $url, bool $canManage, bool $isGuest): array
    {
        $out = [];
        if (!$isGuest) {
            $out[] = ['label' => 'Rédiger une fiche de renseignement', 'hint' => 'Nouvelle note terrain', 'href' => $url('atak/sse/fiches/nouvelle'), 'group' => 'Actions', 'keywords' => 'nouvelle fiche note'];
        }
        if ($canManage) {
            $out[] = ['label' => 'Ouvrir une investigation', 'hint' => 'Nouvelle toile relationnelle', 'href' => $url('atak/sse/toiles/nouveau'), 'group' => 'Actions', 'keywords' => 'nouvelle toile graphe'];
            $out[] = ['label' => 'Créer un objet', 'hint' => 'Identité, site, véhicule, matériel…', 'href' => $url('atak/sse/objets/nouveau'), 'group' => 'Actions', 'keywords' => 'nouveau objet identite'];
            $out[] = ['label' => 'Nouveau dossier', 'hint' => 'Affaire structurée', 'href' => $url('atak/sse/dossiers/nouveau'), 'group' => 'Actions', 'keywords' => 'creer dossier affaire'];
        }
        foreach ($sections as $section) {
            foreach ($section['items'] as $item) {
                $out[] = [
                    'label' => (string) $item['label'],
                    'hint' => (string) $item['hint'],
                    'href' => $url((string) $item['path']),
                    'group' => (string) $section['label'],
                    'keywords' => (string) ($item['keywords'] ?? ''),
                ];
            }
        }

        return $out;
    }
}
