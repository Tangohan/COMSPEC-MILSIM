<?php
declare(strict_types=1);
/**
 * Effectif et suivi : un tableau des membres directs de l'unité avec leur statut, leur réponse au prochain rendez-vous,
 * les consignes qu'ils ont lues et ce qui reste à traiter. Réservé à ceux qui peuvent publier dans l'espace ; les
 * filtres sont instantanés (jnet_unit_workspace.js) et le tableau reste complet sans JavaScript.
 *
 * @var array<string, mixed> $usw
 * @var list<array<string, mixed>> $members
 * @var string $label
 */
$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$rows = is_array($usw['tracking'] ?? null) ? $usw['tracking'] : [];
$event = is_array($usw['event'] ?? null) ? $usw['event'] : null;
$strength = is_array($space['strength'] ?? null) ? $space['strength'] : ['total' => 0, 'available' => 0];
$statusPill = [
    'active' => ['Disponible', '#34d399'],
    'off' => ['Indisponible', '#94a3b8'],
    'absence' => ['En absence', '#fbbf24'],
];
$answerPill = [
    'yes' => ['Présent', '#34d399'],
    'maybe' => ['Incertain', '#fbbf24'],
    'no' => ['Absent', '#f87171'],
    'none' => ['Sans réponse', '#94a3b8'],
];
$tonePill = ['warn' => '#fbbf24', 'bad' => '#f87171', 'info' => '#60a5fa'];
$todoCount = count(array_filter($rows, static fn (array $r): bool => $r['todo'] !== []));
$noAnswer = count(array_filter($rows, static fn (array $r): bool => $r['answer'] === 'none'));
$present = count(array_filter($rows, static fn (array $r): bool => $r['answer'] === 'yes'));
$readTotal = $rows !== [] ? (int) $rows[0]['readTotal'] : 0;
$filters = ['all' => 'Tous', 'todo' => 'À traiter', 'active' => 'Disponibles', 'absence' => 'En absence', 'off' => 'Indisponibles'];
$filterCount = [
    'all' => count($rows),
    'todo' => $todoCount,
    'active' => count(array_filter($rows, static fn (array $r): bool => $r['status'] === 'active')),
    'absence' => count(array_filter($rows, static fn (array $r): bool => $r['status'] === 'absence')),
    'off' => count(array_filter($rows, static fn (array $r): bool => $r['status'] === 'off')),
];
?>
<header class="us-page">
    <h1 class="ja-disp ja-disp--md">Qui est prêt.</h1>
    <p><?= (int) $strength['available'] ?> disponible<?= (int) $strength['available'] > 1 ? 's' : '' ?> sur <?= (int) $strength['total'] ?>, sous-unités comprises. <?= count($rows) ?> membre<?= count($rows) > 1 ? 's' : '' ?> affecté<?= count($rows) > 1 ? 's' : '' ?> directement à <?= $h($label) ?>.</p>
    <a class="us-btn" href="<?= $h(url('jnet/personnel?filtre=' . rawurlencode($label))) ?>">Annuaire de l’organisation</a>
</header>
<?php if ($rows === []): ?>
    <div class="jn-empty"><p>Aucun membre affecté directement à cette unité.</p></div>
