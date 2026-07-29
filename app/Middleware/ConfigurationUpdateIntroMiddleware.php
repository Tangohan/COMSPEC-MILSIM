<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Container;
use App\Core\Gate;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Services\ConfigurationUpdate\ConfigurationUpdateService;

/**
 * Affiche une seule fois la page « Nouveautés » après connexion admin
 * lorsqu’il reste des configurations actionnables. Non bloquant.
 */
final class ConfigurationUpdateIntroMiddleware
{
    public function __invoke(Request $request, callable $next): Response
    {
        if (!Session::get('user_id') || !Session::get('tenant_id')) {
            return $next($request);
        }

        $path = $request->path();
        if (
            str_starts_with($path, '/back-office/nouveautes-organisation')
            || str_starts_with($path, '/back-office/mise-a-niveau')
            || str_starts_with($path, '/login')
            || str_starts_with($path, '/logout')
            || str_starts_with($path, '/assets/')
            || $request->method() !== 'GET'
        ) {
            return $next($request);
        }

        // Uniquement sur le back-office / tableau de bord org — pas sur tout le site.
        if (!str_starts_with($path, '/back-office') && $path !== '/dashboard') {
            return $next($request);
        }

        if (Session::get('configuration_updates_intro_shown')) {
            return $next($request);
        }

        $gate = Gate::getInstance();
        $canSee = $gate->allows('admin.organization')
            || $gate->allows('admin.access')
            || $gate->allows('tenant.configuration.manage')
            || $gate->allows('admin.settings.manage')
            || $gate->allows('site.support');
        if (!$canSee) {
            return $next($request);
        }

        try {
            /** @var ConfigurationUpdateService $svc */
            $svc = Container::get(ConfigurationUpdateService::class);
            if (!$svc->isAvailable()) {
                return $next($request);
            }
            $tenantId = (int) Session::get('tenant_id');
            $summary = $svc->hubSummary($tenantId);
            if (empty($summary['show_intro']) || (int) ($summary['counts']['actionable'] ?? 0) === 0) {
                Session::set('configuration_updates_intro_shown', true);

                return $next($request);
            }
        } catch (\Throwable) {
            return $next($request);
        }

        Session::set('configuration_updates_intro_shown', true);

        return Response::redirect(url('back-office/nouveautes-organisation'));
    }
}
