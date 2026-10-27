<?php

declare(strict_types=1);

namespace App\Services\Account;

use App\Repositories\UserRepository;
use App\Services\Admin\AdminUserAtakService;
use App\Services\Admin\PlayerTracePurgeService;
use App\Support\SteamId;
use Throwable;

/**
 * Tableau de bord d’un compte simple (sans organisation) : son profil, son ATAK et ce que
 * la plateforme garde sur lui, puisqu’aucune communauté ne le lui montre ailleurs.
 *
 * Lecture seule ; les mêmes sources que la fiche opérateur de l’administration
 * ({@see AdminUserAtakService}) et que le registre des données ({@see PlayerTracePurgeService}).
 */
final class SoloAccountOverviewService
{
    /** Lignes du registre qui décrivent la fiche elle-même, pas un historique. */
    private const HIDDEN_TABLES = ['users', 'athena_accounts'];

    public function __construct(
        private ?UserRepository $users = null,
        private ?AdminUserAtakService $atak = null,
        private ?PlayerTracePurgeService $traces = null,
    ) {}

    /**
     * @return array{
     *     profile: array{display_name: string, callsign: string, email: string, steam_linked: bool, steam_hint: string, created_at: ?string, last_login_at: ?string},
     *     atak: array{state: string, problems: list<string>, sessions_usable: int, last_seen_at: ?string, terminals: list<array{label: string, uid: string, last_seen_at: ?string, link: string}>},
     *     history: list<array{label: string, count: int, last: ?string}>
     * }|null
     */
    public function forUser(int $userId): ?array
    {
        $this->users ??= new UserRepository();
        $user = $this->users->findById($userId, null);
        if ($user === null) {
            return null;
        }

        $steam = SteamId::normalize((string) ($user['steam_id'] ?? ''));
        $profile = [
            'display_name' => trim((string) ($user['display_name'] ?? '')),
            'callsign' => trim((string) ($user['callsign'] ?? '')),
            'email' => trim((string) ($user['email'] ?? '')),
            'steam_linked' => $steam !== null,
            'steam_hint' => $steam !== null ? '…' . substr($steam, -4) : '',
            'created_at' => isset($user['created_at']) ? (string) $user['created_at'] : null,
            'last_login_at' => isset($user['last_login_at']) ? (string) $user['last_login_at'] : null,
        ];

        return [
            'profile' => $profile,
            'atak' => $this->atak($userId),
            'history' => $this->history($userId),
        ];
    }

    /**
     * @return array{state: string, problems: list<string>, sessions_usable: int, last_seen_at: ?string, terminals: list<array{label: string, uid: string, last_seen_at: ?string, link: string}>}
     */
    private function atak(int $userId): array
    {
        $out = ['state' => 'idle', 'problems' => [], 'sessions_usable' => 0, 'last_seen_at' => null, 'terminals' => []];
        try {
            $overview = ($this->atak ??= new AdminUserAtakService())->overview($userId);
        } catch (Throwable) {
            return $out;
        }
        if ($overview === null) {
            return $out;
        }

        $out['state'] = (string) ($overview['verdict']['state'] ?? 'idle');
        $out['problems'] = array_values(array_map('strval', $overview['verdict']['problems'] ?? []));
        $out['sessions_usable'] = (int) ($overview['sessions_usable'] ?? 0);
        foreach ($overview['sessions'] ?? [] as $s) {
            $seen = (string) ($s['last_seen_at'] ?? '');
            if ($seen !== '' && ($out['last_seen_at'] === null || $seen > $out['last_seen_at'])) {
                $out['last_seen_at'] = $seen;
            }
        }
        foreach ($overview['terminals'] ?? [] as $t) {
            $uid = (string) ($t['terminal_uid'] ?? '');
            $out['terminals'][] = [
                'label' => trim((string) ($t['terminal_label'] ?? '')) ?: ($uid !== '' ? $uid : 'ATAK'),
                'uid' => $uid,
                'last_seen_at' => isset($t['last_seen_at']) ? (string) $t['last_seen_at'] : null,
                'link' => is_array($t['link'] ?? null)
                    ? trim((string) ($t['link']['label'] ?? '') . ' ' . (string) ($t['link']['ago'] ?? ''))
                    : '',
            ];
        }

        return $out;
    }

    /**
     * @return list<array{label: string, count: int, last: ?string}>
     */
    private function history(int $userId): array
    {
        try {
            $this->traces ??= new PlayerTracePurgeService();
            $rows = $this->traces->count($this->traces->plan($this->traces->identityForUser($userId)));
        } catch (Throwable) {
            return [];
        }
        $out = [];
        foreach ($rows as $r) {
            if (in_array($r['table'], self::HIDDEN_TABLES, true)) {
                continue;
            }
            $out[] = ['label' => $r['label'], 'count' => $r['count'], 'last' => $r['last']];
        }
        usort($out, static fn (array $a, array $b): int => strcmp((string) $b['last'], (string) $a['last']));

        return $out;
    }
}