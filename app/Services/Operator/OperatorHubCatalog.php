<?php

declare(strict_types=1);

namespace App\Services\Operator;

/**
 * Page « Outils opérateur » (/operateur) : la liste des outils est une donnée.
 *
 * Ajouter un outil = une ligne dans TOOLS :
 *   key (stable : sert aux favoris), group, label, desc, path (sans barre initiale), icon (clé du jeu d'icônes de
 *   views/operateur/_icons.php), et au besoin :
 *   - any_permissions : l'un de ces droits suffit (sinon tout membre connecté) ;
 *   - route : chemin qui doit être déclaré dans routes/web.php (outil d'une autre branche, affiché dès qu'elle arrive) ;
 *   - keywords : mots de recherche en plus du libellé et de la description.
 * Un outil que le membre ne peut pas ouvrir n'est pas listé (aucun lien mort).
 */
final class OperatorHubCatalog
{
    /** Droits qui ouvrent le Bureau SSE (mêmes que le menu « Opérateur » de config/navigation.php). */
    private const SSE = ['atak.sse.access', 'atak.sse.grant', 'atak.sse.case.manage', 'admin.access', 'admin.organization'];
    private const TOC = ['atak.toc.use', 'atak.toc.manage', 'admin.organization'];

    public const GROUPS = [
        'operation' => ['label' => 'En opération', 'desc' => 'Ce qui sert pendant la mission.'],
        'identite' => ['label' => 'Mon identité', 'desc' => 'Qui vous êtes en jeu : identités, papiers et couvertures.'],
        'materiel' => ['label' => 'Mon matériel', 'desc' => 'Ce que vous portez.'],
        'preparation' => ['label' => 'Préparation', 'desc' => 'Avant de partir.'],
        'comptes_rendus' => ['label' => 'Comptes rendus', 'desc' => 'Ce que vous rapportez du terrain.'],
    ];

