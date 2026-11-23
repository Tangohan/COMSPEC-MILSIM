<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Response;
use PHPUnit\Framework\TestCase;

final class PlatformStatisticsPdfViewTest extends TestCase
{
    public function testPartnerReportOmitsInternalSectionsAndKeepsAggregateMetrics(): void
    {
        $activity = [
            'days' => 7,
            'engagement' => [
                'signups' => [['date' => '2026-11-06', 'total' => 2]],
                'lastLogins' => ['recent' => 1, 'never' => 1],
                'ages' => [],
            ],
            'returns' => [
                'signed_in' => 2, 'returned' => 1, 'one_day' => 1, 'two_to_three' => 1,
                'four_to_six' => 0, 'seven_plus' => 0, 'average_days' => 1.5,
            ],
            'followup' => ['total' => 2, 'activated' => 1, 'returned' => 1],
            'cohortLabel' => '30/10/2026 – 05/11/2026',
            'downloads' => null,
            'accounts' => [['date' => '2026-11-06', 'total' => 1]],
            'pages' => [['label' => 'Page interne témoin', 'total' => 1]],
        ];
        $data = [
            'title' => 'Rapport',
            'period' => '06/11/2026 – 12/11/2026',
            'activity' => $activity,
            'communities' => ['total' => 2, 'active' => 1, 'events' => 3],
            'ranking' => [['name' => 'Communauté interne témoin', 'events' => 3, 'actors' => 1]],
        ];

        $partner = Response::view('admin.system.statistics_pdf', ['partner' => true] + $data)->body();
        $internal = Response::view('admin.system.statistics_pdf', ['partner' => false] + $data)->body();

        self::assertStringContainsString('1 / 2 (50,0 %)', $partner);
        self::assertStringContainsString('Première semaine après l’inscription', $partner);
        self::assertStringContainsString('Indisponible', $partner);
        foreach (['Communauté interne témoin', 'Page interne témoin', 'Comptes web observés', 'Fréquence des connexions'] as $detail) {
            self::assertStringContainsString($detail, $internal);
            self::assertStringNotContainsString($detail, $partner);
        }
    }
}
