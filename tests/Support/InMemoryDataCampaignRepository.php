<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Repositories\DataCampaignRepository;

/**
 * Dépôt des campagnes de fiabilisation en mémoire (aucune base) : mêmes règles que le dépôt réel pour ce que les tests
 * vérifient (unicité des rappels, signalements ouverts, périmètre, instantanés).
 */
final class InMemoryDataCampaignRepository extends DataCampaignRepository
{
    /** @var array<int, array<string, mixed>> */
    public array $campaigns = [];

    /** @var array<int, array<string, mixed>> clé : campagne . ':' . membre */
    public array $memberRows = [];

    /** @var array<int, array<string, mixed>> */
    public array $flagRows = [];

    /** @var array<string, array<string, mixed>> clé : campagne:membre:slot */
    public array $reminderRows = [];

    /** @var array<int, array<string, mixed>> */
    public array $settingsByTenant = [];

    /** @var list<array{user_id: int, unit_id: int, display_name: string, callsign: string}> */
    public array $rosterRows = [];

    /** @var array<int, array{name: string, parent_id: int}> */
    public array $unitRows = [];

    /** @var array<int, string> état des demandes de correction liées (id => statut) */
    public array $requestStatus = [];

    public string $nowSql = '2026-12-01 10:00:00';

    private int $seq = 0;

    public function __construct()
    {
        parent::__construct(null);
    }

    public function schemaReady(): bool
    {
        return true;
    }

    public function settings(int $tenantId): array
    {
        return self::normalizeSettings($this->settingsByTenant[$tenantId] ?? []);
    }

    public function saveSettings(int $tenantId, array $s, int $userId): void
    {
        $this->settingsByTenant[$tenantId] = [
            'auto_enabled' => $s['auto_enabled'] ? 1 : 0,
            'open_md' => $s['open_md'],
            'close_md' => $s['close_md'],
            'blocks_json' => json_encode($s['blocks']),
            'unit_ids_json' => json_encode($s['unit_ids']),
            'reminder_days_json' => json_encode($s['reminder_days']),
            'last_auto_year' => $this->settingsByTenant[$tenantId]['last_auto_year'] ?? 0,
        ];
    }

    public function markAutoYear(int $tenantId, int $year): void
    {
        $this->settingsByTenant[$tenantId]['last_auto_year'] = $year;
    }

    public function tenantsWithAutoEnabled(): array
    {
        $out = [];
        foreach ($this->settingsByTenant as $tenantId => $row) {
            if (!empty($row['auto_enabled'])) {
                $out[] = $tenantId;
            }
        }

        return $out;
    }

    public function tenantsWithOpenCampaign(): array
    {
        $out = [];
        foreach ($this->campaigns as $c) {
            if ($c['status'] === self::STATUS_OPEN) {
                $out[(int) $c['tenant_id']] = (int) $c['tenant_id'];
            }
        }

        return array_values($out);
    }

    public function createCampaign(int $tenantId, array $c, int $userId): int
    {
        $id = ++$this->seq;
        $this->campaigns[$id] = self::hydrateCampaign([
            'id' => $id,
            'tenant_id' => $tenantId,
            'campaign_year' => $c['year'],
            'title' => $c['title'],
            'status' => self::STATUS_DRAFT,
            'blocks_json' => json_encode($c['blocks']),
            'unit_ids_json' => json_encode($c['unit_ids']),
            'reminder_days_json' => json_encode($c['reminder_days']),
            'opens_on' => $c['opens_on'],
            'due_on' => $c['due_on'],
            'auto_created' => $c['auto'] ? 1 : 0,
            'created_by' => $userId,
        ]);

        return $id;
    }

    public function findCampaign(int $id, int $tenantId): ?array
    {
        $c = $this->campaigns[$id] ?? null;

        return $c !== null && (int) $c['tenant_id'] === $tenantId ? $c : null;
    }

    public function listCampaigns(int $tenantId, int $limit = 50): array
    {
        return array_values(array_filter($this->campaigns, static fn (array $c): bool => (int) $c['tenant_id'] === $tenantId));
    }

    public function currentOpenCampaign(int $tenantId): ?array
    {
        $open = $this->openCampaigns($tenantId);

        return $open === [] ? null : $open[count($open) - 1];
    }

    public function openCampaigns(int $tenantId): array
    {
        return array_values(array_filter($this->campaigns, static fn (array $c): bool => (int) $c['tenant_id'] === $tenantId && $c['status'] === self::STATUS_OPEN));
    }

