<?php

declare(strict_types=1);

namespace App\Controllers\Admin\System;

use App\Core\Request;
use App\Core\Response;
use App\Repositories\LoginAttemptRepository;
use App\Repositories\PlatformLoadRepository;
use App\Services\Watch\WatchState;

final class SystemSecurityController
{
    public function index(Request $request, array $params = []): Response
    {
        $loginSignals = null;
        try {
            $loginSignals = (new LoginAttemptRepository())->securitySignals();
        } catch (\RuntimeException $exception) {
            error_log('Platform security: cannot read login attempts (code ' . $exception->getCode() . ')');
        }

        $now = time();
        $load = new PlatformLoadRepository();
        $peaks = $load->minuteSeries(60, $now);
        usort($peaks, static fn (array $a, array $b): int =>
            [$b['requests'], $b['minute']] <=> [$a['requests'], $a['minute']]
        );
        $state = WatchState::raw();

        return Response::view('layout.main', [
            'title' => 'Surveillance de la sécurité',
            'content' => 'admin.system.security',
            'isPlatformAdminShell' => true,
            'backOfficePageCss' => ['platform-admin.css'],
            'loginSignals' => $loginSignals,
            'trafficPeaks' => array_slice($peaks, 0, 5),
            'trafficRoutes' => $load->topRoutes(15, $now, 5),
            'trafficSensorAge' => WatchState::ageSeconds($state, $now),
        ])->header('Cache-Control', 'private, no-store');
    }
}
