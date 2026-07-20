<?php
declare(strict_types=1);
$courses = $courses ?? [];
$coursesSearch = trim((string) ($coursesSearch ?? ''));
$trainingCanExportFull = !empty($trainingCanExportFull);
$trainingCanEditShowcaseOrCatalog = !empty($trainingCanEditShowcaseOrCatalog);
$trainingCanDeleteCourse = !empty($trainingCanDeleteCourse);
$flashOk = \App\Core\Session::getFlash('success');
$flashErr = \App\Core\Session::getFlash('error');
$courseCount = count($courses);
require base_path('views/admin/training/partials/command_shell_open.php');
?>
<style>
    .courses-sheets {
        border: 1px solid #cbd5e1;
        background: #ffffff;
        overflow: auto;
        max-height: min(72vh, 56rem);
        width: 100%;
    }
    .courses-sheets__table {
        width: 100%;
        min-width: 64rem;
        border-collapse: separate;
        border-spacing: 0;
        font-size: 0.8125rem;
        line-height: 1.35;
    }
    .courses-sheets__table th,
    .courses-sheets__table td {
        border-right: 1px solid #e2e8f0;
        border-bottom: 1px solid #e2e8f0;
        padding: 0.35rem 0.5rem;
        vertical-align: middle;
    }
    .courses-sheets__table th:last-child,
    .courses-sheets__table td:last-child {
        border-right: 0;
    }
    .courses-sheets__table thead th {
        position: sticky;
        top: 0;
        z-index: 2;
        background: #f1f5f9;
        color: #475569;
        font-size: 0.625rem;
        font-weight: 800;
        letter-spacing: 0.12em;
        text-transform: uppercase;
        white-space: nowrap;
        border-bottom: 1px solid #94a3b8;
        box-shadow: 0 1px 0 #94a3b8;
    }
    .courses-sheets__table tbody tr:nth-child(even) td {
        background: #f8fafc;
    }
    .courses-sheets__table tbody tr:hover td {
        background: #eff6ff;
    }
    .courses-sheets__badge {
        display: inline-flex;
        align-items: center;
        border-radius: 0.25rem;
        border: 1px solid transparent;
        padding: 0.1rem 0.4rem;
        font-size: 0.6875rem;
        font-weight: 800;
        letter-spacing: 0.02em;
        white-space: nowrap;
    }
    .courses-sheets__badge--ok {
        background: #ecfdf5;
        border-color: #a7f3d0;
        color: #065f46;
    }
    .courses-sheets__badge--muted {
        background: #f1f5f9;
        border-color: #e2e8f0;
        color: #64748b;
    }
    .courses-sheets__badge--watch {
        background: #fffbeb;
        border-color: #fde68a;
        color: #92400e;
    }
    .courses-sheets__badge--scope {
        background: #f5f3ff;
        border-color: #ddd6fe;
        color: #5b21b6;
    }
    .courses-sheets__actions {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 0.25rem 0.35rem;
    }
    .courses-sheets__actions a,
    .courses-sheets__actions button {
        display: inline-flex;
        align-items: center;
        height: 1.5rem;
        padding: 0 0.45rem;
        border-radius: 0.25rem;
        border: 1px solid #cbd5e1;
        background: #fff;
        color: #0f172a;
        font-size: 0.6875rem;
        font-weight: 700;
        text-decoration: none;
        white-space: nowrap;
        cursor: pointer;
        line-height: 1;
    }
    .courses-sheets__actions a:hover,
    .courses-sheets__actions button:hover {
        background: #f8fafc;
        border-color: #94a3b8;
    }
    .courses-sheets__actions a.is-primary {
        background: #0f172a;
        border-color: #0f172a;
        color: #fff;
    }
    .courses-sheets__actions a.is-primary:hover {
        background: #1e293b;
        border-color: #1e293b;
    }
    .courses-sheets__actions button.is-warn {
        border-color: #fde68a;
        background: #fffbeb;
        color: #92400e;
    }
    .courses-sheets__actions button.is-danger {
        border-color: #fecdd3;
        background: #fff1f2;
        color: #9f1239;
    }
    .courses-sheets__actions form {
        display: inline;
        margin: 0;
    }
