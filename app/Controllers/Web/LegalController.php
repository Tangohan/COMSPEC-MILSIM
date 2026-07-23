<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Container;
use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Services\EmailService;

/**
 * Pages légales publiques (hub documentation, formulaire d’exercice des droits).
 */
final class LegalController
{
    /** @return array<string, string> */
    private static function gdprRequestKindLabels(): array
    {
        return [
            'access' => __('legal.kind_access'),
            'rectification' => __('legal.kind_rectification'),
            'erasure' => __('legal.kind_erasure'),
            'restriction' => __('legal.kind_restriction'),
            'portability' => __('legal.kind_portability'),
            'objection' => __('legal.kind_objection'),
            'other' => __('legal.kind_other'),
        ];
    }

    /** Hub unique : CGU, CGV, mentions, données personnelles, cookies. */
    public function site(Request $request, array $params = []): Response
    {
        return Response::view('layout.legal', [
            'content' => 'legal.site',
            'title' => __('legal.hub_title'),
            'legalActivePage' => 'site',
        ]);
    }

    public function privacy(Request $request, array $params = []): Response
    {
        return Response::redirect(url('legal/site') . '#rgpd', 301);
    }

    public function cookies(Request $request, array $params = []): Response
    {
        return Response::redirect(url('legal/site') . '#cookies', 301);
    }

    public function legalNotice(Request $request, array $params = []): Response
    {
        return Response::redirect(url('legal/site') . '#mentions', 301);
    }

    public function terms(Request $request, array $params = []): Response
    {
        return Response::redirect(url('legal/site') . '#cgu', 301);
    }

    public function sales(Request $request, array $params = []): Response
    {
        return Response::redirect(url('legal/site') . '#cgv', 301);
    }

    public function gdprRequestForm(Request $request, array $params = []): Response
    {
        return Response::view('layout.legal', [
            'content' => 'legal.gdpr_request',
            'title' => __('legal.rights_title'),
            'legalActivePage' => 'droits',
            'gdprRequestKinds' => self::gdprRequestKindLabels(),
            'privacyInboxConfigured' => privacy_request_inbox_email() !== null,
        ]);
    }

    public function gdprRequestSubmit(Request $request, array $params = []): Response
    {
        if ($request->method() !== 'POST') {
            return Response::redirect(url('demande-donnees'));
        }
        if (!Csrf::validate((string) $request->input('_csrf_token'))) {
            Session::flash('error', __('legal.flash_session'));

            return Response::redirect(url('demande-donnees'));
        }

        $honeypot = trim((string) $request->input('company_website', ''));
        if ($honeypot !== '') {
            Session::flash('success', __('legal.flash_sent'));

            return Response::redirect(url('demande-donnees'));
        }

        $to = privacy_request_inbox_email();
        if ($to === null) {
            Session::flash('error', __('legal.flash_unavailable'));

            return Response::redirect(url('demande-donnees'));
        }

        $kinds = self::gdprRequestKindLabels();
        $kind = (string) $request->input('request_kind', '');
        if (!isset($kinds[$kind])) {
            Session::flash('error', __('legal.flash_kind'));

            return Response::redirect(url('demande-donnees'));
        }

        $email = trim((string) $request->input('from_email', ''));
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            Session::flash('error', __('legal.flash_email'));

            return Response::redirect(url('demande-donnees'));
        }

        $fullName = trim((string) $request->input('full_name', ''));
        if (strlen($fullName) > 160) {
            $fullName = substr($fullName, 0, 160);
        }

        $communityHint = trim((string) $request->input('community_hint', ''));
        if (strlen($communityHint) > 200) {
            $communityHint = substr($communityHint, 0, 200);
        }

        $message = trim((string) $request->input('message', ''));
        if (strlen($message) < 10) {
            Session::flash('error', 'Décrivez votre demande en quelques phrases (au moins dix caractères).');

            return Response::redirect(url('demande-donnees'));
        }
        if (strlen($message) > 4000) {
            $message = substr($message, 0, 4000);
        }

