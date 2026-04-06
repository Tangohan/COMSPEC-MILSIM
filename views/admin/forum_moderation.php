<?php
$labels = $forumConfig['labels'] ?? [];
$baseUrl = url('');
$pendingReports = $pendingReports ?? [];
$handledReports = $handledReports ?? [];
$panelTitle = 'Modération forum';
$scopeFilter = $modScopeFilter ?? '';
$pendingCount = count($pendingReports);
$forumModerationLogs = $forumModerationLogs ?? [];
$forumModerationLogsAvailable = $forumModerationLogsAvailable ?? false;
?>
<div class="forum-mod-console w-full max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-8 sm:py-10">
  <!-- Fil d’Ariane -->
  <nav class="flex flex-wrap items-center gap-2 text-[10px] font-bold uppercase tracking-[0.2em] text-slate-500 mb-6">
    <a href="<?= $baseUrl ?>/forum" class="text-emerald-700 hover:text-emerald-600 transition-colors">Forum</a>
    <span class="text-slate-300" aria-hidden="true">/</span>
    <span class="text-slate-800"><?= htmlspecialchars($panelTitle) ?></span>
  </nav>

  <!-- En-tête -->
  <header class="relative overflow-hidden rounded-2xl border border-slate-200/90 bg-white shadow-[0_1px_3px_rgba(15,23,42,0.06)] mb-8">
    <div class="absolute inset-x-0 top-0 h-1 bg-gradient-to-r from-rose-500 via-rose-400 to-amber-400" aria-hidden="true"></div>
    <div class="px-5 py-6 sm:px-8 sm:py-7 md:flex md:items-start md:justify-between md:gap-6">
      <div class="min-w-0">
        <p class="text-[10px] font-black uppercase tracking-[0.35em] text-rose-600/90 mb-2">Console modération</p>
        <h1 class="text-2xl sm:text-3xl font-black text-slate-900 tracking-tight leading-tight">
          <?= htmlspecialchars($panelTitle) ?>
        </h1>
        <p class="mt-2 text-sm text-slate-600 max-w-xl leading-relaxed">
          Traitement des signalements, synthèse des actions et accès aux outils automatiques.
        </p>
      </div>
      <div class="mt-5 md:mt-0 shrink-0 flex flex-col sm:flex-row gap-2">
        <a href="<?= $baseUrl ?>/back-office/forum-moderation"
           class="inline-flex items-center justify-center rounded-lg px-4 py-2 text-[11px] font-bold uppercase tracking-wider border transition <?= $scopeFilter === '' ? 'border-rose-300 bg-rose-50 text-rose-900 shadow-sm' : 'border-slate-200 bg-slate-50 text-slate-600 hover:border-slate-300' ?>">
          Tout
        </a>
        <a href="<?= $baseUrl ?>/back-office/forum-moderation?scope=organization"
           class="inline-flex items-center justify-center rounded-lg px-4 py-2 text-[11px] font-bold uppercase tracking-wider border transition <?= $scopeFilter === 'organization' ? 'border-indigo-400 bg-indigo-50 text-indigo-900 shadow-sm' : 'border-slate-200 bg-slate-50 text-slate-600 hover:border-slate-300' ?>">
          Sections org
        </a>
      </div>
    </div>
  </header>

  <?php $success = \App\Core\Session::getFlash('success'); $error = \App\Core\Session::getFlash('error'); ?>
  <?php if ($success): ?>
    <div class="mb-6 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900 shadow-sm" role="status">
      <?= htmlspecialchars($success) ?>
    </div>
  <?php endif; ?>
  <?php if ($error): ?>
    <div class="mb-6 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-900 shadow-sm" role="alert">
      <?= htmlspecialchars($error) ?>
    </div>
  <?php endif; ?>

  <!-- KPIs -->
  <div class="grid grid-cols-2 sm:grid-cols-3 gap-3 mb-8">
    <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
      <p class="text-[10px] font-black uppercase tracking-wider text-slate-500">En attente</p>
      <p class="mt-1 text-2xl font-black tabular-nums text-rose-700"><?= $pendingCount ?></p>
    </div>
    <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
      <p class="text-[10px] font-black uppercase tracking-wider text-slate-500">Traités (aperçu)</p>
      <p class="mt-1 text-2xl font-black tabular-nums text-slate-800"><?= count($handledReports) ?></p>
    </div>
    <div class="col-span-2 sm:col-span-1 rounded-xl border border-slate-200 bg-gradient-to-br from-slate-50 to-white p-4 shadow-sm">
      <p class="text-[10px] font-black uppercase tracking-wider text-slate-500">File fichiers</p>
      <a href="<?= $baseUrl ?>/admin/content-moderation" class="mt-2 inline-flex text-sm font-bold text-emerald-700 hover:text-emerald-600">
        Quarantaine &amp; scan →
      </a>
    </div>
  </div>

  <!-- Onglets -->
  <div class="rounded-t-xl border border-b-0 border-slate-200 bg-slate-100/80 px-2 pt-2">
    <div class="flex gap-1 overflow-x-auto pb-0" role="tablist">
      <button type="button" role="tab" aria-selected="true"
        class="mod-tab mod-tab--active flex-shrink-0 rounded-t-lg px-4 py-3 text-[11px] font-black uppercase tracking-wider border border-b-0 border-slate-200 bg-white text-slate-900 shadow-sm"
        data-tab="reports">
        Signalements
        <?php if ($pendingCount > 0): ?>
          <span class="ml-2 inline-flex min-w-[1.25rem] justify-center rounded-md bg-rose-100 px-1.5 py-0.5 text-[10px] font-black text-rose-800"><?= $pendingCount ?></span>
        <?php endif; ?>
      </button>
      <button type="button" role="tab" aria-selected="false"
        class="mod-tab flex-shrink-0 rounded-t-lg px-4 py-3 text-[11px] font-black uppercase tracking-wider border border-transparent text-slate-500 hover:text-slate-800 hover:bg-white/60"
        data-tab="detections">
        Détections
      </button>
      <button type="button" role="tab" aria-selected="false"
        class="mod-tab flex-shrink-0 rounded-t-lg px-4 py-3 text-[11px] font-black uppercase tracking-wider border border-transparent text-slate-500 hover:text-slate-800 hover:bg-white/60"
        data-tab="bot">
        Bot &amp; logs
      </button>
    </div>
  </div>

  <div id="mod-panel-reports" class="mod-panel rounded-b-2xl border border-slate-200 bg-white shadow-sm overflow-hidden -mt-px">
    <section>
      <div class="flex items-center gap-3 border-b border-slate-100 bg-slate-50/90 px-5 py-4">
        <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-rose-100 text-rose-700 text-sm" aria-hidden="true">⚑</span>
        <div>
          <h2 class="text-sm font-black uppercase tracking-wider text-slate-900">Signalements en attente</h2>
          <p class="text-[11px] text-slate-500">Prioriser selon gravité et contexte du sujet.</p>
        </div>
      </div>
      <?php if (empty($pendingReports)): ?>
        <div class="px-5 py-14 text-center">
          <p class="text-base font-bold text-emerald-800">Aucun signalement en attente</p>
          <p class="mt-2 text-sm text-slate-500">La file est vide. Les nouveaux signalements apparaîtront ici.</p>
        </div>
      <?php else: ?>
        <ul class="divide-y divide-slate-100">
          <?php foreach ($pendingReports as $r): ?>
            <li class="px-5 py-5 transition hover:bg-slate-50/80">
              <div class="flex flex-wrap items-start justify-between gap-4">
                <div class="min-w-0 flex-1">
                  <div class="flex flex-wrap items-center gap-2">
                    <span class="inline-flex rounded-md border border-slate-200 bg-slate-50 px-2 py-0.5 text-[10px] font-black uppercase text-slate-700">#<?= (int) $r['id'] ?></span>
                    <?php if (!empty($r['report_type'])): ?>
                      <span class="text-[10px] font-bold uppercase tracking-wide text-slate-500"><?= htmlspecialchars($r['report_type']) ?></span>
                    <?php endif; ?>
                    <?php
                    $contentKind = trim((string) ($r['content_kind'] ?? ''));
                    $contentKindFr = match ($contentKind) {
                        'training_course' => 'Formation',
                        'member_profile' => 'Fiche personnelle',
                        'profile_picture' => 'Photo de compte',
                        'operator_visual' => 'Dossier opérateur',
                        'help_page' => 'Aide intégrée',
                        'site_image' => 'Image du site',
                        'portal_help' => 'Aide portail',
                        default => '',
                    };
                    $hasPost = !empty($r['post_id']);
                    $hasUrl = !empty($r['reported_url']);
                    if ($contentKindFr !== ''): ?>
                      <span class="inline-flex rounded-md border border-teal-200 bg-teal-50 px-2 py-0.5 text-[10px] font-black uppercase text-teal-900"><?= htmlspecialchars($contentKindFr) ?></span>
                    <?php elseif ($hasUrl): ?>
                      <span class="inline-flex rounded-md border border-amber-200 bg-amber-50 px-2 py-0.5 text-[10px] font-black uppercase text-amber-900">URL</span>
                    <?php elseif ($hasPost): ?>
                      <span class="inline-flex rounded-md border border-slate-200 bg-white px-2 py-0.5 text-[10px] font-black uppercase text-slate-600">Message</span>
                    <?php else: ?>
                      <span class="inline-flex rounded-md border border-indigo-200 bg-indigo-50 px-2 py-0.5 text-[10px] font-black uppercase text-indigo-900">Sujet</span>
                    <?php endif; ?>
                  </div>
                  <?php if ($hasUrl): ?>
                    <p class="mt-2 text-xs font-semibold text-amber-900 break-all">
                      <a href="<?= htmlspecialchars((string) $r['reported_url']) ?>" target="_blank" rel="noopener noreferrer" class="underline-offset-2 hover:underline"><?= htmlspecialchars((string) $r['reported_url']) ?></a>
                    </p>
                  <?php endif; ?>
                  <p class="mt-2 text-sm text-slate-800 leading-relaxed"><?= nl2br(htmlspecialchars($r['reason'] ?? '—')) ?></p>
                  <?php if (!empty($r['comment'])): ?>
                    <p class="mt-2 text-xs text-slate-600 border-l-2 border-slate-200 pl-3"><?= nl2br(htmlspecialchars($r['comment'])) ?></p>
                  <?php endif; ?>
                  <p class="mt-3 text-[11px] text-slate-500">
                    Par <strong class="text-slate-700"><?= htmlspecialchars($r['reporter_name'] ?? '') ?></strong>
                    · <?= $r['created_at'] ? date('d/m/Y H:i', strtotime($r['created_at'])) : '' ?>
                  </p>
                  <?php
                  $topicId = (int) ($r['post_topic_id'] ?? $r['topic_id'] ?? 0);
                  if ($topicId && !empty($r['topic_title'])):
                  ?>
                    <p class="mt-2 text-xs text-slate-600">
                      Sujet :
                      <a href="<?= $baseUrl ?>/forum/topic/<?= $topicId ?>" class="font-semibold text-emerald-700 hover:text-emerald-600 underline-offset-2 hover:underline"><?= htmlspecialchars($r['topic_title']) ?></a>
                    </p>
                  <?php endif; ?>
                </div>
                <div class="flex flex-wrap items-center gap-2 shrink-0">
                  <?php if ($topicId): ?>
                    <a href="<?= $baseUrl ?>/forum/topic/<?= $topicId ?>" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-[10px] font-black uppercase tracking-wider text-slate-700 shadow-sm hover:border-emerald-300 hover:text-emerald-800">Voir</a>
                  <?php elseif ($hasUrl): ?>
                    <a href="<?= htmlspecialchars((string) $r['reported_url']) ?>" target="_blank" rel="noopener noreferrer" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-[10px] font-black uppercase tracking-wider text-slate-700 shadow-sm hover:border-emerald-300 hover:text-emerald-800">Ouvrir le lien</a>
                  <?php endif; ?>
                  <form method="post" action="<?= $baseUrl ?>/forum/report/<?= (int) $r['id'] ?>/handle" class="inline">
                    <?= \App\Core\Csrf::field() ?>
                    <button type="submit" class="rounded-lg bg-rose-600 px-4 py-2 text-[10px] font-black uppercase tracking-wider text-white shadow-sm hover:bg-rose-500">Marquer traité</button>
                  </form>
                </div>
              </div>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </section>

    <section class="border-t border-slate-200">
      <div class="flex items-center gap-3 border-b border-slate-100 bg-slate-50/90 px-5 py-4">
        <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-slate-200/80 text-slate-700 text-sm" aria-hidden="true">✓</span>
        <div>
          <h2 class="text-sm font-black uppercase tracking-wider text-slate-900">Traités récemment</h2>
          <p class="text-[11px] text-slate-500">Les 15 derniers dossiers clôturés.</p>
        </div>
      </div>
      <?php if (empty($handledReports)): ?>
        <div class="px-5 py-10 text-center text-sm text-slate-500">Aucun historique récent.</div>
      <?php else: ?>
        <ul class="divide-y divide-slate-100">
          <?php foreach ($handledReports as $r): ?>
            <li class="px-5 py-4 text-sm">
              <span class="text-[10px] font-black uppercase text-slate-400">#<?= (int) $r['id'] ?></span>
              <p class="mt-1 text-slate-700 line-clamp-2"><?= htmlspecialchars(mb_substr($r['reason'] ?? '', 0, 160)) ?></p>
              <p class="mt-2 text-[11px] text-slate-500">
                Traité par <strong class="text-slate-700"><?= htmlspecialchars($r['handled_by_name'] ?? '') ?></strong>
                · <?= !empty($r['handled_at']) ? date('d/m/Y H:i', strtotime($r['handled_at'])) : '' ?>
              </p>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </section>
  </div>

  <div id="mod-panel-detections" class="mod-panel hidden rounded-b-2xl rounded-t-none border border-t-0 border-slate-200 bg-white p-8 shadow-sm -mt-px">
    <div class="mx-auto max-w-md text-center">
      <div class="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-2xl bg-amber-50 text-2xl border border-amber-100" aria-hidden="true">◎</div>
      <h3 class="text-lg font-black text-slate-900">Détections automatiques</h3>
      <p class="mt-2 text-sm text-slate-600 leading-relaxed">
        Les règles configurées (<code class="rounded bg-slate-100 px-1.5 py-0.5 text-xs text-slate-800">forum_moderation_rules</code>) alimentent les journaux applicatifs.
        Pour la supervision opérationnelle, privilégiez les exports SQL ou l’API admin selon votre déploiement.
      </p>
    </div>
  </div>

  <div id="mod-panel-bot" class="mod-panel hidden rounded-b-2xl rounded-t-none border border-t-0 border-slate-200 bg-white shadow-sm -mt-px overflow-hidden">
    <div class="border-b border-slate-100 bg-gradient-to-r from-violet-50/90 to-white px-5 py-4 sm:px-6 sm:flex sm:items-center sm:justify-between sm:gap-4">
      <div class="flex items-start gap-3">
        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-violet-100 text-lg text-violet-800 border border-violet-200/80" aria-hidden="true">◇</span>
        <div>
          <h3 class="text-base font-black text-slate-900">Historique bot &amp; heuristique</h3>
          <p class="mt-0.5 text-[12px] text-slate-600">Entrées de <code class="rounded bg-white px-1 py-0.5 text-[11px] text-slate-800 ring-1 ring-slate-200">forum_moderation_logs</code> (moteur <span class="text-slate-500">ForumModerationEngine</span>).</p>
        </div>
      </div>
      <a href="<?= $baseUrl ?>/admin/content-moderation" class="mt-4 sm:mt-0 inline-flex shrink-0 items-center justify-center rounded-lg border border-emerald-200 bg-emerald-600 px-4 py-2 text-[11px] font-black uppercase tracking-wider text-white shadow-sm hover:bg-emerald-500">
        File quarantaine fichiers
      </a>
    </div>

    <div class="px-4 py-5 sm:px-6">
      <?php if (!$forumModerationLogsAvailable): ?>
        <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-950">
          <p class="font-bold">Table <code class="text-xs">forum_moderation_logs</code> absente</p>
          <p class="mt-1 text-[13px] text-amber-900/90">Exécutez les migrations (<code class="rounded bg-white/80 px-1">php run-migrations.php</code> ou import <code class="rounded bg-white/80 px-1">migrations/forum_moderation_bot.sql</code>).</p>
        </div>
      <?php elseif (empty($forumModerationLogs)): ?>
        <div class="rounded-xl border border-slate-200 bg-slate-50 px-5 py-10 text-center">
          <p class="text-sm font-semibold text-slate-800">Aucune entrée récente</p>
          <p class="mt-2 text-xs text-slate-600 max-w-md mx-auto leading-relaxed">Le moteur n’écrit que les actions significatives (ex. score ≥ 0,3 ou <code class="rounded bg-white px-1">flag</code>). Les contenus sans alerte n’apparaissent pas ici.</p>
        </div>
      <?php else: ?>
        <div class="overflow-x-auto rounded-xl border border-slate-200">
          <table class="min-w-full divide-y divide-slate-200 text-left text-[13px]">
            <thead class="bg-slate-50 text-[10px] font-black uppercase tracking-wider text-slate-500">
              <tr>
                <th class="px-3 py-2.5 whitespace-nowrap">Date</th>
                <th class="px-3 py-2.5 whitespace-nowrap">Règle</th>
                <th class="px-3 py-2.5 whitespace-nowrap">Score</th>
                <th class="px-3 py-2.5 whitespace-nowrap">Action</th>
                <th class="px-3 py-2.5 whitespace-nowrap">Auteur</th>
                <th class="px-3 py-2.5">Détail</th>
                <th class="px-3 py-2.5 whitespace-nowrap">Lien</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 bg-white">
              <?php foreach ($forumModerationLogs as $log): ?>
                <?php
                $action = (string) ($log['action_taken'] ?? '');
                $actionClass = match ($action) {
                  'allow' => 'bg-emerald-100 text-emerald-900 ring-1 ring-emerald-200',
                  'flag' => 'bg-amber-100 text-amber-950 ring-1 ring-amber-200',
                  default => 'bg-slate-100 text-slate-800 ring-1 ring-slate-200',
                };
                $author = trim((string) ($log['user_display_name'] ?? ''));
                if ($author === '') {
                  $author = trim((string) ($log['user_callsign'] ?? ''));
                }
                if ($author === '') {
                  $author = !empty($log['user_id']) ? 'Utilisateur #' . (int) $log['user_id'] : '—';
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
                <tr class="hover:bg-slate-50/80">
                  <td class="px-3 py-2.5 align-top whitespace-nowrap text-slate-600 tabular-nums text-[12px]">
                    <?= !empty($log['created_at']) ? htmlspecialchars(date('d/m/Y H:i', strtotime((string) $log['created_at']))) : '—' ?>
                  </td>
                  <td class="px-3 py-2.5 align-top font-mono text-[12px] text-slate-800"><?= htmlspecialchars((string) ($log['rule_type'] ?? '')) ?></td>
                  <td class="px-3 py-2.5 align-top tabular-nums text-slate-700"><?= htmlspecialchars((string) ($log['score'] ?? '')) ?></td>
                  <td class="px-3 py-2.5 align-top">
                    <span class="inline-flex rounded-md px-2 py-0.5 text-[10px] font-black uppercase tracking-wide <?= $actionClass ?>"><?= htmlspecialchars($action) ?></span>
                  </td>
                  <td class="px-3 py-2.5 align-top text-slate-800"><?= htmlspecialchars($author) ?></td>
                  <td class="px-3 py-2.5 align-top text-slate-600 text-[12px] max-w-[14rem] sm:max-w-md">
                    <span class="line-clamp-2" title="<?= htmlspecialchars($detailLine) ?>"><?= htmlspecialchars($detailLine !== '' ? $detailLine : '—') ?></span>
                  </td>
                  <td class="px-3 py-2.5 align-top whitespace-nowrap">
                    <?php if ($topicId > 0): ?>
                      <a href="<?= $baseUrl ?>/forum/topic/<?= $topicId ?>" class="text-[11px] font-bold text-emerald-700 hover:text-emerald-600">Sujet #<?= $topicId ?></a>
                    <?php else: ?>
                      <span class="text-slate-400">—</span>
                    <?php endif; ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <p class="mt-3 text-[11px] text-slate-500">Les 40 dernières lignes pour ce tenant. Pour la quarantaine fichiers / scan, utilisez le bouton ci-dessus.</p>
      <?php endif; ?>
    </div>
  </div>
</div>

<script>
(function() {
  var root = document.querySelector('.forum-mod-console');
  if (!root) return;
  var tabs = root.querySelectorAll('.mod-tab');
  var inactive = 'mod-tab flex-shrink-0 rounded-t-lg px-4 py-3 text-[11px] font-black uppercase tracking-wider border border-transparent text-slate-500 hover:text-slate-800 hover:bg-white/60';
  var active = 'mod-tab mod-tab--active flex-shrink-0 rounded-t-lg px-4 py-3 text-[11px] font-black uppercase tracking-wider border border-b-0 border-slate-200 bg-white text-slate-900 shadow-sm';
  function setActive(selected) {
    var id = selected.getAttribute('data-tab');
    tabs.forEach(function(bt) {
      var on = bt === selected;
      bt.setAttribute('aria-selected', on ? 'true' : 'false');
      bt.className = on ? active : inactive;
    });
    root.querySelectorAll('.mod-panel').forEach(function(p) { p.classList.add('hidden'); });
    var panel = document.getElementById('mod-panel-' + id);
    if (panel) panel.classList.remove('hidden');
  }
  tabs.forEach(function(tab) {
    tab.addEventListener('click', function() { setActive(tab); });
  });
})();
</script>
