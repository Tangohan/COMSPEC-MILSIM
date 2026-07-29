<?php
declare(strict_types=1);
/**
 * Corps d’une diapositive canvas (templates).
 * @var array<string, mixed> $sl
 * @var string $tpl
 */
$sl = is_array($sl ?? null) ? $sl : [];
$tpl = (string) ($tpl ?? ($sl['template'] ?? 'title_hero'));

if ($tpl === 'scorm_sequence') {
    $raw = trim((string) ($sl['body'] ?? ''));
    $steps = $raw !== '' ? array_map('trim', preg_split('/[|→\n]+/', $raw) ?: []) : ['Brief', 'Slides', 'Knowledge check', 'Assessment', 'Certification'];
    $steps = array_values(array_filter($steps, static fn ($x) => $x !== ''));
    if ($steps === []) {
        $steps = ['Brief', 'Slides', 'Assessment'];
    }
    ?>
    <p class="lms-canvas-template-label mb-3">Déroulé type</p>
    <div class="lms-scorm-strip">
        <?php foreach ($steps as $k => $st): ?>
        <?php if ($k > 0): ?><span class="text-slate-300">→</span><?php endif; ?>
        <span><?= htmlspecialchars($st) ?></span>
        <?php endforeach; ?>
    </div>
    <?php if (!empty($sl['title'])): ?>
    <h2 class="text-xl font-semibold text-slate-900 mt-6"><?= htmlspecialchars((string) $sl['title']) ?></h2>
    <?php endif; ?>
    <?php
} elseif ($tpl === 'timeline') {
    $events = function_exists('training_canvas_parse_timeline_events') ? training_canvas_parse_timeline_events((string) ($sl['body'] ?? '')) : [];
    ?>
    <p class="lms-canvas-template-label mb-4">Frise chronologique</p>
    <?php if (!empty($sl['title'])): ?>
    <h2 class="text-xl font-semibold text-slate-900 mb-6"><?= htmlspecialchars((string) $sl['title']) ?></h2>
    <?php endif; ?>
    <?php if ($events === []): ?>
    <p class="text-sm text-slate-500">Aucune étape à afficher pour cette frise.</p>
    <?php else: ?>
    <ol class="space-y-8">
        <?php foreach ($events as $idx => $ev): ?>
        <li class="flex gap-4">
            <div class="flex flex-col items-center shrink-0 pt-1" aria-hidden="true">
                <span class="h-3.5 w-3.5 rounded-full bg-emerald-600 ring-4 ring-emerald-100"></span>
                <?php if ($idx < count($events) - 1): ?>
                <span class="w-0.5 flex-1 min-h-[1.25rem] bg-emerald-200 mt-2"></span>
                <?php endif; ?>
            </div>
            <div class="min-w-0 flex-1 pb-2">
            <div class="flex flex-wrap items-baseline gap-x-3 gap-y-1 mb-1">
                <?php if (($ev['time'] ?? '') !== ''): ?>
                <span class="text-xs font-semibold text-teal-700"><?= htmlspecialchars((string) $ev['time']) ?></span>
                <?php endif; ?>
                <?php if (($ev['title'] ?? '') !== ''): ?>
                <span class="text-base font-semibold text-slate-900"><?= htmlspecialchars((string) $ev['title']) ?></span>
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
    <?php
} elseif ($tpl === 'reading_article') {
    ?>
    <div class="lms-reading-article-wrap">
        <p class="lms-canvas-template-label mb-3">Article de lecture</p>
        <?php if (!empty($sl['title'])): ?>
        <h2 class="text-2xl md:text-3xl font-semibold text-slate-900 tracking-tight leading-tight mb-3"><?= htmlspecialchars((string) $sl['title']) ?></h2>
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
    <?php
} elseif ($tpl === 'fill_blanks') {
    ?>
    <p class="lms-canvas-template-label mb-3">Texte à trous</p>
    <?php if (!empty($sl['title'])): ?>
    <h2 class="text-lg font-semibold text-slate-900 mb-4"><?= htmlspecialchars((string) $sl['title']) ?></h2>
    <?php endif; ?>
    <?php
    $fbBody = (string) ($sl['body'] ?? '');
    $fbSan = training_canvas_sanitize_html($fbBody);
    $fbHtml = function_exists('training_canvas_fill_blanks_html') ? training_canvas_fill_blanks_html($fbSan) : $fbSan;
    ?>
    <div class="prose prose-slate max-w-none text-slate-800 text-base leading-relaxed lms-fill-blanks-host" data-lms-fill-blanks-slide>
        <?= $fbHtml ?>
    </div>
    <p class="text-xs text-slate-500 mt-4">Chaque réponse correcte se verrouille tout de suite. Quand tous les champs sont validés, utilisez « Suivant » pour continuer.</p>
    <?php
} elseif ($tpl === 'resources_list') {
    $resList = function_exists('training_canvas_slide_resources') ? training_canvas_slide_resources($sl) : [];
    ?>
    <p class="lms-canvas-template-label mb-3">Ressources</p>
    <?php if (!empty($sl['title'])): ?>
    <h2 class="text-xl font-semibold text-slate-900 mb-2"><?= htmlspecialchars((string) $sl['title']) ?></h2>
    <?php endif; ?>
    <?php if (!empty($sl['subtitle'])): ?>
    <p class="text-sm text-teal-800 font-semibold mb-4"><?= htmlspecialchars((string) $sl['subtitle']) ?></p>
    <?php endif; ?>
    <?php if (!empty($sl['body'])): ?>
    <div class="prose prose-slate prose-sm max-w-none text-slate-600 mb-6"><?= training_canvas_sanitize_html((string) $sl['body']) ?></div>
    <?php endif; ?>
    <?php if ($resList === []): ?>
    <p class="text-sm text-slate-500">Aucune ressource associée à cette étape.</p>
    <?php else: ?>
    <ul class="lms-resources-plain space-y-2 text-base text-slate-800">
        <?php foreach ($resList as $r): ?>
        <li class="pl-1">
            <a href="<?= htmlspecialchars((string) $r['url']) ?>" target="_blank" rel="noopener noreferrer" class="font-semibold text-teal-800 underline decoration-emerald-300 underline-offset-2 hover:text-teal-950"><?= htmlspecialchars((string) $r['title']) ?></a>
            <span class="text-slate-500 text-sm"> — ouvrir dans un nouvel onglet</span>
        </li>
        <?php endforeach; ?>
    </ul>
    <?php endif; ?>
    <?php
} elseif ($tpl === 'knowledge_check') {
    $lines = preg_split('/\r\n|\r|\n/', (string) ($sl['body'] ?? '')) ?: [];
    $lines = array_values(array_filter(array_map('trim', $lines), static fn ($x) => $x !== ''));
    ?>
    <p class="lms-canvas-template-label mb-3">Repères</p>
    <?php if (!empty($sl['title'])): ?>
    <h2 class="text-lg font-semibold text-slate-900 mb-4"><?= htmlspecialchars((string) $sl['title']) ?></h2>
    <?php endif; ?>
    <?php if ($lines === []): ?>
    <p class="text-sm text-slate-500">Aucun repère pour cette étape.</p>
    <?php else: ?>
    <ul class="lms-knowledge-list list-disc pl-6 space-y-2.5 text-base md:text-lg text-slate-800 leading-relaxed">
        <?php foreach ($lines as $line): ?>
        <li><?= htmlspecialchars($line) ?></li>
        <?php endforeach; ?>
    </ul>
    <?php endif; ?>
    <?php
} elseif ($tpl === 'quote') {
    ?>
    <blockquote class="text-xl md:text-2xl font-serif italic text-slate-800 border-l-4 border-emerald-500 pl-6">
        <?= training_canvas_sanitize_html((string) ($sl['body'] ?? '')) ?>
    </blockquote>
    <?php
} elseif ($tpl === 'image_full' && !empty($sl['imageUrl'])) {
    ?>
    <figure class="space-y-3">
        <img src="<?= htmlspecialchars((string) $sl['imageUrl']) ?>" alt="" class="w-full rounded-xl object-cover max-h-[420px] bg-slate-100" loading="lazy">
        <?php if (!empty($sl['imageCaption'])): ?>
        <figcaption class="text-sm text-slate-500 text-center"><?= htmlspecialchars((string) $sl['imageCaption']) ?></figcaption>
        <?php endif; ?>
    </figure>
    <?php
} elseif ($tpl === 'split_text_image' && !empty($sl['imageUrl'])) {
    ?>
    <div class="grid md:grid-cols-2 gap-8 items-start">
        <div class="prose prose-slate max-w-none text-slate-700">
            <?php if (!empty($sl['title'])): ?>
            <h2 class="text-2xl font-semibold text-slate-900 mb-2"><?= htmlspecialchars((string) $sl['title']) ?></h2>
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
    <?php
} elseif ($tpl === 'scenario_decision') {
    $opts = isset($sl['options']) && is_array($sl['options']) ? $sl['options'] : [];
    $correctId = trim((string) ($sl['correctOptionId'] ?? ''));
    $sid = 'sd-' . substr(hash('sha256', (string) ($sl['title'] ?? '') . (string) ($sl['context'] ?? '')), 0, 10);
    ?>
    <p class="lms-canvas-template-label mb-2">Mise en situation</p>
    <?php if (!empty($sl['title'])): ?>
    <h2 class="text-xl font-semibold text-slate-900 mb-3"><?= htmlspecialchars((string) $sl['title']) ?></h2>
    <?php endif; ?>
    <?php if (!empty($sl['context'])): ?>
    <p class="text-sm text-slate-600 mb-3"><strong>Contexte</strong> — <?= htmlspecialchars((string) $sl['context']) ?></p>
    <?php endif; ?>
    <?php if (!empty($sl['situation'])): ?>
    <div class="prose prose-slate prose-sm max-w-none text-slate-700 mb-4"><?= training_canvas_sanitize_html((string) $sl['situation']) ?></div>
    <?php endif; ?>
    <div class="flex flex-col gap-2" data-lms-scenario="<?= htmlspecialchars($sid) ?>" data-correct="<?= htmlspecialchars($correctId) ?>">
        <?php foreach ($opts as $opt): ?>
        <?php if (!is_array($opt)) {
            continue;
        }
        $oid = trim((string) ($opt['id'] ?? ''));
        $otext = trim((string) ($opt['text'] ?? ''));
        if ($oid === '' || $otext === '') {
            continue;
        }
        ?>
        <label class="flex items-start gap-3 border border-slate-200 rounded-lg px-3.5 py-2.5 text-sm text-slate-700 cursor-pointer hover:border-emerald-500 hover:bg-emerald-50/60 transition-colors">
            <input type="radio" name="<?= htmlspecialchars($sid) ?>" value="<?= htmlspecialchars($oid) ?>" class="mt-0.5 accent-emerald-600" data-lms-scenario-option>
            <span><?= htmlspecialchars($otext) ?></span>
        </label>
        <?php endforeach; ?>
    </div>
    <?php if (!empty($sl['explanation'])): ?>
    <div class="mt-4 hidden rounded-lg border border-emerald-200 bg-emerald-50 px-3.5 py-2.5 text-sm text-emerald-900" data-lms-scenario-explain>
        <?= training_canvas_sanitize_html((string) $sl['explanation']) ?>
    </div>
    <?php endif; ?>
    <?php
} elseif ($tpl === 'dos_donts') {
    $dos = isset($sl['dos']) && is_array($sl['dos']) ? $sl['dos'] : [];
    $donts = isset($sl['donts']) && is_array($sl['donts']) ? $sl['donts'] : [];
    ?>
    <p class="lms-canvas-template-label mb-2">À faire / à ne pas faire</p>
    <?php if (!empty($sl['title'])): ?>
    <h2 class="text-lg font-semibold text-slate-900 mb-3"><?= htmlspecialchars((string) $sl['title']) ?></h2>
    <?php endif; ?>
    <div class="grid md:grid-cols-2 gap-4">
        <div class="rounded-xl border border-emerald-200 bg-emerald-50/60 p-4">
            <p class="text-xs font-bold text-emerald-800 mb-2">À faire</p>
            <ul class="list-disc pl-5 space-y-1.5 text-sm text-emerald-950">
                <?php foreach ($dos as $d): ?>
                <?php if (trim((string) $d) === '') {
                    continue;
                } ?>
                <li><?= htmlspecialchars((string) $d) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
        <div class="rounded-xl border border-rose-200 bg-rose-50/60 p-4">
            <p class="text-xs font-bold text-rose-800 mb-2">À ne pas faire</p>
            <ul class="list-disc pl-5 space-y-1.5 text-sm text-rose-950">
                <?php foreach ($donts as $d): ?>
                <?php if (trim((string) $d) === '') {
                    continue;
                } ?>
                <li><?= htmlspecialchars((string) $d) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>
    <?php if (!empty($sl['synthesis'])): ?>
    <div class="mt-4 prose prose-sm max-w-none text-slate-700"><?= training_canvas_sanitize_html((string) $sl['synthesis']) ?></div>
    <?php endif; ?>
    <?php
} elseif ($tpl === 'process_steps') {
    $steps = isset($sl['steps']) && is_array($sl['steps']) ? $sl['steps'] : [];
    ?>
    <p class="lms-canvas-template-label mb-2">Procédure</p>
    <?php if (!empty($sl['title'])): ?>
    <h2 class="text-lg font-semibold text-slate-900 mb-3"><?= htmlspecialchars((string) $sl['title']) ?></h2>
    <?php endif; ?>
    <?php if ($steps === []): ?>
    <p class="text-sm text-slate-500">Aucune étape définie.</p>
    <?php else: ?>
    <ol class="space-y-3">
        <?php foreach ($steps as $i => $st): ?>
        <?php if (!is_array($st)) {
            continue;
        } ?>
        <li class="rounded-xl border border-slate-200 bg-white p-4">
            <p class="text-xs font-bold text-emerald-700">Étape <?= (int) ($i + 1) ?></p>
            <?php if (!empty($st['title'])): ?>
            <p class="font-semibold text-slate-900 mt-1"><?= htmlspecialchars((string) $st['title']) ?></p>
            <?php endif; ?>
            <?php if (!empty($st['action'])): ?>
            <p class="text-sm text-slate-700 mt-1"><?= htmlspecialchars((string) $st['action']) ?></p>
            <?php endif; ?>
            <?php if (!empty($st['vigilance'])): ?>
            <p class="text-xs text-amber-800 mt-2"><strong>Vigilance</strong> — <?= htmlspecialchars((string) $st['vigilance']) ?></p>
            <?php endif; ?>
        </li>
        <?php endforeach; ?>
    </ol>
    <?php endif; ?>
    <?php
} elseif ($tpl === 'role_scope_compare') {
    ?>
    <p class="lms-canvas-template-label mb-2">Membre / encadrement</p>
    <?php if (!empty($sl['title'])): ?>
    <h2 class="text-lg font-semibold text-slate-900 mb-3"><?= htmlspecialchars((string) $sl['title']) ?></h2>
    <?php endif; ?>
    <div class="grid md:grid-cols-2 gap-3">
        <div class="rounded-xl border border-slate-200 p-4 bg-slate-50">
            <p class="text-xs font-bold text-slate-500 mb-2">Ce que voit un membre</p>
            <div class="prose prose-sm max-w-none text-slate-700"><?= training_canvas_sanitize_html((string) ($sl['memberView'] ?? '')) ?></div>
        </div>
        <div class="rounded-xl border border-slate-200 p-4 bg-slate-50">
            <p class="text-xs font-bold text-slate-500 mb-2">Ce que voit l’encadrement</p>
            <div class="prose prose-sm max-w-none text-slate-700"><?= training_canvas_sanitize_html((string) ($sl['staffView'] ?? '')) ?></div>
        </div>
        <div class="rounded-xl border border-emerald-200 p-4 bg-emerald-50/50">
            <p class="text-xs font-bold text-emerald-800 mb-2">Droits et rôle</p>
            <div class="prose prose-sm max-w-none text-slate-700"><?= training_canvas_sanitize_html((string) ($sl['rightsNote'] ?? '')) ?></div>
        </div>
        <div class="rounded-xl border border-teal-200 p-4 bg-teal-50/40">
            <p class="text-xs font-bold text-teal-800 mb-2">Pas une anomalie</p>
            <div class="prose prose-sm max-w-none text-slate-700"><?= training_canvas_sanitize_html((string) ($sl['notAnomaly'] ?? '')) ?></div>
        </div>
    </div>
    <?php
} elseif ($tpl === 'common_mistakes') {
    $mistakes = isset($sl['mistakes']) && is_array($sl['mistakes']) ? $sl['mistakes'] : [];
    ?>
    <p class="lms-canvas-template-label mb-2">Erreurs fréquentes</p>
    <?php if (!empty($sl['title'])): ?>
    <h2 class="text-lg font-semibold text-slate-900 mb-3"><?= htmlspecialchars((string) $sl['title']) ?></h2>
    <?php endif; ?>
    <?php if ($mistakes === []): ?>
    <p class="text-sm text-slate-500">Aucune fiche d’erreur pour cette étape.</p>
    <?php else: ?>
    <div class="space-y-3">
        <?php foreach ($mistakes as $m): ?>
        <?php if (!is_array($m)) {
            continue;
        } ?>
        <div class="rounded-xl border border-rose-200 bg-rose-50/40 p-4">
            <?php if (!empty($m['error'])): ?>
            <p class="font-semibold text-rose-900"><?= htmlspecialchars((string) $m['error']) ?></p>
            <?php endif; ?>
            <?php if (!empty($m['why'])): ?>
            <p class="text-sm text-slate-700 mt-1"><strong>Pourquoi</strong> — <?= htmlspecialchars((string) $m['why']) ?></p>
            <?php endif; ?>
            <?php if (!empty($m['consequence'])): ?>
            <p class="text-sm text-slate-700 mt-1"><strong>Conséquence</strong> — <?= htmlspecialchars((string) $m['consequence']) ?></p>
            <?php endif; ?>
            <?php if (!empty($m['correction'])): ?>
            <p class="text-sm text-emerald-900 mt-1"><strong>Correction</strong> — <?= htmlspecialchars((string) $m['correction']) ?></p>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
    <?php
} elseif ($tpl === 'case_review') {
    ?>
    <p class="lms-canvas-template-label mb-2">Analyse de cas</p>
    <?php if (!empty($sl['title'])): ?>
    <h2 class="text-lg font-semibold text-slate-900 mb-3"><?= htmlspecialchars((string) $sl['title']) ?></h2>
    <?php endif; ?>
    <div class="space-y-4">
        <?php if (!empty($sl['caseText'])): ?>
        <div><p class="text-xs font-bold text-slate-500 mb-1">Cas</p><div class="prose prose-sm max-w-none text-slate-700"><?= training_canvas_sanitize_html((string) $sl['caseText']) ?></div></div>
        <?php endif; ?>
        <?php if (!empty($sl['analysis'])): ?>
        <div><p class="text-xs font-bold text-slate-500 mb-1">Analyse</p><div class="prose prose-sm max-w-none text-slate-700"><?= training_canvas_sanitize_html((string) $sl['analysis']) ?></div></div>
        <?php endif; ?>
        <?php if (!empty($sl['goodConduct'])): ?>
        <div><p class="text-xs font-bold text-emerald-800 mb-1">Bonne conduite</p><div class="prose prose-sm max-w-none text-slate-700"><?= training_canvas_sanitize_html((string) $sl['goodConduct']) ?></div></div>
        <?php endif; ?>
        <?php if (!empty($sl['conclusion'])): ?>
        <div><p class="text-xs font-bold text-slate-500 mb-1">Conclusion</p><div class="prose prose-sm max-w-none text-slate-700"><?= training_canvas_sanitize_html((string) $sl['conclusion']) ?></div></div>
        <?php endif; ?>
    </div>
    <?php
} else {
    /* title_hero et gabarits génériques */
    ?>
    <?php if (!empty($sl['title'])): ?>
    <h2 class="text-2xl md:text-3xl font-semibold text-slate-900 tracking-tight"><?= htmlspecialchars((string) $sl['title']) ?></h2>
    <?php endif; ?>
    <?php if (!empty($sl['subtitle'])): ?>
    <p class="text-lg text-teal-800 font-semibold mt-2"><?= htmlspecialchars((string) $sl['subtitle']) ?></p>
    <?php endif; ?>
    <?php if (!empty($sl['body'])): ?>
    <div class="prose prose-slate max-w-none text-slate-700 mt-4"><?= training_canvas_sanitize_html((string) $sl['body']) ?></div>
    <?php endif; ?>
    <?php if (!empty($sl['imageUrl'])): ?>
    <figure class="mt-4">
        <img src="<?= htmlspecialchars((string) $sl['imageUrl']) ?>" alt="" class="w-full max-h-72 rounded-xl object-cover bg-slate-100" loading="lazy">
        <?php if (!empty($sl['imageCaption'])): ?>
        <figcaption class="text-xs text-slate-500 mt-2"><?= htmlspecialchars((string) $sl['imageCaption']) ?></figcaption>
        <?php endif; ?>
    </figure>
    <?php endif; ?>
    <?php if (!empty($sl['fileUrl'])): ?>
    <div class="mt-4 p-4 rounded-xl bg-slate-100 border border-slate-200 flex flex-wrap justify-between gap-3 items-center">
        <span class="text-sm font-semibold text-slate-800"><?= htmlspecialchars((string) ($sl['fileLabel'] ?? 'Fichier')) ?></span>
        <a href="<?= htmlspecialchars((string) $sl['fileUrl']) ?>" target="_blank" rel="noopener noreferrer" class="text-xs font-bold text-emerald-700 hover:underline">Ouvrir</a>
    </div>
    <?php endif; ?>
    <?php
}

