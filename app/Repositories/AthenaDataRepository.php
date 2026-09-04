<?php
declare(strict_types=1);
namespace App\Repositories;

use App\Support\LazyDatabaseConnection;
use PDO;

final class AthenaDataRepository
{
    use LazyDatabaseConnection;
    public function __construct(?PDO $pdo = null) { $this->pdo = $pdo; }

    /** @param array<string,mixed> $event @return array{status:string,server_version?:int} */
    public function ingest(int $tenantId, array $event, string $acceptedAt): array
    {
        $pdo = $this->pdo();
        $eventId = (string)$event['event_id'];
        $known = $pdo->prepare('SELECT 1 FROM athena_ingest_receipts WHERE tenant_id=? AND event_id=? LIMIT 1');
        $known->execute([$tenantId, $eventId]);
        if ($known->fetchColumn()) return ['status' => 'known'];
        $pdo->beginTransaction();
        try {
            $source = $event['source']; $context = $event['context'] ?? []; $payload = $event['payload'];
            $sourceId = $this->upsertSource($tenantId, $source, $context, $acceptedAt);
            $type = (string)$event['type']; $entityId = trim((string)($payload['entity_id'] ?? $payload['id'] ?? $source['terminal_id']));
            $pdo->prepare('INSERT INTO athena_ingest_receipts (tenant_id,event_id,event_type,received_at) VALUES (?,?,?,?)')->execute([$tenantId,$eventId,$type,$acceptedAt]);
            $stamp = self::sqlDate((string)$event['timestamp']);
            $latency = max(0, (int)round((strtotime($acceptedAt) - strtotime((string)$event['timestamp'])) * 1000));
            $payloadJson = self::json($payload); $pipeline = self::json($event['pipeline'] ?? []);
            $status = 'accepted'; $serverVersion = null;
            if (\App\Services\Tactical\AthenaEventValidator::isState($type)) {
                $sql = 'INSERT INTO athena_live_state (tenant_id,source_id,state_type,entity_id,event_id,world,mission,server_name,payload,client_timestamp,updated_at,payload_size,latency_ms) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE event_id=IF(client_timestamp<=VALUES(client_timestamp),VALUES(event_id),event_id),world=VALUES(world),mission=VALUES(mission),server_name=VALUES(server_name),payload=IF(client_timestamp<=VALUES(client_timestamp),VALUES(payload),payload),payload_size=IF(client_timestamp<=VALUES(client_timestamp),VALUES(payload_size),payload_size),latency_ms=IF(client_timestamp<=VALUES(client_timestamp),VALUES(latency_ms),latency_ms),client_timestamp=GREATEST(client_timestamp,VALUES(client_timestamp)),updated_at=GREATEST(updated_at,VALUES(updated_at))';
                $pdo->prepare($sql)->execute([$tenantId,$sourceId,$type,$entityId,$eventId,$context['world']??null,$context['mission']??null,$context['server']??null,$payloadJson,$stamp,$acceptedAt,strlen($payloadJson),$latency]);
            } else {
                if (preg_match('/^(marker|drawing|route|zone|poi|intel|contact)\.(created|updated|deleted)$/', $type, $m)) {
                    [$status,$serverVersion] = $this->applyMapObject($tenantId,$sourceId,$m[1],$m[2],$event,$acceptedAt);
                    if ($status === 'conflict') { $pdo->rollBack(); return ['status'=>'conflict','server_version'=>$serverVersion]; }
                }
                $sql='INSERT INTO athena_events (tenant_id,source_id,event_id,schema_name,event_type,entity_id,client_timestamp,accepted_at,persisted_at,world,mission,server_name,payload,pipeline,payload_size,latency_ms,status) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)';
                $pdo->prepare($sql)->execute([$tenantId,$sourceId,$eventId,$event['schema'],$type,$entityId,$stamp,$acceptedAt,$acceptedAt,$context['world']??null,$context['mission']??null,$context['server']??null,$payloadJson,$pipeline,strlen($payloadJson),$latency,'accepted']);
                $this->applySpecialized($tenantId,$sourceId,$type,$event,$acceptedAt);
            }
            $this->metric($tenantId, \App\Services\Tactical\AthenaEventValidator::isState($type) ? 'state_updates' : 'events_accepted', $latency);
            $pdo->commit(); return ['status'=>$status];
        } catch (\Throwable $e) { if ($pdo->inTransaction()) $pdo->rollBack(); if($e instanceof \PDOException && (int)($e->errorInfo[1]??0)===1062)return ['status'=>'known']; throw $e; }
    }

