<?php

declare(strict_types=1);

namespace App\Services\Tenant;

use App\Core\Session;
use App\Repositories\PlatformAdminTenantAuditRepository;
use RuntimeException;
use Throwable;

final class TenantAdminAuditService
{
    private const SECRET_KEYS = ['password','password_hash','token','secret','api_key','authorization','cookie','private_key','jwt'];
    /** Deliberately closed registry: rollback can never address a browser-supplied table. */
    private const ENTITY_TABLES = ['user'=>'users','role'=>'roles','tenant_setting'=>'tenant_settings','atak_config'=>'tenant_atak_settings'];
    public function __construct(private ?PlatformAdminTenantAuditRepository $repo=null) { $this->repo ??= new PlatformAdminTenantAuditRepository(); }

    public function beginSession(int $adminId,int $tenantId,?string $reason,string $ip,string $agent): int { return $this->repo->begin($adminId,$tenantId,$reason,$ip,$agent); }
    public function endSession(int $id,int $adminId): bool { return $this->repo->end($id,$adminId); }
    public function recordCreate(string $type,string|int $id,array $after,string $module='core'): int { return $this->record('create',$type,$id,null,$after,$module,true); }
    public function recordUpdate(string $type,string|int $id,array $before,array $after,string $module='core'): int { return $this->record('update',$type,$id,$before,$after,$module,true); }
    public function recordDelete(string $type,string|int $id,array $before,string $module='core',bool $reversible=true): int { return $this->record('delete',$type,$id,$before,null,$module,$reversible); }
    public function recordAction(string $action,string $description,array $metadata=[],string $module='core'): int { return $this->record($action,null,null,null,null,$module,false,$description,$metadata); }
    public function getSessionHistory(int $sessionId,?string $type=null): array { return ['actions'=>$this->repo->actions($sessionId,$type),'errors'=>$this->repo->errors($sessionId)]; }
    public function recordError(Throwable $e,string $route,string $module='core'): void { $c=$this->context();$this->repo->insertError($c+['route'=>$route,'module'=>$module,'exception_class'=>$e::class,'message'=>$this->redactText($e->getMessage()),'stack_trace'=>$this->redactText($e->getTraceAsString()),'context_json'=>$this->json(['code'=>$e->getCode()])]); }

