<?php

declare(strict_types=1);

namespace App\Services\Effectifs;

use App\Repositories\MemberProfileInviteRepository;
use App\Repositories\UserRepository;
use App\Services\Account\ProfileWelcomeJourney;

/**
 * Suivi des liens « compléter son profil », façon « partage » : destinataire, auteur, dates, état, dernière
 * ouverture et étapes faites (lues sur le compte réel à chaque affichage, comme l'accueil du profil).
 */
final class MemberProfileInviteBoard
{
    /** @var array<int, list<array{key:string, label:string, done:bool}>> */
    private array $stepsByUser = [];

    public function __construct(
        private MemberProfileInviteService $invites,
        private ?MemberProfileInviteRepository $repo = null,
        private ?UserRepository $users = null,
        private ?ProfileWelcomeJourney $journey = null,
    ) {
        $this->repo ??= new MemberProfileInviteRepository();
    }

    /** @return list<array<string, mixed>> */
    public function forMember(int $tenantId, int $userId): array
    {
        if (!$this->invites->available()) {
            return [];
        }

        return $this->present($tenantId, $this->repo->listForUser($tenantId, $userId, 10));
    }

    /** @return list<array<string, mixed>> */
    public function forTenant(int $tenantId, int $limit = 100): array
    {
        if (!$this->invites->available()) {
            return [];
        }

        return $this->present($tenantId, $this->repo->listForTenant($tenantId, $limit));
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return list<array<string, mixed>>
     */
    private function present(int $tenantId, array $rows): array
    {
        $out = [];
        foreach ($rows as $row) {
            $userId = (int) $row['user_id'];
            $steps = $this->steps($tenantId, $userId, $row);
            $status = $this->invites->statusOf($row);
            if (in_array($status, [MemberProfileInviteService::STATUS_ACTIVE, MemberProfileInviteService::STATUS_OPENED, MemberProfileInviteService::STATUS_USED], true)
                && !empty($row['account_ready_at']) && MemberProfileInviteService::allDone($steps)) {
                $this->invites->markCompleted($row);
                $row['completed_at'] = $this->invites->now()->format('Y-m-d H:i:s');
                $status = MemberProfileInviteService::STATUS_COMPLETED;
            }
            $creator = trim((string) ($row['creator_callsign'] ?? '')) !== '' ? (string) $row['creator_callsign'] : (string) ($row['creator_name'] ?? '');
            $member = trim((string) ($row['member_callsign'] ?? '')) !== '' ? (string) $row['member_callsign'] : (string) ($row['member_name'] ?? '');
            $out[] = [
                'id' => (int) $row['id'],
                'user_id' => $userId,
                'member' => $member !== '' ? $member : 'Membre #' . $userId,
                'creator' => $creator !== '' ? $creator : '—',
                'created_at' => (string) $row['created_at'],
                'expires_at' => (string) $row['expires_at'],
                'last_opened_at' => (string) ($row['last_opened_at'] ?? ''),
                'open_count' => (int) ($row['open_count'] ?? 0),
                'single_use' => !empty($row['single_use']),
                'duration_hours' => (int) ($row['duration_hours'] ?? 48),
                'last_sent_channel' => (string) ($row['last_sent_channel'] ?? ''),
                'last_sent_at' => (string) ($row['last_sent_at'] ?? ''),
                'status' => $status,
                'status_label' => MemberProfileInviteService::statusLabel($status),
                'usable' => in_array($status, [MemberProfileInviteService::STATUS_ACTIVE, MemberProfileInviteService::STATUS_OPENED], true),
                'steps' => $steps,
            ];
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $invite
     * @return list<array{key:string, label:string, done:bool}>
     */
    private function steps(int $tenantId, int $userId, array $invite): array
    {
        if (!isset($this->stepsByUser[$userId])) {
            $user = $this->users()->findById($userId, $tenantId) ?? ['id' => $userId];
            $facts = [];
            try {
                $facts = $this->journey()->facts($user);
            } catch (\Throwable) {
            }
            $this->stepsByUser[$userId] = MemberProfileInviteService::steps($facts, $user);
        }
        $steps = $this->stepsByUser[$userId];
        if (!empty($invite['account_ready_at']) && $steps !== [] && !$steps[0]['done']) {
            $steps[0]['done'] = true;
        }

        return $steps;
    }

    private function users(): UserRepository
    {
        return $this->users ??= \App\Core\Container::get(UserRepository::class);
    }

    private function journey(): ProfileWelcomeJourney
    {
        return $this->journey ??= new ProfileWelcomeJourney();
    }
}
