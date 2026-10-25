<?php

declare(strict_types=1);

namespace App\Services\Community;

use App\Core\Database;
use App\Repositories\UserRepository;
use PDO;
use Throwable;

/**
 * Retrait d’une communauté par l’administration du site, en deux temps :
 *
 * 1. Archivage (réversible) : les appartenances passent en « archived », les comptes dont
 *    c’était la communauté courante sont rattachés à une autre communauté de la personne
 *    (ou à « Sans communauté »). Aucune donnée n’est supprimée ; la restauration remet tout.
 * 2. Suppression définitive (seulement une communauté déjà archivée) : sauvegarde JSON
 *    compressée de toutes les lignes `tenant_id` dans storage/private/tenant-deletions,
 *    puis DELETE de la ligne `tenants` (les clés étrangères en cascade emportent ses données).
 *
 * Les comptes joueurs ne sont jamais supprimés : ils sont déplacés avant le DELETE, et la
 * suppression est refusée s’il en reste un rattaché à la communauté.
 */
final class TenantRemovalService
{
    public const BACKUP_DIR = 'storage/private/tenant-deletions';

    /** Statuts d’abonnement qui bloquent la suppression (paiement encore en cours). */
    private const BILLING_LIVE_STATUSES = ['active', 'trialing', 'past_due'];

    /**
     * Journal des interventions de l’administration du site dans la communauté (clés étrangères
     * RESTRICT) : purgé juste avant la suppression, enfants d’abord. Il figure dans la sauvegarde.
     */
    public const INTERVENTION_TABLES = [
        'platform_admin_tenant_actions',
        'platform_admin_tenant_errors',
        'platform_admin_tenant_checkpoints',
        'platform_admin_tenant_sessions',
    ];

    /** @var array<string, array{deleted: bool, rule: string}>|null */
    private ?array $tenantTables = null;

    public function __construct(
        private ?PDO $pdo = null,
        private ?string $backupDir = null,
    ) {}

    private function pdo(): PDO
    {
        return $this->pdo ??= Database::getPdo();
    }

    /**
     * @return array<string, mixed>|null null si la communauté n’existe pas
     */
    public function preview(int $tenantId): ?array
    {
        $tenant = $this->findTenant($tenantId);
        if ($tenant === null) {
            return null;
        }
        $protected = $this->isProtected($tenant);
        $plan = $protected ? [] : $this->planRehome($tenantId);
        $toOther = 0;
        $toNoOrg = 0;
        $blocked = 0;
        foreach ($plan as $step) {
            if ($step['kind'] === 'other') {
                $toOther++;
            } elseif ($step['kind'] === 'no_org') {
                $toNoOrg++;
            } else {
                $blocked++;
            }
        }

        $technical = $this->countTechnicalAccounts($tenantId);
        $tables = [];
        $totalDeleted = 0;
        $totalKept = 0;
        $restrictBlockers = [];
        foreach ($this->tenantTables() as $table => $meta) {
            // Les comptes humains sont relogés avant la suppression : seuls les comptes techniques partent.
            $count = $table === 'users' ? $technical : $this->countRows($table, $tenantId);
            if ($count < 1) {
                continue;
            }
            $purged = in_array($table, self::INTERVENTION_TABLES, true);
            $deleted = $meta['deleted'] || $purged;
            $tables[] = ['table' => $table, 'rows' => $count, 'deleted' => $deleted, 'rule' => $purged ? 'PURGE' : $meta['rule']];
            if ($deleted) {
                $totalDeleted += $count;
            } else {
                $totalKept += $count;
            }
            if (!$purged && ($meta['rule'] === 'RESTRICT' || $meta['rule'] === 'NO ACTION')) {
                $restrictBlockers[] = $table;
            }
        }
        usort($tables, static fn (array $a, array $b): int => [$b['rows'], $a['table']] <=> [$a['rows'], $b['table']]);

        $archive = TenantArchiveState::info($tenant);
        $billingLive = $this->billingLive($tenant);

        $archiveBlockers = [];
        $deleteBlockers = [];
        if ($protected) {
            $archiveBlockers[] = 'Espace technique du site : il ne peut être ni archivé ni supprimé.';
            $deleteBlockers[] = 'Espace technique du site : il ne peut être ni archivé ni supprimé.';
        } else {
            if ($toNoOrg > 0 && $this->noOrgTenantId() === null) {
                $archiveBlockers[] = 'L’espace « Sans communauté » (adresse « default ») est introuvable : impossible de reloger les comptes.';
            }
            if ($archive === null) {
                $deleteBlockers[] = 'Archivez d’abord la communauté : la suppression définitive ne s’applique qu’à une communauté archivée.';
            }
            if ($billingLive) {
                $deleteBlockers[] = 'Un abonnement payant est encore en cours : résiliez-le chez le prestataire de paiement avant la suppression.';
            }
            if ($blocked > 0) {
                $deleteBlockers[] = $blocked . ' compte(s) ne peuvent pas être relogés : la suppression les emporterait, elle est donc refusée.';
            }
            foreach ($restrictBlockers as $table) {
                $deleteBlockers[] = 'La table « ' . $table . ' » interdit la suppression tant qu’elle contient des lignes de cette communauté.';
            }
        }

        return [
            'tenant' => $tenant,
            'protected' => $protected,
            'archive' => $archive,
            'billing_live' => $billingLive,
            'accounts' => [
                'members' => $this->countMemberships($tenantId, 'active'),
                'archived_memberships' => $this->countMemberships($tenantId, 'archived'),
                'home' => count($plan),
                'to_other' => $toOther,
                'to_no_org' => $toNoOrg,
                'blocked' => $blocked,
                'technical' => $technical,
            ],
            'tables' => $tables,
            'tables_scanned' => count($this->tenantTables()),
            'rows_deleted' => $totalDeleted,
            'rows_kept' => $totalKept,
            'archive_blockers' => $archiveBlockers,
            'delete_blockers' => $deleteBlockers,
        ];
    }

