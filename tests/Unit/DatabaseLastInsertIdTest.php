<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Database;
use PHPUnit\Framework\TestCase;

final class DatabaseLastInsertIdTest extends TestCase
{
    public function testLastInsertIdIsPublicOnDatabase(): void
    {
        self::assertTrue(
            method_exists(Database::class, 'lastInsertId'),
            'SseDigitalLabRepository::createDevice() appelle Database::lastInsertId() après execute().'
        );
    }
}
