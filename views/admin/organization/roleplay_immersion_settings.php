<?php
declare(strict_types=1);

/** @var array{enabled: bool, optional: bool, stages: list<string>, recruitment_tracks: list<string>, eligibility: array<string,mixed>} $rpConfig */
/** @var array<string, mixed> $rpEligibility */
/** @var string $immersionFormAction */

$cfg = is_array($rpConfig ?? null) ? $rpConfig : [];
$eligibility = is_array($rpEligibility ?? null) ? $rpEligibility : [];
$formAction = (string) ($immersionFormAction ?? url('back-office/roleplay/immersion'));
$enabled = !empty($cfg['enabled']);
$optional = !empty($cfg['optional']);
$stages = is_array($cfg['stages'] ?? null) ? $cfg['stages'] : [];
$tracks = is_array($cfg['recruitment_tracks'] ?? null) ? $cfg['recruitment_tracks'] : [];
if ($stages === []) {
    $stages = ['Pré-qualification', 'Tutorat', 'Validation', 'Intégration active'];
}
if ($tracks === []) {
    $tracks = ['Infanterie', 'Support', 'Commandement'];
}

$minCompleteness = max(0, min(100, (int) ($eligibility['min_completeness'] ?? 50)));
$minReadiness = max(0, min(100, (int) ($eligibility['min_readiness'] ?? 30)));
$requireUnit = !empty($eligibility['require_unit']);
$requireCallsign = !empty($eligibility['require_callsign']);
$requireTutor = !empty($eligibility['require_tutor']);

$readyBits = [
    'la complétude du dossier atteint ' . $minCompleteness . ' %',
    'la disponibilité atteint ' . $minReadiness . ' %',
];
if ($requireUnit) {
    $readyBits[] = 'une unité est renseignée';
}
if ($requireCallsign) {
    $readyBits[] = 'un indicatif radio est renseigné';
}
if ($requireTutor) {
    $readyBits[] = 'un tuteur est désigné';
}
$readySentence = 'Un dossier est marqué prêt lorsque ' . implode(', ', $readyBits) . '.';

