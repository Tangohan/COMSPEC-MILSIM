<?php
declare(strict_types=1);
$rows = is_array($publicationRows ?? null) ? $publicationRows : [];
$publicationCourses = is_array($publicationCourses ?? null) ? $publicationCourses : [];

$publicationStatutLibelle = static function (string $code): string {
    $c = strtolower(trim($code));

    return match ($c) {
        'draft' => 'Brouillon',
        'review' => 'En relecture',
        'validated' => 'Validé',
        'published' => 'Publié',
        'archived' => 'Archivé',
        default => $c !== '' ? $c : '—',
    };
};

$publicationDateMaj = static function (?string $raw): string {
    if ($raw === null || trim($raw) === '') {
        return '—';
    }
    $t = strtotime($raw);

    return $t ? date('d/m/Y, H:i', $t) : '—';
};
?>
<section class="tc-panel p-6 md:p-8">
    <p class="tc-kicker">Pilotage des formations</p>
    <h1 class="tc-hero-title mb-3">Publications documentaires</h1>
    <div class="text-sm text-slate-600 space-y-3 max-w-3xl leading-relaxed">
        <p>Ici vous suivez les <strong class="font-semibold text-slate-800">versions publiées</strong> des supports liés à vos parcours&nbsp;: livrets, annexes et documents officiels synchronisés avec le <strong class="font-semibold text-slate-800">catalogue formations</strong>, le <strong class="font-semibold text-slate-800">module courrier</strong> et les <strong class="font-semibold text-slate-800">règles de diffusion</strong> de la communauté.</p>
        <p>Chaque ligne correspond à une <strong class="font-semibold text-slate-800">publication</strong> (état du cycle, libellé de version, indice de conformité). Le lien «&nbsp;Historique&nbsp;» ouvre le journal des révisions enregistrées pour cette publication.</p>
    </div>
</section>

<section class="rounded-2xl border border-slate-200 bg-white p-6 md:p-8 mt-6" aria-labelledby="pub-create-heading">
    <h2 id="pub-create-heading" class="text-sm font-black uppercase tracking-[0.2em] text-slate-600 mb-1">Création de contenu</h2>
    <p class="text-xs text-slate-500 mb-5 max-w-3xl leading-relaxed">Préparez les sources (parcours LMS, pièces en bibliothèque documents, gabarits courrier), puis ouvrez un <strong class="text-slate-700">brouillon de publication</strong> pour enchaîner compilation et validation. Les actions API (compiler, valider, publier) restent disponibles pour les intégrations techniques.</p>

    <div class="grid gap-6 lg:grid-cols-2">
        <div class="rounded-xl border border-slate-100 bg-slate-50/80 p-5">
            <h3 class="text-xs font-black uppercase tracking-wide text-slate-700 mb-3">Sources & édition</h3>
            <ul class="space-y-2 text-sm">
                <li><a href="<?= htmlspecialchars(training_studio_url()) ?>" class="font-semibold text-emerald-700 hover:underline">Studio LMS</a> — modules, leçons et structure du parcours.</li>
                <li><a href="<?= htmlspecialchars(training_lms_admin_url('courses')) ?>" class="font-semibold text-emerald-700 hover:underline">Catalogue (édition)</a> — visibilité, vitrine et métadonnées.</li>
                <li><a href="<?= htmlspecialchars(training_lms_admin_url('pages-html')) ?>" class="font-semibold text-emerald-700 hover:underline">Documentations HTML</a> — manuels publiables (page unique ou chapitres, sans quiz) sur <code class="text-xs bg-white px-1 rounded">/formations/page/…</code>.</li>
                <li><a href="<?= htmlspecialchars(url('documents/gestion/ajout')) ?>" class="font-semibold text-emerald-700 hover:underline">Ajouter un document</a> — bibliothèque communautaire (selon vos droits documents).</li>
                <li><a href="<?= htmlspecialchars(url('documents/gestion')) ?>" class="font-semibold text-emerald-700 hover:underline">Gestion documentaire</a> — versions et cycle de vie.</li>
                <li><a href="<?= htmlspecialchars(url('courrier/editor')) ?>" class="font-semibold text-emerald-700 hover:underline">Éditeur courrier</a> — gabarits institutionnels liés aux publications.</li>
            </ul>
        </div>

        <div class="rounded-xl border border-emerald-100 bg-emerald-50/40 p-5">
            <h3 class="text-xs font-black uppercase tracking-wide text-emerald-900 mb-3">Nouvelle publication (brouillon)</h3>
            <?php if ($publicationCourses === []): ?>
                <p class="text-sm text-slate-600">Aucun parcours n’est encore défini pour ce tenant. Créez d’abord une formation dans le <a href="<?= htmlspecialchars(training_studio_url()) ?>" class="font-semibold text-emerald-800 underline">studio</a>.</p>
            <?php else: ?>
            <form method="post" action="<?= htmlspecialchars(training_lms_admin_url('publications/brouillon')) ?>" class="space-y-4">
                <?= \App\Core\Csrf::field() ?>
                <div>
                    <label for="pub-course-id" class="block text-xs font-bold uppercase tracking-wide text-slate-600 mb-1">Parcours concerné</label>
                    <select name="course_id" id="pub-course-id" required class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-900">
                        <option value="">— Choisir un parcours —</option>
                        <?php foreach ($publicationCourses as $c):
                            $cid = (int) ($c['id'] ?? 0);
                            if ($cid < 1) {
                                continue;
                            }
                            $ct = trim((string) ($c['title'] ?? ''));
                            $vis = trim((string) ($c['visibility'] ?? ''));
                            ?>
                        <option value="<?= $cid ?>"><?= htmlspecialchars($ct !== '' ? $ct : 'Parcours #' . $cid) ?><?= $vis !== '' ? ' (' . htmlspecialchars($vis) . ')' : '' ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="pub-doc-ref" class="block text-xs font-bold uppercase tracking-wide text-slate-600 mb-1">Référence documentaire (optionnel)</label>
                    <input type="text" name="document_reference" id="pub-doc-ref" maxlength="120" placeholder="ex. LIVRET-2026-OPS-01" class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-900 placeholder:text-slate-400">
                    <p class="text-[11px] text-slate-500 mt-1">Sert d’identifiant métier sur la couverture et dans la chaîne de validation.</p>
                </div>
                <button type="submit" class="tc-btn-primary tc-btn-emerald text-sm">Créer le brouillon</button>
            </form>
            <?php endif; ?>
        </div>
    </div>
