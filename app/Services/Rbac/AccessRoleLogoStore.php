<?php

declare(strict_types=1);

namespace App\Services\Rbac;

use App\Services\Sse\SseIdImageService;
use App\Services\Sse\SseServiceEmblems;

/**
 * Logo téléversé d'un niveau d'accès : fichier rangé HORS du dossier public (storage/private/access-role-logos), servi
 * seulement par la route contrôlée de la page Accès (communauté de la session, nom de fichier préfixé par la communauté).
 *
 * Contrôles : 1 Mo au plus ; vrai type lu dans le CONTENU (jamais le nom ni le type annoncé) ; PNG, JPEG ou WebP réencodés par
 * GD (aucune métadonnée gardée, EXIF compris ; plus grand côté ramené à 512 px ; même traitement que les emblèmes de service) ;
 * SVG accepté seulement s'il passe la liste blanche de sanitizeSvg() (aucun script, aucun lien, aucune ressource externe,
 * aucune entité) et réécrit à partir de cette liste. Le logo est un choix de la communauté (case de confirmation à cocher).
 */
final class AccessRoleLogoStore
{
    public const DIR = 'storage/private/access-role-logos';
    public const MAX_BYTES = 1048576;

    public const CONFIRM_LABEL = 'Je confirme que ce logo appartient à ma communauté ou est une création originale.';

    private const NAME = '/^([0-9]{1,10})_[a-f0-9]{16}\.(png|jpg|svg)$/';

    private const SVG_ELEMENTS = ['svg', 'g', 'path', 'circle', 'ellipse', 'rect', 'line', 'polyline', 'polygon', 'defs', 'lineargradient', 'radialgradient', 'stop', 'title', 'desc', 'clippath', 'text', 'tspan'];

    /** Éléments actifs ou externes : leur présence fait refuser le fichier. */
    private const SVG_FORBIDDEN = ['script', 'foreignobject', 'image', 'use', 'iframe', 'embed', 'object', 'a', 'style', 'animate', 'animatemotion',
        'animatetransform', 'animatecolor', 'set', 'feimage', 'handler', 'listener', 'link', 'meta', 'base'];

    private const SVG_ATTRIBUTES = [
        'xmlns', 'viewbox', 'width', 'height', 'x', 'y', 'x1', 'x2', 'y1', 'y2', 'cx', 'cy', 'r', 'rx', 'ry', 'd', 'points', 'fill', 'fill-opacity',
        'fill-rule', 'stroke', 'stroke-width', 'stroke-linecap', 'stroke-linejoin', 'stroke-opacity', 'stroke-dasharray', 'stroke-miterlimit', 'opacity',
        'transform', 'id', 'offset', 'stop-color', 'stop-opacity', 'gradientunits', 'gradienttransform', 'clip-path', 'clip-rule', 'preserveaspectratio',
        'font-size', 'font-family', 'font-weight', 'text-anchor', 'dominant-baseline', 'letter-spacing', 'fx', 'fy', 'spreadmethod', 'version',
    ];

    private string $root;

    public function __construct(?string $root = null)
    {
        $this->root = rtrim($root ?? base_path(self::DIR), '/\\');
    }

    /**
     * Range un logo pour un niveau de la communauté.
     *
     * @return array{ok: true, file: string, mime: string}|array{ok: false, error: string}
     */
    public function store(int $tenantId, string $sourceFile): array
    {
        if ($tenantId < 1) {
            return self::fail('Action non autorisée.');
        }
        $size = @filesize($sourceFile);
        if ($size === false || $size <= 0) {
            return self::fail('Aucun fichier reçu : choisissez une image.');
        }
        if ($size > self::MAX_BYTES) {
            return self::fail('Image refusée : 1 Mo au plus.');
        }
        $bytes = (string) @file_get_contents($sourceFile);
        $raster = SseIdImageService::sniff($bytes);
        if ($raster !== null) {
            $made = SseServiceEmblems::process($bytes, $raster);
            if (is_string($made)) {
                return self::fail($made);
            }
            $data = $made['data'];
            $ext = $made['format'];
            $mime = $ext === 'png' ? 'image/png' : 'image/jpeg';
        } elseif (self::looksLikeSvg($bytes)) {
            $clean = self::sanitizeSvg($bytes);
            if ($clean === null) {
                return self::fail('Logo SVG refusé : il contient des éléments non autorisés (script, lien, image externe, style). Envoyez un SVG simple ou une image PNG, JPEG ou WebP.');
            }
            $data = $clean;
            $ext = 'svg';
            $mime = 'image/svg+xml';
        } else {
            return self::fail('Image refusée : envoyez un fichier PNG, JPEG, WebP ou SVG.');
        }
        if (!is_dir($this->root) && !@mkdir($this->root, 0750, true) && !is_dir($this->root)) {
            return self::fail('Enregistrement du logo impossible.');
        }
        $name = $tenantId . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
        if (@file_put_contents($this->root . DIRECTORY_SEPARATOR . $name, $data) === false) {
            return self::fail('Enregistrement du logo impossible.');
        }

        return ['ok' => true, 'file' => $name, 'mime' => $mime];
    }

