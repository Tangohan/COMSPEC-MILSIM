<?php
declare(strict_types=1);

/**
 * Doctrine > Publier un document : assistant en 5 étapes (texte, classement, destinataires, lecture attendue,
 * récapitulatif) — POST back-office/documents/publier, action = save | submit_validation | publish.
 * Sans JavaScript, toutes les étapes restent affichées et le formulaire complet reste utilisable.
 */

require_once base_path('views/admin/documents/partials/doctrine_ui.php');

$document = is_array($document ?? null) ? $document : null;
$doctrine = is_array($doctrine ?? null) ? $doctrine : null;
$types = is_array($types ?? null) ? $types : [];
$domains = is_array($domains ?? null) ? $domains : [];
$units = is_array($units ?? null) ? $units : [];
$jobRoles = is_array($jobRoles ?? null) ? $jobRoles : [];
$roles = is_array($roles ?? null) ? $roles : [];
$users = is_array($users ?? null) ? $users : [];
$publishedDocs = is_array($publishedDocs ?? null) ? $publishedDocs : [];
$audiences = is_array($audiences ?? null) ? $audiences : [];
$can = is_array($can ?? null) ? $can : [];
$csrf = dct_e((string) ($csrf_token ?? ''));
$docId = (int) ($document['id'] ?? 0);
$status = (string) ($doctrine['doctrine_status'] ?? 'draft');

$audienceMode = 'all_members';
$selectedUnits = [];
$selectedJobRoles = [];
$selectedRoles = [];
$selectedUsers = [];
$includeChildren = false;
foreach ($audiences as $a) {
    $t = (string) ($a['audience_type'] ?? '');
    if ($t === 'unit') {
        $audienceMode = 'targeted';
        $selectedUnits[] = (int) ($a['audience_value'] ?? 0);
        if (!empty($a['include_children'])) {
            $includeChildren = true;
        }
    } elseif ($t === 'job_role') {
        $audienceMode = 'targeted';
        $selectedJobRoles[] = (int) ($a['audience_value'] ?? 0);
    } elseif ($t === 'role') {
        $audienceMode = 'targeted';
        $selectedRoles[] = (string) ($a['audience_value'] ?? '');
    } elseif ($t === 'user') {
        $audienceMode = 'targeted';
        $selectedUsers[] = (int) ($a['audience_value'] ?? 0);
    }
}

$h = static fn (mixed $v): string => dct_e((string) $v);
$dt = static function (?array $row, string $key) use ($h): string {
    $raw = $row[$key] ?? null;
    $ts = $raw ? strtotime((string) $raw) : false;

    return $ts ? $h(date('Y-m-d\TH:i', $ts)) : '';
};
$isEdit = $docId > 0;
// Pré-remplissage depuis Doctrine › Référentiel (« Créer un document à partir de cette référence »).
$fromRef = !$isEdit && is_array($fromReference ?? null) ? $fromReference : null;
if ($fromRef !== null) {
    $refTitle = (string) ($fromRef['title_local'] !== '' ? $fromRef['title_local'] : $fromRef['title']);
    $document = ['title' => mb_substr($fromRef['code'] . ' — ' . $refTitle, 0, 255)];
    $refLink = $fromRef['source_url'] !== '' ? ' <a href="' . dct_e($fromRef['source_url']) . '" rel="noopener">' . dct_e($fromRef['source_label']) . '</a>' : '';
    $doctrine = [
        'summary' => '',
        'issuing_label' => '',
        'body_html' => '<p><strong>' . (\App\Services\Doctrine\DoctrineLibraryCatalog::lang() === 'en' ? 'Reference:' : 'Référence :') . '</strong> '
            . dct_e($fromRef['code'] . ' — ' . $fromRef['title'] . ($fromRef['issuer'] !== '' ? ' (' . $fromRef['issuer'] . ($fromRef['edition'] !== '' ? ', ' . $fromRef['edition'] : '') . ')' : ''))
            . $refLink . '</p><p></p>',
    ];
}
$stateLabel = \App\Services\Doctrine\DoctrineHubService::stateLabel(match ($status) {
    'review', 'approval' => 'review',
    'published' => 'published',
    default => 'draft',
});
$levels = ['interne' => 'Normal', 'restreint' => 'Restreint', 'commandement' => 'Commandement', 'confidentiel' => 'Confidentiel interne'];
$curLevel = (string) ($document['classification_level'] ?? 'interne');
$back = '<a class="dct-btn" href="' . dct_e(url('back-office/doctrine') . ($isEdit ? '#doc-' . $docId : '')) . '">' . dct_icon('back') . '<span>Retour à la doctrine</span></a>';
$headHtml = dct_head(
    $isEdit ? 'Doctrine · ' . (string) ($doctrine['reference_code'] ?? '') : 'Doctrine · Nouveau document',
    $isEdit ? 'Modifier le brouillon' : 'Rédiger et diffuser un document',
    'Écrivez le texte, classez-le, dites qui doit le lire et avant quand. Enregistrez en brouillon autant que nécessaire : rien n’est envoyé avant la publication.',
    $back,
    $isEdit ? 'hub' : 'publish',
    $can
    );
