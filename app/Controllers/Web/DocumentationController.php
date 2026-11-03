<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;

/**
 * Guide utilisateur intégré au site + documentation technique du mod Overwatch.
 */
final class DocumentationController
{
    /** Révision affichée sur le guide intégré (incrémenter lors d’une mise à jour de fond). */
    public const SITE_DOCS_REVISION_NUMBER = 10;

    public const SITE_DOCS_REVISION_DATE_LABEL = '28 juillet 2026';

    /**
     * Fiches techniques du mod (Markdown sous docs/) — pas d’inventaire HTTP / audit BDD.
     *
     * @var array<string, array{rel: string, title: string, section: string, hint: string}>
     */
    private const MOD_ENTRIES = [
        'mod-architecture' => [
            'rel' => 'technique/overwatch-mod/architecture.md',
            'title' => 'Architecture & addons',
            'section' => 'Mod Overwatch',
            'hint' => 'Structure PBO, extension, conventions SQF',
        ],
        'mod-dependances' => [
            'rel' => 'technique/overwatch-mod/bibliotheques-et-dependances.md',
            'title' => 'Bibliothèques & mods utilisés',
            'section' => 'Mod Overwatch',
            'hint' => 'CBA, ACE, cTab, BCE, KAT, Mavic, radios…',
        ],
        'mod-compilation' => [
            'rel' => 'technique/overwatch-mod/compilation.md',
            'title' => 'Compilation & publication',
            'section' => 'Mod Overwatch',
            'hint' => 'Build local, PBO, DLL, Workshop',
        ],
    ];

    /**
     * Guides des qualifications (docs/guides/<fichier>.md en français, <fichier>.en.md en anglais).
     *
     * @var array<string, array{file: string, title: string, hint: string}>
     */
    public const QUALIFICATION_GUIDES = [
        'en-jeu' => [
            'file' => 'guides/qualifications-en-jeu',
            'title' => 'Exploiter les qualifications en jeu (Arma 3)',
            'hint' => 'Cours Academy, séances en jeu, données du mod, validation par l’instructeur et renouvellement, avec des exemples.',
        ],
        'prerequis-automatiques' => [
            'file' => 'guides/qualifications-prerequis-automatiques',
            'title' => 'Prérequis automatiques possibles',
            'hint' => 'Tout ce que le site mesure seul : source, calcul, limites et exemples de règles.',
        ],
        'prerequis-attestes' => [
            'file' => 'guides/qualifications-prerequis-attestes',
            'title' => 'Prérequis manuels (attestation)',
            'hint' => 'Ce qu’un instructeur atteste, comment le faire, quelles preuves garder et ce que le mod pourrait mesurer demain.',
        ],
    ];

    public function index(Request $request, array $params = []): Response
    {
        if (!Session::get('user_id')) {
            Session::flash('error', 'Connectez-vous pour continuer.');

            return Response::redirect(url('login'));
        }

        return Response::view('layout.main', [
            'content' => 'documentation.site.index',
            'title' => 'Guide du portail',
            'siteDocsPage' => true,
            'siteDocsRevisionNumber' => self::SITE_DOCS_REVISION_NUMBER,
            'siteDocsRevisionDateLabel' => self::SITE_DOCS_REVISION_DATE_LABEL,
        ]);
    }

    /**
     * Profils de communauté (Complet, Bureau des effectifs, Carte ATAK) : comparatif des modules,
     * niveaux d’accès et tutoriel « Bien démarrer » du profil de la communauté courante.
     */
    public function profiles(Request $request, array $params = []): Response
    {
        if (!Session::get('user_id')) {
            Session::flash('error', 'Connectez-vous pour continuer.');

            return Response::redirect(url('login'));
        }
        $tenantType = null;
        $tenantId = (int) Session::get('tenant_id');
        if ($tenantId > 0) {
            try {
                $tenant = (new \App\Repositories\TenantRepository())->findById($tenantId);
                if (is_array($tenant)) {
                    $tenantType = \App\Services\Community\TenantTypeConfig::normalizeType((string) ($tenant['tenant_type'] ?? 'full'));
                }
            } catch (\Throwable) {
                $tenantType = null;
            }
        }

        return Response::view('layout.main', [
            'content' => 'documentation.profiles',
            'title' => 'Profils de communauté et accès',
            'docsTenantType' => $tenantType,
        ]);
    }

    public function references(Request $request, array $params = []): Response
    {
        if (!Session::get('user_id')) {
            Session::flash('error', 'Connectez-vous pour continuer.');

            return Response::redirect(url('login'));
        }

        $overviewHtml = $this->loadMarkdownHtml('technique/overwatch-mod/index.md');

        return Response::view('layout.main', [
            'content' => 'documentation.references.index',
            'title' => 'Documentation technique du mod',
            'docEntries' => self::MOD_ENTRIES,
            'modOverviewHtml' => $overviewHtml,
            'siteDocsRefsPage' => true,
        ]);
    }

