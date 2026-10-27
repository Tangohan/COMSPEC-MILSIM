<?php
declare(strict_types=1);

use App\Core\Csrf;
use App\Services\Ops\OpsHealthService;
use App\Services\Platform\PlatformStorageService;

$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$ok = \App\Core\Session::getFlash('success');
$err = \App\Core\Session::getFlash('error');
$base = url('admin/system/exploitation');
$tab = (string) ($opsTab ?? 'etat');
$csrf = Csrf::field();
$toneClass = static fn (string $tone): string => match ($tone) {
    'ok' => 'mint', 'warn' => 'amber', 'bad' => 'rose', default => 'slate',
};
$when = static function (?string $iso): string {
    $t = $iso ? strtotime($iso . (preg_match('/[zZ]|[+-]\d\d:?\d\d$/', (string) $iso) ? '' : ' UTC')) : false;

    return $t ? date('d/m/Y H:i', $t) : '—';
};
$ago = static function (?string $iso): string {
    $t = $iso ? strtotime($iso . (preg_match('/[zZ]|[+-]\d\d:?\d\d$/', (string) $iso) ? '' : ' UTC')) : false;

    return $t ? OpsHealthService::formatAge(time() - $t) : 'jamais';
};
$tabs = [
    'etat' => 'État du site',
    'migrations' => 'Migrations',
    'sauvegardes' => 'Sauvegardes',
    'donnees' => 'Comptes et données',
];
$summary = is_array($opsSummary ?? null) ? $opsSummary : null;
?>
<div class="pa ops">
    <div class="pa__frame">
        <header class="pa-hero">
            <p class="pa-crumb">
                <a href="<?= $h(url('admin')) ?>">Administration du site</a>
                <span aria-hidden="true"> / </span>
                Console d’exploitation
            </p>
            <h1 class="pa-hero__title">Console d’exploitation</h1>
            <p class="pa-hero__lead">
                L’état du site, les migrations de la base, les sauvegardes et les suppressions de comptes, au même endroit.
                Réservé à l’administration du site, après une double vérification.
            </p>
            <?php if (empty($opsLocked)): ?>
                <div class="pa-hero__actions">
                    <span class="ops-session" title="La console se verrouille seule après 30 minutes.">
                        <span class="ops-dot ops-dot--ok" aria-hidden="true"></span>
                        Ouverte encore <?= (int) ceil(((int) ($opsRemaining ?? 0)) / 60) ?> min
                    </span>
                    <form method="post" action="<?= $h($base . '/verrouiller') ?>">
                        <?= $csrf ?>
                        <button class="pa-btn pa-btn--ghost" type="submit">Verrouiller</button>
                    </form>
                </div>
            <?php endif; ?>
        </header>

        <div class="pa-panel">
            <?php if ($ok): ?><p class="pa-flash pa-flash--ok" role="status"><?= $h($ok) ?></p><?php endif; ?>
            <?php if ($err): ?><p class="pa-flash pa-flash--err" role="alert"><?= $h($err) ?></p><?php endif; ?>

