<?php
declare(strict_types=1);

/**
 * Modèles de debriefing (/back-office/atak/comptes-rendus/modeles).
 */
use App\Support\AarCustomForm;

$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$base = url('back-office/atak/comptes-rendus/modeles');
$templates = is_array($aarTemplates ?? null) ? $aarTemplates : [];
$usage = is_array($aarTemplateUsage ?? null) ? $aarTemplateUsage : [];
$csrf = '<input type="hidden" name="_csrf_token" value="' . $h($csrfToken ?? '') . '">';
$active = count(array_filter($templates, static fn ($t) => ($t['status'] ?? 'active') !== 'archived'));
?>
<div class="bo-aar">
  <section class="ath-table-panel">
    <div class="ath-table-toolbar">
      <span class="ath-table-toolbar__title">Questionnaires</span>
      <span class="ath-table-toolbar__count"><?= $active ?> actif<?= $active > 1 ? 's' : '' ?> · <?= count($templates) ?></span>
      <span class="ath-table-toolbar__spacer"></span>
      <span class="bo-aar-muted">Un compte rendu garde les questions en vigueur au moment où il a été déposé.</span>
    </div>
    <?php if ($templates === []): ?>
      <div class="bo-aar-empty">
        <h2 class="ath-panel__title">Aucun modèle</h2>
        <p class="ath-panel__lead">Un modèle remplace le formulaire standard par vos propres questions : réponses courtes, listes, cases à cocher, texte libre.</p>
        <p><a class="ath-btn ath-btn--solid" href="<?= $h($base . '/nouveau') ?>">Créer un modèle</a></p>
      </div>
    <?php else: ?>
    <div class="ath-table-wrap">
      <table class="ath-table bo-aar-table">
        <thead><tr><th>Modèle</th><th>Questions</th><th class="ath-th-num">Utilisé</th><th>Auteur</th><th>Statut</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($templates as $t):
            $tid = (int) ($t['id'] ?? 0);
            $archived = ($t['status'] ?? 'active') === 'archived';
            $fields = is_array($t['fields'] ?? null) ? $t['fields'] : [];
            $types = array_count_values(array_map(static fn ($f) => AarCustomForm::typeLabel((string) ($f['type'] ?? 'text')), array_filter($fields, 'is_array')));
            $req = count(array_filter($fields, static fn ($f) => is_array($f) && !empty($f['required'])));
            ?>
          <tr<?= $archived ? ' class="is-muted"' : '' ?>>
            <td><a class="bo-aar-cell-title" href="<?= $h($base . '/' . $tid) ?>"><?= $h($t['title'] ?? 'Modèle') ?></a><?php if (trim((string) ($t['description'] ?? '')) !== ''): ?><span class="bo-aar-cell-sub"><?= $h($t['description']) ?></span><?php endif; ?></td>
            <td><b><?= count($fields) ?></b><?= $req > 0 ? ' <span class="bo-aar-muted">dont ' . $req . ' obligatoire' . ($req > 1 ? 's' : '') . '</span>' : '' ?>
              <span class="bo-aar-cell-sub"><?= $h(implode(' · ', array_map(static fn ($l, $n) => $n . ' ' . mb_strtolower($l), array_keys($types), $types))) ?></span></td>
            <td class="ath-td-num"><?= (int) ($usage[$tid] ?? 0) ?></td>
            <td><?= $h(trim((string) ($t['author_name'] ?? '')) ?: '—') ?></td>
            <td><span class="ath-tag ath-tag--<?= $archived ? 'neut' : 'ok' ?>"><?= $archived ? 'Archivé' : 'Actif' ?></span></td>
            <td class="bo-aar-right bo-aar-rowbtns">
              <a class="ath-btn" href="<?= $h($base . '/' . $tid) ?>">Modifier</a>
              <form method="post" action="<?= $h($base . '/' . $tid . '/dupliquer') ?>"><?= $csrf ?><button type="submit" class="ath-btn">Dupliquer</button></form>
              <?php if ($archived): ?>
                <form method="post" action="<?= $h($base . '/' . $tid . '/reactiver') ?>"><?= $csrf ?><button type="submit" class="ath-btn">Réactiver</button></form>
              <?php else: ?>
                <form method="post" action="<?= $h($base . '/' . $tid . '/archiver') ?>" data-aar-confirm="Archiver ce modèle ? Il ne sera plus proposé ; les comptes rendus déjà déposés restent lisibles."><?= $csrf ?><button type="submit" class="ath-btn">Archiver</button></form>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
  </section>
</div>
<script src="<?= $h(asset_url('assets/js/back-office-aar.js')) ?>" defer></script>
