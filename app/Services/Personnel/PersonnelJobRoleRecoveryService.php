<?php

declare(strict_types=1);

namespace App\Services\Personnel;

use PDO;
use RuntimeException;

/** Evidence-only, tenant-scoped recovery. No access role or permission is ever created or assigned. */
final class PersonnelJobRoleRecoveryService
{
    private const RESERVED = ['member','community_owner','tenant_admin','deputy_commander','trainer','instructor','senior_instructor','recruiter','hr','deputy_hr','super_admin','site_super_admin','position_training','position_active'];
    private array $tables = [];

    public function __construct(private PDO $pdo) {}

    /** Read-only: no schema creation, materialisation, updates or permission changes. */
    public function preview(int $tenantId, array $labels = []): array
    {
        if ($tenantId < 1) throw new RuntimeException('Communauté invalide.');
        $labels = array_values(array_filter(array_map(static fn($v): string=>trim((string)$v),$labels)));
        $candidates=[];$skipped=[];
        $add=function(string $label, ?int $user, string $detail, array $evidence, bool $primary=false) use (&$candidates,$labels): void {
            $label=trim($label);
            if ($label==='' || in_array(mb_strtolower($label,'UTF-8'),['membre','member'],true)) return;
            if ($labels!==[] && !array_filter($labels,fn(string $filter):bool=>$this->matches($filter,$label,(string)($evidence['slug']??'')))) return;
            $label=mb_substr($label,0,120);
            $key=mb_strtolower($label,'UTF-8').'|'.($user??0);
            $candidates[$key]??=['label'=>$label,'user_id'=>$user,'detail'=>mb_substr(trim($detail),0,150),'evidence'=>[],'was_primary'=>false];
            $candidates[$key]['evidence'][]=$evidence;
            $candidates[$key]['was_primary']=$candidates[$key]['was_primary'] || $primary;
        };
        // Immutable RBAC migration snapshots retain lost custom labels and their historical holders.
        if ($this->hasTable('access_control_v2_role_snapshot')) {
            $st=$this->pdo->prepare('SELECT * FROM access_control_v2_role_snapshot WHERE tenant_id=? ORDER BY snapshot_id DESC LIMIT 10000');$st->execute([$tenantId]);
            $seen=[];
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $old=(int)$row['role_id'];
                if (isset($seen[$old])) continue;
                $seen[$old]=true;$slug=mb_strtolower(trim((string)$row['role_slug']),'UTF-8');
                if (in_array($slug,self::RESERVED,true) || str_starts_with($slug,'community_access-')) continue;
                $proof=['source'=>'access_control_v2_role_snapshot','id'=>(int)$row['snapshot_id'],'legacy_role_id'=>$old,'slug'=>$slug,'at'=>$row['migrated_at']??null];
                $add((string)$row['role_name'],null,'',$proof);
                foreach ([['access_control_v2_user_primary_role_snapshot',true],['access_control_v2_user_role_snapshot',false],['access_control_v2_tenant_user_role_snapshot',false]] as [$table,$primary]) {
                    if (!$this->hasTable($table)) continue;
                    $query='SELECT DISTINCT user_id FROM '.$table.' WHERE role_id=?';$args=[$old];
                    if ($table==='access_control_v2_tenant_user_role_snapshot') {$query.=' AND tenant_id=?';$args[]=$tenantId;}
                    $holders=$this->pdo->prepare($query);$holders->execute($args);
                    foreach ($holders->fetchAll(PDO::FETCH_COLUMN) as $user) $add((string)$row['role_name'],(int)$user,'',$proof+['assignment_source'=>$table],$primary);
                }
            }
        }
        // Current retained ORBAT labels are evidence of a named post, without coupling future edits.
        if ($this->hasTable('personnel_assignments') && $this->hasTable('users') && $this->hasTable('units') && $this->hasColumn('personnel_assignments','role_name')) {
            $sql='SELECT a.* FROM personnel_assignments a JOIN users u ON u.id=a.user_id JOIN units un ON un.id=a.unit_id AND un.tenant_id=u.tenant_id WHERE u.tenant_id=?';
            if ($this->hasColumn('personnel_assignments','ended_at')) $sql.=' AND (a.ended_at IS NULL OR a.ended_at >= CURRENT_DATE)';
            if ($this->hasColumn('personnel_assignments','status')) $sql.=" AND (a.status='active' OR a.status IS NULL OR a.status='')";
            $st=$this->pdo->prepare($sql.' ORDER BY a.id DESC LIMIT 10000');$st->execute([$tenantId]);
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) $add((string)($row['role_name']??''),(int)$row['user_id'],'',['source'=>'personnel_assignments','id'=>(int)$row['id'],'unit_id'=>(int)($row['unit_id']??0)],!empty($row['is_primary']));
        }
        // The latest correction is useful only when it contains a label or still-resolvable job ID.
        if ($this->hasTable('personnel_correction_requests')) {
            $st=$this->pdo->prepare('SELECT id,target_user_id,status,before_json,proposed_json,created_at FROM personnel_correction_requests WHERE tenant_id=? ORDER BY id DESC LIMIT 10000');$st->execute([$tenantId]);$seen=[];
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $user=(int)$row['target_user_id'];if (isset($seen[$user])) continue;
                $seen[$user]=true;
                $before=json_decode((string)$row['before_json'],true);$proposed=json_decode((string)$row['proposed_json'],true);
                $field=($row['status']==='approved' && is_array($proposed) && array_key_exists('job_roles',$proposed))?'proposed_json':'before_json';
                $data=$field==='proposed_json'?$proposed:$before;
                $items=is_array($data)?($data['job_roles']??[]):[];
                if (is_string($items)) $items=json_decode($items,true);
                if (!is_array($items)) continue;
                foreach ($items as $item) {
                    if (!is_array($item)) continue;
                    $label=trim((string)($item['role_name']??$item['name']??$item['label']??''));
                    $old=(int)($item['role_id']??$item['personnel_job_role_id']??0);
                    if ($label==='' && $old>0 && $this->hasTable('personnel_job_roles')) {
                        $lookup=$this->pdo->prepare('SELECT name FROM personnel_job_roles WHERE tenant_id=? AND id=?');$lookup->execute([$tenantId,$old]);$label=(string)($lookup->fetchColumn()?:'');
                    }
                    if ($label==='') {$skipped[]=['source'=>'personnel_correction_requests','id'=>(int)$row['id'],'user_id'=>$user,'legacy_job_id'=>$old,'reason'=>'label_missing_no_inference'];continue;}
                    $add($label,$user,(string)($item['detail']??$item['role_detail']??''),['source'=>'personnel_correction_requests','id'=>(int)$row['id'],'field'=>$field,'at'=>$row['created_at']??null],!empty($item['is_primary']));
                }
            }
        }
        $ready=$this->hasTable('personnel_job_roles') && $this->hasTable('personnel_job_role_categories') && $this->hasTable('personnel_profile_job_roles');
        foreach ($candidates as &$candidate) {
            $candidate['existing_job_id']=$this->findJob($tenantId,$candidate['label']);
            $user=(int)($candidate['user_id']??0);$valid=$user>0 && $this->belongs($tenantId,$user);
            $current=$valid?$this->assignments($tenantId,$user):[];
            $same=$candidate['existing_job_id']>0 && in_array($candidate['existing_job_id'],$current,true);
            $candidate['assignment_conflict']=$valid && $current!==[] && !$same;
            $candidate['can_assign']=$ready && $valid && ($current===[] || $same);
            $candidate['already_assigned']=$same;
            if ($user>0 && !$valid) $candidate['skip_reason']='user_not_in_current_tenant';
            if ($candidate['assignment_conflict']) $candidate['skip_reason']='current_jobs_preserved';
        }
        unset($candidate);
        $missing=[];
        foreach ($labels as $label) if (!array_filter($candidates,fn(array $c):bool=>$this->matches($label,$c['label'],implode(' ',array_column($c['evidence'],'slug'))))) $missing[]=$label;
        return ['tenant_id'=>$tenantId,'ready'=>$ready,'dry_run'=>true,'candidates'=>array_values($candidates),'missing_labels'=>$missing,'skipped'=>$skipped,
            'totals'=>['candidates'=>count($candidates),'assignable'=>count(array_filter($candidates,static fn(array $c):bool=>$c['can_assign'] && !$c['already_assigned']))]];
    }

    /** Explicit apply only: append recovered jobs/holders; current assignments, labels and grants stay intact. */
    public function apply(int $tenantId, array $labels = [], ?int $actorId = null): array
    {
        $report=$this->preview($tenantId,$labels);
        if (!$report['ready']) throw new RuntimeException('Les tables des emplois doivent être migrées avant la récupération.');
        $this->ensureAudit();
        $this->pdo->beginTransaction();
        try {
            $lock=$this->pdo->prepare('SELECT id FROM tenants WHERE id=?'.($this->mysql()?' FOR UPDATE':''));$lock->execute([$tenantId]);
            if (!$lock->fetchColumn()) throw new RuntimeException('Communauté introuvable.');
            $report=$this->preview($tenantId,$labels);
            $report+=['restored_jobs'=>0,'restored_assignments'=>0,'unchanged'=>0,'batch_id'=>bin2hex(random_bytes(12))];
            $restoredForUser=[];
            foreach ($report['candidates'] as $candidate) {
                $job=$this->findJob($tenantId,$candidate['label']);$new=false;
                if ($job<1) {$job=$this->createJob($tenantId,$candidate['label']);$new=true;$report['restored_jobs']++;}
                $assigned=false;$user=(int)($candidate['user_id']??0);
                // Locking reads see the latest committed assignments even under REPEATABLE READ.
                // Keep a concurrent HR edit or tenant transfer that happened after the preview.
                $valid=$user>0 && $this->belongs($tenantId,$user,true);
                $current=$valid?$this->assignments($tenantId,$user,true):[];
                $same=in_array($job,$current,true);
                $foreignCurrent=array_diff($current,$restoredForUser[$user]??[]);
                $candidate['assignment_conflict']=$valid && $foreignCurrent!==[] && !$same;
                if ($valid && !$same && $foreignCurrent===[]) {
                    $hasPrimary=$this->pdo->prepare('SELECT 1 FROM personnel_profile_job_roles WHERE tenant_id=? AND user_id=? AND is_primary=1 LIMIT 1');$hasPrimary->execute([$tenantId,$user]);
                    $primary=$candidate['was_primary'] && !$hasPrimary->fetchColumn()?1:0;
                    $sql='INSERT '.($this->mysql()?'IGNORE':'OR IGNORE').' INTO personnel_profile_job_roles(tenant_id,user_id,personnel_job_role_id,is_primary,sort_order,role_detail) VALUES (?,?,?,?,0,?)';
                    $st=$this->pdo->prepare($sql);$st->execute([$tenantId,$user,$job,$primary,$candidate['detail']!==''?$candidate['detail']:null]);
                    $assigned=$st->rowCount()>0;if ($assigned) {$report['restored_assignments']++;$restoredForUser[$user][]=$job;}
                }
                if (!$new && !$assigned) $report['unchanged']++;
                $proof=json_encode($candidate['evidence'],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
                $hash=hash('sha256',$tenantId.'|'.$candidate['label'].'|'.$user.'|'.$proof);
                $sql='INSERT '.($this->mysql()?'IGNORE':'OR IGNORE').' INTO personnel_job_role_recovery_log(tenant_id,evidence_hash,batch_id,actor_user_id,user_id,job_role_id,label,evidence_json,outcome) VALUES (?,?,?,?,?,?,?,?,?)';
                $st=$this->pdo->prepare($sql);$st->execute([$tenantId,$hash,$report['batch_id'],$actorId,$user?:null,$job,$candidate['label'],$proof,$assigned?'assignment_restored':($new?'definition_restored':($candidate['assignment_conflict']?'current_jobs_preserved':'already_present'))]);
            }
            $this->pdo->commit();$report['dry_run']=false;return $report;
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();throw $e;
        }
    }

    private function createJob(int $tenant,string $label): int
    {
        $st=$this->pdo->prepare("SELECT id FROM personnel_job_role_categories WHERE tenant_id=? AND slug='recovered-employment' LIMIT 1");$st->execute([$tenant]);$category=(int)$st->fetchColumn();
        if ($category<1) {
            $q=$this->pdo->prepare("INSERT INTO personnel_job_role_categories(tenant_id,parent_id,name,slug,sort_order) VALUES (?,NULL,'Emplois récupérés','recovered-employment',0)");$q->execute([$tenant]);$category=(int)$this->pdo->lastInsertId();
        }
        $slug='recovered-'.substr(hash('sha256',mb_strtolower($label,'UTF-8')),0,32);
        $st=$this->pdo->prepare('INSERT INTO personnel_job_roles(tenant_id,category_id,name,slug,description,sort_order,is_system) VALUES (?,?,?,?,?,0,0)');
        $st->execute([$tenant,$category,$label,$slug,'Récupéré depuis une trace conservée ; voir le journal de récupération.']);return (int)$this->pdo->lastInsertId();
    }

    private function findJob(int $tenant,string $label): int
    {
        if (!$this->hasTable('personnel_job_roles')) return 0;
        $st=$this->pdo->prepare('SELECT id,name FROM personnel_job_roles WHERE tenant_id=? ORDER BY id');$st->execute([$tenant]);
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) if (mb_strtolower(trim($row['name']),'UTF-8')===mb_strtolower(trim($label),'UTF-8')) return (int)$row['id'];
        return 0;
    }
    private function belongs(int $tenant,int $user,bool $lock=false): bool
    {
        if (!$this->hasTable('users')) return false;
        $st=$this->pdo->prepare('SELECT id FROM users WHERE id=? AND tenant_id=?'.($lock && $this->mysql()?' FOR UPDATE':''));$st->execute([$user,$tenant]);return (bool)$st->fetchColumn();
    }
    private function assignments(int $tenant,int $user,bool $lock=false): array
    {
        if (!$this->hasTable('personnel_profile_job_roles')) return [];
        $st=$this->pdo->prepare('SELECT personnel_job_role_id FROM personnel_profile_job_roles WHERE tenant_id=? AND user_id=?'.($lock && $this->mysql()?' FOR UPDATE':''));$st->execute([$tenant,$user]);return array_map('intval',$st->fetchAll(PDO::FETCH_COLUMN));
    }
    private function matches(string $filter,string $label,string $slug=''): bool
    {
        return (bool)preg_match('/(?<![\\pL\\pN])'.preg_quote($filter,'/').'(?![\\pL\\pN])/iu',$label.' '.$slug);
    }
    private function mysql(): bool { return $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql'; }
    private function hasTable(string $table): bool
    {
        if (array_key_exists($table,$this->tables)) return $this->tables[$table];
        if ($this->mysql()) {$st=$this->pdo->prepare('SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?');$st->execute([$table]);}
        else {$st=$this->pdo->prepare("SELECT 1 FROM sqlite_master WHERE type='table' AND name=?");$st->execute([$table]);}
        return $this->tables[$table]=(bool)$st->fetchColumn();
    }
    private function hasColumn(string $table,string $column): bool
    {
        if ($this->mysql()) {$st=$this->pdo->prepare('SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?');$st->execute([$table,$column]);return (bool)$st->fetchColumn();}
        foreach ($this->pdo->query('PRAGMA table_info('.$table.')')->fetchAll(PDO::FETCH_ASSOC) as $row) if ($row['name']===$column) return true;
        return false;
    }
    private function ensureAudit(): void
    {
        $sql=$this->mysql()?"CREATE TABLE IF NOT EXISTS personnel_job_role_recovery_log(id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,tenant_id INT UNSIGNED NOT NULL,evidence_hash CHAR(64) NOT NULL,batch_id CHAR(24) NOT NULL,actor_user_id INT UNSIGNED NULL,user_id INT UNSIGNED NULL,job_role_id INT UNSIGNED NOT NULL,label VARCHAR(120) NOT NULL,evidence_json LONGTEXT NOT NULL,outcome VARCHAR(64) NOT NULL,created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,UNIQUE KEY pjrr_evidence(tenant_id,evidence_hash)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4":"CREATE TABLE IF NOT EXISTS personnel_job_role_recovery_log(id INTEGER PRIMARY KEY AUTOINCREMENT,tenant_id INTEGER NOT NULL,evidence_hash TEXT NOT NULL,batch_id TEXT NOT NULL,actor_user_id INTEGER,user_id INTEGER,job_role_id INTEGER NOT NULL,label TEXT NOT NULL,evidence_json TEXT NOT NULL,outcome TEXT NOT NULL,created_at TEXT DEFAULT CURRENT_TIMESTAMP,UNIQUE(tenant_id,evidence_hash))";
        $this->pdo->exec($sql);
    }
}