    /** @param array<string,mixed> $source @param array<string,mixed> $context */
    private function upsertSource(int $tenantId,array $source,array $context,string $now): int
    {
        $sql='INSERT INTO athena_sources (tenant_id,terminal_id,source_type,callsign,user_id,server_name,mission,world,mod_version,extension_version,last_seen_at,status,metadata,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE id=LAST_INSERT_ID(id),source_type=VALUES(source_type),callsign=VALUES(callsign),server_name=VALUES(server_name),mission=VALUES(mission),world=VALUES(world),mod_version=VALUES(mod_version),extension_version=VALUES(extension_version),last_seen_at=VALUES(last_seen_at),status="online",metadata=VALUES(metadata),updated_at=VALUES(updated_at)';
        $this->pdo()->prepare($sql)->execute([$tenantId,$source['terminal_id'],$source['source_type'],$source['callsign']??null,$source['user_id']??null,$context['server']??null,$context['mission']??null,$context['world']??null,$source['mod_version']??null,$source['extension_version']??null,$now,'online',self::json($source['metadata']??[]),$now,$now]);
        return (int)$this->pdo()->lastInsertId();
    }

    /** @return array{string,int|null} */
    private function applyMapObject(int $tenantId,int $sourceId,string $kind,string $action,array $event,string $now): array
    {
        $p=$event['payload']; $id=trim((string)($p['id']??$p['entity_id']??'')); if($id==='') return ['accepted',null];
        $st=$this->pdo()->prepare('SELECT version FROM athena_map_objects WHERE tenant_id=? AND object_id=? FOR UPDATE'); $st->execute([$tenantId,$id]); $current=$st->fetchColumn();
        $clientVersion=isset($p['version'])?(int)$p['version']:null;
        if ($current!==false && $clientVersion!==null && $clientVersion < (int)$current) return ['conflict',(int)$current];
        $version=$current===false?max(1,$clientVersion??1):max((int)$current+1,$clientVersion??0);
        $context=$event['context']??[]; $actor=(string)($event['source']['terminal_id']??'unknown');
        $sql='INSERT INTO athena_map_objects (tenant_id,object_id,source_id,world,object_type,subtype,world_x,world_y,world_z,heading,label,scope,persistent,geometry,style,metadata,version,created_by,created_at,updated_by,updated_at,deleted_by,deleted_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE source_id=VALUES(source_id),world=VALUES(world),object_type=VALUES(object_type),subtype=VALUES(subtype),world_x=VALUES(world_x),world_y=VALUES(world_y),world_z=VALUES(world_z),heading=VALUES(heading),label=VALUES(label),scope=VALUES(scope),persistent=VALUES(persistent),geometry=VALUES(geometry),style=VALUES(style),metadata=VALUES(metadata),version=VALUES(version),updated_by=VALUES(updated_by),updated_at=VALUES(updated_at),deleted_by=VALUES(deleted_by),deleted_at=VALUES(deleted_at)';
        $deleted=$action==='deleted'?$now:null;
        $this->pdo()->prepare($sql)->execute([$tenantId,$id,$sourceId,(string)($p['world']??$context['world']??'unknown'),$kind,$p['subtype']??null,$p['x']??null,$p['y']??null,$p['z']??null,$p['heading']??null,$p['label']??null,$p['scope']??'mission',!empty($p['persistent'])?1:0,self::json($p['geometry']??[]),self::json($p['style']??[]),self::json($p['metadata']??[]),$version,$actor,$now,$actor,$now,$deleted?$actor:null,$deleted]);
        return ['accepted',$version];
    }

    private function applySpecialized(int $tenantId,int $sourceId,string $type,array $event,string $now): void
    {
        $p=$event['payload']; $c=$event['context']??[];
        if ($type==='terrain.chunk.received') $this->pdo()->prepare('INSERT INTO athena_terrain_chunks (tenant_id,source_id,world,layer_name,chunk_id,bounds,coverage_status,content_hash,storage_ref,metadata,received_at) VALUES (?,?,?,?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE source_id=VALUES(source_id),bounds=VALUES(bounds),coverage_status=VALUES(coverage_status),content_hash=VALUES(content_hash),storage_ref=VALUES(storage_ref),metadata=VALUES(metadata),received_at=VALUES(received_at)')->execute([$tenantId,$sourceId,$c['world']??'unknown',$p['layer']??'elevation',$p['chunk_id']??$event['event_id'],self::json($p['bounds']??[]),$p['status']??'partial',$p['hash']??null,$p['storage_ref']??null,self::json($p['metadata']??[]),$now]);
        if ($type==='scene.ingested') $this->pdo()->prepare('INSERT INTO athena_scene_snapshots (tenant_id,source_id,snapshot_id,world,object_count,bounds,content_hash,storage_ref,metadata,captured_at,received_at) VALUES (?,?,?,?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE object_count=VALUES(object_count),bounds=VALUES(bounds),content_hash=VALUES(content_hash),storage_ref=VALUES(storage_ref),metadata=VALUES(metadata),received_at=VALUES(received_at)')->execute([$tenantId,$sourceId,$p['snapshot_id']??$event['event_id'],$c['world']??'unknown',$p['object_count']??0,self::json($p['bounds']??[]),$p['hash']??null,$p['storage_ref']??null,self::json($p['metadata']??[]),self::sqlDate((string)$event['timestamp']),$now]);
        if ($type==='sync.started' || $type==='sync.completed') { $done=$type==='sync.completed'; $this->pdo()->prepare('INSERT INTO athena_sync_sessions (tenant_id,sync_id,source_id,status,queue_size,summary,started_at,completed_at) VALUES (?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE status=VALUES(status),queue_size=VALUES(queue_size),summary=VALUES(summary),completed_at=VALUES(completed_at)')->execute([$tenantId,$p['sync_id']??$event['event_id'],$sourceId,$done?'completed':'started',$p['queue_size']??0,self::json($p['summary']??$p),$now,$done?$now:null]); }
    }

