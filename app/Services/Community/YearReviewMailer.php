<?php

declare(strict_types=1);

namespace App\Services\Community;

use App\Core\Container;
use App\Repositories\TenantRepository;
use App\Repositories\UserNotificationPreferencesRepository;
use App\Repositories\UserRepository;
use App\Repositories\UserUiTourRepository;
use App\Services\Email\EmailEvents;
use App\Services\Email\MailBudgetGate;
use App\Services\EmailService;
use App\Support\YearReviewCalendar;
use App\Support\YearReviewStats;

/**
 * E-mail « ta rétrospective est prête » : une fois par joueur et par année, aux comptes qui ont assez joué pour
 * avoir une rétrospective, qui ne l'ont pas encore ouverte et n'ont pas coupé cet e-mail dans leurs préférences.
 * Lancé chaque jour par le cron pendant les EMAIL_DAYS jours qui suivent la publication. Chaque passage prend au
 * plus la moitié de ce qui reste du quota journalier (Administration > Envois d'e-mails), pour laisser passer les
 * autres e-mails des membres ; les joueurs restants sont prévenus les jours suivants.
 */
final class YearReviewMailer
{
    /** Envois au plus par passage du cron (le reste part au passage suivant). */
    public const BATCH = 400;

    public function __construct(
        private ?YearReviewService $reviews = null,
        private ?UserUiTourRepository $tours = null,
        private ?UserRepository $users = null,
        private ?UserNotificationPreferencesRepository $prefs = null,
        private ?EmailService $email = null,
        private ?MailBudgetGate $budget = null,
    ) {
    }

    /** @return array{year: int|null, tenants: int, sent: int, skipped: int, failed: int} */
    public function sendAnnouncements(?int $now = null, int $batch = self::BATCH): array
    {
        $now ??= time();
        $out = ['year' => null, 'tenants' => 0, 'sent' => 0, 'skipped' => 0, 'failed' => 0];
        $year = YearReviewAccess::calendar()->emailYear($now);
        if ($year === null) {
            return $out;
        }
        $out['year'] = $year;
        $tours = $this->tours ??= new UserUiTourRepository();
        if (!$tours->schemaReady()) {
            return $out;
        }
        $reviews = $this->reviews ??= new YearReviewService();
        $users = $this->users ??= Container::get(UserRepository::class);
        $prefs = $this->prefs ??= Container::get(UserNotificationPreferencesRepository::class);
        $email = $this->email ??= Container::get(EmailService::class);
        $url = self::reviewUrl($year);
        $batch = min($batch, $this->budgetShare());
        $done = [];

        foreach ((new TenantRepository())->listBasicAll() as $tenant) {
            $tenantId = (int) ($tenant['id'] ?? 0);
            if ($tenantId < 1) {
                continue;
            }
            try {
                $review = $reviews->forTenant($tenantId, $year, $now);
            } catch (\Throwable) {
                continue;
            }
            $out['tenants']++;
            foreach ($review['players'] as $key => $player) {
                if ($out['sent'] >= $batch) {
                    return $out;
                }
                if (empty($player['active']) || !preg_match('/^u(\d+)$/', (string) $key, $m)) {
                    continue;
                }
                $userId = (int) $m[1];
                if (isset($done[$userId])) {
                    continue;
                }
                $done[$userId] = true;
                if ($tours->isDismissed($userId, YearReviewCalendar::mailKey($year))
                    || $tours->isDismissed($userId, YearReviewCalendar::seenKey($year))
                    || !$prefs->isEmailEventEnabled($userId, EmailEvents::YEAR_REVIEW_READY)) {
                    $out['skipped']++;
                    continue;
                }
                $user = $users->findById($userId);
                $to = strtolower(trim((string) ($user['email'] ?? '')));
                if ($user === null || ($user['status'] ?? 'active') !== 'active' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
                    $out['skipped']++;
                    continue;
                }
                $ok = $email->sendTemplated(
                    EmailEvents::YEAR_REVIEW_READY,
                    'year_review_ready',
                    $to,
                    'Ta rétrospective ' . $year . ' est prête',
                    self::templateVars($year, $player, (string) ($tenant['name'] ?? ''), $url),
                    $tenantId,
                    null,
                    ['purpose' => 'year_review_ready', 'year' => $year]
                );
                if ($ok) {
                    // Marqué même si l'envoi est reporté par le quota : il partira de la file, pas une seconde fois.
                    $tours->dismiss($userId, YearReviewCalendar::mailKey($year));
                    $out['sent']++;
                } else {
                    $out['failed']++;
                }
            }
        }

        return $out;
    }

    /** Part du quota journalier laissée à ce passage : la moitié de ce qui reste, réserve exclue. */
    private function budgetShare(): int
    {
        try {
            $gate = $this->budget ??= Container::get(MailBudgetGate::class);
            $config = $gate->config();
            if (in_array(EmailEvents::YEAR_REVIEW_READY, $config['muted'], true)) {
                return 0;
            }
            if ($config['limit'] <= 0) {
                return self::BATCH;
            }

            return self::share($config['limit'], $config['reserve'], $gate->sentToday());
        } catch (\Throwable) {
            return 0;
        }
    }

    public static function share(int $limit, int $reserve, int $sentToday): int
    {
        $room = $limit - $reserve - $sentToday;

        return $room <= 0 ? 0 : max(1, intdiv($room, 2));
    }

    /**
     * Trois chiffres d'accroche, sans dévoiler la suite (profil, rang).
     *
     * @param array<string, mixed> $player
     * @return array{year: int, displayName: string, tenantName: string, reviewUrl: string, teasers: list<array{0: string, 1: string}>}
     */
    public static function templateVars(int $year, array $player, string $tenantName, string $url): array
    {
        $teasers = [];
        if ((int) ($player['play_s'] ?? 0) >= 3600) {
            $teasers[] = [YearReviewStats::hours((int) $player['play_s']), 'de jeu'];
        }
        if ((int) ($player['missions'] ?? 0) > 0) {
            $teasers[] = [YearReviewStats::int((int) $player['missions']), (int) $player['missions'] > 1 ? 'missions' : 'mission'];
        }
        if ((int) ($player['total_m'] ?? 0) >= 1000) {
            $teasers[] = [YearReviewStats::km((int) $player['total_m']), 'parcourus'];
        }

        return [
            'year' => $year,
            'displayName' => (string) ($player['name'] ?? ''),
            'tenantName' => $tenantName,
            'reviewUrl' => $url,
            'teasers' => array_slice($teasers, 0, 3),
        ];
    }

    public static function reviewUrl(int $year): string
    {
        $path = function_exists('url') ? (string) url('retrospective/' . $year) : '/retrospective/' . $year;
        if (preg_match('#^https?://#i', $path)) {
            return $path;
        }
        $base = rtrim((string) (function_exists('env') ? env('APP_URL', '') : ''), '/');

        return $base . '/' . ltrim($path, '/');
    }
}
