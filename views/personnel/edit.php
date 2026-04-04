<?php
$targetUser = $targetUser ?? null;
$personnelProfile = $personnelProfile ?? null;
$units = $units ?? [];
$isMe = (int)($targetUser['id'] ?? 0) === (int)(\App\Core\Session::get('user_id'));
$formAction = url('personnel/' . (int)$targetUser['id'] . '/update');
if (!$targetUser) {
    echo '<p>Utilisateur non trouvé.</p>';
    return;
}
$p = $personnelProfile ?? [];
$clearanceOptions = ['Non classifié', 'Restreint', 'Confidentiel', 'Secret', 'Très secret'];
$currentClearance = trim((string)($p['clearance_level'] ?? ''));
?>
<div class="max-w-3xl mx-auto px-6 py-12">
    <h1 class="text-2xl font-black text-slate-900 mb-2">Éditer le dossier</h1>
    <p class="text-slate-600 mb-6">Identité opérationnelle, affectation, équipement.</p>
    <?php $success = \App\Core\Session::getFlash('success'); if ($success): ?>
    <div class="mb-4 p-3 bg-green-50 border border-green-200 text-green-800 text-sm rounded"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>
    <?php $error = \App\Core\Session::getFlash('error'); if ($error): ?>
    <div class="mb-4 p-3 bg-red-50 border border-red-200 text-red-800 text-sm rounded"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="post" action="<?= htmlspecialchars($formAction) ?>" class="space-y-8">
        <?= \App\Core\Csrf::field() ?>

        <section class="bg-white border border-slate-200 rounded-2xl p-6">
            <h2 class="text-sm font-black uppercase tracking-wider text-slate-900 mb-4">Identité opérationnelle</h2>
            <div class="grid md:grid-cols-2 gap-4">
                <div>
                    <label for="character_name" class="block text-xs font-bold text-slate-600 mb-1">Nom opérateur / RP</label>
                    <input type="text" name="character_name" id="character_name" value="<?= htmlspecialchars($p['character_name'] ?? $targetUser['display_name'] ?? '') ?>" class="w-full px-3 py-2 border border-slate-300 rounded focus:ring-2 focus:ring-slate-900" maxlength="150">
                </div>
                <div>
                    <label for="callsign" class="block text-xs font-bold text-slate-600 mb-1">Callsign</label>
                    <input type="text" name="callsign" id="callsign" value="<?= htmlspecialchars($p['callsign'] ?? $targetUser['callsign'] ?? '') ?>" class="w-full px-3 py-2 border border-slate-300 rounded focus:ring-2 focus:ring-slate-900" maxlength="100">
                </div>
                <div>
                    <label for="primary_role" class="block text-xs font-bold text-slate-600 mb-1">Rôle principal</label>
                    <input type="text" name="primary_role" id="primary_role" value="<?= htmlspecialchars($p['primary_role'] ?? '') ?>" placeholder="Fusilier, JTAC, Medic…" class="w-full px-3 py-2 border border-slate-300 rounded focus:ring-2 focus:ring-slate-900" maxlength="100">
                </div>
                <div>
                    <label for="secondary_role" class="block text-xs font-bold text-slate-600 mb-1">Rôle secondaire</label>
                    <input type="text" name="secondary_role" id="secondary_role" value="<?= htmlspecialchars($p['secondary_role'] ?? '') ?>" class="w-full px-3 py-2 border border-slate-300 rounded focus:ring-2 focus:ring-slate-900" maxlength="100">
                </div>
                <div>
                    <label for="clearance_level" class="block text-xs font-bold text-slate-600 mb-1">Niveau de clearance</label>
                    <select name="clearance_level" id="clearance_level" class="w-full px-3 py-2 border border-slate-300 rounded focus:ring-2 focus:ring-slate-900">
                        <option value="">— Non défini —</option>
                        <?php foreach ($clearanceOptions as $opt): ?>
                        <option value="<?= htmlspecialchars($opt) ?>" <?= $currentClearance === $opt ? 'selected' : '' ?>><?= htmlspecialchars($opt) ?></option>
                        <?php endforeach; ?>
                        <?php if ($currentClearance !== '' && !in_array($currentClearance, $clearanceOptions, true)): ?>
                        <option value="<?= htmlspecialchars($currentClearance) ?>" selected><?= htmlspecialchars($currentClearance) ?></option>
                        <?php endif; ?>
                    </select>
                </div>
                <div>
                    <label for="enlistment_date" class="block text-xs font-bold text-slate-600 mb-1">Date d'incorporation</label>
                    <input type="date" name="enlistment_date" id="enlistment_date" value="<?= htmlspecialchars($p['enlistment_date'] ?? '') ?>" class="w-full px-3 py-2 border border-slate-300 rounded focus:ring-2 focus:ring-slate-900">
                </div>
                <div class="md:col-span-2">
                    <label for="primary_unit_id" class="block text-xs font-bold text-slate-600 mb-1">Unité principale</label>
                    <select name="primary_unit_id" id="primary_unit_id" class="w-full px-3 py-2 border border-slate-300 rounded focus:ring-2 focus:ring-slate-900">
                        <option value="">— Aucune —</option>
                        <?php foreach ($units as $u): ?>
                        <option value="<?= (int)$u['id'] ?>" <?= (isset($p['primary_unit_id']) && (int)$p['primary_unit_id'] === (int)$u['id']) ? 'selected' : '' ?>><?= htmlspecialchars($u['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </section>

        <section class="bg-white border border-slate-200 rounded-2xl p-6">
            <h2 class="text-sm font-black uppercase tracking-wider text-slate-900 mb-4">Équipement / dotation</h2>
            <div class="grid md:grid-cols-2 gap-4">
                <div>
                    <label for="equipment_class" class="block text-xs font-bold text-slate-600 mb-1">Classe d'équipement</label>
                    <input type="text" name="equipment_class" id="equipment_class" value="<?= htmlspecialchars($p['equipment_class'] ?? '') ?>" placeholder="Rifleman Light…" class="w-full px-3 py-2 border border-slate-300 rounded focus:ring-2 focus:ring-slate-900" maxlength="100">
                </div>
                <div>
                    <label for="kit_assigned" class="block text-xs font-bold text-slate-600 mb-1">Kit assigné</label>
                    <input type="text" name="kit_assigned" id="kit_assigned" value="<?= htmlspecialchars($p['kit_assigned'] ?? '') ?>" class="w-full px-3 py-2 border border-slate-300 rounded focus:ring-2 focus:ring-slate-900" maxlength="255">
                </div>
                <div>
                    <label for="radio_assigned" class="block text-xs font-bold text-slate-600 mb-1">Radio</label>
                    <input type="text" name="radio_assigned" id="radio_assigned" value="<?= htmlspecialchars($p['radio_assigned'] ?? '') ?>" placeholder="PRC-152…" class="w-full px-3 py-2 border border-slate-300 rounded focus:ring-2 focus:ring-slate-900" maxlength="100">
                </div>
                <div>
                    <label for="vehicle_authorized" class="block text-xs font-bold text-slate-600 mb-1">Véhicule autorisé</label>
                    <input type="text" name="vehicle_authorized" id="vehicle_authorized" value="<?= htmlspecialchars($p['vehicle_authorized'] ?? '') ?>" placeholder="MRAP, Utility…" class="w-full px-3 py-2 border border-slate-300 rounded focus:ring-2 focus:ring-slate-900" maxlength="255">
                </div>
                <div>
                    <label for="weapon_specialty" class="block text-xs font-bold text-slate-600 mb-1">Spécialité armement</label>
                    <input type="text" name="weapon_specialty" id="weapon_specialty" value="<?= htmlspecialchars($p['weapon_specialty'] ?? '') ?>" class="w-full px-3 py-2 border border-slate-300 rounded focus:ring-2 focus:ring-slate-900" maxlength="100">
                </div>
                <div class="md:col-span-2 flex items-center gap-2">
                    <input type="checkbox" name="deployable" id="deployable" value="1" <?= ($p['deployable'] ?? 1) ? 'checked' : '' ?> class="rounded border-slate-300">
                    <label for="deployable" class="text-sm font-semibold text-slate-700">Déployable</label>
                </div>
            </div>
        </section>

        <section class="bg-white border border-slate-200 rounded-2xl p-6">
            <h2 class="text-sm font-black uppercase tracking-wider text-slate-900 mb-4">Notes de commandement</h2>
            <textarea name="command_notes" id="command_notes" rows="4" class="w-full px-3 py-2 border border-slate-300 rounded focus:ring-2 focus:ring-slate-900" placeholder="Notes internes (visible par vous et les admins)"><?= htmlspecialchars($p['command_notes'] ?? '') ?></textarea>
        </section>

        <div class="flex gap-4">
            <button type="submit" class="py-2.5 px-6 bg-slate-900 text-white font-semibold rounded hover:bg-slate-800">Enregistrer</button>
            <a href="<?= $isMe ? url('personnel/me') : url('personnel/' . (int)$targetUser['id']) ?>" class="py-2.5 px-6 border border-slate-300 text-slate-700 font-semibold rounded hover:bg-slate-50">Annuler</a>
        </div>
    </form>

    <p class="mt-8 text-sm text-slate-500"><a href="<?= $isMe ? url('personnel/me') : url('personnel/' . (int)$targetUser['id']) ?>" class="underline">Retour à la fiche</a></p>
</div>
