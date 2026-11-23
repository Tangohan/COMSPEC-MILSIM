<?php
declare(strict_types=1);

/**
 * Tableau de bord : promotions à venir (grades planifiés) et récentes.
 * Réservé aux personnes qui peuvent changer un grade (le contrôleur ne fournit les données qu'à elles) ; masqué si vide.
 */

$promotions = is_array($dashboard_promotions ?? null) ? $dashboard_promotions : [];
$promoUpcoming = is_array($promotions['upcoming'] ?? null) ? $promotions['upcoming'] : [];
$promoRecent = is_array($promotions['recent'] ?? null) ? $promotions['recent'] : [];
if ($promoUpcoming === [] && $promoRecent === []) {
    return;
}
$promoEsc = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$promoMember = static fn (array $p): string => effectifs_workspace_url('membres/' . (int) $p['user_id']);
?>
<section class="dash-zone dash-zone--promotions dash-reveal" id="promotions-a-venir" aria-labelledby="dash-promo-title" data-dash-reveal>
    <div class="dash-zone__inner">
        <header class="dash-zone__head">
            <h2 id="dash-promo-title" class="dash-zone__title">Promotions à venir</h2>
        </header>
        <?php if ($promoUpcoming !== []): ?>
        <ul class="dash-sign__list">
            <?php foreach ($promoUpcoming as $p): ?>
            <?php
            $ts = strtotime((string) $p['scheduled_for']) ?: 0;
            $rel = (array) $p['relative'];
            $days = (int) ($rel['days'] ?? 0);
            ?>
            <li class="dash-sign__item">
                <div class="dash-sign__main">
                    <a class="dash-sign__title" href="<?= $promoEsc($promoMember($p)) ?>"><?= $promoEsc($p['name']) ?></a>
                    <p class="dash-sign__hint"><?= $promoEsc($p['from_label'] !== '' ? $p['from_label'] : '—') ?> → <?= $promoEsc($p['to_label']) ?></p>
                </div>
                <div class="dash-sign__side">
                    <span class="dash-sign__chip dash-sign__chip--neutral"><?php if ($rel['kind'] === 'today'): ?>Aujourd’hui<?php elseif ($rel['kind'] === 'tomorrow'): ?>Demain<?php else: ?>Dans <?= $days ?> jours<?php endif; ?></span>
                    <span class="dash-sign__deadline"><?= $promoEsc(date('d/m/Y H:i', $ts)) ?></span>
                </div>
            </li>
            <?php endforeach; ?>
        </ul>
        <?php else: ?>
        <p class="dash-zone__lead">Aucune promotion planifiée.</p>
        <?php endif; ?>

        <?php if ($promoRecent !== []): ?>
        <h3 class="dash-zone__title">Promotions récentes</h3>
        <ul class="dash-sign__list">
            <?php foreach ($promoRecent as $p): ?>
            <li class="dash-sign__item">
                <div class="dash-sign__main">
                    <a class="dash-sign__title" href="<?= $promoEsc($promoMember($p)) ?>"><?= $promoEsc($p['name']) ?></a>
                    <p class="dash-sign__hint"><?= $promoEsc($p['to_label']) ?></p>
                </div>
                <div class="dash-sign__side">
                    <span class="dash-sign__deadline"><?= $promoEsc(date('d/m/Y', strtotime((string) ($p['finished_at'] ?: $p['scheduled_for'])) ?: 0)) ?></span>
                </div>
            </li>
            <?php endforeach; ?>
        </ul>
        <?php endif; ?>
    </div>
</section>