<?php if (!empty($opsLocked)): ?>
            <section class="pa-card ops-lock" aria-labelledby="ops-lock-title">
                <span class="ops-lock__icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="5" y="11" width="14" height="10" rx="2"/><path d="M8 11V8a4 4 0 0 1 8 0v3"/></svg>
                </span>
                <p class="pa-card__kicker">Double vérification</p>
                <h2 id="ops-lock-title" class="pa-card__title">Confirmez que c’est bien vous</h2>
                <?php if (($opsMethod ?? 'email') === 'totp'): ?>
                    <p class="pa-card__help">Saisissez le code à six chiffres de votre application d’authentification.</p>
                <?php elseif (!empty($opsPendingCode)): ?>
                    <p class="pa-card__help">Un code a été envoyé à <strong><?= $h($opsPendingCode['email_masked']) ?></strong>. Il reste valable jusqu’à <?= $h(date('H:i', (int) $opsPendingCode['expires_at'])) ?>.</p>
                <?php else: ?>
                    <p class="pa-card__help">Nous envoyons un code à six chiffres à <strong><?= $h($opsEmailMasked ?? '') ?></strong>. Pour ne plus attendre l’e-mail, activez une application d’authentification dans <a href="<?= $h(url('account/security')) ?>">votre compte</a>.</p>
                <?php endif; ?>

                <?php if (($opsMethod ?? 'email') === 'totp' || !empty($opsPendingCode)): ?>
                    <form method="post" action="<?= $h($base . '/verifier') ?>" class="ops-lock__form" autocomplete="off">
                        <?= $csrf ?>
                        <label class="ops-sr" for="ops-code">Code</label>
                        <input id="ops-code" class="ops-code" name="code" inputmode="numeric" pattern="[0-9 ]{6,7}" maxlength="7" placeholder="000000" required autofocus autocomplete="one-time-code">
                        <button class="pa-btn pa-btn--solid" type="submit">Ouvrir la console</button>
                    </form>
                <?php endif; ?>
                <?php if (($opsMethod ?? 'email') !== 'totp'): ?>
                    <form method="post" action="<?= $h($base . '/code') ?>" class="ops-lock__resend">
                        <?= $csrf ?>
                        <button class="pa-btn <?= !empty($opsPendingCode) ? 'pa-btn--ghost pa-btn--sm' : 'pa-btn--solid' ?>" type="submit"><?= !empty($opsPendingCode) ? 'Renvoyer un code' : 'Recevoir le code par e-mail' ?></button>
                    </form>
                <?php endif; ?>
                <p class="ops-lock__foot">La console reste ouverte 30 minutes, pour ce navigateur seulement. Chaque action est inscrite au journal d’audit.</p>
            </section>
<?php else: ?>
            <nav class="ops-tabs" aria-label="Sections de la console">
                <?php foreach ($tabs as $key => $label): ?>
                    <a class="ops-tab<?= $tab === $key ? ' is-active' : '' ?>" href="<?= $h($base . '?onglet=' . $key) ?>"<?= $tab === $key ? ' aria-current="page"' : '' ?>>
                        <?= $h($label) ?>
                        <?php if ($key === 'etat' && $summary !== null && ($summary['bad'] + $summary['warn']) > 0): ?>
                            <span class="pa-pill pa-pill--<?= $summary['bad'] > 0 ? 'rose' : 'amber' ?>"><?= (int) ($summary['bad'] + $summary['warn']) ?></span>
                        <?php endif; ?>
                    </a>
                <?php endforeach; ?>
            </nav>

<?php if ($tab === 'etat'): ?>
            <?php $sections = is_array($opsHealth ?? null) ? $opsHealth : []; ?>
            <div class="ops-verdict ops-verdict--<?= $h($summary['tone'] ?? 'ok') ?>">
                <span class="ops-verdict__mark" aria-hidden="true"></span>
                <div>
                    <strong><?= ($summary['tone'] ?? 'ok') === 'ok' ? 'Tout fonctionne' : (($summary['tone'] ?? '') === 'bad' ? 'Un point bloque' : 'Quelques points à regarder') ?></strong>
                    <span><?= (int) ($summary['ok'] ?? 0) ?> contrôle(s) au vert · <?= (int) ($summary['warn'] ?? 0) ?> à surveiller · <?= (int) ($summary['bad'] ?? 0) ?> en échec · relevé à <?= $h(date('H:i')) ?></span>
                </div>
                <a class="pa-btn pa-btn--ghost pa-btn--sm" href="<?= $h($base . '?onglet=etat') ?>">Actualiser</a>
            </div>
            <div class="ops-grid">
                <?php foreach ($sections as $section): ?>
                    <section class="pa-card ops-health" aria-label="<?= $h($section['title']) ?>">
                        <h2 class="ops-health__title"><?= $h($section['title']) ?></h2>
                        <ul class="ops-checks">
                            <?php foreach ($section['items'] as $item): ?>
                                <li class="ops-check">
                                    <span class="ops-dot ops-dot--<?= $h($item['tone'] ?? 'info') ?>" aria-hidden="true"></span>
                                    <span class="ops-check__label"><?= $h($item['label']) ?></span>
                                    <span class="ops-check__value"><?= $h($item['value']) ?></span>
                                    <?php if (!empty($item['hint'])): ?><span class="ops-check__hint"><?= $h($item['hint']) ?></span><?php endif; ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </section>
                <?php endforeach; ?>
            </div>
            <p class="ops-links">
                Aller plus loin :
                <a href="<?= $h(url('admin/system/cron')) ?>">Tâches automatiques</a> ·
                <a href="<?= $h(url('admin/system/storage')) ?>">Espace disque</a> ·
                <a href="<?= $h(url('admin/maintenance')) ?>">Maintenance</a> ·
                <a href="<?= $h(url('admin/system/updates')) ?>">Mises à jour</a> ·
                <a href="<?= $h(url('admin/audit')) ?>">Journal d’audit</a>
            </p>

