<?php

declare(strict_types=1);

/**
 * Onglet Unité & rôle — édition fiche personnel.
 *
 * @var bool $orbatStaffMode
 * @var bool $orbatFrozen
 * @var string $orbatFieldAttr
 * @var bool $pendingOrbatCorrection
 * @var bool $canApplyOrbatImmediately
 * @var list<array<string, mixed>> $orbatCorrectionHistory
 * @var array<string, mixed>|null $targetUser
 * @var list<array<string, mixed>> $personnelAssignments
 * @var list<array<string, mixed>> $currentUnitAssignments
 * @var list<array<string, mixed>> $units
 * @var list<array<string, mixed>> $grades
 * @var list<array<string, mixed>> $dossierPresets
 * @var list<array<string, mixed>> $jobRoleOptions
 * @var list<array<string, mixed>> $currentJobRoles
 * @var bool $jobRolesEnabled
 * @var int $maxJobRolesPerMember
 * @var int $maxUnitAssignmentsPerMember
 * @var int $currentGradeId
 * @var string $gradeLabel
 * @var string $gradeLabelSummary
 * @var string $primaryAssignmentLabel
 * @var string $primaryRoleLabel
 * @var string $primaryJobLabel
 * @var array<string, mixed> $p
 */

$orbatStaffMode = !empty($orbatStaffMode);
$orbatFrozen = !empty($orbatFrozen);
$orbatFieldAttr = $orbatFrozen ? ' disabled' : '';
$orbatCorrectionHistory = is_array($orbatCorrectionHistory ?? null) ? $orbatCorrectionHistory : [];
$targetUserId = (int) (($targetUser['id'] ?? 0));
$openOrbatRequests = [];
foreach ($orbatCorrectionHistory as $histRow) {
    if (!is_array($histRow)) {
        continue;
    }
    if (trim((string) ($histRow['status'] ?? '')) === 'pending') {
        $openOrbatRequests[] = $histRow;
    }
}
$h = static fn (mixed $v): string => htmlspecialchars(trim((string) $v), ENT_QUOTES, 'UTF-8');
$statusFr = static function (string $status): string {
    return match ($status) {
        'pending' => 'En attente',
        'approved' => 'Confirmée',
        'rejected' => 'Refusée',
        'cancelled' => 'Annulée',
        default => $status,
    };
};
$formatWhen = static function (mixed $raw): string {
    $raw = trim((string) $raw);
    if ($raw === '') {
        return '';
    }
    try {
        return (new DateTimeImmutable($raw))->format('d/m/Y à H:i');
    } catch (Throwable) {
        return $raw;
    }
};
?>
<section id="edit-orbat" x-show="tab === 'edit-orbat'" class="scroll-mt-24 overflow-hidden rounded-2xl border border-cyan-200/90 bg-white shadow-sm ring-1 ring-cyan-900/[0.04]">
  <div class="pd-orbat-hero">
    <div class="pd-orbat-hero__veil" aria-hidden="true"></div>
    <div class="pd-orbat-hero__inner">
      <p class="pd-orbat-hero__kicker"><?= $orbatStaffMode ? 'Gestion du dossier' : 'Votre situation' ?></p>
      <h2 class="pd-orbat-hero__title">Unité &amp; rôle</h2>
      <?php if ($orbatStaffMode): ?>
      <p class="pd-orbat-hero__lead">Vous appliquez les changements tout de suite : unité, emploi, grade et date d’engagement sont enregistrés sur le dossier.</p>
      <?php else: ?>
      <p class="pd-orbat-hero__lead">Vous consultez ici votre situation. Pour la modifier, vous envoyez une demande : l’encadrement confirme avant que le dossier change.</p>
      <?php endif; ?>
      <div class="pd-orbat-hero__stats">
        <div>
          <span>Unité principale</span>
          <strong><?= $h($primaryAssignmentLabel) ?></strong>
        </div>
        <div>
          <span>Place</span>
          <strong><?= $h($primaryRoleLabel) ?></strong>
        </div>
        <div>
          <span>Emploi</span>
          <strong><?= $h($primaryJobLabel) ?></strong>
        </div>
        <div>
          <span>Grade</span>
          <strong><?= $h($gradeLabelSummary) ?></strong>
        </div>
      </div>
    </div>
  </div>

  <div class="space-y-6 p-6">
    <?php if ($openOrbatRequests !== []): ?>
    <section class="pd-orbat-block pd-orbat-block--pending" aria-labelledby="pd-orbat-pending-title">
      <header class="pd-orbat-block__head">
        <h3 id="pd-orbat-pending-title">Demande en attente</h3>
        <p>Voici ce que vous avez proposé. Un responsable confirmera ou refusera. Vous pouvez annuler tant que la demande n’est pas traitée.</p>
      </header>
      <?php foreach ($openOrbatRequests as $openReq):
          $openId = (int) ($openReq['id'] ?? 0);
          $openWhen = $formatWhen($openReq['created_at'] ?? '');
          $openNote = trim((string) ($openReq['note'] ?? ''));
          $openDiff = is_array($openReq['diff_lines'] ?? null) ? $openReq['diff_lines'] : [];
          $openRequester = trim((string) ($openReq['requester_label'] ?? 'Vous'));
          ?>
      <article class="pd-orbat-request">
        <div class="pd-orbat-request__meta">
          <span class="pd-orbat-badge pd-orbat-badge--warn">En attente</span>
          <?php if ($openWhen !== ''): ?>
          <span>Déposée le <?= $h($openWhen) ?></span>
          <?php endif; ?>
          <span>Par <?= $h($openRequester) ?></span>
        </div>
        <?php if ($openDiff !== []): ?>
        <ul class="pd-orbat-request__diff">
          <?php foreach ($openDiff as $line): ?>
          <li><?= $h((string) $line) ?></li>
          <?php endforeach; ?>
        </ul>
        <?php else: ?>
        <p class="text-xs text-slate-600">Aucun détail de modification n’a été conservé pour cette demande.</p>
        <?php endif; ?>
        <?php if ($openNote !== ''): ?>
        <p class="pd-orbat-request__note"><strong>Message :</strong> <?= $h($openNote) ?></p>
        <?php endif; ?>
        <?php if (!$orbatStaffMode && $openId > 0 && $targetUserId > 0): ?>
        <div class="pd-orbat-request__actions">
          <button
            type="submit"
            form="orbat-cancel-<?= (int) $openId ?>"
            class="pd-orbat-btn pd-orbat-btn--danger"
            onclick="return confirm('Annuler cette demande ? Vous pourrez en envoyer une nouvelle ensuite.');"
          >Annuler la demande</button>
        </div>
        <?php elseif ($orbatStaffMode): ?>
        <p class="mt-2 text-xs"><a class="font-semibold underline" href="<?= $h(url('back-office/personnel/corrections')) ?>">Traiter dans les demandes de correction</a></p>
        <?php endif; ?>
      </article>
      <?php endforeach; ?>
    </section>
    <?php elseif ($orbatFrozen): ?>
    <div class="pd-orbat-banner pd-orbat-banner--warn" role="status">
      <p class="pd-orbat-banner__title">Une demande est déjà en attente</p>
      <p>Un responsable doit d’abord confirmer ou refuser la demande en cours. Vous ne pouvez pas en envoyer une autre tant qu’elle n’est pas traitée.</p>
    </div>
    <?php elseif (!$orbatStaffMode): ?>
    <div class="pd-orbat-banner pd-orbat-banner--info" role="note">
      <p class="pd-orbat-banner__title">Validation Ressources humaines</p>
      <p>Changer l’unité, l’emploi, le grade ou la date d’engagement envoie une demande. Rien n’est écrit sur le dossier tant qu’un responsable n’a pas confirmé.</p>
    </div>
    <?php endif; ?>

    <?php if ($orbatCorrectionHistory !== []): ?>
    <section class="pd-orbat-block" aria-labelledby="pd-orbat-history-title">
      <header class="pd-orbat-block__head">
        <h3 id="pd-orbat-history-title">Historique des demandes</h3>
        <p>Les dernières demandes sur cette fiche (en attente, confirmées, refusées ou annulées).</p>
      </header>
      <div class="pd-orbat-table-wrap">
        <table class="pd-orbat-table">
          <thead>
            <tr>
              <th scope="col">Statut</th>
              <th scope="col">Déposée</th>
              <th scope="col">Changements</th>
              <th scope="col">Décision</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($orbatCorrectionHistory as $hist):
                if (!is_array($hist)) {
                    continue;
                }
                $st = trim((string) ($hist['status'] ?? ''));
                $whenFr = $formatWhen($hist['created_at'] ?? '');
                $resolvedFr = $formatWhen($hist['resolved_at'] ?? '');
                $diffLines = is_array($hist['diff_lines'] ?? null) ? $hist['diff_lines'] : [];
                $resolver = trim((string) ($hist['resolver_label'] ?? ''));
                $badgeClass = match ($st) {
                    'pending' => 'pd-orbat-badge--warn',
                    'approved' => '',
                    'rejected' => 'pd-orbat-badge--danger',
                    'cancelled' => 'pd-orbat-badge--muted',
                    default => 'pd-orbat-badge--muted',
                };
                ?>
            <tr class="<?= $st === 'pending' ? 'is-primary' : '' ?>">
              <td><span class="pd-orbat-badge <?= $h($badgeClass) ?>"><?= $h($statusFr($st)) ?></span></td>
              <td class="pd-orbat-table__date"><?= $h($whenFr !== '' ? $whenFr : '—') ?></td>
              <td>
                <?php if ($diffLines !== []): ?>
                <ul class="pd-orbat-mini-diff">
                  <?php foreach (array_slice($diffLines, 0, 4) as $line): ?>
                  <li><?= $h((string) $line) ?></li>
                  <?php endforeach; ?>
                  <?php if (count($diffLines) > 4): ?>
                  <li>… et <?= count($diffLines) - 4 ?> autre<?= count($diffLines) - 4 > 1 ? 's' : '' ?></li>
                  <?php endif; ?>
                </ul>
                <?php else: ?>
                <span class="text-slate-500">—</span>
                <?php endif; ?>
              </td>
              <td class="pd-orbat-table__date">
                <?php if ($resolvedFr !== ''): ?>
                  <?= $h($resolvedFr) ?><?= $resolver !== '' ? ' · ' . $h($resolver) : '' ?>
                <?php else: ?>
                  —
                <?php endif; ?>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </section>
    <?php endif; ?>

    <div class="pd-orbat-grid2">
      <article class="pd-orbat-card">
        <p class="pd-orbat-card__kicker">Affectation — l’équipe</p>
        <p>Indique <strong>dans quelle unité</strong> la personne est rattachée. Une seule affectation est principale : c’est elle qui place la personne dans l’organigramme.</p>
      </article>
      <article class="pd-orbat-card">
        <p class="pd-orbat-card__kicker">Emploi — la fonction</p>
        <p>Indique <strong>ce que la personne fait</strong>, pas où elle est. L’emploi n’ouvre aucun droit d’accès.</p>
      </article>
    </div>

    <fieldset class="pd-fieldset pd-orbat-stack"<?= $orbatFrozen ? ' disabled' : '' ?>>
      <?php if ($orbatFrozen): ?>
      <p class="pd-orbat-frozen">Formulaire verrouillé le temps que l’encadrement traite la demande en cours.</p>
      <?php endif; ?>

      <section class="pd-orbat-block">
        <header class="pd-orbat-block__head">
          <h3>Grade et dates</h3>
          <p>Grade officiel du dossier, titre affiché et date d’engagement.</p>
        </header>
        <div class="grid gap-4 md:grid-cols-2">
          <div>
            <label for="grade_id" class="mb-1 block text-xs font-bold text-slate-600">Grade attribué</label>
            <select name="grade_id" id="grade_id"<?= $orbatFieldAttr ?> class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm">
              <option value="">— Aucun —</option>
              <?php foreach ($grades as $g): ?>
              <?php
                $gid = (int) ($g['id'] ?? 0);
                if ($gid < 1) {
                    continue;
                }
                $glab = trim((string) ($g['label_long'] ?? $g['label_short'] ?? $g['name'] ?? $g['code'] ?? ''));
                if ($glab === '') {
                    $glab = 'Grade #' . $gid;
                }
              ?>
              <option value="<?= $gid ?>"<?= $currentGradeId === $gid ? ' selected' : '' ?>><?= $h($glab) ?></option>
              <?php endforeach; ?>
            </select>
            <p class="mt-1 text-[11px] text-slate-500">Grade officiel du dossier, distinct du titre affiché ci-dessous.</p>
          </div>
          <div>
            <label for="enlistment_date" class="mb-1 block text-xs font-bold text-slate-600">Date d’engagement</label>
            <input type="date" name="enlistment_date" id="enlistment_date"<?= $orbatFieldAttr ?> value="<?= $h(substr(trim((string) ($p['enlistment_date'] ?? '')), 0, 10)) ?>" class="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm">
            <p class="mt-1 text-[11px] text-slate-500">Date de prise d’armes dans la communauté, utilisée pour l’ancienneté.</p>
          </div>
          <div>
            <label for="rank_display" class="mb-1 block text-xs font-bold text-slate-600">Grade ou titre affiché</label>
            <input type="text" name="rank_display" id="rank_display"<?= $orbatFieldAttr ?> value="<?= $h((string) ($p['rank_display'] ?? '')) ?>" placeholder="Sous-lieutenant, Chief…" class="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm" maxlength="100">
            <?php if ($gradeLabel !== ''): ?>
            <p class="mt-1 text-[11px] text-slate-500">Grade attribué : <strong class="text-slate-700"><?= $h($gradeLabel) ?></strong></p>
            <?php endif; ?>
            <p class="mt-1 text-[11px] text-slate-500">Affiché en haut du site à la place du libellé de communauté, s’il est renseigné.</p>
          </div>
          <div>
            <label for="rank_display_override" class="mb-1 block text-xs font-bold text-slate-600">Libellé court personnalisé</label>
            <input type="text" name="rank_display_override" id="rank_display_override"<?= $orbatFieldAttr ?> value="<?= $h((string) ($p['rank_display_override'] ?? '')) ?>" placeholder="O-5, OF-4…" class="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm" maxlength="100">
            <p class="mt-1 text-[11px] text-slate-500">Remplace le code affiché à côté du grade en haut du site.</p>
          </div>
        </div>
      </section>

      <section class="pd-orbat-block">
        <header class="pd-orbat-block__head">
          <h3>Affectations actuelles</h3>
          <p>Ce qui est déjà enregistré sur le dossier.</p>
        </header>
        <?php if (!empty($personnelAssignments)): ?>
        <div class="pd-orbat-table-wrap">
          <table class="pd-orbat-table">
            <thead>
              <tr>
                <th scope="col">Unité</th>
                <th scope="col">Place dans l’équipe</th>
                <th scope="col">Depuis</th>
                <th scope="col">Rôle</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($personnelAssignments as $pa): ?>
              <?php
                $startedRaw = trim((string) ($pa['started_at'] ?? $pa['assigned_at'] ?? ''));
                $startedFr = '—';
                if ($startedRaw !== '') {
                    try {
                        $startedFr = (new DateTimeImmutable($startedRaw))->format('d/m/Y');
                    } catch (Throwable) {
                        $startedFr = $startedRaw;
                    }
                }
                $isPrimary = !empty($pa['is_primary']);
              ?>
              <tr class="<?= $isPrimary ? 'is-primary' : '' ?>">
                <td>
                  <span class="pd-orbat-table__unit"><?= $h((string) ($pa['unit_name'] ?? '—')) ?></span>
                </td>
                <td><?= $h((string) ($pa['role_name'] ?? '—')) ?></td>
                <td class="pd-orbat-table__date"><?= $h($startedFr) ?></td>
                <td>
                  <?php if ($isPrimary): ?>
                  <span class="pd-orbat-badge">Principale</span>
                  <?php else: ?>
                  <span class="pd-orbat-badge pd-orbat-badge--muted">Complémentaire</span>
                  <?php endif; ?>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <?php else: ?>
        <p class="pd-orbat-empty">Aucune affectation active. Ajoutez au moins une unité ci-dessous si la personne doit apparaître dans l’organigramme.</p>
        <?php endif; ?>
      </section>

      <section class="pd-orbat-block">
        <header class="pd-orbat-block__head">
          <h3><?= $orbatStaffMode ? 'Modifier les affectations' : 'Proposer une affectation' ?></h3>
          <p><?= $orbatStaffMode
            ? 'Ajoutez, retirez ou changez les rattachements d’unité.'
            : 'Indiquez la situation souhaitée. Elle partira en demande à l’enregistrement.' ?></p>
        </header>
        <?php
        $unitAssignmentsSeed = [];
        foreach ($currentUnitAssignments as $assignmentRow) {
            $unitAssignmentsSeed[] = [
                'unit_id' => (int) ($assignmentRow['unit_id'] ?? 0),
                'role_name' => (string) ($assignmentRow['role_name'] ?? ''),
                'is_primary' => !empty($assignmentRow['is_primary']),
            ];
        }
        if ($unitAssignmentsSeed === [] && !empty($p['primary_unit_id'])) {
            $unitAssignmentsSeed[] = [
                'unit_id' => (int) $p['primary_unit_id'],
                'role_name' => '',
                'is_primary' => true,
            ];
        }
        $unitOptionsJson = htmlspecialchars(json_encode(array_map(static function (array $u): array {
            return [
                'id' => (int) ($u['id'] ?? 0),
                'name' => (string) ($u['name'] ?? ''),
            ];
        }, $units), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '[]', ENT_QUOTES, 'UTF-8');
        $currentUnitAssignmentsJson = htmlspecialchars(json_encode($unitAssignmentsSeed, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '[]', ENT_QUOTES, 'UTF-8');
        ?>
        <div x-data="personnelUnitAssignmentsEditor(<?= $currentUnitAssignmentsJson ?>, <?= $unitOptionsJson ?>, <?= (int) $maxUnitAssignmentsPerMember ?>)" class="space-y-3">
          <div class="flex items-start justify-between gap-3">
            <p class="text-[11px] text-slate-500">Une personne peut avoir plusieurs affectations. Une seule est principale.</p>
            <?php if (!$orbatFrozen): ?>
            <button type="button" class="rounded-lg border border-dashed border-cyan-300 px-3 py-1.5 text-xs font-semibold text-cyan-800 hover:bg-cyan-50" @click="addRow()" x-show="rows.length < maxRows">Ajouter une affectation</button>
            <?php endif; ?>
          </div>
          <?php if (empty($units)): ?>
          <p class="pd-orbat-empty">Aucune unité : créez la structure dans l’<a class="font-semibold underline" href="<?= $h(url('orbat')) ?>">organigramme</a>.</p>
          <?php endif; ?>
          <?php if (!empty($units)): ?>
          <label class="relative block">
            <span class="sr-only">Rechercher une unité</span>
            <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-slate-400" aria-hidden="true">⌕</span>
            <input type="search"<?= $orbatFieldAttr ?> x-model.debounce.150ms="unitQuery" placeholder="Rechercher une unité…" autocomplete="off" class="w-full rounded-xl border border-slate-200 bg-white py-2.5 pl-9 pr-3 text-sm shadow-sm focus:border-cyan-500 focus:outline-none focus:ring-2 focus:ring-cyan-500/20">
          </label>
          <?php endif; ?>
          <input type="hidden" name="primary_unit_id" :value="primaryUnitId()">
          <template x-for="(row, idx) in rows" :key="row.key">
            <div class="pd-orbat-row">
              <div class="flex flex-col gap-3 lg:flex-row lg:items-end">
                <label class="flex shrink-0 items-center gap-2 text-xs font-bold text-slate-700">
                  <input type="hidden" :name="'unit_assignments[' + idx + '][is_primary]'" :value="primaryIdx === idx ? '1' : '0'">
                  <input type="radio" name="unit_assignments_primary" :value="idx" x-model.number="primaryIdx" class="text-emerald-600"<?= $orbatFrozen ? ' disabled' : '' ?>>
                  Affectation principale
                </label>
                <div class="min-w-[220px] flex-1">
                  <label class="mb-1 block text-[11px] font-bold text-slate-600">Unité</label>
                  <select :name="'unit_assignments[' + idx + '][unit_id]'" x-model.number="row.unit_id" class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm"<?= $orbatFrozen ? ' disabled' : '' ?>>
                    <option value="0">— Aucune —</option>
                    <template x-for="unit in filteredUnitOptions(row.unit_id)" :key="unit.id">
                      <option :value="unit.id" x-text="unit.name"></option>
                    </template>
                  </select>
                </div>
                <div class="min-w-[220px] flex-1">
                  <label class="mb-1 block text-[11px] font-bold text-slate-600">Place dans l’équipe</label>
                  <input type="text" :name="'unit_assignments[' + idx + '][role_name]'" x-model="row.role_name" maxlength="120" placeholder="Ex. Membre, chef d’équipe, adjoint…" class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm"<?= $orbatFrozen ? ' disabled' : '' ?>>
                </div>
                <?php if (!$orbatFrozen): ?>
                <button type="button" class="rounded-lg border border-rose-200 px-3 py-2 text-xs font-bold text-rose-700 hover:bg-rose-50" @click="removeRow(idx)" x-show="rows.length > 1">Retirer</button>
                <?php endif; ?>
              </div>
            </div>
          </template>
        </div>
      </section>

      <section class="pd-orbat-block" id="job_roles_editor">
        <header class="pd-orbat-block__head">
          <h3><?= $orbatStaffMode ? 'Modifier l’emploi' : 'Proposer un emploi' ?></h3>
          <p>La fonction tenue, distincte de l’unité d’affectation.</p>
        </header>
        <?php if ($jobRolesEnabled): ?>
        <?php
        $jobRoleOptionsJson = htmlspecialchars(json_encode($jobRoleOptions, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '[]', ENT_QUOTES, 'UTF-8');
        $currentJobRolesJson = htmlspecialchars(json_encode($currentJobRoles, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '[]', ENT_QUOTES, 'UTF-8');
        ?>
        <div x-data="personnelJobRolesEditor(<?= $currentJobRolesJson ?>, <?= $jobRoleOptionsJson ?>, <?= (int) $maxJobRolesPerMember ?>)">
          <div class="mb-3 flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
            <p class="text-[11px] text-slate-500">L’emploi principal apparaît sur la fiche, l’organigramme et le forum.</p>
            <label class="relative block sm:w-80">
              <span class="sr-only">Rechercher une fonction</span>
              <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-slate-400" aria-hidden="true">⌕</span>
              <input type="search"<?= $orbatFieldAttr ?> x-model.debounce.150ms="roleQuery" placeholder="Rechercher une fonction…" autocomplete="off" class="w-full rounded-xl border border-cyan-200 bg-white py-2.5 pl-9 pr-3 text-sm shadow-sm focus:border-cyan-500 focus:outline-none focus:ring-2 focus:ring-cyan-500/20">
            </label>
          </div>
          <div class="space-y-2">
            <template x-for="(row, idx) in roles" :key="row.key">
              <div class="pd-orbat-row flex flex-col gap-2 sm:flex-row sm:flex-wrap sm:items-end">
                <label class="flex shrink-0 items-center gap-1.5 text-[10px] font-bold text-slate-600">
                  <input type="hidden" :name="'job_roles[' + idx + '][is_primary]'" :value="primaryIdx === idx ? '1' : '0'">
                  <input type="radio" name="job_roles_primary" :value="idx" x-model.number="primaryIdx" class="text-emerald-600"<?= $orbatFrozen ? ' disabled' : '' ?>>
                  Emploi principal
                </label>
                <div class="min-w-[220px] flex-1">
                  <label class="mb-0.5 block text-[10px] font-bold uppercase text-slate-500">Emploi</label>
                  <select :name="'job_roles[' + idx + '][role_id]'" x-model.number="row.role_id" class="w-full rounded-lg border border-cyan-200 bg-white px-2.5 py-2 text-xs shadow-sm focus:border-cyan-500 focus:outline-none focus:ring-2 focus:ring-cyan-500/20"<?= $orbatFrozen ? ' disabled' : '' ?>>
                    <option value="0">— Non renseigné —</option>
                    <template x-for="opt in filteredJobRoleOptions(row.role_id)" :key="opt.id">
                      <option :value="opt.id" x-text="opt.label"></option>
                    </template>
                  </select>
                  <p x-show="roleQuery && matchingRoleCount() === 0" class="mt-1 text-[10px] font-semibold text-amber-700">Aucune fonction correspondante.</p>
                </div>
                <div class="min-w-[160px] flex-1">
                  <label class="mb-0.5 block text-[10px] font-bold uppercase text-slate-500">Précision</label>
                  <input type="text" :name="'job_roles[' + idx + '][detail]'" x-model="row.detail" maxlength="150" placeholder="Optionnel" class="w-full rounded-lg border border-cyan-200 px-2.5 py-2 text-xs shadow-sm focus:border-cyan-500 focus:outline-none focus:ring-2 focus:ring-cyan-500/20"<?= $orbatFrozen ? ' disabled' : '' ?>>
                </div>
                <?php if (!$orbatFrozen): ?>
                <button type="button" class="shrink-0 rounded-lg border border-rose-200 px-2.5 py-2 text-[10px] font-bold text-rose-700 hover:bg-rose-50" @click="removeRow(idx)" x-show="roles.length > 1">Retirer</button>
                <?php endif; ?>
              </div>
            </template>
            <?php if (!$orbatFrozen): ?>
            <button type="button" class="rounded-lg border border-dashed border-cyan-300 px-3 py-1.5 text-xs font-semibold text-cyan-800 hover:bg-cyan-50" @click="addRow()" x-show="roles.length < maxRoles">Ajouter un emploi</button>
            <?php endif; ?>
          </div>
        </div>
        <?php else: ?>
        <p class="pd-orbat-empty">Le catalogue d’emplois n’est pas encore disponible dans cette communauté.</p>
        <?php endif; ?>
      </section>

      <?php if (!empty($dossierPresets) && !$orbatFrozen): ?>
      <section class="pd-orbat-block pd-orbat-block--presets">
        <header class="pd-orbat-block__head">
          <h3>Modèles de fonction</h3>
          <p>Remplit l’emploi ci-dessus et des suggestions d’équipement. L’équipe se choisit toujours à part. <a href="<?= $h(url('personnel/tutorials')) ?>" class="font-bold underline">Guide</a>.</p>
        </header>
        <div class="mt-3 flex flex-wrap gap-2">
          <?php foreach ($dossierPresets as $pr): ?>
          <button type="button" class="personnel-preset-btn rounded-lg border border-emerald-300 bg-white px-3 py-1.5 text-left text-[11px] font-bold text-emerald-950 shadow-sm transition hover:border-emerald-500 hover:bg-emerald-50" data-preset-id="<?= $h((string) ($pr['id'] ?? '')) ?>" title="<?= $h((string) ($pr['description'] ?? '')) ?>">
            <?= $h((string) ($pr['label'] ?? '')) ?>
          </button>
          <?php endforeach; ?>
        </div>
      </section>
      <?php endif; ?>

      <?php if ($orbatStaffMode && !$orbatFrozen): ?>
      <section class="pd-orbat-block">
        <header class="pd-orbat-block__head">
          <h3>Motifs (historique)</h3>
          <p>Facultatif : ajoute un motif lisible dans l’historique du dossier.</p>
        </header>
        <div class="grid gap-4 md:grid-cols-2">
          <div>
            <label for="assignment_change_reason" class="mb-1 block text-xs font-bold text-slate-600">Motif du changement d’affectation</label>
            <input type="text" name="assignment_change_reason" id="assignment_change_reason" class="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm" maxlength="255" placeholder="Ex. Renfort section Alfa, rotation trimestrielle">
          </div>
          <div>
            <label for="job_role_change_reason" class="mb-1 block text-xs font-bold text-slate-600">Motif du changement de fonction</label>
            <input type="text" name="job_role_change_reason" id="job_role_change_reason" class="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm" maxlength="255" placeholder="Ex. Validation stage leader, besoin de cellule appui">
          </div>
        </div>
      </section>
      <?php endif; ?>
    </fieldset>

    <p class="text-[11px] text-slate-500">
      <a href="<?= $h(url('orbat')) ?>" class="font-semibold text-cyan-800 underline-offset-2 hover:underline">Voir l’organigramme</a>
      — Vue d’ensemble des unités.
    </p>
  </div>
</section>
