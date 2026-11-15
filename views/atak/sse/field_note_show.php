<?php
declare(strict_types=1);
ob_start();
$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
/**
 * Bureau SSE — lecture d'une fiche de renseignement.
 * En-tête (référence, état, cotation, actions), puis deux colonnes : le document à gauche, d'un seul tenant, et à droite
 * une colonne à onglets (Essentiel, Étiquettes, Suivi, Pièces jointes, Dossier, Liaisons). Les formulaires s'ouvrent dans des fenêtres.
 * Styles : public/assets/css/sse-fiche-rens.css (classes .fnr-*) ; script : public/assets/js/sse-fiche-rens.js (aucun texte).
 * Étiquettes de style américain (SseFieldNoteTagService::decorate) : marquage de diffusion dans le bandeau et le cartouche,
 * besoin, discipline et produit dans le cartouche, thèmes en pastilles, mention de validité, onglet « Étiquettes » filtrable.
 * Styles et script des étiquettes : sse-fiches-etiquettes.css / .js (.fnt-*, .fnp-*).
 *
 * @var array<string,mixed> $note
 * @var array<string,mixed>|null $linkedCase
 * @var list<array{id:int,label:string}> $openCases
 * @var array<string,string> $statusOptions
 * @var list<array{at:string,actor:string,label:string,from:string,to:string,body:string}> $history
 * @var int $attachmentsMax
 * @var bool $canManage
 * @var bool $canWrite
 * @var array<string, array{key: string, label: string, hint: string, tags: list<array<string, mixed>>}> $tagFamilies étiquettes à proposer
 * @var bool $tagsReady
 */
$note = is_array($note ?? null) ? $note : [];
$attachments = is_array($note['attachments'] ?? null) ? $note['attachments'] : [];
$openCases = is_array($openCases ?? null) ? $openCases : [];
$statusOptions = is_array($statusOptions ?? null) ? $statusOptions : [];
$history = is_array($history ?? null) ? $history : [];
$linkedCase = is_array($linkedCase ?? null) && !empty($linkedCase['id']) ? $linkedCase : null;
$attachmentsMax = (int) ($attachmentsMax ?? 4);
$canManage = (bool) ($canManage ?? false);
$canWrite = (bool) ($canWrite ?? false);
$noteId = (int) ($note['id'] ?? 0);
$csrf = \App\Core\Csrf::token();
$ref = (string) ($note['reference_code'] ?? '');
$kindCode = (string) ($note['note_kind'] ?? '');
$kindLabel = (string) ($note['note_kind_label'] ?? '');
$noteTitle = trim((string) ($note['title'] ?? ''));

