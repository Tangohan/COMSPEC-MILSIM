<?php
declare(strict_types=1);

/**
 * Liste « Prérequis détectés » d'une qualification : une case par suggestion (cochée d'office si la détection est sûre),
 * valeur modifiable, ce qui la mesure (jeu, dossier, instructeur), la confiance et la phrase source.
 *
 * @var list<array<string, mixed>> $detected suggestions (QualificationPrerequisiteSuggester)
 * @var string $detectedPick nom des cases (ex. « pick » ou « pick[12] »)
 * @var string $detectedValue nom des valeurs (ex. « value » ou « value[12] »)
 * @var array<string, mixed> $criterionNames libellés des cours, qualifications et périmètres
 */
$dl = is_array($detected ?? null) ? $detected : [];
$dPick = (string) ($detectedPick ?? 'pick');
$dValue = (string) ($detectedValue ?? 'value');
$de = static fn (mixed $s): string => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
$levels = ['high' => 'Sûr', 'medium' => 'Probable', 'low' => 'À vérifier'];
?>
<ul class="rq-detect">
    <?php foreach ($dl as $s): $sid = (string) $s['id']; $fid = 'rqd-' . substr(sha1($dPick . $sid), 0, 10); ?>
    <li class="rq-detect__item rq-detect__item--<?= $de($s['level']) ?><?= empty($s['available']) ? ' is-off' : '' ?>">
        <div class="rq-detect__row">
            <input id="<?= $de($fid) ?>" type="checkbox" name="<?= $de($dPick) ?>[]" value="<?= $de($sid) ?>"<?= !empty($s['checked']) ? ' checked' : '' ?><?= empty($s['available']) ? ' disabled' : '' ?>>
            <label class="rq-detect__what" for="<?= $de($fid) ?>">
                <?php if ($s['kind'] === 'criterion'): $criterion = $s['criterion']; ?>
                    <?php require base_path('views/partials/qualification_criterion.php'); ?>
                <?php elseif ($s['kind'] === 'grade'): ?>
                    <span class="qfs-crit"><span>Grade minimum</span> <em translate="no"><?= $de($s['target'] ?? '') ?></em></span>
                <?php else: ?>
                    <span class="qfs-crit"><span>Qualification à détenir</span> <em translate="no"><?= $de($s['target'] ?? '') ?></em></span>
                <?php endif; ?>
            </label>
            <?php if ($s['value'] !== null): ?>
                <label class="rq-detect__val"><span class="sr-only">Valeur demandée</span>
                    <input type="number" min="0" step="any" inputmode="decimal" name="<?= $de($dValue) ?>[<?= $de($sid) ?>]" value="<?= $de(\App\Support\QualificationCriteriaCatalog::formatNumber((float) $s['value'])) ?>"<?= empty($s['available']) ? ' disabled' : '' ?>>
                    <span><?= $de($s['unit']) ?></span>
                </label>
            <?php endif; ?>
        </div>
        <div class="rq-detect__tags">
            <?php if ($s['kind'] === 'grade'): ?><span class="rq-pill">Grade du dossier</span>
            <?php elseif ($s['kind'] === 'qualification'): ?><span class="rq-pill">Prérequis de la fiche</span>
            <?php elseif (($s['criterion']['type'] ?? '') === 'attestation'): ?><span class="rq-pill rq-pill--warn">Attesté par un instructeur</span>
            <?php elseif (($s['criterion']['type'] ?? '') === 'academy'): ?><span class="rq-pill">Formation Academy</span>
            <?php else: ?><span class="rq-pill rq-pill--ok">Mesuré par le site</span><?php endif; ?>
            <span class="rq-pill rq-detect__lvl rq-detect__lvl--<?= $de($s['level']) ?>"><?= $de($levels[$s['level']] ?? '') ?></span>
        </div>
        <?php if (trim((string) $s['source']) !== ''): ?>
            <p class="rq-detect__src"><span>Source :</span> <q translate="no"><?= $de(mb_strimwidth((string) $s['source'], 0, 320, '…')) ?></q></p>
        <?php endif; ?>
        <?php if (trim((string) ($s['note'] ?? '')) !== ''): ?><p class="rq-help"><?= $de($s['note']) ?></p><?php endif; ?>
    </li>
    <?php endforeach; ?>
</ul>
