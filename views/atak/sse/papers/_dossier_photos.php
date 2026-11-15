<?php
declare(strict_types=1);
/**
 * Onglet « Photos » de la fiche identité : portrait du titulaire mis en avant, puis les images rangées par source (fiche,
 * SEEK II, terrain, photothèque) en vignettes de même rapport, légende sur deux lignes, visionneuse plein écran
 * (sse-identity-photos.js). Les groupes, les doublons et les compteurs viennent de SseIdentityPhotoGallery ; cette vue ne
 * fait que les afficher. Aucune image n'est chargée avant d'être proche de l'écran (loading="lazy").
 *
 * Données (portée de identity_show.php) : $identity, $filePhotos, $seekImages, $fileMentions, $canManage, $canSeeTruth,
 * $secret, $iid, $filesSubject, $self, $h, $day, $photoGallery (SseIdentityPhotoGallery::build, calculé par la fiche).
 *
 * @var array<string, mixed> $identity
 */
use App\Services\Sse\SseIdentityPhotoGallery;

$gal = $photoGallery;
$gLocked = !($canSeeTruth || !$secret);
$gManage = !empty($canManage);
$gEdit = !empty($canEditImages);
$gGroupLabels = ['fiche' => 'Fiche', 'seek' => 'SEEK II', 'terrain' => 'Terrain', 'biblio' => 'Photothèque'];
$gGroupHints = [
    'fiche' => 'Portrait du titulaire et photos de face ou de profil versées à la fiche.',
    'seek' => 'Clichés pris au terminal SEEK II par les contrôleurs.',
    'terrain' => 'Photos prises sur le terrain, par un membre ou depuis ATAK.',
    'biblio' => 'Autres images de la photothèque, rattachées à cette fiche.',
];
$gKinds = ['front' => 'Face', 'profile' => 'Profil', 'field' => 'Terrain', 'document' => 'Papier'];
$gSources = ['seek' => 'Terminal SEEK II', 'atak' => 'ATAK', 'upload' => 'Envoi manuel'];
$gPhotothequeUrl = url('atak/sse/fichiers/photos');
$gTitle = static function (array $it) use ($gKinds): string {
    if ($it['holder']) {
        return 'Portrait du titulaire';
    }
    if ($it['caption'] !== '') {
        return $it['caption'];
    }
    if ($it['ref'] !== '' && $it['group'] === 'seek') {
        return 'Fiche ' . $it['ref'];
    }

    return $gKinds[$it['kind']] ?? 'Photo';
};
$gSeekSig = static function (array $it): string {
    $sig = is_array($it['seek'] ?? null) ? trim((string) ($it['seek']['signature_image'] ?? '')) : '';

    return $sig !== '' ? (string) url($sig) : '';
};
/** Une vignette : lien ouvrant la visionneuse + légende + fiche cachée reprise par la visionneuse. */
$gTile = static function (array $it, bool $hero = false) use ($h, $day, $gTitle, $gKinds, $gSources, $gManage, $gSeekSig, $gPhotothequeUrl): string {
    $title = $gTitle($it);
    $when = $it['date'] !== '' ? $day($it['date']) : '';
    $capMeta = array_filter([$when !== '' && $when !== '—' ? $when : '', $it['place'] !== '' ? 'carroyage ' . $it['place'] : '']);
    $seek = is_array($it['seek'] ?? null) ? $it['seek'] : [];
    $rows = [];
    if (!$it['holder']) {
        $rows['Type'] = $gKinds[$it['kind']] ?? '';
        $rows['Source'] = $gSources[$it['source']] ?? '';
    }
    if ($it['ref'] !== '') {
        $rows['Fiche'] = $it['ref'];
    }
    if (trim((string) ($seek['verdict_label'] ?? '')) !== '') {
        $rows['Verdict'] = trim((string) $seek['verdict_label']);
    }
    if ($when !== '' && $when !== '—') {
        $rows['Date de rangement'] = $when;
    }
    if (trim((string) ($seek['game_time'] ?? '')) !== '') {
        $rows['Heure de la prise'] = trim((string) $seek['game_time']);
    }
    if ($it['place'] !== '') {
        $rows['Carroyage'] = $it['place'];
    }
    $who = trim((string) ($seek['controller_callsign'] ?? ''));
    $unit = trim((string) ($seek['unit'] ?? ''));
    if ($who !== '' || $unit !== '') {
        $rows['Contrôleur'] = trim($who . ($unit !== '' ? ' (' . $unit . ')' : ''));
    }
    $notes = [];
    foreach ((is_array($seek['capture'] ?? null) ? $seek['capture'] : []) as $capMod => $cap) {
        foreach (($cap['notes'] ?? []) as $capNote) {
            $notes[] = \App\Support\SseCaptureNotes::label((string) $capMod) . ' : ' . $capNote;
        }
    }
    $mid = $it['uid'];
    $o = '<a class="idg-thumb' . ($hero ? ' idg-thumb--hero' : '') . '" id="photo-' . $h($mid) . '" href="' . $h($it['src']) . '" data-idg-item data-uid="' . $h($mid) . '" data-src="' . $h($it['src']) . '"'
        . ' data-id="' . (int) $it['id'] . '"' . ($it['holder'] ? ' data-holder="1"' : '') . ($gManage && $it['src'] !== '' ? ' data-download="' . $h($it['src']) . '"' : '')
        . ($it['id'] > 0 ? ' data-library="' . $h($gPhotothequeUrl . '#photo-' . $it['id']) . '"' : '')
        . ' aria-label="' . $h('Ouvrir : ' . $title) . '">';
    if ($it['src'] !== '') {
        $o .= '<img src="' . $h($it['src']) . '" alt="" loading="lazy" decoding="async" width="' . ($hero ? 288 : 240) . '" height="' . ($hero ? 370 : 320) . '">';
    }
    $o .= '<span class="idg-gone">Image indisponible</span>';
    $badges = '';
    if ($it['holder']) {
        $badges .= '<span class="idg-badge idg-badge--accent">Portrait</span>';
    } elseif ($it['group'] === 'fiche') {
        $badges .= '<span class="idg-badge">' . $h($gKinds[$it['kind']] ?? 'Photo') . '</span>';
    } elseif ($it['group'] === 'seek' && trim((string) ($seek['verdict_label'] ?? '')) !== '') {
        $badges .= '<span class="idg-badge" translate="no">' . $h(trim((string) $seek['verdict_label'])) . '</span>';
    }
    if (!empty($it['masked'])) {
        $badges .= '<span class="idg-badge idg-badge--warn">Masquée</span>';
    }
    if ($badges !== '') {
        $o .= '<span class="idg-badges">' . $badges . '</span>';
    }
    $o .= '</a>';
    if (!$hero) {
        $o .= '<div class="idg-cap"><p class="idg-cap__t" translate="no" title="' . $h($title) . '">' . $h($title) . '</p>';
        if ($capMeta !== []) {
            $o .= '<p class="idg-cap__m">' . $h(implode(' · ', $capMeta)) . '</p>';
        }
        $o .= '</div>';
    }
    $o .= '<div class="idg-meta" hidden data-title="' . $h($title) . '"' . (!empty($it['masked']) ? ' data-masked="1"' : '') . '><dl>';
    foreach ($rows as $k => $v) {
        if ($v !== '') {
            $o .= '<dt>' . $h($k) . '</dt><dd translate="no">' . $h($v) . '</dd>';
        }
    }
    foreach ($notes as $n) {
        $o .= '<dt>Capture</dt><dd translate="no">' . $h($n) . '</dd>';
    }
    $sig = $gSeekSig($it);
    if ($sig !== '') {
        $o .= '<dt>Signature</dt><dd><img src="' . $h($sig) . '" alt="' . $h('Signature tracée au SEEK II') . '" loading="lazy" width="160" height="46" class="idg-sig"></dd>';
    }
    $o .= '</dl></div>';

    return $o;
};
?>
<?php if ($gLocked): ?>
    <div class="idg-state idg-state--locked">
        <svg viewBox="0 0 24 24" width="28" height="28" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><rect x="5" y="11" width="14" height="9" rx="1"/><path d="M8 11V8a4 4 0 0 1 8 0v3"/></svg>
        <strong>Photos réservées à l’habilitation Confidentiel</strong>
        <p>Les images de cette identité ne s’affichent qu’avec l’habilitation Confidentiel, comme sa nature.</p>
    </div>
