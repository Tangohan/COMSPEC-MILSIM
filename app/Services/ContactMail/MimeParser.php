<?php

declare(strict_types=1);

namespace App\Services\ContactMail;

/**
 * Lecture d'un e-mail brut (RFC 5322 / MIME) : en-têtes encodés (RFC 2047), paramètres (RFC 2231), multipart imbriqué,
 * base64 et quoted-printable, jeux de caractères convertis en UTF-8, pièces jointes, identifiants de fil de discussion.
 * Les limites (profondeur, nombre de parties, taille du texte et des pièces) bornent le travail sur un message hostile.
 */
final class MimeParser
{
    public const MAX_ATTACHMENT_BYTES = 15728640;

    private const MAX_PARTS = 300;
    private const MAX_DEPTH = 10;
    private const MAX_ATTACHMENTS = 40;
    private const MAX_TEXT = 1048576;
    private const MAX_HTML = 2097152;

    /** @var array{parts: int, text: string, html: string, attachments: list<ParsedAttachment>} */
    private array $state = ['parts' => 0, 'text' => '', 'html' => '', 'attachments' => []];

    public static function parse(string $raw, ?string $fallbackDate = null): ParsedMessage
    {
        return (new self())->parseMessage($raw, $fallbackDate);
    }

    public function parseMessage(string $raw, ?string $fallbackDate = null): ParsedMessage
    {
        $this->state = ['parts' => 0, 'text' => '', 'html' => '', 'attachments' => []];
        $raw = str_replace("\r\n", "\n", $raw);
        [$headers, $body] = self::splitPart($raw);

        $this->walk($headers, $body, 0, '');

        $text = $this->state['text'];
        $html = $this->state['html'];
        if ($text === '' && $html !== '') {
            $text = self::htmlToText($html);
        }

        $from = self::parseAddresses(self::first($headers, 'from'));
        $replyTo = self::parseAddresses(self::first($headers, 'reply-to'));
        $refs = [];
        if (preg_match_all('/<([^<>\s]{1,250})>/', self::first($headers, 'references'), $m) > 0) {
            $refs = array_slice(array_values(array_unique($m[1])), -30);
        }
        $messageId = preg_match('/<([^<>\s]{1,250})>/', self::first($headers, 'message-id'), $m) === 1 ? $m[1] : '';
        $inReplyTo = preg_match('/<([^<>\s]{1,250})>/', self::first($headers, 'in-reply-to'), $m) === 1 ? $m[1] : '';

        $subject = trim(self::decodeHeader(self::first($headers, 'subject')));

        return new ParsedMessage(
            $messageId,
            $inReplyTo,
            $refs,
            $from[0]['name'] ?? '',
            $from[0]['email'] ?? '',
            $replyTo[0]['email'] ?? '',
            self::parseAddresses(self::all($headers, 'to')),
            self::parseAddresses(self::all($headers, 'cc')),
            mb_substr($subject, 0, 250),
            self::parseDate(self::first($headers, 'date'), $fallbackDate),
            $text,
            $html,
            $this->state['attachments'],
            self::looksAutomatic($headers, $from[0]['email'] ?? ''),
        );
    }

    /**
     * En-têtes (nom en minuscules => valeurs dépliées) et corps d'une partie.
     *
     * @return array{0: array<string, list<string>>, 1: string}
     */
    public static function splitPart(string $part): array
    {
        $pos = strpos($part, "\n\n");
        if (str_starts_with($part, "\n")) {
            return [[], substr($part, 1)];
        }
        if ($pos === false) {
            // Que des en-têtes (ou rien) : pas de corps.
            $headerBlock = $part;
            $body = '';
        } else {
            $headerBlock = substr($part, 0, $pos);
            $body = substr($part, $pos + 2);
        }
        $headers = [];
        $current = null;
        foreach (explode("\n", $headerBlock) as $line) {
            if ($line === '') {
                continue;
            }
            if (($line[0] === ' ' || $line[0] === "\t") && $current !== null) {
                $last = count($headers[$current]) - 1;
                $headers[$current][$last] .= ' ' . trim($line);
                continue;
            }
            $colon = strpos($line, ':');
            if ($colon === false || $colon === 0) {
                $current = null;
                continue;
            }
            $current = strtolower(trim(substr($line, 0, $colon)));
            $headers[$current][] = trim(substr($line, $colon + 1));
        }

        return [$headers, $body];
    }

