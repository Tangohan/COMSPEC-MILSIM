<?php
use App\Core\{Csrf,Session};
use App\Services\Tenant\TenantContext;
$intervention = TenantContext::intervention();
$activeTenantIntervention = null;
try {
    $activeTenantIntervention = $intervention ?: (new App\Repositories\PlatformAdminTenantAuditRepository())->activeForTenant((int) Session::get('tenant_id'));
} catch (Throwable) {}
if ($activeTenantIntervention):
    $isManager = $intervention !== null;
    $tenantName = 'Organisation';
    if ($isManager) { try { $tenantName=(string)((new App\Repositories\TenantRepository())->findById((int)$intervention['admin_tenant_id'])['name']??$tenantName); } catch(Throwable){} }
?>
<div id="tenant-intervention-banner" class="sticky top-0 z-[100] bg-amber-400 text-slate-950 shadow-lg px-4 py-3" role="status">
 <div class="max-w-7xl mx-auto flex flex-wrap items-center justify-between gap-3">
  <button type="button" onclick="document.getElementById('tenant-intervention-modal').showModal()" class="font-black tracking-wide"><?= $isManager ? 'MODE ADMINISTRATION TENANT — '.htmlspecialchars($tenantName,ENT_QUOTES,'UTF-8') : 'Un gestionnaire analyse et corrige votre organisation' ?></button>
  <?php if($isManager): ?><nav class="flex gap-3 text-sm font-bold"><a href="<?= htmlspecialchars(url('admin/system/tenants/'.(int)$intervention['admin_tenant_id'].'/intervention/journal')) ?>">Journal</a><a href="<?= htmlspecialchars(url('admin/system/tenants/'.(int)$intervention['admin_tenant_id'].'/intervention/journal?type=update')) ?>">Changements</a><a href="<?= htmlspecialchars(url('admin/system/tenants/'.(int)$intervention['admin_tenant_id'].'/intervention/journal#errors')) ?>">Erreurs</a><a href="<?= htmlspecialchars(url('admin/system/tenants/'.(int)$intervention['admin_tenant_id'].'/intervention/journal')) ?>">Rollback</a><form method="post" action="<?= htmlspecialchars(url('admin/system/tenant-intervention/leave')) ?>"><input type="hidden" name="_csrf_token" value="<?= htmlspecialchars(Csrf::token()) ?>"><button class="underline">Quitter l’organisation</button></form></nav><?php endif ?>
 </div>
</div>
<dialog id="tenant-intervention-modal" class="rounded-2xl p-0 max-w-xl backdrop:bg-slate-950/70"><div class="p-7"><div class="text-amber-600 font-bold">Intervention en cours</div><h2 class="text-2xl font-black my-3">Gestion sécurisée de votre organisation</h2><p>Un gestionnaire de la plateforme intervient actuellement dans votre organisation afin d’effectuer des opérations de diagnostic, de maintenance ou de correction.</p><p class="mt-3">Certaines configurations ou données peuvent être modifiées pendant cette intervention.</p><p class="mt-3">Toutes les opérations réalisées par le gestionnaire sont intégralement journalisées et peuvent faire l’objet d’une restauration lorsque cela est techniquement possible.</p><dl class="mt-5 text-sm"><dt>Début</dt><dd><?= htmlspecialchars((string)($activeTenantIntervention['admin_tenant_started_at']??$activeTenantIntervention['started_at']??'—')) ?></dd><dt>Session</dt><dd>#<?= (int)($activeTenantIntervention['admin_tenant_session_id']??$activeTenantIntervention['id']??0) ?></dd></dl><button onclick="this.closest('dialog').close()" class="mt-5 rounded-lg bg-slate-900 text-white px-4 py-2">Fermer</button></div></dialog>
<?php endif; ?>
