<?php
declare(strict_types=1);
/**
 * Carte d'une tenue (page Équipement). Variables : $w (tenue), $cardSection (mine|community|fav), $h, $lower, $csrfToken.
 * Tout texte venu du jeu ou des membres (nom de tenue, nom d'objet, propriétaire) est échappé et marqué translate="no".
 * Les attributs data-eq-* sont lus par public/assets/js/equipment-catalog.js (filtres, aperçu rapide, copie ACE).
 */
$wid = (int) ($w['id'] ?? 0);
$display = (string) ($w['display_name'] ?? \App\Support\ArsenalLoadoutItems::formatWardrobeTitle((string) ($w['name'] ?? '')));
$kinds = is_array($w['kinds'] ?? null) ? $w['kinds'] : [];
$cid = (int) ($w['collection_id'] ?? 0);
$preview = is_array($w['preview'] ?? null) ? $w['preview'] : [];
$previewNames = implode(' · ', array_slice(array_map(static fn (array $p): string => (string) ($p['name'] ?? ''), $preview), 0, 3));
$isMine = !empty($w['mine']);
$collectionName = trim((string) ($w['collection_name'] ?? ''));
$isFav = !empty($w['is_favorite_me']);
$ownerLabel = (string) (($w['owner_label'] ?? '') !== '' ? $w['owner_label'] : 'Membre');
$cardKey = (string) $cardSection . '-tenue-' . $wid;
$sourceLabel = match ((string) ($w['source'] ?? '')) {
    'starter_kit' => 'Copie d’un kit de départ',
    'role_kit' => 'Kit de départ',
    'assistant' => 'Créée avec l’assistant',
    'game_loadout' => 'Équipement porté en jeu',
    default => '',
};
?>
<li
    id="<?= $h($cardKey) ?>"
    data-eq-card
    data-id="<?= $wid ?>"
    data-name="<?= $h($lower((string) ($w['name'] ?? ''))) ?>"
    data-display="<?= $h($lower($display)) ?>"
    data-collection="<?= $cid ?>"
    data-collection-name="<?= $h($lower((string) ($w['collection_name'] ?? ''))) ?>"
    data-kinds="<?= $h(implode(',', $kinds)) ?>"
    data-created="<?= $h((string) ($w['created_at'] ?? '')) ?>"
    data-owner="<?= $h($lower($ownerLabel)) ?>"
    data-items="<?= $h($lower($previewNames)) ?>"
>
    <button type="button" class="eq-hub__card" data-eq-quickview="<?= $wid ?>">
        <span class="eq-hub__media eq-hub__media--portrait">
            <?php if (!empty($w['cover_url'])): ?>
            <img class="eq-hub__img" src="<?= $h((string) $w['cover_url']) ?>" alt="" loading="lazy" decoding="async">
            <?php elseif ($preview !== []): ?>
            <span class="eq-hub__kit" aria-hidden="true">
                <?php foreach (array_slice($preview, 0, 4) as $p): ?>
                <span class="eq-hub__kit-cell">
                    <?php if (!empty($p['icon'])): ?>
                    <img src="<?= $h((string) $p['icon']) ?>" alt="" loading="lazy" decoding="async">
                    <?php else: ?>
                    <span class="eq-hub__kit-ph"><?= $h(strtoupper(substr((string) preg_replace('/[^A-Za-z0-9]/', '', (string) ($p['name'] ?? '')), 0, 1)) ?: '?') ?></span>
                    <?php endif; ?>
                    <em translate="no"><?= $h((string) ($p['name'] ?? '')) ?></em>
                </span>
                <?php endforeach; ?>
            </span>
            <?php else: ?>
            <span class="eq-hub__ph" aria-hidden="true">
                <svg viewBox="0 0 80 100" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M40 18c6 0 10 4 10 10v6c8 3 14 10 14 20v28H16V54c0-10 6-17 14-20v-6c0-6 4-10 10-10z" stroke="currentColor" stroke-width="2.2"/>
                    <path d="M28 54h24M32 64h16" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                </svg>
            </span>
            <?php endif; ?>
            <span class="eq-hub__skeleton" aria-hidden="true"></span>
            <span class="eq-hub__quick">Aperçu rapide</span>
        </span>
        <span class="eq-hub__card-body">
            <strong translate="no"><?= $h($display) ?></strong>
            <span><span translate="no"><?= $h($ownerLabel) ?></span> · <?php if ($collectionName !== ''): ?><span translate="no"><?= $h($collectionName) ?></span><?php else: ?>Sans collection<?php endif; ?></span>
            <?php if ($previewNames !== ''): ?>
            <small class="eq-hub__card-items" translate="no"><?= $h($previewNames) ?></small>
            <?php endif; ?>
            <?php if ($sourceLabel !== ''): ?>
            <small class="eqx-tag"><?= $h($sourceLabel) ?></small>
            <?php endif; ?>
            <?php if ($isMine && ($w['visibility'] ?? 'tenant') === 'personal'): ?>
            <small class="eq-hub__card-private">Visible par vous seul</small>
            <?php endif; ?>
        </span>
    </button>
    <div class="eq-hub__card-actions">
        <form method="post" action="<?= $h(url('equipment/tenues/' . $wid . '/favori')) ?>" class="eqx-fav-form">
            <input type="hidden" name="_csrf_token" value="<?= $h((string) $csrfToken) ?>">
            <input type="hidden" name="on" value="<?= $isFav ? '0' : '1' ?>">
            <input type="hidden" name="_anchor" value="<?= $h($cardKey) ?>">
            <button type="submit" class="eqx-fav<?= $isFav ? ' is-on' : '' ?>" aria-pressed="<?= $isFav ? 'true' : 'false' ?>" title="<?= $isFav ? 'Retirer des favoris' : 'Mettre en favori' ?>">
                <span aria-hidden="true"><?= $isFav ? '★' : '☆' ?></span>
                <span class="visually-hidden"><?= $isFav ? 'Retirer des favoris' : 'Mettre en favori' ?> : <span translate="no"><?= $h($display) ?></span></span>
            </button>
        </form>
        <button type="button" class="eq-hub__btn eq-hub__btn--sm" data-eq-ace-copy-card="<?= $wid ?>">Copier pour ACE</button>
        <?php if ($isMine): ?>
        <button type="button" class="eq-hub__btn eq-hub__btn--sm eq-hub__btn--outline" data-eq-edit-card="<?= $wid ?>">Modifier</button>
        <form method="post" action="<?= $h(url('equipment/tenues/' . $wid . '/delete')) ?>" data-ui-confirm="1" data-ui-confirm-title="Supprimer la tenue" data-ui-confirm-body="Supprimer « <?= $h($display) ?> » d’Athena ? Elle reste dans votre arsenal ACE si elle y est enregistrée.">
            <input type="hidden" name="_csrf_token" value="<?= $h((string) $csrfToken) ?>">
            <button type="submit" class="eq-hub__btn eq-hub__btn--sm eq-hub__btn--ghost">Supprimer</button>
        </form>
        <?php endif; ?>
    </div>
</li>
