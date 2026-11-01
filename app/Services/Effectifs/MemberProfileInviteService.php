<?php

declare(strict_types=1);

namespace App\Services\Effectifs;

use App\Repositories\MemberProfileInviteRepository;
use App\Services\Audit\AuditAction;
use App\Support\SecretCipher;
use DateTimeImmutable;

/**
 * Liens « compléter son profil » : le gestionnaire crée une adresse personnelle valable 24 ou 48 h, la personne
 * l'ouvre, active son compte (ou se connecte) puis suit l'accueil du profil (/account/bienvenue).
 *
 * Sécurité : jeton de 256 bits (random_bytes), jamais stocké en clair — empreinte SHA-256 pour l'ouverture, copie
 * chiffrée (APP_KEY) pour « Copier le lien » ; expiration stricte ; révocable ; usage unique en option ; un seul
 * lien vivant par membre. Aucun envoi d'e-mail ici : le lien part copié, par Discord ou par e-mail sur demande.
 */
final class MemberProfileInviteService
{
    /** @var list<int> */
    public const DURATIONS = [24, 48];

    public const STATUS_ACTIVE = 'active';
    public const STATUS_OPENED = 'opened';
    public const STATUS_USED = 'used';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_EXPIRED = 'expired';
    public const STATUS_REVOKED = 'revoked';

    /** Session : lien ouvert (identifiant + empreinte), pour ne pas garder le jeton dans l'adresse. */
    public const SESSION_KEY = 'member_profile_invite';

    /** @var \Closure(): DateTimeImmutable */
    private \Closure $clock;

    /** @var \Closure(string, int, ?int, int, string): void */
    private \Closure $audit;

    /**
     * @param (\Closure(): DateTimeImmutable)|null $clock
     * @param (\Closure(string, int, ?int, int, string): void)|null $audit
     */
    public function __construct(
        private ?MemberProfileInviteRepository $repo = null,
        ?\Closure $clock = null,
        ?\Closure $audit = null,
    ) {
        $this->repo ??= new MemberProfileInviteRepository();
        $this->clock = $clock ?? static fn (): DateTimeImmutable => new DateTimeImmutable('now');
        $this->audit = $audit ?? static function (string $action, int $tenantId, ?int $actorId, int $inviteId, string $detail): void {
            try {
                \App\Core\Container::get(\App\Services\Audit\AuditService::class)
                    ->log($action, $tenantId, $actorId, 'member_profile_invite', $inviteId, null, $detail !== '' ? $detail : null);
            } catch (\Throwable) {
            }
        };
    }

    public function available(): bool
    {
        return $this->repo->tableExists();
    }

    public function now(): DateTimeImmutable
    {
        return ($this->clock)();
    }

    private function stamp(?DateTimeImmutable $at = null): string
    {
        return ($at ?? $this->now())->format('Y-m-d H:i:s');
    }

    public static function hashToken(string $token): string
    {
        return hash('sha256', trim($token));
    }

    public static function isWellFormedToken(string $token): bool
    {
        return preg_match('/^[a-f0-9]{64}$/', $token) === 1;
    }

    public static function url(string $token): string
    {
        return url('invitation/profil/' . $token);
    }

    public static function normalizeHours(mixed $raw): int
    {
        $h = (int) $raw;

        return in_array($h, self::DURATIONS, true) ? $h : 48;
    }

    /**
     * Crée un lien (et ferme les liens encore ouverts du même membre).
     *
     * @return array{id:int, token:string, url:string, expires_at:string}
     */
    public function issue(int $tenantId, int $userId, ?int $actorId, int $hours, bool $singleUse): array
    {
        if ($tenantId < 1 || $userId < 1) {
            throw new \InvalidArgumentException('Membre introuvable.');
        }
        $hours = self::normalizeHours($hours);
        $now = $this->now();
        $token = bin2hex(random_bytes(32));
        $this->repo->revokeOpenForUser($tenantId, $userId, $actorId, $this->stamp($now));
        $expires = $this->stamp($now->modify('+' . $hours . ' hours'));
        $cipher = null;
        try {
            $cipher = SecretCipher::encrypt($token);
        } catch (\Throwable) {
            $cipher = null;
        }
        $id = $this->repo->create([
            'tenant_id' => $tenantId,
            'user_id' => $userId,
            'token_hash' => self::hashToken($token),
            'token_cipher' => $cipher,
            'duration_hours' => $hours,
            'single_use' => $singleUse,
            'created_by' => $actorId,
            'created_at' => $this->stamp($now),
            'expires_at' => $expires,
        ]);
        ($this->audit)(AuditAction::PROFILE_INVITE_CREATED, $tenantId, $actorId, $id, 'membre:' . $userId . ';duree:' . $hours . 'h' . ($singleUse ? ';usage-unique' : ''));

        return ['id' => $id, 'token' => $token, 'url' => self::url($token), 'expires_at' => $expires];
    }