    public function rollbackAction(int $actionId): int
    {
        $action=$this->repo->action($actionId); $ctx=$this->context();
        if (!$action || (int)$action['tenant_id']!==$ctx['tenant_id'] || (int)$action['session_id']!==$ctx['session_id']) throw new RuntimeException('Action hors de cette intervention.');
        if (!(bool)$action['is_reversible'] || $action['rollback_status']!=='not_requested') throw new RuntimeException('Cette action ne peut pas être restaurée.');
        $table=self::ENTITY_TABLES[(string)$action['entity_type']]??null; if (!$table) throw new RuntimeException('Type d’entité non pris en charge automatiquement.');
        $before=json_decode((string)($action['before_state']??''),true); $after=json_decode((string)($action['after_state']??''),true); $id=(string)$action['entity_id'];$pdo=$this->repo->pdo();$pdo->beginTransaction();
        try {
            $q=$pdo->prepare("SELECT * FROM `$table` WHERE id=? AND tenant_id=? FOR UPDATE");$q->execute([$id,$ctx['tenant_id']]);$current=$q->fetch()?:null;
            if ($action['action_type']==='update') { if (!$this->matches($current,$after)) throw new RuntimeException('Rollback impossible automatiquement : l’entité a été modifiée depuis cette action.'); $this->updateRow($table,$id,$ctx['tenant_id'],$before); }
            elseif ($action['action_type']==='create') { if (!$this->matches($current,$after)) throw new RuntimeException('Rollback impossible automatiquement : l’entité a été modifiée depuis cette action.');$d=$pdo->prepare("DELETE FROM `$table` WHERE id=? AND tenant_id=?");$d->execute([$id,$ctx['tenant_id']]); }
            elseif ($action['action_type']==='delete') { if ($current!==null) throw new RuntimeException('Rollback impossible automatiquement : l’identifiant est déjà utilisé.');$this->insertRow($table,$before,$ctx['tenant_id']); }
            else throw new RuntimeException('Action non réversible.');
            $rollback=$this->record('rollback',(string)$action['entity_type'],$id,$current,$before,'rollback',false,'Rollback action #'.$actionId,['source_action_id'=>$actionId],$actionId);
            $u=$pdo->prepare("UPDATE platform_admin_tenant_actions SET rollback_status='rolled_back',rolled_back_by_action_id=? WHERE id=? AND tenant_id=?");$u->execute([$rollback,$actionId,$ctx['tenant_id']]);$pdo->commit();return $rollback;
        } catch(Throwable $e) { if($pdo->inTransaction())$pdo->rollBack();throw $e; }
    }
    private function record(string $action,?string $type,string|int|null $id,?array $before,?array $after,string $module,bool $reversible,?string $description=null,array $metadata=[],?int $rollbackOf=null): int { $c=$this->context();return $this->repo->insertAction($c+['action_type'=>$action,'module'=>$module,'route'=>$_SERVER['REQUEST_URI']??null,'http_method'=>$_SERVER['REQUEST_METHOD']??null,'entity_type'=>$type,'entity_id'=>$id===null?null:(string)$id,'description'=>$description,'before_state'=>$before===null?null:$this->json($before),'after_state'=>$after===null?null:$this->json($after),'metadata'=>$this->json($metadata),'is_reversible'=>$reversible,'rollback_of_action_id'=>$rollbackOf]); }
    private function context(): array { $c=TenantContext::intervention();if(!$c)throw new RuntimeException('Aucune intervention tenant vérifiée.');return ['session_id'=>(int)$c['admin_tenant_session_id'],'tenant_id'=>(int)$c['admin_tenant_id'],'admin_id'=>(int)$c['platform_admin_id'],'request_id'=>(string)($_ENV['REQUEST_ID']??'unknown')]; }
    private function json(array $v): string { return json_encode($this->sanitize($v),JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE); }
    private function sanitize(array $v): array { foreach($v as $k=>$x){$lk=strtolower((string)$k);if(array_filter(self::SECRET_KEYS,fn($s)=>str_contains($lk,$s))){$v[$k]='[REDACTED]';}elseif(is_array($x))$v[$k]=$this->sanitize($x);}return $v; }
    private function redactText(string $v): string { return preg_replace('/(password|token|secret|api[_-]?key|cookie)\s*[=:]\s*[^\s,;]+/i','$1=[REDACTED]',$v)??'[REDACTED]'; }
    private function matches(?array $current,?array $snapshot): bool { if($current===null||$snapshot===null)return $current===$snapshot;foreach($snapshot as $k=>$v){if(in_array($k,['updated_at','created_at'],true))continue;if(array_key_exists($k,$current)&&(string)$current[$k]!=(string)$v)return false;}return true; }
    private function updateRow(string $table,string $id,int $tenant,array $row): void { unset($row['id'],$row['tenant_id'],$row['created_at']);$cols=array_keys($row);$sql=implode(',',array_map(fn($c)=>"`$c`=?",$cols));$s=$this->repo->pdo()->prepare("UPDATE `$table` SET $sql WHERE id=? AND tenant_id=?");$s->execute([...array_values($row),$id,$tenant]); }
    private function insertRow(string $table,array $row,int $tenant): void { $row['tenant_id']=$tenant;$cols=array_keys($row);$names=implode(',',array_map(fn($c)=>"`$c`",$cols));$s=$this->repo->pdo()->prepare("INSERT INTO `$table` ($names) VALUES (".implode(',',array_fill(0,count($cols),'?')).')');$s->execute(array_values($row)); }
}
