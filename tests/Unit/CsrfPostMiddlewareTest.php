<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Middleware\CsrfPostMiddleware;
use PHPUnit\Framework\TestCase;

final class CsrfPostMiddlewareTest extends TestCase
{
    protected function tearDown(): void
    {
        $_POST = [];
        unset($_SERVER['HTTP_X_CSRF_TOKEN']);
        parent::tearDown();
    }

    public function testStandardFieldPasses(): void
    {
        $this->bindPost('/admin/atak-mod-reports/status', ['_csrf_token' => Csrf::token()]);
        self::assertTrue($this->passes());
    }

    public function testShortFieldNameUsedByModReportsPasses(): void
    {
        $this->bindPost('/admin/atak-mod-reports/bulk', ['_csrf' => Csrf::token()]);
        self::assertTrue($this->passes());
    }

    public function testHeaderTokenFromFetchPasses(): void
    {
        $this->bindPost('/back-office/ressources/atak-realism/save', []);
        $_SERVER['HTTP_X_CSRF_TOKEN'] = Csrf::token();
        self::assertTrue($this->passes());
    }

    public function testMissingOrWrongTokenIsRejected(): void
    {
        $this->bindPost('/admin/atak-mod-reports/status', ['_csrf' => 'nope']);
        self::assertFalse($this->passes());
    }

    private function passes(): bool
    {
        $ok = new Response();
        $mw = new CsrfPostMiddleware();

        return $mw(new Request(), static fn () => $ok) === $ok;
    }

    /** @param array<string, string> $post */
    private function bindPost(string $uri, array $post): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = $uri;
        $_SERVER['SCRIPT_NAME'] = '/index.php';
        $_POST = $post;
    }
}
