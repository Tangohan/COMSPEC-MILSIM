<?php
/** @var list<array<string,mixed>> $engineSuggestions */
/** @var list<array<string,mixed>> $engineSignals */
/** @var bool $canManage */
/** @var array<string,mixed> $case */
$h = static fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$engineSuggestions = is_array($engineSuggestions ?? null) ? $engineSuggestions : [];
$engineSignals = is_array($engineSignals ?? null) ? $engineSignals : [];
$caseId = (int) ($case['id'] ?? 0);
?>
<section id="moteur" class="panel sse-ana-panel">
    <div class="panel-header">
        <div class="panel-title"><span class="panel-index">01.14</span> Moteur — propositions</div>
        <div class="panel-header__end">
            <div class="panel-meta">
                <?= count($engineSuggestions) ?> rapprochement(s) · <?= count($engineSignals) ?> signal(aux)
                · <a class="link" href="<?= $h(url('atak/sse/rapprochements') . '?case_id=' . $caseId) ?>">File complète</a>
            </div>
            <?php $sectionKey = '01.14'; require __DIR__ . '/panel_section_info.php'; ?>
        </div>
    </div>
    <div class="panel-body">
        <p class="sse-note">
            Le moteur propose. Il ne décide jamais qu’une hypothèse est vraie.
            Validez ou rejetez chaque rapprochement — les liens validés alimentent le graphe et le registre.
        </p>
        <?php if ($engineSuggestions === [] && $engineSignals === []): ?>
            <p class="muted">Aucune proposition pour ce dossier. Un passage nocturne ou manuel pourra en produire.</p>
        <?php endif; ?>

        <?php if ($engineSuggestions !== []): ?>
            <ul class="sse-sugg-list">
                <?php foreach ($engineSuggestions as $s): ?>
                    <li class="sse-sugg-item sse-sugg-item--<?= $h($s['confidence'] ?? 'possible') ?>">
                        <div>
                            <strong><?= $h($s['title'] ?? '') ?></strong>
                            <span class="sse-ana-tag"><?= $h($s['confidence_label'] ?? '') ?></span>
                            <p><?= $h($s['reason'] ?? '') ?></p>
                        </div>
                        <?php if ($canManage): ?>
                            <div class="sse-sugg-actions">
                                <form method="post" action="<?= $h(url('atak/sse/rapprochements/' . (int) $s['id'] . '/valider')) ?>">
                                    <?= \App\Core\Csrf::field() ?>
                                    <input type="hidden" name="case_id" value="<?= $caseId ?>">
                                    <button class="btn btn--sm" type="submit">Valider</button>
                                </form>
                                <form method="post" action="<?= $h(url('atak/sse/rapprochements/' . (int) $s['id'] . '/rejeter')) ?>">
                                    <?= \App\Core\Csrf::field() ?>
                                    <input type="hidden" name="case_id" value="<?= $caseId ?>">
                                    <button class="btn btn--ghost btn--sm" type="submit">Rejeter</button>
                                </form>
                            </div>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>

        <?php if ($engineSignals !== []): ?>
            <ul class="sse-ana-suggest" style="margin-top:1rem">
                <?php foreach (array_slice($engineSignals, 0, 8) as $sig): ?>
                    <li class="sse-ana-suggest__item">
                        <strong><?= $h($sig['title'] ?? '') ?></strong>
                        <?php if (!empty($sig['detail'])): ?><em><?= $h($sig['detail']) ?></em><?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
</section>
