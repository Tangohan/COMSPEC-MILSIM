<?php

declare(strict_types=1);

namespace App\Services\Security\Conditions;

use App\Core\Database;
use PDO;

final class ModuleValidatedConditionEvaluator implements ConditionEvaluatorInterface
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getPdo();
    }

    public function supports(string $conditionType): bool
    {
        return strtoupper($conditionType) === 'MODULE_VALIDATED';
    }

    public function evaluate(array $user, array $conditionValue): bool
    {
        $moduleId = (int) ($conditionValue['module_id'] ?? 0);
        $userId = (int) ($user['id'] ?? 0);
        if ($moduleId < 1 || $userId < 1) {
            return false;
        }

        // « Parcours validé » : le numéro saisi (clé module_id historique) désigne un parcours
        // (training_courses). Depuis le LMS, training_progress n'a plus ni user_id ni module_id :
        // on lit l'inscription terminée, comme TrainingEnrollmentRepository::userHasCompletedCourse.
        // Une erreur SQL refuse la condition au lieu de renvoyer une 500 sur la ressource protégée.
        try {
            $stmt = $this->pdo->prepare('SELECT 1 FROM training_enrollments WHERE user_id = ? AND course_id = ? AND status = \'completed\' LIMIT 1');
            $stmt->execute([$userId, $moduleId]);
        } catch (\PDOException) {
            return false;
        }

        return (bool) $stmt->fetchColumn();
    }
}
