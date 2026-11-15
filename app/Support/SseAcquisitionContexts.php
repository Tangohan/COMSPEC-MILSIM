<?php

declare(strict_types=1);

namespace App\Support;

use App\Repositories\SseFileRecordRepository;

/**
 * Contextes d'acquisition du portail SSE : comment une information, une photo, un papier, une empreinte ou un support
 * a été obtenu (colonne sse_persons.circumstances).
 *
 * Le catalogue est dans config/sse_acquisition_contexts.php (forces spéciales américaines par défaut, jeu « police » pour
 * les communautés qui jouent des policiers). Chaque communauté peut en plus, depuis la gestion des fichiers SSE :
 * choisir le jeu, désactiver un contexte, le renommer (FR et EN) et en ajouter. Ces réglages vivent dans
 * sse_file_settings.settings_json (clés acquisition_set, acquisition_overrides, acquisition_custom).
 *
 * Règle de compatibilité : un code stocké n'est jamais réécrit. Les anciens codes (controle, perquisition, reddition,
 * autre) et tout texte libre déjà en base restent lisibles ; seul l'AFFICHAGE les rattache au code actuel
 * (« Point de contrôle tactique (TCP / VCP) · ancien : Contrôle »).
 */
final class SseAcquisitionContexts
{
    public const SET_US_SOF = 'us_sof';
    public const SET_POLICE = 'police';

    public const CODE_MAX = 64;
    public const CUSTOM_CODE_MAX = 40;
    public const LABEL_MAX = 90;
    public const CUSTOM_LIMIT = 20;

    /** @var array<string, mixed>|null */
    private static ?array $config = null;

    /**
     * @param array<string, array{enabled?: bool, fr?: string, en?: string}> $overrides
     * @param list<array{code: string, group: string, fr: string, en: string, enabled: bool}> $custom
     */
    private function __construct(
        private readonly string $set,
        private readonly array $overrides,
        private readonly array $custom,
    ) {
    }

    // ------------------------------------------------------------------ Construction

    /** Catalogue par défaut, sans réglage de communauté. */
    public static function defaults(): self
    {
        return new self(self::SET_US_SOF, [], []);
    }

    /**
     * @param array<string, mixed> $settings Réglages des fichiers SSE (SseFileRecordRepository::settings()).
     */
    public static function fromSettings(array $settings): self
    {
        return new self(
            self::normalizeSet($settings['acquisition_set'] ?? null),
            self::normalizeOverrides($settings['acquisition_overrides'] ?? []),
            self::normalizeCustom($settings['acquisition_custom'] ?? []),
        );
    }

    /** Contextes d'une communauté ; catalogue par défaut si les réglages sont illisibles (table absente, base coupée). */
    public static function forTenant(int $tenantId): self
    {
        if ($tenantId < 1) {
            return self::defaults();
        }
        try {
            return self::fromSettings((new SseFileRecordRepository())->settings($tenantId));
        } catch (\Throwable) {
            return self::defaults();
        }
    }

    /** @return array<string, mixed> */
    public static function config(): array
    {
        if (self::$config === null) {
            $path = base_path('config/sse_acquisition_contexts.php');
            $raw = is_file($path) ? require $path : [];
            self::$config = is_array($raw) ? $raw : [];
        }

        return self::$config;
    }

    // ------------------------------------------------------------------ Lecture du catalogue

    public function set(): string
    {
        return $this->set;
    }

    /** @return array<string, array{fr: string, en: string}> */
    public static function sets(): array
    {
        $out = [];
        foreach ((array) (self::config()['sets'] ?? []) as $code => $row) {
            if (is_array($row)) {
                $out[(string) $code] = ['fr' => (string) ($row['fr'] ?? $code), 'en' => (string) ($row['en'] ?? $code)];
            }
        }

        return $out;
    }

    /** @return array<string, array{fr: string, en: string}> */
    public static function groups(): array
    {
        $out = [];
        foreach ((array) (self::config()['groups'] ?? []) as $code => $row) {
            if (is_array($row)) {
                $out[(string) $code] = ['fr' => (string) ($row['fr'] ?? $code), 'en' => (string) ($row['en'] ?? $code)];
            }
        }

        return $out;
    }

    /** Anciens codes : code => {to, fr, en}. @return array<string, array{to: string, fr: string, en: string}> */
    public static function legacy(): array
    {
        $out = [];
        foreach ((array) (self::config()['legacy'] ?? []) as $code => $row) {
            if (is_array($row)) {
                $out[(string) $code] = [
                    'to' => (string) ($row['to'] ?? ''),
                    'fr' => (string) ($row['fr'] ?? $code),
                    'en' => (string) ($row['en'] ?? $code),
                ];
            }
        }

        return $out;
    }

