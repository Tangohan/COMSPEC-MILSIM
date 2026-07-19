<?php
declare(strict_types=1);

$enrollments = $enrollments ?? [];
$courses = $courses ?? [];
$selectedCourseId = (int) ($selectedCourseId ?? 0);
$trainingEnrollmentApprovalRights = $trainingEnrollmentApprovalRights ?? [];
$filterExpiring = !empty($enrollmentFilterExpiring);
$filterStatus = (string) ($enrollmentFilterStatus ?? '');
$expiringCount = (int) ($enrollmentExpiringCount ?? 0);
$pendingCount = (int) ($enrollmentPendingCount ?? 0);
$courseCount = count($courses);
$listCount = count($enrollments);
$showCourseColumn = $selectedCourseId < 1;
$hasActiveFilter = $filterExpiring || $filterStatus === 'pending_approval';
$hasListContext = $selectedCourseId > 0 || $hasActiveFilter;

$selectedCourseTitle = '';
foreach ($courses as $c) {
    if ((int) ($c['id'] ?? 0) === $selectedCourseId) {
        $selectedCourseTitle = (string) ($c['title'] ?? '');
        break;
    }
}

$enrollmentStatusLabels = [
    'assigned' => 'Assigné',
    'in_progress' => 'En cours',
    'completed' => 'Terminé',
    'failed' => 'Non validé',
    'expired' => 'Expiré',
    'revoked' => 'Révoqué',
    'withdrawn' => 'Inscription annulée par le membre',
    'pending_approval' => 'En attente de validation',
];

$baseEnrollUrl = training_lms_admin_url('enrollments');
$buildEnrollQs = static function (array $parts) use ($baseEnrollUrl): string {
    $q = array_filter($parts, static fn ($v) => $v !== null && $v !== '' && $v !== 0 && $v !== '0' && $v !== false);
    if ($q === []) {
        return $baseEnrollUrl;
    }

    return $baseEnrollUrl . '?' . http_build_query($q);
};

$filterAllHref = $buildEnrollQs($selectedCourseId > 0 ? ['course_id' => $selectedCourseId] : []);
$filterExpiringHref = $buildEnrollQs(array_filter([
    'course_id' => $selectedCourseId > 0 ? $selectedCourseId : null,
    'expiring' => 1,
]));
$filterPendingHref = $buildEnrollQs(array_filter([
    'course_id' => $selectedCourseId > 0 ? $selectedCourseId : null,
    'status' => 'pending_approval',
]));

$headerLead = 'Pilotez les inscriptions, validez les demandes et surveillez les échéances.';
if ($filterExpiring && $selectedCourseId > 0 && $selectedCourseTitle !== '') {
    $headerLead = 'Échéances à surveiller pour « ' . $selectedCourseTitle . ' » (sous 30 jours ou déjà dépassées).';
} elseif ($filterExpiring) {
    $headerLead = 'Inscriptions qui expirent sous 30 jours ou sont déjà expirées, toutes formations confondues.';
} elseif ($filterStatus === 'pending_approval' && $selectedCourseId > 0 && $selectedCourseTitle !== '') {
    $headerLead = 'Demandes d’accès en attente pour « ' . $selectedCourseTitle . ' ».';
} elseif ($filterStatus === 'pending_approval') {
    $headerLead = 'Demandes d’accès en attente de validation, toutes formations confondues.';
} elseif ($selectedCourseId > 0 && $selectedCourseTitle !== '') {
    $headerLead = 'Inscriptions et progression pour « ' . $selectedCourseTitle . ' ».';
}

