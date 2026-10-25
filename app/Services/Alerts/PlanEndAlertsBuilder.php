<?php

declare(strict_types=1);

namespace App\Services\Alerts;

use App\Core\Gate;
use App\Repositories\SubscriptionPlanRepository;
use App\Repositories\TenantRepository;
use App\Services\Platform\PlanEndWarningService;
use App\Support\PlanAccessWindow;
use App\Support\PlanGift;

/**
 * Alerte cloche automatique pour les responsables de la communauté : formule qui se termine bientôt
 * (période payée par PayPal, essai fondateur, formule offerte) ou période payée qui vient d’expirer.
 * Le renouvellement étant manuel (PayPal), c’est le rappel visible sur toutes les pages.
 */
final class PlanEndAlertsBuilder
{
    public function __construct(
        private TenantRepository $tenants,
        private SubscriptionPlanRepository $plans,
    ) {}

    /**
     * @return list<array{scope: string, id: int, kind: string, title: string, body: string, cta_label: ?string, cta_url: ?string, cta_secondary_label: ?string, cta_secondary_url: ?string, coupon_code: ?string}>
     */
    public function build(int $userId, int $tenantId, ?int $now = null): array
    {
        if ($userId <= 0 || $tenantId < 2 || !$this->viewerManagesBilling()) {
            return [];
        }
        $tenant = $this->tenants->findById($tenantId);
        if (!is_array($tenant)) {
            return [];
        }
        $now ??= time();
        $cfg = (new PlanEndWarningService())->config();
        $paidDays = PlanAccessWindow::paidReminderDays($cfg['warning_days']);
        $lang = PlanGift::locale(function_exists('locale') ? (string) locale() : 'fr');
        $tenantName = trim((string) ($tenant['name'] ?? '')) ?: 'Communauté';

        $soon = PlanAccessWindow::endingSoon($tenant, max($paidDays, $cfg['warning_days']), $now);
        if ($soon !== null && $soon['days_left'] <= ($soon['kind'] === 'paid' ? $paidDays : $cfg['warning_days'])) {
            $copy = PlanAccessWindow::copy($lang, $tenantName, $this->planName($soon['plan_slug']), $soon['kind'], $soon['ends_at'], false, 0);

            return [$this->alert(
                $soon['days_left'] <= 2 ? 'urgent' : 'rappel',
                $copy['title'],
                $copy['lead'] . ($soon['kind'] === 'paid'
                    ? ($lang === 'en' ? ' Renew it with PayPal to keep its modules.' : ' Renouvelez-la par PayPal pour garder ses modules.')
                    : ''),
                $copy['banner_cta'],
                $tenantId
            )];
        }

        $expired = PlanAccessWindow::paidExpired($tenant, $now);
        if ($expired !== null) {
            $copy = PlanAccessWindow::paidExpiredCopy($lang, $this->planName($expired['plan_slug']), $expired['ended_at']);

            return [$this->alert('urgent', $copy['title'], $copy['text'], $copy['cta'], $tenantId)];
        }

        return [];
    }

    /** @return array{scope: string, id: int, kind: string, title: string, body: string, cta_label: ?string, cta_url: ?string, cta_secondary_label: ?string, cta_secondary_url: ?string, coupon_code: ?string} */
    private function alert(string $kind, string $title, string $body, string $cta, int $tenantId): array
    {
        return [
            'scope' => 'Formule',
            'id' => -2600 - $tenantId,
            'kind' => $kind,
            'title' => $title,
            'body' => $body,
            'cta_label' => $cta,
            'cta_url' => url('platform/upgrade?from=plan_end_alert'),
            'cta_secondary_label' => null,
            'cta_secondary_url' => null,
            'coupon_code' => null,
        ];
    }

    private function planName(string $slug): string
    {
        try {
            $p = $this->plans->findBySlug($slug);
        } catch (\Throwable) {
            $p = null;
        }

        return trim((string) ($p['name'] ?? '')) ?: $slug;
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
