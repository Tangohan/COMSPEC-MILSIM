<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Request;
use App\Core\Response;
use App\Support\Wiki\AtakWiki;
use App\Support\Wiki\Search;

/**
 * Wiki ATAK public (/wiki/atak) : accueil, sommaire des apps, une page par app, aide, nouveautés, recherche.
 * Contenu lu dans les fichiers du dépôt (App\Support\Wiki\AtakWiki, docs/wiki-atak.md).
 */
final class AtakWikiController
{
    public function root(Request $request, array $params = []): Response
    {
        return Response::redirect(AtakWiki::url(), 301);
    }

    public function home(Request $request, array $params = []): Response
    {
        $lang = AtakWiki::lang();
        $catalog = AtakWiki::catalog();

        return $this->view('home', [
            'title' => $lang === 'en' ? 'COMSPEC ATAK wiki: every app of the tactical phone for Arma 3' : 'Wiki COMSPEC ATAK : toutes les apps du téléphone tactique pour Arma 3',
            'meta_description' => $lang === 'en'
                ? 'Guide to the COMSPEC ATAK mod for Arma 3: installation, dedicated server, CBA settings, and one page for each of the ' . count($catalog['pages']) . ' phone apps.'
                : 'Guide du mod COMSPEC ATAK pour Arma 3 : installation, serveur dédié, réglages CBA et une page pour chacune des apps du téléphone.',
            'intro' => $catalog['intro'][$lang],
        ]);
    }

    public function apps(Request $request, array $params = []): Response
    {
        $lang = AtakWiki::lang();

        return $this->view('apps', [
            'title' => $lang === 'en' ? 'All COMSPEC ATAK apps by category — ATAK wiki' : 'Toutes les apps COMSPEC ATAK par catégorie — Wiki ATAK',
            'meta_description' => $lang === 'en'
                ? 'Every app of the COMSPEC ATAK phone, by category: navigation, communication, fires, specialties, intelligence, mission, system and civilian.'
                : 'Toutes les apps du téléphone COMSPEC ATAK, par catégorie : navigation, communication, appui et feux, spécialités, renseignement, mission, système et civil.',
        ]);
    }

    public function news(Request $request, array $params = []): Response
    {
        $lang = AtakWiki::lang();

        return $this->view('news', [
            'title' => $lang === 'en' ? 'COMSPEC ATAK release notes — ATAK wiki' : 'Nouveautés de COMSPEC ATAK, version par version — Wiki ATAK',
            'meta_description' => $lang === 'en'
                ? 'What changed in each COMSPEC ATAK version, written for players.'
                : 'Ce qui change à chaque version de COMSPEC ATAK, expliqué pour les joueurs.',
            'releases' => AtakWiki::catalog()['releases'],
        ]);
    }

    public function search(Request $request, array $params = []): Response
    {
        $lang = AtakWiki::lang();
        $query = mb_substr(trim($request->queryString('q')), 0, 120);

        return $this->view('search', [
            'title' => $lang === 'en' ? 'Search the ATAK wiki' : 'Rechercher dans le wiki ATAK',
            'meta_description' => $lang === 'en' ? 'Search every page of the COMSPEC ATAK wiki.' : 'Rechercher dans toutes les pages du wiki COMSPEC ATAK.',
            'seo_robots' => 'noindex,follow',
            'query' => $query,
            'terms' => Search::terms($query),
            'results' => AtakWiki::search($query, $lang),
        ]);
    }

    public function show(Request $request, array $params = []): Response
    {
        $slug = strtolower((string) ($params['slug'] ?? ''));
        $page = preg_match('/^[a-z0-9\-]{1,80}$/', $slug) === 1 ? AtakWiki::page($slug) : null;
        $lang = AtakWiki::lang();
        if ($page === null) {
            return $this->view('missing', [
                'title' => $lang === 'en' ? 'Page not found — ATAK wiki' : 'Page introuvable — Wiki ATAK',
                'meta_description' => '',
                'seo_robots' => 'noindex,follow',
            ])->setStatusCode(404);
        }
        $doc = AtakWiki::render($slug, $lang);
        $title = (string) $page[$lang]['title'];
        $lead = trim((string) $page[$lang]['lead']);
        if ($page['kind'] === 'app') {
            $seoTitle = ($lang === 'en' ? 'ATAK app ' : 'App ATAK ') . $title . ' — ' . ($lang === 'en' ? 'COMSPEC ATAK wiki' : 'Wiki COMSPEC ATAK');
        } else {
            $seoTitle = $title . ' — ' . ($lang === 'en' ? 'COMSPEC ATAK wiki' : 'Wiki COMSPEC ATAK');
        }
        $desc = $lead !== '' ? $title . ' : ' . $lead : mb_substr((string) $page[$lang]['text'], 0, 155);
        if ($lang === 'en') {
            $desc = str_replace(' : ', ': ', $desc);
        }

        return $this->view('page', [
            'title' => $seoTitle,
            'meta_description' => mb_substr($desc, 0, 300),
            'wikiPage' => $page,
            'doc' => $doc,
        ]);
    }

    /** @param array<string, mixed> $data */
    private function view(string $content, array $data): Response
    {
        return Response::view('layout.marketing', $data + [
            'content' => 'site.wiki_atak.' . $content,
            'marketingActive' => 'wiki-atak',
            'marketingStyles' => [asset_url('assets/css/wiki-atak.css')],
            'wikiLang' => AtakWiki::lang(),
            'wikiCatalog' => AtakWiki::catalog(),
        ]);
    }
}
