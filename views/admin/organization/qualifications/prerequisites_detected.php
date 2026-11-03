<?php
declare(strict_types=1);

/**
 * Aperçu de masse « Prérequis détectés » : chaque qualification du référentiel (ou celles qui viennent d'être
 * importées) avec ce que son texte laisse lire. Les détections sûres sont cochées ; rien n'est ajouté sans validation.
 *
 * @var list<array{definition: array<string, mixed>, suggestions: list<array<string, mixed>>}> $rows
 * @var list<int> $filterIds
 * @var bool $afterImport
 * @var string $backUrl
 * @var array<string, mixed> $criterionNames
 * @var bool $ready
 */
$rows = is_array($rows ?? null) ? $rows : [];
$filterIds = is_array($filterIds ?? null) ? $filterIds : [];
$h = static fn (mixed $s): string => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
$flashSuccess = \App\Core\Session::getFlash('success');
$flashError = \App\Core\Session::getFlash('error');
$total = 0;
$sure = 0;
foreach ($rows as $r) {
    $total += count($r['suggestions']);
    $sure += count(array_filter($r['suggestions'], static fn (array $s): bool => !empty($s['checked'])));
}
?>
<div class="rq">
    <header class="rq-top">
        <a href="<?= $h($backUrl ?? url('back-office/referentiels/qualifications')) ?>" class="rq-back">
            <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true"><path d="M15 6l-6 6 6 6"/></svg>
            <?php if (!empty($afterImport)): ?>Retour au catalogue<?php else: ?>Référentiel des qualifications<?php endif; ?>
        </a>
        <div class="rq-top__row">
            <div>
                <p class="rq-kicker">Personnel · Qualifications</p>
                <h1>Prérequis détectés</h1>
                <p class="rq-lead"><?php if (!empty($afterImport)): ?><span>Les qualifications importées citent des conditions : sauts, heures de vol, grade, autre qualification…</span><?php else: ?><span>Conditions lues dans la description de vos qualifications et dans leur fiche du catalogue : sauts, heures de vol, missions, ancienneté, grade, autre qualification…</span><?php endif; ?>
                    <span>Les plus sûres sont cochées ; corrigez une valeur si besoin, décochez ce qui ne convient pas, puis ajoutez. Rien n’est ajouté sans votre accord.</span></p>
            </div>
        </div>
    </header>
    <?php if ($flashSuccess): ?><p class="rq-flash rq-flash--ok" role="status"><?= $h($flashSuccess) ?></p><?php endif; ?>
    <?php if ($flashError): ?><p class="rq-flash rq-flash--err" role="alert"><?= $h($flashError) ?></p><?php endif; ?>
    <?php if (empty($ready)): ?><p class="rq-flash rq-flash--err" role="status">Les modes d’obtention arrivent avec la prochaine mise à jour du site : l’ajout sera possible ensuite.</p><?php endif; ?>

    <section class="rq-card rq-card--wide" aria-labelledby="rqd-title">
        <h2 id="rqd-title">À valider <span class="rq-count"><?= (int) $total ?></span></h2>
        <ul class="rq-help">
            <li><b>Mesuré par le site</b> : sauts en parachute, heures de vol, missions jouées (mod ATAK), ancienneté (dossier) ; la qualification est proposée dès que le seuil est atteint.</li>
            <li><b>Attesté par un instructeur</b> : ce que le jeu ne mesure pas (sauts en chute libre, plongée, tir, tests physiques), validé membre par membre sur la fiche de la qualification.</li>
            <li>Une qualification sans règle automatique passe en « proposée, un responsable valide » ; elle reste délivrable à la main.</li>
        </ul>
        <?php if ($rows === []): ?>
            <p class="rq-muted">Aucun prérequis détecté : rien à ajouter.</p>
        <?php else: ?>
        <form method="post" action="<?= $h(url('back-office/referentiels/qualifications/prerequis-detectes')) ?>">
            <?= \App\Core\Csrf::field() ?>
            <input type="hidden" name="ids" value="<?= $h(implode(',', $filterIds)) ?>">
            <?php foreach ($rows as $r): $d = $r['definition']; $defId = (int) $d['id']; ?>
                <div class="rq-detect-def">
                    <h3><a href="<?= $h(url('back-office/referentiels/qualifications/' . $defId . '/edit')) ?>#prerequis-detectes" translate="no"><?= $h($d['name'] ?? '') ?></a> <span class="rq-pill" translate="no"><?= $h($d['code'] ?? '') ?></span></h3>
                    <?php
                    $detected = $r['suggestions'];
                    $detectedPick = 'pick[' . $defId . ']';
                    $detectedValue = 'value[' . $defId . ']';
                    require __DIR__ . '/_detected_prerequisites.php';
                    ?>
                </div>
            <?php endforeach; ?>
            <div class="rq-detect-actions">
                <button class="rq-btn rq-btn--primary" type="submit"<?= empty($ready) ? ' disabled' : '' ?>>Ajouter les prérequis cochés</button>
                <span class="rq-muted"><?= (int) $sure ?> cochés d’office sur <?= (int) $total ?></span>
                <a class="rq-btn" href="<?= $h($backUrl ?? url('back-office/referentiels/qualifications')) ?>">Plus tard</a>
            </div>
        </form>
        <?php endif; ?>
    </section>
</div>
