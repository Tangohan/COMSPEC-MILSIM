<?php

declare(strict_types=1);

namespace App\Services\Monitoring;

use App\Repositories\AtakModReportRepository;
use App\Services\Email\EmailEvents;
use App\Services\EmailService;

/**
 * E-mail à l'exploitation pour chaque signalement joueur venu du mod (Échap > Signaler un problème).
 * Destinataire : la boîte des alertes d'inscription / d'exploitation (registration_alert_inbox_email).
 * Limite : au plus MAX_PER_HOUR e-mails par joueur et par heure ; au-delà le signalement reste visible
 * dans /admin/atak-mod-reports, sans e-mail.
 */
final class AtakModReportMailer
{
    public const MAX_PER_HOUR = 5;

    public function __construct(
        private AtakModReportRepository $reports,
        private ?EmailService $email = null
    ) {}

    /**
     * @param array{id:int, channel:string, message:string, detail:string, source:string, steam_uid:?string,
     *     player_name:string, callsign:string, client_ip:string, mod_version:string, extension_version:string,
     *     arma_build:string} $r
     */
    public function notify(array $r): bool
    {
        $to = function_exists('registration_alert_inbox_email') ? registration_alert_inbox_email() : null;
        if ($to === null) {
            return false;
        }
        $recent = $this->reports->countRecentPlayerReports($r['steam_uid'], $r['client_ip'], 60);
        if ($recent > self::MAX_PER_HOUR) {
            return false;
        }

        [$subject, $html, $text] = self::compose($r, url('admin/atak-mod-reports'), $recent === self::MAX_PER_HOUR);
        $email = $this->email ?? \App\Core\Container::get(EmailService::class);

        return $email->send(
            EmailEvents::ATAK_MOD_PLAYER_REPORT,
            $to,
            $subject,
            $html,
            $text,
            null,
            null,
            ['purpose' => 'atak_mod_player_report', 'report_id' => $r['id']]
        );
    }

    /**
     * @param array<string, mixed> $r
     * @return array{0:string, 1:string, 2:string} sujet, HTML, texte
     */
    public static function compose(array $r, string $link, bool $lastBeforeLimit = false): array
    {
        $esc = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
        $who = trim((string) ($r['callsign'] ?? ''));
        $name = trim((string) ($r['player_name'] ?? ''));
        if ($who === '') {
            $who = $name !== '' ? $name : 'joueur inconnu';
        } elseif ($name !== '' && $name !== $who) {
            $who .= ' (' . $name . ')';
        }
        $type = trim((string) ($r['channel'] ?? '')) ?: 'Autre';
        $message = trim((string) ($r['message'] ?? ''));
        $detail = trim((string) ($r['detail'] ?? ''));
        $withLog = str_contains($detail, '--- journal ---');
        $short = mb_substr(preg_replace('/\s+/u', ' ', $message) ?? $message, 0, 70);

        $facts = [
            'Type' => $type,
            'Joueur' => $who,
            'Steam' => (string) ($r['steam_uid'] ?? '') ?: '—',
            'Mod' => (string) ($r['mod_version'] ?? '') ?: '—',
            'COMSPEC Link' => (string) ($r['extension_version'] ?? '') ?: '—',
            'Arma' => (string) ($r['arma_build'] ?? '') ?: '—',
            'Journal joint' => $withLog ? 'oui' : 'non',
        ];

        $subject = 'Signalement joueur — ' . $type . ' — ' . $who;

        $text = 'Nouveau signalement envoyé depuis le jeu (Échap > Signaler un problème).' . "\n\n"
            . 'Message du joueur :' . "\n" . $message . "\n\n";
        foreach ($facts as $k => $v) {
            $text .= $k . ' : ' . $v . "\n";
        }
        $text .= "\n" . 'Voir et traiter : ' . $link . "\n";
        if ($detail !== '') {
            $text .= "\n" . '--- Détail technique ---' . "\n" . $detail . "\n";
        }

        $rows = '';
        foreach ($facts as $k => $v) {
            $rows .= '<tr><td style="padding:2px 12px 2px 0;color:#667">' . $esc($k) . '</td><td>' . $esc($v) . '</td></tr>';
        }
        $html = '<p>Nouveau signalement envoyé depuis le jeu (Échap &gt; Signaler un problème).</p>'
            . '<p style="padding:10px 12px;background:#f3f5f3;border-left:3px solid #5CC76B;white-space:pre-wrap">'
            . $esc($message) . '</p>'
            . '<table style="border-collapse:collapse;font-size:14px">' . $rows . '</table>'
            . '<p><a href="' . $esc($link) . '">Voir et traiter le signalement</a></p>';
        if ($detail !== '') {
            $html .= '<p style="margin-top:18px;color:#667">Détail technique' . ($withLog ? ' et journal de session' : '') . ' :</p>'
                . '<pre style="font-size:12px;background:#f6f6f6;padding:10px;white-space:pre-wrap;word-break:break-word">'
                . $esc($detail) . '</pre>';
        }
        if ($lastBeforeLimit) {
            $note = 'Ce joueur a atteint ' . self::MAX_PER_HOUR . ' signalements cette heure : les suivants ne seront visibles que sur le site.';
            $text .= "\n" . $note . "\n";
            $html .= '<p style="color:#a66">' . $esc($note) . '</p>';
        }
        if ($short !== '' && mb_strlen($subject) < 120) {
            $subject .= ' — ' . $short;
        }

        // Indicatif et type viennent du jeu : pas de retour à la ligne dans l'en-tête Subject.
        $subject = trim((string) preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $subject));

        return [$subject, $html, $text];
    }
}
