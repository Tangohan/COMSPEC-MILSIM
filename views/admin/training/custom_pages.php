<?php
declare(strict_types=1);
$rows = is_array($customPagesRows ?? null) ? $customPagesRows : [];
?>
<section class="tc-panel p-6 md:p-8">
    <p class="tc-kicker">Pilotage des formations</p>
    <h1 class="tc-hero-title mb-3">Documentations HTML</h1>
    <div class="text-sm text-slate-600 max-w-3xl leading-relaxed space-y-3 mb-6">
        <p>
            Créez des <strong class="font-semibold text-slate-800">pages autonomes</strong> (livrets, guides, notices) en complément des parcours LMS et du module Documents. Chaque page publiée est visible par les membres connectés sur une URL dédiée du catalogue formations.
        </p>
        <p class="text-slate-500 text-xs">
            L’éditeur propose un <strong class="text-slate-700">aperçu en direct</strong>, un mode lecture « feuillet » pour contrôler la présentation, et la détection automatique d’une page complète ou d’un extrait (en-tête complété sur le site public si besoin).
        </p>
    </div>
    <a href="<?= htmlspecialchars(training_lms_admin_url('pages-html/nouvelle')) ?>" class="tc-btn-primary tc-btn-emerald text-sm">Nouvelle documentation</a>
</section>

<section class="rounded-2xl border border-slate-200 bg-white overflow-hidden">
    <div class="overflow-x-auto">
        <table class="min-w-full text-sm">
            <thead>
            <tr class="text-left border-b border-slate-200 bg-slate-50">
                <th class="py-3 px-4">Titre</th>
                <th class="py-3 px-4">URL</th>
                <th class="py-3 px-4">État</th>
                <th class="py-3 px-4">Mise à jour</th>
                <th class="py-3 px-4">Actions</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($rows as $r):
                $sid = (int) ($r['id'] ?? 0);
                $slug = (string) ($r['slug'] ?? '');
                $pub = !empty($r['is_published']);
                $viewUrl = url('formations/page/' . rawurlencode($slug));
                ?>
            <tr class="border-b border-slate-100">
                <td class="py-3 px-4 font-medium text-slate-900"><?= htmlspecialchars((string) ($r['title'] ?? '')) ?></td>
                <td class="py-3 px-4 font-mono text-xs"><code class="bg-slate-100 px-1 rounded">/formations/page/<?= htmlspecialchars($slug) ?></code></td>
                <td class="py-3 px-4"><?= $pub ? '<span class="text-emerald-700 font-semibold">Publié</span>' : '<span class="text-slate-500">Brouillon</span>' ?></td>
                <td class="py-3 px-4 text-slate-600"><?= !empty($r['updated_at']) ? htmlspecialchars(date('d/m/Y H:i', strtotime((string) $r['updated_at']))) : '—' ?></td>
                <td class="py-3 px-4 space-x-2 whitespace-nowrap">
                    <?php if ($pub): ?>
                    <a href="<?= htmlspecialchars($viewUrl) ?>" target="_blank" rel="noopener noreferrer" class="text-emerald-700 font-semibold hover:underline">Voir</a>
                    <?php endif; ?>
                    <a href="<?= htmlspecialchars(training_lms_admin_url('pages-html/' . $sid . '/modifier')) ?>" class="text-slate-800 font-semibold hover:underline">Modifier</a>
                    <form method="post" action="<?= htmlspecialchars(training_lms_admin_url('pages-html/' . $sid . '/supprimer')) ?>" class="inline" data-ui-confirm="1" data-ui-confirm-title="Supprimer la page" data-ui-confirm-body="Cette documentation HTML sera définitivement supprimée.">
                        <?= \App\Core\Csrf::field() ?>
                        <button type="submit" class="text-rose-700 font-semibold hover:underline text-sm bg-transparent border-0 cursor-pointer p-0">Supprimer</button>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php if ($rows === []): ?>
            <tr><td colspan="5" class="py-10 px-4 text-center text-slate-600">Aucune documentation HTML pour l’instant. Créez une première page pour vos livrets ou guides.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
