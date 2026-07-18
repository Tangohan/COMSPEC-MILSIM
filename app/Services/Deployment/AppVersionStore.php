<?php

declare(strict_types=1);

namespace App\Services\Deployment;

/**
 * Lecture / écriture de storage/app_version.json.
 */
final class AppVersionStore
{
    public function path(): string
    {
        return base_path('storage/app_version.json');
    }

    public function current(): string
    {
        return platform_app_version();
    }

    public function write(string $version): void
    {
        $version = trim($version);
        if ($version === '' || !preg_match('/^\d+\.\d+\.\d+/', $version)) {
            throw new \InvalidArgumentException('Version invalide.');
        }

        $payload = [
            'version' => $version,
            'updated_at' => (new \DateTimeImmutable('now'))->format(\DateTimeInterface::ATOM),
        ];

        $dir = dirname($this->path());
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new \RuntimeException('Impossible de créer le dossier de version.');
        }

        $tmp = $this->path() . '.tmp';
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        if ($json === false || file_put_contents($tmp, $json . "\n") === false) {
            throw new \RuntimeException('Impossible d’écrire la version applicative.');
        }
        if (!rename($tmp, $this->path())) {
            @unlink($tmp);
            throw new \RuntimeException('Impossible de finaliser la version applicative.');
        }
    }
}
