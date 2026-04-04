<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

class AtakIntelRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getPdo();
    }

    public function store(string $type, string $author, ?float $posX = null, ?float $posY = null, ?string $content = null, ?array $metadata = null): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO atak_intel (type, author, pos_x, pos_y, content, metadata) VALUES (?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $type,
            $author,
            $posX,
            $posY,
            $content,
            $metadata !== null ? json_encode($metadata, JSON_UNESCAPED_UNICODE) : null,
        ]);
        return (int) $this->pdo->lastInsertId();
    }
}
