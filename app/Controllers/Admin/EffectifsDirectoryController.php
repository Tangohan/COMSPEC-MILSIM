<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Csrf;
use App\Core\Gate;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\UnitRepository;
use App\Repositories\UserRepository;
use App\Services\Effectifs\EffectifsSearchService;
use App\Services\Effectifs\UnitCodeService;
use App\Support\EffectifsLmsAccess;
use Throwable;

/**
 * Effectifs : recherche rapide (Ctrl+K) et réglage des codes unité.
 */
class EffectifsDirectoryController
{
    public function __construct(
        private ?UserRepository $users = null,
        private ?UnitRepository $units = null,
        private ?UnitCodeService $codes = null,
        private ?EffectifsSearchService $search = null,
    ) {
        $this->users ??= new UserRepository();
        $this->units ??= new UnitRepository();
        $this->codes ??= new UnitCodeService();
    }

    /** Recherche des effectifs de la communauté courante (réponse lue par la fenêtre « Rechercher »). */
    public function search(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        if (!(int) Session::get('user_id') || $tenantId < 1) {
            return Response::json(['ok' => false, 'message' => 'Reconnectez-vous pour rechercher.'], 401);
        }
        if (!EffectifsLmsAccess::allows(Gate::getInstance())) {
            return Response::json(['ok' => false, 'message' => 'Recherche réservée au pilotage des effectifs.'], 403);
        }
        $q = (string) $request->query('q', '');
        $service = $this->search ?? new EffectifsSearchService($this->users, $this->codes);
        $result = $service->search($tenantId, $q);
        foreach ($result['members'] as &$m) {
            $m['url'] = effectifs_workspace_url('membres/' . (int) $m['id']);
        }
        unset($m);
        foreach ($result['units'] as &$u) {
            $u['url'] = effectifs_workspace_url('affectations') . '?unite=' . (int) $u['id'] . '#eff-aff-units';
        }
        unset($u);
        foreach ($result['jobs'] as &$j) {
            $j['url'] = effectifs_workspace_url('affectations') . '?q=' . rawurlencode((string) $j['name']);
        }
        unset($j);

        return Response::json(['ok' => true] + $result)
            ->header('Cache-Control', 'no-store');
    }

    /** Code de la communauté (SOAR) : réservé aux administrateurs de la communauté. */
    public function saveCommunityCode(Request $request, array $params = []): Response
    {
        $back = effectifs_workspace_url('affectations') . '?vue=unites#eff-aff-codes';
        $denied = $this->guardPost($request, $back);
        if ($denied !== null) {
            return $denied;
        }
        $gate = Gate::getInstance();
        if (!$gate->allows('admin.organization') && !$gate->allows('admin.access')) {
            Session::flash('error', 'Seuls les administrateurs de la communauté peuvent changer son code.');

            return Response::redirect($back);
        }
        $tenantId = (int) Session::get('tenant_id');
        $before = (string) ($this->codes->sync($tenantId)['community']['code'] ?? '');
        $error = $this->codes->changeDisplayPrefix($tenantId, (string) $request->input('community_code', ''));
        if ($error !== null) {
            Session::flash('error', $error);

            return Response::redirect($back);
        }
        $after = (string) ($this->codes->sync($tenantId)['community']['code'] ?? '');
        $this->audit('community_unit_code_updated', $tenantId, 'tenant', $tenantId, $before, $after);
        Session::flash('success', 'Code de la communauté enregistré : ' . $after . '. Les codes des unités ont été mis à jour.');

        return Response::redirect($back);
    }

    /** Segment d'une unité (le « B » de SOAR-B-A-1) ; vide = calculé depuis le nom. */
    public function saveUnitSegment(Request $request, array $params = []): Response
    {
        $back = effectifs_workspace_url('affectations') . '?vue=unites#eff-aff-units';
        $denied = $this->guardPost($request, $back);
        if ($denied !== null) {
            return $denied;
        }
        if (!EffectifsLmsAccess::canManageAssignments(Gate::getInstance())) {
            Session::flash('error', 'Vous n’êtes pas habilité à modifier les codes des unités.');

            return Response::redirect($back);
        }
        $tenantId = (int) Session::get('tenant_id');
        $unitId = (int) ($params['id'] ?? 0);
        $unit = $unitId > 0 ? $this->units->findById($unitId, $tenantId) : null;
        if ($unit === null) {
            Session::flash('error', 'Unité introuvable.');

            return Response::redirect($back);
        }
        $normalized = null;
        $error = UnitCodeService::validateSegment($this->units->allForTenant($tenantId), $unitId, (string) $request->input('segment', ''), $normalized);
        if ($error !== null) {
            Session::flash('error', $error);

            return Response::redirect($back);
        }
        $before = $this->codes->forUnit($tenantId, $unitId)['code'] ?? '';
        $this->units->update($unitId, $tenantId, ['code' => $normalized !== '' ? $normalized : null]);
        UnitCodeService::forget($tenantId);
        $after = $this->codes->forUnit($tenantId, $unitId)['code'] ?? '';
        $this->audit('unit_code_updated', $tenantId, 'unit', $unitId, (string) $before, (string) $after);
        Session::flash('success', $normalized !== ''
            ? 'Code de l’unité enregistré : ' . $after . '.'
            : 'Le code de l’unité est de nouveau calculé depuis son nom : ' . $after . '.');

        return Response::redirect($back);
    }

    private function guardPost(Request $request, string $back): ?Response
    {
        if (!(int) Session::get('user_id') || !(int) Session::get('tenant_id')) {
            return Response::redirect(url('login'));
        }
        if (!EffectifsLmsAccess::allows(Gate::getInstance())) {
            Session::flash('error', 'Accès réservé au pilotage des effectifs.');

            return Response::redirect(url('dashboard'));
        }
        if (!Csrf::validate((string) $request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée. Rechargez la page, puis enregistrez de nouveau.');

            return Response::redirect($back);
        }

        return null;
    }

    private function audit(string $action, int $tenantId, string $entity, int $entityId, string $before, string $after): void
    {
        try {
            \App\Core\Container::get(\App\Services\Audit\AuditService::class)
                ->log($action, $tenantId, (int) Session::get('user_id'), $entity, $entityId, $before, $after);
        } catch (Throwable) {
        }
    }
}