</section>

<section class="rounded-2xl border border-slate-200 bg-white p-6 mt-6">
    <h2 class="text-sm font-black uppercase tracking-[0.2em] text-slate-600 mb-1">Liste des publications</h2>
    <p class="text-xs text-slate-500 mb-4">Triées par dernière mise à jour. Les parcours sans publication n’apparaissent pas dans ce tableau.</p>
    <div class="overflow-auto">
        <table class="min-w-full text-sm">
            <thead>
            <tr class="text-left border-b border-slate-200">
                <th class="py-2 pr-4">Réf.</th>
                <th class="py-2 pr-4">Parcours</th>
                <th class="py-2 pr-4">État</th>
                <th class="py-2 pr-4">Version</th>
                <th class="py-2 pr-4">Conformité</th>
                <th class="py-2 pr-4">Dernière mise à jour</th>
                <th class="py-2 pr-4">Journal</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($rows as $row):
                $pid = (int) ($row['id'] ?? 0);
                $courseTitle = trim((string) ($row['course_title'] ?? ''));
                if ($courseTitle === '') {
                    $courseTitle = 'Parcours n°' . (int) ($row['course_id'] ?? 0) . ' (titre indisponible ou parcours retiré)';
                }
                $st = (string) ($row['status'] ?? '');
                ?>
                <tr class="border-b border-slate-100">
                    <td class="py-2 pr-4 font-mono text-xs text-slate-600"><?= $pid ?></td>
                    <td class="py-2 pr-4 font-medium text-slate-900"><?= htmlspecialchars($courseTitle) ?></td>
                    <td class="py-2 pr-4"><?= htmlspecialchars($publicationStatutLibelle($st)) ?></td>
                    <td class="py-2 pr-4"><?= htmlspecialchars((string) ($row['version_label'] ?? 'v1')) ?></td>
                    <td class="py-2 pr-4"><?= (int) ($row['compliance_score'] ?? 0) ?>&nbsp;%</td>
                    <td class="py-2 pr-4 text-slate-600"><?= htmlspecialchars($publicationDateMaj($row['updated_at'] ?? null)) ?></td>
                    <td class="py-2 pr-4">
                        <a class="text-emerald-700 font-semibold hover:underline" href="<?= htmlspecialchars(training_lms_admin_url('publications/' . $pid . '/changelog')) ?>">Historique des versions</a>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if ($rows === []): ?>
                <tr><td colspan="7" class="py-8 text-center text-slate-600">Aucune publication enregistrée pour cette communauté. Les entrées apparaîtront lorsque des supports auront été créés ou importés via le moteur de publication.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
