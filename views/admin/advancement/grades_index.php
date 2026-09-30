<?php
require __DIR__ . '/_helpers.php';
$grades = is_array($grades ?? null) ? $grades : [];
$filieres = is_array($filieres ?? null) ? $filieres : [];
$templates = is_array($templates ?? null) ? $templates : [];
$personnel = is_array($personnel ?? null) ? $personnel : [];
$gradeOrder = is_array($gradeOrder ?? null) ? $gradeOrder : ['order' => [], 'detections' => []];
$detections = is_array($gradeOrder['detections'] ?? null) ? $gradeOrder['detections'] : [];
$byFiliere = [];
foreach ($grades as $grade) {
    if (!empty($grade['archived_at'])) {
        continue;
    }
    $fid = (string) ($grade['filiere_label'] ?? 'Sans filière');
    $byFiliere[$fid][] = $grade;
}
?>
<div class="adv-page" data-adv-page="grades">
    <?php require __DIR__ . '/_flash.php'; ?>
    <div class="adv-actions">
        <a class="ath-btn ath-btn--solid" href="<?= adv_h(url('back-office/organisation/grades/create')) ?>">Nouveau grade</a>
        <a class="ath-btn" href="<?= adv_h(url('back-office/rh/avancement')) ?>">Campagnes d’avancement</a>
    </div>

    <section class="adv-panel">
        <h2>Dupliquer une échelle <?= adv_info('Échelle', 'Copie propre à la communauté. Les grades déjà créés ne sont pas écrasés : seuls les codes manquants s’ajoutent.') ?></h2>
        <form method="post" action="<?= adv_h(url('back-office/organisation/grades/importer')) ?>" class="adv-inline">
            <?= \App\Core\Csrf::field() ?>
            <select name="template" class="adv-search" data-placeholder="Choisir un modèle">
                <?php foreach ($templates as $code => $tpl): ?>
                    <option value="<?= adv_h((string) $code) ?>"><?= adv_h((string) ($tpl['label'] ?? $code)) ?></option>
                <?php endforeach; ?>
            </select>
            <button class="ath-btn" type="submit">Dupliquer</button>
        </form>
    </section>

    <section class="adv-panel">
        <h2>Filières <?= adv_info('Filières', 'L’avancement se calcule dans une même filière : militaire du rang, sous-officier, officier… Un caporal n’est pas comparé à un lieutenant.') ?></h2>
        <form method="post" action="<?= adv_h(url('back-office/organisation/grades/filieres')) ?>" class="adv-inline">
            <?= \App\Core\Csrf::field() ?>
            <input name="code" placeholder="Code" required maxlength="64">
            <input name="label" placeholder="Libellé" required maxlength="150">
            <input name="sort_order" type="number" value="0" aria-label="Ordre d’affichage des filières">
            <button class="ath-btn" type="submit">Ajouter</button>
        </form>
        <?php if ($filieres !== []): ?>
            <ul class="adv-chips">
                <?php foreach ($filieres as $filiere): ?>
                    <li><?= adv_h((string) ($filiere['label'] ?? '')) ?> <span><?= adv_h((string) ($filiere['code'] ?? '')) ?></span></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>

    <?php require __DIR__ . '/_detections.php'; ?>

    <form method="post" action="<?= adv_h(url('back-office/organisation/grades/ordre')) ?>" id="adv-grade-order">
        <?= \App\Core\Csrf::field() ?>
        <div class="adv-section-head">
            <h2>Hiérarchie <?= adv_info('Ordre', 'Dans chaque filière, 1 est le plus junior. Glissez les lignes, ou alignez automatiquement selon les codes et libellés connus (OTAN, grades FR/US).') ?></h2>
            <button class="ath-btn" type="submit" form="adv-grade-auto">Ordonner automatiquement</button>
        </div>
        <div class="adv-table-wrap">
            <table class="adv-table">
                <thead>
                    <tr>
                        <th>Code</th>
                        <th>Libellé</th>
                        <th>Filière</th>
                        <th>Ordre</th>
                        <th>Voie ancienneté</th>
                        <th>Voie choix</th>
                        <th>Temps mini</th>
                        <th>Qualification requise</th>
                        <th>Statut</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody id="adv-grade-body">
                <?php foreach ($grades as $grade): ?>
                    <?php $archived = !empty($grade['archived_at']); ?>
                    <tr draggable="<?= $archived ? 'false' : 'true' ?>" data-grade-row data-filiere="<?= (int) ($grade['filiere_id'] ?? 0) ?>">
                        <td>
                            <input type="hidden" name="order[]" value="<?= (int) ($grade['id'] ?? 0) ?>">
                            <strong><?= adv_h((string) ($grade['code'] ?? '')) ?></strong>
                        </td>
                        <td><?= adv_h((string) ($grade['label'] ?? '')) ?><?php if (!empty($grade['short_label'])): ?> <span class="adv-muted"><?= adv_h((string) $grade['short_label']) ?></span><?php endif; ?></td>
                        <td><?= adv_h((string) ($grade['filiere_label'] ?? '—')) ?></td>
                        <td class="adv-order"><?= (int) ($grade['rank_order'] ?? 0) ?></td>
                        <td><?= !empty($grade['advancement_seniority_enabled']) ? 'Oui' : 'Non' ?></td>
                        <td><?= !empty($grade['advancement_choice_enabled']) ? 'Oui' : 'Non' ?></td>
                        <td><?= $grade['min_time_in_previous_grade_months'] === null || $grade['min_time_in_previous_grade_months'] === '' ? '—' : (int) $grade['min_time_in_previous_grade_months'] . ' mois' ?></td>
                        <td><?= adv_h((string) ($grade['required_qualification_name'] ?? '')) ?: '—' ?></td>
                        <td><?= $archived ? 'Archivé' : 'Actif' ?></td>
                        <td class="adv-row-actions">
                            <a href="<?= adv_h(url('back-office/organisation/grades/' . (int) $grade['id'] . '/edit')) ?>">Modifier</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if ($grades === []): ?>
                    <tr><td colspan="10" class="adv-muted">Aucun grade. Dupliquez une échelle ci-dessus ou créez un grade.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
        <p class="adv-muted">Glissez les lignes pour l’ordre dans chaque filière, puis enregistrez. L’auto-ordre détecte soldat → généraux, enlisted → officers, etc.</p>
        <button class="ath-btn ath-btn--solid" type="submit">Enregistrer l’ordre</button>
    </form>
    <form method="post" id="adv-grade-auto" action="<?= adv_h(url('back-office/organisation/grades/ordre-auto')) ?>">
        <?= \App\Core\Csrf::field() ?>
    </form>

    <section class="adv-panel">
        <h2>Grade initial <?= adv_info('Grade initial', 'Première ligne d’historique uniquement. Ensuite, on n’écrase jamais : ancienneté, choix ou ligne de correction.') ?></h2>
        <form method="post" action="<?= adv_h(url('back-office/organisation/grades/initial')) ?>" class="adv-inline">
            <?= \App\Core\Csrf::field() ?>
            <select name="personnel_id" class="adv-search" data-placeholder="Personnel" required>
                <option value="">Personnel</option>
                <?php foreach ($personnel as $person): ?>
                    <option value="<?= (int) $person['id'] ?>"><?= adv_h(adv_option($person)) ?></option>
                <?php endforeach; ?>
            </select>
            <select name="grade_id" class="adv-search" data-placeholder="Grade" required>
                <option value="">Grade</option>
                <?php foreach ($byFiliere as $filiereLabel => $group): ?>
                    <optgroup label="<?= adv_h((string) $filiereLabel) ?>">
                        <?php foreach ($group as $grade): ?>
                            <option value="<?= (int) $grade['id'] ?>"><?= adv_h((string) $grade['label']) ?> (<?= adv_h((string) ($grade['code'] ?? '')) ?>)</option>
                        <?php endforeach; ?>
                    </optgroup>
                <?php endforeach; ?>
            </select>
            <input type="date" name="obtained_at" required>
            <button class="ath-btn" type="submit">Attribuer</button>
        </form>
    </section>
</div>
<script src="<?= adv_h(asset_url('assets/js/back-office-advancement.js')) ?>"></script>
