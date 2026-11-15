<?php
declare(strict_types=1);
/**
 * Choix des étiquettes d'une fiche de renseignement, par famille : recherche par code ou libellé, sélection multiple,
 * suggestions selon le type de fiche. Une seule famille est affichée à la fois (pas de mur de cases).
 * Champs envoyés : tags[] (clés) et tag_qualifier[clé] (numéro du besoin, liste REL TO, échéance).
 * Script : public/assets/js/sse-fiches-etiquettes.js (aucun texte) ; styles : sse-fiches-etiquettes.css (.fnp-*).
 *
 * @var array<string, array{key: string, label: string, hint: string, tags: list<array<string, mixed>>}> $tagFamilies
 * @var list<array<string, mixed>> $pickerSelected étiquettes déjà portées ({key, qualifier})
 * @var array<string, list<string>> $tagSuggestions clés suggérées par type de fiche
 * @var string $pickerKind type de fiche courant
 * @var string $pickerId préfixe des identifiants
 * @var int $tagsMax
 * @var string $relToDefault
 * @var string $pickerName nom du champ envoyé (« tags » par défaut ; « suggested », « required » dans Gestion des fiches)
 * @var bool $pickerQualifiers false : aucun complément demandé (choix pour un réglage)
 */
$ph = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$tagFamilies = is_array($tagFamilies ?? null) ? $tagFamilies : [];
$pickerSelected = is_array($pickerSelected ?? null) ? $pickerSelected : [];
$tagSuggestions = is_array($tagSuggestions ?? null) ? $tagSuggestions : [];
$pickerKind = (string) ($pickerKind ?? '');
$pickerId = (string) ($pickerId ?? 'fnp');
$tagsMax = (int) ($tagsMax ?? 16);
$relToDefault = (string) ($relToDefault ?? '');
$pickerName = (string) ($pickerName ?? 'tags');
$pickerQualifiers = (bool) ($pickerQualifiers ?? true);
$pickerIndex = [];
foreach ($tagFamilies as $pickerFamily) {
    foreach ($pickerFamily['tags'] as $pickerTag) {
        $pickerIndex[(string) $pickerTag['key']] = $pickerTag;
    }
}
$pickerQualifierLabels = ['number' => 'N° du besoin', 'text' => 'Précision', 'date' => 'Valable jusqu’au'];
?>
<div class="fnp" data-fnp data-fnp-max="<?= $tagsMax ?>" data-fnp-kind="<?= $ph($pickerKind) ?>" data-fnp-name="<?= $ph($pickerName) ?>"
     data-fnp-suggest="<?= $ph(json_encode($tagSuggestions, JSON_UNESCAPED_UNICODE) ?: '{}') ?>"
     data-fnp-relto="<?= $ph($relToDefault) ?>"
     data-fnp-ph-number="<?= $ph($pickerQualifierLabels['number']) ?>" data-fnp-ph-text="<?= $ph($pickerQualifierLabels['text']) ?>" data-fnp-ph-date="<?= $ph($pickerQualifierLabels['date']) ?>">
    <div class="fnp-top">
        <p class="fnp-none" data-fnp-none<?= $pickerSelected !== [] ? ' hidden' : '' ?>>Aucune étiquette choisie.</p>
        <p class="fnp-count"><span data-fnp-count><?= count($pickerSelected) ?></span> sur <?= $tagsMax ?> au plus</p>
    </div>
    <ul class="fnp-selected" data-fnp-selected aria-label="Étiquettes choisies"><?php foreach ($pickerSelected as $pickerChosen):
        $pickerKey = (string) ($pickerChosen['key'] ?? $pickerChosen['slug'] ?? '');
        $pickerTag = $pickerIndex[$pickerKey] ?? null;
        if ($pickerTag === null) { continue; }
        $pickerQ = $pickerQualifiers ? (string) $pickerTag['qualifier_kind'] : '';
        $pickerValue = (string) ($pickerChosen['qualifier'] ?? '');
        if ($pickerQ === 'date' && $pickerValue !== '') { $pickerValue = str_replace(' ', 'T', substr($pickerValue, 0, 16)); }
        ?><li class="fnp-chip" data-fnp-key="<?= $ph($pickerKey) ?>" style="--fnt: <?= $ph($pickerTag['hex']) ?>">
            <input type="hidden" name="<?= $ph($pickerName) ?>[]" value="<?= $ph($pickerKey) ?>" data-fnp-chip-key>
            <strong data-fnp-chip-code><?= $ph($pickerTag['code']) ?></strong>
            <?php if ($pickerQ !== ''): ?>
                <input type="<?= $pickerQ === 'date' ? 'datetime-local' : 'text' ?>" class="fnp-q fnp-q--<?= $ph($pickerQ) ?>" name="tag_qualifier[<?= $ph($pickerKey) ?>]" value="<?= $ph($pickerValue) ?>" maxlength="80"
                       placeholder="<?= $ph($pickerQualifierLabels[$pickerQ] ?? '') ?>" aria-label="<?= $ph($pickerTag['code']) ?> : <?= $ph($pickerQualifierLabels[$pickerQ] ?? '') ?>" data-fnp-chip-q>
            <?php endif; ?>
            <button type="button" class="fnp-x" data-fnp-remove aria-label="Retirer cette étiquette" title="Retirer cette étiquette">×</button>
        </li><?php endforeach; ?></ul>

    <div class="fnp-suggest" data-fnp-suggest-box hidden>
        <span>Suggestions pour ce type de fiche</span>
        <span class="fnt-set" data-fnp-suggest-list></span>
    </div>

    <div class="fnp-search">
        <label class="sr-only fn-sr" for="<?= $ph($pickerId) ?>-search">Chercher une étiquette</label>
        <input type="search" id="<?= $ph($pickerId) ?>-search" data-fnp-search autocomplete="off" placeholder="Chercher un code ou un libellé : HUMINT, PIR, NOFORN…">
    </div>
    <div class="fnp-fams" role="tablist" aria-label="Familles d’étiquettes">
        <?php $pickerFirst = true; foreach ($tagFamilies as $pickerFamily): ?>
            <button type="button" class="fnp-fam" role="tab" aria-selected="<?= $pickerFirst ? 'true' : 'false' ?>" data-fnp-fam="<?= $ph($pickerFamily['key']) ?>" data-fnp-fam-hint="<?= $ph($pickerFamily['hint']) ?>"><?= $ph($pickerFamily['label']) ?> <span><?= count($pickerFamily['tags']) ?></span></button>
        <?php $pickerFirst = false; endforeach; ?>
    </div>
    <p class="fnp-hint" data-fnp-hint><?= $ph((string) (array_values($tagFamilies)[0]['hint'] ?? '')) ?></p>
    <ul class="fnp-options" data-fnp-options>
        <?php $pickerFirstKey = (string) (array_key_first($tagFamilies) ?? ''); foreach ($tagFamilies as $pickerFamily): foreach ($pickerFamily['tags'] as $pickerTag): ?>
            <li data-fnp-option data-key="<?= $ph($pickerTag['key']) ?>" data-family="<?= $ph($pickerFamily['key']) ?>" data-code="<?= $ph($pickerTag['code']) ?>" data-hex="<?= $ph($pickerTag['hex']) ?>"
                data-qualifier="<?= $ph($pickerQualifiers ? $pickerTag['qualifier_kind'] : '') ?>" data-search="<?= $ph($pickerTag['code'] . ' ' . $pickerTag['label'] . ' ' . $pickerTag['desc'] . ' ' . $pickerFamily['label']) ?>"<?= $pickerFamily['key'] !== $pickerFirstKey ? ' hidden' : '' ?>>
                <button type="button" class="fnp-opt" data-fnp-add aria-pressed="false" style="--fnt: <?= $ph($pickerTag['hex']) ?>">
                    <strong><?= $ph($pickerTag['code']) ?></strong>
                    <span><?= $ph($pickerTag['label']) ?></span>
                    <?php if ($pickerTag['desc'] !== ''): ?><em><?= $ph($pickerTag['desc']) ?></em><?php endif; ?>
                </button>
            </li>
        <?php endforeach; endforeach; ?>
    </ul>
    <p class="fnp-empty" data-fnp-empty hidden>Aucune étiquette ne correspond à cette recherche.</p>
    <template data-fnp-chip><li class="fnp-chip"><input type="hidden" name="<?= $ph($pickerName) ?>[]" value="" data-fnp-chip-key><strong data-fnp-chip-code></strong><input type="text" class="fnp-q" maxlength="80" data-fnp-chip-q><button type="button" class="fnp-x" data-fnp-remove aria-label="Retirer cette étiquette" title="Retirer cette étiquette">×</button></li></template>
</div>
