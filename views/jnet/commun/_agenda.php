<?php
/**
 * Salon « Agenda » : les événements à venir de la communauté (la table de la page « Événements »), groupés par jour, avec la
 * réponse du lecteur (présent, incertain, absent) et le nombre de réponses déjà données. Rien n'est inventé : sans événement
 * ou sans module, le salon le dit.
 *
 * @var array<string, mixed> $view
 */

use App\Services\Jnet\JnetAgenda;
use App\Services\Jnet\JnetCommonRules;

$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$type = (string) ($view['type'] ?? '');
$types = is_array($view['types'] ?? null) ? $view['types'] : [];
$counts = is_array($view['counts'] ?? null) ? $view['counts'] : [];
$groups = is_array($view['groups'] ?? null) ? $view['groups'] : [];
$typeTone = ['operation' => 'bad', 'formation' => 'violet', 'evenement' => 'info', 'autre' => 'dim'];
?>
<div class="jc-body">
    <?php if (empty($view['enabled'])): ?>
        <div class="jc-empty">
            <p><strong>Le module des événements n’est pas disponible.</strong></p>
            <p>Il n’est pas activé pour cette communauté, ou ses tables ne sont pas encore prêtes.</p>
        </div>
    <?php else: ?>
        <div class="jc-bar">
            <span class="jc-bar__l" aria-hidden="true">Type</span>
            <nav class="jc-chips" aria-label="Type d’événement">
                <a class="jc-chip<?= $type === '' ? ' is-on' : '' ?>" href="<?= $h(url(JnetCommonRules::path('agenda'))) ?>" data-jc-salon="agenda"<?= $type === '' ? ' aria-current="true"' : '' ?>>Tout<small><?= (int) ($view['total'] ?? 0) ?></small></a>
                <?php foreach ($types as $key => $label): ?>
                    <a class="jc-chip<?= $type === $key ? ' is-on' : '' ?>" href="<?= $h(url(JnetCommonRules::path('agenda', ['genre' => (string) $key]))) ?>" data-jc-salon="agenda"<?= $type === $key ? ' aria-current="true"' : '' ?>><?= $h((string) $label) ?><small><?= (int) ($counts[$key] ?? 0) ?></small></a>
                <?php endforeach; ?>
            </nav>
            <a class="jc-link jc-bar__more" href="<?= $h(url('evenements')) ?>">Tous les événements</a>
        </div>

        <?php if ($groups === []): ?>
            <div class="jc-empty">
                <p><strong>Rien de prévu.</strong></p>
                <p>Aucun événement de ce type à venir.</p>
            </div>
        <?php else: ?>
            <?php foreach ($groups as $g): ?>
                <section class="jc-agday<?= !empty($g['today']) ? ' is-today' : '' ?>" aria-label="<?= $h((string) $g['date']) ?>">
                    <h3 class="jc-agday__t">
                        <?php if ((string) $g['label'] !== ''): ?><span><?= $h((string) $g['label']) ?></span><span aria-hidden="true">·</span><?php endif; ?>
                        <span><?= $h(JnetAgenda::WEEKDAYS_LONG[(int) $g['weekday']]) ?></span> <time><?= $h((string) $g['date']) ?></time>
                    </h3>
                    <?php foreach ($g['items'] as $e): ?>
                        <article class="jc-evc jc-tone--<?= $h($typeTone[$e['typeKey']] ?? 'dim') ?>" id="ev-<?= (int) $e['id'] ?>">
                            <div class="jc-evc__h"><b class="jc-mono"><?= $h((string) $e['time']) ?></b><?php if (!empty($e['ongoing'])): ?><small>En cours</small><?php endif; ?></div>
                            <div class="jc-evc__m">
                                <span class="jc-pill jc-pill--tone"><?= $h((string) $e['type']) ?></span>
                                <h4 class="jc-evc__t"><?= $h((string) $e['title']) ?></h4>
                                <?php if ((string) $e['where'] !== ''): ?><p class="jc-evc__w" translate="no"><?= $h((string) $e['where']) ?></p><?php endif; ?>
                            </div>
                            <div class="jc-evc__r">
                                <?php require base_path('views/jnet/commun/_rsvp.php'); ?>
                                <span class="jc-mono" data-jc-rsvp-count="<?= (int) $e['id'] ?>" data-yes="<?= (int) $e['counts']['yes'] ?>">
                                    <?= $h(JnetCommonRules::fmt((int) $e['counts']['yes'] > 1 ? '%1 présents' : '%1 présent', (int) $e['counts']['yes'])) ?><?php if ((int) $e['counts']['maybe'] > 0): ?> · <?= $h(JnetCommonRules::fmt((int) $e['counts']['maybe'] > 1 ? '%1 incertains' : '%1 incertain', (int) $e['counts']['maybe'])) ?><?php endif; ?>
                                </span>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </section>
            <?php endforeach; ?>
        <?php endif; ?>
    <?php endif; ?>
</div>
