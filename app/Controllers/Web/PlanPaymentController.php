<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Container;
use App\Core\Csrf;
use App\Core\Gate;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\PlanPaymentRequestRepository;
use App\Services\Billing\PlanPaymentService;
use App\Support\ManualBilling;

/**
 * Page de paiement d’Athena (/paiement/{référence}) : bouton PayPal avec le montant, QR code pour payer
 * depuis un téléphone, puis déclaration de l’identifiant de transaction PayPal.
 */
final class PlanPaymentController
{
    private function service(): PlanPaymentService
    {
        return Container::get(PlanPaymentService::class);
    }

    public function show(Request $request, array $params = []): Response
    {
        $row = $this->ownedRequest((string) ($params['reference'] ?? ''));
        if ($row === null) {
            Session::flash('error', 'Demande de paiement introuvable.');

            return Response::redirect(url('dashboard'));
        }
        $service = $this->service();
        $tenant = !empty($row['tenant_id'])
            ? Container::get(\App\Repositories\TenantRepository::class)->findById((int) $row['tenant_id'])
            : null;

        return Response::view('payment.show', [
            'title' => 'Paiement ' . $row['reference'] . ' — Athena',
            'payment' => $row,
            'description' => $service->describe($row),
            'amountLabel' => ManualBilling::formatAmount((int) $row['amount_cents']),
            'paypalUrl' => $service->paypalUrlFor($row),
            'qrUrl' => url('paiement/' . rawurlencode((string) $row['reference']) . '/qr.png'),
            'tenantName' => $tenant !== null ? (string) ($tenant['name'] ?? '') : '',
            'csrfToken' => Csrf::token(),
            'flashError' => Session::getFlash('error'),
            'flashSuccess' => Session::getFlash('success'),
        ]);
    }

    public function declare(Request $request, array $params = []): Response
    {
        $reference = (string) ($params['reference'] ?? '');
        $back = url('paiement/' . rawurlencode(strtoupper(trim($reference))));
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée. Réessayez.');

            return Response::redirect($back);
        }
        $row = $this->ownedRequest($reference);
        if ($row === null) {
            Session::flash('error', 'Demande de paiement introuvable.');

            return Response::redirect(url('dashboard'));
        }
        $result = $this->service()->declare(
            $row,
            (string) $request->input('transaction_ref', ''),
            (string) $request->input('payer_note', '')
        );
        if (empty($result['ok'])) {
            Session::flash('error', (string) ($result['error'] ?? 'Déclaration impossible.'));
        } else {
            Session::flash('success', 'Merci ! Votre paiement est déclaré. L’équipe Athena le vérifie et active votre achat, vous recevrez un e-mail.');
        }

        return Response::redirect($back);
    }

    public function qr(Request $request, array $params = []): Response
    {
        $row = $this->ownedRequest((string) ($params['reference'] ?? ''));
        if ($row === null) {
            return (new Response())->setStatusCode(404)->setBody('');
        }
        $qr = (new \App\Services\Qr\QrPngGenerator())->png($this->service()->paypalUrlFor($row), 420, 16);
        if ($qr === null) {
            return (new Response())->setStatusCode(503)->setBody('');
        }

        return (new Response())
            ->setStatusCode(200)
            ->header('Content-Type', $qr['mime'])
            ->header('Cache-Control', 'private, max-age=3600')
            ->setBody($qr['body']);
    }

    /**
     * La demande n’est visible que par la personne qui l’a créée ou par l’administration du site.
     *
     * @return array<string, mixed>|null
     */
    private function ownedRequest(string $reference): ?array
    {
        $reference = strtoupper(trim($reference));
        if (preg_match('/^ATH-[A-Z0-9]{6}$/', $reference) !== 1) {
            return null;
        }
        $row = $this->service()->find($reference);
        if ($row === null || (string) $row['status'] === PlanPaymentRequestRepository::STATUS_CANCELLED) {
            return null;
        }
        $uid = (int) (Session::get('user_id') ?? 0);
        if ($uid > 0 && (int) ($row['user_id'] ?? 0) === $uid) {
            return $row;
        }

        return Gate::getInstance()->isPlatformAdmin() ? $row : null;
    }
}
