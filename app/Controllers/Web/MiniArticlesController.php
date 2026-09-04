<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\TenantMiniArticleRepository;
use App\Support\MiniArticleHtml;

final class MiniArticlesController
{
    public function __construct(
        private ?TenantMiniArticleRepository $articles = null,
    ) {
        $this->articles ??= new TenantMiniArticleRepository();
    }

    public function index(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        if ($tenantId < 1) {
            return Response::redirect(url('login'));
        }

        $rows = $this->articles->listPublishedForTenant($tenantId, 40);
        $items = array_map(fn (array $row): array => $this->present($row), $rows);

        return Response::view('layout.main', [
            'content' => 'articles.index',
            'title' => 'Articles',
            'miniArticles' => $items,
            'canManageMiniArticles' => $this->canManage(),
        ]);
    }

    public function show(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        if ($tenantId < 1) {
            return Response::redirect(url('login'));
        }
        $slug = trim((string) ($params['slug'] ?? ''));
        $row = $slug !== '' ? $this->articles->findPublishedBySlug($tenantId, $slug) : null;
        if (!$row) {
            Session::flash('error', 'Article introuvable.');

            return Response::redirect(url('articles'));
        }

        return Response::view('layout.main', [
            'content' => 'articles.show',
            'title' => (string) ($row['title'] ?? 'Article'),
            'miniArticle' => $this->present($row),
            'canManageMiniArticles' => $this->canManage(),
        ]);
    }

    private function canManage(): bool
    {
        return function_exists('can') && (
            can('admin.organization') || can('admin.access') || can('site.support')
        );
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function present(array $row): array
    {
        $tags = [];
        $rawTags = $row['tags_json'] ?? null;
        if (is_string($rawTags) && $rawTags !== '') {
            $decoded = json_decode($rawTags, true);
            if (is_array($decoded)) {
                foreach ($decoded as $t) {
                    $t = trim((string) $t);
                    if ($t !== '') {
                        $tags[] = $t;
                    }
                }
            }
        }
        $gallery = [];
        $rawGal = $row['gallery_json'] ?? null;
        if (is_string($rawGal) && $rawGal !== '') {
            $decoded = json_decode($rawGal, true);
            if (is_array($decoded)) {
                foreach ($decoded as $path) {
                    $url = MiniArticleHtml::publicUrl((string) $path);
                    if ($url !== null) {
                        $gallery[] = $url;
                    }
                }
            }
        }

        return [
            'id' => (int) ($row['id'] ?? 0),
            'title' => (string) ($row['title'] ?? ''),
            'slug' => (string) ($row['slug'] ?? ''),
            'excerpt' => trim((string) ($row['excerpt'] ?? '')),
            'body_html' => (string) ($row['body_html'] ?? ''),
            'tags' => $tags,
            'cover_url' => MiniArticleHtml::publicUrl(isset($row['cover_path']) ? (string) $row['cover_path'] : null),
            'gallery' => $gallery,
            'pinned' => !empty($row['pinned']),
            'published_at' => isset($row['published_at']) ? (string) $row['published_at'] : null,
            'href' => url('articles/' . rawurlencode((string) ($row['slug'] ?? ''))),
        ];
    }
}