    /**
     * @return array{ok: bool, error?: string, memberships?: int, moved_other?: int, moved_no_org?: int, not_moved?: int}
     */
    public function archive(int $tenantId, ?int $actorId, string $reason = ''): array
    {
        $tenant = $this->findTenant($tenantId);
        if ($tenant === null) {
            return ['ok' => false, 'error' => 'Communauté introuvable.'];
        }
        if ($this->isProtected($tenant)) {
            return ['ok' => false, 'error' => 'Espace technique du site : il ne peut pas être archivé.'];
        }
        if (TenantArchiveState::isArchived($tenant)) {
            return ['ok' => false, 'error' => 'Cette communauté est déjà archivée.'];
        }
        $preview = $this->preview($tenantId);
        if ($preview !== null && $preview['archive_blockers'] !== []) {
            return ['ok' => false, 'error' => (string) $preview['archive_blockers'][0]];
        }

        $memberships = 0;
        if ($this->tableExists('user_community_memberships')) {
            $st = $this->pdo()->prepare(
                "UPDATE user_community_memberships SET status = 'archived', updated_at = ?
                 WHERE tenant_id = ? AND status = 'active'"
            );
            $st->execute([$this->now(), $tenantId]);
            $memberships = $st->rowCount();
        }
        $moved = $this->rehome($tenantId);

        $settings = $this->decodeSettings($tenant['settings'] ?? null);
        $settings[TenantArchiveState::SETTINGS_KEY] = [
            'archived_at' => $this->now(),
            'archived_by' => $actorId,
            'reason' => mb_substr(trim($reason), 0, 500),
            'memberships_archived' => $memberships,
            'moved_other' => $moved['other'],
            'moved_no_org' => $moved['no_org'],
            'moved_users' => $moved['users'],
        ];
        $this->writeSettings($tenantId, $settings);

        return [
            'ok' => true,
            'memberships' => $memberships,
            'moved_other' => $moved['other'],
            'moved_no_org' => $moved['no_org'],
            'not_moved' => $moved['blocked'],
        ];
    }

