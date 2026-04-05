<?php $roles = $roles ?? []; $grades = $grades ?? []; $gradeCategories = $gradeCategories ?? []; ?>
<div class="max-w-2xl mx-auto px-6 py-12">
    <h1 class="text-2xl font-black text-slate-900 mb-6">Nouvel utilisateur</h1>

    <form method="post" action="<?= url('back-office/users/store') ?>" class="space-y-4">
        <?= \App\Core\Csrf::field() ?>
        <div>
            <label for="email" class="block text-sm font-medium text-slate-700">Email *</label>
            <input type="email" id="email" name="email" required class="mt-1 block w-full px-3 py-2 border border-slate-300 rounded-md shadow-sm" value="">
        </div>
        <div>
            <label for="password" class="block text-sm font-medium text-slate-700">Mot de passe * (min. 6 caractères)</label>
            <input type="password" id="password" name="password" required minlength="6" class="mt-1 block w-full px-3 py-2 border border-slate-300 rounded-md shadow-sm">
        </div>
        <div>
            <label for="display_name" class="block text-sm font-medium text-slate-700">Nom d'affichage</label>
            <input type="text" id="display_name" name="display_name" class="mt-1 block w-full px-3 py-2 border border-slate-300 rounded-md shadow-sm">
        </div>
        <div>
            <label for="callsign" class="block text-sm font-medium text-slate-700">Indicatif</label>
            <input type="text" id="callsign" name="callsign" class="mt-1 block w-full px-3 py-2 border border-slate-300 rounded-md shadow-sm">
        </div>
        <div>
            <label for="role_id" class="block text-sm font-medium text-slate-700">Rôle (communauté ou opérationnel)</label>
            <p class="text-xs text-slate-500 mt-0.5 mb-1">Les rôles site/plateforme ne sont pas attribuables ici.</p>
            <select id="role_id" name="role_id" class="mt-1 block w-full px-3 py-2 border border-slate-300 rounded-md shadow-sm">
                <option value="">—</option>
                <?php
                $byLayer = ['community' => [], 'intra' => []];
                foreach ($roles as $r) {
                    $ly = (string) ($r['role_layer'] ?? 'community');
                    if (!isset($byLayer[$ly])) {
                        $byLayer[$ly] = [];
                    }
                    $byLayer[$ly][] = $r;
                }
                ?>
                <?php if (!empty($byLayer['community'])): ?>
                <optgroup label="Gouvernance communauté">
                    <?php foreach ($byLayer['community'] as $r): ?>
                    <option value="<?= (int) $r['id'] ?>"><?= htmlspecialchars($r['name']) ?></option>
                    <?php endforeach; ?>
                </optgroup>
                <?php endif; ?>
                <?php if (!empty($byLayer['intra'])): ?>
                <optgroup label="Rôles opérationnels">
                    <?php foreach ($byLayer['intra'] as $r): ?>
                    <option value="<?= (int) $r['id'] ?>"><?= htmlspecialchars($r['name']) ?></option>
                    <?php endforeach; ?>
                </optgroup>
                <?php endif; ?>
            </select>
        </div>
        <div>
            <label for="nationality_code" class="block text-sm font-medium text-slate-700">Nationalité / doctrine</label>
            <select id="nationality_code" name="nationality_code" class="mt-1 block w-full px-3 py-2 border border-slate-300 rounded-md shadow-sm">
                <option value="">—</option>
                <option value="FR">Français</option>
                <option value="US">Américain</option>
            </select>
        </div>
        <div>
            <label for="professional_category_code" class="block text-sm font-medium text-slate-700">Catégorie de personnel</label>
            <select id="professional_category_code" name="professional_category_code" class="mt-1 block w-full px-3 py-2 border border-slate-300 rounded-md shadow-sm">
                <option value="">—</option>
                <?php foreach ($gradeCategories as $c): ?>
                <option value="<?= htmlspecialchars($c['code']) ?>"><?= htmlspecialchars($c['label']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label for="grade_id" class="block text-sm font-medium text-slate-700">Grade</label>
            <select id="grade_id" name="grade_id" class="mt-1 block w-full px-3 py-2 border border-slate-300 rounded-md shadow-sm">
                <option value="">—</option>
                <?php foreach ($grades as $g): ?>
                <option value="<?= (int) $g['id'] ?>"><?= htmlspecialchars($g['label_long'] ?? $g['name'] ?? '') ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label for="preferred_grade_format" class="block text-sm font-medium text-slate-700">Format d'affichage du grade</label>
            <select id="preferred_grade_format" name="preferred_grade_format" class="mt-1 block w-full px-3 py-2 border border-slate-300 rounded-md shadow-sm">
                <option value="classic">Classique (texte)</option>
                <option value="otan">OTAN</option>
                <option value="hybrid">Hybride (ex. Capitaine (OF-2))</option>
            </select>
        </div>
        <div>
            <label for="status" class="block text-sm font-medium text-slate-700">Statut</label>
            <select id="status" name="status" class="mt-1 block w-full px-3 py-2 border border-slate-300 rounded-md shadow-sm">
                <option value="pending">En attente</option>
                <option value="active">Actif</option>
                <option value="inactive">Inactif</option>
            </select>
        </div>
        <div class="flex gap-3 pt-4">
            <button type="submit" class="px-4 py-2 bg-slate-900 text-white text-sm font-semibold rounded hover:bg-slate-800">Créer</button>
            <a href="<?= url('back-office/users') ?>" class="px-4 py-2 text-slate-600 text-sm hover:underline">Annuler</a>
        </div>
    </form>
</div>
