<?php

declare(strict_types=1);

use App\Core\Csrf;
use App\Services\Account\PlatformBanNotice;

$h = static fn (string|int|null $value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$statusLabels = [
    'received' => 'Reçue', 'in_review' => 'En cours d’examen', 'info_requested' => 'Réponse demandée',
    'accepted' => 'Acceptée', 'upheld' => 'Maintenue', 'closed' => 'Clôturée',
];
$pill = ['received' => 'amber', 'in_review' => 'blue', 'info_requested' => 'rose', 'accepted' => 'mint', 'upheld' => 'slate', 'closed' => 'slate'];
$when = static fn (?string $utc): string => $utc ? PlatformBanNotice::dateLabel($utc) : '—';
$action = static fn (string $name): string => url($base . '/' . (int) $case['ban_id'] . '/' . $name);
$mine = (int) ($case['assigned_to'] ?? 0) === (int) $actorId;
?>
<div class="pa pu ps ac">
    <div class="pa__frame">
        <header class="pa-hero">
            <h1 class="pa-hero__title">Dossier <span class="ac-ref"><?= $h($case['reference']) ?></span></h1>
            <p class="pa-hero__lead">Décision n° <?= (int) $case['ban_id'] ?> · <?= $h($case['display_label'] ?: $case['email_norm']) ?></p>
            <div class="pa-hero__actions">
                <span class="pa-pill pa-pill--<?= $h($pill[$case['status']] ?? 'slate') ?>"><?= $h($statusLabels[$case['status']] ?? $case['status']) ?></span>
                <a class="pa-btn pa-btn--ghost" href="<?= $h(url($base)) ?>">Retour à la liste</a>
            </div>
        </header>
        <?php if ($success): ?><p class="pa-flash pa-flash--ok" role="status"><?= $h($success) ?></p><?php endif; ?>
        <?php if ($error): ?><p class="pa-flash pa-flash--err" role="alert"><?= $h($error) ?></p><?php endif; ?>

        <div class="ac-layout">
            <section class="pa-card ac-thread-card" aria-labelledby="ac-thread-title">
                <h2 class="pa-card__title" id="ac-thread-title">Fil d’échange</h2>
                <ol class="ac-thread">
                    <?php foreach ($messages as $message): ?>
                        <?php $kind = (int) $message['is_internal'] === 1 ? 'note' : ($message['author_type'] === 'user' ? 'user' : 'staff'); ?>
                        <li class="ac-msg ac-msg--<?= $kind ?>">
                            <p class="ac-msg__meta"><strong><?= $kind === 'note' ? 'Note interne (invisible pour la personne)' : ($kind === 'user' ? 'La personne' : 'Modération') ?></strong> · <time><?= $h($when($message['created_at'])) ?></time></p>
                            <p class="ac-msg__body"><?= $h($message['body']) ?></p>
                        </li>
                    <?php endforeach; ?>
                </ol>

                <?php if ($open): ?>
                <form method="post" action="<?= $h($action('reply')) ?>" class="ps-form ac-form">
                    <?= Csrf::field() ?>
                    <label class="pu-dialog__field"><span>Message visible par la personne</span>
                        <textarea name="message" rows="4" minlength="2" maxlength="4000" required></textarea></label>
                    <div class="ac-actions">
                        <button type="submit" class="pa-btn pa-btn--solid">Répondre</button>
                        <button type="submit" class="pa-btn pa-btn--line" formaction="<?= $h($action('info')) ?>">Demander des précisions</button>
                        <?php if ($platform): ?>
                            <button type="submit" class="pa-btn pa-btn--line" formaction="<?= $h($action('accept')) ?>">Accepter et lever</button>
                            <button type="submit" class="pa-btn pa-btn--line" formaction="<?= $h($action('uphold')) ?>">Maintenir</button>
                            <button type="submit" class="pa-btn pa-btn--line" formaction="<?= $h($action('close')) ?>">Clôturer</button>
                        <?php endif; ?>
                    </div>
                    <p class="pa-card__help">Accepter, maintenir et clôturer envoient ce message comme réponse finale et ferment le dossier.<?= $platform ? '' : ' Ces décisions sont réservées à l’administration de la plateforme.' ?></p>
                </form>
                <?php else: ?>
                    <p class="pa-empty-state">Ce dossier est clos : la personne ne peut plus répondre.</p>
                <?php endif; ?>

                <form method="post" action="<?= $h($action('note')) ?>" class="ps-form ac-form ac-form--note">
                    <?= Csrf::field() ?>
                    <label class="pu-dialog__field"><span>Note interne (jamais visible par la personne)</span>
                        <textarea name="message" rows="3" minlength="2" maxlength="4000" required></textarea></label>
                    <button type="submit" class="pa-btn pa-btn--line">Ajouter la note</button>
                </form>
            </section>

            <aside class="ac-side">
                <section class="pa-card" aria-labelledby="ac-ban-title">
                    <h2 class="pa-card__title" id="ac-ban-title">Sanction contestée</h2>
                    <dl class="ps-case">
                        <div><dt>Motif interne</dt><dd><?= $h($case['reason']) ?></dd></div>
                        <?php if ($case['rule_reference'] || $case['public_reason']): ?>
                            <div><dt>Avis communiqué</dt><dd><?= $h(trim((string) $case['rule_reference'] . "\n" . (string) $case['public_reason'])) ?></dd></div>
                        <?php endif; ?>
                        <div><dt>Portée</dt><dd>Toute la plateforme Athena</dd></div>
                        <div><dt>Début</dt><dd><?= $h($when($case['ban_created_at'])) ?></dd></div>
                        <div><dt>Fin</dt><dd><?= $h($case['ends_at'] ? $when($case['ends_at']) : 'Définitive') ?></dd></div>
                        <div><dt>État</dt><dd><?= $case['lifted_at'] ? 'Levée le ' . $h($when($case['lifted_at'])) : 'En cours' ?></dd></div>
                    </dl>
                </section>
                <section class="pa-card" aria-labelledby="ac-assign-title">
                    <h2 class="pa-card__title" id="ac-assign-title">Assignation</h2>
                    <p><?= (int) ($case['assigned_to'] ?? 0) > 0 ? 'Assigné à ' . $h($mine ? 'vous' : ($assigneeName ?: 'un modérateur')) : 'Non assigné' ?></p>
                    <?php if ($open): ?>
                    <form method="post" action="<?= $h($action('assign')) ?>">
                        <?= Csrf::field() ?>
                        <?php if ($mine): ?>
                            <input type="hidden" name="assignee" value="none"><button type="submit" class="pa-btn pa-btn--line">Libérer le dossier</button>
                        <?php else: ?>
                            <button type="submit" class="pa-btn pa-btn--line">Prendre en charge</button>
                        <?php endif; ?>
                    </form>
                    <?php endif; ?>
                </section>
                <section class="pa-card" aria-labelledby="ac-history-title">
                    <h2 class="pa-card__title" id="ac-history-title">Historique du compte</h2>
                    <?php if ($history === []): ?><p class="pa-empty-state">Aucune autre sanction.</p><?php endif; ?>
                    <ul class="ac-history">
                        <?php foreach ($history as $entry): ?>
                            <li><strong>n° <?= (int) $entry['id'] ?></strong> · <?= $h($when($entry['created_at'])) ?> · <?= $entry['lifted_at'] ? 'levée' : 'en cours' ?><br><span class="ac-muted"><?= $h($entry['reason']) ?></span></li>
                        <?php endforeach; ?>
                    </ul>
                </section>
            </aside>
        </div>
    </div>
</div>
