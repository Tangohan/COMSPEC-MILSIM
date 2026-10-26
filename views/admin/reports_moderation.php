<?php
declare(strict_types=1);

use App\Controllers\Web\ReportsModerationController;

$baseUrl = url('');
$pendingReports = $pendingReports ?? [];
$handledReports = $handledReports ?? [];
$reportTimelines = is_array($reportTimelines ?? null) ? $reportTimelines : [];
$canSanctionMembers = !empty($canSanctionMembers);
$userId = (int) (\App\Core\Session::get('user_id') ?? 0);
$h = static fn (?string $v): string => htmlspecialchars((string) $v, ENT_QUOTES | ENT_HTML5, 'UTF-8');
$actionBase = $baseUrl . '/back-office/signalements/';

$kindLabel = static function (?string $kind): string {
    return match (strtolower(trim((string) $kind))) {
        'training_course' => 'Formation',
        'member_profile' => 'Fiche membre',
        'profile_picture' => 'Photo de profil',
        'operator_visual' => 'Visuel opérateur',
        'help_page' => 'Page d’aide',
        'site_image' => 'Image du site',
        'portal_help' => 'Demande d’aide',
        'org_anomaly' => 'Anomalie d’organisation',
        'site_support_request' => 'Demande d’assistance',
        '' => 'Signalement',
        default => 'Autre contenu',
    };
};
$typeLabel = static function (?string $raw): string {
    return match (strtolower(trim((string) $raw))) {
        'spam' => 'Spam',
        'abuse' => 'Abus ou contenu choquant',
        'illegal' => 'Contenu illégal',
        'other', '' => 'Autre motif',
        default => 'Motif personnalisé',
    };
};
$followUpLabel = static function (?string $action): string {
    $a = strtolower(trim((string) $action));
    if ($a === '' || $a === 'close') {
        return 'Clôture sans mesure';
    }

    return ReportsModerationController::FOLLOW_UPS[$a] ?? ReportsModerationController::SANCTIONS[$a] ?? 'Mesure enregistrée';
};
$dateLabel = static function (?string $raw): string {
    $ts = $raw !== null && $raw !== '' ? strtotime($raw) : false;

    return $ts ? date('d/m/Y H:i', $ts) : '—';
};

