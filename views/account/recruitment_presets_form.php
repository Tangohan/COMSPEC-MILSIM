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

$accountNavKey = 'recruitment';
$accountTitle = $preset ? 'Modifier le profil de candidature' : 'Nouveau profil de candidature';
$accountLead = 'Dossier réutilisable pour les formulaires d’enrôlement : personnage, disponibilités, matériel et motivation.';
require base_path('views/partials/account/shell_open.php');
?>

    <form method="post" action="<?= htmlspecialchars($formAction) ?>" enctype="multipart/form-data" class="account-hub__stack">
        <?= \App\Core\Csrf::field() ?>

        <div class="account-hub__panel">
            <div class="account-hub__panel-head">
                <p class="account-hub__panel-kicker">Profil</p>
                <h2 class="account-hub__panel-title">Identification du préréglage</h2>
            </div>
            <div class="account-hub__panel-body account-hub__form-grid">
            <div>
                <label class="account-hub__label">Nom du profil</label>
                <input type="text" name="label" required maxlength="120" value="<?= htmlspecialchars($labelVal) ?>" placeholder="ex. Candidature blindé / chef de groupe">
                <?php if (!empty($errors['label'])): ?>
                    <p class="account-hub__field-error"><?= htmlspecialchars($errors['label'][0] ?? '') ?></p>
                <?php endif; ?>
            </div>
            <div>
                <label class="account-hub__label">Indicatif (optionnel)</label>
                <input type="text" name="callsign" value="<?= htmlspecialchars((string) ($p['callsign'] ?? '')) ?>">
            </div>
            </div>
        </div>

        <div class="account-hub__panel">
            <div class="account-hub__panel-head">
                <p class="account-hub__panel-kicker">Personnage</p>
                <h2 class="account-hub__panel-title">Personnage &amp; rôle-play</h2>
                <p class="account-hub__panel-desc">L’identité personnage est surtout prénom + nom (et naissance / nationalité). Le nom de scène est optionnel.</p>
            </div>
            <div class="account-hub__panel-body space-y-4">
            <div class="account-hub__form-grid account-hub__form-grid--2">
                <div>
                    <label class="account-hub__label">Prénom (personnage)</label>
                    <input type="text" name="rp_first_name" maxlength="100" value="<?= htmlspecialchars((string) ($rp['first_name'] ?? '')) ?>" autocomplete="off">
                </div>
                <div>
                    <label class="account-hub__label">Nom (personnage)</label>
                    <input type="text" name="rp_last_name" maxlength="100" value="<?= htmlspecialchars((string) ($rp['last_name'] ?? '')) ?>" autocomplete="off">
                </div>
                <div>
                    <label class="account-hub__label">Date de naissance (personnage)</label>
                    <input type="date" name="rp_birth_date" value="<?= htmlspecialchars((string) ($rp['birth_date'] ?? '')) ?>" autocomplete="off">
                </div>
                <div>
                    <label class="account-hub__label">Nationalité (personnage)</label>
                    <input type="text" name="rp_nationality" maxlength="100" value="<?= htmlspecialchars((string) ($rp['nationality'] ?? '')) ?>" autocomplete="off">
                </div>
                <div style="grid-column:1/-1">
                    <label class="account-hub__label">Nom de scène (optionnel)</label>
                    <input type="text" name="rp_character_name" maxlength="200" value="<?= htmlspecialchars((string) ($rp['character_name'] ?? '')) ?>" placeholder="Prioritaire sur prénom + nom si renseigné">
                </div>
            </div>
            <div>
                <label class="account-hub__label">Bio / fil narratif</label>
                <textarea name="rp_bio" rows="5" placeholder="Histoire courte, ton RP, unité d’origine fictive…"><?= htmlspecialchars((string) ($rp['bio'] ?? '')) ?></textarea>
            </div>
            <div>
                <label class="account-hub__label">CV du personnage (parcours, spécialités)</label>
                <textarea name="rp_cv" rows="6" placeholder="Formation, opérations simulées, rôles habituels…"><?= htmlspecialchars((string) ($rp['cv'] ?? '')) ?></textarea>
            </div>
            <div>
                <label class="account-hub__label">Portrait du personnage (JPG, PNG, WebP — max 2 Mo)</label>
                <?php if ($imgPreview): ?>
                    <p class="account-hub__hint">Image actuelle :</p>
                    <img src="<?= htmlspecialchars($imgPreview) ?>" alt="" class="account-hub__media-preview account-hub__media-preview--portrait" style="margin:.5rem 0;height:6rem;width:6rem">
                    <label class="account-hub__check" style="margin:.5rem 0;cursor:pointer">
                        <input type="checkbox" name="remove_character_image" value="1">
                        <span style="font-size:.8125rem">Supprimer l’image enregistrée</span>
                    </label>
                <?php endif; ?>
                <input type="file" name="rp_character_image" accept="image/jpeg,image/png,image/webp">
                <?php if (!empty($errors['rp_character_image'])): ?>
                    <p class="account-hub__field-error"><?= htmlspecialchars($errors['rp_character_image'][0] ?? '') ?></p>
                <?php endif; ?>
            </div>
            <div>
                <label class="account-hub__label">Lien image externe (optionnel)</label>
                <input type="url" name="rp_image_external_url" value="<?= htmlspecialchars((string) ($rp['image_external_url'] ?? '')) ?>" placeholder="https://…">
            </div>
            </div>
        </div>

        <div class="account-hub__panel">
            <div class="account-hub__panel-head">
                <p class="account-hub__panel-kicker">Planning</p>
                <h2 class="account-hub__panel-title">Disponibilités</h2>
                <p class="account-hub__panel-desc">Créneaux par jour. Laissez un jour sur « — » pour ignorer la ligne.</p>
            </div>
            <div class="account-hub__panel-body space-y-4">
            <div>
                <label class="account-hub__label">Référence fuseau / précision</label>
                <input type="text" name="availability_timezone_label" value="<?= htmlspecialchars((string) ($av['timezone_label'] ?? '')) ?>" placeholder="ex. Europe/Paris, soirées FR">
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
            <button type="button" id="add-schedule-slot" class="account-hub__btn account-hub__btn--soft" style="padding:.45rem .75rem;font-size:.75rem">+ Ajouter un créneau</button>
            <div>
                <label class="account-hub__label">Précisions libres (contraintes, exceptions…)</label>
                <textarea name="availability_free_text" rows="3"><?= htmlspecialchars((string) ($av['free_text'] ?? '')) ?></textarea>
            </div>
            </div>
        </div>

        <div class="account-hub__panel">
            <div class="account-hub__panel-head">
                <p class="account-hub__panel-kicker">Technique</p>
                <h2 class="account-hub__panel-title">MilSim &amp; matériel</h2>
            </div>
            <div class="account-hub__panel-body space-y-4">
            <div class="account-hub__form-grid account-hub__form-grid--2">
                <div>
                    <label class="account-hub__label">Âge</label>
                    <input type="number" name="age" min="16" max="99" value="<?= htmlspecialchars((string) ($p['age'] ?? '')) ?>">
                </div>
                <div>
                    <label class="account-hub__label">Fuseau (texte libre)</label>
                    <input type="text" name="timezone" value="<?= htmlspecialchars((string) ($p['timezone'] ?? '')) ?>" placeholder="Paris (UTC+1)">
                </div>
            </div>
            <div>
                <label class="account-hub__label">Configuration PC (processeur / carte graphique / mémoire)</label>
                <input type="text" name="system_config" value="<?= htmlspecialchars((string) ($p['system_config'] ?? '')) ?>">
            </div>
            <div class="account-hub__form-grid account-hub__form-grid--2">
                <div>
                    <label class="account-hub__label">Microphone</label>
                    <select name="microphone_quality">
                        <option value="">—</option>
                        <option value="Oui" <?= (($p['microphone_quality'] ?? '') === 'Oui') ? 'selected' : '' ?>>Oui</option>
                        <option value="Non" <?= (($p['microphone_quality'] ?? '') === 'Non') ? 'selected' : '' ?>>Non</option>
                    </select>
                </div>
                <div>
                    <label class="account-hub__label">Niveau ACE / ACRE</label>
                    <select name="ace_acre_level">
                        <option value="">—</option>
                        <?php foreach (['Aucune', 'Basique', 'Expérimenté', 'Avancé'] as $opt): ?>
                            <option value="<?= htmlspecialchars($opt) ?>" <?= (($p['ace_acre_level'] ?? '') === $opt) ? 'selected' : '' ?>><?= htmlspecialchars($opt) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div>
                <label class="account-hub__label">Expérience MilSim passée</label>
                <textarea name="past_milsim_experience" rows="4"><?= htmlspecialchars((string) ($p['past_milsim_experience'] ?? '')) ?></textarea>
            </div>
            <div>
                <label class="account-hub__label">Motivation — pourquoi rejoindre ?</label>
                <textarea name="motivation_why_join" rows="4"><?= htmlspecialchars((string) ($p['motivation_why_join'] ?? '')) ?></textarea>
            </div>
            <div>
                <label class="account-hub__label">Votre compréhension de l’engagement (accountability)</label>
                <textarea name="motivation_accountability" rows="3"><?= htmlspecialchars((string) ($p['motivation_accountability'] ?? '')) ?></textarea>
            </div>
            <div class="account-hub__form-grid account-hub__form-grid--2">
                <div>
                    <label class="account-hub__label">Engagement temps / effort</label>
                    <select name="commitment_effort">
                        <option value="">—</option>
                        <option value="Oui" <?= (($p['commitment_effort'] ?? '') === 'Oui') ? 'selected' : '' ?>>Oui</option>
                        <option value="Non" <?= (($p['commitment_effort'] ?? '') === 'Non') ? 'selected' : '' ?>>Non</option>
                    </select>
                </div>
                <div>
                    <label class="account-hub__label">Disponible mercredi &amp; samedi soir</label>
                    <select name="availability_wed_sat">
                        <option value="">—</option>
                        <?php foreach (['Oui', 'Non', 'Variable'] as $opt): ?>
                            <option value="<?= htmlspecialchars($opt) ?>" <?= (($p['availability_wed_sat'] ?? '') === $opt) ? 'selected' : '' ?>><?= htmlspecialchars($opt) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            </div>
        </div>

        <div class="account-hub__panel">
            <div class="account-hub__panel-head">
                <p class="account-hub__panel-kicker">Notes</p>
                <h2 class="account-hub__panel-title">Notes personnelles (candidat)</h2>
                <p class="account-hub__panel-desc">Informations ou contraintes à joindre au dossier — distinctes de la motivation affichée sur le formulaire public.</p>
            </div>
            <div class="account-hub__panel-body">
            <textarea name="admin_notes" rows="4"><?= htmlspecialchars((string) ($p['admin_notes'] ?? '')) ?></textarea>
            </div>
        </div>

        <div class="account-hub__sticky-bar">
            <button type="submit" class="account-hub__btn account-hub__btn--ink">Enregistrer le profil</button>
            <a href="<?= url('account/recruitment-presets') ?>" class="account-hub__btn" style="background:#fff;color:#475569;border:1px solid #e2e8f0">Annuler</a>
        </div>
    </form>
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

<?php require base_path('views/partials/account/shell_close.php'); ?>
