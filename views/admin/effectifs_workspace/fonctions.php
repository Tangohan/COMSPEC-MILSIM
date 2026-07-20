<?php
declare(strict_types=1);

$jobRoles = is_array($jobRoles ?? null) ? $jobRoles : [];
$canManageAssignments = (bool) ($canManageAssignments ?? false);
$total = count($jobRoles);
$categories = [];
foreach ($jobRoles as $jr) {
    $cat = trim((string) ($jr['category_name'] ?? ''));
    if ($cat !== '') {
        $categories[$cat] = true;
    }
}
$categoryCount = count($categories);
?>
<div class="eff-catalog">
    <div class="eff-catalog__head">
        <div class="min-w-0">
            <p class="eff-catalog__kicker">Emplois métier</p>
            <h1 class="eff-catalog__title">Fonctions</h1>
            <p class="eff-catalog__lead">
                Les fonctions figurent sur les dossiers personnel — radio, médic, logistique, etc. —
                distinctes des rôles d’administration.
            </p>
        </div>
        <?php if ($canManageAssignments): ?>
        <div class="eff-catalog__tools">
            <a class="eff-catalog__btn eff-catalog__btn--primary" href="<?= htmlspecialchars(url('back-office/personnel-job-roles'), ENT_QUOTES, 'UTF-8') ?>">Référentiel des emplois</a>
            <a class="eff-catalog__btn" href="<?= htmlspecialchars(url('back-office/personnel-job-roles/assignments'), ENT_QUOTES, 'UTF-8') ?>">Attributions</a>
        </div>
        <?php endif; ?>
    </div>

    <div class="eff-catalog-filters" style="grid-template-columns: repeat(2, minmax(0, 1fr)); border-bottom: 0; padding-bottom: 0.35rem;">
        <div>
            <p class="eff-catalog__kicker" style="letter-spacing:0.14em">Fonctions</p>
            <p style="margin:0.15rem 0 0;font-size:1.35rem;font-weight:900;color:#0f172a;font-variant-numeric:tabular-nums"><?= $total ?></p>
        </div>
        <div>
            <p class="eff-catalog__kicker" style="letter-spacing:0.14em">Catégories</p>
            <p style="margin:0.15rem 0 0;font-size:1.35rem;font-weight:900;color:#0f172a;font-variant-numeric:tabular-nums"><?= $categoryCount ?></p>
        </div>
    </div>

    <?php if ($jobRoles === []): ?>
        <div class="eff-catalog__empty">
            <strong>Aucun emploi métier défini</strong>
            Cette communauté n’a pas encore de fonctions dans le référentiel.
            <?php if ($canManageAssignments): ?>
                <p style="margin-top:1rem">
                    <a class="eff-catalog__btn eff-catalog__btn--primary" href="<?= htmlspecialchars(url('back-office/personnel-job-roles'), ENT_QUOTES, 'UTF-8') ?>">Créer le référentiel</a>
                </p>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div class="eff-sheets" role="region" aria-label="Tableur des fonctions" tabindex="0">
            <table class="eff-sheets__table" id="eff-fonctions-table" data-cols-storage="eff-fonctions-col-widths-v1">
                <colgroup>
                    <col data-col="libelle" style="width:14rem">
                    <col data-col="categorie" style="width:11rem">
                    <col data-col="description" style="width:28rem">
                    <col data-col="actions" style="width:9rem">
                </colgroup>
                <thead>
                    <tr>
                        <th data-col="libelle">Libellé<span class="eff-sheets__col-resizer" role="separator" aria-orientation="vertical" aria-label="Redimensionner la colonne Libellé" tabindex="0"></span></th>
                        <th data-col="categorie">Catégorie<span class="eff-sheets__col-resizer" role="separator" aria-orientation="vertical" aria-label="Redimensionner la colonne Catégorie" tabindex="0"></span></th>
                        <th data-col="description">Description<span class="eff-sheets__col-resizer" role="separator" aria-orientation="vertical" aria-label="Redimensionner la colonne Description" tabindex="0"></span></th>
                        <th data-col="actions">Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($jobRoles as $jr):
                    $name = trim((string) ($jr['name'] ?? ''));
                    $category = trim((string) ($jr['category_name'] ?? ''));
                    $description = trim((string) ($jr['description'] ?? ''));
                    ?>
                    <tr>
                        <td>
                            <span class="eff-sheets__cell-text"><?= htmlspecialchars($name !== '' ? $name : '—', ENT_QUOTES, 'UTF-8') ?></span>
                        </td>
                        <td>
                            <?php if ($category !== ''): ?>
                                <span class="eff-sheets__badge eff-sheets__badge--muted"><?= htmlspecialchars($category, ENT_QUOTES, 'UTF-8') ?></span>
                            <?php else: ?>
                                <span class="eff-sheets__path-muted">—</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="eff-sheets__path" title="<?= htmlspecialchars($description !== '' ? $description : '', ENT_QUOTES, 'UTF-8') ?>">
                                <?= htmlspecialchars($description !== '' ? $description : '—', ENT_QUOTES, 'UTF-8') ?>
                            </span>
                        </td>
                        <td>
                            <div class="eff-sheets__actions">
                                <?php if ($canManageAssignments): ?>
                                    <a href="<?= htmlspecialchars(url('back-office/personnel-job-roles'), ENT_QUOTES, 'UTF-8') ?>">Éditer</a>
                                <?php else: ?>
                                    <span class="eff-sheets__path-muted">—</span>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="eff-catalog-foot">
            <p style="margin:0"><?= $total ?> fonction<?= $total > 1 ? 's' : '' ?> dans le référentiel</p>
        </div>

        <script>
        (function () {
            var table = document.getElementById('eff-fonctions-table');
            if (!table) return;
            var storageKey = table.getAttribute('data-cols-storage') || 'eff-fonctions-col-widths-v1';
            var cols = table.querySelectorAll('colgroup col[data-col]');
            var minWidth = 56;

            function applyWidths(map) {
                cols.forEach(function (col) {
                    var key = col.getAttribute('data-col');
                    if (!key || !map[key]) return;
                    var w = parseInt(map[key], 10);
                    if (!isFinite(w) || w < minWidth) return;
                    col.style.width = w + 'px';
                });
            }

            function readStored() {
                try {
                    var raw = localStorage.getItem(storageKey);
                    if (!raw) return {};
                    var parsed = JSON.parse(raw);
                    return parsed && typeof parsed === 'object' ? parsed : {};
                } catch (e) {
                    return {};
                }
            }

            function writeStored(map) {
                try {
                    localStorage.setItem(storageKey, JSON.stringify(map));
                } catch (e) { /* ignore */ }
            }

            function currentMap() {
                var map = {};
                cols.forEach(function (col) {
                    var key = col.getAttribute('data-col');
                    if (!key) return;
                    var w = parseInt(col.style.width, 10);
                    if (!isFinite(w) || w < minWidth) {
                        w = Math.round(col.getBoundingClientRect().width);
                    }
                    if (isFinite(w) && w >= minWidth) map[key] = w;
                });
                return map;
            }

            applyWidths(readStored());

            table.querySelectorAll('thead th .eff-sheets__col-resizer').forEach(function (handle) {
                var th = handle.closest('th');
                if (!th) return;
                var colKey = th.getAttribute('data-col');
                if (!colKey) return;
                var col = table.querySelector('colgroup col[data-col="' + colKey + '"]');
                if (!col) return;

                function startResize(clientX) {
                    var startX = clientX;
                    var startW = col.getBoundingClientRect().width;
                    document.body.classList.add('eff-sheets--resizing');

                    function onMove(ev) {
                        var next = Math.max(minWidth, Math.round(startW + (ev.clientX - startX)));
                        col.style.width = next + 'px';
                    }

                    function onUp() {
                        document.body.classList.remove('eff-sheets--resizing');
                        document.removeEventListener('mousemove', onMove);
                        document.removeEventListener('mouseup', onUp);
                        writeStored(currentMap());
                    }

                    document.addEventListener('mousemove', onMove);
                    document.addEventListener('mouseup', onUp);
                }

                handle.addEventListener('mousedown', function (ev) {
                    ev.preventDefault();
                    ev.stopPropagation();
                    startResize(ev.clientX);
                });

                handle.addEventListener('keydown', function (ev) {
                    var step = ev.shiftKey ? 24 : 8;
                    var w = Math.round(col.getBoundingClientRect().width);
                    if (ev.key === 'ArrowLeft') {
                        ev.preventDefault();
                        col.style.width = Math.max(minWidth, w - step) + 'px';
                        writeStored(currentMap());
                    } else if (ev.key === 'ArrowRight') {
                        ev.preventDefault();
                        col.style.width = (w + step) + 'px';
                        writeStored(currentMap());
                    }
                });
            });
        })();
        </script>
    <?php endif; ?>
</div>
