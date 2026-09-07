<?php
declare(strict_types=1);

use App\Support\MemberIntegrationCatalog;

/** @var array<string,mixed>|null $integration */
/** @var list<array<string,mixed>> $steps */
/** @var list<array<string,mixed>> $events */
/** @var list<array<string,mixed>> $referents */
/** @var list<array<string,mixed>> $pendingInvites */
/** @var list<array<string,mixed>> $upcoming */
/** @var list<array<string,mixed>> $groups */
/** @var array<string,mixed> $dossier */
/** @var array<string,string> $statusLabels */
/** @var array<string,string> $stepStatusLabels */
/** @var array<string,string> $rsvpLabels */

$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$fmtWhen = static function (mixed $v): string {
    $s = trim((string) $v);
    if ($s === '') {
        return '';
    }
    $ts = strtotime($s);

    return $ts === false ? $s : date('d/m/Y à H:i', $ts);
};
$tagForStatus = static function (string $status): string {
    return match ($status) {
        MemberIntegrationCatalog::STATUS_COMPLETED,
        MemberIntegrationCatalog::STEP_COMPLETED,
        MemberIntegrationCatalog::STEP_SKIPPED => 'ok',
        MemberIntegrationCatalog::STATUS_CANCELLED,
        MemberIntegrationCatalog::STATUS_BLOCKED,
        MemberIntegrationCatalog::STEP_CANCELLED,
        MemberIntegrationCatalog::STEP_BLOCKED => 'bad',
        MemberIntegrationCatalog::STATUS_WAITING_MEMBER,
        MemberIntegrationCatalog::STATUS_WAITING_STAFF,
        MemberIntegrationCatalog::STEP_WAITING_MEMBER,
        MemberIntegrationCatalog::STEP_WAITING_STAFF => 'warn',
        MemberIntegrationCatalog::STEP_PENDING,
        MemberIntegrationCatalog::STATUS_TO_START => 'neut',
        default => 'info',
    };
};
$stepActionHref = static function (array $st): ?array {
    $type = (string) ($st['step_type'] ?? '');

    return match ($type) {
        MemberIntegrationCatalog::TYPE_PERSONNEL_DOSSIER => [
            'href' => url('personnel/me'),
            'label' => 'Compléter mon dossier',
        ],
        MemberIntegrationCatalog::TYPE_LMS_OPTIONAL => [
            'href' => url('formations'),
            'label' => 'Voir les formations',
        ],
        MemberIntegrationCatalog::TYPE_DOCUMENT_READ => [
            'href' => url('account'),
            'label' => 'Ouvrir mon compte',
        ],
        MemberIntegrationCatalog::TYPE_APPOINTMENT,
        MemberIntegrationCatalog::TYPE_EVENT_INVITE => [
            'href' => '#mi-invitations',
            'label' => 'Voir les rendez-vous',
        ],
        default => null,
    };
};

$row = is_array($integration ?? null) ? $integration : null;
$steps = is_array($steps ?? null) ? $steps : [];
$events = is_array($events ?? null) ? $events : [];
$referents = is_array($referents ?? null) ? $referents : [];
$pendingInvites = is_array($pendingInvites ?? null) ? $pendingInvites : [];
$upcoming = is_array($upcoming ?? null) ? $upcoming : [];
$groups = is_array($groups ?? null) ? $groups : [];
$statusLabels = is_array($statusLabels ?? null) ? $statusLabels : MemberIntegrationCatalog::statusLabels();
$stepStatusLabels = is_array($stepStatusLabels ?? null) ? $stepStatusLabels : MemberIntegrationCatalog::stepStatusLabels();
$rsvpLabels = is_array($rsvpLabels ?? null) ? $rsvpLabels : MemberIntegrationCatalog::rsvpLabels();
$score = is_array($dossier['score'] ?? null) ? $dossier['score'] : [];
$crit = is_array($score['sections_critiques'] ?? null) ? $score['sections_critiques'] : [];
$missing = is_array($score['missing_labels'] ?? null) ? $score['missing_labels'] : [];
$pct = max(0, min(100, (int) ($row['progress_percent'] ?? ($score['percent'] ?? 0))));
$intStatus = (string) ($row['status'] ?? '');
$intStatusLabel = $statusLabels[$intStatus] ?? 'En cours';
$isTerminal = $row !== null && MemberIntegrationCatalog::isTerminalStatus($intStatus);
$dossierComplete = !empty($row['dossier_complete']) || $crit === [];

