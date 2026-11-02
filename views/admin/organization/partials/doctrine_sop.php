<?php
declare(strict_types=1);

/**
 * Doctrine > fiches SOP simples (tables doctrine_documents, API opérations) : création d’une fiche en brouillon
 * et mise en vigueur d’une version. Inclus par views/admin/organization/doctrine.php (helpers dct_*).
 *
 * @var list<array<string, mixed>> $doctrine_documents
 */

$sopDocs = is_array($doctrine_documents ?? null) ? $doctrine_documents : [];
$sopType = static fn (string $t): string => match ($t) {
    'sop' => 'SOP',
    'checklist' => 'Liste de contrôle',
    'report_format' => 'Format de compte rendu',
    default => $t !== '' ? ucfirst(str_replace('_', ' ', $t)) : 'Fiche',
};
$sopStatus = static fn (string $s): string => match ($s) {
    'active', 'published' => 'En vigueur',
    'archived' => 'Archivée',
    'superseded' => 'Remplacée',
    default => 'Brouillon',
};
$sopVersions = 0;
foreach ($sopDocs as $sd) {
    $sopVersions += count(is_array($sd['versions'] ?? null) ? $sd['versions'] : []);
}
$sopCsrf = dct_e((string) \App\Core\Csrf::token());
?>
<details class="dct-sop" id="sop">
    <summary>
        <?= dct_icon('layers') ?>
        <span><b>Fiches SOP simples</b><small><?= count($sopDocs) ?> fiche(s) · <?= $sopVersions ?> version(s) · texte court versionné, lu par l’API des opérations</small></span>
        <?= dct_icon('down', 'dct-guide__chev') ?>
    </summary>
    <div class="dct-sop__body">
        <p class="dct-hint">Une fiche SOP est un texte court (procédure, liste de contrôle, format de compte rendu) sans destinataires ni accusés. Pour un texte à faire lire et à suivre, utilisez plutôt « Nouveau document ».</p>

        <?php if ($sopDocs !== []): ?>
        <div class="dct-table-wrap">
            <table class="dct-table">
                <thead><tr><th scope="col">Fiche</th><th scope="col">Type</th><th scope="col">Version</th><th scope="col">Date d’effet</th><th scope="col">Lectures</th><th scope="col">État</th><th scope="col"><span class="dct-sr">Action</span></th></tr></thead>
                <tbody>
                <?php foreach ($sopDocs as $sd): ?>
                    <?php $currentId = (int) ($sd['current_version_id'] ?? 0); ?>
                    <?php foreach ((is_array($sd['versions'] ?? null) ? $sd['versions'] : []) as $v): ?>
                        <?php $vid = (int) ($v['id'] ?? 0); $active = $vid > 0 && $vid === $currentId; ?>
                        <tr>
                            <td><b><?= dct_e((string) ($sd['title'] ?? '')) ?></b></td>
                            <td><?= dct_e($sopType((string) ($sd['document_type'] ?? ''))) ?></td>
                            <td translate="no"><?= dct_e((string) ($v['version_label'] ?? '')) ?></td>
                            <td><?= dct_e(dct_date((string) ($v['effective_at'] ?? '')) ?: '—') ?></td>
                            <td><?= (int) ($v['ack_count'] ?? 0) ?></td>
                            <td><?= dct_state_pill($active ? 'published' : 'draft', $active ? 'En vigueur' : $sopStatus((string) ($v['status'] ?? ''))) ?></td>
                            <td>
                                <?php if (!$active): ?>
                                    <form method="post" action="<?= dct_e(url('back-office/doctrine/versions/' . $vid . '/activate')) ?>" data-confirm="Mettre cette version en vigueur ? Elle remplace la version actuelle pour toute la communauté.">
                                        <input type="hidden" name="_csrf_token" value="<?= $sopCsrf ?>">
                                        <button type="submit" class="dct-btn is-sm">Mettre en vigueur</button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

        <form method="post" action="<?= dct_e(url('back-office/doctrine')) ?>" class="dct-form">
            <input type="hidden" name="_csrf_token" value="<?= $sopCsrf ?>">
            <h3 class="dct-form__t">Nouvelle fiche SOP</h3>
            <div class="dct-grid">
                <label class="dct-fl dct-fl--wide"><span>Titre</span><input type="text" name="title" maxlength="180" required></label>
                <label class="dct-fl"><span>Type</span>
                    <select name="document_type">
                        <option value="sop">SOP</option>
                        <option value="checklist">Liste de contrôle</option>
                        <option value="report_format">Format de compte rendu</option>
                    </select>
                </label>
                <label class="dct-fl"><span>Version</span><input type="text" name="version_label" value="1.0.0" maxlength="20"></label>
                <label class="dct-fl"><span>Date d’effet</span><input type="date" name="effective_at"></label>
            </div>
            <div class="dct-grid dct-grid--assist writing-assistant-layout">
                <label class="dct-fl"><span>Texte de la fiche</span>
                    <textarea id="doctrine-content-markdown" name="content_markdown" rows="10" required placeholder="Objet&#10;&#10;Procédure&#10;- Étape 1&#10;- Étape 2"></textarea>
                    <small>L’assistant insère une formule à l’endroit du curseur. La fiche est créée en brouillon.</small>
                </label>
                <?php
                $assistantTarget = 'doctrine-content-markdown';
                $assistantInsertMode = 'markdown';
                $assistantLocked = false;
                require base_path('views/partials/writing_assistant.php');
                ?>
            </div>
            <div class="dct-form__f"><button type="submit" class="dct-btn is-dark">Créer la fiche</button></div>
        </form>
    </div>
</details>
