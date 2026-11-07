<?php

declare(strict_types=1);

namespace App\Services\PatchStudio;

/** Contrat v1. Pas de markup, URL, chemin, CSS ou code dans le document. */
final class Document
{
    public const MAX_BYTES = 1048576;
    public const SHAPES = ['rectangle', 'rounded_rectangle', 'ellipse', 'triangle', 'hexagon', 'octagon', 'shield', 'chevron', 'banner', 'diamond', 'star', 'polygon'];
    public const PATTERNS = ['multicam', 'woodland', 'desert', 'fabric', 'velcro', 'pvc', 'rubber', 'embroidery', 'stripes', 'dots'];
    public const FONTS = ['Barlow Condensed', 'Roboto Condensed', 'Oswald', 'Saira Stencil One', 'Inter', 'JetBrains Mono'];
    public const VARIABLES = ['callsign', 'unit', 'group', 'rank', 'firstname', 'lastname', 'country', 'organization'];
    public const MATERIALS = ['flat','cordura500','cordura1000','multicam','nylon','polyester','velcro','embroidery'];

    public static function validate(array $doc): array
    {
        self::keys($doc, ['version', 'canvas', 'variants', 'fallbacks', 'render']);
        if (($doc['version'] ?? null) !== 1) self::fail('Version de projet non prise en charge.');
        $canvas = $doc['canvas'] ?? null;
        if (!is_array($canvas)) self::fail('Canevas requis.');
        self::keys($canvas, ['width', 'height', 'transparent', 'background']);
        self::number($canvas, 'width', 32, 2048, true);
        self::number($canvas, 'height', 32, 2048, true);
        self::boolean($canvas, 'transparent');
        self::color($canvas['background'] ?? '#000000');
        if (isset($doc['render'])) {
            $r = $doc['render'];
            if (!is_array($r)) self::fail('Réglages de rendu invalides.');
            self::keys($r, ['lightAngle','lightElevation','lightIntensity','relief','contactShadow','quality']);
            self::optionalNumbers($r, ['lightAngle'=>[-360,360], 'lightElevation'=>[10,90], 'lightIntensity'=>[0,1], 'relief'=>[0,2], 'contactShadow'=>[0,1]]);
            if (isset($r['quality'])) self::enum($r['quality'], ['draft','high']);
        }
        if (!is_array($doc['variants'] ?? null) || !isset($doc['variants']['standard'])) self::fail('Composition standard requise.');
        self::keys($doc['variants'], ['standard', 'night']);
        $count = 0;
        foreach ($doc['variants'] as $layers) {
            $ids = [];
            self::layers($layers, $ids, $count, 0);
        }
        if (!is_array($doc['fallbacks'] ?? null)) self::fail('Substitutions requises.');
        self::keys($doc['fallbacks'], self::VARIABLES);
        foreach ($doc['fallbacks'] as $v) self::string($v, 120);
        if (strlen(json_encode($doc, JSON_THROW_ON_ERROR)) > self::MAX_BYTES) self::fail('Projet trop volumineux.');
        return $doc;
    }

