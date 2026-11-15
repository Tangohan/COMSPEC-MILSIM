<?php

declare(strict_types=1);

namespace App\Services\ContactMail;

/** Flux d'octets d'une session IMAP (socket TLS en production, serveur factice dans les essais). */
interface ImapConnection
{
    /** Une ligne sans son CRLF, ou null à la fin du flux. @throws ImapException délai dépassé */
    public function readLine(int $maxLength): ?string;

    /** Exactement $length octets (moins si le flux se ferme). @throws ImapException délai dépassé */
    public function readBytes(int $length): string;

    /** @throws ImapException */
    public function write(string $data): void;

    public function close(): void;
}
