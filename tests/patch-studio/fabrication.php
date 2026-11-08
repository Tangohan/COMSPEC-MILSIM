<?php
require __DIR__.'/bootstrap.php';
use App\Services\PatchStudio\{Assets,Document,Export,Projects,TextureCatalog};
$checks=0;
function fabricationCheck(bool $value,string $label): void {global $checks;$checks++;if(!$value)throw new RuntimeException($label);}
function fabricationReject(callable $fn,string $label): void {try{$fn();}catch(InvalidArgumentException){fabricationCheck(true,$label);return;}throw new RuntimeException('Accepted '.$label);}
$old=json_decode(file_get_contents(__DIR__.'/project.json'),true,64,JSON_THROW_ON_ERROR);
fabricationCheck(Document::validate($old)===$old,'legacy projects remain unchanged');
$doc=$old;
$doc['render']=['lightAngle'=>225,'lightElevation'=>50,'lightIntensity'=>0.65,'relief'=>0.75,'contactShadow'=>0.45,'quality'=>'high'];
$doc['variants']['standard'][0]['material']=['type'=>'cordura500','scale'=>1,'angle'=>0,'roughness'=>0.8,'relief'=>0.8];
$doc['variants']['standard'][0]['fill']=['type'=>'texture','textureId'=>'multicam-original','scale'=>1,'repeat'=>false];
$doc['variants']['standard'][0]['stroke']+=['mode'=>'same','finish'=>'overlock','threadWidth'=>0.6,'density'=>1.2,'threadAngle'=>12,'relief'=>0.8];
$doc['variants']['standard'][1]['material']=['type'=>'velcro','scale'=>0.8,'roughness'=>0.9,'relief'=>0.5];
$doc['variants']['standard'][1]['construction']=['mode'=>'laser','supportId'=>$doc['variants']['standard'][0]['id'],'depth'=>1.35,'sealColor'=>'#312d21','sealWidth'=>0.45];
fabricationCheck(Document::validate($doc)===$doc,'new construction JSON roundtrip');
foreach(Document::MATERIALS as $type){$d=$doc;$d['variants']['standard'][0]['material']['type']=$type;fabricationCheck(Document::validate($d)===$d,'material '.$type);}
foreach(['embroidered','overlock','heatcut','none','legacy'] as $finish){$d=$doc;$d['variants']['standard'][0]['stroke']['finish']=$finish;fabricationCheck(Document::validate($d)===$d,'finish '.$finish);}
foreach(['print','embroidery','laser'] as $mode){$d=$doc;$d['variants']['standard'][1]['construction']['mode']=$mode;fabricationCheck(Document::validate($d)===$d,'text mode '.$mode);}
$badCases=[
 'render.url'=>'https://evil', 'render.quality'=>'ultra', 'render.lightAngle'=>'0" onload="evil', 'render.lightElevation'=>0, 'render.relief'=>100,
 'variants.standard.0.material.type'=>'external', 'variants.standard.0.material.scale'=>0, 'variants.standard.0.material.angle'=>361, 'variants.standard.0.material.roughness'=>-1,
 'variants.standard.0.stroke.finish'=>'glow', 'variants.standard.0.stroke.threadWidth'=>10, 'variants.standard.0.stroke.density'=>10000, 'variants.standard.0.stroke.threadAngle'=>90,
 'variants.standard.1.construction.supportId'=>'missing', 'variants.standard.1.construction.depth'=>999, 'variants.standard.1.construction.sealColor'=>'url(https://evil)', 'variants.standard.1.construction.sealWidth'=>8,
];
foreach($badCases as $path=>$value){$d=$doc;$ref=&$d;foreach(explode('.',$path) as $key)$ref=&$ref[$key];$ref=$value;unset($ref);fabricationReject(fn()=>Document::validate($d),$path);}
$d=$doc;$d['variants']['standard'][1]['construction']['supportId']=$d['variants']['standard'][1]['id'];fabricationReject(fn()=>Document::validate($d),'text cannot support its own cut');
$d=$doc;$d['variants']['standard'][0]['stroke']['mode']='custom';fabricationReject(fn()=>Document::validate($d),'custom border requires a fill');
$doc['variants']['standard'][0]['stroke']['mode']='custom';$doc['variants']['standard'][0]['stroke']['fill']=['type'=>'texture','textureId'=>'multicam-black','rotation'=>20,'repeat'=>false];
fabricationCheck(Document::validate($doc)===$doc,'independent border texture');fabricationCheck(count(TextureCatalog::used($doc))===2,'nested border included in texture manifest');
$pdo=patchTestDb();$repo=new Projects($pdo);$p=$repo->create(7,1,'Textile laser',$doc);fabricationCheck($repo->find($p['id'],7,1,false)['document']===$doc,'fabrication document persists');
$d=$doc;$d['render']['lightAngle']=110;$updated=$repo->save($p,1,1,'Textile laser',$d,'private','active');fabricationCheck((int)$updated['revision']===2&&$updated['document']===$d,'lighting persisted in new revision');
$d=$doc;$d['variants']['standard'][0]['stroke']['fill']=['type'=>'asset','assetId'=>str_repeat('c',32)];fabricationCheck(Document::assets($d)===[str_repeat('c',32)],'nested private border asset referenced');fabricationReject(fn()=>$repo->create(7,1,'Denied',$d),'private border resource denied');
$im=imagecreatetruecolor(256,128);imagealphablending($im,false);imagesavealpha($im,true);imagefill($im,0,0,imagecolorallocatealpha($im,0,0,0,127));imagefilledrectangle($im,20,20,230,110,imagecolorallocatealpha($im,40,60,40,0));ob_start();imagepng($im);$png=ob_get_clean();imagedestroy($im);
$zipPath=tempnam(sys_get_temp_dir(),'patch-fabrication-');
try{
 file_put_contents($zipPath,Export::bundle($p,'standard',$png,[]));$zip=new ZipArchive();fabricationCheck($zip->open($zipPath)===true,'fabrication ZIP opens');
 fabricationCheck(json_decode($zip->getFromName('project.json'),true)===$doc,'ZIP preserves masks, materials, stitches and lighting');
 $manifest=json_decode($zip->getFromName('resources.json'),true);fabricationCheck(isset($manifest['texture:multicam-original'],$manifest['texture:multicam-black']),'ZIP contains upper and independent border textures');$zip->close();
}finally{unlink($zipPath);}
$im=imagecreatetruecolor(4096,1024);imagesavealpha($im,true);imagefill($im,0,0,0x567342);ob_start();imagepng($im);$large=ob_get_clean();imagedestroy($im);
[$clean,$w,$h]=Export::raster($large);fabricationCheck($w===4096&&$h===1024,'non square high-resolution PNG');fabricationReject(fn()=>Export::paa($large),'Arma CPU budget remains 2048');
$before=ini_get('memory_limit');
try{ini_set('memory_limit','32M');fabricationReject(fn()=>Export::raster($large),'pre-decompression memory guard');}finally{ini_set('memory_limit',$before);}
$im=imagecreatetruecolor(8,8);ob_start();imagepng($im);$small=ob_get_clean();imagedestroy($im);fabricationReject(fn()=>Export::raster($small),'invalid tiny render');
echo "PASS $checks fabrication backend checks\n";
