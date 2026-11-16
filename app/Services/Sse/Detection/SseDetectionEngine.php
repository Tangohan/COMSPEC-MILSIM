<?php

declare(strict_types=1);

namespace App\Services\Sse\Detection;

/**
 * Moteur de Cerbère-Lecture : lit un texte (titre, corps…) avec le lexique et dit, règle par règle, ce qu'il a trouvé, où,
 * dans quel contexte, ce qui a été écarté et pourquoi, puis en tire des détections (thème, urgence, type de fiche) avec une
 * confiance en pourcentage. Ne lit ni n'écrit aucune base.
 *
 * Correspondance : mots entiers repliés (SseTextNormalizer), jamais de sous-chaîne. Un terme trouvé est annulé quand il fait
 * partie d'une exclusion de sa règle (« barrage routier ») ; une racine déclarée (« surveill* ») ne prend jamais un faux ami.
 * Contexte : une négation (« pas de », « aucun ») ou une hypothèse (« exercice », « simulation ») juste avant le terme, dans
 * la même phrase, divise sa confiance et l'empêche de compter pour l'urgence ; une formule prudente (« pouvant », « possible »)
 * la réduit un peu.
 *
 * Confiance d'une règle : poids du meilleur terme (après contexte), + 10 par terme distinct en plus (jusqu'à +20), + 3 par
 * occurrence répétée (jusqu'à +6), + 8 si un terme est dans le titre, + 6 si le type de fiche est celui attendu par la règle.
 * Urgence : uniquement des règles d'urgence (menace immédiate, otages, attentat, victimes, armes, engin, délai). « Critique »
 * exige un signal immédiat ET un deuxième signal d'urgence distinct ; sinon la proposition est « Urgent ».
 */
final class SseDetectionEngine
{
    /** Ponctuation qui termine une phrase : une négation ne traverse jamais une phrase. */
    private const SENTENCE_BREAK = '/[.!?;\n\r·•|]/u';

    private const SNIPPET_RADIUS = 60;

    public function __construct(private SseDetectionLexicon $lexicon)
    {
    }

    public function lexicon(): SseDetectionLexicon
    {
        return $this->lexicon;
    }

