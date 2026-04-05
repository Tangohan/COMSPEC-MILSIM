<?php

declare(strict_types=1);

namespace App\Services\Moderation;

use RuntimeException;

final class ModerationQuarantineException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly int $artifactId,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, 0, $previous);
    }
}
