<?php

declare(strict_types=1);

namespace App\Services\ContactMail;

/**
 * Client IMAP minimal (LOGIN, SELECT, UID SEARCH, UID FETCH BODY.PEEK, UID STORE, APPEND, LOGOUT) : l'extension PHP imap
 * n'existe plus dans PHP 8.4. Chaque lecture a un délai d'attente, chaque ligne et chaque message une taille maximale.
 * Aucun message d'erreur ne reprend l'identifiant, le mot de passe ni le contenu d'un message.
 */
final class ImapClient
{
    public const MAX_LINE = 16384;

    /** Au-delà, le contenu d'un message n'est pas rapatrié (seuls ses en-têtes le sont). */
    public const DEFAULT_MAX_MESSAGE_BYTES = 20971520;

    private int $tagCounter = 0;

    private bool $greeted = false;

    public function __construct(
        private ImapConnection $connection,
        private int $maxMessageBytes = self::DEFAULT_MAX_MESSAGE_BYTES,
    ) {}

    /** @throws ImapException */
    public static function connect(ContactMailConfig $config): self
    {
        return new self(SocketImapConnection::open($config->imapHost(), $config->imapPort(), $config->timeout()));
    }

    /** @throws ImapException */
    public function login(string $user, string $password): void
    {
        $this->greet();
        $this->command(['LOGIN ', self::astring($user), ' ', self::astring($password)]);
    }

    /**
     * @return array{uidvalidity: int, uidnext: int, exists: int}
     * @throws ImapException
     */
    public function select(string $folder): array
    {
        $r = $this->command(['SELECT ', self::astring($folder)]);
        $info = ['uidvalidity' => 0, 'uidnext' => 0, 'exists' => 0];
        foreach ($r['untagged'] as $line) {
            if (preg_match('/\[UIDVALIDITY (\d+)\]/i', $line, $m) === 1) {
                $info['uidvalidity'] = (int) $m[1];
            } elseif (preg_match('/\[UIDNEXT (\d+)\]/i', $line, $m) === 1) {
                $info['uidnext'] = (int) $m[1];
            } elseif (preg_match('/^\* (\d+) EXISTS/i', $line, $m) === 1) {
                $info['exists'] = (int) $m[1];
            }
        }

        return $info;
    }

    /**
     * UID des messages dont l'UID est au moins $fromUid, du plus ancien au plus récent.
     *
     * @return list<int>
     * @throws ImapException
     */
    public function uidsFrom(int $fromUid): array
    {
        $fromUid = max(1, $fromUid);
        $r = $this->command(['UID SEARCH UID ' . $fromUid . ':*']);
        $uids = [];
        foreach ($r['untagged'] as $line) {
            if (preg_match('/^\* SEARCH ?(.*)$/i', trim($line), $m) === 1) {
                foreach (preg_split('/\s+/', trim($m[1]), -1, PREG_SPLIT_NO_EMPTY) ?: [] as $n) {
                    $uid = (int) $n;
                    // « n:* » renvoie toujours le dernier message, même plus ancien que n.
                    if ($uid >= $fromUid) {
                        $uids[$uid] = $uid;
                    }
                }
            }
        }
        sort($uids);

        return array_values($uids);
    }

    /**
     * Taille et indicateurs de messages.
     *
     * @param list<int> $uids
     * @return array<int, array{size: int, flags: list<string>}>
     * @throws ImapException
     */
    public function summaries(array $uids): array
    {
        $out = [];
        foreach (array_chunk($uids, 200) as $chunk) {
            $r = $this->command(['UID FETCH ' . implode(',', array_map('intval', $chunk)) . ' (UID FLAGS RFC822.SIZE)']);
            foreach ($r['untagged'] as $line) {
                $f = self::parseFetch($line);
                if ($f === null || !isset($f['UID'])) {
                    continue;
                }
                $out[(int) $f['UID']] = [
                    'size' => (int) ($f['RFC822.SIZE'] ?? 0),
                    'flags' => self::flagList($f['FLAGS'] ?? null),
                ];
            }
        }

        return $out;
    }

    /**
     * Message complet (BODY.PEEK : ne le marque pas lu), ou ses seuls en-têtes s'il dépasse la taille permise.
     *
     * @return array{raw: string, flags: list<string>, truncated: bool}|null null : message disparu
     * @throws ImapException
     */
    public function fetchMessage(int $uid, int $size): ?array
    {
        $truncated = $size > $this->maxMessageBytes;
        $section = $truncated ? 'BODY.PEEK[HEADER]' : 'BODY.PEEK[]';
        $r = $this->command(['UID FETCH ' . $uid . ' (UID FLAGS ' . $section . ')']);
        foreach ($r['untagged'] as $line) {
            $f = self::parseFetch($line);
            if ($f === null || (int) ($f['UID'] ?? 0) !== $uid) {
                continue;
            }
            $raw = $f['BODY[]'] ?? $f['BODY[HEADER]'] ?? null;

            return [
                'raw' => is_string($raw) ? $raw : '',
                'flags' => self::flagList($f['FLAGS'] ?? null),
                'truncated' => $truncated,
            ];
        }

        return null;
    }