    /**
     * Analyse règle par règle.
     *
     * @param array<string, string> $segments nom => texte (« title » compte comme titre)
     * @param array{kind?: string, themes?: list<string>} $context
     * @return array{text_length: int, tokens: int, rules: array<string, array<string, mixed>>}
     */
    public function analyze(array $segments, array $context = []): array
    {
        $text = '';
        $ranges = [];
        foreach ($segments as $name => $part) {
            $part = (string) $part;
            if (trim($part) === '') {
                continue;
            }
            if ($text !== '') {
                $text .= "\n";
            }
            $ranges[] = [strlen($text), strlen($text) + strlen($part), (string) $name];
            $text .= $part;
        }
        $tokens = SseTextNormalizer::tokens($text);
        $out = ['text_length' => mb_strlen($text), 'tokens' => count($tokens), 'rules' => []];
        if ($tokens === []) {
            return $out;
        }
        $scan = $this->scan($text, $tokens);
        $friends = $this->lexicon->falseFriends();
        $cues = [];
        foreach ($this->lexicon->contexts() as $name => $c) {
            $spans = [];
            foreach ($c['cues'] as $cue) {
                foreach ($this->find($scan, $cue['words'], $friends) as $span) {
                    $spans[] = $span + ['raw' => $cue['raw']];
                }
            }
            // Rangés par phrase : un terme ne regarde que les indices de sa phrase.
            $bySentence = [];
            foreach ($spans as $span) {
                $bySentence[$scan['sentence'][$span['s']]][] = $span;
            }
            $cues[$name] = ['factor' => $c['factor'], 'before' => $c['before'], 'after' => $c['after'], 'spans' => $bySentence];
        }
        $kind = strtoupper((string) ($context['kind'] ?? ''));

        foreach ($this->lexicon->compiled() as $id => $c) {
            $rule = $c['rule'];
            $ignoredFriends = [];
            // Exclusions rangées par mot couvert : un terme est exclu s'il tient entier dans l'une d'elles.
            $excludedAt = [];
            foreach ($c['exclusions'] as $ex) {
                foreach ($this->find($scan, $ex['words'], $friends) as $span) {
                    for ($k = $span['s']; $k <= $span['e']; $k++) {
                        $excludedAt[$k][] = $span + ['raw' => $ex['raw']];
                    }
                }
            }
            $hits = [];
            $excluded = [];
            foreach ($c['terms'] as $term) {
                foreach ($this->find($scan, $term['words'], $friends, $ignoredFriends) as $span) {
                    $by = null;
                    foreach ($excludedAt[$span['s']] ?? [] as $x) {
                        if ($x['s'] <= $span['s'] && $span['e'] <= $x['e']) {
                            $by = $x['raw'];
                            break;
                        }
                    }
                    $hit = [
                        'term' => $term['raw'],
                        'weight' => $term['weight'],
                        's' => $span['s'],
                        'e' => $span['e'],
                        'snippet' => $this->snippet($text, $tokens, $span['s'], $span['e']),
                        'segment' => $this->segmentOf($ranges, $tokens[$span['s']]['at']),
                    ];
                    if ($by !== null) {
                        $excluded[] = $hit + ['by' => $by];
                        continue;
                    }
                    [$ctx, $cue, $factor] = $this->contextOf($scan, $cues, $span['s'], $span['e']);
                    $hits[] = $hit + ['context' => $ctx, 'cue' => $cue, 'factor' => $factor];
                }
            }
            if ($hits === [] && $excluded === [] && $ignoredFriends === []) {
                continue;
            }
            // Un même passage peut répondre à deux termes de la règle (« prise d’otage » et « otage ») : on garde le plus long.
            $hits = self::dedupeOverlaps($hits);
            $missing = false;
            if ($hits !== [] && $c['requires'] !== []) {
                $missing = true;
                foreach ($c['requires'] as $req) {
                    if ($this->find($scan, $req['words'], $friends) !== []) {
                        $missing = false;
                        break;
                    }
                }
            }
            [$confidence, $signals] = $missing ? [0, [['label' => 'contexte requis absent', 'points' => 0]]] : self::score($rule, $hits, $kind);
            $out['rules'][$id] = [
                'id' => $id,
                'target' => $rule['target'],
                'value' => $rule['value'],
                'label' => $rule['label'],
                'explain' => $rule['explain'],
                'weight' => $rule['weight'],
                'level' => $rule['level'],
                'immediacy' => $rule['immediacy'],
                'confidence' => $confidence,
                // Les 25 premiers passages suffisent à l'explication ; le compte reste exact.
                'hit_count' => count($hits),
                'hits' => array_map(static fn (array $h): array => array_diff_key($h, ['s' => 0, 'e' => 0]), array_slice($hits, 0, 25)),
                'excluded' => array_map(static fn (array $h): array => array_diff_key($h, ['s' => 0, 'e' => 0]), array_slice($excluded, 0, 25)),
                'false_friends' => array_values(array_unique($ignoredFriends)),
                'missing_context' => $missing,
                'requires' => array_column($c['requires'], 'raw'),
                'exclusions' => array_column($c['exclusions'], 'raw'),
                'signals' => $signals,
            ];
        }

        return $out;
    }

