<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Support\LazyDatabaseConnection;
use PDO;

final class PublicModpackRepository
{
    use LazyDatabaseConnection;

    public function current(): array
    {
        return $this->pdo()->query('SELECT * FROM public_modpack WHERE id = 1')->fetch(PDO::FETCH_ASSOC) ?: [];
    }

    public function countView(): void
    {
        $this->pdo()->exec('INSERT INTO public_modpack_daily (activity_date, views)
            VALUES (UTC_DATE(), 1) ON DUPLICATE KEY UPDATE views = views + 1');
    }

    public function countDownload(): void
    {
        $this->pdo()->exec('INSERT INTO public_modpack_daily (activity_date, downloads)
            VALUES (UTC_DATE(), 1) ON DUPLICATE KEY UPDATE downloads = downloads + 1');
    }

    public function activity(): array
    {
        return $this->pdo()->query('SELECT COALESCE(SUM(views), 0) AS views,
            COALESCE(SUM(downloads), 0) AS downloads,
            COALESCE(SUM(CASE WHEN activity_date >= UTC_DATE() - INTERVAL 6 DAY THEN downloads END), 0) AS recent,
            COALESCE(SUM(CASE WHEN activity_date BETWEEN UTC_DATE() - INTERVAL 13 DAY
                AND UTC_DATE() - INTERVAL 7 DAY THEN downloads END), 0) AS previous
            FROM public_modpack_daily')->fetch(PDO::FETCH_ASSOC);
    }

    public function publish(string $key, string $version, string $notes, int $bytes, string $sha256): void
    {
        $statement = $this->pdo()->prepare('UPDATE public_modpack SET archive_key = ?, version = ?,
            release_notes = ?, bytes = ?, sha256 = ?, published_at = UTC_TIMESTAMP(), published = 1 WHERE id = 1');
        $statement->execute([$key, $version, $notes, $bytes, $sha256]);
    }

    public function configure(?string $expected, int $max, int $threshold, int $speed, bool $published): void
    {
        $statement = $this->pdo()->prepare('UPDATE public_modpack SET expected_version = ?, max_downloads = ?,
            throttle_at = ?, throttle_mib = ?, published = IF(archive_key IS NULL, 0, ?) WHERE id = 1');
        $statement->execute([$expected, $max, $threshold, $speed, (int) $published]);
    }
}
