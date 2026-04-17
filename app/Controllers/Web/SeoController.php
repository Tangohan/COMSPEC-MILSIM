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
        $body = "User-agent: *\n"
            . "Allow: /\n"
            . "Disallow: /back-office/\n"
            . "Disallow: /api/\n"
            . "Disallow: /account/\n"
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
            '/',
            '/register',
            '/login',
            '/join',
            '/mentions-legales',
            '/donnees-personnelles',
            '/cookies',
            '/cgu',
            '/cgv',
            '/demande-donnees',
        ];

        $urls = [];
        foreach ($paths as $p) {
            $loc = htmlspecialchars($base . $p, ENT_QUOTES, 'UTF-8');
            $urls[] = "  <url><loc>{$loc}</loc><lastmod>{$today}</lastmod><changefreq>weekly</changefreq><priority>0.7</priority></url>";
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