    /**
     * Détections tirées de l'analyse : un thème, une urgence, un type de fiche, chacun avec sa confiance et ses règles.
     *
     * @param array<string, string> $segments
     * @param array{kind?: string, themes?: list<string>, urgency_rank?: int} $context
     * @return array{analysis: array<string, mixed>, detections: list<array<string, mixed>>}
     */
    public function detect(array $segments, array $context = []): array
    {
        $analysis = $this->analyze($segments, $context);
        $th = $this->lexicon->thresholds();
        $tier = static fn (int $c): string => $c >= $th['visible'] ? 'visible' : ($c >= $th['review'] ? 'review' : 'hidden');
        $detections = [];

        // Thèmes : la meilleure règle du thème, + 5 par autre règle du même thème qui répond.
        $byTheme = [];
        foreach ($analysis['rules'] as $r) {
            if ($r['target'] === 'theme' && $r['confidence'] > 0) {
                $byTheme[$r['value']][] = $r;
            }
        }
        foreach ($byTheme as $value => $rules) {
            usort($rules, static fn (array $a, array $b): int => $b['confidence'] <=> $a['confidence']);
            $conf = min(99, $rules[0]['confidence'] + 5 * (count($rules) - 1));
            $signals = $rules[0]['signals'];
            if (count($rules) > 1) {
                $signals[] = ['label' => 'autres règles du thème', 'points' => 5 * (count($rules) - 1)];
            }
            $detections[] = ['target' => 'theme', 'value' => (string) $value, 'confidence' => $conf, 'tier' => $tier($conf), 'rules' => $rules, 'signals' => $signals];
        }

        // Urgence : seules les règles d'urgence comptent, sans négation ni hypothèse.
        $counted = [];
        foreach ($analysis['rules'] as $r) {
            if ($r['target'] !== 'urgency' || $r['missing_context']) {
                continue;
            }
            $best = 0.0;
            foreach ($r['hits'] as $h) {
                if (in_array($h['context'], ['normal', 'hedge'], true)) {
                    $best = max($best, $h['weight'] * $h['factor']);
                }
            }
            if ($best > 0) {
                $counted[] = $r + ['urgency_score' => (int) round($best)];
            }
        }
        if ($counted !== []) {
            usort($counted, static fn (array $a, array $b): int => $b['urgency_score'] <=> $a['urgency_score']);
            $immediate = array_values(array_filter($counted, static fn (array $r): bool => $r['immediacy']));
            $level = $immediate !== [] && count($counted) >= 2 ? 'critique' : 'urgent';
            $signals = [['label' => 'signal le plus fort : ' . $counted[0]['label'], 'points' => $counted[0]['urgency_score']]];
            $conf = $counted[0]['urgency_score'];
            if (count($counted) > 1) {
                $bonus = min(25, 10 * (count($counted) - 1));
                $conf += $bonus;
                $signals[] = ['label' => 'signaux d’urgence distincts : ' . count($counted), 'points' => $bonus];
            }
            foreach ($counted as $r) {
                foreach ($r['hits'] as $h) {
                    if ($h['segment'] === 'title' && in_array($h['context'], ['normal', 'hedge'], true)) {
                        $conf += 5;
                        $signals[] = ['label' => 'dans le titre', 'points' => 5];
                        break 2;
                    }
                }
            }
            if (array_intersect((array) ($context['themes'] ?? []), ['TERROR', 'CBRNE', 'INSURG']) !== []) {
                $conf += 5;
                $signals[] = ['label' => 'thème grave déjà choisi par l’émetteur', 'points' => 5];
            }
            if ($level === 'urgent' && $immediate !== []) {
                $signals[] = ['label' => 'signal immédiat seul : « Critique » demande un deuxième signal', 'points' => 0];
            }
            $conf = min(99, $conf);
            $detections[] = ['target' => 'urgency', 'value' => $level, 'confidence' => $conf, 'tier' => $tier($conf), 'rules' => $counted, 'signals' => $signals];
        }

        // Type de fiche : la règle qui a le plus de termes distincts, à partir de deux.
        $bestKind = null;
        foreach ($analysis['rules'] as $r) {
            if ($r['target'] !== 'kind') {
                continue;
            }
            $distinct = count(array_unique(array_column($r['hits'], 'term')));
            if ($distinct >= 2 && ($bestKind === null || $distinct > $bestKind[1])) {
                $bestKind = [$r, $distinct];
            }
        }
        if ($bestKind !== null) {
            [$r, $distinct] = $bestKind;
            $conf = min(90, 30 + 15 * $distinct);
            $detections[] = ['target' => 'kind', 'value' => $r['value'], 'confidence' => $conf, 'tier' => $tier($conf), 'rules' => [$r],
                'signals' => [['label' => 'termes distincts : ' . $distinct, 'points' => $conf]]];
        }

        return ['analysis' => $analysis, 'detections' => $detections];
    }

