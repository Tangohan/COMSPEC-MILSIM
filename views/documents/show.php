<?php
$document = $document ?? null;
$viewType = $viewType ?? 'pdf';
$baseUrl = url('');
if (!$document) {
    echo '<p>Document non trouvé.</p>';
    return;
}
$title = htmlspecialchars($document['title']);
$fileUrl = $baseUrl . '/documents/' . (int)$document['id'] . '/file';
$downloadUrl = $baseUrl . '/documents/' . (int)$document['id'] . '/download';
$lifecycleBlocked = (bool) ($lifecycleBlocked ?? false);
$securitySessionToken = (string) ($securitySessionToken ?? '');
$requiresAccessCode = (bool) ($requiresAccessCode ?? false);
$requiresSignature = (bool) ($requiresSignature ?? false);
$signatureBeforeDownload = (bool) ($signatureBeforeDownload ?? true);
$isAccessCodeUnlocked = (bool) ($isAccessCodeUnlocked ?? false);
$updatedAt = !empty($document['updated_at']) ? date('d/m/Y H:i', strtotime((string) $document['updated_at'])) : '—';
$reviewDueAt = !empty($document['review_due_at']) ? date('d/m/Y', strtotime((string) $document['review_due_at'])) : 'Non défini';
$expiresAt = !empty($document['expires_at']) ? date('d/m/Y', strtotime((string) $document['expires_at'])) : 'Non défini';
?>
<div class="max-w-7xl mx-auto px-4 sm:px-6 py-6 sm:py-8" data-doc-protect>
    <?php if ($lifecycleBlocked): ?>
    <div class="mb-6 rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">Ce document est marqué comme obsolète (revue/correction requise).</div>
    <?php endif; ?>
    <div class="rounded-3xl border border-slate-200 bg-gradient-to-br from-white via-slate-50 to-emerald-50/40 p-5 sm:p-6 mb-6">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <a href="<?= url('documents') ?>" class="text-sm text-slate-500 hover:text-slate-900 mb-2 inline-block">← Retour aux documents</a>
            <h1 class="text-2xl font-black text-slate-900"><?= $title ?></h1>
            <?php if (!empty($document['description'])): ?>
            <p class="text-slate-600 mt-2 max-w-2xl"><?= nl2br(htmlspecialchars($document['description'])) ?></p>
            <?php endif; ?>
            <div class="mt-3 flex flex-wrap gap-2 text-xs">
                <span class="inline-flex items-center rounded-full bg-slate-100 px-2.5 py-1 text-slate-700 font-medium">Ouverture sécurisée</span>
                <span class="inline-flex items-center rounded-full bg-slate-100 px-2.5 py-1 text-slate-700 font-medium"><?= $viewType === 'image' ? 'Aperçu image' : ($viewType === 'manuscript' ? 'Manuel rédigé' : 'Aperçu PDF') ?></span>
            </div>
        </div>
        <div class="flex items-center gap-2">
            <?php if ($viewType === 'manuscript'): ?>
            <button type="button" onclick="window.print()" class="inline-flex items-center gap-2 px-4 py-2 bg-slate-900 text-white text-sm font-semibold rounded-lg hover:bg-slate-800 transition-colors">Imprimer</button>
            <?php elseif (!empty($document['file_path'])): ?>
            <a id="doc-download-link" href="<?= $downloadUrl . '?security_session_token=' . rawurlencode($securitySessionToken) ?>" class="inline-flex items-center gap-2 px-4 py-2 bg-slate-900 text-white text-sm font-semibold rounded-lg hover:bg-slate-800 transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" /></svg>
                Télécharger
            </a>
            <?php endif; ?>
        </div>
    </div>
    </div>

    <div class="grid gap-6 xl:grid-cols-[1fr_320px]">
    <div>
    <?php if ($viewType === 'manuscript'): ?>
    <?php
        $documentTitle = (string) ($document['title'] ?? '');
        $fmLivePreview = false;
        $manuscript = $manuscript ?? \App\Support\DocumentManuscript::forView($document);
        require base_path('views/partials/document_fm_paper.php');
    ?>
    <?php elseif ($viewType === 'image'): ?>
    <div class="bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden">
        <div class="relative p-4 flex justify-center bg-slate-50 min-h-[60vh]" data-doc-viewport x-data="{ open: false }">
            <div class="doc-viewport-inner relative w-full flex justify-center min-h-[50vh]">
                <button type="button" @click="open = true" class="cursor-zoom-in">
                    <img src="<?= $fileUrl ?>" alt="<?= $title ?>" draggable="false" class="doc-protect-asset max-w-full max-h-[75vh] object-contain rounded shadow" />
                </button>
            </div>
            <?php require base_path('views/partials/document_screenshot_shield.php'); ?>
            <template x-teleport="body">
                <div x-show="open" x-cloak class="fixed inset-0 z-[200] bg-black/90 flex items-center justify-center p-4" data-doc-protect data-doc-viewport @click="open = false">
                    <div class="doc-viewport-inner relative max-w-full max-h-full flex items-center justify-center">
                        <img src="<?= $fileUrl ?>" alt="<?= $title ?>" draggable="false" class="doc-protect-asset max-w-full max-h-full object-contain" @click.stop />
                    </div>
                    <?php require base_path('views/partials/document_screenshot_shield.php'); ?>
                </div>
            </template>
        </div>
    </div>
    <?php else: ?>
    <div class="bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden relative" data-doc-viewport>
        <div class="doc-viewport-inner">
            <div class="flex flex-wrap items-center justify-between gap-2 p-3 border-b border-slate-200 bg-slate-50">
                <div class="flex items-center gap-2">
                    <button type="button" id="doc-prev" class="px-3 py-1.5 text-sm font-medium text-slate-700 bg-white border border-slate-200 rounded hover:bg-slate-50 disabled:opacity-40 disabled:cursor-not-allowed">Préc.</button>
                    <label class="text-sm text-slate-600">
                        <span class="sr-only">Page actuelle</span>
                        <input id="doc-page-input" type="number" min="1" value="1" class="w-16 border border-slate-200 rounded px-2 py-1 text-center text-slate-700" />
                    </label>
                    <span class="text-sm text-slate-600">/ <span id="doc-page-count">—</span></span>
                    <button type="button" id="doc-next" class="px-3 py-1.5 text-sm font-medium text-slate-700 bg-white border border-slate-200 rounded hover:bg-slate-50 disabled:opacity-40 disabled:cursor-not-allowed">Suiv.</button>
                </div>
                <div class="flex items-center gap-2">
                    <button type="button" id="doc-fit-width" class="px-3 py-1.5 text-sm font-medium text-slate-700 bg-white border border-slate-200 rounded hover:bg-slate-50">Ajuster largeur</button>
                    <button type="button" id="doc-zoom-out" class="px-3 py-1.5 text-sm font-medium text-slate-700 bg-white border border-slate-200 rounded hover:bg-slate-50">−</button>
                    <span id="doc-zoom-level" class="text-sm text-slate-600 w-14 text-center">120%</span>
                    <button type="button" id="doc-zoom-in" class="px-3 py-1.5 text-sm font-medium text-slate-700 bg-white border border-slate-200 rounded hover:bg-slate-50">+</button>
                </div>
            </div>
            <div class="px-3 py-2 bg-slate-50/70 border-b border-slate-100 text-xs text-slate-500">
                Astuce : utilisez ← → pour changer de page, et + / − pour zoomer.
            </div>
            <div class="p-4 overflow-auto bg-slate-100 min-h-[70vh] flex justify-center relative" id="doc-viewer" data-doc-protect>
                <div id="doc-loading" class="absolute inset-0 bg-slate-100/85 backdrop-blur-[1px] flex items-center justify-center text-sm font-medium text-slate-600">Chargement du document…</div>
                <div id="doc-error" class="hidden absolute top-4 left-1/2 -translate-x-1/2 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700"></div>
            </div>
        </div>
        <?php require base_path('views/partials/document_screenshot_shield.php'); ?>
    </div>
    <script type="module">
      import { getDocument, GlobalWorkerOptions } from <?= json_encode(asset_url('assets/vendor/pdfjs/pdf.mjs')) ?>;
      GlobalWorkerOptions.workerSrc = <?= json_encode(asset_url('assets/vendor/pdfjs/pdf.worker.min.mjs')) ?>;
      const url = <?= json_encode($fileUrl) ?>;
      const container = document.getElementById('doc-viewer');
      const pageCountEl = document.getElementById('doc-page-count');
      const pageInput = document.getElementById('doc-page-input');
      const loadingEl = document.getElementById('doc-loading');
      const errorEl = document.getElementById('doc-error');
      const prevBtn = document.getElementById('doc-prev');
      const nextBtn = document.getElementById('doc-next');
      const fitWidthBtn = document.getElementById('doc-fit-width');
      const zoomLevelEl = document.getElementById('doc-zoom-level');
      let pdfDoc = null;
      let pageNum = 1;
      let scale = 1.2;
      const scaleStep = 0.2;
      let isRendering = false;

      function setLoading(state) {
        if (!loadingEl) return;
        loadingEl.style.display = state ? 'flex' : 'none';
      }

      function setError(message) {
        if (!errorEl) return;
        if (!message) {
          errorEl.classList.add('hidden');
          errorEl.textContent = '';
          return;
        }
        errorEl.textContent = message;
        errorEl.classList.remove('hidden');
      }

      function syncControls() {
        const hasPdf = !!pdfDoc;
        prevBtn.disabled = !hasPdf || isRendering || pageNum <= 1;
        nextBtn.disabled = !hasPdf || isRendering || pageNum >= (pdfDoc?.numPages || 1);
        pageInput.disabled = !hasPdf || isRendering;
        pageInput.value = String(pageNum);
        zoomLevelEl.textContent = Math.round(scale * 100) + '%';
      }

      function renderPage(num) {
        if (!pdfDoc || isRendering) return;
        isRendering = true;
        setLoading(true);
        syncControls();
        pdfDoc.getPage(num).then(function(page) {
          const viewport = page.getViewport({ scale });
          const canvas = document.createElement('canvas');
          const ctx = canvas.getContext('2d');
          canvas.height = viewport.height;
          canvas.width = viewport.width;
          canvas.classList.add('doc-protect-asset');
          canvas.addEventListener('contextmenu', function (e) { e.preventDefault(); }, true);
          container.innerHTML = '';
          if (loadingEl) container.appendChild(loadingEl);
          if (errorEl) container.appendChild(errorEl);
          container.appendChild(canvas);
          const task = page.render({ canvasContext: ctx, viewport });
          (task.promise || Promise.resolve()).then(function() {
            pageNum = num;
            setError('');
          }).catch(function() {
            setError('Le rendu de cette page a échoué.');
          }).finally(function() {
            isRendering = false;
            setLoading(false);
            syncControls();
          });
        }).catch(function() {
          isRendering = false;
          setLoading(false);
          setError('Impossible d’ouvrir cette page du PDF.');
          syncControls();
        });
      }

      getDocument(url).promise.then(function(pdf) {
        pdfDoc = pdf;
        pageCountEl.textContent = pdf.numPages;
        pageInput.max = String(pdf.numPages);
        syncControls();
        renderPage(pageNum);
      }).catch(function(err) {
        setLoading(false);
        setError('Impossible de charger le PDF.');
      });

      prevBtn.onclick = function() {
        if (pageNum <= 1) return;
        renderPage(pageNum - 1);
      };
      nextBtn.onclick = function() {
        if (!pdfDoc || pageNum >= pdfDoc.numPages) return;
        renderPage(pageNum + 1);
      };
      document.getElementById('doc-zoom-in').onclick = function() {
        scale += scaleStep;
        syncControls();
        renderPage(pageNum);
      };
      document.getElementById('doc-zoom-out').onclick = function() {
        if (scale <= 0.5) return;
        scale -= scaleStep;
        syncControls();
        renderPage(pageNum);
      };

      fitWidthBtn.onclick = function() {
        const viewportWidth = container.clientWidth - 32;
        if (!pdfDoc || viewportWidth <= 0) return;
        pdfDoc.getPage(pageNum).then(function(page) {
          const unscaled = page.getViewport({ scale: 1 });
          const computed = viewportWidth / unscaled.width;
          if (!Number.isFinite(computed) || computed <= 0) return;
          scale = Math.max(0.5, Math.min(3, computed));
          syncControls();
          renderPage(pageNum);
        });
      };

      pageInput.addEventListener('change', function() {
        if (!pdfDoc) return;
        const requested = Math.max(1, Math.min(pdfDoc.numPages, parseInt(pageInput.value || '1', 10)));
        renderPage(requested);
      });

      window.addEventListener('keydown', function(e) {
        if (!pdfDoc) return;
        if (e.key === 'ArrowRight') {
          e.preventDefault();
          nextBtn.click();
        } else if (e.key === 'ArrowLeft') {
          e.preventDefault();
          prevBtn.click();
        } else if (e.key === '+' || e.key === '=') {
          e.preventDefault();
          document.getElementById('doc-zoom-in').click();
        } else if (e.key === '-' || e.key === '_') {
          e.preventDefault();
          document.getElementById('doc-zoom-out').click();
        }
      });
    </script>
    <?php endif; ?>
    </div>
    <aside class="rounded-2xl border border-slate-200 bg-white p-4 h-fit xl:sticky xl:top-6">
        <p class="text-[10px] font-black uppercase tracking-[0.2em] text-slate-400">Métadonnées</p>
        <dl class="mt-3 space-y-3 text-sm">
            <div><dt class="text-slate-500">Type d'aperçu</dt><dd class="font-semibold text-slate-900"><?= $viewType === 'image' ? 'Image' : ($viewType === 'manuscript' ? 'Manuel rédigé' : 'PDF') ?></dd></div>
            <div><dt class="text-slate-500">Dernière mise à jour</dt><dd class="font-semibold text-slate-900"><?= htmlspecialchars($updatedAt) ?></dd></div>
            <div><dt class="text-slate-500">Revue prévue</dt><dd class="font-semibold text-slate-900"><?= htmlspecialchars($reviewDueAt) ?></dd></div>
            <div><dt class="text-slate-500">Expiration</dt><dd class="font-semibold text-slate-900"><?= htmlspecialchars($expiresAt) ?></dd></div>
        </dl>
        <?php if ($requiresAccessCode || $requiresSignature): ?>
        <div class="mt-4 rounded-xl border border-amber-200 bg-amber-50 p-3 text-xs text-amber-900">
            <p class="font-semibold mb-2">Sécurité document</p>
            <?php if ($requiresAccessCode): ?>
            <div class="mb-2">
                <label class="block mb-1">Code d'accès</label>
                <input id="doc-access-code-input" type="password" class="w-full rounded border border-amber-200 px-2 py-1.5" placeholder="Saisir le code" />
                <button id="doc-access-code-btn" type="button" class="mt-2 w-full rounded bg-amber-900 px-3 py-1.5 font-semibold text-white">Déverrouiller</button>
            </div>
            <?php endif; ?>
            <?php if ($requiresSignature): ?>
            <div>
                <label class="block mb-1">Signature numérique (compte)</label>
                <input id="doc-sign-name" type="text" class="w-full rounded border border-amber-200 px-2 py-1.5 mb-2" placeholder="Nom affiché/signataire" />
                <canvas id="doc-sign-pad" width="280" height="120" class="w-full rounded border border-amber-200 bg-white"></canvas>
                <div class="mt-2 flex gap-2">
                    <button id="doc-sign-clear" type="button" class="flex-1 rounded border border-amber-300 px-2 py-1.5">Effacer</button>
                    <button id="doc-sign-submit" type="button" class="flex-1 rounded bg-amber-900 px-2 py-1.5 font-semibold text-white">Signer</button>
                </div>
            </div>
            <?php endif; ?>
            <p id="doc-security-message" class="mt-2 text-[11px]"></p>
        </div>
        <?php endif; ?>
        <p class="mt-4 text-xs text-slate-500">Astuce: flèches clavier pour naviguer, +/− pour zoomer rapidement.</p>
    </aside>
    </div>
