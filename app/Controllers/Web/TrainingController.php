<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\TrainingRepository;

class TrainingController
{
    public function __construct(
        private TrainingRepository $trainingRepository
    ) {}

    public function index(Request $request, array $params = []): Response
    {
        $tenantId = Session::get('tenant_id');
        if (!$tenantId) {
            return Response::redirect(url('login'));
        }
        $modules = $this->trainingRepository->listPublishedForTenant((int) $tenantId);
        return Response::view('layout.main', [
            'content' => 'training.index',
            'title' => 'Formations',
            'modules' => $modules,
        ]);
    }

    public function show(Request $request, array $params = []): Response
    {
        $tenantId = Session::get('tenant_id');
        if (!$tenantId) {
            return Response::redirect(url('login'));
        }
        $slug = $params['slug'] ?? '';
        $module = $this->trainingRepository->findBySlug($slug, (int) $tenantId);
        if (!$module) {
            return (new Response())->setStatusCode(404)->setBody('Module non trouvé.');
        }
        return Response::view('layout.main', [
            'content' => 'training.show',
            'title' => $module['title'],
            'module' => $module,
        ]);
    }
}
