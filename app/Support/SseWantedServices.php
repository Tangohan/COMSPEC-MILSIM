<?php

declare(strict_types=1);

namespace App\Support;

use App\Support\SseWantedCatalog as C;

/**
 * Services demandeurs des avis de recherche : catalogue livré (config/sse_wanted_services.php, tous FICTIFS) et services créés par
 * la communauté (rangés dans ses réglages, clé « services »). Une même forme pour les deux : voir {@see self::normalize()}.
 *
 * Garde-fous : aucun nom d'organisation réelle ({@see SseWantedRealNames}, y compris pour les noms créés ou importés), coordonnées
 * fictives seulement (lignes « 555 01xx », domaines « .invalid », « .example » ou « .test »), codes de la communauté préfixés
 * « CSV- » pour ne jamais heurter un code livré (« SVC- »).
 */
final class SseWantedServices
{
    public const ROLES = [
        'request' => ['Demande de recherche', 'Search request'],
        'warrant' => ['Mandat', 'Warrant'],
        'alert' => ['Alerte', 'Alert'],
    ];

    public const MAX_CUSTOM = 60;

    public const EXPORT_FORMAT = 'comspec.wanted-services';

    private const CODE_RE = '/^(?:SVC|CSV)-[A-Z0-9]{1,12}(?:-[A-Z0-9]{1,10})?$/';

    private const NAME_RE = '/^[\p{L}\p{N} .,\'’()\/&:·+—–-]+$/u';

    private const PHONE_RE = '/^(?:\+?1[ .-]?)?\(?555\)?[ .-]?01\d{2}$/';

    private const MAIL_RE = '/^[a-z0-9._+-]{1,40}@[a-z0-9.-]{1,60}\.(?:invalid|example|test)$/i';

    /** @var array<string, mixed>|null */
    private static ?array $raw = null;

    /** @var array<string, array<string, mixed>>|null */
    private static ?array $catalogue = null;

    /** @param array<string, mixed>|null $raw données de remplacement (tests) */
    public static function useRaw(?array $raw): void
    {
        self::$raw = $raw;
        self::$catalogue = null;
    }

    /** @return array<string, mixed> */
    private static function raw(): array
    {
        if (self::$raw === null) {
            $file = dirname(__DIR__, 2) . '/config/sse_wanted_services.php';
            $data = is_file($file) ? require $file : [];
            self::$raw = is_array($data) ? $data : [];
        }

        return self::$raw;
    }

    /** @return array<string, array{fr: string, en: string}> */
    public static function families(): array
    {
        $out = [];
        foreach ((array) (self::raw()['families'] ?? []) as $key => $f) {
            if (is_string($key) && is_array($f)) {
                $out[$key] = ['fr' => (string) ($f['fr'] ?? $key), 'en' => (string) ($f['en'] ?? $f['fr'] ?? $key)];
            }
        }

        return $out;
    }

    /** @return array<string, string> clé => couleur hexadécimale */
    public static function palette(): array
    {
        $out = [];
        foreach ((array) (self::raw()['palette'] ?? []) as $key => $hex) {
            if (is_string($key) && is_string($hex) && preg_match('/^#[0-9A-Fa-f]{6}$/', $hex) === 1) {
                $out[$key] = strtoupper($hex);
            }
        }

        return $out ?: ['navy' => '#1F3A5F'];
    }

    /** @return list<string> */
    public static function emblems(): array
    {
        $list = array_values(array_filter((array) (self::raw()['emblems'] ?? []), 'is_string'));

        return $list ?: ['hex'];
    }

