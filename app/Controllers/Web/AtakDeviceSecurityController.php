<?php
declare(strict_types=1);
namespace App\Controllers\Web;
use App\Core\Csrf;use App\Core\Request;use App\Core\Response;use App\Core\Session;use App\Repositories\AtakDeviceAuthRepository;use App\Services\Atak\AtakDeviceAuthService;use App\Services\Auth\AuthService;use App\Services\Security\FileRateLimiter;
final class AtakDeviceSecurityController
{
 public function __construct(private AuthService $auth,private AtakDeviceAuthService $service,private AtakDeviceAuthRepository $repo,private FileRateLimiter $limits){}
 private function actor():array{return $this->auth->user()??[];} private function ip():string{return substr((string)($_SERVER['REMOTE_ADDR']??''),0,45);}
 public function index(Request $r,array $p=[]):Response{$u=$this->actor();$uid=(int)($u['id']??0);$tid=(int)($u['tenant_id']??0);return Response::view('layout.main',['content'=>'account.atak_devices','title'=>'Sécurité · Terminaux ATAK','accountHubPage'=>true,'accountUser'=>$u,'devices'=>$this->repo->devices($uid,$tid),'recovery'=>$this->repo->recoverySummary($uid,$tid),'events'=>$this->repo->recentEvents($uid,$tid),'pending'=>Session::get('atak_pair_preview'),'recoveryCodes'=>Session::getFlash('atak_recovery_codes')]);}
 public function lookup(Request $r,array $p=[]):Response{if(!Csrf::validate($r->input('_csrf_token')))return Response::json(['error'=>'csrf_invalid'],419);$u=$this->actor();$uid=(int)$u['id'];if($this->limits->tooManyAttempts('atak:approve:'.$uid,20,600)){Session::flash('error','Trop de tentatives. Réessayez plus tard.');return Response::redirect(url('account/security/devices'));}$pair=$this->service->inspect((string)$r->input('user_code',''));if(!$pair||$pair['status']!=='pending'){Session::flash('error','Code introuvable, expiré ou déjà traité.');Session::forget('atak_pair_preview');}else Session::set('atak_pair_preview',$pair);return Response::redirect(url('account/security/devices').'#pairing');}
 public function decide(Request $r,array $p=[]):Response{if(!Csrf::validate($r->input('_csrf_token')))return Response::json(['error'=>'csrf_invalid'],419);$u=$this->actor();$pair=Session::get('atak_pair_preview');$approve=$r->input('decision')==='approve';if(!is_array($pair)||!$this->service->decide($pair,(int)$u['id'],(int)$u['tenant_id'],$approve,$this->ip()))Session::flash('error','La demande ne peut plus être traitée.');else Session::flash('success',$approve?'Terminal autorisé et enrôlé.':'Demande refusée.');Session::forget('atak_pair_preview');return Response::redirect(url('account/security/devices'));}
 public function recovery(Request $r,array $p=[]):Response{if(!Csrf::validate($r->input('_csrf_token')))return Response::json(['error'=>'csrf_invalid'],419);$u=$this->actor();$password=(string)$r->input('confirm_password','');if(!password_verify($password,(string)($u['password_hash']??''))){Session::flash('error','Mot de passe actuel incorrect.');return Response::redirect(url('account/security/devices').'#recovery');}Session::flash('atak_recovery_codes',$this->service->generateCodes((int)$u['id'],(int)$u['tenant_id'],$this->ip()));return Response::redirect(url('account/security/devices').'#recovery');}
 public function revoke(Request $r,array $p=[]):Response{if(!Csrf::validate($r->input('_csrf_token')))return Response::json(['error'=>'csrf_invalid'],419);$u=$this->actor();$id=(int)($p['id']??0);$ok=$this->repo->revokeDevice($id,(int)$u['id'],(int)$u['tenant_id']);if($ok)$this->repo->event('ATAK_DEVICE_REVOKED',(int)$u['id'],(int)$u['tenant_id'],$id,$this->ip());Session::flash($ok?'success':'error',$ok?'Terminal et sessions révoqués.':'Terminal introuvable.');return Response::redirect(url('account/security/devices'));}

 /** Recherche JSON utilisée par le panneau Compte de la carte ATAK Web. */
 public function atakLookup(Request $r,array $p=[]):Response
 {
  if(!Csrf::validate($r->input('_csrf_token')))return Response::json(['error'=>'csrf_invalid','message'=>'Session expirée. Rechargez la carte.'],419);
  $u=$this->actor();$uid=(int)($u['id']??0);
  if($this->limits->tooManyAttempts('atak:web-approve:'.$uid,20,600))return Response::json(['error'=>'rate_limited','message'=>'Trop de tentatives. Réessayez dans quelques minutes.'],429);
  $pair=$this->service->inspect((string)$r->input('user_code',''));
  if(!$pair||$pair['status']!=='pending')return Response::json(['error'=>'pairing_not_found','message'=>'Code introuvable, expiré ou déjà traité.'],404);
  Session::set('atak_pair_preview',$pair);
  $steam=(string)($pair['steam_uid']??'');
  return Response::json(['ok'=>true,'pairing'=>['terminal_uid'=>(string)$pair['terminal_uid'],'steam_uid_masked'=>$steam===''?'Non fourni':substr($steam,0,4).'••••'.substr($steam,-4),'mod_version'=>(string)($pair['mod_version']??''),'created_at'=>(string)$pair['created_at'],'expires_at'=>(string)$pair['expires_at']]])->header('Cache-Control','no-store');
 }

 /** Approbation/refus JSON, toujours explicite et lié à la session ATHENA courante. */
 public function atakDecide(Request $r,array $p=[]):Response
 {
  if(!Csrf::validate($r->input('_csrf_token')))return Response::json(['error'=>'csrf_invalid','message'=>'Session expirée. Rechargez la carte.'],419);
  $u=$this->actor();$pair=Session::get('atak_pair_preview');$approve=$r->input('decision')==='approve';
  if(!is_array($pair))return Response::json(['error'=>'pairing_missing','message'=>'Recherchez de nouveau le code du terminal.'],409);
  $ok=$this->service->decide($pair,(int)$u['id'],(int)$u['tenant_id'],$approve,$this->ip());
  Session::forget('atak_pair_preview');
  if(!$ok)return Response::json(['error'=>'pairing_conflict','message'=>'La demande a expiré ou a déjà été traitée.'],409);
  return Response::json(['ok'=>true,'status'=>$approve?'approved':'denied','message'=>$approve?'Terminal autorisé, enrôlé et lié à son certificat.':'Demande refusée.'])->header('Cache-Control','no-store');
 }
}
