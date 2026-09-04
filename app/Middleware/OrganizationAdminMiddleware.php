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
            Session::flash('error', 'Connectez-vous pour continuer.');
            return Response::redirect(url('login'));
        }
        $gate = \App\Core\Gate::getInstance();
        $path = $request->path();
        $scopedOrgAccess = $gate->allows('admin.organization') || $gate->allows('admin.access')
            || $gate->allows('site.support');
        // La racine du back-office contient aussi la synthèse personnelle en lecture seule.
        // Tous les membres authentifiés peuvent la consulter ; les sous-routes restent
        // strictement protégées par les permissions ci-dessous.
        if ($path === '/back-office') {
            $scopedOrgAccess = true;
        }
        if (!$scopedOrgAccess) {
            if (str_starts_with($path, '/api/back-office/search') && (
                $gate->allows('organization.effectifs.hub.view')
                || $gate->allows('personnel.profile.update')
                || $gate->allows('personnel.assignments.manage')
                || $gate->allows('personnel.grades.manage')
                || $gate->allows('personnel.status.manage')
            )) {
                $scopedOrgAccess = true;
            } elseif (str_starts_with($path, '/back-office/ressources/recrutement') && $gate->allows('organization.recruitment.manage')) {
                $scopedOrgAccess = true;
            } elseif (str_starts_with($path, '/back-office/recruitments') && $gate->allows('organization.recruitment.manage')) {
                $scopedOrgAccess = true;
            } elseif (str_starts_with($path, '/back-office/recruitment/offers') && ($gate->allows('organization.recruitment.openings.manage') || $gate->allows('organization.recruitment.manage'))) {
                $scopedOrgAccess = true;
            } elseif (str_starts_with($path, '/back-office/recruitment/reference-format') && ($gate->allows('organization.recruitment.openings.manage') || $gate->allows('organization.recruitment.manage'))) {
                $scopedOrgAccess = true;
            } elseif (str_starts_with($path, '/back-office/organisation-effectifs') && $gate->allows('organization.effectifs.hub.view')) {
                $scopedOrgAccess = true;
            } elseif (str_starts_with($path, '/back-office/ressources/effectifs') && (
                // Garder en phase avec EffectifsLmsAccess::allows() — personnel.profile.view exclu
                // volontairement (voir ce fichier pour le pourquoi).
                $gate->allows('organization.effectifs.hub.view')
                || $gate->allows('personnel.profile.update')
                || $gate->allows('personnel.assignments.manage')
                || $gate->allows('personnel.grades.manage')
                || $gate->allows('personnel.status.manage')
            )) {
                $scopedOrgAccess = true;
            } elseif (str_starts_with($path, '/back-office/organisation/progression') && (
                $gate->allows('personnel.progression.view')
                || $gate->allows('personnel.progression.manage')
                || $gate->allows('personnel.progression.configure')
            )) {
                $scopedOrgAccess = true;
            } elseif (str_starts_with($path, '/back-office/organisation/matricules') && (
                $gate->allows('personnel.member_number.manage')
                || $gate->allows('admin.members.manage')
            )) {
                $scopedOrgAccess = true;
            } elseif (str_starts_with($path, '/back-office/organisation/indicatifs') && $gate->allows('personnel.callsign.manage')) {
                $scopedOrgAccess = true;
            } elseif (str_starts_with($path, '/back-office/organisation/catalogue') && (
                $gate->allows('organization.catalog.manage')
                || $gate->allows('organization.orbat.manage')
            )) {
                $scopedOrgAccess = true;
            } elseif (str_starts_with($path, '/back-office/positions') && (
                $gate->allows('organization.job_roles.referential.manage')
                || $gate->allows('admin.roles.manage')
            )) {
                $scopedOrgAccess = true;
            } elseif (str_starts_with($path, '/back-office/audit') && $gate->allows('admin.audit.view')) {
                $scopedOrgAccess = true;
            } elseif (str_starts_with($path, '/back-office/conformite') && $gate->allows('admin.compliance.export')) {
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
            } elseif (str_starts_with($path, '/back-office/security-indicators') && (
                $gate->allows('organization.recruitment.manage')
                || $gate->allows('admin.members.moderate')
            )) {
                $scopedOrgAccess = true;
            } elseif (str_starts_with($path, '/back-office/media') && (
                $gate->allows('media.view')
                || $gate->allows('media.upload')
                || $gate->allows('media.collections.manage')
                || $gate->allows('media.publish')
                || $gate->allows('media.manage')
            )) {
                $scopedOrgAccess = true;
            } elseif (str_starts_with($path, '/back-office/integration-membres') && (
                $gate->allows('member_integration.view')
                || $gate->allows('member_integration.manage')
                || $gate->allows('member_integration.assign')
                || $gate->allows('member_integration.note')
                || $gate->allows('member_integration.template_manage')
            )) {
                $scopedOrgAccess = true;
            } elseif (str_starts_with($path, '/back-office/onboarding-members') && (
                $gate->allows('member_integration.view')
                || $gate->allows('member_integration.manage')
                || $gate->allows('member_integration.assign')
                || $gate->allows('member_integration.note')
                || $gate->allows('member_integration.template_manage')
            )) {
                $scopedOrgAccess = true;
            } elseif (str_starts_with($path, '/back-office/roles-permissions') && (
                $gate->allows('admin.roles.manage')
                || $gate->allows('admin.permissions.manage')
            )) {
                $scopedOrgAccess = true;
            }
        }
        if (!$scopedOrgAccess) {
            Session::flash('error', 'Cette zone est réservée aux personnes habilitées à administrer la communauté. Si vous pensez devoir y accéder, contactez un administrateur.');
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
