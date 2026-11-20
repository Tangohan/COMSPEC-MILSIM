<?php

declare(strict_types=1);

namespace App\Services\Admin;

use App\Repositories\PlatformDashboardActivityRepository;
use App\Repositories\PublicModpackRepository;
use DateTimeImmutable;
use DateTimeZone;
use RuntimeException;

final class PlatformDashboardActivity
{
    public const PERIODS = [7, 14, 30];

    private const PUBLIC_PAGES = [
        '/' => 'Accueil public',
        '/a-propos' => 'À propos',
        '/notre-histoire' => 'Notre histoire',
        '/communities' => 'Annuaire des communautés',
        '/contact' => 'Contact',
        '/nouveautes' => 'Nouveautés',
        '/modpacks' => 'Modpack',
        '/sse' => 'Présentation SSE',
        '/atak-natif' => 'Présentation ATAK',
        '/wiki/atak' => 'Wiki ATAK',
    ];

    public function overview(int $days): array
    {
        $days = in_array($days, self::PERIODS, true) ? $days : 14;
        $end = new DateTimeImmutable('today', new DateTimeZone('UTC'));
        $start = $end->modify("-{$days} days");
        $from = $start->format('Y-m-d');
        $until = $end->format('Y-m-d');

        $downloads = null;
        try {
            $downloads = $this->fillDays(
                (new PublicModpackRepository())->dailyDownloads($from, $until),
                $start,
                $days
            );
        } catch (RuntimeException) {
            // Les compteurs du modpack dépendent de sa migration.
        }

        $accounts = null;
        $pages = null;
        $telemetry = new PlatformDashboardActivityRepository();
        try {
            $activity = $telemetry->observedAccountsByDay($from, $until);
            if ($activity !== []) {
                $accounts = $this->fillDays($activity, $start, $days);
                $counts = $telemetry->observedPageViews(array_keys(self::PUBLIC_PAGES), $from, $until);
                $pages = [];
                foreach (self::PUBLIC_PAGES as $path => $label) {
                    $pages[] = ['path' => $path, 'label' => $label, 'total' => (int) ($counts[$path] ?? 0)];
                }
                usort($pages, static fn (array $a, array $b): int => $a['total'] <=> $b['total']);
                $pages = array_slice($pages, 0, 6);
            }
        } catch (RuntimeException) {
            // La page reste utilisable si la télémétrie est indisponible.
        }

        $engagement = null;
        $returns = null;
        $overview = new PlatformAccountsOverviewService();
        try {
            $signups = [];
            $ages = [];
            $lastLogins = [];
            foreach ($overview->engagement($start, $end) as $row) {
                $total = (int) $row['total'];
                if ($row['signup_day'] !== null) {
                    $day = $row['signup_day'];
                    $signups[$day] = ($signups[$day] ?? 0) + $total;
                }
                $age = $row['age_band'];
                $login = $row['login_band'];
                $ages[$age]['total'] = ($ages[$age]['total'] ?? 0) + $total;
                $ages[$age]['recent'] = ($ages[$age]['recent'] ?? 0) + ($login === 'recent' ? $total : 0);
                $lastLogins[$login] = ($lastLogins[$login] ?? 0) + $total;
            }
            $signupRows = [];
            foreach ($signups as $day => $total) {
                $signupRows[] = ['day' => $day, 'total' => $total];
            }
            $engagement = [
                'signups' => $this->fillDays($signupRows, $start, $days),
                'ages' => $ages,
                'lastLogins' => $lastLogins,
            ];
        } catch (RuntimeException) {
            // Le registre des comptes peut être momentanément indisponible.
        }
        try {
            $returns = $overview->observedReturns($from, $until);
        } catch (RuntimeException) {
            // Le journal des connexions est indépendant du registre des comptes.
        }

        return [
            'days' => $days,
            'periodLabel' => $start->format('d/m') . ' – ' . $end->modify('-1 day')->format('d/m'),
            'downloads' => $downloads,
            'accounts' => $accounts,
            'pages' => $pages,
            'engagement' => $engagement,
            'returns' => $returns,
        ];
    }

    /** @return list<array{date: string, total: int}> */
    private function fillDays(array $rows, DateTimeImmutable $start, int $days): array
    {
        $counts = array_column($rows, 'total', 'day');
        $series = [];
        for ($index = 0; $index < $days; $index++) {
            $date = $start->modify("+{$index} days")->format('Y-m-d');
            $series[] = ['date' => $date, 'total' => (int) ($counts[$date] ?? 0)];
        }

        return $series;
    }
}
