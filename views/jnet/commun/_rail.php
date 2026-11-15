<?php
/**
 * Barre des salons de l'espace commun : identité de l'organisation, salons par sections repliables (pastilles de non-lus,
 * salon actif, cadenas des salons réservés au renseignement), puis les autres écrans de JNET.
 *
 * @var array<string, mixed> $cm
 * @var list<array<string, mixed>> $salons
 */

use App\Services\Jnet\JnetCommonRules;
use App\Support\JnetUi;

$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$postureKey = strtolower((string) ($cm['posture']['key'] ?? 'green'));
$postureClass = in_array($postureKey, ['red', 'amber'], true) ? $postureKey : 'green';
$orgLabel = (string) ($cm['orgLabel'] ?? 'Organisation');
$monogram = mb_strtoupper(mb_substr($orgLabel, 0, 2));
$sections = is_array($cm['sections'] ?? null) ? $cm['sections'] : JnetCommonRules::SECTIONS;
$others = [
    ['label' => 'Fiche d’unité', 'href' => url('jnet/unite')],
    ['label' => 'Personnel', 'href' => url('jnet/personnel')],
    ['label' => 'Opérations', 'href' => url('jnet/operations')],
    ['label' => 'Exploitation', 'href' => url('jnet/exploitation')],
    ['label' => 'Bibliothèque', 'href' => url('jnet/bibliotheque')],
    ['label' => 'Guide de l’extranet', 'href' => url('jnet/guide')],
];
?>
<aside class="jc-rail" id="jc-rail" aria-label="Barre des salons">
<div class="jc-sticky">
    <div class="jc-org">
        <?= JnetUi::crest($monogram, (string) ($cm['orgBadge'] ?? ''), 'jc-org__logo') ?>
        <div class="jc-org__id">
            <strong class="jc-org__name" translate="no"><?= $h($orgLabel) ?></strong>
            <span class="jc-org__sub"><span class="jc-dot jc-dot--<?= $h($postureClass) ?>" aria-hidden="true"></span><?= $h((string) ($cm['posture']['label'] ?? '')) ?></span>
        </div>
    </div>
    <nav class="jc-rail__nav" aria-label="Salons" data-jc-rail>
        <?php foreach ($sections as $sectionKey => $sectionLabel): ?>
            <details class="jc-sec" data-jc-sec="<?= $h($sectionKey) ?>" open>
                <summary class="jc-sec__t"><?= JnetCommonRules::icon('caret') ?><span><?= $h($sectionLabel) ?></span></summary>
                <ul class="jc-sec__list">
                    <?php foreach ($salons as $s): ?>
                        <?php if ($s['section'] !== $sectionKey) { continue; } ?>
                        <li>
                            <a class="jc-ch<?= !empty($s['active']) ? ' is-active' : '' ?>" href="<?= $h((string) $s['href']) ?>" data-jc-salon="<?= $h((string) $s['key']) ?>"<?= !empty($s['active']) ? ' aria-current="page"' : '' ?>>
                                <?= JnetCommonRules::icon((string) $s['icon']) ?>
                                <span class="jc-ch__t"><?= $h((string) $s['label']) ?></span>
                                <?php if (!empty($s['locked'])): ?>
                                    <span class="jc-ch__lock" title="Réservé aux membres habilités au renseignement"><?= JnetCommonRules::icon('lock') ?><span class="ja-sr">Réservé aux membres habilités au renseignement</span></span>
                                <?php elseif ((int) $s['badge'] > 0): ?>
                                    <em class="jc-badge<?= !empty($s['badgeIsUnread']) ? ' is-unread' : '' ?>"><?= min(99, (int) $s['badge']) ?></em>
                                <?php endif; ?>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </details>
        <?php endforeach; ?>
        <details class="jc-sec" data-jc-sec="ecrans" open>
            <summary class="jc-sec__t"><?= JnetCommonRules::icon('caret') ?><span>Autres écrans</span></summary>
            <ul class="jc-sec__list">
                <?php foreach ($others as $o): ?>
                    <li><a class="jc-ch jc-ch--plain" href="<?= $h($o['href']) ?>"><span class="jc-ch__t"><?= $h($o['label']) ?></span></a></li>
                <?php endforeach; ?>
            </ul>
        </details>
    </nav>
    <p class="jc-rail__hint">Flèches haut et bas : changer de salon. Touche / : chercher dans le salon.</p>
</div>
</aside>
