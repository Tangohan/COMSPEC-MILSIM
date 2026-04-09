<?php

declare(strict_types=1);

namespace App\Controllers\Admin\System;

use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\CooperationCatalogRepository;
use App\Support\CooperationDictionary;

/**
 * Catalogue de référence des types de coopération (tenant_id = 0).
 */
final class SystemCooperationCatalogController
{
    public function __construct(
        private CooperationCatalogRepository $catalog
    ) {}

    public function index(Request $request, array $params = []): Response
    {
        if (!$this->catalog->tableExists()) {
            Session::flash('error', 'Les tables du catalogue coopération ne sont pas encore installées (migrations).');

            return Response::redirect(url('admin'));
        }

        return Response::view('layout.main', [
            'content' => 'admin.system.cooperation_catalog_index',
            'title' => 'Types de coopération (référence site)',
            'cooperationCatalogRows' => $this->catalog->listByTenantId(0),
        ]);
    }

    public function create(Request $request, array $params = []): Response
    {
        if (!$this->catalog->tableExists()) {
            Session::flash('error', 'Tables catalogue absentes.');

            return Response::redirect(url('admin'));
        }

        return Response::view('layout.main', [
            'content' => 'admin.system.cooperation_catalog_form',
            'title' => 'Ajouter un type (référence site)',
            'cooperationCatalogEntry' => null,
            'cooperationPriorityChoices' => CooperationDictionary::priorityChoices(),
            'formAction' => url('admin/system/cooperation/catalog'),
            'formMethod' => 'post',
        ]);
    }

    public function store(Request $request, array $params = []): Response
    {
        if (! Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée.');

            return Response::redirect(url('admin/system/cooperation/catalog'));
        }
        if (!$this->catalog->tableExists()) {
            return Response::redirect(url('admin'));
        }
        $slug = strtolower(trim((string) $request->input('slug', '')));
        if (! preg_match('/^[a-z0-9_]{1,64}$/', $slug)) {
            Session::flash('error', 'L’identifiant interne doit contenir uniquement des lettres minuscules, chiffres ou tirets bas (sans espace).');

            return Response::redirect(url('admin/system/cooperation/catalog/create'));
        }
        if ($this->catalog->findByTenantAndSlug(0, $slug)) {
            Session::flash('error', 'Un type porte déjà cet identifiant interne.');

            return Response::redirect(url('admin/system/cooperation/catalog/create'));
        }
        $this->catalog->insert(0, $this->normalizePayload($request, $slug, true));
        Session::flash('success', 'Type ajouté à la référence site.');

        return Response::redirect(url('admin/system/cooperation/catalog'));
    }

    public function edit(Request $request, array $params = []): Response
    {
        $id = (int) ($params['id'] ?? 0);
        $row = $id > 0 ? $this->catalog->findById($id) : null;
        if (! $row || (int) ($row['tenant_id'] ?? -1) !== 0) {
            Session::flash('error', 'Entrée introuvable.');

            return Response::redirect(url('admin/system/cooperation/catalog'));
        }

        return Response::view('layout.main', [
            'content' => 'admin.system.cooperation_catalog_form',
            'title' => 'Modifier le type — ' . (string) ($row['label'] ?? ''),
            'cooperationCatalogEntry' => $row,
            'cooperationPriorityChoices' => CooperationDictionary::priorityChoices(),
            'formAction' => url('admin/system/cooperation/catalog/' . $id . '/update'),
            'formMethod' => 'post',
        ]);
    }

    public function update(Request $request, array $params = []): Response
    {
        if (! Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée.');

            return Response::redirect(url('admin/system/cooperation/catalog'));
        }
        $id = (int) ($params['id'] ?? 0);
        $row = $id > 0 ? $this->catalog->findById($id) : null;
        if (! $row || (int) ($row['tenant_id'] ?? -1) !== 0) {
            Session::flash('error', 'Entrée introuvable.');

            return Response::redirect(url('admin/system/cooperation/catalog'));
        }
        $slug = (string) ($row['slug'] ?? '');
        $this->catalog->update($id, $this->normalizePayload($request, $slug, false));
        Session::flash('success', 'Type mis à jour.');

        return Response::redirect(url('admin/system/cooperation/catalog'));
    }

    public function delete(Request $request, array $params = []): Response
    {
        if (! Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée.');

            return Response::redirect(url('admin/system/cooperation/catalog'));
        }
        $id = (int) ($params['id'] ?? 0);
        $row = $id > 0 ? $this->catalog->findById($id) : null;
        if (! $row || (int) ($row['tenant_id'] ?? -1) !== 0) {
            Session::flash('error', 'Entrée introuvable.');

            return Response::redirect(url('admin/system/cooperation/catalog'));
        }
        $slug = (string) ($row['slug'] ?? '');
        if (array_key_exists($slug, CooperationDictionary::typologyChoices())) {
            Session::flash('error', 'Les types fournis par défaut avec le portail ne peuvent pas être supprimés. Vous pouvez les désactiver à la place.');

            return Response::redirect(url('admin/system/cooperation/catalog'));
        }
        $this->catalog->delete($id, 0);
        Session::flash('success', 'Type retiré de la référence site.');

        return Response::redirect(url('admin/system/cooperation/catalog'));
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizePayload(Request $request, string $slug, bool $isCreate): array
    {
        $label = trim((string) $request->input('label', ''));
        if ($label === '') {
            $label = $slug;
        }
        $desc = trim((string) $request->input('description', ''));
        $prio = trim((string) $request->input('default_priority', ''));
        if ($prio !== '' && ! array_key_exists($prio, CooperationDictionary::priorityChoices())) {
            $prio = '';
        }
        $checkLines = [];
        foreach (preg_split('/\R/', (string) $request->input('checklist_lines', '')) ?: [] as $ln) {
            $ln = trim((string) $ln);
            if ($ln !== '') {
                $checkLines[] = $ln;
            }
        }
        $out = [
            'label' => mb_substr($label, 0, 255),
            'description' => $desc !== '' ? $desc : null,
            'default_priority' => $prio !== '' ? $prio : null,
            'sort_order' => max(0, min(99999, (int) $request->input('sort_order', 0))),
            'is_active' => $request->input('is_active') ? 1 : 0,
            'checklist_json' => $checkLines !== [] ? json_encode($checkLines, JSON_UNESCAPED_UNICODE) : null,
        ];
        if ($isCreate) {
            $out['slug'] = $slug;
        }

        return $out;
    }
}
