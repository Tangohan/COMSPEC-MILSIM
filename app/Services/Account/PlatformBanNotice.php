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

    public static function endLabel(?string $endsAt): string
    {
        if ($endsAt === null || $endsAt === '') {
            return 'Sans date de fin';
        }

        return (new DateTimeImmutable($endsAt, new DateTimeZone('UTC')))
            ->setTimezone(new DateTimeZone('Europe/Paris'))->format('d/m/Y à H:i') . ' (heure de Paris)';
    }
}
