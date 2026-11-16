<?php

declare(strict_types=1);

namespace App\Services\BackOffice\Home;

/**
 * Tableau de bord du back-office en widgets : catalogue, disposition par personne et par communauté, placement sur la
 * grille. Calculs purs ; la disposition est enregistrée en JSON versionné dans bo_user_nav_prefs.home_json.
 *
 * Grille : 12 colonnes sur grand écran, 6 sur tablette, 2 sur téléphone ; une rangée a une hauteur fixe. Chaque widget
 * a une taille (colonnes × rangées) bornée par son minimum et son maximum. Le placement reprend celui de la grille CSS
 * en « dense » (premier emplacement libre, de haut en bas puis de gauche à droite), puis comble les trous restants en
 * élargissant ou en allongeant un voisin : plus aucun vide sur la grille. Un widget vide (aucune donnée) passe en
 * version compacte d'une rangée, regroupée en fin de grille, au lieu d'occuper une rangée entière.
 *
 * JSON enregistré : {"v":2,"items":[{"id":"agenda","w":4,"h":3,"hidden":false}],"known":[…]}. Les dispositions v1
 * (largeur « normal|wide|full », bloc « kpis ») sont reprises sans perte. Un widget « tableau » s'écrit sheet:<id>.
 */
final class HomeLayout
{
    public const VERSION = 2;

    public const COLS = 12;
    public const COLS_TABLET = 6;
    public const COLS_MOBILE = 2;
    public const MAX_ROWS = 8;
    public const MAX_ITEMS = 60;
    public const MAX_SHEET_WIDGETS = 6;

    /** Gestionnaire (admin.organization / admin.access). */
    public const ROLE_MANAGER = 'manager';
    /** Responsable des effectifs (accès aux effectifs, sans la gestion de la communauté). */
    public const ROLE_HR = 'hr';
    /** Recruteur (accès au recrutement seulement). */
    public const ROLE_RECRUITER = 'recruiter';
    /** Assistance (site.support) et autres accès du back-office. */
    public const ROLE_SUPPORT = 'support';

    public const PATH_EFFECTIFS = 'back-office/ressources/effectifs';
    public const PATH_RECRUITMENTS = 'back-office/recruitments';

    /** Délai du widget « Disponibilité à vérifier », en jours (filtre « À vérifier » des effectifs). */
    public const AVAILABILITY_DAYS = 30;