    /**
     * @return array{ok: bool, error?: string, memberships?: int}
     */
    public function restore(int $tenantId): array
    {
        $tenant = $this->findTenant($tenantId);
        if ($tenant === null) {
            return ['ok' => false, 'error' => 'Communauté introuvable.'];
        }
        if (!TenantArchiveState::isArchived($tenant)) {
            return ['ok' => false, 'error' => 'Cette communauté n’est pas archivée.'];
        }
        $settings = $this->decodeSettings($tenant['settings'] ?? null);
        $movedBack = $this->bringBack($tenantId, $settings[TenantArchiveState::SETTINGS_KEY] ?? []);
        $memberships = 0;
        if ($this->tableExists('user_community_memberships')) {
            $st = $this->pdo()->prepare(
                "UPDATE user_community_memberships SET status = 'active', left_at = NULL, updated_at = ?
                 WHERE tenant_id = ? AND status = 'archived'"
            );
            $st->execute([$this->now(), $tenantId]);
            $memberships = $st->rowCount();
        }
        unset($settings[TenantArchiveState::SETTINGS_KEY]);
        $this->writeSettings($tenantId, $settings);

        return ['ok' => true, 'memberships' => $memberships, 'moved_back' => $movedBack];
    }

    /**
     * @return array{ok: bool, error?: string, backup?: string, rows_deleted?: int, tables?: int, tenant?: array<string, mixed>}
     */
    public function delete(int $tenantId, string $typedSlug): array
    {
        $tenant = $this->findTenant($tenantId);
        if ($tenant === null) {
            return ['ok' => false, 'error' => 'Communauté introuvable.'];
        }
        $slug = strtolower(trim((string) ($tenant['slug'] ?? '')));
        if ($slug === '' || strtolower(trim($typedSlug)) !== $slug) {
            return ['ok' => false, 'error' => 'Recopiez exactement l’adresse courte « ' . $slug . ' » pour confirmer.'];
        }

        // Des comptes ont pu rejoindre la communauté (ou y revenir) depuis l’archivage.
        $this->rehome($tenantId);
        $preview = $this->preview($tenantId);
        if ($preview === null) {
            return ['ok' => false, 'error' => 'Communauté introuvable.'];
        }
        if ($preview['delete_blockers'] !== []) {
            return ['ok' => false, 'error' => (string) $preview['delete_blockers'][0]];
        }

        try {
            $backup = $this->writeBackup($tenant);
        } catch (Throwable $e) {
            return ['ok' => false, 'error' => 'Sauvegarde impossible, rien n’a été supprimé : ' . $e->getMessage()];
        }

        $pdo = $this->pdo();
        try {
            $pdo->beginTransaction();
            $this->purgeInterventionLog($tenantId);
            $st = $pdo->prepare('DELETE FROM tenants WHERE id = ?');
            $st->execute([$tenantId]);
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            return ['ok' => false, 'error' => 'Suppression refusée par la base, rien n’a été supprimé : ' . $e->getMessage(), 'backup' => $backup];
        }

        return [
            'ok' => true,
            'backup' => $backup,
            'rows_deleted' => (int) $preview['rows_deleted'],
            'tables' => count(array_filter($preview['tables'], static fn (array $t): bool => $t['deleted'])),
            'tenant' => $tenant,
            'accounts' => $preview['accounts'],
        ];
    }

    /** Supprime les sessions d’intervention admin (et leurs actions, erreurs, points de reprise). */
    private function purgeInterventionLog(int $tenantId): void
    {
        foreach (self::INTERVENTION_TABLES as $table) {
            if (!$this->tableExists($table)) {
                continue;
            }
            if ($table !== 'platform_admin_tenant_sessions' && $this->tableExists('platform_admin_tenant_sessions')) {
                $this->pdo()->prepare(
                    'DELETE FROM `' . $table . '` WHERE tenant_id = ? OR session_id IN (SELECT id FROM platform_admin_tenant_sessions WHERE tenant_id = ?)'
                )->execute([$tenantId, $tenantId]);
                continue;
            }
            $this->pdo()->prepare('DELETE FROM `' . $table . '` WHERE tenant_id = ?')->execute([$tenantId]);
        }
    }

