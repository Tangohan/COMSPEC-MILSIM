<?php
/** @var string $previewName */
/** @var string $previewSlug */
/** @var array<string, mixed> $communityPreview */
/** @var array<string, mixed> $milsimPackPreview */
/** @var string $registrationMode */
$c = $communityPreview ?? [];
$pack = $milsimPackPreview ?? [];
$labels = \App\Services\Community\TenantCommunityProfileService::badgeLabels();
$badges = [];
foreach (is_array($c['style_badges'] ?? null) ? $c['style_badges'] : [] as $slug) {
    if (is_string($slug) && isset($labels[$slug])) {
        $badges[] = $labels[$slug];
    }
}
?>
<div class="max-w-3xl mx-auto px-6 py-10">
    <p class="text-[11px] font-black uppercase tracking-[0.2em] text-slate-500 mb-2">Aperçu (brouillon)</p>
    <h1 class="text-2xl font-black text-slate-900 mb-2"><?= htmlspecialchars($previewName ?? '') ?></h1>
    <p class="text-sm text-slate-600 mb-6">Adresse courte proposée pour le lien public : <span class="rounded bg-slate-100 px-2 py-0.5 font-mono text-xs"><?= htmlspecialchars($previewSlug ?? '') ?></span></p>
    <p class="mb-6">
        <a href="<?= htmlspecialchars(url('communities/create'), ENT_QUOTES, 'UTF-8') ?>" class="text-sm font-bold text-emerald-700 hover:underline">← Retour à l’assistant</a>
    </p>

    <?php
    $bu = trim((string) ($c['public_banner_url'] ?? ''));
    if ($bu !== '' && filter_var($bu, FILTER_VALIDATE_URL)):
    ?>
    <div class="mb-6 rounded-2xl border border-slate-200 overflow-hidden aspect-[21/7] bg-slate-100">
        <img src="<?= htmlspecialchars($bu, ENT_QUOTES, 'UTF-8') ?>" alt="" class="w-full h-full object-cover">
    </div>
    <?php endif; ?>

    <div class="flex flex-wrap gap-2 mb-4">
        <?php foreach ($badges as $bl): ?>
            <span class="inline-flex items-center rounded-full border border-emerald-200 bg-emerald-50 px-3 py-1 text-[10px] font-black uppercase tracking-widest text-emerald-900"><?= htmlspecialchars($bl) ?></span>
        <?php endforeach; ?>
    </div>

    <?php if (trim((string) ($c['simple_body'] ?? '')) !== ''): ?>
        <div class="mb-6 rounded-2xl border border-emerald-200 bg-emerald-50/60 p-4">
            <p class="text-[11px] font-black uppercase tracking-[0.2em] text-emerald-800 mb-2">Bandeau d’accueil</p>
            <div class="text-sm text-slate-700 whitespace-pre-wrap"><?= htmlspecialchars((string) $c['simple_body']) ?></div>
        </div>
    <?php endif; ?>

    <?php if (trim((string) ($c['public_about_body'] ?? '')) !== ''): ?>
        <div class="prose prose-slate max-w-none mb-8">
            <h2 class="text-lg font-black text-slate-900 mb-2">Qui sommes-nous ?</h2>
            <div class="text-sm text-slate-700 whitespace-pre-wrap"><?= htmlspecialchars((string) $c['public_about_body']) ?></div>
        </div>
    <?php endif; ?>

    <?php if (trim((string) ($c['expectations'] ?? '')) !== ''): ?>
        <div class="mb-8 rounded-2xl border border-slate-200 bg-slate-50 p-4 text-sm text-slate-800">
            <p class="font-black text-xs uppercase tracking-wider text-slate-500 mb-2">Attentes / mot d’ordre</p>
            <div class="whitespace-pre-wrap"><?= htmlspecialchars((string) $c['expectations']) ?></div>
        </div>
    <?php endif; ?>

    <div class="rounded-2xl border border-slate-200 bg-white p-5 mb-8">
        <p class="text-sm font-black text-slate-900 mb-2">Formulaire de candidature (extrait)</p>
        <p class="text-xs text-slate-500 mb-4">Mode : <?= htmlspecialchars(\App\Services\Community\TenantCommunityProfileService::registrationModeLabel($registrationMode), ENT_QUOTES, 'UTF-8') ?></p>
        <?php if ($registrationMode === 'milsim' && !empty($pack['fields']) && is_array($pack['fields'])): ?>
            <ul class="text-sm text-slate-700 space-y-1 list-disc pl-5">
                <?php foreach ($pack['fields'] as $fk => $fv): ?>
                    <?php if (is_array($fv) && !empty($fv['label'])): ?>
                        <li><?= htmlspecialchars((string) $fv['label']) ?></li>
                    <?php endif; ?>
                <?php endforeach; ?>
            </ul>
        <?php elseif ($registrationMode === 'discord'): ?>
            <p class="text-sm text-slate-600">Fiche Discord : pseudo, coordonnées et questions personnalisées. Le lien d’invitation Discord s’affiche si renseigné après création.</p>
        <?php else: ?>
            <p class="text-sm text-slate-600">Champs réduits (mode simple).</p>
        <?php endif; ?>
    </div>

    <p class="text-xs text-slate-500">Ceci est une simulation à partir des champs saisis ; la communauté n’est pas encore créée.</p>
</div>
