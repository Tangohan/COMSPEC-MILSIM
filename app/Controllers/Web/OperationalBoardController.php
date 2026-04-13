<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\PlanningEntryRepository;

final class OperationalBoardController
{
    public function __construct(private PlanningEntryRepository $planningEntries) {}

    public function index(Request $request, array $params = []): Response
    {
        $tenantId = (int) (Session::get('tenant_id') ?? 0);
        if ($tenantId < 1) {
            return Response::redirect(url('login'));
        }

        $posture = $this->planningEntries->getPosture($tenantId) ?? ['posture_level' => 'NORMAL'];
        $filters = [
            'status' => (string) ($request->query('status') ?? 'active'),
            'entry_type' => (string) ($request->query('entry_type') ?? ''),
            'operational_status' => (string) ($request->query('operational_status') ?? ''),
            'tag' => (string) ($request->query('tag') ?? ''),
            'period_start' => (string) ($request->query('period_start') ?? date('Y-m-d')),
            'period_end' => (string) ($request->query('period_end') ?? date('Y-m-d', strtotime('+14 days'))),
            'mode' => (string) ($request->query('mode') ?? 'standard'),
            'critical_only' => (int) ($request->query('critical_only') ?? 0),
        ];
        if (in_array((string) ($posture['posture_level'] ?? 'NORMAL'), ['ALERTE', 'CRISE'], true)) {
            $filters['critical_only'] = 1;
        }

        $entries = $this->planningEntries->listForBoard($tenantId, $filters);
        $panels = ['permanences' => [], 'infos' => [], 'activites' => []];
        foreach ($entries as $entry) {
            $type = (string) ($entry['entry_type'] ?? 'task');
            $panels[$type === 'permanence' ? 'permanences' : ($type === 'info' ? 'infos' : 'activites')][] = $entry;
        }

        return Response::view('layout.main', [
            'title' => 'Tableau opérationnel',
            'content' => 'operations/board',
            'boardSchemaReady' => $this->planningEntries->isOperationalBoardSchemaReady(),
            'boardFilters' => $filters,
            'boardPanels' => $panels,
            'boardCategories' => $this->planningEntries->listCategories($tenantId),
            'boardTemplates' => $this->planningEntries->listTemplates($tenantId),
            'boardPosture' => $posture,
            'boardLogs' => $this->planningEntries->listRecentLogs($tenantId, 40),
            'boardTags' => $this->planningEntries->listTags($tenantId),
            'boardQualificationAlerts' => $this->planningEntries->findQualificationAlerts($tenantId),
            'boardFireConflicts' => $this->planningEntries->findFireWindowConflicts($tenantId),
        ]);
    }

