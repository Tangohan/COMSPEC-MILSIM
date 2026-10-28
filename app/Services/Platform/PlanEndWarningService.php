<?php

declare(strict_types=1);

namespace App\Services\Platform;

use App\Core\Container;
use App\Repositories\PlatformSettingsRepository;
use App\Repositories\SubscriptionPlanRepository;
use App\Repositories\TenantRepository;
use App\Services\EmailService;
use App\Support\PlanAccessWindow;
use App\Support\PlanGift;

/**
 * Fins de formule : réglages (grâce, avertissement), liste de suivi pour l'administration de la plateforme
 * et e-mail aux gestionnaires N jours avant la fin d'un essai fondateur, d'une formule offerte ou d'une
 * période payée par PayPal (rappels toujours envoyés : le renouvellement est manuel).
 */
final class PlanEndWarningService
{
    /** Fenêtre de la liste de suivi : fins dans les 14 prochains jours (ou plus si l'avertissement est plus long). */
    public const WATCH_DAYS = 14;

    public function __construct(
        private ?PlatformSettingsRepository $settings = null,
        private ?TenantRepository $tenants = null,
        private ?SubscriptionPlanRepository $plans = null,
        private ?PlanGiftService $gifts = null,
        private ?EmailService $email = null,
    ) {
    }

    private function settingsRepo(): PlatformSettingsRepository
    {
        return $this->settings ??= Container::get(PlatformSettingsRepository::class);
    }

    private function tenantsRepo(): TenantRepository
    {
        return $this->tenants ??= Container::get(TenantRepository::class);
    }

    private function plansRepo(): SubscriptionPlanRepository
    {
        return $this->plans ??= Container::get(SubscriptionPlanRepository::class);
    }

    private function giftService(): PlanGiftService
    {
        return $this->gifts ??= new PlanGiftService();
    }

    private function emailService(): EmailService
    {
        return $this->email ??= Container::get(EmailService::class);
    }

    /**
     * @return array{grace_enabled: bool, grace_days: int, warning_days: int, warning_email: bool}
     */
    public function config(): array
    {
        $repo = $this->settingsRepo();

        return [
            'grace_enabled' => $repo->getBool(PlanAccessWindow::SETTING_GRACE_ENABLED, false),
            'grace_days' => PlanAccessWindow::clampDays($repo->get(PlanAccessWindow::SETTING_GRACE_DAYS, ''), PlanAccessWindow::DEFAULT_GRACE_DAYS),
            'warning_days' => PlanAccessWindow::clampDays($repo->get(PlanAccessWindow::SETTING_WARNING_DAYS, ''), PlanAccessWindow::DEFAULT_WARNING_DAYS),
            'warning_email' => $repo->getBool(PlanAccessWindow::SETTING_WARNING_EMAIL, false),
        ];
    }

    /** Jours de grâce appliqués (0 si la grâce est désactivée). */
    public function effectiveGraceDays(): int
    {
        $c = $this->config();

        return $c['grace_enabled'] ? $c['grace_days'] : 0;
    }

    /** @param array<string, mixed> $input */
    public function saveConfig(array $input): void
    {
        $this->settingsRepo()->setMany([
            PlanAccessWindow::SETTING_GRACE_ENABLED => !empty($input['grace_enabled']) ? '1' : '0',
            PlanAccessWindow::SETTING_GRACE_DAYS => (string) PlanAccessWindow::clampDays($input['grace_days'] ?? null, PlanAccessWindow::DEFAULT_GRACE_DAYS),
            PlanAccessWindow::SETTING_WARNING_DAYS => (string) PlanAccessWindow::clampDays($input['warning_days'] ?? null, PlanAccessWindow::DEFAULT_WARNING_DAYS),
            PlanAccessWindow::SETTING_WARNING_EMAIL => !empty($input['warning_email']) ? '1' : '0',
        ]);
        self::$configCache = null;
    }

    /** @var array{grace_days: int, at: int}|null */
    private static ?array $configCache = null;

    /** Jours de grâce, lus une fois par requête (appelé par le contrôle d'accès à chaque appel du jeu). */
    public static function cachedGraceDays(): int
    {
        if (self::$configCache !== null && self::$configCache['at'] > time() - 60) {
            return self::$configCache['grace_days'];
        }
        try {
            $days = (new self())->effectiveGraceDays();
        } catch (\Throwable) {
            $days = 0;
        }
        self::$configCache = ['grace_days' => $days, 'at' => time()];

        return $days;
    }

