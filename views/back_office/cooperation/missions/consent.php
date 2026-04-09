<?php
declare(strict_types=1);

use App\Support\CooperationDictionary;

$m = $interteamMission ?? [];
$mid = (int) ($m['id'] ?? 0);
$return = trim((string) ($interteamConsentReturn ?? ''));
$csrf = $csrfToken ?? \App\Core\Csrf::token();
$suggested = $cooperationSuggestedShareKeys ?? [];
$consentKeys = ['brief', 'liaison', 'competency', 'identity', 'org_structure', 'qualification', 'readiness', 'material', 'map', 'documents', 'minutes', 'meeting', 'cert_excerpt'];
?>
<div class="max-w-4xl mx-auto px-6 py-10">
    <?php require base_path('views/back_office/cooperation/missions/_nav.php'); ?>
    <h1 class="text-2xl font-black text-slate-900 mb-2">Autorisation de partage</h1>
    <p class="text-sm text-slate-600 mb-6 leading-relaxed max-w-2xl">Pour accéder aux échanges inter-unités sur la coopération <strong><?= htmlspecialchars((string) ($m['title'] ?? ''), ENT_QUOTES, 'UTF-8') ?></strong>, indiquez les familles d’informations couvertes par votre accord, puis validez avec un code reçu par e-mail.</p>

    <form method="post" action="<?= htmlspecialchars(cooperation_mission_consent_url($mid) . '/send-otp', ENT_QUOTES, 'UTF-8') ?>" class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm space-y-4 mb-6 max-w-2xl">
        <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
        <?php if ($return !== ''): ?>
        <input type="hidden" name="return" value="<?= htmlspecialchars($return, ENT_QUOTES, 'UTF-8') ?>">
        <?php endif; ?>
        <fieldset>
            <legend class="text-sm font-semibold text-slate-900 mb-3">Familles de données concernées</legend>
            <div class="grid gap-2 sm:grid-cols-1">
                <?php foreach ($consentKeys as $k): ?>
                <label class="flex items-start gap-2 text-sm text-slate-700">
                    <input type="checkbox" name="share_<?= htmlspecialchars($k, ENT_QUOTES, 'UTF-8') ?>" value="1" class="mt-1 rounded border-slate-300"<?= in_array($k, $suggested, true) ? ' checked' : '' ?>>
                    <span><?= htmlspecialchars(CooperationDictionary::dataSharingFamilyLabel($k), ENT_QUOTES, 'UTF-8') ?></span>
                </label>
                <?php endforeach; ?>
            </div>
        </fieldset>
        <div>
            <label for="justification_sensitive" class="block text-xs font-bold uppercase tracking-wider text-slate-500 mb-1">Justification (si partage sensible)</label>
            <textarea id="justification_sensitive" name="justification_sensitive" rows="3" maxlength="4000" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" placeholder="Facultatif : cadre opérationnel ou référence hiérarchique…"></textarea>
        </div>
        <button type="submit" class="rounded-xl bg-slate-900 px-5 py-2.5 text-sm font-semibold text-white hover:bg-slate-800">Recevoir le code par e-mail</button>
    </form>

    <form method="post" action="<?= htmlspecialchars(cooperation_mission_consent_url($mid) . '/verify-otp', ENT_QUOTES, 'UTF-8') ?>" class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm space-y-4 max-w-lg">
        <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
        <?php if ($return !== ''): ?>
        <input type="hidden" name="return" value="<?= htmlspecialchars($return, ENT_QUOTES, 'UTF-8') ?>">
        <?php endif; ?>
        <div>
            <label for="otp_code" class="block text-sm font-semibold text-slate-900">Code à six chiffres</label>
            <input id="otp_code" name="otp_code" type="text" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="one-time-code" class="mt-2 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm tracking-widest" placeholder="000000" required>
        </div>
        <button type="submit" class="rounded-xl bg-emerald-700 px-5 py-2.5 text-sm font-semibold text-white hover:bg-emerald-800">Valider</button>
    </form>

    <p class="mt-6"><a href="<?= htmlspecialchars(cooperation_mission_show_url($mid), ENT_QUOTES, 'UTF-8') ?>" class="text-sm text-slate-600 underline">Retour à la synthèse</a></p>
</div>
