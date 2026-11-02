<?php
declare(strict_types=1);

/**
 * Doctrine > Publier un document : rédaction, classement, exigences de lecture et destinataires
 * (POST back-office/documents/publier, action = save | submit_validation | publish).
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
?>
<div class="dct">
    <?= $headHtml ?>
    <?= dct_flashes() ?>

    <form id="doc-publish-form" method="post" action="<?= $h(url('back-office/documents/publier')) ?>" class="dct-split">
        <input type="hidden" name="_csrf_token" value="<?= $csrf ?>">
        <input type="hidden" name="document_id" value="<?= $docId ?>">
        <input type="hidden" name="action" id="doc-publish-action" value="save">
        <?php if ($fromRef !== null): ?>
        <input type="hidden" name="source_reference" value="<?= $h($fromRef['key']) ?>">
        <p class="dct-flash is-ok" role="status" style="grid-column:1/-1"><?= dct_icon('book') ?><span>Pré-rempli à partir de la référence <b translate="no"><?= $h($fromRef['code']) ?></b> du référentiel doctrinal. Le document y sera rattaché à l’enregistrement.</span></p>
        <?php endif; ?>

        <div style="display:grid;gap:16px;min-width:0">
            <section class="dct-form" aria-labelledby="dct-p-id">
                <h2 class="dct-form__t" id="dct-p-id">1 · Le texte</h2>
                <div class="dct-grid dct-grid--2">
                    <label class="dct-fl dct-fl--full"><span>Titre</span><input name="title" required maxlength="255" value="<?= $h($document['title'] ?? '') ?>" placeholder="Ex. Procédure radio en opération"></label>
                    <label class="dct-fl dct-fl--full"><span>Objet <small>une phrase qui dit à quoi sert le texte</small></span>
                        <textarea name="object" rows="2"><?= $h($doctrine['summary'] ?? $document['short_description'] ?? '') ?></textarea>
                    </label>
                </div>
                <label class="dct-fl"><span>Contenu</span>
                    <textarea id="doc-body-html" name="body_html" rows="16"><?= $h($doctrine['body_html'] ?? '') ?></textarea>
                    <small>Titres, listes, tableaux et liens. Pour importer un texte existant, collez-le ici : la mise en forme simple est conservée.</small>
                </label>
            </section>

            <section class="dct-form" aria-labelledby="dct-p-class">
                <h2 class="dct-form__t" id="dct-p-class">2 · Le classement</h2>
                <div class="dct-grid dct-grid--2">
                    <label class="dct-fl"><span>Type</span>
                        <select name="document_type_id" required>
                            <option value="">Choisir…</option>
                            <?php foreach ($types as $t): ?>
                                <option value="<?= (int) $t['id'] ?>"<?= (int) ($doctrine['document_type_id'] ?? 0) === (int) $t['id'] ? ' selected' : '' ?>><?= $h($t['label'] ?? '') ?> (<?= $h($t['code_prefix'] ?? '') ?>)</option>
                            <?php endforeach; ?>
                        </select>
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

            <section class="dct-form" aria-labelledby="dct-p-read">
                <h2 class="dct-form__t" id="dct-p-read">3 · La lecture attendue</h2>
                <div class="dct-checks">
                    <label class="dct-check"><input type="checkbox" name="reading_required" value="1"<?= !empty($doctrine['reading_required']) ? ' checked' : '' ?>>Lecture obligatoire</label>
                    <label class="dct-check"><input type="checkbox" name="acknowledgment_required" value="1"<?= !empty($doctrine['acknowledgment_required']) ? ' checked' : '' ?>>Accusé de lecture</label>
                    <label class="dct-check"><input type="checkbox" name="reminder_on_publish" value="1"<?= !isset($doctrine['reminder_on_publish']) || !empty($doctrine['reminder_on_publish']) ? ' checked' : '' ?>>Prévenir à la publication</label>
                    <label class="dct-check"><input type="checkbox" name="require_validation" value="1"<?= !empty($doctrine['require_validation']) ? ' checked' : '' ?>>Validation avant publication</label>
                    <label class="dct-check"><input type="checkbox" name="is_permanent" value="1"<?= !empty($doctrine['is_permanent']) ? ' checked' : '' ?>>Document permanent</label>
                </div>
                <div class="dct-grid dct-grid--3">
                    <label class="dct-fl"><span>Prise d’effet</span><input type="datetime-local" name="effective_at" value="<?= $dt($doctrine, 'effective_at') ?>"></label>
                    <label class="dct-fl"><span>À lire avant le</span><input type="datetime-local" name="acknowledgment_deadline_at" value="<?= $dt($doctrine, 'acknowledgment_deadline_at') ?>"></label>
                    <label class="dct-fl"><span>Fin d’application</span><input type="datetime-local" name="expires_at" value="<?= $dt($doctrine, 'expires_at') ?>"></label>
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

            <section class="dct-form" aria-labelledby="dct-p-aud">
                <h2 class="dct-form__t" id="dct-p-aud">4 · Les destinataires</h2>
                <div class="dct-choice">
                    <label class="dct-opt"><input type="radio" name="audience_mode" value="all_members"<?= $audienceMode === 'all_members' ? ' checked' : '' ?>><b>Toute la communauté</b><small>Chaque membre actif, y compris ceux qui arriveront plus tard.</small></label>
                    <label class="dct-opt"><input type="radio" name="audience_mode" value="targeted"<?= $audienceMode === 'targeted' ? ' checked' : '' ?>><b>Des destinataires choisis</b><small>Unités, emplois, rôles ou membres : cumulables.</small></label>
                </div>
                <div id="audience-targeted" class="dct-grid dct-grid--2"<?= $audienceMode === 'targeted' ? '' : ' hidden' ?>>
                    <label class="dct-fl"><span>Unités</span>
                        <select name="audience_units[]" multiple size="6">
                            <?php foreach ($units as $u): ?>
                                <option value="<?= (int) $u['id'] ?>"<?= in_array((int) $u['id'], $selectedUnits, true) ? ' selected' : '' ?>><?= $h($u['name'] ?? '') ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label class="dct-fl"><span>Emplois</span>
                        <select name="audience_job_roles[]" multiple size="6">
                            <?php foreach ($jobRoles as $jr): ?>
                                <option value="<?= (int) ($jr['id'] ?? 0) ?>"<?= in_array((int) ($jr['id'] ?? 0), $selectedJobRoles, true) ? ' selected' : '' ?>><?= $h($jr['label'] ?? '') ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label class="dct-check dct-fl--full"><input type="checkbox" name="include_children" value="1"<?= $includeChildren ? ' checked' : '' ?>>Inclure les sous-unités des unités choisies</label>
                    <label class="dct-fl"><span>Rôles Athena</span>
                        <select name="audience_roles[]" multiple size="4">
                            <?php foreach ($roles as $r): ?>
                                <option value="<?= $h($r['slug'] ?? '') ?>"<?= in_array((string) ($r['slug'] ?? ''), $selectedRoles, true) ? ' selected' : '' ?>><?= $h($r['name'] ?? $r['slug'] ?? '') ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label class="dct-fl"><span>Membres</span>
                        <select name="audience_users[]" multiple size="6">
                            <?php foreach ($users as $u): ?>
                                <option value="<?= (int) $u['id'] ?>"<?= in_array((int) $u['id'], $selectedUsers, true) ? ' selected' : '' ?>><?= $h($u['display_name'] ?? $u['email'] ?? '') ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <p class="dct-hint dct-fl--full">Ctrl ou Cmd + clic pour choisir plusieurs lignes.</p>
                </div>
            </section>
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
            <button type="submit" class="dct-btn" data-dct-action="submit_validation">Soumettre à validation</button>
            <?php if (!empty($can['publish'])): ?>
                <button type="submit" class="dct-btn is-dark" data-dct-action="publish"><?= dct_icon('send') ?><span>Publier</span></button>
            <?php else: ?>
                <p class="dct-hint">Vous pouvez préparer le document ; la publication est réservée aux membres qui en ont le droit.</p>
            <?php endif; ?>
            <?php if ($isEdit): ?>
                <a class="dct-btn" href="<?= $h(url('documents/doctrine/' . $docId)) ?>"><?= dct_icon('eye') ?><span>Voir comme un lecteur</span></a>
            <?php endif; ?>
        </aside>
    </form>
</div>
<script>
(function () {
  var form = document.getElementById('doc-publish-form');
  var action = document.getElementById('doc-publish-action');
  var box = document.getElementById('audience-targeted');
  if (!form) return;
  form.querySelectorAll('[data-dct-action]').forEach(function (b) {
    b.addEventListener('click', function () { if (action) action.value = b.getAttribute('data-dct-action'); });
  });
  function sync() {
    var mode = form.querySelector('input[name="audience_mode"]:checked');
    if (box && mode) box.hidden = mode.value !== 'targeted';
  }
  form.querySelectorAll('input[name="audience_mode"]').forEach(function (r) { r.addEventListener('change', sync); });
  sync();
})();
</script>
<script src="<?= $h(asset_url('assets/js/document_rich_editor.js')) ?>" defer></script>
