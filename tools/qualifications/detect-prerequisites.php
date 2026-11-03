<?php

declare(strict_types=1);

/**
 * Pré-calcul des prérequis structurés des catalogues de qualifications (config/qualification_catalog/*.json).
 *
 *   php tools/qualifications/detect-prerequisites.php           aperçu (rien n'est écrit)
 *   php tools/qualifications/detect-prerequisites.php --write   écrit le champ prerequisites_detected des packs et
 *                                                               le rapport docs/qualifications/prerequis-detectes.md
 *
 * Seules les détections de confiance haute (QualificationPrerequisiteExtractor::HIGH) sont écrites ; les autres restent
 * des suggestions calculées à l'écran. Le rapport liste chaque ajout avec sa phrase source, pour relecture humaine.
 * Relancer recalcule tout le champ (idempotent).
 */

$root = dirname(__DIR__, 2);
spl_autoload_register(static function (string $class) use ($root): void {
    if (str_starts_with($class, 'App\\')) {
        $file = $root . '/app/' . str_replace('\\', '/', substr($class, 4)) . '.php';
        if (is_file($file)) {
            require $file;
        }
    }
});

use App\Support\QualificationCatalog\QualificationCatalog;
use App\Support\QualificationPrerequisiteExtractor as Extractor;
use App\Support\QualificationPrerequisiteResolver as Resolver;

$write = in_array('--write', $argv, true);
$resolver = Resolver::fromCatalog('fr');
$dir = $root . '/config/qualification_catalog';
$report = [];
$uncertain = [];
$total = 0;

/** @param array<string, mixed> $d */
$stored = static function (array $d): array {
    $out = ['kind' => $d['kind']];
    foreach (match ($d['kind']) {
        'metric' => ['metric', 'scope', 'min', 'window_days'],
        'attestation' => ['key', 'min'],
        'grade' => ['nato', 'rank'],
        default => ['ref'],
    } as $k) {
        $v = $d[$k] ?? null;
        $out[$k] = is_float($v) && floor($v) === $v ? (int) $v : $v;
    }

    return $out;
};
$sig = static fn (array $d): string => $d['kind'] === 'qualification' ? 'q:' . $d['ref'] : Extractor::signature($d);

foreach (QualificationCatalog::PACKS as $pack) {
    $file = $dir . '/' . $pack . '.json';
    $raw = str_replace("\r\n", "\n", (string) file_get_contents($file));
    $data = json_decode($raw, true);
    foreach ($data['entries'] as $ei => $entry) {
        $found = [];
        $keep = static function (array $d, array $extra) use (&$found, &$uncertain, $sig, $entry): void {
            $key = $sig($d);
            if ($d['confidence'] < Extractor::HIGH) {
                $uncertain[$entry['id']][$key] ??= $d + $extra;

                return;
            }
            if (!isset($found[$key]) || $d['confidence'] > $found[$key]['confidence']) {
                $found[$key] = $d + $extra;
            }
        };
        foreach (['en', 'fr'] as $lang) {
            foreach ($entry['prerequisites'][$lang] ?? [] as $i => $sentence) {
                foreach (Extractor::extract([(string) $sentence], Extractor::ORIGIN_PREREQUISITE, $pack) as $d) {
                    if ($d['kind'] !== 'reference') {
                        $keep($d, ['source_index' => $i]);
                        continue;
                    }
                    foreach ($resolver->resolve((string) $sentence, (string) $entry['id'], $pack) as $hit) {
                        $keep(['kind' => 'qualification', 'ref' => (string) $hit['id'], 'confidence' => $hit['confidence'], 'source' => $sentence, 'matched' => $hit['matched']], ['source_index' => $i]);
                    }
                }
            }
            foreach (Extractor::extract((string) ($entry['description'][$lang] ?? ''), Extractor::ORIGIN_DESCRIPTION, $pack) as $d) {
                $keep($d, ['source_kind' => 'description']);
            }
        }
        $items = [];
        foreach ($found as $key => $d) {
            unset($uncertain[$entry['id']][$key]);
            $item = $stored($d) + ['confidence' => round((float) $d['confidence'], 2)];
            if (isset($d['source_index'])) {
                $item['source_index'] = (int) $d['source_index'];
            } else {
                $item['source'] = 'description';
            }
            $items[] = $item;
            $report[$pack][$entry['id']][] = $item + ['_entry' => $entry, '_matched' => (string) ($d['matched'] ?? $d['excerpt'] ?? ''), '_sentence' => (string) ($d['source'] ?? '')];
        }
        $total += count($items);
        // Le champ suit la liste des prérequis ; absent quand rien n'est sûr.
        $rebuilt = [];
        foreach ($entry as $k => $v) {
            if ($k === 'prerequisites_detected') {
                continue;
            }
            $rebuilt[$k] = $v;
            if ($k === 'prerequisites' && $items !== []) {
                $rebuilt['prerequisites_detected'] = $items;
            }
        }
        $data['entries'][$ei] = $rebuilt;
    }
    if ($write) {
        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $json = preg_replace_callback('/^( +)/m', static fn (array $m): string => str_repeat(' ', intdiv(strlen($m[1]), 2)), (string) $json) . "\n";
        file_put_contents($file, str_replace("\n", PHP_EOL === "\r\n" ? "\r\n" : "\n", $json));
    }
}

