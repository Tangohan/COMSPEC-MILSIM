<?php

declare(strict_types=1);

namespace App\Controllers\Admin\System;

use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Services\DemoNda\DemoNdaGateService;

final class SystemDemoNdaController
{
    public function __construct(
        private DemoNdaGateService $gate,
    ) {}

    public function index(Request $request, array $params = []): Response
    {
        return Response::view('layout.main', [
            'title' => 'Accès démonstration',
            'content' => 'admin.system.demo_nda',
            'gateEnabled' => $this->gate->isEnabled(),
            'ttlHours' => $this->gate->ttlHours(),
            'accessCode' => $this->gate->getAccessCode(),
            'envBypassIps' => $this->gate->envBypassIps(),
            'adminBypassIps' => $this->gate->adminBypassIps(),
            'clientIp' => $this->gate->clientIp(),
            'visits' => $this->gate->listRecentVisits(100),
        ]);
    }

    public function regenerateCode(Request $request, array $params = []): Response
    {
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée.');

            return Response::redirect(url('admin/system/demo-nda'));
        }
        $code = $this->gate->regenerateAccessCode();
        Session::flash('success', 'Nouveau code généré : ' . $code);

        return Response::redirect(url('admin/system/demo-nda'));
    }

    public function addBypassIp(Request $request, array $params = []): Response
    {
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée.');

            return Response::redirect(url('admin/system/demo-nda'));
        }
        $ip = trim((string) $request->input('bypass_ip', ''));
        if (!$this->gate->addAdminBypassIp($ip)) {
            Session::flash('error', 'Adresse réseau invalide.');

            return Response::redirect(url('admin/system/demo-nda'));
        }
        Session::flash('success', 'Adresse exemptée enregistrée.');

        return Response::redirect(url('admin/system/demo-nda'));
    }

    public function removeBypassIp(Request $request, array $params = []): Response
    {
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée.');

            return Response::redirect(url('admin/system/demo-nda'));
        }
        $ip = trim((string) $request->input('bypass_ip', ''));
        $this->gate->removeAdminBypassIp($ip);
        Session::flash('success', 'Adresse retirée de la liste d’exemption.');

        return Response::redirect(url('admin/system/demo-nda'));
    }

    public function addMyIp(Request $request, array $params = []): Response
    {
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée.');

            return Response::redirect(url('admin/system/demo-nda'));
        }
        $ip = $this->gate->clientIp();
        if (!$this->gate->addAdminBypassIp($ip)) {
            Session::flash('error', 'Impossible d’enregistrer votre adresse actuelle.');

            return Response::redirect(url('admin/system/demo-nda'));
        }
        Session::flash('success', 'Votre adresse actuelle a été exemptée.');

        return Response::redirect(url('admin/system/demo-nda'));
    }

    public function resetVisit(Request $request, array $params = []): Response
    {
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée.');

            return Response::redirect(url('admin/system/demo-nda'));
        }
        $id = (int) $request->input('visit_id', 0);
        if (!$this->gate->resetVisit($id)) {
            Session::flash('error', 'Visite introuvable.');

            return Response::redirect(url('admin/system/demo-nda'));
        }
        Session::flash('success', 'Fenêtre d’accès rouverte pour cette connexion.');

        return Response::redirect(url('admin/system/demo-nda'));
    }
}
