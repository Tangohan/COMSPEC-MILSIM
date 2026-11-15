<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Codification des avis de recherche (jeu de rôle, aucune donnée réelle) : motifs « M-FAM-NN » et mesures à prendre « A-XXX »
 * du catalogue livré (config/sse_wanted_codes.php), personnalisés par communauté dans sse_wanted_settings.settings_json
 * (clé « codes », aucune migration) : désactiver, renommer, ordonner, ajouter ses codes et ses modèles, régler les codes
 * préremplis par type d'avis.
 *
 * Forme stockée avec l'avis (colonne codes_json de sse_wanted_notice_extras) : {@see self::snapshot()}. Elle porte les
 * libellés du jour de la pose : renommer ou retirer un code plus tard ne change pas les avis déjà posés.
 *
 * Forme exposée au PDF et au jeu ({@see self::export()}) : [{code, fr, en}] pour les motifs et pour les mesures, stable.
 */
final class SseWantedCodes
{
    public const REASONS = 'reasons';

    public const MEASURES = 'measures';

    public const KINDS = [self::REASONS, self::MEASURES];

    /** Codes au plus par avis et par catégorie. */
    public const MAX_SELECTED = 12;

    public const MAX_CUSTOM = 30;

    public const MAX_CUSTOM_TEMPLATES = 10;

    public const TEXT_MAX = 400;

    private const REASON_PATTERN = '/^M-[A-Z0-9]{2,4}-\d{2}$/';

    private const MEASURE_PATTERN = '/^A-[A-Z0-9]{2,4}$/';

    /** @var array<string, mixed>|null */
    private static ?array $catalogue = null;

    /** Catalogue livré (chargé une fois). @return array<string, mixed> */
    public static function catalogue(): array
    {
        if (self::$catalogue === null) {
            $data = require dirname(__DIR__, 2) . '/config/sse_wanted_codes.php';
            self::$catalogue = is_array($data) ? $data : [];
        }

        return self::$catalogue;
    }

    /** Remplace le catalogue (tests) ; null le recharge. @param array<string, mixed>|null $catalogue */
    public static function useCatalogue(?array $catalogue): void
    {
        self::$catalogue = $catalogue;
    }

