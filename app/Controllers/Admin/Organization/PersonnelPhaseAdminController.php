<?php

declare(strict_types=1);

namespace App\Controllers\Admin\Organization;

use App\Core\Csrf;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\PersonnelPhaseRepository;
use App\Repositories\TrainingCourseRepository;
use App\Services\Personnel\PhaseRules\PhaseRuleEngine;
use App\Services\Personnel\PhaseRules\PhaseTransitionService;

final class PersonnelPhaseAdminController
{
    public function __construct(
        private PersonnelPhaseRepository $phases,
        private PhaseRuleEngine $engine,
        private PhaseTransitionService $transitions,
        private TrainingCourseRepository $courses,
    ) {}

    public function index(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        if ($tenantId < 1) {
            return Response::redirect(url('login'));
        }
        if ($this->phases->schemaReady() && $this->phases->countDefinitions($tenantId) === 0) {
            $this->phases->seedDefaultPhases($tenantId);
        }
        $list = $this->phases->listPhases($tenantId, false);
        $selectedId = (int) ($request->query('phase') ?? ($list[0]['id'] ?? 0));
        $selected = $selectedId > 0 ? $this->phases->findPhase($tenantId, $selectedId) : ($list[0] ?? null);
        $ruleSet = $selected ? $this->phases->ruleSetForPhase($tenantId, (int) $selected['id']) : null;
        $conditions = $ruleSet ? $this->phases->listConditions($tenantId, (int) $ruleSet['id']) : [];
        $previews = [];
        foreach ($conditions as $c) {
            $previews[] = PhaseRuleEngine::previewSentence($c);
        }

        return Response::view('layout.main', [
            'title' => 'Parcours RH',
            'content' => 'admin.organization.roleplay_phase_rules',
            'phaseList' => $list,
            'phaseSelected' => $selected,
            'phaseRuleSet' => $ruleSet,
            'phaseConditions' => $conditions,
            'phasePreviews' => $previews,
            'phaseCatalog' => PhaseRuleEngine::catalog(),
            'phaseCourses' => $this->courses->listForTenant($tenantId, null),
            'phaseQualifications' => $this->qualificationChoices($tenantId),
            'phaseStatuses' => ['En formation', 'Disponible', 'Actif', 'Réserve', 'Indisponible'],
            'phaseHourCategories' => \App\Services\Personnel\RoleplayGameSessionSettings::forTenant($tenantId)['hour_categories'],
            'phaseFormAction' => url('back-office/roleplay/regles-phases'),
        ]);
    }

