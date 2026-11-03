<?php
declare(strict_types=1);
/**
 * Aide repliable « Photo d'identité façon photomaton » (COMSPEC IDENTITY) : préparer la capture du personnage dans
 * Arma 3, puis prompt d'exemple (FR et EN) à copier pour un générateur d'image. Les prompts sont du contenu à copier :
 * jamais traduits par le site (translate="no"), rangés dans App\Support\SseIdPhotoGuide (même texte que
 * docs/guide/photo-identite.md). Bouton « Copier le prompt » : sse-id-images.js.
 */
$h = $h ?? static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$guideVariants = ['tired' => 'Fatigué', 'beard' => 'Barbe', 'scar' => 'Cicatrice', 'older' => 'Plus âgé', 'younger' => 'Plus jeune'];
?>
<details class="idi-guide">
    <summary>Photo d’identité façon photomaton : créer la photo d’un personnage</summary>
    <div class="idi-guide__body">
        <p class="idw-note"><strong>Uniquement des personnages du jeu.</strong> N’envoyez jamais la photo d’une vraie personne, ni la vôtre : la photo sert au jeu de rôle et reste sous le filigrane SIMULATION.</p>
        <ol>
            <li>Dans Arma 3, ouvrez la caméra libre (Splendid Camera, menu Échap) ou le mode photo de votre mod.</li>
            <li>Placez le personnage face caméra : tête droite, regard droit dans l’objectif, expression neutre, bouche fermée.</li>
            <li>Retirez casque, lunettes de soleil et équipement qui masquent le visage ; front et oreilles dégagés.</li>
            <li>Mettez-le devant un mur uni et clair, avec une lumière douce et frontale, sans ombres marquées sur le visage.</li>
            <li>Cadrez serré : la tête et le haut des épaules, au centre.</li>
            <li>Faites la capture (F12 dans Steam) et retrouvez-la dans le dossier des captures Steam.</li>
            <li>Donnez la capture à un générateur d’image (ChatGPT ou autre) avec le prompt ci-dessous, puis importez le résultat ici et recadrez-le au format 35 × 45.</li>
        </ol>
        <?php foreach (['fr' => 'Prompt en français', 'en' => 'Prompt en anglais'] as $lang => $label): ?>
            <div class="idi-prompt" data-prompt-lang="<?= $h($lang) ?>">
                <label for="idi-prompt-<?= $h($lang) ?>"><?= $h($label) ?></label>
                <textarea id="idi-prompt-<?= $h($lang) ?>" readonly rows="6" translate="no" data-prompt><?= $h(\App\Support\SseIdPhotoGuide::prompt($lang)) ?></textarea>
            </div>
        <?php endforeach; ?>
        <fieldset class="idi-variants">
            <legend>Variantes à ajouter au prompt</legend>
            <?php foreach ($guideVariants as $k => $label): ?>
                <?php $v = \App\Support\SseIdPhotoGuide::VARIANTS[$k]; ?>
                <label class="idw-check"><input type="checkbox" data-variant-fr="<?= $h($v['fr']) ?>" data-variant-en="<?= $h($v['en']) ?>"> <?= $h($label) ?></label>
            <?php endforeach; ?>
        </fieldset>
        <div class="idp-actions">
            <button type="button" class="btn btn--ghost" data-copy-prompt="fr">Copier le prompt (français)</button>
            <button type="button" class="btn btn--ghost" data-copy-prompt="en">Copier le prompt (anglais)</button>
            <span class="muted" aria-live="polite"><span data-copy-ok hidden>Prompt copié : collez-le avec la capture dans le générateur d’image.</span><span data-copy-fail hidden>Copie impossible : sélectionnez le texte et copiez-le à la main.</span></span>
        </div>
    </div>
</details>
