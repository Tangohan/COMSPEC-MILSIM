<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Request;
use App\Core\Response;

final class SeoController
{
    public function robots(Request $request, array $params = []): Response
    {
        $base = rtrim((string) url(''), '/');
        // Bots de mirroring : Disallow total (complément ; non contraignant pour les scrapers malhonnêtes).
        $mirrorAgents = [
            'HTTrack',
            'OfflineExplorer',
            'SiteSucker',
            'WebCopier',
            'WebZIP',
            'WebReaper',
            'Teleport',
            'Xaldon',
            'wget',
            'libwww-perl',
        ];
        $mirrorBlock = '';
        foreach ($mirrorAgents as $agent) {
            $mirrorBlock .= "User-agent: {$agent}\nDisallow: /\n\n";
        }

        $body = $mirrorBlock
            . "User-agent: *\n"
            . "Allow: /\n"
            . "Disallow: /back-office/\n"
            . "Disallow: /api/\n"
            . "Disallow: /account/\n"
            . "Disallow: /offline-archive/\n"
            . "Sitemap: {$base}/sitemap.xml\n";

        return (new Response())
            ->header('Content-Type', 'text/plain; charset=utf-8')
            ->setBody($body);
    }

    public function sitemap(Request $request, array $params = []): Response
    {
        $base = rtrim((string) url(''), '/');
        $today = gmdate('Y-m-d');
        $paths = [
            ['/', 'daily', '1.0'],
            ['/a-propos', 'monthly', '0.8'],
            ['/contact', 'monthly', '0.7'],
            ['/nouveautes', 'weekly', '0.8'],
            ['/register', 'monthly', '0.6'],
            ['/login', 'monthly', '0.5'],
            ['/join', 'monthly', '0.6'],
            ['/mentions-legales', 'yearly', '0.3'],
            ['/donnees-personnelles', 'yearly', '0.3'],
            ['/cookies', 'yearly', '0.3'],
            ['/cgu', 'yearly', '0.3'],
            ['/cgv', 'yearly', '0.3'],
            ['/legal/site', 'yearly', '0.3'],
            ['/demande-donnees', 'yearly', '0.4'],
        ];

        $urls = [];
        foreach ($paths as [$p, $freq, $prio]) {
            $loc = htmlspecialchars($base . $p, ENT_QUOTES, 'UTF-8');
            $urls[] = "  <url><loc>{$loc}</loc><lastmod>{$today}</lastmod><changefreq>{$freq}</changefreq><priority>{$prio}</priority></url>";
        }

        $xml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n"
            . "<urlset xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\">\n"
            . implode("\n", $urls)
            . "\n</urlset>";

        return (new Response())
            ->header('Content-Type', 'application/xml; charset=utf-8')
            ->setBody($xml);
    }
}
