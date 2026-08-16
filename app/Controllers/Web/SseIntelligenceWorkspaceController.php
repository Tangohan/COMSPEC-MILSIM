<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\SseCaseRepository;
use App\Repositories\TheatreMissionCycleRepository;
use App\Services\Sse\SseAccessCodeService;
use App\Services\Sse\SseAnalysisService;
use App\Services\Sse\SseIntelligenceWorkspaceService;
use App\Services\Sse\SseIntelCycleService;

/**
 * Page Intelligence Workspace (LOT 1) — coque 3 colonnes branchée aux données réelles.
 * Ne remplace pas SsePortalController : route dédiée /atak/sse/workspace.
 */
final class SseIntelligenceWorkspaceController
{
    public function __construct(
        private ?SseAccessCodeService $access = null,
        private ?SseIntelligenceWorkspaceService $workspace = null,
        private ?SseIntelCycleService $cycle = null,
        private ?SseAnalysisService $analysis = null,
        private ?SseCaseRepository $cases = null,
        private ?TheatreMissionCycleRepository $missions = null,
    ) {
        $this->access ??= new SseAccessCodeService();
        $this->workspace ??= new SseIntelligenceWorkspaceService();
        $this->cycle ??= new SseIntelCycleService();
        $this->analysis ??= new SseAnalysisService();
        $this->cases ??= new SseCaseRepository();
        $this->missions ??= new TheatreMissionCycleRepository();
    }

    public function show(Request $request, array $params = []): Response
    {
        $tenantId = $this->tenantId();
        $caseId = (int) ($request->query('case') ?? 0);
        $userId = (int) (Session::get('user_id') ?? 0);
        $payload = $this->workspace->workspacePayload(
            $tenantId,
            $this->access->caseScope(),
            $caseId > 0 ? $caseId : null,
            $userId > 0 ? $userId : null
        );

        return $this->portalView('atak.sse.intelligence_workspace', [
            'title' => 'Intelligence Workspace',
            'activeNav' => 'workspace',
            'ws' => $payload,
            'canManage' => $this->canManage(),
            'canGrant' => $this->canGrant(),
            'csrfToken' => \App\Core\Csrf::token(),
        ]);
    }

    public function inboxDecide(Request $request, array $params = []): Response
    {
        if (!$this->canManage() || !\App\Core\Csrf::validate((string) $request->input('_csrf_token', ''))) {
            Session::flash('error', 'Action non autorisée.');

            return Response::redirect(url('atak/sse/workspace'));
        }
        $result = $this->workspace->decideInboxItem(
            $this->tenantId(),
            (string) $request->input('kind', ''),
            (int) $request->input('id', 0),
            (string) $request->input('decision', ''),
            (string) (Session::get('display_name') ?? 'Analyste'),
            (int) (Session::get('user_id') ?? 0) ?: null
        );
        Session::flash($result['ok'] ? 'success' : 'error', $result['message'] ?? ($result['ok'] ? 'OK' : 'Échec'));
        $case = (int) $request->input('case', 0);

        return Response::redirect(url('atak/sse/workspace') . ($case > 0 ? ('?case=' . $case) : ''));
    }

    public function caseMetaUpdate(Request $request, array $params = []): Response
    {
        if (!$this->canManage() || !\App\Core\Csrf::validate((string) $request->input('_csrf_token', ''))) {
            Session::flash('error', 'Action non autorisée.');

            return Response::redirect(url('atak/sse/workspace'));
        }
        $caseId = (int) ($params['id'] ?? $request->input('case_id', 0));
        $result = $this->workspace->updateCaseMeta(
            $this->tenantId(),
            $caseId,
            [
                'lifecycle_status' => $request->input('lifecycle_status'),
                'priority' => $request->input('priority'),
                'producing_unit' => $request->input('producing_unit'),
                'confidence_note' => $request->input('confidence_note'),
            ],
            (string) (Session::get('display_name') ?? 'Analyste'),
            (int) (Session::get('user_id') ?? 0) ?: null
        );
        Session::flash($result['ok'] ? 'success' : 'error', $result['ok']
            ? 'Chemise mise à jour.'
            : ($result['message'] ?? 'Mise à jour impossible.'));

        return Response::redirect(url('atak/sse/workspace') . '?case=' . $caseId);
    }

