<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Navigation du bureau SSE, définie une seule fois et rangée selon le cycle du renseignement :
 * piloter → collecter → instruire → référencer → analyser → produire → préparer.
 *
 * Utilisée par la barre latérale et par la palette de commandes (Ctrl+K). L'élément actif se
 * déduit de l'URL demandée (correspondance la plus longue), ce qui couvre aussi les pages de
 * détail (/dossiers/12 → « Dossiers validés ») et les registres d'objets (/objets/vehicules).
 */
final class SsePortalNav
{
    /**
     * @return list<array{id: string, label: string, items: list<array{id: string, label: string, hint: string, path: string, icon: string, external?: bool, aliases?: list<string>, keywords?: string}>}>
     */
    public static function sections(bool $canManage, bool $canGrant, bool $isGuest = false, bool $canMemberPapers = false, bool $canEditorLibrary = false): array
    {
        $sections = [
            ['id' => 'pilotage', 'label' => 'Pilotage', 'items' => [
                ['id' => 'operations', 'label' => 'Vue opérationnelle', 'hint' => 'Situation du jour : priorités, file opérateur, alertes.', 'path' => 'atak/sse/operations', 'icon' => 'ops', 'keywords' => 'accueil tableau de bord situation'],
                ['id' => 'workspace', 'label' => 'Espace d’exploitation', 'hint' => 'Boîte de réception, cycle du renseignement et constats d’analyse.', 'path' => 'atak/sse/workspace', 'icon' => 'inbox', 'keywords' => 'intelligence workspace inbox cycle'],
            ]],
            ['id' => 'collecte', 'label' => 'Collecte', 'items' => [
                ['id' => 'transmissions', 'label' => 'Transmissions terrain', 'hint' => 'Envois reçus d’Arma 3 : fiches, biométrie, sites, relevés.', 'path' => 'atak/sse/transmissions', 'icon' => 'collect', 'keywords' => 'arma envois seek'],
                ['id' => 'fiches', 'label' => 'Fiches de renseignement', 'hint' => 'Notes libres remontées du terrain, avec pièces jointes.', 'path' => 'atak/sse/fiches', 'icon' => 'note', 'keywords' => 'notes terrain'],
                ['id' => 'collecte', 'label' => 'Demandes de collecte', 'hint' => 'Ce qu’il reste à collecter et où le déposer.', 'path' => 'atak/sse/collecte', 'icon' => 'target', 'keywords' => 'collecte besoins'],
                ['id' => 'labnum', 'label' => 'Exploitation numérique', 'hint' => 'Téléphones, ordinateurs et supports saisis.', 'path' => 'atak/sse/exploitation-numerique', 'icon' => 'lab', 'keywords' => 'domex telephone ordinateur supports'],
            ]],
            ['id' => 'dossiers', 'label' => 'Dossiers', 'items' => [
                ['id' => 'interet', 'label' => 'Dossiers d’intérêt', 'hint' => 'Signalements à qualifier : hypothèses, pas encore d’identité certaine.', 'path' => 'atak/sse/interet', 'icon' => 'interest', 'keywords' => 'pressee signalements'],
                ['id' => 'dossiers', 'label' => 'Dossiers validés', 'hint' => 'Affaires structurées : preuves, notes, personnel, comptes rendus.', 'path' => 'atak/sse/dossiers', 'icon' => 'folder', 'keywords' => 'affaires cases preuves'],
                ['id' => 'toiles', 'label' => 'Investigations', 'hint' => 'Toiles relationnelles : relier personnes, lieux, objets.', 'path' => 'atak/sse/toiles', 'icon' => 'mesh', 'keywords' => 'graphe relationnel toiles reseau liens'],
            ]],
            ['id' => 'referentiel', 'label' => 'Référentiel', 'items' => [
                ['id' => 'identites', 'label' => 'Identités', 'hint' => 'Fiches personnes et indices biométriques.', 'path' => 'atak/sse/identites', 'icon' => 'id', 'aliases' => ['atak/sse/personnes'], 'keywords' => 'personnes biometrie'],
                ['id' => 'organisations', 'label' => 'Organisations', 'hint' => 'Groupes, cellules et structures affiliées.', 'path' => 'atak/sse/objets/organisations', 'icon' => 'org'],
                ['id' => 'sites', 'label' => 'Sites', 'hint' => 'Lieux d’intérêt et d’exploitation.', 'path' => 'atak/sse/sites', 'icon' => 'site', 'keywords' => 'lieux'],
                ['id' => 'vehicules', 'label' => 'Véhicules', 'hint' => 'Moyens mobiles rattachés aux dossiers.', 'path' => 'atak/sse/objets/vehicules', 'icon' => 'vehicle'],
                ['id' => 'materiels', 'label' => 'Matériels', 'hint' => 'Équipements et objets saisis.', 'path' => 'atak/sse/objets/materiels', 'icon' => 'gear', 'keywords' => 'armes saisies'],
                ['id' => 'pieces', 'label' => 'Pièces documentaires', 'hint' => 'Documents physiques ou numérisés versés au dossier.', 'path' => 'atak/sse/objets/documents', 'icon' => 'doc'],
            ]],
            ['id' => 'identite', 'label' => 'Contrôle d’identité', 'items' => array_values(array_filter([
                ['id' => 'papiers-tableau', 'label' => 'Tableau de bord', 'hint' => 'Contrôles, faux détectés et recherchés interpellés par opération.', 'path' => 'atak/sse/papiers/tableau', 'icon' => 'ops', 'keywords' => 'identite controle tableau bord operation faux recherches'],
                ['id' => 'papiers', 'label' => 'Papiers', 'hint' => 'Registre des papiers d’identité fictifs émis pour le jeu.', 'path' => 'atak/sse/papiers', 'icon' => 'doc', 'aliases' => ['atak/sse/papiers/documents'], 'keywords' => 'papiers passeport carte permis registre mrz documents identite'],
                ['id' => 'papiers-identites', 'label' => 'Identités', 'hint' => 'Identités fictives (réelles, de couverture, falsifiées) portées par les papiers.', 'path' => 'atak/sse/papiers/identites', 'icon' => 'id', 'keywords' => 'identites fictives couverture alias faux'],
                ['id' => 'papiers-controles', 'label' => 'Contrôles reçus', 'hint' => 'Contrôles d’identité transmis depuis le terrain.', 'path' => 'atak/sse/papiers/controles', 'icon' => 'queue', 'keywords' => 'controles identite verdict terrain checkpoint'],
                $canMemberPapers ? ['id' => 'papiers-membres', 'label' => 'Papiers des membres', 'hint' => 'Identité principale, alias et kit de papiers des membres.', 'path' => 'atak/sse/papiers/membres', 'icon' => 'org', 'keywords' => 'membres papiers kit alias identite principale'] : null,
                ['id' => 'papiers-pays', 'label' => 'Pays émetteurs', 'hint' => 'Pays imaginaires qui émettent les papiers.', 'path' => 'atak/sse/papiers/pays', 'icon' => 'flag', 'keywords' => 'pays emetteurs drapeaux nationalite'],
                $canMemberPapers ? ['id' => 'papiers-atelier', 'label' => 'Atelier de documents', 'hint' => 'Composer un papier avec aperçu recto, verso et pages de passeport.', 'path' => 'atak/sse/papiers/atelier', 'icon' => 'doc', 'keywords' => 'atelier document apercu passeport carte permis export png'] : null,
                $canMemberPapers ? ['id' => 'papiers-kits', 'label' => 'Kits types', 'hint' => 'Modèles de kits (civil, militaire, journaliste, agent infiltré…) à affecter aux membres.', 'path' => 'atak/sse/papiers/kits-types', 'icon' => 'org', 'keywords' => 'kits types modeles civil militaire diplomatique journaliste contractor infiltre'] : null,
                $canMemberPapers ? ['id' => 'papiers-profils-ia', 'label' => 'Profils IA', 'hint' => 'Identité, camp, papiers et comportement à relier aux IA en jeu (Zeus, Eden).', 'path' => 'atak/sse/papiers/profils-ia', 'icon' => 'person', 'keywords' => 'ia pnj zeus eden profil population'] : null,
                $canMemberPapers ? ['id' => 'papiers-preparation', 'label' => 'Préparation de mission', 'hint' => 'Générer la population, les recherchés et les HVT d’une opération.', 'path' => 'atak/sse/papiers/preparation', 'icon' => 'ops', 'keywords' => 'preparation mission population civils militaires suspects hvt recherches generation'] : null,
                $canMemberPapers ? ['id' => 'papiers-briefs', 'label' => 'Briefs de couverture', 'hint' => 'Biographie, légende et consignes remises aux joueurs couverts.', 'path' => 'atak/sse/papiers/briefs', 'icon' => 'note', 'keywords' => 'briefs couverture legende biographie consignes'] : null,
                $canMemberPapers ? ['id' => 'papiers-echanges', 'label' => 'Import et export', 'hint' => 'Importer ou exporter identités et papiers (CSV, JSON).', 'path' => 'atak/sse/papiers/echanges', 'icon' => 'queue', 'keywords' => 'import export csv json identites papiers'] : null,
                ['id' => 'papiers-frm', 'label' => 'FRM à valider', 'hint' => 'Brouillons de fiches FRM nés des contrôles, à valider.', 'path' => 'atak/sse/papiers/tableau#frm', 'icon' => 'note', 'keywords' => 'frm brouillons valider fiches controles'],
            ]))],
            ['id' => 'analyse', 'label' => 'Analyse', 'items' => [
                ['id' => 'chronologie', 'label' => 'Chronologie', 'hint' => 'Faits et événements dans l’ordre.', 'path' => 'atak/sse/chronologie', 'icon' => 'time', 'keywords' => 'timeline'],
                ['id' => 'croisements', 'label' => 'Croisements', 'hint' => 'Rapprochements proposés entre dossiers, à valider.', 'path' => 'atak/sse/croisements', 'icon' => 'cross'],
                ['id' => 'rapprochements', 'label' => 'Suggestions du moteur', 'hint' => 'Liens trouvés automatiquement, à accepter ou rejeter.', 'path' => 'atak/sse/rapprochements', 'icon' => 'engine', 'keywords' => 'rapprochements moteur'],
                ['id' => 'anomalies', 'label' => 'Anomalies', 'hint' => 'Écarts et incohérences à examiner.', 'path' => 'atak/sse/anomalies', 'icon' => 'alert'],
                ['id' => 'carte', 'label' => 'Carte ATAK', 'hint' => 'Projection géographique (ouvre l’ATAK).', 'path' => 'atak', 'icon' => 'map', 'external' => true],
            ]],
            ['id' => 'production', 'label' => 'Production', 'items' => [
                ['id' => 'validation', 'label' => 'Files de validation', 'hint' => 'Décisions en attente d’arbitrage.', 'path' => 'atak/sse/validation', 'icon' => 'queue'],
                ['id' => 'documents', 'label' => 'Rédaction', 'hint' => 'Notes, flash et documents officiels.', 'path' => 'atak/sse/documents', 'icon' => 'write', 'keywords' => 'documents note flash'],
                ['id' => 'rapports', 'label' => 'Rapports', 'hint' => 'Productions finales et bilans du bureau.', 'path' => 'atak/sse/rapports', 'icon' => 'report'],
                ['id' => 'bibliotheque', 'label' => 'Mentions officielles', 'hint' => 'Formulations validées à réutiliser.', 'path' => 'atak/sse/bibliotheque', 'icon' => 'library'],
                ['id' => 'presentation', 'label' => 'Présentation', 'hint' => 'Bandeau, pied de page et papier des documents.', 'path' => 'atak/sse/presentation', 'icon' => 'paper'],
            ]],
            ['id' => 'preparation', 'label' => 'Préparation', 'items' => [
                ['id' => 'campagnes', 'label' => 'Campagnes', 'hint' => 'Personnages, organisations et scellés du mod, partagés entre communautés.', 'path' => 'atak/sse/campagnes', 'icon' => 'flag'],
                ['id' => 'dev', 'label' => 'Modèles de mission', 'hint' => 'Créer et emporter des modèles pour Arma.', 'path' => 'atak/sse/dev', 'icon' => 'prepare'],
            ]],
        ];
        if ($canEditorLibrary) {
            $sections[(int) array_search('preparation', array_column($sections, 'id'), true)]['items'][] = ['id' => 'editeur', 'label' => 'Bibliothèque de l’éditeur', 'hint' => 'Contenus de l’éditeur SSE du jeu, en lecture seule.', 'path' => 'atak/sse/editeur', 'icon' => 'library', 'keywords' => 'editeur mod renseignements identites papiers kits dossiers contenus'];
        }
        if ($canManage) {
            $sections[(int) array_search('preparation', array_column($sections, 'id'), true)]['items'][] = ['id' => 'maitre-jeu', 'label' => 'Maître du jeu', 'hint' => 'Identités, listes et histoires lues par le terminal SEEK.', 'path' => 'atak/sse/maitre-jeu', 'icon' => 'dice', 'keywords' => 'zeus seek'];
        }
        if ($canGrant && !$isGuest) {
            $sections[] = ['id' => 'administration', 'label' => 'Administration', 'items' => [
                ['id' => 'acces', 'label' => 'Habilitations et codes', 'hint' => 'Codes d’accès temporaires et niveaux de diffusion.', 'path' => 'atak/sse/acces', 'icon' => 'admin', 'keywords' => 'codes acces administration'],
            ]];
        }
        $sections[] = ['id' => 'aide', 'label' => 'Aide', 'items' => [
            ['id' => 'guide', 'label' => 'Documentation', 'hint' => 'Mode d’emploi du bureau SSE.', 'path' => 'atak/sse/guide', 'icon' => 'help', 'aliases' => ['atak/sse/documentation'], 'keywords' => 'aide guide'],
        ]];

        return $sections;
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
                    $pos = strpos($path, $needle);
                    // Le chemin d'un élément doit terminer la base du site (…/atak/sse/xxx/), pas apparaître au milieu.
                    if ($pos !== false && strlen($needle) > $bestLen) {
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

    /** Section qui contient l'élément actif (ouverte par défaut dans la barre latérale). */
    public static function sectionOf(array $sections, string $itemId): string
    {
        foreach ($sections as $section) {
            foreach ($section['items'] as $item) {
                if ($item['id'] === $itemId) {
                    return (string) $section['id'];
                }
            }
        }

        return '';
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
