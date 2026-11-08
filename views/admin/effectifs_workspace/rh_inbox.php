<?php
declare(strict_types=1);

/*
 * Effectifs > À traiter : les alertes RH rangées par urgence.
 * Un onglet par section (adresse propre : ?onglet=maintenant, ?onglet=surveiller, ?onglet=infos), des cartes
 * avec compteur et lien direct vers l'action, le détail des personnes dans une fenêtre.
 * Aucune adresse e-mail n'est affichée ici.
 */

require base_path('views/admin/effectifs_workspace/partials/rh_ui_helpers.php');

$inbox = is_array($rhActionInbox ?? null) ? $rhActionInbox : ['total' => 0, 'attention' => 0, 'sections' => []];
$sections = is_array($inbox['sections'] ?? null) ? $inbox['sections'] : [];
$activeId = (string) ($rhInboxActive ?? 'now');
if (!isset($sections[$activeId])) {
    $activeId = 'now';
}
$can = is_array($rhInboxCan ?? null) ? $rhInboxCan : [];
$total = (int) ($inbox['total'] ?? 0);
$attention = (int) ($inbox['attention'] ?? 0);

$tabUrl = static fn (string $slug): string => effectifs_workspace_url('a-traiter?onglet=' . $slug);
$daysSince = static function (string $raw): ?int {
    $raw = trim($raw);
    $ts = $raw !== '' ? strtotime($raw) : false;
    if ($ts === false) {
        return null;
    }

    return max(0, (int) floor((strtotime(date('Y-m-d')) - strtotime(date('Y-m-d', $ts))) / 86400));
};
/** « Demandé il y a N jours » : le nombre est écrit dans la phrase, pour que la traduction reste possible. */
$asked = static function (string $raw) use ($daysSince): void {
    $d = $daysSince($raw);
    if ($d === null) {
        return;
    }
    echo '<span>';
    if ($d === 0) {
        ?>Demandé aujourd’hui<?php
    } elseif ($d === 1) {
        ?>Demandé hier<?php
    } else {
        ?>Demandé il y a <?= $d ?> jours<?php
    }
    echo '</span>';
};
$toneLabel = ['danger' => 'Urgent', 'warn' => 'À faire', 'info' => 'À suivre'];
$dialogs = [];
?>
<div class="rh-ib" data-rh-ib>
    <header class="rh-ib__head">
        <div class="rh-ib__copy">
            <p class="eff-page-kicker">Bureau effectifs</p>
            <h2 class="eff-page-title">À traiter</h2>
            <p class="eff-page-lead">Toutes les alertes RH, rangées par urgence. Le système propose ; vous décidez.</p>
        </div>
        <div class="rh-ib__score <?= $total > 0 ? 'is-warn' : 'is-ok' ?>" role="status">
            <strong><?= $total ?></strong>
            <span><?= $total > 0 ? ($total > 1 ? 'points à traiter maintenant' : 'point à traiter maintenant') : 'Rien à traiter maintenant' ?></span>
            <?php if ($attention > $total): ?>
                <small><?= $attention - $total ?> de plus à surveiller</small>
            <?php endif; ?>
        </div>
    </header>

    <nav class="rh-ib__tabs" aria-label="Sections par urgence">
        <?php foreach ($sections as $id => $tabSection): ?>
            <?php $isActive = (string) $id === $activeId; $n = (int) ($tabSection['count'] ?? 0); ?>
            <a class="rh-ib__tab rh-ib__tab--<?= $h((string) $id) ?><?= $isActive ? ' is-active' : '' ?>" href="<?= $h($tabUrl((string) $tabSection['slug'])) ?>"<?= $isActive ? ' aria-current="page"' : '' ?>>
                <span class="rh-ib__tab-label"><?= $h((string) $tabSection['label']) ?></span>
                <span class="rh-ib__tab-n<?= $n > 0 ? ' is-on' : '' ?>"><?= $n ?></span>
            </a>
        <?php endforeach; ?>
    </nav>

    <?php $section = $sections[$activeId] ?? ['cards' => [], 'clear' => [], 'lead' => '', 'label' => '', 'count' => 0]; ?>
    <section class="rh-ib__panel" id="section-<?= $h($activeId) ?>" aria-labelledby="rh-ib-title">
        <h3 class="rh-ib__title" id="rh-ib-title"><?= $h((string) $section['label']) ?></h3>
        <p class="rh-ib__lead"><?= $h((string) $section['lead']) ?></p>

        <?php if (($section['cards'] ?? []) === []): ?>
            <div class="rh-ib__empty" role="status">
                <p class="rh-ib__empty-title">Rien à signaler ici</p>
                <p>Aucune situation de cette section pour le moment.</p>
            </div>
        <?php else: ?>
            <ul class="rh-ib__cards">
                <?php foreach ($section['cards'] as $card): ?>
                    <?php
                    $cid = (string) $card['id'];
                    $items = is_array($card['items'] ?? null) ? $card['items'] : [];
                    $gated = (string) ($card['gate'] ?? '') === 'corrections' && empty($can['corrections']);
                    $href = (string) ($card['href'] ?? '');
                    $cta = (string) ($card['cta'] ?? '');
                    $hasLink = $href !== '' && $cta !== '' && !$gated;
                    $tone = (string) ($card['tone'] ?? 'info');
                    $days = (int) ($card['days'] ?? 0);
                    if ($items !== []) {
                        $dialogs[] = $card;
                    }
                    ?>
                    <li class="rh-card rh-card--<?= $h($tone) ?>" id="carte-<?= $h($cid) ?>">
                        <div class="rh-card__top">
                            <h4 class="rh-card__title"><?= $h((string) $card['title']) ?></h4>
                            <span class="rh-card__count"><?= (int) $card['count'] ?></span>
                        </div>
                        <p class="rh-card__tone"><?= $h($toneLabel[$tone] ?? '') ?></p>
                        <p class="rh-card__text"><?= $h((string) $card['text']) ?></p>
                        <?php if ($days > 0 && $cid === 'absence_long'): ?>
                            <p class="rh-card__hint">Seuil actuel : <?= $days ?> jours d’absence.</p>
                        <?php elseif ($days > 0 && $cid === 'inactive'): ?>
                            <p class="rh-card__hint">Seuil actuel : <?= $days ?> jours sans connexion.</p>
                        <?php endif; ?>
                        <div class="rh-card__foot">
                            <?php if ($hasLink): ?>
                                <a class="ath-btn ath-btn--solid ath-btn--sm" href="<?= $h($href) ?>"><?= $h($cta) ?></a>
                            <?php endif; ?>
                            <?php if ($items !== []): ?>
                                <button type="button" class="ath-btn ath-btn--sm<?= $hasLink ? '' : ' ath-btn--solid' ?>" data-dialog-open="rh-dlg-<?= $h($cid) ?>" aria-haspopup="dialog">Voir le détail</button>
                            <?php endif; ?>
                            <?php if ($gated): ?>
                                <span class="rh-card__note">Réservé aux responsables des dossiers.</span>
                            <?php endif; ?>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>

        <?php if (($section['clear'] ?? []) !== []): ?>
            <details class="rh-ib__clear">
                <summary>Points sans alerte (<?= count($section['clear']) ?>)</summary>
                <ul>
                    <?php foreach ($section['clear'] as $clearTitle): ?>
                        <li><?= $h((string) $clearTitle) ?></li>
                    <?php endforeach; ?>
                </ul>
            </details>
        <?php endif; ?>
    </section>
