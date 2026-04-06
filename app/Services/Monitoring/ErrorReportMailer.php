<?php

declare(strict_types=1);

namespace App\Services\Monitoring;

use App\Core\Request;
use App\Core\Session;
use Throwable;

/**
 * Envoie un rapport d’exception / erreur fatale par e-mail (hors réponse HTTP client).
 */
final class ErrorReportMailer
{
    private const TRACE_HTML_MAX = 120000;

    public function reportThrowable(Throwable $e, ?string $requestId = null): void
    {
        $this->sendInternal(
            kind: 'exception',
            className: $e::class,
            message: $e->getMessage(),
            file: $e->getFile(),
            line: $e->getLine(),
            trace: $e->getTraceAsString(),
            requestId: $requestId
        );
    }

    /**
     * @param array{type:int,message:string,file:string,line:int}|null $err error_get_last()
     */
    public function reportFatal(?array $err, ?string $requestId = null): void
    {
        if ($err === null) {
            return;
        }
        $this->sendInternal(
            kind: 'fatal',
            className: 'PHP Fatal',
            message: (string) ($err['message'] ?? ''),
            file: (string) ($err['file'] ?? ''),
            line: (int) ($err['line'] ?? 0),
            trace: '',
            requestId: $requestId
        );
    }

    private function sendInternal(
        string $kind,
        string $className,
        string $message,
        string $file,
        int $line,
        string $trace,
        ?string $requestId
    ): void {
        if (!filter_var((string) env('ERROR_ALERT_ENABLED', true), FILTER_VALIDATE_BOOLEAN)) {
            return;
        }
        $to = trim((string) env('ERROR_ALERT_EMAIL', ''));
        if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return;
        }

        $ip = $this->clientIp();
        $fingerprint = $kind . '|' . $className . '|' . $file . '|' . $line . '|' . substr($message, 0, 200);
        $dedupeKey = $fingerprint . '|' . $ip;
        $throttle = ErrorAlertThrottle::fromEnv();
        if ($throttle->isThrottled($dedupeKey)) {
            return;
        }

        $path = Request::normalizePathFromServer();
        $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
        $uri = (string) ($_SERVER['REQUEST_URI'] ?? '');
        $accept = (string) ($_SERVER['HTTP_ACCEPT'] ?? '');
        $sanitizedUri = $this->sanitizeUri($uri);

        $userId = null;
        $tenantId = null;
        try {
            Session::start();
            $userId = Session::get('user_id');
            $tenantId = Session::get('tenant_id');
        } catch (\Throwable) {
        }

        $rid = $requestId ?? (string) (getenv('REQUEST_ID') ?: ($_ENV['REQUEST_ID'] ?? ''));
        $appEnv = (string) env('APP_ENV', '');

        $text = $this->buildPlainTextBody(
            $kind,
            $className,
            $message,
            $file,
            $line,
            $trace,
            $path,
            $method,
            $sanitizedUri,
            $ip,
            $rid,
            $appEnv,
            $userId,
            $tenantId,
            $accept
        );

        $html = $this->buildHtmlBody(
            $kind,
            $className,
            $message,
            $file,
            $line,
            $trace,
            $path,
            $method,
            $sanitizedUri,
            $ip,
            $rid,
            $appEnv,
            $userId,
            $tenantId,
            $accept
        );

        $pathForSubject = $path !== '' ? $path : '/';
        $pathShort = mb_strlen($pathForSubject) > 72 ? mb_substr($pathForSubject, 0, 69) . '…' : $pathForSubject;
        $brand = function_exists('email_brand_name') ? email_brand_name() : 'Application';
        $subject = '[' . $brand . '] Incident technique — ' . $pathShort;

