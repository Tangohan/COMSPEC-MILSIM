<?php
declare(strict_types=1);

$units = is_array($units ?? null) ? $units : [];
$membersWithoutUnit = (int) ($membersWithoutUnit ?? 0);
$canManageAssignments = (bool) ($canManageAssignments ?? false);
$unitCount = count($units);

$typeLabel = static function (string $typeRaw): string {
    return match ($typeRaw) {
        'company', 'compagnie' => 'Compagnie',
        'platoon', 'peloton' => 'Peloton',
        'section' => 'Section',
        'squad', 'groupe' => 'Groupe',
        'hq', 'etat_major', 'état-major' => 'État-major',
        'team', 'equipe', 'équipe' => 'Équipe',
        'battalion', 'bataillon' => 'Bataillon',
        default => $typeRaw !== '' ? $typeRaw : '—',
    };
};
?>
<div class="eff-catalog">
    <div class="eff-catalog__head">
        <div class="min-w-0">
            <p class="eff-catalog__kicker">Structure</p>
            <h1 class="eff-catalog__title">Affectations</h1>
            <p class="eff-catalog__lead">
                Suivez les unités de rattachement et les membres encore sans unité.
                Depuis le tableur des effectifs, vous pouvez affecter une unité directement.
            </p>
        </div>
        <div class="eff-catalog__tools">
            <a class="eff-catalog__btn" href="<?= htmlspecialchars(effectifs_workspace_url() . '?sans_affectation=1', ENT_QUOTES, 'UTF-8') ?>">
                Sans unité (<?= $membersWithoutUnit ?>)
            </a>
            <?php if ($canManageAssignments): ?>
                <a class="eff-catalog__btn" href="<?= htmlspecialchars(url('back-office/organisation/structure'), ENT_QUOTES, 'UTF-8') ?>">Structure &amp; ORBAT</a>
                <a class="eff-catalog__btn" href="<?= htmlspecialchars(url('deploiement'), ENT_QUOTES, 'UTF-8') ?>">Déploiement</a>
            <?php endif; ?>
        </div>
    </div>

    <div class="eff-catalog-filters" style="grid-template-columns: repeat(2, minmax(0, 1fr)); border-bottom: 0; padding-bottom: 0.35rem;">
        <div>
            <p class="eff-catalog__kicker" style="letter-spacing:0.14em">Unités</p>
            <p style="margin:0.15rem 0 0;font-size:1.35rem;font-weight:900;color:#0f172a;font-variant-numeric:tabular-nums"><?= $unitCount ?></p>
        </div>
        <div>
            <p class="eff-catalog__kicker" style="letter-spacing:0.14em">Sans unité</p>
            <p style="margin:0.15rem 0 0;font-size:1.35rem;font-weight:900;color:#0f172a;font-variant-numeric:tabular-nums"><?= $membersWithoutUnit ?></p>
        </div>
    </div>

    <?php if ($units === []): ?>
        <div class="eff-catalog__empty">
            <strong>Aucune unité définie</strong>
            La structure ne contient pas encore d’unité de rattachement.
            <?php if ($canManageAssignments): ?>
                <p style="margin-top:1rem">
                    <a class="eff-catalog__btn eff-catalog__btn--primary" href="<?= htmlspecialchars(url('back-office/organisation/structure'), ENT_QUOTES, 'UTF-8') ?>">Ouvrir la structure</a>
                </p>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div class="eff-sheets" role="region" aria-label="Tableur des affectations" tabindex="0">
            <table class="eff-sheets__table" id="eff-affectations-table" data-cols-storage="eff-affectations-col-widths-v1">
                <colgroup>
                    <col data-col="unite" style="width:18rem">
                    <col data-col="type" style="width:10rem">
                    <col data-col="code" style="width:8rem">
                    <col data-col="actions" style="width:11rem">
                </colgroup>
                <thead>
                    <tr>
                        <th data-col="unite">Unité<span class="eff-sheets__col-resizer" role="separator" aria-orientation="vertical" aria-label="Redimensionner la colonne Unité" tabindex="0"></span></th>
                        <th data-col="type">Type<span class="eff-sheets__col-resizer" role="separator" aria-orientation="vertical" aria-label="Redimensionner la colonne Type" tabindex="0"></span></th>
                        <th data-col="code">Code<span class="eff-sheets__col-resizer" role="separator" aria-orientation="vertical" aria-label="Redimensionner la colonne Code" tabindex="0"></span></th>
                        <th data-col="actions">Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($units as $u):
                    $name = trim((string) ($u['name'] ?? ''));
                    $code = trim((string) ($u['code'] ?? ''));
                    $label = $typeLabel((string) ($u['type'] ?? ''));
                    ?>
                    <tr>
                        <td>
                            <span class="eff-sheets__cell-text"><?= htmlspecialchars($name !== '' ? $name : '—', ENT_QUOTES, 'UTF-8') ?></span>
                        </td>
                        <td>
                            <?php if ($label !== '—'): ?>
                                <span class="eff-sheets__badge eff-sheets__badge--muted"><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></span>
                            <?php else: ?>
                                <span class="eff-sheets__path-muted">—</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="eff-sheets__meta"><?= htmlspecialchars($code !== '' ? $code : '—', ENT_QUOTES, 'UTF-8') ?></span>
                        </td>
                        <td>
                            <div class="eff-sheets__actions">
                                <a href="<?= htmlspecialchars(effectifs_workspace_url(), ENT_QUOTES, 'UTF-8') ?>">Voir effectifs</a>
                                <?php if ($canManageAssignments): ?>
                                    <a href="<?= htmlspecialchars(url('back-office/organisation/structure'), ENT_QUOTES, 'UTF-8') ?>">Structure</a>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="eff-catalog-foot">
            <p style="margin:0"><?= $unitCount ?> unité<?= $unitCount > 1 ? 's' : '' ?> · <?= $membersWithoutUnit ?> membre<?= $membersWithoutUnit > 1 ? 's' : '' ?> sans unité</p>
            <div class="eff-catalog-foot__links">
                <a class="eff-catalog__btn" href="<?= htmlspecialchars(effectifs_workspace_url() . '?sans_affectation=1', ENT_QUOTES, 'UTF-8') ?>">Traiter les sans unité</a>
            </div>
        </div>

        <script>
        (function () {
            var table = document.getElementById('eff-affectations-table');
            if (!table) return;
            var storageKey = table.getAttribute('data-cols-storage') || 'eff-affectations-col-widths-v1';
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
