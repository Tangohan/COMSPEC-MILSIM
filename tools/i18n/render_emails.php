<?php

declare(strict_types=1);

/**
 * Rend chaque modèle views/emails/*.php avec des valeurs repères (8800001, 8800002…) à la place des variables, pour
 * que tools/i18n/site_i18n.py extract-emails en tire des modèles %1, %2… Deux passes : variables « drapeau »
 * (is…, has…, show…) vraies puis fausses. Sortie JSON : {"fichier#passe": {"html": ..., "text": ..., "subject": ...}}.
 */

$root = dirname(__DIR__, 2);
require $root . '/bootstrap/autoload.php';
set_error_handler(static fn (): bool => true);

$out = [];
foreach (glob($root . '/views/emails/*.php') ?: [] as $path) {
    $src = (string) file_get_contents($path);
    preg_match_all('/\$([a-zA-Z_]\w*)/', $src, $m);
    $names = array_values(array_unique(array_diff($m[1], ['this', 'GLOBALS'])));
    foreach ([true, false] as $flags) {
        $vars = [];
        $n = 0;
        foreach ($names as $name) {
            if (preg_match('/^(is|has|show|can|with|include)[A-Z_]/', $name)) {
                $vars[$name] = $flags;
                continue;
            }
            $vars[$name] = (string) (8800001 + $n++);
        }
        try {
            $res = (static function (string $__p, array $__v) {
                extract($__v, EXTR_SKIP);
                ob_start();
                $r = include $__p;
                ob_end_clean();

                return $r;
            })($path, $vars);
        } catch (\Throwable) {
            continue;
        }
        if (is_array($res)) {
            $out[basename($path) . '#' . ($flags ? '1' : '0')] = [
                'html' => (string) ($res['html'] ?? ''),
                'text' => (string) ($res['text'] ?? ''),
            ];
        }
    }
}
echo json_encode($out, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
