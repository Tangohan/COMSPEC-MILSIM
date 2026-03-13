<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\Csrf;
use App\Repositories\EnlistmentRepository;
use App\Repositories\TenantRepository;

class EnlistmentController
{
    public function __construct(
        private EnlistmentRepository $enlistmentRepository,
        private TenantRepository $tenantRepository
    ) {}

    public function show(Request $request, array $params = []): Response
    {
        return Response::view('layout.main', ['content' => 'enlistment', 'title' => 'Enrôlement']);
    }

    public function store(Request $request, array $params = []): Response
    {
        if (!$request->isPost()) {
            return Response::redirect(url('enlistment'));
        }
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée. Réessayez.');
            return Response::redirect(url('enlistment'));
        }
        $tenant = $this->tenantRepository->getDefaultTenant();
        if (!$tenant) {
            Session::flash('error', 'Organisation non configurée.');
            return Response::redirect(url('enlistment'));
        }
        $this->enlistmentRepository->create((int) $tenant['id'], [
            'first_name' => trim((string) $request->input('first_name')),
            'last_name' => trim((string) $request->input('last_name')),
            'email' => trim((string) $request->input('email')),
            'callsign' => trim((string) $request->input('callsign')) ?: null,
            'country' => trim((string) $request->input('country')) ?: null,
            'experience' => trim((string) $request->input('experience')) ?: null,
            'specialty' => trim((string) $request->input('specialty')) ?: null,
            'platform' => trim((string) $request->input('platform')) ?: null,
            'availability' => trim((string) $request->input('availability')) ?: null,
            'notes' => trim((string) $request->input('notes')) ?: null,
        ]);
        Session::flash('success', 'Candidature enregistrée. Nous vous recontacterons.');
        return Response::redirect(url('enlistment'));
    }
}
