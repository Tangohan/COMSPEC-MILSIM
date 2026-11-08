<?php
declare(strict_types=1);
/**
 * Création d'un kit de départ (responsables) : depuis une tenue remontée du jeu, ou de zéro avec l'assistant.
 * Affiché dans une fenêtre (#eq-kit-new) ou, sans JavaScript, directement dans l'espace (?creer=kit).
 * Variables : $gameWardrobes, $kitUnitOptions, $kitRoleOptions, $kitUnitsReady, $csrfToken, $h.
 */
?>
<div class="eqx-create__grid">
    <div class="eqx-create__card">
        <h3>À partir d’une tenue remontée du jeu</h3>
        <?php if ($gameWardrobes === []): ?>
        <p class="eq-hub__hint">Aucune tenue remontée du jeu pour le moment. Envoyez une tenue depuis l’arsenal en jeu, ou enregistrez un kit porté depuis « Équipement des joueurs » dans la gestion de la communauté.</p>
        <?php else: ?>
        <form method="post" action="<?= $h(url('equipment/kits/depuis-tenue')) ?>" class="eqx-form">
            <input type="hidden" name="_csrf_token" value="<?= $h($csrfToken) ?>">
            <label>Tenue à transformer
                <select name="wardrobe_id" class="bo-select" required>
                    <?php foreach ($gameWardrobes as $gw): ?>
                    <option value="<?= (int) ($gw['id'] ?? 0) ?>" translate="no"><?= $h((string) ($gw['display_name'] ?? $gw['name'] ?? '')) ?> — <?= $h((string) (($gw['owner_label'] ?? '') !== '' ? $gw['owner_label'] : 'Membre')) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>Nom du kit <small>(facultatif : reprend le nom de la tenue)</small>
                <input type="text" name="name" maxlength="90" autocomplete="off">
            </label>
            <label>Description <small>(facultatif)</small>
                <textarea name="description" rows="2" maxlength="1000" placeholder="Quand l’utiliser, pour qui, particularités…"></textarea>
            </label>
            <label>À qui proposer ce kit
                <select name="audience" class="bo-select">
                    <?php $audience = 'all'; require __DIR__ . '/_audience_options.php'; ?>
                </select>
            </label>
            <button type="submit" class="eq-hub__btn">Créer le kit</button>
        </form>
        <?php endif; ?>
    </div>
    <div class="eqx-create__card">
        <h3>De zéro, avec l’assistant</h3>
        <p>Choisissez un emploi : l’assistant propose des objets que vos joueurs ont déjà utilisés en jeu, emplacement par emplacement, et vous dit ce qui manque.</p>
        <p><a class="eq-hub__btn eq-hub__btn--outline" href="<?= $h(url('equipment/assistant') . '?cible=kit') ?>" data-ath-modal>Créer un kit avec l’assistant</a></p>
    </div>
</div>
