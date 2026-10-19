<?php
/** @var list<array<string, mixed>> $rows */
/** @var int $total */
/** @var int $totalAll */
/** @var string $severityFilter */
/** @var string $statusFilter */
/** @var string $searchFilter */
/** @var string $gravityFilter */
/** @var string $sort */
/** @var array<string, int> $statusCounts */
/** @var array<string, int> $gravityCounts rapports à traiter (nouveaux ou en cours) par gravité */
/** @var array<string, array{user_id:int, tenant_id:int, email:string, name:string, lang?:string}> $linkedAccounts */

use App\Repositories\AtakModReportRepository as Reports;
use App\Services\Monitoring\AtakModReportGravity as Gravity;
use App\Support\AtakModReportDetail as Detail;
use App\Services\Monitoring\AtakModReportReplyService as Replies;

$rows = is_array($rows ?? null) ? $rows : [];
$total = (int) ($total ?? count($rows));
$totalAll = (int) ($totalAll ?? $total);
$severityFilter = trim((string) ($severityFilter ?? ''));
$statusFilter = trim((string) ($statusFilter ?? ''));
$searchFilter = trim((string) ($searchFilter ?? ''));
$gravityFilter = trim((string) ($gravityFilter ?? ''));
$sort = ($sort ?? '') === Reports::SORT_RECENT ? Reports::SORT_RECENT : Reports::SORT_GRAVITY;
$statusCounts = is_array($statusCounts ?? null) ? $statusCounts : [];
$gravityCounts = is_array($gravityCounts ?? null) ? $gravityCounts : [];
$linkedAccounts = is_array($linkedAccounts ?? null) ? $linkedAccounts : [];

$h = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');

$fmtDate = static function (mixed $raw): string {
    $s = trim((string) $raw);
    if ($s === '') {
        return '—';
    }
    try {
        return (new \DateTimeImmutable($s))->format('d/m/Y H:i');
    } catch (\Throwable) {
        return $s;
    }
};

$maskSteam = static function (string $s): string {
    return strlen($s) <= 8 ? $s : '…' . substr($s, -8);
};

$maskEmail = static function (string $email): string {
    $at = strpos($email, '@');
    if ($at === false || $at < 1) {
        return $email;
    }

    return substr($email, 0, 1) . str_repeat('•', max(2, min(6, $at - 1))) . substr($email, $at);
};

/** Type → [libellé, ton de pastille] */
$severityMeta = static function (string $sev): array {
    return match ($sev) {
        'error' => ['Erreur', 'rose'],
        'warn' => ['Alerte', 'amber'],
        'info' => ['Info', 'slate'],
        'bug' => ['Signalement joueur', 'blue'],
        default => [$sev, 'slate'],
    };
};

$statusTone = [
    Reports::STATUS_NEW => 'amber',
    Reports::STATUS_IN_PROGRESS => 'blue',
    Reports::STATUS_FIXED => 'mint',
    Reports::STATUS_DISMISSED => 'slate',
];
/** Bouton d'action vers chaque statut, depuis la carte d'un rapport. */
$statusAction = [
    Reports::STATUS_IN_PROGRESS => 'Prendre en charge',
    Reports::STATUS_FIXED => 'Marquer corrigé',
    Reports::STATUS_DISMISSED => 'Classer sans suite',
    Reports::STATUS_NEW => 'Rouvrir',
];

$csrfToken = \App\Core\Csrf::token();
$base = url('admin/atak-mod-reports');
$statusChoices = Reports::STATUS_LABELS;

$listUrl = static function (array $overrides = []) use ($base, $severityFilter, $statusFilter, $searchFilter, $gravityFilter, $sort): string {
    $params = array_merge([
        'severity' => $severityFilter,
        'status' => $statusFilter,
        'q' => $searchFilter,
        'gravity' => $gravityFilter,
        'sort' => $sort === Reports::SORT_RECENT ? Reports::SORT_RECENT : '',
    ], $overrides);
    $params = array_filter($params, static fn ($v): bool => trim((string) $v) !== '');

    return $params === [] ? $base : $base . '?' . http_build_query($params);
};

$returnFields = '<input type="hidden" name="_csrf" value="' . $h($csrfToken) . '">'
    . '<input type="hidden" name="return_severity" value="' . $h($severityFilter) . '">'
    . '<input type="hidden" name="return_status" value="' . $h($statusFilter) . '">'
    . '<input type="hidden" name="return_q" value="' . $h($searchFilter) . '">'
    . '<input type="hidden" name="return_gravity" value="' . $h($gravityFilter) . '">'
    . '<input type="hidden" name="return_sort" value="' . $h($sort === Reports::SORT_RECENT ? Reports::SORT_RECENT : '') . '">';

