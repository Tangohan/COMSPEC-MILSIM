<?php
declare(strict_types=1);
/** @var array<string, mixed>|null $deck */
/** @var string $base */
$deck = $deck ?? null;
$base = $base ?? '';
if (!$deck || empty($deck['slides']) || !is_array($deck['slides'])) {
    echo '<p class="text-slate-500">Aucune étape à afficher pour cette leçon.</p>';

    return;
}
$slides = $deck['slides'];
$modals = isset($deck['modals']) && is_array($deck['modals']) ? $deck['modals'] : [];
$slideCount = count($slides);
$initialSlidePct = $slideCount > 0 ? (int) round(100 / $slideCount) : 0;
?>
<div class="lms-canvas-player lms-deck-player" data-lms-canvas-player data-lms-canvas-slide-count="<?= (int) $slideCount ?>">
    <div class="hidden rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-900 shadow-sm" data-lms-canvas-toast role="alert" aria-live="polite"></div>

    <div class="lms-deck-player__bar">
        <p class="lms-deck-player__hint">
            Parcours interactif — <strong>flèches ← → du clavier</strong> ou boutons
        </p>
        <div class="lms-deck-player__controls">
            <button type="button" data-lms-prev class="lms-deck-btn" disabled>← Précédent</button>
            <button type="button" data-lms-next class="lms-deck-btn lms-deck-btn--go">Suivant →</button>
        </div>
    </div>

    <div class="lms-deck-player__meter-head">
        <span>Progression des étapes</span>
        <span class="lms-deck-player__meter-count" data-lms-canvas-slide-label>Étape 1 sur <?= (int) $slideCount ?></span>
    </div>
    <div class="lms-deck-player__meter" role="presentation">
        <div class="lms-deck-player__meter-fill" data-lms-canvas-slide-progress-bar style="width: <?= (int) $initialSlidePct ?>%"></div>
    </div>

    <div class="lms-deck-stage">
        <div class="swiper lms-canvas-swiper">
            <div class="swiper-wrapper">
            <?php foreach ($slides as $i => $sl): ?>
            <?php
            $sl = is_array($sl) ? $sl : [];
            $tpl = (string) ($sl['template'] ?? 'title_hero');
            $surface = trim((string) ($sl['surface'] ?? 'default'));
            $slideWrap = 'lms-canvas-slide flex min-h-0 flex-col';
            if ($surface === 'elevated') {
                $slideWrap .= ' lms-canvas-slide--elevated';
            }
            ?>
            <div class="swiper-slide">
                <div class="<?= htmlspecialchars($slideWrap) ?>" data-lms-slide data-index="<?= (int) $i ?>">
                <?php if (!empty($sl['contextKicker'])): ?>
                <p class="lms-canvas-context-kicker"><?= htmlspecialchars((string) $sl['contextKicker']) ?></p>
                <?php endif; ?>
                <?php
                require __DIR__ . '/canvas_lesson_slide_body.php';
                ?>
                </div>
            </div>
            <?php endforeach; ?>
            </div>
            <div class="swiper-pagination !hidden" aria-hidden="true"></div>
        </div>
    </div>

    <div class="lms-canvas-dots" data-lms-canvas-dots role="tablist" aria-label="Étapes du parcours">
        <?php for ($di = 0; $di < $slideCount; $di++): ?>
        <button type="button"
                class="<?= $di === 0 ? 'is-active' : '' ?>"
                data-lms-canvas-dot="<?= (int) $di ?>"
                aria-label="Aller à l’étape <?= (int) ($di + 1) ?>"
                aria-current="<?= $di === 0 ? 'true' : 'false' ?>"></button>
        <?php endfor; ?>
    </div>
</div>

<?php foreach ($modals as $m): ?>
<?php
if (!is_array($m) || empty($m['id'])) {
    continue;
}
$mid = preg_replace('/[^a-zA-Z0-9_-]/', '', (string) $m['id']);
?>
<div id="lms-modal-<?= htmlspecialchars($mid) ?>" data-lms-modal-panel class="hidden fixed inset-0 z-[150] flex items-center justify-center p-4 bg-slate-900/50" aria-hidden="true">
    <div class="bg-white rounded-2xl max-w-lg w-full max-h-[85vh] overflow-y-auto shadow-2xl p-6 relative">
        <button type="button" data-lms-modal-close class="absolute top-4 right-4 text-slate-400 hover:text-slate-700 text-xl leading-none" aria-label="Fermer">×</button>
        <h3 class="text-lg font-semibold text-slate-900 mb-3 pr-8"><?= htmlspecialchars((string) ($m['title'] ?? 'Détail')) ?></h3>
        <div class="prose prose-sm text-slate-700 max-w-none"><?= training_canvas_sanitize_html((string) ($m['body'] ?? '')) ?></div>
        <button type="button" data-lms-modal-close class="mt-6 w-full py-2 rounded-lg bg-slate-900 text-white text-sm font-bold">Fermer</button>
    </div>
</div>
<?php endforeach; ?>

<script src="<?= htmlspecialchars($base) ?>/assets/js/training_canvas_player.js" defer></script>
