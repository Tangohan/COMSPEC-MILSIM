<?php

declare(strict_types=1);

namespace App\Services\Monitoring;

use App\Repositories\AtakModReportRepository;
use App\Repositories\MemberNotificationRepository;
use App\Services\Email\EmailEvents;
use App\Services\EmailService;

/**
 * Réponse de l'exploitation au joueur qui a envoyé un rapport depuis le mod (page /admin/atak-mod-reports).
 *
 * Le texte s'adapte au rapport : signalement écrit par le joueur (« votre signalement ») ou erreur envoyée
 * automatiquement par son mod (« votre mod nous a envoyé cette erreur »), et à la langue de son profil Athena
 * (anglais si son profil est en anglais, français sinon).
 *
 * Le joueur n'est connu que par son Steam : on le prévient sur Athena (centre d'activité) et par e-mail
 * quand ce Steam est lié à un compte. La réponse reste toujours enregistrée sur le rapport.
 * Pas de message dans le jeu : le téléphone ne relève pas encore de messages du site (il faudrait une
 * commande « notif » côté mod).
 */
final class AtakModReportReplyService
{
    public const NOTIFICATION_TYPE = 'atak_mod_report_reply';

    /** @var \Closure(string, array<string, mixed>, ?int, array<string, mixed>): bool */
    private \Closure $sendMail;

    /**
     * @param (\Closure(string, array<string, mixed>, ?int, array<string, mixed>): bool)|null $sendMail
     *        envoi de l'e-mail (destinataire, variables du modèle, communauté, résumé) ; EmailService par défaut
     */
    public function __construct(
        private AtakModReportRepository $reports,
        private ?MemberNotificationRepository $notifications = null,
        ?\Closure $sendMail = null
    ) {
        $this->sendMail = $sendMail ?? static function (string $to, array $vars, ?int $tenantId, array $summary): bool {
            return \App\Core\Container::get(EmailService::class)->sendTemplated(
                EmailEvents::ATAK_MOD_REPORT_REPLY,
                'atak_mod_report_reply',
                $to,
                self::subject((bool) ($vars['auto'] ?? false), (string) ($vars['lang'] ?? 'fr')),
                $vars,
                $tenantId,
                null,
                $summary,
                false,
                (string) ($vars['lang'] ?? 'fr')
            );
        };
    }

    /** Messages types, selon que le rapport vient du joueur ou d'une détection automatique, et la langue du joueur. */
    private const TEMPLATES = [
        'player' => [
            'fr' => [
                self::TPL_FIXED => 'Merci pour votre signalement. Le problème est corrigé : la correction arrive avec la prochaine mise à jour du mod COMSPEC ATAK.',
                self::TPL_IN_PROGRESS => 'Merci pour votre signalement. Nous avons reproduit le problème et travaillons sur une correction.',
                self::TPL_NEED_INFO => 'Merci pour votre signalement. Pouvez-vous nous en dire plus (ce que vous faisiez, sur quel serveur) ? Répondez sur le Discord COMSPEC, salon support.',
                self::TPL_NOT_A_BUG => 'Merci pour votre signalement. Après vérification, ce comportement est normal dans le mod : rien à corriger de notre côté.',
            ],
            'en' => [
                self::TPL_FIXED => 'Thanks for your report. The problem is fixed: the fix ships with the next COMSPEC ATAK mod update.',
                self::TPL_IN_PROGRESS => 'Thanks for your report. We reproduced the problem and are working on a fix.',
                self::TPL_NEED_INFO => 'Thanks for your report. Can you tell us more (what you were doing, on which server)? Reply on the COMSPEC Discord, support channel.',
                self::TPL_NOT_A_BUG => 'Thanks for your report. After checking, this is how the mod is meant to behave: nothing to fix on our side.',
            ],
        ],
        'auto' => [
            'fr' => [
                self::TPL_FIXED => 'Votre mod COMSPEC ATAK nous a envoyé cette erreur automatiquement. Elle est corrigée : la correction arrive avec la prochaine mise à jour du mod.',
                self::TPL_IN_PROGRESS => 'Votre mod COMSPEC ATAK nous a envoyé cette erreur automatiquement. Nous l’avons reproduite et travaillons sur une correction : vous n’avez rien à faire.',
                self::TPL_NEED_INFO => 'Votre mod COMSPEC ATAK nous a envoyé cette erreur automatiquement. Pouvez-vous nous dire ce que vous faisiez à ce moment-là ? Répondez sur le Discord COMSPEC, salon support.',
                self::TPL_NOT_A_BUG => 'Votre mod COMSPEC ATAK nous a envoyé cette erreur automatiquement. Après vérification, elle est sans conséquence : rien à faire de votre côté.',
            ],
            'en' => [
                self::TPL_FIXED => 'Your COMSPEC ATAK mod sent us this error automatically. It is fixed: the fix ships with the next mod update.',
                self::TPL_IN_PROGRESS => 'Your COMSPEC ATAK mod sent us this error automatically. We reproduced it and are working on a fix: nothing to do on your side.',
                self::TPL_NEED_INFO => 'Your COMSPEC ATAK mod sent us this error automatically. Can you tell us what you were doing at that moment? Reply on the COMSPEC Discord, support channel.',
                self::TPL_NOT_A_BUG => 'Your COMSPEC ATAK mod sent us this error automatically. After checking, it has no impact: nothing to do on your side.',
            ],
        ],
    ];

