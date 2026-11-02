<?php
/**
 * Carte d’une proposition : $prow (ligne de AdvancementProposalService::forTarget), $pcampaignId, $pcanAct (campagne
 * ouverte : retenir / écarter possibles), $pmode ('pending' | 'dismissed' | 'candidate').
 */
use App\Services\Advancement\AdvancementProposalEngine as Engine;

$p = is_array($prow['proposal'] ?? null) ? $prow['proposal'] : [];
$pstatus = (string) ($p['status'] ?? '');
$pmode = (string) ($pmode ?? 'pending');
$pid = (int) ($prow['personnel_id'] ?? 0);
$ptone = match ($pstatus) {
    Engine::STATUS_PROPOSE => 'ok',
    Engine::STATUS_REVIEW => 'wait',
    Engine::STATUS_BLOCKED => 'bad',
    default => 'quiet',
};
$pscore = (int) ($p['score'] ?? 0);
$since = trim((string) ($prow['obtained_at'] ?? ''));
$decision = is_array($prow['decision'] ?? null) ? $prow['decision'] : null;
?>
<article class="advc-prop advc-prop--<?= adv_h($ptone) ?>" id="proposition-<?= $pid ?>">
    <header class="advc-prop__head">
        <div class="advc-prop__who">
            <strong><?= adv_h((string) ($prow['name'] ?? '')) ?></strong>
            <span class="advc-muted"><?= adv_h((string) ($prow['current_label'] ?? '')) ?><?php if ($since !== ''): ?> depuis le <?= adv_h(adv_date($since)) ?><?php endif; ?></span>
        </div>
        <div class="advc-prop__score" title="Score de la détection">
            <span class="advc-meter" aria-hidden="true"><span style="width: <?= max(0, min(100, $pscore)) ?>%"></span></span>
            <b><?= $pscore ?></b><span class="advc-muted">/100</span>
        </div>
        <span class="adv-badge adv-badge--<?= adv_h($ptone) ?>"><?= adv_h(Engine::statusLabel($pstatus)) ?><?php if (!empty($p['near'])): ?> · bientôt<?php endif; ?></span>
    </header>

    <?php if (!empty($p['missing'])): ?>
        <ul class="advc-missing" aria-label="Ce qui manque">
            <?php foreach ($p['missing'] as $line): ?>
                <li><?= adv_h((string) $line) ?></li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>

    <details class="advc-details advc-details--points">
        <summary>Détail des points</summary>
        <ul class="advc-points">
            <?php foreach (is_array($p['criteria'] ?? null) ? $p['criteria'] : [] as $c): ?>
                <?php $met = $c['met'] ?? null; $cls = !empty($c['blocking']) ? 'is-bad' : ($met === false ? 'is-warn' : 'is-ok'); ?>
                <li class="<?= $cls ?>">
                    <span class="advc-points__label"><?= adv_h((string) $c['label']) ?></span>
                    <span class="advc-points__val"><?= (int) $c['points'] ?> / <?= (int) $c['max'] ?></span>
                    <span class="advc-points__detail"><span><?= adv_h((string) $c['detail']) ?></span><?php if (trim((string) ($c['hint'] ?? '')) !== ''): ?> <span class="advc-points__hint"><?= adv_h((string) $c['hint']) ?></span><?php endif; ?></span>
                </li>
            <?php endforeach; ?>
            <?php if (empty($p['criteria'])): ?>
                <li><span class="advc-points__detail">Aucun critère mesuré pour ce membre.</span></li>
            <?php endif; ?>
        </ul>
    </details>

    <?php if ($pmode === 'candidate'): ?>
        <p class="advc-muted">Déjà candidat dans cette campagne<?php if ($decision !== null && ($decision['decision'] ?? '') === 'retenue'): ?> (proposition retenue le <?= adv_h(adv_date((string) ($decision['decided_at'] ?? ''))) ?>)<?php endif; ?>.</p>
    <?php elseif ($pmode === 'dismissed'): ?>
        <p class="advc-muted">Écartée le <?= adv_h(adv_date((string) ($decision['decided_at'] ?? ''))) ?><?php if (trim((string) ($decision['justification'] ?? '')) !== ''): ?> : <?= adv_h((string) $decision['justification']) ?><?php endif; ?></p>
        <?php if (!empty($pcanAct)): ?>
            <form method="post" action="<?= adv_h(url('back-office/rh/avancement/' . (int) $pcampaignId . '/propositions/retablir')) ?>">
                <?= \App\Core\Csrf::field() ?>
                <input type="hidden" name="personnel_id" value="<?= $pid ?>">
                <button class="ath-btn" type="submit">Rétablir</button>
            </form>
        <?php endif; ?>
    <?php elseif (!empty($pcanAct) && $pstatus !== Engine::STATUS_INELIGIBLE): ?>
        <div class="advc-prop__acts">
            <form method="post" action="<?= adv_h(url('back-office/rh/avancement/' . (int) $pcampaignId . '/propositions/retenir')) ?>" class="advc-prop__keep">
                <?= \App\Core\Csrf::field() ?>
                <input type="hidden" name="personnel_id" value="<?= $pid ?>">
                <label class="advc-field">
                    <span class="advc-field__label">Justification (versée au dossier de candidature)</span>
                    <textarea name="justification" rows="2" maxlength="2000"><?= adv_h((string) ($p['justification'] ?? '')) ?></textarea>
                </label>
                <button class="ath-btn ath-btn--solid" type="submit">Retenir</button>
            </form>
            <details class="advc-details advc-prop__drop">
                <summary>Écarter</summary>
                <form method="post" action="<?= adv_h(url('back-office/rh/avancement/' . (int) $pcampaignId . '/propositions/ecarter')) ?>">
                    <?= \App\Core\Csrf::field() ?>
                    <input type="hidden" name="personnel_id" value="<?= $pid ?>">
                    <label class="advc-field">
                        <span class="advc-field__label">Motif (facultatif)</span>
                        <input type="text" name="reason" maxlength="500" placeholder="Ex. vu en entretien, pas candidat cette année">
                    </label>
                    <button class="ath-btn" type="submit">Écarter la proposition</button>
                </form>
            </details>
        </div>
    <?php endif; ?>
</article>
