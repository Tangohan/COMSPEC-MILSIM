<?php

declare(strict_types=1);

namespace App\Services\Personnel;

use App\Support\QualificationAdminStatus;
use App\Support\QualificationCriteriaCatalog as Cat;

/**
 * Règle d'obtention automatique d'une qualification : un arbre « toutes / au moins une » de blocs, chaque bloc
 * « toutes / au moins une » de critères (seuil mesuré, formation Academy réussie, autre qualification détenue,
 * condition non mesurée attestée par un instructeur).
 *
 * Généralise PilotQualificationEngine (un palier = des seuils d'heures + un palier précédent) à toutes les données
 * mesurées par le site. Pur : ni base, ni session ; les mesures sont fournies par des fonctions, testable seul.
 *
 * Format stocké (qualification_award_rules.rule_json) :
 * {"op":"all","groups":[{"op":"any","items":[{"type":"metric","metric":"flight_hours","scope":"family:uh60","min":10,"window_days":0}, …]}, …]}
 */
final class QualificationCriteriaEngine
{
    public const HELD = 'held';
    public const PENDING = 'pending';
    public const BLOCKED = 'blocked';
    public const ELIGIBLE = 'eligible';
    public const PROGRESS = 'progress';
    /** Obtenue mais expirée ou bientôt expirée, et règle toujours remplie : à renouveler. */
    public const RENEW = 'renew';

    /**
     * Règle propre : opérateurs connus, critères connus, seuils bornés, au plus 8 blocs de 12 critères. Une règle
     * sans critère vaut tableau vide (pas d'obtention automatique).
     *
     * @param mixed $raw tableau ou JSON
     * @return array{op: string, groups: list<array{op: string, items: list<array<string, mixed>>}>}|array{}
     */
    public static function normalize(mixed $raw): array
    {
        if (is_string($raw)) {
            $raw = trim($raw) === '' ? [] : json_decode($raw, true);
        }
        if (!is_array($raw)) {
            return [];
        }
        $groupsIn = is_array($raw['groups'] ?? null) ? $raw['groups'] : [];
        // Forme courte : une liste de critères à la racine = un seul bloc.
        if ($groupsIn === [] && is_array($raw['items'] ?? null)) {
            $groupsIn = [['op' => 'all', 'items' => $raw['items']]];
        }
        $groups = [];
        foreach (array_slice($groupsIn, 0, Cat::MAX_GROUPS) as $g) {
            if (!is_array($g)) {
                continue;
            }
            $items = [];
            foreach (array_slice(is_array($g['items'] ?? null) ? $g['items'] : [], 0, Cat::MAX_ITEMS) as $c) {
                $clean = is_array($c) ? self::normalizeCriterion($c) : null;
                if ($clean !== null) {
                    $items[] = $clean;
                }
            }
            if ($items !== []) {
                $groups[] = ['op' => self::op($g['op'] ?? 'all'), 'items' => $items];
            }
        }
        if ($groups === []) {
            return [];
        }

        return ['op' => self::op($raw['op'] ?? 'all'), 'groups' => $groups];
    }

    /** @param array<string, mixed> $c @return array<string, mixed>|null */
    public static function normalizeCriterion(array $c): ?array
    {
        $type = (string) ($c['type'] ?? 'metric');
        if ($type === 'academy') {
            // Cours intégré de l’Academy (JTAC, cours inspirés des standards OTAN) : désigné par sa clé.
            $key = (string) ($c['course_key'] ?? '');
            if ($key !== '') {
                return preg_match('/\A[a-z0-9-]{1,64}\z/', $key) === 1 ? ['type' => 'academy', 'course_key' => $key] : null;
            }
            $id = (int) ($c['course_id'] ?? 0);

            return $id > 0 ? ['type' => 'academy', 'course_id' => $id] : null;
        }
        if ($type === 'qualification') {
            $id = (int) ($c['definition_id'] ?? 0);

            return $id > 0 ? ['type' => 'qualification', 'definition_id' => $id] : null;
        }
        if ($type === 'attestation') {
            // Condition non mesurée par le jeu : un instructeur l'atteste (nombre de sauts, épreuve réussie…).
            $key = (string) ($c['key'] ?? 'other');
            if (!Cat::isAttestation($key)) {
                $key = 'other';
            }
            $label = trim(preg_replace('/\s+/u', ' ', (string) ($c['label'] ?? '')) ?? '');
            $label = mb_substr($label !== '' ? $label : Cat::attestationLabel($key), 0, 160);
            $min = Cat::attestationCounts($key) ? round(max(0.0, min(100000.0, (float) str_replace(',', '.', (string) ($c['min'] ?? 0)))), 2) : 0.0;
            if (Cat::attestationCounts($key) && $min <= 0) {
                return null;
            }

            return ['type' => 'attestation', 'key' => $key, 'label' => $label, 'min' => $min];
        }
        $metric = (string) ($c['metric'] ?? '');
        if (!Cat::isMetric($metric)) {
            return null;
        }
        $min = round(max(0.0, min(100000.0, (float) str_replace(',', '.', (string) ($c['min'] ?? 0)))), 2);
        if ($min <= 0) {
            return null;
        }
        $window = (int) ($c['window_days'] ?? 0);
        if (!Cat::windowAllowed($metric) || !in_array($window, Cat::WINDOWS, true)) {
            $window = 0;
        }

        return [
            'type' => 'metric',
            'metric' => $metric,
            'scope' => Cat::normalizeScope($metric, (string) ($c['scope'] ?? '')),
            'min' => $min,
            'window_days' => $window,
        ];
    }

