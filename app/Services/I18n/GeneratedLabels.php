<?php

declare(strict_types=1);

namespace App\Services\I18n;

use App\Repositories\TenantGeneratedLabelRepository;

/**
 * Libellés générés par Athena : noms des unités et des emplois copiés depuis un modèle d’organisation, postes types
 * de l’ORBAT, types d’unité du référentiel. Catalogue : lang/fr/unit_labels.php (clé « type.identifiant »),
 * traductions : lang/en/unit_labels.php et les langues publiées par la communauté (/traduire-site).
 *
 * Résolution, pour la langue du lecteur : libellé saisi par la communauté (tenant_generated_labels) > traduction du
 * site > libellé par défaut (français). Un texte n’est reconnu comme généré que s’il porte encore exactement le
 * libellé par défaut (et, quand on la connaît, l’adresse courte du modèle) : un nom saisi à la main par la
 * communauté est rendu tel quel, jamais traduit.
 *
 * Cache léger : les surcharges d’une communauté sont lues une fois par requête (oubliées à l’enregistrement).
 */
final class GeneratedLabels
{
    public const GROUP = 'unit_labels';

    /** Langues personnalisables dans le back-office. */
    public const LANGS = ['fr' => 'Français', 'en' => 'English'];

    public const KINDS = [
        'unit' => 'Unités des modèles',
        'category' => 'Catégories d’emplois',
        'job' => 'Emplois',
        'post' => 'Postes types de l’ORBAT',
        'unit_type' => 'Types d’unité',
    ];

    /** Postes : reconnus au seul titre (le code du poste change d’un groupe à l’autre). */
    private const TEXT_ONLY_KINDS = ['post'];

    /** @var array<string, array<string, array<string, string>>> */
    private static array $catalogs = [];

    /** @var array<string, array<string, list<string>>>|null type => [texte français => identifiants] */
    private static ?array $byText = null;

    /** @var array<int, array<string, array<string, string>>> */
    private static array $overrides = [];

    /** @var array<int, array<string, string>|null> */
    private static array $units = [];

    private static ?TenantGeneratedLabelRepository $repository = null;

    private static ?string $root = null;

    /** Tests : dépôt et racine du projet injectés. */
    public static function useRepository(?TenantGeneratedLabelRepository $repository, ?string $root = null): void
    {
        self::$repository = $repository;
        self::$root = $root;
        self::reset();
    }

    public static function reset(): void
    {
        self::$catalogs = [];
        self::$byText = null;
        self::$overrides = [];
        self::$units = [];
    }

    /** Oublie le cache d’une communauté (après un enregistrement). */
    public static function forget(int $tenantId): void
    {
        unset(self::$overrides[$tenantId], self::$units[$tenantId]);
    }

    /**
     * @return array<string, array<string, string>> type => [identifiant => libellé]
     */
    public static function catalog(string $lang = 'fr'): array
    {
        $lang = $lang === 'en' ? 'en' : 'fr';
        if (isset(self::$catalogs[$lang])) {
            return self::$catalogs[$lang];
        }
        $root = self::$root ?? (function_exists('base_path') ? base_path() : dirname(__DIR__, 3));
        $file = rtrim($root, '/\\') . '/lang/' . $lang . '/' . self::GROUP . '.php';
        /** @var mixed $data */
        $data = is_file($file) ? require $file : [];
        $out = [];
        foreach (is_array($data) ? $data : [] as $kind => $list) {
            if (!is_string($kind) || !is_array($list)) {
                continue;
            }
            foreach ($list as $id => $text) {
                if (is_string($text) && $text !== '') {
                    $out[$kind][(string) $id] = $text;
                }
            }
        }

        return self::$catalogs[$lang] = $out;
    }

    /** Toutes les clés du catalogue, dans l’ordre du fichier. @return list<string> */
    public static function keys(): array
    {
        $keys = [];
        foreach (self::catalog('fr') as $kind => $list) {
            foreach (array_keys($list) as $id) {
                $keys[] = $kind . '.' . $id;
            }
        }

        return $keys;
    }

    public static function isKey(string $key): bool
    {
        [$kind, $id] = self::split($key);

        return $kind !== '' && isset(self::catalog('fr')[$kind][$id]);
    }

