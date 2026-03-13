<?php

declare(strict_types=1);

namespace App\Core;

class Request
{
    private array $query;
    private array $request;
    private array $server;
    private string $method;
    private string $uri;
    private string $path;

    public function __construct()
    {
        $this->query = $_GET;
        $this->request = $_POST;
        $this->server = $_SERVER;
        $this->method = strtoupper($this->server['REQUEST_METHOD'] ?? 'GET');
        $this->uri = $this->server['REQUEST_URI'] ?? '/';
        $this->path = parse_url($this->uri, PHP_URL_PATH) ?: '/';
        $this->path = '/' . trim($this->path, '/');
        if ($this->path !== '/') {
            $this->path = rtrim($this->path, '/') ?: '/';
        }
        // Si l'app est servie depuis un sous-dossier (ex. /public/), retirer ce préfixe pour le routage
        if (str_starts_with($this->path, '/public')) {
            $this->path = substr($this->path, 7) ?: '/';
        }
    }

    public function method(): string
    {
        return $this->method;
    }

    public function path(): string
    {
        return $this->path;
    }

    public function uri(): string
    {
        return $this->uri;
    }

    public function query(string $key, mixed $default = null): mixed
    {
        return $this->query[$key] ?? $default;
    }

    public function input(string $key, mixed $default = null): mixed
    {
        return $this->request[$key] ?? $default;
    }

    public function all(): array
    {
        return array_merge($this->query, $this->request);
    }

    public function isPost(): bool
    {
        return $this->method === 'POST';
    }

    public function isGet(): bool
    {
        return $this->method === 'GET';
    }

    public function ip(): string
    {
        return $this->server['HTTP_X_FORWARDED_FOR'] ?? $this->server['HTTP_CLIENT_IP'] ?? $this->server['REMOTE_ADDR'] ?? '';
    }

    public function userAgent(): string
    {
        return $this->server['HTTP_USER_AGENT'] ?? '';
    }
}
