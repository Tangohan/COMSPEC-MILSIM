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
        $response = $this->router->dispatch();
        $response->send();
    }
}
