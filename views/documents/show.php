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
?>
<div class="max-w-6xl mx-auto px-6 py-8">
    <div class="flex flex-wrap items-center justify-between gap-4 mb-6">
        <div>
            <a href="<?= url('documents') ?>" class="text-sm text-slate-500 hover:text-slate-900 mb-2 inline-block">← Retour aux documents</a>
            <h1 class="text-2xl font-black text-slate-900"><?= $title ?></h1>
            <?php if (!empty($document['description'])): ?>
            <p class="text-slate-600 mt-2 max-w-2xl"><?= nl2br(htmlspecialchars($document['description'])) ?></p>
            <?php endif; ?>
        </div>
        <a href="<?= $downloadUrl ?>" class="inline-flex items-center gap-2 px-4 py-2 bg-slate-900 text-white text-sm font-semibold rounded hover:bg-slate-800">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" /></svg>
            Télécharger
        </a>
    </div>

    <?php if ($viewType === 'image'): ?>
    <div class="bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden">
        <div class="p-4 flex justify-center bg-slate-50 min-h-[60vh]" x-data="{ open: false }">
            <button type="button" @click="open = true" class="cursor-zoom-in">
                <img src="<?= $fileUrl ?>" alt="<?= $title ?>" class="max-w-full max-h-[75vh] object-contain rounded shadow" />
            </button>
            <template x-teleport="body">
                <div x-show="open" x-cloak class="fixed inset-0 z-[200] bg-black/90 flex items-center justify-center p-4" @click="open = false">
                    <img src="<?= $fileUrl ?>" alt="<?= $title ?>" class="max-w-full max-h-full object-contain" @click.stop />
                </div>
            </template>
        </div>
    </div>
    <?php else: ?>
    <div class="bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden">
        <div class="flex items-center gap-2 p-2 border-b border-slate-200 bg-slate-50">
            <button type="button" id="doc-prev" class="px-3 py-1.5 text-sm font-medium text-slate-700 bg-white border border-slate-200 rounded hover:bg-slate-50">Préc.</button>
            <span class="text-sm text-slate-600"><span id="doc-page-num">1</span> / <span id="doc-page-count">—</span></span>
            <button type="button" id="doc-next" class="px-3 py-1.5 text-sm font-medium text-slate-700 bg-white border border-slate-200 rounded hover:bg-slate-50">Suiv.</button>
            <button type="button" id="doc-zoom-out" class="px-3 py-1.5 text-sm font-medium text-slate-700 bg-white border border-slate-200 rounded hover:bg-slate-50">−</button>
            <span id="doc-zoom-level" class="text-sm text-slate-600 w-12 text-center">100%</span>
            <button type="button" id="doc-zoom-in" class="px-3 py-1.5 text-sm font-medium text-slate-700 bg-white border border-slate-200 rounded hover:bg-slate-50">+</button>
        </div>
        <div class="p-4 overflow-auto bg-slate-100 min-h-[70vh] flex justify-center" id="doc-viewer"></div>
    </div>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.mjs" type="module"></script>
    <script type="module">
      import * as pdfjsLib from 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.mjs';
      pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.mjs';
      const url = <?= json_encode($fileUrl) ?>;
      const container = document.getElementById('doc-viewer');
      const pageNumEl = document.getElementById('doc-page-num');
      const pageCountEl = document.getElementById('doc-page-count');
      let pdfDoc = null;
      let pageNum = 1;
      let scale = 1.2;
      const scaleStep = 0.2;

      function renderPage(num) {
        if (!pdfDoc) return;
        pdfDoc.getPage(num).then(function(page) {
          const viewport = page.getViewport({ scale });
          const canvas = document.createElement('canvas');
          const ctx = canvas.getContext('2d');
          canvas.height = viewport.height;
          canvas.width = viewport.width;
          container.innerHTML = '';
          container.appendChild(canvas);
          page.render({ canvasContext: ctx, viewport });
          pageNumEl.textContent = num;
        });
      }

      pdfjsLib.getDocument(url).promise.then(function(pdf) {
        pdfDoc = pdf;
        pageCountEl.textContent = pdf.numPages;
        renderPage(pageNum);
      }).catch(function(err) {
        container.innerHTML = '<p class="text-red-600">Impossible de charger le PDF.</p>';
      });

      document.getElementById('doc-prev').onclick = function() {
        if (pageNum <= 1) return;
        pageNum--;
        renderPage(pageNum);
      };
      document.getElementById('doc-next').onclick = function() {
        if (!pdfDoc || pageNum >= pdfDoc.numPages) return;
        pageNum++;
        renderPage(pageNum);
      };
      document.getElementById('doc-zoom-in').onclick = function() {
        scale += scaleStep;
        document.getElementById('doc-zoom-level').textContent = Math.round(scale * 100) + '%';
        renderPage(pageNum);
      };
      document.getElementById('doc-zoom-out').onclick = function() {
        if (scale <= 0.5) return;
        scale -= scaleStep;
        document.getElementById('doc-zoom-level').textContent = Math.round(scale * 100) + '%';
        renderPage(pageNum);
      };
    </script>
    <?php endif; ?>
</div>
