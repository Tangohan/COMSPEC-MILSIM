<?php
declare(strict_types=1);

/**
 * Doctrine > Types de documents : référentiel des types (note de service, instruction…) et de leur comportement
 * par défaut (lecture obligatoire, accusé de lecture, validation avant publication).
 *
 * @var list<array<string, mixed>> $types
 */

require_once base_path('views/admin/documents/partials/doctrine_ui.php');

$types = is_array($types ?? null) ? $types : [];
$csrf = dct_e((string) ($csrf_token ?? ''));
$can = is_array($can ?? null) ? $can : [];
$color = static fn (mixed $c): string => preg_match('/^#[0-9a-fA-F]{3,8}$/', (string) $c) === 1 ? (string) $c : '';
$back = '<a class="dct-btn" href="' . dct_e(url('back-office/doctrine')) . '">' . dct_icon('back') . '<span>Retour à la doctrine</span></a>';
$headHtml = dct_head(
    'Opérations · Doctrine',
    'Types de documents',
    'Le type dit ce qu’est un texte (note de service, instruction, directive…) et fixe ses réglages par défaut : lecture obligatoire, accusé de lecture, validation avant publication. Son abréviation numérote les documents, par exemple NS-2026-004.',
    $back,
    'types',
    $can
    );
?>
<div class="dct">
    <?= $headHtml ?>
    <?= dct_flashes() ?>

    <div class="dct-split">
        <section class="dct-panel" aria-labelledby="dct-types-title">
            <h2 id="dct-types-title" class="dct-sr">Types enregistrés</h2>
            <?php if ($types === []): ?>
                <div class="dct-empty">
                    <div class="dct-empty__ill"><?= dct_icon('tag', 'dct-i--lg') ?></div>
                    <b>Aucun type pour l’instant</b>
                    <p>Créez au moins un type pour publier : par exemple « Note de service » (abréviation NS), lecture obligatoire.</p>
                </div>
            <?php else: ?>
                <div class="dct-table-wrap" style="border:0;border-radius:0">
                    <table class="dct-table">
                        <thead><tr><th scope="col">Type</th><th scope="col">Abréviation</th><th scope="col">Par défaut</th><th scope="col">État</th><th scope="col"><span class="dct-sr">Action</span></th></tr></thead>
                        <tbody>
                        <?php foreach ($types as $t): ?>
                            <?php $tid = (int) ($t['id'] ?? 0); $active = !empty($t['is_active']); ?>
                            <tr>
                                <td>
                                    <b style="display:inline-flex;gap:8px;align-items:center"><?= dct_swatch($color($t['color'] ?? '')) ?><?= dct_e((string) ($t['label'] ?? '')) ?></b>
                                    <?php if (trim((string) ($t['description'] ?? '')) !== ''): ?><small><?= dct_e((string) $t['description']) ?></small><?php endif; ?>
                                </td>
                                <td class="dct-mono" translate="no"><b><?= dct_e((string) ($t['code_prefix'] ?? '')) ?></b></td>
                                <td>
                                    <div class="dct-chips">
                                        <?php if (!empty($t['default_acknowledgment_required'])): ?><span class="dct-tag is-warn">Accusé de lecture</span><?php elseif (!empty($t['default_reading_required'])): ?><span class="dct-tag is-warn">Lecture obligatoire</span><?php else: ?><span class="dct-tag">Pour information</span><?php endif; ?>
                                        <span class="dct-tag"><?= !empty($t['default_require_validation']) ? 'Validation avant publication' : 'Publication directe' ?></span>
                                    </div>
                                </td>
                                <td><?= $active ? dct_state_pill('published', 'Actif') : dct_state_pill('archived', 'Désactivé') ?></td>
                                <td>
                                    <form method="post" action="<?= dct_e(url('back-office/documents/types')) ?>">
                                        <input type="hidden" name="_csrf_token" value="<?= $csrf ?>">
                                        <input type="hidden" name="id" value="<?= $tid ?>">
                                        <input type="hidden" name="label" value="<?= dct_e((string) ($t['label'] ?? '')) ?>">
                                        <input type="hidden" name="code_prefix" value="<?= dct_e((string) ($t['code_prefix'] ?? '')) ?>">
                                        <input type="hidden" name="description" value="<?= dct_e((string) ($t['description'] ?? '')) ?>">
                                        <input type="hidden" name="color" value="<?= dct_e((string) ($t['color'] ?? '#64748b')) ?>">
                                        <input type="hidden" name="sort_order" value="<?= (int) ($t['sort_order'] ?? 100) ?>">
                                        <?php if (!empty($t['default_reading_required'])): ?><input type="hidden" name="default_reading_required" value="1"><?php endif; ?>
                                        <?php if (!empty($t['default_acknowledgment_required'])): ?><input type="hidden" name="default_acknowledgment_required" value="1"><?php endif; ?>
                                        <?php if (!empty($t['default_require_validation'])): ?><input type="hidden" name="default_require_validation" value="1"><?php endif; ?>
                                        <?php if (!$active): ?>
                                            <input type="hidden" name="is_active" value="1">
                                            <button class="dct-btn is-sm" type="submit">Réactiver</button>
                                        <?php else: ?>
                                            <button class="dct-btn is-sm is-quiet" type="submit">Désactiver</button>
                                        <?php endif; ?>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <p class="dct-hint" style="padding:10px 16px">Un type désactivé n’est plus proposé à la publication ; les documents déjà publiés le gardent.</p>
            <?php endif; ?>
        </section>

        <aside class="dct-aside">
            <form method="post" action="<?= dct_e(url('back-office/documents/types')) ?>" class="dct-card">
                <input type="hidden" name="_csrf_token" value="<?= $csrf ?>">
                <h2 class="dct-card__t">Ajouter un type</h2>
                <label class="dct-fl"><span>Nom</span><input name="label" required maxlength="120" placeholder="Note de service"></label>
                <div class="dct-grid dct-grid--2">
                    <label class="dct-fl"><span>Abréviation</span><input class="dct-mono" name="code_prefix" required maxlength="16" placeholder="NS"></label>
                    <label class="dct-fl"><span>Couleur</span><input name="color" type="color" value="#0f766e"></label>
                </div>
                <label class="dct-fl"><span>Code interne <small>facultatif</small></span><input class="dct-mono" name="code" maxlength="64" placeholder="note_de_service"></label>
                <label class="dct-fl"><span>Description <small>facultative</small></span><textarea name="description" rows="2" maxlength="500"></textarea></label>
                <fieldset class="dct-fl">
                    <legend>Réglages par défaut</legend>
                    <div class="dct-checks">
                        <label class="dct-check"><input type="checkbox" name="default_reading_required" value="1">Lecture obligatoire</label>
                        <label class="dct-check"><input type="checkbox" name="default_acknowledgment_required" value="1">Accusé de lecture</label>
                        <label class="dct-check"><input type="checkbox" name="default_require_validation" value="1">Validation avant publication</label>
                        <label class="dct-check"><input type="checkbox" name="is_active" value="1" checked>Type actif</label>
                    </div>
                </fieldset>
                <div class="dct-form__f"><button type="submit" class="dct-btn is-dark"><?= dct_icon('plus') ?><span>Ajouter le type</span></button></div>
            </form>
        </aside>
    </div>
</div>
