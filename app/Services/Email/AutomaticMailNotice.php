<?php

declare(strict_types=1);

namespace App\Services\Email;

use App\Services\ContactMail\ContactMailConfig;

/**
 * Pied de message des e-mails automatiques de la plateforme (expéditeur « ne pas répondre ») : une phrase, en français puis
 * en anglais, qui dit où écrire. Les réponses arrivent dans la boîte de contact (en-tête Reply-To) sauf si
 * MAIL_REPLY_TO_MODE=none. Les messages envoyés avec le compte d'une communauté n'y passent pas.
 */
final class AutomaticMailNotice
{
    public const MARKER = 'data-athena-auto-notice';

    /**
     * @return array{html: string, text: string}
     */
    public static function apply(string $html, string $text, ContactMailConfig $config): array
    {
        if (str_contains($html, self::MARKER) || str_contains($text, "\n-- \nCe message est automatique")) {
            return ['html' => $html, 'text' => $text];
        }
        $lines = self::lines($config->autoReplyTo());
        if ($html !== '') {
            $p = '<p ' . self::MARKER . '="1" style="margin:24px 0 0;padding-top:12px;border-top:1px solid #e3e8e6;color:#6b7771;font-size:12px;line-height:1.5;">'
                . htmlspecialchars($lines[0], ENT_QUOTES, 'UTF-8') . '<br>'
                . '<span lang="en">' . htmlspecialchars($lines[1], ENT_QUOTES, 'UTF-8') . '</span></p>';
            $pos = strripos($html, '</body>');
            $html = $pos === false ? $html . $p : substr($html, 0, $pos) . $p . substr($html, $pos);
        }
        if ($text !== '') {
            $text = rtrim($text) . "\n\n-- \n" . $lines[0] . "\n" . $lines[1] . "\n";
        }

        return ['html' => $html, 'text' => $text];
    }

    /**
     * @return array{0: string, 1: string} phrase française, phrase anglaise
     */
    public static function lines(?string $replyTo): array
    {
        if ($replyTo === null) {
            return [
                'Ce message est automatique : merci de ne pas y répondre.',
                'This message is automatic: please do not reply.',
            ];
        }

        return [
            'Ce message est automatique. Pour nous écrire, répondez à ' . $replyTo . ' ou utilisez votre espace Athena.',
            'This message is automatic. To reach us, reply to ' . $replyTo . ' or use your Athena space.',
        ];
    }
}
