<?php
// Dedicated localhost test router. Never deploy this router or expose its fixture session.
if (PHP_SAPI !== 'cli-server' || getenv('PATCH_STUDIO_TEST') !== '1') { http_response_code(404); exit; }
require __DIR__.'/bootstrap.php';
require __DIR__.'/plan-fixtures.php';
patchTestPlan($_SERVER['HTTP_X_TEST_PLAN']??'pro_plus');
use App\Core\{Csrf,Database,Gate,Request,Response,Session};
use App\Controllers\Api\PatchStudioApiController;
$GLOBALS['patch_test_root']=getenv('PATCH_TEST_ROOT')?:sys_get_temp_dir().'/athena-patch-browser';
if(!is_dir($GLOBALS['patch_test_root']))mkdir($GLOBALS['patch_test_root'],0700,true);
$path=parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH);
if(str_starts_with($path,'/assets/'))return false;
$pdo=patchTestDb('sqlite:'.$GLOBALS['patch_test_root'].'/test.sqlite');
(new ReflectionProperty(Database::class,'pdo'))->setValue(null,$pdo);
Session::start();Session::set('user_id',1);Session::set('tenant_id',7);Gate::getInstance()->setPermissions(['admin.organization']);
$_SESSION['_csrf_token']='test-token';
$r=new Request(); // capture via the existing factory/constructor below
if($path==='/patch-studio'){$response=(new App\Controllers\Web\PatchStudioController())->index($r);$response->send();return;}
if($path==='/api/patch-studio/variables'){
 if(!\App\Services\PatchStudio\PlanAccess::allows(7)){\App\Services\PatchStudio\PlanAccess::deniedJson()->send();return;}
 $members=[['userId'=>1,'label'=>'SABER','unitId'=>4,'values'=>['callsign'=>'SABER 1-2','unit'=>'ALPHA','organization'=>'ATHENA']],['userId'=>2,'label'=>'TIGER','unitId'=>8,'values'=>['callsign'=>'TIGER 6','unit'=>'BRAVO','organization'=>'ATHENA']]];
 if(($_SERVER['HTTP_X_TEST_RH']??'')==='no')$members=array_slice($members,0,1);
 if(isset($_GET['users']))$members=array_values(array_filter($members,static fn($m)=>in_array((string)$m['userId'],explode(',',(string)$_GET['users']),true)));
 if(!empty($_GET['q']))$members=array_values(array_filter($members,static fn($m)=>mb_stripos($m['label'].' '.$m['values']['callsign'],(string)$_GET['q'])!==false));
 Response::json(['members'=>$members,'limit'=>100])->send();return;
}
if(($_SERVER['HTTP_X_TEST_SCHEMA']??'')==='missing')foreach(['patch_studio_projects','patch_studio_assets'] as $table)$pdo->exec('DROP TABLE '.$table);

$method=$_SERVER['REQUEST_METHOD'];$controller=new PatchStudioApiController();$route=null;$params=[];
if($path==='/api/patch-studio/projects')$route=$method==='POST'?'create':'index';
elseif($path==='/api/patch-studio/assets')$route=$method==='POST'?'upload':'assets';
elseif(preg_match('#^/api/patch-studio/assets/([a-f0-9]{32})$#',$path,$m)){$route='asset';$params=['id'=>$m[1]];}
elseif(preg_match('#^/api/patch-studio/projects/([a-f0-9]{32})(?:/(history|export|share|preview))?$#',$path,$m)){$params=['id'=>$m[1]];$route=$m[2]??($method==='PUT'?'update':'show');}
if(!$route){http_response_code(404);return;}$controller->$route($r,$params)->send();
