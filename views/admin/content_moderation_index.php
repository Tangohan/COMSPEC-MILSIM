<?php
declare(strict_types=1);

use App\Services\Moderation\ModerationArtifactState;
use App\Services\Moderation\ModerationRestrictionsCatalog;
use App\Services\Moderation\ModerationSourceType;

$baseUrl = url('');
$contentModerationUrl = rtrim(url('admin/content-moderation'), '/');
$artifacts = $artifacts ?? [];
$recentForumPublished = $recentForumPublished ?? [];
$moduleLabels = $moduleLabels ?? ModerationRestrictionsCatalog::moduleLabels();
$viewerUserId = (int) (\App\Core\Session::get('user_id') ?? 0);
$total = (int) ($total ?? 0);
$page = (int) ($page ?? 1);
$perPage = (int) ($perPage ?? 30);
$missingTables = !empty($missingTables);

/** @param non-empty-string $state */
$stateLabel = static function (string $state): string {
    return match ($state) {
        ModerationArtifactState::PENDING_SCAN => 'En attente d’analyse',
        ModerationArtifactState::CLEAN => 'Sans alerte (automatique)',
        ModerationArtifactState::QUARANTINED => 'En quarantaine',
        ModerationArtifactState::REJECTED => 'Rejeté',
        ModerationArtifactState::APPROVED_OVERRIDE => 'Approuvé manuellement',
        default => $state,
    };
};

