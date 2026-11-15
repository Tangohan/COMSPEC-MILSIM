<?php
declare(strict_types=1);

use App\Core\Csrf;
use App\Core\Session;
use App\Support\UtcDateTime;

$h = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
$state = (string) ($mbState ?? 'unconfigured');
$base = url('admin/system/boite-contact');
$ok = Session::getFlash('success');
$err = Session::getFlash('error');
$failedForm = Session::getFlash('mb_form');
$thread = is_array($mbThread ?? null) ? $mbThread : [];
$messages = is_array($mbMessages ?? null) ? $mbMessages : [];
$reply = is_array($mbReply ?? null) ? $mbReply : ['to' => '', 'subject' => '', 'body' => ''];
$draft = is_array($mbDraft ?? null) ? $mbDraft : null;
$showImages = !empty($mbShowImages);
$htmlView = !empty($mbHtmlView);
$tid = (int) ($thread['id'] ?? 0);
$status = (string) ($thread['status'] ?? 'inbox');
$subject = (string) ($thread['subject'] ?? '') !== '' ? (string) $thread['subject'] : '(sans objet)';
$who = trim((string) ($thread['participant_name'] ?? '')) !== '' ? (string) $thread['participant_name'] : (string) ($thread['participant_email'] ?? '');
$fmt = static function (mixed $raw): string {
    $t = UtcDateTime::timestamp($raw);

    return $t === null ? '' : date('d/m/Y H:i', $t);
};
$addrs = static function (array $list): string {
    $out = [];
    foreach ($list as $a) {
        $out[] = trim((string) $a['name']) !== '' ? $a['name'] . ' <' . $a['email'] . '>' : (string) $a['email'];
    }

    return implode(', ', $out);
};
$size = static function (int $bytes): string {
    if ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 1, ',', ' ') . ' Mo';
    }

    return max(1, (int) round($bytes / 1024)) . ' Ko';
};
$act = static function (string $action, string $label, string $class = 'pa-btn pa-btn--line pa-btn--sm') use ($h, $base, $tid): string {
    return '<form method="post" action="' . $h($base . '/fil/' . $tid . '/action') . '">' . Csrf::field()
        . '<input type="hidden" name="action" value="' . $h($action) . '"><button type="submit" class="' . $h($class) . '">' . $h($label) . '</button></form>';
};
$values = is_array($failedForm) ? $failedForm : ($draft !== null
    ? [
        'to' => implode(', ', array_map(static fn (array $a): string => (string) $a['email'], json_decode((string) ($draft['to_json'] ?? '[]'), true) ?: [])),
        'cc' => implode(', ', array_map(static fn (array $a): string => (string) $a['email'], json_decode((string) ($draft['cc_json'] ?? '[]'), true) ?: [])),
        'subject' => (string) $draft['subject'],
        'body' => (string) ($draft['body_text'] ?? ''),
    ]
    : ['to' => $reply['to'], 'cc' => '', 'subject' => $reply['subject'], 'body' => $reply['body']]);
