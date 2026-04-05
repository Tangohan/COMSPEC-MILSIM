<?php
/** @var array<string,mixed> $enlistment */
$e = $enlistment ?? [];
$rpSnap = is_array($e['recruitment_rp_json'] ?? null) ? $e['recruitment_rp_json'] : null;
$id = (int) ($e['id'] ?? 0);
?>
<div class="max-w-4xl mx-auto px-6 py-12">
    <div class="flex flex-wrap items-center justify-between gap-4 mb-8">
        <h1 class="text-2xl font-black text-slate-900">Candidature #<?= $id ?></h1>
        <a href="<?= url('back-office/recruitments') ?>" class="text-sm text-slate-600 hover:text-slate-900 underline">← Liste</a>
    </div>

    <div class="bg-white border border-slate-200 rounded-2xl p-6 shadow-sm space-y-4 mb-6">
        <h2 class="text-sm font-black uppercase tracking-widest text-slate-900">Identité</h2>
        <dl class="grid sm:grid-cols-2 gap-3 text-sm">
            <dt class="text-slate-500">Date</dt>
            <dd><?= !empty($e['created_at']) ? htmlspecialchars(date('d/m/Y H:i', strtotime((string) $e['created_at']))) : '—' ?></dd>
            <dt class="text-slate-500">Nom</dt>
            <dd><?= htmlspecialchars(trim(($e['first_name'] ?? '') . ' ' . ($e['last_name'] ?? '')) ?: '—') ?></dd>
            <dt class="text-slate-500">Email</dt>
            <dd><?= htmlspecialchars((string) ($e['email'] ?? '—')) ?></dd>
            <dt class="text-slate-500">Indicatif</dt>
            <dd><?= htmlspecialchars((string) ($e['callsign'] ?? '—')) ?></dd>
            <dt class="text-slate-500">Statut</dt>
            <dd><span class="inline-flex px-2 py-0.5 rounded text-xs font-medium bg-slate-100"><?= htmlspecialchars((string) ($e['status'] ?? '—')) ?></span></dd>
            <dt class="text-slate-500">Compte / soumission</dt>
            <dd>
                <?php if (!empty($e['submitter_user_id'])): ?>
                    <a href="<?= url('personnel/' . (int) $e['submitter_user_id']) ?>" class="text-sky-700 underline font-medium">Utilisateur #<?= (int) $e['submitter_user_id'] ?></a>
                    <span class="text-xs text-slate-500 block"><?= htmlspecialchars((string) ($e['submitted_via'] ?? '')) ?></span>
                <?php else: ?>
                    —
                <?php endif; ?>
            </dd>
            <?php if (!empty($e['recruitment_preset_id'])): ?>
                <dt class="text-slate-500">Profil preset</dt>
                <dd>#<?= (int) $e['recruitment_preset_id'] ?></dd>
            <?php endif; ?>
        </dl>
    </div>

    <?php
    $olympus = [
        'age' => 'Âge',
        'timezone' => 'Fuseau',
        'weekly_availability' => 'Disponibilités hebdo',
        'system_config' => 'Config PC',
        'microphone_quality' => 'Microphone',
        'past_milsim_experience' => 'Exp. MilSim',
        'ace_acre_level' => 'ACE / ACRE',
        'motivation_why_join' => 'Motivation',
        'motivation_accountability' => 'Accountability',
        'commitment_effort' => 'Engagement',
        'availability_wed_sat' => 'Mer. / sam. soir',
        'availability' => 'Disponibilité (résumé)',
    ];
    $hasOlympus = false;
    foreach ($olympus as $k => $_) {
        if (isset($e[$k]) && $e[$k] !== '' && $e[$k] !== null) {
            $hasOlympus = true;
            break;
        }
    }
    ?>
    <?php if ($hasOlympus): ?>
    <div class="bg-white border border-slate-200 rounded-2xl p-6 shadow-sm space-y-3 mb-6">
        <h2 class="text-sm font-black uppercase tracking-widest text-slate-900">Dossier MilSim</h2>
        <dl class="space-y-3 text-sm">
            <?php foreach ($olympus as $col => $label): ?>
                <?php if (isset($e[$col]) && $e[$col] !== '' && $e[$col] !== null): ?>
                    <div>
                        <dt class="text-xs font-bold text-slate-500"><?= $label ?></dt>
                        <dd class="mt-1 text-slate-800 whitespace-pre-wrap"><?= htmlspecialchars((string) $e[$col]) ?></dd>
                    </div>
                <?php endif; ?>
            <?php endforeach; ?>
        </dl>
    </div>
    <?php endif; ?>

    <?php if (!empty($e['notes'])): ?>
    <div class="bg-slate-50 border border-slate-200 rounded-2xl p-6 mb-6">
        <h2 class="text-sm font-black uppercase tracking-widest text-slate-900 mb-2">Notes (fusion dossier)</h2>
        <pre class="text-sm text-slate-800 whitespace-pre-wrap font-sans"><?= htmlspecialchars((string) $e['notes']) ?></pre>
    </div>
    <?php endif; ?>

    <?php if ($rpSnap): ?>
    <div class="bg-emerald-50/80 border border-emerald-200 rounded-2xl p-6 space-y-4">
        <h2 class="text-sm font-black uppercase tracking-widest text-emerald-950">Snapshot RP (au dépôt)</h2>
        <?php
        $img = trim((string) ($rpSnap['image_url'] ?? ''));
        $imgExt = trim((string) ($rpSnap['image_external_url'] ?? ''));
        ?>
        <?php if ($img !== ''): ?>
            <div>
                <p class="text-xs font-bold text-emerald-900 mb-1">Portrait (fichier)</p>
                <img src="<?= htmlspecialchars(url($img)) ?>" alt="" class="max-h-48 rounded-xl border border-emerald-200">
            </div>
        <?php endif; ?>
        <?php if ($imgExt !== ''): ?>
            <p class="text-sm"><a href="<?= htmlspecialchars($imgExt) ?>" class="text-emerald-800 underline break-all" target="_blank" rel="noopener"><?= htmlspecialchars($imgExt) ?></a></p>
        <?php endif; ?>
        <?php if (trim((string) ($rpSnap['character_name'] ?? '')) !== ''): ?>
            <div>
                <p class="text-xs font-bold text-emerald-900">Personnage</p>
                <p class="text-sm text-emerald-950"><?= htmlspecialchars((string) $rpSnap['character_name']) ?></p>
            </div>
        <?php endif; ?>
        <?php if (trim((string) ($rpSnap['bio'] ?? '')) !== ''): ?>
            <div>
                <p class="text-xs font-bold text-emerald-900">Bio</p>
                <pre class="text-sm text-emerald-950 whitespace-pre-wrap font-sans"><?= htmlspecialchars((string) $rpSnap['bio']) ?></pre>
            </div>
        <?php endif; ?>
        <?php if (trim((string) ($rpSnap['cv'] ?? '')) !== ''): ?>
            <div>
                <p class="text-xs font-bold text-emerald-900">CV</p>
                <pre class="text-sm text-emerald-950 whitespace-pre-wrap font-sans"><?= htmlspecialchars((string) $rpSnap['cv']) ?></pre>
            </div>
        <?php endif; ?>
        <?php if (trim((string) ($rpSnap['admin_notes'] ?? '')) !== ''): ?>
            <div>
                <p class="text-xs font-bold text-emerald-900">Notes candidat</p>
                <pre class="text-sm text-emerald-950 whitespace-pre-wrap font-sans"><?= htmlspecialchars((string) $rpSnap['admin_notes']) ?></pre>
            </div>
        <?php endif; ?>
        <?php
        $derived = is_array($rpSnap['derived_availability'] ?? null) ? $rpSnap['derived_availability'] : null;
        ?>
        <?php if ($derived && (!empty($derived['availability']) || !empty($derived['weekly_availability']))): ?>
            <div class="text-sm text-emerald-950">
                <p class="text-xs font-bold text-emerald-900 mb-1">Disponibilités dérivées</p>
                <?php if (!empty($derived['weekly_availability'])): ?>
                    <p class="whitespace-pre-wrap"><?= htmlspecialchars((string) $derived['weekly_availability']) ?></p>
                <?php endif; ?>
                <?php if (!empty($derived['availability']) && ($derived['availability'] ?? '') !== ($derived['weekly_availability'] ?? '')): ?>
                    <p class="mt-2 whitespace-pre-wrap"><?= htmlspecialchars((string) $derived['availability']) ?></p>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <p class="mt-8 text-sm text-slate-500"><a href="<?= url('back-office/recruitments') ?>" class="underline">Retour liste</a></p>
</div>
