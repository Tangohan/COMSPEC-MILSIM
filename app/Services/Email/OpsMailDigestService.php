<?php

declare(strict_types=1);

namespace App\Services\Email;

use App\Repositories\EmailDigestRepository;
use App\Services\EmailService;

/**
 * Récapitulatif des alertes d'exploitation : un e-mail par boîte toutes les N minutes au lieu d'un par alerte.
 */
final class OpsMailDigestService
{
    /** Alertes détaillées dans le corps ; au-delà, seulement comptées par type. */
    public const MAX_DETAILED = 40;

    public function __construct(
        private EmailDigestRepository $items,
        private EmailService $email,
        private MailBudgetGate $gate,
    ) {}

    /**
     * @param bool $force envoie tout de suite tout ce qui attend (bouton de la page d'administration)
     * @return array{recipients:int, emails:int, items:int}
     */
    public function sendDue(bool $force = false): array
    {
        $minutes = $force ? 0 : max(1, $this->gate->config()['digest_minutes']);
        $out = ['recipients' => 0, 'emails' => 0, 'items' => 0];
        foreach ($this->items->recipientsDue($minutes) as $to) {
            $rows = $this->items->pendingFor($to);
            if ($rows === []) {
                continue;
            }
            $out['recipients']++;
            [$subject, $html, $text] = self::compose($rows, url('admin/system/e-mails'));
            $ok = $this->email->send(
                EmailEvents::OPS_DIGEST,
                $to,
                $subject,
                $html,
                $text,
                null,
                null,
                ['purpose' => 'ops_digest', 'items' => count($rows)]
            );
            if ($ok) {
                $this->items->markDigested(array_map(static fn (array $r): int => (int) $r['id'], $rows));
                $out['emails']++;
                $out['items'] += count($rows);
            }
        }
        try {
            $this->items->purgeOld();
        } catch (\Throwable) {
        }

        return $out;
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return array{0:string, 1:string, 2:string} sujet, HTML, texte
     */
    public static function compose(array $rows, string $adminLink): array
    {
        $byEvent = [];
        foreach ($rows as $r) {
            $code = (string) ($r['event_code'] ?? '');
            $byEvent[$code] = ($byEvent[$code] ?? 0) + 1;
        }
        arsort($byEvent);
        $total = count($rows);
        $parts = [];
        foreach ($byEvent as $code => $n) {
            $parts[] = $n . ' × ' . MailBudget::label((string) $code);
        }
        $brand = function_exists('email_brand_name') ? email_brand_name() : 'Athena';
        $subject = '[' . $brand . '] Récapitulatif exploitation — ' . $total . ' alerte' . ($total > 1 ? 's' : '');

        $esc = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
        $clip = static function (string $s, int $max): string {
            $s = trim(preg_replace("/\n{3,}/", "\n\n", str_replace("\r", '', $s)) ?? $s);

            return mb_strlen($s) > $max ? mb_substr($s, 0, $max - 1) . '…' : $s;
        };
        $when = static function (mixed $raw): string {
            $t = strtotime((string) $raw);

            return $t ? date('d/m H:i', $t) : '';
        };

        $text = $total . ' alerte' . ($total > 1 ? 's' : '') . " regroupée(s) depuis le dernier récapitulatif :\n";
        foreach ($parts as $p) {
            $text .= '  - ' . $p . "\n";
        }
        $text .= "\nRéglages et volume des envois : " . $adminLink . "\n";

        $html = '<p style="margin:0 0 12px;">' . $esc((string) $total) . ' alerte' . ($total > 1 ? 's' : '')
            . ' regroupée(s) depuis le dernier récapitulatif.</p><ul style="margin:0 0 16px;padding-left:18px;">';
        foreach ($parts as $p) {
            $html .= '<li>' . $esc($p) . '</li>';
        }
        $html .= '</ul>';

        foreach (array_slice($rows, 0, self::MAX_DETAILED) as $r) {
            $label = MailBudget::label((string) ($r['event_code'] ?? ''));
            $subj = (string) ($r['subject'] ?? '');
            $body = $clip((string) ($r['body_text'] ?? ''), 700);
            $at = $when($r['created_at'] ?? '');
            $text .= "\n──────── " . $at . ' · ' . $label . "\n" . $subj . "\n" . $body . "\n";
            $html .= '<div style="border-top:1px solid #d0d5dd;padding:10px 0;">'
                . '<p style="margin:0;font-size:12px;color:#667085;">' . $esc($at . ' · ' . $label) . '</p>'
                . '<p style="margin:2px 0 6px;font-weight:600;">' . $esc($subj) . '</p>'
                . '<pre style="margin:0;white-space:pre-wrap;font-family:inherit;font-size:13px;color:#344054;">' . $esc($body) . '</pre>'
                . '</div>';
        }
        if ($total > self::MAX_DETAILED) {
            $more = $total - self::MAX_DETAILED;
            $text .= "\n… et " . $more . " autre(s) alerte(s), comptée(s) ci-dessus.\n";
            $html .= '<p style="color:#667085;">… et ' . $esc((string) $more) . ' autre(s) alerte(s), comptée(s) ci-dessus.</p>';
        }
        $html .= '<p style="margin-top:16px;"><a href="' . $esc($adminLink) . '">Réglages et volume des envois</a></p>';

        return [$subject, $html, $text];
    }
}
