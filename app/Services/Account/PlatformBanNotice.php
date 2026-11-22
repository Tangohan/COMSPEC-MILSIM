<?php

declare(strict_types=1);

namespace App\Services\Account;

use DateTimeImmutable;
use DateTimeZone;

final class PlatformBanNotice
{
    public const MODELS = [
        'conduite' => [
            'rule' => 'CGU Athena · Règles de conduite',
            'text' => 'Nous avons restreint votre accès à Athena après examen d’un incident lié aux règles de conduite. Si un élément de contexte a été omis ou si vous pensez que la décision repose sur une erreur, vous pouvez demander un réexamen.',
        ],
        'contenus' => [
            'rule' => 'CGU Athena · Contenus utilisateurs et modération',
            'text' => 'Après examen de contenus associés à votre compte, l’équipe de modération a décidé de restreindre votre accès. Vous pouvez apporter des précisions sur les contenus ou leur contexte dans votre contestation.',
        ],
        'securite' => [
            'rule' => 'CGU Athena · Compte utilisateur et suspension',
            'text' => 'Votre accès a été restreint à la suite d’un incident de sécurité. Si vous pensez que votre compte a été utilisé à votre insu, indiquez ce que vous avez constaté dans votre demande de réexamen. Ne communiquez jamais votre mot de passe.',
        ],
    ];

    public const DEFAULT_TEXT = 'L’administration d’Athena a restreint l’accès à votre compte. Vous pouvez demander un réexamen de la décision et fournir les éléments de contexte qui vous semblent utiles.';

    public readonly string $rule;
    public readonly string $message;

    public function __construct(string $rule, string $message, public readonly bool $appealClosed = false)
    {
        $this->rule = mb_substr(trim($rule), 0, 160);
        $this->message = mb_substr(trim($message), 0, 2000);
    }

    public function isEmpty(): bool
    {
        return $this->rule === '' && $this->message === '' && !$this->appealClosed;
    }

    /** @return array{id:int, rule:string, message:string, end:string, appeal_closed:bool} */
    public static function publicDetails(array $ban, string $locale = 'fr'): array
    {
        $rule = trim((string) ($ban['rule_reference'] ?? ''));
        $appealClosed = !empty($ban['appeal_closed']);
        if ($locale === 'en') {
            foreach (self::MODELS as $key => $model) {
                if ($rule === $model['rule']) {
                    $rule = \__('moderation.preset_' . $key . '_rule', [], $locale);
                    break;
                }
            }
        }

        return [
            'id' => (int) $ban['id'],
            'rule' => $rule,
            'message' => self::displayMessage((string) ($ban['public_reason'] ?? ''), $appealClosed, $locale),
            'end' => self::endLabel($ban['ends_at'] ?? null, $locale),
            'appeal_closed' => $appealClosed,
        ];
    }

    public static function displayMessage(string $message, bool $appealClosed, string $locale = 'fr'): string
    {
        $message = trim($message) ?: self::DEFAULT_TEXT;
        $key = $message === self::DEFAULT_TEXT ? 'moderation.default_reason' : null;
        foreach (self::MODELS as $modelKey => $model) {
            if ($message === $model['text']) {
                $key = 'moderation.preset_' . $modelKey . '_reason';
                break;
            }
        }
        if ($key === null) {
            return $message;
        }
        if ($locale === 'en') {
            $message = \__($key, [], $locale);
        }
        if ($appealClosed) {
            // Les modèles commencent par le motif. Les phrases suivantes invitent à contester.
            [$message] = explode('. ', $message, 2);

            return rtrim($message, '.') . '.';
        }

        return $message;
    }

    public static function endLabel(?string $endsAt, string $locale = 'fr'): string
    {
        if ($endsAt === null || $endsAt === '') {
            return \__('moderation.no_end', [], $locale);
        }

        $date = (new DateTimeImmutable($endsAt, new DateTimeZone('UTC')))
            ->setTimezone(new DateTimeZone('Europe/Paris'));

        return \__('moderation.end_date', [
            'date' => $date->format($locale === 'en' ? 'j M Y' : 'd/m/Y'),
            'time' => $date->format('H:i'),
        ], $locale);
    }
}
