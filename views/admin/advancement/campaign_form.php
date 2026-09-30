<?php
$h = static fn (mixed $v): string => htmlspecialchars(trim((string) $v), ENT_QUOTES, 'UTF-8');
$grades = is_array($grades ?? null) ? $grades : [];
$filieres = is_array($filieres ?? null) ? $filieres : [];
?>
<div class="adv-page">
    <?php require __DIR__ . '/_flash.php'; ?>
    <form method="post" action="<?= $h(url('back-office/rh/avancement/store')) ?>" class="adv-form">
        <?= \App\Core\Csrf::field() ?>
        <label>Grade visé
            <select name="grade_id" required>
                <option value="">Choisir</option>
                <?php foreach ($grades as $grade): ?>
                    <?php if (empty($grade['advancement_choice_enabled'])) { continue; } ?>
                    <option value="<?= (int) $grade['id'] ?>"><?= $h((string) $grade['label']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>Filière
            <select name="filiere_id">
                <option value="">Celle du grade</option>
                <?php foreach ($filieres as $filiere): ?>
                    <option value="<?= (int) $filiere['id'] ?>"><?= $h((string) $filiere['label']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>Année<input type="number" name="year" value="<?= (int) date('Y') ?>" required></label>
        <label>Ouverture<input type="date" name="opens_at" required></label>
        <label>Clôture<input type="date" name="closes_at" required></label>
        <label>Quota de places<input type="number" min="0" name="quota_slots" placeholder="Illimité si vide"></label>
        <div class="adv-actions">
            <button class="ath-btn ath-btn--solid" type="submit">Ouvrir la campagne</button>
            <a class="ath-btn" href="<?= $h(url('back-office/rh/avancement')) ?>">Retour</a>
        </div>
    </form>
</div>
