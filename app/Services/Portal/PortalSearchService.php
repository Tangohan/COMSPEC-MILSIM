<?php

declare(strict_types=1);

namespace App\Services\Portal;

use App\Core\Gate;
use App\Repositories\CommunityEventRepository;
use App\Repositories\DocumentRepository;
use App\Repositories\ForumTopicRepository;
use App\Repositories\TrainingCourseRepository;
use App\Repositories\UserRepository;
use App\Services\Documents\DocumentAccessService;

/**
 * Recherche multi-périmètres du portail, toujours limitée au tenant courant.
 *
 * @phpstan-type SearchHit array{id?: int, title: string, subtitle?: string, excerpt?: string, category?: string, author?: string, href: string, updated_at?: mixed}
 * @phpstan-type SearchResult array{
 *   query: string,
 *   minLength: int,
 *   documents: list<SearchHit>,
 *   forum: list<SearchHit>,
 *   personnel: list<SearchHit>,
 *   events: list<SearchHit>,
 *   training: list<SearchHit>,
 *   commands: list<SearchHit>,
 *   meta?: array{skipped?: string|null}
 * }
 */
final class PortalSearchService
{
    public const MIN_QUERY_LEN = 2;

    public const MAX_QUERY_LEN = 200;

    private const PER_SCOPE = 12;

    public function __construct(
        private DocumentRepository $documentRepository,
        private DocumentAccessService $documentAccessService,
        private ForumTopicRepository $forumTopicRepository,
        private UserRepository $userRepository,
        private ?CommunityEventRepository $communityEventRepository = null,
        private ?TrainingCourseRepository $trainingCourseRepository = null,
    ) {}

    /**
     * @param array{
     *   documents?: bool,
     *   forum?: bool,
     *   personnel?: bool,
     *   events?: bool,
     *   training?: bool,
     *   commands?: bool
     * } $scopes
     * @return SearchResult
     */
    public function search(int $tenantId, int $userId, string $raw, Gate $gate, array $scopes = []): array
    {
        if (mb_strlen($raw) > self::MAX_QUERY_LEN) {
            $raw = mb_substr($raw, 0, self::MAX_QUERY_LEN);
        }

        $wantDocs = $scopes['documents'] ?? true;
        $wantForum = $scopes['forum'] ?? true;
        $wantPersonnel = $scopes['personnel'] ?? true;
        $wantEvents = $scopes['events'] ?? true;
        $wantTraining = $scopes['training'] ?? true;
        $wantCommands = $scopes['commands'] ?? true;

        $commands = $wantCommands ? $this->filterCommands($raw) : [];

        if (mb_strlen($raw) < self::MIN_QUERY_LEN) {
            return [
                'query' => $raw,
                'minLength' => self::MIN_QUERY_LEN,
                'documents' => [],
                'forum' => [],
                'personnel' => [],
                'events' => [],
                'training' => [],
                'commands' => $commands,
                'meta' => [
                    'skipped' => $raw !== '' ? 'short_query' : null,
                ],
            ];
        }

        $documents = [];
        if ($wantDocs && !$gate->deny('documents.view')) {
            $docs = $this->documentRepository->listForTenant(
                $tenantId,
                null,
                'published',
                $raw,
                null,
                null,
                null,
                null,
                'updated_desc'
            );
            $docs = array_values(array_filter(
                $docs,
                fn ($d) => $this->documentAccessService->canRead($d, $userId, $tenantId)
            ));
            $docs = array_slice($docs, 0, self::PER_SCOPE);
            foreach ($docs as $d) {
                $slug = (string) ($d['slug'] ?? '');
                if ($slug === '') {
                    continue;
                }
                $documents[] = [
                    'id' => (int) ($d['id'] ?? 0),
                    'title' => (string) ($d['title'] ?? 'Document'),
                    'excerpt' => $this->excerpt((string) ($d['short_description'] ?? $d['description'] ?? '')),
                    'category' => (string) ($d['category_name'] ?? ''),
                    'href' => url('documents/' . rawurlencode($slug)),
                    'updated_at' => $d['updated_at'] ?? $d['created_at'] ?? null,
                ];
            }
        }

        $forum = [];
        if ($wantForum) {
            $topics = $this->forumTopicRepository->search($tenantId, $raw, self::PER_SCOPE);
            foreach ($topics as $t) {
                $forum[] = [
                    'id' => (int) ($t['id'] ?? 0),
                    'title' => (string) ($t['title'] ?? 'Sujet'),
                    'category' => (string) ($t['category_name'] ?? ''),
                    'author' => (string) ($t['author_name'] ?? ''),
                    'href' => url('forum/topic/' . (int) ($t['id'] ?? 0)),
                    'updated_at' => $t['updated_at'] ?? null,
                ];
            }
        }

        $personnel = [];
        if ($wantPersonnel && $gate->allows('personnel.profile.view')) {
            $users = $this->userRepository->searchForPortal($tenantId, $raw, self::PER_SCOPE);
            foreach ($users as $u) {
                $id = (int) ($u['id'] ?? 0);
                if ($id < 1) {
                    continue;
                }
                $slug = trim((string) ($u['profile_slug'] ?? ''));
                $pathSeg = $slug !== '' ? $slug : (string) $id;
                $callsign = trim((string) ($u['callsign'] ?? ''));
                $personnel[] = [
                    'id' => $id,
                    'title' => (string) ($u['display_name'] ?? 'Membre'),
                    'subtitle' => $callsign,
                    'href' => url('personnel/' . rawurlencode($pathSeg)),
                ];
            }
        }

        $events = [];
        if ($wantEvents && $this->communityEventRepository !== null) {
            $events = $this->searchEvents($tenantId, $raw);
        }

        $training = [];
        if ($wantTraining && $this->trainingCourseRepository !== null) {
            $training = $this->searchTraining($tenantId, $raw);
        }

        return [
            'query' => $raw,
            'minLength' => self::MIN_QUERY_LEN,
            'documents' => $documents,
            'forum' => $forum,
            'personnel' => $personnel,
            'events' => $events,
            'training' => $training,
            'commands' => $commands,
        ];
    }

