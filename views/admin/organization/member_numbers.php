<?php
declare(strict_types=1);

$schemaReady = !empty($memberNumberSchemaReady);
$config = is_array($memberNumberConfig ?? null) ? $memberNumberConfig : [];
$preview = isset($memberNumberPreview) ? (string) $memberNumberPreview : '';
$modes = is_array($memberNumberModes ?? null) ? $memberNumberModes : ['free', 'automatic', 'assisted'];
$csrf = htmlspecialchars((string) ($memberNumberCsrf ?? ''), ENT_QUOTES, 'UTF-8');
$flashOk = \App\Core\Session::getFlash('success');
$flashErr = \App\Core\Session::getFlash('error');
$h = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');

$enabled = !empty($config['enabled']);
$label = (string) ($config['label'] ?? "Matricule d'organisation");
$mode = (string) ($config['mode'] ?? 'free');
$pattern = (string) ($config['pattern'] ?? '{PREFIX}-{NUMBER:4}');
$prefix = (string) ($config['prefix'] ?? '');
$nextSeq = (int) ($config['next_sequence'] ?? 1);
$unique = !array_key_exists('unique_required', $config) || !empty($config['unique_required']);
$required = !empty($config['required']);

$modeLabels = [
    'free' => 'Saisie libre',
    'automatic' => 'Génération automatique',
    'assisted' => 'Saisie manuelle assistée',
];
?>
<div class="max-w-4xl mx-auto space-y-6 px-4 py-6">
    <header class="space-y-2">
        <p class="text-[10px] font-black uppercase tracking-[0.2em] text-slate-500">Organisation · Personnel / Membres</p>
        <h1 class="text-2xl font-black text-slate-900">Configuration des matricules</h1>
        <p class="text-sm text-slate-600 max-w-3xl">
            Le matricule d’organisation est l’identifiant métier de chaque communauté.
            L’identifiant plateforme (Athena) reste permanent, non modifiable, et n’est jamais remplacé.
        </p>
        <div class="flex flex-wrap gap-2 pt-1">
            <a href="<?= $h(url('back-office/organisation-effectifs')) ?>" class="text-sm font-semibold text-emerald-800 hover:underline">← Centre effectifs</a>
            <a href="<?= $h(url('back-office/users')) ?>" class="text-sm font-semibold text-slate-600 hover:underline">Membres</a>
        </div>
    </header>

    <?php if ($flashOk): ?>
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-950" role="status"><?= $h((string) $flashOk) ?></div>
    <?php endif; ?>
    <?php if ($flashErr): ?>
        <div class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-950" role="alert"><?= $h((string) $flashErr) ?></div>
    <?php endif; ?>

    <?php if (!$schemaReady): ?>
        <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-950">
            Le module n’est pas encore migré. Lancez les migrations plateforme pour activer les matricules d’organisation.
        </div>
    <?php else: ?>
        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm space-y-4">
            <h2 class="text-sm font-black uppercase tracking-wider text-slate-700">Paramètres</h2>
            <form method="post" action="<?= $h(url('back-office/organisation/matricules')) ?>" class="space-y-4">
                <input type="hidden" name="_csrf_token" value="<?= $csrf ?>">

                <label class="flex items-center gap-2 text-sm font-semibold text-slate-800">
                    <input type="checkbox" name="enabled" value="1" <?= $enabled ? 'checked' : '' ?> class="rounded border-slate-300">
                    Activer les matricules d’organisation
                </label>

                <div class="grid gap-3 md:grid-cols-2">
                    <div>
                        <label class="block text-xs font-bold text-slate-600 mb-1">Libellé affiché</label>
                        <input name="label" value="<?= $h($label) ?>" maxlength="80"
                               class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm"
                               placeholder="Matricule d'organisation">
                        <p class="mt-1 text-[11px] text-slate-500">Ex. Matricule interne, Service Number, Numéro d’incorporation…</p>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-600 mb-1">Mode</label>
                        <select name="mode" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm">
                            <?php foreach ($modes as $m): ?>
                                <option value="<?= $h((string) $m) ?>" <?= $mode === $m ? 'selected' : '' ?>>
                                    <?= $h($modeLabels[$m] ?? (string) $m) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-600 mb-1">Préfixe</label>
                        <input name="prefix" value="<?= $h($prefix) ?>" maxlength="40"
                               class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm font-mono"
                               placeholder="GEND">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-600 mb-1">Format</label>
                        <input name="pattern" value="<?= $h($pattern) ?>" maxlength="120"
                               class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm font-mono"
                               placeholder="{PREFIX}-{NUMBER:4}">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-600 mb-1">Prochaine séquence</label>
                        <input type="number" name="next_sequence" min="1" value="<?= (int) $nextSeq ?>"
                               class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm font-mono">
                    </div>
                    <div class="flex flex-col justify-end gap-2 pb-1">
                        <label class="flex items-center gap-2 text-sm text-slate-800">
                            <input type="checkbox" name="unique_required" value="1" <?= $unique ? 'checked' : '' ?> class="rounded border-slate-300">
                            Unicité dans la communauté
                        </label>
                        <label class="flex items-center gap-2 text-sm text-slate-800">
                            <input type="checkbox" name="required" value="1" <?= $required ? 'checked' : '' ?> class="rounded border-slate-300">
                            Obligatoire à l’attribution
                        </label>
                    </div>
                </div>

                <?php if ($preview !== ''): ?>
                    <p class="text-sm text-slate-600">
                        Prochaine suggestion :
                        <span class="font-mono font-bold text-slate-900"><?= $h($preview) ?></span>
                    </p>
                <?php endif; ?>

                <div class="rounded-xl border border-slate-100 bg-slate-50 px-3 py-2 text-[11px] text-slate-600 leading-relaxed">
                    Variables : <code>{PREFIX}</code>, <code>{NUMBER}</code>, <code>{NUMBER:3|4|5}</code>,
                    <code>{YEAR}</code>, <code>{YEAR:2}</code>, <code>{MONTH}</code>, <code>{TENANT}</code>,
                    <code>{UNIT}</code>, <code>{GRADE}</code> (UNIT/GRADE optionnels, sans dépendance obligatoire).
                </div>

                <button type="submit" class="inline-flex items-center rounded-xl bg-emerald-700 px-4 py-2 text-sm font-bold text-white hover:bg-emerald-800">
                    Enregistrer
                </button>
            </form>
        </section>

        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm space-y-3">
            <h2 class="text-sm font-black uppercase tracking-wider text-slate-700">Import CSV</h2>
            <p class="text-sm text-slate-600">
                Colonnes attendues : <code class="text-xs">prenom;nom;email;tenant_member_number</code>
                (au minimum <code class="text-xs">email</code> + <code class="text-xs">tenant_member_number</code>).
                Contrôles : doublon tenant, longueur, caractères, membre présent.
            </p>
            <form method="post" action="<?= $h(url('back-office/organisation/matricules/import')) ?>" enctype="multipart/form-data" class="flex flex-wrap items-end gap-3">
                <input type="hidden" name="_csrf_token" value="<?= $csrf ?>">
                <div>
                    <label class="block text-xs font-bold text-slate-600 mb-1">Fichier CSV</label>
                    <input type="file" name="csv_file" accept=".csv,text/csv" required class="text-sm">
                </div>
                <button type="submit" class="inline-flex items-center rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-bold text-slate-800 hover:bg-slate-50">
                    Importer
                </button>
            </form>
        </section>
    <?php endif; ?>
</div>
