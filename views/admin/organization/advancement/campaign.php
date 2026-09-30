<?php
declare(strict_types=1);
$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$success = \App\Core\Session::getFlash('success'); $error = \App\Core\Session::getFlash('error');
$status = (string) $campaign['status'];
?>
<div class="adv-page">
  <?php if ($success): ?><p class="adv-flash is-ok"><?= $h($success) ?></p><?php endif; ?><?php if ($error): ?><p class="adv-flash is-bad"><?= $h($error) ?></p><?php endif; ?>
  <section class="adv-hero"><div><span class="adv-eyebrow">CAMPAGNE <?= (int) $campaign['year'] ?> · <?= $h(strtoupper($status)) ?></span><h2><?= $h($campaign['grade_label']) ?></h2><p>Du <?= $h(date('d/m/Y', strtotime($campaign['opens_at']))) ?> au <?= $h(date('d/m/Y', strtotime($campaign['closes_at']))) ?> · quota <?= $campaign['quota_slots'] !== null ? (int) $campaign['quota_slots'] : 'illimité' ?>.</p></div>
    <div class="adv-actions"><?php if ($status === 'draft'): ?><form method="post" action="<?= $h(url('back-office/rh/avancement/campagnes/' . $campaign['id'] . '/ouvrir')) ?>"><?= \App\Core\Csrf::field() ?><button class="ath-btn ath-btn--solid">Ouvrir</button></form><?php elseif ($status === 'open'): ?><form method="post" action="<?= $h(url('back-office/rh/avancement/campagnes/' . $campaign['id'] . '/commission')) ?>"><?= \App\Core\Csrf::field() ?><button class="ath-btn ath-btn--solid">Passer en commission</button></form><?php elseif ($status === 'commission'): ?><form method="post" action="<?= $h(url('back-office/rh/avancement/campagnes/' . $campaign['id'] . '/publier')) ?>" onsubmit="return confirm('La publication attribue les grades et est irréversible. Continuer ?')"><?= \App\Core\Csrf::field() ?><button class="ath-btn ath-btn--solid">Publier le tableau</button></form><?php endif; ?><a class="ath-btn" href="<?= $h(url('back-office/rh/avancement')) ?>">Retour</a></div>
  </section>
  <section class="adv-panel adv-panel--table"><div class="adv-panel__head"><div><span class="adv-eyebrow">COMMISSION</span><h3><?= count($candidacies) ?> candidature<?= count($candidacies) > 1 ? 's' : '' ?></h3></div></div>
    <table class="adv-table"><thead><tr><th>Rang</th><th>Candidat</th><th>Éligibilité</th><th>Mobilité</th><th>Avis & décision</th><th></th></tr></thead><tbody>
    <?php foreach ($candidacies as $c): ?><tr><td><?= $c['preference_rank'] ? (int) $c['preference_rank'] . '/' . count($candidacies) : '—' ?></td>
      <td><strong><?= $h($c['display_name'] ?: $c['callsign'] ?: $c['email']) ?></strong><small><?= $h(date('d/m/Y H:i', strtotime($c['volunteered_at']))) ?></small></td>
      <td><span class="adv-status <?= $c['is_eligible'] ? 'is-ok' : 'is-bad' ?>"><?= $c['is_eligible'] ? 'Éligible' : 'Non éligible' ?></span><small><?= $h($c['eligibility_reason'] ?? '') ?></small></td>
      <td><?= $c['mobility_requested'] ? $h($c['billet_title'] ?: 'Demandée') : 'Non' ?></td>
      <td><?php if ($status === 'commission'): ?><form class="adv-decision" method="post" action="<?= $h(url('back-office/rh/avancement/campagnes/' . $campaign['id'] . '/candidatures/' . $c['id'])) ?>"><?= \App\Core\Csrf::field() ?>
        <input type="number" min="1" name="preference_rank" value="<?= $c['preference_rank'] ? (int) $c['preference_rank'] : '' ?>" placeholder="Rang">
        <select name="commission_opinion"><option value="">Avis</option><option value="proposed" <?= $c['commission_opinion'] === 'proposed' ? 'selected' : '' ?>>Proposé</option><option value="not_proposed" <?= $c['commission_opinion'] === 'not_proposed' ? 'selected' : '' ?>>Non proposé</option></select>
        <select name="decision"><option value="">Décision</option><option value="registered" <?= $c['decision'] === 'registered' ? 'selected' : '' ?>>Inscrit</option><option value="not_registered" <?= $c['decision'] === 'not_registered' ? 'selected' : '' ?>>Non inscrit</option></select><button class="ath-btn">Enregistrer</button></form><?php else: ?><?= $h($c['decision'] ?? 'En attente') ?><?php endif; ?></td>
      <td><?php if ($status !== 'published'): ?><form method="post" action="<?= $h(url('back-office/rh/avancement/campagnes/' . $campaign['id'] . '/candidatures/' . $c['id'] . '/reverifier')) ?>"><?= \App\Core\Csrf::field() ?><button class="adv-link">Revérifier</button></form><?php endif; ?></td>
    </tr><?php endforeach; ?></tbody></table>
    <?php if (!$candidacies): ?><div class="adv-empty">Aucune candidature pour cette campagne.</div><?php endif; ?>
  </section>
</div>
