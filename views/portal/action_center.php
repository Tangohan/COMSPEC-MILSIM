<?php
/**
 * Mon service : en-tête en bande (identité, affectation, chiffre clé), une bande par tâche du poste, puis les
 * éléments à suivre en tuiles. Composants réutilisables : public/assets/css/back-office-bands.css.
 *
 * @var array<string, mixed> $action_center_digest
 * @var array<string, mixed> $mon_service
 */
$action_center_digest = $action_center_digest ?? [];
$mon_service = is_array($mon_service ?? null) ? $mon_service : [];
$sections = $action_center_digest['sections'] ?? [];
$totalAttention = max(0, (int) ($action_center_digest['total_attention'] ?? 0));
$todayLabel = (new DateTimeImmutable('now'))->format('d/m/Y');
$tasks = is_array($mon_service['tasks'] ?? null) ? $mon_service['tasks'] : [];
$waiting = is_array($mon_service['waiting'] ?? null) ? $mon_service['waiting'] : [];
$missionDuty = is_array($mon_service['mission_duty'] ?? null) ? $mon_service['mission_duty'] : null;
$csrf = \App\Core\Csrf::token();
$statusLabels = ['open' => 'À traiter', 'acknowledged' => 'Pris en compte', 'in_progress' => 'En cours', 'completed' => 'Terminée', 'cancelled' => 'Annulée'];
$priorityLabels = ['critical' => 'Critique', 'high' => 'Haute priorité', 'normal' => 'Priorité normale', 'low' => 'Basse priorité'];
// Un membre sans rôle de gestion retourne à son espace JNET, pas dans le back-office (rien à proposer si son arrivée est le tableau de bord).
$backHref = url('back-office');
$backLabel = 'Retour au back-office';
try {
    if (\App\Support\PortalAccessChoice::isPlainMember()) {
        $backHref = \App\Support\PortalAccessChoice::plainMemberHome();
        $backLabel = 'Retour à mon espace JNET';
    }
} catch (\Throwable) {
}
$showBackLink = $backHref !== url('dashboard');

