<?php
declare(strict_types=1);

/**
 * Champs communs aux formulaires de création et de modification d’un code d’invitation.
 *
 * Les deux écrans ne diffèrent que par la présence du champ « code » (immuable après
 * création) et par les valeurs préremplies : un seul gabarit évite d’entretenir deux
 * fois les mêmes six champs.
 *
 * @var bool $codeFieldEditable        Affiche le champ « code » (création uniquement)
 * @var string $codeLabelValue         Valeur du libellé
 * @var int|null $codeMaxUsesValue     Quota d’utilisations, null = illimité
 * @var string|null $codeExpiresAtValue Échéance au format base
 * @var bool $codeAutoAcceptValue      Validation automatique
 * @var int|null $codeOpeningIdValue   Offre de recrutement rattachée
 * @var string $codeSpecialtyValue     Spécialité par défaut
 * @var list<array<string, mixed>> $recruitmentOpenings
 */

$h = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');

$codeFieldEditable = (bool) ($codeFieldEditable ?? false);
$codeLabelValue = (string) ($codeLabelValue ?? '');
$codeMaxUsesValue = ($codeMaxUsesValue ?? null) !== null ? (int) $codeMaxUsesValue : null;
$codeExpiresAtValue = $codeExpiresAtValue ?? null;
$codeAutoAcceptValue = (bool) ($codeAutoAcceptValue ?? false);
$codeOpeningIdValue = ($codeOpeningIdValue ?? null) !== null ? (int) $codeOpeningIdValue : null;
$codeSpecialtyValue = (string) ($codeSpecialtyValue ?? '');
$recruitmentOpenings = is_array($recruitmentOpenings ?? null) ? $recruitmentOpenings : [];

// `datetime-local` attend « Y-m-dTH:i » : la valeur de base est convertie, jamais recopiée.
$expiresAtInput = '';
if ($codeExpiresAtValue !== null && trim((string) $codeExpiresAtValue) !== '') {
    $ts = strtotime((string) $codeExpiresAtValue);
    $expiresAtInput = $ts ? date('Y-m-d\TH:i', $ts) : '';
}
?>
<div class="ath-form__grid">
    <label class="ath-field">
        <span class="ath-field__label">Libellé *</span>
        <input type="text" name="label" value="<?= $h($codeLabelValue) ?>" maxlength="180" required class="ath-field__input" placeholder="Migration communauté partenaire">
        <span class="ath-field__help">Sert à retrouver le code dans la liste ; invisible pour le candidat.</span>
    </label>
    <?php if ($codeFieldEditable): ?>
    <label class="ath-field">
        <span class="ath-field__label">Code *</span>
        <input type="text" name="code" maxlength="64" required class="ath-field__input" placeholder="MIGRATION2026">
        <span class="ath-field__help">Ce que le candidat saisira. Non modifiable après création.</span>
    </label>
    <?php endif; ?>
    <label class="ath-field">
        <span class="ath-field__label">Quota d’utilisations</span>
        <input type="number" name="max_uses" min="1" max="10000" value="<?= $codeMaxUsesValue !== null ? (int) $codeMaxUsesValue : '' ?>" class="ath-field__input" placeholder="Illimité">
        <span class="ath-field__help">Laissez vide pour un nombre d’usages illimité.</span>
    </label>
    <label class="ath-field">
        <span class="ath-field__label">Expiration</span>
        <input type="datetime-local" name="expires_at" value="<?= $h($expiresAtInput) ?>" class="ath-field__input">
        <span class="ath-field__help">Laissez vide pour un code sans échéance.</span>
    </label>
</div>

<div class="ath-check-grid" style="margin-top:14px;">
    <label class="ath-check">
        <input type="checkbox" name="auto_accept" value="1"<?= $codeAutoAcceptValue ? ' checked' : '' ?>>
        <span>Validation automatique de la candidature</span>
    </label>
</div>
<p class="ath-field__help" style="margin-top:6px;">
    Avec la validation automatique, la candidature est acceptée dès l’usage du code, sans passer par une revue.
</p>

<div class="ath-form__grid" style="margin-top:14px;">
    <label class="ath-field">
        <span class="ath-field__label">Offre de recrutement rattachée</span>
        <select name="assign_to_opening_id" class="ath-field__select">
            <option value="">Aucune — rattachement manuel</option>
            <?php foreach ($recruitmentOpenings as $opening): ?>
                <?php $openingId = (int) ($opening['id'] ?? 0); ?>
            <option value="<?= $openingId ?>"<?= $codeOpeningIdValue === $openingId ? ' selected' : '' ?>><?= $h((string) ($opening['title'] ?? 'Sans titre')) ?></option>
            <?php endforeach; ?>
        </select>
    </label>
    <label class="ath-field">
        <span class="ath-field__label">Spécialité par défaut</span>
        <input type="text" name="default_specialty" value="<?= $h($codeSpecialtyValue) ?>" maxlength="120" class="ath-field__input" placeholder="Infanterie">
    </label>
</div>
