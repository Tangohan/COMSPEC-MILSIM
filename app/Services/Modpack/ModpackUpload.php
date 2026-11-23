<?php

declare(strict_types=1);

namespace App\Services\Modpack;

final class ModpackUpload
{
    public const MAX_BLOCK_BYTES = 786432;
    public const MAX_ARCHIVE_BYTES = 21474836480;

    public function __construct(
        private readonly string $directory,
        private readonly int $userId,
    ) {
    }

    public static function validToken(string $token): bool
    {
        return preg_match('/\A[a-f0-9]{32}\z/', $token) === 1;
    }

    public static function hasRarSignature(string $bytes): bool
    {
        return str_starts_with($bytes, "Rar!\x1a\x07\x00") || str_starts_with($bytes, "Rar!\x1a\x07\x01\x00");
    }

    public function locked(callable $operation): mixed
    {
        $lock = @fopen($this->directory . '/upload-' . $this->userId . '.lock', 'c');
        if ($lock === false) {
            throw new \RuntimeException(__('modpack.upload_storage'));
        }
        try {
            if (!flock($lock, LOCK_EX | LOCK_NB)) {
                throw new \RuntimeException(__('modpack.upload_busy'));
            }
            return $operation();
        } finally {
            fclose($lock);
        }
    }

    public function path(string $token): string
    {
        if (!self::validToken($token)) {
            throw new \InvalidArgumentException(__('modpack.upload_id'));
        }
        return $this->directory . '/upload-' . $this->userId . '-' . $token . '.part';
    }

    public function append(string $token, int $offset, string $bytes): int
    {
        $length = strlen($bytes);
        if ($length < 1 || $length > self::MAX_BLOCK_BYTES || $offset < 0 || $offset > self::MAX_ARCHIVE_BYTES - $length) {
            throw new \DomainException(__('modpack.upload_size'));
        }
        if ($offset === 0 && !self::hasRarSignature($bytes)) {
            throw new \DomainException(__('modpack.upload_signature'));
        }

        return $this->locked(function () use ($token, $offset, $bytes, $length): int {
            $path = $this->path($token);
            $file = @fopen($path, $offset === 0 ? 'c+b' : 'r+b');
            if ($file === false) {
                throw new \DomainException(__('modpack.upload_missing'));
            }

            try {
                $size = fstat($file)['size'] ?? -1;
                if ($size === $offset + $length && fseek($file, $offset) === 0 && fread($file, $length) === $bytes) {
                    return $size;
                }
                if ($size !== $offset) {
                    throw new \DomainException(__('modpack.upload_offset'));
                }
                if (fseek($file, $offset) !== 0 || @fwrite($file, $bytes) !== $length || !fflush($file)) {
                    @ftruncate($file, $offset);
                    throw new \RuntimeException(__('modpack.upload_disk'));
                }
                if ($offset === 0) {
                    foreach (glob($this->directory . '/upload-' . $this->userId . '-*.part') ?: [] as $old) {
                        if ($old !== $path) {
                            @unlink($old);
                        }
                    }
                }
                return $offset + $length;
            } finally {
                fclose($file);
            }
        });
    }
}