<?php elseif ($tab === 'migrations'): ?>
            <?php
            $m = is_array($opsMigrations ?? null) ? $opsMigrations : [];
            $sql = is_array($m['sql'] ?? null) ? $m['sql'] : null;
            $steps = is_array($m['steps'] ?? null) ? $m['steps'] : [];
            $results = is_array($m['results'] ?? null) ? $m['results'] : [];
            $last = is_array($m['last_run'] ?? null) ? $m['last_run'] : null;
            $stepErrors = count(array_filter($results, static fn ($r) => ($r['status'] ?? '') === 'error'));
            $failedSql = $sql['failed'] ?? [];
            $pendingSql = $sql['pending'] ?? [];
            ?>
            <ul class="pa-kpi-strip">
                <li class="pa-kpi<?= $last !== null && empty($last['ok']) ? ' pa-kpi--alert' : '' ?>">
                    <span class="pa-kpi__label">Dernier passage</span>
                    <span class="pa-kpi__value ops-kpi-text"><?= $h($last['summary_line'] ?? 'Jamais lancé') ?></span>
                    <span class="pa-kpi__hint"><?= $h($last !== null ? $ago((string) ($last['finished_at'] ?? '')) . ' · ' . (($last['mode'] ?? '') === 'cli' ? 'ligne de commande' : 'web') : '') ?></span>
                </li>
                <li class="pa-kpi<?= $stepErrors > 0 ? ' pa-kpi--alert' : '' ?>">
                    <span class="pa-kpi__label">Étapes en erreur</span>
                    <span class="pa-kpi__value"><?= $stepErrors ?></span>
                    <span class="pa-kpi__hint">sur <?= count($steps) ?> étapes</span>
                </li>
                <li class="pa-kpi<?= $failedSql !== [] ? ' pa-kpi--alert' : '' ?>">
                    <span class="pa-kpi__label">Fichiers SQL en échec</span>
                    <span class="pa-kpi__value"><?= count($failedSql) ?></span>
                    <span class="pa-kpi__hint"><?= (int) ($sql['applied'] ?? 0) ?> appliqués sur <?= (int) ($sql['total'] ?? 0) ?></span>
                </li>
                <li class="pa-kpi<?= $pendingSql !== [] ? ' pa-kpi--warn' : '' ?>">
                    <span class="pa-kpi__label">En attente</span>
                    <span class="pa-kpi__value"><?= count($pendingSql) ?></span>
                    <span class="pa-kpi__hint">appliqués au prochain passage</span>
                </li>
            </ul>

            <section class="pa-card ops-run">
                <div>
                    <p class="pa-card__kicker">Lancer</p>
                    <h2 class="pa-card__title">Passer toutes les migrations</h2>
                    <p class="pa-card__help">Schéma, fichiers SQL en attente ou en échec, référentiels et maintenance, dans l’ordre. Compter quelques minutes ; la progression s’affiche en direct.</p>
                </div>
                <form method="post" action="<?= $h($base . '/migrations/lancer') ?>" class="ops-run__form" data-ops-confirm="Lancer toutes les migrations maintenant ?">
                    <?= $csrf ?>
                    <input type="hidden" name="mode" value="run">
                    <label class="ops-check-input"><input type="checkbox" name="backup" value="1" checked> Sauvegarder la base juste avant</label>
                    <button class="pa-btn pa-btn--solid" type="submit">Lancer les migrations</button>
                </form>
            </section>

            <?php if ($failedSql !== [] || $pendingSql !== []): ?>
                <section class="pa-card">
                    <h2 class="ops-health__title">Fichiers SQL à traiter</h2>
                    <div class="pa-table-wrap">
                        <table class="pa-table ops-table">
                            <thead><tr><th>Fichier</th><th>État</th><th>Détail</th><th class="ops-right">Action</th></tr></thead>
                            <tbody>
                            <?php foreach (array_slice($failedSql, 0, 30) as $f): ?>
                                <tr>
                                    <td><code><?= $h($f['name']) ?></code></td>
                                    <td><span class="pa-pill pa-pill--rose">En échec</span></td>
                                    <td class="ops-msg"><?= $h($f['error'] ?? '') ?></td>
                                    <td class="ops-right">
                                        <form method="post" action="<?= $h($base . '/migrations/lancer') ?>" data-ops-confirm="Rejouer <?= $h($f['name']) ?> pendant un passage complet ?">
                                            <?= $csrf ?>
                                            <input type="hidden" name="mode" value="replay">
                                            <input type="hidden" name="file" value="<?= $h($f['name']) ?>">
                                            <input type="hidden" name="backup" value="1">
                                            <button class="pa-btn pa-btn--line pa-btn--sm" type="submit">Rejouer</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <?php foreach (array_slice($pendingSql, 0, 30) as $p): ?>
                                <tr>
                                    <td><code><?= $h($p) ?></code></td>
                                    <td><span class="pa-pill pa-pill--amber">En attente</span></td>
                                    <td class="ops-msg">Appliqué au prochain passage complet.</td>
                                    <td></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </section>
            <?php endif; ?>

            <?php if (is_array($opsMigrationOutput ?? null)): ?>
                <section class="pa-card" id="ops-output">
                    <div class="ops-card-head">
                        <h2 class="ops-health__title">Sortie de l’étape <code><?= $h($opsMigrationOutput['step']) ?></code></h2>
                        <a class="pa-btn pa-btn--ghost pa-btn--sm" href="<?= $h($base . '?onglet=migrations') ?>">Fermer</a>
                    </div>
                    <pre class="ops-console"><?= $opsMigrationOutput['text'] !== '' ? $h($opsMigrationOutput['text']) : 'Aucune sortie enregistrée pour cette étape.' ?></pre>
                </section>
            <?php endif; ?>

            <?php
            $problemSteps = array_values(array_filter($steps, static fn ($s) => in_array($results[$s['id']]['status'] ?? '', ['error', 'warn'], true)));
            $stepTable = static function (array $list) use ($results, $h, $base, $csrf, $ago, $toneClass): void {
                ?>
                <div class="pa-table-wrap">
                    <table class="pa-table ops-table">
                        <thead><tr><th>#</th><th>Étape</th><th>Dernier résultat</th><th>Quand</th><th>Message</th><th class="ops-right">Actions</th></tr></thead>
                        <tbody>
                        <?php foreach ($list as $s): ?>
                            <?php
                            $r = $results[$s['id']] ?? null;
                            [$tone, $label] = migrations_web_status_tag($r['status'] ?? null);
                            ?>
                            <tr class="<?= ($r['status'] ?? '') === 'error' ? 'is-bad' : '' ?>">
                                <td class="ops-num"><?= (int) $s['index'] ?></td>
                                <td><strong><?= $h($s['label']) ?></strong><br><code class="ops-sub"><?= $h($s['id']) ?></code></td>
                                <td><span class="pa-pill pa-pill--<?= $toneClass($tone) ?>"><?= $h($label) ?></span></td>
                                <td class="ops-nowrap"><?= $h($r !== null ? $ago($r['ran_at']) : '—') ?></td>
                                <td class="ops-msg"><?= $h($r['message'] ?? '') ?></td>
                                <td class="ops-right ops-nowrap">
                                    <?php if ($r !== null): ?>
                                        <a class="pa-btn pa-btn--ghost pa-btn--sm" href="<?= $h($base . '?onglet=migrations&sortie=' . rawurlencode($s['id']) . '#ops-output') ?>">Sortie</a>
                                    <?php endif; ?>
                                    <?php if (!empty($s['rerun'])): ?>
                                        <form method="post" action="<?= $h($base . '/migrations/lancer') ?>" class="ops-inline" data-ops-confirm="Relancer seulement « <?= $h($s['label']) ?> » ?">
                                            <?= $csrf ?>
                                            <input type="hidden" name="mode" value="run_step">
                                            <input type="hidden" name="step" value="<?= $h($s['id']) ?>">
                                            <button class="pa-btn pa-btn--line pa-btn--sm" type="submit">Relancer</button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php
            };
            ?>
            <section class="pa-card" id="ops-steps">
                <h2 class="ops-health__title">Étapes à regarder</h2>
                <?php if ($problemSteps === []): ?>
                    <p class="pa-empty">Aucune étape en erreur ni en avertissement au dernier passage.</p>
                <?php else: ?>
                    <?php $stepTable($problemSteps); ?>
                <?php endif; ?>
                <details class="ops-details ops-details--inner">
                    <summary>Toutes les étapes du pipeline (<?= count($steps) ?>)</summary>
                    <?php $stepTable($steps); ?>
                </details>
            </section>

            <?php $tail = is_array($m['log_tail'] ?? null) ? $m['log_tail'] : []; ?>
            <?php if ($tail !== []): ?>
                <details class="pa-card ops-details">
                    <summary>Journal du dernier passage (<?= count($tail) ?> dernières lignes sur <?= (int) ($m['log_lines'] ?? 0) ?>)</summary>
                    <pre class="ops-console"><?= $h(implode("\n", $tail)) ?></pre>
                </details>
            <?php endif; ?>
            <p class="ops-links">En ligne de commande sur le VPS : <code>php run-migrations.php</code> (tout), <code>--status</code> (fichiers SQL), <code>--list</code>, <code>--step=identifiant</code>, <code>--replay=fichier.sql</code>. Accès de secours sans le site : <code>/run-migrations.php</code> et son mot de passe.</p>

