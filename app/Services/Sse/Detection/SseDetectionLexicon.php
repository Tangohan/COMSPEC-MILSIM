<?php

declare(strict_types=1);

namespace App\Services\Sse\Detection;

/**
 * Lexique de Cerbère-Lecture : le fichier livré (config/sse_detection_lexicon.php) et, par-dessus, les réglages d'une
 * communauté (règle désactivée, poids, termes, exclusions, contexte requis). Compile chaque terme en motif de mots entiers
 * pour SseDetectionEngine. Aucune base ici : les réglages arrivent déjà lus (SseDetectionRepository::settings()).
 */
final class SseDetectionLexicon
{
    public const TARGETS = ['theme', 'urgency', 'kind'];

    public const TERMS_MAX = 80;

    public const TERM_LENGTH_MAX = 80;

    /** @var array<string, mixed>|null */
    private static ?array $shipped = null;

    /** @var array<string, array<string, mixed>> règles effectives */
    private array $rules;

    /** @var array<string, array<string, mixed>>|null */
    private ?array $compiled = null;

    /** @var array<string, array<string, mixed>>|null */
    private ?array $contexts = null;

    /**
     * @param array<string, array{enabled?: bool, weight?: int|null, terms?: array<string, int|null>|null, exclusions?: list<string>|null, requires?: list<string>|null, updated_at?: string, updated_by?: string}> $settings
     */
    public function __construct(private array $settings = [])
    {
        $this->rules = [];
        foreach (self::shipped()['rules'] as $id => $rule) {
            $this->rules[$id] = self::effective((string) $id, $rule, $settings[$id] ?? null);
        }
    }

    /** @return array{version: string, thresholds: array{visible: int, review: int}, contexts: array<string, array<string, mixed>>, false_friends: array<string, string>, rules: array<string, array<string, mixed>>} */
    public static function shipped(): array
    {
        if (self::$shipped === null) {
            $raw = require dirname(__DIR__, 4) . '/config/sse_detection_lexicon.php';
            $raw = is_array($raw) ? $raw : [];
            $rules = [];
            foreach ((array) ($raw['rules'] ?? []) as $id => $r) {
                $rules[(string) $id] = [
                    'id' => (string) $id,
                    'target' => (string) $r['target'],
                    'value' => (string) $r['value'],
                    'label' => (string) $r['label'],
                    'explain' => (string) ($r['explain'] ?? ''),
                    'weight' => max(0, min(100, (int) ($r['weight'] ?? 50))),
                    'terms' => self::termMap((array) ($r['terms'] ?? [])),
                    'exclusions' => array_values(array_map('strval', (array) ($r['exclusions'] ?? []))),
                    'requires' => array_values(array_map('strval', (array) ($r['requires'] ?? []))),
                    'kinds' => array_values(array_map('strval', (array) ($r['kinds'] ?? []))),
                    'level' => (string) ($r['level'] ?? ''),
                    'immediacy' => !empty($r['immediacy']),
                ];
            }
            self::$shipped = [
                'version' => (string) ($raw['version'] ?? ''),
                'thresholds' => [
                    'visible' => (int) ($raw['thresholds']['visible'] ?? 55),
                    'review' => (int) ($raw['thresholds']['review'] ?? 30),
                ],
                'contexts' => (array) ($raw['contexts'] ?? []),
                'false_friends' => array_map('strval', (array) ($raw['false_friends'] ?? [])),
                'rules' => $rules,
            ];
        }

        return self::$shipped;
    }