</style>

                <?php if ($flashOk): ?>
                <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm font-medium text-emerald-950" role="status"><?= htmlspecialchars((string) $flashOk) ?></div>
                <?php endif; ?>
                <?php if ($flashErr): ?>
                <div class="rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-sm font-medium text-rose-950" role="alert"><?= htmlspecialchars((string) $flashErr) ?></div>
                <?php endif; ?>

                <div class="w-full max-w-none overflow-hidden rounded-xl border border-slate-300/80 bg-white shadow-sm">
                    <div class="border-b border-slate-200 bg-slate-50 px-3 py-3 sm:px-4">
                        <div class="flex flex-wrap items-end justify-between gap-2">
                            <div class="min-w-0">
                                <p class="text-[10px] font-bold uppercase tracking-[0.28em] text-slate-500">Catalogue d’édition</p>
                                <h1 class="mt-0.5 text-lg font-black tracking-tight text-slate-900 sm:text-xl">Toutes les formations</h1>
                                <p class="mt-1 text-xs text-slate-600">
                                    <?= $courseCount === 0
                                        ? 'Aucune formation pour cette communauté.'
                                        : ($courseCount === 1
                                            ? '1 formation — brouillons et parcours publiés.'
                                            : $courseCount . ' formations — brouillons et parcours publiés.') ?>
                                </p>
                            </div>
                            <div class="flex flex-wrap gap-1.5">
                                <a href="<?= training_studio_url() ?>" class="inline-flex h-8 items-center rounded border border-slate-900 bg-slate-900 px-2.5 text-xs font-semibold text-white hover:bg-slate-800">Créer dans le studio</a>
                                <a href="<?= htmlspecialchars(training_lms_admin_url()) ?>" class="inline-flex h-8 items-center rounded border border-slate-300 bg-white px-2.5 text-xs font-semibold text-slate-700 hover:bg-slate-50">Vue d’ensemble</a>
                            </div>
                        </div>
                        <form method="get" action="<?= htmlspecialchars(training_lms_admin_url('courses'), ENT_QUOTES, 'UTF-8') ?>" class="mt-2.5 flex max-w-sm gap-1.5">
                            <input type="search" name="q" value="<?= htmlspecialchars($coursesSearch, ENT_QUOTES, 'UTF-8') ?>" placeholder="Rechercher un titre ou une description…" class="h-8 flex-1 rounded border border-slate-300 px-2.5 text-xs">
                            <button type="submit" class="h-8 rounded border border-slate-300 bg-white px-2.5 text-xs font-semibold text-slate-700 hover:bg-slate-50">Rechercher</button>
                            <?php if ($coursesSearch !== ''): ?>
                            <a href="<?= htmlspecialchars(training_lms_admin_url('courses'), ENT_QUOTES, 'UTF-8') ?>" class="h-8 inline-flex items-center rounded border border-slate-200 px-2.5 text-xs font-semibold text-slate-500 hover:bg-slate-50">Réinitialiser</a>
                            <?php endif; ?>
                        </form>
                    </div>

                    <?php if ($courseCount === 0 && $coursesSearch !== ''): ?>
                    <div class="px-6 py-12 text-center">
                        <p class="text-sm font-semibold text-slate-800">Aucune formation ne correspond à « <?= htmlspecialchars($coursesSearch, ENT_QUOTES, 'UTF-8') ?> ».</p>
                        <a href="<?= htmlspecialchars(training_lms_admin_url('courses'), ENT_QUOTES, 'UTF-8') ?>" class="mt-4 inline-flex h-9 items-center rounded border border-slate-300 bg-white px-3 text-xs font-bold text-slate-700 hover:bg-slate-50">Réinitialiser la recherche</a>
                    </div>
                    <?php elseif ($courseCount === 0): ?>
                    <div class="px-6 py-12 text-center">
                        <p class="text-sm font-semibold text-slate-800">Aucune formation pour cette communauté.</p>
                        <a href="<?= training_studio_url() ?>" class="mt-4 inline-flex h-9 items-center rounded border border-emerald-600 bg-emerald-600 px-3 text-xs font-bold text-white hover:bg-emerald-500">Créer dans le studio</a>
                    </div>
                    <?php else: ?>
                    <div class="courses-sheets" role="region" aria-label="Catalogue des formations">
                        <table class="courses-sheets__table text-left">
                            <thead>
                                <tr>
                                    <th>Titre</th>
                                    <th>Code</th>
                                    <th>Thème</th>
                                    <th>Publication</th>
                                    <th>Portée</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($courses as $c): ?>
                                <?php
                                $cid = (int) $c['id'];
                                $vis = (string) ($c['visibility'] ?? '');
                                $visFr = match ($vis) {
                                    'published' => 'Publié',
                                    'private' => 'Privé',
                                    'archived' => 'Archivé',
                                    'draft' => 'Brouillon',
                                    default => $vis !== '' ? ucfirst($vis) : '—',
                                };
                                $visBadge = match ($vis) {
                                    'published' => 'courses-sheets__badge--ok',
                                    'draft' => 'courses-sheets__badge--watch',
                                    default => 'courses-sheets__badge--muted',
                                };
                                $scopeRaw = (string) ($c['lms_scope'] ?? 'tenant');
                                $scopeFr = $scopeRaw === 'platform' ? 'Toute la plateforme' : 'Cette communauté';
                                $scopeBadge = $scopeRaw === 'platform' ? 'courses-sheets__badge--scope' : 'courses-sheets__badge--muted';
                                $code = trim((string) ($c['course_code'] ?? ''));
                                $theme = trim((string) ($c['category'] ?? ''));
                                $slug = trim((string) ($c['slug'] ?? ''));
                                ?>
                                <tr>
                                    <td class="font-semibold text-slate-900"><?= htmlspecialchars((string) $c['title']) ?></td>
                                    <td class="whitespace-nowrap font-mono text-xs text-slate-700"><?= $code !== '' ? htmlspecialchars($code) : '—' ?></td>
                                    <td><?= $theme !== '' ? htmlspecialchars($theme) : '—' ?></td>
                                    <td>
                                        <span class="courses-sheets__badge <?= $visBadge ?>"><?= htmlspecialchars($visFr) ?></span>
                                    </td>
                                    <td>
                                        <span class="courses-sheets__badge <?= $scopeBadge ?>"><?= htmlspecialchars($scopeFr) ?></span>
                                    </td>
                                    <td>
                                        <div class="courses-sheets__actions" role="group" aria-label="Actions pour cette formation">
                                            <a href="<?= url('formations/' . rawurlencode($slug !== '' ? $slug : (string) $cid)) ?>" class="is-primary" target="_blank" rel="noopener" title="Ouvre la fiche visible par les membres">Ouvrir</a>
                                            <a href="<?= training_studio_url((string) $cid) ?>" title="Modifier le contenu pédagogique">Éditer</a>
                                            <?php if ($trainingCanEditShowcaseOrCatalog): ?>
                                            <a href="<?= htmlspecialchars(training_lms_admin_url('courses/' . $cid . '/showcase')) ?>" title="Carte et textes sur la page des formations">Vitrine</a>
                                            <?php endif; ?>
                                            <a href="<?= htmlspecialchars(training_lms_admin_url('enrollments') . '?course_id=' . $cid) ?>">Inscriptions</a>
                                            <?php if ($trainingCanExportFull): ?>
                                            <a href="<?= htmlspecialchars(training_lms_admin_url('courses/' . $cid . '/export')) ?>" title="Télécharger une sauvegarde complète">Dossier</a>
                                            <?php endif; ?>
                                            <?php if ($trainingCanEditShowcaseOrCatalog && $vis === 'published'): ?>
                                            <form method="post" action="<?= htmlspecialchars(training_lms_admin_url('courses/' . $cid . '/unpublish')) ?>" onsubmit="return confirm('Retirer cette formation du catalogue public ? Elle restera modifiable dans le studio.');">
                                                <?= \App\Core\Csrf::field() ?>
                                                <button type="submit" class="is-warn">Retirer</button>
                                            </form>
                                            <?php endif; ?>
                                            <?php if ($trainingCanDeleteCourse): ?>
                                            <form method="post" action="<?= htmlspecialchars(training_lms_admin_url('courses/' . $cid . '/delete')) ?>" onsubmit="return confirm('Supprimer définitivement ce parcours, les inscriptions et la progression associées ? Cette action est irréversible.');">
                                                <?= \App\Core\Csrf::field() ?>
                                                <button type="submit" class="is-danger">Supprimer</button>
                                            </form>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php endif; ?>
                </div>
<?php require base_path('views/admin/training/partials/command_shell_close.php'); ?>
