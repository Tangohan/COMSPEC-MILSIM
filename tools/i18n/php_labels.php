<?php

declare(strict_types=1);

// Libellés rangés dans des tableaux PHP (« 'label' => 'Indicatifs radio' ») des fichiers donnés, lus avec le
// tokenizer de PHP (les expressions régulières de site_i18n.py se décalaient sur les chaînes "…$x…").
// Sortie : une ligne JSON [fichier, texte] par libellé. Appelé par tools/i18n/site_i18n.py.

$keys = ['label', 'title', 'subtitle', 'kicker', 'description', 'hint', 'group', 'heading', 'cta', 'empty', 'section',
    'tab', 'button', 'help', 'lead', 'intro', 'name', 'text', 'summary', 'confirm', 'badge', 'action', 'tooltip'];
$keys = array_flip($keys);
$files = array_slice($argv, 1);
if ($files === [] || $files === ['-']) {
    $files = array_filter(array_map('trim', explode("\n", (string) stream_get_contents(STDIN))));
}
foreach ($files as $file) {
    $src = @file_get_contents($file);
    if (!is_string($src)) {
        continue;
    }
    $tokens = @token_get_all($src);
    $sig = [];
    foreach ($tokens as $t) {
        if (is_array($t) && in_array($t[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
            continue;
        }
        $sig[] = $t;
    }
    $n = count($sig);
    for ($i = 2; $i < $n; $i++) {
        $t = $sig[$i];
        if (!is_array($t) || $t[0] !== T_CONSTANT_ENCAPSED_STRING) {
            continue;
        }
        $arrow = $sig[$i - 1];
        $key = $sig[$i - 2];
        if (!is_array($arrow) || $arrow[0] !== T_DOUBLE_ARROW || !is_array($key) || $key[0] !== T_CONSTANT_ENCAPSED_STRING) {
            continue;
        }
        $k = substr($key[1], 1, -1);
        if (!isset($keys[$k])) {
            continue;
        }
        // Valeur suivie d'une concaténation : texte incomplet, ignoré.
        $next = $sig[$i + 1] ?? null;
        if ($next === '.') {
            continue;
        }
        $raw = $t[1];
        $val = $raw[0] === "'" ? str_replace(["\\'", '\\\\'], ["'", '\\'], substr($raw, 1, -1)) : stripcslashes(substr($raw, 1, -1));
        echo json_encode([$file, $val], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), "\n";
    }
}
