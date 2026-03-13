<?php
$targetUser = $targetUser ?? null;
$personnelExtras = $personnelExtras ?? null;
$userProfile = $userProfile ?? null;
if (!$targetUser) {
    echo '<p>Utilisateur non trouvé.</p>';
    return;
}
$gradeName = $targetUser['grade_id'] ? '—' : '';
?>
<div class="max-w-4xl mx-auto px-6 py-12">
    <h1 class="text-2xl font-black text-slate-900 mb-6">Fiche personnel</h1>
    <div class="bg-white border border-slate-200 rounded-lg shadow-sm overflow-hidden">
        <div class="p-6 border-b border-slate-100">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Opérateur</p>
                    <p class="text-xl font-bold text-slate-900"><?= htmlspecialchars($targetUser['display_name'] ?: $targetUser['email']) ?></p>
                    <?php if (!empty($targetUser['callsign'])): ?>
                    <p class="text-sm text-slate-600"><?= htmlspecialchars($targetUser['callsign']) ?></p>
                    <?php endif; ?>
                </div>
                <div class="text-right text-sm text-slate-500">
                    <p>ID #<?= (int) $targetUser['id'] ?></p>
                    <?php if ($personnelExtras && !empty($personnelExtras['service_number'])): ?>
                    <p><?= htmlspecialchars($personnelExtras['service_number']) ?></p>
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
    <p class="mt-6 text-sm text-slate-500"><a href="<?= url('dashboard') ?>" class="underline">Retour au dashboard</a></p>
</div>
