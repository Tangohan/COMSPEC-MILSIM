<?php
declare(strict_types=1);

/**
 * Widget « Liaisons » : chemin de liaison complet et liens directs d'un élément du portail SSE.
 * Inclus en UNE ligne par chaque page d'élément : \App\Services\Sse\Links\SseLinksWidget::render('person', $pid);
 *
 * @var array<string, mixed> $sl données préparées par SseLinksWidget::data()
 */
$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$g = $sl['g'];
$nodes = (array) $g['nodes'];
$edges = (array) $g['edges'];
$rootKey = (string) $g['root'];
$root = $sl['root'];
$uid = (string) $sl['uid'];
$total = (int) ($g['counts']['total'] ?? 0);
$direct = (int) ($g['counts']['direct'] ?? 0);

/** Icônes : tracés 24 px, un symbole par type d'élément. */
$paths = [
    'user' => 'M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8z|M4 21a8 8 0 0 1 16 0',
    'id' => 'M3 6h18v12H3z|M7 10h4|M7 14h3|M15 10h2|M14 14h4',
    'doc' => 'M6 3h9l4 4v14H6z|M9 13h7|M9 17h7',
    'check' => 'M12 3l8 4v5c0 5-3.5 8-8 9-4.5-1-8-4-8-9V7z|M9 12l2 2 4-4',
    'finger' => 'M7 15a5 5 0 0 1 10 0|M5 12a7 7 0 0 1 14 0|M12 10v8|M9 18v2',
    'alert' => 'M12 3l10 18H2z|M12 10v5|M12 18h.01',
    'pdf' => 'M6 3h9l4 4v14H6z|M9 15h6|M9 18h4',
    'photo' => 'M3 6h18v13H3z|M3 16l5-5 4 4 3-3 6 6|M16 10h.01',
    'tag' => 'M3 12V3h9l9 9-9 9z|M7.5 7.5h.01',
    'flag' => 'M5 21V4|M5 4h12l-2 4 2 4H5',
    'folder' => 'M3 6h6l2 2h10v11H3z',
    'eye' => 'M2 12s4-7 10-7 10 7 10 7-4 7-10 7S2 12 2 12z|M12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6z',
    'note' => 'M5 3h14v18H5z|M9 8h6|M9 12h6|M9 16h3',
    'pin' => 'M12 21s7-6 7-12a7 7 0 1 0-14 0c0 6 7 12 7 12z|M12 11a2 2 0 1 0 0-4 2 2 0 0 0 0 4z',
    'signal' => 'M4 18a8 8 0 0 1 16 0|M8 18a4 4 0 0 1 8 0|M12 18h.01',
    'jnet' => 'M4 5h16v11H9l-5 4z|M8 9h8|M8 12h5',
    'lock' => 'M6 11h12v9H6z|M8 11V8a4 4 0 0 1 8 0v3',
    'link' => 'M10 14a4 4 0 0 0 5.7 0l3-3a4 4 0 0 0-5.7-5.7l-1 1|M14 10a4 4 0 0 0-5.7 0l-3 3a4 4 0 0 0 5.7 5.7l1-1',
    'close' => 'M6 6l12 12|M18 6L6 18',
    'copy' => 'M9 9h11v11H9z|M5 15V4h11',
    'plus' => 'M12 5v14|M5 12h14',
    'trash' => 'M4 7h16|M9 7V4h6v3|M6 7l1 13h10l1-13',
    'arrow' => 'M5 12h14|M13 6l6 6-6 6',
];
// Les tracés sont déclarés une seule fois (symboles) : chaque icône du chemin n'est qu'un renvoi, la page reste légère.
$sprite = '<svg width="0" height="0" style="position:absolute" aria-hidden="true" focusable="false"><defs>';
foreach ($paths as $name => $d) {
    $sprite .= '<symbol id="' . $h($uid) . '-i-' . $name . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">';
    foreach (explode('|', $d) as $p) {
        $sprite .= '<path d="' . $p . '"/>';
    }
    $sprite .= '</symbol>';
}
$sprite .= '</defs></svg>';
$ico = static function (string $key, string $cls = 'sl-i') use ($paths, $uid, $h): string {
    $name = isset($paths[$key]) ? $key : 'tag';

    return '<svg class="' . $cls . '" width="18" height="18" aria-hidden="true" focusable="false"><use href="#' . $h($uid) . '-i-' . $name . '"/></svg>';
};
$when = static function (string $d): string {
    $t = $d !== '' ? strtotime($d) : false;

    return $t !== false ? date('d/m/Y', $t) : '';
};
/** Maillon : phrase « <relation> <parent> » vue depuis l'enfant (le lien est stocké dans un sens, lu dans les deux). */
$phrase = static function (array $e, string $child): string {
    return $e['from'] === $child ? (string) $e['label'] : (string) $e['inverse'];
};
$badge = static function (array $e) use ($h): string {
    return match ((string) $e['origin']) {
        'cerbere' => '<span class="sl-badge sl-badge--cerbere" title="Traitement automatique du système">Cerbère</span>',
        'manual' => '<span class="sl-badge sl-badge--manual" title="Lien posé par une personne">Manuel</span>',
        'derived' => '<span class="sl-badge sl-badge--derived" title="Lien déduit des données : le motif n’a pas été enregistré">Déduit</span>',
        default => '',
    };
};
$back = (string) $sl['back'];
$csrf = (string) $sl['csrf'];
$urls = (array) $sl['urls'];
$chains = array_values(array_map('strval', (array) ($g['chains'] ?? [])));
$chainText = $chains !== [] ? implode("\n", $chains) : (string) ($root['ref'] ?: $root['title']);
$nearest = [];
foreach ((array) ($g['direct'] ?? []) as $items) {
    foreach ($items as $it) {
        $nearest[] = $it;
    }
}
usort($nearest, static fn (array $a, array $b): int => [(string) ($edges[$b['edge']]['at'] ?? ''), $b['node']] <=> [(string) ($edges[$a['edge']]['at'] ?? ''), $a['node']]);
$mini = array_slice($nearest, 0, 3);
$sseStyles = '<link rel="stylesheet" href="' . $h(asset_url('assets/css/sse-links.css')) . '">';
$sseScript = '<script defer src="' . $h(asset_url('assets/js/sse-links.js')) . '"></script>';
?>
<?= $sseStyles ?>
<?= $sprite ?>
<section class="sl" data-sl id="<?= $h($uid) ?>" data-sl-ref="<?= $h($sl['ref']) ?>" data-sl-search="<?= $h($urls['search']) ?>" data-sl-cite="<?= $h($sl['cite']) ?>" data-sl-url="<?= $h($urls['self']) ?>" data-sl-text="<?= str_replace("\n", '&#10;', $h($chainText)) ?>" aria-label="Liaisons de cet élément">
    <div class="sl-bar">
        <button type="button" class="sl-open" data-sl-open aria-haspopup="dialog" aria-controls="<?= $h($uid) ?>-drawer">
            <?= $ico('link') ?>
            <span>Liaisons (<?= $total ?>)</span>
        </button>
        <?php if ($mini !== []): ?>
            <ol class="sl-mini" aria-label="Éléments les plus proches">
                <?php foreach ($mini as $it): $n = $nodes[$it['node']]; ?>
                    <li class="sl-mini__item<?= $n['restricted'] ? ' is-restricted' : '' ?>">
                        <?php if ($n['href'] !== ''): ?>
                            <a href="<?= $h($n['href']) ?>" class="sl-mini__a"><?= $ico((string) $n['icon']) ?><span class="sl-mini__t"><?= $h($n['type_label']) ?></span> <b><?= $h($n['ref'] !== '' ? $n['ref'] : $n['title']) ?></b></a>
                        <?php else: ?>
                            <span class="sl-mini__a"><?= $ico((string) $n['icon']) ?><span><?= $h($n['title']) ?></span></span>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
                <?php if ($direct > count($mini)): ?>
                    <li class="sl-mini__more">+ <?= $direct - count($mini) ?></li>
                <?php endif; ?>
            </ol>
        <?php else: ?>
            <p class="sl-bar__none">Aucune liaison enregistrée pour le moment.</p>
        <?php endif; ?>
    </div>

    <dialog class="sl-drawer" id="<?= $h($uid) ?>-drawer" aria-labelledby="<?= $h($uid) ?>-title" data-sl-drawer>
        <div class="sl-drawer__panel">
            <header class="sl-drawer__head">
                <div>
                    <h2 id="<?= $h($uid) ?>-title" tabindex="-1" data-sl-title>Liaisons : <?= $h($root['ref'] !== '' ? $root['ref'] : $root['title']) ?></h2>
                    <p class="sl-desc">D’où vient cet élément, ce qui en découle, et pourquoi chaque lien existe.</p>
                </div>
                <button type="button" class="sl-iconbtn" data-sl-close aria-label="Fermer le panneau"><?= $ico('close') ?></button>
            </header>

            <?php if ($sl['degraded']): ?>
                <p class="sl-note sl-note--warn" role="note">Mode dégradé : la table des liens enregistrés n’est pas encore créée sur ce site (étape « sse_entity_links »). Les liens sont déduits des données existantes, sans motif enregistré, et on ne peut pas encore en ajouter à la main.</p>
            <?php endif; ?>
            <?php if (!empty($g['truncated'])): ?>
                <p class="sl-note" role="note">Le chemin est très étendu : seuls les <?= (int) \App\Support\SseLinkCatalog::MAX_NODES ?> éléments les plus proches sont affichés.</p>
            <?php endif; ?>

            <div class="sl-tabs" role="tablist" aria-label="Affichage des liaisons">
                <button type="button" role="tab" id="<?= $h($uid) ?>-t-path" aria-controls="<?= $h($uid) ?>-p-path" aria-selected="true" data-sl-tab="path">Chemin complet</button>
                <button type="button" role="tab" id="<?= $h($uid) ?>-t-direct" aria-controls="<?= $h($uid) ?>-p-direct" aria-selected="false" tabindex="-1" data-sl-tab="direct">Liens directs (<?= $direct ?>)</button>
            </div>

            <div class="sl-drawer__body">
                <div role="tabpanel" id="<?= $h($uid) ?>-p-path" aria-labelledby="<?= $h($uid) ?>-t-path" data-sl-panel="path">
                    <p class="sl-desc">En partant de cet élément : chaque maillon dit pourquoi il est relié au précédent, du plus ancien au plus récent à chaque niveau.</p>
                    <?php if ($total === 0): ?>
                        <div class="sl-empty">
                            <p><b>Rien n’est encore relié à cet élément.</b></p>
                            <p>Les contrôles, papiers, dossiers et documents qui s’y rattachent apparaîtront ici dès qu’ils existent. Vous pouvez aussi ajouter un lien à la main.</p>
                        </div>
                        <ol class="sl-thread"><li class="sl-step sl-step--root" style="--sl-depth:0"><div class="sl-node sl-node--here"><?= $ico((string) $root['icon']) ?><div class="sl-node__t"><span class="sl-node__type"><?= $h($root['type_label']) ?></span> <b><?= $h($root['ref']) ?></b><span class="sl-node__title"><?= $h($root['title']) ?></span></div><span class="sl-here">Vous êtes ici</span></div></li></ol>
                    <?php else: ?>
                        <ol class="sl-thread">
                            <?php foreach ((array) $g['thread'] as $row): $n = $nodes[$row['key']]; $e = $row['edge'] !== null ? ($edges[$row['edge']] ?? null) : null; $lvl = (int) $row['level']; ?>
                                <li class="sl-step<?= $lvl === 0 ? ' sl-step--root' : '' ?><?= $n['restricted'] ? ' is-restricted' : '' ?>" style="--sl-depth:<?= $lvl ?>" data-sl-type="<?= $h($n['type']) ?>">
                                    <?php if ($e !== null && $row['parent'] !== null): $pn = $nodes[$row['parent']]; ?>
                                        <div class="sl-link">
                                            <p class="sl-link__rel"><?= $ico('arrow', 'sl-i sl-i--s') ?> <?= $n['restricted'] ? 'Lié à' : $h($phrase($e, $row['key'])) ?> <b><?= $h($pn['restricted'] ? 'un élément restreint' : ($pn['ref'] !== '' ? $pn['ref'] : $pn['title'])) ?></b> <?= $badge($e) ?></p>
                                            <?php if ($e['reason'] !== ''): ?>
                                                <p class="sl-link__why"><?= $h($e['reason']) ?></p>
                                            <?php elseif ($e['origin'] !== 'hidden'): ?>
                                                <p class="sl-link__why sl-link__why--none">Motif non enregistré.</p>
                                            <?php endif; ?>
                                            <?php if ($e['origin'] !== 'hidden'): ?>
                                                <p class="sl-link__meta">
                                                    <?php $meta = array_values(array_filter([$when((string) $e['at']), $e['actor'] !== '' ? ($e['origin'] === 'cerbere' && $e['rule'] === null ? 'Cerbère' : $e['actor']) : '', $e['source'] !== '' ? 'source : ' . $e['source'] : ''])); ?>
                                                    <?= $h(implode(' · ', $meta)) ?>
                                                </p>
                                            <?php endif; ?>
                                            <?php if ($e['removable'] && $e['link_id'] !== null && $sl['canWrite']): ?>
                                                <form method="post" action="<?= $h($urls['removeBase'] . '/' . (int) $e['link_id'] . '/retirer') ?>" class="sl-link__rm" data-confirm="Retirer ce lien posé à la main ?">
                                                    <input type="hidden" name="_csrf_token" value="<?= $h($csrf) ?>">
                                                    <input type="hidden" name="back" value="<?= $h($back) ?>">
                                                    <button type="submit" class="sl-linkbtn"><?= $ico('trash', 'sl-i sl-i--s') ?> Retirer ce lien</button>
                                                </form>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                    <?php if ($n['restricted']): ?>
                                        <div class="sl-node sl-node--restricted"><?= $ico('lock') ?><div class="sl-node__t"><b>Élément restreint</b><span class="sl-node__title">Vous n’avez pas le droit de lire cet élément.</span></div></div>
                                    <?php else: ?>
                                        <?php $tag = $n['href'] !== '' && $lvl > 0 ? 'a' : 'div'; ?>
                                        <<?= $tag ?> class="sl-node<?= $lvl === 0 ? ' sl-node--here' : '' ?>"<?= $tag === 'a' ? ' href="' . $h($n['href']) . '"' : '' ?>>
                                            <?= $ico((string) $n['icon']) ?>
                                            <span class="sl-node__t">
                                                <span class="sl-node__type"><?= $h($n['type_label']) ?></span>
                                                <?php if ($n['ref'] !== ''): ?><b><?= $h($n['ref']) ?></b><?php endif; ?>
                                                <span class="sl-node__title"><?= $h($n['title']) ?></span>
                                                <?php if ($n['summary'] !== '' || $n['date'] !== ''): ?><small><?= $h(implode(' · ', array_filter([$n['summary'], $when((string) $n['date'])]))) ?></small><?php endif; ?>
                                            </span>
                                            <?php if ($lvl === 0): ?><span class="sl-here">Vous êtes ici</span><?php endif; ?>
                                        </<?= $tag ?>>
                                        <?php if (!empty($row['also'])): ?>
                                            <ul class="sl-also" aria-label="Autres liens de cet élément">
                                                <?php foreach ($row['also'] as $al): $ae = $edges[$al['edge']]; $on = $nodes[$al['other']]; ?>
                                                    <li><?= $ico('arrow', 'sl-i sl-i--s') ?> <?= $h($phrase($ae, $row['key'])) ?> <b><?= $h($on['restricted'] ? 'un élément restreint' : ($on['ref'] !== '' ? $on['ref'] : $on['title'])) ?></b> <?= $badge($ae) ?><?= $ae['reason'] !== '' ? ' <span class="sl-also__why">' . $h($ae['reason']) . '</span>' : '' ?></li>
                                                <?php endforeach; ?>
                                            </ul>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </li>
                            <?php endforeach; ?>
                        </ol>
                    <?php endif; ?>
                </div>

                <div role="tabpanel" id="<?= $h($uid) ?>-p-direct" aria-labelledby="<?= $h($uid) ?>-t-direct" data-sl-panel="direct" hidden>
                    <p class="sl-desc">Ce qui est relié directement à cet élément, regroupé par type, du plus récent au plus ancien.</p>
                    <?php if ($direct === 0): ?>
                        <div class="sl-empty"><p>Aucun lien direct pour le moment.</p></div>
                    <?php else: ?>
                        <?php foreach ((array) $g['direct'] as $type => $items): ?>
                            <section class="sl-group" aria-label="<?= $h($type === 'restricted' ? 'Éléments restreints' : \App\Support\SseLinkCatalog::typeLabel((string) $type)) ?>">
                                <h3 class="sl-group__h"><?= $h($type === 'restricted' ? 'Éléments restreints' : \App\Support\SseLinkCatalog::typeLabel((string) $type)) ?> <span class="sl-count"><?= count($items) ?></span></h3>
                                <ul class="sl-list">
                                    <?php foreach ($items as $it): $n = $nodes[$it['node']]; $e = $edges[$it['edge']]; ?>
                                        <li class="sl-row<?= $n['restricted'] ? ' is-restricted' : '' ?>">
                                            <?php if ($n['restricted']): ?>
                                                <span class="sl-row__main"><?= $ico('lock') ?><span class="sl-row__t"><b>Élément restreint</b></span></span>
                                            <?php else: ?>
                                                <a class="sl-row__main" href="<?= $h($n['href']) ?>"><?= $ico((string) $n['icon']) ?><span class="sl-row__t"><b><?= $h($n['ref'] !== '' ? $n['ref'] : $n['title']) ?></b><span><?= $h($n['title']) ?></span></span></a>
                                                <span class="sl-row__why"><?= $h($it['forward'] ? $e['label'] : $e['inverse']) ?> <?= $badge($e) ?></span>
                                                <?php if ($e['reason'] !== ''): ?><span class="sl-row__reason"><?= $h($e['reason']) ?></span><?php endif; ?>
                                                <span class="sl-row__meta"><?= $h(implode(' · ', array_filter([$when((string) $e['at']), $e['actor']]))) ?></span>
                                            <?php endif; ?>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            </section>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <footer class="sl-drawer__foot">
                <button type="button" class="sl-btn" data-sl-copy="text"><?= $ico('copy') ?> Copier le chemin</button>
                <button type="button" class="sl-btn" data-sl-copy="url"><?= $ico('link') ?> Copier le lien</button>
                <?php if ($sl['cite'] !== ''): ?>
                    <button type="button" class="sl-btn" data-sl-copy="cite"><?= $ico('jnet') ?> Insérer dans un compte rendu</button>
                <?php endif; ?>
                <?php if ($sl['canWrite']): ?>
                    <button type="button" class="sl-btn sl-btn--primary" data-sl-add-open><?= $ico('plus') ?> Ajouter un lien</button>
                <?php else: ?>
                    <button type="button" class="sl-btn" disabled title="<?= $h($sl['degraded'] ? 'La table des liens enregistrés n’est pas encore créée.' : ($sl['guest'] ? 'Une session invitée ne peut pas poser de lien.' : 'Droit manquant : écrire dans la section de cet élément.')) ?>"><?= $ico('plus') ?> Ajouter un lien</button>
                <?php endif; ?>
            </footer>
            <p class="sl-hint">La citation JNET ne garde que la référence : chaque lecteur voit le chemin avec ses propres droits. <a class="sl-linkbtn" href="<?= $h($urls['cerberes']) ?>">Que font les Cerbères ?</a></p>
        </div>
    </dialog>

    <?php if ($sl['canWrite']): ?>
        <dialog class="sl-modal" id="<?= $h($uid) ?>-add" aria-labelledby="<?= $h($uid) ?>-add-t" data-sl-modal>
            <form method="post" action="<?= $h($urls['add']) ?>" class="sl-form" data-sl-form>
                <input type="hidden" name="_csrf_token" value="<?= $h($csrf) ?>">
                <input type="hidden" name="back" value="<?= $h($back) ?>">
                <input type="hidden" name="from" value="<?= $h($sl['ref']) ?>">
                <header class="sl-modal__head">
                    <div>
                        <h2 id="<?= $h($uid) ?>-add-t">Ajouter un lien</h2>
                        <p class="sl-desc">Relier <b><?= $h($root['ref'] !== '' ? $root['ref'] : $root['title']) ?></b> à un autre élément, avec le motif du lien.</p>
                    </div>
                    <button type="button" class="sl-iconbtn" data-sl-modal-close aria-label="Fermer la fenêtre"><?= $ico('close') ?></button>
                </header>
                <div class="sl-form__row">
                    <label class="sl-field">
                        <span>Type d’élément</span>
                        <select data-sl-type-filter>
                            <option value="">Tous les types</option>
                            <?php foreach ((array) $sl['types'] as $k => $label): ?>
                                <option value="<?= $h($k) ?>"><?= $h($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label class="sl-field sl-field--grow">
                        <span>Chercher par référence ou par nom</span>
                        <input type="search" data-sl-q autocomplete="off" maxlength="80" placeholder="CTRL-3, IDT-000001, Karim…">
                    </label>
                </div>
                <ul class="sl-results" data-sl-results role="listbox" aria-label="Résultats de la recherche" hidden></ul>
                <p class="sl-desc" data-sl-hint>Tapez au moins deux caractères : seuls les éléments que vous avez le droit de lire sont proposés.</p>
                <label class="sl-field">
                    <span>Élément choisi</span>
                    <input type="text" name="to" data-sl-to required maxlength="40" pattern="[a-z_]+:[1-9][0-9]{0,9}" placeholder="choisissez dans les résultats (type:numéro)" autocomplete="off">
                </label>
                <div class="sl-form__row">
                    <label class="sl-field">
                        <span>Relation</span>
                        <select name="relation">
                            <?php foreach ((array) $sl['relations'] as $k => $label): ?>
                                <option value="<?= $h($k) ?>"><?= $h($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                </div>
                <label class="sl-field">
                    <span>Motif du lien (obligatoire)</span>
                    <textarea name="reason" required minlength="5" maxlength="255" rows="3" placeholder="Pourquoi ces deux éléments sont-ils liés ? Ce motif sera lu par les autres analystes."></textarea>
                </label>
                <footer class="sl-modal__foot">
                    <button type="button" class="sl-btn" data-sl-modal-close>Annuler</button>
                    <button type="submit" class="sl-btn sl-btn--primary">Enregistrer le lien</button>
                </footer>
            </form>
        </dialog>
    <?php endif; ?>

    <span hidden data-sl-msg="copied">Copié dans le presse-papiers.</span>
    <span hidden data-sl-msg="copied-cite">Citation copiée : dans un compte rendu JNET, collez-la dans le texte.</span>
    <span hidden data-sl-msg="copy-failed">Copie impossible : sélectionnez le texte à la main.</span>
    <span hidden data-sl-msg="no-result">Aucun résultat parmi ce que vous pouvez lire.</span>
    <span hidden data-sl-msg="searching">Recherche en cours…</span>
    <span hidden data-sl-msg="pick">Choisir cet élément</span>
</section>
<?= $sseScript ?>
