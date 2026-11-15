<?php
declare(strict_types=1);
/**
 * Fenêtres modales de la page Personnes (classer, verrouiller, déverrouiller, clore, rouvrir, rattacher, réglage, raccourcis).
 * Partagées avec la fiche d'une personne : le script ouvre celle que demande un bouton « data-pe-act ».
 *
 * @var \App\Services\Sse\SsePersonViewer $viewer
 * @var string $csrf
 * @var array<string, mixed>|null $listing
 * @var list<array<string, mixed>>|null $caseOptions
 */

use App\Support\SsePersonWork as W;

$h ??= static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$levels = W::assignableLevels($viewer->level);
$batchUrl = url('atak/sse/identites/lot');
$returnTo = (string) ($returnTo ?? ($_SERVER['REQUEST_URI'] ?? ''));
$autoLevel = is_array($listing ?? null) ? ($listing['autoLevel'] ?? null) : null;
$field = static function (string $act) use ($h, $csrf, $batchUrl, $returnTo): string {
    return '<input type="hidden" name="_csrf_token" value="' . $h($csrf) . '"><input type="hidden" name="action" value="' . $h($act) . '"><input type="hidden" name="ids" value="" data-pe-ids><input type="hidden" name="return" value="' . $h($returnTo) . '">';
};
$levelOptions = static function (bool $withNone) use ($h, $levels): string {
    $out = $withNone ? '<option value="">Retirer la classification</option>' : '<option value="">Choisir un niveau</option>';
    foreach ($levels as $code) {
        $out .= '<option value="' . $h($code) . '">' . $h(W::classificationLabel($code)) . '</option>';
    }

    return $out;
};
?>
<dialog class="pe-dialog" id="pe-dlg-classify" aria-labelledby="pe-dlg-classify-t">
    <form method="post" action="<?= $h($batchUrl) ?>" data-pe-form="classify">
        <?= $field('classify') ?>
        <h2 id="pe-dlg-classify-t">Classifier</h2>
        <p class="pe-dlg__desc">Choisissez le niveau de la fiche. Vous ne pouvez pas classer au-dessus de votre propre habilitation.</p>
        <p class="pe-dlg__targets" data-pe-targets></p>
        <label>Niveau
            <select name="level" required><?= $levelOptions(true) ?></select>
        </label>
        <label>Motif
            <textarea name="reason" rows="3" maxlength="400" placeholder="Obligatoire pour relever, abaisser ou retirer une classification"></textarea>
        </label>
        <p class="pe-dlg__error" role="alert" hidden></p>
        <div class="pe-dlg__foot"><button type="button" class="pe-btn" data-pe-cancel>Annuler</button><button type="submit" class="pe-btn pe-btn--solid">Classifier</button></div>
    </form>
</dialog>

<dialog class="pe-dialog" id="pe-dlg-lock" aria-labelledby="pe-dlg-lock-t">
    <form method="post" action="<?= $h($batchUrl) ?>" data-pe-form="lock">
        <?= $field('lock') ?>
        <h2 id="pe-dlg-lock-t">Verrouiller</h2>
        <p class="pe-dlg__desc">Une fiche verrouillée n’est lisible et modifiable que par les personnes dont l’habilitation atteint sa classification ; les autres voient « Élément restreint ».</p>
        <p class="pe-dlg__targets" data-pe-targets></p>
        <label>Classification de la fiche
            <select name="level"><?= $levelOptions(false) ?></select>
            <small>Obligatoire si la fiche n’est pas encore classifiée ; sinon sa classification actuelle fixe l’habilitation exigée.</small>
        </label>
        <label>Motif
            <textarea name="reason" rows="3" maxlength="400" required placeholder="Pourquoi verrouiller cette fiche"></textarea>
        </label>
        <label>Fin du verrouillage (facultatif)
            <input type="datetime-local" name="until">
        </label>
        <p class="pe-dlg__error" role="alert" hidden></p>
        <div class="pe-dlg__foot"><button type="button" class="pe-btn" data-pe-cancel>Annuler</button><button type="submit" class="pe-btn pe-btn--solid">Verrouiller</button></div>
    </form>
