<?php

declare(strict_types=1);

namespace App\Services\Admin;

use App\Core\Request;
use App\Repositories\AdminActionRepository;

final class AdminActionService
{
    public function __construct(private ?AdminActionRepository $repo = null)
    {
        $this->repo ??= new AdminActionRepository();
    }

    public function log(Request $request, array $payload, array $before = [], array $after = []): int
    {
        $payload['ip_address'] = method_exists($request, 'ip') ? $request->ip() : null;
        $payload['session_id'] = session_id() ?: null;

        return $this->repo->create($payload, $before, $after);
    }
}
