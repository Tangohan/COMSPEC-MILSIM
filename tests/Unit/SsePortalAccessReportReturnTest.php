<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Middleware\SsePortalAccessMiddleware;
use App\Support\LoginIntendedDestination;
use PHPUnit\Framework\TestCase;

/**
 * QR code d'une alerte scanné sans session : passage par la connexion puis retour sur la page de signalement, jamais ailleurs
 * (chemin interne uniquement, aucune redirection ouverte). Les autres pages du portail gardent le renvoi vers le sas.
 */
final class SsePortalAccessReportReturnTest extends TestCase
{
    /** @var array<string, mixed> */
    private array $saved = [];

    protected function setUp(): void
    {
        $this->saved = ['session' => $_SESSION ?? null, 'server' => $_SERVER, 'env' => $_ENV];
        $_SESSION = [];
        $_ENV['APP_URL'] = 'https://athena.example';
        $_SERVER['HTTP_HOST'] = 'athena.example';
        $_SERVER['HTTPS'] = 'on';
    }

    protected function tearDown(): void
    {
        $_SERVER = $this->saved['server'];
        $_ENV = $this->saved['env'];
        if ($this->saved['session'] === null) {
            unset($_SESSION);
        } else {
            $_SESSION = $this->saved['session'];
        }
    }

    private function visit(string $uri, string $method = 'GET'): Response
    {
        $_SERVER['REQUEST_URI'] = $uri;
        $_SERVER['REQUEST_METHOD'] = $method;

        return (new SsePortalAccessMiddleware())(new Request(), static fn (): Response => (new Response())->setBody('NEXT'));
    }

    public function testAnonymousVisitorOnAReportPageGoesToLoginAndComesBackToIt(): void
    {
        $token = 'AbCdEfGhIjKlMnOpQrStUv';
        $res = $this->visit('/atak/sse/signalement/' . $token);

        self::assertSame(302, $res->statusCode());
        self::assertSame(url('login'), $res->headerValue('Location'));
        self::assertSame(url('atak/sse/signalement/' . $token), LoginIntendedDestination::consumeRedirectUrl());
        self::assertNull(LoginIntendedDestination::consumeRedirectUrl(), 'le retour ne sert qu’une fois');
    }

    public function testOtherPortalPagesAndWritesStillGoToTheGateWithoutAReturn(): void
    {
        foreach ([['/atak/sse/operations', 'GET'], ['/atak/sse/signalement/AbCdEfGhIjKlMnOpQrStUv', 'POST'], ['/atak/sse/signalement/AbCdEfGhIjKlMnOpQrStUv/qr.svg', 'GET']] as [$uri, $method]) {
            $_SESSION = [];
            $res = $this->visit($uri, $method);

            self::assertSame(url('atak/sse'), $res->headerValue('Location'), $method . ' ' . $uri);
            self::assertNull(LoginIntendedDestination::consumeRedirectUrl(), $method . ' ' . $uri);
        }
    }

    public function testTheReturnNeverLeavesTheSite(): void
    {
        foreach (['https://evil.example/x', '//evil.example/x', 'javascript:alert(1)', '/../../etc/passwd', 'atak/../x'] as $bad) {
            Session::set('post_login_redirect', ['suffix' => $bad, 'expires_at' => time() + 600]);
            self::assertNull(LoginIntendedDestination::consumeRedirectUrl(), $bad);
        }
        Session::set('post_login_redirect', ['suffix' => 'atak/sse/signalement/AbCdEfGhIjKlMnOpQrStUv', 'expires_at' => time() - 5]);
        self::assertNull(LoginIntendedDestination::consumeRedirectUrl(), 'un retour expiré est ignoré');
        self::assertNull(LoginIntendedDestination::suffixFromRedirect('login'));
        self::assertSame('atak/sse/signalement/AbCdEfGhIjKlMnOpQrStUv', LoginIntendedDestination::suffixFromRedirect('https://evil.example/atak/sse/signalement/AbCdEfGhIjKlMnOpQrStUv'), 'seul le chemin est retenu, jamais l’hôte');
    }
}
