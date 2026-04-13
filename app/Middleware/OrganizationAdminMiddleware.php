<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Support\NonDefaultTenantContextGuard;

class OrganizationAdminMiddleware
{
    public function __invoke(Request $request, callable $next): Response
    {
        if (!Session::get('user_id')) {
            Session::flash('error', 'Authentification requise.');
            return Response::redirect(url('login'));
        }
        $gate = \App\Core\Gate::getInstance();
        $path = $request->path();
        $scopedOrgAccess = $gate->allows('admin.organization') || $gate->allows('admin.access')
            || $gate->allows('site.support');
        if (!$scopedOrgAccess) {
            if (str_starts_with($path, '/back-office/recruitments') && $gate->allows('organization.recruitment.manage')) {
                $scopedOrgAccess = true;
            } elseif (str_starts_with($path, '/back-office/recruitment/offers') && ($gate->allows('organization.recruitment.openings.manage') || $gate->allows('organization.recruitment.manage'))) {
                $scopedOrgAccess = true;
            } elseif (str_starts_with($path, '/back-office/recruitment/reference-format') && ($gate->allows('organization.recruitment.openings.manage') || $gate->allows('organization.recruitment.manage'))) {
                $scopedOrgAccess = true;
            } elseif (str_starts_with($path, '/back-office/organisation-effectifs') && $gate->allows('organization.effectifs.hub.view')) {
                $scopedOrgAccess = true;
            } elseif (str_starts_with($path, '/back-office/positions') && $gate->allows('organization.job_roles.referential.manage')) {
                $scopedOrgAccess = true;
            } elseif (str_starts_with($path, '/back-office/communications') && (
                $gate->allows('comms.email.send.orbat')
                || $gate->allows('comms.email.send.mission')
                || $gate->allows('comms.email.send.activity')
                || $gate->allows('comms.email.send.custom')
                || $gate->allows('comms.email.broadcast')
                || $gate->allows('comms.email_templates.manage')
                || $gate->allows('comms.notifications.history.view')
            )) {
                $scopedOrgAccess = true;
            }
        }
        if (!$scopedOrgAccess) {
            Session::flash('error', 'Accès réservé aux administrateurs organisationnels.');
            if ($gate->allows('admin.system')) {
                return Response::redirect(url('admin'));
            }
            return Response::redirect(url('dashboard'));
        }

        $blocked = NonDefaultTenantContextGuard::redirectIfInvalid();
        if ($blocked !== null) {
            return $blocked;
        }

        return $next($request);
    }
}
