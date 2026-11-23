<?php

declare(strict_types=1);

namespace App\Controllers\Admin\System;

use App\Core\Request;
use App\Core\Response;
use App\Repositories\TenantRepository;
use App\Repositories\UserRepository;

/**
 * Recherche globale de l'administration du site : comptes (e-mail, pseudo, Steam, indicatif, numéro)
 * et communautés (nom, adresse courte, code, numéro), avec les actions rapides de chaque fiche.
 * Route protégée par SystemAdminMiddleware.
 */
final class SystemAdminSearchController
{
    public const MIN_LENGTH = 2;

    public function __construct(
        private UserRepository $users,
        private TenantRepository $tenants,
    ) {}

    public function index(Request $request, array $params = []): Response
    {
        $q = mb_substr(trim($request->queryString('q')), 0, 120);
        if ($request->query('fragment') === 'choix') {
            $type = $request->queryString('type');
            if (!in_array($type, ['account', 'organization'], true)) {
                return Response::json(['error' => 'Type de recherche inconnu.'], 400)->header('Cache-Control', 'private, no-store');
            }
            $rows = $this->results($q, 12, $type);
            $items = $type === 'account' ? array_slice($rows['accounts'], 0, 12) : $rows['communities'];

            return Response::json([
                'items' => $items,
                'error' => $rows['error'],
                'too_short' => $rows['too_short'],
            ])->header('Cache-Control', 'private, no-store');
        }
        $compact = $request->query('fragment') === 'resultats';
        $r = $this->results($q, $compact ? 8 : null);
        $data = [
            'searchQuery' => $q,
            'searchAccounts' => $r['accounts'],
            'searchCommunities' => $r['communities'],
            'searchError' => $r['error'],
            'searchTooShort' => $r['too_short'],
        ];
        if ($compact) {
            return Response::view('admin.partials.dashboard_search_results', $data)
                ->header('Cache-Control', 'private, no-store');
        }

        return Response::view('layout.main', $data + [
            'title' => 'Recherche',
            'content' => 'admin.system.search',
            'backOfficePageCss' => ['platform-admin.css'],
        ]);
    }

    /**
     * @return array{accounts: list<array<string, mixed>>, communities: list<array<string, mixed>>, error: ?string, too_short: bool}
     */
    public function results(string $q, ?int $limit = null, ?string $type = null): array
    {
        $out = ['accounts' => [], 'communities' => [], 'error' => null, 'too_short' => false];
        $id = ctype_digit(ltrim($q, '#')) ? (int) ltrim($q, '#') : 0;
        if ($q === '') {
            return $out;
        }
        if (mb_strlen($q) < self::MIN_LENGTH && $id === 0) {
            $out['too_short'] = true;

            return $out;
        }
        try {
            if ($type !== 'organization') {
                $out['accounts'] = $this->accounts($q, $id, $limit ?? 25);
            }
            if ($type !== 'account') {
                $out['communities'] = $this->tenants->searchForPlatformAdmin($q, $limit ?? 20);
            }
        } catch (\Throwable) {
            $out['error'] = 'La recherche n’a pas pu aboutir. Réessayez dans un instant.';
        }

        return $out;
    }

    /** @return list<array<string, mixed>> */
    private function accounts(string $q, int $id, int $limit): array
    {
        $rows = mb_strlen($q) >= self::MIN_LENGTH ? $this->users->searchAccountsForPlatformOperator($q, $limit) : [];
        if ($id > 0 && !in_array($id, array_map(static fn (array $r): int => (int) ($r['id'] ?? 0), $rows), true)) {
            $byId = $this->users->findById($id);
            if ($byId !== null) {
                array_unshift($rows, $byId);
            }
        }

        return array_map(static fn (array $u): array => [
            'id' => (int) ($u['id'] ?? 0),
            'email' => (string) ($u['email'] ?? ''),
            'display_name' => trim((string) ($u['display_name'] ?? '')),
            'callsign' => trim((string) ($u['callsign'] ?? '')),
            'steam_id' => trim((string) ($u['steam_id'] ?? '')),
            'community' => trim((string) ($u['tenant_name'] ?? '')),
            'state' => self::accountStateLabel((string) ($u['status'] ?? '')),
        ], $rows);
    }

    public static function accountStateLabel(string $status): string
    {
        return match ($status) {
            'active' => 'Compte actif',
            'inactive' => 'Compte inactif',
            'pending_verification' => 'E-mail à vérifier',
            'banned' => 'Banni',
            default => 'Autre état',
        };
    }
}
