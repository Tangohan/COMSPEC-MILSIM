<?php
declare(strict_types=1);

$preset = $preset ?? null;
$formAction = $formAction ?? url('account/recruitment-presets/create');
$errors = $errors ?? [];
$payloadDefaults = $payloadDefaults ?? [];
$p = is_array($payloadDefaults) ? $payloadDefaults : [];
$rp = is_array($p['rp'] ?? null) ? $p['rp'] : [];
$av = is_array($p['availability'] ?? null) ? $p['availability'] : [];
$schedule = is_array($av['schedule'] ?? null) ? $av['schedule'] : [];
$scheduleRows = [];
foreach ($schedule as $s) {
    if (!is_array($s)) {
        continue;
    }
    $scheduleRows[] = [
        'dow' => (int) ($s['dow'] ?? 0),
        'start' => (string) ($s['start'] ?? ''),
        'end' => (string) ($s['end'] ?? ''),
    ];
}
if ($scheduleRows === []) {
    $scheduleRows[] = ['dow' => 0, 'start' => '', 'end' => ''];
}
$labelVal = $preset ? (string) ($preset['label'] ?? '') : '';
$dowLabels = [
    0 => '— Jour —',
    1 => 'Lundi',
    2 => 'Mardi',
    3 => 'Mercredi',
    4 => 'Jeudi',
    5 => 'Vendredi',
    6 => 'Samedi',
    7 => 'Dimanche',
];
$imgUrl = trim((string) ($rp['image_url'] ?? ''));
$imgPreview = $imgUrl !== '' ? url($imgUrl) : null;
?>
<div class="max-w-3xl mx-auto px-6 py-12">
    <h1 class="text-2xl font-black text-slate-900 mb-2"><?= $preset ? 'Modifier le profil' : 'Nouveau profil' ?></h1>
    <p class="text-slate-600 text-sm mb-8">Dossier de candidature réutilisable (mode compte Athena) : RP, matériel, disponibilités et notes.</p>

    <form method="post" action="<?= htmlspecialchars($formAction) ?>" enctype="multipart/form-data" class="space-y-8">
        <?= \App\Core\Csrf::field() ?>

        <div class="bg-white border border-slate-200 rounded-2xl p-6 shadow-sm space-y-4">
            <h2 class="text-sm font-black uppercase tracking-widest text-slate-900">Profil</h2>
            <div>
                <label class="block text-xs font-bold text-slate-600 mb-1">Nom du profil</label>
                <input type="text" name="label" required maxlength="120" value="<?= htmlspecialchars($labelVal) ?>" class="w-full border border-slate-200 rounded-xl px-4 py-3 text-sm" placeholder="ex. Candidature blindé / chef de groupe">
                <?php if (!empty($errors['label'])): ?>
                    <p class="text-xs text-red-600 mt-1"><?= htmlspecialchars($errors['label'][0] ?? '') ?></p>
                <?php endif; ?>
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-600 mb-1">Indicatif / callsign (optionnel)</label>
                <input type="text" name="callsign" class="w-full border border-slate-200 rounded-xl px-4 py-3 text-sm" value="<?= htmlspecialchars((string) ($p['callsign'] ?? '')) ?>">
            </div>
        </div>

        <div class="bg-white border border-slate-200 rounded-2xl p-6 shadow-sm space-y-4">
            <h2 class="text-sm font-black uppercase tracking-widest text-slate-900">Personnage &amp; RP</h2>
            <p class="text-xs text-slate-500">L’identité personnage est surtout <strong>prénom + nom</strong> (et naissance / nationalité). Le <strong>nom de scène</strong> est optionnel et remplace l’affichage court si vous le remplissez.</p>
            <div class="grid sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold text-slate-600 mb-1">Prénom (personnage)</label>
                    <input type="text" name="rp_first_name" maxlength="100" class="w-full border border-slate-200 rounded-xl px-4 py-3 text-sm" value="<?= htmlspecialchars((string) ($rp['first_name'] ?? '')) ?>" autocomplete="off">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-600 mb-1">Nom (personnage)</label>
                    <input type="text" name="rp_last_name" maxlength="100" class="w-full border border-slate-200 rounded-xl px-4 py-3 text-sm" value="<?= htmlspecialchars((string) ($rp['last_name'] ?? '')) ?>" autocomplete="off">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-600 mb-1">Date de naissance (personnage)</label>
                    <input type="date" name="rp_birth_date" class="w-full border border-slate-200 rounded-xl px-4 py-3 text-sm" value="<?= htmlspecialchars((string) ($rp['birth_date'] ?? '')) ?>" autocomplete="off">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-600 mb-1">Nationalité (personnage)</label>
                    <input type="text" name="rp_nationality" maxlength="100" class="w-full border border-slate-200 rounded-xl px-4 py-3 text-sm" value="<?= htmlspecialchars((string) ($rp['nationality'] ?? '')) ?>" autocomplete="off">
                </div>
                <div class="sm:col-span-2">
                    <label class="block text-xs font-bold text-slate-600 mb-1">Nom de scène (optionnel)</label>
                    <input type="text" name="rp_character_name" maxlength="200" class="w-full border border-slate-200 rounded-xl px-4 py-3 text-sm" value="<?= htmlspecialchars((string) ($rp['character_name'] ?? '')) ?>" placeholder="ex. Sgt. M. Durant — prioritaire sur prénom + nom si renseigné">
                </div>
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-600 mb-1">Bio / fil narratif</label>
                <textarea name="rp_bio" rows="5" class="w-full border border-slate-200 rounded-xl px-4 py-3 text-sm" placeholder="Histoire courte, ton RP, unité d’origine fictive…"><?= htmlspecialchars((string) ($rp['bio'] ?? '')) ?></textarea>
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-600 mb-1">CV du personnage (parcours, spécialités RP)</label>
                <textarea name="rp_cv" rows="6" class="w-full border border-slate-200 rounded-xl px-4 py-3 text-sm" placeholder="Formation, opérations simulées, rôles habituels…"><?= htmlspecialchars((string) ($rp['cv'] ?? '')) ?></textarea>
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-600 mb-1">Portrait du personnage (JPG, PNG, WebP — max 2 Mo)</label>
                <?php if ($imgPreview): ?>
                    <p class="text-xs text-slate-500 mb-2">Image actuelle :</p>
                    <img src="<?= htmlspecialchars($imgPreview) ?>" alt="" class="h-24 w-24 object-cover rounded-xl border border-slate-200 mb-2">
                    <label class="inline-flex items-center gap-2 text-sm text-slate-700">
                        <input type="checkbox" name="remove_character_image" value="1" class="rounded border-slate-300">
                        Supprimer l’image enregistrée
                    </label>
                <?php endif; ?>
                <input type="file" name="rp_character_image" accept="image/jpeg,image/png,image/webp" class="mt-2 block w-full text-sm text-slate-600">
                <?php if (!empty($errors['rp_character_image'])): ?>
                    <p class="text-xs text-red-600 mt-1"><?= htmlspecialchars($errors['rp_character_image'][0] ?? '') ?></p>
                <?php endif; ?>
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-600 mb-1">Lien image externe (optionnel)</label>
                <input type="url" name="rp_image_external_url" class="w-full border border-slate-200 rounded-xl px-4 py-3 text-sm" value="<?= htmlspecialchars((string) ($rp['image_external_url'] ?? '')) ?>" placeholder="https://…">
            </div>
        </div>

        <div class="bg-white border border-slate-200 rounded-2xl p-6 shadow-sm space-y-4">
            <h2 class="text-sm font-black uppercase tracking-widest text-slate-900">Disponibilités</h2>
            <p class="text-xs text-slate-500">Créneaux par jour (fuseau indicatif ci-dessous). Laisser un jour sur « — » pour ignorer la ligne.</p>
            <div>
                <label class="block text-xs font-bold text-slate-600 mb-1">Référence fuseau / précision</label>
                <input type="text" name="availability_timezone_label" class="w-full border border-slate-200 rounded-xl px-4 py-3 text-sm" value="<?= htmlspecialchars((string) ($av['timezone_label'] ?? '')) ?>" placeholder="ex. Europe/Paris, soirées FR">
            </div>
            <div id="schedule-slots" class="space-y-3">
                <?php foreach ($scheduleRows as $idx => $row): ?>
                    <div class="schedule-row flex flex-wrap items-end gap-2 rounded-xl border border-slate-100 bg-slate-50 p-3">
                        <div>
                            <label class="block text-[10px] font-bold text-slate-500 mb-1">Jour</label>
                            <select name="slot_dow[]" class="border border-slate-200 rounded-lg px-2 py-2 text-sm min-w-[9rem]">
                                <?php foreach ($dowLabels as $dv => $dl): ?>
                                    <option value="<?= (int) $dv ?>" <?= (int) $row['dow'] === (int) $dv ? 'selected' : '' ?>><?= htmlspecialchars($dl) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold text-slate-500 mb-1">Début</label>
                            <input type="time" name="slot_start[]" value="<?= htmlspecialchars($row['start']) ?>" class="border border-slate-200 rounded-lg px-2 py-2 text-sm">
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold text-slate-500 mb-1">Fin</label>
                            <input type="time" name="slot_end[]" value="<?= htmlspecialchars($row['end']) ?>" class="border border-slate-200 rounded-lg px-2 py-2 text-sm">
                        </div>
                        <button type="button" class="remove-slot text-xs text-rose-600 font-semibold px-2 py-2" title="Retirer ce créneau">✕</button>
                    </div>
                <?php endforeach; ?>
            </div>
            <button type="button" id="add-schedule-slot" class="text-xs font-bold text-emerald-700 underline">+ Ajouter un créneau</button>
            <div>
                <label class="block text-xs font-bold text-slate-600 mb-1">Précisions libres (contraintes IRL, exceptions…)</label>
                <textarea name="availability_free_text" rows="3" class="w-full border border-slate-200 rounded-xl px-4 py-3 text-sm"><?= htmlspecialchars((string) ($av['free_text'] ?? '')) ?></textarea>
            </div>
        </div>

        <div class="bg-white border border-slate-200 rounded-2xl p-6 shadow-sm space-y-4">
            <h2 class="text-sm font-black uppercase tracking-widest text-slate-900">MilSim &amp; technique</h2>
            <div class="grid sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold text-slate-600 mb-1">Âge</label>
                    <input type="number" name="age" min="16" max="99" class="w-full border border-slate-200 rounded-xl px-4 py-3 text-sm" value="<?= htmlspecialchars((string) ($p['age'] ?? '')) ?>">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-600 mb-1">Fuseau (texte)</label>
                    <input type="text" name="timezone" class="w-full border border-slate-200 rounded-xl px-4 py-3 text-sm" value="<?= htmlspecialchars((string) ($p['timezone'] ?? '')) ?>" placeholder="Paris (UTC+1)">
                </div>
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-600 mb-1">Configuration PC (CPU / GPU / RAM)</label>
                <input type="text" name="system_config" class="w-full border border-slate-200 rounded-xl px-4 py-3 text-sm" value="<?= htmlspecialchars((string) ($p['system_config'] ?? '')) ?>">
            </div>
            <div class="grid sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold text-slate-600 mb-1">Microphone</label>
                    <select name="microphone_quality" class="w-full border border-slate-200 rounded-xl px-4 py-3 text-sm bg-white">
                        <option value="">—</option>
                        <option value="Oui" <?= (($p['microphone_quality'] ?? '') === 'Oui') ? 'selected' : '' ?>>Oui</option>
                        <option value="Non" <?= (($p['microphone_quality'] ?? '') === 'Non') ? 'selected' : '' ?>>Non</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-600 mb-1">ACE / ACRE</label>
                    <select name="ace_acre_level" class="w-full border border-slate-200 rounded-xl px-4 py-3 text-sm bg-white">
                        <option value="">—</option>
                        <?php foreach (['Aucune', 'Basique', 'Expérimenté', 'Avancé'] as $opt): ?>
                            <option value="<?= htmlspecialchars($opt) ?>" <?= (($p['ace_acre_level'] ?? '') === $opt) ? 'selected' : '' ?>><?= htmlspecialchars($opt) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-600 mb-1">Expérience MilSim passée</label>
                <textarea name="past_milsim_experience" rows="4" class="w-full border border-slate-200 rounded-xl px-4 py-3 text-sm"><?= htmlspecialchars((string) ($p['past_milsim_experience'] ?? '')) ?></textarea>
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-600 mb-1">Motivation — pourquoi rejoindre ?</label>
                <textarea name="motivation_why_join" rows="4" class="w-full border border-slate-200 rounded-xl px-4 py-3 text-sm"><?= htmlspecialchars((string) ($p['motivation_why_join'] ?? '')) ?></textarea>
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-600 mb-1">Accountability (votre compréhension)</label>
                <textarea name="motivation_accountability" rows="3" class="w-full border border-slate-200 rounded-xl px-4 py-3 text-sm"><?= htmlspecialchars((string) ($p['motivation_accountability'] ?? '')) ?></textarea>
            </div>
            <div class="grid sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold text-slate-600 mb-1">Engagement temps / effort</label>
                    <select name="commitment_effort" class="w-full border border-slate-200 rounded-xl px-4 py-3 text-sm bg-white">
                        <option value="">—</option>
                        <option value="Oui" <?= (($p['commitment_effort'] ?? '') === 'Oui') ? 'selected' : '' ?>>Oui</option>
                        <option value="Non" <?= (($p['commitment_effort'] ?? '') === 'Non') ? 'selected' : '' ?>>Non</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-600 mb-1">Dispo mer &amp; sam soir</label>
                    <select name="availability_wed_sat" class="w-full border border-slate-200 rounded-xl px-4 py-3 text-sm bg-white">
                        <option value="">—</option>
                        <?php foreach (['Oui', 'Non', 'Variable'] as $opt): ?>
                            <option value="<?= htmlspecialchars($opt) ?>" <?= (($p['availability_wed_sat'] ?? '') === $opt) ? 'selected' : '' ?>><?= htmlspecialchars($opt) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </div>

        <div class="bg-amber-50 border border-amber-200 rounded-2xl p-6 shadow-sm space-y-4">
            <h2 class="text-sm font-black uppercase tracking-widest text-amber-950">Notes (candidat)</h2>
            <p class="text-xs text-amber-900/80">Informations administratives ou contraintes que vous souhaitez joindre au dossier (non affichées comme « motivation » sur le formulaire public).</p>
            <textarea name="admin_notes" rows="4" class="w-full border border-amber-200 rounded-xl px-4 py-3 text-sm bg-white"><?= htmlspecialchars((string) ($p['admin_notes'] ?? '')) ?></textarea>
        </div>

        <div class="flex gap-3">
            <button type="submit" class="px-6 py-3 bg-slate-900 text-white text-xs font-black uppercase tracking-widest rounded-xl hover:bg-emerald-600">Enregistrer</button>
            <a href="<?= url('account/recruitment-presets') ?>" class="px-6 py-3 border border-slate-200 rounded-xl text-sm font-semibold text-slate-700 hover:bg-slate-50">Annuler</a>
        </div>
    </form>