</dialog>

<dialog class="pe-dialog" id="pe-dlg-unlock" aria-labelledby="pe-dlg-unlock-t">
    <form method="post" action="<?= $h($batchUrl) ?>" data-pe-form="unlock">
        <?= $field('unlock') ?>
        <h2 id="pe-dlg-unlock-t">Déverrouiller</h2>
        <p class="pe-dlg__desc">La fiche redevient lisible selon les droits habituels. Le déverrouillage est journalisé avec son motif.</p>
        <p class="pe-dlg__targets" data-pe-targets></p>
        <label>Motif
            <textarea name="reason" rows="3" maxlength="400" required placeholder="Pourquoi lever le verrou"></textarea>
        </label>
        <p class="pe-dlg__error" role="alert" hidden></p>
        <div class="pe-dlg__foot"><button type="button" class="pe-btn" data-pe-cancel>Annuler</button><button type="submit" class="pe-btn pe-btn--solid">Déverrouiller</button></div>
    </form>
</dialog>

<dialog class="pe-dialog" id="pe-dlg-close" aria-labelledby="pe-dlg-close-t">
    <form method="post" action="<?= $h($batchUrl) ?>" data-pe-form="close">
        <?= $field('close') ?>
        <input type="hidden" name="lift" value="" data-pe-lift>
        <h2 id="pe-dlg-close-t">Clore le dossier</h2>
        <p class="pe-dlg__desc">Le dossier sort de la vue principale mais n’est jamais supprimé : il reste dans l’onglet « Closes » et peut être rouvert.</p>
        <p class="pe-dlg__targets" data-pe-targets></p>
        <p class="pe-dlg__warn" data-pe-wanted-warn role="alert" hidden></p>
        <label>Motif de clôture
            <select name="reason_code" required>
                <option value="">Choisir un motif</option>
                <?php foreach (W::CLOSE_REASONS as $code => $labels): ?>
                    <option value="<?= $h($code) ?>"><?= $h($labels[0]) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>Précision (facultatif)
            <textarea name="note" rows="2" maxlength="400"></textarea>
        </label>
        <p class="pe-dlg__error" role="alert" hidden></p>
        <div class="pe-dlg__foot"><button type="button" class="pe-btn" data-pe-cancel>Annuler</button><button type="submit" class="pe-btn pe-btn--solid" data-pe-close-submit>Clore le dossier</button></div>
    </form>
</dialog>

<dialog class="pe-dialog" id="pe-dlg-reopen" aria-labelledby="pe-dlg-reopen-t">
    <form method="post" action="<?= $h($batchUrl) ?>" data-pe-form="reopen">
        <?= $field('reopen') ?>
        <h2 id="pe-dlg-reopen-t">Rouvrir le dossier</h2>
        <p class="pe-dlg__desc">La fiche revient dans la vue principale. La clôture reste dans l’historique.</p>
        <p class="pe-dlg__targets" data-pe-targets></p>
        <label>Motif
            <textarea name="reason" rows="3" maxlength="400" required placeholder="Pourquoi rouvrir ce dossier"></textarea>
        </label>
        <p class="pe-dlg__error" role="alert" hidden></p>
        <div class="pe-dlg__foot"><button type="button" class="pe-btn" data-pe-cancel>Annuler</button><button type="submit" class="pe-btn pe-btn--solid">Rouvrir</button></div>
    </form>
</dialog>

<dialog class="pe-dialog" id="pe-dlg-link" aria-labelledby="pe-dlg-link-t">
    <form method="post" action="<?= $h($batchUrl) ?>" data-pe-form="link">
        <?= $field('link') ?>
        <h2 id="pe-dlg-link-t">Ajouter à un dossier</h2>
        <p class="pe-dlg__desc">Rattache les personnes sélectionnées à un dossier d’enquête de votre périmètre.</p>
        <p class="pe-dlg__targets" data-pe-targets></p>
        <label>Dossier
            <select name="case_id" required>
                <option value="">Choisir un dossier</option>
                <?php foreach (($caseOptions ?? []) as $c): ?>
                    <option value="<?= (int) ($c['id'] ?? 0) ?>"><?= $h(trim((string) ($c['reference_code'] ?? '') . ' ' . (string) ($c['title'] ?? ''))) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <p class="pe-dlg__error" role="alert" hidden></p>
        <div class="pe-dlg__foot"><button type="button" class="pe-btn" data-pe-cancel>Annuler</button><button type="submit" class="pe-btn pe-btn--solid">Ajouter</button></div>
    </form>
