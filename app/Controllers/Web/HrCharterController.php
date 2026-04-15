<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\HrCharterRepository;
use App\Services\Auth\AuthService;

final class HrCharterController
{
    public function __construct(
        private AuthService $authService,
        private HrCharterRepository $hrCharterRepository
    ) {}

    public function show(Request $request, array $params = []): Response
    {
        $user = $this->authService->user();
        if (!$user) {
            return Response::redirect(url('login'));
        }

        $tenantId = (int) Session::get('tenant_id');
        if ($tenantId < 1) {
            return Response::redirect(url('dashboard'));
        }

        $activeVersion = $this->hrCharterRepository->findActiveVersion();
        $acceptance = $activeVersion !== null
            ? $this->hrCharterRepository->hasAcceptedVersion($tenantId, (int) $user['id'], (int) $activeVersion['id'])
            : false;

        return Response::view('layout.main', [
            'content' => 'rh/charter',
            'title' => 'Charte RH & conformité',
            'activeVersion' => $activeVersion,
            'hasAcceptedActiveVersion' => $acceptance,
            'latestAcceptance' => $this->hrCharterRepository->findLatestAcceptance($tenantId, (int) $user['id']),
            'lmsTracks' => $this->defaultLmsTracks(),
        ]);
    }

    public function accept(Request $request, array $params = []): Response
    {
        $user = $this->authService->user();
        if (!$user) {
            return Response::redirect(url('login'));
        }

        if (!Csrf::validate((string) $request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée, merci de relancer la validation de la charte.');

            return Response::redirect(url('rh/charte'));
        }

        $tenantId = (int) Session::get('tenant_id');
        $activeVersion = $this->hrCharterRepository->findActiveVersion();
        if ($tenantId < 1 || $activeVersion === null) {
            Session::flash('error', 'Aucune version active de charte n’est publiée pour le moment.');

            return Response::redirect(url('rh/charte'));
        }

        $confirmed = (string) $request->input('confirm_acceptance', '') === '1';
        if (!$confirmed) {
            Session::flash('error', 'Vous devez confirmer votre engagement avant signature.');

            return Response::redirect(url('rh/charte'));
        }

        $this->hrCharterRepository->storeAcceptance(
            $tenantId,
            (int) $user['id'],
            (int) $activeVersion['id'],
            trim($request->ip()) ?: null,
            trim($request->userAgent()) ?: null
        );

        Session::flash('success', 'Charte validée. Votre accusé de prise de connaissance a été enregistré.');

        return Response::redirect(url('rh/charte'));
    }

    /**
     * @return list<array{title: string, duration: string, status: string}>
     */
    private function defaultLmsTracks(): array
    {
        return [
            ['title' => 'Protection des données et RGPD opérationnel', 'duration' => '35 min', 'status' => 'required'],
            ['title' => 'Traçabilité des accès SIRH', 'duration' => '20 min', 'status' => 'required'],
            ['title' => 'Hygiène numérique et sécurité des comptes', 'duration' => '25 min', 'status' => 'required'],
            ['title' => 'Conduite à tenir en cas de violation de données', 'duration' => '15 min', 'status' => 'required'],
        ];
    }
}
