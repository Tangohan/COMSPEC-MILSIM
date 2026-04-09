<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\CooperationCatalogRepository;
use App\Support\CooperationDictionary;

/**
 * Entrées supplémentaires du catalogue coopération pour la communauté active.
 */
final class CooperationCatalogWebController
{
    public function __construct(
        private CooperationCatalogRepository $catalog
    ) {}

    public function index(Request $request, array $params = []): Response
    {
        if (! $this->assertAccess()) {
            return Response::redirect(url('dashboard'));
        }
        $tid = (int) Session::get('tenant_id');

        return Response::view('layout.main', [
            'content' => 'back_office.cooperation.catalog_index',
            'title' => 'Types de coopération (votre communauté)',
            'cooperationCatalogRows' => $this->catalog->tableExists() ? $this->catalog->listByTenantId($tid) : [],
            'cooperationCatalogTableOk' => $this->catalog->tableExists(),
            'csrfToken' => Csrf::token(),
        ]);
    }

    public function create(Request $request, array $params = []): Response
    {
        if (! $this->assertAccess()) {
            return Response::redirect(url('dashboard'));
        }

        return Response::view('layout.main', [
            'content' => 'back_office.cooperation.catalog_form',
            'title' => 'Ajouter un type ou modèle local',
            'cooperationCatalogEntry' => null,
            'cooperationPriorityChoices' => CooperationDictionary::priorityChoices(),
            'formAction' => url('back-office/cooperation/catalog'),
            'formMethod' => 'post',
            'csrfToken' => Csrf::token(),
        ]);
    }

    public function store(Request $request, array $params = []): Response
    {
        if (! $this->assertAccess()) {
            return Response::redirect(url('dashboard'));
        }
        if (! Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Jeton de sécurité invalide.');

            return Response::redirect(url('back-office/cooperation/catalog/create'));
        }
        $tid = (int) Session::get('tenant_id');
        if (! $this->catalog->tableExists()) {
            Session::flash('error', 'Fonction indisponible : tables non installées.');

            return Response::redirect(url('back-office/cooperation/catalog'));
        }
        $slug = strtolower(trim((string) $request->input('slug', '')));
        if (! preg_match('/^[a-z0-9_]{1,64}$/', $slug)) {
            Session::flash('error', 'Utilisez un identifiant court sans espaces (lettres minuscules, chiffres ou tirets bas).');

            return Response::redirect(url('back-office/cooperation/catalog/create'));
        }
        if ($this->catalog->findByTenantAndSlug($tid, $slug)) {
            Session::flash('error', 'Ce nom interne est déjà utilisé pour votre communauté.');

            return Response::redirect(url('back-office/cooperation/catalog/create'));
        }
        $this->catalog->insert($tid, $this->normalizePayload($request, $slug, true));
        Session::flash('success', 'Entrée ajoutée. Elle apparaîtra dans les listes de typologie pour vos coopérations.');

        return Response::redirect(url('back-office/cooperation/catalog'));
    }

    public function edit(Request $request, array $params = []): Response
    {
        if (! $this->assertAccess()) {
            return Response::redirect(url('dashboard'));
        }
        $tid = (int) Session::get('tenant_id');
        $id = (int) ($params['id'] ?? 0);
        $row = $id > 0 ? $this->catalog->findById($id) : null;
        if (! $row || (int) ($row['tenant_id'] ?? 0) !== $tid) {
            Session::flash('error', 'Entrée introuvable.');

            return Response::redirect(url('back-office/cooperation/catalog'));
        }

        return Response::view('layout.main', [
            'content' => 'back_office.cooperation.catalog_form',
            'title' => 'Modifier — ' . (string) ($row['label'] ?? ''),
            'cooperationCatalogEntry' => $row,
            'cooperationPriorityChoices' => CooperationDictionary::priorityChoices(),
            'formAction' => url('back-office/cooperation/catalog/' . $id . '/update'),
            'formMethod' => 'post',
            'csrfToken' => Csrf::token(),
        ]);
    }

    public function update(Request $request, array $params = []): Response
    {
        if (! $this->assertAccess()) {
            return Response::redirect(url('dashboard'));
        }
        if (! Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Jeton de sécurité invalide.');

            return Response::redirect(url('back-office/cooperation/catalog'));
        }
        $tid = (int) Session::get('tenant_id');
        $id = (int) ($params['id'] ?? 0);
        $row = $id > 0 ? $this->catalog->findById($id) : null;
        if (! $row || (int) ($row['tenant_id'] ?? 0) !== $tid) {
            Session::flash('error', 'Entrée introuvable.');

            return Response::redirect(url('back-office/cooperation/catalog'));
        }
        $slug = (string) ($row['slug'] ?? '');
        $this->catalog->update($id, $this->normalizePayload($request, $slug, false));
        Session::flash('success', 'Modifications enregistrées.');

        return Response::redirect(url('back-office/cooperation/catalog'));
    }

    public function delete(Request $request, array $params = []): Response
    {
        if (! $this->assertAccess()) {
            return Response::redirect(url('dashboard'));
        }
        if (! Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Jeton de sécurité invalide.');

            return Response::redirect(url('back-office/cooperation/catalog'));
        }
        $tid = (int) Session::get('tenant_id');
        $id = (int) ($params['id'] ?? 0);
        $row = $id > 0 ? $this->catalog->findById($id) : null;
        if (! $row || (int) ($row['tenant_id'] ?? 0) !== $tid) {
            Session::flash('error', 'Entrée introuvable.');

            return Response::redirect(url('back-office/cooperation/catalog'));
        }
        $this->catalog->delete($id, $tid);
        Session::flash('success', 'Entrée retirée du catalogue local.');

        return Response::redirect(url('back-office/cooperation/catalog'));
    }

    private function assertAccess(): bool
    {
        if (! Session::get('user_id')) {
            Session::flash('error', 'Authentification requise.');

            return false;
        }
        if (! function_exists('can') || ! can('cooperation.catalog.manage')) {
            Session::flash('error', 'Action réservée aux personnes habilitées à gérer le catalogue coopération.');

            return false;
        }

        return true;
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
