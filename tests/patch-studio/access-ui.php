<?php
require __DIR__.'/bootstrap.php';
require __DIR__.'/plan-fixtures.php';
use App\Core\{Csrf,Database,Gate,Request,Session};
use App\Controllers\Api\PatchStudioApiController;
use App\Controllers\Web\PatchStudioController;
use App\Services\Billing\SubscriptionPlanFeaturesCatalog;
use App\Services\PatchStudio\PlanAccess;
$checks=0;
function verify(bool $v,string $label): void { global $checks;$checks++;if(!$v)throw new RuntimeException($label); }
$GLOBALS['patch_test_root']=sys_get_temp_dir().'/patch-ui-'.bin2hex(random_bytes(4));
Session::start();Session::set('tenant_id',7);Session::set('user_id',1);Gate::getInstance()->setPermissions(['admin.organization']);
$_SERVER['REQUEST_METHOD']='GET';$_SERVER['REQUEST_URI']='/patch-studio';
$request=new Request();
foreach(['free','standard','pro'] as $slug){
 patchTestPlan($slug,true);verify(!PlanAccess::allows(7),$slug.' cannot override minimum tier');
 verify((new PatchStudioController())->index($request)->statusCode()===403,$slug.' web denied');
}
patchTestPlan('pro_plus');verify(PlanAccess::allows(7),'existing Pro+ without new feature bit uses catalogue default');
verify(!PlanAccess::allows(8),'unknown tenant denied');verify(!PlanAccess::allows(0),'invalid tenant denied');
patchTestPlan('pro_plus',false);verify(!PlanAccess::allows(7),'explicit disabled feature respected');
patchTestPlan('pro_plus',null,'past_due');verify(!PlanAccess::allows(7),'past due denied');
patchTestPlan('free',null,'none',['founder_trial_ends_at'=>date('c',time()+86400)]);verify(!PlanAccess::allows(7),'Pro founder trial insufficient');
patchTestPlan('free',null,'none',['plan_gift'=>['plan_slug'=>'pro_plus','gift_id'=>1,'ends_at'=>date('c',time()+86400)]]);verify(PlanAccess::allows(7),'active Pro+ gift accepted');
patchTestPlan('free',null,'none',['plan_gift'=>['plan_slug'=>'pro_plus','gift_id'=>1,'ends_at'=>date('c',time()-86400)]]);verify(!PlanAccess::allows(7),'expired Pro+ gift denied');
patchTestPlan('pro_plus',null,'past_due',['plan_gift'=>['plan_slug'=>'pro_plus','gift_id'=>2,'ends_at'=>null]]);verify(PlanAccess::allows(7),'active gift overrides past due per existing billing policy');
patchTestPlan('pro_plus',null,'active',['manual_billing'=>['plan_slug'=>'pro_plus','reference'=>'TEST','interval'=>'monthly','ends_at'=>date('c',time()-86400)]]);verify(!PlanAccess::allows(7),'expired manual payment denied');
foreach(['free','standard','pro'] as $slug)verify(!SubscriptionPlanFeaturesCatalog::defaultsForSlug($slug)['patch_studio'],'catalogue '.$slug.' off');
verify(SubscriptionPlanFeaturesCatalog::defaultsForSlug('pro_plus')['patch_studio'],'catalogue Pro+ on');
patchTestPlan('standard',true);
$api=new PatchStudioApiController();
foreach(['index','show','create','update','history','share','variables','assets','upload','asset','export'] as $method){
 $response=$api->$method($request,['id'=>str_repeat('a',32)]);
 $data=json_decode($response->body(),true);
 verify($response->statusCode()===403&&$data['required_plan']==='pro_plus','direct API '.$method.' denied before any private lookup');
 verify($response->headerValue('Cache-Control')==='private, no-store','denied '.$method.' no-store');
}
patchTestPlan();
$page=(new PatchStudioController())->index($request);verify($page->statusCode()===200,'Pro+ editor allowed');
$dom=new DOMDocument();@$dom->loadHTML($page->body());$xpath=new DOMXPath($dom);
$src=$xpath->evaluate('string(//script[@data-patch-studio-module]/@src)');
verify(str_contains($src,'/assets/js/patch-studio.js?v=')&&!str_contains($src,'.mjs'),'JavaScript served using JS MIME path with version');
verify($xpath->query('//link[contains(@href,"atak-nine-line.css")]')->length===1,'shared 9-line stylesheet');
verify($xpath->query('//img[contains(@src,"athena-embleme-blanc.svg")]')->length===1,'shared Athena emblem');
verify($xpath->query('//aside[@aria-labelledby="ps-disclaimer-title"]')->length===1,'persistent disclaimer');
verify(str_contains($page->body(),'jumelles de vision nocturne') && str_contains($page->body(),'pas encore été validée en jeu'),'night vision limits visible');
verify($xpath->query('//body[contains(@class,"nl-page")]')->length===1,'shared 9-line theme');
verify(is_file(dirname(__DIR__,2).'/public/assets/js/patch-studio.js'),'renamed module exists');
verify(is_file(dirname(__DIR__,2).'/public/assets/js/patch-studio-core.js'),'renamed core exists');
verify(!is_file(dirname(__DIR__,2).'/public/assets/js/patch-studio.mjs'),'legacy octet-stream path no longer requested');
verify(str_contains(file_get_contents(dirname(__DIR__,2).'/public/assets/js/patch-studio.js'),'new URL(import.meta.url).search'),'core inherits entry version');
patchTestPlan('pro');$locked=(new PatchStudioController())->index($request);
verify(str_contains($locked->body(),'Une formule Pro+ minimum est requise'),'paywall clearly names minimum');
verify(!str_contains($locked->body(),'data-patch-studio-module'),'no editor module on paywall');
verify(str_contains($locked->body(),'ps-disclaimer-title'),'paywall disclaimer present');
verify(str_contains($locked->body(),'platform/upgrade'),'existing billing flow linked');
// Shared controllers retain the same gate, including game catalogue AND binary resources.
verify(str_contains(file_get_contents(dirname(__DIR__,2).'/app/Controllers/Api/AtakPatchesApiController.php'),'PlanAccess::allows($t)'),'ATAK common auth path gates catalogue and texture reads');
echo "PASS $checks plan/UI checks\n";
