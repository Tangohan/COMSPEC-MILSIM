<?php

declare(strict_types=1);

namespace App\Services\Sse\Detection;

use App\Repositories\SseDetectionRepository;
use App\Services\Intel\IntelIntakePresenter;
use App\Support\LazyDatabaseConnection;
use App\Support\SseFieldNoteCatalog;
use PDO;

/**
 * Cerbère-Lecture, côté bureau : propositions d'une remontée (type, thèmes, urgence) expliquées, retour d'expérience
 * (appliquée, ignorée, mauvaise détection), page de gestion des règles et re-détection.
 *
 * Rien n'est appliqué tout seul : une proposition s'applique par le clic d'un traitant, par la même action que la saisie à la
 * main (IntelIntakeDesk). Une urgence « Critique » demande en plus de cocher « J’ai vérifié le texte ».
 */
class SseDetectionService
{
    use LazyDatabaseConnection;

    public const LABEL = 'Cerbère-Lecture';

    public const FEEDBACK_REASONS = [
        'Mot pris dans un autre sens',
        'Négation ou exercice mal lu',
        'Le texte ne parle pas de ça',
        'Déjà couvert par un autre thème',
    ];

    /** Taux de mauvaises détections à partir duquel une règle est signalée (avec au moins 3 rejets). */
    public const FLAG_RATE = 40;

    /** @var array<int, SseDetectionLexicon> */
    private array $lexicons = [];

    public function __construct(?PDO $pdo = null, private ?SseDetectionRepository $repo = null)
    {
        $this->pdo = $pdo;
    }

    public function repo(): SseDetectionRepository
    {
        return $this->repo ??= new SseDetectionRepository($this->pdo);
    }

    public function lexicon(int $tenantId): SseDetectionLexicon
    {
        return $this->lexicons[$tenantId] ??= new SseDetectionLexicon($this->repo()->settings($tenantId));
    }

    public function forget(int $tenantId): void
    {
        unset($this->lexicons[$tenantId]);
    }

    public function engine(int $tenantId): SseDetectionEngine
    {
        return new SseDetectionEngine($this->lexicon($tenantId));
    }

    /**
     * Textes lus d'une remontée : titre (titre de la fiche, résumé du compte rendu), corps, remarques, lieu.
     *
     * @param array<string, mixed> $row
     * @return array<string, string>
     */
    public static function segments(string $source, array $row): array
    {
        $s = static fn (string $k): string => trim((string) ($row[$k] ?? ''));

        return $source === 'cr'
            ? ['title' => $s('summary'), 'body' => $s('details'), 'remarks' => $s('remarks'), 'place' => $s('location_description')]
            : ['title' => $s('title'), 'body' => $s('body'), 'remarks' => $s('remarks') . ($s('summary') !== '' ? "\n" . $s('summary') : ''), 'place' => $s('place_label')];
    }

