<?php

declare(strict_types=1);

namespace App\Controllers\Admin\Organization;

use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\PersonnelPassRepository;
use App\Repositories\TrainingCourseRepository;
use App\Services\Personnel\Pass\PassConditionEngine;
use App\Services\Personnel\Pass\PassService;
use App\Core\Database;
use PDO;

final class PersonnelPassAdminController
{
    public function __construct(
        private PersonnelPassRepository $passes,
        private PassService $passService,
        private PassConditionEngine $engine,
        private TrainingCourseRepository $courses,
    ) {}

    public function index(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        if ($tenantId < 1) {
            return Response::redirect(url('login'));
        }

        return Response::view('layout.main', [
            'title' => 'PASS RH',
            'content' => 'admin.organization.passes_index',
            'passList' => $this->passes->schemaReady() ? $this->passes->listPasses($tenantId) : [],
            'passSchemaReady' => $this->passes->schemaReady(),
        ]);
    }

    public function create(Request $request, array $params = []): Response
    {
        return $this->form(null);
    }

    public function edit(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        $id = (int) ($params['id'] ?? 0);
        $pass = $this->passes->find($tenantId, $id);
        if ($pass === null) {
            Session::flash('error', 'PASS introuvable.');

            return Response::redirect(url('back-office/organisation/passes'));
        }

        return $this->form($pass);
    }

