<?php
declare(strict_types=1);

/**
 * Format des références d’avis de vacance — charte ATHENA.
 *
 * L’en-tête de page est rendu par la coque back-office. La page compose une référence à
 * partir de parties activables, et montre le résultat obtenu avec les données réelles de
 * la communauté.
 *
 * @var array<string,mixed> $format
 * @var string $prospectionDocumentRef
 * @var string $previewReference
 * @var int $previewYear
 * @var int $previewSeq
 * @var int $previewLastSeq
 * @var string $previewUnitLabel
 * @var string $previewTenantName
 * @var bool $previewHasUnits
 */

$h = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');

$format = is_array($format ?? null) ? $format : [];
$prospectionDocumentRef = (string) ($prospectionDocumentRef ?? '');
$previewReference = (string) ($previewReference ?? '');
$previewYear = (int) ($previewYear ?? (int) date('Y'));
$previewSeq = (int) ($previewSeq ?? 1);
$previewLastSeq = (int) ($previewLastSeq ?? 0);
$previewUnitLabel = trim((string) ($previewUnitLabel ?? ''));
$previewTenantName = trim((string) ($previewTenantName ?? ''));
$previewHasUnits = (bool) ($previewHasUnits ?? false);
$previewSeqPadded = str_pad((string) max(1, $previewSeq), 4, '0', STR_PAD_LEFT);

/**
 * Parties composant la référence. Chaque partie est activable et peut porter un champ de
 * texte propre — d’où la description en tableau plutôt que six blocs recopiés.
 *
 * @var list<array<string, mixed>> $cards
 */
$cards = [
    [
        'name' => 'include_organization_tag',
        'checked' => !empty($format['include_organization_tag']),
        'title' => 'Sigle de la communauté',
        'desc' => 'Ajoute un court sigle au début de la référence.',
        'extra' => 'organization_tag',
        'extra_label' => 'Sigle personnalisé',
        'extra_hint' => 'Vide : le code communauté ou un abrégé du nom du portail est utilisé.',
        'extra_value' => (string) ($format['organization_tag'] ?? ''),
        'extra_max' => 32,
        'extra_placeholder' => 'ORION, 1RG…',
        'extra_mono' => false,
    ],
    [
        'name' => 'include_ao_segment',
        'checked' => !empty($format['include_ao_segment']),
        'title' => 'Type d’avis',
        'desc' => 'Indique qu’il s’agit d’un appel à candidatures.',
        'extra' => 'ao_segment',
        'extra_label' => 'Texte du type d’avis',
        'extra_hint' => 'Court, en majuscules de préférence.',
        'extra_value' => (string) ($format['ao_segment'] ?? 'AO'),
        'extra_max' => 12,
        'extra_placeholder' => 'AO',
        'extra_mono' => true,
    ],
    [
        'name' => 'include_unit_code',
        'checked' => !empty($format['include_unit_code']),
        'title' => 'Code de l’unité porteuse',
        'desc' => 'Reprend le code enregistré pour l’unité choisie sur chaque avis.',
        'extra' => null,
    ],
    [
        'name' => 'include_unit_name_abbr',
        'checked' => !empty($format['include_unit_name_abbr']),
        'title' => 'Abrégé du nom d’unité',
        'desc' => 'Construit un abrégé à partir du nom complet de l’unité.',
        'extra' => null,
    ],
    [
        'name' => 'include_arm_domain_abbr',
        'checked' => !empty($format['include_arm_domain_abbr']),
        'title' => 'Domaine d’armes',
        'desc' => 'Ajoute l’abrégé du domaine : infanterie → INF, transmissions → TRS.',
        'extra' => null,
    ],
    [
        'name' => 'include_rec_segment',
        'checked' => !empty($format['include_rec_segment']),
        'title' => 'Mention recrutement',
        'desc' => 'Ajoute un libellé fixe marquant qu’il s’agit du recrutement.',
        'extra' => 'rec_segment',
        'extra_label' => 'Texte de la mention',
        'extra_hint' => 'Souvent REC.',
        'extra_value' => (string) ($format['rec_segment'] ?? 'REC'),
        'extra_max' => 16,
        'extra_placeholder' => 'REC',
        'extra_mono' => true,
    ],
];

$activeParts = 0;
foreach ($cards as $card) {
    if (!empty($card['checked'])) {
        $activeParts++;
    }
}

$flashError = \App\Core\Session::getFlash('error');
$flashSuccess = \App\Core\Session::getFlash('success');
?>
<?php if ($flashError): ?>
<p class="ath-flash ath-flash--err" role="alert"><?= $h((string) $flashError) ?></p>
<?php endif; ?>
<?php if ($flashSuccess): ?>
<p class="ath-flash ath-flash--ok" role="status"><?= $h((string) $flashSuccess) ?></p>
<?php endif; ?>

<div class="ath-form__actions" style="border-top:0;margin:0 0 16px;padding-top:0;">
    <a href="<?= $h(url('back-office/recruitment/offers')) ?>" class="ath-btn">Retour au registre</a>
</div>

