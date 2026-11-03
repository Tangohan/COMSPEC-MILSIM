<?php
/**
 * Action principale d'un élément du catalogue (et lien vers sa fiche hors fiche détaillée).
 *
 * @var array<string, mixed> $it
 * @var bool $actionsInDetail
 */
$actionsInDetail = $actionsInDetail ?? false;
$award = is_array($it['award'] ?? null) ? $it['award'] : null;
$brevetUrl = $award !== null && trim((string) ($award['certificate_document_path'] ?? '')) !== '' && in_array($it['state'], ['held', 'expiring', 'renew'], true)
    ? url('back-office/ma-situation/qualifications/' . (int) $award['id'] . '/brevet') : '';
$courseLink = '';
foreach ($it['how'] ?? [] as $line) {
    if (($line['type'] ?? '') === 'course' && ($line['url'] ?? '') !== '' && empty($line['met'])) {
        $courseLink = (string) $line['url'];
        break;
    }
}
$showFiche = !$actionsInDetail;
?>
<?php if (($vi = ($versionInfo ?? [])[(string) ($it['course_key'] ?? '')] ?? null) !== null): $vKey = (string) $it['course_key']; $vLearner = null; require __DIR__ . '/version_badge.php'; endif ?>
<div class="acx-actions">
<?php if ($it['kind'] === 'course' && isset($it['course_key'])): ?>
    <?php if (!empty($it['enrolled'])): ?>
        <a class="button small" href="<?= $h($link('classroom') . '?course=' . rawurlencode((string) $it['course_key'])) ?>"><?= $it['state'] === 'held' ? '<span>Revoir la formation</span>' : '<span>Continuer</span>' ?></a>
        <?php if (!empty($it['licence_issued'])): ?><a class="text-link" href="<?= $h($link('record')) ?>"><span>Voir ma licence</span></a><?php endif ?>
    <?php else: ?>
        <form method="post" action="<?= $h($link('action/enroll')) ?>"><?= \App\Core\Csrf::field() ?><input type="hidden" name="course" value="<?= $h($it['course_key']) ?>"><button class="button small"<?= empty($academyStorageReady) ? ' disabled' : '' ?>><span>Commencer la formation</span></button></form>
    <?php endif ?>
<?php elseif ($it['kind'] === 'course'): ?>
    <a class="button small" href="<?= $h($it['url']) ?>"><?= $it['state'] === 'held' ? '<span>Revoir la formation</span>' : ($it['state'] === 'progress' ? '<span>Continuer</span>' : '<span>Commencer la formation</span>') ?></a>
    <?php if ((int) ($it['certificate_id'] ?? 0) > 0): ?><a class="text-link" href="<?= $h(url('academy/formations/certificate/' . (int) $it['certificate_id'])) ?>"><span>Mon attestation</span></a><?php endif ?>
<?php elseif ($it['kind'] === 'qualification'): ?>
    <?php if ($brevetUrl !== ''): ?>
        <a class="button small" href="<?= $h($brevetUrl) ?>"><?= $oxSymbol('download') ?><span>Télécharger le brevet</span></a>
    <?php elseif ($courseLink !== '' && !in_array($it['state'], ['held', 'expiring', 'pending', 'blocked'], true)): ?>
        <a class="button small" href="<?= $h($courseLink) ?>"><span>Commencer la formation</span></a>
    <?php elseif ($actionsInDetail && in_array('manual', $it['modes'], true) && !in_array($it['state'], ['held', 'expiring', 'pending'], true)): ?>
        <p class="acx-contact"><span>Pour l’obtenir, contactez un instructeur</span><?php if (($it['issuer'] ?? '') !== ''): ?> <span>de</span> <b translate="no"><?= $h($it['issuer']) ?></b><?php endif ?>.</p>
    <?php endif ?>
    <?php if ($showFiche): ?><a class="text-link" href="<?= $h($oxFiche((string) $it['id'])) ?>"><span>Voir les critères</span></a><?php $showFiche = false; endif ?>
<?php elseif ($it['kind'] === 'reference'): ?>
    <?php if ($oxCanImport): $oq = is_array($offerQuota ?? null) ? $offerQuota : null; $oqFull = $oq !== null && !$oq['can_add']; ?>
    <form method="post" action="<?= $h(url('back-office/referentiels/qualifications/catalogue/importer')) ?>" class="acx-import">
        <?= \App\Core\Csrf::field() ?><input type="hidden" name="one" value="<?= $h((string) $it['ref_id']) ?>"><input type="hidden" name="return_academy" value="1">
        <button class="button small"<?= $oqFull ? ' disabled aria-describedby="acx-q-' . $h((string) $it['ref_id']) . '"' : '' ?>><?= $oxSymbol('download') ?><span>Importer dans mon référentiel</span></button>
        <?php if ($oq !== null && !$oq['unlimited'] && ($actionsInDetail || $oqFull)): ?>
        <p class="acx-quota<?= $oqFull ? ' is-full' : '' ?>" id="acx-q-<?= $h((string) $it['ref_id']) ?>"><b><?= (int) $oq['used'] ?> / <?= (int) $oq['limit'] ?></b> <span>imports sur votre formule</span><?php if ($oq['offer'] !== null): ?> · <a class="acx-plan" href="<?= $h($oq['upgrade_url']) ?>" title="<?= $h($oqFull ? 'Plafond atteint : ce qui est déjà importé reste utilisable. Passez à une formule supérieure pour importer davantage.' : 'Plus d’imports avec une formule supérieure.') ?>"><span>Formule</span> <span translate="no"><?= $h($oq['offer']['name']) ?></span> : <?= $oq['offer']['value'] === 0 ? '<span>illimité</span>' : (int) $oq['offer']['value'] ?></a><?php endif ?></p>
        <?php endif ?>
    </form>
    <?php elseif ($actionsInDetail): ?>
    <p class="acx-contact">Un responsable de votre communauté peut l’importer dans le référentiel des qualifications.</p>
    <?php endif ?>
<?php elseif ($it['kind'] === 'pathway'): ?>
    <?php if (!$actionsInDetail): ?><a class="button small" href="<?= $h($oxFiche((string) $it['id'])) ?>"><span>Voir le parcours</span></a><?php $showFiche = false; endif ?>
    <a class="text-link" href="<?= $h($it['url']) ?>"><span>Ouvrir dans le générateur de parcours</span></a>
<?php elseif ($it['kind'] === 'competency'): ?>
    <?php if (!$actionsInDetail): ?><a class="button small outline" href="<?= $h($oxFiche((string) $it['id'])) ?>"><span>Comment l’acquérir</span></a><?php $showFiche = false; endif ?>
    <a class="text-link" href="<?= $h($it['generator']) ?>"><span>Générer un parcours</span></a>
<?php elseif ($it['kind'] === 'issuer'): ?>
    <a class="button small outline" href="<?= $h($oxUrl(['onglet' => 'qualifications', 'emetteur' => (string) (int) $it['issuer_id'], 'q' => '', 'categorie' => '', 'niveau' => '', 'mode' => '', 'etat' => '', 'source' => ''])) ?>"><span>Voir ses qualifications</span></a>
<?php endif ?>
<?php if ($showFiche): ?><a class="text-link" href="<?= $h($oxFiche((string) $it['id'])) ?>"><span>Fiche détaillée</span></a><?php endif ?>
</div>
