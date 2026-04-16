<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

final class SiteRoleAssignmentRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getPdo();
    }

    /** @return list<int> role_id actifs pour cet email */
    public function activeRoleIdsForEmail(string $email): array
    {
        $email = strtolower(trim($email));
        if ($email === '') {
            return [];
        }
        $stmt = $this->pdo->prepare(
            'SELECT sra.role_id FROM site_role_assignments sra
             INNER JOIN roles r ON r.id = sra.role_id AND r.tenant_id IS NULL AND r.role_layer = \'site\'
             WHERE sra.email_normalized = ? AND sra.revoked_at IS NULL'
        );
        $stmt->execute([$email]);

        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    /** @return list<array<string, mixed>> */
    public function listAllWithAssignments(): array
    {
        $roles = $this->pdo->query(
            "SELECT id, name, slug, description FROM roles WHERE tenant_id IS NULL AND role_layer = 'site' ORDER BY name ASC"
        )->fetchAll(PDO::FETCH_ASSOC);

        $out = [];
        foreach ($roles as $r) {
            $rid = (int) $r['id'];
            $st = $this->pdo->prepare(
                'SELECT id, email_normalized, assigned_by_user_id, created_at, revoked_at FROM site_role_assignments WHERE role_id = ? AND revoked_at IS NULL ORDER BY email_normalized ASC'
            );
            $st->execute([$rid]);
            $out[] = array_merge($r, ['assignments' => $st->fetchAll(PDO::FETCH_ASSOC)]);
        }

        return $out;
    }

    public function assign(string $email, int $siteRoleId, ?int $assignedByUserId = null): bool
    {
        $email = strtolower(trim($email));
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return false;
        }
        $chk = $this->pdo->prepare('SELECT id FROM roles WHERE id = ? AND tenant_id IS NULL AND role_layer = ? LIMIT 1');
        $chk->execute([$siteRoleId, 'site']);
        if (!$chk->fetch()) {
            return false;
        }
        $ex = $this->pdo->prepare('SELECT id, revoked_at FROM site_role_assignments WHERE email_normalized = ? AND role_id = ? LIMIT 1');
        $ex->execute([$email, $siteRoleId]);
        $row = $ex->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            if ($row['revoked_at'] !== null && $row['revoked_at'] !== '') {
                $this->pdo->prepare('UPDATE site_role_assignments SET revoked_at = NULL, assigned_by_user_id = ?, created_at = NOW() WHERE id = ?')
                    ->execute([$assignedByUserId, (int) $row['id']]);
            }

            return true;
        }
        $this->pdo->prepare('INSERT INTO site_role_assignments (email_normalized, role_id, assigned_by_user_id, created_at) VALUES (?, ?, ?, NOW())')
            ->execute([$email, $siteRoleId, $assignedByUserId]);

        return true;
    }

    public function revoke(int $assignmentId): bool
    {
        $st = $this->pdo->prepare('UPDATE site_role_assignments SET revoked_at = NOW() WHERE id = ? AND revoked_at IS NULL');
        $st->execute([$assignmentId]);

        return $st->rowCount() > 0;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findActiveAssignmentById(int $assignmentId): ?array
    {
        if ($assignmentId < 1) {
            return null;
        }
        $st = $this->pdo->prepare(
            "SELECT sra.id, sra.email_normalized, sra.role_id, r.name AS role_name, r.slug AS role_slug
             FROM site_role_assignments sra
             INNER JOIN roles r ON r.id = sra.role_id AND r.tenant_id IS NULL AND r.role_layer = 'site'
             WHERE sra.id = ? AND sra.revoked_at IS NULL
             LIMIT 1"
        );
        $st->execute([$assignmentId]);
        $row = $st->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    /**
     * @param list<string> $roleSlugs
     * @return list<string>
     */
    public function listActiveEmailsByRoleSlugs(array $roleSlugs): array
    {
        $slugs = array_values(array_unique(array_filter(array_map(
            static fn (mixed $v): string => strtolower(trim((string) $v)),
            $roleSlugs
        ), static fn (string $v): bool => $v !== '')));
        if ($slugs === []) {
            return [];
        }

        try {
            $in = implode(',', array_fill(0, count($slugs), '?'));
            $stmt = $this->pdo->prepare(
                "SELECT DISTINCT sra.email_normalized
                 FROM site_role_assignments sra
                 INNER JOIN roles r ON r.id = sra.role_id AND r.tenant_id IS NULL AND r.role_layer = 'site'
                 WHERE sra.revoked_at IS NULL
                   AND r.slug IN ({$in})"
            );
            $stmt->execute($slugs);
            $rows = $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
            $emails = [];
            foreach ($rows as $email) {
                $mail = strtolower(trim((string) $email));
                if ($mail !== '' && filter_var($mail, FILTER_VALIDATE_EMAIL)) {
                    $emails[] = $mail;
                }
            }

            return array_values(array_unique($emails));
        } catch (\Throwable) {
            return [];
        }
    }
}
