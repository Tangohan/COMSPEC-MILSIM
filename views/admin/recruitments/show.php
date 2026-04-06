<?php
/** @var array<string,mixed> $enlistment */
/** @var list<array<string,mixed>> $enlistmentCannedMessages */
$e = $enlistment ?? [];
$enlistmentCannedMessages = $enlistmentCannedMessages ?? [];
$rpSnap = is_array($e['recruitment_rp_json'] ?? null) ? $e['recruitment_rp_json'] : null;
$id = (int) ($e['id'] ?? 0);
$statusRaw = (string) ($e['status'] ?? '');
$statusLabels = [
    'submitted' => 'Soumis',
    'reviewed' => 'Acceptée',
    'rejected' => 'Refusée',
    'blocked' => 'Interdit (non admis)',
];
$statusLabel = $statusLabels[$statusRaw] ?? $statusRaw;
$flashOk = \App\Core\Session::getFlash('success');
$flashErr = \App\Core\Session::getFlash('error');
?>
<div class="max-w-4xl mx-auto px-6 py-12">
    <div class="flex flex-wrap items-center justify-between gap-4 mb-8">
        <h1 class="text-2xl font-black text-slate-900">Candidature #<?= $id ?></h1>
        <a href="<?= url('back-office/recruitments') ?>" class="text-sm text-slate-600 hover:text-slate-900 underline">← Liste</a>
    </div>

    <?php if ($flashOk): ?>
        <p class="mb-4 text-sm text-emerald-700 font-medium"><?= htmlspecialchars((string) $flashOk) ?></p>
    <?php endif; ?>
    <?php if ($flashErr): ?>
        <p class="mb-4 text-sm text-red-600 font-medium"><?= htmlspecialchars((string) $flashErr) ?></p>
    <?php endif; ?>

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
            <dd>
                <span class="inline-flex px-2 py-0.5 rounded text-xs font-medium
                    <?= $statusRaw === 'submitted' ? 'bg-amber-100 text-amber-900' : ($statusRaw === 'rejected' ? 'bg-red-100 text-red-800' : ($statusRaw === 'blocked' ? 'bg-red-950 text-red-100' : ($statusRaw === 'reviewed' ? 'bg-emerald-100 text-emerald-900' : 'bg-slate-100 text-slate-700'))) ?>">
                    <?= htmlspecialchars($statusLabel ?: '—') ?>
                </span>
            </dd>
            <?php if (!empty($e['reviewed_at']) || !empty($e['reviewer_comment']) || !empty($e['reviewed_by'])): ?>
            <dt class="text-slate-500">Traitement</dt>
            <dd class="text-sm text-slate-700">
                <?php if (!empty($e['reviewed_at'])): ?>
                    <span class="block"><?= htmlspecialchars(date('d/m/Y H:i', strtotime((string) $e['reviewed_at']))) ?></span>
                <?php endif; ?>
                <?php if (!empty($e['reviewed_by'])): ?>
                    <span class="block text-slate-500">Par utilisateur #<?= (int) $e['reviewed_by'] ?></span>
                <?php endif; ?>
                <?php if (!empty($e['reviewer_comment'])): ?>
                    <span class="block mt-1 whitespace-pre-wrap"><?= htmlspecialchars((string) $e['reviewer_comment']) ?></span>
                <?php endif; ?>
            </dd>
            <?php endif; ?>
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

    <?php if ($statusRaw === 'submitted'): ?>
    <div class="bg-amber-50/90 border border-amber-200 rounded-2xl p-6 shadow-sm mb-6">
        <h2 class="text-sm font-black uppercase tracking-widest text-amber-950 mb-3">Décision</h2>
        <form method="post" action="<?= htmlspecialchars(url('back-office/recruitments/' . $id . '/decision')) ?>" class="space-y-4">
            <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars(\App\Core\Csrf::token()) ?>">
            <div>
                <div class="flex flex-wrap items-center justify-between gap-2 mb-1">
                    <label for="reviewer_comment" class="block text-xs font-bold text-amber-900">Commentaire interne (optionnel)</label>
                    <div class="flex flex-wrap items-center gap-2">
                        <?php if (!empty($enlistmentCannedMessages)): ?>
                        <label for="canned-msg-select" class="sr-only">Message préfait</label>
                        <select id="canned-msg-select" class="max-w-[min(100%,16rem)] rounded-lg border border-amber-300 bg-white px-2 py-1.5 text-[11px] font-semibold text-amber-950 shadow-sm">
                            <option value="">— Insérer un message préfait —</option>
                            <?php foreach ($enlistmentCannedMessages as $cm): ?>
                            <option value="<?= (int) ($cm['id'] ?? 0) ?>"><?= htmlspecialchars((string) ($cm['label'] ?? ''), ENT_QUOTES, 'UTF-8') ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?php endif; ?>
                        <a href="<?= url('back-office/recruitments/messages-prefaits') ?>" class="text-[11px] font-bold text-sky-800 hover:text-sky-950 underline underline-offset-2">Gérer les modèles</a>
                    </div>
                </div>
                <textarea id="reviewer_comment" name="reviewer_comment" rows="3" class="w-full rounded-xl border border-amber-200 bg-white px-3 py-2 text-sm text-slate-900 placeholder:text-slate-400" placeholder="Motif, consignes…"></textarea>
                <?php if (!empty($enlistmentCannedMessages)): ?>
                <script type="application/json" id="enlistment-canned-json"><?= json_encode($enlistmentCannedMessages, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
                <script>
                (function () {
                  var sel = document.getElementById('canned-msg-select');
                  var raw = document.getElementById('enlistment-canned-json');
                  var ta = document.getElementById('reviewer_comment');
                  if (!sel || !raw || !ta) return;
                  var list = [];
                  try { list = JSON.parse(raw.textContent || '[]'); } catch (e) { return; }
                  var byId = {};
                  list.forEach(function (row) { if (row && row.id) byId[String(row.id)] = row.body || ''; });
                  sel.addEventListener('change', function () {
                    var id = sel.value;
                    if (!id || !byId[id]) { sel.selectedIndex = 0; return; }
                    var chunk = byId[id];
                    if (ta.value.trim() !== '') ta.value += '\n\n';
                    ta.value += chunk;
                    sel.selectedIndex = 0;
                    ta.focus();
                  });
                })();
                </script>
                <?php endif; ?>
            </div>
            <div class="flex flex-wrap gap-2 items-stretch sm:items-center enlist-decision-actions" role="group" aria-label="Décision sur la candidature">
                <?php
                $btnBase = 'enlist-decision-btn inline-flex min-h-[2.75rem] items-center justify-center px-5 py-2.5 rounded-xl text-sm font-bold shadow-sm border transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 cursor-pointer';
                ?>
                <button type="submit" name="decision" value="accept" class="<?= $btnBase ?> enlist-decision-btn--accept">Accepter</button>
                <button type="submit" name="decision" value="reject" class="<?= $btnBase ?> enlist-decision-btn--reject">Refuser</button>
                <button type="submit" name="decision" value="block" class="<?= $btnBase ?> enlist-decision-btn--block">Interdire</button>
            </div>
            <p class="text-xs text-amber-900/80"><strong>Interdire</strong> marque la candidature comme refus définitif (non admis). Le commentaire est conservé en interne.</p>
        </form>
    </div>
    <?php endif; ?>

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
