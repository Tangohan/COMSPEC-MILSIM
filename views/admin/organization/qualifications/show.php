<?php
declare(strict_types=1);

/**
 * Fiche d'une qualification : comment l'obtenir, titulaires, propositions à valider, membres en progression,
 * délivrance à la main et historique.
 *
 * @var array<string, mixed> $definition
 * @var array<string, mixed> $board
 * @var array<string, mixed> $names
 * @var list<array<string, mixed>> $history
 */
$def = is_array($definition ?? null) ? $definition : [];
$b = is_array($board ?? null) ? $board : [];
$st = is_array($b['settings'] ?? null) ? $b['settings'] : [];
$modes = is_array($b['modes'] ?? null) ? $b['modes'] : [];
$counts = is_array($b['counts'] ?? null) ? $b['counts'] : ['ok' => 0, 'soon' => 0, 'exp' => 0, 'train' => 0];
$rule = is_array($st['rule'] ?? null) ? $st['rule'] : [];
$criterionNames = is_array($names ?? null) ? $names : [];
$history = is_array($history ?? null) ? $history : [];
$id = (int) ($def['id'] ?? 0);
$base = 'back-office/referentiels/qualifications/' . $id;
$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$fd = static function (?string $iso): string {
    $iso = trim((string) $iso);
    $ts = $iso !== '' ? strtotime(substr($iso, 0, 10)) : false;

    return $ts === false ? '—' : (int) date('j', $ts) . ' ' . \App\Support\LocalizedCalendar::month((int) date('n', $ts), true) . ' ' . date('Y', $ts);
};
$who = static function (array $m): string {
    $cs = trim((string) ($m['callsign'] ?? ''));
    $dn = trim((string) ($m['display_name'] ?? ''));

    return $cs !== '' && $dn !== '' && $cs !== $dn ? $cs . ' · ' . $dn : ($cs !== '' ? $cs : ($dn !== '' ? $dn : '#' . (int) ($m['user_id'] ?? 0)));
};
$archived = !empty($def['archived_at']);
$manual = !empty($st['allow_manual']);
$auto = !empty($st['auto_enabled']) && $rule !== [];
$short = trim((string) ($def['short_name'] ?? '')) ?: (string) ($def['code'] ?? '?');
$badgeUrl = (string) ($badgeUrl ?? '');
$months = (int) ($def['default_validity_months'] ?? 0);
$flashSuccess = \App\Core\Session::getFlash('success');
$flashError = \App\Core\Session::getFlash('error');
$hasAcademy = in_array(\App\Support\QualificationCriteriaCatalog::MODE_ACADEMY, $modes, true);
$eventLabels = [
    'awarded' => 'Délivrée',
    'renewed' => 'Renouvelée',
    'obtained' => 'Validée',
    'revoked' => 'Retirée',
    'suspended' => 'Suspendue',
    'expired' => 'Expirée',
    'status_changed' => 'Statut modifié',
    'practice' => 'Pratique enregistrée',
];
?>
<div class="qfs">
    <a class="qfs-back" href="<?= $h(url('back-office/referentiels/qualifications')) ?>">
        <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true"><path d="M15 6l-6 6 6 6"/></svg>
        Référentiel des qualifications
    </a>

    <div class="qmx-ph">
        <div class="qfs-id">
            <span class="qfs-badge"><?php if ($badgeUrl !== '' && trim((string) ($def['badge_media_path'] ?? '')) !== ''): ?><img src="<?= $h($badgeUrl) ?>" alt=""><?php else: ?><span translate="no"><?= $h(mb_substr($short, 0, 5)) ?></span><?php endif; ?></span>
            <div>
                <span class="qmx-k">Qualification<?= trim((string) ($def['category_name'] ?? '')) !== '' ? ' · ' . $h($def['category_name']) : '' ?></span>
                <h2><?= $h($def['name'] ?? '') ?></h2>
                <div class="qfs-chips">
                    <span class="qfs-chip" translate="no"><?= $h($def['code'] ?? '') ?></span>
                    <?php foreach ($modes as $mode): ?>
                        <span class="qfs-chip qfs-chip--<?= $h($mode) ?>"><?= $h(\App\Support\QualificationCriteriaCatalog::MODES[$mode]['label'] ?? $mode) ?></span>
                    <?php endforeach; ?>
                    <?php if (!empty($def['is_permanent'])): ?><span class="qfs-chip">Permanente</span><?php elseif ($months > 0): ?><span class="qfs-chip">Valable <?= $months ?> mois</span><?php endif; ?>
                    <?php if ($archived): ?><span class="qfs-chip qfs-chip--muted">Archivée</span><?php endif; ?>
                </div>
            </div>
        </div>
        <div class="qmx-acts">
            <?php if ($hasAcademy): ?><a class="qmx-btn" href="<?= $h(url('academy')) ?>">Ouvrir l’Academy</a><?php endif; ?>
            <a class="qmx-btn" href="<?= $h(effectifs_workspace_url('qualifications')) ?>">Matrice des effectifs</a>
            <?php if ($auto && !$archived): ?>
            <form method="post" action="<?= $h(url($base . '/evaluer')) ?>">
                <?= \App\Core\Csrf::field() ?>
                <button type="submit" class="qmx-btn">Évaluer maintenant</button>
            </form>
            <?php endif; ?>
            <a class="qmx-btn qmx-btn--dark" href="<?= $h(url($base . '/edit')) ?>">Modifier</a>
        </div>
    </div>

    <?php if ($flashSuccess): ?><p class="qmx-note qmx-note--ok" role="status"><?= $h($flashSuccess) ?></p><?php endif; ?>
    <?php if ($flashError): ?><p class="qmx-note qmx-note--bad" role="alert"><?= $h($flashError) ?></p><?php endif; ?>
    <?php if (empty($schemaReady)): ?><p class="qmx-note qmx-note--warn">Les modes d’obtention automatiques arrivent avec la prochaine mise à jour du site. En attendant, la qualification se délivre à la main.</p><?php endif; ?>
    <?php if ($archived): ?>
        <form class="qmx-note qmx-note--warn" method="post" action="<?= $h(url($base . '/restaurer')) ?>">
            <?= \App\Core\Csrf::field() ?>
            <span>Archivée : elle n’est plus délivrée, les titulaires la gardent dans leur historique.</span>
            <button type="submit" class="qmx-btn qmx-btn--sm">Réactiver</button>
        </form>
    <?php endif; ?>
    <?php if (trim((string) ($def['description'] ?? '')) !== ''): ?>
        <p class="qfs-lead"><?= nl2br($h($def['description'])) ?></p>
    <?php endif; ?>
    <?php if (is_array($defaultIssuer ?? null)): ?>
        <p class="qfs-lead qfs-issuer"><span>Émetteur habituel :</span> <a href="<?= $h(url('back-office/referentiels/qualifications/emetteurs/' . (int) $defaultIssuer['id'])) ?>"><b translate="no"><?= $h($defaultIssuer['name']) ?></b></a><?php if (!empty($defaultIssuer['archived_at'])): ?> <span>(archivé)</span><?php endif; ?><br><small>Son nom figure sur le brevet, y compris quand la qualification est obtenue par l’Academy ou automatiquement.</small></p>
    <?php elseif (!empty($issuerLinkReady)): ?>
        <p class="qfs-lead qfs-issuer">Aucun émetteur habituel : sans choix à la délivrance, le brevet porte le nom de la communauté. <a href="<?= $h(url('back-office/referentiels/qualifications/emetteurs')) ?>">Rattacher un émetteur</a></p>
    <?php endif; ?>

    <div class="qmx-stats qmx-stats--static">
        <?php foreach ([
            ['Titulaires valides', (int) $counts['ok'] + (int) $counts['soon'], 'qualification en cours de validité', 'var(--qx-ok)', ''],
            ['À renouveler', (int) $counts['soon'], 'expirent bientôt ou en période de grâce', 'var(--qx-warn)', (int) $counts['soon'] > 0 ? 'warn' : ''],
            ['Expirées', (int) $counts['exp'], 'à repasser', 'var(--qx-bad)', (int) $counts['exp'] > 0 ? 'bad' : ''],
            ['En cours', (int) $counts['train'], 'en formation, à valider ou en progression', 'var(--qx-info)', ''],
        ] as [$label, $value, $sub, $dot, $tone]): ?>
        <div class="qmx-stat">
            <span class="qmx-k"><span class="qmx-dot" style="background:<?= $h($dot) ?>"></span><?= $h($label) ?></span>
            <b class="<?= $h($tone) ?>"><?= (int) $value ?></b>
            <small><?= $h($sub) ?></small>
        </div>
        <?php endforeach; ?>
    </div>

    <div class="qfs-grid">
        <div class="qfs-col">
            <?php if (!empty($b['pending'])): ?>
            <section class="qfs-card" id="a-valider" aria-labelledby="qfs-pending">
                <h3 id="qfs-pending">À valider <span class="qfs-n"><?= count($b['pending']) ?></span></h3>
                <p class="qfs-lead">Membres proposés par la règle automatique ou mis en formation : validez ou refusez.</p>
                <ul class="qfs-list">
                    <?php foreach ($b['pending'] as $row): $award = $row['award']; ?>
                    <li>
                        <div class="qfs-who"><b translate="no"><?= $h($who($row['member'])) ?></b><small><?= $h(\App\Support\QualificationAdminStatus::label((string) \App\Services\Personnel\QualificationAwardRuleService::adminStatus($award))) ?><?= trim((string) ($award['notes'] ?? '')) !== '' ? ' · ' . $h($award['notes']) : '' ?></small></div>
                        <div class="qfs-acts">
                            <form method="post" action="<?= $h(url('back-office/referentiels/qualifications/propositions/' . (int) $award['id'])) ?>">
                                <?= \App\Core\Csrf::field() ?>
                                <input type="hidden" name="definition_id" value="<?= $id ?>">
                                <button type="submit" name="decision" value="approve" class="qmx-btn qmx-btn--sm qmx-btn--ok">Valider</button>
                                <button type="submit" name="decision" value="reject" class="qmx-btn qmx-btn--sm qmx-btn--red">Refuser</button>
                            </form>
                        </div>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </section>
            <?php endif; ?>

            <section class="qfs-card" aria-labelledby="qfs-holders">
                <h3 id="qfs-holders">Titulaires <span class="qfs-n"><?= count($b['holders'] ?? []) ?></span></h3>
                <?php if (empty($b['holders'])): ?>
                    <p class="qfs-empty">Personne ne détient encore cette qualification.</p>
                <?php else: ?>
                <ul class="qfs-list">
                    <?php foreach ($b['holders'] as $row):
                        $award = $row['award'];
                        $cell = (string) $row['cell'];
                        $src = (string) ($award['source'] ?? 'manual');
                        $srcLabel = match ($src) {
                            'training' => 'Academy',
                            'auto_rule' => 'Automatique',
                            'auto_pilot' => 'Cursus pilote',
                            default => 'À la main',
                        };
                        ?>
                    <li>
                        <div class="qfs-who">
                            <b translate="no"><a href="<?= $h(url('back-office/ressources/effectifs/membres/' . (int) $row['member']['user_id'])) ?>#qualifications"><?= $h($who($row['member'])) ?></a></b>
                            <small><span><?= $h($srcLabel) ?></span> · <span>obtenue le <?= $h($fd($award['obtained_at'] ?? null)) ?></span><?php if (trim((string) ($award['expires_at'] ?? '')) !== ''): ?> · <span>expire le <?= $h($fd($award['expires_at'])) ?></span><?php endif; ?></small>
                        </div>
                        <span class="qfs-tag qfs-tag--<?= $h($cell === 'ok' ? 'ok' : ($cell === 'soon' ? 'soon' : 'exp')) ?>"><?= $h($cell === 'ok' ? 'Valide' : ($cell === 'soon' ? 'À renouveler' : 'Expirée')) ?></span>
                        <details class="qfs-acts">
                            <summary class="qmx-btn qmx-btn--sm">Retirer</summary>
                            <form method="post" action="<?= $h(url('back-office/referentiels/qualifications/matrice/case')) ?>">
                                <?= \App\Core\Csrf::field() ?>
                                <input type="hidden" name="definition_id" value="<?= $id ?>">
                                <input type="hidden" name="user_id" value="<?= (int) $row['member']['user_id'] ?>">
                                <input type="hidden" name="target" value="none">
                                <input type="hidden" name="return_to" value="fiche">
                                <input type="text" name="reason" required maxlength="500" placeholder="Motif du retrait" aria-label="Motif du retrait">
                                <button type="submit" class="qmx-btn qmx-btn--sm qmx-btn--red">Confirmer</button>
                            </form>
                        </details>
                    </li>
                    <?php endforeach; ?>
                </ul>
                <?php endif; ?>
            </section>

            <?php if ($auto): ?>
            <section class="qfs-card" aria-labelledby="qfs-progress">
                <h3 id="qfs-progress">En progression <span class="qfs-n"><?= count($b['progress'] ?? []) ?></span></h3>
                <p class="qfs-lead">Membres qui avancent vers les seuils, du plus avancé au moins avancé. Ceux qui remplissent tout la reçoivent au prochain passage (toutes les heures) ou en cliquant « Évaluer maintenant ».</p>
                <?php if (empty($b['progress'])): ?>
                    <p class="qfs-empty">Aucun membre n’a encore commencé.</p>
                <?php else: ?>
                <div class="qpg">
                    <?php foreach (array_slice($b['progress'], 0, 60) as $row): ?>
                    <article class="qpg-item">
                        <div class="qpg-head">
                            <b translate="no"><?= $h($who($row['member'])) ?></b>
                            <?php if ($row['met'] && $row['prereq']['ok']): ?><span class="qfs-tag qfs-tag--ok">Conditions remplies</span><?php else: ?><span class="qfs-tag qfs-tag--info"><?= (int) $row['pct'] ?> %</span><?php endif; ?>
                        </div>
                        <div class="qpg-bar<?= $row['met'] ? ' is-met' : '' ?>" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?= (int) $row['pct'] ?>"><i style="width:<?= max(2, min(100, (int) $row['pct'])) ?>%"></i></div>
                        <ul class="qpg-crit">
                            <?php foreach ($row['eval']['groups'] ?? [] as $g): foreach ($g['items'] as $ci): $criterion = $ci['criterion']; ?>
                            <li class="<?= $ci['met'] ? 'is-met' : '' ?>">
                                <?php require base_path('views/partials/qualification_criterion.php'); ?>
                                <span><?php if (($criterion['type'] ?? 'metric') === 'metric' || (($criterion['type'] ?? '') === 'attestation' && (float) ($criterion['min'] ?? 0) > 0)): ?><?= $h(\App\Support\QualificationCriteriaCatalog::formatNumber((float) $ci['current'])) ?> / <?= $h(\App\Support\QualificationCriteriaCatalog::formatNumber((float) $ci['target'])) ?><?php else: ?><?= $ci['met'] ? 'Fait' : 'À faire' ?><?php endif; ?></span>
                            </li>
                            <?php endforeach; endforeach; ?>
                        </ul>
                        <?php if (!$row['prereq']['ok']): ?><p class="qpg-missing">Prérequis manquants : <?= $h(implode(', ', $row['prereq']['missing'])) ?></p><?php endif; ?>
                    </article>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </section>
            <?php endif; ?>

            <?php $attestList = is_array($attestations ?? null) ? $attestations : []; ?>
            <?php if ($attestList !== [] && !$archived): ?>
            <section class="qfs-card" id="attestations" aria-labelledby="qfs-attest">
                <h3 id="qfs-attest">Conditions attestées par un instructeur <span class="qfs-n"><?= count($attestList) ?></span></h3>
                <p class="qfs-lead">Le jeu ne mesure pas ces conditions : un instructeur les valide membre par membre. Un nombre de sauts ou d’heures attesté vaut pour toutes les qualifications qui le demandent.</p>
                <?php if (empty($attestReady)): ?><p class="qfs-empty">Les attestations arrivent avec la prochaine mise à jour du site.</p><?php endif; ?>
                <?php foreach ($attestList as $ai => $a): $criterion = $a['criterion']; ?>
                    <div class="qfs-attest">
                        <?php require base_path('views/partials/qualification_criterion.php'); ?>
                        <?php if ($a['attested'] !== []): ?>
                        <ul class="qfs-list">
                            <?php foreach ($a['attested'] as $uid => $row): ?>
                            <li>
                                <div class="qfs-who"><b translate="no"><?= $h($who($row['member'])) ?></b><small><?php if ($a['counts']): ?><?= $h(\App\Support\QualificationCriteriaCatalog::formatNumber((float) $row['value'])) ?> <?= $h(\App\Support\QualificationCriteriaCatalog::attestationUnit((string) $criterion['key'])) ?> · <?php endif; ?><?= $h($fd((string) $row['attested_at'])) ?></small><?php if (trim((string) $row['note']) !== ''): ?><small translate="no"><?= $h($row['note']) ?></small><?php endif; ?></div>
                                <form method="post" action="<?= $h(url($base . '/attestations')) ?>">
                                    <?= \App\Core\Csrf::field() ?>
                                    <input type="hidden" name="user_id" value="<?= (int) $uid ?>">
                                    <input type="hidden" name="store_key" value="<?= $h($a['store_key']) ?>">
                                    <input type="hidden" name="remove" value="1">
                                    <button type="submit" class="qmx-btn">Retirer</button>
                                </form>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                        <?php endif; ?>
                        <?php if (!empty($attestReady) && !empty($b['candidates'])): ?>
                        <form class="qfs-deliver" method="post" action="<?= $h(url($base . '/attestations')) ?>">
                            <?= \App\Core\Csrf::field() ?>
                            <input type="hidden" name="store_key" value="<?= $h($a['store_key']) ?>">
                            <div class="qmx-fl"><label for="qfs-att-u<?= (int) $ai ?>">Membre</label>
                                <select id="qfs-att-u<?= (int) $ai ?>" name="user_id" class="qmx-fin" required>
                                    <option value="">Choisir…</option>
                                    <?php foreach ($b['candidates'] as $m): ?><option value="<?= (int) $m['user_id'] ?>"><?= $h($who($m)) ?></option><?php endforeach; ?>
                                </select>
                            </div>
                            <?php if ($a['counts']): ?>
                            <div class="qmx-fl"><label for="qfs-att-v<?= (int) $ai ?>">Nombre attesté</label><input id="qfs-att-v<?= (int) $ai ?>" class="qmx-fin" type="number" min="0" step="any" name="value" required value="<?= $h(\App\Support\QualificationCriteriaCatalog::formatNumber((float) ($criterion['min'] ?? 0))) ?>"></div>
                            <?php endif; ?>
                            <div class="qmx-fl"><label for="qfs-att-n<?= (int) $ai ?>">Commentaire ou preuve</label><input id="qfs-att-n<?= (int) $ai ?>" class="qmx-fin" name="note" maxlength="255" placeholder="Carnet de sauts, séance de tir du…"></div>
                            <div><button type="submit" class="qmx-btn qmx-btn--dark">Attester</button></div>
                        </form>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </section>
            <?php endif; ?>

            <?php if (!empty($b['blocked'])): ?>
            <section class="qfs-card" aria-labelledby="qfs-blocked">
                <h3 id="qfs-blocked">Retirées, refusées ou suspendues <span class="qfs-n"><?= count($b['blocked']) ?></span></h3>
                <p class="qfs-lead">Jamais redonnées automatiquement : un responsable peut les délivrer de nouveau à la main.</p>
                <ul class="qfs-list">
                    <?php foreach ($b['blocked'] as $row): $award = $row['award']; ?>
                    <li><div class="qfs-who"><b translate="no"><?= $h($who($row['member'])) ?></b><small><?= $h(\App\Support\QualificationAdminStatus::label(\App\Services\Personnel\QualificationAwardRuleService::adminStatus($award))) ?><?= trim((string) ($award['revocation_reason'] ?? '')) !== '' ? ' · ' . $h($award['revocation_reason']) : '' ?></small></div></li>
                    <?php endforeach; ?>
                </ul>
            </section>
            <?php endif; ?>
        </div>

        <div class="qfs-col">
            <section class="qfs-card" aria-labelledby="qfs-how">
                <h3 id="qfs-how">Comment l’obtenir</h3>
                <?php if ($manual): ?>
                    <p class="qfs-lead qfs-mode"><b>À la main</b><span>Un instructeur ou un gestionnaire la délivre depuis la matrice des effectifs ou ci-dessous, avec la date et un commentaire.</span></p>
                <?php endif; ?>
                <?php if (in_array(\App\Support\QualificationCriteriaCatalog::MODE_PILOT, $modes, true)): ?>
                    <p class="qfs-lead qfs-mode"><b>Cursus pilote</b><span>Palier délivré d’après les heures de vol, réglé sur la page du cursus.</span> <a href="<?= $h(url('back-office/referentiels/qualifications/cursus-pilote')) ?>">Cursus pilote</a></p>
                <?php endif; ?>
                <?php if ($auto): ?>
                    <p class="qfs-lead qfs-mode"><b><?= ($st['auto_mode'] ?? '') === 'propose' ? 'Proposée automatiquement' : 'Délivrée automatiquement' ?></b><span>Quand les conditions suivantes sont remplies (vérifié toutes les heures).</span></p>
                    <div class="qfs-rule">
                        <?php foreach ($rule['groups'] as $gi => $g): ?>
                            <?php if ($gi > 0): ?><span class="qfs-rule__op"><?= ($rule['op'] ?? 'all') === 'any' ? 'ou' : 'et' ?></span><?php endif; ?>
                            <div class="qfs-rule__group">
                                <?php if (count($g['items']) > 1): ?><small><?= $g['op'] === 'any' ? 'Au moins une de ces conditions' : 'Toutes ces conditions' ?></small><?php endif; ?>
                                <?php foreach ($g['items'] as $criterion): ?>
                                    <?php require base_path('views/partials/qualification_criterion.php'); ?>
                                <?php endforeach; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <?php if (!empty($st['last_evaluated_at'])): ?><p class="qfs-empty">Dernier passage : <?= $h($fd((string) $st['last_evaluated_at'])) ?> <?= $h(substr((string) $st['last_evaluated_at'], 11, 5)) ?></p><?php endif; ?>
                <?php elseif (!$manual && !in_array(\App\Support\QualificationCriteriaCatalog::MODE_PILOT, $modes, true)): ?>
                    <p class="qfs-empty">Aucun mode d’obtention : modifiez la qualification pour en choisir un.</p>
                <?php endif; ?>
                <ul class="qfs-flags">
                    <?php if ((int) ($st['prereq_grade_id'] ?? 0) > 0): ?><li><span>Grade minimum exigé</span></li><?php endif; ?>
                    <?php if ((int) ($st['prereq_job_role_id'] ?? 0) > 0): ?><li><span>Fonction exigée</span></li><?php endif; ?>
                    <li><span>Page publique du joueur</span> <b><?= !empty($st['show_on_profile']) ? 'Visible' : 'Masquée' ?></b></li>
                    <li><span>Fiche opérateur ATAK</span> <b><?= !empty($st['show_on_orbat']) ? 'Visible' : 'Masquée' ?></b></li>
                    <?php if ((int) ($st['min_holders_per_unit'] ?? 0) > 0): ?><li><span>Besoin minimum par unité</span> <b><?= (int) $st['min_holders_per_unit'] ?></b></li><?php endif; ?>
                </ul>
            </section>

            <?php if ($manual && !$archived && !empty($b['candidates'])): ?>
            <section class="qfs-card" aria-labelledby="qfs-deliver">
                <h3 id="qfs-deliver">Délivrer à un membre</h3>
                <form class="qfs-deliver qfs-deliver--stack" method="post" action="<?= $h(url('back-office/referentiels/qualifications/matrice/case')) ?>">
                    <?= \App\Core\Csrf::field() ?>
                    <input type="hidden" name="definition_id" value="<?= $id ?>">
                    <input type="hidden" name="target" value="ok">
                    <input type="hidden" name="return_to" value="fiche">
                    <div class="qmx-fl"><label for="qfs-user">Membre</label>
                        <select id="qfs-user" name="user_id" class="qmx-fin" required>
                            <option value="">Choisir…</option>
                            <?php foreach ($b['candidates'] as $m): ?><option value="<?= (int) $m['user_id'] ?>"><?= $h($who($m)) ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <div class="qmx-fl"><label for="qfs-date">Date d’obtention</label><input id="qfs-date" class="qmx-fin" type="date" name="obtained_at" value="<?= $h(date('Y-m-d')) ?>" max="<?= $h(date('Y-m-d')) ?>"></div>
                    <div class="qmx-fl"><label for="qfs-notes">Commentaire ou preuve</label><textarea id="qfs-notes" class="qmx-fin" name="notes" rows="2" placeholder="Stage, évaluation, lien vers la preuve…"></textarea></div>
                    <label class="qmx-chk"><input type="checkbox" name="certificate" value="1"><span><b>Générer le brevet</b><small>PDF numéroté, à télécharger depuis la fiche du membre.</small></span></label>
                    <label class="qmx-chk"><input type="checkbox" name="force" value="1"><span><b>Délivrer quand même</b><small>Si un prérequis (grade, fonction, autre qualification) manque.</small></span></label>
                    <div><button type="submit" class="qmx-btn qmx-btn--dark">Délivrer</button></div>
                </form>
            </section>
            <?php endif; ?>

            <section class="qfs-card" aria-labelledby="qfs-hist">
                <h3 id="qfs-hist">Historique <span class="qfs-n"><?= count($history) ?></span></h3>
                <?php if ($history === []): ?>
                    <p class="qfs-empty">Rien pour l’instant.</p>
                <?php else: ?>
                <ul class="qfs-hist">
                    <?php foreach ($history as $ev):
                        $perf = trim((string) ($ev['performer_name'] ?? ''));
                        $memberName = trim((string) ($ev['callsign'] ?? '')) ?: trim((string) ($ev['display_name'] ?? ''));
                        ?>
                    <li>
                        <time datetime="<?= $h(substr((string) ($ev['event_date'] ?? ''), 0, 10)) ?>"><?= $h($fd((string) ($ev['event_date'] ?? ''))) ?></time>
                        <div><b><?= $h($eventLabels[(string) ($ev['event_type'] ?? '')] ?? (string) ($ev['event_type'] ?? '')) ?></b> · <span translate="no"><?= $h($memberName) ?></span><small><?= $perf !== '' ? 'par ' . $h($perf) : 'automatique' ?><?= trim((string) ($ev['details'] ?? '')) !== '' ? ' · ' . $h($ev['details']) : '' ?></small></div>
                    </li>
                    <?php endforeach; ?>
                </ul>
                <?php endif; ?>
            </section>
        </div>
    </div>
</div>
