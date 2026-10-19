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
            $adminLink = url('admin/system/e-mails');
            [$subject, $html, $text] = self::compose($rows, $adminLink);
            $ok = $this->email->send(
                EmailEvents::OPS_DIGEST,
                $to,
                $subject,
                $html,
                $text,
                null,
                null,
                ['purpose' => 'ops_digest', 'items' => count($rows)],
                null,
                false,
                false,
                [[
                    'name' => self::attachmentName(),
                    'content' => self::attachmentText($rows, $adminLink),
                    'mime' => 'text/plain; charset=UTF-8',
                ]]
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
     * Regroupe les alertes identiques (même type, même sujet, même cause) : 24 erreurs 500 de la même ligne
     * deviennent une seule entrée « 24 fois, de 08:02 à 10:01 ».
     *
     * @param list<array<string, mixed>> $rows
     * @return list<array{event_code:string, subject:string, cause:string, count:int, first:string, last:string, rows:list<array<string, mixed>>}>
     */
    public static function groupByCause(array $rows): array
    {
        $groups = [];
        foreach ($rows as $r) {
            $code = (string) ($r['event_code'] ?? '');
            $subject = (string) ($r['subject'] ?? '');
            $body = (string) ($r['body_text'] ?? '');
            $cause = self::field($body, 'Message');
            $where = self::field($body, 'Fichier') . ':' . self::field($body, 'Ligne');
            $key = $code . '|' . $subject . '|' . mb_substr($cause, 0, 240) . '|' . $where;
            $at = (string) ($r['created_at'] ?? '');
            if (!isset($groups[$key])) {
                $groups[$key] = [
                    'event_code' => $code,
                    'subject' => $subject,
                    'cause' => $cause,
                    'count' => 0,
                    'first' => $at,
                    'last' => $at,
                    'rows' => [],
                ];
            }
            $g = &$groups[$key];
            $g['count']++;
            if ($at !== '' && ($g['first'] === '' || $at < $g['first'])) {
                $g['first'] = $at;
            }
            if ($at > $g['last']) {
                $g['last'] = $at;
            }
            $g['rows'][] = $r;
            unset($g);
        }
        $groups = array_values($groups);
        usort($groups, static fn (array $a, array $b): int => [$b['count'], $a['first']] <=> [$a['count'], $b['first']]);

        return $groups;
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
        $groups = self::groupByCause($rows);
        $parts = [];
        foreach ($byEvent as $code => $n) {
            $parts[] = $n . ' × ' . MailBudget::label((string) $code);
        }
        $brand = function_exists('email_brand_name') ? email_brand_name() : 'Athena';
        $subject = '[' . $brand . '] Récapitulatif exploitation — ' . $total . ' alerte' . ($total > 1 ? 's' : '')
            . (count($groups) < $total ? ' (' . count($groups) . ' cause' . (count($groups) > 1 ? 's' : '') . ')' : '');

        $esc = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');

        $text = $total . ' alerte' . ($total > 1 ? 's' : '') . " regroupée(s) depuis le dernier récapitulatif :\n";
        foreach ($parts as $p) {
            $text .= '  - ' . $p . "\n";
        }
        $text .= "\nLe détail complet (pile d'appels, chaque occurrence) est dans le fichier .txt joint.\n";
        $text .= 'Réglages et volume des envois : ' . $adminLink . "\n";

        $html = '<p style="margin:0 0 12px;">' . $esc((string) $total) . ' alerte' . ($total > 1 ? 's' : '')
            . ' regroupée(s) depuis le dernier récapitulatif.</p><ul style="margin:0 0 16px;padding-left:18px;">';
        foreach ($parts as $p) {
            $html .= '<li>' . $esc($p) . '</li>';
        }
        $html .= '</ul><p style="margin:0 0 12px;color:#667085;font-size:13px;">Les alertes identiques sont regroupées'
            . ' en une seule entrée. Le détail complet (pile d’appels, chaque occurrence) est dans le fichier .txt joint.</p>';

        foreach (array_slice($groups, 0, self::MAX_DETAILED) as $g) {
            $label = MailBudget::label($g['event_code']);
            $head = self::span($g) . ' · ' . $label;
            $last = $g['rows'][count($g['rows']) - 1];
            $body = self::excerpt((string) ($last['body_text'] ?? ''), 900);
            $text .= "\n──────── " . $head . "\n" . $g['subject'] . "\n" . $body . "\n";
            $html .= '<div style="border-top:1px solid #d0d5dd;padding:10px 0;">'
                . '<p style="margin:0;font-size:12px;color:#667085;">'
                . ($g['count'] > 1 ? '<strong style="color:#b42318;">' . $esc($g['count'] . ' fois') . '</strong> · ' : '')
                . $esc(self::span($g, false) . ' · ' . $label) . '</p>'
                . '<p style="margin:2px 0 6px;font-weight:600;">' . $esc($g['subject']) . '</p>'
                . '<pre style="margin:0;white-space:pre-wrap;font-family:inherit;font-size:13px;color:#344054;">' . $esc($body) . '</pre>'
                . '</div>';
        }
        if (count($groups) > self::MAX_DETAILED) {
            $more = count($groups) - self::MAX_DETAILED;
            $text .= "\n… et " . $more . " autre(s) cause(s), dans le fichier joint.\n";
            $html .= '<p style="color:#667085;">… et ' . $esc((string) $more) . ' autre(s) cause(s), dans le fichier joint.</p>';
        }
        $html .= '<p style="margin-top:16px;"><a href="' . $esc($adminLink) . '">Réglages et volume des envois</a></p>';

        return [$subject, $html, $text];
    }

    /** Nom du fichier joint au récapitulatif. */
    public static function attachmentName(?int $now = null): string
    {
        return 'rapports-erreurs-' . date('Y-m-d-Hi', $now ?? time()) . '.txt';
    }

    /**
     * Fichier texte joint : une section par cause (compteur, dates, rapport complet de la dernière occurrence)
     * puis la liste de chaque occurrence (date, route, identifiant de requête, IP, client / version du mod).
     *
     * @param list<array<string, mixed>> $rows
     */
    public static function attachmentText(array $rows, string $adminLink = ''): string
    {
        $groups = self::groupByCause($rows);
        $total = count($rows);
        $rule = str_repeat('=', 78);
        $out = [
            'RAPPORTS D’ERREUR — RÉCAPITULATIF EXPLOITATION',
            'Généré le ' . date('d/m/Y H:i'),
            $total . ' alerte(s), ' . count($groups) . ' cause(s) distincte(s).',
            '',
            'CAUSES (de la plus fréquente à la plus rare)',
        ];
        foreach ($groups as $i => $g) {
            $out[] = sprintf('%3d. %4d × %s — %s', $i + 1, $g['count'], MailBudget::label($g['event_code']), $g['subject']);
            if ($g['cause'] !== '') {
                $out[] = '          ' . $g['cause'];
            }
        }
        foreach ($groups as $i => $g) {
            $out[] = '';
            $out[] = $rule;
            $out[] = sprintf('CAUSE %d — %d occurrence(s) · %s', $i + 1, $g['count'], self::span($g, false));
            $out[] = MailBudget::label($g['event_code']) . ' — ' . $g['subject'];
            $out[] = $rule;
            $out[] = 'Occurrences :';
            foreach ($g['rows'] as $r) {
                $body = (string) ($r['body_text'] ?? '');
                $bits = array_filter([
                    self::when($r['created_at'] ?? '', 'd/m/Y H:i:s'),
                    self::field($body, 'Méthode HTTP') . ' ' . self::field($body, 'Chemin applicatif'),
                    ($rid = self::field($body, 'Identifiant de corrélation')) !== '' ? 'requête ' . $rid : '',
                    ($ip = self::field($body, 'Adresse IP du client')) !== '' ? 'IP ' . $ip : '',
                    ($ua = self::field($body, 'Client (User-Agent, version du mod)')) !== '' ? 'client ' . $ua : '',
                    ($tenant = self::field($body, 'Communauté active (identifiant interne)')) !== '' ? 'communauté ' . $tenant : '',
                ], static fn (string $v): bool => trim($v) !== '' && trim($v) !== '—');
                $out[] = '  - ' . implode(' · ', array_map('trim', $bits));
            }
            $last = $g['rows'][count($g['rows']) - 1];
            $out[] = '';
            $out[] = 'Rapport complet de la dernière occurrence :';
            $out[] = str_replace("\r", '', trim((string) ($last['body_text'] ?? '')));
        }
        if ($adminLink !== '') {
            $out[] = '';
            $out[] = 'Réglages et volume des envois : ' . $adminLink;
        }

        return implode("\r\n", $out) . "\r\n";
    }

    /**
     * Valeur d'une ligne « Libellé : valeur » d'un rapport d'incident ('' si absente).
     */
    private static function field(string $body, string $label): string
    {
        if ($body === '') {
            return '';
        }
        if (preg_match('/^' . preg_quote($label, '/') . '\s*:\s*(.*)$/mu', $body, $m) !== 1) {
            return '';
        }
        $v = trim($m[1]);

        return $v === '—' ? '' : $v;
    }

    /** Partie utile du rapport : sans l'introduction standard ni la pile d'appels (elle est dans le .txt). */
    private static function excerpt(string $body, int $max): string
    {
        $body = str_replace("\r", '', $body);
        $start = mb_strpos($body, '────────── Résumé');
        if ($start !== false) {
            $body = mb_substr($body, $start);
        }
        $end = mb_strpos($body, '────────── Pile');
        if ($end !== false) {
            $body = mb_substr($body, 0, $end);
        }
        $body = trim(preg_replace("/\n{3,}/", "\n\n", $body) ?? $body);

        return mb_strlen($body) > $max ? mb_substr($body, 0, $max - 1) . '…' : $body;
    }

    /** @param array{count:int, first:string, last:string} $g */
    private static function span(array $g, bool $withCount = true): string
    {
        $first = self::when($g['first']);
        $last = self::when($g['last']);
        $range = $g['count'] > 1 && $first !== $last ? $first . ' → ' . $last : $last;

        return ($withCount && $g['count'] > 1 ? $g['count'] . ' fois · ' : '') . $range;
    }

    private static function when(mixed $raw, string $format = 'd/m H:i'): string
    {
        $t = strtotime((string) $raw);

        return $t ? date($format, $t) : '';
    }
}
