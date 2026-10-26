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
use App\Support\PayPalDonation;

/**
 * Page publique de dons (PayPal, même compte que les paiements de formules).
 * Le paiement par carte via Stripe (checkout, merci) reste en sommeil : la page ne le propose plus.
 */
final class AtakSupportController
{
    /** Montants proposés en euros. */
    public const PRESETS_EUR = [5, 10, 25, 50, 100];

    /** Paliers de la page de dons (montant en euros => nom et effet). Textes en français, traduits à l’affichage. */
    public const TIERS = [
        5 => ['name' => 'Ration de combat', 'line' => 'Un café pour les nuits de développement.'],
        10 => ['name' => 'Soirée d’opé', 'line' => 'Garde les serveurs allumés pendant vos missions.'],
        25 => ['name' => 'Équipe de feu', 'line' => 'Fait avancer une nouvelle app du téléphone.'],
        50 => ['name' => 'Section complète', 'line' => 'Un vrai coup d’accélérateur sur la feuille de route.'],
    ];

    public const DEFAULT_EUR = 10;

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
        $alreadyDonor = false;
        if ($userId > 0 && $this->donations->schemaReady()) {
            $alreadyDonor = $this->donations->hasPaidDonationForUser($userId);
        }

        // Même compte PayPal que la page de paiement des formules (Admin > Paiements).
        $paypalBase = PayPalDonation::accountUrl();
        $tiers = [];
        foreach (self::TIERS as $eur => $tier) {
            $tiers[] = $tier + ['eur' => $eur, 'url' => PayPalDonation::amountUrl($paypalBase, $eur * 100)];
        }

        return Response::view('layout.marketing', [
            'content' => 'atak.support',
            'title' => __('site.support_meta_title'),
            'meta_description' => __('site.support_meta_description'),
            'marketingActive' => 'soutenir',
            'marketingStyles' => [asset_url('assets/css/site-support.css') . '?v=1'],
            'tiers' => $tiers,
            'defaultEur' => self::DEFAULT_EUR,
            'minEur' => self::MIN_EUR,
            'maxEur' => self::MAX_EUR,
            'paypalBase' => $paypalBase,
            'paypalAmountable' => PayPalDonation::acceptsAmount($paypalBase),
            'alreadyDonor' => $alreadyDonor,
            'isLoggedIn' => $userId > 0,
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

    /** QR code du compte PayPal du site (généré par Athena, mis en cache une heure). */
    public function paypalQr(Request $request, array $params = []): Response
    {
        $qr = (new \App\Services\Qr\QrPngGenerator())->png(PayPalDonation::accountUrl(), 420, 16);
        if ($qr === null) {
            return (new Response())->setStatusCode(503)->setBody('');
        }

        return (new Response())
            ->setStatusCode(200)
            ->header('Content-Type', $qr['mime'])
            ->header('Cache-Control', 'public, max-age=3600')
            ->setBody($qr['body']);
    }
}
