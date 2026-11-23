<?php
declare(strict_types=1);
use App\Support\MilitarySpecialtyLabels as Labels;
$h = static fn (mixed $s): string => htmlspecialchars((string) $s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$member = $specialtyMember ?? null;
$job = $specialtyJob ?? null;
$definition = $specialtyDefinition ?? null;
$context = $member !== null ? ['member' => (int) $member['id']] : ($job !== null ? ['job' => (int) $job['id']] : ($definition !== null ? ['qualification' => (int) $definition['id']] : []));
$targetId = (int) ($member['id'] ?? $job['id'] ?? $definition['id'] ?? 0);
$selectedCode = $specialtySelectedCode ?? null;
$selectedId = (int) ($selectedCode['id'] ?? 0);
$actionUrl = effectifs_workspace_url('specialites');
$csrf = $csrfToken ?? '';
$postFields = static function (string $action) use ($h, $csrf, $targetId): void { ?>
    <input type="hidden" name="_csrf_token" value="<?= $h($csrf) ?>">
    <input type="hidden" name="action" value="<?= $h($action) ?>">
    <input type="hidden" name="target_id" value="<?= $targetId ?>">
<?php };
?>
<div class="eff-specialties">
    <header class="eff-page-head">
        <p class="eff-page-kicker">Personnel · Référentiels nationaux</p>
        <h1 class="eff-page-title">Spécialités militaires</h1>
        <p class="eff-page-lead">Identifiez un métier dans son pays et son armée, puis précisez le niveau détenu ou l’objectif de formation.</p>
    </header>
    <?php foreach (['success', 'error'] as $flashKey): $flash = \App\Core\Session::getFlash($flashKey); if ($flash): ?>
        <p class="eff-specialties__notice" role="status"><?= $h($flash) ?></p>
    <?php endif; endforeach; ?>
    <details class="eff-specialties__card" open>
        <summary><strong>Comprendre les codes et les niveaux</strong></summary>
        <dl class="eff-specialties__definitions">
            <div><dt>Spécialité</dt><dd>Un métier identifié dans une armée : AFSC <code>1Z2X1</code> (Combat Control), MOS <code>15T</code> (maintenance UH-60), spécialité Marine <code>FUSIL</code>.</dd></div>
            <div><dt>Niveau de compétence</dt><dd>Le degré de maîtrise dans ce métier. Pour un AFSC enlisted, le quatrième caractère indique le niveau : 1 Helper, 3 Apprentice, 5 Journeyman, 7 Craftsman, 9 Superintendent. Le code 0 Chief Enlisted Manager a un sens propre. Ces valeurs ne se transposent pas aux autres armées.</dd></div>
            <div><dt>Qualification ou brevet</dt><dd>Un acquis attesté, parfois à renouveler : JTAC, BAT, BM1 ou BM2. Les formations de l’Academy alimentent les qualifications ; elles ne confèrent pas automatiquement un code militaire.</dd></div>
            <div><dt>Grade, emploi et affectation</dt><dd>Le grade situe la personne dans la hiérarchie ; l’emploi décrit sa responsabilité ; l’affectation indique son unité et son poste. Par exemple, le 160th SOAR et le CPA 10 sont des unités. Le code <code>18X</code> décrit une candidature, pas la réussite du parcours Special Forces.</dd></div>
        </dl>
        <p>En France, les indices AAE et les sigles de spécialité Marine suivent leurs propres règles. Les anciens BSAT/BSTAT Terre sont à dater : le cursus rénové utilise BM1 à BM4. Le Trade Group 8 RAF est attesté historiquement. Les codes OTAN OF/OR désignent des grades ; aucun code métier OTAN universel n’est supposé ici.</p>
    </details>
    <?php if (empty($specialtyReady)): ?>
        <p class="eff-specialties__notice" role="status">La consultation structurée et l’enregistrement des spécialités seront disponibles après la mise à jour du site. Les codes déjà saisis dans les emplois restent conservés.</p>
    <?php else: ?>
    <?php if ($member !== null): ?>
        <section class="eff-specialties__card" aria-labelledby="sp-member-title">
            <h2 id="sp-member-title">Dossier de <?= $h($member['display_name'] ?? $member['callsign'] ?? 'ce membre') ?></h2>
            <p><a href="<?= $h(effectifs_workspace_url('membres/' . $targetId)) ?>">Revenir à la fiche du membre</a></p>
            <p>« Déclarée » conserve une information à vérifier. « Confirmée » enregistre une validation RH dans la simulation. La nature de la référence reste déterminante : confirmer une option 18X ne transforme pas le candidat en opérateur qualifié.</p>
            <?php if (($specialtyMemberRows ?? []) === []): ?><p>Aucune spécialité enregistrée pour ce membre.</p><?php endif; ?>
            <ul class="eff-specialties__list">
                <?php foreach (($specialtyMemberRows ?? []) as $row): ?>
                <li><strong><?= $h(Labels::title($row)) ?></strong> · <?= $h($row['country_name_fr'] ?? $row['country_code'] ?? '') ?> · <?= $h($row['service_name'] ?? '') ?>
                    <p><?= $h(Labels::status((string) $row['status'])) ?><?php if (($row['level_code'] ?? '') !== '' && $row['level_code'] !== null): ?> · Niveau <?= $h($row['level_code']) ?> : <?= $h($row['level_label_fr'] ?? '') ?><?php else: ?> · Niveau non renseigné<?php endif; ?></p>
                    <?php if (!empty($specialtyCanMember) && $row['status'] !== 'archived'): ?>
                        <a href="<?= $h($actionUrl . '?' . http_build_query($context + ['code' => (int) $row['specialty_code_id']])) ?>#sp-entry">Modifier le niveau ou la validation</a>
                        <form method="post" action="<?= $h($actionUrl) ?>"><?php $postFields('archive'); ?><input type="hidden" name="assignment_id" value="<?= (int) $row['id'] ?>"><button class="eff-btn" type="submit">Archiver cette spécialité</button></form>
                    <?php endif; ?>
                </li>
                <?php endforeach; ?>
            </ul>
        </section>
    <?php elseif ($job !== null): ?>
        <section class="eff-specialties__card">
            <h2>Spécialités associées à l’emploi « <?= $h($job['name']) ?> »</h2>
            <p><a href="<?= $h(effectifs_workspace_url('fonctions') . '?emploi=' . $targetId) ?>">Revenir à l’emploi</a></p>
            <p>Ces références décrivent les métiers associés à l’emploi. Elles n’attribuent aucune spécialité à ses titulaires. Les prérequis et les qualifications se règlent séparément.</p>
            <?php if (!empty($specialtyCanJob)): ?><form method="post" action="<?= $h($actionUrl) ?>"><?php $postFields('job'); ?>
                <fieldset><legend>Références de cet emploi</legend>
                <?php foreach (($specialtyAllCodes ?? []) as $code): ?>
                    <label class="eff-specialties__check"><input type="checkbox" name="specialty_code_ids[]" value="<?= (int) $code['id'] ?>" <?= in_array((int) $code['id'], $specialtyJobIds ?? [], true) ? 'checked' : '' ?>> <?= $h($code['country_code'] . ' · ' . $code['service_name'] . ' · ' . Labels::title($code)) ?><?= !empty($code['archived_at']) || !empty($code['system_archived_at']) ? ' — Référence archivée conservée ; décochez pour la retirer' : '' ?></label>
                <?php endforeach; ?></fieldset><button class="eff-btn eff-btn--primary" type="submit">Enregistrer les références de l’emploi</button>
            </form><?php else: ?><p>La modification des emplois est réservée aux responsables habilités.</p><?php endif; ?>
        </section>
    <?php elseif ($definition !== null): ?>
        <section class="eff-specialties__card">
            <h2>Objectifs de la qualification « <?= $h($definition['name']) ?> »</h2>
            <p><a href="<?= $h(url('back-office/referentiels/qualifications/' . $targetId . '/edit')) ?>">Revenir à la qualification et à ses formations</a></p>
            <p>Un objectif indique à quel métier contribue la qualification et, si connu, le niveau visé. Il ne déclenche aucune attribution de spécialité. Les règles d’obtention, les certificats et leurs échéances restent ceux de la qualification.</p>
            <ul class="eff-specialties__list"><?php foreach (($specialtyTargets ?? []) as $row): ?>
                <li><?= $h(Labels::title($row)) ?><?php if (($row['level_code'] ?? '') !== '' && $row['level_code'] !== null): ?> · Niveau <?= $h($row['level_code']) ?><?php endif; ?>
                    <?php if (!empty($specialtyCanQualification)): ?><form method="post" action="<?= $h($actionUrl) ?>"><?php $postFields('remove_target'); ?><input type="hidden" name="specialty_code_id" value="<?= (int) $row['specialty_code_id'] ?>"><button class="eff-btn" type="submit">Retirer cet objectif</button></form><?php endif; ?>
                </li><?php endforeach; ?></ul>
            <?php if (($specialtyTargets ?? []) === []): ?><p>Aucun objectif de spécialité associé.</p><?php endif; ?>
        </section>
    <?php else: ?>
        <p class="eff-specialties__card">Pour rattacher une référence, ouvrez une <a href="<?= $h(effectifs_workspace_url()) ?>">fiche membre</a>, un <a href="<?= $h(effectifs_workspace_url('fonctions')) ?>">emploi</a> ou une <a href="<?= $h(url('back-office/referentiels/qualifications')) ?>">qualification</a>, puis choisissez « Spécialités militaires ».</p>
    <?php endif; ?>

    <?php if ($selectedCode !== null): $code = $selectedCode; ?>
        <section class="eff-specialties__card" id="sp-entry" aria-labelledby="sp-entry-title">
            <h2 id="sp-entry-title"><?= $h(Labels::title($code)) ?></h2>
            <p><?= $h($code['official_title']) ?> · <?= $h($code['country_name_fr']) ?> · <?= $h($code['service_name']) ?> · <?= $h($code['system_name']) ?></p>
            <p><strong><?= $h(Labels::kind((string) $code['kind'])) ?></strong> · <?= $h(Labels::verification((string) $code['verification_status'])) ?></p>
            <p><?= nl2br($h($code['description'] ?? '')) ?></p>
            <?php $sourceUrl = Labels::sourceUrl($code['source_url'] ?? ''); if ($sourceUrl !== ''): ?>
                <p>Source : <a href="<?= $h($sourceUrl) ?>" target="_blank" rel="noopener noreferrer"><?= $h($code['source_name'] ?? $code['source_publisher'] ?? 'Publication officielle') ?></a> · <?= $h($code['source_locator'] ?? '') ?><?php if (!empty($code['verified_at'])): ?> · Contrôlée le <?= $h($code['verified_at']) ?><?php endif; ?></p>
            <?php endif; ?>
            <?php if (($code['levels'] ?? []) !== []): ?>
                <h3>Niveaux documentés</h3><ul class="eff-specialties__list"><?php foreach ($code['levels'] as $level): ?><li><strong><?= $h($level['level_code'] . ' · ' . $level['official_title']) ?></strong> — <?= $h($level['label_fr']) ?>. <?= $h(Labels::verification((string) $level['verification_status'])) ?><?php if (!empty($level['awarded_code'])): ?> · Code individuel <?= $h($level['awarded_code']) ?><?php endif; ?></li><?php endforeach; ?></ul>
                <p>Un niveau dont l’applicabilité reste à vérifier peut être déclaré pour suivi. Sa confirmation exige une référence vérifiée. Le code individuel complet n’est affiché que s’il est attesté.</p>
            <?php else: ?><p>Aucune progression de niveau n’est publiée ici pour cette référence. Laissez le niveau vide ; les brevets peuvent être liés comme qualifications.</p><?php endif; ?>
            <?php if (($member !== null && !empty($specialtyCanMember)) || ($definition !== null && !empty($specialtyCanQualification))):
                $current = [];
                foreach (($member !== null ? ($specialtyMemberRows ?? []) : ($specialtyTargets ?? [])) as $saved) if ((int) $saved['specialty_code_id'] === $selectedId) $current = $saved;
            ?>
            <form method="post" action="<?= $h($actionUrl) ?>" class="eff-specialties__form">
                <?php $postFields($member !== null ? 'member' : 'qualification'); ?>
                <input type="hidden" name="specialty_code_id" value="<?= $selectedId ?>">
                <label>Niveau <?= $definition !== null ? 'visé' : 'détenu' ?> (facultatif)<select name="specialty_level_id"><option value="">Non renseigné / non applicable</option>
                <?php foreach (($code['levels'] ?? []) as $level): ?><option value="<?= (int) $level['id'] ?>" <?= (int) ($current['specialty_level_id'] ?? 0) === (int) $level['id'] ? 'selected' : '' ?>><?= $h($level['level_code'] . ' · ' . $level['official_title'] . ' — ' . Labels::verification((string) $level['verification_status']) . (!empty($level['archived_at']) ? ' — Niveau archivé conservé' : '')) ?></option><?php endforeach; ?></select></label>
                <?php if ($member !== null): ?>
                    <label>État de validation<select name="status"><option value="declared">Déclarée — à vérifier</option><option value="confirmed" <?= ($current['status'] ?? '') === 'confirmed' ? 'selected' : '' ?>>Confirmée par les RH</option></select></label>
                    <label>Date d’obtention (facultative)<input type="date" name="obtained_at" value="<?= $h($current['obtained_at'] ?? '') ?>" max="<?= $h(date('Y-m-d')) ?>"></label>
                    <label>Qualification du dossier servant de preuve (facultative)<select name="evidence_qualification_id"><option value="">Aucune preuve liée</option><?php foreach (($specialtyEvidence ?? []) as $award): ?><option value="<?= (int) $award['id'] ?>" <?= (int) ($current['evidence_qualification_id'] ?? 0) === (int) $award['id'] ? 'selected' : '' ?>><?= $h($award['qualification_name'] ?? '') ?></option><?php endforeach; ?></select></label>
                <?php endif; ?>
                <button type="submit" class="eff-btn eff-btn--primary"><?= $member !== null ? 'Enregistrer la spécialité au dossier' : 'Enregistrer l’objectif pédagogique' ?></button>
            </form>
            <?php endif; ?>
        </section>
    <?php endif; ?>
    <section class="eff-specialties__card" aria-labelledby="sp-catalog-title">
        <h2 id="sp-catalog-title">Catalogue des codes et appellations</h2>
        <form method="get" action="<?= $h($actionUrl) ?>" class="eff-specialties__filters">
            <?php foreach ($context as $key => $value): ?><input type="hidden" name="<?= $h($key) ?>" value="<?= (int) $value ?>"><?php endforeach; ?>
            <label>Pays<select name="country"><option value="">Tous les pays</option><?php foreach (($specialtyCountries ?? []) as $iso => $name): ?><option value="<?= $h($iso) ?>" <?= ($specialtyCountry ?? '') === $iso ? 'selected' : '' ?>><?= $h($name) ?></option><?php endforeach; ?></select></label>
            <label>Code, métier, armée ou explication<input type="search" name="q" value="<?= $h($specialtySearch ?? '') ?>" placeholder="1Z2X1, FUSIL, maintenance…"></label><button class="eff-btn" type="submit">Rechercher</button>
        </form>
        <ul class="eff-specialties__list"><?php foreach (($specialtyCatalog ?? []) as $code): ?>
            <li><a href="<?= $h($actionUrl . '?' . http_build_query($context + ['code' => (int) $code['id'], 'country' => $specialtyCountry ?? '', 'q' => $specialtySearch ?? ''])) ?>#sp-entry"><strong><?= $h(Labels::title($code)) ?></strong></a><p><?= $h($code['country_name_fr'] . ' · ' . $code['service_name'] . ' · ' . $code['system_name']) ?></p><p><?= $h(Labels::kind((string) $code['kind']) . ' · ' . Labels::verification((string) $code['verification_status'])) ?></p></li>
        <?php endforeach; ?></ul>
        <?php if (($specialtyCatalog ?? []) === []): ?><p>Aucune référence ne correspond à cette recherche.</p><?php endif; ?>
    </section>
    <?php endif; ?>
</div>
