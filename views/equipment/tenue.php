<?php
declare(strict_types=1);
$wardrobe = $wardrobe ?? [];
$collections = $collections ?? [];
$csrfToken = (string) ($csrfToken ?? '');
$flashOk = trim((string) ($flash_success ?? ''));
$flashErr = trim((string) ($flash_error ?? ''));
$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$mine = !empty($wardrobe['mine']);
?>
<div class="eq-hub">
    <header class="eq-hub__hero">
        <div class="eq-hub__hero-inner">
            <p class="eq-hub__kicker"><a href="<?= $h(url('equipment')) ?>">Équipement</a> · Tenue</p>
            <h1><?= $h($wardrobe['name'] ?? '') ?></h1>
            <p class="eq-hub__lead">
                <?= $h(($wardrobe['collection_name'] ?? '') !== '' ? $wardrobe['collection_name'] : 'Sans collection') ?>
            </p>
        </div>
    </header>
    <div class="eq-hub__body">
        <?php if ($flashOk !== ''): ?><p class="eq-hub__flash eq-hub__flash--ok"><?= $h($flashOk) ?></p><?php endif; ?>
        <?php if ($flashErr !== ''): ?><p class="eq-hub__flash eq-hub__flash--err"><?= $h($flashErr) ?></p><?php endif; ?>

        <div class="eq-hub__detail">
            <div class="eq-hub__cover eq-hub__cover--lg">
                <?php if (!empty($wardrobe['cover_url'])): ?>
                <img src="<?= $h($wardrobe['cover_url']) ?>" alt="">
                <?php else: ?>
                <span class="eq-hub__cover-ph">Ajoutez une photo de présentation</span>
                <?php endif; ?>
            </div>
            <?php if (trim((string) ($wardrobe['notes'] ?? '')) !== ''): ?>
            <p><?= nl2br($h($wardrobe['notes'])) ?></p>
            <?php endif; ?>
            <p class="eq-hub__hint">Cette tenue s’envoie et se récupère depuis l’arsenal en jeu, bandeau Athena en haut de l’écran d’équipement.</p>
        </div>

        <?php if ($mine): ?>
        <section class="eq-hub__panel">
            <h2>Présentation</h2>
            <form method="post" action="<?= $h(url('equipment/tenues/' . (int) $wardrobe['id'])) ?>" enctype="multipart/form-data" class="eq-hub__form">
                <input type="hidden" name="_csrf_token" value="<?= $h($csrfToken) ?>">
                <label>Photo de présentation
                    <input type="file" name="cover" accept="image/jpeg,image/png,image/webp">
                    <span class="eq-hub__hint">JPG, PNG ou WebP, 8 Mo maximum.</span>
                </label>
                <label>Collection
                    <select name="collection_id" class="bo-select">
                        <option value="0">Sans collection</option>
                        <?php foreach ($collections as $c): ?>
                        <option value="<?= (int) $c['id'] ?>" <?= ((int) ($wardrobe['collection_id'] ?? 0) === (int) $c['id']) ? 'selected' : '' ?>>
                            <?= $h($c['name'] ?? '') ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>Note
                    <textarea name="notes" rows="2" maxlength="255"><?= $h($wardrobe['notes'] ?? '') ?></textarea>
                </label>
                <button type="submit" class="eq-hub__btn">Enregistrer</button>
            </form>
            <form method="post" action="<?= $h(url('equipment/tenues/' . (int) $wardrobe['id'] . '/delete')) ?>" onsubmit="return confirm('Retirer cette tenue d’Athena ?');" class="eq-hub__danger">
                <input type="hidden" name="_csrf_token" value="<?= $h($csrfToken) ?>">
                <button type="submit" class="eq-hub__btn eq-hub__btn--ghost">Retirer la tenue</button>
            </form>
        </section>
        <?php endif; ?>
    </div>
</div>
