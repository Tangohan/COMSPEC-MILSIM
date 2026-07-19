<?php
declare(strict_types=1);

/**
 * @var array<string,mixed> $transmissionSession
 * @var list<array<string,mixed>> $transmissionEntries
 * @var bool $transmissionCanManage
 * @var bool $transmissionCanContribute
 * @var bool $transmissionCanManagePoe
 * @var bool $transmissionPoeExists
 */

$s = is_array($transmissionSession ?? null) ? $transmissionSession : [];
$sid = (int) ($s['id'] ?? 0);
$sTitle = trim((string) ($s['title'] ?? ''));
$status = (string) ($s['status'] ?? 'open');
$isOpen = $status === 'open';
$eventTitle = trim((string) ($s['event_title'] ?? ''));
$entries = is_array($transmissionEntries ?? null) ? $transmissionEntries : [];
$canManage = (bool) ($transmissionCanManage ?? false);
$canContribute = (bool) ($transmissionCanContribute ?? false);
$canManagePoe = (bool) ($transmissionCanManagePoe ?? false);
$poeExists = (bool) ($transmissionPoeExists ?? false);
$flashSuccess = \App\Core\Session::getFlash('success');
$flashError = \App\Core\Session::getFlash('error');
?>
<div class="mx-auto max-w-4xl space-y-6 px-4 py-8 sm:px-6">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <p class="text-[10px] font-black uppercase tracking-[0.28em] text-slate-500">
                <a href="<?= htmlspecialchars(url('transmission'), ENT_QUOTES, 'UTF-8') ?>" class="hover:underline">Transmission</a>
                <?= $eventTitle !== '' ? ' · ' . htmlspecialchars($eventTitle, ENT_QUOTES, 'UTF-8') : '' ?>
            </p>
            <h1 class="mt-1 text-2xl font-black tracking-tight text-slate-900"><?= htmlspecialchars($sTitle, ENT_QUOTES, 'UTF-8') ?></h1>
            <span class="mt-2 inline-flex items-center rounded-full px-2.5 py-0.5 text-[10px] font-bold uppercase tracking-wide ring-1 <?= $isOpen ? 'bg-emerald-50 text-emerald-800 ring-emerald-200' : 'bg-slate-100 text-slate-700 ring-slate-200' ?>"><?= $isOpen ? 'Ouverte' : 'Fermée' ?></span>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="<?= htmlspecialchars(url('transmission/' . $sid . '/poe'), ENT_QUOTES, 'UTF-8') ?>" class="rounded-lg border border-slate-900 bg-slate-900 px-3 py-2 text-[11px] font-bold uppercase tracking-wide text-white hover:bg-slate-800">
                <?= $poeExists ? 'Ouvrir le PoE' : 'Rédiger le PoE' ?>
            </a>
            <?php if ($canManage): ?>
            <form method="post" action="<?= htmlspecialchars(url('transmission/' . $sid . '/' . ($isOpen ? 'close' : 'reopen')), ENT_QUOTES, 'UTF-8') ?>">
                <?= \App\Core\Csrf::field() ?>
                <button type="submit" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-[11px] font-bold uppercase tracking-wide text-slate-800 hover:bg-slate-100"><?= $isOpen ? 'Fermer la session' : 'Rouvrir la session' ?></button>
            </form>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($flashSuccess): ?>
    <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900"><?= htmlspecialchars((string) $flashSuccess, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>
    <?php if ($flashError): ?>
    <div class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-900"><?= htmlspecialchars((string) $flashError, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>

    <div class="space-y-4">
        <?php if ($entries === []): ?>
        <div class="rounded-2xl border-2 border-dashed border-slate-300 bg-slate-50 px-6 py-10 text-center">
            <p class="text-sm text-slate-600">Aucun compte-rendu pour le moment. Le fil de reconnaissance apparaîtra ici.</p>
        </div>
        <?php else: ?>
            <?php foreach ($entries as $e): ?>
                <?php
                $eid = (int) ($e['id'] ?? 0);
                $authorName = trim((string) ($e['author_name'] ?? '')) ?: trim((string) ($e['author_email'] ?? '')) ?: 'Membre';
                $createdAt = trim((string) ($e['created_at'] ?? ''));
                $body = trim((string) ($e['body'] ?? ''));
                $gridRef = trim((string) ($e['grid_ref'] ?? ''));
                $attachments = is_array($e['attachments'] ?? null) ? $e['attachments'] : [];
                ?>
            <article id="pv-<?= $eid ?>" class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <div class="flex flex-wrap items-baseline justify-between gap-2">
                    <p class="text-sm font-black text-slate-900"><?= htmlspecialchars($authorName, ENT_QUOTES, 'UTF-8') ?></p>
                    <p class="text-xs text-slate-500"><?= $createdAt !== '' ? htmlspecialchars(date('d/m/Y H:i', strtotime($createdAt)), ENT_QUOTES, 'UTF-8') : '' ?><?= $gridRef !== '' ? ' · Grille ' . htmlspecialchars($gridRef, ENT_QUOTES, 'UTF-8') : '' ?></p>
                </div>
                <?php if ($body !== ''): ?>
                <p class="mt-3 whitespace-pre-wrap text-sm leading-relaxed text-slate-700"><?= htmlspecialchars($body, ENT_QUOTES, 'UTF-8') ?></p>
                <?php endif; ?>
                <?php if ($attachments !== []): ?>
                <div class="mt-3 grid grid-cols-2 gap-2 sm:grid-cols-3">
                    <?php foreach ($attachments as $att): ?>
                        <?php $attUrl = asset_url((string) ($att['storage_path'] ?? '')); ?>
                    <a href="<?= htmlspecialchars($attUrl, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener" class="block overflow-hidden rounded-lg border border-slate-200">
                        <img src="<?= htmlspecialchars($attUrl, ENT_QUOTES, 'UTF-8') ?>" alt="Capture de reconnaissance" class="h-28 w-full object-cover" loading="lazy">
                    </a>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </article>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <?php if ($canContribute && $isOpen): ?>
    <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <h2 class="text-sm font-black uppercase tracking-wide text-slate-900">Ajouter un compte-rendu</h2>
        <form method="post" action="<?= htmlspecialchars(url('transmission/' . $sid . '/entries'), ENT_QUOTES, 'UTF-8') ?>" enctype="multipart/form-data" class="mt-4 space-y-3">
            <?= \App\Core\Csrf::field() ?>
            <div>
                <label class="block text-xs font-bold uppercase tracking-wide text-slate-600" for="pv-body">Observation</label>
                <textarea id="pv-body" name="body" rows="4" maxlength="4000" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" placeholder="Ce que la reconnaissance observe : positions, effectifs, itinéraires, menaces..."></textarea>
            </div>
            <div class="grid gap-3 sm:grid-cols-2">
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wide text-slate-600" for="pv-grid">Référence grille (optionnel)</label>
                    <input type="text" id="pv-grid" name="grid_ref" maxlength="50" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" placeholder="ex. 045 128">
                </div>
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wide text-slate-600" for="pv-attachments">Captures d’écran (max 6)</label>
                    <input type="file" id="pv-attachments" name="attachments[]" accept="image/jpeg,image/png,image/webp" multiple class="mt-1 w-full text-xs">
                </div>
            </div>
            <button type="submit" class="rounded-lg bg-emerald-600 px-4 py-2 text-xs font-bold uppercase tracking-wide text-white hover:bg-emerald-500">Publier dans le fil</button>
        </form>
    </section>
    <?php elseif (!$isOpen): ?>
    <p class="text-center text-xs text-slate-500">Session fermée — lecture seule.</p>
    <?php endif; ?>
</div>