$hasFilters = $severityFilter !== '' || $statusFilter !== '' || $searchFilter !== '' || $gravityFilter !== '';
/** Gravité → ton de pastille */
$gravityTone = [
    Gravity::CRITICAL => 'rose',
    Gravity::HIGH => 'amber',
    Gravity::NORMAL => 'blue',
    Gravity::LOW => 'slate',
];
?>
<div class="pa amr">
    <div class="pa__frame">
        <header class="pa-hero">
            <p class="pa-crumb">
                <a class="amr-link" href="<?= $h(url('admin')) ?>">Administration du site</a>
                <span aria-hidden="true"> / </span>
                Signalements du mod
            </p>
            <div class="pa-hero__row">
                <div>
                    <p class="pa-hero__kicker">Mod COMSPEC ATAK</p>
                    <h1 class="pa-hero__title">Signalements du mod</h1>
                    <p class="pa-hero__lead">
                        Problèmes envoyés par les joueurs depuis le jeu (Échap &gt; Signaler un problème) et erreurs remontées
                        automatiquement. Prenez-les en charge, marquez-les corrigés et répondez au joueur sur Athena ou par e-mail.
                    </p>
                    <div class="pa-hero__actions">
                        <a class="pa-btn pa-btn--solid" href="<?= $h($listUrl(['status' => Reports::STATUS_NEW])) ?>">Voir les nouveaux</a>
                        <a class="pa-btn pa-btn--ghost" href="<?= $h(url('admin/atak-beta')) ?>">Accès anticipé</a>
                    </div>
                </div>
                <aside class="pa-hero__meta">
                    <p class="pa-hero__meta-kicker">Suivi</p>
                    <dl>
                        <?php foreach ($statusChoices as $value => $label): ?>
                        <div class="pa-hero__meta-row">
                            <dt><?= $h($label) ?></dt>
                            <dd><?= (int) ($statusCounts[$value] ?? 0) ?></dd>
                        </div>
                        <?php endforeach; ?>
                        <div class="pa-hero__meta-row">
                            <dt>Au total</dt>
                            <dd><?= (int) $totalAll ?></dd>
                        </div>
                    </dl>
                </aside>
            </div>
            <div class="amr-gravity" aria-label="Rapports à traiter par gravité">
                <p class="amr-gravity__label">À traiter par gravité</p>
                <ul class="amr-gravity__list">
                    <?php foreach (Gravity::LEVELS as $level): ?>
                    <li>
                        <a class="amr-grav-chip amr-grav-chip--<?= $h($level) ?><?= $gravityFilter === $level ? ' is-active' : '' ?>"
                           href="<?= $h($listUrl(['gravity' => $gravityFilter === $level ? '' : $level])) ?>"
                           title="<?= $h(Gravity::HINTS[$level]) ?>">
                            <span class="amr-grav-chip__dot" aria-hidden="true"></span>
                            <?= $h(Gravity::LABELS[$level]) ?>
                            <strong><?= (int) ($gravityCounts[$level] ?? 0) ?></strong>
                        </a>
                    </li>
                    <?php endforeach; ?>
                </ul>
                <p class="amr-gravity__hint">
                    Calculée à la réception d’après le message. Critique et signalements joueurs : e-mail à l’exploitation.
                    Faible : jamais d’e-mail au joueur. Corrigez-la sur chaque rapport si besoin.
                </p>
            </div>
        </header>

        <?php if ($flash = \App\Core\Session::getFlash('success')): ?>
            <p class="pa-flash pa-flash--ok" role="status"><?= $h((string) $flash) ?></p>
        <?php endif; ?>
        <?php if ($flash = \App\Core\Session::getFlash('error')): ?>
            <p class="pa-flash pa-flash--err" role="alert"><?= $h((string) $flash) ?></p>
        <?php endif; ?>

        <nav class="pa-jump amr-tabs" aria-label="Filtrer par suivi">
            <a class="amr-tab<?= $statusFilter === '' ? ' is-active' : '' ?>" href="<?= $h($listUrl(['status' => ''])) ?>">
                Tous <span class="amr-tabs__n"><?= (int) $totalAll ?></span>
            </a>
            <?php foreach ($statusChoices as $value => $label): ?>
            <a class="amr-tab<?= $statusFilter === $value ? ' is-active' : '' ?>" href="<?= $h($listUrl(['status' => $value])) ?>">
                <?= $h($label) ?> <span class="amr-tabs__n"><?= (int) ($statusCounts[$value] ?? 0) ?></span>
            </a>
            <?php endforeach; ?>
        </nav>

        <div class="pa-panel">
            <form method="get" action="<?= $h($base) ?>" class="amr-search" role="search">
                <?php if ($statusFilter !== ''): ?>
                <input type="hidden" name="status" value="<?= $h($statusFilter) ?>">
                <?php endif; ?>
                <label class="amr-search__field">
                    <span class="amr-label">Rechercher</span>
                    <input type="search" name="q" value="<?= $h($searchFilter) ?>" placeholder="Message, joueur, indicatif, Steam, module…">
                </label>
                <label class="amr-search__field amr-search__field--type">
                    <span class="amr-label">Type</span>
                    <select name="severity">
                        <option value="" <?= $severityFilter === '' ? 'selected' : '' ?>>Tous les types</option>
                        <option value="bug" <?= $severityFilter === 'bug' ? 'selected' : '' ?>>Signalements joueurs</option>
                        <option value="error" <?= $severityFilter === 'error' ? 'selected' : '' ?>>Erreurs</option>
                        <option value="warn" <?= $severityFilter === 'warn' ? 'selected' : '' ?>>Alertes</option>
                        <option value="info" <?= $severityFilter === 'info' ? 'selected' : '' ?>>Infos</option>
                    </select>
                </label>
                <label class="amr-search__field amr-search__field--type">
                    <span class="amr-label">Gravité</span>
                    <select name="gravity">
                        <option value="" <?= $gravityFilter === '' ? 'selected' : '' ?>>Toutes</option>
                        <?php foreach (Gravity::LABELS as $value => $label): ?>
                        <option value="<?= $h($value) ?>" <?= $gravityFilter === $value ? 'selected' : '' ?>><?= $h($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label class="amr-search__field amr-search__field--type">
                    <span class="amr-label">Trier</span>
                    <select name="sort">
                        <option value="" <?= $sort === Reports::SORT_GRAVITY ? 'selected' : '' ?>>Plus graves d’abord</option>
                        <option value="<?= $h(Reports::SORT_RECENT) ?>" <?= $sort === Reports::SORT_RECENT ? 'selected' : '' ?>>Plus récents d’abord</option>
                    </select>
                </label>
                <div class="amr-search__actions">
                    <button type="submit" class="pa-btn pa-btn--ink">Filtrer</button>
                    <?php if ($hasFilters): ?>
                    <a class="pa-btn pa-btn--line" href="<?= $h($base) ?>">Réinitialiser</a>
                    <?php endif; ?>
                </div>
            </form>

            <?php if ($rows !== []): ?>
            <section class="amr-bulk" aria-labelledby="amr-bulk-heading">
                <form id="amr-bulk" method="post" action="<?= $h(url('admin/atak-mod-reports/bulk')) ?>" class="amr-bulk__row">
                    <?= $returnFields ?>
                    <input type="hidden" name="scope" value="selected">
                    <h2 id="amr-bulk-heading" class="amr-sr">Actions groupées</h2>
                    <label class="amr-check amr-bulk__all">
                        <input type="checkbox" data-amr-all aria-label="Cocher tous les rapports affichés">
                        <span><strong data-amr-count>0</strong> coché(s)</span>
                    </label>
                    <div class="amr-bulk__buttons">
                        <button type="submit" name="bulk_action" value="in_progress" class="pa-btn pa-btn--sm pa-btn--line" data-amr-needs-selection>Prendre en charge</button>
                        <button type="submit" name="bulk_action" value="fixed" class="pa-btn pa-btn--sm pa-btn--mint" data-amr-needs-selection>Marquer corrigé</button>
                        <button type="submit" name="bulk_action" value="dismissed" class="pa-btn pa-btn--sm pa-btn--line" data-amr-needs-selection>Sans suite</button>
                        <button type="submit" name="bulk_action" value="delete" class="pa-btn pa-btn--sm amr-btn--danger" data-amr-needs-selection
                                onclick="return confirm('Supprimer définitivement les rapports cochés ?');">Supprimer</button>
                        <span class="amr-bulk__gravity">
                            <label class="amr-sr" for="amr-gravity-to">Nouvelle gravité</label>
                            <select id="amr-gravity-to" name="gravity_to">
                                <?php foreach (Gravity::LABELS as $value => $label): ?>
                                <option value="<?= $h($value) ?>"><?= $h($label) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <button type="submit" name="bulk_action" value="gravity" class="pa-btn pa-btn--sm pa-btn--line" data-amr-needs-selection>Changer la gravité</button>
                        </span>
                    </div>
                    <label class="amr-check amr-bulk__notify">
                        <input type="checkbox" name="notify" value="1" checked>
                        <span>Prévenir les joueurs quand je marque corrigé (message type dans leur langue, sans e-mail si la gravité est faible)</span>
                    </label>
                </form>

                <form method="post" action="<?= $h(url('admin/atak-mod-reports/bulk')) ?>" class="amr-bulk__row amr-bulk__row--all">
                    <?= $returnFields ?>
                    <input type="hidden" name="scope" value="filter">
                    <p class="amr-bulk__label">
                        Tout d’un coup, sur les <strong><?= (int) $total ?></strong> rapport(s)
                        <?= $hasFilters ? 'de ce filtre' : 'du journal' ?> :
                    </p>
                    <div class="amr-bulk__buttons">
                        <button type="submit" name="bulk_action" value="in_progress" class="pa-btn pa-btn--sm pa-btn--line"
                                onclick="return confirm('Prendre en charge les <?= (int) $total ?> rapport(s) ?');">Tout prendre en charge</button>
                        <button type="submit" name="bulk_action" value="fixed" class="pa-btn pa-btn--sm pa-btn--line"
                                onclick="return confirm('Marquer corrigés les <?= (int) $total ?> rapport(s) ?');">Tout marquer corrigé</button>
                        <button type="submit" name="bulk_action" value="dismissed" class="pa-btn pa-btn--sm pa-btn--line"
                                onclick="return confirm('Classer sans suite les <?= (int) $total ?> rapport(s) ?');">Tout classer sans suite</button>
                        <button type="submit" name="bulk_action" value="delete" class="pa-btn pa-btn--sm amr-btn--danger"
                                onclick="return confirm('Supprimer définitivement les <?= (int) $total ?> rapport(s) ? Cette action est irréversible.');">Tout supprimer</button>
                    </div>
                    <label class="amr-check amr-bulk__notify">
                        <input type="checkbox" name="notify" value="1">
                        <span>Prévenir les joueurs (avec « Tout marquer corrigé »)</span>
                    </label>
                </form>
            </section>
            <?php endif; ?>

            <?php if ($rows === []): ?>
                <div class="pa-empty-state amr-empty">
                    <?php if ($hasFilters): ?>
                        <p><strong>Aucun rapport pour ce filtre.</strong></p>
                        <p><a class="amr-link" href="<?= $h($base) ?>">Voir tous les rapports</a></p>
                    <?php else: ?>
                        <p><strong>Aucun rapport pour le moment.</strong></p>
                        <p>Dès qu’un joueur signale un problème depuis le jeu, il apparaîtra ici.</p>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <?php if ($total > count($rows)): ?>
                <p class="amr-note">Les <?= count($rows) ?> rapports les plus récents sont affichés sur <?= (int) $total ?>. Les actions « Tout… » s’appliquent aux <?= (int) $total ?>.</p>
                <?php endif; ?>

                <?php
                // Même problème (même empreinte) au même stade de suivi : replié sous le premier affiché.
                $repeatsOf = [];
                $repeatSkip = [];
                $groupHost = [];
                foreach ($rows as $r) {
                    $fp = trim((string) ($r['fingerprint'] ?? ''));
                    if ($fp === '') {
                        continue;
                    }
                    $key = $fp . '|' . strtolower(trim((string) ($r['workflow_status'] ?? '')));
                    $rid = (int) ($r['id'] ?? 0);
                    if (!isset($groupHost[$key])) {
                        $groupHost[$key] = $rid;
                        continue;
                    }
                    $repeatsOf[$groupHost[$key]][] = $r;
                    $repeatSkip[$rid] = true;
                }
                ?>
                <?php if ($repeatSkip !== []): ?>
                <p class="amr-note">
                    <?= count($repeatSkip) ?> rapport(s) identique(s) à un autre sont repliés sous le premier. Ouvrez « Mêmes rapports » sur une carte pour les voir ou les cocher.
                </p>
                <?php endif; ?>

                <ol class="amr-list">
                <?php foreach ($rows as $row): ?>
                    <?php
                    $id = (int) ($row['id'] ?? 0);
                    if (isset($repeatSkip[$id])) {
                        continue;
                    }
                    $repeats = $repeatsOf[$id] ?? [];
                    $sev = strtolower(trim((string) ($row['severity'] ?? 'error')));
                    [$sevLabel, $sevTone] = $severityMeta($sev);
                    $wf = strtolower(trim((string) ($row['workflow_status'] ?? Reports::STATUS_NEW)));
                    if (!isset($statusChoices[$wf])) {
                        $wf = Reports::STATUS_NEW;
                    }
                    $msg = trim((string) ($row['message'] ?? ''));
                    $detail = trim((string) ($row['detail_text'] ?? ''));
                    $sessionLog = '';
                    $contextRaw = trim((string) ($row['context_json'] ?? ''));
                    if ($contextRaw !== '') {
                        $ctx = json_decode($contextRaw, true);
                        if (is_array($ctx) && !empty($ctx['session_log']) && is_string($ctx['session_log'])) {
                            $sessionLog = trim($ctx['session_log']);
                        }
                    }
                    $player = trim((string) ($row['player_name'] ?? ''));
                    $callsign = trim((string) ($row['callsign'] ?? ''));
                    $steam = trim((string) ($row['steam_uid'] ?? ''));
                    $account = $steam !== '' ? ($linkedAccounts[$steam] ?? null) : null;
                    $canSite = $account !== null && $account['user_id'] > 0 && $account['tenant_id'] > 0;
                    $canEmail = $account !== null && $account['email'] !== '';
                    $hits = (int) ($row['hit_count'] ?? 1);
                    $grav = Gravity::normalize((string) ($row['gravity'] ?? '')) ?? Gravity::NORMAL;
                    $isAuto = Replies::isAuto($row);
                    $playerLang = Replies::lang($account);
                    $mailDefault = $canEmail && Gravity::mailsPlayer($grav);
                    $replyText = trim((string) ($row['reply_text'] ?? ''));
                    $replyChannels = array_filter(explode(',', (string) ($row['reply_channels'] ?? '')));
                    ?>
                    <li class="amr-card amr-card--<?= $h($wf) ?>" id="rapport-<?= $id ?>">
                        <div class="amr-card__head">
                            <label class="amr-check amr-card__select">
                                <input type="checkbox" name="ids[]" value="<?= $id ?>" form="amr-bulk" data-amr-item aria-label="Cocher ce rapport">
                            </label>
                            <div class="amr-card__tags">
                                <span class="amr-grav-chip amr-grav-chip--<?= $h($grav) ?> amr-grav-chip--static" title="<?= $h(Gravity::HINTS[$grav]) ?>">
                                    <span class="amr-grav-chip__dot" aria-hidden="true"></span><?= $h(Gravity::LABELS[$grav]) ?>
                                </span>
                                <span class="pa-pill pa-pill--<?= $h($statusTone[$wf]) ?>"><?= $h($statusChoices[$wf]) ?></span>
                                <span class="pa-pill pa-pill--<?= $h($sevTone) ?>"><?= $h($sevLabel) ?></span>
                                <?php if ($isAuto): ?>
                                <span class="amr-card__channel" title="Envoyé tout seul par le mod, le joueur n’a rien signalé">Détection auto</span>
                                <?php endif; ?>
                                <span class="amr-card__channel"><?= $h((string) ($row['channel'] ?? '') ?: '—') ?></span>
                                <?php if ($repeats !== []): ?>
                                <a class="amr-card__repeats" href="#memes-<?= $id ?>" title="Autres rapports identiques, repliés en bas de la carte">+ <?= count($repeats) ?> identique(s)</a>
                                <?php endif; ?>
                                <?php if ($hits > 1): ?>
                                <span class="amr-card__hits" title="Envois regroupés (même problème, même joueur, moins de 30 min d’écart)">× <?= $hits ?></span>
                                <?php endif; ?>
                            </div>
                            <p class="amr-card__when">
                                <time datetime="<?= $h((string) ($row['last_seen_at'] ?? '')) ?>"><?= $h($fmtDate($row['last_seen_at'] ?? $row['created_at'] ?? '')) ?></time>
                            </p>
                        </div>

                        <p class="amr-card__msg"><?= $h($msg) ?></p>

                        <dl class="amr-card__facts">
                            <div>
                                <dt>Joueur</dt>
                                <dd>
                                    <?= $h($player !== '' ? $player : 'Inconnu') ?><?php if ($callsign !== '' && $callsign !== $player): ?> · <?= $h($callsign) ?><?php endif; ?>
                                    <?php if ($steam !== ''): ?><span class="amr-muted">Steam <?= $h($maskSteam($steam)) ?></span><?php endif; ?>
                                </dd>
                            </div>
                            <div>
                                <dt>Compte Athena</dt>
                                <dd>
                                    <?php if ($account !== null): ?>
                                        <span class="amr-dot amr-dot--ok" aria-hidden="true"></span>Lié<?= $canEmail ? ' · ' . $h($maskEmail($account['email'])) : ' · sans e-mail' ?>
                                        · <?= $playerLang === 'en' ? 'anglais' : 'français' ?>
                                    <?php else: ?>
                                        <span class="amr-dot" aria-hidden="true"></span>Aucun compte lié
                                    <?php endif; ?>
                                </dd>
                            </div>
                            <div>
                                <dt>Versions</dt>
                                <dd>
                                    Mod <?= $h(trim((string) ($row['mod_version'] ?? '')) ?: '—') ?>
                                    · Link <?= $h(trim((string) ($row['extension_version'] ?? '')) ?: '—') ?>
                                </dd>
                            </div>
                            <div>
                                <dt>Premier envoi</dt>
                                <dd><?= $h($fmtDate($row['created_at'] ?? '')) ?></dd>
                            </div>
                        </dl>

                        <?php if ($replyText !== ''): ?>
                        <div class="amr-reply-sent">
                            <p class="amr-reply-sent__head">
                                Réponse du <?= $h($fmtDate($row['replied_at'] ?? '')) ?>
                                <?php if (trim((string) ($row['replied_by'] ?? '')) !== ''): ?>par <?= $h((string) $row['replied_by']) ?><?php endif; ?>
                                · <?= $replyChannels === []
                                    ? 'gardée sur le rapport, joueur non prévenu'
                                    : 'envoyée ' . $h(implode(' et ', array_map(static fn (string $c): string => $c === 'site' ? 'sur Athena' : 'par e-mail', $replyChannels))) ?>
                            </p>
                            <p class="amr-reply-sent__text"><?= nl2br($h($replyText)) ?></p>
                        </div>
                        <?php endif; ?>

                        <div class="amr-card__actions">
                            <form method="post" action="<?= $h(url('admin/atak-mod-reports/status')) ?>" class="amr-card__status">
                                <?= $returnFields ?>
                                <input type="hidden" name="report_id" value="<?= $id ?>">
                                <?php foreach ($statusAction as $value => $label): ?>
                                    <?php if ($value === $wf) { continue; } ?>
                                    <button type="submit" name="workflow_status" value="<?= $h($value) ?>"
                                            class="pa-btn pa-btn--sm <?= $value === Reports::STATUS_FIXED ? 'pa-btn--mint' : 'pa-btn--line' ?>"><?= $h($label) ?></button>
                                <?php endforeach; ?>
                            </form>
                            <form method="post" action="<?= $h(url('admin/atak-mod-reports/gravity')) ?>" class="amr-card__gravity">
                                <?= $returnFields ?>
                                <input type="hidden" name="report_id" value="<?= $id ?>">
                                <label>
                                    <span class="amr-label">Gravité</span>
                                    <select name="gravity" data-amr-autosubmit>
                                        <?php foreach (Gravity::LABELS as $value => $label): ?>
                                        <option value="<?= $h($value) ?>" <?= $grav === $value ? 'selected' : '' ?>><?= $h($label) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </label>
                                <noscript><button type="submit" class="pa-btn pa-btn--sm pa-btn--line">OK</button></noscript>
                            </form>
                            <form method="post" action="<?= $h(url('admin/atak-mod-reports/delete')) ?>"
                                  onsubmit="return confirm('Supprimer définitivement ce rapport ?');">
                                <?= $returnFields ?>
                                <input type="hidden" name="report_id" value="<?= $id ?>">
                                <button type="submit" class="pa-btn pa-btn--sm amr-btn--danger-ghost">Supprimer</button>
                            </form>
                        </div>

                        <div class="amr-card__more">
                            <details class="amr-details amr-details--reply">
                                <summary><?= $replyText !== '' ? 'Répondre à nouveau au joueur' : 'Répondre au joueur' ?></summary>
                                <form method="post" action="<?= $h(url('admin/atak-mod-reports/reply')) ?>" class="amr-reply">
                                    <?= $returnFields ?>
                                    <input type="hidden" name="report_id" value="<?= $id ?>">
                                    <p class="amr-note">
                                        <?= $isAuto
                                            ? 'Erreur détectée automatiquement par le mod : le joueur n’a rien signalé, les messages types et l’e-mail le disent.'
                                            : 'Signalement écrit par le joueur depuis le jeu.' ?>
                                        <?php if ($account !== null): ?>
                                        Langue du joueur : <strong><?= $playerLang === 'en' ? 'anglais' : 'français' ?></strong>
                                        (profil Athena)<?= $playerLang === 'en' ? ', écrivez votre réponse en anglais' : '' ?>.
                                        <?php endif; ?>
                                    </p>
                                    <div class="amr-reply__templates" role="group" aria-label="Messages types">
                                        <?php foreach (Replies::templates($isAuto, $playerLang) as $tplKey => $tplText): ?>
                                        <button type="button" class="amr-chip" data-amr-template="<?= $h($tplText) ?>"><?= $h(Replies::TEMPLATE_LABELS[$tplKey] ?? $tplKey) ?></button>
                                        <?php endforeach; ?>
                                    </div>
                                    <label class="amr-reply__field">
                                        <span class="amr-label">Message au joueur</span>
                                        <textarea name="reply_message" rows="3" maxlength="4000" required placeholder="Votre réponse…"></textarea>
                                    </label>
                                    <?php if ($account === null): ?>
                                    <p class="amr-note">
                                        <?= $steam === '' ? 'Ce rapport n’a pas de Steam' : 'Ce Steam n’est lié à aucun compte Athena' ?> :
                                        le joueur ne peut pas être prévenu, la réponse sera seulement gardée sur le rapport.
                                    </p>
                                    <?php endif; ?>
                                    <div class="amr-reply__options">
                                        <label class="amr-check<?= $canSite ? '' : ' is-off' ?>">
                                            <input type="checkbox" name="via_site" value="1" <?= $canSite ? 'checked' : 'disabled' ?>>
                                            <span>Notification sur Athena</span>
                                        </label>
                                        <label class="amr-check<?= $canEmail ? '' : ' is-off' ?>">
                                            <input type="checkbox" name="via_email" value="1" <?= $canEmail ? ($mailDefault ? 'checked' : '') : 'disabled' ?>>
                                            <span>E-mail<?= $canEmail ? ' à ' . $h($maskEmail($account['email'])) : ' (adresse inconnue)' ?><?= $canEmail && !$mailDefault ? ' (décoché : gravité faible)' : '' ?></span>
                                        </label>
                                        <label class="amr-reply__then">
                                            <span class="amr-label">Puis</span>
                                            <select name="then_status">
                                                <option value="">Ne pas changer le suivi</option>
                                                <?php foreach ($statusChoices as $value => $label): ?>
                                                    <?php if ($value === $wf) { continue; } ?>
                                                <option value="<?= $h($value) ?>">Passer en « <?= $h($label) ?> »</option>
                                                <?php endforeach; ?>
                                            </select>
                                        </label>
                                    </div>
                                    <div class="amr-reply__submit">
                                        <button type="submit" class="pa-btn pa-btn--sm pa-btn--ink">Envoyer la réponse</button>
                                    </div>
                                    <p class="amr-hint">Pas encore de message dans le jeu : le téléphone ne relève pas les messages du site.</p>
                                </form>
                            </details>
                            <?php if ($detail !== ''): ?>
                            <?php
                            $parsed = Detail::parse($detail);
                            $hintCode = '';
                            $hint = '';
                            foreach ($parsed['facts'] as $fact) {
                                if ($fact['label'] === 'Code HTTP' && $hintCode === '') {
                                    $hintCode = 'HTTP ' . $fact['value'];
                                    $hint = $hint !== '' ? $hint : (string) ($fact['note'] ?? '');
                                } elseif (in_array($fact['label'], ['Message du serveur', 'Diagnostic Athena'], true)) {
                                    $hint = $fact['value'];
                                    break;
                                }
                            }
                            ?>
                            <details class="amr-details">
                                <summary>
                                    Détail technique
                                    <?php if ($hintCode !== ''): ?><span class="amr-details__code"><?= $h($hintCode) ?></span><?php endif; ?>
                                    <?php if ($hint !== ''): ?><span class="amr-details__hint"><?= $h(mb_strimwidth($hint, 0, 90, '…')) ?></span><?php endif; ?>
                                </summary>
                                <?php if ($parsed['readable']): ?>
                                <div class="amr-tech">
                                    <?php if ($parsed['facts'] !== []): ?>
                                    <dl class="amr-tech__facts">
                                        <?php foreach ($parsed['facts'] as $fact): ?>
                                        <div><dt><?= $h($fact['label']) ?></dt><dd><?= $h($fact['value']) ?><?php if (isset($fact['note'])): ?> <span class="amr-tech__note"><?= $h($fact['note']) ?></span><?php endif; ?></dd></div>
                                        <?php endforeach; ?>
                                    </dl>
                                    <?php endif; ?>
                                    <?php if ($parsed['text'] !== ''): ?>
                                    <p class="amr-tech__text"><?= nl2br($h($parsed['text'])) ?></p>
                                    <?php endif; ?>
                                    <?php if ($parsed['json'] !== null): ?>
                                    <p class="amr-tech__label">Réponse du serveur</p>
                                    <pre class="amr-pre amr-pre--json"><?= $h($parsed['json']) ?></pre>
                                    <?php endif; ?>
                                    <details class="amr-tech__raw">
                                        <summary>Texte brut reçu du mod<?php if ($parsed['repaired']): ?> <span class="amr-tech__note">accents réparés</span><?php endif; ?></summary>
                                        <pre class="amr-pre"><?= $h($parsed['raw']) ?></pre>
                                    </details>
                                </div>
                                <?php else: ?>
                                <pre class="amr-pre"><?= $h($parsed['raw']) ?></pre>
                                <?php endif; ?>
                            </details>
                            <?php endif; ?>
                            <?php if ($sessionLog !== ''): ?>
                            <details class="amr-details">
                                <summary>Journal de session</summary>
                                <pre class="amr-pre amr-pre--log"><?= $h($sessionLog) ?></pre>
                            </details>
                            <?php endif; ?>
                            <?php if (trim((string) ($row['arma_build'] ?? '')) !== '' || trim((string) ($row['source'] ?? '')) !== ''): ?>
                            <details class="amr-details">
                                <summary>Identifiants</summary>
                                <dl class="amr-kv">
                                    <div><dt>Rapport</dt><dd>#<?= $id ?></dd></div>
                                    <div><dt>Source</dt><dd><?= $h((string) ($row['source'] ?? '—')) ?></dd></div>
                                    <div><dt>Steam</dt><dd><?= $h($steam !== '' ? $steam : '—') ?></dd></div>
                                    <div><dt>Arma</dt><dd><?= $h(trim((string) ($row['arma_build'] ?? '')) ?: '—') ?></dd></div>
                                </dl>
                            </details>
                            <?php endif; ?>
                            <?php if ($repeats !== []): ?>
                            <details class="amr-details amr-repeats" id="memes-<?= $id ?>">
                                <summary>Mêmes rapports <span class="amr-tabs__n"><?= count($repeats) ?></span></summary>
                                <ul class="amr-repeats__list">
                                    <?php foreach ($repeats as $rep): ?>
                                    <?php
                                    $repId = (int) ($rep['id'] ?? 0);
                                    $repWho = implode(' · ', array_filter([trim((string) ($rep['player_name'] ?? '')), trim((string) ($rep['callsign'] ?? ''))])) ?: '—';
                                    $repHits = (int) ($rep['hit_count'] ?? 1);
                                    ?>
                                    <li class="amr-repeats__row" id="rapport-<?= $repId ?>">
                                        <label class="amr-check">
                                            <input type="checkbox" name="ids[]" value="<?= $repId ?>" form="amr-bulk" data-amr-item aria-label="Cocher ce rapport">
                                        </label>
                                        <time datetime="<?= $h((string) ($rep['last_seen_at'] ?? '')) ?>"><?= $h($fmtDate($rep['last_seen_at'] ?? $rep['created_at'] ?? '')) ?></time>
                                        <span class="amr-repeats__who"><?= $h($repWho) ?></span>
                                        <?php if ($repHits > 1): ?><span class="amr-card__hits">× <?= $repHits ?></span><?php endif; ?>
                                        <span class="amr-repeats__ver"><?= $h(trim((string) ($rep['mod_version'] ?? '')) !== '' ? 'Mod ' . trim((string) $rep['mod_version']) : '') ?></span>
                                        <span class="amr-repeats__id">#<?= $repId ?></span>
                                    </li>
                                    <?php endforeach; ?>
                                </ul>
                                <p class="amr-repeats__foot">
                                    <button type="button" class="pa-btn pa-btn--sm pa-btn--line" data-amr-check-repeats>Cocher ce rapport et les identiques</button>
                                    <span class="amr-hint">puis utilisez les actions groupées en haut de la liste.</span>
                                </p>
                            </details>
                            <?php endif; ?>
                        </div>
                    </li>
                <?php endforeach; ?>
                </ol>
            <?php endif; ?>
        </div>
    </div>
</div>
<script>
(function () {
    var root = document.querySelector('.amr');
    if (!root) { return; }
    var all = root.querySelector('[data-amr-all]');
    var counter = root.querySelector('[data-amr-count]');
    var items = function () { return Array.prototype.slice.call(root.querySelectorAll('[data-amr-item]')); };
    var refresh = function () {
        var list = items();
        var n = list.filter(function (c) { return c.checked; }).length;
        if (counter) { counter.textContent = String(n); }
        if (all) {
            all.checked = n > 0 && n === list.length;
            all.indeterminate = n > 0 && n < list.length;
        }
        root.querySelectorAll('[data-amr-needs-selection]').forEach(function (b) { b.disabled = n === 0; });
        list.forEach(function (c) {
            var card = c.closest('.amr-card');
            if (card) { card.classList.toggle('is-selected', c.checked); }
        });
    };
    if (all) {
        all.addEventListener('change', function () {
            items().forEach(function (c) { c.checked = all.checked; });
            refresh();
        });
    }
    root.addEventListener('change', function (e) {
        if (e.target && e.target.hasAttribute('data-amr-item')) { refresh(); }
        if (e.target && e.target.hasAttribute('data-amr-autosubmit') && e.target.form) { e.target.form.submit(); }
    });
    root.addEventListener('click', function (e) {
        var group = e.target && e.target.closest ? e.target.closest('[data-amr-check-repeats]') : null;
        if (group) {
            var card = group.closest('.amr-card');
            if (card) {
                card.querySelectorAll('[data-amr-item]').forEach(function (c) { c.checked = true; });
                refresh();
            }
            return;
        }
        var btn = e.target && e.target.closest ? e.target.closest('[data-amr-template]') : null;
        if (!btn) { return; }
        var form = btn.closest('form');
        var area = form ? form.querySelector('textarea[name="reply_message"]') : null;
        if (area) {
            area.value = btn.getAttribute('data-amr-template') || '';
            area.focus();
        }
    });
    refresh();
})();
</script>
