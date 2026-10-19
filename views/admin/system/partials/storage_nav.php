<?php
declare(strict_types=1);

/**
 * Onglets de l’espace « Stockage et données » (administration du site) + messages flash.
 *
 * @var string $storageTab overview | data
 */
$storageTab = (string) ($storageTab ?? 'overview');
$snFlashErr = \App\Core\Session::getFlash('error');
$snFlashOk = \App\Core\Session::getFlash('success');
$snTabs = [
    'overview' => ['Espace disque et historiques', url('admin/system/storage')],
    'data' => ['Explorateur de données', url('admin/system/storage/donnees')],
];
?>
<nav class="flex flex-wrap gap-1 rounded-xl border border-slate-200 bg-white p-1 text-sm font-semibold" aria-label="Stockage et données">
    <?php foreach ($snTabs as $k => [$label, $href]): ?>
        <a href="<?= htmlspecialchars($href, ENT_QUOTES, 'UTF-8') ?>" class="rounded-lg px-3 py-1.5 <?= $k === $storageTab ? 'bg-slate-900 text-white' : 'text-slate-600 hover:bg-slate-100' ?>"<?= $k === $storageTab ? ' aria-current="page"' : '' ?>><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></a>
    <?php endforeach; ?>
    <a href="<?= htmlspecialchars(url('admin'), ENT_QUOTES, 'UTF-8') ?>" class="ml-auto rounded-lg px-3 py-1.5 text-slate-500 hover:bg-slate-100">Centre opérateur site</a>
</nav>
<?php if ($snFlashErr): ?>
    <p class="rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-900" role="alert"><?= htmlspecialchars((string) $snFlashErr, ENT_QUOTES, 'UTF-8') ?></p>
<?php endif; ?>
<?php if ($snFlashOk): ?>
    <p class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900" role="status"><?= htmlspecialchars((string) $snFlashOk, ENT_QUOTES, 'UTF-8') ?></p>
<?php endif; ?>