$svcH = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
$svcAccents = ['emerald', 'sky', 'amber', 'violet', 'rose'];
$svcName = trim((string) ($mon_service['display_name'] ?? '')) ?: 'Mon service';
$svcInitials = '';
foreach (preg_split('/[\s\-]+/u', $svcName) ?: [] as $svcPart) {
    if ($svcPart !== '' && mb_strlen($svcInitials) < 2) {
        $svcInitials .= mb_strtoupper(mb_substr($svcPart, 0, 1));
    }
}
$svcVisibleTasks = [];
foreach ($tasks as $task) {
    if (is_array($task) && (int) ($task['id'] ?? 0) > 0 && trim((string) ($task['title'] ?? '')) !== '') {
        $svcVisibleTasks[] = $task;
    }
}
$svcTaskCount = count($tasks);
$svcValidations = (int) ($waiting['validations'] ?? 0);
$svcReports = (int) ($waiting['reports'] ?? 0);
$svcSectionDesc = [
    'À traiter pour vous' => 'Courrier, messages et dossiers qui attendent votre lecture.',
    'Encadrement' => 'Ce que votre rôle d’encadrement vous demande d’examiner.',
    'Agenda et échéances' => 'Vos rendez-vous des deux prochaines semaines et vos échéances.',
    'Raccourcis' => 'Les pages utiles au quotidien.',
];
$svcToday = new DateTimeImmutable('today');
/** Écart en jours entre aujourd'hui et l'échéance (négatif : en retard), null sans échéance lisible. */
$svcDueDays = static function (string $due) use ($svcToday): ?int {
    if ($due === '') {
        return null;
    }
    try {
        $d = (new DateTimeImmutable($due))->setTime(0, 0);
    } catch (\Throwable) {
        return null;
    }

    return (int) $svcToday->diff($d)->format('%r%a');
};
$svcDueLabel = static function (string $due): string {
    try {
        $d = new DateTimeImmutable($due);
    } catch (\Throwable) {
        return $due;
    }
    $time = $d->format('H:i');

    return 'Échéance le ' . $d->format('d/m/Y') . ($time !== '00:00' ? ' ' . $time : '');
};
$svcSectionIndex = 1;
// Ancre « ce qui vous attend » : la première section à suivre réellement affichée.
$svcFollowAnchor = '';
foreach ($sections as $secIdx => $sec) {
    $st = (string) ($sec['title'] ?? '');
    if ($st !== '' && is_array($sec['items'] ?? null) && $sec['items'] !== []) {
        $svcFollowAnchor = $st === 'Agenda et échéances' ? 'agenda-et-echeances' : 'action-center-sec-' . (int) $secIdx;
        break;
    }
}
?>
<div class="bo-page" data-bo-svc>
    <header class="bo-band bo-band--hero bo-band--emerald" aria-labelledby="ath-page-title">
        <div class="bo-band__media bo-band__media--fallback" aria-hidden="true"></div>
        <div class="bo-band__glow" aria-hidden="true"></div>
        <div class="bo-band__veil" aria-hidden="true"></div>
        <div class="bo-band__body">
            <div class="bo-band__who">
                <div class="bo-band__heading">
                    <h1 class="bo-band__title" id="ath-page-title"><?= $svcH($svcName) ?></h1>
                    <?php if ($svcInitials !== ''): ?>
                    <span class="bo-faces" aria-hidden="true"><span class="bo-faces__it"><?= $svcH($svcInitials) ?></span></span>
                    <?php endif; ?>
                </div>
                <p class="bo-band__line">Ce que votre poste attend de vous : tâches affectées, avis demandés, puis le reste de votre briefing.</p>
                <?php if ($svcVisibleTasks !== []): ?>
                <a class="bo-band__btn bo-band__btn--solid" href="#mon-service-tasks">Voir mes tâches <span class="bo-band__arrow" aria-hidden="true">→</span></a>
                <?php elseif ($totalAttention > 0 && $svcFollowAnchor !== ''): ?>
                <a class="bo-band__btn bo-band__btn--solid" href="#<?= $svcH($svcFollowAnchor) ?>">Voir ce qui vous attend <span class="bo-band__arrow" aria-hidden="true">→</span></a>
                <?php endif; ?>
            </div>
            <div class="bo-band__side">
                <p class="bo-band__meta"><span>Mon service</span> <?= $svcH($todayLabel) ?></p>
                <p class="bo-figure<?= $totalAttention > 0 ? ' is-hot' : '' ?>"><strong><?= $totalAttention ?></strong><span><?= $totalAttention > 1 ? 'éléments à traiter' : 'élément à traiter' ?></span></p>
            </div>
            <div class="bo-band__foot">
                <dl class="bo-facts" aria-label="Identité et affectation">
                    <div><dt>Grade</dt><dd><?= $svcH((string) ($mon_service['grade_label'] ?? '—')) ?></dd></div>
                    <div><dt>Unité</dt><dd><?= $svcH((string) ($mon_service['unit_label'] ?? '—')) ?></dd></div>
                    <div><dt>Poste organique</dt><dd><?= $svcH((string) ($mon_service['organic_post_label'] ?? '—')) ?></dd></div>
                    <div><dt>Rôle Athena</dt><dd><?= $svcH((string) ($mon_service['technical_role_label'] ?? 'Membre')) ?></dd></div>
                </dl>
                <?php if ($missionDuty): ?>
                <dl class="bo-facts bo-facts--accent" aria-label="Affectation mission">
                    <div><dt>Affectation mission</dt><dd><?= $svcH((string) ($missionDuty['duty_title'] ?? '')) ?></dd></div>
                    <div><dt>Indicatif</dt><dd><?= $svcH((string) (($missionDuty['duty_callsign'] ?? '') ?: '—')) ?></dd></div>
                    <div><dt>Mission</dt><dd><?= $svcH((string) (($missionDuty['mission_label'] ?? '') ?: '—')) ?></dd></div>
                </dl>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <section class="bo-section" aria-labelledby="mon-service-tasks">
        <header class="bo-section__head">
            <span class="bo-section__index" aria-hidden="true">/ <?= sprintf('%02d', $svcSectionIndex++) ?></span>
            <h2 class="bo-section__title" id="mon-service-tasks">Tâches de mon poste</h2>
            <div class="bo-section__aside">
                <span class="bo-chip"><b><?= $svcTaskCount ?></b> <?= $svcTaskCount > 1 ? 'tâches ouvertes' : 'tâche ouverte' ?></span>
                <span class="bo-chip"><b><?= $svcValidations ?></b> <?= $svcValidations > 1 ? 'validations' : 'validation' ?></span>
                <span class="bo-chip"><b><?= $svcReports ?></b> <?= $svcReports > 1 ? 'rapports' : 'rapport' ?></span>
            </div>
            <p class="bo-section__desc">Les demandes adressées à votre nom, à votre poste ou à votre permanence.</p>
        </header>

        <?php if ($svcVisibleTasks === []): ?>
        <div class="bo-band bo-band--empty bo-band--sky">
            <div class="bo-band__media bo-band__media--fallback" aria-hidden="true"></div>
            <div class="bo-band__glow" aria-hidden="true"></div>
            <div class="bo-band__veil" aria-hidden="true"></div>
            <div class="bo-band__body">
                <div class="bo-band__who">
                    <p class="bo-band__title">Aucune tâche ouverte</p>
                    <p class="bo-band__line">Rien n’attend votre poste pour l’instant. Les demandes adressées à votre permanence apparaîtront ici.</p>
                </div>
            </div>
        </div>
        <?php else: ?>
        <ol class="bo-bands">
            <?php foreach ($svcVisibleTasks as $svcI => $task): ?>
            <?php
            $taskId = (int) $task['id'];
            $title = trim((string) $task['title']);
            $prio = (string) ($task['priority'] ?? 'normal');
            $status = (string) ($task['status'] ?? 'open');
            $due = trim((string) ($task['due_at'] ?? ''));
            $desc = trim((string) ($task['description'] ?? ''));
            $svcDays = $svcDueDays($due);
            $svcAccent = $prio === 'critical' ? 'rose' : $svcAccents[$svcI % count($svcAccents)];
            $svcIsOpinion = (string) ($task['type'] ?? '') === \App\Services\Workflow\WorkflowEngine::TASK_TYPE_OPINION && (int) ($task['linked_entity_id'] ?? 0) > 0;
            $svcDialogId = 'mon-service-avis-' . $taskId;
            ?>
            <li>
                <article class="bo-band bo-band--<?= $svcH($svcAccent) ?><?= $svcI % 2 === 1 ? ' is-mirror' : '' ?>" style="--bo-band-i:<?= (int) $svcI ?>" aria-labelledby="mon-service-task-<?= $taskId ?>">
                    <div class="bo-band__media bo-band__media--fallback" aria-hidden="true"></div>
                    <div class="bo-band__glow" aria-hidden="true"></div>
                    <div class="bo-band__veil" aria-hidden="true"></div>
                    <div class="bo-band__body">
                        <div class="bo-band__identity">
                            <span class="bo-band__index" aria-hidden="true">/ <?= sprintf('%02d', $svcI + 1) ?></span>
                            <div class="bo-band__who">
                                <h3 class="bo-band__title" id="mon-service-task-<?= $taskId ?>"><?= $svcH($title) ?></h3>
                                <?php if ($desc !== ''): ?>
                                <p class="bo-band__line"><?= $svcH($desc) ?></p>
                                <?php endif; ?>
                                <p class="bo-band__tag"><?= $svcH($priorityLabels[$prio] ?? $prio) ?></p>
                            </div>
                        </div>
                        <div class="bo-band__side">
                            <p class="bo-band__meta"><span><?= $svcH($statusLabels[$status] ?? $status) ?></span> <?= $due !== '' ? $svcH($svcDueLabel($due)) : 'Sans échéance' ?></p>
                            <div class="bo-band__row">
                                <div class="bo-band__actions">
                                    <?php if ($svcIsOpinion): ?>
                                    <button type="button" class="bo-band__btn bo-band__btn--solid" data-bo-dialog-open="<?= $svcH($svcDialogId) ?>" aria-haspopup="dialog">Donner mon avis</button>
                                    <?php else: ?>
                                    <?php if ($status === 'open'): ?>
                                    <form method="post" action="<?= $svcH(url('mon-service/tache/' . $taskId . '/accuser')) ?>">
                                        <input type="hidden" name="_csrf_token" value="<?= $svcH($csrf) ?>">
                                        <button type="submit" class="bo-band__btn">Accuser</button>
                                    </form>
                                    <?php endif; ?>
                                    <?php if (in_array($status, ['open', 'acknowledged'], true)): ?>
                                    <form method="post" action="<?= $svcH(url('mon-service/tache/' . $taskId . '/demarrer')) ?>">
                                        <input type="hidden" name="_csrf_token" value="<?= $svcH($csrf) ?>">
                                        <button type="submit" class="bo-band__btn">Démarrer</button>
                                    </form>
                                    <?php endif; ?>
                                    <?php if ($status !== 'completed'): ?>
                                    <form method="post" action="<?= $svcH(url('mon-service/tache/' . $taskId . '/terminer')) ?>">
                                        <input type="hidden" name="_csrf_token" value="<?= $svcH($csrf) ?>">
                                        <button type="submit" class="bo-band__btn bo-band__btn--solid">Terminer</button>
                                    </form>
                                    <?php endif; ?>
                                    <?php endif; ?>
                                </div>
                                <?php if ($svcDays !== null): ?>
                                <p class="bo-figure<?= $svcDays < 0 ? ' is-alert' : ($svcDays <= 1 ? ' is-hot' : '') ?>"><strong><?= abs($svcDays) ?><small> j</small></strong><span><?= $svcDays < 0 ? 'de retard' : ($svcDays === 0 ? 'échéance aujourd’hui' : 'avant l’échéance') ?></span></p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </article>
                <?php if ($svcIsOpinion): ?>
                <dialog class="bo-dialog" id="<?= $svcH($svcDialogId) ?>" aria-labelledby="<?= $svcH($svcDialogId) ?>-title">
                    <form method="post" class="bo-dialog__form" action="<?= $svcH(url('mon-service/circuit/' . (int) $task['linked_entity_id'] . '/avis')) ?>">
                        <input type="hidden" name="_csrf_token" value="<?= $svcH($csrf) ?>">
                        <div class="bo-dialog__head">
                            <div class="bo-dialog__titles">
                                <h2 class="bo-dialog__title" id="<?= $svcH($svcDialogId) ?>-title">Votre avis</h2>
                                <p class="bo-dialog__sub"><?= $svcH($title) ?></p>
                            </div>
                            <button type="button" class="bo-dialog__close" data-bo-dialog-close aria-label="Fermer"><svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M18 6 6 18M6 6l12 12"/></svg></button>
                        </div>
                        <div class="bo-dialog__body">
                            <label class="bo-dialog__label" for="avis-<?= $taskId ?>">Votre avis (obligatoire s’il est défavorable)</label>
                            <textarea id="avis-<?= $taskId ?>" name="commentaire" rows="4" maxlength="500"></textarea>
                            <p class="bo-dialog__help">Votre avis est transmis aux responsables des effectifs, qui prennent la décision.</p>
                        </div>
                        <div class="bo-dialog__foot">
                            <button type="submit" name="avis" value="defavorable" class="bo-dialog__btn bo-dialog__btn--danger">Avis défavorable</button>
                            <button type="submit" name="avis" value="favorable" class="bo-dialog__btn bo-dialog__btn--primary">Avis favorable</button>
                        </div>
                    </form>
                </dialog>
                <?php endif; ?>
            </li>
            <?php endforeach; ?>
        </ol>
        <?php endif; ?>
    </section>

    <?php foreach ($sections as $secIdx => $sec): ?>
    <?php
    $st = (string) ($sec['title'] ?? '');
    $items = $sec['items'] ?? [];
    if (! is_array($items) || $items === [] || $st === '') {
        continue;
    }
    $secDomId = $st === 'Agenda et échéances' ? 'agenda-et-echeances' : 'action-center-sec-' . (int) $secIdx;
    ?>
    <section class="bo-section" aria-labelledby="<?= $svcH($secDomId) ?>">
        <header class="bo-section__head">
            <span class="bo-section__index" aria-hidden="true">/ <?= sprintf('%02d', $svcSectionIndex++) ?></span>
            <h2 class="bo-section__title" id="<?= $svcH($secDomId) ?>"><?= $svcH($st) ?></h2>
            <?php if (($svcSectionDesc[$st] ?? '') !== ''): ?>
            <p class="bo-section__desc"><?= $svcH($svcSectionDesc[$st]) ?></p>
            <?php endif; ?>
        </header>
        <ul class="bo-tiles">
            <?php foreach ($items as $it): ?>
            <?php
            if (! is_array($it)) {
                continue;
            }
            $label = (string) ($it['label'] ?? '');
            $href = (string) ($it['href'] ?? '');
            $hint = (string) ($it['hint'] ?? '');
            $count = isset($it['count']) ? (int) $it['count'] : null;
            if ($label === '' || $href === '') {
                continue;
            }
            $meta = $count !== null && $count > 0 ? (string) $count : '';
            $priority = (string) ($it['priority'] ?? 'low');
            $action = (string) ($it['action'] ?? 'Ouvrir');
            $eventId = max(0, (int) ($it['event_id'] ?? 0));
            $rsvpStatus = (string) ($it['rsvp_status'] ?? '');
            ?>
            <li>
                <article class="bo-tile<?= $eventId > 0 ? '' : ' bo-tile--link' ?>">
                    <div class="bo-tile__head">
                        <span class="bo-tile__dot<?= in_array($priority, ['high', 'normal'], true) ? ' bo-tile__dot--' . $svcH($priority) : '' ?>" aria-hidden="true"></span>
                        <h3 class="bo-tile__title"><?= $svcH($label) ?></h3>
                        <?php if ($meta !== ''): ?><span class="bo-tile__count"><?= $svcH($meta) ?></span><?php endif; ?>
                    </div>
                    <p class="bo-tile__hint"><?= $svcH($hint) ?></p>
                    <div class="bo-tile__foot">
                        <?php if ($eventId > 0): ?>
                            <?php
                            $rsvpEventId = $eventId;
                            $rsvpCurrentStatus = $rsvpStatus;
                            $rsvpCompact = true;
                            $rsvpShowAbsenceReason = false;
                            require base_path('views/partials/dashboard_rsvp_buttons.php');
                            ?>
                        <?php else: ?>
                            <a href="<?= $svcH($href) ?>" class="bo-tile__link"><?= $svcH($action) ?></a>
                        <?php endif; ?>
                    </div>
                </article>
            </li>
            <?php endforeach; ?>
        </ul>
    </section>
    <?php endforeach; ?>

    <nav class="bo-backlinks" aria-label="Retour">
        <a href="<?= $svcH(url('hub')) ?>" class="bo-backlinks__it">Retour au centre de commandement</a>
        <?php if ($showBackLink): ?><a href="<?= $svcH($backHref) ?>" class="bo-backlinks__it"><?= $svcH($backLabel) ?></a><?php endif; ?>
    </nav>
</div>
<script defer src="<?= $svcH(asset_url('assets/js/back-office-bands.js')) ?>"></script>
