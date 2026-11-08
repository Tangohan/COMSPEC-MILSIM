<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\BackOfficeLegacyRedirects;
use App\Support\BackOfficeTabs;
use PHPUnit\Framework\TestCase;

/**
 * Chaque page du back-office (config/back_office_pages.php) doit être atteignable depuis le menu latéral
 * (views/partials/ath_sidebar_nav.php), avec les droits d'un gestionnaire de communauté.
 *
 * Origine : « Consignes et kit ATAK » avait été ajoutée au méga-menu du portail (config/navigation.php) mais pas au
 * menu latéral du back-office, qui est une liste à part : la page existait sans entrée dans le menu.
 */
final class BackOfficeMenuCoverageTest extends TestCase
{
    /**
     * Pages volontairement absentes du menu, avec la raison. Une nouvelle page n'a pas à figurer ici : elle va dans
     * le menu.
     */
    private const OFF_MENU = [
        'back-office/community/inscription' => 'onglet de Paramètres de la communauté',
        'back-office/integration-membres/modeles/nouveau' => 'formulaire de création ouvert depuis Modèles de parcours',
        'back-office/users/create' => 'formulaire de création ouvert depuis Membres',
        'back-office/organisation/catalogue/modele' => 'détail d’un modèle ouvert depuis le Catalogue',
        'back-office/organisation/catalogue/apercu' => 'aperçu ouvert depuis le Catalogue',
        'back-office/positions' => 'ancienne page des postes, remplacée par l’Organigramme',
        'back-office/roles-permissions' => 'matrice ouverte depuis Rôles et droits',
        'back-office/access-management' => 'ouverte depuis Rôles et droits',
        'back-office/roles/presets' => 'ouverte depuis Rôles et droits',
        'back-office/roles' => 'liste ouverte depuis Rôles et droits',
        'back-office/roles-functions/referentiel' => 'ouverte depuis Fonctions et emplois',
        'back-office/roles-functions/catalogue' => 'ouverte depuis Fonctions et emplois',
        'back-office/roles-functions' => 'ouverte depuis Fonctions et emplois',
        'back-office/personnel-job-roles/kits' => 'ouverte depuis Fonctions et emplois',
        'back-office/personnel-job-roles/assignments' => 'ouverte depuis Fonctions et emplois',
        'back-office/personnel-job-roles' => 'ouverte depuis Fonctions et emplois',
        'back-office/events/reponses-nominatives' => 'détail d’un créneau ouvert depuis Événements',
        'back-office/atak/comptes-rendus/nouveau' => 'formulaire de création ouvert depuis Comptes rendus',
        'back-office/atak/comptes-rendus/modeles/nouveau' => 'formulaire de création ouvert depuis Modèles de debriefing',
        'back-office/atak/realisme/terminaux' => 'journal d’un appareil ouvert depuis Parc de terminaux',
        'back-office/ressources/atak-realism' => 'préfixe des réglages, servis en onglet de Parc de terminaux',
        'messages' => 'messagerie du portail, hors back-office',
        'back-office/conformite' => 'préfixe de Export pour contrôle (back-office/conformite/export-dossier)',
        'back-office/recruitment' => 'préfixe des offres (Offres publiées)',
        'back-office/cooperation' => 'préfixe des coopérations (Coopérations inter-unités)',
        'back-office/ressources' => 'préfixe des ressources',
        'formation' => 'Formations (lien calculé par training_lms_admin_path())',
        'documents/gestion/ajout' => 'formulaire d’ajout ouvert depuis Gestion documentaire',
    ];