    /**
     * Catalogue livré, normalisé, dans l'ordre du fichier.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function catalogue(): array
    {
        if (self::$catalogue !== null) {
            return self::$catalogue;
        }
        $raw = self::raw();
        $families = (array) ($raw['families'] ?? []);
        $out = [];
        foreach ((array) ($raw['services'] ?? []) as $code => $row) {
            if (!is_string($code) || !is_array($row)) {
                continue;
            }
            $fam = is_array($families[$row['fam'] ?? ''] ?? null) ? $families[$row['fam']] : [];
            $merged = $row + array_diff_key($fam, ['fr' => 1, 'en' => 1]);
            $merged['family'] = (string) ($row['fam'] ?? '');
            $n = self::normalize(['code' => $code] + $merged, 'catalogue');
            if ($n !== null) {
                $out[$code] = $n;
            }
        }

        return self::$catalogue = $out;
    }

    /**
     * Forme unique d'un service. Renvoie null si la ligne est inutilisable (code ou nom invalide).
     *
     * @param array<string, mixed> $r
     * @return array<string, mixed>|null
     */
    public static function normalize(array $r, string $source = 'custom'): ?array
    {
        $code = strtoupper(C::line($r['code'] ?? '', 30));
        $fr = C::line($r['fr'] ?? '', 120);
        if (preg_match(self::CODE_RE, $code) !== 1 || $fr === '') {
            return null;
        }
        $palette = self::palette();
        $color = is_string($r['color'] ?? null) && isset($palette[$r['color']]) ? (string) $r['color'] : (string) array_key_first($palette);
        $emblem = is_string($r['emblem'] ?? null) && in_array($r['emblem'], self::emblems(), true) ? (string) $r['emblem'] : self::emblems()[0];
        $families = self::families();
        $family = is_string($r['family'] ?? null) && isset($families[$r['family']]) ? (string) $r['family'] : (string) (array_key_first($families) ?? 'coord');
        $roles = [];
        foreach (is_array($r['roles'] ?? null) ? $r['roles'] : [] as $role) {
            if (is_string($role) && isset(self::ROLES[$role]) && !in_array($role, $roles, true)) {
                $roles[] = $role;
            }
        }
        $classes = SseUsClassification::codes();
        $pickClass = static fn (mixed $v, string $def): string => is_string($v) && in_array($v, $classes, true) ? $v : $def;
        $max = $pickClass($r['max'] ?? null, 'encadrement');
        $class = $pickClass($r['class'] ?? null, $max);
        if (array_search($class, $classes, true) > array_search($max, $classes, true)) {
            $class = $max;
        }
        $threats = [];
        foreach (is_array($r['threats'] ?? null) ? $r['threats'] : [] as $t) {
            if (is_string($t) && isset(C::THREATS[$t]) && !in_array($t, $threats, true)) {
                $threats[] = $t;
            }
        }
        $types = [];
        foreach (is_array($r['types'] ?? null) ? $r['types'] : [] as $t) {
            if (is_string($t) && preg_match('/^[a-z0-9_]{2,24}$/', $t) === 1 && !in_array($t, $types, true)) {
                $types[] = $t;
            }
        }
        $lang = [];
        foreach (is_array($r['lang'] ?? null) ? $r['lang'] : ['fr', 'en'] as $l) {
            if (in_array($l, ['fr', 'en'], true) && !in_array($l, $lang, true)) {
                $lang[] = $l;
            }
        }
        $thr = is_string($r['thr'] ?? null) && isset(C::THREATS[$r['thr']]) ? (string) $r['thr'] : 'medium';
        $en = C::line($r['en'] ?? '', 120);
        $radio = C::line($r['radio'] ?? '', 80);
        $tel = C::line($r['tel'] ?? '', 24);
        $mail = strtolower(C::line($r['mail'] ?? '', 80));
        $authFr = C::line($r['auth_fr'] ?? '', 160);
        $authEn = C::line($r['auth_en'] ?? '', 160);

        return [
            'code' => $code,
            'source' => $source,
            'family' => $family,
            'fr' => $fr,
            'en' => $en !== '' ? $en : $fr,
            'ac' => strtoupper(C::line($r['ac'] ?? '', 12)),
            'roles' => $roles ?: ['request'],
            'auth_fr' => $authFr !== '' ? $authFr : $fr,
            'auth_en' => $authEn !== '' ? $authEn : ($en !== '' ? $en : $fr),
            'radio' => $radio,
            'tel' => $tel,
            'mail' => $mail,
            'max' => $max,
            'class' => $class,
            'thr' => $thr,
            'exp' => max(0, min(3650, (int) ($r['exp'] ?? 0))),
            'threats' => $threats ?: array_keys(C::THREATS),
            'types' => $types,
            'color' => $color,
            'emblem' => $emblem,
            'lang' => $lang ?: ['fr', 'en'],
            'ins_fr' => C::text($r['ins_fr'] ?? '', C::INSTRUCTIONS_MAX),
            'ins_en' => C::text($r['ins_en'] ?? '', C::INSTRUCTIONS_MAX),
        ];
    }

