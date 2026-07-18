<?php

declare(strict_types=1);

namespace App\Services\Portal;

use App\Core\Gate;

/**
 * Formule une réponse guidée à partir de la recherche portail (sans IA externe).
 *
 * @phpstan-type Suggestion array{label: string, href: string}
 * @phpstan-type AnswerPayload array{
 *   answer: string,
 *   suggestions: list<Suggestion>,
 *   scoped: true,
 *   hits: int
 * }
 */
final class AssistantAnswerService
{
    private const MAX_SUGGESTIONS = 8;

    /** Mots de liaison / question à retirer pour la recherche textuelle. */
    private const STOP_WORDS = [
        'où', 'ou', 'trouver', 'comment', 'quoi', 'quel', 'quelle', 'quels', 'quelles',
        'est', 'sont', 'pour', 'dans', 'avec', 'sans', 'chez', 'vers', 'sur', 'par',
        'les', 'des', 'une', 'uns', 'mes', 'tes', 'ses', 'nos', 'vos', 'leur', 'leurs',
        'mon', 'ton', 'son', 'notre', 'votre', 'cette', 'cet', 'ces', 'aux', 'du', 'de',
        'la', 'le', 'un', 'et', 'ou', 'mais', 'donc', 'car', 'que', 'qui', 'quoi',
        'faire', 'accès', 'acceder', 'accéder', 'voir', 'ouvrir', 'aller', 'svp',
        'please', 'aide', 'besoin', 'veux', 'voudrais', 'peux', 'pouvez', 'moi',
        'nous', 'vous', 'ils', 'elles', 'y', 'a', 'il', 'elle', 'je', 'tu',
    ];

    public function __construct(
        private PortalSearchService $portalSearchService,
    ) {}

    /**
     * @return AnswerPayload
     */
    public function answer(int $tenantId, int $userId, string $question, Gate $gate): array
    {
        $searchQuery = $this->extractSearchQuery($question);
        if ($searchQuery === '' || mb_strlen($searchQuery) < PortalSearchService::MIN_QUERY_LEN) {
            $searchQuery = $this->fallbackQuery($question);
        }

        $results = $this->portalSearchService->search($tenantId, $userId, $searchQuery, $gate);
        $hitCount = $this->countContentHits($results);

        // Phrase multi-mots sans résultat : retenter avec le mot le plus long (ex. « calendrier manœuvres »).
        if ($hitCount === 0 && $results['commands'] === [] && str_contains($searchQuery, ' ')) {
            $primary = $this->fallbackQuery($searchQuery);
            if ($primary !== '' && $primary !== $searchQuery) {
                $results = $this->portalSearchService->search($tenantId, $userId, $primary, $gate);
                $searchQuery = $primary;
                $hitCount = $this->countContentHits($results);
            }
        }

        $suggestions = $this->buildSuggestions($results);

        if ($hitCount === 0 && $suggestions === []) {
            return [
                'answer' => 'Aucun élément correspondant n’a été trouvé dans le périmètre de votre communauté. '
                    . 'Essayez d’autres mots-clés, ou ouvrez la recherche du portail et le guide intégré.',
                'suggestions' => [
                    ['label' => 'Ouvrir la recherche', 'href' => url('search') . ($searchQuery !== '' ? '?q=' . rawurlencode($searchQuery) : '')],
                    ['label' => 'Consulter le guide', 'href' => url('documentation')],
                    ['label' => 'Centre de commandement', 'href' => url('hub')],
                ],
                'scoped' => true,
                'hits' => 0,
            ];
        }

        $answer = $this->composeAnswer($results, $hitCount, $suggestions);

        if (count($suggestions) < 3) {
            $suggestions = $this->ensureFallbackSuggestions($suggestions, $searchQuery);
        }

        return [
            'answer' => $answer,
            'suggestions' => array_slice($suggestions, 0, self::MAX_SUGGESTIONS),
            'scoped' => true,
            'hits' => $hitCount + count($results['commands']),
        ];
    }

