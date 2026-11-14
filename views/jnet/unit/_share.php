<?php
declare(strict_types=1);
/**
 * Page « Partager » (?vue=partager&kind=…&item=…) : le repli sans JavaScript de la fenêtre « Partager ».
 *
 * @var array<string, mixed> $usw
 * @var string $base
 * @var string $label
 */
$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$item = is_array($usw['shareItem'] ?? null) ? $usw['shareItem'] : ['kind' => 'exchange', 'id' => 0, 'title' => ''];
$targets = is_array($usw['shareTargets'] ?? null) ? $usw['shareTargets'] : [];
$kind = (string) $item['kind'];
$backTab = ['exchange' => '?vue=echanges&direct=1', 'page' => '?vue=page&p=' . (int) $item['id'] . '&direct=1', 'file' => '?vue=documents&direct=1'][$kind] ?? '?direct=1';
?>
<header class="us-page">
    <h1 class="ja-disp ja-disp--md">Partager avec une autre unité.</h1>
    <p>Depuis <?= $h($label) ?>, vers une unité où vous pouvez publier.</p>
    <a class="us-btn" href="<?= $h($base . $backTab) ?>">Annuler</a>
</header>
<?php if ($targets === [] || (int) $item['id'] <= 0): ?>
    <div class="jn-empty"><p><?= (int) $item['id'] <= 0 ? 'Rien à partager : ouvrez cette page depuis un échange, une page ou un fichier.' : 'Vous ne pouvez publier dans aucune autre unité : le partage n’est pas disponible pour vous.' ?></p></div>
<?php else: ?>
    <div class="uw-share-page">
        <?php $shareKind = $kind; $shareId = (int) $item['id']; $shareTitle = (string) $item['title']; $shareUid = 'page'; require base_path('views/jnet/unit/_share_form.php'); ?>
    </div>
<?php endif; ?>
