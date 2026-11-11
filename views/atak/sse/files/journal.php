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
?>

<div class="page-heading">
    <div>
        <h1>Journal des consultations</h1>
        <p>Toutes les consultations des fichiers, faites sur le site ou en jeu. Une consultation faite hors liaison apparaît quand le terminal la remonte (différée).</p>
    </div>
</div>

<form class="toolbar" method="get" action="<?= $h(url('atak/sse/fichiers/journal')) ?>">
    <div class="toolbar-field">
        <label for="fq-source">Source</label>
        <select id="fq-source" name="source">
            <option value="">Toutes les sources</option>
            <?php foreach (F::SOURCES as $k => $label): ?>
                <option value="<?= $h($k) ?>" <?= ($filters['source'] ?? '') === $k ? 'selected' : '' ?>><?= $h($label) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="toolbar-field">
        <label for="fq-type">Type</label>
        <select id="fq-type" name="type">
            <option value="">Tous les types</option>
            <?php foreach (F::QUERY_TYPES as $k => $label): ?>
                <option value="<?= $h($k) ?>" <?= ($filters['type'] ?? '') === $k ? 'selected' : '' ?>><?= $h($label) ?> (<?= $h($k) ?>)</option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="toolbar-field">
        <label for="fq-hitf">Réponse</label>
        <select id="fq-hitf" name="hit">
            <option value="">HIT et NO HIT</option>
            <option value="1" <?= ($filters['hit'] ?? '') === '1' ? 'selected' : '' ?>>HIT seulement</option>
            <option value="0" <?= ($filters['hit'] ?? '') === '0' ? 'selected' : '' ?>>NO HIT seulement</option>
        </select>
    </div>
    <div class="toolbar-field">
        <label for="fq-q">Recherche</label>
        <input id="fq-q" name="q" type="search" value="<?= $h($filters['q'] ?? '') ?>" placeholder="Numéro de requête, nom, numéro, opérateur…">
    </div>
    <div class="toolbar-actions"><button class="btn" type="submit">Appliquer</button></div>
</form>

<section class="panel">
    <div class="panel-header">
        <div class="panel-title">Consultations</div>
        <div class="panel-meta"><?= count($rows) ?> consultation(s)</div>
    </div>
    <?php if ($rows === []): ?>
        <div class="empty-state"><div class="empty-state-inner"><strong>Aucune consultation</strong><p>Les consultations du site et du jeu apparaissent ici.</p></div></div>
    <?php else: ?>
        <div class="table-wrap">
            <table>
                <thead><tr><th>Requête</th><th>Heure</th><th>Type</th><th>Critères</th><th>Source</th><th>Opérateur</th><th>Réponse</th><th>Codes</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($rows as $r): ?>
                    <tr>
                        <td><span translate="no"><?= $h($r['qrn']) ?></span><?php if ($r['deferred']): ?> <span class="badge">Différée</span><?php endif; ?></td>
                        <td><?= $h($r['created_at']) ?><?php if (($r['game_time'] ?? '') !== ''): ?><div class="fq-code">jeu <?= $h($r['game_time']) ?></div><?php endif; ?></td>
                        <td><?= $h($r['query_type_label']) ?> <span class="fq-code"><?= $h($r['query_type']) ?></span></td>
                        <td translate="no"><?= $h(implode(' · ', array_map(static fn (mixed $v): string => is_scalar($v) ? (string) $v : '', $r['criteria'])) ?: '—') ?></td>
                        <td><?= $h($r['source_label']) ?></td>
                        <td translate="no"><?= $h($r['operator']) ?></td>
                        <td>
                            <span class="fq-hit <?= $r['hit'] ? 'fq-hit--yes' : 'fq-hit--no' ?>"><?= $r['hit'] ? 'HIT' : 'NO HIT' ?></span>
                            <?php if ($r['verdict'] !== ''): ?><div class="fq-code"><?= $h(C::label(C::VERDICTS, $r['verdict'], $r['verdict'])) ?></div><?php endif; ?>
                        </td>
                        <td><span class="fq-code" translate="no"><?= $h(implode(' ', array_filter($r['codes'], static fn (string $code): bool => !str_starts_with($code, 'qrn:')))) ?></span></td>
                        <td><a class="btn-open" href="<?= $h($r['replay_url']) ?>">Rejouer</a></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
<?php
$sseContent = ob_get_clean();
require dirname(__DIR__) . '/_layout.php';
