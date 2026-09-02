<?php

declare(strict_types=1);

namespace App\Controllers\Admin\Organization;

use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\TenantMiniArticleRepository;
use App\Support\MiniArticleHtml;

final class TenantMiniArticlesController
{
    private const MAX_IMAGE_BYTES = 12 * 1024 * 1024;

    /** @var list<string> */
    private const ALLOWED_IMAGE_MIMES = ['image/jpeg', 'image/png', 'image/webp'];

    public function __construct(
        private ?TenantMiniArticleRepository $articles = null,
    ) {
        $this->articles ??= new TenantMiniArticleRepository();
    }

    public function index(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        if ($tenantId <= 0) {
            return Response::redirect(url('dashboard'));
        }

        return Response::view('layout.main', [
            'content' => 'admin.organization.mini_articles_index',
            'title' => 'Mini-articles',
            'miniArticles' => $this->articles->listForTenant($tenantId),
            'miniArticlesSchemaReady' => $this->articles->schemaReady(),
        ]);
    }

    public function create(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        if ($tenantId <= 0) {
            return Response::redirect(url('dashboard'));
        }

        return Response::view('layout.main', [
            'content' => 'admin.organization.mini_articles_form',
            'title' => 'Nouveau mini-article',
            'miniArticle' => null,
            'formAction' => url('back-office/articles'),
        ]);
    }

