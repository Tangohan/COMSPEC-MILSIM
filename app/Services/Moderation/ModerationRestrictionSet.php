<?php

declare(strict_types=1);

namespace App\Services\Moderation;

/**
 * Résultat fusionné des sanctions actives pour un membre dans une organisation.
 */
final class ModerationRestrictionSet
{
    public function __construct(
        public readonly bool $accountLocked,
        /** full_access | read_only | none */
        public readonly string $forumAccess,
        public readonly bool $messagesBlocked,
        /** @var list<string> */
        public readonly array $modulesBlocked,
        public readonly bool $joinBlocked
    ) {}

    public static function defaultOpen(): self
    {
        return new self(false, 'full_access', false, [], false);
    }

    public function canReadForum(): bool
    {
        return $this->forumAccess !== 'none';
    }

    public function canWriteForum(): bool
    {
        return $this->forumAccess === 'full_access' && !$this->messagesBlocked;
    }

    public function canSendMessages(): bool
    {
        return !$this->messagesBlocked;
    }

    public function isModuleAllowed(string $moduleKey): bool
    {
        if ($moduleKey === ModerationRestrictionsCatalog::KEY_FORUM) {
            return $this->canReadForum();
        }

        return !in_array($moduleKey, $this->modulesBlocked, true);
    }

    /** @param array<string, mixed>|null $decoded */
    public static function fromJsonChunk(?array $decoded, string $legacyActionType): self
    {
        if ($decoded === null || $decoded === []) {
            if (in_array($legacyActionType, ['mute', 'suspend', 'ban'], true)) {
                return new self(true, 'none', true, ModerationRestrictionsCatalog::moduleKeys(), false);
            }

            return self::defaultOpen();
        }
        $lock = !empty($decoded['account_lock']);
        $forum = (string) ($decoded['forum'] ?? 'full_access');
        if (!in_array($forum, ['full_access', 'read_only', 'none'], true)) {
            $forum = 'full_access';
        }
        $msg = !empty($decoded['messages_blocked']);
        $mods = $decoded['modules_blocked'] ?? [];
        if (!is_array($mods)) {
            $mods = [];
        }
        $mods = array_values(array_unique(array_filter(array_map('strval', $mods))));
        $mods = array_values(array_intersect($mods, ModerationRestrictionsCatalog::moduleKeys()));
        if ($forum === 'none') {
            $mods[] = ModerationRestrictionsCatalog::KEY_FORUM;
            $mods = array_values(array_unique($mods));
        }
        $join = !empty($decoded['join_blocked']);

        return new self($lock, $forum, $msg, $mods, $join);
    }

    public function merge(self $other): self
    {
        $locked = $this->accountLocked || $other->accountLocked;
        $forumRank = static fn (string $f): int => match ($f) {
            'none' => 0,
            'read_only' => 1,
            'full_access' => 2,
            default => 2,
        };
        $forum = $forumRank($this->forumAccess) <= $forumRank($other->forumAccess) ? $this->forumAccess : $other->forumAccess;
        $messages = $this->messagesBlocked || $other->messagesBlocked;
        $mods = array_values(array_unique(array_merge($this->modulesBlocked, $other->modulesBlocked)));
        $join = $this->joinBlocked || $other->joinBlocked;

        return new self($locked, $forum, $messages, $mods, $join);
    }
}
