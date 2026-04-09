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
    <?php if ($err): ?><p class="text-red-600 text-sm mb-4"><?= htmlspecialchars($err) ?></p><?php endif; ?>
    <?php if ($ok): ?><p class="text-emerald-700 text-sm mb-4"><?= htmlspecialchars($ok) ?></p><?php endif; ?>

    <p class="mb-2"><a href="<?= htmlspecialchars(url('messages')) ?>" class="text-sm text-emerald-700 underline">← Messagerie interne</a></p>
    <h1 class="text-xl font-black text-slate-900 mb-2"><?= htmlspecialchars((string) ($thread['subject'] ?? 'Conversation')) ?></h1>
    <p class="text-xs text-slate-500 mb-6">Les personnes habilitées sur votre communauté peuvent lire et répondre dans cet échange.</p>

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

    <section class="rounded-2xl border border-slate-200 bg-slate-50 p-6">
        <h2 class="text-xs font-black uppercase tracking-widest text-slate-700 mb-3">Répondre</h2>
        <form method="post" action="<?= htmlspecialchars(url('messages/' . $threadId . '/reply')) ?>" class="space-y-3">
            <?= \App\Core\Csrf::field() ?>
            <textarea name="body" rows="4" required class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm bg-white" placeholder="Votre réponse…"></textarea>
            <button type="submit" class="rounded-xl bg-slate-900 px-4 py-2 text-xs font-bold uppercase tracking-wider text-white hover:bg-emerald-700">Envoyer</button>
        </form>
    </section>
</div>
