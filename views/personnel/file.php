<?php
$targetUser = $targetUser ?? null;
$personnelExtras = $personnelExtras ?? null;
$userProfile = $userProfile ?? null;
$grade = $grade ?? null;
$grades = $grades ?? [];
$adminPanels = $adminPanels ?? [];
$adminDataByPanel = $adminDataByPanel ?? [];
if (!$targetUser) {
    echo '<p>Utilisateur non trouvé.</p>';
    return;
}
$matricule = $personnelExtras['service_number'] ?? null;
?>
<div class="max-w-4xl mx-auto px-6 py-12">
    <h1 class="text-2xl font-black text-slate-900 mb-6">Fiche personnel</h1>

    <!-- En-tête : identité, grade (code OTAN), matricule -->
    <div class="bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden mb-8">
        <div class="p-6 border-b border-slate-100">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <p class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Opérateur</p>
                    <p class="text-xl font-bold text-slate-900"><?= htmlspecialchars($targetUser['display_name'] ?: $targetUser['email']) ?></p>
                    <?php if (!empty($targetUser['callsign'])): ?>
                    <p class="text-sm text-slate-600"><?= htmlspecialchars($targetUser['callsign']) ?></p>
                    <?php endif; ?>
                </div>
                <div class="text-right space-y-1">
                    <p class="text-xs text-slate-500">ID #<?= (int) $targetUser['id'] ?></p>
                    <?php if ($matricule): ?>
                    <p class="text-sm font-mono font-bold text-slate-900">Matricule : <?= htmlspecialchars($matricule) ?></p>
                    <?php else: ?>
                    <p class="text-xs text-slate-400 italic">Matricule non attribué</p>
                    <form method="post" action="<?= url('personnel/' . (int)$targetUser['id'] . '/generate-matricule') ?>" class="mt-2 inline">
                        <?= \App\Core\Csrf::field() ?>
                        <button type="submit" class="text-xs font-semibold text-emerald-600 hover:text-emerald-700 underline">Générer un matricule</button>
                    </form>
                    <?php endif; ?>
                    <?php if ($grade): ?>
                    <p class="text-xs">
                        <span class="font-semibold text-slate-700"><?= htmlspecialchars($grade['name']) ?></span>
                        <?php if (!empty($grade['nato_code'])): ?>
                        <span class="text-slate-500">(<?= htmlspecialchars($grade['nato_code']) ?>)</span>
                        <?php endif; ?>
                    </p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <dl class="grid grid-cols-1 md:grid-cols-2 gap-4 p-6">
            <div><dt class="text-xs font-semibold text-slate-500 uppercase">Email</dt><dd class="mt-1"><?= htmlspecialchars($targetUser['email']) ?></dd></div>
            <div><dt class="text-xs font-semibold text-slate-500 uppercase">Statut</dt><dd class="mt-1"><?= htmlspecialchars($targetUser['status'] ?? '—') ?></dd></div>
            <?php if ($personnelExtras): ?>
            <div><dt class="text-xs font-semibold text-slate-500 uppercase">Escadron</dt><dd class="mt-1"><?= htmlspecialchars($personnelExtras['squadron'] ?? '—') ?></dd></div>
            <div><dt class="text-xs font-semibold text-slate-500 uppercase">Date d'enrôlement</dt><dd class="mt-1"><?= htmlspecialchars($personnelExtras['date_of_enlistment'] ?? '—') ?></dd></div>
            <div><dt class="text-xs font-semibold text-slate-500 uppercase">Niveau de clearance</dt><dd class="mt-1"><?= htmlspecialchars($personnelExtras['clearance_level'] ?? '—') ?></dd></div>
            <div><dt class="text-xs font-semibold text-slate-500 uppercase">Readiness</dt><dd class="mt-1"><?= isset($personnelExtras['readiness_percent']) ? (int)$personnelExtras['readiness_percent'] . '%' : '—' ?></dd></div>
            <?php endif; ?>
        </dl>
    </div>

    <!-- Panneaux de données administratives -->
    <?php foreach ($adminPanels as $panel): ?>
    <?php
        $panelId = (int) $panel['id'];
        $data = $adminDataByPanel[$panelId] ?? [];
    ?>
    <section class="bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden mb-6">
        <div class="px-6 py-4 border-b border-slate-100 bg-slate-50/50">
            <h2 class="text-sm font-black text-slate-900 uppercase tracking-wide"><?= htmlspecialchars($panel['name']) ?></h2>
            <?php if (!empty($panel['description'])): ?>
            <p class="text-xs text-slate-500 mt-0.5"><?= htmlspecialchars($panel['description']) ?></p>
            <?php endif; ?>
        </div>
        <div class="p-6">
            <?php if (empty($data)): ?>
            <p class="text-sm text-slate-400 italic">Non renseigné</p>
            <?php else: ?>
            <dl class="grid grid-cols-1 md:grid-cols-2 gap-3">
                <?php foreach ($data as $key => $value): ?>
                <?php
                    if ($value === null || $value === '') continue;
                    $label = is_string($key) ? $key : 'Champ';
                    $display = is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE) : (string) $value;
                ?>
                <div>
                    <dt class="text-xs font-semibold text-slate-500 uppercase"><?= htmlspecialchars($label) ?></dt>
                    <dd class="mt-0.5 text-sm text-slate-900"><?= nl2br(htmlspecialchars($display)) ?></dd>
                </div>
                <?php endforeach; ?>
            </dl>
            <?php endif; ?>
        </div>
    </section>
    <?php endforeach; ?>

    <p class="mt-6 text-sm text-slate-500"><a href="<?= url('dashboard') ?>" class="underline">Retour au dashboard</a></p>
</div>