    /** Ligne de coordonnées fictives d'un service : « canal radio · téléphone · courriel ». */
    public static function contactLine(array $service): string
    {
        return implode(' · ', array_values(array_filter([(string) ($service['radio'] ?? ''), (string) ($service['tel'] ?? ''), (string) ($service['mail'] ?? '')], static fn (string $s): bool => $s !== '')));
    }

    /**
     * Réglages « services » d'une communauté : bornés, sans doublon.
     *
     * @return array{custom: array<string, array<string, mixed>>, hidden: list<string>, order: list<string>, default: string}
     */
    public static function normalizeSettings(mixed $raw): array
    {
        $raw = is_array($raw) ? $raw : [];
        $custom = [];
        foreach (is_array($raw['custom'] ?? null) ? $raw['custom'] : [] as $code => $row) {
            if (!is_array($row) || count($custom) >= self::MAX_CUSTOM) {
                continue;
            }
            $n = self::normalize(['code' => is_string($code) ? $code : (string) ($row['code'] ?? '')] + $row, 'custom');
            if ($n !== null && str_starts_with($n['code'], 'CSV-') && !isset($custom[$n['code']])) {
                $n['enabled'] = !array_key_exists('enabled', $row) || (bool) $row['enabled'];
                $custom[$n['code']] = $n;
            }
        }
        $known = array_merge(array_keys(self::catalogue()), array_keys($custom));
        $pickKnown = static function (mixed $list) use ($known): array {
            $out = [];
            foreach (is_array($list) ? $list : [] as $c) {
                if (is_string($c) && in_array($c, $known, true) && !in_array($c, $out, true)) {
                    $out[] = $c;
                }
            }

            return $out;
        };
        $default = is_string($raw['default'] ?? null) && in_array($raw['default'], $known, true) ? (string) $raw['default'] : '';

        return ['custom' => $custom, 'hidden' => $pickKnown($raw['hidden'] ?? []), 'order' => $pickKnown($raw['order'] ?? []), 'default' => $default];
    }

    /**
     * Services de la communauté : catalogue et services créés, dans l'ordre choisi (les autres à la suite). Les services masqués ou
     * désactivés portent « enabled » faux ; {@see self::available()} ne garde que les autres.
     *
     * @param array<string, mixed> $config réglages normalisés
     * @return array<string, array<string, mixed>>
     */
    public static function all(array $config): array
    {
        $s = self::normalizeSettings($config['services'] ?? []);
        $all = [];
        foreach (self::catalogue() as $code => $svc) {
            $svc['enabled'] = !in_array($code, $s['hidden'], true);
            $all[$code] = $svc;
        }
        foreach ($s['custom'] as $code => $svc) {
            $all[$code] = $svc;
        }
        $ordered = [];
        foreach ($s['order'] as $code) {
            if (isset($all[$code])) {
                $ordered[$code] = $all[$code];
            }
        }
        foreach ($all as $code => $svc) {
            $ordered[$code] ??= $svc;
        }

        return $ordered;
    }

    /** @return array<string, array<string, mixed>> services proposés dans le formulaire */
    public static function available(array $config): array
    {
        return array_filter(self::all($config), static fn (array $s): bool => $s['enabled'] === true);
    }

    /** Un service de la communauté (même masqué : un avis déjà posé reste lisible), ou null. */
    public static function find(string $code, array $config): ?array
    {
        return self::all($config)[strtoupper(trim($code))] ?? null;
    }

    public static function defaultCode(array $config): string
    {
        $s = self::normalizeSettings($config['services'] ?? []);
        $avail = self::available($config);

        return $s['default'] !== '' && isset($avail[$s['default']]) ? $s['default'] : '';
    }

    public static function classRank(string $class): int
    {
        $i = array_search($class, SseUsClassification::codes(), true);

        return is_int($i) ? $i : 1;
    }

    /** Le service peut-il émettre à ce niveau de classification ? */
    public static function canIssueAt(array $service, string $class): bool
    {
        return self::classRank($class) <= self::classRank((string) ($service['max'] ?? 'encadrement'));
    }

