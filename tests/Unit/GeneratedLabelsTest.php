<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Repositories\TenantGeneratedLabelRepository;
use App\Services\I18n\GeneratedLabels;
use App\Services\Organization\PositionCapabilityCatalog;
use App\Services\OrganizationCatalog\OrganizationKitDefinitions;
use App\Support\HtmlTranslator;
use PDO;
use PHPUnit\Framework\TestCase;
use Tests\Support\MysqlDdlSqlitePdo;

require_once dirname(__DIR__, 2) . '/bootstrap/migration_runner.php';

/**
 * Libellés générés (unités et emplois des modèles, postes types, types d’unité) : catalogue synchronisé avec les
 * générateurs, résolution communauté > traduction > défaut, noms saisis jamais traduits, table rejouable.
 */
final class GeneratedLabelsTest extends TestCase
{
    private const SOG = 'Groupement d’opérations spéciales';

    private static function root(): string
    {
        return dirname(__DIR__, 2);
    }

    protected function setUp(): void
    {
        GeneratedLabels::useRepository(new TenantGeneratedLabelRepository(self::pdo()), self::root());
    }

    protected function tearDown(): void
    {
        GeneratedLabels::useRepository(null);
        HtmlTranslator::reset();
    }

    private static function pdo(): PDO
    {
        $pdo = new MysqlDdlSqlitePdo();
        $pdo->exec('CREATE TABLE tenants (id INTEGER PRIMARY KEY, name TEXT, slug TEXT)');
        $pdo->exec("INSERT INTO tenants (id, name, slug) VALUES (1, 'Tris Unity Group', 'tris-unity-group'), (2, 'Autre', 'autre')");
        $pdo->exec('CREATE TABLE units (id INTEGER PRIMARY KEY, tenant_id INTEGER, slug TEXT, name TEXT)');
        $stmt = $pdo->prepare('INSERT INTO units (tenant_id, slug, name) VALUES (?, ?, ?)');
        foreach ([[1, 'ussof-groupement', self::SOG], [1, 'peloton-maison', '1er peloton'], [2, 'ussof-groupement', self::SOG]] as $row) {
            $stmt->execute($row);
        }
        self::runStep($pdo);

        return $pdo;
    }

    private static function runStep(PDO $pdo): string
    {
        $root = self::root();
        $migrationFlush = static function (): void {
        };
        $migrationEnsurePdo = static function (): void {
        };
        require $root . '/database/steps/_context.php';
        ob_start();
        try {
            require $root . '/database/steps/3295_tenant_generated_labels.php';
        } finally {
            $out = (string) ob_get_clean();
        }

        return $out;
    }

    public function testStepCreatesTheTableOnceAndTheSecondPassChangesNothing(): void
    {
        $pdo = new MysqlDdlSqlitePdo();
        $pdo->exec('CREATE TABLE tenants (id INTEGER PRIMARY KEY, name TEXT, slug TEXT)');
        $pdo->changes = 0;
        $pdo->ddl = [];
        self::assertStringContainsString('créée', self::runStep($pdo));
        self::assertNotSame([], $pdo->ddl);
        $pdo->ddl = [];
        $pdo->changes = 0;
        self::assertStringContainsString('déjà présente', self::runStep($pdo));
        self::assertSame([], $pdo->ddl);
        self::assertSame(0, $pdo->changes);

        $sql = (string) file_get_contents(self::root() . '/migrations/2026_11_01_020_tenant_generated_labels.sql');
        self::assertStringContainsString('UNIQUE KEY `uk_tenant_generated_labels_key` (`tenant_id`, `label_key`, `lang`)', $sql);
        // Noms de contraintes uniques dans tout le schéma.
        foreach (['uk_tenant_generated_labels_key', 'fk_tenant_generated_labels_tenant'] as $name) {
            $hits = 0;
            foreach (glob(self::root() . '/migrations/*.sql') ?: [] as $file) {
                $hits += substr_count((string) file_get_contents($file), '`' . $name . '`');
            }
            self::assertSame(1, $hits, $name);
        }
    }