    /**
     * @param array<string, list<string>> $headers
     */
    private function walk(array $headers, string $body, int $depth, string $parentSubtype): void
    {
        if (++$this->state['parts'] > self::MAX_PARTS || $depth > self::MAX_DEPTH) {
            return;
        }
        $ct = self::parseParams(self::first($headers, 'content-type'));
        $type = strtolower($ct['value']) !== '' ? strtolower($ct['value']) : 'text/plain';
        if (preg_match('~^[a-z0-9.+\-]+/[a-z0-9.+\-]+$~', $type) !== 1) {
            $type = 'application/octet-stream';
        }
        [$major, $subtype] = explode('/', $type, 2);

        if ($major === 'multipart') {
            $boundary = $ct['params']['boundary'] ?? '';
            if ($boundary === '') {
                return;
            }
            foreach (self::splitMultipart($body, $boundary) as $child) {
                [$h, $b] = self::splitPart($child);
                $this->walk($h, $b, $depth + 1, $subtype);
            }

            return;
        }

        $disposition = self::parseParams(self::first($headers, 'content-disposition'));
        $dispValue = strtolower($disposition['value']);
        $filename = self::filename($disposition['params'], $ct['params']);
        $content = self::decodeTransfer($body, strtolower(trim(self::first($headers, 'content-transfer-encoding'))));

        $isBody = ($type === 'text/plain' || $type === 'text/html') && $dispValue !== 'attachment' && $filename === '';
        if ($isBody) {
            $text = self::toUtf8($content, $ct['params']['charset'] ?? '');
            $this->addBody($type === 'text/html', $text, $parentSubtype);

            return;
        }

        if (count($this->state['attachments']) >= self::MAX_ATTACHMENTS) {
            return;
        }
        $contentId = preg_match('/<([^<>\s]{1,200})>/', self::first($headers, 'content-id'), $m) === 1 ? $m[1] : '';
        if ($type === 'message/rfc822' && $filename === '') {
            $filename = 'message.eml';
        }
        $n = count($this->state['attachments']) + 1;
        $filename = self::cleanFilename($filename, $type, $n);
        $size = strlen($content);
        $this->state['attachments'][] = new ParsedAttachment(
            $filename,
            $type,
            $size <= self::MAX_ATTACHMENT_BYTES ? $content : null,
            $size,
            $contentId,
            $dispValue === 'inline' || ($contentId !== '' && $dispValue !== 'attachment'),
        );
    }

    private function addBody(bool $html, string $text, string $parentSubtype): void
    {
        $key = $html ? 'html' : 'text';
        $max = $html ? self::MAX_HTML : self::MAX_TEXT;
        $text = str_replace("\0", '', $text);
        if ($this->state[$key] !== '' && $parentSubtype === 'alternative') {
            return;
        }
        $joined = $this->state[$key] === '' ? $text : $this->state[$key] . ($html ? "\n" : "\n\n") . $text;
        $this->state[$key] = strlen($joined) > $max ? mb_strcut($joined, 0, $max, 'UTF-8') : $joined;
    }

    /**
     * @return list<string>
     */
    public static function splitMultipart(string $body, string $boundary): array
    {
        $delim = '--' . $boundary;
        $parts = [];
        $cur = null;
        foreach (explode("\n", $body) as $line) {
            $t = rtrim($line, " \t\r");
            if ($t === $delim) {
                if ($cur !== null) {
                    $parts[] = implode("\n", $cur);
                }
                $cur = [];
                continue;
            }
            if ($t === $delim . '--') {
                if ($cur !== null) {
                    $parts[] = implode("\n", $cur);
                }
                $cur = null;
                break;
            }
            if ($cur !== null) {
                $cur[] = $line;
            }
        }
        if ($cur !== null) {
            $parts[] = implode("\n", $cur);
        }

        return $parts;
    }

