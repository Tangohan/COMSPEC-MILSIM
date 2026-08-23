<?php
declare(strict_types=1);
/** @var array<string,mixed>|null $context */
/** @var list<array<string,mixed>> $entities */
/** @var callable $h */
use App\Support\SseWorkspaceUi;
?>
<header class="iw-intel-col-head">
    <h2>Contexte</h2>
</header>
<?php
/** @var array<string,mixed> $liaison */
$liaison = is_array($liaison ?? null) ? $liaison : [];
if ($liaison !== []):
    $liaisonStatus = (string) ($liaison['status'] ?? '');
    $tone = match ($liaisonStatus) {
        'nominal' => 'ok',
        'dégradé' => 'warn',
        default => 'danger',
    };
?>
<div class="iw-liaison iw-tone-<?= $h($tone) ?>" data-iw-liaison>
    <span class="iw-intel-kicker">Liaison terrain</span>
    <strong><?= $h((string) ($liaison['liaison_label'] ?? $liaison['status_label'] ?? 'État inconnu')) ?></strong>
    <p class="record-sub">
        File d’attente : <?= (int) ($liaison['file_attente'] ?? 0) ?>
        · Échecs : <?= (int) ($liaison['echecs'] ?? 0) ?>
        · Conflits : <?= (int) ($liaison['conflits'] ?? 0) ?>
    </p>
</div>
<?php endif; ?>
<div data-iw-context>
<?php if ($context === null): ?>
    <p class="iw-intel-empty">Sélectionnez une entité ou un dossier.</p>
<?php else: ?>
    <div class="iw-intel-context">
        <span class="iw-intel-kicker"><?= $h((string) ($context['entity_type_label'] ?? SseWorkspaceUi::entityTypeLabel((string) ($context['entity_type'] ?? 'élément')))) ?></span>
        <h3 data-iw-context-title><?= $h((string) ($context['display_label'] ?? $context['title'] ?? $context['reference_code'] ?? 'Élément')) ?></h3>
        <?php if (!empty($context['lifecycle_label'])): ?>
            <p><strong>Cycle :</strong> <?= $h((string) $context['lifecycle_label']) ?></p>
        <?php endif; ?>
        <?php if (!empty($context['confidence_label'])): ?>
            <p class="iw-intel-conf"><?= $h((string) $context['confidence_label']) ?></p>
        <?php elseif (!empty($context['confidence_code'])): ?>
            <p class="iw-intel-conf">Confiance <?= $h((string) $context['confidence_code']) ?></p>
        <?php endif; ?>
        <?php if (!empty($context['identity_tier_label']) && (string) ($context['identity_tier_label'] ?? '') !== 'Identité non précisée'): ?>
            <p><strong>Identité :</strong> <?= $h((string) $context['identity_tier_label']) ?></p>
        <?php endif; ?>
        <?php if (!empty($context['href'])): ?>
            <a class="iw-btn" href="<?= $h((string) $context['href']) ?>">Ouvrir</a>
        <?php elseif (!empty($context['full_href'])): ?>
            <a class="iw-btn" href="<?= $h((string) $context['full_href']) ?>">Fiche complète</a>
        <?php elseif (($context['source_table'] ?? '') === 'sse_persons' && !empty($context['source_id'])): ?>
            <a class="iw-btn" href="<?= $h(url('atak/sse/identites/' . (int) $context['source_id'])) ?>">Ouvrir l’identité</a>
        <?php elseif (($context['source_table'] ?? '') === 'sse_cases' && !empty($context['source_id'])): ?>
            <a class="iw-btn" href="<?= $h(url('atak/sse/workspace') . '?case=' . (int) $context['source_id']) ?>">Ouvrir la chemise</a>
        <?php endif; ?>
    </div>
<?php endif; ?>
</div>

<header class="iw-intel-col-head" style="margin-top:1.25rem">
    <h2>Entités récentes</h2>
</header>
<ul class="iw-intel-list iw-intel-list--compact" data-iw-entity-list>
    <?php foreach (array_slice($entities, 0, 12) as $ent): ?>
        <?php if (!is_array($ent)) {
            continue;
        } ?>
        <li class="iw-feed-item" data-entity-uuid="<?= $h((string) ($ent['uuid'] ?? '')) ?>">
            <span class="iw-feed-ico" aria-hidden="true"><?= SseWorkspaceUi::icon((string) ($ent['icon'] ?? SseWorkspaceUi::iconForEntityType((string) ($ent['entity_type'] ?? '')))) ?></span>
            <span class="iw-feed-copy">
                <span class="iw-intel-kicker"><?= $h((string) ($ent['entity_type_label'] ?? SseWorkspaceUi::entityTypeLabel((string) ($ent['entity_type'] ?? '')))) ?></span>
                <strong><?= $h((string) ($ent['display_label'] ?? '')) ?></strong>
                <?php if (!empty($ent['confidence_label'])): ?>
                    <em><?= $h((string) $ent['confidence_label']) ?></em>
                <?php elseif (!empty($ent['confidence_code'])): ?>
                    <em><?= $h((string) $ent['confidence_code']) ?></em>
                <?php endif; ?>
            </span>
        </li>
    <?php endforeach; ?>
    <?php if ($entities === []): ?>
        <li class="iw-intel-empty">Index vide.</li>
    <?php endif; ?>
</ul>
