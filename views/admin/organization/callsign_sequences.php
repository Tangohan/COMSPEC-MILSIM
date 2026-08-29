<?php
declare(strict_types=1);

$schemaReady = !empty($callsignSchemaReady);
$sequences = is_array($callsignSequences ?? null) ? $callsignSequences : [];
$previews = is_array($callsignPreviews ?? null) ? $callsignPreviews : [];
$modes = is_array($callsignModes ?? null) ? $callsignModes : ['PREFIX_NUMERIC'];
$csrf = htmlspecialchars((string) ($callsignCsrf ?? ''), ENT_QUOTES, 'UTF-8');
$flashOk = \App\Core\Session::getFlash('success');
$flashErr = \App\Core\Session::getFlash('error');
$h = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
?>
<div class="max-w-5xl mx-auto space-y-6 px-4 py-6">
    <header class="space-y-2">
        <p class="text-[10px] font-black uppercase tracking-[0.2em] text-slate-500">Effectifs · Indicatifs</p>
        <h1 class="text-2xl font-black text-slate-900">Règles d’indicatifs</h1>
        <p class="text-sm text-slate-600 max-w-3xl">
            Générateur séquentiel par organisation (transactionnel). Pas de <code class="text-xs">MAX+1</code> :
            chaque séquence possède son propre curseur, des plages réservées et un historique des changements.
        </p>
        <div class="flex flex-wrap gap-2 pt-1">
            <a href="<?= $h(url('back-office/organisation/progression')) ?>" class="text-sm font-semibold text-emerald-800 hover:underline">← Progression &amp; carrière</a>
            <a href="<?= $h(url('back-office/organisation-effectifs')) ?>" class="text-sm font-semibold text-slate-600 hover:underline">Centre effectifs</a>
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
            Le module n’est pas encore migré. Lancez les migrations plateforme pour créer les tables de séquences.
        </div>
    <?php else: ?>
        <?php
        $notice_tone = 'info';
        $notice_title = 'Stratégies disponibles';
        $notice_body = 'NUMERIC (10, 11…), PREFIX_NUMERIC (A-10), CUSTOM_PATTERN (<code>{PREFIX}-{NUMBER:03}</code> → ALPHA-001), MANUAL (saisie seule). '
            . 'Les plages réservées (ex. 01–09 commandement) sont sautées automatiquement. '
            . 'Tout changement d’indicatif est historisé — jamais silencieux.';
        include base_path('views/partials/bo_dsfr_notice.php');
        ?>

        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm space-y-4">
            <h2 class="text-sm font-black uppercase tracking-wider text-slate-700">Nouvelle séquence</h2>
            <form method="post" action="<?= $h(url('back-office/organisation/indicatifs')) ?>" class="grid gap-3 md:grid-cols-2">
                <input type="hidden" name="_csrf_token" value="<?= $csrf ?>">
                <div>
                    <label class="block text-xs font-bold text-slate-600 mb-1">Nom</label>
                    <input name="name" required class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm" placeholder="Section ALPHA">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-600 mb-1">Code technique</label>
                    <input name="code" required class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm font-mono" placeholder="alpha">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-600 mb-1">Mode</label>
                    <select name="mode" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm">
                        <?php foreach ($modes as $mode): ?>
                            <option value="<?= $h((string) $mode) ?>" <?= $mode === 'PREFIX_NUMERIC' ? 'selected' : '' ?>><?= $h((string) $mode) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-600 mb-1">Pattern</label>
                    <input name="pattern" value="{PREFIX}-{NUMBER:02}" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm font-mono">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-600 mb-1">Préfixe</label>
                    <input name="prefix" value="A" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-600 mb-1">Suffixe</label>
                    <input name="suffix" value="" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-600 mb-1">Départ</label>
                    <input type="number" min="1" name="start_number" value="10" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-600 mb-1">Incrément</label>
                    <input type="number" min="1" name="increment_by" value="1" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-600 mb-1">Padding</label>
                    <input type="number" min="0" max="8" name="padding" value="2" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-600 mb-1">Changement d’unité</label>
                    <select name="unit_change_policy" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm">
                        <option value="keep">Conserver</option>
                        <option value="regenerate">Régénérer</option>
                        <option value="ask">Demander validation</option>
                        <option value="none">Ne rien faire</option>
                    </select>
                </div>
                <div class="md:col-span-2">
                    <label class="block text-xs font-bold text-slate-600 mb-1">Plages réservées</label>
                    <textarea name="reserved_ranges_text" rows="3" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm font-mono" placeholder="1-9|Commandement|command&#10;50-59|Appuis|support"></textarea>
                    <p class="mt-1 text-[11px] text-slate-500">Une ligne : <code>début-fin|libellé|purpose</code></p>
                </div>
                <div class="md:col-span-2 flex flex-wrap gap-4 text-sm">
                    <label class="inline-flex items-center gap-2"><input type="checkbox" name="is_default" value="1"> Séquence par défaut</label>
                    <label class="inline-flex items-center gap-2"><input type="checkbox" name="allow_manual_override" value="1" checked> Autoriser saisie manuelle</label>
                    <label class="inline-flex items-center gap-2"><input type="checkbox" name="reuse_released" value="1"> Réutiliser les indicatifs libérés</label>
                </div>
                <div class="md:col-span-2">
                    <button type="submit" class="rounded-xl bg-slate-900 px-4 py-2.5 text-sm font-bold text-white hover:bg-slate-800">Créer la séquence</button>
                </div>
            </form>
        </section>

        <section class="space-y-4">
            <h2 class="text-sm font-black uppercase tracking-wider text-slate-700">Séquences configurées</h2>
            <?php if ($sequences === []): ?>
                <p class="text-sm text-slate-500">Aucune séquence pour l’instant.</p>
            <?php endif; ?>
            <?php foreach ($sequences as $seq):
                $id = (int) ($seq['id'] ?? 0);
                $preview = $previews[$id] ?? null;
                $rangesRepo = new \App\Repositories\CallsignSequenceRepository();
                $ranges = $rangesRepo->listReservedRanges((int) \App\Core\Session::get('tenant_id'), $id);
                $rangesText = '';
                foreach ($ranges as $r) {
                    $rangesText .= $r['range_start'] . '-' . $r['range_end'] . '|' . $r['label'] . '|' . $r['purpose'] . "\n";
                }
            ?>
            <form method="post" action="<?= $h(url('back-office/organisation/indicatifs/' . $id)) ?>" class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm grid gap-3 md:grid-cols-2">
                <input type="hidden" name="_csrf_token" value="<?= $csrf ?>">
                <div class="md:col-span-2 flex flex-wrap items-center justify-between gap-2">
                    <p class="font-bold text-slate-900"><?= $h((string) ($seq['name'] ?? '')) ?>
                        <span class="ml-2 font-mono text-xs text-slate-500"><?= $h((string) ($seq['code'] ?? '')) ?></span>
                    </p>
                    <p class="text-xs text-slate-500">Prochain : <strong class="font-mono text-slate-800"><?= $preview !== null ? $h($preview) : '—' ?></strong>
                        · curseur <?= (int) ($seq['current_number'] ?? 0) ?>
                        <?= !empty($seq['is_default']) ? ' · <span class="text-emerald-700 font-semibold">défaut</span>' : '' ?>
                    </p>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-600 mb-1">Nom</label>
                    <input name="name" value="<?= $h((string) ($seq['name'] ?? '')) ?>" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-600 mb-1">Code</label>
                    <input name="code" value="<?= $h((string) ($seq['code'] ?? '')) ?>" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm font-mono">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-600 mb-1">Mode</label>
                    <select name="mode" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm">
                        <?php foreach ($modes as $mode): ?>
                            <option value="<?= $h((string) $mode) ?>" <?= ((string) ($seq['mode'] ?? '')) === $mode ? 'selected' : '' ?>><?= $h((string) $mode) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-600 mb-1">Pattern</label>
                    <input name="pattern" value="<?= $h((string) ($seq['pattern'] ?? '')) ?>" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm font-mono">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-600 mb-1">Préfixe</label>
                    <input name="prefix" value="<?= $h((string) ($seq['prefix'] ?? '')) ?>" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-600 mb-1">Suffixe</label>
                    <input name="suffix" value="<?= $h((string) ($seq['suffix'] ?? '')) ?>" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-600 mb-1">Départ</label>
                    <input type="number" name="start_number" value="<?= (int) ($seq['start_number'] ?? 1) ?>" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-600 mb-1">Incrément / padding</label>
                    <div class="flex gap-2">
                        <input type="number" name="increment_by" value="<?= (int) ($seq['increment_by'] ?? 1) ?>" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm">
                        <input type="number" name="padding" value="<?= (int) ($seq['padding'] ?? 2) ?>" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm">
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-600 mb-1">Changement d’unité</label>
                    <select name="unit_change_policy" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm">
                        <?php foreach (['keep' => 'Conserver', 'regenerate' => 'Régénérer', 'ask' => 'Demander', 'none' => 'Rien'] as $pol => $lab): ?>
                            <option value="<?= $h($pol) ?>" <?= ((string) ($seq['unit_change_policy'] ?? '')) === $pol ? 'selected' : '' ?>><?= $h($lab) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="md:col-span-2">
                    <label class="block text-xs font-bold text-slate-600 mb-1">Plages réservées</label>
                    <textarea name="reserved_ranges_text" rows="2" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm font-mono"><?= $h(rtrim($rangesText)) ?></textarea>
                </div>
                <div class="md:col-span-2 flex flex-wrap gap-4 text-sm">
                    <label class="inline-flex items-center gap-2"><input type="checkbox" name="is_default" value="1" <?= !empty($seq['is_default']) ? 'checked' : '' ?>> Défaut</label>
                    <label class="inline-flex items-center gap-2"><input type="checkbox" name="is_active" value="1" <?= !empty($seq['is_active']) ? 'checked' : '' ?>> Active</label>
                    <label class="inline-flex items-center gap-2"><input type="checkbox" name="allow_manual_override" value="1" <?= !empty($seq['allow_manual_override']) ? 'checked' : '' ?>> Manuel OK</label>
                    <label class="inline-flex items-center gap-2"><input type="checkbox" name="reuse_released" value="1" <?= !empty($seq['reuse_released']) ? 'checked' : '' ?>> Réutiliser libérés</label>
                </div>
                <div class="md:col-span-2">
                    <button type="submit" class="rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-bold text-slate-900 hover:bg-slate-50">Enregistrer</button>
                </div>
            </form>
            <?php endforeach; ?>
        </section>
    <?php endif; ?>
</div>