    /**
     * @param list<string> $flags par exemple ['\\Seen'] ; $mode : '+FLAGS', '-FLAGS' ou 'FLAGS'
     * @throws ImapException
     */
    public function storeFlags(int $uid, string $mode, array $flags): void
    {
        if (!in_array($mode, ['+FLAGS', '-FLAGS', 'FLAGS'], true)) {
            throw new ImapException('Mode d’indicateurs IMAP inconnu.');
        }
        $safe = [];
        foreach ($flags as $flag) {
            if (preg_match('/^\\\\?[A-Za-z0-9$_\-]{1,40}$/', $flag) === 1) {
                $safe[] = $flag;
            }
        }
        $this->command(['UID STORE ' . $uid . ' ' . $mode . '.SILENT (' . implode(' ', $safe) . ')']);
    }

    /**
     * Dépose un message dans un dossier (copie dans « Envoyés »).
     *
     * @param list<string> $flags
     * @throws ImapException
     */
    public function append(string $folder, string $raw, array $flags = ['\\Seen']): void
    {
        $this->command(['APPEND ', self::astring($folder), ' (' . implode(' ', $flags) . ') ', new ImapLiteral($raw)]);
    }

    public function logout(): void
    {
        try {
            if ($this->greeted) {
                $this->command(['LOGOUT']);
            }
        } catch (\Throwable) {
            // Fermeture : rien à rattraper.
        }
        $this->connection->close();
    }

    /** @throws ImapException */
    private function greet(): void
    {
        if ($this->greeted) {
            return;
        }
        $line = $this->connection->readLine(self::MAX_LINE);
        if ($line === null || preg_match('/^\* (OK|PREAUTH)\b/i', $line) !== 1) {
            throw new ImapException('Le serveur IMAP n’a pas accueilli la connexion.');
        }
        $this->greeted = true;
    }

    /**
     * Envoie une commande (morceaux de texte et littéraux) et lit la réponse jusqu'à la ligne étiquetée.
     *
     * @param list<string|ImapLiteral> $pieces
     * @return array{status: string, untagged: list<string>}
     * @throws ImapException
     */
    private function command(array $pieces): array
    {
        $this->greet();
        $tag = 'A' . str_pad((string) ++$this->tagCounter, 4, '0', STR_PAD_LEFT);
        $this->connection->write($tag . ' ');
        foreach ($pieces as $piece) {
            if ($piece instanceof ImapLiteral) {
                $this->connection->write('{' . strlen($piece->data) . "}\r\n");
                $cont = $this->connection->readLine(self::MAX_LINE);
                if ($cont === null || !str_starts_with($cont, '+')) {
                    throw new ImapException('Le serveur IMAP a refusé la commande.');
                }
                $this->connection->write($piece->data);
            } else {
                $this->connection->write($piece);
            }
        }
        $this->connection->write("\r\n");

        $untagged = [];
        while (true) {
            $line = $this->connection->readLine(self::MAX_LINE);
            if ($line === null) {
                throw new ImapException('Connexion IMAP coupée.');
            }
            if (str_starts_with($line, $tag . ' ')) {
                $status = strtoupper(strtok(substr($line, strlen($tag) + 1), ' ') ?: '');
                if ($status !== 'OK') {
                    // Le texte du serveur n'est pas repris : il peut citer la commande.
                    throw new ImapException('Commande IMAP refusée (' . ($status !== '' ? $status : 'réponse vide') . ').');
                }

                return ['status' => $status, 'untagged' => $untagged];
            }
            if (str_starts_with($line, '*')) {
                $untagged[] = $this->readLiterals($line);
            }
        }
    }

