<?php

declare(strict_types=1);

namespace App\Services\Cooperation;

/**
 * Mise en forme commune des courriels de coopération.
 *
 * Chaque courriel commence par un encadré fixe — coopération, unité émettrice, ce qui est attendu,
 * échéance — puis le texte du gabarit (modifiable par l’administration), puis un bouton d’action
 * qui mène directement au bon écran. Le gabarit reste libre ; seul l’encadré est imposé.
 */
final class CooperationEmailLayout
{
    /** Écrans cibles du bouton d’action (résolus en URL par le répartiteur). */
    public const TARGET_SHOW = 'show';
    public const TARGET_PARTICIPANTS = 'participants';
    public const TARGET_CONDUCT = 'conduct';
    public const TARGET_NEGOTIATE = 'negotiate';
    public const TARGET_CONSENT = 'consent';
    public const TARGET_EXCHANGE = 'exchange';
    public const TARGET_REX = 'rex';
    public const TARGET_INDEX = 'index';

    /**
     * Ce qui est attendu du destinataire et où agir, par événement.
     *
     * @return array{expected: string, cta: string, target: string, action_required: bool}
     */
    public static function action(string $eventKey): array
    {
        $info = static fn (string $cta = 'Ouvrir la coopération', string $target = self::TARGET_SHOW): array => [
            'expected' => 'Aucune action n’est attendue de votre part : ce message vous informe.',
            'cta' => $cta,
            'target' => $target,
            'action_required' => false,
        ];
        $todo = static fn (string $expected, string $cta, string $target): array => [
            'expected' => $expected,
            'cta' => $cta,
            'target' => $target,
            'action_required' => true,
        ];

        return match ($eventKey) {
            CooperationAnnouncementEvents::INVITATION_SENT,
            CooperationAnnouncementEvents::INVITATION_REMINDER => $todo(
                'Accepter ou refuser l’invitation de votre unité (un motif peut accompagner un refus).',
                'Répondre à l’invitation',
                self::TARGET_SHOW
            ),
            CooperationAnnouncementEvents::PARTNER_ACCEPTED => $todo(
                'Lancer la coopération dès que les unités attendues ont répondu.',
                'Voir les participants',
                self::TARGET_PARTICIPANTS
            ),
            CooperationAnnouncementEvents::PARTNER_DECLINED => $todo(
                'Inviter une autre unité, ou lancer sans elle si d’autres unités ont accepté.',
                'Voir les participants',
                self::TARGET_PARTICIPANTS
            ),
            CooperationAnnouncementEvents::COUNTER_PROPOSAL_SUBMITTED => $todo(
                'Examiner la contre-proposition, puis l’accepter ou la refuser.',
                'Traiter la contre-proposition',
                self::TARGET_NEGOTIATE
            ),
            CooperationAnnouncementEvents::COUNTER_PROPOSAL_ACCEPTED,
            CooperationAnnouncementEvents::COUNTER_PROPOSAL_DECLINED => $info('Voir la négociation', self::TARGET_NEGOTIATE),
            CooperationAnnouncementEvents::MISSION_ACTIVATED => $todo(
                'Donner l’autorisation de partage de votre unité pour accéder à l’espace commun.',
                'Donner mon autorisation',
                self::TARGET_CONSENT
            ),
            CooperationAnnouncementEvents::CONSENT_EXPIRING => $todo(
                'Renouveler votre autorisation de partage pour garder l’accès à l’espace commun.',
                'Renouveler mon autorisation',
                self::TARGET_CONSENT
            ),
            CooperationAnnouncementEvents::MISSION_RESUMED => $info('Ouvrir l’espace commun', self::TARGET_EXCHANGE),
            CooperationAnnouncementEvents::OPERATIONAL_STAGE_UPDATED => $info('Voir la conduite', self::TARGET_CONDUCT),
            CooperationAnnouncementEvents::SITREP_ADDED => $info('Lire le point de situation', self::TARGET_CONDUCT),
            CooperationAnnouncementEvents::MISSION_CLOSED => $todo(
                'Rédiger le retour d’expérience de votre unité.',
                'Rédiger le retour d’expérience',
                self::TARGET_REX
            ),
            CooperationAnnouncementEvents::MEMBER_DESIGNATED,
            CooperationAnnouncementEvents::CO_LEAD_DESIGNATED => $info('Voir mon rôle', self::TARGET_SHOW),
            CooperationAnnouncementEvents::MISSION_CREATED => $todo(
                'Compléter la proposition et inviter les unités partenaires.',
                'Ouvrir la coopération',
                self::TARGET_SHOW
            ),
            // Unité retirée ou proposition annulée : la coopération n’est plus accessible, on renvoie à la liste.
            CooperationAnnouncementEvents::PARTNER_REMOVED,
            CooperationAnnouncementEvents::PROPOSAL_CANCELLED => $info('Voir mes coopérations', self::TARGET_INDEX),
            default => $info(),
        };
    }

