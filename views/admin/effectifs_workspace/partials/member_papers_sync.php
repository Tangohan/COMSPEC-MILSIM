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
$fix = static function (array $f) use ($ph, $manage, $base, $csrfToken): void {
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
    $href = match (true) {
        isset($f['edit']) && $manage => $base . '/documents/' . (int) $f['edit'] . '/modifier',
        isset($f['new']) && $manage => $base . '/documents/nouveau' . '?' . http_build_query(['identity_id' => (int) $f['new']]),
        (string) ($f['href'] ?? '') !== '' => (string) $f['href'],
        default => '',
    };
    if ($href !== '') {
        ?><a class="eff-fm-btn is-sm" href="<?= $ph($href) ?>"><?= $ph($label) ?></a><?php
    } elseif ($label !== '') {
        ?><span class="eff-card__hint"><?= $ph($label) ?></span><?php
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
                <div>
                    <b><?= $ph($is['title']) ?></b>
                    <p><?= $ph($is['why']) ?> <span class="idm-sync__ex"><?= $ph($is['example']) ?></span></p>
                </div>
                <?php $fix($is['fix']); ?>
            </li>
        <?php endforeach; ?>
    </ul>
    <?php
};
?>
<article class="eff-fm-card eff-fm-card--wide" id="papiers-synchro">
    <div class="eff-fm-card__head">
        <h3>Transmission en jeu</h3>
        <span class="eff-fm-chip <?= $ph($stateChip[$diag['state']] ?? '') ?>"><?= $ph($stateLabels[$diag['state']] ?? '') ?></span>
    </div>
    <p class="eff-fm-lead">Ce qu’Athena prévoit d’envoyer en jeu, ce qui l’en empêche, et ce que le jeu a réellement reçu. Sans papier d’Athena remis, le jeu tire au hasard une identité au joueur : c’est elle qu’il voit sur son téléphone (Ma couverture), pas celle d’Athena.</p>
    <p class="eff-card__hint">Prêt : prévu tel quel · Incomplet : prévu, le jeu complète au hasard · Erroné : prévu avec une donnée fausse · Bloqué : ne part pas.<?= $diag['auto_kit'] ? ' Aucun kit composé : kit automatique (identité principale).' : '' ?></p>
    <?php $dl = $diag['delivery'] ?? null; ?>
    <?php if (is_array($dl)): ?>
        <div class="idm-sync__block" id="papiers-livraison">
            <div class="idm-doc__head">
                <b>Arrivée réelle en jeu</b>
                <span class="eff-fm-chip <?= $ph(['received' => 'is-ok', 'not_received' => 'is-warn', 'other' => 'is-info'][$dl['state']] ?? '') ?>"><?= $ph($dl['title']) ?></span>
                <?php if ($dl['at'] !== ''): ?>
                    <span class="idm-doc__meta">Dernière remontée du jeu le <?= $ph($dl['at']) ?> UTC<?php if ($dl['server'] !== ''): ?> · serveur <span translate="no"><?= $ph($dl['server']) ?></span><?php endif; ?></span>
                <?php endif; ?>
            </div>
            <p><?= $ph($dl['why']) ?></p>
            <?php if ($dl['todo'] !== ''): ?><p class="eff-card__hint"><?= $ph($dl['todo']) ?><?php if ($dl['state'] === 'not_received' && is_array($pp['cover'] ?? null)): ?> <a class="link" href="#couverture">Télécharger la couverture</a><?php endif; ?></p><?php endif; ?>
        </div>
    <?php endif; ?>
    <?php $issues($diag['account']); ?>
    <?php foreach ($diag['identities'] as $iid => $st): ?>
        <?php $i = $identityById[(int) $iid] ?? null; if ($i === null) { continue; } ?>
        <div class="idm-sync__block">
            <div class="idm-doc__head">
                <b><?= $ph(\App\Services\Sse\MemberIdentityPapersService::displayName($i)) ?></b>
                <span class="idm-doc__num" translate="no"><?= $ph($i['ref']) ?></span>
                <span class="eff-fm-chip <?= $ph($stateChip[$st['state']] ?? '') ?>"><?= $ph($st['state_label']) ?></span>
                <span class="idm-doc__meta"><?= $ph($st['note']) ?></span>
                <?php if ($manage && (int) $iid !== (int) ($diag['sent_identity_id'] ?? 0)): ?>
                    <form method="post" action="<?= $ph($base . '/envoyer-jeu') ?>" class="idm-sync__fix">
                        <input type="hidden" name="_csrf_token" value="<?= $ph($csrfToken) ?>">
                        <input type="hidden" name="identity_id" value="<?= (int) $iid ?>">
                        <button type="submit" class="eff-fm-btn is-sm">Envoyer la version Athena vers le jeu</button>
                    </form>
                <?php endif; ?>
            </div>
            <?php $issues($st['issues']); ?>
            <?php foreach ($diag['documents'] as $did => $dst): ?>
                <?php $d = $docById[(int) $did] ?? null; if ($d === null || (int) $d['identity_id'] !== (int) $iid) { continue; } ?>
                <div class="idm-sync__doc">
                    <span><?= $ph($d['doc_type_label'] . ' · ' . $d['country_name']) ?> <span class="idm-doc__num" translate="no"><?= $ph($d['doc_number']) ?></span></span>
                    <span class="eff-fm-chip <?= $ph($stateChip[$dst['state']] ?? '') ?>"><?= $ph($dst['state_label']) ?></span>
                    <span class="idm-doc__meta"><?= $ph($dst['note']) ?></span>
                </div>
                <?php $issues($dst['issues']); ?>
            <?php endforeach; ?>
        </div>
    <?php endforeach; ?>

    <h4 class="idm-sync__title">Différence avec le jeu</h4>
    <?php if ($sync['reports'] === []): ?>
        <p class="eff-fm-lead">Rien de remonté du jeu pour l’instant. Le téléphone du joueur envoie l’identité qu’il utilise quand il ouvre Ma couverture (réglage « Transmission automatique à Athena » de la communauté).</p>
    <?php endif; ?>
    <?php foreach ($sync['reports'] as $r): ?>
        <div class="idm-sync__block">
            <div class="idm-doc__head">
                <b translate="no"><?= $ph($r['name'] ?? '') ?></b>
                <span class="eff-fm-chip <?= in_array($r['origin'], ['generated', 'cover', 'import'], true) ? 'is-warn' : 'is-info' ?>"><?= $ph($originLabels[$r['origin']] ?? $r['origin']) ?></span>
                <span class="idm-doc__meta">Remontée le <?= $ph(substr((string) $r['updated_at'], 0, 16)) ?> UTC · comparée à <?= $ph(\App\Services\Sse\MemberIdentityPapersService::displayName($r['identity'])) ?> (<?= $ph($r['identity']['ref']) ?>)</span>
            </div>
            <?php if (!empty($r['reviewed'])): ?>
                <p class="eff-card__hint">Différence déjà examinée le <?= $ph(substr((string) ($r['reviewed_at'] ?? ''), 0, 16)) ?> : elle reviendra si le jeu envoie autre chose.</p>
            <?php elseif ($r['diff'] === []): ?>
                <p class="eff-fm-lead">Identique à Athena sur les champs envoyés par le jeu.</p>
            <?php else: ?>
                <?php if ($manage): ?>
                <form method="post" action="<?= $ph($base . '/synchro-jeu') ?>" class="eff-card__form">
                    <input type="hidden" name="_csrf_token" value="<?= $ph($csrfToken) ?>">
                    <input type="hidden" name="game_cover_id" value="<?= (int) $r['id'] ?>">
                <?php endif; ?>
                    <table class="idm-sync__table">
                        <thead><tr><th scope="col">Champ</th><th scope="col">Athena</th><th scope="col">Jeu</th><?php if ($manage): ?><th scope="col">Version gardée</th><?php endif; ?></tr></thead>
                        <tbody>
                        <?php foreach ($r['diff'] as $df): ?>
                            <tr>
                                <th scope="row"><?= $ph($df['label']) ?></th>
                                <td translate="no"><?= $ph($df['athena'] !== '' ? ($df['field'] === 'nationality' ? $countryLabel($df['athena']) : $df['athena']) : '—') ?></td>
                                <td translate="no"><?= $ph($df['field'] === 'nationality' ? $countryLabel($df['game']) : $df['game']) ?></td>
                                <?php if ($manage): ?>
                                    <td>
                                        <label class="eff-card__check"><input type="radio" name="choice[<?= $ph($df['field']) ?>]" value="athena"> Garder Athena</label>
                                        <label class="eff-card__check"><input type="radio" name="choice[<?= $ph($df['field']) ?>]" value="game"> Reprendre la version du jeu</label>
                                    </td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php if ($manage): ?>
                        <p class="eff-card__hint">Rien n’est écrasé sans votre choix : chaque valeur écartée (d’Athena ou du jeu) est gardée dans l’historique ci-dessous et se restaure.</p>
                        <div class="idm-doc__actions">
                            <button type="submit" class="eff-btn eff-btn--primary">Appliquer les choix</button>
                            <button type="submit" name="keep_both" value="1" class="eff-fm-btn is-sm">Garder les deux (Athena reste, le jeu va dans l’historique)</button>
                        </div>
                </form>
                    <?php endif; ?>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>

    <?php if ($sync['history'] !== []): ?>
        <details class="idm-doc__preview">
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
                            <form method="post" action="<?= $ph($base . '/historique/' . (int) $hr['id'] . '/restaurer') ?>">
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
