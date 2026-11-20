<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Controllers\Auth\AuthController;
use App\Core\Request;
use App\Core\Session;
use App\Repositories\PlatformAccountBanRepository;
use App\Repositories\UserRepository;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Tests\Support\MysqlDdlSqlitePdo;

final class PlatformLoginRestrictionTest extends TestCase
{
    public function testVerifiedDiscordLoginShowsThePublicDecisionForAnInactiveBannedAccount(): void
    {
        $pdo = new MysqlDdlSqlitePdo();
        $pdo->exec('CREATE TABLE platform_account_bans (id INTEGER PRIMARY KEY, email_norm TEXT, reason TEXT,
            public_reason TEXT, rule_reference TEXT, ends_at TEXT, lifted_at TEXT)');
        $pdo->exec("INSERT INTO platform_account_bans (id, email_norm, reason, public_reason, rule_reference)
            VALUES (17, 'banni@example.test', 'Note interne confidentielle', 'Explication publique', 'CGU Athena')");
        $users = $this->createStub(UserRepository::class);
        $users->method('findById')->willReturn(['id' => 4, 'tenant_id' => 1, 'status' => 'inactive', 'email' => 'banni@example.test']);
        $reflection = new ReflectionClass(AuthController::class);
        $controller = $reflection->newInstanceWithoutConstructor();
        $reflection->getProperty('platformBans')->setValue($controller, new PlatformAccountBanRepository($pdo));
        $reflection->getProperty('userRepository')->setValue($controller, $users);

        $previous = $_SESSION ?? [];
        $_SESSION = ['pending_verification_email' => 'banni@example.test'];
        try {
            $response = $controller->completeVerifiedLogin(['id' => 4, 'tenant_id' => 1], new Request());
            self::assertSame(303, $response->statusCode());
            self::assertStringEndsWith('/login', $response->headerValue('Location'));
            self::assertNull(Session::get('pending_verification_email'));
            $notice = Session::getFlash('login_access_restriction');
            self::assertSame([
                'id' => 17, 'rule' => 'CGU Athena', 'message' => 'Explication publique', 'end' => 'Sans date de fin',
            ], $notice);
            self::assertNull(Session::getFlash('login_access_restriction'));
        } finally {
            $_SESSION = $previous;
        }
    }
}
