<?php

declare(strict_types=1);

namespace App\Controllers\Admin\System;

use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Services\Platform\LowMemberOrgService;

/**
 * Administration du site > Organisations à supprimer : communautés toujours à un ou deux membres après les rappels.
 * « Supprimer » ouvre le retrait existant (archiver puis supprimer définitivement, avec confirmation) ;
 * « Garder » retire la communauté des rappels et de la liste.
 */
final class SystemLowMemberOrgsController
{
    public function __construct(private ?LowMemberOrgService $service = null)
    {
        $this->service ??= new LowMemberOrgService();
    }

    public function index(Request $request, array $params = []): Response
    {
        $rows = [];
        $error = null;
        $ready = $this->service->schemaReady();
        if ($ready) {
            try {
                $rows = $this->service->evaluate();
            } catch (\Throwable) {
                $error = 'La liste ne peut pas être calculée pour le moment.';
            }
        }
        $groups = ['ready' => [], 'warned' => [], 'archived' => [], 'kept' => []];
        foreach ($rows as $row) {
            $key = $row['status'] === 'pending' ? 'warned' : $row['status'];
            $groups[$key][] = $row;
        }

        return Response::view('layout.main', [
            'title' => 'Organisations à supprimer',
            'content' => 'admin.system.low_member_orgs',
            'lowOrgsSchemaReady' => $ready,
            'lowOrgsError' => $error,
            'lowOrgsGroups' => $groups,
            'backOfficePageCss' => ['platform-admin.css'],
        ]);
    }

    public function keep(Request $request, array $params = []): Response
    {
        return $this->decide($request, (int) ($params['id'] ?? 0), true);
    }

    public function unkeep(Request $request, array $params = []): Response
    {
        return $this->decide($request, (int) ($params['id'] ?? 0), false);
    }

    private function decide(Request $request, int $tenantId, bool $keep): Response
    {
        $back = url('admin/organisations-a-supprimer');
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée. Réessayez.');

            return Response::redirect($back);
        }
        if ($tenantId <= 1 || !$this->service->schemaReady()) {
            Session::flash('error', 'Communauté introuvable.');

            return Response::redirect($back);
        }
        $actor = Session::get('user_id');
        if ($keep) {
            $this->service->keep($tenantId, is_numeric($actor) ? (int) $actor : null);
            Session::flash('success', 'Communauté gardée : plus de rappel ni d’alerte pour elle.');
        } else {
            $this->service->unkeep($tenantId);
            Session::flash('success', 'Communauté remise dans le suivi : elle reviendra dans la liste si elle compte toujours un ou deux membres.');
        }

        return Response::redirect($back);
    }
}
