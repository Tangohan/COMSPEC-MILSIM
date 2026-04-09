<?php
declare(strict_types=1);

use App\Support\CooperationDictionary;

$m = $interteamMission ?? [];
$lockMode = (string) ($m['exchange_lock_mode'] ?? 'none');
$participants = $interteamParticipants ?? [];
$grants = $interteamGrants ?? [];
$isLead = !empty($interteamIsLead);
$canManage = !empty($interteamCanManage);
$topicChoices = $interteamTopicChoices ?? [];
$csrf = $csrfToken ?? \App\Core\Csrf::token();
$sid = (int) ($m['id'] ?? 0);
$status = (string) ($m['status'] ?? '');
$sessionTenantId = (int) ($sessionTenantId ?? 0);
$consentDone = !empty($interteamConsentDone);
$coopTopicUrl = trim((string) ($interteamCoopTopicUrl ?? ''));

$myGrantCount = 0;
if (!empty($grants)) {
    foreach ($grants as $g) {
        if ((int) ($g['consumer_tenant_id'] ?? 0) === $sessionTenantId) {
            $myGrantCount++;
        }
    }
}
?>
<div class="max-w-4xl mx-auto px-6 py-10 space-y-8">
    <div>
        <a href="<?= htmlspecialchars(cooperation_mission_show_url($sid), ENT_QUOTES, 'UTF-8') ?>" class="text-sm font-medium text-slate-600 hover:text-slate-900 underline">← Synthèse</a>
        <?php require base_path('views/back_office/cooperation/missions/_nav.php'); ?>
        <h1 class="mt-4 text-2xl font-black text-slate-900">Espace commun</h1>
        <p class="mt-2 text-sm text-slate-600">Fil coordonné sur le brief de l’unité support et autorisations d’accès complémentaires vers d’autres espaces d’échange.</p>
    </div>

    <?php if (!$consentDone && $status === 'active'): ?>
    <p class="text-sm text-amber-900 bg-amber-50 border border-amber-200 rounded-lg px-3 py-2">Pour lire ou écrire sur le fil partagé, validez votre <a class="font-semibold underline" href="<?= htmlspecialchars(cooperation_mission_consent_url($sid), ENT_QUOTES, 'UTF-8') ?>">autorisation de partage</a> (code par e-mail).</p>
    <?php endif; ?>

    <?php if ($coopTopicUrl !== '' && $status === 'active'): ?>
    <section class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
        <h2 class="text-sm font-black uppercase tracking-wider text-slate-800">Fil principal</h2>
        <p class="mt-2 text-sm text-slate-600">Espace d’échange lié à cette coopération, hébergé sur le brief de l’unité support.</p>
        <a href="<?= htmlspecialchars($coopTopicUrl, ENT_QUOTES, 'UTF-8') ?>" class="mt-4 inline-flex rounded-xl bg-sky-800 px-4 py-2 text-sm font-semibold text-white hover:bg-sky-900">Ouvrir l’espace commun dans le forum</a>
    </section>
    <?php elseif ($status === 'active'): ?>
    <p class="text-sm text-slate-600">L’espace commun sera disponible une fois le fil créé (lancement de la coopération).</p>
    <?php else: ?>
    <p class="text-sm text-slate-600">L’espace commun s’ouvre lorsque la coopération est lancée.</p>
    <?php endif; ?>

    <?php if (!$isLead && $myGrantCount > 0 && $status === 'active'): ?>
    <section class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
        <h2 class="text-sm font-black uppercase tracking-wider text-slate-800">Vos autorisations sur le brief partenaire</h2>
        <p class="mt-2 text-sm text-slate-600"><?= (int) $myGrantCount ?> accès actif<?= $myGrantCount > 1 ? 's' : '' ?> vers votre unité. Retrouvez les sujets dans votre forum (section coopération).</p>
    </section>
    <?php endif; ?>

    <?php if ($isLead && $canManage && $status === 'active'): ?>
    <section class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
        <h2 class="text-sm font-black uppercase tracking-wider text-slate-800">Autorisations d’accès à l’espace commun</h2>
        <p class="mt-2 text-xs text-slate-600 leading-relaxed">Ajoutez un accès vers un autre espace d’échange du brief de l’unité support. Le fil principal reste disponible pour toutes les unités actives.</p>
        <form method="post" action="<?= htmlspecialchars(cooperation_missions_url($sid . '/grant-topic'), ENT_QUOTES, 'UTF-8') ?>" class="mt-4 grid gap-3 sm:grid-cols-2">
            <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
            <div class="sm:col-span-2">
                <label class="block text-xs font-bold text-slate-500 mb-1">Espace d’échange à partager</label>
                <select name="topic_id" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" required>
                    <option value="">— Choisir —</option>
                    <?php foreach ($topicChoices as $opt): ?>
                    <option value="<?= (int) ($opt['id'] ?? 0) ?>"><?= htmlspecialchars((string) ($opt['title'] ?? ''), ENT_QUOTES, 'UTF-8') ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-500 mb-1">Unité destinataire</label>
                <select name="consumer_tenant_id" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" required>
                    <option value="">— Choisir —</option>
                    <?php foreach ($participants as $p): ?>
                    <?php if (in_array(($p['role'] ?? ''), ['partner', 'co_lead'], true) && ($p['status'] ?? '') === 'active'): ?>
                    <option value="<?= (int) ($p['tenant_id'] ?? 0) ?>"><?= htmlspecialchars((string) ($p['tenant_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></option>
                    <?php endif; ?>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="sm:col-span-2">
                <button type="submit" class="rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">Ajouter l’autorisation</button>
            </div>
        </form>

        <?php if (!empty($grants)): ?>
        <h3 class="mt-8 text-xs font-black uppercase tracking-wider text-slate-500">Autorisations actives</h3>
        <ul class="mt-3 space-y-2 text-sm">
            <?php foreach ($grants as $g): ?>
            <li class="flex flex-wrap items-center justify-between gap-2 rounded-lg border border-slate-100 bg-slate-50/80 px-3 py-2">
                <span><?php
                    $gt = (string) ($g['grant_type'] ?? '');
                    $gtLabel = CooperationDictionary::forumGrantTypeLabel($gt);
                    echo htmlspecialchars($gtLabel . ' — unité « ' . (string) ($g['consumer_tenant_name'] ?? '') . ' »', ENT_QUOTES, 'UTF-8');
                ?></span>
                <form method="post" action="<?= htmlspecialchars(cooperation_missions_url($sid . '/grants/' . (int) ($g['id'] ?? 0) . '/revoke'), ENT_QUOTES, 'UTF-8') ?>" onsubmit="return confirm('Retirer cette autorisation ?');">
                    <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
                    <button type="submit" class="text-xs font-semibold text-rose-700 hover:text-rose-900">Retirer</button>
                </form>
            </li>
            <?php endforeach; ?>
        </ul>
        <?php endif; ?>
    </section>
    <?php endif; ?>

    <?php if ($isLead && $canManage && $status === 'active'): ?>
    <section class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
        <h2 class="text-sm font-black uppercase tracking-wider text-slate-800">Verrouillage de l’espace commun</h2>
        <p class="mt-2 text-xs text-slate-600">Définissez si les partenaires peuvent encore publier sur le fil principal hébergé sur votre brief.</p>
        <form method="post" action="<?= htmlspecialchars(cooperation_missions_url($sid . '/exchange-lock'), ENT_QUOTES, 'UTF-8') ?>" class="mt-4 flex flex-wrap items-end gap-3">
            <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
            <div class="flex-1 min-w-[220px]">
                <label class="block text-xs font-bold text-slate-500 mb-1">Mode</label>
                <select name="exchange_lock_mode" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
                    <?php foreach (CooperationDictionary::exchangeLockModeChoices() as $val => $lab): ?>
                    <option value="<?= htmlspecialchars($val, ENT_QUOTES, 'UTF-8') ?>"<?= $lockMode === $val ? ' selected' : '' ?>><?= htmlspecialchars($lab, ENT_QUOTES, 'UTF-8') ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="rounded-xl bg-slate-800 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-900">Enregistrer</button>
        </form>
    </section>
    <?php endif; ?>
</div>
