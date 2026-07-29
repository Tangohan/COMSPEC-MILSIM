<?php
declare(strict_types=1);
/**
 * Rendu d’une question personnalisée sur le formulaire public d’enrôlement.
 *
 * @var array{id:string,label:string,widget:string,options:list<string>,required:bool,section:string} $customQuestion
 */
$cq = is_array($customQuestion ?? null) ? $customQuestion : [];
$cid = (string) ($cq['id'] ?? '');
if ($cid === '') {
    return;
}
$label = (string) ($cq['label'] ?? '');
$widget = (string) ($cq['widget'] ?? 'select');
$opts = is_array($cq['options'] ?? null) ? $cq['options'] : [];
$req = !empty($cq['required']);
$name = 'custom_q_' . $cid;
$fieldId = 'custom-q-' . preg_replace('/[^a-zA-Z0-9_\-]/', '', $cid);
?>
<div class="ce-field">
    <label class="ce-label" for="<?= htmlspecialchars($fieldId, ENT_QUOTES, 'UTF-8') ?>">
        <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
        <?php if ($req): ?><span class="ce-label-hint">(obligatoire)</span><?php endif; ?>
    </label>
    <?php if ($widget === 'textarea'): ?>
        <textarea id="<?= htmlspecialchars($fieldId, ENT_QUOTES, 'UTF-8') ?>" name="<?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?>" class="input-field track-field" rows="4"<?= $req ? ' required' : '' ?>></textarea>
    <?php elseif ($widget === 'select' || $widget === 'yesno'): ?>
        <?php if ($widget === 'yesno' && $opts === []) { $opts = ['Oui', 'Non']; } ?>
        <select id="<?= htmlspecialchars($fieldId, ENT_QUOTES, 'UTF-8') ?>" name="<?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?>" class="input-field track-field"<?= $req ? ' required' : '' ?>>
            <option value="">Sélectionner</option>
            <?php foreach ($opts as $opt): ?>
                <?php if (!is_string($opt) || trim($opt) === '') { continue; } ?>
                <option value="<?= htmlspecialchars($opt, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($opt, ENT_QUOTES, 'UTF-8') ?></option>
            <?php endforeach; ?>
        </select>
    <?php else: ?>
        <input type="text" id="<?= htmlspecialchars($fieldId, ENT_QUOTES, 'UTF-8') ?>" name="<?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?>" class="input-field track-field"<?= $req ? ' required' : '' ?>>
    <?php endif; ?>
</div>