<?php elseif ($tab === 'sauvegardes'): ?>
            <?php $rows = is_array($opsBackups ?? null) ? $opsBackups : []; ?>
            <section class="pa-card ops-run">
                <div>
                    <p class="pa-card__kicker">Nouvelle sauvegarde</p>
                    <h2 class="pa-card__title">Copier la base et les fichiers</h2>
                    <p class="pa-card__help">
                        Rangée dans <code><?= $h($opsBackupRoot ?? '') ?></code>, hors du dossier public. Les <?= (int) ($opsBackupKeep ?? 10) ?> plus récentes sont gardées.
                        Une sauvegarde de la base est aussi prise avant chaque passage de migrations, chaque restauration et chaque suppression de compte immédiate.
                    </p>
                </div>
                <form method="post" action="<?= $h($base . '/sauvegardes/creer') ?>" class="ops-run__form">
                    <?= $csrf ?>
                    <label class="ops-field">Nom court <input type="text" name="label" maxlength="40" placeholder="avant-mise-a-jour"></label>
                    <label class="ops-check-input"><input type="checkbox" name="database" value="1" checked> Base de données</label>
                    <label class="ops-check-input"><input type="checkbox" name="files" value="1" checked> Fichiers envoyés (images, documents)</label>
                    <button class="pa-btn pa-btn--solid" type="submit">Créer la sauvegarde</button>
                </form>
            </section>

            <section class="pa-card">
                <h2 class="ops-health__title">Sauvegardes disponibles</h2>
                <?php if ($rows === []): ?>
                    <p class="pa-empty">Aucune sauvegarde pour l’instant.</p>
                <?php else: ?>
                    <ul class="ops-backups">
                        <?php foreach ($rows as $b): ?>
                            <?php
                            $id = (string) ($b['id'] ?? '');
                            $hasDb = !empty($b['database']['included']);
                            $hasFiles = !empty($b['storage']['included']);
                            ?>
                            <li class="ops-backup">
                                <div class="ops-backup__main">
                                    <strong><?= $h($when((string) ($b['created_at'] ?? ''))) ?></strong>
                                    <?php if (($b['label'] ?? '') !== ''): ?><span class="pa-pill pa-pill--slate"><?= $h($b['label']) ?></span><?php endif; ?>
                                    <span class="ops-sub"><?= $h($id) ?></span>
                                </div>
                                <div class="ops-backup__meta">
                                    <?= $hasDb ? '<span class="pa-pill pa-pill--blue">Base · ' . $h(PlatformStorageService::formatBytes((int) ($b['database']['bytes'] ?? 0))) . '</span>' : '' ?>
                                    <?= $hasFiles ? '<span class="pa-pill pa-pill--violet">Fichiers · ' . (int) ($b['storage']['file_count'] ?? 0) . '</span>' : '' ?>
                                    <span class="ops-sub"><?= $h(PlatformStorageService::formatBytes((int) ($b['size'] ?? 0))) ?> sur le disque<?= ($b['git_sha'] ?? '') !== '' ? ' · code ' . $h(substr((string) $b['git_sha'], 0, 7)) : '' ?></span>
                                </div>
                                <div class="ops-backup__actions">
                                    <form method="post" action="<?= $h($base . '/sauvegardes/verifier') ?>" class="ops-inline"><?= $csrf ?><input type="hidden" name="id" value="<?= $h($id) ?>"><button class="pa-btn pa-btn--ghost pa-btn--sm" type="submit">Vérifier</button></form>
                                    <?php if ($hasDb): ?>
                                        <form method="post" action="<?= $h($base . '/sauvegardes/telecharger') ?>" class="ops-inline"><?= $csrf ?><input type="hidden" name="id" value="<?= $h($id) ?>"><input type="hidden" name="part" value="database"><button class="pa-btn pa-btn--ghost pa-btn--sm" type="submit">Base .sql.gz</button></form>
                                    <?php endif; ?>
                                    <?php if ($hasFiles && !empty($opsZipAvailable)): ?>
                                        <form method="post" action="<?= $h($base . '/sauvegardes/telecharger') ?>" class="ops-inline"><?= $csrf ?><input type="hidden" name="id" value="<?= $h($id) ?>"><input type="hidden" name="part" value="files"><button class="pa-btn pa-btn--ghost pa-btn--sm" type="submit">Fichiers .zip</button></form>
                                    <?php endif; ?>
                                </div>
                                <details class="ops-danger">
                                    <summary>Restaurer ou supprimer</summary>
                                    <form method="post" action="<?= $h($base . '/sauvegardes/restaurer') ?>" class="ops-danger__form">
                                        <?= $csrf ?>
                                        <input type="hidden" name="id" value="<?= $h($id) ?>">
                                        <p>La restauration remplace les données actuelles par celles du <?= $h($when((string) ($b['created_at'] ?? ''))) ?>. L’état actuel est d’abord sauvegardé, pour pouvoir revenir en arrière. Les membres connectés peuvent être déconnectés.</p>
                                        <?php if ($hasDb): ?><label class="ops-check-input"><input type="checkbox" name="database" value="1" checked> Base de données</label><?php endif; ?>
                                        <?php if ($hasFiles): ?><label class="ops-check-input"><input type="checkbox" name="files" value="1"> Fichiers</label><?php endif; ?>
                                        <label class="ops-field">Tapez <strong><?= $h($opsRestoreWord) ?></strong> <input type="text" name="confirm" autocomplete="off" required data-ops-typed="<?= $h($opsRestoreWord) ?>"></label>
                                        <button class="pa-btn pa-btn--danger" type="submit" disabled>Restaurer</button>
                                    </form>
                                    <form method="post" action="<?= $h($base . '/sauvegardes/supprimer') ?>" class="ops-danger__form">
                                        <?= $csrf ?>
                                        <input type="hidden" name="id" value="<?= $h($id) ?>">
                                        <label class="ops-field">Supprimer cette sauvegarde : tapez <strong><?= $h($opsDeleteBackupWord) ?></strong> <input type="text" name="confirm" autocomplete="off" required data-ops-typed="<?= $h($opsDeleteBackupWord) ?>"></label>
                                        <button class="pa-btn pa-btn--danger" type="submit" disabled>Supprimer</button>
                                    </form>
                                </details>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </section>
            <p class="ops-links">En ligne de commande : <code>php scripts/data-snapshot.php create</code>, <code>list</code>, <code>verify ID</code>, <code>restore ID</code>.</p>

