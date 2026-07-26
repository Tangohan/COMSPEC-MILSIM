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

    /** @var array<string, mixed> Contexte requête (ex. API intégrations, hors session). */
    private array $attributes = [];

    public function __construct()
    {
        $this->query = $_GET;
        $this->request = $_POST;
        $this->server = $_SERVER;
        $this->method = strtoupper($this->server['REQUEST_METHOD'] ?? 'GET');
        $this->uri = $this->server['REQUEST_URI'] ?? '/';
        $this->path = self::normalizePathFromServer($this->server);
    }

    /**
     * Chemin HTTP canonique (après retrait du base path), identique au routage.
     */
    public static function normalizePathFromServer(?array $server = null): string
    {
        $server = $server ?? $_SERVER;
        $uri = $server['REQUEST_URI'] ?? '/';
        $path = parse_url((string) $uri, PHP_URL_PATH) ?: '/';
        $path = '/' . trim((string) $path, '/');
        if ($path !== '/') {
            $path = rtrim($path, '/') ?: '/';
        }
        $prefix = self::basePathPrefixFromServer($server);
        if ($prefix !== '' && str_starts_with($path, $prefix)) {
            $path = substr($path, strlen($prefix)) ?: '/';
        }

        // Docroot = dossier « public » : l’URI peut contenir /public/… alors que SCRIPT_NAME vaut
        // souvent /index.php (sans « /public/ »). Sans ce retrait, le routeur voit /public/admin/… → 404.
        if ($prefix === '' && ($path === '/public' || str_starts_with($path, '/public/'))) {
            $script = (string) ($server['SCRIPT_NAME'] ?? '');
            $scriptReferencesPublicDir = str_contains($script, '/public/');
            $frontControllerIndex = $script === '/index.php' || str_ends_with($script, '/index.php');
            if (!$scriptReferencesPublicDir && $frontControllerIndex) {
                $path = $path === '/public' ? '/' : ('/' . ltrim(substr($path, strlen('/public')), '/'));
                if ($path !== '/') {
                    $path = rtrim($path, '/') ?: '/';
                }
            }
        }

        // Filet : APP_BASE_PATH incohérent avec l’URI (ex. valeur erronée) laisse /public/… alors que les routes sont sans ce segment → 404.
        while ($path !== '/' && ($path === '/public' || str_starts_with($path, '/public/'))) {
            $path = $path === '/public' ? '/' : ('/' . ltrim(substr($path, strlen('/public')), '/'));
            if ($path !== '/') {
                $path = rtrim($path, '/') ?: '/';
            }
        }

        return $path;
    }

    /**
     * Préfixe du base path (ex. /public ou APP_BASE_PATH), aligné avec url().
     */
    public static function basePathPrefixFromServer(array $server): string
    {
        $prefix = rtrim((string) (function_exists('env') ? env('APP_BASE_PATH', '') : (getenv('APP_BASE_PATH') ?: '')), '/');
        if ($prefix === '' && isset($server['SCRIPT_NAME']) && str_contains((string) $server['SCRIPT_NAME'], '/public/')) {
            $prefix = '/public';
        }

        return $prefix;
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

    /** Alias query string (compat repositories ATAK Phase 2). */
    public function get(string $key, mixed $default = null): mixed
    {
        return $this->query($key, $default);
    }

    /** @return array<string, mixed> */
    public function queryParams(): array
    {
        return $this->query;
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

    /** @param mixed $value */
    public function setAttribute(string $key, mixed $value): void
    {
        $this->attributes[$key] = $value;
    }

    public function attribute(string $key, mixed $default = null): mixed
    {
        return $this->attributes[$key] ?? $default;
    }

}
