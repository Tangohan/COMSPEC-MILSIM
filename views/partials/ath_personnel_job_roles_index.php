<?php
declare(strict_types=1);

$categories = $categories ?? [];
$roles = $roles ?? [];
$permCounts = $permCounts ?? [];
$activeTab = $activeTab ?? 'referentiel';
$personnelProfilesJobRoleReady = $personnelProfilesJobRoleReady ?? true;

$athKpis = [
    ['label' => 'CATÉGORIES', 'value' => (string) count($categories), 'delta' => '', 'tone' => '#1e4f80', 'pct' => '100%', 'note' => 'arborescence'],
    ['label' => 'EMPLOIS', 'value' => (string) count($roles), 'delta' => '', 'tone' => '#0b8a5c', 'pct' => '100%', 'note' => 'référentiel'],
    ['label' => 'DROITS LIÉS', 'value' => (string) array_sum(array_map(static fn ($v): int => (int) $v, $permCounts)), 'delta' => '', 'tone' => '#c98a12', 'pct' => '—', 'note' => 'permissions'],
    ['label' => 'STATUT', 'value' => !empty($personnelProfilesJobRoleReady) ? 'Prêt' : 'Migration', 'delta' => '', 'tone' => !empty($personnelProfilesJobRoleReady) ? '#0b8a5c' : '#c98a12', 'pct' => '—', 'note' => 'dossiers effectifs'],
];
require base_path('views/partials/ath_kpis.php');
?>
<div class="flex flex-wrap gap-2 mb-6 ath-rise">
    <a href="<?= url('back-office/personnel-job-roles') ?>" class="ath-btn<?= $activeTab === 'referentiel' ? ' ath-btn--solid' : '' ?>">Référentiel</a>
    <a href="<?= url('back-office/personnel-job-roles/assignments') ?>" class="ath-btn<?= $activeTab === 'assignments' ? ' ath-btn--solid' : '' ?>">Attributions effectifs</a>
    <a href="<?= url('back-office/personnel-job-roles/roles/create') ?>" class="ath-btn">Nouvel emploi</a>
</div>
<div class="pjr-ath-wrap">
<?php require base_path('views/partials/ath_personnel_job_roles_referentiel.php'); ?>
</div>
