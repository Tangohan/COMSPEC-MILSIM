<?php
declare(strict_types=1);
use App\Support\SseFileCatalog as F;
use App\Support\SseIdentityCatalog as C;

ob_start();
$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
/** @var list<array<string,mixed>> $rows */
/** @var array<string,string> $filters */
$filesTab = 'journal';
require __DIR__ . '/_nav.php';
$sel = static fn (string $a, string $b): string => $a === $b ? ' selected' : '';
?>
<div class="fx">
    <div class="fx-th">
        <div>
            <h1>Journal des consultations</h1>
            <p>Toutes les consultations des fichiers, faites sur le site ou en jeu : qui a interrogé quoi, quand, et la réponse obtenue. Une consultation faite hors liaison apparaît quand le terminal la remonte (différée).</p>
        </div>
    </div>
    <section class="fx-card">
        <form class="fx-fs" method="get" action="<?= $h(url('atak/sse/fichiers/journal')) ?>" data-fx-instant role="search" aria-label="Filtres du journal">
            <div class="fx-g3" style="grid-template-columns:repeat(4,minmax(0,1fr))">
                <label class="fx-f"><span>Source</span><select class="fx-in" name="source"><option value="">Toutes les sources</option><?php foreach (F::SOURCES as $k => $label): ?><option value="<?= $h($k) ?>"<?= $sel($filters['source'] ?? '', $k) ?>><?= $h($label) ?></option><?php endforeach; ?></select></label>
                <label class="fx-f"><span>Type</span><select class="fx-in" name="type"><option value="">Tous les types</option><?php foreach (F::QUERY_TYPES as $k => $label): ?><option value="<?= $h($k) ?>"<?= $sel($filters['type'] ?? '', $k) ?>><?= $h($label) ?> (<?= $h($k) ?>)</option><?php endforeach; ?></select></label>
                <label class="fx-f"><span>Réponse</span><select class="fx-in" name="hit"><option value="">HIT et NO HIT</option><option value="1"<?= $sel($filters['hit'] ?? '', '1') ?>>HIT seulement</option><option value="0"<?= $sel($filters['hit'] ?? '', '0') ?>>NO HIT seulement</option></select></label>
                <label class="fx-f"><span>Recherche <kbd>/</kbd></span><input class="fx-in" name="q" type="search" value="<?= $h($filters['q'] ?? '') ?>" placeholder="QRN, nom, numéro, opérateur…" data-fx-focus></label>
            </div>
            <div class="fx-row"><small class="fx-help">Les filtres s’appliquent tout seuls. Ex. « HIT seulement » et la source « Jeu » pour revoir les contrôles positifs de la soirée.</small><a href="<?= $h(url('atak/sse/fichiers/journal')) ?>">Réinitialiser les filtres</a></div>
        </form>
        <div class="fx-rh"><b><?= count($rows) ?> consultation<?= count($rows) > 1 ? 's' : '' ?></b></div>
        <?php if ($rows === []): ?>
            <div class="fx-nr"><b>AUCUNE CONSULTATION</b><p>Les consultations du site et du jeu apparaissent ici.</p></div>
        <?php else: ?>
            <div class="fx-tbw">
                <table class="fx-tb resp">
                    <thead><tr><th>Requête</th><th>Heure</th><th>Type</th><th>Critères</th><th>Source</th><th>Opérateur</th><th>Réponse</th><th></th></tr></thead>
                    <tbody>
                    <?php foreach ($rows as $r): ?>
                        <tr class="<?= $r['hit'] && in_array($r['verdict'], ['hvt', 'recherche'], true) ? 'fl' : '' ?>">
                            <td class="m" translate="no"><?= $h($r['qrn']) ?><?php if ($r['deferred']): ?> <span class="fx-flg a">Différée</span><?php endif; ?></td>
                            <td class="m"><?= $h(substr((string) $r['created_at'], 0, 16)) ?><?php if (($r['game_time'] ?? '') !== ''): ?><br><span class="fx-help">jeu <?= $h($r['game_time']) ?></span><?php endif; ?></td>
                            <td><?= $h($r['query_type_label']) ?> <span class="fx-flg"><?= $h($r['query_type']) ?></span></td>
                            <td translate="no"><?= $h(implode(' · ', array_map(static fn (mixed $v): string => is_scalar($v) ? (string) $v : '', $r['criteria'])) ?: '—') ?></td>
                            <td><?= $h($r['source_label']) ?></td>
                            <td translate="no"><?= $h($r['operator']) ?></td>
                            <td><span class="fx-flg <?= $r['hit'] ? 'r' : '' ?>"><?= $r['hit'] ? 'HIT' : 'NO HIT' ?></span><?php if ($r['verdict'] !== ''): ?><br><span class="fx-help"><?= $h(C::label(C::VERDICTS, $r['verdict'], $r['verdict'])) ?></span><?php endif; ?></td>
                            <td><a href="<?= $h($r['replay_url']) ?>">Rejouer</a></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>
</div>
<?php
$sseContent = ob_get_clean();
require dirname(__DIR__) . '/_layout.php';
