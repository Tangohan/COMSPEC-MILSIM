<?php
declare(strict_types=1);
/**
 * Fenêtre « Modifier le service » / « Nouveau service » des réglages de services : cinq onglets (Identité, Emblème, Coordonnées,
 * Documents, Avancé) et, à côté, l'aperçu en direct de l'en-tête de document. Sans le droit « Gérer », la fenêtre s'ouvre en
 * lecture : champs figés, aucun bouton d'écriture. Inclus par _wanted_services.php (mêmes variables).
 *
 * @var array<string,mixed> $config
 * @var array<string,array<string,mixed>> $serviceList
 * @var array<string,array{fr:string,en:string}> $serviceFamilies
 * @var array<string,string> $servicePalette
 * @var bool $serviceEmblemReady
 * @var bool $canAdmin
 */
use App\Core\Csrf;
use App\Services\Sse\SseServiceEmblems;
use App\Support\SseUsClassification;
use App\Support\SseWantedCatalog as W;
use App\Support\SseWantedServices;

$typeChoices = W::types($config);
$closeIcon = '<svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true" focusable="false"><path d="M6 6l12 12M18 6L6 18" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"/></svg>';
$printBox = static function (string $key) use ($h): string {
    return '<label class="svc-print"><input type="checkbox" name="print[]" value="' . $h($key) . '" data-f="print"> <span>Sur les PDF</span></label>';
};
$colorNames = ['navy' => 'Marine', 'slate' => 'Ardoise', 'teal' => 'Sarcelle', 'olive' => 'Olive', 'burgundy' => 'Bordeaux', 'amber' => 'Ambre', 'plum' => 'Prune', 'graphite' => 'Graphite'];
$shapeNames = ['hex' => 'Hexagone', 'circle' => 'Disque', 'diamond' => 'Losange', 'bars' => 'Bandes', 'chevron' => 'Écu', 'cross' => 'Croix', 'ring' => 'Anneau'];
$tabs = ['id' => 'Identité', 'emblem' => 'Emblème', 'contact' => 'Coordonnées', 'docs' => 'Documents', 'adv' => 'Avancé'];
?>
<dialog class="svc-modal" id="svc-modal" aria-labelledby="svc-modal-title" data-svc-modal data-create-url="<?= $h(url('atak/sse/avis/services/nouveau')) ?>" data-list-url="<?= $h(url('atak/sse/avis/reglages/services')) ?>">
    <form method="post" action="<?= $h(url('atak/sse/avis/services/nouveau')) ?>" data-svc-form novalidate>
        <?= Csrf::field() ?>
        <input type="hidden" name="print_set" value="1">
        <input type="hidden" name="default_set" value="1">
        <input type="hidden" name="code" value="" data-svc-code>
        <header class="svc-modal__head">
            <div>
                <h2 id="svc-modal-title" data-svc-title tabindex="-1">Modifier le service</h2>
                <p>Ce que vous réglez ici vaut pour votre communauté et pour les documents émis ensuite.</p>
            </div>
            <button type="button" class="ws-iconbtn" data-svc-close aria-label="Fermer"><?= $closeIcon ?></button>
        </header>
        <div class="svc-modal__main">
            <div class="svc-modal__edit">
                <div class="svc-tabs" role="tablist" aria-label="Sections du service" data-svc-tabs>
                    <?php $first = true; foreach ($tabs as $tk => $tl): ?>
                        <button class="svc-tab" type="button" role="tab" id="svc-tab-<?= $h($tk) ?>" aria-controls="svc-pane-<?= $h($tk) ?>" aria-selected="<?= $first ? 'true' : 'false' ?>" data-svc-tab="<?= $h($tk) ?>"><?= $h($tl) ?></button>
                    <?php $first = false; endforeach; ?>
                </div>
                <ul class="ws-errors svc-msg" data-svc-errors role="alert" hidden></ul>
                <fieldset class="svc-fields" data-svc-fields <?= $canAdmin ? '' : 'disabled' ?>>

                <section class="svc-pane" id="svc-pane-id" role="tabpanel" aria-labelledby="svc-tab-id" data-svc-pane="id">
                    <div class="svc-pane__head"><h3>Identité</h3><p>Le nom, le sigle et l’autorité que les documents affichent en tête.</p></div>
                    <div class="ws-field-grid ws-field-grid--2">
                        <div><label for="svcf-fr">Nom français</label><input id="svcf-fr" type="text" name="fr" maxlength="120" required minlength="3" data-f="fr" autocomplete="off"></div>
                        <div><label for="svcf-en">Nom anglais</label><input id="svcf-en" type="text" name="en" maxlength="120" data-f="en" autocomplete="off"></div>
                    </div>
                    <div class="ws-field-grid ws-field-grid--2">
                        <div><label for="svcf-ac">Sigle</label><input id="svcf-ac" type="text" name="ac" maxlength="12" data-f="ac" autocomplete="off"><p class="ws-hint">2 à 12 lettres, chiffres ou tirets. Le sigle d’un organisme réel est refusé.</p></div>
                        <div><label for="svcf-family">Famille</label>
                            <select id="svcf-family" name="family" data-f="family"><?php foreach ($serviceFamilies as $k => $f): ?><option value="<?= $h($k) ?>"><?= $h($f[$lang]) ?></option><?php endforeach; ?></select></div>
                    </div>
                    <div class="ws-field-grid ws-field-grid--2">
                        <div><label for="svcf-afr">Autorité de tutelle (français)</label><input id="svcf-afr" type="text" name="auth_fr" maxlength="160" data-f="auth_fr" autocomplete="off"></div>
                        <div><label for="svcf-aen">Autorité de tutelle (anglais)</label><input id="svcf-aen" type="text" name="auth_en" maxlength="160" data-f="auth_en" autocomplete="off"></div>
                    </div>
                    <div class="ws-field-grid ws-field-grid--2">
                        <div><label for="svcf-dfr">Description (français)</label><textarea id="svcf-dfr" name="desc_fr" rows="3" maxlength="<?= (int) SseWantedServices::DESC_MAX ?>" data-f="desc_fr"></textarea><p class="ws-hint">Une à trois lignes, 300 caractères au plus.</p></div>
                        <div><label for="svcf-den">Description (anglais)</label><textarea id="svcf-den" name="desc_en" rows="3" maxlength="<?= (int) SseWantedServices::DESC_MAX ?>" data-f="desc_en"></textarea></div>
                    </div>
                    <div><label for="svcf-motto">Devise ou mention</label><input id="svcf-motto" type="text" name="motto" maxlength="120" data-f="motto" autocomplete="off"><p class="ws-hint">Facultative : une courte ligne sous le nom.</p></div>
                </section>

                <section class="svc-pane" id="svc-pane-emblem" role="tabpanel" aria-labelledby="svc-tab-emblem" data-svc-pane="emblem" hidden>
                    <div class="svc-pane__head"><h3>Emblème</h3><p>Une image de votre communauté, ou à défaut une forme simple à la couleur du service avec son sigle.</p></div>
                    <div class="svc-upload" data-svc-upload>
                        <div class="svc-upload__now">
                            <span class="svc-upload__mark" data-svc-upload-mark></span>
                            <div class="svc-upload__state">
                                <strong data-svc-upload-state></strong>
                                <span data-svc-upload-by></span>
                            </div>
                        </div>
                        <?php if (!$serviceEmblemReady): ?>
                            <p class="svc-note"><?= $h(SseServiceEmblems::NOT_READY) ?></p>
                        <?php elseif ($canAdmin): ?>
                            <p class="svc-note" data-svc-upload-new hidden>Créez d’abord le service : son emblème se téléverse ensuite depuis cette fenêtre.</p>
                            <div class="svc-upload__form" data-svc-upload-form>
                                <p class="svc-rule"><?= $h(SseServiceEmblems::RULE) ?></p>
                                <div><label for="svcf-file">Image à téléverser</label><input id="svcf-file" type="file" accept="image/png,image/jpeg,image/webp" data-svc-file><p class="ws-hint">PNG, JPEG ou WebP, 1 Mo au plus. L’image est réduite à 512 px et ses métadonnées sont retirées.</p></div>
                                <label class="svc-confirm"><input type="checkbox" value="1" data-svc-confirm> <span><?= $h(SseServiceEmblems::CONFIRM_LABEL) ?></span></label>
                                <p class="svc-inline-msg" data-svc-upload-msg role="status" hidden></p>
                                <div class="svc-upload__acts">
                                    <button class="btn ws-btn" type="button" data-svc-upload-send>Téléverser l’emblème</button>
                                    <span class="svc-ask" data-svc-ask hidden data-svc-upload-remove>
                                        <button class="btn btn--ghost ws-btn" type="button" data-svc-ask-open>Retirer l’emblème</button>
                                        <span class="svc-ask__confirm" hidden><span>Retirer l’image et revenir à l’emblème générique ?</span><button class="btn btn--danger ws-btn" type="button" data-svc-emblem-remove>Retirer</button><button class="btn btn--ghost ws-btn" type="button" data-svc-ask-cancel>Annuler</button></span>
                                    </span>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                    <fieldset class="svc-set"><legend>Forme de l’emblème générique</legend>
                        <div class="svc-shapes"><?php foreach (SseWantedServices::emblems() as $ek): ?><label class="svc-shape"><input type="radio" name="emblem" value="<?= $h($ek) ?>" data-f="emblem"><?= sse_service_emblem_svg($ek, '#5b6672', '', 28) ?><span><?= $h($shapeNames[$ek] ?? ucfirst($ek)) ?></span></label><?php endforeach; ?></div>
                    </fieldset>
                    <fieldset class="svc-set"><legend>Couleur d’en-tête</legend>
                        <div class="ws-swatches"><?php foreach ($servicePalette as $ck => $hex): ?><label class="ws-swatch"><input type="radio" name="color" value="<?= $h($ck) ?>" data-f="color" data-hex="<?= $h($hex) ?>"><i style="background:<?= $h($hex) ?>"></i> <?= $h($colorNames[$ck] ?? ucfirst($ck)) ?></label><?php endforeach; ?></div>
                    </fieldset>
                </section>

                <section class="svc-pane" id="svc-pane-contact" role="tabpanel" aria-labelledby="svc-tab-contact" data-svc-pane="contact" hidden>
                    <div class="svc-pane__head"><h3>Coordonnées</h3><p>Cochez « Sur les PDF » pour imprimer un champ dans le bloc « Service à informer ».</p></div>
                    <div class="svc-crow"><div><label for="svcf-radio">Canal radio ou indicatif</label><input id="svcf-radio" type="text" name="radio" maxlength="80" placeholder="HAMMER ACTUAL · canal TAC 1" data-f="radio" autocomplete="off"></div><?= $printBox('radio') ?></div>
                    <div class="svc-crow"><div><label for="svcf-tel">Téléphone</label><input id="svcf-tel" type="text" name="tel" maxlength="24" placeholder="+1 555 0142" data-f="tel" autocomplete="off"><p class="ws-hint">Une ligne « 555 01xx ».</p></div><?= $printBox('tel') ?></div>
                    <div class="svc-crow"><div><label for="svcf-mail">Courriel</label><input id="svcf-mail" type="text" name="mail" maxlength="80" placeholder="veille@service.invalid" data-f="mail" autocomplete="off"><p class="ws-hint">Domaine en « .invalid », « .example » ou « .test ».</p></div><?= $printBox('mail') ?></div>
                    <div class="svc-crow"><div><label for="svcf-address">Adresse postale</label><textarea id="svcf-address" name="address" rows="3" maxlength="<?= (int) SseWantedServices::ADDRESS_MAX ?>" placeholder="Bâtiment 4, camp Ardent&#10;Route du Littoral" data-f="address"></textarea><p class="ws-hint">Quatre lignes au plus.</p></div><?= $printBox('address') ?></div>
                    <div class="svc-crow"><div><label for="svcf-city">Localité</label><input id="svcf-city" type="text" name="city" maxlength="50" placeholder="Port-Hélène" data-f="city" autocomplete="off"></div><?= $printBox('city') ?></div>
                    <div class="svc-crow"><div><label for="svcf-country">Pays ou théâtre</label><input id="svcf-country" type="text" name="country" maxlength="50" placeholder="Théâtre de la Corne de l’Est" data-f="country" autocomplete="off"></div><?= $printBox('country') ?></div>
                    <div class="svc-crow"><div><label for="svcf-hours">Horaires ou permanence</label><input id="svcf-hours" type="text" name="hours" maxlength="60" placeholder="Permanence 24 h/24" data-f="hours" autocomplete="off"></div><?= $printBox('hours') ?></div>
                    <div class="svc-crow"><div><label for="svcf-web">Adresse web de signalement</label><input id="svcf-web" type="text" name="web" maxlength="160" placeholder="https://signalement.service.invalid" data-f="web" autocomplete="off"><p class="ws-hint">Seulement si elle diffère de la page de signalement du document.</p></div><?= $printBox('web') ?></div>
                </section>

                <section class="svc-pane" id="svc-pane-docs" role="tabpanel" aria-labelledby="svc-tab-docs" data-svc-pane="docs" hidden>
                    <div class="svc-pane__head"><h3>Documents</h3><p>Ce que le service peut émettre, et les valeurs proposées quand on le choisit.</p></div>
                    <div class="ws-field-grid ws-field-grid--2">
                        <div><label for="svcf-max">Classification maximale</label>
                            <select id="svcf-max" name="max" data-f="max"><?php foreach (SseUsClassification::codes() as $cc): ?><option value="<?= $h($cc) ?>"><?= $h(SseUsClassification::label($cc, $lang)) ?></option><?php endforeach; ?></select></div>
                        <div><label for="svcf-class">Classification proposée</label>
                            <select id="svcf-class" name="class" data-f="class"><?php foreach (SseUsClassification::codes() as $cc): ?><option value="<?= $h($cc) ?>"><?= $h(SseUsClassification::label($cc, $lang)) ?></option><?php endforeach; ?></select></div>
                    </div>
                    <div class="ws-field-grid ws-field-grid--2">
                        <div><label for="svcf-thr">Niveau de menace proposé</label>
                            <select id="svcf-thr" name="thr" data-f="thr"><?php foreach (W::THREATS as $tk => $tl): ?><option value="<?= $h($tk) ?>"><?= $h($tl[$lang === 'en' ? 1 : 0]) ?></option><?php endforeach; ?></select></div>
                        <div><label for="svcf-exp">Durée de validité (jours)</label><input id="svcf-exp" type="number" name="exp" min="0" max="3650" data-f="exp"><p class="ws-hint">0 : la durée réglée pour la communauté.</p></div>
                    </div>
                    <fieldset class="svc-set"><legend>Rôle du service</legend>
                        <div class="ws-checks"><?php foreach (SseWantedServices::ROLES as $rk => $rn): ?><label class="ws-check"><input type="checkbox" name="roles[]" value="<?= $h($rk) ?>" data-f="roles"> <?= $h($rn[$lang === 'en' ? 1 : 0]) ?></label><?php endforeach; ?></div></fieldset>
                    <fieldset class="svc-set"><legend>Langues de rédaction</legend>
                        <div class="ws-checks"><label class="ws-check"><input type="checkbox" name="lang[]" value="fr" data-f="lang"> Français</label><label class="ws-check"><input type="checkbox" name="lang[]" value="en" data-f="lang"> English</label></div></fieldset>
                    <fieldset class="svc-set"><legend>Niveaux de menace habituels</legend>
                        <div class="ws-checks"><?php foreach (W::THREATS as $tk => $tl): ?><label class="ws-check"><input type="checkbox" name="threats[]" value="<?= $h($tk) ?>" data-f="threats"> <?= $h($tl[$lang === 'en' ? 1 : 0]) ?></label><?php endforeach; ?></div></fieldset>
                    <fieldset class="svc-set"><legend>Types d’avis habituels</legend>
                        <div class="ws-checks"><?php foreach ($typeChoices as $tk => $tl): ?><label class="ws-check"><input type="checkbox" name="types[]" value="<?= $h($tk) ?>" data-f="types"> <?= $h($tl[$lang === 'en' ? 1 : 0]) ?></label><?php endforeach; ?></div></fieldset>
                    <div class="ws-field-grid ws-field-grid--2">
                        <div><label for="svcf-ifr">Consigne proposée (français)</label><textarea id="svcf-ifr" name="ins_fr" rows="4" maxlength="<?= (int) W::INSTRUCTIONS_MAX ?>" data-f="ins_fr"></textarea></div>
                        <div><label for="svcf-ien">Consigne proposée (anglais)</label><textarea id="svcf-ien" name="ins_en" rows="4" maxlength="<?= (int) W::INSTRUCTIONS_MAX ?>" data-f="ins_en"></textarea></div>
                    </div>
                </section>

                <section class="svc-pane" id="svc-pane-adv" role="tabpanel" aria-labelledby="svc-tab-adv" data-svc-pane="adv" hidden>
                    <div class="svc-pane__head"><h3>Avancé</h3><p>La place du service dans la liste, sa visibilité et le retour aux valeurs livrées.</p></div>
                    <div class="svc-switches">
                        <label class="svc-switch"><input type="checkbox" name="enabled" value="1" data-f="enabled"> <span><strong>Actif</strong><span>Proposé dans les formulaires d’avis et de fiches. Un service masqué reste lisible sur les documents déjà émis.</span></span></label>
                        <label class="svc-switch"><input type="checkbox" name="is_default" value="1" data-f="is_default"> <span><strong>Service par défaut</strong><span>Choisi d’office à l’ouverture d’un formulaire.</span></span></label>
                    </div>
                    <div class="ws-field-grid ws-field-grid--2">
                        <div><label for="svcf-pos">Rang dans la liste</label><input id="svcf-pos" type="number" name="position" min="1" max="<?= count($serviceList) + 1 ?>" data-f="pos"><p class="ws-hint">1 : en tête de la liste des services.</p></div>
                        <div><label for="svcf-codeview">Code du service</label><input id="svcf-codeview" type="text" value="" readonly data-svc-codeview translate="no"><p class="ws-hint">Attribué une fois pour toutes : les documents émis le gardent.</p></div>
                    </div>
                    <?php if ($canAdmin): ?>
                        <div class="svc-danger" data-svc-reset hidden>
                            <div><strong>Rétablir les valeurs livrées</strong><span>Efface vos modifications de ce service du catalogue. L’emblème téléversé est conservé.</span></div>
                            <span class="svc-ask" data-svc-ask>
                                <button class="btn btn--ghost ws-btn" type="button" data-svc-ask-open>Rétablir</button>
                                <span class="svc-ask__confirm" hidden><button class="btn btn--danger ws-btn" type="submit" formnovalidate data-svc-plain data-svc-reset-go>Confirmer le retour aux valeurs livrées</button><button class="btn btn--ghost ws-btn" type="button" data-svc-ask-cancel>Annuler</button></span>
                            </span>
                        </div>
                        <div class="svc-danger" data-svc-delete hidden>
                            <div><strong>Supprimer le service</strong><span>Un service porté par des avis est désactivé, jamais supprimé.</span></div>
                            <span class="svc-ask" data-svc-ask>
                                <button class="btn btn--ghost ws-btn" type="button" data-svc-ask-open>Supprimer</button>
                                <span class="svc-ask__confirm" hidden><button class="btn btn--danger ws-btn" type="submit" name="op" value="delete" formnovalidate data-svc-plain data-svc-delete-go>Confirmer la suppression</button><button class="btn btn--ghost ws-btn" type="button" data-svc-ask-cancel>Annuler</button></span>
                            </span>
                        </div>
                    <?php endif; ?>
                </section>

                </fieldset>
            </div>
            <aside class="svc-modal__preview" aria-label="Aperçu en direct">
                <div class="svc-preview__head">
                    <div><h3>Aperçu en direct</h3><p>L’en-tête et le bloc « Service à informer » de vos documents.</p></div>
                    <div class="svc-seg" role="group" aria-label="Langue de l’aperçu"><button type="button" data-svc-plang="fr" aria-pressed="true">FR</button><button type="button" data-svc-plang="en" aria-pressed="false">EN</button></div>
                </div>
                <div class="svc-doc" data-svc-live></div>
                <button class="btn btn--ghost ws-btn svc-preview__more" type="button" data-svc-more aria-expanded="false" data-label-open="Voir le bloc « Service à informer »" data-label-close="Réduire l’aperçu">Voir le bloc « Service à informer »</button>
            </aside>
        </div>
        <footer class="svc-modal__foot">
            <button class="btn btn--ghost ws-btn" type="button" data-svc-close><?= $canAdmin ? 'Annuler' : 'Fermer' ?></button>
            <?php if ($canAdmin): ?><button class="btn ws-btn" type="submit" data-svc-save>Enregistrer</button><?php endif; ?>
        </footer>
    </form>
</dialog>