    /**
     * Comptes humains dont la communauté courante est celle-ci, et leur destination.
     *
     * @return list<array{user_id: int, kind: string, target: ?int}>
     */
    public function planRehome(int $tenantId): array
    {
        $plan = [];
        $noOrg = $this->noOrgTenantId();
        foreach ($this->homeHumanUserIds($tenantId) as $userId) {
            $target = $this->otherCommunityFor($userId, $tenantId);
            if ($target !== null) {
                $plan[] = ['user_id' => $userId, 'kind' => 'other', 'target' => $target];
            } elseif ($noOrg !== null && $noOrg !== $tenantId) {
                $plan[] = ['user_id' => $userId, 'kind' => 'no_org', 'target' => $noOrg];
            } else {
                $plan[] = ['user_id' => $userId, 'kind' => 'blocked', 'target' => null];
            }
        }

        return $plan;
    }

    /**
     * @return array{other: int, no_org: int, blocked: int, users: list<array{user_id: int, to: int}>}
     */
    private function rehome(int $tenantId): array
    {
        $out = ['other' => 0, 'no_org' => 0, 'blocked' => 0, 'users' => []];
        $noOrg = $this->noOrgTenantId();
        foreach ($this->planRehome($tenantId) as $step) {
            $userId = $step['user_id'];
            $done = false;
            if ($step['kind'] === 'other' && $this->moveUser($userId, $tenantId, (int) $step['target'])) {
                $out['other']++;
                $out['users'][] = ['user_id' => $userId, 'to' => (int) $step['target']];
                $done = true;
            }
            if (!$done && $noOrg !== null && $noOrg !== $tenantId) {
                $this->ensureActiveMembership($userId, $noOrg);
                if ($this->moveUser($userId, $tenantId, $noOrg)) {
                    $out['no_org']++;
                    $out['users'][] = ['user_id' => $userId, 'to' => $noOrg];
                    $done = true;
                }
            }
            if (!$done) {
                $out['blocked']++;
            }
        }

        return $out;
    }

    /**
     * Ramène dans la communauté restaurée les comptes que l'archivage avait déplacés, s'ils n'ont pas
     * changé de communauté depuis. Les archives antérieures à `moved_users` n'ont pas la liste : on
     * ramène alors les membres archivés qui sont restés dans « Aucune organisation ».
     */
    private function bringBack(int $tenantId, mixed $archive): int
    {
        $moves = [];
        foreach (is_array($archive) && is_array($archive['moved_users'] ?? null) ? $archive['moved_users'] : [] as $m) {
            if (is_array($m) && (int) ($m['user_id'] ?? 0) > 0 && (int) ($m['to'] ?? 0) > 0) {
                $moves[(int) $m['user_id']] = (int) $m['to'];
            }
        }
        $noOrg = $this->noOrgTenantId();
        if ($noOrg !== null && $noOrg !== $tenantId && $this->tableExists('user_community_memberships')) {
            $st = $this->pdo()->prepare(
                "SELECT m.user_id FROM user_community_memberships m
                 INNER JOIN users u ON u.id = m.user_id
                 WHERE m.tenant_id = ? AND m.status = 'archived' AND u.tenant_id = ?"
            );
            $st->execute([$tenantId, $noOrg]);
            foreach ($st->fetchAll(PDO::FETCH_COLUMN) ?: [] as $userId) {
                $moves[(int) $userId] ??= $noOrg;
            }
        }
        $n = 0;
        foreach ($moves as $userId => $from) {
            if ($this->moveUser($userId, $from, $tenantId)) {
                $n++;
            }
        }

        return $n;
    }

    private function moveUser(int $userId, int $fromTenantId, int $toTenantId): bool
    {
        try {
            $st = $this->pdo()->prepare('UPDATE users SET tenant_id = ? WHERE id = ? AND tenant_id = ?');
            $st->execute([$toTenantId, $userId, $fromTenantId]);

            return $st->rowCount() > 0;
        } catch (Throwable) {
            // Doublon (même e-mail déjà présent dans la communauté cible) : on tente la suivante.
            return false;
        }
    }

