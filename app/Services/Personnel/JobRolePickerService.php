<?php

declare(strict_types=1);

namespace App\Services\Personnel;

use App\Repositories\PersonnelJobRoleRepository;
use App\Services\Rbac\MilitaryOperationalRoleCatalog;

/**
 * Choix des fonctions d'un membre : toutes les fonctions militaires du référentiel, rangées par domaine
 * (Infanterie, Santé, Renseignement…), celles créées par la communauté, puis à part les postes nés de l'ORBAT.
 *
 * Une fonction du référentiel n'est copiée dans la communauté qu'au moment où on l'attribue
 * (valeur « cat:slug »), pour ne pas remplir la base d'emplois que personne ne tient.
 */
final class JobRolePickerService
{
    public const CATALOG_PREFIX = 'cat:';

    public const GROUP_COMMUNITY = 'Fonctions créées par la communauté';

    public const GROUP_ORBAT = 'Postes de l’ORBAT';

    /** Domaines du référentiel qui décrivent un statut, pas une fonction. */
    private const HIDDEN_DOMAINS = ['Statut'];

    public function __construct(private PersonnelJobRoleRepository $jobRoles)
    {
    }

    /**
     * @param list<int> $assignedIds
     * @return list<array{label: string, hint: string, items: list<array{value: string, label: string, detail: string, checked: bool}>}>
     */
    public function groups(int $tenantId, array $assignedIds = []): array
    {
        $assigned = array_fill_keys(array_map('intval', $assignedIds), true);
        $tenantRoles = $this->jobRoles->tablesExist() ? $this->jobRoles->listRolesWithCategory($tenantId) : [];
        $catalog = self::catalogEntries();
        $catalogBySlug = [];
        foreach ($catalog as $entry) {
            $catalogBySlug[$entry['slug']] = $entry;
        }

        $groups = [];
        $add = static function (string $group, string $hint, array $item) use (&$groups): void {
            if (!isset($groups[$group])) {
                $groups[$group] = ['label' => $group, 'hint' => $hint, 'items' => []];
            }
            $groups[$group]['items'][] = $item;
        };
        // Ordre des domaines : celui du référentiel.
        foreach ($catalog as $entry) {
            $groups[$entry['category']] ??= ['label' => $entry['category'], 'hint' => '', 'items' => []];
        }

        $materialized = [];
        $orbat = [];
        foreach ($tenantRoles as $role) {
            $rid = (int) ($role['id'] ?? 0);
            if ($rid < 1) {
                continue;
            }
            $slug = trim((string) ($role['slug'] ?? ''));
            $name = trim((string) ($role['name'] ?? ''));
            $item = [
                'value' => (string) $rid,
                'label' => $name !== '' ? $name : 'Fonction #' . $rid,
                'detail' => '',
                'checked' => isset($assigned[$rid]),
            ];
            if (str_starts_with($slug, 'unit-') || (int) ($role['source_unit_id'] ?? 0) > 0) {
                $item['detail'] = 'Unité de l’ORBAT';
                $orbat[] = $item;
                continue;
            }
            if ($slug !== '' && isset($catalogBySlug[$slug])) {
                $entry = $catalogBySlug[$slug];
                $materialized[$slug] = true;
                if (in_array($entry['category'], self::HIDDEN_DOMAINS, true) && !$item['checked']) {
                    continue;
                }
                $item['detail'] = $entry['subcategory'];
                $add($entry['category'], '', $item);
                continue;
            }
            $item['detail'] = trim((string) ($role['description'] ?? ''));
            if (mb_strlen($item['detail'], 'UTF-8') > 80) {
                $item['detail'] = mb_substr($item['detail'], 0, 79, 'UTF-8') . '…';
            }
            $add(self::GROUP_COMMUNITY, 'Ajoutées dans l’onglet Emplois.', $item);
        }
        foreach ($catalog as $entry) {
            if (isset($materialized[$entry['slug']]) || in_array($entry['category'], self::HIDDEN_DOMAINS, true)) {
                continue;
            }
            $add($entry['category'], '', [
                'value' => self::CATALOG_PREFIX . $entry['slug'],
                'label' => $entry['name'],
                'detail' => $entry['subcategory'],
                'checked' => false,
            ]);
        }
        if ($orbat !== []) {
            $groups[self::GROUP_ORBAT] = [
                'label' => self::GROUP_ORBAT,
                'hint' => 'Le nom d’une unité, pas une fonction. Gardé pour les fiches qui l’utilisent déjà ; l’affectation se règle dans la carte Unité.',
                'items' => $orbat,
            ];
        }
        // Communauté en tête : ce sont les fonctions que les gérants ont pensées pour leur unité.
        if (isset($groups[self::GROUP_COMMUNITY])) {
            $groups = [self::GROUP_COMMUNITY => $groups[self::GROUP_COMMUNITY]] + $groups;
        }
        foreach ($groups as $k => $group) {
            if ($group['items'] === []) {
                unset($groups[$k]);
            }
        }

        return array_values($groups);
    }

