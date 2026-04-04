<?php

declare(strict_types=1);

namespace App\Controllers\Admin\System;

use App\Core\Request;
use App\Core\Response;
use App\Repositories\AuditLogRepository;

class SystemAuditController
{
    public function __construct(
        private ?AuditLogRepository $auditLogs = null
    ) {
        $this->auditLogs ??= new AuditLogRepository();
    }

    public function index(Request $request, array $params = []): Response
    {
        $page = max(1, (int) $request->query('page', 1));
        $perPage = 25;
        $filters = [
            'date_from' => $this->optionalString($request->query('date_from')),
            'date_to' => $this->optionalString($request->query('date_to')),
            'action' => $this->optionalString($request->query('action')),
            'user_id' => $this->optionalPositiveInt($request->query('user_id')),
            'tenant_id' => $this->optionalPositiveInt($request->query('tenant_id')),
        ];
        $result = $this->auditLogs->listSystem($filters, $page, $perPage);
        $totalPages = max(1, (int) ceil($result['total'] / $perPage));

        return Response::view('layout.main', [
            'content' => 'admin.system.audit',
            'title' => 'Journaux d\'audit',
            'auditRows' => $result['rows'],
            'auditTotal' => $result['total'],
            'auditPage' => $page,
            'auditPerPage' => $perPage,
            'auditTotalPages' => $totalPages,
            'auditFilters' => $filters,
            'auditScope' => 'system',
        ]);
    }

    private function optionalString(mixed $v): ?string
    {
        if ($v === null || $v === '') {
            return null;
        }

        return trim((string) $v);
    }

    private function optionalPositiveInt(mixed $v): ?int
    {
        $n = (int) $v;

        return $n > 0 ? $n : null;
    }
}