    /**
     * Propositions d'une remontée, prêtes à afficher. Celles qu'une personne a déjà appliquées, ignorées ou rejetées ne
     * reviennent pas. Rien n'est écrit.
     *
     * @param array<string, mixed> $item remontée lue par IntelIntakeService::find
     * @return list<array<string, mixed>>
     */
    public function proposals(int $tenantId, array $item): array
    {
        $source = (string) ($item['source'] ?? '');
        $id = (int) ($item['id'] ?? 0);
        $row = is_array($item['row'] ?? null) ? $item['row'] : [];
        $current = $source === 'fiche' ? SseFieldNoteCatalog::normalizeThemes($row['themes'] ?? '') : [];
        $kind = $source === 'fiche' ? strtoupper((string) ($item['type'] ?? '')) : '';
        $engine = $this->engine($tenantId);
        $result = $engine->detect(self::segments($source, $row), ['kind' => $kind, 'themes' => $current]);
        $decided = $this->repo()->decisions($tenantId, $source, $id);
        $hidden = static fn (string $target, string $value): bool => in_array($decided[$target . '|' . $value]['decision'] ?? '', SseDetectionRepository::HIDING, true);
        $version = $engine->lexicon()->version();
        $out = [];

        $themes = array_values(array_filter($result['detections'], static fn (array $d): bool => $d['target'] === 'theme'
            && $d['tier'] !== 'hidden' && isset(SseFieldNoteCatalog::THEMES[$d['value']]) && !in_array($d['value'], $current, true) && !$hidden('theme', $d['value'])));
        usort($themes, static fn (array $a, array $b): int => $b['confidence'] <=> $a['confidence']);
        // Pas plus de thèmes que la fiche n'en accepte : les plus sûrs d'abord.
        $allowedThemes = array_column(array_slice($themes, 0, max(0, SseFieldNoteCatalog::THEMES_MAX - count($current))), 'value');

        foreach ($result['detections'] as $d) {
            if ($d['tier'] === 'hidden' || $hidden($d['target'], $d['value'])) {
                continue;
            }
            $base = [
                'target' => $d['target'], 'value' => $d['value'], 'confidence' => $d['confidence'], 'tier' => $d['tier'],
                'rules' => $d['rules'], 'signals' => $d['signals'], 'version' => $version, 'critical' => false,
                'evidence' => self::evidence($d['rules']),
                'excerpts' => self::excerpts($result['analysis']['text'], $d['rules']),
                'feedback' => [
                    'cerbere_target' => $d['target'], 'cerbere_value' => $d['value'],
                    'cerbere_rules' => implode(',', array_column($d['rules'], 'id')), 'cerbere_confidence' => (string) $d['confidence'],
                ],
                'dom' => 'cdx-' . $source . '-' . $id . '-' . $d['target'] . '-' . strtolower($d['value']),
            ];
            if ($d['target'] === 'theme') {
                if ($source !== 'fiche' || !in_array($d['value'], $allowedThemes, true)) {
                    continue;
                }
                $merged = implode(',', array_merge($current, [$d['value']]));
                $out[] = $base + [
                    'key' => 'themes', 'label' => 'Thèmes', 'value_label' => SseFieldNoteCatalog::themeLabel($d['value']),
                    'action' => 'themes', 'fields' => ['themes' => $merged] + $base['feedback'],
                ];
            } elseif ($d['target'] === 'kind') {
                if ($source !== 'fiche' || $d['value'] === $kind || !isset(SseFieldNoteCatalog::KINDS[$d['value']])) {
                    continue;
                }
                $out[] = $base + [
                    'key' => 'type', 'label' => 'Type de fiche', 'value_label' => $d['value'] . ' · ' . SseFieldNoteCatalog::kindLabel($d['value']),
                    'action' => 'type', 'fields' => ['type' => $d['value']] + $base['feedback'],
                ];
            } elseif ($d['target'] === 'urgency') {
                $wanted = $d['value'] === 'critique' ? 'critical' : 'high';
                $rank = ['low' => 0, 'medium' => 1, 'high' => 2, 'critical' => 3];
                if ($rank[$wanted] <= $rank[IntelIntakePresenter::urgencyLevel((string) ($item['priority'] ?? ''))]) {
                    continue;
                }
                $code = IntelIntakePresenter::URGENCY_LEVELS[$wanted][$source === 'cr' ? 1 : 0];
                $field = $source === 'cr' ? 'priority' : 'urgency';
                $out[] = ['critical' => $wanted === 'critical'] + $base + [
                    'key' => 'urgence', 'label' => $source === 'cr' ? 'Priorité' : 'Urgence',
                    'value_label' => IntelIntakePresenter::urgencyLabel($source, $code),
                    'action' => 'edit', 'fields' => [$field => $code] + $base['feedback'],
                ];
            }
        }
        foreach ($out as &$p) {
            $p['reason'] = self::reason($p);
        }
        unset($p);
        // Les propositions sûres d'abord, puis les « à vérifier ».
        usort($out, static fn (array $a, array $b): int => [$a['tier'] === 'visible' ? 0 : 1, -$a['confidence']] <=> [$b['tier'] === 'visible' ? 0 : 1, -$b['confidence']]);

        return $out;
    }

    /**
     * Passages trouvés, tous termes confondus, dans l'ordre des règles : au plus un par terme et par contexte.
     *
     * @param list<array<string, mixed>> $rules
     * @return list<array{rule: string, term: string, context: string, before: string, match: string, after: string}>
     */
    public static function evidence(array $rules): array
    {
        $out = [];
        $seen = [];
        foreach ($rules as $r) {
            foreach ($r['hits'] as $h) {
                $key = SseTextNormalizer::fold($h['snippet']['match']) . '|' . $h['context'];
                if (isset($seen[$key])) {
                    continue;
                }
                $seen[$key] = true;
                $out[] = ['rule' => (string) $r['id'], 'term' => (string) $h['term'], 'context' => (string) $h['context']] + $h['snippet'];
            }
        }

        return $out;
    }

