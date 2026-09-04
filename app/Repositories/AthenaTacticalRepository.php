<?php
declare(strict_types=1);
namespace App\Repositories;

use App\Core\Database;
use PDO;

final class AthenaTacticalRepository
{
    private PDO $pdo;
    public function __construct() { $this->pdo = Database::getPdo(); }

    /** @return list<array<string,mixed>> */
    public function sync(int $tenantId, string $world, int $cursor, int $limit = 500): array
    {
        $sql = 'SELECT revision,event_type,marker_uuid,payload,created_at FROM athena_tactical_events WHERE tenant_id=? AND world_name=? AND revision>? ORDER BY revision ASC LIMIT ' . max(1, min(1000, $limit));
        $s = $this->pdo->prepare($sql); $s->execute([$tenantId, $world, $cursor]);
        return $s->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @return list<array<string,mixed>> */
    public function markers(int $tenantId, string $world, bool $includeDraft = false): array
    {
        $sql = 'SELECT * FROM athena_tactical_markers WHERE tenant_id=? AND world_name=? AND active=1 AND deleted_at IS NULL';
        if (!$includeDraft) $sql .= " AND status='PUBLISHED'";
        $s=$this->pdo->prepare($sql.' ORDER BY priority DESC,updated_at DESC'); $s->execute([$tenantId,$world]);
        return array_map([$this,'decode'], $s->fetchAll(PDO::FETCH_ASSOC));
    }

    /** @param array<string,mixed> $data */
    public function create(int $tenantId, int $userId, array $data): array
    {
        $uuid=$this->uuid(); $this->pdo->beginTransaction();
        try {
            $event=$this->event($tenantId,(string)$data['world_name'],$uuid,'MARKER_CREATE',null);
            $s=$this->pdo->prepare('INSERT INTO athena_tactical_markers (uuid,tenant_id,operation_id,kit_id,world_name,type,symbol,affiliation,label,description,geometry_type,coordinates,rotation,scale,color,opacity,priority,status,visibility_scope,visibility_ref,created_by,published_at,revision) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
            $status=(string)($data['status']??'DRAFT');
            $s->execute([$uuid,$tenantId,$data['operation_id']??null,$data['kit_id']??null,$data['world_name'],$data['type']??'MARKER',$data['symbol']??'mil_dot',$data['affiliation']??'UNKNOWN',$data['label']??'',$data['description']??null,$data['geometry_type']??'POINT',json_encode($data['coordinates'],JSON_THROW_ON_ERROR),$data['rotation']??0,$data['scale']??1,$data['color']??'#f5c542',$data['opacity']??1,$data['priority']??0,$status,$data['visibility_scope']??'COMMUNITY',$data['visibility_ref']??null,$userId,$status==='PUBLISHED'?date('Y-m-d H:i:s'):null,$event]);
            $row=$this->find($tenantId,$uuid); $this->replaceEventPayload($event,$row); $this->pdo->commit(); return $row;
        } catch (\Throwable $e) { $this->pdo->rollBack(); throw $e; }
    }

    /** @param array<string,mixed> $data */
    public function update(int $tenantId,string $uuid,int $expected,array $data): ?array
    {
        $current=$this->find($tenantId,$uuid); if(!$current)return null;
        if((int)$current['revision']!==$expected) throw new \DomainException('revision_conflict');
        $allowed=['type','symbol','affiliation','label','description','geometry_type','rotation','scale','color','opacity','priority','status','visibility_scope','visibility_ref','coordinates'];
        $sets=[];$values=[]; foreach($allowed as $key){if(array_key_exists($key,$data)){ $sets[]="$key=?";$values[]=$key==='coordinates'?json_encode($data[$key],JSON_THROW_ON_ERROR):$data[$key]; }}
        if(!$sets)return $current; $this->pdo->beginTransaction();
        try { $revision=$this->event($tenantId,(string)$current['world_name'],$uuid,'MARKER_UPDATE',null); $sets[]='revision=?';$values[]=$revision;$sets[]='published_at=IF(status=\'PUBLISHED\',COALESCE(published_at,NOW()),published_at)';$values[]=$tenantId;$values[]=$uuid;$values[]=$expected;
            $s=$this->pdo->prepare('UPDATE athena_tactical_markers SET '.implode(',',$sets).' WHERE tenant_id=? AND uuid=? AND revision=?');$s->execute($values); if($s->rowCount()!==1)throw new \DomainException('revision_conflict');
            $row=$this->find($tenantId,$uuid);$this->replaceEventPayload($revision,$row);$this->pdo->commit();return $row;
        }catch(\Throwable $e){$this->pdo->rollBack();throw $e;}
    }

    public function delete(int $tenantId,string $uuid,int $expected): bool
    { $row=$this->find($tenantId,$uuid);if(!$row)return false;if((int)$row['revision']!==$expected)throw new \DomainException('revision_conflict');$this->pdo->beginTransaction();try{$rev=$this->event($tenantId,(string)$row['world_name'],$uuid,'MARKER_DELETE',['uuid'=>$uuid]);$s=$this->pdo->prepare('UPDATE athena_tactical_markers SET active=0,deleted_at=NOW(),revision=? WHERE tenant_id=? AND uuid=? AND revision=?');$s->execute([$rev,$tenantId,$uuid,$expected]);if($s->rowCount()!==1)throw new \DomainException('revision_conflict');$this->pdo->commit();return true;}catch(\Throwable $e){$this->pdo->rollBack();throw $e;}}
    public function find(int $tenantId,string $uuid): ?array { $s=$this->pdo->prepare('SELECT * FROM athena_tactical_markers WHERE tenant_id=? AND uuid=? LIMIT 1');$s->execute([$tenantId,$uuid]);$r=$s->fetch(PDO::FETCH_ASSOC);return $r?$this->decode($r):null; }
    private function event(int $t,string $w,string $u,string $type,?array $p): int {$s=$this->pdo->prepare('INSERT INTO athena_tactical_events(tenant_id,world_name,marker_uuid,event_type,payload) VALUES(?,?,?,?,?)');$s->execute([$t,$w,$u,$type,$p?json_encode($p,JSON_THROW_ON_ERROR):null]);return(int)$this->pdo->lastInsertId();}
    private function replaceEventPayload(int $revision,array $row): void {$s=$this->pdo->prepare('UPDATE athena_tactical_events SET payload=? WHERE revision=?');$s->execute([json_encode($row,JSON_THROW_ON_ERROR),$revision]);}
    private function decode(array $r): array {$r['coordinates']=json_decode((string)$r['coordinates'],true)??[];return $r;}
    private function uuid(): string {$b=random_bytes(16);$b[6]=chr((ord($b[6])&15)|64);$b[8]=chr((ord($b[8])&63)|128);return vsprintf('%s%s-%s-%s-%s-%s%s%s',str_split(bin2hex($b),4));}
}
