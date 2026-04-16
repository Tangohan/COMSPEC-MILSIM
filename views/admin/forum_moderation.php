<?php
declare(strict_types=1);
$labels = $forumConfig['labels'] ?? [];
$baseUrl = url('');
$pendingReports = $pendingReports ?? [];
$handledReports = $handledReports ?? [];
$panelTitle = 'Modération forum';
$scopeFilter = $modScopeFilter ?? '';
$pendingCount = count($pendingReports);
$forumModerationLogs = $forumModerationLogs ?? [];
$forumModerationLogsAvailable = $forumModerationLogsAvailable ?? false;
$reportTimelines = is_array($reportTimelines ?? null) ? $reportTimelines : [];

$reportTypeLabel = static function (?string $raw): string {
    $t = strtolower(trim((string) $raw));
    return match ($t) {
        'spam' => 'Spam',
        'abuse' => 'Abus ou contenu choquant',
        'illegal' => 'Contenu illégal',
        'other' => 'Autre motif',
        '' => '',
        default => 'Motif personnalisé',
    };
};

$botActionMeta = static function (string $action): array {
    $a = strtolower(trim($action));
    return match ($a) {
        'allow' => ['label' => 'Autorisé', 'class' => 'bg-emerald-100 text-emerald-900 ring-1 ring-emerald-200/80'],
        'flag' => ['label' => 'À revoir', 'class' => 'bg-amber-100 text-amber-950 ring-1 ring-amber-200/80'],
        'block', 'reject' => ['label' => 'Refusé', 'class' => 'bg-rose-100 text-rose-900 ring-1 ring-rose-200/80'],
        default => ['label' => $action !== '' ? $action : '—', 'class' => 'bg-slate-100 text-slate-800 ring-1 ring-slate-200/80'],
    };
};