    public function testCatalogCoversEveryGeneratorAndEnglishHasEveryKey(): void
    {
        $fr = GeneratedLabels::catalog('fr');
        $en = GeneratedLabels::catalog('en');
        foreach (OrganizationKitDefinitions::officialKits() as $kit) {
            foreach (['units' => 'unit', 'job_role_categories' => 'category', 'job_roles' => 'job'] as $part => $kind) {
                foreach ($kit[$part] ?? [] as $row) {
                    self::assertSame($row['name'], $fr[$kind][$row['slug']] ?? null, $kit['code'] . ' : ' . $kind . '.' . $row['slug']);
                }
            }
        }
        foreach (array_merge(PositionCapabilityCatalog::headquartersBillets(), PositionCapabilityCatalog::combatGroupBillets()) as $b) {
            self::assertNotSame([], GeneratedLabels::keysFor('post', $b['title']), $b['title']);
        }
        foreach (['Commandant', 'Adjoint', 'Opérateur', 'Soutien', 'Instructeur', 'Stagiaire'] as $title) {
            self::assertNotSame([], GeneratedLabels::keysFor('post', $title), $title);
        }
        foreach (['commandement', 'operationnel', 'soutien', 'formation'] as $code) {
            self::assertArrayHasKey($code, $fr['unit_type']);
        }
        foreach ($fr as $kind => $list) {
            foreach ($list as $id => $text) {
                self::assertStringNotContainsString('.', (string) $id, 'Identifiant sans point (clé de traduction).');
                self::assertNotSame('', trim($en[$kind][$id] ?? ''), 'Anglais manquant : ' . $kind . '.' . $id);
            }
        }
        self::assertSame(count(GeneratedLabels::keys()), count(array_unique(GeneratedLabels::keys())));
    }

    public function testGeneratedUnitIsShownInTheReaderLanguage(): void
    {
        self::assertSame(self::SOG, GeneratedLabels::display(1, 'unit', self::SOG, 'ussof-groupement', 'fr'));
        self::assertSame('Special Operations Group', GeneratedLabels::display(1, 'unit', self::SOG, 'ussof-groupement', 'en'));
        // Sans adresse courte (fiche membre) : reconnu au nom exact.
        self::assertSame('Special Operations Group', GeneratedLabels::display(1, 'unit', self::SOG, null, 'en'));
        self::assertSame('Squad leader', GeneratedLabels::display(1, 'post', 'Chef de groupe', 'N-10-SL', 'en'));
        self::assertSame('Command', GeneratedLabels::display(1, 'unit_type', 'Commandement', 'commandement', 'en'));
    }

    public function testNamesTypedByTheCommunityAreNeverTranslated(): void
    {
        // Unité du modèle renommée par la communauté.
        self::assertSame('Groupement Spectre', GeneratedLabels::display(1, 'unit', 'Groupement Spectre', 'ussof-groupement', 'en'));
        // Même texte que le modèle mais autre unité (adresse courte différente) : nom choisi par la communauté.
        self::assertSame(self::SOG, GeneratedLabels::display(1, 'unit', self::SOG, 'groupement-maison', 'en'));
        self::assertSame('Alpha', GeneratedLabels::display(1, 'unit', 'Alpha', null, 'en'));
        // Nom seul (fiche membre) : « 1er peloton » saisi à la main (aucune unité de modèle sous ce nom) reste tel quel.
        self::assertSame('1er peloton', GeneratedLabels::display(1, 'unit', '1er peloton', null, 'en'));
        self::assertSame('1st platoon', GeneratedLabels::display(1, 'unit', '1er peloton', 'usinf-1-peloton', 'en'));
        self::assertSame('', GeneratedLabels::display(1, 'unit', '', null, 'en'));
    }

    public function testCommunityOverrideWinsPerLanguageAndResetRestoresTheDefault(): void
    {
        $pdo = self::pdo();
        $repo = new TenantGeneratedLabelRepository($pdo);
        GeneratedLabels::useRepository($repo, self::root());
        $repo->put(1, 'unit.ussof-groupement', 'en', 'SOG Spectre', 7, '2026-11-01 10:00:00');
        $repo->put(1, 'unit.ussof-groupement', 'fr', 'GOS Spectre', 7, '2026-11-01 10:00:00');
        GeneratedLabels::forget(1);

        self::assertSame('SOG Spectre', GeneratedLabels::display(1, 'unit', self::SOG, 'ussof-groupement', 'en'));
        self::assertSame('GOS Spectre', GeneratedLabels::display(1, 'unit', self::SOG, 'ussof-groupement', 'fr'));
        // Langue publiée par la communauté du site, sans traduction : l’anglais de la communauté.
        self::assertSame('SOG Spectre', GeneratedLabels::display(1, 'unit', self::SOG, 'ussof-groupement', 'zz'));
        // Une autre communauté garde la traduction du site.
        self::assertSame('Special Operations Group', GeneratedLabels::display(2, 'unit', self::SOG, 'ussof-groupement', 'en'));

        // Mise à jour d’une ligne existante (clé unique tenant + clé + langue).
        $repo->put(1, 'unit.ussof-groupement', 'en', 'SOG Phantom', 7, '2026-11-01 11:00:00');
        self::assertSame(2, (int) $pdo->query('SELECT COUNT(*) FROM tenant_generated_labels')->fetchColumn());
        GeneratedLabels::forget(1);
        self::assertSame('SOG Phantom', GeneratedLabels::resolve(1, 'unit.ussof-groupement', 'en'));

        // Texte vide = retour au libellé par défaut ; delete sans langue = toutes les langues.
        $repo->put(1, 'unit.ussof-groupement', 'en', '  ', 7, '2026-11-01 12:00:00');
        GeneratedLabels::forget(1);
        self::assertSame('Special Operations Group', GeneratedLabels::resolve(1, 'unit.ussof-groupement', 'en'));
        $repo->delete(1, 'unit.ussof-groupement');
        GeneratedLabels::forget(1);
        self::assertSame(self::SOG, GeneratedLabels::resolve(1, 'unit.ussof-groupement', 'fr'));
        self::assertSame(0, (int) $pdo->query('SELECT COUNT(*) FROM tenant_generated_labels')->fetchColumn());
    }