<div class="ath-panel ath-rise">
    <h2 class="ath-panel__title" style="margin-top:0;">Aperçu de la référence</h2>
    <p class="ath-panel__lead">Construit avec les données réelles de votre communauté, tel qu’enregistré.</p>
    <p class="ath-mono" style="font-size:19px;font-weight:800;margin:12px 0 0;overflow-wrap:anywhere;">
        <?= $h($previewReference !== '' ? $previewReference : '—') ?>
    </p>
    <div class="ath-stat-grid" style="margin-top:14px;">
        <div class="ath-stat">
            <p class="ath-stat__value" style="font-size:13px;font-family:var(--ath-font);"><?= $h($previewTenantName !== '' ? $previewTenantName : '—') ?></p>
            <p class="ath-stat__label">Communauté</p>
        </div>
        <div class="ath-stat">
            <p class="ath-stat__value" style="font-size:13px;font-family:var(--ath-font);"><?= $h($previewUnitLabel !== '' ? $previewUnitLabel : '—') ?></p>
            <p class="ath-stat__label">Unité d’exemple</p>
        </div>
        <div class="ath-stat">
            <p class="ath-stat__value" style="font-size:13px;font-family:var(--ath-font);">Transmissions (TRS)</p>
            <p class="ath-stat__label">Domaine illustré</p>
        </div>
        <div class="ath-stat">
            <p class="ath-stat__value"><?= $h($previewYear . '-' . $previewSeqPadded) ?></p>
            <p class="ath-stat__label">Prochain numéro</p>
        </div>
        <div class="ath-stat">
            <p class="ath-stat__value" style="font-size:13px;font-family:var(--ath-font);">
                <?= $previewLastSeq === 0 ? 'Aucun avis numéroté' : 'Dernier : ' . $h((string) $previewLastSeq) ?>
            </p>
            <p class="ath-stat__label">Numérotation</p>
        </div>
    </div>
    <?php if (!$previewHasUnits && !empty($format['include_unit_code'])): ?>
    <p class="ath-field__help" style="margin-top:12px;color:#8a5a06;">
        Le code d’unité est activé, mais aucune unité n’est encore définie : cette partie
        restera vide sur les références produites.
    </p>
    <?php endif; ?>
</div>

<form method="post" action="<?= $h(url('back-office/recruitment/reference-format')) ?>">
    <?= \App\Core\Csrf::field() ?>

    <div class="ath-form ath-rise">
        <div class="ath-form__head">
            <span class="ath-form__title">Mention en tête des offres</span>
            <span class="ath-form__hint">Texte affiché aux visiteurs, au-dessus de la référence.</span>
        </div>
        <div class="ath-form__grid ath-form__grid--wide">
            <label class="ath-field">
                <span class="ath-field__label">Mention affichée</span>
                <input type="text" name="prospection_document_ref" value="<?= $h($prospectionDocumentRef) ?>" maxlength="180" class="ath-field__input">
                <span class="ath-field__help">Laissez vide pour n’afficher que la référence.</span>
            </label>
        </div>
    </div>

    <div class="ath-form ath-rise">
        <div class="ath-form__head">
            <span class="ath-form__title">Parties de la référence</span>
            <span class="ath-form__hint"><?= $activeParts ?> partie<?= $activeParts > 1 ? 's' : '' ?> active<?= $activeParts > 1 ? 's' : '' ?> sur <?= count($cards) ?></span>
        </div>
        <div class="ath-form__grid">
            <label class="ath-field">
                <span class="ath-field__label">Caractère entre les parties</span>
                <input type="text" name="separator" value="<?= $h((string) ($format['separator'] ?? '/')) ?>" maxlength="3" class="ath-field__input" style="font-family:var(--ath-mono);">
                <span class="ath-field__help">Par exemple / − ·</span>
            </label>
        </div>

        <div style="margin-top:14px;">
            <?php foreach ($cards as $card): ?>
                <?php
                $name = (string) $card['name'];
                $extra = $card['extra'] ?? null;
                ?>
            <div class="ath-meter" style="margin-bottom:10px;padding:11px 12px;">
                <?php // Une case décochée n’est pas transmise : le champ caché garantit un « 0 » explicite. ?>
                <input type="hidden" name="<?= $h($name) ?>" value="0">
                <label class="ath-check" style="border:0;background:transparent;padding:0;">
                    <input type="checkbox" name="<?= $h($name) ?>" value="1"<?= !empty($card['checked']) ? ' checked' : '' ?>>
                    <span>
                        <strong><?= $h((string) $card['title']) ?></strong><br>
                        <span class="ath-field__help"><?= $h((string) $card['desc']) ?></span>
                    </span>
                </label>
                <?php if (is_string($extra) && $extra !== ''): ?>
                <label class="ath-field" style="margin-top:10px;">
                    <span class="ath-field__label"><?= $h((string) ($card['extra_label'] ?? '')) ?></span>
                    <input
                        type="text"
                        name="<?= $h($extra) ?>"
                        value="<?= $h((string) ($card['extra_value'] ?? '')) ?>"
                        maxlength="<?= (int) ($card['extra_max'] ?? 64) ?>"
                        placeholder="<?= $h((string) ($card['extra_placeholder'] ?? '')) ?>"
                        class="ath-field__input"
                        <?= !empty($card['extra_mono']) ? 'style="font-family:var(--ath-mono);"' : '' ?>
                    >
                    <span class="ath-field__help"><?= $h((string) ($card['extra_hint'] ?? '')) ?></span>
                </label>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="ath-form__actions" style="border-top:0;padding-top:0;">
        <button type="submit" class="ath-btn ath-btn--solid">Enregistrer le format</button>
        <a href="<?= $h(url('back-office/recruitment/offers')) ?>" class="ath-btn">Annuler</a>
    </div>
</form>
