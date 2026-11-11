<?php

declare(strict_types=1);

/**
 * Recopie les jetons de couleur du thème TOC du jeu (fn_tocTheme.sqf, table « _tokens » en JSON strict) dans
 * public/assets/css/atak-toc.css (entre « toc-tokens:start » et « toc-tokens:end »), pour que le TOC web et le TOC du
 * jeu aient exactement les mêmes couleurs.
 *
 *   php tools/toc/sync_toc_tokens.php [chemin de fn_tocTheme.sqf]          réécrit le bloc
 *   php tools/toc/sync_toc_tokens.php --check [chemin]                      0 si identique, 1 sinon
 * Sans chemin : mod/COMSPEC_ATAK_Native/Sources/addons/main/functions/toc/fn_tocTheme.sqf.
 */

$root = dirname(__DIR__, 2);
$args = array_slice($argv, 1);
$check = in_array('--check', $args, true);
$args = array_values(array_filter($args, static fn (string $a): bool => $a !== '--check'));
$sqf = $args[0] ?? $root . '/mod/COMSPEC_ATAK_Native/Sources/addons/main/functions/toc/fn_tocTheme.sqf';
$css = $root . '/public/assets/css/atak-toc.css';

/** @return array<string, string> */
function toc_tokens_from_sqf(string $src): array
{
    if (preg_match('/TABLE _tokens \(JSON\)[^\n]*\n\s*private _tokens = (\[.*?\]);\s*\n\s*\/\/ ---- FIN/s', $src, $m) !== 1) {
        throw new RuntimeException('table _tokens introuvable');
    }
    $rows = json_decode($m[1], true, 8, JSON_THROW_ON_ERROR);
    $out = [];
    foreach ($rows as $r) {
        if (is_array($r) && count($r) === 2 && preg_match('/^#[0-9a-f]{6}$/i', (string) $r[1]) === 1) {
            $out[(string) $r[0]] = strtolower((string) $r[1]);
        }
    }

    return $out;
}

/** @param array<string, string> $tokens */
function toc_tokens_css(array $tokens): string
{
    $lines = [];
    foreach ($tokens as $k => $v) {
        $lines[] = '  --toc-' . $k . ': ' . $v . ';';
    }

    return "/* toc-tokens:start */\n:root {\n" . implode("\n", $lines) . "\n}\n/* toc-tokens:end */";
}

if (!is_file($sqf)) {
    fwrite(STDERR, "fn_tocTheme.sqf introuvable : {$sqf}\n");
    exit(2);
}
$tokens = toc_tokens_from_sqf((string) file_get_contents($sqf));
$block = toc_tokens_css($tokens);
$cur = (string) file_get_contents($css);
$new = preg_replace('#/\* toc-tokens:start \*/.*?/\* toc-tokens:end \*/#s', $block, $cur, 1);
if ($new === null) {
    exit(2);
}
if ($check) {
    echo $new === $cur ? "Jetons identiques.\n" : "Jetons différents : lancer php tools/toc/sync_toc_tokens.php\n";
    exit($new === $cur ? 0 : 1);
}
file_put_contents($css, $new);
echo count($tokens) . " jetons recopiés dans atak-toc.css\n";
