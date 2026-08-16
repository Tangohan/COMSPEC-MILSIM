<?php

declare(strict_types=1);

namespace App\Services\Sse;

use App\Repositories\SsePersonRepository;
use App\Repositories\SseWatchlistRepository;

final class SseCrossMatchService
{
    /** Score minimal retenu comme correspondance. */
    public const MATCH_THRESHOLD = 60;

    public function __construct(
        private ?SseWatchlistRepository $watchlist = null,
        private ?SsePersonRepository $persons = null,
    ) {
        $this->watchlist ??= new SseWatchlistRepository();
        $this->persons ??= new SsePersonRepository();
    }

    /**
     * @return list<array{person: array<string, mixed>, matches: list<array{entry: array<string, mixed>, score: int, reason: string}>}>
     */
    public function matchPersonsAgainstWatchlist(int $tenantId, int $contextId = 1): array
    {
        $entries = $this->watchlist->listActive($tenantId);
        $persons = $this->persons->listForContext($tenantId, $contextId, ['limit' => 200]);
        $out = [];
        foreach ($persons as $person) {
            $matches = [];
            foreach ($entries as $entry) {
                $hit = $this->score($person, $entry);
                if ($hit['score'] >= self::MATCH_THRESHOLD) {
                    $matches[] = $hit;
                }
            }
            if ($matches !== []) {
                usort($matches, static fn (array $a, array $b): int => $b['score'] <=> $a['score']);
                $out[] = ['person' => $person, 'matches' => $matches];
            }
        }

        return $out;
    }

    /**
     * Croisement d'une seule fiche — appelé à l'enregistrement terrain.
     * Le seuil est le même que la vue portail : une correspondance faible n'en est pas une.
     *
     * @param array<string, mixed> $person
     * @return list<array{entry: array<string, mixed>, score: int, reason: string}>
     */
    public function matchOne(array $person, int $tenantId): array
    {
        $matches = [];
        foreach ($this->watchlist->listActive($tenantId) as $entry) {
            $hit = $this->score($person, $entry);
            if ($hit['score'] >= self::MATCH_THRESHOLD) {
                $matches[] = $hit;
            }
        }
        usort($matches, static fn (array $a, array $b): int => $b['score'] <=> $a['score']);

        return $matches;
    }

    /**
     * @param array<string, mixed> $person
     * @param array<string, mixed> $entry
     * @return array{entry: array<string, mixed>, score: int, reason: string}
     */
    public function evaluateMatch(array $person, array $entry): array
    {
        return $this->score($person, $entry);
    }

    /**
     * @param array<string, mixed> $person
     * @param array<string, mixed> $entry
     * @return array{entry: array<string, mixed>, score: int, reason: string}
     */
    private function score(array $person, array $entry): array
    {
        $pParts = $this->identityParts($person);
        $eParts = $this->identityParts($entry);

        $direct = $this->scoreIdentityPair(
            $pParts['last'],
            $pParts['first'],
            $pParts['alias'],
            $eParts['last'],
            $eParts['first'],
            $eParts['alias'],
            false
        );
        $swapped = $this->scoreIdentityPair(
            $pParts['last'],
            $pParts['first'],
            $pParts['alias'],
            $eParts['first'],
            $eParts['last'],
            $eParts['alias'],
            true
        );

        $tokenHit = $this->scoreNameTokens($pParts['tokens'], $eParts['tokens']);
        $fullHit = $this->scoreFullName($pParts['full'], $eParts['full']);

        $candidates = [$direct, $swapped, $tokenHit, $fullHit];
        usort($candidates, static fn (array $a, array $b): int => $b['score'] <=> $a['score']);
        $best = $candidates[0];

        return [
            'entry' => $entry,
            'score' => min(100, $best['score']),
            'reason' => $best['reasons'] !== []
                ? implode(' · ', $best['reasons'])
                : 'Correspondance faible',
        ];
    }

    /**
     * Décompose une fiche / entrée : si nom/prénom absents, tente de les
     * déduire de l’alias ou du libellé affiché (« Khalil Jawadi »).
     *
     * @param array<string, mixed> $row
     * @return array{last: string, first: string, alias: string, full: string, tokens: list<string>}
     */
    private function identityParts(array $row): array
    {
        $last = $this->norm((string) ($row['last_name'] ?? ''));
        $first = $this->norm((string) ($row['first_name'] ?? ''));
        $alias = $this->norm((string) ($row['alias'] ?? ''));
        $display = $this->norm((string) ($row['display_name'] ?? ''));

        if ($last === '' && $first === '') {
            $source = $alias !== '' ? (string) ($row['alias'] ?? '') : (string) ($row['display_name'] ?? '');
            $guess = $this->splitPersonName($source);
            $first = $this->norm($guess['first']);
            $last = $this->norm($guess['last']);
        }

        $tokens = [];
        $rawBits = [
            (string) ($row['first_name'] ?? ''),
            (string) ($row['last_name'] ?? ''),
            (string) ($row['alias'] ?? ''),
            (string) ($row['display_name'] ?? ''),
        ];
        foreach ($rawBits as $raw) {
            foreach ($this->tokenize($raw) as $tok) {
                if ($tok !== '' && !in_array($tok, $tokens, true)) {
                    $tokens[] = $tok;
                }
            }
        }

        $full = $first . $last;
        if ($full === '' && $alias !== '') {
            $full = $alias;
        }
        if ($full === '' && $display !== '') {
            $full = $display;
        }

        return [
            'last' => $last,
            'first' => $first,
            'alias' => $alias,
            'full' => $full,
            'tokens' => $tokens,
        ];
    }

