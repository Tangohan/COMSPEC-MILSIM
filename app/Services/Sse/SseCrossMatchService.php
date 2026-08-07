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
        $score = 0;
        $reasons = [];
        $pLast = $this->norm((string) ($person['last_name'] ?? ''));
        $pFirst = $this->norm((string) ($person['first_name'] ?? ''));
        $pAlias = $this->norm((string) ($person['alias'] ?? ''));
        $eLast = $this->norm((string) ($entry['last_name'] ?? ''));
        $eFirst = $this->norm((string) ($entry['first_name'] ?? ''));
        $eAlias = $this->norm((string) ($entry['alias'] ?? ''));

        if ($pLast !== '' && $pLast === $eLast) {
            $score += 50;
            $reasons[] = 'Nom identique';
        } elseif ($pLast !== '' && $eLast !== '' && (str_contains($pLast, $eLast) || str_contains($eLast, $pLast))) {
            $score += 30;
            $reasons[] = 'Nom proche';
        }

        if ($pFirst !== '' && $pFirst === $eFirst) {
            $score += 30;
            $reasons[] = 'Prénom identique';
        }

        if ($pAlias !== '' && $eAlias !== '' && $pAlias === $eAlias) {
            $score += 40;
            $reasons[] = 'Alias identique';
        } elseif ($pAlias !== '' && ($pAlias === $eLast || $pAlias === $eFirst)) {
            $score += 35;
            $reasons[] = 'Alias correspondant à l’identité surveillée';
        }

        return [
            'entry' => $entry,
            'score' => min(100, $score),
            'reason' => $reasons !== [] ? implode(' · ', $reasons) : 'Correspondance faible',
        ];
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