/* Compléments visuels communs (métrique, cartes, insights) */
require __DIR__ . '/canvas_slide_dense_bottom.php';

/* Actions de diapositive */
ob_start();
foreach (['primaryAction' => 'primary', 'secondaryAction' => 'secondary'] as $akey => $cls) {
    $act = $sl[$akey] ?? null;
    if (!is_array($act) || empty($act['label'])) {
        continue;
    }
    $label = (string) $act['label'];
    $type = (string) ($act['type'] ?? 'link');
    if ($type === 'modal' && !empty($act['modalId'])) {
        $mid = preg_replace('/[^a-zA-Z0-9_-]/', '', (string) $act['modalId']);
        echo '<button type="button" class="px-5 py-2.5 rounded-xl text-sm font-bold ' . ($cls === 'primary' ? 'bg-emerald-600 text-white hover:bg-emerald-700' : 'border border-slate-300 text-slate-800 hover:bg-slate-50') . '" data-lms-open-modal="' . htmlspecialchars($mid) . '">' . htmlspecialchars($label) . '</button>';
    } else {
        $u = (string) ($act['url'] ?? '');
        if ($u !== '') {
            echo '<a href="' . htmlspecialchars($u) . '" target="_blank" rel="noopener" class="px-5 py-2.5 rounded-xl text-sm font-bold ' . ($cls === 'primary' ? 'bg-emerald-600 text-white hover:bg-emerald-700' : 'border border-slate-300 text-slate-800 hover:bg-slate-50') . '">' . htmlspecialchars($label) . '</a>';
        }
    }
}
$slideActionsInner = ob_get_clean();
if ($slideActionsInner !== ''):
    ?>
<div class="mt-auto mt-5 flex flex-wrap gap-3 pt-3">
    <?= $slideActionsInner ?>
</div>
<?php endif; ?>
