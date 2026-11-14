<?php

declare(strict_types=1);

namespace App\Services\Rbac;

use App\Repositories\RoleAssignmentLogRepository;
use App\Repositories\RoleRepository;
use App\Repositories\UserRepository;
use App\Services\Personnel\PersonnelDutyPositionService;
use App\Support\AdministrativePositionCatalog;
use InvalidArgumentException;
use PDO;
use Throwable;

/**
 * Registre des droits d'accès d'une communauté : une ligne par droit détenu.
 *
 * Rien de nouveau en base : le registre lit et écrit les tables existantes.
 * - tenant_user_roles : niveaux d'accès, positions administratives et rôles, pour toute la communauté
 *   (org_unit_id NULL) ou pour une unité ; valid_from / valid_until bornent le droit (lus par RbacService),
 *   metadata garde « accordé par », le motif et la dernière revue.
 * - user_roles : anciennes attributions d'un membre qui n'a encore aucune ligne tenant_user_roles.
 * - user_permission_overrides : droits individuels accordés (ou retirés) hors niveau d'accès.
 * - role_assignments_log : historique des attributions et retraits.
 */
final class AccessRegisterService
{
    public const MAX_ROWS = 1500;
    public const EXPIRING_DAYS = 30;

    public const KIND_ACCESS = 'acces';
    public const KIND_UNIT = 'unite';
    public const KIND_POSITION = 'position';
    public const KIND_ROLE = 'role';
    public const KIND_GRANT = 'individuel';
    public const KIND_DENY = 'retrait';

    public const STATE_ACTIVE = 'actif';
    public const STATE_EXPIRING = 'echeance';
    public const STATE_UPCOMING = 'a_venir';
    public const STATE_EXPIRED = 'expire';

    public const REASON_MAX = 255;

    public function __construct(
        private PDO $pdo,
        private UserRepository $users,
        private RoleRepository $roles,
        private PersonnelDutyPositionService $duty,
    ) {}

    /**
     * Colonnes du registre « en vigueur » : clé => [libellé, visible par défaut].
     *
     * @return array<string, array{0: string, 1: bool}>
     */
    public static function columns(): array
    {
        return [
            'member' => ['Membre', true],
            'callsign' => ['Indicatif', true],
            'member_number' => ['Matricule', false],
            'position' => ['Position administrative', true],
            'right' => ['Droit', true],
            'kind_label' => ['Nature', true],
            'scope' => ['Périmètre', true],
            'granted_by' => ['Accordé par', true],
            'since' => ['Depuis', true],
            'until' => ['Jusqu’au', true],
            'state_label' => ['État', true],
            'reason' => ['Motif', false],
            'reviewed' => ['Dernière revue', false],
            'account' => ['Compte', false],
            'ref' => ['Référence', false],
        ];
    }

    /**
     * Colonnes de l'historique des mouvements.
     *
     * @return array<string, array{0: string, 1: bool}>
     */
    public static function historyColumns(): array
    {
        return [
            'date' => ['Date', true],
            'action_label' => ['Mouvement', true],
            'member' => ['Membre', true],
            'callsign' => ['Indicatif', true],
            'right' => ['Droit', true],
            'kind_label' => ['Nature', true],
            'by' => ['Par', true],
            'reason' => ['Motif', true],
        ];
    }

    /** @return array<string, string> */
    public static function kindLabels(): array
    {
        return [
            self::KIND_ACCESS => 'Niveau d’accès',
            self::KIND_UNIT => 'Accès limité à une unité',
            self::KIND_POSITION => 'Position administrative',
            self::KIND_ROLE => 'Autre rôle',
            self::KIND_GRANT => 'Droit individuel',
            self::KIND_DENY => 'Retrait individuel',
        ];
    }

    /** @return array<string, string> */
    public static function stateLabels(): array
    {
        return [
            self::STATE_ACTIVE => 'En vigueur',
            self::STATE_EXPIRING => 'Échéance proche',
            self::STATE_UPCOMING => 'À venir',
            self::STATE_EXPIRED => 'Échu',
        ];
    }

    /** Nature d'un rôle détenu, selon son identifiant technique et son périmètre. */
    public static function kindForRole(string $slug, ?int $unitId): string
    {
        $slug = strtolower(trim($slug));
        if (AdministrativePositionCatalog::isKnown($slug)) {
            return self::KIND_POSITION;
        }
        if ($unitId !== null && $unitId > 0) {
            return self::KIND_UNIT;
        }

        return CommunityAccessProfiles::isAssignableAccessSlug($slug) ? self::KIND_ACCESS : self::KIND_ROLE;
    }

    /** État d'un droit au moment $now selon ses bornes (« Y-m-d H:i:s » ou vide). */
    public static function stateFor(?string $from, ?string $until, int $now): string
    {
        $fromTs = self::timestamp($from);
        $untilTs = self::timestamp($until);
        if ($untilTs !== null && $untilTs <= $now) {
            return self::STATE_EXPIRED;
        }
        if ($fromTs !== null && $fromTs > $now) {
            return self::STATE_UPCOMING;
        }
        if ($untilTs !== null && $untilTs - $now <= self::EXPIRING_DAYS * 86400) {
            return self::STATE_EXPIRING;
        }

        return self::STATE_ACTIVE;
    }

    /**
     * Date saisie (« AAAA-MM-JJ ») vers une borne en base. Une fin vaut jusqu'à la fin de ce jour.
     *
     * @throws InvalidArgumentException date illisible
     */
    public static function normalizeDate(string $input, bool $endOfDay): ?string
    {
        $input = trim($input);
        if ($input === '') {
            return null;
        }
        $d = \DateTimeImmutable::createFromFormat('!Y-m-d', $input);
        if ($d === false || $d->format('Y-m-d') !== $input) {
            throw new InvalidArgumentException('Date illisible : utilisez le format AAAA-MM-JJ.');
        }

        return $d->format('Y-m-d') . ($endOfDay ? ' 23:59:59' : ' 00:00:00');
    }

