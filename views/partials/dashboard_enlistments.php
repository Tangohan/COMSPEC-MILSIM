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

$rowMeta = static function (array $e): array {
    $id = (int) ($e['id'] ?? 0);
    $fn = trim((string) ($e['first_name'] ?? ''));
    $ln = trim((string) ($e['last_name'] ?? ''));
    $name = trim($fn . ' ' . $ln);
    if ($name === '') {
        $name = 'Candidat';
    }
    $mail = trim((string) ($e['email'] ?? ''));
    $dt = (string) ($e['created_at'] ?? '');
    $dateStr = $dt !== '' ? date('d/m/Y', strtotime($dt)) : '';

    return [
        'id' => $id,
        'name' => $name,
        'mail' => $mail,
        'date' => $dateStr,
    ];
};
?>
<?php if ($my !== []): ?>
<section class="dash-impact border-b border-white/5 bg-[#070b09] text-white">
    <div class="mx-auto max-w-6xl px-6 py-10 md:px-10">
        <p class="di-kicker text-sky-300/90">Athena.</p>
        <h2 class="di-display di-display-md mt-3 text-white">Mes candidatures<span class="text-sky-300">.</span></h2>
        <p class="di-body mt-3 max-w-xl">Dossiers déjà transmis : le staff n’a pas encore rendu sa décision.</p>
        <ul class="mt-8 divide-y divide-white/[0.06] border border-white/[0.06]">
            <?php foreach ($my as $e): ?>
                <?php
                $meta = $rowMeta($e);
                $detailUrl = url('back-office/recruitments/' . $meta['id'] . '?dossier=1');
                ?>
                <li>
                    <?php if ($canOpenBo): ?>
                        <a href="<?= htmlspecialchars($detailUrl, ENT_QUOTES, 'UTF-8') ?>" class="group flex flex-wrap items-center justify-between gap-3 px-5 py-4 transition hover:bg-white/[0.03]">
                            <span class="min-w-0">
                                <span class="block text-base font-bold text-white group-hover:text-sky-200"><?= htmlspecialchars($meta['name'], ENT_QUOTES, 'UTF-8') ?></span>
                                <span class="mt-0.5 block text-sm text-white/40">
                                    <?= $meta['mail'] !== '' ? htmlspecialchars($meta['mail'], ENT_QUOTES, 'UTF-8') : '—' ?>
                                    <?php if ($meta['date'] !== ''): ?> · <?= htmlspecialchars($meta['date'], ENT_QUOTES, 'UTF-8') ?><?php endif; ?>
                                </span>
                            </span>
                            <span class="text-[10px] font-bold uppercase tracking-[0.18em] text-sky-300/80">Ouvrir →</span>
                        </a>
                    <?php else: ?>
                        <div class="flex flex-wrap items-center justify-between gap-3 px-5 py-4">
                            <span class="min-w-0">
                                <span class="block text-base font-bold text-white"><?= htmlspecialchars($meta['name'], ENT_QUOTES, 'UTF-8') ?></span>
                                <span class="mt-0.5 block text-sm text-white/40">
                                    <?= $meta['mail'] !== '' ? htmlspecialchars($meta['mail'], ENT_QUOTES, 'UTF-8') : '—' ?>
                                    <?php if ($meta['date'] !== ''): ?> · <?= htmlspecialchars($meta['date'], ENT_QUOTES, 'UTF-8') ?><?php endif; ?>
                                </span>
                            </span>
                            <span class="rounded-sm border border-sky-400/25 bg-sky-400/10 px-2.5 py-1 text-[10px] font-bold uppercase tracking-[0.16em] text-sky-200">En attente</span>
                        </div>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>
</section>
<?php endif; ?>

<?php if ($showStaff && $staff !== []): ?>
<section class="dash-impact border-b border-white/5 bg-[#0c0a07] text-white">
    <div class="mx-auto max-w-6xl px-6 py-10 md:px-10">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div class="min-w-0 max-w-2xl">
                <p class="di-kicker text-amber-300/90">Communauté</p>
                <h2 class="di-display di-display-md mt-3 text-white">Candidatures<br class="hidden sm:block"> à traiter<span class="text-amber-300">.</span></h2>
                <p class="di-body mt-3">Dossiers reçus pour votre organisation — à examiner et décider.</p>
            </div>
            <a href="<?= htmlspecialchars(url('back-office/recruitments') . '?status=submitted', ENT_QUOTES, 'UTF-8') ?>" class="inline-flex items-center gap-2 border border-amber-400/30 bg-amber-400/10 px-4 py-2.5 text-[10px] font-bold uppercase tracking-[0.18em] text-amber-100 transition hover:bg-amber-400/20">
                Espace recrutement →
            </a>
        </div>
        <ul class="mt-8 divide-y divide-white/[0.06] border border-white/[0.06]">
            <?php foreach ($staff as $e): ?>
                <?php
                $meta = $rowMeta($e);
                $detailUrl = url('back-office/recruitments/' . $meta['id'] . '?dossier=1');
                ?>
                <li>
                    <a href="<?= htmlspecialchars($detailUrl, ENT_QUOTES, 'UTF-8') ?>" class="group flex flex-wrap items-center justify-between gap-3 px-5 py-4 transition hover:bg-white/[0.03]">
                        <span class="min-w-0">
                            <span class="block text-base font-bold text-white group-hover:text-amber-100"><?= htmlspecialchars($meta['name'], ENT_QUOTES, 'UTF-8') ?></span>
                            <span class="mt-0.5 block text-sm text-white/40">
                                <?= $meta['mail'] !== '' ? htmlspecialchars($meta['mail'], ENT_QUOTES, 'UTF-8') : '—' ?>
                                <?php if ($meta['date'] !== ''): ?> · <?= htmlspecialchars($meta['date'], ENT_QUOTES, 'UTF-8') ?><?php endif; ?>
                            </span>
                        </span>
                        <span class="text-[10px] font-bold uppercase tracking-[0.18em] text-amber-300/80">Traiter →</span>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>
</section>
<?php endif; ?>
