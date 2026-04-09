<?php
declare(strict_types=1);

$m = $interteamMission ?? [];
$status = (string) ($m['status'] ?? '');
$csrf = $csrfToken ?? \App\Core\Csrf::token();
$sid = (int) ($m['id'] ?? 0);
$rex = $interteamRexRow ?? null;
$rexList = $interteamRexList ?? [];
$canPilot = !empty($interteamCanPilot);
$canReadConsolidatedRex = !empty($interteamCanReadConsolidatedRex);
?>
<div class="max-w-4xl mx-auto px-6 py-10 space-y-8">
    <div>
        <a href="<?= htmlspecialchars(cooperation_mission_show_url($sid), ENT_QUOTES, 'UTF-8') ?>" class="text-sm font-medium text-slate-600 hover:text-slate-900 underline">← Synthèse</a>
        <?php require base_path('views/back_office/cooperation/missions/_nav.php'); ?>
        <h1 class="mt-4 text-2xl font-black text-slate-900">Retour d’expérience</h1>
        <p class="mt-2 text-sm text-slate-600">Chaque unité peut consigner sa vision après clôture. Selon vos habilitations, une synthèse multi-unités peut être proposée ci-dessous.</p>
    </div>

    <?php if ($status !== 'archived'): ?>
    <p class="text-sm text-slate-600 rounded-lg border border-slate-200 bg-slate-50 px-4 py-3">Le formulaire de retour d’expérience est disponible lorsque la coopération est clôturée.</p>
    <?php else: ?>
        <?php
        $row = is_array($rex) ? $rex : [];
        $v = static function (string $k) use ($row): string {
            return htmlspecialchars((string) ($row[$k] ?? ''), ENT_QUOTES, 'UTF-8');
        };
        $rv = static function (string $k) use ($row): string {
            $n = (int) ($row[$k] ?? 0);

            return $n >= 1 && $n <= 5 ? (string) $n : '';
        };
        ?>
    <section class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
        <h2 class="text-sm font-black uppercase tracking-wider text-slate-800">Formulaire de votre unité</h2>
        <form method="post" action="<?= htmlspecialchars(cooperation_missions_url($sid . '/rex'), ENT_QUOTES, 'UTF-8') ?>" class="mt-4 space-y-4">
            <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
            <div>
                <label class="block text-xs font-bold text-slate-500 mb-1">Ce qui a bien fonctionné</label>
                <textarea name="rex_worked_well" rows="3" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm"><?= $v('worked_well') ?></textarea>
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-500 mb-1">Ce qui a moins fonctionné</label>
                <textarea name="rex_failed" rows="3" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm"><?= $v('failed_aspects') ?></textarea>
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-500 mb-1">Incidents de coordination</label>
                <textarea name="rex_coordination" rows="2" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm"><?= $v('coordination_incidents') ?></textarea>
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-500 mb-1">Difficultés de partage d’information</label>
                <textarea name="rex_sharing" rows="2" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm"><?= $v('sharing_difficulties') ?></textarea>
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-500 mb-1">Difficultés techniques</label>
                <textarea name="rex_technical" rows="2" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm"><?= $v('technical_difficulties') ?></textarea>
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-500 mb-1">Recommandations</label>
                <textarea name="rex_recommendations" rows="3" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm"><?= $v('recommendations') ?></textarea>
            </div>
            <div class="grid gap-4 sm:grid-cols-2">
                <?php
                $ratings = [
                    'rating_fluidity' => 'Fluidité de la coopération',
                    'rating_security' => 'Sécurité des échanges',
                    'rating_usefulness' => 'Utilité opérationnelle',
                    'rating_reactivity' => 'Réactivité des unités',
                ];
                foreach ($ratings as $name => $lab):
                ?>
                <div>
                    <label class="block text-xs font-bold text-slate-500 mb-1"><?= htmlspecialchars($lab, ENT_QUOTES, 'UTF-8') ?> (1 à 5)</label>
                    <select name="<?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?>" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
                        <option value="">—</option>
                        <?php for ($i = 1; $i <= 5; $i++): ?>
                        <option value="<?= $i ?>"<?= $rv($name) === (string) $i ? ' selected' : '' ?>><?= $i ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                <?php endforeach; ?>
            </div>
            <button type="submit" class="rounded-xl bg-slate-900 px-5 py-2.5 text-sm font-semibold text-white hover:bg-slate-800">Enregistrer le retour d’expérience</button>
        </form>
    </section>

    <?php if ($canReadConsolidatedRex && $rexList !== []): ?>
    <section class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
        <h2 class="text-sm font-black uppercase tracking-wider text-slate-800">Synthèse consolidée (toutes les unités)</h2>
        <p class="mt-2 text-xs text-slate-600">Vue d’ensemble des retours enregistrés par chaque communauté engagée.</p>
        <ul class="mt-4 space-y-6">
            <?php foreach ($rexList as $block): ?>
            <li class="border-b border-slate-100 pb-4 last:border-0">
                <p class="text-sm font-bold text-slate-900"><?= htmlspecialchars((string) ($block['tenant_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></p>
                <?php if (trim((string) ($block['worked_well'] ?? '')) !== ''): ?>
                <p class="mt-2 text-xs text-slate-600"><span class="font-semibold text-slate-800">Réussites :</span> <?= htmlspecialchars((string) $block['worked_well'], ENT_QUOTES, 'UTF-8') ?></p>
                <?php endif; ?>
                <?php if (trim((string) ($block['failed_aspects'] ?? '')) !== ''): ?>
                <p class="mt-1 text-xs text-slate-600"><span class="font-semibold text-slate-800">Points perfectibles :</span> <?= htmlspecialchars((string) $block['failed_aspects'], ENT_QUOTES, 'UTF-8') ?></p>
                <?php endif; ?>
                <?php if (trim((string) ($block['coordination_incidents'] ?? '')) !== ''): ?>
                <p class="mt-1 text-xs text-slate-600"><span class="font-semibold text-slate-800">Incidents de coordination :</span> <?= htmlspecialchars((string) $block['coordination_incidents'], ENT_QUOTES, 'UTF-8') ?></p>
                <?php endif; ?>
                <?php if (trim((string) ($block['recommendations'] ?? '')) !== ''): ?>
                <p class="mt-1 text-xs text-slate-600"><span class="font-semibold text-slate-800">Recommandations :</span> <?= htmlspecialchars((string) $block['recommendations'], ENT_QUOTES, 'UTF-8') ?></p>
                <?php endif; ?>
            </li>
            <?php endforeach; ?>
        </ul>
    </section>
    <?php endif; ?>
    <?php endif; ?>
</div>