    private static function layers(mixed $layers, array &$ids, int &$count, int $depth): void
    {
        if (!is_array($layers) || !array_is_list($layers) || $depth > 5) self::fail('Arbre de calques invalide.');
        foreach ($layers as $l) {
            if (++$count > 160 || !is_array($l)) self::fail('160 objets maximum, variantes comprises.');
            self::keys($l, ['id','type','name','x','y','width','height','rotation','scaleX','scaleY','opacity','visible','locked','fill','stroke','shadow','shape','radius','sides','content','fontFamily','fontAsset','fontSize','fontWeight','italic','textCase','letterSpacing','wordSpacing','lineHeight','align','verticalAlign','curve','assetId','crop','children','mask','material','construction']);
            if (!self::id($l['id'] ?? '') || isset($ids[$l['id']])) self::fail('Identifiant de calque invalide ou dupliqué.');
            $ids[$l['id']] = true;
            self::enum($l['type'] ?? '', ['text', 'shape', 'image', 'group']);
            self::string($l['name'] ?? '', 120);
            foreach (['x','y'] as $k) self::number($l, $k, -8192, 8192);
            foreach (['width','height'] as $k) self::number($l, $k, 1, 4096);
            self::number($l, 'rotation', -3600, 3600);
            foreach (['scaleX','scaleY'] as $k) self::number($l, $k, 0.05, 20);
            self::number($l, 'opacity', 0, 1);
            foreach (['visible','locked'] as $k) self::boolean($l, $k);
            if (isset($l['fill'])) self::fill($l['fill']);
            if (isset($l['stroke'])) {
                $s = $l['stroke'];
                if (!is_array($s)) self::fail('Contour invalide.');
                self::keys($s, ['color','width','opacity','style','position','mode','fill','finish','threadWidth','density','threadAngle','relief']);
                self::color($s['color'] ?? '');
                self::number($s, 'width', 0, 80);
                self::number($s, 'opacity', 0, 1);
                self::enum($s['style'] ?? '', ['solid','double','dashed','stitch']);
                self::enum($s['position'] ?? '', ['center','inside','outside']);
                if (isset($s['mode'])) self::enum($s['mode'], ['color','same','custom']);
                if (isset($s['finish'])) self::enum($s['finish'], ['legacy','embroidered','overlock','heatcut','none']);
                if (isset($s['fill'])) self::fill($s['fill']);
                if (($s['mode'] ?? '') === 'custom' && !isset($s['fill'])) self::fail('Texture de bordure requise.');
                self::optionalNumbers($s, ['threadWidth'=>[0.1,3], 'density'=>[0.15,3], 'threadAngle'=>[-70,70], 'relief'=>[0,2]]);
            }
            if (isset($l['material'])) {
                $m = $l['material'];
                if (!in_array($l['type'], ['shape','text'], true) || !is_array($m)) self::fail('Matière réservée aux formes et textes.');
                self::keys($m, ['type','scale','angle','roughness','relief']);
                self::enum($m['type'] ?? '', self::MATERIALS);
                self::optionalNumbers($m, ['scale'=>[0.25,4], 'angle'=>[-360,360], 'roughness'=>[0,1], 'relief'=>[0,2]]);
            }
            if (isset($l['construction'])) {
                $c = $l['construction'];
                if ($l['type'] !== 'text' || !is_array($c)) self::fail('Fabrication réservée au texte.');
                self::keys($c, ['mode','supportId','depth','sealColor','sealWidth','threadWidth','density','threadAngle']);
                self::enum($c['mode'] ?? '', ['print','embroidery','laser']);
                if (isset($c['supportId']) && !self::id($c['supportId'])) self::fail('Support invalide.');
                if (isset($c['sealColor'])) self::color($c['sealColor']);
                self::optionalNumbers($c, ['depth'=>[0,4], 'sealWidth'=>[0,2], 'threadWidth'=>[0.1,3], 'density'=>[0.15,3], 'threadAngle'=>[-90,90]]);
                if ($c['mode'] === 'laser') {
                    $support = array_filter($layers, static fn ($other) => is_array($other) && ($other['id'] ?? null) === ($c['supportId'] ?? null) && ($other['type'] ?? '') === 'shape');
                    if (count($support) !== 1) self::fail('La découpe laser et son support doivent être dans le même groupe.');
                }
            }
            if (isset($l['shadow'])) {
                $s = $l['shadow'];
                if (!is_array($s)) self::fail('Ombre invalide.');
                self::keys($s, ['color','blur','x','y']);
                self::color($s['color'] ?? '');
                self::number($s,'blur',0,30);
                foreach (['x','y'] as $k) self::number($s,$k,-100,100);
            }
            if ($l['type'] === 'shape') {
                self::enum($l['shape'] ?? '', self::SHAPES);
                self::number($l, 'radius', 0, 1024);
                self::number($l, 'sides', 3, 24, true);
            }
            if ($l['type'] === 'text') {
                self::string($l['content'] ?? null, 2000);
                if (!empty($l['fontAsset'])) {
                    if (!self::assetId($l['fontAsset'])) self::fail('Police invalide.');
                } else self::enum($l['fontFamily'] ?? '', self::FONTS);
                self::number($l, 'fontSize', 4, 512);
                self::enum($l['fontWeight'] ?? 400, [400,500,600,700,800,900]);
                self::boolean($l, 'italic');
                self::enum($l['textCase'] ?? '', ['none','upper','lower']);
                foreach (['letterSpacing','wordSpacing'] as $k) self::number($l,$k,-20,100);
                self::number($l,'lineHeight',0.5,4);
                self::number($l,'curve',-360,360);
                self::enum($l['align'] ?? '', ['left','center','right']);
                self::enum($l['verticalAlign'] ?? '', ['top','middle','bottom']);
            }
            if ($l['type'] === 'image' && !self::assetId($l['assetId'] ?? '')) self::fail('Image invalide.');
            if (isset($l['crop'])) {
                if (!is_array($l['crop'])) self::fail('Recadrage invalide.');
                self::keys($l['crop'],['x','y','width','height']);
                foreach (['x','y'] as $k) self::number($l['crop'],$k,0,1);
                foreach (['width','height'] as $k) self::number($l['crop'],$k,0.01,1);
                if ($l['crop']['x'] + $l['crop']['width'] > 1.001 || $l['crop']['y'] + $l['crop']['height'] > 1.001) self::fail('Recadrage hors image.');
            }
            if ($l['type'] === 'group') {
                if (isset($l['mask'])) self::enum($l['mask'], array_merge(['none'], self::SHAPES));
                self::layers($l['children'] ?? null, $ids, $count, $depth + 1);
            } elseif (isset($l['children'])) self::fail('Seul un groupe contient des enfants.');
        }
    }

