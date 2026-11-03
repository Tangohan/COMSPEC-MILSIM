<?php

declare(strict_types=1);

namespace App\Support;

use App\Support\QualificationCatalog\QualificationCatalog;

/**
 * Retrouve, dans une phrase de prérequis, les qualifications et les cours déjà connus : entrées du catalogue de
 * référence (pré-calcul des packs) ou fiches et cours de la communauté (écran « Prérequis détectés »).
 *
 * Un nom complet cité tel quel (« Military parachutist badge », « Basic Airborne Course ») est sûr ; un sigle
 * (« SFAS », « BMP », « LC2 ») l'est un peu moins, et moins encore s'il vient d'un autre pays que la qualification
 * analysée. Une phrase qui cite plusieurs qualifications reliées par « ou » propose chacune sans la cocher.
 */
final class QualificationPrerequisiteResolver
{
    /** Sigles trop courants pour désigner une qualification à eux seuls. */
    private const COMMON_SHORT = ['CAP', 'OPS', 'NCO', 'SNCO', 'MOS', 'ADL', 'SLP', 'RAF', 'USAF', 'ETAP', 'CMF', 'ID', 'OK', 'EMT', 'FAA'];

    /** @var list<array{type: string, id: int|string, label: string, pack: string, names: list<string>, short: list<string>}> */
    private array $candidates;

    /**
     * @param list<array{type: string, id: int|string, label: string, pack?: string, names: list<string>, short?: list<string>}> $candidates
     *        type : qualification | course ; names : intitulés complets ; short : sigles (casse respectée)
     */
    public function __construct(array $candidates)
    {
        $this->candidates = [];
        foreach ($candidates as $c) {
            $names = [];
            foreach ($c['names'] as $n) {
                $bare = preg_replace('/\s*\([^)]*\)\s*/u', ' ', $n) ?? $n;
                $variants = [$n, $bare];
                // Première partie d'un nom composé : « KSK commando basic training » pour « … and commando badge ».
                $head = preg_split('/\s+(?:and|et|und|with|avec)\s+|\s+[\/–—:]\s+/u', trim($bare))[0] ?? '';
                if (substr_count(trim($head), ' ') >= 2) {
                    $variants[] = $head;
                }
                foreach ($variants as $v) {
                    $v = self::norm((string) $v);
                    // Un nom d'un seul mot court (« Pilote ») citerait n'importe quoi : seulement les noms parlants.
                    if ($v !== '' && (mb_strlen($v) >= 12 || (substr_count($v, ' ') >= 1 && mb_strlen($v) >= 8))) {
                        $names[$v] = true;
                    }
                }
                // Sigle entre parenthèses dans le nom : « Military parachutist badge (BMP) » (pas « (NCOs) », « (Great Lakes) »).
                if (preg_match_all('/\(([\p{Lu}\d\/.\-]{2,10})\)/u', $n, $m)) {
                    foreach ($m[1] as $paren) {
                        $c['short'][] = trim($paren);
                    }
                }
            }
            $short = [];
            foreach ($c['short'] ?? [] as $s) {
                $s = trim((string) $s);
                if (mb_strlen($s) >= 2 && mb_strlen($s) <= 14 && preg_match('/\p{Lu}/u', $s) === 1 && !in_array(strtoupper($s), self::COMMON_SHORT, true)
                    && preg_match('/^[\p{Lu}\d][\p{L}\d\/.\- ]*$/u', $s) === 1 && preg_match('/[\p{Lu}\d].*[\p{Lu}\d]/u', $s) === 1) {
                    $short[$s] = true;
                }
            }
            $this->candidates[] = [
                'type' => (string) $c['type'],
                'id' => $c['id'],
                'label' => (string) $c['label'],
                'pack' => (string) ($c['pack'] ?? ''),
                'names' => array_keys($names),
                'short' => array_keys($short),
            ];
        }
        // Sigle porté par plusieurs fiches (« PFV », « SERE », « CBRN ») ou mot de leurs noms : ambigu.
        $tokens = [];
        foreach ($this->candidates as $i => $c) {
            $seen = [];
            foreach (array_merge($c['short'], $c['names']) as $text) {
                foreach (explode(' ', self::norm($text)) as $tok) {
                    $seen[$tok] = true;
                }
            }
            foreach ($c['short'] as $s) {
                $seen[self::norm($s)] = true;
            }
            foreach (array_keys($seen) as $tok) {
                $tokens[$tok] = ($tokens[$tok] ?? 0) + 1;
            }
        }
        $this->shared = $tokens;
    }

    /** @var array<string, int> nombre de fiches qui portent chaque mot ou sigle */
    private array $shared = [];

    /** Toutes les entrées du catalogue de référence (identifiant = id de l'entrée). */
    public static function fromCatalog(string $lang = 'fr'): self
    {
        $out = [];
        foreach (QualificationCatalog::rawEntries() as $e) {
            $names = array_values(array_filter([
                (string) ($e['name']['fr'] ?? ''), (string) ($e['name']['en'] ?? ''), (string) ($e['official_name'] ?? ''),
            ]));
            $out[] = [
                'type' => 'qualification',
                'id' => (string) $e['id'],
                'label' => QualificationCatalog::pick($e['name'] ?? [], $lang),
                'pack' => (string) ($e['pack'] ?? ''),
                'names' => $names,
                'short' => [(string) ($e['short_name'] ?? '')],
            ];
        }

        return new self($out);
    }

