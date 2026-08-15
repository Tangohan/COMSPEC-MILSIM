<?php
$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$unitName = (string) ($unitName ?? 'Unité');
$commandStaff = is_array($commandStaff ?? null) ? $commandStaff : [];
$currentOps = is_array($currentOps ?? null) ? $currentOps : [];
$subUnits = is_array($subUnits ?? null) ? $subUnits : [];
$recentEvents = is_array($recentEvents ?? null) ? $recentEvents : [];
$orbat = $orbat ?? ($orbatPreview ?? null);
$stats = is_array($stats ?? null) ? $stats : [];
$face = static function (array $p) use ($h): string {
    if (!empty($p['photo'])) {
        return '<img src="' . $h((string) $p['photo']) . '" alt="">';
    }
    return '<span>' . $h((string) ($p['initials'] ?? '?')) . '</span>';
};
?>
<section class="jnet-panel">
    <div class="jnet-panel__head">
        <h2>Fiche unité</h2>
        <span class="jnet-status is-green"><?= $h((string) ($opsStatus ?? 'GREEN')) ?></span>
    </div>
    <div class="jnet-panel__body jnet-unit-sheet">
        <div class="jnet-unit-sheet__id">
            <div class="jnet-unit-badge" aria-hidden="true"><?= $h(strtoupper(substr(preg_replace('/\s+/', '', $unitName) ?: 'U', 0, 3))) ?></div>
            <div>
                <h1><?= $h($unitName) ?></h1>
                <p class="jnet-hero-unit__motto"><?= $h((string) ($unitMotto ?? '')) ?></p>
                <p class="jnet-meta"><?= $h((string) ($locationLabel ?? '')) ?> · <?= $h((string) ($qualificationSummary ?? '')) ?></p>
            </div>
        </div>
        <div class="jnet-statstrip jnet-statstrip--inline">
            <div><span>Effectif présent / autorisé</span><strong><?= (int) ($stats['personnelPresent'] ?? 0) ?>/<?= (int) ($stats['personnelAuth'] ?? 0) ?></strong></div>
            <div><span>Ops en cours</span><strong><?= (int) ($stats['activeOps'] ?? 0) ?></strong></div>
            <div><span>Cibles prioritaires</span><strong><?= (int) ($stats['priorityTargets'] ?? 0) ?></strong></div>
        </div>

        <h3 class="jnet-section-title">Commandement</h3>
        <div class="jnet-gallery jnet-gallery--compact">
            <?php foreach ($commandStaff as $p): ?>
                <a class="jnet-person-card" href="<?= $h((string) ($p['href'] ?? '#')) ?>">
                    <div class="jnet-avatar jnet-avatar--xl"><?= $face($p) ?></div>
                    <strong><?= $h((string) ($p['name'] ?? '')) ?></strong>
                    <span><?= $h((string) ($p['function'] ?? '')) ?></span>
                </a>
            <?php endforeach; ?>
        </div>

        <h3 class="jnet-section-title">Opérations en cours</h3>
        <?php foreach ($currentOps as $op): ?>
            <a class="jnet-op-row" href="<?= $h((string) ($op['href'] ?? '#')) ?>">
                <strong><?= $h((string) ($op['title'] ?? '')) ?></strong>
                <span class="jnet-badge jnet-badge--watch"><?= $h((string) ($op['state'] ?? '')) ?></span>
            </a>
        <?php endforeach; ?>

        <h3 class="jnet-section-title">Sous-unités</h3>
        <?php if ($subUnits === []): ?>
            <p class="jnet-empty">Structure d’unités non renseignée — configurez l’ORBAT dans l’administration.</p>
        <?php else: ?>
            <ul class="jnet-bullet">
                <?php foreach ($subUnits as $u): ?>
                    <li><?= $h((string) ($u['name'] ?? 'Unité')) ?><?= !empty($u['code']) ? ' · ' . $h((string) $u['code']) : '' ?></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>

        <h3 class="jnet-section-title">Derniers événements</h3>
        <div class="jnet-feed">
            <?php foreach ($recentEvents as $ev): ?>
                <a class="jnet-feed__item" href="<?= $h((string) ($ev['href'] ?? '#')) ?>">
                    <time><?= $h((string) ($ev['time'] ?? '')) ?></time>
                    <div>
                        <strong><?= $h((string) ($ev['title'] ?? '')) ?></strong>
                        <span><?= $h((string) ($ev['detail'] ?? '')) ?></span>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<section class="jnet-panel">
    <div class="jnet-panel__head"><h2>Mini-ORBAT</h2></div>
    <div class="jnet-panel__body">
        <?php if (!is_array($orbat) || empty($orbat['label'] ?? null)): ?>
            <div class="jnet-orbat-demo">
                <div class="jnet-orbat-node jnet-orbat-node--cmd">
                    <strong>COMMAND</strong>
                    <span><?= $h((string) (($commandStaff[0]['name'] ?? 'Commandement'))) ?></span>
                </div>
                <div class="jnet-orbat-branches">
                    <?php foreach ([['ALPHA', '12/14'], ['BRAVO', '9/12'], ['SUPPORT', '8/9']] as $b): ?>
                        <div class="jnet-orbat-node">
                            <strong><?= $h($b[0]) ?></strong>
                            <span><?= $h($b[1]) ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
                <p class="jnet-meta">Aperçu illustratif — l’ORBAT Athena s’affiche ici dès que la structure d’unités est renseignée.</p>
            </div>
        <?php else: ?>
            <?php
            $renderNode = function (array $node, int $depth = 0) use (&$renderNode, $h): void {
                $label = (string) ($node['label'] ?? 'Unité');
                $leader = (string) ($node['leader'] ?? '');
                $strength = $node['strength'] ?? null;
                $members = is_array($node['members'] ?? null) ? $node['members'] : [];
                echo '<div class="jnet-orbat-node" style="margin-left:' . (min($depth, 4) * 1.1) . 'rem">';
                echo '<strong>' . $h($label) . '</strong>';
                if ($leader !== '' && $leader !== '—') {
                    echo '<span>' . $h($leader) . '</span>';
                }
                if ($strength !== null && $strength !== '') {
                    echo '<em>' . $h((string) $strength) . '</em>';
                }
                echo '<div class="jnet-orbat-members">';
                foreach (array_slice($members, 0, 8) as $m) {
                    $uid = (int) ($m['user_id'] ?? 0);
                    $mlabel = (string) ($m['label'] ?? 'Membre');
                    if ($uid > 0) {
                        echo '<a href="' . $h(url('jnet/personnel/' . $uid)) . '">' . $h($mlabel) . '</a>';
                    } else {
                        echo '<span>' . $h($mlabel) . '</span>';
                    }
                }
                echo '</div></div>';
                foreach ($node['children'] ?? [] as $child) {
                    if (is_array($child)) {
                        $renderNode($child, $depth + 1);
                    }
                }
            };
            $renderNode($orbat, 0);
            ?>
        <?php endif; ?>
    </div>
</section>
