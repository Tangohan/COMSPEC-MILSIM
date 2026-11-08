<?php
declare(strict_types=1);

$tenant = $tenant ?? [];
$formAction = $formAction ?? url('enlistment');
$tenantName = trim((string) ($tenant['name'] ?? 'Communauté'));
$communityConfig = $communityConfig ?? [];
$requireAiAck = array_key_exists('require_ai_ack', $communityConfig) ? (bool) $communityConfig['require_ai_ack'] : true;
$enlistmentContext = is_array($enlistmentContext ?? null) ? $enlistmentContext : [];
$isAuthenticated = !empty($enlistmentContext['isAuthenticated']);
$showMilsimUnavailableNotice = $showMilsimUnavailableNotice ?? false;
$simpleEnlistmentUrl = $simpleEnlistmentUrl ?? $formAction;
$selectedRecruitmentOpening = is_array($selectedRecruitmentOpening ?? null) ? $selectedRecruitmentOpening : null;
$enlistmentMemberOpeningInsight = is_array($enlistmentMemberOpeningInsight ?? null) ? $enlistmentMemberOpeningInsight : null;
$milsimPack = $milsimPack ?? \App\Services\Community\EnlistmentMilsimPackService::defaultPack();
$simpleMotivation = \App\Services\Community\EnlistmentMilsimPackService::normalizeMotivationSection(
    is_array($milsimPack['motivation'] ?? null) ? $milsimPack['motivation'] : null,
    is_array($milsimPack['fields'] ?? null) ? $milsimPack['fields'] : null
);
$simpleMotivationLabel = (string) ($simpleMotivation['why_join']['label'] ?? 'Motivation');
$simpleMotivationPlaceholder = (string) ($simpleMotivation['why_join']['placeholder'] ?? 'Pourquoi souhaitez-vous rejoindre la communauté ?');
$simpleMotivationHelp = trim((string) ($simpleMotivation['why_join']['help'] ?? ''));
$simpleMotivationRequired = !empty($simpleMotivation['why_join']['required']);
$accountTheme = 'light';
?>
<div class="max-w-2xl mx-auto px-4 py-12">
    <h1 class="text-2xl font-black uppercase tracking-tight text-slate-900 mb-2">Inscription — <?= htmlspecialchars($tenantName) ?></h1>
    <p class="text-sm text-slate-600 mb-6">Formulaire condensé pour une inscription rapide.</p>

    <?php if ($showMilsimUnavailableNotice): ?>
        <div class="mb-6 p-5 rounded-2xl bg-amber-50 border border-amber-300 text-sm text-amber-950 shadow-sm">
            <p class="font-black uppercase tracking-widest text-[10px] text-amber-800 mb-2">Questionnaire MilSim indisponible</p>
            <p class="leading-relaxed">Cette communauté n’utilise pas le dossier MilSim complet. Remplissez le <strong>formulaire simplifié</strong> juste sous ce message pour soumettre votre candidature.</p>
        </div>
    <?php endif; ?>

    <?php if (!$isAuthenticated): ?>
        <?php require base_path('views/enlistment/_account_gate.php'); ?>
    <?php else: ?>
    <form id="formulaire-inscription-simple" method="post" action="<?= htmlspecialchars($simpleEnlistmentUrl) ?>" class="space-y-5 bg-white border border-slate-200 rounded-2xl p-6 shadow-sm">
        <?= \App\Core\Csrf::field() ?>
        <?php require base_path('views/enlistment/_invite_link.php'); ?>
        <?php if ($selectedRecruitmentOpening !== null && !empty($selectedRecruitmentOpening['id'])): ?>
            <input type="hidden" name="enlistment_opening_id" value="<?= (int) $selectedRecruitmentOpening['id'] ?>">
            <div class="rounded-xl border border-sky-200 bg-sky-50 p-5">
                <p class="text-[10px] font-black uppercase tracking-widest text-sky-800">Candidature ciblée</p>
                <p class="mt-2 text-sm font-bold text-slate-900">Vous postulez pour : <?= htmlspecialchars((string) ($selectedRecruitmentOpening['title'] ?? ''), ENT_QUOTES, 'UTF-8') ?></p>
                <p class="mt-1 text-xs text-sky-900/80 leading-relaxed">Votre dossier sera rattaché à cet avis pour le suivi côté équipe RH.</p>
            </div>
            <?php if ($enlistmentMemberOpeningInsight !== null): ?>
                <?php require base_path('views/partials/enlistment_member_opening_insight.php'); ?>
            <?php endif; ?>
        <?php endif; ?>

        <?php require base_path('views/enlistment/_account_identity.php'); ?>

        <div class="space-y-5">
            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1" for="simple-availability">Disponibilité</label>
                <input type="text" id="simple-availability" name="availability" class="w-full border border-slate-300 rounded-xl px-4 py-3 text-sm" placeholder="Soirs, week-end...">
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1" for="simple-motivation"><?= htmlspecialchars($simpleMotivationLabel) ?><?php if ($simpleMotivationRequired): ?> <span class="font-normal text-slate-500">(obligatoire)</span><?php endif; ?></label>
                <?php if ($simpleMotivationHelp !== ''): ?>
                    <p class="text-xs text-slate-600 mb-1"><?= htmlspecialchars($simpleMotivationHelp) ?></p>
                <?php endif; ?>
                <textarea id="simple-motivation" name="motivation_why_join" rows="4" class="w-full border border-slate-300 rounded-xl px-4 py-3 text-sm" placeholder="<?= htmlspecialchars($simpleMotivationPlaceholder) ?>"<?= $simpleMotivationRequired ? ' required' : '' ?>></textarea>
            </div>
        </div>

        <?php require base_path('views/enlistment/_account_consent.php'); ?>

        <?php if ($requireAiAck): ?>
            <label class="flex items-start gap-3 text-sm text-slate-700">
                <input type="checkbox" name="no_ai_confirmed" value="1" class="mt-0.5" required>
                <span>Je confirme l'absence d'IA dans ce dossier.</span>
            </label>
        <?php else: ?>
            <input type="hidden" name="no_ai_confirmed" value="1">
        <?php endif; ?>

        <button type="submit" class="w-full py-3.5 bg-slate-900 text-white text-xs font-black uppercase tracking-widest rounded-xl hover:bg-emerald-600">Soumettre la candidature</button>
    </form>
    <?php endif; ?>
</div>
