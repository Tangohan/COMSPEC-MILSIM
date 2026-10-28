<?php

declare(strict_types=1);

namespace App\Support;

use App\Support\Profile\PublicFlagCountryCatalog;

/**
 * Pays de l’unité, langues de la communauté, langue des joueurs et langue de l’ORBAT.
 *
 * Réglages stockés dans tenants.settings.community :
 *  - unit_country : pays de l’unité (ISO 3166-1 alpha-2), repris de l’unité réelle représentée à défaut ;
 *  - default_locale : langue de référence (fr, en, ru, es) ;
 *  - community_languages : langues parlées dans l’unité (toujours au moins la langue de référence) ;
 *  - orbat_language : langue de la feuille ORBAT ('' = langue de référence).
 *
 * L’interface du site reste en français ou en anglais (LocaleService) : ces langues décrivent
 * l’unité et ses joueurs, pas la traduction du portail.
 */
final class CommunityLanguages
{
    public const DEFAULT = 'fr';

    /** @var array<string, array{native: string, fr: string, en: string, country: string}> */
    private const LANGUAGES = [
        'fr' => ['native' => 'Français', 'fr' => 'Français', 'en' => 'French', 'country' => 'FR'],
        'en' => ['native' => 'English', 'fr' => 'Anglais', 'en' => 'English', 'country' => 'GB'],
        'ru' => ['native' => 'Русский', 'fr' => 'Russe', 'en' => 'Russian', 'country' => 'RU'],
        'es' => ['native' => 'Español', 'fr' => 'Espagnol', 'en' => 'Spanish', 'country' => 'ES'],
    ];

    /** Systèmes de grades disponibles dans le catalogue (GradeSelectOptions). */
    private const GRADE_COUNTRIES = ['FR', 'US'];

    /** @return list<string> */
    public static function codes(): array
    {
        return array_keys(self::LANGUAGES);
    }

    /** Code reconnu (fr, fr-FR, fr_fr, FR…) ou null. */
    public static function normalize(mixed $raw): ?string
    {
        $v = strtolower(trim((string) $raw));
        if ($v === '') {
            return null;
        }
        $v = substr(str_replace('_', '-', $v), 0, 2);

        return isset(self::LANGUAGES[$v]) ? $v : null;
    }

    /** Libellé dans la langue elle-même (Русский, Español…). */
    public static function nativeLabel(string $code): string
    {
        return self::LANGUAGES[$code]['native'] ?? strtoupper($code);
    }

    /** Libellé lu par un francophone (Russe, Espagnol…). */
    public static function label(string $code): string
    {
        return self::LANGUAGES[$code]['fr'] ?? strtoupper($code);
    }

    /** Nom de la langue dans la langue de la page (« French » sur le site anglais). */
    public static function pageLabel(string $code): string
    {
        if (function_exists('locale') && locale() === 'en') {
            return self::LANGUAGES[$code]['en'] ?? strtoupper($code);
        }

        return self::label($code);
    }

    /** « Russe · Русский », « Français ». */
    public static function fullLabel(string $code): string
    {
        $fr = self::label($code);
        $native = self::nativeLabel($code);

        return $fr === $native ? $fr : $fr . ' · ' . $native;
    }

    public static function flag(string $code): string
    {
        $country = self::countryCode($code);

        return $country !== '' ? PublicFlagCountryCatalog::flagEmoji($country) : '';
    }

    /** ISO 3166-1 alpha-2 associé à la langue (FR pour fr, GB pour en…). */
    public static function countryCode(string $code): string
    {
        $normalized = self::normalize($code) ?? strtolower(trim($code));

        return strtoupper((string) (self::LANGUAGES[$normalized]['country'] ?? ''));
    }

    /**
     * Drapeau HTML fiable (image flagcdn) — les emoji régionaux cassent sur Edge.
     *
     * @param array{class?: string, size?: string, title?: string, label?: string} $opts
     */
    public static function flagHtml(string $code, array $opts = []): string
    {
        $country = self::countryCode($code);
        if ($country === '' || !function_exists('athena_flag_html')) {
            return '';
        }

        return athena_flag_html($country, [
            'mode' => 'img',
            'size' => (string) ($opts['size'] ?? '16x12'),
            'class' => trim('eff-lang-flag ' . (string) ($opts['class'] ?? '')),
            'title' => (string) ($opts['title'] ?? self::pageLabel($code)),
            'label' => (string) ($opts['label'] ?? self::pageLabel($code)),
        ]);
    }

    /**
     * Une image de drapeau par pays distinct, dans l’ordre des codes langue.
     *
     * @param list<string> $codes
     * @param array{class?: string, size?: string} $opts
     */
    public static function flagsHtml(array $codes, array $opts = []): string
    {
        $seen = [];
        $parts = [];
        foreach ($codes as $code) {
            $country = self::countryCode((string) $code);
            if ($country === '' || isset($seen[$country])) {
                continue;
            }
            $seen[$country] = true;
            $html = self::flagHtml((string) $code, $opts);
            if ($html !== '') {
                $parts[] = $html;
            }
        }

        return implode('', $parts);
    }

    /** @return array<string, string> code => « Russe · Русский » */
    public static function options(): array
    {
        $out = [];
        foreach (self::codes() as $code) {
            $out[$code] = self::fullLabel($code);
        }

        return $out;
    }