/** @param non-empty-string $type */
$sourceLabel = static function (string $type): string {
    return match ($type) {
        ModerationSourceType::FORUM_UPLOAD => 'Pièce jointe (forum)',
        ModerationSourceType::DOCUMENT_VERSION => 'Document (version)',
        ModerationSourceType::COURRIER_DOCUMENT => 'Courrier',
        default => $type,
    };
};
?>
<div class="w-full max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-8 lg:py-10">
  <nav class="flex flex-wrap items-center gap-2 text-sm font-semibold text-slate-600 mb-6" aria-label="Fil d’Ariane">
    <a href="<?= htmlspecialchars($baseUrl) ?>/back-office" class="text-sky-700 hover:text-sky-900 hover:underline">Back-office</a>
    <span class="text-slate-300" aria-hidden="true">/</span>
    <span class="text-slate-900">Modération des fichiers</span>
  </nav>

  <header class="mb-8 border-b border-slate-200 pb-6">
    <h1 class="text-2xl md:text-3xl font-bold tracking-tight text-slate-900">File d’attente et quarantaine</h1>
    <p class="mt-2 text-base text-slate-600 max-w-2xl leading-relaxed">
      Cette page liste d’abord les éléments <strong class="font-semibold text-slate-800">en quarantaine ou en analyse</strong> (décision humaine requise).
      Les pièces jointes forum jugées sûres automatiquement n’y figurent pas : elles apparaissent plus bas dans le suivi récent, avec aperçu lorsque c’est possible.
    </p>
  </header>

  <?php $success = \App\Core\Session::getFlash('success'); $error = \App\Core\Session::getFlash('error'); ?>
  <?php if ($success): ?>
    <p class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-950" role="status"><?= htmlspecialchars((string) $success) ?></p>
  <?php endif; ?>
  <?php if ($error): ?>
    <p class="mb-4 rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-medium text-rose-950" role="alert"><?= htmlspecialchars((string) $error) ?></p>
  <?php endif; ?>

  <?php if ($missingTables): ?>
    <p class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm font-medium text-amber-950">
      La modération fichiers n’est pas encore initialisée sur cet environnement. Un administrateur technique doit appliquer les migrations prévues.
    </p>
  <?php elseif (empty($artifacts)): ?>
    <p class="rounded-xl border border-emerald-200 bg-white px-5 py-6 text-base font-medium text-slate-800 shadow-sm">
      Aucun élément en file d’attente pour l’instant. Si vous venez de joindre une image au forum et qu’elle s’affiche déjà dans le sujet, l’analyse automatique l’a probablement acceptée sans quarantaine : consultez la section « Suivi des pièces jointes forum » ci-dessous.
    </p>
  <?php else: ?>
    <p class="text-sm text-slate-600 mb-5">
      <span class="font-semibold text-slate-900"><?= (int) $total ?></span>
      élément<?= $total > 1 ? 's' : '' ?> au total
      · page <span class="font-semibold text-slate-900"><?= (int) $page ?></span>
    </p>
    <ul class="space-y-6">
      <?php foreach ($artifacts as $a): ?>
        <?php
        $id = (int) ($a['id'] ?? 0);
        $rawState = (string) ($a['state'] ?? '');
        $rawSource = (string) ($a['source_type'] ?? '');
        $displayState = $rawState !== '' ? $stateLabel($rawState) : '—';
        $displaySource = $rawSource !== '' ? $sourceLabel($rawSource) : '—';
        $uploaderId = (int) ($a['user_id'] ?? 0);
        $uploaderLabel = isset($a['uploader_label']) && is_string($a['uploader_label']) && $a['uploader_label'] !== '' ? $a['uploader_label'] : null;
        $forumCtx = isset($a['forum_context']) && is_array($a['forum_context']) ? $a['forum_context'] : null;
        $topicLink = '';
        if ($forumCtx !== null && (int) ($forumCtx['topic_id'] ?? 0) > 0 && (int) ($forumCtx['post_id'] ?? 0) > 0) {
            $topicLink = rtrim($baseUrl, '/') . '/forum/topic/' . (int) $forumCtx['topic_id'] . '#post-' . (int) $forumCtx['post_id'];
        }
        $canSanctionUploader = $uploaderId > 0 && $uploaderId !== $viewerUserId;
        ?>
        <li class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
          <div class="flex flex-wrap items-start justify-between gap-4">
            <div class="min-w-0 flex-1 space-y-2">
              <?php
              $mime = (string) ($a['mime'] ?? '');
              $isImage = str_starts_with($mime, 'image/');
              $isPdf = $mime === 'application/pdf';
              $securePreview = $contentModerationUrl . '/' . $id . '/preview';
              ?>
              <?php if ($isImage): ?>
                <div class="mb-3">
                  <img src="<?= htmlspecialchars($securePreview, ENT_QUOTES, 'UTF-8') ?>" alt="" class="max-h-48 max-w-full rounded-lg border border-slate-200 bg-slate-50 object-contain shadow-inner" loading="lazy" width="320" height="240" />
                </div>
              <?php elseif ($isPdf): ?>
                <div class="mb-3">
                  <a href="<?= htmlspecialchars($securePreview, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener noreferrer" class="inline-flex items-center gap-2 rounded-lg border border-slate-300 bg-slate-50 px-3 py-2 text-sm font-semibold text-slate-800 hover:bg-white">
                    Ouvrir l’aperçu du PDF
                  </a>
                </div>
              <?php endif; ?>
              <p class="text-xs font-bold uppercase tracking-wide text-amber-800">
                Dossier n°<?= $id ?> · <?= htmlspecialchars($displaySource) ?>
              </p>
              <p class="text-sm text-slate-800">
                <span class="text-slate-500">État :</span>
                <strong class="text-slate-900"><?= htmlspecialchars($displayState) ?></strong>
                <span class="text-slate-400 mx-1.5" aria-hidden="true">·</span>
                <span class="text-slate-500">Indicateur :</span>
                <strong class="text-slate-900 tabular-nums"><?= (int) ($a['risk_score'] ?? 0) ?></strong>
              </p>
              <?php if (!empty($a['original_name'])): ?>
                <p class="text-sm text-slate-700">
                  <span class="text-slate-500">Nom du fichier :</span>
                  <?= htmlspecialchars((string) $a['original_name']) ?>
                </p>
              <?php endif; ?>
              <?php if (!empty($a['file_path'])): ?>
                <details class="text-xs text-slate-500">
                  <summary class="cursor-pointer font-medium text-slate-600 hover:text-slate-800">Détails techniques (support)</summary>
                  <p class="mt-2 break-all leading-relaxed pl-1 border-l-2 border-slate-200"><?= htmlspecialchars((string) $a['file_path']) ?></p>
                </details>
              <?php endif; ?>
              <?php
              $codes = $a['reason_codes'] ?? [];
              if (is_array($codes) && $codes !== []):
                  $codeLabels = array_map(static fn ($c) => is_string($c) ? $c : '', $codes);
                  $codeLabels = array_values(array_filter($codeLabels, static fn ($s) => $s !== ''));
              ?>
                <p class="text-sm text-rose-900 bg-rose-50 border border-rose-100 rounded-lg px-3 py-2">
                  <span class="font-semibold">Motifs signalés :</span>
                  <?= htmlspecialchars(implode(', ', $codeLabels)) ?>
                </p>
              <?php endif; ?>
              <div class="mt-4 rounded-lg border border-slate-200 bg-slate-50/80 p-4 space-y-3">
                <p class="text-xs font-bold uppercase tracking-wide text-slate-600">Contexte</p>
                <?php if ($rawSource === ModerationSourceType::FORUM_UPLOAD): ?>
                  <?php if ($forumCtx !== null && $topicLink !== ''): ?>
                    <p class="text-sm text-slate-800">
                      <span class="text-slate-500">Discussion :</span>
                      <strong class="text-slate-900"><?= htmlspecialchars((string) ($forumCtx['topic_title'] ?? ''), ENT_QUOTES, 'UTF-8') ?></strong>
                    </p>
                    <p>
                      <a href="<?= htmlspecialchars($topicLink, ENT_QUOTES, 'UTF-8') ?>" class="inline-flex items-center gap-1.5 text-sm font-semibold text-sky-700 hover:text-sky-900 hover:underline">
                        Ouvrir le message dans la discussion
                      </a>
                    </p>
                  <?php else: ?>
                    <p class="text-sm text-slate-700 leading-relaxed">
                      Le fichier n’est pas encore relié à un message visible (publication ou rattachement en cours). Après publication, le lien vers la discussion apparaîtra ici.
                    </p>
                  <?php endif; ?>
                <?php else: ?>
                  <p class="text-sm text-slate-700 leading-relaxed">
                    Hors pièce jointe forum : pas de lien vers une discussion.
                  </p>
                <?php endif; ?>
                <p class="text-sm text-slate-800 pt-1 border-t border-slate-200/80">
                  <span class="text-slate-500">Auteur du fichier :</span>
                  <?php if ($uploaderLabel !== null): ?>
                    <strong class="text-slate-900"><?= htmlspecialchars($uploaderLabel, ENT_QUOTES, 'UTF-8') ?></strong>
                  <?php else: ?>
                    <span class="text-slate-600">Non identifié</span>
                  <?php endif; ?>
                </p>
              </div>
            </div>
          </div>
          <div class="mt-5 rounded-xl border border-slate-300/80 bg-slate-50 p-4 sm:p-5">
            <p class="text-sm font-bold text-slate-900 mb-4">Actions de modération</p>
            <div class="flex flex-wrap gap-2 mb-5">
              <form method="post" action="<?= htmlspecialchars($contentModerationUrl) ?>/<?= $id ?>/approve" class="inline">
                <?= \App\Core\Csrf::field() ?>
                <button type="submit" class="inline-flex items-center justify-center min-h-[2.5rem] px-4 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold shadow-sm transition-colors">
                  Approuver et publier
                </button>
              </form>
              <form method="post" action="<?= htmlspecialchars($contentModerationUrl) ?>/<?= $id ?>/reject" class="inline-flex flex-col gap-2 items-stretch sm:items-end">
                <?= \App\Core\Csrf::field() ?>
                <label class="sr-only" for="reject-note-<?= $id ?>">Motif du refus (facultatif)</label>
                <input type="text" id="reject-note-<?= $id ?>" name="note" maxlength="2000" placeholder="Motif interne (facultatif)" class="min-w-[12rem] max-w-md rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 placeholder:text-slate-400" autocomplete="off" />
                <button type="submit" class="inline-flex items-center justify-center min-h-[2.5rem] px-4 rounded-lg bg-white border-2 border-rose-300 text-rose-900 hover:bg-rose-50 text-sm font-semibold transition-colors">
                  Refuser et supprimer le fichier
                </button>
              </form>
            </div>
            <?php if ($canSanctionUploader): ?>
              <div class="space-y-4 border-t border-slate-200 pt-4">
                <p class="text-xs text-slate-600 leading-relaxed">
                  Mesures sur le membre (niveau organisation, comme la page
                  <a href="<?= htmlspecialchars(url('back-office/moderation'), ENT_QUOTES, 'UTF-8') ?>" class="font-semibold text-sky-700 hover:underline">Restrictions membres</a>).
                  Vous pouvez aussi lever une mesure depuis cette page dédiée.
                </p>
                <form method="post" action="<?= htmlspecialchars($contentModerationUrl) ?>/<?= $id ?>/warn-uploader" class="rounded-lg border border-amber-200 bg-amber-50/60 p-4 space-y-3">
                  <?= \App\Core\Csrf::field() ?>
                  <p class="text-sm font-semibold text-amber-950">Avertissement sur le dossier</p>
                  <label class="block text-xs font-medium text-slate-700" for="warn-reason-<?= $id ?>">Motif (facultatif, visible des personnes habilitées)</label>
                  <textarea id="warn-reason-<?= $id ?>" name="member_sanction_reason" rows="2" class="w-full max-w-xl rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900"></textarea>
                  <button type="submit" class="inline-flex items-center justify-center min-h-[2.5rem] px-4 rounded-lg bg-amber-700 hover:bg-amber-800 text-white text-sm font-semibold shadow-sm transition-colors">
                    Enregistrer un avertissement
                  </button>
                </form>
                <details class="rounded-lg border border-rose-200 bg-white p-4 group">
                  <summary class="cursor-pointer text-sm font-semibold text-rose-950 list-none flex items-center justify-between gap-2">
                    <span>Limiter l’accès du membre à certains domaines du portail</span>
                    <span class="text-xs font-normal text-slate-500 group-open:hidden">Afficher le formulaire</span>
                    <span class="text-xs font-normal text-slate-500 hidden group-open:inline">Masquer</span>
                  </summary>
                  <form method="post" action="<?= htmlspecialchars($contentModerationUrl) ?>/<?= $id ?>/restrict-uploader" class="mt-4 space-y-3 border-t border-slate-100 pt-4">
                    <?= \App\Core\Csrf::field() ?>
                    <p class="text-xs text-slate-600">Cochez les parties du portail concernées par la restriction dans votre communauté.</p>
                    <div class="grid sm:grid-cols-2 gap-2">
                      <?php foreach ($moduleLabels as $mkey => $mlabel): ?>
                        <label class="flex items-center gap-2 text-sm text-slate-800">
                          <input type="checkbox" name="modules_blocked[]" value="<?= htmlspecialchars($mkey, ENT_QUOTES, 'UTF-8') ?>" class="rounded border-slate-300">
                          <?= htmlspecialchars($mlabel, ENT_QUOTES, 'UTF-8') ?>
                        </label>
                      <?php endforeach; ?>
                    </div>
                    <fieldset class="space-y-2">
                      <legend class="text-xs font-semibold text-slate-700">Durée</legend>
                      <label class="flex items-center gap-2 text-sm">
                        <input type="radio" name="duration_mode" value="permanent" checked class="rounded border-slate-300"> Sans date de fin
                      </label>
                      <label class="flex items-center gap-2 text-sm flex-wrap">
                        <input type="radio" name="duration_mode" value="temporary" class="rounded border-slate-300"> Temporaire :
                        <input type="number" name="duration_days" value="7" min="1" max="3650" class="w-20 rounded border border-slate-300 px-2 py-1 text-sm" title="Nombre de jours"> jours
                      </label>
                    </fieldset>
                    <label class="block text-xs font-medium text-slate-700" for="restrict-reason-<?= $id ?>">Motif (facultatif)</label>
                    <textarea id="restrict-reason-<?= $id ?>" name="member_sanction_reason" rows="2" class="w-full max-w-xl rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900"></textarea>
                    <button type="submit" class="inline-flex items-center justify-center min-h-[2.5rem] px-4 rounded-lg bg-rose-700 hover:bg-rose-800 text-white text-sm font-semibold shadow-sm transition-colors">
                      Enregistrer la restriction
                    </button>
                  </form>
                </details>
              </div>
            <?php elseif ($uploaderId > 0): ?>
              <p class="text-xs text-slate-500 border-t border-slate-200 pt-4">Vous ne pouvez pas vous sanctionner vous-même depuis cette liste.</p>
            <?php endif; ?>
          </div>
        </li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>

  <?php if (!$missingTables && $recentForumPublished !== []): ?>
    <section class="mt-12 border-t border-slate-200 pt-10" aria-labelledby="forum-recent-heading">
      <h2 id="forum-recent-heading" class="text-lg font-bold text-slate-900">Suivi des pièces jointes forum (récentes)</h2>
      <p class="mt-2 text-sm text-slate-600 max-w-2xl">
        Fichiers déjà publiés ou validés après quarantaine. Aucune action requise ici ; l’aperçu sert au contrôle visuel.
      </p>
      <ul class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        <?php foreach ($recentForumPublished as $r): ?>
          <?php
          $rid = (int) ($r['id'] ?? 0);
          $fn = basename(str_replace('\\', '/', (string) ($r['file_path'] ?? '')));
          $pubUrl = $fn !== '' && $fn !== '.' && $fn !== '..' ? url('uploads/forum/' . $fn) : '';
          $rmime = (string) ($r['mime'] ?? '');
          $rIsImage = str_starts_with($rmime, 'image/');
          $rFc = isset($r['forum_context']) && is_array($r['forum_context']) ? $r['forum_context'] : null;
          $rTopicLink = '';
          if ($rFc !== null && (int) ($rFc['topic_id'] ?? 0) > 0 && (int) ($rFc['post_id'] ?? 0) > 0) {
              $rTopicLink = rtrim($baseUrl, '/') . '/forum/topic/' . (int) $rFc['topic_id'] . '#post-' . (int) $rFc['post_id'];
          }
          ?>
          <li class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm flex flex-col gap-2">
            <?php if ($rIsImage && $pubUrl !== ''): ?>
              <a href="<?= htmlspecialchars($pubUrl, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener noreferrer" class="block overflow-hidden rounded-lg border border-slate-100 bg-slate-50 aspect-video">
                <img src="<?= htmlspecialchars($pubUrl, ENT_QUOTES, 'UTF-8') ?>" alt="" class="h-full w-full object-contain" loading="lazy" />
              </a>
            <?php elseif ($rmime === 'application/pdf' && $pubUrl !== ''): ?>
              <a href="<?= htmlspecialchars($pubUrl, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener noreferrer" class="inline-flex items-center justify-center rounded-lg border border-slate-200 bg-slate-50 px-3 py-8 text-sm font-semibold text-slate-700 hover:bg-white">
                Document PDF
              </a>
            <?php endif; ?>
            <?php if (!empty($r['original_name'])): ?>
              <p class="text-xs text-slate-700 truncate" title="<?= htmlspecialchars((string) $r['original_name'], ENT_QUOTES, 'UTF-8') ?>">
                <?= htmlspecialchars((string) $r['original_name']) ?>
              </p>
            <?php endif; ?>
            <?php if ($rTopicLink !== ''): ?>
              <a href="<?= htmlspecialchars($rTopicLink, ENT_QUOTES, 'UTF-8') ?>" class="text-xs font-semibold text-sky-700 hover:underline">Voir dans la discussion</a>
            <?php endif; ?>
            <p class="text-[10px] font-semibold uppercase tracking-wide text-slate-400">Réf. <?= $rid ?></p>
          </li>
        <?php endforeach; ?>
      </ul>
    </section>
  <?php endif; ?>
</div>