require base_path('views/admin/training/partials/command_shell_open.php');
?>
                <section class="tc-panel tc-enroll" aria-labelledby="tc-enroll-title">
                    <div class="tc-enroll__head">
                        <div class="tc-enroll__head-copy">
                            <p class="tc-kicker">Suivi</p>
                            <h1 id="tc-enroll-title" class="tc-hero-title mb-2">Assignations &amp; progression</h1>
                            <p class="tc-enroll__lead"><?= htmlspecialchars($headerLead, ENT_QUOTES, 'UTF-8') ?></p>
                        </div>
                        <div class="tc-enroll__metrics" aria-label="Indicateurs rapides">
                            <div class="tc-enroll__metric">
                                <p class="tc-enroll__metric-k">Formations</p>
                                <p class="tc-enroll__metric-v"><?= $courseCount ?></p>
                            </div>
                            <a href="<?= htmlspecialchars($filterPendingHref, ENT_QUOTES, 'UTF-8') ?>" class="tc-enroll__metric tc-enroll__metric--link<?= $filterStatus === 'pending_approval' ? ' is-active' : '' ?>">
                                <p class="tc-enroll__metric-k">À valider</p>
                                <p class="tc-enroll__metric-v<?= $pendingCount > 0 ? ' text-violet-700' : '' ?>"><?= $pendingCount ?></p>
                            </a>
                            <a href="<?= htmlspecialchars($filterExpiringHref, ENT_QUOTES, 'UTF-8') ?>" class="tc-enroll__metric tc-enroll__metric--link<?= $filterExpiring ? ' is-active is-amber' : '' ?>">
                                <p class="tc-enroll__metric-k">À surveiller</p>
                                <p class="tc-enroll__metric-v<?= $expiringCount > 0 ? ' text-amber-600' : '' ?>"><?= $expiringCount ?></p>
                            </a>
                        </div>
                    </div>

                    <div class="tc-enroll__toolbar">
                        <form method="get" action="<?= htmlspecialchars($baseEnrollUrl, ENT_QUOTES, 'UTF-8') ?>" class="tc-enroll__picker" data-tc-course-picker>
                            <?php if ($filterExpiring): ?>
                            <input type="hidden" name="expiring" value="1">
                            <?php endif; ?>
                            <?php if ($filterStatus === 'pending_approval'): ?>
                            <input type="hidden" name="status" value="pending_approval">
                            <?php endif; ?>
                            <label class="tc-enroll__label" for="tc-enroll-course-select">Formation</label>
                            <div class="tc-enroll__picker-row">
                                <div class="tc-enroll__combobox" data-tc-course-combobox>
                                    <input
                                        type="search"
                                        id="tc-enroll-course-search"
                                        class="tc-enroll__search"
                                        placeholder="Rechercher une formation…"
                                        autocomplete="off"
                                        value="<?= htmlspecialchars($selectedCourseTitle, ENT_QUOTES, 'UTF-8') ?>"
                                        data-tc-course-search
                                        aria-autocomplete="list"
                                        aria-controls="tc-enroll-course-list"
                                        aria-expanded="false"
                                        hidden
                                    >
                                    <select
                                        id="tc-enroll-course-select"
                                        name="course_id"
                                        class="tc-enroll__select-fallback"
                                        data-tc-course-select
                                    >
                                        <option value="0">Toutes les formations</option>
                                        <?php foreach ($courses as $c):
                                            $cid = (int) ($c['id'] ?? 0);
                                            $ctitle = (string) ($c['title'] ?? '');
                                            ?>
                                        <option
                                            value="<?= $cid ?>"
                                            data-label="<?= htmlspecialchars($ctitle, ENT_QUOTES, 'UTF-8') ?>"
                                            data-search="<?= htmlspecialchars(mb_strtolower($ctitle, 'UTF-8'), ENT_QUOTES, 'UTF-8') ?>"
                                            <?= $selectedCourseId === $cid ? 'selected' : '' ?>
                                        ><?= htmlspecialchars($ctitle, ENT_QUOTES, 'UTF-8') ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <ul id="tc-enroll-course-list" class="tc-enroll__options" role="listbox" hidden data-tc-course-list></ul>
                                </div>
                                <button type="submit" class="tc-enroll__submit">Afficher</button>
                            </div>
                        </form>

                        <div class="tc-enroll__chips" role="group" aria-label="Filtres d’affichage">
                            <a href="<?= htmlspecialchars($filterAllHref, ENT_QUOTES, 'UTF-8') ?>" class="tc-enroll__chip<?= !$hasActiveFilter ? ' is-active' : '' ?>">Toutes</a>
                            <a href="<?= htmlspecialchars($filterPendingHref, ENT_QUOTES, 'UTF-8') ?>" class="tc-enroll__chip<?= $filterStatus === 'pending_approval' ? ' is-active' : '' ?>">
                                À valider<?php if ($pendingCount > 0): ?><span class="tc-enroll__chip-count"><?= $pendingCount ?></span><?php endif; ?>
                            </a>
                            <a href="<?= htmlspecialchars($filterExpiringHref, ENT_QUOTES, 'UTF-8') ?>" class="tc-enroll__chip tc-enroll__chip--amber<?= $filterExpiring ? ' is-active' : '' ?>">
                                À surveiller<?php if ($expiringCount > 0): ?><span class="tc-enroll__chip-count"><?= $expiringCount ?></span><?php endif; ?>
                            </a>
                        </div>
                    </div>

                    <?php if (!$hasListContext): ?>
                    <div class="tc-enroll__empty">
                        <div class="tc-enroll__empty-icon" aria-hidden="true">
                            <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75"><path stroke-linecap="round" stroke-linejoin="round" d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path stroke-linecap="round" stroke-linejoin="round" d="M23 21v-2a4 4 0 00-3-3.87M16 3.13a4 4 0 010 7.75"/></svg>
                        </div>
                        <h2 class="tc-enroll__empty-title">Choisissez une formation pour démarrer</h2>
                        <p class="tc-enroll__empty-text">
                            Recherchez un parcours ci-dessus pour afficher les inscriptions et la progression.
                            Vous pouvez aussi ouvrir directement les demandes à valider ou les échéances à surveiller.
                        </p>
                        <div class="tc-enroll__empty-actions">
                            <a href="<?= htmlspecialchars($filterPendingHref, ENT_QUOTES, 'UTF-8') ?>" class="tc-btn-primary tc-btn-emerald">Voir les demandes à valider</a>
                            <a href="<?= htmlspecialchars($filterExpiringHref, ENT_QUOTES, 'UTF-8') ?>" class="tc-btn-primary tc-btn-ghost">Voir les échéances</a>
                        </div>
                    </div>
                    <?php elseif ($listCount === 0): ?>
                    <div class="tc-enroll__empty tc-enroll__empty--compact">
                        <h2 class="tc-enroll__empty-title">
                            <?php if ($filterExpiring): ?>
                            Aucune échéance à surveiller
                            <?php elseif ($filterStatus === 'pending_approval'): ?>
                            Aucune demande en attente
                            <?php else: ?>
                            Aucune inscription pour cette formation
                            <?php endif; ?>
                        </h2>
                        <p class="tc-enroll__empty-text">
                            <?php if ($filterExpiring): ?>
                            Aucune inscription n’expire sous 30 jours pour le filtre actuel.
                            <?php elseif ($filterStatus === 'pending_approval'): ?>
                            Toutes les demandes ont déjà été traitées pour ce filtre.
                            <?php else: ?>
                            Dès qu’un membre sera assigné ou s’inscrira, il apparaîtra ici.
                            <?php endif; ?>
                        </p>
                        <?php if ($hasActiveFilter): ?>
                        <div class="tc-enroll__empty-actions">
                            <a href="<?= htmlspecialchars($filterAllHref, ENT_QUOTES, 'UTF-8') ?>" class="tc-btn-primary tc-btn-ghost">Retirer le filtre</a>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php else: ?>
                    <div class="tc-enroll__list-meta">
                        <p class="tc-enroll__list-count">
                            <strong><?= $listCount ?></strong>
                            inscription<?= $listCount > 1 ? 's' : '' ?>
                            <?php if ($filterExpiring): ?>à surveiller<?php elseif ($filterStatus === 'pending_approval'): ?>en attente<?php endif; ?>
                        </p>
                    </div>
                    <div class="tc-table-wrap tc-enroll__table overflow-x-auto">
                        <table class="min-w-[720px]">
                            <thead>
                                <tr>
                                    <?php if ($showCourseColumn): ?>
                                    <th>Formation</th>
                                    <?php endif; ?>
                                    <th>Apprenant</th>
                                    <th>Statut</th>
                                    <th>Origine</th>
                                    <th>Motivation</th>
                                    <th>Assigné le</th>
                                    <th>Expire</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($enrollments as $e):
                                    $st = (string) ($e['status'] ?? '');
                                    $stLab = $enrollmentStatusLabels[$st] ?? $st;
                                    $eid = (int) ($e['id'] ?? 0);
                                    $canAct = $st === 'pending_approval' && $eid > 0 && !empty($trainingEnrollmentApprovalRights[$eid]);
                                    $at = (string) ($e['assignment_type'] ?? '');
                                    $atLab = match ($at) {
                                        'manual' => 'Manuel',
                                        'self_enroll' => 'Auto-inscription',
                                        'role' => 'Par rôle',
                                        'unit' => 'Par unité',
                                        'campaign' => 'Campagne',
                                        default => $at !== '' ? $at : '—',
                                    };
                                    $mot = trim((string) ($e['motivation_text'] ?? ''));
                                    $expiresAt = (string) ($e['expires_at'] ?? '');
                                    $expiresSoon = false;
                                    if ($expiresAt !== '') {
                                        $expTs = strtotime($expiresAt);
                                        $expiresSoon = $expTs !== false && $expTs <= (time() + 30 * 86400);
                                    }
                                    $badgeClass = match (true) {
                                        $st === 'completed' => 'tc-enroll__badge tc-enroll__badge--ok',
                                        in_array($st, ['revoked', 'expired', 'withdrawn'], true) => 'tc-enroll__badge tc-enroll__badge--muted',
                                        $st === 'pending_approval' => 'tc-enroll__badge tc-enroll__badge--pending',
                                        default => 'tc-enroll__badge tc-enroll__badge--progress',
                                    };
                                    ?>
                                <tr>
                                    <?php if ($showCourseColumn): ?>
                                    <td class="font-medium text-slate-900">
                                        <?php
                                        $rowCourseId = (int) ($e['course_id'] ?? 0);
                                        $rowCourseTitle = (string) ($e['course_title'] ?? '');
                                        if ($rowCourseId > 0):
                                            ?>
                                        <a href="<?= htmlspecialchars($buildEnrollQs(['course_id' => $rowCourseId]), ENT_QUOTES, 'UTF-8') ?>" class="tc-enroll__course-link"><?= htmlspecialchars($rowCourseTitle !== '' ? $rowCourseTitle : 'Formation', ENT_QUOTES, 'UTF-8') ?></a>
                                        <?php else: ?>
                                        <?= htmlspecialchars($rowCourseTitle !== '' ? $rowCourseTitle : '—', ENT_QUOTES, 'UTF-8') ?>
                                        <?php endif; ?>
                                    </td>
                                    <?php endif; ?>
                                    <td class="font-medium text-slate-900"><?= htmlspecialchars((string) ($e['display_name'] ?? $e['email'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                                    <td><span class="<?= htmlspecialchars($badgeClass, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($stLab, ENT_QUOTES, 'UTF-8') ?></span></td>
                                    <td class="text-slate-600"><?= htmlspecialchars($atLab, ENT_QUOTES, 'UTF-8') ?></td>
                                    <td class="text-slate-600 text-sm max-w-xs">
                                        <?php if ($mot === ''): ?>
                                        —
                                        <?php else:
                                            $ex = mb_strlen($mot) > 120 ? mb_substr($mot, 0, 117) . '…' : $mot;
                                            ?>
                                        <span title="<?= htmlspecialchars($mot, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($ex, ENT_QUOTES, 'UTF-8') ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-slate-500 text-sm whitespace-nowrap"><?= !empty($e['assigned_at']) ? date('d/m/Y', strtotime((string) $e['assigned_at'])) : '—' ?></td>
                                    <td class="text-sm whitespace-nowrap <?= $expiresSoon ? 'text-amber-700 font-semibold' : 'text-slate-500' ?>">
                                        <?= $expiresAt !== '' ? date('d/m/Y', strtotime($expiresAt)) : '—' ?>
                                    </td>
                                    <td class="text-slate-600 text-sm">
                                        <?php if ($canAct): ?>
                                        <div class="flex flex-wrap gap-2">
                                            <form method="post" action="<?= htmlspecialchars(training_lms_admin_url('enrollments/' . $eid . '/approve'), ENT_QUOTES, 'UTF-8') ?>" class="inline">
                                                <?= \App\Core\Csrf::field() ?>
                                                <button type="submit" class="tc-enroll__act tc-enroll__act--ok">Valider</button>
                                            </form>
                                            <form method="post" action="<?= htmlspecialchars(training_lms_admin_url('enrollments/' . $eid . '/decline'), ENT_QUOTES, 'UTF-8') ?>" class="inline" onsubmit="return confirm('Refuser cette inscription ?');">
                                                <?= \App\Core\Csrf::field() ?>
                                                <button type="submit" class="tc-enroll__act tc-enroll__act--ghost">Refuser</button>
                                            </form>
                                        </div>
                                        <?php else: ?>
                                        <span class="text-slate-400">—</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php endif; ?>
                </section>

                <p class="text-sm text-slate-500">
                    <a href="<?= htmlspecialchars(training_lms_admin_url(), ENT_QUOTES, 'UTF-8') ?>" class="font-semibold text-slate-700 underline decoration-slate-300 hover:text-emerald-800">← Vue d’ensemble</a>
                </p>

<script>
(function () {
  var root = document.querySelector('[data-tc-course-picker]');
  if (!root) return;
  var search = root.querySelector('[data-tc-course-search]');
  var list = root.querySelector('[data-tc-course-list]');
  var select = root.querySelector('[data-tc-course-select]');
  if (!search || !list || !select) return;

  var source = Array.prototype.slice.call(select.options).map(function (opt) {
    return {
      id: String(opt.value || '0'),
      label: opt.getAttribute('data-label') || (opt.value === '0' ? 'Toutes les formations' : (opt.textContent || '')),
      search: (opt.getAttribute('data-search') || opt.textContent || '').toLowerCase()
    };
  });

  search.hidden = false;
  select.classList.add('tc-enroll__select-fallback--enhanced');
  select.setAttribute('tabindex', '-1');
  select.setAttribute('aria-hidden', 'true');
  var label = root.querySelector('.tc-enroll__label');
  if (label) label.setAttribute('for', 'tc-enroll-course-search');

  function selectedLabel() {
    var cur = source.find(function (o) { return o.id === String(select.value || '0'); });
    return cur && cur.id !== '0' ? cur.label : '';
  }
  search.value = selectedLabel();

  function openList() {
    list.hidden = false;
    search.setAttribute('aria-expanded', 'true');
  }
  function closeList() {
    list.hidden = true;
    search.setAttribute('aria-expanded', 'false');
  }
  function render(q) {
    q = (q || '').toLowerCase().trim();
    list.innerHTML = '';
    source.forEach(function (item) {
      if (item.id !== '0' && q && item.search.indexOf(q) === -1) return;
      var li = document.createElement('li');
      li.setAttribute('role', 'option');
      li.className = 'tc-enroll__option' + (item.id === String(select.value) ? ' is-selected' : '');
      li.setAttribute('data-id', item.id);
      li.setAttribute('data-label', item.label);
      li.textContent = item.label;
      list.appendChild(li);
    });
    if (!list.children.length) {
      var empty = document.createElement('li');
      empty.className = 'tc-enroll__option';
      empty.setAttribute('aria-disabled', 'true');
      empty.textContent = 'Aucune formation ne correspond';
      list.appendChild(empty);
    }
  }

  search.addEventListener('focus', function () {
    render(search.value);
    openList();
  });
  search.addEventListener('input', function () {
    render(search.value);
    openList();
  });
  search.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') {
      closeList();
      search.blur();
    }
  });

  list.addEventListener('mousedown', function (e) {
    var opt = e.target.closest('[data-id]');
    if (!opt) return;
    e.preventDefault();
    select.value = opt.getAttribute('data-id') || '0';
    search.value = opt.getAttribute('data-label') || '';
    closeList();
    root.submit();
  });

  document.addEventListener('click', function (e) {
    if (!root.contains(e.target)) closeList();
  });
})();
</script>
<?php require base_path('views/admin/training/partials/command_shell_close.php'); ?>
