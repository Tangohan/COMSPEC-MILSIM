<?php
declare(strict_types=1);
/**
 * Fiche d'une identité fictive, en dossier : en-tête (portrait, nom, alias, niveau de menace, statut), bandeau d'avis de
 * recherche actif, actions groupées et onglets (synthèse, papiers, photos, relations, historique, détections, avis).
 * Sans JavaScript, toutes les sections s'empilent. Données : SseIdentityRegistryController::identityShow et
 * SseIdentityDossierService ; chaque clé du dossier absente (ancienne communauté, étape de base de données en attente)
 * donne une section vide, jamais une erreur.
 *
 * @var array<string,mixed> $identity
 * @var array<string,mixed>|null $person
 * @var list<array<string,mixed>> $documents
 * @var list<array<string,mixed>> $countries
 */
use App\Controllers\Web\SseFileController;
use App\Repositories\SseAiProfileRepository;
use App\Services\Sse\SseFileSearchService;
use App\Services\Sse\SseIdentityHolderService;
use App\Services\Sse\SseIdentityPhotoGallery;
use App\Services\Sse\SseWantedAccess;
use App\Support\SseFileSubjectCatalog as S;
use App\Support\SseIdentityCatalog as C;
use App\Support\SseWantedCatalog as W;

ob_start();
$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$canSeeTruth = !empty($canSeeTruth);
$secret = in_array($identity['kind'], ['cover', 'forged'], true);
$showPerson = is_array($person ?? null) && ($canSeeTruth || !$secret);
$dlang = (string) ($lang ?? 'fr') === 'en' ? 'en' : 'fr'; // $lang est réutilisé comme compteur par les aides incluses : on garde $dlang.
$wr = ($wantedRights ?? null) instanceof SseWantedAccess ? $wantedRights : null;
$canWanted = $wr !== null && $wr->canManage();
$canPdf = $wr !== null && $wr->canGeneratePdf();
$notice = is_array($notice ?? null) ? $notice : null;
$notices = is_array($notices ?? null) ? $notices : [];
$detections = is_array($detections ?? null) ? $detections : [];
$timeline = is_array($timeline ?? null) ? $timeline : [];
$wantedReady = !empty($wantedReady);
$wantedConfig = is_array($wantedConfig ?? null) ? $wantedConfig : [];
$wantedTypes = is_array($wantedTypes ?? null) ? $wantedTypes : W::types();
$profile = is_array($profile ?? null) ? $profile : null;
$fileAliases = is_array($fileAliases ?? null) ? $fileAliases : [];
$fileLinks = is_array($fileLinks ?? null) ? $fileLinks : [];
$fileBackLinks = is_array($fileBackLinks ?? null) ? $fileBackLinks : [];
$filePhotos = is_array($filePhotos ?? null) ? $filePhotos : [];
$fileMentions = is_array($fileMentions ?? null) ? $fileMentions : [];
$detectionCounts = is_array($detectionCounts ?? null) ? $detectionCounts : ['new' => 0];
$timelineFilter = (string) ($timelineFilter ?? '');
$iid = (int) $identity['id'];
// Sujet contrôlé ou remonté du terrain : on y constate des papiers, on n'en génère pas.
$observedOnly = SseIdentityHolderService::isObservedOnly($identity);
$self = 'atak/sse/papiers/identites/' . $iid;
$filesSubject = 'atak/sse/fichiers/sujet/' . $iid;
$dt = static function (?string $v) use ($dlang): string {
    $v = trim((string) $v);
    if ($v === '') {
        return '—';
    }
    $ts = strtotime(str_contains($v, ':') ? $v . ' UTC' : $v . ' 12:00:00 UTC');

    return $ts === false ? $v : gmdate($dlang === 'en' ? 'Y-m-d H:i' : 'd/m/Y H:i', $ts);
};
$day = static function (?string $v) use ($dlang): string {
    $v = trim((string) $v);
    $ts = $v !== '' ? strtotime(substr($v, 0, 10) . ' 12:00:00 UTC') : false;

    return $ts === false ? '—' : gmdate($dlang === 'en' ? 'Y-m-d' : 'd/m/Y', $ts);
};
$age = SseFileSearchService::age($identity['birth_date'] ?? null, gmdate('Y-m-d'));
$display = (string) ($identity['display_name'] ?? '');
$photoUrl = ($identity['photo_path'] ?? null) !== null ? (string) url((string) $identity['photo_path']) : null;
if ($photoUrl === null) {
    foreach ($filePhotos as $fp) {
        $photoUrl = (string) user_media_public_url((string) $fp['path']);
        break;
    }
}
// Sans photo du titulaire ni du dossier : le cliché SEEK II de l'identité, puis la photo de la fiche personne liée.
if ($photoUrl === null && is_array($seekImages ?? null) && ($seekImages[0]['url'] ?? '') !== '') {
    $photoUrl = (string) $seekImages[0]['url'];
}
if ($photoUrl === null && $showPerson && is_array($person['primary_photo'] ?? null) && ($person['primary_photo']['url'] ?? '') !== '') {
    $photoUrl = (string) $person['primary_photo']['url'];
}
if ($secret && !$canSeeTruth) {
    $photoUrl = null; // couverture ou fausse identité sans l'habilitation : ni portrait ni cliché dans l'en-tête
}
$aliasLine = [];
if (($identity['alias'] ?? null) !== null) {
    $aliasLine[] = (string) $identity['alias'];
}
foreach ($fileAliases as $fa) {
    if (!in_array((string) $fa['alias'], $aliasLine, true)) {
        $aliasLine[] = (string) $fa['alias'];
    }
}
$threatTone = static fn (string $level): string => match ($level) {
    'critical', 'high' => 'bad',
    'medium' => 'warn',
    default => 'ok',
};
$photoKinds = ['front' => 'Face', 'profile' => 'Profil', 'field' => 'Terrain', 'document' => 'Papier'];
$noticeTypeLabel = $notice !== null ? W::typeLabel((string) $notice['notice_type'], $wantedConfig, $dlang) : '';
$aiLinks = [];
try {
    $aiLinks = (new SseAiProfileRepository())->forIdentity((int) ($identity['tenant_id'] ?? ($wr !== null ? $wr->tenantId() : 0)), $iid);
} catch (\Throwable) {
}
$photoGallery = SseIdentityPhotoGallery::build(
    $identity,
    $filePhotos,
    is_array($seekImages ?? null) ? $seekImages : [],
    null,
    ($identity['photo_path'] ?? null) !== null ? (string) url((string) $identity['photo_path']) : null,
    ($identity['signature_path'] ?? null) !== null ? (string) url((string) $identity['signature_path']) : null
);
$photoCount = ($canSeeTruth || !$secret) ? $photoGallery['total'] : 0;
$tabs = [
    ['synthese', 'Synthèse', null, false],
    ['papiers', $observedOnly ? 'Papiers constatés' : 'Papiers et documents', count($documents), false],
    ['photos', 'Photos', $photoCount, false],
    ['relations', 'Relations et affiliations', count($fileLinks) + count($fileBackLinks) + count($aiLinks), false],
    ['historique', 'Historique', count($timeline), false],
    ['detections', 'Détections', count($detections), (int) ($detectionCounts['new'] ?? 0) > 0],
    ['avis', 'Avis de recherche', $notice !== null ? '1' : (count($notices) > 0 ? (string) count($notices) : null), $notice !== null],
    ['documents-emis', 'Documents émis', count((array) ($sheetMenu['issues'] ?? [])) ?: null, false],
];
$sseExtraStyles = ($sseExtraStyles ?? '') . '<link rel="stylesheet" href="' . $h(asset_url('assets/css/sse-identity-dossier.css')) . '"><link rel="stylesheet" href="' . $h(asset_url('assets/css/sse-identity-photos.css')) . '">';
$sseExtraScripts = ($sseExtraScripts ?? '') . '<script src="' . $h(asset_url('assets/js/sse-identity-dossier.js')) . '" defer></script><script src="' . $h(asset_url('assets/js/sse-identity-photos.js')) . '" defer></script>';
require __DIR__ . '/_subnav.php';
?>

