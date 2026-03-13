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
    private const MODPACK_MIMES = [
        'application/zip',
        'application/x-zip-compressed',
        'application/x-rar-compressed',
        'application/vnd.rar',
        'application/x-7z-compressed',
    ];
    private const IMAGE_MIMES = ['image/jpeg', 'image/png', 'image/webp'];

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
        $file = $_FILES['modpack_file'] ?? null;
        if ($file && ($file['error'] ?? 0) === UPLOAD_ERR_OK) {
            $mime = $this->getMime($file['tmp_name']);
            if (!in_array($mime, self::MODPACK_MIMES, true) || $file['size'] > self::MODPACK_MAX_SIZE) {
                Session::set('error', 'Fichier modpack invalide (ZIP/RAR/7z, max 2 Go).');
                return Response::redirect(url('admin/modpacks/create'));
            }
        }
        $now = date('Y-m-d H:i:s');
        $userId = Session::get('user_id');
        $data = [
            'tenant_id' => (int) $tenantId,
            'name' => $name,
            'slug' => $effectiveSlug,
            'url' => null,
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
        if ($file && ($file['error'] ?? 0) === UPLOAD_ERR_OK) {
            $mime = $this->getMime($file['tmp_name']);
            $ext = $this->extensionFromMime($mime);
            $safeName = $id . '_' . time() . '.' . $ext;
            $fullPath = $baseDir . DIRECTORY_SEPARATOR . $safeName;
            if (move_uploaded_file($file['tmp_name'], $fullPath)) {
                $this->modpackRepository->update($id, (int) $tenantId, [
                    'file_path' => 'modpacks/' . $id . '/' . $safeName,
                    'size' => (int) $file['size'],
                    'updated_at' => $now,
                ]);
            }
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
        $now = date('Y-m-d H:i:s');
        $data = [
            'name' => $name,
            'slug' => $effectiveSlug,
            'version' => trim((string) $request->input('version')) ?: null,
            'description' => trim((string) $request->input('description')) ?: null,
            'updated_at' => $now,
        ];
        $baseDir = base_path('storage/uploads/modpacks/' . $id);
        $file = $_FILES['modpack_file'] ?? null;
        if ($file && ($file['error'] ?? 0) === UPLOAD_ERR_OK) {
            $mime = $this->getMime($file['tmp_name']);
            if (in_array($mime, self::MODPACK_MIMES, true) && $file['size'] <= self::MODPACK_MAX_SIZE) {
                if (!is_dir($baseDir)) {
                    mkdir($baseDir, 0755, true);
                }
                $ext = $this->extensionFromMime($mime);
                $safeName = $id . '_' . time() . '.' . $ext;
                $fullPath = $baseDir . DIRECTORY_SEPARATOR . $safeName;
                if (move_uploaded_file($file['tmp_name'], $fullPath)) {
                    $data['file_path'] = 'modpacks/' . $id . '/' . $safeName;
                    $data['size'] = (int) $file['size'];
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

    private function processImageUploads(int $modpackId, string $baseDir, int $startOrder): void
    {
        $files = $_FILES['images'] ?? [];
        if (empty($files['name']) || !is_array($files['name'])) {
            return;
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

    private function getMime(string $path): string
    {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $path) ?: '';
        finfo_close($finfo);
        return $mime;
    }

    private function extensionFromMime(string $mime): string
    {
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