    /**
     * Valeurs reprises dans le formulaire quand on choisit ce service.
     *
     * @return array<string, mixed>
     */
    public static function prefill(array $service, string $lang = 'fr'): array
    {
        $en = $lang === 'en';
        $name = (string) ($en ? $service['en'] : $service['fr']);
        $label = $service['ac'] !== '' ? $name . ' (' . $service['ac'] . ')' : $name;
        $ins = (string) ($en ? $service['ins_en'] : $service['ins_fr']);

        return [
            'contact_label' => mb_substr($label, 0, 160),
            'contact_detail' => mb_substr(self::contactLine($service), 0, 200),
            'authority' => (string) ($en ? $service['auth_en'] : $service['auth_fr']),
            'classification' => (string) $service['class'],
            'threat_level' => (string) $service['thr'],
            'expiry_days' => (int) $service['exp'],
            'instructions' => $ins !== '' ? $ins : null,
            'notice_types' => $service['types'],
            'threats' => $service['threats'],
        ];
    }

    /**
     * Données du sélecteur du formulaire (JSON pour le navigateur).
     *
     * @return array<string, mixed>
     */
    public static function clientData(array $config, string $lang = 'fr'): array
    {
        $en = $lang === 'en';
        $palette = self::palette();
        $families = [];
        foreach (self::families() as $key => $f) {
            $families[$key] = $en ? $f['en'] : $f['fr'];
        }
        $items = [];
        foreach (self::available($config) as $code => $svc) {
            $items[] = [
                'code' => $code,
                'family' => $svc['family'],
                'name' => $en ? $svc['en'] : $svc['fr'],
                'ac' => $svc['ac'],
                'roles' => array_map(static fn (string $r): string => self::ROLES[$r][$en ? 1 : 0], $svc['roles']),
                'contact' => self::contactLine($svc),
                'max' => $svc['max'],
                'maxLabel' => SseUsClassification::abbr((string) $svc['max']),
                'color' => $palette[$svc['color']] ?? '#1F3A5F',
                'emblem' => $svc['emblem'],
                'custom' => $svc['source'] === 'custom',
                'prefill' => self::prefill($svc, $lang),
            ];
        }
        $ranks = [];
        foreach (SseUsClassification::codes() as $c) {
            $ranks[$c] = ['rank' => self::classRank($c), 'abbr' => SseUsClassification::abbr($c), 'label' => SseUsClassification::label($c, $lang)];
        }

        return ['families' => $families, 'items' => $items, 'default' => self::defaultCode($config), 'classes' => $ranks];
    }

    // --------------------------------------------------------------------------------------------- Validation

