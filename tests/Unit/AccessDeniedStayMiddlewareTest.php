<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Middleware\AccessDeniedStayMiddleware;
use PHPUnit\Framework\TestCase;

final class AccessDeniedStayMiddlewareTest extends TestCase
{
    private array $serverBackup = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->serverBackup = $_SERVER;
        $_SESSION = [];
        $_SERVER['HTTP_HOST'] = 'athena-comspec.eu';
        $_SERVER['SCRIPT_NAME'] = '/index.php';
    }

    protected function tearDown(): void
    {
        $_SERVER = $this->serverBackup;
        $_SESSION = [];
        parent::tearDown();
    }

    public function testDeniedPageReturnsToPreviousPageWithToast(): void
    {
        $this->bind('GET', '/back-office/effectifs/promotions', 'https://athena-comspec.eu/back-office/effectifs?tab=membres', 'document');
        $res = $this->run(static fn () => self::deny('Accès réservé aux administrateurs.'));

        self::assertSame(302, $res->statusCode());
        self::assertSame('/back-office/effectifs?tab=membres', $res->headerValue('Location'));
        self::assertSame('Accès réservé aux administrateurs.', Session::getFlash('error'));
    }

    public function testDeniedFetchGetsJson403AndNoLeftoverFlash(): void
    {
        $this->bind('POST', '/back-office/effectifs/1/grade', 'https://athena-comspec.eu/back-office/effectifs', 'empty');
        $res = $this->run(static fn () => self::deny('Vous n’avez pas accès.'));

        self::assertSame(403, $res->statusCode());
        self::assertSame('1', $res->headerValue('X-Ath-Access-Denied'));
        self::assertStringContainsString('access_denied', $res->body());
        self::assertNull(Session::getFlash('error'));
    }

    public function testNoRefererKeepsOriginalRedirect(): void
    {
        $this->bind('GET', '/back-office/effectifs', null, 'document');
        $res = $this->run(static fn () => self::deny('Non.'));

        self::assertSame(url('dashboard'), $res->headerValue('Location'));
    }

    public function testRefererOnSamePageOrOtherSiteKeepsOriginalRedirect(): void
    {
        $this->bind('GET', '/back-office/effectifs', 'https://athena-comspec.eu/back-office/effectifs', 'document');
        self::assertSame(url('dashboard'), $this->run(static fn () => self::deny('Non.'))->headerValue('Location'));

        $this->bind('GET', '/back-office/effectifs', 'https://evil.example/page', 'document');
        self::assertSame(url('dashboard'), $this->run(static fn () => self::deny('Non.'))->headerValue('Location'));
    }

    public function testRedirectWithoutNewErrorIsUntouched(): void
    {
        $this->bind('POST', '/back-office/effectifs/1', 'https://athena-comspec.eu/back-office/effectifs/1', 'document');
        $res = $this->run(static function (): Response {
            Session::flash('success', 'Enregistré.');

            return Response::redirect(url('dashboard'));
        });

        self::assertSame(url('dashboard'), $res->headerValue('Location'));
    }

    public function testRedirectToOtherPageIsUntouched(): void
    {
        $this->bind('POST', '/back-office/effectifs/1', 'https://athena-comspec.eu/back-office/effectifs', 'document');
        $res = $this->run(static function (): Response {
            Session::flash('error', 'Champ manquant.');

            return Response::redirect(url('back-office/effectifs/1/edit'));
        });

        self::assertSame(url('back-office/effectifs/1/edit'), $res->headerValue('Location'));
    }

    private static function deny(string $message): Response
    {
        Session::flash('error', $message);

        return Response::redirect(url('dashboard'));
    }

    private function run(callable $next): Response
    {
        return (new AccessDeniedStayMiddleware())(new Request(), $next);
    }

    private function bind(string $method, string $uri, ?string $referer, string $dest): void
    {
        $_SESSION = [];
        $_SERVER['REQUEST_METHOD'] = $method;
        $_SERVER['REQUEST_URI'] = $uri;
        $_SERVER['HTTP_SEC_FETCH_DEST'] = $dest;
        if ($referer === null) {
            unset($_SERVER['HTTP_REFERER']);
        } else {
            $_SERVER['HTTP_REFERER'] = $referer;
        }
    }
}
