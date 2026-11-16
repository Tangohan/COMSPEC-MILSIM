<?php
declare(strict_types=1);

use App\Services\Sse\Detection\SseDetectionEngine;
use App\Services\Sse\Detection\SseDetectionService as D;

/**
 * Résultat de « Testez un texte » (page Détections Cerbère) : rendu dans la page, ou renvoyé seul au script (JSON {html}).
 * Chaque détection avec ses passages surlignés, sa règle, sa confiance et ce qu'elle deviendrait sur une remontée ; puis ce qui
 * a été écarté (exclusion, négation, contexte requis absent, faux ami).
 *
 * @var array{text: string, kind: string, analysis: array<string, mixed>, detections: list<array<string, mixed>>, ms: float}|null $cdxTest
 */
$rh = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
if (!is_array($cdxTest ?? null)) {
    return;
}
$quote = static fn (array $e): string => '<q class="cdx-q">' . $rh($e['before']) . '<mark>' . $rh($e['match']) . '</mark>' . $rh($e['after']) . '</q>';
$tierLabel = ['visible' => 'serait proposée', 'review' => 'serait repliée « à vérifier »', 'hidden' => 'sous le seuil : non proposée'];
$band = static fn (int $c): string => $c >= 75 ? 'high' : ($c >= 55 ? 'mid' : 'low');
$set = [];
foreach ($cdxTest['analysis']['rules'] as $r) {
    $lost = array_values(array_filter($r['hits'], static fn (array $h): bool => in_array($h['context'], ['negation', 'hypothesis'], true)));
    if ($r['excluded'] !== [] || $r['false_friends'] !== [] || $r['missing_context'] || $lost !== []) {
        $set[] = $r + ['lost' => $lost];
    }
}
?>
<div class="cdx-result">
    <h3 class="cdx-panel__sub" tabindex="-1">Résultat : <?= (int) $cdxTest['analysis']['tokens'] ?> mots lus en <?= $rh(number_format((float) $cdxTest['ms'], 1, ',', ' ')) ?> ms</h3>
    <?php if ($cdxTest['detections'] === []): ?>
        <p class="cdx-result__none">Aucune détection : ce texte ne déclencherait aucune proposition.</p>
    <?php endif; ?>
    <?php foreach ($cdxTest['detections'] as $d): $ev = D::evidence($d['rules']); $c = (int) $d['confidence']; ?>
        <article class="cdx-card cdx-card--<?= $rh($d['tier']) ?>">
            <div class="cdx-card__head">
                <p class="cdx-card__what"><strong><?= $rh(D::targetLabel($d['target'])) ?> : <?= $rh(D::valueLabel($d['target'], $d['value'])) ?></strong></p>
                <span class="cdx-conf cdx-conf--<?= $band($c) ?>"><?= $c ?> %</span>
            </div>
            <p class="cdx-card__line"><span class="cdx-k">Détecté :</span>
                <?php foreach ($ev as $i => $e): ?><?= $i > 0 ? ' ' : '' ?><?= $quote($e) ?><?php if ($e['context'] !== 'normal'): ?> <span class="cdx-ctx">(<?= $rh(SseDetectionEngine::contextLabel($e['context'])) ?>)</span><?php endif; ?><?php endforeach; ?>
            </p>
            <p class="cdx-card__meta"><?= count($d['rules']) > 1 ? 'Règles' : 'Règle' ?> <span class="cdx-mono" translate="no"><?= $rh(implode(', ', array_column($d['rules'], 'id'))) ?></span> · <?= $rh($tierLabel[$d['tier']] ?? '') ?></p>
            <ul class="cdx-signals">
                <?php foreach ($d['signals'] as $s): ?><li><span><?= $rh($s['label']) ?></span><strong><?= (int) $s['points'] > 0 ? '+' . (int) $s['points'] : '—' ?></strong></li><?php endforeach; ?>
            </ul>
        </article>
    <?php endforeach; ?>
    <?php if ($set !== []): ?>
        <h4 class="cdx-panel__sub">Écarté par les garde-fous</h4>
        <ul class="cdx-hits">
            <?php foreach ($set as $r): ?>
                <?php foreach ($r['excluded'] as $x): ?><li><?= $quote($x['snippet']) ?><span class="cdx-dim"><span translate="no"><?= $rh($r['id']) ?></span> · exclusion « <?= $rh($x['by']) ?> »</span></li><?php endforeach; ?>
                <?php foreach ($r['lost'] as $x): ?><li><?= $quote($x['snippet']) ?><span class="cdx-dim"><span translate="no"><?= $rh($r['id']) ?></span> · <?= $rh(SseDetectionEngine::contextLabel($x['context'])) ?> (« <?= $rh((string) $x['cue']) ?> ») : confiance réduite<?= $r['target'] === 'urgency' ? ', ne compte pas pour l’urgence' : '' ?></span></li><?php endforeach; ?>
                <?php if ($r['missing_context']): ?><li><span><span translate="no"><?= $rh($r['id']) ?></span> · terme trouvé sans son contexte requis (<?= $rh(implode(', ', $r['requires'])) ?>)</span></li><?php endif; ?>
                <?php if ($r['false_friends'] !== []): ?><li><span><span translate="no"><?= $rh($r['id']) ?></span> · faux amis ignorés : <?= $rh(implode(', ', $r['false_friends'])) ?></span></li><?php endif; ?>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</div>
