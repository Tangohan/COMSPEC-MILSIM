<?php
require __DIR__.'/bootstrap.php';
use App\Services\PatchStudio\{Access,Assets,Document,Export,Projects};
use App\Services\GameEquipment\PaaDecoder;
$checks=0;
function check(bool $v,string $label): void { global $checks; $checks++; if(!$v) throw new RuntimeException($label); }
function rejects(callable $fn,string $label,string $class=InvalidArgumentException::class): void { try{$fn();}catch(Throwable $e){check($e instanceof $class,$label.': '.get_class($e).' '.$e->getMessage());return;}throw new RuntimeException($label.' accepted'); }
$doc=json_decode(file_get_contents(__DIR__.'/project.json'),true,64,JSON_THROW_ON_ERROR);
check(Document::validate($doc)===$doc,'document roundtrip');
foreach(['canvas','version','unknown','layers','assets'] as $case){$d=$doc;if($case==='canvas')$d['canvas']['width']=PHP_INT_MAX;if($case==='version')$d['version']=9;if($case==='unknown')$d['evil']='x';if($case==='layers')$d['variants']['standard']=array_fill(0,161,$d['variants']['standard'][0]);if($case==='assets')$d['variants']['standard'][0]['fill']=['type'=>'asset','assetId'=>'../../etc/passwd'];rejects(fn()=>Document::validate($d),'reject '.$case);}
$d=$doc;$d['variants']['standard'][1]['fontFamily']='https://evil';rejects(fn()=>Document::validate($d),'font injection');
$clean=Assets::svg('<svg xmlns="http://www.w3.org/2000/svg" onload="evil()"><script>evil()</script><foreignObject><div>evil</div></foreignObject><image href="https://evil/x"/><rect width="100" height="30" fill="#fff" style="background:url(https://evil)"/></svg>');
check(!preg_match('/script|foreignObject|image|onload|style|https:/',$clean),'SVG active content stripped');
rejects(fn()=>Assets::svg('<!DOCTYPE svg [<!ENTITY e SYSTEM "file:///etc/passwd">]><svg>&e;</svg>'),'entity attack');
rejects(fn()=>Assets::validate("<html>nope</html>"),'invalid signature');
rejects(fn()=>Assets::validate(str_repeat('x',Assets::MAX_BYTES+1)),'upload limit');
$pdo=patchTestDb();$repo=new Projects($pdo);$p=$repo->create(7,1,'SABER',$doc);
check((int)$p['revision']===1&&$p['document']===$doc,'create/read real SQL');
rejects(fn()=>$repo->find($p['id'],8,3,true),'cross tenant admin',OutOfBoundsException::class);
rejects(fn()=>$repo->find($p['id'],7,2,false),'private project',OutOfBoundsException::class);
$repo->query('INSERT INTO patch_studio_shares VALUES (?,?,?)',[$p['id'],2,'reader']);
check(!$repo->find($p['id'],7,2,false)['canEdit'],'reader access');
rejects(fn()=>$repo->find($p['id'],7,2,false,true),'reader cannot write',OutOfBoundsException::class);
$repo->query('UPDATE patch_studio_shares SET role=? WHERE project_id=?',['editor',$p['id']]);
$editor=$repo->find($p['id'],7,2,false,true);$d=$doc;$d['variants']['standard'][1]['letterSpacing']=8;
$p=$repo->save($editor,2,1,'SABER 2',$d,'private','active');check((int)$p['revision']===2,'editor update');
rejects(fn()=>$repo->save($editor,2,1,'SABER',$d,'private','active'),'optimistic lock',DomainException::class);
rejects(fn()=>$repo->save($editor,2,2,'SABER',$d,'organization','active'),'editor cannot publish organization');
$p=$repo->find($p['id'],7,1,false,true);$p=$repo->save($p,1,2,'SABER',$d,'organization','archived');
check($repo->query('SELECT COUNT(*) FROM patch_studio_versions')->fetchColumn()==3,'immutable history');
check($repo->find($p['id'],7,2,false)['status']==='archived','archives access');
$root=sys_get_temp_dir().'/patch-test-'.bin2hex(random_bytes(6));$store=new Assets($root);
try{
 $hash=$store->put('hello');check($store->read($hash)==='hello','private content addressing');rejects(fn()=>$store->path('../../etc/passwd'),'path traversal');
 file_put_contents($store->path($hash),'bad');rejects(fn()=>$store->read($hash),'corrupt hash',RuntimeException::class);
 $im=imagecreatetruecolor(256,128);imagealphablending($im,false);imagesavealpha($im,true);imagefill($im,0,0,imagecolorallocatealpha($im,0,0,0,127));imagefilledrectangle($im,8,8,240,120,imagecolorallocatealpha($im,100,120,70,0));ob_start();imagepng($im);$png=ob_get_clean();imagedestroy($im);
 [$clean,$w,$h]=Export::raster($png);check($w===256&&$h===128,'non square PNG dimensions');
 $paa=Export::paa($clean);check(unpack('v',substr($paa,0,2))[1]===0xff05,'DXT5 alpha');
 $decoded=PaaDecoder::decode($paa,512);check(imagesx($decoded)===256&&imagesy($decoded)===128,'existing decoder non square roundtrip');check(((imagecolorat($decoded,0,0)>>24)&127)>120,'alpha preserved');imagedestroy($decoded);
 $opaque=imagecreatetruecolor(128,64);imagefill($opaque,0,0,0x557733);$opaquePaa=\App\Support\PaaEncoder::encode($opaque);imagedestroy($opaque);check(unpack('v',substr($opaquePaa,0,2))[1]===0xff01,'opaque DXT1');
 $thumb=Export::thumbnail($png);check(getimagesizefromstring($thumb)[0]===256,'bounded preview');
 $addon=Export::addon($p,'standard',$clean);check(str_contains($addon['config'],'CfgUnitInsignia')&&str_contains($addon['config'],'CfgAthenaPatches'),'addon configs');
 check(hash_equals(substr($addon['pbo'],-20),sha1(substr($addon['pbo'],0,-21),true)),'PBO checksum');
 $zip=Export::bundle($p,'standard',$clean,[]);$zipfile=$root.'/test.zip';file_put_contents($zipfile,$zip);$z=new ZipArchive();check($z->open($zipfile)===true,'ZIP opens');check($z->getFromName('patch.paa')===$paa,'ZIP PAA exact');check(json_decode($z->getFromName('project.json'),true)===$p['document'],'ZIP editable project');$z->close();
 $repo->query('INSERT INTO patch_studio_assets (id,tenant_id,owner_id,hash,mime,bytes,label,created_at) VALUES (?,?,?,?,?,?,?,CURRENT_TIMESTAMP)',[str_repeat('a',32),7,1,$hash,'image/png',3,'test']);
 check($repo->asset(str_repeat('a',32),7,2,false)===null,'private asset denied');check($repo->asset(str_repeat('a',32),8,3,true)===null,'cross tenant asset denied');
 $pdo->exec("INSERT INTO patch_studio_asset_refs VALUES ('{$p['id']}','".str_repeat('a',32)."')");check($repo->asset(str_repeat('a',32),7,2,false)!==null,'project shared asset');
 $bad=$doc;$bad['variants']['standard'][0]['fill']=['type'=>'asset','assetId'=>str_repeat('b',32)];rejects(fn()=>$repo->create(7,1,'bad',$bad),'inaccessible asset');check($repo->query('SELECT COUNT(*) FROM patch_studio_projects')->fetchColumn()==1,'transaction rollback');
} finally {
 if(is_dir($root)){ $it=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST);foreach($it as $f){$f->isDir()?rmdir($f->getPathname()):unlink($f->getPathname());}rmdir($root); }
}
echo "PASS $checks backend checks\n";