$renderTimeline = static function (int $reportId) use ($reportTimelines, $h, $dateLabel): void {
    $events = $reportTimelines[$reportId] ?? [];
    if ($events === []) {
        return;
    }
    ?>
    <ol class="mt-3 space-y-1.5 border-l-2 border-slate-200 pl-3 text-xs text-slate-600">
      <?php foreach ($events as $ev): ?>
        <li>
          <span class="font-semibold text-slate-800"><?= $h((string) ($ev['event_label'] ?? '')) ?></span>
          <span class="text-slate-400"> · <?= $h((string) ($ev['actor_name'] ?? '')) ?> · <?= $h($dateLabel((string) ($ev['created_at'] ?? ''))) ?></span>
          <?php if (!empty($ev['event_detail'])): ?>
            <span class="block whitespace-pre-line text-slate-600"><?= $h((string) $ev['event_detail']) ?></span>
          <?php endif; ?>
        </li>
      <?php endforeach; ?>
    </ol>
    <?php
};
?>
<div class="w-full max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-8 sm:py-12 pb-16">
  <nav class="flex flex-wrap items-center gap-2 text-[11px] font-semibold text-slate-500 mb-8" aria-label="Fil d’Ariane">
    <a href="<?= $h($baseUrl . '/back-office') ?>" class="text-slate-600 hover:text-emerald-700 transition-colors">Back-office</a>
    <span class="text-slate-300 select-none" aria-hidden="true">/</span>
    <span class="text-slate-900 font-bold">Signalements</span>
  </nav>

  <header class="relative overflow-hidden rounded-3xl border border-slate-200/90 bg-white shadow-[0_4px_24px_rgba(15,23,42,0.06)] mb-10">
    <div class="absolute inset-x-0 top-0 h-1.5 bg-gradient-to-r from-rose-500 via-orange-400 to-amber-400" aria-hidden="true"></div>
    <div class="px-6 py-8 sm:px-10 sm:py-9 flex flex-col sm:flex-row sm:items-end sm:justify-between gap-6">
      <div class="min-w-0">
        <p class="text-[11px] font-bold uppercase tracking-[0.2em] text-rose-600/90 mb-2">Espace modérateurs</p>
        <h1 class="text-2xl sm:text-3xl font-black text-slate-900 tracking-tight leading-tight">Signalements</h1>
        <p class="mt-2 max-w-2xl text-sm text-slate-600 leading-relaxed">Les signalements envoyés par les membres depuis les fiches, photos, formations, pages d’aide et anomalies d’organisation. Prenez un dossier en charge, commentez-le, puis clôturez-le avec la mesure adaptée.</p>
      </div>
      <div class="flex gap-3 shrink-0">
        <div class="rounded-2xl border border-rose-200 bg-rose-50 px-5 py-3 text-center">
          <p class="text-2xl font-black tabular-nums text-rose-800"><?= count($pendingReports) ?></p>
          <p class="text-[11px] font-bold uppercase tracking-wide text-rose-700/80">En attente</p>
        </div>
        <a href="<?= $h($baseUrl . '/back-office/content-moderation') ?>" class="rounded-2xl border border-slate-200 bg-slate-50 px-5 py-3 text-center text-sm font-semibold text-slate-700 hover:bg-slate-100 self-stretch flex items-center">File des fichiers</a>
      </div>
    </div>
  </header>

  <section aria-labelledby="reports-pending-heading" class="mb-12">
    <h2 id="reports-pending-heading" class="text-lg font-black text-slate-900 mb-4">À traiter</h2>
    <?php if ($pendingReports === []): ?>
      <p class="rounded-2xl border border-dashed border-slate-300 bg-white px-6 py-10 text-center text-sm text-slate-500">Aucun signalement en attente.</p>
    <?php endif; ?>
    <div class="space-y-5">
      <?php foreach ($pendingReports as $r):
          $id = (int) ($r['id'] ?? 0);
          $assignedTo = (int) ($r['assigned_to'] ?? 0);
          $targetId = (int) ($r['target_user_id'] ?? 0);
          $canSanctionThis = $canSanctionMembers && $targetId > 0 && $targetId !== (int) ($r['reporter_id'] ?? 0) && $targetId !== $userId;
      ?>
        <article class="rounded-2xl border border-slate-200 bg-white p-5 sm:p-6 shadow-sm" id="signalement-<?= $id ?>">
          <div class="flex flex-wrap items-start justify-between gap-3">
            <div class="min-w-0">
              <p class="text-[11px] font-bold uppercase tracking-wide text-rose-700"><?= $h($kindLabel((string) ($r['content_kind'] ?? ''))) ?> · <?= $h($typeLabel((string) ($r['report_type'] ?? ''))) ?></p>
              <p class="mt-1 text-sm text-slate-500">Dossier nº <?= $id ?> · envoyé par <strong class="text-slate-800"><?= $h((string) ($r['reporter_name'] ?? 'Membre')) ?></strong> le <?= $h($dateLabel((string) ($r['created_at'] ?? ''))) ?></p>
              <?php if ($targetId > 0): ?>
                <p class="mt-1 text-sm text-slate-500">Membre visé : <a class="font-semibold text-sky-700 hover:underline" href="<?= $h($baseUrl . '/personnel/' . $targetId) ?>"><?= $h((string) ($r['target_name'] ?? '')) ?></a></p>
              <?php endif; ?>
            </div>
            <form method="post" action="<?= $h($actionBase . $id . ($assignedTo > 0 ? '/liberer' : '/prendre')) ?>">
              <?= \App\Core\Csrf::field() ?>
              <?php if ($assignedTo > 0): ?>
                <span class="mr-2 text-xs text-slate-500">Pris en charge par <?= $h((string) ($r['assigned_to_name'] ?? '')) ?></span>
                <button type="submit" class="rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50">Remettre dans la file</button>
              <?php else: ?>
                <button type="submit" class="rounded-lg bg-slate-900 px-3 py-1.5 text-xs font-semibold text-white hover:bg-slate-800">Prendre en charge</button>
              <?php endif; ?>
            </form>
          </div>

          <div class="mt-4 rounded-xl bg-slate-50 border border-slate-200/80 px-4 py-3 text-sm text-slate-800 whitespace-pre-line"><?= $h((string) ($r['reason'] ?? '')) ?></div>
          <?php if (!empty($r['comment'])): ?>
            <p class="mt-2 text-sm text-slate-600 whitespace-pre-line"><span class="font-semibold text-slate-700">Précisions :</span> <?= $h((string) $r['comment']) ?></p>
          <?php endif; ?>
          <?php if (!empty($r['reported_url'])): ?>
            <p class="mt-2 text-sm"><a class="font-semibold text-sky-700 hover:underline break-all" href="<?= $h((string) $r['reported_url']) ?>" rel="noopener noreferrer">Ouvrir le contenu signalé</a></p>
          <?php endif; ?>
          <?php $renderTimeline($id); ?>

          <div class="mt-5 grid gap-4 md:grid-cols-2">
            <form method="post" action="<?= $h($actionBase . $id . '/traiter') ?>" class="rounded-xl border border-slate-200 p-4 space-y-3">
              <?= \App\Core\Csrf::field() ?>
              <label class="block text-xs font-bold uppercase tracking-wide text-slate-600" for="follow-up-<?= $id ?>">Clôturer le dossier</label>
              <select id="follow-up-<?= $id ?>" name="follow_up" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm">
                <?php foreach (ReportsModerationController::FOLLOW_UPS as $value => $label): ?>
                  <option value="<?= $h($value) ?>"><?= $h($label) ?></option>
                <?php endforeach; ?>
                <?php if ($canSanctionThis): ?>
                  <optgroup label="Sanction sur le membre visé">
                    <?php foreach (ReportsModerationController::SANCTIONS as $value => $label): ?>
                      <option value="<?= $h($value) ?>"><?= $h($label) ?></option>
                    <?php endforeach; ?>
                  </optgroup>
                <?php endif; ?>
              </select>
              <textarea name="moderator_note" maxlength="500" rows="2" placeholder="Note de clôture (facultative, visible de l’équipe)" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm"></textarea>
              <?php if ($canSanctionThis): ?>
                <label class="flex items-center gap-2 text-xs text-slate-600"><input type="checkbox" name="confirm_permanent_ban" value="1"> Je confirme en cas d’exclusion définitive</label>
              <?php endif; ?>
              <button type="submit" class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700">Clôturer</button>
            </form>
            <form method="post" action="<?= $h($actionBase . $id . '/commenter') ?>" class="rounded-xl border border-slate-200 p-4 space-y-3">
              <?= \App\Core\Csrf::field() ?>
              <label class="block text-xs font-bold uppercase tracking-wide text-slate-600" for="comment-<?= $id ?>">Commentaire interne</label>
              <textarea id="comment-<?= $id ?>" name="timeline_comment" maxlength="1200" rows="4" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm"></textarea>
              <button type="submit" class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Ajouter au dossier</button>
            </form>
          </div>
        </article>
      <?php endforeach; ?>
    </div>
  </section>

  <section aria-labelledby="reports-handled-heading">
    <h2 id="reports-handled-heading" class="text-lg font-black text-slate-900 mb-4">Récemment clôturés</h2>
    <?php if ($handledReports === []): ?>
      <p class="text-sm text-slate-500">Aucun dossier clôturé pour le moment.</p>
    <?php endif; ?>
    <div class="space-y-4">
      <?php foreach ($handledReports as $r):
          $id = (int) ($r['id'] ?? 0);
      ?>
        <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
          <div class="flex flex-wrap items-start justify-between gap-3">
            <div class="min-w-0">
              <p class="text-[11px] font-bold uppercase tracking-wide text-slate-500"><?= $h($kindLabel((string) ($r['content_kind'] ?? ''))) ?> · dossier nº <?= $id ?></p>
              <p class="mt-1 text-sm text-slate-800"><?= $h(mb_strimwidth((string) ($r['reason'] ?? ''), 0, 220, '…')) ?></p>
              <p class="mt-1 text-xs text-slate-500"><?= $h($followUpLabel((string) ($r['last_follow_up_action'] ?? ''))) ?> · par <?= $h((string) ($r['handled_by_name'] ?? '—')) ?> le <?= $h($dateLabel((string) ($r['handled_at'] ?? ''))) ?></p>
              <?php if (!empty($r['resolution_note'])): ?>
                <p class="mt-1 text-xs text-slate-600 whitespace-pre-line"><?= $h((string) $r['resolution_note']) ?></p>
              <?php endif; ?>
            </div>
            <form method="post" action="<?= $h($actionBase . $id . '/rouvrir') ?>" class="flex flex-col gap-2 items-end">
              <?= \App\Core\Csrf::field() ?>
              <input type="text" name="reopen_note" maxlength="500" placeholder="Motif de réouverture (facultatif)" class="w-56 rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs">
              <label class="flex items-center gap-2 text-xs text-slate-600"><input type="checkbox" name="notify_reporter" value="1" checked> Prévenir l’auteur du signalement</label>
              <button type="submit" class="rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50">Rouvrir</button>
            </form>
          </div>
          <?php $renderTimeline($id); ?>
        </article>
      <?php endforeach; ?>
    </div>
  </section>
</div>
