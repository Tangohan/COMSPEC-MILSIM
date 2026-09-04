<?php
declare(strict_types=1);
namespace App\Repositories;

use App\Core\Database;
use PDO;

final class AtakDeviceAuthRepository
{
    public function __construct(private ?PDO $connection = null) {}
    private function db(): PDO { return $this->connection ??= Database::getPdo(); }

    public function createPairing(array $v): int
    {
        $q=$this->db()->prepare('INSERT INTO atak_device_pairings (device_code_hash,user_code_hash,user_code_hint,terminal_uid,steam_uid,mod_version,request_ip,user_agent,expires_at) VALUES (?,?,?,?,?,?,?,?,?)');
        $q->execute([$v['device_hash'],$v['user_hash'],$v['user_hint'],$v['terminal_uid'],$v['steam_uid'],$v['mod_version'],$v['ip'],$v['ua'],$v['expires_at']]);
        return (int)$this->db()->lastInsertId();
    }
    public function pairingByDeviceHash(string $hash, bool $lock=false): ?array
    {
        $q=$this->db()->prepare('SELECT * FROM atak_device_pairings WHERE device_code_hash=? LIMIT 1'.($lock?' FOR UPDATE':'')); $q->execute([$hash]); return $q->fetch(PDO::FETCH_ASSOC) ?: null;
    }
    public function pairingByUserHash(string $hash): ?array
    {
        $q=$this->db()->prepare('SELECT * FROM atak_device_pairings WHERE user_code_hash=? LIMIT 1'); $q->execute([$hash]); return $q->fetch(PDO::FETCH_ASSOC) ?: null;
    }
    public function expire(int $id): void { $this->db()->prepare("UPDATE atak_device_pairings SET status='expired' WHERE id=? AND status='pending'")->execute([$id]); }
    public function deny(int $id): bool { $q=$this->db()->prepare("UPDATE atak_device_pairings SET status='denied',denied_at=UTC_TIMESTAMP() WHERE id=? AND status='pending' AND expires_at>UTC_TIMESTAMP()"); $q->execute([$id]); return $q->rowCount()===1; }
    public function accountForUser(int $userId,int $tenantId): ?array { $q=$this->db()->prepare("SELECT a.*,m.user_id,m.tenant_id,m.status AS membership_status,'active' AS tenant_status,u.status AS user_status FROM account_tenant_memberships m JOIN athena_accounts a ON a.id=m.account_id JOIN users u ON u.id=m.user_id JOIN tenants t ON t.id=m.tenant_id WHERE m.user_id=? AND m.tenant_id=? LIMIT 1"); $q->execute([$userId,$tenantId]); return $q->fetch(PDO::FETCH_ASSOC)?:null; }
    public function enrollDevice(array $v): int
    {
        $q=$this->db()->prepare("INSERT INTO atak_trusted_devices(user_id,tenant_id,account_id,terminal_uid,steam_uid,label,approved_by,approved_at,last_seen_at,last_ip,last_mod_version) VALUES(?,?,?,?,?,?,?,UTC_TIMESTAMP(),UTC_TIMESTAMP(),?,?) ON DUPLICATE KEY UPDATE id=LAST_INSERT_ID(id),user_id=VALUES(user_id),account_id=VALUES(account_id),steam_uid=VALUES(steam_uid),label=VALUES(label),approved_by=VALUES(approved_by),approved_at=UTC_TIMESTAMP(),last_seen_at=UTC_TIMESTAMP(),last_ip=VALUES(last_ip),last_mod_version=VALUES(last_mod_version),enrollment_status='enrolled',revoked_at=NULL,revoked_by=NULL");
        $q->execute([$v['user_id'],$v['tenant_id'],$v['account_id'],$v['terminal_uid'],$v['steam_uid'],$v['label'],$v['approved_by'],$v['ip'],$v['mod_version']]); return (int)$this->db()->lastInsertId();
    }
    public function linkRegistry(int $id, int $terminalId, int $certificateId): void { $this->db()->prepare('UPDATE atak_trusted_devices SET atak_terminal_id=?,certificate_id=? WHERE id=?')->execute([$terminalId,$certificateId,$id]); }
    public function approvePairing(int $id,int $userId,int $tenantId,int $trustedId): bool { $q=$this->db()->prepare("UPDATE atak_device_pairings SET status='approved',user_id=?,tenant_id=?,approved_by=?,approved_at=UTC_TIMESTAMP(),trusted_device_id=? WHERE id=? AND status='pending' AND expires_at>UTC_TIMESTAMP()"); $q->execute([$userId,$tenantId,$userId,$trustedId,$id]); return $q->rowCount()===1; }
    public function consumeApproved(int $id): bool { $q=$this->db()->prepare("UPDATE atak_device_pairings SET status='consumed',consumed_at=UTC_TIMESTAMP() WHERE id=? AND status='approved' AND consumed_at IS NULL"); $q->execute([$id]); return $q->rowCount()===1; }
    public function devices(int $userId,int $tenantId): array { $q=$this->db()->prepare('SELECT d.*,c.certificate_ref,c.status certificate_status FROM atak_trusted_devices d LEFT JOIN atak_certificates c ON c.id=d.certificate_id WHERE d.user_id=? AND d.tenant_id=? ORDER BY d.created_at DESC'); $q->execute([$userId,$tenantId]); return $q->fetchAll(PDO::FETCH_ASSOC)?:[]; }
    public function revokeDevice(int $id,int $userId,int $tenantId): bool { $q=$this->db()->prepare("UPDATE atak_trusted_devices SET revoked_at=UTC_TIMESTAMP(),revoked_by=?,enrollment_status='revoked' WHERE id=? AND user_id=? AND tenant_id=? AND revoked_at IS NULL"); $q->execute([$userId,$id,$userId,$tenantId]); if($q->rowCount()!==1)return false; $this->db()->prepare('UPDATE game_sessions SET revoked_at=UTC_TIMESTAMP() WHERE user_id=? AND tenant_id=? AND device_id=(SELECT terminal_uid FROM atak_trusted_devices WHERE id=?) AND revoked_at IS NULL')->execute([$userId,$tenantId,$id]); return true; }
    public function generateRecoverySet(int $userId,int $tenantId,array $codes): int { $db=$this->db(); $db->beginTransaction(); try{$db->prepare('UPDATE atak_recovery_code_sets SET revoked_at=UTC_TIMESTAMP() WHERE user_id=? AND tenant_id=? AND revoked_at IS NULL')->execute([$userId,$tenantId]); $db->prepare('INSERT INTO atak_recovery_code_sets(user_id,tenant_id) VALUES(?,?)')->execute([$userId,$tenantId]); $set=(int)$db->lastInsertId(); $q=$db->prepare('INSERT INTO atak_recovery_codes(set_id,lookup_hash,code_hash) VALUES(?,?,?)'); foreach($codes as $c)$q->execute([$set,$c['lookup'],$c['hash']]); $db->commit(); return $set;}catch(\Throwable $e){$db->rollBack();throw $e;} }
    public function recoverySummary(int $userId,int $tenantId): array { $q=$this->db()->prepare('SELECT s.created_at,s.revoked_at,COUNT(c.id) total,SUM(c.used_at IS NULL) available FROM atak_recovery_code_sets s JOIN atak_recovery_codes c ON c.set_id=s.id WHERE s.user_id=? AND s.tenant_id=? ORDER BY s.id DESC LIMIT 1');$q->execute([$userId,$tenantId]);return $q->fetch(PDO::FETCH_ASSOC)?:[]; }
    public function recoveryByLookup(string $lookup,bool $lock=false): ?array { $q=$this->db()->prepare('SELECT c.*,s.user_id,s.tenant_id,s.revoked_at set_revoked_at FROM atak_recovery_codes c JOIN atak_recovery_code_sets s ON s.id=c.set_id WHERE c.lookup_hash=? LIMIT 1'.($lock?' FOR UPDATE':''));$q->execute([$lookup]);return $q->fetch(PDO::FETCH_ASSOC)?:null; }
    public function consumeRecovery(int $id,string $terminal): bool {$q=$this->db()->prepare('UPDATE atak_recovery_codes SET used_at=UTC_TIMESTAMP(),used_terminal_uid=? WHERE id=? AND used_at IS NULL');$q->execute([$terminal,$id]);return $q->rowCount()===1;}
    public function event(string $type,?int $user,?int $tenant,?int $subject,string $ip,array $meta=[]):void{$json=json_encode($meta,JSON_UNESCAPED_SLASHES);$this->db()->prepare('INSERT INTO atak_security_events(user_id,tenant_id,event_type,subject_type,subject_id,ip_address,metadata_json) VALUES(?,?,?,\'atak_device\',?,?,?)')->execute([$user,$tenant,$type,$subject,$ip,$json?:null]);}
    public function recentEvents(int $userId,int $tenantId):array{$q=$this->db()->prepare('SELECT event_type,metadata_json,created_at FROM atak_security_events WHERE user_id=? AND tenant_id=? ORDER BY id DESC LIMIT 20');$q->execute([$userId,$tenantId]);return $q->fetchAll(PDO::FETCH_ASSOC)?:[];}
    public function transaction(callable $fn): mixed {$db=$this->db();$db->beginTransaction();try{$v=$fn();$db->commit();return $v;}catch(\Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}}
    public function cleanup():array{$a=$this->db()->exec("DELETE FROM atak_device_pairings WHERE expires_at<UTC_TIMESTAMP()-INTERVAL 7 DAY");$b=$this->db()->exec("DELETE FROM game_sessions WHERE (refresh_expires_at<UTC_TIMESTAMP() OR revoked_at<UTC_TIMESTAMP()-INTERVAL 30 DAY)");return ['pairings'=>(int)$a,'sessions'=>(int)$b];}

    public function hasActiveRecoveryCodes(int $userId, int $tenantId): bool
    {
        $q = $this->db()->prepare(
            'SELECT 1
             FROM atak_recovery_code_sets s
             JOIN atak_recovery_codes c ON c.set_id = s.id
             WHERE s.user_id = ? AND s.tenant_id = ?
               AND s.revoked_at IS NULL AND c.used_at IS NULL
             LIMIT 1'
        );
        $q->execute([$userId, $tenantId]);

        return (bool) $q->fetchColumn();
    }
}
