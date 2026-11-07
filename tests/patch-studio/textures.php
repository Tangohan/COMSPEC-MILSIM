<?php
require __DIR__.'/bootstrap.php';
use App\Services\PatchStudio\{Document,Export,TextureCatalog};
$checks=0;
function verifyTexture(bool $ok,string $label): void { global $checks;$checks++;if(!$ok)throw new RuntimeException($label); }
$catalog=TextureCatalog::all();verifyTexture(count($catalog)===5,'five original image variants');
$doc=json_decode(file_get_contents(__DIR__.'/project.json'),true);
foreach(['original','arid','tropic','alpine','black'] as $variant){
 $id='multicam-'.$variant;$t=TextureCatalog::read($id);$size=getimagesizefromstring($t['data']);
 verifyTexture($size['mime']==='image/jpeg','real JPEG '.$variant);
 verifyTexture($size[0]===$t['width']&&$size[1]===$t['height'],'original proportions '.$variant);
 verifyTexture(hash('sha256',$t['data'])===$t['sha256'],'source integrity '.$variant);
 verifyTexture(str_starts_with($t['source'],'https://multicampattern.com/'),'source attribution '.$variant);
 verifyTexture(str_contains($t['rights'],'tous droits réservés'),'no invented free license '.$variant);
 $d=$doc;$d['variants']['standard'][1]['fill']=['type'=>'texture','textureId'=>$id,'scale'=>0.5,'rotation'=>30,'offsetX'=>12,'offsetY'=>24,'repeat'=>true,'opacity'=>0.8,'brightness'=>1.1,'contrast'=>1.2];
 verifyTexture(Document::validate($d)===$d,'text texture schema roundtrip '.$variant);
}
foreach(['../../etc/passwd','https://example.com/a.jpg','multicam-missing'] as $id){
 $d=$doc;$d['variants']['standard'][0]['fill']=['type'=>'texture','textureId'=>$id];
 try{Document::validate($d);throw new RuntimeException('invalid texture accepted');}catch(InvalidArgumentException){verifyTexture(true,'reject unknown texture');}
}
$doc['variants']['standard'][0]['fill']=['type'=>'texture','textureId'=>'multicam-original'];
$doc['variants']['standard'][1]['fill']=['type'=>'texture','textureId'=>'multicam-black'];
$doc['variants']['night']=$doc['variants']['standard'];$doc['variants']['night'][0]['fill']=['type'=>'texture','textureId'=>'multicam-tropic'];
verifyTexture(count(TextureCatalog::used($doc))===3,'dedupe textures across compositions');
$im=imagecreatetruecolor(256,128);ob_start();imagepng($im);$png=ob_get_clean();imagedestroy($im);
$p=['id'=>str_repeat('a',32),'name'=>'Textures','revision'=>1,'document'=>$doc];
$path=tempnam(sys_get_temp_dir(),'patch-texture-test-');
try{
 file_put_contents($path,Export::bundle($p,'standard',$png,[]));$zip=new ZipArchive();verifyTexture($zip->open($path)===true,'textured project ZIP');
 $manifest=json_decode($zip->getFromName('resources.json'),true);$bundled=json_decode($zip->getFromName('textures/catalog.json'),true);
 verifyTexture(count($bundled['textures'])===3,'only required textures included');
 foreach(TextureCatalog::used($doc) as $id){$entry=$manifest['texture:'.$id];verifyTexture($zip->getFromName($entry['file'])===TextureCatalog::read($id)['data'],'ZIP exact JPEG '.$id);verifyTexture($entry['sha256']===hash('sha256',$zip->getFromName($entry['file'])),'ZIP integrity '.$id);}
 verifyTexture(json_decode($zip->getFromName('project.json'),true)===$doc,'editable texture settings preserved');$zip->close();
}finally{if(is_file($path))unlink($path);}
echo "PASS $checks texture checks\n";