    /**
     * @return array{first: string, last: string}
     */
    private function splitPersonName(string $raw): array
    {
        $raw = trim(preg_replace('/\s+/u', ' ', $raw) ?? '');
        if ($raw === '') {
            return ['first' => '', 'last' => ''];
        }
        $parts = preg_split('/\s+/u', $raw) ?: [];
        if (count($parts) === 1) {
            return ['first' => $parts[0], 'last' => ''];
        }
        // Convention fiche : premier mot = prénom, reste = nom.
        return [
            'first' => $parts[0],
            'last' => implode(' ', array_slice($parts, 1)),
        ];
    }

    /**
     * @return list<string>
     */
    private function tokenize(string $raw): array
    {
        $raw = trim($raw);
        if ($raw === '') {
            return [];
        }
        $parts = preg_split('/[\s,;\/|+_-]+/u', $raw) ?: [];
        $out = [];
        foreach ($parts as $p) {
            $n = $this->norm($p);
            if ($n !== '' && mb_strlen($n) >= 2) {
                $out[] = $n;
            }
        }

        return $out;
    }

    /**
     * @param list<string> $personTokens
     * @param list<string> $entryTokens
     * @return array{score: int, reasons: list<string>}
     */
    private function scoreNameTokens(array $personTokens, array $entryTokens): array
    {
        if ($personTokens === [] || $entryTokens === []) {
            return ['score' => 0, 'reasons' => []];
        }
        $shared = array_values(array_intersect($personTokens, $entryTokens));
        if ($shared === []) {
            return ['score' => 0, 'reasons' => []];
        }

        $need = min(count($personTokens), count($entryTokens));
        $hit = count($shared);
        if ($hit >= 2 && $need >= 2) {
            return [
                'score' => 85,
                'reasons' => ['Même combinaison de noms (ordre indifférent)'],
            ];
        }
        if ($hit === 1 && $need === 1) {
            return [
                'score' => 55,
                'reasons' => ['Un seul élément de nom en commun'],
            ];
        }
        if ($hit >= 1) {
            return [
                'score' => 40,
                'reasons' => ['Élément de nom partagé'],
            ];
        }

        return ['score' => 0, 'reasons' => []];
    }

    /**
     * @return array{score: int, reasons: list<string>}
     */
    private function scoreFullName(string $personFull, string $entryFull): array
    {
        if ($personFull === '' || $entryFull === '') {
            return ['score' => 0, 'reasons' => []];
        }
        if ($personFull === $entryFull) {
            return ['score' => 90, 'reasons' => ['Identité nominale identique']];
        }
        // Même lettres dans un autre ordre (JawadiKhalil vs KhalilJawadi) — rare mais utile.
        $a = $this->sortedChars($personFull);
        $b = $this->sortedChars($entryFull);
        if ($a !== '' && $a === $b && mb_strlen($personFull) >= 6) {
            return ['score' => 80, 'reasons' => ['Même identité nominale (ordre des mots différent)']];
        }

        return ['score' => 0, 'reasons' => []];
    }

    private function sortedChars(string $s): string
    {
        $chars = preg_split('//u', $s, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        sort($chars);

        return implode('', $chars);
    }

    /**
     * @return array{score: int, reasons: list<string>}
     */
    private function scoreIdentityPair(
        string $pLast,
        string $pFirst,
        string $pAlias,
        string $eLast,
        string $eFirst,
        string $eAlias,
        bool $namesSwapped
    ): array {
        $score = 0;
        $reasons = [];

        if ($pLast !== '' && $pLast === $eLast) {
            $score += 50;
            $reasons[] = $namesSwapped ? 'Nom identique (ordre inversé)' : 'Nom identique';
        } elseif ($pLast !== '' && $eLast !== '' && (str_contains($pLast, $eLast) || str_contains($eLast, $pLast))) {
            $score += 30;
            $reasons[] = $namesSwapped ? 'Nom proche (ordre inversé)' : 'Nom proche';
        }

        if ($pFirst !== '' && $pFirst === $eFirst) {
            $score += 30;
            $reasons[] = $namesSwapped ? 'Prénom identique (ordre inversé)' : 'Prénom identique';
        } elseif (
            $namesSwapped
            && $pFirst !== ''
            && $eFirst !== ''
            && (str_contains($pFirst, $eFirst) || str_contains($eFirst, $pFirst))
        ) {
            $score += 18;
            $reasons[] = 'Prénom proche (ordre inversé)';
        }

        if ($pAlias !== '' && $eAlias !== '' && $pAlias === $eAlias) {
            $score += 40;
            $reasons[] = 'Alias identique';
        } elseif ($pAlias !== '' && ($pAlias === $eLast || $pAlias === $eFirst)) {
            $score += 35;
            $reasons[] = 'Alias correspondant à l’identité surveillée';
        }

        if (
            $namesSwapped
            && $pLast !== ''
            && $pFirst !== ''
            && $eLast !== ''
            && $eFirst !== ''
            && $pLast === $eLast
            && $pFirst === $eFirst
        ) {
            $score += 10;
            if (!in_array('Nom et prénom intervertis', $reasons, true)) {
                $reasons[] = 'Nom et prénom intervertis';
            }
        }

        return ['score' => $score, 'reasons' => $reasons];
    }

    private function norm(string $s): string
    {
        $s = mb_strtolower(trim($s), 'UTF-8');
        $s = strtr($s, [
            'à' => 'a', 'â' => 'a', 'ä' => 'a',
            'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
            'î' => 'i', 'ï' => 'i',
            'ô' => 'o', 'ö' => 'o',
            'ù' => 'u', 'û' => 'u', 'ü' => 'u',
            'ç' => 'c',
        ]);

        return preg_replace('/[^a-z0-9]+/', '', $s) ?? '';
    }
}
