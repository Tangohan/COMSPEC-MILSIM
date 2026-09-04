<?php
declare(strict_types=1);
namespace App\Controllers\Api;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\AthenaDataRepository;
use App\Services\Tactical\AthenaEventValidator;
use App\Support\ComspecApiKeyAuth;

final class AthenaDataApiController
{
    public function __construct(private ?AthenaDataRepository $repository=null) { $this->repository ??= new AthenaDataRepository(); }

    public function ingest(Request $request,array $params=[]): Response
    {
        if (!ComspecApiKeyAuth::requestPresentsValidKey()) return Response::json(['error'=>'unauthorized','message'=>'Clé terminal ou session device invalide.'],401);
        $tenantId=(int)(ComspecApiKeyAuth::matchedTenantId()??0);
        if($tenantId<1) return Response::json(['error'=>'tenant_context_required','message'=>'Utilisez une clé ATAK propre à la communauté.'],403);
        $length=(int)($_SERVER['CONTENT_LENGTH']??0);
        if($length>8388608) return Response::json(['error'=>'batch_too_large','max_bytes'=>8388608],413);
        $body=ComspecApiKeyAuth::peekJsonObject();
        $events=array_key_exists('events',$body)?$body['events']:[$body];
        if(!is_array($events)||$events===[]||count($events)>AthenaEventValidator::MAX_BATCH) return Response::json(['error'=>'invalid_batch','max_events'=>AthenaEventValidator::MAX_BATCH],422);
        $accepted=[];$known=[];$rejected=[];$conflicts=[];$now=gmdate('Y-m-d H:i:s.000');
        foreach($events as $i=>$event){
            $errors=AthenaEventValidator::errors($event);
            if($errors!==[]){$rejected[]=['index'=>$i,'event_id'=>is_array($event)?($event['event_id']??null):null,'errors'=>$errors];continue;}
            try{$result=$this->repository->ingest($tenantId,$event,$now);$id=(string)$event['event_id'];
                if($result['status']==='known'){$accepted[]=$id;$known[]=$id;}
                elseif($result['status']==='conflict'){$conflicts[]=['event_id'=>$id,'entity_id'=>$event['payload']['id']??null,'client_version'=>$event['payload']['version']??null,'server_version'=>$result['server_version']??null];}
                else $accepted[]=$id;
            }catch(\Throwable $e){$rejected[]=['index'=>$i,'event_id'=>$event['event_id']??null,'errors'=>['persistence_failed']];}
        }
        $status=$accepted===[]&&($rejected!==[]||$conflicts!==[])?422:200;
        return Response::json(['accepted'=>$accepted,'known'=>$known,'rejected'=>$rejected,'conflicts'=>$conflicts,'server_time'=>gmdate(DATE_ATOM)],$status);
    }

    public function dashboard(Request $request,array $params=[]): Response
    {
        $tenantId=(int)Session::get('tenant_id'); if($tenantId<1)return Response::json(['error'=>'tenant_context_required'],403);
        return Response::json(['ok'=>true,'data'=>$this->repository->dashboard($tenantId,(int)$request->query('after',0),(int)$request->query('limit',200))]);
    }

    public function devSeed(Request $request,array $params=[]): Response
    {
        if(ComspecApiKeyAuth::isAppProduction())return Response::json(['error'=>'not_available_in_production'],404);
        if(!\App\Core\Csrf::validate((string)($_SERVER['HTTP_X_CSRF_TOKEN']??'')))return Response::json(['error'=>'csrf_invalid'],419);
        $tenantId=(int)Session::get('tenant_id');if($tenantId<1)return Response::json(['error'=>'tenant_context_required'],403);
        $types=['position.updated','bft.updated','marker.created','drawing.created','route.created','terrain.chunk.received','terminal.heartbeat'];$accepted=[];
        for($i=0;$i<18;$i++){$type=$types[$i%count($types)];$terminal='DEV-'.str_pad((string)(1+$i%5),2,'0',STR_PAD_LEFT);$id='dev-'.bin2hex(random_bytes(12));$payload=['entity_id'=>'entity-'.($i%7),'x'=>4000+($i*731)%21000,'y'=>3000+($i*997)%22000,'z'=>50+$i,'version'=>1];if($type==='terrain.chunk.received')$payload+=['chunk_id'=>'debug-'.$i,'layer'=>'elevation','bounds'=>['x'=>($i%4)*5000,'y'=>intdiv($i,4)*5000,'width'=>5000,'height'=>5000],'status'=>$i%3?'complete':'partial'];
            $event=['schema'=>'athena.event.v1','event_id'=>$id,'type'=>$type,'timestamp'=>gmdate(DATE_ATOM),'source'=>['terminal_id'=>$terminal,'source_type'=>'dev_debug','callsign'=>'DEBUG-'.$i%5],'context'=>['world'=>'Altis','mission'=>'DEV_INSPECTOR','server'=>'LOCAL-DEBUG'],'payload'=>$payload];$this->repository->ingest($tenantId,$event,gmdate('Y-m-d H:i:s.000'));$accepted[]=$id;}
        $this->repository->markDebugSourceOffline($tenantId,'DEV-05');
        return Response::json(['ok'=>true,'debug'=>true,'accepted'=>$accepted]);
    }
}