    /**
     * Réglages « codes » d'une communauté, complets et bornés.
     *
     * @return array{disabled: list<string>, renamed: array<string, array{0: string, 1: string}>, custom: array<string, array<string, string>>, rank: array<string, int>, type_defaults: array<string, array{reasons: list<string>, measures: list<string>}>, templates_disabled: list<string>, custom_templates: array<string, array<string, mixed>>}
     */
    public static function normalizeSettings(mixed $raw): array
    {
        $raw = is_array($raw) ? $raw : [];
        $cat = self::catalogue();
        $known = array_merge(array_keys($cat['reasons'] ?? []), array_keys($cat['measures'] ?? []));
        $line = static fn (mixed $v, int $max): string => SseWantedCatalog::line($v, $max);

        $custom = [];
        foreach (is_array($raw['custom'] ?? null) ? $raw['custom'] : [] as $code => $c) {
            $code = is_string($code) ? strtoupper($code) : '';
            $kind = self::kindOf($code);
            if ($kind === null || in_array($code, $known, true) || !is_array($c) || count($custom) >= self::MAX_CUSTOM) {
                continue;
            }
            $fr = $line($c['fr'] ?? '', 80);
            if ($fr === '') {
                continue;
            }
            $en = $line($c['en'] ?? '', 80) ?: $fr;
            $custom[$code] = [
                'kind' => $kind,
                'family' => $kind === self::REASONS ? substr($code, 2, strpos($code, '-', 2) - 2) : 'CUS',
                'fr' => $fr, 'en' => $en,
                'text_fr' => SseWantedCatalog::line($c['text_fr'] ?? '', self::TEXT_MAX) ?: $fr . '.',
                'text_en' => SseWantedCatalog::line($c['text_en'] ?? '', self::TEXT_MAX) ?: $en . '.',
                'threat' => isset(SseWantedCatalog::THREATS[(string) ($c['threat'] ?? '')]) ? (string) $c['threat'] : 'medium',
            ];
        }
        $all = array_merge($known, array_keys($custom));

        $disabled = [];
        foreach (is_array($raw['disabled'] ?? null) ? $raw['disabled'] : [] as $code) {
            if (is_string($code) && in_array($code, $all, true)) {
                $disabled[] = $code;
            }
        }
        $renamed = [];
        foreach (is_array($raw['renamed'] ?? null) ? $raw['renamed'] : [] as $code => $labels) {
            if (is_string($code) && in_array($code, $known, true) && is_array($labels)) {
                $fr = $line($labels[0] ?? '', 80);
                if ($fr !== '') {
                    $renamed[$code] = [$fr, $line($labels[1] ?? '', 80) ?: $fr];
                }
            }
        }
        $rank = [];
        foreach (is_array($raw['rank'] ?? null) ? $raw['rank'] : [] as $code => $n) {
            if (is_string($code) && in_array($code, $all, true) && is_numeric($n) && (int) $n >= 1 && (int) $n <= 999) {
                $rank[$code] = (int) $n;
            }
        }
        $defaults = [];
        foreach (is_array($raw['type_defaults'] ?? null) ? $raw['type_defaults'] : [] as $type => $sel) {
            if (!is_string($type) || preg_match('/^[a-z0-9_]{2,24}$/', $type) !== 1 || !is_array($sel)) {
                continue;
            }
            $defaults[$type] = [
                self::REASONS => self::cleanList($sel[self::REASONS] ?? [], self::REASONS, $all),
                self::MEASURES => self::cleanList($sel[self::MEASURES] ?? [], self::MEASURES, $all),
            ];
        }
        $tplOff = [];
        foreach (is_array($raw['templates_disabled'] ?? null) ? $raw['templates_disabled'] : [] as $k) {
            if (is_string($k) && isset($cat['templates'][$k])) {
                $tplOff[] = $k;
            }
        }
        $tpl = [];
        foreach (is_array($raw['custom_templates'] ?? null) ? $raw['custom_templates'] : [] as $key => $t) {
            if (!is_string($key) || preg_match('/^[a-z0-9_]{2,24}$/', $key) !== 1 || isset($cat['templates'][$key]) || !is_array($t) || count($tpl) >= self::MAX_CUSTOM_TEMPLATES) {
                continue;
            }
            $fr = $line($t['fr'] ?? '', 80);
            if ($fr === '') {
                continue;
            }
            $tpl[$key] = [
                'fr' => $fr, 'en' => $line($t['en'] ?? '', 80) ?: $fr,
                'type' => is_string($t['type'] ?? null) && preg_match('/^[a-z0-9_]{2,24}$/', $t['type']) === 1 ? $t['type'] : '',
                'threat' => isset(SseWantedCatalog::THREATS[(string) ($t['threat'] ?? '')]) ? (string) $t['threat'] : 'medium',
                'armed' => isset(SseWantedCatalog::ARMED[(string) ($t['armed'] ?? '')]) ? (string) $t['armed'] : 'unknown',
                self::REASONS => self::cleanList($t[self::REASONS] ?? [], self::REASONS, $all),
                self::MEASURES => self::cleanList($t[self::MEASURES] ?? [], self::MEASURES, $all),
            ];
        }

        return [
            'disabled' => array_values(array_unique($disabled)), 'renamed' => $renamed, 'custom' => $custom, 'rank' => $rank,
            'type_defaults' => $defaults, 'templates_disabled' => array_values(array_unique($tplOff)), 'custom_templates' => $tpl,
        ];
    }

