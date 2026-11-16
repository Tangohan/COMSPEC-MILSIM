<?php
declare(strict_types=1);

/**
 * JNET — comptes rendus (/jnet/comptes-rendus), dans la coque views/jnet/_page.php. Données : JnetReportsController::index.
 *
 * @var string $jrTab
 * @var array<string, string> $jrTabs
 * @var bool $jrCanRead
 * @var bool $jrCanManage
 * @var array{sent: list<array<string, mixed>>, debriefs: list<array<string, mixed>>} $jrMine
 * @var array{q: string, kind: string} $jrMineFilter
 */
use App\Services\Aar\AarReportAdminService as Aar;

$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$base = url('jnet/comptes-rendus');
$tab = (string) ($jrTab ?? 'mes');
$canRead = !empty($jrCanRead);
$canManage = !empty($jrCanManage);
$csrf = '<input type="hidden" name="_csrf_token" value="' . $h($csrfToken ?? '') . '">';
$fmt = static function (mixed $raw, string $format = 'd/m/Y'): string {
    $t = is_string($raw) && $raw !== '' ? strtotime($raw) : false;

    return $t ? date($format, $t) : '—';
};
$tone = static fn (string $status): string => 'jr-pill--' . Aar::tone($status);
$mine = is_array($jrMine ?? null) ? $jrMine : ['sent' => [], 'debriefs' => []];
$sent = (array) ($mine['sent'] ?? []);
$debriefs = (array) ($mine['debriefs'] ?? []);
$mf = is_array($jrMineFilter ?? null) ? $jrMineFilter : ['q' => '', 'kind' => ''];
$k = is_array($jrKpis ?? null) ? $jrKpis : [];
$all = is_array($jrAll ?? null) ? $jrAll : [];
$tabCount = [
    'mes' => count($sent) + count($debriefs),
    'rapports' => count($all),
    'actions' => (int) ($k['actions_open'] ?? 0),
    'terrain' => null,
];
$pad = static fn (int $n): string => str_pad((string) $n, 2, '0', STR_PAD_LEFT);

// Chiffres clés : ce qui attend le lecteur d'abord.
if ($canRead && $tab !== 'mes') {
    $delay = $k['avg_delay_hours'] ?? null;
    $stats = [
        ['value' => (int) ($k['to_review'] ?? 0), 'label' => 'à relire', 'href' => $base . '?statut=pending', 'warn' => (int) ($k['to_review'] ?? 0) > 0],
        ['value' => (int) ($k['validated'] ?? 0), 'of' => (int) ($k['total'] ?? 0), 'label' => 'validés', 'href' => $base . '?statut=validated'],
        ['value' => (int) ($k['actions_open'] ?? 0), 'label' => (int) ($k['actions_overdue'] ?? 0) > 0 ? 'actions ouvertes, ' . (int) $k['actions_overdue'] . ' en retard' : 'actions ouvertes', 'href' => $base . '?vue=actions', 'warn' => (int) ($k['actions_overdue'] ?? 0) > 0],
        ['value' => Aar::formatDelay($delay), 'label' => 'délai moyen de dépôt', 'href' => null],
    ];
} else {
    $read = 0;
    foreach ($sent as $s) {
        $read += in_array((string) ($s['status'] ?? ''), ['Lu par le poste de commandement', 'Traité', 'Archivé'], true) ? 1 : 0;
    }
    $waiting = 0;
    $done = 0;
    foreach ($debriefs as $d) {
        $waiting += empty($d['closed']) && in_array((string) $d['state'], ['sent', 'delivered', 'opened'], true) ? 1 : 0;
        $done += in_array((string) $d['state'], ['submitted', 'read'], true) ? 1 : 0;
    }
    $stats = [
        ['value' => count($sent), 'label' => 'envoyés depuis mon ATAK', 'href' => $base . '?' . http_build_query(['vue' => 'mes', 'envoi' => 'envoyes'])],
        ['value' => $read, 'label' => 'lus par le poste de commandement', 'href' => null],
        ['value' => $waiting, 'label' => 'CR de fin d’opération à remettre', 'href' => $base . '?' . http_build_query(['vue' => 'mes', 'envoi' => 'fin-op']), 'warn' => $waiting > 0],
        ['value' => $done, 'label' => 'CR de fin d’opération remis', 'href' => null],
    ];
}
?>
<div class="jr" data-jr>

