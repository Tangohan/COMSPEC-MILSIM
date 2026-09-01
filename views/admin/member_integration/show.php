<?php
declare(strict_types=1);

/** @var array<string,mixed> $integration */
/** @var list<array<string,mixed>> $steps */
/** @var list<array<string,mixed>> $events */
/** @var list<array<string,mixed>> $referents */
/** @var list<array<string,mixed>> $appointments */
/** @var list<array<string,mixed>> $matricesAssigned */
/** @var list<array<string,mixed>> $matricesAll */
/** @var array<string,mixed> $dossier */
/** @var list<array<string,mixed>> $staff */
/** @var array<string,string> $statusLabels */
/** @var array<string,string> $stepTypeLabels */

$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$row = is_array($integration ?? null) ? $integration : [];
$id = (int) ($row['id'] ?? 0);
$userId = (int) ($row['user_id'] ?? 0);
$score = is_array($dossier['score'] ?? null) ? $dossier['score'] : [];
$missing = is_array($score['missing_labels'] ?? null) ? $score['missing_labels'] : [];
$crit = is_array($score['sections_critiques'] ?? null) ? $score['sections_critiques'] : [];
$pct = (int) ($row['progress_percent'] ?? ($score['percent'] ?? 0));
?>
<link href="<?= $h(asset_url('assets/css/member-integration.css')) ?>" rel="stylesheet">

<p><a href="<?= $h(url('back-office/integration-membres')) ?>">← Tous les parcours</a></p>

<header class="mi-panel" style="margin-bottom:1rem">
    <h1 style="margin:0 0 .35rem"><?= $h($row['display_name'] ?? 'Parcours d’intégration') ?></h1>
    <p class="mi-muted">
        <?= $h($statusLabels[(string) ($row['status'] ?? '')] ?? '') ?>
        · <?= $pct ?> % des étapes obligatoires
        · <?= !empty($row['dossier_complete']) ? 'Dossier complet' : 'Dossier à compléter' ?>
    </p>
    <div class="mi-progress" style="margin-top:.5rem"><span style="width:<?= max(0, min(100, $pct)) ?>%"></span></div>
    <p class="mi-actions">
        <a class="mi-btn mi-btn--ghost" href="<?= $h(url('personnel/' . $userId)) ?>">Ouvrir la fiche personnelle</a>
    </p>
</header>