/* ——— Rapport ——— */
$describe = static function (array $it): string {
    return match ($it['kind']) {
        'metric' => match ($it['metric']) {
            'halo_jumps' => $it['min'] . ' saut(s) en parachute, mesurés par le mod' . (($it['window_days'] ?? 0) > 0 ? ' sur ' . $it['window_days'] . ' jours' : ''),
            'flight_hours' => $it['min'] . ' h de vol' . ($it['scope'] !== '' ? ' (' . $it['scope'] . ')' : '') . ', mesurées',
            'missions_played' => $it['min'] . ' mission(s) jouée(s), mesurées',
            'flight_count' => $it['min'] . ' vol(s), mesurés',
            'seniority_days' => 'ancienneté ' . $it['min'] . ' jours, mesurée (dossier / arrivée)',
            'playtime_hours' => $it['min'] . ' h de jeu, mesurées',
            default => $it['metric'] . ' ≥ ' . $it['min'],
        },
        'attestation' => match ($it['key']) {
            'freefall_jumps' => $it['min'] . ' saut(s) en chute libre, attestés par un instructeur',
            'dive_hours' => $it['min'] . ' h de plongée, attestées par un instructeur',
            default => $it['key'] . ' (attestation)',
        },
        'grade' => 'grade minimum ' . $it['nato'] . ' (' . $it['rank'] . ')',
        default => 'qualification « ' . QualificationCatalog::pick(QualificationCatalog::findRaw((string) $it['ref'])['name'] ?? [], 'fr') . ' » (`' . $it['ref'] . '`)',
    };
};
$phrase = static function (array $it, array $entry, string $lang): string {
    if (isset($it['source_index'])) {
        return (string) ($entry['prerequisites'][$lang][$it['source_index']] ?? '');
    }

    return (string) ($entry['description'][$lang] ?? '');
};
$md = "# Prérequis détectés dans les catalogues de qualifications\n\n";
$md .= "Généré par `php tools/qualifications/detect-prerequisites.php --write` (ne pas modifier à la main : relancer l’outil).\n\n";
$md .= "Chaque ajout ci-dessous est écrit dans le champ `prerequisites_detected` de l’entrée du catalogue (confiance ≥ "
    . Extractor::HIGH . "). Ils ne s’appliquent jamais seuls : à l’import ou depuis la fiche, l’encart « Prérequis détectés » les propose cochés, "
    . "la personne valide. Les détections moins sûres restent des suggestions non cochées (liste en fin de rapport).\n\n";