<section class="ja-stats jr-stats" style="--n:<?= count($stats) ?>" aria-label="Chiffres clés">
    <?php foreach ($stats as $st): ?>
        <?php $tag = $st['href'] !== null ? 'a' : 'div'; ?>
        <<?= $tag ?> class="ja-stat<?= !empty($st['warn']) ? ' jr-stat--warn' : '' ?>"<?= $st['href'] !== null ? ' href="' . $h($st['href']) . '"' : '' ?>>
            <b><?= $h((string) $st['value']) ?><?php if (isset($st['of'])): ?> <small>/ <?= (int) $st['of'] ?></small><?php endif; ?></b>
            <span><?= $h($st['label']) ?></span>
        </<?= $tag ?>>
    <?php endforeach; ?>
</section>

<div class="jr-bar">
    <nav class="ja-tabs jr-tabs" aria-label="Vues des comptes rendus">
        <?php $i = 0; foreach ($jrTabs ?? [] as $key => $label): $on = $tab === $key; $n = $tabCount[$key] ?? null; ?>
            <a class="ja-tab<?= $on ? ' is-on' : '' ?>" href="<?= $h($base . '?vue=' . $key) ?>"<?= $on ? ' aria-current="page"' : '' ?>><span class="ja-mono"><?= $pad(++$i) ?></span><?= $h($label) ?><?php if ($n !== null && $n > 0): ?><em><?= min(999, (int) $n) ?></em><?php endif; ?></a>
        <?php endforeach; ?>
    </nav>
    <?php if ($canRead): ?>
        <a class="ja-b2 jr-cross" href="<?= $h(url('back-office/atak/comptes-rendus')) ?>">Ouvrir dans le back-office</a>
    <?php endif; ?>
</div>

<?php if ($tab === 'mes'): ?>
<?php
    $q = mb_strtolower(trim((string) $mf['q']), 'UTF-8');
    $match = static function (array $hay) use ($q): bool {
        return $q === '' || str_contains(mb_strtolower(implode(' ', array_map('strval', $hay)), 'UTF-8'), $q);
    };
    $sentShown = $mf['kind'] === 'fin-op' ? [] : array_values(array_filter($sent, static fn (array $s): bool => $match([$s['type'] ?? '', $s['summary'] ?? '', $s['grid'] ?? '', $s['status'] ?? ''])));
    $debriefsShown = $mf['kind'] === 'envoyes' ? [] : array_values(array_filter($debriefs, static fn (array $d): bool => $match([$d['label'] ?? '', $d['mission_label'] ?? '', $d['state_label'] ?? ''])));
?>
<form class="jr-filters" method="get" action="<?= $h($base) ?>" role="search" aria-label="Rechercher dans mes comptes rendus">
    <input type="hidden" name="vue" value="mes">
    <label class="jr-field jr-field--grow"><span>Rechercher</span><input type="search" name="q" value="<?= $h($mf['q']) ?>" placeholder="Type, résumé, opération…"></label>
    <label class="jr-field"><span>Envoi</span>
        <select name="envoi" data-jr-autosubmit>
            <?php foreach (['' => 'Tous', 'envoyes' => 'Envoyés depuis mon ATAK', 'fin-op' => 'CR de fin d’opération'] as $v => $l): ?>
                <option value="<?= $h($v) ?>"<?= $mf['kind'] === $v ? ' selected' : '' ?>><?= $h($l) ?></option>
            <?php endforeach; ?>
        </select>
    </label>
    <button class="ja-b1 jr-btn" type="submit">Filtrer</button>
    <?php if ($mf['q'] !== '' || $mf['kind'] !== ''): ?><a class="ja-b2 jr-btn" href="<?= $h($base . '?' . http_build_query(['vue' => 'mes'])) ?>">Effacer</a><?php endif; ?>
</form>

