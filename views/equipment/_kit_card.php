<?php
declare(strict_types=1);
/**
 * Carte d'un kit de départ. Variables : $kit, $canManageKits, $kitUnitOptions, $kitRoleOptions, $kitUnitsReady, $h, $csrfToken.
 * Noms de kits, d'objets, d'unités et de rôles viennent des membres ou du jeu : échappés et marqués translate="no".
 */
$kid = (int) ($kit['id'] ?? 0);
$unitName = trim((string) ($kit['unit_name'] ?? ''));
$roleName = trim((string) ($kit['role_name'] ?? ''));
$audience = 'all';
if (($kit['unit_id'] ?? null) !== null) {
    $audience = 'unit:' . (int) $kit['unit_id'];
} elseif (($kit['job_role_id'] ?? null) !== null) {
    $audience = 'role:' . (int) $kit['job_role_id'];
}
$sourceLabel = match ((string) ($kit['source'] ?? 'scratch')) {
    'game_loadout' => 'Tenue remontée du jeu',
    'assistant' => 'Créé avec l’assistant',
    default => 'Créé de zéro',
};
$preview = is_array($kit['preview'] ?? null) ? $kit['preview'] : [];
$sections = is_array($kit['sections'] ?? null) ? $kit['sections'] : [];
$mods = is_array($kit['required_mods'] ?? null) ? $kit['required_mods'] : [];
$copyId = (int) ($kit['copy_wardrobe_id'] ?? 0);
$desc = trim((string) ($kit['description'] ?? ''));
?>
<li class="eqx-kit" id="kit-<?= $kid ?>">
    <div class="eqx-kit__top">
        <span class="eq-hub__media eq-hub__media--kit">
            <?php if ($preview !== []): ?>
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
                <svg viewBox="0 0 80 100" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M40 18c6 0 10 4 10 10v6c8 3 14 10 14 20v28H16V54c0-10 6-17 14-20v-6c0-6 4-10 10-10z" stroke="currentColor" stroke-width="2.2"/></svg>
            </span>
            <?php endif; ?>
        </span>
        <div class="eqx-kit__body">
            <p class="eqx-kit__for">
                <?php if ($unitName !== ''): ?>
                Pour l’unité <strong translate="no"><?= $h($unitName) ?></strong>
                <?php elseif ($roleName !== ''): ?>
                Pour le rôle <strong translate="no"><?= $h($roleName) ?></strong>
                <?php else: ?>
                Pour tous les membres
                <?php endif; ?>
            </p>
            <h3 translate="no"><?= $h((string) ($kit['name'] ?? '')) ?></h3>
            <?php if ($desc !== ''): ?>
            <p class="eqx-kit__desc" translate="no"><?= $h(mb_strimwidth($desc, 0, 220, '…')) ?></p>
            <?php endif; ?>
            <p class="eqx-kit__meta">
                <span class="eqx-tag"><?= $h($sourceLabel) ?></span>
                <span><?= (int) ($kit['item_count'] ?? 0) > 0 ? (int) $kit['item_count'] . ' objets' : '' ?></span>
                <span>Mods requis : <?= $mods === [] ? 'aucun' : '<span translate="no">' . $h(implode(', ', array_map('strval', $mods))) . '</span>' ?></span>
            </p>
        </div>
    </div>

    <?php if ($sections !== []): ?>
    <details class="eqx-kit__details" data-eq-details-modal>
        <summary>Voir le contenu du kit</summary>
        <div class="eqx-kit__sections">
            <?php foreach ($sections as $sec): ?>
            <div>
                <h4><?= $h((string) ($sec['title'] ?? '')) ?></h4>
                <ul>
                    <?php foreach (($sec['items'] ?? []) as $it): ?>
                    <li><span translate="no"><?= $h((string) ($it['name'] ?? '')) ?></span><?php if ((int) ($it['qty'] ?? 1) > 1): ?> <em>× <?= (int) $it['qty'] ?></em><?php endif; ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endforeach; ?>
        </div>
    </details>
    <?php endif; ?>

    <div class="eqx-kit__actions">
        <form method="post" action="<?= $h(url('equipment/kits/' . $kid . '/copier')) ?>">
            <input type="hidden" name="_csrf_token" value="<?= $h((string) $csrfToken) ?>">
            <button type="submit" class="eq-hub__btn"><?= $copyId > 0 ? 'Mettre à jour ma copie' : 'Copier dans mes tenues' ?></button>
        </form>
        <?php if ($copyId > 0): ?>
        <a class="eq-hub__btn eq-hub__btn--outline" href="<?= $h(url('equipment') . '?tenue=' . $copyId) ?>">Voir ma copie</a>
        <?php endif; ?>
    </div>

    <?php if (!empty($canManageKits)): ?>
    <details class="eqx-kit__manage" data-eq-details-modal>
        <summary>Gérer ce kit</summary>
        <form method="post" action="<?= $h(url('equipment/kits/' . $kid . '/destinataire')) ?>" class="eqx-form">
            <input type="hidden" name="_csrf_token" value="<?= $h((string) $csrfToken) ?>">
            <label>À qui proposer ce kit
                <select name="audience" class="bo-select">
                    <?php require __DIR__ . '/_audience_options.php'; ?>
                </select>
            </label>
            <button type="submit" class="eq-hub__btn eq-hub__btn--outline">Enregistrer le destinataire</button>
        </form>
        <form method="post" action="<?= $h(url('equipment/kits/' . $kid . '/supprimer')) ?>" data-ui-confirm="1" data-ui-confirm-title="Supprimer le kit" data-ui-confirm-body="Supprimer ce kit de départ ? Les copies déjà faites par les membres restent dans leurs tenues.">
            <input type="hidden" name="_csrf_token" value="<?= $h((string) $csrfToken) ?>">
            <button type="submit" class="eq-hub__btn eq-hub__btn--ghost">Supprimer le kit</button>
        </form>
    </details>
    <?php endif; ?>
</li>
