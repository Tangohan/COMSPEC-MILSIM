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

        $hasProgress = $this->pdo->query("SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'training_progress' LIMIT 1");
        if (!$hasProgress || !$hasProgress->fetchColumn()) {
            return false;
        }

        $stmt = $this->pdo->prepare('SELECT 1 FROM training_progress WHERE user_id = ? AND module_id = ? AND status IN (\'validated\',\'completed\') LIMIT 1');
        $stmt->execute([$userId, $moduleId]);

        return (bool) $stmt->fetchColumn();
    }
}
