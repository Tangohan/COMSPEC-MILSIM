<?php
declare(strict_types=1);

use App\Support\SseIdentityCatalog as C;

/**
 * Fiche membre › Identité & papiers › création ou modification d'un papier.
 *
 * @var array<string, mixed> $identity
 * @var array<string, mixed>|null $doc
 * @var array<string, mixed>|null $country
 * @var list<array<string, mixed>> $countries
 * @var array<string, mixed> $values
 * @var list<string> $mrzPreview
 * @var list<array{id: int, label: string}> $maps
 */
$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$canSeeTruth = !empty($canSeeTruth);
$isEdit = is_array($doc ?? null);
$action = $isEdit ? $base . '/documents/' . (int) $doc['id'] : $base . '/documents';
$regen = (string) ($regenUrl ?? '');
$origin = $isEdit ? (string) $doc['st_origin'] : match ($identity['kind']) {
    'cover' => 'cover',
    'forged' => 'forged',
    'generated' => 'generated',
    default => 'official',
};
?>
<div class="bo-member-situation">
    <p class="bo-member-situation__lead"><a href="<?= $h($backUrl) ?>">← Fiche de <?= $h(\App\Support\MemberName::full($member)) ?></a></p>
    <?php if (($flashError = \App\Core\Session::getFlash('error')) !== null): ?>
        <p class="bo-member-situation__flash bo-member-situation__flash--err"><?= $h($flashError) ?></p>
    <?php endif; ?>
    <p class="idm-sim"><strong>SIMULATION</strong> Papier fictif pour le jeu, émis par un pays imaginaire. Aucune valeur réelle.</p>

    <?php if ($country === null): ?>
        <p class="bo-member-situation__flash bo-member-situation__flash--err">Aucun pays émetteur actif : créez-en un dans le registre des papiers du bureau SSE.</p>
    <?php else: ?>
        <?php if (!$isEdit): ?>
            <form method="get" action="<?= $h($base . '/documents/nouveau') ?>" class="idm-new idm-form">
                <input type="hidden" name="identity_id" value="<?= (int) $identity['id'] ?>">
                <label>Papier
                    <select name="doc_type">
                        <?php foreach (C::DOC_TYPES as $k => $label): ?>
                            <?php if (in_array($k, $country['doc_types'], true) || $k === $docType): ?>
                                <option value="<?= $h($k) ?>" <?= $docType === $k ? 'selected' : '' ?>><?= $h($label) ?></option>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>Pays émetteur
                    <select name="country">
                        <?php foreach ($countries as $c): ?>
                            <option value="<?= $h($c['code']) ?>" <?= $country['code'] === $c['code'] ? 'selected' : '' ?>><?= $h($c['name']) ?> (<?= $h($c['code']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <button type="submit" class="eff-fm-btn is-sm">Changer</button>
            </form>
        <?php endif; ?>

        <form method="post" action="<?= $h($action) ?>" class="idm-form">
            <input type="hidden" name="_csrf_token" value="<?= $h($csrfToken) ?>">
            <input type="hidden" name="identity_id" value="<?= (int) $identity['id'] ?>">
            <input type="hidden" name="doc_type" value="<?= $h($docType) ?>">
            <input type="hidden" name="country_code" value="<?= $h($country['code']) ?>">
            <p><b><?= $h(C::label(C::DOC_TYPES, $docType)) ?></b> · <?= $h($country['name']) ?> (<?= $h($country['code']) ?>) · titulaire <b><?= $h($identityName) ?></b><?= $isEdit ? ' · ' . $h($doc['workflow_label']) : '' ?></p>
            <div class="idm-form__grid">
                <label>Numéro
                    <input type="text" name="doc_number" maxlength="20" value="<?= $h($values['doc_number']) ?>" autocomplete="off" translate="no" style="font-family:monospace">
                    <small>Format du pays : <span translate="no"><?= $h($country['number_format']) ?></span> (« # » chiffre, « @ » lettre). Laissé vide, un numéro libre est tiré au sort.</small>
                </label>
                <label>Délivré le<input type="date" name="issued_on" value="<?= $h($values['issued_on']) ?>"></label>
                <label>Expire le<input type="date" name="expires_on" value="<?= $h($values['expires_on']) ?>"></label>
                <label>Autorité de délivrance<input type="text" name="authority" maxlength="160" value="<?= $h($values['authority']) ?>"></label>
                <label>Carte
                    <select name="context_id">
                        <option value="0">Toutes les cartes</option>
                        <?php foreach ($maps as $m): ?>
                            <option value="<?= (int) $m['id'] ?>" <?= (int) $values['context_id'] === $m['id'] ? 'selected' : '' ?>><?= $h($m['label']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>Opération<input type="text" name="op_key" maxlength="64" value="<?= $h($values['op_key']) ?>" placeholder="Toutes les opérations"></label>
                <?php if ($canSeeTruth): ?>
                    <label>Origine (vérité de jeu)
                        <select name="st_origin">
                            <?php foreach (C::ORIGINS as $k => $label): ?>
                                <option value="<?= $h($k) ?>" <?= $origin === $k ? 'selected' : '' ?>><?= $h($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label>Type de falsification
                        <select name="forgery_kind">
                            <option value="">Aucune</option>
                            <?php foreach (C::FORGERY_KINDS as $k => $label): ?>
                                <option value="<?= $h($k) ?>" <?= $isEdit && $doc['forgery_kind'] === $k ? 'selected' : '' ?>><?= $h($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label>Qualité du faux (0 à 100)<input type="number" name="forgery_quality" min="0" max="100" value="<?= $h($isEdit && $doc['forgery_quality'] !== null ? $doc['forgery_quality'] : 50) ?>"></label>
                <?php endif; ?>
                <label class="idm-form__wide">Notes internes<textarea name="notes" rows="2" maxlength="4000"><?= $h($values['notes']) ?></textarea></label>
            </div>
            <?php if ($mrzPreview !== [] && !$isEdit): ?>
                <p>Bande de lecture calculée avec le numéro proposé :</p>
                <div class="idp-mrz" translate="no"><?= $h(implode("\n", $mrzPreview)) ?></div>
            <?php endif; ?>
            <div class="idm-form__actions">
                <button type="submit" class="eff-btn eff-btn--primary"><?= $isEdit ? 'Enregistrer' : 'Créer le brouillon' ?></button>
                <a class="eff-fm-btn is-sm" href="<?= $h($regen) ?>">Générer un autre numéro</a>
                <a class="eff-fm-btn is-sm" href="<?= $h($backUrl) ?>">Annuler</a>
            </div>
        </form>
    <?php endif; ?>
</div>
