<?php
$h = static fn (mixed $v): string => htmlspecialchars(trim((string) $v), ENT_QUOTES, 'UTF-8');
$grades = is_array($grades ?? null) ? $grades : [];
$filieres = is_array($filieres ?? null) ? $filieres : [];
$templates = is_array($templates ?? null) ? $templates : [];
$personnel = is_array($personnel ?? null) ? $personnel : [];
$personLabel = static function (array $row) use ($h): string {
    $name = trim((string) ($row['display_name'] ?? ''));
    if ($name === '') {
        $name = trim((string) ($row['callsign'] ?? ''));
    }
    if ($name === '') {
        $name = trim((string) ($row['email'] ?? 'Personnel'));
    }

    return $h($name);
};
?>
<div class="adv-page">
    <?php require __DIR__ . '/_flash.php'; ?>
    <div class="adv-actions">
        <a class="ath-btn ath-btn--solid" href="<?= $h(url('back-office/organisation/grades/create')) ?>">Nouveau grade</a>
        <a class="ath-btn" href="<?= $h(url('back-office/rh/avancement')) ?>">Campagnes d’avancement</a>
    </div>

    <section class="adv-panel">
        <h2>Dupliquer une échelle</h2>
        <p>Chaque communauté reçoit sa propre copie. Rien n’est partagé avec les autres unités.</p>
        <form method="post" action="<?= $h(url('back-office/organisation/grades/importer')) ?>" class="adv-inline">
            <?= \App\Core\Csrf::field() ?>
            <select name="template">
                <?php foreach ($templates as $code => $tpl): ?>
                    <option value="<?= $h((string) $code) ?>"><?= $h((string) ($tpl['label'] ?? $code)) ?></option>
                <?php endforeach; ?>
            </select>
            <button class="ath-btn" type="submit">Dupliquer</button>
        </form>
    </section>

    <section class="adv-panel">
        <h2>Filières</h2>
        <form method="post" action="<?= $h(url('back-office/organisation/grades/filieres')) ?>" class="adv-inline">
            <?= \App\Core\Csrf::field() ?>
            <input name="code" placeholder="Code" required maxlength="64">
            <input name="label" placeholder="Libellé" required maxlength="150">
            <input name="sort_order" type="number" value="0" aria-label="Ordre">
            <button class="ath-btn" type="submit">Ajouter</button>
        </form>
        <?php if ($filieres !== []): ?>
            <ul class="adv-chips">
                <?php foreach ($filieres as $filiere): ?>
                    <li><?= $h((string) ($filiere['label'] ?? '')) ?> <span><?= $h((string) ($filiere['code'] ?? '')) ?></span></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>

    <form method="post" action="<?= $h(url('back-office/organisation/grades/ordre')) ?>" id="adv-grade-order">
        <?= \App\Core\Csrf::field() ?>
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
                    <tr draggable="true" data-grade-row>
                        <td>
                            <input type="hidden" name="order[]" value="<?= (int) ($grade['id'] ?? 0) ?>">
                            <strong><?= $h((string) ($grade['code'] ?? '')) ?></strong>
                        </td>
                        <td><?= $h((string) ($grade['label'] ?? '')) ?><?php if (!empty($grade['short_label'])): ?> <span class="adv-muted"><?= $h((string) $grade['short_label']) ?></span><?php endif; ?></td>
                        <td><?= $h((string) ($grade['filiere_label'] ?? '—')) ?></td>
                        <td class="adv-order"><?= (int) ($grade['rank_order'] ?? 0) ?></td>
                        <td><?= !empty($grade['advancement_seniority_enabled']) ? 'Oui' : 'Non' ?></td>
                        <td><?= !empty($grade['advancement_choice_enabled']) ? 'Oui' : 'Non' ?></td>
                        <td><?= $grade['min_time_in_previous_grade_months'] === null || $grade['min_time_in_previous_grade_months'] === '' ? '—' : (int) $grade['min_time_in_previous_grade_months'] . ' mois' ?></td>
                        <td><?= $h((string) ($grade['required_qualification_name'] ?? '')) ?: '—' ?></td>
                        <td><?= $archived ? 'Archivé' : 'Actif' ?></td>
                        <td class="adv-row-actions">
                            <a href="<?= $h(url('back-office/organisation/grades/' . (int) $grade['id'] . '/edit')) ?>">Modifier</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if ($grades === []): ?>
                    <tr><td colspan="10" class="adv-muted">Aucun grade. Dupliquez une échelle ci-dessus ou créez un grade.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
        <p class="adv-muted">Glissez les lignes pour définir l’ordre hiérarchique, puis enregistrez.</p>
        <button class="ath-btn ath-btn--solid" type="submit">Enregistrer l’ordre</button>
    </form>

    <section class="adv-panel">
        <h2>Grade initial</h2>
        <p>Première ligne d’historique uniquement. Une correction ultérieure ne réécrit pas cette ligne.</p>
        <form method="post" action="<?= $h(url('back-office/organisation/grades/initial')) ?>" class="adv-inline">
            <?= \App\Core\Csrf::field() ?>
            <select name="personnel_id" required>
                <option value="">Personnel</option>
                <?php foreach ($personnel as $person): ?>
                    <option value="<?= (int) $person['id'] ?>"><?= $personLabel($person) ?></option>
                <?php endforeach; ?>
            </select>
            <select name="grade_id" required>
                <option value="">Grade</option>
                <?php foreach ($grades as $grade): ?>
                    <?php if (!empty($grade['archived_at'])) { continue; } ?>
                    <option value="<?= (int) $grade['id'] ?>"><?= $h((string) $grade['label']) ?></option>
                <?php endforeach; ?>
            </select>
            <input type="date" name="obtained_at" required>
            <button class="ath-btn" type="submit">Attribuer</button>
        </form>
    </section>
</div>
<script>
(function () {
    var body = document.getElementById('adv-grade-body');
    if (!body) return;
    var dragged = null;
    body.addEventListener('dragstart', function (event) {
        dragged = event.target.closest('[data-grade-row]');
        if (dragged) dragged.classList.add('is-dragging');
    });
    body.addEventListener('dragend', function () {
        if (dragged) dragged.classList.remove('is-dragging');
        dragged = null;
        renumber();
    });
    body.addEventListener('dragover', function (event) {
        event.preventDefault();
        var target = event.target.closest('[data-grade-row]');
        if (!dragged || !target || target === dragged) return;
        var rect = target.getBoundingClientRect();
        var after = event.clientY > rect.top + rect.height / 2;
        body.insertBefore(dragged, after ? target.nextSibling : target);
    });
    function renumber() {
        body.querySelectorAll('[data-grade-row]').forEach(function (row, index) {
            var cell = row.querySelector('.adv-order');
            if (cell) cell.textContent = String(index + 1);
        });
    }
})();
</script>
