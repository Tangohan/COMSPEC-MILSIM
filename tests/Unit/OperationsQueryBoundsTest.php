<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Controllers\Api\OperationsApiController;
use App\Core\Request;
use PHPUnit\Framework\TestCase;

/**
 * NET-021 (suite) : une borne ?from= ou ?to= de l'API opérations envoyée en tableau (?from[]=x) est ignorée au lieu
 * de lever « Array to string conversion », que le gestionnaire d'erreurs de l'application change en erreur 500.
 */
final class OperationsQueryBoundsTest extends TestCase
{
    /** @var array<string, mixed> */
    private array $savedGet = [];

    private bool $hadPost = false;

    private mixed $savedPost = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->savedGet = $_GET;
        // Un test précédent peut avoir retiré $_POST (unset) : Request le copie dans une propriété typée array.
        $this->hadPost = isset($_POST);
        $this->savedPost = $_POST ?? null;
        $_POST = [];
    }

    protected function tearDown(): void
    {
        $_GET = $this->savedGet;
        if ($this->hadPost) {
            $_POST = $this->savedPost;
        } else {
            unset($_POST);
        }
        parent::tearDown();
    }

    public function testArrayBoundIsIgnored(): void
    {
        self::assertNull($this->nullableQuery(['from' => ['x']], 'from'));
        self::assertNull($this->nullableQuery(['to' => ['a' => 'b']], 'to'));
    }

    public function testScalarBoundsAreUnchanged(): void
    {
        self::assertSame('2026-10-26 10:00:00', $this->nullableQuery(['from' => ' 2026-10-26 10:00:00 '], 'from'));
        self::assertSame('0', $this->nullableQuery(['to' => '0'], 'to'));
        self::assertNull($this->nullableQuery(['from' => '   '], 'from'));
        self::assertNull($this->nullableQuery([], 'from'));
    }

    /** @param array<string, mixed> $get */
    private function nullableQuery(array $get, string $key): ?string
    {
        $_GET = $get;
        $method = new \ReflectionMethod(OperationsApiController::class, 'nullableQuery');
        $controller = (new \ReflectionClass(OperationsApiController::class))->newInstanceWithoutConstructor();
        // Comme l'application (bootstrap/app.php, ExceptionHandler::register) : un avertissement devient une exception.
        set_error_handler(static function (int $no, string $msg, string $file = '', int $line = 0): bool {
            throw new \ErrorException($msg, 0, $no, $file, $line);
        });
        try {
            return $method->invoke($controller, new Request(), $key);
        } finally {
            restore_error_handler();
        }
    }
}