    /**
     * Corps HTML complet (styles en ligne, compatibles avec les clients de messagerie).
     *
     * @param array{title: string, issuer: string, expected: string, deadline: string, cta: string, url: string, action_required: bool} $head
     */
    public static function html(array $head, string $body): string
    {
        $h = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
        $rows = [
            ['Coopération', $head['title']],
            ['Unité émettrice', $head['issuer']],
            ['Ce qui est attendu', $head['expected']],
        ];
        if ($head['deadline'] !== '') {
            $rows[] = ['Échéance', $head['deadline']];
        }
        $accent = $head['action_required'] ? '#1d4ed8' : '#475569';

        $out = '<div style="font-family:Arial,Helvetica,sans-serif;color:#0f172a;max-width:600px;">';
        $out .= '<table role="presentation" cellpadding="0" cellspacing="0" style="width:100%;border-collapse:collapse;border-left:4px solid ' . $accent . ';background:#f8fafc;margin:0 0 20px;">';
        foreach ($rows as [$label, $value]) {
            if (trim($value) === '') {
                continue;
            }
            $out .= '<tr><th scope="row" style="text-align:left;vertical-align:top;padding:8px 12px;font-size:13px;color:#475569;font-weight:600;white-space:nowrap;">'
                . $h($label) . '</th><td style="padding:8px 12px;font-size:14px;color:#0f172a;">' . $h($value) . '</td></tr>';
        }
        $out .= '</table>';
        $out .= '<div style="font-size:15px;line-height:1.55;">' . nl2br($h($body)) . '</div>';
        if ($head['url'] !== '') {
            $out .= '<p style="margin:24px 0 8px;"><a href="' . $h($head['url']) . '" style="display:inline-block;background:' . $accent
                . ';color:#ffffff;text-decoration:none;font-weight:700;padding:12px 20px;border-radius:6px;font-size:15px;">'
                . $h($head['cta']) . '</a></p>';
            $out .= '<p style="font-size:12px;color:#64748b;margin:0;">Si le bouton ne fonctionne pas, copiez ce lien : ' . $h($head['url']) . '</p>';
        }
        $out .= '</div>';

        return $out;
    }

    /**
     * Version texte (même informations que l’encadré, puis le message et le lien).
     *
     * @param array{title: string, issuer: string, expected: string, deadline: string, cta: string, url: string, action_required: bool} $head
     */
    public static function text(array $head, string $body): string
    {
        $lines = [
            'Coopération : ' . $head['title'],
            'Unité émettrice : ' . $head['issuer'],
            'Ce qui est attendu : ' . $head['expected'],
        ];
        if ($head['deadline'] !== '') {
            $lines[] = 'Échéance : ' . $head['deadline'];
        }
        $out = implode("\n", $lines) . "\n\n" . trim(strip_tags($body));
        if ($head['url'] !== '') {
            $out .= "\n\n" . $head['cta'] . ' : ' . $head['url'];
        }

        return $out;
    }
}
