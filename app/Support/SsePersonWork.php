<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Page « Personnes » du portail SSE (vue de travail) : vocabulaire, règles de lecture et lecture des paramètres d'adresse.
 *
 * Aucune base ici : tout ce qui se calcule sur une fiche déjà lue (rang de classification, verrouillage en cours, état d'une
 * modalité biométrique, filtres de l'adresse) vit dans cette classe pour être testé sans connexion et partagé par la liste, le
 * tiroir, la fiche et l'API du jeu.
 *
 * Classification : les CODES de l'échelle (interne, encadrement, confidentiel, tres_restreint) sont ceux de SseCaseRepository et
 * de SseUsClassification (CUI, C, S, TS) ; rien n'est renommé en base. Une personne sans classification (NULL) n'est pas classifiée.
 */
final class SsePersonWork
{
    /** Réglage de communauté : au-delà de ce niveau (exclu), une fiche classifiée est verrouillée d'office. Vide : règle désactivée. */
    public const SETTING_AUTO_LOCK = 'person_auto_lock_above';

    /** Fiches lues au plus pour une page (la liste se filtre et se trie sur cet ensemble). */
    public const LOAD_LIMIT = 3000;

    public const PER_PAGE = 50;

    /** Fenêtre pendant laquelle une réouverture par le même compte ne change ni la date ni le compteur. */
    public const VIEW_WINDOW_SECONDS = 60;

    /** Fiches traitées au plus par une action groupée. */
    public const BATCH_MAX = 200;

    public const REASON_MIN = 3;

    /** @var array<string, array{0: string, 1: string}> code => [français, anglais] */
    public const CLOSE_REASONS = [
        'resolu' => ['Résolu', 'Resolved'],
        'neutralise' => ['Neutralisé', 'Neutralised'],
        'detenu_transfere' => ['Détenu transféré', 'Detainee transferred'],
        'decede' => ['Décédé', 'Deceased'],
        'doublon_fusionne' => ['Doublon fusionné', 'Duplicate merged'],
        'erreur' => ['Erreur', 'Entered in error'],
    ];

    /** @var list<string> */
    public const TABS = ['toutes', 'recherchees', 'non_signees', 'classifiees', 'verrouillees', 'closes', 'recentes'];

    /** @var array<string, string> */
    public const TAB_LABELS = [
        'toutes' => 'Toutes',
        'recherchees' => 'Recherchées',
        'non_signees' => 'Non signées',
        'classifiees' => 'Classifiées',
        'verrouillees' => 'Verrouillées',
        'closes' => 'Closes',
        'recentes' => 'Récentes',
    ];

    /** @var array<string, string> */
    public const SORT_LABELS = [
        'ouverture' => 'Dernière ouverture',
        'modification' => 'Dernière modification',
        'nom' => 'Nom',
        'priorite' => 'Priorité',
    ];

    /** @var array<string, string> */
    public const PERIOD_LABELS = [
        '24h' => 'Dernières 24 h',
        '7j' => '7 derniers jours',
        '30j' => '30 derniers jours',
    ];

    /** @var list<string> */
    public const BIO_KINDS = ['empreintes', 'iris', 'adn'];

    /** @var array<string, string> */
    public const BIO_LABELS = ['empreintes' => 'Empreintes', 'iris' => 'Iris', 'adn' => 'ADN'];

    /** Qualité en dessous de laquelle un relevé est « partiel ». */
    public const BIO_PARTIAL_BELOW = 60;

    /** Pastilles de statut : code => couleur logique. */
    public const STATUS_TONES = [
        'civil' => 'ok',
        'combattant' => 'bad',
        'detenu' => 'warn',
        'prioritaire' => 'info',
    ];

    /** Menace d'un avis de recherche : poids pour le tri par priorité. */
    private const THREAT_WEIGHT = ['low' => 1, 'medium' => 2, 'high' => 3, 'critical' => 4];

    // ------------------------------------------------------------------ Classification

    /** Rang d'un code de classification : -1 si la fiche n'est pas classifiée. */
    public static function rank(?string $code): int
    {
        $c = strtolower(trim((string) $code));

        return $c === '' || !isset(SseUsClassification::SCALE[$c]) ? -1 : (int) array_search($c, SseUsClassification::codes(), true);
    }

    public static function isClassification(?string $code): bool
    {
        return self::rank($code) >= 0;
    }

    /** Code connu de l'échelle, sinon null (non classifiée). */
    public static function cleanClassification(mixed $raw): ?string
    {
        $c = strtolower(trim(is_scalar($raw) ? (string) $raw : ''));

        return isset(SseUsClassification::SCALE[$c]) ? $c : null;
    }

    /** Étiquette courte d'une classification (CUI, C, S, TS) ou « — ». */
    public static function classificationBadge(?string $code): string
    {
        $c = self::cleanClassification($code);

        return $c === null ? '—' : SseUsClassification::abbr($c);
    }

    /** Libellé long, dans la langue demandée. */
    public static function classificationLabel(?string $code, string $lang = 'fr'): string
    {
        $c = self::cleanClassification($code);
        if ($c === null) {
            return $lang === 'en' ? 'Not classified' : 'Non classifiée';
        }

        return SseUsClassification::label($c, $lang);
    }

    /**
     * Niveaux que l'éditeur peut poser : jamais au-dessus de sa propre habilitation.
     *
     * @return list<string> codes, du plus large au plus restreint
     */
    public static function assignableLevels(string $viewerLevel): array
    {
        $max = self::rank($viewerLevel);

        return array_values(array_filter(SseUsClassification::codes(), static fn (string $c): bool => self::rank($c) <= $max));
    }

    // ------------------------------------------------------------------ Verrouillage

    /** Seuil de la règle automatique : rang au-delà duquel une fiche classifiée est verrouillée, ou null si la règle est éteinte. */
    public static function autoThreshold(?string $setting): ?int
    {
        $code = self::cleanClassification($setting);

        return $code === null ? null : self::rank($code);
    }

    /**
     * Verrouillage d'une fiche (hydratée par SsePersonRepository).
     *
     * @param array<string, mixed> $person
     * @return array{locked: bool, manual: bool, auto: bool, required: int, until: ?string, reason: ?string, by: ?int, at: ?string}
     */
    public static function lockState(array $person, ?int $autoThreshold, ?int $now = null): array
    {
        $now ??= time();
        $rank = self::rank(is_string($person['classification'] ?? null) ? $person['classification'] : null);
        $lockedAt = trim((string) ($person['locked_at'] ?? ''));
        $until = trim((string) ($person['lock_until'] ?? ''));
        $manual = $lockedAt !== '';
        if ($manual && $until !== '') {
            $end = strtotime($until . ' UTC');
            $manual = $end === false || $end > $now;
        }
        $auto = $autoThreshold !== null && $rank > $autoThreshold;

        return [
            'locked' => $manual || $auto,
            'manual' => $manual,
            'auto' => $auto,
            // Habilitation exigée : la classification de la fiche (plancher de l'échelle si elle n'en porte pas).
            'required' => max(0, $rank),
            'until' => $manual && $until !== '' ? $until : null,
            'reason' => $manual ? self::textOrNull($person['lock_reason'] ?? null) : null,
            'by' => $manual && (int) ($person['locked_by'] ?? 0) > 0 ? (int) $person['locked_by'] : null,
            'at' => $manual ? $lockedAt : null,
        ];
    }

    /** @param array<string, mixed> $person */
    public static function isClosed(array $person): bool
    {
        return trim((string) ($person['closed_at'] ?? '')) !== '';
    }

    /**
     * Une fiche sort-elle du jeu (téléphones, terminal SEEK) ? Close, ou verrouillée : le jeu ne dit pas qui regarde, la
     * fiche n'est donc jamais envoyée (échec fermé).
     *
     * @param array<string, mixed> $person
     */
    public static function hiddenFromGame(array $person, ?int $autoThreshold, ?int $now = null): bool
    {
        return self::isClosed($person) || self::lockState($person, $autoThreshold, $now)['locked'];
    }

    // ------------------------------------------------------------------ Biométrie

    /**
     * État de chaque modalité à partir des relevés de la personne.
     *
     * @param list<array{kind?: string, quality?: int|null}> $samples
     * @return array<string, string> empreintes|iris|adn => present|partial|absent
     */
    public static function bioStates(array $samples): array
    {
        $best = [];
        foreach ($samples as $s) {
            $kind = SsePersonDedupe::normalizeKind((string) ($s['kind'] ?? 'empreintes'));
            $q = isset($s['quality']) && $s['quality'] !== null ? (int) $s['quality'] : 100;
            $best[$kind] = max($best[$kind] ?? -1, $q);
        }
        $out = [];
        foreach (self::BIO_KINDS as $kind) {
            $out[$kind] = !isset($best[$kind]) ? 'absent' : ($best[$kind] < self::BIO_PARTIAL_BELOW ? 'partial' : 'present');
        }

        return $out;
    }

    /** @param array<string, string> $states */
    public static function bioComplete(array $states): bool
    {
        foreach (self::BIO_KINDS as $kind) {
            if (($states[$kind] ?? 'absent') !== 'present') {
                return false;
            }
        }

        return true;
    }

    // ------------------------------------------------------------------ Priorité

    /**
     * Poids de tri « priorité » : un avis de recherche d'abord (par menace), puis la personne prioritaire, puis le combattant et le
     * détenu ; une fiche non signée passe avant une fiche signée à poids égal.
     *
     * @param array<string, mixed> $person
     */
    public static function priority(array $person, ?string $threat, bool $signed): int
    {
        $score = $threat !== null ? 100 + (self::THREAT_WEIGHT[$threat] ?? 1) * 10 : 0;
        $score += match ((string) ($person['status'] ?? 'civil')) {
            'prioritaire' => 50,
            'combattant' => 20,
            'detenu' => 10,
            default => 0,
        };

        return $score + ($signed ? 0 : 1);
    }

    // ------------------------------------------------------------------ Adresse (filtres partageables)

    /**
     * Paramètres de la page, nettoyés : une valeur inconnue disparaît, jamais d'erreur.
     *
     * @param array<string, mixed> $raw
     * @return array{q: string, tab: string, status: string, cls: string, wanted: string, signed: string, bio: string, nat: string, ctx: int, period: string, sort: string, p: int, vue: string, open: int}
     */
    public static function cleanQuery(array $raw): array
    {
        $str = static fn (string $k, int $max = 120): string => mb_substr(trim(is_scalar($raw[$k] ?? null) ? (string) $raw[$k] : ''), 0, $max);
        $pick = static fn (string $k, array $allowed): string => in_array($str($k, 40), $allowed, true) ? $str($k, 40) : '';
        $statuses = array_keys(\App\Repositories\SsePersonRepository::STATUS_LABELS);
        $cls = $str('cls', 20);
        $tab = $pick('tab', self::TABS);
        $sort = $pick('sort', array_keys(self::SORT_LABELS));
        $vue = $pick('vue', ['tableau', 'cartes']);

        return [
            'q' => $str('q'),
            'tab' => $tab !== '' ? $tab : 'toutes',
            'status' => $pick('status', $statuses),
            'cls' => $cls === 'none' || self::isClassification($cls) ? $cls : '',
            'wanted' => $pick('wanted', ['1', '0']),
            'signed' => $pick('signed', ['1', '0']),
            'bio' => $pick('bio', ['full', 'partial']),
            'nat' => $str('nat', 80),
            'ctx' => max(0, (int) ($raw['ctx'] ?? 0)),
            'period' => $pick('period', array_keys(self::PERIOD_LABELS)),
            'sort' => $sort !== '' ? $sort : 'ouverture',
            'p' => max(1, (int) ($raw['p'] ?? 1)),
            'vue' => $vue,
            'open' => max(0, (int) ($raw['open'] ?? 0)),
        ];
    }

    /**
     * Adresse relative de la page avec les paramètres non vides (valeurs par défaut omises).
     *
     * @param array<string, mixed> $query
     * @param array<string, mixed> $override
     */
    public static function queryString(array $query, array $override = []): string
    {
        $q = array_merge($query, $override);
        $defaults = ['tab' => 'toutes', 'sort' => 'ouverture', 'p' => 1, 'ctx' => 0, 'open' => 0];
        $out = [];
        foreach (['q', 'tab', 'status', 'cls', 'wanted', 'signed', 'bio', 'nat', 'ctx', 'period', 'sort', 'p', 'vue', 'open'] as $k) {
            $v = $q[$k] ?? '';
            if ($v === '' || $v === null || (isset($defaults[$k]) && $v === $defaults[$k])) {
                continue;
            }
            $out[$k] = $v;
        }

        return http_build_query($out, '', '&', PHP_QUERY_RFC3986);
    }

    /** Libellé d'un motif de clôture. */
    public static function closeReasonLabel(?string $code, string $lang = 'fr'): string
    {
        $c = strtolower(trim((string) $code));

        return isset(self::CLOSE_REASONS[$c]) ? self::CLOSE_REASONS[$c][$lang === 'en' ? 1 : 0] : '';
    }

    private static function textOrNull(mixed $v): ?string
    {
        $s = trim((string) $v);

        return $s === '' ? null : $s;
    }
}
