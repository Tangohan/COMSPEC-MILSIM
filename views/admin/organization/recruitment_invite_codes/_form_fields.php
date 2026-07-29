<?php
declare(strict_types=1);

/**
 * Champs communs aux formulaires de création et de modification d’un code prioritaire.
 *
 * @var bool $codeFieldEditable
 * @var string $codeLabelValue
 * @var int|null $codeMaxUsesValue
 * @var string|null $codeExpiresAtValue
 * @var bool $codeAutoAcceptValue
 * @var int|null $codeOpeningIdValue
 * @var string $codeSpecialtyValue
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

$expiresAtInput = '';
if ($codeExpiresAtValue !== null && trim((string) $codeExpiresAtValue) !== '') {
    $ts = strtotime((string) $codeExpiresAtValue);
    $expiresAtInput = $ts ? date('Y-m-d\TH:i', $ts) : '';
}
?>
<div class="ath-form__grid">
    <label class="ath-field">
        <span class="ath-field__label">Nom interne *</span>
        <input type="text" name="label" value="<?= $h($codeLabelValue) ?>" maxlength="180" required class="ath-field__input" placeholder="Migration communauté partenaire">
        <span class="ath-field__help">Sert à retrouver le code dans la liste ; invisible pour le candidat.</span>
    </label>
    <?php if ($codeFieldEditable): ?>
    <label class="ath-field">
        <span class="ath-field__label">Code à communiquer</span>
        <input type="text" name="code" maxlength="64" class="ath-field__input" placeholder="MIGRATION2026" pattern="[A-Za-z0-9\-_]{3,64}" title="Lettres, chiffres, tirets ou underscores (3 à 64 caractères)">
        <span class="ath-field__help">Ce que le candidat saisira. Laissez vide pour en générer un automatiquement. Non modifiable après création.</span>
    </label>
    <?php endif; ?>
    <label class="ath-field">
        <span class="ath-field__label">Nombre maximum d’utilisations</span>
        <input type="number" name="max_uses" min="1" max="10000" value="<?= $codeMaxUsesValue !== null ? (int) $codeMaxUsesValue : '' ?>" class="ath-field__input" placeholder="Illimité">
        <span class="ath-field__help">Laissez vide pour un nombre d’usages illimité.</span>
    </label>
    <label class="ath-field">
        <span class="ath-field__label">Date d’expiration</span>
        <input type="datetime-local" name="expires_at" value="<?= $h($expiresAtInput) ?>" class="ath-field__input">
        <span class="ath-field__help">Laissez vide pour un code sans échéance.</span>
    </label>
</div>

<div class="ath-check-grid" style="margin-top:14px;">
    <label class="ath-check">
        <input type="checkbox" name="auto_accept" value="1"<?= $codeAutoAcceptValue ? ' checked' : '' ?>>
        <span>Accepter automatiquement la candidature</span>
    </label>
</div>
<p class="ath-field__help" style="margin-top:6px;">
    Si cette option est cochée, la candidature est acceptée dès que le code est utilisé, sans revue manuelle.
    Sinon, le dossier est simplement accéléré et reste à traiter.
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
        <span class="ath-field__help">La candidature sera associée automatiquement à cette offre publiée.</span>
    </label>
    <label class="ath-field">
        <span class="ath-field__label">Spécialité proposée par défaut</span>
        <input type="text" name="default_specialty" value="<?= $h($codeSpecialtyValue) ?>" maxlength="120" class="ath-field__input" placeholder="Infanterie">
        <span class="ath-field__help">Préremplit la spécialité si le candidat ne l’a pas indiquée.</span>
    </label>
</div>
