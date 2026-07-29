<?php
$tenant = $tenant ?? [];
$formAction = $formAction ?? url('enlistment');
$tenantName = trim((string) ($tenant['name'] ?? 'Communauté'));
$communityConfig = $communityConfig ?? [];
$requireAiAck = array_key_exists('require_ai_ack', $communityConfig) ? (bool) $communityConfig['require_ai_ack'] : true;
$ctx = $enlistmentContext ?? [];
$canUseAccount = !empty($ctx['canUseAccount']);
$prefill = array_merge(
    ['full_name' => '', 'email' => ''],
    is_array($ctx['prefill'] ?? null) ? $ctx['prefill'] : []
);
$platformEmail = trim((string) ($ctx['platform_email'] ?? ''));
$showPlatformEmailOption = $platformEmail !== '';
$recruitmentPresets = $ctx['recruitmentPresets'] ?? [];
$hasMembershipOnTarget = !empty($ctx['hasMembershipOnTarget']);
$switchToTargetUrl = $ctx['switchToTargetUrl'] ?? null;
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
?>
<script>
document.addEventListener('alpine:init', function () {
    Alpine.data('simpleEnlistmentForm', function () {
        return {
            flow: <?= json_encode($canUseAccount ? 'account' : 'guest', JSON_UNESCAPED_UNICODE) ?>,
            showConsentModal: false,
            usePlatformEmail: false,
            platformEmail: <?= json_encode($platformEmail, JSON_UNESCAPED_UNICODE) ?>,
            prefillEmail: <?= json_encode($prefill['email'] ?? '', JSON_UNESCAPED_UNICODE) ?>,
            syncGuestEmail: function () {
                var el = document.querySelector('#simple-enlist-email');
                if (!el) return;
                el.value = this.usePlatformEmail ? this.platformEmail : this.prefillEmail;
            },
            applyPreset: function (ev) {
                var opt = ev.target.selectedOptions[0];
                if (!opt || !opt.dataset.payload) return;
                try {
                    var p = JSON.parse(opt.dataset.payload);
                    var av = document.querySelector('input[name="availability"]');
                    var mo = document.querySelector('textarea[name="motivation_why_join"]');
                    if (av && p.availability) av.value = p.availability;
                    if (mo && p.motivation_why_join) mo.value = p.motivation_why_join;
                } catch (e) {}
            },
        };
    });
});
</script>
<div class="max-w-2xl mx-auto px-4 py-12" x-data="simpleEnlistmentForm">
    <h1 class="text-2xl font-black uppercase tracking-tight text-slate-900 mb-2">Inscription — <?= htmlspecialchars($tenantName) ?></h1>
    <p class="text-sm text-slate-500 mb-6">Mode simple activé : formulaire condensé pour onboarding rapide.</p>

    <?php if ($showMilsimUnavailableNotice): ?>
        <div class="mb-6 p-5 rounded-2xl bg-amber-50 border border-amber-300 text-sm text-amber-950 shadow-sm">
            <p class="font-black uppercase tracking-widest text-[10px] text-amber-800 mb-2">Questionnaire MilSim indisponible</p>
            <p class="leading-relaxed">Cette communauté n’utilise pas le dossier MilSim complet. Remplissez le <strong>formulaire simplifié</strong> juste sous ce message pour soumettre votre candidature.</p>
        </div>
    <?php endif; ?>

    <?php if ($canUseAccount): ?>
        <div class="mb-6 p-4 rounded-2xl bg-emerald-50 border border-emerald-200 text-sm text-emerald-900">
            <p class="font-bold">Compte Athena détecté</p>
            <p class="mt-1 text-emerald-800/90">Vous pouvez envoyer la candidature avec les données de votre compte (après consentement) ou remplir en invité.</p>
        </div>
    <?php elseif ($hasMembershipOnTarget && $switchToTargetUrl): ?>
        <div class="mb-6 p-4 rounded-2xl bg-amber-50 border border-amber-200 text-sm text-amber-950">
            <p class="font-bold">Autre communauté active</p>
            <p class="mt-1">Vous avez un compte sur cette communauté : basculez le contexte pour utiliser le dossier membre local (optionnel si le mode compte est déjà proposé).</p>
            <a href="<?= htmlspecialchars($switchToTargetUrl) ?>" class="mt-3 inline-flex text-xs font-black uppercase tracking-widest text-amber-950 underline">Basculer et continuer</a>
        </div>
    <?php else: ?>
        <div class="mb-6 p-4 rounded-2xl bg-slate-50 border border-slate-200 text-sm text-slate-700">
            <p class="font-bold">Compte Athena</p>
            <p class="mt-1">Vous avez déjà un compte ? <a href="<?= htmlspecialchars(url('login')) ?>" class="font-semibold text-slate-900 underline">Connectez-vous</a> pour l’associer à votre candidature. Sinon, continuez en invité.</p>
        </div>
    <?php endif; ?>

    <form id="formulaire-inscription-simple" method="post" action="<?= htmlspecialchars($simpleEnlistmentUrl) ?>" class="space-y-5 bg-white border border-slate-200 rounded-2xl p-6 shadow-sm">
        <?= \App\Core\Csrf::field() ?>
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
        <input type="hidden" name="enlistment_flow" :value="flow">

        <?php if ($canUseAccount): ?>
            <div class="flex rounded-xl border border-slate-200 p-1 bg-slate-50">
                <button type="button" @click="flow = 'account'" :class="flow === 'account' ? 'bg-white shadow text-slate-900' : 'text-slate-500'"
                    class="flex-1 py-2.5 text-xs font-black uppercase tracking-widest rounded-lg transition">Compte Athena</button>
                <button type="button" @click="flow = 'guest'" :class="flow === 'guest' ? 'bg-white shadow text-slate-900' : 'text-slate-500'"
                    class="flex-1 py-2.5 text-xs font-black uppercase tracking-widest rounded-lg transition">Invité</button>
            </div>
        <?php else: ?>
            <input type="hidden" name="enlistment_flow" value="guest">
        <?php endif; ?>

        <div x-show="flow === 'guest'" class="space-y-5" x-cloak>
            <div>
                <label class="block text-xs font-bold text-slate-600 mb-1">Nom complet</label>
                <input type="text" name="full_name" class="w-full border border-slate-200 rounded-xl px-4 py-3 text-sm guest-req" placeholder="Prénom Nom"
                    value="<?= htmlspecialchars($prefill['full_name'] ?? '') ?>"
                    x-bind:disabled="flow !== 'guest'">
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-600 mb-1">Email</label>
                <input type="email" name="email" id="simple-enlist-email" class="w-full border border-slate-200 rounded-xl px-4 py-3 text-sm guest-req" placeholder="email@exemple.com"
                    value="<?= htmlspecialchars($prefill['email'] ?? '') ?>"
                    x-bind:disabled="flow !== 'guest'"
                    x-bind:readonly="flow === 'guest' && usePlatformEmail"
                    autocomplete="email">
                <?php if ($showPlatformEmailOption): ?>
                <label class="mt-3 flex items-start gap-2.5 text-sm text-slate-700 cursor-pointer">
                    <input type="checkbox" name="use_platform_email" value="1" class="mt-0.5 rounded border-slate-300"
                        x-bind:disabled="flow !== 'guest'"
                        x-model="usePlatformEmail"
                        @change="syncGuestEmail()">
                    <span>Utiliser l’e-mail enregistré sur la plateforme (<span class="font-mono text-xs"><?= htmlspecialchars($platformEmail, ENT_QUOTES, 'UTF-8') ?></span>)</span>
                </label>
                <?php endif; ?>
            </div>
        </div>

        <div x-show="flow === 'account'" class="space-y-5" x-cloak>
            <?php if ($canUseAccount): ?>
                <div class="rounded-xl border border-slate-100 bg-slate-50 p-4 text-sm text-slate-700">
                    <p class="text-[10px] font-black uppercase tracking-widest text-slate-400 mb-2">Partage avec le staff recrutement</p>
                    <label class="flex items-center gap-2 mb-2">
                        <input type="checkbox" name="share_email" value="1" checked class="rounded border-slate-300" x-bind:disabled="flow !== 'account'" required>
                        <span>Partager mon <strong>email</strong> de connexion (recommandé)</span>
                    </label>
                    <label class="flex items-center gap-2 mb-2">
                        <input type="checkbox" name="share_name" value="1" checked class="rounded border-slate-300" x-bind:disabled="flow !== 'account'">
                        <span>Partager mon <strong>nom</strong> (affichage / dossier)</span>
                    </label>
                </div>

                <?php if (!empty($recruitmentPresets)): ?>
                    <div>
                        <label class="block text-xs font-bold text-slate-600 mb-1">Profil enregistré (optionnel)</label>
                        <select name="recruitment_preset_id" class="w-full border border-slate-200 rounded-xl px-4 py-3 text-sm bg-white" x-bind:disabled="flow !== 'account'" @change="applyPreset($event)">
                            <option value="">— Aucun —</option>
                            <?php foreach ($recruitmentPresets as $rp): ?>
                                <?php
                                $pid = (int) ($rp['id'] ?? 0);
                                $pl = (string) ($rp['label'] ?? '');
                                $pay = $rp['payload'] ?? [];
                                if (!is_array($pay)) {
                                    $pay = [];
                                }
                                ?>
                                <option value="<?= $pid ?>" data-payload="<?= htmlspecialchars(json_encode($pay, JSON_UNESCAPED_UNICODE)) ?>"><?= htmlspecialchars($pl) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <p class="text-xs text-slate-500 mt-1"><a href="<?= url('account/recruitment-presets') ?>" class="underline">Gérer mes profils</a></p>
                    </div>
                <?php else: ?>
                    <p class="text-xs text-slate-500"><a href="<?= url('account/recruitment-presets/create') ?>" class="underline">Créer un profil de candidature</a> pour préremplir motivation et disponibilité.</p>
                <?php endif; ?>

                <div class="rounded-xl border border-dashed border-slate-200 p-4">
                    <p class="text-xs text-slate-500 mb-2">Aperçu identité (non modifiable ici)</p>
                    <p class="text-sm font-mono text-slate-800"><?= htmlspecialchars($prefill['email'] ?? '') ?></p>
                    <p class="text-sm text-slate-600 mt-1"><?= htmlspecialchars($prefill['full_name'] ?: '—') ?></p>
                </div>

                <div class="rounded-xl bg-slate-900 text-white p-4">
                    <label class="flex items-start gap-3 cursor-pointer">
                        <input type="checkbox" name="consent_data_sharing" value="1" class="mt-1 rounded border-white/30" x-bind:disabled="flow !== 'account'" required>
                        <span class="text-sm leading-relaxed">J’accepte que les informations cochées ci-dessus et le contenu de ma candidature soient transmis au staff de <strong><?= htmlspecialchars($tenantName) ?></strong> pour traitement du recrutement.</span>
                    </label>
                    <button type="button" @click="showConsentModal = true" class="mt-3 text-[10px] font-black uppercase tracking-widest text-emerald-400 hover:text-emerald-300">Détails du traitement</button>
                </div>
            <?php endif; ?>
        </div>

        <div class="space-y-5">
            <div>
                <label class="block text-xs font-bold text-slate-600 mb-1">Disponibilité</label>
                <input type="text" name="availability" class="w-full border border-slate-200 rounded-xl px-4 py-3 text-sm" placeholder="Soirs, week-end...">
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-600 mb-1"><?= htmlspecialchars($simpleMotivationLabel) ?><?php if ($simpleMotivationRequired): ?> <span class="font-normal text-slate-400">(obligatoire)</span><?php endif; ?></label>
                <?php if ($simpleMotivationHelp !== ''): ?>
                    <p class="text-xs text-slate-500 mb-1"><?= htmlspecialchars($simpleMotivationHelp) ?></p>
                <?php endif; ?>
                <textarea name="motivation_why_join" rows="4" class="w-full border border-slate-200 rounded-xl px-4 py-3 text-sm" placeholder="<?= htmlspecialchars($simpleMotivationPlaceholder) ?>"<?= $simpleMotivationRequired ? ' required' : '' ?>></textarea>
            </div>
        </div>

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

    <div x-show="showConsentModal" class="fixed inset-0 z-[200] flex items-center justify-center p-4 bg-slate-900/60" x-cloak style="display: none;" @click.self="showConsentModal = false">
        <div class="bg-white rounded-2xl max-w-lg w-full p-6 shadow-2xl border border-slate-200" @click.stop>
            <h2 class="text-lg font-black text-slate-900 uppercase tracking-tight mb-3">Données transmises</h2>
            <p class="text-sm text-slate-600 leading-relaxed mb-4">Les données que vous validez servent uniquement à la cellule de recrutement de cette communauté. Elles sont conservées le temps nécessaire à l’instruction de votre dossier, conformément aux usages du service.</p>
            <p class="text-sm text-slate-600 leading-relaxed mb-6">Vous pouvez retirer votre consentement avant envoi en fermant cette page ; après soumission, contactez le staff pour toute demande liée à vos données.</p>
            <button type="button" class="w-full py-3 bg-slate-900 text-white text-xs font-black uppercase tracking-widest rounded-xl" @click="showConsentModal = false">Compris</button>
        </div>
    </div>
</div>

<style>[x-cloak]{display:none!important}</style>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var form = document.querySelector('form[action*="enlistment"]');
    if (!form) return;
    form.addEventListener('submit', function () {
        var flowInput = form.querySelector('input[name="enlistment_flow"]');
        var flow = flowInput ? flowInput.value : 'guest';
        document.querySelectorAll('.guest-req').forEach(function (el) {
            el.required = (flow === 'guest');
        });
    });
});
</script>
