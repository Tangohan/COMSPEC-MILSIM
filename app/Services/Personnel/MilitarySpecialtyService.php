<?php

declare(strict_types=1);

namespace App\Services\Personnel;

use App\Core\Database;
use App\Repositories\MilitarySpecialtyRepository;
use App\Support\Migrations\SchemaInspector;
use App\Support\QualificationAdminStatus;
use DateTimeImmutable;
use InvalidArgumentException;
use PDO;
use Throwable;

/** Descriptive RH/LMS links only: no assignment, rank, permission or qualification is awarded here. */
final class MilitarySpecialtyService
{
    private PDO $pdo;
    private MilitarySpecialtyRepository $repo;
    private SchemaInspector $schema;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Database::getPdo();
        $this->repo = new MilitarySpecialtyRepository($this->pdo);
        $this->schema = new SchemaInspector($this->pdo);
    }

    public function ready(): bool
    {
        return $this->repo->ready();
    }

    public function catalog(?string $country = null): array
    {
        return $this->repo->catalog($country);
    }

    public function findCode(int $id): ?array
    {
        return $this->repo->findCode($id);
    }

    public function listForMember(int $tenantId, int $userId): array
    {
        return $this->repo->listForMember($tenantId, $userId);
    }

    /** @param list<int|string> $userIds @return array<int, list<array<string, mixed>>> */
    public function confirmedForMembers(int $tenantId, array $userIds): array
    {
        return $this->repo->confirmedForMembers($tenantId, $userIds);
    }

    public function jobCodeIds(int $tenantId, int $jobId): array
    {
        return $this->repo->jobCodeIds($tenantId, $jobId);
    }

    public function qualificationTargets(int $tenantId, int $definitionId): array
    {
        return $this->repo->qualificationTargets($tenantId, $definitionId);
    }

    /** Replace only this job's descriptive references; archived references may be retained or removed. */
    public function saveJobCodes(int $tenantId, int $jobId, array $codeIds, int $actorId): void
    {
        $this->transaction($tenantId, $actorId, function () use ($tenantId, $jobId, $codeIds, $actorId): void {
            $this->assertLocalParent('personnel_job_roles', $tenantId, $jobId, 'Cet emploi n’appartient pas à votre communauté.');
            if (count($codeIds) > 64) {
                throw new InvalidArgumentException('Un emploi peut référencer au maximum 64 spécialités.');
            }
            $ids = [];
            foreach ($codeIds as $raw) {
                $ids[] = $this->id($raw, 'Choisissez une spécialité de référence valide.');
            }
            $ids = array_values(array_unique($ids));
            sort($ids);
            $before = $this->repo->jobCodeIds($tenantId, $jobId, true);
            foreach (array_diff($ids, $before) as $id) {
                $this->selectableCode((int) $id);
            }
            if ($before === $ids) {
                return;
            }
            foreach (array_diff($before, $ids) as $id) {
                $this->pdo->prepare('DELETE FROM personnel_job_role_specialty_codes WHERE tenant_id = ? AND job_role_id = ? AND specialty_code_id = ?')
                    ->execute([$tenantId, $jobId, $id]);
            }
            foreach (array_diff($ids, $before) as $id) {
                $this->pdo->prepare('INSERT INTO personnel_job_role_specialty_codes (tenant_id, job_role_id, specialty_code_id, created_by, created_at, updated_at) VALUES (?, ?, ?, ?, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)')
                    ->execute([$tenantId, $jobId, $id, $actorId]);
            }
            $this->audit($tenantId, $actorId, 'job_references', $jobId, ['specialty_code_ids' => $before], ['specialty_code_ids' => $ids]);
        });
    }

    /** One current row per member/code. Confirmed means RH confirmation within the simulation. */
    public function saveMember(int $tenantId, int $userId, array $input, int $actorId): int
    {
        return $this->transaction($tenantId, $actorId, function () use ($tenantId, $userId, $input, $actorId): int {
            $this->assertMember($tenantId, $userId);
            $codeId = $this->id($input['specialty_code_id'] ?? null, 'Choisissez une spécialité de référence.');
            $levelId = $this->optionalId($input['specialty_level_id'] ?? null, 'Le niveau de spécialité est invalide.');
            $proofId = $this->optionalId($input['evidence_qualification_id'] ?? null, 'La preuve de qualification est invalide.');
            $status = $input['status'] ?? 'declared';
            if (!is_string($status) || !in_array($status, ['declared', 'confirmed'], true)) {
                throw new InvalidArgumentException('Choisissez « Déclarée » ou « Confirmée par les RH ». Pour un retrait, utilisez l’archivage.');
            }
            $obtainedAt = $this->date($input['obtained_at'] ?? null, 'La date d’obtention doit être une date réelle au format AAAA-MM-JJ.');
            if ($obtainedAt !== null && $obtainedAt > date('Y-m-d')) {
                throw new InvalidArgumentException('La date d’obtention ne peut pas être future.');
            }
            $st = $this->pdo->prepare('SELECT * FROM personnel_profile_specialty_codes WHERE tenant_id = ? AND user_id = ? AND specialty_code_id = ?' . $this->lockSuffix());
            $st->execute([$tenantId, $userId, $codeId]);
            $before = $st->fetch(PDO::FETCH_ASSOC) ?: [];
            $after = ['specialty_code_id' => $codeId, 'specialty_level_id' => $levelId, 'status' => $status,
                'obtained_at' => $obtainedAt, 'evidence_qualification_id' => $proofId];
            if ($before !== [] && $this->snapshot($before) === $after) {
                return (int) $before['id'];
            }
            $code = $this->selectableCode($codeId);
            $level = $this->level($code, $levelId);
            if ($proofId !== null) {
                $this->assertProof($tenantId, $userId, $proofId, $status === 'confirmed');
            }
            if ($status === 'confirmed') {
                $this->assertVerified($code, 'La spécialité');
                if ($level !== null) {
                    $this->assertVerified($level, 'Le niveau');
                }
            }
            $validator = $status === 'confirmed' ? $actorId : null;
            $validatedAt = $status === 'confirmed' ? date('Y-m-d H:i:s') : null;
            if ($before === []) {
                $st = $this->pdo->prepare('INSERT INTO personnel_profile_specialty_codes
                    (tenant_id, user_id, specialty_code_id, specialty_level_id, status, obtained_at, evidence_qualification_id, validated_by, validated_at, created_at, updated_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)');
                $st->execute([$tenantId, $userId, $codeId, $levelId, $status, $obtainedAt, $proofId, $validator, $validatedAt]);
                $id = (int) $this->pdo->lastInsertId();
            } else {
                $id = (int) $before['id'];
                $this->pdo->prepare('UPDATE personnel_profile_specialty_codes SET specialty_level_id = ?, status = ?, obtained_at = ?, evidence_qualification_id = ?, validated_by = ?, validated_at = ?, updated_at = CURRENT_TIMESTAMP
                    WHERE tenant_id = ? AND user_id = ? AND id = ?')
                    ->execute([$levelId, $status, $obtainedAt, $proofId, $validator, $validatedAt, $tenantId, $userId, $id]);
            }
            $this->audit($tenantId, $actorId, 'member_saved', $id, $before === [] ? [] : $this->snapshot($before), $after + ['user_id' => $userId]);
            return $id;
        });
    }

    /** Archiving never depends on the continued availability/verification of the historical reference. */
    public function archiveMember(int $tenantId, int $userId, int $id, int $actorId): void
    {
        $this->transaction($tenantId, $actorId, function () use ($tenantId, $userId, $id, $actorId): void {
            $this->assertMember($tenantId, $userId);
            $st = $this->pdo->prepare('SELECT * FROM personnel_profile_specialty_codes WHERE tenant_id = ? AND user_id = ? AND id = ?' . $this->lockSuffix());
            $st->execute([$tenantId, $userId, $id]);
            $before = $st->fetch(PDO::FETCH_ASSOC);
            if ($before === false) {
                throw new InvalidArgumentException('Cette spécialité n’appartient pas au dossier du membre dans votre communauté.');
            }
            if ($before['status'] === 'archived') {
                return;
            }
            $this->pdo->prepare("UPDATE personnel_profile_specialty_codes SET status = 'archived', updated_at = CURRENT_TIMESTAMP WHERE tenant_id = ? AND user_id = ? AND id = ?")
                ->execute([$tenantId, $userId, $id]);
            $this->audit($tenantId, $actorId, 'member_archived', $id, $this->snapshot($before), array_replace($this->snapshot($before), ['status' => 'archived', 'user_id' => $userId]));
        });
    }

    /** Pedagogical targets do not grant a specialty when a course or qualification is completed. */
    public function saveQualificationTargets(int $tenantId, int $definitionId, array $targets, int $actorId): void
    {
        $this->transaction($tenantId, $actorId, function () use ($tenantId, $definitionId, $targets, $actorId): void {
            $this->applyQualificationTargets($tenantId, $definitionId, $targets, $actorId);
        });
    }

    /** Add/remove a single target under the same lock as the snapshot: concurrent edits to other codes survive. */
    public function changeQualificationTarget(int $tenantId, int $definitionId, int $codeId, mixed $levelId, int $actorId, bool $remove = false): void
    {
        $this->transaction($tenantId, $actorId, function () use ($tenantId, $definitionId, $codeId, $levelId, $actorId, $remove): void {
            $this->assertLocalParent('personnel_qualification_definitions', $tenantId, $definitionId, 'Cette qualification n’appartient pas à votre communauté.');
            $this->id($codeId, 'Choisissez une spécialité pour cet objectif.');
            $targets = array_values(array_filter($this->repo->qualificationTargets($tenantId, $definitionId, true),
                static fn (array $row): bool => (int) $row['specialty_code_id'] !== $codeId));
            if (!$remove) $targets[] = ['specialty_code_id' => $codeId, 'specialty_level_id' => $levelId];
            $this->applyQualificationTargets($tenantId, $definitionId, $targets, $actorId);
        });
    }

    private function applyQualificationTargets(int $tenantId, int $definitionId, array $targets, int $actorId): void
    {
            $this->assertLocalParent('personnel_qualification_definitions', $tenantId, $definitionId, 'Cette qualification n’appartient pas à votre communauté.');
            if (count($targets) > 64) {
                throw new InvalidArgumentException('Une qualification peut contribuer à 64 spécialités au maximum.');
            }
            $after = [];
            foreach ($targets as $target) {
                if (!is_array($target)) {
                    throw new InvalidArgumentException('L’objectif de spécialité est invalide.');
                }
                $codeId = $this->id($target['specialty_code_id'] ?? null, 'Choisissez une spécialité pour chaque objectif.');
                $levelId = $this->optionalId($target['specialty_level_id'] ?? null, 'Le niveau visé est invalide.');
                if (array_key_exists($codeId, $after) && $after[$codeId] !== $levelId) {
                    throw new InvalidArgumentException('Une spécialité ne peut avoir qu’un niveau visé par qualification.');
                }
                $after[$codeId] = $levelId;
            }
            ksort($after);
            $before = [];
            foreach ($this->repo->qualificationTargets($tenantId, $definitionId, true) as $row) {
                $before[(int) $row['specialty_code_id']] = $row['specialty_level_id'] === null ? null : (int) $row['specialty_level_id'];
            }
            foreach ($after as $codeId => $levelId) {
                if (array_key_exists($codeId, $before) && $before[$codeId] === $levelId) {
                    continue;
                }
                $this->level($this->selectableCode($codeId), $levelId);
            }
            if ($before === $after) {
                return;
            }
            foreach ($before as $codeId => $levelId) {
                if (!array_key_exists($codeId, $after)) {
                    $this->pdo->prepare('DELETE FROM qualification_specialty_targets WHERE tenant_id = ? AND definition_id = ? AND specialty_code_id = ?')
                        ->execute([$tenantId, $definitionId, $codeId]);
                }
            }
            foreach ($after as $codeId => $levelId) {
                if (array_key_exists($codeId, $before)) {
                    if ($before[$codeId] !== $levelId) {
                        $this->pdo->prepare('UPDATE qualification_specialty_targets SET specialty_level_id = ?, updated_at = CURRENT_TIMESTAMP WHERE tenant_id = ? AND definition_id = ? AND specialty_code_id = ?')
                            ->execute([$levelId, $tenantId, $definitionId, $codeId]);
                    }
                } else {
                    $this->pdo->prepare('INSERT INTO qualification_specialty_targets (tenant_id, definition_id, specialty_code_id, specialty_level_id, created_by, created_at, updated_at) VALUES (?, ?, ?, ?, ?, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)')
                        ->execute([$tenantId, $definitionId, $codeId, $levelId, $actorId]);
                }
            }
            $this->audit($tenantId, $actorId, 'qualification_targets', $definitionId, ['targets' => $before], ['targets' => $after]);
    }

    private function selectableCode(int $id): array
    {
        // Locking reads also see current reference edits inside a caller's REPEATABLE READ transaction.
        $code = $this->repo->findCode($id, true);
        if ($code === null || $code['archived_at'] !== null || $code['system_archived_at'] !== null) {
            throw new InvalidArgumentException('Cette spécialité est absente ou archivée dans le référentiel.');
        }
        if (!in_array($code['kind'], ['specialty', 'domain', 'entry_option', 'trade', 'trade_group'], true)
            || !in_array($code['verification_status'], ['verified', 'historical', 'unverified'], true)
            || ($code['code'] !== null && trim((string) $code['code']) === '')) {
            throw new InvalidArgumentException('La référence de spécialité est incohérente ; faites vérifier sa nomenclature.');
        }
        $from = $this->date($code['valid_from'], 'La date de début de validité de la référence est invalide.');
        $until = $this->date($code['valid_until'], 'La date de fin de validité de la référence est invalide.');
        if ($from !== null && $until !== null && $from > $until) {
            throw new InvalidArgumentException('Les dates de validité de la référence sont inversées.');
        }
        return $code;
    }

    private function level(array $code, ?int $id): ?array
    {
        if ($id === null) {
            return null;
        }
        foreach ($code['levels'] as $level) {
            if ((int) $level['id'] === $id && $level['archived_at'] === null) {
                return $level;
            }
        }
        throw new InvalidArgumentException('Le niveau doit appartenir à la spécialité choisie et être disponible.');
    }

    private function assertVerified(array $row, string $subject): void
    {
        $checked = $this->date($row['verified_at'] ?? null, $subject . ' a une date de vérification invalide.');
        if ($row['verification_status'] !== 'verified' || empty($row['source_id']) || empty($row['source_name'])
            || empty($row['source_url']) || $checked === null || $checked > date('Y-m-d')) {
            throw new InvalidArgumentException($subject . ' doit être vérifié, sourcé et daté avant une confirmation RH. Vous pouvez enregistrer une déclaration.');
        }
        $today = date('Y-m-d');
        if ((!empty($row['valid_from']) && $row['valid_from'] > $today) || (!empty($row['valid_until']) && $row['valid_until'] < $today)) {
            throw new InvalidArgumentException($subject . ' n’est pas en vigueur à la date de confirmation.');
        }
    }

    private function assertProof(int $tenantId, int $userId, int $id, bool $confirming): void
    {
        $st = $this->pdo->prepare('SELECT * FROM personnel_qualifications WHERE tenant_id = ? AND user_id = ? AND id = ?' . $this->lockSuffix());
        $st->execute([$tenantId, $userId, $id]);
        $proof = $st->fetch(PDO::FETCH_ASSOC);
        if ($proof === false) {
            throw new InvalidArgumentException('La qualification justificative doit appartenir à ce membre dans votre communauté.');
        }
        if ($confirming && (isset($proof['admin_status']) || isset($proof['status']))
            && QualificationAdminStatus::normalize((string) ($proof['admin_status'] ?? $proof['status'])) !== QualificationAdminStatus::OBTAINED) {
            throw new InvalidArgumentException('La preuve de confirmation doit être une qualification obtenue, sans suspension ni retrait.');
        }
    }

    private function assertMember(int $tenantId, int $userId): void
    {
        $st = $this->pdo->prepare('SELECT id, tenant_id FROM users WHERE id = ?' . $this->lockSuffix());
        $st->execute([$userId]);
        $user = $st->fetch(PDO::FETCH_ASSOC);
        if ($user !== false && $this->schema->tableExists('user_community_memberships')) {
            $membership = $this->pdo->prepare('SELECT status FROM user_community_memberships WHERE tenant_id = ? AND user_id = ?' . $this->lockSuffix());
            $membership->execute([$tenantId, $userId]);
            $status = $membership->fetchColumn();
            if ($status !== false) {
                if ($status === 'active') {
                    return;
                }
                throw new InvalidArgumentException('Ce membre ne fait plus partie de la communauté active.');
            }
        }
        if ($user === false || (int) $user['tenant_id'] !== $tenantId) {
            throw new InvalidArgumentException('Ce membre n’appartient pas à votre communauté active.');
        }
    }

    private function assertLocalParent(string $table, int $tenantId, int $id, string $message): void
    {
        // Table names are fixed callers, never request input.
        $st = $this->pdo->prepare('SELECT id FROM ' . $table . ' WHERE tenant_id = ? AND id = ?' . $this->lockSuffix());
        $st->execute([$tenantId, $id]);
        if ($id < 1 || $st->fetchColumn() === false) {
            throw new InvalidArgumentException($message);
        }
    }

    /** Savepoints protect a caller's existing transaction if a write or its journal insertion fails. */
    private function transaction(int $tenantId, int $actorId, callable $work): mixed
    {
        if (!$this->ready()) {
            throw new InvalidArgumentException('Appliquez la migration des spécialités militaires avant de modifier ces références.');
        }
        if ($tenantId < 1 || $actorId < 1) {
            throw new InvalidArgumentException('La communauté et l’auteur de la modification sont requis.');
        }
        $owns = !$this->pdo->inTransaction();
        if ($owns) {
            $this->pdo->beginTransaction();
        } else {
            $this->pdo->exec('SAVEPOINT military_specialty_write');
        }
        try {
            $st = $this->pdo->prepare('SELECT id FROM tenants WHERE id = ?' . $this->lockSuffix());
            $st->execute([$tenantId]);
            if ($st->fetchColumn() === false) {
                throw new InvalidArgumentException('Communauté introuvable.');
            }
            $actor = $this->pdo->prepare('SELECT id FROM users WHERE id = ?');
            $actor->execute([$actorId]);
            if ($actor->fetchColumn() === false) {
                throw new InvalidArgumentException('Auteur de la modification introuvable.');
            }
            $result = $work();
            if ($owns) {
                $this->pdo->commit();
            } else {
                $this->pdo->exec('RELEASE SAVEPOINT military_specialty_write');
            }
            return $result;
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                if ($owns) {
                    $this->pdo->rollBack();
                } else {
                    $this->pdo->exec('ROLLBACK TO SAVEPOINT military_specialty_write');
                    $this->pdo->exec('RELEASE SAVEPOINT military_specialty_write');
                }
            }
            throw $e;
        }
    }

    private function lockSuffix(): string
    {
        return $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite' ? '' : ' FOR UPDATE';
    }

    private function id(mixed $raw, string $message): int
    {
        if ((!is_int($raw) && (!is_string($raw) || preg_match('/^[1-9][0-9]*$/D', $raw) !== 1)) || (int) $raw < 1
            || (is_string($raw) && filter_var($raw, FILTER_VALIDATE_INT) === false)) {
            throw new InvalidArgumentException($message);
        }
        return (int) $raw;
    }

    private function optionalId(mixed $raw, string $message): ?int
    {
        return $raw === null || $raw === '' ? null : $this->id($raw, $message);
    }

    private function date(mixed $raw, string $message): ?string
    {
        if ($raw === null || $raw === '') {
            return null;
        }
        if (!is_string($raw)) {
            throw new InvalidArgumentException($message);
        }
        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $raw);
        if ($parsed === false || $parsed->format('Y-m-d') !== $raw || $raw < '1000-01-01') {
            throw new InvalidArgumentException($message);
        }
        return $raw;
    }

    private function snapshot(array $row): array
    {
        return ['specialty_code_id' => (int) $row['specialty_code_id'],
            'specialty_level_id' => $row['specialty_level_id'] === null ? null : (int) $row['specialty_level_id'],
            'status' => (string) $row['status'], 'obtained_at' => $row['obtained_at'],
            'evidence_qualification_id' => $row['evidence_qualification_id'] === null ? null : (int) $row['evidence_qualification_id']];
    }

    private function audit(int $tenantId, int $actorId, string $operation, int $id, array $before, array $after): void
    {
        $this->pdo->prepare('INSERT INTO audit_logs (tenant_id, user_id, action, entity_type, entity_id, old_value, new_value, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP)')
            ->execute([$tenantId, $actorId, 'personnel.specialty.' . $operation, 'military_specialty', $id,
                json_encode($before, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE), json_encode($after, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE)]);
    }
}