        try {
            $mailer = \App\Core\Container::get(\App\Services\EmailService::class);
            $mailer->send(
                'error_alert',
                $to,
                $subject,
                $html,
                $text,
                is_numeric($tenantId) ? (int) $tenantId : null,
                null,
                ['kind' => $kind, 'path' => $path]
            );
        } catch (\Throwable) {
            // ne pas masquer l’erreur d’origine
        }
    }

    private function kindLabelFr(string $kind): string
    {
        return match ($kind) {
            'fatal' => 'Erreur fatale PHP',
            default => 'Exception non gérée',
        };
    }

    /**
     * @param mixed $userId
     * @param mixed $tenantId
     */
    private function buildPlainTextBody(
        string $kind,
        string $className,
        string $message,
        string $file,
        int $line,
        string $trace,
        string $path,
        string $method,
        string $sanitizedUri,
        string $ip,
        string $rid,
        string $appEnv,
        mixed $userId,
        mixed $tenantId,
        string $accept
    ): string {
        $kindFr = $this->kindLabelFr($kind);
        $lines = [
            '══════════════════════════════════════',
            'RAPPORT D’INCIDENT (équipe technique)',
            '══════════════════════════════════════',
            '',
            'Bonjour,',
            '',
            'L’application a rencontré un dysfonctionnement pendant le traitement d’une requête.',
            'Ce message est envoyé automatiquement à la boîte configurée (ERROR_ALERT_EMAIL).',
            'Il sert à diagnostiquer la cause : consultez le résumé ci-dessous, puis les journaux',
            'serveur (storage/logs) et l’historique des déploiements si l’incident se répète.',
            '',
            'Les paramètres sensibles dans l’adresse de la requête ont été partiellement masqués.',
            '',
            '────────── Résumé ──────────',
            'Nature : ' . $kindFr,
            'Message : ' . ($message !== '' ? $message : '(aucun message détaillé)'),
            '',
            '────────── Requête concernée ──────────',
            'Méthode HTTP : ' . $method,
            'Chemin applicatif : ' . ($path !== '' ? $path : '—'),
            'Adresse demandée (sanitisée) : ' . $sanitizedUri,
            'Identifiant de corrélation : ' . ($rid !== '' ? $rid : '—'),
            'Environnement (APP_ENV) : ' . ($appEnv !== '' ? $appEnv : '—'),
            'Adresse IP du client : ' . $ip,
            'En-tête Accept : ' . ($accept !== '' ? $accept : '—'),
            'Compte connecté (identifiant interne) : ' . ($userId !== null && $userId !== '' ? (string) $userId : '—'),
            'Communauté active (identifiant interne) : ' . ($tenantId !== null && $tenantId !== '' ? (string) $tenantId : '—'),
            '',
            '────────── Détail technique ──────────',
            'Classe ou origine : ' . $className,
            'Fichier : ' . $file,
            'Ligne : ' . (string) $line,
            '',
            '────────── Pile d’appels (trace) ──────────',
            $trace !== '' ? $trace : '(non disponible pour ce type d’erreur — consultez le journal PHP / serveur web.)',
            '',
            '— Fin du rapport —',
        ];

        return implode("\n", $lines);
    }

    /**
     * @param mixed $userId
     * @param mixed $tenantId
     */
    private function buildHtmlBody(
        string $kind,
        string $className,
        string $message,
        string $file,
        int $line,
        string $trace,
        string $path,
        string $method,
        string $sanitizedUri,
        string $ip,
        string $rid,
        string $appEnv,
        mixed $userId,
        mixed $tenantId,
        string $accept
    ): string {
        if (!function_exists('email_html_layout') || !function_exists('email_html_callout')) {
            $fallback = $this->buildPlainTextBody(
                $kind,
                $className,
                $message,
                $file,
                $line,
                $trace,
                $path,
                $method,
                $sanitizedUri,
                $ip,
                $rid,
                $appEnv,
                $userId,
                $tenantId,
                $accept
            );

            return '<pre style="font-family:Consolas,monospace;font-size:13px;line-height:1.5;color:#334155;white-space:pre-wrap;">'
                . htmlspecialchars($fallback, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
                . '</pre>';
        }

        $kindFr = $this->kindLabelFr($kind);
        $h = static fn (string $s): string => htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        $intro = '<p style="margin:0 0 14px;">Bonjour,</p>'
            . '<p style="margin:0 0 14px;">Une <strong>erreur technique</strong> s’est produite pendant le traitement d’une requête sur le site. '
            . 'Ce courriel vous permet d’en comprendre le <strong>contexte</strong> et de prioriser une intervention '
            . '(correctif, données, configuration ou charge serveur).</p>'
            . '<p style="margin:0 0 14px;">En pratique : notez l’<strong>identifiant de corrélation</strong> ci-dessous s’il est renseigné, '
            . 'vérifiez les <strong>journaux applicatifs</strong> au même horaire, et reproduisez l’action si possible sur un environnement de test.</p>';

        $badge = '<p style="margin:0 0 12px;">'
            . '<span style="display:inline-block;padding:5px 12px;border-radius:8px;background-color:#fef2f2;color:#9f1239;font-size:12px;font-weight:700;letter-spacing:0.04em;">'
            . $h($kindFr) . '</span></p>';

        $msgDisplay = $message !== '' ? $h($message) : '<em style="color:#64748b;">Aucun message détaillé fourni par le moteur.</em>';
        $summaryBox = '<div style="padding:16px 18px;background-color:#fffbeb;border-radius:10px;border:1px solid #fde68a;margin:0 0 20px;">'
            . '<p style="margin:0 0 8px;font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:0.08em;color:#b45309;">Message signalé</p>'
            . '<p style="margin:0;font-size:16px;line-height:1.55;color:#0f172a;font-weight:600;">' . $msgDisplay . '</p>'
            . '</div>';

        $metaRows = [
            ['Méthode HTTP', $h($method)],
            ['Chemin applicatif', $h($path !== '' ? $path : '—')],
            ['Adresse demandée (sanitisée)', $h($sanitizedUri !== '' ? $sanitizedUri : '—')],
            ['Identifiant de corrélation', $h($rid !== '' ? $rid : '—')],
            ['Environnement', $h($appEnv !== '' ? $appEnv : '—')],
            ['Adresse IP du client', $h($ip)],
            ['En-tête Accept', $h($accept !== '' ? $accept : '—')],
            ['Compte connecté (réf. interne)', $userId !== null && $userId !== '' ? $h((string) $userId) : '—'],
            ['Communauté active (réf. interne)', $tenantId !== null && $tenantId !== '' ? $h((string) $tenantId) : '—'],
        ];

        $metaHtml = '<p style="margin:0 0 12px;font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:0.08em;color:#64748b;">Contexte de la requête</p>'
            . '<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="border-collapse:collapse;font-size:14px;color:#334155;">';
        foreach ($metaRows as [$label, $val]) {
            $metaHtml .= '<tr>'
                . '<td style="padding:10px 12px 10px 0;vertical-align:top;width:42%;border-bottom:1px solid #e2e8f0;color:#64748b;font-weight:600;">' . $h($label) . '</td>'
                . '<td style="padding:10px 0;vertical-align:top;border-bottom:1px solid #e2e8f0;word-break:break-word;">' . $val . '</td>'
                . '</tr>';
        }
        $metaHtml .= '</table>';

        $tech = '<p style="margin:24px 0 12px;font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:0.08em;color:#64748b;">Détail technique</p>'
            . '<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="border-collapse:collapse;font-size:13px;color:#334155;font-family:Consolas,\'Courier New\',monospace;">'
            . '<tr><td style="padding:8px 0;color:#64748b;width:140px;">Classe</td><td style="padding:8px 0;word-break:break-all;">' . $h($className) . '</td></tr>'
            . '<tr><td style="padding:8px 0;color:#64748b;">Fichier</td><td style="padding:8px 0;word-break:break-all;">' . $h($file) . '</td></tr>'
            . '<tr><td style="padding:8px 0;color:#64748b;">Ligne</td><td style="padding:8px 0;">' . $h((string) $line) . '</td></tr>'
            . '</table>';

        $traceHtml = '';
        if ($trace !== '') {
            $traceBody = $trace;
            $truncated = false;
            if (strlen($traceBody) > self::TRACE_HTML_MAX) {
                $traceBody = substr($traceBody, 0, self::TRACE_HTML_MAX);
                $truncated = true;
            }
            $traceHtml = '<p style="margin:24px 0 10px;font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:0.08em;color:#64748b;">Pile d’appels</p>'
                . '<p style="margin:0 0 8px;font-size:13px;color:#64748b;line-height:1.5;">Ordre d’exécution au moment de l’erreur (la ligne du haut est la plus récente).</p>'
                . '<div style="max-height:480px;overflow:auto;padding:14px 16px;background-color:#0f172a;border-radius:10px;border:1px solid #1e293b;">'
                . '<pre style="margin:0;font-family:Consolas,\'Courier New\',monospace;font-size:11px;line-height:1.45;color:#e2e8f0;white-space:pre-wrap;word-break:break-word;">'
                . $h($traceBody)
                . '</pre></div>';
            if ($truncated) {
                $traceHtml .= '<p style="margin:10px 0 0;font-size:12px;color:#b45309;">La trace a été tronquée dans ce message pour limiter la taille du courriel ; la version complète figure dans la partie texte ou sur le serveur.</p>';
            }
        } else {
            $traceHtml = email_html_callout(
                '<p style="margin:0;font-size:14px;line-height:1.55;">Pour ce type d’erreur, la pile d’appels n’est pas jointe ici. '
                . 'Consultez le journal PHP ou du serveur web au même horaire pour le détail complet.</p>',
                'warning'
            );
        }

        $confidential = email_html_callout(
            '<p style="margin:0 0 8px;"><strong>Données sensibles.</strong> Ce message peut contenir des chemins serveur ou des indices sur la configuration. '
            . 'Ne le diffusez pas publiquement (réseaux sociaux, tickets publics).</p>'
            . '<p style="margin:0;">Si plusieurs alertes identiques arrivent en peu de temps, vérifiez aussi la charge, les migrations récentes et la disponibilité de la base de données.</p>',
            'info'
        );

        $bodyHtml = $intro . $badge . $summaryBox . $metaHtml . $tech . $traceHtml . $confidential;

        $heading = 'Incident technique sur le site';
        $preheader = $kindFr . ' — ' . ($message !== '' ? mb_substr($message, 0, 90) : $path);

        return email_html_layout(
            $preheader,
            $heading,
            $bodyHtml,
            [
                'accent' => 'rose',
                'footer_note' => 'Message généré automatiquement — ne répondez pas à cette adresse. '
                    . 'Pour la maintenance, utilisez vos canaux internes ou la documentation d’exploitation.',
            ]
        );
    }

    private function clientIp(): string
    {
        $ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '0';
        if (is_string($ip) && str_contains($ip, ',')) {
            return trim(explode(',', $ip)[0]);
        }

        return is_string($ip) ? $ip : '0';
    }

    private function sanitizeUri(string $uri): string
    {
        $parts = parse_url($uri);
        if (!is_array($parts)) {
            return $uri;
        }
        $q = $parts['query'] ?? '';
        if ($q === '') {
            return $uri;
        }
        parse_str($q, $params);
        $redacted = ['password', 'password_confirmation', 'token', '_csrf_token', 'csrf'];
        foreach ($redacted as $k) {
            if (isset($params[$k])) {
                $params[$k] = '[omis]';
            }
        }
        $parts['query'] = http_build_query($params);

        return $this->buildUri($parts);
    }

    /** @param array<string, mixed> $parts */
    private function buildUri(array $parts): string
    {
        $s = '';
        if (!empty($parts['scheme'])) {
            $s .= $parts['scheme'] . '://';
        }
        if (!empty($parts['host'])) {
            $s .= $parts['host'];
        }
        if (!empty($parts['path'])) {
            $s .= $parts['path'];
        }
        if (!empty($parts['query'])) {
            $s .= '?' . $parts['query'];
        }

        return $s !== '' ? $s : (string) ($parts['path'] ?? '/');
    }
}
