<?php
require __DIR__ . '/_helpers.php';
$grades = is_array($grades ?? null) ? $grades : [];
$filieres = is_array($filieres ?? null) ? $filieres : [];
$byFiliere = [];
foreach ($grades as $grade) {
    if (empty($grade['advancement_choice_enabled']) || !empty($grade['archived_at'])) {
        continue;
    }
    $fid = (string) ($grade['filiere_label'] ?? 'Sans filière');
    $byFiliere[$fid][] = $grade;
}
?>
<div class="adv-page">
    <?php require __DIR__ . '/_flash.php'; ?>
    <section class="adv-panel">
        <h2>Nouvelle campagne <?= adv_info('Campagne', 'Le grade visé doit être ouvert à la voie choix. Le quota est contrôlé à la publication, pas à la candidature.') ?></h2>
        <form method="post" action="<?= adv_h(url('back-office/rh/avancement/store')) ?>" class="adv-form">
            <?= \App\Core\Csrf::field() ?>
            <label>Grade visé
                <select name="grade_id" class="adv-search" data-placeholder="Choisir un grade" required>
                    <option value="">Choisir un grade ouvert au choix</option>
                    <?php foreach ($byFiliere as $filiereLabel => $group): ?>
                        <optgroup label="<?= adv_h((string) $filiereLabel) ?>">
                            <?php foreach ($group as $grade): ?>
                                <option value="<?= (int) $grade['id'] ?>"><?= adv_h((string) $grade['label']) ?> (<?= adv_h((string) ($grade['code'] ?? '')) ?>)</option>
                            <?php endforeach; ?>
                        </optgroup>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>Filière <?= adv_info('Filière', 'Laissez « celle du grade » sauf si la campagne ne concerne qu’une partie de l’échelle.') ?>
                <select name="filiere_id" class="adv-search" data-placeholder="Celle du grade">
                    <option value="">Celle du grade</option>
                    <?php foreach ($filieres as $filiere): ?>
                        <option value="<?= (int) $filiere['id'] ?>"><?= adv_h((string) $filiere['label']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <div class="adv-grid">
                <label>Année<input type="number" name="year" value="<?= (int) date('Y') ?>" required></label>
                <label>Ouverture<input type="date" name="opens_at" required></label>
                <label>Clôture<input type="date" name="closes_at" required></label>
            </div>
            <label>Quota de places <?= adv_info('Quota', 'Nombre maximal d’inscrits au tableau. Vide = pas de plafond.') ?>
                <input type="number" min="0" name="quota_slots" placeholder="Illimité si vide">
            </label>
            <div class="adv-actions">
                <button class="ath-btn ath-btn--solid" type="submit">Ouvrir la campagne</button>
                <a class="ath-btn" href="<?= adv_h(url('back-office/rh/avancement')) ?>">Retour</a>
            </div>
        </form>
    </section>
</div>
<script src="<?= adv_h(asset_url('assets/js/back-office-advancement.js')) ?>"></script>
