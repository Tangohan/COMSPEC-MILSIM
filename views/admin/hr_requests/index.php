<?php
declare(strict_types=1);

/**
 * Back-office — Demandes RH (/back-office/demandes-rh) : file unique des demandes en attente de décision.
 * En-tête et onglets par type : coque back-office (App\Support\BackOfficeTabs). Aucune décision ici : « Décider »
 * ouvre l'écran du type, avec ses propres droits.
 *
 * @var list<array<string, mixed>> $hrRequests
 * @var list<array{label: string, count: int, href: string}> $hrTypeCounts
 * @var int $hrRowsPerType
 */
$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$rows = is_array($hrRequests ?? null) ? $hrRequests : [];
$typeCounts = is_array($hrTypeCounts ?? null) ? $hrTypeCounts : [];
$perType = (int) ($hrRowsPerType ?? 40);
$total = 0;
$truncated = false;
foreach ($typeCounts as $tc) {
    $total += (int) $tc['count'];
    $truncated = $truncated || (int) $tc['count'] > $perType;
}
?>
<div class="hr-requests" id="hr-requests">
    <section class="ath-table-panel">
        <div class="ath-table-toolbar">
            <span class="ath-table-toolbar__title">En attente de décision</span>
            <span class="ath-table-toolbar__count"><?= $total ?></span>
            <?php foreach ($typeCounts as $tc): ?>
                <a class="ath-tag" href="<?= $h($tc['href']) ?>"><?= $h($tc['label']) ?> · <?= (int) $tc['count'] ?></a>
            <?php endforeach; ?>
        </div>
        <?php if ($rows === []): ?>
            <p class="ath-panel__lead">Aucune demande en attente.</p>
        <?php else: ?>
            <table class="ath-table">
                <thead>
                    <tr>
                        <th>Type</th>
                        <th>Membre</th>
                        <th>Demande</th>
                        <th>Avis du circuit</th>
                        <th>Depuis</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($rows as $r):
                    $ts = strtotime((string) ($r['created_at'] ?? ''));
                    $opinion = is_array($r['opinion'] ?? null) ? $r['opinion'] : [];
                    ?>
                    <tr>
                        <td><?= $h($r['type_label']) ?></td>
                        <td><strong><?= $h($r['member']) ?></strong></td>
                        <td><?= $h($r['request']) ?></td>
                        <td>
                            <?php if ($opinion === []): ?>
                                —
                            <?php else: ?>
                                <?= implode('<br>', array_map($h, $opinion)) ?>
                            <?php endif; ?>
                        </td>
                        <td><?= $ts ? $h(date('d/m/Y', $ts)) : '—' ?></td>
                        <td><a class="ath-btn" href="<?= $h($r['href']) ?>">Décider</a></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php if ($truncated): ?>
                <p class="ath-panel__lead">Liste limitée aux demandes les plus anciennes de chaque type : ouvrez le filtre d’un type pour voir toutes ses demandes.</p>
            <?php endif; ?>
        <?php endif; ?>
    </section>
</div>
