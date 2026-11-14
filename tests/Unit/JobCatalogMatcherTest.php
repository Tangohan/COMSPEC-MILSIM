<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\JobCatalog\JobCatalog;
use App\Support\JobCatalog\JobCatalogHints;
use App\Support\JobCatalog\JobCatalogMatcher as M;
use PHPUnit\Framework\TestCase;

/**
 * Emplois & fonctions : un emploi saisi par la communauté est reconnu dans le référentiel (« Hop hop ! Ça existe dans
 * notre référentiel »), largement (synonymes, langues, abrégés, codes) et en tenant compte de la nation et de l'unité.
 */
final class JobCatalogMatcherTest extends TestCase
{
    private const ROOT = __DIR__ . '/../../';

    private static function best(array $job, int $min = M::SUGGEST): ?array
    {
        return M::match($job, 1, $min)[0] ?? null;
    }

    public function testExactNameInAnyLanguageIsRecognised(): void
    {
        foreach (['Chuteur opérationnel', 'CHUTEUR OPERATIONNEL', 'Tireur d’élite', 'Sniper'] as $name) {
            $hit = self::best(['name' => $name]);
            self::assertNotNull($hit, $name);
            self::assertSame(100, $hit['score'], $name);
        }
    }

    public function testSynonymsAbbreviationsAndCodesAreRecognised(): void
    {
        // Famille « santé » : infirmier, médecin, medic… ; code MOS exact.
        self::assertNotNull(self::best(['name' => 'Infirmier']));
        self::assertNotNull(self::best(['name' => 'Medic']));
        $code = self::best(['name' => '18D']);
        self::assertSame('code', $code['reasons'][0] ?? '');
        self::assertSame('US', $code['country']);
        self::assertNotNull(self::best(['name' => 'JTAC']));
        self::assertNotNull(self::best(['name' => 'Opérateur radio']));
        self::assertNotNull(self::best(['name' => 'Pilote hélico']));
        // Pluriel, casse et accents ne comptent pas.
        self::assertNotNull(self::best(['name' => 'operateurs radios']));
    }

    public function testNationAndUnitDecideBetweenCloseJobs(): void
    {
        self::assertSame('GB', self::best(['name' => 'Sniper', 'unit' => '22 SAS'])['country']);
        self::assertSame('DE', self::best(['name' => 'KSK Kommandosoldat'])['country']);
        self::assertSame('RU', self::best(['name' => 'Radio spetsnaz'])['country']);
        // Pays de la communauté : départage à score égal.
        self::assertSame('FR', self::best(['name' => 'Chef de groupe', 'country' => 'FR'])['country']);
        self::assertSame('GB', M::nation('Chef de patrouille du SAS'));
        self::assertSame('US', M::nation('ODA Green Berets'));
        self::assertSame('MY', M::nation('Royal Malaysian Navy PASKAL'));
        self::assertSame('', M::nation('Responsable des transmissions'));
    }

    /** Un nom tapé en français ne doit pas enfermer dans les postes français : nation jouée et choix du pays. */
    public function testNationChoiceAndOtherCountries(): void
    {
        // Nation jouée US : le poste américain passe devant, même tapé en français.
        self::assertSame('US', self::best(['name' => 'Infirmier', 'nation' => 'US'])['country']);
        self::assertSame('GB', self::best(['name' => 'Tireur d’élite', 'nation' => 'GB'])['country']);
        // « Voir dans : États-Unis » : seulement des postes américains.
        $only = M::match(['name' => 'Infirmier', 'only' => 'US'], 5, M::SUGGEST);
        self::assertNotSame([], $only);
        foreach ($only as $hit) {
            self::assertSame('US', $hit['country']);
        }
        // Le meilleur équivalent de chaque pays, un par pays.
        $per = M::perCountry(['name' => 'Tireur d’élite']);
        $countries = array_column($per, 'country');
        self::assertSame(count($countries), count(array_unique($countries)));
        foreach (['US', 'FR', 'GB', 'RU'] as $c) {
            self::assertContains($c, $countries, $c);
        }
        $ctrl = (string) file_get_contents(self::ROOT . 'app/Controllers/Admin/JobCatalogController.php');
        self::assertStringContainsString("'only' => self::countryParam((string) \$request->queryString('pays', ''))", $ctrl);
        $js = (string) file_get_contents(self::ROOT . 'public/assets/js/job-catalog-detect.js');
        self::assertStringContainsString('Voir dans :', $js);
        self::assertStringContainsString('Le même poste dans un autre pays', (string) file_get_contents(self::ROOT . 'views/admin/effectifs_workspace/job_editor.php'));
    }