    public function store(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        if ($tenantId <= 0) {
            return Response::redirect(url('dashboard'));
        }
        if (!Csrf::validate((string) $request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée.');

            return Response::redirect(url('back-office/articles/create'));
        }
        if (!$this->articles->schemaReady()) {
            Session::flash('error', 'Le module mini-articles n’est pas encore installé. Lancez les migrations.');

            return Response::redirect(url('back-office/articles'));
        }

        $data = $this->normalize($request, $tenantId, null);
        if (($data['_error'] ?? '') !== '') {
            Session::flash('error', (string) $data['_error']);

            return Response::redirect(url('back-office/articles/create'));
        }
        unset($data['_error']);
        $data['author_user_id'] = (int) (Session::get('user_id') ?? 0) ?: null;
        $id = $this->articles->insert($tenantId, $data);
        Session::flash('success', ($data['status'] ?? '') === 'published'
            ? 'Mini-article publié.'
            : 'Brouillon enregistré.');

        return Response::redirect($id > 0
            ? url('back-office/articles/' . $id . '/edit')
            : url('back-office/articles'));
    }

    public function edit(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        if ($tenantId <= 0) {
            return Response::redirect(url('dashboard'));
        }
        $id = (int) ($params['id'] ?? 0);
        $row = $id > 0 ? $this->articles->findByIdForTenant($id, $tenantId) : null;
        if (!$row) {
            Session::flash('error', 'Article introuvable.');

            return Response::redirect(url('back-office/articles'));
        }

        return Response::view('layout.main', [
            'content' => 'admin.organization.mini_articles_form',
            'title' => 'Modifier le mini-article',
            'miniArticle' => $row,
            'formAction' => url('back-office/articles/' . $id . '/update'),
        ]);
    }

    public function update(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        if ($tenantId <= 0) {
            return Response::redirect(url('dashboard'));
        }
        if (!Csrf::validate((string) $request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée.');

            return Response::redirect(url('back-office/articles'));
        }
        $id = (int) ($params['id'] ?? 0);
        $row = $id > 0 ? $this->articles->findByIdForTenant($id, $tenantId) : null;
        if (!$row) {
            Session::flash('error', 'Article introuvable.');

            return Response::redirect(url('back-office/articles'));
        }
        $data = $this->normalize($request, $tenantId, $row);
        if (($data['_error'] ?? '') !== '') {
            Session::flash('error', (string) $data['_error']);

            return Response::redirect(url('back-office/articles/' . $id . '/edit'));
        }
        unset($data['_error']);
        $this->articles->update($id, $tenantId, $data);
        Session::flash('success', 'Modifications enregistrées.');

        return Response::redirect(url('back-office/articles/' . $id . '/edit'));
    }

    public function delete(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        if ($tenantId <= 0) {
            return Response::redirect(url('dashboard'));
        }
        if (!Csrf::validate((string) $request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée.');

            return Response::redirect(url('back-office/articles'));
        }
        $id = (int) ($params['id'] ?? 0);
        $row = $id > 0 ? $this->articles->findByIdForTenant($id, $tenantId) : null;
        if ($row) {
            $this->deleteStoredFile(isset($row['cover_path']) ? (string) $row['cover_path'] : null);
            foreach ($this->decodeGallery($row['gallery_json'] ?? null) as $path) {
                $this->deleteStoredFile($path);
            }
            $this->articles->delete($id, $tenantId);
            Session::flash('success', 'Article supprimé.');
        }

        return Response::redirect(url('back-office/articles'));
    }

    /**
     * @param array<string, mixed>|null $existing
     * @return array<string, mixed>
     */
    private function normalize(Request $request, int $tenantId, ?array $existing): array
    {
        $title = trim((string) $request->input('title', ''));
        if ($title === '') {
            return ['_error' => 'Le titre est obligatoire.'];
        }
        if (mb_strlen($title) > 255) {
            return ['_error' => 'Titre trop long (255 caractères max.).'];
        }

        $excerpt = trim((string) $request->input('excerpt', ''));
        if (mb_strlen($excerpt) > 2000) {
            return ['_error' => 'La description est trop longue (2000 caractères max.).'];
        }

        $bodyHtml = MiniArticleHtml::sanitize((string) $request->input('body_html', ''));
        if ($bodyHtml === '') {
            return ['_error' => 'Le corps de l’article est obligatoire.'];
        }

        $tags = MiniArticleHtml::parseTags((string) $request->input('tags', ''));
        $status = trim((string) $request->input('status', 'draft')) === 'published' ? 'published' : 'draft';
        $pinned = (bool) $request->input('pinned');

        $exceptId = $existing !== null ? (int) ($existing['id'] ?? 0) : null;
        $slugBase = MiniArticleHtml::slugify($title, $exceptId ?? 0);
        $slug = $slugBase;
        $n = 2;
        while ($this->articles->slugExists($tenantId, $slug, $exceptId)) {
            $slug = $slugBase . '-' . $n;
            $n++;
            if ($n > 50) {
                $slug = $slugBase . '-' . bin2hex(random_bytes(3));
                break;
            }
        }

        $coverPath = isset($existing['cover_path']) ? (string) ($existing['cover_path'] ?? '') : '';
        if ((bool) $request->input('remove_cover')) {
            $this->deleteStoredFile($coverPath !== '' ? $coverPath : null);
            $coverPath = '';
        }
        $coverUpload = $this->storeUploadedImage($tenantId, 'cover', 'cover', 1600);
        if (($coverUpload['error'] ?? null) !== null) {
            return ['_error' => (string) $coverUpload['error']];
        }
        if (($coverUpload['path'] ?? null) !== null) {
            $this->deleteStoredFile($coverPath !== '' ? $coverPath : null);
            $coverPath = (string) $coverUpload['path'];
        }

        $gallery = $this->decodeGallery($existing['gallery_json'] ?? null);
        $removeGallery = $request->input('remove_gallery');
        if (is_array($removeGallery)) {
            $keep = [];
            foreach ($gallery as $path) {
                if (in_array($path, $removeGallery, true)) {
                    $this->deleteStoredFile($path);
                } else {
                    $keep[] = $path;
                }
            }
            $gallery = $keep;
        }

        $galleryFiles = $_FILES['gallery'] ?? null;
        if (is_array($galleryFiles) && isset($galleryFiles['name']) && is_array($galleryFiles['name'])) {
            $count = count($galleryFiles['name']);
            for ($i = 0; $i < $count && count($gallery) < 6; $i++) {
                $one = [
                    'name' => $galleryFiles['name'][$i] ?? '',
                    'type' => $galleryFiles['type'][$i] ?? '',
                    'tmp_name' => $galleryFiles['tmp_name'][$i] ?? '',
                    'error' => $galleryFiles['error'][$i] ?? UPLOAD_ERR_NO_FILE,
                    'size' => $galleryFiles['size'][$i] ?? 0,
                ];
                $_FILES['__gallery_one'] = $one;
                $up = $this->storeUploadedImage($tenantId, '__gallery_one', 'gal', 1400);
                unset($_FILES['__gallery_one']);
                if (($up['error'] ?? null) !== null) {
                    return ['_error' => (string) $up['error']];
                }
                if (($up['path'] ?? null) !== null) {
                    $gallery[] = (string) $up['path'];
                }
            }
        }

        $publishedAt = null;
        if ($status === 'published') {
            $publishedAt = isset($existing['published_at']) && (string) ($existing['published_at'] ?? '') !== ''
                ? (string) $existing['published_at']
                : date('Y-m-d H:i:s');
            if (($existing['status'] ?? '') !== 'published') {
                $publishedAt = date('Y-m-d H:i:s');
            }
        }

        return [
            '_error' => '',
            'title' => $title,
            'slug' => $slug,
            'excerpt' => $excerpt !== '' ? $excerpt : null,
            'body_html' => $bodyHtml,
            'tags_json' => $tags !== [] ? json_encode($tags, JSON_UNESCAPED_UNICODE) : null,
            'cover_path' => $coverPath !== '' ? $coverPath : null,
            'gallery_json' => $gallery !== [] ? json_encode(array_values($gallery), JSON_UNESCAPED_UNICODE) : null,
            'status' => $status,
            'published_at' => $publishedAt,
            'pinned' => $pinned,
        ];
    }

    /**
     * @return list<string>
     */
    private function decodeGallery(mixed $raw): array
    {
        if (is_string($raw) && $raw !== '') {
            $decoded = json_decode($raw, true);
        } elseif (is_array($raw)) {
            $decoded = $raw;
        } else {
            return [];
        }
        if (!is_array($decoded)) {
            return [];
        }
        $out = [];
        foreach ($decoded as $path) {
            $path = trim((string) $path);
            if ($path !== '' && str_starts_with(str_replace('\\', '/', $path), 'uploads/tenant-articles/')) {
                $out[] = $path;
            }
        }

        return array_values(array_unique($out));
    }

    /**
     * @return array{path: ?string, error: ?string}
     */
    private function storeUploadedImage(int $tenantId, string $field, string $prefix, int $maxWidth): array
    {
        $file = $_FILES[$field] ?? null;
        if (!is_array($file)) {
            return ['path' => null, 'error' => null];
        }
        $err = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($err === UPLOAD_ERR_NO_FILE) {
            return ['path' => null, 'error' => null];
        }
        if ($err !== UPLOAD_ERR_OK) {
            return ['path' => null, 'error' => 'Envoi d’image impossible. Vérifiez la taille (max. 12 Mo).'];
        }
        $tmp = (string) ($file['tmp_name'] ?? '');
        if ($tmp === '' || !is_uploaded_file($tmp)) {
            return ['path' => null, 'error' => 'Fichier image invalide.'];
        }
        if ((int) ($file['size'] ?? 0) > self::MAX_IMAGE_BYTES) {
            return ['path' => null, 'error' => 'Image trop volumineuse : limite de 12 Mo.'];
        }
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($tmp) ?: '';
        if (!in_array($mime, self::ALLOWED_IMAGE_MIMES, true)) {
            return ['path' => null, 'error' => 'Format non pris en charge. Utilisez JPG, PNG ou WebP.'];
        }

        $dirRel = 'uploads/tenant-articles/' . $tenantId;
        $dirAbs = base_path('public/' . $dirRel);
        if (!is_dir($dirAbs) && !@mkdir($dirAbs, 0755, true) && !is_dir($dirAbs)) {
            return ['path' => null, 'error' => 'Stockage images indisponible.'];
        }

        $ext = match ($mime) {
            'image/png' => 'png',
            'image/webp' => 'webp',
            default => 'jpg',
        };
        $name = $prefix . '-' . bin2hex(random_bytes(8)) . '.' . $ext;
        $destAbs = $dirAbs . '/' . $name;
        $destRel = $dirRel . '/' . $name;

        if (!$this->writeImage($tmp, $destAbs, $maxWidth, $ext === 'png')) {
            return ['path' => null, 'error' => 'Impossible de traiter cette image.'];
        }

        return ['path' => $destRel, 'error' => null];
    }

    private function writeImage(string $tmpPath, string $destPath, int $maxWidth, bool $keepAlpha): bool
    {
        if (!function_exists('imagecreatefromstring')) {
            return @copy($tmpPath, $destPath);
        }
        $bin = @file_get_contents($tmpPath);
        if ($bin === false) {
            return false;
        }
        $im = @imagecreatefromstring($bin);
        if (!$im) {
            return false;
        }
        $w = imagesx($im);
        $h = imagesy($im);
        if ($w < 1 || $h < 1) {
            imagedestroy($im);

            return false;
        }
        if ($w > $maxWidth) {
            $newH = max(1, (int) round($h * ($maxWidth / $w)));
            $scaled = imagecreatetruecolor($maxWidth, $newH);
            if ($scaled === false) {
                imagedestroy($im);

                return false;
            }
            if ($keepAlpha) {
                imagealphablending($scaled, false);
                imagesavealpha($scaled, true);
                $transparent = imagecolorallocatealpha($scaled, 0, 0, 0, 127);
                imagefilledrectangle($scaled, 0, 0, $maxWidth, $newH, $transparent);
            }
            imagecopyresampled($scaled, $im, 0, 0, 0, 0, $maxWidth, $newH, $w, $h);
            imagedestroy($im);
            $im = $scaled;
        }

        $ok = false;
        $ext = strtolower(pathinfo($destPath, PATHINFO_EXTENSION));
        if ($ext === 'png') {
            $ok = imagepng($im, $destPath, 6);
        } elseif ($ext === 'webp' && function_exists('imagewebp')) {
            $ok = imagewebp($im, $destPath, 82);
        } else {
            $ok = imagejpeg($im, $destPath, 85);
        }
        imagedestroy($im);

        return (bool) $ok;
    }

    private function deleteStoredFile(?string $relativePath): void
    {
        $relativePath = trim((string) $relativePath);
        if ($relativePath === '' || str_contains($relativePath, '..')) {
            return;
        }
        if (!str_starts_with(str_replace('\\', '/', $relativePath), 'uploads/tenant-articles/')) {
            return;
        }
        $abs = base_path('public/' . ltrim(str_replace('\\', '/', $relativePath), '/'));
        if (is_file($abs)) {
            @unlink($abs);
        }
    }
}
