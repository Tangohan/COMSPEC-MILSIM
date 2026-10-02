<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\InterteamMissionRepository;
use App\Repositories\TenantRepository;
use App\Repositories\UserRepository;
use App\Services\Cooperation\CooperationTransitionRules;
use App\Support\CooperationAccess;
use App\Support\CooperationDictionary;

/**
 * Recherche d’unités et de membres pour les sélecteurs du module coopération (combobox).
 *
 * Réponses JSON volontairement minimales (aucune adresse e-mail, aucun champ interne) et
 * limitées au périmètre de l’unité connectée : les unités invitables pour le pilotage, et les
 * seuls membres de sa propre unité.
 */
final class CooperationSearchApiController
{
    private const MAX_RESULTS = 15;

    public function __construct(
        private InterteamMissionRepository $missions,
        private TenantRepository $tenants,
        private UserRepository $users
    ) {}

    /**
     * GET /back-office/cooperation/api/tenants/search?q=&mission_id=
     */
    public function tenants(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        if ($tenantId <= 0 || (int) Session::get('user_id') <= 0) {
            return Response::json(['error' => 'unauthenticated'], 401);
        }
        $missionId = (int) $request->query('mission_id', 0);
        $participants = [];
        if ($missionId > 0) {
            // Inviter sur une mission existante : réservé au pilotage de cette mission.
            if (!CooperationAccess::canManage() || !$this->missions->tenantCanPilotMission($missionId, $tenantId)) {
                return Response::json(['error' => 'forbidden'], 403);
            }
            $participants = $this->missions->listParticipants($missionId);
        } elseif (!CooperationAccess::canCreate()) {
            return Response::json(['error' => 'forbidden'], 403);
        }

        $q = mb_substr(trim((string) $request->query('q', '')), 0, 80);
        if (mb_strlen($q) < 2) {
            return Response::json(['query' => $q, 'results' => [], 'hint' => 'Saisissez au moins 2 caractères.']);
        }
        $rows = $this->tenants->searchBasicExcluding($q, $tenantId, self::MAX_RESULTS);
        $results = [];
        foreach ($rows as $t) {
            $state = '';
            $p = CooperationTransitionRules::participantFor((int) $t['id'], $participants);
            if ($p !== null) {
                $state = (string) ($p['status'] ?? '');
            }
            $check = CooperationTransitionRules::canInviteTenant((int) $t['id'], $tenantId, $participants);
            $results[] = [
                'id' => (int) $t['id'],
                'name' => (string) $t['name'],
                'type' => match ((string) ($t['tenant_type'] ?? '')) {
                    'effectifs' => 'Gestion des effectifs',
                    'atak' => 'ATAK',
                    'full', '' => 'Communauté',
                    default => 'Communauté',
                },
                'logo' => $this->safeLogo($t['logo_url'] ?? null),
                'selectable' => $check['allowed'],
                'state' => $state !== '' ? CooperationDictionary::participantStateLabel($state) : '',
            ];
        }

        return $this->noStore(Response::json(['query' => $q, 'results' => $results]));
    }

    /**
     * GET /back-office/cooperation/api/members/search?q=&mission_id=
     */
    public function members(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        if ($tenantId <= 0 || (int) Session::get('user_id') <= 0) {
            return Response::json(['error' => 'unauthenticated'], 401);
        }
        $missionId = (int) $request->query('mission_id', 0);
        if ($missionId <= 0 || !CooperationAccess::canManage() || !$this->missions->tenantCanPilotMission($missionId, $tenantId)) {
            return Response::json(['error' => 'forbidden'], 403);
        }
        $q = mb_substr(trim((string) $request->query('q', '')), 0, 80);
        if (mb_strlen($q) < 2) {
            return Response::json(['query' => $q, 'results' => [], 'hint' => 'Saisissez au moins 2 caractères.']);
        }
        $rows = $this->users->listForTenant($tenantId, $q, 'active', null, self::MAX_RESULTS);
        $results = [];
        foreach ($rows as $u) {
            $name = trim((string) ($u['display_name'] ?? ''));
            if ($name === '') {
                continue;
            }
            $callsign = trim((string) ($u['callsign'] ?? ''));
            $results[] = [
                'id' => (int) ($u['id'] ?? 0),
                'name' => $name,
                'detail' => $callsign !== '' ? 'Indicatif ' . $callsign : '',
            ];
        }

        return $this->noStore(Response::json(['query' => $q, 'results' => $results]));
    }

    private function safeLogo(mixed $raw): ?string
    {
        $s = trim((string) $raw);
        if ($s === '') {
            return null;
        }
        if (str_starts_with($s, 'https://') || str_starts_with($s, '/')) {
            return $s;
        }

        return function_exists('asset_url') ? asset_url(ltrim($s, '/')) : null;
    }

    private function noStore(Response $response): Response
    {
        $response->header('Cache-Control', 'no-store');

        return $response;
    }
}