    private static function fill(mixed $f): void
    {
        if (!is_array($f)) self::fail('Remplissage invalide.');
        self::keys($f,['type','color','color2','angle','pattern','assetId','textureId','scale','rotation','offsetX','offsetY','repeat','opacity','contrast','brightness']);
        self::enum($f['type'] ?? '', ['solid','gradient','pattern','asset','texture','none']);
        foreach (['color','color2'] as $k) if (isset($f[$k])) self::color($f[$k]);
        if ($f['type'] === 'solid' || $f['type'] === 'gradient') self::color($f['color'] ?? '');
        if ($f['type'] === 'gradient') self::color($f['color2'] ?? '');
        if ($f['type'] === 'pattern') self::enum($f['pattern'] ?? '', self::PATTERNS);
        if ($f['type'] === 'texture' && TextureCatalog::find($f['textureId'] ?? null) === null) self::fail('Texture inconnue.');
        if ($f['type'] === 'asset' && !self::assetId($f['assetId'] ?? '')) self::fail('Ressource de motif invalide.');
        foreach (['angle','rotation'] as $k) if (isset($f[$k])) self::number($f,$k,-3600,3600);
        if (isset($f['scale'])) self::number($f,'scale',0.05,20);
        foreach (['offsetX','offsetY'] as $k) if (isset($f[$k])) self::number($f,$k,-4096,4096);
        if (isset($f['opacity'])) self::number($f,'opacity',0,1);
        foreach (['brightness','contrast'] as $k) if (isset($f[$k])) self::number($f,$k,0,3);
        if (isset($f['repeat'])) self::boolean($f,'repeat');
    }

    public static function assets(array $doc): array
    {
        $out = [];
        $walk = static function (array $v) use (&$walk, &$out): void {
            foreach ($v as $k => $x) {
                if (in_array($k, ['assetId','fontAsset'], true) && is_string($x) && $x !== '') $out[$x] = true;
                elseif (is_array($x)) $walk($x);
            }
        };
        $walk($doc);
        return array_keys($out);
    }

    public static function assetId(mixed $id): bool { return is_string($id) && preg_match('/^[a-f0-9]{32}$/D', $id) === 1; }
    public static function id(mixed $id): bool { return is_string($id) && preg_match('/^[a-zA-Z0-9_-]{1,64}$/D', $id) === 1; }
    private static function optionalNumbers(array $a, array $ranges): void
    {
        foreach ($ranges as $key => [$min, $max]) if (array_key_exists($key, $a)) self::number($a, $key, $min, $max);
    }
    private static function keys(array $a, array $allowed): void { if (array_diff(array_keys($a), $allowed)) self::fail('Propriété inconnue.'); }
    private static function enum(mixed $v, array $values): void { if (!in_array($v, $values, true)) self::fail('Valeur non autorisée.'); }
    private static function color(mixed $c): void { if (!is_string($c) || !preg_match('/^#[0-9a-fA-F]{6}([0-9a-fA-F]{2})?$/D', $c)) self::fail('Couleur HEX invalide.'); }
    private static function string(mixed $s, int $max): void { if (!is_string($s) || mb_strlen($s) > $max || preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', $s)) self::fail('Texte invalide.'); }
    private static function number(array $a, string $k, float $min, float $max, bool $int = false): void
    {
        $v = $a[$k] ?? null;
        if ((!is_int($v) && !is_float($v)) || !is_finite((float) $v) || $v < $min || $v > $max || ($int && !is_int($v))) self::fail('Nombre invalide : ' . $k);
    }
    private static function boolean(array $a, string $k): void { if (!is_bool($a[$k] ?? null)) self::fail('Booléen requis : ' . $k); }
    private static function fail(string $message): never { throw new \InvalidArgumentException($message); }
}
