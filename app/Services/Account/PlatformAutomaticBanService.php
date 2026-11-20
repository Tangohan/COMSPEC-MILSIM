<?php

declare(strict_types=1);

namespace App\Services\Account;

use App\Core\Container;
use App\Repositories\ModerationRepository;
use App\Repositories\PlatformAccountBanRepository;
use App\Repositories\PlatformSettingsRepository;
use App\Repositories\UserRepository;
use App\Services\Audit\AuditAction;
use App\Services\Audit\AuditService;

final class PlatformAutomaticBanService
{
    private const KEY = 'platform_auto_ban_rule';
    private const DEFAULTS = ['enabled' => false, 'threshold' => 3, 'window_days' => 30, 'duration' => '7j'];

    public function __construct(
        private ?PlatformSettingsRepository $store = null,
        private ?PlatformAccountBanRepository $bans = null,
        private ?PlatformAccountBanService $service = null,
        private ?UserRepository $users = null,
    ) {
    }

    /** @return array{enabled: bool, threshold: int, window_days: int, duration: string} */
    public function settings(): array
    {
        $stored = json_decode($this->store()->get(self::KEY), true);
        if (!is_array($stored)) {
            return self::DEFAULTS;
        }

        return self::validated(
            ($stored['enabled'] ?? false) === true,
            (int) ($stored['threshold'] ?? 0),
            (int) ($stored['window_days'] ?? 0),
            (string) ($stored['duration'] ?? ''),
        ) ?? self::DEFAULTS;
    }

    /** @return array{enabled: bool, threshold: int, window_days: int, duration: string}|null */
    public function save(bool $enabled, int $threshold, int $days, string $duration): ?array
    {
        $rule = self::validated($enabled, $threshold, $days, $duration);
        if ($rule === null || !$this->store()->tableExists()) {
            return null;
        }
        $this->store()->setMany([self::KEY => json_encode($rule, JSON_THROW_ON_ERROR)]);

        return $rule;
    }

    public function afterConfirmedRestriction(ModerationRepository $moderation, string $email, int $actionId, int $actorId): bool
    {
        $rule = $this->settings();
        if (!$rule['enabled'] || $actionId < 1 || $actorId < 1 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return false;
        }
        $bans = $this->bans ??= new PlatformAccountBanRepository();
        if (!$bans->supportsNotices() || $bans->findActiveForEmail($email) !== null) {
            return false;
        }
        $users = $this->users ??= Container::get(UserRepository::class);
        $ids = $users->listIdsByEmailNormalized($email);
        if ($ids === []) {
            return false;
        }
        $previous = $bans->latestForEmail($email);
        $since = gmdate('Y-m-d H:i:s', time() - $rule['window_days'] * 86400);
        $count = $moderation->countConfirmedPlatformRestrictions($ids, $since, $previous['created_at'] ?? null, $actionId);
        if ($count < $rule['threshold']) {
            return false;
        }
        $end = PlatformAccountBanService::resolveEnd($rule['duration'], '');
        $reason = sprintf('Règle automatique : %d suspensions ou bannissements confirmés par l’administration sur %d jours.', $count, $rule['window_days']);
        $notice = new PlatformBanNotice('CGU Athena · Suspension et résiliation',
            $reason . ' L’accès à Athena est restreint. Vous pouvez expliquer votre situation ou demander un réexamen de cette décision.');
        $result = ($this->service ??= new PlatformAccountBanService())->ban($email, $reason, [], true, $actorId, $end['endsAt'] ?? null, $notice);
        if (!$result['ok']) {
            return false;
        }
        AuditService::logPlatformAction(AuditAction::USER_BANNED, [
            'ban_id' => $result['ban_id'],
            'mode' => 'automatic',
            'trigger_action_id' => $actionId,
            'confirmed_decisions' => $count,
            'window_days' => $rule['window_days'],
            'ends_at' => $result['ends_at'],
        ]);

        return true;
    }

    private function store(): PlatformSettingsRepository
    {
        return $this->store ??= new PlatformSettingsRepository();
    }

    /** @return array{enabled: bool, threshold: int, window_days: int, duration: string}|null */
    private static function validated(bool $enabled, int $threshold, int $days, string $duration): ?array
    {
        if ($threshold < 1 || $threshold > 100 || $days < 1 || $days > 365
            || (!isset(PlatformAccountBanService::DURATIONS[$duration]) && $duration !== 'definitif')) {
            return null;
        }

        return ['enabled' => $enabled, 'threshold' => $threshold, 'window_days' => $days, 'duration' => $duration];
    }
}
