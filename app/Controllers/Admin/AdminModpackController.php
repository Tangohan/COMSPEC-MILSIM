<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\ModpackRepository;

class AdminModpackController
{
    private const MODPACK_MAX_SIZE = 2 * 1024 * 1024 * 1024; // 2 Go
    private const IMAGE_MAX_SIZE = 5 * 1024 * 1024; // 5 Mo
    private const CHUNK_MAX_SIZE = 8 * 1024 * 1024; // 8 Mo
    private const MODPACK_MIMES = [
        'application/zip',
        'application/x-zip-compressed',
        'application/x-rar-compressed',
        'application/vnd.rar',
        'application/x-7z-compressed',
        'application/octet-stream',
    ];
    private const IMAGE_MIMES = ['image/jpeg', 'image/png', 'image/webp'];
    private const ALLOWED_EXTENSIONS = ['zip', 'rar', '7z'];

    public function __construct(
        private ModpackRepository $modpackRepository
    ) {}

    public function index(Request $request, array $params = []): Response
    {
        $tenantId = Session::get('tenant_id');
        if (!$tenantId) {
            return Response::redirect(url('login'));
        }
        $modpacks = $this->modpackRepository->listForTenant((int) $tenantId);
        return Response::view('layout.main', [
            'content' => 'admin.modpacks.index',
            'title' => 'Modpacks',
            'modpacks' => $modpacks,
            'seo_robots' => 'noindex,nofollow',
        ]);
    }

    public function create(Request $request, array $params = []): Response
    {
        $tenantId = Session::get('tenant_id');
        if (!$tenantId) {
            return Response::redirect(url('login'));
        }
        return Response::view('layout.main', [
            'content' => 'admin.modpacks.create',
            'title' => 'Nouveau modpack',
            'seo_robots' => 'noindex,nofollow',
            'modpackUploadLimits' => $this->uploadLimitsPayload(),
        ]);
    }

    public function store(Request $request, array $params = []): Response
    {
        $tenantId = Session::get('tenant_id');
        if (!$tenantId) {
            return Response::redirect(url('login'));
        }
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::set('error', 'Session expirée.');
            return Response::redirect(url('admin/modpacks/create'));
        }
        $name = trim((string) $request->input('name'));
        $slugInput = trim((string) $request->input('slug'));
        $effectiveSlug = $slugInput !== '' ? $slugInput : $this->modpackRepository->slugify($name);
        if ($effectiveSlug === '') {
            $effectiveSlug = 'modpack';
        }
        if ($name === '') {
            Session::set('error', 'Le nom est requis.');
            return Response::redirect(url('admin/modpacks/create'));
        }
        if ($this->modpackRepository->slugExists((int) $tenantId, $effectiveSlug)) {
            Session::set('error', 'Ce slug existe déjà.');
            return Response::redirect(url('admin/modpacks/create'));
        }

        $externalUrl = $this->normalizeExternalUrl((string) $request->input('url', ''));
        if ($externalUrl === false) {
            Session::set('error', 'L’URL externe doit commencer par http:// ou https://.');
            return Response::redirect(url('admin/modpacks/create'));
        }

        $staged = $this->resolveStagedUpload((int) $tenantId, (string) $request->input('staged_upload_id', ''));
        $file = $_FILES['modpack_file'] ?? null;
        $hasDirectFile = is_array($file) && ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK;
        if ($hasDirectFile) {
            $validationError = $this->validateModpackUpload($file['tmp_name'], (int) $file['size'], (string) ($file['name'] ?? ''));
            if ($validationError !== null) {
                Session::set('error', $validationError);
                return Response::redirect(url('admin/modpacks/create'));
            }
        }

        $now = date('Y-m-d H:i:s');
        $userId = Session::get('user_id');
        $data = [
            'tenant_id' => (int) $tenantId,
            'name' => $name,
            'slug' => $effectiveSlug,
            'url' => $externalUrl,
            'version' => trim((string) $request->input('version')) ?: null,
            'file_path' => null,
            'size' => null,
            'released_at' => $now,
            'updated_at' => $now,
            'description' => trim((string) $request->input('description')) ?: null,
            'created_by' => $userId ? (int) $userId : null,
        ];
        $id = $this->modpackRepository->create($data);
        $baseDir = base_path('storage/uploads/modpacks/' . $id);
        if (!is_dir($baseDir)) {
            mkdir($baseDir, 0755, true);
        }

