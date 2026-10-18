<?php
declare(strict_types=1);
/**
 * Barre de pilotage de la fiche candidat : qui, où en est le dossier (frise des 4 étapes),
 * quelle est la prochaine action, qui la porte et depuis quand. Même calcul que la file en colonnes
 * (EnlistmentNextAction), pour que les deux écrans disent la même chose.
 *
 * Attend les variables de views/admin/recruitments/show.php : $e, $id, $statusRaw, $statusLabel, $isDossierClos,
 * $enlistmentSlaHours, $currentStaffUserId, $assigneeDisplayName, $staffRetroFeedback, $needsAcceptanceOnboarding,
 * $isInternalOpeningApplication, $submitterId.
 */
use App\Support\EnlistmentNextAction;
use App\Support\EnlistmentStatusCatalog;

$pbH = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$pbNow = (int) ($nowTs ?? time());
$pbRow = $e;
$pbRow['instructor_label'] = trim((string) ($assigneeDisplayName ?? ''));
$pbRetroDone = is_array($staffRetroFeedback ?? null)
    ? (string) (($staffRetroFeedback['submitted_at'] ?? '') ?: ($staffRetroFeedback['created_at'] ?? ''))
    : null;
$pbRetroDone = $pbRetroDone !== null && $pbRetroDone !== '' ? $pbRetroDone : null;
$pbStage = EnlistmentStatusCatalog::stage($pbRow);
$pbDelay = EnlistmentStatusCatalog::delay($pbRow, $enlistmentSlaHours, $pbNow);
$pbRow['stage'] = $pbStage;
$pbRow['delay'] = $pbDelay;
$pbAction = EnlistmentNextAction::for($pbRow, $enlistmentSlaHours, $currentStaffUserId, $pbNow, $pbRetroDone);

// L’intégration guidée n’est affichée que si le service la juge nécessaire : sinon, on vise le rattachement.
if ($pbAction !== null && $pbAction['anchor'] === 'integration-membre' && empty($needsAcceptanceOnboarding)) {
    $pbAction['anchor'] = 'rattachement-membre';
}

$pbFirst = (string) ($e['first_name'] ?? '');
$pbLast = (string) ($e['last_name'] ?? '');
$pbName = trim($pbFirst . ' ' . $pbLast) ?: 'Candidat sans nom';
$pbInitials = mb_strtoupper(mb_substr(trim($pbFirst), 0, 1) . mb_substr(trim($pbLast), 0, 1)) ?: '?';
$pbCreated = trim((string) ($e['created_at'] ?? ''));
$pbCreatedTs = $pbCreated !== '' ? strtotime($pbCreated) : false;

// Frise : 1 Réception · 2 Instruction · 3 Intégration · 4 Bilan 30 jours.
$pbIndex = (int) $pbStage['index'];
$pbAllDone = $pbAction !== null && $pbAction['key'] === 'done';
if ($pbStage['key'] === 'integrated') {
    $pbIndex = 4;
}
$pbInstrHint = match ($pbStage['key']) {
    'interview' => (string) $pbStage['hint'] !== '' ? 'Entretien · ' . mb_strtolower((string) $pbStage['hint']) : 'Entretien',
    'on_hold' => 'Mis en attente',
    default => 'Étude et décision',
};
$pbSteps = [
    ['label' => 'Réception', 'hint' => 'Désigner un référent'],
    ['label' => 'Instruction', 'hint' => $pbInstrHint],
    ['label' => 'Intégration', 'hint' => 'Rôles, unité, compte'],
    ['label' => 'Bilan 30 jours', 'hint' => $pbAllDone ? 'Rédigé' : 'Retour d’équipe'],
];

