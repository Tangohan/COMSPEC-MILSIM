<?php
declare(strict_types=1);
$row = $cooperationTplRow ?? [];
$ek = (string) ($cooperationTplEventKey ?? '');
$ch = (string) ($cooperationTplChannel ?? '');
$labels = $cooperationPlaceholderLabels ?? [];
$hasLocal = !empty($cooperationTplHasLocal);
$csrf = $csrfToken ?? \App\Core\Csrf::token();
$subjectVal = trim((string) ($row['subject'] ?? ''));
$bodyVal = (string) ($row['body'] ?? '');
$minH = (int) ($row['min_interval_hours'] ?? 24);
$active = !empty($row['is_active']);
$forumTopicId = 0;
$forumDraft = false;
if ($ch === 'forum') {
    $fj = $row['forum_settings_json'] ?? null;
    if (is_string($fj) && $fj !== '') {
        $d = json_decode($fj, true);
        if (is_array($d)) {
            $forumTopicId = (int) ($d['topic_id'] ?? 0);
            $forumDraft = !empty($d['as_draft']);
        }
    }
}
?>
<div class="max-w-3xl mx-auto px-6 py-10">
    <a href="<?= url('back-office/cooperation/announcements') ?>" class="text-sm font-medium text-slate-600 hover:text-slate-900 underline">← Retour</a>
    <h1 class="mt-4 text-2xl font-black text-slate-900">Personnaliser le message</h1>
    <p class="mt-2 text-sm text-slate-600">Les valeurs affichées tiennent compte des réglages du site lorsque vous n’avez pas encore enregistré de version locale.</p>
    <?php $e = \App\Core\Session::getFlash('error'); ?>
    <?php if ($e): ?><p class="text-red-600 text-sm mt-4"><?= htmlspecialchars($e, ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>

    <?php if ($hasLocal): ?>
    <form method="post" action="<?= url('back-office/cooperation/announcements/revert') ?>" class="mt-4 inline" onsubmit="return confirm('Revenir aux textes par défaut du site pour ce canal ?');">
        <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
        <input type="hidden" name="event_key" value="<?= htmlspecialchars($ek, ENT_QUOTES, 'UTF-8') ?>">
        <input type="hidden" name="channel" value="<?= htmlspecialchars($ch, ENT_QUOTES, 'UTF-8') ?>">
        <button type="submit" class="text-sm font-semibold text-rose-700 hover:underline">Retirer la personnalisation locale</button>
    </form>
    <?php endif; ?>

    <div class="mt-6 rounded-lg border border-slate-200 bg-slate-50 p-4 text-sm text-slate-700">
        <p class="font-semibold text-slate-900 mb-2">Éléments réutilisables dans le texte</p>
        <ul class="list-disc pl-5 space-y-1">
            <?php foreach ($labels as $key => $lab): ?>
                <li><code class="text-xs bg-white px-1 rounded border"><?= htmlspecialchars('{' . $key . '}', ENT_QUOTES, 'UTF-8') ?></code> — <?= htmlspecialchars($lab, ENT_QUOTES, 'UTF-8') ?></li>
            <?php endforeach; ?>
        </ul>
    </div>

    <form method="post" action="<?= url('back-office/cooperation/announcements/save') ?>" class="mt-8 space-y-5 rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
        <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
        <input type="hidden" name="event_key" value="<?= htmlspecialchars($ek, ENT_QUOTES, 'UTF-8') ?>">
        <input type="hidden" name="channel" value="<?= htmlspecialchars($ch, ENT_QUOTES, 'UTF-8') ?>">

        <?php if ($ch === 'email'): ?>
        <div>
            <label for="subject" class="block text-xs font-bold uppercase tracking-wider text-slate-500 mb-1">Objet du courriel</label>
            <input type="text" id="subject" name="subject" required maxlength="255" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" value="<?= htmlspecialchars($subjectVal, ENT_QUOTES, 'UTF-8') ?>">
        </div>
        <?php elseif ($ch === 'in_app'): ?>
        <div>
            <label for="subject" class="block text-xs font-bold uppercase tracking-wider text-slate-500 mb-1">Titre court (facultatif)</label>
            <input type="text" id="subject" name="subject" maxlength="255" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" value="<?= htmlspecialchars($subjectVal, ENT_QUOTES, 'UTF-8') ?>">
        </div>
        <?php endif; ?>

        <div>
            <label for="body" class="block text-xs font-bold uppercase tracking-wider text-slate-500 mb-1"><?= $ch === 'forum' ? 'Texte du message' : 'Corps du message' ?></label>
            <textarea id="body" name="body" rows="10" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm font-mono text-xs"><?= htmlspecialchars($bodyVal, ENT_QUOTES, 'UTF-8') ?></textarea>
        </div>

        <?php if ($ch === 'forum'): ?>
        <div>
            <label for="forum_topic_id" class="block text-xs font-bold uppercase tracking-wider text-slate-500 mb-1">Numéro du sujet forum (optionnel)</label>
            <input type="number" id="forum_topic_id" name="forum_topic_id" min="0" class="w-full max-w-xs rounded-lg border border-slate-200 px-3 py-2 text-sm" value="<?= (int) $forumTopicId ?>">
            <p class="text-xs text-slate-500 mt-1">Si vous ne renseignez pas ce champ, une publication peut tout de même être proposée sur le fil commun du dossier lorsqu’il existe.</p>
        </div>
        <div class="flex items-center gap-2">
            <input type="checkbox" id="forum_as_draft" name="forum_as_draft" value="1" class="rounded border-slate-300"<?= $forumDraft ? ' checked' : '' ?>>
            <label for="forum_as_draft" class="text-sm text-slate-700">Enregistrer comme brouillon</label>
        </div>
        <?php endif; ?>

        <div>
            <label for="min_interval_hours" class="block text-xs font-bold uppercase tracking-wider text-slate-500 mb-1">Délai minimum entre deux publications forum (heures)</label>
            <input type="number" id="min_interval_hours" name="min_interval_hours" min="0" max="168" class="w-40 rounded-lg border border-slate-200 px-3 py-2 text-sm" value="<?= (int) $minH ?>">
        </div>

        <div class="flex items-center gap-2">
            <input type="checkbox" id="is_active" name="is_active" value="1" class="rounded border-slate-300"<?= $active ? ' checked' : '' ?>>
            <label for="is_active" class="text-sm text-slate-700">Activer ce gabarit pour cette communauté (après fusion avec les défauts du site)</label>
        </div>

        <button type="submit" class="rounded-xl bg-slate-900 px-5 py-2.5 text-sm font-semibold text-white hover:bg-slate-800">Enregistrer pour cette communauté</button>
    </form>
</div>