    private function metric(int $tenantId,string $column,int $latency): void
    { $allowed=['state_updates','events_accepted']; if(!in_array($column,$allowed,true))return; $bucket=gmdate('Y-m-d H:i:00'); $sql="INSERT INTO athena_ingest_metrics (tenant_id,bucket_at,{$column},db_writes,latency_sum_ms,latency_samples) VALUES (?,?,1,1,?,1) ON DUPLICATE KEY UPDATE {$column}={$column}+1,db_writes=db_writes+1,latency_sum_ms=latency_sum_ms+VALUES(latency_sum_ms),latency_samples=latency_samples+1"; $this->pdo()->prepare($sql)->execute([$tenantId,$bucket,$latency]); }

    /** @return array<string,mixed> */
    public function dashboard(int $tenantId,int $after=0,int $limit=200): array
    {
        $limit=max(1,min(500,$limit));
        $q=$this->pdo()->prepare("SELECT e.id,e.event_id,e.event_type AS type,e.entity_id,e.client_timestamp,e.accepted_at,e.world,e.mission,e.server_name,e.payload,e.pipeline,e.payload_size,e.latency_ms,e.status,s.terminal_id,s.callsign FROM athena_events e JOIN athena_sources s ON s.id=e.source_id WHERE e.tenant_id=? AND e.id>? ORDER BY e.id DESC LIMIT {$limit}"); $q->execute([$tenantId,$after]); $events=$q->fetchAll(PDO::FETCH_ASSOC);
        foreach($events as &$e){foreach(['payload','pipeline'] as $k)$e[$k]=json_decode((string)$e[$k],true)?:[];} unset($e);
        $sources=$this->pdo()->prepare("SELECT *,CASE WHEN last_seen_at<UTC_TIMESTAMP(3)-INTERVAL 5 MINUTE THEN 'offline' WHEN last_seen_at<UTC_TIMESTAMP(3)-INTERVAL 45 SECOND THEN 'degraded' ELSE 'online' END AS computed_status FROM athena_sources WHERE tenant_id=? ORDER BY last_seen_at DESC");$sources->execute([$tenantId]);
        $live=$this->pdo()->prepare('SELECT l.*,s.terminal_id,s.callsign FROM athena_live_state l JOIN athena_sources s ON s.id=l.source_id WHERE l.tenant_id=? ORDER BY l.updated_at DESC LIMIT 500');$live->execute([$tenantId]);$states=$live->fetchAll(PDO::FETCH_ASSOC);foreach($states as &$s)$s['payload']=json_decode((string)$s['payload'],true)?:[];unset($s);
        $maps=$this->pdo()->prepare('SELECT * FROM athena_map_objects WHERE tenant_id=? ORDER BY updated_at DESC LIMIT 1000');$maps->execute([$tenantId]);$objects=$maps->fetchAll(PDO::FETCH_ASSOC);foreach($objects as &$o){foreach(['geometry','style','metadata'] as $k)$o[$k]=json_decode((string)$o[$k],true)?:[];}unset($o);
        $terrain=$this->pdo()->prepare('SELECT world,layer_name,coverage_status,COUNT(*) chunks FROM athena_terrain_chunks WHERE tenant_id=? GROUP BY world,layer_name,coverage_status');$terrain->execute([$tenantId]);
        $metrics=$this->pdo()->prepare('SELECT COALESCE(SUM(events_accepted),0) events_total,COALESCE(SUM(state_updates),0) state_total,COALESCE(SUM(invalid_payloads),0) invalid_payloads,COALESCE(SUM(db_writes),0) db_writes,ROUND(COALESCE(SUM(latency_sum_ms)/NULLIF(SUM(latency_samples),0),0)) avg_latency_ms FROM athena_ingest_metrics WHERE tenant_id=?');$metrics->execute([$tenantId]);
        $sync=$this->pdo()->prepare('SELECT y.*,s.terminal_id FROM athena_sync_sessions y JOIN athena_sources s ON s.id=y.source_id WHERE y.tenant_id=? ORDER BY y.started_at DESC LIMIT 50');$sync->execute([$tenantId]);$syncRows=$sync->fetchAll(PDO::FETCH_ASSOC);foreach($syncRows as &$y)$y['summary']=json_decode((string)$y['summary'],true)?:[];unset($y);
        $terrainRows=$terrain->fetchAll(PDO::FETCH_ASSOC);
        $existingTerrain=$this->existingTerrain($tenantId,1);
        if (($existingTerrain['terrain_filled']??0)>0 || ($existingTerrain['buildings']??0)>0 || ($existingTerrain['forests']??0)>0) {
            $terrainRows[]=[
                'world'=>$existingTerrain['world_name']?:'Carte #1',
                'layer_name'=>'relevé ATAK existant',
                'coverage_status'=>($existingTerrain['terrain_filled']??0)>0?'available':'scene_only',
                'chunks'=>$existingTerrain['terrain_chunks']??0,
                'coverage_pct'=>$existingTerrain['terrain_coverage_pct']??0,
                'filled_cells'=>$existingTerrain['terrain_filled']??0,
                'total_cells'=>$existingTerrain['terrain_total']??0,
                'buildings'=>$existingTerrain['buildings']??0,
                'forests'=>$existingTerrain['forests']??0,
                'sampled_at'=>$existingTerrain['last_survey_at']??null,
                'source'=>'atak_existing',
            ];
        }
        return ['events'=>$events,'sources'=>$sources->fetchAll(PDO::FETCH_ASSOC),'live_state'=>$states,'map_objects'=>$objects,'terrain'=>$terrainRows,'terrain_inventory'=>$existingTerrain,'scene_objects'=>$existingTerrain['objects']??[],'sync_sessions'=>$syncRows,'metrics'=>$metrics->fetch(PDO::FETCH_ASSOC)?:[],'server_time'=>gmdate(DATE_ATOM)];
    }

