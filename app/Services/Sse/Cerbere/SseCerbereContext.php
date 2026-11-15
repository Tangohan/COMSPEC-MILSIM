<?php

declare(strict_types=1);

namespace App\Services\Sse\Cerbere;

use App\Support\SseLinkCatalog;
use App\Support\SsePortalNav;

/**
 * Contexte du widget Cerbère : où se trouve la personne dans le portail SSE, et de quel élément il s'agit.
 *
 * Le contexte se déduit de l'adresse de la page (jamais d'un paramètre de communauté) :
 *  - « element » : page d'un élément (type du chemin de liaison + identifiant) : fiche personne, identité, papier, contrôle,
 *    dossier, dossier d'intérêt, document, fiche de renseignement, site, transmission, campagne ;
 *  - « list » : liste ou espace de travail (personnes, papiers, avis de recherche, photothèque, droits, campagnes...), rattaché à
 *    une entrée de la navigation ;
 *  - « home » : tableau de bord (accueil, vue opérationnelle, tableau des papiers) ;
 *  - « triage » : la boîte « À trier » elle-même ;
 *  - « page » : le reste (page connue de la navigation ou non).
 * Fonction pure : aucune lecture de base, aucun droit. Les droits sont jugés par SseCerbereWidget.
 */
final class SseCerbereContext
{
    public const KIND_ELEMENT = 'element';
    public const KIND_LIST = 'list';
    public const KIND_HOME = 'home';
    public const KIND_TRIAGE = 'triage';
    public const KIND_PAGE = 'page';

    /** Tableaux de bord : le widget y fait la synthèse de tout ce qui attend. */
    private const HOMES = ['accueil', 'operations', 'workspace', 'papiers-tableau'];

    /**
     * Pages d'élément : motif => [type de liaison, libellé de la page, entrée de navigation].
     * Les motifs les plus précis d'abord ; un suffixe (/pdf, /lecture...) reste dans le contexte de l'élément.
     *
     * @var list<array{0: string, 1: string, 2: string, 3: string}>
     */
    private const ELEMENTS = [
        ['#^papiers/identites/(\d+)(?:/|$)#', 'identity', 'Identité du registre', 'papiers-identites'],
        ['#^papiers/(?:documents|atelier)/(\d+)(?:/|$)#', 'paper', 'Papier', 'papiers'],
        ['#^papiers/controles/(\d+)(?:/|$)#', 'check', 'Contrôle', 'papiers-controles'],
        ['#^(?:identites|personnes)/(\d+)(?:/|$)#', 'person', 'Fiche personne', 'identites'],
        ['#^dossiers/(\d+)(?:/|$)#', 'case', 'Dossier de cas', 'dossiers'],
        ['#^interet/(\d+)(?:/|$)#', 'interest', 'Dossier d’intérêt', 'interet'],
        ['#^documents/(\d+)(?:/|$)#', 'document', 'Document de rédaction', 'documents'],
        ['#^fiches/(\d+)(?:/|$)#', 'note', 'Fiche de renseignement', 'fiches'],
        ['#^sites/(\d+)(?:/|$)#', 'site', 'Site', 'sites'],
        ['#^transmissions/(\d+)(?:/|$)#', 'transmission', 'Transmission de terrain', 'transmissions'],
        ['#^campagnes/(\d+)(?:/|$)#', 'campaign', 'Campagne', 'campagnes'],
    ];

    /** Pages sans élément liable mais dont la famille est précise : motif => [libellé, entrée de navigation]. */
    private const PAGES = [
        ['#^papiers/atelier(?:/|$)#', 'Atelier de documents', 'papiers-atelier'],
        ['#^avis/detections(?:/|$)#', 'Détections d’avis de recherche', 'avis-detections'],
        ['#^avis(?:/|$)#', 'Avis de recherche', 'avis-recherche'],
        ['#^fichiers/photos(?:/|$)#', 'Photothèque', 'fichiers-photos'],
        ['#^fichiers(?:/|$)#', 'Fichiers', 'fichiers'],
        ['#^(?:droits|acces)(?:/|$)#', 'Droits et habilitations', 'droits'],
        ['#^cerberes(?:/|$)#', 'Cerbères', 'cerberes'],
        ['#^toiles/(\d+)(?:/|$)#', 'Investigation', 'toiles'],
    ];