    /**
     * Saisie du formulaire de réglages (onglet « Codification ») vers les réglages normalisés, avec les erreurs.
     *
     * @param array<string, mixed> $input rows (codes présents dans le formulaire), enabled (liste de codes), fr / en / rank (code => valeur), custom_codes (une ligne
     *        « code|libellé fr|libellé en|texte fr|texte en|menace »), defaults_reasons / defaults_measures (type => « M-…, M-… »),
     *        templates_enabled (liste), custom_templates (« clé|fr|en|type|menace|armé|motifs|mesures »)
     * @return array{settings: array<string, mixed>, errors: list<string>}
     */
    public static function fromForm(array $input): array
    {
        $cat = self::catalogue();
        $errors = [];
        $known = array_merge(array_keys($cat['reasons'] ?? []), array_keys($cat['measures'] ?? []));
        $submitted = is_array($input['enabled'] ?? null) ? array_map('strval', $input['enabled']) : [];
        $hasForm = ($input['present'] ?? '') === '1';

        $custom = [];
        foreach (preg_split('/\R/u', SseWantedCatalog::text($input['custom_codes'] ?? '', 6000)) ?: [] as $raw) {
            $raw = trim($raw);
            if ($raw === '') {
                continue;
            }
            $p = array_map('trim', explode('|', $raw));
            $code = strtoupper($p[0]);
            if (self::kindOf($code) === null) {
                $errors[] = 'Code propre mal écrit : « ' . mb_substr($raw, 0, 40) . ' » (motif : M-ABC-01, mesure : A-ABC).';
                continue;
            }
            if (in_array($code, $known, true)) {
                $errors[] = 'Le code « ' . $code . ' » existe déjà dans le catalogue : renommez-le plutôt.';
                continue;
            }
            if (($p[1] ?? '') === '') {
                $errors[] = 'Le code « ' . $code . ' » n’a pas de libellé.';
                continue;
            }
            $custom[$code] = ['fr' => $p[1], 'en' => $p[2] ?? '', 'text_fr' => $p[3] ?? '', 'text_en' => $p[4] ?? '', 'threat' => $p[5] ?? 'medium'];
        }
        if (count($custom) > self::MAX_CUSTOM) {
            $errors[] = self::MAX_CUSTOM . ' codes propres au plus.';
        }
        $all = array_merge($known, array_keys($custom));

        // Un code absent des lignes du formulaire (nouveau code propre) reste proposé ; un code décoché est désactivé.
        $rows = is_array($input['rows'] ?? null) ? array_map('strval', $input['rows']) : [];
        $disabled = [];
        foreach ($all as $code) {
            if ($hasForm && in_array($code, $rows, true) && !in_array($code, $submitted, true)) {
                $disabled[] = $code;
            }
        }
        $renamed = [];
        $rank = [];
        foreach (self::KINDS as $kind) {
            $i = 0;
            foreach (array_keys($cat[$kind] ?? []) as $code) {
                $i++;
                $fr = SseWantedCatalog::line($input['fr'][$code] ?? '', 80);
                $en = SseWantedCatalog::line($input['en'][$code] ?? '', 80);
                $base = $cat[$kind][$code];
                if ($fr !== '' && ($fr !== $base['fr'] || ($en !== '' && $en !== $base['en']))) {
                    $renamed[$code] = [$fr, $en !== '' ? $en : $fr];
                }
                $n = $input['rank'][$code] ?? '';
                if (is_numeric($n) && (int) $n >= 1 && (int) $n <= 999 && (int) $n !== $i) {
                    $rank[$code] = (int) $n;
                }
            }
        }
        $defaults = [];
        foreach (['reasons' => self::REASONS, 'measures' => self::MEASURES] as $field => $kind) {
            foreach (is_array($input['defaults_' . $field] ?? null) ? $input['defaults_' . $field] : [] as $type => $csv) {
                if (!is_string($type)) {
                    continue;
                }
                $list = [];
                foreach (preg_split('/[\s,;]+/', strtoupper((string) $csv), -1, PREG_SPLIT_NO_EMPTY) ?: [] as $code) {
                    if (!in_array($code, $all, true) || self::kindOf($code) !== $kind) {
                        $errors[] = 'Code inconnu parmi les codes préremplis du type « ' . $type . ' » : ' . mb_substr($code, 0, 20) . '.';
                        continue;
                    }
                    $list[] = $code;
                }
                $defaults[$type][$kind] = $list;
            }
        }
        $tplOff = [];
        foreach (array_keys($cat['templates'] ?? []) as $key) {
            if ($hasForm && !in_array($key, is_array($input['templates_enabled'] ?? null) ? $input['templates_enabled'] : [], true)) {
                $tplOff[] = $key;
            }
        }
        $tpl = [];
        foreach (preg_split('/\R/u', SseWantedCatalog::text($input['custom_templates'] ?? '', 4000)) ?: [] as $raw) {
            $raw = trim($raw);
            if ($raw === '') {
                continue;
            }
            $p = array_map('trim', explode('|', $raw));
            if (preg_match('/^[a-z0-9_]{2,24}$/', $p[0]) !== 1 || ($p[1] ?? '') === '') {
                $errors[] = 'Modèle mal écrit : « ' . mb_substr($raw, 0, 40) . ' » (clé|libellé fr|libellé en|type|menace|armé|motifs|mesures).';
                continue;
            }
            $codes = array_values(array_filter(preg_split('/[\s,;]+/', strtoupper(($p[6] ?? '') . ' ' . ($p[7] ?? '')), -1, PREG_SPLIT_NO_EMPTY) ?: []));
            foreach ($codes as $code) {
                if (!in_array($code, $all, true)) {
                    $errors[] = 'Le modèle « ' . $p[0] . ' » cite un code inconnu : ' . mb_substr($code, 0, 20) . '.';
                }
            }
            $tpl[$p[0]] = [
                'fr' => $p[1], 'en' => $p[2] ?? '', 'type' => $p[3] ?? '', 'threat' => $p[4] ?? 'medium', 'armed' => $p[5] ?? 'unknown',
                self::REASONS => array_values(array_filter(preg_split('/[\s,;]+/', strtoupper($p[6] ?? ''), -1, PREG_SPLIT_NO_EMPTY) ?: [], static fn (string $c): bool => self::kindOf($c) === self::REASONS)),
                self::MEASURES => array_values(array_filter(preg_split('/[\s,;]+/', strtoupper($p[7] ?? ''), -1, PREG_SPLIT_NO_EMPTY) ?: [], static fn (string $c): bool => self::kindOf($c) === self::MEASURES)),
            ];
        }
        foreach ($custom as $code => $c) {
            $hit = SseWantedRealNames::find(implode(' ', $c));
            if ($hit !== null) {
                $errors[] = 'Le code « ' . $code . ' » cite une organisation réelle : « ' . $hit . ' ».';
            }
        }

        return [
            'settings' => self::normalizeSettings(['disabled' => $disabled, 'renamed' => $renamed, 'custom' => $custom, 'rank' => $rank, 'type_defaults' => $defaults, 'templates_disabled' => $tplOff, 'custom_templates' => $tpl]),
            'errors' => array_values(array_unique($errors)),
        ];
    }