    public function store(Request $request, array $params = []): Response
    {
        if (!$this->validateCsrf()) {
            return Response::redirect(url('back-office/tableau-operationnel'));
        }

        [$tenantId, $userId] = $this->tenantAndUser();
        $title = trim((string) $request->input('title', ''));
        if ($tenantId < 1 || $userId < 1 || $title === '') {
            Session::flash('error', 'Impossible de créer l\'entrée (titre/session).');
            return Response::redirect(url('back-office/tableau-operationnel'));
        }

        $newId = $this->planningEntries->create([
            'tenant_id' => $tenantId,
            'title' => $title,
            'description' => trim((string) $request->input('description', '')),
            'entry_type' => $this->enum($request->input('entry_type', 'task'), ['permanence', 'info', 'mission', 'task', 'formation'], 'task'),
            'category_id' => (int) $request->input('category_id', 0),
            'linked_type' => $this->normalizeLinkedType((string) $request->input('linked_type', 'none')),
            'linked_id' => $this->nullableInt($request->input('linked_id', '')),
            'start_date' => trim((string) $request->input('start_date', '')),
            'end_date' => trim((string) $request->input('end_date', '')),
            'status' => 'draft',
            'validation_status' => 'draft',
            'priority' => $this->enum($request->input('priority', 'normal'), ['low', 'normal', 'high', 'critical'], 'normal'),
            'display_order' => (int) $request->input('display_order', 100),
            'visibility_scope' => $this->enum($request->input('visibility_scope', 'tenant'), ['tenant', 'unit', 'role', 'private'], 'tenant'),
            'security_level' => $this->enum($request->input('security_level', 'unit_public'), ['unit_public', 'command_restricted', 'confidential', 'secret_ops'], 'unit_public'),
            'operational_status' => $this->enum($request->input('operational_status', 'planned'), ['planned', 'in_progress', 'suspended', 'completed', 'cancelled'], 'planned'),
            'phase_current' => $this->enum($request->input('phase_current', 'phase_1'), ['phase_1', 'phase_2', 'phase_3'], 'phase_1'),
            'chief_user_id' => $this->nullableInt($request->input('chief_user_id', '')),
            'deputy_user_id' => $this->nullableInt($request->input('deputy_user_id', '')),
            'replacement_user_id' => $this->nullableInt($request->input('replacement_user_id', '')),
            'replacement_auto_activate' => (int) $request->input('replacement_auto_activate', 0),
            'command_chain' => trim((string) $request->input('command_chain', '')),
            'accountability_note' => trim((string) $request->input('accountability_note', '')),
            'location_lat' => trim((string) $request->input('location_lat', '')),
            'location_lng' => trim((string) $request->input('location_lng', '')),
            'operation_zone' => trim((string) $request->input('operation_zone', '')),
            'map_link' => trim((string) $request->input('map_link', '')),
            'dossier_ref' => trim((string) $request->input('dossier_ref', '')),
            'legal_constraints' => trim((string) $request->input('legal_constraints', '')),
            'fire_window_start' => trim((string) $request->input('fire_window_start', '')),
            'fire_window_end' => trim((string) $request->input('fire_window_end', '')),
            'created_by' => $userId,
        ]);
        if ($newId < 1) {
            Session::flash('error', 'Le tableau opérationnel n’est pas encore activé sur ce serveur. Un administrateur doit exécuter les mises à jour de base de données (script d’initialisation ou migrations Phinx).');
            return Response::redirect(url('back-office/tableau-operationnel'));
        }

        Session::flash('success', 'Entrée créée en brouillon.');
        return Response::redirect(url('back-office/tableau-operationnel'));
    }

    public function setPosture(Request $request, array $params = []): Response
    {
        if (!$this->validateCsrf()) {
            return Response::redirect(url('back-office/tableau-operationnel'));
        }
        [$tenantId, $userId] = $this->tenantAndUser();
        $level = $this->enum($request->input('posture_level', 'NORMAL'), ['NORMAL', 'VIGILANCE', 'ALERTE', 'CRISE'], 'NORMAL');
        $this->planningEntries->setPosture($tenantId, $level, $userId);
        $labels = [
            'NORMAL' => 'niveau normal',
            'VIGILANCE' => 'vigilance renforcée',
            'ALERTE' => 'alerte',
            'CRISE' => 'crise',
        ];
        Session::flash('success', 'Niveau de posture appliqué : ' . ($labels[$level] ?? 'mis à jour') . '.');
        return Response::redirect(url('back-office/tableau-operationnel'));
    }

    public function transitionValidation(Request $request, array $params = []): Response
    {
        if (!$this->validateCsrf()) {
            return Response::redirect(url('back-office/tableau-operationnel'));
        }
        [$tenantId, $userId] = $this->tenantAndUser();
        $entryId = (int) ($params['id'] ?? 0);
        $status = $this->enum($request->input('validation_status', 'validated'), ['draft', 'validated', 'active', 'rejected'], 'validated');
        $reason = trim((string) $request->input('validation_comment', ''));
        $this->planningEntries->transitionValidation($tenantId, $entryId, $status, $reason !== '' ? $reason : null, $userId);
        Session::flash('success', 'Validation mise à jour.');
        return Response::redirect(url('back-office/tableau-operationnel'));
    }

