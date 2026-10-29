<?php

declare(strict_types=1);

use App\Support\SqlText;

/** Additive import: every surviving legacy field is retained until an operator verifies its recovery. */
return static function (PDO $pdo): void {
    $hasColumn = static function (string $table, string $column) use ($pdo): bool {
        $st=$pdo->prepare('SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=? LIMIT 1');
        $st->execute([$table,$column]);
        return (bool)$st->fetchColumn();
    };
    $hasTable = static function (string $table) use ($pdo): bool {
        $st=$pdo->prepare('SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? LIMIT 1');
        $st->execute([$table]);
        return (bool)$st->fetchColumn();
    };
    foreach (['personnel_profiles','personnel_job_roles','personnel_job_role_categories','personnel_profile_job_roles','users'] as $table) {
        if (!$hasTable($table)) return;
    }
    $fields=[];
    foreach (['primary_role','secondary_role','personnel_job_role_id','role_sub_label'] as $field) {
        if ($hasColumn('personnel_profiles',$field)) $fields[]=$field;
    }
    if ($fields===[]) return;
    // Retaining historical columns must not reattach jobs that HR deliberately removed later.
    // Record each imported legacy payload, in the same transaction as its append-only import.
    $pdo->exec("CREATE TABLE IF NOT EXISTS personnel_job_role_legacy_imports (
        tenant_id INT UNSIGNED NOT NULL, user_id INT UNSIGNED NOT NULL, payload_hash CHAR(64) NOT NULL,
        imported_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY(tenant_id,user_id,payload_hash)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $receipt=$pdo->prepare('SELECT 1 FROM personnel_job_role_legacy_imports WHERE tenant_id=? AND user_id=? AND payload_hash=?');
    $record=$pdo->prepare('INSERT IGNORE INTO personnel_job_role_legacy_imports(tenant_id,user_id,payload_hash) VALUES (?,?,?)');
    $select=implode(',',array_map(static fn(string $field):string=>'pp.`'.$field.'`',$fields));
    $rows=$pdo->query('SELECT pp.user_id,u.tenant_id,'.$select.' FROM personnel_profiles pp JOIN users u ON u.id=pp.user_id')->fetchAll(PDO::FETCH_ASSOC);
    $lookup=$pdo->prepare('SELECT id FROM personnel_job_roles WHERE tenant_id=? AND '.SqlText::normalizedEquals($pdo,'name').' ORDER BY id LIMIT 1');
    $category=$pdo->prepare("SELECT id FROM personnel_job_role_categories WHERE tenant_id=? AND slug='organisation' LIMIT 1");
    $createCategory=$pdo->prepare("INSERT IGNORE INTO personnel_job_role_categories(tenant_id,parent_id,name,slug,sort_order) VALUES (?,NULL,'Organisation','organisation',0)");
    $createRole=$pdo->prepare('INSERT IGNORE INTO personnel_job_roles(tenant_id,category_id,name,slug,description,sort_order,is_system) VALUES (?,?,?,?,NULL,0,0)');
    $existing=$pdo->prepare('SELECT personnel_job_role_id,is_primary,role_detail FROM personnel_profile_job_roles WHERE tenant_id=? AND user_id=?');
    $insert=$pdo->prepare('INSERT IGNORE INTO personnel_profile_job_roles(tenant_id,user_id,personnel_job_role_id,is_primary,sort_order,role_detail) VALUES (?,?,?,?,?,?)');
    $imported=0;
    foreach ($rows as $row) {
        $tenant=(int)$row['tenant_id'];$user=(int)$row['user_id'];
        if ($tenant<1 || $user<1) continue;
        $payload=[];
        foreach ($fields as $field) $payload[$field]=$row[$field]??null;
        $hash=hash('sha256',json_encode($payload,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR));
        $receipt->execute([$tenant,$user,$hash]);
        if ($receipt->fetchColumn()) continue;
        $pdo->beginTransaction();
        try {
            $existing->execute([$tenant,$user]);$present=$existing->fetchAll(PDO::FETCH_ASSOC);
            $known=array_fill_keys(array_map(static fn(array $r):int=>(int)$r['personnel_job_role_id'],$present),true);
            $primary=(bool)array_filter($present,static fn(array $r):bool=>(int)$r['is_primary']===1);
            $roles=[];
            $oldId=(int)($row['personnel_job_role_id']??0);
            if ($oldId>0) {
                $ref=$pdo->prepare('SELECT id FROM personnel_job_roles WHERE tenant_id=? AND id=?');$ref->execute([$tenant,$oldId]);
                if ($ref->fetchColumn()) $roles[$oldId]=trim((string)($row['role_sub_label']??''));
            }
            foreach (['primary_role','secondary_role'] as $field) {
                $label=trim((string)($row[$field]??''));if ($label==='') continue;
                $lookup->execute([$tenant,mb_strtolower($label,'UTF-8')]);$role=(int)$lookup->fetchColumn();
                if ($role<1) {
                    $createCategory->execute([$tenant]);$category->execute([$tenant]);$cat=(int)$category->fetchColumn();
                    $slug='legacy-'.substr(hash('sha256',mb_strtolower($label,'UTF-8')),0,32);
                    $createRole->execute([$tenant,$cat,mb_substr($label,0,120),$slug]);
                    $lookup->execute([$tenant,mb_strtolower(mb_substr($label,0,120),'UTF-8')]);$role=(int)$lookup->fetchColumn();
                }
                if ($role>0 && !isset($roles[$role])) $roles[$role]='';
            }
            foreach ($roles as $role=>$detail) {
                if (isset($known[$role])) continue;
                $insert->execute([$tenant,$user,$role,$primary?0:1,count($known),$detail!==''?mb_substr($detail,0,150):null]);
                if ($insert->rowCount()>0) {$imported++;$primary=true;$known[$role]=true;}
            }
            $record->execute([$tenant,$user,$hash]);
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            echo '  [ATTENTION] import emplois user '.$user.' conservé en champs historiques : '.$e->getMessage()."\n";
        }
    }
    echo '  consolidation additive : '.$imported." attribution(s) ajoutée(s), champs historiques et attributions existantes conservés.\n";
};
