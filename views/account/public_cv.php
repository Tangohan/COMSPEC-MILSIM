<?php
declare(strict_types=1);

$form = is_array($publicCvForm ?? null) ? $publicCvForm : ['enabled' => false, 'slug' => '', 'bio' => '', 'sections' => []];
$errors = is_array($publicCvErrors ?? null) ? $publicCvErrors : [];
$ready = !empty($publicCvReady);
$baseUrl = (string) ($publicCvBaseUrl ?? '');
$liveUrl = $publicCvLiveUrl ?? null;
$sections = is_array($form['sections'] ?? null) ? $form['sections'] : [];
$h = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');

$accountNavKey = 'public_cv';
$accountTitle = 'Ma page publique';
$accountLead = 'Votre C.V. de joueur, consultable sans compte : indicatif, grade, distinctions et parcours. Fermée tant que vous ne l’ouvrez pas.';
if (is_string($liveUrl) && $liveUrl !== '') {
    $accountPublicCvUrl = $liveUrl;
}
require base_path('views/partials/account/shell_open.php');
?>

<?php if (!$ready): ?>
<div class="account-hub__ov-alert" role="status">
    <div><strong>Aucune communauté active.</strong> <span>Rejoignez une communauté pour ouvrir votre page publique : elle présente votre parcours au sein de celle-ci.</span></div>
</div>
<?php endif; ?>

<?php if (is_string($liveUrl) && $liveUrl !== ''): ?>
<div class="account-hub__ov-alert" role="status">
    <div class="account-hub__ov-alert-main">
        <strong>Votre page est en ligne</strong>
        <span style="word-break:break-all"><?= $h($liveUrl) ?></span>
    </div>
    <a href="<?= $h($liveUrl) ?>" target="_blank" rel="noopener" class="account-hub__btn account-hub__btn--ink">Voir ma page publique</a>
</div>
<?php endif; ?>

<form method="post" action="<?= $h(url('account/page-publique')) ?>">
    <?= \App\Core\Csrf::field() ?>

    <section class="account-hub__panel" aria-labelledby="pcv-open-heading">
        <div class="account-hub__panel-head">
            <p class="account-hub__panel-kicker">Visibilité</p>
            <h2 id="pcv-open-heading" class="account-hub__panel-title">Ouvrir ma page</h2>
            <p class="account-hub__panel-desc">Quand la page est ouverte, toute personne qui a le lien peut la consulter, sans compte. Elle n’est pas proposée aux moteurs de recherche. Votre adresse e-mail, votre nom réel, vos informations médicales et votre dossier protégé n’y figurent jamais.</p>
        </div>
        <div class="account-hub__panel-body account-hub__form-grid">
            <input type="hidden" name="enabled" value="0">
            <label class="account-hub__check">
                <input type="checkbox" name="enabled" value="1"<?= !empty($form['enabled']) ? ' checked' : '' ?>>
                <span>
                    <strong>Rendre ma page publique</strong>
                    <span class="account-hub__hint" style="display:block">Décochez à tout moment : la page devient aussitôt introuvable.</span>
                </span>
            </label>

            <div>
                <label class="account-hub__label" for="pcv-slug">Adresse de votre page</label>
                <div style="display:flex;align-items:center;gap:.4rem;flex-wrap:wrap">
                    <span class="account-hub__hint" style="margin:0;word-break:break-all"><?= $h($baseUrl) ?></span>
                    <input type="text" id="pcv-slug" name="slug" value="<?= $h($form['slug'] ?? '') ?>" maxlength="<?= \App\Support\PublicCv\PublicCvSettings::SLUG_MAX ?>" style="max-width:16rem" autocomplete="off" spellcheck="false">
                </div>
                <p class="account-hub__hint">Lettres, chiffres et tirets, par exemple votre indicatif. Les accents et espaces sont convertis automatiquement.</p>
                <?php if (!empty($errors['slug'])): ?>
                <p class="account-hub__field-error"><?= $h($errors['slug']) ?></p>
                <?php endif; ?>
            </div>

            <div>
                <label class="account-hub__label" for="pcv-bio">Présentation courte</label>
                <textarea id="pcv-bio" name="bio" rows="3" maxlength="<?= \App\Support\PublicCv\PublicCvSettings::BIO_MAX ?>" placeholder="Ex. : Pilote de transport depuis 2024, toujours partant pour les opérations de nuit."><?= $h($form['bio'] ?? '') ?></textarea>
                <p class="account-hub__hint"><?= \App\Support\PublicCv\PublicCvSettings::BIO_MAX ?> caractères au plus. N’y mettez pas d’information personnelle.</p>
            </div>
        </div>
    </section>

    <section class="account-hub__panel" aria-labelledby="pcv-sections-heading" style="margin-top:1.25rem">
        <div class="account-hub__panel-head">
            <p class="account-hub__panel-kicker">Contenu</p>
            <h2 id="pcv-sections-heading" class="account-hub__panel-title">Ce que la page montre</h2>
            <p class="account-hub__panel-desc">Votre indicatif et votre portrait sont toujours affichés. Choisissez le reste.</p>
        </div>
        <div class="account-hub__panel-body account-hub__form-grid account-hub__form-grid--2">
            <?php foreach (\App\Support\PublicCv\PublicCvSettings::SECTIONS as $key => [$label, $help]): ?>
            <label class="account-hub__check">
                <input type="checkbox" name="sections[]" value="<?= $h($key) ?>"<?= !empty($sections[$key]) ? ' checked' : '' ?>>
                <span>
                    <strong><?= $h($label) ?></strong>
                    <span class="account-hub__hint" style="display:block"><?= $h($help) ?></span>
                </span>
            </label>
            <?php endforeach; ?>
        </div>
    </section>

    <div style="margin-top:1.25rem">
        <button type="submit" class="account-hub__btn account-hub__btn--ink"<?= $ready ? '' : ' disabled' ?>>Enregistrer</button>
    </div>
</form>

<?php require base_path('views/partials/account/shell_close.php'); ?>