    /**
     * Termes d'une règle sous la forme « terme => poids propre ou null ».
     *
     * @param array<int|string, mixed> $terms
     * @return array<string, int|null>
     */
    private static function termMap(array $terms): array
    {
        $out = [];
        foreach ($terms as $k => $v) {
            if (is_int($k)) {
                $out[(string) $v] = null;
            } else {
                $out[(string) $k] = $v === null ? null : max(0, min(100, (int) $v));
            }
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $rule
     * @param array<string, mixed>|null $set
     * @return array<string, mixed>
     */
    private static function effective(string $id, array $rule, ?array $set): array
    {
        $rule['enabled'] = true;
        $rule['customized'] = false;
        $rule['shipped_weight'] = $rule['weight'];
        $rule['updated_at'] = '';
        $rule['updated_by'] = '';
        if ($set === null) {
            return $rule;
        }
        $rule['customized'] = true;
        $rule['enabled'] = !array_key_exists('enabled', $set) || (bool) $set['enabled'];
        if (isset($set['weight']) && $set['weight'] !== null) {
            $rule['weight'] = max(0, min(100, (int) $set['weight']));
        }
        foreach (['terms', 'exclusions', 'requires'] as $field) {
            if (isset($set[$field]) && is_array($set[$field])) {
                $rule[$field] = $field === 'terms' ? self::termMap($set[$field]) : array_values(array_map('strval', $set[$field]));
            }
        }
        $rule['updated_at'] = (string) ($set['updated_at'] ?? '');
        $rule['updated_by'] = (string) ($set['updated_by'] ?? '');

        return $rule;
    }

    public function version(): string
    {
        return self::shipped()['version'];
    }

    /** @return array{visible: int, review: int} */
    public function thresholds(): array
    {
        return self::shipped()['thresholds'];
    }

    /** @return array<string, string> */
    public function falseFriends(): array
    {
        return self::shipped()['false_friends'];
    }

    /** @return array<string, array<string, mixed>> toutes les règles, actives ou non */
    public function rules(): array
    {
        return $this->rules;
    }

    /** @return array<string, mixed>|null */
    public function rule(string $id): ?array
    {
        return $this->rules[$id] ?? null;
    }

    public static function isRuleId(string $id): bool
    {
        return isset(self::shipped()['rules'][$id]);
    }

    /**
     * Règles actives compilées : chaque terme en suite de mots, avec son poids effectif.
     *
     * @return array<string, array{rule: array<string, mixed>, terms: list<array{raw: string, weight: int, words: list<array<string, mixed>>}>, exclusions: list<array{raw: string, words: list<array<string, mixed>>}>, requires: list<array{raw: string, words: list<array<string, mixed>>}>}>
     */
    public function compiled(): array
    {
        if ($this->compiled !== null) {
            return $this->compiled;
        }
        $this->compiled = [];
        foreach ($this->rules as $id => $rule) {
            if (!$rule['enabled']) {
                continue;
            }
            // Un poids réglé déplace d'autant les poids propres des termes.
            $scale = $rule['shipped_weight'] > 0 ? $rule['weight'] / $rule['shipped_weight'] : 1.0;
            $terms = [];
            foreach ($rule['terms'] as $raw => $own) {
                $words = self::compile((string) $raw);
                if ($words !== []) {
                    $w = $own === null ? $rule['weight'] : (int) round(min(100, $own * $scale));
                    $terms[] = ['raw' => (string) $raw, 'weight' => max(0, min(100, $w)), 'words' => $words];
                }
            }
            $this->compiled[$id] = [
                'rule' => $rule,
                'terms' => $terms,
                'exclusions' => self::compileList($rule['exclusions']),
                'requires' => self::compileList($rule['requires']),
            ];
        }

        return $this->compiled;
    }

    /** @return array<string, array{factor: float, before: int, after: int, cues: list<array{raw: string, words: list<array<string, mixed>>}>}> */
    public function contexts(): array
    {
        if ($this->contexts !== null) {
            return $this->contexts;
        }
        $this->contexts = [];
        foreach (self::shipped()['contexts'] as $name => $c) {
            $this->contexts[(string) $name] = [
                'factor' => (float) ($c['factor'] ?? 1.0),
                'before' => (int) ($c['before'] ?? 0),
                'after' => (int) ($c['after'] ?? 0),
                'cues' => self::compileList(array_map('strval', (array) ($c['cues'] ?? []))),
            ];
        }

        return $this->contexts;
    }

    /**
     * @param list<string> $list
     * @return list<array{raw: string, words: list<array<string, mixed>>}>
     */
    private static function compileList(array $list): array
    {
        $out = [];
        foreach ($list as $raw) {
            $words = self::compile($raw);
            if ($words !== []) {
                $out[] = ['raw' => $raw, 'words' => $words];
            }
        }

        return $out;
    }

    /**
     * Un terme en suite de mots : chaque mot donne ses formes acceptées (forme de base, suffixes déclarés) ou une racine.
     *
     * @return list<array{forms: array<string, true>, prefix: string}>
     */
    public static function compile(string $raw): array
    {
        $out = [];
        foreach (SseTextNormalizer::patternWords($raw) as $word) {
            if (str_ends_with($word, '*')) {
                $stem = rtrim($word, '*');
                if (mb_strlen($stem) >= 3) {
                    $out[] = ['forms' => [], 'prefix' => $stem];
                }
                continue;
            }
            $forms = [];
            if (preg_match('/^([^(]+)\(([^)]*)\)$/u', $word, $m) === 1) {
                $forms[$m[1]] = true;
                foreach (explode('|', $m[2]) as $suffix) {
                    $forms[$m[1] . $suffix] = true;
                }
            } else {
                $forms[$word] = true;
            }
            $out[] = ['forms' => $forms, 'prefix' => ''];
        }

        return $out;
    }

    /**
     * Texte d'une liste de termes pour la fenêtre d'édition : une ligne par terme, « terme = poids » s'il a son poids.
     *
     * @param array<string, int|null> $terms
     */
    public static function termsToText(array $terms): string
    {
        $lines = [];
        foreach ($terms as $raw => $own) {
            $lines[] = $own === null ? (string) $raw : $raw . ' = ' . $own;
        }

        return implode("\n", $lines);
    }

    /**
     * Lit la fenêtre d'édition : une ligne par terme, « terme = poids » facultatif. Les lignes vides et doublons sont retirés.
     *
     * @return array<string, int|null>
     */
    public static function parseTerms(string $text, bool $withWeights = true): array
    {
        $out = [];
        foreach (preg_split('/\R/u', $text) ?: [] as $line) {
            $line = trim(str_replace("\u{00A0}", ' ', $line));
            if ($line === '') {
                continue;
            }
            $own = null;
            if ($withWeights && preg_match('/^(.+?)\s*=\s*(\d{1,3})$/u', $line, $m) === 1) {
                $line = trim($m[1]);
                $own = max(0, min(100, (int) $m[2]));
            }
            $line = mb_substr($line, 0, self::TERM_LENGTH_MAX);
            if ($line === '' || self::compile($line) === [] || array_key_exists($line, $out)) {
                continue;
            }
            $out[$line] = $own;
            if (count($out) >= self::TERMS_MAX) {
                break;
            }
        }

        return $out;
    }
}
