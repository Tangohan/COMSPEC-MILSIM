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

    public function __construct(string $rule, string $message)
    {
        $this->rule = mb_substr(trim($rule), 0, 160);
        $this->message = mb_substr(trim($message), 0, 2000);
    }

    public function isEmpty(): bool
    {
        return $this->rule === '' && $this->message === '';
    }

    /** @return array{id:int, rule:string, message:string, end:string} */
    public static function publicDetails(array $ban, string $locale = 'fr'): array
    {
        $rule = trim((string) ($ban['rule_reference'] ?? ''));
        $message = trim((string) ($ban['public_reason'] ?? '')) ?: self::DEFAULT_TEXT;
        if ($locale === 'en') {
            if ($message === self::DEFAULT_TEXT) {
                $message = \__('moderation.default_reason', [], $locale);
            }
            foreach (self::MODELS as $key => $model) {
                if ($rule === $model['rule']) {
                    $rule = \__('moderation.preset_' . $key . '_rule', [], $locale);
                }
                if ($message === $model['text']) {
                    $message = \__('moderation.preset_' . $key . '_reason', [], $locale);
                }
            }
        }

        return [
            'id' => (int) $ban['id'],
            'rule' => $rule,
            'message' => $message,
            'end' => self::endLabel($ban['ends_at'] ?? null, $locale),
        ];
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
