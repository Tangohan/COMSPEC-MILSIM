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
    public static function sections(bool $canManage, bool $canGrant, bool $isGuest = false): array
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
        if ($canManage) {
            $sections[6]['items'][] = ['id' => 'maitre-jeu', 'label' => 'Maître du jeu', 'hint' => 'Identités, listes et histoires lues par le terminal SEEK.', 'path' => 'atak/sse/maitre-jeu', 'icon' => 'dice', 'keywords' => 'zeus seek'];
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
     * @param list<array<string, mixed>> $sections
     */
    public static function activeId(array $sections, string $requestPath, string $activeNav = ''): string
    {
        $path = '/' . trim((string) (parse_url($requestPath, PHP_URL_PATH) ?? ''), '/') . '/';
        $best = '';
        $bestLen = 0;
        foreach ($sections as $section) {
            foreach ($section['items'] as $item) {
                if (!empty($item['external'])) {
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