    /**
     * Widgets disponibles. Tailles en [colonnes, rangées] sur la grille de 12 ; « right » : page dont le droit d'accès
     * conditionne le widget (null : tout le back-office).
     *
     * @return array<string, array{label: string, hint: string, def: array{0: int, 1: int}, min: array{0: int, 1: int}, max: array{0: int, 1: int}, right: ?string, kind: string}>
     */
    public static function catalog(): array
    {
        $w = static fn (string $label, string $hint, array $def, array $min, array $max, string $kind, ?string $right = null): array
            => ['label' => $label, 'hint' => $hint, 'def' => $def, 'min' => $min, 'max' => $max, 'right' => $right, 'kind' => $kind];

        return [
            'kpi_members' => $w('Effectif actif', 'Membres actifs et venus sur le site en 30 jours.', [3, 1], [2, 1], [6, 2], 'tile'),
            'kpi_presence' => $w('Présence moyenne', 'Taux de présence aux activités sur 30 jours.', [3, 1], [2, 1], [6, 2], 'tile'),
            'kpi_month' => $w('Activités du mois', 'Créneaux du mois et ceux déjà terminés.', [3, 1], [2, 1], [6, 2], 'tile'),
            'kpi_files' => $w('Dossiers complets', 'Part des membres actifs au dossier complet.', [3, 1], [2, 1], [6, 2], 'tile'),
            'operation' => $w('Prochaine opération', 'Date, lieu et présences confirmées.', [8, 2], [6, 2], [12, 3], 'band'),
            'decisions' => $w('À décider', 'Ce qui attend votre validation.', [4, 2], [3, 2], [6, 4], 'list'),
            'tasks' => $w('Tâches en attente', 'Candidatures, corrections, signalements et mise à niveau.', [8, 3], [4, 2], [12, 6], 'band'),
            'agenda' => $w('Agenda à venir', 'Les prochains créneaux de la communauté.', [4, 3], [3, 2], [6, 5], 'list'),
            'participation' => $w('Participation', 'Inscrits et présents des dernières activités.', [8, 3], [4, 2], [12, 4], 'chart'),
            'pipeline' => $w('Recrutement', 'Où en sont les candidatures.', [4, 3], [3, 2], [6, 5], 'list', self::PATH_RECRUITMENTS),
            'units' => $w('État des unités', 'Postes pourvus, qualifications et activité récente.', [4, 3], [3, 2], [6, 5], 'list'),
            'feed' => $w('Ce qui a bougé', 'Les dernières actions dans la communauté.', [4, 3], [3, 2], [8, 6], 'list'),
            'availability' => $w('Disponibilité à vérifier', 'Membres sans session de jeu qui compte depuis plusieurs jours.', [4, 2], [3, 2], [6, 5], 'list', self::PATH_EFFECTIFS),
            'shortcuts' => $w('Vos raccourcis', 'Vos pages favorites du menu.', [4, 1], [3, 1], [12, 3], 'list'),
            'usage' => $w('Connexions sur 14 jours', 'Site et téléphones ATAK, jour par jour.', [8, 2], [4, 2], [12, 4], 'chart'),
            'messages' => $w('Derniers messages', 'Vos conversations récentes.', [4, 2], [3, 2], [6, 4], 'list'),
            'sessions' => $w('Sessions de jeu récentes', 'Les dernières sessions sur le serveur de la communauté.', [6, 2], [3, 2], [8, 5], 'list', self::PATH_EFFECTIFS),
            'gaps' => $w('Dossiers à compléter', 'Fonctions, grades, rôles ou images manquants.', [6, 2], [4, 2], [12, 6], 'list'),
            'custom' => $w('Contenu de la communauté', 'Message d’accueil, consigne, vidéo ou tableau pour toute l’équipe.', [12, 2], [4, 1], [12, 6], 'content'),
        ];
    }

    /**
     * Catalogue limité aux widgets dont la personne peut voir la donnée.
     *
     * @param callable(string): bool $allowedPath droit d'accès à une page du back-office (chemin sans « / » initial)
     * @return array<string, array{label: string, hint: string, def: array{0: int, 1: int}, min: array{0: int, 1: int}, max: array{0: int, 1: int}, right: ?string, kind: string}>
     */
    public static function catalogFor(callable $allowedPath): array
    {
        return array_filter(self::catalog(), static fn (array $def): bool => $def['right'] === null || $allowedPath($def['right']));
    }

    /**
     * Rôle qui choisit la composition par défaut.
     *
     * @param callable(string): bool $allowedPath
     */
    public static function roleFor(bool $manager, callable $allowedPath): string
    {
        if ($manager) {
            return self::ROLE_MANAGER;
        }
        if ($allowedPath(self::PATH_EFFECTIFS)) {
            return self::ROLE_HR;
        }

        return $allowedPath(self::PATH_RECRUITMENTS) ? self::ROLE_RECRUITER : self::ROLE_SUPPORT;
    }