<div data-dos>
    <header class="dos-head">
        <div class="dos-portrait">
            <?php if ($photoUrl !== null): ?>
                <img src="<?= $h($photoUrl) ?>" alt="Portrait de l’identité" width="112" height="144" decoding="async" data-dos-portrait-img>
            <?php endif; ?>
            <svg viewBox="0 0 64 80" fill="currentColor" aria-hidden="true" focusable="false"><circle cx="32" cy="26" r="14"/><path d="M6 78c0-17 11-28 26-28s26 11 26 28z"/></svg>
        </div>
        <div class="dos-id">
            <h1 translate="no"><?= $h($display) ?></h1>
            <?php if ($aliasLine !== []): ?>
                <p class="dos-alias">Alias : <span translate="no"><?= $h(implode(' · ', array_slice($aliasLine, 0, 5))) ?></span></p>
            <?php endif; ?>
            <ul class="dos-chips">
                <li class="dos-chip dos-chip--mono"><?= $h($identity['ref']) ?></li>
                <li class="dos-chip"><?= $h(C::label(C::SEXES, $identity['sex'])) ?></li>
                <?php if ($age !== null): ?><li class="dos-chip"><?= (int) $age ?> ans</li><?php endif; ?>
                <?php if (($identity['nationality'] ?? null) !== null): ?><li class="dos-chip"><?= $h($identity['nationality']) ?></li><?php endif; ?>
                <?php if ($canSeeTruth): ?><li class="dos-chip"><?= $h($identity['kind_label']) ?></li><?php endif; ?>
                <?php if ($notice !== null): ?>
                    <li class="dos-chip dos-chip--<?= $h($threatTone((string) $notice['threat_level'])) ?>">Menace : <?= $h(mb_strtolower(W::label(W::THREATS, (string) $notice['threat_level'], $dlang))) ?></li>
                    <li class="dos-chip dos-chip--bad">Sous avis de recherche</li>
                <?php else: ?>
                    <li class="dos-chip dos-chip--ok">Aucun avis de recherche</li>
                <?php endif; ?>
                <li class="dos-chip"><?= count($documents) ?> papier(s)</li>
            </ul>
        </div>
        <div class="dos-actions">
            <?php if ($canWanted && $wantedReady): ?>
                <?php if ($notice === null): ?>
                    <a class="btn btn--danger" href="<?= $h(url($self . '/avis/nouveau')) ?>">Placer un avis de recherche</a>
                <?php else: ?>
                    <a class="btn btn--danger" href="<?= $h(url($self . '#avis')) ?>" data-dos-go>Lever l’avis</a>
                <?php endif; ?>
            <?php endif; ?>
            <?php if (!empty($canManage)): ?>
                <a class="btn" href="<?= $h(url($self . '#papiers')) ?>" data-dos-go><?= $observedOnly ? 'Consigner un papier constaté' : 'Générer un papier' ?></a>
            <?php endif; ?>
            <details class="dos-menu">
                <summary>Autres actions</summary>
                <div class="dos-menu__list">
                    <a href="<?= $h(url($filesSubject . '#signalement')) ?>">Modifier le signalement</a>
                    <a href="<?= $h(url($filesSubject . '#photos')) ?>">Verser une photo</a>
                    <a href="<?= $h(url($filesSubject . '#mentions')) ?>">Mentionner aux fichiers</a>
                    <a href="<?= $h(url($self . '#photos')) ?>" data-dos-go>Photo et signature du titulaire</a>
                    <a href="<?= $h(SseFileController::replayUrl('QW', ['last' => $identity['last_name'], 'first' => $identity['first_name'], 'dob' => $identity['birth_date'] ?? ''])) ?>">Passer aux fichiers</a>
                    <a href="<?= $h(url($filesSubject)) ?>">Fiche des fichiers</a>
                    <?php if ($showPerson): ?><a href="<?= $h(url('atak/sse/identites/' . (int) $person['id'] . '?fiche=personne')) ?>">Fiche personne</a><?php endif; ?>
                </div>
            </details>
        </div>
    </header>

    <?php require dirname(__DIR__) . '/_sheet_menu.php'; ?>

    <?php if ($notice !== null): ?>
        <section class="dos-wanted" aria-label="Avis de recherche actif">
            <p class="dos-wanted__title">Avis de recherche actif</p>
            <ul class="dos-wanted__meta">
                <li><strong translate="no"><?= $h($notice['ref']) ?></strong></li>
                <li><?= $h($noticeTypeLabel) ?></li>
                <li>Niveau de menace : <strong><?= $h(mb_strtolower(W::label(W::THREATS, (string) $notice['threat_level'], $dlang))) ?></strong></li>
                <li><?= $h(W::label(W::ARMED, (string) $notice['armed'], $dlang)) ?></li>
                <li>Émis le <?= $h($dt($notice['issued_at'])) ?></li>
                <?php if ($notice['expires_at'] !== null): ?><li>Expire le <?= $h($dt($notice['expires_at'])) ?></li><?php endif; ?>
            </ul>
            <?php if (($notice['reason'] ?? null) !== null): ?><p class="dos-wanted__reason" translate="no"><?= $h(mb_strimwidth((string) $notice['reason'], 0, 260, '…', 'UTF-8')) ?></p><?php endif; ?>
            <div class="dos-wanted__acts">
                <a class="btn btn--ghost" href="<?= $h(url($self . '#avis')) ?>" data-dos-go>Voir l’avis</a>
                <?php if ($canPdf): ?>
                    <a class="btn" href="<?= $h(url('atak/sse/avis/' . (int) $notice['id'] . '/pdf')) ?>">Télécharger la notice PDF</a>
                    <a class="btn btn--ghost" href="<?= $h(url('atak/sse/avis/' . (int) $notice['id'] . '/pdf?mode=impression')) ?>" target="_blank" rel="noopener">Imprimer</a>
                <?php endif; ?>
            </div>
        </section>
    <?php endif; ?>

    <nav class="dos-tabs" role="tablist" aria-label="Sections du dossier">
        <?php foreach ($tabs as $k => [$tid, $tlabel, $tcount, $talert]): ?>
            <a class="dos-tab" role="tab" id="tab-<?= $h($tid) ?>" href="#<?= $h($tid) ?>" aria-controls="<?= $h($tid) ?>" aria-selected="<?= $k === 0 ? 'true' : 'false' ?>"><?= $h($tlabel) ?><?php if ($tcount !== null): ?> <span class="dos-tab__n<?= $talert ? ' dos-tab__n--alert' : '' ?>"><?= $h($tcount) ?></span><?php endif; ?></a>
        <?php endforeach; ?>
    </nav>

    <section class="dos-panel" id="synthese" role="tabpanel" aria-labelledby="tab-synthese" tabindex="-1">
        <h2 class="dos-panel__title">Synthèse</h2>
        <div class="dos-stats">
            <div class="dos-stat"><strong><?= count($documents) ?></strong><span>Papiers</span></div>
            <div class="dos-stat"><strong><?= $photoCount ?></strong><span>Photos</span></div>
            <div class="dos-stat"><strong><?= count($fileLinks) + count($fileBackLinks) ?></strong><span>Relations</span></div>
            <div class="dos-stat"><strong><?= count($detections) ?></strong><span>Détections</span></div>
            <div class="dos-stat"><strong><?= count($notices) ?></strong><span>Avis de recherche</span></div>
        </div>
        <div class="dos-grid">
            <article class="dos-card">
                <h3>État civil</h3>
                <div class="dos-card__body">
                    <dl class="dos-kv">
                        <dt>Nom</dt><dd translate="no"><?= $h(mb_strtoupper((string) $identity['last_name'])) ?></dd>
                        <dt>Prénom</dt><dd translate="no"><?= $h($identity['first_name']) ?></dd>
                        <dt>Naissance</dt><dd><?= $h(C::birthDateLabel($identity['birth_date'] ?? null)) ?></dd>
                        <dt>Lieu de naissance</dt><dd class="<?= ($identity['birth_place'] ?? null) === null ? 'is-empty' : '' ?>"><?= $h($identity['birth_place'] ?? 'Non renseigné') ?></dd>
                        <dt>Nationalité</dt><dd class="<?= ($identity['nationality'] ?? null) === null ? 'is-empty' : '' ?>"><?= $h($identity['nationality'] ?? 'Non renseignée') ?></dd>
                        <dt>Profession</dt><dd class="<?= ($identity['profession'] ?? null) === null ? 'is-empty' : '' ?>"><?= $h($identity['profession'] ?? 'Non renseignée') ?></dd>
                        <dt>Langue</dt><dd class="<?= ($identity['language'] ?? null) === null ? 'is-empty' : '' ?>"><?= $h($identity['language'] ?? 'Non renseignée') ?></dd>
                        <dt>Nature</dt><dd><?= $canSeeTruth ? $h($identity['kind_label']) : '<span class="idp-hidden">Réservée à l’habilitation Confidentiel</span>' ?></dd>
                        <?php if ($showPerson): ?><dt>Fiche personne</dt><dd><a class="link" href="<?= $h(url('atak/sse/identites/' . (int) $person['id'] . '?fiche=personne')) ?>"><?= $h($person['display_name'] ?? ('IDN-' . (int) $person['id'])) ?></a></dd><?php endif; ?>
                        <dt>Personnage en jeu</dt><dd class="<?= ($identity['arma_key'] ?? null) === null ? 'is-empty' : '' ?>"><?= $h($identity['arma_key'] ?? 'Aucun') ?></dd>
                        <dt>Carte</dt><dd><?= $identity['context_id'] !== null ? '#' . (int) $identity['context_id'] : 'Toutes les cartes' ?></dd>
                        <dt>Opération</dt><dd><?= $h($identity['op_key'] ?? 'Toutes les opérations') ?></dd>
                        <?php if ($identity['seed'] !== null): ?><dt>Graine du tirage</dt><dd><?= (int) $identity['seed'] ?></dd><?php endif; ?>
                    </dl>
                    <?php if (!empty($identity['notes'])): ?><p class="muted"><?= nl2br($h($identity['notes'])) ?></p><?php endif; ?>
                </div>
            </article>

            <article class="dos-card">
                <h3>Signalement et signes particuliers</h3>
                <div class="dos-card__body">
                    <?php
                    $sig = [];
                    if ($profile !== null) {
                        if (($profile['height_cm'] ?? null) !== null) { $sig[] = ['Taille', (int) $profile['height_cm'] . ' cm']; }
                        if (($profile['weight_kg'] ?? null) !== null) { $sig[] = ['Poids', (int) $profile['weight_kg'] . ' kg']; }
                        if (($profile['build'] ?? null) !== null) { $sig[] = ['Corpulence', S::label(S::BUILDS, (string) $profile['build'])]; }
                        if (($profile['hair'] ?? null) !== null) { $sig[] = ['Cheveux', S::label(S::HAIR, (string) $profile['hair'])]; }
                        if (($profile['eyes'] ?? null) !== null) { $sig[] = ['Yeux', S::label(S::EYES, (string) $profile['eyes'])]; }
                        if (($profile['complexion'] ?? null) !== null) { $sig[] = ['Teint', (string) $profile['complexion']]; }
                        if (($profile['marks'] ?? null) !== null) { $sig[] = ['Signes particuliers', (string) $profile['marks']]; }
                    }
                    ?>
                    <?php if ($sig === []): ?>
                        <div class="dos-empty"><strong>Signalement non renseigné</strong>Taille, corpulence, cheveux, yeux et signes particuliers s’affichent ici une fois saisis.<?php if (!empty($canManage)): ?> <a href="<?= $h(url($filesSubject . '#signalement')) ?>">Renseigner le signalement</a><?php endif; ?></div>
                    <?php else: ?>
                        <dl class="dos-kv"><?php foreach ($sig as [$k, $v]): ?><dt><?= $h($k) ?></dt><dd translate="no"><?= $h($v) ?></dd><?php endforeach; ?></dl>
                    <?php endif; ?>
                </div>
            </article>

            <article class="dos-card">
                <h3>Alias, affiliations et mentions</h3>
                <div class="dos-card__body">
                    <?php $factionLine = trim((string) ($identity['faction'] ?? '') . ' ' . (string) ($identity['side'] ?? '')); ?>
                    <dl class="dos-kv">
                        <dt>Alias</dt><dd class="<?= $aliasLine === [] ? 'is-empty' : '' ?>" translate="no"><?= $aliasLine === [] ? 'Aucun alias connu' : $h(implode(' · ', $aliasLine)) ?></dd>
                        <dt>Affiliation</dt><dd class="<?= ($profile['affiliation'] ?? null) === null ? 'is-empty' : '' ?>"><?= $h($profile['affiliation'] ?? 'Aucune connue') ?></dd>
                        <dt>Faction et camp</dt><dd class="<?= $factionLine === '' ? 'is-empty' : '' ?>"><?= $h($factionLine !== '' ? $factionLine : 'Non renseignés') ?></dd>
                        <dt>Mentions actives</dt>
                        <dd class="<?= $fileMentions === [] ? 'is-empty' : '' ?>"><?php if ($fileMentions === []): ?>Aucune mention<?php else: ?><?php foreach ($fileMentions as $m): ?><span class="dos-chip dos-chip--warn"><?= $h($m['mention_label']) ?></span> <?php endforeach; ?><?php endif; ?></dd>
                    </dl>
                    <?php if ($aiLinks !== []): ?>
                        <p class="muted">Profils IA reliés : <?php foreach ($aiLinks as $i => $aiP): ?><?= $i > 0 ? ', ' : '' ?><a class="link" href="<?= $h(url('atak/sse/papiers/profils-ia/' . (int) $aiP['id'])) ?>"><?= $h($aiP['ref'] . ' · ' . $aiP['label']) ?></a><?php endforeach; ?></p>
                    <?php endif; ?>
                </div>
            </article>
        </div>
    </section>

    <section class="dos-panel" id="papiers" role="tabpanel" aria-labelledby="tab-papiers" tabindex="-1">
        <h2 class="dos-panel__title"><?= $observedOnly ? 'Papiers constatés' : 'Papiers et documents' ?></h2>
        <p class="muted" style="margin:0 0 10px"><?= $observedOnly
            ? 'Papiers constatés au contrôle : ce qui a été relevé sur ce sujet. Papiers émis par le service : seulement pour les identités des membres, les couvertures et les faux préparés, jamais pour un sujet contrôlé.'
            : 'Papiers émis par le service : ils partent en jeu une fois émis. Les papiers relevés sur un sujet contrôlé se consignent à part, sur sa fiche.' ?></p>
        <div class="panel">
            <div class="panel-header">
                <div class="panel-title"><?= $observedOnly ? 'Papiers constatés au contrôle' : 'Papiers émis ou préparés' ?></div>
                <div class="panel-meta"><?= count($documents) ?> papier(s)</div>
            </div>
            <?php if ($documents === []): ?>
                <div class="empty-state"><div class="empty-state-inner"><strong>Aucun papier</strong><p>Ajoutez une carte, un passeport ou un permis : le numéro et la bande de lecture sont calculés pour vous.</p></div></div>
            <?php else: ?>
                <div class="table-wrap">
                    <table>
                        <thead><tr><th>Référence</th><th>Type</th><th>Pays</th><th>Numéro</th><th>Expire le</th><th>État</th><?php if ($observedOnly): ?><th>Authenticité</th><?php endif; ?><th></th></tr></thead>
                        <tbody>
                        <?php foreach ($documents as $d): ?>
                            <tr>
                                <td data-label="Référence"><?= $h($d['ref']) ?></td>
                                <td data-label="Type"><?= $h($d['doc_type_label']) ?></td>
                                <td data-label="Pays"><?= $h($d['country_code']) ?></td>
                                <td data-label="Numéro"><code><?= $h($d['doc_number']) ?></code></td>
                                <td data-label="Expire le"><?= $h($d['expires_on'] ?? '—') ?></td>
                                <td data-label="État"><?= $h($d['workflow_label']) ?><?php if ($observedOnly && $d['workflow'] !== 'observed'): ?> <span class="badge badge--gray">Généré avant : lecture seule</span><?php endif; ?></td>
                                <?php if ($observedOnly): ?><td data-label="Authenticité"><?= $d['workflow'] === 'observed' ? $h(C::label(C::AUTHENTICITY, SseIdentityHolderService::authenticityOf($d))) : '—' ?></td><?php endif; ?>
                                <td><a class="btn-open" href="<?= $h(url('atak/sse/papiers/documents/' . (int) $d['id'])) ?>">Ouvrir</a></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
            <?php if (!empty($canManage) && $observedOnly): ?>
                <form class="panel-body idp-actions" method="post" action="<?= $h(url($self . '/constates')) ?>">
                    <?= \App\Core\Csrf::field() ?>
                    <label for="obs_doc_type" class="muted">Papier constaté</label>
                    <select id="obs_doc_type" name="doc_type">
                        <?php foreach (C::DOC_TYPES as $k => $label): ?>
                            <option value="<?= $h($k) ?>"><?= $h($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <input name="country_code" maxlength="3" size="4" placeholder="Pays" aria-label="Pays lu sur le papier" required>
                    <input name="doc_number" maxlength="20" placeholder="Numéro" aria-label="Numéro lu sur le papier" required>
                    <input name="issued_on" type="date" aria-label="Délivré le">
                    <input name="expires_on" type="date" aria-label="Expire le">
                    <select name="authenticity" aria-label="Authenticité constatée">
                        <?php foreach (C::AUTHENTICITY as $k => $label): ?>
                            <option value="<?= $h($k) ?>"><?= $h($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <select name="source" aria-label="Source du constat">
                        <?php foreach (C::OBSERVED_SOURCES as $k => $label): ?>
                            <option value="<?= $h($k) ?>"><?= $h($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <input name="seen_on" type="date" aria-label="Date du constat" value="<?= $h(gmdate('Y-m-d')) ?>">
                    <input name="place" maxlength="120" placeholder="Lieu du constat" aria-label="Lieu du constat">
                    <button class="btn" type="submit">Consigner le papier constaté</button>
                </form>
            <?php elseif (!empty($canManage)): ?>
                <form class="panel-body idp-actions" method="get" action="<?= $h(url('atak/sse/papiers/documents/nouveau')) ?>">
                    <input type="hidden" name="identity_id" value="<?= $iid ?>">
                    <label for="new_doc_type" class="muted">Nouveau papier</label>
                    <select id="new_doc_type" name="doc_type">
                        <?php foreach (C::DOC_TYPES as $k => $label): ?>
                            <option value="<?= $h($k) ?>"><?= $h($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <select name="country" aria-label="Pays émetteur">
                        <?php foreach ($countries as $c): ?>
                            <option value="<?= $h($c['code']) ?>" <?= ($identity['nationality'] ?? '') === $c['code'] ? 'selected' : '' ?>><?= $h($c['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button class="btn" type="submit">Préparer le papier</button>
                </form>
            <?php endif; ?>
        </div>
    </section>

    <section class="dos-panel" id="photos" role="tabpanel" aria-labelledby="tab-photos" tabindex="-1">
        <h2 class="dos-panel__title">Photos</h2>
        <?php require __DIR__ . '/_dossier_photos.php'; ?>
    </section>

    <section class="dos-panel" id="relations" role="tabpanel" aria-labelledby="tab-relations" tabindex="-1">
        <h2 class="dos-panel__title">Relations et affiliations</h2>
        <?php if ($fileLinks === [] && $fileBackLinks === [] && $aiLinks === []): ?>
            <div class="dos-card"><div class="dos-empty"><strong>Aucune relation connue</strong>Adresses, véhicules, téléphones, courriels et personnes liées apparaissent ici une fois rattachés à la fiche.<?php if (!empty($canManage)): ?> <a href="<?= $h(url($filesSubject . '#liens')) ?>">Ajouter une relation</a><?php endif; ?></div></div>
        <?php else: ?>
            <div class="dos-grid">
                <?php
                $byKind = [];
                foreach ($fileLinks as $l) {
                    $byKind[(string) $l['kind']][] = $l;
                }
                ?>
                <?php foreach ($byKind as $kind => $rows): ?>
                    <article class="dos-card">
                        <h3><?= $h(S::label(S::LINK_KINDS, (string) $kind, (string) $kind)) ?> <span class="muted">(<?= count($rows) ?>)</span></h3>
                        <div class="dos-card__body"><ul class="dos-list">
                            <?php foreach ($rows as $l): ?>
                                <li translate="no"><?php if ($l['linked_identity_id'] !== null): ?><a class="link" href="<?= $h(url('atak/sse/papiers/identites/' . (int) $l['linked_identity_id'])) ?>"><?= $h($l['value']) ?></a><?php else: ?><?= $h($l['value']) ?><?php endif; ?><?php if (($l['relation_label'] ?? '') !== '' && $l['relation_label'] !== '—'): ?><small><?= $h($l['relation_label']) ?></small><?php endif; ?><?php if (($l['flag_label'] ?? '') !== '' && $l['flag_label'] !== '—'): ?><small><?= $h($l['flag_label']) ?></small><?php endif; ?></li>
                            <?php endforeach; ?>
                        </ul></div>
                    </article>
                <?php endforeach; ?>
                <?php if ($fileBackLinks !== []): ?>
                    <article class="dos-card">
                        <h3>Citée comme personne liée <span class="muted">(<?= count($fileBackLinks) ?>)</span></h3>
                        <div class="dos-card__body"><ul class="dos-list">
                            <?php foreach ($fileBackLinks as $l): ?>
                                <li><a class="link" href="<?= $h(url('atak/sse/papiers/identites/' . (int) $l['identity_id'])) ?>"><?= $h(C::identityRef((int) $l['identity_id'])) ?></a><?php if (($l['relation_label'] ?? '') !== '' && $l['relation_label'] !== '—'): ?><small><?= $h($l['relation_label']) ?></small><?php endif; ?></li>
                            <?php endforeach; ?>
                        </ul></div>
                    </article>
                <?php endif; ?>
                <?php if ($aiLinks !== []): ?>
                    <article class="dos-card">
                        <h3>Profils IA reliés <span class="muted">(<?= count($aiLinks) ?>)</span></h3>
                        <div class="dos-card__body"><ul class="dos-list">
                            <?php foreach ($aiLinks as $aiP): ?>
                                <li><a class="link" href="<?= $h(url('atak/sse/papiers/profils-ia/' . (int) $aiP['id'])) ?>"><?= $h($aiP['ref'] . ' · ' . $aiP['label']) ?></a></li>
                            <?php endforeach; ?>
                        </ul></div>
                    </article>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </section>

    <section class="dos-panel" id="historique" role="tabpanel" aria-labelledby="tab-historique" tabindex="-1">
        <h2 class="dos-panel__title">Historique</h2>
        <?php require __DIR__ . '/_dossier_timeline.php'; ?>
    </section>

    <section class="dos-panel" id="detections" role="tabpanel" aria-labelledby="tab-detections" tabindex="-1">
        <h2 class="dos-panel__title">Détections</h2>
        <?php require __DIR__ . '/_dossier_detections.php'; ?>
    </section>

    <section class="dos-panel" id="avis" role="tabpanel" aria-labelledby="tab-avis" tabindex="-1">
        <h2 class="dos-panel__title">Avis de recherche</h2>
        <?php require __DIR__ . '/_dossier_notice.php'; ?>
    </section>

    <section class="dos-panel" id="documents-emis" role="tabpanel" aria-labelledby="tab-documents-emis" tabindex="-1">
        <h2 class="dos-panel__title">Documents émis</h2>
        <?php $issuesEmbedded = true; if (!empty($sheetMenu['ready']) && ($sheetMenu['reason'] ?? null) === null) { require dirname(__DIR__) . '/_sheet_issues.php'; } ?>
    </section>
</div>
<?php
$sseContent = ob_get_clean();
require dirname(__DIR__) . '/_layout.php';
