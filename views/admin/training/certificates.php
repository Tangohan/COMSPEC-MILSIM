<?php
$certificates = $certificates ?? [];
?>
<div class="max-w-5xl mx-auto px-6 py-12">
    <h1 class="text-2xl font-black text-slate-900 mb-8">Certificats</h1>
    <?php if (empty($certificates)): ?>
    <p class="text-slate-500">Aucun certificat délivré.</p>
    <?php else: ?>
    <table class="w-full border border-slate-200 rounded-lg overflow-hidden">
        <thead class="bg-slate-50 border-b border-slate-200">
            <tr>
                <th class="text-left p-3 text-xs font-semibold text-slate-600 uppercase">N°</th>
                <th class="text-left p-3 text-xs font-semibold text-slate-600 uppercase">Formation</th>
                <th class="text-left p-3 text-xs font-semibold text-slate-600 uppercase">Délivré le</th>
                <th class="text-left p-3 text-xs font-semibold text-slate-600 uppercase">Expire</th>
                <th class="text-left p-3 text-xs font-semibold text-slate-600 uppercase">Statut</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($certificates as $c): ?>
            <tr class="border-b border-slate-100 hover:bg-slate-50">
                <td class="p-3 font-mono text-sm"><?= htmlspecialchars($c['certificate_number'] ?? '') ?></td>
                <td class="p-3"><?= htmlspecialchars($c['course_title'] ?? '') ?></td>
                <td class="p-3"><?= !empty($c['issued_at']) ? date('d/m/Y', strtotime($c['issued_at'])) : '—' ?></td>
                <td class="p-3"><?= !empty($c['expires_at']) ? date('d/m/Y', strtotime($c['expires_at'])) : '—' ?></td>
                <td class="p-3">
                    <span class="px-2 py-0.5 text-xs rounded <?= ($c['status'] ?? '') === 'valid' ? 'bg-emerald-100 text-emerald-800' : (($c['status'] ?? '') === 'revoked' ? 'bg-rose-100 text-rose-800' : 'bg-slate-200 text-slate-600') ?>"><?= htmlspecialchars($c['status'] ?? '') ?></span>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
    <p class="mt-8 text-sm text-slate-500"><a href="<?= url('admin/training') ?>" class="underline">Retour tableau de bord</a></p>
</div>
