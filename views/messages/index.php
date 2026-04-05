<?php
declare(strict_types=1);

/** @var list<array<string, mixed>> $msgThreads */
$threads = $msgThreads ?? [];
$err = \App\Core\Session::getFlash('error');
$ok = \App\Core\Session::getFlash('success');
?>
<div class="max-w-3xl mx-auto px-4 py-10">
    <h1 class="text-2xl font-black text-slate-900 mb-2">Messagerie</h1>
    <p class="text-sm text-slate-600 mb-6">Conversations avec l’équipe de votre communauté active.</p>

    <?php if ($err): ?><p class="text-red-600 text-sm mb-4"><?= htmlspecialchars($err) ?></p><?php endif; ?>
    <?php if ($ok): ?><p class="text-emerald-700 text-sm mb-4"><?= htmlspecialchars($ok) ?></p><?php endif; ?>

    <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm mb-8">
        <h2 class="text-sm font-black uppercase tracking-widest text-slate-900 mb-4">Nouveau message</h2>
        <form method="post" action="<?= htmlspecialchars(url('messages')) ?>" class="space-y-3">
            <?= \App\Core\Csrf::field() ?>
            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">Sujet (optionnel)</label>
                <input type="text" name="subject" maxlength="255" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" placeholder="Contact équipe">
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">Message</label>
                <textarea name="body" rows="4" required class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" placeholder="Votre message…"></textarea>
            </div>
            <button type="submit" class="rounded-xl bg-slate-900 px-4 py-2 text-xs font-bold uppercase tracking-wider text-white hover:bg-emerald-700">Envoyer</button>
        </form>
    </section>

    <h2 class="text-sm font-black uppercase tracking-widest text-slate-500 mb-3">Fils</h2>
    <?php if ($threads === []): ?>
        <p class="text-sm text-slate-500 rounded-xl border border-dashed border-slate-200 bg-slate-50 px-4 py-8 text-center">Aucune conversation pour l’instant.</p>
    <?php else: ?>
        <ul class="space-y-2">
            <?php foreach ($threads as $t): ?>
                <?php
                $id = (int) ($t['id'] ?? 0);
                $subj = (string) ($t['subject'] ?? 'Conversation');
                $preview = trim((string) ($t['last_preview'] ?? ''));
                if ($preview !== '' && function_exists('mb_strlen') && mb_strlen($preview) > 140) {
                    $preview = mb_substr($preview, 0, 137) . '…';
                } elseif ($preview !== '' && strlen($preview) > 140) {
                    $preview = substr($preview, 0, 137) . '…';
                }
                ?>
                <li>
                    <a href="<?= htmlspecialchars(url('messages/' . $id)) ?>" class="block rounded-xl border border-slate-200 bg-white px-4 py-3 shadow-sm transition hover:border-emerald-300 hover:bg-emerald-50/40">
                        <span class="font-semibold text-slate-900"><?= htmlspecialchars($subj) ?></span>
                        <?php if ($preview !== ''): ?>
                            <span class="block mt-1 text-xs text-slate-500 line-clamp-2"><?= htmlspecialchars($preview) ?></span>
                        <?php endif; ?>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>

    <p class="mt-8"><a href="<?= htmlspecialchars(url('dashboard')) ?>" class="text-sm text-slate-600 underline">Retour tableau de bord</a></p>
</div>