</div>
<script>
(function () {
    var container = document.getElementById('schedule-slots');
    var addBtn = document.getElementById('add-schedule-slot');
    if (!container || !addBtn) return;
    var dowOptions = <?= json_encode($dowLabels, JSON_UNESCAPED_UNICODE) ?>;

    function rowHtml() {
        var opts = '';
        for (var k in dowOptions) {
            if (!Object.prototype.hasOwnProperty.call(dowOptions, k)) continue;
            opts += '<option value="' + k + '">' + dowOptions[k].replace(/</g, '&lt;') + '</option>';
        }
        return '<div class="schedule-row flex flex-wrap items-end gap-2 rounded-xl border border-slate-100 bg-slate-50 p-3">' +
            '<div><label class="block text-[10px] font-bold text-slate-500 mb-1">Jour</label>' +
            '<select name="slot_dow[]" class="border border-slate-200 rounded-lg px-2 py-2 text-sm min-w-[9rem]">' + opts + '</select></div>' +
            '<div><label class="block text-[10px] font-bold text-slate-500 mb-1">Début</label>' +
            '<input type="time" name="slot_start[]" class="border border-slate-200 rounded-lg px-2 py-2 text-sm"></div>' +
            '<div><label class="block text-[10px] font-bold text-slate-500 mb-1">Fin</label>' +
            '<input type="time" name="slot_end[]" class="border border-slate-200 rounded-lg px-2 py-2 text-sm"></div>' +
            '<button type="button" class="remove-slot text-xs text-rose-600 font-semibold px-2 py-2" title="Retirer">✕</button></div>';
    }
    addBtn.addEventListener('click', function () {
        var wrap = document.createElement('div');
        wrap.innerHTML = rowHtml();
        var node = wrap.firstElementChild;
        if (node) container.appendChild(node);
        bindRemove(node);
    });
    function bindRemove(el) {
        if (!el) return;
        var btn = el.querySelector('.remove-slot');
        if (btn) btn.addEventListener('click', function () {
            if (container.querySelectorAll('.schedule-row').length > 1) el.remove();
        });
    }
    container.querySelectorAll('.schedule-row').forEach(bindRemove);
})();
</script>