    /** Complète une réponse non étiquetée avec ses littéraux {N} : le texte garde « {N}\r\n<octets> » tel quel. */
    private function readLiterals(string $line): string
    {
        $raw = $line;
        $guard = 0;
        while (preg_match('/\{(\d+)\}$/', $line, $m) === 1 && $guard++ < 64) {
            $n = (int) $m[1];
            if ($n > $this->maxMessageBytes + 1048576) {
                // Littéral hors limite : lu pour garder la session saine, mais jeté.
                $left = $n;
                while ($left > 0) {
                    $chunk = $this->connection->readBytes(min(65536, $left));
                    if ($chunk === '') {
                        throw new ImapException('Connexion IMAP coupée.');
                    }
                    $left -= strlen($chunk);
                }
                $data = '';
                $raw = substr($raw, 0, -strlen($m[0])) . '{0}';
            } else {
                $data = $this->connection->readBytes($n);
                if (strlen($data) !== $n) {
                    throw new ImapException('Connexion IMAP coupée.');
                }
            }
            $raw .= "\r\n" . $data;
            $next = $this->connection->readLine(self::MAX_LINE);
            if ($next === null) {
                throw new ImapException('Connexion IMAP coupée.');
            }
            $raw .= $next;
            $line = $next;
        }

        return $raw;
    }

    /** Argument IMAP : texte entre guillemets, ou littéral si le texte sort de l'ASCII imprimable. */
    private static function astring(string $value): string|ImapLiteral
    {
        if (preg_match('/^[\x20-\x7E]*$/', $value) === 1) {
            return '"' . str_replace(['\\', '"'], ['\\\\', '\\"'], $value) . '"';
        }
        if (str_contains($value, "\0")) {
            throw new ImapException('Caractère interdit dans un argument IMAP.');
        }

        return new ImapLiteral($value);
    }

    /**
     * « * 12 FETCH (UID 5 FLAGS (\Seen) BODY[] {N}… ) » en tableau clé => valeur (chaîne, liste ou null).
     *
     * @return array<string, mixed>|null
     */
    public static function parseFetch(string $raw): ?array
    {
        if (preg_match('/^\* \d+ FETCH \(/i', $raw, $m) !== 1) {
            return null;
        }
        $pos = strlen($m[0]);
        $len = strlen($raw);
        $out = [];
        while ($pos < $len) {
            while ($pos < $len && $raw[$pos] === ' ') {
                $pos++;
            }
            if ($pos >= $len || $raw[$pos] === ')') {
                break;
            }
            $key = strtoupper((string) self::readAtom($raw, $pos));
            $value = self::readValue($raw, $pos);
            if (str_starts_with($key, 'BODY[')) {
                $key = preg_replace('/<\d+>$/', '', $key) ?? $key;
            }
            $out[$key] = $value;
        }

        return $out;
    }

    private static function readAtom(string $raw, int &$pos): ?string
    {
        $len = strlen($raw);
        $start = $pos;
        while ($pos < $len) {
            $c = $raw[$pos];
            if ($c === '[') {
                $end = strpos($raw, ']', $pos);
                $pos = $end === false ? $len : $end + 1;
                continue;
            }
            if ($c === ' ' || $c === '(' || $c === ')') {
                break;
            }
            $pos++;
        }
        $atom = substr($raw, $start, $pos - $start);

        return $atom === '' ? null : $atom;
    }

    private static function readValue(string $raw, int &$pos): mixed
    {
        $len = strlen($raw);
        while ($pos < $len && $raw[$pos] === ' ') {
            $pos++;
        }
        if ($pos >= $len) {
            return null;
        }
        $c = $raw[$pos];
        if ($c === '(') {
            $pos++;
            $list = [];
            while ($pos < $len) {
                while ($pos < $len && $raw[$pos] === ' ') {
                    $pos++;
                }
                if ($pos >= $len) {
                    break;
                }
                if ($raw[$pos] === ')') {
                    $pos++;
                    break;
                }
                $list[] = self::readValue($raw, $pos);
            }

            return $list;
        }
        if ($c === '"') {
            $pos++;
            $s = '';
            while ($pos < $len && $raw[$pos] !== '"') {
                if ($raw[$pos] === '\\' && $pos + 1 < $len) {
                    $pos++;
                }
                $s .= $raw[$pos++];
            }
            $pos++;

            return $s;
        }
        if ($c === '{') {
            $end = strpos($raw, '}', $pos);
            if ($end === false) {
                $pos = $len;

                return null;
            }
            $n = (int) substr($raw, $pos + 1, $end - $pos - 1);
            $start = $end + 1;
            if (substr($raw, $start, 2) === "\r\n") {
                $start += 2;
            }
            $pos = $start + $n;

            return substr($raw, $start, $n);
        }
        $atom = self::readAtom($raw, $pos);

        return $atom === null || strcasecmp($atom, 'NIL') === 0 ? null : $atom;
    }

    /**
     * @return list<string>
     */
    private static function flagList(mixed $value): array
    {
        $out = [];
        foreach (is_array($value) ? $value : [] as $f) {
            if (is_string($f) && $f !== '') {
                $out[] = $f;
            }
        }

        return $out;
    }
}
