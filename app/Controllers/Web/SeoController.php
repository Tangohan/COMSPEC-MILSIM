<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Container;
use App\Core\Request;
use App\Core\Response;
use App\Repositories\RecruitmentOpeningRepository;
use App\Repositories\TenantRepository;
use App\Repositories\UnitRepository;

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
            . "Disallow: /admin/\n"
            . "Disallow: /api/\n"
            . "Disallow: /account/\n"
            . "Disallow: /offline-archive/\n"
            . "Disallow: /modpacks/\n"
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
            ['/communities', 'daily', '0.9'],
            ['/a-propos', 'monthly', '0.8'],
            ['/sse', 'monthly', '0.85'],
            ['/atak-natif', 'monthly', '0.85'],
            ['/contact', 'monthly', '0.7'],
            ['/nouveautes', 'weekly', '0.8'],
            ['/register', 'monthly', '0.6'],
            ['/login', 'monthly', '0.5'],
            ['/join', 'monthly', '0.6'],
            ['/recrutement', 'monthly', '0.55'],
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
            $urls[] = $this->urlEntry($base . $p, $today, $freq, $prio);
        }

        try {
            /** @var TenantRepository $tenants */
            $tenants = Container::get(TenantRepository::class);
            /** @var UnitRepository $units */
            $units = Container::get(UnitRepository::class);
            $openings = null;
            try {
                /** @var RecruitmentOpeningRepository $openings */
                $openings = Container::get(RecruitmentOpeningRepository::class);
            } catch (\Throwable) {
                $openings = null;
            }

            foreach ($tenants->listForRegistry() as $row) {
                $slug = trim((string) ($row['slug'] ?? ''));
                if ($slug === '') {
                    continue;
                }
                $enc = rawurlencode($slug);
                $lastmod = $this->normalizeLastmod($row['updated_at'] ?? $row['created_at'] ?? null) ?? $today;
                $prio = !empty($row['registry_featured']) ? '0.85' : '0.7';
                $urls[] = $this->urlEntry($base . '/c/' . $enc, $lastmod, 'weekly', $prio);
                $urls[] = $this->urlEntry($base . '/c/' . $enc . '/medias', $lastmod, 'weekly', '0.55');
                $urls[] = $this->urlEntry($base . '/c/' . $enc . '/reels', $lastmod, 'weekly', '0.5');

                $tid = (int) ($row['id'] ?? 0);
                if ($tid < 1) {
                    continue;
                }
                try {
                    foreach ($units->listPublicForTenant($tid) as $unit) {
                        $unitSlug = trim((string) ($unit['slug'] ?? ''));
                        if ($unitSlug === '') {
                            continue;
                        }
                        $unitMod = $this->normalizeLastmod($unit['updated_at'] ?? null) ?? $lastmod;
                        $urls[] = $this->urlEntry(
                            $base . '/c/' . $enc . '/unite/' . rawurlencode($unitSlug),
                            $unitMod,
                            'weekly',
                            '0.6'
                        );
                    }
                } catch (\Throwable) {
                    // Unités absentes / schéma partiel : continuer le sitemap.
                }

                if ($openings !== null) {
                    try {
                        foreach ($openings->listPublishedForTenant($tid) as $opening) {
                            $avisSlug = trim((string) ($opening['public_page_slug'] ?? ''));
                            if ($avisSlug === '') {
                                continue;
                            }
                            $avisMod = $this->normalizeLastmod($opening['updated_at'] ?? $opening['published_at'] ?? null) ?? $lastmod;
                            $urls[] = $this->urlEntry(
                                $base . '/c/' . $enc . '/avis/' . rawurlencode($avisSlug),
                                $avisMod,
                                'weekly',
                                '0.65'
                            );
                        }
                    } catch (\Throwable) {
                        // Avis indisponibles : continuer.
                    }
                }
            }
        } catch (\Throwable) {
            // Sitemap partiel si le registre est indisponible.
        }

        $xml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n"
            . "<urlset xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\">\n"
            . implode("\n", $urls)
            . "\n</urlset>";

        return (new Response())
            ->header('Content-Type', 'application/xml; charset=utf-8')
            ->header('Cache-Control', 'public, max-age=3600')
            ->setBody($xml);
    }

    private function urlEntry(string $loc, string $lastmod, string $freq, string $prio): string
    {
        $safeLoc = htmlspecialchars($loc, ENT_QUOTES, 'UTF-8');
        $safeMod = htmlspecialchars($lastmod, ENT_QUOTES, 'UTF-8');
        $safeFreq = htmlspecialchars($freq, ENT_QUOTES, 'UTF-8');
        $safePrio = htmlspecialchars($prio, ENT_QUOTES, 'UTF-8');

        return "  <url><loc>{$safeLoc}</loc><lastmod>{$safeMod}</lastmod><changefreq>{$safeFreq}</changefreq><priority>{$safePrio}</priority></url>";
    }

    private function normalizeLastmod(mixed $value): ?string
    {
        $raw = trim((string) ($value ?? ''));
        if ($raw === '') {
            return null;
        }
        $ts = strtotime($raw);
        if ($ts === false) {
            return null;
        }

        return gmdate('Y-m-d', $ts);
    }
}