    public function autoCampaignForYear(int $tenantId, int $year): ?array
    {
        foreach ($this->campaigns as $c) {
            if ((int) $c['tenant_id'] === $tenantId && (int) $c['campaign_year'] === $year && !empty($c['auto_created'])) {
                return $c;
            }
        }

        return null;
    }

    public function setStatus(int $id, int $tenantId, string $status, int $userId): bool
    {
        if (!isset($this->campaigns[$id]) || (int) $this->campaigns[$id]['tenant_id'] !== $tenantId) {
            return false;
        }
        $this->campaigns[$id]['status'] = $status;
        if ($status === self::STATUS_OPEN) {
            $this->campaigns[$id]['opened_at'] = $this->campaigns[$id]['opened_at'] ?? $this->nowSql;
        }

        return true;
    }

    public function roster(int $tenantId): array
    {
        return $this->rosterRows;
    }

    public function addMembers(int $campaignId, int $tenantId, array $members): int
    {
        $n = 0;
        foreach ($members as $m) {
            $key = $campaignId . ':' . $m['user_id'];
            if (isset($this->memberRows[$key])) {
                continue;
            }
            $this->memberRows[$key] = [
                'campaign_id' => $campaignId, 'tenant_id' => $tenantId, 'user_id' => $m['user_id'], 'unit_id' => $m['unit_id'],
                'blocks_json' => null, 'snapshot_json' => null, 'snapshot_hash' => null, 'confirmed_at' => null,
            ];
            $n++;
        }

        return $n;
    }

    public function memberRow(int $campaignId, int $userId): ?array
    {
        $r = $this->memberRows[$campaignId . ':' . $userId] ?? null;

        return $r === null ? null : self::hydrateMember($r);
    }

    public function members(int $campaignId, int $tenantId, ?array $onlyUsers = null): array
    {
        $out = [];
        foreach ($this->memberRows as $r) {
            if ((int) $r['campaign_id'] !== $campaignId || ($onlyUsers !== null && !in_array((int) $r['user_id'], $onlyUsers, true))) {
                continue;
            }
            $row = self::hydrateMember($r);
            $name = '';
            foreach ($this->rosterRows as $m) {
                if ($m['user_id'] === (int) $r['user_id']) {
                    $name = $m['display_name'];
                }
            }
            $row['display_name'] = $name;
            $row['callsign'] = '';
            $row['open_flags'] = count(array_filter($this->flagRows, fn (array $f): bool => $this->isOpen($f) && (int) $f['campaign_id'] === $campaignId && (int) $f['user_id'] === (int) $r['user_id']));
            $row['last_reminded_at'] = $this->lastReminderAt($campaignId, (int) $r['user_id']);
            $out[] = $row;
        }

        return $out;
    }

    public function saveAnswers(int $campaignId, int $userId, array $answers): void
    {
        $this->memberRows[$campaignId . ':' . $userId]['blocks_json'] = json_encode($answers);
    }

    public function confirm(int $campaignId, int $userId, array $answers, array $snapshot, string $hash): void
    {
        $key = $campaignId . ':' . $userId;
        $this->memberRows[$key]['blocks_json'] = json_encode($answers);
        $this->memberRows[$key]['snapshot_json'] = json_encode($snapshot);
        $this->memberRows[$key]['snapshot_hash'] = $hash;
        $this->memberRows[$key]['confirmed_at'] = $this->nowSql;
    }

    public function latestConfirmation(int $tenantId, int $userId): ?array
    {
        $best = null;
        foreach ($this->memberRows as $r) {
            if ((int) $r['user_id'] === $userId && $r['confirmed_at'] !== null) {
                $best = $r;
            }
        }
        if ($best === null) {
            return null;
        }
        $out = self::hydrateMember($best);
        $c = $this->campaigns[(int) $best['campaign_id']];
        $out['campaign_year'] = (int) $c['campaign_year'];
        $out['title'] = (string) $c['title'];

        return $out;
    }

    public function historyForUser(int $tenantId, int $userId, int $limit = 20): array
    {
        $out = [];
        foreach ($this->memberRows as $r) {
            if ((int) $r['user_id'] === $userId) {
                $c = $this->campaigns[(int) $r['campaign_id']];
                $out[] = ['campaign_id' => $r['campaign_id'], 'confirmed_at' => $r['confirmed_at'], 'campaign_year' => $c['campaign_year'], 'title' => $c['title'], 'status' => $c['status']];
            }
        }

        return $out;
    }

