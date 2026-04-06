<?php

declare(strict_types=1);

namespace App\Controllers\Admin\System;

use App\Core\Request;
use App\Core\Response;
use App\Repositories\UserRepository;

/**
 * Suggestions de comptes pour les écrans super-admin (ex. liste de restriction plateforme).
 */
final class SystemUserLookupApiController
{
    public function __construct(
        private UserRepository $userRepository
    ) {}

    public function search(Request $request, array $params = []): Response
    {
        $rows = $this->userRepository->searchAccountsForPlatformOperator(
            (string) $request->query('q', ''),
            20
        );
        $users = [];
        foreach ($rows as $u) {
            $status = (string) ($u['status'] ?? '');
            $stateLabel = match ($status) {
                'active' => 'Compte actif',
                'inactive' => 'Compte inactif',
                'pending_verification' => 'En attente de vérification de l’e-mail',
                default => '—',
            };
            $users[] = [
                'id' => (int) ($u['id'] ?? 0),
                'email' => (string) ($u['email'] ?? ''),
                'display_name' => trim((string) ($u['display_name'] ?? '')),
                'callsign' => trim((string) ($u['callsign'] ?? '')),
                'community' => trim((string) ($u['tenant_name'] ?? '')),
                'account_state' => $stateLabel,
            ];
        }

        return Response::json(['users' => $users]);
    }
}
