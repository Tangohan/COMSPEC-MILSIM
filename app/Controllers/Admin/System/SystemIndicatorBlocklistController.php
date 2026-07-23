<?php

declare(strict_types=1);

namespace App\Controllers\Admin\System;

use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\BlockedIndicatorRepository;
use App\Services\Auth\AuthService;
use App\Services\Moderation\IndicatorBlocklistService;

/**
 * Liste de restriction e-mail / réseau à l’échelle de toute la plateforme.
 */
final class SystemIndicatorBlocklistController
{
    public function __construct(
        private AuthService $authService,
        private IndicatorBlocklistService $indicatorBlocklistService,
        private BlockedIndicatorRepository $blockedIndicatorRepository
    ) {}

    public function index(Request $request, array $params = []): Response
    {
        $rows = $this->blockedIndicatorRepository->listActiveGlobal();

        return Response::view('layout.main', [
            'title' => 'Liste de restriction (plateforme)',
            'content' => 'admin.system.indicator_blocklist',
            'blocklistRows' => $rows,
        ]);
    }

    public function add(Request $request, array $params = []): Response
    {
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée.');

            return Response::redirect(url('admin/system/blocklist'));
        }
        $actor = $this->authService->user();
        if (!$actor) {
            return Response::redirect(url('login'));
        }
        $actorId = (int) $actor['id'];
        $kind = trim((string) $request->input('indicator_kind'));
        $raw = trim((string) $request->input('restriction_target'));
        $reason = trim((string) $request->input('block_reason'));
        $durationMode = $request->input('block_duration_mode') === 'temporary' ? 'temporary' : 'permanent';
        $expiresAt = null;
        if ($durationMode === 'temporary') {
            $days = max(1, (int) $request->input('block_duration_days'));
            $expiresAt = (new \DateTimeImmutable())->modify('+' . $days . ' days');
        }
        try {
            if ($kind === 'email') {
                $this->indicatorBlocklistService->addEmailBlock(
                    $actorId,
                    'global',
                    null,
                    $raw,
                    $reason !== '' ? $reason : null,
                    $expiresAt,
                    null
                );
            } elseif ($kind === 'ip') {
                $this->indicatorBlocklistService->addIpBlock(
                    $actorId,
                    'global',
                    null,
                    $raw,
                    $reason !== '' ? $reason : null,
                    $expiresAt
                );
            } elseif ($kind === 'steam') {
                $this->indicatorBlocklistService->addSteamBlock(
                    $actorId,
                    'global',
                    null,
                    $raw,
                    $reason !== '' ? $reason : null,
                    $expiresAt
                );
            } else {
                Session::flash('error', 'Type d’entrée non reconnu.');

                return Response::redirect(url('admin/system/blocklist'));
            }
            Session::flash('success', 'Entrée globale enregistrée.');
        } catch (\Throwable $e) {
            Session::flash('error', $e->getMessage());
        }

        return Response::redirect(url('admin/system/blocklist'));
    }

    public function revoke(Request $request, array $params = []): Response
    {
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée.');

            return Response::redirect(url('admin/system/blocklist'));
        }
        $actor = $this->authService->user();
        if (!$actor) {
            return Response::redirect(url('login'));
        }
        $id = (int) $request->input('indicator_id');
        if ($this->indicatorBlocklistService->revokeIndicator((int) $actor['id'], $id, null)) {
            Session::flash('success', 'Entrée levée.');
        } else {
            Session::flash('error', 'Entrée introuvable ou déjà levée.');
        }

        return Response::redirect(url('admin/system/blocklist'));
    }
}
