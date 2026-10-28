<?php

declare(strict_types=1);

namespace App\Controllers\Admin\System;

use App\Core\Container;
use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\PaymentLedgerRepository;
use App\Repositories\PlanPaymentRequestRepository;
use App\Repositories\PlatformSettingsRepository;
use App\Services\Billing\BillingProvider;
use App\Services\Billing\DonorService;
use App\Services\Billing\PaymentAnalyticsService as Analytics;
use App\Services\Billing\PlanPaymentService;
use App\Support\ManualBilling;
use App\Support\PayPalDonation;

/**
 * /admin/paiements : suivi de tout l'argent reçu par Athena.
 * Vues : aperçu (indicateurs), paiements (registre et décisions), abonnements (récurrence, relances, renouvellements),
 * dons (dons, donateurs et communautés soutien), réglages (PayPal, formules en vente).
 */
final class SystemPlanPaymentsController
{
    private const BASE = 'admin/paiements';
    private const VIEWS = ['apercu', 'paiements', 'abonnements', 'dons', 'reglages'];

    private ?PaymentLedgerRepository $ledgerRepo = null;

    private function service(): PlanPaymentService
    {
        return Container::get(PlanPaymentService::class);
    }

    private function repo(): PlanPaymentRequestRepository
    {
        return Container::get(PlanPaymentRequestRepository::class);
    }

    private function ledger(): PaymentLedgerRepository
    {
        return $this->ledgerRepo ??= new PaymentLedgerRepository();
    }

