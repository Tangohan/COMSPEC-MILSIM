<?php
/**
 * Vitrine : outils du gestionnaire (pastille « Modifier la page », puis barre d’édition et panneau).
 * Inclus seulement si la personne peut modifier la page (CommunityPageEditorAccess).
 *
 * @var array<string, mixed> $pageLayout
 * @var array<string, mixed> $pageEditor
 * @var array<string, mixed> $communityConfig
 * @var bool $pbEditing
 * @var string $slug
 */
use App\Services\Community\PublicPageBuilder;

$pbeBase = url('c/' . rawurlencode((string) $slug));
$pbeHasDraft = !empty($pageEditor['hasDraft']);
$pbePreview = !empty($pageEditor['previewDraft']);
?>
<?php if (!$pbEditing): ?>
<div class="pbe-launch" role="region" aria-label="Gestion de la page">
  <?php if ($pbePreview): ?>
  <span class="pbe-launch__note">Aperçu du brouillon (non publié)</span>
  <a class="pbe-btn pbe-btn--soft" href="<?= htmlspecialchars($pbeBase, ENT_QUOTES, 'UTF-8') ?>">Version publiée</a>
  <?php elseif ($pbeHasDraft): ?>
  <span class="pbe-launch__note">Un brouillon attend d’être publié</span>
  <a class="pbe-btn pbe-btn--soft" href="<?= htmlspecialchars($pbeBase . '?apercu=brouillon', ENT_QUOTES, 'UTF-8') ?>">Aperçu</a>
  <?php endif; ?>
  <a class="pbe-btn pbe-btn--primary" href="<?= htmlspecialchars($pbeBase . '?edition=1', ENT_QUOTES, 'UTF-8') ?>">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 20h4L19 9l-4-4L4 16z"/><path d="M13.5 6.5l4 4"/></svg>
    <span class="pbe-btn__txt">Modifier la page</span>
  </a>
</div>
<?php else: ?>
<?php
$pbeMedia = [];
foreach (is_array($publicMediaItems ?? null) ? $publicMediaItems : [] as $pbeMi) {
    if (($pbeMi['media_kind'] ?? '') !== 'image') {
        continue;
    }
    $pbeUrl = \App\Support\CommunityMediaDetails::publicUrl(isset($pbeMi['storage_path']) ? (string) $pbeMi['storage_path'] : null);
    if ($pbeUrl) {
        $pbeMedia[] = ['url' => $pbeUrl, 'title' => trim((string) ($pbeMi['title'] ?? ''))];
    }
    if (count($pbeMedia) >= 24) {
        break;
    }
}
if (!empty($brandBanner)) {
    $pbeMedia[] = ['url' => $brandBanner, 'title' => 'Bannière de la communauté'];
}
$pbeDraft = is_array($communityConfig['public_page_builder_draft'] ?? null) ? $communityConfig['public_page_builder_draft'] : [];
$pbeStyleKeys = ['section' => PublicPageBuilder::styleKeysFor('section'), 'section:hero' => PublicPageBuilder::styleKeysFor('section', 'hero')];
foreach (array_keys(PublicPageBuilder::BLOCK_TYPES) as $pbeT) {
    $pbeStyleKeys[$pbeT] = PublicPageBuilder::styleKeysFor($pbeT);
}
$pbeConfig = [
    'base' => $pbeBase,
    'csrf' => \App\Core\Csrf::token(),
    'endpoints' => [
        'draft' => $pbeBase . '/page-editor/draft',
        'publish' => $pbeBase . '/page-editor/publish',
        'discard' => $pbeBase . '/page-editor/discard',
        'block' => $pbeBase . '/page-editor/block',
        'image' => $pbeBase . '/page-editor/image',
    ],
    'backOffice' => url('back-office/community/presentation'),
    'hasDraft' => $pbeHasDraft,
    'draftUpdatedAt' => (string) ($pageEditor['draftUpdatedAt'] ?? ''),
    'layout' => $pageLayout,
    'fields' => PublicPageBuilder::normalizeFields(is_array($pbeDraft['fields'] ?? null) ? $pbeDraft['fields'] : []),
    'sections' => PublicPageBuilder::SECTIONS,
    'types' => PublicPageBuilder::BLOCK_TYPES,
    'styleOptions' => PublicPageBuilder::styleOptions(),
    'styleKeys' => $pbeStyleKeys,
    'media' => $pbeMedia,
];
?>
<script type="application/json" id="pb-editor-config"><?= json_encode($pbeConfig, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
<div class="pbe-dock" role="toolbar" aria-label="Édition de la page" data-pbe-dock>
  <div class="pbe-dock__status" aria-live="polite"><span class="pbe-dot<?= $pbeHasDraft ? ' pbe-dot--saved' : '' ?>" data-pbe-dot></span><span data-pbe-status><?= $pbeHasDraft ? 'Brouillon enregistré' : 'Aucune modification' ?></span></div>
  <span class="pbe-sep" aria-hidden="true"></span>
  <button type="button" class="pbe-btn" data-pbe-action="page">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M4 6h16M4 12h10M4 18h7"/><circle cx="18" cy="15" r="3"/></svg>
    <span class="pbe-btn__txt">Page et en-tête</span>
  </button>
  <button type="button" class="pbe-btn" data-pbe-action="library">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>
    <span class="pbe-btn__txt">Ajouter un bloc</span>
  </button>
  <span class="pbe-sep" aria-hidden="true"></span>
  <a class="pbe-btn" href="<?= htmlspecialchars($pbeBase, ENT_QUOTES, 'UTF-8') ?>" data-pbe-action="exit">
    <span class="pbe-btn__txt">Quitter</span>
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18"/></svg>
  </a>
  <button type="button" class="pbe-btn pbe-btn--primary" data-pbe-action="publish">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12.5l4.5 4.5L19 7.5"/></svg>
    <span class="pbe-btn__txt">Publier</span>
  </button>
</div>
<div class="pbe-scrim" hidden data-pbe-scrim></div>
<aside class="pbe-panel" aria-label="Réglages" aria-hidden="true" data-pbe-panel>
  <div class="pbe-panel__head">
    <div>
      <p class="pbe-panel__kicker" data-pbe-panel-kicker></p>
      <h2 class="pbe-panel__title" data-pbe-panel-title></h2>
    </div>
    <button type="button" class="pbe-icon" data-pbe-action="close" aria-label="Fermer le panneau">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18"/></svg>
    </button>
  </div>
  <div class="pbe-panel__body" data-pbe-panel-body></div>
</aside>
<input type="file" accept="image/jpeg,image/png,image/webp" hidden data-pbe-file>
<script defer src="<?= htmlspecialchars(asset_url('assets/js/community-page-editor.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
<?php endif; ?>