    /** @return array<string,mixed> */
    private function existingTerrain(int $tenantId,int $mapId): array
    {
        $empty=['map_id'=>$mapId,'world_name'=>'','world_size'=>30720,'terrain_filled'=>0,'terrain_total'=>0,'terrain_chunks'=>0,'terrain_coverage_pct'=>0,'hillshade_available'=>false,'buildings'=>0,'forests'=>0,'last_survey_at'=>null,'objects'=>[]];
        try {
            $terrainRepo=new AtakTerrainRepository($this->pdo());
            $summary=$terrainRepo->coverageSummary($tenantId,$mapId);
            $grid=$terrainRepo->getGrid($tenantId,$mapId,false);
            $empty=array_replace($empty,$summary,[
                'world_name'=>(string)($grid['world_name']??''),
                'world_size'=>max(1,(int)($grid['world_size']??30720)),
                'hillshade_available'=>(int)($summary['terrain_filled']??0)>=9,
            ]);
        } catch (\Throwable) {
        }
        try {
            $sceneRepo=new AtakSceneObjectRepository($this->pdo());
            $counts=$sceneRepo->countByKind($tenantId,$mapId);
            $size=(float)$empty['world_size'];
            $empty['buildings']=(int)($counts['building']??0);
            $empty['forests']=(int)($counts['forest']??0);
            $empty['objects']=$sceneRepo->visible($tenantId,$mapId,0,0,$size,$size,5000);
            $sceneAt=$sceneRepo->lastUpdatedAt($tenantId,$mapId);
            if ($sceneAt!==null && ($empty['last_survey_at']===null || strtotime($sceneAt)>strtotime((string)$empty['last_survey_at']))) $empty['last_survey_at']=$sceneAt;
        } catch (\Throwable) {
        }
        return $empty;
    }
    public function markDebugSourceOffline(int $tenantId,string $terminalId):void{$this->pdo()->prepare('UPDATE athena_sources SET last_seen_at=UTC_TIMESTAMP(3)-INTERVAL 20 MINUTE,status="offline" WHERE tenant_id=? AND terminal_id=? AND source_type="dev_debug"')->execute([$tenantId,$terminalId]);}
    private static function json(mixed $v): string { return json_encode($v,JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE)?:'{}'; }
    private static function sqlDate(string $v): string { $t=strtotime($v); return gmdate('Y-m-d H:i:s.000',$t===false?time():$t); }
}