    /**
     * @return array{kind: string, type: string, id: string, nav: string, label: string, section: string}
     */
    public static function fromPath(string $requestPath): array
    {
        $path = trim((string) (parse_url($requestPath, PHP_URL_PATH) ?? ''), '/');
        $pos = strpos($path, 'atak/sse');
        $rest = $pos === false ? '' : trim(substr($path, $pos + strlen('atak/sse')), '/');
        if ($rest === '' || $rest === 'accueil') {
            return self::make(self::KIND_HOME, '', '', 'accueil', 'Accueil du bureau');
        }
        if ($rest === 'a-trier' || str_starts_with($rest, 'a-trier/')) {
            return self::make(self::KIND_TRIAGE, '', '', 'a-trier', 'À trier');
        }
        foreach (self::ELEMENTS as [$pattern, $type, $label, $nav]) {
            if (preg_match($pattern, $rest, $m) === 1 && isset(SseLinkCatalog::TYPES[$type])) {
                return self::make(self::KIND_ELEMENT, $type, (string) (int) $m[1], $nav, $label);
            }
        }
        foreach (self::PAGES as [$pattern, $label, $nav]) {
            if (preg_match($pattern, $rest) === 1) {
                return self::make($nav === 'papiers-atelier' || $nav === 'toiles' ? self::KIND_PAGE : self::KIND_LIST, '', '', $nav, $label);
            }
        }
        $sections = self::navSections();
        $nav = SsePortalNav::activeId($sections, 'atak/sse/' . $rest);
        $found = $nav !== '' ? SsePortalNav::locate($sections, $nav) : null;
        if ($found === null) {
            return self::make(self::KIND_PAGE, '', '', '', 'Bureau SSE');
        }
        $kind = in_array($nav, self::HOMES, true) ? self::KIND_HOME : self::KIND_LIST;

        return self::make($kind, '', '', $nav, (string) $found[1]['label']);
    }

    /** @return array{kind: string, type: string, id: string, nav: string, label: string, section: string} */
    private static function make(string $kind, string $type, string $id, string $nav, string $label): array
    {
        return [
            'kind' => $kind, 'type' => $type, 'id' => $id, 'nav' => $nav, 'label' => $label,
            'section' => $type !== '' ? (string) SseLinkCatalog::TYPES[$type]['section'] : '',
        ];
    }

    /**
     * Navigation complète (tous droits) : sert seulement à reconnaître une page, jamais à décider d'un accès.
     *
     * @return list<array<string, mixed>>
     */
    private static function navSections(): array
    {
        static $cache = null;

        return $cache ??= SsePortalNav::sections(true, true, false, true, true);
    }

    /**
     * Adresse de page acceptée par le point d'accès : chemin du portail SSE uniquement (jamais un domaine, jamais « .. »).
     * Retourne le chemin normalisé « atak/sse/... » ou null.
     */
    public static function sanitizePage(string $page): ?string
    {
        $page = trim($page);
        if ($page === '' || strlen($page) > 300 || preg_match('#[\x00-\x1f\\\\]#', $page) === 1) {
            return null;
        }
        $path = trim((string) (parse_url($page, PHP_URL_PATH) ?? ''), '/');
        $pos = strpos($path, 'atak/sse');
        if ($pos === false || str_contains($path, '..')) {
            return null;
        }
        $path = substr($path, $pos);

        return preg_match('#^atak/sse(/[A-Za-z0-9_.-]+)*$#', $path) === 1 ? $path : null;
    }
}
