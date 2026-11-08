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
    /**
     * Carte _smdi : vert = reflet, bleu = brillance, rouge inutilisé. Tissu mat, reflet léger sur les parties claires.
     * 256 px au plus : un écusson n'a pas besoin de plus, et le calcul reste borné.
     */
    public static function smdi(string $png): string
    {
        $src=imagecreatefromstring($png); $w=imagesx($src); $h=imagesy($src); $k=1;
        while (max($w,$h)/$k>256) $k*=2;
        $nw=max(4,(int)($w/$k)); $nh=max(4,(int)($h/$k));
        $dst=imagecreatetruecolor($nw,$nh);
        try {
            imagealphablending($dst,false); imagesavealpha($dst,true);
            imagefill($dst,0,0,imagecolorallocatealpha($dst,0,0,0,127));
            imagecopyresampled($dst,$src,0,0,0,0,$nw,$nh,$w,$h);
            for ($y=0;$y<$nh;$y++) for ($x=0;$x<$nw;$x++) {
                $c=imagecolorat($dst,$x,$y);
                $a=1-(($c>>24)&127)/127;
                $lum=(0.299*(($c>>16)&255)+0.587*(($c>>8)&255)+0.114*($c&255))/255;
                imagesetpixel($dst,$x,$y,(255<<16)|((int)round(($a*(16+48*$lum)))<<8)|40);
            }
            return PaaEncoder::encode($dst);
        } finally { imagedestroy($src);imagedestroy($dst); }
    }
    /** Matériau de l'écusson : même gabarit Super que mod/.../items/data/items.rvmat, sans relief ; thermique comme \a3\data_f\default.rvmat. */
    public static function rvmat(string $prefix): string
    {
        $uv='uvSource="tex"; class uvTransform { aside[]={1,0,0}; up[]={0,1,0}; dir[]={0,0,0}; pos[]={0,0,0}; };';
        return "ambient[]={1,1,1,1};\ndiffuse[]={1,1,1,1};\nforcedDiffuse[]={0,0,0,0};\nemmisive[]={0,0,0,1};\nspecular[]={0.2,0.2,0.2,1};\nspecularPower=20;\n"
            . "PixelShaderID=\"Super\";\nVertexShaderID=\"Super\";\n"
            . "class Stage1 { texture=\"#(rgb,8,8,3)color(0.5,0.5,1,1,NOHQ)\"; $uv };\n"
            . "class Stage2 { texture=\"#(argb,8,8,3)color(0.5,0.5,0.5,1,DT)\"; $uv };\n"
            . "class Stage3 { texture=\"#(argb,8,8,3)color(0,0,0,0,MC)\"; $uv };\n"
            . "class Stage4 { texture=\"#(argb,8,8,3)color(1,1,1,1,AS)\"; $uv };\n"
            . "class Stage5 { texture=\"\\$prefix\\patch_smdi.paa\"; $uv };\n"
            . "class Stage6 { texture=\"#(ai,64,64,1)fresnel(1.3,0.3)\"; uvSource=\"none\"; };\n"
            . "class Stage7 { texture=\"a3\\data_f\\env_land_co.paa\"; useWorldEnvMap=\"true\"; $uv };\n"
            . "class StageTI { texture=\"a3\\data_f\\default_vehicle_ti_ca.paa\"; };\n";
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
            . "class CfgUnitInsignia { class $class { displayName=\"$label\"; author=\"ATHENA\"; texture=\"\\$prefix\\patch.paa\"; material=\"\\$prefix\\patch.rvmat\"; textureVehicle=\"\"; }; };\n"
            . "class CfgAthenaPatches { class $class { projectId=\"{$p['id']}\"; variant=\"$variant\"; hash=\"$hash\"; revision={$p['revision']}; texture=\"\\$prefix\\patch.paa\"; }; };\n";
        // BIS_fnc_setUnitInsignia applique « material » puis « texture » : le matériau suit l'insigne sans rien changer côté mod.
        $smdi=self::smdi($png); $rvmat=self::rvmat($prefix);
        $files=[['name'=>'config.cpp','data'=>$cfg],['name'=>'patch.paa','data'=>$paa],['name'=>'patch.rvmat','data'=>$rvmat],['name'=>'patch_smdi.paa','data'=>$smdi]];
        return ['paa'=>$paa,'smdi'=>$smdi,'rvmat'=>$rvmat,'config'=>$cfg,'prefix'=>$prefix,'class'=>$class,'pbo'=>AtakTranslationModBuilder::buildPbo($prefix,$files,0)];
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
            // Texture, matériau et carte _smdi livrés. Le moteur n'a pas de canal de réflexion IR : aucune réponse IR propre.
            'night'=>['ir_material'=>false,'rvmat'=>true,'smdi'=>true,'baked_adjustment'=>$variant==='night'?$nv:null]];
        $files=[
            ['name'=>'patch.png','data'=>$png],['name'=>'patch.paa','data'=>$a['paa']],
            ['name'=>'patch.rvmat','data'=>$a['rvmat']],['name'=>'patch_smdi.paa','data'=>$a['smdi']],
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
- Livré : la texture, un matériau (patch.rvmat) et une carte de reflets (patch_smdi.paa) pour un écusson de tissu mat. Pour la version nuit, le gain et le contraste choisis dans le Studio sont déjà appliqués aux pixels du fichier.
- Le matériau s'applique aux insignes posés par BIS_fnc_setUnitInsignia (setUnitInsignia, arsenal, mission). Le patch au dos du téléphone ATAK n'utilise que la texture.
- Non livré : aucune réflexion infrarouge propre, Arma 3 n'a pas de canal pour cela. Le rendu en JVN dépend de la luminosité de la texture, du matériau et de l'éclairage de la scène.
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
