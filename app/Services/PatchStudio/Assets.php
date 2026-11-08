<?php

declare(strict_types=1);

namespace App\Services\PatchStudio;

/** Validation avant décodage ; stockage privé exclusivement, noms calculés côté serveur. */
final class Assets
{
    public const MAX_BYTES = 8388608;
    public function __construct(private ?string $root = null) { $this->root ??= base_path('storage/patch-studio'); }

    public function put(string $bytes): string
    {
        $hash = hash('sha256', $bytes);
        $path = $this->path($hash);
        if (!is_dir(dirname($path)) && !mkdir(dirname($path), 0700, true) && !is_dir(dirname($path))) throw new \RuntimeException('Stockage indisponible.');
        if (!is_file($path)) {
            $tmp = tempnam(dirname($path), '.ps-');
            if ($tmp === false) throw new \RuntimeException('Stockage indisponible.');
            try {
                if (file_put_contents($tmp, $bytes, LOCK_EX) !== strlen($bytes) || !rename($tmp, $path)) throw new \RuntimeException('Écriture impossible.');
                chmod($path, 0600);
            } finally { if (is_file($tmp)) unlink($tmp); }
        }
        return $hash;
    }
    public function path(string $hash): string
    {
        if (preg_match('/^[a-f0-9]{64}$/D', $hash) !== 1) throw new \InvalidArgumentException('Hash invalide.');
        return $this->root . '/' . substr($hash, 0, 2) . '/' . $hash;
    }
    public function read(string $hash): string
    {
        $bytes = file_get_contents($this->path($hash));
        if (!is_string($bytes) || !hash_equals($hash, hash('sha256', $bytes))) throw new \RuntimeException('Ressource absente ou corrompue.');
        return $bytes;
    }

    /** @return array{bytes:string,mime:string} */
    public static function validate(string $bytes, bool $fontLicense = false, int $maxPixels = 8388608, int $maxBytes = self::MAX_BYTES): array
    {
        if ($bytes === '' || strlen($bytes) > $maxBytes) throw new \InvalidArgumentException('Fichier vide ou trop volumineux.');
        $magic = substr($bytes, 0, 4);
        if ($magic === "\x00\x01\x00\x00" || $magic === 'OTTO') {
            if (!$fontLicense) throw new \InvalidArgumentException('Confirmez votre droit d’utiliser et incorporer cette police.');
            self::font($bytes);
            return ['bytes' => $bytes, 'mime' => $magic === 'OTTO' ? 'font/otf' : 'font/ttf'];
        }
        if (preg_match('/^\s*(?:<\?xml[^>]*>\s*)?<svg\b/i', $bytes)) return ['bytes'=>self::svg($bytes),'mime'=>'image/svg+xml'];
        $info = @getimagesizefromstring($bytes);
        if (!$info || !in_array($info['mime'], ['image/png','image/jpeg','image/webp'], true)) throw new \InvalidArgumentException('PNG, JPEG, WebP, SVG ou TTF/OTF attendus.');
        if ($info[0] > 4096 || $info[1] > 4096 || $info[0] * $info[1] > $maxPixels) throw new \InvalidArgumentException('Image trop grande pour ce traitement.');
        if (!extension_loaded('gd')) throw new \RuntimeException('Extension PHP GD requise.');
        $im = @imagecreatefromstring($bytes);
        if (!$im) throw new \InvalidArgumentException('Image illisible.');
        try {
            imagepalettetotruecolor($im);
            imagesavealpha($im, true);
            ob_start();
            imagepng($im, null, 6);
            $png = (string) ob_get_clean();
        } finally { imagedestroy($im); }
        return ['bytes'=>$png,'mime'=>'image/png'];
    }

    private static function font(string $b): void
    {
        if (strlen($b) < 12) throw new \InvalidArgumentException('Police invalide.');
        $n = unpack('n', substr($b, 4, 2))[1];
        if ($n < 1 || $n > 128 || 12 + 16 * $n > strlen($b)) throw new \InvalidArgumentException('Tables de police invalides.');
        $tags = [];
        for ($i=0;$i<$n;$i++) {
            $t = substr($b, 12 + 16 * $i, 16);
            $p = unpack('Noffset/Nlength', substr($t, 8));
            if ($p['offset'] < 12 + 16 * $n || $p['offset'] + $p['length'] > strlen($b)) throw new \InvalidArgumentException('Table de police hors limites.');
            $tags[] = substr($t,0,4);
        }
        // Pas de glyphes SVG actifs ; le navigateur vérifie aussi la police via son sanitizer OTS.
        if (in_array('SVG ', $tags, true) || array_diff(['cmap','head','name','maxp'], $tags)) throw new \InvalidArgumentException('Police non prise en charge.');
    }

    public static function svg(string $bytes): string
    {
        if (strlen($bytes) > 524288 || preg_match('/<!DOCTYPE|<!ENTITY/i', $bytes)) throw new \InvalidArgumentException('SVG trop grand ou déclaration interdite.');
        $dom = new \DOMDocument();
        $before = libxml_use_internal_errors(true);
        try { $ok = $dom->loadXML($bytes, LIBXML_NONET); } finally { libxml_clear_errors(); libxml_use_internal_errors($before); }
        if (!$ok || $dom->documentElement?->localName !== 'svg') throw new \InvalidArgumentException('SVG invalide.');
        $tags = ['svg','g','path','rect','circle','ellipse','polygon','polyline','line','defs','linearGradient','radialGradient','stop','clipPath'];
        $attrs = ['viewBox','width','height','x','y','x1','x2','y1','y2','cx','cy','r','rx','ry','d','points','fill','stroke','stroke-width','stroke-linecap','stroke-linejoin','stroke-dasharray','fill-rule','clip-rule','opacity','fill-opacity','stroke-opacity','transform','id','offset','stop-color','stop-opacity','gradientUnits','gradientTransform','clip-path'];
        foreach ((new \DOMXPath($dom))->query('//processing-instruction()') as $pi) $pi->parentNode?->removeChild($pi);
        $nodes = iterator_to_array($dom->getElementsByTagName('*'));
        if (count($nodes)>2000) throw new \InvalidArgumentException('SVG limité à 2000 éléments.');
        foreach ($nodes as $el) {
            if (!in_array($el->localName,$tags,true) || ($el->namespaceURI !== null && $el->namespaceURI !== 'http://www.w3.org/2000/svg')) {
                if ($el === $dom->documentElement) throw new \InvalidArgumentException('Namespace SVG invalide.');
                $el->parentNode?->removeChild($el); continue;
            }
            foreach (iterator_to_array($el->attributes) as $a) {
                $v = $a->value;
                if (!in_array($a->name,$attrs,true) || strlen($v)>65536 || preg_match('/[<>]|(?:https?|data|javascript):|\/\/|[\\\\]|@|expression/i',$v)
                    || (stripos($v,'url')!==false && !preg_match('/^url\(#[a-zA-Z0-9_-]+\)$/D',$v))) $el->removeAttributeNode($a);
            }
        }
        $dom->documentElement->setAttribute('xmlns','http://www.w3.org/2000/svg');
        return (string) $dom->saveXML($dom->documentElement);
    }
}
