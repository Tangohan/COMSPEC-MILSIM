<?php

declare(strict_types=1);

namespace App\Services\ContactMail;

/**
 * Valeurs de départ d'une réponse : destinataire (Reply-To s'il existe), objet « Re: … » sans empilement, corps avec la
 * signature puis la citation du message d'origine, et en-têtes de fil (In-Reply-To, References).
 */
final class ReplyComposer
{
    private const QUOTE_MAX_LINES = 40;

    private const QUOTE_MAX_CHARS = 4000;

    /**
     * @param array<string, mixed> $thread
     * @param array<string, mixed>|null $target message auquel on répond (le dernier message reçu du fil si null)
     * @return array{to: string, subject: string, body: string, in_reply_to: string, references: string}
     */
    public static function defaults(array $thread, ?array $target, ContactMailConfig $config): array
    {
        $own = strtolower($config->address());
        $to = '';
        $inReplyTo = '';
        $references = '';
        $quote = '';
        if ($target !== null) {
            $to = trim((string) ($target['reply_to_email'] ?? '')) !== '' ? (string) $target['reply_to_email'] : (string) ($target['from_email'] ?? '');
            if (strtolower($to) === $own) {
                $to = '';
            }
            if (($target['source'] ?? 'mail') === 'mail') {
                $inReplyTo = (string) ($target['message_id'] ?? '');
                $refs = preg_split('/\s+/', trim((string) ($target['refs'] ?? '')), -1, PREG_SPLIT_NO_EMPTY) ?: [];
                if ($inReplyTo !== '') {
                    $refs[] = $inReplyTo;
                }
                $references = implode(' ', array_slice(array_values(array_unique($refs)), -20));
            }
            $quote = self::quote($target);
        }
        if ($to === '') {
            $to = (string) ($thread['participant_email'] ?? '');
        }

        $sig = trim($config->signature());
        $body = "\n\n" . ($sig !== '' ? "-- \n" . $sig . "\n\n" : '') . $quote;

        return [
            'to' => $to,
            'subject' => MailHeaderGuard::replySubject((string) ($thread['subject'] ?? '')),
            'body' => $body,
            'in_reply_to' => $inReplyTo,
            'references' => $references,
        ];
    }

    /**
     * @param array<string, mixed> $message
     */
    public static function quote(array $message): string
    {
        $text = trim((string) ($message['body_text'] ?? ''));
        if ($text === '') {
            return '';
        }
        $lines = preg_split('/\r?\n/', mb_substr($text, 0, self::QUOTE_MAX_CHARS)) ?: [];
        $lines = array_slice($lines, 0, self::QUOTE_MAX_LINES);
        $who = trim((string) ($message['from_name'] ?? ''));
        $email = (string) ($message['from_email'] ?? '');
        $who = $who !== '' ? $who . ' <' . $email . '>' : $email;
        $ts = strtotime((string) ($message['sent_at'] ?? '') . ' UTC') ?: time();
        $head = 'Le ' . date('d/m/Y', $ts) . ' à ' . date('H:i', $ts) . ', ' . $who . ' a écrit :';

        return $head . "\n" . implode("\n", array_map(static fn (string $l): string => '> ' . $l, $lines)) . "\n";
    }

    /**
     * Valeurs de départ d'un nouveau message : seulement la signature.
     */
    public static function newMessageBody(ContactMailConfig $config): string
    {
        $sig = trim($config->signature());

        return $sig !== '' ? "\n\n-- \n" . $sig . "\n" : '';
    }
}