    /**
     * Composition par défaut d'un rôle : [id, colonnes, rangées]. Chaque composition remplit des rangées entières de
     * la grille de 12 (vérifié par les tests) : aucun trou quand tous les widgets ont des données.
     *
     * @return list<array{0: string, 1: int, 2: int}>
     */
    public static function preset(string $role): array
    {
        return match ($role) {
            self::ROLE_HR => [
                ['kpi_members', 3, 1], ['kpi_presence', 3, 1], ['kpi_files', 3, 1], ['kpi_month', 3, 1],
                ['tasks', 8, 3], ['availability', 4, 3],
                ['gaps', 8, 3], ['decisions', 4, 3],
                ['units', 4, 3], ['sessions', 4, 3], ['agenda', 4, 3],
                ['feed', 4, 2], ['messages', 4, 2], ['shortcuts', 4, 2],
                ['custom', 12, 2],
            ],
            self::ROLE_RECRUITER => [
                ['kpi_members', 3, 1], ['kpi_month', 3, 1], ['kpi_presence', 3, 1], ['kpi_files', 3, 1],
                ['pipeline', 4, 3], ['tasks', 8, 3],
                ['agenda', 4, 3], ['messages', 4, 3], ['feed', 4, 3],
                ['shortcuts', 12, 1],
                ['custom', 12, 2],
            ],
            self::ROLE_SUPPORT => [
                ['tasks', 8, 3], ['shortcuts', 4, 3],
                ['kpi_members', 3, 1], ['kpi_presence', 3, 1], ['kpi_month', 3, 1], ['kpi_files', 3, 1],
                ['agenda', 4, 3], ['feed', 4, 3], ['messages', 4, 3],
                ['usage', 12, 2],
                ['custom', 12, 2],
            ],
            default => [
                ['kpi_members', 3, 1], ['kpi_presence', 3, 1], ['kpi_month', 3, 1], ['kpi_files', 3, 1],
                ['operation', 8, 2], ['decisions', 4, 2],
                ['tasks', 8, 3], ['agenda', 4, 3],
                ['participation', 8, 3], ['pipeline', 4, 3],
                ['units', 4, 3], ['feed', 4, 3], ['availability', 4, 2], ['shortcuts', 4, 1],
                ['usage', 8, 2], ['messages', 4, 2],
                ['gaps', 6, 2], ['sessions', 6, 2],
                ['custom', 12, 2],
            ],
        };
    }

    /**
     * Disposition par défaut d'un rôle : les widgets du rôle visibles, les autres du catalogue masqués à la fin.
     *
     * @param list<string>|null $allowedIds widgets autorisés (null : tout le catalogue)
     * @return list<array{id: string, w: int, h: int, hidden: bool}>
     */
    public static function defaults(string $role, ?array $allowedIds = null): array
    {
        $catalog = self::catalog();
        $allowed = $allowedIds === null ? null : array_fill_keys($allowedIds, true);
        $out = [];
        $seen = [];
        foreach (self::preset($role) as [$id, $w, $h]) {
            if ($allowed !== null && !isset($allowed[$id])) {
                continue;
            }
            $out[] = ['id' => $id, 'w' => $w, 'h' => $h, 'hidden' => false];
            $seen[$id] = true;
        }
        foreach ($catalog as $id => $def) {
            if (!isset($seen[$id]) && ($allowed === null || isset($allowed[$id]))) {
                $out[] = ['id' => $id, 'w' => $def['def'][0], 'h' => $def['def'][1], 'hidden' => true];
            }
        }

        return $out;
    }

    public static function isSheet(string $id): bool
    {
        return preg_match('/^sheet:[1-9]\d{0,9}$/', $id) === 1;
    }

    public static function sheetId(string $id): int
    {
        return self::isSheet($id) ? (int) substr($id, 6) : 0;
    }

    /**
     * Bornes d'un widget (tableau compris), null s'il est inconnu.
     *
     * @return array{def: array{0: int, 1: int}, min: array{0: int, 1: int}, max: array{0: int, 1: int}}|null
     */
    public static function bounds(string $id): ?array
    {
        if (self::isSheet($id)) {
            return ['def' => [12, 4], 'min' => [6, 3], 'max' => [12, self::MAX_ROWS]];
        }
        $def = self::catalog()[$id] ?? null;

        return $def === null ? null : ['def' => $def['def'], 'min' => $def['min'], 'max' => $def['max']];
    }

