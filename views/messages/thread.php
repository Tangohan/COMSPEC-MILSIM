<?php
declare(strict_types=1);

/** @var array<string, mixed> $msgThread */
/** @var list<array<string, mixed>> $msgMessages */
/** @var int $msgCurrentUserId */
$thread = $msgThread ?? [];
$messages = $msgMessages ?? [];
$currentUid = (int) ($msgCurrentUserId ?? 0);
$threadId = (int) ($thread['id'] ?? 0);
$err = \App\Core\Session::getFlash('error');
$ok = \App\Core\Session::getFlash('success');
?>
<div class="max-w-3xl mx-auto px-4 py-10">
    <a href="<?= htmlspecialchars(url('messages')) ?>" class="inline-flex items-center gap-1.5 text-sm font-semibold text-slate-600 mb-4 hover:text-emerald-800">
        <svg viewBox="0 0 20 20" fill="none" class="h-4 w-4" aria-hidden="true"><path d="M12.5 4.5 6.5 10l6 5.5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
        Messagerie interne
    </a>

    <div class="relative overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm mb-6">
        <div class="absolute inset-x-0 top-0 h-1 bg-gradient-to-r from-emerald-500 via-emerald-300 to-transparent" aria-hidden="true"></div>
        <div class="p-5 sm:p-6">
            <h1 class="text-xl font-black tracking-tight text-slate-900 mb-1"><?= htmlspecialchars((string) ($thread['subject'] ?? 'Conversation')) ?></h1>
            <p class="text-xs text-slate-500">Les personnes habilitées sur votre communauté peuvent lire et répondre dans cet échange.</p>
        </div>
    </div>

    <?php if ($err): ?><div class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-medium text-rose-900 mb-4"><?= htmlspecialchars($err) ?></div><?php endif; ?>
    <?php if ($ok): ?><div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-900 mb-4"><?= htmlspecialchars($ok) ?></div><?php endif; ?>

    <div class="space-y-3 mb-8">
        <?php foreach ($messages as $m): ?>
            <?php
            $body = (string) ($m['body'] ?? '');
            $senderId = (int) ($m['sender_user_id'] ?? 0);
            $isMine = $currentUid > 0 && $senderId === $currentUid;
            $name = trim((string) ($m['display_name'] ?? ''));
            if ($name === '') {
                $name = (string) ($m['email'] ?? 'Participant');
            }
            if ($isMine) {
                $name = 'Vous';
            }
            $when = (string) ($m['created_at'] ?? '');
            $dt = $when !== '' && strtotime($when) ? date('d/m/Y H:i', strtotime($when)) : $when;
            ?>
            <div class="flex <?= $isMine ? 'justify-end' : 'justify-start' ?>">
                <div class="max-w-[min(100%,32rem)] rounded-2xl px-4 py-3 shadow-sm border <?= $isMine
                    ? 'bg-emerald-900 border-emerald-800 text-white'
                    : 'bg-white border-slate-200 text-slate-800' ?>">
                    <p class="text-[11px] font-bold mb-1.5 <?= $isMine ? 'text-emerald-100' : 'text-slate-500' ?>">
                        <?= htmlspecialchars($name) ?> · <?= htmlspecialchars($dt) ?>
                    </p>
                    <div class="text-sm whitespace-pre-wrap <?= $isMine ? 'text-emerald-50' : 'text-slate-800' ?>"><?= htmlspecialchars($body) ?></div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <section class="rounded-2xl border border-slate-200 bg-white shadow-sm p-6">
        <h2 class="text-[11px] font-black uppercase tracking-[0.2em] text-slate-500 mb-3">Répondre</h2>
        <form method="post" action="<?= htmlspecialchars(url('messages/' . $threadId . '/reply')) ?>" class="space-y-3">
            <?= \App\Core\Csrf::field() ?>
            <textarea name="body" rows="4" required class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm bg-white focus:border-emerald-400 focus:outline-none focus:ring-2 focus:ring-emerald-100" placeholder="Votre réponse…"></textarea>
            <button type="submit" class="rounded-xl bg-slate-900 px-4 py-2.5 text-xs font-bold uppercase tracking-wider text-white transition hover:bg-emerald-700">Envoyer</button>
        </form>
    </section>
</div>
