<?php
declare(strict_types=1);
/**
 * Liens vers les modules encore sous /admin/* mais scopés au tenant de session.
 * Affiché sur le dashboard plateforme pour clarifier Plateforme vs communauté courante.
 */
$gate = \App\Core\Gate::getInstance();
$sys = $gate->allows('admin.system');
$org = $gate->allows('admin.organization') || $gate->allows('admin.access');
$training = $gate->allows('training.manage') || $gate->allows('training.assign') || $gate->allows('admin.access');
$docs = $gate->allows('documents.upload') || $gate->allows('admin.access');

$links = [];
if ($sys || $org) {
    $links[] = ['href' => url('back-office'), 'label' => 'Back-office organisation', 'desc' => 'Pilotage, membres, unités, configuration'];
}
if ($sys || $org) {
    $links[] = ['href' => url('admin/modpacks'), 'label' => 'Modpacks', 'desc' => 'Packs mods de la communauté courante'];
    $links[] = ['href' => url('admin/atak-config'), 'label' => 'Configuration ATAK / Tacmap', 'desc' => 'Réglages carte pour ce tenant'];
    $links[] = ['href' => url('admin/atak-mod'), 'label' => 'Mod ATAK (Overwatch)', 'desc' => 'Fichiers mod côté communauté'];
    $links[] = ['href' => url('admin/forum-config'), 'label' => 'Configuration forum', 'desc' => 'Catégories, mots interdits, domaines'];
}
if ($sys || $training) {
    $links[] = ['href' => url('admin/training'), 'label' => 'Formations (LMS)', 'desc' => 'Catalogue et suivi pour ce tenant'];
}
if ($sys || $docs) {
    $links[] = ['href' => url('documents/gestion'), 'label' => 'Gestion documentaire', 'desc' => 'Documents de la communauté courante'];
}

if ($links === []) {
    return;
}
?>
<div class="rounded-xl border border-emerald-200/90 bg-emerald-50/40 p-5 mb-8">
    <h2 class="text-lg font-bold text-slate-900 mb-1">Modules communauté (session actuelle)</h2>
    <p class="text-sm text-slate-600 mb-4">Ces outils s’appliquent à la <strong class="font-semibold text-slate-800">communauté sélectionnée</strong> dans votre session, même si l’URL commence par <code class="text-xs bg-white/80 px-1 rounded">/admin/…</code>.</p>
    <ul class="space-y-2">
        <?php foreach ($links as $row): ?>
            <li>
                <a href="<?= htmlspecialchars($row['href'], ENT_QUOTES, 'UTF-8') ?>" class="group block rounded-lg border border-transparent px-0 py-1 hover:border-emerald-200/80 hover:bg-white/60">
                    <span class="font-medium text-emerald-950 group-hover:underline"><?= htmlspecialchars($row['label'], ENT_QUOTES, 'UTF-8') ?></span>
                    <?php if (($row['desc'] ?? '') !== ''): ?>
                        <span class="block text-xs text-slate-500 mt-0.5"><?= htmlspecialchars((string) $row['desc'], ENT_QUOTES, 'UTF-8') ?></span>
                    <?php endif; ?>
                </a>
            </li>
        <?php endforeach; ?>
    </ul>
</div>
