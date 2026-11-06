<?php

declare(strict_types=1);

namespace App\Services\PatchStudio;

use App\Core\Database;
use PDO;

final class Projects
{
    public function __construct(private ?PDO $pdo = null) { $this->pdo ??= Database::getPdo(); }
    public function query(string $sql, array $args = []): \PDOStatement { $s=$this->pdo->prepare($sql); $s->execute($args); return $s; }
    public function find(string $id, int $tenant, int $user, bool $admin, bool $write=false, bool $manage=false): array
    {
        if (!Document::assetId($id)) throw new \OutOfBoundsException('Projet introuvable.');
        $p = $this->query('SELECT p.*, s.role AS share_role FROM patch_studio_projects p LEFT JOIN patch_studio_shares s ON s.project_id=p.id AND s.user_id=? WHERE p.id=? AND p.tenant_id=?',[$user,$id,$tenant])->fetch(PDO::FETCH_ASSOC);
        if (!$p || !Access::allows($p,$tenant,$user,$admin,$p['share_role'],$write,$manage)) throw new \OutOfBoundsException('Projet introuvable.');
        $p['canEdit'] = Access::allows($p,$tenant,$user,$admin,$p['share_role'],true);
        $p['canManage'] = Access::allows($p,$tenant,$user,$admin,$p['share_role'],false,true);
        $p['document'] = json_decode($p['document'],true,64,JSON_THROW_ON_ERROR);
        return $p;
    }
    public function listing(int $tenant,int $user,bool $admin): array
    {
        return $this->query('SELECT p.id,p.name,p.owner_id,p.visibility,p.status,p.revision,p.updated_at,s.role AS share_role FROM patch_studio_projects p LEFT JOIN patch_studio_shares s ON s.project_id=p.id AND s.user_id=? WHERE p.tenant_id=? AND (p.owner_id=? OR p.visibility=\'organization\' OR s.role IS NOT NULL OR ?=1) ORDER BY p.updated_at DESC LIMIT 250',[$user,$tenant,$user,(int)$admin])->fetchAll(PDO::FETCH_ASSOC);
    }
    public function create(int $tenant,int $user,string $name,array $doc): array
    {
        $id=bin2hex(random_bytes(16)); $json=json_encode(Document::validate($doc),JSON_THROW_ON_ERROR);
        $this->pdo->beginTransaction();
        try {
            $this->query('INSERT INTO patch_studio_projects (id,tenant_id,owner_id,name,document,created_at,updated_at) VALUES (?,?,?,?,?,CURRENT_TIMESTAMP,CURRENT_TIMESTAMP)',[$id,$tenant,$user,self::name($name),$json]);
            $this->record($id,1,$user,$json,$doc,$tenant);
            $this->pdo->commit();
        } catch (\Throwable $e) { $this->pdo->rollBack(); throw $e; }
        return $this->find($id,$tenant,$user,false);
    }
    public function save(array $p,int $user,int $expected,string $name,array $doc,string $visibility,string $status): array
    {
        Document::validate($doc);
        if (!in_array($visibility,['private','organization'],true) || !in_array($status,['active','archived','deleted'],true)) throw new \InvalidArgumentException('État invalide.');
        if (!$p['canManage'] && ($visibility!==$p['visibility'] || $status!==$p['status'])) throw new \InvalidArgumentException('Seul le propriétaire ou administrateur peut modifier le partage ou l’état.');
        $json=json_encode($doc,JSON_THROW_ON_ERROR);
        $this->pdo->beginTransaction();
        try {
            $s=$this->query('UPDATE patch_studio_projects SET name=?,document=?,revision=revision+1,visibility=?,status=?,updated_at=CURRENT_TIMESTAMP WHERE id=? AND tenant_id=? AND revision=?',[self::name($name),$json,$visibility,$status,$p['id'],$p['tenant_id'],$expected]);
            if ($s->rowCount()!==1) throw new \DomainException('Projet modifié ailleurs. Rechargez ou enregistrez une copie.');
            $this->record($p['id'],$expected+1,$user,$json,$doc,(int)$p['tenant_id']);
            $this->pdo->commit();
        } catch (\Throwable $e) { $this->pdo->rollBack(); throw $e; }
        return $this->find($p['id'],(int)$p['tenant_id'],$user,(bool)$p['canManage']);
    }
    private function record(string $id,int $rev,int $user,string $json,array $doc,int $tenant): void
    {
        $this->query('INSERT INTO patch_studio_versions (project_id,revision,author_id,document,created_at) VALUES (?,?,?,?,CURRENT_TIMESTAMP)',[$id,$rev,$user,$json]);
        foreach (Document::assets($doc) as $asset) {
            if (!$this->asset($asset,$tenant,$user,false)) throw new \InvalidArgumentException('Ressource inaccessible.');
            if (!$this->query('SELECT 1 FROM patch_studio_asset_refs WHERE project_id=? AND asset_id=?',[$id,$asset])->fetchColumn()) $this->query('INSERT INTO patch_studio_asset_refs (project_id,asset_id) VALUES (?,?)',[$id,$asset]);
        }
    }
    public function asset(string $id,int $tenant,int $user,bool $admin): ?array
    {
        if (!Document::assetId($id)) return null;
        $a=$this->query('SELECT * FROM patch_studio_assets WHERE id=? AND tenant_id=?',[$id,$tenant])->fetch(PDO::FETCH_ASSOC);
        if (!$a) return null;
        if ((int)$a['owner_id']===$user || $admin) return $a;
        $ok=$this->query('SELECT 1 FROM patch_studio_asset_refs r JOIN patch_studio_projects p ON p.id=r.project_id LEFT JOIN patch_studio_shares s ON s.project_id=p.id AND s.user_id=? WHERE r.asset_id=? AND p.tenant_id=? AND p.status<>\'deleted\' AND (p.owner_id=? OR p.visibility=\'organization\' OR s.role IS NOT NULL) LIMIT 1',[$user,$id,$tenant,$user])->fetchColumn();
        return $ok ? $a : null;
    }
    public static function name(string $name): string
    {
        $name=trim($name);
        if ($name==='' || mb_strlen($name)>120 || preg_match('/[\x00-\x1F]/',$name)) throw new \InvalidArgumentException('Nom requis (120 caractères maximum).');
        return $name;
    }
}