<?php else: ?>
    <div class="uw-minis">
        <div class="uw-mini"><b><?= count($rows) ?></b><span>membre<?= count($rows) > 1 ? 's' : '' ?></span></div>
        <?php if ($event !== null): ?>
            <div class="uw-mini"><b class="is-ok"><?= $present ?></b><span>présent<?= $present > 1 ? 's' : '' ?> · <?= $h((string) $event['when']) ?></span></div>
            <div class="uw-mini"><b class="is-bad"><?= $noAnswer ?></b><span>sans réponse</span></div>
        <?php endif; ?>
        <div class="uw-mini"><b class="is-warn"><?= $todoCount ?></b><span>avec un point à traiter</span></div>
    </div>
    <?php if ($event === null): ?><p class="uw-note">Aucun rendez-vous n’est programmé dans le module Événements : la réponse de présence apparaîtra dès qu’il y en aura un.</p><?php endif; ?>
    <div class="uw-tools" data-uw-filter>
        <div class="ja-chips" role="group" aria-label="Filtrer les membres">
            <?php foreach ($filters as $key => $name): ?>
                <?php if ($key !== 'all' && $filterCount[$key] === 0) { continue; } ?>
                <button type="button" class="ja-chip<?= $key === 'all' ? ' is-on' : '' ?>" data-filter="<?= $h($key) ?>" aria-pressed="<?= $key === 'all' ? 'true' : 'false' ?>"><?= $h($name) ?> <small class="ja-mono"><?= $filterCount[$key] ?></small></button>
            <?php endforeach; ?>
        </div>
        <label class="ja-fi"><svg class="ja-i" viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg><input type="search" data-uw-q placeholder="Nom, indicatif, fonction" aria-label="Chercher un membre"></label>
    </div>
    <div class="uw-table" data-uw-table>
        <table>
            <thead>
                <tr>
                    <th scope="col">Membre</th>
                    <th scope="col">Fonction</th>
                    <th scope="col">Statut</th>
                    <?php if ($event !== null): ?><th scope="col">Réponse</th><?php endif; ?>
                    <?php if ($readTotal > 0): ?><th scope="col">Consignes lues</th><?php endif; ?>
                    <th scope="col">À traiter</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $r): ?>
                    <?php $search = mb_strtolower($r['name'] . ' ' . $r['callsign'] . ' ' . $r['function'] . ' ' . $r['grade']); ?>
                    <tr data-status="<?= $h($r['status']) ?>" data-todo="<?= $r['todo'] !== [] ? '1' : '0' ?>" data-search="<?= $h($search) ?>">
                        <td>
                            <a class="uw-who" href="<?= $h($r['href']) ?>">
                                <span class="us-member__face<?= $r['status'] === 'off' ? ' is-off' : '' ?>"><?= is_string($r['photo'] ?? null) && $r['photo'] !== '' ? '<img src="' . $h($r['photo']) . '" alt="">' : '<span>' . $h($r['initials']) . '</span>' ?></span>
                                <span><b><?= $h($r['name']) ?></b><?= $r['me'] ? '<small>vous</small>' : '' ?><em><?= $h(trim($r['grade'] . ' ' . $r['callsign'])) ?></em></span>
                            </a>
                        </td>
                        <td><?= $h($r['function']) ?></td>
                        <td><span class="ja-pill" style="--p: <?= $h($statusPill[$r['status']][1]) ?>"><?= $h($statusPill[$r['status']][0]) ?></span></td>
                        <?php if ($event !== null): ?>
                            <td><?php if ($r['answer'] !== ''): ?><span class="ja-pill" style="--p: <?= $h($answerPill[$r['answer']][1]) ?>"><?= $h($answerPill[$r['answer']][0]) ?></span><?php endif; ?></td>
                        <?php endif; ?>
                        <?php if ($readTotal > 0): ?>
                            <td>
                                <div class="uw-prog" title="<?= (int) $r['read'] ?> consigne<?= $r['read'] > 1 ? 's' : '' ?> lue<?= $r['read'] > 1 ? 's' : '' ?> sur <?= (int) $r['readTotal'] ?>">
                                    <span class="uw-bar uw-bar--sm"><i style="width: <?= round($r['read'] / $r['readTotal'] * 100, 1) ?>%; background: <?= $r['read'] === $r['readTotal'] ? '#34d399' : ($r['read'] / $r['readTotal'] >= 0.6 ? '#fbbf24' : '#f87171') ?>"></i></span>
                                    <span><?= (int) $r['read'] ?> / <?= (int) $r['readTotal'] ?></span>
                                </div>
                            </td>
                        <?php endif; ?>
                        <td>
                            <?php if ($r['todo'] === []): ?><span class="uw-dash" aria-label="Rien à traiter">—</span><?php else: ?>
                                <div class="uw-todo-pills"><?php foreach ($r['todo'] as $t): ?><span class="ja-pill" style="--p: <?= $h($tonePill[$t['tone']] ?? '#94a3b8') ?>"><?= $h($t['label']) ?></span><?php endforeach; ?></div>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <p class="uw-none" data-uw-none hidden>Aucun membre ne correspond.</p>
    </div>
    <?php if ($readTotal > 0): ?><p class="uw-note">Consignes lues : sur les <?= $readTotal ?> dernières consignes et ordres à accuser reçus ou publiés dans l’espace.</p><?php endif; ?>
<?php endif; ?>
