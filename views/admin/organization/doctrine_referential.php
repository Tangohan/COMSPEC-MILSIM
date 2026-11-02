<?php
declare(strict_types=1);

/**
 * Doctrine › Référentiel doctrinal (langage visuel du module Doctrine, classes .dct-* et .dlib-*).
 *
 * 1. Catalogue des publications de référence (App\Services\Doctrine\DoctrineLibraryService::overview) : références
 *    publiques fournies par le site (France, États-Unis, OTAN, Allemagne) et références ajoutées par la communauté ;
 *    rail par pays/organisation et par type, recherche, filtres, volet de détail, création d’un document de doctrine
 *    à partir d’une référence et rattachement à un document existant.
 * 2. Personnalisation : ajout et modification d’une référence (?modifier=custom-<id>), catégories, masquage.
 * 3. Trames d’ordres, échelons et parcours selon le choix US / FR / les deux (inchangé, replié).
 * Les textes du catalogue sont servis dans la langue du lecteur et marqués translate="no".
 *
 * @var array<string, mixed> $library
 * @var array<string, bool> $can
 * @var string $doctrineReferential
 */

use App\Services\Doctrine\DoctrineLibraryCatalog;
use App\Services\Doctrine\DoctrineReferential;
use App\Services\Doctrine\EchelonCatalog;
use App\Services\Doctrine\OrderFormatCatalog;
use App\Services\Doctrine\TrainingPipelineCatalog;

require_once base_path('views/admin/documents/partials/doctrine_ui.php');

$h = 'dct_e';
$lib = is_array($library ?? null) ? $library : [];
$can = is_array($can ?? null) ? $can : [];
$canEdit = !empty($can['edit']);
$canCreate = !empty($can['create']);
$ready = !empty($lib['schema_ready']);
$entries = is_array($lib['entries'] ?? null) ? $lib['entries'] : [];
$packs = is_array($lib['packs'] ?? null) ? $lib['packs'] : [];
$domains = is_array($lib['domains'] ?? null) ? $lib['domains'] : [];
$types = is_array($lib['types'] ?? null) ? $lib['types'] : [];
$levels = is_array($lib['levels'] ?? null) ? $lib['levels'] : [];
$categories = is_array($lib['categories'] ?? null) ? $lib['categories'] : [];
$documents = is_array($lib['documents'] ?? null) ? $lib['documents'] : [];
$stats = is_array($lib['stats'] ?? null) ? $lib['stats'] : [];
$readerLang = (string) ($lib['lang'] ?? 'fr');
$csrf = dct_e((string) ($csrf_token ?? \App\Core\Csrf::token()));
$base = url('back-office/doctrine/referentiel');
$activeCategories = array_values(array_filter($categories, static fn (array $c): bool => !$c['archived']));

// Référence à modifier (formulaire pré-rempli côté serveur, sans JavaScript).
$editKey = trim((string) ($_GET['modifier'] ?? ''));
$editing = null;
foreach ($entries as $e) {
    if ($e['origin'] === 'custom' && $e['key'] === $editKey) {
        $editing = $e;
    }
}
$form = $editing['raw'] ?? ['organization' => '', 'code' => '', 'title' => '', 'title_translation' => '', 'lang' => 'fr', 'domain' => '', 'type' => 'doctrine', 'level' => 'tactical', 'summary' => '', 'links' => []];

$packColor = ['fr' => '#1e4f80', 'us' => '#7c2d12', 'nato' => '#0b5394', 'de' => '#b45309', 'custom' => '#059669'];
$badge = static function (array $e) use ($packColor): string {
    $code = $e['origin'] === 'custom'
        ? mb_strtoupper(mb_substr((string) preg_replace('/[^\p{L}\p{N}]/u', '', $e['issuer_short']), 0, 4))
        : ($e['country'] === 'NATO' ? 'OTAN' : $e['country']);

    return '<span class="dct-fi dlib-fi" style="--c:' . dct_e($packColor[$e['pack']] ?? '#64748b') . '" aria-hidden="true" translate="no">' . dct_e($code !== '' ? $code : 'DOC') . '</span>';
};
$docTitle = static fn (array $d): string => ($d['reference'] !== '' ? $d['reference'] . ' — ' : '') . $d['title'];