    private function ensureActiveMembership(int $userId, int $tenantId): void
    {
        if (!$this->tableExists('user_community_memberships')) {
            return;
        }
        $now = $this->now();
        $st = $this->pdo()->prepare('SELECT id FROM user_community_memberships WHERE user_id = ? AND tenant_id = ? LIMIT 1');
        $st->execute([$userId, $tenantId]);
        if ($st->fetchColumn() !== false) {
            $this->pdo()->prepare(
                "UPDATE user_community_memberships SET status = 'active', left_at = NULL, updated_at = ? WHERE user_id = ? AND tenant_id = ?"
            )->execute([$now, $userId, $tenantId]);

            return;
        }
        $this->pdo()->prepare(
            "INSERT INTO user_community_memberships (user_id, tenant_id, status, joined_at, created_at, updated_at)
             VALUES (?, ?, 'active', ?, ?, ?)"
        )->execute([$userId, $tenantId, $now, $now, $now]);
    }

    /**
     * @return list<int>
     */
    private function homeHumanUserIds(int $tenantId): array
    {
        $ids = [];
        foreach ($this->homeUsers($tenantId) as $row) {
            if (!self::isTechnicalAccount($row)) {
                $ids[] = (int) $row['id'];
            }
        }

        return $ids;
    }

    private function countTechnicalAccounts(int $tenantId): int
    {
        $n = 0;
        foreach ($this->homeUsers($tenantId) as $row) {
            if (self::isTechnicalAccount($row)) {
                $n++;
            }
        }

        return $n;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function homeUsers(int $tenantId): array
    {
        if (!$this->tableExists('users')) {
            return [];
        }
        $st = $this->pdo()->prepare('SELECT * FROM users WHERE tenant_id = ? ORDER BY id ASC');
        $st->execute([$tenantId]);

        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Comptes de service et comptes système internes : propres à la communauté, ils partent avec elle.
     *
     * @param array<string, mixed> $row
     */
    public static function isTechnicalAccount(array $row): bool
    {
        if (!empty($row['is_service_account'])) {
            return true;
        }
        $email = strtolower(trim((string) ($row['email'] ?? '')));

        return $email === UserRepository::SYSTEM_MODERATOR_EMAIL || str_ends_with($email, '@internal.local');
    }

    private function otherCommunityFor(int $userId, int $tenantId): ?int
    {
        if (!$this->tableExists('user_community_memberships')) {
            return null;
        }
        $st = $this->pdo()->prepare(
            "SELECT m.tenant_id, t.slug, t.settings
             FROM user_community_memberships m
             INNER JOIN tenants t ON t.id = m.tenant_id
             WHERE m.user_id = ? AND m.tenant_id <> ? AND m.status = 'active'
             ORDER BY m.joined_at ASC, m.tenant_id ASC"
        );
        $st->execute([$userId, $tenantId]);
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            if (strtolower((string) ($row['slug'] ?? '')) === 'default' || (int) $row['tenant_id'] <= 1) {
                continue;
            }
            if (TenantArchiveState::isArchived($row)) {
                continue;
            }

            return (int) $row['tenant_id'];
        }

        return null;
    }

    private function noOrgTenantId(): ?int
    {
        $st = $this->pdo()->prepare('SELECT id FROM tenants WHERE LOWER(slug) = ? ORDER BY id ASC LIMIT 1');
        $st->execute(['default']);
        $id = $st->fetchColumn();

        return $id !== false ? (int) $id : null;
    }

    private function countMemberships(int $tenantId, string $status): int
    {
        if (!$this->tableExists('user_community_memberships')) {
            return 0;
        }
        $st = $this->pdo()->prepare('SELECT COUNT(*) FROM user_community_memberships WHERE tenant_id = ? AND status = ?');
        $st->execute([$tenantId, $status]);

        return (int) $st->fetchColumn();
    }

    /**
     * @param array<string, mixed> $tenant
     */
    private function isProtected(array $tenant): bool
    {
        return (int) ($tenant['id'] ?? 0) <= 1 || strtolower(trim((string) ($tenant['slug'] ?? ''))) === 'default';
    }

    /**
     * @param array<string, mixed> $tenant
     */
    private function billingLive(array $tenant): bool
    {
        $status = strtolower(trim((string) ($tenant['subscription_status'] ?? 'none')));
        $hasProvider = trim((string) ($tenant['stripe_subscription_id'] ?? '')) !== ''
            || trim((string) ($tenant['paypal_subscription_id'] ?? '')) !== '';

        return $hasProvider && in_array($status, self::BILLING_LIVE_STATUSES, true);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function findTenant(int $tenantId): ?array
    {
        if ($tenantId < 1) {
            return null;
        }
        $st = $this->pdo()->prepare('SELECT * FROM tenants WHERE id = ? LIMIT 1');
        $st->execute([$tenantId]);
        $row = $st->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    /**
     * Tables qui portent une colonne tenant_id, et ce que la suppression de la communauté leur fait :
     * deleted = clé étrangère en cascade vers tenants ; sinon les lignes restent (règle SET NULL / aucune clé).
     *
     * @return array<string, array{deleted: bool, rule: string}>
     */
    public function tenantTables(): array
    {
        if ($this->tenantTables !== null) {
            return $this->tenantTables;
        }
        $out = [];
        try {
            if ($this->isSqlite()) {
                $names = $this->pdo()->query("SELECT name FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%' ORDER BY name")
                    ?->fetchAll(PDO::FETCH_COLUMN) ?: [];
                foreach ($names as $name) {
                    $name = (string) $name;
                    if ($name === 'tenants' || !self::safeIdentifier($name)) {
                        continue;
                    }
                    $hasColumn = false;
                    foreach ($this->pdo()->query('PRAGMA table_info(`' . $name . '`)')?->fetchAll(PDO::FETCH_ASSOC) ?: [] as $col) {
                        if (($col['name'] ?? '') === 'tenant_id') {
                            $hasColumn = true;
                        }
                    }
                    if (!$hasColumn) {
                        continue;
                    }
                    $rule = 'NONE';
                    foreach ($this->pdo()->query('PRAGMA foreign_key_list(`' . $name . '`)')?->fetchAll(PDO::FETCH_ASSOC) ?: [] as $fk) {
                        if (($fk['table'] ?? '') === 'tenants' && ($fk['from'] ?? '') === 'tenant_id') {
                            $rule = strtoupper((string) ($fk['on_delete'] ?? 'NO ACTION'));
                        }
                    }
                    $out[$name] = ['deleted' => $rule === 'CASCADE', 'rule' => $rule];
                }
            } else {
                $cols = $this->pdo()->query(
                    "SELECT c.TABLE_NAME FROM information_schema.COLUMNS c
                     INNER JOIN information_schema.TABLES t ON t.TABLE_SCHEMA = c.TABLE_SCHEMA AND t.TABLE_NAME = c.TABLE_NAME
                     WHERE c.TABLE_SCHEMA = DATABASE() AND c.COLUMN_NAME = 'tenant_id' AND t.TABLE_TYPE = 'BASE TABLE'
                     ORDER BY c.TABLE_NAME"
                )?->fetchAll(PDO::FETCH_COLUMN) ?: [];
                $rules = [];
                $fkRows = $this->pdo()->query(
                    "SELECT k.TABLE_NAME, r.DELETE_RULE
                     FROM information_schema.KEY_COLUMN_USAGE k
                     INNER JOIN information_schema.REFERENTIAL_CONSTRAINTS r
                        ON r.CONSTRAINT_SCHEMA = k.CONSTRAINT_SCHEMA AND r.CONSTRAINT_NAME = k.CONSTRAINT_NAME AND r.TABLE_NAME = k.TABLE_NAME
                     WHERE k.TABLE_SCHEMA = DATABASE() AND k.REFERENCED_TABLE_NAME = 'tenants' AND k.COLUMN_NAME = 'tenant_id'"
                )?->fetchAll(PDO::FETCH_ASSOC) ?: [];
                foreach ($fkRows as $fk) {
                    $rules[(string) $fk['TABLE_NAME']] = strtoupper((string) $fk['DELETE_RULE']);
                }
                foreach ($cols as $name) {
                    $name = (string) $name;
                    if ($name === 'tenants' || !self::safeIdentifier($name)) {
                        continue;
                    }
                    $rule = $rules[$name] ?? 'NONE';
                    $out[$name] = ['deleted' => $rule === 'CASCADE', 'rule' => $rule];
                }
            }
        } catch (Throwable) {
            $out = [];
        }

        return $this->tenantTables = $out;
    }

    private function countRows(string $table, int $tenantId): int
    {
        try {
            $st = $this->pdo()->prepare('SELECT COUNT(*) FROM `' . $table . '` WHERE tenant_id = ?');
            $st->execute([$tenantId]);

            return (int) $st->fetchColumn();
        } catch (Throwable) {
            return 0;
        }
    }

    /**
     * Sauvegarde JSON (gzip) : la ligne tenants puis, table par table, toutes les lignes tenant_id.
     *
     * @param array<string, mixed> $tenant
     * @return string chemin relatif à la racine du site
     */
    private function writeBackup(array $tenant): string
    {
        $tenantId = (int) $tenant['id'];
        $dir = $this->backupDir ?? base_path(self::BACKUP_DIR);
        if (!is_dir($dir) && !@mkdir($dir, 0750, true) && !is_dir($dir)) {
            throw new \RuntimeException('dossier ' . self::BACKUP_DIR . ' non inscriptible');
        }
        $slug = preg_replace('/[^a-z0-9-]/', '', strtolower((string) ($tenant['slug'] ?? ''))) ?: 'communaute';
        $file = sprintf('%s/%s-%d-%s.json.gz', rtrim($dir, '/'), gmdate('Ymd-His'), $tenantId, $slug);
        $gz = @gzopen($file, 'wb6');
        if ($gz === false) {
            throw new \RuntimeException('fichier de sauvegarde non inscriptible');
        }
        $flags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE;
        try {
            gzwrite($gz, '{"format":"athena-tenant-backup-1","created_at":' . json_encode(gmdate('c'))
                . ',"tenant":' . json_encode($tenant, $flags) . ',"tables":{');
            $firstTable = true;
            foreach (array_keys($this->tenantTables()) as $table) {
                $st = $this->pdo()->prepare('SELECT * FROM `' . $table . '` WHERE tenant_id = ?');
                $st->execute([$tenantId]);
                $firstRow = true;
                while (($row = $st->fetch(PDO::FETCH_ASSOC)) !== false) {
                    if ($firstRow) {
                        gzwrite($gz, ($firstTable ? '' : ',') . json_encode($table) . ':[');
                        $firstTable = false;
                    }
                    gzwrite($gz, ($firstRow ? '' : ',') . json_encode($row, $flags));
                    $firstRow = false;
                }
                if (!$firstRow) {
                    gzwrite($gz, ']');
                }
            }
            gzwrite($gz, '}}');
        } finally {
            gzclose($gz);
        }
        $root = rtrim(base_path(''), '/') . '/';

        return str_starts_with($file, $root) ? substr($file, strlen($root)) : $file;
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeSettings(mixed $raw): array
    {
        if (is_array($raw)) {
            return $raw;
        }
        if (!is_string($raw) || trim($raw) === '') {
            return [];
        }
        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @param array<string, mixed> $settings
     */
    private function writeSettings(int $tenantId, array $settings): void
    {
        $this->pdo()->prepare('UPDATE tenants SET settings = ?, updated_at = ? WHERE id = ?')->execute([
            $settings === [] ? null : json_encode($settings, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            $this->now(),
            $tenantId,
        ]);
    }

    private function tableExists(string $table): bool
    {
        return array_key_exists($table, $this->tenantTables());
    }

    private function isSqlite(): bool
    {
        return $this->pdo()->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite';
    }

    private static function safeIdentifier(string $name): bool
    {
        return (bool) preg_match('/^[A-Za-z0-9_]+$/', $name);
    }

    private function now(): string
    {
        return date('Y-m-d H:i:s');
    }
}
