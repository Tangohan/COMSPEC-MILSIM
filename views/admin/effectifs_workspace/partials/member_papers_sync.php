<?php
declare(strict_types=1);

/**
 * Fiche membre › Identité & papiers › « Transmission en jeu » et « Différence avec le jeu » (inclus par member_papers.php).
 *
 * @var array<string, mixed> $pp sectionFor() ; $pp['sync'] = MemberIdentityPapersController::syncFor()
 * @var callable $ph
 * @var bool $manage
 * @var bool $truth
 * @var string $base
 * @var string $csrfToken
 * @var callable $countryLabel
 * @var array<int, array<string, mixed>> $docById
 */
$sync = $pp['sync'] ?? null;
if (!is_array($sync)) {
    return;
}
$diag = $sync['diag'];
$stateChip = ['ready' => 'is-ok', 'incomplete' => 'is-info', 'error' => 'is-warn', 'blocked' => 'idm-state--blocked'];
$stateLabels = \App\Services\Sse\MemberIdentitySyncService::STATES;
$originLabels = [
    'athena' => 'Papiers reçus d’Athena',
    'generated' => 'Identité tirée par le mod (aucun papier Athena reçu)',
    'game' => 'Papiers donnés en jeu (Zeus, éditeur)',
    'cover' => 'Couverture née en jeu',
    'import' => 'Couverture importée d’un fichier',
];
$identityById = [];
foreach ($pp['identities'] as $i) {
    $identityById[(int) $i['id']] = $i;
}
$sentId = (int) ($diag['sent_identity_id'] ?? 0);
$sentName = isset($identityById[$sentId]) ? \App\Services\Sse\MemberIdentityPapersService::displayName($identityById[$sentId]) : '';
// Un même bouton (ex. « Composer le kit ») n'est proposé qu'une fois par bloc : les lignes suivantes renvoient au premier.
$shown = [];
$fix = static function (array $f) use ($ph, $manage, $base, $csrfToken, &$shown): void {
    $label = (string) ($f['label'] ?? '');
    if (isset($f['do'], $f['doc']) && $manage) {
        ?>
        <form method="post" action="<?= $ph($base . '/documents/' . (int) $f['doc'] . '/action') ?>" class="idm-sync__fix">
            <input type="hidden" name="_csrf_token" value="<?= $ph($csrfToken) ?>">
            <input type="hidden" name="do" value="<?= $ph($f['do']) ?>">
            <button type="submit" class="eff-fm-btn is-sm"><?= $ph($label) ?></button>
        </form>
        <?php
        return;
    }
    if (isset($f['photo']) && $manage) {
        // Ouvre directement le choix du fichier de la photo d'identité (carte de l'identité, plus bas).
        ?><label class="eff-fm-btn is-sm is-dark idm-sync__fix" for="idm-photo-<?= (int) $f['photo'] ?>"><?= $ph($label) ?></label><?php
        return;
    }
    $href = match (true) {
        isset($f['edit']) && $manage => $base . '/documents/' . (int) $f['edit'] . '/modifier',
        isset($f['new']) && $manage => $base . '/documents/nouveau' . '?' . http_build_query(['identity_id' => (int) $f['new']]),
        (string) ($f['href'] ?? '') !== '' => (string) $f['href'],
        default => '',
    };
    if ($href !== '' && isset($shown[$href])) {
        return;
    }
    if ($href !== '') {
        $shown[$href] = true;
        ?><a class="eff-fm-btn is-sm idm-sync__fix" href="<?= $ph($href) ?>"><?= $ph($label) ?></a><?php
    } elseif ($label !== '') {
        ?><span class="idm-sync__todo"><?= $ph($label) ?></span><?php
    }
};
$issues = static function (array $list) use ($ph, $stateChip, $stateLabels, $fix): void {
    if ($list === []) {
        return;
    }
    ?>
    <ul class="idm-sync__issues">
        <?php foreach ($list as $is): ?>
            <li class="idm-sync__issue">
                <span class="eff-fm-chip <?= $ph($stateChip[$is['level']] ?? '') ?>"><?= $ph($stateLabels[$is['level']] ?? '') ?></span>
                <div class="idm-sync__what">
                    <b><?= $ph($is['title']) ?></b>
                    <p><?= $ph($is['why']) ?> <span class="idm-sync__ex"><?= $ph($is['example']) ?></span></p>
                </div>
                <div class="idm-sync__act"><?php $fix($is['fix']); ?></div>
            </li>
        <?php endforeach; ?>
    </ul>
    <?php
};
?>
<article class="eff-fm-card eff-fm-card--wide idm-sync" id="papiers-synchro">
    <div class="eff-fm-card__head">
        <h3>Transmission en jeu</h3>
        <span class="eff-fm-chip <?= $ph($stateChip[$diag['state']] ?? '') ?>"><?= $ph($stateLabels[$diag['state']] ?? '') ?></span>
    </div>
    <p class="idm-text">Ce qu’Athena prévoit d’envoyer en jeu, ce qui l’en empêche, et ce que le jeu a réellement reçu. Sans papier d’Athena remis, le jeu tire au hasard une identité au joueur : c’est elle qu’il voit sur son téléphone (Ma couverture), pas celle d’Athena.</p>
    <ul class="idm-legend" aria-label="États de transmission">
        <li><span class="eff-fm-chip is-ok"><?= $ph($stateLabels['ready']) ?></span> part en jeu tel quel</li>
        <li><span class="eff-fm-chip is-info"><?= $ph($stateLabels['incomplete']) ?></span> part, le jeu complète au hasard</li>
        <li><span class="eff-fm-chip is-warn"><?= $ph($stateLabels['error']) ?></span> part avec une donnée fausse</li>
        <li><span class="eff-fm-chip idm-state--blocked"><?= $ph($stateLabels['blocked']) ?></span> ne part pas</li>
    </ul>
    <?php if ($sentName !== '' || $diag['auto_kit']): ?>
        <p class="idm-text">
            <?php if ($sentName !== ''): ?>Identité envoyée en jeu : <strong translate="no"><?= $ph($sentName) ?></strong>.<?php endif; ?>
            <?= $diag['auto_kit'] ? 'Aucun kit composé : kit automatique (identité principale).' : '' ?>
        </p>
    <?php endif; ?>
    <?php $dl = $diag['delivery'] ?? null; ?>
    <?php if (is_array($dl)): ?>
        <section class="idm-sync__block" id="papiers-livraison">
            <div class="idm-sync__head">
                <div class="idm-sync__who">
                    <h4>Arrivée réelle en jeu</h4>
                    <span class="eff-fm-chip <?= $ph(['received' => 'is-ok', 'not_received' => 'is-warn', 'other' => 'is-info'][$dl['state']] ?? '') ?>"><?= $ph($dl['title']) ?></span>
                </div>
            </div>
            <?php if ($dl['at'] !== ''): ?>
                <p class="idm-sync__note">Dernière remontée du jeu le <?= $ph($dl['at']) ?> UTC<?php if ($dl['server'] !== ''): ?> · serveur <span translate="no"><?= $ph($dl['server']) ?></span><?php endif; ?></p>
            <?php endif; ?>
            <p class="idm-text"><?= $ph($dl['why']) ?></p>
            <?php if ($dl['todo'] !== ''): ?>
                <div class="idm-callout">
                    <p><?= $ph($dl['todo']) ?></p>
                    <?php if ($dl['state'] === 'not_received' && is_array($pp['cover'] ?? null)): ?>
                        <a class="eff-fm-btn is-sm" href="#couverture">Télécharger la couverture</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </section>
    <?php endif; ?>
    <?php $shown = []; $issues($diag['account']); ?>
    <?php foreach ($diag['identities'] as $iid => $st): ?>
        <?php $i = $identityById[(int) $iid] ?? null; if ($i === null) { continue; } ?>
        <?php $shown = []; $iName = \App\Services\Sse\MemberIdentityPapersService::displayName($i); ?>
        <section class="idm-sync__block" aria-label="<?= $ph($iName) ?>">
            <div class="idm-sync__head">
                <div class="idm-sync__who">
                    <h4 translate="no"><?= $ph($iName) ?></h4>
                    <span class="idm-doc__num" translate="no"><?= $ph($i['ref']) ?></span>
                    <span class="eff-fm-chip <?= $ph($stateChip[$st['state']] ?? '') ?>"><?= $ph($st['state_label']) ?></span>
                </div>
                <?php if ($manage && (int) $iid !== $sentId): ?>
                    <form method="post" action="<?= $ph($base . '/envoyer-jeu') ?>" class="idm-sync__fix"
                          data-idm-confirm="<?= $ph('Le kit par défaut sera remplacé par les papiers émis de cette identité : elle partira en jeu à la place de l’identité actuelle. Continuer ?') ?>">
                        <input type="hidden" name="_csrf_token" value="<?= $ph($csrfToken) ?>">
                        <input type="hidden" name="identity_id" value="<?= (int) $iid ?>">
                        <button type="submit" class="eff-fm-btn is-sm" title="Remplace le kit par défaut par les papiers émis de cette identité">Utiliser cette identité en jeu</button>
                    </form>
                <?php endif; ?>
            </div>
            <p class="idm-sync__note"><?= $ph($st['note']) ?></p>
            <?php $issues($st['issues']); ?>
            <?php $myDocs = array_filter(array_keys($diag['documents']), static fn (int|string $did): bool => isset($docById[(int) $did]) && (int) $docById[(int) $did]['identity_id'] === (int) $iid); ?>
            <?php if ($myDocs !== []): ?>
                <ul class="idm-sync__docs" aria-label="Papiers">
                    <?php foreach ($myDocs as $did): ?>
                        <?php $d = $docById[(int) $did]; $dst = $diag['documents'][$did]; ?>
                        <li>
                            <div class="idm-sync__doc">
                                <span class="idm-sync__docname"><?= $ph($d['doc_type_label'] . ' · ' . $d['country_name']) ?> <span class="idm-doc__num" translate="no"><?= $ph($d['doc_number']) ?></span></span>
                                <span class="eff-fm-chip <?= $ph($stateChip[$dst['state']] ?? '') ?>"><?= $ph($dst['state_label']) ?></span>
                                <span class="idm-sync__note"><?= $ph($dst['note']) ?></span>
                            </div>
                            <?php $issues($dst['issues']); ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>
    <?php endforeach; ?>

    <h4 class="idm-sync__title">Différence avec le jeu</h4>
    <?php if ($sync['reports'] === []): ?>
        <p class="idm-text">Rien de remonté du jeu pour l’instant. Le téléphone du joueur envoie l’identité qu’il utilise quand il ouvre Ma couverture (réglage « Transmission automatique à Athena » de la communauté).</p>
    <?php endif; ?>
    <?php foreach ($sync['reports'] as $r): ?>
        <section class="idm-sync__block" aria-label="<?= $ph($r['name'] ?? '') ?>">
            <div class="idm-sync__head">
                <div class="idm-sync__who">
                    <h4 translate="no"><?= $ph($r['name'] ?? '') ?></h4>
                    <span class="eff-fm-chip <?= in_array($r['origin'], ['generated', 'cover', 'import'], true) ? 'is-warn' : 'is-info' ?>"><?= $ph($originLabels[$r['origin']] ?? $r['origin']) ?></span>
                </div>
            </div>
            <p class="idm-sync__note">Remontée le <?= $ph(substr((string) $r['updated_at'], 0, 16)) ?> UTC · comparée à <?= $ph(\App\Services\Sse\MemberIdentityPapersService::displayName($r['identity'])) ?> (<?= $ph($r['identity']['ref']) ?>)</p>
            <?php if (!empty($r['reviewed'])): ?>
                <p class="idm-text">Différence déjà examinée le <?= $ph(substr((string) ($r['reviewed_at'] ?? ''), 0, 16)) ?> : elle reviendra si le jeu envoie autre chose.</p>
            <?php elseif ($r['diff'] === []): ?>
                <p class="idm-text">Identique à Athena sur les champs envoyés par le jeu.</p>
            <?php else: ?>
                <?php if ($manage): ?>
                <form method="post" action="<?= $ph($base . '/synchro-jeu') ?>" class="idm-sync__form">
                    <input type="hidden" name="_csrf_token" value="<?= $ph($csrfToken) ?>">
                    <input type="hidden" name="game_cover_id" value="<?= (int) $r['id'] ?>">
                <?php endif; ?>
                    <div class="idm-sync__scroll">
                    <table class="idm-sync__table">
                        <thead><tr><th scope="col">Champ</th><th scope="col">Athena</th><th scope="col">Jeu</th><?php if ($manage): ?><th scope="col">Version gardée</th><?php endif; ?></tr></thead>
                        <tbody>
                        <?php foreach ($r['diff'] as $df): ?>
                            <tr>
                                <th scope="row"><?= $ph($df['label']) ?></th>
                                <td translate="no" data-label="Athena"><?= $ph($df['athena'] !== '' ? ($df['field'] === 'nationality' ? $countryLabel($df['athena']) : $df['athena']) : '—') ?></td>
                                <td translate="no" data-label="Jeu"><?= $ph($df['field'] === 'nationality' ? $countryLabel($df['game']) : $df['game']) ?></td>
                                <?php if ($manage): ?>
                                    <td>
                                        <fieldset class="idm-choice">
                                            <legend class="eff-fm-sr"><?= $ph($df['label']) ?> : version gardée</legend>
                                            <label class="idm-choice__opt"><input type="radio" name="choice[<?= $ph($df['field']) ?>]" value="athena"> <span>Garder Athena</span></label>
                                            <label class="idm-choice__opt"><input type="radio" name="choice[<?= $ph($df['field']) ?>]" value="game"> <span>Reprendre la version du jeu</span></label>
                                        </fieldset>
                                    </td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                    </div>
                    <?php if ($manage): ?>
                        <p class="idm-text">Rien n’est écrasé sans votre choix : chaque valeur écartée (d’Athena ou du jeu) est gardée dans l’historique ci-dessous et se restaure.</p>
                        <div class="idm-doc__actions">
                            <button type="submit" class="eff-fm-btn is-dark">Appliquer les choix</button>
                            <button type="submit" name="keep_both" value="1" class="eff-fm-btn">Garder les deux (Athena reste, le jeu va dans l’historique)</button>
                        </div>
                </form>
                    <?php endif; ?>
            <?php endif; ?>
        </section>
    <?php endforeach; ?>

    <?php if ($sync['history'] !== []): ?>
        <details class="idm-doc__preview idm-sync__history">
            <summary>Historique des valeurs écartées (<?= count($sync['history']) ?>)</summary>
            <ul class="eff-fm-list">
                <?php foreach ($sync['history'] as $hr): ?>
                    <?php $label = \App\Services\Sse\MemberIdentitySyncService::FIELDS[$hr['field']][0] ?? $hr['field']; ?>
                    <li>
                        <span class="eff-fm-list__dt"><?= $ph(substr((string) $hr['created_at'], 0, 16)) ?></span>
                        <div>
                            <b><?= $ph($label) ?> : « <?= $ph($hr['lost_value'] ?? '—') ?> » (<?= $ph($hr['lost_side'] === 'game' ? 'version du jeu' : 'version d’Athena') ?>) écarté au profit de « <?= $ph($hr['kept_value'] ?? '—') ?> »</b>
                            <p><?= $ph(['game' => 'Repris du jeu', 'athena' => 'Athena gardée', 'both' => 'Les deux gardés', 'restore' => 'Restauration'][$hr['decision']] ?? $hr['decision']) ?><?= ($hr['restored_at'] ?? null) !== null ? ' · restauré le ' . $ph(substr((string) $hr['restored_at'], 0, 16)) : '' ?></p>
                        </div>
                        <?php if ($manage && ($hr['restored_at'] ?? null) === null): ?>
                            <form method="post" action="<?= $ph($base . '/historique/' . (int) $hr['id'] . '/restaurer') ?>" class="idm-sync__restore">
                                <input type="hidden" name="_csrf_token" value="<?= $ph($csrfToken) ?>">
                                <button type="submit" class="eff-fm-btn is-sm">Restaurer</button>
                            </form>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        </details>
    <?php endif; ?>
</article>