$openSteps = [];
$doneCount = 0;
foreach ($steps as $st) {
    $stStatus = (string) ($st['status'] ?? '');
    if (MemberIntegrationCatalog::isStepDone($stStatus) || $stStatus === MemberIntegrationCatalog::STEP_CANCELLED) {
        $doneCount++;
    } else {
        $openSteps[] = $st;
    }
}
$nextStep = $openSteps[0] ?? null;
$nextAction = null;
if ($pendingInvites !== []) {
    $nextAction = [
        'title' => 'Une invitation attend votre réponse',
        'body' => 'Confirmez votre présence pour le prochain rendez-vous.',
        'href' => '#mi-invitations',
        'cta' => 'Répondre',
    ];
} elseif ($crit !== []) {
    $nextAction = [
        'title' => 'Votre dossier est incomplet',
        'body' => 'Quelques informations manquent encore pour avancer dans votre arrivée.',
        'href' => url('personnel/me'),
        'cta' => 'Compléter mon dossier',
    ];
} elseif ($nextStep !== null) {
    $cta = $stepActionHref($nextStep);
    $nextAction = [
        'title' => 'Prochaine étape : ' . trim((string) ($nextStep['title'] ?? 'À faire')),
        'body' => trim((string) ($nextStep['description'] ?? '')) !== ''
            ? trim((string) $nextStep['description'])
            : 'Suivez cette étape, puis votre encadrement la validera si besoin.',
        'href' => $cta['href'] ?? '#mi-etapes',
        'cta' => $cta['label'] ?? 'Voir l’étape',
    ];
} elseif ($row !== null && !$isTerminal) {
    $nextAction = [
        'title' => 'Vous avez fait votre part',
        'body' => 'L’encadrement peut encore valider des étapes ou vous proposer un rendez-vous.',
        'href' => url('personnel/me'),
        'cta' => 'Ouvrir ma fiche',
    ];
}

?>
<link href="<?= $h(asset_url('assets/css/member-integration.css')) ?>" rel="stylesheet">

