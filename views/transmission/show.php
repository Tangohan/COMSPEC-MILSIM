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

$urgencyLabels = [
    'immediate' => 'Urgence immédiate',
    'deferred' => 'Urgence différée',
];

$tammucSections = [
    'terrain_text' => ['Terrain', 'Points favorables ou défavorables, objectifs terrain, limites, itinéraires d’approche, de désengagement ou de fuite adverse.'],
    'adversary_text' => ['Adversaire', 'Position, nature, attitude, volume, armement, rapport de force, renforts possibles.'],
    'mission_text' => ['Mission', 'Mission, terme missionnel et objectifs clairs pour le chef d’élément.'],
    'means_text' => ['Moyens', 'Moyens disponibles et renforts pouvant être demandés.'],
    'engagement_frame_text' => ['Cadre de la manœuvre', 'Règles d’engagement et limites d’emploi dans le cadre de la manœuvre.'],
];

$defaultCapturedLocal = date('Y-m-d\TH:i');
?>
<div class="mx-auto max-w-4xl space-y-8 px-4 py-8 sm:px-6">
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

    <div class="space-y-5">
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
                $capturedAt = trim((string) ($e['captured_at'] ?? ''));
                $body = trim((string) ($e['body'] ?? ''));
                $gridRef = trim((string) ($e['grid_ref'] ?? ''));
                $urgency = trim((string) ($e['urgency'] ?? ''));
                $urgencyLabel = $urgencyLabels[$urgency] ?? '';
                $attachments = is_array($e['attachments'] ?? null) ? $e['attachments'] : [];
                ?>
            <article id="pv-<?= $eid ?>" class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
                <div class="flex flex-wrap items-baseline justify-between gap-2">
                    <p class="text-sm font-black text-slate-900"><?= htmlspecialchars($authorName, ENT_QUOTES, 'UTF-8') ?></p>
                    <p class="text-xs text-slate-500">
                        Publié <?= $createdAt !== '' ? htmlspecialchars(date('d/m/Y H:i', strtotime($createdAt)), ENT_QUOTES, 'UTF-8') : '' ?>
                        <?php if ($gridRef !== ''): ?> · Grille <?= htmlspecialchars($gridRef, ENT_QUOTES, 'UTF-8') ?><?php endif; ?>
                    </p>
                </div>
                <?php if ($capturedAt !== ''): ?>
                <p class="mt-2 text-xs font-semibold text-emerald-800">
                    Captation : <?= htmlspecialchars(date('d/m/Y H:i', strtotime($capturedAt)), ENT_QUOTES, 'UTF-8') ?>
                </p>
                <?php endif; ?>
                <?php if ($urgencyLabel !== ''): ?>
                <p class="mt-2 inline-flex items-center rounded-full px-2.5 py-0.5 text-[10px] font-bold uppercase tracking-wide ring-1 <?= $urgency === 'immediate' ? 'bg-rose-50 text-rose-800 ring-rose-200' : 'bg-amber-50 text-amber-800 ring-amber-200' ?>">
                    <?= htmlspecialchars($urgencyLabel, ENT_QUOTES, 'UTF-8') ?>
                </p>
                <?php endif; ?>

                <?php if ($body !== ''): ?>
                <div class="mt-4">
                    <p class="text-[10px] font-black uppercase tracking-wide text-slate-500">Synthèse</p>
                    <p class="mt-1 whitespace-pre-wrap text-sm leading-relaxed text-slate-700"><?= htmlspecialchars($body, ENT_QUOTES, 'UTF-8') ?></p>
                </div>
                <?php endif; ?>

                <?php
                $hasAnyTammuc = false;
                foreach (array_keys($tammucSections) as $fieldKey) {
                    if (trim((string) ($e[$fieldKey] ?? '')) !== '') {
                        $hasAnyTammuc = true;
                        break;
                    }
                }
                ?>
                <?php if ($hasAnyTammuc): ?>
                <div class="mt-5 space-y-4 border-t border-slate-100 pt-4">
                    <p class="text-[10px] font-black uppercase tracking-[0.2em] text-slate-500">Analyse rapide (MRT)</p>
                    <?php foreach ($tammucSections as $fieldKey => [$label, $hint]): ?>
                        <?php $val = trim((string) ($e[$fieldKey] ?? '')); ?>
                        <?php if ($val === '') {
                            continue;
                        } ?>
                    <div>
                        <p class="text-xs font-bold uppercase tracking-wide text-slate-700"><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></p>
                        <p class="mt-1 whitespace-pre-wrap text-sm leading-relaxed text-slate-700"><?= htmlspecialchars($val, ENT_QUOTES, 'UTF-8') ?></p>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>

                <?php if ($attachments !== []): ?>
                <div class="mt-5 grid grid-cols-2 gap-2 sm:grid-cols-3">
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
    <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
        <h2 class="text-sm font-black uppercase tracking-wide text-slate-900">Ajouter un compte-rendu</h2>
        <p class="mt-2 text-sm leading-relaxed text-slate-600">
            Analyse rapide destinée au chef d’élément : structure MRT simplifiée (Terrain, Adversaire, Mission, Moyens et urgence, Cadre de la manœuvre). Restez concis — ce n’est pas un mémoire.
        </p>

        <form method="post" action="<?= htmlspecialchars(url('transmission/' . $sid . '/entries'), ENT_QUOTES, 'UTF-8') ?>" enctype="multipart/form-data" class="mt-6 space-y-6">
            <?= \App\Core\Csrf::field() ?>

            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wide text-slate-600" for="pv-captured">Heure de captation <span class="normal-case font-semibold text-rose-600">(obligatoire)</span></label>
                    <input type="datetime-local" id="pv-captured" name="captured_at" required value="<?= htmlspecialchars($defaultCapturedLocal, ENT_QUOTES, 'UTF-8') ?>" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    <p class="mt-1 text-xs text-slate-500">Date et heure auxquelles l’observation a été faite sur le terrain.</p>
                </div>
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wide text-slate-600" for="pv-grid">Référence grille (optionnel)</label>
                    <input type="text" id="pv-grid" name="grid_ref" maxlength="50" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" placeholder="ex. 045 128">
                </div>
            </div>

            <div>
                <label class="block text-xs font-bold uppercase tracking-wide text-slate-600" for="pv-body">Synthèse</label>
                <textarea id="pv-body" name="body" rows="3" maxlength="4000" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" placeholder="Vue d’ensemble en quelques lignes : ce qu’il faut retenir immédiatement…"></textarea>
            </div>

            <div class="space-y-5 border-t border-slate-100 pt-5">
                <p class="text-[10px] font-black uppercase tracking-[0.2em] text-slate-500">Sections d’analyse</p>

                <?php foreach (['terrain_text', 'adversary_text', 'mission_text'] as $fieldKey): ?>
                    <?php [$label, $hint] = $tammucSections[$fieldKey]; ?>
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wide text-slate-600" for="pv-<?= htmlspecialchars($fieldKey, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></label>
                    <p class="mt-0.5 text-xs text-slate-500"><?= htmlspecialchars($hint, ENT_QUOTES, 'UTF-8') ?></p>
                    <textarea id="pv-<?= htmlspecialchars($fieldKey, ENT_QUOTES, 'UTF-8') ?>" name="<?= htmlspecialchars($fieldKey, ENT_QUOTES, 'UTF-8') ?>" rows="3" maxlength="4000" class="mt-1.5 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"></textarea>
                </div>
                <?php endforeach; ?>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label class="block text-xs font-bold uppercase tracking-wide text-slate-600" for="pv-means_text">Moyens</label>
                        <p class="mt-0.5 text-xs text-slate-500"><?= htmlspecialchars($tammucSections['means_text'][1], ENT_QUOTES, 'UTF-8') ?></p>
                        <textarea id="pv-means_text" name="means_text" rows="3" maxlength="4000" class="mt-1.5 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"></textarea>
                    </div>
                    <div>
                        <label class="block text-xs font-bold uppercase tracking-wide text-slate-600" for="pv-urgency">Urgence</label>
                        <p class="mt-0.5 text-xs text-slate-500">Indiquez si une décision doit être prise tout de suite ou peut attendre.</p>
                        <select id="pv-urgency" name="urgency" class="mt-1.5 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                            <option value="">Non précisé</option>
                            <option value="immediate">Urgence immédiate</option>
                            <option value="deferred">Urgence différée</option>
                        </select>
                    </div>
                </div>

                <?php [$cadreLabel, $cadreHint] = $tammucSections['engagement_frame_text']; ?>
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wide text-slate-600" for="pv-engagement_frame_text"><?= htmlspecialchars($cadreLabel, ENT_QUOTES, 'UTF-8') ?></label>
                    <p class="mt-0.5 text-xs text-slate-500"><?= htmlspecialchars($cadreHint, ENT_QUOTES, 'UTF-8') ?></p>
                    <textarea id="pv-engagement_frame_text" name="engagement_frame_text" rows="3" maxlength="4000" class="mt-1.5 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"></textarea>
                </div>
            </div>

            <div>
                <label class="block text-xs font-bold uppercase tracking-wide text-slate-600" for="pv-attachments">Captures d’écran (max 6)</label>
                <input type="file" id="pv-attachments" name="attachments[]" accept="image/jpeg,image/png,image/webp" multiple class="mt-1 w-full text-xs">
            </div>

            <button type="submit" class="rounded-lg bg-emerald-600 px-4 py-2.5 text-xs font-bold uppercase tracking-wide text-white hover:bg-emerald-500">Publier dans le fil</button>
        </form>
    </section>
    <?php elseif (!$isOpen): ?>
    <p class="text-center text-xs text-slate-500">Session fermée — lecture seule.</p>
    <?php endif; ?>
</div>
