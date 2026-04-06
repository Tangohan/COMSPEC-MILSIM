<?php
declare(strict_types=1);
$m = $interteamMission ?? [];
$participants = $interteamParticipants ?? [];
$grants = $interteamGrants ?? [];
$isLead = !empty($interteamIsLead);
$canManage = !empty($interteamCanManage);
$canRespond = !empty($interteamCanRespond);
$partners = $interteamPartnerPicker ?? [];
$csrf = $csrfToken ?? \App\Core\Csrf::token();
$sid = (int) ($m['id'] ?? 0);
$status = (string) ($m['status'] ?? '');
$sessionTenantId = (int) ($sessionTenantId ?? 0);

$myParticipant = null;
foreach ($participants as $p) {
    if ((int) ($p['tenant_id'] ?? 0) === $sessionTenantId) {
        $myParticipant = $p;
        break;
    }
}
$myStatus = (string) ($myParticipant['status'] ?? '');
$isPartner = ($myParticipant['role'] ?? '') === 'partner';
?>
<div class="max-w-3xl mx-auto px-6 py-10">
    <div class="mb-8">
        <a href="<?= url('admin/interteam-missions') ?>" class="text-sm font-medium text-slate-600 hover:text-slate-900 underline">← Retour à la liste</a>
        <h1 class="mt-4 text-2xl font-black text-slate-900"><?= htmlspecialchars((string) ($m['title'] ?? ''), ENT_QUOTES, 'UTF-8') ?></h1>
        <p class="mt-2 text-sm text-slate-600">État : <strong class="text-slate-800"><?= htmlspecialchars($status, ENT_QUOTES, 'UTF-8') ?></strong></p>
    </div>

    <section class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm mb-6">
        <h2 class="text-sm font-black uppercase tracking-wider text-slate-800">Unités participantes</h2>
        <ul class="mt-4 divide-y divide-slate-100">
            <?php foreach ($participants as $p): ?>
            <li class="py-3 flex flex-wrap justify-between gap-2 text-sm">
                <span class="font-semibold text-slate-900"><?= htmlspecialchars((string) ($p['tenant_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span>
                <span class="text-slate-600"><?= htmlspecialchars((string) ($p['role'] ?? ''), ENT_QUOTES, 'UTF-8') ?> — <?= htmlspecialchars((string) ($p['status'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span>
            </li>
            <?php endforeach; ?>
        </ul>

        <?php if ($isPartner && $myStatus === 'invited' && $canRespond): ?>
        <div class="mt-6 flex flex-wrap gap-3">
            <form method="post" action="<?= url('admin/interteam-missions/' . $sid . '/accept') ?>">
                <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
                <button type="submit" class="rounded-xl bg-emerald-700 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-800">Accepter la mission</button>
            </form>
            <form method="post" action="<?= url('admin/interteam-missions/' . $sid . '/decline') ?>">
                <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
                <button type="submit" class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-800 hover:bg-slate-50">Refuser</button>
            </form>
        </div>
        <?php endif; ?>

        <?php if ($canManage && $status === 'draft'): ?>
        <form method="post" action="<?= url('admin/interteam-missions/' . $sid . '/invite') ?>" class="mt-6 flex flex-wrap items-end gap-3 border-t border-slate-100 pt-6">
            <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
            <div class="flex-1 min-w-[200px]">
                <label for="partner_tenant_id" class="block text-xs font-bold uppercase tracking-wider text-slate-500 mb-1">Inviter une unité</label>
                <select id="partner_tenant_id" name="partner_tenant_id" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
                    <option value="">— Choisir —</option>
                    <?php foreach ($partners as $t): ?>
                    <option value="<?= (int) ($t['id'] ?? 0) ?>"><?= htmlspecialchars((string) ($t['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">Inviter</button>
        </form>
        <form method="post" action="<?= url('admin/interteam-missions/' . $sid . '/activate') ?>" class="mt-4">
            <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
            <button type="submit" class="rounded-xl border border-emerald-300 bg-emerald-50 px-4 py-2 text-sm font-semibold text-emerald-900 hover:bg-emerald-100">Rendre la mission opérationnelle</button>
            <p class="mt-2 text-xs text-slate-500">Toutes les unités invitées doivent avoir accepté avant l’activation.</p>
        </form>
        <?php endif; ?>
    </section>

    <?php if ($canManage && $status === 'active'): ?>
    <section class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm mb-6">
        <h2 class="text-sm font-black uppercase tracking-wider text-slate-800">Partager un sujet du brief</h2>
        <p class="mt-2 text-xs text-slate-600 leading-relaxed">Indiquez l’identifiant numérique du sujet (visible dans l’adresse du brief sur votre unité) et l’unité destinataire. Les membres de l’unité invitée verront le sujet dans leur brief, en lecture seule.</p>
        <form method="post" action="<?= url('admin/interteam-missions/' . $sid . '/grant-topic') ?>" class="mt-4 grid gap-3 sm:grid-cols-2">
            <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
            <div>
                <label class="block text-xs font-bold text-slate-500 mb-1">Numéro du sujet</label>
                <input type="number" name="topic_id" min="1" required class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-500 mb-1">Unité destinataire</label>
                <select name="consumer_tenant_id" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" required>
                    <option value="">— Choisir —</option>
                    <?php foreach ($participants as $p): ?>
                    <?php if (($p['role'] ?? '') === 'partner' && ($p['status'] ?? '') === 'active'): ?>
                    <option value="<?= (int) ($p['tenant_id'] ?? 0) ?>"><?= htmlspecialchars((string) ($p['tenant_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></option>
                    <?php endif; ?>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="sm:col-span-2">
                <button type="submit" class="rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">Ajouter le partage</button>
            </div>
        </form>

        <?php if (!empty($grants)): ?>
        <h3 class="mt-8 text-xs font-black uppercase tracking-wider text-slate-500">Partages actifs</h3>
        <ul class="mt-3 space-y-2 text-sm">
            <?php foreach ($grants as $g): ?>
            <li class="flex flex-wrap items-center justify-between gap-2 rounded-lg border border-slate-100 bg-slate-50/80 px-3 py-2">
                <span><?= htmlspecialchars((string) ($g['grant_type'] ?? ''), ENT_QUOTES, 'UTF-8') ?> #<?= (int) ($g['resource_id'] ?? 0) ?> → <?= htmlspecialchars((string) ($g['consumer_tenant_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span>
                <form method="post" action="<?= url('admin/interteam-missions/' . $sid . '/grants/' . (int) ($g['id'] ?? 0) . '/revoke') ?>" onsubmit="return confirm('Retirer ce partage ?');">
                    <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
                    <button type="submit" class="text-xs font-semibold text-rose-700 hover:text-rose-900">Retirer</button>
                </form>
            </li>
            <?php endforeach; ?>
        </ul>
        <?php endif; ?>
    </section>
    <?php endif; ?>
</div>