    /** @param array<string, mixed> $row */
    public static function status(array $row, DateTimeImmutable $now): string
    {
        if (!empty($row['revoked_at'])) {
            return self::STATUS_REVOKED;
        }
        if (!empty($row['completed_at'])) {
            return self::STATUS_COMPLETED;
        }
        $expires = strtotime((string) ($row['expires_at'] ?? '')) ?: 0;
        if ($expires <= $now->getTimestamp()) {
            return self::STATUS_EXPIRED;
        }
        if (!empty($row['single_use']) && !empty($row['used_at'])) {
            return self::STATUS_USED;
        }

        return !empty($row['first_opened_at']) ? self::STATUS_OPENED : self::STATUS_ACTIVE;
    }

    /** @param array<string, mixed> $row */
    public static function isUsable(array $row, DateTimeImmutable $now): bool
    {
        return in_array(self::status($row, $now), [self::STATUS_ACTIVE, self::STATUS_OPENED], true);
    }

    /** @param array<string, mixed> $row */
    public function statusOf(array $row): string
    {
        return self::status($row, $this->now());
    }

    public static function statusLabel(string $status): string
    {
        return match ($status) {
            self::STATUS_ACTIVE => 'Actif, pas encore ouvert',
            self::STATUS_OPENED => 'Ouvert',
            self::STATUS_USED => 'Utilisé (lien fermé)',
            self::STATUS_COMPLETED => 'Profil complété',
            self::STATUS_EXPIRED => 'Expiré',
            self::STATUS_REVOKED => 'Révoqué',
            default => 'Inconnu',
        };
    }

    /**
     * Lien ouvrable pour ce jeton, ou null (inconnu, expiré, révoqué, déjà utilisé, profil terminé).
     *
     * @return array<string, mixed>|null
     */
    public function findUsableByToken(string $token): ?array
    {
        $token = trim($token);
        if (!self::isWellFormedToken($token)) {
            return null;
        }
        $row = $this->repo->findByTokenHash(self::hashToken($token));

        return $row !== null && self::isUsable($row, $this->now()) ? $row : null;
    }

    /**
     * Lien repris depuis la session (après la première ouverture), toujours revérifié.
     *
     * @return array<string, mixed>|null
     */
    public function findUsableByHash(string $hash): ?array
    {
        $row = $this->repo->findByTokenHash($hash);

        return $row !== null && self::isUsable($row, $this->now()) ? $row : null;
    }

    /** @param array<string, mixed> $row */
    public function recordOpen(array $row): void
    {
        $this->repo->recordOpen((int) $row['id'], $this->stamp());
        if (empty($row['first_opened_at'])) {
            ($this->audit)(AuditAction::PROFILE_INVITE_OPENED, (int) $row['tenant_id'], null, (int) $row['id'], 'membre:' . (int) $row['user_id']);
        }
    }

    /** Compte activé ou connexion faite par le lien : un lien à usage unique se ferme ici. */
    public function markAccountReady(array $row): void
    {
        $this->repo->markAccountReady((int) $row['id'], $this->stamp(), !empty($row['single_use']));
        if (empty($row['account_ready_at'])) {
            ($this->audit)(AuditAction::PROFILE_INVITE_ACCOUNT_READY, (int) $row['tenant_id'], (int) $row['user_id'], (int) $row['id'], '');
        }
    }

    /** @param array<string, mixed> $row */
    public function markCompleted(array $row): void
    {
        if (!empty($row['completed_at'])) {
            return;
        }
        $this->repo->markCompleted((int) $row['id'], $this->stamp());
        ($this->audit)(AuditAction::PROFILE_INVITE_COMPLETED, (int) $row['tenant_id'], (int) $row['user_id'], (int) $row['id'], '');
    }

