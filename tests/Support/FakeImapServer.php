<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Services\ContactMail\ImapConnection;

/** Serveur IMAP en mémoire (LOGIN, SELECT, UID SEARCH, UID FETCH, UID STORE, APPEND, LOGOUT) : aucun réseau. */
final class FakeImapServer implements ImapConnection
{
    /** @var array<int, array{raw: string, flags: list<string>}> */
    public array $messages = [];

    /** @var list<string> commandes reçues, sans les mots de passe */
    public array $log = [];

    /** @var list<array{folder: string, raw: string}> */
    public array $appended = [];

    /** @var array<int, string> */
    public array $stored = [];

    public bool $closed = false;

    public int $uidValidity = 42;

    private string $out = "* OK IMAP ready\r\n";

    private string $in = '';

    private ?int $pendingLiteral = null;

    private string $pendingCommand = '';

    public function __construct(private string $user = 'contact@example.test', private string $password = 'secret') {}

    public function readLine(int $maxLength): ?string
    {
        $pos = strpos($this->out, "\r\n");
        if ($pos === false) {
            return null;
        }
        $line = substr($this->out, 0, $pos);
        $this->out = substr($this->out, $pos + 2);

        return $line;
    }

    public function readBytes(int $length): string
    {
        $chunk = substr($this->out, 0, $length);
        $this->out = substr($this->out, strlen($chunk));

        return $chunk;
    }

    public function write(string $data): void
    {
        $this->in .= $data;
        while (true) {
            if ($this->pendingLiteral !== null) {
                if (strlen($this->in) < $this->pendingLiteral + 2) {
                    return;
                }
                $literal = substr($this->in, 0, $this->pendingLiteral);
                $this->in = substr($this->in, $this->pendingLiteral + 2);
                $this->pendingLiteral = null;
                $this->finishAppend($this->pendingCommand, $literal);
                continue;
            }
            $pos = strpos($this->in, "\r\n");
            if ($pos === false) {
                return;
            }
            $line = substr($this->in, 0, $pos);
            $this->in = substr($this->in, $pos + 2);
            $this->handle($line);
        }
    }

    public function close(): void
    {
        $this->closed = true;
        // Une nouvelle session sur le même serveur repart d'un message d'accueil.
        $this->out = "* OK IMAP ready
";
        $this->in = '';
        $this->pendingLiteral = null;
    }

    private function handle(string $line): void
    {
        [$tag, $rest] = explode(' ', $line, 2) + ['', ''];
        if (preg_match('/^LOGIN /i', $rest) === 1) {
            $this->log[] = 'LOGIN ***';
            $ok = $rest === 'LOGIN "' . $this->user . '" "' . str_replace(['\\', '"'], ['\\\\', '\\"'], $this->password) . '"';
            $this->out .= $ok ? "$tag OK LOGIN done\r\n" : "$tag NO [AUTHENTICATIONFAILED] invalid credentials\r\n";

            return;
        }
        $this->log[] = $rest;
        if (preg_match('/^SELECT /i', $rest) === 1) {
            $n = count($this->messages);
            $next = $this->messages === [] ? 1 : max(array_keys($this->messages)) + 1;
            $this->out .= "* $n EXISTS\r\n* OK [UIDVALIDITY {$this->uidValidity}] UIDs valid\r\n* OK [UIDNEXT $next] next\r\n$tag OK [READ-WRITE] done\r\n";
        } elseif (preg_match('/^UID SEARCH UID (\d+):\*$/i', $rest, $m) === 1) {
            $uids = array_keys($this->messages);
            $hit = array_values(array_filter($uids, static fn (int $u): bool => $u >= (int) $m[1]));
            if ($hit === [] && $uids !== []) {
                $hit = [max($uids)];
            }
            $this->out .= '* SEARCH' . ($hit === [] ? '' : ' ' . implode(' ', $hit)) . "\r\n$tag OK SEARCH done\r\n";
        } elseif (preg_match('/^UID FETCH ([\d,]+) \(UID FLAGS RFC822\.SIZE\)$/i', $rest, $m) === 1) {
            foreach (explode(',', $m[1]) as $uid) {
                $msg = $this->messages[(int) $uid] ?? null;
                if ($msg !== null) {
                    $this->out .= '* ' . (int) $uid . ' FETCH (UID ' . (int) $uid . ' FLAGS (' . implode(' ', $msg['flags']) . ') RFC822.SIZE ' . strlen($msg['raw']) . ")\r\n";
                }
            }
            $this->out .= "$tag OK FETCH done\r\n";
        } elseif (preg_match('/^UID FETCH (\d+) \(UID FLAGS (BODY\.PEEK\[(HEADER)?\])\)$/i', $rest, $m) === 1) {
            $uid = (int) $m[1];
            $msg = $this->messages[$uid] ?? null;
            if ($msg !== null) {
                $raw = $msg['raw'];
                $section = 'BODY[]';
                if (($m[3] ?? '') !== '') {
                    $section = 'BODY[HEADER]';
                    $raw = (string) strstr($raw, "\r\n\r\n", true) . "\r\n\r\n";
                }
                $this->out .= "* $uid FETCH (UID $uid FLAGS (" . implode(' ', $msg['flags']) . ") $section {" . strlen($raw) . "}\r\n" . $raw . ")\r\n";
            }
            $this->out .= "$tag OK FETCH done\r\n";
        } elseif (preg_match('/^UID STORE (\d+) ([+-]?FLAGS)\.SILENT \((.*)\)$/i', $rest, $m) === 1) {
            $this->stored[(int) $m[1]] = $m[2] . ' ' . $m[3];
            $this->out .= "$tag OK STORE done\r\n";
        } elseif (preg_match('/^APPEND "([^"]*)" \([^)]*\) \{(\d+)\}$/i', $rest, $m) === 1) {
            $this->pendingLiteral = (int) $m[2];
            $this->pendingCommand = $tag . ' ' . $m[1];
            $this->out .= "+ Ready for literal data\r\n";
        } elseif (preg_match('/^LOGOUT$/i', $rest) === 1) {
            $this->out .= "* BYE bye\r\n$tag OK LOGOUT done\r\n";
        } else {
            $this->out .= "$tag BAD unknown command\r\n";
        }
    }

    private function finishAppend(string $tagAndFolder, string $literal): void
    {
        [$tag, $folder] = explode(' ', $tagAndFolder, 2);
        $this->appended[] = ['folder' => $folder, 'raw' => $literal];
        $this->out .= "$tag OK APPEND done\r\n";
    }
}
