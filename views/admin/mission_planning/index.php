<?php
declare(strict_types=1);

use App\Support\MissionPlanningLabels;

/** @var bool $mpReady */
/** @var list<array<string,mixed>> $mpPlans */
/** @var list<array<string,mixed>> $mpEvents */
/** @var list<array<string,mixed>> $mpMaps */

$ready = !empty($mpReady);
$plans = is_array($mpPlans ?? null) ? $mpPlans : [];
$events = is_array($mpEvents ?? null) ? $mpEvents : [];
$maps = is_array($mpMaps ?? null) ? $mpMaps : [];
$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');

$draft = 0;
$live = 0;
$assigned = 0;
$auth = 0;
foreach ($plans as $p) {
    $st = (string) ($p['status'] ?? '');
    if ($st === 'draft') {
        $draft++;
    }
    if ($st === 'live') {
        $live++;
    }
    $assigned += (int) ($p['assigned_count'] ?? 0);
    $auth += (int) ($p['auth_count'] ?? 0);
}

$athKpis = [
    ['label' => 'PLANS', 'value' => (string) count($plans), 'delta' => '', 'tone' => '#1e4f80', 'pct' => '100%', 'note' => 'fiches ouvertes'],
    ['label' => 'BROUILLONS', 'value' => (string) $draft, 'delta' => '', 'tone' => '#8c979b', 'pct' => count($plans) ? (int) round($draft / count($plans) * 100) . '%' : '0%', 'note' => 'en préparation'],
    ['label' => 'EN SESSION', 'value' => (string) $live, 'delta' => '', 'tone' => '#0b8a5c', 'pct' => $live > 0 ? '100%' : '0%', 'note' => 'synchronisation Arma'],
    ['label' => 'POSTES AFFECTÉS', 'value' => $assigned . ' / ' . $auth, 'delta' => '', 'tone' => '#1e4f80', 'pct' => $auth > 0 ? (int) round($assigned / $auth * 100) . '%' : '0%', 'note' => 'tous plans confondus'],
];
require base_path('views/partials/ath_kpis.php');

$s = \App\Core\Session::getFlash('success');
$e = \App\Core\Session::getFlash('error');
?>
<?php if ($s): ?><div class="ath-banner-warn ath-rise" style="background:#e6f8f0;border-color:#bfe9d8;" role="status"><div class="ath-banner-warn__text" style="color:#0b6b47;"><?= $h($s) ?></div></div><?php endif; ?>
<?php if ($e): ?><div class="ath-banner-warn ath-rise" role="alert"><div class="ath-banner-warn__text"><?= $h($e) ?></div></div><?php endif; ?>

<?php if (!$ready): ?>
<div class="ath-card ath-rise" style="padding:18px 20px;">
    <p>La planification de mission n’est pas encore installée sur cette communauté. Exécutez les mises à jour de la base, puis revenez ici.</p>
</div>
<?php else: ?>
<form method="post" action="<?= $h(url('back-office/planification')) ?>" class="ath-card ath-rise" id="nouveau-plan" style="padding:18px 20px;margin-bottom:16px;">
    <div style="font-size:9px;font-weight:800;letter-spacing:0.18em;color:#8c979b;margin-bottom:12px;">NOUVEAU PLAN DE MISSION</div>
    <input type="hidden" name="_csrf_token" value="<?= $h(\App\Core\Csrf::token()) ?>">
    <div class="mp-form-grid">
        <div>
            <label class="ath-users-filters__label" for="mp-title">Nom de la mission</label>
            <input id="mp-title" type="text" name="title" required class="bo-select" style="height:40px;width:100%;" placeholder="Ex. Voile de fer">
        </div>
        <div>
            <label class="ath-users-filters__label" for="mp-op">Nom d’opération</label>
            <input id="mp-op" type="text" name="operation_name" class="bo-select" style="height:40px;width:100%;" placeholder="Ex. IRON VEIL">
        </div>
        <div>
            <label class="ath-users-filters__label" for="mp-tf">Force</label>
            <input id="mp-tf" type="text" name="task_force_name" value="TF DAGGER" class="bo-select" style="height:40px;width:100%;">
        </div>
        <div>
            <label class="ath-users-filters__label" for="mp-code">Identifiant mission</label>
            <input id="mp-code" type="text" name="mission_code" class="bo-select" style="height:40px;width:100%;" placeholder="Laissé vide : généré automatiquement">
        </div>
        <div>
            <label class="ath-users-filters__label" for="mp-dtg">Horodatage</label>
            <input id="mp-dtg" type="text" name="dtg" class="bo-select" style="height:40px;width:100%;" placeholder="Laissé vide : horodatage du moment">
        </div>
        <div>
            <label class="ath-users-filters__label" for="mp-event">Événement lié</label>
            <select id="mp-event" name="event_id" class="bo-select">
                <option value="">Aucun pour l’instant</option>
                <?php foreach ($events as $ev): ?>
                    <option value="<?= (int) ($ev['id'] ?? 0) ?>"><?= $h($ev['title'] ?? 'Créneau') ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="ath-users-filters__label" for="mp-map">Carte</label>
            <select id="mp-map" name="map_id" class="bo-select">
                <option value="">Non précisée</option>
                <?php foreach ($maps as $map): ?>
                    <option value="<?= (int) ($map['id'] ?? 0) ?>"><?= $h($map['label'] ?? $map['slug'] ?? 'Carte') ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>
    <button type="submit" class="ath-btn ath-btn--solid" style="margin-top:14px;">Créer le plan</button>
</form>

<div class="ath-table-panel ath-rise">
    <div class="ath-table-toolbar">
        <span class="ath-table-toolbar__title">Plans de mission</span>
        <span class="ath-table-toolbar__count"><?= count($plans) ?> plan<?= count($plans) > 1 ? 's' : '' ?></span>
    </div>
    <?php if ($plans === []): ?>
        <div class="ath-table-empty">Aucun plan pour le moment. Créez-en un pour préparer l’organisation de combat avant la session.</div>
    <?php else: ?>
        <div class="ath-table-wrap">
            <table class="ath-table">
                <thead>
                    <tr>
                        <th scope="col">Mission</th>
                        <th scope="col">Force</th>
                        <th scope="col">Événement</th>
                        <th scope="col">Effectifs</th>
                        <th scope="col">État</th>
                        <th scope="col">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($plans as $p):
                        $pid = (int) ($p['id'] ?? 0);
                        ?>
                        <tr>
                            <td>
                                <strong><?= $h($p['operation_name'] ?: $p['title']) ?></strong>
                                <div class="mp-muted"><?= $h($p['mission_code'] ?? '') ?> · <?= $h($p['dtg'] ?? '') ?></div>
                            </td>
                            <td><?= $h($p['task_force_name'] ?? '') ?></td>
                            <td><?= $h($p['event_title'] ?? '—') ?></td>
                            <td><?= (int) ($p['assigned_count'] ?? 0) ?> / <?= (int) ($p['auth_count'] ?? 0) ?></td>
                            <td><?= $h($p['status_label'] ?? MissionPlanningLabels::status((string) ($p['status'] ?? 'draft'))) ?></td>
                            <td><a class="ath-btn" href="<?= $h(url('back-office/planification/' . $pid)) ?>">Ouvrir</a></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
<?php endif; ?>