$md .= "À relire : la phrase source justifie-t-elle l’ajout ? Un ajout faux se retire en corrigeant l’extracteur "
    . "(`app/Support/QualificationPrerequisiteExtractor.php`, `QualificationPrerequisiteResolver.php`) puis en relançant l’outil.\n\n";
$counts = [];
foreach ($report as $pack => $entries) {
    foreach ($entries as $items) {
        foreach ($items as $it) {
            $counts[$pack][$it['kind']] = ($counts[$pack][$it['kind']] ?? 0) + 1;
        }
    }
}
$md .= "## Synthèse\n\n| Pack | Qualifications | Mesurés (jeu, site) | Attestés | Grade | Total |\n|---|---|---|---|---|---|\n";
foreach (QualificationCatalog::PACKS as $pack) {
    $c = $counts[$pack] ?? [];
    $md .= '| ' . $pack . ' | ' . ($c['qualification'] ?? 0) . ' | ' . ($c['metric'] ?? 0) . ' | ' . ($c['attestation'] ?? 0) . ' | ' . ($c['grade'] ?? 0) . ' | ' . array_sum($c) . " |\n";
}
$md .= '| **Total** | | | | | **' . $total . "** |\n\n";
foreach ($report as $pack => $entries) {
    $md .= '## ' . strtoupper($pack) . "\n\n";
    foreach ($entries as $id => $items) {
        $entry = $items[0]['_entry'];
        $md .= '### `' . $id . '` — ' . QualificationCatalog::pick($entry['name'], 'fr') . "\n\n";
        foreach ($items as $it) {
            $fr = $phrase($it, $entry, 'fr');
            $en = $phrase($it, $entry, 'en');
            $where = isset($it['source_index']) ? 'prérequis n° ' . ($it['source_index'] + 1) : 'description';
            $md .= '- **' . $describe($it) . '** — confiance ' . number_format((float) $it['confidence'], 2) . ', ' . $where . "\n";
            if (isset($it['source_index'])) {
                $md .= '  - FR : « ' . $fr . " »\n";
                $md .= '  - EN : “' . $en . "”\n";
            } else {
                // Description : la phrase qui porte la condition (la description complète est dans le catalogue).
                $md .= '  - Phrase : « ' . $it['_sentence'] . " »\n";
            }
        }
        $md .= "\n";
    }
}
$n = 0;
$lines = '';
foreach ($uncertain as $id => $items) {
    foreach ($items as $d) {
        $n++;
        $what = match ($d['kind']) {
            'metric' => $d['metric'] . ' ≥ ' . $d['min'],
            'attestation' => 'attestation ' . $d['key'] . (($d['min'] ?? 0) > 0 ? ' ≥ ' . $d['min'] : ''),
            'grade' => 'grade ' . $d['nato'] . ' (' . $d['rank'] . ')',
            default => 'qualification `' . $d['ref'] . '`',
        };
        $lines .= '| `' . $id . '` | ' . $what . ' | ' . number_format((float) $d['confidence'], 2) . ' | ' . str_replace('|', '/', mb_strimwidth((string) ($d['source'] ?? ''), 0, 140, '…')) . " |\n";
    }
}
$md .= "## Restent en suggestion (" . $n . ")\n\nNon écrits dans les catalogues : proposés non cochés dans l’encart « Prérequis détectés ».\n\n| Entrée | Détection | Confiance | Phrase |\n|---|---|---|---|\n" . $lines;

if ($write) {
    @mkdir($root . '/docs/qualifications', 0775, true);
    file_put_contents($root . '/docs/qualifications/prerequis-detectes.md', $md);
}
fwrite(STDOUT, $total . ' prérequis structurés' . ($write ? ' écrits' : ' détectés (aperçu, --write pour écrire)') . ', ' . $n . " restent en suggestion.\n");