    public function testSameTextInSeveralModelsPrefersTheKeyTheCommunityCustomised(): void
    {
        $repo = new TenantGeneratedLabelRepository(self::pdo());
        GeneratedLabels::useRepository($repo, self::root());
        $keys = GeneratedLabels::keysFor('job', 'Chef de groupe');
        self::assertGreaterThan(1, count($keys));
        $repo->put(1, $keys[1], 'en', 'Team boss', null, '2026-11-01 10:00:00');
        GeneratedLabels::forget(1);
        self::assertSame('Team boss', GeneratedLabels::display(1, 'job', 'Chef de groupe', null, 'en'));
    }

    public function testMissingTableFallsBackToSiteTranslations(): void
    {
        GeneratedLabels::useRepository(new TenantGeneratedLabelRepository(new PDO('sqlite::memory:')), self::root());
        self::assertSame('Special Operations Group', GeneratedLabels::display(1, 'unit', self::SOG, 'ussof-groupement', 'en'));
    }

    public function testOrbatTreeKeepsTheStoredNameForEditingAndLocksTranslation(): void
    {
        $tree = [
            'unitId' => 10,
            'label' => self::SOG,
            'billets' => [['id' => 1, 'title' => 'Chef de groupe'], ['id' => 2, 'title' => 'Chef radio maison']],
            'commander' => ['label' => 'VIPER', 'title' => 'Commandant'],
            'children' => [
                ['unitId' => 11, 'label' => 'Commandement', 'children' => []],
            ],
        ];
        $out = GeneratedLabels::localizeOrbat($tree, 1, [10 => 'ussof-groupement', 11 => 'unite-maison'], 'en');
        self::assertSame('Special Operations Group', $out['label']);
        self::assertSame(self::SOG, $out['labelSource']);
        self::assertSame(['label'], $out['noTranslate']);
        self::assertSame('Squad leader', $out['billets'][0]['title']);
        self::assertSame('Chef de groupe', $out['billets'][0]['titleSource']);
        self::assertSame('Chef radio maison', $out['billets'][1]['title']);
        self::assertArrayNotHasKey('titleSource', $out['billets'][1]);
        self::assertSame('Commander', $out['commander']['title']);
        // Nom saisi par la communauté (adresse courte hors modèle) : intact, et protégé de la traduction du JSON.
        self::assertSame('Commandement', $out['children'][0]['label']);
        self::assertArrayNotHasKey('labelSource', $out['children'][0]);

        // Rejouable dans une autre langue (feuille PDF) depuis le nom enregistré.
        $fr = GeneratedLabels::localizeOrbat($out, 1, [10 => 'ussof-groupement'], 'fr');
        self::assertSame(self::SOG, $fr['label']);
        self::assertArrayNotHasKey('labelSource', $fr);
        self::assertSame('Chef de groupe', $fr['billets'][0]['title']);

        HtmlTranslator::useDictionary(['Commandement' => 'Command', 'Commandant' => 'Commander', 'Poste vacant' => 'Vacant post']);
        $json = (string) json_encode(['roster' => $out, 'message' => 'Poste vacant'], JSON_UNESCAPED_UNICODE);
        $translated = json_decode(HtmlTranslator::translateJson($json), true);
        self::assertSame('Commandement', $translated['roster']['children'][0]['label']);
        self::assertSame('Vacant post', $translated['message']);
    }

    public function testAnonymizedUnitKeepsItsConfidentialMentionTranslatableAndNeverLeaksTheName(): void
    {
        $node = \App\Services\Organization\OrgVisibilityService::anonymizeUnitNode(
            ['unitId' => 10, 'label' => self::SOG, 'labelSource' => self::SOG, 'children' => []]
        );
        $out = GeneratedLabels::localizeOrbat($node, 1, [10 => 'ussof-groupement'], 'en');
        self::assertSame(\App\Support\ConfidentialDisplay::label(\App\Support\ConfidentialDisplay::UNIT_ANONYMIZED), $out['label']);
        self::assertArrayNotHasKey('labelSource', $out);
        self::assertArrayNotHasKey('noTranslate', $out);
        self::assertStringNotContainsString(self::SOG, (string) json_encode($out, JSON_UNESCAPED_UNICODE));
        self::assertSame('', unit_name_translate_attr($out['label']));
        self::assertSame(' translate="no"', unit_name_translate_attr('Groupement Spectre'));
    }
}