    /**
     * Disposition enregistrée, nettoyée (lecture tolérante) : widgets inconnus, non autorisés et tableaux illisibles
     * retirés, tailles ramenées dans les bornes, nouveaux widgets du catalogue ajoutés à la fin. Reprend les
     * dispositions v1 (largeurs « normal|wide|full », bloc « kpis » éclaté en quatre tuiles).
     *
     * @param list<int> $readableSheets tableaux que la personne peut lire
     * @param list<string>|null $allowedIds widgets autorisés (null : tout le catalogue)
     * @return list<array{id: string, w: int, h: int, hidden: bool}>
     */
    public static function normalize(mixed $raw, string $role, array $readableSheets, ?array $allowedIds = null): array
    {
        $items = is_array($raw) && is_array($raw['items'] ?? null) ? $raw['items'] : null;
        if ($items === null) {
            return self::defaults($role, $allowedIds);
        }
        $catalog = self::catalog();
        $allowed = $allowedIds === null ? array_fill_keys(array_keys($catalog), true) : array_fill_keys($allowedIds, true);
        $readable = array_fill_keys($readableSheets, true);
        $known = is_array($raw['known'] ?? null) ? array_fill_keys(array_map('strval', $raw['known']), true) : [];
        if (isset($known['kpis'])) {
            $known += array_fill_keys(['kpi_members', 'kpi_presence', 'kpi_month', 'kpi_files'], true);
        }
        $out = [];
        $seen = [];
        $sheets = 0;
        foreach (array_slice($items, 0, self::MAX_ITEMS) as $row) {
            if (!is_array($row)) {
                continue;
            }
            $id = (string) ($row['id'] ?? '');
            // v1 : un seul bloc « Chiffres clés » pour les quatre tuiles.
            $ids = $id === 'kpis' ? ['kpi_members', 'kpi_presence', 'kpi_month', 'kpi_files'] : [$id];
            foreach ($ids as $one) {
                if ($one === '' || isset($seen[$one])) {
                    continue;
                }
                if (self::isSheet($one)) {
                    if (!isset($readable[self::sheetId($one)]) || $sheets >= self::MAX_SHEET_WIDGETS) {
                        continue;
                    }
                    $sheets++;
                } elseif (!isset($catalog[$one], $allowed[$one])) {
                    continue;
                }
                [$w, $h] = self::sizeOf($one, $row);
                $out[] = ['id' => $one, 'w' => $w, 'h' => $h, 'hidden' => !empty($row['hidden'])];
                $seen[$one] = true;
            }
        }
        foreach ($catalog as $id => $def) {
            if (!isset($seen[$id]) && isset($allowed[$id])) {
                // Widget apparu depuis le dernier enregistrement : visible ; widget retiré par la personne : masqué.
                $out[] = ['id' => $id, 'w' => $def['def'][0], 'h' => $def['def'][1], 'hidden' => isset($known[$id])];
            }
        }

        return $out;
    }

    /**
     * Disposition envoyée par le mode « Personnaliser » (lecture stricte) : tout widget inconnu, non autorisé ou de
     * taille hors bornes rejette l'ensemble, rien n'est enregistré à moitié.
     *
     * @param list<string> $allowedIds widgets autorisés pour la personne
     * @param list<int> $readableSheets
     * @return array{0: array{v: int, items: list<array{id: string, w: int, h: int, hidden: bool}>, known: list<string>}|null, 1: list<string>}
     */
    public static function validate(mixed $payload, array $allowedIds, array $readableSheets): array
    {
        if (!is_array($payload) || !is_array($payload['items'] ?? null)) {
            return [null, ['Disposition illisible.']];
        }
        if ((int) ($payload['v'] ?? 0) !== self::VERSION) {
            return [null, ['Version de disposition inconnue : rechargez la page.']];
        }
        if (count($payload['items']) > self::MAX_ITEMS) {
            return [null, ['Trop de widgets.']];
        }
        $allowed = array_fill_keys($allowedIds, true);
        $readable = array_fill_keys($readableSheets, true);
        $catalog = self::catalog();
        $errors = [];
        $out = [];
        $seen = [];
        $sheets = 0;
        foreach ($payload['items'] as $row) {
            $id = is_array($row) && is_string($row['id'] ?? null) ? $row['id'] : '';
            if ($id === '' || isset($seen[$id])) {
                $errors[] = $id === '' ? 'Widget sans identifiant.' : 'Widget en double : ' . $id . '.';
                continue;
            }
            if (self::isSheet($id)) {
                if (!isset($readable[self::sheetId($id)])) {
                    $errors[] = 'Tableau introuvable : ' . $id . '.';
                    continue;
                }
                if (empty($row['hidden']) && ++$sheets > self::MAX_SHEET_WIDGETS) {
                    $errors[] = 'Six tableaux au plus sur l’accueil.';
                    continue;
                }
            } elseif (!isset($catalog[$id])) {
                $errors[] = 'Widget inconnu : ' . $id . '.';
                continue;
            } elseif (!isset($allowed[$id])) {
                $errors[] = 'Widget non autorisé : ' . $id . '.';
                continue;
            }
            $b = self::bounds($id);
            $w = $row['w'] ?? null;
            $h = $row['h'] ?? null;
            if (!is_int($w) || !is_int($h) || $w < $b['min'][0] || $w > $b['max'][0] || $h < $b['min'][1] || $h > $b['max'][1]) {
                $errors[] = 'Taille hors limites : ' . $id . '.';
                continue;
            }
            $out[] = ['id' => $id, 'w' => $w, 'h' => $h, 'hidden' => !empty($row['hidden'])];
            $seen[$id] = true;
        }
        if ($errors !== []) {
            return [null, $errors];
        }

        return [self::stored($out), []];
    }

