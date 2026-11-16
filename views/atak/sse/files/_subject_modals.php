<?php
declare(strict_types=1);
/**
 * Fiche sujet : les formulaires d'édition, un par section, dans des modales (dialog natif). La fiche reste en lecture ;
 * chaque section modifiable porte une icône « Modifier » qui ouvre sa modale (href « #fx-m-… » : sans JavaScript, la
 * modale s'affiche par :target). Les formulaires, champs, jetons CSRF et routes POST sont ceux d'avant.
 * Droits : $canManage (alias, signalement, liens, mentions), $canWrite (photo, annotation), déjà calculés par la fiche.
 *
 * @var array<string,mixed> $r
 * @var array<string,mixed> $p
 * @var string $c
 * @var string $self
 * @var int $iid
 * @var string $csrf
 * @var callable $h
 * @var bool $canManage
 * @var bool $canWrite
 * @var array<string,string> $mentionLabels
 */
use App\Support\SseFileSubjectCatalog as S;
use App\Support\SseRecordsCountryPacks as Packs;

$fxModal = static function (string $id, string $title, string $desc, string $back, string $inner, string $submit, string $action, bool $multipart = false) use ($h, $csrf): string {
    return '<dialog class="fx-modal" id="' . $h($id) . '" aria-labelledby="' . $h($id) . '-t">'
        . '<div class="fx-modal__card">'
        . '<div class="fx-modal__head"><h2 id="' . $h($id) . '-t">' . $h($title) . '</h2>'
        . '<a class="fx-modal__x" href="#' . $h($back) . '" data-fx-modal-close aria-label="Fermer" title="Fermer">×</a></div>'
        . '<form class="fx-modal__form" method="post" action="' . $h($action) . '"' . ($multipart ? ' enctype="multipart/form-data"' : '') . '>' . $csrf
        . '<div class="fx-modal__body"><p class="fx-modal__err" role="alert" data-fx-modal-err hidden></p>'
        . '<p class="fx-modal__desc">' . $h($desc) . '</p>' . $inner . '</div>'
        . '<div class="fx-modal__foot"><a class="btn" href="#' . $h($back) . '" data-fx-modal-close>Annuler</a>'
        . '<button class="btn pri" type="submit">' . $h($submit) . '</button></div>'
        . '</form></div></dialog>';
};
$select = static function (string $name, array $options, ?string $current = null, string $first = '—') use ($h): string {
    $out = '<select class="fx-in" name="' . $h($name) . '"><option value="">' . $h($first) . '</option>';
    foreach ($options as $k => $l) {
        $out .= '<option value="' . $h($k) . '"' . ($current !== null && $current === (string) $k ? ' selected' : '') . '>' . $h($l) . '</option>';
    }

    return $out . '</select>';
};