    /**
     * Ligne tenants minimale reconstruite depuis l'annuaire (listOverviewForPlatform).
     *
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    public static function tenantFromOverview(array $row): array
    {
        $w = is_array($row['plan_window'] ?? null) ? $row['plan_window'] : [];
        $settings = [];
        if (!empty($w['founder_trial_ends_at'])) {
            $settings['founder_trial_ends_at'] = $w['founder_trial_ends_at'];
        }
        if (is_array($w['plan_gift'] ?? null)) {
            $settings[PlanGift::SETTINGS_KEY] = $w['plan_gift'];
        }
        if (!empty($w['plan_end_warning_sent'])) {
            $settings[PlanAccessWindow::WARNED_KEY] = $w['plan_end_warning_sent'];
        }
        if (is_array($w['manual_billing'] ?? null)) {
            $settings[\App\Support\ManualBilling::SETTINGS_KEY] = $w['manual_billing'];
        }

        return [
            'id' => (int) ($row['id'] ?? 0),
            'name' => (string) ($row['name'] ?? ''),
            'plan_slug' => (string) ($row['plan_slug'] ?? 'free'),
            'subscription_status' => (string) ($row['subscription_status'] ?? 'none'),
            'settings' => $settings,
        ];
    }

    /**
     * Modules par formule : slug => [nom, features].
     *
     * @return array<string, array{name: string, features: array<string, mixed>}>
     */
    private function planCatalog(): array
    {
        $out = [];
        try {
            foreach ($this->plansRepo()->allOrdered() as $p) {
                $slug = (string) ($p['slug'] ?? '');
                if ($slug === '') {
                    continue;
                }
                $f = json_decode((string) ($p['features_json'] ?? '{}'), true);
                $out[$slug] = ['name' => (string) ($p['name'] ?? $slug), 'features' => is_array($f) ? $f : []];
            }
        } catch (\Throwable) {
        }

        return $out;
    }

    /**
     * Liste de suivi pour /admin/formules-offertes.
     *
     * @return array{
     *   ending: list<array<string, mixed>>,
     *   grace: list<array<string, mixed>>,
     *   gated: list<array<string, mixed>>
     * }
     */
    public function watchList(?int $now = null): array
    {
        $now ??= time();
        $cfg = $this->config();
        $graceDays = $cfg['grace_enabled'] ? $cfg['grace_days'] : 0;
        $watchDays = max(self::WATCH_DAYS, $cfg['warning_days']);
        $catalog = $this->planCatalog();
        $hasAtak = static fn (string $slug): bool => !empty($catalog[$slug]['features']['atak']);
        $planName = static fn (string $slug): string => $catalog[$slug]['name'] ?? $slug;

        $ending = [];
        $grace = [];
        $gated = [];
        foreach ($this->tenantsRepo()->listOverviewForPlatform() as $row) {
            $tenant = self::tenantFromOverview($row);
            $base = [
                'id' => $tenant['id'],
                'name' => $tenant['name'],
                'tenant_type' => (string) ($row['tenant_type'] ?? 'full'),
                'user_count' => (int) ($row['user_count'] ?? 0),
                'effective_plan' => $planName(PlanGift::effectivePlanSlug($tenant, $now)),
            ];
            $soon = PlanAccessWindow::endingSoon($tenant, $watchDays, $now);
            if ($soon !== null) {
                $ending[] = $base + [
                    'kind' => $soon['kind'],
                    'plan' => $planName($soon['plan_slug']),
                    'ends_at' => $soon['ends_at'],
                    'days_left' => $soon['days_left'],
                    'loses_atak' => $hasAtak($soon['plan_slug']) && !$hasAtak($soon['kind'] === 'paid' ? 'free' : (string) $tenant['plan_slug']),
                    'warned' => PlanAccessWindow::alreadyWarned($tenant, $soon['kind'], $soon['ends_at'], PlanAccessWindow::reminderStage($soon['kind'], $soon['days_left'])),
                ];
                continue;
            }
            $g = PlanAccessWindow::grace($tenant, $graceDays, $now);
            if ($g !== null) {
                $grace[] = $base + [
                    'kind' => $g['kind'],
                    'plan' => $planName($g['plan_slug']),
                    'ended_at' => $g['ended_at'],
                    'grace_ends_at' => $g['grace_ends_at'],
                    'days_left' => $g['days_left'],
                ];
                continue;
            }
            $effective = PlanGift::effectivePlanSlug($tenant, $now);
            if (!$hasAtak($effective) || $tenant['subscription_status'] === 'past_due') {
                $lapsed = PlanAccessWindow::lapsed($tenant, $now);
                $gated[] = $base + [
                    'reason' => $tenant['subscription_status'] === 'past_due'
                        ? 'past_due'
                        : ($lapsed !== null ? $lapsed['kind'] . '_ended' : 'base_plan'),
                    'ended_at' => $lapsed['ended_at'] ?? null,
                ];
            }
        }
        usort($ending, static fn (array $a, array $b): int => strcmp((string) $a['ends_at'], (string) $b['ends_at']));
        usort($gated, static function (array $a, array $b): int {
            // Fins récentes d'abord : ce sont les communautés qui viennent de perdre la liaison.
            return strcmp((string) ($b['ended_at'] ?? ''), (string) ($a['ended_at'] ?? ''));
        });

        return ['ending' => $ending, 'grace' => $grace, 'gated' => $gated];
    }

