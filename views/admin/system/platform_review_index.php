<?php
declare(strict_types=1);

use App\Support\PlatformReviewCatalog;

$h = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
$ready = !empty($prReady);
$tab = (($prTab ?? 'avis') === 'traductions') ? 'traductions' : 'avis';
$summary = is_array($prSummary ?? null) ? $prSummary : ['count' => 0, 'average' => 0.0];
$reviews = is_array($prReviews ?? null) ? $prReviews : [];
$suggestions = is_array($prSuggestions ?? null) ? $prSuggestions : [];
$pending = (int) ($prPending ?? 0);
$statusFilter = (string) ($prStatusFilter ?? '');
$indexUrl = url('admin/system/avis-plateforme');
$csrf = \App\Core\Csrf::token();
$flashOk = \App\Core\Session::getFlash('success');
$flashErr = \App\Core\Session::getFlash('error');

$member = static function (array $row) use ($h): string {
    $name = trim((string) ($row['display_name'] ?? ''));
    $call = trim((string) ($row['callsign'] ?? ''));
    if ($name !== '' && $call !== '') {
        return $h($name . ' (' . $call . ')');
    }

    return $h($name !== '' ? $name : ($call !== '' ? $call : 'Membre'));
};
?>
<div class="pa">
    <div class="pa__frame">
        <header class="pa-hero">
            <p class="pa-crumb">
                <a href="<?= $h(url('admin')) ?>">Administration du site</a>
                <span aria-hidden="true"> / </span>
                Avis et traductions
            </p>
            <div class="pa-hero__row">
                <div>
                    <p class="pa-hero__kicker">Administration du site</p>
                    <h1 class="pa-hero__title">Avis et traductions</h1>
                    <p class="pa-hero__lead">
                        Les membres connectés peuvent noter Athena et proposer une formulation plus naturelle.
                        Relisez les avis, puis marquez les propositions reprises ou non retenues.
                    </p>
                    <div class="pa-hero__actions">
                        <a class="pa-btn <?= $tab === 'avis' ? 'pa-btn--solid' : 'pa-btn--ghost' ?>" href="<?= $h($indexUrl) ?>">Avis reçus</a>
                        <a class="pa-btn <?= $tab === 'traductions' ? 'pa-btn--solid' : 'pa-btn--ghost' ?>" href="<?= $h($indexUrl . '?vue=traductions') ?>">Propositions de traduction</a>
                    </div>
                </div>
                <aside class="pa-hero__meta">
                    <p class="pa-hero__meta-kicker">Aperçu</p>
                    <dl>
                        <div class="pa-hero__meta-row">
                            <dt>Avis envoyés</dt>
                            <dd><?= (int) ($summary['count'] ?? 0) ?></dd>
                        </div>
                        <div class="pa-hero__meta-row">
                            <dt>Note moyenne</dt>
                            <dd><?= $h(number_format((float) ($summary['average'] ?? 0), 1, ',', ' ')) ?> / 10</dd>
                        </div>
                        <div class="pa-hero__meta-row">
                            <dt>Traductions en attente</dt>
                            <dd><?= $pending ?></dd>
                        </div>
                    </dl>
                </aside>
            </div>
        </header>

        <?php if (is_string($flashOk) && trim($flashOk) !== ''): ?>
            <p class="pa-flash pa-flash--ok"><?= $h($flashOk) ?></p>
        <?php endif; ?>
        <?php if (is_string($flashErr) && trim($flashErr) !== ''): ?>
            <p class="pa-flash pa-flash--err"><?= $h($flashErr) ?></p>
        <?php endif; ?>

        <?php if (!$ready): ?>
            <section class="pa-card">
                <h2 class="pa-card__title">Mise à jour nécessaire</h2>
                <p>Relancez la mise à jour du portail pour activer la collecte des avis et des propositions de traduction.</p>
            </section>
        <?php elseif ($tab === 'avis'): ?>
            <section class="pa-card">
                <h2 class="pa-card__title">Avis sur Athena</h2>
                <?php if ($reviews === []): ?>
                    <p>Aucun avis n’a encore été envoyé.</p>
                <?php else: ?>
                    <div class="pa-table-wrap">
                        <table class="pa-table">
                            <thead>
                                <tr>
                                    <th>Membre</th>
                                    <th>Communauté</th>
                                    <th>Note</th>
                                    <th>Clarté</th>
                                    <th>Usage</th>
                                    <th>Fréquence</th>
                                    <th>Point de friction</th>
                                    <th>Appareil</th>
                                    <th>Ce qui aide</th>
                                    <th>À changer</th>
                                    <th>Souhait</th>
                                    <th>Date</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($reviews as $row): ?>
                                <tr>
                                    <td><?= $member($row) ?></td>
                                    <td><?= $h(trim((string) ($row['tenant_name'] ?? '')) ?: '—') ?></td>
                                    <td><?= isset($row['score']) && $row['score'] !== null ? (int) $row['score'] . ' / 10' : '—' ?></td>
                                    <td><?= isset($row['clarity_score']) && $row['clarity_score'] !== null && $row['clarity_score'] !== '' ? (int) $row['clarity_score'] . ' / 5' : '—' ?></td>
                                    <td><?= $h(PlatformReviewCatalog::usageLabel((string) ($row['usage_kind'] ?? ''))) ?></td>
                                    <td><?= $h(trim((string) ($row['frequency_kind'] ?? '')) !== '' ? PlatformReviewCatalog::frequencyLabel((string) $row['frequency_kind']) : '—') ?></td>
                                    <td><?= $h(trim((string) ($row['friction_area'] ?? '')) !== '' ? PlatformReviewCatalog::frictionLabel((string) $row['friction_area']) : '—') ?></td>
                                    <td><?= $h(trim((string) ($row['device_kind'] ?? '')) !== '' ? PlatformReviewCatalog::deviceLabel((string) $row['device_kind']) : '—') ?></td>
                                    <td><?= $h(trim((string) ($row['highlights'] ?? '')) ?: '—') ?></td>
                                    <td><?= $h(trim((string) ($row['improvements'] ?? '')) ?: '—') ?></td>
                                    <td><?= $h(trim((string) ($row['wishlist'] ?? '')) ?: '—') ?></td>
                                    <td><?php
                                        $rawDate = trim((string) ($row['submitted_at'] ?? ''));
                                        $ts = $rawDate !== '' ? strtotime($rawDate) : false;
                                        echo $ts ? $h(date('d/m/Y H:i', $ts)) : '—';
                                    ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </section>
        <?php else: ?>
            <section class="pa-card">
                <h2 class="pa-card__title">Propositions de traduction</h2>
                <form class="pa-filters" method="get" action="<?= $h($indexUrl) ?>">
                    <input type="hidden" name="vue" value="traductions">
                    <label>État
                        <select name="statut">
                            <option value="">Tous</option>
                            <option value="pending" <?= $statusFilter === 'pending' ? 'selected' : '' ?>>En attente</option>
                            <option value="accepted" <?= $statusFilter === 'accepted' ? 'selected' : '' ?>>Reprises</option>
                            <option value="declined" <?= $statusFilter === 'declined' ? 'selected' : '' ?>>Non retenues</option>
                        </select>
                    </label>
                    <button type="submit" class="pa-btn pa-btn--solid">Filtrer</button>
                </form>
                <?php if ($suggestions === []): ?>
                    <p>Aucune proposition pour ces filtres.</p>
                <?php else: ?>
                    <div class="pa-table-wrap">
                        <table class="pa-table">
                            <thead>
                                <tr>
                                    <th>Membre</th>
                                    <th>Langue</th>
                                    <th>Zone</th>
                                    <th>Texte actuel</th>
                                    <th>Proposition</th>
                                    <th>Précision</th>
                                    <th>État</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($suggestions as $row): ?>
                                <?php $id = (int) ($row['id'] ?? 0); $st = (string) ($row['status'] ?? 'pending'); ?>
                                <tr>
                                    <td><?= $member($row) ?></td>
                                    <td><?= $h(PlatformReviewCatalog::localeLabel((string) ($row['locale'] ?? ''))) ?></td>
                                    <td><?= $h(PlatformReviewCatalog::areaLabel((string) ($row['area'] ?? ''))) ?></td>
                                    <td><?= $h((string) ($row['original_text'] ?? '')) ?></td>
                                    <td><?= $h((string) ($row['proposed_text'] ?? '')) ?></td>
                                    <td><?= $h(trim((string) ($row['comment'] ?? '')) ?: '—') ?></td>
                                    <td><?= $h(PlatformReviewCatalog::statusLabel($st)) ?></td>
                                    <td>
                                        <?php if ($st === PlatformReviewCatalog::STATUS_PENDING && $id > 0): ?>
                                            <form method="post" action="<?= $h(url('admin/system/avis-plateforme/traductions/' . $id . '/reprendre')) ?>" style="display:inline">
                                                <input type="hidden" name="_csrf_token" value="<?= $h($csrf) ?>">
                                                <button type="submit" class="pa-btn pa-btn--solid">Reprendre</button>
                                            </form>
                                            <form method="post" action="<?= $h(url('admin/system/avis-plateforme/traductions/' . $id . '/ecarter')) ?>" style="display:inline">
                                                <input type="hidden" name="_csrf_token" value="<?= $h($csrf) ?>">
                                                <button type="submit" class="pa-btn pa-btn--ghost">Écarter</button>
                                            </form>
                                        <?php else: ?>
                                            —
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </section>
        <?php endif; ?>
    </div>
</div>