    public function cycleRequirementStore(Request $request, array $params = []): Response
    {
        $caseId = (int) ($request->input('case_id', 0));
        $back = url('atak/sse/workspace') . ($caseId > 0 ? ('?case=' . $caseId . '#cycle') : '#cycle');
        if (!$this->canManage() || !\App\Core\Csrf::validate((string) $request->input('_csrf_token', ''))) {
            Session::flash('error', 'Action non autorisée.');

            return Response::redirect($back);
        }
        $result = $this->cycle->createRequirement(
            $this->tenantId(),
            [
                'case_id' => $caseId > 0 ? $caseId : null,
                'req_type' => (string) $request->input('req_type', 'PIR'),
                'title' => (string) $request->input('title', ''),
                'question' => (string) $request->input('question', ''),
                'priority' => (string) $request->input('priority', 'normale'),
                'assignee_label' => (string) $request->input('assignee_label', ''),
                'confirmation_criterion' => (string) $request->input('confirmation_criterion', ''),
                'due_at' => (string) $request->input('due_at', ''),
                'pos_x' => $request->input('pos_x', ''),
                'pos_y' => $request->input('pos_y', ''),
                'visible_on_atak' => in_array((string) $request->input('visible_on_atak', ''), ['1', 'on', 'true'], true),
            ],
            (string) (Session::get('display_name') ?? 'Analyste'),
            (int) (Session::get('user_id') ?? 0) ?: null
        );
        Session::flash($result['ok'] ? 'success' : 'error', $result['message'] ?? $result['error'] ?? 'Échec');

        return Response::redirect($back);
    }

    public function cycleTaskingStore(Request $request, array $params = []): Response
    {
        $caseId = (int) ($request->input('case_id', 0));
        $back = url('atak/sse/workspace') . ($caseId > 0 ? ('?case=' . $caseId . '#cycle') : '#cycle');
        if (!$this->canManage() || !\App\Core\Csrf::validate((string) $request->input('_csrf_token', ''))) {
            Session::flash('error', 'Action non autorisée.');

            return Response::redirect($back);
        }
        $result = $this->cycle->createTasking(
            $this->tenantId(),
            [
                'requirement_id' => (int) $request->input('requirement_id', 0),
                'case_id' => $caseId > 0 ? $caseId : null,
                'title' => (string) $request->input('title', ''),
                'instruction' => (string) $request->input('instruction', ''),
                'tasked_unit' => (string) $request->input('tasked_unit', ''),
                'tasked_callsign' => (string) $request->input('tasked_callsign', ''),
                'priority' => (string) $request->input('priority', 'normale'),
                'pos_x' => $request->input('pos_x', ''),
                'pos_y' => $request->input('pos_y', ''),
                'visible_on_atak' => in_array((string) $request->input('visible_on_atak', ''), ['1', 'on', 'true'], true),
                'emit' => true,
            ],
            (string) (Session::get('display_name') ?? 'Analyste'),
            (int) (Session::get('user_id') ?? 0) ?: null
        );
        Session::flash($result['ok'] ? 'success' : 'error', $result['message'] ?? $result['error'] ?? 'Échec');

        return Response::redirect($back);
    }

    public function cycleProductGenerate(Request $request, array $params = []): Response
    {
        $caseId = (int) ($request->input('case_id', 0));
        $back = url('atak/sse/workspace') . ($caseId > 0 ? ('?case=' . $caseId . '#cycle') : '#cycle');
        if (!$this->canManage() || !\App\Core\Csrf::validate((string) $request->input('_csrf_token', ''))) {
            Session::flash('error', 'Action non autorisée.');

            return Response::redirect($back);
        }
        $result = $this->cycle->generateProduct(
            $this->tenantId(),
            $caseId,
            (string) $request->input('product_type', 'INITIAL'),
            (string) $request->input('release_level', 'interne'),
            (string) (Session::get('display_name') ?? 'Analyste'),
            (int) (Session::get('user_id') ?? 0) ?: null,
            ((int) $request->input('requirement_id', 0)) ?: null
        );
        Session::flash($result['ok'] ? 'success' : 'error', $result['message'] ?? $result['error'] ?? 'Échec');

        return Response::redirect($back);
    }

    public function cycleProductAction(Request $request, array $params = []): Response
    {
        $productId = (int) ($params['id'] ?? 0);
        $caseId = (int) ($request->input('case_id', 0));
        $action = (string) ($request->input('action', ''));
        $back = url('atak/sse/workspace') . ($caseId > 0 ? ('?case=' . $caseId . '#cycle') : '#cycle');
        if (!$this->canManage() || !\App\Core\Csrf::validate((string) $request->input('_csrf_token', ''))) {
            Session::flash('error', 'Action non autorisée.');

            return Response::redirect($back);
        }
        $author = (string) (Session::get('display_name') ?? 'Analyste');
        $result = match ($action) {
            'valider' => $this->cycle->validateProduct($this->tenantId(), $productId, $author),
            'sanitiser' => $this->cycle->sanitiseProduct(
                $this->tenantId(),
                $productId,
                (string) $request->input('release_level', '') ?: null,
                $author
            ),
            'diffuser' => $this->cycle->diffuseProduct(
                $this->tenantId(),
                $productId,
                array_map(
                    static fn (string $l): array => ['label' => $l],
                    array_values(array_filter(array_map(
                        'trim',
                        preg_split('/[,;\n]+/', (string) $request->input('recipients_text', '')) ?: []
                    )))
                ),
                $author
            ),
            default => ['ok' => false, 'error' => 'Action non reconnue.'],
        };
        Session::flash($result['ok'] ? 'success' : 'error', $result['message'] ?? $result['error'] ?? 'Échec');

        return Response::redirect($back);
    }

