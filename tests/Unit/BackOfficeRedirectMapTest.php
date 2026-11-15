<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\BackOfficeLegacyRedirects;
use App\Support\BackOfficeTabs;
use PHPUnit\Framework\TestCase;
use Tests\Support\BackOfficeRouteMap as Map;

/**
 * Audit du back-office, lot 7.5 : chaque ancienne adresse redirige vers une cible qui existe, en un seul saut.
 *
 * Les fonctions de redirection de routes/web.php sont exécutées (elles ne touchent ni base ni session) ; la cible
 * est ensuite cherchée dans la carte des routes, comme le ferait le Router. Les chaînes déjà présentes sont figées
 * ci-dessous : le test empêche d'en ajouter.
 */
final class BackOfficeRedirectMapTest extends TestCase
{
    /**
     * Redirections qui mènent à une autre redirection (deux sauts) : 'ancienne adresse' => 'étape intermédiaire'.
     * Chaque ligne est un écart réel à corriger en pointant directement vers la page finale.
     * Vide depuis la vague de tri : les quatre chaînes relevées par l'audit pointent vers la page finale.
     *
     * @var array<string, string>
     */
    private const KNOWN_CHAINS = [];

    /**
     * Redirections du back-office qui répondent 302 : la règle du plan (« aucune adresse supprimée : 301 ») demande une
     * redirection permanente pour toute page déplacée. Il y en avait 136 à l'audit ; il reste /back-office/renseignement,
     * qui n'est pas une page déplacée mais l'entrée du module vers sa page par défaut.
     */
    private const MAX_TEMPORARY_BACK_OFFICE_REDIRECTS = 1;

    private static function isRedirectPath(string $path): bool
    {
        $route = Map::match('GET', Map::samplePath($path));

        return $route !== null && (Map::isRedirect($route) || isset(BackOfficeLegacyRedirects::map()[trim($path, '/')]));
    }

    /** Vérifie une cible : la route existe, n'est pas elle-même une redirection, et l'onglet demandé existe. */
    private static function problemWithTarget(string $targetPath, string $query): ?string
    {
        $route = Map::match('GET', $targetPath);
        if ($route === null) {
            return 'cible sans route : ' . $targetPath;
        }
        if (self::isRedirectPath($targetPath)) {
            return 'deuxième saut : ' . $targetPath . ' redirige à son tour';
        }
        parse_str($query, $q);
        $tab = (string) ($q['onglet'] ?? '');
        $family = BackOfficeTabs::FAMILIES[trim($targetPath, '/')] ?? null;
        if ($tab !== '' && $family !== null && !isset($family['tabs'][$tab])) {
            return 'onglet inconnu « ' . $tab . ' » sur ' . $targetPath;
        }

        return null;
    }

    /** @return list<array{method:string,path:string,pattern:string,handler:mixed,middleware:array}> redirections qui répondent vraiment (non masquées) */
    private static function liveRedirects(): array
    {
        $out = [];
        foreach (Map::routes() as $route) {
            if ($route['method'] !== 'GET' || !Map::isRedirect($route)) {
                continue;
            }
            // Une redirection masquée par une route enregistrée avant elle ne répond jamais.
            if (Map::match('GET', Map::samplePath($route['path'])) !== $route) {
                continue;
            }
            $out[] = $route;
        }

        return $out;
    }

    public function testEveryLegacyMapEntryIsServedByARedirectToARealPage(): void
    {
        $bad = [];
        foreach (BackOfficeLegacyRedirects::map() as $legacy => $target) {
            $route = Map::match('GET', '/' . $legacy);
            if ($route === null || !Map::isRedirectClosure($route)) {
                $bad[] = $legacy . ' : aucune redirection ne répond (route ' . ($route === null ? 'absente' : Map::key($route)) . ')';
                continue;
            }
            $qPos = strpos($target, '?');
            $path = '/' . trim($qPos === false ? $target : substr($target, 0, $qPos), '/');
            $problem = self::problemWithTarget($path, $qPos === false ? '' : substr($target, $qPos + 1));
            if ($problem !== null) {
                $bad[] = $legacy . ' → ' . $target . ' : ' . $problem;
            }
        }
        self::assertSame([], $bad);
    }

