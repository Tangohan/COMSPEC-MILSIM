<?php
declare(strict_types=1);

$m = $interteamMission ?? [];
$sid = (int) ($m['id'] ?? 0);
$status = (string) ($m['status'] ?? '');
$consentDone = !empty($interteamConsentDone);
?>
<div class="max-w-4xl mx-auto px-6 py-10 space-y-8">
    <div>
        <a href="<?= htmlspecialchars(cooperation_mission_show_url($sid), ENT_QUOTES, 'UTF-8') ?>" class="text-sm font-medium text-slate-600 hover:text-slate-900 underline">← Synthèse</a>
        <?php require base_path('views/back_office/cooperation/missions/_nav.php'); ?>
        <h1 class="mt-4 text-2xl font-black text-slate-900">Espace commun</h1>
        <p class="mt-2 text-sm text-slate-600">Autorisation de partage et échanges entre les unités engagées.</p>
    </div>

    <?php $viewerConsent = is_array($cooperationViewerConsent ?? null) ? $cooperationViewerConsent : ['state' => $consentDone ? 'valid' : 'none', 'until' => null]; ?>
    <?php if ($viewerConsent['state'] !== 'valid' && $status === 'active'): ?>
    <div class="coop-consent-state coop-consent-state--expired flex flex-wrap items-center justify-between gap-3" role="alert">
        <p class="m-0">
            <?php if ($viewerConsent['state'] === 'expired'): ?>
            <strong>Votre autorisation de partage a expiré.</strong> Les données partagées restent masquées pour vous tant que vous ne l’avez pas renouvelée.
            <?php else: ?>
            <strong>Autorisation de partage à valider.</strong> Elle est nécessaire pour accéder aux données partagées de la coopération (code à six chiffres envoyé par e-mail).
            <?php endif; ?>
        </p>
        <a class="coop-progress__action-btn" href="<?= htmlspecialchars(cooperation_mission_consent_url($sid), ENT_QUOTES, 'UTF-8') ?>"><?= $viewerConsent['state'] === 'expired' ? 'Renouveler mon autorisation' : 'Valider mon autorisation' ?></a>
    </div>
    <?php elseif ($status === 'active' && !empty($viewerConsent['until'])): ?>
    <p class="text-xs text-slate-500">Votre autorisation de partage est valide jusqu’au <?= htmlspecialchars(date('d/m/Y à H:i', (int) strtotime((string) $viewerConsent['until'])), ENT_QUOTES, 'UTF-8') ?>.</p>
    <?php endif; ?>

    <?php if ($status === 'active'): ?>
    <section class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
        <h2 class="text-sm font-black uppercase tracking-wider text-slate-800">Échanger avec les unités engagées</h2>
        <p class="mt-2 text-sm text-slate-600">Les échanges passent par les points de situation et les réunions de la coopération. Votre autorisation de partage encadre les données visibles par les unités partenaires.</p>
        <a href="<?= htmlspecialchars(cooperation_mission_show_url($sid) . '#conduite', ENT_QUOTES, 'UTF-8') ?>" class="mt-4 inline-flex rounded-xl bg-sky-800 px-4 py-2 text-sm font-semibold text-white hover:bg-sky-900">Ouvrir la conduite de la coopération</a>
    </section>
    <?php else: ?>
    <?php
    $ui_empty_title = 'L’espace commun n’est pas encore ouvert';
    $ui_empty_description = 'Il s’ouvre au lancement de la coopération : chaque unité valide alors son autorisation de partage.';
    $ui_empty_primary_label = 'Voir ce qu’il reste à faire';
    $ui_empty_primary_href = cooperation_mission_show_url($sid);
    require base_path('views/partials/ui/empty_state.php');
    ?>
    <?php endif; ?>
</div>
