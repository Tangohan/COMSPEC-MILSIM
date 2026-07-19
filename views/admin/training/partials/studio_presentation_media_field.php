<?php
/**
 * Champ média présentation Studio (image ou audio).
 *
 * Variables attendues :
 * @var string $mediaKey          ex. thumbnail | banner | loader | audio
 * @var string $mediaKind         image|audio
 * @var string $mediaLabel
 * @var string $mediaHelp
 * @var string $mediaPathValue    chemin relatif ou URL stockée
 * @var string $mediaPathName     name de l’input hidden (chemin)
 * @var string $mediaUploadName   name de l’input file
 * @var string $mediaRemoveName   name de l’input remove
 * @var string $mediaAccept       accept=…
 * @var string $mediaRatio        classes ratio (images)
 * @var string|null $mediaPreviewUrl  URL affichable actuelle (null = vide)
 */
$mediaKey = (string) ($mediaKey ?? 'media');
$mediaKind = (string) ($mediaKind ?? 'image');
$mediaLabel = (string) ($mediaLabel ?? 'Média');
$mediaHelp = (string) ($mediaHelp ?? '');
$mediaPathValue = (string) ($mediaPathValue ?? '');
$mediaPathName = (string) ($mediaPathName ?? ($mediaKey . '_path'));
$mediaUploadName = (string) ($mediaUploadName ?? ($mediaKey . '_upload'));
$mediaRemoveName = (string) ($mediaRemoveName ?? ($mediaKey . '_remove'));
$mediaAccept = (string) ($mediaAccept ?? 'image/jpeg,image/png,image/webp,image/gif');
$mediaRatio = (string) ($mediaRatio ?? 'aspect-[4/3]');
$mediaPreviewUrl = isset($mediaPreviewUrl) && is_string($mediaPreviewUrl) && trim($mediaPreviewUrl) !== ''
    ? trim($mediaPreviewUrl)
    : null;
$hasMedia = $mediaPreviewUrl !== null || $mediaPathValue !== '';
?>
<div class="ts-pres-media rounded-2xl border border-slate-200 bg-slate-50/60 p-4 sm:p-5" data-pres-media="<?= htmlspecialchars($mediaKey) ?>" data-pres-kind="<?= htmlspecialchars($mediaKind) ?>">
    <div class="flex flex-wrap items-start justify-between gap-3 mb-3">
        <div>
            <label class="block text-xs font-bold text-slate-700"><?= htmlspecialchars($mediaLabel) ?></label>
            <?php if ($mediaHelp !== ''): ?>
            <p class="text-[11px] text-slate-500 mt-0.5"><?= htmlspecialchars($mediaHelp) ?></p>
            <?php endif; ?>
        </div>
        <button type="button" class="text-xs font-bold text-rose-700 hover:underline <?= $hasMedia ? '' : 'hidden' ?>" data-pres-remove-btn="<?= htmlspecialchars($mediaKey) ?>">Retirer</button>
    </div>
    <input type="hidden" name="<?= htmlspecialchars($mediaPathName) ?>" value="<?= htmlspecialchars($mediaPathValue) ?>" data-pres-path="<?= htmlspecialchars($mediaKey) ?>">
    <input type="hidden" name="<?= htmlspecialchars($mediaRemoveName) ?>" value="0" data-pres-remove="<?= htmlspecialchars($mediaKey) ?>">

    <?php if ($mediaKind === 'audio'): ?>
    <div class="space-y-3">
        <div class="rounded-xl border border-slate-200 bg-white px-4 py-3" data-pres-audio-wrap="<?= htmlspecialchars($mediaKey) ?>">
            <p class="text-[11px] text-slate-400 <?= $mediaPreviewUrl ? 'hidden' : '' ?>" data-pres-audio-empty="<?= htmlspecialchars($mediaKey) ?>">Aucun fichier audio pour l’instant</p>
            <audio controls class="w-full <?= $mediaPreviewUrl ? '' : 'hidden' ?>"<?php if ($mediaPreviewUrl): ?> src="<?= htmlspecialchars($mediaPreviewUrl) ?>"<?php endif; ?> data-pres-audio="<?= htmlspecialchars($mediaKey) ?>"></audio>
        </div>
        <label class="media-dropzone flex flex-col items-center justify-center text-center gap-1.5 rounded-xl border-2 border-dashed border-slate-300 bg-white px-4 py-6 cursor-pointer transition hover:border-emerald-400 hover:bg-emerald-50/40" data-pres-dropzone="<?= htmlspecialchars($mediaKey) ?>">
            <span class="text-sm font-semibold text-slate-700">Joindre un fichier audio</span>
            <span class="text-[11px] text-slate-500">MP3, OGG, WAV ou M4A — 12 Mo maximum</span>
            <input type="file" name="<?= htmlspecialchars($mediaUploadName) ?>" accept="<?= htmlspecialchars($mediaAccept) ?>" class="sr-only" data-pres-file="<?= htmlspecialchars($mediaKey) ?>">
        </label>
    </div>
    <?php else: ?>
    <div class="grid grid-cols-1 sm:grid-cols-[minmax(0,200px)_1fr] gap-4 items-stretch">
        <div class="<?= htmlspecialchars($mediaRatio) ?> w-full rounded-xl border border-slate-200 bg-white overflow-hidden flex items-center justify-center" data-pres-preview-wrap="<?= htmlspecialchars($mediaKey) ?>">
            <img src="<?= htmlspecialchars((string) $mediaPreviewUrl) ?>" alt="" class="w-full h-full object-cover <?= $mediaPreviewUrl ? '' : 'hidden' ?>" data-pres-preview-img="<?= htmlspecialchars($mediaKey) ?>">
            <span class="text-[11px] text-slate-400 px-3 text-center <?= $mediaPreviewUrl ? 'hidden' : '' ?>" data-pres-preview-empty="<?= htmlspecialchars($mediaKey) ?>">Aucune image</span>
        </div>
        <div class="flex flex-col gap-2 justify-center">
            <label class="media-dropzone flex flex-col items-center justify-center text-center gap-1 rounded-xl border-2 border-dashed border-slate-300 bg-white px-3 py-5 cursor-pointer transition hover:border-emerald-400 hover:bg-emerald-50/40" data-pres-dropzone="<?= htmlspecialchars($mediaKey) ?>">
                <span class="text-sm font-semibold text-slate-700">Téléverser</span>
                <span class="text-[11px] text-slate-500">Depuis votre poste (JPG, PNG, WEBP, GIF — 4 Mo max.)</span>
                <input type="file" name="<?= htmlspecialchars($mediaUploadName) ?>" accept="<?= htmlspecialchars($mediaAccept) ?>" class="sr-only" data-pres-file="<?= htmlspecialchars($mediaKey) ?>">
            </label>
            <button type="button" class="inline-flex items-center justify-center rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-xs font-bold text-slate-800 hover:bg-slate-50" data-pres-open-library="<?= htmlspecialchars($mediaKey) ?>">
                Choisir une image du site
            </button>
        </div>
    </div>
    <?php endif; ?>
    <p class="mt-2 text-[11px] text-slate-500 empty:hidden" data-pres-filename="<?= htmlspecialchars($mediaKey) ?>"></p>
</div>
