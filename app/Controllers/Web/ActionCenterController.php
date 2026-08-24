<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Gate;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\UserRepository;
use App\Services\Portal\UnifiedActionDigestService;

final class ActionCenterController
{
    public function __construct(
        private UnifiedActionDigestService $digest,
        private UserRepository $userRepository,
    ) {}

    public function index(Request $request, array $params = []): Response
    {
        $tenantId = (int) (Session::get('tenant_id') ?? 0);
        $userId = (int) (Session::get('user_id') ?? 0);
        if ($tenantId < 1 || $userId < 1) {
            return Response::redirect(url('login'));
        }
        $gate = Gate::getInstance();
        $userRow = $this->userRepository->findById($userId);
        $email = trim((string) ($userRow['email'] ?? Session::get('email') ?? ''));

        $roleSlug = $this->userRepository->getRoleSlugForUser($userId) ?? '';
        $staffSlugs = ['recruiter', 'community_owner', 'hr', 'tenant_admin'];
        $showStaffRecruitment = $gate->allows('admin.organization') || $gate->allows('admin.access')
            || in_array($roleSlug, $staffSlugs, true);

        $digestPayload = $this->digest->buildActionCenter(
            $tenantId,
            $userId,
            $email,
            $gate,
            $showStaffRecruitment
        );

        return Response::view('layout.main', [
            'title' => 'Aujourd’hui — Athena',
            'content' => 'portal.action_center',
            'action_center_digest' => $digestPayload,
        ]);
    }
}
