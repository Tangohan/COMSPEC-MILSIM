<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

final class PlatformAdminTenantAuditRepository
{
    public function __construct(private ?PDO $pdo = null) { $this->pdo ??= Database::getPdo(); }

    public function begin(int $adminId, int $tenantId, ?string $reason, string $ip, string $agent): int
    {
        $s=$this->pdo->prepare("INSERT INTO platform_admin_tenant_sessions(admin_id,tenant_id,started_at,reason,status,ip_address,user_agent) VALUES(?,?,UTC_TIMESTAMP(),?,'active',?,?)");
        $s->execute([$adminId,$tenantId,$reason,$ip,mb_substr($agent,0,500)]); return (int)$this->pdo->lastInsertId();
    }
    public function end(int $id, int $adminId): bool { $s=$this->pdo->prepare("UPDATE platform_admin_tenant_sessions SET ended_at=UTC_TIMESTAMP(),status='ended' WHERE id=? AND admin_id=? AND status='active'"); $s->execute([$id,$adminId]); return $s->rowCount()===1; }
    public function activeForTenant(int $tenantId): ?array { $s=$this->pdo->prepare("SELECT id,started_at,status FROM platform_admin_tenant_sessions WHERE tenant_id=? AND status='active' ORDER BY started_at DESC LIMIT 1"); $s->execute([$tenantId]); return $s->fetch() ?: null; }
    public function findSession(int $id): ?array { $s=$this->pdo->prepare('SELECT * FROM platform_admin_tenant_sessions WHERE id=?');$s->execute([$id]);return $s->fetch()?:null; }
    public function actions(int $sessionId, ?string $type=null): array { $sql='SELECT * FROM platform_admin_tenant_actions WHERE session_id=?'.($type?' AND action_type=?':'').' ORDER BY created_at DESC,id DESC';$s=$this->pdo->prepare($sql);$s->execute($type?[$sessionId,$type]:[$sessionId]);return $s->fetchAll(); }
    public function errors(int $sessionId): array { $s=$this->pdo->prepare('SELECT * FROM platform_admin_tenant_errors WHERE session_id=? ORDER BY created_at DESC,id DESC');$s->execute([$sessionId]);return $s->fetchAll(); }
    public function action(int $id): ?array { $s=$this->pdo->prepare('SELECT * FROM platform_admin_tenant_actions WHERE id=?');$s->execute([$id]);return $s->fetch()?:null; }
    public function insertAction(array $a): int { $s=$this->pdo->prepare('INSERT INTO platform_admin_tenant_actions(session_id,tenant_id,admin_id,request_id,action_type,module,route,http_method,entity_type,entity_id,description,before_state,after_state,metadata,is_reversible,rollback_status,rollback_of_action_id,created_at) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,UTC_TIMESTAMP())');$s->execute([$a['session_id'],$a['tenant_id'],$a['admin_id'],$a['request_id'],$a['action_type'],$a['module']??null,$a['route']??null,$a['http_method']??null,$a['entity_type']??null,$a['entity_id']??null,$a['description']??null,$a['before_state']??null,$a['after_state']??null,$a['metadata']??null,!empty($a['is_reversible'])?1:0,$a['rollback_status']??'not_requested',$a['rollback_of_action_id']??null]);return (int)$this->pdo->lastInsertId(); }
    public function insertError(array $a): void { $s=$this->pdo->prepare('INSERT INTO platform_admin_tenant_errors(session_id,tenant_id,admin_id,request_id,severity,module,route,exception_class,message,stack_trace,context_json,created_at) VALUES(?,?,?,?,?,?,?,?,?,?,?,UTC_TIMESTAMP())');$s->execute([$a['session_id'],$a['tenant_id'],$a['admin_id'],$a['request_id'],'error',$a['module']??null,$a['route'],$a['exception_class'],$a['message'],$a['stack_trace'],$a['context_json']]); }
    public function pdo(): PDO { return $this->pdo; }
}
