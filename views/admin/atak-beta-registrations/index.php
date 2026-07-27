<?php
/** @var list<array<string, mixed>> $rows */
/** @var int $total */
$rows = is_array($rows ?? null) ? $rows : [];
$total = (int) ($total ?? count($rows));

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

$maskSteam = static function (mixed $raw): string {
    $s = trim((string) $raw);
    if ($s === '') {
        return '—';
    }
    if (strlen($s) <= 8) {
        return $s;
    }

    return '…' . substr($s, -8);
};

$maskIp = static function (mixed $raw): string {
    $ip = trim((string) $raw);
    if ($ip === '') {
        return '—';
    }
    if (str_contains($ip, ':')) {
        $parts = explode(':', $ip);

        return '…' . substr((string) (end($parts) ?: ''), -6);
    }
    $octets = explode('.', $ip);
    if (count($octets) === 4) {
        return $octets[0] . '.' . $octets[1] . '.·.' . $octets[3];
    }

    return '…' . substr($ip, -4);
};

$ackedCount = 0;
$restrictedCount = 0;
foreach ($rows as $countRow) {
    if (trim((string) ($countRow['acknowledged_at'] ?? '')) !== '') {
        $ackedCount++;
    }
    if (!empty($countRow['steam_restricted'])) {
        $restrictedCount++;
    }
}

