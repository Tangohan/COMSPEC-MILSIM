<?php
declare(strict_types=1);
/** @var array<string,mixed> $analysis */
/** @var callable $h */
/** @var bool $canManage */
/** @var string $csrfToken */
/** @var int $selectedCaseId */
$analysis = is_array($analysis ?? null) ? $analysis : [];
$pol = is_array($analysis['pattern_of_life'] ?? null) ? $analysis['pattern_of_life'] : [];
$heatmap = is_array($analysis['heatmap'] ?? null) ? $analysis['heatmap'] : [];
$contradictions = is_array($analysis['contradictions'] ?? null) ? $analysis['contradictions'] : [];
$rapprochements = is_array($analysis['rapprochements'] ?? null) ? $analysis['rapprochements'] : [];
$anomalies = is_array($analysis['anomalies'] ?? null) ? $analysis['anomalies'] : [];
$counts = is_array($analysis['counts'] ?? null) ? $analysis['counts'] : [];
$cells = is_array($heatmap['cells'] ?? null) ? $heatmap['cells'] : [];
$caseId = (int) ($selectedCaseId ?? 0);
$byHour = is_array($pol['by_hour'] ?? null) ? $pol['by_hour'] : [];
$maxHour = max(1, ...(array_map('intval', array_values($byHour)) ?: [1]));
?>
<header class="iw-intel-col-head">
    <h2>Analyse</h2>
    <span class="iw-folder-meta">
        <span><?= (int) ($counts['contradictions'] ?? 0) ?> contradictions</span>
        <span><?= (int) ($counts['rapprochements'] ?? 0) ?> rapprochements</span>
        <span><?= (int) ($counts['anomalies'] ?? 0) ?> anomalies</span>
    </span>
</header>

<p class="muted" style="margin:0 0 12px">
    Rythme d’activité, densités de présence, contradictions et rapprochements —
    propositions automatiques à arbitrer, jamais des faits confirmés.
</p>

<section class="iw-cycle-block">
    <h3>Rythme d’activité (Pattern of Life)</h3>
    <p><?= $h((string) ($pol['summary'] ?? 'Pas encore de profil.')) ?></p>
    <?php if ($byHour !== []): ?>
        <div class="iw-pol-bars" aria-label="Activité par heure UTC">
            <?php for ($hh = 0; $hh < 24; $hh++): ?>
                <?php
                $c = (int) ($byHour[$hh] ?? 0);
                $pct = (int) round(100 * $c / $maxHour);
                ?>
                <div class="iw-pol-bar" title="<?= $h(sprintf('%02dh — %d', $hh, $c)) ?>">
                    <span style="height:<?= max(4, $pct) ?>%"></span>
                    <?php if ($hh % 6 === 0): ?><em><?= $hh ?></em><?php endif; ?>
                </div>
            <?php endfor; ?>
        </div>
    <?php endif; ?>
    <?php
    $peakDays = is_array($pol['peak_weekdays'] ?? null) ? $pol['peak_weekdays'] : [];
    if ($peakDays !== []):
    ?>
        <p class="record-sub">Jours les plus actifs :
            <?php foreach ($peakDays as $pd): ?>
                <?php if (!is_array($pd)) {
                    continue;
                } ?>
                <strong><?= $h((string) ($pd['label'] ?? '')) ?></strong>
                (<?= (int) ($pd['count'] ?? 0) ?>)
            <?php endforeach; ?>
        </p>
    <?php endif; ?>
</section>

<section class="iw-cycle-block">
    <h3>Carte de densité</h3>
    <p><?= $h((string) ($heatmap['summary'] ?? '')) ?></p>
    <?php if ($cells === []): ?>
        <p class="iw-intel-empty">Aucune cellule de densité (positions manquantes sur la période).</p>
    <?php else: ?>
        <ul class="iw-intel-list iw-intel-list--compact">
            <?php foreach (array_slice($cells, 0, 10) as $cell): ?>
                <?php if (!is_array($cell)) {
                    continue;
                } ?>
                <li>
                    <strong>Zone</strong>
                    X <?= $h(number_format((float) ($cell['x'] ?? 0), 0, ',', ' ')) ?>
                    · Y <?= $h(number_format((float) ($cell['y'] ?? 0), 0, ',', ' ')) ?>
                    <em><?= (int) ($cell['count'] ?? 0) ?> obs. · intensité <?= $h((string) ($cell['intensity'] ?? 0)) ?></em>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</section>