    /** Prolonge de 24 ou 48 h à partir de maintenant (même adresse). Un lien révoqué ou terminé ne se prolonge pas. */
    public function extend(int $tenantId, int $id, int $hours, ?int $actorId): bool
    {
        $row = $this->repo->find($tenantId, $id);
        if ($row === null || in_array(self::status($row, $this->now()), [self::STATUS_REVOKED, self::STATUS_COMPLETED, self::STATUS_USED], true)) {
            return false;
        }
        $hours = self::normalizeHours($hours);
        $ok = $this->repo->extend($tenantId, $id, $this->stamp($this->now()->modify('+' . $hours . ' hours')));
        if ($ok) {
            ($this->audit)(AuditAction::PROFILE_INVITE_EXTENDED, $tenantId, $actorId, $id, '+' . $hours . 'h');
        }

        return $ok;
    }

    /**
     * Nouvelle adresse (même membre, même durée, même option) ; l'ancienne cesse de fonctionner.
     *
     * @return array{id:int, token:string, url:string, expires_at:string}|null
     */
    public function regenerate(int $tenantId, int $id, ?int $actorId): ?array
    {
        $row = $this->repo->find($tenantId, $id);
        if ($row === null || self::status($row, $this->now()) === self::STATUS_COMPLETED) {
            return null;
        }
        $this->repo->revoke($tenantId, $id, $actorId, $this->stamp());
        $new = $this->issue($tenantId, (int) $row['user_id'], $actorId, (int) $row['duration_hours'], !empty($row['single_use']));
        ($this->audit)(AuditAction::PROFILE_INVITE_REGENERATED, $tenantId, $actorId, $new['id'], 'remplace:' . $id);

        return $new;
    }

    public function revoke(int $tenantId, int $id, ?int $actorId): bool
    {
        $ok = $this->repo->revoke($tenantId, $id, $actorId, $this->stamp());
        if ($ok) {
            ($this->audit)(AuditAction::PROFILE_INVITE_REVOKED, $tenantId, $actorId, $id, '');
        }

        return $ok;
    }

    /** @param array<string, mixed> $row */
    public function recordSent(array $row, string $channel, ?int $actorId): void
    {
        $this->repo->recordSent((int) $row['id'], $channel, $this->stamp());
        ($this->audit)(AuditAction::PROFILE_INVITE_SENT, (int) $row['tenant_id'], $actorId, (int) $row['id'], 'canal:' . $channel);
    }

    /**
     * Adresse complète d'un lien encore ouvrable (pour « Copier le lien »), ou null.
     *
     * @param array<string, mixed> $row
     */
    public function linkFor(array $row): ?string
    {
        if (!self::isUsable($row, $this->now())) {
            return null;
        }
        $token = SecretCipher::decrypt((string) ($row['token_cipher'] ?? ''));
        if ($token === null || !self::isWellFormedToken($token) || !hash_equals((string) $row['token_hash'], self::hashToken($token))) {
            return null;
        }

        return self::url($token);
    }

    /**
     * Étapes suivies pour le gestionnaire. « Discord » n'apparaît que si la liaison est proposée sur le site.
     *
     * @param array<string, bool> $facts ProfileWelcomeJourney::facts()
     * @param array<string, mixed> $user ligne users
     * @param array<string, mixed>|null $invite
     * @return list<array{key:string, label:string, done:bool}>
     */
    public static function steps(array $facts, array $user, ?array $invite = null): array
    {
        $account = strtolower((string) ($user['status'] ?? '')) === 'active'
            && (!empty($invite['account_ready_at']) || trim((string) ($user['last_login_at'] ?? '')) !== '');
        $out = [['key' => 'compte', 'label' => 'Compte', 'done' => $account]];
        if (!empty($facts['discord_available']) || !empty($facts['discord_linked'])) {
            $out[] = ['key' => 'discord', 'label' => 'Discord', 'done' => !empty($facts['discord_linked'])];
        }
        $out[] = ['key' => 'steam', 'label' => 'Steam', 'done' => !empty($facts['steam_linked'])];
        $out[] = ['key' => 'portrait', 'label' => 'Portrait', 'done' => !empty($facts['portrait'])];
        $out[] = ['key' => 'securite', 'label' => 'Double vérification', 'done' => !empty($facts['second_factor'])];

        return $out;
    }

    /** @param list<array{done:bool}> $steps */
    public static function allDone(array $steps): bool
    {
        foreach ($steps as $s) {
            if (!$s['done']) {
                return false;
            }
        }

        return $steps !== [];
    }

    /** « j•••@gmail.com » : assez pour reconnaître son adresse, pas pour la deviner. */
    public static function maskEmail(string $email): string
    {
        $email = trim($email);
        $at = strrpos($email, '@');
        if ($at === false || $at < 1) {
            return '';
        }

        return mb_substr($email, 0, 1) . '•••' . substr($email, $at);
    }
}
