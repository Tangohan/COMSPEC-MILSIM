<?php
declare(strict_types=1);
use App\Controllers\Web\SseFileController;
use App\Support\SseFileCatalog as F;

ob_start();
$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
/** @var list<array<string,mixed>> $records */
/** @var array<string,string> $filters */
$filesTab = 'seek';
require __DIR__ . '/_nav.php';
$yes = static fn (bool $b): string => $b ? '✓' : '·';
?>

<div class="page-heading">
    <div>
        <h1>Fiches SEEK II</h1>
        <p>Enregistrements biométriques (doigts, iris, visage) faits en jeu avec le SEEK II, rattachés au registre quand la clé biométrique ou le papier présenté correspond. Chaque fiche ajoute un contrôle N4 aux contrôles subis de la personne.</p>
    </div>
</div>

<form class="toolbar" method="get" action="<?= $h(url('atak/sse/fichiers/seek')) ?>">
    <div class="toolbar-field">
        <label for="fq-verdict">Verdict</label>
        <select id="fq-verdict" name="verdict">
            <option value="">Tous les verdicts</option>
            <?php foreach (F::SEEK_VERDICTS as $k => $label): ?>
                <option value="<?= $h($k) ?>" <?= ($filters['verdict'] ?? '') === $k ? 'selected' : '' ?>><?= $h($label) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="toolbar-field">
        <label for="fq-q">Recherche</label>
        <input id="fq-q" name="q" type="search" value="<?= $h($filters['q'] ?? '') ?>" placeholder="REC, clé, nom, numéro, contrôleur…">
    </div>
    <div class="toolbar-actions"><button class="btn" type="submit">Appliquer</button></div>
</form>

<section class="panel">
    <div class="panel-header">
        <div class="panel-title">Enregistrements</div>
        <div class="panel-meta"><?= count($records) ?> fiche(s)</div>
    </div>
    <?php if ($records === []): ?>
        <div class="empty-state"><div class="empty-state-inner"><strong>Aucune fiche SEEK II</strong><p>Les fiches apparaissent ici quand un joueur les transmet depuis le SEEK II en jeu.</p></div></div>
    <?php else: ?>
        <div class="table-wrap">
            <table>
                <thead><tr><th>Enregistrement</th><th>Reçu le</th><th>Nom déclaré</th><th>Papier présenté</th><th>Verdict</th><th>Doigts</th><th>Iris</th><th>Visage</th><th>Lieu</th><th>Contrôleur</th><th>Registre</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($records as $s): ?>
                    <tr>
                        <td translate="no"><?= $h($s['rec']) ?><?php if ($s['enrolled']): ?> <span class="badge">Enrôlé</span><?php endif; ?></td>
                        <td><?= $h($s['created_at']) ?><?php if (($s['game_time'] ?? '') !== ''): ?><div class="fq-code">jeu <?= $h($s['game_time']) ?></div><?php endif; ?></td>
                        <?php if ($s['subject_unknown']): ?>
                            <td>Inconnu</td>
                        <?php else: ?>
                            <td translate="no"><?= $h($s['display_name'] !== '' ? $s['display_name'] : '—') ?></td>
                        <?php endif; ?>
                        <td translate="no"><?= $h(trim(($s['doc_country'] ?? '') . ' ' . ($s['doc_number'] ?? '')) ?: '—') ?></td>
                        <td><?= $h($s['verdict_label']) ?> <span class="fq-code"><?= $h($s['verdict']) ?></span></td>
                        <td><?= $yes($s['modalities']['fingers']) ?></td>
                        <td><?= $yes($s['modalities']['iris']) ?></td>
                        <td><?= $yes($s['modalities']['face']) ?></td>
                        <td translate="no"><?= $h($s['grid'] ?? '—') ?></td>
                        <td translate="no"><?= $h($s['controller_callsign'] ?? '—') ?></td>
                        <td>
                            <?php if ($s['identity_id'] !== null): ?><a class="link" href="<?= $h(url('atak/sse/papiers/identites/' . (int) $s['identity_id'])) ?>">Identité</a><?php endif; ?>
                            <?php if ($s['check_id'] !== null): ?> <a class="link" href="<?= $h(url('atak/sse/papiers/controles/' . (int) $s['check_id'])) ?>">Contrôle N4</a><?php endif; ?>
                        </td>
                        <td><a class="btn-open" href="<?= $h(SseFileController::replayUrl('QB', ['number' => $s['rec']])) ?>">Passer aux fichiers</a></td>
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
