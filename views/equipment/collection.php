<?php
declare(strict_types=1);
$collection = $collection ?? [];
$wardrobes = $wardrobes ?? [];
$mineWardrobes = $mineWardrobes ?? [];
$canEdit = !empty($canEdit);
$csrfToken = (string) ($csrfToken ?? '');
$flashOk = trim((string) ($flash_success ?? ''));
$flashErr = trim((string) ($flash_error ?? ''));
$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$visibilityLabel = static function (string $v): string {
    return match ($v) {
        'unit' => 'Unité',
        'tenant' => 'Communauté',
        default => 'Personnel',
    };
};
$selected = [];
foreach ($wardrobes as $w) {
    if (!empty($w['mine'])) {
        $selected[(int) $w['id']] = true;
    }
}
?>
<div class="eq-hub">
    <header class="eq-hub__hero">
        <div class="eq-hub__hero-inner">
            <p class="eq-hub__kicker"><a href="<?= $h(url('equipment')) ?>">Équipement</a> · Collection</p>
            <h1><?= $h($collection['name'] ?? '') ?></h1>
            <p class="eq-hub__lead"><?= $h($visibilityLabel((string) ($collection['visibility'] ?? 'personal'))) ?></p>
        </div>
    </header>
    <div class="eq-hub__body">
        <?php if ($flashOk !== ''): ?><p class="eq-hub__flash eq-hub__flash--ok"><?= $h($flashOk) ?></p><?php endif; ?>
        <?php if ($flashErr !== ''): ?><p class="eq-hub__flash eq-hub__flash--err"><?= $h($flashErr) ?></p><?php endif; ?>

        <div class="eq-hub__detail">
            <div class="eq-hub__cover eq-hub__cover--lg">
                <?php if (!empty($collection['cover_url'])): ?>
                <img src="<?= $h($collection['cover_url']) ?>" alt="">
                <?php else: ?>
                <span class="eq-hub__cover-ph">Sans photo de présentation</span>
                <?php endif; ?>
            </div>
            <?php if (trim((string) ($collection['description'] ?? '')) !== ''): ?>
            <p><?= nl2br($h($collection['description'])) ?></p>
            <?php endif; ?>
        </div>

        <section>
            <h2>Tenues de la collection</h2>
            <?php if ($wardrobes === []): ?>
            <p class="eq-hub__empty">Aucune tenue n’est encore rangée ici.</p>
            <?php else: ?>
            <ul class="eq-hub__grid">
                <?php foreach ($wardrobes as $w): ?>
                <li>
                    <a class="eq-hub__card" href="<?= $h(url('equipment/tenues/' . (int) $w['id'])) ?>">
                        <span class="eq-hub__cover">
                            <?php if (!empty($w['cover_url'])): ?>
                            <img src="<?= $h($w['cover_url']) ?>" alt="">
                            <?php else: ?>
                            <span class="eq-hub__cover-ph">Sans photo</span>
                            <?php endif; ?>
                        </span>
                        <span class="eq-hub__card-body">
                            <strong><?= $h($w['name'] ?? '') ?></strong>
                            <span><?= $h(($w['owner_label'] ?? '') !== '' ? $w['owner_label'] : 'Membre') ?></span>
                        </span>
                    </a>
                </li>
                <?php endforeach; ?>
            </ul>
            <?php endif; ?>
        </section>

        <?php if ($canEdit): ?>
        <section class="eq-hub__panel">
            <h2>Modifier la collection</h2>
            <form method="post" action="<?= $h(url('equipment/collections/' . (int) $collection['id'])) ?>" enctype="multipart/form-data" class="eq-hub__form">
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
                            <input type="checkbox" name="wardrobe_ids[]" value="<?= (int) $w['id'] ?>" <?= !empty($selected[(int) $w['id']]) ? 'checked' : '' ?>>
                            <?= $h($w['name'] ?? 'Tenue') ?>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </fieldset>
                <?php endif; ?>
                <button type="submit" class="eq-hub__btn">Enregistrer</button>
            </form>
            <form method="post" action="<?= $h(url('equipment/collections/' . (int) $collection['id'] . '/delete')) ?>" onsubmit="return confirm('Retirer cette collection ? Les tenues ne sont pas supprimées.');" class="eq-hub__danger">
                <input type="hidden" name="_csrf_token" value="<?= $h($csrfToken) ?>">
                <button type="submit" class="eq-hub__btn eq-hub__btn--ghost">Retirer la collection</button>
            </form>
        </section>
        <?php endif; ?>
    </div>
</div>
