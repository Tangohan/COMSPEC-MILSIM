<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\AtakDonationRepository;
use App\Services\Billing\AtakDonationFulfillmentService;
use App\Services\Billing\StripeCheckoutService;
use App\Repositories\UserRepository;

/**
 * Page publique de soutien au financement ATAK (montant au choix + badge donateur).
 */
final class AtakSupportController
{
    /** Montants proposés en euros. */
    public const PRESETS_EUR = [5, 10, 25, 50, 100];

    public const MIN_EUR = 3;

    public const MAX_EUR = 500;

    public function __construct(
        private AtakDonationRepository $donations,
        private StripeCheckoutService $stripe,
        private AtakDonationFulfillmentService $fulfillment,
        private UserRepository $users
    ) {}

    public function index(Request $request, array $params = []): Response
    {
        $userId = (int) (Session::get('user_id') ?? 0);
        $isLoggedIn = $userId > 0;
        $alreadyDonor = false;
        if ($isLoggedIn && $this->donations->schemaReady()) {
            $alreadyDonor = $this->donations->hasPaidDonationForUser($userId);
        }

        $flashError = Session::getFlash('atak_support_error');
        $flashInfo = Session::getFlash('atak_support_info');

        return Response::view('layout.main', [
            'title' => 'Soutenir ATAK',
            'content' => 'atak.support',
            'presets' => self::PRESETS_EUR,
            'minEur' => self::MIN_EUR,
            'maxEur' => self::MAX_EUR,
            'isLoggedIn' => $isLoggedIn,
            'alreadyDonor' => $alreadyDonor,
            'csrfToken' => Csrf::token(),
            'flashError' => is_string($flashError) ? $flashError : null,
            'flashInfo' => is_string($flashInfo) ? $flashInfo : null,
            'stripeReady' => (getenv('STRIPE_SECRET_KEY') ?: '') !== '',
            'schemaReady' => $this->donations->schemaReady(),
        ]);
    }

    public function checkout(Request $request, array $params = []): Response
    {
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('atak_support_error', 'La session a expiré. Merci de réessayer.');

            return Response::redirect(url('soutenir-atak'));
        }

        $userId = (int) (Session::get('user_id') ?? 0);
        if ($userId < 1) {
            Session::flash('atak_support_error', 'Connectez-vous pour contribuer et recevoir le badge donateur.');

            return $this->redirectToLoginForSupport();
        }

        if (!$this->donations->schemaReady()) {
            Session::flash('atak_support_error', 'Le financement ATAK n’est pas encore disponible. Réessayez un peu plus tard.');

            return Response::redirect(url('soutenir-atak'));
        }

        if ((getenv('STRIPE_SECRET_KEY') ?: '') === '') {
            Session::flash('atak_support_error', 'Le paiement sécurisé n’est pas configuré pour le moment.');

            return Response::redirect(url('soutenir-atak'));
        }

        $amountEur = $this->resolveAmountEur($request);
        if ($amountEur === null) {
            Session::flash(
                'atak_support_error',
                'Choisissez un montant entre ' . self::MIN_EUR . ' € et ' . self::MAX_EUR . ' €.'
            );

            return Response::redirect(url('soutenir-atak'));
        }

        $amountCents = (int) round($amountEur * 100);
        $tenantId = (int) (Session::get('tenant_id') ?? 0);
        $email = (string) (Session::get('email') ?? '');
        if ($email === '') {
            $user = $this->users->findById($userId);
            $email = is_array($user) ? (string) ($user['email'] ?? '') : '';
        }

        $successUrl = url('soutenir-atak/merci') . '?session_id={CHECKOUT_SESSION_ID}';
        $cancelUrl = url('soutenir-atak') . '?annule=1';

        try {
            $session = $this->stripe->createPaymentCheckoutSession(
                $amountCents,
                'eur',
                'Soutien ATAK — Athena',
                'Contribution au développement du module ATAK',
                $successUrl,
                $cancelUrl,
                $email !== '' ? $email : null,
                [
                    'kind' => 'atak_donation',
                    'user_id' => (string) $userId,
                    'tenant_id' => $tenantId > 0 ? (string) $tenantId : '',
                    'amount_cents' => (string) $amountCents,
                ]
            );
            $this->donations->createPending(
                $userId,
                $tenantId > 0 ? $tenantId : null,
                $amountCents,
                'eur',
                $session['id']
            );
        } catch (\Throwable $e) {
            Session::flash(
                'atak_support_error',
                'Impossible d’ouvrir le paiement pour le moment. Réessayez dans quelques minutes.'
            );

            return Response::redirect(url('soutenir-atak'));
        }

        return Response::redirect($session['url']);
    }

    public function thanks(Request $request, array $params = []): Response
    {
        $sessionId = trim((string) $request->query('session_id', ''));
        $donation = null;
        $fulfilled = false;
        if ($sessionId !== '') {
            try {
                $fulfilled = $this->fulfillment->fulfillByCheckoutSessionId($sessionId);
            } catch (\Throwable) {
                $fulfilled = false;
            }
            if ($this->donations->schemaReady()) {
                $donation = $this->donations->findByCheckoutSessionId($sessionId);
            }
        }

        $amountLabel = null;
        if (is_array($donation) && (int) ($donation['amount_cents'] ?? 0) > 0) {
            $amountLabel = number_format(((int) $donation['amount_cents']) / 100, 2, ',', ' ') . ' €';
        }

        return Response::view('layout.main', [
            'title' => 'Merci — Soutenir ATAK',
            'content' => 'atak.support_thanks',
            'donation' => $donation,
            'fulfilled' => $fulfilled,
            'amountLabel' => $amountLabel,
            'isPaid' => is_array($donation) && ($donation['status'] ?? '') === 'paid',
            'badgeGranted' => is_array($donation) && !empty($donation['badge_granted']),
        ]);
    }

    /** Mémorise le retour vers la page de soutien puis envoie vers la connexion. */
    public function loginGate(Request $request, array $params = []): Response
    {
        return $this->redirectToLoginForSupport();
    }

    private function redirectToLoginForSupport(): Response
    {
        // Même mécanisme que AuthMiddleware (évite les redirections ouvertes).
        Session::set('post_login_redirect', [
            'suffix' => 'soutenir-atak',
            'expires_at' => time() + 900,
        ]);
        Session::flash(
            'info',
            'Connectez-vous pour soutenir ATAK et recevoir le badge donateur. Vous reviendrez ensuite sur cette page.'
        );

        return Response::redirect(url('login'));
    }

    private function resolveAmountEur(Request $request): ?float
    {
        $preset = trim((string) $request->input('amount_preset', ''));
        $custom = trim((string) $request->input('amount_custom', ''));

        $value = null;
        if ($preset !== '' && $preset !== 'custom') {
            $value = (float) str_replace(',', '.', $preset);
        } elseif ($custom !== '') {
            $value = (float) str_replace(',', '.', $custom);
        }

        if ($value === null || !is_finite($value)) {
            return null;
        }
        $value = round($value, 2);
        if ($value < self::MIN_EUR || $value > self::MAX_EUR) {
            return null;
        }

        return $value;
    }
}
