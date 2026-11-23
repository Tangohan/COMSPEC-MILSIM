<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Container;
use App\Core\Csrf;
use App\Core\Gate;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\TenantRepository;
use App\Services\Community\TenantTypeConfig;
use App\Services\Effectifs\EffectifsDeskPayload;
use App\Services\Effectifs\NoAccountDirectory;
use App\Support\EffectifsLmsAccess;

/**
 * Bureau des effectifs en vue dédiée plein écran (modèle « Bureau effectifs — mode tableur »).
 * Lecture seule : la page et ses données (GET). Les écritures passent par les routes existantes de l'espace Effectifs
 * (invitations, import), avec leurs propres contrôles de droits et leur jeton CSRF.
 * Réservé aux profils « Bureau des effectifs » et « Complet » (TenantTypeConfig::rosterBureauMode) ; les autres
 * retombent sur la liste des membres classique. Tout est cloisonné par la communauté de la session.
 */
final class EffectifsDeskController
{
    public function index(Request $request, array $params = []): Response
    {
        $denied = $this->deny();
        if ($denied !== null) {
            return $denied;
        }
        $tenantId = (int) Session::get('tenant_id');
        $tenant = Container::get(TenantRepository::class)->findById($tenantId) ?: [];
        $gate = Gate::getInstance();
        $name = function_exists('community_display_name') ? community_display_name($tenant) : (string) ($tenant['name'] ?? '');

        return Response::view('admin.effectifs_desk.page', [
            'title' => 'Bureau des effectifs',
            'deskCommunity' => $name !== '' ? $name : 'Communauté',
            'deskCsrf' => Csrf::token(),
            'deskCanImport' => EffectifsLmsAccess::canEditProfiles($gate),
            'deskCanCreate' => EffectifsLmsAccess::canCreateMembers($gate),
            'deskCanInvite' => EffectifsLmsAccess::canInviteMembers($gate),
            'deskCanPdf' => EffectifsWorkspaceController::pdfExportsAllowed($tenantId),
            'deskFlashOk' => (string) Session::getFlash('success', ''),
            'deskFlashError' => (string) Session::getFlash('error', ''),
        ]);
    }

    public function data(Request $request, array $params = []): Response
    {
        $denied = $this->deny(true);
        if ($denied !== null) {
            return $denied;
        }
        $tenantId = (int) Session::get('tenant_id');
        $viewerId = (int) Session::get('user_id');
        $roster = Container::get(EffectifsWorkspaceController::class)->deskRoster($tenantId);
        $noAccount = [];
        try {
            $noAccount = (new NoAccountDirectory())->list($tenantId, true);
        } catch (\Throwable $e) {
            error_log('Bureau des effectifs : fiches sans compte indisponibles : ' . $e->getMessage());
        }
        $members = EffectifsDeskPayload::members($roster['rows'], $roster['identities'], $noAccount);
        $viewerUnit = 0;
        foreach ($members as $m) {
            if ($m['id'] === $viewerId) {
                $viewerUnit = (int) $m['unitId'];
                break;
            }
        }

        return Response::json([
            'ok' => true,
            'syncedAt' => date('c'),
            'truncated' => $roster['truncated'],
            'viewerUnitId' => $viewerUnit,
            'members' => $members,
            'units' => EffectifsDeskPayload::units($roster['units'], $roster['unit_codes'], $roster['commanders']),
        ]);
    }

    /** Même garde que la liste des membres, plus le profil de la communauté. */
    private function deny(bool $json = false): ?Response
    {
        if (!(int) Session::get('user_id') || !(int) Session::get('tenant_id')) {
            return $json ? Response::json(['ok' => false, 'message' => 'Connexion requise.'], 401) : Response::redirect(url('login'));
        }
        if (!EffectifsLmsAccess::allows(Gate::getInstance())) {
            if ($json) {
                return Response::json(['ok' => false, 'message' => 'Accès réservé au pilotage des effectifs.'], 403);
            }
            Session::flash('error', 'Accès réservé au pilotage des effectifs.');

            return Response::redirect(url('dashboard'));
        }
        $tenant = Container::get(TenantRepository::class)->findById((int) Session::get('tenant_id'));
        if (!TenantTypeConfig::rosterBureauMode((string) ($tenant['tenant_type'] ?? 'full'))) {
            return $json ? Response::json(['ok' => false, 'message' => 'Vue réservée aux profils « Bureau des effectifs » et « Complet ».'], 403) : Response::redirect(effectifs_workspace_url());
        }

        return null;
    }
}
