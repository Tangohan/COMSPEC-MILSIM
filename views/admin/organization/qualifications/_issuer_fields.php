<?php
declare(strict_types=1);

/**
 * Champs du formulaire d'un émetteur (création guidée et modification), en quatre étapes :
 * identité, rattachement, signature du brevet, qualifications délivrées.
 *
 * @var array<string, mixed>|null $iss émetteur modifié (null = création)
 * @var list<array<string, mixed>> $issuers
 * @var list<array{id: int, name: string}> $units
 * @var list<array<string, mixed>> $definitions
 * @var array<int, array<string, mixed>> $linked qualifications liées à l'émetteur (clé = id)
 * @var bool $enriched
 * @var bool $linkReady
 * @var string $fid préfixe des identifiants de champs
 * @var array<string, array{fr: string, en: string}> $countries pays du catalogue
 * @var string $lang
 */
$iss = is_array($iss ?? null) ? $iss : null;
$fid = (string) ($fid ?? 'qi');
$linked = is_array($linked ?? null) ? $linked : [];
$curKind = \App\Repositories\QualificationIssuerRepository::normalizeKind((string) ($iss['issuer_kind'] ?? 'training_center'));
$e = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$kinds = [
    'school' => ['École', 'Un cursus complet, par exemple « 160th SOAR Flight School ».'],
    'training_center' => ['Centre de formation', 'Stages, recyclages, examens : « Centre d’instruction CAS ».'],
    'unit' => ['Unité', 'Une compagnie, un escadron qui qualifie ses propres membres.'],
    'instructor' => ['Instructeur', 'Une personne ou une cellule habilitée : « Instructeur JTAC ».'],
    'external' => ['Organisme externe', 'Une autre communauté ou un partenaire dont vous reconnaissez les brevets.'],
];
$curId = (int) ($iss['id'] ?? 0);
$countries = is_array($countries ?? null) ? $countries : \App\Support\QualificationCatalog\QualificationCatalog::COUNTRIES;
$lang = (string) ($lang ?? 'fr');
$curCountry = (string) ($iss['country_code'] ?? '');
?>
<fieldset class="qis-step">
    <legend><span class="qis-step__n" aria-hidden="true">1</span> Identité</legend>
    <div class="qis-grid">
        <label class="qis-f qis-f--wide" for="<?= $e($fid) ?>-name">
            <span>Nom <em>obligatoire</em></span>
            <input id="<?= $e($fid) ?>-name" name="name" required maxlength="200" value="<?= $e($iss['name'] ?? '') ?>" placeholder="Centre d’instruction CAS">
        </label>
        <label class="qis-f" for="<?= $e($fid) ?>-short">
            <span>Nom court</span>
            <input id="<?= $e($fid) ?>-short" name="short_name" maxlength="80" value="<?= $e($iss['short_name'] ?? '') ?>" placeholder="CI-CAS">
            <small>Affiché sur les cartes et dans les listes.</small>
        </label>
    </div>
    <div class="qis-kinds" role="radiogroup" aria-label="Type d’émetteur">
        <?php foreach ($kinds as $k => [$label, $hint]): ?>
        <label class="qis-kind">
            <input type="radio" name="issuer_kind" value="<?= $e($k) ?>"<?= $k === $curKind ? ' checked' : '' ?>>
            <span><b><?= $e($label) ?></b><small><?= $e($hint) ?></small></span>
        </label>
        <?php endforeach; ?>
    </div>
    <?php if (!empty($enriched)): ?>
    <label class="qis-f" for="<?= $e($fid) ?>-desc">
        <span>Présentation</span>
        <textarea id="<?= $e($fid) ?>-desc" name="description" rows="3" maxlength="2000" placeholder="Ce que l’émetteur forme et valide, ses exigences, son contact…"><?= $e($iss['description'] ?? '') ?></textarea>
    </label>
    <label class="qis-f" for="<?= $e($fid) ?>-desc-en">
        <span>Présentation en anglais</span>
        <textarea id="<?= $e($fid) ?>-desc-en" name="description_en" rows="3" maxlength="2000" placeholder="What the issuer trains and validates…"><?= $e($iss['description_en'] ?? '') ?></textarea>
        <small>Facultatif : affichée aux membres qui lisent le site en anglais.</small>
    </label>
    <div class="qis-f">
        <span id="<?= $e($fid) ?>-country-h">Pays</span>
        <div class="qis-countries" role="radiogroup" aria-labelledby="<?= $e($fid) ?>-country-h">
            <label class="qis-country-opt"><input type="radio" name="country" value=""<?= $curCountry === '' ? ' checked' : '' ?>><span>Aucun</span></label>
            <?php foreach ($countries as $code => $labels): ?>
            <label class="qis-country-opt"><input type="radio" name="country" value="<?= $e($code) ?>"<?= $curCountry === (string) $code ? ' checked' : '' ?>><?= \App\Support\QualificationCatalog\CatalogCountry::flag((string) $code) ?><span><?= $e($labels[$lang] ?? $labels['fr'] ?? $code) ?></span></label>
            <?php endforeach; ?>
        </div>
    </div>
    <div class="qis-grid">
        <label class="qis-f" for="<?= $e($fid) ?>-loc">
            <span>Localisation</span>
            <input id="<?= $e($fid) ?>-loc" name="location" maxlength="200" value="<?= $e($iss['location'] ?? '') ?>" placeholder="Camp de Canjuers, Var">
        </label>
        <label class="qis-f" for="<?= $e($fid) ?>-web">
            <span>Site officiel</span>
            <input id="<?= $e($fid) ?>-web" name="website" type="url" maxlength="300" inputmode="url" value="<?= $e($iss['website_url'] ?? '') ?>" placeholder="https://www.exemple.fr">
        </label>
    </div>
    <label class="qis-f" for="<?= $e($fid) ?>-logo">
        <span>Logo ou insigne</span>
        <input id="<?= $e($fid) ?>-logo" type="file" name="logo" accept="image/png,image/webp,image/svg+xml">
        <small>PNG, WebP ou SVG, 2 Mo au plus. Sans logo, les initiales s’affichent.</small>
    </label>
    <?php endif; ?>