    public const TOOLS = [
        ['key' => 'atak', 'group' => 'operation', 'label' => 'Situation tactique', 'desc' => 'Carte, positions et comptes rendus de la mission, comme sur le téléphone.', 'path' => 'atak', 'icon' => 'map', 'keywords' => 'atak cop carte web'],
        ['key' => 'toc', 'group' => 'operation', 'label' => 'Poste TOC', 'desc' => 'La console du poste de commandement.', 'path' => 'atak/toc', 'icon' => 'radar', 'any_permissions' => self::TOC, 'route' => 'atak/toc', 'keywords' => 'pc commandement'],
        ['key' => 'lier', 'group' => 'operation', 'label' => 'Lier mon ATAK', 'desc' => 'Relier le téléphone du jeu à votre compte Athena.', 'path' => 'lier', 'icon' => 'link', 'keywords' => 'code liaison téléphone'],
        ['key' => 'fichiers', 'group' => 'operation', 'label' => 'Passage aux fichiers', 'desc' => 'Interroger les fichiers : nom, alias, plaque, papier, téléphone.', 'path' => 'atak/sse/fichiers', 'icon' => 'search', 'any_permissions' => self::SSE, 'keywords' => 'sse contrôle recherche'],
        ['key' => 'seek', 'group' => 'operation', 'label' => 'Fiches SEEK II', 'desc' => 'Relevés biométriques faits en jeu.', 'path' => 'atak/sse/fichiers/seek', 'icon' => 'fingerprint', 'any_permissions' => self::SSE, 'keywords' => 'biométrie empreinte'],
        ['key' => 'telephones', 'group' => 'operation', 'label' => 'Téléphones du jeu', 'desc' => 'Extractions des téléphones synchronisés, les vôtres et celles de l’équipe.', 'path' => 'atak/sse/fichiers/telephones', 'icon' => 'phone', 'any_permissions' => self::SSE, 'keywords' => 'extraction'],
        ['key' => 'photos', 'group' => 'operation', 'label' => 'Photothèque', 'desc' => 'Photos signalétiques filtrées par signalement.', 'path' => 'atak/sse/fichiers/photos', 'icon' => 'camera', 'any_permissions' => self::SSE, 'keywords' => 'photo signalement'],
        ['key' => 'bureau_sse', 'group' => 'operation', 'label' => 'Bureau SSE', 'desc' => 'L’accueil du renseignement : dossiers, personnes, lieux et recherche.', 'path' => 'atak/sse/accueil', 'icon' => 'files', 'any_permissions' => self::SSE, 'keywords' => 'renseignement dossiers sse accueil'],
        ['key' => 'appareils', 'group' => 'operation', 'label' => 'Appareils ATAK', 'desc' => 'Vos téléphones du jeu reliés et vos codes de sécurité.', 'path' => 'account/security/devices', 'icon' => 'lock', 'keywords' => 'codes sécurité terminal appareil'],
        ['key' => 'terrain', 'group' => 'operation', 'label' => 'Mode terrain', 'desc' => 'Page allégée pour le téléphone : liens de la mission, sans menus.', 'path' => 'operateur/terrain', 'icon' => 'smartphone', 'keywords' => 'mobile'],

        ['key' => 'identites', 'group' => 'identite', 'label' => 'Mes identités', 'desc' => 'Identité principale, alias, photo et signalement.', 'path' => 'operateur/identite', 'icon' => 'user', 'keywords' => 'nom photo nationalité alias'],
        ['key' => 'documents', 'group' => 'identite', 'label' => 'Mes papiers', 'desc' => 'Carte, passeport, permis : aperçu, demande, perte ou vol.', 'path' => 'operateur/identite/documents', 'icon' => 'id', 'keywords' => 'documents passeport carte permis'],
        ['key' => 'couverture', 'group' => 'identite', 'label' => 'Mes couvertures', 'desc' => 'Légendes à jouer : créer, modifier, télécharger pour le jeu.', 'path' => 'operateur/identite/couverture', 'icon' => 'mask', 'keywords' => 'légende cover infiltration'],

        ['key' => 'materiel', 'group' => 'materiel', 'label' => 'Mon matériel', 'desc' => 'Vos fiches matériel et ce qui vous est attribué.', 'path' => 'equipment?espace=materiel', 'icon' => 'box', 'keywords' => 'équipement arsenal'],
        ['key' => 'kits', 'group' => 'materiel', 'label' => 'Tenues et kits', 'desc' => 'Composer et retrouver vos tenues.', 'path' => 'equipment?espace=kits', 'icon' => 'shirt', 'keywords' => 'tenue kit loadout'],

        ['key' => 'plans', 'group' => 'preparation', 'label' => 'Plans d’opération', 'desc' => 'Préparer un plan sur la carte et le briefer par phases.', 'path' => 'atak/toc/plans', 'icon' => 'route', 'any_permissions' => self::TOC, 'route' => 'atak/toc/plans', 'keywords' => 'plan briefing'],
        ['key' => 'manoeuvres', 'group' => 'preparation', 'label' => 'Manœuvres', 'desc' => 'Calendrier des opérations et inscriptions.', 'path' => 'evenements', 'icon' => 'calendar', 'keywords' => 'événements opérations calendrier'],
        ['key' => 'guides', 'group' => 'preparation', 'label' => 'Guides', 'desc' => 'Mode d’emploi du portail, de l’ATAK et des fichiers.', 'path' => 'documentation', 'icon' => 'book', 'keywords' => 'aide documentation'],
        ['key' => 'guide_sse', 'group' => 'preparation', 'label' => 'Guide du Bureau SSE', 'desc' => 'Fichiers, contrôles d’identité, SEEK II et téléphones.', 'path' => 'atak/sse/guide', 'icon' => 'book', 'any_permissions' => self::SSE, 'keywords' => 'aide sse'],
        ['key' => 'academy', 'group' => 'preparation', 'label' => 'Mes formations', 'desc' => 'Votre parcours dans l’Academy.', 'path' => 'academy/courses', 'icon' => 'cap', 'keywords' => 'academy formation cours'],

        ['key' => 'fiche', 'group' => 'comptes_rendus', 'label' => 'Rédiger une fiche de renseignement', 'desc' => 'Ce que vous avez vu ou appris sur le terrain.', 'path' => 'atak/sse/fiches/nouvelle', 'icon' => 'pen', 'any_permissions' => self::SSE, 'keywords' => 'cr compte rendu rapport'],
        ['key' => 'fiches', 'group' => 'comptes_rendus', 'label' => 'Fiches de renseignement', 'desc' => 'Les fiches déjà rédigées et leur suivi.', 'path' => 'atak/sse/fiches', 'icon' => 'files', 'any_permissions' => self::SSE, 'keywords' => 'cr comptes rendus'],
    ];

