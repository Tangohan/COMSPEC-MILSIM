<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Repositories\SseLoreRepository;
use PDO;
use PDOStatement;
use PHPUnit\Framework\TestCase;
use Tests\Support\SseFilesSqlite;

/** SQLite autorise les paramètres répétés ; MySQL en préparation native les refuse (HY093). */
final class SseLoreUniqueParametersStatement extends PDOStatement
{
    public function execute(?array $params = null): bool
    {
        preg_match_all('/(?<!:):([a-zA-Z_][a-zA-Z0-9_]*)/', $this->queryString, $matches);
        TestCase::assertSame(count($matches[1]), count(array_unique($matches[1])), 'Chaque paramètre doit être unique pour PDO MySQL natif.');

        return parent::execute($params);
    }
}

final class SseLoreRepositoryTest extends TestCase
{
    public function testCreateUpdateAndTouchUseDistinctParametersAndKeepTimestampsConsistent(): void
    {
        $pdo = SseFilesSqlite::open();
        try {
            $repo = new SseLoreRepository();
            $pdo->setAttribute(PDO::ATTR_STATEMENT_CLASS, [SseLoreUniqueParametersStatement::class]);
            $id = $repo->create(1, ['op_key' => 'OP-TEST', 'title' => 'Préparation', 'country' => 'FRA'], null);
            $row = $repo->find(1, $id);
            self::assertNotNull($row);
            self::assertSame('Préparation', $row['title']);
            self::assertSame($row['created_at'], $row['updated_at']);
            self::assertSame($row['updated_at'], $row['content_updated_at']);
            $repo->update(1, $id, ['title' => 'Analyse', 'phase' => 2]);
            $repo->touch(1, $id);
            $row = $repo->find(1, $id);
            self::assertSame('Analyse', $row['title']);
            self::assertSame(2, $row['phase']);
            self::assertSame($row['updated_at'], $row['content_updated_at']);
            self::assertNull($repo->find(2, $id));
        } finally {
            SseFilesSqlite::close();
        }
    }
}