    public function index(Request $request, array $params = []): Response
    {
        $view = (string) $request->query('vue', '');
        $focus = strtoupper(trim((string) $request->query('ref', '')));
        if ($view === '' && $focus !== '') {
            $view = 'paiements';
        }
        if (!in_array($view, self::VIEWS, true)) {
            $view = 'apercu';
        }
        $service = $this->service();
        $error = $service->schemaReady() ? null : 'Le registre des paiements n’existe pas encore : lancez la mise à jour de la base (run-migrations).';

        $ledger = $this->safe(fn () => $this->ledger()->ledger(), []);
        $offers = $service->offers();
        $prices = [];
        $planNames = [];
        foreach ($offers as $o) {
            $prices[$o['slug']] = ['monthly' => $o['monthly'], 'yearly' => $o['yearly']];
            $planNames[$o['slug']] = $o['name'];
        }
        $subscriptions = $this->safe(fn () => $service->activeManualSubscriptions(), []);
        $history = Analytics::tenantHistory($ledger);
        $openByTenant = [];
        $payerByTenant = [];
        foreach ($ledger as $e) {
            $tid = (int) ($e['tenant_id'] ?? 0);
            if ($tid > 0 && ($e['source'] ?? '') === 'plan') {
                if (in_array($e['status'], ['awaiting', 'declared'], true) && !isset($openByTenant[$tid])) {
                    $openByTenant[$tid] = $e;
                }
                if (!isset($payerByTenant[$tid]) && ($e['payer_email'] ?? '') !== '') {
                    $payerByTenant[$tid] = (string) $e['payer_email'];
                }
            }
        }
        foreach ($subscriptions as &$s) {
            $tid = (int) $s['tenant_id'];
            $s['history'] = $history[$tid] ?? ['periods' => 0, 'total_cents' => 0, 'first_at' => null];
            $s['open'] = $openByTenant[$tid] ?? null;
            $s['payer_email'] = $payerByTenant[$tid] ?? '';
            $p = $prices[$s['plan_slug']] ?? ['monthly' => null, 'yearly' => null];
            $s['price_cents'] = $s['interval'] === 'yearly' ? $p['yearly'] : $p['monthly'];
        }
        unset($s);
        $activeIds = array_map(static fn ($s) => (int) $s['tenant_id'], $subscriptions);
        $apiSubs = [];
        foreach ($this->safe(fn () => Container::get(\App\Repositories\TenantRepository::class)->listOverviewForPlatform(), []) as $t) {
            if (!empty($t['stripe_subscription_id'])) {
                $apiSubs[] = $t;
            }
        }

        // Registre filtré (vue Paiements).
        $f = [
            'source' => (string) $request->query('nature', ''),
            'status' => (string) $request->query('statut', ''),
            'q' => trim((string) $request->query('q', '')),
            'days' => max(0, (int) $request->query('periode', 0)),
        ];
        $filtered = Analytics::filter($ledger, $f['source'], $f['status'], $f['q'], $f['days']);
        $page = array_slice($filtered, 0, 150);
        $events = $this->safe(fn () => $this->ledger()->eventsFor(
            array_map(static fn ($e) => (int) $e['id'], array_filter($page, static fn ($e) => in_array($e['source'], ['plan', 'heart'], true))),
            array_map(static fn ($e) => (int) $e['id'], array_filter($page, static fn ($e) => $e['source'] === 'donation')),
            array_map(static fn ($e) => (int) $e['id'], array_filter($page, static fn ($e) => $e['source'] === 'stripe_donation'))
        ), []);
        $statusCounts = [];
        foreach ($ledger as $e) {
            $statusCounts[$e['status']] = ($statusCounts[$e['status']] ?? 0) + 1;
        }

        $donorSvc = new DonorService($this->ledger());
        $overrides = $this->safe(fn () => $donorSvc->overrides(), ['revoked' => [], 'granted' => []]);
        $donors = DonorService::donorsFromLedger($ledger, [], $overrides['granted']);
        $supporters = DonorService::supportersFromLedger($ledger, array_merge($activeIds, array_map(static fn ($t) => (int) $t['id'], array_filter($apiSubs, static fn ($t) => in_array($t['subscription_status'], ['active', 'trialing'], true)))));
        $tenantNames = [];
        foreach ($ledger as $e) {
            if ((int) ($e['tenant_id'] ?? 0) > 0) {
                $tenantNames[(int) $e['tenant_id']] = (string) $e['tenant_name'];
            }
        }
        foreach ($subscriptions as $s) {
            $tenantNames[(int) $s['tenant_id']] = (string) $s['tenant_name'];
        }

        return Response::view('layout.main', [
            'title' => 'Paiements et dons',
            'content' => 'admin.system.plan_payments',
            'isPlatformAdminShell' => true,
            'backOfficePageCss' => ['admin-payments.css', 'donor-marks.css'],
            'payView' => $view,
            'payError' => $error,
            'payFocus' => $focus,
            'payLedger' => $ledger,
            'payRows' => $page,
            'payRowsTotal' => count($filtered),
            'payFilters' => $f,
            'payStatusCounts' => $statusCounts,
            'payEvents' => $events,
            'payRecentEvents' => $this->safe(fn () => $this->ledger()->recentEvents(25), []),
            'payKpis' => Analytics::kpis($ledger, $subscriptions, $prices),
            'payMonthly' => Analytics::monthly($ledger),
            'payLapsed' => Analytics::lapsed($ledger, $activeIds),
            'paySubscriptions' => $subscriptions,
            'payApiSubs' => $apiSubs,
            'payOffers' => $offers,
            'payPlanNames' => $planNames,
            'payDonors' => $donors,
            'payRevoked' => $overrides['revoked'],
            'payHidden' => $overrides['hidden'] ?? [],
            'payBoardCount' => count(DonorService::board()),
            'paySupporters' => $supporters,
            'payTenantNames' => $tenantNames,
            'payDonorCacheAt' => DonorService::cached()['updated_at'],
            'payPaypalUrl' => $service->paypalBaseUrl(),
            'payProviders' => [
                'paypal_api' => BillingProvider::paypalConfigured(),
                'paypal_webhook' => trim((string) (getenv('PAYPAL_WEBHOOK_ID') ?: '')) !== '',
                'stripe' => BillingProvider::stripeConfigured(),
                'webhook_url' => url('api/paypal/webhook'),
            ],
            'csrfToken' => Csrf::token(),
        ]);
    }

    // ------------------------------------------------------------------ décisions sur une demande

    public function activate(Request $request, array $params = []): Response
    {
        return $this->decide($request, $params, true);
    }

    public function reject(Request $request, array $params = []): Response
    {
        return $this->decide($request, $params, false);
    }

    public function cancel(Request $request, array $params = []): Response
    {
        return $this->act($request, function (int $actor) use ($request, $params) {
            $r = $this->service()->cancelByAdmin((int) ($params['id'] ?? 0), $actor ?: null, $this->note($request));

            return [$r, 'Demande annulée.'];
        });
    }