    /**
     * Fichier à servir : nom au format attendu, préfixé par la communauté de la session, présent dans le dossier privé.
     *
     * @return array{path: string, mime: string}|null
     */
    public function resolve(int $tenantId, string $file): ?array
    {
        if (preg_match(self::NAME, $file, $m) !== 1 || (int) $m[1] !== $tenantId || $tenantId < 1) {
            return null;
        }
        $abs = $this->root . DIRECTORY_SEPARATOR . $file;
        if (!is_file($abs)) {
            return null;
        }
        $mime = match ($m[2]) {
            'png' => 'image/png',
            'jpg' => 'image/jpeg',
            default => 'image/svg+xml',
        };
        if ($m[2] !== 'svg' && SseIdImageService::sniff((string) @file_get_contents($abs, false, null, 0, 64)) !== $mime) {
            return null;
        }

        return ['path' => $abs, 'mime' => $mime];
    }

    public function delete(?string $file): void
    {
        if ($file === null || preg_match(self::NAME, $file) !== 1) {
            return;
        }
        $abs = $this->root . DIRECTORY_SEPARATOR . $file;
        if (is_file($abs)) {
            @unlink($abs);
        }
    }

    public static function looksLikeSvg(string $bytes): bool
    {
        $head = ltrim(substr($bytes, 0, 2048), "\xEF\xBB\xBF \t\r\n");

        return preg_match('/^(?:<\?xml[^>]*>\s*)?(?:<!--.*?-->\s*)*<svg[\s>]/is', $head) === 1;
    }

    /**
     * SVG réécrit depuis une liste blanche d'éléments et d'attributs ; null si le fichier contient quoi que ce soit d'actif ou
     * d'externe (script, foreignObject, image, use, style, animation, gestionnaire on*, lien href, url() externe, entité).
     */
    public static function sanitizeSvg(string $bytes): ?string
    {
        if (preg_match('/<!DOCTYPE|<!ENTITY/i', $bytes) === 1 || !class_exists(\DOMDocument::class)) {
            return null;
        }
        $doc = new \DOMDocument();
        $prev = libxml_use_internal_errors(true);
        $ok = $doc->loadXML($bytes, LIBXML_NONET | LIBXML_NOBLANKS);
        libxml_clear_errors();
        libxml_use_internal_errors($prev);
        $root = $ok ? $doc->documentElement : null;
        if ($root === null || strtolower($root->localName) !== 'svg') {
            return null;
        }
        $out = new \DOMDocument('1.0', 'UTF-8');
        $copy = self::copySvgNode($root, $out);
        if (!$copy instanceof \DOMElement) {
            return null;
        }
        $out->appendChild($copy);
        $xml = $out->saveXML($out->documentElement);

        return is_string($xml) && $xml !== '' ? $xml : null;
    }

    /**
     * Copie d'un élément : un élément ou un attribut actif fait refuser tout le fichier (null) ; un élément ou un attribut
     * simplement inconnu (métadonnées d'un éditeur, par exemple) est laissé de côté.
     *
     * @return \DOMElement|false|null false = élément inconnu sans danger, ignoré
     */
    private static function copySvgNode(\DOMElement $node, \DOMDocument $out): \DOMElement|false|null
    {
        $tag = strtolower($node->localName);
        if (in_array($tag, self::SVG_FORBIDDEN, true)) {
            return null;
        }
        if (!in_array($tag, self::SVG_ELEMENTS, true) || ($node->namespaceURI !== null && $node->namespaceURI !== 'http://www.w3.org/2000/svg')) {
            return $node->getElementsByTagName('*')->length > 0 && self::containsForbidden($node) ? null : false;
        }
        $el = $out->createElementNS('http://www.w3.org/2000/svg', $node->localName);
        foreach (iterator_to_array($node->attributes ?? []) as $attr) {
            /** @var \DOMAttr $attr */
            $name = strtolower($attr->localName);
            $value = (string) $attr->value;
            if (str_starts_with($name, 'on') || in_array($name, ['href', 'src', 'style'], true)) {
                return null;
            }
            // Seules les références internes (#id) sont admises dans url(…).
            if (preg_match('/url\s*\(\s*[\'"]?\s*(?!#)/i', $value) === 1 || preg_match('/javascript:|data:|expression\s*\(/i', $value) === 1) {
                return null;
            }
            if (($attr->prefix === null || $attr->prefix === '') && in_array($name, self::SVG_ATTRIBUTES, true) && $name !== 'xmlns') {
                $el->setAttribute($attr->localName, $value);
            }
        }
        foreach (iterator_to_array($node->childNodes) as $child) {
            if ($child instanceof \DOMElement) {
                $c = self::copySvgNode($child, $out);
                if ($c === null) {
                    return null;
                }
                if ($c !== false) {
                    $el->appendChild($c);
                }
            } elseif ($child instanceof \DOMCdataSection || $child instanceof \DOMProcessingInstruction) {
                return null;
            } elseif ($child instanceof \DOMText && in_array($tag, ['text', 'tspan', 'title', 'desc'], true) && trim($child->data) !== '') {
                $el->appendChild($out->createTextNode($child->data));
            }
        }

        return $el;
    }

    private static function containsForbidden(\DOMElement $node): bool
    {
        foreach ($node->getElementsByTagName('*') as $d) {
            if (in_array(strtolower($d->localName), self::SVG_FORBIDDEN, true)) {
                return true;
            }
            foreach (iterator_to_array($d->attributes ?? []) as $a) {
                $n = strtolower($a->localName);
                if (str_starts_with($n, 'on') || $n === 'href') {
                    return true;
                }
            }
        }

        return false;
    }

    /** @return array{ok: false, error: string} */
    private static function fail(string $error): array
    {
        return ['ok' => false, 'error' => $error];
    }
}