<div class="mi-member">
    <header class="mi-member__hero">
        <div class="mi-member__hero-inner">
            <p class="mi-member__eyebrow">Arrivée</p>
            <h1 class="mi-member__title">Mon intégration</h1>
            <p class="mi-member__lead">
                <?php if ($row === null): ?>
                    Suivez ici les étapes d’arrivée, les rendez-vous et les messages de votre encadrement.
                <?php elseif ($isTerminal): ?>
                    Votre parcours d’arrivée est <?= $h(mb_strtolower($intStatusLabel)) ?>.
                    Gardez cette page pour relire les étapes et les messages.
                <?php else: ?>
                    Avancez étape par étape : dossier, rendez-vous, et validations de votre référent.
                <?php endif; ?>
            </p>
            <?php if ($row !== null): ?>
                <div class="mi-member__chips" aria-label="État du parcours">
                    <span class="mi-member__tag mi-member__tag--<?= $h($tagForStatus($intStatus)) ?>"><?= $h($intStatusLabel) ?></span>
                    <span class="mi-member__tag mi-member__tag--<?= $dossierComplete ? 'ok' : 'warn' ?>">
                        <?= $dossierComplete ? 'Dossier complet' : 'Dossier à compléter' ?>
                    </span>
                    <span class="mi-member__tag mi-member__tag--neut"><?= $pct ?> % réalisé</span>
                    <?php if ($steps !== []): ?>
                        <span class="mi-member__tag mi-member__tag--neut"><?= $doneCount ?> / <?= count($steps) ?> étapes</span>
                    <?php endif; ?>
                </div>
                <div class="mi-member__progress-wrap">
                    <div class="mi-member__progress-meta">
                        <span>Avancement</span>
                        <span><?= $pct ?> %</span>
                    </div>
                    <div class="mi-progress" role="progressbar" aria-valuenow="<?= $pct ?>" aria-valuemin="0" aria-valuemax="100">
                        <span style="width:<?= $pct ?>%"></span>
                    </div>
                </div>
            <?php endif; ?>
            <div class="mi-member__hero-actions">
                <a class="mi-btn" href="<?= $h(url('personnel/me')) ?>">Ma fiche</a>
                <a class="mi-btn mi-btn--ghost" href="<?= $h(url('personnel/mon-espace-rh')) ?>">Mes démarches</a>
                <a class="mi-btn mi-btn--ghost" href="<?= $h(url('account')) ?>">Mon compte</a>
            </div>
        </div>
    </header>

    <div class="mi-member__body">
        <?php if ($row === null): ?>
            <section class="mi-panel mi-member__empty" aria-labelledby="mi-empty-title">
                <strong id="mi-empty-title">Aucun parcours d’arrivée ouvert</strong>
                <p>
                    Si vous venez d’arriver, votre encadrement peut ouvrir le suivi depuis
                    <em>Intégration des nouveaux membres</em>. En attendant, complétez votre fiche
                    et votre compte : ce sont les bases pour démarrer.
                </p>
                <div class="mi-actions">
                    <a class="mi-btn" href="<?= $h(url('personnel/me')) ?>">Compléter ma fiche</a>
                    <a class="mi-btn mi-btn--ghost" href="<?= $h(url('account')) ?>">Ouvrir mon compte</a>
                    <a class="mi-btn mi-btn--ghost" href="<?= $h(url('personnel/mon-espace-rh')) ?>">Mes démarches</a>
                </div>
            </section>
        <?php else: ?>
            <?php if ($nextAction !== null): ?>
                <section class="mi-panel mi-member__next" aria-labelledby="mi-next-title">
                    <p class="mi-member__kicker">À faire maintenant</p>
                    <h2 id="mi-next-title"><?= $h($nextAction['title']) ?></h2>
                    <p><?= $h($nextAction['body']) ?></p>
                    <div class="mi-actions">
                        <a class="mi-btn" href="<?= $h($nextAction['href']) ?>"><?= $h($nextAction['cta']) ?></a>
                    </div>
                </section>
            <?php endif; ?>

            <div class="mi-member__grid">
                <div class="mi-member__main">
                    <section class="mi-panel" id="mi-etapes" aria-labelledby="mi-steps-title">
                        <div class="mi-member__section-head">
                            <h2 id="mi-steps-title">Étapes</h2>
                            <p>Ce que vous devez faire, et ce que l’encadrement valide.</p>
                        </div>
                        <?php if ($steps === []): ?>
                            <div class="mi-empty">
                                <strong>Aucune étape visible</strong>
                                <p>Votre parcours n’a pas encore d’étapes affichées. Contactez votre référent si besoin.</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($steps as $st): ?>
                                <?php
                                $stStatus = (string) ($st['status'] ?? '');
                                $stDone = MemberIntegrationCatalog::isStepDone($stStatus) || $stStatus === MemberIntegrationCatalog::STEP_CANCELLED;
                                $stLabel = $stepStatusLabels[$stStatus] ?? 'À faire';
                                $due = $fmtWhen($st['due_at'] ?? '');
                                $doneAt = $fmtWhen($st['completed_at'] ?? '');
                                $desc = trim((string) ($st['description'] ?? ''));
                                $cta = !$stDone ? $stepActionHref($st) : null;
                                ?>
                                <article class="mi-member__step<?= $stDone ? ' is-done' : '' ?>">
                                    <div class="mi-member__step-top">
                                        <div>
                                            <h3><?= $h($st['title'] ?? '') ?></h3>
                                            <p class="mi-muted">
                                                <?= !empty($st['is_required']) ? 'Obligatoire' : 'Facultative' ?>
                                                <?php if ($due !== '' && !$stDone): ?> · Prévue le <?= $h($due) ?><?php endif; ?>
                                                <?php if ($doneAt !== ''): ?> · Faite le <?= $h($doneAt) ?><?php endif; ?>
                                            </p>
                                        </div>
                                        <span class="mi-member__tag mi-member__tag--<?= $h($tagForStatus($stStatus)) ?>"><?= $h($stLabel) ?></span>
                                    </div>
                                    <?php if ($desc !== ''): ?>
                                        <p class="mi-member__step-desc"><?= nl2br($h($desc)) ?></p>
                                    <?php endif; ?>
                                    <?php if ($cta !== null): ?>
                                        <div class="mi-actions">
                                            <a class="mi-btn mi-btn--ghost" href="<?= $h($cta['href']) ?>"><?= $h($cta['label']) ?></a>
                                        </div>
                                    <?php endif; ?>
                                </article>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </section>

                    <section class="mi-panel" id="mi-invitations" aria-labelledby="mi-invites-title">
                        <div class="mi-member__section-head">
                            <h2 id="mi-invites-title">Rendez-vous</h2>
                            <p>Répondez aux invitations et ajoutez-les à votre calendrier.</p>
                        </div>

                        <?php if ($pendingInvites === [] && $upcoming === []): ?>
                            <p class="mi-muted">Aucune invitation en attente pour le moment.</p>
                        <?php endif; ?>

                        <?php foreach ($pendingInvites as $inv): ?>
                            <?php
                            $apptId = (int) ($inv['appointment_id'] ?? 0);
                            $when = $fmtWhen($inv['starts_at'] ?? '');
                            $where = trim((string) ($inv['location'] ?? ''));
                            $meet = trim((string) ($inv['meeting_url'] ?? ''));
                            ?>
                            <article class="mi-member__step">
                                <div class="mi-member__step-top">
                                    <div>
                                        <h3><?= $h($inv['title'] ?? 'Rendez-vous') ?></h3>
                                        <p class="mi-muted">
                                            <?= $when !== '' ? $h($when) : 'Date à préciser' ?>
                                            <?php if ($where !== ''): ?> · <?= $h($where) ?><?php endif; ?>
                                        </p>
                                    </div>
                                    <span class="mi-member__tag mi-member__tag--warn">Réponse attendue</span>
                                </div>
                                <?php if ($meet !== ''): ?>
                                    <p class="mi-member__step-desc"><a href="<?= $h($meet) ?>" rel="noopener noreferrer" target="_blank">Lien de réunion</a></p>
                                <?php endif; ?>
                                <form method="post" action="<?= $h(url('mon-integration/repondre')) ?>" class="mi-actions">
                                    <?= \App\Core\Csrf::field() ?>
                                    <input type="hidden" name="appointment_id" value="<?= $apptId ?>">
                                    <button class="mi-btn" name="reponse" value="oui" type="submit">Oui</button>
                                    <button class="mi-btn mi-btn--ghost" name="reponse" value="peut-etre" type="submit">Peut-être</button>
                                    <button class="mi-btn mi-btn--warn" name="reponse" value="non" type="submit">Non</button>
                                    <?php if ($apptId > 0): ?>
                                        <a class="mi-btn mi-btn--ghost" href="<?= $h(url('mon-integration/rendez-vous/' . $apptId . '/calendrier')) ?>">Ajouter au calendrier</a>
                                    <?php endif; ?>
                                </form>
                            </article>
                        <?php endforeach; ?>

                        <?php foreach ($upcoming as $inv): ?>
                            <?php
                            $apptId = (int) ($inv['appointment_id'] ?? 0);
                            $when = $fmtWhen($inv['starts_at'] ?? '');
                            $where = trim((string) ($inv['location'] ?? ''));
                            $rsvp = (string) ($inv['status'] ?? '');
                            $rsvpLabel = $rsvpLabels[$rsvp] ?? 'Confirmé';
                            ?>
                            <article class="mi-member__step is-done">
                                <div class="mi-member__step-top">
                                    <div>
                                        <h3><?= $h($inv['title'] ?? 'Rendez-vous') ?></h3>
                                        <p class="mi-muted">
                                            <?= $when !== '' ? $h($when) : 'Date à préciser' ?>
                                            <?php if ($where !== ''): ?> · <?= $h($where) ?><?php endif; ?>
                                        </p>
                                    </div>
                                    <span class="mi-member__tag mi-member__tag--ok"><?= $h($rsvpLabel) ?></span>
                                </div>
                                <?php if ($apptId > 0): ?>
                                    <div class="mi-actions">
                                        <a class="mi-btn mi-btn--ghost" href="<?= $h(url('mon-integration/rendez-vous/' . $apptId . '/calendrier')) ?>">Ajouter au calendrier</a>
                                    </div>
                                <?php endif; ?>
                            </article>
                        <?php endforeach; ?>
                    </section>

                    <section class="mi-panel" aria-labelledby="mi-messages-title">
                        <div class="mi-member__section-head">
                            <h2 id="mi-messages-title">Messages</h2>
                            <p>Informations partagées avec vous par l’encadrement.</p>
                        </div>
                        <?php if ($events === []): ?>
                            <p class="mi-muted">Aucun message pour le moment.</p>
                        <?php else: ?>
                            <ul class="mi-member__journal">
                                <?php foreach ($events as $ev): ?>
                                    <li>
                                        <span class="mi-member__journal-body"><?= $h($ev['message'] ?? $ev['body'] ?? '') ?></span>
                                        <span class="mi-muted"><?= $h($fmtWhen($ev['created_at'] ?? '')) ?></span>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </section>
                </div>

                <aside class="mi-member__side">
                    <section class="mi-panel" aria-labelledby="mi-ref-title">
                        <div class="mi-member__section-head">
                            <h2 id="mi-ref-title">Votre référent</h2>
                            <p>Personne à contacter pour votre arrivée.</p>
                        </div>
                        <?php if ($referents === []): ?>
                            <p class="mi-muted">Aucun référent n’est encore désigné.</p>
                        <?php else: ?>
                            <ul class="mi-member__bullets">
                                <?php foreach ($referents as $ref): ?>
                                    <li>
                                        <?= $h($ref['display_name'] ?? '') ?>
                                        <?php if (!empty($ref['is_primary'])): ?>
                                            <span class="mi-muted"> — référent principal</span>
                                        <?php endif; ?>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </section>

                    <?php if ($crit !== [] || $missing !== []): ?>
                        <section class="mi-panel" aria-labelledby="mi-dossier-title">
                            <div class="mi-member__section-head">
                                <h2 id="mi-dossier-title">Dossier personnel</h2>
                                <p>Éléments encore attendus sur votre fiche.</p>
                            </div>
                            <ul class="mi-member__bullets">
                                <?php
                                $labels = $missing !== [] ? $missing : $crit;
                                foreach (array_slice($labels, 0, 8) as $label):
                                    $text = is_array($label)
                                        ? trim((string) ($label['label'] ?? $label['title'] ?? $label['name'] ?? ''))
                                        : trim((string) $label);
                                    if ($text === '') {
                                        continue;
                                    }
                                    ?>
                                    <li><?= $h($text) ?></li>
                                <?php endforeach; ?>
                            </ul>
                            <div class="mi-actions">
                                <a class="mi-btn" href="<?= $h(url('personnel/me')) ?>">Compléter mon dossier</a>
                            </div>
                        </section>
                    <?php endif; ?>

                    <?php if ($groups !== []): ?>
                        <section class="mi-panel" aria-labelledby="mi-groups-title">
                            <div class="mi-member__section-head">
                                <h2 id="mi-groups-title">Groupes de suivi</h2>
                                <p>Groupes auxquels vous êtes rattaché pour l’arrivée.</p>
                            </div>
                            <ul class="mi-member__bullets">
                                <?php foreach ($groups as $g): ?>
                                    <li><?= $h($g['name'] ?? '') ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </section>
                    <?php endif; ?>
                </aside>
            </div>
        <?php endif; ?>
    </div>
</div>
