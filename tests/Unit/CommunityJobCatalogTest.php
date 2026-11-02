<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Controllers\Admin\JobCatalogController;
use App\Core\Database;
use App\Core\Gate;
use App\Core\Request;
use App\Core\Session;
use App\Repositories\JobCatalogCollectionRepository;
use App\Support\JobCatalog\CommunityJobCatalog;
use App\Support\JobCatalog\JobCatalog;
use InvalidArgumentException;
use PDO;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use Tests\Support\MysqlDdlSqlitePdo;

/**
 * Catalogues d’emplois de communauté : étape de migration rejouable, contrôle des champs, création et modification,
 * droits, partage public en lecture seule, isolation entre communautés, journal d’audit et format de la fenêtre.
 */
final class CommunityJobCatalogTest extends TestCase
{
    private MysqlDdlSqlitePdo $pdo;
    private mixed $previousPdo;
    private array $globals;
    private array $permissions;
    private mixed $sessionStarted;

    protected function setUp(): void
    {
        JobCatalog::useDirectory(null);
        $this->sessionStarted = (new ReflectionProperty(Session::class, 'started'))->getValue();
        (new ReflectionProperty(Session::class, 'started'))->setValue(null, true);
        $this->globals = [$_GET, $_POST, $_SERVER, $_SESSION ?? [], $_FILES];
        $this->permissions = Gate::getInstance()->permissionSlugs();
        $this->previousPdo = (new ReflectionProperty(Database::class, 'pdo'))->getValue();
        $this->pdo = new MysqlDdlSqlitePdo();
        $this->pdo->exec("CREATE TABLE tenants (id INTEGER PRIMARY KEY, name TEXT, settings TEXT)");
        $this->pdo->exec("INSERT INTO tenants (id, name, settings) VALUES (14, 'Task Force Phantom', '{}'), (15, 'Fondation 07', '{}')");
        $this->pdo->exec('CREATE TABLE audit_logs (id INTEGER PRIMARY KEY AUTOINCREMENT, tenant_id INTEGER, user_id INTEGER, action TEXT, entity_type TEXT, entity_id INTEGER, old_value TEXT, new_value TEXT, created_at TEXT)');
        (new ReflectionProperty(Database::class, 'pdo'))->setValue(null, $this->pdo);
    }

    protected function tearDown(): void
    {
        (new ReflectionProperty(Database::class, 'pdo'))->setValue(null, $this->previousPdo);
        (new ReflectionProperty(Session::class, 'started'))->setValue(null, $this->sessionStarted);
        Gate::getInstance()->setPermissions($this->permissions);
        [$_GET, $_POST, $_SERVER, $_SESSION, $_FILES] = $this->globals;
    }

    private function migrate(): string
    {
        $pdo = $this->pdo;
        $root = dirname(__DIR__, 2);
        ob_start();
        try {
            require $root . '/database/steps/3480_job_catalog_collections.php';
        } finally {
            $out = (string) ob_get_clean();
        }

        return $out;
    }

    public function testStepCreatesBothTablesOnceAndAReplayChangesNothing(): void
    {
        $repo = new JobCatalogCollectionRepository($this->pdo);
        self::assertFalse($repo->schemaReady());
        self::assertStringContainsString('job_catalog_collections, job_catalog_collection_entries', $this->migrate());
        $this->pdo->changes = 0;
        $this->pdo->ddl = [];
        self::assertStringContainsString('déjà présentes', $this->migrate());
        self::assertSame(0, $this->pdo->changes, implode(' | ', $this->pdo->ddl));
        self::assertTrue((new JobCatalogCollectionRepository($this->pdo))->schemaReady());
        self::assertFileExists(dirname(__DIR__, 2) . '/migrations/2026_11_02_060_job_catalog_collections.sql');
        $sql = (string) file_get_contents(dirname(__DIR__, 2) . '/migrations/2026_11_02_060_job_catalog_collections.sql');
        foreach (['uk_jcc_tenant_name', 'uk_jcce_collection_name', 'fk_jcc_tenant', 'fk_jcce_collection', 'fk_jcce_tenant'] as $constraint) {
            self::assertStringContainsString($constraint, $sql);
        }
    }

