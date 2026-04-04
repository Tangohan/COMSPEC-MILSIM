<?php

declare(strict_types=1);

namespace App\Controllers\Courrier;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\Courrier\DocumentPresetRepository;
use App\Repositories\Courrier\DocumentTemplateRepository;

class CourrierTemplateController
{
    public function __construct(
        private DocumentTemplateRepository $templateRepository,
        private DocumentPresetRepository $presetRepository
    ) {
    }

    public function index(Request $request, array $params = []): Response
    {
        $tenantId = (int) (Session::get('tenant_id') ?? 0);
        if (!$tenantId) {
            return Response::redirect(url('login'));
        }

        $templates = $this->templateRepository->listForTenant($tenantId, false);
        return Response::view('layout.main', [
            'title' => 'Modèles — Bureau Courrier',
            'content' => 'courrier/templates/index',
            'courrier' => ['templates' => $templates],
        ]);
    }

    public function create(Request $request, array $params = []): Response
    {
        $tenantId = (int) (Session::get('tenant_id') ?? 0);
        $userId = (int) (Session::get('user_id') ?? 0);
        if (!$tenantId) {
            return Response::redirect(url('login'));
        }

        $presets = $this->presetRepository->listForTenant($tenantId);
        return Response::view('layout.main', [
            'title' => 'Nouveau modèle — Bureau Courrier',
            'content' => 'courrier/templates/edit',
            'courrier' => ['template' => null, 'presets' => $presets],
        ]);
    }

    public function store(Request $request, array $params = []): Response
    {
        $tenantId = (int) (Session::get('tenant_id') ?? 0);
        $userId = (int) (Session::get('user_id') ?? 0);
        if (!$tenantId) {
            return Response::redirect(url('login'));
        }

        $name = trim((string) ($request->input('name') ?? ''));
        $slug = $this->slugify($name ?: 'modele-' . time());
        $presetId = $request->input('preset_id') ? (int) $request->input('preset_id') : null;
        $bodyTemplate = $request->input('body_template') ?? '';

        $id = $this->templateRepository->create([
            'tenant_id' => $tenantId,
            'name' => $name ?: 'Sans titre',
            'slug' => $slug,
            'category' => $request->input('category'),
            'description' => $request->input('description'),
            'preset_id' => $presetId,
            'body_template' => $bodyTemplate,
            'created_by' => $userId,
            'updated_by' => $userId,
        ]);
        Session::flash('success', 'Modèle créé.');
        return Response::redirect(url('courrier/templates/' . $id . '/edit'));
    }

    public function edit(Request $request, array $params = []): Response
    {
        $id = (int) ($params['id'] ?? 0);
        $tenantId = (int) (Session::get('tenant_id') ?? 0);
        if (!$tenantId) {
            return Response::redirect(url('login'));
        }

        $template = $this->templateRepository->findById($id, $tenantId);
        if (!$template) {
            Session::flash('error', 'Modèle introuvable.');
            return Response::redirect(url('courrier/templates'));
        }

        $presets = $this->presetRepository->listForTenant($tenantId);
        return Response::view('layout.main', [
            'title' => ($template['name'] ?? 'Modèle') . ' — Bureau Courrier',
            'content' => 'courrier/templates/edit',
            'courrier' => ['template' => $template, 'presets' => $presets],
        ]);
    }

    public function update(Request $request, array $params = []): Response
    {
        $id = (int) ($params['id'] ?? 0);
        $tenantId = (int) (Session::get('tenant_id') ?? 0);
        $userId = (int) (Session::get('user_id') ?? 0);
        if (!$tenantId) {
            return Response::redirect(url('login'));
        }

        $template = $this->templateRepository->findById($id, $tenantId);
        if (!$template || ($template['is_system'] && $template['is_locked'])) {
            Session::flash('error', 'Modèle introuvable ou verrouillé.');
            return Response::redirect(url('courrier/templates'));
        }

        $this->templateRepository->update($id, [
            'name' => trim((string) ($request->input('name') ?? $template['name'])),
            'slug' => trim((string) ($request->input('slug') ?? $template['slug'])),
            'category' => $request->input('category'),
            'description' => $request->input('description'),
            'preset_id' => $request->input('preset_id') ? (int) $request->input('preset_id') : null,
            'body_template' => $request->input('body_template'),
            'updated_by' => $userId,
        ]);
        Session::flash('success', 'Modèle mis à jour.');
        return Response::redirect(url('courrier/templates/' . $id . '/edit'));
    }

    private function slugify(string $s): string
    {
        $s = preg_replace('/[^a-z0-9]+/i', '-', $s);
        return strtolower(trim($s, '-')) ?: 'modele';
    }
}
