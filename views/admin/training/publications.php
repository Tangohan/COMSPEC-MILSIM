<?php
declare(strict_types=1);
$rows = is_array($publicationRows ?? null) ? $publicationRows : [];
?>
<section class="tc-panel p-6 md:p-8">
    <p class="tc-kicker">Back-office formation</p>
    <h1 class="tc-hero-title mb-2">TrainingPublicationEngine</h1>
    <p class="text-sm text-slate-600">Chemin UI : <code>/formation/publications</code> (alias back-office disponible).</p>
</section>

<section class="rounded-2xl border border-slate-200 bg-white p-6 mt-6">
    <h2 class="text-sm font-black uppercase tracking-[0.2em] text-slate-600 mb-4">Versionning &amp; change log</h2>
    <div class="overflow-auto">
        <table class="min-w-full text-sm">
            <thead>
            <tr class="text-left border-b border-slate-200">
                <th class="py-2 pr-4">ID</th>
                <th class="py-2 pr-4">Cours</th>
                <th class="py-2 pr-4">Statut</th>
                <th class="py-2 pr-4">Version</th>
                <th class="py-2 pr-4">Conformité</th>
                <th class="py-2 pr-4">MAJ</th>
                <th class="py-2 pr-4">Actions</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($rows as $row): ?>
                <tr class="border-b border-slate-100">
                    <td class="py-2 pr-4 font-mono">#<?= (int) ($row['id'] ?? 0) ?></td>
                    <td class="py-2 pr-4"><?= (int) ($row['course_id'] ?? 0) ?></td>
                    <td class="py-2 pr-4"><?= htmlspecialchars((string) ($row['status'] ?? 'draft')) ?></td>
                    <td class="py-2 pr-4"><?= htmlspecialchars((string) ($row['version_label'] ?? 'v1')) ?></td>
                    <td class="py-2 pr-4"><?= (int) ($row['compliance_score'] ?? 0) ?>%</td>
                    <td class="py-2 pr-4"><?= htmlspecialchars((string) ($row['updated_at'] ?? '')) ?></td>
                    <td class="py-2 pr-4">
                        <a class="text-emerald-700 font-semibold" href="<?= htmlspecialchars(training_lms_admin_url('publications/' . (int) ($row['id'] ?? 0) . '/changelog')) ?>">Change log</a>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if ($rows === []): ?>
                <tr><td colspan="7" class="py-4 text-slate-500">Aucune publication.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
