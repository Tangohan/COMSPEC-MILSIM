<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Repositories\CustomGradeSystemRepository;
use PDO;
use PHPUnit\Framework\TestCase;

final class CustomGradeSystemRepositoryTest extends TestCase
{
    private PDO $pdo;
    private CustomGradeSystemRepository $repo;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->exec('CREATE TABLE grade_systems (id INTEGER PRIMARY KEY AUTOINCREMENT, code TEXT, label TEXT, country_code TEXT, format_type TEXT, owner_tenant_id INT, is_active INT, created_at TEXT, updated_at TEXT)');
        $this->pdo->exec('CREATE TABLE grade_categories (id INTEGER PRIMARY KEY AUTOINCREMENT, code TEXT, label TEXT, sort_order INT, owner_tenant_id INT, grade_system_id INT, is_active INT, created_at TEXT, updated_at TEXT)');
        $this->pdo->exec('CREATE TABLE grades (id INTEGER PRIMARY KEY AUTOINCREMENT, grade_system_id INT, grade_category_id INT, code TEXT, label_short TEXT, label_long TEXT, label_otan TEXT, sort_order INT, is_commissioned INT, is_active INT, created_at TEXT, updated_at TEXT)');
        $this->pdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, tenant_id INT, grade_id INT)');
        $this->repo = new CustomGradeSystemRepository($this->pdo, 'grades');
    }

    public function testTemplateSeedsCategoriesAndRanksFromHighestToLowest(): void
    {
        $id = $this->repo->createSystem(7, '', 'sw_republic');
        $system = $this->repo->findSystem(7, $id);

        self::assertNotNull($system);
        self::assertSame('Grand Army of the Republic', $system['label']);
        self::assertSame('T7_GRAND_ARMY_OF_THE_REPUBLIC', $system['code']);
        $tree = $this->repo->tree(7, $id);
        self::assertSame(['Jedi Command', 'Clone Officers', 'Clone NCOs', 'Clone Troopers'], array_column($tree, 'label'));
        self::assertSame(['Jedi General', 'Jedi Commander'], array_column($tree[0]['grades'], 'label_long'));
        // Codes uniques dans le système, même quand deux abrégés se ressemblent.
        $codes = $this->pdo->query('SELECT code FROM grades WHERE grade_system_id = ' . $id)->fetchAll(PDO::FETCH_COLUMN);
        self::assertSame(count($codes), count(array_unique($codes)));
    }

    public function testAnotherCommunityCannotSeeOrEditTheSystem(): void
    {
        $id = $this->repo->createSystem(7, 'Site-19', 'scp');
        $tree = $this->repo->tree(7, $id);
        $gradeId = (int) $tree[0]['grades'][0]['id'];

        self::assertNull($this->repo->findSystem(8, $id));
        self::assertSame([], $this->repo->tree(8, $id));
        self::assertFalse($this->repo->renameSystem(8, $id, 'Pirates'));
        self::assertFalse($this->repo->updateGrade(8, $gradeId, 'Hacked', 'H'));
        self::assertSame(0, $this->repo->createGrade(8, (int) $tree[0]['id'], 'Intrus', 'I'));
        self::assertSame([], $this->repo->listSystems(8));
    }

    public function testRanksCanBeAddedRenamedMovedAndCategoriesReordered(): void
    {
        $id = $this->repo->createSystem(7, 'Maison', 'vide');
        $catId = (int) $this->repo->tree(7, $id)[0]['id'];
        $low = $this->repo->createGrade(7, $catId, 'Recrue', 'REC');
        $high = $this->repo->createGrade(7, $catId, 'Vétéran', 'VET');

        // Ajouté en bas : la recrue est au-dessus du vétéran tant qu'on ne le monte pas.
        self::assertSame([$low, $high], array_map(static fn (array $g): int => (int) $g['id'], $this->repo->tree(7, $id)[0]['grades']));
        self::assertTrue($this->repo->moveGrade(7, $high, 1));
        self::assertSame([$high, $low], array_map(static fn (array $g): int => (int) $g['id'], $this->repo->tree(7, $id)[0]['grades']));

        self::assertTrue($this->repo->updateGrade(7, $low, 'Recrue de 1re classe', ''));
        self::assertSame('Recrue de 1re classe', $this->repo->findGrade(7, $low)['label_short']);

        $second = $this->repo->createCategory(7, $id, 'Civils');
        self::assertSame(['Grades', 'Civils'], array_column($this->repo->tree(7, $id), 'label'));
        self::assertTrue($this->repo->moveCategory(7, $second, 1));
        self::assertSame(['Civils', 'Grades'], array_column($this->repo->tree(7, $id), 'label'));
        self::assertFalse($this->repo->moveCategory(7, $second, 1));
    }

    public function testRankHeldByAMemberIsRetiredInsteadOfDeleted(): void
    {
        $id = $this->repo->createSystem(7, 'Maison', 'vide');
        $catId = (int) $this->repo->tree(7, $id)[0]['id'];
        $gradeId = $this->repo->createGrade(7, $catId, 'Recrue', 'REC');
        $this->pdo->exec('INSERT INTO users (tenant_id, grade_id) VALUES (7, ' . $gradeId . ')');

        self::assertSame(['deleted' => false, 'holders' => 1], $this->repo->deleteGrade(7, $gradeId));
        $tree = $this->repo->tree(7, $id);
        self::assertSame([], $tree[0]['grades']);
        self::assertSame($gradeId, (int) $tree[0]['retired'][0]['id']);
        self::assertSame(1, $this->repo->deleteCategory(7, $catId));
        self::assertSame(1, $this->repo->deleteSystem(7, $id));

        $this->pdo->exec('UPDATE users SET grade_id = NULL');
        self::assertSame(0, $this->repo->deleteSystem(7, $id));
        self::assertNull($this->repo->findSystem(7, $id));
        self::assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM grades')->fetchColumn());
    }
}
