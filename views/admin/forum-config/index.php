<?php
$categories = $categories ?? [];
$forumConfig = $forumConfig ?? [];
$bannedWords = $bannedWords ?? [];
$blacklistedDomains = $blacklistedDomains ?? [];
$baseUrl = url('');
$csrf = \App\Core\Csrf::field();
$csrfToken = \App\Core\Csrf::token();
$success = \App\Core\Session::getFlash('success');
$error = \App\Core\Session::getFlash('error');
$forumName = $forumConfig['name'] ?? 'Chambre des Murmures';
?>
<div class="max-w-5xl mx-auto px-6 py-12" x-data="forumConfigPage()">
    <div class="flex items-center justify-between mb-8">
        <div>
            <h1 class="text-2xl font-black text-slate-900"><?= htmlspecialchars($forumName) ?></h1>
            <p class="mt-1 text-slate-600">Configuration des catégories, paramètres, modération et mots bannis.</p>
        </div>
        <a href="<?= $baseUrl ?>/forum" class="text-slate-600 hover:text-slate-900 text-sm font-medium">Voir le forum →</a>
    </div>

    <?php if ($success): ?>
    <p class="mb-4 text-sm text-emerald-600"><?= htmlspecialchars($success) ?></p>
    <?php endif; ?>
    <?php if ($error): ?>
    <p class="mb-4 text-sm text-red-600"><?= htmlspecialchars($error) ?></p>
    <?php endif; ?>

    <!-- Section 1 : Catégories -->
    <section class="mb-10">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-lg font-bold text-slate-900">Gestion des catégories</h2>
            <button type="button" @click="fcOpenCreate()" class="px-3 py-1.5 bg-slate-900 text-white text-sm font-medium rounded hover:bg-slate-800">Créer une catégorie</button>
        </div>
        <div class="bg-white border border-slate-200 rounded-xl overflow-hidden">
            <?php if (empty($categories)): ?>
            <p class="p-6 text-slate-500">Aucune catégorie. Cliquez sur « Créer une catégorie » pour commencer.</p>
            <?php else: ?>
            <table class="w-full">
                <thead class="bg-slate-50 border-b border-slate-200">
                    <tr>
                        <th class="text-left p-3 text-xs font-semibold text-slate-600 uppercase">Ordre</th>
                        <th class="text-left p-3 text-xs font-semibold text-slate-600 uppercase">Nom</th>
                        <th class="text-left p-3 text-xs font-semibold text-slate-600 uppercase">Slug</th>
                        <th class="text-left p-3 text-xs font-semibold text-slate-600 uppercase">Verrouillé</th>
                        <th class="text-left p-3 text-xs font-semibold text-slate-600 uppercase">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($categories as $cat): ?>
                    <tr class="border-b border-slate-100 hover:bg-slate-50">
                        <td class="p-3"><?= (int)($cat['display_order'] ?? 0) ?></td>
                        <td class="p-3 font-medium"><?= htmlspecialchars($cat['name'] ?? '') ?></td>
                        <td class="p-3 text-slate-500"><?= htmlspecialchars($cat['slug'] ?? '') ?></td>
                        <td class="p-3"><?= !empty($cat['is_locked']) ? 'Oui' : 'Non' ?></td>
                        <td class="p-3 flex flex-wrap gap-2">
                            <button type="button" @click='fcOpenEdit(<?= json_encode($cat) ?>)' class="text-slate-600 hover:text-slate-900 text-sm underline">Éditer</button>
                            <button type="button" @click="fcLock(<?= (int)($cat['id']) ?>, <?= !empty($cat['is_locked']) ? 'false' : 'true' ?>)" class="text-slate-600 hover:text-slate-900 text-sm underline"><?= !empty($cat['is_locked']) ? 'Déverrouiller' : 'Verrouiller' ?></button>
                            <button type="button" @click="fcDelete(<?= (int)($cat['id']) ?>)" class="text-rose-600 hover:text-rose-800 text-sm underline">Supprimer</button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
    </section>

    <!-- Section 2 : Paramètres globaux (repliables) -->
    <section class="mb-10">
        <h2 class="text-lg font-bold text-slate-900 mb-4">Paramètres globaux</h2>
        <div class="space-y-2">
            <?php
            $paramGroups = [
                'Apparence' => ['forum_hero_image_url'],
                'Accès' => ['forum_enabled', 'forum_guest_read'],
                'Libellés rôles' => ['forum_role_read_label', 'forum_role_write_label'],
                'Limites & cooldowns' => ['forum_topics_per_page', 'forum_posts_per_page', 'forum_cooldown_seconds'],
                'Anti-spam' => ['forum_antispam_enabled', 'forum_antispam_min_length'],
                'Sandbox & bot' => ['forum_sandbox_enabled', 'forum_bot_enabled'],
                'Pièces jointes' => ['forum_attachments_max_size', 'forum_attachments_allowed_ext'],
                'URL Gate' => ['forum_url_gate_enabled'],
                'Notifications' => ['forum_notify_moderators'],
                'Modération' => ['forum_moderation_tutorial_html'],
            ];
            foreach ($paramGroups as $groupLabel => $keys):
            ?>
            <div class="border border-slate-200 rounded-lg overflow-hidden" x-data="{ open: false }">
                <button type="button" @click="open = !open" class="w-full px-4 py-3 bg-slate-50 text-left text-sm font-semibold text-slate-800 flex items-center justify-between">
                    <?= htmlspecialchars($groupLabel) ?>
                    <span x-text="open ? '−' : '+'"></span>
                </button>
                <div x-show="open" class="border-t border-slate-200">
                    <div class="p-4 space-y-3 bg-white">
                        <?php foreach ($keys as $key): ?>
                        <div>
                            <label class="block text-xs font-medium text-slate-600 mb-1"><?= htmlspecialchars($key) ?></label>
                            <input type="text" class="fc-input w-full border border-slate-200 rounded px-3 py-2 text-sm" name="<?= htmlspecialchars($key) ?>" data-key="<?= htmlspecialchars($key) ?>" value="<?= htmlspecialchars($forumConfig[$key] ?? '') ?>" placeholder="">
                            <?php if ($key === 'forum_hero_image_url'): ?>
                            <p class="mt-1.5 text-xs text-slate-500 leading-relaxed">Image d’en-tête du forum : URL HTTPS absolue ou chemin commençant par <code class="text-[11px] bg-slate-100 px-1 rounded">/</code> (ex. assets servis par le site). Recommandé : large bannière (~1600×400&nbsp;px), WebP ou JPEG, fichier léger pour de bonnes performances.</p>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <div class="mt-4">
            <button type="button" @click="saveForumSettings()" class="px-4 py-2 bg-slate-900 text-white text-sm font-semibold rounded hover:bg-slate-800">Enregistrer les paramètres</button>
        </div>
    </section>

    <!-- Section 3 : Gains XP (lecture seule) -->
    <section class="mb-10">
        <h2 class="text-lg font-bold text-slate-900 mb-4">Gains XP</h2>
        <div class="bg-slate-50 border border-slate-200 rounded-lg p-4 text-sm text-slate-600">
            <p>Configuration en lecture seule (gérée par le module XP).</p>
            <p class="mt-2">Création de sujet : <?= htmlspecialchars($forumConfig['xp_topic'] ?? '—') ?> XP · Réponse : <?= htmlspecialchars($forumConfig['xp_reply'] ?? '—') ?> XP</p>
        </div>
    </section>

    <!-- Section 4 : Mots bannis -->
    <section class="mb-10">
        <h2 class="text-lg font-bold text-slate-900 mb-4">Mots bannis</h2>
        <div class="flex gap-2 mb-3">
            <input type="text" x-model="bannedWordInput" placeholder="Mot ou expression" class="fc-input flex-1 border border-slate-200 rounded px-3 py-2 text-sm">
            <button type="button" @click="bwAdd()" class="px-3 py-2 bg-slate-900 text-white text-sm font-medium rounded hover:bg-slate-800">Ajouter</button>
        </div>
        <ul class="bg-white border border-slate-200 rounded-lg divide-y divide-slate-100">
            <?php foreach ($bannedWords as $w): ?>
            <li class="flex items-center justify-between px-4 py-2">
                <span><?= htmlspecialchars(is_array($w) ? ($w['word'] ?? $w) : $w) ?></span>
                <button type="button" @click="bwDelete(<?= is_array($w) ? (int)($w['id'] ?? 0) : 0 ?>)" class="text-rose-600 hover:text-rose-800 text-sm">Supprimer</button>
            </li>
            <?php endforeach; ?>
            <?php if (empty($bannedWords)): ?>
            <li class="px-4 py-4 text-slate-500 text-sm">Aucun mot banni.</li>
            <?php endif; ?>
        </ul>
    </section>

    <!-- Section 5 : Domaines blacklistés -->
    <section class="mb-10">
        <h2 class="text-lg font-bold text-slate-900 mb-4">Domaines blacklistés</h2>
        <div class="flex gap-2 mb-3">
            <input type="text" x-model="blacklistedDomainInput" placeholder="exemple.com" class="fc-input flex-1 border border-slate-200 rounded px-3 py-2 text-sm">
            <button type="button" @click="bdAdd()" class="px-3 py-2 bg-slate-900 text-white text-sm font-medium rounded hover:bg-slate-800">Ajouter</button>
        </div>
        <ul class="bg-white border border-slate-200 rounded-lg divide-y divide-slate-100">
            <?php foreach ($blacklistedDomains as $d): ?>
            <li class="flex items-center justify-between px-4 py-2">
                <span><?= htmlspecialchars(is_array($d) ? ($d['domain'] ?? $d) : $d) ?></span>
                <button type="button" @click="bdDelete(<?= is_array($d) ? (int)($d['id'] ?? 0) : 0 ?>)" class="text-rose-600 hover:text-rose-800 text-sm">Supprimer</button>
            </li>
            <?php endforeach; ?>
            <?php if (empty($blacklistedDomains)): ?>
            <li class="px-4 py-4 text-slate-500 text-sm">Aucun domaine blacklisté.</li>
            <?php endif; ?>
        </ul>
    </section>

    <!-- Section 6 : File sandbox -->
    <section class="mb-10">
        <h2 class="text-lg font-bold text-slate-900 mb-4">File sandbox (messages en attente)</h2>
        <div class="bg-slate-50 border border-slate-200 rounded-lg p-4 text-sm text-slate-600">
            Aucun message en attente.
        </div>
    </section>

    <!-- Section 7 : Statut bot -->
    <section class="mb-10">
        <h2 class="text-lg font-bold text-slate-900 mb-4">Statut bot de modération</h2>
        <div class="flex gap-2">
            <button type="button" @click="botSelfTest()" class="px-3 py-2 border border-slate-200 text-slate-700 text-sm rounded hover:bg-slate-50">Test bot</button>
            <button type="button" @click="botPreview()" class="px-3 py-2 border border-slate-200 text-slate-700 text-sm rounded hover:bg-slate-50">Aperçu</button>
        </div>
        <p class="mt-2 text-sm text-slate-500">Bot actif (placeholder).</p>
    </section>

    <!-- Section 8 : Liens -->
    <section class="mb-10">
        <h2 class="text-lg font-bold text-slate-900 mb-4">Liens</h2>
        <div class="flex flex-wrap gap-3">
            <a href="<?= $baseUrl ?>/forum/moderation" class="px-3 py-2 border border-slate-200 text-slate-700 text-sm rounded hover:bg-slate-50">Modération forum</a>
            <a href="<?= $baseUrl ?>/forum" class="px-3 py-2 border border-slate-200 text-slate-700 text-sm rounded hover:bg-slate-50">Voir le forum</a>
            <a href="<?= $baseUrl ?>/admin" class="px-3 py-2 border border-slate-200 text-slate-700 text-sm rounded hover:bg-slate-50">Administration</a>
        </div>
    </section>

    <!-- Modale création / édition catégorie -->
    <div x-show="fcModalOpen" x-cloak class="fc-modal-overlay fixed inset-0 z-50 flex items-center justify-center bg-black/50" @click.self="fcModalOpen = false">
        <div class="bg-white rounded-xl shadow-xl max-w-md w-full mx-4 p-6" @click.stop>
            <h3 class="text-lg font-bold text-slate-900 mb-4" x-text="fcEditId ? 'Modifier la catégorie' : 'Nouvelle catégorie'"></h3>
            <form @submit.prevent="fcSubmitForm()">
                <input type="hidden" name="id" x-model="fcEditId">
                <div class="space-y-3">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Nom</label>
                        <input type="text" class="fc-input w-full border border-slate-200 rounded px-3 py-2" name="name" x-model="fcForm.name" required>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Slug</label>
                        <input type="text" class="fc-input w-full border border-slate-200 rounded px-3 py-2" name="slug" x-model="fcForm.slug">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Description</label>
                        <textarea class="fc-input w-full border border-slate-200 rounded px-3 py-2" name="description" x-model="fcForm.description" rows="2"></textarea>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Ordre d'affichage</label>
                        <input type="number" class="fc-input w-full border border-slate-200 rounded px-3 py-2" name="display_order" x-model="fcForm.display_order" min="0">
                    </div>
                </div>
                <div class="mt-6 flex gap-2">
                    <button type="submit" class="px-4 py-2 bg-slate-900 text-white text-sm font-semibold rounded hover:bg-slate-800">Enregistrer</button>
                    <button type="button" @click="fcModalOpen = false" class="px-4 py-2 border border-slate-200 text-slate-700 text-sm rounded hover:bg-slate-50">Annuler</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function forumConfigPage() {
    const BASE = <?= json_encode($baseUrl) ?>;
    const CSRF = <?= json_encode($csrfToken) ?>;
    return {
        fcModalOpen: false,
        fcEditId: null,
        fcForm: { name: '', slug: '', description: '', display_order: 0 },
        bannedWordInput: '',
        blacklistedDomainInput: '',
        async fcOpenCreate() {
            this.fcEditId = null;
            this.fcForm = { name: '', slug: '', description: '', display_order: 0 };
            this.fcModalOpen = true;
        },
        fcOpenEdit(cat) {
            this.fcEditId = cat.id;
            this.fcForm = { name: cat.name || '', slug: cat.slug || '', description: cat.description || '', display_order: cat.display_order ?? 0 };
            this.fcModalOpen = true;
        },
        async fcSubmitForm() {
            const action = this.fcEditId ? 'update' : 'create';
            const body = new FormData();
            body.append('_csrf_token', CSRF);
            body.append('action', action);
            if (this.fcEditId) body.append('id', this.fcEditId);
            body.append('name', this.fcForm.name);
            body.append('slug', this.fcForm.slug || this.fcForm.name.toLowerCase().replace(/\s+/g, '-'));
            body.append('description', this.fcForm.description);
            body.append('display_order', this.fcForm.display_order);
            try {
                const r = await fetch(BASE + '/api/admin/forum-categories', { method: 'POST', body });
                const j = await r.json().catch(() => ({}));
                if (r.ok && (j.success || j.ok)) { this.fcModalOpen = false; location.reload(); return; }
                alert(j.message || 'Erreur lors de l\'enregistrement.');
            } catch (e) { alert('Erreur réseau.'); }
        },
        async fcLock(id, locked) {
            const body = new FormData();
            body.append('_csrf_token', CSRF);
            body.append('action', 'lock');
            body.append('id', id);
            body.append('locked', locked ? '1' : '0');
            try {
                const r = await fetch(BASE + '/api/admin/forum-categories', { method: 'POST', body });
                const j = await r.json().catch(() => ({}));
                if (r.ok && (j.success || j.ok)) { location.reload(); return; }
                alert(j.message || 'Erreur.'); } catch (e) { alert('Erreur réseau.'); }
        },
        async fcDelete(id) {
            if (!confirm('Supprimer cette catégorie ?')) return;
            const body = new FormData();
            body.append('_csrf_token', CSRF);
            body.append('action', 'delete');
            body.append('id', id);
            try {
                const r = await fetch(BASE + '/api/admin/forum-categories', { method: 'POST', body });
                const j = await r.json().catch(() => ({}));
                if (r.ok && (j.success || j.ok)) { location.reload(); return; }
                alert(j.message || 'Erreur.'); } catch (e) { alert('Erreur réseau.'); }
        },
        async saveForumSettings() {
            const settings = {};
            document.querySelectorAll('.fc-input[data-key]').forEach(el => { settings[el.dataset.key] = el.value; });
            const body = new FormData();
            body.append('_csrf_token', CSRF);
            body.append('settings', JSON.stringify(settings));
            try {
                const r = await fetch(BASE + '/api/admin/site-settings', { method: 'POST', body });
                const j = await r.json().catch(() => ({}));
                if (r.ok && (j.success || j.ok)) { alert('Paramètres enregistrés.'); return; }
                alert(j.message || 'Erreur.'); } catch (e) { alert('Erreur réseau.'); }
        },
        async bwAdd() {
            const word = this.bannedWordInput.trim();
            if (!word) return;
            const body = new FormData();
            body.append('_csrf_token', CSRF);
            body.append('action', 'add_banned_word');
            body.append('word', word);
            try {
                const r = await fetch(BASE + '/api/back-office/forum-moderation', { method: 'POST', body });
                const j = await r.json().catch(() => ({}));
                if (r.ok && (j.success || j.ok)) { this.bannedWordInput = ''; location.reload(); return; }
                alert(j.message || 'Erreur.'); } catch (e) { alert('Erreur réseau.'); }
        },
        async bwDelete(id) {
            if (!id) return;
            const body = new FormData();
            body.append('_csrf_token', CSRF);
            body.append('action', 'delete_banned_word');
            body.append('id', id);
            try {
                const r = await fetch(BASE + '/api/back-office/forum-moderation', { method: 'POST', body });
                const j = await r.json().catch(() => ({}));
                if (r.ok && (j.success || j.ok)) { location.reload(); return; }
                alert(j.message || 'Erreur.'); } catch (e) { alert('Erreur réseau.'); }
        },
        async bdAdd() {
            const domain = this.blacklistedDomainInput.trim();
            if (!domain) return;
            const body = new FormData();
            body.append('_csrf_token', CSRF);
            body.append('action', 'add_blacklisted_domain');
            body.append('domain', domain);
            try {
                const r = await fetch(BASE + '/api/back-office/forum-moderation', { method: 'POST', body });
                const j = await r.json().catch(() => ({}));
                if (r.ok && (j.success || j.ok)) { this.blacklistedDomainInput = ''; location.reload(); return; }
                alert(j.message || 'Erreur.'); } catch (e) { alert('Erreur réseau.'); }
        },
        async bdDelete(id) {
            if (!id) return;
            const body = new FormData();
            body.append('_csrf_token', CSRF);
            body.append('action', 'delete_blacklisted_domain');
            body.append('id', id);
            try {
                const r = await fetch(BASE + '/api/back-office/forum-moderation', { method: 'POST', body });
                const j = await r.json().catch(() => ({}));
                if (r.ok && (j.success || j.ok)) { location.reload(); return; }
                alert(j.message || 'Erreur.'); } catch (e) { alert('Erreur réseau.'); }
        },
        async botSelfTest() { const b = new FormData(); b.append('_csrf_token', CSRF); b.append('action', 'bot_self_test'); const r = await fetch(BASE + '/api/back-office/forum-moderation', { method: 'POST', body: b }); const j = await r.json().catch(() => ({})); alert(j.message || (r.ok ? 'Test envoyé.' : 'Erreur.')); },
        async botPreview() { const b = new FormData(); b.append('_csrf_token', CSRF); b.append('action', 'bot_preview'); const r = await fetch(BASE + '/api/back-office/forum-moderation', { method: 'POST', body: b }); const j = await r.json().catch(() => ({})); alert(j.message || (r.ok ? 'Aperçu.' : 'Erreur.')); }
    };
}
</script>