<?php if ($mf['kind'] !== 'fin-op'): ?>
<section class="jr-sec" aria-labelledby="jr-sent-t">
    <header class="jr-sh"><span class="jr-sh__n ja-mono" aria-hidden="true">01</span><div><h2 class="ja-disp ja-disp--sm" id="jr-sent-t">Envoyés depuis mon ATAK</h2><p>Chaque compte rendu envoyé depuis votre téléphone et ce qu’Athena en a fait.</p></div></header>
    <?php if ($sentShown === []): ?>
        <div class="ja-empty"><p><?= $sent === [] ? 'Aucun compte rendu envoyé depuis votre ATAK pour l’instant.' : 'Aucun compte rendu ne correspond à cette recherche.' ?></p></div>
    <?php else: ?>
        <ul class="ja-rows jr-rows">
            <?php foreach ($sentShown as $s): $sid = (int) $s['id']; ?>
                <li>
                    <button type="button" class="ja-row jr-row" data-jr-open="jr-sent-<?= $sid ?>" aria-haspopup="dialog">
                        <span class="n">CR-<?= $sid ?></span>
                        <span class="t"><b><?= $h($s['type'] ?? 'Compte rendu') ?><?= !empty($s['urgent']) ? ' · ' . $h($s['priority'] ?? '') : '' ?></b><small><?= $h(($s['summary'] ?? '') !== '' ? $s['summary'] : 'Sans résumé') ?> · <?= $h($fmt($s['at'] ?? null, 'd/m/Y H:i')) ?></small></span>
                        <span class="m"><span class="ja-pill jr-pill"><?= $h(($s['status'] ?? '') !== '' ? $s['status'] : 'Reçu par Athena') ?></span></span>
                        <span class="ar" aria-hidden="true">→</span>
                    </button>
                    <dialog class="jr-dlg" id="jr-sent-<?= $sid ?>" aria-labelledby="jr-sent-<?= $sid ?>-t">
                        <div class="jr-dlg__head">
                            <h3 id="jr-sent-<?= $sid ?>-t"><?= $h($s['type'] ?? 'Compte rendu') ?> · CR-<?= $sid ?></h3>
                            <button type="button" class="jr-dlg__x" data-jr-close aria-label="Fermer">×</button>
                        </div>
                        <dl class="jr-kv">
                            <div><dt>État</dt><dd><?= $h(($s['status'] ?? '') !== '' ? $s['status'] : 'Reçu par Athena') ?></dd></div>
                            <div><dt>Envoyé le</dt><dd><?= $h($fmt($s['at'] ?? null, 'd/m/Y à H:i')) ?></dd></div>
                            <div><dt>Priorité</dt><dd><?= $h($s['priority'] ?? 'Routine') ?></dd></div>
                            <div><dt>Position</dt><dd class="ja-mono"><?= $h(($s['grid'] ?? '') !== '' ? $s['grid'] : '—') ?></dd></div>
                            <div><dt>Indicatif</dt><dd><?= $h(($s['by'] ?? '') !== '' ? $s['by'] : '—') ?></dd></div>
                        </dl>
                        <p class="jr-text"><?= $h(($s['summary'] ?? '') !== '' ? $s['summary'] : 'Sans résumé.') ?></p>
                        <div class="jr-dlg__foot"><button type="button" class="ja-b2 jr-btn" data-jr-close>Fermer</button></div>
                    </dialog>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</section>
<?php endif; ?>

<?php if ($mf['kind'] !== 'envoyes'): ?>
<section class="jr-sec" aria-labelledby="jr-dbf-t">
    <header class="jr-sh"><span class="jr-sh__n ja-mono" aria-hidden="true"><?= $mf['kind'] === 'fin-op' ? '01' : '02' ?></span><div><h2 class="ja-disp ja-disp--sm" id="jr-dbf-t">CR de fin d’opération</h2><p>Les comptes rendus que le commandement vous a demandés sur votre ATAK, et où vous en êtes.</p></div></header>
    <?php if ($debriefsShown === []): ?>
        <div class="ja-empty"><p><?= $debriefs === [] ? 'Aucun CR de fin d’opération ne vous a été demandé.' : 'Aucun CR ne correspond à cette recherche.' ?></p></div>
    <?php else: ?>
        <ul class="ja-rows jr-rows">
            <?php foreach ($debriefsShown as $d): $st = (string) $d['state']; ?>
                <li class="ja-row jr-row jr-row--static">
                    <span class="n">CR</span>
                    <?php
                    $dSub = array_filter([
                        (string) $d['mission_label'],
                        $d['mode'] === 'read' ? 'à lire' : 'à remplir',
                        'envoyé le ' . $fmt($d['sent_at'], 'd/m/Y H:i'),
                        $d['done_at'] === '' ? '' : ($st === 'read' ? 'lu le ' : 'remis le ') . $fmt($d['done_at'], 'd/m/Y H:i'),
                    ]);
                    ?>
                    <span class="t"><b><?= $h($d['label'] !== '' ? $d['label'] : 'Fin d’opération') ?></b><small><?= $h(implode(' · ', $dSub)) ?></small></span>
                    <span class="m"><span class="ja-pill jr-pill <?= in_array($st, ['submitted', 'read'], true) ? 'jr-pill--ok' : (in_array($st, ['expired', 'cancelled'], true) ? 'jr-pill--bad' : 'jr-pill--warn') ?>"><?= $h($d['state_label'] !== '' ? $d['state_label'] : 'Envoyé') ?><?= !empty($d['closed']) && !in_array($st, ['submitted', 'read'], true) ? ' · clôturé' : '' ?></span></span>
                    <span class="ar" aria-hidden="true"></span>
                </li>
            <?php endforeach; ?>
        </ul>
        <p class="jr-note">Le CR se remplit sur votre téléphone ATAK, dans l’app Comptes rendus.</p>
    <?php endif; ?>