$h = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
$followupUrl = url('back-office/roleplay-followup');
$deadlinesUrl = url('back-office/roleplay-followup/echeances');
$personnelUrl = url('personnel');
$atakRoleplayUrl = url('admin/atak/roleplay');
?>
<div class="bo-imm bo-community-settings">

    <section class="bo-imm__hero ath-rise" aria-labelledby="bo-imm-hero-title">
        <div class="bo-imm__hero-copy">
            <span class="bo-imm__eyebrow">Roleplay · Arrivée dans l’unité</span>
            <h2 id="bo-imm-hero-title">Suivre l’arrivée d’un membre, pas le jeu</h2>
            <p>
                Cette page décide <strong>comment votre communauté suit un nouveau membre</strong> :
                étapes du parcours, filière choisie, tuteur, et ce qui rend un dossier « prêt ».
                Une fois enregistré, le staff voit ces choix sur chaque fiche et dans le bureau de suivi.
            </p>
            <p>
                Ce n’est pas ici que l’on règle l’immersion en session : les pannes de liaison et les aides de jeu
                se configurent ailleurs, pour ATAK et Overwatch.
            </p>
            <div class="bo-imm__hero-actions">
                <a href="<?= $h($followupUrl) ?>" class="ath-btn ath-btn--solid">Ouvrir le bureau de suivi</a>
                <a href="<?= $h($deadlinesUrl) ?>" class="ath-btn">Voir les échéances</a>
            </div>
        </div>
        <aside class="bo-imm__orient" aria-label="Ce que vous pouvez faire ici">
            <span class="bo-imm__orient-title">Après enregistrement, vous verrez</span>
            <ul>
                <li><strong>Sur la fiche du membre</strong><span>Étape, filière, tuteur et indicateur « dossier prêt »</span></li>
                <li><strong>Dans le bureau de suivi</strong><span>Tous les dossiers, triés par prochaine échéance</span></li>
                <li><strong>Dans les menus du staff</strong><span>Vos étapes et filières, dans l’ordre saisi</span></li>
            </ul>
        </aside>
    </section>

    <section class="bo-imm__caps" aria-label="Où se trouve chaque capacité">
        <article class="bo-imm__cap bo-imm__cap--here">
            <span class="bo-imm__cap-kicker">Cette page</span>
            <h3>Parcours d’immersion</h3>
            <p>Activer le suivi, nommer les étapes, choisir les filières et fixer ce qui rend un dossier prêt.</p>
        </article>
        <article class="bo-imm__cap">
            <span class="bo-imm__cap-kicker">Suivi quotidien</span>
            <h3>Bureau de suivi</h3>
            <p>Attribuer un tuteur, avancer une étape, noter un bilan et voir qui est déjà suivi.</p>
            <a href="<?= $h($followupUrl) ?>">Ouvrir le bureau</a>
        </article>
        <article class="bo-imm__cap">
            <span class="bo-imm__cap-kicker">Dates</span>
            <h3>Échéances</h3>
            <p>Entretiens, visites médicales et rotations : ce qui est en retard, ce qui arrive.</p>
            <a href="<?= $h($deadlinesUrl) ?>">Voir le calendrier</a>
        </article>
        <article class="bo-imm__cap">
            <span class="bo-imm__cap-kicker">En session</span>
            <h3>ATAK et Overwatch</h3>
            <p>Liaisons dégradées, capteurs et aides de jeu se règlent dans le mode roleplay ATAK, pas sur cette page.</p>
            <a href="<?= $h($atakRoleplayUrl) ?>">Ouvrir le mode roleplay ATAK</a>
        </article>
    </section>

    <div class="bo-imm__status" aria-label="État actuel du suivi">
        <article class="bo-imm__tile <?= $enabled ? 'bo-imm__tile--ok' : 'bo-imm__tile--warn' ?>">
            <span class="bo-imm__tile-kicker">Suivi</span>
            <strong><?= $enabled ? 'Visible' : 'Masqué' ?></strong>
            <span><?= $enabled ? 'Présent sur les fiches et dans le bureau' : 'Invisible tant que vous ne l’affichez pas' ?></span>
        </article>
        <article class="bo-imm__tile">
            <span class="bo-imm__tile-kicker">Étapes</span>
            <strong><?= count($stages) ?></strong>
            <span>proposées au staff, de la plus tôt à la plus avancée</span>
        </article>
        <article class="bo-imm__tile">
            <span class="bo-imm__tile-kicker">Filières</span>
            <strong><?= count($tracks) ?></strong>
            <span>choix de recrutement affichés sur le dossier</span>
        </article>
    </div>

    <?php if (!$enabled): ?>
    <div class="bo-imm__empty" role="status">
        <strong>Le suivi n’apparaît pas encore.</strong>
        Cochez « Afficher le suivi d’immersion » ci-dessous, puis enregistrez. Tant que ce n’est pas fait, les fiches et le bureau restent sans cette section.
    </div>
    <?php endif; ?>

    <form method="post" action="<?= $h($formAction) ?>" class="bo-settings-grid" id="bo-imm-form">
        <?= \App\Core\Csrf::field() ?>

        <section class="ath-card ath-rise bo-setting-group" id="activation-options">
            <p class="bo-setting-group__kicker">Activation</p>
            <h2 class="bo-setting-group__title">Afficher le suivi sur les fiches</h2>
            <p class="bo-setting-row__help" style="margin-top:8px;max-width:720px;">
                Ces deux choix concernent uniquement votre communauté. Ils ne changent rien en jeu, ni sur ATAK.
            </p>
            <div class="bo-setting-group__rows" style="margin-top:13px;">
                <label class="bo-setting-row" style="align-items:flex-start;cursor:pointer;">
                    <input type="checkbox" name="rp_followup_enabled" value="1" style="margin-top:3px;min-height:auto;" <?= $enabled ? 'checked' : '' ?>>
                    <span class="bo-setting-row__copy">
                        <span class="bo-setting-row__label">Afficher le suivi d’immersion</span>
                        <span class="bo-setting-row__help">La section apparaît sur la fiche, à l’édition du dossier, et dans le bureau de suivi.</span>
                    </span>
                </label>
                <label class="bo-setting-row" style="align-items:flex-start;cursor:pointer;">
                    <input type="checkbox" name="rp_followup_optional" value="1" style="margin-top:3px;min-height:auto;" <?= $optional ? 'checked' : '' ?>>
                    <span class="bo-setting-row__copy">
                        <span class="bo-setting-row__label">Ne pas bloquer la validation du dossier</span>
                        <span class="bo-setting-row__help">Le staff peut enregistrer une fiche même si l’étape, la filière ou le tuteur sont encore vides.</span>
                    </span>
                </label>
            </div>
        </section>

        <section class="ath-card ath-rise bo-setting-group bo-setting-group--wide" id="listes">
            <p class="bo-setting-group__kicker">Listes</p>
            <h2 class="bo-setting-group__title">Étapes et filières proposées au staff</h2>
            <p class="bo-setting-row__help" style="margin-top:8px;max-width:720px;">
                Chaque ligne devient un choix dans les menus. Renommez, réordonnez ou ajoutez : l’ordre affiché ici est celui des listes.
            </p>
            <div class="bo-setting-group__rows" style="margin-top:13px;">
                <div class="bo-setting-row bo-setting-row--stack">
                    <div class="bo-setting-row__copy">
                        <div class="bo-setting-row__label">Étapes d’avancement</div>
                        <div class="bo-setting-row__help">De la plus tôt à la plus avancée. Exemple : pré-qualification, puis tutorat, puis validation.</div>
                    </div>
                    <div class="bo-setting-row__control">
                        <div class="bo-imm-list" data-imm-list>
                            <div data-imm-rows></div>
                            <button type="button" class="bo-imm-list__add" data-imm-add>Ajouter une étape</button>
                            <textarea id="rp_followup_stages" name="rp_followup_stages" rows="6" class="bo-imm-list__fallback" data-imm-source data-placeholder="Ex. Tutorat"><?= $h(implode("\n", array_map(static fn ($v) => trim((string) $v), $stages))) ?></textarea>
                            <p class="bo-imm-list__hint">Si les boutons n’apparaissent pas, écrivez une étape par ligne.</p>
                        </div>
                    </div>
                </div>
                <div class="bo-setting-row bo-setting-row--stack">
                    <div class="bo-setting-row__copy">
                        <div class="bo-setting-row__label">Filières de recrutement</div>
                        <div class="bo-setting-row__help">Spécialités proposées au staff, par exemple infanterie, soutien ou commandement.</div>
                    </div>
                    <div class="bo-setting-row__control">
                        <div class="bo-imm-list" data-imm-list>
                            <div data-imm-rows></div>
                            <button type="button" class="bo-imm-list__add" data-imm-add>Ajouter une filière</button>
                            <textarea id="rp_followup_tracks" name="rp_followup_tracks" rows="6" class="bo-imm-list__fallback" data-imm-source data-placeholder="Ex. Infanterie"><?= $h(implode("\n", array_map(static fn ($v) => trim((string) $v), $tracks))) ?></textarea>
                            <p class="bo-imm-list__hint">Si les boutons n’apparaissent pas, écrivez une filière par ligne.</p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section class="ath-card ath-rise bo-setting-group" id="indicateur">
            <p class="bo-setting-group__kicker">Indicateur</p>
            <h2 class="bo-setting-group__title">Quand un dossier est « prêt »</h2>
            <p class="bo-setting-row__help" style="margin-top:8px;max-width:720px;">
                Sur la fiche, un encadré vert ou ambre indique si le dossier atteint ces minimums.
                Les pourcentages reprennent la complétude et la disponibilité déjà saisies ailleurs dans le portail.
            </p>
            <div class="bo-setting-group__rows" style="margin-top:13px;">
                <div class="bo-setting-row bo-setting-row--stack">
                    <div class="bo-setting-row__copy">
                        <div class="bo-setting-row__label">Complétude du dossier — minimum</div>
                        <div class="bo-setting-row__help">Part des informations déjà renseignées sur la fiche, de 0 à 100.</div>
                    </div>
                    <div class="bo-setting-row__control">
                        <input type="number" min="0" max="100" id="rp_eligibility_min_completeness" name="rp_eligibility_min_completeness" class="bo-setting-row__field--wide" value="<?= $minCompleteness ?>" aria-describedby="rp-elig-completeness-help">
                    </div>
                </div>
                <div class="bo-setting-row bo-setting-row--stack">
                    <div class="bo-setting-row__copy">
                        <div class="bo-setting-row__label">Disponibilité — minimum</div>
                        <div class="bo-setting-row__help">Niveau d’engagement déjà saisi sur le dossier, de 0 à 100.</div>
                    </div>
                    <div class="bo-setting-row__control">
                        <input type="number" min="0" max="100" id="rp_eligibility_min_readiness" name="rp_eligibility_min_readiness" class="bo-setting-row__field--wide" value="<?= $minReadiness ?>">
                    </div>
                </div>
            </div>
            <div class="bo-imm__preview">
                <span class="bo-imm__preview-label">Résultat sur la fiche</span>
                <p><?= $h($readySentence) ?></p>
            </div>
        </section>

        <section class="ath-card ath-rise bo-setting-group" id="exigences">
            <p class="bo-setting-group__kicker">Exigences</p>
            <h2 class="bo-setting-group__title">Informations obligatoires pour « prêt »</h2>
            <p class="bo-setting-row__help" style="margin-top:8px;max-width:720px;">
                Cochez ce qui doit être présent en plus des deux minimums. Décocher une case ne l’exige plus.
            </p>
            <div class="bo-setting-group__rows" style="margin-top:13px;">
                <label class="bo-setting-row" style="align-items:flex-start;cursor:pointer;">
                    <input type="checkbox" name="rp_eligibility_require_unit" value="1" style="margin-top:3px;min-height:auto;" <?= $requireUnit ? 'checked' : '' ?>>
                    <span class="bo-setting-row__copy">
                        <span class="bo-setting-row__label">Affectation à une unité renseignée</span>
                        <span class="bo-setting-row__help">Le membre doit déjà être placé dans une unité de l’organigramme.</span>
                    </span>
                </label>
                <label class="bo-setting-row" style="align-items:flex-start;cursor:pointer;">
                    <input type="checkbox" name="rp_eligibility_require_callsign" value="1" style="margin-top:3px;min-height:auto;" <?= $requireCallsign ? 'checked' : '' ?>>
                    <span class="bo-setting-row__copy">
                        <span class="bo-setting-row__label">Indicatif radio renseigné</span>
                        <span class="bo-setting-row__help">Le nom d’appel doit figurer sur le dossier.</span>
                    </span>
                </label>
                <label class="bo-setting-row" style="align-items:flex-start;cursor:pointer;">
                    <input type="checkbox" name="rp_eligibility_require_tutor" value="1" style="margin-top:3px;min-height:auto;" <?= $requireTutor ? 'checked' : '' ?>>
                    <span class="bo-setting-row__copy">
                        <span class="bo-setting-row__label">Tuteur désigné sur le dossier</span>
                        <span class="bo-setting-row__help">Un référent d’arrivée doit être choisi dans le bureau de suivi ou sur la fiche.</span>
                    </span>
                </label>
            </div>
        </section>

        <div class="bo-settings-save">
            <button type="submit" class="ath-btn ath-btn--solid">Enregistrer le suivi d’immersion</button>
        </div>
    </form>

    <p class="bo-settings-note">
        <a href="<?= $h($followupUrl) ?>">Bureau de suivi</a>
        · <a href="<?= $h($deadlinesUrl) ?>">Échéances</a>
        · <a href="<?= $h($personnelUrl) ?>">Dossiers personnel</a>
        · <a href="<?= $h($atakRoleplayUrl) ?>">Mode roleplay ATAK</a>
    </p>
</div>
<script src="<?= $h(asset_url('assets/js/roleplay-immersion-settings.js')) ?>" defer></script>
