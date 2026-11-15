<?php
declare(strict_types=1);
/**
 * Fiche d'une personne : état de travail (signature, avis, classification, verrouillage, clôture) et actions.
 * Mêmes fenêtres modales et même script que la liste (persons/_dialogs.php, assets/js/sse-persons.js).
 *
 * @var array<string, mixed> $work ['row' => ligne de liste, 'viewer' => SsePersonViewer, 'csrf' => string, 'ready' => bool]
 */

use App\Support\SsePersonUi as Ui;
use App\Support\SsePersonWork as W;

$h ??= static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$r = $work['row'];
$viewer = $work['viewer'];
$csrf = (string) $work['csrf'];
$id = (int) $r['id'];
$now = time();
$canAct = (bool) $work['ready'] && $viewer->canWrite && $r['can_modify'];
$deny = !$work['ready'] ? 'Disponible après la mise à jour de la base de données (étape « SSE Personnes »).' : 'Droit manquant ou fiche classifiée au-dessus de votre habilitation.';
$returnTo = url('atak/sse/identites/' . $id) . '?fiche=personne';
$listing = null;
$caseOptions = [];
?>
<section class="pe-workbar" aria-label="État de travail de la fiche" data-pe-subject data-id="<?= $id ?>" data-name="<?= $h($r['name']) ?>" data-wanted="<?= $r['wanted'] !== null ? '1' : '0' ?>" data-can-wanted="<?= $viewer->canWanted ? '1' : '0' ?>">
    <div class="pe-workbar__flags">
        <?php if ($r['closed']): ?>
            <span class="pe-status"><span class="pe-dot pe-dot--closed" aria-hidden="true"></span>Clos · <?= $h(W::closeReasonLabel($r['close_reason'])) ?></span>
        <?php endif; ?>
        <?php if ($r['classification'] !== null): ?>
            <span class="pe-class pe-class--<?= $h(Ui::classTone($r['classification'])) ?>" title="<?= $h(W::classificationLabel($r['classification'])) ?>"><?= $h(W::classificationBadge($r['classification'])) ?></span>
        <?php else: ?>
            <span class="pe-none">Non classifiée</span>
        <?php endif; ?>
        <?php if ($r['lock']['locked']): ?>
            <span class="pe-badge pe-badge--warn pe-badge--plain"><?= $r['lock']['auto'] && !$r['lock']['manual'] ? 'Verrouillée (règle de la communauté)' : 'Verrouillée' ?></span>
        <?php endif; ?>
        <?php if ($r['wanted'] !== null): ?>
            <a class="pe-badge pe-badge--<?= $h(Ui::threatTone($r['wanted']['threat'])) ?>" href="<?= $h(url('atak/sse/papiers/identites/' . $r['wanted']['identity_id'])) ?>">Recherché<small><?= $h(Ui::threat($r['wanted']['threat'])) ?> · <?= $h($r['wanted']['ref']) ?></small></a>
        <?php endif; ?>
        <?php if ($r['signed']): ?>
            <span class="pe-badge pe-badge--ok pe-badge--plain">Signé<small><?= $h(trim($r['sig']['callsign'] . ($r['sig']['terminal'] !== '' ? ' · terminal ' . $r['sig']['terminal'] : '') . ($r['sig']['at'] !== '' ? ' · ' . Ui::full($r['sig']['at']) : ''), ' ·')) ?></small></span>
        <?php else: ?>
            <span class="pe-badge pe-badge--warn pe-badge--plain">Non signé</span>
        <?php endif; ?>
    </div>
    <div class="pe-workbar__acts">
        <button type="button" class="pe-btn" data-pe-act="classify" data-ids="<?= $id ?>"<?= $canAct ? '' : ' disabled title="' . $h($deny) . '"' ?>>Classifier</button>
        <?php if ($r['lock']['manual']): ?>
            <button type="button" class="pe-btn" data-pe-act="unlock" data-ids="<?= $id ?>"<?= $canAct ? '' : ' disabled title="' . $h($deny) . '"' ?>>Déverrouiller</button>
        <?php else: ?>
            <button type="button" class="pe-btn" data-pe-act="lock" data-ids="<?= $id ?>"<?= $canAct ? '' : ' disabled title="' . $h($deny) . '"' ?>>Verrouiller</button>
        <?php endif; ?>
        <?php if ($r['closed']): ?>
            <button type="button" class="pe-btn" data-pe-act="reopen" data-ids="<?= $id ?>"<?= $canAct ? '' : ' disabled title="' . $h($deny) . '"' ?>>Rouvrir le dossier</button>
        <?php else: ?>
            <button type="button" class="pe-btn" data-pe-act="close" data-ids="<?= $id ?>"<?= $canAct ? '' : ' disabled title="' . $h($deny) . '"' ?>>Clore le dossier</button>
        <?php endif; ?>
        <a class="pe-btn" href="<?= $h(url('atak/sse/identites')) ?>">Retour à la liste</a>
    </div>
    <p>Dernière ouverture par vous : <?= $h($r['my_opened_at'] !== null ? Ui::when($r['my_opened_at'], $now) : 'première ouverture') ?><?= $r['last_read'] !== null ? ' · dernière lecture : ' . $h(trim(($r['last_read']['who'] !== null ? $r['last_read']['who'] . ' · ' : '') . Ui::when($r['last_read']['at'], $now))) : '' ?></p>
</section>
<div class="pe-host">
    <div hidden data-pe-msgs>
        <span data-k="targets">Personnes concernées (%1)</span>
        <span data-k="error">L’action a échoué : réessayez.</span>
        <span data-k="lift">Lever l’avis et clore</span>
        <span data-k="close">Clore le dossier</span>
        <span data-k="wantedblock">Cette personne est sous avis de recherche actif : l’avis doit être levé avant la clôture.</span>
        <span data-k="wantednoright">Vous n’avez pas le droit de lever un avis de recherche : cette clôture est impossible.</span>
        <span data-k="closed">Dossiers clos : %1</span>
        <span data-k="undo">Annuler (%1 s)</span>
        <span data-k="undone">Clôture annulée.</span>
    </div>
    <div class="pe-undo" data-pe-undo role="status" aria-live="polite" hidden>
        <span data-pe-undo-text></span>
        <button type="button" class="pe-btn" data-pe-undo-btn></button>
    </div>
</div>
<?php require __DIR__ . '/_dialogs.php'; ?>
