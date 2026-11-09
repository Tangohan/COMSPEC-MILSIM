<?php

declare(strict_types=1);

namespace App\Services\BackOffice\Home;

/**
 * Disposition de l'accueil du back-office (tableau de bord de pilotage) : quels blocs, dans quel ordre, quelle
 * largeur. Calculs purs ; enregistrée par personne et par communauté (bo_user_nav_prefs.home_json).
 *
 * Un bloc « tableau » s'écrit sheet:<id> ; il n'est gardé que si la personne peut encore le lire.
 */
final class HomeLayout
{
    public const SIZES = ['normal', 'wide', 'full'];

    /** Rôle « gestionnaire » (admin.organization / admin.access) ou « assistance » (site.support seul). */
    public const ROLE_MANAGER = 'manager';
    public const ROLE_SUPPORT = 'support';

    public const MAX_SHEET_WIDGETS = 6;

    /**
     * Blocs disponibles : libellé, largeur par défaut, description courte.
     *
     * @return array<string, array{label: string, size: string, hint: string}>
     */
    public static function catalog(): array
    {
        return [
            'operation' => ['label' => 'Prochaine opération', 'size' => 'wide', 'hint' => 'Date, lieu et présences confirmées.'],
            'decisions' => ['label' => 'À décider', 'size' => 'normal', 'hint' => 'Ce qui attend votre validation.'],
            'tasks' => ['label' => 'Tâches en attente', 'size' => 'normal', 'hint' => 'Les compteurs du menu : candidatures, corrections, signalements…'],
            'shortcuts' => ['label' => 'Raccourcis', 'size' => 'normal', 'hint' => 'Vos pages favorites du menu.'],
            'kpis' => ['label' => 'Chiffres clés', 'size' => 'full', 'hint' => 'Effectifs, activité et tendances.'],
            'participation' => ['label' => 'Participation', 'size' => 'wide', 'hint' => 'Inscrits et présents des dernières activités.'],
            'units' => ['label' => 'État des unités', 'size' => 'normal', 'hint' => 'Postes pourvus, qualifications, activité.'],
            'pipeline' => ['label' => 'Recrutement', 'size' => 'normal', 'hint' => 'Parcours des recrues.'],
            'agenda' => ['label' => 'Agenda', 'size' => 'normal', 'hint' => 'Les prochains créneaux.'],
            'feed' => ['label' => 'Ce qui a bougé', 'size' => 'normal', 'hint' => 'Les dernières actions dans la communauté.'],
            'usage' => ['label' => 'Connexions', 'size' => 'wide', 'hint' => 'Site et téléphones ATAK sur 14 jours.'],
            'messages' => ['label' => 'Derniers messages', 'size' => 'normal', 'hint' => 'Votre messagerie.'],
            'gaps' => ['label' => 'Dossiers à compléter', 'size' => 'full', 'hint' => 'Fonctions, grades, rôles ou images manquants.'],
        ];
    }

    /** @return list<string> */
    public static function defaultOrder(string $role): array
    {
        if ($role === self::ROLE_SUPPORT) {
            return ['tasks', 'shortcuts', 'kpis', 'agenda', 'feed', 'usage', 'messages'];
        }

        return ['operation', 'decisions', 'tasks', 'shortcuts', 'kpis', 'participation', 'units', 'pipeline', 'agenda', 'feed', 'usage', 'messages', 'gaps'];
    }

