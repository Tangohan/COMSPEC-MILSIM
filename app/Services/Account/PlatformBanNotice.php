<?php

declare(strict_types=1);

namespace App\Services\Account;

use DateTimeImmutable;
use DateTimeZone;

final class PlatformBanNotice
{
    public const MODELS = [
        'conduite' => [
            'rule' => 'Code de conduite Athena · art. 1',
            'text' => 'Nous avons restreint votre accès à Athena après examen d’un incident lié aux règles de conduite. Si un élément de contexte a été omis ou si vous pensez que la décision repose sur une erreur, vous pouvez demander un réexamen.',
        ],
        'contenus' => [
            'rule' => 'Code de conduite Athena · art. 2',
            'text' => 'Après examen de contenus associés à votre compte, l’équipe de modération a décidé de restreindre votre accès. Vous pouvez apporter des précisions sur les contenus ou leur contexte dans votre contestation.',
        ],
        'securite' => [
            'rule' => 'Code de conduite Athena · art. 3',
            'text' => 'Votre accès a été restreint à la suite d’un incident de sécurité. Si vous pensez que votre compte a été utilisé à votre insu, indiquez ce que vous avez constaté dans votre demande de réexamen. Ne communiquez jamais votre mot de passe.',
        ],
        'inactivite' => [
            'rule' => 'Code de conduite Athena · art. 7',
            'text' => 'Nous avons suspendu l’accès à votre compte car aucune connexion à Athena ni session de jeu n’a été enregistrée depuis une période prolongée. Si vous souhaitez reprendre votre activité ou si vous pensez qu’une connexion n’a pas été comptabilisée, vous pouvez demander le réexamen de cette décision.',
        ],
        'inactivite_initiale' => [
            'rule' => 'Code de conduite Athena · art. 7',
            'text' => 'Nous avons suspendu l’accès à votre compte car aucune connexion à Athena ni session de jeu n’a été enregistrée depuis sa création. Si vous souhaitez commencer à utiliser Athena ou si vous pensez qu’une connexion n’a pas été comptabilisée, vous pouvez demander le réexamen de cette décision.',
        ],
    ];

    private const LEGACY_RULES = [
        'CGU Athena · Règles de conduite' => 'conduite',
        'CGU Athena · Contenus utilisateurs et modération' => 'contenus',
        'CGU Athena · Compte utilisateur et suspension' => 'securite',
        'Athena · Inactivité du compte' => 'inactivite',
    ];

    private const LEGACY_INACTIVITY = 'Votre accès à Athena a été suspendu après une période sans connexion au site ni session de jeu enregistrée. Si vous pensez qu’une activité n’a pas été prise en compte, vous pouvez demander un réexamen.';

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

    /** @return array{id:int, rule:string, rule_url:string, message:string, end:string, start:string, permanent:bool, appeal_closed:bool, appeal:array{state:string, received:string, reference:string}|null, locale:string} */
    public static function publicDetails(array $ban, string $locale = 'fr'): array
    {
        $rule = trim((string) ($ban['rule_reference'] ?? ''));
        $appealClosed = !empty($ban['appeal_closed']);
        $article = null;
        if (preg_match('/^Code de conduite Athena · art\. ([1-7])$/uD', $rule, $match) === 1) {
            $article = (int) $match[1];
        }
        if ($locale === 'en') {
            if ($article !== null) {
                $rule = \__('moderation.conduct_article', ['number' => $article], $locale);
            } elseif (isset(self::LEGACY_RULES[$rule])) {
                $rule = \__('moderation.preset_' . self::LEGACY_RULES[$rule] . '_rule', [], $locale);
            }
        }

        return [
            'id' => (int) $ban['id'],
            'rule' => $rule,
            'rule_url' => $article === null ? '' : url('code-de-conduite') . '?lang=' . $locale . '#article-' . $article,
            'locale' => $locale,
            'message' => self::displayMessage((string) ($ban['public_reason'] ?? ''), $appealClosed, $locale),
            'end' => self::endLabel($ban['ends_at'] ?? null, $locale),
            'start' => self::dateLabel($ban['created_at'] ?? null, $locale),
            'permanent' => empty($ban['ends_at']),
            'appeal_closed' => $appealClosed,
            'appeal' => null,
        ];
    }

    /**
     * Avis affiché à la connexion (fenêtre de suspension) : décision publique et suivi de la contestation éventuelle.
     *
     * @param array<string, mixed> $ban ligne platform_account_bans
     * @return array<string, mixed>
     */
    public static function forLogin(array $ban, string $locale = 'fr'): array
    {
        $notice = self::publicDetails($ban, $locale);
        try {
            $appeals = new \App\Repositories\PlatformBanAppealRepository();
            $notice['appeal'] = $appeals->ready() ? self::appealProgress($appeals->find((int) $ban['id']), $locale) : null;
        } catch (\Throwable) {
            // Le suivi est un confort : l’avis de suspension s’affiche sans lui.
        }

        return $notice;
    }

    /**
     * Suivi d’une contestation pour la personne concernée : reçue, puis décision. Aucun nom de modérateur ni note interne.
     *
     * @param array<string, mixed>|null $appeal ligne platform_ban_appeals
     * @return array{state: string, received: string, reference: string}|null
     */
    public static function appealProgress(?array $appeal, string $locale = 'fr'): ?array
    {
        if ($appeal === null) {
            return null;
        }
        $state = (string) ($appeal['status'] ?? '');
        if (!in_array($state, ['received', 'in_review', 'info_requested', 'accepted', 'upheld', 'closed'], true)) {
            $state = match ((string) ($appeal['decision'] ?? '')) {
                'lift' => 'accepted',
                'uphold' => 'upheld',
                default => 'received',
            };
        }

        return [
            'state' => $state,
            'received' => self::dateLabel($appeal['created_at'] ?? null, $locale),
            'reference' => (string) ($appeal['reference'] ?? ''),
        ];
    }

    /** Date UTC de la base, affichée à l’heure de Paris. Vide si la date est absente ou illisible. */
    public static function dateLabel(?string $utc, string $locale = 'fr'): string
    {
        if ($utc === null || $utc === '') {
            return '';
        }
        try {
            $date = (new DateTimeImmutable($utc, new DateTimeZone('UTC')))->setTimezone(new DateTimeZone('Europe/Paris'));
        } catch (\Exception) {
            return '';
        }

        return \__('moderation.end_date', [
            'date' => $date->format($locale === 'en' ? 'j M Y' : 'd/m/Y'),
            'time' => $date->format('H:i'),
        ], $locale);
    }

    public static function displayMessage(string $message, bool $appealClosed, string $locale = 'fr'): string
    {
        $message = trim($message) ?: self::DEFAULT_TEXT;
        $key = match ($message) {
            self::DEFAULT_TEXT => 'moderation.default_reason',
            self::LEGACY_INACTIVITY => 'moderation.legacy_inactivity_reason',
            default => null,
        };
        foreach (self::MODELS as $modelKey => $model) {
            if ($message === $model['text']) {
                $key = 'moderation.preset_' . $modelKey . '_reason';
                break;
            }
        }
        if ($key === null) {
            return $message;
        }
        if ($appealClosed && in_array($key, ['moderation.preset_inactivite_reason', 'moderation.preset_inactivite_initiale_reason'], true)) {
            return \__($key . '_closed', [], $locale);
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

        return self::dateLabel($endsAt, $locale) ?: \__('moderation.no_end', [], $locale);
    }
}
