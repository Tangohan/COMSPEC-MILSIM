<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\EnlistmentRepository;

class AdminRecruitmentsController
{
    public function __construct(
        private EnlistmentRepository $enlistmentRepository
    ) {}

    public function index(Request $request, array $params = []): Response
    {
        $tenantId = Session::get('tenant_id');
        if (!$tenantId) {
            return Response::redirect(url('login'));
        }
        $statusFilter = $request->query('status');
        $enlistments = $this->enlistmentRepository->allForTenant((int) $tenantId, $statusFilter ?: null);

        return Response::view('layout.main', [
            'content' => 'admin.recruitments.index',
            'title' => 'Candidatures',
            'enlistments' => $enlistments,
            'statusFilter' => $statusFilter,
        ]);
    }
}
