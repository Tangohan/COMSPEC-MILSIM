<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Response;
use App\Repositories\PersonnelJobRoleRepository;
use App\Support\PersonnelJobCategoryTree;
use PDO;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

final class PersonnelJobCategoriesTest extends TestCase
{
    private function categories(): array
    {
        return [
            ['id'=>1,'name'=>'Opérations','parent_id'=>null,'job_count'=>1],
            ['id'=>2,'name'=>'Transmissions','parent_id'=>1,'job_count'=>2],
            ['id'=>3,'name'=>'Radio','parent_id'=>2,'job_count'=>3],
            ['id'=>4,'name'=>'Administration','parent_id'=>null,'job_count'=>0],
        ];
    }

    private function render(array $data = []): string
    {
        return Response::view('admin.effectifs_workspace.fonctions', $data + [
            'jobsVue'=>'categories', 'jobCategories'=>$this->categories(),
            'canManageAssignments'=>true, 'csrfToken'=>str_repeat('a',64), 'jobsFragment'=>true,
        ])->body();
    }

    public function testHierarchyAndCountsIncludeAllNestedCategoriesWithoutLosingOrphanedRecords(): void
    {
        $rows = PersonnelJobCategoryTree::rows($this->categories());
        self::assertSame([1,2,3,4],array_column($rows,'id'));
        self::assertSame('Opérations / Transmissions / Radio',$rows[2]['path']);
        self::assertSame([0,1,2,0],array_column($rows,'depth'));
        self::assertSame(6,$rows[0]['branch_job_count']);
        self::assertSame(2,$rows[0]['sub_category_count']);
        self::assertSame([1,2,3],PersonnelJobCategoryTree::descendantIds($this->categories(),1));
        $legacy = [['id'=>8,'name'=>'A','parent_id'=>9],['id'=>9,'name'=>'B','parent_id'=>8],['id'=>10,'name'=>'C','parent_id'=>999]];
        self::assertCount(3,PersonnelJobCategoryTree::rows($legacy));
        self::assertSame([8,9],PersonnelJobCategoryTree::descendantIds($legacy,8));
    }

    public function testScreenExplainsThePurposeAndOffersAPrefilledSubcategoryAction(): void
    {
        $body = $this->render();
        self::assertStringContainsString('Des dossiers pour ranger vos emplois',$body);
        self::assertStringContainsString('sous-catégorie permet',$body);
        self::assertStringContainsString('nouvelle_categorie=1&amp;parent=2',$body);
        self::assertStringNotContainsString('À la racine',$body);
        self::assertSame(0,substr_count($body,'name="parent_id"'));
        $new = $this->render(['creatingJobCategory'=>true,'newCategoryParent'=>2]);
        self::assertSame(1,substr_count($new,'name="parent_id"'));
        self::assertStringContainsString('value="2" selected',$new);
        self::assertStringContainsString('Créer la sous-catégorie',$new);
    }

    public function testEditorCannotSelectItselfOrDescendantsAndPreservesSafeDeletion(): void
    {
        $body = $this->render(['selectedJobCategory'=>$this->categories()[0]]);
        self::assertStringNotContainsString('<option value="1"',$body);
        self::assertStringNotContainsString('<option value="2"',$body);
        self::assertStringNotContainsString('<option value="3"',$body);
        self::assertStringContainsString('<option value="4"',$body);
        self::assertStringContainsString('Déplacez d’abord ses emplois',$body);
        self::assertStringContainsString('disabled>Supprimer la catégorie vide',$body);
        self::assertStringContainsString('categorie_emplois=1',$body);
        self::assertStringNotContainsString('name="category_name"',$this->render(['canManageAssignments'=>false]));
    }

    public function testLargeCategoryCatalogueHasOneEditorInsteadOfQuadraticParentSelectors(): void
    {
        $categories = [];
        for ($id=1; $id<=1000; $id++) $categories[]=['id'=>$id,'name'=>'Catégorie '.$id,'parent_id'=>null,'job_count'=>0];
        $body=$this->render(['jobCategories'=>$categories,'selectedJobCategory'=>$categories[0]]);
        self::assertSame(1000,substr_count($body,'data-category-row'));
        self::assertSame(1,substr_count($body,'name="parent_id"'));
        self::assertSame(1000,substr_count($body,'<option '));
        self::assertLessThan(1500000,strlen($body));
    }

    public function testGroupedCountsExcludeAnotherTenantsJobsWithoutFetchingAnyHolders(): void
    {
        $pdo=new PDO('sqlite::memory:');
        $pdo->exec("CREATE TABLE personnel_job_role_categories(id INTEGER,tenant_id INTEGER,name TEXT,parent_id INTEGER,sort_order INTEGER);
            INSERT INTO personnel_job_role_categories VALUES(1,14,'Opérations',NULL,0),(2,14,'Vide',1,0),(3,15,'Autre',NULL,0);
            CREATE TABLE personnel_job_roles(id INTEGER,tenant_id INTEGER,category_id INTEGER);
            INSERT INTO personnel_job_roles VALUES(1,14,1),(2,14,1),(3,15,1),(4,15,3);");
        $repo=$this->getMockBuilder(PersonnelJobRoleRepository::class)->disableOriginalConstructor()->onlyMethods(['tablesExist'])->getMock();
        $repo->method('tablesExist')->willReturn(true);
        (new ReflectionProperty(PersonnelJobRoleRepository::class,'pdo'))->setValue($repo,$pdo);
        $rows=$repo->listCategoriesWithJobCounts(14);
        self::assertSame([1,2],array_map('intval',array_column($rows,'id')));
        self::assertSame([2,0],array_map('intval',array_column($rows,'job_count')));
    }
}
