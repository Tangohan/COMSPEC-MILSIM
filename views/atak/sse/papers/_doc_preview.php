<?php
declare(strict_types=1);
/**
 * Aperçu d'un papier fictif (atelier, COMSPEC IDENTITY Lot 6) : recto et verso d'une carte ou d'un permis, couverture,
 * page de données et page d'observations d'un passeport. Gabarit générique aux couleurs du pays émetteur imaginaire,
 * filigrane SIMULATION en diagonale sur toute la surface et mention « SIMULATION — DOCUMENT DE JEU » obligatoires sur
 * chaque face, toujours la dernière couche (au-dessus de la photo, de la signature et de l'image de fond). Pays réel
 * stylisé (univers réaliste-simulation) : gabarit générique de sa zone (idw-zone--europe | americas | other), drapeau
 * simple à bandes, jamais un vrai modèle, emblème ou motif de sécurité. Les éléments data-f / data-p sont mis à jour en direct par
 * sse-id-workshop.js (data-f : donnée du papier ; data-p : champ imprimé en plus, ligne masquée s'il est vide).
 *
 * @var array<string, mixed> $preview SseIdWorkshopService::previewModel()
 */
$h = $h ?? static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$pv = $preview;
$pvType = (string) $pv['doc_type'];
$pvC1 = (string) ($pv['country']['colors'][0] ?? '#1f4e79');
$pvC2 = (string) ($pv['country']['colors'][1] ?? '#c9a227');
$pvIdentity = $pv['identity'];
$pvDoc = $pv['doc'];
$pvPrint = $pv['print'];
$pvUp = static fn (string $s): string => mb_strtoupper($s);
$pvRow = static function (string $key, string $label) use ($pvPrint, $h): string {
    $v = (string) ($pvPrint[$key] ?? '');

    return '<div data-row-p="' . $h($key) . '"' . ($v === '' ? ' hidden' : '') . '><dt translate="no">' . $h($label) . '</dt><dd data-p="' . $h($key) . '">' . $h($v) . '</dd></div>';
};
$pvFlag = static function () use ($pv, $h): string {
    $url = $pv['country']['flag_url'] ?? null;

    return '<span class="idw-flag" aria-hidden="true">'
        . '<img data-f-flag src="' . $h((string) $url) . '" alt=""' . ($url === null ? ' hidden' : '') . '>'
        . '<span class="idw-flag__bands" data-f-flag-bands' . ($url !== null ? ' hidden' : '') . '><i></i><i></i><i></i></span></span>';
};
$pvPhoto = static function () use ($pvIdentity, $h): string {
    $url = $pvIdentity['photo_url'] ?? null;

    return '<div class="idw-photo">'
        . '<img data-f-photo src="' . $h((string) $url) . '" alt=""' . ($url === null ? ' hidden' : '') . '>'
        . '<svg data-f-silhouette viewBox="0 0 60 75" aria-hidden="true"' . ($url !== null ? ' hidden' : '') . '><rect width="60" height="75" fill="currentColor" opacity=".08"/>'
        . '<circle cx="30" cy="27" r="13" fill="currentColor" opacity=".35"/><path d="M6 75c2-17 12-25 24-25s22 8 24 25z" fill="currentColor" opacity=".35"/></svg>'
        . '</div>';
};
$pvWatermark = '<div class="idw-watermark" aria-hidden="true">' . str_repeat('<span>SIMULATION · SIMULATION</span>', 7) . '</div>';
// Mention de la face avant le filigrane : le filigrane reste la dernière couche.
$pvWatermark = '<div class="idw-mention" translate="no">' . $h(\App\Support\SseIdRealCountryCatalog::FACE_MENTION) . '</div>' . $pvWatermark;
$pvZone = (string) ($pv['country']['zone'] ?? '');
$pvBand = static function (string $sub, bool $pvTypeLine = false) use ($pvFlag, $pv, $h, $pvUp): string {
    return '<div class="idw-band">' . $pvFlag()
        . '<div class="idw-band__txt"><b data-f="country_name">' . $h($pvUp((string) $pv['country']['name'])) . '</b><span translate="no"' . ($pvTypeLine ? ' data-f="type_line"' : '') . '>' . $h($sub) . '</span></div>'
        . '<span class="idw-band__sim" translate="no">SIMULATION</span></div>';
};
$pvTypeLine = match ($pvType) {
    'passport' => 'PASSEPORT · PASSPORT',
    'driving_license' => 'PERMIS DE CONDUIRE · DRIVING LICENCE',
    default => 'CARTE D’IDENTITÉ · IDENTITY CARD',
};
$pvMrz = '<div class="idw-mrz" data-f="mrz" translate="no">' . $h(implode("\n", $pv['mrz'])) . '</div>';
$pvStamp = '<div class="idw-stamp" aria-hidden="true"><span data-p-stamp>' . $h((string) (($pvPrint['stamp'] ?? '') !== '' ? $pvPrint['stamp'] : $pvDoc['authority'])) . '</span><b data-f="country_code">' . $h((string) $pv['country']['code']) . '</b></div>';
$pvFields = '<dl class="idw-fields">'
    . '<div><dt translate="no">Nom · Surname</dt><dd data-f="last_name">' . $h($pvUp((string) $pvIdentity['last_name'])) . '</dd></div>'
    . '<div><dt translate="no">Prénoms · Given names</dt><dd data-f="first_name">' . $h($pvIdentity['first_name']) . '</dd></div>'
    . '<div class="idw-half"><dt translate="no">Sexe · Sex</dt><dd data-f="sex">' . $h($pvIdentity['sex']) . '</dd></div>'
    . '<div class="idw-half"><dt translate="no">Nationalité · Nationality</dt><dd data-f="nationality">' . $h($pvIdentity['nationality']) . '</dd></div>'
    . '<div class="idw-half"><dt translate="no">Né(e) le · Date of birth</dt><dd data-f="birth_date">' . $h($pvIdentity['birth_date']) . '</dd></div>'
    . '<div class="idw-half"><dt translate="no">Lieu · Place of birth</dt><dd data-f="birth_place">' . $h($pvIdentity['birth_place']) . '</dd></div>'
    . '<div class="idw-half"><dt translate="no">N° · Number</dt><dd data-f="number" class="idw-num">' . $h($pvDoc['number']) . '</dd></div>'
    . '<div class="idw-half"><dt translate="no">Expire le · Expiry</dt><dd data-f="expires_on">' . $h($pvDoc['expires_on']) . '</dd></div>'
    . '</dl>';
