<?php
/** @var list<array<string, mixed>> $rows */
/** @var int $total */
/** @var int $totalAll */
/** @var string $severityFilter */
/** @var string $statusFilter */
/** @var string $searchFilter */
/** @var array<string, int> $statusCounts */
/** @var array<string, array{user_id:int, tenant_id:int, email:string, name:string}> $linkedAccounts */
/** @var string $defaultFixedMessage */

use App\Repositories\AtakModReportRepository as Reports;

$rows = is_array($rows ?? null) ? $rows : [];
$total = (int) ($total ?? count($rows));
$totalAll = (int) ($totalAll ?? $total);
$severityFilter = trim((string) ($severityFilter ?? ''));
$statusFilter = trim((string) ($statusFilter ?? ''));
$searchFilter = trim((string) ($searchFilter ?? ''));
$statusCounts = is_array($statusCounts ?? null) ? $statusCounts : [];
$linkedAccounts = is_array($linkedAccounts ?? null) ? $linkedAccounts : [];
$defaultFixedMessage = (string) ($defaultFixedMessage ?? '');

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

$listUrl = static function (array $overrides = []) use ($base, $severityFilter, $statusFilter, $searchFilter): string {
    $params = array_merge(['severity' => $severityFilter, 'status' => $statusFilter, 'q' => $searchFilter], $overrides);
    $params = array_filter($params, static fn ($v): bool => trim((string) $v) !== '');

    return $params === [] ? $base : $base . '?' . http_build_query($params);
};

$returnFields = '<input type="hidden" name="_csrf" value="' . $h($csrfToken) . '">'
    . '<input type="hidden" name="return_severity" value="' . $h($severityFilter) . '">'
    . '<input type="hidden" name="return_status" value="' . $h($statusFilter) . '">'
    . '<input type="hidden" name="return_q" value="' . $h($searchFilter) . '">';

$hasFilters = $severityFilter !== '' || $statusFilter !== '' || $searchFilter !== '';
$replyTemplates = [
    'Corrigé' => $defaultFixedMessage,
    'En cours' => 'Merci pour votre signalement. Nous avons reproduit le problème et travaillons sur une correction.',
    'Besoin d’infos' => 'Merci pour votre signalement. Pouvez-vous nous en dire plus (ce que vous faisiez, sur quel serveur) ? Répondez sur le Discord COMSPEC, salon support.',
    'Pas un bug' => 'Merci pour votre signalement. Après vérification, ce comportement est normal dans le mod : rien à corriger de notre côté.',
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
                    </div>
                    <label class="amr-check amr-bulk__notify">
                        <input type="checkbox" name="notify" value="1" checked>
                        <span>Prévenir les joueurs quand je marque corrigé</span>
                    </label>
                    <input type="hidden" name="notify_message" value="<?= $h($defaultFixedMessage) ?>">
                </form>

                <form method="post" action="<?= $h(url('admin/atak-mod-reports/bulk')) ?>" class="amr-bulk__row amr-bulk__row--all">
                    <?= $returnFields ?>
                    <input type="hidden" name="scope" value="filter">
                    <input type="hidden" name="notify_message" value="<?= $h($defaultFixedMessage) ?>">
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

                <ol class="amr-list">
                <?php foreach ($rows as $row): ?>
                    <?php
                    $id = (int) ($row['id'] ?? 0);
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
                    $replyText = trim((string) ($row['reply_text'] ?? ''));
                    $replyChannels = array_filter(explode(',', (string) ($row['reply_channels'] ?? '')));
                    ?>
                    <li class="amr-card amr-card--<?= $h($wf) ?>" id="rapport-<?= $id ?>">
                        <div class="amr-card__head">
                            <label class="amr-check amr-card__select">
                                <input type="checkbox" name="ids[]" value="<?= $id ?>" form="amr-bulk" data-amr-item aria-label="Cocher ce rapport">
                            </label>
                            <div class="amr-card__tags">
                                <span class="pa-pill pa-pill--<?= $h($statusTone[$wf]) ?>"><?= $h($statusChoices[$wf]) ?></span>
                                <span class="pa-pill pa-pill--<?= $h($sevTone) ?>"><?= $h($sevLabel) ?></span>
                                <span class="amr-card__channel"><?= $h((string) ($row['channel'] ?? '') ?: '—') ?></span>
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
                                    <div class="amr-reply__templates" role="group" aria-label="Messages types">
                                        <?php foreach ($replyTemplates as $tplLabel => $tplText): ?>
                                        <button type="button" class="amr-chip" data-amr-template="<?= $h($tplText) ?>"><?= $h($tplLabel) ?></button>
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
                                            <input type="checkbox" name="via_email" value="1" <?= $canEmail ? 'checked' : 'disabled' ?>>
                                            <span>E-mail<?= $canEmail ? ' à ' . $h($maskEmail($account['email'])) : ' (adresse inconnue)' ?></span>
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
                            <details class="amr-details">
                                <summary>Détail technique</summary>
                                <pre class="amr-pre"><?= $h($detail) ?></pre>
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
    });
    root.addEventListener('click', function (e) {
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
