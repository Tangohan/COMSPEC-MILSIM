<?php
$document = $document ?? null;
$versions = $versions ?? [];
$collaborators = $collaborators ?? [];
$children = $children ?? [];
$auditEntries = $auditEntries ?? [];
$usersMap = $usersMap ?? [];
$accessSessions = $accessSessions ?? [];
$accessEvents = $accessEvents ?? [];
if (!$document) {
    echo '<p>Document non trouvé.</p>';
    return;
}
$classificationLabels = ['public' => 'Public', 'interne' => 'Interne service', 'restreint' => 'Diffusion restreinte', 'sensible' => 'Donnée sensible', 'confidentiel' => 'Confidentiel opérationnel', 'operationnel' => 'Accès commandement'];
$statusLabels = ['draft' => 'Brouillon', 'review' => 'En relecture', 'approval' => 'À valider', 'published' => 'Publié', 'suspended' => 'Suspendu', 'archived' => 'Archivé', 'obsolete' => 'Obsolète'];
$docId = (int)$document['id'];
?>
<div class="max-w-6xl mx-auto px-6 py-12">
    <div class="flex flex-wrap items-center justify-between gap-4 mb-6">
        <div>
            <a href="<?= url('documents/gestion') ?>" class="text-sm text-slate-500 hover:text-slate-900 mb-2 inline-block">← Retour aux documents</a>
            <h1 class="text-2xl font-black text-slate-900"><?= htmlspecialchars($document['title']) ?></h1>
            <?php if (!empty($document['short_description'])): ?>
            <p class="text-slate-600 mt-1"><?= htmlspecialchars($document['short_description']) ?></p>
            <?php endif; ?>
            <div class="flex flex-wrap gap-2 mt-2">
                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-slate-100 text-slate-800"><?= $statusLabels[$document['status'] ?? 'draft'] ?? $document['status'] ?></span>
                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-800"><?= $classificationLabels[$document['classification_level'] ?? 'interne'] ?? $document['classification_level'] ?></span>
            </div>
        </div>
        <div class="flex gap-2">
            <a href="<?= url('documents/gestion/' . $docId . '/modifier') ?>" class="px-4 py-2 bg-slate-900 text-white text-sm font-semibold rounded hover:bg-slate-800">Modifier</a>
            <?php if (!empty($document['file_path'])): ?>
            <a href="<?= url('documents/' . $docId . '/file') ?>" class="px-4 py-2 border border-slate-200 text-slate-700 text-sm rounded hover:bg-slate-50" target="_blank">Ouvrir le fichier</a>
            <a href="<?= url('documents/' . $docId . '/download') ?>" class="px-4 py-2 border border-slate-200 text-slate-700 text-sm rounded hover:bg-slate-50">Télécharger</a>
            <?php elseif (!empty($manuscript)): ?>
            <button type="button" onclick="window.print()" class="px-4 py-2 border border-slate-200 text-slate-700 text-sm rounded hover:bg-slate-50">Imprimer</button>
            <?php endif; ?>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
        <div class="space-y-6">
            <?php if (!empty($manuscript)): ?>
            <section class="bg-white border border-slate-200 rounded-lg p-4 overflow-hidden">
                <h2 class="text-sm font-bold text-slate-800 mb-3">Aperçu du manuel</h2>
                <?php
                $documentTitle = (string) ($document['title'] ?? '');
                $fmLivePreview = true;
                require base_path('views/partials/document_fm_paper.php');
                ?>
            </section>
            <?php endif; ?>

            <?php if (!empty($document['description'])): ?>
            <section class="bg-white border border-slate-200 rounded-lg p-4">
                <h2 class="text-sm font-bold text-slate-800 mb-2">Description</h2>
                <p class="text-slate-600 text-sm whitespace-pre-wrap"><?= nl2br(htmlspecialchars($document['description'])) ?></p>
            </section>
            <?php endif; ?>

            <?php if (!empty($document['file_path'])): ?>
            <section class="bg-white border border-slate-200 rounded-lg p-4">
                <h2 class="text-sm font-bold text-slate-800 mb-2">Fichier actuel</h2>
                <p class="text-sm text-slate-600">Version courante — <?= htmlspecialchars($document['mime_type'] ?? '') ?> — <?= isset($document['size']) ? number_format((int)$document['size'] / 1024, 1) . ' Ko' : '' ?></p>
            </section>
            <?php endif; ?>

            <?php if (!empty($collaborators)): ?>
            <section class="bg-white border border-slate-200 rounded-lg p-4">
                <h2 class="text-sm font-bold text-slate-800 mb-2">Collaborateurs</h2>
                <ul class="space-y-1 text-sm text-slate-600">
                    <?php foreach ($collaborators as $c): ?>
                    <li><?= htmlspecialchars($c['role']) ?> — <?= htmlspecialchars($usersMap[$c['user_id']] ?? '#' . $c['user_id']) ?></li>
                    <?php endforeach; ?>
                </ul>
                <p class="mt-2"><a href="<?= url('documents/gestion/' . $docId . '/acces') ?>" class="text-sm underline">Gérer les accès</a></p>
            </section>
            <?php endif; ?>

            <?php if (!empty($children)): ?>
            <section class="bg-white border border-slate-200 rounded-lg p-4">
                <h2 class="text-sm font-bold text-slate-800 mb-2">Sous-documents</h2>
                <ul class="space-y-2">
                    <?php foreach ($children as $ch): ?>
                    <li>
                        <a href="<?= url('documents/gestion/' . $ch['id'] . '/modifier') ?>" class="text-slate-900 hover:underline"><?= htmlspecialchars($ch['title']) ?></a>
                        <span class="text-slate-500 text-xs">(<?= htmlspecialchars($ch['relation_type'] ?? '') ?>)</span>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </section>
            <?php endif; ?>
        </div>

        <div class="space-y-6">
            <section class="bg-white border border-slate-200 rounded-lg p-4">
                <h2 class="text-sm font-bold text-slate-800 mb-2">Métadonnées</h2>
                <dl class="text-sm space-y-1">
                    <dt class="text-slate-500">Type</dt>
                    <dd class="text-slate-800"><?= htmlspecialchars($document['document_type'] ?? '—') ?></dd>
                    <dt class="text-slate-500 mt-2">Visibilité</dt>
                    <dd class="text-slate-800"><?= htmlspecialchars($document['visibility_scope'] ?? '—') ?></dd>
                    <dt class="text-slate-500 mt-2">Propriétaire</dt>
                    <dd class="text-slate-800"><?= htmlspecialchars($usersMap[$document['owner_user_id'] ?? 0] ?? '—') ?></dd>
                    <dt class="text-slate-500 mt-2">Auteur</dt>
                    <dd class="text-slate-800"><?= htmlspecialchars($usersMap[$document['author_user_id'] ?? 0] ?? '—') ?></dd>
                    <?php if (!empty($document['effective_at'])): ?>
                    <dt class="text-slate-500 mt-2">Date d'effet</dt>
                    <dd class="text-slate-800"><?= date('d/m/Y H:i', strtotime($document['effective_at'])) ?></dd>
                    <?php endif; ?>
                    <?php if (!empty($document['expires_at'])): ?>
                    <dt class="text-slate-500 mt-2">Date d'expiration</dt>
                    <dd class="text-slate-800"><?= date('d/m/Y H:i', strtotime($document['expires_at'])) ?></dd>
                    <?php endif; ?>
                </dl>
            </section>

            <section class="bg-white border border-slate-200 rounded-lg p-4">
                <h2 class="text-sm font-bold text-slate-800 mb-2">Versions</h2>
                <?php if (empty($versions)): ?>
                <p class="text-slate-500 text-sm">Aucune version.</p>
                <?php else: ?>
                <ul class="space-y-1 text-sm">
                    <?php foreach ($versions as $v): ?>
                    <li>
                        Version <?= (int)($v['version_number'] ?? 0) ?>
                        <?= !empty($v['is_current']) ? '<span class="text-emerald-600 font-medium">(actuelle)</span>' : '' ?>
                        — <?= !empty($v['created_at']) ? date('d.m.Y H:i', strtotime($v['created_at'])) : '' ?>
                        <?= !empty($v['change_notes']) ? ' — ' . htmlspecialchars($v['change_notes']) : '' ?>
                    </li>
                    <?php endforeach; ?>
                </ul>
                <?php endif; ?>
            </section>

            <section class="bg-white border border-slate-200 rounded-lg p-4">
                <h2 class="text-sm font-bold text-slate-800 mb-2">Historique récent</h2>
                <?php if (empty($auditEntries)): ?>
                <p class="text-slate-500 text-sm">Aucune entrée.</p>
                <?php else: ?>
                <ul class="space-y-2 text-sm">
                    <?php foreach (array_slice($auditEntries, 0, 10) as $e): ?>
                    <li>
                        <span class="text-slate-500"><?= !empty($e['created_at']) ? date('d.m.Y H:i', strtotime($e['created_at'])) : '' ?></span>
                        — <?= htmlspecialchars($e['action']) ?>
                        <?= isset($e['user_id']) && isset($usersMap[$e['user_id']]) ? ' par ' . htmlspecialchars($usersMap[$e['user_id']]) : '' ?>
                    </li>
                    <?php endforeach; ?>
                </ul>
                <p class="mt-2"><a href="<?= url('documents/gestion/' . $docId . '/historique') ?>" class="underline">Voir tout l'historique</a></p>
                <?php endif; ?>
            </section>

            <section class="bg-white border border-slate-200 rounded-lg p-4">
                <h2 class="text-sm font-bold text-slate-800 mb-2">Timeline des accès</h2>
                <?php if (empty($accessEvents)): ?>
                <p class="text-slate-500 text-sm">Aucun accès journalisé.</p>
                <?php else: ?>
                <ul class="space-y-2 text-xs">
                    <?php foreach (array_slice($accessEvents, 0, 20) as $ev): ?>
                    <li class="border-b border-slate-100 pb-1">
                        <span class="text-slate-500"><?= !empty($ev['created_at']) ? date('d.m.Y H:i:s', strtotime($ev['created_at'])) : '' ?></span>
                        — <span class="font-semibold text-slate-800"><?= htmlspecialchars((string) ($ev['event_type'] ?? 'event')) ?></span>
                        <?php if (!empty($ev['display_name']) || !empty($ev['email'])): ?>
                        <span class="text-slate-600">par <?= htmlspecialchars((string) ($ev['display_name'] ?? $ev['email'])) ?></span>
                        <?php endif; ?>
                    </li>
                    <?php endforeach; ?>
                </ul>
                <?php endif; ?>
            </section>

            <section class="bg-white border border-slate-200 rounded-lg p-4">
                <h2 class="text-sm font-bold text-slate-800 mb-2">Temps de lecture & téléchargements</h2>
                <?php if (empty($accessSessions)): ?>
                <p class="text-slate-500 text-sm">Aucune session.</p>
                <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="min-w-full text-xs">
                        <thead>
                            <tr class="text-left text-slate-500">
                                <th class="py-1 pr-2">Compte</th>
                                <th class="py-1 pr-2">Ouvert</th>
                                <th class="py-1 pr-2">Lecture</th>
                                <th class="py-1 pr-2">Téléchargements</th>
                                <th class="py-1 pr-2">Signature</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($accessSessions as $s): ?>
                            <tr class="border-t border-slate-100">
                                <td class="py-1 pr-2"><?= htmlspecialchars((string) ($s['display_name'] ?? $s['email'] ?? ('#' . ($s['user_id'] ?? '')))) ?></td>
                                <td class="py-1 pr-2"><?= !empty($s['opened_at']) ? date('d.m H:i', strtotime($s['opened_at'])) : '—' ?></td>
                                <td class="py-1 pr-2"><?= (int) (($s['read_seconds'] ?? 0) / 60) ?> min</td>
                                <td class="py-1 pr-2"><?= (int) ($s['download_count'] ?? 0) ?></td>
                                <td class="py-1 pr-2"><?= !empty($s['signature_completed_at']) ? '✅' : (!empty($s['signature_required']) ? '⏳' : '—') ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </section>
        </div>
    </div>
</div>