    /** « reasons » pour M-…, « measures » pour A-…, null sinon. */
    public static function kindOf(string $code): ?string
    {
        return match (true) {
            preg_match(self::REASON_PATTERN, $code) === 1 => self::REASONS,
            preg_match(self::MEASURE_PATTERN, $code) === 1 => self::MEASURES,
            default => null,
        };
    }

    /**
     * Tous les codes d'une catégorie dans l'ordre de la communauté, avec leur état. Code => [kind, family, fr, en, text_fr,
     * text_en, threat, custom, enabled].
     *
     * @param array<string, mixed> $config réglages de la communauté (clé « codes »)
     * @return array<string, array<string, mixed>>
     */
    public static function all(array $config, string $kind): array
    {
        $kind = $kind === self::MEASURES ? self::MEASURES : self::REASONS;
        $s = self::normalizeSettings($config['codes'] ?? []);
        $out = [];
        foreach (self::catalogue()[$kind] ?? [] as $code => $c) {
            $fr = $s['renamed'][$code][0] ?? $c['fr'];
            $en = $s['renamed'][$code][1] ?? $c['en'];
            $out[$code] = $c + ['kind' => $kind, 'custom' => false] ;
            $out[$code]['fr'] = $fr;
            $out[$code]['en'] = $en;
            $out[$code]['enabled'] = !in_array($code, $s['disabled'], true);
        }
        foreach ($s['custom'] as $code => $c) {
            if ($c['kind'] === $kind) {
                $out[$code] = $c + ['custom' => true, 'enabled' => !in_array($code, $s['disabled'], true)];
            }
        }
        $pos = array_flip(array_keys($out));
        uksort($out, static function (string $a, string $b) use ($s, $pos): int {
            $ra = isset($s['rank'][$a]) ? [$s['rank'][$a], 0, $pos[$a]] : [$pos[$a] + 1, 1, $pos[$a]];
            $rb = isset($s['rank'][$b]) ? [$s['rank'][$b], 0, $pos[$b]] : [$pos[$b] + 1, 1, $pos[$b]];

            return $ra <=> $rb;
        });

        return $out;
    }

