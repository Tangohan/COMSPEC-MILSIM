<?php
declare(strict_types=1);

/** @var array<string, mixed> $msgThread */
/** @var list<array<string, mixed>> $msgMessages */
$thread = $msgThread ?? [];
$messages = $msgMessages ?? [];
$threadId = (int) ($thread['id'] ?? 0);
$err = \App\Core\Session::getFlash('error');
?>
<div class="max-w-3xl mx-auto px-4 py-10">
    <?php if ($err): ?><p class="text-red-600 text-sm mb-4"><?= htmlspecialchars($err) ?></p><?php endif; ?>

    <p class="mb-2"><a href="<?= htmlspecialchars(url('messages')) ?>" class="text-sm text-emerald-700 underline">← Messagerie</a></p>
    <h1 class="text-xl font-black text-slate-900 mb-6"><?= htmlspecialchars((string) ($thread['subject'] ?? 'Conversation')) ?></h1>

    <div class="space-y-4 mb-8">
        <?php foreach ($messages as $m): ?>
            <?php
            $body = (string) ($m['body'] ?? '');
            $name = trim((string) ($m['display_name'] ?? ''));
            if ($name === '') {
                $name = (string) ($m['email'] ?? 'Utilisateur');
            }
            $when = (string) ($m['created_at'] ?? '');
            $dt = $when !== '' && strtotime($when) ? date('d/m/Y H:i', strtotime($when)) : $when;
            ?>
            <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                <p class="text-xs font-bold text-slate-500 mb-2"><?= htmlspecialchars($name) ?> · <?= htmlspecialchars($dt) ?></p>
                <div class="text-sm text-slate-800 whitespace-pre-wrap"><?= htmlspecialchars($body) ?></div>
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
