<?php
declare(strict_types=1);

/**
 * Formulaire POST vers publier-lie (brouillon tableau opérationnel depuis une source métier).
 *
 * Variables attendues :
 * - $opBoardPublishSourceType : 'event' | 'mission' | 'formation'
 * - $opBoardPublishSourceId : int > 0
 * - $opBoardPublishCsrf : jeton CSRF (chaîne)
 * - $opBoardPublishVariant : 'events_dark' | 'mission' | 'mission_compact' | 'course' (styles d’encart)
 */
$opBoardPublishSourceType = (string) ($opBoardPublishSourceType ?? 'mission');
$opBoardPublishSourceId = (int) ($opBoardPublishSourceId ?? 0);
$opBoardPublishCsrf = (string) ($opBoardPublishCsrf ?? \App\Core\Csrf::token());
$opBoardPublishVariant = (string) ($opBoardPublishVariant ?? 'mission');

if ($opBoardPublishSourceId < 1 || !in_array($opBoardPublishSourceType, ['event', 'mission', 'formation'], true)) {
    return;
}

$opBoardPublishHelp = 'Si une fiche est déjà ouverte pour cette source, vous serez invité à la reprendre plutôt qu’à en créer une nouvelle.';
$action = htmlspecialchars(url('back-office/tableau-operationnel/publier-lie'), ENT_QUOTES, 'UTF-8');
$csrfEsc = htmlspecialchars($opBoardPublishCsrf, ENT_QUOTES, 'UTF-8');
$typeEsc = htmlspecialchars($opBoardPublishSourceType, ENT_QUOTES, 'UTF-8');

if ($opBoardPublishVariant === 'events_dark'): ?>
                    <form method="post" action="<?= $action ?>" class="mt-3 space-y-1">
                        <input type="hidden" name="_csrf_token" value="<?= $csrfEsc ?>">
                        <input type="hidden" name="source_type" value="<?= $typeEsc ?>">
                        <input type="hidden" name="source_id" value="<?= $opBoardPublishSourceId ?>">
                        <button type="submit" class="text-xs font-bold text-sky-300 hover:text-sky-200 underline decoration-sky-500/50">Publier au mur opérationnel</button>
                        <p class="text-[10px] text-neutral-500 max-w-md"><?= htmlspecialchars($opBoardPublishHelp, ENT_QUOTES, 'UTF-8') ?></p>
                    </form>
<?php elseif ($opBoardPublishVariant === 'course'): ?>
                            <p class="mb-2 text-[9px] font-black uppercase tracking-[0.2em] text-emerald-800/80">Mur opérationnel</p>
                            <form method="post" action="<?= $action ?>" class="block w-full">
                                <input type="hidden" name="_csrf_token" value="<?= $csrfEsc ?>">
                                <input type="hidden" name="source_type" value="<?= $typeEsc ?>">
                                <input type="hidden" name="source_id" value="<?= $opBoardPublishSourceId ?>">
                                <button type="submit" class="flex w-full min-h-[2.5rem] items-center justify-center rounded-xl border border-emerald-400/60 bg-white px-4 py-2 text-xs font-black uppercase tracking-wider text-emerald-900 shadow-sm transition-colors hover:bg-emerald-100/80 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500 focus-visible:ring-offset-2">Publier au mur opérationnel</button>
                            </form>
                            <p class="mt-2 text-[10px] leading-snug text-slate-600"><?= htmlspecialchars($opBoardPublishHelp, ENT_QUOTES, 'UTF-8') ?></p>
<?php elseif ($opBoardPublishVariant === 'mission_compact'): ?>
<div class="mt-2 mb-4 flex flex-col gap-2 sm:flex-row sm:items-center sm:gap-4">
    <form method="post" action="<?= $action ?>" class="inline-block shrink-0">
        <input type="hidden" name="_csrf_token" value="<?= $csrfEsc ?>">
        <input type="hidden" name="source_type" value="<?= $typeEsc ?>">
        <input type="hidden" name="source_id" value="<?= $opBoardPublishSourceId ?>">
        <button type="submit" class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-2 text-sm font-semibold text-emerald-900 hover:bg-emerald-100">Publier au mur opérationnel</button>
    </form>
    <p class="text-xs text-slate-500 max-w-xl leading-relaxed"><?= htmlspecialchars($opBoardPublishHelp, ENT_QUOTES, 'UTF-8') ?></p>
</div>
<?php else: ?>
        <div class="mt-4 space-y-2">
        <form method="post" action="<?= $action ?>" class="inline-block">
            <input type="hidden" name="_csrf_token" value="<?= $csrfEsc ?>">
            <input type="hidden" name="source_type" value="<?= $typeEsc ?>">
            <input type="hidden" name="source_id" value="<?= $opBoardPublishSourceId ?>">
            <button type="submit" class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-2 text-sm font-semibold text-emerald-900 hover:bg-emerald-100">Publier au mur opérationnel</button>
        </form>
        <p class="text-xs text-slate-500 max-w-xl"><?= htmlspecialchars($opBoardPublishHelp, ENT_QUOTES, 'UTF-8') ?></p>
        </div>
<?php endif;
