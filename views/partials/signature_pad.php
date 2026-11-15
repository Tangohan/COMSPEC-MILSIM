<?php

declare(strict_types=1);

/**
 * Pad de signature : zone de dessin (souris, doigt, stylet), annulation, effacement, import d'un fichier image et
 * signature « tapée » (alternative au clavier). Voir public/assets/js/signature-pad.js.
 *
 * Paramètres : le tableau $sigpad défini avant l'inclusion (tous facultatifs) :
 *   name          champ caché qui reçoit le PNG en data URL (défaut « signature_data_url »)
 *   file_name     nom du champ fichier natif gardé dans le formulaire (« signature ») ; null : pas d'import de fichier
 *   file_attrs    attributs HTML ajoutés au champ fichier (data-bind-img="signature"…)
 *   current_url   image de la signature actuelle, affichée à titre d'information
 *   label         libellé du pad (défaut « Signature »)
 *   height        hauteur du pad en pixels (défaut 160)
 *   ink           couleur de l'encre (défaut #0b1220)
 *   background    « transparent » (défaut) ou « white » : fond du PNG exporté
 *   required      true : le formulaire ne part pas sans dessin ni fichier
 *   theme         « light » (défaut) ou « dark » (portail SSE)
 *   max_side      plus grand côté du PNG exporté, en pixels (défaut 900)
 *   trim          true (défaut) : le PNG est rogné sur l'encre
 *   name_hint     nom proposé dans « Taper mon nom »
 *   id            identifiant du pad (défaut : numéroté)
 */

$sp = isset($sigpad) && is_array($sigpad) ? $sigpad : [];
$spEsc = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$spCount = (int) ($GLOBALS['__sigpad_count'] ?? 0) + 1;
$GLOBALS['__sigpad_count'] = $spCount;
$spId = (string) ($sp['id'] ?? 'sigpad-' . $spCount);
$spName = (string) ($sp['name'] ?? 'signature_data_url');
$spFile = array_key_exists('file_name', $sp) ? $sp['file_name'] : null;
$spFileAttrs = (string) ($sp['file_attrs'] ?? '');
$spCurrent = (string) ($sp['current_url'] ?? '');
$spLabel = (string) ($sp['label'] ?? 'Signature');
$spHeight = max(100, min(400, (int) ($sp['height'] ?? 160)));
$spInk = preg_match('/^#[0-9a-fA-F]{6}$/', (string) ($sp['ink'] ?? '')) === 1 ? (string) $sp['ink'] : '#0b1220';
$spBackground = ($sp['background'] ?? 'transparent') === 'white' ? 'white' : 'transparent';
$spRequired = !empty($sp['required']);
$spDark = ($sp['theme'] ?? 'light') === 'dark';
$spMaxSide = max(300, min(1600, (int) ($sp['max_side'] ?? 900)));
$spTrim = !array_key_exists('trim', $sp) || !empty($sp['trim']);
$spHint = mb_substr((string) ($sp['name_hint'] ?? ''), 0, 60);
$spFileAccept = (string) ($sp['file_accept'] ?? 'image/png,image/jpeg,image/webp');
$spFirst = empty($GLOBALS['__sigpad_assets']);
$GLOBALS['__sigpad_assets'] = true;
?>
<?php if ($spFirst && function_exists('asset_url')): ?>
<link rel="stylesheet" href="<?= $spEsc(asset_url('assets/css/signature-pad.css')) ?>">
<script src="<?= $spEsc(asset_url('assets/js/signature-pad.js')) ?>" defer></script>
<?php endif; ?>
<div class="sigpad<?= $spDark ? ' sigpad--dark' : '' ?>" data-sigpad id="<?= $spEsc($spId) ?>"
     data-ink="<?= $spEsc($spInk) ?>" data-background="<?= $spEsc($spBackground) ?>" data-required="<?= $spRequired ? '1' : '0' ?>"
     data-height="<?= (int) $spHeight ?>" data-max-side="<?= (int) $spMaxSide ?>" data-trim="<?= $spTrim ? '1' : '0' ?>" data-empty="1">
    <p class="sigpad__label" id="<?= $spEsc($spId) ?>-label"><?= $spEsc($spLabel) ?><?= $spRequired ? ' *' : '' ?></p>
    <?php if ($spCurrent !== ''): ?>
        <div class="sigpad__current">
            <img src="<?= $spEsc($spCurrent) ?>" alt="Signature actuelle" loading="lazy">
            <span>Signature actuelle : dessinez ou chargez-en une nouvelle pour la remplacer.</span>
        </div>
    <?php endif; ?>
    <div class="sigpad__stage">
        <canvas class="sigpad__canvas" data-sigpad-canvas role="img" style="height: <?= (int) $spHeight ?>px"
                aria-labelledby="<?= $spEsc($spId) ?>-label" aria-describedby="<?= $spEsc($spId) ?>-status"
                title="Zone de dessin de la signature"></canvas>
        <span class="sigpad__hint" data-sigpad-hint>Signez ici avec la souris, le doigt ou le stylet.</span>
    </div>
    <div class="sigpad__tools">
        <button type="button" class="sigpad__btn" data-sigpad-undo disabled>Annuler le dernier trait</button>
        <button type="button" class="sigpad__btn" data-sigpad-clear disabled>Effacer</button>
        <?php if (is_string($spFile) && $spFile !== ''): ?>
            <input type="file" class="sigpad__file" id="<?= $spEsc($spId) ?>-file" name="<?= $spEsc($spFile) ?>" accept="<?= $spEsc($spFileAccept) ?>" data-sigpad-file<?= $spFileAttrs !== '' ? ' ' . $spFileAttrs : '' ?>>
            <label class="sigpad__btn" for="<?= $spEsc($spId) ?>-file">Charger un fichier</label>
        <?php endif; ?>
    </div>
    <div class="sigpad__type">
        <label for="<?= $spEsc($spId) ?>-name">Taper mon nom (signature écrite, sans dessin)
            <input type="text" id="<?= $spEsc($spId) ?>-name" data-sigpad-name maxlength="60" autocomplete="off" value="<?= $spEsc($spHint) ?>">
        </label>
        <button type="button" class="sigpad__btn" data-sigpad-type>Utiliser ce nom</button>
    </div>
    <p class="sigpad__status" id="<?= $spEsc($spId) ?>-status" data-sigpad-status>Signature vide</p>
    <input type="hidden" name="<?= $spEsc($spName) ?>" value="" data-sigpad-out>
    <span hidden>
        <span data-sigpad-t="empty">Signature vide</span>
        <span data-sigpad-t="ready">Signature prête</span>
        <span data-sigpad-t="required">Signature obligatoire : dessinez-la, tapez votre nom ou chargez un fichier.</span>
        <span data-sigpad-t="file_only">Fichier choisi : il sera envoyé tel quel.</span>
    </span>
</div>
<?php unset($sp, $sigpad); ?>
