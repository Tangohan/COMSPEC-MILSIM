<?php
declare(strict_types=1);
/**
 * Fiches de vigilance émises d'une personne : niveau (couleur, forme, rang), état (en cours, expirée, levée), échéance, PDF,
 * renouveler, lever avec motif (modales), adresse de signalement avec son QR code. Chaque action dit le droit qui manque.
 *
 * @var array<string, mixed> $m données de SseSheetMenu::build (clés « vigilances », « kind »)
 */
use App\Core\Csrf;
use App\Services\Sse\SseVigilanceFollow;

$h = $h ?? static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$vigs = is_array($m['vigilances'] ?? null) ? $m['vigilances'] : [];
$from = ($m['kind'] ?? 'personne') === 'identite' ? 'identite' : 'personne';
$stateLabel = ['active' => 'En cours', 'expired' => 'Expirée', 'lifted' => 'Levée'];
?>
<div class="vig-list" id="vigilances">
    <p class="vig-list__desc">Avertissements émis pour cette personne : niveau, échéance et suites à donner.</p>
    <?php foreach ($vigs as $v): ?>
        <?php if ($v['masked']): ?>
            <article class="vig-row is-masked" style="--lv:<?= $h($v['hex']) ?>;--lv-ink:<?= $h($v['ink']) ?>">
                <div class="vig-row__main">
                    <p class="vig-row__meta"><strong translate="no"><?= $h($v['ref']) ?></strong> · <span><?= $h($stateLabel[$v['state']] ?? '') ?></span></p>
                    <p class="vig-row__denial">Classée au-dessus de votre habilitation : le détail et le PDF ne vous sont pas ouverts.</p>
                </div>
            </article>
            <?php continue; ?>
        <?php endif; ?>
        <?php $rid = 'vig-' . (int) $v['id']; ?>
        <article class="vig-row is-<?= $h($v['state']) ?>" style="--lv:<?= $h($v['hex']) ?>;--lv-ink:<?= $h($v['ink']) ?>" id="<?= $h($rid) ?>">
            <div class="vig-row__head">
                <span class="vig-lvl"><?= SseVigilanceFollow::shapeSvg((string) $v['shape'], 22) ?><b><?= $h($v['title']) ?></b><i><?= (int) $v['rank'] ?>/4</i></span>
                <span class="vig-pill vig-pill--<?= $h($v['state']) ?>"><?= $h($stateLabel[$v['state']] ?? '') ?></span>
                <?php if ($v['perishable']): ?><span class="vig-pill vig-pill--warn">Périssable</span><?php endif; ?>
            </div>
            <div class="vig-row__main">
                <p class="vig-row__meta"><strong translate="no"><?= $h($v['ref']) ?></strong> · <span>Émise le</span> <span><?= $h($v['issued_label']) ?></span><?php if ($v['issued_by'] !== ''): ?> <span>par</span> <span translate="no"><?= $h($v['issued_by']) ?></span><?php endif; ?></p>
                <?php if ($v['order_label'] !== ''): ?><p class="vig-row__order"><?= $h($v['order_label']) ?></p><?php endif; ?>
                <?php if ($v['measures'] !== []): ?>
                    <ul class="vig-chips"><?php foreach ($v['measures'] as $mm): ?><li><?= $h($mm) ?></li><?php endforeach; ?></ul>
                <?php endif; ?>
                <p class="vig-row__due">
                    <?php if ($v['state'] === 'lifted'): ?>
                        <span>Levée le</span> <strong><?= $h($v['lifted_label']) ?></strong><?php if ($v['lifted_by'] !== ''): ?> <span>par</span> <span translate="no"><?= $h($v['lifted_by']) ?></span><?php endif; ?>
                        <?php if ($v['lift_reason'] !== ''): ?> · <span translate="no"><?= $h($v['lift_reason']) ?></span><?php endif; ?>
                    <?php elseif ($v['state'] === 'expired'): ?>
                        <span>Expirée le</span> <strong><?= $h($v['until_label']) ?></strong>
                    <?php elseif ($v['until_lifted']): ?>
                        <strong>Jusqu’à levée</strong>
                    <?php else: ?>
                        <span>Valable jusqu’au</span> <strong><?= $h($v['until_label']) ?></strong>
                    <?php endif; ?>
                </p>
            </div>
            <div class="vig-row__actions">
                <?php if (isset($issuesById[(int) $v['id']])): $lmRow = $issuesById[(int) $v['id']]; $lmClass = 'vig-btn'; $lmLabel = 'Télécharger le PDF'; require __DIR__ . '/_sheet_level_menu.php'; else: ?>
                <a class="vig-btn" href="<?= $h($v['pdf_url']) ?>">Télécharger le PDF</a>
                <?php endif; ?>
                <?php if ($v['state'] !== 'lifted'): ?>
                    <button type="button" class="vig-btn" data-vig-open="<?= $h($rid) ?>-renew"<?= $v['can_renew'] ? '' : ' disabled aria-disabled="true"' ?>>Renouveler</button>
                <?php endif; ?>
                <?php if ($v['state'] === 'active'): ?>
                    <button type="button" class="vig-btn vig-btn--warn" data-vig-open="<?= $h($rid) ?>-lift"<?= $v['can_lift'] ? '' : ' disabled aria-disabled="true"' ?>>Lever</button>
                <?php endif; ?>
                <?php if ($v['state'] === 'active' && $v['url'] !== ''): ?>
                    <button type="button" class="vig-btn" data-vig-open="<?= $h($rid) ?>-link">Adresse et QR code</button>
                <?php endif; ?>
            </div>
            <?php $notes = array_values(array_unique(array_filter([$v['state'] !== 'lifted' && !$v['can_renew'] ? $v['renew_denial'] : null, $v['state'] === 'active' && !$v['can_lift'] ? $v['lift_denial'] : null]))); ?>
            <?php foreach ($notes as $note): ?><p class="vig-row__denial"><?= $h($note) ?></p><?php endforeach; ?>

            <?php if ($v['can_renew']): ?>
                <dialog class="vig-dialog" id="<?= $h($rid) ?>-renew" aria-labelledby="<?= $h($rid) ?>-renew-t">
                    <form method="post" action="<?= $h($v['renew_url']) ?>" class="vig-dialog__form">
                        <?= Csrf::field() ?>
                        <input type="hidden" name="from" value="<?= $h($from) ?>">
                        <h3 id="<?= $h($rid) ?>-renew-t">Renouveler la fiche de vigilance</h3>
                        <p class="vig-dialog__desc">Une nouvelle fiche est émise avec le même niveau, les mêmes consignes et le même service ; celle-ci est levée.</p>
                        <fieldset class="vig-days">
                            <legend>Nouvelle durée de validité</legend>
                            <?php foreach (SseVigilanceFollow::SHORTCUT_DAYS as $d): ?>
                                <label class="vig-day"><input type="radio" name="days" value="<?= (int) $d ?>"<?= $d === 30 ? ' checked' : '' ?>><span><?= (int) $d ?> <span>jours</span></span></label>
                            <?php endforeach; ?>
                        </fieldset>
                        <div class="vig-dialog__actions">
                            <button type="button" class="vig-btn" data-vig-close>Annuler</button>
                            <button type="submit" class="vig-btn vig-btn--primary">Renouveler</button>
                        </div>
                    </form>
                </dialog>
            <?php endif; ?>
            <?php if ($v['can_lift']): ?>
                <dialog class="vig-dialog" id="<?= $h($rid) ?>-lift" aria-labelledby="<?= $h($rid) ?>-lift-t">
                    <form method="post" action="<?= $h($v['lift_url']) ?>" class="vig-dialog__form">
                        <?= Csrf::field() ?>
                        <input type="hidden" name="from" value="<?= $h($from) ?>">
                        <h3 id="<?= $h($rid) ?>-lift-t">Lever la fiche de vigilance</h3>
                        <p class="vig-dialog__desc">La fiche n’est plus un avertissement en cours ; le motif reste au journal de la fiche.</p>
                        <label for="<?= $h($rid) ?>-reason">Motif de la levée</label>
                        <textarea id="<?= $h($rid) ?>-reason" name="reason" rows="3" required minlength="3" maxlength="500" placeholder="Ex. personne localisée et prise en charge"></textarea>
                        <div class="vig-dialog__actions">
                            <button type="button" class="vig-btn" data-vig-close>Annuler</button>
                            <button type="submit" class="vig-btn vig-btn--warn-solid">Lever la fiche</button>
                        </div>
                    </form>
                </dialog>
            <?php endif; ?>
            <?php if ($v['state'] === 'active' && $v['url'] !== ''): ?>
                <dialog class="vig-dialog vig-dialog--link" id="<?= $h($rid) ?>-link" aria-labelledby="<?= $h($rid) ?>-link-t">
                    <div class="vig-dialog__form">
                        <h3 id="<?= $h($rid) ?>-link-t">Adresse de signalement</h3>
                        <p class="vig-dialog__desc">Imprimée sur le PDF avec son QR code : toute personne qui l’ouvre peut signaler une observation.</p>
                        <div class="vig-qr">
                            <img src="<?= $h($v['qr_url']) ?>" alt="QR code de l’adresse de signalement" width="168" height="168" loading="lazy">
                            <div class="vig-qr__side">
                                <label for="<?= $h($rid) ?>-url">Lien de signalement</label>
                                <input id="<?= $h($rid) ?>-url" type="text" readonly value="<?= $h($v['url']) ?>" data-vig-url>
                                <div class="vig-qr__row">
                                    <button type="button" class="vig-btn vig-btn--primary" data-vig-copy="<?= $h($rid) ?>-url">Copier le lien</button>
                                    <span class="vig-copied" data-vig-copied hidden role="status">Lien copié.</span>
                                </div>
                                <p class="vig-qr__svc"><span>Service à informer :</span> <strong translate="no"><?= $h($v['service']) ?></strong> · <span translate="no"><?= $h($v['delay']) ?></span></p>
                            </div>
                        </div>
                        <div class="vig-dialog__actions"><button type="button" class="vig-btn" data-vig-close>Fermer</button></div>
                    </div>
                </dialog>
            <?php endif; ?>
        </article>
    <?php endforeach; ?>
</div>
