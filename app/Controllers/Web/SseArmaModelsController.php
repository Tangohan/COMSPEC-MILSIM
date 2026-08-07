<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\SseArmaModelRepository;
use App\Repositories\SseCaseRepository;
use App\Services\Sse\SseAccessCodeService;
use App\Services\Sse\SseArmaModelService;

/**
 * Portail SSE — Atelier de préparation (modèles pour missions Arma).
 */
final class SseArmaModelsController
{
    public function __construct(
        private ?SseAccessCodeService $access = null,
        private ?SseCaseRepository $cases = null,
        private ?SseArmaModelService $models = null,
    ) {
        $this->access ??= new SseAccessCodeService();
        $this->cases ??= new SseCaseRepository();
        $this->models ??= new SseArmaModelService();
    }

    public function hub(Request $request, array $params = []): Response
    {
        $tenantId = $this->tenantId();
        $all = $this->models->repository()->listForTenant($tenantId, ['limit' => 200]);
        $published = array_values(array_filter(
            $all,
            static fn (array $m): bool => ($m['status'] ?? '') === 'published'
        ));

        return $this->portalView('atak.sse.dev.hub', [
            'title' => 'Atelier de préparation',
            'recentModels' => array_slice($all, 0, 8),
            'modelsCount' => count($all),
            'publishedCount' => count($published),
            'templates' => $this->models->builtinTemplates(),
            'activeNav' => 'dev',
            'devSubnav' => 'hub',
        ]);
    }

    public function modelsIndex(Request $request, array $params = []): Response
    {
        $filters = [
            'status' => (string) $request->query('status', ''),
            'profile' => (string) $request->query('profile', ''),
            'q' => (string) $request->query('q', ''),
        ];

        return $this->portalView('atak.sse.dev.models_index', [
            'title' => 'Modèles de mission',
            'models' => $this->models->repository()->listForTenant($this->tenantId(), $filters),
            'filters' => $filters,
            'statuses' => SseArmaModelRepository::STATUS_LABELS,
            'profiles' => SseArmaModelRepository::PROFILE_LABELS,
            'activeNav' => 'dev',
            'devSubnav' => 'modeles',
        ]);
    }

    public function modelCreateForm(Request $request, array $params = []): Response
    {
        if (!$this->canManage()) {
            Session::flash('error', 'Action non autorisée.');

            return Response::redirect(url('atak/sse/dev/modeles'));
        }

        $templateKey = trim((string) $request->query('modele', ''));
        $form = $this->models->mergeTemplateDefaults([]);
        $templateLabel = '';
        foreach ($this->models->builtinTemplates() as $tpl) {
            if ($tpl['key'] === $templateKey) {
                $form = $this->models->mergeTemplateDefaults($tpl['defaults']);
                $templateLabel = $tpl['label'];
                break;
            }
        }

        return $this->portalView('atak.sse.dev.model_form', [
            'title' => 'Nouveau modèle de mission',
            'form' => $form,
            'model' => null,
            'templateLabel' => $templateLabel,
            'templates' => $this->models->builtinTemplates(),
            'profiles' => SseArmaModelRepository::PROFILE_LABELS,
            'complexities' => SseArmaModelRepository::COMPLEXITY_LABELS,
            'regions' => SseArmaModelRepository::REGION_LABELS,
            'themes' => SseArmaModelRepository::THEME_LABELS,
            'statuses' => SseArmaModelRepository::STATUS_LABELS,
            'activeNav' => 'dev',
            'devSubnav' => 'modeles',
        ]);
    }

    public function modelStore(Request $request, array $params = []): Response
    {
        if (!$this->canManage() || !Csrf::validate((string) $request->input('_csrf_token', ''))) {
            Session::flash('error', 'Action non autorisée.');

            return Response::redirect(url('atak/sse/dev/modeles'));
        }

        $normalized = $this->models->normalizeFromForm($request->all());
        if (!$normalized['ok']) {
            Session::flash('error', implode(' ', $normalized['errors']));

            return Response::redirect(url('atak/sse/dev/modeles/nouveau'));
        }

        $data = $normalized['data'];
        $data['source'] = 'WEB';
        $data['created_by'] = $this->userId();
        $data['updated_by'] = $this->userId();

        $existing = $this->models->repository()->findByPublicId((string) $data['public_id'], $this->tenantId());
        if ($existing !== null) {
            Session::flash('error', 'Un modèle avec cet identifiant existe déjà. Modifiez le nom ou l’identifiant.');

            return Response::redirect(url('atak/sse/dev/modeles/nouveau'));
        }

        $id = $this->models->repository()->create($this->tenantId(), $data);
        Session::flash('success', 'Modèle enregistré.');

        return Response::redirect(url('atak/sse/dev/modeles/' . $id));
    }