</div>

<?php foreach ($dialogs as $card): ?>
    <?php
    $cid = (string) $card['id'];
    $items = $card['items'];
    $more = (int) ($card['more'] ?? 0);
    $href = (string) ($card['href'] ?? '');
    $gated = (string) ($card['gate'] ?? '') === 'corrections' && empty($can['corrections']);
    ?>
    <dialog class="eff-dialog rh-dlg" id="rh-dlg-<?= $h($cid) ?>" aria-labelledby="rh-dlg-<?= $h($cid) ?>-t">
        <div class="eff-dialog__body">
            <header class="eff-dialog__head">
                <div>
                    <p class="eff-page-kicker"><?= (int) $card['count'] ?> concerné<?= (int) $card['count'] > 1 ? 's' : '' ?></p>
                    <h2 id="rh-dlg-<?= $h($cid) ?>-t"><?= $h((string) $card['title']) ?></h2>
                </div>
                <button type="button" class="eff-dialog__close" data-dialog-close aria-label="Fermer">×</button>
            </header>
            <p class="rh-dlg__lead"><?= $h((string) $card['text']) ?></p>
            <ul class="rh-dlg__list">
                <?php foreach ($items as $item): ?>
                    <?php $itemHref = (string) ($item['href'] ?? ''); ?>
                    <li class="rh-dlg__item">
                        <div class="rh-dlg__who">
                            <?php if ($itemHref !== '' && !$gated): ?>
                                <a href="<?= $h($itemHref) ?>"><?= $h((string) ($item['label'] ?? '')) ?></a>
                            <?php else: ?>
                                <strong><?= $h((string) ($item['label'] ?? '')) ?></strong>
                            <?php endif; ?>
                        </div>
                        <div class="rh-dlg__what">
                            <?php if ($cid === 'corrections'): ?>
                                <?php if (trim((string) ($item['by'] ?? '')) !== ''): ?><span>Demandé par <?= $h((string) $item['by']) ?></span><?php endif; ?>
                                <?php $asked((string) ($item['since'] ?? '')); ?>
                                <?php foreach ((array) ($item['fields'] ?? []) as $field): ?><em class="rh-chip"><?= $h((string) $field) ?></em><?php endforeach; ?>
                            <?php elseif ($cid === 'elevations'): ?>
                                <?php if (trim((string) ($item['by'] ?? '')) !== ''): ?><span>Proposé par <?= $h((string) $item['by']) ?></span><?php endif; ?>
                                <?php $asked((string) ($item['since'] ?? '')); ?>
                            <?php elseif ($cid === 'mobility'): ?>
                                <?php if (trim((string) ($item['kind'] ?? '')) !== ''): ?><span><?= $h((string) $item['kind']) ?></span><?php endif; ?>
                                <?php if (trim((string) ($item['unit'] ?? '')) !== ''): ?><span><?= $h((string) $item['unit']) ?></span><?php endif; ?>
                                <?php $asked((string) ($item['since'] ?? '')); ?>
                            <?php elseif ($cid === 'qualif_urgent' || $cid === 'qualif_soon'): ?>
                                <span><?= $h((string) ($item['what'] ?? '')) ?></span>
                                <?php $left = (int) ($item['days_left'] ?? 0); ?>
                                <?php if ($left < 0): ?>
                                    <span class="rh-dlg__late">Échue depuis <?= -$left ?> j</span>
                                <?php else: ?>
                                    <span>Expire le <?= $h($rhWhen((string) ($item['expires_on'] ?? ''))) ?>, dans <?= $left ?> j</span>
                                <?php endif; ?>
                            <?php elseif ($cid === 'vacant_critical' || $cid === 'vacant'): ?>
                                <?php if (trim((string) ($item['unit'] ?? '')) !== ''): ?><span><?= $h((string) $item['unit']) ?></span><?php endif; ?>
                                <span>Titulaires : <?= (int) ($item['held'] ?? 0) ?> sur <?= (int) ($item['authorized'] ?? 0) ?></span>
                            <?php elseif ($cid === 'absence_long'): ?>
                                <span>Absent depuis <?= (int) ($item['days_open'] ?? 0) ?> jours</span>
                                <?php if (trim((string) ($item['ends_on'] ?? '')) !== ''): ?>
                                    <span>Retour prévu le <?= $h($rhWhen((string) $item['ends_on'])) ?></span>
                                <?php else: ?>
                                    <span>Sans date de retour</span>
                                <?php endif; ?>
                            <?php elseif ($cid === 'inactive'): ?>
                                <?php $lastLogin = trim((string) ($item['last_login'] ?? '')); ?>
                                <?php if ($lastLogin === ''): ?>
                                    <span>Jamais connecté</span>
                                <?php else: ?>
                                    <span>Dernière connexion le <?= $h($rhWhen($lastLogin)) ?></span>
                                <?php endif; ?>
                            <?php elseif ($cid === 'incomplete'): ?>
                                <?php foreach ((array) ($item['issues'] ?? []) as $issue): ?>
                                    <a class="rh-chip rh-chip--link" href="<?= $h((string) ($issue['href'] ?? '#')) ?>"><?= $h((string) ($issue['label'] ?? '')) ?></a>
                                <?php endforeach; ?>
                            <?php elseif ($cid === 'missing_command'): ?>
                                <span>Rattachement attendu : <?= $h((string) ($item['expected'] ?? '')) ?></span>
                            <?php elseif ($cid === 'career'): ?>
                                <span><?= $h((string) ($item['source'] ?? '')) ?></span>
                                <span><?= $h((string) ($item['detail'] ?? '')) ?></span>
                            <?php endif; ?>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
            <?php if ($more > 0): ?>
                <p class="rh-dlg__more">Et <?= $more ?> autre<?= $more > 1 ? 's' : '' ?> à consulter depuis la page de l’action.</p>
            <?php endif; ?>
            <footer class="eff-dialog__foot">
                <span class="eff-dialog__spacer"></span>
                <?php if ($href !== '' && (string) ($card['cta'] ?? '') !== '' && !$gated): ?>
                    <a class="ath-btn ath-btn--solid" href="<?= $h($href) ?>"><?= $h((string) $card['cta']) ?></a>
                <?php endif; ?>
                <button type="button" class="ath-btn" data-dialog-close>Fermer</button>
            </footer>
        </div>
    </dialog>
<?php endforeach; ?>

<?php $rhShortcutCurrent = 'a-traiter'; require base_path('views/admin/effectifs_workspace/partials/rh_shortcuts.php'); ?>
<script src="<?= $h(asset_url('assets/js/eff-member.js')) ?>" defer></script>
