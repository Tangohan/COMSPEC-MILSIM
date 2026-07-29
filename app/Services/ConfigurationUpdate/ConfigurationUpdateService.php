<?php

declare(strict_types=1);

namespace App\Services\ConfigurationUpdate;

use App\Core\Container;
use App\Repositories\ConfigurationUpdateRepository;
use App\Repositories\TenantRepository;
use App\Services\Audit\AuditAction;
use App\Services\Audit\AuditService;

/**
 * API centrale de configuration post-mise à jour.
 *
 * Absence de ligne tenant_configuration_updates = PENDING (création lazy).
 * Éligibilité = données réelles via ConfigurationUpdateCatalog / Probes.
 */
final class ConfigurationUpdateService
{
    public const STATUS_PENDING = 'PENDING';
    public const STATUS_SEEN = 'SEEN';
    public const STATUS_IN_PROGRESS = 'IN_PROGRESS';
    public const STATUS_COMPLETED = 'COMPLETED';
    public const STATUS_DISMISSED = 'DISMISSED';
    public const STATUS_NOT_APPLICABLE = 'NOT_APPLICABLE';

    public const INTRO_SEEN_SETTING = 'configuration_updates_intro_seen_at';

    /** Schéma fonctionnel courant (bump lors d’évolutions majeures du moteur). */
    public const CURRENT_SCHEMA_VERSION = 1;

    public function __construct(
        private ConfigurationUpdateRepository $repo,
        private ConfigurationUpdateCatalog $catalog,
        private TenantRepository $tenants,
        private ?AuditService $audit = null,
    ) {
        try {
            $this->audit ??= Container::get(AuditService::class);
        } catch (\Throwable) {
            $this->audit = null;
        }
    }

    public function isAvailable(): bool
    {
        return $this->repo->tablesExist();
    }

    /**
     * Synchronise le catalogue PHP vers la table système (idempotent).
     */
    public function syncCatalogToDatabase(): void
    {
        if (!$this->isAvailable()) {
            return;
        }
        // Les titres/chemins sont déjà seedés par migration ; rien d’obligatoire ici.
        // Point d’extension futur : upsert depuis le catalogue pour éviter une migration SQL.
    }

    /**
     * @return list<array{
     *   code: string,
     *   title: string,
     *   description: string,
     *   level: string,
     *   level_label: string,
     *   status: string,
     *   status_label: string,
     *   configure_path: string,
     *   configure_url: string,
     *   estimate_minutes: ?int,
     *   dismissible: bool,
     *   blocking: bool,
     *   is_new: bool,
     *   completed_at: ?string,
     *   dismissed_at: ?string,
     *   remind_at: ?string,
     *   progress_step: ?string
     * }>
     */
    public function listForTenant(int $tenantId, bool $includeTerminal = true): array
    {
        if (!$this->isAvailable() || $tenantId <= 0) {
            return [];
        }

        $systemRows = $this->repo->listActiveSystemUpdates();
        $byCode = [];
        foreach ($systemRows as $row) {
            $byCode[(string) $row['code']] = $row;
        }
        $states = $this->repo->mapTenantStates($tenantId);
        $out = [];

        foreach ($this->catalog->definitions() as $def) {
            $sys = $byCode[$def->code] ?? null;
            if ($sys === null) {
                continue;
            }
            $updateId = (int) $sys['id'];
            $state = $states[$updateId] ?? null;
            $status = $this->resolveStatus($tenantId, $def, $state);

            if (!$includeTerminal && in_array($status, [self::STATUS_COMPLETED, self::STATUS_DISMISSED, self::STATUS_NOT_APPLICABLE], true)) {
                continue;
            }

            $releasedAt = (string) ($sys['released_at'] ?? '');
            $seenAt = $state['updated_at'] ?? null;
            $isNew = $status === self::STATUS_PENDING
                && $releasedAt !== ''
                && strtotime($releasedAt) > strtotime('-45 days');

            $out[] = [
                'code' => $def->code,
                'title' => (string) ($sys['title'] ?? $def->title),
                'description' => (string) ($sys['description'] ?? $def->description),
                'level' => (string) ($sys['configuration_level'] ?? $def->level),
                'level_label' => $this->levelLabel((string) ($sys['configuration_level'] ?? $def->level)),
                'status' => $status,
                'status_label' => $this->statusLabel($status),
                'configure_path' => (string) ($sys['configure_path'] ?? $def->configurePath),
                'configure_url' => $this->buildUrl((string) ($sys['configure_path'] ?? $def->configurePath)),
                'estimate_minutes' => isset($sys['estimate_minutes']) ? (int) $sys['estimate_minutes'] : $def->estimateMinutes,
                'dismissible' => (bool) ($sys['dismissible'] ?? $def->dismissible),
                'blocking' => (bool) ($sys['blocking'] ?? $def->blocking),
                'is_new' => $isNew && $seenAt === null,
                'completed_at' => isset($state['completed_at']) ? (string) $state['completed_at'] : null,
                'dismissed_at' => isset($state['dismissed_at']) ? (string) $state['dismissed_at'] : null,
                'remind_at' => isset($state['remind_at']) ? (string) $state['remind_at'] : null,
                'progress_step' => isset($state['progress_step']) ? (string) $state['progress_step'] : null,
                'update_id' => $updateId,
            ];
        }

        return $out;
    }