    public function extractSearchQuery(string $question): string
    {
        $normalized = mb_strtolower(trim($question));
        $normalized = preg_replace('/[^\p{L}\p{N}\s]+/u', ' ', $normalized) ?? $normalized;
        $parts = preg_split('/\s+/u', trim($normalized), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $kept = [];
        foreach ($parts as $part) {
            if (mb_strlen($part) < 2) {
                continue;
            }
            if (in_array($part, self::STOP_WORDS, true)) {
                continue;
            }
            $kept[] = $part;
        }

        $query = implode(' ', $kept);
        if (mb_strlen($query) > PortalSearchService::MAX_QUERY_LEN) {
            $query = mb_substr($query, 0, PortalSearchService::MAX_QUERY_LEN);
        }

        return trim($query);
    }

    private function fallbackQuery(string $question): string
    {
        $normalized = mb_strtolower(trim($question));
        $normalized = preg_replace('/[^\p{L}\p{N}\s]+/u', ' ', $normalized) ?? $normalized;
        $parts = preg_split('/\s+/u', trim($normalized), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $longest = '';
        foreach ($parts as $part) {
            if (mb_strlen($part) > mb_strlen($longest) && !in_array($part, self::STOP_WORDS, true)) {
                $longest = $part;
            }
        }

        return $longest;
    }

    /**
     * @param array{
     *   documents: list<array{title: string, href: string, excerpt?: string, category?: string}>,
     *   forum: list<array{title: string, href: string, category?: string}>,
     *   personnel: list<array{title: string, href: string, subtitle?: string}>,
     *   events: list<array{title: string, href: string, subtitle?: string}>,
     *   training: list<array{title: string, href: string, subtitle?: string}>,
     *   commands: list<array{title: string, href: string, subtitle?: string}>
     * } $results
     * @param list<array{label: string, href: string}> $suggestions
     */
    private function composeAnswer(array $results, int $hitCount, array $suggestions): string
    {
        $parts = [];

        if ($results['commands'] !== []) {
            $cmd = $results['commands'][0];
            $parts[] = 'Le raccourci le plus pertinent est « ' . $cmd['title'] . ' ».';
        }

        $sections = [];
        if ($results['events'] !== []) {
            $sections[] = $this->countLabel(count($results['events']), 'manœuvre', 'manœuvres');
        }
        if ($results['documents'] !== []) {
            $sections[] = $this->countLabel(count($results['documents']), 'document', 'documents');
        }
        if ($results['forum'] !== []) {
            $sections[] = $this->countLabel(count($results['forum']), 'sujet du forum', 'sujets du forum');
        }
        if ($results['training'] !== []) {
            $sections[] = $this->countLabel(count($results['training']), 'formation', 'formations');
        }
        if ($results['personnel'] !== []) {
            $sections[] = $this->countLabel(count($results['personnel']), 'membre', 'membres');
        }

        if ($sections !== []) {
            $parts[] = 'Voici ce qui correspond dans votre communauté : ' . $this->joinFrench($sections) . '.';
        } elseif ($hitCount === 0 && $suggestions !== []) {
            $parts[] = 'Aucun contenu nommé n’a été trouvé, mais ces raccourcis du portail correspondent à votre demande.';
        }

        $highlight = $this->firstHighlightTitle($results);
        if ($highlight !== null) {
            $parts[] = 'Piste principale : « ' . $highlight . ' ».';
        }

        $parts[] = 'Utilisez les liens ci-dessous pour ouvrir le bon écran.';

        return implode(' ', $parts);
    }

    /**
     * @param array{
     *   documents: list<array{title: string, href: string}>,
     *   forum: list<array{title: string, href: string}>,
     *   personnel: list<array{title: string, href: string}>,
     *   events: list<array{title: string, href: string}>,
     *   training: list<array{title: string, href: string}>,
     *   commands: list<array{title: string, href: string}>
     * } $results
     * @return list<array{label: string, href: string}>
     */
    private function buildSuggestions(array $results): array
    {
        $out = [];
        $seen = [];

        $push = static function (string $label, string $href) use (&$out, &$seen): void {
            if ($href === '' || isset($seen[$href])) {
                return;
            }
            $seen[$href] = true;
            $out[] = ['label' => $label, 'href' => $href];
        };

        foreach ($results['commands'] as $c) {
            $push((string) $c['title'], (string) $c['href']);
        }
        foreach ($results['events'] as $e) {
            $push('Manœuvre : ' . (string) $e['title'], (string) $e['href']);
        }
        foreach ($results['documents'] as $d) {
            $push('Document : ' . (string) $d['title'], (string) $d['href']);
        }
        foreach ($results['forum'] as $f) {
            $push('Forum : ' . (string) $f['title'], (string) $f['href']);
        }
        foreach ($results['training'] as $t) {
            $push('Formation : ' . (string) $t['title'], (string) $t['href']);
        }
        foreach ($results['personnel'] as $p) {
            $label = (string) $p['title'];
            $sub = trim((string) ($p['subtitle'] ?? ''));
            if ($sub !== '') {
                $label .= ' (' . $sub . ')';
            }
            $push('Membre : ' . $label, (string) $p['href']);
        }

        return $out;
    }

    /**
     * @param list<array{label: string, href: string}> $suggestions
     * @return list<array{label: string, href: string}>
     */
    private function ensureFallbackSuggestions(array $suggestions, string $searchQuery): array
    {
        $extras = [
            ['label' => 'Ouvrir la recherche', 'href' => url('search') . ($searchQuery !== '' ? '?q=' . rawurlencode($searchQuery) : '')],
            ['label' => 'Consulter le guide', 'href' => url('documentation')],
            ['label' => 'Centre de commandement', 'href' => url('hub')],
        ];
        $seen = [];
        foreach ($suggestions as $s) {
            $seen[$s['href']] = true;
        }
        foreach ($extras as $extra) {
            if (isset($seen[$extra['href']])) {
                continue;
            }
            $suggestions[] = $extra;
            if (count($suggestions) >= self::MAX_SUGGESTIONS) {
                break;
            }
        }

        return $suggestions;
    }

    /**
     * @param array{
     *   documents: list<mixed>,
     *   forum: list<mixed>,
     *   personnel: list<mixed>,
     *   events: list<mixed>,
     *   training: list<mixed>,
     *   commands: list<mixed>
     * } $results
     */
    private function countContentHits(array $results): int
    {
        return count($results['documents'])
            + count($results['forum'])
            + count($results['personnel'])
            + count($results['events'])
            + count($results['training']);
    }

    /**
     * @param array{
     *   documents: list<array{title: string}>,
     *   forum: list<array{title: string}>,
     *   personnel: list<array{title: string}>,
     *   events: list<array{title: string}>,
     *   training: list<array{title: string}>,
     *   commands: list<array{title: string}>
     * } $results
     */
    private function firstHighlightTitle(array $results): ?string
    {
        foreach (['events', 'documents', 'forum', 'training', 'personnel', 'commands'] as $key) {
            if ($results[$key] !== []) {
                return (string) $results[$key][0]['title'];
            }
        }

        return null;
    }

    private function countLabel(int $n, string $singular, string $plural): string
    {
        return $n . ' ' . ($n > 1 ? $plural : $singular);
    }

    /**
     * @param list<string> $items
     */
    private function joinFrench(array $items): string
    {
        $n = count($items);
        if ($n === 0) {
            return '';
        }
        if ($n === 1) {
            return $items[0];
        }
        if ($n === 2) {
            return $items[0] . ' et ' . $items[1];
        }
        $last = array_pop($items);

        return implode(', ', $items) . ' et ' . $last;
    }
}
