<?php
$page = isset($customPage) && is_array($customPage) ? $customPage : null;
$isEdit = $page !== null;
$customPage = $page ?? [];
$chaptersInit = \App\Support\TrainingFormationCustomPageRenderer::decodeSections((string)($customPage['sections_json'] ?? ''));
$sectionsJsonInitial = json_encode($chaptersInit, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS);
$action = $isEdit ? training_lms_admin_url('pages-html/'.(int)$customPage['id']) : training_lms_admin_url('pages-html');
?>
<link rel="stylesheet" href="<?= htmlspecialchars(url('assets/css/training_custom_page_editor.css')) ?>">
<div id="cp-editor-root" data-initial-handbook="<?= (($customPage['doc_structure'] ?? 'single') === 'handbook') ? '1' : '0' ?>">
<section class="tc-panel p-6 md:p-8">
  <h1 class="tc-hero-title"><?= $isEdit ? 'Studio DOC HTML / Manuel' : 'Nouveau DOC HTML' ?></h1>
</section>
<form method="post" action="<?= htmlspecialchars($action) ?>" id="cp-main-form" class="space-y-6">
  <?= \App\Core\Csrf::field() ?>
  <input type="hidden" name="sections_json" id="cp-sections-json" value="<?= htmlspecialchars((string)$sectionsJsonInitial, ENT_QUOTES, 'UTF-8') ?>">

  <div class="grid lg:grid-cols-12 gap-5">
    <aside class="lg:col-span-3 space-y-4">
      <?php include __DIR__.'/partials/custom_page_publication_panel.php'; ?>
      <?php include __DIR__.'/partials/custom_page_theme_picker.php'; ?>
      <?php include __DIR__.'/partials/custom_page_visibility_selector.php'; ?>
      <div class="cp-editor-card text-xs space-y-2"><div class="cp-editor-card__head"><p class="cp-editor-card__title">SEO / partage</p></div>
        <input name="canonical_url" placeholder="Canonical URL" value="<?= htmlspecialchars((string)($customPage['canonical_url'] ?? '')) ?>" class="w-full rounded border px-2 py-2">
        <input name="meta_title" placeholder="Meta title" value="<?= htmlspecialchars((string)($customPage['meta_title'] ?? '')) ?>" class="w-full rounded border px-2 py-2">
        <textarea name="meta_description" placeholder="Meta description" rows="2" class="w-full rounded border px-2 py-2"><?= htmlspecialchars((string)($customPage['meta_description'] ?? '')) ?></textarea>
      </div>
    </aside>

    <section class="lg:col-span-6 space-y-4">
      <div class="cp-editor-card space-y-3">
        <input type="text" required name="title" id="cp-title" value="<?= htmlspecialchars((string)($customPage['title'] ?? '')) ?>" placeholder="Titre" class="w-full rounded-lg border border-slate-200 px-3 py-2">
        <input type="text" required name="slug" id="cp-slug" value="<?= htmlspecialchars((string)($customPage['slug'] ?? '')) ?>" pattern="[a-z0-9-]+" placeholder="slug" class="w-full rounded-lg border border-slate-200 px-3 py-2 font-mono">
        <input type="text" name="subtitle" value="<?= htmlspecialchars((string)($customPage['subtitle'] ?? '')) ?>" placeholder="Sous-titre" class="w-full rounded-lg border border-slate-200 px-3 py-2">
        <textarea name="summary" rows="2" placeholder="Accroche / résumé" class="w-full rounded-lg border border-slate-200 px-3 py-2"><?= htmlspecialchars((string)($customPage['summary'] ?? '')) ?></textarea>
        <div class="flex gap-4 text-xs"><label><input type="radio" name="doc_structure" value="single" <?= (($customPage['doc_structure'] ?? 'single') !== 'handbook'?'checked':'') ?>>Page unique</label><label><input type="radio" name="doc_structure" value="handbook" <?= (($customPage['doc_structure'] ?? '') === 'handbook'?'checked':'') ?>>Manuel</label></div>
      </div>

      <div class="cp-editor-workspace p-4">
        <div class="cp-editor-card" data-cp-snippet-context="intro">
          <div class="cp-editor-card__head"><p class="cp-editor-card__title">Corps HTML</p></div>
          <div class="cp-snippet-bar"><button type="button" class="cp-snippet-btn" data-cp-snippet="encadre">Encadré</button><button type="button" class="cp-snippet-btn" data-cp-snippet="timeline">Timeline</button><button type="button" class="cp-snippet-btn" data-cp-snippet="tableau">Tableau</button></div>
          <textarea name="html_body" id="cp-html" rows="18" class="w-full rounded-xl border border-slate-200 bg-slate-50/60 px-3 py-3 font-mono"><?= htmlspecialchars((string)($customPage['html_body'] ?? '')) ?></textarea>
        </div>
        <div id="cp-handbook-wrap" class="space-y-3 mt-4 hidden"><div class="flex items-center justify-between"><p class="text-xs font-black uppercase tracking-wide">Chapitres manuel</p><button type="button" id="cp-add-chapter" class="cp-snippet-btn">Ajouter chapitre</button></div><div id="cp-chapters-list" class="space-y-2"></div></div>
      </div>
    </section>

    <aside class="lg:col-span-3 space-y-4">
      <div class="cp-editor-card">
        <div class="cp-editor-card__head"><p class="cp-editor-card__title">Aperçu</p></div>
        <iframe id="cp-preview-frame" class="w-full min-h-[420px] rounded-lg border border-slate-200" sandbox="allow-scripts"></iframe>
      </div>
      <div class="cp-editor-card text-xs">
        <div class="cp-editor-card__head flex items-center justify-between gap-2">
          <p class="cp-editor-card__title">Versions</p>
          <?php if (!empty($customPage['id'])): ?>
          <a href="<?= htmlspecialchars(training_lms_admin_url('pages-html/' . (int) $customPage['id'] . '/versions/comparer')) ?>" class="text-indigo-700 font-semibold">Comparer</a>
          <?php endif; ?>
        </div>
        <ul class="space-y-2 max-h-80 overflow-auto"><?php foreach (($customPageRevisions ?? []) as $rev) { include __DIR__.'/partials/custom_page_version_item.php'; } ?></ul>
      </div>
    </aside>
  </div>

  <div class="sticky bottom-0 z-10 bg-white/95 border border-slate-200 rounded-xl p-3 flex gap-2">
    <button type="submit" class="tc-btn-primary tc-btn-emerald">Enregistrer le brouillon</button>
    <a href="<?= htmlspecialchars(training_lms_admin_url('pages-html')) ?>" class="tc-btn-primary tc-btn-ghost">Retour</a>
  </div>
</form>

<template id="cp-chapter-template"><div class="cp-chapter rounded-xl border border-slate-200 bg-white p-4 space-y-2"><div class="flex justify-between"><input class="cp-chapter-title w-full rounded border px-2 py-2" placeholder="Titre chapitre"><button type="button" data-cp-chapter-remove class="text-rose-700">Suppr.</button></div><input type="hidden" class="cp-chapter-slug"><textarea class="cp-chapter-html w-full rounded border px-2 py-2 font-mono" rows="7"></textarea></div></template>
</div>
<script>window.cpDocCssHref=<?= json_encode(rtrim(url(''),'/').'/assets/css/training_formation_doc.css') ?>;</script>
<script src="<?= htmlspecialchars(url('assets/js/training_custom_page_handbook.js')) ?>" defer></script>
<script src="<?= htmlspecialchars(url('assets/js/training_custom_page_editor.js')) ?>" defer></script>
<script src="<?= htmlspecialchars(url('assets/js/training_custom_page_rich.js')) ?>" defer></script>