    /**
     * Clés candidates pour un texte affiché : vide si le texte n’est pas (ou plus) un libellé généré.
     *
     * @return list<string>
     */
    public static function keysFor(string $kind, string $text, ?string $id = null): array
    {
        $text = trim($text);
        if ($text === '') {
            return [];
        }
        $fr = self::catalog('fr')[$kind] ?? [];
        $id = $id !== null ? trim($id) : '';
        if ($id !== '' && !in_array($kind, self::TEXT_ONLY_KINDS, true)) {
            return ($fr[$id] ?? null) === $text ? [$kind . '.' . $id] : [];
        }
        if (self::$byText === null) {
            self::$byText = [];
            foreach (self::catalog('fr') as $k => $list) {
                foreach ($list as $i => $t) {
                    self::$byText[$k][$t][] = (string) $i;
                }
            }
        }

        return array_map(static fn (string $i): string => $kind . '.' . $i, self::$byText[$kind][$text] ?? []);
    }

    /** Libellé du site (sans personnalisation) dans une langue. */
    public static function defaultLabel(string $key, string $lang = 'fr'): string
    {
        [$kind, $id] = self::split($key);
        $fr = self::catalog('fr')[$kind][$id] ?? '';
        $lang = self::lang($lang);
        if ($lang === 'fr' || $fr === '') {
            return $fr;
        }
        // Traduction validée sur /traduire-site (pour l’anglais : corrections de l’anglais).
        $community = self::communityTranslation($key, $lang);
        if ($community !== null) {
            return $community;
        }

        return self::catalog('en')[$kind][$id] ?? $fr;
    }

    /** Libellé affiché pour une clé : communauté > traduction > défaut. */
    public static function resolve(int $tenantId, string $key, ?string $lang = null): string
    {
        $lang = self::lang($lang);
        $own = self::overrides($tenantId)[$key][$lang] ?? null;
        if ($own !== null && $own !== '') {
            return $own;
        }
        if ($lang !== 'fr' && $lang !== 'en') {
            // Langue publiée par la communauté du site : sa traduction, sinon l’anglais (personnalisé compris).
            return self::communityTranslation($key, $lang) ?? self::resolve($tenantId, $key, 'en');
        }

        return self::defaultLabel($key, $lang);
    }

    /**
     * Texte à afficher : traduit / personnalisé s’il s’agit d’un libellé généré, sinon rendu tel quel.
     *
     * @param string|null $id adresse courte (unité, emploi, catégorie) ou code (type d’unité) quand on la connaît
     */
    public static function display(int $tenantId, string $kind, string $text, ?string $id = null, ?string $lang = null): string
    {
        $keys = self::keysFor($kind, $text, $id);
        if ($kind === 'unit' && ($id === null || trim($id) === '') && $keys !== [] && $tenantId > 0) {
            // Nom seul (fiche membre) : seulement si la communauté a bien l’unité du modèle sous ce nom.
            $units = self::tenantUnits($tenantId);
            if ($units !== null) {
                $keys = array_values(array_filter(
                    $keys,
                    static fn (string $k): bool => ($units[substr($k, 5)] ?? null) === trim($text)
                ));
            }
        }
        if ($keys === []) {
            return $text;
        }
        $lang = self::lang($lang);
        $chosen = $keys[0];
        if (count($keys) > 1) {
            // Même texte dans plusieurs modèles : la clé déjà personnalisée par la communauté l’emporte.
            $own = self::overrides($tenantId);
            foreach ($keys as $k) {
                if (isset($own[$k][$lang]) || isset($own[$k]['en'])) {
                    $chosen = $k;
                    break;
                }
            }
        }

        return self::resolve($tenantId, $chosen, $lang);
    }