    public static function isLegacyCode(string $code): bool
    {
        return isset(self::legacy()[strtolower(trim($code))]);
    }

    /** Contextes du catalogue de base, tous jeux confondus : code => entrée. @return array<string, array<string, mixed>> */
    public static function catalogByCode(): array
    {
        $out = [];
        foreach ((array) (self::config()['contexts'] ?? []) as $row) {
            if (is_array($row) && isset($row['code'])) {
                $out[(string) $row['code']] = $row;
            }
        }

        return $out;
    }

    /**
     * Contextes proposés à cette communauté (jeu choisi + contextes ajoutés), avec leur état et leurs libellés.
     *
     * @return list<array{code: string, group: string, fr: string, en: string, desc_fr: string, desc_en: string, enabled: bool, custom: bool, default_fr: string, default_en: string}>
     */
    public function entries(bool $enabledOnly = false): array
    {
        $rows = [];
        if ($this->set === self::SET_POLICE) {
            $legacy = self::legacy();
            foreach ((array) (self::config()['police_codes'] ?? []) as $code) {
                $code = (string) $code;
                if (isset($legacy[$code])) {
                    $rows[] = [
                        'code' => $code, 'group' => 'police', 'fr' => $legacy[$code]['fr'], 'en' => $legacy[$code]['en'],
                        'desc_fr' => '', 'desc_en' => '', 'custom' => false,
                    ];
                }
            }
        } else {
            foreach (self::catalogByCode() as $code => $row) {
                $rows[] = [
                    'code' => $code, 'group' => (string) ($row['group'] ?? 'autre'),
                    'fr' => (string) ($row['fr'] ?? $code), 'en' => (string) ($row['en'] ?? $code),
                    'desc_fr' => (string) ($row['desc_fr'] ?? ''), 'desc_en' => (string) ($row['desc_en'] ?? ''), 'custom' => false,
                ];
            }
        }
        foreach ($this->custom as $c) {
            $rows[] = [
                'code' => $c['code'], 'group' => $c['group'], 'fr' => $c['fr'], 'en' => $c['en'] !== '' ? $c['en'] : $c['fr'],
                'desc_fr' => '', 'desc_en' => '', 'custom' => true,
            ];
        }

        $out = [];
        foreach ($rows as $r) {
            $ov = $this->overrides[$r['code']] ?? [];
            $enabled = $r['custom'] ? $this->customEnabled($r['code']) : (bool) ($ov['enabled'] ?? true);
            if ($enabledOnly && !$enabled) {
                continue;
            }
            $out[] = [
                'code' => $r['code'],
                'group' => $r['group'],
                'fr' => !$r['custom'] && ($ov['fr'] ?? '') !== '' ? (string) $ov['fr'] : $r['fr'],
                'en' => !$r['custom'] && ($ov['en'] ?? '') !== '' ? (string) $ov['en'] : $r['en'],
                'desc_fr' => $r['desc_fr'],
                'desc_en' => $r['desc_en'],
                'enabled' => $enabled,
                'custom' => $r['custom'],
                'default_fr' => $r['fr'],
                'default_en' => $r['en'],
            ];
        }

        return $out;
    }

    /**
     * Choix du formulaire : contextes actifs, regroupés.
     *
     * @return list<array{group: string, label: string, options: list<array{code: string, label: string, description: string}>}>
     */
    public function groupedOptions(?string $lang = null): array
    {
        $lang = $this->lang($lang);
        $groups = self::groups();
        $byGroup = [];
        foreach ($this->entries(true) as $e) {
            $byGroup[$e['group']][] = [
                'code' => $e['code'],
                'label' => $lang === 'en' ? $e['en'] : $e['fr'],
                'description' => $lang === 'en' ? $e['desc_en'] : $e['desc_fr'],
            ];
        }
        $out = [];
        foreach ($groups as $code => $g) {
            if (isset($byGroup[$code])) {
                $out[] = ['group' => $code, 'label' => $lang === 'en' ? $g['en'] : $g['fr'], 'options' => $byGroup[$code]];
                unset($byGroup[$code]);
            }
        }
        foreach ($byGroup as $code => $opts) {
            $g = $groups['autre'];
            $out[] = ['group' => (string) $code, 'label' => $lang === 'en' ? $g['en'] : $g['fr'], 'options' => $opts];
        }

        return $out;
    }

    /** @return list<array{code: string, label: string, description: string}> */
    public function options(?string $lang = null): array
    {
        $flat = [];
        foreach ($this->groupedOptions($lang) as $g) {
            foreach ($g['options'] as $o) {
                $flat[] = $o;
            }
        }

        return $flat;
    }

    // ------------------------------------------------------------------ Libellés