    /**
     * Ajoute un tableau en tête des widgets (visible, pleine largeur).
     *
     * @param list<array{id: string, w: int, h: int, hidden: bool}> $items
     * @return list<array{id: string, w: int, h: int, hidden: bool}>
     */
    public static function withSheet(array $items, int $sheetId): array
    {
        $id = 'sheet:' . $sheetId;
        $items = array_values(array_filter($items, static fn (array $r): bool => $r['id'] !== $id));
        array_unshift($items, ['id' => $id, 'w' => 12, 'h' => 4, 'hidden' => false]);

        return $items;
    }

    /**
     * @param list<array{id: string, w: int, h: int, hidden: bool}> $items
     * @return array{v: int, items: list<array{id: string, w: int, h: int, hidden: bool}>, known: list<string>}
     */
    public static function stored(array $items): array
    {
        return ['v' => self::VERSION, 'items' => array_values($items), 'known' => array_keys(self::catalog())];
    }

    /**
     * Place les widgets visibles sur les trois grilles (12, 6 et 2 colonnes), sans trou. Les widgets vides
     * (« compact ») passent sur une rangée et sont regroupés après les autres.
     *
     * @param list<array{id: string, w: int, h: int, compact?: bool}> $items dans l'ordre choisi par la personne
     * @param bool $editing mode « Personnaliser » : sur téléphone, chaque widget prend toute la largeur (barre d'outils)
     * @return list<array{id: string, compact: bool, x: int, y: int, w: int, h: int, x6: int, y6: int, w6: int, h6: int, x2: int, y2: int, w2: int}>
     *         triés dans l'ordre de lecture de la grande grille (coordonnées à partir de 1, comme en CSS)
     */
    public static function plan(array $items, bool $editing = false): array
    {
        $main = [];
        $strip = [];
        foreach ($items as $it) {
            $compact = !empty($it['compact']);
            $w = max(1, min(self::COLS, (int) $it['w']));
            $h = $compact ? 1 : max(1, min(self::MAX_ROWS, (int) $it['h']));
            $row = ['id' => (string) $it['id'], 'w' => $w, 'h' => $h, 'compact' => $compact];
            if ($compact) {
                $strip[] = $row;
            } else {
                $main[] = $row;
            }
        }
        $grids = [];
        foreach ([self::COLS, self::COLS_TABLET, self::COLS_MOBILE] as $cols) {
            $scale = static function (array $r) use ($cols, $editing): array {
                if ($cols === self::COLS_TABLET) {
                    $r['w'] = $r['w'] <= 3 ? 2 : ($r['w'] <= 6 ? 3 : 6);
                } elseif ($cols === self::COLS_MOBILE) {
                    $r['w'] = !$editing && $r['w'] <= 3 ? 1 : 2;
                    $r['h'] = 1;
                }

                return $r;
            };
            $a = self::region(array_map($scale, $main), $cols, $cols !== self::COLS_MOBILE);
            $bottom = 0;
            foreach ($a as $p) {
                $bottom = max($bottom, $p['y'] + $p['h'] - 1);
            }
            $b = self::region(array_map($scale, $strip), $cols, false);
            foreach ($b as &$p) {
                $p['y'] += $bottom;
            }
            unset($p);
            foreach (array_merge($a, $b) as $p) {
                $grids[$cols][$p['id']] = $p;
            }
        }
        $out = [];
        foreach ($grids[self::COLS] as $id => $p) {
            $t = $grids[self::COLS_TABLET][$id];
            $m = $grids[self::COLS_MOBILE][$id];
            $out[] = [
                'id' => $id, 'compact' => (bool) $p['compact'],
                'x' => $p['x'], 'y' => $p['y'], 'w' => $p['w'], 'h' => $p['h'],
                'x6' => $t['x'], 'y6' => $t['y'], 'w6' => $t['w'], 'h6' => $t['h'],
                'x2' => $m['x'], 'y2' => $m['y'], 'w2' => $m['w'],
            ];
        }
        usort($out, static fn (array $a, array $b): int => [$a['y'], $a['x']] <=> [$b['y'], $b['x']]);

        return $out;
    }

