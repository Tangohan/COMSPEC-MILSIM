<?php
declare(strict_types=1);

use App\Services\Platform\PlatformDataBrowserService;

/** @var string $table */
/** @var list<array<string, mixed>> $columns */
/** @var list<string> $primaryKey */
/** @var array{rows: list<array<string, mixed>>, total: int, total_exact: bool, page: int, pages: int, sort: string, dir: string, error: ?string} $result */
/** @var array<string, mixed> $opts */
/** @var PlatformDataBrowserService $browser */
/** @var bool $isProtected */
/** @var bool $isReadOnly */

$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$base = url('admin/system/storage/donnees/' . rawurlencode($table));
$csrf = \App\Core\Csrf::token();
$canEditRows = !$isReadOnly && $primaryKey !== [];
$filterable = array_values(array_filter($columns, static fn (array $c): bool => !$c['sensitive']));

// Lien vers la même vue en changeant quelques paramètres (tri, page…).
$link = static function (array $change) use ($opts, $base): string {
    $q = array_filter(array_merge($opts, $change), static fn ($v): bool => $v !== '' && $v !== null && $v !== 0);
    if (($q['page'] ?? 1) === 1) {
        unset($q['page']);
    }

    return $base . ($q !== [] ? '?' . http_build_query($q) : '');
};
$current = $link([]);
$hasFilter = trim((string) $opts['q']) !== '' || ((string) $opts['col'] !== '' && ((string) $opts['val'] !== '' || in_array($opts['op'], ['null', 'notnull'], true)));
$storageTab = 'data';
?>
<div class="mx-auto max-w-[100rem] px-4 sm:px-6 py-8 space-y-5">
    <header class="pa-head flex flex-wrap items-end justify-between gap-3">
        <div class="min-w-0">
            <p class="text-[11px] font-semibold uppercase tracking-wider text-amber-800 mb-1"><a href="<?= $h(url('admin/system/storage/donnees')) ?>" class="hover:underline">Explorateur de données</a> · toutes les communautés</p>
            <h1 class="font-mono text-2xl font-black text-slate-900 break-all"><?= $h($table) ?></h1>
            <p class="mt-1 text-sm text-slate-600">
                <?= $result['total_exact'] ? '' : '≈ ' ?><?= number_format($result['total'], 0, ',', ' ') ?> ligne(s)<?= $hasFilter ? ' pour ce filtre' : '' ?> · <?= count($columns) ?> colonne(s)
                <?php if ($primaryKey !== []): ?>· clé : <span class="font-mono"><?= $h(implode(', ', $primaryKey)) ?></span><?php endif; ?>
            </p>
            <?php if ($isReadOnly): ?>
                <p class="mt-1 text-xs font-semibold text-slate-700">Lecture seule : cette table sert de preuve ou de suivi technique.</p>
            <?php elseif ($primaryKey === []): ?>
                <p class="mt-1 text-xs font-semibold text-amber-800">Pas de clé primaire : lecture seule ligne à ligne.</p>
            <?php elseif ($isProtected): ?>
                <p class="mt-1 text-xs font-semibold text-amber-800">Table protégée : lignes modifiables une par une, jamais vidée en bloc.</p>
            <?php endif; ?>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="<?= $h($link(['format' => 'csv', 'page' => 1])) ?>" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-semibold text-slate-800 hover:bg-slate-50">Exporter en CSV<span class="text-slate-500 font-normal"> (<?= PlatformDataBrowserService::EXPORT_LIMIT ?> max)</span></a>
            <a href="#structure" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-semibold text-slate-800 hover:bg-slate-50">Structure</a>
        </div>
    </header>
    <?php require base_path('views/admin/system/partials/storage_nav.php'); ?>

    <form method="get" action="<?= $h($base) ?>" class="dbx-filter rounded-xl border border-slate-200 bg-white p-3">
        <label class="sr-only" for="dq">Chercher dans toutes les colonnes</label>
        <input id="dq" type="search" name="q" value="<?= $h($opts['q']) ?>" placeholder="Chercher dans toutes les colonnes…" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
        <label class="sr-only" for="dcol">Colonne</label>
        <select id="dcol" name="col" class="rounded-lg border border-slate-300 px-2 py-2 text-sm">
            <option value="">Filtrer une colonne…</option>
            <?php foreach ($filterable as $c): ?>
                <option value="<?= $h($c['name']) ?>"<?= $opts['col'] === $c['name'] ? ' selected' : '' ?>><?= $h($c['name']) ?></option>
            <?php endforeach; ?>
        </select>
        <label class="sr-only" for="dop">Condition</label>
        <select id="dop" name="op" class="rounded-lg border border-slate-300 px-2 py-2 text-sm">
            <?php foreach (PlatformDataBrowserService::OPERATORS as $k => $label): ?>
                <option value="<?= $h($k) ?>"<?= $opts['op'] === $k ? ' selected' : '' ?>><?= $h($label) ?></option>
            <?php endforeach; ?>
        </select>
        <label class="sr-only" for="dval">Valeur</label>
        <input id="dval" type="text" name="val" value="<?= $h($opts['val']) ?>" placeholder="Valeur" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
        <div class="flex gap-2">
            <?php if ($opts['sort'] !== ''): ?><input type="hidden" name="sort" value="<?= $h($opts['sort']) ?>"><input type="hidden" name="dir" value="<?= $h($opts['dir']) ?>"><?php endif; ?>
            <button type="submit" class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">Chercher</button>
            <?php if ($hasFilter): ?><a href="<?= $h($base) ?>" class="self-center text-sm text-slate-600 hover:underline">Effacer</a><?php endif; ?>
        </div>
    </form>

    <?php if ($result['error'] !== null): ?>
        <p class="rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-900"><?= $h($result['error']) ?></p>
    <?php elseif ($result['rows'] === []): ?>
        <p class="rounded-xl border border-dashed border-slate-300 bg-white p-6 text-center text-sm text-slate-600"><?= $hasFilter ? 'Aucune ligne ne correspond à ce filtre.' : 'Cette table est vide.' ?></p>
    <?php else: ?>
        <div class="dbx-wrap">
            <table class="dbx">
                <thead>
                    <tr>
                        <th class="dbx-act"><span class="sr-only">Actions</span></th>
                        <?php foreach ($columns as $c): ?>
                            <?php
                            $isSort = $result['sort'] === $c['name'];
                            $nextDir = $isSort && $result['dir'] === 'asc' ? 'desc' : 'asc';
                            ?>
                            <th scope="col" title="<?= $h($c['type'] . ($c['nullable'] ? ' · NULL permis' : '') . ($c['comment'] !== '' ? ' · ' . $c['comment'] : '')) ?>">
                                <a href="<?= $h($link(['sort' => $c['name'], 'dir' => $nextDir, 'page' => 1])) ?>">
                                    <?= $h($c['name']) ?><?= $c['primary'] ? ' 🔑' : '' ?><?= $isSort ? ($result['dir'] === 'asc' ? ' ▲' : ' ▼') : '' ?>
                                </a>
                                <span><?= $h($c['data_type']) ?><?= $c['sensitive'] ? ' · masqué' : '' ?></span>
                            </th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($result['rows'] as $r): ?>
                        <?php $pk = $browser->pkOf($table, $r); ?>
                        <tr>
                            <td class="dbx-act">
                                <?php if ($pk !== null): ?>
                                    <a href="<?= $h(url('admin/system/storage/donnees/' . rawurlencode($table) . '/ligne') . '?' . http_build_query(['pk' => $pk, 'retour' => $current])) ?>" class="dbx-btn"><?= $canEditRows ? 'Ouvrir' : 'Voir' ?></a>
                                    <?php if ($canEditRows): ?>
                                        <form method="post" action="<?= $h(url('admin/system/storage/donnees/' . rawurlencode($table) . '/ligne/supprimer')) ?>" onsubmit="return confirm('Supprimer définitivement cette ligne de « <?= $h($table) ?> » ?');">
                                            <input type="hidden" name="_csrf_token" value="<?= $h($csrf) ?>">
                                            <?php foreach ($pk as $k => $v): ?><input type="hidden" name="pk[<?= $h($k) ?>]" value="<?= $h($v) ?>"><?php endforeach; ?>
                                            <input type="hidden" name="retour" value="<?= $h($current) ?>">
                                            <button type="submit" class="dbx-btn dbx-btn--del" title="Supprimer cette ligne">✕</button>
                                        </form>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </td>
                            <?php foreach ($columns as $c): ?>
                                <?php $val = $r[$c['name']] ?? null; ?>
                                <td class="<?= $val === null ? 'is-null' : '' ?><?= $c['sensitive'] ? ' is-masked' : '' ?>" title="<?= $h(PlatformDataBrowserService::displayValue($val, $c['sensitive'], 600)) ?>"><?= $h(PlatformDataBrowserService::displayValue($val, $c['sensitive'], 80)) ?></td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <nav class="flex flex-wrap items-center justify-between gap-2 text-sm" aria-label="Pages">
            <span class="text-slate-600">Page <?= (int) $result['page'] ?> sur <?= $result['total_exact'] ? '' : '≈ ' ?><?= (int) $result['pages'] ?> · <?= PlatformDataBrowserService::PER_PAGE ?> lignes par page</span>
            <span class="flex gap-1">
                <?php if ($result['page'] > 1): ?>
                    <a class="dbx-page" href="<?= $h($link(['page' => 1])) ?>">« Première</a>
                    <a class="dbx-page" href="<?= $h($link(['page' => $result['page'] - 1])) ?>">‹ Précédente</a>
                <?php endif; ?>
                <?php if ($result['page'] < $result['pages']): ?>
                    <a class="dbx-page" href="<?= $h($link(['page' => $result['page'] + 1])) ?>">Suivante ›</a>
                    <?php if ($result['total_exact']): ?><a class="dbx-page" href="<?= $h($link(['page' => $result['pages']])) ?>">Dernière »</a><?php endif; ?>
                <?php endif; ?>
            </span>
        </nav>
    <?php endif; ?>

    <section id="structure" class="rounded-xl border border-slate-200 bg-white p-5">
        <h2 class="text-sm font-black uppercase tracking-[0.18em] text-slate-500 mb-3">Structure</h2>
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead><tr class="text-left text-[11px] font-bold uppercase tracking-wider text-slate-400"><th class="py-1.5 pr-4">Colonne</th><th class="py-1.5 pr-4">Type</th><th class="py-1.5 pr-4">Vide permis</th><th class="py-1.5 pr-4">Défaut</th><th class="py-1.5">Remarque</th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                    <?php foreach ($columns as $c): ?>
                        <tr>
                            <td class="py-1.5 pr-4 font-mono font-semibold"><?= $h($c['name']) ?></td>
                            <td class="py-1.5 pr-4 font-mono text-slate-600"><?= $h($c['type']) ?></td>
                            <td class="py-1.5 pr-4"><?= $c['nullable'] ? 'Oui' : 'Non' ?></td>
                            <td class="py-1.5 pr-4 font-mono text-slate-600"><?= $h($c['default'] ?? '—') ?></td>
                            <td class="py-1.5 text-slate-600"><?= $h(trim(($c['primary'] ? 'Clé primaire. ' : '') . ($c['auto'] ? 'Auto-incrément. ' : '') . ($c['sensitive'] ? 'Valeur masquée, non modifiable ici. ' : '') . $c['comment'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>

    <?php if (!$isProtected): ?>
        <section class="rounded-xl border border-rose-200 bg-white p-5">
            <h2 class="text-sm font-black uppercase tracking-[0.18em] text-rose-700 mb-1">Vider la table</h2>
            <p class="text-sm text-slate-600 mb-3">Supprime <strong>toutes</strong> les lignes, pour toutes les communautés. Irréversible. Pour confirmer, recopiez le nom de la table.</p>
            <form method="post" action="<?= $h($base . '/vider') ?>" class="flex flex-wrap items-center gap-2" onsubmit="return confirm('Vider entièrement « <?= $h($table) ?> » ? Cette action est irréversible.');">
                <input type="hidden" name="_csrf_token" value="<?= $h($csrf) ?>">
                <label class="sr-only" for="confirm-table">Nom de la table</label>
                <input id="confirm-table" name="confirm_table" type="text" autocomplete="off" required placeholder="<?= $h($table) ?>" class="min-w-[14rem] rounded-lg border border-slate-300 px-3 py-2 font-mono text-sm">
                <button type="submit" class="rounded-lg bg-rose-700 px-4 py-2 text-sm font-semibold text-white hover:bg-rose-800">Vider la table</button>
            </form>
        </section>
    <?php endif; ?>
</div>
<style>
.dbx-filter { display: grid; gap: 0.5rem; }
@media (min-width: 900px) { .dbx-filter { grid-template-columns: minmax(12rem, 1.4fr) minmax(9rem, 1fr) minmax(8rem, 0.8fr) minmax(9rem, 1fr) auto; align-items: center; } }
.dbx-wrap { overflow: auto; max-height: 70vh; border: 1px solid #e2e8f0; border-radius: 0.75rem; background: #fff; }
.dbx { border-collapse: separate; border-spacing: 0; font-size: 0.8rem; min-width: 100%; }
.dbx th, .dbx td { border-bottom: 1px solid #f1f5f9; border-right: 1px solid #f1f5f9; padding: 0.35rem 0.6rem; text-align: left; white-space: nowrap; max-width: 22rem; overflow: hidden; text-overflow: ellipsis; vertical-align: top; }
.dbx thead th { position: sticky; top: 0; z-index: 2; background: #f8fafc; border-bottom: 1px solid #cbd5e1; }
.dbx thead th a { display: block; font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-weight: 700; color: #0f172a; text-decoration: none; }
.dbx thead th a:hover { color: #047857; }
.dbx thead th span { display: block; font-size: 0.65rem; font-weight: 600; color: #94a3b8; }
.dbx tbody tr:nth-child(even) td { background: #fbfdff; }
.dbx tbody tr:hover td { background: #ecfdf5; }
.dbx td { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; color: #1e293b; }
.dbx td.is-null { color: #cbd5e1; font-style: italic; }
.dbx td.is-masked { color: #94a3b8; }
.dbx .dbx-act { position: sticky; left: 0; z-index: 1; background: #fff; box-shadow: 1px 0 0 #e2e8f0; }
.dbx thead .dbx-act { z-index: 3; background: #f8fafc; }
.dbx .dbx-act form { display: inline; margin: 0; }
.dbx-btn { display: inline-block; border: 1px solid #cbd5e1; border-radius: 0.4rem; padding: 0.1rem 0.45rem; font-family: inherit; font-size: 0.72rem; font-weight: 700; color: #0f172a; background: #fff; text-decoration: none; cursor: pointer; }
.dbx-btn:hover { border-color: #059669; color: #047857; }
.dbx-btn--del:hover { border-color: #e11d48; color: #be123c; }
.dbx-page { border: 1px solid #e2e8f0; border-radius: 0.5rem; padding: 0.3rem 0.65rem; background: #fff; font-weight: 600; color: #0f172a; text-decoration: none; }
.dbx-page:hover { border-color: #059669; }
</style>