    /**
     * Mises à jour encore actionnables (pending / seen / in_progress, rappel écoulé).
     *
     * @return list<array<string, mixed>>
     */
    public function getActionableUpdates(int $tenantId): array
    {
        $items = $this->listForTenant($tenantId, true);
        $now = time();
        $out = [];
        foreach ($items as $item) {
            $status = $item['status'];
            if (in_array($status, [self::STATUS_COMPLETED, self::STATUS_NOT_APPLICABLE], true)) {
                continue;
            }
            if ($status === self::STATUS_DISMISSED) {
                $remind = $item['remind_at'] ?? null;
                if ($remind === null || $remind === '' || strtotime((string) $remind) > $now) {
                    continue;
                }
            }
            $out[] = $item;
        }

        return $out;
    }

    /**
     * @return array{
     *   actionable: list<array<string, mixed>>,
     *   completed: list<array<string, mixed>>,
     *   dismissed: list<array<string, mixed>>,
     *   not_applicable: list<array<string, mixed>>,
     *   counts: array{actionable: int, recommended: int, required: int, completed: int},
     *   show_intro: bool,
     *   nav_badge: int
     * }
     */
    public function hubSummary(int $tenantId): array
    {
        $all = $this->listForTenant($tenantId, true);
        $actionable = [];
        $completed = [];
        $dismissed = [];
        $na = [];
        $recommended = 0;
        $required = 0;

        foreach ($all as $item) {
            switch ($item['status']) {
                case self::STATUS_COMPLETED:
                    $completed[] = $item;
                    break;
                case self::STATUS_DISMISSED:
                    $dismissed[] = $item;
                    break;
                case self::STATUS_NOT_APPLICABLE:
                    $na[] = $item;
                    break;
                default:
                    $actionable[] = $item;
                    if ($item['level'] === ConfigurationUpdateDefinition::LEVEL_RECOMMENDED) {
                        $recommended++;
                    }
                    if ($item['level'] === ConfigurationUpdateDefinition::LEVEL_REQUIRED || !empty($item['blocking'])) {
                        $required++;
                    }
            }
        }

        $settings = $this->tenants->getSettings($tenantId);
        $introSeen = trim((string) ($settings[self::INTRO_SEEN_SETTING] ?? '')) !== '';

        return [
            'actionable' => $actionable,
            'completed' => $completed,
            'dismissed' => $dismissed,
            'not_applicable' => $na,
            'counts' => [
                'actionable' => count($actionable),
                'recommended' => $recommended,
                'required' => $required,
                'completed' => count($completed),
            ],
            'show_intro' => !$introSeen && $actionable !== [],
            'nav_badge' => count($actionable),
        ];
    }

    public function markIntroSeen(int $tenantId): void
    {
        $this->tenants->mergeSettings($tenantId, [
            self::INTRO_SEEN_SETTING => date('c'),
        ]);
    }

    public function markSeen(int $tenantId, string $code, ?int $userId = null): void
    {
        $this->transition($tenantId, $code, self::STATUS_SEEN, $userId, AuditAction::CONFIGURATION_UPDATE_SEEN);
    }

    public function markStarted(int $tenantId, string $code, ?int $userId = null, ?string $step = null): void
    {
        $this->transition($tenantId, $code, self::STATUS_IN_PROGRESS, $userId, AuditAction::CONFIGURATION_UPDATE_STARTED, $step);
    }

    public function markCompleted(int $tenantId, string $code, ?int $userId = null, ?array $metadata = null): void
    {
        $this->transition($tenantId, $code, self::STATUS_COMPLETED, $userId, AuditAction::CONFIGURATION_UPDATE_COMPLETED, null, $metadata);
    }

    public function dismiss(int $tenantId, string $code, ?int $userId = null, ?string $remindAt = null): void
    {
        $sys = $this->repo->findSystemByCode($code);
        if ($sys === null) {
            return;
        }
        $this->repo->upsertTenantState(
            $tenantId,
            (int) $sys['id'],
            self::STATUS_DISMISSED,
            $userId,
            null,
            null,
            null,
            $remindAt
        );
        $this->auditLog(AuditAction::CONFIGURATION_UPDATE_DISMISSED, $tenantId, $userId, $code);
    }

    public function reopen(int $tenantId, string $code, ?int $userId = null): void
    {
        $this->transition($tenantId, $code, self::STATUS_PENDING, $userId, AuditAction::CONFIGURATION_UPDATE_REOPENED);
    }

    public function isCompleted(int $tenantId, string $code): bool
    {
        foreach ($this->listForTenant($tenantId, true) as $item) {
            if ($item['code'] === $code) {
                return $item['status'] === self::STATUS_COMPLETED
                    || $item['status'] === self::STATUS_NOT_APPLICABLE;
            }
        }

        return false;
    }

