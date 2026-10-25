<?php

declare(strict_types=1);

namespace App\Controllers\Admin\System;

use App\Core\Container;
use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\PlanPaymentRequestRepository;
use App\Repositories\PlatformSettingsRepository;
use App\Services\Billing\PlanPaymentService;
use App\Support\ManualBilling;
use App\Support\PayPalDonation;

/**
 * /admin/paiements : paiements PayPal des formules déclarés par les communautés, à vérifier puis activer.
 */
final class SystemPlanPaymentsController
{
    private function service(): PlanPaymentService
    {
        return Container::get(PlanPaymentService::class);
    }

    private function repo(): PlanPaymentRequestRepository
    {
        return Container::get(PlanPaymentRequestRepository::class);
    }

    public function index(Request $request, array $params = []): Response
    {
        $status = (string) $request->query('statut', '');
        if (!in_array($status, ['', 'awaiting', 'declared', 'activated', 'rejected', 'cancelled'], true)) {
            $status = '';
        }
        $rows = [];
        $counts = [];
        $error = null;
        if (!$this->service()->schemaReady()) {
            $error = 'Le registre des paiements n’existe pas encore : lancez la mise à jour de la base (run-migrations).';
        } else {
            $rows = $this->repo()->listForAdmin($status !== '' ? $status : null);
            $counts = $this->repo()->countByStatus();
        }
        $service = $this->service();
        foreach ($rows as &$row) {
            $row['_description'] = $service->describe($row);
            $row['_amount'] = ManualBilling::formatAmount((int) $row['amount_cents']);
        }
        unset($row);

        return Response::view('layout.main', [
            'title' => 'Paiements PayPal',
            'content' => 'admin.system.plan_payments',
            'isPlatformAdminShell' => true,
            'backOfficePageCss' => ['platform-admin.css'],
            'paymentRows' => $rows,
            'paymentCounts' => $counts,
            'paymentStatus' => $status,
            'paymentFocus' => strtoupper(trim((string) $request->query('ref', ''))),
            'paymentError' => $error,
            'paypalBaseUrl' => $service->paypalBaseUrl(),
        ]);
    }

    public function activate(Request $request, array $params = []): Response
    {
        return $this->decide($request, $params, true);
    }

    public function reject(Request $request, array $params = []): Response
    {
        return $this->decide($request, $params, false);
    }

    private function decide(Request $request, array $params, bool $activate): Response
    {
        $back = url('admin/paiements');
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée. Réessayez.');

            return Response::redirect($back);
        }
        $id = (int) ($params['id'] ?? 0);
        $actor = (int) (Session::get('user_id') ?? 0);
        $note = trim((string) $request->input('admin_note', ''));
        $result = $activate
            ? $this->service()->activate($id, $actor > 0 ? $actor : null, $note)
            : $this->service()->reject($id, $actor > 0 ? $actor : null, $note);
        if (empty($result['ok'])) {
            Session::flash('error', (string) ($result['error'] ?? 'Action impossible.'));
        } elseif ($activate) {
            $ends = (string) ($result['ends_at'] ?? '');
            Session::flash('success', $ends !== ''
                ? 'Formule activée jusqu’au ' . date('d/m/Y', (int) strtotime($ends)) . '. La personne est prévenue par e-mail.'
                : 'Paiement confirmé. La personne est prévenue par e-mail.');
        } else {
            Session::flash('success', 'Paiement refusé. La personne est prévenue par e-mail.');
        }

        return Response::redirect($back);
    }

    public function saveSettings(Request $request, array $params = []): Response
    {
        $back = url('admin/paiements') . '#reglages';
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
            Session::flash('success', $url === '' ? 'Lien PayPal remis par défaut.' : 'Lien PayPal enregistré.');
        } catch (\Throwable) {
            Session::flash('error', 'Le réglage n’a pas pu être enregistré (table platform_settings absente ?).');
        }

        return Response::redirect($back);
    }
}
