<?php
declare(strict_types=1);

use App\Services\Platform\PlatformDataBrowserService;

/** @var string $table */
/** @var list<array<string, mixed>> $columns */
/** @var array<string, mixed> $row */
/** @var array<string, string> $pk */
/** @var bool $isReadOnly */
/** @var string $back */

$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$tableUrl = url('admin/system/storage/donnees/' . rawurlencode($table));
$backUrl = ($back !== '' && str_starts_with($back, $tableUrl)) ? $back : $tableUrl;
$csrf = \App\Core\Csrf::token();
$pkLabel = implode(', ', array_map(static fn ($k, $v): string => $k . ' = ' . $v, array_keys($pk), $pk));
$longTypes = ['text', 'mediumtext', 'longtext', 'tinytext', 'json', 'blob', 'mediumblob', 'longblob'];
$storageTab = 'data';
?>
<div class="max-w-5xl mx-auto px-4 sm:px-6 py-8 space-y-5">
    <header class="pa-head">
        <p class="text-[11px] font-semibold uppercase tracking-wider text-amber-800 mb-1">
            <a href="<?= $h(url('admin/system/storage/donnees')) ?>" class="hover:underline">Explorateur de données</a> ·
            <a href="<?= $h($backUrl) ?>" class="font-mono hover:underline"><?= $h($table) ?></a>
        </p>
        <h1 class="text-2xl font-black text-slate-900">Ligne <span class="font-mono text-lg text-slate-600"><?= $h($pkLabel) ?></span></h1>
        <p class="mt-1 text-sm text-slate-600">
            <?php if ($isReadOnly): ?>
                Table en lecture seule.
            <?php else: ?>
                Modifiez les champs voulus puis enregistrez : seuls les champs changés sont écrits, et la modification est inscrite au journal d’audit. Les champs masqués (mots de passe, jetons), binaires et la clé ne sont pas modifiables ici.
            <?php endif; ?>
        </p>
    </header>
    <?php require base_path('views/admin/system/partials/storage_nav.php'); ?>

    <form method="post" action="<?= $h($tableUrl . '/ligne') ?>" class="rounded-xl border border-slate-200 bg-white divide-y divide-slate-100">
        <input type="hidden" name="_csrf_token" value="<?= $h($csrf) ?>">
        <input type="hidden" name="retour" value="<?= $h($backUrl) ?>">
        <?php foreach ($pk as $k => $v): ?><input type="hidden" name="pk[<?= $h($k) ?>]" value="<?= $h($v) ?>"><?php endforeach; ?>

        <?php foreach ($columns as $c): ?>
            <?php
            $name = $c['name'];
            $val = $row[$name] ?? null;
            $binary = in_array($c['data_type'], ['blob', 'mediumblob', 'longblob', 'tinyblob', 'binary', 'varbinary', 'geometry', 'point'], true)
                || (is_string($val) && !mb_check_encoding($val, 'UTF-8'));
            $locked = $isReadOnly || $c['primary'] || $c['sensitive'] || $binary;
            $display = PlatformDataBrowserService::displayValue($val, $c['sensitive'], 0);
            $isLong = in_array($c['data_type'], $longTypes, true) || mb_strlen((string) $val) > 120;
            $id = 'f-' . preg_replace('/[^a-z0-9_]/i', '_', $name);
            ?>
            <div class="dbx-field px-4 py-3">
                <label for="<?= $h($id) ?>" class="min-w-0">
                    <span class="block font-mono text-sm font-bold text-slate-900 break-all"><?= $h($name) ?><?= $c['primary'] ? ' 🔑' : '' ?></span>
                    <span class="block text-[11px] text-slate-500 font-mono"><?= $h($c['type']) ?><?= $c['nullable'] ? ' · NULL permis' : '' ?></span>
                    <?php if ($c['comment'] !== ''): ?><span class="block text-[11px] text-slate-500"><?= $h($c['comment']) ?></span><?php endif; ?>
                </label>
                <div class="min-w-0">
                    <?php if ($locked): ?>
                        <div id="<?= $h($id) ?>" class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 font-mono text-sm text-slate-700 whitespace-pre-wrap break-all<?= $val === null ? ' italic text-slate-400' : '' ?>"><?= $h($display) ?></div>
                    <?php else: ?>
                        <?php if ($isLong): ?>
                            <textarea id="<?= $h($id) ?>" name="v[<?= $h($name) ?>]" rows="<?= min(14, max(3, (int) ceil(mb_strlen((string) $val) / 90))) ?>" class="w-full rounded-lg border border-slate-300 px-3 py-2 font-mono text-sm" data-null-target="<?= $h($name) ?>"><?= $h($val ?? '') ?></textarea>
                        <?php else: ?>
                            <input id="<?= $h($id) ?>" type="text" name="v[<?= $h($name) ?>]" value="<?= $h($val ?? '') ?>"<?= $c['max_length'] !== null ? ' maxlength="' . (int) $c['max_length'] . '"' : '' ?> class="w-full rounded-lg border border-slate-300 px-3 py-2 font-mono text-sm" data-null-target="<?= $h($name) ?>">
                        <?php endif; ?>
                        <?php if ($c['nullable']): ?>
                            <label class="mt-1 inline-flex items-center gap-1.5 text-xs text-slate-600">
                                <input type="checkbox" name="null[]" value="<?= $h($name) ?>"<?= $val === null ? ' checked' : '' ?> data-null-box="<?= $h($name) ?>"> NULL (aucune valeur)
                            </label>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>

        <div class="flex flex-wrap items-center gap-2 px-4 py-3 bg-slate-50 rounded-b-xl">
            <?php if (!$isReadOnly): ?>
                <button type="submit" class="rounded-lg bg-emerald-700 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-800">Enregistrer les modifications</button>
            <?php endif; ?>
            <a href="<?= $h($backUrl) ?>" class="rounded-lg px-3 py-2 text-sm font-semibold text-slate-600 hover:underline">Retour à la table</a>
        </div>
    </form>

    <?php if (!$isReadOnly): ?>
        <form method="post" action="<?= $h($tableUrl . '/ligne/supprimer') ?>" class="flex flex-wrap items-center justify-between gap-2 rounded-xl border border-rose-200 bg-white px-4 py-3" onsubmit="return confirm('Supprimer définitivement cette ligne ?');">
            <input type="hidden" name="_csrf_token" value="<?= $h($csrf) ?>">
            <input type="hidden" name="retour" value="<?= $h($backUrl) ?>">
            <?php foreach ($pk as $k => $v): ?><input type="hidden" name="pk[<?= $h($k) ?>]" value="<?= $h($v) ?>"><?php endforeach; ?>
            <p class="text-sm text-slate-600">Supprimer cette ligne : irréversible. Refusé si d’autres données en dépendent.</p>
            <button type="submit" class="rounded-lg border border-rose-300 px-4 py-2 text-sm font-semibold text-rose-800 hover:bg-rose-50">Supprimer la ligne</button>
        </form>
    <?php endif; ?>
</div>
<style>
.dbx-field { display: grid; gap: 0.5rem; }
@media (min-width: 768px) { .dbx-field { grid-template-columns: 14rem minmax(0, 1fr); align-items: start; } }
</style>
<script>
(function () {
    // Cocher NULL grise le champ ; taper une valeur décoche NULL.
    document.querySelectorAll('[data-null-box]').forEach(function (box) {
        var field = document.querySelector('[data-null-target="' + box.getAttribute('data-null-box') + '"]');
        if (!field) { return; }
        var sync = function () { field.style.opacity = box.checked ? '0.45' : '1'; };
        box.addEventListener('change', sync);
        field.addEventListener('input', function () { box.checked = false; sync(); });
        sync();
    });
})();
</script>