</section>
<?php endif; ?>

<?php elseif ($tab === 'rapports'): ?>
<?php
    $f = is_array($jrFilters ?? null) ? $jrFilters : [];
    $reports = is_array($jrReports ?? null) ? $jrReports : [];
    $counts = is_array($jrCounts ?? null) ? $jrCounts : [];
    $filtersOn = ($f['q'] ?? '') !== '' || ($f['status'] ?? '') !== '' || ($f['author'] ?? '') !== '' || ($f['unit'] ?? '') !== ''
        || ($f['type'] ?? '') !== '' || (int) ($f['period'] ?? 0) > 0 || !empty($f['open_actions']);
?>
<section class="jr-sec" aria-labelledby="jr-list-t">
    <header class="jr-sh"><span class="jr-sh__n ja-mono" aria-hidden="true">01</span><div><h2 class="ja-disp ja-disp--sm" id="jr-list-t">Comptes rendus post-op</h2><p>Les retours rédigés après chaque opération, avec leur relecture.</p></div></header>

    <div class="ja-chips jr-chips" role="group" aria-label="Statut">
        <?php $qsBase = array_filter(['vue' => 'rapports', 'q' => $f['q'] ?? '', 'auteur' => $f['author'] ?? '', 'unite' => $f['unit'] ?? '', 'type' => $f['type'] ?? '', 'periode' => (int) ($f['period'] ?? 0) ?: '', 'tri' => ($f['sort'] ?? 'recent') !== 'recent' ? $f['sort'] : '', 'actions_ouvertes' => !empty($f['open_actions']) ? '1' : ''], static fn ($v) => $v !== '' && $v !== null); ?>
        <a class="ja-chip jr-chip<?= ($f['status'] ?? '') === '' ? ' is-on' : '' ?>" href="<?= $h($base . '?' . http_build_query($qsBase)) ?>"<?= ($f['status'] ?? '') === '' ? ' aria-current="true"' : '' ?>>Tous <b><?= count($all) ?></b></a>
        <?php foreach (Aar::STATUSES as $code => $label): $on = ($f['status'] ?? '') === $code; ?>
            <a class="ja-chip jr-chip<?= $on ? ' is-on' : '' ?>" href="<?= $h($base . '?' . http_build_query($qsBase + ['statut' => $code])) ?>"<?= $on ? ' aria-current="true"' : '' ?>><i class="jr-dot <?= $h($tone($code)) ?>" aria-hidden="true"></i><?= $h($label) ?> <b><?= (int) ($counts[$code] ?? 0) ?></b></a>
        <?php endforeach; ?>
    </div>

    <form class="jr-filters" method="get" action="<?= $h($base) ?>" role="search" aria-label="Filtrer les comptes rendus">
        <input type="hidden" name="vue" value="rapports">
        <?php if (($f['status'] ?? '') !== ''): ?><input type="hidden" name="statut" value="<?= $h($f['status']) ?>"><?php endif; ?>
        <label class="jr-field jr-field--grow"><span>Rechercher</span><input type="search" name="q" value="<?= $h($f['q'] ?? '') ?>" placeholder="Référence, opération, auteur…"></label>
        <label class="jr-field"><span>Unité</span>
            <select name="unite" data-jr-autosubmit>
                <option value="">Toutes</option>
                <?php foreach ($jrUnits ?? [] as $u): ?><option value="<?= $h($u) ?>"<?= ($f['unit'] ?? '') === $u ? ' selected' : '' ?>><?= $h($u) ?></option><?php endforeach; ?>
            </select>
        </label>
        <label class="jr-field"><span>Auteur</span>
            <select name="auteur" data-jr-autosubmit>
                <option value="">Tous</option>
                <?php foreach ($jrAuthors ?? [] as $a): ?><option value="<?= $h($a) ?>"<?= ($f['author'] ?? '') === $a ? ' selected' : '' ?>><?= $h($a) ?></option><?php endforeach; ?>
            </select>
        </label>
        <label class="jr-field"><span>Période</span>
            <select name="periode" data-jr-autosubmit>
                <?php foreach (\App\Controllers\Web\JnetReportsController::PERIODS as $d => $l): ?><option value="<?= (int) $d ?>"<?= (int) ($f['period'] ?? 0) === $d ? ' selected' : '' ?>><?= $h($l) ?></option><?php endforeach; ?>
            </select>
        </label>
        <label class="jr-field"><span>Type</span>
            <select name="type" data-jr-autosubmit>
                <option value="">Tous</option>
                <?php foreach (Aar::TYPES as $v => $l): ?><option value="<?= $h($v) ?>"<?= ($f['type'] ?? '') === $v ? ' selected' : '' ?>><?= $h($l) ?></option><?php endforeach; ?>
            </select>
        </label>
        <label class="jr-field"><span>Tri</span>
            <select name="tri" data-jr-autosubmit>
                <?php foreach (Aar::SORTS as $v => $l): ?><option value="<?= $h($v) ?>"<?= ($f['sort'] ?? 'recent') === $v ? ' selected' : '' ?>><?= $h($l) ?></option><?php endforeach; ?>
            </select>
        </label>
        <label class="jr-check"><input type="checkbox" name="actions_ouvertes" value="1"<?= !empty($f['open_actions']) ? ' checked' : '' ?> data-jr-autosubmit> Actions ouvertes</label>
        <button class="ja-b1 jr-btn" type="submit">Filtrer</button>
        <?php if ($filtersOn): ?><a class="ja-b2 jr-btn" href="<?= $h($base . '?vue=rapports') ?>">Effacer</a><?php endif; ?>
    </form>

    <p class="jr-count" role="status"><?= count($reports) ?> compte<?= count($reports) > 1 ? 's' : '' ?> rendu<?= count($reports) > 1 ? 's' : '' ?><?= $filtersOn ? ' sur ' . count($all) : '' ?></p>

    <?php if ($reports === []): ?>
        <div class="ja-empty">
            <p><?= $all === [] ? 'Aucun compte rendu pour l’instant. Un brouillon s’ouvre à la clôture de chaque mission planifiée.' : 'Aucun compte rendu ne correspond à ces filtres.' ?></p>
            <?php if ($filtersOn): ?><p><a class="ja-b2 jr-btn" href="<?= $h($base . '?vue=rapports') ?>">Tout afficher</a></p><?php endif; ?>
        </div>
    <?php else: ?>
        <ul class="ja-rows jr-rows">
            <?php foreach ($reports as $r):
                $rid = (int) $r['id'];
                $st = (string) ($r['status'] ?? 'pending');
                $op = trim((string) ($r['operation_label'] ?? '')) !== '' ? (string) $r['operation_label'] : (string) ($r['title'] ?? '—');
                $open = (int) ($r['totals']['open_actions'] ?? 0);
                $closed = (int) ($r['totals']['closed_actions'] ?? 0);
                $meta = array_filter([
                    trim((string) ($r['author_name'] ?? '')) ?: 'auteur inconnu',
                    (string) ($r['author_unit'] ?? ''),
                    'déposé le ' . $fmt($r['reported_at'] ?? null),
                    $open + $closed > 0 ? $closed . '/' . ($open + $closed) . ' actions closes' : '',
                    !empty($r['is_custom']) ? 'questionnaire' : '',
                ]);
            ?>
                <li>
                    <a class="ja-row jr-row" href="<?= $h($base . '/' . $rid) ?>">
                        <span class="n"><?= $h($r['reference_code'] ?? '') ?></span>
                        <span class="t"><b><?= $h($op) ?></b><small><?= $h(implode(' · ', $meta)) ?></small></span>
                        <span class="m"><span class="ja-pill jr-pill <?= $h($tone($st)) ?>"><?= $h(Aar::statusLabel($st)) ?></span></span>
                        <span class="ar" aria-hidden="true">→</span>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</section>

