<?php
declare(strict_types=1);
/**
 * Bloc « Émettre un document » : mêmes modèles et mêmes boutons sur la fiche personne et sur la fiche du registre. Un modèle
 * que le lecteur ne peut pas émettre n'a pas de bouton : il est listé dans « non disponibles » avec la raison (infobulle et
 * texte). Inclus par person_show.php et papers/identity_show.php.
 *
 * @var array<string, mixed> $sheetMenu données de SseSheetMenu::build
 */
use App\Core\Csrf;

$h = $h ?? static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$m = is_array($sheetMenu ?? null) ? $sheetMenu : [];
if ($m === [] || (int) ($m['id'] ?? 0) < 1) {
    return;
}
$mAllowed = (array) ($m['allowed'] ?? []);
$mLocked = (array) ($m['locked'] ?? []);
$mLang = ($m['lang'] ?? 'fr') === 'en' ? 'en' : 'fr';
$mIssues = (array) ($m['issues'] ?? []);
$sseExtraStyles = ($sseExtraStyles ?? '') . '<link rel="stylesheet" href="' . $h(asset_url('assets/css/sse-sheets.css')) . '">';
?>
<section class="shx" id="emettre" aria-labelledby="shx-title">
    <div class="shx__head">
        <h2 id="shx-title" class="shx__title">Émettre un document</h2>
        <p class="shx__sub">Fiches PDF de jeu de rôle, générées d’un clic à partir de cette fiche : les textes sont rédigés automatiquement, sans rien remplir.</p>
    </div>
    <?php if (($m['reason'] ?? null) !== null): ?>
        <p class="shx__note" role="note"><?= $h($m['reason']) ?></p>
    <?php else: ?>
        <?php if ($mAllowed === []): ?>
            <p class="shx__note" role="note">Aucun modèle n’est disponible pour vous sur cette fiche.</p>
        <?php else: ?>
            <div class="shx__buttons">
                <?php foreach ($mAllowed as $a): ?>
                    <?php $primary = ($a['model'] ?? '') === ($m['primary'] ?? null); ?>
                    <?php if (!empty($a['quick_url'])): ?>
                        <div class="shx__item">
                            <form method="post" action="<?= $h($a['quick_url']) ?>" class="shx__form">
                                <?= Csrf::field() ?>
                                <button type="submit" class="btn shx__btn<?= $primary ? ' shx__btn--primary' : '' ?>" title="<?= $h($a['hint']) ?>"><?= $h($a['label']) ?></button>
                            </form>
                            <a class="link shx__custom" href="<?= $h($a['url']) ?>">Personnaliser les textes</a>
                        </div>
                    <?php elseif (($a['action'] ?? 'link') === 'create_identity'): ?>
                        <form method="post" action="<?= $h($a['url']) ?>" class="shx__form">
                            <?= Csrf::field() ?>
                            <button type="submit" class="btn shx__btn" title="<?= $h($a['hint']) ?>"><?= $h($a['label']) ?></button>
                        </form>
                    <?php else: ?>
                        <a class="btn shx__btn<?= $primary ? ' shx__btn--primary' : '' ?>" href="<?= $h($a['url']) ?>" title="<?= $h($a['hint']) ?>"><?= $h($a['label']) ?></a>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        <?php
        $mDef = (string) ($m['default_level'] ?? '');
        if ($mAllowed !== [] && $mDef !== ''):
            $mHidden = array_map(static fn (string $c): string => \App\Support\SseRedactionExplain::label($c, $mLang), array_keys(array_filter(\App\Services\Sse\SseRedactionService::CATEGORIES, static fn (array $meta, string $c): bool => !\App\Services\Sse\SseRedactionService::visibleAt($c, $mDef), ARRAY_FILTER_USE_BOTH)));
            ?>
            <p class="shx__default-note" role="note">
                En un clic, le document est diffusé au niveau <strong><?= $h(\App\Support\SseUsClassification::abbr($mDef)) ?></strong> (réglage de la communauté).
                <?php if ($mHidden === []): ?>Rien n’est masqué.<?php else: ?>Passages masqués : <?= $h(implode(', ', $mHidden)) ?>.<?php endif; ?>
                « Personnaliser les textes » permet de choisir le niveau et d’en voir l’effet avant d’émettre.
            </p>
        <?php endif; ?>
        <?php if ($mLocked !== []): ?>
            <details class="shx__locked">
                <summary title="<?= $h(implode(' — ', array_map(static fn (array $l): string => $l['label'] . ' : ' . $l['reason'], $mLocked))) ?>"><?= count($mLocked) ?> modèle(s) non disponible(s)</summary>
                <ul>
                    <?php foreach ($mLocked as $l): ?>
                        <li><strong><?= $h($l['label']) ?></strong> : <?= $h($l['reason']) ?></li>
                    <?php endforeach; ?>
                </ul>
            </details>
        <?php endif; ?>
        <p class="shx__links">
            <?php if ($mIssues !== []): ?><a class="link" href="#documents-emis">Documents émis (<?= count($mIssues) ?>)</a><?php endif; ?>
            <a class="link" href="<?= $h($m['phrasesUrl'] ?? '#') ?>">Trames de phrases</a>
        </p>
    <?php endif; ?>
</section>