    public function modelShow(Request $request, array $params = []): Response
    {
        $id = (int) ($params['id'] ?? 0);
        $model = $this->models->repository()->findForTenant($id, $this->tenantId());
        if ($model === null) {
            Session::flash('error', 'Modèle introuvable.');

            return Response::redirect(url('atak/sse/dev/modeles'));
        }

        return $this->portalView('atak.sse.dev.model_show', [
            'title' => (string) ($model['name'] ?? 'Modèle'),
            'model' => $model,
            'armaModel' => $this->models->toArmaModel($model),
            'sqfSnippet' => $this->models->toSqfImportBlock($model),
            'activeNav' => 'dev',
            'devSubnav' => 'modeles',
        ]);
    }

    public function modelEditForm(Request $request, array $params = []): Response
    {
        if (!$this->canManage()) {
            Session::flash('error', 'Action non autorisée.');

            return Response::redirect(url('atak/sse/dev/modeles'));
        }

        $id = (int) ($params['id'] ?? 0);
        $model = $this->models->repository()->findForTenant($id, $this->tenantId());
        if ($model === null) {
            Session::flash('error', 'Modèle introuvable.');

            return Response::redirect(url('atak/sse/dev/modeles'));
        }

        return $this->portalView('atak.sse.dev.model_form', [
            'title' => 'Modifier le modèle',
            'form' => $this->models->rowToFormValues($model),
            'model' => $model,
            'templateLabel' => '',
            'templates' => $this->models->builtinTemplates(),
            'profiles' => SseArmaModelRepository::PROFILE_LABELS,
            'complexities' => SseArmaModelRepository::COMPLEXITY_LABELS,
            'regions' => SseArmaModelRepository::REGION_LABELS,
            'themes' => SseArmaModelRepository::THEME_LABELS,
            'statuses' => SseArmaModelRepository::STATUS_LABELS,
            'activeNav' => 'dev',
            'devSubnav' => 'modeles',
        ]);
    }

    public function modelUpdate(Request $request, array $params = []): Response
    {
        if (!$this->canManage() || !Csrf::validate((string) $request->input('_csrf_token', ''))) {
            Session::flash('error', 'Action non autorisée.');

            return Response::redirect(url('atak/sse/dev/modeles'));
        }

        $id = (int) ($params['id'] ?? 0);
        $model = $this->models->repository()->findForTenant($id, $this->tenantId());
        if ($model === null) {
            Session::flash('error', 'Modèle introuvable.');

            return Response::redirect(url('atak/sse/dev/modeles'));
        }

        $input = $request->all();
        $input['public_id'] = (string) ($model['public_id'] ?? '');
        $normalized = $this->models->normalizeFromForm($input);
        if (!$normalized['ok']) {
            Session::flash('error', implode(' ', $normalized['errors']));

            return Response::redirect(url('atak/sse/dev/modeles/' . $id . '/modifier'));
        }

        $data = $normalized['data'];
        $data['updated_by'] = $this->userId();
        $this->models->repository()->update($id, $this->tenantId(), $data);
        Session::flash('success', 'Modèle mis à jour.');

        return Response::redirect(url('atak/sse/dev/modeles/' . $id));
    }

    public function modelDelete(Request $request, array $params = []): Response
    {
        if (!$this->canManage() || !Csrf::validate((string) $request->input('_csrf_token', ''))) {
            Session::flash('error', 'Action non autorisée.');

            return Response::redirect(url('atak/sse/dev/modeles'));
        }

        $id = (int) ($params['id'] ?? 0);
        $model = $this->models->repository()->findForTenant($id, $this->tenantId());
        if ($model === null) {
            Session::flash('error', 'Modèle introuvable.');

            return Response::redirect(url('atak/sse/dev/modeles'));
        }

        $this->models->repository()->softDelete($id, $this->tenantId());
        Session::flash('success', 'Modèle archivé.');

        return Response::redirect(url('atak/sse/dev/modeles'));
    }

