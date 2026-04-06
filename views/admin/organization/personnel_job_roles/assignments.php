<?php
$assignmentRows = $assignmentRows ?? [];
$jobRoleOptions = $jobRoleOptions ?? [];
$filters = $filters ?? [];
$assignmentsPage = (int) ($assignmentsPage ?? 1);
$assignmentsTotal = (int) ($assignmentsTotal ?? 0);
$assignmentsPerPage = (int) ($assignmentsPerPage ?? 30);
$assignmentsTotalPages = (int) ($assignmentsTotalPages ?? 1);
$activeTab = $activeTab ?? 'assignments';
$flashSuccess = \App\Core\Session::getFlash('success');
$flashError = \App\Core\Session::getFlash('error');

$returnQuery = http_build_query(array_filter([
    'search' => $filters['search'] ?? '',
    'job_role_id' => !empty($filters['job_role_id']) ? (int) $filters['job_role_id'] : null,
    'unassigned' => !empty($filters['unassigned']) ? '1' : null,
    'page' => $assignmentsPage > 1 ? $assignmentsPage : null,
], static fn ($v) => $v !== null && $v !== ''));

$baseUrl = url('back-office/personnel-job-roles/assignments');
?>
<div class="mx-auto max-w-7xl px-6 py-12">
    <?php require __DIR__ . '/_nav.php'; ?>

    <div class="mb-6 flex flex-wrap items-end justify-between gap-4">
        <div>
            <h1 class="text-2xl font-black text-slate-900">Attributions rôles métier</h1>
            <p class="mt-1 max-w-3xl text-sm text-slate-600">Attribuez le référentiel de fonction (dossier personnel) à chaque membre. Le libellé ORBAT / <code class="rounded bg-slate-100 px-1 text-xs">primary_role</code> est recalculé ; si une unité principale est définie dans le dossier, l’affectation ORBAT est resynchronisée.</p>
        </div>
        <a href="<?= url('back-office/personnel-job-roles') ?>" class="text-sm font-medium text-slate-600 hover:underline">Référentiel &amp; catégories</a>
    </div>

    <?php if ($flashSuccess): ?>
    <p class="mb-4 rounded bg-emerald-50 px-3 py-2 text-sm text-emerald-800"><?= htmlspecialchars($flashSuccess) ?></p>
    <?php endif; ?>
    <?php if ($flashError): ?>
    <p class="mb-4 rounded bg-red-50 px-3 py-2 text-sm text-red-800"><?= htmlspecialchars($flashError) ?></p>
    <?php endif; ?>

    <form method="get" action="<?= htmlspecialchars($baseUrl) ?>" class="mb-8 flex flex-wrap items-end gap-4 rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
        <div class="min-w-[200px] flex-1">
            <label class="mb-1 block text-xs font-semibold text-slate-600">Recherche</label>
            <input type="text" name="search" value="<?= htmlspecialchars((string) ($filters['search'] ?? '')) ?>" placeholder="Nom, email, indicatif…" class="w-full rounded border border-slate-200 px-3 py-2 text-sm">
        </div>
        <div class="min-w-[220px]">
            <label class="mb-1 block text-xs font-semibold text-slate-600">Rôle métier</label>
            <select name="job_role_id" class="w-full rounded border border-slate-200 px-3 py-2 text-sm">
                <option value="0">— Tous —</option>
                <?php foreach ($jobRoleOptions as $jo): ?>
                <option value="<?= (int) $jo['id'] ?>" <?= (int) ($filters['job_role_id'] ?? 0) === (int) $jo['id'] ? 'selected' : '' ?>><?= htmlspecialchars($jo['label']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="flex items-center gap-2 pb-2">
            <input type="checkbox" name="unassigned" id="unassigned" value="1" <?= !empty($filters['unassigned']) ? 'checked' : '' ?>>
            <label for="unassigned" class="text-sm text-slate-700">Sans rôle métier</label>
        </div>
        <button type="submit" class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">Filtrer</button>
    </form>

    <div class="mb-4 text-sm text-slate-600">
        <?= (int) $assignmentsTotal ?> membre(s) · page <?= (int) $assignmentsPage ?> / <?= (int) $assignmentsTotalPages ?>
    </div>

    <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white shadow-sm">
        <table class="w-full min-w-[960px] border-collapse text-left text-sm">
            <thead class="border-b border-slate-200 bg-slate-50">
                <tr>
                    <th class="p-3 text-xs font-semibold uppercase text-slate-600">Membre</th>
                    <th class="p-3 text-xs font-semibold uppercase text-slate-600">Statut</th>
                    <th class="p-3 text-xs font-semibold uppercase text-slate-600" colspan="4">Attribution (dossier)</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($assignmentRows as $row): ?>
                <?php
                $uid = (int) ($row['id'] ?? 0);
                $slug = trim((string) ($row['profile_slug'] ?? ''));
                $personnelUrl = url('personnel/' . ($slug !== '' ? $slug : (string) $uid));
                $curJr = isset($row['personnel_job_role_id']) ? (int) $row['personnel_job_role_id'] : 0;
                ?>
                <tr class="border-b border-slate-100 align-top hover:bg-slate-50/80">
                    <td class="p-3">
                        <p class="font-semibold text-slate-900"><?= htmlspecialchars((string) ($row['display_name'] ?? '—')) ?></p>
                        <p class="text-xs text-slate-500"><?= htmlspecialchars((string) ($row['email'] ?? '')) ?></p>
                        <?php if (trim((string) ($row['callsign'] ?? '')) !== ''): ?>
                        <p class="text-xs font-mono text-slate-600"><?= htmlspecialchars((string) $row['callsign']) ?></p>
                        <?php endif; ?>
                        <a href="<?= htmlspecialchars($personnelUrl) ?>" class="mt-1 inline-block text-xs font-medium text-cyan-700 hover:underline">Fiche personnelle</a>
                    </td>
                    <td class="p-3 text-xs uppercase text-slate-600"><?= htmlspecialchars((string) ($row['status'] ?? '')) ?></td>
                    <td class="p-3" colspan="4">
                        <form method="post" action="<?= url('back-office/personnel-job-roles/assignments/save') ?>" class="flex flex-col gap-3 lg:flex-row lg:items-end">
                            <?= \App\Core\Csrf::field() ?>
                            <input type="hidden" name="user_id" value="<?= $uid ?>">
                            <input type="hidden" name="return_query" value="<?= htmlspecialchars($returnQuery, ENT_QUOTES, 'UTF-8') ?>">
                            <div class="min-w-[200px] flex-1">
                                <label class="mb-1 block text-[10px] font-bold uppercase text-slate-500">Rôle métier</label>
                                <select name="personnel_job_role_id" class="w-full rounded border border-slate-200 px-2 py-1.5 text-xs">
                                    <option value="">— Aucun —</option>
                                    <?php foreach ($jobRoleOptions as $jo): ?>
                                    <option value="<?= (int) $jo['id'] ?>" <?= $curJr === (int) $jo['id'] ? 'selected' : '' ?>><?= htmlspecialchars($jo['label']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="min-w-[160px] flex-1">
                                <label class="mb-1 block text-[10px] font-bold uppercase text-slate-500">Sous-rôle</label>
                                <input type="text" name="role_sub_label" value="<?= htmlspecialchars((string) ($row['role_sub_label'] ?? '')) ?>" class="w-full rounded border border-slate-200 px-2 py-1.5 text-xs" maxlength="150" placeholder="Optionnel">
                            </div>
                            <div class="min-w-[180px] flex-1 rounded border border-dashed border-slate-200 bg-slate-50/80 px-2 py-1.5 text-xs text-slate-600" title="Valeur enregistrée (ORBAT / affichages)">
                                <span class="font-bold text-slate-500">Libellé :</span>
                                <?= htmlspecialchars((string) ($row['primary_role'] ?? '—')) ?>
                            </div>
                            <div class="shrink-0">
                                <button type="submit" class="rounded-lg bg-emerald-700 px-4 py-2 text-xs font-bold text-white hover:bg-emerald-800">Enregistrer</button>
                            </div>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php if (empty($assignmentRows)): ?>
        <p class="p-8 text-center text-slate-500">Aucun membre ne correspond aux filtres.</p>
        <?php endif; ?>
    </div>

    <?php if ($assignmentsTotalPages > 1): ?>
    <div class="mt-6 flex flex-wrap justify-center gap-2">
        <?php for ($p = 1; $p <= $assignmentsTotalPages; $p++): ?>
        <?php
        $pageQs = http_build_query(array_filter([
            'search' => $filters['search'] ?? '',
            'job_role_id' => !empty($filters['job_role_id']) ? (int) $filters['job_role_id'] : null,
            'unassigned' => !empty($filters['unassigned']) ? '1' : null,
            'page' => $p > 1 ? $p : null,
        ], static fn ($v) => $v !== null && $v !== ''));
        $href = $baseUrl . ($pageQs !== '' ? '?' . $pageQs : '');
        ?>
        <a href="<?= htmlspecialchars($href) ?>" class="min-w-[2.25rem] rounded border px-3 py-1.5 text-sm <?= $p === $assignmentsPage ? 'border-slate-900 bg-slate-900 font-bold text-white' : 'border-slate-200 bg-white text-slate-700 hover:bg-slate-50' ?>"><?= $p ?></a>
        <?php endfor; ?>
    </div>
    <?php endif; ?>
</div>