    /**
     * @return list<array{title: string, subtitle?: string, href: string}>
     */
    private function searchEvents(int $tenantId, string $raw): array
    {
        if ($this->communityEventRepository === null) {
            return [];
        }
        $tokens = $this->matchTokens($raw);
        $rows = $this->communityEventRepository->upcomingForTenant($tenantId, 80);
        $out = [];
        foreach ($rows as $row) {
            $title = (string) ($row['title'] ?? '');
            $loc = (string) ($row['location'] ?? '');
            $hay = mb_strtolower($title . ' ' . $loc);
            if ($title === '' || !$this->haystackMatchesTokens($hay, $tokens, $raw)) {
                continue;
            }
            $id = (int) ($row['id'] ?? 0);
            if ($id < 1) {
                continue;
            }
            $out[] = [
                'id' => $id,
                'title' => $title,
                'subtitle' => (string) ($row['starts_at'] ?? ''),
                'href' => url('manoeuvres'),
            ];
            if (count($out) >= self::PER_SCOPE) {
                break;
            }
        }

        return $out;
    }

    /**
     * @return list<array{title: string, subtitle?: string, href: string}>
     */
    private function searchTraining(int $tenantId, string $raw): array
    {
        if ($this->trainingCourseRepository === null) {
            return [];
        }
        $rows = $this->trainingCourseRepository->listForTenant($tenantId, 'published', null, $raw, false);
        $rows = array_slice($rows, 0, self::PER_SCOPE);
        $out = [];
        foreach ($rows as $row) {
            $slug = trim((string) ($row['slug'] ?? ''));
            if ($slug === '') {
                continue;
            }
            $out[] = [
                'id' => (int) ($row['id'] ?? 0),
                'title' => (string) ($row['title'] ?? 'Formation'),
                'subtitle' => $this->excerpt((string) ($row['short_description'] ?? '')),
                'href' => url('formations/' . rawurlencode($slug)),
            ];
        }

        return $out;
    }

