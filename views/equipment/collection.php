<?php
declare(strict_types=1);
/**
 * Ancienne fiche collection : le catalogue hub filtre désormais sur ?collection=.
 * Conservée pour compatibilité des includes / redirections legacy.
 */
$collection = $collection ?? [];
$wardrobes = $wardrobes ?? [];
$mineWardrobes = $mineWardrobes ?? [];
$canEdit = !empty($canEdit);
$csrfToken = (string) ($csrfToken ?? '');
$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$id = (int) ($collection['id'] ?? 0);
$hubUrl = url('equipment') . ($id > 0 ? ('?collection=' . $id) : '');
?>
<div class="eq-hub">
    <header class="eq-hub__banner">
        <div class="eq-hub__banner-copy">
            <p class="eq-hub__kicker"><a href="<?= $h(url('equipment')) ?>">Équipement</a> · Collection</p>
            <h1><?= $h($collection['name'] ?? 'Collection') ?></h1>
            <p class="eq-hub__count">Cette collection s’ouvre dans le catalogue.</p>
        </div>
        <div class="eq-hub__banner-actions">
            <a class="eq-hub__btn" href="<?= $h($hubUrl) ?>">Voir dans le catalogue</a>
        </div>
    </header>
    <div class="eq-hub__body">
        <?php if ($canEdit): ?>
        <section class="eq-hub__panel" style="background:#fff;border:1px solid #e3e6e9;border-radius:.75rem;padding:1rem;">
            <h2>Modifier la collection</h2>
            <form method="post" action="<?= $h(url('equipment/collections/' . $id)) ?>" enctype="multipart/form-data" class="eq-hub__form">
                <input type="hidden" name="_csrf_token" value="<?= $h($csrfToken) ?>">
                <label>Nom
                    <input type="text" name="name" required maxlength="120" value="<?= $h($collection['name'] ?? '') ?>">
                </label>
                <label>Présentation
                    <textarea name="description" rows="3" maxlength="500"><?= $h($collection['description'] ?? '') ?></textarea>
                </label>
                <label>Qui peut s’en servir
                    <select name="visibility" class="bo-select">
                        <?php foreach (['personal' => 'Moi seulement', 'unit' => 'Mon unité', 'tenant' => 'Toute la communauté'] as $val => $lab): ?>
                        <option value="<?= $h($val) ?>" <?= (($collection['visibility'] ?? '') === $val) ? 'selected' : '' ?>><?= $h($lab) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>Photo de présentation
                    <input type="file" name="cover" accept="image/jpeg,image/png,image/webp">
                    <span class="eq-hub__hint"><?= $h(\App\Support\EquipmentCoverStorage::hintText()) ?> Laisser vide pour conserver la photo actuelle.</span>
                </label>
                <?php if ($mineWardrobes !== []): ?>
                <fieldset>
                    <legend>Vos tenues dans cette collection</legend>
                    <div class="eq-hub__checks">
                        <?php foreach ($mineWardrobes as $w): ?>
                        <label class="eq-hub__check">
                            <input type="checkbox" name="wardrobe_ids[]" value="<?= (int) $w['id'] ?>" <?= ((int) ($w['collection_id'] ?? 0) === $id) ? 'checked' : '' ?>>
                            <?= $h($w['name'] ?? 'Tenue') ?>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </fieldset>
                <?php endif; ?>
                <button type="submit" class="eq-hub__btn">Enregistrer</button>
            </form>
            <form method="post" action="<?= $h(url('equipment/collections/' . $id . '/delete')) ?>" data-ui-confirm="1" data-ui-confirm-title="Retirer la collection" data-ui-confirm-body="Retirer cette collection ? Les tenues ne sont pas supprimées." class="eq-hub__danger">
                <input type="hidden" name="_csrf_token" value="<?= $h($csrfToken) ?>">
                <button type="submit" class="eq-hub__btn eq-hub__btn--ghost">Retirer la collection</button>
            </form>
        </section>
        <?php endif; ?>

        <?php if ($wardrobes !== []): ?>
        <ul class="eq-hub__grid">
            <?php foreach ($wardrobes as $w): ?>
            <li>
                <a class="eq-hub__card" href="<?= $h(url('equipment') . '?tenue=' . (int) $w['id']) ?>">
                    <span class="eq-hub__media eq-hub__media--portrait">
                        <?php if (!empty($w['cover_url'])): ?>
                        <img class="eq-hub__img" src="<?= $h($w['cover_url']) ?>" alt="">
                        <?php else: ?>
                        <span class="eq-hub__ph" aria-hidden="true"></span>
                        <?php endif; ?>
                    </span>
                    <span class="eq-hub__card-body">
                        <strong><?= $h($w['display_name'] ?? $w['name'] ?? '') ?></strong>
                    </span>
                </a>
            </li>
            <?php endforeach; ?>
        </ul>
        <?php endif; ?>
    </div>
</div>
