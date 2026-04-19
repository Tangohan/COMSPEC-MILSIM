<?php

declare(strict_types=1);

namespace App\Services\Analytics;

use App\Core\Session;
use App\Repositories\AnalyticsEventRepository;

final class AnalyticsEventService
{
    private const PROPS_MAX_BYTES = 2048;

    private const BEACON_MAX_PER_MINUTE = 48;

    private AnalyticsEventRepository $repository;

    public function __construct(?AnalyticsEventRepository $repository = null)
    {
        $this->repository = $repository ?? new AnalyticsEventRepository();
    }

    /**
     * @param array<string, mixed>|null $props
     */
    public function record(
        int $tenantId,
        ?int $actorUserId,
        string $category,
        string $name,
        ?string $subjectType = null,
        ?int $subjectId = null,
        ?int $durationSeconds = null,
        ?array $props = null,
        ?string $sessionHash = null
    ): void {
        if ($tenantId < 1) {
            return;
        }
        if (!in_array($category, AnalyticsEventCategory::all(), true)) {
            return;
        }
        $allowed = AnalyticsEventName::serverEventsByCategory();
        if (!isset($allowed[$category]) || !in_array($name, $allowed[$category], true)) {
            return;
        }
        if ($subjectType !== null && $subjectType !== '' && !in_array($subjectType, AnalyticsSubjectType::all(), true)) {
            return;
        }
        if ($subjectType === null || $subjectType === '') {
            $subjectType = null;
            $subjectId = null;
        } elseif ($subjectId === null || $subjectId < 1) {
            return;
        }
        $props = $this->normalizeProps($props);
        try {
            $this->repository->insert(
                $tenantId,
                $actorUserId,
                $sessionHash,
                $category,
                $name,
                $subjectType !== '' ? $subjectType : null,
                $subjectId,
                $durationSeconds !== null ? max(0, min(86400, $durationSeconds)) : null,
                $props
            );
        } catch (\Throwable) {
            // ne pas bloquer la requête utilisateur
        }
    }

    /**
     * @param array<string, mixed>|null $props
     */
    public function recordBeacon(
        int $tenantId,
        ?int $actorUserId,
        string $category,
        string $name,
        ?string $subjectType,
        ?int $subjectId,
        ?int $durationSeconds,
        ?array $props
    ): bool {
        if ($tenantId < 1) {
            return false;
        }
        if (!in_array($name, AnalyticsEventName::beaconEventNames(), true)) {
            return false;
        }
        if (!in_array($category, AnalyticsEventCategory::all(), true)) {
            return false;
        }
        if ($subjectType !== null && $subjectType !== '' && !in_array($subjectType, AnalyticsSubjectType::all(), true)) {
            return false;
        }
        if ($subjectType !== null && $subjectType !== '' && ($subjectId === null || $subjectId < 1)) {
            return false;
        }
        if (!$this->beaconRateAllow()) {
            return false;
        }
        $durationSeconds = $durationSeconds !== null ? max(0, min(86400, $durationSeconds)) : null;
        if ($name !== AnalyticsEventName::TENANT_RECRUITMENT_CTA_CLICK && ($durationSeconds === null || $durationSeconds < 1)) {
            return false;
        }
        $props = $this->normalizeProps($props);
        $hash = $this->sessionHash();
        try {
            $this->repository->insert(
                $tenantId,
                $actorUserId,
                $hash,
                $category,
                $name,
                $subjectType !== null && $subjectType !== '' ? $subjectType : null,
                $subjectId,
                $durationSeconds,
                $props
            );
        } catch (\Throwable) {
            return false;
        }

        return true;
    }

    /**
     * @return list<array{name: string, created_at: string, actor_user_id: int|null}>
     */
    public function listRecentForEnlistment(int $tenantId, int $enlistmentId, int $limit = 20): array
    {
        return $this->repository->listRecentForEnlistmentSubject($tenantId, $enlistmentId, $limit);
    }

    /** @param array<string, mixed>|null $props @return array<string, mixed>|null */
    private function normalizeProps(?array $props): ?array
    {
        if ($props === null || $props === []) {
            return null;
        }
        $clean = [];
        foreach ($props as $k => $v) {
            if (!is_string($k) || $k === '' || count($clean) >= 24) {
                continue;
            }
            if (is_bool($v)) {
                $clean[$k] = $v;
            } elseif (is_int($v)) {
                $clean[$k] = $v;
            } elseif (is_float($v)) {
                $clean[$k] = $v;
            } elseif (is_string($v)) {
                $clean[$k] = mb_substr($v, 0, 200);
            }
        }
        $json = json_encode($clean, JSON_UNESCAPED_UNICODE);
        if ($json !== false && strlen($json) > self::PROPS_MAX_BYTES) {
            return null;
        }

        return $clean === [] ? null : $clean;
    }

    private function sessionHash(): ?string
    {
        Session::start();
        $sid = session_id();
        if ($sid === '') {
            return null;
        }
        $key = $_ENV['APP_KEY'] ?? getenv('APP_KEY') ?: 'comspec_analytics';

        return hash_hmac('sha256', $sid, $key);
    }

    private function beaconRateAllow(): bool
    {
        Session::start();
        $now = time();
        $bucket = $_SESSION['_analytics_beacon'] ?? null;
        if (!is_array($bucket) || !isset($bucket['t'], $bucket['n']) || (int) $bucket['t'] < $now - 60) {
            $_SESSION['_analytics_beacon'] = ['t' => $now, 'n' => 1];

            return true;
        }
        $n = (int) $bucket['n'] + 1;
        if ($n > self::BEACON_MAX_PER_MINUTE) {
            return false;
        }
        $_SESSION['_analytics_beacon'] = ['t' => (int) $bucket['t'], 'n' => $n];

        return true;
    }
}
