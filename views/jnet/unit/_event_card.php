<?php
declare(strict_types=1);
/**
 * Prochain rendez-vous de la communauté (module Événements) vu depuis l'espace : date, lieu, réponses des membres de
 * l'unité et réponse du lecteur. Les réponses passent par le service des événements (POST /jnet/u/{id}/presence).
 *
 * @var array<string, mixed> $event résultat de JnetUnitWorkspaceService::nextEvent
 * @var bool $evCanAnswer
 * @var string $evAction
 */
$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$event = is_array($event ?? null) ? $event : [];
$counts = is_array($event['counts'] ?? null) ? $event['counts'] : ['yes' => 0, 'maybe' => 0, 'no' => 0, 'none' => 0];
$total = max(0, (int) ($event['total'] ?? 0));
$answers = ['yes' => ['Présent', '#34d399'], 'maybe' => ['Incertain', '#fbbf24'], 'no' => ['Absent', '#f87171']];
$legend = ['yes' => ['Présents', '#34d399'], 'maybe' => ['Incertains', '#fbbf24'], 'no' => ['Absents', '#f87171'], 'none' => ['Sans réponse', '#94a3b8']];
$mine = (string) ($event['mine'] ?? '');
?>
<article class="uw-event" id="rendez-vous">
    <div class="uw-event__top">
        <?php if ((string) ($event['countdown'] ?? '') !== ''): ?><span class="uw-event__count"><?= $h((string) $event['countdown']) ?></span><?php endif; ?>
        <h3 class="ja-disp ja-disp--sm"><a href="<?= $h((string) ($event['href'] ?? '#')) ?>"><?= $h((string) ($event['title'] ?? '')) ?></a></h3>
    </div>
    <div class="uw-event__body">
        <dl class="uw-dl">
            <dt>Date</dt><dd><?= $h((string) ($event['when'] ?? '')) ?></dd>
            <?php if ((string) ($event['location'] ?? '') !== ''): ?><dt>Lieu</dt><dd><?= $h((string) $event['location']) ?></dd><?php endif; ?>
            <dt>Présents</dt><dd><?= (int) $counts['yes'] ?> / <?= $total ?></dd>
        </dl>
        <?php if ($total > 0): ?>
            <div class="uw-bar" role="img" aria-label="<?= (int) $counts['yes'] ?> présents, <?= (int) $counts['maybe'] ?> incertains, <?= (int) $counts['no'] ?> absents, <?= (int) $counts['none'] ?> sans réponse sur <?= $total ?>">
                <?php foreach ($legend as $k => [$name, $color]): ?>
                    <i style="width: <?= round(((int) ($counts[$k] ?? 0)) / $total * 100, 2) ?>%; background: <?= $h($color) ?>"></i>
                <?php endforeach; ?>
            </div>
            <ul class="uw-legend">
                <?php foreach ($legend as $k => [$name, $color]): ?>
                    <li><i style="background: <?= $h($color) ?>"></i><?= $h($name) ?> <b><?= (int) ($counts[$k] ?? 0) ?></b></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
        <?php if (!empty($evCanAnswer)): ?>
            <form class="uw-answer" method="post" action="<?= $h((string) ($evAction ?? '')) ?>">
                <?= \App\Core\Csrf::field() ?>
                <input type="hidden" name="event_id" value="<?= (int) ($event['id'] ?? 0) ?>">
                <p class="uw-answer__label" id="uw-answer-label">Votre réponse</p>
                <div class="uw-seg" role="group" aria-labelledby="uw-answer-label">
                    <?php foreach ($answers as $k => [$name, $color]): ?>
                        <button type="submit" name="status" value="<?= $h($k) ?>" style="--p: <?= $h($color) ?>"<?= $mine === $k ? ' class="is-on" aria-pressed="true"' : ' aria-pressed="false"' ?>><?= $h($name) ?></button>
                    <?php endforeach; ?>
                </div>
            </form>
        <?php elseif ($mine !== ''): ?>
            <p class="uw-note">Votre réponse : <b><?= $h($answers[$mine][0] ?? $mine) ?></b></p>
        <?php endif; ?>
    </div>
</article>
