<?php
declare(strict_types=1);

/**
 * Raccourcis du tableau de bord — charte ATHENA.
 *
 * L’en-tête de page est rendu par la coque back-office ; cette vue produit le compteur
 * de places, le tableau ordonné et l’aperçu.
 *
 * @var list<array{row: array<string, mixed>, summary: string}> $dashboardPins
 * @var int $maxPins
 * @var list<array<string, mixed>> $previewPins
 */

$pins = is_array($dashboardPins ?? null) ? $dashboardPins : [];
$maxPins = max(1, (int) ($maxPins ?? 30));
$previewPins = is_array($previewPins ?? null) ? $previewPins : [];
$count = count($pins);

$h = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');

$pinTypeLabel = static function (string $t): string {
    return match ($t) {
        'url' => 'Lien libre',
        'route' => 'Page du portail',
        'document' => 'Document',
        'forum_topic' => 'Sujet de forum',
        'training' => 'Formation',
        'event' => 'Événement',
        default => $t !== '' ? ucfirst(str_replace('_', ' ', $t)) : 'Raccourci',
    };
};

$usageRatio = (int) round($count / $maxPins * 100);
$flashError = \App\Core\Session::getFlash('error');
$flashSuccess = \App\Core\Session::getFlash('success');
?>
<?php if ($flashError): ?>
<p class="ath-flash ath-flash--err" role="alert"><?= $h((string) $flashError) ?></p>
<?php endif; ?>
<?php if ($flashSuccess): ?>
<p class="ath-flash ath-flash--ok" role="status"><?= $h((string) $flashSuccess) ?></p>
<?php endif; ?>

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
    <a href="<?= $h(url('back-office/dashboard-pins/create')) ?>" class="ath-btn ath-btn--solid">Ajouter un raccourci</a>
</div>
<?php else: ?>
<div class="ath-note" style="background:#fdf3e2;border-color:#f2ddb4;">
    <p class="ath-note__title" style="color:#8a5a06;">Limite atteinte</p>
    <p class="ath-note__text" style="color:#8a5a06;">Les <?= $maxPins ?> places sont occupées : supprimez un raccourci pour en ajouter un autre.</p>
</div>
<?php endif; ?>

<?php
$csrf = \App\Core\Csrf::token();

$athTableTitle = 'Raccourcis publiés';
$athTableCount = $count;
$athTableCols = ['RANG|r', 'RACCOURCI', 'TYPE'];
$athTableRows = [];
$athTableRowActions = [];
foreach ($pins as $index => $item) {
    $row = is_array($item['row'] ?? null) ? $item['row'] : [];
    $id = (int) ($row['id'] ?? 0);
    $athTableRows[] = [
        (string) ($index + 1),
        (string) ($item['summary'] ?? '—'),
        $pinTypeLabel((string) ($row['pin_type'] ?? '')),
    ];

    // Balisage d’action construit ici, échappements compris (cf. contrat de ath_table.php).
    $moveUrl = $h(url('back-office/dashboard-pins/' . $id . '/move'));
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
        . '<a href="' . $h(url('back-office/dashboard-pins/' . $id . '/edit')) . '" class="ath-row-action">Modifier</a> '
        . '<form method="post" action="' . $h(url('back-office/dashboard-pins/' . $id . '/delete')) . '"'
        . ' onsubmit="return confirm(\'Supprimer ce raccourci ? Il disparaîtra du tableau de bord des membres.\');">'
        . '<input type="hidden" name="_csrf_token" value="' . $h($csrf) . '">'
        . '<button type="submit" class="ath-row-action ath-row-action--danger">Supprimer</button>'
        . '</form>';
}
$athTableActionsLabel = 'ORDRE & ÉDITION';
$athTableFilters = [];
$athTableMinWidth = '1020px';
$athTableShowCheckbox = false;
$athTableExportUrl = null;
$athTablePager = null;
$athTableRowHrefs = null;
$athTableFoot = $pins === []
    ? 'Aucun raccourci : les membres ne verront que le hub standard.'
    : 'L’ordre ci-dessus est celui vu par les membres. Chacun ne voit que les raccourcis autorisés par ses droits de lecture.';
require base_path('views/partials/ath_table.php');
?>

<?php if ($previewPins !== []): ?>
<h2 class="ath-section-title">Aperçu selon vos droits actuels</h2>
<div class="ath-card" style="padding:14px 16px;">
    <div class="ath-item__tags" style="margin-top:0;">
        <?php foreach ($previewPins as $p): ?>
            <?php $label = (string) ($p['label'] ?? ''); ?>
            <?php if (!empty($p['href'])): ?>
            <a href="<?= $h((string) $p['href']) ?>" class="ath-tag ath-tag--neut"><?= $h($label) ?></a>
            <?php else: ?>
            <span class="ath-tag ath-tag--neut"><?= $h($label) ?></span>
            <?php endif; ?>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>