    /**
     * E-mail aux gestionnaires des communautés dont la formule à durée limitée se termine bientôt.
     * Essai fondateur et formule offerte : une fois par fin, seulement si l'e-mail est activé.
     * Formule payée par PayPal (renouvellement manuel) : toujours, N jours avant puis la veille.
     * Empreinte de la dernière annonce dans tenants.settings.
     *
     * @return array{enabled: bool, gift_trial_email: bool, tenants: int, emails: int}
     */
    public function sendWarnings(?int $now = null): array
    {
        $now ??= time();
        $cfg = $this->config();
        $giftTrialEmail = $cfg['warning_email'] && $cfg['warning_days'] >= 1;
        $paidDays = PlanAccessWindow::paidReminderDays($cfg['warning_days']);
        $graceDays = $cfg['grace_enabled'] ? $cfg['grace_days'] : 0;
        $catalog = $this->planCatalog();
        $tenantsWarned = 0;
        $emails = 0;
        foreach ($this->tenantsRepo()->listOverviewForPlatform() as $row) {
            $tenant = self::tenantFromOverview($row);
            $soon = PlanAccessWindow::endingSoon($tenant, max($paidDays, $cfg['warning_days']), $now);
            if ($soon === null) {
                continue;
            }
            if ($soon['kind'] !== 'paid' && (!$giftTrialEmail || $soon['days_left'] > $cfg['warning_days'])) {
                continue;
            }
            if ($soon['kind'] === 'paid' && $soon['days_left'] > $paidDays) {
                continue;
            }
            $stage = PlanAccessWindow::reminderStage($soon['kind'], $soon['days_left']);
            if (PlanAccessWindow::alreadyWarned($tenant, $soon['kind'], $soon['ends_at'], $stage)) {
                continue;
            }
            $full = $this->tenantsRepo()->findById($tenant['id']);
            if (!is_array($full)) {
                continue;
            }
            $planName = $catalog[$soon['plan_slug']]['name'] ?? $soon['plan_slug'];
            // Une période payée échue revient à la formule gratuite ; un essai ou une offre, à la formule de base.
            $afterSlug = $soon['kind'] === 'paid' ? 'free' : (string) $tenant['plan_slug'];
            $atakLost = !empty($catalog[$soon['plan_slug']]['features']['atak'])
                && empty($catalog[$afterSlug]['features']['atak']);
            $sent = 0;
            foreach ($this->giftService()->recipients($full) as $user) {
                $to = (string) $user['email'];
                try {
                    $lang = PlanGift::locale($this->emailService()->recipientLocale($to));
                    if ($this->emailService()->sendPlanEndWarning(
                        $to,
                        $lang,
                        trim((string) ($user['callsign'] ?? '')) ?: trim((string) ($user['display_name'] ?? '')),
                        $tenant['name'] !== '' ? $tenant['name'] : 'Communauté',
                        $planName,
                        $soon['kind'],
                        $soon['ends_at'],
                        $atakLost,
                        $graceDays,
                        url('platform/upgrade'),
                        $tenant['id'],
                    )) {
                        $sent++;
                    }
                } catch (\Throwable) {
                }
            }
            // Empreinte posée même sans destinataire : pas de nouvel essai à chaque passage.
            $this->tenantsRepo()->mergeSettings($tenant['id'], [
                PlanAccessWindow::WARNED_KEY => PlanAccessWindow::warningFingerprint($soon['kind'], $soon['ends_at'], $stage),
            ]);
            $tenantsWarned++;
            $emails += $sent;
        }

        return ['enabled' => true, 'gift_trial_email' => $giftTrialEmail, 'tenants' => $tenantsWarned, 'emails' => $emails];
    }
}
