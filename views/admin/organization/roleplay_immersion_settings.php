<?php
declare(strict_types=1);

/** @var array{enabled: bool, optional: bool, stages: list<string>, recruitment_tracks: list<string>, eligibility: array<string,mixed>} $rpConfig */
/** @var array<string, mixed> $rpEligibility */
/** @var string $immersionFormAction */

$cfg = is_array($rpConfig ?? null) ? $rpConfig : [];
$eligibility = is_array($rpEligibility ?? null) ? $rpEligibility : [];
$formAction = (string) ($immersionFormAction ?? url('back-office/roleplay/immersion'));
$stages = is_array($cfg['stages'] ?? null) ? $cfg['stages'] : ['Pré-qualification', 'Tutorat', 'Validation', 'Intégration active'];
$tracks = is_array($cfg['recruitment_tracks'] ?? null) ? $cfg['recruitment_tracks'] : ['Infanterie', 'Support', 'Commandement'];

$err = \App\Core\Session::getFlash('error');
$ok = \App\Core\Session::getFlash('success');
$h = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
?>
<div class="bo-community-settings">

    <?php if ($err): ?>
        <div class="bo-settings-flash bo-settings-flash--err" role="alert"><?= $h((string) $err) ?></div>
    <?php endif; ?>
    <?php if ($ok): ?>
        <div class="bo-settings-flash bo-settings-flash--ok" role="status"><?= $h((string) $ok) ?></div>
    <?php endif; ?>

    <section class="ath-card ath-rise bo-setting-group" id="activation">
        <p class="bo-setting-group__kicker">Dossiers personnel</p>
        <h2 class="bo-setting-group__title">Suivi d’immersion (tutorat &amp; dossier)</h2>
        <p class="bo-setting-row__help" style="margin-top:8px;max-width:720px;">
            Permet d’afficher sur chaque fiche dossier le tuteur, une frise des évènements importants (entretien, visite médicale, rotation…),
            l’avancement recrutement et la filière choisie. Ces réglages ne concernent que votre communauté.
        </p>
    </section>

    <form method="post" action="<?= $h($formAction) ?>" class="bo-settings-grid" style="margin-top:16px;">
        <?= \App\Core\Csrf::field() ?>

        <section class="ath-card ath-rise bo-setting-group" id="activation-options">
            <p class="bo-setting-group__kicker">Activation</p>
            <h2 class="bo-setting-group__title">Affichage sur les fiches</h2>
            <p class="bo-setting-row__help" style="margin-top:8px;max-width:720px;">
                Sans la première case, la section n’apparaît pas sur les fiches ni dans les formulaires dossier.
            </p>
            <div class="bo-setting-group__rows" style="margin-top:13px;">
                <label class="bo-setting-row" style="align-items:flex-start;cursor:pointer;">
                    <input type="checkbox" name="rp_followup_enabled" value="1" style="margin-top:3px;min-height:auto;" <?= !empty($cfg['enabled']) ? 'checked' : '' ?>>
                    <span class="bo-setting-row__copy">
                        <span class="bo-setting-row__label">Afficher le suivi d’immersion</span>
                        <span class="bo-setting-row__help">Sur les fiches et formulaires dossier.</span>
                    </span>
                </label>
                <label class="bo-setting-row" style="align-items:flex-start;cursor:pointer;">
                    <input type="checkbox" name="rp_followup_optional" value="1" style="margin-top:3px;min-height:auto;" <?= !empty($cfg['optional']) ? 'checked' : '' ?>>
                    <span class="bo-setting-row__copy">
                        <span class="bo-setting-row__label">Ne pas bloquer la validation du dossier</span>
                        <span class="bo-setting-row__help">Si les champs de cette section sont encore vides.</span>
                    </span>
                </label>
            </div>
        </section>

        <section class="ath-card ath-rise bo-setting-group" id="listes">
            <p class="bo-setting-group__kicker">Listes</p>
            <h2 class="bo-setting-group__title">Choix proposés aux équipiers</h2>
            <p class="bo-setting-row__help" style="margin-top:8px;max-width:720px;">
                Chaque ligne devient un choix dans les menus (étapes d’avancement et filières de recrutement). Vous pouvez renommer ou réordonner librement.
            </p>
            <div class="bo-setting-group__rows" style="margin-top:13px;">
                <div class="bo-setting-row bo-setting-row--stack">
                    <div class="bo-setting-row__copy">
                        <div class="bo-setting-row__label">Étapes d’avancement</div>
                        <div class="bo-setting-row__help">Une ligne = une étape, de la plus tôt à la plus avancée.</div>
                    </div>
                    <div class="bo-setting-row__control">
                        <textarea id="rp_followup_stages" name="rp_followup_stages" rows="6" class="bo-setting-row__field--wide" style="min-height:140px;resize:vertical;"><?= $h(implode("\n", array_map(static fn ($v) => trim((string) $v), $stages))) ?></textarea>
                    </div>
                </div>
                <div class="bo-setting-row bo-setting-row--stack">
                    <div class="bo-setting-row__copy">
                        <div class="bo-setting-row__label">Filières de recrutement</div>
                        <div class="bo-setting-row__help">Une ligne = une filière affichée aux staffs.</div>
                    </div>
                    <div class="bo-setting-row__control">
                        <textarea id="rp_followup_tracks" name="rp_followup_tracks" rows="6" class="bo-setting-row__field--wide" style="min-height:140px;resize:vertical;"><?= $h(implode("\n", array_map(static fn ($v) => trim((string) $v), $tracks))) ?></textarea>
                    </div>
                </div>
            </div>
        </section>

        <section class="ath-card ath-rise bo-setting-group" id="indicateur">
            <p class="bo-setting-group__kicker">Indicateur</p>
            <h2 class="bo-setting-group__title">« Dossier prêt » sur la fiche</h2>
            <p class="bo-setting-row__help" style="margin-top:8px;max-width:720px;">
                Sur la fiche, un encadré indique si le dossier atteint ces minimums. Les pourcentages reprennent la complétude du dossier et le niveau d’engagement déjà saisis ailleurs dans le portail.
            </p>
            <div class="bo-setting-group__rows" style="margin-top:13px;">
                <div class="bo-setting-row bo-setting-row--stack">
                    <div class="bo-setting-row__copy">
                        <div class="bo-setting-row__label">Complétude du dossier — minimum (%)</div>
                    </div>
                    <div class="bo-setting-row__control">
                        <input type="number" min="0" max="100" id="rp_eligibility_min_completeness" name="rp_eligibility_min_completeness" class="bo-setting-row__field--wide" value="<?= (int) ($eligibility['min_completeness'] ?? 50) ?>">
                    </div>
                </div>
                <div class="bo-setting-row bo-setting-row--stack">
                    <div class="bo-setting-row__copy">
                        <div class="bo-setting-row__label">Engagement / disponibilité — minimum (%)</div>
                    </div>
                    <div class="bo-setting-row__control">
                        <input type="number" min="0" max="100" id="rp_eligibility_min_readiness" name="rp_eligibility_min_readiness" class="bo-setting-row__field--wide" value="<?= (int) ($eligibility['min_readiness'] ?? 30) ?>">
                    </div>
                </div>
            </div>
        </section>

        <section class="ath-card ath-rise bo-setting-group" id="exigences">
            <p class="bo-setting-group__kicker">Exigences</p>
            <h2 class="bo-setting-group__title">Conditions supplémentaires</h2>
            <p class="bo-setting-row__help" style="margin-top:8px;max-width:720px;">
                Cochez les informations qui doivent obligatoirement être présentes sur la fiche pour compter le dossier comme prêt (en plus des deux pourcentages ci-dessus).
            </p>
            <div class="bo-setting-group__rows" style="margin-top:13px;">
                <label class="bo-setting-row" style="align-items:flex-start;cursor:pointer;">
                    <input type="checkbox" name="rp_eligibility_require_unit" value="1" style="margin-top:3px;min-height:auto;" <?= !empty($eligibility['require_unit']) ? 'checked' : '' ?>>
                    <span class="bo-setting-row__copy">
                        <span class="bo-setting-row__label">Affectation à une unité renseignée</span>
                    </span>
                </label>
                <label class="bo-setting-row" style="align-items:flex-start;cursor:pointer;">
                    <input type="checkbox" name="rp_eligibility_require_callsign" value="1" style="margin-top:3px;min-height:auto;" <?= !empty($eligibility['require_callsign']) ? 'checked' : '' ?>>
                    <span class="bo-setting-row__copy">
                        <span class="bo-setting-row__label">Indicatif radio renseigné</span>
                    </span>
                </label>
                <label class="bo-setting-row" style="align-items:flex-start;cursor:pointer;">
                    <input type="checkbox" name="rp_eligibility_require_tutor" value="1" style="margin-top:3px;min-height:auto;" <?= !empty($eligibility['require_tutor']) ? 'checked' : '' ?>>
                    <span class="bo-setting-row__copy">
                        <span class="bo-setting-row__label">Tuteur désigné sur le dossier</span>
                    </span>
                </label>
            </div>
        </section>

        <div class="bo-settings-save">
            <button type="submit" class="ath-btn ath-btn--solid">Enregistrer le suivi d’immersion</button>
        </div>
    </form>

    <p class="bo-settings-note">
        <a href="<?= $h(url('back-office/roleplay-followup')) ?>">Bureau de suivi roleplay</a>
        · <a href="<?= $h(url('personnel')) ?>">Dossiers personnel</a>
    </p>
</div>
