<?php

declare(strict_types=1);

namespace App\Services\ContactMail;

/** E-mail reçu, décodé en UTF-8 : en-têtes, corps texte et HTML bruts (non assainis), pièces jointes. */
final class ParsedMessage
{
    /**
     * @param list<array{name: string, email: string}> $to
     * @param list<array{name: string, email: string}> $cc
     * @param list<string> $references identifiants sans chevrons, du plus ancien au plus récent
     * @param list<ParsedAttachment> $attachments
     */
    public function __construct(
        public readonly string $messageId,
        public readonly string $inReplyTo,
        public readonly array $references,
        public readonly string $fromName,
        public readonly string $fromEmail,
        public readonly string $replyToEmail,
        public readonly array $to,
        public readonly array $cc,
        public readonly string $subject,
        public readonly string $sentAt,
        public readonly string $text,
        public readonly string $html,
        public readonly array $attachments,
        public readonly bool $autoReply = false,
    ) {}
}