    private static function decodeTransfer(string $body, string $encoding): string
    {
        return match ($encoding) {
            'base64' => (string) base64_decode(preg_replace('/[^A-Za-z0-9+\/=]/', '', $body) ?? '', false),
            'quoted-printable' => quoted_printable_decode($body),
            default => $body,
        };
    }

    /** Valeur « text/html » et paramètres d'un en-tête structuré (Content-Type, Content-Disposition). RFC 2231 résolu. */
    public static function parseParams(string $header): array
    {
        $len = strlen($header);
        $i = 0;
        $value = '';
        while ($i < $len && $header[$i] !== ';') {
            $value .= $header[$i++];
        }
        $raw = [];
        while ($i < $len) {
            $i++; // ';'
            while ($i < $len && ($header[$i] === ' ' || $header[$i] === "\t")) {
                $i++;
            }
            $name = '';
            while ($i < $len && $header[$i] !== '=' && $header[$i] !== ';') {
                $name .= $header[$i++];
            }
            if ($i >= $len || $header[$i] === ';') {
                continue;
            }
            $i++; // '='
            $val = '';
            if ($i < $len && $header[$i] === '"') {
                $i++;
                while ($i < $len && $header[$i] !== '"') {
                    if ($header[$i] === '\\' && $i + 1 < $len) {
                        $i++;
                    }
                    $val .= $header[$i++];
                }
                $i++;
                while ($i < $len && $header[$i] !== ';') {
                    $i++;
                }
            } else {
                while ($i < $len && $header[$i] !== ';') {
                    $val .= $header[$i++];
                }
                $val = trim($val);
            }
            $name = strtolower(trim($name));
            if ($name !== '') {
                $raw[$name] = $val;
            }
        }

        $params = [];
        $bases = [];
        foreach ($raw as $name => $_) {
            $bases[preg_replace('/\*\d*\*?$/', '', $name) ?? $name] = true;
        }
        foreach (array_keys($bases) as $base) {
            if (isset($raw[$base . '*'])) {
                $params[$base] = self::decodeExtValue($raw[$base . '*']);
                continue;
            }
            if (isset($raw[$base . '*0*']) || isset($raw[$base . '*0'])) {
                $joined = '';
                $charset = '';
                for ($n = 0; $n < 50; $n++) {
                    if (isset($raw[$base . '*' . $n . '*'])) {
                        $piece = $raw[$base . '*' . $n . '*'];
                        if ($n === 0 && preg_match("/^([^']*)'[^']*'(.*)$/s", $piece, $m) === 1) {
                            $charset = $m[1];
                            $piece = $m[2];
                        }
                        $joined .= rawurldecode($piece);
                    } elseif (isset($raw[$base . '*' . $n])) {
                        $joined .= $raw[$base . '*' . $n];
                    } else {
                        break;
                    }
                }
                $params[$base] = self::toUtf8($joined, $charset !== '' ? $charset : 'utf-8');
                continue;
            }
            if (isset($raw[$base])) {
                $params[$base] = self::decodeHeader($raw[$base]);
            }
        }

        return ['value' => trim($value), 'params' => $params];
    }

    private static function decodeExtValue(string $v): string
    {
        if (preg_match("/^([^']*)'[^']*'(.*)$/s", $v, $m) === 1) {
            return self::toUtf8(rawurldecode($m[2]), $m[1] !== '' ? $m[1] : 'utf-8');
        }

        return self::toUtf8(rawurldecode($v), 'utf-8');
    }

    /**
     * @param array<string, string> $dispParams
     * @param array<string, string> $typeParams
     */
    private static function filename(array $dispParams, array $typeParams): string
    {
        return trim($dispParams['filename'] ?? $typeParams['name'] ?? '');
    }

    public static function cleanFilename(string $name, string $mime, int $n = 1): string
    {
        $name = str_replace('\\', '/', $name);
        $name = basename($name);
        $name = preg_replace('/[\x00-\x1F\x7F]+/u', '', $name) ?? '';
        $name = trim(preg_replace('/[<>:"|?*]+/', '_', $name) ?? '', " .\t");
        if (mb_strlen($name) > 150) {
            $name = mb_substr($name, 0, 150);
        }
        if ($name === '') {
            $ext = match ($mime) {
                'application/pdf' => '.pdf',
                'image/png' => '.png',
                'image/jpeg' => '.jpg',
                'text/plain' => '.txt',
                'message/rfc822' => '.eml',
                default => '',
            };
            $name = 'piece-jointe-' . $n . $ext;
        }

        return $name;
    }