        $stored = null;
        if ($staged !== null) {
            $stored = $this->commitStagedFile($staged, $id, $baseDir);
            $this->cleanupStagingDir((int) $tenantId, (string) $request->input('staged_upload_id', ''));
        } elseif ($hasDirectFile) {
            $stored = $this->storeUploadedFile($file['tmp_name'], (string) ($file['name'] ?? ''), (int) $file['size'], $id, $baseDir);
        }

        if ($stored !== null) {
            $this->modpackRepository->update($id, (int) $tenantId, [
                'file_path' => $stored['file_path'],
                'size' => $stored['size'],
                'updated_at' => $now,
            ]);
        }

        $this->processImageUploads($id, $baseDir, 0);
        Session::set('success', 'Modpack créé.');
        return Response::redirect(url('admin/modpacks'));
    }

    public function edit(Request $request, array $params = []): Response
    {
        $tenantId = Session::get('tenant_id');
        if (!$tenantId) {
            return Response::redirect(url('login'));
        }
        $id = (int) ($params['id'] ?? 0);
        $modpack = $this->modpackRepository->findById($id, (int) $tenantId);
        if (!$modpack) {
            return (new Response())->setStatusCode(404)->setBody('Modpack non trouvé.');
        }
        return Response::view('layout.main', [
            'content' => 'admin.modpacks.edit',
            'title' => 'Modifier le modpack',
            'modpack' => $modpack,
            'seo_robots' => 'noindex,nofollow',
            'modpackUploadLimits' => $this->uploadLimitsPayload(),
        ]);
    }

    public function update(Request $request, array $params = []): Response
    {
        $tenantId = Session::get('tenant_id');
        if (!$tenantId) {
            return Response::redirect(url('login'));
        }
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::set('error', 'Session expirée.');
            return Response::redirect(url('admin/modpacks'));
        }
        $id = (int) ($params['id'] ?? 0);
        $modpack = $this->modpackRepository->findById($id, (int) $tenantId);
        if (!$modpack) {
            return (new Response())->setStatusCode(404)->setBody('Modpack non trouvé.');
        }
        $name = trim((string) $request->input('name'));
        $slugInput = trim((string) $request->input('slug'));
        $effectiveSlug = $slugInput !== '' ? $slugInput : $this->modpackRepository->slugify($name);
        if ($effectiveSlug === '') {
            $effectiveSlug = 'modpack';
        }
        if ($name === '') {
            Session::set('error', 'Le nom est requis.');
            return Response::redirect(url('admin/modpacks/' . $id . '/edit'));
        }
        if ($this->modpackRepository->slugExists((int) $tenantId, $effectiveSlug, $id)) {
            Session::set('error', 'Ce slug existe déjà.');
            return Response::redirect(url('admin/modpacks/' . $id . '/edit'));
        }

        $externalUrl = $this->normalizeExternalUrl((string) $request->input('url', ''));
        if ($externalUrl === false) {
            Session::set('error', 'L’URL externe doit commencer par http:// ou https://.');
            return Response::redirect(url('admin/modpacks/' . $id . '/edit'));
        }

        $now = date('Y-m-d H:i:s');
        $data = [
            'name' => $name,
            'slug' => $effectiveSlug,
            'url' => $externalUrl,
            'version' => trim((string) $request->input('version')) ?: null,
            'description' => trim((string) $request->input('description')) ?: null,
            'updated_at' => $now,
        ];
        $baseDir = base_path('storage/uploads/modpacks/' . $id);
        $oldPath = trim((string) ($modpack['file_path'] ?? ''));

        $staged = $this->resolveStagedUpload((int) $tenantId, (string) $request->input('staged_upload_id', ''));
        $file = $_FILES['modpack_file'] ?? null;
        $hasDirectFile = is_array($file) && ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK;
        if ($hasDirectFile) {
            $validationError = $this->validateModpackUpload($file['tmp_name'], (int) $file['size'], (string) ($file['name'] ?? ''));
            if ($validationError !== null) {
                Session::set('error', $validationError);
                return Response::redirect(url('admin/modpacks/' . $id . '/edit'));
            }
        }

        $stored = null;
        if ($staged !== null) {
            if (!is_dir($baseDir)) {
                mkdir($baseDir, 0755, true);
            }
            $stored = $this->commitStagedFile($staged, $id, $baseDir);
            $this->cleanupStagingDir((int) $tenantId, (string) $request->input('staged_upload_id', ''));
        } elseif ($hasDirectFile) {
            if (!is_dir($baseDir)) {
                mkdir($baseDir, 0755, true);
            }
            $stored = $this->storeUploadedFile($file['tmp_name'], (string) ($file['name'] ?? ''), (int) $file['size'], $id, $baseDir);
        }

        if ($stored !== null) {
            $data['file_path'] = $stored['file_path'];
            $data['size'] = $stored['size'];
            if ($oldPath !== '' && $oldPath !== $stored['file_path']) {
                $oldFull = base_path('storage/uploads/' . $oldPath);
                if (is_file($oldFull)) {
                    @unlink($oldFull);
                }
            }
        }

        $this->modpackRepository->update($id, (int) $tenantId, $data);
        $deleteIds = $request->input('delete_image');
        if (is_array($deleteIds)) {
            foreach ($deleteIds as $imgId) {
                $imgId = (int) $imgId;
                if ($imgId > 0) {
                    $img = $this->modpackRepository->getImageById($imgId);
                    if ($img && (int) $img['tenant_id'] === (int) $tenantId) {
                        $this->modpackRepository->deleteImage($imgId);
                        $p = base_path('storage/uploads/' . $img['file_path']);
                        if (is_file($p)) {
                            @unlink($p);
                        }
                    }
                }
            }
        }
        $existingCount = count($modpack['images'] ?? []);
        $this->processImageUploads($id, $baseDir, $existingCount);
        Session::set('success', 'Modpack mis à jour.');
        return Response::redirect(url('admin/modpacks'));
    }

    public function delete(Request $request, array $params = []): Response
    {
        $tenantId = Session::get('tenant_id');
        if (!$tenantId) {
            return Response::redirect(url('login'));
        }
        $id = (int) ($params['id'] ?? 0);
        $modpack = $this->modpackRepository->findById($id, (int) $tenantId);
        if (!$modpack) {
            return (new Response())->setStatusCode(404)->setBody('Modpack non trouvé.');
        }
        $this->modpackRepository->delete($id, (int) $tenantId);
        $dir = base_path('storage/uploads/modpacks/' . $id);
        if (is_dir($dir)) {
            $this->removeDirRecursive($dir);
        }
        Session::set('success', 'Modpack supprimé.');
        return Response::redirect(url('admin/modpacks'));
    }

    /** Initialise un upload par morceaux (gros fichiers). */
    public function uploadInit(Request $request, array $params = []): Response
    {
        $tenantId = Session::get('tenant_id');
        if (!$tenantId) {
            return Response::json(['success' => false, 'message' => 'Non autorisé.'], 403);
        }
        if (!Csrf::validate($this->requestToken($request))) {
            return Response::json(['success' => false, 'message' => 'Session expirée.'], 403);
        }

        $filename = basename(trim((string) ($request->input('filename') ?? '')));
        $size = (int) ($request->input('size') ?? 0);
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        if ($filename === '' || !in_array($ext, self::ALLOWED_EXTENSIONS, true)) {
            return Response::json(['success' => false, 'message' => 'Extension attendue : ZIP, RAR ou 7z.'], 422);
        }
        if ($size < 1 || $size > self::MODPACK_MAX_SIZE) {
            return Response::json(['success' => false, 'message' => 'Taille invalide (max 2 Go).'], 422);
        }

        $uploadId = bin2hex(random_bytes(16));
        $dir = $this->stagingDir((int) $tenantId, $uploadId);
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            return Response::json(['success' => false, 'message' => 'Impossible de préparer le dépôt.'], 500);
        }
        file_put_contents($dir . '/meta.json', json_encode([
            'filename' => $filename,
            'size' => $size,
            'ext' => $ext,
            'created_at' => time(),
            'received' => [],
        ], JSON_UNESCAPED_UNICODE));

        return Response::json([
            'success' => true,
            'upload_id' => $uploadId,
            'chunk_size' => self::CHUNK_MAX_SIZE,
        ]);
    }

    /** Reçoit un morceau d’archive. */
    public function uploadChunk(Request $request, array $params = []): Response
    {
        $tenantId = Session::get('tenant_id');
        if (!$tenantId) {
            return Response::json(['success' => false, 'message' => 'Non autorisé.'], 403);
        }
        if (!Csrf::validate($this->requestToken($request))) {
            return Response::json(['success' => false, 'message' => 'Session expirée.'], 403);
        }

        $uploadId = preg_replace('/[^a-f0-9]/', '', (string) ($request->input('upload_id') ?? '')) ?? '';
        $index = (int) ($request->input('chunk_index') ?? -1);
        $total = (int) ($request->input('chunk_total') ?? 0);
        if ($uploadId === '' || $index < 0 || $total < 1 || $index >= $total) {
            return Response::json(['success' => false, 'message' => 'Paramètres de morceau invalides.'], 422);
        }

        $dir = $this->stagingDir((int) $tenantId, $uploadId);
        $metaPath = $dir . '/meta.json';
        if (!is_file($metaPath)) {
            return Response::json(['success' => false, 'message' => 'Upload inconnu ou expiré.'], 404);
        }

        $chunk = $_FILES['chunk'] ?? null;
        if (!is_array($chunk) || ($chunk['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            return Response::json(['success' => false, 'message' => 'Morceau manquant.'], 422);
        }
        if ((int) $chunk['size'] > self::CHUNK_MAX_SIZE + 1024) {
            return Response::json(['success' => false, 'message' => 'Morceau trop volumineux.'], 422);
        }

        $dest = $dir . '/chunk_' . str_pad((string) $index, 6, '0', STR_PAD_LEFT);
        if (!move_uploaded_file($chunk['tmp_name'], $dest)) {
            return Response::json(['success' => false, 'message' => 'Échec d’enregistrement du morceau.'], 500);
        }

        $meta = json_decode((string) file_get_contents($metaPath), true);
        if (!is_array($meta)) {
            $meta = [];
        }
        $received = is_array($meta['received'] ?? null) ? $meta['received'] : [];
        $received[$index] = true;
        $meta['received'] = $received;
        $meta['chunk_total'] = $total;
        file_put_contents($metaPath, json_encode($meta, JSON_UNESCAPED_UNICODE));

        return Response::json([
            'success' => true,
            'received' => count($received),
            'total' => $total,
        ]);
    }

    /** Assemble les morceaux et valide le fichier final. */
    public function uploadFinalize(Request $request, array $params = []): Response
    {
        $tenantId = Session::get('tenant_id');
        if (!$tenantId) {
            return Response::json(['success' => false, 'message' => 'Non autorisé.'], 403);
        }
        if (!Csrf::validate($this->requestToken($request))) {
            return Response::json(['success' => false, 'message' => 'Session expirée.'], 403);
        }

        $uploadId = preg_replace('/[^a-f0-9]/', '', (string) ($request->input('upload_id') ?? '')) ?? '';
        if ($uploadId === '') {
            return Response::json(['success' => false, 'message' => 'Upload inconnu.'], 422);
        }

        $dir = $this->stagingDir((int) $tenantId, $uploadId);
        $metaPath = $dir . '/meta.json';
        if (!is_file($metaPath)) {
            return Response::json(['success' => false, 'message' => 'Upload inconnu ou expiré.'], 404);
        }
        $meta = json_decode((string) file_get_contents($metaPath), true);
        if (!is_array($meta)) {
            return Response::json(['success' => false, 'message' => 'Métadonnées invalides.'], 500);
        }

        $total = (int) ($meta['chunk_total'] ?? $request->input('chunk_total') ?? 0);
        $received = is_array($meta['received'] ?? null) ? $meta['received'] : [];
        if ($total < 1 || count($received) !== $total) {
            return Response::json([
                'success' => false,
                'message' => 'Upload incomplet (' . count($received) . '/' . $total . ').',
            ], 422);
        }

        $ext = (string) ($meta['ext'] ?? 'zip');
        $assembled = $dir . '/assembled.' . $ext;
        $out = fopen($assembled, 'wb');
        if ($out === false) {
            return Response::json(['success' => false, 'message' => 'Impossible d’assembler le fichier.'], 500);
        }
        for ($i = 0; $i < $total; $i++) {
            $chunkPath = $dir . '/chunk_' . str_pad((string) $i, 6, '0', STR_PAD_LEFT);
            if (!is_file($chunkPath)) {
                fclose($out);
                @unlink($assembled);
                return Response::json(['success' => false, 'message' => 'Morceau manquant : #' . $i], 422);
            }
            $in = fopen($chunkPath, 'rb');
            if ($in === false) {
                fclose($out);
                @unlink($assembled);
                return Response::json(['success' => false, 'message' => 'Lecture impossible du morceau #' . $i], 500);
            }
            stream_copy_to_stream($in, $out);
            fclose($in);
            @unlink($chunkPath);
        }
        fclose($out);

        $size = (int) filesize($assembled);
        $expected = (int) ($meta['size'] ?? 0);
        if ($expected > 0 && abs($size - $expected) > 32) {
            @unlink($assembled);
            return Response::json(['success' => false, 'message' => 'Taille assemblée incohérente.'], 422);
        }

        $validationError = $this->validateModpackUpload($assembled, $size, (string) ($meta['filename'] ?? ('file.' . $ext)));
        if ($validationError !== null) {
            @unlink($assembled);
            return Response::json(['success' => false, 'message' => $validationError], 422);
        }

        $meta['assembled'] = 'assembled.' . $ext;
        $meta['assembled_size'] = $size;
        file_put_contents($metaPath, json_encode($meta, JSON_UNESCAPED_UNICODE));

        return Response::json([
            'success' => true,
            'upload_id' => $uploadId,
            'filename' => (string) ($meta['filename'] ?? ''),
            'size' => $size,
            'size_label' => $this->formatBytes($size),
        ]);
    }

    private function processImageUploads(int $modpackId, string $baseDir, int $startOrder): void
    {
        $files = $_FILES['images'] ?? [];
        if (empty($files['name']) || !is_array($files['name'])) {
            return;
        }
        if (!is_dir($baseDir)) {
            mkdir($baseDir, 0755, true);
        }
        $order = $startOrder;
        foreach ($files['name'] as $i => $name) {
            if (empty($name) || ($files['error'][$i] ?? 0) !== UPLOAD_ERR_OK) {
                continue;
            }
            $tmp = $files['tmp_name'][$i];
            $mime = $this->getMime($tmp);
            if (!in_array($mime, self::IMAGE_MIMES, true) || $files['size'][$i] > self::IMAGE_MAX_SIZE) {
                continue;
            }
            $ext = match ($mime) {
                'image/jpeg' => 'jpg',
                'image/png' => 'png',
                'image/webp' => 'webp',
                default => 'jpg',
            };
            $safeName = 'img_' . $modpackId . '_' . time() . '_' . $i . '.' . $ext;
            $relPath = 'modpacks/' . $modpackId . '/' . $safeName;
            $fullPath = $baseDir . DIRECTORY_SEPARATOR . $safeName;
            if (move_uploaded_file($tmp, $fullPath)) {
                $this->modpackRepository->addImage($modpackId, $relPath, $order);
                $order++;
            }
        }
    }

    /** @return array{file_path:string,size:int}|null */
    private function storeUploadedFile(string $tmpPath, string $originalName, int $size, int $modpackId, string $baseDir): ?array
    {
        $mime = $this->getMime($tmpPath);
        $ext = $this->extensionFromNameOrMime($originalName, $mime);
        $safeName = $modpackId . '_' . time() . '.' . $ext;
        $fullPath = $baseDir . DIRECTORY_SEPARATOR . $safeName;
        if (!is_uploaded_file($tmpPath)) {
            if (!@rename($tmpPath, $fullPath) && !@copy($tmpPath, $fullPath)) {
                return null;
            }
            @unlink($tmpPath);
        } elseif (!move_uploaded_file($tmpPath, $fullPath)) {
            return null;
        }

        return [
            'file_path' => 'modpacks/' . $modpackId . '/' . $safeName,
            'size' => $size,
        ];
    }

    /**
     * @param array{path:string,size:int,filename:string} $staged
     * @return array{file_path:string,size:int}|null
     */
    private function commitStagedFile(array $staged, int $modpackId, string $baseDir): ?array
    {
        return $this->storeUploadedFile($staged['path'], $staged['filename'], $staged['size'], $modpackId, $baseDir);
    }

    /** @return array{path:string,size:int,filename:string}|null */
    private function resolveStagedUpload(int $tenantId, string $uploadId): ?array
    {
        $uploadId = preg_replace('/[^a-f0-9]/', '', $uploadId) ?? '';
        if ($uploadId === '') {
            return null;
        }
        $dir = $this->stagingDir($tenantId, $uploadId);
        $metaPath = $dir . '/meta.json';
        if (!is_file($metaPath)) {
            return null;
        }
        $meta = json_decode((string) file_get_contents($metaPath), true);
        if (!is_array($meta) || empty($meta['assembled'])) {
            return null;
        }
        $path = $dir . '/' . basename((string) $meta['assembled']);
        if (!is_file($path)) {
            return null;
        }

        return [
            'path' => $path,
            'size' => (int) ($meta['assembled_size'] ?? filesize($path)),
            'filename' => (string) ($meta['filename'] ?? basename($path)),
        ];
    }

    private function cleanupStagingDir(int $tenantId, string $uploadId): void
    {
        $uploadId = preg_replace('/[^a-f0-9]/', '', $uploadId) ?? '';
        if ($uploadId === '') {
            return;
        }
        $dir = $this->stagingDir($tenantId, $uploadId);
        if (is_dir($dir)) {
            $this->removeDirRecursive($dir);
        }
    }

    private function stagingDir(int $tenantId, string $uploadId): string
    {
        return base_path('storage/uploads/modpacks/_staging/' . $tenantId . '/' . $uploadId);
    }

    private function validateModpackUpload(string $path, int $size, string $originalName): ?string
    {
        if ($size < 1) {
            return 'Fichier vide.';
        }
        if ($size > self::MODPACK_MAX_SIZE) {
            return 'Fichier trop volumineux (max 2 Go).';
        }
        $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        if (!in_array($ext, self::ALLOWED_EXTENSIONS, true)) {
            return 'Fichier modpack invalide (ZIP/RAR/7z, max 2 Go).';
        }
        $mime = $this->getMime($path);
        $allowedByExt = match ($ext) {
            'zip' => ['application/zip', 'application/x-zip-compressed', 'application/octet-stream'],
            'rar' => ['application/x-rar-compressed', 'application/vnd.rar', 'application/octet-stream'],
            '7z' => ['application/x-7z-compressed', 'application/octet-stream'],
            default => self::MODPACK_MIMES,
        };
        if (!in_array($mime, $allowedByExt, true) && !in_array($mime, self::MODPACK_MIMES, true)) {
            return 'Type MIME non reconnu pour ce modpack (' . $mime . ').';
        }

        return null;
    }

    /** @return string|null|false null = vide OK, false = invalide, string = URL ok */
    private function normalizeExternalUrl(string $raw): string|false|null
    {
        $raw = trim($raw);
        if ($raw === '') {
            return null;
        }
        if (!preg_match('#^https?://#i', $raw)) {
            return false;
        }

        return $raw;
    }

    private function requestToken(Request $request): string
    {
        $token = (string) ($request->input('_csrf_token') ?? $request->input('csrf_token') ?? '');
        if ($token !== '') {
            return $token;
        }
        $raw = (string) file_get_contents('php://input');
        if ($raw !== '') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                return (string) ($decoded['_csrf_token'] ?? $decoded['csrf_token'] ?? '');
            }
        }

        return '';
    }

    /** @return array<string, int|string> */
    private function uploadLimitsPayload(): array
    {
        return [
            'max_bytes' => self::MODPACK_MAX_SIZE,
            'chunk_bytes' => self::CHUNK_MAX_SIZE,
            'image_max_bytes' => self::IMAGE_MAX_SIZE,
            'max_label' => '2 Go',
            'extensions' => self::ALLOWED_EXTENSIONS,
        ];
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes >= 1073741824) {
            return number_format($bytes / 1073741824, 1, ',', ' ') . ' Go';
        }
        if ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 1, ',', ' ') . ' Mo';
        }
        if ($bytes >= 1024) {
            return number_format($bytes / 1024, 1, ',', ' ') . ' Ko';
        }

        return $bytes . ' o';
    }

    private function getMime(string $path): string
    {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = $finfo ? (finfo_file($finfo, $path) ?: '') : '';
        if ($finfo) {
            finfo_close($finfo);
        }

        return $mime;
    }

    private function extensionFromNameOrMime(string $name, string $mime): string
    {
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        if (in_array($ext, self::ALLOWED_EXTENSIONS, true)) {
            return $ext;
        }

        return match ($mime) {
            'application/zip', 'application/x-zip-compressed' => 'zip',
            'application/x-rar-compressed', 'application/vnd.rar' => 'rar',
            'application/x-7z-compressed' => '7z',
            default => 'zip',
        };
    }

    private function removeDirRecursive(string $dir): void
    {
        foreach (scandir($dir) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $path = $dir . DIRECTORY_SEPARATOR . $entry;
            if (is_dir($path)) {
                $this->removeDirRecursive($path);
            } else {
                @unlink($path);
            }
        }
        @rmdir($dir);
    }
}
