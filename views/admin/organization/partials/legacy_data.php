<?php
declare(strict_types=1);

/**
 * Qualité des données, section « Données anciennes à vérifier » : ce que les écrans récents affichent autrement parce que
 * la donnée est ancienne ou incomplète, la reprise (simulation d'abord, annulable) des anciens noms de personnage, et le
 * réglage de la clôture automatique des bilans de recrutement. Rien n'est supprimé ni écrasé.
 *
 * Reçoit de data_quality.php : $legacy (LegacyDataScreen::build), $csrfToken, $icon (closure), $h (échappement).
 */

/** @var array<string, mixed> $legacy */
$legacy = is_array($legacy ?? null) ? $legacy : [];
$lgLines = is_array($legacy['lines'] ?? null) ? $legacy['lines'] : [];
$lgCounts = is_array($legacy['counts'] ?? null) ? $legacy['counts'] : [];
$lgSim = is_array($legacy['simulation'] ?? null) ? $legacy['simulation'] : null;
$lgBatches = is_array($legacy['batches'] ?? null) ? $legacy['batches'] : [];
$lgImportOk = !empty($legacy['importAvailable']);
$lgImportWhy = (string) ($legacy['importReason'] ?? '');
$lgRetroOn = !isset($legacy['retroEnabled']) || !empty($legacy['retroEnabled']);
$lgWindow = (int) ($legacy['retroWindowDays'] ?? \App\Support\EnlistmentRetroAutoClose::AUTO_CLOSE_MAX_LATE_DAYS);
$lgThreshold = (int) ($legacy['retroThresholdDays'] ?? \App\Support\EnlistmentRetroAutoClose::AUTO_CLOSE_LATE_DAYS);
$lgImportable = (int) ($lgCounts['importable_names'] ?? 0);
$lgLinks = [
    'no_civil_name' => ['label' => 'Compléter les fiches', 'href' => effectifs_workspace_url('membres')],
    'stale_dossiers' => ['label' => 'Ouvrir le recrutement', 'href' => url('back-office/recruitments')],
    'profile_gaps' => ['label' => 'Ouvrir les effectifs', 'href' => effectifs_workspace_url('membres')],
];
?>
            <!-- Données anciennes à vérifier -->
            <section class="bo-dq-section" id="dq-donnees-anciennes" data-dq-section="donnees-anciennes">
                <header class="bo-dq-section__head">
                    <span class="bo-dq-section__icon"><?= $icon('versions') ?></span>
                    <div>
                        <h2 class="bo-dq-section__title">Données anciennes à vérifier</h2>
                        <p class="bo-dq-section__lead">Athena ne supprime ni n’écrase jamais vos données. Voici ce que les écrans récents affichent autrement parce que la donnée est ancienne ou incomplète, et où la compléter.</p>
                    </div>
                </header>

                <?php if ($lgLines === []): ?>
                <div class="bo-dq-empty bo-dq-empty--quiet">
                    <p class="bo-dq-empty__text">Rien à signaler : vos membres et vos dossiers sont complets.</p>
                </div>
                <?php else: ?>
                <ul class="bo-dq-trash">
                    <?php foreach ($lgLines as $lgLine): $lgKey = (string) ($lgLine['key'] ?? ''); ?>
                    <li class="bo-dq-trash__item">
                        <div class="bo-dq-trash__body">
                            <p class="bo-dq-trash__title"><?= $h((string) ($lgLine['text'] ?? '')) ?></p>
                        </div>
                        <?php if (isset($lgLinks[$lgKey])): ?>
                        <a class="ath-btn" href="<?= $h($lgLinks[$lgKey]['href']) ?>"><?= $h($lgLinks[$lgKey]['label']) ?></a>
                        <?php endif; ?>
                    </li>
                    <?php endforeach; ?>
                </ul>
                <?php endif; ?>

                <?php if ($lgImportable > 0 || $lgBatches !== [] || $lgSim !== null): ?>
                <div class="bo-dq-section__head">
                    <div>
                        <h3 class="bo-dq-section__title">Reprendre les anciens noms</h3>
                        <p class="bo-dq-section__lead">Pour les membres sans prénom ni nom, Athena peut copier l’ancien nom de personnage de leur dossier. Seuls les membres dont le prénom et le nom sont vides sont concernés : aucune valeur existante n’est remplacée, le nom de personnage reste intact, chaque reprise est marquée « Importé depuis l’ancien nom de personnage — à vérifier » et peut être annulée.</p>
                    </div>
                </div>
                <?php if (!$lgImportOk): ?>
                <div class="bo-dq-empty bo-dq-empty--quiet">
                    <p class="bo-dq-empty__text"><?= $h($lgImportWhy !== '' ? $lgImportWhy : 'La reprise n’est pas disponible sur ce serveur.') ?></p>
                </div>
                <?php elseif ($lgSim === null): ?>
                <p class="bo-dq-section__lead"><?= (int) $lgImportable ?> membre(s) pourraient être repris. Commencez par une simulation : rien n’est écrit.</p>
                <a class="ath-btn" href="<?= $h(url('back-office/organisation/qualite-donnees?simuler=1#dq-donnees-anciennes')) ?>">Simuler la reprise</a>
                <?php elseif (empty($lgSim['candidates'])): ?>
                <div class="bo-dq-empty bo-dq-empty--quiet">
                    <p class="bo-dq-empty__text">Simulation : aucun membre à reprendre.</p>
                </div>
                <?php else: ?>
                <p class="bo-dq-section__lead">Simulation (rien n’est écrit) : <?= count($lgSim['candidates']) ?> membre(s) seraient repris<?= (int) ($lgSim['total'] ?? 0) > count($lgSim['candidates']) ? ' sur ' . (int) $lgSim['total'] . ' (les suivants au prochain lot)' : '' ?>.</p>
                <ul class="bo-dq-trash">
                    <?php foreach (array_slice($lgSim['candidates'], 0, 20) as $lgCand): ?>
                    <li class="bo-dq-trash__item">
                        <div class="bo-dq-trash__body">
                            <p class="bo-dq-trash__title" translate="no"><?= $h((string) ($lgCand['character_name'] ?? '')) ?></p>
                            <p class="bo-dq-trash__meta"><span>Prénom : <span translate="no"><?= $h((string) (($lgCand['first_name'] ?? '') !== '' ? $lgCand['first_name'] : '—')) ?></span></span> <span>Nom : <span translate="no"><?= $h((string) ($lgCand['last_name'] ?? '')) ?></span></span></p>
                        </div>
                    </li>
                    <?php endforeach; ?>
                </ul>
                <?php if (count($lgSim['candidates']) > 20): ?>
                <p class="bo-dq-section__lead"><?= (int) (count($lgSim['candidates']) - 20) ?> autre(s) membre(s) non affiché(s) dans cette liste.</p>
                <?php endif; ?>
                <form method="post" action="<?= $h(url('back-office/organisation/qualite-donnees/anciens-noms/reprendre')) ?>">
                    <input type="hidden" name="_csrf_token" value="<?= $h($csrfToken) ?>">
                    <button type="submit" class="ath-btn">Reprendre ces <?= count($lgSim['candidates']) ?> nom(s)</button>
                </form>
                <?php endif; ?>

                <?php if ($lgBatches !== []): ?>
                <ul class="bo-dq-trash">
                    <?php foreach ($lgBatches as $lgBatch): ?>
                    <li class="bo-dq-trash__item">
                        <div class="bo-dq-trash__body">
                            <p class="bo-dq-trash__title">Reprise du <?= $h(date('d/m/Y H:i', (int) (strtotime((string) ($lgBatch['at'] ?? '')) ?: 0))) ?> : <?= (int) ($lgBatch['imported'] ?? 0) ?> nom(s) repris, <?= (int) ($lgBatch['active'] ?? 0) ?> encore à vérifier</p>
                        </div>
                        <?php if ((int) ($lgBatch['active'] ?? 0) > 0): ?>
                        <form method="post" action="<?= $h(url('back-office/organisation/qualite-donnees/anciens-noms/annuler')) ?>">
                            <input type="hidden" name="_csrf_token" value="<?= $h($csrfToken) ?>">
                            <input type="hidden" name="batch_id" value="<?= $h((string) ($lgBatch['batch_id'] ?? '')) ?>">
                            <button type="submit" class="ath-btn">Annuler cette reprise</button>
                        </form>
                        <?php endif; ?>
                    </li>
                    <?php endforeach; ?>
                </ul>
                <?php endif; ?>
                <?php endif; ?>

                <div class="bo-dq-section__head">
                    <div>
                        <h3 class="bo-dq-section__title">Clôture automatique des bilans de recrutement</h3>
                        <p class="bo-dq-section__lead">Un bilan 30 jours attendu depuis <?= $lgThreshold ?> jours est clos automatiquement par Athena (« néant »), jamais au-delà de <?= $lgWindow ?> jours de retard : les dossiers plus anciens restent tels quels et sont seulement signalés ici. Les instructeurs reçoivent une seule information par passage.</p>
                    </div>
                </div>
                <form method="post" action="<?= $h(url('back-office/organisation/qualite-donnees/bilans-automatiques')) ?>">
                    <input type="hidden" name="_csrf_token" value="<?= $h($csrfToken) ?>">
                    <input type="hidden" name="enabled" value="<?= $lgRetroOn ? '0' : '1' ?>">
                    <p class="bo-dq-section__lead"><?= $lgRetroOn ? 'Clôture automatique activée pour votre communauté.' : 'Clôture automatique désactivée pour votre communauté.' ?></p>
                    <button type="submit" class="ath-btn"><?= $lgRetroOn ? 'Désactiver' : 'Activer' ?></button>
                </form>
            </section>
