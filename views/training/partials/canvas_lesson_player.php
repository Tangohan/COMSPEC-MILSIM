<?php
declare(strict_types=1);
/** @var array<string, mixed>|null $deck */
/** @var string $base */
$deck = $deck ?? null;
$base = $base ?? '';
if (!$deck || empty($deck['slides']) || !is_array($deck['slides'])) {
    echo '<p class="text-slate-500">Parcours canvas vide ou invalide.</p>';

    return;
}
$slides = $deck['slides'];
$modals = isset($deck['modals']) && is_array($deck['modals']) ? $deck['modals'] : [];
$slideCount = count($slides);
$closure = isset($deck['closure']) && is_array($deck['closure']) ? $deck['closure'] : null;
$initialSlidePct = $slideCount > 0 ? (int) round(100 / $slideCount) : 0;
?>
<div class="lms-canvas-player space-y-6" data-lms-canvas-player data-lms-canvas-slide-count="<?= (int) $slideCount ?>">
    <div class="hidden rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-900 shadow-sm" data-lms-canvas-toast role="alert" aria-live="polite"></div>
    <div class="rounded-2xl border border-slate-200 bg-white px-5 py-4 shadow-sm" data-lms-canvas-slide-progress-wrap>
        <div class="mb-3 flex flex-wrap items-center justify-between gap-4">
            <p class="text-[10px] font-black uppercase tracking-[0.28em] text-slate-400">Progression des étapes</p>
            <p class="text-sm font-bold text-slate-900" data-lms-canvas-slide-label>Étape 1 sur <?= (int) $slideCount ?></p>
        </div>
        <div class="h-2 w-full overflow-hidden rounded-full bg-slate-100">
            <div class="h-full rounded-full bg-emerald-500 transition-all duration-300 lms-canvas-slide-progress-inner" data-lms-canvas-slide-progress-bar style="width: <?= $initialSlidePct ?>%"></div>
        </div>
    </div>
    <div class="swiper lms-canvas-swiper">
        <div class="swiper-wrapper">
        <?php foreach ($slides as $i => $sl): ?>
        <?php
        $sl = is_array($sl) ? $sl : [];
        $tpl = (string) ($sl['template'] ?? 'title_hero');
        $surface = trim((string) ($sl['surface'] ?? 'default'));
        $slideWrap = 'lms-canvas-slide flex min-h-[420px] flex-col p-6 md:p-10';
        if ($surface === 'elevated') {
            $slideWrap .= ' lms-canvas-slide--elevated';
        }
        ?>
        <div class="swiper-slide">
            <div class="<?= htmlspecialchars($slideWrap) ?>" data-lms-slide data-index="<?= (int) $i ?>">
            <?php if (!empty($sl['contextKicker'])): ?>
            <p class="lms-canvas-context-kicker mb-4 text-[10px] font-black uppercase tracking-[0.35em] text-emerald-600"><?= htmlspecialchars((string) $sl['contextKicker']) ?></p>
            <?php endif; ?>
            <?php if ($tpl === 'scorm_sequence'): ?>
            <?php
            $raw = trim((string) ($sl['body'] ?? ''));
            $steps = $raw !== '' ? array_map('trim', preg_split('/[|→\n]+/', $raw) ?: []) : ['Brief', 'Slides', 'Knowledge check', 'Assessment', 'Certification'];
            $steps = array_values(array_filter($steps, static fn ($x) => $x !== ''));
            if ($steps === []) {
                $steps = ['Brief', 'Slides', 'Assessment'];
            }
            ?>
            <p class="text-[10px] font-black uppercase tracking-[0.35em] text-slate-400 mb-3">Déroulé type</p>
            <div class="lms-scorm-strip">
                <?php foreach ($steps as $k => $st): ?>
                <?php if ($k > 0): ?><span class="text-slate-300">→</span><?php endif; ?>
                <span><?= htmlspecialchars($st) ?></span>
                <?php endforeach; ?>
            </div>
            <?php if (!empty($sl['title'])): ?>
            <h2 class="text-xl font-black text-slate-900 mt-6"><?= htmlspecialchars((string) $sl['title']) ?></h2>
            <?php endif; ?>
            <?php elseif ($tpl === 'timeline'): ?>
            <?php
            $events = function_exists('training_canvas_parse_timeline_events') ? training_canvas_parse_timeline_events((string) ($sl['body'] ?? '')) : [];
            ?>
            <p class="text-[10px] font-black uppercase tracking-[0.35em] text-slate-400 mb-4">Frise chronologique</p>
            <?php if (!empty($sl['title'])): ?>
            <h2 class="text-xl font-black text-slate-900 mb-6"><?= htmlspecialchars((string) $sl['title']) ?></h2>
            <?php endif; ?>
            <?php if ($events === []): ?>
            <p class="text-sm text-slate-500">Ajoutez des étapes dans l’éditeur (JSON ou lignes <span class="font-mono text-xs">date | titre | texte</span>).</p>
            <?php else: ?>
            <ol class="space-y-8">
                <?php foreach ($events as $idx => $ev): ?>
                <li class="flex gap-4">
                    <div class="flex flex-col items-center shrink-0 pt-1" aria-hidden="true">
                        <span class="h-3.5 w-3.5 rounded-full bg-violet-600 ring-4 ring-violet-100"></span>
                        <?php if ($idx < count($events) - 1): ?>
                        <span class="w-0.5 flex-1 min-h-[1.25rem] bg-violet-200 mt-2"></span>
                        <?php endif; ?>
                    </div>
                    <div class="min-w-0 flex-1 pb-2">
                    <div class="flex flex-wrap items-baseline gap-x-3 gap-y-1 mb-1">
                        <?php if (($ev['time'] ?? '') !== ''): ?>
                        <span class="text-xs font-black uppercase tracking-wider text-violet-700"><?= htmlspecialchars((string) $ev['time']) ?></span>
                        <?php endif; ?>
                        <?php if (($ev['title'] ?? '') !== ''): ?>
                        <span class="text-base font-black text-slate-900"><?= htmlspecialchars((string) $ev['title']) ?></span>
                        <?php endif; ?>
                    </div>
                    <?php if (($ev['html'] ?? '') !== ''): ?>
                    <div class="prose prose-slate prose-sm max-w-none text-slate-700"><?= $ev['html'] ?></div>
                    <?php endif; ?>
                    </div>
                </li>
                <?php endforeach; ?>
            </ol>
            <?php endif; ?>
            <?php elseif ($tpl === 'reading_article'): ?>
            <div class="max-w-3xl mx-auto">
                <p class="text-[10px] font-black uppercase tracking-[0.35em] text-emerald-700/90 mb-3">Article de lecture</p>
                <?php if (!empty($sl['title'])): ?>
                <h2 class="text-2xl md:text-3xl font-black text-slate-900 tracking-tight leading-tight mb-3"><?= htmlspecialchars((string) $sl['title']) ?></h2>
                <?php endif; ?>
                <?php if (!empty($sl['subtitle'])): ?>
                <p class="text-lg text-slate-600 font-medium mb-8 border-b border-slate-200 pb-6"><?= htmlspecialchars((string) $sl['subtitle']) ?></p>
                <?php endif; ?>
                <?php if (!empty($sl['body'])): ?>
                <div class="lms-reading-article prose prose-slate prose-lg max-w-none text-slate-800 leading-relaxed">
                    <?= training_canvas_sanitize_html((string) $sl['body']) ?>
                </div>
                <?php endif; ?>
            </div>
            <?php elseif ($tpl === 'fill_blanks'): ?>
            <p class="text-[10px] font-black uppercase tracking-[0.35em] text-amber-700/90 mb-3">Texte à trous</p>
            <?php if (!empty($sl['title'])): ?>
            <h2 class="text-lg font-black text-slate-900 mb-4"><?= htmlspecialchars((string) $sl['title']) ?></h2>
            <?php endif; ?>
            <?php
            $fbBody = (string) ($sl['body'] ?? '');
            $fbSan = training_canvas_sanitize_html($fbBody);
            $fbHtml = function_exists('training_canvas_fill_blanks_html') ? training_canvas_fill_blanks_html($fbSan) : $fbSan;
            ?>
            <div class="prose prose-slate max-w-none text-slate-800 text-base leading-relaxed lms-fill-blanks-host" data-lms-fill-blanks-slide>
                <?= $fbHtml ?>
            </div>
            <p class="text-xs text-slate-500 mt-4">Renseignez chaque champ ; le bouton « Suivant » vérifie vos réponses avant de continuer.</p>
            <?php elseif ($tpl === 'resources_list'): ?>
            <?php
            $resList = function_exists('training_canvas_slide_resources') ? training_canvas_slide_resources($sl) : [];
            ?>
            <p class="text-[10px] font-black uppercase tracking-[0.35em] text-slate-400 mb-3">Ressources</p>
            <?php if (!empty($sl['title'])): ?>
            <h2 class="text-xl font-black text-slate-900 mb-2"><?= htmlspecialchars((string) $sl['title']) ?></h2>
            <?php endif; ?>
            <?php if (!empty($sl['subtitle'])): ?>
            <p class="text-sm text-violet-700 font-semibold mb-4"><?= htmlspecialchars((string) $sl['subtitle']) ?></p>
            <?php endif; ?>
            <?php if (!empty($sl['body'])): ?>
            <div class="prose prose-slate prose-sm max-w-none text-slate-600 mb-6"><?= training_canvas_sanitize_html((string) $sl['body']) ?></div>
            <?php endif; ?>
            <?php if ($resList === []): ?>
            <p class="text-sm text-slate-500">Ajoutez des liens dans l’éditeur (liste de ressources).</p>
            <?php else: ?>
            <ul class="lms-resources-plain space-y-2 text-base text-slate-800 max-w-2xl">
                <?php foreach ($resList as $r): ?>
                <li class="pl-1">
                    <a href="<?= htmlspecialchars((string) $r['url']) ?>" target="_blank" rel="noopener noreferrer" class="font-semibold text-violet-800 underline decoration-violet-300 underline-offset-2 hover:text-violet-950 hover:decoration-violet-600"><?= htmlspecialchars((string) $r['title']) ?></a>
                    <span class="text-slate-500 text-sm"> — ouvrir dans un nouvel onglet</span>
                </li>
                <?php endforeach; ?>
            </ul>
            <?php endif; ?>
            <?php elseif ($tpl === 'knowledge_check'): ?>
            <?php
            $lines = preg_split('/\r\n|\r|\n/', (string) ($sl['body'] ?? '')) ?: [];
            $lines = array_values(array_filter(array_map('trim', $lines), static fn ($x) => $x !== ''));
            ?>
            <p class="text-[10px] font-black uppercase tracking-[0.35em] text-slate-400 mb-3">Repères</p>
            <?php if (!empty($sl['title'])): ?>
            <h2 class="text-lg font-black text-slate-900 mb-4"><?= htmlspecialchars((string) $sl['title']) ?></h2>
            <?php endif; ?>
            <?php if ($lines === []): ?>
            <p class="text-sm text-slate-500">Ajoutez des lignes dans le corps de la slide (éditeur).</p>
            <?php else: ?>
            <ul class="lms-knowledge-list list-disc pl-6 space-y-2.5 text-base md:text-lg text-slate-800 leading-relaxed max-w-3xl">
                <?php foreach ($lines as $line): ?>
                <li><?= htmlspecialchars($line) ?></li>
                <?php endforeach; ?>
            </ul>
            <?php endif; ?>
            <?php elseif ($tpl === 'quote'): ?>
            <blockquote class="text-xl md:text-2xl font-serif italic text-slate-800 border-l-4 border-violet-500 pl-6">
                <?= training_canvas_sanitize_html((string) ($sl['body'] ?? '')) ?>
            </blockquote>
            <?php elseif ($tpl === 'image_full' && !empty($sl['imageUrl'])): ?>
            <figure class="space-y-3">
                <img src="<?= htmlspecialchars((string) $sl['imageUrl']) ?>" alt="" class="w-full rounded-xl object-cover max-h-[420px] bg-slate-100" loading="lazy">
                <?php if (!empty($sl['imageCaption'])): ?>
                <figcaption class="text-sm text-slate-500 text-center"><?= htmlspecialchars((string) $sl['imageCaption']) ?></figcaption>
                <?php endif; ?>
            </figure>
            <?php elseif ($tpl === 'split_text_image' && !empty($sl['imageUrl'])): ?>
            <div class="grid md:grid-cols-2 gap-8 items-start">
                <div class="prose prose-slate max-w-none text-slate-700">
                    <?php if (!empty($sl['title'])): ?>
                    <h2 class="text-2xl font-black text-slate-900 mb-2"><?= htmlspecialchars((string) $sl['title']) ?></h2>
                    <?php endif; ?>
                    <?= training_canvas_sanitize_html((string) ($sl['body'] ?? '')) ?>
                </div>
                <figure>
                    <img src="<?= htmlspecialchars((string) $sl['imageUrl']) ?>" alt="" class="w-full rounded-xl object-cover bg-slate-100" loading="lazy">
                    <?php if (!empty($sl['imageCaption'])): ?>
                    <figcaption class="text-xs text-slate-500 mt-2"><?= htmlspecialchars((string) $sl['imageCaption']) ?></figcaption>
                    <?php endif; ?>
                </figure>
            </div>
            <?php else: ?>
            <div class="space-y-4">
                <?php if (!empty($sl['title'])): ?>
                <h2 class="text-2xl md:text-3xl font-black text-slate-900 tracking-tight"><?= htmlspecialchars((string) $sl['title']) ?></h2>
                <?php endif; ?>
                <?php if (!empty($sl['subtitle'])): ?>
                <p class="text-lg text-violet-700 font-semibold"><?= htmlspecialchars((string) $sl['subtitle']) ?></p>
                <?php endif; ?>
                <?php if (!empty($sl['body'])): ?>
                <div class="prose prose-slate max-w-none text-slate-700">
                    <?= training_canvas_sanitize_html((string) $sl['body']) ?>
                </div>
                <?php endif; ?>
                <?php if (!empty($sl['imageUrl']) && $tpl !== 'split_text_image'): ?>
                <figure class="mt-4">
                    <img src="<?= htmlspecialchars((string) $sl['imageUrl']) ?>" alt="" class="w-full max-h-80 rounded-xl object-cover bg-slate-100" loading="lazy">
                    <?php if (!empty($sl['imageCaption'])): ?>
                    <figcaption class="text-xs text-slate-500 mt-2"><?= htmlspecialchars((string) $sl['imageCaption']) ?></figcaption>
                    <?php endif; ?>
                </figure>
                <?php endif; ?>
                <?php if (!empty($sl['fileUrl'])): ?>
                <div class="mt-6 p-4 rounded-xl bg-slate-100 border border-slate-200 flex flex-wrap items-center justify-between gap-3">
                    <span class="text-sm font-semibold text-slate-800"><?= htmlspecialchars((string) ($sl['fileLabel'] ?: 'Fichier')) ?></span>
                    <a href="<?= htmlspecialchars((string) $sl['fileUrl']) ?>" target="_blank" rel="noopener" class="px-4 py-2 rounded-lg bg-slate-900 text-white text-sm font-bold hover:bg-slate-800">Télécharger / ouvrir</a>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <?php require base_path('views/training/partials/canvas_slide_dense_bottom.php'); ?>

            <div class="mt-auto mt-8 flex flex-wrap gap-3 pt-4">
                <?php
                foreach (['primaryAction' => 'primary', 'secondaryAction' => 'secondary'] as $ak => $cls) {
                    $act = $sl[$ak] ?? null;
                    if (!is_array($act) || empty($act['label'])) {
                        continue;
                    }
                    $label = (string) $act['label'];
                    $type = (string) ($act['type'] ?? 'link');
                    if ($type === 'modal' && !empty($act['modalId'])) {
                        $mid = preg_replace('/[^a-zA-Z0-9_-]/', '', (string) $act['modalId']);
                        echo '<button type="button" class="px-5 py-2.5 rounded-xl text-sm font-bold ' . ($cls === 'primary' ? 'bg-violet-600 text-white hover:bg-violet-700' : 'border border-slate-300 text-slate-800 hover:bg-slate-50') . '" data-lms-open-modal="' . htmlspecialchars($mid) . '">' . htmlspecialchars($label) . '</button>';
                    } else {
                        $u = (string) ($act['url'] ?? '');
                        if ($u !== '') {
                            echo '<a href="' . htmlspecialchars($u) . '" target="_blank" rel="noopener" class="px-5 py-2.5 rounded-xl text-sm font-bold ' . ($cls === 'primary' ? 'bg-violet-600 text-white hover:bg-violet-700' : 'border border-slate-300 text-slate-800 hover:bg-slate-50') . '">' . htmlspecialchars($label) . '</a>';
                        }
                    }
                }
                ?>
            </div>
            </div>
        </div>
        <?php endforeach; ?>
        </div>
        <div class="swiper-pagination !relative !mt-4"></div>
    </div>

    <?php if ($closure !== null): ?>
    <div class="rounded-[1.5rem] border border-slate-200 bg-gradient-to-br from-slate-50 to-white p-6 shadow-sm md:p-8" data-lms-canvas-closure>
        <?php if (!empty($closure['title'])): ?>
        <h3 class="text-lg font-black uppercase tracking-tight text-slate-900 md:text-xl"><?= htmlspecialchars((string) $closure['title']) ?></h3>
        <?php endif; ?>
        <div class="mt-6 grid gap-6 md:grid-cols-2">
            <?php
            $seen = $closure['seen'] ?? [];
            if (is_array($seen) && $seen !== []):
                ?>
            <div>
                <p class="mb-2 text-[10px] font-black uppercase tracking-[0.24em] text-slate-400">Ce que vous avez parcouru</p>
                <ul class="list-inside list-disc space-y-1.5 text-sm text-slate-700">
                    <?php foreach ($seen as $line):
                        if (!is_string($line) || trim($line) === '') {
                            continue;
                        }
                        ?>
                    <li><?= htmlspecialchars($line) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php
            endif;
            $acquired = $closure['acquired'] ?? [];
            if (is_array($acquired) && $acquired !== []):
                ?>
            <div>
                <p class="mb-2 text-[10px] font-black uppercase tracking-[0.24em] text-emerald-700">Ce que vous pouvez en retirer</p>
                <ul class="list-inside list-disc space-y-1.5 text-sm text-slate-800">
                    <?php foreach ($acquired as $line):
                        if (!is_string($line) || trim($line) === '') {
                            continue;
                        }
                        ?>
                    <li><?= htmlspecialchars($line) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endif; ?>
        </div>
        <?php if (!empty($closure['nextHint'])): ?>
        <p class="mt-6 rounded-xl border border-slate-200 bg-white p-4 text-sm leading-relaxed text-slate-700"><?= nl2br(htmlspecialchars((string) $closure['nextHint'])) ?></p>
        <?php endif; ?>
        <p class="mt-4 text-xs text-slate-500">Utilisez les boutons <strong>Précédent</strong> / <strong>Suivant</strong> en bas du parcours, puis la navigation sous la leçon pour poursuivre la formation.</p>
    </div>
    <?php endif; ?>

    <div class="flex flex-wrap items-center justify-between gap-4">
        <div class="flex gap-2">
            <button type="button" data-lms-prev class="px-4 py-2 rounded-lg border border-slate-300 text-sm font-bold text-slate-800 hover:bg-slate-50 disabled:opacity-40">← Précédent</button>
            <button type="button" data-lms-next class="px-4 py-2 rounded-lg border border-slate-300 text-sm font-bold text-slate-800 hover:bg-slate-50 disabled:opacity-40">Suivant →</button>
        </div>
        <div class="flex gap-1.5 lms-canvas-dots" aria-hidden="true"></div>
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
        <h3 class="text-lg font-black text-slate-900 mb-3 pr-8"><?= htmlspecialchars((string) ($m['title'] ?? 'Modale')) ?></h3>
        <div class="prose prose-sm text-slate-700 max-w-none"><?= training_canvas_sanitize_html((string) ($m['body'] ?? '')) ?></div>
        <button type="button" data-lms-modal-close class="mt-6 w-full py-2 rounded-lg bg-slate-900 text-white text-sm font-bold">Fermer</button>
    </div>
</div>
<?php endforeach; ?>

<script src="<?= htmlspecialchars($base) ?>/assets/js/training_canvas_player.js" defer></script>
