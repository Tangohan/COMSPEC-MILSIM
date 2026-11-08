<?php

declare(strict_types=1);

/**
 * Synthèses de comptes rendus (/back-office/atak/comptes-rendus/syntheses) : liste et choix des comptes rendus à condenser.
 *
 * @var list<array<string, mixed>> $aarSyntheses
 * @var list<array<string, mixed>> $aarAll       comptes rendus à condenser (vide sans le droit d'écrire)
 * @var bool $aarCanConclude
 */
$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$base = url('back-office/atak/comptes-rendus');
$syntheses = is_array($aarSyntheses ?? null) ? $aarSyntheses : [];
$all = is_array($aarAll ?? null) ? $aarAll : [];
$canConclude = !empty($aarCanConclude);
$fmt = static function (mixed $raw): string {
    $t = is_string($raw) && $raw !== '' ? strtotime($raw) : false;

    return $t ? date('d/m/Y', $t) : '—';
};
?>
<div class="bo-aar">
  <section class="ath-table-panel">
    <div class="ath-table-toolbar"><span class="ath-table-toolbar__title">Synthèses de plusieurs comptes rendus</span><span class="ath-table-toolbar__count"><?= count($syntheses) ?></span></div>
    <?php if ($syntheses === []): ?>
      <div class="bo-aar-empty"><p class="ath-panel__lead">Aucune synthèse pour l’instant.</p>
        <?php if ($canConclude): ?><p class="bo-aar-muted">Choisissez des comptes rendus ci-dessous pour en condenser plusieurs en un seul texte.</p><?php endif; ?></div>
    <?php else: ?>
      <table class="ath-table">
        <thead><tr><th>Titre</th><th>Comptes rendus</th><th>Auteur</th><th>Date</th></tr></thead>
        <tbody>
        <?php foreach ($syntheses as $sy): ?>
          <tr>
            <td><a href="<?= $h($base . '/syntheses/' . (int) $sy['id']) ?>"><?= $h((string) $sy['title']) ?></a></td>
            <td><?= count($sy['report_ids']) ?></td>
            <td><?= $h((string) ($sy['author_name'] ?? '')) ?></td>
            <td><?= $h($fmt($sy['created_at'] ?? null)) ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </section>
  <?php if ($canConclude): ?>
  <form method="get" action="<?= $h($base . '/syntheses/nouvelle') ?>" class="ath-panel">
    <p class="bo-aar-side__t">Nouvelle synthèse : cochez au moins deux comptes rendus</p>
    <?php if ($all === []): ?><p class="bo-aar-muted">Aucun compte rendu à condenser.</p><?php endif; ?>
    <div class="bo-aar-annex__list">
      <?php foreach ($all as $rep): ?>
        <label class="bo-aar-annex__row"><input type="checkbox" name="ids[]" value="<?= (int) $rep['id'] ?>"> <b><?= $h((string) ($rep['reference_code'] ?? '')) ?></b> <span><?= $h((string) (($rep['operation_label'] ?? '') !== '' ? $rep['operation_label'] : ($rep['title'] ?? ''))) ?></span> <small><?= $h($fmt($rep['reported_at'] ?? null)) ?></small></label>
      <?php endforeach; ?>
    </div>
    <button type="submit" class="ath-btn ath-btn--solid">Condenser en une synthèse</button>
  </form>
  <?php endif; ?>
</div>