    public function testFieldsAreCheckedAndCleanedLikeTheOfficialCatalog(): void
    {
        $c = CommunityJobCatalog::normalizeCollection(['name' => '  SCP   Foundation ', 'color' => '#AA3300', 'icon' => 'biohazard', 'is_public' => '1']);
        self::assertSame(['name' => 'SCP Foundation', 'description' => '', 'icon' => 'biohazard', 'color' => '#aa3300', 'is_public' => 1], $c);
        self::assertSame(['', '', 0], [CommunityJobCatalog::normalizeCollection(['name' => 'W40k', 'color' => 'red', 'icon' => 'nope'])['color'], CommunityJobCatalog::normalizeCollection(['name' => 'W40k', 'icon' => 'nope'])['icon'], CommunityJobCatalog::normalizeCollection(['name' => 'W40k'])['is_public']]);
        $e = CommunityJobCatalog::normalizeEntry(['name' => 'Agent de terrain MTF', 'abbreviation' => 'mtf-e11', 'type' => 'pirate', 'qualifications' => "- Niveau 2\r\n\r\n• Confinement\nNiveau 2", 'tags' => 'SCP, Terrain, SCP']);
        self::assertSame('MTFE11', $e['abbreviation']);
        self::assertSame('operator', $e['type']);
        self::assertSame(['Niveau 2', 'Confinement'], $e['qualifications']);
        self::assertSame(['SCP', 'Terrain'], $e['tags']);
        foreach ([['name' => ''], ['name' => 'X'], ['name' => str_repeat('a', 121)]] as $bad) {
            try {
                CommunityJobCatalog::normalizeEntry($bad);
                self::fail('Nom refusé attendu : ' . json_encode($bad));
            } catch (InvalidArgumentException) {
                self::addToAssertionCount(1);
            }
        }
        $this->expectException(InvalidArgumentException::class);
        CommunityJobCatalog::normalizeCollection(['name' => str_repeat('a', 81)]);
    }

    public function testCommunitiesOnlySeeTheirOwnCatalogsUnlessShared(): void
    {
        $this->migrate();
        $repo = new JobCatalogCollectionRepository($this->pdo);
        $scp = $repo->saveCollection(14, 0, CommunityJobCatalog::normalizeCollection(['name' => 'SCP Foundation', 'color' => '#7a1f1f']), null, null, 5)['id'];
        $entry = $repo->saveEntry(14, $scp, 0, CommunityJobCatalog::normalizeEntry(['name' => 'Agent MTF Epsilon-11', 'abbreviation' => 'E11', 'category' => 'Mobile Task Forces', 'unit' => 'MTF Epsilon-11', 'qualifications' => 'Niveau 2']), null, 5)['id'];
        try {
            $repo->saveCollection(14, 0, CommunityJobCatalog::normalizeCollection(['name' => 'scp foundation']), null, null, 5);
            self::fail('Nom en double refusé attendu.');
        } catch (InvalidArgumentException $e) {
            self::assertStringContainsString('déjà ce nom', $e->getMessage());
        }
        // Le même nom reste libre pour une autre communauté.
        $other = $repo->saveCollection(15, 0, CommunityJobCatalog::normalizeCollection(['name' => 'SCP Foundation']), null, null, 9)['id'];
        self::assertNotSame($scp, $other);

        // Isolation : la communauté 15 ne voit ni ne modifie le catalogue privé de la 14.
        self::assertSame([$other], array_map('intval', array_column($repo->visibleCollections(15), 'id')));
        foreach ([
            fn () => $repo->saveCollection(15, $scp, CommunityJobCatalog::normalizeCollection(['name' => 'Piraté']), null, null, 9),
            fn () => $repo->deleteCollection(15, $scp, 9),
            fn () => $repo->saveEntry(15, $scp, 0, CommunityJobCatalog::normalizeEntry(['name' => 'Intrus']), null, 9),
            fn () => $repo->saveEntry(15, $other, $entry, CommunityJobCatalog::normalizeEntry(['name' => 'Intrus']), null, 9),
            fn () => $repo->deleteEntry(15, $entry, 9),
        ] as $attempt) {
            try {
                $attempt();
                self::fail('Écriture dans le catalogue d’une autre communauté acceptée.');
            } catch (InvalidArgumentException) {
                self::addToAssertionCount(1);
            }
        }
        self::assertSame('SCP Foundation', $this->pdo->query("SELECT name FROM job_catalog_collections WHERE id = $scp")->fetchColumn());

        // Partage public : visible en lecture seule, avec le nom de la communauté auteur.
        $repo->saveCollection(14, $scp, CommunityJobCatalog::normalizeCollection(['name' => 'SCP Foundation', 'is_public' => '1']), null, null, 5);
        $visible = $repo->visibleCollections(15);
        self::assertSame([$other, $scp], array_map('intval', array_column($visible, 'id')), 'Les siens d’abord, puis les partagés.');
        $catalog = CommunityJobCatalog::toCatalog($visible, $repo->entriesFor(array_column($visible, 'id')), 15, 'fr', static fn (string $p): string => '/' . $p);
        $shared = $catalog['countries'][1];
        self::assertSame(['c' . $scp, 'SCP Foundation', false, 'Task Force Phantom', 1], [$shared['code'], $shared['label'], $shared['editable'], $shared['author'], $shared['count']]);
        self::assertTrue($catalog['countries'][0]['editable']);
        self::assertSame('', $catalog['countries'][0]['author']);
        $row = $catalog['entries'][0];
        self::assertSame(['c' . $scp . '-' . $entry, 'c' . $scp, 'Agent MTF Epsilon-11', 'E11', 'Mobile Task Forces', 'MTF Epsilon-11', ['Niveau 2'], false, 'Task Force Phantom'],
            [$row['id'], $row['country'], $row['official_name'], $row['abbreviation'], $row['branch_label'], $row['formation'], $row['qualifications'], $row['editable'], $row['author']]);
        self::assertSame([], $row['names'], 'Langue du nom saisi inconnue : aucune liste par langue.');
        self::assertSame('Opérateur', $row['type_label']);

        // Suppression : le catalogue et ses emplois partent, chemins d’images rendus pour effacement ; tout est journalisé.
        $repo->saveEntry(14, $scp, $entry, CommunityJobCatalog::normalizeEntry(['name' => 'Agent MTF Epsilon-11']), 'uploads/community-media/14/img-a.jpg', 5);
        self::assertSame(['uploads/community-media/14/img-a.jpg'], $repo->deleteCollection(14, $scp, 5));
        self::assertSame(0, (int) $this->pdo->query("SELECT COUNT(*) FROM job_catalog_collection_entries WHERE collection_id = $scp")->fetchColumn());
        $actions = $this->pdo->query('SELECT action FROM audit_logs WHERE tenant_id = 14 ORDER BY id')->fetchAll(PDO::FETCH_COLUMN);
        self::assertSame(['personnel.job_catalog_collection.created', 'personnel.job_catalog_entry.created', 'personnel.job_catalog_collection.updated', 'personnel.job_catalog_entry.updated', 'personnel.job_catalog_collection.deleted'], $actions);
    }

