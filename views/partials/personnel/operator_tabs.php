<?php
/**
 * Onglets fiche opérateur unifiée (Phase 2).
 * @var string $active_tab identity|skills|training|deployment|security
 * @var string $base_path chemin relatif sans query (ex. personnel/me)
 */
$active_tab = (string) ($active_tab ?? 'identity');
$base_path = (string) ($base_path ?? 'personnel/me');
$tabs = [
    'identity' => 'Identité',
    'skills' => 'Compétences',
    'training' => 'Formations',
    'deployment' => 'Déploiement',
    'security' => 'Compte',
];
$hrefFor = static function (string $key) use ($base_path): string {
    return match ($key) {
        'training' => url('formations/mes-formations'),
        'deployment' => url('deploiement'),
        'security' => url('account'),
        'skills' => url('formations/competences'),
        default => url($base_path),
    };
};
?>
<nav class="mb-8 flex flex-wrap gap-2 border-b border-slate-200 pb-3" aria-label="Sections de la fiche">
    <?php foreach ($tabs as $key => $label): ?>
        <?php $is = $active_tab === $key; ?>
        <a href="<?= htmlspecialchars($hrefFor($key), ENT_QUOTES, 'UTF-8') ?>"
           class="rounded-lg px-3 py-2 text-sm font-semibold transition <?= $is ? 'bg-emerald-50 text-emerald-900' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900' ?>"
           <?= $is ? 'aria-current="page"' : '' ?>><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></a>
    <?php endforeach; ?>
</nav>
