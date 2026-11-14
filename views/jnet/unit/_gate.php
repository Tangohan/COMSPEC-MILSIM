<?php
declare(strict_types=1);
/**
 * Prise en compte obligatoire : ordres non lus qu'il faut accuser avant d'utiliser l'espace, quand le chef a activé le
 * réglage « Prise en compte obligatoire ». Sans JavaScript, la boîte est affichée en ligne, en haut de la page, avec un
 * bouton par ordre (même accusé de lecture que partout dans JNET). Avec JavaScript, jnet_unit_workspace.js la passe en
 * fenêtre bloquante : Échap ne la ferme pas, seul « J'ai pris connaissance » l'enregistre puis la ferme.
 *
 * @var list<array<string, mixed>> $usGate ordres décorés (id, title, body, from, author, when, link)
 * @var string $base
 * @var string $exBack
 */
$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$gate = is_array($usGate ?? null) ? $usGate : [];
if ($gate === []) {
    return;
}
$n = count($gate);
?>
<dialog class="uw-gate" data-uw-gate open aria-labelledby="uw-gate-title" aria-describedby="uw-gate-desc">
    <div class="uw-gate__box">
        <h2 id="uw-gate-title">Information importante</h2>
        <p id="uw-gate-desc"><?= $n > 1 ? $n . ' ordres attendent' : 'Un ordre attend' ?> votre prise en compte. Lisez-<?= $n > 1 ? 'les' : 'le' ?>, puis confirmez.</p>
        <ol class="uw-gate__list">
            <?php foreach ($gate as $e): ?>
                <?php $from = (array) ($e['from'] ?? []); $body = trim((string) ($e['body'] ?? '')); ?>
                <li class="uw-gate__item" data-uw-gate-item>
                    <h3><?= $h((string) $e['title']) ?></h3>
                    <p class="uw-gate__meta"><?= $h((string) ($from['label'] ?? '')) ?> · <?= $h((string) ($e['author'] ?? '')) ?> · <?= $h((string) ($e['when'] ?? '')) ?></p>
                    <?php if ($body !== ''): ?><p class="uw-gate__body"><?= nl2br($h($body)) ?></p><?php endif; ?>
                    <?php if ((string) ($e['link'] ?? '') !== ''): ?><p><a class="uw-link" href="<?= $h(url((string) $e['link'])) ?>">Ouvrir le lien joint</a></p><?php endif; ?>
                    <form method="post" action="<?= $h(url('jnet/echanges/' . (int) $e['id'] . '/lu')) ?>" data-uw-gate-form>
                        <?= \App\Core\Csrf::field() ?>
                        <input type="hidden" name="back" value="<?= $h((string) ($exBack ?? $base . '?direct=1')) ?>">
                        <button class="ja-b1" type="submit">J’ai pris connaissance</button>
                    </form>
                </li>
            <?php endforeach; ?>
        </ol>
    </div>
</dialog>