    public function markersLibrary(Request $request, array $params = []): Response
    {
        if (!Session::get('user_id')) {
            Session::flash('error', 'Connectez-vous pour continuer.');

            return Response::redirect(url('login'));
        }

        return Response::view('layout.main', [
            'content' => 'documentation.site.marqueurs',
            'title' => 'Bibliothèque de marqueurs',
            'siteDocsPage' => true,
            'siteDocsMarkersPage' => true,
            'markerIconsCdn' => function_exists('atak_marker_icons_cdn_base')
                ? atak_marker_icons_cdn_base()
                : rtrim((string) url('assets/markers/arma'), '/'),
        ]);
    }

    public function file(Request $request, array $params = []): Response
    {
        if (!Session::get('user_id')) {
            Session::flash('error', 'Connectez-vous pour continuer.');

            return Response::redirect(url('login'));
        }
        $key = (string) ($params['key'] ?? '');
        if ($key === '' || !isset(self::MOD_ENTRIES[$key])) {
            return Response::redirect(url('documentation/references'));
        }
        $meta = self::MOD_ENTRIES[$key];
        $rel = $meta['rel'];
        $title = $meta['title'];
        $loaded = $this->readDocsFile($rel);
        if ($loaded === null) {
            return Response::view('layout.main', [
                'content' => 'documentation.missing',
                'title' => 'Document introuvable',
                'docTitle' => $title,
                'siteDocsRefsPage' => true,
            ]);
        }

        $html = function_exists('markdown_to_html')
            ? markdown_to_html($loaded)
            : '<pre>' . htmlspecialchars($loaded, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</pre>';

        return Response::view('layout.main', [
            'content' => 'documentation.file',
            'title' => $title,
            'docTitle' => $title,
            'docKey' => $key,
            'docBodyHtml' => $html,
            'siteDocsRefsPage' => true,
        ]);
    }

    /** Sommaire des guides des qualifications. */
    public function qualificationGuides(Request $request, array $params = []): Response
    {
        if (!Session::get('user_id')) {
            Session::flash('error', 'Connectez-vous pour continuer.');

            return Response::redirect(url('login'));
        }

        return Response::view('layout.main', [
            'content' => 'documentation.qualifications.index',
            'title' => 'Guides des qualifications',
            'qualificationGuides' => self::QUALIFICATION_GUIDES,
            'siteDocsRefsPage' => true,
        ]);
    }

    /** Un guide des qualifications, dans la langue du lecteur (repli sur le français). */
    public function qualificationGuide(Request $request, array $params = []): Response
    {
        if (!Session::get('user_id')) {
            Session::flash('error', 'Connectez-vous pour continuer.');

            return Response::redirect(url('login'));
        }
        $slug = (string) ($params['slug'] ?? '');
        if (!isset(self::QUALIFICATION_GUIDES[$slug])) {
            return Response::redirect(url('documentation/qualifications'));
        }
        $meta = self::QUALIFICATION_GUIDES[$slug];
        $body = self::qualificationGuideMarkdown($slug, locale());
        if ($body === null) {
            return Response::view('layout.main', [
                'content' => 'documentation.missing',
                'title' => 'Document introuvable',
                'docTitle' => $meta['title'],
                'siteDocsRefsPage' => true,
            ]);
        }
        // Le titre est celui de la page : on retire le « # » du fichier.
        $body = (string) preg_replace('/\A\s*# [^\n]*\n/u', '', $body);

        return Response::view('layout.main', [
            'content' => 'documentation.qualifications.guide',
            'title' => $meta['title'],
            'qualificationGuides' => self::QUALIFICATION_GUIDES,
            'guideSlug' => $slug,
            'docBodyHtml' => function_exists('markdown_to_html') ? markdown_to_html($body) : '<pre>' . htmlspecialchars($body, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</pre>',
            'siteDocsRefsPage' => true,
        ]);
    }

    /** Texte Markdown d’un guide des qualifications : version anglaise si demandée et présente, sinon française. */
    public static function qualificationGuideMarkdown(string $slug, string $lang): ?string
    {
        $file = self::QUALIFICATION_GUIDES[$slug]['file'] ?? null;
        if ($file === null) {
            return null;
        }
        foreach ($lang === 'en' ? [$file . '.en.md', $file . '.md'] : [$file . '.md'] as $rel) {
            $full = base_path('docs/' . $rel);
            if (is_file($full)) {
                return (string) file_get_contents($full);
            }
        }

        return null;
    }

    private function loadMarkdownHtml(string $rel): string
    {
        $body = $this->readDocsFile($rel);
        if ($body === null) {
            return '<p class="site-docs__refs-lead">Document d’introduction indisponible pour le moment.</p>';
        }
        if (function_exists('markdown_to_html')) {
            return markdown_to_html($body);
        }

        return '<pre>' . htmlspecialchars($body, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</pre>';
    }

    private function readDocsFile(string $rel): ?string
    {
        $full = base_path('docs/' . $rel);
        $docsRoot = realpath(base_path('docs'));
        $resolved = is_file($full) ? realpath($full) : false;
        if ($docsRoot === false || $resolved === false || !str_starts_with($resolved, $docsRoot)) {
            return null;
        }
        $body = (string) file_get_contents($full);
        if (!mb_check_encoding($body, 'UTF-8')) {
            $clean = @iconv('UTF-8', 'UTF-8//IGNORE', $body);
            $body = $clean !== false ? $clean : mb_convert_encoding($body, 'UTF-8', 'UTF-8');
        }

        return $body;
    }
}