    private function post(string $method, array $post, array $permissions = ['personnel.assignments.manage'], int $tenant = 14): array
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SESSION = ['user_id' => 7, 'tenant_id' => $tenant, '_csrf_token' => str_repeat('a', 64)];
        $_POST = $post + ['_csrf_token' => str_repeat('a', 64)];
        $_FILES = [];
        Gate::getInstance()->setPermissions($permissions);
        $response = (new JobCatalogController())->{$method}(new Request());

        return [$response->statusCode(), json_decode($response->body(), true)];
    }

    public function testWindowEndpointsCheckRightsTokenAndServeCommunityCatalogs(): void
    {
        [$status, $body] = $this->post('saveCollection', ['name' => 'Warhammer 40k']);
        self::assertSame(503, $status, 'Sans l’étape, les catalogues de communauté sont annoncés pour la mise à jour.');
        $this->migrate();
        self::assertSame(403, $this->post('saveCollection', ['name' => 'Warhammer 40k'], [])[0], 'Mêmes droits que la gestion des emplois.');
        self::assertSame(419, $this->post('saveCollection', ['name' => 'Warhammer 40k', '_csrf_token' => 'faux'])[0]);
        self::assertSame(422, $this->post('saveCollection', ['name' => ''])[0]);
        [$status, $body] = $this->post('saveCollection', ['name' => 'Warhammer 40k', 'description' => 'Astra Militarum', 'color' => '#334455', 'icon' => 'shield'], ['admin.roles.manage']);
        self::assertSame(200, $status, (string) ($body['message'] ?? ''));
        $code = (string) $body['code'];
        $id = (int) substr($code, 1);
        [$status, $body] = $this->post('saveEntry', ['collection_id' => (string) $id, 'name' => 'Commissaire', 'abbreviation' => 'COM', 'category' => 'Astra Militarum', 'type' => 'command', 'qualifications' => "Schola Progenium"]);
        self::assertSame(200, $status, (string) ($body['message'] ?? ''));
        self::assertSame($code . '-1', $body['id']);
        self::assertSame(404, $this->post('saveEntry', ['collection_id' => (string) $id, 'entry_id' => '1', 'name' => 'Intrus'], ['personnel.assignments.manage'], 15)[0], 'Emploi d’une autre communauté.');
        self::assertSame(422, $this->post('saveEntry', ['collection_id' => (string) $id, 'name' => 'Intrus'], ['personnel.assignments.manage'], 15)[0], 'Catalogue d’une autre communauté.');
        self::assertSame('Commissaire', $this->pdo->query('SELECT name FROM job_catalog_collection_entries WHERE id = 1')->fetchColumn());

        // La fenêtre reçoit le catalogue comme un pays, recherchable et filtrable par le script.
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_GET = [];
        $_SESSION = ['user_id' => 7, 'tenant_id' => 14];
        Gate::getInstance()->setPermissions(['personnel.assignments.manage']);
        $json = json_decode((new JobCatalogController())->catalogue(new Request())->body(), true);
        self::assertTrue($json['community_ready']);
        self::assertSame($code, end($json['countries'])['code']);
        self::assertSame('Warhammer 40k', end($json['countries'])['label']);
        $mine = array_values(array_filter($json['entries'], static fn (array $e): bool => $e['country'] === $code));
        self::assertSame(['Commissaire'], array_column($mine, 'name'));
        self::assertTrue($mine[0]['editable']);
        self::assertSame('Commandement et encadrement', $mine[0]['type_label']);
        self::assertSame(['US', 'FR', 'DE', 'CN', $code], array_column($json['countries'], 'code'));

        // Une autre communauté ne voit pas le catalogue privé.
        $_SESSION = ['user_id' => 9, 'tenant_id' => 15];
        $json = json_decode((new JobCatalogController())->catalogue(new Request())->body(), true);
        self::assertSame(['US', 'FR', 'DE', 'CN'], array_column($json['countries'], 'code'));

        // Suppression par la communauté auteur seulement.
        self::assertSame(404, $this->post('deleteEntry', ['entry_id' => '1'], ['personnel.assignments.manage'], 15)[0]);
        self::assertSame(200, $this->post('deleteEntry', ['entry_id' => '1'])[0]);
        self::assertSame(404, $this->post('deleteCollection', ['collection_id' => (string) $id], ['personnel.assignments.manage'], 15)[0]);
        self::assertSame(200, $this->post('deleteCollection', ['collection_id' => (string) $id])[0]);
        self::assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM job_catalog_collections')->fetchColumn());
    }
    public function testWindowOffersExpandCommunityFormsAndASingleCopyConfirmation(): void
    {
        $root = dirname(__DIR__, 2);
        $view = (string) file_get_contents($root . '/views/admin/effectifs_workspace/partials/job_catalog_modal.php');
        foreach (['data-job-catalog-expand', 'aria-keyshortcuts="Alt+Enter"', 'data-job-catalog-new-collection', 'data-job-catalog-collection-form', 'data-job-catalog-entry-form',
            'name="is_public"', 'accept="image/jpeg,image/png,image/webp"', 'data-job-catalog-confirm', 'data-job-catalog-confirm-skip',
            'Rien n’est enregistré tant que vous n’avez pas validé l’emploi.', 'Données de référence issues de sources publiques, à titre indicatif ; vérifiez et adaptez à votre unité.',
            'Créé par la communauté', 'L’image reste dans le catalogue'] as $needle) {
            self::assertStringContainsString($needle, $view, $needle);
        }
        // Partage public désactivé par défaut (case non cochée).
        self::assertDoesNotMatchRegularExpression('/name="is_public"[^>]*checked/', $view);
        $js = (string) file_get_contents($root . '/public/assets/js/job-catalog.js');
        // « Ne plus afficher » et fenêtre agrandie mémorisés dans un stockage protégé ; plus de confirm() champ par champ pour « Copier tout ».
        self::assertStringContainsString("const stored = key => { try {", $js);
        self::assertStringContainsString("store(SKIP_CONFIRM, '1')", $js);
        self::assertStringContainsString("store(EXPANDED, on ? '1' : null)", $js);
        self::assertStringNotContainsString("window.confirm('Remplacer les champs déjà remplis", $js);
        self::assertStringContainsString("window.confirm('Remplacer le champ « '", $js, 'La copie d’un seul champ déjà rempli garde sa confirmation.');
    }
}
