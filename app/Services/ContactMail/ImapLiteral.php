<?php

declare(strict_types=1);

namespace App\Services\ContactMail;

/** Argument IMAP envoyé en littéral {N} (texte non ASCII, corps d'un message à déposer). */
final class ImapLiteral
{
    public function __construct(public readonly string $data) {}
}