$canForumContentMod = function_exists('forum_user_can_moderate') && forum_user_can_moderate();
$canDeleteForumPost = function_exists('can') && can('forum.post.delete_any');
$gateMod = \App\Core\Gate::getInstance();
$canFormalMemberWarn = function_exists('can') && can('admin.members.moderate');
?>
<div class="forum-mod-console w-full max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-8 sm:py-12 pb-16">
  <nav class="flex flex-wrap items-center gap-2 text-[11px] font-semibold text-slate-500 mb-8" aria-label="Fil d’Ariane">
    <a href="<?= htmlspecialchars($baseUrl . '/back-office', ENT_QUOTES, 'UTF-8') ?>" class="text-slate-600 hover:text-emerald-700 transition-colors">Back-office</a>
    <span class="text-slate-300 select-none" aria-hidden="true">/</span>
    <a href="<?= htmlspecialchars($baseUrl . '/forum', ENT_QUOTES, 'UTF-8') ?>" class="text-slate-600 hover:text-emerald-700 transition-colors">Forum</a>
    <span class="text-slate-300 select-none" aria-hidden="true">/</span>
    <span class="text-slate-900 font-bold"><?= htmlspecialchars($panelTitle, ENT_QUOTES, 'UTF-8') ?></span>
  </nav>

  <header class="relative overflow-hidden rounded-3xl border border-slate-200/90 bg-white shadow-[0_4px_24px_rgba(15,23,42,0.06)] mb-10">
    <div class="absolute inset-x-0 top-0 h-1.5 bg-gradient-to-r from-rose-500 via-orange-400 to-amber-400" aria-hidden="true"></div>
    <div class="px-6 py-8 sm:px-10 sm:py-9">
      <div class="flex flex-col lg:flex-row lg:items-end lg:justify-between gap-8">
        <div class="min-w-0 flex gap-5">
          <div class="hidden sm:flex h-14 w-14 shrink-0 items-center justify-center rounded-2xl bg-gradient-to-br from-rose-500 to-rose-600 text-white shadow-lg shadow-rose-500/25" aria-hidden="true">
            <svg class="h-7 w-7" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" /></svg>
          </div>
          <div class="min-w-0">
            <p class="text-[11px] font-bold uppercase tracking-[0.2em] text-rose-600/90 mb-2">Espace modérateurs</p>
            <h1 class="text-2xl sm:text-3xl font-black text-slate-900 tracking-tight leading-tight">
              <?= htmlspecialchars($panelTitle, ENT_QUOTES, 'UTF-8') ?>
            </h1>
            <p class="mt-3 text-sm sm:text-base text-slate-600 max-w-2xl leading-relaxed">
              Examinez les signalements des membres, consultez l’historique des dossiers clos et, si besoin, ouvrez la file des pièces jointes à valider.
            </p>
          </div>
        </div>
        <div class="shrink-0">
          <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400 mb-2">Portée d’affichage</p>
          <div class="inline-flex rounded-xl border border-slate-200/90 bg-slate-50/80 p-1 shadow-inner" role="group" aria-label="Filtrer les signalements">
            <a href="<?= htmlspecialchars($baseUrl . '/back-office/forum-moderation', ENT_QUOTES, 'UTF-8') ?>"
               class="rounded-lg px-4 py-2.5 text-xs font-bold transition-all <?= $scopeFilter === '' ? 'bg-white text-slate-900 shadow-sm ring-1 ring-slate-200/80' : 'text-slate-600 hover:text-slate-900 hover:bg-white/70' ?>">
              Tout le forum
            </a>
            <a href="<?= htmlspecialchars($baseUrl . '/back-office/forum-moderation?scope=organization', ENT_QUOTES, 'UTF-8') ?>"
               class="rounded-lg px-4 py-2.5 text-xs font-bold transition-all <?= $scopeFilter === 'organization' ? 'bg-white text-indigo-900 shadow-sm ring-1 ring-indigo-200/80' : 'text-slate-600 hover:text-slate-900 hover:bg-white/70' ?>">
              Sections organisation
            </a>
          </div>
        </div>
      </div>
    </div>
  </header>

  <?php $success = \App\Core\Session::getFlash('success'); $error = \App\Core\Session::getFlash('error'); ?>
  <?php if ($success): ?>
    <div class="mb-8 rounded-2xl border border-emerald-200/90 bg-emerald-50/90 px-5 py-4 text-sm text-emerald-950 shadow-sm flex gap-3 items-start" role="status">
      <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-emerald-200/60 text-emerald-800" aria-hidden="true">
        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" /></svg>
      </span>
      <span class="pt-0.5 leading-relaxed"><?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?></span>
    </div>
  <?php endif; ?>
  <?php if ($error): ?>
    <div class="mb-8 rounded-2xl border border-rose-200/90 bg-rose-50/90 px-5 py-4 text-sm text-rose-950 shadow-sm flex gap-3 items-start" role="alert">
      <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-rose-200/60 text-rose-800" aria-hidden="true">
        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z" /></svg>
      </span>
      <span class="pt-0.5 leading-relaxed"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></span>
    </div>
  <?php endif; ?>

  <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-10">
    <div class="rounded-2xl border border-slate-200/90 bg-white p-5 shadow-sm flex gap-4 items-start">
      <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-rose-50 text-rose-600 ring-1 ring-rose-100" aria-hidden="true">
        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" /></svg>
      </span>
      <div>
        <p class="text-[10px] font-bold uppercase tracking-wider text-slate-500">À traiter</p>
        <p class="mt-1 text-3xl font-black tabular-nums text-rose-700 leading-none"><?= $pendingCount ?></p>
        <p class="mt-2 text-xs text-slate-500">Signalements ouverts</p>
      </div>
    </div>
    <div class="rounded-2xl border border-slate-200/90 bg-white p-5 shadow-sm flex gap-4 items-start">
      <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-slate-100 text-slate-600 ring-1 ring-slate-200/80" aria-hidden="true">
        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" /></svg>
      </span>
      <div>
        <p class="text-[10px] font-bold uppercase tracking-wider text-slate-500">Historique court</p>
        <p class="mt-1 text-3xl font-black tabular-nums text-slate-800 leading-none"><?= count($handledReports) ?></p>
        <p class="mt-2 text-xs text-slate-500">Derniers dossiers clos (aperçu)</p>
      </div>
    </div>
    <div class="rounded-2xl border border-emerald-200/70 bg-gradient-to-br from-emerald-50/90 to-white p-5 shadow-sm flex flex-col justify-between min-h-[7.5rem]">
      <div class="flex gap-4 items-start">
        <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-emerald-100 text-emerald-700 ring-1 ring-emerald-200/80" aria-hidden="true">
          <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="m2.25 15.75 5.159-5.159a2.25 2.25 0 0 1 3.182 0l5.159 5.159m-1.5-1.5 1.409-1.409a2.25 2.25 0 0 1 3.182 0l2.909 2.909m-18 3.75h16.5a1.5 1.5 0 0 0 1.5-1.5V6a1.5 1.5 0 0 0-1.5-1.5H3A1.5 1.5 0 0 0 1.5 6v12a1.5 1.5 0 0 0 1.5 1.5Zm10.5-11.25h.008v.008H12V8.25Z" /></svg>
        </span>
        <div>
          <p class="text-[10px] font-bold uppercase tracking-wider text-emerald-800/80">Pièces &amp; fichiers</p>
          <p class="mt-1 text-sm font-semibold text-slate-800 leading-snug">Quarantaine et analyse automatique</p>
        </div>
      </div>
      <a href="<?= htmlspecialchars($baseUrl . '/admin/content-moderation', ENT_QUOTES, 'UTF-8') ?>" class="mt-4 inline-flex items-center gap-2 text-sm font-bold text-emerald-800 hover:text-emerald-700 group">
        Ouvrir la file de validation
        <svg class="h-4 w-4 transition-transform group-hover:translate-x-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3" /></svg>
      </a>
    </div>
  </div>

  <div class="rounded-2xl border border-slate-200/90 bg-slate-100/60 p-1.5 shadow-inner mb-0" role="tablist" aria-label="Sections de la console">
    <div class="flex gap-1 overflow-x-auto pb-0.5">
      <button type="button" role="tab" id="mod-tab-reports" aria-selected="true" aria-controls="mod-panel-reports"
        class="mod-tab mod-tab--active flex-shrink-0 rounded-xl px-4 py-3 text-xs font-bold tracking-wide border border-transparent bg-white text-slate-900 shadow-sm ring-1 ring-slate-200/60 min-w-[8rem] sm:min-w-0 text-center transition-all"
        data-tab="reports">
        Signalements
        <?php if ($pendingCount > 0): ?>
          <span class="ml-1.5 inline-flex min-w-[1.35rem] justify-center rounded-md bg-rose-100 px-1.5 py-0.5 text-[10px] font-black text-rose-800 tabular-nums"><?= $pendingCount ?></span>
        <?php endif; ?>
      </button>
      <button type="button" role="tab" id="mod-tab-detections" aria-selected="false" aria-controls="mod-panel-detections"
        class="mod-tab flex-shrink-0 rounded-xl px-4 py-3 text-xs font-bold tracking-wide border border-transparent text-slate-600 hover:text-slate-900 hover:bg-white/80 min-w-[8rem] sm:min-w-0 text-center transition-all"
        data-tab="detections">
        Vérifications auto
      </button>
      <button type="button" role="tab" id="mod-tab-bot" aria-selected="false" aria-controls="mod-panel-bot"
        class="mod-tab flex-shrink-0 rounded-xl px-4 py-3 text-xs font-bold tracking-wide border border-transparent text-slate-600 hover:text-slate-900 hover:bg-white/80 min-w-[8rem] sm:min-w-0 text-center transition-all"
        data-tab="bot">
        Décisions auto
      </button>
    </div>
  </div>

  <div id="mod-panel-reports" role="tabpanel" aria-labelledby="mod-tab-reports" class="mod-panel rounded-b-3xl rounded-t-none border border-t-0 border-slate-200/90 bg-white shadow-[0_8px_30px_rgba(15,23,42,0.04)] overflow-hidden -mt-px">
    <section>
      <div class="flex flex-wrap items-center gap-4 border-b border-slate-100 bg-gradient-to-r from-slate-50 to-white px-6 py-5">
        <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-rose-100 text-rose-700 ring-1 ring-rose-200/60" aria-hidden="true">
          <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M3 3v1.5M3 21v-6m0 0h18m0 0V21M9 3v1.5m0 0V21m6-18v1.5m0 0V21M9 9h6m-6 3h6m-6 3h6M3 9h.01M3 12h.01M3 15h.01" /></svg>
        </span>
        <div class="min-w-0 flex-1">
          <h2 class="text-base font-black text-slate-900 tracking-tight">File d’attente</h2>
          <p class="text-sm text-slate-500 mt-0.5">Tri recommandé : ancienneté et gravité du motif indiqué par le membre.</p>
        </div>
      </div>
      <?php if (empty($pendingReports)): ?>
        <div class="px-6 py-16 sm:py-20 text-center">
          <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-2xl bg-emerald-50 text-emerald-600 ring-1 ring-emerald-100 mb-5" aria-hidden="true">
            <svg class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.25"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" /></svg>
          </div>
          <p class="text-lg font-bold text-slate-900">Rien en attente</p>
          <p class="mt-2 text-sm text-slate-500 max-w-md mx-auto leading-relaxed">Les nouveaux signalements apparaîtront ici dès qu’un membre en déposera un.</p>
        </div>
      <?php else: ?>
        <ul class="divide-y divide-slate-100">
          <?php foreach ($pendingReports as $r): ?>
            <?php
            $contentKind = trim((string) ($r['content_kind'] ?? ''));
            $contentKindFr = match ($contentKind) {
                'training_course' => 'Formation',
                'member_profile' => 'Fiche personnelle',
                'profile_picture' => 'Photo de compte',
                'operator_visual' => 'Dossier opérateur',
                'help_page' => 'Aide intégrée',
                'site_image' => 'Visuel du site',
                'portal_help' => 'Aide portail',
                default => '',
            };
            $hasPost = !empty($r['post_id']);
            $hasUrl = !empty($r['reported_url']);
            $topicId = (int) ($r['topic_id'] ?? 0) ?: (int) ($r['post_topic_id'] ?? 0);
            $rtLabel = $reportTypeLabel($r['report_type'] ?? null);
            $resolvedTargetUid = function_exists('forum_report_resolve_target_user_id') ? forum_report_resolve_target_user_id($r) : null;
            if ($resolvedTargetUid !== null && (int) ($r['reporter_id'] ?? 0) === $resolvedTargetUid) {
                $resolvedTargetUid = null;
            }
            $showWarnOption = $canFormalMemberWarn && $resolvedTargetUid !== null && $resolvedTargetUid > 0;
            $followUpExtraCount = 0;
            if ($hasPost && $canForumContentMod) {
                ++$followUpExtraCount;
            }
            if ($hasPost && $canDeleteForumPost) {
                ++$followUpExtraCount;
            }
            if ($topicId > 0 && $canForumContentMod) {
                $followUpExtraCount += 2;
            }
            if ($showWarnOption) {
                ++$followUpExtraCount;
            }
            $currentUserId = (int) (\App\Core\Session::get('user_id') ?? 0);
            $assignedToId = (int) ($r['assigned_to'] ?? 0);
            $isAssignedToMe = $assignedToId > 0 && $assignedToId === $currentUserId;
            $isAssigned = $assignedToId > 0;
            $timelineRows = $reportTimelines[(int) ($r['id'] ?? 0)] ?? [];
            ?>
            <li class="px-5 sm:px-6 py-6 sm:py-7 transition-colors hover:bg-slate-50/50">
              <article class="rounded-2xl border border-slate-200/80 bg-slate-50/30 sm:bg-white sm:border-slate-100 p-5 sm:p-6 shadow-sm">
                <div class="flex flex-col lg:flex-row lg:items-stretch gap-6">
                  <div class="min-w-0 flex-1 space-y-4">
                    <div class="flex flex-wrap items-center gap-2">
                      <span class="inline-flex items-center rounded-lg bg-slate-100 px-2.5 py-1 text-[11px] font-bold text-slate-600 tabular-nums">Dossier <?= (int) $r['id'] ?></span>
                      <?php if ($rtLabel !== ''): ?>
                        <span class="inline-flex rounded-lg border border-slate-200 bg-white px-2.5 py-1 text-[11px] font-bold text-slate-700"><?= htmlspecialchars($rtLabel, ENT_QUOTES, 'UTF-8') ?></span>
                      <?php endif; ?>
                      <?php if ($contentKindFr !== ''): ?>
                        <span class="inline-flex rounded-lg border border-teal-200/80 bg-teal-50 px-2.5 py-1 text-[11px] font-bold text-teal-900"><?= htmlspecialchars($contentKindFr, ENT_QUOTES, 'UTF-8') ?></span>
                      <?php elseif ($hasUrl): ?>
                        <span class="inline-flex rounded-lg border border-amber-200/80 bg-amber-50 px-2.5 py-1 text-[11px] font-bold text-amber-950">Page ou ressource externe</span>
                      <?php elseif ($hasPost): ?>
                        <span class="inline-flex rounded-lg border border-slate-200 bg-white px-2.5 py-1 text-[11px] font-bold text-slate-600">Message</span>
                      <?php else: ?>
                        <span class="inline-flex rounded-lg border border-indigo-200/80 bg-indigo-50 px-2.5 py-1 text-[11px] font-bold text-indigo-900">Sujet entier</span>
                      <?php endif; ?>
                    </div>
                    <?php if ($hasUrl): ?>
                      <p class="text-sm">
                        <span class="text-slate-500 font-medium">Lien concerné : </span>
                        <a href="<?= htmlspecialchars((string) $r['reported_url'], ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener noreferrer" class="font-semibold text-emerald-800 break-all underline decoration-emerald-300 underline-offset-2 hover:text-emerald-700"><?= htmlspecialchars((string) $r['reported_url'], ENT_QUOTES, 'UTF-8') ?></a>
                      </p>
                    <?php endif; ?>
                    <div>
                      <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400 mb-1.5">Motif</p>
                      <p class="text-[15px] sm:text-base text-slate-800 leading-relaxed"><?= nl2br(htmlspecialchars($r['reason'] ?? '—', ENT_QUOTES, 'UTF-8')) ?></p>
                    </div>
                    <?php if (!empty($r['comment'])): ?>
                      <blockquote class="border-l-[3px] border-slate-300 pl-4 py-0.5 text-sm text-slate-600 italic">
                        <?= nl2br(htmlspecialchars($r['comment'], ENT_QUOTES, 'UTF-8')) ?>
                      </blockquote>
                    <?php endif; ?>
                    <div class="flex flex-wrap gap-x-4 gap-y-1 text-xs text-slate-500 pt-1">
                      <span><span class="text-slate-400">Signalé par</span> <strong class="text-slate-700 font-semibold"><?= htmlspecialchars((string) ($r['reporter_name'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></strong></span>
                      <span class="text-slate-300 hidden sm:inline" aria-hidden="true">·</span>
                      <span class="tabular-nums"><?= $r['created_at'] ? date('d/m/Y à H:i', strtotime((string) $r['created_at'])) : '' ?></span>
                    </div>
                    <div class="flex flex-wrap items-center gap-2 text-xs">
                      <?php if ($isAssigned): ?>
                        <span class="inline-flex rounded-lg border border-indigo-200 bg-indigo-50 px-2.5 py-1 font-semibold text-indigo-900">
                          En cours : <?= htmlspecialchars((string) ($r['assigned_to_name'] ?? 'Modérateur'), ENT_QUOTES, 'UTF-8') ?>
                        </span>
                      <?php else: ?>
                        <span class="inline-flex rounded-lg border border-slate-200 bg-slate-50 px-2.5 py-1 font-semibold text-slate-600">Non attribué</span>
                      <?php endif; ?>
                      <?php if (!empty($r['assigned_at'])): ?>
                        <span class="text-slate-500 tabular-nums">depuis <?= htmlspecialchars(date('d/m/Y H:i', strtotime((string) $r['assigned_at'])), ENT_QUOTES, 'UTF-8') ?></span>
                      <?php endif; ?>
                    </div>
                    <?php if ($topicId && !empty($r['topic_title'])): ?>
                      <p class="text-sm text-slate-600">
                        <span class="text-slate-400 font-medium">Sujet :</span>
                        <a href="<?= htmlspecialchars($baseUrl . '/forum/topic/' . $topicId, ENT_QUOTES, 'UTF-8') ?>" class="font-semibold text-emerald-800 hover:text-emerald-700 underline decoration-emerald-200 underline-offset-2"><?= htmlspecialchars((string) $r['topic_title'], ENT_QUOTES, 'UTF-8') ?></a>
                      </p>
                    <?php endif; ?>
                    <div class="rounded-xl border border-slate-200/80 bg-white px-4 py-3">
                      <p class="text-[10px] font-bold uppercase tracking-wider text-slate-500 mb-2">Timeline dossier</p>
                      <?php if (empty($timelineRows)): ?>
                        <p class="text-xs text-slate-500">Aucun événement pour l’instant.</p>
                      <?php else: ?>
                        <ul class="space-y-2">
                          <?php foreach ($timelineRows as $evt): ?>
                            <li class="rounded-lg border border-slate-100 bg-slate-50/70 px-3 py-2">
                              <div class="flex flex-wrap items-center gap-2 text-[11px]">
                                <span class="font-semibold text-slate-700"><?= htmlspecialchars((string) ($evt['event_label'] ?? 'Événement'), ENT_QUOTES, 'UTF-8') ?></span>
                                <span class="text-slate-400">·</span>
                                <span class="text-slate-500 tabular-nums"><?= !empty($evt['created_at']) ? htmlspecialchars(date('d/m/Y H:i', strtotime((string) $evt['created_at'])), ENT_QUOTES, 'UTF-8') : '—' ?></span>
                                <span class="text-slate-400">·</span>
                                <span class="text-slate-600"><?= htmlspecialchars((string) (($evt['actor_name'] ?? '') !== '' ? $evt['actor_name'] : 'Système'), ENT_QUOTES, 'UTF-8') ?></span>
                              </div>
                              <?php if (!empty($evt['event_detail'])): ?>
                                <p class="mt-1 text-xs text-slate-600 leading-relaxed"><?= nl2br(htmlspecialchars((string) $evt['event_detail'], ENT_QUOTES, 'UTF-8')) ?></p>
                              <?php endif; ?>
                            </li>
                          <?php endforeach; ?>
                        </ul>
                      <?php endif; ?>
                      <form method="post" action="<?= htmlspecialchars($baseUrl . '/forum/report/' . (int) $r['id'] . '/comment', ENT_QUOTES, 'UTF-8') ?>" class="mt-3 space-y-2">
                        <?= \App\Core\Csrf::field() ?>
                        <textarea name="timeline_comment" rows="2" maxlength="1200" class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs text-slate-800 placeholder:text-slate-400 focus:border-emerald-400 focus:ring-1 focus:ring-emerald-400 outline-none" placeholder="Ajouter un commentaire de traitement (visible dans la timeline)."></textarea>
                        <button type="submit" class="inline-flex rounded-lg border border-slate-200 bg-slate-50 px-3 py-1.5 text-[11px] font-bold text-slate-700 hover:bg-slate-100 transition-colors">Ajouter au dossier</button>
                      </form>
                    </div>
                  </div>
                  <div class="flex flex-col gap-4 shrink-0 lg:w-[min(100%,20rem)] lg:border-l lg:border-slate-100 lg:pl-6">
                    <div class="flex flex-col sm:flex-row lg:flex-col gap-2">
                      <?php if ($topicId): ?>
                        <a href="<?= htmlspecialchars($baseUrl . '/forum/topic/' . $topicId, ENT_QUOTES, 'UTF-8') ?>" class="inline-flex items-center justify-center rounded-xl border-2 border-slate-200 bg-white px-4 py-3 text-xs font-bold text-slate-800 shadow-sm hover:border-emerald-300 hover:bg-emerald-50/50 hover:text-emerald-900 transition-colors text-center">
                          Voir le fil
                        </a>
                      <?php elseif ($hasUrl): ?>
                        <a href="<?= htmlspecialchars((string) $r['reported_url'], ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener noreferrer" class="inline-flex items-center justify-center rounded-xl border-2 border-slate-200 bg-white px-4 py-3 text-xs font-bold text-slate-800 shadow-sm hover:border-emerald-300 hover:bg-emerald-50/50 hover:text-emerald-900 transition-colors text-center">
                          Ouvrir la page
                        </a>
                      <?php endif; ?>
                      <?php if ($canFormalMemberWarn && $resolvedTargetUid): ?>
                        <a href="<?= htmlspecialchars($baseUrl . '/back-office/users/' . (int) $resolvedTargetUid, ENT_QUOTES, 'UTF-8') ?>" class="inline-flex items-center justify-center rounded-xl border border-violet-200 bg-violet-50 px-4 py-3 text-xs font-bold text-violet-950 shadow-sm hover:bg-violet-100 transition-colors text-center">
                          Fiche membre
                        </a>
                      <?php endif; ?>
                    </div>
                    <div class="grid grid-cols-1 gap-2">
                      <?php if (!$isAssigned || !$isAssignedToMe): ?>
                        <form method="post" action="<?= htmlspecialchars($baseUrl . '/forum/report/' . (int) $r['id'] . '/claim', ENT_QUOTES, 'UTF-8') ?>">
                          <?= \App\Core\Csrf::field() ?>
                          <button type="submit" class="w-full rounded-lg border border-indigo-200 bg-indigo-50 px-3 py-2 text-[11px] font-bold text-indigo-900 hover:bg-indigo-100 transition-colors">Prendre en charge</button>
                        </form>
                      <?php endif; ?>
                      <?php if ($isAssignedToMe): ?>
                        <form method="post" action="<?= htmlspecialchars($baseUrl . '/forum/report/' . (int) $r['id'] . '/unclaim', ENT_QUOTES, 'UTF-8') ?>">
                          <?= \App\Core\Csrf::field() ?>
                          <button type="submit" class="w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-[11px] font-bold text-slate-700 hover:bg-slate-100 transition-colors">Relâcher</button>
                        </form>
                      <?php endif; ?>
                    </div>
                    <form method="post" action="<?= htmlspecialchars($baseUrl . '/forum/report/' . (int) $r['id'] . '/handle', ENT_QUOTES, 'UTF-8') ?>" class="forum-mod-close-form space-y-4 rounded-xl border border-slate-200/90 bg-white p-4 shadow-sm">
                      <?= \App\Core\Csrf::field() ?>
                      <?php if ($followUpExtraCount < 1): ?>
                      <p class="text-xs text-slate-600 leading-relaxed rounded-lg bg-slate-50 border border-slate-100 px-3 py-2">
                        Seule la clôture est proposée ici : ce dossier ne porte pas sur un message ou un sujet du fil (ou vos rôles ne permettent pas d’agir sur le forum). Utilisez les liens ci-dessus pour consulter le contenu signalé le cas échéant.
                      </p>
                      <?php endif; ?>
                      <div>
                        <label class="block text-[10px] font-bold uppercase tracking-wider text-slate-500 mb-2" for="follow-up-<?= (int) $r['id'] ?>">Mesure avant clôture</label>
                        <select name="follow_up" id="follow-up-<?= (int) $r['id'] ?>" class="forum-mod-follow-select w-full rounded-xl border border-slate-200 bg-slate-50/50 px-3 py-2.5 text-sm font-medium text-slate-800 shadow-inner focus:border-emerald-400 focus:ring-2 focus:ring-emerald-500/20 outline-none transition">
                          <option value="close">Clôturer sans autre mesure</option>
                          <?php if ($hasPost && $canForumContentMod): ?>
                            <option value="hide_post">Masquer le message signalé</option>
                          <?php endif; ?>
                          <?php if ($hasPost && $canDeleteForumPost): ?>
                            <option value="delete_post">Supprimer le message signalé</option>
                          <?php endif; ?>
                          <?php if ($topicId > 0 && $canForumContentMod): ?>
                            <option value="lock_topic">Verrouiller le sujet</option>
                            <option value="hide_topic">Retirer le sujet de la liste (masquer)</option>
                          <?php endif; ?>
                          <?php if ($showWarnOption): ?>
                            <option value="sanction_warn">Avertissement formel au membre concerné</option>
                          <?php endif; ?>
                        </select>
                      </div>
                      <div class="forum-mod-note-field hidden rounded-lg border border-dashed border-violet-200 bg-violet-50/40 p-3">
                        <label class="block text-[10px] font-bold uppercase tracking-wider text-violet-800 mb-1.5" for="mod-note-<?= (int) $r['id'] ?>">Précision pour le dossier membre (optionnel)</label>
                        <textarea name="moderator_note" id="mod-note-<?= (int) $r['id'] ?>" rows="2" maxlength="500" class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 placeholder:text-slate-400 focus:border-violet-400 focus:ring-1 focus:ring-violet-400 outline-none" placeholder="Sera joint à l’avertissement enregistré."></textarea>
                      </div>
                      <button type="submit" class="w-full inline-flex items-center justify-center gap-2 rounded-xl bg-gradient-to-b from-rose-600 to-rose-700 px-4 py-3 text-xs font-bold text-white shadow-md shadow-rose-600/25 hover:from-rose-500 hover:to-rose-600 transition-colors">
                        <svg class="h-4 w-4 opacity-90 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" /></svg>
                        Appliquer et clôturer
                      </button>
                    </form>
                  </div>
                </div>
              </article>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </section>

    <section class="border-t border-slate-200 bg-slate-50/40">
      <div class="flex flex-wrap items-center gap-4 border-b border-slate-100/80 bg-white/80 px-6 py-5">
        <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-slate-200/80 text-slate-600" aria-hidden="true">
          <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" /></svg>
        </span>
        <div>
          <h2 class="text-base font-black text-slate-900 tracking-tight">Derniers dossiers clos</h2>
          <p class="text-sm text-slate-500 mt-0.5">Les 15 clôtures les plus récentes sur cette communauté.</p>
        </div>
      </div>
      <?php if (empty($handledReports)): ?>
        <div class="px-6 py-12 text-center text-sm text-slate-500">Aucun dossier clos récemment.</div>
      <?php else: ?>
        <ul class="divide-y divide-slate-100 bg-white">
          <?php foreach ($handledReports as $r): ?>
            <?php $handledTimeline = $reportTimelines[(int) ($r['id'] ?? 0)] ?? []; ?>
            <li class="px-6 py-4 flex flex-col sm:flex-row sm:items-center gap-3 sm:gap-6 hover:bg-slate-50/60 transition-colors">
              <div class="shrink-0">
                <span class="inline-flex rounded-lg bg-slate-100 px-2 py-1 text-[10px] font-bold text-slate-500 tabular-nums">#<?= (int) $r['id'] ?></span>
              </div>
              <div class="min-w-0 flex-1">
                <p class="text-sm text-slate-700 line-clamp-2 leading-relaxed"><?= htmlspecialchars(mb_substr((string) ($r['reason'] ?? ''), 0, 200), ENT_QUOTES, 'UTF-8') ?><?= mb_strlen((string) ($r['reason'] ?? '')) > 200 ? '…' : '' ?></p>
                <?php if (!empty($r['last_follow_up_action'])): ?>
                  <p class="mt-1 text-xs text-slate-500">Mesure appliquée : <span class="font-semibold text-slate-700"><?= htmlspecialchars((string) $r['last_follow_up_action'], ENT_QUOTES, 'UTF-8') ?></span></p>
                <?php endif; ?>
                <?php if (!empty($handledTimeline)): ?>
                  <p class="mt-1 text-xs text-slate-500">Dernier événement : <?= htmlspecialchars((string) ($handledTimeline[0]['event_label'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></p>
                <?php endif; ?>
              </div>
              <div class="shrink-0 text-xs text-slate-500 sm:text-right sm:min-w-[10rem]">
                <p class="font-semibold text-slate-700"><?= htmlspecialchars((string) ($r['handled_by_name'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></p>
                <p class="tabular-nums mt-0.5"><?= !empty($r['handled_at']) ? date('d/m/Y H:i', strtotime((string) $r['handled_at'])) : '' ?></p>
              </div>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </section>
  </div>

  <div id="mod-panel-detections" role="tabpanel" aria-labelledby="mod-tab-detections" hidden class="mod-panel rounded-b-3xl rounded-t-none border border-t-0 border-slate-200/90 bg-white shadow-[0_8px_30px_rgba(15,23,42,0.04)] -mt-px overflow-hidden">
    <div class="px-6 sm:px-10 py-14 sm:py-16 max-w-lg mx-auto text-center">
      <div class="mx-auto mb-6 flex h-16 w-16 items-center justify-center rounded-2xl bg-gradient-to-br from-amber-100 to-orange-50 text-amber-700 ring-1 ring-amber-200/80" aria-hidden="true">
        <svg class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.25"><path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904 9 18.75l-.813-2.846a4.5 4.5 0 0 0-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 0 0 3.09-3.09L9 5.25l.813 2.847a4.5 4.5 0 0 0 3.09 3.09L15.75 12l-2.847.813a4.5 4.5 0 0 0-3.09 3.09ZM18.259 8.715 18 9.75l-.259-1.035a3.375 3.375 0 0 0-2.455-2.456L14.25 6l1.036-.259a3.375 3.375 0 0 0 2.455-2.456L18 2.25l.259 1.035a3.375 3.375 0 0 0 2.456 2.456L21.75 6l-1.035.259a3.375 3.375 0 0 0-2.456 2.456Z" /></svg>
      </div>
      <h3 class="text-xl font-black text-slate-900 tracking-tight">Vérifications automatiques</h3>
      <p class="mt-4 text-sm text-slate-600 leading-relaxed">
        Des règles prédéfinies peuvent analyser les publications (mots sensibles, pièces jointes, etc.) et alimenter le journal des décisions automatiques dans l’onglet voisin.
        La configuration fine de ces règles relève de l’équipe qui exploite la plateforme.
      </p>
    </div>
  </div>

  <div id="mod-panel-bot" role="tabpanel" aria-labelledby="mod-tab-bot" hidden class="mod-panel rounded-b-3xl rounded-t-none border border-t-0 border-slate-200/90 bg-white shadow-[0_8px_30px_rgba(15,23,42,0.04)] -mt-px overflow-hidden">
    <div class="border-b border-slate-100 bg-gradient-to-r from-violet-50/90 via-white to-white px-6 py-6 sm:px-8 sm:flex sm:items-center sm:justify-between sm:gap-6">
      <div class="flex items-start gap-4 min-w-0">
        <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-violet-100 text-violet-700 ring-1 ring-violet-200/80" aria-hidden="true">
          <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.25"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 3v1.5M4.5 8.25H3m18 0h-1.5M4.5 12H3m18 0h-1.5m-15 3.75H3m18 0h-1.5M8.25 19.5V21M12 3v1.5m0 15V21m3.75-18v1.5m0 15V21m-9-1.5h10.5a2.25 2.25 0 0 0 2.25-2.25V6.75a2.25 2.25 0 0 0-2.25-2.25H6.75A2.25 2.25 0 0 0 4.5 6.75v10.5a2.25 2.25 0 0 0 2.25 2.25Z" /></svg>
        </span>
        <div class="min-w-0">
          <h3 class="text-lg font-black text-slate-900 tracking-tight">Décisions automatiques</h3>
          <p class="mt-1 text-sm text-slate-600 leading-relaxed max-w-xl">Historique des choix enregistrés lors de la publication des messages (score, motif, lien vers le sujet).</p>
        </div>
      </div>
      <a href="<?= htmlspecialchars($baseUrl . '/admin/content-moderation', ENT_QUOTES, 'UTF-8') ?>" class="mt-5 sm:mt-0 inline-flex shrink-0 items-center justify-center rounded-xl bg-emerald-600 px-5 py-3 text-xs font-bold text-white shadow-md shadow-emerald-600/25 hover:bg-emerald-500 transition-colors">
        File pièces jointes
      </a>
    </div>

    <div class="px-5 py-6 sm:px-8 sm:py-8">
      <?php if (!$forumModerationLogsAvailable): ?>
        <div class="rounded-2xl border border-amber-200/90 bg-amber-50/80 px-6 py-5 text-sm text-amber-950">
          <p class="font-bold text-base">Fonctionnalité non activée</p>
          <p class="mt-2 text-amber-900/90 leading-relaxed">L’historique des décisions automatiques n’est pas disponible sur cette installation. L’équipe technique peut l’activer lors d’une mise à jour de la base de données.</p>
        </div>
      <?php elseif (empty($forumModerationLogs)): ?>
        <div class="rounded-2xl border border-slate-200 bg-slate-50/60 px-8 py-14 text-center">
          <p class="text-base font-semibold text-slate-800">Aucune entrée pour l’instant</p>
          <p class="mt-3 text-sm text-slate-600 max-w-md mx-auto leading-relaxed">Seules les publications ayant déclenché une analyse ou une alerte apparaissent ici. Le fil peut rester vide plusieurs jours si tout se passe normalement.</p>
        </div>
      <?php else: ?>
        <div class="overflow-x-auto rounded-2xl border border-slate-200/90 shadow-sm">
          <table class="min-w-full divide-y divide-slate-200 text-left text-sm">
            <thead class="bg-slate-50/95 text-[10px] font-bold uppercase tracking-wider text-slate-500">
              <tr>
                <th scope="col" class="px-4 py-3.5 whitespace-nowrap">Date</th>
                <th scope="col" class="px-4 py-3.5 whitespace-nowrap">Règle</th>
                <th scope="col" class="px-4 py-3.5 whitespace-nowrap">Indice</th>
                <th scope="col" class="px-4 py-3.5 whitespace-nowrap">Décision</th>
                <th scope="col" class="px-4 py-3.5 whitespace-nowrap">Auteur</th>
                <th scope="col" class="px-4 py-3.5 min-w-[8rem]">Détail</th>
                <th scope="col" class="px-4 py-3.5 whitespace-nowrap">Sujet</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 bg-white">
              <?php foreach ($forumModerationLogs as $log): ?>
                <?php
                $action = (string) ($log['action_taken'] ?? '');
                $am = $botActionMeta($action);
                $author = trim((string) ($log['user_display_name'] ?? ''));
                if ($author === '') {
                    $author = trim((string) ($log['user_callsign'] ?? ''));
                }
                if ($author === '') {
                    $author = !empty($log['user_id']) ? 'Membre nº ' . (int) $log['user_id'] : '—';
                }
                $reasons = $log['detail_parsed']['reasons'] ?? null;
                $detailLine = '';
                if (is_array($reasons)) {
                    $detailLine = implode(', ', array_map('strval', $reasons));
                } elseif (!empty($log['detail_json'])) {
                    $detailLine = mb_strlen((string) $log['detail_json']) > 120
                      ? mb_substr((string) $log['detail_json'], 0, 117) . '…'
                      : (string) $log['detail_json'];
                }
                $topicId = (int) ($log['post_topic_id'] ?? 0);
                ?>
                <tr class="hover:bg-slate-50/70 transition-colors">
                  <td class="px-4 py-3.5 align-top whitespace-nowrap text-slate-600 tabular-nums text-xs">
                    <?= !empty($log['created_at']) ? htmlspecialchars(date('d/m/Y H:i', strtotime((string) $log['created_at'])), ENT_QUOTES, 'UTF-8') : '—' ?>
                  </td>
                  <td class="px-4 py-3.5 align-top text-xs font-semibold text-slate-800"><?= htmlspecialchars(str_replace(['_', '-'], ' ', (string) ($log['rule_type'] ?? '')), ENT_QUOTES, 'UTF-8') ?></td>
                  <td class="px-4 py-3.5 align-top tabular-nums text-slate-700 font-medium"><?= htmlspecialchars((string) ($log['score'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                  <td class="px-4 py-3.5 align-top">
                    <span class="inline-flex rounded-lg px-2.5 py-1 text-[10px] font-bold uppercase tracking-wide <?= $am['class'] ?>"><?= htmlspecialchars($am['label'], ENT_QUOTES, 'UTF-8') ?></span>
                  </td>
                  <td class="px-4 py-3.5 align-top text-slate-800"><?= htmlspecialchars($author, ENT_QUOTES, 'UTF-8') ?></td>
                  <td class="px-4 py-3.5 align-top text-slate-600 text-xs max-w-[12rem] sm:max-w-md">
                    <span class="line-clamp-2" title="<?= htmlspecialchars($detailLine, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($detailLine !== '' ? $detailLine : '—', ENT_QUOTES, 'UTF-8') ?></span>
                  </td>
                  <td class="px-4 py-3.5 align-top whitespace-nowrap">
                    <?php if ($topicId > 0): ?>
                      <a href="<?= htmlspecialchars($baseUrl . '/forum/topic/' . $topicId, ENT_QUOTES, 'UTF-8') ?>" class="text-xs font-bold text-emerald-800 hover:text-emerald-700 underline decoration-emerald-200 underline-offset-2">Ouvrir</a>
                    <?php else: ?>
                      <span class="text-slate-400 text-xs">—</span>
                    <?php endif; ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <p class="mt-4 text-xs text-slate-500 leading-relaxed">Aperçu limité aux 40 dernières lignes pour votre communauté. Pour les fichiers en quarantaine, utilisez le raccourci en haut de page.</p>
      <?php endif; ?>
    </div>
  </div>
</div>

<script>
(function() {
  var root = document.querySelector('.forum-mod-console');
  if (!root) return;
  var tabs = root.querySelectorAll('.mod-tab');
  var panels = root.querySelectorAll('.mod-panel');

  var inactive = 'mod-tab flex-shrink-0 rounded-xl px-4 py-3 text-xs font-bold tracking-wide border border-transparent text-slate-600 hover:text-slate-900 hover:bg-white/80 min-w-[8rem] sm:min-w-0 text-center transition-all';
  var active = 'mod-tab mod-tab--active flex-shrink-0 rounded-xl px-4 py-3 text-xs font-bold tracking-wide border border-transparent bg-white text-slate-900 shadow-sm ring-1 ring-slate-200/60 min-w-[8rem] sm:min-w-0 text-center transition-all';

  function setActive(selected) {
    var id = selected.getAttribute('data-tab');
    tabs.forEach(function(bt) {
      var on = bt === selected;
      bt.setAttribute('aria-selected', on ? 'true' : 'false');
      bt.setAttribute('tabindex', on ? '0' : '-1');
      bt.className = on ? active : inactive;
    });
    panels.forEach(function(p) {
      var show = p.id === 'mod-panel-' + id;
      if (show) {
        p.removeAttribute('hidden');
        p.classList.remove('hidden');
      } else {
        p.setAttribute('hidden', 'hidden');
        p.classList.add('hidden');
      }
    });
    try { selected.focus(); } catch (e) {}
  }

  tabs.forEach(function(tab, i) {
    if (!tab.hasAttribute('tabindex')) tab.setAttribute('tabindex', i === 0 ? '0' : '-1');
    tab.addEventListener('click', function() { setActive(tab); });
    tab.addEventListener('keydown', function(ev) {
      var key = ev.key;
      if (key !== 'ArrowRight' && key !== 'ArrowLeft' && key !== 'Home' && key !== 'End') return;
      ev.preventDefault();
      var n = Array.prototype.indexOf.call(tabs, tab);
      var next = n;
      if (key === 'ArrowRight') next = (n + 1) % tabs.length;
      if (key === 'ArrowLeft') next = (n - 1 + tabs.length) % tabs.length;
      if (key === 'Home') next = 0;
      if (key === 'End') next = tabs.length - 1;
      setActive(tabs[next]);
    });
  });

  root.querySelectorAll('.forum-mod-close-form').forEach(function(f) {
    var sel = f.querySelector('.forum-mod-follow-select');
    var note = f.querySelector('.forum-mod-note-field');
    function syncNote() {
      if (!sel || !note) return;
      note.classList.toggle('hidden', sel.value !== 'sanction_warn');
    }
    if (sel) sel.addEventListener('change', syncNote);
    syncNote();
    f.addEventListener('submit', function(ev) {
      if (sel && sel.value === 'delete_post') {
        if (!confirm('Supprimer définitivement ce message ? Cette opération est irréversible.')) {
          ev.preventDefault();
        }
      }
    });
  });
})();
</script>
