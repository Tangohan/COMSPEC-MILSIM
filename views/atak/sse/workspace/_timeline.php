<?php
declare(strict_types=1);
/** @var list<array<string,mixed>> $timeline */
/** @var callable $h */
use App\Support\SseWorkspaceUi;
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
            }
            $iconName = (string) ($ev['icon'] ?? SseWorkspaceUi::iconForEventType((string) ($ev['event_type'] ?? '')));
            $timeLabel = (string) ($ev['event_time_label'] ?? SseWorkspaceUi::formatEventTime((string) ($ev['event_time'] ?? '')));
            $tierLabel = (string) ($ev['identity_tier_label'] ?? '');
            if ($tierLabel === '' && !empty($ev['identity_tier'])) {
                $tierLabel = SseWorkspaceUi::identityTierLabel((string) $ev['identity_tier']);
            }
            $repeats = (int) ($ev['repeat_count'] ?? 1);
            ?>
            <li data-event-time="<?= $h((string) ($ev['event_time'] ?? '')) ?>" data-entity="<?= $h((string) ($ev['entity_uuid'] ?? '')) ?>">
                <time datetime="<?= $h((string) ($ev['event_time'] ?? '')) ?>"><?= $h($timeLabel) ?></time>
                <div class="iw-tl-body">
                    <span class="iw-feed-ico iw-feed-ico--tl" aria-hidden="true"><?= SseWorkspaceUi::icon($iconName) ?></span>
                    <div class="iw-tl-copy">
                        <div class="iw-tl-badges">
                            <span class="iw-intel-badge"><?= $h((string) ($ev['event_type_label'] ?? SseWorkspaceUi::eventTypeLabel((string) ($ev['event_type'] ?? '')))) ?></span>
                            <span class="iw-intel-badge iw-intel-badge--muted"><?= $h((string) ($ev['source_system_label'] ?? SseWorkspaceUi::sourceSystemLabel((string) ($ev['source_system'] ?? '')))) ?></span>
                            <?php if (!empty($ev['confidence_label'])): ?>
                                <span class="iw-intel-badge iw-intel-badge--conf" title="<?= $h((string) $ev['confidence_label']) ?>"><?= $h((string) ($ev['confidence_code'] ?? '')) ?></span>
                            <?php elseif (!empty($ev['confidence_code'])): ?>
                                <span class="iw-intel-badge iw-intel-badge--conf"><?= $h((string) $ev['confidence_code']) ?></span>
                            <?php endif; ?>
                            <?php if ($tierLabel !== '' && $tierLabel !== 'Identité non précisée'): ?>
                                <span class="iw-intel-badge"><?= $h($tierLabel) ?></span>
                            <?php endif; ?>
                            <?php if ($repeats > 1): ?>
                                <span class="iw-intel-badge iw-intel-badge--muted"><?= $repeats ?> fois</span>
                            <?php endif; ?>
                        </div>
                        <p><?= $h((string) ($ev['summary'] ?? '')) ?></p>
                        <?php if (!empty($ev['author_label'])): ?>
                            <small><?= $h((string) $ev['author_label']) ?></small>
                        <?php endif; ?>
                    </div>
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
    <ul class="iw-intel-list iw-intel-list--cards">
        <?php foreach ($relations as $rel): ?>
            <?php if (!is_array($rel)) {
                continue;
            }
            $from = (string) ($rel['from_type_label'] ?? SseWorkspaceUi::entityTypeLabel((string) ($rel['from_type'] ?? '')));
            $to = (string) ($rel['to_type_label'] ?? SseWorkspaceUi::entityTypeLabel((string) ($rel['to_type'] ?? '')));
            $relLabel = (string) ($rel['relation_label'] ?? SseWorkspaceUi::relationLabel((string) ($rel['relation'] ?? '')));
            ?>
            <li class="iw-feed-item">
                <span class="iw-feed-ico" aria-hidden="true"><?= SseWorkspaceUi::icon('graph') ?></span>
                <span class="iw-feed-copy">
                    <span class="iw-intel-kicker">
                        <?= $h((string) ($rel['status'] ?? 'confirmed') === 'proposed' ? 'Proposées' : 'Confirmées') ?>
                    </span>
                    <strong><?= $h($from) ?> → <?= $h($relLabel) ?> → <?= $h($to) ?></strong>
                </span>
            </li>
        <?php endforeach; ?>
    </ul>
<?php endif; ?>
