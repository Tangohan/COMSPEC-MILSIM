<?php
declare(strict_types=1);

/**
 * Back-office > Doctrine et SOP : page de pilotage du module Doctrine (langage visuel des pages Effectifs).
 *
 * Données réelles (App\Services\Doctrine\DoctrineHubService::overview) : documents doctrinaux du tenant, leurs
 * versions, destinataires, historique et l’état de lecture de chaque destinataire (même règle que le Suivi de
 * diffusion). En bas : les fiches SOP simples du poste ATAK (doctrine_documents), inchangées.
 *
 * @var array<string, mixed> $hub
 * @var array<string, bool> $can
 * @var list<array<string, mixed>> $doctrine_documents
 */

require_once base_path('views/admin/documents/partials/doctrine_ui.php');

$hub = is_array($hub ?? null) ? $hub : [];
$can = is_array($can ?? null) ? $can : [];
$docs = is_array($hub['documents'] ?? null) ? $hub['documents'] : [];
$types = is_array($hub['types'] ?? null) ? $hub['types'] : [];
$domains = is_array($hub['domains'] ?? null) ? $hub['domains'] : [];
$stats = is_array($hub['stats'] ?? null) ? $hub['stats'] : [];
$csrf = dct_e((string) ($csrf_token ?? ''));
$h = 'dct_e';
$st = static fn (string $k): int => (int) ($stats[$k] ?? 0);

$readPct = $st('expected') > 0 ? (int) floor(100 * $st('done') / $st('expected')) : null;
$toRemind = 0;
foreach ($docs as $d) {
    if ($d['tracked'] && $d['editable'] && $d['missing_count'] > 0) {
        ++$toRemind;
    }
}
$complianceUrl = url('back-office/documents/compliance');
$exportUrl = url('back-office/doctrine/export-lectures');

// Unités réellement visées par au moins un document (filtre « Destinataires »).
$unitFilter = [];
foreach ($docs as $d) {
    foreach ($d['audiences'] as $a) {
        if ($a['kind'] === 'unit' && (int) ($a['id'] ?? 0) > 0) {
            $unitFilter[(int) $a['id']] = (string) $a['label'];
        }
    }
}
asort($unitFilter);

$actions = '';
if (!empty($can['compliance']) && $st('expected') > 0) {
    $actions .= '<a class="dct-btn" href="' . $h($exportUrl) . '">' . dct_icon('dl') . '<span>Exporter les lectures</span></a>';
}
if (!empty($can['remind']) && $toRemind > 0) {
    $actions .= '<button type="button" class="dct-btn" data-dct-remind-all>' . dct_icon('bell') . '<span>Relancer les retardataires</span></button>';
}
if (!empty($can['create'])) {
    $actions .= '<a class="dct-btn is-dark" href="' . $h(url('back-office/documents/publier')) . '">' . dct_icon('plus') . '<span>Nouveau document</span></a>';
}