<div class="mi-grid mi-grid--2">
    <div>
        <section class="mi-panel">
            <h2>Étapes</h2>
            <?php foreach ($steps as $st): ?>
                <div class="mi-step">
                    <strong><?= $h($st['title'] ?? '') ?></strong>
                    <span class="mi-badge"><?= $h($stepTypeLabels[(string) ($st['step_type'] ?? '')] ?? '') ?></span>
                    <p class="mi-muted"><?= $h($st['status'] ?? '') ?><?= !empty($st['is_required']) ? ' · obligatoire' : ' · facultative' ?></p>
                    <?php if (!empty($canAssign)): ?>
                        <form method="post" action="<?= $h(url('back-office/integration-membres/' . $id . '/etape')) ?>" class="mi-actions">
                            <?= \App\Core\Csrf::field() ?>
                            <input type="hidden" name="step_id" value="<?= (int) ($st['id'] ?? 0) ?>">
                            <button class="mi-btn" type="submit">Valider</button>
                        </form>
                        <form method="post" action="<?= $h(url('back-office/integration-membres/' . $id . '/etape')) ?>" class="mi-form">
                            <?= \App\Core\Csrf::field() ?>
                            <input type="hidden" name="step_id" value="<?= (int) ($st['id'] ?? 0) ?>">
                            <input type="hidden" name="force" value="1">
                            <label>Valider malgré tout (motif obligatoire)
                                <input name="reason" required placeholder="Motif visible pour l’encadrement">
                            </label>
                            <button class="mi-btn mi-btn--warn" type="submit">Forcer la validation</button>
                        </form>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </section>

        <section class="mi-panel" style="margin-top:1rem">
            <h2>Journal</h2>
            <?php foreach ($events as $ev): ?>
                <p class="mi-step">
                    <span class="mi-badge"><?= $h($ev['visibility'] ?? '') === 'member' ? 'Visible du membre' : 'Interne' ?></span>
                    <?= $h($ev['message'] ?? $ev['body'] ?? '') ?>
                    <span class="mi-muted"> · <?= $h($ev['created_at'] ?? '') ?></span>
                </p>
            <?php endforeach; ?>
            <?php if (!empty($canNote)): ?>
                <form method="post" action="<?= $h(url('back-office/integration-membres/' . $id . '/note')) ?>" class="mi-form">
                    <?= \App\Core\Csrf::field() ?>
                    <label>Note
                        <textarea name="message" required rows="3"></textarea>
                    </label>
                    <label><input type="checkbox" name="visible_member" value="1"> Visible par le membre</label>
                    <button class="mi-btn" type="submit">Enregistrer</button>
                </form>
            <?php endif; ?>
        </section>
    </div>
    <div>
        <section class="mi-panel">
            <h2>Dossier personnel</h2>
            <p><?= (int) ($score['score'] ?? 0) ?> % complété</p>
            <?php if ($crit !== []): ?>
                <p class="mi-muted">Sections encore nécessaires :</p>
                <ul><?php foreach ($crit as $c): ?><li><?= $h(is_array($c) ? ($c['label'] ?? '') : $c) ?></li><?php endforeach; ?></ul>
            <?php endif; ?>
            <?php if ($missing !== []): ?>
                <p class="mi-muted">Éléments manquants :</p>
                <ul><?php foreach ($missing as $m): ?><li><?= $h(is_array($m) ? ($m['label'] ?? '') : $m) ?></li><?php endforeach; ?></ul>
            <?php endif; ?>
            <?php $bilans = is_array($dossier['bilans'] ?? null) ? $dossier['bilans'] : []; ?>
            <?php if ($bilans !== []): ?>
                <h3 style="font-size:.9rem">Bilans d’étape</h3>
                <ul><?php foreach ($bilans as $b): ?><li><?= $h($b['stage_label'] ?? $b['title'] ?? 'Bilan') ?> · <?= $h($b['created_at'] ?? '') ?></li><?php endforeach; ?></ul>
            <?php endif; ?>
            <?php $rb = is_array($dossier['recruitment_bilans'] ?? null) ? $dossier['recruitment_bilans'] : []; ?>
            <?php if ($rb !== []): ?>
                <h3 style="font-size:.9rem">Bilans de candidature</h3>
                <ul><?php foreach ($rb as $b): ?><li><?= $h($b['feedback_scope'] ?? 'Bilan') ?> · <?= $h($b['submitted_at'] ?? $b['created_at'] ?? '') ?></li><?php endforeach; ?></ul>
            <?php endif; ?>
        </section>

        <section class="mi-panel" style="margin-top:1rem">
            <h2>Référents</h2>
            <ul>
                <?php foreach ($referents as $ref): ?>
                    <li><?= $h($ref['display_name'] ?? $ref['email'] ?? '') ?><?= !empty($ref['is_primary']) ? ' (principal)' : '' ?></li>
                <?php endforeach; ?>
            </ul>
            <?php if (!empty($canAssign)): ?>
                <form method="post" action="<?= $h(url('back-office/integration-membres/' . $id . '/referents')) ?>" class="mi-form">
                    <?= \App\Core\Csrf::field() ?>
                    <label>Référent principal
                        <select name="primary_referent_user_id">
                            <option value="0">Aucun</option>
                            <?php foreach ($staff as $s): ?>
                                <option value="<?= (int) ($s['id'] ?? 0) ?>"><?= $h($s['display_name'] ?? $s['email'] ?? '') ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <button class="mi-btn" type="submit">Mettre à jour</button>
                </form>
            <?php endif; ?>
        </section>

        <section class="mi-panel" style="margin-top:1rem">
            <h2>Groupes de suivi</h2>
            <ul>
                <?php foreach ($matricesAssigned as $m): ?>
                    <li><?= $h($m['name'] ?? '') ?></li>
                <?php endforeach; ?>
                <?php if ($matricesAssigned === []): ?><li class="mi-muted">Aucun groupe pour l’instant.</li><?php endif; ?>
            </ul>
            <?php if (!empty($canAssign)): ?>
                <form method="post" action="<?= $h(url('back-office/integration-membres/' . $id . '/groupe')) ?>" class="mi-form">
                    <?= \App\Core\Csrf::field() ?>
                    <label>Ajouter à un groupe
                        <select name="matrix_id">
                            <?php foreach ($matricesAll as $m): ?>
                                <option value="<?= (int) ($m['id'] ?? 0) ?>"><?= $h($m['name'] ?? '') ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <input type="hidden" name="action" value="assign">
                    <button class="mi-btn" type="submit">Ajouter</button>
                </form>
            <?php endif; ?>
        </section>

        <section class="mi-panel" style="margin-top:1rem">
            <h2>Rendez-vous</h2>
            <ul>
                <?php foreach ($appointments as $a): ?>
                    <li>
                        <?= $h($a['title'] ?? '') ?> · <?= $h($a['starts_at'] ?? '') ?>
                        <a href="<?= $h(url('back-office/integration-membres/rendez-vous/' . (int) ($a['id'] ?? 0) . '/calendrier')) ?>">Ajouter au calendrier</a>
                    </li>
                <?php endforeach; ?>
            </ul>
            <?php if (!empty($canAssign)): ?>
                <form method="post" action="<?= $h(url('back-office/integration-membres/' . $id . '/rendez-vous')) ?>" class="mi-form">
                    <?= \App\Core\Csrf::field() ?>
                    <label>Titre <input name="title" required></label>
                    <label>Début <input type="datetime-local" name="starts_at" required></label>
                    <label>Fin <input type="datetime-local" name="ends_at" required></label>
                    <label>Lieu <input name="location"></label>
                    <label>Message personnel <textarea name="personal_message" rows="2"></textarea></label>
                    <button class="mi-btn" type="submit">Inviter le membre</button>
                </form>
            <?php endif; ?>
        </section>

        <?php if (!empty($canManage)): ?>
            <form method="post" action="<?= $h(url('back-office/integration-membres/' . $id . '/annuler')) ?>" class="mi-form mi-panel" style="margin-top:1rem">
                <?= \App\Core\Csrf::field() ?>
                <label>Annuler le parcours (motif)
                    <input name="reason" required>
                </label>
                <button class="mi-btn mi-btn--danger" type="submit">Annuler</button>
            </form>
            <form method="post" action="<?= $h(url('back-office/integration-membres/' . $id . '/rouvrir')) ?>" class="mi-form" style="margin-top:.5rem">
                <?= \App\Core\Csrf::field() ?>
                <button class="mi-btn mi-btn--ghost" type="submit">Rouvrir</button>
            </form>
        <?php endif; ?>
    </div>
</div>
