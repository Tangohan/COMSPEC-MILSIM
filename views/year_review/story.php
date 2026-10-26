<?php
declare(strict_types=1);

// Écrans de la rétrospective. Les noms et les nombres sont dans des éléments à part (translate="no" pour les noms),
// les libellés restent en français et sont traduits à l'affichage. Chaque écran « share » peut devenir une image
// 1080 × 1350 pour Discord (year-review.js la dessine à partir de ce qui est affiché, donc dans la langue de la page).

$h = $h ?? static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
$count = count($yrSlides);
?>
<div class="yr" data-yr data-year="<?= $yrYear ?>" data-logo="<?= $h(asset_url('assets/brand/athena-embleme-blanc.svg')) ?>">
    <div class="yr-stage" data-yr-stage>
        <div class="yr-progress" aria-hidden="true">
            <?php for ($i = 0; $i < $count; $i++): ?><span><i></i></span><?php endfor; ?>
        </div>
        <header class="yr-top">
            <p class="yr-brand"><img src="<?= $h(asset_url('assets/brand/athena-embleme-blanc.svg')) ?>" alt="" width="22" height="22"> ATHENA <span>· COMSPEC</span></p>
            <div class="yr-top__actions">
                <?php if (!empty($yrPreview)): ?><span class="yr-chip">Aperçu avant publication</span><?php endif; ?>
                <?php if (count($yrYears) > 1): ?>
                    <nav class="yr-years" aria-label="Autres années">
                        <?php foreach ($yrYears as $y): ?>
                            <a href="<?= $h(url('retrospective/' . (int) $y)) ?>"<?= (int) $y === $yrYear ? ' aria-current="page"' : '' ?>><?= (int) $y ?></a>
                        <?php endforeach; ?>
                    </nav>
                <?php endif; ?>
                <button type="button" class="yr-icon" data-yr-pause aria-label="Mettre en pause" title="Pause (espace)">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M8 5v14M16 5v14"/></svg>
                </button>
                <a class="yr-icon" href="<?= $h((string) $yrCloseUrl) ?>" aria-label="Fermer la rétrospective" title="Fermer (Échap)" data-yr-close>
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18"/></svg>
                </a>
            </div>
        </header>
        <?php foreach ($yrSlides as $i => $s): ?>
            <section class="yr-slide yr-tone--<?= $h((string) ($s['tone'] ?? 'night')) ?> yr-slide--<?= $h((string) $s['key']) ?>"
                     data-yr-slide data-key="<?= $h((string) $s['key']) ?>"<?= !empty($s['share']) ? ' data-share' : '' ?>
                     aria-roledescription="écran" aria-label="<?= $i + 1 ?> / <?= $count ?>"<?= $i > 0 ? ' hidden' : '' ?>>
                <div class="yr-slide__inner">
                    <?php if (($s['kicker'] ?? '') !== ''): ?>
                        <p class="yr-kicker" data-share-kicker><?= $h((string) $s['kicker']) ?></p>
                    <?php endif; ?>
                    <?php if (($s['name'] ?? '') !== ''): ?>
                        <p class="yr-name" translate="no" data-share-name><?= $h((string) $s['name']) ?></p>
                    <?php endif; ?>
                    <?php if (($s['big'] ?? '') !== ''): ?>
                        <p class="yr-big<?= mb_strlen((string) $s['big']) > 10 ? ' yr-big--words' : '' ?>" data-share-big><?= $h((string) $s['big']) ?></p>
                        <?php if (($s['big_label'] ?? '') !== ''): ?>
                            <p class="yr-big-label" data-share-big-label><?= $h((string) $s['big_label']) ?></p>
                        <?php endif; ?>
                    <?php endif; ?>
                    <?php if (($s['title'] ?? '') !== ''): ?>
                        <h2 class="yr-title" data-share-title><?= $h((string) $s['title']) ?></h2>
                    <?php endif; ?>
                    <?php if (($s['persona'] ?? '') !== ''): ?>
                        <p class="yr-persona" data-share-persona><?= $h((string) $s['persona']) ?></p>
                    <?php endif; ?>
                    <?php if (($s['lead'] ?? '') !== ''): ?>
                        <p class="yr-lead"><?= $h((string) $s['lead']) ?></p>
                    <?php endif; ?>
                    <?php if (($s['sub'] ?? '') !== ''): ?>
                        <p class="yr-sub" translate="no"><?= $h((string) $s['sub']) ?></p>
                    <?php endif; ?>
                    <?php if (!empty($s['partial'])): ?>
                        <p class="yr-note">Chiffres arrêtés à aujourd’hui : l’année n’est pas finie.</p>
                    <?php endif; ?>

                    <?php if (!empty($s['bars'])): ?>
                        <div class="yr-bars" aria-hidden="true" data-share-bars>
                            <?php foreach ($s['bars'] as $b): ?>
                                <span style="--h:<?= max(2, (int) $b['pct']) ?>%" data-pct="<?= (int) $b['pct'] ?>"><i></i><b><?= $h((string) $b['label']) ?></b></span>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($s['stats'])): ?>
                        <dl class="yr-stats<?= ($s['key'] ?? '') === 'summary' ? ' yr-stats--grid' : '' ?>">
                            <?php foreach ($s['stats'] as [$label, $value]): ?>
                                <div data-share-stat><dt><?= $h((string) $label) ?></dt><dd translate="no"><?= $h((string) $value) ?></dd></div>
                            <?php endforeach; ?>
                        </dl>
                    <?php endif; ?>

                    <?php if (!empty($s['ranks'])): ?>
                        <ul class="yr-ranks">
                            <?php foreach ($s['ranks'] as [$top, $label]): ?>
                                <li data-share-stat><strong data-v><?= $h((string) $top) ?></strong><span data-l><?= $h((string) $label) ?></span></li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>

                    <?php if (!empty($s['boards'])): ?>
                        <div class="yr-boards">
                            <?php foreach ($s['boards'] as $b): ?>
                                <div class="yr-board" data-share-board>
                                    <h3><?= $h((string) $b['label']) ?></h3>
                                    <ol>
                                        <?php foreach ($b['rows'] as $r): ?>
                                            <li><b><?= (int) $r['rank'] ?></b><span translate="no"><?= $h((string) $r['name']) ?></span><em translate="no"><?= $h((string) $r['value']) ?></em></li>
                                        <?php endforeach; ?>
                                    </ol>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($s['locked'])): ?>
                        <p class="yr-lock" aria-hidden="true">
                            <svg viewBox="0 0 24 24"><rect x="5" y="11" width="14" height="10" rx="2"/><path d="M8 11V8a4 4 0 0 1 8 0v3"/></svg>
                        </p>
                        <a class="yr-btn yr-btn--solid" href="<?= $h(url('platform/upgrade?from=year_review_collective')) ?>">Voir les formules</a>
                    <?php endif; ?>

                    <?php if (($s['key'] ?? '') === 'summary'): ?>
                        <div class="yr-end">
                            <button type="button" class="yr-btn" data-yr-restart>Revoir depuis le début</button>
                            <a class="yr-btn" href="<?= $h((string) $yrCloseUrl) ?>">Fermer</a>
                        </div>
                    <?php endif; ?>
                </div>
            </section>
        <?php endforeach; ?>
        <footer class="yr-dock">
            <button type="button" class="yr-btn yr-btn--solid" data-yr-share hidden>
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 15V3M7 8l5-5 5 5M5 13v6a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2v-6"/></svg>
                <span>Partager l’image</span>
            </button>
            <button type="button" class="yr-btn" data-yr-copy hidden>Copier pour Discord</button>
            <button type="button" class="yr-btn" data-yr-download hidden>Télécharger</button>
            <p class="yr-toast" role="status" aria-live="polite" data-yr-toast></p>
        </footer>
    </div>

    <button type="button" class="yr-arrow yr-arrow--prev" data-yr-prev aria-label="Écran précédent"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M15 5l-7 7 7 7"/></svg></button>
    <button type="button" class="yr-arrow yr-arrow--next" data-yr-next aria-label="Écran suivant"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M9 5l7 7-7 7"/></svg></button>


    <div hidden data-yr-i18n>
        <span data-k="copied">Image copiée : collez-la dans Discord (Ctrl+V).</span>
        <span data-k="downloaded">Image téléchargée : glissez-la dans Discord.</span>
        <span data-k="copy_fail">Copie impossible dans ce navigateur : l’image est téléchargée.</span>
        <span data-k="pause">Mettre en pause</span>
        <span data-k="play">Reprendre</span>
        <span data-k="file">retrospective</span>
    </div>
</div>
