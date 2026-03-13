<?php

declare(strict_types=1);

namespace App\Core;

class Response
{
    private int $statusCode = 200;
    private array $headers = [];
    private string $body = '';

    public function setStatusCode(int $code): self
    {
        $this->statusCode = $code;
        return $this;
    }

    public function header(string $name, string $value): self
    {
        $this->headers[$name] = $value;
        return $this;
    }

    public function setBody(string $body): self
    {
        $this->body = $body;
        return $this;
    }

    public function send(): void
    {
        http_response_code($this->statusCode);
        foreach ($this->headers as $name => $value) {
            header("{$name}: {$value}");
        }
        echo $this->body;
    }

    public static function redirect(string $url, int $status = 302): self
    {
        $response = new self();
        $response->setStatusCode($status)->header('Location', $url);
        return $response;
    }

    public static function view(string $viewPath, array $data = []): self
    {
        extract($data, EXTR_SKIP);
        ob_start();
        $fullPath = base_path('views/' . str_replace('.', '/', $viewPath) . '.php');
        if (is_file($fullPath)) {
            require $fullPath;
        }
        $body = ob_get_clean() ?: '';
        $response = new self();
        $response->header('Content-Type', 'text/html; charset=utf-8')->setBody($body);
        return $response;
    }
}