    public function store(Request $request, array $params = []): Response
    {
        $redirect = url('back-office/organisation/passes');
        $tenantId = (int) Session::get('tenant_id');
        if ($tenantId < 1) {
            return Response::redirect(url('login'));
        }
        if (!Csrf::validate((string) $request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée. Merci de réessayer.');

            return Response::redirect($redirect);
        }
        if (!$this->passes->schemaReady()) {
            Session::flash('error', 'Schéma PASS non disponible. Appliquez la mise à jour de la base.');

            return Response::redirect($redirect);
        }
        $label = trim((string) $request->input('label', ''));
        if ($label === '') {
            Session::flash('error', 'Indiquez le nom du PASS.');

            return Response::redirect(url('back-office/organisation/passes/create'));
        }
        $data = $this->collectPassMeta($request);
        $id = $this->passes->create($tenantId, $data, (int) Session::get('user_id') ?: null);
        $this->passes->replaceConditions($tenantId, $id, $this->passService->sanitizeConditions($this->collectConditions($request)));
        Session::flash('success', 'PASS créé.');

        return Response::redirect(url('back-office/organisation/passes/' . $id . '/edit'));
    }

    public function update(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        $id = (int) ($params['id'] ?? 0);
        $redirect = url('back-office/organisation/passes/' . $id . '/edit');
        if ($tenantId < 1) {
            return Response::redirect(url('login'));
        }
        if (!Csrf::validate((string) $request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée. Merci de réessayer.');

            return Response::redirect($redirect);
        }
        $pass = $this->passes->find($tenantId, $id);
        if ($pass === null) {
            Session::flash('error', 'PASS introuvable.');

            return Response::redirect(url('back-office/organisation/passes'));
        }
        $label = trim((string) $request->input('label', ''));
        if ($label === '') {
            Session::flash('error', 'Indiquez le nom du PASS.');

            return Response::redirect($redirect);
        }
        $this->passes->update($tenantId, $id, $this->collectPassMeta($request));
        $this->passes->replaceConditions($tenantId, $id, $this->passService->sanitizeConditions($this->collectConditions($request)));
        Session::flash('success', 'PASS enregistré.');

        return Response::redirect($redirect);
    }

    public function destroy(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        $id = (int) ($params['id'] ?? 0);
        if (!Csrf::validate((string) $request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée. Merci de réessayer.');

            return Response::redirect(url('back-office/organisation/passes'));
        }
        if ($this->passes->find($tenantId, $id) !== null) {
            $this->passes->delete($tenantId, $id);
            Session::flash('success', 'PASS supprimé.');
        }

        return Response::redirect(url('back-office/organisation/passes'));
    }

    /** @param array<string, mixed>|null $pass */
    private function form(?array $pass): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        if ($tenantId < 1) {
            return Response::redirect(url('login'));
        }
        $conditions = $pass ? $this->passes->listConditions($tenantId, (int) $pass['id']) : [];
        $previews = [];
        foreach ($conditions as $c) {
            $previews[] = PassConditionEngine::previewSentence($c);
        }

        return Response::view('layout.main', [
            'title' => $pass ? 'Modifier un PASS' : 'Nouveau PASS',
            'content' => 'admin.organization.passes_form',
            'pass' => $pass,
            'passConditions' => $conditions,
            'passPreviews' => $previews,
            'passCatalog' => PassConditionEngine::catalog(),
            'passAvisKinds' => PassConditionEngine::avisKinds(),
            'passBilanKinds' => PassConditionEngine::bilanKinds(),
            'passCourses' => $this->courses->listForTenant($tenantId, null),
            'passQualifications' => $this->qualificationChoices($tenantId),
            'passGrades' => $this->gradeChoices($tenantId),
            'passHourCategories' => \App\Services\Personnel\RoleplayGameSessionSettings::forTenant($tenantId)['hour_categories'] ?? [],
            'passSchemaReady' => $this->passes->schemaReady(),
        ]);
    }

    /** @return array<string, mixed> */
    private function collectPassMeta(Request $request): array
    {
        return [
            'code' => trim((string) $request->input('code', '')),
            'label' => trim((string) $request->input('label', '')),
            'description' => trim((string) $request->input('description', '')),
            'logic' => (string) $request->input('logic', 'all') === 'any' ? 'any' : 'all',
            'use_for_post' => $request->input('use_for_post') === '1',
            'use_for_advancement' => $request->input('use_for_advancement') === '1',
            'use_for_notation' => $request->input('use_for_notation') === '1',
            'is_active' => (string) $request->input('is_active', '1') === '1',
        ];
    }

    /** @return list<array<string, mixed>> */
    private function collectConditions(Request $request): array
    {
        $types = $request->input('condition_type', []);
        if (!is_array($types)) {
            return [];
        }
        $out = [];
        $n = count($types);
        for ($i = 0; $i < $n; $i++) {
            $type = trim((string) ($types[$i] ?? ''));
            if ($type === '') {
                continue;
            }
            $out[] = [
                'condition_type' => $type,
                'qualification_id' => $this->inputAt($request, 'qualification_id', $i),
                'training_module_id' => $this->inputAt($request, 'training_module_id', $i),
                'grade_id' => $this->inputAt($request, 'grade_id', $i),
                'threshold_value' => $this->inputAt($request, 'threshold_value', $i),
                'window_days' => $this->inputAt($request, 'window_days', $i),
                'require_validity' => (string) $this->inputAt($request, 'require_validity', $i) === '1',
                'hour_category' => $this->inputAt($request, 'hour_category', $i),
                'session_kind' => $this->inputAt($request, 'session_kind', $i),
                'avis_kind' => $this->inputAt($request, 'avis_kind', $i),
                'bilan_kind' => $this->inputAt($request, 'bilan_kind', $i),
            ];
        }

        return $out;
    }

    private function inputAt(Request $request, string $key, int $i): mixed
    {
        $all = $request->input($key, []);
        if (!is_array($all)) {
            return null;
        }

        return $all[$i] ?? null;
    }

    /** @return list<array{id: int, label: string}> */
    private function qualificationChoices(int $tenantId): array
    {
        try {
            $pdo = Database::getPdo();
            $st = $pdo->prepare(
                'SELECT id, name, code FROM personnel_qualification_definitions
                 WHERE tenant_id = ? AND archived_at IS NULL
                 ORDER BY name ASC LIMIT 400'
            );
            $st->execute([$tenantId]);
            $out = [];
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
                $out[] = [
                    'id' => (int) $row['id'],
                    'label' => trim((string) ($row['name'] ?? '')) . ' (' . trim((string) ($row['code'] ?? '')) . ')',
                ];
            }

            return $out;
        } catch (\Throwable) {
            return [];
        }
    }

    /** @return list<array{id: int, label: string}> */
    private function gradeChoices(int $tenantId): array
    {
        try {
            $pdo = Database::getPdo();
            $st = $pdo->prepare(
                'SELECT id, label, code, rank_order FROM grade_definitions
                 WHERE tenant_id = ? AND archived_at IS NULL
                 ORDER BY rank_order ASC, label ASC LIMIT 400'
            );
            $st->execute([$tenantId]);
            $out = [];
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
                $out[] = [
                    'id' => (int) $row['id'],
                    'label' => trim((string) ($row['label'] ?? '')) . ' [' . trim((string) ($row['code'] ?? '')) . ']',
                ];
            }

            return $out;
        } catch (\Throwable) {
            return [];
        }
    }
}
