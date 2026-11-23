<?php

declare(strict_types=1);

namespace App\Controllers\Auth;

use App\Core\Container;
use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\PlatformAccountBanRepository;
use App\Repositories\PlatformBanAppealRepository;
use App\Services\Account\PlatformBanAppealMailer;
use App\Services\Account\PlatformBanNotice;
use App\Services\Audit\AuditAction;
use App\Services\Audit\AuditService;
use App\Services\Email\EmailEvents;
use App\Services\Email\OpsMailDigestService;
use App\Services\EmailService;
use App\Services\Security\FileRateLimiter;

final class PlatformBanAppealController
{
    private const PATH = 'contester-sanction';
    private const SESSION_LINK = 'platform_ban_appeal_link';
    private const LINK_TTL = 3600;

    public function __construct(
        private ?PlatformAccountBanRepository $bans = null,
        private ?PlatformBanAppealRepository $appeals = null,
        private ?EmailService $email = null,
    ) {
        $this->bans ??= new PlatformAccountBanRepository();
        $this->appeals ??= new PlatformBanAppealRepository();
    }

    public function show(Request $request, array $params = []): Response
    {
        $token = $request->query('token');
        if ($token !== null) {
            Session::forget(self::SESSION_LINK);
            if (is_string($token) && preg_match('/^[a-f0-9]{64}$/D', $token) === 1 && $this->appeals->ready()) {
                $hash = hash('sha256', $token);
                if ($this->appeals->findValidLink($hash) !== null) {
                    Session::start();
                    Session::regenerate();
                    Session::set(self::SESSION_LINK, $hash);
                }
            }
            if (!Session::get(self::SESSION_LINK)) {
                Session::flash('error', __('moderation.invalid_link'));
            }

            return $this->back();
        }

        $ban = $this->authorizedBan();
        $notice = null;
        $appeal = null;
        if ($ban !== null) {
            $language = $this->noticeLanguage((string) $ban['email_norm']);
            $notice = PlatformBanNotice::publicDetails($ban, $language) + [
                'active' => self::isActive($ban),
            ];
            $saved = $this->appeals->find((int) $ban['id']);
            if ($saved !== null) {
                $appeal = [
                    'decision' => $saved['decision'],
                    'reply' => $saved['public_reply'],
                    'statement' => $saved['statement'],
                    'received' => PlatformBanNotice::appealProgress($saved, $language)['received'] ?? '',
                ];
            }
        }

        return Response::view('auth.platform-ban-appeal', [
            'notice' => $notice,
            'appeal' => $appeal,
            'error' => Session::getFlash('error'),
            'success' => Session::getFlash('success'),
        ])->header('Cache-Control', 'private, no-store')->header('Referrer-Policy', 'no-referrer');
    }

    public function requestLink(Request $request, array $params = []): Response
    {
        if (!Csrf::verify($request)) {
            Session::flash('error', __('moderation.expired_form'));

            return $this->back();
        }

        $address = PlatformAccountBanRepository::normalizeEmail($request->inputString('email'));
        Session::flash('success', __('moderation.request_received'));
        if (strlen($address) > 255 || !filter_var($address, FILTER_VALIDATE_EMAIL)) {
            return $this->back();
        }

        try {
            $limit = new FileRateLimiter();
            if ($limit->tooManyAttempts('ban-appeal:network:' . $request->ip(), 12, 3600, true)
                || $limit->tooManyAttempts('ban-appeal:address:' . $address, 3, 3600, true)
                || !$this->appeals->ready()) {
                return $this->back();
            }
            $ban = $this->bans->findActiveForEmail($address) ?? $this->bans->latestForEmail($address);
            if ($ban === null) {
                return $this->back();
            }

            $token = bin2hex(random_bytes(32));
            $this->appeals->storeLink((int) $ban['id'], hash('sha256', $token), time() + self::LINK_TTL);
            $language = $this->noticeLanguage($address);
            $this->email->sendTemplated(
                EmailEvents::PLATFORM_BAN_APPEAL_LINK,
                'platform_ban_appeal_link',
                $address,
                __('moderation.mail_subject', [], $language),
                [
                    'appealUrl' => url(self::PATH) . '?token=' . $token . '&lang=' . $language,
                    'appealLanguage' => $language,
                    'appealClosed' => !empty($ban['appeal_closed']),
                ],
                forceImmediate: true,
                locale: $language,
            );
        } catch (\Throwable) {
            // Même réponse lorsque l’adresse n’est pas reconnue ou l’envoi est indisponible.
            error_log('[ban_appeal] Impossible de traiter la demande de lien.');
        }

        return $this->back();
    }