$pbUrg = $pbAction !== null ? (string) $pbAction['urgency'] : 'none';
$pbStateTone = match ($pbStage['tone']) {
    'rose' => '#fb7185',
    'amber', 'orange' => '#fbbf24',
    'sky' => '#38bdf8',
    'emerald' => '#34d399',
    default => '#94a3b8',
};
$pbOwner = $pbAction['owner'] ?? ((int) ($e['reviewed_by'] ?? 0) > 0 ? 'other' : 'nobody');
$pbOwnerLabel = $pbAction['ownerLabel'] ?? (trim((string) ($assigneeDisplayName ?? '')) ?: 'Personne');
$pbDecidedAt = trim((string) ($e['reviewed_at'] ?? ''));
?>
<section id="recap-dossier" class="rp-pilot scroll-mt-28" aria-labelledby="rp-pilot-name">
    <div class="rp-pilot__top">
        <div class="rp-pilot__who">
            <span class="rp-pilot__avatar" aria-hidden="true"><?= $pbH($pbInitials) ?></span>
            <div>
                <p class="rp-pilot__eyebrow">Dossier n°<?= (int) $id ?> · <?= $isInternalOpeningApplication ? 'Mobilité interne' : 'Candidature externe' ?></p>
                <h2 id="rp-pilot-name" class="rp-pilot__name"><?= $pbH($pbName) ?></h2>
                <p class="rp-pilot__sub">
                    Reçu <?= $pbCreatedTs !== false ? 'le ' . $pbH(date('d/m/Y à H:i', $pbCreatedTs)) : '—' ?>
                    <?php if (trim((string) ($e['email'] ?? '')) !== ''): ?> · <?= $pbH((string) $e['email']) ?><?php endif; ?>
                </p>
            </div>
        </div>
        <span class="rp-pilot__state" style="--rp-st: <?= $pbH($pbStateTone) ?>"><?= $pbH($pbStage['key'] === 'closed' ? (string) $statusLabel : (string) $pbStage['label']) ?></span>
    </div>

    <ol class="rp-steps<?= $pbStage['key'] === 'closed' ? ' is-closed' : '' ?>" aria-label="Étapes du parcours">
        <?php foreach ($pbSteps as $i => $st): ?>
            <?php
            $n = $i + 1;
            $cls = '';
            if ($pbStage['key'] !== 'closed') {
                if ($n < $pbIndex || ($pbAllDone && $n <= 4)) {
                    $cls = ' is-done';
                } elseif ($n === $pbIndex) {
                    $cls = ' is-current';
                }
            }
            ?>
            <li class="rp-step<?= $cls ?>"<?= $cls === ' is-current' ? ' aria-current="step"' : '' ?>>
                <span class="rp-step__dot" aria-hidden="true"></span>
                <span class="rp-step__label"><?= $pbH($st['label']) ?></span>
                <span class="rp-step__hint"><?= $pbH($st['hint']) ?></span>
            </li>
        <?php endforeach; ?>
    </ol>

    <div class="rp-pilot__body">
        <div class="rp-next-box rp-next-box--<?= $pbH($pbUrg) ?>">
            <div>
                <?php if ($pbAction !== null): ?>
                    <p class="rp-next-box__eyebrow">Prochaine action<?= $pbAction['urgencyLabel'] !== '' ? ' · ' . $pbH($pbAction['urgencyLabel']) : '' ?></p>
                    <p class="rp-next-box__title"><?= $pbH($pbAction['label']) ?></p>
                    <p class="rp-next-box__detail"><?= $pbH($pbAction['detail']) ?></p>
                <?php else: ?>
                    <p class="rp-next-box__eyebrow">Dossier clos</p>
                    <p class="rp-next-box__title"><?= $pbH((string) $statusLabel) ?></p>
                    <p class="rp-next-box__detail"><?= $pbH((string) $pbStage['hint']) ?><?= $pbDecidedAt !== '' ? ' le ' . $pbH(date('d/m/Y', strtotime($pbDecidedAt) ?: $pbNow)) : '' ?>. Aucune action n’est attendue ; le journal garde la trace des échanges.</p>
                <?php endif; ?>
            </div>
            <div class="rp-next-box__go">
                <?php if ($pbAction !== null && !empty($pbAction['canAssign'])): ?>
                    <form method="post" action="<?= $pbH(url('back-office/recruitments/' . (int) $id . '/referent')) ?>">
                        <?= \App\Core\Csrf::field() ?>
                        <input type="hidden" name="return_to" value="dossier">
                        <button type="submit" class="rp-btn rp-btn--go">Me désigner référent</button>
                    </form>
                <?php elseif ($pbAction !== null && $pbAction['column'] !== null): ?>
                    <a class="rp-btn" href="#<?= $pbH($pbAction['anchor']) ?>"><?= $pbH($pbAction['cta']) ?> <span aria-hidden="true">↓</span></a>
                <?php elseif ($pbAction !== null && $statusRaw === 'reviewed'): ?>
                    <a class="rp-btn rp-btn--ghost" href="#bilan-recrutement"><?= $pbH($pbAction['cta'] === 'Voir le bilan' ? 'Voir le bilan' : 'Bilan 30 jours') ?></a>
                <?php endif; ?>
                <?php if (!$isDossierClos): ?>
                    <a class="rp-btn rp-btn--ghost" href="#journal-dossier">Ajouter une note</a>
                <?php endif; ?>
            </div>
        </div>
        <dl class="rp-facts">
            <div class="<?= $pbOwner === 'you' ? 'is-you' : ($pbOwner === 'nobody' && !$isDossierClos ? 'is-watch' : '') ?>">
                <dt>Référent</dt>
                <dd><?= $pbH($pbOwner === 'nobody' ? 'Personne' : $pbOwnerLabel) ?><small><?= $pbOwner === 'you' ? 'Vous suivez ce dossier' : ($pbOwner === 'nobody' ? 'À désigner' : 'Suit le dossier') ?></small></dd>
            </div>
            <div class="is-<?= $pbH(match ((string) $pbDelay['key']) { 'late' => 'late', 'watch' => 'watch', 'ok' => 'ok', default => 'none' }) ?>">
                <dt><?= $statusRaw === 'submitted' ? 'Délai' : 'Traitement' ?></dt>
                <dd><?= $pbH((string) $pbDelay['label']) ?><small><?= $pbH((string) $pbDelay['meta'] !== '' ? (string) $pbDelay['meta'] : '—') ?></small></dd>
            </div>
            <div>
                <dt>Statut</dt>
                <dd><?= $pbH((string) $statusLabel ?: '—') ?><small>Étape <?= max(0, (int) $pbStage['index']) ?> sur <?= (int) $pbStage['total'] ?></small></dd>
            </div>
            <div>
                <dt>Compte membre</dt>
                <dd><?= $submitterId > 0 ? 'Lié' : 'Aucun' ?><small><?= $submitterId > 0 ? 'Le candidat a un compte' : 'Candidature invitée' ?></small></dd>
            </div>
        </dl>
    </div>
</section>