    public function transitionOperationalStatus(Request $request, array $params = []): Response
    {
        if (!$this->validateCsrf()) {
            return Response::redirect(url('back-office/tableau-operationnel'));
        }
        [$tenantId, $userId] = $this->tenantAndUser();
        $entryId = (int) ($params['id'] ?? 0);
        $status = $this->enum($request->input('operational_status', 'planned'), ['planned', 'in_progress', 'suspended', 'completed', 'cancelled'], 'planned');
        if (!$this->planningEntries->transitionOperationalStatus($tenantId, $entryId, $status, $userId)) {
            Session::flash('error', 'Transition refusée (checklist requise ou statut invalide).');
        } else {
            Session::flash('success', 'Cycle opérationnel mis à jour.');
        }
        return Response::redirect(url('back-office/tableau-operationnel'));
    }

    public function createFromTemplate(Request $request, array $params = []): Response
    {
        if (!$this->validateCsrf()) {
            return Response::redirect(url('back-office/tableau-operationnel'));
        }
        [$tenantId, $userId] = $this->tenantAndUser();
        $entryId = $this->planningEntries->createFromTemplate($tenantId, (int) $request->input('template_id', 0), $userId);
        Session::flash($entryId === null ? 'error' : 'success', $entryId === null ? 'Template introuvable.' : 'Entrée créée depuis template.');
        return Response::redirect(url('back-office/tableau-operationnel'));
    }

    public function createFrago(Request $request, array $params = []): Response
    {
        if (!$this->validateCsrf()) {
            return Response::redirect(url('back-office/tableau-operationnel'));
        }
        [$tenantId, $userId] = $this->tenantAndUser();
        $entryId = $this->planningEntries->createFrago($tenantId, (int) ($params['id'] ?? 0), $userId);
        Session::flash($entryId === null ? 'error' : 'success', $entryId === null ? 'FRAGO impossible.' : 'FRAGO généré.');
        return Response::redirect(url('back-office/tableau-operationnel'));
    }

    public function toggleChecklist(Request $request, array $params = []): Response
    {
        if (!$this->validateCsrf()) {
            return Response::redirect(url('back-office/tableau-operationnel'));
        }
        [$tenantId, $userId] = $this->tenantAndUser();
        $entryId = (int) ($params['id'] ?? 0);
        $itemId = (int) ($params['itemId'] ?? 0);
        $done = (int) $request->input('is_done', 0) === 1;
        $this->planningEntries->markChecklistItem($tenantId, $entryId, $itemId, $done, $userId);
        return Response::redirect(url('back-office/tableau-operationnel'));
    }

    public function stream(Request $request, array $params = []): Response
    {
        $tenantId = (int) (Session::get('tenant_id') ?? 0);
        $since = (int) ($request->query('since_id') ?? 0);
        return Response::json([
            'ok' => true,
            'events' => $this->planningEntries->listRealtimeSnapshot($tenantId, $since, 100),
        ]);
    }

    private function validateCsrf(): bool
    {
        if (!Csrf::validate((string) ($_POST['_csrf_token'] ?? ''))) {
            Session::flash('error', 'Session expirée.');
            return false;
        }
        return true;
    }

    /** @return array{0:int,1:int} */
    private function tenantAndUser(): array
    {
        return [(int) (Session::get('tenant_id') ?? 0), (int) (Session::get('user_id') ?? 0)];
    }

    private function enum(mixed $value, array $allowed, string $fallback): string
    {
        $value = trim((string) $value);
        return in_array($value, $allowed, true) ? $value : $fallback;
    }

    private function nullableInt(mixed $value): ?int
    {
        $v = trim((string) $value);
        $i = (int) $v;
        return $i > 0 ? $i : null;
    }

    private function normalizeLinkedType(string $linkedType): ?string
    {
        $normalized = $this->enum($linkedType, ['event', 'mission', 'formation', 'none'], 'none');
        return $normalized === 'none' ? null : $normalized;
    }
}
