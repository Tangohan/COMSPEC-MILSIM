<?php
declare(strict_types=1);
$h = static fn (mixed $v): string => htmlspecialchars(trim((string) $v), ENT_QUOTES, 'UTF-8');
$grades = is_array($grades ?? null) ? $grades : [];
$filieres = is_array($filieres ?? null) ? $filieres : [];
?>
<div class="bo-adv">
    <p class="bo-adv__back"><a href="<?= $h(url('back-office/rh/avancement')) ?>">← Campagnes</a></p>
    <form method="post" action="<?= $h(url('back-office/rh/avancement/store')) ?>" class="bo-adv__panel bo-adv__form">
        <?= \App\Core\Csrf::field() ?>
        <div class="bo-adv__form-grid">
            <label>Grade visé
                <select name="grade_id" required>
                    <option value="">Sélectionner</option>
                    <?php foreach ($grades as $g): ?>
                        <option value="<?= (int) ($g['id'] ?? 0) ?>"><?= $h((string) (($g['code'] ?? '') . ' — ' . ($g['label'] ?? ''))) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>Filière (optionnel)
                <select name="filiere_id">
                    <option value="0">Toutes</option>
                    <?php foreach ($filieres as $f): ?>
                        <option value="<?= (int) ($f['id'] ?? 0) ?>"><?= $h((string) ($f['label'] ?? '')) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>Année
                <input type="number" name="year" min="2020" max="2100" value="<?= (int) date('Y') ?>">
            </label>
            <label>Ouverture
                <input type="date" name="opens_at" value="<?= $h(date('Y-m-d')) ?>">
            </label>
            <label>Clôture
                <input type="date" name="closes_at" value="<?= $h(date('Y-m-d', strtotime('+30 days') ?: time())) ?>">
            </label>
            <label>Quota de places
                <input type="number" name="quota_slots" min="0" placeholder="Illimité si vide">
            </label>
        </div>
        <label>Notes
            <textarea name="notes" rows="3" class="bo-adv__textarea"></textarea>
        </label>
        <div class="bo-adv__toolbar">
            <button type="submit" class="ath-btn ath-btn--solid">Ouvrir la campagne</button>
            <a class="ath-btn" href="<?= $h(url('back-office/rh/avancement')) ?>">Annuler</a>
        </div>
    </form>
</div>