    /**
     * @param array<string, mixed> $rule
     * @param list<array<string, mixed>> $hits
     * @return array{0: int, 1: list<array{label: string, points: int}>}
     */
    private static function score(array $rule, array $hits, string $kind): array
    {
        if ($hits === []) {
            return [0, []];
        }
        $best = null;
        foreach ($hits as $h) {
            $v = $h['weight'] * $h['factor'];
            if ($best === null || $v > $best[0]) {
                $best = [$v, $h];
            }
        }
        $signals = [['label' => 'terme « ' . $best[1]['term'] . ' »' . ($best[1]['context'] !== 'normal' ? ' (' . self::contextLabel($best[1]['context']) . ')' : ''), 'points' => (int) round($best[0])]];
        $score = $best[0];
        $firm = array_values(array_filter($hits, static fn (array $h): bool => in_array($h['context'], ['normal', 'hedge'], true)));
        if ($firm !== []) {
            $distinct = count(array_unique(array_column($firm, 'term')));
            if ($distinct > 1) {
                $b = min(20, 10 * ($distinct - 1));
                $score += $b;
                $signals[] = ['label' => 'termes distincts : ' . $distinct, 'points' => $b];
            }
            $repeat = count($firm) - $distinct;
            if ($repeat > 0) {
                $b = min(6, 3 * $repeat);
                $score += $b;
                $signals[] = ['label' => 'répété ' . (count($firm)) . ' fois', 'points' => $b];
            }
            foreach ($firm as $h) {
                if ($h['segment'] === 'title') {
                    $score += 8;
                    $signals[] = ['label' => 'dans le titre', 'points' => 8];
                    break;
                }
            }
            if ($kind !== '' && in_array($kind, $rule['kinds'], true)) {
                $score += 6;
                $signals[] = ['label' => 'attendu sur une fiche ' . $kind, 'points' => 6];
            }
        }

        return [max(1, min(99, (int) round($score))), $signals];
    }

    public static function contextLabel(string $context): string
    {
        return ['negation' => 'négation', 'hypothesis' => 'hypothèse ou exercice', 'hedge' => 'formule prudente'][$context] ?? 'affirmé';
    }

    /**
     * Index des mots : forme exacte, forme sans -s/-x final, et numéro de phrase de chaque mot.
     *
     * @param list<array{w: string, at: int, len: int}> $tokens
     * @return array{tokens: list<array{w: string, at: int, len: int}>, index: array<string, list<int>>, sing: array<string, list<int>>, sentence: list<int>}
     */
    private function scan(string $text, array $tokens): array
    {
        $index = [];
        $sing = [];
        $sentence = [];
        $sid = 0;
        $prevEnd = 0;
        foreach ($tokens as $i => $t) {
            if ($i > 0 && preg_match(self::SENTENCE_BREAK, substr($text, $prevEnd, $t['at'] - $prevEnd)) === 1) {
                $sid++;
            }
            $sentence[$i] = $sid;
            $prevEnd = $t['at'] + $t['len'];
            $index[$t['w']][] = $i;
            $last = substr($t['w'], -1);
            if (strlen($t['w']) > 3 && ($last === 's' || $last === 'x')) {
                $sing[substr($t['w'], 0, -1)][] = $i;
            }
        }

        return ['tokens' => $tokens, 'index' => $index, 'sing' => $sing, 'sentence' => $sentence];
    }

    /**
     * @param array<string, mixed> $word
     * @param array<string, string> $friends
     */
    private static function wordMatches(array $word, string $w, array $friends): bool
    {
        if ($word['prefix'] !== '') {
            return str_starts_with($w, $word['prefix']) && !isset($friends[$w]);
        }
        if (isset($word['forms'][$w])) {
            return true;
        }
        $last = substr($w, -1);

        return strlen($w) > 3 && ($last === 's' || $last === 'x') && isset($word['forms'][substr($w, 0, -1)]);
    }

