<?php
declare(strict_types=1);
$domains = $domains ?? [];
$csrf_token = (string) ($csrf_token ?? '');
?>
<div class="max-w-5xl mx-auto px-4 py-8">
    <h1 class="text-2xl font-black text-slate-900">Nomenclature documentaire</h1>
    <p class="mt-2 text-sm text-slate-600">Codes, libellés et abréviations pour les références [SERVICE]/[DOMAINE]/[ANNÉE]-[NUMÉRO].</p>

    <form method="post" action="<?= url('back-office/documents/nomenclature') ?>" class="mt-6 grid gap-3 rounded-xl border border-slate-200 bg-white p-4 md:grid-cols-4">
        <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8') ?>">
        <label class="text-xs font-bold uppercase text-slate-500">Code<input class="mt-1 w-full rounded-lg border px-3 py-2" name="code" required placeholder="OPS"></label>
        <label class="text-xs font-bold uppercase text-slate-500">Libellé<input class="mt-1 w-full rounded-lg border px-3 py-2" name="label" required placeholder="Opérations"></label>
        <label class="text-xs font-bold uppercase text-slate-500">Abréviation<input class="mt-1 w-full rounded-lg border px-3 py-2" name="doc_prefix" required placeholder="OPS"></label>
        <label class="text-xs font-bold uppercase text-slate-500">Couleur<input class="mt-1 w-full rounded-lg border px-3 py-2" name="color" placeholder="#059669"></label>
        <div class="md:col-span-4"><button type="submit" class="rounded-lg bg-slate-900 px-4 py-2 text-xs font-black uppercase text-white">Ajouter</button></div>
    </form>

    <table class="mt-8 w-full text-sm">
        <thead><tr class="border-b text-left text-xs uppercase text-slate-500"><th>Code</th><th>Libellé</th><th>Abrév.</th><th>Sous-domaines</th></tr></thead>
        <tbody>
        <?php foreach ($domains as $d): ?>
            <tr class="border-b">
                <td class="py-2 font-mono font-bold"><?= htmlspecialchars((string) ($d['code'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                <td class="py-2"><?= htmlspecialchars((string) ($d['label'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                <td class="py-2 font-mono"><?= htmlspecialchars((string) ($d['doc_prefix'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                <td class="py-2 text-slate-600">
                    <?php foreach (($d['subdomains'] ?? []) as $s): ?>
                        <?= htmlspecialchars((string) ($s['code'] ?? '') . ' — ' . (string) ($s['label'] ?? ''), ENT_QUOTES, 'UTF-8') ?><br>
                    <?php endforeach; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
