<?php

declare(strict_types=1);
namespace App\Controllers\Api;

use App\Core\{Request,Response};
use App\Services\PatchStudio\{Assets,Export,Projects};
use App\Support\ComspecApiKeyAuth;
use PDO;

/** Lecture uniquement, compte lié obligatoire ; une clé de communauté seule ne lit aucun projet. */
final class AtakPatchesApiController
{
    public function index(Request $r,array $params=[]): Response
    {
        return $this->run(function(Projects $db,int $t,int $u) use($r) {
            $offset=max(0,min(10000,(int)$r->query('offset',0)));
            $rows=$db->query('SELECT p.id,p.name,p.revision AS project_revision,r.* FROM patch_studio_projects p JOIN patch_studio_releases r ON r.project_id=p.id LEFT JOIN patch_studio_shares s ON s.project_id=p.id AND s.user_id=? WHERE p.tenant_id=? AND p.status=\'active\' AND (p.owner_id=? OR p.visibility=\'organization\' OR s.role IS NOT NULL) AND r.revision=(SELECT MAX(r2.revision) FROM patch_studio_releases r2 WHERE r2.project_id=p.id AND r2.variant=r.variant) ORDER BY p.id,r.variant LIMIT 11 OFFSET '.$offset,[$u,$t,$u])->fetchAll(PDO::FETCH_ASSOC);
            $more=count($rows)>10; $rows=array_slice($rows,0,10);
            $out=[];
            foreach($rows as $v) {
                $v['id']=$v['project_id'];
                $out[]=['id'=>$v['id'],'name'=>$v['name'],'revision'=>(int)$v['revision'],'variant'=>$v['variant'],'hash'=>$v['paa_hash'],'pngHash'=>$v['preview_hash'],'class'=>Export::className($v,$v['variant'],$v['paa_hash']),'width'=>(int)$v['width'],'height'=>(int)$v['height'],'stale'=>(int)$v['project_revision']!==(int)$v['revision'],
                    'texture'=>'/api/atak/patches/'.$v['id'].'/'.$v['revision'].'/'.$v['variant'].'/paa',
                    'preview'=>'/api/atak/patches/'.$v['id'].'/'.$v['revision'].'/'.$v['variant'].'/png'];
            }
            return Response::json(['patches'=>$out,'nextOffset'=>$more?$offset+10:null,'application'=>'mounted_addon_or_mission']);
        });
    }
    public function file(Request $r,array $params=[]): Response
    {
        return $this->run(function(Projects $db,int $t,int $u) use($params,$r) {
            $p=$db->find((string)$params['id'],$t,$u,false);
            if ($p['status']!=='active') throw new \OutOfBoundsException();
            $variant=$params['variant']??'';$format=$params['format']??'';
            if (!in_array($variant,['standard','night'],true) || !in_array($format,['png','paa'],true)) throw new \OutOfBoundsException();
            $v=$db->query('SELECT * FROM patch_studio_releases WHERE project_id=? AND revision=? AND variant=?',[$p['id'],(int)$params['revision'],$variant])->fetch(PDO::FETCH_ASSOC);
            if (!$v) throw new \OutOfBoundsException();
            $hash=$v[$format==='png'?'preview_hash':'paa_hash']; $etag='"'.$hash.'"';
            if ($r->header('If-None-Match')===$etag) return (new Response())->setStatusCode(304)->header('ETag',$etag);
            return (new Response())->header('Content-Type',$format==='png'?'image/png':'application/octet-stream')->header('ETag',$etag)->header('X-Content-SHA256',$hash)->header('X-Content-Type-Options','nosniff')->setBody((new Assets())->read($hash));
        });
    }
    private function run(callable $fn): Response
    {
        if (!ComspecApiKeyAuth::armaInlineAuthOk()) return Response::json(['error'=>'unauthorized'],401);
        $t=(int)ComspecApiKeyAuth::matchedTenantId(); $u=(int)ComspecApiKeyAuth::matchedUserId();
        if ($t<1 || $u<1) return Response::json(['error'=>'linked_account_required'],403);
        try { return $fn(new Projects(),$t,$u)->header('Cache-Control','private, no-store'); }
        catch (\OutOfBoundsException) { return Response::json(['error'=>'not_found'],404); }
        catch (\Throwable $e) { error_log('ATAK patches: '.$e->getMessage()); return Response::json(['error'=>'unavailable'],503); }
    }
}