</fieldset>

<fieldset class="qis-step">
    <legend><span class="qis-step__n" aria-hidden="true">2</span> Rattachement</legend>
    <div class="qis-grid">
        <?php if (!empty($enriched)): ?>
        <label class="qis-f" for="<?= $e($fid) ?>-unit">
            <span>Unité de rattachement</span>
            <select id="<?= $e($fid) ?>-unit" name="unit_id">
                <option value="">Aucune</option>
                <?php foreach ($units as $u): ?>
                <option value="<?= (int) $u['id'] ?>"<?= (int) ($iss['unit_id'] ?? 0) === (int) $u['id'] ? ' selected' : '' ?>><?= $e($u['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <small>L’unité dont dépend l’émetteur dans l’ORBAT.</small>
        </label>
        <?php endif; ?>
        <label class="qis-f" for="<?= $e($fid) ?>-parent">
            <span>Dépend d’un autre émetteur</span>
            <select id="<?= $e($fid) ?>-parent" name="parent_issuer_id">
                <option value="">Non</option>
                <?php foreach ($issuers as $p):
                    if ((int) $p['id'] === $curId) {
                        continue;
                    } ?>
                <option value="<?= (int) $p['id'] ?>"<?= (int) ($iss['parent_issuer_id'] ?? 0) === (int) $p['id'] ? ' selected' : '' ?>><?= $e($p['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <small>Par exemple un instructeur rattaché à son école.</small>
        </label>
    </div>
</fieldset>

<?php if (!empty($enriched)): ?>
<fieldset class="qis-step">
    <legend><span class="qis-step__n" aria-hidden="true">3</span> Signature du brevet</legend>
    <p class="qis-help">Le brevet PDF porte le nom de l’émetteur. Indiquez qui signe : sinon, la signature reste « Pour l’autorité émettrice ».</p>
    <div class="qis-grid">
        <label class="qis-f" for="<?= $e($fid) ?>-sig">
            <span>Signataire</span>
            <input id="<?= $e($fid) ?>-sig" name="signatory_name" maxlength="150" value="<?= $e($iss['signatory_name'] ?? '') ?>" placeholder="Cne Martin">
        </label>
        <label class="qis-f" for="<?= $e($fid) ?>-sigt">
            <span>Qualité</span>
            <input id="<?= $e($fid) ?>-sigt" name="signatory_title" maxlength="150" value="<?= $e($iss['signatory_title'] ?? '') ?>" placeholder="Chef du centre d’instruction">
        </label>
    </div>
</fieldset>
<?php endif; ?>

<?php if (!empty($linkReady)): ?>
<fieldset class="qis-step">
    <legend><span class="qis-step__n" aria-hidden="true"><?= !empty($enriched) ? '4' : '3' ?></span> Qualifications délivrées</legend>
    <input type="hidden" name="definitions_form" value="1">
    <p class="qis-help">Cochez les qualifications dont c’est l’émetteur habituel. Il sera proposé d’office à la délivrance à la main, et signera les brevets obtenus par l’Academy ou automatiquement. Une qualification n’a qu’un émetteur habituel : la cocher ici la retire de son émetteur précédent.</p>
    <?php if ($definitions === []): ?>
        <p class="qis-help"><b>Aucune qualification pour l’instant.</b> <a href="<?= $e(url('back-office/referentiels/qualifications/create')) ?>">Créer une qualification</a></p>
    <?php else: ?>
    <div class="qis-defs">
        <?php foreach ($definitions as $d):
            $did = (int) $d['id'];
            $other = (int) ($d['default_issuer_id'] ?? 0);
            $checked = $curId > 0 ? $other === $curId : false;
            $otherName = '';
            if ($other > 0 && $other !== $curId) {
                foreach ($issuers as $p) {
                    if ((int) $p['id'] === $other) {
                        $otherName = (string) $p['name'];
                    }
                }
            } ?>
        <label class="qis-def">
            <input type="checkbox" name="definition_ids[]" value="<?= $did ?>"<?= $checked ? ' checked' : '' ?>>
            <span><b><?= $e($d['name'] ?? '') ?></b><small translate="no"><?= $e($d['code'] ?? '') ?></small><?php if ($otherName !== ''): ?><small class="qis-def__other">Actuellement : <?= $e($otherName) ?></small><?php endif; ?></span>
        </label>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</fieldset>
<?php endif; ?>
