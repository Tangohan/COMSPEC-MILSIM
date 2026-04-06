<?php
declare(strict_types=1);
/** @var list<array<string,mixed>> $my_enlistments_pending */
/** @var list<array<string,mixed>> $staff_enlistments_pending */
/** @var bool $show_staff_enlistments */

$my = $my_enlistments_pending ?? [];
$staff = $staff_enlistments_pending ?? [];
$showStaff = !empty($show_staff_enlistments);
if ($my === [] && ($staff === [] || !$showStaff)) {
    return;
}

$gate = \App\Core\Gate::getInstance();
$canOpenBo = $gate->allows('admin.organization') || $gate->allows('admin.access')
    || $showStaff;

$fmtRow = static function (array $e): string {
    $id = (int) ($e['id'] ?? 0);
    $fn = trim((string) ($e['first_name'] ?? ''));
    $ln = trim((string) ($e['last_name'] ?? ''));
    $name = trim($fn . ' ' . $ln);
    if ($name === '') {
        $name = '—';
    }
    $mail = trim((string) ($e['email'] ?? ''));
    $dt = (string) ($e['created_at'] ?? '');
    $dateStr = $dt !== '' ? date('d/m/Y H:i', strtotime($dt)) : '—';

    return $name . ' · ' . ($mail !== '' ? $mail : '—') . ' · ' . $dateStr . ' · #' . $id;
};
?>
<?php if ($my !== []): ?>
<section class="border-b border-slate-200 bg-sky-50/80">
    <div class="max-w-7xl mx-auto px-6 md:px-10 py-6">
        <h2 class="text-[11px] font-black uppercase tracking-[0.28em] text-sky-900/90 mb-3">Mes candidatures en attente</h2>
        <p class="text-xs text-sky-800/80 mb-4">Statut « soumis » : le staff n’a pas encore traité votre dossier sur cette communauté.</p>
        <ul class="space-y-2">
            <?php foreach ($my as $e): ?>
                <?php
                $id = (int) ($e['id'] ?? 0);
                $detailUrl = url('back-office/recruitments/' . $id);
                ?>
                <li class="flex flex-wrap items-center gap-x-3 gap-y-1 text-sm text-sky-950">
                    <?php if ($canOpenBo): ?>
                        <a href="<?= htmlspecialchars($detailUrl, ENT_QUOTES, 'UTF-8') ?>" class="font-semibold text-sky-800 hover:underline"><?= htmlspecialchars($fmtRow($e), ENT_QUOTES, 'UTF-8') ?></a>
                    <?php else: ?>
                        <span class="font-medium"><?= htmlspecialchars($fmtRow($e), ENT_QUOTES, 'UTF-8') ?></span>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>
</section>
<?php endif; ?>

<?php if ($showStaff && $staff !== []): ?>
<section class="border-b border-amber-200/80 bg-amber-50/90">
    <div class="max-w-7xl mx-auto px-6 md:px-10 py-6">
        <div class="flex flex-wrap items-baseline justify-between gap-3 mb-3">
            <h2 class="text-[11px] font-black uppercase tracking-[0.28em] text-amber-950">Candidatures à traiter (communauté)</h2>
            <a href="<?= htmlspecialchars(url('back-office/recruitments') . '?status=submitted', ENT_QUOTES, 'UTF-8') ?>" class="text-xs font-bold text-amber-900 hover:underline">Ouvrir le back-office recrutement</a>
        </div>
        <p class="text-xs text-amber-900/80 mb-4">File d’attente : statut « soumis » pour le tenant courant (recrutement, fondateur, RH, commandement).</p>
        <ul class="space-y-2">
            <?php foreach ($staff as $e): ?>
                <?php
                $id = (int) ($e['id'] ?? 0);
                $detailUrl = url('back-office/recruitments/' . $id);
                ?>
                <li>
                    <a href="<?= htmlspecialchars($detailUrl, ENT_QUOTES, 'UTF-8') ?>" class="text-sm font-medium text-amber-950 hover:underline"><?= htmlspecialchars($fmtRow($e), ENT_QUOTES, 'UTF-8') ?></a>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>
</section>
<?php endif; ?>
