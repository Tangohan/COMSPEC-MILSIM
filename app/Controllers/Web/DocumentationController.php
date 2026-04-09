<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;

/**
 * Guide utilisateur intégré au site + références projet (fichiers sources sous docs/).
 */
final class DocumentationController
{
    /** Révision affichée sur le guide intégré (incrémenter lors d’une mise à jour de fond). */
    public const SITE_DOCS_REVISION_NUMBER = 5;

    public const SITE_DOCS_REVISION_DATE_LABEL = '9 avril 2026';

    /** @var array<string, array{rel: string, title: string, section: string}> */
    private const ENTRIES = [
        'routes' => ['rel' => 'ROUTES.md', 'title' => 'Référence des routes HTTP', 'section' => 'Technique'],
        'inventaire' => ['rel' => 'INVENTAIRE-FONCTIONNALITES.md', 'title' => 'Inventaire fonctionnalités', 'section' => 'Technique'],
        'navigation' => ['rel' => 'utilisateur/tableau-de-bord-et-navigation.md', 'title' => 'Tableau de bord & navigation', 'section' => 'Utilisateur'],
        'back-office' => ['rel' => 'utilisateur/back-office-organisation.md', 'title' => 'Back-office organisation', 'section' => 'Utilisateur'],
        'premiers-pas' => ['rel' => 'utilisateur/premiers-pas.md', 'title' => 'Premiers pas', 'section' => 'Utilisateur'],
        'connexion-compte' => ['rel' => 'utilisateur/connexion-et-compte.md', 'title' => 'Connexion et compte', 'section' => 'Utilisateur'],
        'faq' => ['rel' => 'utilisateur/faq.md', 'title' => 'FAQ utilisateur', 'section' => 'Utilisateur'],
        'formations' => ['rel' => 'utilisateur/formations.md', 'title' => 'Formations (LMS)', 'section' => 'Utilisateur'],
        'forum' => ['rel' => 'utilisateur/forum.md', 'title' => 'Forum', 'section' => 'Utilisateur'],
        'recherche' => ['rel' => 'utilisateur/recherche-et-raccourcis.md', 'title' => 'Recherche & raccourcis', 'section' => 'Utilisateur'],
        'technique-readme' => ['rel' => 'technique/README.md', 'title' => 'Documentation technique (index)', 'section' => 'Technique'],
        'modules' => ['rel' => 'technique/modules-fonctionnels.md', 'title' => 'Modules fonctionnels', 'section' => 'Technique'],
    ];

    public function index(Request $request, array $params = []): Response
    {
        if (!Session::get('user_id')) {
            Session::flash('error', 'Authentification requise.');

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

    public function references(Request $request, array $params = []): Response
    {
        if (!Session::get('user_id')) {
            Session::flash('error', 'Authentification requise.');

            return Response::redirect(url('login'));
        }

        return Response::view('layout.main', [
            'content' => 'documentation.references.index',
            'title' => 'Références projet',
            'docEntries' => self::ENTRIES,
            'siteDocsRefsPage' => true,
        ]);
    }

    public function file(Request $request, array $params = []): Response
    {
        if (!Session::get('user_id')) {
            Session::flash('error', 'Authentification requise.');

            return Response::redirect(url('login'));
        }
        $key = (string) ($params['key'] ?? '');
        if ($key === '' || !isset(self::ENTRIES[$key])) {
            return Response::redirect(url('documentation/references'));
        }
        $rel = self::ENTRIES[$key]['rel'];
        $title = self::ENTRIES[$key]['title'];
        $full = base_path('docs/' . $rel);
        $docsRoot = realpath(base_path('docs'));
        $resolved = is_file($full) ? realpath($full) : false;
        if ($docsRoot === false || $resolved === false || !str_starts_with($resolved, $docsRoot)) {
            return Response::view('layout.main', [
                'content' => 'documentation.missing',
                'title' => 'Document introuvable',
                'docTitle' => $title,
                'siteDocsRefsPage' => true,
            ]);
        }
        $body = (string) file_get_contents($full);

        return Response::view('layout.main', [
            'content' => 'documentation.file',
            'title' => $title,
            'docTitle' => $title,
            'docKey' => $key,
            'docBody' => $body,
            'siteDocsRefsPage' => true,
        ]);
    }
}
