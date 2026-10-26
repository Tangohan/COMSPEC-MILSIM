<?php

declare(strict_types=1);

namespace App\Services\Alerts;

use App\Core\Gate;
use App\Repositories\PlanPaymentRequestRepository;
use App\Services\Billing\PlanPaymentService;
use App\Support\ManualBilling;

/**
 * Alertes cloche des paiements PayPal (validation manuelle) :
 * - responsables de la communauté ou personne qui a payé : paiement pas encore effectué, en cours de vérification,
 *   non validé (refusé), annulé ou remboursé ;
 * - administration de la plateforme : paiements déclarés qui attendent d’être validés.
 */
final class PlanPaymentAlertsBuilder
{
    /** Une demande traitée reste signalée pendant ce nombre de jours. */
    public const RECENT_DAYS = 14;

    public function __construct(
        private PlanPaymentRequestRepository $requests,
        private PlanPaymentService $payments,
    ) {}

    /**
     * @return list<array{scope: string, id: int, kind: string, title: string, body: string, cta_label: ?string, cta_url: ?string, cta_secondary_label: ?string, cta_secondary_url: ?string, coupon_code: ?string}>
     */
    public function build(int $userId, int $tenantId, ?int $now = null): array
    {
        if ($userId <= 0 || !$this->requests->schemaReady()) {
            return [];
        }
        $now ??= time();
        $out = [];
        try {
            if ($this->isPlatformAdmin()) {
                $admin = $this->adminAlert($now);
                if ($admin !== null) {
                    $out[] = $admin;
                }
            }
            if ($tenantId >= 2) {
                $own = $this->tenantAlert($userId, $tenantId, $now);
                if ($own !== null) {
                    $out[] = $own;
                }
            }
        } catch (\Throwable) {
            return $out;
        }

        return $out;
    }

    private static function en(): bool
    {
        return function_exists('locale') && str_starts_with(strtolower((string) locale()), 'en');
    }

    /** Texte français ou anglais selon la langue du visiteur (phrases dynamiques, hors dictionnaire). */
    private static function t(string $fr, string $en): string
    {
        return self::en() ? $en : $fr;
    }

    /** @return array<string, mixed>|null */
    private function adminAlert(int $now): ?array
    {
        $summary = $this->requests->declaredSummary();
        if ($summary['count'] < 1) {
            return null;
        }
        $oldest = $summary['oldest'] !== null ? strtotime($summary['oldest'] . ' UTC') : false;
        $late = $oldest !== false && $oldest < $now - 86400;
        $n = $summary['count'];

        return self::alert(
            -2700,
            $late ? 'urgent' : 'rappel',
            self::t($n . ' paiement' . ($n > 1 ? 's' : '') . ' PayPal à valider', $n . ' PayPal payment' . ($n > 1 ? 's' : '') . ' to validate'),
            self::t(
                ($late ? 'Au moins une personne attend depuis plus de 24 h. ' : '')
                    . 'Vérifiez la réception sur PayPal puis activez, refusez ou annulez la demande dans Admin > Paiements.',
                ($late ? 'At least one person has been waiting for more than 24 hours. ' : '')
                    . 'Check receipt on PayPal, then activate, refuse or cancel the request in Admin > Payments.'
            ),
            self::t('Ouvrir les paiements', 'Open payments'),
            url('admin/paiements?statut=declared'),
            self::t('Plateforme', 'Platform')
        );
    }

