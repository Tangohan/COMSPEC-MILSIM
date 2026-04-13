<?php
declare(strict_types=1);
$campaigns = $campaigns ?? [];
?>
<div class="max-w-5xl mx-auto px-6 py-10">
    <h1 class="text-2xl font-black text-slate-900 mb-2">Historique des envois</h1>
    <p class="text-sm text-slate-600 mb-6"><a href="<?= url('back-office/communications') ?>" class="text-blue-700 font-semibold hover:underline">← Rédaction</a></p>

    <?php if (empty($campaigns)): ?>
        <p class="text-slate-500">Aucun envoi enregistré pour l’instant.</p>
    <?php else: ?>
    <div class="overflow-x-auto border border-slate-200 rounded-lg">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 border-b border-slate-200">
                <tr>
                    <th class="text-left p-3 font-semibold text-slate-600">Date</th>
                    <th class="text-left p-3 font-semibold text-slate-600">Famille</th>
                    <th class="text-left p-3 font-semibold text-slate-600">Objet</th>
                    <th class="text-left p-3 font-semibold text-slate-600">Destinataires</th>
                    <th class="text-left p-3 font-semibold text-slate-600">État</th>
                    <th class="text-left p-3 font-semibold text-slate-600">Détail</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($campaigns as $c): ?>
                <?php
                    $kind = (string) ($c['kind'] ?? '');
                    $kindLabel = \App\Support\TenantEmailKind::label($kind);
                    $st = (string) ($c['status'] ?? '');
                    $stLabel = match ($st) {
                        'completed' => 'Terminé',
                        'queued' => 'En cours',
                        'failed_partial' => 'Partiel',
                        default => $st,
                    };
                ?>
                <tr class="border-b border-slate-100 hover:bg-slate-50">
                    <td class="p-3 whitespace-nowrap"><?= htmlspecialchars((string) ($c['created_at'] ?? '')) ?></td>
                    <td class="p-3"><?= htmlspecialchars($kindLabel) ?></td>
                    <td class="p-3 max-w-xs truncate" title="<?= htmlspecialchars((string) ($c['subject_snapshot'] ?? '')) ?>"><?= htmlspecialchars((string) ($c['subject_snapshot'] ?? '')) ?></td>
                    <td class="p-3"><?= (int) ($c['recipient_count'] ?? 0) ?></td>
                    <td class="p-3"><?= htmlspecialchars($stLabel) ?></td>
                    <td class="p-3 text-xs text-slate-600">
                        <?php
                        $sent = (int) ($c['sent_count'] ?? 0);
                        $fail = (int) ($c['failed_count'] ?? 0);
                        ?>
                        <?= $sent ?> envoyé(s)<?= $fail > 0 ? ', ' . $fail . ' échec(s)' : '' ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>