    /**
     * Outils que ce membre peut ouvrir.
     *
     * @param callable(string): bool $can        droit du membre (Gate)
     * @param callable(string): bool $allowsPath politique du menu pour un chemin (profil de communauté, sanctions, modules)
     * @param callable(string): bool|null $routeDeclared null : lecture de routes/web.php
     * @return list<array<string, mixed>>
     */
    public static function visible(callable $can, callable $allowsPath, ?callable $routeDeclared = null): array
    {
        $routeDeclared ??= [self::class, 'routeDeclared'];
        $out = [];
        foreach (self::TOOLS as $tool) {
            if (isset($tool['route']) && !$routeDeclared((string) $tool['route'])) {
                continue;
            }
            $perms = $tool['any_permissions'] ?? [];
            if ($perms !== []) {
                $ok = false;
                foreach ($perms as $p) {
                    if ($can($p)) {
                        $ok = true;
                        break;
                    }
                }
                if (!$ok) {
                    continue;
                }
            }
            if (!$allowsPath((string) strtok((string) $tool['path'], '?'))) {
                continue;
            }
            $out[] = $tool;
        }

        return $out;
    }

    /**
     * Droit qui ouvre un outil réservé, en clair (affiché sur une tuile grisée : « Accès réservé : … »).
     * Le premier droit de any_permissions est celui d'un simple utilisateur de l'outil : c'est lui qui se demande.
     */
    public const PERMISSION_LABELS = [
        'atak.sse.access' => 'Bureau SSE',
        'atak.sse.case.manage' => 'Dossiers du Bureau SSE',
        'atak.sse.grant' => 'Codes d’accès au Bureau SSE',
        'atak.sse.export' => 'Export des dossiers SSE',
        'atak.sse.files.admin' => 'Gestion des fichiers SSE',
        'atak.sse.lore.manage' => 'Lore des sessions',
        'atak.toc.use' => 'Poste TOC',
        'atak.toc.manage' => 'Réglages du poste TOC',
    ];

    /**
     * Ce que chaque droit des outils opérateur permet, en une ligne avec un exemple. Montré dans la fenêtre de demande
     * d'accès et, pour le responsable, à côté de la case du droit dans « Demandes d'élévation ».
     */
    public const PERMISSION_HELP = [
        'atak.sse.access' => 'Ouvrir le Bureau SSE et ses fichiers. Exemple : contrôler une plaque, lire une fiche SEEK II, rédiger une fiche de renseignement.',
        'atak.sse.case.manage' => 'Créer et tenir les dossiers de renseignement. Exemple : ouvrir un dossier sur une cellule et y rattacher des personnes.',
        'atak.sse.grant' => 'Donner un code d’accès temporaire au Bureau SSE. Exemple : ouvrir le bureau à un invité le temps d’une mission.',
        'atak.sse.export' => 'Sortir un dossier de renseignement en PDF. Exemple : imprimer le dossier d’une cible pour le briefing.',
        'atak.sse.files.admin' => 'Gérer les fichiers SSE depuis le back-office. Exemple : fusionner deux fiches de la même personne, lire le journal des consultations.',
        'atak.sse.lore.manage' => 'Préparer le lore d’une session comme maître du jeu. Exemple : choisir ce que les joueurs savent d’une personne au début de la mission.',
        'atak.toc.use' => 'Tenir le poste TOC web et préparer des plans d’opération. Exemple : suivre la carte commune et briefer un plan par phases.',
        'atak.toc.manage' => 'Régler le poste TOC web. Exemple : envoyer un plan en jeu, modifier les modèles de la communauté.',
    ];

