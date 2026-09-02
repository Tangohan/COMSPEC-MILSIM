<?php
declare(strict_types=1);

/**
 * Tenues mises en avant sur le tableau de bord — charte ATHENA.
 *
 * @var list<array<string, mixed>> $kitPins
 * @var int $maxPins
 */

$pins = is_array($kitPins ?? null) ? $kitPins : [];
$maxPins = max(1, (int) ($maxPins ?? 12));
$count = count($pins);
$h = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
$usageRatio = (int) round($count / $maxPins * 100);
$flashError = \App\Core\Session::getFlash('error');
$flashSuccess = \App\Core\Session::getFlash('success');
$csrf = \App\Core\Csrf::token();
?>
<?php if ($flashError): ?>
<p class="ath-flash ath-flash--err" role="alert"><?= $h((string) $flashError) ?></p>
<?php endif; ?>
<?php if ($flashSuccess): ?>
<p class="ath-flash ath-flash--ok" role="status"><?= $h((string) $flashSuccess) ?></p>
<?php endif; ?>

<p class="ath-note__text" style="margin-bottom:16px;">
    Ces cartes apparaissent sur le tableau de bord, comme le catalogue des formations.
    Un PNG de personnage (fond transparent) se place devant le fond.
</p>

<div class="ath-meter ath-rise" style="margin-bottom:16px;">
    <div class="ath-meter__head">
        <span>Places occupées</span>
        <span class="ath-meter__value"><?= $count ?> / <?= $maxPins ?></span>
    </div>
    <div class="ath-meter__track">
        <span class="ath-meter__fill<?= $usageRatio >= 90 ? ' ath-meter__fill--warn' : '' ?>" style="width:<?= max(0, min(100, $usageRatio)) ?>%"></span>
    </div>
</div>

<?php if ($count < $maxPins): ?>
<div class="ath-form__actions" style="border-top:0;margin:0 0 16px;padding-top:0;">
    <a href="<?= $h(url('back-office/dashboard-tenues/create')) ?>" class="ath-btn ath-btn--solid">Mettre une tenue en avant</a>
    <a href="<?= $h(url('dashboard')) ?>" class="ath-btn">Voir le tableau de bord</a>
</div>
<?php else: ?>
<div class="ath-note" style="background:#fdf3e2;border-color:#f2ddb4;">
    <p class="ath-note__title" style="color:#8a5a06;">Limite atteinte</p>
    <p class="ath-note__text" style="color:#8a5a06;">Les <?= $maxPins ?> places sont occupées : retirez une tenue pour en ajouter une autre.</p>
</div>
<?php endif; ?>

<?php if ($pins !== []): ?>
<div class="dash-showcase__track" style="padding-left:0;padding-right:0;margin-bottom:20px;">
    <?php foreach ($pins as $card): ?>
    <article class="dash-showcase__card<?= !empty($card['has_figure']) ? ' dash-showcase__card--kit' : '' ?>">
        <?php if (!empty($card['has_figure'])): ?>
            <?php if (!empty($card['backdrop'])): ?>
            <img src="<?= $h((string) $card['backdrop']) ?>" alt="" class="dash-showcase__card-bg">
            <?php endif; ?>
            <img src="<?= $h((string) $card['figure']) ?>" alt="<?= $h((string) ($card['title'] ?? '')) ?>" class="dash-showcase__card-figure">
        <?php elseif (!empty($card['cover'])): ?>
            <img src="<?= $h((string) $card['cover']) ?>" alt="<?= $h((string) ($card['title'] ?? '')) ?>" class="dash-showcase__card-img">
        <?php endif; ?>
        <span class="dash-showcase__card-veil" aria-hidden="true"></span>
        <span class="dash-showcase__card-body">
            <span class="dash-showcase__card-badge"><?= $h((string) ($card['badge_label'] ?? 'Tenue')) ?></span>
            <span class="dash-showcase__card-title"><?= $h((string) ($card['title'] ?? '')) ?></span>
        </span>
    </article>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<?php
$athTableTitle = 'Tenues publiées';
$athTableCount = $count;
$athTableCols = ['RANG|r', 'TENUE', 'PASTILLE'];
$athTableRows = [];
$athTableRowActions = [];
foreach ($pins as $index => $card) {
    $id = (int) ($card['id'] ?? 0);
    $athTableRows[] = [
        (string) ($index + 1),
        (string) ($card['title'] ?? '—'),
        (string) ($card['badge_label'] ?? '—'),
    ];
    $moveUrl = $h(url('back-office/dashboard-tenues/' . $id . '/move'));
    $isFirst = $index === 0;
    $isLast = $index === $count - 1;
    $athTableRowActions[] = '<form method="post" action="' . $moveUrl . '">'
        . '<input type="hidden" name="_csrf_token" value="' . $h($csrf) . '">'
        . '<input type="hidden" name="direction" value="up">'
        . '<button type="submit" class="ath-row-action" title="Monter"' . ($isFirst ? ' disabled' : '') . '>↑</button>'
        . '</form> '
        . '<form method="post" action="' . $moveUrl . '">'
        . '<input type="hidden" name="_csrf_token" value="' . $h($csrf) . '">'
        . '<input type="hidden" name="direction" value="down">'
        . '<button type="submit" class="ath-row-action" title="Descendre"' . ($isLast ? ' disabled' : '') . '>↓</button>'
        . '</form> '
        . '<a href="' . $h(url('back-office/dashboard-tenues/' . $id . '/edit')) . '" class="ath-row-action">Modifier</a> '
        . '<form method="post" action="' . $h(url('back-office/dashboard-tenues/' . $id . '/delete')) . '"'
        . ' onsubmit="return confirm(\'Retirer cette tenue du tableau de bord ?\');">'
        . '<input type="hidden" name="_csrf_token" value="' . $h($csrf) . '">'
        . '<button type="submit" class="ath-row-action ath-row-action--danger">Retirer</button>'
        . '</form>';
}
$athTableActionsLabel = 'ORDRE & ÉDITION';
$athTableFilters = [];
$athTableMinWidth = '960px';
$athTableShowCheckbox = false;
$athTableExportUrl = null;
$athTablePager = null;
$athTableRowHrefs = null;
$athTableFoot = $pins === []
    ? 'Aucune tenue en vitrine : le tableau de bord n’affiche pas cette rangée.'
    : 'L’ordre ci-dessus est celui vu par les membres sur le tableau de bord.';
require base_path('views/partials/ath_table.php');
?>
