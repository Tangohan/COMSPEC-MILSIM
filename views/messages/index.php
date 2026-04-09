<?php
declare(strict_types=1);

/** @var list<array<string, mixed>> $msgThreads */
$threads = $msgThreads ?? [];
$recipientsOk = (bool) ($msgRecipientsConfigured ?? true);
$err = \App\Core\Session::getFlash('error');
$ok = \App\Core\Session::getFlash('success');
?>
<div class="max-w-3xl mx-auto px-4 py-10">
    <h1 class="text-2xl font-black text-slate-900 mb-2">Messagerie interne</h1>
    <p class="text-sm text-slate-600 mb-6">
        Écrire à l’encadrement et aux rôles habilités de votre communauté active. Les réponses s’affichent dans la même conversation.
    </p>

    <?php if ($err): ?><p class="text-red-600 text-sm mb-4"><?= htmlspecialchars($err) ?></p><?php endif; ?>
    <?php if ($ok): ?><p class="text-emerald-700 text-sm mb-4"><?= htmlspecialchars($ok) ?></p><?php endif; ?>

    <?php if (!$recipientsOk): ?>
        <div class="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-950 mb-8">
            Aucun destinataire n’est configuré pour recevoir les messages internes sur cette communauté. Préférez le forum ou contactez un responsable pour qu’un rôle habilité soit désigné.
        </div>
    <?php endif; ?>

    <section class="mb-10">
        <h2 class="text-sm font-black uppercase tracking-widest text-slate-500 mb-3">Conversations</h2>
        <?php if ($threads === []): ?>
            <p class="text-sm text-slate-500 rounded-xl border border-dashed border-slate-200 bg-slate-50 px-4 py-8 text-center">Aucune conversation pour l’instant. Ouvrez une nouvelle demande ci-dessous lorsque vous en avez besoin.</p>
        <?php else: ?>
            <ul class="space-y-2">
                <?php foreach ($threads as $t): ?>
                    <?php
                    $id = (int) ($t['id'] ?? 0);
                    $subj = (string) ($t['subject'] ?? 'Échange avec l’encadrement');
                    $preview = trim((string) ($t['last_preview'] ?? ''));
                    $hasUnread = !empty($t['has_unread']);
                    if ($preview !== '' && function_exists('mb_strlen') && mb_strlen($preview) > 140) {
                        $preview = mb_substr($preview, 0, 137) . '…';
                    } elseif ($preview !== '' && strlen($preview) > 140) {
                        $preview = substr($preview, 0, 137) . '…';
                    }
                    ?>
                    <li>
                        <a href="<?= htmlspecialchars(url('messages/' . $id)) ?>" class="block rounded-xl border border-slate-200 bg-white px-4 py-3 shadow-sm transition hover:border-emerald-300 hover:bg-emerald-50/40">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="font-semibold text-slate-900"><?= htmlspecialchars($subj) ?></span>
                                <?php if ($hasUnread): ?>
                                    <span class="rounded-full bg-emerald-100 px-2 py-0.5 text-[10px] font-black uppercase tracking-wider text-emerald-800">À lire</span>
                                <?php endif; ?>
                            </div>
                            <?php if ($preview !== ''): ?>
                                <span class="block mt-1 text-xs text-slate-500 line-clamp-2"><?= htmlspecialchars($preview) ?></span>
                            <?php endif; ?>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>

    <section class="rounded-2xl border border-slate-200 bg-white p-0 shadow-sm overflow-hidden">
        <details class="group">
            <summary class="cursor-pointer list-none px-6 py-4 flex items-center justify-between gap-3 bg-slate-50/80 hover:bg-slate-50 border-b border-slate-100 [&::-webkit-details-marker]:hidden">
                <span class="text-sm font-black uppercase tracking-widest text-slate-800">Nouvelle demande à l’encadrement</span>
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
                            <input type="text" name="subject" maxlength="255" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" placeholder="Ex. Question logistique pour l’opération du week-end">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-700 mb-1">Votre message</label>
                            <textarea name="body" rows="4" required class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" placeholder="Expliquez votre demande de façon claire…"></textarea>
                        </div>
                        <button type="submit" class="rounded-xl bg-slate-900 px-4 py-2 text-xs font-bold uppercase tracking-wider text-white hover:bg-emerald-700">Envoyer</button>
                    </form>
                <?php else: ?>
                    <p class="text-sm text-slate-500">L’envoi est désactivé tant qu’aucun destinataire n’est disponible.</p>
                <?php endif; ?>
            </div>
        </details>
    </section>

    <p class="mt-8"><a href="<?= htmlspecialchars(url('dashboard')) ?>" class="text-sm text-slate-600 underline">Retour tableau de bord</a></p>
</div>
