<?php
declare(strict_types=1);

/**
 * Section « Éléments rattachés » d'une cible du bureau SSE (dossier, dossier d'intérêt, piste, investigation, personne,
 * identité, site...) : ce qui y a été rattaché depuis la fenêtre « Rattacher », avec le libellé donné, la nature, le motif,
 * les étiquettes, l'auteur et la date ; actions ouvrir, modifier, déplacer, détacher (avec motif).
 * Incluse en UNE ligne : \App\Services\Sse\Attach\SseAttachWindow::section('case', $id);
 *
 * Une pièce que le lecteur ne peut pas lire est « Élément restreint » : ni nom, ni référence, ni motif, ni auteur.
 *
 * @var array<string, mixed> $as données de SseAttachService::attached() + back, csrf, base, uid, type, id
 */
$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$items = (array) $as['items'];
$visible = array_values(array_filter($items, static fn (array $i): bool => empty($i['restricted'])));
$restricted = count($items) - count($visible);
$target = (array) $as['target'];
$uid = (string) $as['uid'];
$fold = static fn (string $s): string => \App\Support\SseAttachCatalog::fold($s);
$proposals = (array) $as['proposals'];
$latest = $as['latest'] ?? null;
$svg = static fn (string $d, int $s = 18): string => '<svg class="ra-ico" viewBox="0 0 24 24" width="' . $s . '" height="' . $s . '" aria-hidden="true" focusable="false" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="' . str_replace('|', '"/><path d="', $d) . '"/></svg>';
$slim = !empty($as['slim']) || $items === [];
$linkIcon = 'M10 14a4 4 0 0 0 5.7 0l3-3a4 4 0 0 0-5.7-5.7l-1 1|M14 10a4 4 0 0 0-5.7 0l-3 3a4 4 0 0 0 5.7 5.7l1-1';
?>
<link rel="stylesheet" href="<?= $h(asset_url('assets/css/sse-rattacher.css')) ?>">
<section class="ras<?= $slim ? ' ras--slim' : '' ?>" data-ras data-ras-base="<?= $h($as['base']) ?>" id="<?= $h($uid) ?>" aria-labelledby="<?= $h($uid) ?>-t">
    <?php if ($slim): ?>
    <header class="ras-slim">
        <h2 id="<?= $h($uid) ?>-t">Éléments rattachés</h2>
        <span class="ras-slim__n"><?= $items === [] ? 'Aucun pour le moment' : count($items) . (count($items) > 1 ? ' éléments' : ' élément') ?></span>
        <?php if (!empty($as['source_ok']) && !empty($as['can_attach'])): ?>
            <button type="button" class="ra-btn ra-btn--sm" data-ra-open data-ra-pieces="<?= $h($as['type'] . ':' . $as['id']) ?>" aria-haspopup="dialog"><?= $svg($linkIcon, 16) ?><span>Rattacher à…</span></button>
        <?php endif; ?>
    </header>
    <?php else: ?>
    <header class="ras-band">
        <span class="ras-band__glow" aria-hidden="true"></span><span class="ras-band__veil" aria-hidden="true"></span>
        <div class="ras-band__body">
            <div class="ras-band__id">
                <div class="ras-band__title">
                    <h2 id="<?= $h($uid) ?>-t">Éléments rattachés</h2>
                    <?php if ((string) $target['ref'] !== ''): ?><span class="ras-band__index" translate="no">/ <?= $h($target['ref']) ?></span><?php endif; ?>
                </div>
                <p class="ras-band__lead">Les éléments liés à cette fiche, avec le motif, la nature et l’auteur de chaque rattachement.</p>
            </div>
            <div class="ras-band__meta">
                <?php if (is_array($latest) && (string) $latest['date'] !== ''): ?>
                    <span class="ras-band__pill">Dernier ajout : <b translate="no"><?= $h(trim((string) $latest['author']) !== '' ? $latest['author'] . ' · ' : '') ?></b><?= $h($latest['date']) ?></span>
                <?php endif; ?>
                <span class="ras-band__actions">
                    <?php if (!empty($as['source_ok']) && !empty($as['can_attach'])): ?>
                        <button type="button" class="ra-btn ra-btn--primary" data-ra-open data-ra-pieces="<?= $h($as['type'] . ':' . $as['id']) ?>" aria-haspopup="dialog"><?= $svg($linkIcon) ?><span>Rattacher à…</span></button>
                    <?php endif; ?>
                    <button type="button" class="ra-btn ra-btn--ghost" data-ras-liaisons hidden>Voir les liaisons</button>
                </span>
                <span class="ras-band__figure"><b><?= count($items) ?></b><small><?= count($items) > 1 ? 'éléments' : 'élément' ?></small></span>
            </div>
        </div>
    </header>
    <?php endif; ?>

    <?php if (empty($as['ready'])): ?>
        <p class="ras-note">Les rattachements enrichis (libellé, étiquettes, motif) arrivent avec la mise à jour de la base. D’ici là, les rattachements historiques restent visibles dans le widget Liaisons.</p>
    <?php endif; ?>

    <?php if ($proposals !== []): ?>
        <div class="ras-proposals" role="region" aria-label="Propositions de Cerbère-Classification">
            <p><strong>Cerbère-Classification</strong> : cette cible est aujourd’hui plus classifiée que <?= count($proposals) ?> pièce(s) qui avaient demandé l’alignement. Rien n’est changé sans votre validation.</p>
            <ul>
                <?php foreach ($proposals as $p): ?>
                    <li>
                        <span><b translate="no"><?= $h($p['source']) ?></b> <span translate="no"><?= $h($p['source_title']) ?></span> : <?= $h($p['from']) ?> → <?= $h($p['to']) ?></span>
                        <?php if (!empty($p['can_apply'])): ?>
                            <form method="post" action="<?= $h($as['base'] . '/aligner') ?>">
                                <input type="hidden" name="_csrf_token" value="<?= $h($as['csrf']) ?>"><input type="hidden" name="back" value="<?= $h($as['back']) ?>"><input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
                                <button type="submit" class="ra-btn ra-btn--sm">Relever à <?= $h($p['to']) ?></button>
                            </form>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <?php if ($items === []): ?>
    <?php else: ?>
        <div class="ras-tools" id="<?= $h($uid) ?>-list">
            <label class="ra-search"><span class="ra-sr">Filtrer les éléments rattachés</span>
                <?= $svg('M11 18a7 7 0 1 0 0-14 7 7 0 0 0 0 14z|M20 20l-4-4') ?>
                <input type="search" data-ras-q placeholder="Référence, libellé, motif" autocomplete="off">
            </label>
            <label class="ra-select"><span class="ra-sr">Type</span><select data-ras-filter="type"><option value="">Tous les types</option><?php foreach ((array) $as['types'] as $k => $label): ?><option value="<?= $h($k) ?>"><?= $h($label) ?></option><?php endforeach; ?></select></label>
            <label class="ra-select"><span class="ra-sr">Nature</span><select data-ras-filter="nature"><option value="">Toutes les natures</option><?php foreach ((array) $as['natures'] as $k => $label): ?><option value="<?= $h($k) ?>"><?= $h($label) ?></option><?php endforeach; ?></select></label>
            <?php if ((array) $as['tags'] !== []): ?>
                <label class="ra-select"><span class="ra-sr">Étiquette</span><select data-ras-filter="tag"><option value="">Toutes les étiquettes</option><?php foreach ((array) $as['tags'] as $t): ?><option value="<?= (int) $t['id'] ?>" translate="no"><?= $h($t['name']) ?></option><?php endforeach; ?></select></label>
            <?php endif; ?>
            <label class="ra-select"><span class="ra-sr">Trier</span><select data-ras-sort><option value="recent">Plus récents</option><option value="alpha">Alphabétique</option><option value="type">Par type</option></select></label>
        </div>
        <ul class="ras-list" data-ras-list>
            <?php foreach ($visible as $it):
                $shown = empty($it['outgoing']) && (string) $it['label'] !== '' ? (string) $it['label'] : (string) $it['title'];
                $tagIds = implode(' ', array_map(static fn (array $t): string => (string) $t['id'], (array) $it['tags']));
                $tagNames = implode(', ', array_map(static fn (array $t): string => (string) $t['name'], (array) $it['tags']));
                ?>
                <li class="ras-item" data-ras-item="<?= (int) $it['id'] ?>" data-type="<?= $h($it['type']) ?>" data-nature="<?= $h($it['nature']) ?>" data-tags="<?= $h($tagIds) ?>"
                    data-at="<?= $h($it['at']) ?>" data-title="<?= $h($fold($shown)) ?>" data-hay="<?= $h($fold($it['ref'] . ' ' . $shown . ' ' . $it['title'] . ' ' . $it['reason'] . ' ' . $tagNames)) ?>"
                    data-piece="<?= $h($it['key']) ?>" data-label="<?= $h($it['label']) ?>" data-reason="<?= $h($it['reason']) ?>" data-note="<?= $h($it['note']) ?>" data-tagnames="<?= $h($tagNames) ?>" data-ref="<?= $h($it['ref']) ?>">
                    <div class="ras-item__main">
                        <p class="ras-item__top">
                            <span class="ras-item__type"><?= $h($it['type_label']) ?></span>
                            <a class="ra-mono" href="<?= $h($it['href']) ?>" translate="no"><?= $h($it['ref'] !== '' ? $it['ref'] : $it['title']) ?></a>
                            <strong translate="no"><?= $h($shown) ?></strong>
                            <?php if (empty($it['outgoing']) && (string) $it['label'] !== '' && (string) $it['label'] !== (string) $it['title']): ?><span class="ras-item__orig" translate="no" title="Titre de la pièce">(<?= $h($it['title']) ?>)</span><?php endif; ?>
                        </p>
                        <p class="ras-item__why"><span class="ras-nature"><?= $h($it['nature_label']) ?></span> <span translate="no"><?= $h($it['reason']) ?></span></p>
                        <p class="ras-item__meta">
                            <?php foreach ((array) $it['tags'] as $t): ?><span class="ra-tag ra-tag--<?= $h($t['color']) ?>" translate="no"><?= $h($t['name']) ?></span><?php endforeach; ?>
                            <span><span translate="no"><?= $h($it['author'] !== '' ? $it['author'] . ' · ' : '') ?></span><?= $h($it['date']) ?></span>
                            <?php if (!empty($it['aligned'])): ?><span class="ras-item__aligned" title="L’alignement de confidentialité a été demandé pour cette pièce">Aligné sur la cible</span><?php endif; ?>
                        </p>
                    </div>
                    <div class="ras-item__side">
                        <?php if ((string) $it['level_abbr'] !== ''): ?><span class="ra-level ra-level--<?= $h($it['level']) ?>"><?= $h($it['level_abbr']) ?></span><?php endif; ?>
                        <a class="ra-btn ra-btn--ghost ra-btn--sm" href="<?= $h($it['href']) ?>">Ouvrir</a>
                        <?php if (!empty($it['can_edit']) && !empty($as['ready'])): ?>
                            <details class="ras-menu">
                                <summary class="ra-btn ra-btn--ghost ra-btn--sm" aria-label="Actions sur <?= $h($it['ref'] !== '' ? $it['ref'] : $shown) ?>">Actions</summary>
                                <div class="ras-menu__list">
                                    <button type="button" data-ras-dialog="<?= $h($uid) ?>-edit">Modifier le rattachement</button>
                                    <button type="button" data-ra-open data-ra-pieces="<?= $h($it['source_key'] ?? $it['key']) ?>" data-ra-move="<?= (int) $it['id'] ?>">Déplacer vers une autre cible</button>
                                    <button type="button" class="is-danger" data-ras-dialog="<?= $h($uid) ?>-detach">Détacher</button>
                                </div>
                            </details>
                        <?php endif; ?>
                    </div>
                </li>
            <?php endforeach; ?>
        </ul>
        <p class="ras-none" data-ras-none hidden>Aucun élément rattaché ne correspond à ces filtres.</p>
        <?php if ($restricted > 0): ?>
            <p class="ras-restricted"><?= $svg('M6 11h12v9H6z|M8 11V8a4 4 0 0 1 8 0v3', 16) ?> <?= $restricted ?> élément(s) restreint(s) : rattachés ici, mais au-dessus de votre habilitation ou hors de vos sections.</p>
        <?php endif; ?>

        <dialog class="ras-dialog" id="<?= $h($uid) ?>-edit" aria-labelledby="<?= $h($uid) ?>-edit-t">
            <form method="post" data-ras-form="modifier">
                <input type="hidden" name="_csrf_token" value="<?= $h($as['csrf']) ?>"><input type="hidden" name="back" value="<?= $h($as['back']) ?>">
                <h2 id="<?= $h($uid) ?>-edit-t">Modifier le rattachement <span class="ra-mono" data-ras-ref translate="no"></span></h2>
                <p class="ra-lead">La pièce elle-même ne change pas : seuls son nom dans cette cible, ses étiquettes et le motif sont modifiés.</p>
                <label class="ra-field">Nom du rattachement<input type="text" name="label" maxlength="160" autocomplete="off"></label>
                <label class="ra-field">Motif<input type="text" name="reason" maxlength="255" minlength="5" required autocomplete="off"></label>
                <label class="ra-field">Étiquettes<input type="text" name="tags" maxlength="300" autocomplete="off" placeholder="Séparées par des virgules"></label>
                <label class="ra-field">Note<textarea name="note" rows="2" maxlength="500"></textarea></label>
                <div class="ras-dialog__actions">
                    <button type="button" class="ra-btn ra-btn--ghost" data-ras-close>Annuler</button>
                    <button type="submit" class="ra-btn ra-btn--primary">Enregistrer</button>
                </div>
            </form>
        </dialog>
        <dialog class="ras-dialog" id="<?= $h($uid) ?>-detach" aria-labelledby="<?= $h($uid) ?>-detach-t">
            <form method="post" data-ras-form="detacher">
                <input type="hidden" name="_csrf_token" value="<?= $h($as['csrf']) ?>"><input type="hidden" name="back" value="<?= $h($as['back']) ?>">
                <h2 id="<?= $h($uid) ?>-detach-t">Détacher <span class="ra-mono" data-ras-ref translate="no"></span></h2>
                <p class="ra-lead">La pièce n’est pas supprimée et garde sa classification : seul son rattachement à cette cible est retiré, et le motif est consigné.</p>
                <label class="ra-field">Motif du détachement<input type="text" name="reason" maxlength="255" minlength="5" required autocomplete="off"></label>
                <div class="ras-dialog__actions">
                    <button type="button" class="ra-btn ra-btn--ghost" data-ras-close>Annuler</button>
                    <button type="submit" class="ra-btn ra-btn--danger">Détacher</button>
                </div>
            </form>
        </dialog>
    <?php endif; ?>
</section>
