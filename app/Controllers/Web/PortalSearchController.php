<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Gate;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\DocumentRepository;
use App\Repositories\ForumTopicRepository;
use App\Repositories\UserRepository;
use App\Services\Documents\DocumentAccessService;

final class PortalSearchController
{
    private const MIN_QUERY_LEN = 2;

    private const MAX_QUERY_LEN = 200;

    private const PER_SCOPE = 12;

    public function __construct(
        private DocumentRepository $documentRepository,
        private DocumentAccessService $documentAccessService,
        private ForumTopicRepository $forumTopicRepository,
        private UserRepository $userRepository,
    ) {}

    public function index(Request $request, array $params = []): Response
    {
        $q = trim((string) $request->query('q', ''));
        if (mb_strlen($q) > self::MAX_QUERY_LEN) {
            $q = mb_substr($q, 0, self::MAX_QUERY_LEN);
        }

        $gate = Gate::getInstance();

        return Response::view('layout.main', [
            'title' => 'Recherche portail',
            'content' => 'portal.search',
            'query' => $q,
            'canSearchDocuments' => !$gate->deny('documents.view'),
            'canSearchPersonnel' => $gate->allows('personnel.profile.view'),
        ]);
    }

    public function apiSearch(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        $userId = (int) Session::get('user_id');
        if ($tenantId < 1 || $userId < 1) {
            return Response::json(['success' => false, 'error' => 'Non authentifié'], 401);
        }

        $raw = trim((string) $request->query('q', ''));
        if (mb_strlen($raw) > self::MAX_QUERY_LEN) {
            $raw = mb_substr($raw, 0, self::MAX_QUERY_LEN);
        }

        $gate = Gate::getInstance();
        $wantDocs = $this->queryFlag($request, 'documents', true);
        $wantForum = $this->queryFlag($request, 'forum', true);
        $wantPersonnel = $this->queryFlag($request, 'personnel', true);

        if (mb_strlen($raw) < self::MIN_QUERY_LEN) {
            return Response::json([
                'success' => true,
                'query' => $raw,
                'minLength' => self::MIN_QUERY_LEN,
                'documents' => [],
                'forum' => [],
                'personnel' => [],
                'meta' => [
                    'skipped' => $raw !== '' ? 'short_query' : null,
                ],
            ]);
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

        return Response::json([
            'success' => true,
            'query' => $raw,
            'minLength' => self::MIN_QUERY_LEN,
            'documents' => $documents,
            'forum' => $forum,
            'personnel' => $personnel,
        ]);
    }

    private function queryFlag(Request $request, string $key, bool $default): bool
    {
        $v = $request->query($key);
        if ($v === null || $v === '') {
            return $default;
        }

        return in_array((string) $v, ['1', 'true', 'yes', 'on'], true);
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