    public function testEveryTabRedirectTargetsAnExistingTabOfItsFamily(): void
    {
        $bad = [];
        foreach (BackOfficeTabs::legacyRedirects() as $legacy => $target) {
            [$base, $query] = array_pad(explode('?', $target, 2), 2, '');
            parse_str($query, $q);
            $family = BackOfficeTabs::FAMILIES[$base] ?? null;
            if ($family === null || !isset($family['tabs'][(string) ($q['onglet'] ?? '')])) {
                $bad[] = $legacy . ' → ' . $target;
            }
            // L'ancienne adresse d'un onglet ne doit plus être une page : ni route de contrôleur, ni famille.
            if (isset(BackOfficeTabs::FAMILIES[$legacy])) {
                $bad[] = $legacy . ' est à la fois un onglet retiré et une page d’accueil';
            }
        }
        self::assertSame([], $bad);
    }

    public function testLegacyMapRedirectsAreMovedPermanentlyAndKeepTheQuery(): void
    {
        $bad = [];
        foreach (array_keys(BackOfficeLegacyRedirects::map()) as $legacy) {
            $route = Map::match('GET', '/' . $legacy);
            $red = $route === null ? null : Map::redirectOf($route);
            if ($red === null) {
                $bad[] = $legacy . ' : redirection non exécutable';
                continue;
            }
            if ($red[0] !== 301) {
                $bad[] = $legacy . ' : code ' . $red[0];
            }
        }
        self::assertSame([], $bad);
        // La requête d'origine (filtres, favoris) survit, l'onglet de la cible prime.
        $loc = (string) BackOfficeLegacyRedirects::location('back-office/ressources/effectifs/alertes', ['q' => 'x', 'onglet' => 'vieux']);
        self::assertStringContainsString('onglet=alertes', $loc);
        self::assertStringContainsString('q=x', $loc);
    }

    /** Toutes les redirections de la table des routes (alias /admin, /backoffice, anciennes pages…) arrivent sur une page réelle. */
    public function testEveryRedirectOfTheRouteTableLeadsToARealPageInOneHop(): void
    {
        $executed = 0;
        $bad = [];
        $chains = [];
        foreach (self::liveRedirects() as $route) {
            $red = Map::redirectOf($route);
            if ($red === null) {
                continue; // redirection portée par un contrôleur (non exécutée ici)
            }
            ++$executed;
            [, $path, $query] = $red;
            $problem = self::problemWithTarget($path, $query);
            if ($problem === null) {
                continue;
            }
            if (str_starts_with($problem, 'deuxième saut')) {
                $chains[$route['path']] = $path;
                continue;
            }
            $bad[] = $route['path'] . ' → ' . $path . ($query !== '' ? '?' . $query : '') . ' : ' . $problem;
        }
        self::assertGreaterThan(100, $executed, 'les redirections de la table des routes doivent être exécutées');
        self::assertSame([], $bad, 'Redirection vers une cible absente ou un onglet inconnu.');
        $new = array_diff_key($chains, self::KNOWN_CHAINS);
        self::assertSame([], $new, 'Nouvelle chaîne de redirections (ancienne adresse → étape → page) : pointer directement vers la page finale.');
    }

    /** Les chaînes figées existent encore ; sinon la liste est périmée et la ligne doit partir. */
    public function testKnownChainsAreStillChains(): void
    {
        $stale = [];
        foreach (array_keys(self::KNOWN_CHAINS) as $legacy) {
            $route = Map::match('GET', $legacy);
            $red = $route === null ? null : Map::redirectOf($route);
            if ($red === null || !self::isRedirectPath($red[1])) {
                $stale[] = $legacy;
            }
        }
        self::assertSame([], $stale, 'Chaîne corrigée : retirer la ligne de KNOWN_CHAINS.');
    }

    /** Une redirection ne boucle jamais sur elle-même. */
    public function testNoRedirectPointsAtItself(): void
    {
        $bad = [];
        foreach (self::liveRedirects() as $route) {
            $red = Map::redirectOf($route);
            if ($red !== null && $red[1] === Map::samplePath($route['path']) && $red[2] === '') {
                $bad[] = $route['path'];
            }
        }
        self::assertSame([], $bad);
    }

    /** Le plan demande 301 pour toute page déplacée : on ne laisse pas grandir le nombre de 302 du back-office. */
    public function testBackOfficeLegacyRedirectsDoNotAddTemporaryOnes(): void
    {
        $temporary = [];
        foreach (self::liveRedirects() as $route) {
            if (!Map::isBackOfficePath($route['path'])) {
                continue;
            }
            $red = Map::redirectOf($route);
            if ($red !== null && $red[0] === 302) {
                $temporary[] = $route['path'];
            }
        }
        self::assertLessThanOrEqual(
            self::MAX_TEMPORARY_BACK_OFFICE_REDIRECTS,
            count($temporary),
            'Nouvelle redirection 302 sous /back-office, /backoffice ou /admin : utiliser une redirection permanente (301) pour une page déplacée.'
        );
    }
}
