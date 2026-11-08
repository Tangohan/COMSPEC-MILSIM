<?php
declare(strict_types=1);

use App\Services\Sse\SseEditorLibraryService as Library;

ob_start();
$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
/** @var list<array<string,mixed>> $editorItems */
/** @var array{type:string,state:string,q:string} $editorFilters */
/** @var int $editorTotal */
/** @var int $editorPage */
/** @var int $editorPages */
/** @var bool $editorUnavailable */
$editorPageUrl = static fn (int $p): string => url('atak/sse/editeur') . '?' . http_build_query(array_filter($editorFilters + ['page' => $p > 1 ? $p : ''], static fn ($v): bool => $v !== ''));
$sseExtraScripts = '<script src="' . $h(asset_url('assets/js/sse-case-modals.js')) . '"></script>';
?>
<style>
.sse-modal.el-fiche { max-width: min(46rem, calc(100vw - 2rem)); }
.el-fiche h2 { text-transform: none; overflow-wrap: anywhere; }
.el-found { display: block; margin-top: .3rem; font-size: .78rem; }
.el-facts { display: grid; grid-template-columns: repeat(auto-fit, minmax(10rem, 1fr)); gap: .6rem 1rem; margin: 0 0 1rem; }
.el-facts dt { color: #94a3b8; font-size: .68rem; font-weight: 650; letter-spacing: .06em; text-transform: uppercase; }
.el-facts dd { margin: .1rem 0 0; overflow-wrap: anywhere; }
.el-body { margin: 0 0 1rem; padding: .7rem .8rem; border: 1px solid rgba(148, 163, 184, .14); white-space: pre-wrap; overflow-wrap: anywhere; }
.el-history { margin: 0; padding: 0; list-style: none; }
.el-history li { padding: .4rem 0; border-top: 1px solid rgba(148, 163, 184, .12); overflow-wrap: anywhere; }
.el-history small { display: block; color: #94a3b8; }
.el-pages { display: flex; align-items: center; justify-content: flex-end; gap: .6rem; margin-top: .8rem; }
</style>
<div class="breadcrumb">Athena / SSE / <strong>Bibliothèque de l’éditeur</strong></div>

<div class="page-heading">
    <div>
        <div class="page-heading-overline">Préparation // Éditeur SSE</div>
        <h1>Bibliothèque de l’éditeur</h1>
        <p>Les contenus enregistrés par la communauté depuis l’éditeur SSE du jeu. Lecture seule : ils se créent et se modifient dans le jeu.</p>
    </div>
    <div class="page-reference"><strong><?= (int) $editorTotal ?> contenu(s)</strong> Réf. ATH-SSE-EDIT-BIB</div>
</div>

<form class="toolbar" method="get" action="<?= $h(url('atak/sse/editeur')) ?>">
    <div class="toolbar-field">
        <label for="el-q">Recherche</label>
        <input id="el-q" name="q" value="<?= $h($editorFilters['q']) ?>" placeholder="Titre, texte, opération, lien ou joueur">
    </div>
    <div class="toolbar-field">
        <label for="el-type">Type</label>
        <select id="el-type" name="type">
            <option value="">Tous</option>
            <?php foreach (Library::TYPE_LABELS as $k => $label): ?><option value="<?= $h($k) ?>" <?= $editorFilters['type'] === $k ? 'selected' : '' ?>><?= $h($label) ?></option><?php endforeach; ?>
        </select>
    </div>
    <div class="toolbar-field">
        <label for="el-state">État</label>
        <select id="el-state" name="state">
            <option value="">Tous</option>
            <?php foreach (Library::STATES as $label): ?><option value="<?= $h($label) ?>" <?= $editorFilters['state'] === $label ? 'selected' : '' ?>><?= $h($label) ?></option><?php endforeach; ?>
        </select>
    </div>
    <div class="toolbar-actions">
        <button class="btn btn--ghost" type="submit">Filtrer</button>
    </div>
</form>

<section class="panel" style="margin-top:10px">
    <div class="panel-header"><div class="panel-title"><span class="panel-index">01</span> Contenus</div><div class="panel-meta">Les plus récents d’abord</div></div>
    <div class="panel-body">
        <?php if ($editorUnavailable): ?>
            <p class="muted">La bibliothèque n’est pas disponible pour le moment : l’étape de migration 4070 n’a pas encore été passée, ou la base ne répond pas. Le mod continue de travailler sur la bibliothèque de chaque poste.</p>
        <?php elseif ($editorItems === []): ?>
            <p class="muted"><?= $editorFilters['type'] === '' && $editorFilters['state'] === '' && $editorFilters['q'] === '' ? 'Aucun contenu. Ils apparaissent ici quand un membre enregistre dans l’éditeur SSE du jeu.' : 'Aucun contenu ne correspond à ces filtres.' ?></p>
        <?php else: ?>
            <div class="table-wrap">
                <table>
                    <thead><tr><th>Titre</th><th>Type</th><th>Marquage</th><th>État</th><th>Opération</th><th>Version</th><th>Date</th><th>Auteur</th><th></th></tr></thead>
                    <tbody>
                    <?php foreach ($editorItems as $it): ?>
                        <tr>
                            <td>
                                <span translate="no"><?= $h($it['title']) ?></span>
                                <?php if ($it['found_by'] !== ''): ?><span class="el-found"><span class="badge">Trouvé par un joueur</span> <span translate="no"><?= $h($it['found_by']) ?></span></span><?php endif; ?>
                            </td>
                            <td><?= $h($it['type_label']) ?></td>
                            <td><?= $h($it['marking']) ?></td>
                            <td><span class="badge"><?= $h($it['state']) ?></span></td>
                            <td translate="no"><?= $h($it['op'] !== '' ? $it['op'] : '—') ?></td>
                            <td><?= (int) $it['version'] ?></td>
                            <td translate="no"><?= $h($it['updated']) ?></td>
                            <td translate="no"><?= $h($it['author'] !== '' ? $it['author'] : '—') ?></td>
                            <td><button type="button" class="btn-open" data-sse-modal-open="el-fiche-<?= (int) $it['id'] ?>">Ouvrir</button></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php if ($editorPages > 1): ?>
                <nav class="el-pages" aria-label="Pages">
                    <?php if ($editorPage > 1): ?><a class="btn btn--ghost" href="<?= $h($editorPageUrl($editorPage - 1)) ?>">Précédent</a><?php endif; ?>
                    <span class="muted">Page <?= (int) $editorPage ?> sur <?= (int) $editorPages ?></span>
                    <?php if ($editorPage < $editorPages): ?><a class="btn btn--ghost" href="<?= $h($editorPageUrl($editorPage + 1)) ?>">Suivant</a><?php endif; ?>
                </nav>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</section>

<?php foreach ($editorItems as $it): ?>
    <dialog class="sse-modal el-fiche" id="el-fiche-<?= (int) $it['id'] ?>" aria-labelledby="el-fiche-<?= (int) $it['id'] ?>-t">
        <div class="sse-modal__card">
            <div class="sse-modal__head">
                <div>
                    <p class="sse-modal__kicker"><?= $h($it['type_label']) ?></p>
                    <h2 id="el-fiche-<?= (int) $it['id'] ?>-t" translate="no"><?= $h($it['title']) ?></h2>
                </div>
                <button type="button" class="sse-modal__close" data-sse-modal-close aria-label="Fermer">×</button>
            </div>
            <div class="sse-modal__body">
                <dl class="el-facts">
                    <div><dt><?= $h($it['marking_title']) ?></dt><dd><?= $h($it['marking']) ?></dd></div>
                    <?php if ($it['nature'] !== ''): ?><div><dt>Nature</dt><dd><?= $h($it['nature']) ?></dd></div><?php endif; ?>
                    <?php if ($it['delivery'] !== ''): ?><div><dt>Remise</dt><dd><?= $h($it['delivery']) ?></dd></div><?php endif; ?>
                    <div><dt>État</dt><dd><?= $h($it['state']) ?></dd></div>
                    <div><dt>Opération</dt><dd translate="no"><?= $h($it['op'] !== '' ? $it['op'] : '—') ?></dd></div>
                    <?php if ($it['link'] !== ''): ?><div><dt>Lié à</dt><dd translate="no"><?= $h($it['link']) ?></dd></div><?php endif; ?>
                    <div><dt>Version</dt><dd><?= (int) $it['version'] ?></dd></div>
                    <div><dt>Date</dt><dd translate="no"><?= $h($it['updated']) ?></dd></div>
                    <div><dt>Auteur</dt><dd translate="no"><?= $h($it['author'] !== '' ? $it['author'] : '—') ?></dd></div>
                    <div><dt>Référence</dt><dd translate="no">SSE-<?= $h($it['uid']) ?></dd></div>
                </dl>
                <p class="sse-modal__label">Contenu</p>
                <?php if (trim($it['body']) === ''): ?>
                    <p class="muted">Aucun texte.</p>
                <?php else: ?>
                    <div class="el-body" translate="no"><?= $h($it['body']) ?></div>
                <?php endif; ?>
                <p class="sse-modal__label">Historique</p>
                <?php if ($it['found_by'] === '' && $it['history'] === []): ?>
                    <p class="muted">Aucun historique.</p>
                <?php else: ?>
                    <ul class="el-history">
                        <?php if ($it['found_by'] !== ''): ?>
                            <li><strong>Trouvé par un joueur</strong><small translate="no"><?= $h($it['found_by'] . ' · ' . $it['found_when']) ?></small></li>
                        <?php endif; ?>
                        <?php foreach ($it['history'] as $line): ?>
                            <li translate="no"><?= $h($line['what']) ?><small><?= $h($line['who'] . ' · ' . $line['when']) ?></small></li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>
    </dialog>
<?php endforeach; ?>
<?php
$sseContent = ob_get_clean();
require __DIR__ . '/_layout.php';
