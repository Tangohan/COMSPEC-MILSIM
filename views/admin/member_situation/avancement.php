<?php
require dirname(__DIR__) . '/advancement/_helpers.php';
$h = static fn (mixed $v): string => htmlspecialchars(trim((string) $v), ENT_QUOTES, 'UTF-8');
$panel = is_array($panel ?? null) ? $panel : [];
$current = is_array($panel['current'] ?? null) ? $panel['current'] : null;
$history = is_array($panel['history'] ?? null) ? $panel['history'] : [];
$offer = is_array($panel['offer'] ?? null) ? $panel['offer'] : null;
$next = is_array($panel['next'] ?? null) ? $panel['next'] : null;
$opinions = is_array($panel['opinions'] ?? null) ? $panel['opinions'] : [];
$mobilityRequests = is_array($mobilityRequests ?? null) ? $mobilityRequests : [];
$units = is_array($assignmentUnits ?? null) ? $assignmentUnits : [];
$mobilityReady = !empty($mobilityReady);
$typeLabels = is_array($mobilityTypeLabels ?? null) ? $mobilityTypeLabels : [];
$statusLabels = is_array($mobilityStatusLabels ?? null) ? $mobilityStatusLabels : [];
$via = [
    'initial' => 'Grade initial',
    'anciennete' => 'Ancienneté',
    'choix' => 'Choix',
    'exception' => 'Passage exceptionnel',
];
$modeLabel = [
    'automatic' => 'Automatique à l’ancienneté',
    'choice' => 'Au choix (demande)',
    'both' => 'Automatique ou au choix',
    'none' => 'Aucune voie ouverte',
];
$format = static function (string $iso) use ($h): string {
    $ts = strtotime(substr($iso, 0, 10));

    return $ts !== false ? $h(date('d/m/Y', $ts)) : $h($iso);
};
$pendingAssignment = false;
$pendingAdvancement = false;
foreach ($mobilityRequests as $row) {
    if ((string) ($row['status'] ?? '') !== 'pending') {
        continue;
    }
    $t = (string) ($row['request_type'] ?? '');
    if (in_array($t, ['assignment', 'unit_change', 'job_application'], true)) {
        $pendingAssignment = true;
    }
    if (in_array($t, ['advancement', 'career_wish'], true)) {
        $pendingAdvancement = true;
    }
}
?>
<div class="bo-member-situation adv-page">
    <?php if (!empty($success)): ?><p class="adv-flash adv-flash--ok"><?= $h($success) ?></p><?php endif; ?>
    <?php if (!empty($error)): ?><p class="adv-flash adv-flash--bad"><?= $h($error) ?></p><?php endif; ?>

    <section class="adv-panel">
        <p class="adv-kicker">Grade actuel <?= adv_info('Grade actuel', 'Ligne d’historique ouverte : elle n’est jamais réécrite. Un nouveau grade ajoute une ligne et clôture la précédente.') ?></p>
        <?php if ($current === null): ?>
            <h2>Aucun grade enregistré</h2>
            <p>Le grade de la fiche (dossier personnel) est repris ici dès qu’il est renseigné. Sinon, une ligne d’historique s’ouvre à la première attribution.</p>
        <?php else: ?>
            <h2><?= $h((string) ($current['label'] ?? '')) ?></h2>
            <p>Depuis le <?= $format((string) ($current['obtained_at'] ?? '')) ?> · <?= $h($via[(string) ($current['obtained_via'] ?? '')] ?? (string) ($current['obtained_via'] ?? '')) ?></p>
        <?php endif; ?>
    </section>

    <section class="adv-offer<?= $next === null ? ' adv-offer--muted' : '' ?>">
        <p class="adv-kicker">Prochain grade</p>
        <?php if ($next === null): ?>
            <h2>Pas de grade suivant sur l’échelle</h2>
            <p>Soit vous êtes au sommet de la filière, soit l’échelle n’est pas encore renseignée pour votre communauté.</p>
        <?php else: ?>
            <h2><?= $h((string) ($next['grade_label'] ?? '')) ?></h2>
            <p>
                <span class="adv-badge <?= !empty($next['automatic']) ? 'adv-badge--ok' : '' ?>"><?= $h($modeLabel[(string) ($next['mode'] ?? 'none')] ?? '') ?></span>
                <?php if (!empty($next['due_on'])): ?>
                    · Échéance le <?= $format((string) $next['due_on']) ?>
                <?php endif; ?>
                <?php if (isset($next['months_required']) && $next['months_required'] !== null && $next['months_required'] !== ''): ?>
                    · <?= (int) $next['months_in_grade'] ?> / <?= (int) $next['months_required'] ?> mois de grade
                <?php endif; ?>
            </p>
            <?php if (!empty($next['automatic'])): ?>
                <?php if (!empty($next['seniority_eligible'])): ?>
                    <p>Les conditions d’ancienneté sont réunies : le passage est automatique, sans demande.</p>
                <?php else: ?>
                    <p><?= $h((string) ($next['seniority_reason'] ?? 'L’avancement automatique n’est pas encore acquis.')) ?></p>
                <?php endif; ?>
            <?php endif; ?>
            <?php $conditions = is_array($next['conditions'] ?? null) ? $next['conditions'] : []; ?>
            <?php if ($conditions !== []): ?>
                <h3>Conditions d’éligibilité</h3>
                <ul class="adv-conditions">
                    <?php foreach ($conditions as $cond): ?>
                        <li class="<?= !empty($cond['met']) ? 'is-met' : 'is-wait' ?>">
                            <strong><?= $h((string) ($cond['label'] ?? '')) ?></strong>
                            <span><?= $h((string) ($cond['detail'] ?? '')) ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>

            <?php if (!empty($next['choice']) && empty($next['seniority_eligible'])): ?>
                <?php if (!empty($next['already_volunteered'])): ?>
                    <p>Votre candidature au tableau d’avancement est déjà enregistrée.</p>
                <?php elseif (!empty($next['campaign_id'])): ?>
                    <form method="post" class="adv-form" action="<?= $h(url('back-office/ma-situation/avancement/' . (int) $next['campaign_id'] . '/volontaire')) ?>">
                        <?= \App\Core\Csrf::field() ?>
                        <label>
                            Motivation (facultatif)
                            <textarea name="notes" rows="2" maxlength="2000" placeholder="Précisez un poste ou un souhait d’affectation."></textarea>
                        </label>
                        <label class="adv-check"><input type="checkbox" name="mobility_requested" value="1"> Je demande aussi une mobilité</label>
                        <button class="ath-btn ath-btn--solid" type="submit">Demander l’avancement</button>
                    </form>
                <?php else: ?>
                    <form method="post" class="adv-form" action="<?= $h(url('back-office/ma-situation/avancement/demande')) ?>">
                        <?= \App\Core\Csrf::field() ?>
                        <input type="hidden" name="target_label" value="<?= $h((string) ($next['grade_label'] ?? '')) ?>">
                        <label>
                            Motivation
                            <textarea name="notes" rows="2" maxlength="2000" placeholder="Pourquoi demander ce grade maintenant ?"<?= $pendingAdvancement ? ' disabled' : '' ?>></textarea>
                        </label>
                        <?php if ($pendingAdvancement): ?>
                            <p>Une demande d’avancement est déjà en attente.</p>
                        <?php else: ?>
                            <button class="ath-btn ath-btn--solid" type="submit">Demander l’avancement</button>
                        <?php endif; ?>
                    </form>
                <?php endif; ?>
            <?php elseif (empty($next['automatic'])): ?>
                <p>Aucune voie d’avancement n’est ouverte pour ce grade.</p>
            <?php endif; ?>
        <?php endif; ?>
    </section>

    <section class="adv-panel">
        <p class="adv-kicker">Demandes</p>
        <h2>Demande d’affectation</h2>
        <p>Unité ou poste visé. L’encadrement traite la demande dans la mobilité interne.</p>
        <?php if (!$mobilityReady): ?>
            <p>Les demandes d’affectation ne sont pas encore disponibles.</p>
        <?php elseif ($pendingAssignment): ?>
            <p>Une demande d’affectation est déjà en attente.</p>
        <?php else: ?>
            <form method="post" class="adv-form" action="<?= $h(url('back-office/ma-situation/avancement/affectation')) ?>">
                <?= \App\Core\Csrf::field() ?>
                <?php if ($units !== []): ?>
                    <label>
                        Unité visée
                        <select name="target_unit_id">
                            <option value="">— Choisir —</option>
                            <?php foreach ($units as $unit): ?>
                                <option value="<?= (int) ($unit['id'] ?? 0) ?>"><?= $h((string) ($unit['name'] ?? '')) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                <?php endif; ?>
                <label>
                    Poste / libellé
                    <input type="text" name="target_label" maxlength="200" placeholder="Ex. Chef d’équipe, radio…">
                </label>
                <label>
                    Motivation
                    <textarea name="motivation" rows="2" maxlength="2000" placeholder="Pourquoi cette affectation ?"></textarea>
                </label>
                <button class="ath-btn ath-btn--solid" type="submit">Déposer la demande d’affectation</button>
            </form>
        <?php endif; ?>

        <?php if ($mobilityRequests !== []): ?>
            <h3>Mes demandes</h3>
            <ul class="adv-timeline">
                <?php foreach ($mobilityRequests as $row): ?>
                    <li>
                        <strong><?= $h((string) ($typeLabels[$row['request_type'] ?? ''] ?? $row['request_type'] ?? '')) ?></strong>
                        <span><?= $h((string) ($statusLabels[$row['status'] ?? ''] ?? $row['status'] ?? '')) ?><?php $tl = trim((string) ($row['target_label'] ?? '')); if ($tl !== ''): ?> · <?= $h($tl) ?><?php endif; ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>

    <section class="adv-panel">
        <p class="adv-kicker">Commandement</p>
        <h2>Avis de commandement</h2>
        <?php if ($opinions === []): ?>
            <p>Aucun avis pour l’instant. Ils apparaissent dès qu’une commission se prononce sur votre candidature.</p>
        <?php else: ?>
            <ul class="adv-timeline">
                <?php foreach ($opinions as $row): ?>
                    <li>
                        <strong><?= $h((string) ($row['grade_label'] ?? 'Avancement')) ?><?php if ((int) ($row['year'] ?? 0) > 0): ?> · <?= (int) $row['year'] ?><?php endif; ?></strong>
                        <span>
                            <?= !empty($row['pending']) ? 'Avis non encore rendu' : $h((string) ($row['opinion_label'] ?? '—')) ?>
                            · <?= $h((string) ($row['decision_label'] ?? '—')) ?>
                        </span>
                        <?php if (trim((string) ($row['notes'] ?? '')) !== ''): ?>
                            <span><?= $h((string) $row['notes']) ?></span>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>

    <?php if ($offer !== null && $next === null): ?>
        <section class="adv-offer">
            <?php if (!empty($offer['is_eligible'])): ?>
                <h2>Vous êtes éligible à l’avancement au grade de <?= $h((string) ($offer['grade_label'] ?? '')) ?></h2>
            <?php else: ?>
                <h2>Campagne ouverte pour <?= $h((string) ($offer['grade_label'] ?? 'le grade suivant')) ?></h2>
                <p><?= $h((string) ($offer['eligibility_reason'] ?? 'Les conditions ne sont pas réunies.')) ?></p>
            <?php endif; ?>
            <?php if (!empty($offer['already_volunteered'])): ?>
                <p>Votre candidature est déjà enregistrée.</p>
            <?php elseif (!empty($offer['is_eligible'])): ?>
                <form method="post" action="<?= $h(url('back-office/ma-situation/avancement/' . (int) $offer['campaign_id'] . '/volontaire')) ?>">
                    <?= \App\Core\Csrf::field() ?>
                    <button class="ath-btn ath-btn--solid" type="submit">Me porter volontaire</button>
                </form>
            <?php endif; ?>
        </section>
    <?php endif; ?>

    <section class="adv-panel">
        <h2>Historique <?= adv_info('Historique', 'Chaque promotion (initial, ancienneté, choix ou exception) reste une ligne. On corrige en ajoutant, pas en écrasant.') ?></h2>
        <?php if ($history === []): ?>
            <p>Aucune ligne pour l’instant.</p>
        <?php else: ?>
            <ol class="adv-timeline">
                <?php foreach ($history as $row): ?>
                    <li>
                        <strong><?= $h((string) ($row['label'] ?? '')) ?></strong>
                        <span><?= $format((string) ($row['obtained_at'] ?? '')) ?><?php if (!empty($row['ends_at'])): ?> — <?= $format((string) $row['ends_at']) ?><?php else: ?> — en cours<?php endif; ?></span>
                        <span><?= $h($via[(string) ($row['obtained_via'] ?? '')] ?? '') ?></span>
                    </li>
                <?php endforeach; ?>
            </ol>
        <?php endif; ?>
    </section>
</div>