    public function submit(Request $request, array $params = []): Response
    {
        $ban = Csrf::verify($request) ? $this->authorizedBan() : null;
        if ($ban === null) {
            Session::flash('error', __('moderation.expired_appeal'));

            return $this->back();
        }
        if (!empty($ban['appeal_closed'])) {
            Session::flash('error', __('moderation.appeal_closed_message'));

            return $this->back();
        }
        // Une sanction levée ou échue n'a plus rien à contester : pas de dossier inutile pour la modération.
        if (!self::isActive($ban)) {
            Session::flash('error', __('moderation.restriction_ended_message'));

            return $this->back();
        }
        try {
            if ((new FileRateLimiter())->tooManyAttempts('ban-appeal-submit:network:' . $request->ip(), 10, 3600, true)) {
                Session::flash('error', __('moderation.too_many_attempts'));

                return $this->back();
            }
        } catch (\Throwable) {
            // Limiteur indisponible : le dépôt reste limité à une contestation par décision.
        }
        $statement = trim($request->inputString('statement'));
        if (mb_strlen($statement) < 10 || mb_strlen($statement) > 4000) {
            Session::flash('error', __('moderation.invalid_statement'));

            return $this->back();
        }

        $submitted = $this->appeals->submit((int) $ban['id'], $statement);
        if ($submitted === null) {
            Session::flash('error', __('moderation.appeal_closed_message'));
        } elseif ($submitted) {
            try {
                (new AuditService())->log(AuditAction::USER_BAN_APPEAL_SUBMITTED, 0, null, 'platform_account_ban', (int) $ban['id']);
            } catch (\Throwable $error) {
                error_log('[ban_appeal.audit] ' . $error->getMessage());
            }
            try {
                Container::get(OpsMailDigestService::class)->enqueueBanAppeal();
            } catch (\Throwable) {
                error_log('[ban_appeal] Impossible d’ajouter la contestation au récapitulatif.');
            }
            (new PlatformBanAppealMailer($this->email))->notify($ban, PlatformBanAppealMailer::RECEIVED);
            Session::flash('success', __('moderation.appeal_received'));
        } else {
            Session::flash('success', __('moderation.appeal_already_received'));
        }

        return $this->back();
    }

    /** @param array<string, mixed> $ban */
    private static function isActive(array $ban): bool
    {
        return empty($ban['lifted_at']) && (empty($ban['ends_at']) || $ban['ends_at'] > gmdate('Y-m-d H:i:s'));
    }

    private function noticeLanguage(string $address): string
    {
        $email = $this->email ??= Container::get(EmailService::class);

        return $email->recipientLocale($address) === 'en' ? 'en' : 'fr';
    }

    /** @return array<string, mixed>|null */
    private function authorizedBan(): ?array
    {
        $hash = Session::get(self::SESSION_LINK);
        if (!is_string($hash) || !$this->appeals->ready()) {
            return null;
        }
        $link = $this->appeals->findValidLink($hash);
        if ($link === null) {
            Session::forget(self::SESSION_LINK);

            return null;
        }

        return $this->bans->findById((int) $link['ban_id']);
    }

    private function back(): Response
    {
        return Response::redirect(url(self::PATH), 303)
            ->header('Cache-Control', 'private, no-store')
            ->header('Referrer-Policy', 'no-referrer');
    }
}