<?php elseif ($tab === 'actions'): ?>
<?php $actions = is_array($jrActions ?? null) ? $jrActions : []; $state = (string) ($jrActionState ?? 'ouvertes'); ?>
<section class="jr-sec" aria-labelledby="jr-act-t">
    <header class="jr-sh"><span class="jr-sh__n ja-mono" aria-hidden="true">01</span><div><h2 class="ja-disp ja-disp--sm" id="jr-act-t">Suivi des actions</h2><p>Les actions décidées dans les comptes rendus, en retard d’abord.</p></div></header>
    <div class="ja-chips jr-chips" role="group" aria-label="État des actions">
        <?php foreach (['ouvertes' => 'En cours', 'retard' => 'En retard', 'closes' => 'Closes', 'toutes' => 'Toutes'] as $code => $label): ?>
            <a class="ja-chip jr-chip<?= $state === $code ? ' is-on' : '' ?>" href="<?= $h($base . '?vue=actions&etat=' . $code) ?>"<?= $state === $code ? ' aria-current="true"' : '' ?>><?= $h($label) ?></a>
        <?php endforeach; ?>
    </div>
    <?php if ($actions === []): ?>
        <div class="ja-empty"><p><?= $state === 'retard' ? 'Aucune action en retard.' : 'Aucune action dans cette vue.' ?></p></div>
    <?php else: ?>
        <ul class="ja-rows jr-rows">
            <?php foreach ($actions as $a): ?>
                <li class="ja-row jr-row jr-row--static jr-row--act">
                    <span class="n"><a href="<?= $h($base . '/' . (int) $a['report_id'] . '#actions') ?>"><?= $h($a['reference']) ?></a></span>
                    <span class="t"><b><?= $h($a['label']) ?></b><small><?= $h($a['owner'] !== '' ? $a['owner'] : 'sans responsable') ?> · <?= $a['due_at'] ? 'échéance ' . $h($fmt($a['due_at'])) : 'sans échéance' ?> · <?= $h($a['operation']) ?></small></span>
                    <span class="m"><span class="ja-pill jr-pill <?= $a['state'] === 'closed' ? 'jr-pill--ok' : ($a['overdue'] ? 'jr-pill--bad' : 'jr-pill--warn') ?>"><?= $a['state'] === 'closed' ? 'Close' : ($a['overdue'] ? 'En retard' : 'En cours') ?></span></span>
                    <span class="ar">
                        <?php if ($canManage): ?>
                            <form method="post" action="<?= $h($base . '/' . (int) $a['report_id'] . '/actions/basculer') ?>"><?= $csrf ?>
                                <input type="hidden" name="state" value="<?= $h($a['state']) ?>"><input type="hidden" name="index" value="<?= (int) $a['index'] ?>"><input type="hidden" name="retour" value="suivi">
                                <button type="submit" class="ja-b2 jr-btn jr-btn--sm" aria-label="<?= $a['state'] === 'open' ? 'Clore' : 'Rouvrir' ?> : <?= $h($a['label']) ?>"><?= $a['state'] === 'open' ? 'Clore' : 'Rouvrir' ?></button>
                            </form>
                        <?php endif; ?>
                    </span>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</section>