    private static function op(mixed $op): string
    {
        return $op === 'any' ? 'any' : 'all';
    }

    /**
     * Évalue une règle pour un membre.
     *
     * @param array<string, mixed> $rule règle normalisée
     * @param callable(array<string, mixed>): ?float $measure valeur mesurée d'un critère (null : donnée absente, compte 0)
     * @param callable(int): bool $holdsQualification le membre détient-il cette qualification (valide)
     * @param callable(int|string): bool $passedCourse le membre a-t-il une attestation valide de cette formation (id) ou
     *                                                 terminé ce cours intégré (clé)
     * @param (callable(array<string, mixed>): ?float)|null $attested valeur attestée par un instructeur pour une
     *                                                 condition non mesurée (null : pas encore attestée)
     * @return array{met: bool, pct: int, op: string, groups: list<array{op: string, met: bool, pct: int, items: list<array{criterion: array<string, mixed>, met: bool, current: float, target: float, pct: int}>}>}
     */
    public static function evaluate(array $rule, callable $measure, callable $holdsQualification, callable $passedCourse, ?callable $attested = null): array
    {
        $groups = [];
        foreach ($rule['groups'] ?? [] as $g) {
            $items = [];
            foreach ($g['items'] as $c) {
                $type = (string) ($c['type'] ?? 'metric');
                if ($type === 'academy' || $type === 'qualification') {
                    $ok = $type === 'academy'
                        ? $passedCourse(isset($c['course_key']) ? (string) $c['course_key'] : (int) $c['course_id'])
                        : $holdsQualification((int) $c['definition_id']);
                    $items[] = ['criterion' => $c, 'met' => $ok, 'current' => $ok ? 1.0 : 0.0, 'target' => 1.0, 'pct' => $ok ? 100 : 0];
                    continue;
                }
                if ($type === 'attestation') {
                    $value = $attested !== null ? $attested($c) : null;
                    $target = (float) ($c['min'] ?? 0);
                    if ($target <= 0) {
                        $ok = $value !== null;
                        $items[] = ['criterion' => $c, 'met' => $ok, 'current' => $ok ? 1.0 : 0.0, 'target' => 1.0, 'pct' => $ok ? 100 : 0];
                        continue;
                    }
                    $current = round(max(0.0, (float) ($value ?? 0.0)), 2);
                    $items[] = ['criterion' => $c, 'met' => $current >= $target, 'current' => $current, 'target' => $target, 'pct' => (int) floor(min(1.0, $current / $target) * 100)];
                    continue;
                }
                $target = (float) ($c['min'] ?? 0);
                $current = round(max(0.0, (float) ($measure($c) ?? 0.0)), 2);
                $met = $current >= $target;
                $pct = $target <= 0 ? 100 : (int) floor(min(1.0, $current / $target) * 100);
                $items[] = ['criterion' => $c, 'met' => $met, 'current' => $current, 'target' => $target, 'pct' => $pct];
            }
            $groups[] = self::combine((string) $g['op'], $items) + ['items' => $items];
        }
        $root = self::combine((string) ($rule['op'] ?? 'all'), $groups);

        return ['met' => $groups !== [] && $root['met'], 'pct' => $groups === [] ? 0 : $root['pct'], 'op' => (string) ($rule['op'] ?? 'all'), 'groups' => $groups];
    }

