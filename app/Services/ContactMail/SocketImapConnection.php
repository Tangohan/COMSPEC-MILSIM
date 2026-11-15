<?php

declare(strict_types=1);

namespace App\Services\ContactMail;

/** Connexion IMAP chiffrée (TLS implicite, port 993), certificat vérifié, délai d'attente sur chaque lecture. */
final class SocketImapConnection implements ImapConnection
{
    /** @var resource|null */
    private $stream;

    /** @param resource $stream */
    private function __construct($stream, private int $timeout)
    {
        $this->stream = $stream;
        stream_set_timeout($stream, $timeout);
    }

    /** @throws ImapException */
    public static function open(string $host, int $port, int $timeout): self
    {
        if ($host === '' || $port <= 0 || preg_match('/[^A-Za-z0-9.\-:\[\]]/', $host) === 1) {
            throw new ImapException('Serveur IMAP invalide.');
        }
        $context = stream_context_create(['ssl' => [
            'verify_peer' => true,
            'verify_peer_name' => true,
            'peer_name' => $host,
            'SNI_enabled' => true,
        ]]);
        $errno = 0;
        $errstr = '';
        $stream = @stream_socket_client('ssl://' . $host . ':' . $port, $errno, $errstr, $timeout, STREAM_CLIENT_CONNECT, $context);
        if ($stream === false) {
            throw new ImapException('Connexion IMAP impossible (' . ($errno > 0 ? 'erreur ' . $errno : 'réseau') . ').');
        }

        return new self($stream, $timeout);
    }

    public function readLine(int $maxLength): ?string
    {
        if ($this->stream === null) {
            return null;
        }
        $line = fgets($this->stream, $maxLength + 1);
        if ($line === false) {
            $this->failIfTimedOut();

            return null;
        }

        return rtrim($line, "\r\n");
    }

    public function readBytes(int $length): string
    {
        $out = '';
        while ($this->stream !== null && $length > 0) {
            $chunk = fread($this->stream, min(65536, $length));
            if ($chunk === false || $chunk === '') {
                $this->failIfTimedOut();
                break;
            }
            $out .= $chunk;
            $length -= strlen($chunk);
        }

        return $out;
    }

    public function write(string $data): void
    {
        if ($this->stream === null) {
            throw new ImapException('Connexion IMAP fermée.');
        }
        $len = strlen($data);
        $sent = 0;
        while ($sent < $len) {
            $n = @fwrite($this->stream, substr($data, $sent));
            if ($n === false || $n === 0) {
                throw new ImapException('Envoi vers le serveur IMAP interrompu.');
            }
            $sent += $n;
        }
    }

    public function close(): void
    {
        if ($this->stream !== null) {
            @fclose($this->stream);
            $this->stream = null;
        }
    }

    private function failIfTimedOut(): void
    {
        if ($this->stream !== null) {
            $meta = stream_get_meta_data($this->stream);
            if (!empty($meta['timed_out'])) {
                throw new ImapException('Le serveur IMAP ne répond plus (délai de ' . $this->timeout . ' s dépassé).');
            }
        }
    }
}