    /**
     * Après création d’une communauté déjà configurée par le wizard : marquer satisfait / N/A.
     */
    public function markSatisfiedForNewTenant(int $tenantId, ?int $userId = null): void
    {
        if (!$this->isAvailable()) {
            return;
        }
        foreach ($this->catalog->definitions() as $def) {
            $sys = $this->repo->findSystemByCode($def->code);
            if ($sys === null) {
                continue;
            }
            $applicable = (bool) ($def->isApplicable)($tenantId);
            if (!$applicable) {
                $this->repo->upsertTenantState($tenantId, (int) $sys['id'], self::STATUS_NOT_APPLICABLE, $userId);
                continue;
            }
            if (($def->isSatisfied)($tenantId)) {
                $this->repo->upsertTenantState($tenantId, (int) $sys['id'], self::STATUS_COMPLETED, $userId);
            }
        }

        $this->tenants->mergeSettings($tenantId, [
            self::INTRO_SEEN_SETTING => date('c'),
        ]);

        try {
            $pdo = \App\Core\Database::getPdo();
            $pdo->prepare(
                'UPDATE tenants SET configuration_schema_version = ? WHERE id = ?'
            )->execute([self::CURRENT_SCHEMA_VERSION, $tenantId]);
        } catch (\Throwable) {
            // colonne absente tant que migration non jouée
        }
    }

    /**
     * Recalcule les états à partir des données métier (auto-complétion sans sollicitation).
     */
    public function refreshFromData(int $tenantId, ?int $userId = null): void
    {
        if (!$this->isAvailable()) {
            return;
        }
        $states = $this->repo->mapTenantStates($tenantId);
        foreach ($this->catalog->definitions() as $def) {
            $sys = $this->repo->findSystemByCode($def->code);
            if ($sys === null) {
                continue;
            }
            $updateId = (int) $sys['id'];
            $existing = $states[$updateId] ?? null;
            $current = $existing['status'] ?? self::STATUS_PENDING;

            if (!($def->isApplicable)($tenantId)) {
                if ($current !== self::STATUS_NOT_APPLICABLE) {
                    $this->repo->upsertTenantState($tenantId, $updateId, self::STATUS_NOT_APPLICABLE, $userId);
                }
                continue;
            }

            if (($def->isSatisfied)($tenantId)) {
                if (!in_array($current, [self::STATUS_COMPLETED, self::STATUS_DISMISSED], true)) {
                    $this->repo->upsertTenantState($tenantId, $updateId, self::STATUS_COMPLETED, $userId);
                }
            }
        }
    }

    private function resolveStatus(int $tenantId, ConfigurationUpdateDefinition $def, ?array $state): string
    {
        if (!($def->isApplicable)($tenantId)) {
            return self::STATUS_NOT_APPLICABLE;
        }

        $stored = $state['status'] ?? null;
        if ($stored === self::STATUS_DISMISSED) {
            return self::STATUS_DISMISSED;
        }
        if ($stored === self::STATUS_COMPLETED) {
            return self::STATUS_COMPLETED;
        }
        if (($def->isSatisfied)($tenantId)) {
            return self::STATUS_COMPLETED;
        }
        if ($stored === self::STATUS_IN_PROGRESS) {
            return self::STATUS_IN_PROGRESS;
        }
        if ($stored === self::STATUS_SEEN) {
            return self::STATUS_SEEN;
        }

        return self::STATUS_PENDING;
    }

    /**
     * @param array<string, mixed>|null $metadata
     */
    private function transition(
        int $tenantId,
        string $code,
        string $status,
        ?int $userId,
        string $auditAction,
        ?string $step = null,
        ?array $metadata = null,
    ): void {
        $sys = $this->repo->findSystemByCode($code);
        if ($sys === null) {
            return;
        }
        $this->repo->upsertTenantState(
            $tenantId,
            (int) $sys['id'],
            $status,
            $userId,
            $metadata,
            $step,
            null,
            null
        );
        $this->auditLog($auditAction, $tenantId, $userId, $code);
    }

    private function auditLog(string $action, int $tenantId, ?int $userId, string $code): void
    {
        if ($this->audit === null) {
            return;
        }
        try {
            $this->audit->log(
                $action,
                $tenantId,
                $userId,
                'configuration_update',
                null,
                null,
                $code
            );
        } catch (\Throwable) {
            // ne jamais casser le parcours
        }
    }

    private function levelLabel(string $level): string
    {
        return match ($level) {
            ConfigurationUpdateDefinition::LEVEL_REQUIRED => 'Obligatoire',
            ConfigurationUpdateDefinition::LEVEL_INFORMATIVE => 'Information',
            default => 'Recommandé',
        };
    }

    private function statusLabel(string $status): string
    {
        return match ($status) {
            self::STATUS_COMPLETED => 'Terminé',
            self::STATUS_DISMISSED => 'Ignoré',
            self::STATUS_IN_PROGRESS => 'En cours',
            self::STATUS_SEEN => 'Vu',
            self::STATUS_NOT_APPLICABLE => 'Non concerné',
            default => 'À configurer',
        };
    }

    private function buildUrl(string $path): string
    {
        if (function_exists('url')) {
            return url($path);
        }

        return '/' . ltrim($path, '/');
    }
}