    /**
     * Contrôle des bornes d'un droit.
     *
     * @throws InvalidArgumentException
     */
    public static function assertValidPeriod(?string $from, ?string $until, int $now): void
    {
        $fromTs = self::timestamp($from);
        $untilTs = self::timestamp($until);
        if ($untilTs !== null && $untilTs <= $now) {
            throw new InvalidArgumentException('La date de fin doit être dans le futur. Pour retirer le droit maintenant, utilisez « Retirer ».');
        }
        if ($fromTs !== null && $untilTs !== null && $untilTs <= $fromTs) {
            throw new InvalidArgumentException('La date de fin doit suivre la date de début.');
        }
    }

    /**
     * Filtre d'affichage : recherche libre, nature, état, année (de « Depuis » ou de la date du mouvement).
     *
     * @param array<string, mixed> $row
     * @param array{q?: string, kind?: string, state?: string, year?: string} $filters
     */
    public static function matches(array $row, array $filters, string $dateKey = 'since'): bool
    {
        $kind = (string) ($filters['kind'] ?? '');
        if ($kind !== '' && ($row['kind'] ?? '') !== $kind) {
            return false;
        }
        $state = (string) ($filters['state'] ?? '');
        if ($state !== '' && ($row['state'] ?? '') !== $state) {
            return false;
        }
        $year = (string) ($filters['year'] ?? '');
        if ($year !== '' && substr((string) ($row[$dateKey] ?? ''), 0, 4) !== $year) {
            return false;
        }
        $q = self::fold((string) ($filters['q'] ?? ''));
        if ($q === '') {
            return true;
        }
        $hay = '';
        foreach ($row as $k => $v) {
            if (is_string($v) && $k !== 'kind' && $k !== 'state') {
                $hay .= ' ' . $v;
            }
        }
        foreach (preg_split('/\s+/', $q) ?: [] as $word) {
            if ($word !== '' && !str_contains(self::fold($hay), $word)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Années présentes dans le registre, la plus récente d'abord.
     *
     * @param list<array<string, mixed>> $rows
     * @return list<string>
     */
    public static function years(array $rows, string $dateKey): array
    {
        $years = [];
        foreach ($rows as $row) {
            $y = substr((string) ($row[$dateKey] ?? ''), 0, 4);
            if (preg_match('/^\d{4}$/', $y)) {
                $years[$y] = true;
            }
        }
        $out = array_keys($years);
        rsort($out);

        return array_map('strval', $out);
    }

    /**
     * CSV pour un tableur (séparateur « ; », BOM ajouté par l'appelant), cellules neutralisées.
     *
     * @param list<array<string, mixed>> $rows
     * @param array<string, string> $columns clé => libellé, dans l'ordre voulu
     */
    public static function csv(array $rows, array $columns): string
    {
        $fh = fopen('php://temp', 'r+');
        if ($fh === false) {
            return '';
        }
        fputcsv($fh, array_values($columns), ';', '"', '');
        foreach (array_slice($rows, 0, self::MAX_ROWS) as $row) {
            $line = [];
            foreach (array_keys($columns) as $key) {
                $line[] = self::safeCell((string) ($row[$key] ?? ''));
            }
            fputcsv($fh, $line, ';', '"', '');
        }
        rewind($fh);
        $out = (string) stream_get_contents($fh);
        fclose($fh);

        return $out;
    }

    /** Une cellule qui commencerait par = + - @ et n'est pas un nombre est neutralisée pour le tableur. */
    public static function safeCell(string $v): string
    {
        $v = str_replace(["\r\n", "\r", "\n", "\t"], ' ', $v);

        return preg_match('/^[=+\-@]/', $v) === 1 && !is_numeric($v) ? "'" . $v : $v;
    }

    /**
     * Droits en vigueur, à venir ou échus non encore balayés.
     *
     * @return list<array<string, mixed>>
     */
    public function current(int $tenantId, ?int $now = null): array
    {
        if ($tenantId < 1) {
            return [];
        }
        $now ??= time();
        $members = [];
        foreach ($this->users->allForTenant($tenantId) as $u) {
            $members[(int) $u['id']] = $u;
        }
        if ($members === []) {
            return [];
        }
        $roles = [];
        foreach ($this->roles->allForTenant($tenantId) as $r) {
            $roles[(int) $r['id']] = $r;
        }
        $units = $this->unitNames($tenantId);
        $lastAssign = $this->lastAssignments($tenantId);
        $positions = [];
        $grants = [];

        $withTur = [];
        foreach ($this->turRows($tenantId) as $t) {
            $uid = (int) $t['user_id'];
            $rid = (int) $t['role_id'];
            if (!isset($members[$uid], $roles[$rid])) {
                continue;
            }
            $unitId = $t['org_unit_id'] !== null ? (int) $t['org_unit_id'] : null;
            if ($unitId === null) {
                $withTur[$uid] = true;
            }
            $meta = self::decodeMeta($t['metadata'] ?? null);
            $log = $lastAssign[$uid . ':' . $rid] ?? null;
            $grants[] = [
                'ref' => 'R-' . (int) $t['id'],
                'user_id' => $uid,
                'role_id' => $rid,
                'slug' => (string) $roles[$rid]['slug'],
                'right' => (string) $roles[$rid]['name'],
                'unit_id' => $unitId,
                'scope' => $unitId === null ? 'Toute la communauté' : ('Unité : ' . ($units[$unitId] ?? ('#' . $unitId))),
                'from' => $t['valid_from'] !== null ? (string) $t['valid_from'] : null,
                'until' => $t['valid_until'] !== null ? (string) $t['valid_until'] : null,
                'since_raw' => (string) ($t['valid_from'] ?? '') !== '' ? (string) $t['valid_from'] : (string) ($log['assigned_at'] ?? $t['created_at'] ?? ''),
                'by_id' => (int) ($meta['granted_by'] ?? ($log['assigned_by'] ?? 0)),
                'reason' => (string) ($meta['reason'] ?? ($log['reason'] ?? '')),
                'reviewed_at' => (string) ($meta['reviewed_at'] ?? ''),
                'reviewed_by' => (int) ($meta['reviewed_by'] ?? 0),
            ];
        }
        // Membres sans ligne tenant_user_roles : leurs rôles viennent encore de user_roles (même règle que la fiche).
        foreach ($this->legacyRows($tenantId) as $l) {
            $uid = (int) $l['user_id'];
            $rid = (int) $l['role_id'];
            if (isset($withTur[$uid]) || !isset($members[$uid], $roles[$rid])) {
                continue;
            }
            $log = $lastAssign[$uid . ':' . $rid] ?? null;
            $grants[] = [
                'ref' => 'U-' . $uid . '-' . $rid,
                'user_id' => $uid,
                'role_id' => $rid,
                'slug' => (string) $roles[$rid]['slug'],
                'right' => (string) $roles[$rid]['name'],
                'unit_id' => null,
                'scope' => 'Toute la communauté',
                'from' => null,
                'until' => null,
                'since_raw' => (string) ($log['assigned_at'] ?? $l['created_at'] ?? ''),
                'by_id' => (int) ($log['assigned_by'] ?? 0),
                'reason' => (string) ($log['reason'] ?? ''),
                'reviewed_at' => '',
                'reviewed_by' => 0,
            ];
        }
        foreach ($grants as $g) {
            if (AdministrativePositionCatalog::isKnown($g['slug']) && $g['unit_id'] === null) {
                $positions[$g['user_id']] = $g['right'];
            }
        }
        foreach ($this->overrideRows($tenantId) as $o) {
            $uid = (int) $o['user_id'];
            if (!isset($members[$uid])) {
                continue;
            }
            $unitId = $o['org_unit_id'] !== null ? (int) $o['org_unit_id'] : null;
            $grants[] = [
                'ref' => 'D-' . (int) $o['id'],
                'user_id' => $uid,
                'role_id' => 0,
                'slug' => '',
                'right' => trim((string) ($o['name'] ?? '')) !== '' ? (string) $o['name'] : (string) $o['slug'],
                'kind' => (int) $o['grant_flag'] === 1 ? self::KIND_GRANT : self::KIND_DENY,
                'unit_id' => $unitId,
                'scope' => $unitId === null ? 'Toute la communauté' : ('Unité : ' . ($units[$unitId] ?? ('#' . $unitId))),
                'from' => null,
                'until' => null,
                'since_raw' => (string) ($o['created_at'] ?? ''),
                'by_id' => (int) ($o['created_by_user_id'] ?? 0),
                'reason' => (string) ($o['reason'] ?? ''),
                'reviewed_at' => '',
                'reviewed_by' => 0,
            ];
        }

        $people = $this->peopleNames(array_merge(
            array_column($grants, 'by_id'),
            array_column($grants, 'reviewed_by')
        ), $members);
        $kindLabels = self::kindLabels();
        $stateLabels = self::stateLabels();
        $out = [];
        foreach ($grants as $g) {
            $u = $members[$g['user_id']];
            $kind = $g['kind'] ?? self::kindForRole($g['slug'], $g['unit_id']);
            $state = self::stateFor($g['from'], $g['until'], $now);
            $isBase = $g['slug'] === CommunityAccessProfiles::SLUG_MEMBER && $g['unit_id'] === null;
            $isRoleRow = !in_array($kind, [self::KIND_GRANT, self::KIND_DENY], true);
            $out[] = [
                'ref' => $g['ref'],
                'user_id' => $g['user_id'],
                'member' => self::nameOf($u),
                'callsign' => trim((string) ($u['callsign'] ?? '')),
                'member_number' => trim((string) ($u['tenant_member_number'] ?? '')),
                'position' => $positions[$g['user_id']] ?? '',
                'right' => $g['right'],
                'kind' => $kind,
                'kind_label' => $kindLabels[$kind],
                'scope' => $g['scope'],
                'granted_by' => $g['by_id'] > 0 ? ($people[$g['by_id']] ?? ('Membre #' . $g['by_id'])) : 'Système',
                'since' => self::day($g['since_raw']),
                'until' => self::day((string) $g['until']),
                'from_input' => self::day((string) $g['from']),
                'state' => $state,
                'state_label' => $stateLabels[$state],
                'reason' => $isBase && $g['reason'] === '' ? 'Accès de base de tout membre' : $g['reason'],
                'reviewed' => $g['reviewed_at'] !== ''
                    ? self::day($g['reviewed_at']) . ($g['reviewed_by'] > 0 ? ' · ' . ($people[$g['reviewed_by']] ?? '') : '')
                    : '',
                'account' => self::accountLabel((string) ($u['status'] ?? '')),
                'can_edit' => $isRoleRow && !$isBase && $kind !== self::KIND_POSITION,
                'can_revoke' => !$isBase && $kind !== self::KIND_POSITION,
                'can_review' => $isRoleRow,
            ];
        }
        $order = array_flip(array_keys(self::kindLabels()));
        usort($out, static fn (array $a, array $b): int => [self::fold($a['member']), $order[$a['kind']], self::fold($a['right'])]
            <=> [self::fold($b['member']), $order[$b['kind']], self::fold($b['right'])]);

        return $out;
    }

    /**
     * Mouvements du journal des attributions, le plus récent d'abord.
     *
     * @return list<array<string, mixed>>
     */
    public function history(int $tenantId, int $limit = self::MAX_ROWS * 2): array
    {
        if ($tenantId < 1 || !$this->hasTable('role_assignments_log')) {
            return [];
        }
        $st = $this->pdo->prepare(
            'SELECT l.id, l.user_id, l.role_id, l.action, l.assigned_by, l.assigned_at, l.reason, r.name AS role_name, r.slug AS role_slug
             FROM role_assignments_log l
             LEFT JOIN roles r ON r.id = l.role_id
             WHERE l.tenant_id = ?
             ORDER BY l.assigned_at DESC, l.id DESC
             LIMIT ' . max(1, min(5000, $limit))
        );
        $st->execute([$tenantId]);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $members = [];
        foreach ($this->users->allForTenant($tenantId) as $u) {
            $members[(int) $u['id']] = $u;
        }
        $people = $this->peopleNames(array_merge(array_column($rows, 'user_id'), array_column($rows, 'assigned_by')), $members);
        $kindLabels = self::kindLabels();
        $out = [];
        foreach ($rows as $r) {
            $uid = (int) $r['user_id'];
            $kind = self::kindForRole((string) ($r['role_slug'] ?? ''), null);
            $reason = (string) ($r['reason'] ?? '');
            if (str_starts_with($reason, 'Unité : ')) {
                $kind = self::KIND_UNIT;
            }
            $by = (int) ($r['assigned_by'] ?? 0);
            $out[] = [
                'ref' => 'H-' . (int) $r['id'],
                'date' => substr((string) $r['assigned_at'], 0, 16),
                'action' => (string) $r['action'],
                'action_label' => $r['action'] === 'revoke' ? 'Retrait' : 'Attribution',
                'member' => isset($members[$uid]) ? self::nameOf($members[$uid]) : ($people[$uid] ?? ('Membre #' . $uid)),
                'callsign' => trim((string) ($members[$uid]['callsign'] ?? '')),
                'right' => trim((string) ($r['role_name'] ?? '')) !== '' ? (string) $r['role_name'] : 'Rôle supprimé',
                'kind' => $kind,
                'kind_label' => $kindLabels[$kind],
                'by' => $by > 0 ? ($people[$by] ?? ('Membre #' . $by)) : 'Système',
                'reason' => $reason,
                'state' => '',
            ];
        }

        return $out;
    }

    /**
     * Droits qu'un responsable peut attribuer depuis le registre : niveaux d'accès et positions.
     *
     * @return list<array{value: string, label: string, group: string}>
     */
    public function grantableTargets(int $tenantId): array
    {
        $out = [];
        foreach ($this->roles->forTenantAccessProfiles($tenantId) as $r) {
            $id = (int) ($r['id'] ?? 0);
            if ($id > 0 && $this->roles->canAssignInTenantAdminContext($id, $tenantId)) {
                $out[] = ['value' => 'role:' . $id, 'label' => (string) $r['name'], 'group' => 'Niveaux d’accès'];
            }
        }
        foreach (AdministrativePositionCatalog::all() as $p) {
            $out[] = ['value' => 'position:' . $p['slug'], 'label' => $p['label'], 'group' => 'Positions administratives'];
        }

        return $out;
    }

    /**
     * Attribue un droit (niveau d'accès, éventuellement limité à une unité et borné dans le temps) ou une position.
     * Un niveau déjà détenu pour le même périmètre est mis à jour (renouvellement).
     *
     * @throws InvalidArgumentException
     *
     * @return string message pour le responsable
     */
    public function grant(int $tenantId, int $actorId, int $userId, string $target, int $unitId, string $from, string $until, string $reason, ?int $now = null): string
    {
        $now ??= time();
        $reason = self::cleanReason($reason);
        if (!$this->users->isMemberOfTenant($userId, $tenantId)) {
            throw new InvalidArgumentException('Membre introuvable dans cette communauté.');
        }
        if (str_starts_with($target, 'position:')) {
            $slug = substr($target, 9);
            if (!AdministrativePositionCatalog::isKnown($slug)) {
                throw new InvalidArgumentException('Position inconnue.');
            }
            $result = $this->duty->applyPosition($tenantId, $userId, $slug, $actorId);
            if ($result === PersonnelDutyPositionService::RESULT_TRAINING_MINIMUM) {
                throw new InvalidArgumentException('Position inchangée : la formation obligatoire n’est pas terminée.');
            }
            if ($result === PersonnelDutyPositionService::RESULT_INVALID) {
                throw new InvalidArgumentException('Position inconnue ou membre introuvable.');
            }
            $changed = $result === PersonnelDutyPositionService::RESULT_CHANGED;
            $this->noteLatestLog($tenantId, $userId, (int) ($this->roles->getIdBySlug($tenantId, $slug) ?? 0), 'assign', $reason);

            return $changed
                ? 'Position administrative attribuée : ' . AdministrativePositionCatalog::label($slug) . '.'
                : 'Le membre avait déjà cette position.';
        }
        $roleId = str_starts_with($target, 'role:') ? (int) substr($target, 5) : 0;
        $role = $roleId > 0 ? $this->roles->findById($roleId, $tenantId) : null;
        if (!$role || !$this->roles->canAssignInTenantAdminContext($roleId, $tenantId)) {
            throw new InvalidArgumentException('Ce droit ne peut pas être attribué depuis le registre.');
        }
        $fromDb = self::normalizeDate($from, false);
        $untilDb = self::normalizeDate($until, true);
        self::assertValidPeriod($fromDb, $untilDb, $now);
        $isBase = (string) $role['slug'] === CommunityAccessProfiles::SLUG_MEMBER;
        if ($isBase && $unitId < 1 && ($fromDb !== null || $untilDb !== null)) {
            throw new InvalidArgumentException('L’accès de base « membre » ne se borne pas dans le temps.');
        }
        $meta = ['granted_by' => $actorId, 'reason' => $reason, 'granted_at' => date('Y-m-d H:i:s', $now)];

        if ($unitId > 0) {
            $units = $this->unitNames($tenantId);
            if (!isset($units[$unitId])) {
                throw new InvalidArgumentException('Unité introuvable dans cette communauté.');
            }
            $existing = $this->pdo->prepare('SELECT id FROM tenant_user_roles WHERE tenant_id = ? AND user_id = ? AND role_id = ? AND org_unit_id = ? LIMIT 1');
            $existing->execute([$tenantId, $userId, $roleId, $unitId]);
            $turId = (int) ($existing->fetchColumn() ?: 0);
            if ($turId > 0) {
                $this->writeTur($turId, $fromDb, $untilDb, $meta);

                return 'Droit renouvelé pour l’unité ' . $units[$unitId] . '.';
            }
            $this->pdo->prepare(
                'INSERT INTO tenant_user_roles (tenant_id, user_id, role_id, org_unit_id, co_unit_id, valid_from, valid_until, metadata, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
            )->execute([$tenantId, $userId, $roleId, $unitId, $unitId, $fromDb, $untilDb, self::encodeMeta($meta), date('Y-m-d H:i:s', $now)]);
            (new RoleAssignmentLogRepository())->logAssign($tenantId, $userId, $roleId, $actorId, self::unitReason($units[$unitId], $reason));

            return '« ' . (string) $role['name'] . ' » attribué pour l’unité ' . $units[$unitId] . '.';
        }

        $added = $this->users->addOrganizationRoleIfMissing($userId, $tenantId, $roleId, $actorId);
        $turId = $this->ensureTenantTur($tenantId, $userId, $roleId);
        if ($turId > 0) {
            $this->writeTur($turId, $fromDb, $untilDb, $meta);
        }
        if ($added) {
            $this->noteLatestLog($tenantId, $userId, $roleId, 'assign', $reason);
        }

        return $added
            ? '« ' . (string) $role['name'] . ' » attribué.'
            : 'Le membre détenait déjà « ' . (string) $role['name'] . ' » : dates et motif mis à jour.';
    }

    /**
     * Modifie les bornes et le motif d'un droit (lignes de rôle uniquement).
     *
     * @throws InvalidArgumentException
     */
    public function update(int $tenantId, int $actorId, string $ref, string $from, string $until, string $reason, ?int $now = null): void
    {
        $now ??= time();
        $row = $this->resolveRoleRef($tenantId, $ref);
        $fromDb = self::normalizeDate($from, false);
        $untilDb = self::normalizeDate($until, true);
        self::assertValidPeriod($fromDb, $untilDb, $now);
        if (AdministrativePositionCatalog::isKnown($row['slug'])) {
            throw new InvalidArgumentException('Une position se change depuis « Attribuer » ou la fiche du membre.');
        }
        if ($row['slug'] === CommunityAccessProfiles::SLUG_MEMBER && $row['unit_id'] === null) {
            throw new InvalidArgumentException('L’accès de base « membre » ne se borne pas dans le temps.');
        }
        if ($untilDb !== null || $fromDb !== null) {
            $this->assertNotLockingOut($tenantId, $actorId, $row);
        }
        $meta = self::decodeMeta($row['metadata']);
        $meta['reason'] = self::cleanReason($reason);
        $meta['updated_by'] = $actorId;
        $meta['updated_at'] = date('Y-m-d H:i:s', $now);
        $this->writeTur($row['id'], $fromDb, $untilDb, $meta);
    }

    /**
     * Retire un droit maintenant. Le retrait est journalisé avec son motif.
     *
     * @throws InvalidArgumentException
     */
    public function revoke(int $tenantId, int $actorId, string $ref, string $reason): string
    {
        $reason = self::cleanReason($reason);
        if (str_starts_with($ref, 'D-')) {
            $id = (int) substr($ref, 2);
            $st = $this->pdo->prepare(
                'SELECT o.id, o.user_id, p.name, p.slug FROM user_permission_overrides o
                 LEFT JOIN permissions p ON p.id = o.permission_id
                 WHERE o.id = ? AND o.tenant_id = ? LIMIT 1'
            );
            $st->execute([$id, $tenantId]);
            $o = $st->fetch(PDO::FETCH_ASSOC);
            if (!$o) {
                throw new InvalidArgumentException('Droit introuvable : la page n’était peut-être plus à jour.');
            }
            if ((int) $o['user_id'] === $actorId) {
                throw new InvalidArgumentException('Vous ne pouvez pas retirer vos propres droits. Demandez à un autre responsable.');
            }
            $this->pdo->prepare('DELETE FROM user_permission_overrides WHERE id = ? AND tenant_id = ?')->execute([$id, $tenantId]);

            return 'Droit individuel « ' . (string) ($o['name'] ?? $o['slug']) . ' » retiré.';
        }
        $row = $this->resolveRoleRef($tenantId, $ref);
        if ($row['slug'] === CommunityAccessProfiles::SLUG_MEMBER && $row['unit_id'] === null) {
            throw new InvalidArgumentException('L’accès de base « membre » ne se retire pas : pour écarter quelqu’un, utilisez « Départ » sur sa fiche.');
        }
        if (AdministrativePositionCatalog::isKnown($row['slug'])) {
            throw new InvalidArgumentException('Une position ne se retire pas : choisissez-en une autre.');
        }
        $this->assertNotLockingOut($tenantId, $actorId, $row);
        $this->removeRow($tenantId, $row, $actorId, $reason);

        return '« ' . $row['name'] . ' » retiré.';
    }

    /**
     * Revue des droits : note la date et l'auteur de la revue sur chaque ligne de rôle choisie.
     *
     * @param list<string> $refs
     * @return int lignes revues
     */
    public function review(int $tenantId, int $actorId, array $refs, ?int $now = null): int
    {
        $now ??= time();
        $done = 0;
        foreach (array_slice(array_values(array_unique($refs)), 0, self::MAX_ROWS) as $ref) {
            if (!is_string($ref) || str_starts_with($ref, 'D-')) {
                continue;
            }
            try {
                $row = $this->resolveRoleRef($tenantId, $ref);
            } catch (InvalidArgumentException) {
                continue;
            }
            $meta = self::decodeMeta($row['metadata']);
            $meta['reviewed_at'] = date('Y-m-d H:i:s', $now);
            $meta['reviewed_by'] = $actorId;
            $this->pdo->prepare('UPDATE tenant_user_roles SET metadata = ? WHERE id = ? AND tenant_id = ?')
                ->execute([self::encodeMeta($meta), $row['id'], $tenantId]);
            $done++;
        }

        return $done;
    }

    /**
     * Retire les droits arrivés à échéance (le contrôle d'accès les ignore déjà) et les inscrit à l'historique.
     *
     * @return int droits retirés
     */
    public function sweepExpired(int $tenantId, ?int $now = null): int
    {
        if ($tenantId < 1) {
            return 0;
        }
        $now ??= time();
        $st = $this->pdo->prepare(
            'SELECT tur.id, tur.user_id, tur.role_id, tur.org_unit_id, tur.metadata, r.slug, r.name
             FROM tenant_user_roles tur
             INNER JOIN roles r ON r.id = tur.role_id
             WHERE tur.tenant_id = ? AND tur.valid_until IS NOT NULL AND tur.valid_until <= ?'
        );
        $st->execute([$tenantId, date('Y-m-d H:i:s', $now)]);
        $done = 0;
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) ?: [] as $t) {
            $row = [
                'id' => (int) $t['id'],
                'user_id' => (int) $t['user_id'],
                'role_id' => (int) $t['role_id'],
                'unit_id' => $t['org_unit_id'] !== null ? (int) $t['org_unit_id'] : null,
                'slug' => (string) $t['slug'],
                'name' => (string) $t['name'],
                'metadata' => $t['metadata'],
            ];
            if ($row['slug'] === CommunityAccessProfiles::SLUG_MEMBER && $row['unit_id'] === null) {
                // Ne devrait pas exister : l'accès de base ne s'arrête pas, seule l'échéance est effacée.
                $this->pdo->prepare('UPDATE tenant_user_roles SET valid_until = NULL WHERE id = ?')->execute([$row['id']]);
                continue;
            }
            try {
                $this->removeRow($tenantId, $row, 0, 'Échéance atteinte');
                $done++;
            } catch (Throwable $e) {
                error_log('[registre-droits] échéance ' . $row['id'] . ' : ' . $e->getMessage());
            }
        }

        return $done;
    }

    /**
     * @param array{id: int, user_id: int, role_id: int, unit_id: ?int, slug: string, name: string, metadata: mixed} $row
     */
    private function removeRow(int $tenantId, array $row, int $actorId, string $reason): void
    {
        if ($row['unit_id'] !== null) {
            $this->pdo->prepare('DELETE FROM tenant_user_roles WHERE id = ? AND tenant_id = ?')->execute([$row['id'], $tenantId]);
            $units = $this->unitNames($tenantId);
            (new RoleAssignmentLogRepository())->logRevoke(
                $tenantId,
                $row['user_id'],
                $row['role_id'],
                $actorId > 0 ? $actorId : null,
                self::unitReason($units[$row['unit_id']] ?? ('#' . $row['unit_id']), $reason)
            );

            return;
        }
        $ids = array_values(array_filter(
            $this->users->listOrganizationRoleIdsForUser($row['user_id'], $tenantId),
            static fn (int $id): bool => $id !== $row['role_id']
        ));
        $this->users->syncOrganizationRoles($row['user_id'], $tenantId, $ids, $actorId > 0 ? $actorId : null, true);
        // Ligne d'une ancienne attribution (user_roles seul) : sync ne la voit pas toujours.
        $this->pdo->prepare('DELETE FROM tenant_user_roles WHERE tenant_id = ? AND user_id = ? AND role_id = ? AND org_unit_id IS NULL')
            ->execute([$tenantId, $row['user_id'], $row['role_id']]);
        $this->noteLatestLog($tenantId, $row['user_id'], $row['role_id'], 'revoke', $reason);
    }

    /**
     * Garde-fous : ne pas se retirer (ou borner) ses propres accès, ne pas retirer le dernier responsable.
     *
     * @param array{user_id: int, role_id: int, unit_id: ?int, slug: string} $row
     * @throws InvalidArgumentException
     */
    private function assertNotLockingOut(int $tenantId, int $actorId, array $row): void
    {
        if ($row['user_id'] === $actorId) {
            throw new InvalidArgumentException('Vous ne pouvez pas retirer ni borner vos propres accès. Demandez à un autre responsable.');
        }
        if ($row['slug'] === CommunityAccessProfiles::SLUG_MANAGER && $row['unit_id'] === null) {
            $st = $this->pdo->prepare(
                'SELECT COUNT(DISTINCT user_id) FROM tenant_user_roles WHERE tenant_id = ? AND role_id = ? AND org_unit_id IS NULL
                   AND (valid_until IS NULL OR valid_until > ?)'
            );
            $st->execute([$tenantId, $row['role_id'], date('Y-m-d H:i:s')]);
            if ((int) $st->fetchColumn() <= 1) {
                throw new InvalidArgumentException('C’est le dernier gestionnaire de la communauté : attribuez ce niveau à quelqu’un d’autre avant de le retirer.');
            }
        }
    }

    /**
     * Retrouve une ligne de rôle (« R-id » ou « U-membre-rôle ») de la communauté.
     * Une ancienne attribution « U- » reçoit sa ligne tenant_user_roles pour pouvoir porter dates et notes.
     *
     * @throws InvalidArgumentException
     * @return array{id: int, user_id: int, role_id: int, unit_id: ?int, slug: string, name: string, metadata: mixed}
     */
    private function resolveRoleRef(int $tenantId, string $ref): array
    {
        if (preg_match('/^U-(\d+)-(\d+)$/', $ref, $m) === 1) {
            $userId = (int) $m[1];
            $roleId = (int) $m[2];
            if (!$this->users->isMemberOfTenant($userId, $tenantId) || !$this->roles->findById($roleId, $tenantId)
                || !in_array($roleId, $this->users->listOrganizationRoleIdsForUser($userId, $tenantId), true)) {
                throw new InvalidArgumentException('Droit introuvable : la page n’était peut-être plus à jour.');
            }
            $ref = 'R-' . $this->ensureTenantTur($tenantId, $userId, $roleId);
        }
        $id = preg_match('/^R-(\d+)$/', $ref, $m) === 1 ? (int) $m[1] : 0;
        $st = $this->pdo->prepare(
            'SELECT tur.id, tur.user_id, tur.role_id, tur.org_unit_id, tur.metadata, r.slug, r.name
             FROM tenant_user_roles tur
             INNER JOIN roles r ON r.id = tur.role_id AND r.tenant_id = tur.tenant_id
             WHERE tur.id = ? AND tur.tenant_id = ? LIMIT 1'
        );
        $st->execute([$id, $tenantId]);
        $t = $id > 0 ? $st->fetch(PDO::FETCH_ASSOC) : false;
        if (!$t) {
            throw new InvalidArgumentException('Droit introuvable : la page n’était peut-être plus à jour.');
        }

        return [
            'id' => (int) $t['id'],
            'user_id' => (int) $t['user_id'],
            'role_id' => (int) $t['role_id'],
            'unit_id' => $t['org_unit_id'] !== null ? (int) $t['org_unit_id'] : null,
            'slug' => strtolower((string) $t['slug']),
            'name' => (string) $t['name'],
            'metadata' => $t['metadata'],
        ];
    }

    private function ensureTenantTur(int $tenantId, int $userId, int $roleId): int
    {
        $st = $this->pdo->prepare('SELECT id FROM tenant_user_roles WHERE tenant_id = ? AND user_id = ? AND role_id = ? AND org_unit_id IS NULL LIMIT 1');
        $st->execute([$tenantId, $userId, $roleId]);
        $id = (int) ($st->fetchColumn() ?: 0);
        if ($id > 0) {
            return $id;
        }
        try {
            $this->pdo->prepare(
                'INSERT INTO tenant_user_roles (tenant_id, user_id, role_id, org_unit_id, co_unit_id, created_at) VALUES (?, ?, ?, NULL, 0, NOW())'
            )->execute([$tenantId, $userId, $roleId]);
        } catch (\PDOException) {
        }
        $st->execute([$tenantId, $userId, $roleId]);

        return (int) ($st->fetchColumn() ?: 0);
    }

    /** @param array<string, mixed> $meta */
    private function writeTur(int $turId, ?string $from, ?string $until, array $meta): void
    {
        $this->pdo->prepare('UPDATE tenant_user_roles SET valid_from = ?, valid_until = ?, metadata = ? WHERE id = ?')
            ->execute([$from, $until, self::encodeMeta($meta), $turId]);
    }

    /** Ajoute le motif au dernier mouvement journalisé par la synchronisation des rôles (qui n'en porte pas). */
    private function noteLatestLog(int $tenantId, int $userId, int $roleId, string $action, string $reason): void
    {
        if ($reason === '' || $roleId < 1 || !$this->hasTable('role_assignments_log')) {
            return;
        }
        try {
            $st = $this->pdo->prepare(
                'SELECT id FROM role_assignments_log WHERE tenant_id = ? AND user_id = ? AND role_id = ? AND action = ? AND reason IS NULL
                 ORDER BY id DESC LIMIT 1'
            );
            $st->execute([$tenantId, $userId, $roleId, $action]);
            $id = (int) ($st->fetchColumn() ?: 0);
            if ($id > 0) {
                $this->pdo->prepare('UPDATE role_assignments_log SET reason = ? WHERE id = ?')->execute([mb_substr($reason, 0, 500), $id]);
            }
        } catch (Throwable) {
        }
    }

    /** @return list<array<string, mixed>> */
    private function turRows(int $tenantId): array
    {
        if (!$this->hasTable('tenant_user_roles')) {
            return [];
        }
        $st = $this->pdo->prepare(
            'SELECT id, user_id, role_id, org_unit_id, valid_from, valid_until, metadata, created_at
             FROM tenant_user_roles WHERE tenant_id = ?'
        );
        $st->execute([$tenantId]);

        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /** @return list<array<string, mixed>> */
    private function legacyRows(int $tenantId): array
    {
        if (!$this->hasTable('user_roles')) {
            return [];
        }
        $st = $this->pdo->prepare(
            'SELECT ur.user_id, ur.role_id, ur.created_at FROM user_roles ur
             INNER JOIN roles r ON r.id = ur.role_id AND r.tenant_id = ?'
        );
        $st->execute([$tenantId]);

        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /** @return list<array<string, mixed>> */
    private function overrideRows(int $tenantId): array
    {
        if (!$this->hasTable('user_permission_overrides')) {
            return [];
        }
        $st = $this->pdo->prepare(
            'SELECT o.id, o.user_id, o.grant_flag, o.org_unit_id, o.reason, o.created_by_user_id, o.created_at, p.name, p.slug
             FROM user_permission_overrides o
             INNER JOIN permissions p ON p.id = o.permission_id
             WHERE o.tenant_id = ?'
        );
        $st->execute([$tenantId]);

        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /** @return array<string, array<string, mixed>> « membre:rôle » => dernière attribution */
    private function lastAssignments(int $tenantId): array
    {
        if (!$this->hasTable('role_assignments_log')) {
            return [];
        }
        $st = $this->pdo->prepare(
            "SELECT user_id, role_id, assigned_by, assigned_at, reason FROM role_assignments_log
             WHERE tenant_id = ? AND action = 'assign' ORDER BY assigned_at ASC, id ASC"
        );
        $st->execute([$tenantId]);
        $out = [];
        while ($r = $st->fetch(PDO::FETCH_ASSOC)) {
            $out[(int) $r['user_id'] . ':' . (int) $r['role_id']] = $r;
        }

        return $out;
    }

    /** @return array<int, string> */
    private function unitNames(int $tenantId): array
    {
        static $cache = [];
        if (isset($cache[$tenantId])) {
            return $cache[$tenantId];
        }
        $out = [];
        try {
            $st = $this->pdo->prepare('SELECT id, name FROM units WHERE tenant_id = ? ORDER BY name ASC');
            $st->execute([$tenantId]);
            while ($r = $st->fetch(PDO::FETCH_ASSOC)) {
                $out[(int) $r['id']] = (string) $r['name'];
            }
        } catch (Throwable) {
        }

        return $cache[$tenantId] = $out;
    }

    /**
     * Unités de la communauté, pour le formulaire.
     *
     * @return array<int, string>
     */
    public function units(int $tenantId): array
    {
        return $this->unitNames($tenantId);
    }

    /**
     * Noms affichés : membres connus, puis les autres comptes (anciens membres, responsables partis).
     *
     * @param list<mixed> $ids
     * @param array<int, array<string, mixed>> $members
     * @return array<int, string>
     */
    private function peopleNames(array $ids, array $members): array
    {
        $out = [];
        $missing = [];
        foreach (array_unique(array_map('intval', $ids)) as $id) {
            if ($id < 1) {
                continue;
            }
            if (isset($members[$id])) {
                $out[$id] = self::nameOf($members[$id]);
            } else {
                $missing[] = $id;
            }
        }
        foreach (array_chunk($missing, 500) as $chunk) {
            try {
                $st = $this->pdo->prepare(
                    'SELECT u.id, u.display_name, up.first_name, up.last_name FROM users u
                     LEFT JOIN user_profiles up ON up.user_id = u.id
                     WHERE u.id IN (' . implode(',', array_fill(0, count($chunk), '?')) . ')'
                );
                $st->execute($chunk);
                while ($u = $st->fetch(PDO::FETCH_ASSOC)) {
                    $out[(int) $u['id']] = self::nameOf($u);
                }
            } catch (Throwable) {
            }
        }

        return $out;
    }

    private function hasTable(string $table): bool
    {
        static $cache = [];
        if (array_key_exists($table, $cache)) {
            return $cache[$table];
        }
        try {
            $st = $this->pdo->prepare('SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1');
            $st->execute([$table]);
            $cache[$table] = (bool) $st->fetchColumn();
        } catch (Throwable) {
            $cache[$table] = false;
        }

        return $cache[$table];
    }

    /** @param array<string, mixed> $u */
    private static function nameOf(array $u): string
    {
        $n = trim((string) ($u['display_name'] ?? ''));
        if ($n === '') {
            $n = trim(trim((string) ($u['first_name'] ?? '')) . ' ' . trim((string) ($u['last_name'] ?? '')));
        }

        return $n !== '' ? $n : ('Membre #' . (int) ($u['id'] ?? 0));
    }

    private static function accountLabel(string $status): string
    {
        return match (strtolower(trim($status))) {
            'active' => 'Actif',
            'inactive' => 'Inactif',
            'pending', 'pending_verification' => 'En attente',
            'suspended' => 'Suspendu',
            default => $status,
        };
    }

    private static function day(?string $v): string
    {
        $s = trim((string) $v);

        return strlen($s) >= 10 && !str_starts_with($s, '0000') ? substr($s, 0, 10) : '';
    }

    private static function timestamp(?string $v): ?int
    {
        $s = trim((string) $v);
        if ($s === '' || str_starts_with($s, '0000')) {
            return null;
        }
        $ts = strtotime($s);

        return $ts === false ? null : $ts;
    }

    private static function fold(string $v): string
    {
        $v = mb_strtolower(trim($v));
        $t = function_exists('iconv') ? @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $v) : false;

        return is_string($t) && $t !== '' ? $t : $v;
    }

    public static function cleanReason(string $reason): string
    {
        $reason = trim(preg_replace('/\s+/u', ' ', $reason) ?? '');

        return mb_substr($reason, 0, self::REASON_MAX);
    }

    private static function unitReason(string $unit, string $reason): string
    {
        return 'Unité : ' . $unit . ($reason !== '' ? ' · ' . $reason : '');
    }

    /** @return array<string, mixed> */
    private static function decodeMeta(mixed $raw): array
    {
        if (!is_string($raw) || $raw === '') {
            return [];
        }
        $d = json_decode($raw, true);

        return is_array($d) ? $d : [];
    }

    /** @param array<string, mixed> $meta */
    private static function encodeMeta(array $meta): string
    {
        return (string) json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
