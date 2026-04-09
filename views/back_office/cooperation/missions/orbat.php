<?php
declare(strict_types=1);

use App\Support\CooperationDictionary;

$m = $interteamMission ?? [];
$needsRaw = $m['competency_needs_json'] ?? null;
$needsList = [];
if (is_string($needsRaw) && trim($needsRaw) !== '') {
    $d = json_decode($needsRaw, true);
    if (is_array($d)) {
        foreach ($d as $x) {
            if (is_string($x) && $x !== '') {
                $needsList[] = $x;
            }
        }
    }
}
$needLabels = CooperationDictionary::competencyNeedLabels();
$orbatBlocks = $interteamOrbatBlocks ?? [];
$canManage = !empty($interteamCanManage);
$canPilot = !empty($interteamCanPilot);
$csrf = $csrfToken ?? \App\Core\Csrf::token();
$sid = (int) ($m['id'] ?? 0);
$status = (string) ($m['status'] ?? '');
?>
<div class="max-w-4xl mx-auto px-6 py-10 space-y-8">
    <div>
        <a href="<?= htmlspecialchars(cooperation_mission_show_url($sid), ENT_QUOTES, 'UTF-8') ?>" class="text-sm font-medium text-slate-600 hover:text-slate-900 underline">← Synthèse</a>
        <?php require base_path('views/back_office/cooperation/missions/_nav.php'); ?>
        <h1 class="mt-4 text-2xl font-black text-slate-900">Structures & liaisons</h1>
        <p class="mt-2 text-sm text-slate-600">Vue indicative des unités déclarées et des points de contact / coordination cartographique.</p>
    </div>

    <?php if ($canPilot && $canManage && $status === 'active'): ?>
    <section class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
        <h2 class="text-sm font-black uppercase tracking-wider text-slate-800">Coordination opérationnelle</h2>
        <form method="post" action="<?= htmlspecialchars(cooperation_missions_url($sid . '/meta'), ENT_QUOTES, 'UTF-8') ?>" class="mt-4 space-y-4">
            <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
            <div>
                <label class="block text-xs font-bold text-slate-500 mb-1">Points de contact, fréquences, rendez-vous</label>
                <textarea name="liaison_notes" rows="4" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" placeholder="Fréquences, contacts encadrement, horaires de coordination…"><?= htmlspecialchars((string) ($m['liaison_notes'] ?? ''), ENT_QUOTES, 'UTF-8') ?></textarea>
            </div>
            <div class="grid gap-3 sm:grid-cols-2">
                <div>
                    <label class="block text-xs font-bold text-slate-500 mb-1">Coordination carto — unité support</label>
                    <input type="text" name="atak_endpoint_primary" value="<?= htmlspecialchars((string) ($m['atak_endpoint_primary'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" placeholder="Libellé opérationnel">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-500 mb-1">Coordination carto — partenaire</label>
                    <input type="text" name="atak_endpoint_partner" value="<?= htmlspecialchars((string) ($m['atak_endpoint_partner'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" placeholder="Libellé opérationnel">
                </div>
            </div>
            <div class="grid gap-3 sm:grid-cols-2">
                <div>
                    <label class="block text-xs font-bold text-slate-500 mb-1">Libellé affiché — hôte principal</label>
                    <input type="text" name="atak_primary_label" value="<?= htmlspecialchars((string) ($m['atak_primary_label'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" placeholder="Ex. : serveur coordination A">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-500 mb-1">Libellé affiché — hôte partenaire</label>
                    <input type="text" name="atak_partner_label" value="<?= htmlspecialchars((string) ($m['atak_partner_label'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" placeholder="Ex. : serveur coordination B">
                </div>
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-500 mb-1">Procédure de bascule</label>
                <textarea name="atak_bascule_notes" rows="3" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" placeholder="Si hôte indisponible, perte de liaison…"><?= htmlspecialchars((string) ($m['atak_bascule_notes'] ?? ''), ENT_QUOTES, 'UTF-8') ?></textarea>
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-500 mb-1">État de synchronisation (texte libre court)</label>
                <input type="text" name="atak_sync_status" value="<?= htmlspecialchars((string) ($m['atak_sync_status'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" class="w-full max-w-md rounded-lg border border-slate-200 px-3 py-2 text-sm" placeholder="Ex. : opérationnel / essai / incident">
            </div>
            <fieldset>
                <legend class="text-xs font-bold text-slate-500 mb-2">Besoins de compétences déclarés pour cette coopération</legend>
                <div class="grid gap-2 sm:grid-cols-2">
                    <?php foreach ($needLabels as $nk => $nlab): ?>
                    <label class="flex items-center gap-2 text-sm text-slate-700">
                        <input type="checkbox" name="need_<?= htmlspecialchars($nk, ENT_QUOTES, 'UTF-8') ?>" value="1" class="rounded border-slate-300"<?= in_array($nk, $needsList, true) ? ' checked' : '' ?>>
                        <span><?= htmlspecialchars($nlab, ENT_QUOTES, 'UTF-8') ?></span>
                    </label>
                    <?php endforeach; ?>
                </div>
            </fieldset>
            <button type="submit" class="rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">Enregistrer</button>
        </form>
    </section>
    <?php endif; ?>

    <?php if ($needsList !== []): ?>
    <section class="rounded-xl border border-emerald-100 bg-emerald-50/40 p-6 shadow-sm">
        <h2 class="text-sm font-black uppercase tracking-wider text-emerald-950">Lecture des besoins / ressources</h2>
        <p class="mt-2 text-xs text-emerald-950/90">Cette coopération mentionne les besoins suivants. La couverture réelle dépend des dossiers de chaque unité (à confirmer avec vos référents).</p>
        <ul class="mt-3 text-sm text-emerald-950 list-disc pl-5">
            <?php foreach ($needsList as $nk): ?>
            <li><?= htmlspecialchars($needLabels[$nk] ?? $nk, ENT_QUOTES, 'UTF-8') ?></li>
            <?php endforeach; ?>
        </ul>
    </section>
    <?php endif; ?>

    <?php if ($orbatBlocks !== []): ?>
    <section class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
        <h2 class="text-sm font-black uppercase tracking-wider text-slate-800">Structure (ORBAT) des unités engagées</h2>
        <p class="mt-2 text-xs text-slate-600">Vue indicative basée sur les unités déclarées par chaque communauté.</p>
        <div class="mt-4 grid gap-6 md:grid-cols-2">
            <?php foreach ($orbatBlocks as $block): ?>
            <div class="rounded-lg border border-slate-100 bg-slate-50/80 p-4">
                <h3 class="text-sm font-bold text-slate-900"><?= htmlspecialchars($block['tenant_name'] ?? '', ENT_QUOTES, 'UTF-8') ?></h3>
                <?php if (empty($block['units'])): ?>
                <p class="mt-2 text-xs text-slate-500">Aucune unité renseignée.</p>
                <?php else: ?>
                <ul class="mt-2 text-xs text-slate-700 space-y-1 list-disc pl-4">
                    <?php foreach ($block['units'] as $u): ?>
                    <li><?= htmlspecialchars((string) ($u['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></li>
                    <?php endforeach; ?>
                </ul>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endif; ?>
</div>