$lastInbound = null;
foreach ($messages as $mv) {
    if ($mv['row']['direction'] === 'in') {
        $lastInbound = $mv['row'];
    }
}
?>
<div class="pa">
    <div class="pa__frame">
        <header class="pa-hero">
            <h1 class="pa-hero__title" translate="no"><?= $h($subject) ?></h1>
            <p class="pa-hero__lead">
                Échange avec <span translate="no"><?= $h($who) ?></span><?= ($thread['participant_email'] ?? '') !== '' && $who !== ($thread['participant_email'] ?? '') ? ' (' . $h((string) $thread['participant_email']) . ')' : '' ?>.
            </p>
            <div class="pa-hero__actions">
                <a class="pa-btn pa-btn--ghost" href="<?= $h($base) ?>">Retour à la boîte</a>
            </div>
        </header>

        <div class="pa-panel">
            <?php if ($ok): ?>
                <p class="pa-flash pa-flash--ok"><?= $h((string) $ok) ?></p>
            <?php endif; ?>
            <?php if ($err): ?>
                <p class="pa-flash pa-flash--err"><?= $h((string) $err) ?></p>
            <?php endif; ?>

            <?php if ($state !== 'ready'): ?>
                <section class="pa-card">
                    <h2 class="pa-card__title">Boîte non disponible</h2>
                    <p class="pa-card__help">La boîte de contact n’est pas configurée ou sa base n’est pas prête. Ouvrez la page principale de la boîte pour le détail.</p>
                    <div class="pa-actions"><a class="pa-btn pa-btn--line" href="<?= $h($base) ?>">Boîte de contact</a></div>
                </section>
            <?php else: ?>
                <div class="cm-toolbar">
                    <?php if ((int) ($thread['unread_count'] ?? 0) === 0): ?>
                        <?= $act('unread', 'Marquer non lu') ?>
                    <?php endif; ?>
                    <?= (int) ($thread['starred'] ?? 0) === 1 ? $act('unstar', 'Retirer l’étoile') : $act('star', 'Mettre une étoile') ?>
                    <?= (int) ($thread['todo'] ?? 0) === 1 ? $act('done', 'Marquer traité') : $act('todo', 'À traiter') ?>
                    <?php if ($status === 'archived' || $status === 'spam' || $status === 'trash'): ?>
                        <?= $act('inbox', $status === 'spam' ? 'Ce n’est pas du spam' : 'Remettre dans la boîte') ?>
                    <?php endif; ?>
                    <?php if ($status !== 'archived'): ?><?= $act('archive', 'Archiver') ?><?php endif; ?>
                    <?php if ($status !== 'spam'): ?><?= $act('spam', 'Spam') ?><?php endif; ?>
                    <?php if ($status !== 'trash'): ?><?= $act('trash', 'Mettre à la corbeille') ?><?php endif; ?>
                </div>
                <?php if ($status === 'trash'): ?>
                    <details class="cm-purge">
                        <summary>Supprimer définitivement ce fil</summary>
                        <p class="cm-hint">Le fil, ses messages et ses pièces jointes sont effacés du site. Les messages restent dans la messagerie du serveur. Cette action est irréversible.</p>
                        <?= $act('purge', 'Confirmer la suppression définitive', 'pa-btn pa-btn--danger pa-btn--sm') ?>
                    </details>
                <?php endif; ?>

                <?php foreach ($messages as $mv): ?>
                    <?php
                    $m = $mv['row'];
                    $out = $m['direction'] === 'out';
                    $from = trim((string) $m['from_name']) !== '' ? $m['from_name'] . ' <' . $m['from_email'] . '>' : (string) $m['from_email'];
                    $query = ($showImages ? '&images=1' : '');
                    ?>
                    <article class="cm-msg<?= $out ? ' cm-msg--out' : '' ?>">
                        <div class="cm-msg__head">
                            <div>
                                <p class="cm-msg__from" translate="no"><?= $h($from !== '' ? $from : '—') ?></p>
                                <p class="cm-msg__to" translate="no">
                                    À : <?= $h($addrs($mv['to'])) ?>
                                    <?= $mv['cc'] !== [] ? ' · Copie : ' . $h($addrs($mv['cc'])) : '' ?>
                                </p>
                            </div>
                            <p class="cm-msg__date">
                                <?= $out ? '<span class="pa-pill pa-pill--mint">Envoyé</span>' : '' ?>
                                <?= (int) $m['auto_reply'] === 1 ? '<span class="pa-pill pa-pill--slate">Message automatique</span>' : '' ?>
                                <?= (string) $m['source'] === 'form' ? '<span class="pa-pill pa-pill--blue">Formulaire du site, adresse non vérifiée</span>' : '' ?>
                                <?= $h($fmt($m['sent_at'])) ?>
                            </p>
                        </div>
                        <?php if ($mv['has_html']): ?>
                            <div class="cm-msg__tools">
                                <?php if ($mv['as_html']): ?>
                                    <a href="<?= $h($base . '/fil/' . $tid) ?>">Voir en texte</a>
                                    <?php if (!$showImages): ?>
                                        <a href="<?= $h($base . '/fil/' . $tid . '?vue=html&images=1') ?>">Afficher les images</a>
                                    <?php else: ?>
                                        <a href="<?= $h($base . '/fil/' . $tid . '?vue=html') ?>">Masquer les images</a>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <a href="<?= $h($base . '/fil/' . $tid . '?vue=html') ?>">Voir la version HTML</a>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                        <div class="cm-body<?= $mv['as_html'] ? '' : ' cm-body--text' ?>" translate="no"><?= $mv['safe'] ?></div>
                        <?php if ($mv['files'] !== []): ?>
                            <ul class="cm-files" aria-label="Pièces jointes">
                                <?php foreach ($mv['files'] as $f): ?>
                                    <li class="cm-file" translate="no">
                                        <?php if ($f['storage_path'] !== null): ?>
                                            <a href="<?= $h($base . '/piece/' . (int) $f['id']) ?>"><?= $h((string) $f['filename']) ?></a>
                                        <?php else: ?>
                                            <?= $h((string) $f['filename']) ?>
                                        <?php endif; ?>
                                        <span><?= $h($size((int) $f['size'])) ?></span>
                                        <?php if ($f['storage_path'] === null): ?><span>(non conservée : trop volumineuse)</span><?php endif; ?>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </article>
                <?php endforeach; ?>

                <?php if ($lastInbound !== null && (int) $lastInbound['auto_reply'] === 1): ?>
                    <p class="pa-flash pa-flash--warn">Le dernier message est automatique : une réponse n’est sans doute pas lue.</p>
                <?php endif; ?>
                <?php if ($status !== 'trash' && $status !== 'spam'): ?>
                    <?php
                    $cmBase = $base;
                    $cmThreadId = $tid;
                    $cmReplyTo = (int) ($mbReplyTarget ?? 0);
                    $cmDraftId = $draft !== null ? (int) $draft['id'] : 0;
                    $cmValues = $values;
                    $cmLimits = $mbUploadLimits ?? [];
                    $cmFormId = 'cm-reply';
                    require __DIR__ . '/_contact_mailbox_form.php';
                    ?>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>