<?php else: ?>
<div class="idg" data-idg>
    <div class="idg-top">
        <figure class="idg-hero<?= $gal['holder'] === null ? ' idg-hero--empty' : '' ?>">
            <?php if ($gal['holder'] !== null): ?>
                <?= $gTile($gal['holder'], true) ?>
            <?php else: ?>
                <span class="idg-hero__empty">
                    <svg viewBox="0 0 64 80" fill="currentColor" aria-hidden="true" focusable="false"><circle cx="32" cy="26" r="14"/><path d="M6 78c0-17 11-28 26-28s26 11 26 28z"/></svg>
                    <span>Pas de portrait</span>
                </span>
            <?php endif; ?>
        </figure>
        <div class="idg-summary">
            <h3 class="idg-summary__title">Portrait et signature du titulaire</h3>
            <p class="idg-summary__text">Affichés sur chaque papier émis pour cette identité, sous le filigrane SIMULATION. Sans portrait, le papier montre une silhouette.</p>
            <div class="idg-sign">
                <?php if ($gal['signature'] !== null): ?>
                    <img src="<?= $h($gal['signature']) ?>" alt="Signature du titulaire" loading="lazy" width="220" height="64">
                <?php else: ?>
                    <span>Pas de signature importée : signature écrite</span>
                <?php endif; ?>
            </div>
            <ul class="idg-counts" aria-label="Photos par source">
                <?php foreach ($gal['groups'] as $gk => $g): ?>
                    <?php if ($g['count'] > 0): ?>
                        <li><a href="#idg-<?= $h($gk) ?>"><?= $h($gGroupLabels[$gk]) ?> <b><?= (int) $g['count'] ?></b></a></li>
                    <?php endif; ?>
                <?php endforeach; ?>
                <?php if ($gal['masked'] > 0): ?><li><span class="idg-badge idg-badge--warn"><?= (int) $gal['masked'] ?> masquée(s)</span></li><?php endif; ?>
            </ul>
            <div class="idg-actions">
                <?php if ($gManage): ?>
                    <a class="btn btn--sm" href="<?= $h(url($filesSubject . '#photos')) ?>">Ajouter une photo</a>
                <?php endif; ?>
                <?php if ($gEdit): ?>
                    <a class="btn btn--ghost btn--sm" href="#images" data-idg-edit>Modifier le portrait</a>
                <?php endif; ?>
                <a class="btn btn--ghost btn--sm" href="<?= $h($gPhotothequeUrl) ?>">Photothèque</a>
            </div>
            <?php if ($fileMentions !== []): ?>
                <ul class="idg-mentions" aria-label="Mentions actives">
                    <?php foreach ($fileMentions as $m): ?>
                        <li class="idg-badge idg-badge--warn" title="<?= $h((string) ($m['note'] ?? '')) ?>">Mention : <?= $h((string) ($m['mention_label'] ?? '')) ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </div>

    <?php $imagesDocId = 0; $holderEditOnly = true; $personPhotos = $showPerson ? ($personPhotos ?? []) : []; require __DIR__ . '/_holder_images.php'; ?>

    <?php if ($gal['isEmpty']): ?>
        <div class="idg-state">
            <svg viewBox="0 0 24 24" width="28" height="28" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><rect x="3" y="5" width="18" height="14" rx="1"/><circle cx="9" cy="10.5" r="1.6"/><path d="m21 16-5-5-8 8"/></svg>
            <strong>Aucune photo pour cette identité</strong>
            <p>Versez une photo du personnage (uniquement des personnages du jeu) ou envoyez son portrait ci-dessus.</p>
            <p class="idg-state__sub">Ce qui fait remonter une image du SEEK II ici :</p>
            <ol class="idg-steps">
                <li>Un contrôleur photographie le visage de la personne au terminal SEEK II.</li>
                <li>Il signe et envoie la fiche, la liaison Athena étant active.</li>
                <li>La fiche est rattachée à cette identité par le papier présenté ou la clé biométrique. Sans l’un ni l’autre, l’image reste dans la photothèque, à rattacher.</li>
            </ol>
            <div class="idg-actions idg-actions--center">
                <?php if ($gManage): ?><a class="btn btn--sm" href="<?= $h(url($filesSubject . '#photos')) ?>">Verser une photo</a><?php endif; ?>
                <a class="btn btn--ghost btn--sm" href="<?= $h(url('atak/sse/fichiers/seek')) ?>">Fiches SEEK II</a>
                <a class="btn btn--ghost btn--sm" href="<?= $h($gPhotothequeUrl) ?>">Photothèque</a>
            </div>
        </div>
    <?php else: ?>
        <?php foreach ($gal['groups'] as $gk => $g): ?>
            <?php if ($g['items'] === []) { continue; } ?>
            <section class="idg-group" id="idg-<?= $h($gk) ?>" aria-labelledby="idg-<?= $h($gk) ?>-t">
                <header class="idg-group__head">
                    <h3 id="idg-<?= $h($gk) ?>-t"><?= $h($gGroupLabels[$gk]) ?></h3>
                    <span class="idg-group__n"><?= (int) $g['count'] ?></span>
                    <p><?= $h($gGroupHints[$gk]) ?></p>
                </header>
                <ul class="idg-grid">
                    <?php foreach (array_slice($g['items'], 0, SseIdentityPhotoGallery::VISIBLE) as $it): ?>
                        <li class="idg-tile"><?= $gTile($it) ?></li>
                    <?php endforeach; ?>
                </ul>
                <?php $gRest = array_slice($g['items'], SseIdentityPhotoGallery::VISIBLE); ?>
                <?php if ($gRest !== []): ?>
                    <details class="idg-more">
                        <summary>Afficher les <?= count($gRest) ?> autres</summary>
                        <ul class="idg-grid">
                            <?php foreach ($gRest as $it): ?>
                                <li class="idg-tile"><?= $gTile($it) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </details>
                <?php endif; ?>
            </section>
        <?php endforeach; ?>
    <?php endif; ?>

    <div class="idv" data-idg-viewer role="dialog" aria-modal="true" aria-label="Visionneuse de photos" hidden>
        <div class="idv__bar">
            <p class="idv__count" data-idv-count aria-live="polite"></p>
            <div class="idv__tools">
                <button type="button" class="idv__btn" data-idv="zoom-out" aria-label="Réduire"><svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true" focusable="false"><path d="M5 12h14"/></svg></button>
                <button type="button" class="idv__btn idv__btn--text" data-idv="zoom-reset" aria-label="Taille d’origine" data-idv-zoom>100 %</button>
                <button type="button" class="idv__btn" data-idv="zoom-in" aria-label="Agrandir"><svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true" focusable="false"><path d="M12 5v14M5 12h14"/></svg></button>
                <button type="button" class="idv__btn idv__btn--text" data-idv="compare" aria-pressed="false">Comparer</button>
                <button type="button" class="idv__btn" data-idv="close" aria-label="Fermer"><svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true" focusable="false"><path d="M6 6l12 12M18 6 6 18"/></svg></button>
            </div>
        </div>
        <div class="idv__main">
            <button type="button" class="idv__nav idv__nav--prev" data-idv="prev" aria-label="Photo précédente"><svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="m15 5-7 7 7 7"/></svg></button>
            <div class="idv__stage" data-idv-stage>
                <figure class="idv__fig"><img data-idv-img alt="" loading="lazy" decoding="async" draggable="false"><figcaption data-idv-cmp-label hidden>Portrait du titulaire</figcaption></figure>
                <figure class="idv__fig idv__fig--cmp" data-idv-cmp hidden><img data-idv-cmp-img alt="" loading="lazy" decoding="async" draggable="false"><figcaption>Photo de comparaison</figcaption></figure>
                <p class="idv__broken" data-idv-broken hidden>Image indisponible</p>
            </div>
            <button type="button" class="idv__nav idv__nav--next" data-idv="next" aria-label="Photo suivante"><svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="m9 5 7 7-7 7"/></svg></button>
            <aside class="idv__side">
                <h3 data-idv-title></h3>
                <div data-idv-meta></div>
                <div class="idv__acts">
                    <?php if ($gEdit): ?>
                        <form method="post" action="<?= $h(url($self . '/images')) ?>" data-idv-portrait hidden>
                            <?= \App\Core\Csrf::field() ?>
                            <input type="hidden" name="photo_library" value="" data-idv-portrait-id>
                            <button class="btn btn--sm" type="submit">Définir comme portrait</button>
                        </form>
                    <?php endif; ?>
                    <?php if ($gManage): ?><a class="btn btn--ghost btn--sm" href="#" data-idv-download download>Télécharger</a><?php endif; ?>
                    <a class="btn btn--ghost btn--sm" href="<?= $h($gPhotothequeUrl) ?>" data-idv-library>Voir dans la photothèque</a>
                </div>
                <p class="idv__hint">Flèches : photo suivante ou précédente. Échap : fermer. + et − : zoom.</p>
            </aside>
        </div>
    </div>
</div>
<?php endif; ?>