<?php elseif ($tab === 'donnees'): ?>
            <?php
            $del = is_array($opsDeletions ?? null) ? $opsDeletions : ['open' => [], 'done' => []];
            $accounts = is_array($opsAccounts ?? null) ? $opsAccounts : [];
            $q = (string) ($opsQuery ?? '');
            ?>
            <section class="pa-card">
                <p class="pa-card__kicker">Supprimer un compte</p>
                <h2 class="pa-card__title">Trouver le compte</h2>
                <p class="pa-card__help">
                    Par défaut la suppression est <strong>programmée</strong> : le compte ne peut plus se connecter tout de suite et s’efface à la date choisie.
                    Jusque-là, « Annuler » le rétablit comme avant. « Supprimer maintenant » l’efface sans attendre, après une sauvegarde de la base.
                </p>
                <form method="get" action="<?= $h($base) ?>" class="ops-search">
                    <input type="hidden" name="onglet" value="donnees">
                    <label class="ops-sr" for="ops-q">Adresse, nom ou numéro</label>
                    <input id="ops-q" type="search" name="q" value="<?= $h($q) ?>" placeholder="Adresse e-mail, nom affiché ou n° de compte" minlength="2" required>
                    <button class="pa-btn pa-btn--solid" type="submit">Chercher</button>
                </form>
                <?php if ($q !== '' && $accounts === []): ?>
                    <p class="pa-empty">Aucun compte ne correspond à « <?= $h($q) ?> ».</p>
                <?php endif; ?>
                <?php if ($accounts !== []): ?>
                    <ul class="ops-accounts">
                        <?php foreach ($accounts as $a): ?>
                            <?php $isAdmin = !empty($a['is_platform_admin']); ?>
                            <li class="ops-account">
                                <div class="ops-account__who">
                                    <strong><?= $h($a['display_name'] !== '' ? $a['display_name'] : '(sans nom)') ?></strong>
                                    <span class="ops-sub"><?= $h($a['email']) ?> · n° <?= (int) $a['id'] ?> · <?= $h($a['tenant_name'] ?? '—') ?></span>
                                    <span class="ops-sub">Créé le <?= $h($when((string) ($a['created_at'] ?? ''))) ?> · dernière connexion <?= $h($ago((string) ($a['last_login_at'] ?? ''))) ?></span>
                                </div>
                                <div class="ops-account__state">
                                    <span class="pa-pill pa-pill--<?= ($a['status'] ?? '') === 'active' ? 'mint' : 'slate' ?>"><?= $h(($a['status'] ?? '') === 'active' ? 'Actif' : 'Inactif') ?></span>
                                    <?php if (!empty($a['deletion_scheduled_at'])): ?><span class="pa-pill pa-pill--amber">Suppression le <?= $h($when((string) $a['deletion_scheduled_at'])) ?></span><?php endif; ?>
                                    <?php if ($isAdmin): ?><span class="pa-pill pa-pill--violet">Admin du site</span><?php endif; ?>
                                </div>
                                <?php if (!$isAdmin && (int) $a['id'] !== (int) \App\Core\Session::get('user_id')): ?>
                                    <details class="ops-danger">
                                        <summary>Supprimer…</summary>
                                        <form method="post" action="<?= $h($base . '/comptes/programmer') ?>" class="ops-danger__form">
                                            <?= $csrf ?>
                                            <input type="hidden" name="user_id" value="<?= (int) $a['id'] ?>">
                                            <input type="hidden" name="tenant_id" value="<?= (int) $a['tenant_id'] ?>">
                                            <input type="hidden" name="q" value="<?= $h($q) ?>">
                                            <label class="ops-field">Supprimer dans <input type="number" name="days" min="1" max="<?= (int) $opsMaxDays ?>" value="<?= (int) $opsDefaultDays ?>"> jours</label>
                                            <label class="ops-field ops-field--grow">Motif (journal d’audit) <input type="text" name="reason" maxlength="500" placeholder="Demande par Discord, doublon…"></label>
                                            <button class="pa-btn pa-btn--line" type="submit">Programmer la suppression</button>
                                        </form>
                                        <form method="post" action="<?= $h($base . '/comptes/supprimer') ?>" class="ops-danger__form">
                                            <?= $csrf ?>
                                            <input type="hidden" name="user_id" value="<?= (int) $a['id'] ?>">
                                            <input type="hidden" name="tenant_id" value="<?= (int) $a['tenant_id'] ?>">
                                            <input type="hidden" name="q" value="<?= $h($q) ?>">
                                            <label class="ops-field ops-field--grow">Supprimer maintenant : recopiez <strong><?= $h($a['email']) ?></strong> <input type="text" name="confirm" autocomplete="off" required data-ops-typed="<?= $h(strtolower((string) $a['email'])) ?>" data-ops-typed-ci="1"></label>
                                            <button class="pa-btn pa-btn--danger" type="submit" disabled>Supprimer maintenant</button>
                                        </form>
                                    </details>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </section>

            <section class="pa-card">
                <h2 class="ops-health__title">Suppressions programmées</h2>
                <?php if ($del['open'] === []): ?>
                    <p class="pa-empty">Aucune suppression en attente.</p>
                <?php else: ?>
                    <ul class="ops-accounts">
                        <?php foreach ($del['open'] as $d): ?>
                            <li class="ops-account">
                                <div class="ops-account__who">
                                    <strong><?= $h($d['display_name'] !== '' ? $d['display_name'] : $d['email']) ?></strong>
                                    <span class="ops-sub"><?= $h($d['email']) ?> · n° <?= (int) $d['user_id'] ?><?= $d['reason'] !== '' ? ' · ' . $h($d['reason']) : '' ?></span>
                                </div>
                                <div class="ops-account__state">
                                    <span class="pa-pill pa-pill--amber">Effacé le <?= $h(date('d/m/Y H:i', (int) strtotime((string) $d['execute_after']))) ?></span>
                                </div>
                                <div class="ops-backup__actions">
                                    <form method="post" action="<?= $h($base . '/comptes/annuler') ?>" class="ops-inline">
                                        <?= $csrf ?><input type="hidden" name="id" value="<?= (int) $d['id'] ?>">
                                        <button class="pa-btn pa-btn--solid pa-btn--sm" type="submit">Annuler et rétablir</button>
                                    </form>
                                </div>
                                <details class="ops-danger">
                                    <summary>Supprimer maintenant…</summary>
                                    <form method="post" action="<?= $h($base . '/comptes/supprimer') ?>" class="ops-danger__form">
                                        <?= $csrf ?><input type="hidden" name="id" value="<?= (int) $d['id'] ?>">
                                        <label class="ops-field ops-field--grow">Recopiez <strong><?= $h($d['email']) ?></strong> <input type="text" name="confirm" autocomplete="off" required data-ops-typed="<?= $h(strtolower((string) $d['email'])) ?>" data-ops-typed-ci="1"></label>
                                        <button class="pa-btn pa-btn--danger" type="submit" disabled>Supprimer maintenant</button>
                                    </form>
                                </details>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
                <?php if ($del['done'] !== []): ?>
                    <details class="ops-details">
                        <summary>Historique (<?= count($del['done']) ?> dernières)</summary>
                        <ul class="ops-history">
                            <?php foreach ($del['done'] as $d): ?>
                                <li>
                                    <?= $d['executed_at'] !== null ? '<span class="pa-pill pa-pill--rose">Supprimé</span>' : '<span class="pa-pill pa-pill--mint">Annulé</span>' ?>
                                    <?= $h($d['email']) ?> · <?= $h($when((string) ($d['executed_at'] ?? $d['cancelled_at']))) ?>
                                    <?= !empty($d['snapshot_id']) ? '<span class="ops-sub">sauvegarde ' . $h($d['snapshot_id']) . '</span>' : '' ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </details>
                <?php endif; ?>
            </section>

            <div class="ops-grid">
                <a class="pa-card ops-tile" href="<?= $h(url('admin/tenants')) ?>">
                    <strong>Communautés</strong>
                    <span>Archiver (se défait d’un clic), restaurer, puis supprimer pour de bon avec sauvegarde de ses données.</span>
                </a>
                <a class="pa-card ops-tile" href="<?= $h(url('admin/users')) ?>">
                    <strong>Annuaire des comptes</strong>
                    <span>Retirer d’une communauté, bannir, fusionner, demandes de suppression des membres.</span>
                </a>
                <a class="pa-card ops-tile" href="<?= $h(url('admin/system/storage')) ?>">
                    <strong>Historiques volumineux</strong>
                    <span>Vider les journaux et traces anciennes pour libérer de la place.</span>
                </a>
                <a class="pa-card ops-tile" href="<?= $h(url('admin/system/donnees')) ?>">
                    <strong>Registre des données</strong>
                    <span>Comptes, joueurs, ATAK et sessions en tableaux : corrélations, export, purge ciblée.</span>
                </a>
            </div>
<?php endif; ?>
<?php endif; ?>
        </div>
    </div>
</div>
<script>
(function () {
    document.querySelectorAll('form[data-ops-confirm]').forEach(function (form) {
        form.addEventListener('submit', function (e) {
            if (!window.confirm(form.getAttribute('data-ops-confirm'))) { e.preventDefault(); }
        });
    });
    document.querySelectorAll('input[data-ops-typed]').forEach(function (input) {
        var btn = input.form ? input.form.querySelector('button[type="submit"]') : null;
        var want = input.getAttribute('data-ops-typed');
        var ci = input.hasAttribute('data-ops-typed-ci');
        var check = function () {
            var v = input.value.trim();
            if (btn) { btn.disabled = (ci ? v.toLowerCase() : v.toUpperCase()) !== want; }
        };
        input.addEventListener('input', check);
        check();
    });
})();
</script>