    /**
     * Extraits du texte où les passages trouvés sont surlignés : les passages proches partagent un même extrait.
     *
     * @param list<array<string, mixed>> $rules
     * @return list<list<array{0: string, 1: bool}>>
     */
    public static function excerpts(string $text, array $rules, int $max = 2): array
    {
        $ranges = [];
        foreach ($rules as $r) {
            foreach ($r['hits'] as $h) {
                if (isset($h['at'], $h['to'])) {
                    $ranges[] = [(int) $h['at'], (int) $h['to']];
                }
            }
        }
        if ($text === '' || $ranges === []) {
            return [];
        }
        usort($ranges, static fn (array $a, array $b): int => $a <=> $b);
        // Passages qui se chevauchent : réunis ; passages proches : même extrait.
        $groups = [];
        foreach ($ranges as [$a, $b]) {
            $g = count($groups) - 1;
            $last = $g >= 0 ? $groups[$g][count($groups[$g]) - 1] : null;
            if ($last !== null && $a < $last[1]) {
                $groups[$g][count($groups[$g]) - 1] = [$last[0], max($last[1], $b)];
            } elseif ($last !== null && $a - $last[1] <= 90) {
                $groups[$g][] = [$a, $b];
            } else {
                $groups[] = [[$a, $b]];
            }
        }

        return array_map(static fn (array $g): array => SseDetectionEngine::excerpt($text, $g, 45), array_slice($groups, 0, $max));
    }

    /** « Détecté : « prise d’otages », « blessés » · règle U-02, U-04 · confiance 82 % » */
    public static function reason(array $p): string
    {
        $terms = [];
        foreach ($p['evidence'] as $e) {
            $t = '« ' . $e['match'] . ' »' . ($e['context'] !== 'normal' ? ' (' . SseDetectionEngine::contextLabel($e['context']) . ')' : '');
            if (!in_array($t, $terms, true)) {
                $terms[] = $t;
            }
        }
        $rules = array_column($p['rules'], 'id');

        return 'Détecté : ' . implode(', ', $terms) . ' · ' . (count($rules) > 1 ? 'règles ' : 'règle ') . implode(', ', $rules) . ' · confiance ' . (int) $p['confidence'] . ' %';
    }

    /**
     * Un traitant écarte une proposition : « ignore » (pas utile ici) ou « reject » (mauvaise détection, motif obligatoire).
     *
     * @param array<string, mixed> $input
     * @param array{id: int, label: string} $actor
     * @return array{ok: bool, message: string}
     */
    public function feedback(int $tenantId, string $source, int $sourceId, array $input, array $actor): array
    {
        $decision = ['ignore' => 'ignored', 'reject' => 'rejected'][(string) ($input['decision'] ?? '')] ?? null;
        $sig = self::signature($input);
        if ($decision === null || $sig === null) {
            return ['ok' => false, 'message' => 'Proposition inconnue : rechargez la page.'];
        }
        $reason = trim((string) (is_scalar($input['reason'] ?? null) ? $input['reason'] : ''));
        $reason = mb_substr((string) preg_replace('/\s+/u', ' ', $reason), 0, 200);
        if ($decision === 'rejected' && mb_strlen($reason) < 3) {
            return ['ok' => false, 'message' => 'Dites en quelques mots pourquoi la détection est mauvaise.'];
        }
        if (!$this->repo()->ready()) {
            return ['ok' => false, 'message' => 'Retour non gardé : l’étape « sse_detection_rules » n’est pas encore passée sur ce site.'];
        }
        $ok = $this->repo()->recordDecision($tenantId, $source, $sourceId, $sig['target'], $sig['value'], $sig['rules'], $sig['confidence'], $decision, $reason, $actor, $this->lexicon($tenantId)->version());

        return $ok
            ? ['ok' => true, 'message' => $decision === 'rejected' ? 'Mauvaise détection notée : elle compte dans le taux de rejet de la règle ' . implode(', ', $sig['rules']) . '.' : 'Proposition ignorée pour cette remontée.']
            : ['ok' => false, 'message' => 'Cette proposition a déjà été appliquée.'];
    }

    /**
     * Une proposition appliquée par le traitant (les champs cerbere_* accompagnent l'action Appliquer).
     *
     * @param array<string, mixed> $input
     * @param array{id: int, label: string} $actor
     */
    public function applied(int $tenantId, string $source, int $sourceId, array $input, array $actor): void
    {
        $sig = self::signature($input);
        if ($sig !== null) {
            try {
                $this->repo()->recordDecision($tenantId, $source, $sourceId, $sig['target'], $sig['value'], $sig['rules'], $sig['confidence'], 'applied', '', $actor, $this->lexicon($tenantId)->version());
            } catch (\Throwable) {
            }
        }
    }