        $brand = email_brand_name();
        $subject = '[' . $brand . '] Demande relative aux données personnelles';
        $kindLabel = $kinds[$kind];

        $safeName = $fullName !== '' ? htmlspecialchars($fullName, ENT_QUOTES, 'UTF-8') : '<em>non indiqué</em>';
        $safeEmail = htmlspecialchars($email, ENT_QUOTES, 'UTF-8');
        $safeKind = htmlspecialchars($kindLabel, ENT_QUOTES, 'UTF-8');
        $safeCommunity = $communityHint !== '' ? htmlspecialchars($communityHint, ENT_QUOTES, 'UTF-8') : '<em>non indiqué</em>';
        $safeMessage = nl2br(htmlspecialchars($message, ENT_QUOTES, 'UTF-8'));

        $html = '<p style="margin:0 0 12px;font-family:Segoe UI,Roboto,Helvetica,Arial,sans-serif;font-size:14px;line-height:1.5;color:#0f172a;">'
            . 'Une personne a envoyé une demande depuis le portail <strong>' . htmlspecialchars($brand, ENT_QUOTES, 'UTF-8') . '</strong>.</p>'
            . '<table style="border-collapse:collapse;font-family:Segoe UI,Roboto,Helvetica,Arial,sans-serif;font-size:14px;color:#334155;">'
            . '<tr><td style="padding:4px 12px 4px 0;vertical-align:top;font-weight:600;">Type de demande</td><td>' . $safeKind . '</td></tr>'
            . '<tr><td style="padding:4px 12px 4px 0;vertical-align:top;font-weight:600;">Adresse e-mail de réponse</td><td>' . $safeEmail . '</td></tr>'
            . '<tr><td style="padding:4px 12px 4px 0;vertical-align:top;font-weight:600;">Nom affiché</td><td>' . $safeName . '</td></tr>'
            . '<tr><td style="padding:4px 12px 4px 0;vertical-align:top;font-weight:600;">Communauté concernée</td><td>' . $safeCommunity . '</td></tr>'
            . '</table>'
            . '<p style="margin:16px 0 8px;font-family:Segoe UI,Roboto,Helvetica,Arial,sans-serif;font-size:13px;font-weight:600;color:#0f172a;">Message</p>'
            . '<div style="font-family:Segoe UI,Roboto,Helvetica,Arial,sans-serif;font-size:14px;line-height:1.55;color:#334155;border-left:3px solid #10b981;padding-left:12px;">'
            . $safeMessage . '</div>';

        $text = "Demande données personnelles — {$brand}\n\n"
            . "Type : {$kindLabel}\n"
            . "E-mail de réponse : {$email}\n"
            . 'Nom : ' . ($fullName !== '' ? $fullName : '—') . "\n"
            . 'Communauté : ' . ($communityHint !== '' ? $communityHint : '—') . "\n\n"
            . "Message :\n" . $message . "\n";

        $emailService = Container::get(EmailService::class);
        $ok = $emailService->send(
            'privacy_rights_request',
            $to,
            $subject,
            $html,
            $text,
            null,
            $email,
            ['request_kind' => $kind]
        );

        if (!$ok) {
            $err = $emailService->getLastSendError();
            Session::flash('error', $err !== null && $err !== ''
                ? 'L’envoi a échoué. Merci de réessayer plus tard ou d’écrire directement à l’adresse indiquée dans les mentions légales.'
                : 'L’envoi a échoué. Merci de réessayer plus tard.');

            return Response::redirect(url('demande-donnees'));
        }

        Session::flash('success', 'Votre demande a été envoyée. Vous recevrez une réponse à l’adresse indiquée, dans les délais prévus par la réglementation.');

        return Response::redirect(url('demande-donnees'));
    }
}
