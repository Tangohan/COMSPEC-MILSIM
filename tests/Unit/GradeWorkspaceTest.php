<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Controllers\Admin\Organization\GradeReferentielController;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use PHPUnit\Framework\TestCase;

final class GradeWorkspaceTest extends TestCase
{
    protected function setUp(): void
    {
        $_SESSION = ['tenant_id'=>42, '_csrf_token'=>str_repeat('a',64)];
        $_GET = $_POST = [];
        $_SERVER['HTTP_X_GRADE_FRAGMENT'] = '1';
        $GLOBALS['__app_locale']='fr';
    }

    protected function tearDown(): void
    {
        unset($_SERVER['HTTP_X_GRADE_FRAGMENT'], $GLOBALS['__app_locale']);
    }

    private function grade(array $values=[]): array
    {
        return $values + ['id'=>1,'code'=>'FR_CNE','label_short'=>'CNE renommé','label_long'=>'Capitaine local','category_code'=>'OFFICIER','category_label'=>'Officiers','country_code'=>'FR','label_otan'=>'OF-2','is_enabled'=>1];
    }

    public function testCatalogueAlwaysShowsCodeEvenWithRenamedOrEmptyAbbreviation(): void
    {
        $body=Response::view('admin.organization.referentiels.grades.index',[
            'tab'=>'fr','gradesFr'=>[$this->grade(),$this->grade(['id'=>2,'code'=>'FR_SGT','label_short'=>'','label_long'=>'Sergent masqué','is_enabled'=>0])],
        ])->body();
        self::assertStringContainsString('Code : FR_CNE',$body);
        self::assertStringContainsString('CNE renommé',$body);
        self::assertStringContainsString('Code : FR_SGT',$body);
        self::assertStringContainsString('class="grd-ref__short">FR_SGT</span>',$body);
        self::assertStringContainsString('data-grade-filter',$body);
        self::assertStringContainsString('data-grade-edit',$body);
    }

    public function testCommunityAndArchivedGradesShowTheirStableCodes(): void
    {
        $body=Response::view('admin.organization.referentiels.grades.index',[
            'tab'=>'perso','tenantExtras'=>[
                ['id'=>10,'code'=>'LOCAL_A','short_label'=>'Abrégé','label'=>'Grade maison'],
                ['id'=>11,'code'=>'LOCAL_OLD','short_label'=>'','label'=>'Ancien grade','archived_at'=>'2026-10-01'],
            ],
        ])->body();
        self::assertStringContainsString('Code : LOCAL_A',$body);
        self::assertStringContainsString('Code : LOCAL_OLD',$body);
    }

    public function testCustomAndRetiredGradesShowTheirStableCodes(): void
    {
        $body=Response::view('admin.organization.referentiels.grades.index',[
            'tab'=>'custom','customReady'=>true,'customSystems'=>[['id'=>3,'code'=>'CUSTOM','label'=>'Maison','grade_count'=>1]],
            'customSelected'=>['id'=>3,'code'=>'CUSTOM','label'=>'Maison'],'customTemplates'=>[],
            'customTree'=>[['id'=>8,'label'=>'Équipe','grades'=>[$this->grade(['code'=>'CUSTOM_ONE','label_short'=>''])],'retired'=>[$this->grade(['id'=>2,'code'=>'CUSTOM_RETIRED'])]]],
        ])->body();
        self::assertStringContainsString('Code : CUSTOM_ONE',$body);
        self::assertStringContainsString('Code : CUSTOM_RETIRED',$body);
    }

    public function testFragmentResponseContainsUpdatedWorkspaceAndEscapedServerNotices(): void
    {
        Session::flash('success','Grade enregistré.');
        Session::flash('error','<script>non</script>');
        $controller=(new \ReflectionClass(GradeReferentielController::class))->newInstanceWithoutConstructor();
        $render=new \ReflectionMethod(GradeReferentielController::class,'gradeView');
        $response=$render->invoke($controller,new Request(),['content'=>'admin.organization.referentiels.grades.index','tab'=>'fr','gradesFr'=>[$this->grade()]]);
        self::assertSame('private, no-store',$response->headerValue('Cache-Control'));
        self::assertSame('X-Grade-Fragment',$response->headerValue('Vary'));
        self::assertStringNotContainsString('<!DOCTYPE',$response->body());
        self::assertStringContainsString('data-grade-workspace',$response->body());
        self::assertStringContainsString('Grade enregistré.',$response->body());
        self::assertStringContainsString('&lt;script&gt;non&lt;/script&gt;',$response->body());
        self::assertNull(Session::getFlash('success'));
    }

    public function testRenameFormRetainsCsrfAndReadOnlyStableCodeForModalAndNativeFallback(): void
    {
        $controller=(new \ReflectionClass(GradeReferentielController::class))->newInstanceWithoutConstructor();
        $response=(new \ReflectionMethod(GradeReferentielController::class,'gradeView'))->invoke($controller,new Request(),[
            'content'=>'admin.organization.referentiels.grades.form','grade'=>$this->grade(),'systems'=>[],'categories'=>[],'tenantOwned'=>true,
        ]);
        self::assertStringContainsString('data-grade-form-page',$response->body());
        self::assertStringContainsString('name="_csrf_token"',$response->body());
        self::assertStringContainsString('value="FR_CNE"',$response->body());
        self::assertStringContainsString('readonly',$response->body());
        self::assertStringContainsString('data-grade-cancel',$response->body());
    }
}
