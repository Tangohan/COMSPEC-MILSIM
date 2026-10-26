<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Services\Navigation\ExternalLeaveService;

/**
 * Interstitiel « vous quittez le site » avant ouverture d’un lien externe.
 */
final class ExternalLeaveController
{
    public function __construct(
        private ExternalLeaveService $leaveService
    ) {}

    public function show(Request $request, array $params = []): Response
    {
        if (!Session::get('user_id')) {
            return Response::redirect(url('login'));
        }
        $u = (string) $request->query('u', '');
        $exp = (string) $request->query('exp', '');
        $sig = (string) $request->query('sig', '');
        $verified = $this->leaveService->verifySignedRequest($u, $exp, $sig);
        if ($verified === null) {
            return Response::view('layout.leave', [
                'content' => 'leave.invalid',
                'title' => 'Lien invalide',
            ])->setStatusCode(400);
        }
        $target = $verified['url'];
        $parts = parse_url($target);
        $domain = is_array($parts) && !empty($parts['host'])
            ? strtoupper((string) $parts['host'])
            : '';
        $isHttps = is_array($parts) && (($parts['scheme'] ?? '') === 'https');

        $displayName = trim((string) Session::get('display_name', ''));
        if ($displayName === '') {
            $displayName = trim((string) Session::get('email', ''));
        }

        return Response::view('layout.leave', [
            'content' => 'leave.page',
            'title' => 'Lien externe',
            'leaveTargetUrl' => $target,
            'leaveDomain' => $domain,
            'leaveIsHttps' => $isHttps,
            'leaveUserDisplayName' => $displayName !== '' ? $displayName : 'membre',
            'leaveCountdown' => 5,
        ]);
    }
}