    /** Mots encodés RFC 2047 (=?UTF-8?B?…?=, =?ISO-8859-1?Q?…?=) décodés ; retours à la ligne retirés. */
    public static function decodeHeader(string $value): string
    {
        if ($value === '') {
            return '';
        }
        if (str_contains($value, '=?')) {
            // Deux mots encodés séparés seulement par des blancs se recollent.
            $value = preg_replace('/(=\?[^?\s]+\?[bBqQ]\?[^?\s]*\?=)\s+(?==\?[^?\s]+\?[bBqQ]\?)/', '$1', $value) ?? $value;
            $value = preg_replace_callback(
                '/=\?([^?\s]+)\?([bBqQ])\?([^?\s]*)\?=/',
                static function (array $m): string {
                    $charset = (string) preg_replace('/\*.*$/', '', $m[1]);
                    $data = strtolower($m[2]) === 'b'
                        ? (string) base64_decode($m[3], false)
                        : quoted_printable_decode(str_replace('_', ' ', $m[3]));

                    return self::toUtf8($data, $charset);
                },
                $value
            ) ?? $value;
        }
        $value = self::toUtf8($value, 'utf-8');

        return trim(preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $value) ?? '');
    }

    /**
     * Liste d'adresses d'un en-tête (From, To, Cc, Reply-To) : nom décodé et adresse validée en minuscules.
     *
     * @return list<array{name: string, email: string}>
     */
    public static function parseAddresses(string $header): array
    {
        $out = [];
        foreach (self::splitAddressList($header) as $chunk) {
            $name = '';
            if (preg_match('/<([^<>]*)>/', $chunk, $m) === 1) {
                $email = trim($m[1]);
                $name = trim((string) preg_replace('/<[^<>]*>/', '', $chunk));
            } else {
                $email = trim((string) preg_replace('/\([^)]*\)/', '', $chunk));
                if (preg_match('/\(([^)]*)\)/', $chunk, $c) === 1) {
                    $name = $c[1];
                }
            }
            // Groupe « Equipe: a@b, c@d; » : le nom du groupe n'est pas une adresse.
            $email = trim((string) preg_replace('/^[^<@]*:/', '', $email), " \t;");
            $email = strtolower(trim($email, " \t\"'"));
            if (ContactMailConfig::isValidAddress($email)) {
                $name = trim(self::decodeHeader(trim($name, " \t\"'")));
                $out[] = ['name' => mb_substr($name, 0, 120), 'email' => $email];
            }
        }

        return $out;
    }

    /** @return list<string> */
    private static function splitAddressList(string $header): array
    {
        $out = [];
        $cur = '';
        $quote = false;
        $angle = false;
        $paren = 0;
        $len = strlen($header);
        for ($i = 0; $i < $len; $i++) {
            $c = $header[$i];
            if ($c === '\\' && $quote && $i + 1 < $len) {
                $cur .= $c . $header[++$i];
                continue;
            }
            if ($c === '"' && $paren === 0) {
                $quote = !$quote;
            } elseif (!$quote && $c === '(') {
                $paren++;
            } elseif (!$quote && $c === ')' && $paren > 0) {
                $paren--;
            } elseif (!$quote && $paren === 0 && $c === '<') {
                $angle = true;
            } elseif (!$quote && $paren === 0 && $c === '>') {
                $angle = false;
            }
            if (($c === ',' || $c === ';') && !$quote && !$angle && $paren === 0) {
                if (trim($cur) !== '') {
                    $out[] = $cur;
                }
                $cur = '';
                continue;
            }
            $cur .= $c;
        }
        if (trim($cur) !== '') {
            $out[] = $cur;
        }

        return array_slice($out, 0, 100);
    }

    /** Texte converti en UTF-8 depuis le jeu de caractères annoncé ; octets invalides remplacés plutôt que de lever. */
    public static function toUtf8(string $data, string $charset): string
    {
        $cs = strtolower(trim($charset, " \t\"'"));
        $isUtf8 = $cs === '' || $cs === 'utf-8' || $cs === 'utf8' || $cs === 'us-ascii' || $cs === 'ascii';
        if ($isUtf8) {
            if (mb_check_encoding($data, 'UTF-8')) {
                return $data;
            }
            // Jeu annoncé faux (en-tête ou corps 8 bits sans déclaration) : le plus courant est Windows-1252.
            return self::convert($data, 'Windows-1252') ?? mb_convert_encoding($data, 'UTF-8', 'UTF-8');
        }
        $alias = [
            'latin1' => 'Windows-1252', 'iso-8859-1' => 'Windows-1252', 'iso8859-1' => 'Windows-1252', 'cp1252' => 'Windows-1252',
            'gb2312' => 'GB18030', 'gbk' => 'GB18030', 'ks_c_5601-1987' => 'EUC-KR', 'x-sjis' => 'SJIS', 'shift_jis' => 'SJIS',
            'unicode-1-1-utf-7' => 'UTF-7',
        ];
        $target = $alias[$cs] ?? $cs;
        if ($target === 'UTF-7') {
            return mb_convert_encoding($data, 'UTF-8', 'UTF-8');
        }

        return self::convert($data, $target) ?? mb_convert_encoding($data, 'UTF-8', 'UTF-8');
    }

    private static function convert(string $data, string $from): ?string
    {
        try {
            return mb_convert_encoding($data, 'UTF-8', $from);
        } catch (\ValueError) {
            // Jeu inconnu de mbstring : iconv, sinon l'appelant remplace les octets invalides.
        }
        if (function_exists('iconv')) {
            $r = @iconv($from, 'UTF-8//IGNORE', $data);
            if (is_string($r)) {
                return $r;
            }
        }

        return null;
    }

    /**
     * @param array<string, list<string>> $headers
     */
    private static function first(array $headers, string $name): string
    {
        return $headers[$name][0] ?? '';
    }

    /**
     * @param array<string, list<string>> $headers
     */
    private static function all(array $headers, string $name): string
    {
        return implode(', ', $headers[$name] ?? []);
    }

    private static function parseDate(string $header, ?string $fallback): string
    {
        $now = time();
        $ts = $header !== '' ? strtotime($header) : false;
        if ($ts === false || $ts > $now + 86400 || $ts < 0) {
            $ts = $fallback !== null ? (strtotime($fallback . ' UTC') ?: $now) : $now;
        }

        return gmdate('Y-m-d H:i:s', $ts);
    }

    /**
     * @param array<string, list<string>> $headers
     */
    private static function looksAutomatic(array $headers, string $from): bool
    {
        $auto = strtolower(self::first($headers, 'auto-submitted'));
        if ($auto !== '' && $auto !== 'no') {
            return true;
        }
        if (preg_match('/^(bulk|junk|auto_reply|list)$/', strtolower(self::first($headers, 'precedence'))) === 1) {
            return true;
        }
        if (isset($headers['x-auto-response-suppress']) || isset($headers['list-unsubscribe'])) {
            return true;
        }

        return preg_match('/^(mailer-daemon|postmaster|no-?reply|do-?not-?reply)@/', $from) === 1;
    }

    /** Version texte d'un message HTML : blocs en lignes, balises et entités retirées. */
    public static function htmlToText(string $html): string
    {
        $html = preg_replace('~<(script|style|head|title)\b[^>]*>.*?</\1\s*>~is', '', $html) ?? $html;
        $html = preg_replace('~<(br|/p|/div|/tr|/h[1-6]|/li|/blockquote)\b[^>]*>~i', "\n", $html) ?? $html;
        $html = preg_replace('~<li\b[^>]*>~i', "\n- ", $html) ?? $html;
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace("/[ \t]+/", ' ', $text) ?? $text;
        $text = preg_replace("/ ?\n ?/", "\n", $text) ?? $text;

        return trim(preg_replace("/\n{3,}/", "\n\n", $text) ?? $text);
    }
}