    /**
     * Identité d'une proposition envoyée par un formulaire, vérifiée : cible connue, valeur connue, règles du lexique.
     *
     * @param array<string, mixed> $input
     * @return array{target: string, value: string, rules: list<string>, confidence: int}|null
     */
    public static function signature(array $input): ?array
    {
        $str = static fn (string $k): string => trim((string) (is_scalar($input[$k] ?? null) ? $input[$k] : ''));
        $target = $str('cerbere_target');
        $value = $str('cerbere_value');
        $known = match ($target) {
            'theme' => isset(SseFieldNoteCatalog::THEMES[$value]),
            'kind' => isset(SseFieldNoteCatalog::KINDS[$value]),
            'urgency' => in_array($value, ['critique', 'urgent'], true),
            default => false,
        };
        if (!$known) {
            return null;
        }
        $rules = array_values(array_unique(array_filter(array_map('trim', explode(',', $str('cerbere_rules'))), static fn (string $r): bool => SseDetectionLexicon::isRuleId($r))));
        if ($rules === []) {
            return null;
        }

        return ['target' => $target, 'value' => $value, 'rules' => array_slice($rules, 0, 12), 'confidence' => max(0, min(100, (int) $str('cerbere_confidence')))];
    }

    /**
     * « Testez un texte » : ce que Cerbère-Lecture trouverait, règle par règle, sans rien écrire.
     *
     * @return array{text: string, kind: string, analysis: array<string, mixed>, detections: list<array<string, mixed>>, ms: float}
     */
    public function liveTest(int $tenantId, string $text, string $kind = ''): array
    {
        $text = mb_substr(str_replace("\r\n", "\n", $text), 0, 20000);
        $kind = isset(SseFieldNoteCatalog::KINDS[strtoupper($kind)]) ? strtoupper($kind) : '';
        $t = microtime(true);
        $r = $this->engine($tenantId)->detect(['body' => $text], ['kind' => $kind]);

        return ['text' => $text, 'kind' => $kind, 'analysis' => $r['analysis'], 'detections' => $r['detections'], 'ms' => round((microtime(true) - $t) * 1000, 1)];
    }

    /**
     * Règles de la page de gestion avec leurs chiffres : déclenchements sur 30 jours (le moteur actuel rejoué sur les remontées
     * des 30 derniers jours), décisions et taux de mauvaises détections.
     *
     * @return array{rules: list<array<string, mixed>>, ready: bool, sample: int, version: string, thresholds: array{visible: int, review: int}}
     */
    public function overview(int $tenantId): array
    {
        $lexicon = $this->lexicon($tenantId);
        $engine = new SseDetectionEngine($lexicon);
        $fired = [];
        $sample = 0;
        foreach ($this->recentTexts($tenantId, 30) as $t) {
            $sample++;
            foreach ($engine->analyze($t['segments'], ['kind' => $t['kind']])['rules'] as $id => $r) {
                if ($r['confidence'] > 0) {
                    $fired[$id] = ($fired[$id] ?? 0) + 1;
                }
            }
        }
        $stats = $this->repo()->ruleStats($tenantId, 30);
        $rows = [];
        foreach ($lexicon->rules() as $id => $rule) {
            $s = $stats[$id] ?? ['applied' => 0, 'ignored' => 0, 'rejected' => 0, 'withdrawn' => 0, 'reasons' => []];
            $decisions = $s['applied'] + $s['ignored'] + $s['rejected'];
            $rate = $decisions > 0 ? (int) round(100 * $s['rejected'] / $decisions) : null;
            $rows[] = $rule + [
                'fired' => (int) ($fired[$id] ?? 0),
                'stats' => $s,
                'reject_rate' => $rate,
                'flagged' => $rate !== null && $rate >= self::FLAG_RATE && $s['rejected'] >= 3,
                'target_label' => self::targetLabel($rule['target']),
                'value_label' => self::valueLabel($rule['target'], $rule['value']),
            ];
        }

        return ['rules' => $rows, 'ready' => $this->repo()->ready(), 'sample' => $sample, 'version' => $lexicon->version(), 'thresholds' => $lexicon->thresholds()];
    }

    public static function targetLabel(string $target): string
    {
        return ['theme' => 'Thème', 'urgency' => 'Urgence', 'kind' => 'Type de fiche'][$target] ?? $target;
    }

    public static function valueLabel(string $target, string $value): string
    {
        return match ($target) {
            'theme' => SseFieldNoteCatalog::themeLabel($value),
            'kind' => $value . ' · ' . SseFieldNoteCatalog::kindLabel($value),
            'urgency' => SseFieldNoteCatalog::urgencyLabel($value),
            default => $value,
        };
    }

