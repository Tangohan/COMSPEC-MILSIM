<?php
/**
 * Barre des salons de l'espace commun : une ligne d'onglets numérotés (pastilles de non-lus, cadenas des salons du
 * renseignement), la recherche rapide et le bouton du panneau. Elle reste collée sous l'en-tête et défile sur les petits écrans.
 *
 * @var array<string, mixed> $cm
 * @var list<array<string, mixed>> $salons
 */

use App\Services\Jnet\JnetCommonRules;

$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$separated = false;
?>
<div class="jc-sal" data-jc-sal>
    <nav class="jc-tabs" aria-label="Salons" data-jc-rail>
        <?php foreach ($salons as $s): ?>
            <?php if (!empty($s['sse']) && !$separated): $separated = true; ?><span class="jc-sep" aria-hidden="true"></span><?php endif; ?>
            <a class="jc-sb<?= !empty($s['active']) ? ' is-active' : '' ?><?= !empty($s['locked']) ? ' is-locked' : '' ?>" href="<?= $h((string) $s['href']) ?>" data-jc-salon="<?= $h((string) $s['key']) ?>"<?= !empty($s['active']) ? ' aria-current="page"' : '' ?>>
                <?php if ((string) $s['num'] !== ''): ?><span class="jc-sb__n" aria-hidden="true"><?= $h((string) $s['num']) ?></span><?php else: ?><?= JnetCommonRules::icon('lock') ?><?php endif; ?>
                <span class="jc-sb__t"><?= $h((string) $s['label']) ?></span>
                <?php if (!empty($s['locked'])): ?>
                    <span class="ja-sr">Réservé aux membres habilités au renseignement</span>
                <?php elseif ((int) $s['badge'] > 0): ?>
                    <em class="jc-badge<?= !empty($s['badgeIsUnread']) ? ' is-unread' : '' ?>"><?= min(99, (int) $s['badge']) ?></em>
                <?php endif; ?>
            </a>
        <?php endforeach; ?>
    </nav>
    <button type="button" class="jc-srch" data-jc-open-pal aria-keyshortcuts="/" aria-haspopup="dialog"><?= JnetCommonRules::icon('search') ?><span class="jc-srch__t">Chercher</span><kbd>/</kbd></button>
    <button type="button" class="jc-srch jc-srch--ic" data-jc-aside-toggle aria-pressed="true" aria-controls="jc-aside" title="Afficher ou masquer le panneau"><?= JnetCommonRules::icon('panel') ?><span class="ja-sr">Panneau</span></button>
</div>
