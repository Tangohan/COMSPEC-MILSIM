<?php
declare(strict_types=1);
use App\Support\SseIdentityCatalog as C;
use App\Support\SseIdWorkshopCatalog as W;

ob_start();
$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
/** @var array<string,mixed> $doc */
/** @var array<string,mixed> $identity */
/** @var array<string,mixed>|null $country */
/** @var list<array<string,mixed>> $countries */
/** @var array<string,mixed> $countryJs */
/** @var array<string,mixed> $preview */
/** @var bool $editable */
/** @var list<array{id:int,url:string,label:string}> $personPhotos */
/** @var bool $identityLocked */
/** @var list<string> $transitions */
/** @var list<int> $duplicates */
/** @var list<array{id:int,label:string}> $maps */
/** @var array{view:bool,manage:bool,truth:bool} $rights */
$id = (int) $doc['id'];
$isNew = $id === 0;
$dis = $editable ? '' : ' disabled';
$idDis = ($editable && !$identityLocked) ? '' : ' disabled';
$print = (array) ($doc['print'] ?? []);
$transitionLabels = ['validated' => 'Valider', 'issued' => 'Émettre', 'draft' => 'Repasser en brouillon', 'revoked' => 'Révoquer'];
$sseExtraStyles = ($sseExtraStyles ?? '') . '<link rel="stylesheet" href="' . $h(asset_url('assets/css/sse-id-workshop.css')) . '">';
$sseExtraScripts = ($sseExtraScripts ?? '')
    . '<script type="application/json" id="idw-countries">' . json_encode($countryJs, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) . '</script>'
    . '<script src="' . $h(asset_url('assets/js/sse-id-workshop.js')) . '"></script>'
    . '<script src="' . $h(asset_url('assets/js/sse-id-images.js')) . '"></script>';
require __DIR__ . '/_subnav.php';
?>
<div class="breadcrumb">Athena / SSE / <a class="link" href="<?= $h(url('atak/sse/papiers')) ?>">Identités &amp; documents</a> / <a class="link" href="<?= $h(url('atak/sse/papiers/atelier')) ?>">Atelier</a> / <strong><?= $h($isNew ? 'Nouveau papier' : $doc['ref']) ?></strong></div>

<div class="page-heading">
    <div>
        <div class="page-heading-overline">Atelier de documents // <?= $h(C::label(C::WORKFLOWS, (string) $doc['workflow'])) ?></div>
        <h1><?= $h($isNew ? 'Nouveau papier' : $doc['ref'] . ' — ' . $doc['doc_number']) ?></h1>
        <p>Modifiez le papier à gauche : l’aperçu à droite se met à jour en direct, bande de lecture automatique comprise. Tout papier reste un spécimen de jeu, émis par un pays imaginaire ou par un pays réel stylisé (univers réaliste-simulation, gabarit générique).</p>
    </div>
    <div class="page-reference">
        <strong><?= $h(C::label(C::WORKFLOWS, (string) $doc['workflow'])) ?></strong>
        <div class="muted"><?= $doc['workflow'] === 'issued' ? 'Chargé par le serveur de jeu' : 'Pas encore en jeu' ?></div>
    </div>
</div>

<?php if (!empty($doc['duplicated_from_id']) || $duplicates !== []): ?>
    <p class="muted">
        <?php if (!empty($doc['duplicated_from_id'])): ?>Copie de <a class="link" href="<?= $h(url('atak/sse/papiers/atelier/' . (int) $doc['duplicated_from_id'])) ?>"><?= $h(C::documentRef((int) $doc['duplicated_from_id'])) ?></a>. <?php endif; ?>
        <?php if ($duplicates !== []): ?>Dupliqué en <?php foreach ($duplicates as $k => $d): ?><?= $k > 0 ? ', ' : '' ?><a class="link" href="<?= $h(url('atak/sse/papiers/atelier/' . $d)) ?>"><?= $h(C::documentRef($d)) ?></a><?php endforeach; ?>.<?php endif; ?>
    </p>
<?php endif; ?>