    /** Codes proposés au formulaire (actifs seulement). @param array<string, mixed> $config @return array<string, array<string, mixed>> */
    public static function active(array $config, string $kind): array
    {
        return array_filter(self::all($config, $kind), static fn (array $c): bool => $c['enabled'] === true);
    }

    /** Un code, actif ou non (les avis déjà posés restent lisibles). @param array<string, mixed> $config @return array<string, mixed>|null */
    public static function find(string $code, array $config): ?array
    {
        $kind = self::kindOf($code);

        return $kind === null ? null : (self::all($config, $kind)[$code] ?? null);
    }

    /** @param array<string, mixed> $config @return array<string, array<string, mixed>> modèles proposés (fournis actifs + propres) */
    public static function templates(array $config): array
    {
        $s = self::normalizeSettings($config['codes'] ?? []);
        $out = [];
        foreach (self::catalogue()['templates'] ?? [] as $key => $t) {
            if (!in_array($key, $s['templates_disabled'], true)) {
                $out[$key] = $t + ['custom' => false];
            }
        }
        foreach ($s['custom_templates'] as $key => $t) {
            $out[$key] = $t + ['custom' => true];
        }
        $active = [self::REASONS => self::active($config, self::REASONS), self::MEASURES => self::active($config, self::MEASURES)];
        foreach ($out as $key => $t) {
            foreach (self::KINDS as $kind) {
                $out[$key][$kind] = array_values(array_filter($t[$kind] ?? [], static fn (string $c): bool => isset($active[$kind][$c])));
            }
        }

        return $out;
    }

    /**
     * Codes préremplis pour un type d'avis : réglage de la communauté, sinon catalogue livré, sinon rien.
     *
     * @param array<string, mixed> $config
     * @return array{reasons: list<string>, measures: list<string>}
     */
    public static function defaultsForType(string $type, array $config): array
    {
        $s = self::normalizeSettings($config['codes'] ?? []);
        $base = $s['type_defaults'][$type] ?? self::catalogue()['type_defaults'][$type] ?? ['reasons' => [], 'measures' => []];
        $out = [];
        foreach (self::KINDS as $kind) {
            $active = self::active($config, $kind);
            $out[$kind] = array_values(array_filter(array_map('strval', $base[$kind] ?? []), static fn (string $c): bool => isset($active[$c])));
        }

        return $out;
    }

