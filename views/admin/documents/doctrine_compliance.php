<?php
declare(strict_types=1);
$rows = $rows ?? [];
$stats = $stats ?? ['concerned' => 0, 'acknowledged' => 0, 'pending' => 0, 'overdue' => 0];
$compliancePct = (float) ($compliancePct ?? 0);
$doctrines = $doctrines ?? [];
$documentFilter = (int) ($documentFilter ?? 0);
?>
<div class="max-w-[1600px] mx-auto px-4 py-8">
    <h1 class="text-2xl font-black text-slate-900">Suivi des prises en compte</h1>
    <div class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
        <div class="rounded-xl border bg-white p-4"><p class="text-xs uppercase text-slate-500">Concernés</p><p class="text-2xl font-black"><?= (int) $stats['concerned'] ?></p></div>
        <div class="rounded-xl border bg-white p-4"><p class="text-xs uppercase text-slate-500">Pris en compte</p><p class="text-2xl font-black text-emerald-700"><?= (int) $stats['acknowledged'] ?></p></div>
        <div class="rounded-xl border bg-white p-4"><p class="text-xs uppercase text-slate-500">En attente</p><p class="text-2xl font-black"><?= (int) $stats['pending'] ?></p></div>
        <div class="rounded-xl border bg-white p-4"><p class="text-xs uppercase text-slate-500">En retard</p><p class="text-2xl font-black text-rose-700"><?= (int) $stats['overdue'] ?></p></div>
        <div class="rounded-xl border bg-white p-4"><p class="text-xs uppercase text-slate-500">Conformité</p><p class="text-2xl font-black"><?= htmlspecialchars(number_format($compliancePct, 1, ',', ''), ENT_QUOTES, 'UTF-8') ?> %</p></div>
    </div>

    <form method="get" class="mt-6 flex flex-wrap gap-2 items-end">
        <label class="text-xs font-bold uppercase text-slate-500">Document
            <select name="document_id" class="mt-1 block rounded-lg border px-3 py-2 text-sm">
                <option value="0">Tous</option>
                <?php foreach ($doctrines as $d): ?>
                <option value="<?= (int) ($d['document_id'] ?? 0) ?>"<?= $documentFilter === (int) ($d['document_id'] ?? 0) ? ' selected' : '' ?>>
                    <?= htmlspecialchars((string) ($d['reference_code'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                </option>
                <?php endforeach; ?>
            </select>
        </label>
        <button type="submit" class="rounded-lg bg-slate-900 px-4 py-2 text-xs font-black uppercase text-white">Filtrer</button>
        <a href="<?= url('back-office/documents/compliance') ?>?export=csv&amp;document_id=<?= $documentFilter ?>" class="rounded-lg border px-4 py-2 text-xs font-black uppercase">Export CSV</a>
    </form>

    <div class="mt-6 overflow-auto rounded-xl border bg-white">
        <table class="w-full text-sm">
            <thead><tr class="border-b bg-slate-50 text-left text-xs uppercase text-slate-500">
                <th class="p-3">Personnel</th><th>Document</th><th>Statut</th><th>Consultation</th><th>Signature</th><th>Échéance</th>
            </tr></thead>
            <tbody>
            <?php foreach ($rows as $r): ?>
                <tr class="border-b">
                    <td class="p-3"><?= htmlspecialchars((string) ($r['display_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                    <td class="p-3"><code><?= htmlspecialchars((string) ($r['reference'] ?? ''), ENT_QUOTES, 'UTF-8') ?></code></td>
                    <td class="p-3"><?= htmlspecialchars((string) ($r['status'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                    <td class="p-3"><?= !empty($r['viewed_at']) ? htmlspecialchars(date('d/m/Y H:i', strtotime((string) $r['viewed_at'])), ENT_QUOTES, 'UTF-8') : '—' ?></td>
                    <td class="p-3"><?= !empty($r['signed_at']) ? htmlspecialchars(date('d/m/Y H:i', strtotime((string) $r['signed_at'])), ENT_QUOTES, 'UTF-8') : '—' ?></td>
                    <td class="p-3"><?= !empty($r['deadline']) ? htmlspecialchars(date('d/m/Y', strtotime((string) $r['deadline'])), ENT_QUOTES, 'UTF-8') : '—' ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