    /**
     * Placement « dense » de la grille CSS : chaque widget, dans l'ordre, prend le premier emplacement libre où il
     * tient, en partant du haut à gauche.
     *
     * @param list<array{id: string, w: int, h: int, compact?: bool}> $items
     * @return list<array{id: string, x: int, y: int, w: int, h: int, compact: bool}>
     */
    public static function pack(array $items, int $cols): array
    {
        $grid = [];
        $out = [];
        foreach ($items as $it) {
            $w = max(1, min($cols, (int) $it['w']));
            $h = max(1, (int) $it['h']);
            for ($y = 0; ; $y++) {
                for ($x = 0; $x + $w <= $cols; $x++) {
                    if (self::free($grid, $x, $y, $w, $h)) {
                        for ($r = $y; $r < $y + $h; $r++) {
                            for ($c = $x; $c < $x + $w; $c++) {
                                $grid[$r][$c] = true;
                            }
                        }
                        $out[] = ['id' => (string) $it['id'], 'x' => $x + 1, 'y' => $y + 1, 'w' => $w, 'h' => $h, 'compact' => !empty($it['compact'])];
                        continue 3;
                    }
                }
            }
        }

        return $out;
    }

    /**
     * Place une zone de la grille sans trou : placement dense puis comblement ; si une combinaison de tailles laisse
     * malgré tout une case vide, repli sur des bandes (widgets côte à côte à la même hauteur, le dernier élargi).
     *
     * @param list<array{id: string, w: int, h: int, compact?: bool}> $items
     * @return list<array{id: string, x: int, y: int, w: int, h: int, compact: bool}>
     */
    public static function region(array $items, int $cols, bool $vertical): array
    {
        $placed = self::fill(self::pack($items, $cols), $cols, $vertical);

        return self::holes($placed, $cols) === 0 ? $placed : self::strips($items, $cols);
    }

    /**
     * Repli sans trou garanti : les widgets se suivent sur des bandes de 12 colonnes ; chaque bande prend la hauteur
     * de son plus grand widget et son dernier widget s'élargit jusqu'au bord.
     *
     * @param list<array{id: string, w: int, h: int, compact?: bool}> $items
     * @return list<array{id: string, x: int, y: int, w: int, h: int, compact: bool}>
     */
    public static function strips(array $items, int $cols): array
    {
        $out = [];
        $y = 1;
        $row = [];
        $used = 0;
        $flush = static function () use (&$out, &$row, &$used, &$y, $cols): void {
            if ($row === []) {
                return;
            }
            $height = max(array_column($row, 'h'));
            $x = 1;
            foreach ($row as $k => $it) {
                $w = $k === array_key_last($row) ? $cols - $x + 1 : $it['w'];
                $out[] = ['id' => $it['id'], 'x' => $x, 'y' => $y, 'w' => $w, 'h' => $height, 'compact' => $it['compact']];
                $x += $w;
            }
            $y += $height;
            $row = [];
            $used = 0;
        };
        foreach ($items as $it) {
            $w = max(1, min($cols, (int) $it['w']));
            if ($used + $w > $cols) {
                $flush();
            }
            $row[] = ['id' => (string) $it['id'], 'w' => $w, 'h' => max(1, (int) $it['h']), 'compact' => !empty($it['compact'])];
            $used += $w;
        }
        $flush();

        return $out;
    }

