<?php
declare(strict_types=1);
/**
 * Carte de la file en colonnes. Attend $card (ligne enrichie portant next_action), $pipelineReturnQs (état de la file),
 * $rpInitials et $rpAgo (fonctions de la vue).
 * @var array<string, mixed> $card
 */
$rpH = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$na = (array) ($card['next_action'] ?? []);
$cid = (int) ($card['id'] ?? 0);
$cfn = (string) ($card['first_name'] ?? '');
$cln = (string) ($card['last_name'] ?? '');
$cname = trim($cfn . ' ' . $cln) ?: 'Candidat sans nom';
$urg = preg_replace('/[^a-z]/', '', (string) ($na['urgency'] ?? 'none')) ?: 'none';
$owner = (string) ($na['owner'] ?? 'nobody');
$ownerLabel = (string) ($na['ownerLabel'] ?? '');
$dossier = url('back-office/recruitments/' . $cid . '?dossier=1');
$anchor = (string) ($na['anchor'] ?? '');
$tags = array_values(array_filter([
    trim((string) ($card['assignment_unit_label'] ?? '')),
    trim((string) ($card['assignment_role_label'] ?? '')),
]));
$opening = trim((string) ($card['assignment_opening_title'] ?? ''));
if ($tags === [] && $opening !== '') {
    $tags[] = $opening;
}
$ownerMark = match ($owner) {
    'you' => 'V',
    'other' => $rpInitials(...array_pad(explode(' ', $ownerLabel, 2), 2, '')),
    default => '?',
};
?>
<article class="rp-card rp-card--<?= $rpH($urg) ?>">
    <header class="rp-card__head">
        <span class="rp-avatar" aria-hidden="true"><?= $rpH($rpInitials($cfn, $cln)) ?></span>
        <div class="rp-card__id">
            <a class="rp-card__name" href="<?= $rpH($dossier) ?>" title="<?= $rpH($cname) ?>"><?= $rpH($cname) ?></a>
            <span class="rp-card__sub">#<?= $cid ?> · reçu <?= $rpH($rpAgo((string) ($card['created_at'] ?? ''))) ?><?= empty($card['submitter_user_id']) ? ' · invité' : '' ?></span>
        </div>
    </header>
    <div class="rp-card__action">
        <p class="rp-card__what">
            <b><?= $rpH((string) ($na['label'] ?? '')) ?></b>
            <?php if ((string) ($na['urgencyLabel'] ?? '') !== ''): ?>
                <span class="rp-urg rp-urg--<?= $rpH($urg) ?>"><?= $rpH((string) $na['urgencyLabel']) ?></span>
            <?php endif; ?>
        </p>
        <span><?= $rpH((string) ($na['detail'] ?? '')) ?></span>
    </div>
    <?php if ($tags !== []): ?>
        <div class="rp-tags">
            <?php foreach ($tags as $t): ?><span class="rp-tag" title="<?= $rpH($t) ?>"><?= $rpH($t) ?></span><?php endforeach; ?>
        </div>
    <?php endif; ?>
    <footer class="rp-card__foot">
        <span class="rp-owner rp-owner--<?= $rpH($owner) ?>" title="Référent : <?= $rpH($ownerLabel) ?>"><i aria-hidden="true"><?= $rpH($ownerMark) ?></i><span><?= $rpH($owner === 'nobody' ? 'Aucun référent' : $ownerLabel) ?></span></span>
        <?php if (!empty($na['canAssign'])): ?>
            <form method="post" action="<?= $rpH(url('back-office/recruitments/' . $cid . '/referent')) ?>">
                <?= \App\Core\Csrf::field() ?>
                <input type="hidden" name="return_qs" value="<?= $rpH($pipelineReturnQs ?? '') ?>">
                <button type="submit" class="rp-card__cta rp-card__cta--take">Me désigner</button>
            </form>
        <?php else: ?>
            <a class="rp-card__cta" href="<?= $rpH($dossier . ($anchor !== '' ? '#' . $anchor : '')) ?>"><?= $rpH((string) ($na['cta'] ?? 'Ouvrir')) ?> <span aria-hidden="true">→</span></a>
        <?php endif; ?>
    </footer>
</article>