<?php elseif ($tab === 'terrain'): ?>
<?php $field = is_array($jrField ?? null) ? $jrField : []; ?>
<section class="jr-sec" aria-labelledby="jr-field-t">
    <header class="jr-sh"><span class="jr-sh__n ja-mono" aria-hidden="true">01</span><div><h2 class="ja-disp ja-disp--sm" id="jr-field-t">Remontées du terrain</h2><p>Les comptes rendus envoyés depuis les ATAK de la communauté ; leur traitement se fait dans les remontées.</p></div></header>
    <?php if ($field === []): ?>
        <div class="ja-empty"><p>Aucun compte rendu envoyé depuis le terrain pour l’instant.</p></div>
    <?php else: ?>
        <ul class="ja-rows jr-rows">
            <?php foreach ($field as $fr): ?>
                <li>
                    <a class="ja-row jr-row" href="<?= $h(url('atak/sse/remontees/cr/' . (int) $fr['id'])) ?>">
                        <span class="n"><?= $h((string) ($fr['ref'] ?? '')) ?></span>
                        <span class="t"><b><?= $h((string) ($fr['title'] ?? '')) ?></b><small><?= $h((string) ($fr['author'] ?? '')) ?> · <?= $h($fmt($fr['at'] ?? null, 'd/m/Y H:i')) ?></small></span>
                        <span class="m"><span class="ja-pill jr-pill"><?= $h((string) ($fr['state_label'] ?? $fr['state'] ?? '')) ?></span></span>
                        <span class="ar" aria-hidden="true">→</span>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</section>
<?php endif; ?>
</div>