</dialog>

<form method="post" action="<?= $h($batchUrl) ?>" hidden data-pe-export-form>
    <input type="hidden" name="_csrf_token" value="<?= $h($csrf) ?>"><input type="hidden" name="action" value="export"><input type="hidden" name="ids" value="">
</form>

<?php if ($viewer->canManage): ?>
<dialog class="pe-dialog" id="pe-dlg-settings" aria-labelledby="pe-dlg-settings-t">
    <form method="post" action="<?= $h(url('atak/sse/identites/reglages')) ?>" data-pe-form="settings">
        <input type="hidden" name="_csrf_token" value="<?= $h($csrf) ?>"><input type="hidden" name="return" value="<?= $h($returnTo) ?>">
        <h2 id="pe-dlg-settings-t">Verrouillage automatique</h2>
        <p class="pe-dlg__desc">Toute fiche classifiée au-dessus du niveau choisi est verrouillée d’office : seules les habilitations égales ou supérieures la lisent.</p>
        <label>Verrouiller au-delà du niveau
            <select name="level">
                <option value="">Aucune règle</option>
                <?php foreach (W::assignableLevels($viewer->level) as $code): ?>
                    <option value="<?= $h($code) ?>"<?= $autoLevel === $code ? ' selected' : '' ?>><?= $h(W::classificationLabel($code)) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <p class="pe-dlg__error" role="alert" hidden></p>
        <div class="pe-dlg__foot"><button type="button" class="pe-btn" data-pe-cancel>Annuler</button><button type="submit" class="pe-btn pe-btn--solid">Enregistrer</button></div>
    </form>
</dialog>
<?php endif; ?>

<dialog class="pe-dialog pe-dialog--wide" id="pe-dlg-help" aria-labelledby="pe-dlg-help-t">
    <div>
        <h2 id="pe-dlg-help-t">Raccourcis clavier</h2>
        <p class="pe-dlg__desc">Ils agissent sur la ligne repérée ou sur la sélection, et ne se déclenchent jamais pendant une saisie.</p>
        <dl class="pe-keys">
            <div><dt><kbd>j</kbd> <kbd>k</kbd></dt><dd>Ligne suivante, précédente</dd></div>
            <div><dt><kbd>Entrée</kbd></dt><dd>Ouvrir l’aperçu</dd></div>
            <div><dt><kbd>o</kbd></dt><dd>Ouvrir la fiche</dd></div>
            <div><dt><kbd>x</kbd></dt><dd>Cocher la ligne</dd></div>
            <div><dt><kbd>c</kbd></dt><dd>Classifier</dd></div>
            <div><dt><kbd>l</kbd></dt><dd>Verrouiller</dd></div>
            <div><dt><kbd>e</kbd></dt><dd>Clore le dossier</dd></div>
            <div><dt><kbd>a</kbd></dt><dd>Placer ou voir l’avis de recherche</dd></div>
            <div><dt><kbd>s</kbd></dt><dd>Voir la signature</dd></div>
            <div><dt><kbd>/</kbd></dt><dd>Rechercher</dd></div>
            <div><dt><kbd>g</kbd> puis <kbd>p</kbd></dt><dd>Vue tableau</dd></div>
            <div><dt><kbd>g</kbd> puis <kbd>f</kbd></dt><dd>Vue cartes compactes</dd></div>
            <div><dt><kbd>?</kbd></dt><dd>Afficher cette aide</dd></div>
        </dl>
        <label class="pe-switch"><input type="checkbox" data-pe-keys-toggle checked> <span>Activer les raccourcis clavier</span></label>
        <div class="pe-dlg__foot"><button type="button" class="pe-btn pe-btn--solid" data-pe-cancel>Fermer</button></div>
    </div>
</dialog>