</div>
<?php require base_path('views/partials/documents_copy_protection.php'); ?>
<script>
(function() {
  const docId = <?= (int) $document['id'] ?>;
  const token = <?= json_encode($securitySessionToken) ?>;
  const csrfToken = <?= json_encode(\App\Core\Csrf::token()) ?>;
  const requiresAccessCode = <?= $requiresAccessCode ? 'true' : 'false' ?>;
  const requiresSignature = <?= $requiresSignature ? 'true' : 'false' ?>;
  const isAccessCodeUnlocked = <?= $isAccessCodeUnlocked ? 'true' : 'false' ?>;
  const signatureBeforeDownload = <?= $signatureBeforeDownload ? 'true' : 'false' ?>;
  const msg = document.getElementById('doc-security-message');
  const track = (eventType, readSeconds) => {
    const fd = new FormData();
    fd.append('_csrf_token', csrfToken);
    fd.append('security_session_token', token);
    fd.append('event_type', eventType);
    if (readSeconds) fd.append('read_seconds', String(readSeconds));
    fetch(<?= json_encode(url('documents/' . (int) $document['id'] . '/access-track')) ?>, { method: 'POST', body: fd, credentials: 'same-origin' }).catch(() => {});
  };
  let startedAt = Date.now();
  setInterval(() => track('heartbeat', 15), 15000);
  window.addEventListener('beforeunload', function() {
    const secs = Math.max(1, Math.floor((Date.now() - startedAt) / 1000));
    track('closed', secs);
  });

  const dlink = document.getElementById('doc-download-link');
  if (dlink && (requiresAccessCode && !isAccessCodeUnlocked)) {
    dlink.classList.add('pointer-events-none', 'opacity-50');
  }

  const unlockBtn = document.getElementById('doc-access-code-btn');
  if (unlockBtn) {
    unlockBtn.addEventListener('click', function() {
      const code = document.getElementById('doc-access-code-input').value || '';
      const fd = new FormData();
      fd.append('_csrf_token', csrfToken);
      fd.append('security_session_token', token);
      fd.append('access_code', code);
      fetch(<?= json_encode(url('documents/' . (int) $document['id'] . '/unlock')) ?>, { method: 'POST', body: fd, credentials: 'same-origin' })
        .then(r => r.json().then(j => ({ ok: r.ok, j })))
        .then(({ ok, j }) => {
          if (!ok) throw new Error((j && j.message) || 'Erreur');
          if (msg) msg.textContent = 'Code validé. Rechargement…';
          window.location.reload();
        })
        .catch(e => { if (msg) msg.textContent = e.message; });
    });
  }

  const pad = document.getElementById('doc-sign-pad');
  if (pad) {
    const ctx = pad.getContext('2d');
    ctx.lineWidth = 2;
    ctx.lineCap = 'round';
    let drawing = false;
    const pos = (ev) => {
      const r = pad.getBoundingClientRect();
      const p = ev.touches ? ev.touches[0] : ev;
      return { x: p.clientX - r.left, y: p.clientY - r.top };
    };
    const start = (ev) => { drawing = true; const p = pos(ev); ctx.beginPath(); ctx.moveTo(p.x, p.y); ev.preventDefault(); };
    const move = (ev) => { if (!drawing) return; const p = pos(ev); ctx.lineTo(p.x, p.y); ctx.stroke(); ev.preventDefault(); };
    const end = () => { drawing = false; };
    pad.addEventListener('mousedown', start); pad.addEventListener('mousemove', move); window.addEventListener('mouseup', end);
    pad.addEventListener('touchstart', start, { passive:false }); pad.addEventListener('touchmove', move, { passive:false }); window.addEventListener('touchend', end);
    document.getElementById('doc-sign-clear')?.addEventListener('click', () => ctx.clearRect(0, 0, pad.width, pad.height));
    document.getElementById('doc-sign-submit')?.addEventListener('click', () => {
      const signatureName = (document.getElementById('doc-sign-name').value || '').trim();
      const dataUrl = pad.toDataURL('image/png');
      const fd = new FormData();
      fd.append('_csrf_token', csrfToken);
      fd.append('security_session_token', token);
      fd.append('signature_name', signatureName);
      fd.append('signature_data_url', dataUrl);
      fetch(<?= json_encode(url('documents/' . (int) $document['id'] . '/signature')) ?>, { method: 'POST', body: fd, credentials: 'same-origin' })
        .then(r => r.json().then(j => ({ ok: r.ok, j })))
        .then(({ ok, j }) => {
          if (!ok) throw new Error((j && j.message) || 'Erreur');
          if (msg) msg.textContent = 'Signature enregistrée.';
          track('signature_completed');
        })
        .catch(e => { if (msg) msg.textContent = e.message; });
    });
  }
})();
</script>