    /**
     * « toutes » : rempli si tout l'est, avancement du moins avancé ; « au moins une » : rempli si un l'est,
     * avancement du plus avancé.
     *
     * @param list<array{met: bool, pct: int}> $parts
     * @return array{op: string, met: bool, pct: int}
     */
    private static function combine(string $op, array $parts): array
    {
        if ($parts === []) {
            return ['op' => $op, 'met' => false, 'pct' => 0];
        }
        $mets = array_map(static fn (array $p): bool => $p['met'], $parts);
        $pcts = array_map(static fn (array $p): int => $p['pct'], $parts);
        if ($op === 'any') {
            return ['op' => $op, 'met' => in_array(true, $mets, true), 'pct' => max($pcts)];
        }

        return ['op' => $op, 'met' => !in_array(false, $mets, true), 'pct' => min($pcts)];
    }

    /**
     * État d'un membre pour une qualification : comme le cursus pilote (une qualification retirée, échouée ou
     * suspendue n'est jamais redonnée toute seule), plus le renouvellement d'une qualification obtenue qui expire.
     *
     * @param array<string, mixed>|null $award meilleure attribution du membre (PilotQualificationEngine::awardsByDefinition)
     * @param string $temporal code QualificationTemporalStatusService de cette attribution
     */
    public static function state(?array $award, bool $ruleMet, bool $prereqOk, string $temporal = ''): string
    {
        $status = PilotQualificationEngine::statusOf($award);
        if ($status === QualificationAdminStatus::OBTAINED) {
            $renewable = in_array($temporal, [QualificationTemporalStatusService::EXPIRING_SOON, QualificationTemporalStatusService::EXPIRED_GRACE, QualificationTemporalStatusService::EXPIRED], true);

            return $renewable && $ruleMet && $prereqOk ? self::RENEW : self::HELD;
        }
        if (in_array($status, [QualificationAdminStatus::CANDIDATE, QualificationAdminStatus::IN_TRAINING, QualificationAdminStatus::IN_EVALUATION], true)) {
            return self::PENDING;
        }
        if (in_array($status, [QualificationAdminStatus::FAILED, QualificationAdminStatus::SUSPENDED, QualificationAdminStatus::REVOKED], true)) {
            return self::BLOCKED;
        }

        return $ruleMet && $prereqOk ? self::ELIGIBLE : self::PROGRESS;
    }

    /**
     * Critères d'une règle (tous blocs confondus).
     *
     * @param array<string, mixed> $rule
     * @return list<array<string, mixed>>
     */
    public static function criteria(array $rule): array
    {
        $out = [];
        foreach ($rule['groups'] ?? [] as $g) {
            foreach ($g['items'] as $c) {
                $out[] = $c;
            }
        }

        return $out;
    }

    /**
     * Formations dont l'attestation suffit à elle seule (règle « une de ces formations ») : leurs attestations sont
     * directement rattachées à la qualification au lieu de créer une seconde attribution.
     *
     * @param array<string, mixed> $rule
     * @return list<int>
     */
    public static function academyOnlyCourses(array $rule): array
    {
        $groups = $rule['groups'] ?? [];
        $criteria = self::criteria($rule);
        if ($criteria === []) {
            return [];
        }
        foreach ($criteria as $c) {
            // Un cours intégré n’a pas d’attestation à rattacher : la règle est évaluée membre par membre.
            if (($c['type'] ?? '') !== 'academy' || isset($c['course_key'])) {
                return [];
            }
        }
        // Une seule formation, ou « au moins une » partout : chaque formation suffit.
        $single = count($criteria) === 1;
        $anyEverywhere = (count($groups) === 1 || ($rule['op'] ?? 'all') === 'any')
            && !in_array(false, array_map(static fn (array $g): bool => $g['op'] === 'any' || count($g['items']) === 1, $groups), true);
        if (!$single && !$anyEverywhere) {
            return [];
        }

        return array_values(array_unique(array_map(static fn (array $c): int => (int) $c['course_id'], $criteria)));
    }

    /** Modes d'obtention d'une qualification, pour l'affichage et les filtres. @param array<string, mixed>|null $settings @return list<string> */
    public static function modes(?array $settings, bool $hasPilotRule): array
    {
        $modes = [];
        if ($settings === null || !empty($settings['allow_manual'])) {
            $modes[] = Cat::MODE_MANUAL;
        }
        $rule = $settings !== null && !empty($settings['auto_enabled']) ? self::normalize($settings['rule_json'] ?? null) : [];
        $types = array_map(static fn (array $c): string => (string) ($c['type'] ?? 'metric'), self::criteria($rule));
        if (in_array('academy', $types, true)) {
            $modes[] = Cat::MODE_ACADEMY;
        }
        if (in_array('metric', $types, true) || in_array('qualification', $types, true) || in_array('attestation', $types, true)) {
            $modes[] = Cat::MODE_AUTO;
        }
        if ($hasPilotRule) {
            $modes[] = Cat::MODE_PILOT;
        }

        return $modes;
    }
}