    /**
     * Textes des remontées récentes de la communauté (fiches transmises et comptes rendus non supprimés).
     *
     * @return list<array{source: string, id: int, segments: array<string, string>, kind: string}>
     */
    public function recentTexts(int $tenantId, int $days, int $limit = 300): array
    {
        $since = gmdate('Y-m-d H:i:s', time() - $days * 86400);
        $out = [];
        try {
            $st = $this->pdo()->prepare("SELECT id, title, body, place_label, note_kind FROM sse_field_notes WHERE tenant_id = ? AND created_at >= ? AND (status IS NULL OR status <> 'brouillon') ORDER BY id DESC LIMIT " . (int) $limit);
            $st->execute([$tenantId, $since]);
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) ?: [] as $r) {
                $out[] = ['source' => 'fiche', 'id' => (int) $r['id'], 'segments' => self::segments('fiche', $r), 'kind' => strtoupper((string) ($r['note_kind'] ?? ''))];
            }
        } catch (\Throwable) {
        }
        try {
            $st = $this->pdo()->prepare('SELECT id, summary, details, remarks, location_description FROM atak_tactical_reports WHERE tenant_id = ? AND created_at >= ? AND deleted_at IS NULL ORDER BY id DESC LIMIT ' . (int) $limit);
            $st->execute([$tenantId, $since]);
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) ?: [] as $r) {
                $out[] = ['source' => 'cr', 'id' => (int) $r['id'], 'segments' => self::segments('cr', $r), 'kind' => ''];
            }
        } catch (\Throwable) {
        }

        return $out;
    }

    /**
     * Enregistre le réglage d'une règle depuis la fenêtre d'édition.
     *
     * @param array<string, mixed> $input
     * @param array{id: int, label: string} $actor
     * @return array{ok: bool, message: string}
     */
    public function saveRule(int $tenantId, array $input, array $actor): array
    {
        $id = trim((string) (is_scalar($input['rule_id'] ?? null) ? $input['rule_id'] : ''));
        $rule = $this->lexicon($tenantId)->rule($id);
        $shipped = SseDetectionLexicon::shipped()['rules'][$id] ?? null;
        if ($rule === null || $shipped === null) {
            return ['ok' => false, 'message' => 'Règle inconnue.'];
        }
        if (!$this->repo()->ready()) {
            return ['ok' => false, 'message' => 'Réglage impossible : l’étape « sse_detection_rules » n’est pas encore passée sur ce site.'];
        }
        $str = static fn (string $k): string => (string) (is_scalar($input[$k] ?? null) ? $input[$k] : '');
        $terms = SseDetectionLexicon::parseTerms($str('terms'));
        if ($terms === []) {
            return ['ok' => false, 'message' => 'Une règle garde au moins un terme : désactivez-la plutôt.'];
        }
        $exclusions = array_keys(SseDetectionLexicon::parseTerms($str('exclusions'), false));
        $requires = array_keys(SseDetectionLexicon::parseTerms($str('requires'), false));
        $weight = max(0, min(100, (int) $str('weight')));
        $data = [
            'enabled' => $str('enabled') === '1',
            // Ce qui est égal au lexique livré n'est pas recopié : une mise à jour du fichier livré continue de s'appliquer.
            'weight' => $weight === $shipped['weight'] ? null : $weight,
            'terms' => $terms === $shipped['terms'] ? null : $terms,
            'exclusions' => $exclusions === $shipped['exclusions'] ? null : $exclusions,
            'requires' => $requires === $shipped['requires'] ? null : $requires,
        ];
        $before = ['enabled' => $rule['enabled'], 'weight' => $rule['weight'], 'terms' => $rule['terms'], 'exclusions' => $rule['exclusions'], 'requires' => $rule['requires']];
        $this->repo()->saveRule($tenantId, $id, $data, $actor, $before, mb_substr(trim($str('reason')), 0, 300));
        $this->forget($tenantId);

        return ['ok' => true, 'message' => 'Règle ' . $id . ' enregistrée : elle s’applique dès maintenant aux propositions.'];
    }

    /**
     * @param array{id: int, label: string} $actor
     * @return array{ok: bool, message: string}
     */
    public function reset(int $tenantId, array $actor): array
    {
        if (!$this->repo()->ready()) {
            return ['ok' => false, 'message' => 'Rien à rétablir : le lexique livré s’applique déjà.'];
        }
        $n = $this->repo()->resetAll($tenantId, $actor);
        $this->forget($tenantId);

        return ['ok' => true, 'message' => $n > 0 ? 'Lexique livré rétabli : ' . $n . ' réglage' . ($n > 1 ? 's retirés' : ' retiré') . '.' : 'Le lexique livré s’appliquait déjà.'];
    }
}