$actions = '';
if ($canEdit) {
    $actions .= '<a class="dct-btn is-dark" href="' . $h($base . '#ajouter') . '" data-dlib-add>' . dct_icon('plus') . '<span>Ajouter une référence</span></a>';
}
if ($canCreate) {
    $actions .= '<a class="dct-btn" href="' . $h(url('back-office/documents/publier')) . '">' . dct_icon('pen') . '<span>Nouveau document</span></a>';
}
?>
<div class="dct dlib" data-dlib>
    <?= dct_head(
        'Opérations · Doctrine',
        'Référentiel doctrinal',
        'Les publications officielles sur lesquelles s’appuie votre doctrine : règlements, manuels et doctrines publiés en accès libre par la France, les États-Unis, l’OTAN et l’Allemagne, plus les références propres à votre communauté. Partez d’une référence pour rédiger ou rattacher vos propres textes.',
        $actions,
        'referential',
        $can
    ) ?>
    <?= dct_flashes() ?>

    <details class="dct-guide"<?= (int) ($stats['custom'] ?? 0) === 0 && (int) ($stats['linked'] ?? 0) === 0 ? ' open' : '' ?>>
        <summary>
            <?= dct_icon('book') ?>
            <span><b>À quoi sert le référentiel</b><small>Trouver la source officielle, puis écrire la doctrine de votre communauté à partir d’elle.</small></span>
            <?= dct_icon('down', 'dct-guide__chev') ?>
        </summary>
        <div class="dct-guide__body">
            <ol class="dct-steps dlib-steps">
                <li>
                    <b>Chercher</b>
                    <p>Filtrez par pays ou organisation, type et domaine, ou cherchez un code (AJP-3.5, FM 3-18, A-2600/1…).</p>
                </li>
                <li>
                    <b>Lire la source</b>
                    <p>Chaque fiche donne le code officiel, le titre d’origine et sa traduction, et le lien vers la publication officielle.</p>
                </li>
                <li>
                    <b>Créer ou rattacher</b>
                    <p>« Créer un document » ouvre un brouillon pré-rempli, déjà rattaché à la référence. « Rattacher » relie un document existant.</p>
                    <?php if ($canCreate): ?><a href="<?= $h(url('back-office/doctrine')) ?>">Documents de doctrine</a><?php endif; ?>
                </li>
                <li>
                    <b>Personnaliser</b>
                    <p>Ajoutez vos propres références (unité réelle ou fictive), classez-les dans vos catégories et masquez celles qui ne vous concernent pas.</p>
                    <?php if ($canEdit): ?><a href="#ajouter">Ajouter une référence</a><?php endif; ?>
                </li>
            </ol>
            <dl class="dct-voc">
                <div><dt>Code officiel</dt><dd>La cote de la publication chez son émetteur : AJP-3.5 (OTAN), ATP 3-21.8 (US Army), A-2600/1 (Bundeswehr)…</dd></div>
                <div><dt>Accès libre</dt><dd>Le site ne fournit que des publications publiques. Rien de classifié ni en diffusion restreinte.</dd></div>
                <div><dt>Rattacher</dt><dd>Relier un document de votre doctrine à la référence dont il s’inspire : la fiche montre alors vos documents.</dd></div>
                <div><dt>Masquer</dt><dd>Retirer une référence du site de votre liste sans la supprimer : vous pouvez la réafficher à tout moment.</dd></div>
                <div><dt>Archiver</dt><dd>Retirer une référence que vous avez ajoutée : elle quitte la liste, ses rattachements et son historique restent.</dd></div>
                <div><dt>Traduction</dt><dd>Les titres traduits sont des traductions de travail : le titre officiel reste celui de la langue d’origine.</dd></div>
            </dl>
        </div>
    </details>

    <div class="dct-stats" role="group" aria-label="Aperçu du référentiel">
        <div class="dct-stat">
            <span class="dct-k">Références affichées</span>
            <b><?= (int) ($stats['visible'] ?? 0) ?></b>
            <small><?= count(array_filter($packs, static fn (array $p): bool => $p['key'] !== 'custom' && $p['count'] > 0)) ?> pays ou organisations</small>
        </div>
        <button type="button" class="dct-stat" data-dlib-stat="custom" aria-pressed="false">
            <span class="dct-k">Ajoutées par la communauté</span>
            <b><?= (int) ($stats['custom'] ?? 0) ?></b>
            <small><?= (int) ($stats['archived'] ?? 0) ?> archivée(s)</small>
        </button>
        <button type="button" class="dct-stat" data-dlib-stat="linked" aria-pressed="false">
            <span class="dct-k">Rattachées à un document</span>
            <b class="<?= (int) ($stats['linked'] ?? 0) > 0 ? 'is-ok' : '' ?>"><?= (int) ($stats['linked'] ?? 0) ?></b>
            <small>références à l’origine de vos textes</small>
        </button>
        <button type="button" class="dct-stat" data-dlib-stat="hidden" aria-pressed="false">
            <span class="dct-k">Masquées</span>
            <b><?= (int) ($stats['hidden'] ?? 0) ?></b>
            <small>du catalogue du site, réaffichables</small>
        </button>
    </div>

    <?php if (!$ready && $canEdit): ?>
        <p class="dct-flash is-err" role="status"><?= dct_icon('alert') ?><span>Ajout de références, catégories, masquage et rattachements arrivent avec la prochaine mise à jour du site. Le catalogue reste consultable.</span></p>
    <?php endif; ?>

    <div class="dct-vault">
        <aside class="dct-rail" aria-label="Classement">
            <span class="dct-k dct-rail__k">Pays et organisations</span>
            <button type="button" class="is-on" data-dlib-rail="pack:" aria-pressed="true">
                <span class="dct-sw dct-sw--all" aria-hidden="true"></span><span class="dct-rail__l">Toutes les références</span><span class="dct-rail__c"><?= (int) ($stats['visible'] ?? 0) ?></span>
            </button>
            <?php foreach ($packs as $p): ?>
                <?php if ($p['key'] === 'custom' && $p['count'] === 0 && !$canEdit) { continue; } ?>
                <button type="button" data-dlib-rail="pack:<?= $h($p['key']) ?>" aria-pressed="false"<?= $p['count'] === 0 ? ' class="is-zero"' : '' ?>>
                    <?= dct_swatch($packColor[$p['key']] ?? '') ?><span class="dct-rail__l"><?= $p['key'] === 'custom' ? 'Ajoutées par la communauté' : '<span translate="no">' . $h($p['label']) . '</span>' ?></span><span class="dct-rail__c"><?= (int) $p['count'] ?></span>
                </button>
            <?php endforeach; ?>
            <hr>
            <span class="dct-k dct-rail__k">Types</span>
            <button type="button" class="is-on" data-dlib-rail="type:" aria-pressed="true">
                <span class="dct-sw dct-sw--all" aria-hidden="true"></span><span class="dct-rail__l">Tous les types</span><span class="dct-rail__c"><?= (int) ($stats['visible'] ?? 0) ?></span>
            </button>
            <?php foreach ($types as $t): ?>
                <?php if ($t['count'] === 0) { continue; } ?>
                <button type="button" data-dlib-rail="type:<?= $h($t['key']) ?>" aria-pressed="false">
                    <?= dct_swatch('') ?><span class="dct-rail__l" translate="no"><?= $h($t['label']) ?></span><span class="dct-rail__c"><?= (int) $t['count'] ?></span>
                </button>
            <?php endforeach; ?>
            <?php if ($canEdit): ?>
                <hr>
                <a class="dct-rail__new" href="#ajouter"><?= dct_icon('plus') ?><span class="dct-rail__l">Ajouter une référence</span></a>
                <a class="dct-rail__new" href="#categories"><?= dct_icon('tag') ?><span class="dct-rail__l">Gérer les catégories</span></a>
            <?php endif; ?>
        </aside>

        <section class="dct-panel" aria-labelledby="dlib-list-title">
            <h2 id="dlib-list-title" class="dct-sr">Références doctrinales</h2>
            <div class="dct-bar">
                <label class="dct-search">
                    <?= dct_icon('search') ?>
                    <span class="dct-sr">Rechercher une référence</span>
                    <input type="search" data-dlib-q placeholder="Code, titre, émetteur…" autocomplete="off">
                </label>
                <label class="dct-sr" for="dlib-f-domain">Domaine</label>
                <select class="dct-sel" id="dlib-f-domain" data-dlib-f="domain">
                    <option value="">Tous les domaines</option>
                    <?php foreach ($domains as $d): ?>
                        <?php if ($d['count'] === 0) { continue; } ?>
                        <option value="<?= $h($d['key']) ?>" translate="no"><?= $h($d['label']) ?> (<?= (int) $d['count'] ?>)</option>
                    <?php endforeach; ?>
                    <?php foreach ($activeCategories as $c): ?>
                        <option value="cat:<?= (int) $c['id'] ?>" translate="no"><?= $h($c['label']) ?> (<?= (int) $c['count'] ?>)</option>
                    <?php endforeach; ?>
                </select>
                <label class="dct-sr" for="dlib-f-level">Niveau</label>
                <select class="dct-sel" id="dlib-f-level" data-dlib-f="level">
                    <option value="">Tous les niveaux</option>
                    <?php foreach ($levels as $k => $l): ?>
                        <option value="<?= $h($k) ?>" translate="no"><?= $h($l) ?></option>
                    <?php endforeach; ?>
                </select>
                <label class="dct-sr" for="dlib-f-state">Affichage</label>
                <select class="dct-sel" id="dlib-f-state" data-dlib-f="state">
                    <option value="">Références affichées</option>
                    <option value="linked">Rattachées à un document</option>
                    <option value="custom">Ajoutées par la communauté</option>
                    <option value="hidden">Masquées</option>
                    <option value="archived">Archivées</option>
                </select>
                <span class="dct-count" aria-live="polite"><b data-dlib-count><?= (int) ($stats['visible'] ?? 0) ?></b> affichée(s)</span>
            </div>

            <div class="dct-list" data-dlib-list>
            <?php foreach ($entries as $e): ?>
                <?php
                $key = (string) $e['key'];
                $state = $e['hidden'] ? 'hidden' : ($e['archived'] ? 'archived' : 'visible');
                $search = mb_strtolower(implode(' ', [$e['code'], $e['series'], $e['title'], $e['title_local'], $e['issuer'], $e['issuer_short'], $e['pack_label'], $e['domain_label'], $e['summary']]), 'UTF-8');
                $domainKey = $e['category_id'] > 0 ? 'cat:' . $e['category_id'] : $e['domain'];
                $docCount = count($e['documents']);
                $titleLang = in_array($e['lang'], ['fr', 'en', 'de'], true) ? $e['lang'] : 'fr';
                ?>
                <article class="dct-doc dlib-ref" id="ref-<?= $h($key) ?>" data-dlib-ref="<?= $h($key) ?>"
                    data-pack="<?= $h($e['pack']) ?>" data-type="<?= $h($e['type']) ?>" data-domain="<?= $h($domainKey) ?>" data-level="<?= $h($e['level']) ?>"
                    data-state="<?= $state ?>" data-origin="<?= $h($e['origin']) ?>" data-linked="<?= $docCount > 0 ? '1' : '0' ?>" data-q="<?= $h($search) ?>"<?= $state !== 'visible' ? ' hidden' : '' ?>>
                    <?= $badge($e) ?>
                    <div class="dct-doc__t">
                        <button type="button" class="dct-doc__open" data-dlib-open aria-haspopup="dialog"><span class="dlib-code" translate="no"><?= $h($e['code']) ?></span> <span translate="no" lang="<?= $h($titleLang) ?>"><?= $h($e['title']) ?></span></button>
                        <?php if ($e['title_local'] !== ''): ?><span class="dlib-tr" translate="no"><?= $h($e['title_local']) ?></span><?php endif; ?>
                        <small>
                            <span translate="no"><?= $h($e['issuer_short']) ?></span>
                            <?php if ($e['domain_label'] !== ''): ?><span translate="no"><?= $h($e['domain_label']) ?></span><?php endif; ?>
                            <?php if ($e['edition'] !== ''): ?><span translate="no"><?= $h($e['edition']) ?></span><?php endif; ?>
                        </small>
                    </div>
                    <span class="dlib-chip" translate="no"><?= $h($e['type_label']) ?></span>
                    <span class="dlib-chip is-soft" translate="no"><?= $h($e['level_label']) ?></span>
                    <span class="dlib-state">
                        <?php if ($e['hidden']): ?><span class="dct-st">Masquée</span>
                        <?php elseif ($e['archived']): ?><span class="dct-st">Archivée</span>
                        <?php elseif ($e['origin'] === 'custom'): ?><span class="dct-st dct-st--draft">Communauté</span>
                        <?php else: ?><span class="dct-st dct-st--published">Accès libre</span><?php endif; ?>
                        <?php if ($docCount > 0): ?><small><?= dct_icon('file') ?><?= $docCount ?> document(s)</small><?php endif; ?>
                    </span>
                    <button type="button" class="dct-more" data-dlib-open aria-label="Détail de la référence"><?= dct_icon('arrow') ?></button>

                    <div class="dct-doc__detail" data-dlib-detail hidden>
                        <div class="dct-dr__h">
                            <?= $badge($e) ?>
                            <div class="dct-dr__ht">
                                <small translate="no"><?= $h($e['code']) ?><?= $e['edition'] !== '' ? ' · ' . $h($e['edition']) : '' ?></small>
                                <b translate="no" lang="<?= $h($titleLang) ?>"><?= $h($e['title']) ?></b>
                            </div>
                            <button type="button" class="dct-btn is-icon" data-dlib-close aria-label="Fermer"><?= dct_icon('x') ?></button>
                        </div>
                        <div class="dct-dr__b">
                            <div class="dct-dr__state">
                                <?php if ($e['origin'] === 'custom'): ?><span class="dct-st dct-st--draft">Ajoutée par la communauté</span>
                                <?php else: ?><span class="dct-st dct-st--published">Publication en accès libre</span><?php endif; ?>
                                <?php if ($e['hidden']): ?><span class="dct-st">Masquée pour la communauté</span><?php endif; ?>
                                <?php if ($e['archived']): ?><span class="dct-st">Archivée</span><?php endif; ?>
                            </div>
                            <?php if ($e['title_local'] !== ''): ?><p class="dlib-dr-tr" translate="no"><?= $h($e['title_local']) ?></p><?php endif; ?>
                            <?php if ($e['summary'] !== ''): ?><p class="dct-dr__sum" translate="no"><?= nl2br($h($e['summary'])) ?></p><?php endif; ?>

                            <div class="dct-acts">
                                <?php if (!$e['hidden'] && !$e['archived'] && $canCreate): ?>
                                    <a class="dct-btn is-dark" href="<?= $h(url('back-office/documents/publier') . '?reference=' . rawurlencode($key)) ?>"><?= dct_icon('plus') ?><span>Créer un document à partir de cette référence</span></a>
                                <?php endif; ?>
                                <?php if ($e['source_url'] !== ''): ?>
                                    <a class="dct-btn" href="<?= $h($e['source_url']) ?>" target="_blank" rel="noopener noreferrer"><?= dct_icon('arrow') ?><span>Ouvrir la source</span></a>
                                <?php endif; ?>
                                <?php if ($e['file'] !== null): ?>
                                    <a class="dct-btn" href="<?= $h($base . '/references/' . (int) $e['custom_id'] . '/fichier') ?>" target="_blank" rel="noopener"><?= dct_icon('dl') ?><span>Fichier joint</span></a>
                                <?php endif; ?>
                                <?php if ($canEdit && $ready && $e['origin'] === 'catalog'): ?>
                                    <form method="post" action="<?= $h($base . '/masquer') ?>"<?php if (!$e['hidden']): ?> data-confirm="Masquer cette référence pour votre communauté ? Elle reste dans le catalogue du site et vous pourrez la réafficher."<?php endif; ?>>
                                        <input type="hidden" name="_csrf_token" value="<?= $csrf ?>">
                                        <input type="hidden" name="key" value="<?= $h($key) ?>">
                                        <input type="hidden" name="hidden" value="<?= $e['hidden'] ? '0' : '1' ?>">
                                        <button type="submit" class="dct-btn is-quiet"><?= dct_icon($e['hidden'] ? 'eye' : 'x') ?><span><?= $e['hidden'] ? 'Réafficher' : 'Masquer' ?></span></button>
                                    </form>
                                <?php endif; ?>
                                <?php if ($canEdit && $ready && $e['origin'] === 'custom'): ?>
                                    <a class="dct-btn" href="<?= $h($base . '?modifier=' . rawurlencode($key) . '#ajouter') ?>"><?= dct_icon('pen') ?><span>Modifier</span></a>
                                    <form method="post" action="<?= $h($base . '/references/' . (int) $e['custom_id'] . '/archiver') ?>"<?php if (!$e['archived']): ?> data-confirm="Archiver cette référence ? Elle quitte la liste ; rien n’est supprimé."<?php endif; ?>>
                                        <input type="hidden" name="_csrf_token" value="<?= $csrf ?>">
                                        <input type="hidden" name="restore" value="<?= $e['archived'] ? '1' : '0' ?>">
                                        <button type="submit" class="dct-btn is-quiet"><?= dct_icon('archive') ?><span><?= $e['archived'] ? 'Réactiver' : 'Archiver' ?></span></button>
                                    </form>
                                <?php endif; ?>
                            </div>

                            <h3 class="dct-dr__k">Documents de la communauté</h3>
                            <?php if ($e['documents'] === []): ?>
                                <p class="dct-hint">Aucun document de votre doctrine n’est encore rattaché à cette référence.</p>
                            <?php else: ?>
                                <ul class="dct-people">
                                    <?php foreach ($e['documents'] as $d): ?>
                                        <li>
                                            <a class="dct-people__n" href="<?= $h(url('back-office/doctrine') . '#doc-' . (int) $d['document_id']) ?>" translate="no"><?= $h($docTitle($d)) ?></a>
                                            <?php if ($canEdit && $ready): ?>
                                                <form method="post" action="<?= $h($base . '/rattacher') ?>" data-confirm="Retirer ce rattachement ? Le document n’est pas modifié.">
                                                    <input type="hidden" name="_csrf_token" value="<?= $csrf ?>">
                                                    <input type="hidden" name="key" value="<?= $h($key) ?>">
                                                    <input type="hidden" name="document_id" value="<?= (int) $d['document_id'] ?>">
                                                    <input type="hidden" name="unlink" value="1">
                                                    <button type="submit" class="dct-btn is-sm is-quiet">Retirer</button>
                                                </form>
                                            <?php endif; ?>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                            <?php if ($canEdit && $ready && $documents !== []): ?>
                                <form method="post" action="<?= $h($base . '/rattacher') ?>" class="dlib-link">
                                    <input type="hidden" name="_csrf_token" value="<?= $csrf ?>">
                                    <input type="hidden" name="key" value="<?= $h($key) ?>">
                                    <label class="dct-fl"><span>Rattacher un document existant</span>
                                        <select name="document_id" required>
                                            <option value="">Choisir un document…</option>
                                            <?php foreach ($documents as $d): ?>
                                                <option value="<?= (int) $d['document_id'] ?>" translate="no"><?= $h($docTitle($d)) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </label>
                                    <button type="submit" class="dct-btn"><?= dct_icon('layers') ?><span>Rattacher</span></button>
                                </form>
                            <?php endif; ?>

                            <dl class="dct-dl">
                                <dt>Code officiel</dt><dd translate="no"><?= $h($e['code']) ?></dd>
                                <dt>Organisme émetteur</dt><dd translate="no"><?= $h($e['issuer']) ?></dd>
                                <dt>Titre officiel</dt><dd translate="no" lang="<?= $h($titleLang) ?>"><?= $h($e['title']) ?> <small>(<?= $h($e['lang_label']) ?>)</small></dd>
                                <?php if ($e['title_local'] !== ''): ?><dt>Traduction</dt><dd translate="no"><?= $h($e['title_local']) ?></dd><?php endif; ?>
                                <dt>Domaine</dt><dd translate="no"><?= $h($e['domain_label'] !== '' ? $e['domain_label'] : '—') ?></dd>
                                <dt>Niveau</dt><dd translate="no"><?= $h($e['level_label']) ?></dd>
                                <dt>Type</dt><dd translate="no"><?= $h($e['type_label']) ?></dd>
                                <?php if ($e['edition'] !== ''): ?><dt>Édition publiée</dt><dd translate="no"><?= $h($e['edition']) ?></dd><?php endif; ?>
                                <dt>Diffusion</dt><dd><?= $e['origin'] === 'custom' ? 'Référence de la communauté' : 'Publique (accès libre)' ?></dd>
                                <?php if ($e['source_url'] !== ''): ?><dt>Source</dt><dd><a href="<?= $h($e['source_url']) ?>" target="_blank" rel="noopener noreferrer" translate="no"><?= $h($e['source_label']) ?></a></dd><?php endif; ?>
                            </dl>
                            <?php if (count($e['links']) > 1): ?>
                                <div class="dct-links">
                                    <?php foreach (array_slice($e['links'], 1) as $l): ?>
                                        <a href="<?= $h($l['url']) ?>" target="_blank" rel="noopener noreferrer" translate="no"><?= dct_icon('arrow') ?><span><?= $h($l['label'] !== '' ? $l['label'] : (string) parse_url($l['url'], PHP_URL_HOST)) ?></span></a>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                            <?php if ($e['origin'] === 'catalog' && ($note = (string) ($packs[$e['pack']]['note'] ?? '')) !== ''): ?>
                                <p class="dct-hint" translate="no"><?= $h($note) ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                </article>
            <?php endforeach; ?>
            </div>

            <div class="dct-empty" data-dlib-nomatch hidden>
                <div class="dct-empty__ill"><?= dct_icon('search', 'dct-i--lg') ?></div>
                <b>Aucune référence ne correspond</b>
                <p>Changez de pays, de type, de domaine ou de niveau, ou effacez la recherche. Les références masquées et archivées ont leur propre affichage.</p>
                <button type="button" class="dct-btn" data-dlib-reset>Effacer les filtres</button>
            </div>
        </section>
    </div>

    <?php if ($canEdit): ?>
    <section class="dlib-custom" aria-label="Personnaliser le référentiel">
        <form id="ajouter" class="dct-form" method="post" action="<?= $h($base . '/references') ?>" enctype="multipart/form-data">
            <input type="hidden" name="_csrf_token" value="<?= $csrf ?>">
            <input type="hidden" name="entry_id" value="<?= (int) ($editing['custom_id'] ?? 0) ?>">
            <div>
                <h2 class="dct-form__t"><?= $editing !== null ? 'Modifier la référence' : 'Ajouter une référence' ?></h2>
                <p class="dct-hint">Une publication que votre communauté suit et que le catalogue du site ne contient pas : règlement d’une unité réelle, doctrine d’une organisation fictive de votre univers, SOP d’une coalition… N’ajoutez que des textes que vous avez le droit de diffuser.</p>
            </div>
            <div class="dct-grid">
                <label class="dct-fl"><span>Organisation</span><input name="organization" required maxlength="120" value="<?= $h($form['organization']) ?>" placeholder="Ex. Task Force Phantom, Bundeswehr"></label>
                <label class="dct-fl"><span>Code</span><input class="dct-mono" name="code" required maxlength="64" value="<?= $h($form['code']) ?>" placeholder="Ex. TFP-SOP-03"></label>
                <label class="dct-fl dct-fl--wide"><span>Titre <small>dans la langue d’origine</small></span><input name="title" required maxlength="255" value="<?= $h($form['title']) ?>"></label>
                <label class="dct-fl"><span>Langue du titre</span>
                    <select name="lang">
                        <?php foreach (DoctrineLibraryCatalog::LANGS as $l): ?>
                            <option value="<?= $h($l) ?>"<?= $form['lang'] === $l ? ' selected' : '' ?> translate="no"><?= $h(DoctrineLibraryCatalog::langLabel($l, $readerLang)) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label class="dct-fl dct-fl--wide"><span>Traduction du titre <small>facultatif</small></span><input name="title_translation" maxlength="255" value="<?= $h($form['title_translation']) ?>"></label>
                <label class="dct-fl"><span>Domaine ou catégorie</span>
                    <select name="domain">
                        <option value="">—</option>
                        <optgroup label="Domaines du site">
                            <?php foreach ($domains as $d): ?>
                                <option value="<?= $h($d['key']) ?>"<?= $form['domain'] === $d['key'] ? ' selected' : '' ?> translate="no"><?= $h($d['label']) ?></option>
                            <?php endforeach; ?>
                        </optgroup>
                        <?php if ($activeCategories !== []): ?>
                        <optgroup label="Catégories de la communauté">
                            <?php foreach ($activeCategories as $c): ?>
                                <option value="cat:<?= (int) $c['id'] ?>"<?= $form['domain'] === 'cat:' . $c['id'] ? ' selected' : '' ?> translate="no"><?= $h($c['label']) ?></option>
                            <?php endforeach; ?>
                        </optgroup>
                        <?php endif; ?>
                    </select>
                </label>
                <label class="dct-fl"><span>Type</span>
                    <select name="type">
                        <?php foreach ($types as $t): ?>
                            <option value="<?= $h($t['key']) ?>"<?= $form['type'] === $t['key'] ? ' selected' : '' ?> translate="no"><?= $h($t['label']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label class="dct-fl"><span>Niveau</span>
                    <select name="level">
                        <?php foreach ($levels as $k => $l): ?>
                            <option value="<?= $h($k) ?>"<?= $form['level'] === $k ? ' selected' : '' ?> translate="no"><?= $h($l) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label class="dct-fl dct-fl--full"><span>Résumé <small>facultatif</small></span><textarea name="summary" rows="3" maxlength="2000"><?= $h($form['summary']) ?></textarea></label>
                <?php for ($i = 0; $i < \App\Services\Doctrine\DoctrineLibraryService::MAX_LINKS; $i++): ?>
                    <?php $l = $form['links'][$i] ?? ['label' => '', 'url' => '']; ?>
                    <label class="dct-fl dct-fl--wide"><span>Lien <?= $i + 1 ?> <small><?= $i === 0 ? 'source principale, https://' : 'facultatif' ?></small></span><input type="url" name="link_url[]" maxlength="500" value="<?= $h($l['url']) ?>" placeholder="https://"></label>
                    <label class="dct-fl dct-fl--wide"><span>Intitulé du lien <?= $i + 1 ?> <small>facultatif</small></span><input name="link_label[]" maxlength="80" value="<?= $h($l['label']) ?>"></label>
                <?php endfor; ?>
                <label class="dct-fl dct-fl--wide"><span>Fichier PDF <small>facultatif, 15 Mo au plus</small></span><input type="file" name="file" accept="application/pdf,.pdf"></label>
                <?php if ($editing !== null && $editing['file'] !== null): ?>
                    <label class="dct-check"><input type="checkbox" name="remove_file" value="1"> <span>Retirer le fichier joint</span> <small translate="no"><?= $h($editing['file']['name']) ?></small></label>
                <?php endif; ?>
            </div>
            <div class="dct-form__f">
                <?php if (!$ready): ?><p class="dct-hint">L’ajout de références arrive avec la prochaine mise à jour du site.</p><?php endif; ?>
                <?php if ($editing !== null): ?><a class="dct-btn" href="<?= $h($base . '#ref-' . $editing['key']) ?>">Annuler</a><?php endif; ?>
                <button type="submit" class="dct-btn is-dark"<?= $ready ? '' : ' disabled' ?>><?= dct_icon('check') ?><span><?= $editing !== null ? 'Enregistrer les modifications' : 'Ajouter la référence' ?></span></button>
            </div>
        </form>

        <div id="categories" class="dct-card">
            <h2 class="dct-card__t">Catégories de la communauté</h2>
            <p class="dct-card__lead">Vos propres classements, en plus des domaines du site : « SOP de la coalition », « Univers Fondation »… Une catégorie archivée disparaît des filtres, ses références restent.</p>
            <?php if ($categories !== []): ?>
                <ul class="dct-people">
                    <?php foreach ($categories as $c): ?>
                        <li>
                            <form method="post" action="<?= $h($base . '/categories') ?>" class="dlib-cat">
                                <input type="hidden" name="_csrf_token" value="<?= $csrf ?>">
                                <input type="hidden" name="category_id" value="<?= (int) $c['id'] ?>">
                                <label class="dct-sr" for="dlib-cat-<?= (int) $c['id'] ?>">Nom de la catégorie</label>
                                <input id="dlib-cat-<?= (int) $c['id'] ?>" name="label" maxlength="80" required value="<?= $h($c['label']) ?>">
                                <input type="color" name="color" value="<?= $h($c['color'] !== '' ? $c['color'] : '#64748b') ?>" aria-label="Couleur">
                                <button type="submit" class="dct-btn is-sm">Renommer</button>
                                <small><?= (int) $c['count'] ?> référence(s)</small><?php if ($c['archived']): ?><small>Archivée</small><?php endif; ?>
                            </form>
                            <form method="post" action="<?= $h($base . '/categories/' . (int) $c['id'] . '/archiver') ?>">
                                <input type="hidden" name="_csrf_token" value="<?= $csrf ?>">
                                <input type="hidden" name="restore" value="<?= $c['archived'] ? '1' : '0' ?>">
                                <button type="submit" class="dct-btn is-sm is-quiet"><?= $c['archived'] ? 'Réactiver' : 'Archiver' ?></button>
                            </form>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
            <form method="post" action="<?= $h($base . '/categories') ?>" class="dlib-cat is-new">
                <input type="hidden" name="_csrf_token" value="<?= $csrf ?>">
                <label class="dct-fl"><span>Nouvelle catégorie</span><input name="label" maxlength="80" required placeholder="Ex. SOP de la coalition"></label>
                <input type="color" name="color" value="#059669" aria-label="Couleur">
                <button type="submit" class="dct-btn"<?= $ready ? '' : ' disabled' ?>><?= dct_icon('plus') ?><span>Créer</span></button>
            </form>
        </div>
    </section>
    <?php endif; ?>

    <?php
    $referential = DoctrineReferential::sanitize($doctrineReferential ?? null);
    $rLabels = is_array($doctrineReferentialLabels ?? null) ? $doctrineReferentialLabels : DoctrineReferential::labels();
    $rDescriptions = is_array($doctrineReferentialDescriptions ?? null) ? $doctrineReferentialDescriptions : DoctrineReferential::descriptions();
    $formats = is_array($doctrineOrderFormats ?? null) ? $doctrineOrderFormats : [];
    $echelons = is_array($doctrineEchelons ?? null) ? $doctrineEchelons : [];
    $training = is_array($doctrineTraining ?? null) ? $doctrineTraining : [];
    $orders = array_values(array_filter($formats, static fn (array $f): bool => ($f['kind'] ?? '') === OrderFormatCatalog::KIND_ORDER));
    $reports = array_values(array_filter($formats, static fn (array $f): bool => ($f['kind'] ?? '') === OrderFormatCatalog::KIND_REPORT));
    $stages = array_values(array_filter($training, static fn (array $t): bool => ($t['type'] ?? '') === TrainingPipelineCatalog::TYPE_STAGE));
    $qualifications = array_values(array_filter($training, static fn (array $t): bool => ($t['type'] ?? '') === TrainingPipelineCatalog::TYPE_QUALIFICATION));
    ?>
    <details class="dct-sop" id="trames">
        <summary>
            <?= dct_icon('layers') ?>
            <span><b>Trames d’ordres, échelons et parcours proposés</b><small>Doctrine retenue : <?= $h($rLabels[$referential] ?? $referential) ?></small></span>
            <?= dct_icon('down', 'dct-guide__chev') ?>
        </summary>
        <div class="dct-sop__body">
            <form method="post" action="<?= $h($base) ?>" class="dct-form">
                <input type="hidden" name="_csrf_token" value="<?= $csrf ?>">
                <div>
                    <h3 class="dct-form__t">Doctrine de la communauté</h3>
                    <p class="dct-hint">Ce choix ne filtre que ce qui vous est proposé : trames de rédaction, échelons et parcours. Il ne modifie ni ne supprime aucun document, rôle ou formation déjà enregistré.</p>
                </div>
                <div class="dct-choice dlib-choice">
                    <?php foreach (DoctrineReferential::keys() as $k): ?>
                        <label class="dct-opt">
                            <input type="radio" name="referential" value="<?= $h($k) ?>"<?= $referential === $k ? ' checked' : '' ?><?= $canEdit ? '' : ' disabled' ?>>
                            <b><?= $h($rLabels[$k] ?? $k) ?></b>
                            <small><?= $h($rDescriptions[$k] ?? '') ?></small>
                        </label>
                    <?php endforeach; ?>
                </div>
                <div class="dct-form__f">
                    <?php if ($canEdit): ?>
                        <button type="submit" class="dct-btn is-dark">Enregistrer le référentiel</button>
                    <?php else: ?>
                        <p class="dct-hint">Consultation seule : le changement de référentiel demande le droit d’administration de la communauté.</p>
                    <?php endif; ?>
                </div>
            </form>

            <?php foreach (['Ordres' => $orders, 'Comptes rendus et demandes' => $reports] as $group => $list): ?>
                <h3 class="dct-dr__k"><?= $h($group) ?></h3>
                <?php if ($list === []): ?>
                    <p class="dct-hint">Aucun gabarit pour ce référentiel.</p>
                <?php endif; ?>
                <?php foreach ($list as $format): ?>
                    <details class="dct-fold dlib-fold">
                        <summary><span class="dct-tag"><?= $h(DoctrineReferential::originLabel((string) ($format['origin'] ?? ''))) ?></span> <span translate="no"><?= $h((string) ($format['code'] ?? '')) ?></span> — <?= $h((string) ($format['label'] ?? '')) ?> <small>(<?= OrderFormatCatalog::fieldCount($format) ?> champs)</small></summary>
                        <p class="dct-hint"><?= $h((string) ($format['purpose'] ?? '')) ?> · Émis par : <?= $h((string) ($format['issued_by'] ?? '—')) ?></p>
                        <?php foreach ($format['sections'] ?? [] as $section): ?>
                            <p class="dct-dr__sub"><?= $h((string) ($section['title'] ?? '')) ?></p>
                            <ol class="dlib-ol">
                                <?php foreach ($section['fields'] ?? [] as $field): ?><li><?= $h((string) $field) ?></li><?php endforeach; ?>
                            </ol>
                        <?php endforeach; ?>
                    </details>
                <?php endforeach; ?>
            <?php endforeach; ?>

            <h3 class="dct-dr__k">Échelons de commandement</h3>
            <div class="dct-table-wrap">
                <table class="dct-table">
                    <thead><tr><th>Échelon</th><th>Origine</th><th>Commandé par</th><th>Effectif</th><th>Fonctions attendues</th></tr></thead>
                    <tbody>
                    <?php foreach ($echelons as $echelon): ?>
                        <tr>
                            <td><b><?= $h((string) ($echelon['label'] ?? '—')) ?></b><small><?= $h((string) ($echelon['composition'] ?? '')) ?></small></td>
                            <td><?= $h(DoctrineReferential::originLabel((string) ($echelon['origin'] ?? ''))) ?></td>
                            <td><?= $h((string) ($echelon['commanded_by'] ?? '—')) ?></td>
                            <td><?= $h(EchelonCatalog::strengthLabel($echelon)) ?></td>
                            <td><?= $h(implode(' · ', array_map('strval', $echelon['functions'] ?? []))) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <p class="dct-hint">Les effectifs sont indicatifs : ils situent l’échelon, ils ne conditionnent rien dans l’application.</p>

            <h3 class="dct-dr__k">Parcours de formation</h3>
            <div class="dct-table-wrap">
                <table class="dct-table">
                    <thead><tr><th>Étape</th><th>Intitulé</th><th>Origine</th><th>Prérequis</th><th>Objet</th></tr></thead>
                    <tbody>
                    <?php foreach ($stages as $entry): ?>
                        <tr>
                            <td translate="no"><b><?= $h((string) ($entry['code'] ?? '—')) ?></b></td>
                            <td><?= $h((string) ($entry['label'] ?? '—')) ?></td>
                            <td><?= $h(DoctrineReferential::originLabel((string) ($entry['origin'] ?? ''))) ?></td>
                            <td><?= $h((string) ($entry['prerequisite'] ?? 'Aucun')) ?></td>
                            <td><?= $h((string) ($entry['purpose'] ?? '—')) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <h3 class="dct-dr__k">Qualifications à entretenir</h3>
            <div class="dct-table-wrap">
                <table class="dct-table">
                    <thead><tr><th>Qualification</th><th>Intitulé</th><th>Origine</th><th>Validité</th><th>Recyclage</th></tr></thead>
                    <tbody>
                    <?php foreach ($qualifications as $entry): ?>
                        <tr>
                            <td translate="no"><b><?= $h((string) ($entry['code'] ?? '—')) ?></b></td>
                            <td><?= $h((string) ($entry['label'] ?? '—')) ?></td>
                            <td><?= $h(DoctrineReferential::originLabel((string) ($entry['origin'] ?? ''))) ?></td>
                            <td><?= $h(TrainingPipelineCatalog::validityLabel($entry)) ?></td>
                            <td><?= $h((string) ($entry['recycling'] ?? '—')) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <p class="dct-hint">Durées indicatives : fixez les vôtres dans le référentiel des compétences.</p>
        </div>
    </details>

    <dialog class="dct-dr" aria-labelledby="dlib-dr-title" data-dlib-drawer>
        <h2 id="dlib-dr-title" class="dct-sr">Détail de la référence</h2>
        <div class="dct-dr__in" data-dlib-drawer-body></div>
    </dialog>
</div>
<noscript><style>.dct .dlib-ref .dct-doc__detail[hidden]{display:block!important;grid-column:1/-1}.dlib-ref .dct-doc__detail .dct-dr__h .dct-btn{display:none}.dct .dlib-ref[hidden]{display:grid!important}</style></noscript>
<script src="<?= $h(asset_url('assets/js/doctrine-library.js')) ?>" defer></script>