    /**
     * @return list<array{title: string, subtitle: string, href: string}>
     */
    public function filterCommands(string $raw): array
    {
        $all = [
            ['title' => 'Centre de commandement', 'subtitle' => 'Hub et raccourcis', 'href' => url('hub'), 'keywords' => 'hub centre commandement'],
            ['title' => 'Manœuvres', 'subtitle' => 'Présences et pointage', 'href' => url('manoeuvres'), 'keywords' => 'manoeuvres manœuvre manoeuvres pointage présence calendrier'],
            ['title' => 'Boîte de réception', 'subtitle' => 'Messages et actions', 'href' => url('boite-reception'), 'keywords' => 'boîte reception inbox messages'],
            ['title' => 'Centre d’actions', 'subtitle' => 'Éléments à traiter', 'href' => url('centre-actions'), 'keywords' => 'actions centre'],
            ['title' => 'Nouveau sujet forum', 'subtitle' => 'Démarrer une discussion', 'href' => url('forum/new-topic'), 'keywords' => 'forum sujet nouveau publier'],
            ['title' => 'Forum', 'subtitle' => 'Briefings et discussions', 'href' => url('forum'), 'keywords' => 'forum briefing'],
            ['title' => 'Assistant', 'subtitle' => 'Aide guidée', 'href' => url('assistant'), 'keywords' => 'assistant aide'],
            ['title' => 'Recherche', 'subtitle' => 'Parcourir le portail', 'href' => url('search'), 'keywords' => 'recherche search'],
            ['title' => 'Formations', 'subtitle' => 'Catalogue des parcours', 'href' => url('formations'), 'keywords' => 'formations catalogue'],
            ['title' => 'Documents', 'subtitle' => 'Bibliothèque de la communauté', 'href' => url('documents'), 'keywords' => 'documents doctrine consignes bibliothèque'],
            ['title' => 'Ma fiche', 'subtitle' => 'Profil personnel', 'href' => url('personnel/me'), 'keywords' => 'fiche personnel profil'],
            ['title' => 'Poste de commandement', 'subtitle' => 'Modes ATAK et terrain', 'href' => url('c2'), 'keywords' => 'c2 poste commandement atak'],
            ['title' => 'Salle de guerre', 'subtitle' => 'Briefing collectif', 'href' => url('salle-de-guerre'), 'keywords' => 'salle guerre'],
            ['title' => 'Guide intégré', 'subtitle' => 'Aide et documentation du portail', 'href' => url('documentation'), 'keywords' => 'guide documentation aide'],
        ];

        $needle = mb_strtolower(trim($raw));
        if ($needle === '') {
            return array_map(static function (array $c): array {
                return [
                    'title' => $c['title'],
                    'subtitle' => $c['subtitle'],
                    'href' => $c['href'],
                ];
            }, array_slice($all, 0, 8));
        }

        $tokens = $this->matchTokens($raw);
        $out = [];
        foreach ($all as $c) {
            $hay = mb_strtolower($c['title'] . ' ' . $c['subtitle'] . ' ' . $c['keywords']);
            if ($this->haystackMatchesTokens($hay, $tokens, $raw)) {
                $out[] = [
                    'title' => $c['title'],
                    'subtitle' => $c['subtitle'],
                    'href' => $c['href'],
                ];
            }
        }

        return $out;
    }

    /**
     * @return list<string>
     */
    private function matchTokens(string $raw): array
    {
        $normalized = mb_strtolower($raw);
        $normalized = preg_replace('/[^\p{L}\p{N}\s]+/u', ' ', $normalized) ?? $normalized;
        $parts = preg_split('/\s+/u', trim($normalized), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return array_values(array_filter(
            $parts,
            static fn (string $t): bool => mb_strlen($t) >= 3
        ));
    }

    /**
     * @param list<string> $tokens
     */
    private function haystackMatchesTokens(string $hay, array $tokens, string $raw): bool
    {
        $needle = mb_strtolower(trim($raw));
        if ($needle !== '' && str_contains($hay, $needle)) {
            return true;
        }
        foreach ($tokens as $token) {
            if (str_contains($hay, $token)) {
                return true;
            }
        }

        return false;
    }

    private function excerpt(string $html, int $max = 160): string
    {
        $t = trim(preg_replace('/\s+/u', ' ', strip_tags($html)) ?? '');
        if (mb_strlen($t) <= $max) {
            return $t;
        }

        return mb_substr($t, 0, $max - 1) . '…';
    }
}