    /**
     * Liste de codes valides, sans doublon, dans l’ordre du catalogue.
     *
     * @return list<string>
     */
    public static function normalizeList(mixed $raw): array
    {
        if (is_string($raw)) {
            $raw = preg_split('/[\s,;]+/', $raw) ?: [];
        }
        if (!is_array($raw)) {
            return [];
        }
        $seen = [];
        foreach ($raw as $item) {
            $code = self::normalize($item);
            if ($code !== null) {
                $seen[$code] = true;
            }
        }

        return array_values(array_filter(self::codes(), static fn (string $c): bool => isset($seen[$c])));
    }

    /** @param array<string, mixed> $community */
    public static function communityDefault(array $community): string
    {
        return self::normalize($community['default_locale'] ?? null) ?? self::DEFAULT;
    }

    /**
     * Langues parlées dans l’unité ; la langue de référence est toujours incluse, en premier.
     *
     * @param array<string, mixed> $community
     * @return list<string>
     */
    public static function communityLanguages(array $community): array
    {
        $default = self::communityDefault($community);
        $list = self::normalizeList($community['community_languages'] ?? []);

        return array_values(array_unique(array_merge([$default], $list)));
    }

    /**
     * Langue explicitement choisie pour l’ORBAT ('' = suit la langue de référence).
     *
     * @param array<string, mixed> $community
     */
    public static function orbatLanguageSetting(array $community): string
    {
        return self::normalize($community['orbat_language'] ?? null) ?? '';
    }

    /** @param array<string, mixed> $community */
    public static function orbatLanguage(array $community): string
    {
        $explicit = self::orbatLanguageSetting($community);

        return $explicit !== '' ? $explicit : self::communityDefault($community);
    }

    /**
     * Pays de l’unité : réglage dédié, sinon pays de l’unité réelle représentée.
     *
     * @param array<string, mixed> $community
     */
    public static function unitCountry(array $community): string
    {
        $own = PublicFlagCountryCatalog::normalize((string) ($community['unit_country'] ?? ''));
        if ($own !== null) {
            return $own;
        }
        $aff = $community['unit_affiliation'] ?? null;
        if (is_array($aff) && !empty($aff['is_real'])) {
            $fromAff = PublicFlagCountryCatalog::normalize((string) ($aff['country'] ?? ''));
            if ($fromAff !== null) {
                return $fromAff;
            }
        }

        return '';
    }

    public static function countryLabel(string $iso2): string
    {
        $options = PublicFlagCountryCatalog::optionsForSelect();

        return $iso2 !== '' ? ($options[strtoupper($iso2)] ?? strtoupper($iso2)) : '';
    }

    /** Nom du pays dans la langue demandée (extension intl si présente), sinon en français. */
    public static function countryLabelIn(string $iso2, string $lang): string
    {
        $iso2 = strtoupper(trim($iso2));
        if ($iso2 === '') {
            return '';
        }
        $lang = self::normalize($lang) ?? self::DEFAULT;
        if ($lang !== 'fr' && class_exists(\Locale::class)) {
            $name = (string) \Locale::getDisplayRegion('-' . $iso2, $lang);
            if ($name !== '' && strtoupper($name) !== $iso2) {
                return $name;
            }
        }

        return self::countryLabel($iso2);
    }

    /** @return array<string, string> ISO2 => libellé français, sans l’entrée vide */
    public static function countryOptions(): array
    {
        $options = PublicFlagCountryCatalog::optionsForSelect();
        unset($options['']);

        return $options;
    }

    /**
     * Système de grades présenté en premier : celui du pays de l’unité s’il existe au catalogue,
     * sinon le système de grades réglé pour la communauté.
     *
     * @param array<string, mixed> $settings tenants.settings décodé (racine)
     */
    public static function preferredGradeCountry(array $settings): string
    {
        $community = is_array($settings['community'] ?? null) ? $settings['community'] : [];
        $country = self::unitCountry($community);
        if (in_array($country, self::GRADE_COUNTRIES, true)) {
            return $country;
        }
        $system = strtoupper(trim((string) ($settings['grade_system_code'] ?? '')));

        return str_starts_with($system, 'US') ? 'US' : 'FR';
    }

    /**
     * Langue d’un joueur : réglée pour la communauté, sinon langue de son interface, sinon langue de référence.
     *
     * @param array{primary_lang?: ?string, other_langs?: ?string}|null $stored
     * @param array<string, mixed> $community
     * @return array{primary: string, others: list<string>, source: 'member'|'interface'|'community'}
     */
    public static function resolveMember(?array $stored, ?string $interfaceLanguage, array $community): array
    {
        $primary = self::normalize($stored['primary_lang'] ?? null);
        $others = self::normalizeList($stored['other_langs'] ?? '');
        if ($primary !== null) {
            return [
                'primary' => $primary,
                'others' => array_values(array_diff($others, [$primary])),
                'source' => 'member',
            ];
        }
        $ui = self::normalize($interfaceLanguage);
        if ($ui !== null) {
            return ['primary' => $ui, 'others' => array_values(array_diff($others, [$ui])), 'source' => 'interface'];
        }
        $default = self::communityDefault($community);

        return ['primary' => $default, 'others' => array_values(array_diff($others, [$default])), 'source' => 'community'];
    }
}