$audienceShort = static function (array $d): string {
    $a = $d['audiences'];
    if ($a === []) {
        return 'Toute la communauté';
    }
    $first = (string) $a[0]['label'];

    return count($a) > 1 ? $first . ' +' . (count($a) - 1) : $first;
};
$requirement = static function (array $d): string {
    if ($d['ack_required']) {
        return 'Accusé de lecture demandé';
    }

    return $d['reading_required'] ? 'Lecture obligatoire' : 'Pour information';
};
$fileIcon = static function (array $d): string {
    $code = $d['type_prefix'] !== '' ? $d['type_prefix'] : ($d['domain_prefix'] !== '' ? $d['domain_prefix'] : 'DOC');

    return '<span class="dct-fi"' . ($d['type_color'] !== '' ? ' style="--c:' . dct_e($d['type_color']) . '"' : '') . ' aria-hidden="true" translate="no">'
        . dct_e(mb_strtoupper(mb_substr($code, 0, 4))) . '</span>';
};
?>
<div class="dct" data-dct>
    <?= dct_head(
        'Opérations · Doctrine',
        'Doctrine et SOP',
        'Les textes qui disent comment votre communauté travaille : instructions, procédures, notes de service. Chaque texte porte une référence, se publie par versions datées et vous montre qui l’a lu.',
        $actions,
        'hub',
        $can
    ) ?>
    <?= dct_flashes() ?>

    <details class="dct-guide"<?= $docs === [] ? ' open' : '' ?>>
        <summary>
            <?= dct_icon('book') ?>
            <span><b>Comment ça marche</b><small>Cinq étapes, du texte écrit à la lecture confirmée.</small></span>
            <?= dct_icon('down', 'dct-guide__chev') ?>
        </summary>
        <div class="dct-guide__body">
            <ol class="dct-steps">
                <li>
                    <b>Écrire ou importer</b>
                    <p>Rédigez le texte dans Athena ou collez-le depuis votre document. Il reste un brouillon tant que vous ne le publiez pas.</p>
                    <?php if (!empty($can['create'])): ?><a href="<?= $h(url('back-office/documents/publier')) ?>">Nouveau document</a><?php endif; ?>
                </li>
                <li>
                    <b>Classer</b>
                    <p>Choisissez son type (note de service, instruction…) et, si besoin, son domaine : Athena lui donne une référence unique, par exemple NS-2026-004.</p>
                    <?php if (!empty($can['manage'])): ?><a href="<?= $h(url('back-office/doctrine?onglet=types')) ?>">Types</a> · <a href="<?= $h(url('back-office/doctrine?onglet=nomenclature')) ?>">Nomenclature</a><?php endif; ?>
                </li>
                <li>
                    <b>Publier une version</b>
                    <p>La publication fige le texte en version v1.0. Pour le changer ensuite, publiez une nouvelle version : l’ancienne reste dans l’historique.</p>
                </li>
                <li>
                    <b>Diffuser</b>
                    <p>Indiquez qui doit le lire : toute la communauté, des unités, des emplois, des rôles ou des membres. Ils sont prévenus à la publication.</p>
                </li>
                <li>
                    <b>Suivre les lectures</b>
                    <p>Chaque destinataire ouvre le texte et, si vous l’exigez, confirme l’avoir lu. Relancez ceux qui manquent avant l’échéance.</p>
                    <?php if (!empty($can['compliance'])): ?><a href="<?= $h($complianceUrl) ?>">Suivi de diffusion</a><?php endif; ?>
                </li>
            </ol>
            <dl class="dct-voc">
                <div><dt>Référence</dt><dd>Le numéro unique du texte, calculé à partir du type ou du domaine, de l’année et d’un numéro d’ordre.</dd></div>
                <div><dt>Version</dt><dd>Un état daté du texte. Seule la dernière version publiée est en vigueur.</dd></div>
                <div><dt>Lecture obligatoire</dt><dd>Le destinataire doit ouvrir le texte ; Athena note la date d’ouverture.</dd></div>
                <div><dt>Accusé de lecture</dt><dd>Le destinataire confirme en un clic avoir lu et compris ; sa confirmation est horodatée.</dd></div>
                <div><dt>Échéance</dt><dd>La date avant laquelle la lecture est attendue. Après, le destinataire passe « En retard ».</dd></div>
                <div><dt>Archiver</dt><dd>Retirer un texte qui ne s’applique plus : il quitte les lectures à faire, son historique reste.</dd></div>
            </dl>
        </div>
    </details>

    <div class="dct-stats" role="group" aria-label="Aperçu de la doctrine">
        <button type="button" class="dct-stat" data-dct-stat="live" aria-pressed="false">
            <span class="dct-k">Documents publiés</span>
            <b><?= $st('published') ?></b>
            <small>en vigueur pour les destinataires</small>
        </button>
        <button type="button" class="dct-stat" data-dct-stat="draft" aria-pressed="false">
            <span class="dct-k">Brouillons</span>
            <b><?= $st('drafts') ?></b>
            <small>pas encore publiés</small>
        </button>
        <button type="button" class="dct-stat" data-dct-stat="attention" aria-pressed="false">
            <span class="dct-k">À valider ou expirés</span>
            <b class="<?= $st('review') + $st('expired') > 0 ? 'is-warn' : '' ?>"><?= $st('review') + $st('expired') ?></b>
            <small><?= $st('review') ?> à valider · <?= $st('expired') ?> expiré(s)</small>
        </button>
        <?php $tone = $readPct === null ? '' : ($readPct >= 80 ? 'is-ok' : ($readPct < 50 ? 'is-bad' : 'is-warn')); ?>
        <<?= !empty($can['compliance']) ? 'a class="dct-stat is-link" href="' . $h($complianceUrl) . '"' : 'div class="dct-stat"' ?>>
            <span class="dct-k">Lectures confirmées</span>
            <b class="<?= $tone ?>"><?= $readPct === null ? '—' : $readPct . ' %' ?></b>
            <?php if ($st('expected') === 0): ?>
                <small>aucune lecture attendue</small>
            <?php elseif ($st('overdue') > 0): ?>
                <small><?= $st('done') ?> sur <?= $st('expected') ?> attendues · <?= $st('overdue') ?> en retard</small>
            <?php else: ?>
                <small><?= $st('done') ?> sur <?= $st('expected') ?> attendues</small>
            <?php endif; ?>
        </<?= !empty($can['compliance']) ? 'a' : 'div' ?>>
    </div>

    <div class="dct-vault">
        <aside class="dct-rail" aria-label="Classement">
            <span class="dct-k dct-rail__k">Types</span>
            <button type="button" class="is-on" data-dct-rail="" aria-pressed="true">
                <span class="dct-sw dct-sw--all" aria-hidden="true"></span><span class="dct-rail__l">Tous les documents</span><span class="dct-rail__c"><?= count($docs) ?></span>
            </button>
            <?php foreach ($types as $t): ?>
                <?php if ($t['count'] === 0 && !$t['active']) { continue; } ?>
                <button type="button" data-dct-rail="type:<?= (int) $t['id'] ?>" aria-pressed="false"<?= $t['count'] === 0 ? ' class="is-zero"' : '' ?>>
                    <?= dct_swatch($t['color']) ?><span class="dct-rail__l"><?= $h($t['label']) ?></span><span class="dct-rail__c"><?= (int) $t['count'] ?></span>
                </button>
            <?php endforeach; ?>
            <?php if ((int) ($hub['untyped'] ?? 0) > 0): ?>
                <button type="button" data-dct-rail="type:0" aria-pressed="false">
                    <?= dct_swatch('') ?><span class="dct-rail__l">Sans type</span><span class="dct-rail__c"><?= (int) $hub['untyped'] ?></span>
                </button>
            <?php endif; ?>
            <?php if ($domains !== []): ?>
                <hr>
                <span class="dct-k dct-rail__k">Nomenclature</span>
                <?php foreach ($domains as $dm): ?>
                    <button type="button" data-dct-rail="domain:<?= (int) $dm['id'] ?>" aria-pressed="false"<?= $dm['count'] === 0 ? ' class="is-zero"' : '' ?>>
                        <?= dct_swatch($dm['color']) ?><span class="dct-rail__l"><?= $h($dm['label']) ?><?php if ($dm['prefix'] !== ''): ?> <small translate="no"><?= $h($dm['prefix']) ?></small><?php endif; ?></span><span class="dct-rail__c"><?= (int) $dm['count'] ?></span>
                    </button>
                <?php endforeach; ?>
            <?php endif; ?>
            <?php if (!empty($can['manage'])): ?>
                <hr>
                <a class="dct-rail__new" href="<?= $h(url('back-office/doctrine?onglet=types')) ?>"><?= dct_icon('tag') ?><span class="dct-rail__l">Gérer les types</span></a>
                <a class="dct-rail__new" href="<?= $h(url('back-office/doctrine?onglet=nomenclature')) ?>"><?= dct_icon('layers') ?><span class="dct-rail__l">Gérer la nomenclature</span></a>
            <?php endif; ?>
        </aside>

        <section class="dct-panel" aria-labelledby="dct-list-title">
            <h2 id="dct-list-title" class="dct-sr">Documents doctrinaux</h2>
            <div class="dct-bar">
                <label class="dct-search">
                    <?= dct_icon('search') ?>
                    <span class="dct-sr">Rechercher un document</span>
                    <input type="search" data-dct-q placeholder="Titre, référence, objet…" autocomplete="off">
                </label>
                <label class="dct-sr" for="dct-f-state">État</label>
                <select class="dct-sel" id="dct-f-state" data-dct-f="state">
                    <option value="">En cours (hors archives)</option>
                    <option value="live">Publiés</option>
                    <option value="draft">Brouillons</option>
                    <option value="attention">À valider ou expirés</option>
                    <option value="archived">Archivés et abrogés</option>
                    <option value="all">Tous les états</option>
                </select>
                <?php if ($unitFilter !== []): ?>
                <label class="dct-sr" for="dct-f-unit">Destinataires</label>
                <select class="dct-sel" id="dct-f-unit" data-dct-f="unit">
                    <option value="">Tous les destinataires</option>
                    <option value="all">Toute la communauté</option>
                    <?php foreach ($unitFilter as $uid => $ul): ?>
                        <option value="<?= (int) $uid ?>"><?= $h($ul) ?></option>
                    <?php endforeach; ?>
                </select>
                <?php endif; ?>
                <label class="dct-sr" for="dct-f-deadline">Échéance</label>
                <select class="dct-sel" id="dct-f-deadline" data-dct-f="deadline">
                    <option value="">Toutes les échéances</option>
                    <option value="past">Échéance dépassée</option>
                    <option value="soon">Dans les 7 jours</option>
                    <option value="later">Plus tard</option>
                    <option value="none">Sans échéance</option>
                </select>
                <label class="dct-sr" for="dct-f-read">Lecture</label>
                <select class="dct-sel" id="dct-f-read" data-dct-f="read">
                    <option value="">Lu ou non lu</option>
                    <option value="missing">Lectures manquantes</option>
                    <option value="complete">Tout le monde a lu</option>
                </select>
                <span class="dct-count" aria-live="polite"><b data-dct-count><?= count($docs) ?></b> affiché(s)</span>
            </div>

            <div class="dct-list" data-dct-list>
            <?php foreach ($docs as $d): ?>
                <?php
                $id = (int) $d['document_id'];
                $unitIds = [];
                $hasAll = false;
                foreach ($d['audiences'] as $a) {
                    if ($a['kind'] === 'unit') {
                        $unitIds[] = (int) ($a['id'] ?? 0);
                    } elseif ($a['kind'] === 'all') {
                        $hasAll = true;
                    }
                }
                $pct = $d['recipients_total'] > 0 ? (int) floor(100 * $d['done_count'] / $d['recipients_total']) : 0;
                $read = !$d['tracked'] ? 'na' : ($d['missing_count'] > 0 ? 'missing' : 'complete');
                $search = mb_strtolower($d['reference'] . ' ' . $d['title'] . ' ' . $d['summary'] . ' ' . $d['type_label'] . ' ' . $d['domain_label'], 'UTF-8');
                $missing = array_values(array_filter($d['recipients'], static fn (array $r): bool => !$r['done']));
                $doneList = array_values(array_filter($d['recipients'], static fn (array $r): bool => $r['done']));
                $isLive = in_array($d['state'], ['published', 'expired'], true);
                $isArchived = in_array($d['state'], ['archived', 'obsolete'], true);
                ?>
                <article class="dct-doc" id="doc-<?= $id ?>" data-dct-doc="<?= $id ?>"
                    data-state="<?= $h($d['state']) ?>" data-type="<?= (int) $d['type_id'] ?>" data-domain="<?= (int) $d['domain_id'] ?>"
                    data-units="<?= $h(implode(' ', $unitIds)) ?>" data-all="<?= $hasAll ? '1' : '0' ?>"
                    data-deadline="<?= $h($d['deadline_state']) ?>" data-read="<?= $read ?>" data-q="<?= $h($search) ?>"<?= $isArchived ? ' hidden' : '' ?>>
                    <?= $fileIcon($d) ?>
                    <div class="dct-doc__t">
                        <button type="button" class="dct-doc__open" data-dct-open aria-haspopup="dialog"><?= $h($d['title'] !== '' ? $d['title'] : $d['reference']) ?></button>
                        <small>
                            <?php if ($d['reference'] !== ''): ?><span class="dct-ref" translate="no"><?= $h($d['reference']) ?></span><?php endif; ?>
                            <?php if ($d['type_label'] !== ''): ?><span><?= $h($d['type_label']) ?></span><?php endif; ?>
                            <?php if (!$d['editable']): ?><span>Plateforme</span><?php endif; ?>
                        </small>
                    </div>
                    <span class="dct-ver" translate="no"><?= $h($d['version_label']) ?></span>
                    <?= dct_state_pill($d['state'], $d['state_label']) ?>
                    <span class="dct-aud"><?= dct_icon('users') ?><span><?= $h($audienceShort($d)) ?></span></span>
                    <div class="dct-prog<?= $d['tracked'] ? '' : ' is-na' ?>">
                        <?php if ($d['tracked']): ?>
                            <span class="dct-prog__bar" role="img" aria-label="<?= $d['done_count'] ?> sur <?= $d['recipients_total'] ?> ont lu"><i style="width:<?= $pct ?>%"></i></span>
                            <small><b><?= $d['done_count'] ?></b>/<?= $d['recipients_total'] ?> ont lu<?php if ($d['overdue_count'] > 0): ?> · <em><?= $d['overdue_count'] ?> en retard</em><?php endif; ?></small>
                        <?php else: ?>
                            <small><?= $d['recipients_total'] ?> destinataire(s) prévu(s)</small>
                        <?php endif; ?>
                    </div>
                    <button type="button" class="dct-more" data-dct-open aria-label="Détail de <?= $h($d['reference'] !== '' ? $d['reference'] : $d['title']) ?>"><?= dct_icon('arrow') ?></button>

                    <div class="dct-doc__detail" data-dct-detail hidden>
                        <div class="dct-dr__h">
                            <?= $fileIcon($d) ?>
                            <div class="dct-dr__ht">
                                <small translate="no"><?= $h($d['reference']) ?> · <?= $h($d['version_label']) ?></small>
                                <b><?= $h($d['title']) ?></b>
                            </div>
                            <button type="button" class="dct-btn is-icon" data-dct-close aria-label="Fermer"><?= dct_icon('x') ?></button>
                        </div>
                        <div class="dct-dr__b">
                            <div class="dct-dr__state">
                                <?= dct_state_pill($d['state'], $d['state_label']) ?>
                                <span><?= $h($requirement($d)) ?></span>
                                <?php if ($d['deadline'] !== null): ?>
                                    <?php $dl = $h(dct_date($d['deadline'])); ?>
                                    <?php if ($d['deadline_state'] === 'past'): ?><span class="is-bad"><?= dct_icon('clock') ?><span>Échéance dépassée depuis le <?= $dl ?></span></span>
                                    <?php elseif ($d['deadline_state'] === 'soon'): ?><span><?= dct_icon('clock') ?><span>À lire avant le <?= $dl ?></span></span>
                                    <?php else: ?><span><?= dct_icon('clock') ?><span>Échéance le <?= $dl ?></span></span><?php endif; ?>
                                <?php endif; ?>
                            </div>
                            <?php if ($d['summary'] !== ''): ?><p class="dct-dr__sum"><?= $h($d['summary']) ?></p><?php endif; ?>

                            <?php if ($d['editable']): ?>
                            <div class="dct-acts">
                                <?php if (in_array($d['state'], ['draft', 'review'], true) && !empty($can['create'])): ?>
                                    <a class="dct-btn" href="<?= $h(url('back-office/documents/publier/' . $id)) ?>"><?= dct_icon('pen') ?><span>Modifier le brouillon</span></a>
                                <?php endif; ?>
                                <?php if (in_array($d['state'], ['draft', 'review'], true) && !empty($can['publish']) && !empty($can['create'])): ?>
                                    <?php if ($d['state'] === 'draft' && $d['require_validation']): ?>
                                        <p class="dct-hint">Ce type demande une validation : ouvrez le brouillon puis « Soumettre à validation ».</p>
                                    <?php else: ?>
                                        <form method="post" action="<?= $h(url('back-office/doctrine/documents/' . $id . '/publier')) ?>" data-confirm="Publier ce document ? Ses <?= (int) $d['recipients_total'] ?> destinataire(s) seront prévenus.">
                                            <input type="hidden" name="_csrf_token" value="<?= $csrf ?>">
                                            <button type="submit" class="dct-btn is-dark"><?= dct_icon('send') ?><span>Publier</span></button>
                                        </form>
                                    <?php endif; ?>
                                <?php endif; ?>
                                <?php if ($isLive && !empty($can['create'])): ?>
                                    <a class="dct-btn is-dark" href="<?= $h(url('back-office/documents/' . $id . '/nouvelle-version')) ?>"><?= dct_icon('up') ?><span>Nouvelle version</span></a>
                                <?php endif; ?>
                                <?php if ($isLive && $d['missing_count'] > 0 && !empty($can['remind'])): ?>
                                    <form method="post" action="<?= $h(url('back-office/documents/relances')) ?>" data-confirm="Envoyer un rappel aux <?= (int) $d['missing_count'] ?> destinataire(s) qui n’ont pas encore lu ?">
                                        <input type="hidden" name="_csrf_token" value="<?= $csrf ?>">
                                        <input type="hidden" name="document_id" value="<?= $id ?>">
                                        <button type="submit" class="dct-btn"><?= dct_icon('bell') ?><span>Relancer <?= (int) $d['missing_count'] ?> retardataire(s)</span></button>
                                    </form>
                                <?php endif; ?>
                                <a class="dct-btn" href="<?= $h(url('documents/doctrine/' . $id)) ?>"><?= dct_icon('eye') ?><span>Voir comme un lecteur</span></a>
                                <?php if (!$isArchived && !empty($can['archive'])): ?>
                                    <form method="post" action="<?= $h(url('back-office/doctrine/documents/' . $id . '/archiver')) ?>" data-confirm="Archiver ce document ? Il quitte les lectures à faire ; son historique reste consultable.">
                                        <input type="hidden" name="_csrf_token" value="<?= $csrf ?>">
                                        <button type="submit" class="dct-btn is-quiet"><?= dct_icon('archive') ?><span>Archiver</span></button>
                                    </form>
                                <?php endif; ?>
                            </div>
                            <?php else: ?>
                                <p class="dct-hint">Document fourni par la plateforme Athena : lecture seule pour votre communauté.</p>
                                <div class="dct-acts"><a class="dct-btn" href="<?= $h(url('documents/doctrine/' . $id)) ?>"><?= dct_icon('eye') ?><span>Voir comme un lecteur</span></a></div>
                            <?php endif; ?>

                            <h3 class="dct-dr__k">Lectures</h3>
                            <?php if ($d['tracked']): ?>
                                <div class="dct-dr__prog">
                                    <span class="dct-prog__bar"><i style="width:<?= $pct ?>%"></i></span>
                                    <span><b><?= $pct ?> %</b> · <?= $d['done_count'] ?> sur <?= $d['recipients_total'] ?> ont lu<?php if ($d['last_reminder_at']): ?> · dernière relance le <?= $h(dct_date($d['last_reminder_at'])) ?><?php endif; ?></span>
                                </div>
                                <?php if ($missing !== []): ?>
                                    <p class="dct-dr__sub">Il manque <?= count($missing) ?> lecture(s)</p>
                                    <ul class="dct-people">
                                        <?php foreach (array_slice($missing, 0, 30) as $r): ?>
                                            <li><span class="dct-people__n"><?= $h($r['name']) ?><?php if ($r['unit'] !== ''): ?><small><?= $h($r['unit']) ?></small><?php endif; ?></span><span class="dct-rs dct-rs--<?= $h(strtolower($r['code'])) ?>"><?= $h($r['label']) ?></span></li>
                                        <?php endforeach; ?>
                                    </ul>
                                    <?php if (count($missing) > 30): ?><p class="dct-hint">Et <?= count($missing) - 30 ?> autre(s) : la liste complète est dans le suivi de diffusion.</p><?php endif; ?>
                                <?php else: ?>
                                    <p class="dct-ok"><?= dct_icon('check') ?><span>Tous les destinataires ont lu la version en vigueur.</span></p>
                                <?php endif; ?>
                                <?php if ($doneList !== []): ?>
                                    <details class="dct-fold">
                                        <summary>Ont lu (<?= count($doneList) ?>)</summary>
                                        <ul class="dct-people">
                                            <?php foreach (array_slice($doneList, 0, 60) as $r): ?>
                                                <?php $when = $r['signed_at'] ?: $r['viewed_at']; ?>
                                                <li><span class="dct-people__n"><?= $h($r['name']) ?><?php if ($r['unit'] !== ''): ?><small><?= $h($r['unit']) ?></small><?php endif; ?></span><span class="dct-rs dct-rs--<?= $h(strtolower($r['code'])) ?>"><?= $h($r['label']) ?><?= $when ? ' · ' . $h(dct_date((string) $when)) : '' ?></span></li>
                                            <?php endforeach; ?>
                                        </ul>
                                    </details>
                                <?php endif; ?>
                                <div class="dct-links">
                                    <?php if (!empty($can['compliance'])): ?>
                                        <a href="<?= $h($complianceUrl . '?document_id=' . $id) ?>"><?= dct_icon('eye') ?><span>Suivi détaillé</span></a>
                                        <a href="<?= $h($exportUrl . '?document_id=' . $id) ?>"><?= dct_icon('dl') ?><span>Exporter (CSV)</span></a>
                                    <?php endif; ?>
                                </div>
                            <?php else: ?>
                                <p class="dct-hint">Les lectures se comptent à partir de la publication. <?= $d['recipients_total'] ?> destinataire(s) prévu(s) aujourd’hui.</p>
                            <?php endif; ?>

                            <h3 class="dct-dr__k">Destinataires</h3>
                            <ul class="dct-chips">
                                <?php foreach ($d['audiences'] as $a): ?>
                                    <li class="dct-chip">
                                        <?= dct_icon($a['kind'] === 'job' ? 'job' : 'users') ?>
                                        <?php if ($a['kind'] === 'job' && function_exists('effectifs_workspace_url')): ?>
                                            <a href="<?= $h(effectifs_workspace_url('fonctions')) ?>" title="Ouvrir le catalogue des emplois"><?= $h($a['label']) ?></a>
                                        <?php else: ?>
                                            <span><?= $h($a['label']) ?></span>
                                        <?php endif; ?>
                                        <?php if ($a['children']): ?><small>et sous-unités</small><?php endif; ?>
                                        <?php if ($a['level'] !== '' && $a['level'] !== 'normal'): ?><?= \App\Support\ConfidentialDisplay::levelBadge($a['level'], 'unit', true) ?><?php endif; ?>
                                    </li>
                                <?php endforeach; ?>
                            </ul>

                            <dl class="dct-dl">
                                <dt>Type</dt><dd><?= $h($d['type_label'] !== '' ? $d['type_label'] : '—') ?></dd>
                                <dt>Domaine</dt><dd><?= $h($d['domain_label'] !== '' ? $d['domain_label'] . ($d['subdomain_label'] !== '' ? ' · ' . $d['subdomain_label'] : '') : '—') ?></dd>
                                <?php if ($d['issuing_label'] !== ''): ?><dt>Émis par</dt><dd><?= $h($d['issuing_label']) ?></dd><?php endif; ?>
                                <dt>État</dt><dd><?= $h($d['workflow_label']) ?></dd>
                                <?php if ($d['published_at']): ?><dt>Publié le</dt><dd><?= $h(dct_date($d['published_at'])) ?></dd><?php endif; ?>
                                <?php if ($d['effective_at']): ?><dt>Prise d’effet</dt><dd><?= $h(dct_date($d['effective_at'])) ?></dd><?php endif; ?>
                                <?php if ($d['expires_at']): ?><dt>Fin d’application</dt><dd><?= $h(dct_date($d['expires_at'])) ?></dd><?php endif; ?>
                                <dt>Visibilité</dt><dd><?= $d['visibility_mode'] === 'library' ? 'Aussi dans la bibliothèque' : 'Destinataires seulement' ?></dd>
                            </dl>

                            <?php if ($d['versions'] !== []): ?>
                                <h3 class="dct-dr__k">Versions</h3>
                                <ol class="dct-tl">
                                    <?php foreach ($d['versions'] as $v): ?>
                                        <li class="<?= $v['current'] ? 'is-cur' : '' ?>">
                                            <b translate="no"><?= $h($v['label']) ?></b>
                                            <?php if ($v['current']): ?><span class="dct-tag">En vigueur</span><?php endif; ?>
                                            <?php if ($v['reset']): ?><span class="dct-tag is-warn">Relecture exigée</span><?php endif; ?>
                                            <?php if ($v['published_at']): ?><small><?= $h(dct_date($v['published_at'])) ?></small><?php endif; ?>
                                            <?php if ($v['summary'] !== ''): ?><p><?= $h($v['summary']) ?></p><?php endif; ?>
                                        </li>
                                    <?php endforeach; ?>
                                </ol>
                            <?php endif; ?>

                            <?php if ($d['history'] !== []): ?>
                                <h3 class="dct-dr__k">Historique</h3>
                                <ul class="dct-hist">
                                    <?php foreach ($d['history'] as $ev): ?>
                                        <li><b><?= $h($ev['label']) ?></b><small><?= $h(dct_date($ev['at'], true)) ?><?= $ev['actor'] !== '' ? ' · ' . $h($ev['actor']) : '' ?></small></li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                        </div>
                    </div>
                </article>
            <?php endforeach; ?>
            </div>

            <div class="dct-empty" data-dct-nomatch hidden>
                <div class="dct-empty__ill"><?= dct_icon('search', 'dct-i--lg') ?></div>
                <b>Aucun document ne correspond</b>
                <p>Changez de type, d’état ou d’échéance, ou effacez la recherche. Les documents archivés sont dans l’état « Archivés et abrogés ».</p>
                <button type="button" class="dct-btn" data-dct-reset>Effacer les filtres</button>
            </div>
            <?php if ($docs === []): ?>
                <div class="dct-empty">
                    <div class="dct-empty__ill"><?= dct_icon('file', 'dct-i--lg') ?></div>
                    <b>Aucun document doctrinal pour l’instant</b>
                    <p>Commencez par votre texte le plus utile : le règlement intérieur, une procédure radio ou la note d’accueil des recrues. Vous choisirez ensuite qui doit le lire.</p>
                    <?php if (!empty($can['create'])): ?>
                        <div class="dct-empty__acts">
                            <a class="dct-btn is-dark" href="<?= $h(url('back-office/documents/publier')) ?>"><?= dct_icon('plus') ?><span>Rédiger un premier document</span></a>
                            <?php if (!empty($can['manage']) && $types === []): ?><a class="dct-btn" href="<?= $h(url('back-office/doctrine?onglet=types')) ?>">Créer les types</a><?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </section>
    </div>

    <?php require base_path('views/admin/organization/partials/doctrine_sop.php'); ?>

    <dialog class="dct-dr" aria-labelledby="dct-dr-title" data-dct-drawer>
        <h2 id="dct-dr-title" class="dct-sr">Détail du document</h2>
        <div class="dct-dr__in" data-dct-drawer-body></div>
    </dialog>

    <?php if (!empty($can['remind']) && $toRemind > 0): ?>
    <dialog class="dct-md" aria-labelledby="dct-remind-title" data-dct-remind>
        <form method="post" action="<?= $h(url('back-office/doctrine/relances')) ?>">
            <input type="hidden" name="_csrf_token" value="<?= $csrf ?>">
            <div class="dct-md__h">
                <div><span class="dct-k">Relance groupée</span><h2 id="dct-remind-title">Relancer les retardataires</h2></div>
                <button type="button" class="dct-btn is-icon" data-dct-md-close aria-label="Fermer"><?= dct_icon('x') ?></button>
            </div>
            <div class="dct-md__b">
                <p><?= $toRemind ?> document(s) publié(s) attendent encore <?= $st('missing') ?> lecture(s). Chaque destinataire concerné reçoit un rappel dans Athena, avec le lien du document.</p>
                <label class="dct-fl"><span>Message joint <small>facultatif</small></span>
                    <textarea name="note" rows="3" maxlength="500" placeholder="Ex. À lire avant l’opération de samedi."></textarea>
                </label>
            </div>
            <div class="dct-md__f">
                <button type="button" class="dct-btn" data-dct-md-close>Annuler</button>
                <button type="submit" class="dct-btn is-dark"><?= dct_icon('bell') ?><span>Envoyer les rappels</span></button>
            </div>
        </form>
    </dialog>
    <?php endif; ?>
</div>
<noscript><style>.dct .dct-doc .dct-doc__detail[hidden]{display:block!important;grid-column:1/-1}.dct-doc__detail .dct-dr__h .dct-btn{display:none}</style></noscript>
<script src="<?= $h(asset_url('assets/js/doctrine-hub.js')) ?>" defer></script>