    /**
     * Disposition par défaut d'un rôle : les blocs du rôle visibles, les autres masqués à la fin.
     *
     * @return list<array{id: string, hidden: bool, size: string}>
     */
    public static function defaults(string $role): array
    {
        $catalog = self::catalog();
        $order = self::defaultOrder($role);
        $out = [];
        foreach ($order as $id) {
            $out[] = ['id' => $id, 'hidden' => false, 'size' => $catalog[$id]['size']];
        }
        foreach ($catalog as $id => $def) {
            if (!in_array($id, $order, true)) {
                $out[] = ['id' => $id, 'hidden' => true, 'size' => $def['size']];
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
     * Disposition enregistrée, nettoyée : blocs inconnus et tableaux illisibles retirés, nouveaux blocs du catalogue
     * ajoutés à la fin (visibles), doublons retirés.
     *
     * @param list<int> $readableSheets tableaux que la personne peut lire
     * @return list<array{id: string, hidden: bool, size: string}>
     */
    public static function normalize(mixed $raw, string $role, array $readableSheets): array
    {
        $catalog = self::catalog();
        $items = is_array($raw) && is_array($raw['items'] ?? null) ? $raw['items'] : null;
        if ($items === null) {
            return self::defaults($role);
        }
        $readable = array_fill_keys($readableSheets, true);
        $out = [];
        $seen = [];
        $sheets = 0;
        foreach ($items as $row) {
            if (!is_array($row)) {
                continue;
            }
            $id = (string) ($row['id'] ?? '');
            if ($id === '' || isset($seen[$id])) {
                continue;
            }
            if (self::isSheet($id)) {
                if (!isset($readable[self::sheetId($id)]) || $sheets >= self::MAX_SHEET_WIDGETS) {
                    continue;
                }
                $sheets++;
                $default = 'full';
            } elseif (isset($catalog[$id])) {
                $default = $catalog[$id]['size'];
            } else {
                continue;
            }
            $size = (string) ($row['size'] ?? $default);
            $out[] = ['id' => $id, 'hidden' => !empty($row['hidden']), 'size' => in_array($size, self::SIZES, true) ? $size : $default];
            $seen[$id] = true;
        }
        $known = is_array($raw['known'] ?? null) ? array_fill_keys(array_map('strval', $raw['known']), true) : [];
        foreach ($catalog as $id => $def) {
            if (!isset($seen[$id])) {
                // Bloc apparu depuis le dernier enregistrement : visible ; bloc retiré par la personne : il reste masqué.
                $out[] = ['id' => $id, 'hidden' => isset($known[$id]), 'size' => $def['size']];
            }
        }

        return $out;
    }

    /**
     * Disposition envoyée par le formulaire « Personnaliser » : ordre (order[]), blocs visibles (visible[]),
     * largeurs (size[id]).
     *
     * @param list<mixed> $order
     * @param list<mixed> $visible
     * @param array<mixed> $sizes
     * @param list<int> $readableSheets
     * @return array{v: int, items: list<array{id: string, hidden: bool, size: string}>, known: list<string>}
     */
    public static function fromForm(array $order, array $visible, array $sizes, string $role, array $readableSheets): array
    {
        $visibleSet = [];
        foreach ($visible as $v) {
            if (is_string($v)) {
                $visibleSet[$v] = true;
            }
        }
        $items = [];
        foreach ($order as $id) {
            if (!is_string($id)) {
                continue;
            }
            $items[] = ['id' => $id, 'hidden' => !isset($visibleSet[$id]), 'size' => is_string($sizes[$id] ?? null) ? $sizes[$id] : ''];
        }
        $clean = self::normalize(['items' => $items], $role, $readableSheets);

        return self::stored($clean);
    }

    /**
     * Monte (-1) ou descend (+1) un bloc parmi la disposition.
     *
     * @param list<array{id: string, hidden: bool, size: string}> $items
     * @return list<array{id: string, hidden: bool, size: string}>
     */
    public static function move(array $items, string $id, int $step): array
    {
        $i = array_search($id, array_column($items, 'id'), true);
        if ($i === false) {
            return $items;
        }
        $j = $i + ($step < 0 ? -1 : 1);
        if ($j < 0 || $j >= count($items)) {
            return $items;
        }
        [$items[$i], $items[$j]] = [$items[$j], $items[$i]];

        return $items;
    }

    /**
     * Ajoute un tableau en tête des blocs (visible, pleine largeur).
     *
     * @param list<array{id: string, hidden: bool, size: string}> $items
     * @return list<array{id: string, hidden: bool, size: string}>
     */
    public static function withSheet(array $items, int $sheetId): array
    {
        $id = 'sheet:' . $sheetId;
        $items = array_values(array_filter($items, static fn (array $r): bool => $r['id'] !== $id));
        array_unshift($items, ['id' => $id, 'hidden' => false, 'size' => 'full']);

        return $items;
    }

    /**
     * @param list<array{id: string, hidden: bool, size: string}> $items
     * @return array{v: int, items: list<array{id: string, hidden: bool, size: string}>, known: list<string>}
     */
    public static function stored(array $items): array
    {
        return ['v' => 1, 'items' => array_values($items), 'known' => array_keys(self::catalog())];
    }
}