    /**
     * Sélection saisie vers des codes connus, sans doublon, bornée. Un code désactivé depuis n'est gardé que s'il figurait déjà
     * dans l'avis ($previous).
     *
     * @param array<string, mixed> $input reasons, measures (listes ou « A, B »)
     * @param array<string, mixed> $config
     * @param array{reasons?: list<string>, measures?: list<string>}|null $previous
     * @return array{reasons: list<string>, measures: list<string>}
     */
    public static function normalizeSelection(array $input, array $config, ?array $previous = null): array
    {
        $out = [self::REASONS => [], self::MEASURES => []];
        foreach (self::KINDS as $kind) {
            $raw = $input[$kind] ?? [];
            if (is_string($raw)) {
                $raw = preg_split('/[\s,;]+/', $raw, -1, PREG_SPLIT_NO_EMPTY) ?: [];
            }
            $all = self::all($config, $kind);
            foreach (is_array($raw) ? $raw : [] as $code) {
                $code = is_string($code) ? strtoupper(trim($code)) : '';
                if ($code === '' || !isset($all[$code]) || in_array($code, $out[$kind], true)) {
                    continue;
                }
                if (!$all[$code]['enabled'] && !in_array($code, $previous[$kind] ?? [], true)) {
                    continue;
                }
                $out[$kind][] = $code;
                if (count($out[$kind]) >= self::MAX_SELECTED) {
                    break;
                }
            }
        }

        return $out;
    }

    /**
     * Forme gardée avec l'avis : codes et libellés du jour de la pose.
     *
     * @param array{reasons: list<string>, measures: list<string>} $selection
     * @param array<string, mixed> $config
     * @return array<string, mixed>
     */
    public static function snapshot(array $selection, array $config, ?string $template = null): array
    {
        $out = ['v' => 1];
        foreach (self::KINDS as $kind) {
            $all = self::all($config, $kind);
            $out[$kind] = [];
            foreach ($selection[$kind] ?? [] as $code) {
                if (isset($all[$code])) {
                    $out[$kind][] = ['code' => $code, 'fr' => (string) $all[$code]['fr'], 'en' => (string) $all[$code]['en']];
                }
            }
        }
        $out['template'] = $template !== null && preg_match('/^[a-z0-9_]{2,24}$/', $template) === 1 ? $template : null;

        return $out;
    }

    /**
     * Relit une forme gardée (colonne JSON, éventuellement absente ou abîmée) : listes de {code, fr, en}.
     *
     * @return array{reasons: list<array{code: string, fr: string, en: string}>, measures: list<array{code: string, fr: string, en: string}>, template: ?string}
     */
    public static function readSnapshot(mixed $stored): array
    {
        if (is_string($stored)) {
            $stored = json_decode($stored, true);
        }
        $out = [self::REASONS => [], self::MEASURES => [], 'template' => null];
        if (!is_array($stored)) {
            return $out;
        }
        foreach (self::KINDS as $kind) {
            foreach (is_array($stored[$kind] ?? null) ? $stored[$kind] : [] as $c) {
                $code = is_array($c) ? (string) ($c['code'] ?? '') : (is_string($c) ? $c : '');
                if (self::kindOf($code) !== $kind) {
                    continue;
                }
                $fr = is_array($c) ? SseWantedCatalog::line($c['fr'] ?? '', 80) : '';
                $out[$kind][] = ['code' => $code, 'fr' => $fr !== '' ? $fr : $code, 'en' => (is_array($c) ? SseWantedCatalog::line($c['en'] ?? '', 80) : '') ?: ($fr !== '' ? $fr : $code)];
            }
        }
        $out['template'] = is_string($stored['template'] ?? null) ? $stored['template'] : null;

        return $out;
    }

    /**
     * Forme exposée au PDF et au jeu : stable (codes + libellés), sans texte développé.
     *
     * @return array{reasons: list<array{code: string, fr: string, en: string}>, measures: list<array{code: string, fr: string, en: string}>}
     */
    public static function export(mixed $stored): array
    {
        $s = self::readSnapshot($stored);

        return [self::REASONS => $s[self::REASONS], self::MEASURES => $s[self::MEASURES]];
    }

