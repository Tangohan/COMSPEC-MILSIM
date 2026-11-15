<?php
declare(strict_types=1);
/**
 * Formulaire « Partager » : un échange, une page ou un fichier de l'espace part vers une autre unité où le lecteur peut
 * publier. Utilisé par la page ?vue=partager (sans JavaScript) et, en gabarit, par la fenêtre « Partager » du script.
 * L'envoi est une écriture comme les autres (CSRF, droits refaits côté serveur) ; la confirmation est affichée en ligne.
 *
 * @var array<string, mixed> $usw
 * @var string $base
 * @var string $shareKind exchange|page|file
 * @var int $shareId
 * @var string $shareTitle
 * @var string $shareUid suffixe d'identifiant
 */
$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$targets = is_array($usw['shareTargets'] ?? null) ? $usw['shareTargets'] : [];
$uid = (string) ($shareUid ?? 'p');
?>
<form class="uw-shareform" method="post" action="<?= $h($base . '/partager') ?>" data-uw-share-form>
    <?= \App\Core\Csrf::field() ?>
    <input type="hidden" name="kind" value="<?= $h((string) ($shareKind ?? 'exchange')) ?>" data-uw-share-kind>
    <input type="hidden" name="item" value="<?= (int) ($shareId ?? 0) ?>" data-uw-share-item>
    <p class="uw-shareform__what">Élément partagé : <b data-uw-share-title><?= $h((string) ($shareTitle ?? '')) ?></b></p>
    <label class="uw-field" for="uw-share-target-<?= $h($uid) ?>">Partager avec</label>
    <select id="uw-share-target-<?= $h($uid) ?>" name="target" required>
        <option value="">Choisir une unité</option>
        <?php $direct = array_values(array_filter($targets, static fn (array $t): bool => ($t['via'] ?? '') !== 'liaison')); ?>
        <?php $viaLinks = array_values(array_filter($targets, static fn (array $t): bool => ($t['via'] ?? '') === 'liaison')); ?>
        <?php foreach ($direct as $t): ?>
            <option value="<?= (int) $t['id'] ?>"><?= $h((string) $t['label']) ?></option>
        <?php endforeach; ?>
        <?php if ($viaLinks !== []): ?>
            <optgroup label="Unités liées : information seulement">
                <?php foreach ($viaLinks as $t): ?>
                    <option value="<?= (int) $t['id'] ?>"><?= $h((string) $t['label']) ?> (<?= $h(mb_strtolower((string) ($t['linkKind'] ?? 'liaison'))) ?>)</option>
                <?php endforeach; ?>
            </optgroup>
        <?php endif; ?>
    </select>
    <label class="uw-field" for="uw-share-note-<?= $h($uid) ?>">Mot pour cette unité <small>(facultatif)</small></label>
    <textarea id="uw-share-note-<?= $h($uid) ?>" name="note" rows="2" maxlength="400" placeholder="Ce qu’elle doit en retenir."></textarea>
    <p class="uw-note">Le partage arrive dans le fil de l’unité choisie comme une information, jamais comme un ordre. Seules les unités où vous pouvez publier, et les unités liées à cet espace par une liaison active, sont proposées.</p>
    <div class="uw-form-actions">
        <button class="ja-b1 sm" type="submit">Partager</button>
    </div>
    <p class="uw-share__done" role="status" aria-live="polite" data-uw-share-msg hidden></p>
</form>
