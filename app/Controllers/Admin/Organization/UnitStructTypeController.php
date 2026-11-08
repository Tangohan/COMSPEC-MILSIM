<?php

declare(strict_types=1);

namespace App\Controllers\Admin\Organization;

use App\Core\Container;
use App\Core\Csrf;
use App\Core\Gate;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\UnitStructTypeRepository;
use App\Services\Auth\AuthService;
use App\Support\UnitStructTypes;

/**
 * Back-office > Types d'entité : la communauté crée, renomme et supprime (si aucune unité ne l'utilise)
 * ses propres types, proposés ensuite à la création et à la modification d'une unité.
 */
final class UnitStructTypeController
{
    private const URL = 'back-office/organisation/types-entite';

    public function __construct(
        private ?AuthService $authService = null,
        private ?UnitStructTypeRepository $types = null,
        private ?Gate $gate = null,
    ) {
        $this->authService ??= Container::get(AuthService::class);
        $this->types ??= new UnitStructTypeRepository();
        $this->gate ??= Gate::getInstance();
    }

    public function index(Request $request, array $params = []): Response
    {
        $denied = $this->guard();
        if ($denied !== null) {
            return $denied;
        }
        $tenantId = (int) Session::get('tenant_id');
        $rows = [];
        foreach (UnitStructTypes::forTenant($tenantId) as $row) {
            $row['used'] = $this->types->countUnitsUsing($tenantId, $row['id']);
            $rows[] = $row;
        }

        return Response::view('layout.main', [
            'isBackOfficeShell' => true,
            'boPageGroup' => 'Personnel',
            'title' => __('unit_types.title'),
            'boPageTitle' => __('unit_types.title'),
            'boPageKicker' => 'PERSONNEL · STRUCTURE',
            'boPageSubtitle' => __('unit_types.subtitle'),
            'backOfficePageCss' => ['back-office-catalog.css'],
            'showPortalFooter' => false,
            'content' => 'admin.organization.unit_types',
            'typeRows' => $rows,
            'typesReady' => $this->types->tableExists(),
            'flashOk' => Session::getFlash('success'),
            'flashErr' => Session::getFlash('error'),
        ]);
    }

    public function save(Request $request, array $params = []): Response
    {
        $denied = $this->guard();
        if ($denied !== null) {
            return $denied;
        }
        $back = Response::redirect(url(self::URL));
        if (!Csrf::validate((string) $request->input('_csrf_token'))) {
            Session::flash('error', __('unit_types.expired'));

            return $back;
        }
        if (!$this->types->tableExists()) {
            Session::flash('error', __('unit_types.not_ready'));

            return $back;
        }
        $tenantId = (int) Session::get('tenant_id');
        $action = strtolower(trim((string) $request->input('action', '')));
        $label = trim(preg_replace('/\s+/u', ' ', (string) $request->input('label', '')) ?? '');
        $slug = strtolower(trim((string) $request->input('slug', '')));

        if ($action === 'create') {
            if ($label === '' || mb_strlen($label) > 120) {
                Session::flash('error', __('unit_types.need_name'));

                return $back;
            }
            foreach (UnitStructTypes::forTenant($tenantId) as $row) {
                if (mb_strtolower($row['label']) === mb_strtolower($label)) {
                    Session::flash('error', __('unit_types.duplicate'));

                    return $back;
                }
            }
            $newSlug = UnitStructTypes::slugFromLabel($label);
            if ($this->types->exists($tenantId, $newSlug) || !$this->types->create($tenantId, $newSlug, $label)) {
                Session::flash('error', __('unit_types.duplicate'));

                return $back;
            }
            Session::flash('success', __('unit_types.created', ['name' => $label]));

            return $back;
        }

        if (!str_starts_with($slug, UnitStructTypes::CUSTOM_PREFIX) || !$this->types->exists($tenantId, $slug)) {
            Session::flash('error', __('unit_types.not_found'));

            return $back;
        }
        if ($action === 'rename') {
            if ($label === '' || mb_strlen($label) > 120) {
                Session::flash('error', __('unit_types.need_name'));

                return $back;
            }
            $this->types->rename($tenantId, $slug, $label);
            Session::flash('success', __('unit_types.renamed', ['name' => $label]));

            return $back;
        }
        if ($action === 'delete') {
            if ($this->types->countUnitsUsing($tenantId, $slug) > 0) {
                Session::flash('error', __('unit_types.in_use'));

                return $back;
            }
            $this->types->delete($tenantId, $slug);
            Session::flash('success', __('unit_types.deleted'));

            return $back;
        }
        Session::flash('error', __('unit_types.not_found'));

        return $back;
    }

    private function guard(): ?Response
    {
        if (!$this->authService->check()) {
            return Response::redirect(url('login'));
        }
        if ((int) Session::get('tenant_id') < 1) {
            return Response::redirect(url('dashboard'));
        }
        if (!($this->gate->allows('organization.catalog.manage')
            || $this->gate->allows('organization.orbat.manage')
            || $this->gate->allows('admin.organization')
            || $this->gate->allows('admin.access'))) {
            Session::flash('error', __('unit_types.denied'));

            return Response::redirect(url('back-office'));
        }

        return null;
    }
}