    public const TPL_FIXED = 'fixed';
    public const TPL_IN_PROGRESS = 'in_progress';
    public const TPL_NEED_INFO = 'need_info';
    public const TPL_NOT_A_BUG = 'not_a_bug';

    /** Libellés (page d'administration) des messages types. */
    public const TEMPLATE_LABELS = [
        self::TPL_FIXED => 'Corrigé',
        self::TPL_IN_PROGRESS => 'En cours',
        self::TPL_NEED_INFO => 'Besoin d’infos',
        self::TPL_NOT_A_BUG => 'Pas un bug',
    ];

    /** Langue d'envoi : en si le profil Athena du joueur est en anglais, sinon fr. */
    public static function lang(?array $account): string
    {
        return ($account['lang'] ?? 'fr') === 'en' ? 'en' : 'fr';
    }

    /** Le rapport vient d'une détection automatique du mod (pas d'Échap > Signaler un problème). */
    public static function isAuto(array $report): bool
    {
        return !AtakModReportGravity::isPlayerReport((string) ($report['source'] ?? ''));
    }

    /** @return array<string, string> clé du message type → texte */
    public static function templates(bool $auto, string $lang): array
    {
        return self::TEMPLATES[$auto ? 'auto' : 'player'][$lang === 'en' ? 'en' : 'fr'];
    }

    public static function template(string $key, bool $auto, string $lang): string
    {
        $set = self::templates($auto, $lang);

        return $set[$key] ?? $set[self::TPL_FIXED];
    }

    /** Message proposé quand on marque un rapport corrigé et qu'on prévient le joueur. */
    public static function defaultFixedMessage(bool $auto = false, string $lang = 'fr'): string
    {
        return self::template(self::TPL_FIXED, $auto, $lang);
    }

    public static function subject(bool $auto, string $lang): string
    {
        if ($lang === 'en') {
            return $auto ? 'COMSPEC ATAK: about the error your mod reported' : 'Reply to your COMSPEC ATAK report';
        }

        return $auto ? 'COMSPEC ATAK : à propos de l’erreur remontée par votre mod' : 'Réponse à votre signalement COMSPEC ATAK';
    }

    /**
     * @param array<string, mixed> $report ligne atak_mod_reports
     * @param array{user_id:int, tenant_id:int, email:string, name:string, lang?:string}|null $account compte lié au Steam
     * @return array{site:bool, email:bool, channels:list<string>}
     */
    public function reply(array $report, string $message, ?array $account, bool $viaSite, bool $viaEmail, string $by): array
    {
        $message = trim($message);
        $site = false;
        $mail = false;
        if ($message !== '' && $account !== null) {
            if ($viaSite) {
                $site = $this->notifySite($account, [$report], $message);
            }
            if ($viaEmail) {
                $mail = $this->sendEmail($account, [$report], $message);
            }
        }
        $channels = array_values(array_filter([$site ? 'site' : '', $mail ? 'email' : '']));
        if ($message !== '') {
            $this->reports->saveReply((int) ($report['id'] ?? 0), $message, $by, $channels);
        }

        return ['site' => $site, 'email' => $mail, 'channels' => $channels];
    }

    /**
     * Prévient chaque joueur une seule fois pour un lot de rapports (action groupée « corrigé »).
     *
     * Message vide : message « corrigé » type, dans la langue du joueur et selon que ses rapports viennent de lui ou
     * d'une détection automatique. Les rapports de gravité faible ne valent qu'une notification Athena, pas d'e-mail.
     *
     * @param list<array<string, mixed>> $reports
     * @return array{players:int, site:int, email:int, unreachable:int, low_no_email:int}
     */
    public function notifyMany(array $reports, string $message, string $by): array
    {
        $message = trim($message);
        $bySteam = [];
        $noSteam = 0;
        foreach ($reports as $report) {
            $steam = trim((string) ($report['steam_uid'] ?? ''));
            if ($steam === '') {
                $noSteam++;
                continue;
            }
            $bySteam[$steam][] = $report;
        }
        $accounts = $this->reports->linkedAccountsBySteam(array_map('strval', array_keys($bySteam)));
        $stats = ['players' => 0, 'site' => 0, 'email' => 0, 'unreachable' => $noSteam, 'low_no_email' => 0];
        foreach ($bySteam as $steam => $group) {
            $account = $accounts[(string) $steam] ?? null;
            if ($account === null) {
                $stats['unreachable'] += count($group);
                continue;
            }
            $stats['players']++;
            $text = $message !== '' ? $message : self::defaultFixedMessage(self::allAuto($group), self::lang($account));
            $site = $this->notifySite($account, $group, $text);
            $mailable = array_values(array_filter(
                $group,
                static fn (array $r): bool => AtakModReportGravity::mailsPlayer((string) ($r['gravity'] ?? ''))
            ));
            $mail = $mailable !== [] && $this->sendEmail($account, $mailable, $text);
            if ($mailable === []) {
                $stats['low_no_email']++;
            }
            $stats['site'] += $site ? 1 : 0;
            $stats['email'] += $mail ? 1 : 0;
            $channels = array_values(array_filter([$site ? 'site' : '', $mail ? 'email' : '']));
            foreach ($group as $report) {
                $this->reports->saveReply((int) ($report['id'] ?? 0), $text, $by, $channels);
            }
        }

        return $stats;
    }