    /**
     * Arbre ORBAT (OrbatRosterPayload) : noms d’unités et titres de postes dans la langue du lecteur. Le nom
     * enregistré reste dans labelSource / titleSource (édition), et noTranslate empêche la traduction du JSON
     * (HtmlTranslator::translateJson) de retoucher un nom saisi par la communauté.
     *
     * @param array<string, mixed> $node
     * @param array<int|string, mixed> $slugByUnitId
     * @return array<string, mixed>
     */
    public static function localizeOrbat(array $node, int $tenantId, array $slugByUnitId = [], ?string $lang = null): array
    {
        $lang = self::lang($lang);
        $unitId = (int) ($node['unitId'] ?? 0);
        if ($unitId > 0 && isset($node['label']) && is_string($node['label'])) {
            $raw = isset($node['labelSource']) && is_string($node['labelSource']) ? $node['labelSource'] : $node['label'];
            $slug = isset($slugByUnitId[$unitId]) ? (string) $slugByUnitId[$unitId] : null;
            $shown = self::display($tenantId, 'unit', $raw, $slug, $lang);
            $node['label'] = $shown;
            if ($shown !== $raw) {
                $node['labelSource'] = $raw;
            } else {
                unset($node['labelSource']);
            }
            $node['noTranslate'] = ['label'];
        }
        if (isset($node['billets']) && is_array($node['billets'])) {
            foreach ($node['billets'] as $i => $b) {
                if (is_array($b)) {
                    $node['billets'][$i] = self::localizePost($b, $tenantId, $lang);
                }
            }
        }
        foreach (['commander', 'deputy'] as $slot) {
            if (isset($node[$slot]) && is_array($node[$slot])) {
                $node[$slot] = self::localizePost($node[$slot], $tenantId, $lang);
            }
        }
        if (isset($node['children']) && is_array($node['children'])) {
            foreach ($node['children'] as $i => $child) {
                if (is_array($child)) {
                    $node['children'][$i] = self::localizeOrbat($child, $tenantId, $slugByUnitId, $lang);
                }
            }
        }

        return $node;
    }

    /**
     * @param array<string, mixed> $post
     * @return array<string, mixed>
     */
    private static function localizePost(array $post, int $tenantId, string $lang): array
    {
        if (!isset($post['title']) || !is_string($post['title'])) {
            return $post;
        }
        $raw = isset($post['titleSource']) && is_string($post['titleSource']) ? $post['titleSource'] : $post['title'];
        $shown = self::display($tenantId, 'post', $raw, null, $lang);
        $post['title'] = $shown;
        if ($shown !== $raw) {
            $post['titleSource'] = $raw;
        } else {
            unset($post['titleSource']);
        }
        $post['noTranslate'] = ['title'];

        return $post;
    }

    /**
     * Surcharges de la communauté (cache par requête).
     *
     * @return array<string, array<string, string>>
     */
    public static function overrides(int $tenantId): array
    {
        if ($tenantId < 1) {
            return [];
        }
        if (isset(self::$overrides[$tenantId])) {
            return self::$overrides[$tenantId];
        }
        try {
            $repo = self::$repository ??= new TenantGeneratedLabelRepository();
            $rows = $repo->allForTenant($tenantId);
        } catch (\Throwable) {
            $rows = [];
        }

        return self::$overrides[$tenantId] = $rows;
    }

    /**
     * Unités de la communauté (adresse courte => nom), lues une fois par requête ; null si illisibles.
     *
     * @return array<string, string>|null
     */
    private static function tenantUnits(int $tenantId): ?array
    {
        if (!array_key_exists($tenantId, self::$units)) {
            try {
                $repo = self::$repository ??= new TenantGeneratedLabelRepository();
                self::$units[$tenantId] = $repo->unitNamesBySlug($tenantId);
            } catch (\Throwable) {
                self::$units[$tenantId] = null;
            }
        }

        return self::$units[$tenantId];
    }

    /** Langue du lecteur : celle de la page (compte, sinon navigateur), « fr » par défaut. */
    public static function lang(?string $lang = null): string
    {
        if ($lang === null || trim($lang) === '') {
            $lang = function_exists('site_lang') ? (string) site_lang() : 'fr';
        }
        $lang = strtolower(trim($lang));
        $lang = (string) preg_replace('/[^a-z0-9_-]/', '', $lang);

        return $lang === '' ? 'fr' : $lang;
    }

    /** @return array{0: string, 1: string} */
    private static function split(string $key): array
    {
        $dot = strpos($key, '.');
        if ($dot === false) {
            return ['', ''];
        }

        return [substr($key, 0, $dot), substr($key, $dot + 1)];
    }

    private static function communityTranslation(string $key, string $lang): ?string
    {
        try {
            $value = CommunityLanguages::dictionary($lang)['k'][self::GROUP . '.' . $key] ?? null;
        } catch (\Throwable) {
            $value = null;
        }

        return is_string($value) && trim($value) !== '' ? $value : null;
    }
}