$wizardSteps = ['Le texte', 'Le classement', 'Les destinataires', 'La lecture attendue', 'Récapitulatif & publication'];
/**
 * Liste à cocher filtrable (destinataires) : mêmes noms de champs que les anciennes listes multiples.
 *
 * @param list<array{0: string, 1: string}> $options [valeur, libellé]
 * @param list<string> $selected
 */
$pick = static function (string $key, string $name, string $label, array $options, array $selected, string $empty) use ($h): string {
    $count = 0;
    $items = '';
    foreach ($options as [$value, $text]) {
        $on = in_array($value, $selected, true);
        $count += $on ? 1 : 0;
        $items .= '<li data-wiz-item="' . $h(mb_strtolower($text)) . '"><label class="dct-wiz__opt">'
            . '<input type="checkbox" name="' . $h($name) . '" value="' . $h($value) . '" data-wiz-label="' . $h($text) . '"' . ($on ? ' checked' : '') . '>'
            . '<span>' . $h($text) . '</span></label></li>';
    }
    $out = '<fieldset class="dct-fl dct-wiz__pick" data-wiz-pick="' . $h($key) . '">'
        . '<legend>' . $h($label) . ' <small data-wiz-pick-count>' . $count . ' choisi(s)</small></legend>';
    if ($options === []) {
        return $out . '<p class="dct-hint">' . $h($empty) . '</p></fieldset>';
    }
    $out .= '<div class="dct-wiz__pickbar" data-wiz-js hidden>'
        . '<input type="search" autocomplete="off" placeholder="Rechercher…" aria-label="Rechercher dans la liste" data-wiz-filter>'
        . '<button type="button" class="dct-btn" data-wiz-all>Tout cocher</button>'
        . '<button type="button" class="dct-btn" data-wiz-none>Tout décocher</button>'
        . '</div>'
        . '<ul class="dct-wiz__opts">' . $items . '</ul>'
        . '<p class="dct-hint" data-wiz-nomatch hidden>Aucun résultat pour cette recherche.</p>';

    return $out . '</fieldset>';
};
$unitOptions = array_map(static fn (array $u): array => [(string) (int) $u['id'], (string) ($u['name'] ?? '')], $units);
$jobOptions = array_map(static fn (array $jr): array => [(string) (int) ($jr['id'] ?? 0), (string) ($jr['label'] ?? '')], $jobRoles);
$roleOptions = array_map(static fn (array $r): array => [(string) ($r['slug'] ?? ''), (string) ($r['name'] ?? $r['slug'] ?? '')], $roles);
$userOptions = array_map(static fn (array $u): array => [(string) (int) $u['id'], (string) ($u['display_name'] ?? $u['email'] ?? '')], $users);
$asStrings = static fn (array $v): array => array_map('strval', $v);
?>
<div class="dct">
    <?= $headHtml ?>
    <?= dct_flashes() ?>

    <form id="doc-publish-form" method="post" action="<?= $h(url('back-office/documents/publier')) ?>" class="dct-split dct-wiz" data-doc-wizard data-draft-key="<?= $h($isEdit ? 'doc-' . $docId : 'new' . ($fromRef !== null ? '-' . $fromRef['key'] : '')) ?>"<?= $isEdit ? ' data-wiz-edit' : '' ?>>
        <input type="hidden" name="_csrf_token" value="<?= $csrf ?>">
        <input type="hidden" name="document_id" value="<?= $docId ?>">
        <input type="hidden" name="action" id="doc-publish-action" value="save">
        <?php if ($fromRef !== null): ?>
        <input type="hidden" name="source_reference" value="<?= $h($fromRef['key']) ?>">
        <p class="dct-flash is-ok" role="status" style="grid-column:1/-1"><?= dct_icon('book') ?><span>Pré-rempli à partir de la référence <b translate="no"><?= $h($fromRef['code']) ?></b> du référentiel doctrinal. Le document y sera rattaché à l’enregistrement.</span></p>
        <?php endif; ?>

        <div class="dct-wiz__main">
            <div class="dct-wiz__restore dct-flash is-ok" role="status" data-wiz-restore hidden>
                <?= dct_icon('clock') ?>
                <span data-wiz-restore-text>Une saisie non enregistrée a été retrouvée sur cet appareil.</span>
                <span class="dct-wiz__restore-acts">
                    <button type="button" class="dct-btn" data-wiz-restore-yes>Reprendre ma saisie</button>
                    <button type="button" class="dct-btn" data-wiz-restore-no>Ignorer</button>
                </span>
            </div>

            <div class="dct-wiz__progress" data-wiz-progress hidden>
                <div class="dct-wiz__progress-t">
                    <p class="dct-wiz__heading" data-wiz-heading aria-live="polite">Étape 1 sur <?= count($wizardSteps) ?> — <?= $h($wizardSteps[0]) ?></p>
                    <p class="dct-hint" data-wiz-next-label>Étape suivante : <?= $h($wizardSteps[1]) ?></p>
                </div>
                <div class="dct-wiz__bar" role="progressbar" aria-label="Avancement" aria-valuemin="1" aria-valuemax="<?= count($wizardSteps) ?>" aria-valuenow="1" data-wiz-bar><span></span></div>
                <?php
                $steps = [];
                foreach ($wizardSteps as $i => $lab) {
                    $steps[] = ['label' => $lab, 'active' => $i === 0];
                }
                require base_path('views/partials/ui/stepper.php');
                ?>
            </div>

            <section class="dct-form dct-wiz__step" id="etape-1" data-wiz-step aria-labelledby="dct-p-id">
                <h2 class="dct-form__t" id="dct-p-id" tabindex="-1">1 · Le texte</h2>
                <div class="dct-grid dct-grid--2">
                    <label class="dct-fl dct-fl--full"><span>Titre</span><input id="doc-title" name="title" required maxlength="255" value="<?= $h($document['title'] ?? '') ?>" placeholder="Ex. Procédure radio en opération" aria-describedby="doc-title-msg">
                        <small id="doc-title-msg" class="dct-wiz__msg" data-wiz-msg="title"></small>
                    </label>
                    <label class="dct-fl"><span>Titre court <small>facultatif, pour les listes et les notifications</small></span><input name="short_title" maxlength="120" value="<?= $h($doctrine['short_title'] ?? $document['short_title'] ?? '') ?>" placeholder="Ex. Procédure radio"></label>
                    <label class="dct-fl dct-fl--full"><span>Objet <small>une phrase qui dit à quoi sert le texte</small></span>
                        <textarea name="object" rows="2"><?= $h($doctrine['summary'] ?? $document['short_description'] ?? '') ?></textarea>
                    </label>
                </div>
                <div class="dct-wiz__drop" data-wiz-drop data-wiz-js hidden>
                    <?= dct_icon('file', 'dct-i--lg') ?>
                    <div>
                        <b>Importer un texte existant</b>
                        <small>Glissez un fichier ici ou <label class="dct-wiz__browse">choisissez-le<input type="file" accept=".html,.htm,.txt,.md,text/html,text/plain,text/markdown" data-wiz-file></label> (page web, texte ou Markdown, 2 Mo au plus). Son contenu remplace celui de l’éditeur.</small>
                    </div>
                    <span class="dct-wiz__busy" data-wiz-drop-status role="status" aria-live="polite"></span>
                </div>
                <label class="dct-fl"><span>Contenu</span>
                    <textarea id="doc-body-html" name="body_html" rows="16"><?= $h($doctrine['body_html'] ?? '') ?></textarea>
                    <small>Titres, listes, tableaux et liens. Pour importer un texte existant, collez-le ici : la mise en forme simple est conservée.</small>
                    <small class="dct-wiz__busy" data-wiz-editor-status role="status" aria-live="polite" data-wiz-js hidden>Chargement de l’éditeur…</small>
                </label>
            </section>

            <section class="dct-form dct-wiz__step" id="etape-2" data-wiz-step aria-labelledby="dct-p-class">
                <h2 class="dct-form__t" id="dct-p-class" tabindex="-1">2 · Le classement</h2>
                <div class="dct-grid dct-grid--2">
                    <label class="dct-fl"><span>Type</span>
                        <select id="doc-type" name="document_type_id" required aria-describedby="doc-type-msg">
                            <option value="">Choisir…</option>
                            <?php foreach ($types as $t): ?>
                                <option value="<?= (int) $t['id'] ?>"<?= (int) ($doctrine['document_type_id'] ?? 0) === (int) $t['id'] ? ' selected' : '' ?>><?= $h($t['label'] ?? '') ?> (<?= $h($t['code_prefix'] ?? '') ?>)</option>
                            <?php endforeach; ?>
                        </select>
                        <small id="doc-type-msg" class="dct-wiz__msg" data-wiz-msg="type"></small>
                        <?php if ($types === []): ?><small>Aucun type actif : <a href="<?= $h(url('back-office/doctrine?onglet=types')) ?>">créez un type</a> avant de publier.</small><?php endif; ?>
                    </label>
                    <label class="dct-fl"><span>Domaine <small>facultatif</small></span>
                        <select name="domain_id"<?= $isEdit ? ' disabled' : '' ?>>
                            <option value="">Aucun : numérotation par type</option>
                            <?php foreach ($domains as $dm): ?>
                                <?php if (array_key_exists('is_active', $dm) && empty($dm['is_active'])) { continue; } ?>
                                <option value="<?= (int) $dm['id'] ?>"<?= (int) ($doctrine['domain_id'] ?? 0) === (int) $dm['id'] ? ' selected' : '' ?>><?= $h($dm['label'] ?? '') ?> (<?= $h($dm['doc_prefix'] ?? $dm['code'] ?? '') ?>)</option>
                            <?php endforeach; ?>
                        </select>
                        <?php if ($isEdit): ?><small>Fixé à la création, comme la référence.</small><?php endif; ?>
                    </label>
                    <label class="dct-fl"><span>Référence <small><?= $isEdit ? 'attribuée' : 'vide = attribuée automatiquement' ?></small></span>
                        <input class="dct-mono" name="reference_code" placeholder="NS-2026-014" value="<?= $h($doctrine['reference_code'] ?? '') ?>"<?= $isEdit ? ' readonly' : '' ?>>
                    </label>
                    <label class="dct-fl"><span>Confidentialité</span>
                        <select name="classification_level">
                            <?php foreach ($levels as $val => $lab): ?>
                                <option value="<?= $h($val) ?>"<?= $curLevel === $val ? ' selected' : '' ?>><?= $h($lab) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label class="dct-fl"><span>Autorité émettrice</span><input name="issuing_label" value="<?= $h($doctrine['issuing_label'] ?? '') ?>" placeholder="État-major / Commandement"></label>
                    <label class="dct-fl"><span>Unité émettrice</span>
                        <select name="issuing_unit_id">
                            <option value="">—</option>
                            <?php foreach ($units as $u): ?>
                                <option value="<?= (int) $u['id'] ?>"<?= (int) ($doctrine['issuing_unit_id'] ?? 0) === (int) $u['id'] ? ' selected' : '' ?>><?= $h($u['name'] ?? '') ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label class="dct-fl dct-fl--full"><span>Remplace le document <small>l’ancien passera « Abrogé » à la publication</small></span>
                        <select name="replaces_document_id">
                            <option value="">Aucun</option>
                            <?php foreach ($publishedDocs as $pd): ?>
                                <?php if ((int) ($pd['document_id'] ?? 0) === $docId) { continue; } ?>
                                <option value="<?= (int) ($pd['document_id'] ?? 0) ?>"<?= (int) ($doctrine['replaces_document_id'] ?? 0) === (int) ($pd['document_id'] ?? 0) ? ' selected' : '' ?>><?= $h(($pd['reference_code'] ?? '') . ' — ' . ($pd['title'] ?? '')) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                </div>
            </section>

            <section class="dct-form dct-wiz__step" id="etape-3" data-wiz-step aria-labelledby="dct-p-aud">
                <h2 class="dct-form__t" id="dct-p-aud" tabindex="-1">3 · Les destinataires</h2>
                <div class="dct-choice">
                    <label class="dct-opt"><input type="radio" name="audience_mode" value="all_members"<?= $audienceMode === 'all_members' ? ' checked' : '' ?>><b>Toute la communauté</b><small>Chaque membre actif, y compris ceux qui arriveront plus tard.</small></label>
                    <label class="dct-opt"><input type="radio" name="audience_mode" value="targeted"<?= $audienceMode === 'targeted' ? ' checked' : '' ?>><b>Des destinataires choisis</b><small>Unités, emplois, rôles ou membres : cumulables.</small></label>
                </div>
                <div id="audience-targeted" class="dct-wiz__aud"<?= $audienceMode === 'targeted' ? '' : ' hidden' ?>>
                    <p class="dct-wiz__total" data-wiz-aud-total aria-live="polite"></p>
                    <small class="dct-wiz__msg" data-wiz-msg="audience" role="alert"></small>
                    <div class="dct-grid dct-grid--2">
                        <div class="dct-wiz__col">
                            <?= $pick('units', 'audience_units[]', 'Unités', $unitOptions, $asStrings($selectedUnits), 'Aucune unité dans la communauté.') ?>
                            <label class="dct-check"><input type="checkbox" name="include_children" value="1"<?= $includeChildren ? ' checked' : '' ?>>Inclure les sous-unités des unités choisies</label>
                        </div>
                        <?= $pick('jobs', 'audience_job_roles[]', 'Emplois', $jobOptions, $asStrings($selectedJobRoles), 'Aucun emploi défini.') ?>
                        <?= $pick('roles', 'audience_roles[]', 'Rôles Athena', $roleOptions, $selectedRoles, 'Aucun rôle défini.') ?>
                        <?= $pick('users', 'audience_users[]', 'Membres', $userOptions, $asStrings($selectedUsers), 'Aucun membre.') ?>
                    </div>
                </div>
            </section>

            <section class="dct-form dct-wiz__step" id="etape-4" data-wiz-step aria-labelledby="dct-p-read">
                <h2 class="dct-form__t" id="dct-p-read" tabindex="-1">4 · La lecture attendue</h2>
                <div class="dct-checks">
                    <label class="dct-check"><input type="checkbox" name="reading_required" value="1"<?= !empty($doctrine['reading_required']) ? ' checked' : '' ?>>Lecture obligatoire</label>
                    <label class="dct-check"><input type="checkbox" name="acknowledgment_required" value="1"<?= !empty($doctrine['acknowledgment_required']) ? ' checked' : '' ?>>Accusé de lecture</label>
                    <label class="dct-check"><input type="checkbox" name="reminder_on_publish" value="1"<?= !isset($doctrine['reminder_on_publish']) || !empty($doctrine['reminder_on_publish']) ? ' checked' : '' ?>>Prévenir à la publication</label>
                    <label class="dct-check"><input type="checkbox" name="require_validation" value="1"<?= !empty($doctrine['require_validation']) ? ' checked' : '' ?>>Validation avant publication</label>
                    <label class="dct-check"><input type="checkbox" name="is_permanent" value="1"<?= !empty($doctrine['is_permanent']) ? ' checked' : '' ?>>Document permanent</label>
                </div>
                <div class="dct-grid dct-grid--3">
                    <label class="dct-fl"><span>Prise d’effet</span><input type="datetime-local" name="effective_at" value="<?= $dt($doctrine, 'effective_at') ?>"></label>
                    <label class="dct-fl"><span>À lire avant le</span><input type="datetime-local" name="acknowledgment_deadline_at" value="<?= $dt($doctrine, 'acknowledgment_deadline_at') ?>" aria-describedby="doc-deadline-msg">
                        <small id="doc-deadline-msg" class="dct-wiz__msg" data-wiz-msg="deadline"></small>
                    </label>
                    <label class="dct-fl"><span>Fin d’application</span><input type="datetime-local" name="expires_at" value="<?= $dt($doctrine, 'expires_at') ?>" aria-describedby="doc-expires-msg">
                        <small id="doc-expires-msg" class="dct-wiz__msg" data-wiz-msg="expires"></small>
                    </label>
                </div>
                <label class="dct-fl"><span>Phrase de confirmation <small>affichée au moment de l’accusé</small></span>
                    <input name="confirmation_text" value="<?= $h($doctrine['confirmation_text'] ?? '') ?>" placeholder="Je reconnais avoir pris connaissance de cette instruction.">
                </label>
                <label class="dct-fl"><span>Où le trouver</span>
                    <select name="visibility_mode">
                        <option value="recipients_only"<?= ($doctrine['visibility_mode'] ?? 'recipients_only') === 'recipients_only' ? ' selected' : '' ?>>Seulement chez les destinataires</option>
                        <option value="library"<?= ($doctrine['visibility_mode'] ?? '') === 'library' ? ' selected' : '' ?>>Aussi dans la bibliothèque de la communauté</option>
                    </select>
                </label>
            </section>

            <section class="dct-form dct-wiz__step" id="etape-5" data-wiz-step aria-labelledby="dct-p-recap">
                <h2 class="dct-form__t" id="dct-p-recap" tabindex="-1">5 · Récapitulatif &amp; publication</h2>
                <dl class="dct-dl dct-wiz__recap" data-wiz-js hidden>
                    <dt>Titre</dt><dd><span data-recap="title">—</span> <button type="button" class="dct-wiz__edit" data-wiz-goto="0">Modifier</button></dd>
                    <dt>Contenu</dt><dd><span data-recap="body">—</span> <button type="button" class="dct-wiz__edit" data-wiz-preview>Aperçu</button></dd>
                    <dt>Type</dt><dd><span data-recap="type">—</span> <button type="button" class="dct-wiz__edit" data-wiz-goto="1">Modifier</button></dd>
                    <dt>Confidentialité</dt><dd data-recap="level">—</dd>
                    <dt>Remplace</dt><dd data-recap="replaces">—</dd>
                    <dt>Destinataires</dt><dd><span data-recap="audience">—</span> <button type="button" class="dct-wiz__edit" data-wiz-goto="2">Modifier</button></dd>
                    <dt>Lecture</dt><dd><span data-recap="reading">—</span> <button type="button" class="dct-wiz__edit" data-wiz-goto="3">Modifier</button></dd>
                    <dt>À lire avant le</dt><dd data-recap="deadline">—</dd>
                    <dt>Où le trouver</dt><dd data-recap="visibility">—</dd>
                </dl>
                <div class="dct-wiz__preview" data-wiz-preview-box hidden>
                    <div class="dct-wiz__preview-h"><b>Aperçu du texte</b><button type="button" class="dct-btn" data-wiz-preview-close><?= dct_icon('x') ?><span>Fermer l’aperçu</span></button></div>
                    <iframe title="Aperçu du texte" sandbox="" data-wiz-preview-frame></iframe>
                </div>
                <p class="dct-hint">Rien n’est envoyé tant que vous ne publiez pas. Le brouillon reste modifiable après l’enregistrement.</p>
                <div class="dct-form__f dct-wiz__final">
                    <button type="submit" class="dct-btn" data-dct-action="submit_validation">Soumettre à validation</button>
                    <?php if (!empty($can['publish'])): ?>
                        <button type="submit" class="dct-btn is-dark" data-dct-action="publish"><?= dct_icon('send') ?><span>Publier</span></button>
                    <?php else: ?>
                        <p class="dct-hint">Vous pouvez préparer le document ; la publication est réservée aux membres qui en ont le droit.</p>
                    <?php endif; ?>
                </div>
            </section>

            <nav class="dct-wiz__pager" aria-label="Navigation entre les étapes" data-wiz-pager hidden>
                <button type="button" class="dct-btn" data-wiz-prev><?= dct_icon('back') ?><span>Étape précédente</span></button>
                <button type="button" class="dct-btn is-dark" data-wiz-next><span>Étape suivante</span><?= dct_icon('arrow') ?></button>
            </nav>
        </div>

        <aside class="dct-aside">
            <div class="dct-card">
                <span class="dct-k">État</span>
                <div><?= dct_state_pill($status === 'review' || $status === 'approval' ? 'review' : ($status === 'published' ? 'published' : 'draft'), $stateLabel) ?></div>
                <ol class="dct-stepbar">
                    <li><span><b>Enregistrer</b> : garde le brouillon, rien n’est envoyé.</span></li>
                    <li><span><b>Soumettre à validation</b> : pour les types qui l’exigent, un responsable publiera.</span></li>
                    <li><span><b>Publier</b> : fige la version v1.0 et prévient les destinataires.</span></li>
                </ol>
            </div>
            <button type="submit" class="dct-btn" data-dct-action="save">Enregistrer le brouillon</button>
            <p class="dct-hint" data-wiz-autosave role="status" aria-live="polite" data-wiz-js hidden>Votre saisie est conservée sur cet appareil jusqu’à l’enregistrement.</p>
            <?php if ($isEdit): ?>
                <a class="dct-btn" href="<?= $h(url('documents/doctrine/' . $docId)) ?>"><?= dct_icon('eye') ?><span>Voir comme un lecteur</span></a>
            <?php endif; ?>
        </aside>
    </form>
</div>
<script src="<?= $h(asset_url('assets/js/doctrine-publish-wizard.js')) ?>" defer></script>
<script src="<?= $h(asset_url('assets/js/document_rich_editor.js')) ?>" defer></script>
