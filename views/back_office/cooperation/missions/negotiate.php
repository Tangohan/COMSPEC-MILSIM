<?php
declare(strict_types=1);

$m = $interteamMission ?? [];
$csrf = $csrfToken ?? \App\Core\Csrf::token();
$sid = (int) ($m['id'] ?? 0);
$status = (string) ($m['status'] ?? '');
$canManage = !empty($interteamCanManage);
$canPilot = !empty($interteamCanPilot);
$partnerCanCounter = !empty($interteamPartnerCanCounter);
$counterPending = !empty($interteamCounterPending);
$cpStatus = (string) ($m['counter_proposal_status'] ?? '');
$cpRaw = $m['counter_proposal_json'] ?? null;
$cp = [];
if ($cpRaw !== null && $cpRaw !== '') {
    if (is_string($cpRaw)) {
        $cp = json_decode($cpRaw, true) ?: [];
    } elseif (is_array($cpRaw)) {
        $cp = $cpRaw;
    }
}
$cpTenantId = (int) ($m['counter_proposal_tenant_id'] ?? 0);
$sessionTenantId = (int) ($sessionTenantId ?? 0);
$participants = $interteamParticipants ?? [];
$cpTenantName = '';
foreach ($participants as $p) {
    if ((int) ($p['tenant_id'] ?? 0) === $cpTenantId) {
        $cpTenantName = (string) ($p['tenant_name'] ?? '');
        break;
    }
}
$labels = [
    'calendar' => ['Calendrier ou créneaux proposés', 'cp_calendar'],
    'support_unit' => ['Autre unité support ou hébergement proposé', 'cp_support_unit'],
    'scope' => ['Périmètre ou objectifs ajustés', 'cp_scope'],
    'sharing' => ['Niveau de partage souhaité', 'cp_sharing'],
    'coordination' => ['Mode de coordination', 'cp_coordination'],
    'conditions' => ['Conditions suspensives ou réserves', 'cp_conditions'],
];
?>
<div class="max-w-4xl mx-auto px-6 py-10 space-y-8">
    <div>
        <a href="<?= htmlspecialchars(cooperation_mission_show_url($sid), ENT_QUOTES, 'UTF-8') ?>" class="text-sm font-medium text-slate-600 hover:text-slate-900 underline">← Synthèse</a>
        <?php require base_path('views/back_office/cooperation/missions/_nav.php'); ?>
        <h1 class="mt-4 text-2xl font-black text-slate-900">Négociation</h1>
        <p class="mt-2 text-sm text-slate-600">Contre-propositions structurées avant le lancement définitif. L’unité support tranche sur l’intégration ou le refus.</p>
    </div>

    <?php if ($status !== 'pending'): ?>
    <p class="text-sm text-slate-600 rounded-lg border border-slate-200 bg-slate-50 px-4 py-3">La négociation structurée s’applique lorsque la coopération est en attente de validation des unités. Une fois lancée, utilisez l’espace commun et la chronologie.</p>
    <?php endif; ?>

    <?php if ($counterPending && $canPilot && $canManage && $cp !== []): ?>
    <section class="rounded-xl border border-amber-200 bg-amber-50/60 p-6 shadow-sm">
        <h2 class="text-sm font-black uppercase tracking-wider text-amber-950">Contre-proposition en attente</h2>
        <?php if ($cpTenantName !== ''): ?>
        <p class="mt-2 text-xs text-amber-950/90">Proposée par : <strong><?= htmlspecialchars($cpTenantName, ENT_QUOTES, 'UTF-8') ?></strong></p>
        <?php endif; ?>
        <dl class="mt-4 space-y-3 text-sm">
            <?php foreach ($labels as $key => [$lab, $_n]): ?>
            <?php $val = trim((string) ($cp[$key] ?? '')); ?>
            <?php if ($val !== ''): ?>
            <div>
                <dt class="font-semibold text-amber-950"><?= htmlspecialchars($lab, ENT_QUOTES, 'UTF-8') ?></dt>
                <dd class="mt-1 text-amber-950/90 whitespace-pre-wrap"><?= htmlspecialchars($val, ENT_QUOTES, 'UTF-8') ?></dd>
            </div>
            <?php endif; ?>
            <?php endforeach; ?>
        </dl>
        <div class="mt-6 flex flex-wrap gap-3">
            <form method="post" action="<?= htmlspecialchars(cooperation_missions_url($sid . '/counter-proposal/respond'), ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="decision" value="accept">
                <button type="submit" class="rounded-xl bg-emerald-700 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-800">Intégrer et poursuivre</button>
            </form>
            <form method="post" action="<?= htmlspecialchars(cooperation_missions_url($sid . '/counter-proposal/respond'), ENT_QUOTES, 'UTF-8') ?>" onsubmit="return confirm('Refuser cette contre-proposition ?');">
                <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="decision" value="decline">
                <button type="submit" class="rounded-xl border border-rose-300 bg-white px-4 py-2 text-sm font-semibold text-rose-900 hover:bg-rose-50">Refuser</button>
            </form>
        </div>
    </section>
    <?php elseif ($cpStatus === 'declined' && $partnerCanCounter): ?>
    <p class="text-sm text-slate-700 border border-slate-200 rounded-lg px-4 py-3 bg-white">La dernière contre-proposition a été refusée par l’unité support. Vous pouvez en soumettre une nouvelle.</p>
    <?php endif; ?>

    <?php if ($partnerCanCounter && $status === 'pending' && (!$counterPending || $cpStatus === 'declined')): ?>
    <section class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
        <h2 class="text-sm font-black uppercase tracking-wider text-slate-800">Votre contre-proposition</h2>
        <p class="mt-2 text-xs text-slate-600">Renseignez les champs utiles ; un seul champ rempli suffit pour transmettre.</p>
        <form method="post" action="<?= htmlspecialchars(cooperation_missions_url($sid . '/counter-proposal'), ENT_QUOTES, 'UTF-8') ?>" class="mt-4 space-y-4">
            <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
            <?php foreach ($labels as $key => [$lab, $inputName]): ?>
            <div>
                <label class="block text-xs font-bold text-slate-500 mb-1"><?= htmlspecialchars($lab, ENT_QUOTES, 'UTF-8') ?></label>
                <textarea name="<?= htmlspecialchars($inputName, ENT_QUOTES, 'UTF-8') ?>" rows="2" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm"></textarea>
            </div>
            <?php endforeach; ?>
            <button type="submit" class="rounded-xl bg-slate-900 px-5 py-2.5 text-sm font-semibold text-white hover:bg-slate-800">Transmettre à l’unité support</button>
        </form>
    </section>
    <?php endif; ?>
</div>
