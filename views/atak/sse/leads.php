<?php
declare(strict_types=1);
ob_start();
$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
/**
 * Pistes : hypothèses de travail du bureau (ouverte, suivie, close). Une piste est une cible de la fenêtre « Rattacher » :
 * on y rattache les fiches, contrôles, personnes et documents qui la nourrissent.
 *
 * @var list<array<string, mixed>> $leads lignes de SseAttachDirectory (déjà filtrées par l'habilitation du lecteur)
 * @var bool $ready étape sse_attachments passée
 * @var bool $canWrite @var bool $canManage @var bool $alwaysAlign
 * @var list<array{value: string, abbr: string, label: string}> $levels
 */
$leads = is_array($leads ?? null) ? $leads : [];
$states = \App\Support\SseAttachCatalog::LEAD_STATUSES;
$q = (string) ($q ?? '');
$etat = (string) ($etat ?? '');
?>
<link rel="stylesheet" href="<?= $h(asset_url('assets/css/sse-rattacher.css')) ?>">
<div class="page-heading">
    <div>
        <h1>Pistes</h1>
        <p>Ce qui est à vérifier : une piste rassemble les pièces qui la nourrissent, jusqu’à ce qu’elle soit confirmée dans un dossier ou close.</p>
    </div>
    <?php if (!empty($canWrite)): ?>
        <button type="button" class="btn" data-ras-show="lead-new" aria-haspopup="dialog">Ouvrir une piste</button>
    <?php endif; ?>
</div>

<?php if (empty($ready)): ?>
    <div class="sse-note" role="note">Les pistes arrivent avec la mise à jour de la base (étape « sse_attachments »). Rien n’est perdu d’ici là : les rattachements à un dossier fonctionnent déjà.</div>
<?php else: ?>
    <form class="ras ras-tools" method="get" action="<?= $h(url('atak/sse/pistes')) ?>" role="search">
        <label class="ra-search"><span class="ra-sr">Rechercher une piste</span>
            <svg class="ra-ico" viewBox="0 0 24 24" width="18" height="18" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><path d="M11 18a7 7 0 1 0 0-14 7 7 0 0 0 0 14z"/><path d="M20 20l-4-4"/></svg>
            <input type="search" name="q" value="<?= $h($q) ?>" placeholder="Référence ou titre" autocomplete="off">
        </label>
        <label class="ra-select"><span class="ra-sr">État</span>
            <select name="etat">
                <option value="">Tous les états</option>
                <?php foreach ($states as $k => $label): ?><option value="<?= $h($k) ?>"<?= $etat === $k ? ' selected' : '' ?>><?= $h($label) ?></option><?php endforeach; ?>
            </select>
        </label>
        <button type="submit" class="ra-btn">Filtrer</button>
    </form>

    <?php if ($leads === []): ?>
        <div class="ras ras-empty">
            <strong><?= $q !== '' || $etat !== '' ? 'Aucune piste ne correspond' : 'Aucune piste ouverte' ?></strong>
            <p><?= $q !== '' || $etat !== '' ? 'Élargissez la recherche ou retirez le filtre d’état.' : 'Ouvrez une piste pour y rattacher les fiches, contrôles et documents qui la nourrissent.' ?></p>
        </div>
    <?php else: ?>
        <ul class="ras ras-list">
            <?php foreach ($leads as $l): ?>
                <li class="ras-item">
                    <div class="ras-item__main">
                        <p class="ras-item__top">
                            <a class="ra-mono" href="<?= $h($l['href']) ?>" translate="no"><?= $h($l['ref']) ?></a>
                            <strong translate="no"><?= $h($l['title']) ?></strong>
                        </p>
                        <?php if ((string) $l['excerpt'] !== ''): ?><p class="ras-item__why" translate="no"><?= $h($l['excerpt']) ?></p><?php endif; ?>
                        <p class="ras-item__meta">
                            <span class="ra-state"><span class="ra-dot ra-dot--<?= $h($l['tone']) ?>"></span><?= $h($l['status_label']) ?></span>
                            <?php if ((string) $l['owner'] !== ''): ?><span>Responsable : <span translate="no"><?= $h($l['owner']) ?></span></span><?php endif; ?>
                            <span><?= $h($l['date']) ?></span>
                            <span><?= (int) $l['count'] ?> élément(s) rattaché(s)</span>
                        </p>
                    </div>
                    <div class="ras-item__side">
                        <?php if ((string) $l['level_abbr'] !== ''): ?><span class="ra-level ra-level--<?= $h($l['level']) ?>" title="<?= $h($l['level_label']) ?>"><?= $h($l['level_abbr']) ?></span><?php endif; ?>
                        <a class="ra-btn ra-btn--ghost ra-btn--sm" href="<?= $h($l['href']) ?>">Ouvrir</a>
                    </div>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>

    <?php if (!empty($canManage)): ?>
        <form class="ras ras-proposals" method="post" action="<?= $h(url('atak/sse/rattacher/reglage')) ?>">
            <?= \App\Core\Csrf::field() ?>
            <input type="hidden" name="back" value="atak/sse/pistes">
            <p><strong>Réglage de la communauté</strong> : toujours aligner à la hausse au rattachement. <?= !empty($alwaysAlign) ? 'Activé : toute pièce rattachée prend le niveau de sa cible quand il est plus élevé.' : 'Désactivé : l’alignement se coche rattachement par rattachement.' ?></p>
            <input type="hidden" name="always_align" value="<?= !empty($alwaysAlign) ? '0' : '1' ?>">
            <button type="submit" class="ra-btn ra-btn--sm"><?= !empty($alwaysAlign) ? 'Désactiver' : 'Activer' ?></button>
        </form>
    <?php endif; ?>

    <?php if (!empty($canWrite)): ?>
        <dialog class="ras ras-dialog" id="lead-new" aria-labelledby="lead-new-t">
            <form method="post" action="<?= $h(url('atak/sse/pistes')) ?>">
                <?= \App\Core\Csrf::field() ?>
                <h2 id="lead-new-t">Ouvrir une piste</h2>
                <p class="ra-lead">Une hypothèse à vérifier, en une phrase. Vous en êtes le responsable.</p>
                <label class="ra-field">Titre<input type="text" name="title" maxlength="200" minlength="3" required autocomplete="off"></label>
                <label class="ra-field">Description<textarea name="description" rows="3" maxlength="2000"></textarea></label>
                <label class="ra-field">Classification
                    <select name="classification">
                        <?php foreach ((array) ($levels ?? []) as $lv): ?><option value="<?= $h($lv['value']) ?>"<?= $lv['value'] === 'encadrement' ? ' selected' : '' ?>><?= $h($lv['abbr'] . ' · ' . $lv['label']) ?></option><?php endforeach; ?>
                    </select>
                </label>
                <div class="ras-dialog__actions">
                    <button type="button" class="ra-btn ra-btn--ghost" data-ras-close>Annuler</button>
                    <button type="submit" class="ra-btn ra-btn--primary">Ouvrir la piste</button>
                </div>
            </form>
        </dialog>
    <?php endif; ?>
<?php endif; ?>
<?php \App\Services\Sse\Attach\SseAttachWindow::render(); ?>
<?php
$sseContent = ob_get_clean();
require __DIR__ . '/_layout.php';