$classCode = \App\Repositories\SseCaseRepository::normalizeClassification((string) ($note['classification'] ?? 'interne'));
$classLabel = \App\Services\Sse\SseRedactionService::levelLabel($classCode);
$classBand = mb_strtoupper($classLabel, 'UTF-8');
$reliability = [
    'A' => 'Source sûre', 'B' => 'Source habituellement sûre', 'C' => 'Source assez sûre',
    'D' => 'Source pas toujours sûre', 'E' => 'Source peu sûre', 'F' => 'Fiabilité inconnue',
];
$credibility = [
    1 => 'Information confirmée', 2 => 'Information probable', 3 => 'Information possible',
    4 => 'Information douteuse', 5 => 'Information improbable', 6 => 'Véracité inconnue',
];
$rel = strtoupper((string) ($note['source_reliability'] ?? 'C'));
$cred = (int) ($note['info_credibility'] ?? 3);
$relLabel = $reliability[$rel] ?? 'Fiabilité inconnue';
$credLabel = $credibility[$cred] ?? 'Véracité inconnue';
$srcCode = (string) ($note['intel_source'] ?? '');
$srcLabel = (string) ($note['intel_source_label'] ?? '');
$urgency = (string) ($note['urgency'] ?? 'routine');
$urgencyLabel = (string) ($note['urgency_label'] ?? '');
$urgencyHint = (string) (\App\Support\SseFieldNoteCatalog::URGENCIES[$urgency]['hint'] ?? '');
$urgencyTone = (string) (\App\Support\SseFieldNoteCatalog::URGENCIES[$urgency]['tone'] ?? 'neutral');
$status = (string) ($note['status'] ?? '');
$statusLabel = (string) ($note['status_label'] ?? '');
$statusTone = ['brouillon' => 'idle', 'transmise' => 'new', 'prise_en_compte' => 'busy', 'exploitee' => 'done', 'sans_suite' => 'idle'][$status] ?? 'idle';
$statusHints = [
    'brouillon' => 'La fiche n’est pas encore transmise au bureau.',
    'transmise' => 'La fiche est arrivée au bureau, personne ne l’a encore prise en charge.',
    'prise_en_compte' => 'Une analyste exploite la fiche (recoupement, vérification).',
    'exploitee' => 'Le renseignement a servi : ordre, dossier ou carte.',
    'sans_suite' => 'La fiche est classée : aucune suite n’est donnée.',
];
$triageNote = trim((string) ($note['triage_note'] ?? ''));
$triagedTs = !empty($note['triaged_at']) ? strtotime((string) $note['triaged_at']) : false;
$observedDate = (string) ($note['observed_date_label'] ?? '');
$observedTime = (string) ($note['observed_time_label'] ?? '');
$place = trim((string) ($note['place_label'] ?? ''));
$grid = trim((string) ($note['grid_reference'] ?? ''));
$hasLatLng = ($note['lat'] ?? null) !== null && ($note['lng'] ?? null) !== null;
$hasGame = ($note['pos_x'] ?? null) !== null && ($note['pos_y'] ?? null) !== null;
$latLng = $hasLatLng ? sprintf('%.5f / %.5f', (float) $note['lat'], (float) $note['lng']) : '';
$gamePos = $hasGame ? sprintf('%.0f / %.0f', (float) $note['pos_x'], (float) $note['pos_y']) : '';
$hasPosition = $grid !== '' || $hasLatLng || $hasGame;
$copyPosition = $grid !== '' ? $grid : ($hasGame ? $gamePos : $latLng);
$paragraphs = preg_split("/\R{2,}/u", trim((string) ($note['body'] ?? ''))) ?: [];
$paragraphs = array_values(array_filter($paragraphs, static fn (string $p): bool => trim($p) !== ''));
$themes = is_array($note['themes'] ?? null) ? $note['themes'] : [];
$author = trim((string) ($note['author_label'] ?? '')) !== '' ? (string) $note['author_label'] : 'Inconnu';
$authorUnit = trim((string) ($note['author_unit'] ?? ''));
$attCount = count($attachments);
$canAttach = $canWrite && $attCount < $attachmentsMax;
$canOpenIntake = !$canManage ? false : \App\Controllers\Admin\AdminIntelIntakeController::allowed();
$base = 'atak/sse/fiches/' . $noteId;
$ico = static fn (string $d): string => '<svg class="fnr-i" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="' . $d . '"/></svg>';
$icons = [
    'back' => 'M15 5l-7 7 7 7',
    'flag' => 'M5 21V4h12l-2 4 2 4H5',
    'folder' => 'M3 6h6l2 2h10v11H3z',
    'clip' => 'M20 11l-8.5 8.5a5 5 0 0 1-7-7L13 4a3.5 3.5 0 0 1 5 5l-8.5 8.5a2 2 0 0 1-3-3L14 7',
    'print' => 'M7 9V4h10v5M7 17H4v-7h16v7h-3M7 14h10v6H7z',
    'more' => 'M5 12h.01M12 12h.01M19 12h.01',
    'copy' => 'M9 9h11v11H9zM5 15V4h11',
    'close' => 'M6 6l12 12M18 6L6 18',
];
// —— Étiquettes de la fiche ——
$tagCat = \App\Support\SseFieldNoteTagCatalog::class;
$tagLang = function_exists('locale') && locale() === 'en' ? 'en' : 'fr';
$tagList = is_array($note['tags'] ?? null) ? $note['tags'] : [];
$tagGroups = is_array($note['tag_groups'] ?? null) ? $note['tag_groups'] : [];
$tagFamilies = is_array($tagFamilies ?? null) ? $tagFamilies : [];
$tagsReady = (bool) ($tagsReady ?? false);
$canTag = $canWrite && $tagsReady && $tagFamilies !== [];
$categories = is_array($note['tag_categories'] ?? null) ? $note['tag_categories'] : array_map(
    static fn (mixed $c): array => ['code' => (string) $c, 'label' => \App\Support\SseFieldNoteCatalog::themeLabel((string) $c), 'hex' => \App\Support\SseFieldNoteCatalog::themeColor((string) $c)],
    $themes
);
$topicTags = array_merge($tagGroups['theme'] ?? [], $tagGroups['framework'] ?? []);
$disciplines = is_array($note['tag_disciplines'] ?? null) ? $note['tag_disciplines'] : [];
$requirements = $tagGroups['requirement'] ?? [];
$products = $tagGroups['product'] ?? [];
$validity = $tagGroups['validity'] ?? [];
$dissemination = $tagGroups['dissemination'] ?? [];
$marking = (string) ($note['marking'] ?? \App\Support\SseUsClassification::banner($classCode));
$handling = is_array($note['handling'] ?? null) ? $note['handling'] : [];
$precedence = is_array($note['precedence'] ?? null) ? $note['precedence'] : ['code' => $tagCat::defaultPrecedence($urgency), 'label' => '', 'desc' => ''];
$relUs = (string) (($note['evaluation_us'] ?? [])['reliability'] ?? $tagCat::reliabilityUs($rel));
$credUs = (string) (($note['evaluation_us'] ?? [])['credibility'] ?? $tagCat::credibilityUs($cred));
$codes = static function (array $tags) use ($h): string {
    $out = '';
    foreach ($tags as $tag) {
        $out .= '<span><b>' . $h($tag['text']) . '</b>' . ($tag['label'] !== '' ? ' <small>' . $h($tag['label']) . ($tag['note'] !== '' ? ' · ' . $h($tag['note']) : '') . '</small>' : '') . '</span>';
    }

    return '<span class="fnr-codes">' . $out . '</span>';
};
$sseExtraStyles = ($sseExtraStyles ?? '') . '<link rel="stylesheet" href="' . $h(asset_url('assets/css/sse-fiche-rens.css')) . '">'
    . '<link rel="stylesheet" href="' . $h(asset_url('assets/css/sse-fiches-etiquettes.css')) . '">';
$sseExtraScripts = ($sseExtraScripts ?? '') . '<script defer src="' . $h(asset_url('assets/js/sse-fiche-rens.js')) . '"></script>'
    . '<script defer src="' . $h(asset_url('assets/js/sse-fiches-etiquettes.js')) . '"></script>';
