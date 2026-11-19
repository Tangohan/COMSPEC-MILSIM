<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Support\LazyDatabaseConnection;
use PDO;

final class PlatformDashboardActivityRepository
{
    use LazyDatabaseConnection;

    /** @return list<array{day: string, total: int}> */
    public function observedAccountsByDay(string $start, string $end): array
    {
        $statement = $this->pdo()->prepare(
            "SELECT DATE(created_at) AS day, COUNT(DISTINCT user_id) AS total
             FROM request_telemetry
             WHERE created_at >= ? AND created_at < ?
               AND method = 'GET' AND status_code BETWEEN 200 AND 299
               AND route_path NOT LIKE '/api/%' AND route_path NOT LIKE '/uploads/%'
             GROUP BY DATE(created_at)"
        );
        $statement->execute([$start, $end]);

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @param non-empty-list<string> $paths
     * @return array<string, int>
     */
    public function observedPageViews(array $paths, string $start, string $end): array
    {
        $placeholders = implode(', ', array_fill(0, count($paths), '?'));
        $statement = $this->pdo()->prepare(
            "SELECT route_path, COUNT(*) AS total FROM request_telemetry
             WHERE route_path IN ($placeholders) AND created_at >= ? AND created_at < ?
               AND method = 'GET' AND status_code = 200
             GROUP BY route_path"
        );
        $statement->execute([...$paths, $start, $end]);

        return $statement->fetchAll(PDO::FETCH_KEY_PAIR);
    }
}
