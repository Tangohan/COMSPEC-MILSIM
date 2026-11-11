<?php

declare(strict_types=1);

/**
 * Fenêtre « Comment faire ma photo ? » (Mon identité) : la photo d'identité d'un personnage du jeu avec un générateur
 * d'image (ChatGPT ou autre). Le prompt et ses variantes viennent de App\Support\SseIdPhotoGuide, la même source que
 * l'aide du Bureau SSE (views/atak/sse/papers/_photo_guide.php) et que docs/guide/photo-identite.md : rien n'est réécrit
 * ici. Le prompt est un contenu à copier, jamais traduit par le site (translate="no") ; il est proposé dans la langue
 * du lecteur, l'autre langue reste à un clic. Bouton « Copier le prompt » : public/assets/js/operateur-hub.js.
 *
 * Variables de views/operateur/identite.php : $h, $opxIcon, $dialogOpen, $dialogClose.
 */
$guideLang = function_exists('locale') && locale() === 'en' ? 'en' : 'fr';
$guideOther = $guideLang === 'en' ? 'fr' : 'en';
$guideVariants = ['tired' => 'Fatigué', 'beard' => 'Barbe', 'scar' => 'Cicatrice', 'older' => 'Plus âgé', 'younger' => 'Plus jeune'];
$guideFace = static function (bool $good): string {
    // Schéma original (aucune photo de personne) : à gauche le bon cadrage, à droite ce qui est refusé.
    $bg = $good ? '#eef2f6' : '#3f4a45';
    $skin = '#c9b29b';
    $svg = '<svg viewBox="0 0 70 90" role="img" aria-hidden="true" focusable="false"><rect width="70" height="90" rx="4" fill="' . $bg . '"/>';
    if ($good) {
        $svg .= '<path d="M11 90c0-17 11-25 24-25s24 8 24 25z" fill="#51606e"/><ellipse cx="35" cy="40" rx="15" ry="19" fill="' . $skin . '"/>'
            . '<path d="M20 36c0-13 7-18 15-18s15 5 15 18c-4-7-9-9-15-9s-11 2-15 9z" fill="#3b2f27"/>'
            . '<circle cx="29" cy="41" r="1.6" fill="#1f2937"/><circle cx="41" cy="41" r="1.6" fill="#1f2937"/><path d="M30 50h10" stroke="#7c5a4a" stroke-width="1.6" stroke-linecap="round"/>';
    } else {
        $svg .= '<g transform="rotate(-14 35 50)"><path d="M2 96c0-17 11-25 24-25s24 8 24 25z" fill="#51606e"/><ellipse cx="26" cy="46" rx="15" ry="19" fill="' . $skin . '"/>'
            . '<path d="M9 40c0-14 8-19 17-19s17 5 17 19c-5-4-10-6-17-6s-12 2-17 6z" fill="#2f3b2f"/><rect x="13" y="40" width="26" height="7" rx="3" fill="#111827"/>'
            . '<path d="M21 56c3 3 7 3 10 0" stroke="#7c5a4a" stroke-width="1.6" fill="none" stroke-linecap="round"/></g><path d="M0 0h30v90H0z" fill="#000" fill-opacity=".35"/>';
    }

    return $svg . '</svg>';
};
?>
<?= $dialogOpen('dlg-photo-guide', 'Comment faire ma photo ?', 'La photo d’identité de votre personnage, façon photomaton, avec un générateur d’image (ChatGPT ou autre).') ?>
    <p class="opx-note"><strong>Uniquement des personnages du jeu.</strong> N’envoyez jamais la photo d’une vraie personne, ni la vôtre : la photo sert au jeu de rôle et reste sous le filigrane SIMULATION.</p>
    <ol class="opx-steps">
        <li>
            <h3>Capturez votre personnage dans Arma 3</h3>
            <ul class="opx-guide__list">
                <li>Dans Arma 3, ouvrez la caméra libre (Splendid Camera, menu Échap) ou le mode photo de votre mod.</li>
                <li>Placez le personnage face caméra : tête droite, regard droit dans l’objectif, expression neutre, bouche fermée.</li>
                <li>Retirez casque, lunettes de soleil et équipement qui masquent le visage ; front et oreilles dégagés.</li>
                <li>Mettez-le devant un mur uni et clair, avec une lumière douce et frontale, sans ombres marquées sur le visage.</li>
                <li>Cadrez serré : la tête et le haut des épaules, au centre.</li>
                <li>Faites la capture (F12 dans Steam) et retrouvez-la dans le dossier des captures Steam.</li>
            </ul>
            <p class="opx-note">Pas de capture ? Décrivez simplement votre personnage au générateur (âge, cheveux, barbe, teint) à la place de l’image jointe.</p>
        </li>
        <li>
            <h3>Donnez la capture et ce prompt au générateur</h3>
            <div class="opx-fields" data-opx-prompt>
                <label class="opx-fields__wide" for="opx-prompt-<?= $h($guideLang) ?>"><?= $guideLang === 'en' ? 'Prompt en anglais' : 'Prompt en français' ?>
                    <textarea id="opx-prompt-<?= $h($guideLang) ?>" readonly rows="7" translate="no" data-opx-prompt-text="<?= $h($guideLang) ?>"><?= $h(\App\Support\SseIdPhotoGuide::prompt($guideLang)) ?></textarea>
                </label>
                <textarea class="sr-only" readonly tabindex="-1" aria-hidden="true" translate="no" data-opx-prompt-text="<?= $h($guideOther) ?>"><?= $h(\App\Support\SseIdPhotoGuide::prompt($guideOther)) ?></textarea>
                <fieldset class="opx-fieldset opx-fields__wide">
                    <legend>Variantes à ajouter au prompt</legend>
                    <div class="opx-guide__variants">
                        <?php foreach ($guideVariants as $k => $label): ?>
                            <?php $v = \App\Support\SseIdPhotoGuide::VARIANTS[$k]; ?>
                            <label class="opx-guide__check"><input type="checkbox" data-opx-variant-fr="<?= $h($v['fr']) ?>" data-opx-variant-en="<?= $h($v['en']) ?>"> <?= $h($label) ?></label>
                        <?php endforeach; ?>
                    </div>
                </fieldset>
            </div>
            <div class="opx-acts">
                <button type="button" class="cc-btn cc-btn-primary" data-opx-copy-prompt="<?= $h($guideLang) ?>">Copier le prompt</button>
                <button type="button" class="cc-btn cc-btn-ghost" data-opx-copy-prompt="<?= $h($guideOther) ?>"><?= $guideOther === 'en' ? 'Copier le prompt (anglais)' : 'Copier le prompt (français)' ?></button>
                <span class="opx-note" aria-live="polite"><span data-opx-copy-ok hidden>Prompt copié : collez-le avec la capture dans le générateur d’image.</span><span data-opx-copy-fail hidden>Copie impossible : sélectionnez le texte et copiez-le à la main.</span></span>
            </div>
        </li>
        <li>
            <h3>Vérifiez le résultat</h3>
            <div class="opx-guide__compare">
                <figure><?= $guideFace(true) ?><figcaption><strong>Accepté.</strong> De face, fond uni et clair, expression neutre, épaules visibles, format portrait.</figcaption></figure>
                <figure><?= $guideFace(false) ?><figcaption><strong>À refaire.</strong> Tête penchée, lunettes ou couvre-chef, ombre sur le visage, fond sombre ou chargé.</figcaption></figure>
            </div>
            <p class="opx-note">Format portrait 35 × 45 (le cadre photo de nos papiers), 600 pixels de haut au moins pour rester net.</p>
        </li>
        <li>
            <h3>Téléchargez l’image et envoyez-la ici</h3>
            <p class="opx-note">PNG, JPG ou WebP, 4 Mo au plus. Le site vérifie le vrai type du fichier, recadre l’image au centre au format 35 × 45, la réenregistre et retire toutes ses données cachées (EXIF, position GPS).</p>
        </li>
    </ol>
<?= $dialogClose ?>