<section class="iw-cycle-block">
    <h3>Contradictions</h3>
    <?php if ($contradictions === []): ?>
        <p class="iw-intel-empty">Aucune contradiction ouverte.</p>
    <?php else: ?>
        <ul class="iw-intel-list">
            <?php foreach ($contradictions as $cx): ?>
                <?php if (!is_array($cx)) {
                    continue;
                } ?>
                <li>
                    <strong><?= $h((string) ($cx['title'] ?? '')) ?></strong>
                    <em><?= $h((string) ($cx['severity_label'] ?? '')) ?> · <?= $h((string) ($cx['confidence_label_fr'] ?? '')) ?></em>
                    <span class="record-sub"><?= $h((string) ($cx['explanation'] ?? '')) ?></span>
                    <?php if ($canManage && ($cx['status'] ?? '') === 'ouvert'): ?>
                        <div class="iw-cycle-product-actions">
                            <form method="post" action="<?= $h(url('atak/sse/workspace/analyse/constats/' . (int) ($cx['id'] ?? 0))) ?>">
                                <input type="hidden" name="_csrf_token" value="<?= $h($csrfToken) ?>">
                                <input type="hidden" name="case_id" value="<?= (int) $caseId ?>">
                                <input type="hidden" name="decision" value="retenu">
                                <button type="submit" class="iw-btn">Retenir</button>
                            </form>
                            <form method="post" action="<?= $h(url('atak/sse/workspace/analyse/constats/' . (int) ($cx['id'] ?? 0))) ?>">
                                <input type="hidden" name="_csrf_token" value="<?= $h($csrfToken) ?>">
                                <input type="hidden" name="case_id" value="<?= (int) $caseId ?>">
                                <input type="hidden" name="decision" value="ecarte">
                                <button type="submit" class="iw-btn">Écarter</button>
                            </form>
                        </div>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</section>

<section class="iw-cycle-block">
    <h3>Rapprochements</h3>
    <?php if ($rapprochements === []): ?>
        <p class="iw-intel-empty">Aucun rapprochement en attente.</p>
    <?php else: ?>
        <ul class="iw-intel-list">
            <?php foreach ($rapprochements as $rp): ?>
                <?php if (!is_array($rp)) {
                    continue;
                } ?>
                <li>
                    <strong><?= $h((string) ($rp['title'] ?? '')) ?></strong>
                    <em><?= $h((string) ($rp['kind_label'] ?? 'Rapprochement')) ?></em>
                    <span class="record-sub"><?= $h((string) ($rp['explanation'] ?? '')) ?></span>
                    <?php if (!empty($rp['href'])): ?>
                        <a class="link" href="<?= $h((string) $rp['href']) ?>">Examiner</a>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</section>

<section class="iw-cycle-block">
    <h3>Anomalies explicables</h3>
    <?php if ($anomalies === []): ?>
        <p class="iw-intel-empty">Aucune anomalie détectée sur la période.</p>
    <?php else: ?>
        <ul class="iw-intel-list">
            <?php foreach ($anomalies as $an): ?>
                <?php if (!is_array($an)) {
                    continue;
                } ?>
                <li>
                    <strong><?= $h((string) ($an['title'] ?? '')) ?></strong>
                    <em><?= $h((string) ($an['finding_type_label'] ?? 'Anomalie')) ?> · <?= $h((string) ($an['severity_label'] ?? '')) ?></em>
                    <span class="record-sub"><?= $h((string) ($an['explanation'] ?? '')) ?></span>
                    <?php if ($canManage && ($an['status'] ?? '') === 'ouvert'): ?>
                        <div class="iw-cycle-product-actions">
                            <form method="post" action="<?= $h(url('atak/sse/workspace/analyse/constats/' . (int) ($an['id'] ?? 0))) ?>">
                                <input type="hidden" name="_csrf_token" value="<?= $h($csrfToken) ?>">
                                <input type="hidden" name="case_id" value="<?= (int) $caseId ?>">
                                <input type="hidden" name="decision" value="retenu">
                                <button type="submit" class="iw-btn">Retenir</button>
                            </form>
                            <form method="post" action="<?= $h(url('atak/sse/workspace/analyse/constats/' . (int) ($an['id'] ?? 0))) ?>">
                                <input type="hidden" name="_csrf_token" value="<?= $h($csrfToken) ?>">
                                <input type="hidden" name="case_id" value="<?= (int) $caseId ?>">
                                <input type="hidden" name="decision" value="ecarte">
                                <button type="submit" class="iw-btn">Écarter</button>
                            </form>
                        </div>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</section>
