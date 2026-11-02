<?php

declare(strict_types=1);

/**
 * Wiki ATAK (/wiki/atak) : contrôle et préparation après une modification des textes.
 *
 *   php tools/wiki/atak_wiki.php          contrôle : une page par sujet, titres FR / EN, liens internes valides
 *   php tools/wiki/atak_wiki.php warm     contrôle puis reconstruit le cache (storage/cache/atak-wiki-*.ser)
 *
 * Le site lit docs/steam/discussions/*.txt, docs/steam/STEAM_DESCRIPTION_LONG_{fr,en}.md et CHANGELOG-ATAK.md à la
 * volée : il n'y a rien à générer, le cache se reconstruit seul quand un fichier change. Cette commande sert à vérifier
 * un nouveau sujet avant de pousser. Code de sortie 1 s'il y a un problème. Voir docs/wiki-atak.md.
 */

use App\Support\Wiki\AtakWiki;

$root = dirname(__DIR__, 2);
require $root . '/app/Support/helpers.php';
spl_autoload_register(static function (string $class) use ($root): void {
    if (str_starts_with($class, 'App\\')) {
        $file = $root . '/app/' . str_replace('\\', '/', substr($class, 4)) . '.php';
        if (is_file($file)) {
            require $file;
        }
    }
});

$warm = ($argv[1] ?? '') === 'warm';
AtakWiki::useRoot($root);
$catalog = AtakWiki::catalog();
$problems = [];
$prefix = AtakWiki::url();

foreach (glob($root . '/' . AtakWiki::DISCUSSIONS_DIR . '/*.txt') ?: [] as $file) {
    $base = basename($file);
    if (preg_match('/^(\d+)_([a-z0-9\-]+)\.txt$/i', $base, $m) !== 1) {
        $problems[] = "$base : nom attendu NN_slug.txt (minuscules, chiffres, tirets)";
        continue;
    }
    if ((int) $m[1] === 0) {
        continue;
    }
    $found = array_filter($catalog['pages'], static fn (array $p): bool => $p['file'] === $base);
    if ($found === []) {
        $problems[] = "$base : aucune page";
    }
}

foreach ($catalog['pages'] as $slug => $page) {
    if ($page['category'] === 'autres') {
        $problems[] = "{$page['file']} : absent de 00_INDEX.txt (rangé dans « Autres »)";
    }
    foreach (['fr', 'en'] as $lang) {
        if (trim((string) $page[$lang]['title']) === '' || trim((string) $page[$lang]['text']) === '') {
            $problems[] = "{$page['file']} : partie $lang vide";
            continue;
        }
        $doc = AtakWiki::render((string) $slug, $lang);
        preg_match_all('/href="([^"]+)"/', $doc['html'], $hrefs);
        foreach ($hrefs[1] as $href) {
            $href = html_entity_decode($href, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            if (str_starts_with($href, $prefix . '/')) {
                $target = rawurldecode(substr($href, strlen($prefix) + 1));
                if (!isset($catalog['pages'][$target]) && !in_array($target, AtakWiki::RESERVED, true)) {
                    $problems[] = "{$page['file']} ($lang) : lien vers une page inconnue « $target »";
                }
            }
        }
        if (preg_match('/\[(?:\/?(?:h[1-3]|b|i|u|list|olist|url|quote|table|code|spoiler))\]/i', strip_tags($doc['html'])) === 1) {
            $problems[] = "{$page['file']} ($lang) : balise BBCode restée en texte (balise mal fermée ?)";
        }
    }
}

printf("%d pages (%d apps), %d catégories, %d versions dans les nouveautés, textes ATAK %s\n",
    count($catalog['pages']),
    count(array_filter($catalog['pages'], static fn (array $p): bool => $p['kind'] === 'app')),
    count($catalog['categories']),
    count($catalog['releases']),
    $catalog['version'] !== '' ? $catalog['version'] : '?'
);
foreach ($problems as $p) {
    fwrite(STDERR, "! $p\n");
}
if ($warm && $problems === []) {
    AtakWiki::useRoot(null);
    AtakWiki::catalog();
    echo "Cache reconstruit.\n";
}
exit($problems === [] ? 0 : 1);