    public function addFlag(array $f): int
    {
        $id = count($this->flagRows) + 1;
        $this->flagRows[$id] = $f + ['id' => $id, 'status' => self::FLAG_OPEN, 'answer' => null, 'created_at' => $this->nowSql];

        return $id;
    }

    public function findFlag(int $flagId, int $tenantId): ?array
    {
        return $this->flagRows[$flagId] ?? null;
    }

    public function flags(int $campaignId, int $tenantId, ?string $status = null, ?array $onlyUsers = null): array
    {
        $out = [];
        foreach ($this->flagRows as $f) {
            if ((int) $f['campaign_id'] !== $campaignId || ($status !== null && $f['status'] !== $status) || ($onlyUsers !== null && !in_array((int) $f['user_id'], $onlyUsers, true))) {
                continue;
            }
            $out[] = $f + ['display_name' => '', 'callsign' => '', 'request_status' => $this->requestStatus[(int) ($f['correction_request_id'] ?? 0)] ?? null, 'is_open' => $this->isOpen($f)];
        }

        return $out;
    }

    public function flagsForUser(int $campaignId, int $tenantId, int $userId): array
    {
        $out = [];
        foreach ($this->flagRows as $f) {
            if ((int) $f['campaign_id'] === $campaignId && (int) $f['user_id'] === $userId && $f['status'] !== self::FLAG_WITHDRAWN) {
                $out[] = $f + ['request_status' => $this->requestStatus[(int) ($f['correction_request_id'] ?? 0)] ?? null, 'request_note' => null];
            }
        }

        return $out;
    }

    public function setFlagStatus(int $flagId, int $tenantId, string $status, int $userId, string $answer = ''): bool
    {
        if (!isset($this->flagRows[$flagId]) || $this->flagRows[$flagId]['status'] !== self::FLAG_OPEN) {
            return false;
        }
        $this->flagRows[$flagId]['status'] = $status;
        $this->flagRows[$flagId]['answer'] = $answer !== '' ? $answer : null;

        return true;
    }

    public function doneSlots(int $campaignId): array
    {
        $out = [];
        foreach ($this->reminderRows as $r) {
            if ((int) $r['campaign_id'] === $campaignId && (int) $r['user_id'] === 0) {
                $out[] = (string) $r['slot'];
            }
        }

        return $out;
    }

    public function reserveReminder(int $campaignId, int $tenantId, int $userId, string $slot, int $sentBy = 0): bool
    {
        $key = $campaignId . ':' . $userId . ':' . $slot;
        if (isset($this->reminderRows[$key])) {
            return false;
        }
        $this->reminderRows[$key] = ['campaign_id' => $campaignId, 'user_id' => $userId, 'slot' => $slot, 'created_at' => $this->nowSql];

        return true;
    }

    public function lastReminderAt(int $campaignId, int $userId): string
    {
        $last = '';
        foreach ($this->reminderRows as $r) {
            if ((int) $r['campaign_id'] === $campaignId && (int) $r['user_id'] === $userId && (string) $r['created_at'] > $last) {
                $last = (string) $r['created_at'];
            }
        }

        return $last;
    }

    public function usersWithSlot(int $campaignId, string $slot): array
    {
        $out = [];
        foreach ($this->reminderRows as $r) {
            if ((int) $r['campaign_id'] === $campaignId && $r['slot'] === $slot && (int) $r['user_id'] > 0) {
                $out[] = (int) $r['user_id'];
            }
        }

        return $out;
    }

    public function units(int $tenantId): array
    {
        return $this->unitRows;
    }

    public function commandedUnitIds(int $tenantId, int $userId): array
    {
        return [];
    }

    public function primaryUnitId(int $userId): int
    {
        foreach ($this->rosterRows as $m) {
            if ($m['user_id'] === $userId) {
                return $m['unit_id'];
            }
        }

        return 0;
    }

    /** @param array<string, mixed> $f */
    private function isOpen(array $f): bool
    {
        $rid = (int) ($f['correction_request_id'] ?? 0);

        return $f['status'] === self::FLAG_OPEN && ($rid === 0 || !isset($this->requestStatus[$rid]) || $this->requestStatus[$rid] === 'pending');
    }
}