$csrfToken = \App\Core\Csrf::token();
$baseBeta = url('admin/atak-beta');
$blocksUrl = url('admin/atak-mod-blocks');
?>
<div class="bo-atak-beta">
    <header class="bo-atak-beta__hero">
        <p class="bo-atak-beta__eyebrow">Tactique · Mod Arma</p>
        <h1>Accès anticipé Overwatch</h1>
        <p class="bo-atak-beta__lead">
            Joueurs ayant lancé le pack en accès anticipé et confirmé la note d’accès (menu principal Arma).
            Le badge « Accepté » confirme la note côté jeu ; pour couper l’accès, créez une restriction Steam.
        </p>
        <nav class="bo-atak-beta__nav" aria-label="Liens associés">
            <a href="<?= htmlspecialchars(url('admin/atak-mod'), ENT_QUOTES, 'UTF-8') ?>">Pack Overwatch</a>
            <span class="bo-atak-beta__nav-sep" aria-hidden="true">·</span>
            <a href="<?= htmlspecialchars(url('admin/atak-mod-reports'), ENT_QUOTES, 'UTF-8') ?>">Rapports erreurs</a>
            <span class="bo-atak-beta__nav-sep" aria-hidden="true">·</span>
            <a href="<?= htmlspecialchars($blocksUrl, ENT_QUOTES, 'UTF-8') ?>">Restrictions d’accès</a>
            <span class="bo-atak-beta__nav-sep" aria-hidden="true">·</span>
            <a href="<?= htmlspecialchars(url('admin/atak-config'), ENT_QUOTES, 'UTF-8') ?>">Configuration ATAK</a>
        </nav>
    </header>

    <div class="bo-atak-beta__deck">
        <?php $flashError = \App\Core\Session::getFlash('error'); $flashSuccess = \App\Core\Session::getFlash('success'); ?>
        <?php if ($flashError): ?>
            <div class="bo-atak-beta__flash bo-atak-beta__flash--err" role="alert"><?= htmlspecialchars($flashError, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>
        <?php if ($flashSuccess): ?>
            <div class="bo-atak-beta__flash bo-atak-beta__flash--ok" role="status"><?= htmlspecialchars($flashSuccess, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>

        <div class="bo-atak-beta__kpis" aria-label="Synthèse">
            <div class="bo-atak-beta__kpi">
                <span class="bo-atak-beta__kpi-label">Total</span>
                <span class="bo-atak-beta__kpi-value"><?= (int) $total ?></span>
            </div>
            <div class="bo-atak-beta__kpi">
                <span class="bo-atak-beta__kpi-label">Affichées</span>
                <span class="bo-atak-beta__kpi-value"><?= count($rows) ?></span>
            </div>
            <div class="bo-atak-beta__kpi">
                <span class="bo-atak-beta__kpi-label">Acceptées</span>
                <span class="bo-atak-beta__kpi-value"><?= (int) $ackedCount ?></span>
            </div>
            <div class="bo-atak-beta__kpi">
                <span class="bo-atak-beta__kpi-label">Restreintes</span>
                <span class="bo-atak-beta__kpi-value"><?= (int) $restrictedCount ?></span>
            </div>
        </div>

        <section class="bo-atak-beta__panel" aria-labelledby="atak-beta-heading">
            <div class="bo-atak-beta__panel-head">
                <div>
                    <h2 id="atak-beta-heading">Inscriptions récentes</h2>
                    <p>Journal des lancements Overwatch remontés vers Athena.</p>
                </div>
            </div>

            <?php if ($rows === []): ?>
                <p class="bo-atak-beta__empty">
                    Aucune inscription pour le moment. Elles apparaissent dès qu’un joueur lance le pack Overwatch,
                    confirme la note d’accès, et que la liaison avec Athena aboutit.
                </p>
            <?php else: ?>
                <form method="post" action="<?= htmlspecialchars($baseBeta . '/bulk', ENT_QUOTES, 'UTF-8') ?>" id="atak-beta-bulk-form"
                      data-confirm-clear="Retirer le marquage « Accepté » pour les inscriptions sélectionnées ? Cela ne coupe pas l’accès au mod."
                      data-confirm-restrict="Restreindre l’accès Steam pour les inscriptions sélectionnées ? Le pack Overwatch refusera ces joueurs pour votre communauté."
                      data-confirm-delete="Supprimer définitivement les inscriptions sélectionnées du journal ? Cette action est irréversible.">
                    <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">

                    <div class="bo-atak-beta__bulk">
                        <label for="atak-beta-bulk-action">Action groupée</label>
                        <select name="bulk_action" id="atak-beta-bulk-action" required>
                            <option value="">Choisir…</option>
                            <option value="clear_acknowledgement">Effacer « Accepté »</option>
                            <option value="restrict_steam">Restreindre Steam</option>
                            <option value="delete">Supprimer du journal</option>
                        </select>
                        <button type="submit" class="bo-atak-beta__bulk-btn">Appliquer</button>
                        <p class="bo-atak-beta__bulk-hint">
                            Cochez les lignes, choisissez l’action, confirmez.
                            Gestion des restrictions : <a href="<?= htmlspecialchars($blocksUrl, ENT_QUOTES, 'UTF-8') ?>">Restrictions d’accès</a>.
                        </p>
                    </div>

                    <div class="bo-atak-beta__table-wrap">
                        <table class="bo-atak-beta__table">
                            <thead>
                                <tr>
                                    <th class="bo-atak-beta__th-check" scope="col">
                                        <label class="sr-only" for="atak-beta-select-all">Tout sélectionner</label>
                                        <input type="checkbox" id="atak-beta-select-all"
                                               class="rounded border-slate-300"
                                               title="Tout sélectionner"
                                               aria-label="Tout sélectionner">
                                    </th>
                                    <th scope="col">Joueur</th>
                                    <th scope="col">Steam</th>
                                    <th scope="col">Réseau</th>
                                    <th scope="col">Versions</th>
                                    <th scope="col">Activité</th>
                                    <th scope="col">Passages</th>
                                    <th class="bo-atak-beta__th-actions" scope="col">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($rows as $r): ?>
                                    <?php
                                    $id = (int) ($r['id'] ?? 0);
                                    $acked = trim((string) ($r['acknowledged_at'] ?? '')) !== '';
                                    $playerName = trim((string) ($r['player_name'] ?? ''));
                                    $modVer = trim((string) ($r['mod_version'] ?? ''));
                                    $armaBuild = trim((string) ($r['arma_build'] ?? ''));
                                    $armaBranch = trim((string) ($r['arma_branch'] ?? ''));
                                    $steamRaw = trim((string) ($r['steam_uid'] ?? ''));
                                    $ipRaw = trim((string) ($r['client_ip'] ?? ''));
                                    $hasSteam = $steamRaw !== '';
                                    $restricted = !empty($r['steam_restricted']);
                                    $buildLabel = $armaBuild !== '' ? $armaBuild : '—';
                                    if ($armaBranch !== '') {
                                        $buildLabel .= ' · ' . $armaBranch;
                                    }
                                    $displayName = $playerName !== '' ? $playerName : 'cette inscription';
                                    $confirmClear = 'Retirer le marquage « Accepté » pour « ' . $displayName . ' » ? Cela ne coupe pas l’accès au mod.';
                                    $confirmRestrict = 'Restreindre l’accès Steam de « ' . $displayName . ' » ? Le pack Overwatch refusera ce joueur pour votre communauté.';
                                    $confirmDelete = 'Supprimer définitivement l’inscription de « ' . $displayName . ' » du journal ? Cette action est irréversible.';
                                    $steamMasked = $maskSteam($steamRaw);
                                    $ipMasked = $maskIp($ipRaw);
                                    ?>
                                    <tr>
                                        <td class="bo-atak-beta__td-check">
                                            <input type="checkbox"
                                                   name="registration_ids[]"
                                                   value="<?= $id ?>"
                                                   class="atak-beta-row-check rounded border-slate-300"
                                                   aria-label="Sélectionner <?= htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8') ?>">
                                        </td>
                                        <td>
                                            <div class="bo-atak-beta__player">
                                                <span class="bo-atak-beta__player-name"><?= htmlspecialchars($playerName !== '' ? $playerName : 'Sans nom', ENT_QUOTES, 'UTF-8') ?></span>
                                                <div class="bo-atak-beta__badges">
                                                    <?php if ($acked): ?>
                                                        <span class="bo-atak-beta__badge bo-atak-beta__badge--ok">Accepté</span>
                                                    <?php else: ?>
                                                        <span class="bo-atak-beta__badge bo-atak-beta__badge--warn">En attente</span>
                                                    <?php endif; ?>
                                                    <?php if ($restricted): ?>
                                                        <span class="bo-atak-beta__badge bo-atak-beta__badge--danger">Restreint</span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="bo-atak-beta__mono" title="<?= htmlspecialchars($hasSteam ? 'Identifiant Steam (partiellement masqué)' : 'Identifiant Steam inconnu', ENT_QUOTES, 'UTF-8') ?>">
                                                <?= htmlspecialchars($steamMasked, ENT_QUOTES, 'UTF-8') ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="bo-atak-beta__mono" title="Adresse réseau (partiellement masquée)">
                                                <?= htmlspecialchars($ipMasked, ENT_QUOTES, 'UTF-8') ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="bo-atak-beta__meta">
                                                <span class="bo-atak-beta__meta-main"><?= htmlspecialchars($modVer !== '' ? 'OW ' . $modVer : 'OW —', ENT_QUOTES, 'UTF-8') ?></span>
                                                <span class="bo-atak-beta__meta-sub"><?= htmlspecialchars('Arma ' . $buildLabel, ENT_QUOTES, 'UTF-8') ?></span>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="bo-atak-beta__meta">
                                                <span class="bo-atak-beta__meta-main"><?= htmlspecialchars($fmtDate($r['last_seen_at'] ?? null), ENT_QUOTES, 'UTF-8') ?></span>
                                                <span class="bo-atak-beta__meta-sub">1ʳᵉ fois <?= htmlspecialchars($fmtDate($r['first_seen_at'] ?? null), ENT_QUOTES, 'UTF-8') ?></span>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="bo-atak-beta__hits"><?= (int) ($r['hit_count'] ?? 1) ?></span>
                                        </td>
                                        <td class="bo-atak-beta__td-actions">
                                            <details class="bo-atak-beta__menu">
                                                <summary>Actions</summary>
                                                <div class="bo-atak-beta__menu-panel">
                                                    <?php if ($acked): ?>
                                                        <button type="submit"
                                                                form="atak-beta-action-clear-<?= $id ?>"
                                                                class="bo-atak-beta__warn"
                                                                onclick="return confirm(<?= htmlspecialchars(json_encode($confirmClear, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8') ?>);">
                                                            Effacer « Accepté »
                                                        </button>
                                                    <?php endif; ?>
                                                    <?php if ($hasSteam && !$restricted): ?>
                                                        <button type="submit"
                                                                form="atak-beta-action-restrict-<?= $id ?>"
                                                                class="bo-atak-beta__danger"
                                                                onclick="return confirm(<?= htmlspecialchars(json_encode($confirmRestrict, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8') ?>);">
                                                            Restreindre Steam
                                                        </button>
                                                    <?php elseif ($restricted): ?>
                                                        <a href="<?= htmlspecialchars($blocksUrl, ENT_QUOTES, 'UTF-8') ?>">Voir la restriction</a>
                                                    <?php else: ?>
                                                        <span class="bo-atak-beta__menu-muted">Steam inconnu</span>
                                                    <?php endif; ?>
                                                    <button type="submit"
                                                            form="atak-beta-action-delete-<?= $id ?>"
                                                            class="bo-atak-beta__danger"
                                                            onclick="return confirm(<?= htmlspecialchars(json_encode($confirmDelete, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8') ?>);">
                                                        Supprimer du journal
                                                    </button>
                                                    <a href="<?= htmlspecialchars($blocksUrl, ENT_QUOTES, 'UTF-8') ?>">Gérer les restrictions</a>
                                                </div>
                                            </details>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </form>

                <?php foreach ($rows as $r): ?>
                    <?php
                    $id = (int) ($r['id'] ?? 0);
                    $acked = trim((string) ($r['acknowledged_at'] ?? '')) !== '';
                    $steamRaw = trim((string) ($r['steam_uid'] ?? ''));
                    $hasSteam = $steamRaw !== '';
                    $restricted = !empty($r['steam_restricted']);
                    ?>
                    <?php if ($acked): ?>
                        <form id="atak-beta-action-clear-<?= $id ?>" method="post" action="<?= htmlspecialchars($baseBeta . '/clear-acknowledgement', ENT_QUOTES, 'UTF-8') ?>" class="hidden">
                            <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                            <input type="hidden" name="registration_id" value="<?= $id ?>">
                        </form>
                    <?php endif; ?>
                    <?php if ($hasSteam && !$restricted): ?>
                        <form id="atak-beta-action-restrict-<?= $id ?>" method="post" action="<?= htmlspecialchars($baseBeta . '/restrict-steam', ENT_QUOTES, 'UTF-8') ?>" class="hidden">
                            <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                            <input type="hidden" name="registration_id" value="<?= $id ?>">
                        </form>
                    <?php endif; ?>
                    <form id="atak-beta-action-delete-<?= $id ?>" method="post" action="<?= htmlspecialchars($baseBeta . '/delete', ENT_QUOTES, 'UTF-8') ?>" class="hidden">
                        <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                        <input type="hidden" name="registration_id" value="<?= $id ?>">
                    </form>
                <?php endforeach; ?>

                <p class="bo-atak-beta__foot">
                    Les repères Steam et réseau sont partiellement masqués pour limiter la diffusion d’identifiants complets dans l’interface.
                </p>

                <script>
                (function () {
                    var form = document.getElementById('atak-beta-bulk-form');
                    if (!form) return;
                    var selectAll = document.getElementById('atak-beta-select-all');
                    var checks = form.querySelectorAll('.atak-beta-row-check');
                    var action = document.getElementById('atak-beta-bulk-action');
                    if (selectAll) {
                        selectAll.addEventListener('change', function () {
                            checks.forEach(function (c) { c.checked = selectAll.checked; });
                        });
                    }
                    document.addEventListener('click', function (e) {
                        form.querySelectorAll('.bo-atak-beta__menu[open]').forEach(function (d) {
                            if (!d.contains(e.target)) d.removeAttribute('open');
                        });
                    });
                    form.addEventListener('submit', function (e) {
                        var selected = form.querySelectorAll('.atak-beta-row-check:checked');
                        if (!selected.length) {
                            e.preventDefault();
                            alert('Sélectionnez au moins une inscription.');
                            return;
                        }
                        if (!action || !action.value) {
                            e.preventDefault();
                            alert('Choisissez une action groupée.');
                            return;
                        }
                        var msg = '';
                        if (action.value === 'clear_acknowledgement') msg = form.getAttribute('data-confirm-clear') || '';
                        else if (action.value === 'restrict_steam') msg = form.getAttribute('data-confirm-restrict') || '';
                        else if (action.value === 'delete') msg = form.getAttribute('data-confirm-delete') || '';
                        if (msg && !confirm(msg)) {
                            e.preventDefault();
                        }
                    });
                })();
                </script>
            <?php endif; ?>
        </section>
    </div>
</div>
