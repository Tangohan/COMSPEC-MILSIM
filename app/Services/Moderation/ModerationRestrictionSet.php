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
        public readonly bool $messagesBlocked,
        /** @var list<string> */
        public readonly array $modulesBlocked,
        public readonly bool $joinBlocked
    ) {}

    public static function defaultOpen(): self
    {
        return new self(false, false, [], false);
    }

    public function canSendMessages(): bool
    {
        return !$this->messagesBlocked;
    }

    public function isModuleAllowed(string $moduleKey): bool
    {
        return !in_array($moduleKey, $this->modulesBlocked, true);
    }

    /** @param array<string, mixed>|null $decoded */
    public static function fromJsonChunk(?array $decoded, string $legacyActionType): self
    {
        if ($decoded === null || $decoded === []) {
            if (in_array($legacyActionType, ['mute', 'suspend', 'ban'], true)) {
                return new self(true, true, ModerationRestrictionsCatalog::moduleKeys(), false);
            }

            return self::defaultOpen();
        }
        $lock = !empty($decoded['account_lock']);
        $msg = !empty($decoded['messages_blocked']);
        $mods = $decoded['modules_blocked'] ?? [];
        if (!is_array($mods)) {
            $mods = [];
        }
        $mods = array_values(array_unique(array_filter(array_map('strval', $mods))));
        $mods = array_values(array_intersect($mods, ModerationRestrictionsCatalog::moduleKeys()));
        $join = !empty($decoded['join_blocked']);

        return new self($lock, $msg, $mods, $join);
    }

    public function merge(self $other): self
    {
        $locked = $this->accountLocked || $other->accountLocked;
        $messages = $this->messagesBlocked || $other->messagesBlocked;
        $mods = array_values(array_unique(array_merge($this->modulesBlocked, $other->modulesBlocked)));
        $join = $this->joinBlocked || $other->joinBlocked;

        return new self($locked, $messages, $mods, $join);
    }
}
