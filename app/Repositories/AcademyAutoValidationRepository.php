<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use App\Services\Academy\AcademyBuiltInCourses;
use App\Services\Academy\AcademyCurriculum;
use PDO;

/**
 * Accès aux données de la vérification automatique Athena des chapitres en attente (Academy). Tout est borné par
 * communauté (tenant_id). Classe non finale : les tests remplacent l'accès base par une version en mémoire.
 */
class AcademyAutoValidationRepository
{
    public const MIN_HOURS = 1;
    public const MAX_HOURS = 720;

    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Database::getPdo();
    }

    /** @return array{enabled: bool, hours: int, excluded: list<string>, final_allowed: list<string>} */
    public function settings(int $tenantId): array
    {
        $defaults = ['enabled' => false, 'hours' => AcademyCurriculum::AUTO_DEFAULT_HOURS, 'excluded' => [], 'final_allowed' => []];
        try {
            $stmt = $this->pdo->prepare('SELECT enabled, delay_hours, excluded_courses, final_courses FROM academy_auto_validation_settings WHERE tenant_id = ?');
            $stmt->execute([$tenantId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (\PDOException) {
            return $defaults;
        }

        return $row ? self::normalize($row) : $defaults;
    }

    /**
     * @param array<string, mixed> $row
     * @return array{enabled: bool, hours: int, excluded: list<string>, final_allowed: list<string>}
     */
    public static function normalize(array $row): array
    {
        $keys = static function (mixed $json): array {
            $list = json_decode((string) $json, true);

            return is_array($list)
                ? array_values(array_unique(array_filter($list, static fn (mixed $k): bool => is_string($k) && preg_match('/\A[a-z0-9-]{1,64}\z/', $k) === 1)))
                : [];
        };
        $excluded = $keys($row['excluded_courses'] ?? '');

        return [
            'enabled' => (int) ($row['enabled'] ?? 0) === 1,
            'hours' => max(self::MIN_HOURS, min(self::MAX_HOURS, (int) ($row['delay_hours'] ?? AcademyCurriculum::AUTO_DEFAULT_HOURS))),
            'excluded' => $excluded,
            'final_allowed' => $keys($row['final_courses'] ?? ''),
        ];
    }

    /**
     * @param list<string> $excluded
     * @param list<string> $finalAllowed cours dont le dernier chapitre peut être validé automatiquement
     */
    public function saveSettings(int $tenantId, bool $enabled, int $hours, array $excluded, ?int $by, array $finalAllowed = []): void
    {
        $hours = max(self::MIN_HOURS, min(self::MAX_HOURS, $hours));
        $clean = self::normalize(['excluded_courses' => json_encode($excluded), 'final_courses' => json_encode($finalAllowed)]);
        $this->pdo->prepare(
            'INSERT INTO academy_auto_validation_settings (tenant_id, enabled, delay_hours, excluded_courses, final_courses, updated_by) VALUES (?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE enabled = VALUES(enabled), delay_hours = VALUES(delay_hours), excluded_courses = VALUES(excluded_courses), final_courses = VALUES(final_courses), updated_by = VALUES(updated_by)'
        )->execute([$tenantId, $enabled ? 1 : 0, $hours, json_encode($clean['excluded']), json_encode($clean['final_allowed']), $by]);
    }

    /** @return array<int, array{enabled: bool, hours: int, excluded: list<string>, final_allowed: list<string>}> communautés qui ont activé la fonction, par identifiant */
    public function enabledTenants(): array
    {
        try {
            $rows = $this->pdo->query('SELECT tenant_id, enabled, delay_hours, excluded_courses, final_courses FROM academy_auto_validation_settings WHERE enabled = 1 ORDER BY tenant_id')->fetchAll(PDO::FETCH_ASSOC);
        } catch (\PDOException) {
            return [];
        }
        $out = [];
        foreach ($rows as $row) {
            $out[(int) $row['tenant_id']] = self::normalize($row);
        }

        return $out;
    }

    /**
     * Chapitres en attente d'un formateur depuis au moins $hours h (date de la dernière modification de l'inscription,
     * que ni les relances ni la vérification ne font bouger), pas encore vérifiés depuis $recheckBefore.
     *
     * @return list<array{user_id: int, course_key: string}>
     */
    public function candidates(int $tenantId, int $hours, int $recheckBefore, int $limit): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT user_id, course_key FROM academy_learners
             WHERE tenant_id = ? AND course_key <> '__notes'
               AND JSON_EXTRACT(state_json, '$.stage') = 6
               AND COALESCE(JSON_EXTRACT(state_json, '$.completed'), 'false') <> 'true'
               AND JSON_EXTRACT(state_json, '$.exempt.at') IS NULL
               AND updated_at <= DATE_SUB(NOW(), INTERVAL ? HOUR)
               AND COALESCE(CAST(JSON_UNQUOTE(JSON_EXTRACT(state_json, '$.auto_check.at')) AS UNSIGNED), 0) <= ?
             ORDER BY updated_at ASC LIMIT " . max(1, min(500, $limit))
        );
        $stmt->execute([$tenantId, $hours, $recheckBefore]);

        return array_map(
            static fn (array $r): array => ['user_id' => (int) $r['user_id'], 'course_key' => (string) $r['course_key']],
            $stmt->fetchAll(PDO::FETCH_ASSOC)
        );
    }

    /**
     * Modifie l'inscription sous verrou (même mécanisme que AcademyRepository::mutate). Le callback lève une
     * DomainException pour annuler sans rien écrire.
     *
     * @param callable(array<string, mixed>): array<string, mixed> $change
     */
    public function apply(int $tenantId, int $userId, string $key, callable $change): void
    {
        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare('SELECT state_json FROM academy_learners WHERE tenant_id = ? AND user_id = ? AND course_key = ? FOR UPDATE');
            $stmt->execute([$tenantId, $userId, $key]);
            $json = $stmt->fetchColumn();
            if ($json === false) {
                throw new \DomainException('not_enrolled');
            }
            $state = $change(AcademyBuiltInCourses::refreshSnapshot(json_decode($json, true, 64, JSON_THROW_ON_ERROR), $key));
            $this->pdo->prepare('UPDATE academy_learners SET state_json = ?, updated_at = CURRENT_TIMESTAMP WHERE tenant_id = ? AND user_id = ? AND course_key = ?')
                ->execute([json_encode($state, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE), $tenantId, $userId, $key]);
            $this->pdo->commit();
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Note la vérification non concluante dans l'inscription sans toucher à la date de modification (le délai d'attente
     * continue de courir). N'écrit que si le chapitre est toujours en attente : un formateur qui valide entre-temps l'emporte.
     *
     * @param array<string, mixed> $check
     */
    public function markChecked(int $tenantId, int $userId, string $key, int $chapter, array $check): bool
    {
        $stmt = $this->pdo->prepare(
            "UPDATE academy_learners SET state_json = JSON_SET(state_json, '$.auto_check', JSON_EXTRACT(?, '$')), updated_at = updated_at
             WHERE tenant_id = ? AND user_id = ? AND course_key = ? AND JSON_EXTRACT(state_json, '$.stage') = 6 AND JSON_EXTRACT(state_json, '$.chapter') = ?"
        );
        $stmt->execute([json_encode($check, JSON_UNESCAPED_UNICODE), $tenantId, $userId, $key, $chapter]);

        return $stmt->rowCount() > 0;
    }

    /**
     * Formateurs à prévenir : membres de la communauté qui ont une permission de pilotage de la formation, plus les
     * personnes désignées une à une pour valider. Les droits donnés à une unité ou à une fonction ne sont pas développés.
     *
     * @return list<int>
     */
    public function instructorIds(int $tenantId): array
    {
        $perm = "p.slug IN ('admin.organization', 'admin.access', 'training.manage', 'training.assign')";
        $ids = [];
        foreach ([
            ["SELECT DISTINCT u.id FROM users u JOIN roles r ON r.id = u.role_id AND r.tenant_id = ? JOIN role_permissions rp ON rp.role_id = u.role_id JOIN permissions p ON p.id = rp.permission_id WHERE {$perm}", [$tenantId]],
            ["SELECT DISTINCT tur.user_id FROM tenant_user_roles tur JOIN role_permissions rp ON rp.role_id = tur.role_id JOIN permissions p ON p.id = rp.permission_id WHERE tur.tenant_id = ? AND (tur.valid_until IS NULL OR tur.valid_until > NOW()) AND {$perm}", [$tenantId]],
            ["SELECT subject_id FROM academy_access_rules WHERE tenant_id = ? AND course_key = '' AND capability = 'validate' AND subject_type = 'user'", [$tenantId]],
        ] as [$sql, $params]) {
            try {
                $stmt = $this->pdo->prepare($sql);
                $stmt->execute($params);
                foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $id) {
                    $ids[(int) $id] = true;
                }
            } catch (\PDOException) {
                continue;
            }
        }
        unset($ids[0]);

        return array_keys($ids);
    }

    /**
     * Attestation, licence et qualifications du cours terminé, attribuées au nom d'Athena par la logique commune avec la
     * validation manuelle (AcademyCredentialAwarder). Chaque attribution nouvelle porte l'origine « auto Athena » dans son historique.
     *
     * @return list<int> attributions créées pour ce stagiaire
     */
    public function awardCredentials(int $tenantId, int $learnerId, string $key, int $hours): array
    {
        $ids = (new \App\Services\Academy\AcademyCredentialAwarder())->awardCompleted($tenantId, $learnerId, $key, 0);
        $awards = new QualificationAwardRepository();
        foreach ($ids as $id) {
            $awards->addHistory($tenantId, $id, 'auto_athena', null, sprintf('Attribuée automatiquement par Athena après %d h sans réponse du formateur (Academy : %s)', $hours, $key));
        }

        return $ids;
    }

    /** @param list<int> $ids */
    public function revokeAwards(int $tenantId, array $ids, int $instructorId): void
    {
        $awards = new QualificationAwardRepository();
        foreach ($ids as $id) {
            $row = $awards->find($id, $tenantId);
            if ($row === null) {
                continue;
            }
            $awards->setAdminStatus($id, \App\Support\QualificationAdminStatus::REVOKED, $instructorId, 'Validation automatique par Athena annulée par un formateur');
            $awards->addHistory($tenantId, $id, 'auto_athena_revoked', $instructorId, 'Attribution automatique par Athena annulée par un formateur');
        }
    }

    /** @param array<string, mixed> $payload */
    public function notify(int $tenantId, int $userId, string $type, array $payload): void
    {
        (new MemberNotificationRepository($this->pdo))->create($tenantId, $userId, $type, $payload);
    }

    /** @param array<string, mixed> $details */
    public function audit(string $action, int $tenantId, array $details): void
    {
        try {
            (new \App\Services\Audit\AuditService())->logChange($action, $tenantId, null, 'academy_course', null, [], $details);
        } catch (\Throwable $e) {
            error_log('[audit] ' . $action . ' : ' . $e->getMessage());
        }
    }
}
