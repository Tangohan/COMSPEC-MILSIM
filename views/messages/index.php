<?php
declare(strict_types=1);

/** @var list<array<string, mixed>> $msgThreads */
$threads = $msgThreads ?? [];
$recipientsOk = (bool) ($msgRecipientsConfigured ?? true);
$err = \App\Core\Session::getFlash('error');
$ok = \App\Core\Session::getFlash('success');
$unreadCount = count(array_filter($threads, static fn (array $t): bool => !empty($t['has_unread'])));
?>
<div class="max-w-3xl mx-auto px-4 py-10">
    <div class="relative overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm mb-8">
        <div class="absolute inset-x-0 top-0 h-1 bg-gradient-to-r from-emerald-500 via-emerald-300 to-transparent" aria-hidden="true"></div>
        <div class="p-6 sm:p-7">
            <p class="text-[10px] font-black uppercase tracking-[0.28em] text-emerald-700 mb-2">Communauté</p>
            <h1 class="text-2xl font-black tracking-tight text-slate-900 mb-2">Messagerie interne</h1>
            <p class="text-sm text-slate-600 leading-relaxed max-w-xl">
                Écrire à l’encadrement et aux rôles habilités de votre communauté active. Les réponses s’affichent dans la même conversation.
            </p>
            <?php if ($threads !== []): ?>
            <div class="mt-4 flex flex-wrap gap-2 text-xs">
                <span class="inline-flex items-center rounded-full border border-slate-200 bg-slate-50 px-3 py-1 font-semibold text-slate-600"><?= count($threads) ?> conversation<?= count($threads) > 1 ? 's' : '' ?></span>
                <?php if ($unreadCount > 0): ?>
                <span class="inline-flex items-center rounded-full border border-emerald-200 bg-emerald-50 px-3 py-1 font-bold text-emerald-800"><?= $unreadCount ?> à lire</span>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($err): ?><div class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-medium text-rose-900 mb-4"><?= htmlspecialchars($err) ?></div><?php endif; ?>
    <?php if ($ok): ?><div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-900 mb-4"><?= htmlspecialchars($ok) ?></div><?php endif; ?>

    <?php if (!$recipientsOk): ?>
        <div class="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-950 mb-8">
            Aucun destinataire n’est configuré pour recevoir les messages internes sur cette communauté. Préférez le forum ou contactez un responsable pour qu’un rôle habilité soit désigné.
        </div>
    <?php endif; ?>

    <section class="mb-8">
        <h2 class="text-[11px] font-black uppercase tracking-[0.2em] text-slate-500 mb-3">Conversations</h2>
        <?php if ($threads === []): ?>
            <div class="rounded-2xl border border-dashed border-slate-300 bg-slate-50/60 px-6 py-12 text-center">
                <div class="mx-auto mb-3 flex h-11 w-11 items-center justify-center rounded-xl border border-slate-200 bg-white text-slate-400" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" class="h-5 w-5"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5Z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                </div>
                <p class="text-sm font-semibold text-slate-700">Aucune conversation pour l’instant</p>
                <p class="mt-1 text-xs text-slate-500">Ouvrez une nouvelle demande ci-dessous lorsque vous en avez besoin.</p>
            </div>
        <?php else: ?>
            <ul class="space-y-2">
                <?php foreach ($threads as $t): ?>
                    <?php
                    $id = (int) ($t['id'] ?? 0);
                    $subj = (string) ($t['subject'] ?? 'Échange avec l’encadrement');
                    $preview = trim((string) ($t['last_preview'] ?? ''));
                    $hasUnread = !empty($t['has_unread']);
                    $initial = $subj !== '' ? mb_strtoupper(mb_substr($subj, 0, 1)) : '#';
                    if ($preview !== '' && function_exists('mb_strlen') && mb_strlen($preview) > 140) {
                        $preview = mb_substr($preview, 0, 137) . '…';
                    } elseif ($preview !== '' && strlen($preview) > 140) {
                        $preview = substr($preview, 0, 137) . '…';
                    }
                    ?>
                    <li>
                        <a href="<?= htmlspecialchars(url('messages/' . $id)) ?>" class="group flex items-start gap-3 rounded-xl border <?= $hasUnread ? 'border-emerald-200 bg-emerald-50/40' : 'border-slate-200 bg-white' ?> px-4 py-3 shadow-sm transition hover:border-emerald-300 hover:bg-emerald-50/60">
                            <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg <?= $hasUnread ? 'bg-emerald-600 text-white' : 'bg-slate-100 text-slate-500' ?> text-xs font-black"><?= htmlspecialchars($initial, ENT_QUOTES, 'UTF-8') ?></div>
                            <div class="min-w-0 flex-1">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="font-semibold text-slate-900 truncate"><?= htmlspecialchars($subj) ?></span>
                                    <?php if ($hasUnread): ?>
                                        <span class="shrink-0 rounded-full bg-emerald-100 px-2 py-0.5 text-[10px] font-black uppercase tracking-wider text-emerald-800">À lire</span>
                                    <?php endif; ?>
                                </div>
                                <?php if ($preview !== ''): ?>
                                    <span class="block mt-0.5 text-xs text-slate-500 line-clamp-2"><?= htmlspecialchars($preview) ?></span>
                                <?php endif; ?>
                            </div>
                            <svg viewBox="0 0 20 20" fill="none" class="mt-1.5 h-4 w-4 shrink-0 text-slate-300 transition group-hover:text-emerald-600" aria-hidden="true"><path d="M7.5 4.5 13 10l-5.5 5.5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>

    <section class="rounded-2xl border border-slate-200 bg-white shadow-sm overflow-hidden">
        <details class="group">
            <summary class="cursor-pointer list-none px-6 py-4 flex items-center justify-between gap-3 bg-slate-50/80 hover:bg-slate-50 border-b border-slate-100 [&::-webkit-details-marker]:hidden">
                <span class="inline-flex items-center gap-2 text-sm font-black uppercase tracking-widest text-slate-800">
                    <svg viewBox="0 0 20 20" fill="none" class="h-4 w-4 text-emerald-600" aria-hidden="true"><path d="M10 4v12M4 10h12" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
                    Nouvelle demande à l’encadrement
                </span>
                <span class="text-slate-400 text-xs font-semibold group-open:hidden">Afficher le formulaire</span>
                <span class="text-emerald-700 text-xs font-semibold hidden group-open:inline">Masquer</span>
            </summary>
            <div class="p-6 pt-4">
                <p class="text-xs text-slate-600 mb-4">
                    Chaque envoi avec un <strong>objet renseigné</strong> ouvre une nouvelle conversation. Sans objet, votre texte peut compléter une demande récente encore sans réponse, pour éviter les doublons.
                </p>
                <?php if ($recipientsOk): ?>
                    <form method="post" action="<?= htmlspecialchars(url('messages')) ?>" class="space-y-3">
                        <?= \App\Core\Csrf::field() ?>
                        <div>
                            <label class="block text-xs font-bold text-slate-700 mb-1">Objet (optionnel)</label>
                            <input type="text" name="subject" maxlength="255" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-emerald-400 focus:outline-none focus:ring-2 focus:ring-emerald-100" placeholder="Ex. Question logistique pour l’opération du week-end">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-700 mb-1">Votre message</label>
                            <textarea name="body" rows="4" required class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-emerald-400 focus:outline-none focus:ring-2 focus:ring-emerald-100" placeholder="Expliquez votre demande de façon claire…"></textarea>
                        </div>
                        <button type="submit" class="rounded-xl bg-slate-900 px-4 py-2.5 text-xs font-bold uppercase tracking-wider text-white transition hover:bg-emerald-700">Envoyer</button>
                    </form>
                <?php else: ?>
                    <p class="text-sm text-slate-500">L’envoi est désactivé tant qu’aucun destinataire n’est disponible.</p>
                <?php endif; ?>
            </div>
        </details>
    </section>

    <p class="mt-8"><a href="<?= htmlspecialchars(url('dashboard')) ?>" class="inline-flex items-center gap-1.5 text-sm font-semibold text-slate-600 underline decoration-slate-300 underline-offset-2 hover:text-emerald-800 hover:decoration-emerald-600">← Retour tableau de bord</a></p>
</div>
