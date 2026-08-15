<?php
/** @var list<array<string,mixed>> $intents */
/** @var array<string,mixed>|null $selectedIntent */
$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$intents = is_array($intents ?? null) ? $intents : [];
$selected = is_array($selectedIntent ?? null) ? $selectedIntent : null;
$selectedId = (string) ($selected['id'] ?? '');
?>
<div class="jnet-intent-layout">
    <aside class="jnet-panel">
        <div class="jnet-panel__head"><h2>Registre</h2></div>
        <div class="jnet-panel__body">
            <?php if ($intents === []): ?>
                <p class="jnet-empty">Aucune intention publiée.</p>
            <?php else: ?>
                <?php foreach ($intents as $intent): ?>
                    <?php $iid = (string) ($intent['id'] ?? ''); ?>
                    <a class="jnet-intent-card<?= $iid === $selectedId ? ' is-active' : '' ?>"
                       href="<?= $h(url('jnet/intentions') . '?id=' . rawurlencode($iid)) ?>">
                        <strong><?= $h((string) ($intent['title'] ?? '')) ?></strong>
                        <span><?= $h((string) ($intent['age'] ?? '')) ?></span>
                    </a>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </aside>

    <article class="jnet-panel">
        <?php if ($selected === null): ?>
            <div class="jnet-panel__body"><p class="jnet-empty">Sélectionnez une intention.</p></div>
        <?php else: ?>
            <div class="jnet-panel__head">
                <h2><?= $h((string) ($selected['title'] ?? 'Intention')) ?></h2>
            </div>
            <div class="jnet-panel__body">
                <div class="jnet-tags">
                    <?php foreach (($selected['tags'] ?? []) as $tag): ?>
                        <span><?= $h((string) $tag) ?></span>
                    <?php endforeach; ?>
                </div>

                <section class="jnet-section">
                    <h3>Énoncé</h3>
                    <p class="jnet-lead"><?= $h((string) ($selected['statement'] ?? '')) ?></p>
                </section>

                <section class="jnet-section">
                    <h3>Tâches clés</h3>
                    <ul>
                        <?php foreach (($selected['tasks'] ?? []) as $task): ?>
                            <li><?= $h((string) $task) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </section>

                <section class="jnet-section">
                    <h3>État final recherché</h3>
                    <p><strong>Forces amies —</strong> <?= $h((string) ($selected['endstate_friendly'] ?? '')) ?></p>
                    <p><strong>Adversaire —</strong> <?= $h((string) ($selected['endstate_enemy'] ?? '')) ?></p>
                </section>
            </div>
        <?php endif; ?>
    </article>
</div>