    /**
     * Qualifications et cours cités par la phrase.
     *
     * @param int|string|null $self la qualification analysée (jamais son propre prérequis)
     * @return list<array{type: string, id: int|string, label: string, confidence: float, matched: string}>
     */
    public function resolve(string $sentence, int|string|null $self = null, string $pack = ''): array
    {
        $hay = ' ' . self::norm($sentence) . ' ';
        $hits = [];
        foreach ($this->candidates as $i => $c) {
            if ($self !== null && (string) $c['id'] === (string) $self && $c['type'] === 'qualification') {
                continue;
            }
            foreach ($c['names'] as $n) {
                $pos = mb_strpos($hay, ' ' . $n . ' ');
                if ($pos !== false) {
                    $hits[] = ['i' => $i, 'start' => $pos, 'len' => mb_strlen($n), 'conf' => 0.9, 'matched' => $n];
                }
            }
            foreach ($c['short'] as $s) {
                if (preg_match('/(?<![\p{L}\d])' . preg_quote($s, '/') . '(?![\p{L}\d])/u', $sentence, $m, PREG_OFFSET_CAPTURE) === 1) {
                    // Position approchée dans la phrase normalisée (sert seulement à départager les recouvrements).
                    $start = mb_strpos($hay, ' ' . self::norm($s) . ' ');
                    $ambiguous = ($this->shared[self::norm($s)] ?? 0) > 1;
                    $hits[] = ['i' => $i, 'start' => $start === false ? -1 : $start, 'len' => mb_strlen(self::norm($s)), 'conf' => $ambiguous ? 0.55 : 0.85, 'matched' => $s];
                }
            }
        }
        if ($hits === []) {
            return [];
        }
        // Le plus long passage l'emporte ; à longueur égale, le même pays que la qualification analysée.
        usort($hits, function (array $a, array $b) use ($pack): int {
            $pa = $this->candidates[$a['i']]['pack'] === $pack ? 1 : 0;
            $pb = $this->candidates[$b['i']]['pack'] === $pack ? 1 : 0;

            return [$b['len'], $pb, $b['conf']] <=> [$a['len'], $pa, $a['conf']];
        });
        $taken = [];
        $out = [];
        foreach ($hits as $h) {
            $c = $this->candidates[$h['i']];
            $key = $c['type'] . ':' . $c['id'];
            if (isset($out[$key])) {
                continue;
            }
            $overlap = false;
            foreach ($taken as [$s, $l, $matched]) {
                if ($h['start'] >= 0 && $s >= 0 && $h['start'] < $s + $l && $s < $h['start'] + $h['len']) {
                    // Même passage, autre pays : on garde le premier (même pays) ; un passage plus court inclus est écarté.
                    $overlap = true;
                }
            }
            if ($overlap) {
                continue;
            }
            $conf = $h['conf'];
            if ($pack !== '' && $c['pack'] !== '' && $c['pack'] !== $pack) {
                $conf -= 0.2;
            }
            $taken[] = [$h['start'], $h['len'], $h['matched']];
            $out[$key] = ['type' => $c['type'], 'id' => $c['id'], 'label' => $c['label'], 'confidence' => $conf, 'matched' => $h['matched']];
        }
        $out = array_values($out);
        $f = QualificationPrerequisiteExtractor::fold($sentence);
        // « P Company ou All Arms Commando Course », « Notfallsanitäter ou médecin » : une possibilité parmi d'autres.
        $alternatives = preg_match('/\s(?:or|ou|oder)\s/u', $f) === 1
            && (count($out) > 1 || mb_strlen(self::norm($sentence)) > mb_strlen((string) self::norm((string) ($out[0]['matched'] ?? ''))) + 12);
        // Conseillé seulement, ou aptitude à suivre la formation (« Fitness for lone-combatant training ») : à confirmer.
        $optional = preg_match('/\b(?:recommended|recommande|souhaite|desired|useful|utile|preferably|de preference|empfohlen|same application conditions|memes conditions)\b|^(?:(?:medical\s+)?fitness for|fit for|aptitude (?:au|a la|aux)|tauglichkeit)\b/u', $f) === 1;
        foreach ($out as $k => $o) {
            $c = $o['confidence'];
            if ($alternatives) {
                $c = min($c, 0.55);
            }
            if ($optional) {
                $c = min($c, 0.5);
            }
            $out[$k]['confidence'] = round($c, 2);
        }

        return $out;
    }

    /** Forme de comparaison : repliée (sans accents), lettres et chiffres séparés par une espace. */
    public static function norm(string $s): string
    {
        $s = QualificationPrerequisiteExtractor::fold($s);
        $s = preg_replace('/[^\p{L}\d]+/u', ' ', $s) ?? $s;

        return trim(preg_replace('/\s+/u', ' ', $s) ?? $s);
    }
}
