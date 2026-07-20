<?php
declare(strict_types=1);
/** @var array<string, mixed> $entry */
/** @var bool $showAdminActions */
/** @var array<string, string> $priorityClass */
/** @var array<string, string> $priorityShort */
/** @var array<string, string> $operationalLabels */
/** @var array<string, string> $phaseLabels */
/** @var array<string, string> $entryTypeLabels */

$showAdminActions = $showAdminActions ?? false;
$priority = (string) ($entry['priority'] ?? 'normal');
$tags = array_filter(array_map('trim', explode(',', (string) ($entry['tags_list'] ?? ''))));
$opKey = (string) ($entry['operational_status'] ?? 'planned');
$phaseKey = (string) ($entry['phase_current'] ?? 'phase_1');
$etype = (string) ($entry['entry_type'] ?? 'task');
$tagBlob = strtolower(implode(' ', $tags));
$eid = (int) ($entry['id'] ?? 0);
$valStat = (string) ($entry['validation_status'] ?? 'draft');
$pubRow = (string) ($entry['status'] ?? 'active');
$isDraftPublication = ($pubRow === 'draft' || $valStat === 'draft');
$titleRaw = trim((string) ($entry['title'] ?? ''));
$titleDisplay = $titleRaw !== '' ? $titleRaw : 'Sans titre';
$searchHaystack = strtolower(trim(implode(' ', array_filter([
    $titleDisplay,
    (string) ($entry['description'] ?? ''),
    (string) ($entry['chief_name'] ?? ''),
    (string) ($entry['deputy_name'] ?? ''),
    (string) ($entry['replacement_name'] ?? ''),
    (string) ($entry['operation_zone'] ?? ''),
    $tagBlob,
]))));
?>
<article class="entry-card rounded-xl border border-l-4 bg-white p-3 text-xs shadow-sm <?= htmlspecialchars($priorityClass[$priority] ?? $priorityClass['normal'], ENT_QUOTES, 'UTF-8') ?>"
         data-entry_type="<?= htmlspecialchars($etype, ENT_QUOTES, 'UTF-8') ?>"
         data-operational_status="<?= htmlspecialchars($opKey, ENT_QUOTES, 'UTF-8') ?>"
         data-priority="<?= htmlspecialchars($priority, ENT_QUOTES, 'UTF-8') ?>"
         data-tag="<?= htmlspecialchars($tagBlob, ENT_QUOTES, 'UTF-8') ?>"
         data-search="<?= htmlspecialchars($searchHaystack, ENT_QUOTES, 'UTF-8') ?>">
    <div class="entry-card__head flex items-start justify-between gap-2">
        <?php if ($showAdminActions && $eid > 0): ?>
            <h3 class="entry-card__title min-w-0 flex-1 font-bold leading-snug text-slate-900">
                <a href="<?= url('back-office/tableau-operationnel/fiche/' . $eid) ?>" class="text-slate-900 underline decoration-emerald-200 decoration-2 underline-offset-2 hover:text-emerald-900 hover:decoration-emerald-400"><?= htmlspecialchars($titleDisplay, ENT_QUOTES, 'UTF-8') ?></a>
            </h3>
        <?php else: ?>
            <h3 class="entry-card__title min-w-0 flex-1 font-bold leading-snug text-slate-900"><?= htmlspecialchars($titleDisplay, ENT_QUOTES, 'UTF-8') ?></h3>
        <?php endif; ?>
        <div class="entry-card__badges flex shrink-0 flex-wrap items-center justify-end gap-1">
            <?php if ($showAdminActions && $isDraftPublication): ?>
                <span class="entry-card__badge entry-card__badge--draft rounded-full border border-amber-200 bg-amber-50 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-amber-950" title="En cours de préparation : visible ici pour le pilotage, pas encore sur le mur des membres">Brouillon</span>
            <?php endif; ?>
            <span class="entry-card__badge entry-card__badge--priority rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-slate-800"><?= htmlspecialchars($priorityShort[$priority] ?? $priority, ENT_QUOTES, 'UTF-8') ?></span>
        </div>
    </div>
    <?php if ($showAdminActions && $eid > 0 && $isDraftPublication): ?>
        <div class="entry-card-draft-open mt-2">
            <a href="<?= url('back-office/tableau-operationnel/fiche/' . $eid) ?>" class="inline-flex items-center rounded-lg border border-amber-300 bg-amber-50 px-2.5 py-1.5 text-[11px] font-bold text-amber-950 shadow-sm hover:bg-amber-100">Continuer la rédaction</a>
        </div>
    <?php endif; ?>
    <?php if ($etype === 'flash_info' && !empty($entry['description'])): ?>
        <div class="entry-card__body mt-2 text-sm leading-relaxed text-slate-800"><?= nl2br(htmlspecialchars((string) $entry['description'], ENT_QUOTES, 'UTF-8')) ?></div>
    <?php elseif (!empty($entry['description'])): ?>
        <p class="entry-card__body mt-2 leading-relaxed text-slate-700"><?= nl2br(htmlspecialchars((string) $entry['description'], ENT_QUOTES, 'UTF-8')) ?></p>
    <?php endif; ?>
    <dl class="entry-card__meta mt-2 grid grid-cols-1 gap-1.5 text-slate-700 md:grid-cols-2">
        <div class="entry-card__meta-row">
            <dt class="inline font-semibold text-slate-800">Commandement :</dt>
            <dd class="inline"><?= htmlspecialchars((string) ($entry['chief_name'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></dd>
        </div>
        <div class="entry-card__meta-row">
            <dt class="inline font-semibold text-slate-800">Adjoint :</dt>
            <dd class="inline"><?= htmlspecialchars((string) ($entry['deputy_name'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></dd>
        </div>
        <div class="entry-card__meta-row">
            <dt class="inline font-semibold text-slate-800">Remplaçant :</dt>
            <dd class="inline"><?= htmlspecialchars((string) ($entry['replacement_name'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></dd>
        </div>
        <div class="entry-card__meta-row">
            <dt class="inline font-semibold text-slate-800">Zone :</dt>
            <dd class="inline"><?= htmlspecialchars((string) ($entry['operation_zone'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></dd>
            <?php if (!empty($entry['map_link'])): ?>
                · <a class="font-semibold text-emerald-700 underline decoration-emerald-200 underline-offset-2 hover:text-emerald-900" href="<?= htmlspecialchars((string) $entry['map_link'], ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener">Carte</a>
            <?php endif; ?>
        </div>
    </dl>
    <p class="entry-card__status mt-2 flex flex-wrap items-center gap-1.5 text-slate-700">
        <span class="entry-card__chip"><span class="entry-card__chip-label">Statut</span> <?= htmlspecialchars($operationalLabels[$opKey] ?? $opKey, ENT_QUOTES, 'UTF-8') ?></span>
        <span class="entry-card__chip"><span class="entry-card__chip-label">Phase</span> <?= htmlspecialchars($phaseLabels[$phaseKey] ?? $phaseKey, ENT_QUOTES, 'UTF-8') ?></span>
        <span class="entry-card__chip"><span class="entry-card__chip-label">Type</span> <?= htmlspecialchars($entryTypeLabels[$etype] ?? $etype, ENT_QUOTES, 'UTF-8') ?></span>
    </p>
    <?php if ($showAdminActions): ?>
    <p class="mt-1 text-[10px] uppercase tracking-wide text-slate-500">Publication : <?= htmlspecialchars($valStat === 'draft' ? 'brouillon' : ($valStat === 'rejected' ? 'refusée' : ($valStat === 'validated' ? 'approuvée' : 'en ligne')), ENT_QUOTES, 'UTF-8') ?></p>
    <?php endif; ?>
    <p class="entry-card__checklist mt-1.5 text-slate-700">
        Points de contrôle : <?= (int) ($entry['checklist_done'] ?? 0) ?> / <?= (int) ($entry['checklist_required'] ?? 0) ?>
        <?php if (!empty($entry['dossier_ref'])): ?>
            · Dossier : <?= htmlspecialchars((string) $entry['dossier_ref'], ENT_QUOTES, 'UTF-8') ?>
        <?php endif; ?>
    </p>
    <?php if ($tags): ?>
        <p class="entry-card__tags mt-2 text-[10px] font-semibold uppercase tracking-wide text-slate-600">Étiquettes : <?= htmlspecialchars(implode(' · ', $tags), ENT_QUOTES, 'UTF-8') ?></p>
    <?php endif; ?>
    <?php if ($showAdminActions && $eid > 0): ?>
        <div class="entry-card-actions mt-3 flex flex-col gap-2 border-t border-slate-100 pt-3">
            <div class="flex flex-wrap items-center gap-2">
                <a href="<?= url('back-office/tableau-operationnel/fiche/' . $eid) ?>" class="inline-flex w-fit rounded-lg border border-emerald-600 bg-emerald-50 px-3 py-1.5 text-[11px] font-bold text-emerald-900 hover:bg-emerald-100"><?= $isDraftPublication ? 'Ouvrir pour modifier' : 'Ouvrir la fiche' ?></a>
                <form method="post" action="<?= url('back-office/tableau-operationnel/fiche/' . $eid . '/dupliquer') ?>" class="inline">
                    <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars(\App\Core\Csrf::token(), ENT_QUOTES, 'UTF-8') ?>">
                    <button type="submit" class="rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-[11px] font-bold text-slate-800 hover:bg-slate-50" title="Crée un brouillon reprenant le contenu de cette entrée">Copier en brouillon</button>
                </form>
            </div>
            <div class="flex flex-wrap gap-2">
                <?php if ($valStat === 'draft'): ?>
                    <form method="post" action="<?= url('back-office/tableau-operationnel/' . $eid . '/validation') ?>" class="inline">
                        <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars(\App\Core\Csrf::token(), ENT_QUOTES, 'UTF-8') ?>">
                        <input type="hidden" name="validation_status" value="validated">
                        <button type="submit" class="rounded-lg bg-slate-700 px-3 py-1.5 text-[11px] font-bold text-white hover:bg-slate-800">Approuver</button>
                    </form>
                    <form method="post" action="<?= url('back-office/tableau-operationnel/' . $eid . '/validation') ?>" class="inline">
                        <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars(\App\Core\Csrf::token(), ENT_QUOTES, 'UTF-8') ?>">
                        <input type="hidden" name="validation_status" value="rejected">
                        <button type="submit" class="rounded-lg border border-rose-300 bg-rose-50 px-3 py-1.5 text-[11px] font-bold text-rose-900 hover:bg-rose-100">Refuser</button>
                    </form>
                <?php endif; ?>
                <?php if (in_array($valStat, ['validated', 'draft'], true)): ?>
                    <form method="post" action="<?= url('back-office/tableau-operationnel/' . $eid . '/validation') ?>" class="inline">
                        <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars(\App\Core\Csrf::token(), ENT_QUOTES, 'UTF-8') ?>">
                        <input type="hidden" name="validation_status" value="active">
                        <button type="submit" class="rounded-lg bg-emerald-700 px-3 py-1.5 text-[11px] font-bold text-white hover:bg-emerald-800">Mettre en ligne</button>
                    </form>
                <?php endif; ?>
                <form method="post" action="<?= url('back-office/tableau-operationnel/' . $eid . '/frago') ?>" class="inline">
                    <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars(\App\Core\Csrf::token(), ENT_QUOTES, 'UTF-8') ?>">
                    <button type="submit" class="rounded-lg bg-slate-800 px-3 py-1.5 text-[11px] font-bold text-white hover:bg-slate-900">Mise à jour opérationnelle</button>
                </form>
                <form method="post" action="<?= url('back-office/tableau-operationnel/' . $eid . '/status') ?>" class="inline">
                    <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars(\App\Core\Csrf::token(), ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="operational_status" value="completed">
                    <button type="submit" class="rounded-lg bg-emerald-700 px-3 py-1.5 text-[11px] font-bold text-white hover:bg-emerald-800">Clôturer</button>
                </form>
            </div>
        </div>
    <?php endif; ?>
</article>
