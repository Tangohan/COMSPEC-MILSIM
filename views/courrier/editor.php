<?php
$c = $courrier ?? [];
$doc = $c['document'] ?? null;
$templates = $c['templates'] ?? [];
$presets = $c['presets'] ?? [];
$variablesByCategory = $c['variables_by_category'] ?? [];
$defaults = $c['defaults'] ?? [];
$alerts = $c['alerts'] ?? [];
$completenessScore = (int)($c['completeness_score'] ?? 0);
$previewHtml = $c['preview_html'] ?? '';
$versions = $c['versions'] ?? [];
$baseUrl = url('');
$isEdit = $doc !== null;
$classificationLabels = $c['classification_labels'] ?? [];
if ($classificationLabels === []) {
    $classificationLabels = \App\Services\Courrier\CourrierClassification::labels();
}
$currentClassification = $doc['classification_level'] ?? 'interne';
$isLocked = $doc && (!empty($doc['signed_at']) || ($doc['status'] ?? '') === 'signed');
$statusLabels = [
    'draft' => ['Brouillon', 'bg-slate-100 text-slate-700'],
    'pending_validation' => ['En validation', 'bg-amber-100 text-amber-900'],
    'validated' => ['Validé', 'bg-sky-100 text-sky-900'],
    'signed' => ['Signé', 'bg-emerald-100 text-emerald-900'],
    'sent' => ['Envoyé', 'bg-blue-100 text-blue-900'],
    'archived' => ['Archivé', 'bg-slate-200 text-slate-600'],
    'rejected' => ['Refusé', 'bg-rose-100 text-rose-800'],
];
$bodyText = $doc['body_rendered'] ?? '';
$wordCount = $bodyText ? count(preg_split('/\s+/u', trim(strip_tags($bodyText)), -1, PREG_SPLIT_NO_EMPTY)) : 0;
$sentenceCount = $bodyText ? count(preg_split('/[.!?]+/u', trim(strip_tags($bodyText)), -1, PREG_SPLIT_NO_EMPTY)) : 0;
$hm = $c['header_meta'] ?? ['header_line1' => '', 'header_unit' => '', 'header_section' => ''];
?>
<link rel="stylesheet" href="<?= htmlspecialchars($baseUrl) ?>/assets/css/courrier-document.css" />
<div class="max-w-[1800px] mx-auto px-4 py-4">
    <?php if (\App\Core\Session::get('success')): ?>
    <p class="mb-2 text-sm text-emerald-600"><?= htmlspecialchars((string)\App\Core\Session::get('success')) ?></p>
    <?php \App\Core\Session::forget('success'); endif; ?>
    <?php if (\App\Core\Session::get('error')): ?>
    <p class="mb-2 text-sm text-red-600"><?= htmlspecialchars((string)\App\Core\Session::get('error')) ?></p>
    <?php \App\Core\Session::forget('error'); endif; ?>

    <div class="flex items-center justify-between gap-4 mb-4">
        <div class="flex items-center gap-3">
            <a href="<?= $baseUrl ?>/courrier" class="text-slate-500 hover:text-slate-900 text-sm">← Bureau Courrier</a>
            <span class="text-slate-400">|</span>
            <span class="text-sm font-medium text-slate-700"><?= $isEdit ? 'Édition' : 'Nouveau document' ?></span>
            <?php if ($doc): ?>
            <?php $st = $doc['status'] ?? 'draft'; $stInfo = $statusLabels[$st] ?? [$st, 'bg-slate-100 text-slate-600']; ?>
            <span class="px-2 py-0.5 text-xs font-medium rounded <?= htmlspecialchars($stInfo[1]) ?>"><?= htmlspecialchars($stInfo[0]) ?></span>
            <?php if ($isLocked): ?>
            <span class="px-2 py-0.5 text-xs font-semibold rounded bg-neutral-800 text-white">Verrouillé</span>
            <?php endif; ?>
            <?php if (!empty($doc['updated_at'])): ?>
            <span class="text-xs text-slate-400">Modifié : <?= htmlspecialchars(date('d/m/Y H:i', strtotime($doc['updated_at']))) ?></span>
            <?php endif; ?>
            <?php endif; ?>
        </div>
        <div class="flex items-center gap-2">
            <span class="text-xs text-slate-500">Conformité</span>
            <span class="w-16 h-2 bg-slate-200 rounded overflow-hidden"><span class="block h-full bg-emerald-500 rounded" style="width:<?= $completenessScore ?>%"></span></span>
            <span class="text-xs font-medium text-slate-700"><?= $completenessScore ?>%</span>
            <?php if (!$isLocked): ?>
            <button type="submit" form="courrier-form" class="px-3 py-1.5 bg-slate-700 text-white text-sm rounded hover:bg-slate-600">Enregistrer brouillon</button>
            <?php endif; ?>
            <?php if ($doc): ?>
            <a href="<?= $baseUrl ?>/courrier/read/<?= (int)$doc['id'] ?>" class="px-3 py-1.5 border border-slate-200 text-slate-700 text-sm rounded hover:bg-slate-50">Voir en lecture</a>
            <a href="<?= $baseUrl ?>/courrier/documents/<?= (int)$doc['id'] ?>/print" target="_blank" class="px-3 py-1.5 border border-slate-200 text-slate-700 text-sm rounded hover:bg-slate-50">Imprimer</a>
            <a href="<?= $baseUrl ?>/courrier/documents/<?= (int)$doc['id'] ?>/pdf" class="px-3 py-1.5 border border-slate-200 text-slate-700 text-sm rounded hover:bg-slate-50" title="PDF interne (Dompdf)">PDF</a>
            <a href="<?= $baseUrl ?>/courrier/documents/<?= (int)$doc['id'] ?>/pdf-external" class="px-3 py-1.5 border border-amber-200 text-amber-900 text-sm rounded hover:bg-amber-50" title="PDF avec caviardage [[REDACT]]">PDF externe</a>
            <?php if (!empty($doc['signed_at'])): ?>
            <a href="<?= $baseUrl ?>/courrier/documents/<?= (int)$doc['id'] ?>/verify" class="px-3 py-1.5 border border-slate-200 text-slate-700 text-sm rounded hover:bg-slate-50">Vérifier authenticité</a>
            <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>

    <?php
    $blocking = array_filter($alerts, fn($a) => ($a['severity'] ?? '') === 'blocking');
    if (!empty($blocking)): ?>
    <div class="mb-4 p-3 bg-rose-50 border border-rose-200 rounded text-sm text-rose-800">
        <strong>Erreurs bloquantes :</strong>
        <ul class="list-disc list-inside mt-1">
            <?php foreach ($blocking as $a): ?>
            <li><?= htmlspecialchars($a['message'] ?? '') ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php endif; ?>

    <?php if ($isLocked): ?>
    <div class="mb-4 p-3 rounded-lg border border-neutral-300 bg-neutral-50 text-sm text-neutral-800">
        <strong>Document signé et verrouillé.</strong> Le contenu ne peut plus être modifié ; l’empreinte et le code de vérification garantissent l’intégrité.
    </div>
    <?php endif; ?>

    <div class="grid grid-cols-1 lg:grid-cols-12 gap-4">
        <!-- Colonne gauche : configuration -->
        <div class="lg:col-span-3 space-y-4">
            <div class="bg-white border border-slate-200 rounded-lg p-4 shadow-sm">
                <h3 class="text-sm font-bold text-slate-700 uppercase tracking-wide mb-3">Configuration</h3>
                <form method="post" action="<?= $baseUrl ?>/courrier/editor/save" id="courrier-form">
                    <?= \App\Core\Csrf::field() ?>
                    <?php if ($doc): ?><input type="hidden" name="id" value="<?= (int)$doc['id'] ?>"><?php endif; ?>

                    <div class="space-y-3">
                        <div>
                            <label class="block text-xs font-medium text-slate-600 mb-1">Type / Modèle</label>
                            <select name="template_id" class="w-full border border-slate-200 rounded px-3 py-2 text-sm" <?= $isLocked ? 'disabled' : '' ?>>
                                <option value="">— Choisir un modèle —</option>
                                <?php foreach ($templates as $t): ?>
                                <option value="<?= (int)$t['id'] ?>" <?= ($doc && (int)$doc['template_id'] === (int)$t['id']) || (!$doc && empty($defaults)) ? 'selected' : '' ?>><?= htmlspecialchars($t['name'] ?? '') ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-600 mb-1">Format (preset)</label>
                            <select name="preset_id" class="w-full border border-slate-200 rounded px-3 py-2 text-sm" <?= $isLocked ? 'disabled' : '' ?>>
                                <option value="">— Choisir un format —</option>
                                <?php
                                $presetsPaper = array_filter($presets, fn($p) => in_array($p['code'] ?? '', ['a4_portrait', 'a4_landscape'], true));
                                $presetsCourrier = array_filter($presets, fn($p) => !in_array($p['code'] ?? '', ['a4_portrait', 'a4_landscape'], true));
                                ?>
                                <optgroup label="Formats papier">
                                    <?php foreach ($presetsPaper as $p): ?>
                                    <option value="<?= (int)$p['id'] ?>" <?= ($doc && (int)$doc['preset_id'] === (int)$p['id']) || (($c['default_preset'] ?? null) && (int)($c['default_preset']['id']) === (int)$p['id']) ? 'selected' : '' ?>><?= htmlspecialchars($p['name'] ?? '') ?></option>
                                    <?php endforeach; ?>
                                </optgroup>
                                <optgroup label="Formats courrier">
                                    <?php foreach ($presetsCourrier as $p): ?>
                                    <option value="<?= (int)$p['id'] ?>" <?= ($doc && (int)$doc['preset_id'] === (int)$p['id']) || (($c['default_preset'] ?? null) && (int)($c['default_preset']['id']) === (int)$p['id']) ? 'selected' : '' ?>><?= htmlspecialchars($p['name'] ?? '') ?></option>
                                    <?php endforeach; ?>
                                </optgroup>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-600 mb-1">Classification</label>
                            <select name="classification_level" class="w-full border border-slate-200 rounded px-3 py-2 text-sm" <?= $isLocked ? 'disabled' : '' ?>>
                                <?php foreach ($classificationLabels as $code => $label): ?>
                                <option value="<?= htmlspecialchars($code) ?>" <?= $currentClassification === $code ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="border-t border-slate-100 pt-3 mt-1">
                            <p class="text-xs font-bold text-slate-700 mb-2">En-tête papier (aperçu / PDF)</p>
                            <div class="space-y-2">
                                <div>
                                    <label class="block text-[10px] font-medium text-slate-500 mb-0.5">Communauté</label>
                                    <input type="text" name="header_line1" value="<?= htmlspecialchars($hm['header_line1'] ?? '') ?>" class="w-full border border-slate-200 rounded px-3 py-2 text-sm" placeholder="Nom de la communauté" <?= $isLocked ? 'readonly' : '' ?>>
                                </div>
                                <div>
                                    <label class="block text-[10px] font-medium text-slate-500 mb-0.5">Unité</label>
                                    <input type="text" name="header_unit" value="<?= htmlspecialchars($hm['header_unit'] ?? '') ?>" class="w-full border border-slate-200 rounded px-3 py-2 text-sm" placeholder="Unité d’affectation" <?= $isLocked ? 'readonly' : '' ?>>
                                </div>
                                <div>
                                    <label class="block text-[10px] font-medium text-slate-500 mb-0.5">Groupe</label>
                                    <input type="text" name="header_section" value="<?= htmlspecialchars($hm['header_section'] ?? '') ?>" class="w-full border border-slate-200 rounded px-3 py-2 text-sm" placeholder="Groupe ou fonction" <?= $isLocked ? 'readonly' : '' ?>>
                                </div>
                            </div>
                            <p class="mt-1 text-[10px] text-slate-400">Remplis d’après votre communauté, votre unité et votre groupe. Vous pouvez les modifier pour ce courrier.</p>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-600 mb-1">Référence</label>
                            <input type="text" name="reference_number" value="<?= htmlspecialchars($doc['reference_number'] ?? ($defaults['reference_number'] ?? '')) ?>" class="w-full border border-slate-200 rounded px-3 py-2 text-sm" placeholder="CR-2025-0001" <?= $isLocked ? 'readonly' : '' ?>>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-600 mb-1">Objet</label>
                            <input type="text" name="subject" value="<?= htmlspecialchars($doc['subject'] ?? '') ?>" class="w-full border border-slate-200 rounded px-3 py-2 text-sm" placeholder="Objet du courrier" <?= $isLocked ? 'readonly' : '' ?>>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-600 mb-1">Signataire (émetteur)</label>
                            <input type="text" name="issuer_label" value="<?= htmlspecialchars($doc['issuer_label'] ?? ($defaults['issuer_label'] ?? '')) ?>" class="w-full border border-slate-200 rounded px-3 py-2 text-sm" <?= $isLocked ? 'readonly' : '' ?>>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-600 mb-1">Destinataire</label>
                            <input type="text" name="destination_label" value="<?= htmlspecialchars($doc['destination_label'] ?? '') ?>" class="w-full border border-slate-200 rounded px-3 py-2 text-sm" <?= $isLocked ? 'readonly' : '' ?>>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-600 mb-1">Titre (interne)</label>
                            <input type="text" name="title" value="<?= htmlspecialchars($doc['title'] ?? '') ?>" class="w-full border border-slate-200 rounded px-3 py-2 text-sm" <?= $isLocked ? 'readonly' : '' ?>>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-600 mb-1">Corps du document</label>
                            <textarea id="body-rendered" name="body_rendered" form="courrier-form" rows="10" class="w-full border border-slate-200 rounded px-3 py-2 text-sm font-sans text-[#0b1220]" <?= $isLocked ? 'readonly' : '' ?>><?= htmlspecialchars($doc['body_rendered'] ?? '') ?></textarea>
                            <p class="mt-1 text-xs text-slate-500"><span id="stat-words"><?= $wordCount ?></span> mot(s) · <span id="stat-sentences"><?= $sentenceCount ?></span> phrase(s)</p>
                            <p class="mt-2 text-xs text-slate-400">Caviardage : entourez le texte sensible par <code class="bg-slate-100 px-1 rounded">[[REDACT]]…[[/REDACT]]</code> — surlignage en aperçu ; le PDF « externe » supprime le texte sous les marqueurs.</p>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Colonne centrale : aperçu -->
        <div class="lg:col-span-6">
            <div class="bg-white border border-slate-200 rounded-lg p-4 shadow-sm sticky top-20">
                <h3 class="text-sm font-bold text-slate-700 uppercase tracking-wide mb-3">Aperçu</h3>
                <div class="courrier-page-chrome bg-slate-50 rounded border border-slate-200 min-h-[400px] overflow-auto p-6 courrier-preview-container">
                    <?php if ($previewHtml): ?>
                    <?= $previewHtml ?>
                    <?php elseif ($doc): ?>
                    <div class="courrier-preview a4-portrait"><div class="courrier-body"><?= !empty(trim(strip_tags($doc['body_rendered'] ?? ''))) ? $doc['body_rendered'] : '<p class="text-slate-400 text-sm">Le corps du document est vide. Saisissez l\'objet, le destinataire et le corps pour voir l\'aperçu.</p>' ?></div></div>
                    <?php if (!empty($blocking)): ?>
                    <p class="text-amber-600 text-xs mt-3">Complétez les champs signalés en erreur pour un document conforme.</p>
                    <?php endif; ?>
                    <?php else: ?>
                    <p class="text-slate-400 text-sm">Choisissez un modèle et enregistrez pour voir l’aperçu.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Colonne droite : variables et conformité -->
        <div class="lg:col-span-3 space-y-4">
            <div class="bg-white border border-slate-200 rounded-lg p-4 shadow-sm">
                <h3 class="text-sm font-bold text-slate-700 uppercase tracking-wide mb-3">Variables disponibles</h3>
                <p class="text-xs text-slate-500 mb-2">Utilisez dans le corps du document.</p>
                <ul class="space-y-1 text-xs max-h-40 overflow-y-auto">
                    <?php foreach ($variablesByCategory as $cat => $vars): ?>
                    <li class="font-medium text-slate-600 mt-2"><?= htmlspecialchars($cat) ?></li>
                    <?php foreach ($vars as $v): ?>
                    <li><code class="text-slate-700 bg-slate-100 px-1 rounded">{{<?= htmlspecialchars($v['code'] ?? '') ?>}}</code></li>
                    <?php endforeach; ?>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php
            $assistantTarget = 'body-rendered';
            $assistantInsertMode = 'html';
            $assistantLocked = $isLocked;
            $assistantDocId = $doc ? (int) $doc['id'] : 0;
            require base_path('views/partials/writing_assistant.php');
            ?>
            <?php if ($doc && !empty($versions)): ?>
            <div class="bg-white border border-slate-200 rounded-lg p-4 shadow-sm">
                <h3 class="text-sm font-bold text-slate-700 uppercase tracking-wide mb-3">Historique</h3>
                <p class="text-xs text-slate-500 mb-2"><?= count($versions) ?> version(s) enregistrée(s).</p>
                <ul class="space-y-1 text-xs max-h-32 overflow-y-auto">
                    <?php foreach (array_slice($versions, 0, 5) as $v): ?>
                    <li class="text-slate-600">v<?= (int)($v['version_number'] ?? 0) ?> — <?= htmlspecialchars($v['created_at'] ?? '') ?></li>
                    <?php endforeach; ?>
                </ul>
                <a href="<?= $baseUrl ?>/courrier/history" class="text-xs text-slate-600 hover:underline mt-2 inline-block">Historique des documents</a>
            </div>
            <?php endif; ?>
            <div class="bg-white border border-slate-200 rounded-lg p-4 shadow-sm">
                <h3 class="text-sm font-bold text-slate-700 uppercase tracking-wide mb-3">Conformité</h3>
                <?php if (empty($alerts)): ?>
                <p class="text-sm text-emerald-600">Aucune alerte.</p>
                <?php else: ?>
                <ul class="space-y-1 text-xs">
                    <?php foreach ($alerts as $a): ?>
                    <li class="<?= ($a['severity'] ?? '') === 'blocking' ? 'text-rose-600' : (($a['severity'] ?? '') === 'warning' ? 'text-amber-600' : 'text-slate-600') ?>"><?= htmlspecialchars($a['message'] ?? '') ?></li>
                    <?php endforeach; ?>
                </ul>
                <?php endif; ?>
            </div>
            <?php if ($doc): ?>
            <div class="bg-white border border-slate-200 rounded-lg p-4 shadow-sm">
                <h3 class="text-sm font-bold text-slate-700 uppercase tracking-wide mb-3">Signature</h3>
                <?php if (($doc['status'] ?? '') === 'validated'): ?>
                <button type="button" id="btn-sign-document" class="w-full px-3 py-2 bg-slate-700 text-white text-sm rounded hover:bg-slate-600 font-medium">
                    Signer le document
                </button>
                <p class="mt-2 text-xs text-slate-500">Signature numérique, tampons et option de hash d'authenticité.</p>
                <?php elseif (($doc['status'] ?? '') === 'draft'): ?>
                <p class="text-xs text-amber-600">Enregistrez puis <strong>Soumettre à validation</strong> pour débloquer la signature.</p>
                <?php elseif (($doc['status'] ?? '') === 'pending_validation'): ?>
                <p class="text-xs text-slate-500">En attente de validation. Une fois validé, le bouton « Signer » sera disponible.</p>
                <?php elseif (!empty($doc['signed_at'])): ?>
                <p class="text-xs text-emerald-600">Document signé le <?= htmlspecialchars(date('d/m/Y H:i', strtotime($doc['signed_at']))) ?>.</p>
                <?php else: ?>
                <p class="text-xs text-slate-500">Validez le document pour pouvoir le signer.</p>
                <?php endif; ?>
                <p class="mt-3"><a href="<?= $baseUrl ?>/courrier/signature" class="text-xs font-semibold text-slate-600 hover:underline">Créer ou modifier ma signature</a></p>
            </div>
            <?php endif; ?>
            <?php if ($doc && in_array($doc['status'] ?? '', ['draft', 'pending_validation', 'rejected'], true)): ?>
            <div class="bg-white border border-slate-200 rounded-lg p-4 shadow-sm">
                <h3 class="text-sm font-bold text-slate-700 uppercase tracking-wide mb-3">Workflow</h3>
                <div class="space-y-2">
                    <?php if (($doc['status'] ?? '') === 'draft'): ?>
                    <form method="post" action="<?= $baseUrl ?>/courrier/documents/<?= (int)$doc['id'] ?>/workflow">
                        <?= \App\Core\Csrf::field() ?>
                        <input type="hidden" name="action" value="submit_validation">
                        <button type="submit" class="w-full px-3 py-2 bg-amber-600 text-white text-sm rounded hover:bg-amber-700">Soumettre à validation</button>
                    </form>
                    <?php endif; ?>
                    <?php if (($doc['status'] ?? '') === 'pending_validation'): ?>
                    <form method="post" action="<?= $baseUrl ?>/courrier/documents/<?= (int)$doc['id'] ?>/workflow" class="mb-2">
                        <?= \App\Core\Csrf::field() ?>
                        <input type="hidden" name="action" value="validate">
                        <button type="submit" class="w-full px-3 py-2 bg-emerald-600 text-white text-sm rounded hover:bg-emerald-700">Valider</button>
                    </form>
                    <form method="post" action="<?= $baseUrl ?>/courrier/documents/<?= (int)$doc['id'] ?>/workflow">
                        <?= \App\Core\Csrf::field() ?>
                        <input type="hidden" name="action" value="reject">
                        <button type="submit" class="w-full px-3 py-2 bg-rose-600 text-white text-sm rounded hover:bg-rose-700">Refuser</button>
                    </form>
                    <?php endif; ?>
                    <?php if (($doc['status'] ?? '') === 'rejected'): ?>
                    <p class="text-xs text-slate-500">Document refusé. Modifiez et soumettez à nouveau.</p>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($doc): ?>
    <?php $document_id = (int)$doc['id']; require base_path('views/courrier/partials/signature-modal.php'); ?>
    <script>
    (function() {
        var btn = document.getElementById('btn-sign-document');
        var modal = document.getElementById('signature-modal');
        if (btn && modal) btn.addEventListener('click', function() { modal.dispatchEvent(new CustomEvent('show')); });
    })();
    </script>
    <?php endif; ?>
</div>

<script>
(function() {
    var ta = document.getElementById('body-rendered');
    var statWords = document.getElementById('stat-words');
    var statSentences = document.getElementById('stat-sentences');
    if (!ta || !statWords || !statSentences) return;
    function countWords(s) {
        s = (s || '').replace(/<[^>]+>/g, ' ').trim();
        return s ? s.split(/\s+/).filter(Boolean).length : 0;
    }
    function countSentences(s) {
        s = (s || '').replace(/<[^>]+>/g, ' ').trim();
        return s ? s.split(/[.!?]+/).filter(Boolean).length : 0;
    }
    function update() {
        var v = ta.value || '';
        statWords.textContent = countWords(v);
        statSentences.textContent = countSentences(v);
    }
    ta.addEventListener('input', update);
    ta.addEventListener('change', update);
})();
</script>
<script>
window.COURRIER_DOC_ID = <?= $doc ? (int)$doc['id'] : 'null' ?>;
</script>
