<?php
declare(strict_types=1);

use App\Services\Moderation\ModerationArtifactState;
use App\Services\Moderation\ModerationSourceType;

$baseUrl = url('');
$contentModerationUrl = rtrim(url('admin/content-moderation'), '/');
$artifacts = $artifacts ?? [];
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
      Fichiers et contenus mis en attente de validation (forum, documents, courrier). Approuvez ou rejetez chaque élément ci-dessous.
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
      Aucun élément en file d’attente.
    </p>
  <?php else: ?>
    <p class="text-sm text-slate-600 mb-5">
      <span class="font-semibold text-slate-900"><?= (int) $total ?></span>
      élément<?= $total > 1 ? 's' : '' ?> au total
      · page <span class="font-semibold text-slate-900"><?= (int) $page ?></span>
    </p>
    <ul class="space-y-4">
      <?php foreach ($artifacts as $a): ?>
        <?php
        $id = (int) ($a['id'] ?? 0);
        $rawState = (string) ($a['state'] ?? '');
        $rawSource = (string) ($a['source_type'] ?? '');
        $displayState = $rawState !== '' ? $stateLabel($rawState) : '—';
        $displaySource = $rawSource !== '' ? $sourceLabel($rawSource) : '—';
        ?>
        <li class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
          <div class="flex flex-wrap items-start justify-between gap-4">
            <div class="min-w-0 flex-1 space-y-2">
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
                <p class="text-xs text-slate-500 break-all leading-relaxed">
                  <span class="font-medium text-slate-600">Référence interne :</span>
                  <?= htmlspecialchars((string) $a['file_path']) ?>
                </p>
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
            </div>
            <div class="flex flex-wrap gap-2 shrink-0 w-full sm:w-auto justify-end">
              <form method="post" action="<?= htmlspecialchars($contentModerationUrl) ?>/<?= $id ?>/approve" class="inline">
                <?= \App\Core\Csrf::field() ?>
                <button type="submit" class="inline-flex items-center justify-center min-h-[2.5rem] px-4 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold shadow-sm transition-colors">
                  Approuver
                </button>
              </form>
              <form method="post" action="<?= htmlspecialchars($contentModerationUrl) ?>/<?= $id ?>/reject" class="inline">
                <?= \App\Core\Csrf::field() ?>
                <button type="submit" class="inline-flex items-center justify-center min-h-[2.5rem] px-4 rounded-lg bg-white border-2 border-rose-200 text-rose-800 hover:bg-rose-50 text-sm font-semibold transition-colors">
                  Rejeter
                </button>
              </form>
            </div>
          </div>
        </li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>
</div>