    /**
     * Comble les trous d'un placement : un voisin s'élargit (à gauche ou à droite) ou, si $vertical, s'allonge
     * (au-dessus ou en dessous) sur les cases vides, tant qu'un trou peut l'être.
     *
     * @param list<array{id: string, x: int, y: int, w: int, h: int, compact: bool}> $placed
     * @return list<array{id: string, x: int, y: int, w: int, h: int, compact: bool}>
     */
    public static function fill(array $placed, int $cols, bool $vertical): array
    {
        $rows = 0;
        foreach ($placed as $p) {
            $rows = max($rows, $p['y'] + $p['h'] - 1);
        }
        for ($guard = 0; $guard < 5000; $guard++) {
            $grid = self::occupancy($placed);
            $changed = false;
            for ($r = 0; $r < $rows && !$changed; $r++) {
                for ($c = 0; $c < $cols && !$changed; $c++) {
                    if (!empty($grid[$r][$c])) {
                        continue;
                    }
                    foreach ($placed as $i => $p) {
                        $x0 = $p['x'] - 1;
                        $y0 = $p['y'] - 1;
                        $spansRow = $r >= $y0 && $r < $y0 + $p['h'];
                        $spansCol = $c >= $x0 && $c < $x0 + $p['w'];
                        if ($spansRow && $x0 + $p['w'] === $c && self::free($grid, $c, $y0, 1, $p['h'])) {
                            $placed[$i]['w']++;
                        } elseif ($spansRow && $x0 === $c + 1 && self::free($grid, $c, $y0, 1, $p['h'])) {
                            $placed[$i]['x']--;
                            $placed[$i]['w']++;
                        } elseif ($vertical && $spansCol && $y0 + $p['h'] === $r && self::free($grid, $x0, $r, $p['w'], 1)) {
                            $placed[$i]['h']++;
                        } elseif ($vertical && $spansCol && $y0 === $r + 1 && self::free($grid, $x0, $r, $p['w'], 1)) {
                            $placed[$i]['y']--;
                            $placed[$i]['h']++;
                        } else {
                            continue;
                        }
                        $changed = true;
                        break;
                    }
                }
            }
            if (!$changed) {
                break;
            }
        }

        return $placed;
    }

    /**
     * Cases vides comprises entre la première et la dernière rangée occupée.
     *
     * @param list<array{x: int, y: int, w: int, h: int}> $placed
     */
    public static function holes(array $placed, int $cols): int
    {
        $grid = self::occupancy($placed);
        $rows = $grid === [] ? 0 : max(array_keys($grid)) + 1;
        $n = 0;
        for ($r = 0; $r < $rows; $r++) {
            for ($c = 0; $c < $cols; $c++) {
                if (empty($grid[$r][$c])) {
                    $n++;
                }
            }
        }

        return $n;
    }

    /**
     * @param list<array{x: int, y: int, w: int, h: int}> $placed
     * @return array<int, array<int, bool>>
     */
    private static function occupancy(array $placed): array
    {
        $grid = [];
        foreach ($placed as $p) {
            for ($r = $p['y'] - 1; $r < $p['y'] - 1 + $p['h']; $r++) {
                for ($c = $p['x'] - 1; $c < $p['x'] - 1 + $p['w']; $c++) {
                    $grid[$r][$c] = true;
                }
            }
        }

        return $grid;
    }

    /** @param array<int, array<int, bool>> $grid */
    private static function free(array $grid, int $x, int $y, int $w, int $h): bool
    {
        for ($r = $y; $r < $y + $h; $r++) {
            for ($c = $x; $c < $x + $w; $c++) {
                if (!empty($grid[$r][$c])) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Taille d'un widget lue dans une ligne enregistrée (v2 : w/h ; v1 : « size »), ramenée dans ses bornes.
     *
     * @param array<mixed> $row
     * @return array{0: int, 1: int}
     */
    private static function sizeOf(string $id, array $row): array
    {
        $b = self::bounds($id) ?? ['def' => [4, 2], 'min' => [1, 1], 'max' => [self::COLS, self::MAX_ROWS]];
        [$w, $h] = $b['def'];
        if (isset($row['w']) || isset($row['h'])) {
            $w = is_numeric($row['w'] ?? null) ? (int) $row['w'] : $w;
            $h = is_numeric($row['h'] ?? null) ? (int) $row['h'] : $h;
        } elseif (is_string($row['size'] ?? null)) {
            $w = match ($row['size']) {
                'wide' => max($w, 8),
                'full' => self::COLS,
                default => $w,
            };
        }

        return [max($b['min'][0], min($b['max'][0], $w)), max($b['min'][1], min($b['max'][1], $h))];
    }
}
