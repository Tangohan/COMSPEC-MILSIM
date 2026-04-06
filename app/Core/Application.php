<?php

declare(strict_types=1);

namespace App\Core;

class Application
{
    private Router $router;
    private Request $request;

    public function __construct()
    {
        $this->request = new Request();
        $this->router = new Router($this->request);
    }

    public function router(): Router
    {
        return $this->router;
    }

    public function request(): Request
    {
        return $this->request;
    }

    public function run(): void
    {
        \App\Core\Session::start();
        $runner = fn (\App\Core\Request $req): \App\Core\Response => $this->router->dispatch();
        $global = [
            new \App\Middleware\RequestIdMiddleware(),
            new \App\Middleware\ComspecTacticalApiMiddleware(),
            new \App\Middleware\SecurityHeadersMiddleware(),
            new \App\Middleware\RateLimitMiddleware(),
            new \App\Middleware\CsrfPostMiddleware(),
        ];
        foreach (array_reverse($global) as $mw) {
            $next = $runner;
            $runner = fn (\App\Core\Request $req): \App\Core\Response => $mw($req, $next);
        }
        $response = $runner($this->request);
        $response->send();
    }
}
