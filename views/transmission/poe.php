<?php
declare(strict_types=1);

/**
 * @var array<string,mixed> $transmissionSession
 * @var list<array<string,mixed>> $transmissionEntries
 * @var array<string,mixed>|null $transmissionPoe
 * @var bool $transmissionCanManagePoe
 */

$s = is_array($transmissionSession ?? null) ? $transmissionSession : [];
$sid = (int) ($s['id'] ?? 0);
$sTitle = trim((string) ($s['title'] ?? ''));
$entries = is_array($transmissionEntries ?? null) ? $transmissionEntries : [];
$poe = is_array($transmissionPoe ?? null) ? $transmissionPoe : null;
$canEdit = (bool) ($transmissionCanManagePoe ?? false);
$flashSuccess = \App\Core\Session::getFlash('success');
$flashError = \App\Core\Session::getFlash('error');

$poeTitle = trim((string) ($poe['title'] ?? '')) ?: ('Plan d’exécution — ' . $sTitle);
$poeStatus = (string) ($poe['status'] ?? 'draft');
$sections = [
    'situation' => 'Situation',
    'mission' => 'Mission',
    'execution' => 'Exécution',
    'soutien' => 'Soutien',
    'commandement' => 'Commandement & transmissions',
];
?>
<div class="mx-auto max-w-6xl px-4 py-8 sm:px-6">
    <p class="text-[10px] font-black uppercase tracking-[0.28em] text-slate-500">
        <a href="<?= htmlspecialchars(url('transmission/' . $sid), ENT_QUOTES, 'UTF-8') ?>" class="hover:underline">← <?= htmlspecialchars($sTitle, ENT_QUOTES, 'UTF-8') ?></a>
    </p>
    <div class="mt-1 flex flex-wrap items-center justify-between gap-3">
        <h1 class="text-2xl font-black tracking-tight text-slate-900">Plan d’Exécution (PoE)</h1>
        <?php if ($poe !== null): ?>
        <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-[10px] font-bold uppercase tracking-wide ring-1 <?= $poeStatus === 'published' ? 'bg-emerald-50 text-emerald-800 ring-emerald-200' : 'bg-amber-50 text-amber-800 ring-amber-200' ?>">
            <?= $poeStatus === 'published' ? 'Publié' : 'Brouillon' ?>
        </span>
        <?php endif; ?>
    </div>

    <?php if ($flashSuccess): ?>
    <div class="mt-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900"><?= htmlspecialchars((string) $flashSuccess, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>
    <?php if ($flashError): ?>
    <div class="mt-4 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-900"><?= htmlspecialchars((string) $flashError, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>

    <div class="mt-6 grid gap-6 lg:grid-cols-[1fr_20rem]">
        <form method="post" action="<?= htmlspecialchars(url('transmission/' . $sid . '/poe'), ENT_QUOTES, 'UTF-8') ?>" class="space-y-4">
            <?= \App\Core\Csrf::field() ?>
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <label class="block text-xs font-bold uppercase tracking-wide text-slate-600" for="poe-title">Titre du document</label>
                <input type="text" id="poe-title" name="title" maxlength="200" value="<?= htmlspecialchars($poeTitle, ENT_QUOTES, 'UTF-8') ?>" <?= $canEdit ? '' : 'readonly' ?> class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm font-bold">
            </div>

            <?php foreach ($sections as $key => $label): ?>
                <?php $val = trim((string) ($poe['section_' . $key] ?? '')); ?>
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <label class="block text-xs font-bold uppercase tracking-wide text-slate-600" for="poe-<?= $key ?>"><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></label>
                <textarea id="poe-<?= $key ?>" name="section_<?= $key ?>" rows="5" <?= $canEdit ? '' : 'readonly' ?> class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"><?= htmlspecialchars($val, ENT_QUOTES, 'UTF-8') ?></textarea>
            </div>
            <?php endforeach; ?>

            <?php if ($canEdit): ?>
            <div class="flex flex-wrap gap-3">
                <button type="submit" name="publish" value="0" class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-xs font-bold uppercase tracking-wide text-slate-800 hover:bg-slate-100">Enregistrer le brouillon</button>
                <button type="submit" name="publish" value="1" class="rounded-lg bg-emerald-600 px-4 py-2 text-xs font-bold uppercase tracking-wide text-white hover:bg-emerald-500">Enregistrer et publier</button>
            </div>
            <?php else: ?>
            <p class="text-xs text-slate-500">Lecture seule — seul le Mission Maker habilité peut modifier ce document.</p>
            <?php endif; ?>
        </form>

        <aside class="space-y-3">
            <p class="text-[10px] font-black uppercase tracking-[0.2em] text-slate-500">Fil de reconnaissance (<?= count($entries) ?>)</p>
            <?php if ($entries === []): ?>
            <p class="text-xs text-slate-500">Aucun compte-rendu publié pour l’instant.</p>
            <?php else: ?>
            <div class="max-h-[70vh] space-y-2 overflow-y-auto pr-1">
                <?php foreach (array_reverse($entries) as $e): ?>
                    <?php
                    $authorName = trim((string) ($e['author_name'] ?? '')) ?: 'Membre';
                    $createdAt = trim((string) ($e['created_at'] ?? ''));
                    $capturedAt = trim((string) ($e['captured_at'] ?? ''));
                    $body = trim((string) ($e['body'] ?? ''));
                    $bodyShort = mb_strlen($body) > 140 ? mb_substr($body, 0, 137) . '…' : $body;
                    $attCount = count(is_array($e['attachments'] ?? null) ? $e['attachments'] : []);
                    $urgency = trim((string) ($e['urgency'] ?? ''));
                    $hasTammuc = trim((string) ($e['terrain_text'] ?? '')) !== ''
                        || trim((string) ($e['adversary_text'] ?? '')) !== ''
                        || trim((string) ($e['mission_text'] ?? '')) !== ''
                        || trim((string) ($e['means_text'] ?? '')) !== ''
                        || trim((string) ($e['engagement_frame_text'] ?? '')) !== '';
                    ?>
                <a href="<?= htmlspecialchars(url('transmission/' . $sid) . '#pv-' . (int) ($e['id'] ?? 0), ENT_QUOTES, 'UTF-8') ?>" class="block rounded-xl border border-slate-200 bg-white p-3 text-xs hover:border-emerald-300 hover:bg-emerald-50/30">
                    <p class="font-bold text-slate-800"><?= htmlspecialchars($authorName, ENT_QUOTES, 'UTF-8') ?> <span class="font-normal text-slate-400"><?= $createdAt !== '' ? date('d/m H:i', strtotime($createdAt)) : '' ?></span></p>
                    <?php if ($capturedAt !== ''): ?>
                    <p class="mt-0.5 text-[10px] font-semibold text-emerald-700">Captation <?= htmlspecialchars(date('d/m H:i', strtotime($capturedAt)), ENT_QUOTES, 'UTF-8') ?></p>
                    <?php endif; ?>
                    <?php if ($bodyShort !== ''): ?><p class="mt-1 text-slate-600"><?= htmlspecialchars($bodyShort, ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>
                    <p class="mt-1 flex flex-wrap gap-x-2 gap-y-0.5 text-[10px] font-bold uppercase tracking-wide text-slate-500">
                        <?php if ($hasTammuc): ?><span class="text-slate-700">MRT</span><?php endif; ?>
                        <?php if ($urgency === 'immediate'): ?><span class="text-rose-700">Urgent</span><?php elseif ($urgency === 'deferred'): ?><span class="text-amber-700">Différé</span><?php endif; ?>
                        <?php if ($attCount > 0): ?><span class="text-emerald-700"><?= $attCount ?> capture<?= $attCount > 1 ? 's' : '' ?></span><?php endif; ?>
                    </p>
                </a>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </aside>
    </div>
</div>
