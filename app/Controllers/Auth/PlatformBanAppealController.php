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
use App\Services\Account\PlatformBanNotice;
use App\Services\Audit\AuditAction;
use App\Services\Audit\AuditService;
use App\Services\Email\EmailEvents;
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
                Session::flash('error', 'Ce lien a expiré ou est invalide. Vous pouvez en demander un nouveau.');
            }

            return $this->back();
        }

        $ban = $this->authorizedBan();
        $notice = null;
        $appeal = null;
        if ($ban !== null) {
            $notice = PlatformBanNotice::publicDetails($ban) + [
                'active' => empty($ban['lifted_at']) && (empty($ban['ends_at']) || $ban['ends_at'] > gmdate('Y-m-d H:i:s')),
            ];
            $saved = $this->appeals->find((int) $ban['id']);
            if ($saved !== null) {
                $appeal = [
                    'decision' => $saved['decision'],
                    'reply' => $saved['public_reply'],
                    'statement' => $saved['statement'],
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
            Session::flash('error', 'Le formulaire a expiré. Réessayez.');

            return $this->back();
        }

        $address = PlatformAccountBanRepository::normalizeEmail($request->inputString('email'));
        $generic = 'Si une décision correspond à cette adresse, un lien confidentiel vous sera envoyé.';
        Session::flash('success', $generic);
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
            ($this->email ??= Container::get(EmailService::class))->sendTemplated(
                EmailEvents::PLATFORM_BAN_APPEAL_LINK,
                'platform_ban_appeal_link',
                $address,
                'Consultez votre décision de modération · Athena',
                ['appealUrl' => url(self::PATH) . '?token=' . $token],
                forceImmediate: true,
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
            Session::flash('error', 'Votre accès au formulaire a expiré. Demandez un nouveau lien.');

            return $this->back();
        }
        $statement = trim($request->inputString('statement'));
        if (mb_strlen($statement) < 10 || mb_strlen($statement) > 4000) {
            Session::flash('error', 'Votre contestation doit contenir entre 10 et 4 000 caractères.');

            return $this->back();
        }

        if ($this->appeals->submit((int) $ban['id'], $statement)) {
            try {
                (new AuditService())->log(AuditAction::USER_BAN_APPEAL_SUBMITTED, 0, null, 'platform_account_ban', (int) $ban['id']);
            } catch (\Throwable $error) {
                error_log('[ban_appeal.audit] ' . $error->getMessage());
            }
            Session::flash('success', 'Votre contestation a été transmise. Vous pourrez consulter la réponse ici.');
        } else {
            Session::flash('success', 'Votre contestation a déjà été enregistrée.');
        }

        return $this->back();
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