    /**
     * Texte développé des codes choisis, phrases mises bout à bout (une phrase par code), borné à $max caractères sans couper
     * une phrase.
     *
     * @param list<string> $codes
     * @param array<string, mixed> $config
     */
    public static function compose(array $codes, string $kind, string $lang, array $config, ?int $max = null): string
    {
        $max ??= $kind === self::MEASURES ? SseWantedCatalog::INSTRUCTIONS_MAX : SseWantedCatalog::REASON_MAX;
        $all = self::all($config, $kind);
        $parts = [];
        foreach ($codes as $code) {
            $c = $all[$code] ?? null;
            $text = $c === null ? '' : trim((string) ($lang === 'en' ? $c['text_en'] : $c['text_fr']));
            if ($text === '') {
                continue;
            }
            $parts[] = preg_match('/[.!?…]$/u', $text) === 1 ? $text : $text . '.';
        }
        $out = '';
        foreach ($parts as $p) {
            $next = $out === '' ? $p : $out . ' ' . $p;
            if (mb_strlen($next) > $max) {
                break;
            }
            $out = $next;
        }

        return $out;
    }

    /**
     * Codes dont le code ou un libellé contient le texte cherché (recherche de la liste des avis).
     *
     * @param array<string, mixed> $config
     * @return list<string>
     */
    public static function matching(string $query, array $config): array
    {
        $q = mb_strtolower(trim($query));
        if (mb_strlen($q) < 2) {
            return [];
        }
        $out = [];
        foreach (self::KINDS as $kind) {
            foreach (self::all($config, $kind) as $code => $c) {
                if (str_contains(mb_strtolower($code . ' ' . $c['fr'] . ' ' . $c['en']), $q)) {
                    $out[] = $code;
                }
            }
        }

        return $out;
    }

    /**
     * Données du sélecteur du formulaire (JSON inséré dans la page).
     *
     * @param array<string, mixed> $config
     * @return array<string, mixed>
     */
    public static function clientData(array $config, string $lang): array
    {
        $cat = self::catalogue();
        $items = [];
        foreach (self::KINDS as $kind) {
            foreach (self::active($config, $kind) as $code => $c) {
                $fam = $kind === self::REASONS ? ($cat['reason_families'][$c['family']] ?? null) : ($cat['measure_families'][$c['family']] ?? null);
                $items[] = [
                    'k' => $kind === self::REASONS ? 'r' : 'm', 'c' => $code, 'f' => $fam !== null ? $fam[$lang === 'en' ? 'en' : 'fr'] : ($lang === 'en' ? 'Custom' : 'Propres à la communauté'),
                    'fr' => $c['fr'], 'en' => $c['en'], 'tf' => $c['text_fr'], 'te' => $c['text_en'], 't' => $c['threat'],
                ];
            }
        }
        $tpl = [];
        foreach (self::templates($config) as $key => $t) {
            $tpl[] = ['key' => $key, 'fr' => $t['fr'], 'en' => $t['en'], 'type' => $t['type'] ?? '', 'threat' => $t['threat'] ?? '', 'armed' => $t['armed'] ?? '', 'r' => $t[self::REASONS], 'm' => $t[self::MEASURES]];
        }
        $defaults = [];
        foreach (array_keys(SseWantedCatalog::types($config)) as $type) {
            $defaults[$type] = self::defaultsForType($type, $config);
        }

        return ['items' => $items, 'templates' => $tpl, 'defaults' => $defaults, 'reasonMax' => SseWantedCatalog::REASON_MAX, 'measureMax' => SseWantedCatalog::INSTRUCTIONS_MAX];
    }

    /**
     * @param mixed $raw
     * @param list<string> $known
     * @return list<string>
     */
    private static function cleanList(mixed $raw, string $kind, array $known): array
    {
        $out = [];
        foreach (is_array($raw) ? $raw : [] as $code) {
            if (is_string($code) && self::kindOf($code) === $kind && in_array($code, $known, true) && !in_array($code, $out, true)) {
                $out[] = $code;
            }
        }

        return $out;
    }
}
