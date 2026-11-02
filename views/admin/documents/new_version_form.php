<?php
declare(strict_types=1);

/**
 * Doctrine > Nouvelle version d’un document publié (POST back-office/documents/{id}/nouvelle-version).
 */

require_once base_path('views/admin/documents/partials/doctrine_ui.php');

$document = is_array($document ?? null) ? $document : [];
$doctrine = is_array($doctrine ?? null) ? $doctrine : [];
$csrf = dct_e((string) ($csrf_token ?? ''));
$can = is_array($can ?? null) ? $can : [];
$docId = (int) ($document['id'] ?? 0);
$reference = (string) ($doctrine['reference_code'] ?? '');
$title = (string) ($document['title'] ?? '');
$back = '<a class="dct-btn" href="' . dct_e(url('back-office/doctrine') . '#doc-' . $docId) . '">' . dct_icon('back') . '<span>Retour à la doctrine</span></a>';
$headHtml = dct_head(
    'Nouvelle version · ' . $reference,
    $title !== '' ? $title : 'Nouvelle version',
    'Un document publié ne se modifie pas en silence : chaque changement important crée une version datée, et l’ancienne reste dans l’historique.',
    $back,
    'hub',
    $can
    );
?>
<div class="dct">
    <?= $headHtml ?>
    <?= dct_flashes() ?>

    <form id="doc-version-form" method="post" action="<?= dct_e(url('back-office/documents/' . $docId . '/nouvelle-version')) ?>" class="dct-split">
        <input type="hidden" name="_csrf_token" value="<?= $csrf ?>">
        <div class="dct-form">
            <label class="dct-fl"><span>Ce qui change</span>
                <textarea name="change_summary" rows="2" required maxlength="1000" placeholder="Ex. Nouvelle fréquence radio du réseau logistique, paragraphe 3."></textarea>
                <small>Visible dans l’historique des versions et dans le rappel envoyé aux destinataires.</small>
            </label>
            <label class="dct-fl"><span>Objet <small>facultatif</small></span>
                <textarea name="object" rows="2"><?= dct_e((string) ($doctrine['summary'] ?? '')) ?></textarea>
            </label>
            <label class="dct-fl"><span>Texte de la nouvelle version</span>
                <textarea id="doc-body-html" name="body_html" rows="16"><?= dct_e((string) ($doctrine['body_html'] ?? '')) ?></textarea>
            </label>
            <label class="dct-fl"><span>Phrase de confirmation <small>affichée au moment de l’accusé</small></span>
                <input name="confirmation_text" value="<?= dct_e((string) ($doctrine['confirmation_text'] ?? '')) ?>" placeholder="Je reconnais avoir pris connaissance de cette instruction.">
            </label>
        </div>

        <aside class="dct-aside">
            <fieldset class="dct-card">
                <legend class="dct-card__t" style="padding:0">Lectures déjà faites</legend>
                <div class="dct-choice" style="grid-template-columns:1fr">
                    <label class="dct-opt"><input type="radio" name="ack_policy" value="reset" checked><b>Demander une nouvelle lecture</b><small>Les destinataires sont prévenus et doivent relire. À choisir quand le fond change.</small></label>
                    <label class="dct-opt"><input type="radio" name="ack_policy" value="keep"><b>Garder les lectures</b><small>Correction de forme : personne n’est relancé, les accusés restent valables.</small></label>
                </div>
            </fieldset>
            <div class="dct-card">
                <h2 class="dct-card__t">Numéro de version</h2>
                <label class="dct-check"><input type="checkbox" name="bump_major" value="1"><span>Changement majeur <small>(v1.x devient v2.0)</small></span></label>
                <p class="dct-hint">Sans cette case, la version suivante est mineure (v1.0 devient v1.1).</p>
            </div>
            <button type="submit" class="dct-btn is-dark" style="width:100%"><?= dct_icon('up') ?><span>Publier la version</span></button>
        </aside>
    </form>
</div>
<script src="<?= dct_e(asset_url('assets/js/document_rich_editor.js')) ?>" defer></script>
