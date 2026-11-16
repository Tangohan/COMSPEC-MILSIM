<?php
declare(strict_types=1);

/**
 * Fiche membre › fenêtre « État civil du personnage » (POST membres/{id}/etat-civil, EffectifsWorkspaceController::updateMemberCivilStatus).
 *
 * Coque .bo-dialog (back-office-bands.css) ; sections et grille à deux colonnes dans eff-civil-dialog.css. Chaque champ a la
 * même structure (libellé sur une ligne, contrôle de 44 px) pour que les deux colonnes restent alignées.
 * Une erreur d'enregistrement revient dans la fenêtre (flash « eff_civil_error », ouverte par ?ouvrir=etat-civil).
 *
 * @var callable(mixed): string $h
 * @var int $id
 * @var string $csrfToken
 * @var string $memberFirstName
 * @var string $memberLastName
 * @var array<string, mixed> $profile
 * @var array<string, array<string, string>> $civilStatusOptions
 * @var string $callsign
 * @var string $display
 * @var array<string, mixed> $identityRow
 * @var list<array{code: string, name: string}> $civilCountries
 * @var array{message: string, fields: list<string>}|null $civilError
 */
$civilError = is_array($civilError ?? null) ? $civilError : null;
$civilBad = static fn (string $field): bool => $civilError !== null && in_array($field, (array) ($civilError['fields'] ?? []), true);
$civilInvalid = static fn (string $field): string => $civilBad($field) ? ' aria-invalid="true" aria-describedby="eff-dlg-civil-error"' : '';
$civilSelect = static function (string $key, string $label, string $empty) use ($h, $profile, $civilStatusOptions): void {
    $current = trim((string) ($profile[$key] ?? ''));
    $opts = is_array($civilStatusOptions[$key] ?? null) ? $civilStatusOptions[$key] : [];
    if ($current !== '' && !isset($opts[$current])) {
        $opts = [$current => $current] + $opts;
    }
    $fid = 'eff-civil-' . str_replace('_', '-', $key);
    ?>
    <div class="eff-civil__field">
        <label class="eff-civil__label" for="<?= $h($fid) ?>"><?= $h($label) ?></label>
        <select class="eff-civil__control" id="<?= $h($fid) ?>" name="<?= $h($key) ?>">
            <option value=""><?= $h($empty) ?></option>
            <?php foreach ($opts as $optValue => $optText): ?>
                <option value="<?= $h($optValue) ?>" <?= $current === (string) $optValue ? 'selected' : '' ?>><?= $h($optText) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <?php
};
$civilWeight = (int) ($profile['weight_kg'] ?? 0) > 0 ? (string) (int) $profile['weight_kg'] : '';
$civilHeight = (int) ($profile['height_cm'] ?? 0) > 0 ? (string) (int) $profile['height_cm'] : '';
$civilNationality = trim((string) ($profile['nationality'] ?? ''));
?>
<dialog class="bo-dialog eff-civil" id="eff-dlg-civil" aria-labelledby="eff-dlg-civil-title" aria-describedby="eff-dlg-civil-sub"<?= $civilError !== null ? ' data-civil-has-error' : '' ?>>
    <form method="post" action="<?= $h(effectifs_workspace_url('membres/' . $id . '/etat-civil')) ?>" class="bo-dialog__form eff-civil__form" data-civil-form>
        <header class="bo-dialog__head eff-civil__head">
            <div class="bo-dialog__titles">
                <h2 class="bo-dialog__title" id="eff-dlg-civil-title">État civil du personnage</h2>
                <p class="bo-dialog__sub" id="eff-dlg-civil-sub">Le prénom et le nom s’affichent partout : fiche, annuaire, JNET, messages.</p>
            </div>
            <button type="button" class="bo-dialog__close" data-dialog-close aria-label="Fermer">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18"/></svg>
            </button>
        </header>
        <input type="hidden" name="_csrf_token" value="<?= $h($csrfToken) ?>">
        <div class="bo-dialog__body eff-civil__body" data-civil-scroll>
            <?php if ($civilError !== null): ?>
                <div class="eff-civil__alert" id="eff-dlg-civil-error" role="alert" tabindex="-1">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 4 2 20h20L12 4Z"/><path d="M12 10v4M12 17v.5"/></svg>
                    <p><?= $h((string) ($civilError['message'] ?? '')) ?></p>
                </div>
            <?php endif; ?>

            <section class="eff-civil__section" aria-labelledby="eff-civil-s-identite">
                <div class="eff-civil__section-head">
                    <h3 id="eff-civil-s-identite">Identité</h3>
                    <p>Un champ laissé vide ne change rien.</p>
                </div>
                <div class="eff-civil__grid">
                    <div class="eff-civil__field">
                        <label class="eff-civil__label" for="eff-dlg-civil-first-name">Prénom</label>
                        <input class="eff-civil__control" type="text" name="first_name" id="eff-dlg-civil-first-name" maxlength="100" autocomplete="off" value="<?= $h($memberFirstName) ?>" placeholder="Ex. Jean"<?= $civilInvalid('first_name') ?>>
                    </div>
                    <div class="eff-civil__field">
                        <label class="eff-civil__label" for="eff-dlg-civil-last-name">Nom</label>
                        <input class="eff-civil__control" type="text" name="last_name" id="eff-dlg-civil-last-name" maxlength="100" autocomplete="off" value="<?= $h($memberLastName) ?>" placeholder="Ex. Martin"<?= $civilInvalid('last_name') ?>>
                    </div>
                    <div class="eff-civil__wide eff-civil__preview">
                        <p class="eff-civil__preview-title">Nom affiché</p>
                        <?php
                        $mnpFirst = '#eff-dlg-civil-first-name';
                        $mnpLast = '#eff-dlg-civil-last-name';
                        $mnpCallsignInput = '';
                        $mnpCallsign = $callsign;
                        $mnpAccount = $display;
                        $mnpTrigram = (string) ($identityRow['trigram'] ?? '');
                        $mnpClass = 'eff-civil__preview-rows';
                        $mnpLayout = 'rows';
                        require base_path('views/partials/member_name_preview.php');
                        $mnpLayout = '';
                        ?>
                    </div>
                    <div class="eff-civil__field">
                        <label class="eff-civil__label" for="eff-civil-nickname">Surnom</label>
                        <input class="eff-civil__control" type="text" id="eff-civil-nickname" name="nickname_primary" maxlength="100" value="<?= $h($profile['nickname_primary'] ?? '') ?>" placeholder="Ex. Doc">
                    </div>
                    <?php $civilSelect('sex', 'Sexe', 'Non renseigné'); ?>
                    <?php $civilSelect('family_situation', 'Situation familiale', 'Non renseignée'); ?>
                    <div class="eff-civil__field">
                        <label class="eff-civil__label" for="eff-civil-nationality">Nationalité</label>
                        <select class="eff-civil__control" id="eff-civil-nationality" name="nationality">
                            <option value="">Non précisée</option>
                            <?php // Valeur enregistrée hors de la liste des pays (ancienne saisie libre) : gardée telle quelle. ?>
                            <?php if ($civilNationality !== '' && !in_array($civilNationality, array_column($civilCountries, 'code'), true)): ?><option value="<?= $h($civilNationality) ?>" selected><?= $h($civilNationality) ?></option><?php endif; ?>
                            <?php foreach ($civilCountries as $civilCountry): ?>
                                <option value="<?= $h($civilCountry['code']) ?>"<?= (string) $civilCountry['code'] === $civilNationality ? ' selected' : '' ?>><?= $h($civilCountry['name'] . ' (' . $civilCountry['code'] . ')') ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="eff-civil__field eff-civil__wide">
                        <label class="eff-civil__label" for="eff-civil-birth-place">Lieu de naissance</label>
                        <input class="eff-civil__control" type="text" id="eff-civil-birth-place" name="birth_place" maxlength="150" value="<?= $h($profile['birth_place'] ?? '') ?>" placeholder="Ex. Lyon, France">
                    </div>
                </div>
            </section>

            <section class="eff-civil__section" aria-labelledby="eff-civil-s-corps">
                <div class="eff-civil__section-head">
                    <h3 id="eff-civil-s-corps">Corps et santé</h3>
                    <p>Repris sur les papiers et la fiche du personnage.</p>
                </div>
                <div class="eff-civil__grid">
                    <div class="eff-civil__field">
                        <label class="eff-civil__label" for="eff-civil-height">Taille</label>
                        <span class="eff-civil__unit">
                            <input class="eff-civil__control" type="number" inputmode="numeric" id="eff-civil-height" name="height_cm" min="100" max="250" step="1" value="<?= $h($civilHeight) ?>" placeholder="Ex. 180" aria-describedby="eff-civil-height-unit">
                            <span id="eff-civil-height-unit" class="eff-civil__suffix" aria-label="centimètres">cm</span>
                        </span>
                    </div>
                    <div class="eff-civil__field">
                        <label class="eff-civil__label" for="eff-civil-weight">Poids</label>
                        <span class="eff-civil__unit">
                            <input class="eff-civil__control" type="number" inputmode="numeric" id="eff-civil-weight" name="weight_kg" min="20" max="300" step="1" value="<?= $h($civilWeight) ?>" placeholder="Ex. 80" aria-describedby="eff-civil-weight-unit">
                            <span id="eff-civil-weight-unit" class="eff-civil__suffix" aria-label="kilogrammes">kg</span>
                        </span>
                    </div>
                    <?php $civilSelect('blood_type', 'Groupe sanguin', 'Non renseigné'); ?>
                    <div class="eff-civil__field">
                        <label class="eff-civil__label" for="eff-civil-allergies">Allergies</label>
                        <input class="eff-civil__control" type="text" id="eff-civil-allergies" name="allergies" maxlength="500" value="<?= $h($profile['allergies'] ?? '') ?>" placeholder="Ex. Latex, arachide">
                    </div>
                    <div class="eff-civil__field eff-civil__wide">
                        <label class="eff-civil__label" for="eff-civil-drugs">Intolérances médicamenteuses</label>
                        <input class="eff-civil__control" type="text" id="eff-civil-drugs" name="drug_intolerances" maxlength="500" value="<?= $h($profile['drug_intolerances'] ?? '') ?>" placeholder="Ex. Pénicilline, AINS">
                    </div>
                </div>
            </section>

            <section class="eff-civil__section" aria-labelledby="eff-civil-s-langues">
                <div class="eff-civil__section-head">
                    <h3 id="eff-civil-s-langues">Langues et devise</h3>
                    <p>Affichées sur la fiche du personnage.</p>
                </div>
                <div class="eff-civil__grid">
                    <div class="eff-civil__field eff-civil__wide">
                        <label class="eff-civil__label" for="eff-civil-languages">Langues parlées</label>
                        <input class="eff-civil__control" type="text" id="eff-civil-languages" name="languages" maxlength="255" value="<?= $h($profile['languages'] ?? '') ?>" placeholder="Ex. Français, anglais, arabe">
                    </div>
                    <div class="eff-civil__field eff-civil__wide">
                        <label class="eff-civil__label" for="eff-civil-motto">Devise</label>
                        <input class="eff-civil__control" type="text" id="eff-civil-motto" name="motto" maxlength="255" value="<?= $h($profile['motto'] ?? '') ?>" placeholder="Ex. Toujours prêt" aria-describedby="eff-civil-motto-help">
                        <p class="eff-civil__help" id="eff-civil-motto-help">Une phrase courte, 255 caractères au plus. Facultative.</p>
                    </div>
                </div>
            </section>
        </div>
        <footer class="bo-dialog__foot eff-civil__foot">
            <button type="button" class="bo-dialog__btn" data-dialog-close>Annuler</button>
            <button type="submit" class="bo-dialog__btn bo-dialog__btn--primary">Enregistrer</button>
        </footer>
    </form>
</dialog>