    /**
     * Transforme les valeurs postées (« 12 » ou « cat:infantry_rifleman ») en identifiants d'emplois de la communauté.
     *
     * @param list<mixed> $raw
     * @return list<int>
     */
    public function resolveValues(int $tenantId, array $raw): array
    {
        $ids = [];
        foreach ($raw as $value) {
            $value = trim((string) $value);
            if ($value === '') {
                continue;
            }
            if (str_starts_with($value, self::CATALOG_PREFIX)) {
                $id = $this->materializeCatalogRole($tenantId, substr($value, strlen(self::CATALOG_PREFIX)));
            } else {
                $id = ctype_digit($value) ? (int) $value : 0;
            }
            if ($id > 0 && !in_array($id, $ids, true)) {
                $ids[] = $id;
            }
        }

        return $ids;
    }

    /** Copie une fonction du référentiel dans la communauté (domaine compris), ou retrouve la copie existante. */
    public function materializeCatalogRole(int $tenantId, string $slug): int
    {
        $slug = trim($slug);
        $entry = null;
        foreach (self::catalogEntries() as $e) {
            if ($e['slug'] === $slug) {
                $entry = $e;
                break;
            }
        }
        if ($entry === null || $tenantId < 1 || !$this->jobRoles->tablesExist()) {
            return 0;
        }
        foreach ($this->jobRoles->listRolesWithCategory($tenantId) as $role) {
            if (trim((string) ($role['slug'] ?? '')) === $slug) {
                return (int) ($role['id'] ?? 0);
            }
        }
        $categoryId = 0;
        $categorySlug = 'domaine-' . self::slugify($entry['category']);
        foreach ($this->jobRoles->listCategories($tenantId) as $cat) {
            if ((string) ($cat['slug'] ?? '') === $categorySlug
                || mb_strtolower(trim((string) ($cat['name'] ?? '')), 'UTF-8') === mb_strtolower($entry['category'], 'UTF-8')) {
                $categoryId = (int) ($cat['id'] ?? 0);
                break;
            }
        }
        if ($categoryId < 1) {
            $categoryId = $this->jobRoles->createCategory($tenantId, null, $entry['category'], $categorySlug, (int) ($entry['display_group'] ?? 0));
        }
        if ($categoryId < 1) {
            return 0;
        }

        return $this->jobRoles->createRole(
            $tenantId,
            $categoryId,
            $entry['name'],
            $entry['slug'],
            $entry['description'] !== '' ? $entry['description'] : null,
            (int) ($entry['display_weight'] ?? 0),
            false,
            isset($entry['mos_code']) ? (string) $entry['mos_code'] : null,
            isset($entry['mos_specialty_title']) ? (string) $entry['mos_specialty_title'] : null
        );
    }

    /** @return list<array{slug: string, name: string, category: string, subcategory: string, description: string, display_group?: int, display_weight?: int, mos_code?: string|null, mos_specialty_title?: string|null}> */
    private static function catalogEntries(): array
    {
        $out = [];
        foreach (MilitaryOperationalRoleCatalog::entries() as $e) {
            $slug = trim((string) ($e['slug'] ?? ''));
            $name = trim((string) ($e['name'] ?? ''));
            if ($slug === '' || $name === '') {
                continue;
            }
            $e['category'] = trim((string) ($e['category'] ?? '')) ?: 'Autres';
            $e['subcategory'] = trim((string) ($e['subcategory'] ?? ''));
            $e['description'] = trim((string) ($e['description'] ?? ''));
            $out[] = $e;
        }

        return $out;
    }

    private static function slugify(string $label): string
    {
        $ascii = function_exists('iconv') ? (string) @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $label) : $label;
        $slug = trim((string) preg_replace('/[^a-z0-9]+/', '-', strtolower($ascii)), '-');

        return $slug !== '' ? substr($slug, 0, 60) : 'domaine';
    }
}