    /**
     * Chemins (sans requête) des liens du menu latéral, lus dans la source : url('…'), effectifs_workspace_url('…')
     * et les quelques liens calculés.
     *
     * @return array<string, true>
     */
    public static function asidePaths(): array
    {
        $src = (string) file_get_contents(dirname(__DIR__, 2) . '/views/partials/ath_sidebar_nav.php');
        $paths = [
            'back-office/ressources/effectifs' => true,
            'orbat' => true,
            'back-office/cooperation/missions' => true,
            'back-office/cooperation/missions/create' => true,
        ];
        preg_match_all("#\\burl\\('([^']+)'\\)#", $src, $m);
        foreach ($m[1] as $u) {
            $paths[trim((string) strtok($u, '?#'), '/')] = true;
        }
        preg_match_all("#effectifs_workspace_url\\('([^']*)'\\)#", $src, $m);
        foreach ($m[1] as $u) {
            $paths['back-office/ressources/effectifs/' . trim((string) strtok($u, '?#'), '/')] = true;
        }

        return $paths;
    }

    public function testConsignesIsInTheAtakSection(): void
    {
        $src = (string) file_get_contents(dirname(__DIR__, 2) . '/views/partials/ath_sidebar_nav.php');
        self::assertMatchesRegularExpression(
            "#\\\$atakResChildren = \\[.*'Consignes et kit ATAK', 'href' => url\\('back-office/ressources/atak-consignes'\\)#s",
            $src
        );
    }

    public function testEveryBackOfficePageIsReachableFromTheMenu(): void
    {
        $root = dirname(__DIR__, 2);
        $aside = self::asidePaths();
        $tabs = [];
        foreach (BackOfficeTabs::FAMILIES as $home => $family) {
            foreach ($family['tabs'] as $tab) {
                $tabs[$tab['legacy']] = $home;
            }
        }
        $pages = (require $root . '/config/back_office_pages.php')['pages'];
        $quick = [];
        foreach ($pages as $page) {
            if (isset($aside[$page['path']])) {
                foreach ($page['quick'] ?? [] as $q) {
                    $quick[trim((string) strtok((string) $q['href'], '?#'), '/')] = true;
                }
            }
        }

        $missing = [];
        foreach ($pages as $page) {
            $path = (string) $page['path'];
            if (str_contains($path, '{') || isset($aside[$path]) || isset($quick[$path])
                || isset(BackOfficeLegacyRedirects::MAP[$path]) || isset(self::OFF_MENU[$path])) {
                continue;
            }
            if (isset($tabs[$path])) {
                self::assertArrayHasKey($tabs[$path], $aside, 'Onglet ' . $path . ' : sa page d’accueil doit être dans le menu.');
                continue;
            }
            $missing[] = $path;
        }
        self::assertSame([], $missing, 'Pages du back-office sans entrée dans le menu latéral (views/partials/ath_sidebar_nav.php).');
    }

    public function testMiseANiveauStepsPointToMenuPages(): void
    {
        $aside = self::asidePaths();
        foreach (require dirname(__DIR__, 2) . '/config/configuration_release_steps.php' as $step) {
            $path = trim((string) strtok($step['path'], '?#'), '/');
            // Les pages du bureau SSE ont leur propre menu (App\Support\SsePortalNav).
            if (str_starts_with($path, 'atak/sse')) {
                continue;
            }
            self::assertArrayHasKey($path, $aside, 'Étape ' . $step['code'] . ' : page absente du menu.');
        }
    }

    public function testRecentPagesAreVisibleToACommunityManager(): void
    {
        $root = dirname(__DIR__, 2);
        require_once $root . '/app/Support/helpers.php';
        if (is_file($root . '/app/Support/navigation_menu.php')) {
            require_once $root . '/app/Support/navigation_menu.php';
        }
        $manager = ['admin.organization', 'admin.access'];
        $recent = array_column((require $root . '/config/back_office_menu.php')['new'], 'path');
        $recent[] = 'back-office/ressources/atak-consignes';
        foreach (array_unique($recent) as $path) {
            $rule = back_office_nav_rule_for_path($path);
            if ($rule === null) {
                continue;
            }
            $granted = array_merge((array) ($rule['any_permissions'] ?? []), isset($rule['permission']) ? [(string) $rule['permission']] : []);
            self::assertNotSame([], array_intersect($manager, $granted), $path . ' : un gestionnaire de communauté ne la verrait pas dans le menu.');
        }
    }
}