    /**
     * Occurrences d'une suite de mots : positions de début et de fin (mots), jamais à travers une fin de phrase.
     *
     * @param array<string, mixed> $scan
     * @param list<array<string, mixed>> $words
     * @param array<string, string> $friends
     * @param list<string> $ignored faux amis rencontrés (racines)
     * @return list<array{s: int, e: int}>
     */
    private function find(array $scan, array $words, array $friends, array &$ignored = []): array
    {
        if ($words === []) {
            return [];
        }
        $first = $words[0];
        $starts = [];
        if ($first['prefix'] !== '') {
            foreach ($scan['index'] as $w => $positions) {
                $w = (string) $w;
                if (str_starts_with($w, $first['prefix'])) {
                    if (isset($friends[$w])) {
                        $ignored[] = $w;
                        continue;
                    }
                    array_push($starts, ...$positions);
                }
            }
        } else {
            foreach ($first['forms'] as $form => $_) {
                array_push($starts, ...($scan['index'][$form] ?? []), ...($scan['sing'][$form] ?? []));
            }
        }
        if ($starts === []) {
            return [];
        }
        $starts = array_unique($starts);
        sort($starts);
        $n = count($words);
        $total = count($scan['tokens']);
        $out = [];
        foreach ($starts as $s) {
            if ($s + $n > $total) {
                continue;
            }
            $ok = true;
            for ($k = 1; $k < $n; $k++) {
                if ($scan['sentence'][$s + $k] !== $scan['sentence'][$s] || !self::wordMatches($words[$k], $scan['tokens'][$s + $k]['w'], $friends)) {
                    $ok = false;
                    break;
                }
            }
            if ($ok) {
                $out[] = ['s' => $s, 'e' => $s + $n - 1];
            }
        }

        return $out;
    }

    /**
     * Contexte d'un terme : négation, hypothèse ou formule prudente dans la même phrase, sinon affirmé.
     *
     * @param array<string, mixed> $scan
     * @param array<string, array{factor: float, before: int, after: int, spans: array<int, list<array{s: int, e: int, raw: string}>>}> $cues
     * @return array{0: string, 1: string|null, 2: float}
     */
    private function contextOf(array $scan, array $cues, int $s, int $e): array
    {
        $sentence = $scan['sentence'][$s];
        foreach (['negation', 'hypothesis', 'hedge'] as $name) {
            $c = $cues[$name] ?? null;
            if ($c === null) {
                continue;
            }
            foreach ($c['spans'][$sentence] ?? [] as $span) {
                $before = $span['e'] < $s && $s - $span['e'] <= $c['before'];
                $after = $span['s'] > $e && $span['s'] - $e <= $c['after'];
                if ($before || $after) {
                    return [$name, $span['raw'], $c['factor']];
                }
            }
        }

        return ['normal', null, 1.0];
    }

    /**
     * @param list<array<string, mixed>> $hits
     * @return list<array<string, mixed>>
     */
    private static function dedupeOverlaps(array $hits): array
    {
        usort($hits, static fn (array $a, array $b): int => [$a['s'], $b['e']] <=> [$b['s'], $a['e']]);
        // Triés par début puis fin décroissante : un passage est contenu dans un passage gardé si sa fin ne dépasse pas.
        $out = [];
        $maxEnd = -1;
        foreach ($hits as $h) {
            if ($h['e'] <= $maxEnd) {
                continue;
            }
            $out[] = $h;
            $maxEnd = $h['e'];
        }

        return $out;
    }

    /**
     * @param list<array{0: int, 1: int, 2: string}> $ranges
     */
    private function segmentOf(array $ranges, int $at): string
    {
        foreach ($ranges as [$from, $to, $name]) {
            if ($at >= $from && $at < $to) {
                return $name;
            }
        }

        return '';
    }

    /**
     * Extrait autour du passage trouvé, tel qu'il est écrit : avant, passage, après.
     *
     * @param list<array{w: string, at: int, len: int}> $tokens
     * @return array{before: string, match: string, after: string}
     */
    private function snippet(string $text, array $tokens, int $s, int $e): array
    {
        $from = $tokens[$s]['at'];
        $to = $tokens[$e]['at'] + $tokens[$e]['len'];
        // Coupes en octets recalées sur un début de caractère UTF-8 (sans parcourir tout le texte).
        $len = strlen($text);
        $start = max(0, $from - self::SNIPPET_RADIUS);
        while ($start > 0 && (ord($text[$start]) & 0xC0) === 0x80) {
            $start--;
        }
        $end = min($len, $to + self::SNIPPET_RADIUS);
        while ($end < $len && (ord($text[$end]) & 0xC0) === 0x80) {
            $end++;
        }
        $before = substr($text, $start, $from - $start);
        $after = substr($text, $to, $end - $to);
        $clean = static fn (string $v): string => (string) preg_replace('/\s+/u', ' ', $v);

        return [
            'before' => ($start > 0 ? '…' : '') . ltrim($clean($before)),
            'match' => $clean(substr($text, $from, $to - $from)),
            'after' => rtrim($clean($after)) . ($end < $len ? '…' : ''),
        ];
    }
}