    public function refund(Request $request, array $params = []): Response
    {
        return $this->act($request, function (int $actor) use ($request, $params) {
            $r = $this->service()->refund((int) ($params['id'] ?? 0), $actor ?: null, $this->note($request), (string) $request->input('end_plan', '0') === '1');

            return [$r, !empty($r['plan_ended'])
                ? 'Paiement marqué remboursé et formule arrêtée. La personne est prévenue par e-mail.'
                : 'Paiement marqué remboursé. La personne est prévenue par e-mail.'];
        });
    }

    public function remind(Request $request, array $params = []): Response
    {
        return $this->act($request, function (int $actor) use ($request, $params) {
            return [$this->service()->remindPayer((int) ($params['id'] ?? 0), $actor ?: null, $this->note($request)), 'Relance envoyée par e-mail.'];
        });
    }

    private function note(Request $request): string
    {
        return mb_substr(trim((string) $request->input('admin_note', '')), 0, 500);
    }

    public function addNote(Request $request, array $params = []): Response
    {
        return $this->act($request, function (int $actor) use ($request, $params) {
            $row = $this->repo()->findById((int) ($params['id'] ?? 0));
            $note = $this->note($request);
            if ($row === null || $note === '') {
                return [['ok' => false, 'error' => 'Note vide ou demande introuvable.'], ''];
            }
            $this->ledger()->log('note', ['request_id' => (int) $row['id'], 'tenant_id' => (int) ($row['tenant_id'] ?? 0)], $actor ?: null, $note);

            return [['ok' => true], 'Note ajoutée au suivi.'];
        });
    }

    // ------------------------------------------------------------------ abonnements

    public function endPlan(Request $request, array $params = []): Response
    {
        return $this->act($request, function (int $actor) use ($request, $params) {
            return [$this->service()->endPlan((int) ($params['tenantId'] ?? 0), $actor ?: null, $this->note($request)), 'Formule arrêtée : la communauté revient à la formule gratuite.'];
        }, 'abonnements');
    }

    public function extend(Request $request, array $params = []): Response
    {
        return $this->act($request, function (int $actor) use ($request, $params) {
            $r = $this->service()->extendPlan((int) ($params['tenantId'] ?? 0), (string) $request->input('span', ''), $actor ?: null, $this->note($request));

            return [$r, 'Formule prolongée jusqu’au ' . (!empty($r['ends_at']) ? date('d/m/Y', (int) strtotime((string) $r['ends_at'])) : '—') . '. La personne est prévenue.'];
        }, 'abonnements');
    }

    public function renew(Request $request, array $params = []): Response
    {
        return $this->act($request, function (int $actor) use ($params) {
            $r = $this->service()->createRenewal((int) ($params['tenantId'] ?? 0), $actor ?: null);

            return [$r, !empty($r['reused'])
                ? 'Lien de la demande ' . ($r['reference'] ?? '') . ' renvoyé au payeur.'
                : 'Demande de renouvellement ' . ($r['reference'] ?? '') . ' créée et envoyée au payeur.'];
        }, 'abonnements');
    }

    // ------------------------------------------------------------------ dons et donateurs

