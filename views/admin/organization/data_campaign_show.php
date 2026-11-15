<?php

declare(strict_types=1);

use App\Services\DataCampaign\DataCampaignService;
use App\Support\DataCampaignBlocks;

/**
 * Back-office > Fiabilisation des données > une campagne : avancement, membres, relances, signalements, export.
 *
 * @var array<string, mixed> $campaign
 * @var array<string, mixed> $overview avancement et listes (DataCampaignService::overview)
 * @var list<array<string, mixed>> $members membres filtrés
 * @var string $filter
 * @var array<string, string> $filters
 * @var int $unitFilter
 * @var array<string, array<string, mixed>> $blockCatalog
 * @var int $cooldownHours
 * @var string $csrfToken
 */

$h = static fn (mixed $v): string => htmlspecialchars(trim((string) $v), ENT_QUOTES, 'UTF-8');
$campaign = is_array($campaign ?? null) ? $campaign : [];
$overview = is_array($overview ?? null) ? $overview : ['members' => [], 'totals' => ['total' => 0, 'confirmed' => 0, 'flagged' => 0, 'none' => 0, 'percent' => 0], 'byUnit' => [], 'flags' => []];
$members = is_array($members ?? null) ? $members : [];
$filters = is_array($filters ?? null) ? $filters : [];
$filter = (string) ($filter ?? 'tous');
$unitFilter = (int) ($unitFilter ?? 0);
$csrfToken = (string) ($csrfToken ?? '');
$cooldownHours = (int) ($cooldownHours ?? DataCampaignService::REMINDER_COOLDOWN_HOURS);
$totals = $overview['totals'];
$id = (int) ($campaign['id'] ?? 0);
$status = (string) ($campaign['status'] ?? 'draft');
$day = static function (string $raw): string {
    $ts = strtotime($raw);

    return $ts !== false && $raw !== '' ? date('d/m/Y', $ts) : '—';
};
$dayTime = static function (string $raw): string {
    $ts = strtotime($raw);

    return $ts !== false && $raw !== '' ? date('d/m/Y H:i', $ts) : '—';
};
$statusText = ['draft' => 'Brouillon', 'open' => 'Ouverte', 'closed' => 'Clôturée'];
$bucketText = ['confirmed' => 'Confirmé', 'flagged' => 'Avec signalements', 'none' => 'Sans réponse'];
$bucketClass = ['confirmed' => 'dc-pill--ok', 'flagged' => 'dc-pill--warn', 'none' => 'dc-pill--muted'];
$pct = static fn (int $n, int $total): int => $total > 0 ? (int) round($n * 100 / $total) : 0;
$bar = static function (array $t) use ($pct): string {
    $total = (int) $t['total'];

    return '<div class="dc-bar" role="img" aria-label="' . (int) $t['confirmed'] . ' confirmés, ' . (int) $t['flagged'] . ' avec signalements, ' . (int) $t['none'] . ' sans réponse sur ' . $total . '">'
        . '<span class="dc-bar__ok" style="width:' . $pct((int) $t['confirmed'], $total) . '%"></span>'
        . '<span class="dc-bar__flag" style="width:' . $pct((int) $t['flagged'], $total) . '%"></span>'
        . '<span class="dc-bar__none" style="width:' . $pct((int) $t['none'], $total) . '%"></span></div>';
};
$base = url('back-office/organisation/fiabilisation/' . $id);
$flagRequestLabel = static fn (array $f): string => match ((string) ($f['request_status'] ?? '')) {
    'pending' => 'Demande de correction en attente (circuit existant)',
    'approved' => 'Demande acceptée',
    'rejected' => 'Demande refusée',
    default => '',
};
$openFlags = array_values(array_filter($overview['flags'], static fn (array $f): bool => !empty($f['is_open'])));
$closedFlags = array_values(array_filter($overview['flags'], static fn (array $f): bool => empty($f['is_open']) && (string) $f['status'] !== 'withdrawn'));
?>
<div class="dc dc--bo">
    <section class="dc-card" aria-labelledby="dc-head-title">
        <h2 class="dc-card__title" id="dc-head-title"><?= $h($campaign['title'] ?? '') ?> <span class="dc-pill <?= $status === 'open' ? 'dc-pill--ok' : ($status === 'closed' ? 'dc-pill--info' : 'dc-pill--muted') ?>"><?= $h($statusText[$status] ?? $status) ?></span></h2>
        <p class="dc-text">
            Ouverture le <?= $h($day((string) ($campaign['opens_on'] ?? ''))) ?>, date limite le <b><?= $h($day((string) ($campaign['due_on'] ?? ''))) ?></b>.
            Parties relues : <?= $h(implode(', ', array_map(static fn (string $k): string => DataCampaignBlocks::label($k), $campaign['blocks'] ?? []))) ?>.
            Rappels : <?= $h(implode(', ', array_map(static fn (int $d): string => $d === 0 ? 'jour J' : 'J-' . $d, $campaign['reminder_days'] ?? []))) ?>.
            <?= empty($campaign['unit_ids']) ? 'Toute la communauté.' : 'Unités choisies et leurs sous-unités.' ?>
        </p>
        <div class="dc-actions">
            <?php if ($status !== 'open'): ?>
                <form method="post" action="<?= $h($base . '/ouvrir') ?>">
                    <input type="hidden" name="_csrf_token" value="<?= $h($csrfToken) ?>">
                    <button type="submit" class="dc-btn"><?= $status === 'closed' ? 'Rouvrir la campagne' : 'Ouvrir la campagne' ?></button>
                </form>
            <?php else: ?>
                <form method="post" action="<?= $h($base . '/cloturer') ?>" data-confirm="Clôturer la campagne ? Les réponses et instantanés sont conservés.">
                    <input type="hidden" name="_csrf_token" value="<?= $h($csrfToken) ?>">
                    <button type="submit" class="dc-btn dc-btn--ghost">Clôturer la campagne</button>
                </form>
                <form method="post" action="<?= $h($base . '/relancer') ?>">
                    <input type="hidden" name="_csrf_token" value="<?= $h($csrfToken) ?>">
                    <button type="submit" class="dc-btn">Relancer tous les membres sans réponse</button>
                </form>
            <?php endif; ?>
            <a class="dc-btn dc-btn--ghost" href="<?= $h($base . '/export') ?>">Exporter en CSV</a>
            <a class="dc-btn dc-btn--ghost" href="<?= $h(url('back-office/organisation/fiabilisation')) ?>">Toutes les campagnes</a>
        </div>
        <details class="dc-doc">
            <summary>Colonnes de l’export</summary>
            <ul>
                <?php foreach (DataCampaignService::exportHeaders() as $col): ?><li><?= $h($col) ?></li><?php endforeach; ?>
            </ul>
            <p class="dc-note">L’export ne contient que le statut de chaque membre : jamais le contenu de sa fiche ni le texte de ses signalements. Séparateur : point-virgule, encodage UTF-8.</p>
        </details>
        <p class="dc-note">Une relance part par notification et e-mail (si le membre l’accepte), jamais plus d’une fois toutes les <?= $cooldownHours ?> h par membre, et pas à qui a déjà répondu.</p>
    </section>

    <section class="dc-card" aria-labelledby="dc-progress-title">
        <h2 class="dc-card__title" id="dc-progress-title">Avancement</h2>
        <p class="dc-text"><b><?= (int) $totals['percent'] ?> %</b> des fiches sont confirmées : <?= (int) $totals['confirmed'] ?> confirmée(s), <?= (int) $totals['flagged'] ?> avec signalements, <?= (int) $totals['none'] ?> sans réponse, sur <?= (int) $totals['total'] ?> membre(s).</p>
        <?= $bar($totals) ?>
        <ul class="dc-legend"><li><span class="dc-dot dc-dot--ok"></span> Confirmés</li><li><span class="dc-dot dc-dot--flag"></span> Avec signalements</li><li><span class="dc-dot dc-dot--none"></span> Sans réponse</li></ul>
        <?php if ($overview['byUnit'] !== []): ?>
            <h3 class="dc-sub">Par unité</h3>
            <ul class="dc-units">
                <?php foreach ($overview['byUnit'] as $u): ?>
                    <li>
                        <a href="<?= $h($base . '?unite=' . (int) $u['unit_id']) ?>"><?= $h($u['label']) ?></a>
                        <span class="dc-muted"><?= (int) $u['confirmed'] ?> / <?= (int) $u['total'] ?></span>
                        <?= $bar($u) ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>

    <section class="dc-card" id="signalements" aria-labelledby="dc-flags-title">
        <h2 class="dc-card__title" id="dc-flags-title">Signalements à traiter (<?= count($openFlags) ?>)</h2>
        <?php if ($openFlags === []): ?>
            <p class="dc-text">Aucun signalement en attente.</p>
        <?php else: ?>
            <div class="dc-flags">
                <?php foreach ($openFlags as $f): ?>
                    <div class="dc-flag">
                        <p class="dc-flag__state"><b><?= $h($f['display_name'] !== '' ? $f['display_name'] : $f['callsign']) ?></b> · <?= $h(DataCampaignBlocks::label((string) $f['block_key'])) ?> · <?= $h($dayTime((string) $f['created_at'])) ?></p>
                        <p class="dc-flag__text"><?= $h($f['message']) ?></p>
                        <?php if ((int) ($f['correction_request_id'] ?? 0) > 0): ?>
                            <p class="dc-note"><?= $h($flagRequestLabel($f)) ?> — à décider dans <a class="dc-link" href="<?= $h(url('back-office/demandes-rh')) ?>">Demandes RH</a>. Le signalement se ferme quand la demande est décidée.</p>
                        <?php else: ?>
                            <form method="post" action="<?= $h($base . '/signalements/' . (int) $f['id'] . '/traiter') ?>" class="dc-form dc-form--row">
                                <input type="hidden" name="_csrf_token" value="<?= $h($csrfToken) ?>">
                                <label class="dc-field dc-field--grow"><span class="dc-sr">Réponse au membre</span><input type="text" name="answer" maxlength="1000" minlength="3" required placeholder="Réponse que le membre lira (ce qui a été fait ou pourquoi non)"></label>
                                <button type="submit" class="dc-btn">Marquer comme traité</button>
                            </form>
                            <p class="dc-note">Pour corriger la fiche elle-même, utilisez <a class="dc-link" href="<?= $h(url('back-office/personnel/corrections') . '?vue=corriger&membre=' . (int) $f['user_id']) ?>">la correction de dossier</a> (validation habituelle).</p>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        <?php if ($closedFlags !== []): ?>
            <details class="dc-doc">
                <summary>Signalements déjà traités (<?= count($closedFlags) ?>)</summary>
                <ul>
                    <?php foreach ($closedFlags as $f): ?>
                        <li><?= $h($f['display_name'] !== '' ? $f['display_name'] : $f['callsign']) ?> · <?= $h(DataCampaignBlocks::label((string) $f['block_key'])) ?> · <?= $h((string) $f['status'] === 'handled' ? 'traité' : ($flagRequestLabel($f) !== '' ? $flagRequestLabel($f) : 'clos')) ?></li>
                    <?php endforeach; ?>
                </ul>
            </details>
        <?php endif; ?>
    </section>

    <section class="dc-card" aria-labelledby="dc-members-title">
        <h2 class="dc-card__title" id="dc-members-title">Membres (<?= count($members) ?>)</h2>
        <nav class="dc-filters" aria-label="Filtrer les membres">
            <?php foreach ($filters as $key => $label): ?>
                <a class="dc-chip<?= $filter === $key ? ' is-on' : '' ?>" href="<?= $h($base . '?filtre=' . $key . ($unitFilter > 0 ? '&unite=' . $unitFilter : '')) ?>"<?= $filter === $key ? ' aria-current="true"' : '' ?>><?= $h($label) ?></a>
            <?php endforeach; ?>
            <?php if ($unitFilter > 0): ?><a class="dc-chip" href="<?= $h($base . '?filtre=' . $filter) ?>">Toutes les unités</a><?php endif; ?>
        </nav>
        <?php if ($members === []): ?>
            <p class="dc-text">Aucun membre ne correspond.</p>
        <?php else: ?>
            <div class="dc-table-wrap">
                <table class="dc-table">
                    <thead><tr><th scope="col">Membre</th><th scope="col">Unité</th><th scope="col">Statut</th><th scope="col">Parties</th><th scope="col">Confirmé le</th><th scope="col">Dernière relance</th><th scope="col"><span class="dc-sr">Relancer</span></th></tr></thead>
                    <tbody>
                    <?php foreach ($members as $m): ?>
                        <tr>
                            <td><?= $h($m['display_name'] !== '' ? $m['display_name'] : '—') ?><?= $m['callsign'] !== '' ? ' <span class="dc-muted">' . $h($m['callsign']) . '</span>' : '' ?></td>
                            <td><?= $h($m['unit_label'] !== '' ? $m['unit_label'] : '—') ?></td>
                            <td><span class="dc-pill <?= $bucketClass[(string) $m['bucket']] ?? 'dc-pill--muted' ?>"><?= $h($bucketText[(string) $m['bucket']] ?? '') ?></span><?= (int) $m['open_flags'] > 0 ? ' <span class="dc-muted">' . (int) $m['open_flags'] . '</span>' : '' ?></td>
                            <td><?= (int) $m['answered'] ?> / <?= count($campaign['blocks'] ?? []) ?></td>
                            <td><?= $h($day((string) ($m['confirmed_at'] ?? ''))) ?></td>
                            <td><?= $h($dayTime((string) ($m['last_reminded_at'] ?? ''))) ?></td>
                            <td>
                                <?php if ($status === 'open' && $m['bucket'] === 'none'): ?>
                                    <form method="post" action="<?= $h($base . '/relancer') ?>">
                                        <input type="hidden" name="_csrf_token" value="<?= $h($csrfToken) ?>">
                                        <input type="hidden" name="user_id" value="<?= (int) $m['user_id'] ?>">
                                        <button type="submit" class="dc-btn dc-btn--ghost">Relancer</button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>
</div>
