<?php
/**
 * Panneau de droite (grands écrans, repliable) : éléments épinglés (revérifiés pour ce lecteur) et unités à l'activité
 * récente. Rien n'est inventé : un bloc sans donnée est omis ou dit qu'il est vide.
 *
 * @var array<string, mixed> $cm
 * @var list<array<string, mixed>> $pins
 */

use App\Services\Jnet\JnetCommonRules;
use App\Support\JnetUi;

$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$recent = is_array($cm['recent'] ?? null) ? $cm['recent'] : [];
?>
<aside class="jc-aside" id="jc-aside" data-jc-aside aria-label="Panneau : épinglés et activité récente">
<div class="jc-sticky">
    <section class="jc-card" aria-labelledby="jc-aside-pins">
        <h2 id="jc-aside-pins" class="jc-card__t"><?= JnetCommonRules::icon('pin') ?><span>Épinglés</span></h2>
        <?php if (empty($cm['pinsReady'])): ?>
            <p class="jc-muted">Les épingles ne sont pas encore activées.</p>
        <?php elseif ($pins === []): ?>
            <p class="jc-muted">Rien d’épinglé pour le moment.</p>
        <?php else: ?>
            <ol class="jc-plist">
                <?php foreach ($pins as $p): ?>
                    <li class="jc-plist__item">
                        <?php if (!empty($p['visible'])): ?>
                            <a class="jc-plist__link" href="<?= $h((string) $p['href']) ?>">
                                <span class="jc-plist__kind"><?= $h((string) $p['typeLabel']) ?></span>
                                <strong><?= $h((string) $p['title']) ?></strong>
                            </a>
                        <?php else: ?>
                            <span class="jc-plist__link is-masked"><?= JnetCommonRules::icon('lock') ?><strong><span>Élément masqué</span></strong></span>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ol>
            <p class="jc-card__more"><a href="<?= $h(url(JnetCommonRules::path('epingles'))) ?>" data-jc-salon="epingles">Gérer les épinglés</a></p>
        <?php endif; ?>
    </section>

    <?php if ($recent !== []): ?>
        <section class="jc-card" aria-labelledby="jc-aside-recent">
            <h2 id="jc-aside-recent" class="jc-card__t"><?= JnetCommonRules::icon('user') ?><span>Unités récemment actives</span></h2>
            <ul class="jc-rlist">
                <?php foreach ($recent as $u): ?>
                    <li>
                        <a class="jc-rlist__item" href="<?= $h((string) $u['href']) ?>"<?= (string) $u['accent'] !== '' ? ' style="--u:' . $h((string) $u['accent']) . '"' : '' ?>>
                            <?= JnetUi::crest(mb_strtoupper(mb_substr((string) $u['label'], 0, 2)), (string) $u['badge'], 'jc-logo') ?>
                            <span class="jc-rlist__t"><strong translate="no"><?= $h((string) $u['label']) ?></strong><em>Dernier échange <?= $h((string) $u['when']) ?></em></span>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </section>
    <?php endif; ?>
</div>
</aside>