    /** @return array<string, mixed>|null */
    private function tenantAlert(int $userId, int $tenantId, int $now): ?array
    {
        $row = $this->requests->latestForTenantSince($tenantId, gmdate('Y-m-d H:i:s', $now - self::RECENT_DAYS * 86400));
        if ($row === null) {
            return null;
        }
        if ((int) ($row['user_id'] ?? 0) !== $userId && !$this->viewerManagesBilling()) {
            return null;
        }
        $ref = (string) $row['reference'];
        $what = $this->payments->describe($row) . ' · ' . ManualBilling::formatAmount((int) $row['amount_cents']);
        $page = url('paiement/' . rawurlencode($ref));
        $note = trim((string) ($row['admin_note'] ?? ''));
        $id = -2800 - (int) $row['id'];

        $msg = $note !== '' ? self::t(' Message : ', ' Message: ') . $note : '';
        $see = self::t('Voir la demande', 'View the request');

        return match ((string) $row['status']) {
            PlanPaymentRequestRepository::STATUS_AWAITING => self::alert(
                $id,
                'rappel',
                self::t('Paiement pas encore effectué', 'Payment not made yet'),
                self::t(
                    'Votre demande ' . $ref . ' (' . $what . ') attend son paiement PayPal. Payez puis déclarez votre identifiant de transaction pour que la formule soit activée.',
                    'Your request ' . $ref . ' (' . $what . ') is waiting for its PayPal payment. Pay, then declare your transaction ID so the plan can be activated.'
                ),
                self::t('Finaliser le paiement', 'Complete the payment'),
                $page
            ),
            PlanPaymentRequestRepository::STATUS_DECLARED => self::alert(
                $id,
                'info',
                self::t('Paiement en attente de validation', 'Payment awaiting validation'),
                self::t(
                    'Votre paiement ' . $ref . ' (' . $what . ') est déclaré : l’équipe Athena le vérifie et active la formule. Vous recevrez un e-mail.',
                    'Your payment ' . $ref . ' (' . $what . ') is declared: the Athena team is checking it and will activate the plan. You will receive an email.'
                ),
                $see,
                $page
            ),
            PlanPaymentRequestRepository::STATUS_REJECTED => self::alert(
                $id,
                'urgent',
                self::t('Paiement non validé', 'Payment not validated'),
                self::t(
                    'L’équipe Athena n’a pas pu valider le paiement ' . $ref . ' (' . $what . ').' . $msg . ' Vérifiez votre transaction PayPal ou contactez l’équipe avec votre reçu.',
                    'The Athena team could not validate payment ' . $ref . ' (' . $what . ').' . $msg . ' Check your PayPal transaction or contact the team with your receipt.'
                ),
                $see,
                $page
            ),
            PlanPaymentRequestRepository::STATUS_REFUNDED => self::alert(
                $id,
                'info',
                self::t('Paiement remboursé', 'Payment refunded'),
                self::t(
                    'Le paiement ' . $ref . ' (' . $what . ') vous a été remboursé sur PayPal.' . $msg,
                    'Payment ' . $ref . ' (' . $what . ') has been refunded to you on PayPal.' . $msg
                ),
                $see,
                $page
            ),
            PlanPaymentRequestRepository::STATUS_CANCELLED => !empty($row['decided_by']) ? self::alert(
                $id,
                'info',
                self::t('Demande de paiement annulée', 'Payment request cancelled'),
                self::t(
                    'L’équipe Athena a annulé la demande ' . $ref . ' (' . $what . ').' . $msg,
                    'The Athena team cancelled request ' . $ref . ' (' . $what . ').' . $msg
                ),
                self::t('Voir les formules', 'See the plans'),
                url('platform/upgrade')
            ) : null,
            default => null,
        };
    }

    /** @return array{scope: string, id: int, kind: string, title: string, body: string, cta_label: ?string, cta_url: ?string, cta_secondary_label: ?string, cta_secondary_url: ?string, coupon_code: ?string} */
    private static function alert(int $id, string $kind, string $title, string $body, string $cta, string $url, ?string $scope = null): array
    {
        return [
            'scope' => $scope ?? self::t('Formule', 'Plan'),
            'id' => $id,
            'kind' => $kind,
            'title' => $title,
            'body' => $body,
            'cta_label' => $cta,
            'cta_url' => $url,
            'cta_secondary_label' => null,
            'cta_secondary_url' => null,
            'coupon_code' => null,
        ];
    }

    private function isPlatformAdmin(): bool
    {
        try {
            return Gate::getInstance()->isPlatformAdmin();
        } catch (\Throwable) {
            return false;
        }
    }

    private function viewerManagesBilling(): bool
    {
        try {
            $gate = Gate::getInstance();

            return $gate->allows('admin.organization')
                || $gate->allows('admin.access')
                || $gate->allows('admin.settings.manage');
        } catch (\Throwable) {
            return false;
        }
    }
}
