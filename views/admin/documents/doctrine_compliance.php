<?php
declare(strict_types=1);

use App\Support\LocalizedCalendar;

/*
 * Doctrine et documents > Suivi de diffusion, d'après la maquette « Suivi de diffusion - Athena ».
 * Tout est rendu côté serveur (lisible sans JavaScript) ; doctrine-compliance.js ne fait que filtrer,
 * replier et basculer la vue « Par personne ».
 */
$docs = is_array($docs ?? null) ? $docs : [];
$stats = ($stats ?? []) + ['concerned' => 0, 'acknowledged' => 0, 'pending' => 0, 'overdue' => 0, 'opened' => 0, 'read' => 0];
$statusCounts = is_array($statusCounts ?? null) ? $statusCounts : [];
$commandStats = ($commandStats ?? []) + ['active_docs' => 0, 'approaching_deadline' => 0];
$compliancePct = (float) ($compliancePct ?? 0);
$documentFilter = (int) ($documentFilter ?? 0);
$statusFilter = (string) ($statusFilter ?? '');
$canRemind = !empty($canRemind);
$canCreate = !empty($canCreate);
$canManage = !empty($canManage);
$csrf_token = (string) ($csrf_token ?? '');
$h = static fn (?string $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$isEn = LocalizedCalendar::lang() === 'en';
$num = static fn (float $v, int $dec = 0): string => number_format($v, $dec, $isEn ? '.' : ',', $isEn ? ',' : ' ');

/* « 12 oct. » (année ajoutée si ce n'est pas l'année en cours). */
$fmtDay = static function (?string $at): string {
    $ts = $at ? strtotime($at) : false;
    if ($ts === false) {
        return '';
    }
    $out = (int) date('j', $ts) . ' ' . LocalizedCalendar::month((int) date('n', $ts), true);

    return date('Y', $ts) !== date('Y') ? $out . ' ' . date('Y', $ts) : $out;
};
/* « 12 oct. 2026, 05:08 ». */
$fmtStamp = static function (?string $at): string {
    $ts = $at ? strtotime($at) : false;
    if ($ts === false) {
        return '';
    }

    return (int) date('j', $ts) . ' ' . LocalizedCalendar::month((int) date('n', $ts), true) . ' ' . date('Y', $ts) . ', ' . date('H:i', $ts);
};
/* Jours restants jusqu'à la fin du jour d'échéance (négatif : dépassée). */
$daysLeft = static function (?string $at): ?int {
    $ts = $at ? strtotime($at) : false;
    if ($ts === false) {
        return null;
    }

    return (int) floor((strtotime(date('Y-m-d 23:59:59', $ts)) - time()) / 86400);
};
$initials = static function (string $name): string {
    $out = '';
    foreach (preg_split('/\s+/u', trim($name)) ?: [] as $part) {
        $part = (string) preg_replace('/[^\p{L}\p{N}]+/u', '', (string) $part);
        if ($part !== '') {
            $out .= mb_strtoupper(mb_substr($part, 0, 1));
        }
        if (mb_strlen($out) >= 2) {
            break;
        }
    }

    return $out !== '' ? $out : '?';
};
$avatar = static fn (int $uid, string $name): string => '<span class="dfs-av" style="--dfs-hue:' . (($uid * 47) % 360) . '" aria-hidden="true" translate="no">' . htmlspecialchars($initials($name), ENT_QUOTES, 'UTF-8') . '</span>';

/* Statuts réels (DoctrineComplianceStatus), dans l'ordre de la barre de progression. */
$statusMeta = [
    'ACKNOWLEDGED' => ['tone' => 'ok', 'icon' => 'check', 'done' => true],
    'READ' => ['tone' => 'read', 'icon' => 'eye', 'done' => true],
    'ACK_REQUIRED' => ['tone' => 'sign', 'icon' => 'file', 'done' => false],
    'ACK_OUTDATED' => ['tone' => 'new', 'icon' => 'up', 'done' => false],
    'UNREAD' => ['tone' => 'un', 'icon' => 'clock', 'done' => false],
    'OVERDUE' => ['tone' => 'late', 'icon' => 'alert', 'done' => false],
];
$statusLabels = \App\Support\Doctrine\DoctrineComplianceStatus::memberBadgeLabels();
$icon = static fn (string $id): string => '<svg class="dfs-i" aria-hidden="true"><use href="#dfs-' . $id . '"></use></svg>';
$badge = static function (string $code) use ($statusMeta, $statusLabels, $icon, $h): string {
    $m = $statusMeta[$code] ?? ['tone' => 'un', 'icon' => 'clock'];

    return '<span class="dfs-st dfs-st--' . $m['tone'] . '">' . $icon($m['icon']) . $h($statusLabels[$code] ?? $code) . '</span>';
};

$unconfirmed = max(0, (int) $stats['concerned'] - (int) $stats['read']);
$ringPct = max(0.0, min(100.0, $compliancePct));

/* Vue « Par personne » : une ligne par destinataire, une colonne par document. */
$people = [];
foreach ($docs as $doc) {
    foreach ($doc['recipients'] as $r) {
        $uid = (int) $r['user_id'];
        $people[$uid] ??= ['name' => (string) $r['display_name'], 'unit' => (string) $r['unit_label'], 'by_doc' => []];
        $people[$uid]['by_doc'][(int) $doc['document_id']] = $r;
    }
}
uasort($people, static fn (array $a, array $b): int => strcasecmp($a['name'], $b['name']));
?>
<div class="dfs" data-dfs data-dfs-open="<?= $documentFilter ?>" data-dfs-status="<?= $h($statusFilter) ?>">
<svg width="0" height="0" style="position:absolute" aria-hidden="true">
<symbol id="dfs-search" viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></symbol>
<symbol id="dfs-x" viewBox="0 0 24 24"><path d="M18 6 6 18M6 6l12 12"/></symbol>
<symbol id="dfs-down" viewBox="0 0 24 24"><path d="m6 9 6 6 6-6"/></symbol>
<symbol id="dfs-check" viewBox="0 0 24 24"><path d="M5 12l5 5 9-10"/></symbol>
<symbol id="dfs-eye" viewBox="0 0 24 24"><path d="M2 12s4-7 10-7 10 7 10 7-4 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/></symbol>
<symbol id="dfs-alert" viewBox="0 0 24 24"><path d="M12 3 2 20h20zM12 10v4M12 17v.5"/></symbol>
<symbol id="dfs-clock" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></symbol>
<symbol id="dfs-bell" viewBox="0 0 24 24"><path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9M10 21a2 2 0 0 0 4 0"/></symbol>
<symbol id="dfs-file" viewBox="0 0 24 24"><path d="M14 3H6v18h12V7zM14 3v4h4"/></symbol>
<symbol id="dfs-users" viewBox="0 0 24 24"><circle cx="9" cy="8" r="4"/><path d="M2 21c1-4 3.5-6 7-6s6 2 7 6M16 4a4 4 0 0 1 0 8M22 21c-.5-3-2-5-4-5.5"/></symbol>
<symbol id="dfs-cal" viewBox="0 0 24 24"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 10h18M8 3v4M16 3v4"/></symbol>
<symbol id="dfs-dots" viewBox="0 0 24 24"><circle cx="5" cy="12" r="1"/><circle cx="12" cy="12" r="1"/><circle cx="19" cy="12" r="1"/></symbol>
<symbol id="dfs-list" viewBox="0 0 24 24"><path d="M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01"/></symbol>
<symbol id="dfs-grid" viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M3 9h18M3 15h18M9 3v18"/></symbol>
<symbol id="dfs-up" viewBox="0 0 24 24"><path d="M12 20V8M6 14l6-6 6 6M4 4h16"/></symbol>
</svg>

<section class="dfs-hero">
    <div class="dfs-hero__bg" aria-hidden="true"></div>
    <div class="dfs-hero__in">
        <div>
            <div class="dfs-kk"><i aria-hidden="true"></i>Doctrine et documents · Conformité</div>
            <h1>Suivi de diffusion</h1>
            <p>Qui a reçu, ouvert et confirmé la lecture de chaque version. Relancez les retardataires avant l’échéance.</p>
        </div>
        <?php if ($canCreate || $canManage): ?>
        <div class="dfs-hero__acts">
            <?php if ($canManage): ?>
            <a class="dfs-hb2" href="<?= $h(url('back-office/documents/types')) ?>">Types de documents</a>
            <?php endif; ?>
            <?php if ($canCreate): ?>
            <a class="dfs-hb1" href="<?= $h(url('back-office/documents/publier')) ?>"><?= $icon('up') ?>Publier un document</a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
    <div class="dfs-hs">
        <div><span class="dfs-k">Documents actifs</span><b><?= (int) $commandStats['active_docs'] ?></b><small><?= (int) $stats['concerned'] ?> <?= (int) $stats['concerned'] > 1 ? 'diffusions' : 'diffusion' ?></small></div>
        <div><span class="dfs-k">Taux de lecture</span><div class="dfs-rate"><span class="dfs-ring" style="--dfs-pct:<?= $h((string) $ringPct) ?>%" aria-hidden="true"></span><b class="is-ok"><?= $h($num($compliancePct, $compliancePct === floor($compliancePct) ? 0 : 1)) ?> %</b></div><small><?= (int) $stats['read'] ?> sur <?= (int) $stats['concerned'] ?> en règle</small></div>
        <div><span class="dfs-k">En retard</span><b class="<?= (int) $stats['overdue'] > 0 ? 'is-bad' : '' ?>"><?= (int) $stats['overdue'] ?></b><small>échéance dépassée</small></div>
        <div><span class="dfs-k">Non confirmés</span><b><?= $unconfirmed ?></b><small><?= (int) $stats['opened'] ?> <?= (int) $stats['opened'] > 1 ? 'ouvertures enregistrées' : 'ouverture enregistrée' ?></small></div>
        <div><span class="dfs-k">Échéances &lt; 7 j</span><b><?= (int) $commandStats['approaching_deadline'] ?></b><small><?= (int) $commandStats['approaching_deadline'] > 0 ? 'à surveiller' : 'aucune imminente' ?></small></div>
    </div>
</section>

<?php if ($docs === []): ?>
<div class="dfs-panel"><div class="dfs-empty"><b>Aucun document publié</b>Les documents publiés avec des destinataires apparaîtront ici avec leur suivi de lecture.</div></div>
<?php else: ?>
<div class="dfs-tools" data-dfs-tools hidden>
    <div class="dfs-seg" role="group" aria-label="Affichage">
        <button type="button" class="is-on" data-dfs-view="doc" aria-pressed="true"><?= $icon('list') ?>Par document</button>
        <button type="button" data-dfs-view="ppl" aria-pressed="false"><?= $icon('grid') ?>Par personne</button>
    </div>
    <div class="dfs-chips" role="group" aria-label="Filtrer par statut">
        <button type="button" class="dfs-chip is-on" data-dfs-filter="" aria-pressed="true">Tous<em><?= (int) $stats['concerned'] ?></em></button>
        <?php foreach (['OVERDUE', 'UNREAD', 'ACK_REQUIRED', 'ACK_OUTDATED', 'READ', 'ACKNOWLEDGED'] as $code): ?>
            <?php if (($statusCounts[$code] ?? 0) > 0): ?>
        <button type="button" class="dfs-chip" data-dfs-filter="<?= $h($code) ?>" aria-pressed="false"><i class="dfs-bg--<?= $h($statusMeta[$code]['tone']) ?>" aria-hidden="true"></i><?= $h($statusLabels[$code]) ?><em><?= (int) $statusCounts[$code] ?></em></button>
            <?php endif; ?>
        <?php endforeach; ?>
    </div>
    <span class="dfs-sp"></span>
    <label class="dfs-search"><?= $icon('search') ?><span class="sr-only">Rechercher</span><input type="search" data-dfs-q placeholder="Référence, titre, personne…" autocomplete="off"></label>
</div>

<div data-dfs-pane="doc">
    <div class="dfs-dl">
    <?php foreach ($docs as $doc): ?>
        <?php
        $docId = (int) $doc['document_id'];
        $recipients = $doc['recipients'];
        $total = count($recipients);
        $counts = array_fill_keys(array_keys($statusMeta), 0);
        $lates = [];
        foreach ($recipients as $r) {
            $counts[$r['status_code']] = ($counts[$r['status_code']] ?? 0) + 1;
            if ($r['status_code'] === 'OVERDUE') {
                $lates[] = $r;
            }
        }
        $done = (int) $counts['ACKNOWLEDGED'] + (int) $counts['READ'];
        $toRemind = (int) $counts['UNREAD'] + (int) $counts['ACK_REQUIRED'] + (int) $counts['ACK_OUTDATED'] + (int) $counts['OVERDUE'];
        $pct = $total > 0 ? (int) round(100 * $done / $total) : 0;
        $n = $daysLeft($doc['deadline']);
        $panelId = 'dfs-rt-' . $docId;
        ?>
        <article class="dfs-dc<?= $lates !== [] ? ' has-late' : '' ?>" id="dfs-doc-<?= $docId ?>" data-dfs-doc data-dfs-q="<?= $h(mb_strtolower($doc['reference'] . ' ' . $doc['title'])) ?>">
            <div class="dfs-dh" data-dfs-zone>
                <div class="dfs-dh__main">
                    <div class="dfs-ref"><code translate="no"><?= $h($doc['reference']) ?></code><span class="dfs-ver" translate="no"><?= $h($doc['version_label']) ?></span><?php if ($doc['type_label'] !== ''): ?><span class="dfs-typ" translate="no"><?= $h($doc['type_label']) ?></span><?php endif; ?></div>
                    <h3 translate="no"><?= $h($doc['title']) ?></h3>
                    <div class="dfs-meta">
                        <?php if ($fmtDay($doc['published_at']) !== ''): ?>
                        <span><?= $icon('up') ?>Publié le <?= $h($fmtDay($doc['published_at'])) ?></span>
                        <?php endif; ?>
                        <?php if ($n !== null): ?>
                        <span class="dfs-due<?= $n < 0 ? ' is-over' : ($n <= 7 ? ' is-soon' : '') ?>"><?= $icon('cal') ?>Échéance <?= $h($fmtDay($doc['deadline'])) ?><?php if ($n < 0): ?> · dépassée de <?= -$n ?> j<?php elseif ($n === 0): ?> · aujourd’hui<?php else: ?> · dans <?= $n ?> j<?php endif; ?></span>
                        <?php else: ?>
                        <span><?= $icon('cal') ?>Sans échéance</span>
                        <?php endif; ?>
                        <span><?= $icon('users') ?><?= $total ?> <?= $total > 1 ? 'destinataires' : 'destinataire' ?></span>
                        <?php if (!empty($doc['last_reminder_at'])): ?>
                        <span><?= $icon('bell') ?><span>Relancé le <?= $h($fmtStamp($doc['last_reminder_at'])) ?></span></span>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="dfs-prog">
                    <div class="dfs-prog__top"><b><?= $pct ?> %</b><small><?= $done ?> / <?= $total ?> en règle</small></div>
                    <div class="dfs-pbar" aria-hidden="true">
                        <?php foreach ($statusMeta as $code => $m): if ($counts[$code] < 1) { continue; } ?>
                        <i class="dfs-bg--<?= $h($m['tone']) ?>" style="width:<?= $h((string) round(100 * $counts[$code] / max(1, $total), 2)) ?>%"></i>
                        <?php endforeach; ?>
                    </div>
                    <div class="dfs-leg">
                        <?php foreach ($statusMeta as $code => $m): if ($counts[$code] < 1) { continue; } ?>
                        <span><i class="dfs-bg--<?= $h($m['tone']) ?>" aria-hidden="true"></i><b><?= (int) $counts[$code] ?></b> <?= $h($statusLabels[$code]) ?></span>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="dfs-dact">
                    <details class="dfs-menu">
                        <summary class="dfs-btn dfs-btn--ic" aria-label="Actions sur le document"><?= $icon('dots') ?></summary>
                        <div class="dfs-pop">
                            <a class="dfs-pi" href="<?= $h(url('documents/doctrine/' . $docId)) ?>">Ouvrir le document</a>
                            <?php if ($canCreate): ?>
                            <a class="dfs-pi" href="<?= $h(url('back-office/documents/' . $docId . '/nouvelle-version')) ?>">Publier une nouvelle version</a>
                            <?php endif; ?>
                            <?php if ($canRemind && $toRemind > 0): ?>
                            <button type="button" class="dfs-pi" data-dfs-remind="<?= $docId ?>" data-dfs-ref="<?= $h($doc['reference']) ?>" data-dfs-count="<?= $toRemind ?>">Relancer les non-confirmés (<?= $toRemind ?>)</button>
                            <?php endif; ?>
                        </div>
                    </details>
                    <button type="button" class="dfs-car" data-dfs-toggle aria-expanded="true" aria-controls="<?= $h($panelId) ?>" aria-label="Afficher les destinataires"><?= $icon('down') ?></button>
                </div>
            </div>
            <?php if ($lates !== []): ?>
            <div class="dfs-late">
                <span class="dfs-stk"><?php foreach (array_slice($lates, 0, 4) as $r) { echo $avatar((int) $r['user_id'], (string) $r['display_name']); } ?></span>
                <span><b><?= count($lates) ?> en retard</b> · <span translate="no"><?= $h(implode(', ', array_map(static fn (array $r): string => (string) $r['display_name'], array_slice($lates, 0, 6)))) ?><?= count($lates) > 6 ? '…' : '' ?></span></span>
                <?php if ($canRemind): ?>
                <button type="button" data-dfs-remind="<?= $docId ?>" data-dfs-ref="<?= $h($doc['reference']) ?>" data-dfs-count="<?= $toRemind ?>">Relancer les non-confirmés</button>
                <?php endif; ?>
            </div>
            <?php endif; ?>
            <div class="dfs-rt" id="<?= $h($panelId) ?>">
                <?php if ($recipients === []): ?>
                <div class="dfs-empty dfs-empty--sm">Aucun destinataire pour ce document.</div>
                <?php else: ?>
                <table>
                    <thead><tr><th>Personnel</th><th class="dfs-hide-s">Unité</th><th>Statut</th><th class="dfs-hide-s">Première ouverture</th><th>Lecture confirmée</th></tr></thead>
                    <tbody>
                    <?php foreach ($recipients as $r): ?>
                        <tr data-dfs-row data-status="<?= $h($r['status_code']) ?>" data-dfs-q="<?= $h(mb_strtolower($r['display_name'])) ?>">
                            <td><div class="dfs-who"><?= $avatar((int) $r['user_id'], (string) $r['display_name']) ?><b translate="no"><?= $h($r['display_name']) ?></b></div></td>
                            <td class="dfs-hide-s" translate="no"><?= $h($r['unit_label'] !== '' ? $r['unit_label'] : '—') ?></td>
                            <td><?= $badge((string) $r['status_code']) ?></td>
                            <td class="dfs-hide-s dfs-tm<?= empty($r['first_viewed_at']) ? ' is-na' : '' ?>"><?= $h($fmtStamp($r['first_viewed_at']) ?: '—') ?></td>
                            <td class="dfs-tl"><span class="dfs-tm<?= empty($r['signed_at']) ? ' is-na' : '' ?>"><?= $h($fmtStamp($r['signed_at']) ?: '—') ?></span><?php if (!empty($r['signed_late'])): ?><small>après l’échéance</small><?php endif; ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>
        </article>
    <?php endforeach; ?>
    </div>
    <div class="dfs-panel" data-dfs-none hidden><div class="dfs-empty"><b>Aucun résultat</b>Aucune diffusion ne correspond à ces filtres.</div></div>
</div>

<div data-dfs-pane="ppl" hidden>
    <?php if ($people === []): ?>
    <div class="dfs-panel"><div class="dfs-empty"><b>Aucun destinataire</b>Aucune personne n’est concernée par les documents publiés.</div></div>
    <?php else: ?>
    <div class="dfs-mx">
        <table>
            <thead><tr><th>Personnel</th><th>Conformité</th>
                <?php foreach ($docs as $doc): ?>
                <th class="dfs-dcol"><code translate="no"><?= $h($doc['reference']) ?></code><span><span translate="no"><?= $h($doc['version_label']) ?></span><?php if (!empty($doc['deadline'])): ?> · éch. <?= $h($fmtDay($doc['deadline'])) ?><?php endif; ?></span></th>
                <?php endforeach; ?>
            </tr></thead>
            <tbody>
            <?php foreach ($people as $uid => $p): ?>
                <?php
                $mine = $p['by_doc'];
                $ok = count(array_filter($mine, static fn (array $r): bool => !empty($statusMeta[$r['status_code']]['done'])));
                $refs = [];
                foreach ($docs as $doc) {
                    if (isset($mine[(int) $doc['document_id']])) {
                        $refs[] = $doc['reference'] . ' ' . $doc['title'];
                    }
                }
                ?>
                <tr data-dfs-prow data-statuses="<?= $h(implode(' ', array_unique(array_map(static fn (array $r): string => (string) $r['status_code'], $mine)))) ?>" data-dfs-q="<?= $h(mb_strtolower($p['name'] . ' ' . implode(' ', $refs))) ?>">
                    <td><div class="dfs-who"><?= $avatar((int) $uid, $p['name']) ?><span><b translate="no"><?= $h($p['name']) ?></b><?php if ($p['unit'] !== ''): ?><small translate="no"><?= $h($p['unit']) ?></small><?php endif; ?></span></div></td>
                    <td><div class="dfs-pr"><span class="dfs-pr__b"><i style="width:<?= $h((string) ($mine !== [] ? round(100 * $ok / count($mine), 2) : 0)) ?>%"></i></span><span><?= $ok ?>/<?= count($mine) ?></span></div></td>
                    <?php foreach ($docs as $doc): $r = $mine[(int) $doc['document_id']] ?? null; ?>
                    <td class="dfs-c"<?= $r !== null ? ' data-status="' . $h((string) $r['status_code']) . '"' : '' ?>><?= $r !== null ? $badge((string) $r['status_code']) : '<span class="dfs-tm is-na">—</span>' ?></td>
                    <?php endforeach; ?>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <div class="dfs-panel" data-dfs-pnone hidden><div class="dfs-empty"><b>Aucun résultat</b>Aucune personne ne correspond à ces filtres.</div></div>
    <?php endif; ?>
</div>
<?php endif; ?>

<?php if ($canRemind && $docs !== []): ?>
<dialog class="dfs-md" aria-labelledby="dfs-md-title" data-dfs-dialog>
    <form method="post" action="<?= $h(url('back-office/documents/relances')) ?>">
        <input type="hidden" name="_csrf_token" value="<?= $h($csrf_token) ?>">
        <input type="hidden" name="document_id" value="" data-dfs-dialog-doc>
        <div class="dfs-md__h">
            <div><span class="dfs-k">Relance</span><h2 id="dfs-md-title">Relancer les non-confirmés</h2></div>
            <button type="button" class="dfs-btn dfs-btn--ic" data-dfs-close aria-label="Fermer"><?= $icon('x') ?></button>
        </div>
        <div class="dfs-md__b">
            <p class="dfs-note"><?= $icon('bell') ?><span>Un rappel part vers chaque destinataire qui n’a pas encore lu ou confirmé la version en vigueur.</span></p>
            <p class="dfs-md__doc"><code translate="no" data-dfs-dialog-ref></code> · <b data-dfs-dialog-count></b> <span class="dfs-k">destinataires</span></p>
            <label class="dfs-fl"><span>Message (facultatif)</span><textarea class="dfs-fin" name="note" rows="3" maxlength="500" placeholder="Ajouté au rappel"></textarea></label>
        </div>
        <div class="dfs-md__f">
            <button type="button" class="dfs-btn" data-dfs-close>Annuler</button>
            <button type="submit" class="dfs-btn dfs-btn--dark"><?= $icon('bell') ?>Envoyer les rappels</button>
        </div>
    </form>
</dialog>
<?php endif; ?>
</div>
<script src="<?= $h(asset_url('assets/js/doctrine-compliance.js')) ?>" defer></script>
