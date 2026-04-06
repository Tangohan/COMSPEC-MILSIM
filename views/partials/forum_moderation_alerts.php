<?php
/**
 * Bandeau alertes modération (signalements + file contenu) — toutes les pages forum.
 */
if (!function_exists('forum_user_can_moderate') || !forum_user_can_moderate()) {
    return;
}
$tenantId = (int) (\App\Core\Session::get('tenant_id') ?? 0);
if ($tenantId <= 0) {
    return;
}
$baseUrl = url('');
/** @var int Compteur uniquement — ne pas utiliser le nom $pendingReports (réservé aux vues qui reçoivent la liste) */
$pendingReportsCount = 0;
$contentQueue = 0;
try {
    $reportRepo = \App\Core\Container::get(\App\Repositories\ForumReportRepository::class);
    $pendingReportsCount = $reportRepo->countPending($tenantId);
    $artRepo = \App\Core\Container::get(\App\Repositories\ModerationArtifactRepository::class);
    if ($artRepo->tableExists()) {
        $contentQueue = $artRepo->countQueue($tenantId, null);
    }
} catch (\Throwable) {
    return;
}
if ($pendingReportsCount < 1 && $contentQueue < 1) {
    return;
}
?>
<div class="w-full max-w-6xl mx-auto px-4 sm:px-6 pt-4">
  <div class="rounded-xl border border-rose-200 bg-gradient-to-r from-rose-50 to-amber-50/90 px-4 py-3 shadow-sm flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
    <div class="flex items-start gap-3">
      <span class="text-xl shrink-0" aria-hidden="true">⚠</span>
      <div>
        <p class="text-sm font-black text-rose-900 uppercase tracking-wide">Modération — action requise</p>
        <p class="text-xs text-rose-800/90 mt-0.5">
          <?php if ($pendingReportsCount > 0): ?>
            <strong><?= (int) $pendingReportsCount ?></strong> signalement<?= $pendingReportsCount > 1 ? 's' : '' ?> en attente
          <?php endif; ?>
          <?php if ($pendingReportsCount > 0 && $contentQueue > 0): ?> · <?php endif; ?>
          <?php if ($contentQueue > 0): ?>
            <strong><?= (int) $contentQueue ?></strong> fichier<?= $contentQueue > 1 ? 's' : '' ?> en file de contrôle
          <?php endif; ?>
        </p>
      </div>
    </div>
    <div class="flex flex-wrap gap-2 shrink-0">
      <a href="<?= htmlspecialchars($baseUrl) ?>/back-office/forum-moderation" class="inline-flex items-center justify-center px-4 py-2 rounded-lg bg-rose-700 hover:bg-rose-800 text-white text-[10px] font-black uppercase tracking-wider">Console modération</a>
      <?php if ($contentQueue > 0): ?>
      <a href="<?= htmlspecialchars($baseUrl) ?>/admin/content-moderation" class="inline-flex items-center justify-center px-4 py-2 rounded-lg border border-amber-300 bg-white text-amber-900 text-[10px] font-black uppercase tracking-wider hover:bg-amber-50">File contenu</a>
      <?php endif; ?>
    </div>
  </div>
</div>