?>
<div class="fnr fnr--<?= $h($classCode) ?>" data-fnr data-fnr-copied="Copié dans le presse-papiers." data-fnr-copy-failed="La copie a échoué : sélectionnez le texte et copiez-le à la main.">
    <header class="fnr-head">
        <a class="fnr-back" href="<?= $h(url('atak/sse/fiches')) ?>" title="Revenir à la file des fiches"><?= $ico($icons['back']) ?><span>File des fiches</span></a>
        <div class="fnr-head__main">
            <h1 class="fnr-title"><span class="fnr-title__ref"><?= $h($ref) ?></span> <span class="fnr-title__kind"><?= $h($kindLabel !== '' ? $kindLabel : 'Fiche de renseignement') ?></span></h1>
            <p class="fnr-desc"><?php if ($noteTitle !== ''): ?><?= $h($noteTitle) ?><?php else: ?>Constat du <?= $h($observedDate) ?> à <?= $h($observedTime) ?>, rédigé par <?= $h($author) ?>.<?php endif; ?></p>
        </div>
        <div class="fnr-actions" data-cw-lift>
            <?php if ($canManage): ?>
                <button type="button" class="btn fnr-act" data-fnr-open="fnr-dlg-suivi" aria-haspopup="dialog"><?= $ico($icons['flag']) ?><span class="fnr-act__long">Modifier le suivi</span><span class="fnr-act__short" aria-hidden="true">Suivi</span></button>
                <button type="button" class="btn btn--ghost fnr-act" data-ra-open data-ra-pieces="note:<?= (int) $noteId ?>" aria-haspopup="dialog"><?= $ico($icons['folder']) ?><span class="fnr-act__long">Rattacher à…</span><span class="fnr-act__short" aria-hidden="true">Rattacher</span></button>
            <?php endif; ?>
            <?php if ($canAttach): ?>
                <button type="button" class="btn <?= $canManage ? 'btn--ghost' : '' ?> fnr-act" data-fnr-open="fnr-dlg-piece" aria-haspopup="dialog"><?= $ico($icons['clip']) ?><span class="fnr-act__long">Joindre une pièce</span><span class="fnr-act__short" aria-hidden="true">Pièce</span></button>
            <?php endif; ?>
            <button type="button" class="btn btn--ghost fnr-act" data-fnr-print><?= $ico($icons['print']) ?><span class="fnr-act__long">Imprimer / PDF</span><span class="fnr-act__short" aria-hidden="true">Imprimer</span></button>
            <details class="fnr-more" data-fnr-more>
                <summary class="btn btn--ghost fnr-act fnr-act--icon" aria-label="Autres actions" title="Autres actions"><?= $ico($icons['more']) ?></summary>
                <div class="fnr-more__menu">
                    <button type="button" data-fnr-copy="<?= $h($ref) ?>">Copier la référence</button>
                    <button type="button" data-fnr-copy="<?= $h(url($base)) ?>">Copier le lien de la fiche</button>
                    <?php if ($canOpenIntake): ?>
                        <a href="<?= $h(url('back-office/remontees/fiche/' . $noteId)) ?>">Suivi dans Remontées</a>
                    <?php endif; ?>
                    <?php if ($canTag): ?>
                        <button type="button" data-fnr-open="fnr-dlg-etiquettes" aria-haspopup="dialog">Modifier les étiquettes</button>
                    <?php endif; ?>
                    <?php if ($canWrite): ?>
                        <a href="<?= $h(url('atak/sse/fiches/nouvelle')) ?>">Rédiger une nouvelle fiche</a>
                    <?php endif; ?>
                </div>
            </details>
        </div>
        <ul class="fnr-chips" aria-label="État de la fiche">
            <li class="fnr-chip fnr-chip--state fnr-chip--<?= $h($statusTone) ?>" title="<?= $h($statusHints[$status] ?? '') ?>"><span class="fnr-dot" aria-hidden="true"></span><?= $h($statusLabel) ?></li>
            <li class="fnr-chip fnr-chip--class"><span class="fnr-square" aria-hidden="true"></span><?= $h($classLabel) ?></li>
            <li class="fnr-chip"><strong class="fnr-chip__code"><?= $h($rel . $cred) ?></strong><?= $h($relLabel) ?> · <?= $h($credLabel) ?></li>
            <?php if ($urgency !== 'routine'): ?>
                <li class="fnr-chip fnr-chip--urgency fnr-chip--<?= $h($urgencyTone) ?>" title="<?= $h($urgencyHint) ?>">Urgence : <?= $h($urgencyLabel) ?></li>
            <?php endif; ?>
            <?php if (!empty($note['redacted'])): ?>
                <li class="fnr-chip" title="Certains passages sont masqués pour votre niveau d’habilitation.">Passages caviardés</li>
            <?php endif; ?>
        </ul>
    </header>

    <div class="fnr-layout fnr-layout--tags">
        <article class="fnr-doc" aria-label="Fiche de renseignement <?= $h($ref) ?>">
            <div class="fnr-band"><?= $h($classBand) ?><?php if ($dissemination !== []): ?><span class="fnr-band__marking"><?= $h($marking) ?></span><?php endif; ?></div>

            <div class="fnr-doc__inner">
                <table class="fnr-cartouche">
                    <tbody>
                        <tr>
                            <th scope="row">Émetteur</th><td>Bureau SSE · Renseignement</td>
                            <th scope="row">Référence</th><td class="fnr-mono"><?= $h($ref) ?></td>
                        </tr>
                        <tr>
                            <th scope="row">Type</th><td><?= $h($kindCode) ?> — <?= $h($kindLabel) ?></td>
                            <th scope="row">Urgence</th><td><?= $h($urgencyLabel) ?><span class="fnr-us" title="<?= $h($precedence['desc']) ?>"><?= $h($precedence['code']) ?></span></td>
                        </tr>
                        <tr>
                            <th scope="row">Diffusion</th><td colspan="3" class="fnr-marking"><?= $h($marking) ?></td>
                        </tr>
                        <?php if ($requirements !== [] || $products !== []): ?>
                            <tr>
                                <th scope="row">Besoin</th><td><?= $requirements !== [] ? $codes($requirements) : 'Non précisé' ?></td>
                                <th scope="row">Produit</th><td><?= $products !== [] ? $codes($products) : 'Non précisé' ?></td>
                            </tr>
                        <?php endif; ?>
                        <?php if ($disciplines !== [] && $tagList !== []): ?>
                            <tr>
                                <th scope="row">Discipline</th><td colspan="3"><?= $codes($disciplines) ?></td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>

                <div class="fnr-doc__head">
                    <div class="fnr-doc__headmain">
                        <h2 class="fnr-doc__title"><?= $h($noteTitle !== '' ? $noteTitle : ($kindLabel !== '' ? $kindLabel : 'Fiche de renseignement')) ?></h2>
                    </div>
                    <div class="fnr-stamp" title="<?= $h($relLabel . ' · ' . $credLabel) ?>">
                        <strong><?= $h($rel . $cred) ?></strong>
                        <span>Cotation</span>
                    </div>
                </div>

                <?php if ($categories !== [] || $topicTags !== []): ?>
                    <ul class="fnr-tags" aria-label="Catégories et thèmes">
                        <?php foreach ($categories as $category): ?>
                            <li class="fnr-tag" style="--fnr-tag: <?= $h($category['hex']) ?>"><?= $h($category['label']) ?></li>
                        <?php endforeach; ?>
                        <?php foreach ($topicTags as $tag): ?>
                            <li class="fnr-tag" style="--fnr-tag: <?= $h($tag['hex']) ?>" title="<?= $h($tag['desc']) ?>"><b><?= $h($tag['text']) ?></b> <?= $h($tag['label']) ?></li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
                <?php if ($validity !== []): ?>
                    <p class="fnr-validity"><strong>Validité</strong>
                        <?php foreach ($validity as $tag): ?>
                            <span><b><?= $h($tag['text']) ?></b> <?= $h($tag['label']) ?><?= $tag['note'] !== '' ? ', ' . $h($tag['note']) : '' ?></span>
                        <?php endforeach; ?>
                    </p>
                <?php endif; ?>

                <section class="fnr-sec">
                    <h3>1. Circonstances</h3>
                    <table class="fnr-facts">
                        <tbody>
                            <tr><th scope="row">Date du constat</th><td><?= $h($observedDate) ?> à <?= $h($observedTime) ?></td></tr>
                            <tr><th scope="row">Lieu</th><td><?= $h($place !== '' ? $place : 'Non précisé') ?></td></tr>
                            <tr><th scope="row">Position</th><td>
                                <?php if (!$hasPosition): ?>Aucune position transmise<?php endif; ?>
                                <?php if ($grid !== ''): ?><span class="fnr-line">Repère <span class="fnr-mono"><?= $h($grid) ?></span></span><?php endif; ?>
                                <?php if ($hasLatLng): ?><span class="fnr-line">Coordonnées <span class="fnr-mono"><?= $h($latLng) ?></span></span><?php endif; ?>
                                <?php if ($hasGame): ?><span class="fnr-line">Position jeu <span class="fnr-mono"><?= $h($gamePos) ?></span></span><?php endif; ?>
                            </td></tr>
                            <tr><th scope="row">Mode de recueil</th><td><?= $srcCode === '' ? 'Non précisé' : $h($srcCode . ($srcLabel !== '' ? ' — ' . $srcLabel : '')) ?></td></tr>
                        </tbody>
                    </table>
                </section>

                <section class="fnr-sec fnr-sec--text">
                    <h3>2. Renseignement</h3>
                    <?php foreach ($paragraphs as $para): ?>
                        <p><?= nl2br($h($para), false) ?></p>
                    <?php endforeach; ?>
                    <?php if ($paragraphs === []): ?><p class="fnr-void">Texte vide.</p><?php endif; ?>
                </section>

                <section class="fnr-sec">
                    <h3>3. Évaluation</h3>
                    <table class="fnr-facts">
                        <tbody>
                            <tr><th scope="row">Source</th><td><strong><?= $h($rel) ?></strong> — <?= $h($relLabel) ?> <span class="fnr-us-label" lang="en">(<?= $h($relUs) ?>)</span></td></tr>
                            <tr><th scope="row">Information</th><td><strong><?= $h($cred) ?></strong> — <?= $h($credLabel) ?> <span class="fnr-us-label" lang="en">(<?= $h($credUs) ?>)</span></td></tr>
                        </tbody>
                    </table>
                </section>

                <?php if ($attachments !== []): ?>
                    <section class="fnr-sec">
                        <h3>4. Pièces jointes</h3>
                        <ol class="fnr-annex">
                            <?php foreach ($attachments as $i => $attachment): $blur = !empty($attachment['blurred']); ?>
                                <li class="<?= $blur ? 'is-blurred' : '' ?>">
                                    <?php if (!empty($attachment['is_image']) && !empty($attachment['url'])): ?>
                                        <span class="fnr-annex__img"><img src="<?= $h($attachment['url']) ?>" alt="" loading="lazy"></span>
                                    <?php else: ?>
                                        <span class="fnr-annex__img fnr-annex__img--doc" aria-hidden="true">PDF</span>
                                    <?php endif; ?>
                                    <span class="fnr-annex__cap"><strong>PJ <?= $i + 1 ?></strong> · <?= $h($attachment['kind_label'] ?? '') ?><?= !empty($attachment['caption']) ? ' — ' . $h($attachment['caption']) : '' ?><?php if ($blur): ?> <em>(floutée par le bureau)</em><?php endif; ?></span>
                                </li>
                            <?php endforeach; ?>
                        </ol>
                    </section>
                <?php endif; ?>

                <table class="fnr-sign">
                    <tbody>
                        <tr>
                            <th scope="row">Rédacteur</th><td><?= $h($author) ?><?= $authorUnit !== '' ? ' · ' . $h($authorUnit) : '' ?></td>
                            <th scope="row">Saisie</th><td><?= $h($note['origin_label'] ?? '') ?></td>
                        </tr>
                        <tr>
                            <th scope="row">Dossier</th><td><?= $linkedCase !== null ? $h($linkedCase['reference_code'] ?? '') : 'Aucun' ?></td>
                            <th scope="row">Suite donnée</th><td><?= $h($statusLabel) ?></td>
                        </tr>
                    </tbody>
                </table>
                <?php foreach ($handling as $line): ?>
                    <p class="fnr-handling"><?= $h($line) ?></p>
                <?php endforeach; ?>
            </div>

            <div class="fnr-band fnr-band--bottom"><?= $h($classBand) ?><?php if ($dissemination !== []): ?><span class="fnr-band__marking"><?= $h($marking) ?></span><?php endif; ?></div>
        </article>

        <aside class="fnr-side" aria-label="Informations sur la fiche">
            <div class="fnr-tabs" role="tablist" aria-label="Rubriques de la fiche">
                <button type="button" role="tab" id="fnr-tab-essentiel" aria-controls="fnr-panel-essentiel" aria-selected="true" data-fnr-tab="essentiel">Essentiel</button>
                <button type="button" role="tab" id="fnr-tab-etiquettes" aria-controls="fnr-panel-etiquettes" aria-selected="false" tabindex="-1" data-fnr-tab="etiquettes">Étiquettes</button>
                <button type="button" role="tab" id="fnr-tab-suivi" aria-controls="fnr-panel-suivi" aria-selected="false" tabindex="-1" data-fnr-tab="suivi">Suivi</button>
                <button type="button" role="tab" id="fnr-tab-pieces" aria-controls="fnr-panel-pieces" aria-selected="false" tabindex="-1" data-fnr-tab="pieces">Pièces <span class="fnr-count"><?= $attCount ?>/<?= $attachmentsMax ?></span></button>
                <button type="button" role="tab" id="fnr-tab-dossier" aria-controls="fnr-panel-dossier" aria-selected="false" tabindex="-1" data-fnr-tab="dossier">Dossier</button>
                <button type="button" role="tab" id="fnr-tab-liaisons" aria-controls="fnr-panel-liaisons" aria-selected="false" tabindex="-1" data-fnr-tab="liaisons">Liaisons</button>
            </div>

            <section class="fnr-panel" role="tabpanel" id="fnr-panel-essentiel" aria-labelledby="fnr-tab-essentiel" data-fnr-panel="essentiel">
                <h2 class="fnr-panel__title">Essentiel</h2>
                <p class="fnr-desc">Les repères de la fiche : quand, où, comment le renseignement a été recueilli.</p>
                <dl class="fnr-rows">
                    <div><dt>Constat</dt><dd><?= $h($observedDate) ?> à <?= $h($observedTime) ?></dd></div>
                    <div><dt>Lieu</dt><dd><?= $h($place !== '' ? $place : 'Non précisé') ?></dd></div>
                    <div><dt>Position</dt><dd>
                        <?php if (!$hasPosition): ?>Aucune position transmise<?php endif; ?>
                        <?php if ($grid !== ''): ?><span class="fnr-line">Repère <span class="fnr-mono"><?= $h($grid) ?></span></span><?php endif; ?>
                        <?php if ($hasLatLng): ?><span class="fnr-line"><span class="fnr-mono"><?= $h($latLng) ?></span></span><?php endif; ?>
                        <?php if ($hasGame): ?><span class="fnr-line">Position jeu <span class="fnr-mono"><?= $h($gamePos) ?></span></span><?php endif; ?>
                        <?php if ($hasPosition): ?>
                            <button type="button" class="btn btn--ghost fnr-mini" data-fnr-copy="<?= $h($copyPosition) ?>"><?= $ico($icons['copy']) ?>Copier la position</button>
                        <?php endif; ?>
                    </dd></div>
                    <div><dt>Recueil</dt><dd><?= $srcCode === '' ? 'Non précisé' : $h($srcCode . ($srcLabel !== '' ? ' — ' . $srcLabel : '')) ?></dd></div>
                    <div><dt>Urgence</dt><dd><strong class="fnr-urgency fnr-urgency--<?= $h($urgencyTone) ?>"><?= $h($urgencyLabel) ?></strong><?php if ($urgencyHint !== ''): ?><span class="fnr-line fnr-dim"><?= $h($urgencyHint) ?></span><?php endif; ?></dd></div>
                    <div><dt>Cotation</dt><dd><strong><?= $h($rel . $cred) ?></strong><span class="fnr-line fnr-dim"><?= $h($relLabel) ?> · <?= $h($credLabel) ?></span></dd></div>
                    <div><dt>Rédacteur</dt><dd><?= $h($author) ?><?= $authorUnit !== '' ? ' · ' . $h($authorUnit) : '' ?><span class="fnr-line fnr-dim">Saisie : <?= $h($note['origin_label'] ?? '') ?></span></dd></div>
                </dl>
            </section>

            <section class="fnr-panel" role="tabpanel" id="fnr-panel-etiquettes" aria-labelledby="fnr-tab-etiquettes" data-fnr-panel="etiquettes" hidden>
                <h2 class="fnr-panel__title">Étiquettes <span class="fnr-count"><?= count($tagList) ?></span></h2>
                <p class="fnr-desc">Ce que la fiche porte comme mentions : besoin servi, discipline, produit, thèmes, diffusion, validité.</p>
                <div class="fnt-list" id="fnr-tag-list">
                    <?php if ($tagList !== []): ?>
                        <div class="fnt-search">
                            <label class="sr-only" for="fnr-tag-filter">Filtrer les étiquettes de la fiche</label>
                            <input type="search" id="fnr-tag-filter" data-fnt-filter="fnr-tag-list" autocomplete="off" placeholder="Filtrer : code, libellé ou famille">
                        </div>
                        <?php foreach ($tagGroups as $familyKey => $familyTags): ?>
                            <section class="fnt-group" data-fnt-group>
                                <h3><?= $h($tagCat::familyLabel((string) $familyKey, $tagLang)) ?></h3>
                                <ul>
                                    <?php foreach ($familyTags as $tag): ?>
                                        <li class="fnt-item" data-fnt-item data-search="<?= $h($tag['text'] . ' ' . $tag['label'] . ' ' . $tag['desc'] . ' ' . $tag['family_label']) ?>">
                                            <a class="fnt<?= $tag['status'] !== 'active' ? ' fnt--off' : '' ?>" style="--fnt: <?= $h($tag['hex']) ?>" href="<?= $h(url('atak/sse/fiches?tag=' . rawurlencode((string) $tag['key']))) ?>" title="Voir les fiches qui portent cette étiquette"><?= $h($tag['text']) ?></a>
                                            <span class="fnt-item__label"><?= $h($tag['label']) ?><?= $tag['note'] !== '' ? ' · ' . $h($tag['note']) : '' ?></span>
                                            <?php if ($tag['desc'] !== ''): ?><span class="fnt-item__desc"><?= $h($tag['desc']) ?></span><?php endif; ?>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            </section>
                        <?php endforeach; ?>
                        <p class="fnp-empty" data-fnt-empty hidden>Aucune étiquette de la fiche ne correspond.</p>
                    <?php else: ?>
                        <div class="fnr-empty">
                            <strong>Aucune étiquette</strong>
                            <p><?= $canTag ? 'Ajoutez le besoin servi, la discipline de recueil, le type de produit ou une mention de diffusion.' : ($tagsReady ? 'Cette fiche ne porte que ses catégories.' : 'L’étiquetage n’est pas encore ouvert sur ce site : la fiche garde ses catégories.') ?></p>
                            <?php if ($canTag): ?>
                                <button type="button" class="btn" data-fnr-open="fnr-dlg-etiquettes" aria-haspopup="dialog">Ajouter des étiquettes</button>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                    <dl class="fnr-rows">
                        <div><dt>Marquage</dt><dd><span class="fnr-mono"><?= $h($marking) ?></span></dd></div>
                        <div><dt>Priorité</dt><dd><strong class="fnr-mono"><?= $h($precedence['code']) ?></strong><span class="fnr-line fnr-dim">Suit l’urgence de la fiche : <?= $h($urgencyLabel) ?>.</span></dd></div>
                        <div><dt>Cotation</dt><dd><strong><?= $h($rel . $cred) ?></strong><span class="fnr-line fnr-dim" lang="en"><?= $h($relUs) ?> · <?= $h($credUs) ?></span></dd></div>
                    </dl>
                    <p class="fnt-note">Une étiquette de diffusion est une mention écrite sur la fiche : elle ne change pas qui peut la lire. L’accès reste décidé par la classification de la fiche et par les droits d’accès de la communauté.</p>
                </div>
                <?php if ($canTag && $tagList !== []): ?>
                    <button type="button" class="btn btn--ghost fnr-wide" data-fnr-open="fnr-dlg-etiquettes" aria-haspopup="dialog">Modifier les étiquettes</button>
                <?php endif; ?>
            </section>

            <section class="fnr-panel" role="tabpanel" id="fnr-panel-suivi" aria-labelledby="fnr-tab-suivi" data-fnr-panel="suivi" hidden>
                <h2 class="fnr-panel__title">Suivi</h2>
                <p class="fnr-desc">Ce que le bureau a fait de la fiche, du plus récent au plus ancien.</p>
                <div class="fnr-state fnr-state--<?= $h($statusTone) ?>">
                    <p class="fnr-state__label"><span class="fnr-dot" aria-hidden="true"></span><?= $h($statusLabel) ?></p>
                    <?php if (isset($statusHints[$status])): ?><p class="fnr-state__hint"><?= $h($statusHints[$status]) ?></p><?php endif; ?>
                    <?php if ($triageNote !== ''): ?><blockquote class="fnr-quote"><?= $h($triageNote) ?></blockquote><?php endif; ?>
                    <?php if ($triagedTs !== false): ?><p class="fnr-state__hint">Dernier suivi le <?= $h(date('d/m/Y', $triagedTs)) ?> à <?= $h(date('H:i', $triagedTs)) ?>.</p><?php endif; ?>
                </div>
                <?php if ($canManage): ?>
                    <button type="button" class="btn btn--ghost fnr-wide" data-fnr-open="fnr-dlg-suivi" aria-haspopup="dialog">Modifier le suivi</button>
                    <?php if ($history !== []): ?>
                        <ol class="fnr-history">
                            <?php foreach ($history as $event): ?>
                                <li>
                                    <p class="fnr-history__what"><strong><?= $h($event['actor']) ?></strong> <?= $h($event['label']) ?><?php if ($event['to'] !== ''): ?> : <?php if ($event['from'] !== ''): ?><?= $h($event['from']) ?> → <?php endif; ?><strong><?= $h($event['to']) ?></strong><?php endif; ?></p>
                                    <?php if ($event['body'] !== ''): ?><blockquote class="fnr-quote"><?= $h($event['body']) ?></blockquote><?php endif; ?>
                                    <p class="fnr-history__when"><?= $h($event['at']) ?></p>
                                </li>
                            <?php endforeach; ?>
                        </ol>
                    <?php else: ?>
                        <div class="fnr-empty">
                            <strong>Aucun changement enregistré</strong>
                            <p>Les changements d’état, de type et de contenu de la fiche s’inscriront ici.</p>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </section>

            <section class="fnr-panel" role="tabpanel" id="fnr-panel-pieces" aria-labelledby="fnr-tab-pieces" data-fnr-panel="pieces" hidden>
                <h2 class="fnr-panel__title">Pièces jointes <span class="fnr-count"><?= $attCount ?>/<?= $attachmentsMax ?></span></h2>
                <p class="fnr-desc">Photos, captures et documents versés avec la fiche, quatre au plus.</p>
                <?php if ($attachments === []): ?>
                    <div class="fnr-empty">
                        <strong>Aucune pièce jointe</strong>
                        <p><?= $canAttach ? 'Ajoutez une photo, une capture ou un document pour appuyer le renseignement.' : 'Aucune photo ni aucun document n’accompagne cette fiche.' ?></p>
                        <?php if ($canAttach): ?>
                            <button type="button" class="btn" data-fnr-open="fnr-dlg-piece" aria-haspopup="dialog">Joindre une pièce</button>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <ul class="fnr-gallery" data-sse-gallery data-gallery-title="<?= $h($ref) ?>">
                        <?php foreach ($attachments as $i => $attachment): $blur = !empty($attachment['blurred']); ?>
                            <li class="<?= $blur ? 'is-blurred' : '' ?>">
                                <figure>
                                    <?php if (!empty($attachment['is_image']) && !empty($attachment['url'])): ?>
                                        <?php if ($blur && !$canManage): ?>
                                            <span class="fnr-gallery__img"><img src="<?= $h($attachment['url']) ?>" alt="Pièce jointe floutée" loading="lazy"></span>
                                        <?php else: ?>
                                            <a class="fnr-gallery__img" href="<?= $h($attachment['url']) ?>" target="_blank" rel="noopener"><img src="<?= $h($attachment['url']) ?>" alt="<?= $h($attachment['caption'] ?? 'Pièce jointe') ?>" loading="lazy"></a>
                                        <?php endif; ?>
                                    <?php elseif (!empty($attachment['url'])): ?>
                                        <a class="fnr-gallery__img fnr-gallery__img--doc" href="<?= $h($attachment['url']) ?>" target="_blank" rel="noopener">Ouvrir le document</a>
                                    <?php endif; ?>
                                    <figcaption>
                                        <strong>PJ <?= $i + 1 ?></strong> · <?= $h($attachment['kind_label'] ?? '') ?>
                                        <?php if (!empty($attachment['caption'])): ?><span class="fnr-line"><?= $h($attachment['caption']) ?></span><?php endif; ?>
                                        <?php if ($blur): ?><span class="fnr-line fnr-dim">Floutée par le bureau</span><?php endif; ?>
                                    </figcaption>
                                    <?php if ($canWrite): ?>
                                        <button type="button" class="btn btn--danger fnr-mini" data-fnr-open="fnr-dlg-retirer" aria-haspopup="dialog"
                                                data-fnr-action="<?= $h(url($base . '/pieces/' . (int) ($attachment['id'] ?? 0) . '/supprimer')) ?>"
                                                data-fnr-subject="PJ <?= $i + 1 ?><?= !empty($attachment['caption']) ? ' — ' . $h($attachment['caption']) : '' ?>">Retirer</button>
                                    <?php endif; ?>
                                </figure>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                    <?php if ($canAttach): ?>
                        <button type="button" class="btn btn--ghost fnr-wide" data-fnr-open="fnr-dlg-piece" aria-haspopup="dialog">Joindre une pièce</button>
                    <?php elseif ($canWrite): ?>
                        <p class="fnr-dim">La fiche porte déjà ses quatre pièces : retirez-en une pour en joindre une autre.</p>
                    <?php endif; ?>
                <?php endif; ?>
            </section>

            <section class="fnr-panel" role="tabpanel" id="fnr-panel-dossier" aria-labelledby="fnr-tab-dossier" data-fnr-panel="dossier" hidden>
                <h2 class="fnr-panel__title">Dossier</h2>
                <p class="fnr-desc">Le dossier du bureau dans lequel cette fiche est versée.</p>
                <?php if ($linkedCase !== null): ?>
                    <a class="fnr-case" href="<?= $h(url('atak/sse/dossiers/' . (int) $linkedCase['id'])) ?>">
                        <strong class="fnr-mono"><?= $h($linkedCase['reference_code'] ?? '') ?></strong>
                        <span><?= $h($linkedCase['title'] ?? '') ?></span>
                        <span class="fnr-case__go">Ouvrir le dossier</span>
                    </a>
                    <?php if ($canManage): ?>
                        <button type="button" class="btn btn--ghost fnr-wide" data-ra-open data-ra-pieces="note:<?= (int) $noteId ?>" aria-haspopup="dialog">Rattacher ailleurs</button>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="fnr-empty">
                        <strong>Aucun dossier</strong>
                        <p><?= $canManage ? 'Rattachez la fiche à un dossier, une piste ou une personne pour la retrouver avec les autres éléments de l’affaire.' : 'Cette fiche n’est versée dans aucun dossier du bureau.' ?></p>
                        <?php if ($canManage): ?>
                            <button type="button" class="btn" data-ra-open data-ra-pieces="note:<?= (int) $noteId ?>" aria-haspopup="dialog">Rattacher à…</button>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </section>

            <section class="fnr-panel" role="tabpanel" id="fnr-panel-liaisons" aria-labelledby="fnr-tab-liaisons" data-fnr-panel="liaisons" hidden>
                <h2 class="fnr-panel__title">Liaisons</h2>
                <p class="fnr-desc">Les personnes, papiers et dossiers reliés à cette fiche, à la main ou par Cerbère.</p>
                <?php \App\Services\Sse\Links\SseLinksWidget::render('note', $noteId); ?>
                <?php \App\Services\Sse\Attach\SseAttachWindow::section('note', $noteId, ['slim' => true]); ?>
            </section>
        </aside>
    </div>

    <?php if ($canManage): ?>
        <dialog class="fnr-dialog" id="fnr-dlg-suivi" aria-labelledby="fnr-dlg-suivi-title">
            <form method="post" action="<?= $h(url($base . '/suivi')) ?>" class="fnr-dialog__card">
                <input type="hidden" name="_csrf_token" value="<?= $h($csrf) ?>">
                <header class="fnr-dialog__head">
                    <div>
                        <h2 id="fnr-dlg-suivi-title">Suivi de la fiche</h2>
                        <p class="fnr-desc">Indiquez où en est la fiche et ce que vous en faites.</p>
                    </div>
                    <button type="button" class="fnr-dialog__close" data-fnr-close aria-label="Fermer la fenêtre"><?= $ico($icons['close']) ?></button>
                </header>
                <div class="fnr-dialog__body">
                    <label class="fnr-field" for="fiche-suivi">Nouvel état
                        <select id="fiche-suivi" name="status" autofocus>
                            <?php foreach ($statusOptions as $code => $label): ?>
                                <option value="<?= $h($code) ?>" <?= $status === $code ? 'selected' : '' ?>><?= $h($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label class="fnr-field" for="fiche-suivi-note">Commentaire de suivi
                        <textarea id="fiche-suivi-note" name="triage_note" maxlength="400" rows="3" placeholder="Ce que vous en faites, en une phrase"><?= $h($triageNote) ?></textarea>
                    </label>
                </div>
                <footer class="fnr-dialog__foot">
                    <button type="button" class="btn btn--ghost" data-fnr-close>Annuler</button>
                    <button type="submit" class="btn">Enregistrer le suivi</button>
                </footer>
            </form>
        </dialog>

        <?php \App\Services\Sse\Attach\SseAttachWindow::render(); ?>
    <?php endif; ?>

    <?php if ($canTag): ?>
        <dialog class="fnr-dialog fnr-dialog--wide" id="fnr-dlg-etiquettes" aria-labelledby="fnr-dlg-etiquettes-title">
            <form method="post" action="<?= $h(url($base . '/etiquettes')) ?>" class="fnr-dialog__card">
                <input type="hidden" name="_csrf_token" value="<?= $h($csrf) ?>">
                <header class="fnr-dialog__head">
                    <div>
                        <h2 id="fnr-dlg-etiquettes-title">Étiquettes de la fiche</h2>
                        <p class="fnr-desc">Choisissez par famille ou cherchez un code. Ce sont des mentions : l’accès à la fiche ne change pas.</p>
                    </div>
                    <button type="button" class="fnr-dialog__close" data-fnr-close aria-label="Fermer la fenêtre"><?= $ico($icons['close']) ?></button>
                </header>
                <div class="fnr-dialog__body">
                    <?php
                    $pickerSelected = $tagList;
                    $pickerKind = $kindCode;
                    $pickerId = 'fnr-picker';
                    require __DIR__ . '/partials/field_note_tag_picker.php';
                    ?>
                </div>
                <footer class="fnr-dialog__foot">
                    <button type="button" class="btn btn--ghost" data-fnr-close>Annuler</button>
                    <button type="submit" class="btn">Enregistrer les étiquettes</button>
                </footer>
            </form>
        </dialog>
    <?php endif; ?>

    <?php if ($canAttach): ?>
        <dialog class="fnr-dialog" id="fnr-dlg-piece" aria-labelledby="fnr-dlg-piece-title">
            <form method="post" action="<?= $h(url($base . '/pieces')) ?>" enctype="multipart/form-data" class="fnr-dialog__card"
                  data-fnr-files-max="<?= $attachmentsMax - $attCount ?>" data-fnr-files-message="Trop de fichiers choisis pour la place qui reste sur la fiche.">
                <input type="hidden" name="_csrf_token" value="<?= $h($csrf) ?>">
                <header class="fnr-dialog__head">
                    <div>
                        <h2 id="fnr-dlg-piece-title">Ajouter une pièce jointe</h2>
                        <p class="fnr-desc">Image, PDF ou texte ; il reste <?= $attachmentsMax - $attCount ?> place(s) sur <?= $attachmentsMax ?>.</p>
                    </div>
                    <button type="button" class="fnr-dialog__close" data-fnr-close aria-label="Fermer la fenêtre"><?= $ico($icons['close']) ?></button>
                </header>
                <div class="fnr-dialog__body">
                    <label class="fnr-field" for="fiche-piece">Fichiers à joindre
                        <input id="fiche-piece" type="file" name="pieces[]" multiple required autofocus accept="image/*,application/pdf,text/plain">
                    </label>
                    <label class="fnr-field" for="fiche-piece-caption">Légende (facultative)
                        <input id="fiche-piece-caption" type="text" name="caption" maxlength="255" placeholder="Ce que montre la pièce">
                    </label>
                </div>
                <footer class="fnr-dialog__foot">
                    <button type="button" class="btn btn--ghost" data-fnr-close>Annuler</button>
                    <button type="submit" class="btn">Joindre</button>
                </footer>
            </form>
        </dialog>
    <?php endif; ?>

    <?php if ($canWrite && $attachments !== []): ?>
        <dialog class="fnr-dialog fnr-dialog--small" id="fnr-dlg-retirer" aria-labelledby="fnr-dlg-retirer-title">
            <form method="post" action="" class="fnr-dialog__card" data-fnr-target-form>
                <input type="hidden" name="_csrf_token" value="<?= $h($csrf) ?>">
                <header class="fnr-dialog__head">
                    <div>
                        <h2 id="fnr-dlg-retirer-title">Retirer cette pièce jointe ?</h2>
                        <p class="fnr-desc">La pièce est retirée de la fiche et ne pourra pas être récupérée.</p>
                    </div>
                    <button type="button" class="fnr-dialog__close" data-fnr-close aria-label="Fermer la fenêtre"><?= $ico($icons['close']) ?></button>
                </header>
                <div class="fnr-dialog__body">
                    <p class="fnr-subject" data-fnr-subject-out></p>
                </div>
                <footer class="fnr-dialog__foot">
                    <button type="button" class="btn btn--ghost" data-fnr-close autofocus>Annuler</button>
                    <button type="submit" class="btn btn--danger">Retirer la pièce</button>
                </footer>
            </form>
        </dialog>
    <?php endif; ?>
</div>

<?php
$sseContent = ob_get_clean();
require __DIR__ . '/_layout.php';