    /**
     * @param array{user_id:int, tenant_id:int, email:string, name:string, lang?:string} $account
     * @param list<array<string, mixed>> $reports
     */
    private function notifySite(array $account, array $reports, string $message): bool
    {
        if ($account['user_id'] < 1 || $account['tenant_id'] < 1) {
            return false;
        }
        try {
            $repo = $this->notifications ?? new MemberNotificationRepository();
            if (!$repo->tableExists()) {
                return false;
            }

            return $repo->create($account['tenant_id'], $account['user_id'], self::NOTIFICATION_TYPE, [
                'summary' => self::clip($message, 160),
                // Texte complet et rapports cités : fenêtre « réponse à votre signalement » à la connexion.
                'message' => mb_substr($message, 0, 2000),
                'report_ids' => array_values(array_filter(array_map(static fn (array $r): int => (int) ($r['id'] ?? 0), $reports))),
                'report' => self::clip(self::reportsLabel($reports, self::lang($account)), 120),
                'auto' => self::allAuto($reports),
                'href' => url('activite'),
            ]) !== null;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * @param array{user_id:int, tenant_id:int, email:string, name:string, lang?:string} $account
     * @param list<array<string, mixed>> $reports
     */
    private function sendEmail(array $account, array $reports, string $message): bool
    {
        if ($account['email'] === '') {
            return false;
        }
        $lang = self::lang($account);
        $first = $reports[0] ?? [];
        $name = $account['name'] !== '' ? $account['name']
            : (trim((string) ($first['callsign'] ?? '')) ?: (trim((string) ($first['player_name'] ?? '')) ?: ($lang === 'en' ? 'operator' : 'opérateur')));
        try {
            return ($this->sendMail)(
                $account['email'],
                [
                    'displayName' => $name,
                    'reportLabel' => self::reportsLabel($reports, $lang),
                    'replyText' => $message,
                    'activityUrl' => url('activite'),
                    'auto' => self::allAuto($reports),
                    'lang' => $lang,
                ],
                $account['tenant_id'] > 0 ? $account['tenant_id'] : null,
                [
                    'purpose' => 'atak_mod_report_reply',
                    'report_ids' => array_map(static fn (array $r): int => (int) ($r['id'] ?? 0), $reports),
                    'lang' => $lang,
                ]
            );
        } catch (\Throwable) {
            return false;
        }
    }

    /** @param list<array<string, mixed>> $reports */
    private static function allAuto(array $reports): bool
    {
        foreach ($reports as $report) {
            if (!self::isAuto($report)) {
                return false;
            }
        }

        return $reports !== [];
    }

    /** @param list<array<string, mixed>> $reports */
    public static function reportsLabel(array $reports, string $lang = 'fr'): string
    {
        $messages = [];
        foreach ($reports as $report) {
            $m = self::clip((string) ($report['message'] ?? ''), 90);
            if ($m !== '' && !in_array($m, $messages, true)) {
                $messages[] = $m;
            }
        }
        if ($messages === []) {
            return $lang === 'en' ? 'Report sent from the game' : 'Signalement envoyé depuis le jeu';
        }
        if ($lang === 'en') {
            $label = '“' . $messages[0] . '”';
            if (count($messages) > 1) {
                $label .= ' and ' . (count($messages) - 1) . ' more';
            }

            return $label;
        }
        $label = '« ' . $messages[0] . ' »';
        if (count($messages) > 1) {
            $label .= ' et ' . (count($messages) - 1) . ' autre(s)';
        }

        return $label;
    }

    private static function clip(string $s, int $max): string
    {
        $s = trim((string) preg_replace('/\s+/u', ' ', $s));

        return mb_strlen($s) > $max ? rtrim(mb_substr($s, 0, $max - 1)) . '…' : $s;
    }
}
