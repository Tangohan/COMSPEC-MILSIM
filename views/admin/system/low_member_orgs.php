<?php
declare(strict_types=1);

use App\Support\LowMemberOrgPolicy;

$groups = is_array($lowOrgsGroups ?? null) ? $lowOrgsGroups : [];
$schemaReady = !empty($lowOrgsSchemaReady);
$pageError = isset($lowOrgsError) ? (string) $lowOrgsError : '';
$h = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
$day = static function (mixed $raw): string {
    $ts = \App\Support\UtcDateTime::timestamp($raw);

    return $ts !== null ? date('d/m/Y', $ts) : '—';
};
$ok = \App\Core\Session::getFlash('success');
$err = \App\Core\Session::getFlash('error');
$stageLabels = [LowMemberOrgPolicy::STAGE_FIRST => 'Rappel 3 mois', LowMemberOrgPolicy::STAGE_SECOND => 'Relance 4 mois'];
$sections = [
    'ready' => ['À supprimer', 'Toujours un ou deux membres ' . LowMemberOrgPolicy::DELETION_GRACE_DAYS . ' jours après la relance. Supprimer ouvre le retrait habituel : archivage, puis suppression définitive après sauvegarde, chaque étape avec confirmation.'],
    'warned' => ['Rappels en cours', 'Créées depuis plus de trois mois avec un ou deux membres : les responsables ont reçu (ou vont recevoir au prochain passage) le rappel, puis la relance.'],
    'archived' => ['Archivées après les rappels', 'Fermées mais pas encore supprimées définitivement.'],
    'kept' => ['Gardées', 'Plus de rappel ni d’alerte pour ces communautés.'],
];
$renderTable = static function (string $key, array $rows) use ($h, $day, $stageLabels): void {
    ?>
    <div class="pa-table-wrap">
        <table class="pa-table">
            <thead>
                <tr><th>Organisation</th><th>Créée le</th><th>Membres</th><th>Dernière activité</th><th>Rappels envoyés</th><th></th></tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $r): ?>
                    <?php $id = (int) $r['id']; ?>
                    <tr>
                        <td>
                            <span class="pa-name"><?= $h((string) $r['name']) ?></span>
                            <span class="pa-sub"><?= $h((string) $r['slug']) ?> · n° <?= $id ?></span>
                        </td>
                        <td><?= $h($day($r['created_at'])) ?><span class="pa-sub"><?= (int) $r['age_days'] ?> jours</span></td>
                        <td><?= (int) $r['members'] ?></td>
                        <td><?= $h($day($r['last_activity'])) ?></td>
                        <td>
                            <?php if ($r['reminders'] === []): ?>
                                <span class="pa-sub">Au prochain passage</span>
                            <?php else: ?>
                                <?php foreach ($r['reminders'] as $stage => $at): ?>
                                    <span class="pa-pill pa-pill--<?= $stage === LowMemberOrgPolicy::STAGE_SECOND ? 'amber' : 'slate' ?>"><?= $h($stageLabels[$stage] ?? (string) $stage) ?> · <?= $h($day($at)) ?></span>
                                <?php endforeach; ?>
                            <?php endif; ?>
                            <?php if ($key === 'kept' && !empty($r['kept_at'])): ?>
                                <span class="pa-sub">Gardée le <?= $h($day($r['kept_at'])) ?></span>
                            <?php endif; ?>
                        </td>
                        <td style="text-align:right;white-space:nowrap;">
                            <?php if ($key !== 'kept'): ?>
                                <a class="pa-btn pa-btn--danger pa-btn--sm" href="<?= $h(url('admin/tenants/' . $id . '/retrait')) ?>">Supprimer</a>
                                <form method="post" action="<?= $h(url('admin/organisations-a-supprimer/' . $id . '/garder')) ?>" style="display:inline;"
                                      onsubmit="return confirm('Garder « <?= $h(addslashes((string) $r['name'])) ?> » ? Plus aucun rappel ni alerte pour elle.');">
                                    <?= \App\Core\Csrf::field() ?>
                                    <button type="submit" class="pa-btn pa-btn--line pa-btn--sm">Ignorer / garder</button>
                                </form>
                            <?php else: ?>
                                <form method="post" action="<?= $h(url('admin/organisations-a-supprimer/' . $id . '/reprendre')) ?>" style="display:inline;">
                                    <?= \App\Core\Csrf::field() ?>
                                    <button type="submit" class="pa-btn pa-btn--line pa-btn--sm">Remettre dans le suivi</button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php
};
?>
<div class="pa">
    <div class="pa__frame">
        <header class="pa-hero">
            <p class="pa-crumb">
                <a href="<?= $h(url('admin')) ?>">Administration du site</a>
                <span aria-hidden="true"> / </span>
                <a href="<?= $h(url('admin/tenants')) ?>">Communautés</a>
                <span aria-hidden="true"> / </span>
                Organisations à supprimer
            </p>
            <h1 class="pa-hero__title">Organisations à supprimer</h1>
            <p class="pa-hero__lead">
                Communautés qui ne comptent qu’un ou deux membres actifs trois mois après leur création. Leurs responsables reçoivent un rappel
                à 3 mois puis une relance à 4 mois (e-mail et notification sur le site, une seule fois chacun). Rien n’est supprimé automatiquement.
            </p>
            <div class="pa-hero__actions">
                <a class="pa-btn pa-btn--ghost" href="<?= $h(url('admin/tenants')) ?>">Annuaire des communautés</a>
                <a class="pa-btn pa-btn--ghost" href="<?= $h(url('admin/system/cron')) ?>">Tâches automatiques</a>
            </div>
        </header>

        <div class="pa-panel">
            <?php if ($ok): ?>
                <p class="pa-flash pa-flash--ok"><?= $h((string) $ok) ?></p>
            <?php endif; ?>
            <?php if ($err): ?>
                <p class="pa-flash pa-flash--err"><?= $h((string) $err) ?></p>
            <?php endif; ?>
            <?php if (!$schemaReady): ?>
                <p class="pa-flash pa-flash--warn">Cette liste arrive avec la prochaine mise à jour de la base de données.</p>
            <?php elseif ($pageError !== ''): ?>
                <p class="pa-flash pa-flash--warn"><?= $h($pageError) ?></p>
            <?php else: ?>
                <?php foreach ($sections as $key => [$label, $help]): ?>
                    <?php $rows = is_array($groups[$key] ?? null) ? $groups[$key] : []; ?>
                    <?php if ($rows === [] && $key !== 'ready') {
                        continue;
                    } ?>
                    <section class="pa-card" style="margin-top:1.25rem;" aria-labelledby="low-orgs-<?= $h($key) ?>">
                        <h2 id="low-orgs-<?= $h($key) ?>" class="pa-card__title"><?= $h($label) ?> (<?= count($rows) ?>)</h2>
                        <p class="pa-card__help"><?= $h($help) ?></p>
                        <?php if ($rows === []): ?>
                            <p class="pa-card__help">Aucune organisation pour le moment.</p>
                        <?php else: ?>
                            <?php $renderTable($key, $rows); ?>
                        <?php endif; ?>
                    </section>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>
