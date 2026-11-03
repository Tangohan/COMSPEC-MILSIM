<?php
declare(strict_types=1);

/**
 * Qualifications et brevets suggérés à un membre (fiche membre du back-office ; « Mes qualifications » en lecture).
 * Rendu dans une fonction : aucune variable de la page qui l'inclut n'est écrasée.
 *
 * @var array<string, mixed>|null $qualificationSuggestions MemberQualificationSuggestionService::forMember()
 * @var array{import?: bool, award?: bool, enroll?: bool, dismiss?: bool} $qualificationSuggestionRights
 * @var int $qualificationSuggestionUserId
 * @var array<string, mixed> $progressNames
 */
(static function (array $qs, array $can, int $memberId, array $criterionNames): void {
    $items = is_array($qs['items'] ?? null) ? $qs['items'] : [];
    $dismissed = is_array($qs['dismissed'] ?? null) ? $qs['dismissed'] : [];
    $e = static fn (mixed $s): string => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
    $States = \App\Services\Personnel\MemberQualificationSuggester::class;
    $stateLabels = [
        $States::STATE_PENDING => ['En cours (formation ou évaluation)', 'info'],
        $States::STATE_ACADEMY => ['En cours de formation Academy', 'info'],
        $States::STATE_READY => ['Prête à obtenir', 'ok'],
        $States::STATE_MISSING => ['Conditions manquantes', 'warn'],
        $States::STATE_NOT_IMPORTED => ['Pas encore dans le référentiel', 'muted'],
    ];
    $canDismiss = !empty($can['dismiss']) && !empty($qs['dismiss_ready']);
    $jobs = is_array($qs['jobs'] ?? null) ? $qs['jobs'] : [];
    $natureLabels = is_array($qs['nature_labels'] ?? null) ? $qs['nature_labels'] : [];
    $dismissUrl = url('back-office/ressources/effectifs/membres/' . $memberId . '/qualifications-suggerees');
    ?>
<div class="qsg" aria-label="Qualifications et brevets suggérés">
    <?php if ($jobs !== [] || $natureLabels !== []): ?>
    <p class="qsg-detected">
        <span>Détecté :</span>
        <?php foreach (array_slice($jobs, 0, 3) as $j): ?>
            <span class="qsg-chip"><span>emploi</span> <b translate="no"><?= $e($j['label']) ?></b><?php if (($j['country'] ?? '') !== ''): ?> <small translate="no">(<?= $e($j['country']) ?>)</small><?php endif; ?></span>
        <?php endforeach; ?>
        <?php foreach ($natureLabels as $n): ?>
            <span class="qsg-chip"><?= $e($n) ?></span>
        <?php endforeach; ?>
    </p>
    <?php endif; ?>
    <?php if ($items === []): ?>
        <p class="qsg-empty">Aucune suggestion : renseignez la fonction et l’unité d’affectation du membre pour en obtenir.</p>
    <?php else: ?>
    <ol class="qsg-list">
        <?php foreach ($items as $it):
            [$stateText, $tone] = $stateLabels[$it['state']] ?? ['', 'muted'];
            ?>
        <li class="qsg-item is-<?= $e($tone) ?>">
            <div class="qsg-head">
                <b translate="no"><?= $e($it['name']) ?></b>
                <?php if ($it['official_name'] !== '' && $it['official_name'] !== $it['name']): ?><small translate="no"><?= $e($it['official_name']) ?></small><?php endif; ?>
                <span class="qfs-tag qfs-tag--<?= $tone === 'ok' ? 'ok' : 'info' ?>"><?= $e($stateText) ?></span>
            </div>
            <p class="qsg-meta">
                <?php if ($it['category'] !== ''): ?><span><?= $e($it['category']) ?></span><?php endif; ?>
                <?php if ($it['country'] !== ''): ?><span translate="no"><?= $e($it['country']) ?></span><?php endif; ?>
                <span class="qsg-score" title="Pertinence"><span>pertinence</span> <?= (int) $it['points'] ?></span>
            </p>
            <ul class="qsg-reasons">
                <?php foreach ($it['reasons'] as $r): ?>
                <li><span><?= $e($r['text']) ?></span><?php if ($r['subject'] !== ''): ?> <b<?= $r['kind'] === 'nature' ? '' : ' translate="no"' ?>><?= $e($r['subject']) ?></b><?php endif; ?></li>
                <?php endforeach; ?>
            </ul>
            <?php if ($it['missing'] !== []): ?>
            <ul class="qsg-missing">
                <?php foreach (array_slice($it['missing'], 0, 5) as $m): ?>
                <li>
                    <span><?= $e($m['text']) ?></span><?php if (is_array($m['criterion'] ?? null)): ?><span> :</span>
                        <?php $criterion = $m['criterion']; require __DIR__ . '/qualification_criterion.php'; ?>
                    <?php elseif (($m['subject'] ?? '') !== ''): ?><span> :</span> <b translate="no"><?= $e($m['subject']) ?></b><?php endif; ?>
                </li>
                <?php endforeach; ?>
            </ul>
            <?php endif; ?>
            <?php foreach ($it['notes'] as $note): ?><p class="qsg-note"><?= $e($note) ?></p><?php endforeach; ?>
            <?php if (is_array($it['course'])): ?>
            <p class="qsg-course">
                <span><?= $it['course']['enrolled'] ? ($it['course']['completed'] ? 'Cours Academy terminé :' : 'Inscrit au cours Academy :') : 'Cours Academy qui y mène :' ?></span>
                <b translate="no"><?= $e($it['course']['title']) ?></b>
            </p>
            <?php endif; ?>
            <?php if (array_filter($can) !== []): ?>
            <div class="qsg-acts">
                <?php if ($it['state'] === $States::STATE_NOT_IMPORTED && !empty($can['import']) && $it['catalog_id'] !== ''): ?>
                <form method="post" action="<?= $e(url('back-office/referentiels/qualifications/catalogue/importer')) ?>">
                    <?= \App\Core\Csrf::field() ?>
                    <input type="hidden" name="one" value="<?= $e($it['catalog_id']) ?>">
                    <input type="hidden" name="pack" value="<?= $e($it['pack']) ?>">
                    <input type="hidden" name="return_member" value="<?= $memberId ?>">
                    <button type="submit" class="eff-btn eff-btn--sm">Importer dans le référentiel</button>
                </form>
                <?php endif; ?>
                <?php if (is_array($it['course']) && !$it['course']['enrolled'] && !empty($can['enroll'])): ?>
                <form method="post" action="<?= $e(url('academy/action/assign')) ?>">
                    <?= \App\Core\Csrf::field() ?>
                    <input type="hidden" name="course" value="<?= $e($it['course']['key']) ?>">
                    <input type="hidden" name="member" value="<?= $memberId ?>">
                    <input type="hidden" name="return" value="membre">
                    <button type="submit" class="eff-btn eff-btn--sm">Inscrire au cours Academy</button>
                </form>
                <?php endif; ?>
                <?php if ((int) $it['definition_id'] > 0 && $it['allow_manual'] && !empty($can['award']) && $it['state'] !== $States::STATE_NOT_IMPORTED): ?>
                    <?php foreach ($it['state'] === $States::STATE_PENDING ? ['ok'] : ['ok', 'train'] as $target): ?>
                <form method="post" action="<?= $e(url('back-office/referentiels/qualifications/matrice/case')) ?>">
                    <?= \App\Core\Csrf::field() ?>
                    <input type="hidden" name="definition_id" value="<?= (int) $it['definition_id'] ?>">
                    <input type="hidden" name="user_id" value="<?= $memberId ?>">
                    <input type="hidden" name="target" value="<?= $e($target) ?>">
                    <input type="hidden" name="return_to" value="member">
                    <button type="submit" class="eff-btn eff-btn--sm<?= $target === 'train' ? ' eff-btn--ghost' : '' ?>"><?= $target === 'ok' ? ($it['state'] === $States::STATE_PENDING ? 'Valider' : 'Délivrer') : 'Mettre en formation' ?></button>
                </form>
                    <?php endforeach; ?>
                <?php endif; ?>
                <?php if ($canDismiss): ?>
                <form method="post" action="<?= $e($dismissUrl) ?>">
                    <?= \App\Core\Csrf::field() ?>
                    <input type="hidden" name="key" value="<?= $e($it['key']) ?>">
                    <button type="submit" class="eff-btn eff-btn--sm eff-btn--ghost">Ignorer pour ce membre</button>
                </form>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </li>
        <?php endforeach; ?>
    </ol>
    <?php endif; ?>
    <?php if ((int) ($qs['held'] ?? 0) > 0): ?>
        <p class="qsg-held"><span>Qualifications déjà détenues, non reproposées :</span> <?= (int) $qs['held'] ?></p>
    <?php endif; ?>
    <?php if ($dismissed !== [] && $canDismiss): ?>
    <details class="qsg-dismissed">
        <summary><span>Suggestions ignorées</span> (<?= count($dismissed) ?>)</summary>
        <ul>
            <?php foreach ($dismissed as $it): ?>
            <li>
                <b translate="no"><?= $e($it['name']) ?></b>
                <form method="post" action="<?= $e($dismissUrl) ?>">
                    <?= \App\Core\Csrf::field() ?>
                    <input type="hidden" name="key" value="<?= $e($it['key']) ?>">
                    <input type="hidden" name="restore" value="1">
                    <button type="submit" class="eff-btn eff-btn--sm eff-btn--ghost">Reprendre</button>
                </form>
            </li>
            <?php endforeach; ?>
        </ul>
    </details>
    <?php endif; ?>
</div>
    <?php
})(
    is_array($qualificationSuggestions ?? null) ? $qualificationSuggestions : [],
    is_array($qualificationSuggestionRights ?? null) ? $qualificationSuggestionRights : [],
    (int) ($qualificationSuggestionUserId ?? 0),
    is_array($progressNames ?? null) ? $progressNames : []
);