<div class="idw-layout">
    <form class="panel idw-form" id="idw-form" method="post" enctype="multipart/form-data"
          action="<?= $h(url('atak/sse/papiers/atelier' . ($isNew ? '' : '/' . $id))) ?>"
          data-mrz-url="<?= $h(url('atak/sse/papiers/atelier/mrz')) ?>">
        <div class="panel-header"><div class="panel-title"><span class="panel-index">01</span> Données du papier</div><div class="panel-meta"><?= $editable ? 'Brouillon modifiable' : 'Lecture seule' ?></div></div>
        <div class="panel-body">
            <?= \App\Core\Csrf::field() ?>
            <?php if ($isNew && (int) ($identity['id'] ?? 0) > 0): ?><input type="hidden" name="identity_id" value="<?= (int) $identity['id'] ?>"><?php endif; ?>
            <?php if ($isNew && isset($identity['seed'])): ?><input type="hidden" name="seed" value="<?= (int) $identity['seed'] ?>"><?php endif; ?>
            <?php if (!$editable && $rights['manage'] && !$isNew): ?>
                <p class="idw-note">Un papier validé, émis ou révoqué ne se modifie plus. Repassez-le en brouillon (s’il est validé) ou dupliquez-le. Ses statuts se changent depuis sa <a class="link" href="<?= $h(url('atak/sse/papiers/documents/' . $id)) ?>">fiche</a>.</p>
            <?php endif; ?>

            <fieldset>
                <legend>Papier</legend>
                <div class="idp-statuses">
                    <div>
                        <label for="idw-country">Pays émetteur</label>
                        <select id="idw-country" name="country_code" data-bind="country"<?= $isNew ? '' : ' disabled' ?>>
                            <?php foreach ($countries as $c): ?>
                                <option value="<?= $h($c['code']) ?>" <?= ($country['code'] ?? '') === $c['code'] ? 'selected' : '' ?>><?= $h($c['name']) ?> (<?= $h($c['code']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (!$isNew): ?><input type="hidden" name="country_code" value="<?= $h($doc['country_code']) ?>"><?php endif; ?>
                    </div>
                    <div>
                        <label for="idw-type">Type</label>
                        <select id="idw-type" name="doc_type" data-bind="doc_type"<?= $isNew ? '' : ' disabled' ?>>
                            <?php foreach (C::DOC_TYPES as $k => $label): ?>
                                <option value="<?= $h($k) ?>" <?= $doc['doc_type'] === $k ? 'selected' : '' ?>><?= $h($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (!$isNew): ?><input type="hidden" name="doc_type" value="<?= $h($doc['doc_type']) ?>"><?php endif; ?>
                    </div>
                    <div>
                        <label for="idw-layout">Modèle d’impression</label>
                        <select id="idw-layout" name="layout" data-bind="layout"<?= $dis ?>>
                            <?php foreach (W::LAYOUTS as $k => $label): ?>
                                <option value="<?= $h($k) ?>" <?= ($doc['layout'] ?? 'modern') === $k ? 'selected' : '' ?>><?= $h($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label for="idw-number">Numéro</label>
                        <input id="idw-number" name="doc_number" data-bind="number" value="<?= $h($doc['doc_number']) ?>" maxlength="20" autocomplete="off"<?= $dis ?>>
                        <p class="idw-note">Format du pays : <code id="idw-format" translate="no"><?= $h($country['number_format'] ?? '') ?></code> (# chiffre, @ lettre)</p>
                    </div>
                    <div>
                        <label for="idw-issued">Délivré le</label>
                        <input id="idw-issued" type="date" name="issued_on" data-bind="issued_on" value="<?= $h($doc['issued_on'] ?? '') ?>"<?= $dis ?>>
                    </div>
                    <div>
                        <label for="idw-expires">Expire le</label>
                        <input id="idw-expires" type="date" name="expires_on" data-bind="expires_on" value="<?= $h($doc['expires_on'] ?? '') ?>"<?= $dis ?>>
                    </div>
                    <div>
                        <label for="idw-authority">Autorité de délivrance</label>
                        <input id="idw-authority" name="authority" data-bind="authority" value="<?= $h($doc['authority'] ?? '') ?>" maxlength="160"<?= $dis ?>>
                    </div>
                </div>
            </fieldset>

            <fieldset>
                <legend>Titulaire</legend>
                <?php if ($identityLocked): ?><p class="idw-note">Cette identité a déjà un autre papier validé ou émis : ses données ne se modifient plus ici.</p><?php endif; ?>
                <?php if (!$isNew && (int) ($identity['id'] ?? 0) > 0): ?><p class="idw-note">Identité <a class="link" href="<?= $h(url('atak/sse/papiers/identites/' . (int) $identity['id'])) ?>"><?= $h($identity['ref'] ?? '') ?></a></p><?php endif; ?>
                <div class="idp-statuses">
                    <div><label for="idw-last">Nom</label><input id="idw-last" name="last_name" data-bind="last_name" value="<?= $h($identity['last_name'] ?? '') ?>" maxlength="120" required<?= $idDis ?>></div>
                    <div><label for="idw-first">Prénoms</label><input id="idw-first" name="first_name" data-bind="first_name" value="<?= $h($identity['first_name'] ?? '') ?>" maxlength="120" required<?= $idDis ?>></div>
                    <div>
                        <label for="idw-sex">Sexe</label>
                        <select id="idw-sex" name="sex" data-bind="sex"<?= $idDis ?>>
                            <?php foreach (C::SEXES as $k => $label): ?><option value="<?= $h($k) ?>" <?= ($identity['sex'] ?? 'X') === $k ? 'selected' : '' ?>><?= $h($label) ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <div><label for="idw-dob">Né(e) le</label><input id="idw-dob" type="date" name="birth_date" data-bind="birth_date" value="<?= $h($identity['birth_date'] ?? '') ?>"<?= $idDis ?>></div>
                    <div><label for="idw-pob">Lieu de naissance</label><input id="idw-pob" name="birth_place" data-bind="birth_place" value="<?= $h($identity['birth_place'] ?? '') ?>" maxlength="120"<?= $idDis ?>></div>
                    <div><label for="idw-nat">Nationalité (code)</label><input id="idw-nat" name="nationality" data-bind="nationality" value="<?= $h($identity['nationality'] ?? '') ?>" maxlength="3"<?= $idDis ?>></div>
                    <div><label for="idw-prof">Profession</label><input id="idw-prof" name="profession" value="<?= $h($identity['profession'] ?? '') ?>" maxlength="120"<?= $idDis ?>></div>
                    <div><label for="idw-alias">Alias</label><input id="idw-alias" name="alias" value="<?= $h($identity['alias'] ?? '') ?>" maxlength="120"<?= $idDis ?>></div>
                    <?php if ($rights['truth']): ?>
                        <div>
                            <label for="idw-kind">Nature de l’identité</label>
                            <select id="idw-kind" name="kind"<?= $idDis ?>>
                                <?php foreach (C::KINDS as $k => $label): ?><option value="<?= $h($k) ?>" <?= ($identity['kind'] ?? 'civil') === $k ? 'selected' : '' ?>><?= $h($label) ?></option><?php endforeach; ?>
                            </select>
                        </div>
                    <?php endif; ?>
                </div>
            </fieldset>

            <fieldset>
                <legend>Images du papier</legend>
                <p class="idw-note">Photo, signature et fond s’affichent dans l’aperçu et l’image exportée, toujours sous le filigrane SIMULATION.</p>
                <div class="idp-statuses">
                    <div style="grid-column: 1 / -1">
                        <?php $photoDis = $dis; $hasPhoto = !empty($identity['photo_path']) && !$identityLocked; require __DIR__ . '/_photo_input.php'; ?>
                    </div>
                    <div>
                        <label for="idw-signature">Signature (image, fond transparent conseillé, <?= (int) \App\Services\Sse\SseIdImageService::maxMb('signature') ?> Mo maximum)</label>
                        <input id="idw-signature" type="file" name="signature" accept="image/png,image/jpeg,image/webp" data-bind-img="signature"<?= $dis ?>>
                        <?php if (!empty($identity['signature_path'])): ?><label class="idw-check"><input type="checkbox" name="signature_remove" value="1"<?= $dis ?>> Retirer la signature importée</label><?php endif; ?>
                    </div>
                    <div>
                        <label for="idw-background">Image de fond du gabarit personnalisé (<?= (int) \App\Services\Sse\SseIdImageService::maxMb('background') ?> Mo maximum)</label>
                        <input id="idw-background" type="file" name="background" accept="image/png,image/jpeg,image/webp" data-bind-img="background"<?= $dis ?>>
                        <p class="idw-note">Texture ou décor de votre univers de fiction : visible avec le modèle « Personnalisé ».</p>
                        <?php if (!empty($doc['background_path'])): ?><label class="idw-check"><input type="checkbox" name="background_remove" value="1"<?= $dis ?>> Retirer l’image de fond</label><?php endif; ?>
                    </div>
                </div>
                <?php if ($editable): ?><?php require __DIR__ . '/_photo_guide.php'; ?><?php endif; ?>
            </fieldset>

            <fieldset>
                <legend>Champs imprimés en plus</legend>
                <div class="idp-statuses">
                    <?php foreach (W::PRINT_FIELDS as $k => [$label, $max]): ?>
                        <div><label for="idw-p-<?= $h($k) ?>"><?= $h($label) ?></label><input id="idw-p-<?= $h($k) ?>" name="print[<?= $h($k) ?>]" data-bind-print="<?= $h($k) ?>" value="<?= $h($print[$k] ?? '') ?>" maxlength="<?= (int) $max ?>"<?= $dis ?>></div>
                    <?php endforeach; ?>
                </div>
            </fieldset>

            <fieldset>
                <legend>Statuts</legend>
                <div class="idp-statuses">
                    <?php foreach (['st_integrity' => ['Intégrité', C::INTEGRITIES], 'st_validity' => ['Validité', C::VALIDITIES], 'st_report' => ['Signalement', C::REPORTS], 'st_attribution' => ['Attribution', C::ATTRIBUTIONS], 'chip_status' => ['Puce', C::CHIPS]] as $name => [$label, $options]): ?>
                        <div>
                            <label for="idw-<?= $h($name) ?>"><?= $h($label) ?></label>
                            <select id="idw-<?= $h($name) ?>" name="<?= $h($name) ?>" data-bind-state="<?= $h($name) ?>"<?= $dis ?>>
                                <?php foreach ($options as $k => $optLabel): ?><option value="<?= $h($k) ?>" <?= (string) ($doc[$name] ?? '') === $k ? 'selected' : '' ?>><?= $h($optLabel) ?></option><?php endforeach; ?>
                            </select>
                        </div>
                    <?php endforeach; ?>
                    <?php if ($rights['truth']): ?>
                        <div>
                            <label for="idw-origin">Origine (vérité de jeu)</label>
                            <select id="idw-origin" name="st_origin"<?= $dis ?>>
                                <?php foreach (C::ORIGINS as $k => $label): ?><option value="<?= $h($k) ?>" <?= ($doc['st_origin'] ?? 'official') === $k ? 'selected' : '' ?>><?= $h($label) ?></option><?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label for="idw-fkind">Falsification</label>
                            <select id="idw-fkind" name="forgery_kind"<?= $dis ?>>
                                <option value="">Aucune</option>
                                <?php foreach (C::FORGERY_KINDS as $k => $label): ?><option value="<?= $h($k) ?>" <?= ($doc['forgery_kind'] ?? '') === $k ? 'selected' : '' ?>><?= $h($label) ?></option><?php endforeach; ?>
                            </select>
                        </div>
                        <div><label for="idw-fq">Qualité du faux (0 à 100)</label><input id="idw-fq" type="number" min="0" max="100" name="forgery_quality" value="<?= (int) ($doc['forgery_quality'] ?? 50) ?>"<?= $dis ?>></div>
                    <?php endif; ?>
                </div>
            </fieldset>

            <fieldset>
                <legend>Portée en jeu</legend>
                <div class="idp-statuses">
                    <div>
                        <label for="idw-map">Carte</label>
                        <select id="idw-map" name="context_id"<?= $dis ?>>
                            <option value="0">Toutes les cartes</option>
                            <?php foreach ($maps as $m): ?><option value="<?= (int) $m['id'] ?>" <?= (int) ($doc['context_id'] ?? 0) === $m['id'] ? 'selected' : '' ?>><?= $h($m['label']) ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <div><label for="idw-op">Opération (code)</label><input id="idw-op" name="op_key" value="<?= $h($doc['op_key'] ?? '') ?>" maxlength="64" placeholder="Toutes les opérations"<?= $dis ?>></div>
                    <div style="grid-column: 1 / -1"><label for="idw-notes">Notes internes</label><textarea id="idw-notes" name="notes" maxlength="4000"<?= $dis ?>><?= $h($doc['notes'] ?? '') ?></textarea></div>
                </div>
            </fieldset>

            <?php if ($editable): ?>
                <div class="idp-actions"><button class="btn" type="submit"><?= $isNew ? 'Créer le brouillon' : 'Enregistrer' ?></button></div>
            <?php endif; ?>
        </div>
    </form>

    <div class="idw-sticky">
        <section class="panel">
            <div class="panel-header"><div class="panel-title"><span class="panel-index">02</span> Aperçu</div><div class="panel-meta" id="idw-mrz-state" aria-live="polite">Bande de lecture calculée en direct</div></div>
            <div class="panel-body">
                <div class="idw-toolbar" role="group" aria-label="Faces du papier">
                    <button type="button" class="btn btn--ghost is-active" data-face-show="">Toutes les faces</button>
                    <button type="button" class="btn btn--ghost" data-face-show="recto" data-kind="card"<?= $doc['doc_type'] === 'passport' ? ' hidden' : '' ?>>Recto</button>
                    <button type="button" class="btn btn--ghost" data-face-show="verso" data-kind="card"<?= $doc['doc_type'] === 'passport' ? ' hidden' : '' ?>>Verso</button>
                    <button type="button" class="btn btn--ghost" data-face-show="couverture" data-kind="passport"<?= $doc['doc_type'] === 'passport' ? '' : ' hidden' ?>>Couverture</button>
                    <button type="button" class="btn btn--ghost" data-face-show="donnees" data-kind="passport"<?= $doc['doc_type'] === 'passport' ? '' : ' hidden' ?>>Page de données</button>
                    <button type="button" class="btn btn--ghost" data-face-show="observations" data-kind="passport"<?= $doc['doc_type'] === 'passport' ? '' : ' hidden' ?>>Observations</button>
                    <button type="button" class="btn" id="idw-export" data-lib="<?= $h(asset_url('assets/vendor/html2canvas-1.4.1/html2canvas.min.js')) ?>" data-name="<?= $h(($doc['ref'] ?: 'papier') . '-simulation') ?>">Exporter en image (PNG)</button>
                </div>
                <?php require __DIR__ . '/_doc_preview.php'; ?>
                <p class="idw-note">L’image exportée garde le filigrane SIMULATION en diagonale et le bandeau « DOCUMENT DE JEU – SANS VALEUR », incrustés dans l’image : elle sert au jeu de rôle uniquement. Photos : uniquement des personnages du jeu.</p>
            </div>
        </section>

        <?php if (!$isNew && ($transitions !== [] || $rights['manage'])): ?>
            <section class="panel" style="margin-top:10px">
                <div class="panel-header"><div class="panel-title"><span class="panel-index">03</span> Cycle du papier</div><div class="panel-meta">Brouillon → validé → émis → révoqué → dupliqué</div></div>
                <div class="panel-body idp-actions">
                    <?php foreach ($transitions as $to): ?>
                        <form method="post" action="<?= $h(url('atak/sse/papiers/atelier/' . $id . '/cycle')) ?>">
                            <?= \App\Core\Csrf::field() ?>
                            <input type="hidden" name="to" value="<?= $h($to) ?>">
                            <?php if ($to === 'revoked'): ?><input name="reason" placeholder="Motif de la révocation" maxlength="255" required><?php endif; ?>
                            <button class="btn<?= $to === 'revoked' ? ' btn--ghost' : '' ?>" type="submit"><?= $h($transitionLabels[$to] ?? $to) ?></button>
                        </form>
                    <?php endforeach; ?>
                    <?php if ($rights['manage']): ?>
                        <form method="post" action="<?= $h(url('atak/sse/papiers/atelier/' . $id . '/dupliquer')) ?>">
                            <?= \App\Core\Csrf::field() ?>
                            <button class="btn btn--ghost" type="submit">Dupliquer (nouveau numéro)</button>
                        </form>
                    <?php endif; ?>
                </div>
            </section>
        <?php endif; ?>
    </div>
</div>
<?php
$sseContent = ob_get_clean();
require dirname(__DIR__) . '/_layout.php';