if ($canManage) {
    // Alias et variantes.
    $in = '<label class="fx-f"><span>Alias, surnom ou variante</span><input class="fx-in" name="alias" maxlength="160" required placeholder="ex. « Doc », « D. WOLF »"></label>'
        . '<label class="fx-f"><span>Type</span><select class="fx-in" name="kind"><option value="aka">Alias</option><option value="nickname">Surnom</option><option value="spelling">Variante d’orthographe</option></select></label>';
    echo $fxModal('fx-m-alias', 'Ajouter un alias', 'Un nom d’usage, un surnom ou une autre orthographe : la recherche retrouve la fiche avec.', 'identite', $in, 'Ajouter', url($self . '/alias'));

    // Signalement.
    ob_start(); ?>
    <div class="fx-g3">
        <label class="fx-f"><span>Taille</span><input class="fx-in mono" name="height" value="<?= $h($p['height_cm'] ?? '') ?>" placeholder="178 ou 5'10&quot;"></label>
        <label class="fx-f"><span>Poids</span><input class="fx-in mono" name="weight" value="<?= $h($p['weight_kg'] ?? '') ?>" placeholder="78 ou 172 lb"></label>
        <label class="fx-f"><span>Pays du sujet</span><select class="fx-in" name="country"><option value="">Selon la nationalité</option><?php foreach (Packs::codes() as $code): ?><option value="<?= $h($code) ?>"<?= ($p['country'] ?? '') === $code ? ' selected' : '' ?>><?= $h(Packs::label($code)) ?></option><?php endforeach; ?></select></label>
    </div>
    <small class="fx-help">Le pays du sujet règle l’affichage : dates, taille en pieds-pouces ou en cm, formats de numéros. Ex. États-Unis : 03/14/1991 et 5'11".</small>
    <div class="fx-g3">
        <label class="fx-f"><span>Corpulence</span><?= $select('build', S::BUILDS, (string) ($p['build'] ?? '')) ?></label>
        <label class="fx-f"><span>Cheveux</span><?= $select('hair', S::HAIR, (string) ($p['hair'] ?? '')) ?></label>
        <label class="fx-f"><span>Yeux</span><?= $select('eyes', S::EYES, (string) ($p['eyes'] ?? '')) ?></label>
    </div>
    <label class="fx-f"><span>Teint</span><input class="fx-in" name="complexion" maxlength="24" value="<?= $h($p['complexion'] ?? '') ?>" placeholder="ex. mat, clair"></label>
    <label class="fx-f"><span>Signes particuliers</span><textarea class="fx-in" name="marks" maxlength="1000" placeholder="ex. tatouage aigle avant-bras droit, cicatrice arcade gauche"><?= $h($p['marks'] ?? '') ?></textarea></label>
    <div class="fx-f"><span>Codes de prudence</span><div class="fx-tg"><?php foreach (S::CAUTIONS as $k => $l): ?><label class="fx-sw"><input type="checkbox" name="cautions[]" value="<?= $h($k) ?>"<?= in_array($k, $p['caution_codes'] ?? [], true) ? ' checked' : '' ?>><i></i><?= $h($l) ?></label><?php endforeach; ?></div><small>Affichés en tête de fiche pour la sécurité des intervenants. Ex. « Armé » si la personne porte habituellement une arme.</small></div>
    <?php
    echo $fxModal('fx-m-signalement', 'Modifier le signalement', 'Taille, corpulence, signes particuliers et codes de prudence affichés en tête de fiche.', 'signalement', (string) ob_get_clean(), 'Enregistrer le signalement', url($self . '/signalement'));

    // Adresses, véhicules, téléphones, personnes liées.
    ob_start(); ?>
    <div class="fx-g2">
        <label class="fx-f"><span>Type</span><select class="fx-in" name="kind"><?php foreach (S::LINK_KINDS as $k => $l): ?><option value="<?= $h($k) ?>"><?= $h($l) ?></option><?php endforeach; ?></select></label>
        <label class="fx-f"><span>Valeur</span><input class="fx-in" name="value" maxlength="255" required placeholder="Adresse, plaque, numéro, courriel ou IDT-…"></label>
    </div>
    <small class="fx-help">Ex. adresse « <?= $h(Packs::get($c)['address_format'] ?? '') ?> », plaque « <?= $h(Packs::get($c)['plate']['example'] ?? '') ?> », téléphone « <?= $h(Packs::get($c)['phone']['example'] ?? '') ?> », personne liée « IDT-42 ».</small>
    <div class="fx-g3">
        <label class="fx-f"><span>Marque (véhicule)</span><input class="fx-in" name="make" maxlength="60"></label>
        <label class="fx-f"><span>Modèle</span><input class="fx-in" name="model" maxlength="60"></label>
        <label class="fx-f"><span>Couleur</span><input class="fx-in" name="color" maxlength="40"></label>
    </div>
    <div class="fx-g2">
        <label class="fx-f"><span>Lien (personne liée)</span><?= $select('relation', S::RELATIONS) ?></label>
        <label class="fx-f"><span>Mention (véhicule)</span><?= $select('flag', S::VEHICLE_FLAGS, null, 'Aucune') ?></label>
    </div>
    <label class="fx-f"><span>Note</span><input class="fx-in" name="note" maxlength="160" placeholder="ex. vu au checkpoint nord le 09/11, 4 occupants"></label>
    <?php
    echo $fxModal('fx-m-liens', 'Ajouter à la fiche', 'Une adresse, un véhicule, un téléphone, un courriel ou une personne liée.', 'liens', (string) ob_get_clean(), 'Ajouter à la fiche', url($self . '/liens'));

    // Mention aux fichiers.
    ob_start(); ?>
    <input type="hidden" name="target_type" value="identity"><input type="hidden" name="target_id" value="<?= $iid ?>"><input type="hidden" name="back" value="<?= $h($self) ?>">
    <label class="fx-f"><span>Mention</span><select class="fx-in" name="mention"><?php foreach ($mentionLabels as $k => $l): ?><option value="<?= $h($k) ?>"><?= $h($l) ?></option><?php endforeach; ?></select></label>
    <label class="fx-f"><span>Référence</span><input class="fx-in" name="reference" maxlength="64" placeholder="ex. MANDAT N° 26-1187"></label>
    <label class="fx-f"><span>Note</span><input class="fx-in" name="note" maxlength="1000" placeholder="ex. porteur habituel d’une arme de poing"></label>
    <?php
    echo $fxModal('fx-m-mention', 'Poser une mention', 'Mandat, recherche ou signalement : la mention sort en jeu à la prochaine consultation de la fiche.', 'mentions', (string) ob_get_clean(), 'Poser', url('atak/sse/fichiers/mentions'));
}

if ($canWrite) {
    // Photo.
    ob_start(); ?>
    <input type="hidden" name="identity_id" value="<?= $iid ?>"><input type="hidden" name="back" value="<?= $h($self) ?>#photos">
    <label class="fx-drop"><input type="file" name="photo" accept="image/jpeg,image/png,image/webp" required><b>Déposez une photo</b><span>JPEG, PNG ou WebP, <?= (int) \App\Services\Sse\SseIdImageService::maxMb('photo') ?> Mo au plus · capture en jeu, photo de papiers</span></label>
    <div class="fx-g2">
        <label class="fx-f"><span>Vue</span><select class="fx-in" name="kind"><option value="front">Face</option><option value="profile">Profil</option><option value="field">Terrain</option><option value="document">Papier</option></select></label>
        <label class="fx-f"><span>Légende</span><input class="fx-in" name="caption" maxlength="255" placeholder="ex. checkpoint nord, 09:12Z"></label>
    </div>
    <small class="fx-help">La photo est seulement rangée sur la fiche : aucune reconnaissance faciale n’est faite.</small>
    <?php
    echo $fxModal('fx-m-photo', 'Ajouter une photo', 'Une photo de face, de profil, de terrain ou de papiers, rangée sur cette fiche.', 'photos', (string) ob_get_clean(), 'Envoyer', url('atak/sse/fichiers/photos'), true);

    // Annotation.
    $in = '<label class="fx-f"><span>Nouvelle annotation</span><textarea class="fx-in" name="body" maxlength="2000" required placeholder="ex. Vu avec le Ford beige au marché de l’ouest, semble nerveux."></textarea><small>Visible par les membres qui lisent les fichiers. Les noms et numéros cités sont recoupés.</small></label>';
    echo $fxModal('fx-m-note', 'Annoter le dossier', 'Une remarque libre, signée de votre indicatif, ajoutée au dossier de la fiche.', 'annotations', $in, 'Annoter', url($self . '/notes'));
}
