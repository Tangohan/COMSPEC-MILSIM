<?php
declare(strict_types=1);
/**
 * Ancienne fiche tenue : le catalogue ouvre désormais un quick-view (?tenue=).
 * Conservée pour compatibilité / liens legacy.
 */
$wardrobe = $wardrobe ?? [];
$collections = $collections ?? [];
$loadoutItems = is_array($loadoutItems ?? null) ? $loadoutItems : [];
$csrfToken = (string) ($csrfToken ?? '');
$flashOk = trim((string) ($flash_success ?? ''));
$flashErr = trim((string) ($flash_error ?? ''));
$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$mine = !empty($wardrobe['mine']);
$hubUrl = url('equipment') . '?tenue=' . (int) ($wardrobe['id'] ?? 0);
?>
<div class="eq-hub">
    <header class="eq-hub__banner">
        <div class="eq-hub__banner-copy">
            <p class="eq-hub__kicker"><a href="<?= $h(url('equipment')) ?>">Équipement</a> · Tenue</p>
            <h1><?= $h($wardrobe['display_name'] ?? $wardrobe['name'] ?? '') ?></h1>
            <p class="eq-hub__count">
                <?= $h(($wardrobe['collection_name'] ?? '') !== '' ? $wardrobe['collection_name'] : 'Sans collection') ?>
            </p>
        </div>
        <div class="eq-hub__banner-actions">
            <a class="eq-hub__btn" href="<?= $h($hubUrl) ?>">Ouvrir l’aperçu catalogue</a>
        </div>
    </header>
    <div class="eq-hub__body">
        <?php if ($flashOk !== ''): ?><p class="eq-hub__flash eq-hub__flash--ok"><?= $h($flashOk) ?></p><?php endif; ?>
        <?php if ($flashErr !== ''): ?><p class="eq-hub__flash eq-hub__flash--err"><?= $h($flashErr) ?></p><?php endif; ?>

        <div class="eq-hub__detail">
            <div class="eq-hub__media eq-hub__media--portrait" style="max-width:18rem;">
                <?php if (!empty($wardrobe['cover_url'])): ?>
                <img class="eq-hub__img is-loaded" src="<?= $h($wardrobe['cover_url']) ?>" alt="">
                <?php else: ?>
                <span class="eq-hub__ph" aria-hidden="true"></span>
                <?php endif; ?>
            </div>
            <p class="eq-hub__hint">Cette tenue s’envoie et se récupère depuis l’arsenal en jeu, bandeau Athena en haut de l’écran d’équipement.</p>
        </div>

        <section class="eq-hub__panel" aria-labelledby="eq-items-heading" style="background:#fff;border:1px solid #e3e6e9;border-radius:.75rem;padding:1rem;margin-top:1rem;">
            <h2 id="eq-items-heading">Équipement</h2>
            <?php if ($loadoutItems === []): ?>
            <p class="eq-hub__empty">Aucun équipement n’est listé pour cette tenue.</p>
            <?php else: ?>
            <div class="eq-hub__items">
                <?php foreach ($loadoutItems as $section): ?>
                <?php
                    $secTitle = trim((string) ($section['title'] ?? ''));
                    $secItems = is_array($section['items'] ?? null) ? $section['items'] : [];
                    if ($secTitle === '' || $secItems === []) {
                        continue;
                    }
                ?>
                <div class="eq-hub__item-group">
                    <h3><?= $h($secTitle) ?></h3>
                    <ul>
                        <?php foreach ($secItems as $it): ?>
                        <?php
                            $iname = trim((string) ($it['name'] ?? ''));
                            $iqty = (int) ($it['qty'] ?? 1);
                            if ($iname === '') {
                                continue;
                            }
                        ?>
                        <li>
                            <span><?= $h($iname) ?></span>
                            <?php if ($iqty > 1): ?><em>× <?= $iqty ?></em><?php endif; ?>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </section>

        <?php if ($mine): ?>
        <section class="eq-hub__panel" style="background:#fff;border:1px solid #e3e6e9;border-radius:.75rem;padding:1rem;margin-top:1rem;">
            <h2>Présentation</h2>
            <form method="post" action="<?= $h(url('equipment/tenues/' . (int) $wardrobe['id'])) ?>" enctype="multipart/form-data" class="eq-hub__form">
                <input type="hidden" name="_csrf_token" value="<?= $h($csrfToken) ?>">
                <label>Photo de présentation
                    <input type="file" name="cover" accept="image/jpeg,image/png,image/webp">
                    <span class="eq-hub__hint"><?= $h(\App\Support\EquipmentCoverStorage::hintText()) ?></span>
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
            <form method="post" action="<?= $h(url('equipment/tenues/' . (int) $wardrobe['id'] . '/delete')) ?>" data-ui-confirm="1" data-ui-confirm-title="Retirer la tenue" data-ui-confirm-body="Retirer cette tenue d’Athena ?" class="eq-hub__danger">
                <input type="hidden" name="_csrf_token" value="<?= $h($csrfToken) ?>">
                <button type="submit" class="eq-hub__btn eq-hub__btn--ghost">Retirer la tenue</button>
            </form>
        </section>
        <?php endif; ?>
    </div>
</div>
