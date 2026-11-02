<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Csrf;
use App\Core\Gate;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\TenantRepository;
use App\Services\Personnel\JobAssignmentBoard;
use App\Services\Personnel\JobAssignmentBoardLoader;
use App\Services\Personnel\JobAssignmentBulkService;
use App\Services\Personnel\JobRoleEnrichmentService;
use App\Services\Personnel\PersonnelJobRoleAssignmentsSettings;
use App\Support\EffectifsLmsAccess;
use App\Support\LocalizedCalendar;
use App\Support\OrgVisibilityCapabilities;

/**
 * Onglet « Qui tient quel emploi » (Effectifs › Emplois & fonctions) : données de la page, actions groupées
 * et export CSV. L’affichage reste servi par EffectifsWorkspaceController::fonctions (même coquille).
 */
final class JobAssignmentBoardController
{
    public function __construct(
        private JobAssignmentBoardLoader $loader,
        private JobAssignmentBulkService $bulk,
        private TenantRepository $tenants,
    ) {}

    /** @return array<string, mixed> variables de la vue job_assignments.php */
    public function boardData(Request $request, int $tenantId): array
    {
        $filters = JobAssignmentBoard::filters($request->queryParams());
        $settings = PersonnelJobRoleAssignmentsSettings::resolve($this->tenants->getSettings($tenantId));
        try {
            $data = $this->loader->load($tenantId, OrgVisibilityCapabilities::fromGate(Gate::getInstance()));
        } catch (\Throwable $e) {
            error_log('Job assignment board: load failed: ' . $e->getMessage());
            $data = ['ready' => false, 'jobs' => [], 'members' => [], 'categories' => [], 'tags' => [], 'units' => [], 'truncated' => false];
        }

        return [
            'jobBoard' => JobAssignmentBoard::build($data['jobs'], $data['members'], $filters, $data['categories']),
            'jobBoardReady' => $data['ready'],
            'jobBoardTruncated' => $data['truncated'],
            'jobBoardCategories' => $data['categories'],
            'jobBoardTags' => $data['tags'],
            'jobBoardUnits' => $data['units'],
            'jobBoardAllMembers' => $data['members'],
            'jobBoardAllJobs' => $data['jobs'],
            'pjrAssignSettings' => $settings,
        ];
    }

    public function bulk(Request $request, array $params = []): Response
    {
        unset($params);
        $back = $this->returnUrl($request);
        if (!(int) Session::get('user_id') || !(int) Session::get('tenant_id')) {
            return Response::redirect(url('login'));
        }
        $gate = Gate::getInstance();
        if (!EffectifsLmsAccess::allows($gate) || !(EffectifsLmsAccess::canManageAssignments($gate) || EffectifsLmsAccess::canManageRoles($gate))) {
            Session::flash('error', 'Vous n’êtes pas habilité à attribuer les emplois.');

            return Response::redirect($back);
        }
        if (!Csrf::validate((string) $request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée. Réessayez.');

            return Response::redirect($back);
        }
        $tenantId = (int) Session::get('tenant_id');
        $action = (string) $request->input('action', '');
        $roleId = (int) $request->input('job_role_id', 0);
        $raw = $request->input('user_ids', []);
        $userIds = is_array($raw) ? array_map('intval', array_values($raw)) : [(int) $raw];
        if ($action !== 'retirer') {
            // Une fonction du site masquée n’est plus proposée : on peut la retirer, pas la confier à quelqu’un de nouveau.
            $decoration = (new JobRoleEnrichmentService())->decorations($tenantId)[$roleId] ?? [];
            if (!empty($decoration['hidden'])) {
                Session::flash('error', 'Cette fonction du site est masquée : réaffichez-la avant de la confier à un membre.');

                return Response::redirect($back);
            }
        }
        $settings = PersonnelJobRoleAssignmentsSettings::resolve($this->tenants->getSettings($tenantId));
        try {
            $result = $this->bulk->apply($tenantId, (int) Session::get('user_id'), $action, $roleId, $userIds,
                (int) $request->input('replaced_user_id', 0), (int) ($settings['max_roles_per_member'] ?? 5));
        } catch (\Throwable $e) {
            error_log('Job assignment board: bulk failed: ' . $e->getMessage());
            $result = ['ok' => false, 'message' => 'Enregistrement impossible. Réessayez.'];
        }
        Session::flash($result['ok'] ? 'success' : 'error', $result['message']);

        return Response::redirect($back);
    }

    public function export(Request $request, array $params = []): Response
    {
        unset($params);
        $tenantId = (int) Session::get('tenant_id');
        if (!(int) Session::get('user_id') || $tenantId < 1) {
            return Response::redirect(url('login'));
        }
        if (!EffectifsLmsAccess::allows(Gate::getInstance())) {
            Session::flash('error', 'Accès réservé au pilotage des effectifs.');

            return Response::redirect(url('dashboard'));
        }
        $data = $this->boardData($request, $tenantId);
        $en = LocalizedCalendar::lang() === 'en';
        $labels = $en ? [
            'job' => 'Job', 'abbreviation' => 'Abbreviation', 'category' => 'Category', 'tags' => 'Tags', 'origin' => 'Origin',
            'places' => 'ORBAT seats', 'holders' => 'Holders', 'member' => 'Member', 'callsign' => 'Callsign', 'grade' => 'Rank',
            'unit' => 'Unit', 'primary' => 'Primary job', 'status' => 'Account', 'jobs' => 'Jobs', 'vacant' => 'Vacant', 'yes' => 'Yes',
            'origin_site' => 'Site', 'origin_community' => 'Created by you', 'origin_orbat' => 'ORBAT', 'origin_hidden' => 'Hidden',
        ] : [
            'job' => 'Emploi', 'abbreviation' => 'Abrégé', 'category' => 'Catégorie', 'tags' => 'Étiquettes', 'origin' => 'Origine',
            'places' => 'Places ORBAT', 'holders' => 'Titulaires', 'member' => 'Membre', 'callsign' => 'Indicatif', 'grade' => 'Grade',
            'unit' => 'Unité', 'primary' => 'Emploi principal', 'status' => 'Compte', 'jobs' => 'Emplois', 'vacant' => 'Vacant', 'yes' => 'Oui',
            'origin_site' => 'Site', 'origin_community' => 'Créée par vous', 'origin_orbat' => 'ORBAT', 'origin_hidden' => 'Masquée',
        ];
        $name = ($data['jobBoard']['filters']['affichage'] ?? '') === 'membres' ? 'emplois-par-membre' : 'qui-tient-quel-emploi';

        return (new Response())
            ->setStatusCode(200)
            ->header('Content-Type', 'text/csv; charset=utf-8')
            ->header('Content-Disposition', 'attachment; filename="' . $name . '-' . date('Ymd-Hi') . '.csv"')
            ->header('Cache-Control', 'private, no-store')
            ->setBody(JobAssignmentBoard::csv($data['jobBoard'], $labels));
    }

    /** Retour sur l’onglet avec les mêmes filtres ; seules les clés connues sont reprises. */
    private function returnUrl(Request $request): string
    {
        parse_str((string) $request->input('return_query', ''), $parsed);
        $query = JobAssignmentBoard::query(JobAssignmentBoard::filters(is_array($parsed) ? $parsed : []));

        return effectifs_workspace_url('fonctions') . '?' . http_build_query($query);
    }
}
