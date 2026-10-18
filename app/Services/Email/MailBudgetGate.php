<?php

declare(strict_types=1);

namespace App\Services\Email;

use App\Repositories\EmailDeliveryRepository;
use App\Repositories\PlatformSettingsRepository;

/**
 * Applique MailBudget avant chaque envoi : lit les réglages et le compteur du jour.
 * En cas de doute (base indisponible, table absente) : l'e-mail part, comme avant.
 */
final class MailBudgetGate
{
    /** @var array{limit:int, reserve:int, digest_minutes:int, muted:list<string>}|null */
    private ?array $config = null;

    private int $configLoadedAt = 0;

    public function __construct(
        private EmailDeliveryRepository $deliveries,
        private ?PlatformSettingsRepository $settings = null,
    ) {}

    /**
     * @return array{decision:string, priority:string, sent_today:int}
     */
    public function check(string $eventCode, string $to): array
    {
        try {
            $config = $this->config();
            $priority = MailBudget::priority($eventCode, $to, self::opsInboxes());
            if ($priority === MailBudget::PRIORITY_CRITICAL) {
                return ['decision' => MailBudget::SEND, 'priority' => $priority, 'sent_today' => 0];
            }
            $sentToday = $config['limit'] > 0 ? $this->sentToday() : 0;

            return [
                'decision' => MailBudget::decide($eventCode, $priority, $sentToday, $config),
                'priority' => $priority,
                'sent_today' => $sentToday,
            ];
        } catch (\Throwable) {
            return ['decision' => MailBudget::SEND, 'priority' => MailBudget::PRIORITY_NORMAL, 'sent_today' => 0];
        }
    }

    public function sentToday(): int
    {
        return $this->deliveries->countSentSince(date('Y-m-d 00:00:00'));
    }

    /**
     * @return array{limit:int, reserve:int, digest_minutes:int, muted:list<string>}
     */
    public function config(): array
    {
        // Relu toutes les minutes : un worker de file ou un cron long voit les nouveaux réglages.
        if ($this->config !== null && (time() - $this->configLoadedAt) < 60) {
            return $this->config;
        }
        $settings = [];
        try {
            $repo = $this->settings ??= new PlatformSettingsRepository();
            foreach ([MailBudget::SETTING_DAILY_LIMIT, MailBudget::SETTING_RESERVE, MailBudget::SETTING_DIGEST_MINUTES, MailBudget::SETTING_MUTED] as $k) {
                $settings[$k] = $repo->get($k, '');
            }
        } catch (\Throwable) {
        }
        $env = trim((string) \env('MAIL_DAILY_LIMIT', ''));
        $this->config = MailBudget::configFrom($settings, $env !== '' && is_numeric($env) ? (int) $env : null);
        $this->configLoadedAt = time();

        return $this->config;
    }

    public function forgetConfig(): void
    {
        $this->config = null;
    }

    /**
     * Boîtes de l'exploitation de la plateforme : tout ce qui y part (hors essentiels) est regroupé.
     *
     * @return list<string>
     */
    public static function opsInboxes(): array
    {
        $out = [];
        foreach (['REGISTRATION_ALERT_EMAIL', 'ERROR_ALERT_EMAIL', 'SECURITY_ALERT_EMAILS'] as $key) {
            foreach (preg_split('/[\s,;]+/', (string) \env($key, '')) ?: [] as $candidate) {
                $candidate = strtolower(trim($candidate));
                if ($candidate !== '' && filter_var($candidate, FILTER_VALIDATE_EMAIL)) {
                    $out[$candidate] = true;
                }
            }
        }

        return array_keys($out);
    }
}
