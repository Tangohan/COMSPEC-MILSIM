<?php
$base = url('');
$success = \App\Core\Session::getFlash('success');
$error = \App\Core\Session::getFlash('error');
$ref = 'JTFO-' . str_pad((string) random_int(1, 999999), 6, '0', STR_PAD_LEFT);
$tenant = $tenant ?? [];
$communityConfig = $communityConfig ?? [];
$formAction = $formAction ?? url('enlistment');
$tenantName = trim((string) ($tenant['name'] ?? 'Athena'));
$requireAiAck = array_key_exists('require_ai_ack', $communityConfig) ? (bool) $communityConfig['require_ai_ack'] : true;
$milsimPack = $milsimPack ?? \App\Services\Community\EnlistmentMilsimPackService::defaultPack();
$p = $milsimPack;
$fld = static function (string $k) use ($p): array {
    if (is_array($p['fields'][$k] ?? null)) {
        return $p['fields'][$k];
    }
    return ['label' => $k, 'placeholder' => '', 'widget' => 'text', 'options' => []];
};
$enlistSlug = trim((string) ($tenant['slug'] ?? 'default'));
$enlistmentContext = $enlistmentContext ?? [];
$canUseAccount = !empty($enlistmentContext['canUseAccount']);
$prefill = array_merge([
    'full_name' => '', 'email' => '', 'callsign' => '', 'age' => '', 'timezone' => '', 'weekly_availability' => '',
], is_array($enlistmentContext['prefill'] ?? null) ? $enlistmentContext['prefill'] : []);
$recruitmentPresets = $enlistmentContext['recruitmentPresets'] ?? [];
$hasMembershipOnTarget = !empty($enlistmentContext['hasMembershipOnTarget']);
$switchToTargetUrl = $enlistmentContext['switchToTargetUrl'] ?? null;
$tenantSlugForForm = trim((string) ($tenant['slug'] ?? ''));
$selectedRecruitmentOpening = is_array($selectedRecruitmentOpening ?? null) ? $selectedRecruitmentOpening : null;
$analyticsBeacon = $analyticsBeacon ?? null;
$compactAccountOpening = $canUseAccount && $selectedRecruitmentOpening !== null;
$twCss = is_file(base_path('public/assets/css/tailwind.css')) ? url('assets/css/tailwind.css') : null;
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Enrôlement — <?= htmlspecialchars($tenantName) ?></title>
    <?php if ($twCss !== null): ?>
    <link href="<?= htmlspecialchars($twCss) ?>" rel="stylesheet">
    <?php else: ?>
    <script src="https://cdn.tailwindcss.com"></script>
    <?php endif; ?>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;700;900&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #f1f5f9; color: #0f172a; }
        .input-field { background: #fff; border: 1px solid #e2e8f0; border-radius: 0.75rem; padding: 0.8rem; width: 100%; outline: none; transition: all 0.3s; font-size: 0.8rem; }
        .input-field:focus { border-color: #0f172a; box-shadow: 0 0 0 2px rgba(15,23,42,0.05); }
        .section-title { font-size: 9px; font-weight: 900; letter-spacing: 0.3em; text-transform: uppercase; color: #94a3b8; margin-bottom: 2rem; display: flex; align-items: center; gap: 15px; }
        .section-title::after { content: ""; flex: 1; height: 1px; background: #e2e8f0; }
        @keyframes scan { 0% { transform: translateX(-100%); } 100% { transform: translateX(100%); } }
        .scan-line { animation: scan 4s linear infinite; opacity: 0.1; }
        form.enlist-compact-default:not(.enlist-compact-expanded) .enlist-full-only { display: none !important; }
    </style>
</head>
<body class="bg-slate-100 min-h-screen">

    <div id="preamble" class="fixed inset-0 z-[100] bg-slate-900 flex items-center justify-center p-6 transition-all duration-1000" data-skip-if-stored="1">
        <div class="max-w-2xl w-full">
            <div class="mb-12 flex items-center gap-4 border-b border-white/10 pb-6">
                <div class="w-12 h-12 bg-emerald-500 rounded-lg flex items-center justify-center font-black text-slate-900 text-xl"><?= htmlspecialchars(mb_substr((string) $p['logo_letter'], 0, 1) ?: 'F') ?></div>
                <div>
                    <h2 class="text-white text-xs font-black tracking-[0.4em] uppercase"><?= htmlspecialchars((string) $p['portal_title']) ?></h2>
                    <p class="text-white/40 text-[9px] font-mono uppercase"><?= htmlspecialchars((string) $p['portal_subtitle']) ?></p>
                </div>
            </div>
            <div class="space-y-8">
                <div class="space-y-4">
                    <h1 class="text-white text-4xl font-black tracking-tighter uppercase"><?= htmlspecialchars((string) $p['preamble_title']) ?></h1>
                    <p class="text-slate-400 text-sm leading-relaxed font-medium">
                        <?= htmlspecialchars((string) $p['preamble_lead']) ?>
                    </p>
                    <div class="bg-white/5 p-4 border-l-2 border-emerald-500 text-[11px] text-emerald-400/80 font-mono leading-relaxed">
                        <?php foreach (is_array($p['preamble_status_lines'] ?? null) ? $p['preamble_status_lines'] : [] as $line): ?>
                            <?php if (trim((string) $line) !== ''): ?>
                            <?= htmlspecialchars((string) $line) ?><br>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                </div>
                <button type="button" onclick="startApp()" class="group relative w-full overflow-hidden bg-white text-slate-900 py-5 rounded-xl font-black tracking-[0.45em] uppercase transition-all hover:bg-emerald-500 hover:text-white active:scale-95">
                    <span class="relative z-10"><?= htmlspecialchars((string) $p['preamble_cta']) ?></span>
                    <div class="absolute inset-0 bg-emerald-600 translate-y-full group-hover:translate-y-0 transition-transform duration-300"></div>
                </button>
                <p class="text-center text-[8px] text-white/20 tracking-widest uppercase italic"><?= htmlspecialchars((string) $p['preamble_footer']) ?></p>
            </div>
        </div>
    </div>

    <nav class="w-full bg-slate-900 text-white h-10 px-6 flex items-center justify-between sticky top-0 z-50">
        <div class="flex items-center gap-6">
            <span class="text-[9px] font-black tracking-[0.3em] text-emerald-400"><?= htmlspecialchars((string) $p['nav_brand']) ?></span>
            <div class="h-4 w-[1px] bg-white/10"></div>
            <a href="<?= $base ?>/" class="text-[8px] font-mono text-white/40 hover:text-white tracking-widest uppercase">Accueil</a>
        </div>
        <div class="flex items-center gap-8">
            <div id="clock" class="text-[9px] font-mono font-bold tracking-tighter">--:--:-- Z</div>
        </div>
    </nav>

    <main class="max-w-[1400px] mx-auto py-8 px-6 grid grid-cols-1 lg:grid-cols-12 gap-8">
        <aside class="lg:col-span-3 space-y-6">
            <div class="bg-white border border-slate-200 rounded-2xl p-6 shadow-sm">
                <p class="text-[8px] font-black text-slate-400 tracking-[0.3em] uppercase mb-4"><?= htmlspecialchars((string) $p['session_block_title']) ?></p>
                <div class="space-y-4">
                    <div class="flex justify-between items-center">
                        <span class="text-[10px] font-bold"><?= htmlspecialchars((string) $p['ref_label']) ?></span>
                        <span class="text-[10px] font-mono bg-slate-100 px-2 py-0.5 rounded"><?= htmlspecialchars($ref) ?></span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-[10px] font-bold">Sécurité</span>
                        <span class="text-[10px] text-emerald-600 font-bold"><?= htmlspecialchars((string) $p['security_label']) ?></span>
                    </div>
                    <div class="w-full bg-slate-100 h-1 rounded-full overflow-hidden mt-4">
                        <div class="bg-slate-900 h-full transition-all duration-300" id="progress-bar" style="width:0%"></div>
                    </div>
                    <p id="progress-text" class="text-[8px] text-slate-400 text-center font-bold" data-progress-prefix="<?= htmlspecialchars((string) $p['progress_prefix']) ?>"><?= htmlspecialchars((string) $p['progress_prefix']) ?> 0 / 20 RÉPONSES</p>
                </div>
            </div>
            <div class="bg-slate-900 rounded-2xl p-6 text-white shadow-xl">
                <p class="text-[8px] font-black text-white/30 tracking-[0.3em] uppercase mb-4"><?= htmlspecialchars((string) $p['roe_title']) ?></p>
                <ul class="space-y-4">
                    <?php
                    $roes = is_array($p['roe_items'] ?? null) ? $p['roe_items'] : [];
                    $ri = 0;
                    foreach ($roes as $rule):
                        $ri++;
                        if (! is_string($rule) || trim($rule) === '') {
                            continue;
                        }
                        ?>
                    <li class="flex gap-3"><span class="text-emerald-400 font-mono text-[10px]"><?= str_pad((string) $ri, 2, '0', STR_PAD_LEFT) ?></span><p class="text-[10px] leading-relaxed text-white/70"><?= htmlspecialchars($rule) ?></p></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </aside>

        <div class="lg:col-span-9">
            <?php if ($success): ?>
            <div class="mb-6 p-4 bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-xl text-sm font-medium"><?= htmlspecialchars($success) ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
            <div class="mb-6 p-4 bg-red-50 border border-red-200 text-red-800 rounded-xl text-sm font-medium"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <div class="bg-white border-x border-t border-slate-200 p-8 rounded-t-3xl border-b-2 border-slate-900">
                <div class="flex justify-between items-end mb-8 gap-6 flex-wrap">
                    <div>
                        <span class="text-[8px] font-black tracking-[0.5em] text-slate-400 uppercase"><?= htmlspecialchars((string) $p['doc_control']) ?></span>
                        <h1 class="text-2xl font-black tracking-tighter uppercase leading-none"><?= htmlspecialchars((string) $p['candidate_prefix']) ?> <?= htmlspecialchars($tenantName) ?></h1>
                        <div class="flex items-center gap-4 text-[9px] font-bold tracking-widest uppercase text-slate-400 mt-3">
                            <span class="flex items-center gap-2">
                                <span class="w-2 h-2 bg-emerald-500 rounded-full animate-pulse"></span>
                                <?= htmlspecialchars((string) $p['queue_label']) ?>
                            </span>
                            <span><?= htmlspecialchars((string) $p['ref_label']) ?>: <?= htmlspecialchars($ref) ?></span>
                        </div>
                    </div>
                    <span class="text-[14px] font-black tracking-[0.2em] uppercase px-4 py-1 border-2 border-slate-900"><?= htmlspecialchars((string) $p['classified_badge']) ?></span>
                </div>
            </div>

            <div class="bg-white border-x border-b border-slate-200 shadow-2xl rounded-b-3xl relative overflow-hidden">
                <div class="w-full h-[2px] bg-slate-100 overflow-hidden relative">
                    <div class="absolute inset-0 bg-slate-900 w-1/2 scan-line"></div>
                </div>
                <div class="absolute inset-0 flex items-center justify-center pointer-events-none opacity-[0.02] select-none rotate-12">
                    <span class="text-[120px] font-black"><?= htmlspecialchars((string) $p['watermark']) ?></span>
                </div>
                <div class="p-8 border-b border-slate-100 bg-slate-50/70 relative z-10">
                    <div class="grid md:grid-cols-2 gap-8 text-[11px] leading-relaxed">
                        <div class="space-y-3">
                            <p class="font-black uppercase tracking-[0.25em] text-slate-400 text-[9px]"><?= htmlspecialchars((string) $p['op_note_title']) ?></p>
                            <p class="text-slate-600"><?= htmlspecialchars((string) $p['op_col1']) ?></p>
                            <p class="text-red-600 font-black uppercase text-[10px] tracking-wide"><?= htmlspecialchars((string) $p['op_ai_warning']) ?></p>
                        </div>
                        <div class="space-y-3 md:border-l border-slate-200 md:pl-8">
                            <p class="text-slate-600"><?= htmlspecialchars((string) $p['op_col2']) ?></p>
                        </div>
                    </div>
                </div>

                <form method="post" action="<?= htmlspecialchars($formAction) ?>" class="p-8 md:p-12 space-y-12 relative z-10<?= $compactAccountOpening ? ' enlist-compact-default' : '' ?>" id="recruitment-form" data-can-use-account="<?= $canUseAccount ? '1' : '0' ?>" data-compact-opening="<?= $compactAccountOpening ? '1' : '0' ?>">
                    <?= \App\Core\Csrf::field() ?>
                    <?php if ($tenantSlugForForm !== ''): ?>
                        <input type="hidden" name="enlistment_tenant_slug" value="<?= htmlspecialchars($tenantSlugForForm) ?>">
                    <?php endif; ?>
                    <input type="hidden" name="enlistment_form_mode" id="enlistment_form_mode" value="<?= $compactAccountOpening ? 'compact' : 'full' ?>">
                    <?php if ($selectedRecruitmentOpening !== null && !empty($selectedRecruitmentOpening['id'])): ?>
                        <input type="hidden" name="enlistment_opening_id" value="<?= (int) $selectedRecruitmentOpening['id'] ?>">
                        <div class="mb-8 rounded-xl border border-sky-200 bg-sky-50 p-5">
                            <p class="text-[10px] font-black uppercase tracking-widest text-sky-800">Candidature ciblée</p>
                            <p class="mt-2 text-sm font-bold text-slate-900">Vous postulez pour : <?= htmlspecialchars((string) ($selectedRecruitmentOpening['title'] ?? ''), ENT_QUOTES, 'UTF-8') ?></p>
                            <p class="mt-1 text-xs text-sky-900/80 leading-relaxed">Votre dossier sera rattaché à cet avis pour le suivi côté équipe RH.</p>
                        </div>
                    <?php endif; ?>
                    <div class="p-6 bg-slate-50 border-l-4 border-slate-900 mb-10">
                        <p class="text-[11px] leading-relaxed text-slate-600 font-medium">
                            <span class="text-slate-900 font-black uppercase">Note :</span>
                            <?= htmlspecialchars((string) $p['archive_note']) ?>
                        </p>
                    </div>

                    <?php if ($hasMembershipOnTarget && $switchToTargetUrl): ?>
                        <div class="p-4 mb-8 rounded-xl bg-amber-50 border border-amber-200 text-[11px] text-amber-950">
                            <p class="font-black uppercase tracking-wider mb-1">Compte sur cette communauté</p>
                            <p class="leading-relaxed">Basculer le contexte Athena pour préremplir avec votre compte.</p>
                            <a href="<?= htmlspecialchars($switchToTargetUrl) ?>" class="mt-2 inline-block text-[10px] font-black uppercase tracking-widest underline">Basculer et continuer</a>
                        </div>
                    <?php endif; ?>

                    <section>
                        <div class="section-title"><?= htmlspecialchars((string) ($p['section_0'] ?? 'Mode de candidature')) ?></div>
                        <?php if ($canUseAccount): ?>
                            <div class="flex flex-col sm:flex-row gap-3 mb-6">
                                <button type="button" id="enlist-btn-flow-account" class="enlist-flow-btn flex-1 py-3 px-4 rounded-xl border-2 border-slate-900 bg-slate-900 text-white text-[10px] font-black uppercase tracking-widest">Compte Athena</button>
                                <button type="button" id="enlist-btn-flow-guest" class="enlist-flow-btn flex-1 py-3 px-4 rounded-xl border-2 border-slate-200 text-slate-600 text-[10px] font-black uppercase tracking-widest">Invité (RP ou identité réelle)</button>
                            </div>
                            <input type="hidden" name="enlistment_flow" id="enlistment_flow" value="account">
                        <?php else: ?>
                            <input type="hidden" name="enlistment_flow" id="enlistment_flow" value="guest">
                            <p class="text-[11px] text-slate-500 mb-4 leading-relaxed">Choisissez plus bas si le dossier est porté par un <strong>personnage RP</strong> ou par votre <strong>identité réelle</strong> (contact administratif).</p>
                        <?php endif; ?>

                        <?php if ($canUseAccount): ?>
                            <div id="enlist-account-panel" class="space-y-5 p-5 rounded-2xl bg-emerald-50/80 border border-emerald-200 mb-6">
                                <p class="text-[10px] font-black uppercase tracking-widest text-emerald-800">Envoi avec le compte connecté</p>
                                <div class="rounded-xl border border-emerald-100 bg-white/80 p-4 text-[11px] text-slate-700 space-y-2">
                                    <label class="flex items-center gap-2 cursor-pointer">
                                        <input type="checkbox" name="share_email" value="1" checked class="rounded border-slate-300">
                                        <span>Partager mon <strong>email</strong> de connexion</span>
                                    </label>
                                    <label class="flex items-center gap-2 cursor-pointer">
                                        <input type="checkbox" name="share_name" value="1" checked class="rounded border-slate-300">
                                        <span>Partager mon <strong>nom</strong> (profil)</span>
                                    </label>
                                    <label class="flex items-center gap-2 cursor-pointer">
                                        <input type="checkbox" name="share_callsign" value="1" class="rounded border-slate-300">
                                        <span>Partager mon <strong>indicatif</strong> du profil</span>
                                    </label>
                                </div>
                                <?php if (!empty($recruitmentPresets)): ?>
                                    <div>
                                        <label class="text-[10px] font-black uppercase tracking-wider text-slate-500">Profil enregistré (optionnel)</label>
                                        <select name="recruitment_preset_id" class="input-field mt-1 bg-white" id="recruitment_preset_select">
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
                                    </div>
                                    <?php
                                    $rpShareLabels = [
                                        'character_name' => 'Nom du personnage',
                                        'bio' => 'Biographie',
                                        'cv' => 'Parcours (CV)',
                                        'image_url' => 'Portrait enregistré (fichier)',
                                        'image_external_url' => 'Lien vers un portrait',
                                        'admin_notes' => 'Notes personnelles du profil',
                                        'availability' => 'Synthèse des disponibilités',
                                    ];
                                    ?>
                                    <div id="enlist-rp-share-panel" class="hidden rounded-xl border border-emerald-100 bg-white/90 p-4 space-y-3 text-[11px] text-slate-700">
                                        <p class="text-[10px] font-black uppercase tracking-wider text-emerald-800">Contenu du profil transmis au recrutement</p>
                                        <p class="text-slate-600 leading-relaxed">Ne cochez que ce que vous acceptez d’ajouter à ce dossier. Le reste reste sur votre compte et n’est pas copié ici.</p>
                                        <?php foreach ($rpShareLabels as $shareKey => $shareLabel): ?>
                                            <label class="flex items-center gap-2 cursor-pointer">
                                                <input type="checkbox" name="share_rp_<?= htmlspecialchars($shareKey) ?>" value="1" checked disabled class="rounded border-slate-300 enlist-rp-share-cb">
                                                <span><?= htmlspecialchars($shareLabel) ?></span>
                                            </label>
                                        <?php endforeach; ?>
                                        <label class="flex items-start gap-2 cursor-pointer pt-2 border-t border-emerald-100">
                                            <input type="checkbox" name="include_milsim_from_preset" value="1" checked disabled class="mt-1 rounded border-slate-300 enlist-include-milsim-cb">
                                            <span>Inclure aussi les <strong>réponses techniques</strong> enregistrées dans ce modèle (matériel, créneaux, motivation enregistrée dans le profil, etc.).</span>
                                        </label>
                                    </div>
                                <?php endif; ?>
                                <div class="rounded-xl bg-slate-900 text-white p-4">
                                    <label class="flex items-start gap-3 cursor-pointer">
                                        <input type="checkbox" name="consent_data_sharing" value="1" class="mt-1 rounded border-white/30" id="consent_data_sharing">
                                        <span class="text-[11px] leading-relaxed">J’accepte que les informations cochées et le contenu de ce dossier soient transmis au staff de <strong><?= htmlspecialchars($tenantName) ?></strong>.</span>
                                    </label>
                                </div>
                                <p class="text-[10px] text-slate-500">Les champs « administratif » ci-dessous complètent votre profil pour l’état-major.</p>
                            </div>
                        <?php endif; ?>

                        <?php if ($compactAccountOpening): ?>
                            <div id="enlist-compact-toolbar" class="rounded-xl border border-sky-200 bg-sky-50/80 p-5 space-y-3 mb-6">
                                <p class="text-[10px] font-black uppercase tracking-widest text-sky-900">Parcours court</p>
                                <p class="text-[11px] text-sky-950/90 leading-relaxed">Seuls les éléments utiles à une candidature ciblée sont affichés. Vous pouvez à tout moment compléter le dossier comme pour une première inscription.</p>
                                <button type="button" id="enlist-btn-expand-full" class="inline-flex items-center justify-center rounded-xl border-2 border-sky-800 bg-white px-4 py-2.5 text-[10px] font-black uppercase tracking-widest text-sky-900 hover:bg-sky-100 transition-colors">
                                    Fournir le questionnaire complet
                                </button>
                            </div>
                        <?php endif; ?>

                        <div id="enlist-guest-identity" class="space-y-4 mb-2 <?= $canUseAccount ? 'hidden' : '' ?>" <?= $canUseAccount ? 'style="display:none"' : '' ?>>
                            <p class="text-[10px] font-black uppercase tracking-widest text-slate-400">Identité portée par la candidature</p>
                            <div class="flex flex-col sm:flex-row gap-4">
                                <label class="flex items-center gap-2 text-[11px] text-slate-700 cursor-pointer">
                                    <input type="radio" name="identity_kind" value="admin" class="rounded border-slate-300" checked>
                                    <span>Identité réelle (dossier administratif)</span>
                                </label>
                                <label class="flex items-center gap-2 text-[11px] text-slate-700 cursor-pointer">
                                    <input type="radio" name="identity_kind" value="rp" class="rounded border-slate-300">
                                    <span>Personnage roleplay (in-universe)</span>
                                </label>
                            </div>
                        </div>
                    </section>

                    <section>
                        <div class="section-title"><?= htmlspecialchars((string) $p['section_1']) ?></div>
                        <div id="enlist-guest-names" class="grid md:grid-cols-2 gap-6 mb-6 <?= $canUseAccount ? 'hidden' : '' ?>" <?= $canUseAccount ? 'style="display:none"' : '' ?>>
                            <div class="space-y-2 md:col-span-2">
                                <label id="label-full-name" class="text-[10px] font-black tracking-wider uppercase"><?= htmlspecialchars($fld('full_name')['label']) ?></label>
                                <input type="text" name="full_name" id="input-full-name" class="input-field track-field guest-req-field" placeholder="<?= htmlspecialchars($fld('full_name')['placeholder']) ?>"
                                    value="<?= htmlspecialchars($prefill['full_name']) ?>"
                                    autocomplete="name">
                            </div>
                            <div id="legal-full-row" class="space-y-2 md:col-span-2 hidden">
                                <label class="text-[10px] font-black tracking-wider uppercase"><?= htmlspecialchars($fld('legal_full_name')['label']) ?></label>
                                <input type="text" name="legal_full_name" class="input-field track-field" placeholder="<?= htmlspecialchars($fld('legal_full_name')['placeholder']) ?>" autocomplete="name">
                            </div>
                            <div class="space-y-2 md:col-span-2">
                                <label class="text-[10px] font-black tracking-wider uppercase"><?= htmlspecialchars($fld('email')['label']) ?></label>
                                <input type="email" name="email" id="input-email" class="input-field track-field guest-req-field" placeholder="<?= htmlspecialchars($fld('email')['placeholder']) ?>"
                                    value="<?= htmlspecialchars($prefill['email']) ?>"
                                    autocomplete="email">
                            </div>
                        </div>
                        <div class="enlist-full-only grid md:grid-cols-2 gap-6">
                            <div class="space-y-2">
                                <label class="text-[10px] font-black tracking-wider uppercase"><?= htmlspecialchars($fld('age')['label']) ?></label>
                                <input type="number" name="age" class="input-field track-field" placeholder="<?= htmlspecialchars($fld('age')['placeholder']) ?>" min="16" max="99"
                                    value="<?= htmlspecialchars($prefill['age']) ?>">
                            </div>
                            <div class="space-y-2">
                                <label class="text-[10px] font-black tracking-wider uppercase"><?= htmlspecialchars($fld('timezone')['label']) ?></label>
                                <input type="text" name="timezone" class="input-field track-field" placeholder="<?= htmlspecialchars($fld('timezone')['placeholder']) ?>"
                                    value="<?= htmlspecialchars($prefill['timezone']) ?>">
                            </div>
                            <div class="space-y-2 md:col-span-2">
                                <label class="text-[10px] font-black tracking-wider uppercase"><?= htmlspecialchars($fld('weekly_availability')['label']) ?></label>
                                <input type="text" name="weekly_availability" class="input-field track-field" placeholder="<?= htmlspecialchars($fld('weekly_availability')['placeholder']) ?>"
                                    value="<?= htmlspecialchars($prefill['weekly_availability']) ?>">
                            </div>
                            <div class="space-y-2 md:col-span-2">
                                <label class="text-[10px] font-black tracking-wider uppercase"><?= htmlspecialchars($fld('callsign')['label']) ?></label>
                                <input type="text" name="callsign" class="input-field track-field" placeholder="<?= htmlspecialchars($fld('callsign')['placeholder']) ?>"
                                    value="<?= htmlspecialchars($prefill['callsign']) ?>">
                            </div>
                        </div>
                    </section>

                    <section class="enlist-full-only">
                        <div class="section-title"><?= htmlspecialchars((string) $p['section_2']) ?></div>
                        <div class="space-y-6">
                            <?php $fieldName = 'system_config'; include base_path('views/partials/enlistment_milsim_widget.php'); ?>
                            <div class="grid md:grid-cols-2 gap-6">
                                <?php $fieldName = 'microphone_quality'; include base_path('views/partials/enlistment_milsim_widget.php'); ?>
                                <?php $fieldName = 'ace_acre_level'; include base_path('views/partials/enlistment_milsim_widget.php'); ?>
                            </div>
                            <?php $fieldName = 'past_milsim_experience'; include base_path('views/partials/enlistment_milsim_widget.php'); ?>
                        </div>
                    </section>

                    <section>
                        <div class="section-title"><?= htmlspecialchars((string) $p['section_3']) ?></div>
                        <div class="space-y-6">
                            <?php $fieldName = 'motivation_why_join'; include base_path('views/partials/enlistment_milsim_widget.php'); ?>
                            <div class="enlist-full-only">
                                <?php $fieldName = 'motivation_accountability'; include base_path('views/partials/enlistment_milsim_widget.php'); ?>
                            </div>
                        </div>
                    </section>

                    <section class="enlist-full-only bg-slate-50 p-6 rounded-2xl space-y-6 border border-slate-100">
                        <div class="section-title"><?= htmlspecialchars((string) $p['section_4']) ?></div>
                        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                            <span class="text-[11px] font-medium"><?= htmlspecialchars((string) $p['commitment_q13']) ?></span>
                            <select name="commitment_effort" class="input-field w-full md:w-40 track-field">
                                <option value="">Sélectionner</option>
                                <option value="Oui">Oui</option>
                                <option value="Non">Non</option>
                            </select>
                        </div>
                        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                            <span class="text-[11px] font-medium"><?= htmlspecialchars((string) $p['availability_q15']) ?></span>
                            <select name="availability_wed_sat" class="input-field w-full md:w-40 track-field">
                                <option value="">Sélectionner</option>
                                <option value="Oui">Oui</option>
                                <option value="Non">Non</option>
                                <option value="Variable">Variable</option>
                            </select>
                        </div>
                    </section>

                    <div class="pt-10 border-t border-slate-100">
                        <?php if ($requireAiAck): ?>
                            <div class="flex items-center gap-4 mb-8">
                                <input type="checkbox" name="no_ai_confirmed" id="no-ai-check" value="1" class="w-5 h-5 rounded border-slate-300 accent-slate-900 track-field">
                                <label for="no-ai-check" class="text-[10px] font-black tracking-widest uppercase text-slate-500 cursor-pointer"><?= htmlspecialchars((string) $p['ai_checkbox']) ?></label>
                            </div>
                        <?php else: ?>
                            <input type="hidden" name="no_ai_confirmed" value="1">
                        <?php endif; ?>
                        <button type="submit" class="w-full bg-slate-900 text-white p-6 rounded-2xl font-black tracking-[0.5em] uppercase hover:bg-emerald-600 transition-all duration-500 shadow-xl active:scale-[0.98]"><?= htmlspecialchars((string) $p['submit_button']) ?></button>
                        <p class="text-[8px] text-center mt-4 text-slate-400 tracking-widest uppercase italic"><?= htmlspecialchars((string) $p['submit_footer']) ?></p>
                    </div>
                </form>
            </div>
        </div>
    </main>

    <script>
        var ENLIST_PREAMBLE_KEY = <?= json_encode('athena_enlist_preamble_' . $enlistSlug, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
        var ENLIST_PREAMBLE_LABEL = <?= json_encode((string) $p['preamble_title'], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
        function updateClock() {
            var t = new Date().toISOString().split('T')[1].split('.')[0] + ' Z';
            var el = document.getElementById('clock');
            if (el) el.textContent = t;
        }
        function updateProgress() {
            var fields = document.querySelectorAll('.track-field');
            var completed = 0;
            fields.forEach(function(field) {
                if (field.type === 'checkbox') { if (field.checked) completed++; }
                else if (field.value && field.value.trim() !== '') completed++;
            });
            var total = fields.length;
            var percent = total ? Math.round((completed / total) * 100) : 0;
            var bar = document.getElementById('progress-bar');
            var text = document.getElementById('progress-text');
            var prefix = 'FORMULAIRE :';
            if (text && text.getAttribute('data-progress-prefix')) {
                prefix = text.getAttribute('data-progress-prefix');
            }
            if (bar) bar.style.width = percent + '%';
            if (text) text.textContent = prefix + ' ' + completed + ' / ' + total + ' RÉPONSES';
        }
        function startApp() {
            try {
                localStorage.setItem(ENLIST_PREAMBLE_KEY, JSON.stringify({
                    label: ENLIST_PREAMBLE_LABEL,
                    accepted: true,
                    at: new Date().toISOString()
                }));
            } catch (e) {}
            var p = document.getElementById('preamble');
            if (p) { p.style.opacity = '0'; p.style.pointerEvents = 'none'; setTimeout(function() { p.classList.add('hidden'); }, 1000); }
        }
        (function checkStoredAccess() {
            try {
                var raw = localStorage.getItem(ENLIST_PREAMBLE_KEY);
                if (raw) {
                    var data = JSON.parse(raw);
                    if (data && data.accepted === true) {
                        var p = document.getElementById('preamble');
                        if (p && p.getAttribute('data-skip-if-stored') === '1') {
                            p.classList.add('hidden');
                            p.style.opacity = '0';
                            p.style.pointerEvents = 'none';
                        }
                    }
                }
            } catch (e) {}
        })();
        setInterval(updateClock, 1000);
        updateClock();
        document.querySelectorAll('.track-field').forEach(function(f) {
            f.addEventListener('input', updateProgress);
            f.addEventListener('change', updateProgress);
        });
        updateProgress();

        (function enlistmentFlowUi() {
            var form = document.getElementById('recruitment-form');
            if (!form) return;
            var canUseAccount = form.getAttribute('data-can-use-account') === '1';
            var compactOpening = form.getAttribute('data-compact-opening') === '1';
            var flowInput = document.getElementById('enlistment_flow');
            var modeInput = document.getElementById('enlistment_form_mode');
            var accPanel = document.getElementById('enlist-account-panel');
            var guestId = document.getElementById('enlist-guest-identity');
            var guestNames = document.getElementById('enlist-guest-names');
            var btnAcc = document.getElementById('enlist-btn-flow-account');
            var btnGuest = document.getElementById('enlist-btn-flow-guest');
            var btnExpand = document.getElementById('enlist-btn-expand-full');
            var legalRow = document.getElementById('legal-full-row');
            var labelFull = document.getElementById('label-full-name');
            var LABEL_ADMIN = <?= json_encode($fld('full_name')['label'], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
            var LABEL_RP = 'Nom du personnage (RP)';

            function setGuestFieldsRequired(isGuest) {
                document.querySelectorAll('.guest-req-field').forEach(function(el) {
                    el.required = !!isGuest;
                });
            }

            function syncMotivationRequired() {
                var ta = document.querySelector('textarea[name="motivation_why_join"]');
                if (!ta) return;
                var flow = flowInput ? flowInput.value : 'guest';
                var compact = form.classList.contains('enlist-compact-default') && !form.classList.contains('enlist-compact-expanded');
                ta.required = (flow === 'account' && compact);
            }

            function syncRpSharePanel() {
                var sel = document.getElementById('recruitment_preset_select');
                var panel = document.getElementById('enlist-rp-share-panel');
                if (!panel) return;
                var guest = flowInput && flowInput.value === 'guest';
                var picked = !guest && sel && sel.value && String(sel.value).length > 0;
                panel.classList.toggle('hidden', !picked);
                panel.querySelectorAll('input').forEach(function(inp) {
                    inp.disabled = !picked;
                });
            }

            function syncIdentityKind() {
                var rp = false;
                document.querySelectorAll('input[name="identity_kind"]').forEach(function(r) {
                    if (r.checked) rp = (r.value === 'rp');
                });
                if (legalRow) {
                    legalRow.classList.toggle('hidden', !rp);
                }
                if (labelFull) {
                    labelFull.textContent = rp ? LABEL_RP : LABEL_ADMIN;
                }
            }

            function setFlow(f) {
                if (!flowInput) return;
                flowInput.value = f;
                var guest = (f === 'guest');
                if (canUseAccount) {
                    if (accPanel) accPanel.style.display = guest ? 'none' : '';
                    if (guestId) { guestId.style.display = guest ? '' : 'none'; guestId.classList.toggle('hidden', !guest); }
                    if (guestNames) { guestNames.style.display = guest ? '' : 'none'; guestNames.classList.toggle('hidden', !guest); }
                    if (btnAcc && btnGuest) {
                        if (guest) {
                            btnAcc.classList.remove('border-slate-900', 'bg-slate-900', 'text-white');
                            btnAcc.classList.add('border-slate-200', 'text-slate-600');
                            btnGuest.classList.add('border-slate-900', 'bg-slate-900', 'text-white');
                            btnGuest.classList.remove('border-slate-200', 'text-slate-600');
                        } else {
                            btnGuest.classList.remove('border-slate-900', 'bg-slate-900', 'text-white');
                            btnGuest.classList.add('border-slate-200', 'text-slate-600');
                            btnAcc.classList.add('border-slate-900', 'bg-slate-900', 'text-white');
                            btnAcc.classList.remove('border-slate-200', 'text-slate-600');
                        }
                    }
                    document.querySelectorAll('#enlist-account-panel input, #enlist-account-panel select').forEach(function(el) {
                        if (el.type === 'button') return;
                        el.disabled = guest;
                    });
                    document.querySelectorAll('input[name="identity_kind"]').forEach(function(r) {
                        r.disabled = !guest;
                    });
                }
                if (guest) {
                    form.classList.add('enlist-compact-expanded');
                    if (modeInput) modeInput.value = 'full';
                } else if (compactOpening) {
                    form.classList.remove('enlist-compact-expanded');
                    if (modeInput) modeInput.value = 'compact';
                } else {
                    form.classList.remove('enlist-compact-expanded');
                    if (modeInput) modeInput.value = 'full';
                }
                setGuestFieldsRequired(guest || !canUseAccount);
                syncRpSharePanel();
                syncMotivationRequired();
            }

            if (canUseAccount && btnAcc && btnGuest) {
                btnAcc.addEventListener('click', function() { setFlow('account'); });
                btnGuest.addEventListener('click', function() { setFlow('guest'); });
                setFlow(flowInput && flowInput.value === 'guest' ? 'guest' : 'account');
            } else {
                setGuestFieldsRequired(true);
                syncRpSharePanel();
                syncMotivationRequired();
            }

            if (btnExpand) {
                btnExpand.addEventListener('click', function() {
                    form.classList.add('enlist-compact-expanded');
                    if (modeInput) modeInput.value = 'full';
                    syncMotivationRequired();
                    try {
                        var s = document.querySelector('section .section-title');
                        if (s) s.scrollIntoView({ behavior: 'smooth', block: 'start' });
                    } catch (e) {}
                });
            }

            document.querySelectorAll('input[name="identity_kind"]').forEach(function(r) {
                r.addEventListener('change', syncIdentityKind);
            });
            syncIdentityKind();

            var presetSel = document.getElementById('recruitment_preset_select');
            if (presetSel) {
                presetSel.addEventListener('change', function(ev) {
                    syncRpSharePanel();
                    var opt = ev.target.selectedOptions[0];
                    var raw = opt && opt.getAttribute('data-payload');
                    if (!raw) return;
                    try {
                        var payload = JSON.parse(raw);
                        var mo = document.querySelector('textarea[name="motivation_why_join"]');
                        if (mo && payload.motivation_why_join) mo.value = payload.motivation_why_join;
                        var cs = document.querySelector('input[name="callsign"]');
                        if (cs && payload.callsign) cs.value = payload.callsign;
                        var av = document.querySelector('input[name="weekly_availability"]');
                        if (av && payload.availability) av.value = payload.availability;
                    } catch (e) {}
                });
            }

            form.addEventListener('submit', function(ev) {
                var flow = flowInput ? flowInput.value : 'guest';
                if (flow === 'guest') {
                    setGuestFieldsRequired(true);
                } else {
                    setGuestFieldsRequired(false);
                }
                syncMotivationRequired();
                if (canUseAccount && flow === 'account') {
                    var c = document.getElementById('consent_data_sharing');
                    if (c && !c.checked) {
                        ev.preventDefault();
                        alert('Veuillez accepter le partage des données avec le staff de recrutement.');
                    }
                }
            });
        })();
    </script>
    <?php require base_path('views/partials/analytics_beacon.php'); ?>
</body>
</html>
