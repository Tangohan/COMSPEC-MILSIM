<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\ModpackRepository;

class ModpackController
{
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
        if (count($modpacks) === 1) {
            return Response::redirect(url('modpacks/' . $modpacks[0]['slug']));
        }
        return Response::view('layout.main', [
            'content' => 'modpacks.index',
            'title' => 'Modpacks',
            'modpacks' => $modpacks,
        ]);
    }

    public function show(Request $request, array $params = []): Response
    {
        $tenantId = Session::get('tenant_id');
        if (!$tenantId) {
            return Response::redirect(url('login'));
        }
        $slug = $params['slug'] ?? '';
        $modpack = $this->modpackRepository->findBySlug((int) $tenantId, $slug);
        if (!$modpack) {
            return (new Response())->setStatusCode(404)->setBody('Modpack non trouvé.');
        }
        $modpack['download_url'] = url('modpacks/' . $modpack['id'] . '/download');
        $modpack['size_formatted'] = $this->formatSize((int) ($modpack['size'] ?? 0));
        return Response::view('layout.main', [
            'content' => 'modpacks.show',
            'title' => $modpack['name'] . ' — Modpack',
            'modpack' => $modpack,
        ]);
    }

    public function download(Request $request, array $params = []): Response
    {
        $tenantId = Session::get('tenant_id');
        if (!$tenantId) {
            return (new Response())->setStatusCode(403)->setBody('Non autorisé');
        }
        $id = (int) ($params['id'] ?? 0);
        $modpack = $this->modpackRepository->findById($id, (int) $tenantId);
        if (!$modpack || empty($modpack['file_path'])) {
            return (new Response())->setStatusCode(404)->setBody('Modpack ou fichier non trouvé.');
        }
        $fullPath = base_path('storage/uploads/' . $modpack['file_path']);
        if (!is_file($fullPath)) {
            return (new Response())->setStatusCode(404)->setBody('Fichier absent.');
        }
        $response = new Response();
        $response->header('Content-Type', 'application/octet-stream');
        $response->header('Content-Disposition', 'attachment; filename="' . basename($modpack['file_path']) . '"');
        $response->setBody((string) file_get_contents($fullPath));
        return $response;
    }

    public function image(Request $request, array $params = []): Response
    {
        $tenantId = Session::get('tenant_id');
        if (!$tenantId) {
            return (new Response())->setStatusCode(403)->setBody('Non autorisé');
        }
        $id = (int) ($params['id'] ?? 0);
        $image = $this->modpackRepository->getImageById($id);
        if (!$image || (int) $image['tenant_id'] !== (int) $tenantId) {
            return (new Response())->setStatusCode(404)->setBody('Image non trouvée.');
        }
        $fullPath = base_path('storage/uploads/' . $image['file_path']);
        if (!is_file($fullPath)) {
            return (new Response())->setStatusCode(404)->setBody('Fichier absent.');
        }
        $mime = mime_content_type($fullPath) ?: 'image/jpeg';
        $response = new Response();
        $response->header('Content-Type', $mime);
        $response->setBody((string) file_get_contents($fullPath));
        return $response;
    }

    private function formatSize(int $bytes): string
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
}
