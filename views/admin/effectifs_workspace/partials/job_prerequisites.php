<?php
declare(strict_types=1);
// Fiche emploi : prérequis (doctrines, heures, qualifications, compétences), éditeur et dérogations accordées.
// Données : JobPrerequisiteViewData::forJobSheet (jobPrereqReady, jobPrereqItems, jobPrereqOptions, jobWaivers…).
$prereqItems = is_array($jobPrereqItems ?? null) ? $jobPrereqItems : [];
$prereqOptions = is_array($jobPrereqOptions ?? null) ? $jobPrereqOptions : [];
$prereqWaivers = is_array($jobWaivers ?? null) ? $jobWaivers : [];
$prereqTypes = ['' => '— Choisir un prérequis —', 'doctrine' => 'Doctrine à lire ou à signer', 'metric:playtime_hours' => 'Heures de jeu', 'metric:flight_hours' => 'Heures de vol', 'qualification' => 'Qualification détenue', 'competency' => 'Compétence acquise'];
$prereqSelect = static function (string $name, array $choices, string|int $value, string $empty) use ($h): string {
    $out = '<select name="' . $h($name) . '" translate="no"><option value="">' . $h($empty) . '</option>';
    foreach ($choices as $choice) {
        $out .= '<option value="' . $h((string) $choice['id']) . '"' . ((string) $choice['id'] === (string) $value ? ' selected' : '') . '>' . $h((string) $choice['label']) . '</option>';
    }

    return $out . '</select>';
};
$prereqRow = static function (string $index, array $c) use ($h, $prereqTypes, $prereqOptions, $prereqSelect): string {
    $type = ($c['type'] ?? '') === 'metric' ? 'metric:' . $c['metric'] : (string) ($c['type'] ?? '');
    $n = static fn (string $f): string => 'crit[' . $index . '][' . $f . ']';
    ob_start();
    if ($type === 'doctrine_ref'): // Publication du référentiel doctrinal rattachée depuis le catalogue d’emplois : gardée telle quelle. ?>
<div class="eff-jobs-prereq-row" data-prereq-row data-type="doctrine_ref">
    <input type="hidden" name="<?= $h($n('type')) ?>" value="doctrine_ref"><input type="hidden" name="<?= $h($n('key')) ?>" value="<?= $h((string) ($c['key'] ?? '')) ?>">
    <p>Doctrine du référentiel : <strong translate="no"><?= $h(\App\Services\Personnel\JobPrerequisiteService::libraryLabel((string) ($c['key'] ?? ''))) ?></strong></p>
    <label>Exigence<select name="<?= $h($n('mode')) ?>"><?php foreach (\App\Services\Personnel\JobPrerequisiteService::DOCTRINE_MODES as $value => $label): ?><option value="<?= $h($value) ?>" <?= ($c['mode'] ?? 'read') === $value ? 'selected' : '' ?>><?= $h($label) ?></option><?php endforeach ?></select></label>
    <label class="eff-jobs-checkbox"><input type="checkbox" name="<?= $h($n('remove')) ?>" value="1">Retirer</label>
</div>
<?php return (string) ob_get_clean();
    endif ?>
<div class="eff-jobs-prereq-row" data-prereq-row data-type="<?= $h($type) ?>">
    <label>Prérequis<select name="<?= $h($n('type')) ?>" data-prereq-type><?php foreach ($prereqTypes as $value => $label): ?><option value="<?= $h($value) ?>" <?= $type === $value ? 'selected' : '' ?>><?= $h($label) ?></option><?php endforeach ?></select></label>
    <label data-prereq-field="doctrine">Doctrine<?= $prereqSelect($n('document_id'), $prereqOptions['doctrines'] ?? [], (int) ($c['document_id'] ?? 0), 'Choisir une doctrine publiée') ?></label>
    <label data-prereq-field="qualification">Qualification<?= $prereqSelect($n('definition_id'), $prereqOptions['qualifications'] ?? [], (int) ($c['definition_id'] ?? 0), 'Choisir une qualification') ?></label>
    <label data-prereq-field="competency">Compétence<?= $prereqSelect($n('requirement_id'), $prereqOptions['competencies'] ?? [], (int) ($c['requirement_id'] ?? 0), 'Choisir une compétence') ?></label>
    <label data-prereq-field="metric:playtime_hours">Périmètre<select name="<?= $h($n('playtime_scope')) ?>"><?php foreach (\App\Services\Personnel\JobPrerequisiteService::PLAYTIME_SCOPES as $value => $label): ?><option value="<?= $h($value) ?>" <?= $type === 'metric:playtime_hours' && ($c['scope'] ?? '') === $value ? 'selected' : '' ?>><?= $h($label) ?></option><?php endforeach ?></select></label>
    <label data-prereq-field="metric:flight_hours">Appareil<select name="<?= $h($n('flight_scope')) ?>"><?php foreach (\App\Services\Personnel\JobPrerequisiteService::FLIGHT_SCOPES as $value => $label): ?><option value="<?= $h($value) ?>" <?= $type === 'metric:flight_hours' && ($c['scope'] ?? '') === $value ? 'selected' : '' ?>><?= $h($label) ?></option><?php endforeach ?><?php foreach ($prereqOptions['aircraft'] ?? [] as $aircraft): ?><option value="<?= $h($aircraft['id']) ?>" translate="no" <?= ($c['scope'] ?? '') === $aircraft['id'] ? 'selected' : '' ?>><?= $h($aircraft['label']) ?></option><?php endforeach ?></select></label>
    <label data-prereq-field="doctrine">Exigence<select name="<?= $h($n('mode')) ?>"><?php foreach (\App\Services\Personnel\JobPrerequisiteService::DOCTRINE_MODES as $value => $label): ?><option value="<?= $h($value) ?>" <?= ($c['mode'] ?? 'read') === $value ? 'selected' : '' ?>><?= $h($label) ?></option><?php endforeach ?></select></label>
    <label data-prereq-field="metric:playtime_hours metric:flight_hours">Heures minimum<input type="number" name="<?= $h($n('min')) ?>" min="0.5" max="100000" step="0.5" value="<?= $h(isset($c['min']) ? (string) $c['min'] : '') ?>" inputmode="decimal"></label>
    <?php if (($c['type'] ?? '') !== ''): ?><label class="eff-jobs-checkbox"><input type="checkbox" name="<?= $h($n('remove')) ?>" value="1">Retirer</label><?php endif ?>
</div>
<?php return (string) ob_get_clean();
};
?>
<section class="eff-jobs-prereq" id="job-prerequisites" aria-labelledby="job-prerequisites-title">
    <h3 id="job-prerequisites-title">Prérequis de l’emploi</h3>
    <?php if (empty($jobPrereqReady)): ?>
        <p class="eff-jobs-warning">Les prérequis (doctrines, heures de jeu ou de vol, qualifications) arrivent avec la prochaine mise à jour du site. L’emploi s’attribue comme avant.</p>
    <?php else: ?>
        <p>Ce qu’un membre doit remplir pour tenir l’emploi. <?= !empty($jobPrereqBlocking) ? 'Votre communauté les rend bloquants : sans eux, l’attribution demande une dérogation motivée.' : 'Votre communauté les garde indicatifs : un avertissement s’affiche à l’attribution.' ?> Les titulaires actuels gardent l’emploi.</p>
        <?php if ($prereqItems === []): ?><p class="eff-card__hint">Aucun prérequis : l’emploi s’attribue librement.</p><?php else: ?>
        <ul class="eff-jobs-prereq-list"><?php foreach ($prereqItems as $item): $prereqParts = $item['parts']; ?><li><?php require __DIR__ . '/job_prereq_label.php'; ?></li><?php endforeach ?></ul>
        <?php endif ?>
        <?php if ($canManage): ?>
        <details class="eff-jobs-advanced" data-jobs-details="prerequisites"><summary>Modifier les prérequis <small><?= count($prereqItems) ?> / <?= \App\Services\Personnel\JobPrerequisiteService::MAX_ITEMS ?></small></summary>
        <form method="post" action="<?= $h($base . '/prerequis') ?>" data-jobs-save data-prereq-form>
            <input type="hidden" name="_csrf_token" value="<?= $h($csrf) ?>"><input type="hidden" name="job_role_id" value="<?= $id ?>"><input type="hidden" name="_workspace_category" value="<?= $filterId ?>">
            <p class="eff-card__hint">Heures mesurées par le mod (temps de jeu, heures aux commandes par appareil). Doctrines : version en vigueur lue, ou lue et signée. Une compétence est acquise quand le membre détient les qualifications qui lui sont liées dans le référentiel.</p>
            <div data-prereq-rows>
                <?php foreach ($prereqItems as $i => $item): ?><?= $prereqRow((string) $i, $item['criterion']) ?><?php endforeach ?>
                <?php if (count($prereqItems) < \App\Services\Personnel\JobPrerequisiteService::MAX_ITEMS): ?><?= $prereqRow((string) count($prereqItems), []) ?><?php endif ?>
            </div>
            <template data-prereq-template><?= $prereqRow('__i__', []) ?></template>
            <div class="eff-jobs-prereq-actions"><button type="button" class="eff-btn" data-prereq-add>+ Ajouter un prérequis</button><button class="eff-btn eff-btn--primary">Enregistrer les prérequis</button></div>
        </form></details>
        <?php endif ?>
        <?php if ($prereqWaivers !== []): ?>
        <h3>Dérogations</h3>
        <p class="eff-card__hint">Membres autorisés à tenir l’emploi sans remplir ses prérequis. Révoquer une dérogation ne retire pas l’emploi : ses prérequis manquants s’affichent de nouveau.</p>
        <ul class="eff-jobs-waivers"><?php foreach ($prereqWaivers as $waiver): $revoked = !empty($waiver['revoked_at']); ?>
            <li class="<?= $revoked ? 'is-revoked' : '' ?>">
                <strong><a href="<?= $h(effectifs_workspace_url('membres/' . (int) $waiver['user_id'])) ?>" translate="no"><?= $h($waiver['member_name'] ?? ('Membre #' . (int) $waiver['user_id'])) ?></a> <span class="eff-prereq-state <?= $revoked ? 'is-unknown' : 'is-waived' ?>"><?= $revoked ? 'Révoquée' : 'Active' ?></span></strong>
                <small>Motif : <span translate="no"><?= $h($waiver['reason']) ?></span></small>
                <small>Accordée le <?= $h((string) $waiver['granted_at']) ?> par <span translate="no"><?= $h($waiver['granted_by_name'] ?? ('#' . (int) ($waiver['granted_by'] ?? 0))) ?></span><?php if ($revoked): ?> · révoquée le <?= $h((string) $waiver['revoked_at']) ?> par <span translate="no"><?= $h($waiver['revoked_by_name'] ?? ('#' . (int) ($waiver['revoked_by'] ?? 0))) ?></span><?php endif ?></small>
                <?php if (!$revoked && $canManage): ?><form method="post" action="<?= $h($base . '/derogations/revoquer') ?>" data-jobs-save><input type="hidden" name="_csrf_token" value="<?= $h($csrf) ?>"><input type="hidden" name="waiver_id" value="<?= (int) $waiver['id'] ?>"><label>Motif de la révocation (facultatif)<input name="revoke_reason" maxlength="500"></label><button class="eff-btn eff-btn--danger" data-jobs-confirm="Révoquer cette dérogation ? Le membre garde l’emploi.">Révoquer</button></form><?php endif ?>
            </li>
        <?php endforeach ?></ul>
        <?php endif ?>
    <?php endif ?>
</section>
