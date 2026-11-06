<?php

declare(strict_types=1);
namespace App\Controllers\Api;

use App\Core\{Csrf,Gate,Request,Response,Session};
use App\Services\PatchStudio\{Assets,Document,Export,Projects,Variables,PlanAccess};
use App\Services\Security\FileRateLimiter;
use App\Services\Tenant\TenantContext;
use PDO;

final class PatchStudioApiController
{
    private function context(): array { return [TenantContext::id(),(int)Session::get('user_id'),Gate::getInstance()->allows('admin.organization')]; }
    private function run(Request $r,callable $fn): Response
    {
        try {
            [$t,$u]=$this->context();
            if ($t<1 || $u<1) return Response::json(['error'=>'Session requise.'],401);
            if (!PlanAccess::allows($t)) return PlanAccess::deniedJson();
            if ($r->method()!=='GET' && !Csrf::verify($r)) return Response::json(['error'=>'Jeton CSRF invalide.'],403);
            $limiter=new FileRateLimiter();
            if ($limiter->tooManyAttempts('patch-studio:'.$t.':'.$u,180,60)) return Response::json(['error'=>'Trop de requêtes.'],429)->header('Retry-After','60');
            return $fn(new Projects(), ...$this->context())->header('Cache-Control','private, no-store');
        } catch (\OutOfBoundsException $e) { return Response::json(['error'=>$e->getMessage()],404); }
        catch (\DomainException $e) { return Response::json(['error'=>$e->getMessage()],409); }
        catch (\InvalidArgumentException|\JsonException $e) { return Response::json(['error'=>$e->getMessage()],422); }
        catch (\Throwable $e) { error_log('Patch Studio: '.$e->getMessage()); return Response::json(['error'=>'Service indisponible. Vérifiez les migrations, GD et le stockage privé.'],503); }
    }
    private function body(): array
    {
        $raw=file_get_contents('php://input',false,null,0,12582913);
        if ($raw===false || strlen($raw)>12582912) throw new \InvalidArgumentException('Requête trop volumineuse.');
        $b=json_decode($raw,true,64,JSON_THROW_ON_ERROR);
        if (!is_array($b) || array_is_list($b)) throw new \InvalidArgumentException('Objet JSON attendu.');
        return $b;
    }
    public function index(Request $r,array $params=[]): Response { return $this->run($r,fn($db,$t,$u,$a)=>Response::json(['projects'=>$db->listing($t,$u,$a)])); }
    public function show(Request $r,array $params=[]): Response { return $this->run($r,fn($db,$t,$u,$a)=>Response::json(['project'=>$db->find((string)$params['id'],$t,$u,$a)])); }
    public function create(Request $r,array $params=[]): Response
    {
        return $this->run($r,function($db,$t,$u) {
            $b=$this->body();
            if ((int)$db->query('SELECT COUNT(*) FROM patch_studio_projects WHERE tenant_id=? AND owner_id=?',[$t,$u])->fetchColumn()>=250) throw new \InvalidArgumentException('Limite de 250 projets atteinte.');
            return Response::json(['project'=>$db->create($t,$u,(string)($b['name']??''),(array)($b['document']??[]))],201);
        });
    }
    public function update(Request $r,array $params=[]): Response
    {
        return $this->run($r,function($db,$t,$u,$a) use($params) {
            $p=$db->find((string)$params['id'],$t,$u,$a,true); $b=$this->body();
            return Response::json(['project'=>$db->save($p,$u,(int)($b['revision']??0),(string)($b['name']??$p['name']),(array)($b['document']??$p['document']),(string)($b['visibility']??$p['visibility']),(string)($b['status']??$p['status']))]);
        });
    }
    public function history(Request $r,array $params=[]): Response
    {
        return $this->run($r,function($db,$t,$u,$a) use($params,$r) {
            $p=$db->find((string)$params['id'],$t,$u,$a);
            $rev=(int)$r->query('revision',0);
            if ($rev>0) {
                $v=$db->query('SELECT document FROM patch_studio_versions WHERE project_id=? AND revision=?',[$p['id'],$rev])->fetchColumn();
                if (!$v) throw new \OutOfBoundsException('Version introuvable.');
                return Response::json(['document'=>json_decode($v,true,64,JSON_THROW_ON_ERROR)]);
            }
            return Response::json(['versions'=>$db->query('SELECT revision,author_id,created_at FROM patch_studio_versions WHERE project_id=? ORDER BY revision DESC LIMIT 100',[$p['id']])->fetchAll(PDO::FETCH_ASSOC)]);
        });
    }
    public function share(Request $r,array $params=[]): Response
    {
        return $this->run($r,function($db,$t,$u,$a) use($params) {
            $p=$db->find((string)$params['id'],$t,$u,$a,false,true); $b=$this->body();
            $uid=(int)($b['userId']??0); $role=$b['role']??'';
            if (!in_array($role,['reader','editor','remove'],true) || !$db->query('SELECT 1 FROM users WHERE id=? AND tenant_id=? AND status=\'active\'',[$uid,$t])->fetchColumn()) throw new \InvalidArgumentException('Membre actif de cette organisation requis.');
            $db->query('DELETE FROM patch_studio_shares WHERE project_id=? AND user_id=?',[$p['id'],$uid]);
            if ($role!=='remove') $db->query('INSERT INTO patch_studio_shares (project_id,user_id,role) VALUES (?,?,?)',[$p['id'],$uid,$role]);
            return Response::json(['ok'=>true]);
        });
    }
    public function variables(Request $r,array $params=[]): Response
    {
        return $this->run($r,function($db,$t,$u) use($r) {
            $ids=explode(',',substr($r->queryString('users',(string)$u),0,2000));
            return Response::json(['members'=>Variables::resolve($t,$u,$ids)]);
        });
    }
    public function assets(Request $r,array $params=[]): Response
    {
        return $this->run($r,function($db,$t,$u) {
            return Response::json(['assets'=>$db->query('SELECT id,label,mime,bytes,license_note FROM patch_studio_assets WHERE tenant_id=? AND owner_id=? ORDER BY created_at DESC LIMIT 300',[$t,$u])->fetchAll(PDO::FETCH_ASSOC)]);
        });
    }
    public function upload(Request $r,array $params=[]): Response
    {
        return $this->run($r,function($db,$t,$u) use($r) {
            $f=$_FILES['file']??null;
            if (!$f || $f['error']!==UPLOAD_ERR_OK || !is_uploaded_file($f['tmp_name']) || $f['size']>Assets::MAX_BYTES) throw new \InvalidArgumentException('Envoi invalide (8 Mio maximum).');
            $quota=$db->query('SELECT COUNT(*) n,COALESCE(SUM(bytes),0) total FROM patch_studio_assets WHERE tenant_id=? AND owner_id=?',[$t,$u])->fetch(PDO::FETCH_ASSOC);
            if ($quota['n']>=300 || $quota['total']>268435456) throw new \InvalidArgumentException('Quota de ressources atteint.');
            $clean=Assets::validate((string)file_get_contents($f['tmp_name']),$r->inputString('fontLicense')==='yes');
            $hash=(new Assets())->put($clean['bytes']);
            $old=$db->query('SELECT id FROM patch_studio_assets WHERE tenant_id=? AND owner_id=? AND hash=?',[$t,$u,$hash])->fetchColumn();
            $id=$old ?: bin2hex(random_bytes(16));
            if (!$old) $db->query('INSERT INTO patch_studio_assets (id,tenant_id,owner_id,hash,mime,bytes,label,license_note,created_at) VALUES (?,?,?,?,?,?,?,?,CURRENT_TIMESTAMP)',[$id,$t,$u,$hash,$clean['mime'],strlen($clean['bytes']),mb_substr(basename((string)$f['name']),0,120),mb_substr($r->inputString('license'),0,500)]);
            return Response::json(['asset'=>$db->asset($id,$t,$u,false)],201);
        });
    }
    public function asset(Request $r,array $params=[]): Response
    {
        return $this->run($r,function($db,$t,$u,$a) use($params) {
            $row=$db->asset((string)$params['id'],$t,$u,$a);
            if (!$row) throw new \OutOfBoundsException('Ressource introuvable.');
            return (new Response())->header('Content-Type',$row['mime'])->header('X-Content-Type-Options','nosniff')->header('Content-Security-Policy',"default-src 'none'; sandbox")->setBody((new Assets())->read($row['hash']));
        });
    }
    public function export(Request $r,array $params=[]): Response
    {
        return $this->run($r,function($db,$t,$u,$a) use($params) {
            $lockDir=base_path('storage/cache/patch-studio');
            if (!is_dir($lockDir) && !mkdir($lockDir,0700,true) && !is_dir($lockDir)) throw new \RuntimeException('Verrou de rendu indisponible.');
            $lock=fopen($lockDir.'/'.hash('sha256',$t.':'.$u).'.lock','c');
            if (!$lock) throw new \RuntimeException('Verrou de rendu indisponible.');
            if (!flock($lock,LOCK_EX|LOCK_NB)) { fclose($lock); return Response::json(['error'=>'Un rendu est déjà en cours.'],429)->header('Retry-After','3'); }
            try {
            $p=$db->find((string)$params['id'],$t,$u,$a); $b=$this->body();
            if ((int)($b['revision']??0)!==(int)$p['revision']) throw new \DomainException('Enregistrez la révision actuelle avant le rendu.');
            if ((new FileRateLimiter())->tooManyAttempts('patch-render:'.$t.':'.$u,12,60)) return Response::json(['error'=>'12 rendus maximum par minute.'],429)->header('Retry-After','60');
            $variant=(string)($b['variant']??'standard');
            if (!isset($p['document']['variants'][$variant])) throw new \InvalidArgumentException('Variante inconnue.');
            $format=$b['format']??'png';
            if (!in_array($format,['png','paa','zip','pbo','publish'],true)) throw new \InvalidArgumentException('Format inconnu.');
            $bytes=base64_decode((string)($b['png']??''),true);
            if ($bytes===false) throw new \InvalidArgumentException('PNG invalide.');
            [$png,$w,$h]=Export::raster($bytes);
            if (abs($w/$h - $p['document']['canvas']['width']/$p['document']['canvas']['height'])>0.01 && empty($b['letterbox']) && empty($b['selection'])) throw new \InvalidArgumentException('Proportions altérées. Utilisez le cadrage proportionnel.');
            if ($format==='publish') {
                if (!$p['canEdit'] || $p['status']!=='active' || !empty($b['selection'])) throw new \InvalidArgumentException('Publication non autorisée.');
                $paa=Export::paa($png); $store=new Assets();
                $ph=$store->put($png); $ah=$store->put($paa); $thumb=$store->put(Export::thumbnail($png));
                $old=$db->query('SELECT png_hash,paa_hash FROM patch_studio_releases WHERE project_id=? AND revision=? AND variant=?',[$p['id'],$p['revision'],$variant])->fetch(PDO::FETCH_ASSOC);
                if ($old && ($old['png_hash']!==$ph || $old['paa_hash']!==$ah)) throw new \DomainException('Cette révision est publiée. Enregistrez une nouvelle révision pour changer son rendu.');
                if (!$old) $db->query('INSERT INTO patch_studio_releases (project_id,revision,variant,png_hash,preview_hash,paa_hash,width,height,created_at) VALUES (?,?,?,?,?,?,?,?,CURRENT_TIMESTAMP)',[$p['id'],$p['revision'],$variant,$ph,$thumb,$ah,$w,$h]);
                return Response::json(['ok'=>true,'class'=>Export::className($p,$variant,$ah)]);
            }
            $data=$png; $mime='image/png';
            if ($format==='paa') { $data=Export::paa($png); $mime='application/octet-stream'; }
            if ($format==='pbo') { $data=Export::addon($p,$variant,$png)['pbo']; $mime='application/octet-stream'; }
            if ($format==='zip') {
                $assets=[];$size=0;
                foreach (Document::assets($p['document']) as $id) {
                    $asset=$db->asset($id,$t,$u,$a);
                    if (!$asset) throw new \InvalidArgumentException('Ressource inaccessible.');
                    $size+=(int)$asset['bytes']; if ($size>33554432) throw new \InvalidArgumentException('Archive limitée à 32 Mio de ressources.');
                    $asset['data']=(new Assets())->read($asset['hash']);$assets[]=$asset;
                }
                $data=Export::bundle($p,$variant,$png,$assets);$mime='application/zip';
            }
            return (new Response())->header('Content-Type',$mime)->header('Content-Disposition','attachment; filename="athena_'.$p['id'].'_'.$variant.'.'.$format.'"')->setBody($data);
            } finally { flock($lock,LOCK_UN); fclose($lock); }
        });
    }
}
