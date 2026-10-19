<?php

declare(strict_types=1);

namespace App\Services\Account;

use App\Core\Container;
use App\Core\Database;
use App\Repositories\BlockedIndicatorRepository;
use App\Repositories\PlatformAccountBanRepository;
use App\Repositories\UserRepository;
use App\Services\Moderation\IndicatorBlocklistService;
use PDO;

/**
 * Bannissement d’une personne sur tout le site.
 *
 * Bannir = toutes les fiches de l’adresse passent « inactif » (sessions fermées à la requête suivante),
 * l’adresse e-mail est refusée à la connexion et à l’inscription, le Steam ID est refusé par le mod,
 * et les adresses réseau cochées sont bloquées. Lever le bannissement remet chaque fiche dans son état
 * d’avant et retire uniquement les entrées créées par ce bannissement.
 */
final class PlatformAccountBanService
{
    public function __construct(
        private ?UserRepository $users = null,
        private ?PlatformAccountBanRepository $bans = null,
        private ?IndicatorBlocklistService $blocklist = null,
        private ?BlockedIndicatorRepository $indicators = null,
        private ?PDO $pdo = null,
    ) {
    }

    private function users(): UserRepository
    {
        return $this->users ??= Container::get(UserRepository::class);
    }

    private function bans(): PlatformAccountBanRepository
    {
        return $this->bans ??= new PlatformAccountBanRepository($this->pdo);
    }

    private function blocklist(): IndicatorBlocklistService
    {
        return $this->blocklist ??= Container::get(IndicatorBlocklistService::class);
    }

    private function indicators(): BlockedIndicatorRepository
    {
        return $this->indicators ??= Container::get(BlockedIndicatorRepository::class);
    }

    private function pdo(): PDO
    {
        return $this->pdo ??= Database::getPdo();
    }

    /**
     * @param list<string> $ips
     * @return array{ok: bool, error?: string, ban_id?: int, blocked_ips?: int, steam_blocked?: bool, accounts?: int}
     */
    public function ban(string $email, string $reason, array $ips, bool $blockSteam, int $actorId): array
    {
        $email = PlatformAccountBanRepository::normalizeEmail($email);
        $reason = trim($reason);
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || str_ends_with($email, '@deleted.invalid')) {
            return ['ok' => false, 'error' => 'Adresse du compte introuvable.'];
        }
        if ($reason === '') {
            return ['ok' => false, 'error' => 'Indiquez le motif du bannissement : il est gardé dans le journal.'];
        }
        if (!$this->bans()->ready()) {
            return ['ok' => false, 'error' => 'La table des bannissements n’a pas pu être créée. Lancez les migrations puis réessayez.'];
        }
        if ($this->bans()->findActiveForEmail($email) !== null) {
            return ['ok' => false, 'error' => 'Cette personne est déjà bannie.'];
        }
        if ($this->users()->emailHasPlatformAdmin($email)) {
            return ['ok' => false, 'error' => 'Un administrateur du site ne peut pas être banni : retirez-lui d’abord ce rôle.'];
        }

        $ids = array_values(array_filter(
            array_map('intval', $this->users()->listIdsByEmailNormalized($email)),
            static fn (int $id): bool => $id > 0
        ));
        if ($ids === []) {
            return ['ok' => false, 'error' => 'Aucun compte trouvé pour cette adresse.'];
        }
        if (in_array($actorId, $ids, true)) {
            return ['ok' => false, 'error' => 'Vous ne pouvez pas vous bannir vous-même.'];
        }

        $snapshot = [];
        $label = '';
        $steam = '';
        foreach ($ids as $id) {
            $row = $this->users()->findById($id, null);
            if ($row === null) {
                continue;
            }
            $snapshot[$id] = (string) ($row['status'] ?? '');
            if ($label === '') {
                $label = trim((string) ($row['callsign'] ?? '')) ?: trim((string) ($row['display_name'] ?? ''));
            }
            if ($steam === '') {
                $steam = trim((string) ($row['steam_id'] ?? ''));
            }
        }

        $indicatorIds = [];
        $note = 'Bannissement site : ' . mb_substr($reason, 0, 200);
        $indicatorIds[] = $this->blocklist()->addEmailBlock($actorId, 'global', null, $email, $note, null);
        $steamBlocked = false;
        if ($blockSteam && $steam !== '') {
            try {
                $indicatorIds[] = $this->blocklist()->addSteamBlock($actorId, 'global', null, $steam, $note, null);
                $steamBlocked = true;
            } catch (\InvalidArgumentException) {
                $steamBlocked = false;
            }
        }
        $blockedIps = 0;
        foreach (self::cleanIps($ips) as $ip) {
            if ($this->indicators()->isIpBlockedGlobally($ip)) {
                continue;
            }
            $indicatorIds[] = $this->blocklist()->addIpBlock($actorId, 'global', null, $ip, $note, null);
            $blockedIps++;
        }

        $this->setStatuses(array_fill_keys(array_keys($snapshot), 'inactive'));

        $banId = $this->bans()->create(
            $email,
            $ids[0] ?? null,
            $label !== '' ? $label : $email,
            $reason,
            $indicatorIds,
            $snapshot,
            $actorId > 0 ? $actorId : null
        );

        return [
            'ok' => true,
            'ban_id' => $banId,
            'blocked_ips' => $blockedIps,
            'steam_blocked' => $steamBlocked,
            'accounts' => count($snapshot),
        ];
    }

    /**
     * @return array{ok: bool, error?: string, restored?: int}
     */
    public function lift(string $email, int $actorId): array
    {
        $ban = $this->bans()->findActiveForEmail($email);
        if ($ban === null) {
            return ['ok' => false, 'error' => 'Aucun bannissement en cours pour cette adresse.'];
        }
        foreach (json_decode((string) ($ban['indicator_ids'] ?? '[]'), true) ?: [] as $indicatorId) {
            if ((int) $indicatorId > 0) {
                $this->blocklist()->revokeIndicator($actorId, (int) $indicatorId, null);
            }
        }
        $snapshot = [];
        foreach (json_decode((string) ($ban['status_snapshot'] ?? '{}'), true) ?: [] as $id => $status) {
            $status = (string) $status;
            // Une fiche déjà anonymisée ou fusionnée entre-temps garde son état actuel.
            $row = $this->users()->findById((int) $id, null);
            if ($row === null || !empty($row['deleted_at']) || (string) ($row['status'] ?? '') === 'merged') {
                continue;
            }
            $snapshot[(int) $id] = $status !== '' ? $status : 'active';
        }
        $this->setStatuses($snapshot);
        $this->bans()->lift((int) $ban['id'], $actorId > 0 ? $actorId : null);

        return ['ok' => true, 'restored' => count($snapshot)];
    }

    /**
     * @param list<string> $ips
     * @return list<string>
     */
    public static function cleanIps(array $ips): array
    {
        $out = [];
        foreach ($ips as $raw) {
            foreach (preg_split('/[\s,;]+/', (string) $raw) ?: [] as $ip) {
                $ip = trim($ip);
                if ($ip !== '' && filter_var($ip, FILTER_VALIDATE_IP) !== false) {
                    $out[$ip] = true;
                }
            }
        }

        return array_keys($out);
    }

    /** @param array<int, string> $statuses */
    private function setStatuses(array $statuses): void
    {
        $st = $this->pdo()->prepare('UPDATE users SET status = ?, updated_at = NOW() WHERE id = ?');
        foreach ($statuses as $id => $status) {
            $st->execute([$status, (int) $id]);
        }
    }
}