    public function save(Request $request, array $params = []): Response
    {
        $redirect = url('back-office/roleplay/regles-phases');
        $tenantId = (int) Session::get('tenant_id');
        if ($tenantId < 1) {
            return Response::redirect(url('login'));
        }
        if (!Csrf::validate((string) $request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée. Merci de réessayer.');

            return Response::redirect($redirect);
        }
        $action = (string) $request->input('phase_action', 'save_rules');
        if ($action === 'add_phase') {
            $label = trim((string) $request->input('phase_label', ''));
            if ($label === '') {
                Session::flash('error', 'Indiquez le nom de l’étape.');

                return Response::redirect($redirect);
            }
            $pos = $this->phases->countDefinitions($tenantId) + 1;
            $id = $this->phases->createPhase($tenantId, [
                'label' => $label,
                'position' => $pos,
                'target_member_status' => (string) $request->input('phase_status', 'En formation'),
                'is_active' => 1,
            ]);
            $this->markConfigured($tenantId);
            Session::flash('success', 'Étape ajoutée.');

            return Response::redirect($redirect . '?phase=' . $id);
        }
        if ($action === 'reorder') {
            $ids = $request->input('phase_order', []);
            if (is_array($ids)) {
                $pos = 1;
                foreach ($ids as $id) {
                    $this->phases->updatePhase($tenantId, (int) $id, ['position' => $pos++]);
                }
            }
            Session::flash('success', 'Ordre des étapes enregistré.');

            return Response::redirect($redirect);
        }
        $phaseId = (int) $request->input('phase_id', 0);
        $phase = $this->phases->findPhase($tenantId, $phaseId);
        if (!$phase) {
            Session::flash('error', 'Étape introuvable.');

            return Response::redirect($redirect);
        }
        $this->phases->updatePhase($tenantId, $phaseId, [
            'label' => mb_substr(trim((string) $request->input('phase_label', $phase['label'])), 0, 120),
            'target_member_status' => (string) $request->input('phase_status', $phase['target_member_status']),
            'is_active' => $request->input('phase_active') ? 1 : 0,
        ]);
        $setId = $this->phases->saveRuleSet($tenantId, $phaseId, [
            'logic' => (string) $request->input('rule_logic', 'all'),
            'effect' => (string) $request->input('rule_effect', 'manual_gate'),
            'is_active' => 1,
        ]);
        $rows = [];
        $raw = $request->input('conditions', []);
        if (is_array($raw)) {
            foreach ($raw as $row) {
                if (!is_array($row) || trim((string) ($row['condition_type'] ?? '')) === '') {
                    continue;
                }
                $rows[] = $row;
            }
        }
        $this->phases->replaceConditions($tenantId, $setId, $rows);
        $this->markConfigured($tenantId);
        Session::flash('success', 'Parcours enregistré. Aucun passage automatique n’a lieu tant que les conditions ne sont pas remplies.');

        return Response::redirect($redirect . '?phase=' . $phaseId);
    }

    public function applyMember(Request $request, array $params = []): Response
    {
        $uid = (int) ($params['id'] ?? 0);
        $tenantId = (int) Session::get('tenant_id');
        $redirect = url('personnel/' . $uid);
        if ($tenantId < 1 || $uid < 1) {
            return Response::redirect($redirect);
        }
        if (!Csrf::validate((string) $request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée. Merci de réessayer.');

            return Response::redirect($redirect);
        }
        $override = (string) $request->input('phase_mode', '') === 'override';
        $reason = trim((string) $request->input('override_reason', ''));
        if ($override && !can('personnel.progression.override') && !can('admin.organization') && !can('admin.access')) {
            Session::flash('error', 'Vous ne pouvez pas forcer ce passage.');

            return Response::redirect($redirect);
        }
        $result = $this->transitions->applyManual($tenantId, $uid, (int) Session::get('user_id'), $override, $reason);
        if (!empty($result['ok'])) {
            Session::flash('success', $override ? 'Passage forcé enregistré.' : 'Passage à l’étape suivante enregistré.');
        } else {
            Session::flash('error', (string) ($result['error'] ?? 'Passage impossible.'));
        }

        return Response::redirect($redirect . '#parcours-rh');
    }

    private function markConfigured(int $tenantId): void
    {
        try {
            (new \App\Repositories\TenantRepository())->mergeSettings($tenantId, [
                'personnel_phase_rules' => ['reviewed' => true],
            ]);
        } catch (\Throwable) {
        }
        try {
            \App\Core\Container::get(\App\Services\ConfigurationUpdate\ConfigurationUpdateService::class)
                ->markCompleted($tenantId, 'PERSONNEL_PHASE_RULES_V1', (int) Session::get('user_id') ?: null);
        } catch (\Throwable) {
        }
    }

    /** @return list<array{id: int, label: string}> */
    private function qualificationChoices(int $tenantId): array
    {
        try {
            $pdo = Database::getPdo();
            $st = $pdo->prepare(
                'SELECT id, name AS label FROM personnel_qualification_definitions WHERE tenant_id = ? ORDER BY name ASC'
            );
            $st->execute([$tenantId]);
            $rows = $st->fetchAll(\PDO::FETCH_ASSOC) ?: [];
            if ($rows !== []) {
                return array_map(static fn (array $r): array => ['id' => (int) $r['id'], 'label' => (string) $r['label']], $rows);
            }
        } catch (\Throwable) {
        }
        try {
            $pdo = Database::getPdo();
            $st = $pdo->prepare(
                'SELECT id, qualification_name AS label FROM personnel_qualifications WHERE tenant_id = ? GROUP BY qualification_name ORDER BY qualification_name ASC'
            );
            $st->execute([$tenantId]);

            return array_map(
                static fn (array $r): array => ['id' => (int) $r['id'], 'label' => (string) $r['label']],
                $st->fetchAll(\PDO::FETCH_ASSOC) ?: []
            );
        } catch (\Throwable) {
            return [];
        }
    }
}
