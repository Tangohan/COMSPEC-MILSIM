<?php
declare(strict_types=1);
/**
 * Comparer deux fiches côte à côte (photos et signalement, mêmes lignes), sans reconnaissance faciale.
 *
 * @var array<string,mixed>|null $a
 * @var array<string,mixed>|null $b
 */
use App\Support\SseFileSubjectCatalog as S;
use App\Support\SseRecordsFormat as RF;

ob_start();
$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
require __DIR__ . '/_nav.php';
$rows = static function (array $r): array {
    $i = $r['identity'];
    $p = $r['profile'] ?? [];
    $c = (string) $r['country'];

    return [
        'Naissance' => ($i['birth_date'] ?? null) ? RF::date((string) $i['birth_date'], $c) : '—',
        'Sexe' => $i['sex'] ?? 'X',
        'Taille' => ($p['height_cm'] ?? null) !== null ? RF::height((int) $p['height_cm'], $c) : '—',
        'Poids' => ($p['weight_kg'] ?? null) !== null ? RF::weight((int) $p['weight_kg'], $c) : '—',
        'Corpulence' => S::label(S::BUILDS, $p['build'] ?? null),
        'Cheveux' => S::label(S::HAIR, $p['hair'] ?? null),
        'Yeux' => S::label(S::EYES, $p['eyes'] ?? null),
        'Signes' => (string) ($p['marks'] ?? '—'),
        'Alias' => $r['aliases'] !== [] ? implode(', ', array_map(static fn (array $x): string => (string) $x['alias'], $r['aliases'])) : '—',
        'Mentions' => $r['flags'] !== [] ? implode(', ', array_map(static fn (array $f): string => $f['label'], $r['flags'])) : '—',
    ];
};
$ra = $a !== null ? $rows($a) : [];
$rb = $b !== null ? $rows($b) : [];
?>
<div class="fx">
    <div class="fx-th"><div><h1>Comparer deux fiches</h1><p>Photos et signalement côte à côte. Les lignes identiques sont marquées : à vous de juger si c’est la même personne.</p></div>
        <div class="fx-acts"><a class="btn" href="<?= $h(url('atak/sse/fichiers/photos')) ?>">Retour à la photothèque</a></div></div>
    <?php if ($a === null || $b === null): ?>
        <div class="fx-card"><div class="fx-nr"><b>DEUX FICHES À CHOISIR</b><p>Cochez deux fiches dans la photothèque, ou ouvrez « Comparer » depuis les fiches proches d’un sujet.</p></div></div>
    <?php else: ?>
        <div class="fx-cmp">
            <?php foreach ([$a, $b] as $r): ?>
                <?php $ph = $r['photos'][0] ?? null; ?>
                <article class="fx-card">
                    <div class="fx-idb" style="grid-template-columns:auto minmax(0,1fr)">
                        <div class="fx-shot"><?php if ($ph !== null): ?><img src="<?= $h(user_media_public_url((string) $ph['path'])) ?>" alt=""><?php else: ?><div class="hc"></div><div class="sil" style="top:20px"><i></i><u></u></div><?php endif; ?><div class="lb"><span>FACE</span><span></span></div></div>
                        <div style="min-width:0"><h2 translate="no"><a href="<?= $h(url('atak/sse/fichiers/sujet/' . (int) $r['identity']['id'])) ?>"><?= $h($r['identity']['display_name']) ?></a></h2><small class="fx-help"><?= $h($r['identity']['ref']) ?></small></div>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
        <div class="fx-card" style="margin-top:16px"><div class="fx-tbw"><table class="fx-tb">
            <thead><tr><th></th><th translate="no"><?= $h($a['identity']['display_name']) ?></th><th translate="no"><?= $h($b['identity']['display_name']) ?></th></tr></thead>
            <tbody><?php foreach ($ra as $k => $v): ?><?php $same = $v !== '—' && $v === ($rb[$k] ?? null); ?><tr><td><?= $h($k) ?><?= $same ? ' <span class="fx-flg g">identique</span>' : '' ?></td><td class="m" style="white-space:normal" translate="no"><?= $h($v) ?></td><td class="m" style="white-space:normal" translate="no"><?= $h($rb[$k] ?? '—') ?></td></tr><?php endforeach; ?></tbody>
        </table></div></div>
    <?php endif; ?>
</div>
<?php
$sseContent = ob_get_clean();
require dirname(__DIR__) . '/_layout.php';
