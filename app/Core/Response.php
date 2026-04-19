<?php

declare(strict_types=1);

namespace App\Core;

class Response
{
    private int $statusCode = 200;
    private array $headers = [];
    private string $body = '';
    /** @var callable|null */
    private $bodyStream = null;

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

    /** @param callable(): void $callback Outputs the response body (e.g. fpassthru). */
    public function setBodyStream(callable $callback): self
    {
        $this->bodyStream = $callback;
        return $this;
    }


    public function statusCode(): int
    {
        return $this->statusCode;
    }

    /** @return array<string,string> */
    public function headers(): array
    {
        return $this->headers;
    }

    public function body(): string
    {
        return $this->body;
    }

    public function send(): void
    {
        http_response_code($this->statusCode);
        foreach ($this->headers as $name => $value) {
            header("{$name}: {$value}");
        }
        if ($this->bodyStream !== null) {
            ($this->bodyStream)();
        } else {
            echo $this->body;
        }
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

    public static function json(array $data, int $statusCode = 200): self
    {
        $response = new self();
        $response->setStatusCode($statusCode)
            ->header('Content-Type', 'application/json; charset=utf-8')
            ->setBody(json_encode($data, JSON_UNESCAPED_UNICODE));
        return $response;
    }
}
