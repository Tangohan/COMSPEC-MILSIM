<?php

declare(strict_types=1);

namespace App\Services\ContactMail;

/** Pièce jointe lue dans un e-mail. $content vaut null quand elle dépasse la taille permise (seule sa fiche est gardée). */
final class ParsedAttachment
{
    public function __construct(
        public readonly string $filename,
        public readonly string $mime,
        public readonly ?string $content,
        public readonly int $size,
        public readonly string $contentId = '',
        public readonly bool $inline = false,
    ) {}
}
