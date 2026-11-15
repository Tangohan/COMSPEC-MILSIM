<?php
/**
 * Panneau de droite (grands écrans, repliable) : fil d'activité (les derniers échanges visibles du lecteur) et éléments
 * épinglés (revérifiés pour ce lecteur). Rien n'est inventé : un bloc sans donnée dit qu'il est vide.
 *
 * @var array<string, mixed> $cm
 * @var list<array<string, mixed>> $pins
 */

use App\Services\Jnet\JnetCommonRules;
use App\Support\JnetUi;

$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$feed = is_array($cm['feed'] ?? null) ? $cm['feed'] : [];
?>
<aside class="jc-aside" id="jc-aside" data-jc-aside aria-label="Panneau : activité et épinglés">
<div class="jc-sticky">
    <section class="jc-panel" aria-labelledby="jc-aside-feed">
        <header class="jc-panel__h">
            <h2 id="jc-aside-feed" class="jc-panel__t"><span class="jc-live" aria-hidden="true"></span>Fil d’activité</h2>
            <span class="jc-mono">récent</span>
        </header>
        <?php if ($feed === []): ?>
            <p class="jc-muted jc-panel__empty">Aucun échange récent.</p>
        <?php else: ?>
            <ul class="jc-fe">
                <?php foreach ($feed as $f): ?>
                    <?php
                    $href = $f['orgLevel']
                        ? url(JnetCommonRules::path('annonces')) . '#ex-' . (int) $f['id']
                        : url(JnetCommonRules::path('annonces', ['portee' => 'tout'])) . '#ex-' . (int) $f['id'];
                    ?>
                    <li>
                        <a class="jc-fi" href="<?= $h($href) ?>" data-jc-salon="annonces"<?= (string) $f['accent'] !== '' ? ' style="--u:' . $h((string) $f['accent']) . '"' : '' ?>>
                            <?= JnetUi::crest(mb_strtoupper(mb_substr((string) $f['label'], 0, 2)), (string) $f['badge'], 'jc-logo') ?>
                            <span class="jc-fi__t">
                                <span class="jc-fi__x"><b translate="no"><?= $h((string) $f['author']) ?></b> a diffusé <b translate="no"><?= $h((string) $f['title']) ?></b></span>
                                <small><?= $h((string) $f['when']) ?></small>
                            </span>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>

    <section class="jc-panel" aria-labelledby="jc-aside-pins">
        <header class="jc-panel__h">
            <h2 id="jc-aside-pins" class="jc-panel__t"><?= JnetCommonRules::icon('pin') ?>Épinglés</h2>
        </header>
        <?php if (empty($cm['pinsReady'])): ?>
            <p class="jc-muted jc-panel__empty">Les épingles ne sont pas encore activées.</p>
        <?php elseif ($pins === []): ?>
            <p class="jc-muted jc-panel__empty">Rien d’épinglé pour le moment.</p>
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

    <p class="jc-hint"><kbd>/</kbd> chercher · <kbd>Alt</kbd> + <kbd>↑</kbd><kbd>↓</kbd> changer de salon · <kbd>Échap</kbd> fermer</p>
</div>
</aside>