    /**
     * Libellé d'un code stocké. Un ancien code reste explicite : « Point de contrôle tactique (TCP / VCP) · ancien : Contrôle » ;
     * un code inconnu ou un texte libre s'affiche tel quel ; vide : « — ».
     */
    public function label(string $code, ?string $lang = null): string
    {
        $lang = $this->lang($lang);
        $raw = trim($code);
        if ($raw === '') {
            return '—';
        }
        $key = strtolower($raw);
        $own = $this->findEntry($key);
        $legacy = self::legacy()[$key] ?? null;

        // Jeu « police » : les anciens codes gardent leur libellé d'origine, sans mention.
        if ($own !== null && ($this->set === self::SET_POLICE || $legacy === null || $legacy['to'] === $key)) {
            return $lang === 'en' ? $own['en'] : $own['fr'];
        }
        if ($legacy !== null) {
            $target = $this->findEntry($legacy['to']) ?? $this->builtinEntry($legacy['to']);
            $old = $lang === 'en' ? $legacy['en'] : $legacy['fr'];
            if ($target !== null) {
                $now = $lang === 'en' ? $target['en'] : $target['fr'];

                return $now . ($lang === 'en' ? ' · formerly: ' : ' · ancien : ') . $old;
            }

            return $old;
        }
        $builtin = $this->builtinEntry($key);
        if ($builtin !== null) {
            return $lang === 'en' ? $builtin['en'] : $builtin['fr'];
        }

        return $raw;
    }

    public function description(string $code, ?string $lang = null): string
    {
        $lang = $this->lang($lang);
        $key = strtolower(trim($code));
        $key = self::legacy()[$key]['to'] ?? $key;
        $e = $this->findEntry($key) ?? $this->builtinEntry($key);
        if ($e === null) {
            return '';
        }

        return $lang === 'en' ? $e['desc_en'] : $e['desc_fr'];
    }

    /** Le code est-il connu de ce catalogue (actuel, ancien ou ajouté par la communauté) ? */
    public function isKnown(string $code): bool
    {
        $key = strtolower(trim($code));

        return $key !== '' && ($this->findEntry($key) !== null || $this->builtinEntry($key) !== null || isset(self::legacy()[$key]));
    }

    /** Code actuel d'un ancien code (pour regrouper ou filtrer) ; un code actuel ou inconnu est rendu tel quel. */
    public static function canonical(string $code): string
    {
        $key = strtolower(trim($code));

        return self::legacy()[$key]['to'] ?? $key;
    }

