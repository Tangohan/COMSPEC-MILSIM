<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\ReferralRepository;

/**
 * Parrainage : lien et code pour inviter une autre unité (récompenses peuvent rester manuelles).
 */
final class ReferralInviteController
{
    public function __construct(
        private ReferralRepository $referralRepository
    ) {}

    public function index(Request $request, array $params = []): Response
    {
        $uid = Session::get('user_id') ? (int) Session::get('user_id') : 0;
        if ($uid < 1) {
            return Response::redirect(url('login'));
        }
        $code = $this->referralRepository->getOrCreateCodeForUser($uid);
        $registerUrl = url('register') . '?ref=' . rawurlencode($code);
        $createUrl = url('communities/create') . '?ref=' . rawurlencode($code);

        return Response::view('layout.main', [
            'title' => 'Inviter une autre unité',
            'content' => 'platform.referral_invite',
            'referral_code' => $code,
            'register_url' => $registerUrl,
            'create_community_url' => $createUrl,
        ]);
    }
}
