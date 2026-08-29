<?php
declare(strict_types=1);

use App\Core\Csrf;
use App\Core\Session;

/** @var list<array<string, mixed>> $catalogRows */
$catalogRows = is_array($catalogRows ?? null) ? $catalogRows : [];
/** @var list<array<string, mixed>> $expectedMatrix */
$expectedMatrix = is_array($expectedMatrix ?? null) ? $expectedMatrix : [];
$branchFilter = (string) ($branchFilter ?? 'ARMY');
$h = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
$flashSuccess = Session::getFlash('success');
$flashError = Session::getFlash('error');

$badgeClass = static function (string $status): string {
    return match (strtoupper($status)) {
        'VERIFIED' => 'bg-emerald-100 text-emerald-900',
        'INVALID' => 'bg-red-100 text-red-900',
        'CUSTOM' => 'bg-slate-200 text-slate-800',
        default => 'bg-amber-100 text-amber-900',
    };
};
?>
<div class="max-w-6xl mx-auto px-6 py-10 space-y-8">
    <header class="space-y-2">
        <p class="text-[10px] font-black uppercase tracking-[0.2em] text-slate-500">Back-office · Personnel</p>
        <h1 class="text-2xl font-black text-slate-900">Catalogue des grades / OTAN</h1>
        <p class="text-sm text-slate-600 max-w-3xl">
            Le code OTAN est une donnée de référence indépendante de <code class="text-xs">hierarchy_order</code>.
            Jamais de dérivation du type <em>ordre&nbsp;4 ⇒ OF-4</em>. Colonel FR ARMY = <strong>OF-5</strong>.
        </p>
        <div class="flex flex-wrap gap-3 text-sm">
            <a href="<?= $h(url('back-office/referentiels/grades')) ?>" class="font-semibold text-emerald-800 hover:underline">← Référentiel legacy</a>
            <a href="<?= $h(url('back-office/referentiels/grades/catalogue')) ?>?branch=ARMY" class="<?= $branchFilter === 'ARMY' ? 'font-black text-slate-900' : 'text-slate-600' ?>">Armée de Terre</a>
            <a href="<?= $h(url('back-office/referentiels/grades/catalogue')) ?>?branch=GENDARMERIE" class="<?= $branchFilter === 'GENDARMERIE' ? 'font-black text-slate-900' : 'text-slate-600' ?>">Gendarmerie</a>
            <a href="<?= $h(url('back-office/referentiels/grades/catalogue')) ?>?branch=ALL" class="<?= $branchFilter === 'ALL' ? 'font-black text-slate-900' : 'text-slate-600' ?>">Tous</a>
        </div>
    </header>

    <?php if ($flashSuccess): ?>
    <p class="text-sm text-emerald-800 bg-emerald-50 border border-emerald-200 rounded-lg px-3 py-2"><?= $h((string) $flashSuccess) ?></p>
    <?php endif; ?>
    <?php if ($flashError): ?>
    <p class="text-sm text-red-800 bg-red-50 border border-red-200 rounded-lg px-3 py-2"><?= $h((string) $flashError) ?></p>
    <?php endif; ?>

    <section class="rounded-xl border border-slate-200 bg-slate-50/80 p-4 space-y-3">
        <p class="text-sm font-semibold text-slate-800">Audit / réparation contrôlée</p>
        <p class="text-xs text-slate-600">Compare le référentiel <code>grades</code> FR_CLASSIC à la matrice attendue. Répare uniquement les mismatches certains (ex. COL stocké OF-4 → OF-5) avec journal <code>rank_migration_audit</code>.</p>
        <form method="post" action="<?= $h(url('back-office/referentiels/grades/catalogue/audit')) ?>" class="flex flex-wrap gap-3 items-center">
            <input type="hidden" name="_csrf_token" value="<?= $h(Csrf::token()) ?>">
            <button type="submit" name="repair" value="0" class="rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-800 hover:bg-slate-100">Auditer sans corriger</button>
            <button type="submit" name="repair" value="1" class="rounded-md bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800" onclick="return confirm('Réparer uniquement les correspondances OTAN certaines ?');">Auditer + réparer les cas certains</button>
        </form>
    </section>

    <section>
        <h2 class="text-sm font-black uppercase tracking-wider text-slate-500 mb-3">Matrice FR ARMY attendue</h2>
        <div class="overflow-x-auto rounded-lg border border-slate-200">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                    <tr>
                        <th class="text-left p-3">Grade</th>
                        <th class="text-left p-3">Catégorie</th>
                        <th class="text-left p-3">OTAN attendu</th>
                        <th class="text-left p-3">hierarchy_order</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($expectedMatrix as $m): ?>
                    <tr class="border-t border-slate-100">
                        <td class="p-3 font-medium text-slate-900"><?= $h((string) $m['canonical_name']) ?></td>
                        <td class="p-3 text-slate-600"><?= $h((string) $m['category']) ?></td>
                        <td class="p-3 font-mono font-semibold text-slate-900"><?= $h((string) $m['expected_nato']) ?></td>
                        <td class="p-3 text-slate-500"><?= (int) $m['hierarchy_order'] ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>

    <section>
        <h2 class="text-sm font-black uppercase tracking-wider text-slate-500 mb-3">Catalogue seedé (<?= $h($branchFilter) ?>)</h2>
        <?php if ($catalogRows === []): ?>
        <p class="text-sm text-amber-800 bg-amber-50 border border-amber-200 rounded-lg px-3 py-2">Catalogue vide — lancez un audit pour seed + analyse.</p>
        <?php else: ?>
        <div class="overflow-x-auto rounded-lg border border-slate-200">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                    <tr>
                        <th class="text-left p-3">Grade</th>
                        <th class="text-left p-3">Pays</th>
                        <th class="text-left p-3">Branche</th>
                        <th class="text-left p-3">Catégorie</th>
                        <th class="text-left p-3">OTAN</th>
                        <th class="text-left p-3">Équiv. US</th>
                        <th class="text-left p-3">Ordre</th>
                        <th class="text-left p-3">Statut</th>
                        <th class="text-left p-3">Source</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($catalogRows as $r):
                    $status = strtoupper((string) ($r['verification_status'] ?? 'UNVERIFIED'));
                    ?>
                    <tr class="border-t border-slate-100">
                        <td class="p-3">
                            <span class="font-medium text-slate-900"><?= $h((string) ($r['canonical_name'] ?? '')) ?></span>
                            <span class="block text-xs text-slate-500"><?= $h((string) ($r['short_name'] ?? '')) ?></span>
                        </td>
                        <td class="p-3"><?= $h((string) ($r['country_code'] ?? '')) ?></td>
                        <td class="p-3"><?= $h((string) ($r['branch'] ?? '')) ?></td>
                        <td class="p-3"><?= $h((string) ($r['category'] ?? '')) ?></td>
                        <td class="p-3 font-mono"><?= $h((string) ($r['nato_code'] ?? '—')) ?></td>
                        <td class="p-3 font-mono text-slate-600"><?= $h((string) ($r['us_equivalent'] ?? '—')) ?></td>
                        <td class="p-3"><?= (int) ($r['hierarchy_order'] ?? 0) ?></td>
                        <td class="p-3"><span class="inline-flex rounded px-2 py-0.5 text-[10px] font-black uppercase <?= $badgeClass($status) ?>"><?= $h($status) ?></span></td>
                        <td class="p-3 text-xs text-slate-500 max-w-[12rem] truncate" title="<?= $h((string) ($r['reference_source'] ?? '')) ?>"><?= $h((string) ($r['reference_source'] ?? '—')) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </section>
</div>
