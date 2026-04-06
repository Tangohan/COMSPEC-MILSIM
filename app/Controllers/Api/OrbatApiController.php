<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Csrf;
use App\Core\Gate;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\UnitRepository;
use App\Repositories\UserRepository;
use App\Support\OrbatRosterPayload;

/**
 * ORBAT : lecture JSON et mise à jour unité pour gérants de communauté.
 */
final class OrbatApiController
{
    private const ORBAT_TYPES = ['command', 'alpha', 'bravo', 'support', 'special'];

    public function __construct(
        private UnitRepository $unitRepository,
        private UserRepository $userRepository
    ) {}

    public function roster(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        if ($tenantId < 1 || !Session::get('user_id')) {
            return Response::json(['success' => false, 'message' => 'Authentification requise'], 401);
        }

        $payload = OrbatRosterPayload::buildForTenant($this->unitRepository, $tenantId);

        return Response::json([
            'success' => true,
            'roster' => $payload,
        ]);
    }

    public function updateUnit(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        $userId = (int) Session::get('user_id');
        if ($tenantId < 1 || $userId < 1) {
            return Response::json(['success' => false, 'message' => 'Authentification requise'], 401);
        }

        $gate = Gate::getInstance();
        if (!$gate->allows('admin.organization') && !$gate->allows('admin.access')) {
            return Response::json(['success' => false, 'message' => 'Droits insuffisants'], 403);
        }

        if (!Csrf::validate($request->input('_csrf_token'))) {
            return Response::json(['success' => false, 'message' => 'Session expirée, rechargez la page'], 403);
        }

        $unitId = (int) $request->input('unit_id', 0);
        if ($unitId < 1) {
            return Response::json(['success' => false, 'message' => 'Unité non valide'], 400);
        }

        $unit = $this->unitRepository->findById($unitId, $tenantId);
        if (!$unit) {
            return Response::json(['success' => false, 'message' => 'Unité introuvable'], 404);
        }

        $data = [];
        if ($request->input('name') !== null) {
            $name = trim((string) $request->input('name', ''));
            if ($name === '') {
                return Response::json(['success' => false, 'message' => 'Le nom de l’unité est requis'], 400);
            }
            if (mb_strlen($name) > 255) {
                return Response::json(['success' => false, 'message' => 'Nom trop long'], 400);
            }
            $data['name'] = $name;
        }

        if ($request->input('code') !== null) {
            $code = trim((string) $request->input('code', ''));
            $data['code'] = $code === '' ? null : mb_substr($code, 0, 20);
        }

        if ($request->input('public_blurb') !== null) {
            $blurb = trim((string) $request->input('public_blurb', ''));
            $data['public_blurb'] = $blurb === '' ? null : mb_substr($blurb, 0, 8000);
        }

        if ($request->input('orbat_type') !== null) {
            $t = strtolower(trim((string) $request->input('orbat_type', '')));
            if (!in_array($t, self::ORBAT_TYPES, true)) {
                return Response::json(['success' => false, 'message' => 'Type d’affichage non reconnu'], 400);
            }
            $data['type'] = $t;
        }

        if ($request->input('commander_user_id') !== null) {
            $raw = $request->input('commander_user_id');
            if ($raw === '' || $raw === null) {
                $data['commander_user_id'] = null;
            } else {
                $cid = (int) $raw;
                if ($cid < 1) {
                    $data['commander_user_id'] = null;
                } else {
                    $cmd = $this->userRepository->findById($cid, $tenantId);
                    if (!$cmd || (string) ($cmd['status'] ?? '') !== 'active') {
                        return Response::json(['success' => false, 'message' => 'Chef d’unité introuvable ou compte inactif'], 400);
                    }
                    $data['commander_user_id'] = $cid;
                }
            }
        }

        if ($data === []) {
            return Response::json(['success' => true, 'message' => null]);
        }

        $this->unitRepository->update($unitId, $tenantId, $data);

        return Response::json([
            'success' => true,
            'roster' => OrbatRosterPayload::buildForTenant($this->unitRepository, $tenantId),
        ]);
    }
}