    /**
     * Tous les outils de la communauté, avec « locked » : vrai quand il manque un droit au membre (tuile grisée, non
     * cliquable, droit à demander dans « needs »). Un outil dont le module est fermé à la communauté, ou dont la route
     * n'existe pas, n'est pas listé du tout : il n'y a rien à demander.
     *
     * @param callable(string): bool $can
     * @param callable(string): bool $allowsPath
     * @param callable(string): bool|null $routeDeclared
     * @return list<array<string, mixed>>
     */
    public static function withAccess(callable $can, callable $allowsPath, ?callable $routeDeclared = null): array
    {
        $routeDeclared ??= [self::class, 'routeDeclared'];
        $out = [];
        foreach (self::TOOLS as $tool) {
            if (isset($tool['route']) && !$routeDeclared((string) $tool['route'])) {
                continue;
            }
            $perms = $tool['any_permissions'] ?? [];
            $granted = $perms === [];
            foreach ($perms as $p) {
                $granted = $granted || $can($p);
            }
            // Le chemin se juge sans droit manquant : un membre sans le droit verrait sinon l'outil disparaître.
            if ($granted && !$allowsPath((string) strtok((string) $tool['path'], '?'))) {
                continue;
            }
            $tool['locked'] = !$granted;
            $tool['needs'] = $granted ? '' : (string) $perms[0];
            $tool['needs_label'] = $granted ? '' : (self::PERMISSION_LABELS[$perms[0]] ?? (string) $perms[0]);
            $out[] = $tool;
        }

        return $out;
    }

    /**
     * Outils rangés pour la page : favoris en tête (dans l'ordre où ils ont été épinglés), puis par groupe. Un
     * favori dont l'outil n'est plus accessible est ignoré. Un groupe vide n'est pas rendu.
     *
     * @param list<array<string, mixed>> $tools  self::visible()
     * @param list<string> $favorites
     * @param array<string, array{label: string, tone: string}> $badges par clé d'outil
     * @return array{favorites: list<array<string, mixed>>, groups: list<array{key: string, label: string, desc: string, tools: list<array<string, mixed>>}>}
     */
    public static function layout(array $tools, array $favorites, array $badges = []): array
    {
        $byKey = [];
        foreach ($tools as $t) {
            $t['favorite'] = in_array($t['key'], $favorites, true);
            $t['badge'] = $badges[$t['key']] ?? null;
            $byKey[$t['key']] = $t;
        }
        $fav = [];
        foreach ($favorites as $k) {
            if (isset($byKey[$k]) && empty($byKey[$k]['locked'])) {
                $fav[] = $byKey[$k];
            }
        }
        $groups = [];
        foreach (self::GROUPS as $gKey => $g) {
            $list = array_values(array_filter($byKey, static fn (array $t): bool => $t['group'] === $gKey));
            if ($list !== []) {
                $groups[] = ['key' => $gKey, 'label' => $g['label'], 'desc' => $g['desc'], 'tools' => $list];
            }
        }

        return ['favorites' => $fav, 'groups' => $groups];
    }

    /**
     * Tuile « Outils opérateur » du menu Espaces de l'en-tête (pastille OPR, en bleu pour la distinguer des espaces) :
     * un membre connecté d'une communauté a toujours au moins un outil (Mon identité) ; un visiteur n'a pas la tuile.
     *
     * @return list<array{abbr: string, label: string, desc: string, href: string, path: string, tone: string}>
     */
    public static function spaceTiles(bool $member): array
    {
        if (!$member) {
            return [];
        }

        return [[
            'abbr' => 'OPR',
            'label' => 'Outils opérateur',
            'desc' => 'Identité, papiers, couverture, fichiers',
            'href' => function_exists('url') ? url('operateur') : '/operateur',
            'path' => 'operateur',
            'tone' => 'blue',
        ]];
    }

    public static function isKnown(string $key): bool
    {
        foreach (self::TOOLS as $t) {
            if ($t['key'] === $key) {
                return true;
            }
        }

        return false;
    }

    /** Le chemin est-il déclaré dans routes/web.php ? (outils livrés par une autre branche) */
    public static function routeDeclared(string $path): bool
    {
        static $routes = null;
        if ($routes === null) {
            $routes = (string) @file_get_contents(dirname(__DIR__, 3) . '/routes/web.php');
        }

        return str_contains($routes, "'/" . trim($path, '/') . "'");
    }
}