$pvSignUrl = $pvIdentity['signature_url'] ?? null;
$pvSignature = '<div class="idw-sign"><span translate="no">Signature du titulaire · Holder’s signature</span>'
    . '<img class="idw-sign__img" data-f-signature src="' . $h((string) $pvSignUrl) . '" alt=""' . ($pvSignUrl === null ? ' hidden' : '') . '>'
    . '<b data-p-signature' . ($pvSignUrl !== null ? ' hidden' : '') . '>' . $h((string) (($pvPrint['signature'] ?? '') !== '' ? $pvPrint['signature'] : $pvIdentity['first_name'] . ' ' . $pvIdentity['last_name'])) . '</b></div>';
// Image de fond du gabarit personnalisé : première couche de chaque face (sous tout le reste, filigrane compris).
$pvBgUrl = $pv['background_url'] ?? null;
$pvBg = '<div class="idw-bg" data-f-bg aria-hidden="true"' . ($pvBgUrl !== null ? ' style="background-image: url(&quot;' . $h((string) $pvBgUrl) . '&quot;)"' : ' hidden') . '></div>';
$pvState = '';
if (($pvDoc['st_validity'] ?? 'valid') !== 'valid' || ($pvDoc['st_report'] ?? 'none') !== 'none') {
    $pvState = '<div class="idw-state" translate="no">' . $h(($pvDoc['st_validity'] ?? 'valid') !== 'valid' ? 'ANNULÉ / INVALID' : 'SIGNALÉ / REPORTED') . '</div>';
}
?>
<div class="idw-preview idw-type--<?= $h($pvType) ?> idw-layout--<?= $h($pv['layout']) ?><?= $pvZone !== '' ? ' idw-zone--' . $h($pvZone) : '' ?>" id="idw-preview"
     style="--idw-c1: <?= $h($pvC1) ?>; --idw-c2: <?= $h($pvC2) ?>;" data-doc-type="<?= $h($pvType) ?>">
    <?php /* Toutes les faces sont rendues : le type affiché (classe idw-type--…) choisit celles qui se voient. */ ?>
        <figure class="idw-face idw-face--cover" data-kind="passport" data-face="couverture" aria-label="Couverture du passeport">
            <?= $pvBg ?>
            <div class="idw-cover">
                <b data-f="country_name"><?= $h($pvUp((string) $pv['country']['name'])) ?></b>
                <span class="idw-cover__emblem" aria-hidden="true"><?= $pvFlag() ?></span>
                <strong translate="no">PASSEPORT</strong>
                <span translate="no">PASSPORT</span>
                <em translate="no">SPÉCIMEN DE JEU — SIMULATION</em>
            </div>
            <?= $pvWatermark ?>
        </figure>
        <figure class="idw-face idw-face--data" data-kind="passport" data-face="donnees" aria-label="Page de données">
            <?= $pvBg ?>
            <?= $pvBand('PASSEPORT · PASSPORT') ?>
            <div class="idw-body"><?= $pvPhoto() ?><?= $pvFields ?></div>
            <dl class="idw-fields idw-fields--more">
                <div class="idw-half"><dt translate="no">Délivré le · Date of issue</dt><dd data-f="issued_on"><?= $h($pvDoc['issued_on']) ?></dd></div>
                <div class="idw-half"><dt translate="no">Autorité · Authority</dt><dd data-f="authority"><?= $h($pvDoc['authority']) ?></dd></div>
                <?= $pvRow('personal_number', 'N° personnel · Personal no.') ?>
            </dl>
            <?= $pvSignature ?>
            <?= $pvState ?>
            <?= $pvMrz ?>
            <?= $pvWatermark ?>
        </figure>
        <figure class="idw-face idw-face--notes" data-kind="passport" data-face="observations" aria-label="Page d’observations">
            <?= $pvBg ?>
            <?= $pvBand('OBSERVATIONS · REMARKS') ?>
            <dl class="idw-fields">
                <?= $pvRow('place_of_issue', 'Lieu de délivrance · Place of issue') ?>
                <?= $pvRow('address', 'Adresse · Address') ?>
                <?= $pvRow('height', 'Taille · Height') ?>
                <?= $pvRow('eyes', 'Yeux · Eyes') ?>
                <?= $pvRow('service', 'Service · Employer') ?>
                <?= $pvRow('rank', 'Fonction · Position') ?>
                <?= $pvRow('phone', 'Téléphone · Phone') ?>
                <?= $pvRow('remarks', 'Observations · Remarks') ?>
            </dl>
            <?= $pvStamp ?>
            <?= $pvWatermark ?>
        </figure>
        <figure class="idw-face idw-face--front" data-kind="card" data-face="recto" aria-label="Recto">
            <?= $pvBg ?>
            <?= $pvBand($pvTypeLine, true) ?>
            <div class="idw-body"><?= $pvPhoto() ?><?= $pvFields ?></div>
            <?= $pvSignature ?>
            <?= $pvState ?>
            <?= $pvWatermark ?>
        </figure>
        <figure class="idw-face idw-face--back" data-kind="card" data-face="verso" aria-label="Verso">
            <?= $pvBg ?>
            <?= $pvBand('MENTIONS · DETAILS') ?>
            <dl class="idw-fields">
                <div class="idw-half"><dt translate="no">Délivré le · Date of issue</dt><dd data-f="issued_on"><?= $h($pvDoc['issued_on']) ?></dd></div>
                <div class="idw-half"><dt translate="no">Autorité · Authority</dt><dd data-f="authority"><?= $h($pvDoc['authority']) ?></dd></div>
                <div class="idw-license-only"><?= $pvRow('categories', 'Catégories · Categories') ?></div>
                <?= $pvRow('place_of_issue', 'Lieu de délivrance · Place of issue') ?>
                <?= $pvRow('address', 'Adresse · Address') ?>
                <?= $pvRow('height', 'Taille · Height') ?>
                <?= $pvRow('eyes', 'Yeux · Eyes') ?>
                <?= $pvRow('personal_number', 'N° personnel · Personal no.') ?>
                <?= $pvRow('service', 'Service · Employer') ?>
                <?= $pvRow('rank', 'Grade · Rank') ?>
                <?= $pvRow('phone', 'Téléphone · Phone') ?>
                <?= $pvRow('remarks', 'Observations · Remarks') ?>
            </dl>
            <?= $pvStamp ?>
            <?= $pvMrz ?>
            <?= $pvWatermark ?>
        </figure>
</div>
