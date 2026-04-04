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
$bodyText = $doc['body_rendered'] ?? '';
$wordCount = $bodyText ? count(preg_split('/\s+/u', trim(strip_tags($bodyText)), -1, PREG_SPLIT_NO_EMPTY)) : 0;
$sentenceCount = $bodyText ? count(preg_split('/[.!?]+/u', trim(strip_tags($bodyText)), -1, PREG_SPLIT_NO_EMPTY)) : 0;
?>
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
            <span class="px-2 py-0.5 text-xs font-medium rounded bg-slate-100 text-slate-600"><?= htmlspecialchars($doc['status'] ?? 'draft') ?></span>
            <?php if (!empty($doc['updated_at'])): ?>
            <span class="text-xs text-slate-400">Modifié : <?= htmlspecialchars(date('d/m/Y H:i', strtotime($doc['updated_at']))) ?></span>
            <?php endif; ?>
            <?php endif; ?>
        </div>
        <div class="flex items-center gap-2">
            <span class="text-xs text-slate-500">Conformité</span>
            <span class="w-16 h-2 bg-slate-200 rounded overflow-hidden"><span class="block h-full bg-emerald-500 rounded" style="width:<?= $completenessScore ?>%"></span></span>
            <span class="text-xs font-medium text-slate-700"><?= $completenessScore ?>%</span>
            <button type="submit" form="courrier-form" class="px-3 py-1.5 bg-slate-700 text-white text-sm rounded hover:bg-slate-600">Enregistrer brouillon</button>
            <?php if ($doc): ?>
            <a href="<?= $baseUrl ?>/courrier/read/<?= (int)$doc['id'] ?>" class="px-3 py-1.5 border border-slate-200 text-slate-700 text-sm rounded hover:bg-slate-50">Voir en lecture</a>
            <a href="<?= $baseUrl ?>/courrier/documents/<?= (int)$doc['id'] ?>/print" target="_blank" class="px-3 py-1.5 border border-slate-200 text-slate-700 text-sm rounded hover:bg-slate-50">Imprimer</a>
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

    <div class="grid grid-cols-1 lg:grid-cols-12 gap-4">
        <!-- Colonne gauche : configuration -->
        <div class="lg:col-span-4 space-y-4">
            <div class="bg-white border border-slate-200 rounded-lg p-4 shadow-sm">
                <h3 class="text-sm font-bold text-slate-700 uppercase tracking-wide mb-3">Configuration</h3>
                <form method="post" action="<?= $baseUrl ?>/courrier/editor/save" id="courrier-form">
                    <?= \App\Core\Csrf::field() ?>
                    <?php if ($doc): ?><input type="hidden" name="id" value="<?= (int)$doc['id'] ?>"><?php endif; ?>

                    <div class="space-y-3">
                        <div>
                            <label class="block text-xs font-medium text-slate-600 mb-1">Type / Modèle</label>
                            <select name="template_id" class="w-full border border-slate-200 rounded px-3 py-2 text-sm">
                                <option value="">— Choisir un modèle —</option>
                                <?php foreach ($templates as $t): ?>
                                <option value="<?= (int)$t['id'] ?>" <?= ($doc && (int)$doc['template_id'] === (int)$t['id']) || (!$doc && empty($defaults)) ? 'selected' : '' ?>><?= htmlspecialchars($t['name'] ?? '') ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-600 mb-1">Format (preset)</label>
                            <select name="preset_id" class="w-full border border-slate-200 rounded px-3 py-2 text-sm">
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
                            <label class="block text-xs font-medium text-slate-600 mb-1">Référence</label>
                            <input type="text" name="reference_number" value="<?= htmlspecialchars($doc['reference_number'] ?? ($defaults['reference_number'] ?? '')) ?>" class="w-full border border-slate-200 rounded px-3 py-2 text-sm" placeholder="CR-2025-0001">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-600 mb-1">Objet</label>
                            <input type="text" name="subject" value="<?= htmlspecialchars($doc['subject'] ?? '') ?>" class="w-full border border-slate-200 rounded px-3 py-2 text-sm" placeholder="Objet du courrier">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-600 mb-1">Signataire (émetteur)</label>
                            <input type="text" name="issuer_label" value="<?= htmlspecialchars($doc['issuer_label'] ?? ($defaults['issuer_label'] ?? '')) ?>" class="w-full border border-slate-200 rounded px-3 py-2 text-sm">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-600 mb-1">Destinataire</label>
                            <input type="text" name="destination_label" value="<?= htmlspecialchars($doc['destination_label'] ?? '') ?>" class="w-full border border-slate-200 rounded px-3 py-2 text-sm">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-600 mb-1">Titre (interne)</label>
                            <input type="text" name="title" value="<?= htmlspecialchars($doc['title'] ?? '') ?>" class="w-full border border-slate-200 rounded px-3 py-2 text-sm">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-600 mb-1">Corps du document</label>
                            <textarea id="body-rendered" name="body_rendered" form="courrier-form" rows="8" class="w-full border border-slate-200 rounded px-3 py-2 text-sm font-serif"><?= htmlspecialchars($doc['body_rendered'] ?? '') ?></textarea>
                            <p class="mt-1 text-xs text-slate-500"><span id="stat-words"><?= $wordCount ?></span> mot(s) · <span id="stat-sentences"><?= $sentenceCount ?></span> phrase(s)</p>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Colonne centrale : aperçu -->
        <div class="lg:col-span-5">
            <div class="bg-white border border-slate-200 rounded-lg p-4 shadow-sm sticky top-20">
                <h3 class="text-sm font-bold text-slate-700 uppercase tracking-wide mb-3">Aperçu</h3>
                <div class="bg-slate-50 rounded border border-slate-200 min-h-[400px] overflow-auto p-6 courrier-preview-container">
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
            <div class="bg-white border border-slate-200 rounded-lg p-4 shadow-sm">
                <h3 class="text-sm font-bold text-slate-700 uppercase tracking-wide mb-3">Blocs prédéfinis</h3>
                <ul class="space-y-2 text-xs">
                    <li><span class="font-medium text-slate-600">Formule d'appel</span> — Madame, Monsieur / À l'attention de…</li>
                    <li><span class="font-medium text-slate-600">Objet / Référence</span> — Objet : … / Réf. : {{document.reference_number}}</li>
                    <li><span class="font-medium text-slate-600">Paragraphes réglementaires</span> — À insérer selon le type de document</li>
                    <li><span class="font-medium text-slate-600">Formule de clôture</span> — Veuillez agréer… / Dans l'attente de…</li>
                    <li><span class="font-medium text-slate-600">Mention signature</span> — Pour copie et usage conforme</li>
                    <li><span class="font-medium text-slate-600">Mention diffusion</span> — Diffusion : …</li>
                </ul>
                <p class="mt-2 text-slate-400 text-xs">Saisie manuelle dans le corps du document ou insertion à brancher en JS.</p>
            </div>
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
                <p class="text-xs text-slate-500">Workflow : valider le document pour pouvoir le signer.</p>
                <?php endif; ?>
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
<style>
.courrier-preview { font-family: "Source Serif 4", Georgia, serif; font-size: 11pt; color: #1e293b; }
.courrier-preview.a4-portrait { max-width: 210mm; }
.courrier-envelope { margin-bottom: 1.25rem; }
.courrier-envelope-top { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 1.5rem; }
.courrier-header { flex: 1; }
.courrier-header-line { font-weight: 700; font-size: 10pt; text-transform: uppercase; letter-spacing: 0.02em; line-height: 1.35; color: #0f172a; }
.courrier-header-ref { font-size: 10pt; margin-top: 0.25rem; color: #475569; }
.courrier-date { font-size: 10pt; color: #475569; white-space: nowrap; }
.courrier-address-block { text-align: right; margin-bottom: 1.25rem; }
.courrier-issuer { font-size: 10pt; line-height: 1.4; margin-bottom: 0.25rem; }
.courrier-to-label { font-size: 10pt; font-style: italic; margin: 0.35rem 0 0.2rem; }
.courrier-destination { font-size: 10pt; line-height: 1.4; }
.courrier-meta { margin-bottom: 1rem; }
.courrier-meta-line { font-size: 10pt; line-height: 1.5; margin-bottom: 0.25rem; }
.courrier-body { white-space: pre-wrap; text-align: justify; line-height: 1.6; margin-top: 0.5rem; }
.courrier-body p { margin-bottom: 0.75rem; }
.courrier-signature-placeholder { margin-top: 2rem; text-align: right; }
.courrier-signature-title { font-weight: 700; font-size: 10pt; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 0.5rem; }
.courrier-signature-box { display: inline-block; border: 2px dashed #94a3b8; padding: 0.6rem 1.2rem; font-size: 10pt; color: #64748b; min-width: 120px; text-align: center; }
.courrier-signature-name { font-size: 10pt; margin-top: 0.35rem; }
</style>
