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
use App\Support\DevDispatchCatalog;
use App\Support\Seo;

final class SeoController
{
    /**
     * Pages publiques fixes : chemin, fréquence, priorité, vue dont la date de modification sert de lastmod
     * (jamais « aujourd'hui » à chaque passage, que Google ignorerait). Les adresses qui redirigent
     * (/cgu, /cookies…) et les pages non indexées (/register, /join) n'y figurent pas.
     *
     * @var list<array{0: string, 1: string, 2: string, 3: string}>
     */
    public const STATIC_PAGES = [
        ['/', 'daily', '1.0', 'home/index'],
        ['/communities', 'daily', '0.9', 'community/registry'],
        ['/a-propos', 'monthly', '0.8', 'site/about'],
        ['/notre-histoire', 'weekly', '0.7', 'site/history'],
        ['/atak-natif', 'monthly', '0.85', 'site/atak_native'],
        ['/sse', 'monthly', '0.8', 'site/sse'],
        ['/academy', 'monthly', '0.6', 'academy/home'],
        ['/nouveautes', 'weekly', '0.8', 'site/changelog'],
        ['/traduire-atak', 'monthly', '0.6', 'site/atak_translate'],
        ['/traduire-atak/workshop', 'daily', '0.6', 'site/atak_translate_workshop'],
        ['/traduire-site', 'monthly', '0.5', 'site/site_translate'],
        ['/contribuer', 'monthly', '0.5', 'site/contribute'],
        ['/soutenir-atak', 'monthly', '0.6', 'atak/support'],
        ['/contact', 'monthly', '0.7', 'site/contact'],
        ['/legal/site', 'yearly', '0.3', 'legal/site'],
        ['/demande-donnees', 'yearly', '0.3', 'legal/gdpr_request'],
    ];

    public function robots(Request $request, array $params = []): Response
    {
        // Toujours le domaine actuel, y compris quand le fichier est lu sur l'ancien domaine.
        $base = Seo::baseUrl();
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
        foreach ([...Seo::PRIVATE_PREFIXES, ...Seo::PRIVATE_CHILDREN] as $path) {
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
        $base = Seo::baseUrl();
        $urls = [];
        foreach (self::STATIC_PAGES as [$p, $freq, $prio, $view]) {
            $urls[] = $this->urlEntry($p === '/' ? $base . '/' : $base . $p, $this->viewLastmod($view), $freq, $prio);
        }

        // Bulletins du journal des nouveautés (SPOTREP, TECHREP, UPDATE) : une page chacun, datée.
        try {
            foreach (DevDispatchCatalog::all() as $dispatch) {
                $kind = (string) ($dispatch['kind'] ?? '');
                $number = (string) ($dispatch['number_pad'] ?? '');
                if ($kind === '' || $number === '') {
                    continue;
                }
                $urls[] = $this->urlEntry(
                    $base . '/nouveautes/' . rawurlencode($kind) . '/' . rawurlencode($number),
                    $this->normalizeLastmod($dispatch['date'] ?? null),
                    'yearly',
                    '0.4'
                );
            }
        } catch (\Throwable) {
            // Journal indisponible : continuer.
        }

        // Wiki ATAK : accueil, sommaire, nouveautés et une page par sujet (date du fichier source).
        try {
            $wiki = \App\Support\Wiki\AtakWiki::catalog();
            $urls[] = $this->urlEntry($base . '/wiki/atak', null, 'weekly', '0.8');
            $urls[] = $this->urlEntry($base . '/wiki/atak/apps', null, 'weekly', '0.7');
            $urls[] = $this->urlEntry($base . '/wiki/atak/nouveautes', null, 'weekly', '0.6');
            foreach ($wiki['pages'] as $slug => $page) {
                $mtime = (int) ($page['mtime'] ?? 0);
                $urls[] = $this->urlEntry(
                    $base . '/wiki/atak/' . rawurlencode((string) $slug),
                    $mtime > 0 ? gmdate('Y-m-d', $mtime) : null,
                    'monthly',
                    '0.6'
                );
            }
        } catch (\Throwable) {
            // Fichiers du wiki illisibles : continuer.
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
                    // Unités dont la page publique est activée (show_on_public_page) uniquement,
                    // jamais une unité masquée, anonymisée ou restreinte (son adresse trahirait son nom).
                    $unitLevels = \App\Services\Organization\OrgVisibilityService::effectiveUnitLevels($units->allForTenant($tid));
                    foreach ($units->listPublicForTenant($tid) as $unit) {
                        if (array_key_exists('show_on_public_page', $unit) && empty($unit['show_on_public_page'])) {
                            continue;
                        }
                        if (($unitLevels[(int) ($unit['id'] ?? 0)] ?? \App\Support\VisibilityLevel::NORMAL) !== \App\Support\VisibilityLevel::NORMAL) {
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

    /** Date de dernière modification de la vue d'une page fixe (aaaa-mm-jj), null si inconnue. */
    private function viewLastmod(string $view): ?string
    {
        $file = base_path('views/' . $view . '.php');
        $mtime = is_file($file) ? @filemtime($file) : false;

        return $mtime !== false ? gmdate('Y-m-d', $mtime) : null;
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
