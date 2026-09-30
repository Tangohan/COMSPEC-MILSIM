<?php
declare(strict_types=1);
$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$via = ['initial' => 'Attribution initiale', 'seniority' => 'Ancienneté', 'choice' => 'Choix'];
?>
<div class="bo-member-situation adv-page">
  <?php if (!empty($success)): ?><p class="adv-flash is-ok"><?= $h($success) ?></p><?php endif; ?><?php if (!empty($error)): ?><p class="adv-flash is-bad"><?= $h($error) ?></p><?php endif; ?>
  <?php foreach ($campaigns as $campaign): $eligibility = $campaign['eligibility']; ?>
    <section class="adv-personal-callout <?= $eligibility['is_eligible'] ? 'is-ready' : 'is-waiting' ?>">
      <div><span class="adv-eyebrow">CAMPAGNE OUVERTE · JUSQU’AU <?= $h(date('d/m/Y', strtotime($campaign['closes_at']))) ?></span>
        <h2><?= $eligibility['is_eligible'] ? 'Vous êtes éligible' : 'Conditions non encore réunies' ?> au grade de <?= $h($campaign['grade_label']) ?></h2>
        <p><?= $eligibility['is_eligible'] ? 'Votre ancienneté et vos qualifications ont été vérifiées.' : $h($eligibility['eligibility_reason']) ?></p>
      </div>
      <form method="post" action="<?= $h(url('back-office/ma-situation/avancement/' . $campaign['id'] . '/candidater')) ?>">
        <?= \App\Core\Csrf::field() ?><button class="ath-btn ath-btn--solid" type="submit">Me porter volontaire</button>
      </form>
    </section>
  <?php endforeach; ?>
  <section class="adv-panel"><div class="adv-panel__head"><div><span class="adv-eyebrow">DOSSIER DE CARRIÈRE</span><h3>Historique de grade</h3></div><div class="adv-count"><strong><?= count($gradeHistory) ?></strong><span>étapes</span></div></div>
    <ol class="adv-timeline"><?php foreach ($gradeHistory as $index => $grade): ?><li class="<?= $index === 0 && !$grade['ends_at'] ? 'is-current' : '' ?>"><span class="adv-timeline__dot"></span><div><span class="adv-eyebrow"><?= $h(date('d/m/Y', strtotime($grade['obtained_at']))) ?></span><h3><?= $h($grade['label']) ?> <small><?= $h($grade['short_label']) ?></small></h3><p><?= $h($via[$grade['obtained_via']] ?? $grade['obtained_via']) ?><?= $grade['ends_at'] ? ' · jusqu’au ' . $h(date('d/m/Y', strtotime($grade['ends_at']))) : ' · grade actuel' ?></p></div></li><?php endforeach; ?>
    <?php if (!$gradeHistory): ?><li class="adv-empty">Aucun historique de grade n’a encore été initialisé.</li><?php endif; ?></ol>
  </section>
</div>
