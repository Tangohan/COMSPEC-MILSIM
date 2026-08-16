<?php
declare(strict_types=1);
/** @var list<array<string,mixed>> $timeline */
/** @var callable $h */
?>
<header class="iw-intel-col-head">
    <h2>Chronologie</h2>
    <div class="iw-timeline-tools">
        <label class="sr-only" for="iw-tl-until">Jusqu’à</label>
        <input type="datetime-local" id="iw-tl-until" data-iw-tl-until title="Afficher l’état jusqu’à cette heure">
        <a class="link" href="<?= $h(url('atak/sse/chronologie')) ?>">Vue étendue</a>
    </div>
</header>
<?php if ($timeline === []): ?>
    <p class="iw-intel-empty">Aucun événement indexé. Les transmissions terrain alimenteront cette frise.</p>
<?php else: ?>
    <ol class="iw-intel-timeline" data-iw-timeline>
        <?php foreach ($timeline as $ev): ?>
            <?php if (!is_array($ev)) {
                continue;
            } ?>
            <li data-event-time="<?= $h((string) ($ev['event_time'] ?? '')) ?>" data-entity="<?= $h((string) ($ev['entity_uuid'] ?? '')) ?>">
                <time><?= $h((string) ($ev['event_time'] ?? '')) ?></time>
                <div>
                    <span class="iw-intel-badge"><?= $h((string) ($ev['event_type_label'] ?? $ev['event_type'] ?? '')) ?></span>
                    <span class="iw-intel-badge iw-intel-badge--muted"><?= $h((string) ($ev['source_system_label'] ?? $ev['source_system'] ?? '')) ?></span>
                    <?php if (!empty($ev['confidence_code'])): ?>
                        <span class="iw-intel-badge iw-intel-badge--conf"><?= $h((string) $ev['confidence_code']) ?></span>
                    <?php endif; ?>
                    <?php if (!empty($ev['identity_tier'])): ?>
                        <span class="iw-intel-badge"><?= $h((string) $ev['identity_tier']) ?></span>
                    <?php endif; ?>
                    <p><?= $h((string) ($ev['summary'] ?? '')) ?></p>
                    <?php if (!empty($ev['author_label'])): ?>
                        <small><?= $h((string) $ev['author_label']) ?></small>
                    <?php endif; ?>
                </div>
            </li>
        <?php endforeach; ?>
    </ol>
<?php endif; ?>

<header class="iw-intel-col-head" style="margin-top:1.5rem">
    <h2>Relations</h2>
    <a class="link" href="<?= $h(url('atak/sse/toiles')) ?>">Toiles</a>
</header>
<?php
$relations = is_array($relations ?? null) ? $relations : [];
if ($relations === []): ?>
    <p class="iw-intel-empty">Aucune relation enregistrée.</p>
<?php else: ?>
    <ul class="iw-intel-list">
        <?php foreach ($relations as $rel): ?>
            <?php if (!is_array($rel)) {
                continue;
            } ?>
            <li>
                <span class="iw-intel-kicker">
                    <?= $h((string) ($rel['status'] ?? 'confirmed') === 'proposed' ? 'Proposées' : 'Confirmées') ?>
                </span>
                <strong>
                    <?= $h((string) ($rel['from_type'] ?? '')) ?> #<?= (int) ($rel['from_id'] ?? 0) ?>
                    → <?= $h((string) ($rel['relation'] ?? '')) ?> →
                    <?= $h((string) ($rel['to_type'] ?? '')) ?> #<?= (int) ($rel['to_id'] ?? 0) ?>
                </strong>
            </li>
        <?php endforeach; ?>
    </ul>
<?php endif; ?>
