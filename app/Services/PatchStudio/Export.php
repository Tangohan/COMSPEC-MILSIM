<?php

declare(strict_types=1);

namespace App\Services\PatchStudio;

use App\Support\AtakTranslationModBuilder;
use App\Support\PaaEncoder;

final class Export
{
    public const MAX_PNG_BYTES = 33554432;
    public static function raster(string $png): array
    {
        if (strlen($png)>self::MAX_PNG_BYTES || substr($png,0,8)!=="\x89PNG\r\n\x1a\n") throw new \InvalidArgumentException('Rendu PNG requis (32 Mio maximum).');
        $info=@getimagesizefromstring($png);
        if (!$info || $info[0]<32 || $info[1]<32 || $info[0]>4096 || $info[1]>4096) throw new \InvalidArgumentException('Rendu : 32 à 4096 pixels par côté.');
        // Refuse before decompression if the worker cannot hold the pixel buffers and PNG copies.
        $limit=trim((string)ini_get('memory_limit'));
        if ($limit!=='' && $limit!=='-1') {
            $multiplier=match(strtolower(substr($limit,-1))) {'g'=>1073741824,'m'=>1048576,'k'=>1024,default=>1};
            $available=(int)$limit*$multiplier-memory_get_usage(true);
            if ($info[0]*$info[1]*8+strlen($png)*2+16777216>$available) throw new \InvalidArgumentException('Mémoire insuffisante pour cette résolution. Réduisez la taille ou configurez PHP memory_limit à 256M minimum (512M conseillé) pour le PNG 4K.');
        }
        $clean=Assets::validate($png,false,16777216,self::MAX_PNG_BYTES)['bytes'];
        return [$clean,$info[0],$info[1]];
    }
    public static function thumbnail(string $png): string
    {
        $src=imagecreatefromstring($png); $w=imagesx($src); $h=imagesy($src); $k=min(1,256/max($w,$h));
        $dst=imagecreatetruecolor(max(1,(int)round($w*$k)),max(1,(int)round($h*$k)));
        try {
            imagealphablending($dst,false); imagesavealpha($dst,true);
            imagefill($dst,0,0,imagecolorallocatealpha($dst,0,0,0,127));
            imagecopyresampled($dst,$src,0,0,0,0,imagesx($dst),imagesy($dst),$w,$h);
            ob_start();imagepng($dst,null,6);return (string)ob_get_clean();
        } finally { imagedestroy($src);imagedestroy($dst); }
    }
    public static function paa(string $png): string
    {
        $info=@getimagesizefromstring($png);
        if ($info && ($info[0]>2048 || $info[1]>2048)) throw new \InvalidArgumentException('Les exports Arma 3 sont limités à 2048 pixels par côté.');
        [, $w, $h]=self::raster($png);
        if (!PaaEncoder::isPowerOfTwo($w) || !PaaEncoder::isPowerOfTwo($h)) throw new \InvalidArgumentException('PAA : côtés en puissances de deux requis. Choisissez un format adapté, sans déformer le patch.');
        $im=imagecreatefromstring($png);
        try { return PaaEncoder::encode($im); } finally { imagedestroy($im); }
    }
    public static function className(array $p,string $variant,string $hash): string
    {
        return 'athena_' . $p['id'] . '_' . $variant . '_' . substr($hash,0,16);
    }
    /** Même structure que tools/pbo_patch.py ; réemploi du générateur PHP existant. */
    public static function addon(array $p,string $variant,string $png): array
    {
        $paa=self::paa($png); $hash=hash('sha256',$paa);
        $class=self::className($p,$variant,$hash); $prefix=$class;
        $label=str_replace(['"',"\r","\n"],['""',' ',' '],$p['name']);
        $cfg="class CfgPatches { class $class { name=\"ATHENA Patch\"; requiredVersion=2.14; requiredAddons[]={\"A3_Characters_F\"}; units[]={}; weapons[]={}; }; };\n"
            . "class CfgUnitInsignia { class $class { displayName=\"$label\"; author=\"ATHENA\"; texture=\"\\$prefix\\patch.paa\"; textureVehicle=\"\"; }; };\n"
            . "class CfgAthenaPatches { class $class { projectId=\"{$p['id']}\"; variant=\"$variant\"; hash=\"$hash\"; revision={$p['revision']}; texture=\"\\$prefix\\patch.paa\"; }; };\n";
        $files=[['name'=>'config.cpp','data'=>$cfg],['name'=>'patch.paa','data'=>$paa]];
        return ['paa'=>$paa,'config'=>$cfg,'prefix'=>$prefix,'class'=>$class,'pbo'=>AtakTranslationModBuilder::buildPbo($prefix,$files,0)];
    }
    /**
     * Réglages de vision nocturne reçus du navigateur : gain et contraste déjà appliqués aux pixels du PNG.
     * Le serveur ne retouche rien ; il borne les valeurs et les consigne dans le pack.
     */
    public static function nightAdjustment(mixed $nv): ?array
    {
        if (!is_array($nv)) return null;
        $g=$nv['gain']??1; $c=$nv['contrast']??1;
        if (!is_numeric($g) || !is_numeric($c)) throw new \InvalidArgumentException('Réglages de vision nocturne invalides.');
        $g=round(max(0.5,min(3.0,(float)$g)),2); $c=round(max(0.5,min(2.0,(float)$c)),2);
        return ($g===1.0 && $c===1.0) ? null : ['gain'=>$g,'contrast'=>$c];
    }
    public static function bundle(array $p,string $variant,string $png,array $assets,?array $nv=null): string
    {
        $a=self::addon($p,$variant,$png); $folder='@ATHENA_' . $p['id'] . '/';
        $meta=['project'=>$p['id'],'revision'=>(int)$p['revision'],'variant'=>$variant,'class'=>$a['class'],'paa_sha256'=>hash('sha256',$a['paa']),'png_sha256'=>hash('sha256',$png),'night_mode'=>$variant==='night'?'texture_adjustment':'none',
            // Seule la texture est livrée : aucun matériau (.rvmat), aucune carte _smdi, aucune réflexion IR propre.
            'night'=>['ir_material'=>false,'rvmat'=>false,'smdi'=>false,'baked_adjustment'=>$variant==='night'?$nv:null]];
        $files=[
            ['name'=>'patch.png','data'=>$png],['name'=>'patch.paa','data'=>$a['paa']],
            ['name'=>'project.json','data'=>json_encode($p['document'],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR)],
            ['name'=>'metadata.json','data'=>json_encode($meta,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR)],
            ['name'=>'source/config.cpp','data'=>$a['config']],['name'=>'source/$PBOPREFIX$','data'=>$a['prefix']],
            ['name'=>$folder.'addons/'.$a['prefix'].'.pbo','data'=>$a['pbo']],
            ['name'=>$folder.'mod.cpp','data'=>'name="ATHENA Patch"; author="ATHENA";'],
            ['name'=>'README.txt','data'=>"ATHENA PATCH STUDIO

INSTALLER
1. Copiez le dossier @ATHENA_* dans le dossier d'Arma 3 ou de votre serveur.
2. Chargez-le dans le Launcher AVANT de lancer Arma 3, sur chaque client et sur le serveur.
3. Tous les joueurs doivent avoir le même addon : un téléchargement en cours de partie ne monte pas un PBO.
4. L'addon est non signé : signez-le et distribuez-le avec votre procédure serveur.
Alternative mission : placez le PAA dans la mission et déclarez CfgUnitInsignia avec le chemin de la mission.

JUMELLES DE VISION NOCTURNE (JVN)
- Livré : une texture. Pour la version nuit, le gain et le contraste choisis dans le Studio sont déjà appliqués aux pixels du fichier.
- Non livré : aucun matériau (.rvmat), aucune carte _smdi, aucune réflexion infrarouge propre. Le rendu en JVN dépend donc de la luminosité de la texture et de l'éclairage de la scène.
- Les aperçus verts du Studio simulent une image de JVN : ils ne remplacent pas un essai en jeu.

Le manifeste resources.json associe les identifiants du projet aux fichiers et licences.
"],
        ];
        $manifest=[];
        foreach ($assets as $asset) {
            $ext=match($asset['mime']) {'font/ttf'=>'ttf','font/otf'=>'otf','image/svg+xml'=>'svg',default=>'png'};
            $name='assets/'.$asset['id'].'.'.$ext;
            $files[]=['name'=>$name,'data'=>$asset['data']];
            $manifest[$asset['id']]=['file'=>$name,'mime'=>$asset['mime'],'sha256'=>$asset['hash'],'license'=>$asset['license_note']];
        }
        $textures=[];
        foreach (TextureCatalog::used($p['document']) as $id) {
            $texture=TextureCatalog::read($id);
            $name='textures/'.$texture['file'];
            $files[]=['name'=>$name,'data'=>$texture['data']];
            unset($texture['data']);
            $manifest['texture:'.$id]=['file'=>$name,'mime'=>'image/jpeg','sha256'=>$texture['sha256'],'license'=>$texture['rights'],'source'=>$texture['source']];
            $textures[]=$texture;
        }
        $files[]=['name'=>'textures/catalog.json','data'=>json_encode(['version'=>1,'textures'=>$textures],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR)];
        $files[]=['name'=>'resources.json','data'=>json_encode($manifest,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR)];
        return AtakTranslationModBuilder::buildZip($files,315532800);
    }
}