    /**
     * Valeur à stocker pour un code reçu du mod, du formulaire ou d'un import : rien n'est refusé.
     * Un code connu (ancien compris) est gardé tel quel en minuscules ; tout autre texte est gardé sans changement
     * de sens (balises et caractères de contrôle retirés). Vide : null.
     */
    public static function storeValue(mixed $raw): ?string
    {
        if (!is_scalar($raw)) {
            return null;
        }
        $v = trim((string) preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', strip_tags((string) $raw)));
        if ($v === '') {
            return null;
        }
        $lower = strtolower($v);
        if (isset(self::catalogByCode()[$lower]) || isset(self::legacy()[$lower]) || preg_match('/^c_[a-z0-9_]{1,38}$/', $lower) === 1) {
            return $lower;
        }

        return $v;
    }

    // ------------------------------------------------------------------ Réglages de la communauté

    public static function normalizeSet(mixed $raw): string
    {
        $s = strtolower(trim((string) (is_scalar($raw) ? $raw : '')));

        return isset(self::sets()[$s]) ? $s : self::SET_US_SOF;
    }

    /**
     * @return array<string, array{enabled?: bool, fr?: string, en?: string}>
     */
    public static function normalizeOverrides(mixed $raw): array
    {
        if (!is_array($raw)) {
            return [];
        }
        $known = array_merge(array_keys(self::catalogByCode()), array_keys(self::legacy()));
        $out = [];
        foreach ($raw as $code => $row) {
            $code = strtolower(trim((string) $code));
            if (!is_array($row) || !in_array($code, $known, true)) {
                continue;
            }
            $entry = [];
            if (array_key_exists('enabled', $row) && !self::truthy($row['enabled'])) {
                $entry['enabled'] = false;
            }
            $fr = self::cleanLabel($row['fr'] ?? '');
            $en = self::cleanLabel($row['en'] ?? '');
            if ($fr !== '') {
                $entry['fr'] = $fr;
            }
            if ($en !== '') {
                $entry['en'] = $en;
            }
            if ($entry !== []) {
                $out[$code] = $entry;
            }
        }

        return $out;
    }

    /**
     * @return list<array{code: string, group: string, fr: string, en: string, enabled: bool}>
     */
    public static function normalizeCustom(mixed $raw): array
    {
        if (!is_array($raw)) {
            return [];
        }
        $groups = self::groups();
        $taken = array_merge(array_keys(self::catalogByCode()), array_keys(self::legacy()));
        $out = [];
        foreach ($raw as $row) {
            if (!is_array($row) || count($out) >= self::CUSTOM_LIMIT) {
                continue;
            }
            $fr = self::cleanLabel($row['fr'] ?? '');
            if ($fr === '') {
                continue;
            }
            $code = strtolower(trim((string) ($row['code'] ?? '')));
            if (preg_match('/^c_[a-z0-9_]{1,38}$/', $code) !== 1) {
                $code = self::customCodeFor($fr);
            }
            $base = $code;
            for ($i = 2; in_array($code, $taken, true); $i++) {
                $code = substr($base, 0, self::CUSTOM_CODE_MAX - strlen((string) $i) - 1) . '_' . $i;
            }
            $taken[] = $code;
            $group = (string) ($row['group'] ?? 'autre');
            $out[] = [
                'code' => $code,
                'group' => isset($groups[$group]) ? $group : 'autre',
                'fr' => $fr,
                'en' => self::cleanLabel($row['en'] ?? ''),
                'enabled' => !array_key_exists('enabled', $row) || self::truthy($row['enabled']),
            ];
        }

        return $out;
    }

    /** Code stable d'un contexte ajouté : « c_ » + libellé en minuscules sans accents. */
    public static function customCodeFor(string $label): string
    {
        $s = strtolower($label);
        $map = ['à' => 'a', 'â' => 'a', 'ä' => 'a', 'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e', 'î' => 'i', 'ï' => 'i', 'ô' => 'o', 'ö' => 'o', 'ù' => 'u', 'û' => 'u', 'ü' => 'u', 'ç' => 'c', 'œ' => 'oe', 'æ' => 'ae'];
        $s = strtr($s, $map);
        $s = trim((string) preg_replace('/[^a-z0-9]+/', '_', $s), '_');
        $s = 'c_' . ($s === '' ? 'contexte' : $s);

        return rtrim(substr($s, 0, self::CUSTOM_CODE_MAX), '_');
    }

    public static function cleanLabel(mixed $raw): string
    {
        if (!is_scalar($raw)) {
            return '';
        }
        $v = trim((string) preg_replace('/[\x00-\x1F\x7F]+/u', ' ', strip_tags((string) $raw)));
        $v = trim((string) preg_replace('/\s+/u', ' ', $v));

        return mb_substr($v, 0, self::LABEL_MAX);
    }

    // ------------------------------------------------------------------ Interne

    private static function truthy(mixed $v): bool
    {
        return $v === true || $v === 1 || $v === '1' || $v === 'on' || $v === 'true';
    }

    private function lang(?string $lang): string
    {
        if ($lang === 'fr' || $lang === 'en') {
            return $lang;
        }
        if (function_exists('locale') && function_exists('site_lang') && locale() === 'en' && site_lang() === 'en') {
            return 'en';
        }

        return 'fr';
    }

    private function customEnabled(string $code): bool
    {
        foreach ($this->custom as $c) {
            if ($c['code'] === $code) {
                return $c['enabled'];
            }
        }

        return true;
    }

    /**
     * Entrée du jeu actif (libellés de la communauté compris), même désactivée : un code déjà stocké reste lisible.
     *
     * @return array{fr: string, en: string, desc_fr: string, desc_en: string}|null
     */
    private function findEntry(string $code): ?array
    {
        foreach ($this->entries(false) as $e) {
            if ($e['code'] === $code) {
                return ['fr' => $e['fr'], 'en' => $e['en'], 'desc_fr' => $e['desc_fr'], 'desc_en' => $e['desc_en']];
            }
        }

        return null;
    }

    /**
     * Entrée du catalogue de base (libellés d'origine ou renommés par la communauté), hors jeu actif : un contexte
     * récent envoyé par le mod reste lisible dans une communauté du jeu « police ».
     *
     * @return array{fr: string, en: string, desc_fr: string, desc_en: string}|null
     */
    private function builtinEntry(string $code): ?array
    {
        $row = self::catalogByCode()[$code] ?? null;
        if ($row === null) {
            return null;
        }
        $ov = $this->overrides[$code] ?? [];

        return [
            'fr' => ($ov['fr'] ?? '') !== '' ? (string) $ov['fr'] : (string) ($row['fr'] ?? $code),
            'en' => ($ov['en'] ?? '') !== '' ? (string) $ov['en'] : (string) ($row['en'] ?? $code),
            'desc_fr' => (string) ($row['desc_fr'] ?? ''),
            'desc_en' => (string) ($row['desc_en'] ?? ''),
        ];
    }
}
