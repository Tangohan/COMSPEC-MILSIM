<?php
$document = $document ?? null;
$auditEntries = $auditEntries ?? [];
$usersMap = $usersMap ?? [];
if (!$document) {
    echo '<p>Document non trouvé.</p>';
    return;
}
$docId = (int)$document['id'];
?>
<div class="max-w-4xl mx-auto px-6 py-12">
    <div class="mb-6">
        <a href="<?= url('documents/gestion/' . $docId . '/modifier') ?>" class="text-sm text-slate-500 hover:text-slate-900">← Retour au document</a>
        <h1 class="text-2xl font-black text-slate-900 mt-2">Historique — <?= htmlspecialchars($document['title']) ?></h1>
    </div>

    <div class="bg-white border border-slate-200 rounded-lg overflow-hidden">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 border-b border-slate-200">
                <tr>
                    <th class="text-left p-3 font-semibold text-slate-700">Date</th>
                    <th class="text-left p-3 font-semibold text-slate-700">Action</th>
                    <th class="text-left p-3 font-semibold text-slate-700">Utilisateur</th>
                    <th class="text-left p-3 font-semibold text-slate-700">Détails</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($auditEntries)): ?>
                <tr><td colspan="4" class="p-4 text-slate-500">Aucune entrée d'audit.</td></tr>
                <?php else: ?>
                <?php foreach ($auditEntries as $e): ?>
                <tr class="border-b border-slate-100 hover:bg-slate-50">
                    <td class="p-3 text-slate-600"><?= !empty($e['created_at']) ? date('d/m/Y H:i:s', strtotime($e['created_at'])) : '' ?></td>
                    <td class="p-3"><?= htmlspecialchars($e['action']) ?></td>
                    <td class="p-3"><?= htmlspecialchars($usersMap[$e['user_id'] ?? 0] ?? '—') ?></td>
                    <td class="p-3 text-slate-600 max-w-xs truncate" title="<?= htmlspecialchars($e['new_value'] ?? '') ?>">
                        <?php
                        if (!empty($e['new_value'])) {
                            $dec = json_decode($e['new_value'], true);
                            echo $dec ? htmlspecialchars(substr(json_encode($dec, JSON_UNESCAPED_UNICODE), 0, 80)) . (strlen($e['new_value']) > 80 ? '…' : '') : htmlspecialchars(substr($e['new_value'], 0, 80));
                        } else {
                            echo '—';
                        }
                        ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