    public function testUnrelatedJobsAreLeftAlone(): void
    {
        foreach (['Cuisinier', 'Community manager', 'Recruteur Discord', 'Chef'] as $name) {
            self::assertNull(self::best(['name' => $name], M::NOTE), $name);
        }
        self::assertSame([], M::match(['name' => 'a']));
    }

    public function testHintsSayWhatTheImportBrings(): void
    {
        $hint = JobCatalogHints::for(['name' => '18D'], 'fr', 1)[0];
        self::assertGreaterThan(0, $hint['counts']['qualifications']);
        self::assertSame('2 qualifications, 1 poste type, 3 sources', JobCatalogHints::gains(['qualifications' => 2, 'billets' => 1, 'sources' => 3]));
        self::assertSame('', JobCatalogHints::gains(['qualifications' => 0, 'billets' => 0, 'sources' => 0]));
        // Emploi déjà rempli depuis le référentiel (même nom, description reprise) : pas de note.
        $entry = JobCatalog::find($hint['id'], 'fr');
        self::assertTrue(JobCatalogHints::alreadyImported((string) $entry['name'], (string) $entry['description'] . "\nNotes de l’unité.", $hint['id'], 'fr'));
        self::assertFalse(JobCatalogHints::alreadyImported((string) $entry['name'], '', $hint['id'], 'fr'));
    }

    public function testDetectionIsWiredInTheJobsPage(): void
    {
        $routes = (string) file_get_contents(self::ROOT . 'routes/web.php');
        self::assertStringContainsString("get('/back-office/ressources/effectifs/fonctions/catalogue/detecter', [\\App\\Controllers\\Admin\\JobCatalogController::class, 'detect']", $routes);
        $editor = (string) file_get_contents(self::ROOT . 'views/admin/effectifs_workspace/job_editor.php');
        self::assertStringContainsString('data-job-detect data-url=', $editor);
        self::assertStringContainsString('data-job-catalog-show=', $editor);
        self::assertStringContainsString('eff-jobs-catalog-note', (string) file_get_contents(self::ROOT . 'views/admin/effectifs_workspace/job_browser.php'));
        self::assertStringContainsString('job-catalog-detect.js', (string) file_get_contents(self::ROOT . 'views/admin/effectifs_workspace/fonctions.php'));
        $js = (string) file_get_contents(self::ROOT . 'public/assets/js/job-catalog-detect.js');
        self::assertStringContainsString('Hop hop !', $js);
        $catalogJs = (string) file_get_contents(self::ROOT . 'public/assets/js/job-catalog.js');
        self::assertStringContainsString('trigger.dataset.jobCatalogShow', $catalogJs);
        // La fiche demandée s'affiche sans attendre le catalogue entier (fenêtre jamais vide).
        self::assertStringContainsString("'emploi=' + encodeURIComponent(id)", $catalogJs);
        self::assertStringContainsString("\$request->queryString('emploi', '')", (string) file_get_contents(self::ROOT . 'app/Controllers/Admin/JobCatalogController.php'));
        // Seuls les emplois créés par la communauté reçoivent une note.
        $ctrl = (string) file_get_contents(self::ROOT . 'app/Controllers/Admin/EffectifsWorkspaceController.php');
        self::assertStringContainsString("=== JobRoleEnrichmentService::ORIGIN_COMMUNITY));", $ctrl);
    }
}
