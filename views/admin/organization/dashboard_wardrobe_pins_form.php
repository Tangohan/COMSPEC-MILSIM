<?php
declare(strict_types=1);

/** @var ?array<string, mixed> $pin */
/** @var list<array<string, mixed>> $wardrobes */
/** @var string $formAction */

$pin = $pin ?? null;
$wardrobes = $wardrobes ?? [];
$isEdit = $pin !== null;
$h = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
$figureUrl = $isEdit ? \App\Support\EquipmentCoverStorage::publicUrl(isset($pin['figure_path']) ? (string) $pin['figure_path'] : null) : null;
$backdropUrl = $isEdit ? \App\Support\EquipmentCoverStorage::publicUrl(isset($pin['backdrop_path']) ? (string) $pin['backdrop_path'] : null) : null;
$flashError = \App\Core\Session::getFlash('error');
$selectedId = $isEdit ? (int) ($pin['wardrobe_id'] ?? 0) : 0;
?>
<?php if ($flashError): ?>
<p class="ath-flash ath-flash--err" role="alert"><?= $h((string) $flashError) ?></p>
<?php endif; ?>

<p class="ath-note__text" style="margin-bottom:16px;">
    Choisissez une tenue déjà envoyée depuis l’arsenal. Le PNG du personnage se découpe sur le fond, comme une carte du catalogue.
</p>

<form method="post" action="<?= $h($formAction) ?>" enctype="multipart/form-data" class="ath-form">
    <?= \App\Core\Csrf::field() ?>

    <label class="ath-field">
        <span class="ath-field__label">Tenue</span>
        <select name="wardrobe_id" class="ath-field__select" required>
            <option value="">Choisir une tenue</option>
            <?php foreach ($wardrobes as $w): ?>
                <?php
                $wid = (int) ($w['id'] ?? 0);
                $label = trim((string) ($w['name'] ?? 'Tenue'));
                $owner = trim((string) ($w['owner_label'] ?? ''));
                $col = trim((string) ($w['collection_name'] ?? ''));
                if ($owner !== '') {
                    $label .= ' — ' . $owner;
                }
                if ($col !== '') {
                    $label .= ' (' . $col . ')';
                }
                ?>
                <option value="<?= $wid ?>"<?= $selectedId === $wid ? ' selected' : '' ?>><?= $h($label) ?></option>
            <?php endforeach; ?>
        </select>
    </label>

    <label class="ath-field">
        <span class="ath-field__label">Titre affiché (optionnel)</span>
        <input type="text" name="title" class="ath-field__input" maxlength="200" value="<?= $h((string) ($pin['title'] ?? '')) ?>" placeholder="Nom de la tenue si différent">
    </label>

    <label class="ath-field">
        <span class="ath-field__label">Pastille (optionnel)</span>
        <input type="text" name="badge_label" class="ath-field__input" maxlength="80" value="<?= $h((string) ($pin['badge_label'] ?? '')) ?>" placeholder="Par exemple Réglementaire, Mission, Collection">
    </label>

    <fieldset class="ath-field">
        <legend class="ath-field__label">Personnage</legend>
        <p class="ath-field__help"><?= $h(\App\Support\EquipmentCoverStorage::figureHintText()) ?></p>
        <?php if ($figureUrl): ?>
        <img src="<?= $h($figureUrl) ?>" alt="" style="max-height:180px;width:auto;background:#0f172a;border-radius:8px;margin:8px 0;">
        <label class="ath-check"><input type="checkbox" name="remove_figure" value="1"> Retirer le personnage actuel</label>
        <?php endif; ?>
        <input type="file" name="figure" accept="image/png,image/webp,image/jpeg">
    </fieldset>

    <fieldset class="ath-field">
        <legend class="ath-field__label">Photo de fond (optionnel)</legend>
        <p class="ath-field__help">Affichée derrière le personnage. <?= $h(\App\Support\EquipmentCoverStorage::hintText()) ?></p>
        <?php if ($backdropUrl): ?>
        <img src="<?= $h($backdropUrl) ?>" alt="" style="max-height:120px;width:auto;border-radius:8px;margin:8px 0;object-fit:cover;">
        <label class="ath-check"><input type="checkbox" name="remove_backdrop" value="1"> Retirer le fond actuel</label>
        <?php endif; ?>
        <input type="file" name="backdrop" accept="image/jpeg,image/png,image/webp">
    </fieldset>

    <div class="ath-form__actions">
        <button type="submit" class="ath-btn ath-btn--solid"><?= $isEdit ? 'Enregistrer' : 'Mettre en avant' ?></button>
        <a href="<?= $h(url('back-office/dashboard-tenues')) ?>" class="ath-btn">Annuler</a>
    </div>
</form>
