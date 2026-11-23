<?php

declare(strict_types=1);

namespace App\Services\Admin;

use App\Core\Response;
use App\Repositories\TenantAnalyticsRepository;
use App\Support\Pdf\AthenaPdfTheme;
use DateTimeImmutable;
use DateTimeZone;
use RuntimeException;

final class PlatformStatisticsPdf
{
    public function __construct(private TenantAnalyticsRepository $analytics)
    {
    }

    /** @return array{filename: string, binary: string} */
    public function export(int $days, bool $partner): array
    {
        $activity = (new PlatformDashboardActivity($this->analytics))->overview($days, !$partner);
        $communities = $activity['communities'];
        $ranking = $activity['communityRanking'];

        // Les détails d’exploitation ne sont pas transmis au gabarit partenaires.
        if ($partner) {
            unset($activity['pages'], $activity['accounts'], $activity['engagement']['ages'], $activity['engagement']['lastLogins']);
        }
        $title = $partner ? 'Synthèse partenaires' : 'Rapport d’exploitation';
        $start = new DateTimeImmutable($activity['from'], new DateTimeZone('UTC'));
        $lastDay = (new DateTimeImmutable($activity['until'], new DateTimeZone('UTC')))->modify('-1 day');
        $period = $start->format('d/m/Y') . ' – ' . $lastDay->format('d/m/Y');
        $format = $partner ? 'partenaires' : 'interne';
        $meta = [
            'title' => $title,
            'kind' => 'Statistiques Athena',
            'reference' => $lastDay->format('Y-m-d') . ' / ' . $activity['days'] . ' J',
            'classification' => $partner ? 'Données agrégées' : 'Diffusion restreinte',
            'audience' => $partner ? 'Partenaires' : 'Administration Athena',
        ];
        $body = Response::view('admin.system.statistics_pdf', compact(
            'activity', 'communities', 'ranking', 'partner', 'title', 'period'
        ))->body();
        $html = AthenaPdfTheme::dompdfDocument($body, $meta, <<<'CSS'
h1 { font-size: 25pt; margin: 0 0 3mm; }
h2 { font-size: 13pt; margin: 6mm 0 2mm; color: #164e46; page-break-after: avoid; }
p { margin: 0 0 2mm; }
.period, .note { color: #4b5d59; }
.note { font-size: 8pt; }
table.report { margin-bottom: 2mm; table-layout: fixed; }
.report th, .report td { padding: 1.6mm 2mm; border-bottom: 0.5pt solid #dce6e2; overflow-wrap: break-word; }
.report thead th { text-align: left; background: #e9f4ef; }
.report th[scope="row"] { text-align: left; font-weight: normal; }
.report .num { text-align: right; }
tr { page-break-inside: avoid; }
.daily-value { font-weight: bold; }
.daily-track { height: 2px; margin-top: 1mm; background: #e6edeb; }
.daily-bar { height: 2px; background: #208672; }
CSS);
        $binary = AthenaPdfTheme::dompdfRender($html, $meta);
        if ($binary === null) {
            throw new RuntimeException('Le moteur PDF n’a produit aucun document.');
        }

        return [
            'filename' => 'athena-statistiques-' . $format . '-' . $activity['days'] . 'j-' . $lastDay->format('Y-m-d') . '.pdf',
            'binary' => $binary,
        ];
    }
}
