<?php
declare(strict_types=1);
$rows = $rows ?? [];
$stats = $stats ?? ['concerned' => 0, 'acknowledged' => 0, 'pending' => 0, 'overdue' => 0, 'opened' => 0, 'read' => 0];
$commandStats = $commandStats ?? ['active_docs' => 0, 'approaching_deadline' => 0];
$compliancePct = (float) ($compliancePct ?? 0);
$doctrines = $doctrines ?? [];
$documentFilter = (int) ($documentFilter ?? 0);
$statusFilter = (string) ($statusFilter ?? '');
$csrf_token = (string) ($csrf_token ?? '');
$unread = max(0, (int) $stats['concerned'] - (int) ($stats['read'] ?? $stats['acknowledged']));
?>
<div class="max-w-[1600px] mx-auto px-4 py-8">
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="text-2xl font-black text-slate-900">Suivi de diffusion</h1>
            <p class="mt-1 text-sm text-slate-600">Qui a reçu, ouvert et confirmé chaque version.</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="<?= url('back-office/documents/publier') ?>" class="rounded-lg bg-slate-900 px-4 py-2 text-xs font-black uppercase text-white">Publier un document</a>
            <a href="<?= url('back-office/documents/types') ?>" class="rounded-lg border px-4 py-2 text-xs font-black uppercase">Types</a>
        </div>
    </div>

    <div class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4 xl:grid-cols-8">
        <div class="rounded-xl border bg-white p-4"><p class="text-xs uppercase text-slate-500">Documents actifs</p><p class="text-2xl font-black"><?= (int) ($commandStats['active_docs'] ?? 0) ?></p></div>
        <div class="rounded-xl border bg-white p-4"><p class="text-xs uppercase text-slate-500">Destinataires</p><p class="text-2xl font-black"><?= (int) $stats['concerned'] ?></p></div>
        <div class="rounded-xl border bg-white p-4"><p class="text-xs uppercase text-slate-500">Ouverts</p><p class="text-2xl font-black"><?= (int) ($stats['opened'] ?? 0) ?></p></div>
        <div class="rounded-xl border bg-white p-4"><p class="text-xs uppercase text-slate-500">Lus</p><p class="text-2xl font-black text-emerald-700"><?= (int) ($stats['read'] ?? $stats['acknowledged']) ?></p></div>
        <div class="rounded-xl border bg-white p-4"><p class="text-xs uppercase text-slate-500">Non lus</p><p class="text-2xl font-black"><?= $unread ?></p></div>
        <div class="rounded-xl border bg-white p-4"><p class="text-xs uppercase text-slate-500">En retard</p><p class="text-2xl font-black text-rose-700"><?= (int) $stats['overdue'] ?></p></div>
        <div class="rounded-xl border bg-white p-4"><p class="text-xs uppercase text-slate-500">Échéances &lt; 7 j</p><p class="text-2xl font-black"><?= (int) ($commandStats['approaching_deadline'] ?? 0) ?></p></div>
        <div class="rounded-xl border bg-white p-4"><p class="text-xs uppercase text-slate-500">Taux de lecture</p><p class="text-2xl font-black"><?= htmlspecialchars(number_format($compliancePct, 1, ',', ''), ENT_QUOTES, 'UTF-8') ?> %</p></div>
    </div>

    <form method="get" class="mt-6 flex flex-wrap gap-2 items-end">
        <label class="text-xs font-bold uppercase text-slate-500">Document
            <select name="document_id" class="mt-1 block rounded-lg border px-3 py-2 text-sm">
                <option value="0">Tous</option>
                <?php foreach ($doctrines as $d): ?>
                <option value="<?= (int) ($d['document_id'] ?? 0) ?>"<?= $documentFilter === (int) ($d['document_id'] ?? 0) ? ' selected' : '' ?>>
                    <?= htmlspecialchars((string) (($d['reference_code'] ?? '') . ' — ' . ($d['title'] ?? '')), ENT_QUOTES, 'UTF-8') ?>
                </option>
                <?php endforeach; ?>
            </select>
        </label>
        <label class="text-xs font-bold uppercase text-slate-500">Statut
            <select name="statut" class="mt-1 block rounded-lg border px-3 py-2 text-sm">
                <option value="">Tous</option>
                <?php
                $statusOptions = [
                    'UNREAD' => 'À lire',
                    'ACK_REQUIRED' => 'À signer',
                    'ACK_OUTDATED' => 'Nouvelle version',
                    'OVERDUE' => 'En retard',
                    'READ' => 'Consulté',
                    'ACKNOWLEDGED' => 'Pris en compte',
                ];
                foreach ($statusOptions as $code => $lab):
                ?>
                <option value="<?= htmlspecialchars($code, ENT_QUOTES, 'UTF-8') ?>"<?= $statusFilter === $code ? ' selected' : '' ?>><?= htmlspecialchars($lab, ENT_QUOTES, 'UTF-8') ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <button type="submit" class="rounded-lg bg-slate-900 px-4 py-2 text-xs font-black uppercase text-white">Filtrer</button>
    </form>

    <?php if ($documentFilter > 0): ?>
    <form method="post" action="<?= url('back-office/documents/relances') ?>" class="mt-4 flex flex-wrap items-end gap-2 rounded-xl border border-amber-200 bg-amber-50/60 p-3">
        <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8') ?>">
        <input type="hidden" name="document_id" value="<?= $documentFilter ?>">
        <label class="grow text-xs font-bold uppercase text-amber-900">Relancer les non-conformes
            <input class="mt-1 w-full rounded-lg border px-3 py-2 text-sm" name="note" placeholder="Message optionnel">
        </label>
        <button type="submit" class="rounded-lg bg-amber-900 px-4 py-2 text-xs font-black uppercase text-white">Envoyer les rappels</button>
    </form>
    <?php endif; ?>

    <div class="mt-6 overflow-auto rounded-xl border bg-white">
        <table class="w-full text-sm">
            <thead><tr class="border-b bg-slate-50 text-left text-xs uppercase text-slate-500">
                <th class="p-3">Personnel</th>
                <th>Unité</th>
                <th>Document</th>
                <th>Statut</th>
                <th>Première ouverture</th>
                <th>Lecture confirmée</th>
                <th>Version</th>
            </tr></thead>
            <tbody>
            <?php foreach ($rows as $r): ?>
                <tr class="border-b">
                    <td class="p-3"><?= htmlspecialchars((string) ($r['display_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                    <td class="p-3"><?= htmlspecialchars((string) ($r['unit_label'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></td>
                    <td class="p-3"><code><?= htmlspecialchars((string) ($r['reference'] ?? ''), ENT_QUOTES, 'UTF-8') ?></code><div class="text-xs text-slate-500"><?= htmlspecialchars((string) ($r['title'] ?? ''), ENT_QUOTES, 'UTF-8') ?></div></td>
                    <td class="p-3"><?= htmlspecialchars((string) ($r['status'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                    <td class="p-3"><?= !empty($r['first_viewed_at']) ? htmlspecialchars(date('d/m/Y H:i', strtotime((string) $r['first_viewed_at'])), ENT_QUOTES, 'UTF-8') : '—' ?></td>
                    <td class="p-3"><?= !empty($r['signed_at']) ? htmlspecialchars(date('d/m/Y H:i', strtotime((string) $r['signed_at'])), ENT_QUOTES, 'UTF-8') : '—' ?></td>
                    <td class="p-3 font-mono text-xs"><?= htmlspecialchars((string) ($r['version_label'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if ($rows === []): ?>
                <tr><td colspan="7" class="p-6 text-center text-slate-500">Aucun destinataire pour ce filtre.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