    public function analysisFindingDecide(Request $request, array $params = []): Response
    {
        $findingId = (int) ($params['id'] ?? 0);
        $caseId = (int) ($request->input('case_id', 0));
        $back = url('atak/sse/workspace') . ($caseId > 0 ? ('?case=' . $caseId . '#analyse') : '#analyse');
        if (!$this->canManage() || !\App\Core\Csrf::validate((string) $request->input('_csrf_token', ''))) {
            Session::flash('error', 'Action non autorisée.');

            return Response::redirect($back);
        }
        $result = $this->analysis->decideFinding(
            $this->tenantId(),
            $findingId,
            (string) $request->input('decision', 'ecarte'),
            (string) (Session::get('display_name') ?? 'Analyste')
        );
        Session::flash(
            $result['ok'] ? 'success' : 'error',
            $result['ok'] ? 'Décision enregistrée.' : ($result['error'] ?? 'Échec')
        );

        return Response::redirect($back);
    }

    private function tenantId(): int
    {
        $tid = $this->access->tenantId();
        if ($tid > 0) {
            return $tid;
        }

        return (int) (Session::get('tenant_id') ?? 0);
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

    /**
     * @param array<string, mixed> $data
     */
    private function portalView(string $view, array $data): Response
    {
        $data['isGuest'] = $this->access->isGuest();
        $data['clearanceUntil'] = (int) Session::get(SseAccessCodeService::SESSION_UNTIL, 0);
        $data['guestLabel'] = (string) Session::get('sse_guest_label', '');
        $data['sseTheme'] = function_exists('sse_ui_theme') ? sse_ui_theme() : 'bureau';
        $data['sseThemeOptions'] = function_exists('sse_ui_theme_options') ? sse_ui_theme_options() : [];
        $data['canGrant'] = $data['canGrant'] ?? $this->canGrant();
        $data['canManage'] = $data['canManage'] ?? $this->canManage();

        $tenantId = $this->tenantId();
        $missionsRaw = $tenantId > 0 ? $this->missions->listForTenant($tenantId, 40) : [];
        $missions = [];
        foreach ($missionsRaw as $row) {
            $id = (int) ($row['id'] ?? 0);
            if ($id < 1) {
                continue;
            }
            $status = (string) ($row['status'] ?? '');
            $missions[] = [
                'id' => $id,
                'title' => trim((string) ($row['title'] ?? '')) ?: ('Mission #' . $id),
                'status' => $status,
                'status_label' => TheatreMissionCycleRepository::statusLabel($status),
            ];
        }
        $data['sseMissions'] = $missions;
        $data['sseMissionId'] = function_exists('sse_ui_mission_id') ? sse_ui_mission_id() : 0;
        $data['sseMissionLabel'] = 'Aucune mission';
        foreach ($missions as $m) {
            if ((int) $m['id'] === (int) $data['sseMissionId']) {
                $data['sseMissionLabel'] = $m['title'];
                break;
            }
        }
        $data['sseClassification'] = function_exists('sse_ui_classification') ? sse_ui_classification() : 'encadrement';
        $data['sseClassificationLabel'] = function_exists('sse_ui_classification_label')
            ? sse_ui_classification_label()
            : 'Encadrement';
        $data['sseClassificationOptions'] = function_exists('sse_ui_classification_options')
            ? sse_ui_classification_options()
            : [];

        if ($tenantId > 0 && $this->access->hasActiveClearance()) {
            $scope = $this->access->caseScope();
            $allForRail = $this->cases->listForTenant($tenantId, $scope);
            $data['sseFolderTree'] = $this->cases->buildTree($allForRail);
            $data['sseFolderParents'] = array_values(array_filter(
                $allForRail,
                static fn (array $c): bool => !empty($c['is_folder'])
            ));
            $data['sseRecentCases'] = array_slice(array_values(array_filter(
                $allForRail,
                static fn (array $c): bool => empty($c['is_folder'])
            )), 0, 8);
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
        } else {
            $data['sseFolderTree'] = [];
            $data['sseFolderParents'] = [];
            $data['sseRecentCases'] = [];
            $data['indexCounts'] = ['total' => 0, 'active' => 0, 'archive' => 0];
        }

        return Response::view($view, $data);
    }
}
