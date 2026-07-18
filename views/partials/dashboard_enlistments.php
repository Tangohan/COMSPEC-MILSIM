<?php
declare(strict_types=1);

/**
 * Candidatures — cartes densifiées pour la grille ops (Athena Command clair).
 *
 * @var list<array<string,mixed>> $my_enlistments_pending
 * @var list<array<string,mixed>> $staff_enlistments_pending
 * @var bool $show_staff_enlistments
 */

$my = $my_enlistments_pending ?? [];
$staff = $staff_enlistments_pending ?? [];
$showStaff = !empty($show_staff_enlistments);
if ($my === [] && ($staff === [] || !$showStaff)) {
    return;
}

$gate = \App\Core\Gate::getInstance();
$canOpenBo = $gate->allows('admin.organization') || $gate->allows('admin.access') || $showStaff;

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
<div class="cc-stack">
    <?php if ($showStaff && $staff !== []): ?>
    <section class="cc-card overflow-hidden">
        <div class="cc-card__head">
            <div>
                <p class="cc-kicker cc-kicker--primary">À traiter</p>
                <h2 class="cc-card__title">Décisions en attente</h2>
            </div>
            <a href="<?= htmlspecialchars(url('back-office/recruitments') . '?status=submitted', ENT_QUOTES, 'UTF-8') ?>" class="cc-card__link">
                Recrutement →
            </a>
        </div>
        <ul class="cc-rows">
            <?php foreach ($staff as $e): ?>
                <?php
                $meta = $rowMeta($e);
                $detailUrl = url('back-office/recruitments/' . $meta['id'] . '?dossier=1');
                ?>
                <li>
                    <a href="<?= htmlspecialchars($detailUrl, ENT_QUOTES, 'UTF-8') ?>" class="cc-row">
                        <div class="cc-row__body">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <p class="cc-row__title"><?= htmlspecialchars($meta['name'], ENT_QUOTES, 'UTF-8') ?></p>
                                <span class="cc-badge cc-badge--amber">À traiter</span>
                            </div>
                            <p class="cc-row__meta">
                                <?= $meta['mail'] !== '' ? htmlspecialchars($meta['mail'], ENT_QUOTES, 'UTF-8') : '—' ?>
                                <?php if ($meta['date'] !== ''): ?> · <?= htmlspecialchars($meta['date'], ENT_QUOTES, 'UTF-8') ?><?php endif; ?>
                            </p>
                        </div>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
    </section>
    <?php endif; ?>

    <?php if ($my !== []): ?>
    <section class="cc-card overflow-hidden">
        <div class="cc-card__head">
            <div>
                <p class="cc-kicker" style="color:#0369a1">Mes dossiers</p>
                <h2 class="cc-card__title">Candidatures en attente</h2>
            </div>
        </div>
        <ul class="cc-rows">
            <?php foreach ($my as $e): ?>
                <?php
                $meta = $rowMeta($e);
                $detailUrl = url('back-office/recruitments/' . $meta['id'] . '?dossier=1');
                ?>
                <li>
                    <?php if ($canOpenBo): ?>
                    <a href="<?= htmlspecialchars($detailUrl, ENT_QUOTES, 'UTF-8') ?>" class="cc-row">
                    <?php else: ?>
                    <div class="cc-row">
                    <?php endif; ?>
                        <div class="cc-row__body">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <p class="cc-row__title"><?= htmlspecialchars($meta['name'], ENT_QUOTES, 'UTF-8') ?></p>
                                <span class="cc-badge cc-badge--sky-solid"><?= $canOpenBo ? 'En cours' : 'En attente' ?></span>
                            </div>
                            <p class="cc-row__meta">
                                <?= $meta['mail'] !== '' ? htmlspecialchars($meta['mail'], ENT_QUOTES, 'UTF-8') : '—' ?>
                                <?php if ($meta['date'] !== ''): ?> · <?= htmlspecialchars($meta['date'], ENT_QUOTES, 'UTF-8') ?><?php endif; ?>
                            </p>
                        </div>
                    <?php if ($canOpenBo): ?>
                    </a>
                    <?php else: ?>
                    </div>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
    </section>
    <?php endif; ?>
</div>