    /**
     * Valide la saisie d'un service créé par la communauté (tiroir « Nouveau service »).
     *
     * @param array<string, mixed> $in
     * @param array<string, mixed> $config réglages de la communauté (doublons de code)
     * @param string|null $editing code du service modifié (son propre code n'est pas un doublon)
     * @param string|null $wantedCode code « CSV-… » souhaité pour un nouveau service (import) : gardé s'il est libre
     * @return array{service: array<string, mixed>|null, errors: list<string>}
     */
    public static function validate(array $in, array $config, ?string $editing = null, ?string $wantedCode = null): array
    {
        $errors = [];
        $fr = C::line($in['fr'] ?? '', 120);
        $en = C::line($in['en'] ?? '', 120);
        $ac = strtoupper(C::line($in['ac'] ?? '', 12));
        foreach (['fr' => $fr, 'en' => $en] as $label => $name) {
            if ($label === 'fr' && mb_strlen($name) < 3) {
                $errors[] = 'Le nom français du service compte au moins trois caractères.';
            } elseif ($name !== '' && preg_match(self::NAME_RE, $name) !== 1) {
                $errors[] = 'Le nom du service ne contient que des lettres, chiffres et signes courants (. , \' ( ) / & : · + –).';
            }
        }
        if ($ac !== '' && preg_match('/^[A-Z0-9-]{2,12}$/', $ac) !== 1) {
            $errors[] = 'Le sigle compte 2 à 12 lettres, chiffres ou tirets.';
        }
        $radio = C::line($in['radio'] ?? '', 80);
        if ($radio !== '' && preg_match('/^[\p{L}\p{N} .,:\/#()\'’·+-]+$/u', $radio) !== 1) {
            $errors[] = 'Le canal radio ne contient que des lettres, chiffres et signes simples.';
        }
        $tel = C::line($in['tel'] ?? '', 24);
        if ($tel !== '' && preg_match(self::PHONE_RE, $tel) !== 1) {
            $errors[] = 'Le téléphone doit être fictif : une ligne « 555 01xx » (par exemple +1 555 0142).';
        }
        $mail = strtolower(C::line($in['mail'] ?? '', 80));
        if ($mail !== '' && preg_match(self::MAIL_RE, $mail) !== 1) {
            $errors[] = 'Le courriel doit être fictif : un domaine en « .invalid », « .example » ou « .test ».';
        }
        $texts = [$fr, $en, $ac, $radio, $tel, $mail, (string) ($in['auth_fr'] ?? ''), (string) ($in['auth_en'] ?? ''), (string) ($in['ins_fr'] ?? ''), (string) ($in['ins_en'] ?? '')];
        $hit = SseWantedRealNames::find(implode(' ', $texts));
        if ($hit !== null) {
            $errors[] = 'Un service de jeu de rôle ne porte pas le nom d’une organisation réelle (« ' . $hit . ' ») : choisissez un nom fictif.';
        }
        $existing = self::all($config);
        $editing = $editing !== null ? strtoupper($editing) : null;
        $wantedCode = $wantedCode !== null ? strtoupper($wantedCode) : null;
        $code = $editing ?? ($wantedCode !== null && str_starts_with($wantedCode, 'CSV-') && preg_match(self::CODE_RE, $wantedCode) === 1 && !isset($existing[$wantedCode])
            ? $wantedCode
            : self::freshCode($ac !== '' ? $ac : $fr, array_keys($existing)));
        if ($editing === null) {
            foreach ($existing as $other) {
                if ($other['source'] === 'custom' && mb_strtolower($other['fr']) === mb_strtolower($fr) && $fr !== '') {
                    $errors[] = 'Un service porte déjà ce nom dans votre communauté.';
                    break;
                }
            }
            if (count(array_filter($existing, static fn (array $s): bool => $s['source'] === 'custom')) >= self::MAX_CUSTOM) {
                $errors[] = 'Votre communauté a atteint la limite de ' . self::MAX_CUSTOM . ' services propres.';
            }
        } elseif (!isset($existing[$editing]) || $existing[$editing]['source'] !== 'custom') {
            $errors[] = 'Service introuvable.';
        }
        $row = $in;
        $row['code'] = $code;
        $row['fr'] = $fr;
        $row['en'] = $en;
        $row['ac'] = $ac;
        $row['roles'] = is_array($in['roles'] ?? null) ? $in['roles'] : [];
        $row['threats'] = is_array($in['threats'] ?? null) ? $in['threats'] : [];
        $row['types'] = is_array($in['types'] ?? null) ? $in['types'] : [];
        $row['lang'] = is_array($in['lang'] ?? null) ? $in['lang'] : ['fr', 'en'];
        $service = self::normalize($row, 'custom');
        if ($service === null) {
            $errors[] = 'Service illisible : vérifiez le nom.';

            return ['service' => null, 'errors' => array_values(array_unique($errors))];
        }
        $service['enabled'] = !array_key_exists('enabled', $in) || !empty($in['enabled']);

        return ['service' => $service, 'errors' => array_values(array_unique($errors))];
    }

    /**
     * Code libre « CSV-… » tiré du sigle ou du nom.
     *
     * @param list<string> $taken
     */
    public static function freshCode(string $base, array $taken): string
    {
        $slug = strtoupper((string) preg_replace('/[^A-Z0-9]+/', '', strtoupper(strtr(
            mb_strtolower($base, 'UTF-8'),
            ['é' => 'e', 'è' => 'e', 'ê' => 'e', 'à' => 'a', 'â' => 'a', 'î' => 'i', 'ï' => 'i', 'ô' => 'o', 'ù' => 'u', 'û' => 'u', 'ç' => 'c']
        ))));
        $slug = substr($slug !== '' ? $slug : 'SERVICE', 0, 8);
        $code = 'CSV-' . $slug;
        for ($i = 2; in_array($code, $taken, true) && $i < 200; $i++) {
            $code = 'CSV-' . substr($slug, 0, 8) . $i;
        }

        return $code;
    }
}
