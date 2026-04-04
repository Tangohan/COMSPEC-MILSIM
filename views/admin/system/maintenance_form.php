<?php

declare(strict_types=1);

$r = $maintenanceRule;
$row = $r ?? [];
$isEdit = $r !== null;
$action = $formAction ?? url('admin/system/maintenance');
$dt = static function (?string $v): string {
    if ($v === null || $v === '') {
        return '';
    }

    return htmlspecialchars(str_replace(' ', 'T', substr($v, 0, 16)), ENT_QUOTES, 'UTF-8');
};
?>
<div class="max-w-3xl mx-auto px-6 py-12">
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-black text-slate-900"><?= $isEdit ? 'Modifier la règle' : 'Nouvelle règle' ?></h1>
        <a href="<?= url('admin/system/maintenance') ?>" class="text-sm text-slate-600 hover:underline">Retour</a>
    </div>

    <form method="post" action="<?= htmlspecialchars($action, ENT_QUOTES, 'UTF-8') ?>" class="space-y-6 rounded-2xl border border-slate-200 bg-white p-8 shadow-sm">
        <?= \App\Core\Csrf::field() ?>

        <div>
            <label class="block text-xs font-bold uppercase tracking-wider text-slate-500 mb-1">Scope</label>
            <input type="text" name="scope" required class="w-full border border-slate-200 rounded-lg px-3 py-2"
                   value="<?= htmlspecialchars((string) ($row['scope'] ?? 'global'), ENT_QUOTES, 'UTF-8') ?>"
                   placeholder="global | route:/forum | module:forum">
            <p class="mt-1 text-xs text-slate-500">Ex. <code class="bg-slate-100 px-1 rounded">global</code>, <code class="bg-slate-100 px-1 rounded">route:/documents/gestion</code>, <code class="bg-slate-100 px-1 rounded">module:atak</code></p>
        </div>

        <div class="flex items-center gap-2">
            <input type="checkbox" name="is_enabled" value="1" id="is_enabled" <?= ($r !== null && !empty($r['is_enabled'])) ? 'checked' : '' ?>>
            <label for="is_enabled" class="text-sm font-medium">Activée</label>
        </div>

        <div>
            <label class="block text-xs font-bold uppercase tracking-wider text-slate-500 mb-1">Titre</label>
            <input type="text" name="title" required class="w-full border border-slate-200 rounded-lg px-3 py-2"
                   value="<?= htmlspecialchars((string) ($row['title'] ?? 'Maintenance en cours'), ENT_QUOTES, 'UTF-8') ?>">
        </div>

        <div>
            <label class="block text-xs font-bold uppercase tracking-wider text-slate-500 mb-1">Message public</label>
            <textarea name="message" rows="4" class="w-full border border-slate-200 rounded-lg px-3 py-2"><?= htmlspecialchars((string) ($row['message'] ?? ''), ENT_QUOTES, 'UTF-8') ?></textarea>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="block text-xs font-bold uppercase tracking-wider text-slate-500 mb-1">Début (optionnel)</label>
                <input type="datetime-local" name="starts_at" class="w-full border border-slate-200 rounded-lg px-3 py-2" value="<?= $dt($isEdit ? ($r['starts_at'] ?? null) : null) ?>">
            </div>
            <div>
                <label class="block text-xs font-bold uppercase tracking-wider text-slate-500 mb-1">Fin (optionnel)</label>
                <input type="datetime-local" name="ends_at" class="w-full border border-slate-200 rounded-lg px-3 py-2" value="<?= $dt($isEdit ? ($r['ends_at'] ?? null) : null) ?>">
            </div>
        </div>

        <div>
            <label class="block text-xs font-bold uppercase tracking-wider text-slate-500 mb-1">Code maintenance</label>
            <input type="text" name="maintenance_code" class="w-full border border-slate-200 rounded-lg px-3 py-2"
                   value="<?= htmlspecialchars((string) ($row['maintenance_code'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
        </div>

        <div class="flex items-center gap-2">
            <input type="checkbox" name="allow_admin_bypass" value="1" id="allow_admin_bypass" <?= ((int) ($row['allow_admin_bypass'] ?? 1)) === 1 ? 'checked' : '' ?>>
            <label for="allow_admin_bypass" class="text-sm font-medium">Contourner pour les super-admins / admins (permissions admin.system, admin.access) et rôles listés ci-dessous</label>
        </div>

        <div>
            <label class="block text-xs font-bold uppercase tracking-wider text-slate-500 mb-1">IP autorisées (CSV)</label>
            <input type="text" name="allowed_ips" class="w-full border border-slate-200 rounded-lg px-3 py-2 font-mono text-sm"
                   value="<?= htmlspecialchars((string) ($row['allowed_ips'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                   placeholder="127.0.0.1,::1">
        </div>

        <div>
            <label class="block text-xs font-bold uppercase tracking-wider text-slate-500 mb-1">Slugs de rôles autorisés (CSV, optionnel)</label>
            <input type="text" name="allowed_roles" class="w-full border border-slate-200 rounded-lg px-3 py-2 font-mono text-sm"
                   value="<?= htmlspecialchars((string) ($row['allowed_roles'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                   placeholder="instructor, moderator">
            <p class="mt-1 text-xs text-slate-500">Correspond au slug du rôle tenant de l’utilisateur.</p>
        </div>

        <div>
            <label class="block text-xs font-bold uppercase tracking-wider text-slate-500 mb-1">URL de redirection (si statut 301–308)</label>
            <input type="text" name="redirect_url" class="w-full border border-slate-200 rounded-lg px-3 py-2"
                   value="<?= htmlspecialchars((string) ($row['redirect_url'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
        </div>

        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block text-xs font-bold uppercase tracking-wider text-slate-500 mb-1">HTTP status</label>
                <input type="number" name="http_status" min="100" max="599" class="w-full border border-slate-200 rounded-lg px-3 py-2"
                       value="<?= (int) ($row['http_status'] ?? 503) ?>">
            </div>
            <div>
                <label class="block text-xs font-bold uppercase tracking-wider text-slate-500 mb-1">Priorité</label>
                <input type="number" name="priority" class="w-full border border-slate-200 rounded-lg px-3 py-2"
                       value="<?= (int) ($row['priority'] ?? 100) ?>">
            </div>
        </div>

        <div class="flex justify-end gap-3">
            <a href="<?= url('admin/system/maintenance') ?>" class="px-4 py-2 text-slate-600 hover:underline">Annuler</a>
            <button type="submit" class="px-6 py-2 rounded-lg bg-slate-900 text-white font-semibold hover:bg-slate-800"><?= $isEdit ? 'Enregistrer' : 'Créer' ?></button>
        </div>
    </form>
</div>