    public function modelExport(Request $request, array $params = []): Response
    {
        $id = (int) ($params['id'] ?? 0);
        $format = strtolower(trim((string) $request->query('format', 'json')));
        $model = $this->models->repository()->findForTenant($id, $this->tenantId());
        if ($model === null) {
            Session::flash('error', 'Modèle introuvable.');

            return Response::redirect(url('atak/sse/dev/modeles'));
        }

        $slug = preg_replace('/[^a-z0-9_\-]+/i', '_', (string) ($model['public_id'] ?? 'modele')) ?: 'modele';

        if ($format === 'sqf') {
            $body = $this->models->toSqfImportBlock($model);
            $response = new Response();

            return $response
                ->header('Content-Type', 'text/plain; charset=utf-8')
                ->header('Content-Disposition', 'attachment; filename="sse_modele_' . $slug . '.sqf"')
                ->setBody($body);
        }

        $payload = [
            'format' => 'comspec_sse_model',
            'formatVersion' => 1,
            'exportedAt' => gmdate('c'),
            'model' => $this->models->toArmaModel($model),
            'serialized' => $this->models->toArmaSerializedPairs($model),
        ];
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) ?: '{}';
        $response = new Response();

        return $response
            ->header('Content-Type', 'application/json; charset=utf-8')
            ->header('Content-Disposition', 'attachment; filename="sse_modele_' . $slug . '.json"')
            ->setBody($json);
    }

    private function portalView(string $view, array $data): Response
    {
        $data['isGuest'] = $this->access->isGuest();
        $data['clearanceUntil'] = (int) Session::get(SseAccessCodeService::SESSION_UNTIL, 0);
        $data['guestLabel'] = (string) Session::get('sse_guest_label', '');
        $data['sseTheme'] = sse_ui_theme();
        $data['sseThemeOptions'] = sse_ui_theme_options();
        $data['canGrant'] = $data['canGrant'] ?? $this->canGrant();
        $data['canManage'] = $data['canManage'] ?? $this->canManage();

        $tenantId = $this->tenantId();
        if ($tenantId > 0 && $this->access->hasActiveClearance()) {
            $scope = $this->access->caseScope();
            $allForRail = $this->cases->listForTenant($tenantId, $scope);
            $data['sseFolderTree'] = $this->cases->buildTree($allForRail);
            $data['sseFolderParents'] = array_values(array_filter(
                $allForRail,
                static fn (array $c): bool => !empty($c['is_folder'])
            ));
            if (!isset($data['indexCounts'])) {
                $indexCounts = ['total' => count($allForRail), 'active' => 0, 'archive' => 0];
                foreach ($allForRail as $case) {
                    $status = (string) ($case['status'] ?? '');
                    if (in_array($status, ['ouvert', 'en_cours'], true)) {
                        $indexCounts['active']++;
                    }
                    if ($status === 'archive') {
                        $indexCounts['archive']++;
                    }
                }
                $data['indexCounts'] = $indexCounts;
            }
            $data['sseRecentCases'] = Session::get('sse_recent_cases', []);
            if (!is_array($data['sseRecentCases'])) {
                $data['sseRecentCases'] = [];
            }
        } else {
            $data['sseFolderTree'] = $data['sseFolderTree'] ?? [];
            $data['sseFolderParents'] = $data['sseFolderParents'] ?? [];
            $data['sseRecentCases'] = $data['sseRecentCases'] ?? [];
            $data['indexCounts'] = $data['indexCounts'] ?? ['total' => 0, 'active' => 0, 'archive' => 0];
        }

        return Response::view($view, $data);
    }

    private function tenantId(): int
    {
        $tid = $this->access->tenantId();
        if ($tid > 0) {
            return $tid;
        }

        return (int) Session::get('tenant_id');
    }

    private function userId(): ?int
    {
        $id = (int) Session::get('user_id');

        return $id > 0 ? $id : null;
    }

    private function canManage(): bool
    {
        if ($this->access->isGuest()) {
            return false;
        }

        return function_exists('can') && (can('atak.sse.case.manage') || can('atak.sse.grant') || can('admin.access'));
    }

    private function canGrant(): bool
    {
        if ($this->access->isGuest()) {
            return false;
        }

        return function_exists('can') && (can('atak.sse.grant') || can('admin.access'));
    }
}