    public function recordDonation(Request $request, array $params = []): Response
    {
        return $this->act($request, function (int $actor) use ($request) {
            $email = strtolower(trim((string) $request->input('donor_email', '')));
            $amount = (int) round(((float) str_replace(',', '.', (string) $request->input('amount', '0'))) * 100);
            $channel = (string) $request->input('channel', 'paypal');
            if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
                return [['ok' => false, 'error' => 'Adresse e-mail du donateur invalide.'], ''];
            }
            if ($amount < 1 || $amount > 10000000) {
                return [['ok' => false, 'error' => 'Montant invalide.'], ''];
            }
            $received = trim((string) $request->input('received_at', ''));
            $ts = $received !== '' ? strtotime($received) : time();
            $userId = 0;
            $tenantId = (int) $request->input('tenant_id', 0);
            if ($email !== '') {
                $m = $this->ledger()->membershipsForEmails([$email])[$email] ?? [];
                $userId = (int) ($m[0]['id'] ?? 0);
            }
            $id = $this->ledger()->createDonation([
                'user_id' => $userId,
                'tenant_id' => $tenantId,
                'donor_name' => trim((string) $request->input('donor_name', '')),
                'donor_email' => $email,
                'amount_cents' => $amount,
                'channel' => in_array($channel, ['paypal', 'virement', 'especes', 'autre'], true) ? $channel : 'autre',
                'transaction_ref' => strtoupper(preg_replace('/[^A-Za-z0-9\-]/', '', (string) $request->input('transaction_ref', '')) ?? ''),
                'note' => trim((string) $request->input('note', '')),
                'is_public' => (string) $request->input('is_public', '0') === '1',
                'received_at' => gmdate('Y-m-d H:i:s', $ts !== false ? $ts : time()),
                'recorded_by' => $actor,
            ]);
            $this->ledger()->log('donation_recorded', ['donation_id' => $id, 'tenant_id' => $tenantId, 'user_id' => $userId], $actor ?: null, '', ['amount_cents' => $amount, 'channel' => $channel]);
            $this->service()->refreshDonors();

            return [['ok' => true], 'Don de ' . ManualBilling::formatAmount($amount) . ' enregistré' . ($email !== '' ? ' : rôle donateur attribué à ' . $email . '.' : '.')];
        }, 'dons');
    }

    public function refundDonation(Request $request, array $params = []): Response
    {
        return $this->act($request, function (int $actor) use ($request, $params) {
            $id = (int) ($params['id'] ?? 0);
            if (!$this->ledger()->refundDonation($id)) {
                return [['ok' => false, 'error' => 'Don introuvable ou déjà remboursé.'], ''];
            }
            $this->ledger()->log('donation_refunded', ['donation_id' => $id], $actor ?: null, $this->note($request));
            $this->service()->refreshDonors();

            return [['ok' => true], 'Don marqué remboursé. Le rôle donateur est recalculé.'];
        }, 'dons');
    }

    public function stripeDonationCancel(Request $request, array $params = []): Response
    {
        return $this->decideStripeDonation($request, $params, 'cancelled');
    }

    public function stripeDonationReject(Request $request, array $params = []): Response
    {
        return $this->decideStripeDonation($request, $params, 'rejected');
    }

    public function stripeDonationMarkPaid(Request $request, array $params = []): Response
    {
        return $this->decideStripeDonation($request, $params, 'paid');
    }

    public function stripeDonationRefund(Request $request, array $params = []): Response
    {
        return $this->decideStripeDonation($request, $params, 'refunded');
    }

    /** Don ATAK Stripe : annulé / refusé / encaissé à la main / remboursé, journalisé et audité. */
    private function decideStripeDonation(Request $request, array $params, string $to): Response
    {
        return $this->act($request, function (int $actor) use ($request, $params, $to) {
            $id = (int) ($params['id'] ?? 0);
            $row = $this->ledger()->findStripeDonation($id);
            if ($row === null) {
                return [['ok' => false, 'error' => 'Don introuvable.'], ''];
            }
            $note = $this->note($request);
            if ($to === 'rejected' && $note === '') {
                return [['ok' => false, 'error' => 'Indiquez la raison du refus.'], ''];
            }
            if (!$this->ledger()->decideStripeDonation($id, $to, $actor ?: null, $note)) {
                return [['ok' => false, 'error' => 'Ce don a déjà été traité.'], ''];
            }
            $kinds = ['cancelled' => 'donation_cancelled', 'rejected' => 'donation_rejected', 'paid' => 'donation_marked_paid', 'refunded' => 'donation_refunded'];
            $ids = ['atak_donation_id' => $id, 'tenant_id' => (int) ($row['tenant_id'] ?? 0), 'user_id' => (int) ($row['user_id'] ?? 0)];
            $this->ledger()->log($kinds[$to], $ids, $actor ?: null, $note, ['from' => (string) $row['status'], 'amount_cents' => (int) $row['amount_cents']]);
            try {
                (new \App\Services\Audit\AuditService())->log(
                    'platform.atak_donation_' . $to,
                    (int) ($row['tenant_id'] ?? 0) > 0 ? (int) $row['tenant_id'] : null,
                    $actor ?: null,
                    'atak_donation',
                    $id,
                    null,
                    'DON-S' . str_pad((string) $id, 5, '0', STR_PAD_LEFT) . ' ' . $row['status'] . ' → ' . $to
                );
            } catch (\Throwable) {
            }
            if (in_array($to, ['paid', 'refunded'], true)) {
                $this->service()->refreshDonors();
            }

            return [['ok' => true], [
                'cancelled' => 'Don annulé.',
                'rejected' => 'Don refusé.',
                'paid' => 'Don marqué encaissé. Le rôle donateur est recalculé.',
                'refunded' => 'Don marqué remboursé. Le rôle donateur est recalculé.',
            ][$to]];
        });
    }

    public function donorGrant(Request $request, array $params = []): Response
    {
        return $this->act($request, function (int $actor) use ($request) {
            $email = strtolower(trim((string) $request->input('email', '')));
            if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
                return [['ok' => false, 'error' => 'Adresse e-mail invalide.'], ''];
            }
            $svc = new DonorService($this->ledger());
            $svc->grantManually($email, (string) $request->input('tier', 'soutien'));
            $this->ledger()->log('donor_granted', [], $actor ?: null, $this->note($request), ['email' => $email, 'tier' => (string) $request->input('tier', 'soutien')]);
            $this->service()->refreshDonors();

            return [['ok' => true], 'Rôle donateur attribué à ' . $email . '.'];
        }, 'dons');
    }

    public function donorRevoke(Request $request, array $params = []): Response
    {
        return $this->act($request, function (int $actor) use ($request) {
            $email = strtolower(trim((string) $request->input('email', '')));
            if ($email === '') {
                return [['ok' => false, 'error' => 'Adresse manquante.'], ''];
            }
            (new DonorService($this->ledger()))->revoke($email);
            $this->ledger()->log('donor_revoked', [], $actor ?: null, $this->note($request), ['email' => $email]);
            $this->service()->refreshDonors();

            return [['ok' => true], 'Rôle donateur retiré à ' . $email . ' (ses dons restent enregistrés).'];
        }, 'dons');
    }

    /** Afficher ou masquer une personne sur le mur public des donateurs (/soutenir-atak#donateurs). */
    public function donorWall(Request $request, array $params = []): Response
    {
        return $this->act($request, function (int $actor) use ($request) {
            $email = strtolower(trim((string) $request->input('email', '')));
            $visible = (string) $request->input('visible', '1') === '1';
            (new DonorService($this->ledger()))->setHidden($email, !$visible);
            $this->service()->refreshDonors();

            return [['ok' => true], ($visible ? 'Affiché' : 'Masqué') . ' sur le mur des donateurs : ' . $email . '.'];
        }, 'dons');
    }

    public function donorRestore(Request $request, array $params = []): Response
    {
        return $this->act($request, function (int $actor) use ($request) {
            $email = strtolower(trim((string) $request->input('email', '')));
            (new DonorService($this->ledger()))->restore($email);
            $this->ledger()->log('donor_restored', [], $actor ?: null, '', ['email' => $email]);
            $this->service()->refreshDonors();

            return [['ok' => true], 'Rôle donateur rétabli pour ' . $email . '.'];
        }, 'dons');
    }

    public function donorRebuild(Request $request, array $params = []): Response
    {
        return $this->act($request, function () {
            $r = (new DonorService($this->ledger()))->rebuild($this->service()->paidTenantIds());

            return [['ok' => true], sprintf('Recalcul terminé : %d donateur(s), %d communauté(s) soutien, %d badge(s) attribué(s), %d retiré(s).', $r['donors'], $r['tenants'], $r['granted'], $r['revoked'])];
        }, 'dons');
    }

    public function searchMembers(Request $request, array $params = []): Response
    {
        $rows = $this->safe(fn () => $this->ledger()->searchUsers((string) $request->query('q', '')), []);

        return Response::json(['ok' => true, 'members' => array_map(static fn ($r) => [
            'id' => (int) $r['id'],
            'name' => (string) ($r['display_name'] ?? ''),
            'email' => (string) ($r['email'] ?? ''),
            'tenant' => (string) ($r['tenant_name'] ?? ''),
        ], $rows)]);
    }

    public function export(Request $request, array $params = []): Response
    {
        $rows = Analytics::filter(
            $this->safe(fn () => $this->ledger()->ledger(), []),
            (string) $request->query('nature', ''),
            (string) $request->query('statut', ''),
            (string) $request->query('q', ''),
            max(0, (int) $request->query('periode', 0))
        );

        return (new Response())
            ->setStatusCode(200)
            ->header('Content-Type', 'text/csv; charset=utf-8')
            ->header('Content-Disposition', 'attachment; filename="paiements-athena-' . date('Ymd-Hi') . '.csv"')
            ->header('Cache-Control', 'private, no-store')
            ->setBody(Analytics::csv($rows));
    }

    // ------------------------------------------------------------------ réglages

    public function saveSettings(Request $request, array $params = []): Response
    {
        $back = url(self::BASE) . '?vue=reglages';
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée. Réessayez.');

            return Response::redirect($back);
        }
        $raw = trim((string) $request->input('paypal_url', ''));
        $url = $raw === '' ? '' : PayPalDonation::normalizeUrl($raw);
        if ($url === null) {
            Session::flash('error', 'Lien PayPal invalide : utilisez un lien paypal.me (ex. https://www.paypal.me/MonCompte).');

            return Response::redirect($back);
        }
        try {
            (new PlatformSettingsRepository())->setMany([ManualBilling::SETTING_PAYPAL_URL => $url]);
            $onSale = $request->input('on_sale', []);
            $onSale = is_array($onSale) ? array_map('strval', $onSale) : [];
            $this->service()->setDisabledPlans(array_values(array_diff(PlanPaymentService::PAID_PLANS, $onSale)));
            Session::flash('success', 'Réglages des paiements enregistrés.');
        } catch (\Throwable) {
            Session::flash('error', 'Le réglage n’a pas pu être enregistré (table platform_settings absente ?).');
        }

        return Response::redirect($back);
    }

    // ------------------------------------------------------------------ outils

    private function decide(Request $request, array $params, bool $activate): Response
    {
        return $this->act($request, function (int $actor) use ($request, $params, $activate) {
            $id = (int) ($params['id'] ?? 0);
            $note = $this->note($request);
            if ($activate) {
                $r = $this->service()->activate($id, $actor ?: null, $note);
                $ends = (string) ($r['ends_at'] ?? '');

                return [$r, $ends !== ''
                    ? 'Formule activée jusqu’au ' . date('d/m/Y', (int) strtotime($ends)) . '. La personne est prévenue par e-mail.'
                    : 'Paiement confirmé. La personne est prévenue par e-mail.'];
            }

            return [$this->service()->reject($id, $actor ?: null, $note), 'Paiement refusé. La personne est prévenue par e-mail.'];
        });
    }

    /**
     * Garde commune des actions : jeton, auteur, message de retour, redirection vers la vue d'origine.
     *
     * @param callable(int): array{0: array{ok?: bool, error?: string}, 1: string} $fn
     */
    private function act(Request $request, callable $fn, string $defaultView = 'paiements'): Response
    {
        $view = (string) $request->input('_vue', $defaultView);
        $back = url(self::BASE) . '?vue=' . (in_array($view, self::VIEWS, true) ? $view : $defaultView);
        $ref = trim((string) $request->input('_ref', ''));
        if ($ref !== '') {
            $back .= '&ref=' . rawurlencode($ref) . '#' . rawurlencode($ref);
        }
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée. Réessayez.');

            return Response::redirect($back);
        }
        try {
            [$result, $message] = $fn((int) (Session::get('user_id') ?? 0));
        } catch (\Throwable $e) {
            error_log('[admin paiements] ' . $e->getMessage());
            $result = ['ok' => false, 'error' => 'Action impossible : le registre n’est peut-être pas à jour (run-migrations).'];
            $message = '';
        }
        if (empty($result['ok'])) {
            Session::flash('error', (string) ($result['error'] ?? 'Action impossible.'));
        } else {
            Session::flash('success', $message);
        }

        return Response::redirect($back);
    }

    /**
     * @template T
     * @param callable(): T $fn
     * @param T $fallback
     * @return T
     */
    private function safe(callable $fn, mixed $fallback): mixed
    {
        try {
            return $fn();
        } catch (\Throwable) {
            return $fallback;
        }
    }
}
