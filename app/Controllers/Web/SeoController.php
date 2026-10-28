<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Container;
use App\Core\Request;
use App\Core\Response;
use App\Repositories\CommunityMediaRepository;
use App\Repositories\RecruitmentOpeningRepository;
use App\Repositories\TenantRepository;
use App\Repositories\UnitRepository;
use App\Support\Seo;

final class SeoController
{
    public function robots(Request $request, array $params = []): Response
    {
        $base = rtrim((string) url(''), '/');
        $prefix = rtrim((string) (parse_url($base, PHP_URL_PATH) ?: ''), '/');
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

        // Espaces membres et outils (même liste que la balise robots des pages, App\Support\Seo).
        $disallow = '';
        foreach (Seo::PRIVATE_PREFIXES as $path) {
            $disallow .= 'Disallow: ' . $prefix . $path . "\n";
        }
        $disallow .= 'Disallow: ' . $prefix . "/c/*/forum\n"
            . 'Disallow: ' . $prefix . "/c/*/enlistment\n"
            . 'Disallow: ' . $prefix . "/c/*/setup\n"
            . 'Disallow: ' . $prefix . "/*?*imprimer=\n";

        $body = $mirrorBlock
            . "User-agent: *\n"
            . 'Allow: ' . $prefix . "/\n"
            . $disallow
            . "\nSitemap: {$base}/sitemap.xml\n";

        return (new Response())
            ->header('Content-Type', 'text/plain; charset=utf-8')
            ->setBody($body);
    }

    public function sitemap(Request $request, array $params = []): Response
    {
        $base = rtrim((string) url(''), '/');
        $today = gmdate('Y-m-d');
        // Pages fixes : pas de lastmod (une date « aujourd'hui » à chaque passage fait ignorer le champ par Google).
        $paths = [
            ['/', 'daily', '1.0'],
            ['/communities', 'daily', '0.9'],
            ['/a-propos', 'monthly', '0.8'],
            ['/sse', 'monthly', '0.85'],
            ['/atak-natif', 'monthly', '0.85'],
            ['/traduire-atak', 'monthly', '0.6'],
            ['/traduire-atak/workshop', 'daily', '0.6'],
            ['/soutenir-atak', 'monthly', '0.6'],
            ['/contact', 'monthly', '0.7'],
            ['/nouveautes', 'weekly', '0.8'],
            ['/register', 'monthly', '0.6'],
            ['/join', 'monthly', '0.5'],
            ['/mentions-legales', 'yearly', '0.3'],
            ['/donnees-personnelles', 'yearly', '0.3'],
            ['/cookies', 'yearly', '0.3'],
            ['/cgu', 'yearly', '0.3'],
            ['/cgv', 'yearly', '0.3'],
            ['/legal/site', 'yearly', '0.3'],
            ['/demande-donnees', 'yearly', '0.3'],
        ];

        $urls = [];
        foreach ($paths as [$p, $freq, $prio]) {
            $urls[] = $this->urlEntry($p === '/' ? $base . '/' : $base . $p, null, $freq, $prio);
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
            $media = null;
            try {
                /** @var CommunityMediaRepository $media */
                $media = Container::get(CommunityMediaRepository::class);
            } catch (\Throwable) {
                $media = null;
            }

            // listForRegistry exclut déjà les communautés masquées de l'annuaire : seules les pages publiques sont listées.
            foreach ($tenants->listForRegistry() as $row) {
                $slug = trim((string) ($row['slug'] ?? ''));
                if ($slug === '') {
                    continue;
                }
                $enc = rawurlencode($slug);
                $lastmod = $this->normalizeLastmod($row['updated_at'] ?? $row['created_at'] ?? null);
                $prio = !empty($row['registry_featured']) ? '0.85' : '0.7';
                $urls[] = $this->urlEntry($base . '/c/' . $enc, $lastmod, 'weekly', $prio);

                $tid = (int) ($row['id'] ?? 0);
                if ($tid < 1) {
                    continue;
                }
                if ($media !== null) {
                    try {
                        if ($media->listPublicPageItems($tid) !== []) {
                            $urls[] = $this->urlEntry($base . '/c/' . $enc . '/medias', $lastmod, 'weekly', '0.55');
                        }
                    } catch (\Throwable) {
                        // Médias indisponibles : continuer.
                    }
                }
                try {
                    // Unités dont la page publique est activée (show_on_public_page) uniquement.
                    foreach ($units->listPublicForTenant($tid) as $unit) {
                        if (array_key_exists('show_on_public_page', $unit) && empty($unit['show_on_public_page'])) {
                            continue;
                        }
                        $unitSlug = trim((string) ($unit['slug'] ?? ''));
                        if ($unitSlug === '') {
                            continue;
                        }
                        $unitMod = $this->normalizeLastmod($unit['updated_at'] ?? null) ?? $lastmod;
                        $urls[] = $this->urlEntry(
                            $base . '/c/' . $enc . '/unite/' . rawurlencode($unitSlug),
                            $unitMod,
                            'weekly',
                            '0.65'
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
            . "<urlset xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\" xmlns:xhtml=\"http://www.w3.org/1999/xhtml\">\n"
            . implode("\n", $urls)
            . "\n</urlset>";

        return (new Response())
            ->header('Content-Type', 'application/xml; charset=utf-8')
            ->header('Cache-Control', 'public, max-age=3600')
            ->setBody($xml);
    }

    /** Une entrée du sitemap avec ses variantes de langue (fr, en, x-default). */
    private function urlEntry(string $loc, ?string $lastmod, string $freq, string $prio): string
    {
        $esc = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES | ENT_XML1, 'UTF-8');
        $out = '  <url><loc>' . $esc($loc) . '</loc>';
        foreach (Seo::alternates($loc) as $lang => $href) {
            $out .= '<xhtml:link rel="alternate" hreflang="' . $esc($lang) . '" href="' . $esc($href) . '"/>';
        }
        if ($lastmod !== null && $lastmod !== '') {
            $out .= '<lastmod>' . $esc($lastmod) . '</lastmod>';
        }

        return $out . '<changefreq>' . $esc($freq) . '</changefreq><priority>' . $esc($prio) . '</priority></url>';
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
